<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WahaService
{
    public function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.waha.url'), '/');
        $apiKey = (string) config('services.waha.api_key');
        if ($baseUrl === '' || $apiKey === '') throw new RuntimeException('WAHA is not configured. Set WAHA_URL and WAHA_API_KEY.');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Api-Key' => $apiKey])
            ->connectTimeout(5)
            ->timeout(30)
            ->retry(2, 250, fn (Throwable $exception): bool => $this->isTransient($exception));
    }

    /**
     * Tanpa callback ini Laravel mengulang SEMUA response gagal, termasuk 4xx.
     * Kondisi seperti "nomor tidak terdaftar", sesi tidak ditemukan (404), atau
     * payload ditolak (422) bersifat permanen: mengulanginya hanya menggandakan
     * beban WAHA tanpa pernah mengubah hasil. Job-level retry sudah menangani
     * gangguan yang benar-benar sementara.
     */
    private function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException || $exception->response === null) {
            return false;
        }

        $status = $exception->response->status();

        return $status === 429 || $status >= 500;
    }

    public function session(string $session): array
    {
        $response = $this->client()->get('/api/sessions/'.rawurlencode($session));
        $response->throw();

        return $response->json();
    }

    public function sendText(string $chatId, string $text, ?string $session = null): array
    {
        $response = $this->client()->post('/api/sendText', ['session' => $session ?: config('services.waha.session', 'default'), 'chatId' => $chatId, 'text' => $text]);
        $response->throw(); return $response->json();
    }


    public function sendImageFile(
        string $chatId,
        string $path,
        ?string $caption = null,
        ?string $session = null
    ): array {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('WAHA image file is not readable: '.$path);
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';
        $data = base64_encode((string) file_get_contents($path));
        if ($data === '') {
            throw new RuntimeException('WAHA image file is empty: '.$path);
        }

        $response = $this->client()->post('/api/sendImage', [
            'session' => $session ?: config('services.waha.session', 'default'),
            'chatId' => $chatId,
            'file' => [
                'mimetype' => $mime,
                'filename' => basename($path),
                'data' => $data,
            ],
            'caption' => $caption,
        ]);

        $response->throw();
        return $response->json();
    }

    public function checkNumberExists(string $phone, ?string $session = null): array
    {
        $response = $this->client()->get('/api/contacts/check-exists', [
            'phone' => $phone,
            'session' => $session ?: config('services.waha.session', 'default'),
        ]);

        $response->throw();
        return $response->json();
    }

    public function sessions(): array
    {
        $response = $this->client()->get('/api/sessions'); $response->throw(); return $response->json();
    }
}
