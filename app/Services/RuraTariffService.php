<?php

namespace App\Services;

use App\Models\RuraTariff;
use App\Helpers\RuraHelper;

class RuraTariffService
{
    /**
     * Lookup a RURA tariff row by route code, origin, destination, and/or corridor.
     * Returns null if not found.
     */
    public function lookupTariff(
        string|int|null $routeCode = null,
        ?string $originStop = null,
        ?string $destinationStop = null,
        ?string $corridor = null
    ): ?array {
        $corridorNorm = RuraHelper::norm($corridor ?? '');

        if ($routeCode !== null && trim((string)$routeCode) !== '') {
            $codeNorm = RuraHelper::norm((string)$routeCode);
            $row = RuraTariff::query()
                ->whereRaw('UPPER(route_code) = ?', [$codeNorm])
                ->when($corridorNorm, fn($q) => $q->whereRaw('UPPER(corridor) = ?', [$corridorNorm]))
                ->first();
            if ($row) {
                return $row->toArray() + ['source' => 'rura_official'];
            }
        }

        if ($originStop && $destinationStop) {
            $o = RuraHelper::norm($originStop);
            $d = RuraHelper::norm($destinationStop);
            $rows = RuraTariff::query()
                ->when($corridorNorm, fn($q) => $q->whereRaw('UPPER(corridor) = ?', [$corridorNorm]))
                ->get();
            foreach ($rows as $row) {
                $rowO = RuraHelper::norm($row->origin_stop);
                $rowD = RuraHelper::norm($row->destination_stop);
                if (($rowO === $o && $rowD === $d) || ($rowO === $d && $rowD === $o)) {
                    return $row->toArray() + ['source' => 'rura_official'];
                }
            }
        }
        return null;
    }
}
