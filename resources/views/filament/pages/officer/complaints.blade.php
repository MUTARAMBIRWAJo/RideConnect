<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-red-600 to-orange-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-red-100">Support & Escalations</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Complaints & Tickets</h1>
            <p class="mt-2 max-w-2xl text-sm text-red-100 sm:text-base">
                Handle passenger complaints, driver disputes, and platform issues. Track resolution status.
            </p>
        </section>

        <!-- Stats Row -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-dashboard-card title="Total Complaints" :value="number_format($totalComplaints)" subtitle="All time" tone="red" />
            <x-dashboard-card title="Open Issues" :value="number_format($openComplaints)" subtitle="Need attention" tone="orange" />
            <x-dashboard-card title="Resolved" :value="number_format($resolvedComplaints)" subtitle="This period" tone="green" />
        </section>

        <!-- Complaints Table -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Support Tickets</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-3 pr-3">ID</th>
                            <th class="py-3 pr-3">Type</th>
                            <th class="py-3 pr-3">Customer</th>
                            <th class="py-3 pr-3">Status</th>
                            <th class="py-3 pr-3">Priority</th>
                            <th class="py-3 pr-3">Created</th>
                            <th class="py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($complaints as $complaint)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="py-3 pr-3 font-mono text-slate-700">#{{ $complaint['id'] ?? '-' }}</td>
                                <td class="py-3 pr-3 text-xs">
                                    <span class="inline-block rounded-full bg-purple-100 px-2 py-1 text-purple-700">
                                        {{ ucfirst(str_replace('_', ' ', $complaint['type'] ?? 'Other')) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-slate-700">{{ $complaint['customer_name'] ?? 'Unknown' }}</td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full {{ in_array($complaint['status'] ?? '', ['resolved', 'RESOLVED', 'closed', 'CLOSED']) ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }} px-2 py-1 text-xs font-medium">
                                        {{ strtoupper($complaint['status'] ?? 'OPEN') }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full {{ in_array($complaint['priority'] ?? '', ['high', 'HIGH', 'urgent', 'URGENT']) ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700' }} px-2 py-1 text-xs font-medium">
                                        {{ strtoupper($complaint['priority'] ?? 'NORMAL') }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-slate-600 text-xs">{{ \Carbon\Carbon::parse($complaint['created_at'])->format('M d, H:i') ?? '-' }}</td>
                                <td class="py-3">
                                    <div class="flex gap-2">
                                        <button class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 transition">Review</button>
                                        <button class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200 transition">Resolve</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500 text-sm">No complaints at this moment. ✓</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Guidelines -->
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs text-amber-900">
                ⚠️ <strong>Resolution Protocol:</strong> Review complaint details, contact involved parties if needed.
                Mark as Reviewed when assessed. Resolve only after proper investigation and customer agreement.
            </p>
        </section>
    </div>
</x-filament-panels::page>
