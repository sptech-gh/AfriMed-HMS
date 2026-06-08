<?php
$search_dirs = [
    "C:/laragon/www/hms-master/public/css",
    "C:/laragon/www/hms-master/assets/css"
];

$matches = [];

foreach ($search_dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'css') {
            $path = $file->getPathname();
            $content = file_get_contents($path);
            if ($content === false) continue;
            
            $lines = explode("\n", $content);
            foreach ($lines as $idx => $line) {
                // Search for background or background-color setting to white/light color
                if (preg_match('/(background|background-color)\s*:\s*(#[fF]{3,6}|white|rgba\(\s*255\s*,\s*255\s*,\s*255)/i', $line)) {
                    $lower_line = strtolower($line);
                    if (strpos($lower_line, 'table') !== false || 
                        strpos($lower_line, 'tbody') !== false || 
                        strpos($lower_line, 'tr') !== false || 
                        strpos($lower_line, 'td') !== false || 
                        strpos($lower_line, 'responsive') !== false || 
                        strpos($lower_line, 'box') !== false || 
                        strpos($lower_line, 'tab') !== false) {
                        $matches[] = [
                            'file' => basename($path),
                            'line' => $idx + 1,
                            'content' => trim($line)
                        ];
                    }
                }
            }
        }
    }
}

$output = "Found " . count($matches) . " potential white background leaks:\n";
foreach ($matches as $m) {
    $output .= sprintf("%s:%d -> %s\n", $m['file'], $m['line'], $m['content']);
}

file_put_contents("C:/Users/USER/.gemini/antigravity/brain/76a581f7-3102-47fa-bd37-aae5eade9323/scratch/search_results.txt", $output);
echo "Written results to C:/Users/USER/.gemini/antigravity/brain/76a581f7-3102-47fa-bd37-aae5eade9323/scratch/search_results.txt\n";
?>
