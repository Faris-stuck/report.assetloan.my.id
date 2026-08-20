<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use Database\Seeders\TarunaBangsaClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarunaBangsaClassSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_three_grades_ten_classes_for_each_listed_major(): void
    {
        $this->seed(TarunaBangsaClassSeeder::class);

        $this->assertSame(120, SchoolClass::where('academic_year', '2026/2027')->where('is_active', true)->count());

        foreach (['10', '11', '12'] as $grade) {
            foreach (['RPL', 'TKR', 'TITL', 'TAV'] as $major) {
                $this->assertSame(
                    10,
                    SchoolClass::where('grade_level', $grade)
                        ->where('major', $major)
                        ->where('academic_year', '2026/2027')
                        ->where('is_active', true)
                        ->count(),
                    "Grade {$grade} {$major} should have 10 active classes."
                );
            }
        }

        $this->assertDatabaseHas('classes', [
            'class_name' => 'Kelas 10 RPL 10',
            'grade_level' => '10',
            'major' => 'RPL',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('classes', [
            'class_name' => 'Kelas 12 TITL 10',
            'grade_level' => '12',
            'major' => 'TITL',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('classes', [
            'class_name' => 'Kelas 12 TAV 10',
            'grade_level' => '12',
            'major' => 'TAV',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }

    public function test_it_is_idempotent_and_renames_legacy_roman_class_name(): void
    {
        SchoolClass::create([
            'class_name' => 'X RPL 1',
            'grade_level' => 'X',
            'major' => 'RPL',
            'academic_year' => '2026/2027',
            'room_name' => 'R-101',
            'is_active' => true,
        ]);

        $this->seed(TarunaBangsaClassSeeder::class);
        $this->seed(TarunaBangsaClassSeeder::class);

        $this->assertSame(120, SchoolClass::where('academic_year', '2026/2027')->where('is_active', true)->count());
        $this->assertDatabaseMissing('classes', [
            'class_name' => 'X RPL 1',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('classes', [
            'class_name' => 'Kelas 10 RPL 1',
            'grade_level' => '10',
            'major' => 'RPL',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }
}
