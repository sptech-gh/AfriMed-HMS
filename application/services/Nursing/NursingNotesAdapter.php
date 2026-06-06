<?php

class NursingNotesAdapter
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function record($iopId, $patientNo, $actorUserId, $idempotencyKey, array $input, $transactions)
    {
        $normalized = $this->normalize($input);
        $this->validate($normalized);

        return $transactions->execute($iopId, $idempotencyKey, function () use ($iopId, $patientNo, $actorUserId, $normalized) {
            return $this->persist($iopId, $patientNo, $actorUserId, $normalized);
        });
    }

    protected function normalize(array $input)
    {
        $focus = '';
        if (isset($input['focus'])) {
            $focus = trim((string)$input['focus']);
        }

        $noteText = '';
        foreach (array('note_text', 'note', 'notes') as $key) {
            if (isset($input[$key]) && trim((string)$input[$key]) !== '') {
                $noteText = (string)$input[$key];
                break;
            }
        }

        $noteText = str_replace("\r\n", "\n", $noteText);
        $noteText = str_replace("\r", "\n", $noteText);
        $noteText = trim($noteText);

        return array(
            'focus' => $focus,
            'normalized_note_text' => $noteText,
        );
    }

    protected function validate(array $data)
    {
        $text = isset($data['normalized_note_text']) ? (string)$data['normalized_note_text'] : '';
        if (trim($text) === '') {
            throw new InvalidArgumentException('note_text_required');
        }
        if (strlen($text) > 4000) {
            throw new InvalidArgumentException('note_text_too_long');
        }
    }

    protected function persist($iopId, $patientNo, $actorUserId, array $normalized)
    {
        if (!$this->db->table_exists('iop_nurse_notes')) {
            throw new RuntimeException('notes_table_missing');
        }

        $now = date('Y-m-d H:i:s');
        $row = array(
            'iop_id' => (string)$iopId,
            'dDate' => date('Y-m-d'),
            'dDateTime' => $now,
            'focus' => (string)$normalized['focus'],
            'notes' => (string)$normalized['normalized_note_text'],
            'cPreparedBy' => (string)$actorUserId,
            'InActive' => 0,
        );

        if (!$this->db->insert('iop_nurse_notes', $row)) {
            throw new RuntimeException('note_insert_failed');
        }

        $noteId = $this->db->insert_id();

        return array(
            'status' => 'success',
            'code' => 'NOTE_SAVED',
            'message' => 'Note saved.',
            'encounter_id' => (string)$iopId,
            'patient_no' => (string)$patientNo,
            'note_id' => (int)$noteId,
            'normalized_note_text' => (string)$normalized['normalized_note_text'],
            'refresh_required' => true,
            'governance_observed' => true,
            'warnings' => array(),
            'errors' => array(),
        );
    }
}
