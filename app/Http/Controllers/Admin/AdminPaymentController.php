<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Trip;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    /**
     * GET /admin/payments
     * Paginated, filterable payment list.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['trip.passenger', 'trip.driver.user'])
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_method')) {
            // payment_method: 'cash' | 'momo' | 'card' — MUST match Flutter values
            $query->where('payment_method', $request->payment_method);
        }
        if ($request->filled('payment_provider')) {
            $query->where('payment_provider', $request->payment_provider);
        }

        $payments = $query->paginate(20)->withQueryString();

        // Enum options — match Flutter paymentMethod values
        $paymentMethods   = ['cash' => 'Cash', 'momo' => 'MoMo', 'card' => 'Card'];
        $paymentStatuses  = ['pending', 'paid', 'failed', 'refunded'];

        return view('admin.payments.index', compact('payments', 'paymentMethods', 'paymentStatuses'));
    }

    /**
     * GET /admin/payments/{payment}
     * Payment detail view.
     */
    public function show(Payment $payment)
    {
        $payment->load(['trip.passenger', 'trip.driver.user']);
        return view('admin.payments.show', compact('payment'));
    }

    /**
     * PUT /admin/payments/{payment}/mark-paid
     * Admin manually marks cash payment as paid.
     */
    public function markPaid(Request $request, Payment $payment)
    {
        $payment->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Sync the parent trip's payment_status
        if ($payment->trip_id) {
            Trip::where('id', $payment->trip_id)
                ->update(['payment_status' => 'paid', 'paid_to_driver_at' => now()]);
        }

        return back()->with('success', "Payment #{$payment->id} marked as paid.");
    }
}
