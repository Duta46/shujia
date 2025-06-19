<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBookingTransactionRequest;
use App\Models\HomeService;
use Illuminate\Support\Carbon;
use App\Models\BookingTransaction;

class BookingTransactionController extends Controller
{
    public function store(StoreBookingTransactionRequest $request)
    {
        try {
            //validate request data
            $validatedData = $request->validated();

            //Handle file upload
            if ($request->hasFile('proof')) {
                $filePath = $request->file('proof')->store('proofs', 'public');
                $validatedData['proof'] = $filePath;
            }

            $serviceIds = $request->input('services_ids');

            if (empty($serviceIds)) {
                return response()->json(['message' => 'No services selected.'], 400);
            };

            $services = HomeService::whereIn('id', $serviceIds)->get();

            if ($services->isEmpty()) {
                return response()->json(['message' => 'Invalid Services.'], 400);
            }

            $totalPrice = $services->sum('price');
            $tax = 0.11 *  $totalPrice;
            $grandTotal = $totalPrice + $tax;

            $validatedData['scheduled_at'] = Carbon::tomorrow()->toDateString();

            $validatedData['total_amount'] = $grandTotal;
            $validatedData['total_tax_amount'] = $tax;
            $validatedData['sub_total'] = $totalPrice;
            $validatedData['is_paid'] = false;
            $validatedData['booking_trx_id'] = BookingTransaction::generateUniqueTrxId();

            $bookingTransaction = BookingTransaction::create($validatedData);

            if (!$bookingTransaction) {
                return response()->json(['message' => 'Failed to create booking transaction.'], 500);
            }

            foreach ($services as $service) {
                $bookingTransaction->transactionDetails()->create([
                    'home_service_id' => $service->id,
                    'price' => $service->price,
                ]);
            }

            return new BookingTransactionApiResource($bookingTransaction->load('transactionDetails'));
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while processing your request.'], 500);
        }
    }

    public function booking_details(Request $request){
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
