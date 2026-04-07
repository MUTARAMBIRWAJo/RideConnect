<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-orange-600 to-red-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-orange-100">Dispute Resolution</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Refund Management</h1>
            <p class="mt-2 max-w-2xl text-sm text-orange-100 sm:text-base">
                Process refund requests, adjust fares, and manage customer/driver disputes.
            </p>
        </section>

        <!-- Refund Stats -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-dashboard-card title="Total Refunded" :value="'$' . number_format($totalRefundAmount, 2)" subtitle="All time" tone="orange" />
            <x-dashboard-card title="Pending Refunds" :value="number_format($pendingRefunds)" subtitle="Awaiting approval" tone="red" />
            <x-dashboard-card title="Approved Refunds" :value="number_format($approvedRefunds)" subtitle="Already processed" tone="green" />
        </section>

        <!-- Refund Requests Table -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Refund Requests</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-3 pr-3">ID</th>
                            <th class="py-3 pr-3">Ride</th>
                            <th class="py-3 pr-3">Amount</th>
                            <th class="py-3 pr-3">Reason</th>
                            <th class="py-3 pr-3">Status</th>
                            <th class="py-3 pr-3">Date</th>
                            <th class="py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($refundRequests as $refund)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="py-3 pr-3 font-mono text-slate-700">#{{ $refund['id'] ?? '-' }}</td>
                                <td class="py-3 pr-3 text-slate-900">Ride #{{ $refund['ride_id'] ?? '-' }}</td>
                                <td class="py-3 pr-3 font-semibold text-slate-900">${{ number_format($refund['amount'] ?? 0, 2) }}</td>
                                <td class="py-3 pr-3 text-xs text-slate-600">{{ substr($refund['reason'] ?? 'No reason provided', 0, 30) }}...</td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full {{ in_array($refund['status'] ?? '', ['pending', 'PENDING']) ? 'bg-yellow-100 text-yellow-700' : (in_array($refund['status'] ?? '', ['approved', 'APPROVED', 'completed', 'COMPLETED']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') }} px-2 py-1 text-xs font-medium">
                                        {{ strtoupper($refund['status'] ?? 'UNKNOWN') }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-xs text-slate-600">{{ \Carbon\Carbon::parse($refund['created_at'])->format('M d, H:i') ?? '-' }}</td>
                                <td class="py-3">
                                    @if (in_array($refund['status'] ?? '', ['pending', 'PENDING']))
                                        <div class="flex gap-1">
                                            <x-filament::modal width="md">
                                                <x-slot name="trigger">
                                                    <button type="button" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200 transition">Approve</button>
                                                </x-slot>

                                                <div class="space-y-4">
                                                    <p class="text-sm text-slate-700">Approve this refund request?</p>
                                                    <div class="flex justify-end">
                                                        <x-filament::button size="sm" color="success" wire:click="approveRefund({{ (int) ($refund['id'] ?? 0) }})">Confirm Approve</x-filament::button>
                                                    </div>
                                                </div>
                                            </x-filament::modal>

                                            <x-filament::modal width="md">
                                                <x-slot name="trigger">
                                                    <button type="button" class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200 transition">Reject</button>
                                                </x-slot>

                                                <div class="space-y-4">
                                                    <p class="text-sm text-slate-700">Reject this refund request?</p>
                                                    <div class="flex justify-end">
                                                        <x-filament::button size="sm" color="danger" wire:click="rejectRefund({{ (int) ($refund['id'] ?? 0) }})">Confirm Reject</x-filament::button>
                                                    </div>
                                                </div>
                                            </x-filament::modal>
                                        </div>
                                    @else
                                        <a href="{{ \App\Filament\Pages\Accountant\TransactionsPage::getUrl(panel: 'accountant') }}?ride={{ $refund['ride_id'] ?? '' }}" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 transition">Details</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500 text-sm">No refund requests at this moment. ✓</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Fare Adjustment Section -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Manual Fare Adjustment</h2>
            <form class="space-y-4" wire:submit.prevent="adjustFare">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-2">Ride ID</label>
                        <input type="number" wire:model.defer="adjustRideId" placeholder="Enter ride ID" class="w-full px-3 py-2 border border-slate-200 rounded text-sm focus:outline-none focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-2">New Fare Amount</label>
                        <input type="number" wire:model.defer="adjustFareAmount" placeholder="0.00" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded text-sm focus:outline-none focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-2">Reason</label>
                        <select wire:model.defer="adjustReason" class="w-full px-3 py-2 border border-slate-200 rounded text-sm focus:outline-none focus:border-blue-500">
                            <option value="customer_complaint">Customer complaint</option>
                            <option value="system_error">System error</option>
                            <option value="loyalty_adjustment">Loyalty adjustment</option>
                            <option value="promotion_credit">Promotion credit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition">
                        Apply Fare Adjustment
                    </button>
                </div>
            </form>
        </section>

        <!-- Policy Info -->
        <section class="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
            <p class="text-xs text-yellow-900">
                ⚠️ <strong>Refund Policy:</strong> All refunds should be approved by management unless they fall within
                automated refund criteria. Document all decisions thoroughly. Maintain audit trail for compliance.
            </p>
        </section>
    </div>
</x-filament-panels::page>
