import os
import re

directories = ['public', 'app', 'admin', 'auth', '.']

def process_file(filepath):
    if not filepath.endswith('.html') and not filepath.endswith('.php'):
        return
    
    # skip deployments and CodeBrowser if we only want our main UI
    if 'deployments' in filepath or 'CodeBrowser' in filepath:
        return
        
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Skip if responsive.css already there
    if 'responsive.css' in content and 'mobile-nav.js' in content:
        return

    original_content = content

    # Inject responsive.css after style.css
    if 'responsive.css' not in content:
        css_pattern = re.compile(r'(<link[^>]*href=["\']([^"\']*)style\.css([^"\']*)["\'][^>]*>)', re.IGNORECASE)
        match = css_pattern.search(content)
        if match:
            replacement = r'\1\n    <link rel="stylesheet" href="\2responsive.css\3">'
            content = css_pattern.sub(replacement, content, count=1)
            
            if 'mobile-nav.js' not in content:
                js_pattern = re.compile(r'(<script[^>]*src=["\']([^"\']*)main\.js([^"\']*)["\'][^>]*><\/script>)', re.IGNORECASE)
                js_match = js_pattern.search(content)
                if js_match:
                    js_replacement = r'\1\n    <script src="\2mobile-nav.js\3"></script>'
                    content = js_pattern.sub(js_replacement, content, count=1)
                else:
                    rel_path = match.group(2).replace('css', 'js')
                    v = match.group(3)
                    new_script = f'<script src="{rel_path}mobile-nav.js{v}"></script>\n'
                    content = re.sub(r'(</body>)', new_script + r'\1', content, flags=re.IGNORECASE)

    if content != original_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'Updated {filepath}')

for root, dirs, files in os.walk('.'):
    parts = root.split(os.sep)
    if len(parts) > 1 and parts[1] not in directories:
        continue
    for file in files:
        process_file(os.path.join(root, file))

print('Done.')
