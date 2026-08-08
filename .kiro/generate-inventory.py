#!/usr/bin/env python3
import os
import json
from datetime import datetime

inventory = []

# Scan docs folder
if os.path.exists("docs"):
    for f in os.listdir("docs"):
        if f.endswith(".md"):
            path = os.path.join("docs", f)
            try:
                size = os.path.getsize(path)
                with open(path, encoding='utf-8') as file:
                    lines = len(file.readlines())
                
                # Get last modified time
                mtime = os.path.getmtime(path)
                last_modified = datetime.fromtimestamp(mtime).strftime('%Y-%m-%d')
                
                inventory.append({
                    "file": f,
                    "size_bytes": size,
                    "lines": lines,
                    "last_modified": last_modified
                })
            except Exception as e:
                print(f"Error processing {f}: {e}")

# Scan docs/DECISIONS folder
if os.path.exists("docs/DECISIONS"):
    for f in os.listdir("docs/DECISIONS"):
        if f.endswith(".md"):
            path = os.path.join("docs/DECISIONS", f)
            try:
                size = os.path.getsize(path)
                with open(path, encoding='utf-8') as file:
                    lines = len(file.readlines())
                
                mtime = os.path.getmtime(path)
                last_modified = datetime.fromtimestamp(mtime).strftime('%Y-%m-%d')
                
                inventory.append({
                    "file": f"DECISIONS/{f}",
                    "size_bytes": size,
                    "lines": lines,
                    "last_modified": last_modified
                })
            except Exception as e:
                print(f"Error processing DECISIONS/{f}: {e}")

# Sort by file name
inventory_sorted = sorted(inventory, key=lambda x: x["file"])

# Save inventory
with open(".kiro/docs-inventory.json", "w", encoding='utf-8') as out:
    json.dump(inventory_sorted, out, indent=2, ensure_ascii=False)

print(f"✅ Inventory complete: {len(inventory_sorted)} files documented")
print(f"Total size: {sum(f['size_bytes'] for f in inventory_sorted)} bytes")
