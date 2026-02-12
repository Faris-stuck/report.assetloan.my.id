#!/usr/bin/env python3
"""
Script to update all admin navbar menus across all admin HTML/PHP files.
"""
import re
import os
import glob

# New navbar template with {prefix} placeholder
def get_new_navbar(prefix):
    return f'''                <ul class="nxl-navbar">
                    <li class="nxl-item nxl-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Dashboard</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/dashboard.html">Dashboard / Informasi</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-cast"></i></span>
                            <span class="nxl-mtext">Item / Inventory</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/barang/data-barang.php">Item Data</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/barang/detail-barang.html">Item Detail</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Item Loan</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/peminjaman/data-peminjaman.html">Request Loan</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/peminjaman/sedang-dipinjam.html">List Loan</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/peminjaman/admin-approval.html">Approval</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-corner-down-left"></i></span>
                            <span class="nxl-mtext">Item Return</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/pengembalian/pengembalian-barang.html">Return Loan</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shield"></i></span>
                            <span class="nxl-mtext">Administrator</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/user/buat-user.html">User List</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{prefix}admin/pengaturan.html">Role List</a></li>
                        </ul>
                    </li>
                </ul>'''

# Files and their prefix
admin_root = '/opt/lampp/htdocs/PROJECT/admin'

# Collect all html/php files in admin
files = []
for ext in ['html', 'php']:
    files.extend(glob.glob(os.path.join(admin_root, f'*.{ext}')))
    files.extend(glob.glob(os.path.join(admin_root, f'**/*.{ext}'), recursive=True))

# Deduplicate
files = sorted(set(files))

# Pattern to match the entire navbar block from <ul class="nxl-navbar"> to the closing </ul>
# followed by optional card div, up to </div> of navbar-content and </div> of navbar-wrapper and </nav>
# We'll match from <ul class="nxl-navbar"> to the FIRST </ul>\n that is at the same indentation level
# Actually, let's be more precise: match from <ul class="nxl-navbar"> through all the nested content
# until we find the closing </ul> that matches (accounting for nesting)

def find_and_replace_navbar(content, prefix):
    """Find the navbar <ul class="nxl-navbar">...</ul> and optional card div, replace with new navbar."""
    
    # Find the start of navbar
    start_marker = '<ul class="nxl-navbar">'
    start_idx = content.find(start_marker)
    if start_idx == -1:
        return content, False
    
    # Find the end - we need to count nested <ul> and </ul> tags
    search_from = start_idx + len(start_marker)
    depth = 1  # We're inside one <ul>
    pos = search_from
    
    while depth > 0 and pos < len(content):
        next_open = content.find('<ul', pos)
        next_close = content.find('</ul>', pos)
        
        if next_close == -1:
            break
        
        if next_open != -1 and next_open < next_close:
            depth += 1
            pos = next_open + 3
        else:
            depth -= 1
            if depth == 0:
                end_idx = next_close + len('</ul>')
                break
            pos = next_close + 5
    
    if depth != 0:
        return content, False
    
    # Now check if there's a card div after the </ul> (skip whitespace)
    after_ul = content[end_idx:].lstrip()
    card_end_idx = end_idx
    
    if after_ul.startswith('<div class="card text-center">'):
        # Find the end of this card div
        card_start = content.find('<div class="card text-center">', end_idx)
        # Count nested divs to find matching </div>
        card_search = card_start + len('<div class="card text-center">')
        div_depth = 1
        cpos = card_search
        while div_depth > 0 and cpos < len(content):
            next_div_open = content.find('<div', cpos)
            next_div_close = content.find('</div>', cpos)
            
            if next_div_close == -1:
                break
            
            if next_div_open != -1 and next_div_open < next_div_close:
                div_depth += 1
                cpos = next_div_open + 4
            else:
                div_depth -= 1
                if div_depth == 0:
                    card_end_idx = next_div_close + len('</div>')
                    break
                cpos = next_div_close + 6
    
    # Get the new navbar
    new_navbar = get_new_navbar(prefix)
    
    # Replace
    new_content = content[:start_idx] + new_navbar + content[card_end_idx:]
    return new_content, True

count = 0
for filepath in files:
    # Determine prefix based on directory depth
    relpath = os.path.relpath(filepath, admin_root)
    depth = relpath.count(os.sep)
    
    if depth == 0:
        # File is in admin/ root (e.g., dashboard.html, pengaturan.html)
        prefix = '../'
    else:
        # File is in admin/subdir/ (e.g., admin/peminjaman/admin-approval.html)
        prefix = '../../'
    
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content, changed = find_and_replace_navbar(content, prefix)
    
    if changed:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        count += 1
        print(f"✅ Updated: {filepath} (prefix: {prefix})")
    else:
        print(f"⚠️  No navbar found: {filepath}")

print(f"\nTotal files updated: {count}")
