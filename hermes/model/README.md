# Model Folder

Folder ini dipakai untuk loader dan validator konfigurasi AI Hermes.

Yang biasanya diubah:
- `../config/ai_agent.php` di dalam folder Hermes sebagai satu-satunya sumber konfigurasi model/provider Hermes.
- `config-helper.php` hanya untuk load, validasi, dan normalisasi config.
- `../home/.env.example` hanya contoh env non-AI untuk deploy.

Kalau ingin ganti model:
- ubah `model` di `PROJECT/hermes/config/ai_agent.php`
- bila perlu ubah juga `extended_provider_*` di file yang sama
