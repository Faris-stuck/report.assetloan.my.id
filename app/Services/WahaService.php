<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WahaService
{
    public function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.waha.url'), '/');
        $apiKey = (string) config('services.waha.api_key');
        if ($baseUrl === '' || $apiKey === '') throw new RuntimeException('WAHA is not configured. Set WAHA_URL and WAHA_API_KEY.');
        return Http::baseUrl($baseUrl)->acceptJson()->asJson()->withHeaders(['X-Api-Key' => $apiKey])->connectTimeout(5)->timeout(30)->retry(2, 250);
    }
    public function sendText(string $chatId, string $text, ?string $session = null): array
    {
        $response = $this->client()->post('/api/sendText', ['session' => $session ?: config('services.waha.session', 'default'), 'chatId' => $chatId, 'text' => $text]);
        $response->throw(); return $response->json();
    }
    public function sendImage(string $chatId, string $imageUrl, ?string $caption = null, ?string $session = null): array
    {
        $payload = ['session' => $session ?: config('services.waha.session', 'default'), 'chatId' => $chatId, 'file' => ['url' => $imageUrl]];
        if ($caption !== null && $caption !== '') $payload['caption'] = $caption;
        $response = $this->client()->post('/api/sendImage', $payload); $response->throw(); return $response->json();
    }
    public function checkNumberExists(string $phone, ?string $session = null): array
    {
        $response = $this->client()->get('/api/contacts/check-exists', ['phone' => $phone, 'session' => $session ?: config('services.waha.session', 'default')]);
        $response->throw(); return $response->json();
    }
    public function sessions(): array
    {
        $response = $this->client()->get('/api/sessions'); $response->throw(); return $response->json();
    }
}
