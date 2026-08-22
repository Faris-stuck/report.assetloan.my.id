<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * Normalisasi parameter filter daftar (pencarian dan rentang tanggal).
 *
 * Nilai query string tidak selalu berupa string: `?search[]=a` membuat
 * request('search') mengembalikan array, dan interpolasinya ke dalam pola LIKE
 * memicu "Array to string conversion" lalu mencari teks "Array". Tanggal yang
 * formatnya salah juga berbahaya karena whereDate() tetap dijalankan dan
 * mengembalikan nol baris, sehingga daftar terlihat kosong tanpa penjelasan.
 */
class RequestFilters
{
    /**
     * Kembalikan kata pencarian yang aman dipakai di pola LIKE, atau string
     * kosong bila tidak ada input yang bisa dipakai.
     */
    public static function searchTerm(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Kembalikan tanggal Y-m-d yang valid, atau null bila input tidak berbentuk
     * tanggal. Input <input type="date"> selalu lolos; nilai lain diabaikan
     * daripada menghasilkan daftar kosong yang menyesatkan.
     */
    public static function isoDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        // Tanda "!" mereset komponen jam sehingga tanggal seperti "2026-02-31"
        // yang digulung PHP menjadi bulan berikutnya tidak dianggap valid.
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
