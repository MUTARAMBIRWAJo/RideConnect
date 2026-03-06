<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Add is_approved column if not exists
if (!Schema::hasColumn('users', 'is_approved')) {
    Schema::table('users', function ($table) {
        $table->boolean('is_approved')->default(false)->after('password');
    });
    echo "Added is_approved column\n";
}

// Add approved_by column if not exists
if (!Schema::hasColumn('users', 'approved_by')) {
    Schema::table('users', function ($table) {
        $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('is_approved');
    });
    echo "Added approved_by column\n";
}

// Add approved_at column if not exists
if (!Schema::hasColumn('users', 'approved_at')) {
    Schema::table('users', function ($table) {
        $table->timestamp('approved_at')->nullable()->after('approved_by');
    });
    echo "Added approved_at column\n";
}

echo "Done!\n";
