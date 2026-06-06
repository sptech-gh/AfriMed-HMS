<?php

class ClinicalReplayFactory
{
    public static function make()
    {
        self::loadDependencies();

        return new ClinicalReplayEngine(
            new CiClinicalEventRepository(),
            new CiDomainEventHydrator(),
            new CorrectionResolver(),
            new EffectiveStateBuilder()
        );
    }

    public static function loadDependencies(): void
    {
        $base = APPPATH . 'services/Clinical/';

        $files = array(
            'Contracts/ClinicalEventRepositoryInterface.php',
            'Contracts/ClinicalEventWriterInterface.php',
            'Contracts/ClinicalTransactionManagerInterface.php',
            'Contracts/DomainEventHydratorInterface.php',
            'Contracts/SnapshotRepositoryInterface.php',
            'Support/ReplayException.php',
            'Support/ReplayMode.php',
            'Support/ReplayOrdering.php',
            'ReadModels/ReplayEvent.php',
            'ReadModels/EffectiveClinicalState.php',
            'ReadModels/PatientReplayResult.php',
            'ReadModels/ShiftReplayResult.php',
            'ReadModels/TimeSliceReplayResult.php',
            'ReadModels/ClinicalWriteResult.php',
            'Replay/CorrectionResolver.php',
            'Replay/EffectiveStateBuilder.php',
            'Replay/TimelineBuilder.php',
            'Replay/ShiftReplayBuilder.php',
            'Replay/AuditReplayBuilder.php',
            'Repositories/CiClinicalEventRepository.php',
            'Repositories/CiClinicalEventWriter.php',
            'Repositories/CiDomainEventHydrator.php',
            'Repositories/CiSnapshotRepository.php',
            'Transactions/CiClinicalTransactionManager.php',
            'Write/IntakeService.php',
            'Replay/ClinicalReplayEngine.php',
        );

        foreach ($files as $file) {
            $path = $base . $file;
            if (file_exists($path)) {
                require_once($path);
            }
        }
    }
}
