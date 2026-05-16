<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_approved')) {
            return;
        }

        $updates = [
            'is_approved' => true,
        ];

        if (Schema::hasColumn('users', 'approved_at')) {
            $updates['approved_at'] = now();
        }

        if (Schema::hasColumn('users', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('users')
            ->whereIn('role', ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT', 'OFFICER'])
            ->where(function ($query): void {
                $query->where('is_approved', false)
                    ->orWhereNull('is_approved');
            })
            ->update($updates);
    }
};
