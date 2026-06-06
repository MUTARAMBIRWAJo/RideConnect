<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RideCancellation;
use Illuminate\Http\Request;

class AdminCancellationController extends Controller
{
    /**
     * GET /admin/cancellations
     * Paginated cancellation list with reason filtering.
     */
    public function index(Request $request)
    {
        $query = RideCancellation::with(['trip.passenger', 'driver.user'])
            ->latest('cancelled_at');

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        $cancellations = $query->paginate(20)->withQueryString();

        // Reason options — MUST match Flutter CancellationReasonScreen keys exactly
        $passengerReasons = [
            'driver_too_long'       => 'Driver is taking too long',
            'wrong_driver_details'  => 'Wrong driver details',
            'changed_mind'          => 'Changed my mind',
            'emergency'             => 'Emergency',
            'other'                 => 'Other reason',
        ];
        $driverReasons = [
            'too_far'               => 'Passenger is too far',
            'wrong_direction'       => 'Wrong direction for me',
            'vehicle_issue'         => 'Vehicle issue',
            'no_show'               => 'Passenger did not show up',
            'other'                 => 'Other reason',
        ];

        return view('admin.cancellations.index', compact(
            'cancellations', 'passengerReasons', 'driverReasons'
        ));
    }

    /**
     * GET /admin/cancellations/{cancellation}
     * Cancellation detail view.
     */
    public function show(RideCancellation $cancellation)
    {
        $cancellation->load(['trip.passenger', 'driver.user']);
        return view('admin.cancellations.show', compact('cancellation'));
    }
}
