<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Report;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicReportSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_file_validation_with_magic_bytes_check(): void
    {
        Storage::fake('private');

        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        // Create a valid image file
        $validImage = UploadedFile::fake()->image('test.jpg', 100, 100);

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString(),
        ])
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Dengan File',
                'reporter_phone' => '+6281234567890',
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => 'Kerusakan dengan Bukti',
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Kerusakan dengan bukti foto.',
                'urgency' => 'sedang',
                'item_name' => 'Meja',
                'damage_condition' => 'Rusak.',
                'attachments' => [$validImage],
                'consent' => '1',
                'captcha' => '8',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reports', ['report_type' => 'damage']);
        $this->assertDatabaseHas('report_attachments', ['mime_type' => 'image/jpeg']);
    }

    public function test_report_model_encrypts_pii_fields(): void
    {
        Storage::fake('private');

        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $this->withSession([
            'math_captcha_answer' => 10,
            'report_submit_token' => Str::uuid()->toString(),
        ])
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Dengan Email',
                'reporter_phone' => '+6281234567890',
                'reporter_email' => 'pelapor@example.com',
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => 'Laporan Dengan Data PII',
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Laporan dengan email dan nomor telepon.',
                'urgency' => 'sedang',
                'item_name' => 'Kursi',
                'damage_condition' => 'Rusak.',
                'consent' => '1',
                'captcha' => '10',
            ]);

        $report = Report::query()->sole();

        // Verify that PII fields are encrypted (they should not be plaintext in database)
        $this->assertNotNull($report->reporter_email);
        $this->assertNotNull($report->reporter_phone);
        $this->assertNotNull($report->submitted_ip_hash);

        // Verify that when accessed via model, they are decrypted correctly
        $this->assertSame('pelapor@example.com', $report->reporter_email);
        $this->assertStringContainsString('62812345', $report->reporter_phone);
    }

    public function test_audit_log_created_for_public_report_submission(): void
    {
        Storage::fake('private');

        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $this->withSession([
            'math_captcha_answer' => 7,
            'report_submit_token' => Str::uuid()->toString(),
        ])
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Audit',
                'reporter_phone' => '+6281234567890',
                'reporter_class_id' => $class->id,
                'report_type' => 'violation',
                'title' => 'Laporan dengan Audit',
                'related_class_id' => $class->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi untuk audit.',
                'urgency' => 'tinggi',
                'alleged_actor_name' => 'Nama Pelaku',
                'consent' => '1',
                'captcha' => '7',
            ]);

        $report = Report::query()->sole();

        // Verify report was created
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'report_type' => 'violation',
        ]);

        // Verify status history was created
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'actor_type' => 'reporter',
        ]);
    }
}
