<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Formulir publik dulu menerima 8-15 digit dengan prefiks apa pun, sementara
 * pengirim WhatsApp mewajibkan 62xxxxxxxxx. Dua aturan itu bertentangan dan
 * hasilnya 11 dari 27 laporan produksi menyimpan nomor yang tidak bisa
 * dihubungi. Kelas ini menjadi satu-satunya penentu, jadi perilakunya dipatok
 * di sini.
 *
 * Yang diuji: nomor negara MANA SAJA diterima dalam bentuk penulisan apa pun,
 * dan yang ditolak hanya masukan yang tidak menunjuk nomor apa pun.
 */
class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bentukIndonesiaYangDiterima(): array
    {
        return [
            'nol di depan' => ['081234567890', '+6281234567890'],
            'plus enam dua' => ['+6281234567890', '+6281234567890'],
            'enam dua tanpa plus' => ['6281234567890', '+6281234567890'],
            'spasi dan tanda kurung' => ['(0812) 3456-7890', '+6281234567890'],
            'spasi di ujung' => ['  081234567890  ', '+6281234567890'],
            'tanpa nol di depan' => ['81234567890', '+6281234567890'],
            'nol setelah kode negara' => ['+62081234567890', '+6281234567890'],
            'panggilan internasional' => ['006281234567890', '+6281234567890'],
            'titik sebagai pemisah' => ['0812.3456.7890', '+6281234567890'],
            // Telepon rumah juga nomor sah. Pelapor boleh mencantumkannya;
            // WhatsApp-nya nanti gagal terkirim, tapi nomornya tetap berguna
            // sebagai kontak dan tidak boleh ditolak formulir.
            'telepon rumah jakarta' => ['021 555 1234', '+62215551234'],
            'telepon rumah bandung' => ['(022) 7654321', '+62227654321'],
        ];
    }

    #[DataProvider('bentukIndonesiaYangDiterima')]
    public function test_bentuk_lokal_indonesia_dinormalkan(string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, PhoneNumber::normalize($masukan));
    }

    /**
     * Nomor luar negeri wajib diterima, dari negara mana pun: ada pelapor yang
     * memakai nomor asing, dan memblokirnya berarti menutup jalur laporan.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bentukLuarNegeriYangDiterima(): array
    {
        return [
            'singapura plus' => ['+65 8123 4567', '+6581234567'],
            'singapura nol nol' => ['0065 8123 4567', '+6581234567'],
            'malaysia' => ['+60 12-345 6789', '+60123456789'],
            'amerika serikat' => ['+1 (415) 555-2671', '+14155552671'],
            'inggris' => ['+44 7911 123456', '+447911123456'],
            'india' => ['+91 98765 43210', '+919876543210'],
            'australia' => ['+61 412 345 678', '+61412345678'],
            'arab saudi' => ['+966 50 123 4567', '+966501234567'],
            'belanda' => ['+31 6 12345678', '+31612345678'],
            'jepang' => ['+81 90 1234 5678', '+819012345678'],
            'timor leste' => ['+670 7723 4567', '+67077234567'],
            'rusia kazakhstan kode 7' => ['+7285781633026', '+7285781633026'],
            'rusia dengan spasi' => ['+7 285 781 63302', '+728578163302'],
            'tiongkok' => ['+86 138 0013 8000', '+8613800138000'],
            'brasil' => ['+55 11 91234 5678', '+5511912345678'],
            'jerman' => ['+49 151 12345678', '+4915112345678'],
            'e164 terpendek yang lazim' => ['+677 12345', '+67712345'],
            'batas atas lima belas digit' => ['+123456789012345', '+123456789012345'],
        ];
    }

    #[DataProvider('bentukLuarNegeriYangDiterima')]
    public function test_nomor_luar_negeri_diterima(string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, PhoneNumber::normalize($masukan));
        $this->assertTrue(PhoneNumber::isReachable($masukan));
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function bentukYangDitolak(): array
    {
        return [
            'kosong' => [''],
            'hanya spasi' => ['   '],
            'null' => [null],
            'bukan angka' => ['bukan-nomor'],

            // Inilah yang lolos ke produksi: deretan digit tanpa kode negara
            // dan tanpa awalan lokal Indonesia. Semua 10 nomor asal-ketik di
            // basis data berbentuk seperti ini.
            'asal ketik digit sama' => ['33333333'],
            'asal ketik sembilan digit' => ['352222222'],
            'asal ketik prefiks 96' => ['9612345678'],
            'asal ketik prefiks 55' => ['5555555555'],
            'asal ketik prefiks 44' => ['444444444'],

            // Diawali "8" tapi bukan blok seluler Indonesia; ini satu-satunya
            // tempat sistem menduga, jadi dugaannya dijaga ketat.
            'blok bukan seluler' => ['8600000000'],
            'delapan nol' => ['8000000000'],

            'indonesia terlalu pendek' => ['0812345'],
            'terlalu panjang' => ['+62812345678901234567'],
            'nol setelah plus' => ['+0812345678'],
        ];
    }

    #[DataProvider('bentukYangDitolak')]
    public function test_bentuk_yang_tidak_menunjuk_nomor_ditolak(?string $masukan): void
    {
        $this->assertNull(PhoneNumber::normalize($masukan));
        $this->assertFalse(PhoneNumber::isReachable($masukan));
    }

    /**
     * Nomor asing TANPA kode negara ditolak, dan itu disengaja:
     * "5551234567" tidak bisa dibedakan dari asal-ketik "5555555555". Yang
     * penting, nomor yang sama diterima begitu kode negaranya disebut — jadi
     * tidak ada pelapor yang benar-benar terkunci.
     */
    public function test_nomor_asing_diterima_setelah_kode_negara_disebut(): void
    {
        $this->assertNull(PhoneNumber::normalize('5551234567'));
        $this->assertSame('+15551234567', PhoneNumber::normalize('+1 555 123 4567'));
        $this->assertSame('+15551234567', PhoneNumber::normalize('0015551234567'));
    }

    public function test_format_waha_tanpa_tanda_plus(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::toWhatsAppNumber('081234567890'));
        $this->assertSame('6581234567', PhoneNumber::toWhatsAppNumber('+65 8123 4567'));
        $this->assertSame('7285781633026', PhoneNumber::toWhatsAppNumber('+7285781633026'));
        $this->assertNull(PhoneNumber::toWhatsAppNumber('33333333'));
    }

    public function test_hasil_normalisasi_hanya_plus_dan_digit(): void
    {
        foreach (['+62 (812) 3456-7890', '+65 8123 4567', '+7 285 781 63302'] as $masukan) {
            $hasil = PhoneNumber::normalize($masukan);

            $this->assertNotNull($hasil, $masukan);
            $this->assertMatchesRegularExpression('/^\+\d+$/', $hasil, $masukan);
        }
    }

    public function test_normalisasi_bersifat_idempoten(): void
    {
        foreach (['081234567890', '+65 8123 4567', '+1 415 555 2671', '+7285781633026', '021 555 1234'] as $masukan) {
            $sekali = PhoneNumber::normalize($masukan);
            $this->assertNotNull($sekali, $masukan);
            $this->assertSame($sekali, PhoneNumber::normalize($sekali), $masukan);
        }
    }
}
