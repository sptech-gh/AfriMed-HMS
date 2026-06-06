<?php
/**
 * Add Missing NHIS Drug Tariffs for Unmapped Drugs
 * This script adds NHIS tariff entries for drugs that don't exist in the tariff database
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'hms_master';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

echo "==========================================\n";
echo "Adding Missing NHIS Drug Tariffs\n";
echo "==========================================\n\n";

// Define missing drugs with NHIS-compliant codes and pricing
$missing_drugs = [
    // IV Fluids
    ['code' => 'IV-NS-500', 'name' => 'IV Normal Saline 0.9%', 'generic' => 'Sodium Chloride', 'form' => 'IV Fluid', 'strength' => '500ml', 'price' => 5.00],
    ['code' => 'IV-D5-500', 'name' => 'IV Dextrose 5%', 'generic' => 'Dextrose', 'form' => 'IV Fluid', 'strength' => '500ml', 'price' => 5.00],
    ['code' => 'IV-DS-500', 'name' => 'IV Dextrose Saline', 'generic' => 'Dextrose Saline', 'form' => 'IV Fluid', 'strength' => '500ml', 'price' => 5.50],
    ['code' => 'IV-RL-500', 'name' => 'IV Ringers Lactate', 'generic' => 'Ringers Lactate', 'form' => 'IV Fluid', 'strength' => '500ml', 'price' => 6.00],
    
    // Injectable Antibiotics
    ['code' => 'INJ-CEF-1G', 'name' => 'Ceftriaxone 1g Injection', 'generic' => 'Ceftriaxone', 'form' => 'Injection', 'strength' => '1g', 'price' => 25.00],
    ['code' => 'INJ-GEN-80', 'name' => 'Gentamicin 80mg Injection', 'generic' => 'Gentamicin', 'form' => 'Injection', 'strength' => '80mg', 'price' => 12.00],
    
    // Emergency Drugs
    ['code' => 'EMG-EPH-1', 'name' => 'Epinephrine Injection', 'generic' => 'Epinephrine', 'form' => 'Injection', 'strength' => '1mg/ml', 'price' => 15.00],
    ['code' => 'EMG-ATR-06', 'name' => 'Atropine Injection', 'generic' => 'Atropine', 'form' => 'Injection', 'strength' => '0.6mg/ml', 'price' => 8.00],
    ['code' => 'EMG-ISO', 'name' => 'Isoproterenol Injection', 'generic' => 'Isoproterenol', 'form' => 'Injection', 'strength' => '0.2mg/ml', 'price' => 35.00],
    ['code' => 'EMG-DEX-4', 'name' => 'Dexamethasone 4mg Injection', 'generic' => 'Dexamethasone', 'form' => 'Injection', 'strength' => '4mg', 'price' => 10.00],
    ['code' => 'EMG-HYD-100', 'name' => 'Hydrocortisone 100mg Injection', 'generic' => 'Hydrocortisone', 'form' => 'Injection', 'strength' => '100mg', 'price' => 18.00],
    
    // Oral Antibiotics
    ['code' => 'ORL-ERY-500', 'name' => 'Erythromycin 500mg Tablet', 'generic' => 'Erythromycin', 'form' => 'Tablet', 'strength' => '500mg', 'price' => 0.80],
    ['code' => 'ORL-AUG', 'name' => 'Amoxicillin-Clavulanate Tablet', 'generic' => 'Amoxicillin-Clavulanate', 'form' => 'Tablet', 'strength' => '625mg', 'price' => 2.50],
    
    // Cardiovascular
    ['code' => 'CAR-LOS-50', 'name' => 'Losartan 50mg Tablet', 'generic' => 'Losartan', 'form' => 'Tablet', 'strength' => '50mg', 'price' => 1.20],
    ['code' => 'CAR-NIF-20', 'name' => 'Nifedipine 20mg Tablet', 'generic' => 'Nifedipine', 'form' => 'Tablet', 'strength' => '20mg', 'price' => 0.90],
    
    // Antihistamines/GI
    ['code' => 'GI-CYP', 'name' => 'Cyproheptadine Tablet', 'generic' => 'Cyproheptadine', 'form' => 'Tablet', 'strength' => '4mg', 'price' => 0.70],
    ['code' => 'GI-DIP', 'name' => 'Diphenhydramine Tablet', 'generic' => 'Diphenhydramine', 'form' => 'Tablet', 'strength' => '25mg', 'price' => 0.60],
    ['code' => 'GI-BUS', 'name' => 'Hyoscine Butylbromide Tablet', 'generic' => 'Hyoscine Butylbromide', 'form' => 'Tablet', 'strength' => '10mg', 'price' => 0.80],
    ['code' => 'GI-RAN-150', 'name' => 'Ranitidine 150mg Tablet', 'generic' => 'Ranitidine', 'form' => 'Tablet', 'strength' => '150mg', 'price' => 0.50],
    
    // Steroids
    ['code' => 'ST-PRED-5', 'name' => 'Prednisolone 5mg Tablet', 'generic' => 'Prednisolone', 'form' => 'Tablet', 'strength' => '5mg', 'price' => 0.60],
    
    // Insulin
    ['code' => 'INS-ACT', 'name' => 'Insulin Actrapid', 'generic' => 'Insulin Actrapid', 'form' => 'Injection', 'strength' => '100IU/ml', 'price' => 45.00],
    ['code' => 'INS-MIX', 'name' => 'Insulin Mixtard', 'generic' => 'Insulin Mixtard', 'form' => 'Injection', 'strength' => '100IU/ml', 'price' => 48.00],
    
    // Analgesics/Other
    ['code' => 'ANL-TRA-50', 'name' => 'Tramadol 50mg Capsule', 'generic' => 'Tramadol', 'form' => 'Capsule', 'strength' => '50mg', 'price' => 1.50],
    ['code' => 'SUP-ZINC-20', 'name' => 'Zinc Sulphate 20mg Tablet', 'generic' => 'Zinc Sulphate', 'form' => 'Tablet', 'strength' => '20mg', 'price' => 0.40],
    
    // Antiemetics
    ['code' => 'ANT-PROM-25', 'name' => 'Promethazine 25mg Tablet', 'generic' => 'Promethazine', 'form' => 'Tablet', 'strength' => '25mg', 'price' => 0.55],
    
    // Special (not on standard NHIS but needed)
    ['code' => 'MISC-NITRIC', 'name' => 'Nitric Oxide', 'generic' => 'Nitric Oxide', 'form' => 'Gas', 'strength' => 'N/A', 'price' => 150.00],
    ['code' => 'ANT-PYR', 'name' => 'Pyrilamine Tablet', 'generic' => 'Pyrilamine', 'form' => 'Tablet', 'strength' => '25mg', 'price' => 0.65],
    ['code' => 'ANT-PRO', 'name' => 'Promethazine Tablet', 'generic' => 'Promethazine', 'form' => 'Tablet', 'strength' => '10mg', 'price' => 0.45],
];

$added = 0;
$existing = 0;
$failed = 0;

echo "Adding " . count($missing_drugs) . " NHIS drug tariffs...\n\n";

foreach ($missing_drugs as $drug) {
    // Check if already exists
    $stmt = $mysqli->prepare("SELECT tariff_id FROM nhis_drug_tariffs WHERE nhis_code = ?");
    $stmt->bind_param("s", $drug['code']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "  ⚠ Already exists: {$drug['code']} - {$drug['name']}\n";
        $existing++;
        continue;
    }
    
    // Insert new tariff
    $stmt = $mysqli->prepare("INSERT INTO nhis_drug_tariffs 
        (nhis_code, drug_name, generic_name, dosage_form, strength, unit_price, unit, pack_size, is_active, created_at, category, is_essential) 
        VALUES (?, ?, ?, ?, ?, ?, 'Unit', 1, 1, NOW(), 'General', 1)");
    
    $stmt->bind_param("sssssd", 
        $drug['code'], 
        $drug['name'], 
        $drug['generic'], 
        $drug['form'], 
        $drug['strength'], 
        $drug['price']
    );
    
    if ($stmt->execute()) {
        echo "  ✓ Added: {$drug['code']} - {$drug['name']} (GH₵{$drug['price']})\n";
        $added++;
    } else {
        echo "  ✗ Failed: {$drug['code']} - " . $stmt->error . "\n";
        $failed++;
    }
}

echo "\n==========================================\n";
echo "Summary\n";
echo "==========================================\n";
echo "Added: $added\n";
echo "Already existed: $existing\n";
echo "Failed: $failed\n";
echo "Total processed: " . count($missing_drugs) . "\n";

// Now map these new tariffs to HMS drugs
echo "\n==========================================\n";
echo "Mapping New Tariffs to HMS Drugs\n";
echo "==========================================\n";

$mapping_queries = [
    ["IV-NS-500", "IV Normal Saline 0.9%"],
    ["IV-D5-500", "IV Dextrose 5%"],
    ["IV-DS-500", "IV Dextrose Saline"],
    ["IV-RL-500", "IV Ringers Lactate"],
    ["INJ-CEF-1G", "Ceftriaxone 1g"],
    ["INJ-GEN-80", "Gentamicin 80mg"],
    ["EMG-EPH-1", "EPINEPHRINE"],
    ["EMG-ATR-06", "ATROPINE"],
    ["EMG-ISO", "ISOPROTERENOL"],
    ["EMG-DEX-4", "Dexamethasone 4mg"],
    ["EMG-HYD-100", "Hydrocortisone 100mg"],
    ["ORL-ERY-500", "Erythromycin 500mg"],
    ["ORL-AUG", "Amoxicillin-Clavulanate"],
    ["CAR-LOS-50", "Losartan 50mg"],
    ["CAR-NIF-20", "Nifedipine 20mg"],
    ["GI-CYP", "CYPROHEPTADINE"],
    ["GI-DIP", "DIPHENHYDRAMINE"],
    ["GI-BUS", "Hyoscine Butylbromide"],
    ["GI-RAN-150", "Ranitidine 150mg"],
    ["ST-PRED-5", "Prednisolone 5mg"],
    ["INS-ACT", "Insulin (Actrapid)"],
    ["INS-MIX", "Insulin (Mixtard)"],
    ["ANL-TRA-50", "Tramadol 50mg"],
    ["SUP-ZINC-20", "Zinc Sulphate 20mg"],
    ["ANT-PROM-25", "Promethazine 25mg"],
    ["MISC-NITRIC", "NITRIC OXIDE"],
    ["ANT-PYR", "PYRILAMINE"],
    ["ANT-PRO", "PROMETHAZINE"],
];

$mapped = 0;
foreach ($mapping_queries as $mapping) {
    list($code, $drug_name) = $mapping;
    
    $stmt = $mysqli->prepare("UPDATE medicine_drug_name 
        SET nhis_drug_code = ?, 
            nhis_code = ?,
            is_nhis_covered = 1,
            nhis_price = (SELECT unit_price FROM nhis_drug_tariffs WHERE nhis_code = ?)
        WHERE drug_name LIKE ? 
        AND InActive = 0
        AND (nhis_drug_code IS NULL OR nhis_drug_code = '')");
    
    $like_name = "%$drug_name%";
    $stmt->bind_param("ssss", $code, $code, $code, $like_name);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "  ✓ Mapped: $drug_name → $code\n";
            $mapped++;
        }
    } else {
        echo "  ✗ Failed to map: $drug_name\n";
    }
}

echo "\nTotal drugs mapped: $mapped\n";

// Show final stats
$result = $mysqli->query("SELECT COUNT(*) as total FROM medicine_drug_name WHERE InActive = 0");
$total = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as mapped FROM medicine_drug_name WHERE InActive = 0 AND (nhis_drug_code IS NOT NULL AND nhis_drug_code != '')");
$mapped_total = $result->fetch_assoc()['mapped'];

echo "\n==========================================\n";
echo "Final Drug Mapping Status\n";
echo "==========================================\n";
echo "Total HMS Drugs: $total\n";
echo "Mapped to NHIS: $mapped_total\n";
echo "Coverage: " . round(($mapped_total / $total) * 100, 1) . "%\n";

echo "\nDone!\n";
