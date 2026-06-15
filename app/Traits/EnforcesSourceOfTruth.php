<?php

namespace App\Traits;

use App\Exceptions\SourceOfTruthViolationException;
use App\Services\Sync\PostgresSyncContext;

trait EnforcesSourceOfTruth
{
    /**
     * Guard writes to ensure restricted database fields are not updated
     * directly in Firebase outside the designated sync job context.
     *
     * @param string $domain
     * @param array $data
     * @throws SourceOfTruthViolationException
     */
    protected function enforceSourceOfTruth(string $domain, array $data): void
    {
        if (PostgresSyncContext::isSyncing()) {
            return;
        }

        $restrictedFields = config("rideconnect.source_of_truth.{$domain}", []);

        foreach ($restrictedFields as $field) {
            // Check top-level keys
            if (array_key_exists($field, $data)) {
                throw SourceOfTruthViolationException::forField($domain, $field);
            }

            // Check nested structures (e.g. payment => ['status' => ...])
            foreach ($data as $key => $value) {
                if (is_array($value) && array_key_exists($field, $value)) {
                    throw SourceOfTruthViolationException::forField($domain, "{$key}.{$field}");
                }
            }
        }
    }
}
