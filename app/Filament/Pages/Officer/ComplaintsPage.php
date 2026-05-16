<?php

namespace App\Filament\Pages\Officer;

use App\Enums\UserRole;
use App\Services\ActionAuditLogger;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComplaintsPage extends Page
{
    protected static ?string $navigationGroup = 'Support & Complaints';

    protected static string $view = 'filament.pages.officer.complaints';

    /** @var array<int, array<string, mixed>> */
    public array $complaints = [];

    public int $totalComplaints = 0;

    public int $openComplaints = 0;

    public int $resolvedComplaints = 0;

    public static function getNavigationLabel(): string
    {
        return 'Complaints & Tickets';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ($user->role === UserRole::OFFICER)
            || $user->hasAnyRole(['Officer', 'officer', 'OFFICER']);
    }

    public function getTitle(): string
    {
        return 'Support Complaints & Tickets';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadComplaints();
    }

    private function loadComplaints(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        $columns = collect(['id', 'type', 'status', 'priority', 'customer_name', 'ride_id', 'created_at', 'updated_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('tickets', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        $query = DB::table('tickets')->select($columns);

        $this->complaints = $query->latest('id')->get()->map(fn ($row): array => (array) $row)->all();

        $this->totalComplaints = count($this->complaints);
        $this->openComplaints = collect($this->complaints)->filter(fn ($c) => in_array($c['status'] ?? '', ['open', 'OPEN', 'pending', 'PENDING'], true))->count();
        $this->resolvedComplaints = collect($this->complaints)->filter(fn ($c) => in_array($c['status'] ?? '', ['resolved', 'RESOLVED', 'closed', 'CLOSED'], true))->count();
    }

    public function resolveComplaint(int $complaintId): void
    {
        if (! auth()->user()->can('manage tickets')) {
            abort(403);
        }

        $updates = ['status' => 'resolved'];
        if (Schema::hasColumn('tickets', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('tickets')
            ->where('id', $complaintId)
            ->update($updates);

        app(ActionAuditLogger::class)->log(
            'ticket.resolve',
            'Officer resolved complaint/ticket #'.$complaintId,
            ['ticket_id' => $complaintId],
        );

        $this->loadComplaints();

        Notification::make()
            ->title('Complaint resolved')
            ->success()
            ->send();
    }

    public function markReviewed(int $complaintId): void
    {
        if (! auth()->user()->can('manage tickets')) {
            abort(403);
        }

        $updates = ['status' => 'reviewed'];
        if (Schema::hasColumn('tickets', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('tickets')
            ->where('id', $complaintId)
            ->update($updates);

        app(ActionAuditLogger::class)->log(
            'ticket.review',
            'Officer marked complaint/ticket as reviewed #'.$complaintId,
            ['ticket_id' => $complaintId],
        );

        $this->loadComplaints();

        Notification::make()
            ->title('Complaint marked as reviewed')
            ->success()
            ->send();
    }
}
