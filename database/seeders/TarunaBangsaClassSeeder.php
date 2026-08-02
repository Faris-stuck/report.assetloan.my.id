<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class TarunaBangsaClassSeeder extends Seeder
{
    private const ACADEMIC_YEAR = '2026/2027';

    /**
     * Jurusan utama SMK Taruna Bangsa Bekasi.
     * Deskripsi ringkas:
     * - TKR: mesin mobil, perawatan, dan teknologi otomotif.
     * - TITL: perencanaan dan pemasangan instalasi listrik rumah/gedung.
     * - TAV: elektronika, audio video, dan robotika.
     * - RPL: aplikasi, situs web, koding, dan dasar jaringan komputer.
     */
    private const MAJORS = [
        'RPL' => 'Rekayasa Perangkat Lunak',
        'TKR' => 'Teknik Kendaraan Ringan',
        'TITL' => 'Teknik Instalasi Tenaga Listrik',
        'TAV' => 'Teknik Elektronika Audio Video',
    ];

    private const GRADES = [
        'X' => '10',
        'XI' => '11',
        'XII' => '12',
    ];

    public function run(): void
    {
        foreach (self::GRADES as $romanGrade => $numberGrade) {
            foreach (array_keys(self::MAJORS) as $majorCode) {
                for ($classNumber = 1; $classNumber <= 10; $classNumber++) {
                    $this->upsertClass($romanGrade, $numberGrade, $majorCode, $classNumber);
                }
            }
        }
    }

    private function upsertClass(string $romanGrade, string $numberGrade, string $majorCode, int $classNumber): void
    {
        $className = "Kelas {$numberGrade} {$majorCode} {$classNumber}";
        $legacyName = "{$romanGrade} {$majorCode} {$classNumber}";
        $roomName = "Ruang {$numberGrade} {$majorCode} {$classNumber}";

        $modern = SchoolClass::where('class_name', $className)
            ->where('academic_year', self::ACADEMIC_YEAR)
            ->first();

        $legacy = SchoolClass::where('class_name', $legacyName)
            ->where('academic_year', self::ACADEMIC_YEAR)
            ->first();

        if (! $modern && $legacy) {
            $legacy->update([
                'class_name' => $className,
                'grade_level' => $numberGrade,
                'major' => $majorCode,
                'room_name' => $roomName,
                'is_active' => true,
            ]);

            return;
        }

        if ($modern) {
            $modern->update([
                'grade_level' => $numberGrade,
                'major' => $majorCode,
                'room_name' => $roomName,
                'is_active' => true,
            ]);

            if ($legacy && $legacy->id !== $modern->id) {
                $legacy->update(['is_active' => false]);
            }

            return;
        }

        SchoolClass::create([
            'class_name' => $className,
            'grade_level' => $numberGrade,
            'major' => $majorCode,
            'academic_year' => self::ACADEMIC_YEAR,
            'room_name' => $roomName,
            'is_active' => true,
        ]);
    }
}
