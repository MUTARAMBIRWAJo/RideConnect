<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Display system settings page.
     * GET /manager/settings
     */
    public function index()
    {
        $settings = Cache::get('system_settings', [
            'platform_name' => 'RideConnect',
            'commission_percentage' => 15.0,
            'currency' => 'RWF',
            'maintenance_mode' => false,
            'email_from_address' => 'noreply@rideconnect.com',
            'email_from_name' => 'RideConnect Support',
        ]);

        return view('manager.settings.index', compact('settings'));
    }

    /**
     * Update system settings.
     * PUT /manager/settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'platform_name' => 'required|string|max:100',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'currency' => 'required|string|in:RWF,EUR,GBP',
            'maintenance_mode' => 'boolean',
            'email_from_address' => 'required|email',
            'email_from_name' => 'required|string|max:100',
        ]);

        Cache::put('system_settings', $validated, now()->addHours(24));

        return redirect()->route('manager.settings.index')
            ->with('success', 'System settings updated successfully.');
    }

    /**
     * Toggle maintenance mode.
     * POST /manager/settings/maintenance
     */
    public function toggleMaintenance(Request $request)
    {
        $settings = Cache::get('system_settings', []);
        $settings['maintenance_mode'] = !$settings['maintenance_mode'];
        Cache::put('system_settings', $settings, now()->addHours(24));

        return response()->json([
            'success' => true,
            'maintenance_mode' => $settings['maintenance_mode'],
            'message' => $settings['maintenance_mode'] ? 'Maintenance mode enabled' : 'Maintenance mode disabled'
        ]);
    }
