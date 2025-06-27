<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBookingTransactionRequest;
use App\Models\HomeService;
use Illuminate\Support\Carbon;
use App\Models\BookingTransaction;
use App\Http\Resources\Api\BookingTransactionApiResource;

class BookingTransactionController extends Controller
{
    public function store(StoreBookingTransactionRequest $request)
    {
        try {
            // Validasi input
            $validatedData = $request->validated();

            // Upload file bukti jika ada
            if ($request->hasFile('proof')) {
                $filePath = $request->file('proof')->store('proofs', 'public');
                $validatedData['proof'] = $filePath;
            }

            // Ambil service_ids dan cek validitasnya
            $serviceIds = $request->input('service_ids');
            if (empty($serviceIds)) {
                return response()->json(['message' => 'Layanan belum dipilih.'], 400);
            }

            $services = HomeService::whereIn('id', $serviceIds)->get();
            if ($services->isEmpty()) {
                return response()->json(['message' => 'Layanan tidak valid.'], 400);
            }

            // Hitung total harga dan pajak
            $totalPrice = $services->sum('price');
            $tax = 0.11 * $totalPrice;
            $grandTotal = $totalPrice + $tax;

            // Mengisi data yang tidak diinput user
            $validatedData['scheduled_at'] = Carbon::tomorrow()->toDateString();
            $validatedData['total_amount'] = $grandTotal;
            $validatedData['total_tax_amount'] = $tax;
            $validatedData['sub_total'] = $totalPrice;
            $validatedData['is_paid'] = false;
            $validatedData['booking_trx_id'] = BookingTransaction::generateUniqueTrxId();

            // Simpan booking transaksi
            $bookingTransaction = BookingTransaction::create($validatedData);

            // Cek apakah berhasil tersimpan
            if (!$bookingTransaction) {
                return response()->json(['message' => 'Gagal membuat booking transaksi.'], 500);
            }

            // Simpan detail transaksi per service
            foreach ($services as $service) {
                $bookingTransaction->transactionDetails()->create([
                    'home_service_id' => $service->id,
                    'price' => $service->price,
                ]);
            }

            // Kembalikan respon sukses dengan data lengkap
            return new BookingTransactionApiResource($bookingTransaction->load('transactionDetails'));
        } catch (\Exception $e) {
            // Untuk memudahkan debugging, tampilkan pesan error
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses permintaan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function booking_details(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'booking_trx_id' => 'required|string'
        ]);

        $booking = BookingTransaction::where('email', $request->email)
            ->where('booking_trx_id', $request->booking_trx_id)
            ->with([
                'transactionDetails',
                'transactionDetails.homeService'
            ])
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        return new BookingTransactionApiResource($booking);
    }
}
