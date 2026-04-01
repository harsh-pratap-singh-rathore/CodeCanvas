import os
import re

directories = ['templates']

def process_file(filepath):
    if not filepath.endswith('code.html'):
        return
        
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original_content = content

    # Check if responsive meta tag exists, if not add it
    if '<meta name="viewport"' not in content and '<meta content="width=device-width, initial-scale=1.0" name="viewport"' not in content:
        content = re.sub(r'(<head[^>]*>)', r'\1\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">', content, 1, re.IGNORECASE)

    # Hamburger Menu Injection Logic for Tailwind
    # We look for </header> or <nav> and insert the mobile menu script
    if 'mobile-menu-btn' not in content:
        # In tailwind templates, there is usually a button with md:hidden for mobile. Let's make it work.
        
        # Add the script right before </body>
        script_to_add = """
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileBtn = document.querySelector('button.md\\\\:hidden');
            const nav = document.querySelector('nav');
            
            if (mobileBtn && nav) {
                // Tailwind based toggle
                mobileBtn.addEventListener('click', () => {
                    nav.classList.toggle('hidden');
                    nav.classList.toggle('flex');
                    nav.classList.toggle('flex-col');
                    nav.classList.toggle('absolute');
                    nav.classList.toggle('top-full');
                    nav.classList.toggle('left-0');
                    nav.classList.toggle('w-full');
                    nav.classList.toggle('bg-white');
                    nav.classList.toggle('dark:bg-slate-900');
                    nav.classList.toggle('p-4');
                    nav.classList.toggle('shadow-lg');
                    nav.classList.toggle('z-50');
                });
            }
        });
    </script>
"""
        content = re.sub(r'(</body>)', script_to_add + r'\1', content, flags=re.IGNORECASE)

        # Make header relative so the absolute nav works right below it
        content = re.sub(r'(<header[^>]*class="[^"]*)(")', r'\1 relative\2', content, 1)


    if content != original_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'Updated {filepath}')

for root, dirs, files in os.walk('templates'):
    for file in files:
        process_file(os.path.join(root, file))

print('Done.')
