<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus fitur lokasi secara menyeluruh.
 *
 * Tempat kejadian kini ditulis pelapor di dalam kronologi, jadi tabel
 * `locations` beserta seluruh kolom turunannya tidak dipakai lagi. Sebelum
 * kolom dibuang, nama lokasi pada laporan lama diarsipkan ke dalam deskripsi
 * supaya tidak ada informasi laporan nyata yang hilang.
 *
 * Setiap langkah dijaga hasTable/hasColumn supaya migrasi ini aman dijalankan
 * pada database baru (mis. SQLite saat pengujian) yang memang tidak pernah
 * punya kolom-kolom tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->archiveIncidentPlaceIntoDescription();

        if (Schema::hasColumn('reports', 'location_id')) {
            Schema::table('reports', function (Blueprint $table): void {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        if (Schema::hasColumn('reports', 'custom_location')) {
            Schema::table('reports', function (Blueprint $table): void {
                $table->dropColumn('custom_location');
            });
        }

        if (Schema::hasColumn('qr_codes', 'location_id')) {
            Schema::table('qr_codes', function (Blueprint $table): void {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        $this->shrinkQrTypeEnum();

        Schema::dropIfExists('locations');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migrasi penghapusan fitur lokasi tidak dapat dibalik: tabel locations '
            . 'beserta relasinya sudah dibuang dan nilai lamanya hanya tersimpan '
            . 'sebagai teks arsip pada kolom description. Pulihkan dari backup database.'
        );
    }

    /**
     * Simpan keterangan tempat kejadian lama ke dalam deskripsi laporan supaya
     * informasinya tidak hilang saat kolomnya dibuang.
     */
    private function archiveIncidentPlaceIntoDescription(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        if (Schema::hasTable('locations') && Schema::hasColumn('reports', 'location_id')) {
            $rows = DB::table('reports')
                ->join('locations', 'locations.id', '=', 'reports.location_id')
                ->whereNotNull('reports.location_id')
                ->select('reports.id', 'reports.description', 'locations.location_name')
                ->get();

            foreach ($rows as $row) {
                $this->appendPlace((int) $row->id, (string) $row->description, (string) $row->location_name);
            }
        }

        if (Schema::hasColumn('reports', 'custom_location')) {
            $rows = DB::table('reports')
                ->whereNotNull('custom_location')
                ->select('id', 'description', 'custom_location')
                ->get();

            foreach ($rows as $row) {
                $this->appendPlace((int) $row->id, (string) $row->description, (string) $row->custom_location);
            }
        }
    }

    private function appendPlace(int $reportId, string $description, string $place): void
    {
        $place = trim($place);

        if ($place === '' || str_contains($description, $place)) {
            return;
        }

        DB::table('reports')->where('id', $reportId)->update([
            'description' => rtrim($description) . PHP_EOL . PHP_EOL . 'Tempat kejadian (arsip): ' . $place,
        ]);
    }

    /**
     * Nilai enum 'location' pada qr_codes tidak pernah ditulis kode aplikasi,
     * jadi pilihannya dipangkas agar skema tidak menyimpan opsi mati.
     */
    private function shrinkQrTypeEnum(): void
    {
        if (! Schema::hasTable('qr_codes') || ! Schema::hasColumn('qr_codes', 'qr_type')) {
            return;
        }

        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('qr_codes')->where('qr_type', 'location')->update(['qr_type' => 'general']);

        DB::statement("ALTER TABLE `qr_codes` MODIFY `qr_type` ENUM('general', 'class') NOT NULL DEFAULT 'general'");
    }
};
