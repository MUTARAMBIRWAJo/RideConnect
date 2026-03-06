<?php

namespace App\Modules\Tax\Contracts;

use App\Models\TaxRule;
use Illuminate\Database\Eloquent\Collection;

interface TaxRuleRepositoryInterface
{
    public function getActiveRulesFor(string $appliesTo, string $jurisdiction = 'RW'): Collection;

    public function findByName(string $name, string $jurisdiction = 'RW'): ?TaxRule;

    public function create(array $data): TaxRule;

    public function deactivate(int $id): void;
}
