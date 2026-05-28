<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use App\Models\Ticket;
use Filament\Widgets\Widget;

class OfficerTicketTrendWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.officer-ticket-trend-widget';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
    ];

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '120s');
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && (method_exists($user, 'can') && $user->can('manage tickets'));
    }

    protected function getViewData(): array
    {
        $openTickets = Ticket::whereIn('status', ['OPEN', 'open', 'PENDING', 'pending'])->count();
        $resolvedToday = Ticket::whereDate('updated_at', now()->toDateString())
            ->whereIn('status', ['RESOLVED', 'resolved', 'CLOSED', 'closed'])
            ->count();
        $averageResolutionTime = Ticket::whereIn('status', ['RESOLVED', 'resolved', 'CLOSED', 'closed'])
            ->whereDate('created_at', '>=', now()->subDays(30)->toDateString())
            ->get()
            ->map(fn ($ticket) => $ticket->updated_at->diffInHours($ticket->created_at))
            ->average();

        $byPriority = Ticket::whereIn('status', ['OPEN', 'open', 'PENDING', 'pending'])
            ->groupBy('priority')
            ->selectRaw('priority, count(*) as count')
            ->pluck('count', 'priority')
            ->toArray();

        return [
            'openTickets' => $openTickets,
            'resolvedToday' => $resolvedToday,
            'avgResolutionHours' => $averageResolutionTime ? round($averageResolutionTime, 1) : null,
            'byPriority' => $byPriority,
        ];
    }
}
