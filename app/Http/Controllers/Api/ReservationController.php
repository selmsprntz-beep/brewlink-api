<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationController extends Controller
{


    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:100',
            'contact_number' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'party_size' => 'required|integer|min:1|max:30',
            'notes' => 'nullable|string|max:500',
        ]);

        $reservation = Reservation::create($data + [
            'reference_code' => $this->makeReferenceCode(),
            'status' => 'pending',
        ]);

        return response()->json($reservation, 201);
    }

    // PUBLIC: customer checks the status using the reference code
    public function track(string $referenceCode): JsonResponse
    {
        $reservation = Reservation::where('reference_code', strtoupper($referenceCode))->firstOrFail();

        return response()->json($reservation);
    }

 
    public function index(Request $request): JsonResponse
    {
        $reservations = Reservation::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('reservation_date', $request->date))
            ->orderBy('reservation_date')
            ->orderBy('reservation_time')
            ->limit(200)
            ->get();

        return response()->json($reservations);
    }

    public function updateStatus(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,declined,cancelled,completed',
        ]);

        $reservation->update($data);

        return response()->json($reservation);
    }

    private function makeReferenceCode(): string
    {
        do {
            $code = 'RSV-' . strtoupper(Str::random(6));
        } while (Reservation::where('reference_code', $code)->exists());

        return $code;
    }
}
