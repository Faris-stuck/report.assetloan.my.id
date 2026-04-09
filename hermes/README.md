# Hermes PHP Engine

Folder ini berisi engine AI internal berbasis PHP untuk PROJECT. Arsitekturnya meniru pola Hermes seperti memory, skills, tools, dan self-improvement terbatas, tetapi berjalan langsung dari request web tanpa process agent terpisah.

## Jalur web
- Widget frontend: `/assets/js/ai-agent-widget.js`
- Chat engine: `/hermes/chat.php`
- Status admin: `/hermes/status.php`
- Maintenance signal: `/hermes/reindex.php`

## Struktur utama
- `model/` untuk loader, validator, dan helper konfigurasi AI
- `config/` untuk `ai_agent.php`, schema, role, keyword, limit, dan string config Hermes
- `engine/` untuk helper runtime aktif seperti grounding, indexing, tools, dan self-improvement
- `memory/` untuk conversation memory dan memory context builder
- `database/` untuk helper memory berbasis database, utilitas maintenance, dan test database
- `docs/` untuk panduan, arsitektur, dan catatan implementasi
- `home/` untuk prompt dasar dan persona engine
- `skills/` untuk skill kustom
- `tools/` untuk helper atau wrapper tool
- `runtime/` untuk signal file dan state sementara
- `patches/` untuk catatan self-improvement terbatas
- `logs/` untuk log internal
- `data/` untuk project index, memory, dan artefak data lokal

## Konvensi rapih folder
- Root `hermes/` diprioritaskan untuk endpoint web dan helper runtime aktif.
- Pengaturan model dan provider aktif dipusatkan di `PROJECT/hermes/config/ai_agent.php`.
- `model/` hanya berisi loader dan validator konfigurasi Hermes.
- Konfigurasi Hermes yang statis dipusatkan di `config/`.
- File panduan dipindah ke `docs/` agar root tidak penuh dokumentasi.
- Utility dan test database dipusatkan di `database/`.

## Cara kerja
- User membuka website dan mengirim chat lewat widget.
- `hermes/chat.php` membangun konteks dari session, role, page context, project index, memory, dan skill relevan.
- PHP memanggil provider AI langsung lewat endpoint `chat/completions`.
- Hasil percakapan disimpan sebagai memory lokal dan observasi perbaikan terbatas.

## Catatan
- Tidak ada dependensi WSL, gateway, atau CLI untuk alur chat normal.
- Folder ini boleh membaca konteks aplikasi utama PROJECT, tetapi artefak engine tetap disimpan di dalam `PROJECT/hermes`.
