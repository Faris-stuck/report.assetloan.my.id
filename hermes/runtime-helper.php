<?php

function aiAgentIniFlagEnabled(string $name): bool
{
    $value = ini_get($name);
    if ($value === false) {
        return false;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'on', 'yes', 'true'], true);
}

function aiAgentHasHttpTransport(): bool
{
    return function_exists('curl_init')
        || aiAgentIniFlagEnabled('allow_url_fopen')
        || function_exists('stream_socket_client');
}

function aiAgentStringLength(string $text): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($text);
    }

    return strlen($text);
}

function aiAgentStringSubstring(string $text, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null
            ? (string) mb_substr($text, $start)
            : (string) mb_substr($text, $start, $length);
    }

    return $length === null
        ? (string) substr($text, $start)
        : (string) substr($text, $start, $length);
}

function aiAgentHttpRequest(string $method, string $url, array $options = []): array
{
    $method = strtoupper(trim($method));
    $headers = $options['headers'] ?? [];
    $body = (string) ($options['body'] ?? '');
    $timeout = max(1, (int) ($options['timeout'] ?? 30));
    $connectTimeout = max(1, (int) ($options['connect_timeout'] ?? 10));
    $lastResponse = [
        'ok' => false,
        'http_code' => 0,
        'body' => '',
        'error' => 'No supported HTTP transport is available on this server.',
        'transport' => 'none',
        'headers' => [],
    ];

    if (function_exists('curl_init')) {
        $lastResponse = aiAgentHttpRequestWithCurl($method, $url, $headers, $body, $timeout, $connectTimeout);
        if ($lastResponse['ok']) {
            return $lastResponse;
        }
    }

    if (aiAgentIniFlagEnabled('allow_url_fopen')) {
        $lastResponse = aiAgentHttpRequestWithStreams($method, $url, $headers, $body, $timeout);
        if ($lastResponse['ok']) {
            return $lastResponse;
        }
    }

    if (function_exists('stream_socket_client')) {
        $lastResponse = aiAgentHttpRequestWithSocket($method, $url, $headers, $body, $timeout, $connectTimeout);
        if ($lastResponse['ok']) {
            return $lastResponse;
        }
    }

    return $lastResponse;
}

function aiAgentHttpRequestWithCurl(string $method, string $url, array $headers, string $body, int $timeout, int $connectTimeout): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        return [
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'error' => 'Failed to initialize cURL.',
            'transport' => 'curl',
            'headers' => [],
        ];
    }

    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
    ];

    if ($body !== '' && $method !== 'GET' && $method !== 'HEAD') {
        $curlOptions[CURLOPT_POSTFIELDS] = $body;
    }

    if ($method === 'HEAD') {
        $curlOptions[CURLOPT_NOBODY] = true;
    }

    curl_setopt_array($curl, $curlOptions);

    $rawResponse = curl_exec($curl);
    $error = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    if ($rawResponse === false) {
        return [
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'error' => $error !== '' ? $error : 'Unknown cURL error.',
            'transport' => 'curl',
            'headers' => [],
        ];
    }

    $rawHeaders = substr($rawResponse, 0, $headerSize);
    $responseBody = substr($rawResponse, $headerSize);

    return [
        'ok' => $httpCode > 0,
        'http_code' => $httpCode,
        'body' => (string) $responseBody,
        'error' => $error,
        'transport' => 'curl',
        'headers' => aiAgentNormalizeResponseHeaders($rawHeaders),
    ];
}

function aiAgentHttpRequestWithStreams(string $method, string $url, array $headers, string $body, int $timeout): array
{
    $headerLines = $headers;
    if ($body !== '' && !aiAgentHeaderExists($headerLines, 'Content-Length')) {
        $headerLines[] = 'Content-Length: ' . strlen($body);
    }
    if (!aiAgentHeaderExists($headerLines, 'Connection')) {
        $headerLines[] = 'Connection: close';
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => $timeout,
            'protocol_version' => 1.1,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $errorMessage = '';
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        $lastError = error_get_last();
        $errorMessage = (string) ($lastError['message'] ?? 'HTTP stream request failed.');
    }

    $responseHeaders = isset($http_response_header) && is_array($http_response_header)
        ? $http_response_header
        : [];
    $httpCode = aiAgentParseHttpCodeFromHeaderLines($responseHeaders);

    return [
        'ok' => $httpCode > 0,
        'http_code' => $httpCode,
        'body' => is_string($result) ? $result : '',
        'error' => $errorMessage,
        'transport' => 'stream',
        'headers' => $responseHeaders,
    ];
}

function aiAgentHttpRequestWithSocket(string $method, string $url, array $headers, string $body, int $timeout, int $connectTimeout): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return [
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'error' => 'Invalid provider URL.',
            'transport' => 'socket',
            'headers' => [],
        ];
    }

    $scheme = strtolower((string) $parts['scheme']);
    $host = (string) $parts['host'];
    $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    $path = (string) ($parts['path'] ?? '/');
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    $target = $path . $query;
    $transport = $scheme === 'https' ? 'ssl' : 'tcp';

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        $transport . '://' . $host . ':' . $port,
        $errno,
        $errstr,
        $connectTimeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!is_resource($socket)) {
        return [
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'error' => $errstr !== '' ? $errstr : ('Socket connection failed (' . $errno . ').'),
            'transport' => 'socket',
            'headers' => [],
        ];
    }

    stream_set_timeout($socket, $timeout);

    $requestHeaders = [
        'Host: ' . $host,
        'Connection: close',
    ];

    foreach ($headers as $header) {
        if (!aiAgentHeaderExists($requestHeaders, aiAgentGetHeaderName($header))) {
            $requestHeaders[] = $header;
        }
    }

    if ($body !== '' && !aiAgentHeaderExists($requestHeaders, 'Content-Length')) {
        $requestHeaders[] = 'Content-Length: ' . strlen($body);
    }

    $request = $method . ' ' . $target . " HTTP/1.1\r\n"
        . implode("\r\n", $requestHeaders)
        . "\r\n\r\n"
        . $body;

    fwrite($socket, $request);

    $rawResponse = '';
    while (!feof($socket)) {
        $chunk = fread($socket, 8192);
        if ($chunk === false) {
            break;
        }
        $rawResponse .= $chunk;
    }

    $meta = stream_get_meta_data($socket);
    fclose($socket);

    if ($rawResponse === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'error' => !empty($meta['timed_out']) ? 'Socket request timed out.' : 'Empty response from provider.',
            'transport' => 'socket',
            'headers' => [],
        ];
    }

    $separatorPos = strpos($rawResponse, "\r\n\r\n");
    $rawHeaders = $separatorPos !== false ? substr($rawResponse, 0, $separatorPos) : $rawResponse;
    $responseBody = $separatorPos !== false ? substr($rawResponse, $separatorPos + 4) : '';
    $headerLines = preg_split("/\r\n/", $rawHeaders) ?: [];
    $httpCode = aiAgentParseHttpCodeFromHeaderLines($headerLines);

    if (aiAgentHeaderIndicatesChunked($headerLines)) {
        $responseBody = aiAgentDecodeChunkedBody($responseBody);
    }

    return [
        'ok' => $httpCode > 0,
        'http_code' => $httpCode,
        'body' => $responseBody,
        'error' => !empty($meta['timed_out']) ? 'Socket request timed out.' : '',
        'transport' => 'socket',
        'headers' => $headerLines,
    ];
}

function aiAgentParseHttpCodeFromHeaderLines(array $headerLines): int
{
    foreach ($headerLines as $line) {
        if (preg_match('#^HTTP/\d+(?:\.\d+)?\s+(\d{3})#i', trim((string) $line), $matches)) {
            return (int) $matches[1];
        }
    }

    return 0;
}

function aiAgentNormalizeResponseHeaders(string $rawHeaders): array
{
    $lines = preg_split("/\r\n|\n|\r/", trim($rawHeaders)) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static function ($line) {
        return $line !== '';
    }));
}

function aiAgentGetHeaderName(string $header): string
{
    $parts = explode(':', $header, 2);
    return trim((string) ($parts[0] ?? ''));
}

function aiAgentHeaderExists(array $headers, string $name): bool
{
    $needle = strtolower(trim($name));
    foreach ($headers as $header) {
        if (strtolower(aiAgentGetHeaderName((string) $header)) === $needle) {
            return true;
        }
    }

    return false;
}

function aiAgentHeaderIndicatesChunked(array $headers): bool
{
    foreach ($headers as $header) {
        if (stripos((string) $header, 'Transfer-Encoding:') === 0 && stripos((string) $header, 'chunked') !== false) {
            return true;
        }
    }

    return false;
}

function aiAgentDecodeChunkedBody(string $body): string
{
    $decoded = '';
    $position = 0;
    $length = strlen($body);

    while ($position < $length) {
        $lineEnd = strpos($body, "\r\n", $position);
        if ($lineEnd === false) {
            break;
        }

        $chunkLengthHex = trim(substr($body, $position, $lineEnd - $position));
        if ($chunkLengthHex === '') {
            break;
        }

        $chunkLength = hexdec($chunkLengthHex);
        $position = $lineEnd + 2;

        if ($chunkLength === 0) {
            break;
        }

        $decoded .= substr($body, $position, $chunkLength);
        $position += $chunkLength + 2;
    }

    return $decoded !== '' ? $decoded : $body;
}

/**
 * Priority 4: Call extended provider (fallback)
 */
function aiAgentCallExtendedProvider(array $extConfig, string $systemPrompt, array $messages = []): array
{
    if (empty($extConfig['enabled']) || empty($extConfig['base_url']) || empty($extConfig['api_key'])) {
        return [
            'ok' => false,
            'error' => 'Extended provider not configured',
            'provider' => 'none',
        ];
    }

    $type = strtolower(trim($extConfig['type'] ?? 'openai'));

    if ($type === 'openai') {
        return aiAgentCallOpenAIProvider($extConfig, $systemPrompt, $messages);
    } elseif ($type === 'anthropic') {
        return aiAgentCallAnthropicProvider($extConfig, $systemPrompt, $messages);
    } elseif ($type === 'local') {
        return aiAgentCallLocalOllamaProvider($extConfig, $systemPrompt, $messages);
    }

    return [
        'ok' => false,
        'error' => 'Unsupported extended provider type: ' . $type,
        'provider' => $type,
    ];
}

/**
 * Call OpenAI-compatible provider
 */
function aiAgentCallOpenAIProvider(array $extConfig, string $systemPrompt, array $messages): array
{
    $payload = [
        'model' => $extConfig['model'] ?? 'gpt-4o-mini',
        'messages' => array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            array_map(function ($msg) {
                return [
                    'role' => ($msg['role'] ?? 'user') === 'user' ? 'user' : 'assistant',
                    'content' => trim((string) ($msg['content'] ?? '')),
                ];
            }, $messages)
        ),
        'temperature' => 0.15,
        'max_tokens' => 900,
    ];

    $result = aiAgentHttpRequest('POST', rtrim($extConfig['base_url'], '/') . '/chat/completions', [
        'headers' => [
            'Authorization: Bearer ' . $extConfig['api_key'],
            'Content-Type: application/json',
        ],
        'body' => json_encode($payload),
        'timeout' => max(5, (int) ($extConfig['timeout'] ?? 30)),
    ]);

    if (!$result['ok']) {
        return [
            'ok' => false,
            'error' => $result['error'] ?? 'Request failed',
            'provider' => 'openai',
            'http_code' => $result['http_code'] ?? 0,
        ];
    }

    $decoded = json_decode($result['body'], true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Invalid JSON response from provider',
            'provider' => 'openai',
        ];
    }

    if (isset($decoded['error'])) {
        return [
            'ok' => false,
            'error' => $decoded['error']['message'] ?? 'Provider error',
            'provider' => 'openai',
        ];
    }

    $reply = '';
    if (isset($decoded['choices'][0]['message']['content'])) {
        $reply = trim((string) $decoded['choices'][0]['message']['content']);
    }

    if ($reply === '') {
        return [
            'ok' => false,
            'error' => 'No content in provider response',
            'provider' => 'openai',
        ];
    }

    return [
        'ok' => true,
        'provider' => 'openai',
        'model' => $extConfig['model'] ?? 'gpt-4o-mini',
        'reply' => $reply,
        'usage' => $decoded['usage'] ?? null,
    ];
}

/**
 * Call Anthropic Claude provider (stub for future implementation)
 */
function aiAgentCallAnthropicProvider(array $extConfig, string $systemPrompt, array $messages): array
{
    // Anthropic API requires different message format (Messages API v1)
    // Stub untuk future implementation
    return [
        'ok' => false,
        'error' => 'Anthropic provider not yet implemented',
        'provider' => 'anthropic',
    ];
}

/**
 * Call local Ollama provider
 */
function aiAgentCallLocalOllamaProvider(array $extConfig, string $systemPrompt, array $messages): array
{
    $payload = [
        'model' => $extConfig['model'] ?? 'mistral',
        'messages' => array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            array_map(function ($msg) {
                return [
                    'role' => ($msg['role'] ?? 'user') === 'user' ? 'user' : 'assistant',
                    'content' => trim((string) ($msg['content'] ?? '')),
                ];
            }, $messages)
        ),
        'stream' => false,
        'temperature' => 0.15,
    ];

    $result = aiAgentHttpRequest('POST', rtrim($extConfig['base_url'], '/') . '/api/chat', [
        'headers' => [
            'Content-Type: application/json',
        ],
        'body' => json_encode($payload),
        'timeout' => max(5, (int) ($extConfig['timeout'] ?? 30)),
    ]);

    if (!$result['ok']) {
        return [
            'ok' => false,
            'error' => $result['error'] ?? 'Local provider request failed',
            'provider' => 'local',
            'http_code' => $result['http_code'] ?? 0,
        ];
    }

    $decoded = json_decode($result['body'], true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'Invalid response from local provider',
            'provider' => 'local',
        ];
    }

    $reply = trim((string) ($decoded['message']['content'] ?? ''));
    if ($reply === '') {
        return [
            'ok' => false,
            'error' => 'No content in local provider response',
            'provider' => 'local',
        ];
    }

    return [
        'ok' => true,
        'provider' => 'local',
        'model' => $extConfig['model'] ?? 'mistral',
        'reply' => $reply,
    ];
}
