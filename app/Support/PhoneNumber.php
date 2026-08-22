<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Satu sumber kebenaran untuk nomor HP pelapor.
 *
 * Sebelumnya logikanya ada di dua tempat dengan aturan yang SALING
 * BERTENTANGAN:
 *
 *   - PublicReportRequest menerima 8-15 digit dengan prefiks apa pun.
 *   - SendReportWhatsAppNotification::normalizePhone() mewajibkan 62xxxxxxxxx
 *     (10-15 digit) dan menolak sisanya.
 *
 * Akibatnya formulir menerima nomor yang sistemnya sendiri tidak bisa
 * hubungi. Pada data produksi 21 Agustus 2026, 11 dari 27 laporan menyimpan
 * nomor yang gagal dinormalkan — 8 di antaranya tanpa email, jadi pelapornya
 * benar-benar tidak bisa dihubungi. Padahal pesan validasinya sendiri berbunyi
 * "Nomor HP wajib diisi agar sekolah dapat menghubungi pelapor."
 *
 * PRINSIPNYA: terima sebanyak mungkin bentuk yang bisa dipetakan ke nomor
 * nyata, dan tolak HANYA yang tidak menunjuk nomor apa pun. Nomor negara mana
 * saja diterima — +65, +60, +1, +44, +7, +966, dan seterusnya — baik ditulis
 * dengan "+", dengan awalan panggilan internasional "00", spasi, tanda kurung,
 * maupun tanda hubung.
 *
 * Aturannya diambil dari bentuk nyata data produksi: seluruh 10 nomor
 * asal-ketik yang lolos ke basis data TIDAK menyebut kode negara sama sekali
 * (tidak diawali +, 00, 0, 8, maupun 62), sementara SEMUA nomor yang sah
 * menyebut salah satunya. Jadi yang membedakan "nomor Singapura" dari "asal
 * ketik" bukan panjang atau prefiksnya, melainkan ada-tidaknya kode negara:
 *
 *   - Bentuk lokal Indonesia diterima tanpa kode negara (0812..., 812...,
 *     62812...), karena mayoritas pelapor adalah siswa di Indonesia.
 *   - Nomor negara lain menyebut kode negaranya (+65..., 0065...). Tanpa itu,
 *     "5551234567" tidak bisa dibedakan dari "5555555555".
 */
final class PhoneNumber
{
    /**
     * Panjang minimum sebuah nomor E.164 lengkap.
     *
     * Beberapa negara memang sesingkat ini, mis. Kepulauan Solomon (+677 dan
     * 5 digit), jadi jangan dinaikkan tanpa alasan.
     */
    private const MIN_LENGTH = 8;

    /** Batas atas E.164. */
    private const MAX_LENGTH = 15;

    /** Kode negara Indonesia. */
    private const ID_COUNTRY_CODE = '62';

    /**
     * Panjang minimum nomor Indonesia dalam bentuk 62 + nomor nasional.
     *
     * Nomor nasional Indonesia terpendek (mis. telepon rumah 021-5551234)
     * berisi 8 digit setelah kode negara. Ini murni penjagaan panjang, bukan
     * penyaring format: tidak ada nomor Indonesia sah yang lebih pendek.
     */
    private const ID_MIN_LENGTH = 10;

    /**
     * Digit yang sah tepat setelah "628" untuk nomor seluler Indonesia.
     *
     * Blok seluler Indonesia adalah 0811-0819, 0821-0823, 0831-0838,
     * 0851-0859, 0877-0878, 0881-0889, dan 0895-0899 — sehingga digit ini
     * tidak pernah 0, 4, atau 6.
     *
     * PENTING: pemeriksaan ini HANYA dipakai saat kode negara TIDAK disebut
     * dan nomornya diawali "8", yaitu satu-satunya kasus di mana sistem
     * MENDUGA nomor itu seluler Indonesia. Dugaan harus konservatif, kalau
     * tidak "8600000000" ikut lolos menjadi +628600000000. Begitu pelapor
     * menyebut kode negaranya sendiri (atau menulis nol lokal seperti
     * "021..."), nomornya diterima apa adanya — termasuk telepon rumah.
     */
    private const ID_MOBILE_DIGITS = ['1', '2', '3', '5', '7', '8', '9'];

    /**
     * Ubah masukan pengguna menjadi E.164 ("+" lalu digit), atau null bila
     * masukannya tidak menunjuk nomor apa pun.
     */
    public static function normalize(?string $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '') {
            return null;
        }

        // Kode negara disebut eksplisit lewat "+" atau awalan panggilan
        // internasional "00". Keduanya berarti digit setelahnya SUDAH memuat
        // kode negara, jadi jangan ditafsirkan sebagai nomor lokal.
        $statesCountryCode = str_starts_with($trimmed, '+') || str_starts_with($digits, '00');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (! $statesCountryCode) {
            $digits = self::fromIndonesianLocal($digits);

            if ($digits === null) {
                return null;
            }
        }

        // "620812..." muncul saat orang menggabungkan +62 dengan nomor lokal
        // yang masih membawa nol. Rapikan sekali.
        if (str_starts_with($digits, self::ID_COUNTRY_CODE.'0')) {
            $digits = self::ID_COUNTRY_CODE.substr($digits, 3);
        }

        // Tidak ada kode negara yang diawali nol, jadi sisa nol di depan
        // menandakan masukan yang rusak, mis. "+0812...".
        if (str_starts_with($digits, '0')) {
            return null;
        }

        $length = strlen($digits);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return null;
        }

        // Satu-satunya batas khusus negara: panjang minimum nomor Indonesia.
        // Tanpa ini "0812345" menjadi "62812345" (8 digit) dan lolos ambang
        // internasional, padahal bukan nomor Indonesia yang sah.
        if (str_starts_with($digits, self::ID_COUNTRY_CODE) && $length < self::ID_MIN_LENGTH) {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * Bentuk yang diminta WAHA: digit saja, tanpa "+".
     */
    public static function toWhatsAppNumber(?string $raw): ?string
    {
        $normalized = self::normalize($raw);

        return $normalized === null ? null : substr($normalized, 1);
    }

    /**
     * Apakah masukan ini menunjuk nomor yang bisa dihubungi?
     */
    public static function isReachable(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }

    /**
     * Tafsirkan deretan digit tanpa kode negara sebagai nomor lokal Indonesia.
     *
     * Mengembalikan null bila bentuknya tidak dikenali — inilah yang menolak
     * asal-ketik seperti "33333333" atau "9612345678" sekaligus tetap
     * mengizinkan nomor negara mana pun yang menyebut kode negaranya.
     */
    private static function fromIndonesianLocal(string $digits): ?string
    {
        // "0812..." -> "62812...", juga "021..." -> "6221..." (telepon rumah).
        if (str_starts_with($digits, '0')) {
            return self::ID_COUNTRY_CODE.substr($digits, 1);
        }

        // Sudah membawa kode negara Indonesia tanpa "+".
        if (str_starts_with($digits, self::ID_COUNTRY_CODE)) {
            return $digits;
        }

        // "812..." tanpa awalan apa pun. Ini satu-satunya DUGAAN yang dibuat
        // sistem, jadi digit operatornya diperiksa supaya asal-ketik seperti
        // "8600000000" tidak ikut lolos.
        if (str_starts_with($digits, '8')) {
            return in_array($digits[1] ?? '', self::ID_MOBILE_DIGITS, true)
                ? self::ID_COUNTRY_CODE.$digits
                : null;
        }

        return null;
    }
}
