import os
import re

css_dir = "C:/laragon/www/hms-master/public/css"
assets_css_dir = "C:/laragon/www/hms-master/assets/css"

search_dirs = [css_dir, assets_css_dir]

bg_pattern = re.compile(r'(background|background-color)\s*:\s*(#[fF]{3,6}|white|rgba\(\s*255\s*,\s*255\s*,\s*255)', re.IGNORECASE)

matches = []

for s_dir in search_dirs:
    if not os.path.exists(s_dir):
        continue
    for root, dirs, files in os.walk(s_dir):
        for file in files:
            if file.endswith('.css'):
                path = os.path.join(root, file)
                with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()
                    # Find lines with background-color or background setting white
                    lines = content.split('\n')
                    for idx, line in enumerate(lines):
                        if bg_pattern.search(line):
                            # Also check if it's related to tables
                            if any(k in line.lower() for k in ['table', 'tbody', 'tr', 'td', 'responsive', 'box', 'tab']):
                                matches.append((file, idx + 1, line.strip()))

print(f"Found {len(matches)} potential white background leaks:")
for m in matches[:100]:
    print(f"{m[0]}:{m[1]} -> {m[2]}")
