<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display the manager dashboard based on user role.
     */
    public function index()
    {
        $user = Auth::user();
        $role = $user->role;

        // Get role-specific data
        $data = $this->getDashboardData($role);

        return view('manager.dashboards.' . $role, [
            'user' => $user,
            'role' => $role,
            'data' => $data
        ]);
    }

    /**
     * Get dashboard data based on role
     */
    private function getDashboardData($role)
    {
        switch ($role) {
            case 'superadmin':
                return $this->getSuperAdminData();
            case 'admin':
                return $this->getAdminData();
            case 'accountant':
                return $this->getAccountantData();
            case 'officer':
                return $this->getOfficerData();
            default:
                return [];
        }
    }

    /**
     * Get SuperAdmin dashboard data
     */
    private function getSuperAdminData()
    {
        // In a real application, these would be actual database queries
        return [
            'total_users' => 2156,
            'total_riders' => 856,
            'total_passengers' => 1245,
            'total_rides' => 1234,
            'revenue_summary' => [
                'this_month' => 24560,
                'this_year' => 289450,
                'total' => 1250000
            ],
            'system_activity' => [
                ['action' => 'User registered', 'time' => '2 minutes ago', 'user' => 'John Doe'],
                ['action' => 'Ride completed', 'time' => '15 minutes ago', 'user' => 'Sarah Wilson'],
                ['action' => 'Payment processed', 'time' => '30 minutes ago', 'user' => 'System'],
            ]
        ];
    }

    /**
     * Get Admin dashboard data
     */
    private function getAdminData()
    {
        return [
            'total_users' => 2156,
            'pending_riders' => 23,
            'active_riders' => 833,
            'total_rides_today' => 156,
            'pending_approvals' => [
                ['name' => 'Michael Chen', 'email' => 'michael@example.com', 'date' => '2 hours ago'],
                ['name' => 'Emma Thompson', 'email' => 'emma@example.com', 'date' => '4 hours ago'],
            ],
            'recent_activity' => [
                ['action' => 'User approved', 'time' => '10 minutes ago', 'user' => 'Admin'],
                ['action' => 'Ride assigned', 'time' => '25 minutes ago', 'user' => 'System'],
            ]
        ];
    }

    /**
     * Get Accountant dashboard data
     */
    private function getAccountantData()
    {
        return [
            'revenue_today' => 1250,
            'revenue_this_month' => 24560,
            'revenue_this_year' => 289450,
            'pending_withdrawals' => 15000,
            'completed_withdrawals' => 45000,
            'monthly_earnings' => [
                ['month' => 'January', 'amount' => 22000],
                ['month' => 'February', 'amount' => 24560],
                ['month' => 'March', 'amount' => 18900],
            ],
            'recent_transactions' => [
                ['type' => 'Ride Payment', 'amount' => 45.50, 'date' => 'Today'],
                ['type' => 'Ride Payment', 'amount' => 23.80, 'date' => 'Today'],
                ['type' => 'Withdrawal', 'amount' => -1500, 'date' => 'Yesterday'],
            ]
        ];
    }

    /**
     * Get Officer dashboard data
     */
    private function getOfficerData()
    {
        return [
            'tickets_today' => 45,
            'pending_tickets' => 12,
            'validated_tickets' => 33,
            'total_tickets' => 156,
            'recent_tickets' => [
                ['id' => 'TKT001', 'passenger' => 'John Smith', 'status' => 'Validated', 'time' => '10:30 AM'],
                ['id' => 'TKT002', 'passenger' => 'Sarah Johnson', 'status' => 'Pending', 'time' => '10:45 AM'],
                ['id' => 'TKT003', 'passenger' => 'Michael Brown', 'status' => 'Validated', 'time' => '11:00 AM'],
            ],
            'quick_stats' => [
                ['label' => 'Today Tickets', 'value' => 45, 'icon' => 'bi-ticket-detailed', 'color' => 'blue'],
                ['label' => 'Pending', 'value' => 12, 'icon' => 'bi-clock', 'color' => 'yellow'],
                ['label' => 'Validated', 'value' => 33, 'icon' => 'bi-check-circle', 'color' => 'green'],
            ]
        ];
    }
}