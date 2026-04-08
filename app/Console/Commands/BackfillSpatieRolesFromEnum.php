<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BackfillSpatieRolesFromEnum extends Command
{
    protected $signature = 'roles:backfill-spatie
        {--dry-run : Show planned changes without writing}
        {--only-missing : Only update users with no assigned Spatie role}
        {--chunk=200 : Number of users processed per chunk}';

    protected $description = 'Sync Spatie roles from users.role enum values for manager users';

    public function handle(): int
    {
        $roleMap = [
            UserRole::SUPER_ADMIN->value => 'Super_admin',
            UserRole::ADMIN->value => 'Admin',
            UserRole::ACCOUNTANT->value => 'Accountant',
            UserRole::OFFICER->value => 'Officer',
        ];

        $dryRun = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');
        $chunkSize = max(1, (int) $this->option('chunk'));

        foreach (array_values($roleMap) as $spatieRoleName) {
            Role::findOrCreate($spatieRoleName, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $processed = 0;
        $updated = 0;
        $unchanged = 0;

        $query = User::query()->whereIn('role', array_keys($roleMap));

        if ($onlyMissing) {
            $query->whereDoesntHave('roles');
        }

        $query->orderBy('id')->chunkById($chunkSize, function ($users) use ($roleMap, $dryRun, &$processed, &$updated, &$unchanged): void {
            foreach ($users as $user) {
                $processed++;
                $enumRole = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
                $targetRole = $roleMap[$enumRole] ?? null;

                if ($targetRole === null) {
                    $unchanged++;
                    continue;
                }

                $currentRoles = $user->getRoleNames()->values()->all();

                if (count($currentRoles) === 1 && $currentRoles[0] === $targetRole) {
                    $unchanged++;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf('[DRY RUN] %s: [%s] -> [%s]', $user->email, implode(', ', $currentRoles), $targetRole));
                } else {
                    $user->syncRoles([$targetRole]);
                    $this->line(sprintf('Synced %s -> %s', $user->email, $targetRole));
                }

                $updated++;
            }
        }, 'id');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('Spatie role backfill summary:');
        $this->line("Processed: {$processed}");
        $this->line("Updated: {$updated}");
        $this->line("Unchanged: {$unchanged}");

        return self::SUCCESS;
    }
}
