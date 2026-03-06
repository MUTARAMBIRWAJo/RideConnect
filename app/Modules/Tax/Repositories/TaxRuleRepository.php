<?php

namespace App\Modules\Tax\Repositories;

use App\Models\TaxRule;
use App\Modules\Tax\Contracts\TaxRuleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaxRuleRepository implements TaxRuleRepositoryInterface
{
    public function getActiveRulesFor(string $appliesTo, string $jurisdiction = 'RW'): Collection
    {
        return TaxRule::active()
            ->forJurisdiction($jurisdiction)
            ->appliesTo($appliesTo)
            ->orderBy('percentage')
            ->get();
    }

    public function findByName(string $name, string $jurisdiction = 'RW'): ?TaxRule
    {
        return TaxRule::where('tax_name', $name)
            ->where('jurisdiction', $jurisdiction)
            ->active()
            ->first();
    }

    public function create(array $data): TaxRule
    {
        return TaxRule::create($data);
    }

    public function deactivate(int $id): void
    {
        TaxRule::where('id', $id)->update(['active' => false]);
    }
}
