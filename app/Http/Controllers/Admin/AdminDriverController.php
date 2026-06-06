<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;

class AdminDriverController extends Controller
{
    public function show(Driver $driver)
    {
        $driver->load(['user', 'vehicle']);

        return view('admin.drivers.show', compact('driver'));
    }

    /**
     * GET /admin/drivers/{driver}/earnings
     * Driver earnings page maps to driver_wallets and driver_earnings tables.
     */
    public function earnings(Driver $driver)
    {
        $driver->load(['user', 'wallet', 'earningsRecords.trip']);

        $earnings = $driver->earningsRecords()
            ->with('trip')
            ->latest('created_at')
            ->paginate(20);

        return view('admin.drivers.earnings', compact('driver', 'earnings'));
    }

    /**
     * GET /admin/drivers/{driver}/behavior
     * Driver behavior stats map to driver_behaviors table.
     */
    public function behavior(Driver $driver)
    {
        $driver->load('user');

        $behaviors = $driver->behaviors()
            ->with('trip')
            ->latest('created_at')
            ->paginate(20);

        $latest = $driver->behaviors()->latest()->first();

        return view('admin.drivers.behavior', compact('driver', 'behaviors', 'latest'));
    }

    /**
     * PUT /admin/drivers/{driver}/availability
     * Admin toggle of driver online/offline status.
     */
    public function updateAvailability(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'availability_status' => 'required|in:online,offline,busy',
        ]);

        $driver->update($validated);

        return back()->with('success', "Driver availability updated to {$validated['availability_status']}.");
    }
}
