<?php

namespace App\Services\AI;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    private const MAX_MESSAGE_LENGTH = 1000;

    /** @var array<string, array<int, string>> */
    private const SECURITY_PATTERNS = [
        'prompt_injection' => [
            'ignore previous', 'ignore all previous', 'abaikan instruksi', 'abaikan semua instruksi',
            'reveal system prompt', 'show system prompt', 'system prompt', 'hidden instruction',
            'developer message', 'bypass restriction', 'disable security', 'jailbreak', 'daniel mode',
        ],
        'privilege_escalation' => [
            'i am admin', 'saya admin', 'saya superadmin', 'act as admin', 'bertindak sebagai admin',
            'pretend to be admin', 'anggap saya admin', 'naikkan role', 'ubah role',
        ],
        'data_exfiltration' => [
            'database', 'db ', 'schema', 'table', 'tabel', 'column', 'kolom', 'sql', 'select ', 'insert ',
            'update ', 'delete ', 'drop ', 'truncate ', 'dump database', 'database dump', 'raw data',
            'semua data', 'seluruh data', 'semua laporan', 'seluruh laporan', 'data user lain',
        ],
        'secret_extraction' => [
            '.env', 'password', 'kata sandi', 'api key', 'apikey', 'secret', 'token', 'credential',
            'credentials', 'private key', 'access key', 'connection string',
        ],
        'execution' => [
            'run command', 'jalankan command', 'execute command', 'terminal', 'shell', 'bash ', 'ssh ',
            'docker ', 'artisan ', 'filesystem', 'buka file', 'baca file', 'ubah file', 'hapus file',
        ],
        'coding' => [
            'buat kode', 'buatkan kode', 'write code', 'generate code', 'coding', 'script php',
            'script python', 'javascript code', 'sql query', 'exploit', 'payload',
        ],
    ];

    /** @var array<int, array{title:string, content:string, keywords:array<int,string>, roles:array<int,string>}> */
    private const KNOWLEDGE = [
        [
            'title' => 'Cara Membuat Laporan',
            'content' => 'LAPORIN memungkinkan laporan dibuat melalui halaman Buat Laporan. Isi identitas yang diminta, pilih jenis laporan, lengkapi detail kejadian, setujui konfirmasi, lalu kirim. Nomor laporan dan informasi pelacakan digunakan untuk memantau laporan.',
            'keywords' => ['cara', 'buat laporan', 'membuat laporan', 'kirim laporan', 'lapor'],
            'roles' => ['*'],
        ],
        [
            'title' => 'Cara Melacak Laporan',
            'content' => 'Gunakan halaman Lacak untuk memasukkan informasi pelacakan yang diminta sistem. Jangan membagikan kode akses pelacakan kepada orang lain.',
            'keywords' => ['lacak', 'melacak', 'tracking', 'status laporan'],
            'roles' => ['*'],
        ],
        [
            'title' => 'Jenis Laporan',
            'content' => 'LAPORIN menangani laporan pelanggaran/perundungan dan laporan kerusakan fasilitas. Pemrosesan lanjutan mengikuti unit pengelola yang berwenang.',
            'keywords' => ['jenis laporan', 'jenis', 'pelanggaran', 'perundungan', 'kerusakan', 'fasilitas'],
            'roles' => ['*'],
        ],
        [
            'title' => 'Alur Penanganan',
            'content' => 'Laporan masuk melalui proses penerimaan, pemeriksaan/pemrosesan oleh unit yang berwenang, dan penyelesaian sesuai status laporan. AI hanya memberikan informasi dan ringkasan; AI tidak mengubah status laporan.',
            'keywords' => ['alur', 'proses', 'penanganan', 'status', 'diproses', 'selesai'],
            'roles' => ['*'],
        ],
        [
            'title' => 'Batasan AI Chat',
            'content' => 'AI Chat hanya membantu informasi, panduan, dan analisis read-only yang diizinkan berdasarkan peran. AI tidak menyediakan source code, kredensial, struktur database, data mentah, perintah server, atau operasi perubahan data.',
            'keywords' => ['ai', 'bantuan ai', 'kemampuan ai', 'apa yang bisa', 'bisa apa'],
            'roles' => ['*'],
        ],
        [
            'title' => 'Peran Kesiswaan',
            'content' => 'AI untuk Kesiswaan berfokus pada ringkasan informasi laporan pelanggaran yang berada dalam ruang lingkup kerja Kesiswaan.',
            'keywords' => ['kesiswaan', 'pelanggaran', 'ringkasan'],
            'roles' => ['kesiswaan'],
        ],
        [
            'title' => 'Peran Sarpras',
            'content' => 'AI untuk Sarpras berfokus pada ringkasan informasi laporan kerusakan fasilitas yang berada dalam ruang lingkup kerja Sarpras.',
            'keywords' => ['sarpras', 'kerusakan', 'fasilitas', 'ringkasan'],
            'roles' => ['sarpras'],
        ],
        [
            'title' => 'Peran Wali Kelas',
            'content' => 'AI untuk Wali Kelas hanya memberikan ringkasan laporan pelanggaran yang berkaitan dengan kelas yang memang berada dalam kewenangan wali kelas tersebut.',
            'keywords' => ['wali kelas', 'kelas', 'pelanggaran', 'ringkasan'],
            'roles' => ['wali_kelas'],
        ],
        [
            'title' => 'Peran Superadmin',
            'content' => 'AI untuk Superadmin menyediakan ringkasan statistik operasional read-only. Perubahan data tetap dilakukan melalui antarmuka administrasi dengan authorization yang berlaku.',
            'keywords' => ['superadmin', 'admin', 'statistik', 'ringkasan'],
            'roles' => ['superadmin'],
        ],
    ];

    public function answer(?User $user, string $message): array
    {
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return $this->safeResponse('Pertanyaan tidak valid. Silakan gunakan pertanyaan singkat dan relevan dengan LAPORIN.');
        }

        $security = $this->securityCheck($message);
        if ($security !== null) {
            Log::warning('AI_SECURITY_REJECT', [
                'reason' => $security['reason'],
                'user_id' => $user?->getAuthIdentifier(),
                'role' => $this->roleFor($user),
                'message_hash' => hash('sha256', $message),
            ]);

            return $this->safeResponse('Maaf, saya tidak dapat membantu dengan permintaan tersebut. Saya hanya dapat membantu informasi, panduan, dan analisis read-only yang tersedia di LAPORIN.');
        }

        $role = $this->roleFor($user);
        $retrieved = $this->retrieve($message, $role);
        $intent = $this->intent($message);

        if ($intent === 'stats') {
            return $this->safeResponse($this->statisticsAnswer($user), $this->sourceLabels($retrieved));
        }

        if ($intent === 'capability') {
            return $this->safeResponse($this->capabilityAnswer($role), $this->sourceLabels($retrieved));
        }

        if ($retrieved === []) {
            return $this->safeResponse('Saya belum menemukan informasi yang cukup untuk menjawab pertanyaan tersebut. Silakan gunakan menu Panduan, Pertanyaan Umum, atau Lacak di LAPORIN.');
        }

        return $this->safeResponse($this->composeFromKnowledge($retrieved, $intent), $this->sourceLabels($retrieved));
    }

    /** @return array{reason:string}|null */
    private function securityCheck(string $message): ?array
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $message) ?? $message);
        foreach (self::SECURITY_PATTERNS as $reason => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalized, $pattern)) {
                    return ['reason' => $reason];
                }
            }
        }

        if (preg_match('/(?:https?:\/\/|javascript:|data:text\/html)/i', $message)) {
            return ['reason' => 'unsafe_uri'];
        }

        return null;
    }

    private function roleFor(?User $user): string
    {
        if ($user === null) {
            return 'guest';
        }

        foreach (['superadmin', 'kesiswaan', 'sarpras', 'wali_kelas'] as $role) {
            if ($user->isRole($role)) {
                return $role;
            }
        }

        return 'authenticated';
    }

    /** @return array<int, array{title:string, content:string, score:int}> */
    private function retrieve(string $message, string $role): array
    {
        $normalized = mb_strtolower($message);
        $tokens = preg_split('/[^a-z0-9_]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $results = [];

        foreach (self::KNOWLEDGE as $entry) {
            if (! in_array('*', $entry['roles'], true) && ! in_array($role, $entry['roles'], true)) {
                continue;
            }

            $score = 0;
            foreach ($entry['keywords'] as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $score += 4;
                }
            }
            foreach ($tokens as $token) {
                if (mb_strlen($token) >= 4 && str_contains(' '.mb_strtolower($entry['content']).' ', ' '.$token)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $results[] = [
                    'title' => $entry['title'],
                    'content' => $entry['content'],
                    'score' => $score,
                ];
            }
        }

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($results, 0, 3);
    }

    private function intent(string $message): string
    {
        $normalized = mb_strtolower($message);
        if (preg_match('/\b(statistik|berapa|jumlah|total|ringkas.*laporan|laporan.*ringkas)\b/u', $normalized)) {
            return 'stats';
        }
        if (preg_match('/\b(bisa apa|bisa melakukan apa|kemampuan|fitur ai|fungsi ai)\b/u', $normalized)) {
            return 'capability';
        }
        return 'general';
    }

    private function statisticsAnswer(?User $user): string
    {
        $role = $this->roleFor($user);
        if ($user === null || $role === 'guest' || $role === 'authenticated') {
            return 'Statistik laporan internal hanya tersedia untuk akun pengelola yang memiliki kewenangan. Saya tetap dapat membantu panduan dan informasi umum LAPORIN.';
        }

        $query = Report::query();
        $scopeLabel = 'laporan dalam seluruh sistem';

        if ($role === 'kesiswaan') {
            $query->where('report_type', 'violation');
            $scopeLabel = 'laporan pelanggaran dalam ruang lingkup Kesiswaan';
        } elseif ($role === 'sarpras') {
            $query->where('report_type', 'damage');
            $scopeLabel = 'laporan kerusakan dalam ruang lingkup Sarpras';
        } elseif ($role === 'wali_kelas') {
            $classIds = $user->homeroomClasses()->pluck('class_id')->filter()->values()->all();
            if ($classIds === []) {
                return 'Tidak ada kelas wali yang terhubung ke akun ini, sehingga saya tidak memiliki data yang dapat diringkas.';
            }
            $query->where('report_type', 'violation')->whereIn('related_class_id', $classIds);
            $scopeLabel = 'laporan pelanggaran pada kelas yang berada dalam kewenangan Anda';
        }

        $total = (clone $query)->count();
        $statuses = (clone $query)->select('status')->get()->countBy('status')->sortKeys()->all();

        if ($total === 0) {
            return "Saat ini tidak ada $scopeLabel yang dapat saya ringkas berdasarkan kewenangan akun Anda.";
        }

        $parts = ["Total $scopeLabel: $total."];
        if ($statuses !== []) {
            $statusText = [];
            foreach ($statuses as $status => $count) {
                $statusText[] = sprintf('%s: %d', str_replace('_', ' ', (string) $status), $count);
            }
            $parts[] = 'Distribusi status: '.implode(', ', $statusText).'.';
        }

        return implode(' ', $parts);
    }

    private function capabilityAnswer(string $role): string
    {
        return match ($role) {
            'guest' => 'Sebagai pengunjung, saya membantu panduan, FAQ, alur pelaporan, jenis laporan, dan cara melacak laporan. Saya tidak dapat melihat data internal.',
            'kesiswaan' => 'Sebagai AI Kesiswaan, saya membantu panduan dan ringkasan read-only untuk laporan pelanggaran dalam kewenangan Kesiswaan. Saya tidak dapat mengubah status, membuat query bebas, atau mengakses data di luar scope.',
            'sarpras' => 'Sebagai AI Sarpras, saya membantu panduan dan ringkasan read-only untuk laporan kerusakan dalam kewenangan Sarpras. Saya tidak dapat mengubah data atau mengakses data di luar scope.',
            'wali_kelas' => 'Sebagai AI Wali Kelas, saya membantu panduan dan ringkasan read-only untuk laporan pelanggaran yang terkait kelas dalam kewenangan Anda.',
            'superadmin' => 'Sebagai AI Superadmin, saya membantu informasi dan statistik operasional read-only. Perubahan data tetap harus dilakukan melalui panel administrasi dengan authorization normal.',
            default => 'Saya membantu panduan dan informasi umum LAPORIN. Data internal hanya tersedia kepada role yang memang memiliki kewenangan.',
        };
    }

    /** @param array<int, array{title:string, content:string, score:int}> $retrieved */
    private function composeFromKnowledge(array $retrieved, string $intent): string
    {
        $answer = $retrieved[0]['content'];
        if ($intent === 'general' && count($retrieved) > 1) {
            $answer .= ' '.$retrieved[1]['content'];
        }
        return trim($answer);
    }

    /** @param array<int, array{title:string, content:string, score:int}> $retrieved */
    private function sourceLabels(array $retrieved): array
    {
        return array_values(array_unique(array_map(fn (array $item): string => $item['title'], $retrieved)));
    }

    private function safeResponse(string $answer, array $sources = []): array
    {
        $answer = preg_replace('/(?:password|api key|apikey|secret|token)\s*[:=]\s*\S+/iu', '[data sensitif disembunyikan]', $answer) ?? $answer;
        return [
            'ok' => true,
            'answer' => trim($answer),
            'sources' => array_slice($sources, 0, 3),
        ];
    }
}
