<?php

namespace App\Services\AI;

use RuntimeException;

final class EmbeddedLlm
{
    private const LOCK_FILE = '/tmp/laporin-ai-inference.lock';
    private const LOCK_WAIT_MS = 3000;
    private const LOCK_SLEEP_US = 50000;

    public static function generate(string $prompt): ?string
    {
        $lockHandle = fopen(self::LOCK_FILE, 'c');
        if ($lockHandle === false) {
            return null;
        }

        $locked = false;
        $deadline = microtime(true) + (self::LOCK_WAIT_MS / 1000);
        do {
            $locked = flock($lockHandle, LOCK_EX | LOCK_NB);
            if (! $locked) {
                usleep(self::LOCK_SLEEP_US);
            }
        } while (! $locked && microtime(true) < $deadline);

        if (! $locked) {
            fclose($lockHandle);
            return null;
        }

        try {
            if (! function_exists('laporin_ai_generate_native')) {
                throw new RuntimeException('Embedded AI native extension is unavailable.');
            }

            $generated = \laporin_ai_generate_native($prompt);
            if (! is_string($generated) || trim($generated) === '') {
                return null;
            }

            return trim($generated);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}
