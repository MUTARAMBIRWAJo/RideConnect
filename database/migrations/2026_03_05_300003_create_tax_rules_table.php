<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('tax_name', 100);            // e.g. "Rwanda VAT", "Withholding Tax"
            $table->decimal('percentage', 6, 4);        // e.g. 18.0000 for 18%
            $table->enum('applies_to', ['ride', 'commission', 'payout', 'all']);
            $table->string('jurisdiction', 100)->default('RW'); // ISO country code or region
            $table->boolean('active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tax_name', 'jurisdiction', 'applies_to']);
            $table->index(['jurisdiction', 'applies_to', 'active']);

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // Seed default Rwanda tax rules
        DB::table('tax_rules')->insert([
            [
                'tax_name'       => 'Rwanda VAT',
                'percentage'     => '18.0000',
                'applies_to'     => 'ride',
                'jurisdiction'   => 'RW',
                'active'         => true,
                'effective_from' => '2024-01-01',
                'description'    => 'Value Added Tax at 18% on passenger ride fares (RRA compliance)',
                'created_by'     => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'tax_name'       => 'Withholding Tax on Commission',
                'percentage'     => '15.0000',
                'applies_to'     => 'commission',
                'jurisdiction'   => 'RW',
                'active'         => true,
                'effective_from' => '2024-01-01',
                'description'    => 'WHT at 15% on platform commission earnings (RRA compliance)',
                'created_by'     => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'tax_name'       => 'Withholding Tax on Payouts',
                'percentage'     => '5.0000',
                'applies_to'     => 'payout',
                'jurisdiction'   => 'RW',
                'active'         => true,
                'effective_from' => '2024-01-01',
                'description'    => 'WHT at 5% on driver payout disbursements',
                'created_by'     => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
