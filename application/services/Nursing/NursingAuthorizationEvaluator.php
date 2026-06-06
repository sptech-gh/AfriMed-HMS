<?php

class NursingAuthorizationEvaluator
{
    public function evaluate(ClinicalMutationRequest $request)
    {
        $data = $request->toArray();
        $capabilities = isset($data['actor_capabilities']) && is_array($data['actor_capabilities']) ? $data['actor_capabilities'] : array();
        $role = strtoupper($request->actorRole());
        $warnings = array();
        $allowedRole = in_array($role, array('NURSE', 'SENIOR_NURSE', 'ADMIN', 'ADMINISTRATOR'), true);
        $hasCapability = in_array('NURSING_RECORD_VITALS', $capabilities, true) || in_array('NURSING_VITALS_RECORDED', $capabilities, true);

        if ($role === 'ADMIN' || $role === 'ADMINISTRATOR') {
            $warnings[] = array('field' => 'actor_role', 'code' => 'ADMIN_BREAK_GLASS_AUDIT_REQUIRED', 'message' => 'Administrator vitals action requires break-glass audit marking.');
        }

        if ($allowedRole && $hasCapability) {
            return array(
                'status' => 'PASS',
                'decision' => 'AUTHORIZED',
                'actor_role' => $role,
                'capabilities_observed' => $capabilities,
                'errors' => array(),
                'warnings' => $warnings,
            );
        }

        return array(
            'status' => 'BLOCK',
            'decision' => 'DENIED',
            'actor_role' => $role,
            'capabilities_observed' => $capabilities,
            'errors' => array(array('field' => 'actor', 'code' => 'NURSING_RECORD_VITALS_NOT_AUTHORIZED', 'message' => 'Actor is not authorized to record vitals.')),
            'warnings' => $warnings,
        );
    }
}
