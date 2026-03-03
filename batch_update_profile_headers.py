#!/usr/bin/env python3
"""
Bulk update profile headers across all HTML files in the project
Replaces old hardcoded profile dropdowns with standardized format
"""

import os
import re
import sys

PROJECT_DIR = '/opt/lampp/htdocs/PROJECT'

# Files to update (relative paths from PROJECT_DIR)
FILES_TO_UPDATE = [
    # Admin pages
    ('admin/pengaturan.html', '../'),
    ('admin/peminjaman/sedang-dipinjam.html', '../../'),
    ('admin/peminjaman/data-peminjaman.html', '../../'),
    ('admin/peminjaman/menunggu-persetujuan.html', '../../'),
    ('admin/peminjaman/admin-approval.html', '../../'),
    ('admin/peminjaman/detail-peminjaman.html', '../../'),
    ('admin/pengembalian/pengembalian-barang.html', '../../'),
    ('admin/pengembalian/barang-rusak.html', '../../'),
    ('admin/peminjam/data-peminjam.html', '../../'),
    ('admin/peminjam/riwayat-peminjaman.html', '../../'),
    ('admin/user/buat-user.html', '../../'),
    ('admin/laporan/laporan-stok.html', '../../'),
    ('admin/laporan/laporan-peminjaman.html', '../../'),
    ('admin/laporan/laporan-pengembalian.html', '../../'),
    ('admin/barang/detail-barang.html', '../../'),
    
    # User pages
    ('user/dashboard.html', '../'),
    ('user/profil.html', '../'),
    ('user/riwayat.html', '../'),
    ('user/peminjaman/status-peminjaman.html', '../../'),
    ('user/peminjaman/ajukan-peminjaman.html', '../../'),
    ('user/pengembalian/ajukan-pengembalian.html', '../../'),
    
    # Manager pages
    ('manager/dashboard.html', '../'),
    ('manager/persetujuan/ditolak.html', '../../'),
    ('manager/persetujuan/disetujui.html', '../../'),
    ('manager/persetujuan/menunggu-approval.html', '../../'),
    ('manager/laporan/laporan-peminjaman.html', '../../'),
    ('manager/laporan/laporan-stok.html', '../../'),
    
    # Pic-Barang pages
    ('pic-barang/dashboard.html', '../'),
    ('pic-barang/profil.html', '../'),
    ('pic-barang/update-barang/update-barang.html', '../../'),
    ('pic-barang/pengembalian/pengembalian-barang.html', '../../'),
]

def get_new_profile_header(base_path):
    """Generate new profile header HTML with correct base path"""
    return f'''                    <div class="dropdown nxl-h-item user-profile-header" data-profile-header>
                        <div class="user-profile-info" data-bs-toggle="dropdown" role="button">
                            <div class="user-name" data-user-name>Loading...</div>
                            <div class="user-email" data-user-email></div>
                        </div>
                        <div class="dropdown-menu dropdown-menu-end user-profile-dropdown">
                            <a href="javascript:void(0);" data-logout class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>'''

def find_old_profile_section(content):
    """
    Find the old profile section in the content
    Matches the pattern starting with <div class="dropdown nxl-h-item"> 
    and ending before </div></div></div><!--! [End] Header Right
    """
    # Pattern to match the entire old profile dropdown
    pattern = r'<div class="dropdown nxl-h-item">\s*<a href="javascript:void\(0\);" data-bs-toggle="dropdown" role="button"[^>]*>\s*<img[^>]*user-avtar[^>]*>\s*</a>\s*<div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">.*?(?=\s*</div>\s*</div>\s*</div>\s*<!--! \[End\] Header Right)'
    
    match = re.search(pattern, content, re.DOTALL)
    return match

def update_file(file_path, base_path):
    """
    Update a single file with the new profile header
    """
    full_path = os.path.join(PROJECT_DIR, file_path)
    
    if not os.path.exists(full_path):
        print(f"✗ File not found: {file_path}")
        return False
    
    try:
        with open(full_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Check if already updated
        if 'data-profile-header' in content:
            print(f"✓ Already updated: {file_path}")
            return True
        
        # Check if file has the old profile header to replace
        if 'nxl-user-dropdown' not in content:
            print(f"⚠ No old profile header found: {file_path}")
            return False
        
        # Find the old profile section
        match = find_old_profile_section(content)
        if not match:
            print(f"✗ Could not match old profile pattern: {file_path}")
            return False
        
        # Replace the old section with new one
        new_content = content[:match.start()] + get_new_profile_header(base_path) + content[match.end():]
        
        # Now add script tag for profile-header.js if not already there
        script_pattern = r'(<script src="' + re.escape(base_path) + r'assets/js/auth/logout\.js"><\/script>)'
        if re.search(script_pattern, new_content):
            profile_script = f'\\1\n    <script src="{base_path}assets/js/profile-header.js"></script>'
            new_content = re.sub(script_pattern, profile_script, new_content)
        elif '<script src="../assets/js/auth/logout.js"></script>' in new_content:
            new_content = new_content.replace(
                '<script src="../assets/js/auth/logout.js"></script>',
                '<script src="../assets/js/auth/logout.js"></script>\n    <script src="../assets/js/profile-header.js"></script>'
            )
        elif '<script src="../../assets/js/auth/logout.js"></script>' in new_content:
            new_content = new_content.replace(
                '<script src="../../assets/js/auth/logout.js"></script>',
                '<script src="../../assets/js/auth/logout.js"></script>\n    <script src="../../assets/js/profile-header.js"></script>'
            )
        else:
            print(f"⚠ Could not find logout.js script tag: {file_path}")
        
        # Write updated content back
        with open(full_path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        
        print(f"✓ Updated: {file_path}")
        return True
    
    except Exception as e:
        print(f"✗ Error updating {file_path}: {str(e)}")
        return False

def main():
    print("=" * 70)
    print("STANDARDIZING PROFILE HEADERS ACROSS ALL PROJECT FILES")
    print("=" * 70)
    print()
    
    success_count = 0
    fail_count = 0
    skip_count = 0
    
    for file_path, base_path in FILES_TO_UPDATE:
        result = update_file(file_path, base_path)
        if result is True:
            success_count += 1
        elif result is False:
            fail_count += 1
        else:
            skip_count += 1
    
    print()
    print("=" * 70)
    print(f"✓ Successfully updated: {success_count}")
    print(f"✗ Failed or errors: {fail_count}")
    print(f"⚠ Already updated: {skip_count}")
    print(f"Total processed: {len(FILES_TO_UPDATE)}")
    print("=" * 70)

if __name__ == '__main__':
    main()
