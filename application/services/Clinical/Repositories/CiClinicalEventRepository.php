<?php

class CiClinicalEventRepository implements ClinicalEventRepositoryInterface
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

    public function getStreamByIopId($iopId): array
    {
        return $this->db
            ->where('iop_id', $iopId)
            ->order_by('stream_version', 'ASC')
            ->order_by('event_id', 'ASC')
            ->get('clinical_events')
            ->result_array();
    }

    public function getStreamAtTime($iopId, $cutoff): array
    {
        return $this->db
            ->where('iop_id', $iopId)
            ->where('event_timestamp <=', $cutoff)
            ->order_by('stream_version', 'ASC')
            ->order_by('event_id', 'ASC')
            ->get('clinical_events')
            ->result_array();
    }

    public function getStreamForShift($iopId, $shiftId, $shiftDate): array
    {
        return $this->db
            ->where('iop_id', $iopId)
            ->where('shift_id', $shiftId)
            ->where('shift_date', $shiftDate)
            ->order_by('stream_version', 'ASC')
            ->order_by('event_id', 'ASC')
            ->get('clinical_events')
            ->result_array();
    }
}
