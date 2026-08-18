<?php

namespace App\Services\AI;

use FFI;
use RuntimeException;

final class EmbeddedLlm
{
    private const BUFFER_SIZE = 8192;
    private const LOCK_FILE = '/tmp/laporin-ai-inference.lock';
    private const LOCK_WAIT_MS = 3000;
    private const LOCK_SLEEP_US = 50000;

    private static ?FFI $ffi = null;

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
            $ffi = self::ffi();
        $buffer = $ffi->new('char['.self::BUFFER_SIZE.']', false);
        $buffer[0] = 0;

        $result = $ffi->laporin_ai_generate($prompt, $buffer, self::BUFFER_SIZE);
        if ($result !== 0) {
            return null;
        }

        $output = FFI::string($buffer);
        return $output !== '' ? trim($output) : null;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private static function ffi(): FFI
    {
        if (! extension_loaded('FFI')) {
            throw new RuntimeException('Embedded AI runtime is unavailable.');
        }

        return self::$ffi ??= FFI::scope('LAPORIN_AI');
    }
}
