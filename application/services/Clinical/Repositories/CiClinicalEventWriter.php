<?php

class CiClinicalEventWriter implements ClinicalEventWriterInterface
{
    protected $db;

    public function __construct($db = null)
    {
        if ($db !== null) {
            $this->db = $db;
            return;
        }

        $CI = &get_instance();
        $this->db = $CI->db;
    }

    public function nextStreamVersion($iopId)
    {
        $this->assertTableExists('clinical_events');

        $row = $this->db
            ->select('stream_version')
            ->where('iop_id', $iopId)
            ->order_by('stream_version', 'DESC')
            ->limit(1)
            ->get('clinical_events')
            ->row_array();

        if (!is_array($row) || !isset($row['stream_version'])) {
            return 1;
        }

        return ((int)$row['stream_version']) + 1;
    }

    public function appendEvent(array $event)
    {
        $this->assertTableExists('clinical_events');

        if (!$this->db->insert('clinical_events', $event)) {
            throw new RuntimeException('clinical_event_insert_failed');
        }

        $id = $this->db->insert_id();
        if ((int)$id <= 0) {
            throw new RuntimeException('clinical_event_id_missing');
        }

        return $id;
    }

    public function insertDomainRow($table, array $row)
    {
        $this->assertTableExists($table);

        if (!$this->db->insert($table, $row)) {
            throw new RuntimeException('clinical_domain_insert_failed');
        }

        $id = $this->db->insert_id();
        if ((int)$id <= 0) {
            throw new RuntimeException('clinical_domain_id_missing');
        }

        return $id;
    }

    protected function assertTableExists($table): void
    {
        if (!$this->db->table_exists($table)) {
            throw new RuntimeException('clinical_table_missing:' . $table);
        }
    }
}
