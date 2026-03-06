<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApproveAllUsers extends Command
{
    protected $signature = 'users:approve-all';
    protected $description = 'Approve all existing users in the database';

    public function handle(): int
    {
        $count = User::where('is_approved', false)
            ->orWhereNull('is_approved')
            ->update([
                'is_approved' => true,
                'approved_by' => 1,
                'approved_at' => now(),
            ]);

        $this->info("Approved {$count} users successfully.");

        return Command::SUCCESS;
    }
}
