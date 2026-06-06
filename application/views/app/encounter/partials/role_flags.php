<?php
$isReception = false;
if (isset($userInfo) && isset($userInfo->module)) {
	$mod = strtolower(trim((string)$userInfo->module));
	$isReception = ($mod === 'receptionist' || $mod === 'reception');
}
if (!$isReception) {
	$isReception = ((int)$this->session->userdata('user_role') === 3);
}
$isNurse = false;
if (isset($userInfo) && isset($userInfo->module)) {
	$mod = strtolower(trim((string)$userInfo->module));
	$isNurse = ($mod === 'nurse');
}
if (!$isNurse) {
	$isNurse = ((int)$this->session->userdata('user_role') === 7);
}
