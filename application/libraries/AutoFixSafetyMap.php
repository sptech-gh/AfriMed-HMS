<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AutoFixSafetyMap
{
    const ZONE_SAFE = 'SAFE';
    const ZONE_CONTROLLED = 'CONTROLLED';
    const ZONE_BLOCKED = 'BLOCKED';

    public static function zone($actionKey)
    {
        $k = strtolower(trim((string)$actionKey));

        if ($k === 'create_mapping') {
            return self::ZONE_SAFE;
        }

        // Current taxonomy: action_key derived from action strings in Phase 8.1
        // e.g. IMPORT_TARIFF -> import_tariff
        if ($k === 'import_tariff') {
            return self::ZONE_CONTROLLED;
        }

        if ($k === 'normalize_service_code') {
            return self::ZONE_SAFE;
        }

        if ($k === 'recalculate_claim_total' || $k === 'override_payment_amount') {
            return self::ZONE_BLOCKED;
        }

        return self::ZONE_BLOCKED;
    }
}
