<?php

class BedsideProcedureClinicalEnricher
{
    public function enrich($procedureName, $notes = '', $actor = null, $recordedAt = null)
    {
        $procedureName = trim((string)$procedureName);
        $notes = trim((string)$notes);
        $type = $this->normalizeProcedureType($procedureName . ' ' . $notes);
        $outcome = $this->inferOutcomeStatus($notes);
        $severity = $this->classifySeverity($type, $procedureName . ' ' . $notes);
        $indication = $this->inferClinicalIndication($type, $procedureName, $notes);
        $followUp = $this->requiresFollowUp($type, $outcome, $notes);

        return array(
            'procedure_type' => $type,
            'procedure_summary' => $procedureName !== '' ? $procedureName : 'Bedside procedure',
            'severity_level' => $severity,
            'clinical_indication' => $indication,
            'outcome_status' => $outcome,
            'risk_flag' => in_array($severity, array('HIGH', 'EMERGENCY'), true) || in_array($outcome, array('FAILED', 'COMPLICATED'), true),
            'follow_up_required' => $followUp,
            'recorded_by' => $actor,
            'timestamp' => $recordedAt
        );
    }

    private function normalizeProcedureType($text)
    {
        $text = strtolower((string)$text);
        if ($this->containsAny($text, array('emergency', 'resuscitation', 'cpr', 'collapse'))) return 'emergency_intervention';
        if ($this->containsAny($text, array('dressing', 'wound care', 'wound dressing'))) return 'dressing_wound';
        if ($this->containsAny($text, array('iv', 'cannula', 'cannulation', 'line'))) return 'iv_cannulation';
        if ($this->containsAny($text, array('catheter', 'catheterization', 'urinary catheter'))) return 'catheterization';
        if ($this->containsAny($text, array('injection', 'administered injection', 'im injection', 'sc injection'))) return 'injection_administration_support';
        if ($this->containsAny($text, array('nebulization', 'nebulisation', 'nebuli', 'nebulizer'))) return 'nebulization';
        if ($this->containsAny($text, array('blood sample', 'sample collection', 'venepuncture', 'venipuncture', 'phlebotomy'))) return 'blood_sample_collection';
        if ($this->containsAny($text, array('debridement'))) return 'wound_debridement_support';
        if ($this->containsAny($text, array('oxygen', 'o2', 'nasal prong', 'face mask'))) return 'oxygen_support';
        return 'other';
    }

    private function classifySeverity($type, $text)
    {
        $text = strtolower((string)$text);
        if ($type === 'emergency_intervention') return 'EMERGENCY';
        if ($this->containsAny($text, array('complicated', 'bleeding', 'failed'))) return 'HIGH';
        if (in_array($type, array('catheterization', 'iv_cannulation'), true)) return 'MEDIUM';
        return 'ROUTINE';
    }

    private function inferOutcomeStatus($notes)
    {
        $notes = strtolower((string)$notes);
        if ($this->containsAny($notes, array('complicated', 'complication', 'bleeding'))) return 'COMPLICATED';
        if ($this->containsAny($notes, array('failed', 'unsuccessful', 'not successful'))) return 'FAILED';
        if ($this->containsAny($notes, array('partial', 'partially'))) return 'PARTIAL';
        return 'SUCCESS';
    }

    private function inferClinicalIndication($type, $procedureName, $notes)
    {
        $text = strtolower(trim((string)$procedureName . ' ' . (string)$notes));
        if ($this->containsAny($text, array('poor venous access', 'difficult vein'))) return 'Poor venous access';
        if ($this->containsAny($text, array('bleeding', 'wound', 'dressing'))) return 'Wound dressing follow-up';
        if ($this->containsAny($text, array('respiratory distress', 'shortness of breath', 'oxygen', 'nebulization', 'nebulisation'))) return 'Respiratory distress support';
        if ($type === 'iv_cannulation') return 'Venous access support';
        if ($type === 'catheterization') return 'Urinary drainage support';
        if ($type === 'blood_sample_collection') return 'Diagnostic sample collection';
        return 'Unknown indication';
    }

    private function requiresFollowUp($type, $outcome, $notes)
    {
        if (in_array($type, array('catheterization', 'dressing_wound', 'wound_debridement_support', 'emergency_intervention'), true)) return true;
        if (in_array($outcome, array('FAILED', 'COMPLICATED'), true)) return true;
        return $this->containsAny(strtolower((string)$notes), array('follow up', 'review', 'bleeding', 'complicated'));
    }

    private function containsAny($text, array $needles)
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($text, $needle) !== false) return true;
        }
        return false;
    }
}
