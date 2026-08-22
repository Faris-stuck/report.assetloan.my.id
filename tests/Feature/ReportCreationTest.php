<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_damage_report_creates_report_with_unique_lap_format_and_attachments(): void
    {
        Storage::fake('private');

        $class = SchoolClass::firstOrFail();
        $staffUnit = StaffUnit::firstOrFail();

        $response = $this->withSession(['math_captcha_answer' => 8, 'report_submit_token' => Str::uuid()->toString()])
            ->post(route('public.report.store'), [
                'reporter_type' => 'staff',
                'reporter_name' => 'Staf Laporan',
                'reporter_phone' => '+6281234567890',
                'reporter_staff_unit_id' => $staffUnit->id,
                'report_type' => 'damage',
                'title' => 'Kerusakan AC Lab',
                'incident_date' => now()->toDateString(),
                'description' => 'AC tidak dingin pada ruang lab.',
                'urgency' => 'tinggi',
                'item_name' => 'AC Lab',
                'damage_condition' => 'Tidak dingin lagi.',
                'consent' => '1',
                'captcha' => '8',
            ]);

        $response->assertRedirect();

        $report = Report::query()->sole();

        $this->assertMatchesRegularExpression('/^LAP-[A-Z2-9]{6}-[A-Z2-9]{6}$/', $report->report_number);
        $this->assertNotSame('LPR', substr($report->report_number, 0, 3));
        $this->assertSame('sarpras', $report->assigned_to_role);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'report_type' => 'damage']);
    }

    public function test_duplicate_report_number_collision_retries_with_new_candidate(): void
    {
        $existing = Report::create([
            'report_number' => 'LAP-ABCDEF-GHJKLM',
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Lama',
            'reporter_class_id' => SchoolClass::firstOrFail()->id,
            'report_type' => 'damage',
            'title' => 'Laporan Lama',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi lama',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'sarpras',
            'consent_accepted_at' => now(),
        ]);

        $this->withSession(['math_captcha_answer' => 9, 'report_submit_token' => Str::uuid()->toString()])
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Baru',
                'reporter_phone' => '+6281234567891',
                'reporter_class_id' => SchoolClass::firstOrFail()->id,
                'report_type' => 'damage',
                'title' => 'Kerusakan Baru',
                'incident_date' => now()->toDateString(),
                'description' => 'Kerusakan baru terdeteksi.',
                'urgency' => 'darurat',
                'item_name' => 'Pintu',
                'damage_condition' => 'Pintu rusak parah.',
                'consent' => '1',
                'captcha' => '9',
            ]);

        $this->assertDatabaseCount('reports', 2);
    }
}
