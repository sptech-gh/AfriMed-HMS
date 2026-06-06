<?php

class CiSnapshotRepository implements SnapshotRepositoryInterface
{
    protected $db;
    protected $table = 'clinical_state_snapshots';

    public function __construct($db = null)
    {
        if ($db !== null) {
            $this->db = $db;
            return;
        }

        $CI = &get_instance();
        $this->db = $CI->db;
    }

    public function findSnapshot($iopId, $type, $key)
    {
        return $this->db
            ->where('iop_id', $iopId)
            ->where('snapshot_type', $type)
            ->where('snapshot_key', $key)
            ->order_by('generated_from_stream_version', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    public function saveSnapshot($iopId, $type, $data, $streamVersion): void
    {
        $this->db->insert($this->table, array(
            'iop_id' => $iopId,
            'snapshot_type' => $type,
            'snapshot_key' => isset($data['snapshot_key']) ? $data['snapshot_key'] : '',
            'computed_state_json' => json_encode($data),
            'generated_from_stream_version' => (int)$streamVersion,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function isFresh($snapshot, $currentStreamVersion): bool
    {
        if (!is_array($snapshot) || !isset($snapshot['generated_from_stream_version'])) {
            return false;
        }

        return (int)$snapshot['generated_from_stream_version'] >= (int)$currentStreamVersion;
    }
}
