<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

DEBUG - 2026-05-08 07:03:04 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 07:03:04 --> Global POST, GET and COOKIE data sanitized
ERROR - 2026-05-08 07:03:04 --> 404 Page Not Found: app/Nurse_module/patient
DEBUG - 2026-05-08 07:07:02 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 07:07:02 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 07:07:02 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 07:07:03 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 07:07:03 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 07:07:03 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 07:07:04 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 07:07:04 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 07:07:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 07:07:04 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 07:07:04 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 07:07:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 07:07:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 07:07:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 07:07:05 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 07:07:05 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508070705_68217c8171 registry=V0 registry_contract=V0 controller=login method=index
DEBUG - 2026-05-08 07:07:05 --> Total execution time: 0.4768
DEBUG - 2026-05-08 07:07:05 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070705_68217c8171 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'receptionist' WHERE `role_id` = 3 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 07:07:05 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070705_68217c8171 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'pharmacy' WHERE `role_id` = 10 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 07:07:05 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070705_68217c8171 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'laboratory' WHERE `role_id` = 11 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 07:07:05 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070705_68217c8171 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'nurse' WHERE `role_id` = 7 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 07:07:05 --> [SHADOW_GOV][DOMAIN_MAP] {"lifecycle_id":"lc_20260508070705_68217c8171","controller":"login","method":"index","intent":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778224024.99306},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778224024.996495},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778224024.997441},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778224024.998496}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":"V0","expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}
DEBUG - 2026-05-08 07:07:05 --> [SHADOW_PARITY] {"UNKNOWN":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}}}
ERROR - 2026-05-08 07:07:05 --> [SHADOW_ALERT] {"domain":"UNKNOWN","intent":"UNKNOWN","severity":"CRITICAL","request_id":"lc_20260508070705_68217c8171","parity":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}},"proof":null,"event":{"lifecycle_id":"lc_20260508070705_68217c8171","controller":"login","method":"index","intent":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778224024.99306},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778224024.996495},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778224024.997441},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778224024.998496}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":"V0","expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}}
DEBUG - 2026-05-08 07:07:05 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_parity.php
DEBUG - 2026-05-08 07:07:14 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 07:07:14 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 07:07:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 07:07:14 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 07:07:14 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 07:07:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 07:07:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 07:07:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 07:07:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 registry_contract=V0 controller=login method=validate_login
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'receptionist' WHERE `role_id` = 3 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'pharmacy' WHERE `role_id` = 10 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'laboratory' WHERE `role_id` = 11 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'nurse' WHERE `role_id` = 7 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 controller=login method=validate_login op=DELETE sql=DELETE FROM `login_attempts` WHERE `username` = 'nurse1'
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_privileges` SET `is_active` = 0 WHERE `user_id` = '00023' AND `is_active` = 1 AND `expiry_date` IS NOT NULL AND `expiry_date` < '2026-05-08'
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508070714_55d6c00f0b registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_privileges` SET `is_active` = 0 WHERE `user_id` = '00023' AND `is_active` = 1 AND `expiry_date` IS NOT NULL AND `expiry_date` < '2026-05-08'
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_GOV][DOMAIN_MAP] {"lifecycle_id":"lc_20260508070714_55d6c00f0b","controller":"login","method":"validate_login","intent":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778224034.800287},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778224034.802789},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778224034.803633},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778224034.804335},{"table":"login_attempts","operation":"DELETE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"timestamp":1778224034.952288},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778224034.966765},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778224034.971356}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":"V0","expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}
DEBUG - 2026-05-08 07:07:14 --> [SHADOW_PARITY] {"UNKNOWN":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"]}}}
ERROR - 2026-05-08 07:07:15 --> [SHADOW_ALERT] {"domain":"UNKNOWN","intent":"UNKNOWN","severity":"CRITICAL","request_id":"lc_20260508070714_55d6c00f0b","parity":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"]}},"proof":null,"event":{"lifecycle_id":"lc_20260508070714_55d6c00f0b","controller":"login","method":"validate_login","intent":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778224034.800287},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778224034.802789},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778224034.803633},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778224034.804335},{"table":"login_attempts","operation":"DELETE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"timestamp":1778224034.952288},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778224034.966765},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778224034.971356}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":"V0","expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}}
DEBUG - 2026-05-08 07:07:15 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_parity.php
DEBUG - 2026-05-08 07:07:15 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 07:07:15 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 07:07:15 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 07:07:15 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 07:07:15 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 07:07:15 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 07:07:15 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 07:07:15 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508070715_08fdd54d1e registry=V0 registry_contract=V0 controller=dashboard method=index
DEBUG - 2026-05-08 07:07:16 --> Total execution time: 1.5831
DEBUG - 2026-05-08 07:07:16 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 07:07:16 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 08:05:00 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 08:05:00 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 08:05:00 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 08:05:01 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 08:05:01 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 08:05:01 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508080501_f6b78f8390 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate
DEBUG - 2026-05-08 08:05:01 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 08:05:01 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 08:13:14 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 08:13:14 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 08:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 08:13:14 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 08:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 08:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 08:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 08:13:14 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508081314_f48614e0ad registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 08:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 09:02:12 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 09:02:12 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 09:02:12 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 09:02:12 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 09:02:12 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 09:02:12 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 09:02:12 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 09:02:12 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508090212_2733e47955 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 09:02:12 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 09:03:42 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 09:03:42 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 09:03:42 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 09:03:43 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 09:03:43 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 09:03:43 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 09:03:43 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 09:03:43 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508090343_2bf9beef28 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 09:03:43 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 09:55:27 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 09:55:27 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 09:55:28 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 09:55:28 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 09:55:29 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 09:55:29 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 09:55:29 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 09:55:29 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508095529_48ce873897 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 09:55:29 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 09:57:19 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 09:57:19 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 09:57:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 09:57:19 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 09:57:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 09:57:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 09:57:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 09:57:19 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508095719_0f713409d6 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 09:57:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 10:13:31 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 10:13:32 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 10:13:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 10:13:32 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 10:13:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 10:13:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 10:13:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 10:13:32 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508101332_12c141fdfb registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 10:13:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:05:32 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:05:32 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:05:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:05:32 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:05:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:05:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:05:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:05:32 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508110532_108850e5da registry=V0 registry_contract=V0 controller=cron method=shadow_governance_analytics intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:05:32 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:25:36 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:25:36 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:25:36 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:25:37 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:25:37 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:25:37 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:25:37 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:25:37 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508112537_f234a0736a registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:25:37 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 11:25:37 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:27:30 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:27:30 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:27:30 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:27:30 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:27:30 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:27:30 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:27:30 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:27:30 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508112730_3da618da87 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:27:30 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 11:27:30 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:57:03 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:57:03 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:57:03 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:57:03 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:57:03 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:57:03 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:57:03 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:57:03 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508115703_35777031a7 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:57:03 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:57:27 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:57:27 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:57:27 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:57:27 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:57:27 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:57:27 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:57:27 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:57:27 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508115727_4007da6f73 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:57:27 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:57:33 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:57:33 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:57:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:57:33 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:57:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:57:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:57:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:57:33 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508115733_98beba3042 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:57:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:58:06 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:58:06 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:58:06 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:58:06 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:58:06 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:58:06 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:58:06 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:58:06 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508115806_fa68b23146 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:58:06 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 11:58:06 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 11:58:54 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 11:58:55 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 11:58:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 11:58:55 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 11:58:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 11:58:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 11:58:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 11:58:55 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508115855_454997e2d1 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 11:58:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 11:58:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:21:23 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:21:23 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:21:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:21:23 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:21:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:21:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:21:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:21:23 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508122123_61dec05f6a registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:21:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:21:33 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:21:33 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:21:33 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:21:33 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:21:33 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:21:33 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:21:33 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508122133_e0c5419003 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:21:33 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508122133_0205b4e46a registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:21:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:22:19 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:22:19 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:22:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:22:19 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:22:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:22:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:22:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:22:19 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508122219_54a2972435 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:22:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:22:20 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:22:20 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:22:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:22:20 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:22:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:22:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:22:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:22:20 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508122220_0ce604baa3 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:22:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 12:22:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:39:49 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:39:49 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:39:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:39:49 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:39:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:39:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:39:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:39:49 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508123949_0b0bda5114 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:39:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:40:38 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:40:38 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:40:38 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:40:39 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:40:39 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:40:39 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:40:39 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:40:39 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508124039_20a2726c23 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:40:39 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:40:40 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:40:40 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:40:40 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:40:40 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:40:40 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:40:40 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:40:40 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:40:40 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508124040_498875a8a2 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:40:40 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:41:23 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:41:23 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:41:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:41:23 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:41:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:41:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:41:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:41:23 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508124123_7a2067d772 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:41:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 12:41:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:43:48 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:43:48 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:43:48 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:43:48 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:43:49 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508124349_f9241d1c81 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 12:43:49 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 12:43:49 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 12:43:49 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 12:43:49 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508124349_912aab93e6 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 12:43:49 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:02:00 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:02:00 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:02:00 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:02:00 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:02:00 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:02:00 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:02:00 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:02:00 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508130200_b0146d7f8c registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:02:00 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:02:09 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:02:09 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:02:09 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:02:09 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:02:09 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:02:09 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:02:09 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508130209_bff4842dd0 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:02:09 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508130209_d1ea9830d3 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:02:09 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:02:45 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:02:45 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:02:45 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:02:45 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:02:45 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:02:45 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:02:45 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:02:45 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508130245_65d57b426e registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:02:45 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 13:02:45 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:19:33 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:19:33 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:19:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:19:33 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:19:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:19:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:19:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:19:33 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508131933_ee176725be registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:19:33 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:20:19 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:20:19 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:20:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:20:19 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:20:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:20:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:20:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:20:19 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508132019_271a3c54eb registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:20:19 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:20:20 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:20:20 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:20:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:20:20 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:20:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:20:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:20:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:20:20 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508132020_196ac9b418 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:20:20 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 13:20:50 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 13:20:50 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 13:20:50 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 13:20:50 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 13:20:50 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 13:20:50 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 13:20:50 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 13:20:50 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508132050_02e064a6a2 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 13:20:50 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 13:20:50 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 14:40:34 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 14:40:34 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 14:40:34 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 14:40:34 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 14:40:35 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 14:40:35 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 14:40:35 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 14:40:35 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508144035_caba15a8d2 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 14:40:35 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 14:40:53 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 14:40:53 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 14:40:53 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 14:40:53 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 14:40:53 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 14:40:53 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 14:40:53 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 14:40:53 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508144053_fa491e24e9 registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 14:40:53 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 14:40:55 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 14:40:55 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 14:40:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 14:40:55 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 14:40:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 14:40:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 14:40:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 14:40:55 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508144055_41e4c34eed registry=V0 registry_contract=V0 controller=cron method=shadow_expectedset_validate intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 14:40:55 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 14:41:03 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 14:41:03 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 14:41:03 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 14:41:03 --> Session: Initialization under CLI aborted.
DEBUG - 2026-05-08 14:41:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 14:41:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 14:41:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 14:41:04 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508144104_f97e07bf51 registry=V0 registry_contract=V0 controller=cron method=shadow_coverage_convergence intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 14:41:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_proof_contracts.php
DEBUG - 2026-05-08 14:41:04 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 17:12:46 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 17:12:46 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 17:12:46 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 17:12:46 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 17:12:46 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 17:12:46 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 17:12:47 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 17:12:47 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 17:12:47 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 17:12:47 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 17:12:47 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 17:12:47 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 17:12:47 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 17:12:47 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 17:12:47 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 17:12:47 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 17:12:47 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508171247_cee8420d94 registry=V0 registry_contract=V0 controller=login method=index intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 17:12:47 --> Total execution time: 0.4434
DEBUG - 2026-05-08 17:12:47 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171247_cee8420d94 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'receptionist' WHERE `role_id` = 3 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 17:12:47 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171247_cee8420d94 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'pharmacy' WHERE `role_id` = 10 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 17:12:47 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171247_cee8420d94 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'laboratory' WHERE `role_id` = 11 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 17:12:47 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171247_cee8420d94 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'nurse' WHERE `role_id` = 7 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 17:12:47 --> [SHADOW_GOV][DOMAIN_MAP] {"lifecycle_id":"lc_20260508171247_cee8420d94","controller":"login","method":"index","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778260367.520118},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778260367.53231},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778260367.535699},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778260367.537733}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}
DEBUG - 2026-05-08 17:12:47 --> [SHADOW_PARITY] {"UNKNOWN":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}}}
ERROR - 2026-05-08 17:12:47 --> [SHADOW_ALERT] {"domain":"UNKNOWN","intent":"UNKNOWN","severity":"CRITICAL","request_id":"lc_20260508171247_cee8420d94","parity":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}},"proof":null,"event":{"lifecycle_id":"lc_20260508171247_cee8420d94","controller":"login","method":"index","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778260367.520118},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778260367.53231},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778260367.535699},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778260367.537733}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}}
DEBUG - 2026-05-08 17:12:47 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_parity.php
DEBUG - 2026-05-08 17:13:13 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 17:13:13 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 17:13:13 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 17:13:13 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 17:13:13 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 17:13:13 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508171314_de32bd428d registry=V0 registry_contract=V0 controller=login method=validate_login intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171314_de32bd428d registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'receptionist' WHERE `role_id` = 3 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171314_de32bd428d registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'pharmacy' WHERE `role_id` = 10 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171314_de32bd428d registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'laboratory' WHERE `role_id` = 11 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171314_de32bd428d registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_roles` SET `module` = 'nurse' WHERE `role_id` = 7 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171314_de32bd428d registry=V0 controller=login method=validate_login op=DELETE sql=DELETE FROM `login_attempts` WHERE `username` = 'nurse1'
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171314_de32bd428d registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_privileges` SET `is_active` = 0 WHERE `user_id` = '00023' AND `is_active` = 1 AND `expiry_date` IS NOT NULL AND `expiry_date` < '2026-05-08'
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508171314_de32bd428d registry=V0 controller=login method=validate_login op=UPDATE sql=UPDATE `user_privileges` SET `is_active` = 0 WHERE `user_id` = '00023' AND `is_active` = 1 AND `expiry_date` IS NOT NULL AND `expiry_date` < '2026-05-08'
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV][DOMAIN_MAP] {"lifecycle_id":"lc_20260508171314_de32bd428d","controller":"login","method":"validate_login","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778260394.073255},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778260394.083551},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778260394.085669},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778260394.087089},{"table":"login_attempts","operation":"DELETE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"timestamp":1778260394.291605},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778260394.304343},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778260394.315491}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_PARITY] {"UNKNOWN":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"]}}}
ERROR - 2026-05-08 17:13:14 --> [SHADOW_ALERT] {"domain":"UNKNOWN","intent":"UNKNOWN","severity":"CRITICAL","request_id":"lc_20260508171314_de32bd428d","parity":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"]}},"proof":null,"event":{"lifecycle_id":"lc_20260508171314_de32bd428d","controller":"login","method":"validate_login","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778260394.073255},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778260394.083551},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778260394.085669},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778260394.087089},{"table":"login_attempts","operation":"DELETE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"timestamp":1778260394.291605},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778260394.304343},{"table":"user_privileges","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":null,"timestamp":1778260394.315491}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles","login_attempts","user_privileges"],"sql_operations_seen":["UPDATE","DELETE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}}
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_parity.php
DEBUG - 2026-05-08 17:13:14 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 17:13:14 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 17:13:14 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 17:13:14 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 17:13:14 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508171314_a45592e0dc registry=V0 registry_contract=V0 controller=dashboard method=index intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 17:13:14 --> Total execution time: 0.5179
DEBUG - 2026-05-08 17:13:14 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 17:31:57 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 17:31:57 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 17:31:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 17:31:57 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 17:31:57 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 17:31:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 17:31:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 17:31:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 17:31:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 17:31:57 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508173157_771caafd5c registry=V0 registry_contract=V0 controller=dashboard method=index intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 17:31:57 --> Total execution time: 0.2803
DEBUG - 2026-05-08 17:31:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 17:32:56 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 17:32:56 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 17:32:56 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 17:32:56 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 17:32:56 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 17:32:56 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 17:32:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 17:32:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 17:32:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 17:32:57 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508173257_ab7cc36bf2 registry=V0 registry_contract=V0 controller=dashboard method=index intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 17:32:57 --> Total execution time: 0.2369
DEBUG - 2026-05-08 17:32:57 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 22:39:21 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 22:39:21 --> No URI present. Default controller set.
DEBUG - 2026-05-08 22:39:21 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 22:39:22 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 22:39:22 --> UTF-8 Support Enabled
DEBUG - 2026-05-08 22:39:22 --> No URI present. Default controller set.
DEBUG - 2026-05-08 22:39:22 --> Global POST, GET and COOKIE data sanitized
DEBUG - 2026-05-08 22:39:22 --> Config file loaded: C:\laragon\www\hms-master\application\config/hms.php
DEBUG - 2026-05-08 22:39:22 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 22:39:22 --> Session: "sess_driver" is empty; using BC fallback to "sess_use_database".
DEBUG - 2026-05-08 22:39:23 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 22:39:23 --> Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".
DEBUG - 2026-05-08 22:39:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 22:39:23 --> Config file loaded: C:\laragon\www\hms-master\application\config/ui_config.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_audit.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_expectedset.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_endpoint_expectations.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 22:39:25 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_governance_registry.php
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508223925_799533aa74 registry=V0 registry_contract=V0 controller=login method=index intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV] bootstrap lifecycle_id=lc_20260508223925_6db6eb12c7 registry=V0 registry_contract=V0 controller=login method=index intent=UNKNOWN endpoint_write_expectation=UNKNOWN
DEBUG - 2026-05-08 22:39:25 --> Total execution time: 3.6527
DEBUG - 2026-05-08 22:39:25 --> Total execution time: 5.1847
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_799533aa74 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'receptionist' WHERE `role_id` = 3 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_6db6eb12c7 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'receptionist' WHERE `role_id` = 3 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_799533aa74 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'pharmacy' WHERE `role_id` = 10 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_6db6eb12c7 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'pharmacy' WHERE `role_id` = 10 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_799533aa74 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'laboratory' WHERE `role_id` = 11 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_6db6eb12c7 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'laboratory' WHERE `role_id` = 11 AND (module IS NULL OR module = '' OR module = '0')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_799533aa74 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'nurse' WHERE `role_id` = 7 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DB_WRITE] lifecycle_id=lc_20260508223925_6db6eb12c7 registry=V0 controller=login method=index op=UPDATE sql=UPDATE `user_roles` SET `module` = 'nurse' WHERE `role_id` = 7 AND (module = '0' OR module IS NULL OR module = '')
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DOMAIN_MAP] {"lifecycle_id":"lc_20260508223925_799533aa74","controller":"login","method":"index","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778279965.688882},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778279965.69503},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778279965.696286},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778279965.697165}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_GOV][DOMAIN_MAP] {"lifecycle_id":"lc_20260508223925_6db6eb12c7","controller":"login","method":"index","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778279965.687798},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778279965.694318},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778279965.695407},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778279965.696243}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_PARITY] {"UNKNOWN":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}}}
DEBUG - 2026-05-08 22:39:25 --> [SHADOW_PARITY] {"UNKNOWN":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}}}
ERROR - 2026-05-08 22:39:26 --> [SHADOW_ALERT] {"domain":"UNKNOWN","intent":"UNKNOWN","severity":"CRITICAL","request_id":"lc_20260508223925_6db6eb12c7","parity":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}},"proof":null,"event":{"lifecycle_id":"lc_20260508223925_6db6eb12c7","controller":"login","method":"index","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778279965.687798},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778279965.694318},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778279965.695407},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778279965.696243}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}}
ERROR - 2026-05-08 22:39:26 --> [SHADOW_ALERT] {"domain":"UNKNOWN","intent":"UNKNOWN","severity":"CRITICAL","request_id":"lc_20260508223925_799533aa74","parity":{"status":"UNPROVABLE","code":"DOMAIN_UNRESOLVED","data":{"tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"]}},"proof":null,"event":{"lifecycle_id":"lc_20260508223925_799533aa74","controller":"login","method":"index","intent":"UNKNOWN","endpoint_write_expectation":"UNKNOWN","context":[],"tables_touched":[{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"ambiguous_where","where_keys":null,"update_set":{"module":"receptionist"},"timestamp":1778279965.688882},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":10},"update_set":{"module":"pharmacy"},"timestamp":1778279965.69503},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":11},"update_set":{"module":"laboratory"},"timestamp":1778279965.696286},{"table":"user_roles","operation":"UPDATE","primary_key":null,"pk_status":"UNPROVABLE","reason":"no_primary_key_map","where_keys":{"role_id":7},"update_set":{"module":"nurse"},"timestamp":1778279965.697165}],"capture_gap_detected":false,"capture_gap_missing":[],"db_driver_class":"MY_DB_mysqli_driver","db_driver_instrumented":true,"expectedset_loaded":false,"expectedset_version":null,"expectedset_contract_bound":false,"expectedset_binding_status":"INTENT_NOT_BINDABLE","expectedset_contract_domain":null,"expectedset_contract_intent":null,"expectedset_contract_key":null,"expectedset_invariants":[],"domain":"UNKNOWN","domain_detected":"UNKNOWN","confidence":"LOW","tables_detected":["user_roles"],"sql_operations_seen":["UPDATE"],"registry_version":"V0","registry_contract_version":"V0","registry_domain":"UNKNOWN","match_status":"DRIFT","transaction_class":null,"enforcement_tier":null,"audit_required":null,"compensation_required":null,"drift_flags":["REGISTRY_NO_MATCH"],"registry_candidates":[]}}
DEBUG - 2026-05-08 22:39:26 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_parity.php
DEBUG - 2026-05-08 22:39:26 --> Config file loaded: C:\laragon\www\hms-master\application\config/shadow_parity.php
