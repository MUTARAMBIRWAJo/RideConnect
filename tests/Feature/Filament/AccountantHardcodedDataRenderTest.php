<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Accountant\AuditLogsPage;
use App\Filament\Pages\Accountant\RefundManagementPage;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountantHardcodedDataRenderTest extends TestCase
{
    public function test_accountant_pages_render_without_seeded_sample_rows(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::ACCOUNTANT->value,
            'is_approved' => true,
        ]);

        Role::findOrCreate('Accountant', 'web');
        $user->syncRoles(['Accountant']);

        $this->actingAs($user, 'web');

        Livewire::test(RefundManagementPage::class)
            ->assertSee('Refund Management')
            ->assertSee('No refund requests at this moment.')
            ->assertDontSee('1001')
            ->assertDontSee('15.50')
            ->assertDontSee('22.75');

        Livewire::test(AuditLogsPage::class)
            ->assertSee('Audit Logs')
            ->assertSee('No audit entries found.')
            ->assertDontSee('1001')
            ->assertDontSee('5.50')
            ->assertDontSee('Manual Review');
    }
}