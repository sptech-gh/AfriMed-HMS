<?php

class IntakeService
{
    protected $transactions;
    protected $writer;

    public function __construct(ClinicalTransactionManagerInterface $transactions, ClinicalEventWriterInterface $writer)
    {
        $this->transactions = $transactions;
        $this->writer = $writer;
    }

    public function record(array $input)
    {
        $normalized = $this->normalize($input);
        $this->validate($normalized);

        return $this->transactions->run($normalized['iop_id'], $normalized['idempotency_key'], function () use ($normalized) {
            $streamVersion = $this->writer->nextStreamVersion($normalized['iop_id']);
            $event = array(
                'iop_id' => $normalized['iop_id'],
                'patient_no' => $normalized['patient_no'],
                'domain' => 'INTAKE',
                'event_type' => 'INTAKE_RECORDED',
                'event_timestamp' => $normalized['recorded_at'],
                'stream_version' => $streamVersion,
                'status' => 'ACTIVE',
                'shift_id' => $normalized['shift_id'],
                'shift_date' => $normalized['shift_date'],
                'actor_user_id' => $normalized['actor_user_id'],
                'idempotency_key' => $normalized['idempotency_key'],
                'payload_json' => json_encode($normalized['payload']),
                'created_at' => $normalized['created_at'],
            );

            $eventId = $this->writer->appendEvent($event);
            $domainRow = array(
                'event_id' => $eventId,
                'iop_id' => $normalized['iop_id'],
                'patient_no' => $normalized['patient_no'],
                'recorded_at' => $normalized['recorded_at'],
                'particulars' => $normalized['payload']['particulars'],
                'iv_fluids_ml' => $normalized['payload']['iv_fluids_ml'],
                'oral_ml' => $normalized['payload']['oral_ml'],
                'blood_ml' => $normalized['payload']['blood_ml'],
                'total_ml' => $normalized['payload']['total_ml'],
                'actor_user_id' => $normalized['actor_user_id'],
                'created_at' => $normalized['created_at'],
            );
            $domainId = $this->writer->insertDomainRow('nursing_intake', $domainRow);

            return new ClinicalWriteResult($eventId, $streamVersion, $domainId, 'INTAKE', 'INTAKE_RECORDED');
        });
    }

    protected function normalize(array $input): array
    {
        $recordedAt = $this->stringOrDefault($input, 'recorded_at', date('Y-m-d H:i:s'));
        $createdAt = $this->stringOrDefault($input, 'created_at', date('Y-m-d H:i:s'));
        $iv = $this->decimalValue(isset($input['iv_fluids_ml']) ? $input['iv_fluids_ml'] : (isset($input['IV_fluids']) ? $input['IV_fluids'] : 0));
        $oral = $this->decimalValue(isset($input['oral_ml']) ? $input['oral_ml'] : (isset($input['oral']) ? $input['oral'] : 0));
        $blood = $this->decimalValue(isset($input['blood_ml']) ? $input['blood_ml'] : (isset($input['blood']) ? $input['blood'] : 0));

        return array(
            'iop_id' => $this->stringOrDefault($input, 'iop_id', ''),
            'patient_no' => $this->stringOrDefault($input, 'patient_no', ''),
            'actor_user_id' => $this->stringOrDefault($input, 'actor_user_id', ''),
            'shift_id' => $this->stringOrDefault($input, 'shift_id', ''),
            'shift_date' => $this->stringOrDefault($input, 'shift_date', ''),
            'recorded_at' => $recordedAt,
            'created_at' => $createdAt,
            'idempotency_key' => $this->stringOrDefault($input, 'idempotency_key', ''),
            'payload' => array(
                'particulars' => $this->stringOrDefault($input, 'particulars', $this->stringOrDefault($input, 'particular', '')),
                'iv_fluids_ml' => $iv,
                'oral_ml' => $oral,
                'blood_ml' => $blood,
                'total_ml' => $iv + $oral + $blood,
            ),
        );
    }

    protected function validate(array $data): void
    {
        if ($data['iop_id'] === '') {
            throw new InvalidArgumentException('iop_id_required');
        }
        if ($data['actor_user_id'] === '') {
            throw new InvalidArgumentException('actor_user_id_required');
        }
        if ($data['idempotency_key'] === '') {
            throw new InvalidArgumentException('idempotency_key_required');
        }
        if ($data['payload']['total_ml'] <= 0) {
            throw new InvalidArgumentException('positive_intake_required');
        }
    }

    protected function stringOrDefault(array $input, $key, $default)
    {
        if (!isset($input[$key])) {
            return $default;
        }
        return trim((string)$input[$key]);
    }

    protected function decimalValue($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return (float)$value;
    }
}
