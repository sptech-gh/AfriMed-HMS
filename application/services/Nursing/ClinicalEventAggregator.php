<?php

class ClinicalEventAggregator
{
    public function buildPatientTimeline(array $datasets, $limit = 30)
    {
        $events = array_merge(
            $this->vitalsEvents(isset($datasets['vitals']) ? $datasets['vitals'] : array()),
            $this->noteEvents(isset($datasets['notes']) ? $datasets['notes'] : array()),
            $this->procedureEvents(isset($datasets['procedures']) ? $datasets['procedures'] : array()),
            $this->medicationEvents(isset($datasets['medications']) ? $datasets['medications'] : array()),
            $this->intakeOutputEvents(isset($datasets['intake_output']) ? $datasets['intake_output'] : array()),
            $this->clinicalContextEvents(isset($datasets['clinical_context']) && is_array($datasets['clinical_context']) ? $datasets['clinical_context'] : array())
        );
        usort($events, array($this, 'sortEvents'));
        return array_slice($events, 0, (int)$limit);
    }

    private function vitalsEvents(array $rows)
    {
        $result = array();
        foreach ($rows as $row) {
            $parts = array();
            if (isset($row['bp']) && trim((string)$row['bp']) !== '') $parts[] = 'BP ' . $row['bp'];
            if (isset($row['temperature']) && trim((string)$row['temperature']) !== '') $parts[] = 'Temp ' . $row['temperature'];
            if (isset($row['pulse']) && trim((string)$row['pulse']) !== '') $parts[] = 'Pulse ' . $row['pulse'];
            if (isset($row['respiratory_rate']) && trim((string)$row['respiratory_rate']) !== '') $parts[] = 'Resp ' . $row['respiratory_rate'];
            if (isset($row['spo2']) && trim((string)$row['spo2']) !== '') $parts[] = 'SpO2 ' . $row['spo2'];
            $result[] = array(
                'type' => 'vital',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => null,
                'summary' => count($parts) > 0 ? implode(' · ', $parts) : 'Vitals recorded',
                'raw_data_reference' => array('table' => 'iop_vital_parameters', 'id' => isset($row['vital_id']) ? $row['vital_id'] : null)
            );
        }
        return $result;
    }

    private function noteEvents(array $rows)
    {
        $result = array();
        foreach ($rows as $row) {
            $focus = isset($row['focus']) && trim((string)$row['focus']) !== '' ? (string)$row['focus'] . ': ' : '';
            $result[] = array(
                'type' => 'note',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => null,
                'summary' => $focus . (isset($row['note']) ? (string)$row['note'] : 'Nursing note recorded'),
                'raw_data_reference' => array('table' => 'iop_nurse_notes', 'id' => isset($row['note_id']) ? $row['note_id'] : null)
            );
        }
        return $result;
    }

    private function procedureEvents(array $rows)
    {
        $result = array();
        foreach ($rows as $row) {
            $name = isset($row['procedure_name']) && trim((string)$row['procedure_name']) !== '' ? (string)$row['procedure_name'] : 'Bedside procedure';
            $severity = isset($row['severity_level']) && trim((string)$row['severity_level']) !== '' ? strtoupper((string)$row['severity_level']) : 'ROUTINE';
            $outcome = isset($row['outcome_status']) && trim((string)$row['outcome_status']) !== '' ? strtoupper((string)$row['outcome_status']) : 'SUCCESS';
            $indication = isset($row['clinical_indication']) && trim((string)$row['clinical_indication']) !== '' ? (string)$row['clinical_indication'] : 'Unknown indication';
            $followUp = isset($row['follow_up_required']) && $row['follow_up_required'] ? 'YES' : 'No';
            $summary = '[PROCEDURE] ' . $name . ' (' . $severity . ') · Outcome: ' . $outcome . ' · Indication: ' . $indication . ' · Follow-up: ' . $followUp;
            $result[] = array(
                'type' => 'procedure',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => isset($row['actor']) ? $row['actor'] : null,
                'summary' => $summary,
                'severity_level' => $severity,
                'outcome_status' => $outcome,
                'clinical_indication' => $indication,
                'follow_up_required' => isset($row['follow_up_required']) ? (bool)$row['follow_up_required'] : false,
                'procedure_type' => isset($row['procedure_type']) ? $row['procedure_type'] : 'other',
                'raw_data_reference' => array('table' => 'iop_bed_side_procedure', 'id' => isset($row['procedure_id']) ? $row['procedure_id'] : null)
            );
        }
        return $result;
    }

    private function medicationEvents(array $rows)
    {
        $result = array();
        foreach ($rows as $row) {
            $name = isset($row['medication_name']) && trim((string)$row['medication_name']) !== '' ? (string)$row['medication_name'] : 'Medication';
            $status = isset($row['status']) && trim((string)$row['status']) !== '' ? strtoupper((string)$row['status']) : 'RECORDED';
            $dose = isset($row['dose_given']) && trim((string)$row['dose_given']) !== '' ? ' · Dose ' . (string)$row['dose_given'] : '';
            $result[] = array(
                'type' => 'medication',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => isset($row['actor']) ? $row['actor'] : null,
                'summary' => $name . ' · ' . $status . $dose,
                'raw_data_reference' => array('table' => 'iop_medication_administration', 'id' => isset($row['administration_id']) ? $row['administration_id'] : null)
            );
        }
        return $result;
    }

    private function intakeOutputEvents(array $rows)
    {
        $result = array();
        foreach ($rows as $row) {
            $type = isset($row['io_type']) && $row['io_type'] === 'output' ? 'output' : 'intake';
            $summary = isset($row['summary']) && trim((string)$row['summary']) !== '' ? (string)$row['summary'] : ucfirst($type) . ' recorded';
            $result[] = array(
                'type' => 'io',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => isset($row['actor']) ? $row['actor'] : null,
                'summary' => strtoupper($type) . ' · ' . $summary,
                'raw_data_reference' => array('table' => isset($row['table']) ? $row['table'] : null, 'id' => isset($row['record_id']) ? $row['record_id'] : null)
            );
        }
        return $result;
    }

    private function clinicalContextEvents(array $context)
    {
        $events = array();

        $complaints = isset($context['complaints']) && is_array($context['complaints']) ? $context['complaints'] : array();
        foreach ($complaints as $row) {
            $label = isset($row['complaint']) && trim((string)$row['complaint']) !== '' ? (string)$row['complaint'] : 'Presenting complaint recorded';
            $severity = isset($row['severity']) && trim((string)$row['severity']) !== '' ? strtoupper((string)$row['severity']) : 'OPD';
            $events[] = array(
                'type' => 'complaint',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => isset($row['recorded_by_name']) ? $row['recorded_by_name'] : null,
                'summary' => 'Complaint: ' . $label,
                'severity_level' => $severity,
                'raw_data_reference' => array('table' => 'iop_complaints', 'id' => isset($row['complaint_id']) ? $row['complaint_id'] : null)
            );
        }

        $diagnoses = isset($context['diagnoses']) && is_array($context['diagnoses']) ? $context['diagnoses'] : array();
        foreach ($diagnoses as $row) {
            $label = isset($row['diagnosis']) && trim((string)$row['diagnosis']) !== '' ? (string)$row['diagnosis'] : 'Diagnosis recorded';
            $events[] = array(
                'type' => 'diagnosis',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => null,
                'summary' => 'Diagnosis: ' . $label,
                'severity_level' => 'DOCTOR',
                'raw_data_reference' => array('table' => 'iop_diagnosis', 'id' => isset($row['diagnosis_id']) ? $row['diagnosis_id'] : null)
            );
        }

        $prescriptions = isset($context['prescriptions']) && is_array($context['prescriptions']) ? $context['prescriptions'] : array();
        foreach ($prescriptions as $row) {
            $name = isset($row['medication']) && trim((string)$row['medication']) !== '' ? (string)$row['medication'] : 'Medication';
            $dose = isset($row['dosage']) && trim((string)$row['dosage']) !== '' ? ' · ' . (string)$row['dosage'] : '';
            $events[] = array(
                'type' => 'prescription',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => isset($row['prescriber']) ? $row['prescriber'] : null,
                'summary' => 'Prescribed: ' . $name . $dose,
                'severity_level' => 'MEDICATION',
                'raw_data_reference' => array('table' => 'iop_medication', 'id' => isset($row['prescription_id']) ? $row['prescription_id'] : null)
            );
        }

        $labs = isset($context['labs']) && is_array($context['labs']) ? $context['labs'] : array();
        foreach ($labs as $row) {
            $test = isset($row['test_name']) && trim((string)$row['test_name']) !== '' ? (string)$row['test_name'] : 'Lab test';
            $isCompleted = isset($row['status']) && $row['status'] === 'completed';
            $events[] = array(
                'type' => $isCompleted ? 'lab_result' : 'lab_request',
                'timestamp' => isset($row['recorded_at']) ? $row['recorded_at'] : null,
                'actor' => isset($row['doctor']) ? $row['doctor'] : null,
                'summary' => ($isCompleted ? 'Lab resulted: ' : 'Lab requested: ') . $test,
                'severity_level' => $isCompleted ? 'RESULTED' : 'PENDING',
                'raw_data_reference' => array('table' => 'iop_laboratory', 'id' => isset($row['lab_id']) ? $row['lab_id'] : null)
            );
        }

        $detention = isset($context['detention']) && is_array($context['detention']) ? $context['detention'] : array();
        if (isset($detention['is_detained']) && $detention['is_detained']) {
            $events[] = array(
                'type' => 'detention',
                'timestamp' => isset($detention['detention_start_at']) ? $detention['detention_start_at'] : null,
                'actor' => null,
                'summary' => 'OPD detention/observation started',
                'severity_level' => 'ATTENTION',
                'raw_data_reference' => array('table' => 'patient_details_iop', 'id' => isset($detention['encounter_id']) ? $detention['encounter_id'] : null)
            );
        }
        if (isset($detention['converted_to_admission']) && $detention['converted_to_admission']) {
            $ipd = isset($detention['converted_ipd_iop_id']) ? (string)$detention['converted_ipd_iop_id'] : '';
            $events[] = array(
                'type' => 'admission',
                'timestamp' => isset($detention['converted_to_admission_at']) ? $detention['converted_to_admission_at'] : null,
                'actor' => null,
                'summary' => 'OPD converted to IPD encounter ' . $ipd,
                'severity_level' => 'ATTENTION',
                'raw_data_reference' => array('table' => 'patient_details_iop', 'id' => $ipd !== '' ? $ipd : null)
            );
        }

        return $events;
    }

    public function sortEvents($a, $b)
    {
        $ta = isset($a['timestamp']) ? strtotime((string)$a['timestamp']) : 0;
        $tb = isset($b['timestamp']) ? strtotime((string)$b['timestamp']) : 0;
        if ($ta == $tb) return 0;
        return ($ta > $tb) ? -1 : 1;
    }
}
