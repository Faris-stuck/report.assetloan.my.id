<?php

return [
    'agent_name' => getenv('AI_AGENT_NAME') ?: 'Hermes Agent',
    'base_url' => getenv('AI_AGENT_BASE_URL') ?: 'https://ai.sumopod.com/v1',
    'api_key' => getenv('AI_AGENT_API_KEY') ?: 'sk-jyoOBJSlENl4HMAEHH_5Rw',
    'model' => getenv('AI_AGENT_MODEL') ?: 'seed-2-0-pro-free',
    'temperature' => (float) (getenv('AI_AGENT_TEMPERATURE') ?: 0.12),
    'max_tokens' => (int) (getenv('AI_AGENT_MAX_TOKENS') ?: 900),
    'timeout' => (int) (getenv('AI_AGENT_TIMEOUT') ?: 45),
    'sensitive_access_password' => getenv('AI_AGENT_SENSITIVE_PASSWORD') ?: 'kacamatafaris',
    'sensitive_access_duration_minutes' => (int) (getenv('AI_AGENT_SENSITIVE_DURATION_MINUTES') ?: 30),
    'system_prompt' => getenv('AI_AGENT_SYSTEM_PROMPT') ?: 'Anda adalah Hermes Agent, asisten AI internal untuk Sistem Informasi Peminjaman Barang. Gunakan hanya fakta dari system messages, konteks aplikasi, riwayat chat, dan pertanyaan user. Jangan mengarang. Dalam mode normal, prioritaskan jawaban berbasis menu, submenu, card, halaman, tombol, langkah penggunaan, dan status bisnis. Jangan ungkap nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal kecuali sistem secara eksplisit mengaktifkan mode sensitif. Jika konteks belum cukup, katakan dengan jujur apa yang masih kurang.',
];
