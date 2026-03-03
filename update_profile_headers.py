#!/usr/bin/env python3
"""
Standardize Profile Header across all project files
This script replaces all profile dropdowns with the standardized format
"""

import re
import os
import sys

PROJECT_DIR = '/opt/lampp/htdocs/PROJECT'

# List of HTML files to update
HTML_FILES = [
    'admin/dashboard.html',  # Already done, but will be skipped
    'admin/pengaturan.html',
    'admin/peminjaman/sedang-dipinjam.html',
    'admin/peminjaman/data-peminjaman.html',
    'admin/peminjaman/menunggu-persetujuan.html',
    'admin/peminjaman/admin-approval.html',
    'admin/peminjaman/detail-peminjaman.html',
    'admin/pengembalian/pengembalian-barang.html',
    'admin/pengembalian/barang-rusak.html',
    'admin/peminjam/data-peminjam.html',
    'admin/peminjam/riwayat-peminjaman.html',
    'admin/user/buat-user.html',
    'admin/laporan/laporan-stok.html',
    'admin/laporan/laporan-peminjaman.html',
    'admin/laporan/laporan-pengembalian.html',
    'admin/barang/detail-barang.html',
    'user/dashboard.html',
    'user/profil.html',
    'user/riwayat.html',
    'user/peminjaman/status-peminjaman.html',
    'user/peminjaman/ajukan-peminjaman.html',
    'user/pengembalian/ajukan-pengembalian.html',
    'manager/dashboard.html',
    'manager/persetujuan/ditolak.html',
    'manager/persetujuan/disetujui.html',
    'manager/persetujuan/menunggu-approval.html',
    'manager/laporan/laporan-peminjaman.html',
    'manager/laporan/laporan-stok.html',
    'pic-barang/dashboard.html',
    'pic-barang/profil.html',
    'pic-barang/update-barang/update-barang.html',
    'pic-barang/pengembalian/pengembalian-barang.html',
]

NEW_PROFILE_HEADER = '''                    <div class="dropdown nxl-h-item user-profile-header" data-profile-header>
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

def get_relative_path(file_path):
    """Calculate relative path for script tags"""
    depth = file_path.count('/')
    if depth == 1:
        return '../'
    elif depth == 2:
        return '../../'
    elif depth > 2:
        return '../' * (depth + 1)
    return './'

def extract_profile_section(content):
    """Extract the old profile section to replace"""
    # Match the old profile dropdown pattern
    pattern = r'<div class="dropdown nxl-h-item">\s*<a href="javascript:void\(0\);" data-bs-toggle="dropdown" role="button"\s*(?:data-bs-auto-close="outside")?\s*>\s*<img src="[^"]*avatar[^"]*" alt="user-image"\s*class="img-fluid user-avtar[^"]*" />\s*</a>\s*<div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">.*?(?=</div>\s*</div>\s*(?:</div>\s*(?:<!--\! \[End\] Header Right|<!--! \[End\] Header Right)))'
    
    return re.search(pattern, content, re.DOTALL)

def update_profile_header_in_file(file_path):
    """Update profile header in a single file"""
    full_path = os.path.join(PROJECT_DIR, file_path)
    
    if not os.path.exists(full_path):
        print(f"⚠️  File not found: {file_path}")
        return False
    
    try:
        with open(full_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Check if file already has the new profile header format
        if 'data-profile-header' in content:
            print(f"✓ Already updated: {file_path}")
            return True
        
        # Check if file has the old profile header
        if 'nxl-user-dropdown' not in content:
            print(f"⚠️  No profile header found: {file_path}")
            return False
        
        # Find and replace the old profile section
        # Use a simpler, more reliable pattern matching
        old_pattern = r'<div class="dropdown nxl-h-item">\s*<a href="javascript:void\(0\);" data-bs-toggle="dropdown" role="button"[^>]*>\s*<img[^>]*user-avtar[^>]*>\s*</a>\s*<div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">.*?(?=\s*</div>\s*</div>\s*</div>\s*<!--! \[End\] Header Right)'
        
        new_content = re.sub(old_pattern, NEW_PROFILE_HEADER, content, flags=re.DOTALL)
        
        if new_content == content:
            print(f"⚠️  Pattern not matched: {file_path}")
            return False
        
        # Also update logout.js script tag
        if '../assets/js/auth/logout.js' in new_content:
            # Check if profile-header.js is already there
            if '../assets/js/profile-header.js' not in new_content and '../../assets/js/profile-header.js' not in new_content:
                # Add profile-header.js after logout.js
                logout_pattern = r'(<script src="(?:\.\./)(?:\.\.\/)?assets/js/auth/logout\.js"><\/script>)'
                relative_path = get_relative_path(file_path)
                profile_script = f'{logout_pattern_match}\n    <script src="{relative_path}assets/js/profile-header.js"></script>'
                
                # First find logout.js location
                if '<script src="../assets/js/auth/logout.js"></script>' in new_content:
                    new_content = new_content.replace(
                        '<script src="../assets/js/auth/logout.js"></script>',
                        '<script src="../assets/js/auth/logout.js"></script>\n    <script src="../assets/js/profile-header.js"></script>'
                    )
                elif '<script src="../../assets/js/auth/logout.js"></script>' in new_content:
                    new_content = new_content.replace(
                        '<script src="../../assets/js/auth/logout.js"></script>',
                        '<script src="../../assets/js/auth/logout.js"></script>\n    <script src="../../assets/js/profile-header.js"></script>'
                    )
        
        with open(full_path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        
        print(f"✓ Updated: {file_path}")
        return True
    
    except Exception as e:
        print(f"✗ Error updating {file_path}: {str(e)}")
        return False

if __name__ == '__main__':
    print("Starting profile header standardization...\n")
    
    success_count = 0
    fail_count = 0
    
    for file in HTML_FILES:
        if update_profile_header_in_file(file):
            success_count += 1
        else:
            fail_count += 1
    
    print(f"\n\nSummary:")
    print(f"✓ Successfully updated: {success_count}")
    print(f"✗ Failed or skipped: {fail_count}")
    print(f"Total: {len(HTML_FILES)}")
