# Hermes PHP Engine

Folder ini berisi engine AI internal berbasis PHP untuk PROJECT. Arsitekturnya meniru pola Hermes seperti memory, skills, tools, dan self-improvement terbatas, tetapi berjalan langsung dari request web tanpa process agent terpisah.

## Jalur web
- Widget frontend: `/assets/js/ai-agent-widget.js`
- Chat engine: `/hermes/chat.php`
- Status admin: `/hermes/status.php`
- Maintenance signal: `/hermes/reindex.php`

## Struktur utama
- `home/` untuk prompt dasar dan persona engine
- `skills/` untuk skill kustom
- `tools/` untuk helper atau wrapper tool
- `runtime/` untuk signal file dan state sementara
- `patches/` untuk catatan self-improvement terbatas
- `logs/` untuk log internal
- `data/` untuk project index, memory, dan artefak data lokal

## Cara kerja
- User membuka website dan mengirim chat lewat widget.
- `hermes/chat.php` membangun konteks dari session, role, page context, project index, memory, dan skill relevan.
- PHP memanggil provider AI langsung lewat endpoint `chat/completions`.
- Hasil percakapan disimpan sebagai memory lokal dan observasi perbaikan terbatas.

## Catatan
- Tidak ada dependensi WSL, gateway, atau CLI untuk alur chat normal.
- Folder ini boleh membaca konteks aplikasi utama PROJECT, tetapi artefak engine tetap disimpan di dalam `PROJECT/hermes`.
