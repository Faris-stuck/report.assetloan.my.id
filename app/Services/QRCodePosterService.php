<?php

namespace App\Services;

use App\Models\QrCode;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QR;

class QRCodePosterService
{
    public function generate(QrCode $qrCode): string
    {
        $qrSvg = QR::format('svg')
            ->size(420)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($qrCode->target_url);

        $qrSvg = $this->cleanSvg($qrSvg);

        $logo = $this->imageData(
            public_path('images/branding/logo tb.png')
        );

        $building = $this->imageData(
            public_path('images/branding/background sekolah.png')
        );

        $qrName = e($qrCode->qr_name);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg"
     width="1122"
     height="1402"
     viewBox="0 0 1122 1402">

    <defs>

        <linearGradient id="greenDark"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1">
            <stop offset="0%" stop-color="#003b20"/>
            <stop offset="100%" stop-color="#006b3c"/>
        </linearGradient>

        <linearGradient id="greenPanel"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1">
            <stop offset="0%" stop-color="#004526"/>
            <stop offset="100%" stop-color="#007142"/>
        </linearGradient>

        <linearGradient id="gold"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="0">
            <stop offset="0%" stop-color="#c99100"/>
            <stop offset="50%" stop-color="#ffd34d"/>
            <stop offset="100%" stop-color="#b77f00"/>
        </linearGradient>

        <linearGradient id="fadeWhite"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1">
            <stop offset="0%" stop-color="#ffffff" stop-opacity=".05"/>
            <stop offset="60%" stop-color="#ffffff" stop-opacity=".65"/>
            <stop offset="100%" stop-color="#ffffff" stop-opacity="1"/>
        </linearGradient>

        <filter id="shadow">
            <feDropShadow dx="0"
                          dy="7"
                          stdDeviation="7"
                          flood-opacity=".25"/>
        </filter>

        <filter id="smallShadow">
            <feDropShadow dx="0"
                          dy="3"
                          stdDeviation="3"
                          flood-opacity=".20"/>
        </filter>

    </defs>

    <!-- ===================================================== -->
    <!-- BACKGROUND -->
    <!-- ===================================================== -->

    <rect width="1122"
          height="1402"
          fill="#f7f8f6"/>

    <image href="{$building}"
           x="0"
           y="0"
           width="1122"
           height="390"
           preserveAspectRatio="xMidYMid slice"
           opacity=".92"/>

    <rect x="0"
          y="0"
          width="1122"
          height="490"
          fill="url(#fadeWhite)"/>

    <!-- Top decorative curves -->

    <path d="M0 0 H420 C250 55 110 140 0 310 Z"
          fill="#003d22"/>

    <path d="M0 0 H475 C270 65 120 155 0 340"
          fill="none"
          stroke="#e2aa00"
          stroke-width="16"/>

    <path d="M0 0 H445 C250 61 115 145 0 323"
          fill="none"
          stroke="#ffffff"
          stroke-width="8"/>

    <!-- ===================================================== -->
    <!-- LOGO SEKOLAH -->
    <!-- ===================================================== -->

    <circle cx="561"
            cy="145"
            r="120"
            fill="#ffffff"
            opacity=".92"
            filter="url(#smallShadow)"/>

    <image href="{$logo}"
           x="451"
           y="35"
           width="220"
           height="220"
           preserveAspectRatio="xMidYMid meet"/>

    <!-- ===================================================== -->
    <!-- TITLE -->
    <!-- ===================================================== -->

    <text x="561"
          y="470"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="150"
          font-weight="900"
          letter-spacing="-5"
          fill="url(#greenDark)"
          stroke="#ffffff"
          stroke-width="7"
          filter="url(#shadow)">
        LAPORIN
    </text>

    <line x1="95"
          y1="530"
          x2="150"
          y2="530"
          stroke="#e4aa00"
          stroke-width="8"
          stroke-linecap="round"/>

    <line x1="972"
          y1="530"
          x2="1027"
          y2="530"
          stroke="#e4aa00"
          stroke-width="8"
          stroke-linecap="round"/>

    <text x="561"
          y="548"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="42"
          font-weight="900"
          fill="#004327">
        SMK TARUNA BANGSA KOTA BEKASI
    </text>

    <!-- ===================================================== -->
    <!-- CTA -->
    <!-- ===================================================== -->

    <path d="
        M182 585
        H940
        L972 617
        L940 649
        H182
        L150 617
        Z"
        fill="url(#greenDark)"
        stroke="#d9a400"
        stroke-width="4"
        filter="url(#smallShadow)"/>

    <text x="561"
          y="632"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="39"
          font-weight="900">

        <tspan fill="#ffffff">
            SCAN QR CODE
        </tspan>

        <tspan fill="#ffc72b">
            UNTUK MELAPOR
        </tspan>

    </text>

    <!-- ===================================================== -->
    <!-- FEATURE CARDS -->
    <!-- ===================================================== -->

    {$this->featureCard(
        60,
        690,
        'Perundungan',
        'Laporkan tindakan',
        'perundungan dengan',
        'aman dan rahasia.',
        'shield'
    )}

    {$this->featureCard(
        60,
        840,
        'Pelanggaran',
        'Laporkan pelanggaran',
        'aturan sekolah untuk',
        'lingkungan yang disiplin.',
        'gavel'
    )}

    {$this->featureCard(
        60,
        990,
        'Kerusakan Fasilitas',
        'Laporkan kerusakan',
        'fasilitas sekolah agar',
        'segera diperbaiki.',
        'tools'
    )}

    <!-- ===================================================== -->
    <!-- QR INTRO -->
    <!-- ===================================================== -->

    <text x="720"
          y="696"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="22"
          font-weight="600"
          fill="#1a1a1a">

        <tspan x="720" dy="0">
            Laporkan perundungan, pelanggaran,
        </tspan>

        <tspan x="720" dy="28">
            atau kerusakan fasilitas dengan cepat dan mudah.
        </tspan>

    </text>

    <!-- ===================================================== -->
    <!-- QR FRAME -->
    <!-- ===================================================== -->

    <rect x="405"
          y="748"
          width="625"
          height="410"
          rx="34"
          fill="#002d1b"
          stroke="#121212"
          stroke-width="7"
          filter="url(#shadow)"/>

    <rect x="424"
          y="767"
          width="587"
          height="372"
          rx="27"
          fill="url(#greenPanel)"
          stroke="#dca700"
          stroke-width="4"/>

    <!-- decorative side rails -->

    <path d="
        M424 830
        L451 800
        V1105
        L424 1075
        Z"
        fill="#00351f"
        stroke="#e2ab00"
        stroke-width="3"/>

    <path d="
        M1011 830
        L984 800
        V1105
        L1011 1075
        Z"
        fill="#00351f"
        stroke="#e2ab00"
        stroke-width="3"/>

    <circle cx="441"
            cy="919"
            r="4"
            fill="#ffc928"/>

    <circle cx="441"
            cy="935"
            r="4"
            fill="#ffc928"/>

    <circle cx="441"
            cy="951"
            r="4"
            fill="#ffc928"/>

    <circle cx="994"
            cy="919"
            r="4"
            fill="#ffc928"/>

    <circle cx="994"
            cy="935"
            r="4"
            fill="#ffc928"/>

    <circle cx="994"
            cy="951"
            r="4"
            fill="#ffc928"/>

    <!-- white QR plate -->

    <rect x="472"
          y="785"
          width="490"
          height="342"
          rx="22"
          fill="#ffffff"
          stroke="#e2ab00"
          stroke-width="3"/>

    <!-- ===================================================== -->
    <!-- REAL QR CODE -->
    <!-- ===================================================== -->

    <svg x="515"
         y="792"
         width="405"
         height="320"
         viewBox="0 0 420 420"
         preserveAspectRatio="xMidYMid meet">

        {$qrSvg}

    </svg>

    <!-- ===================================================== -->
    <!-- QR NAME -->
    <!-- ===================================================== -->

    <text x="718"
          y="1144"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="18"
          font-weight="700"
          fill="#ffffff">
        {$qrName}
    </text>

    <!-- ===================================================== -->
    <!-- FOOTER -->
    <!-- ===================================================== -->

    <path d="
        M0 1188
        L95 1155
        H1027
        L1122 1188
        V1402
        H0
        Z"
        fill="#004c2d"
        stroke="#d9a500"
        stroke-width="4"/>

    <path d="
        M72 1213
        H1050
        L1070 1234
        V1365
        L1050 1385
        H72
        L52 1365
        V1234
        Z"
        fill="#ffffff"
        stroke="#d8a000"
        stroke-width="3"
        filter="url(#shadow)"/>

    <line x1="128"
          y1="1260"
          x2="205"
          y2="1260"
          stroke="#dca600"
          stroke-width="3"/>

    <line x1="917"
          y1="1260"
          x2="994"
          y2="1260"
          stroke="#dca600"
          stroke-width="3"/>

    <text x="561"
          y="1287"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="43"
          font-weight="800"
          fill="#004228">
        BERSAMA JAGA
    </text>

    <text x="561"
          y="1355"
          text-anchor="middle"
          font-family="Arial, Helvetica, sans-serif"
          font-size="58"
          font-weight="900"
          fill="#003c23">
        SEKOLAH AMAN DAN NYAMAN
    </text>

</svg>
SVG;
    }

    private function imageData(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException(
                "Asset poster tidak ditemukan: {$path}"
            );
        }

        $mime = mime_content_type($path);

        if ($mime === false) {
            throw new RuntimeException(
                "Tidak dapat mendeteksi MIME asset: {$path}"
            );
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(
                "Tidak dapat membaca asset: {$path}"
            );
        }

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }

    private function cleanSvg(string $svg): string
    {
        $svg = preg_replace(
            '/<\?xml.*?\?>/s',
            '',
            $svg
        );

        $svg = preg_replace(
            '/<!DOCTYPE.*?>/s',
            '',
            $svg
        );

        $svg = preg_replace(
            '/<svg[^>]*>/',
            '',
            $svg,
            1
        );

        $svg = preg_replace(
            '/<\/svg>\s*$/',
            '',
            $svg
        );

        return trim((string) $svg);
    }

    private function featureCard(
        int $x,
        int $y,
        string $title,
        string $line1,
        string $line2,
        string $line3,
        string $icon
    ): string {
        $iconSvg = match ($icon) {
            'shield' => '
                <path d="
                    M0 -30
                    L25 -20
                    V0
                    C25 21 11 34 0 41
                    C-11 34 -25 21 -25 0
                    V-20 Z"
                    fill="none"
                    stroke="#00613a"
                    stroke-width="5"/>

                <circle cx="0"
                        cy="-7"
                        r="9"
                        fill="none"
                        stroke="#00613a"
                        stroke-width="4"/>

                <path d="
                    M-14 20
                    C-9 5 9 5 14 20"
                    fill="none"
                    stroke="#00613a"
                    stroke-width="4"/>
            ',

            'gavel' => '
                <g fill="none"
                   stroke="#00613a"
                   stroke-width="6"
                   stroke-linecap="round">

                    <path d="M-20 -20 L5 5"/>
                    <path d="M-30 -10 L-12 -28"/>
                    <path d="M-6 14 L12 -4"/>
                    <path d="M4 6 L28 30"/>
                    <path d="M-28 31 H25"/>

                </g>
            ',

            default => '
                <g fill="none"
                   stroke="#00613a"
                   stroke-width="5"
                   stroke-linecap="round">

                    <circle r="25"/>
                    <path d="M-25 25 L25 -25"/>
                    <path d="M-31 13 L-13 31"/>
                    <path d="M13 -31 L31 -13"/>

                </g>
            ',
        };

        $safeTitle = e($title);
        $safe1 = e($line1);
        $safe2 = e($line2);
        $safe3 = e($line3);

        return <<<SVG

        <g transform="translate({$x},{$y})">

            <path d="
                M0 0
                H290
                L312 22
                V118
                L290 140
                H0
                Z"
                fill="url(#greenPanel)"
                stroke="#d9a300"
                stroke-width="3"
                filter="url(#smallShadow)"/>

            <circle cx="62"
                    cy="70"
                    r="47"
                    fill="#ffffff"
                    stroke="#dda500"
                    stroke-width="4"/>

            <g transform="translate(62,70)">
                {$iconSvg}
            </g>

            <text x="120"
                  y="45"
                  font-family="Arial, Helvetica, sans-serif"
                  font-size="20"
                  font-weight="800"
                  fill="#ffffff">
                {$safeTitle}
            </text>

            <text x="120"
                  y="72"
                  font-family="Arial, Helvetica, sans-serif"
                  font-size="15"
                  fill="#ffffff">

                <tspan x="120" dy="0">
                    {$safe1}
                </tspan>

                <tspan x="120" dy="20">
                    {$safe2}
                </tspan>

                <tspan x="120" dy="20">
                    {$safe3}
                </tspan>

            </text>

        </g>

        SVG;
    }
}