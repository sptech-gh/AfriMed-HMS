<?php

class ClinicalContextPanelComposer
{
    public function compose(array $workspacePayload)
    {
        $context = isset($workspacePayload['clinical_context']) && is_array($workspacePayload['clinical_context']) ? $workspacePayload['clinical_context'] : array();
        $complaints = isset($context['complaints']) && is_array($context['complaints']) ? $context['complaints'] : array();
        $diagnoses = isset($context['diagnoses']) && is_array($context['diagnoses']) ? $context['diagnoses'] : array();
        $prescriptions = isset($context['prescriptions']) && is_array($context['prescriptions']) ? $context['prescriptions'] : array();
        $labs = isset($context['labs']) && is_array($context['labs']) ? $context['labs'] : array();
        $history = isset($context['history']) && is_array($context['history']) ? $context['history'] : array();
        $detention = isset($context['detention']) && is_array($context['detention']) ? $context['detention'] : array();

        return array(
            'title' => 'Clinical Context',
            'items' => array(
                'complaints' => $complaints,
                'diagnoses' => $diagnoses,
                'prescriptions' => $prescriptions,
                'labs' => $labs,
                'history' => $history,
                'detention' => $detention
            ),
            'meta' => array(
                'complaint_count' => count($complaints),
                'diagnosis_count' => count($diagnoses),
                'prescription_count' => count($prescriptions),
                'lab_count' => count($labs),
                'pending_lab_count' => $this->countLabsByStatus($labs, 'pending'),
                'resulted_lab_count' => $this->countLabsByStatus($labs, 'completed'),
                'risk_alert_count' => $this->countRiskAlerts($history, $detention)
            ),
            'empty_state' => 'No OPD clinical context found for this encounter.'
        );
    }

    private function countLabsByStatus(array $labs, $status)
    {
        $count = 0;
        foreach ($labs as $lab) {
            if (isset($lab['status']) && $lab['status'] === $status) {
                $count++;
            }
        }
        return $count;
    }

    private function countRiskAlerts(array $history, array $detention)
    {
        $count = 0;
        foreach (array('allergies', 'warnings', 'chronic_conditions') as $key) {
            if (isset($history[$key]) && trim((string)$history[$key]) !== '') {
                $count++;
            }
        }
        if (isset($detention['is_detained']) && $detention['is_detained']) {
            $count++;
        }
        if (isset($detention['converted_to_admission']) && $detention['converted_to_admission']) {
            $count++;
        }
        return $count;
    }
}
