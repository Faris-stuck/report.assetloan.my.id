# Hermes PHP Workspace

Folder ini adalah rumah engine AI internal PROJECT yang meniru pola kerja Hermes dalam implementasi PHP.

## Scope
- Working directory default untuk engine ini adalah `PROJECT/hermes`.
- Root aplikasi utama berada satu level di atas folder ini: `PROJECT/`.
- Engine boleh membaca file aplikasi di parent workspace jika helper runtime mengizinkan.
- Perubahan mandiri engine diprioritaskan ke dalam folder ini lebih dulu.

## Preferred Write Paths
- `skills/` untuk skill baru atau skill yang diperbarui
- `tools/` untuk tool wrapper, helper, dan integrasi MCP
- `patches/` untuk hasil refleksi, patch, atau usulan perubahan
- `runtime/` untuk signal file, pid, dan state jangka pendek
- `logs/` untuk log internal
- `data/` untuk cache, memory store lokal, dan artefak pendukung
- `home/` untuk `SOUL.md`, `.env`, dan prompt dasar

## Project Notes
- Endpoint web PHP utama berada di file `chat.php`, `status.php`, `lock.php`, dan `reindex.php`.
- Widget frontend memanggil endpoint `/hermes/chat.php`.
- File `context-helper.php`, `codebase-helper.php`, `index-helper.php`, `tool-helper.php`, `memory-helper.php`, `skills-helper.php`, dan `self-improve-helper.php` adalah bagian runtime aktif engine ini.
- Jika menjawab pertanyaan penggunaan aplikasi, prioritaskan istilah menu, halaman, tombol, card, status, dan alur pengguna.
- Jika user meminta detail teknis, Anda boleh menyebut file, path, endpoint, atau struktur implementasi secara eksplisit.

## Self-Improvement
- Simpan pembelajaran yang stabil sebagai memory, skill, tool, atau catatan di dalam folder ini.
- Jangan menaruh artefak self-improvement di luar `PROJECT/hermes` kecuali memang diminta user.
- Self-improvement bersifat terbatas: utamakan observasi, refleksi, dan draft patch, bukan modifikasi otomatis ke codebase utama tanpa permintaan user.
