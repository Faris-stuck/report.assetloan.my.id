<?php

namespace App\Services\AI;

use FFI;
use RuntimeException;

final class EmbeddedLlm
{
    private const BUFFER_SIZE = 8192;

    private static ?FFI $ffi = null;

    public static function generate(string $prompt): ?string
    {
        $ffi = self::ffi();
        $buffer = $ffi->new('char['.self::BUFFER_SIZE.']', false);
        $buffer[0] = 0;

        $result = $ffi->laporin_ai_generate($prompt, $buffer, self::BUFFER_SIZE);
        if ($result !== 0) {
            return null;
        }

        $output = FFI::string($buffer);
        return $output !== '' ? trim($output) : null;
    }

    private static function ffi(): FFI
    {
        if (! extension_loaded('FFI')) {
            throw new RuntimeException('Embedded AI runtime is unavailable.');
        }

        return self::$ffi ??= FFI::scope('LAPORIN_AI');
    }
}
