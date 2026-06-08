<?php
$views_dir = realpath(__DIR__ . '/../application/views');
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views_dir));

$inventory = [];

foreach ($files as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if ($ext !== 'php') continue;
    
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    
    $relative_path = str_replace($views_dir . DIRECTORY_SEPARATOR, '', $path);
    $relative_path = str_replace('\\', '/', $relative_path); // Normalize to slash
    
    foreach ($lines as $idx => $line) {
        $line_num = $idx + 1;
        
        $has_companyInfo = strpos($line, 'companyInfo') !== false;
        $has_company_name = strpos($line, 'company_name') !== false;
        $has_logo = stripos($line, 'logo') !== false;
        
        if ($has_companyInfo || $has_company_name || $has_logo) {
            $is_match = false;
            
            if ($has_companyInfo && (strpos($line, '$companyInfo->') !== false || strpos($line, 'companyInfo') !== false)) {
                $is_match = true;
            }
            if ($has_company_name && strpos($line, 'company_name') !== false && strpos($line, 'insurance') === false && strpos($line, 'corporate') === false) {
                $is_match = true;
            }
            if ($has_logo && (strpos($line, 'company_logo') !== false || strpos($line, 'hms_logo') !== false || strpos($line, 'login_logo') !== false || strpos($line, 'logo_path') !== false || preg_match('/src=.*logo/i', $line))) {
                $is_match = true;
            }
            
            if ($is_match) {
                $inventory[] = [
                    'file' => $relative_path,
                    'line' => $line_num,
                    'content' => trim($line)
                ];
            }
        }
    }
}

// Generate Markdown Content
$md = "# Branding Dependency Inventory\n\n";
$md .= "This document lists all hardcoded and dynamic branding references in the HMS platform views. These must be refactored systematically to separate Platform Branding (Reddy HMS) from Facility Branding.\n\n";
$md .= "## Audited Branding References\n\n";
$md .= "| View File | Line | Content Snippet | Planned Refactor |\n";
$md .= "| :--- | :---: | :--- | :--- |\n";

foreach ($inventory as $item) {
    $file = $item['file'];
    $line = $item['line'];
    $content = htmlspecialchars($item['content']);
    
    // Suggest planned refactors dynamically
    $refactor = "Use branding helper functions (`getFacilityLogo()`, `getFacilityName()`, etc.)";
    if (strpos($file, 'include/header') !== false || strpos($file, 'login.php') !== false) {
        $refactor = "Use platform branding helpers (`getPlatformLogo()`, `getPlatformName()`) for global nav and login page layout, but retain `getFacilityName()` inside navigation header text.";
    }
    
    $md .= "| `application/views/{$file}` | {$line} | `{$content}` | {$refactor} |\n";
}

$md .= "\n\nTotal Audited Matches: " . count($inventory) . "\n";

$target = "C:/Users/USER/.gemini/antigravity/brain/76a581f7-3102-47fa-bd37-aae5eade9323/branding_dependency_inventory.md";
file_put_contents($target, $md);
echo "Successfully generated Branding Dependency Inventory with " . count($inventory) . " matches.\n";
