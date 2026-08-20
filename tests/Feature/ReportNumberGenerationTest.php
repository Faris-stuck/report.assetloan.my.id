<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Report;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_report_number_uses_secure_random_lap_format(): void
    {
        $this->seed();

        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();
        $submitToken = (string) Str::uuid();

        $this->withSession([
            'math_captcha_answer' => 7,
            'report_submit_token' => $submitToken,
        ])->post(route('public.report.store'), [
            'report_submit_token' => $submitToken,
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Nomor Random',
            'reporter_phone' => '+6281234567893',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Test nomor laporan random',
            'location_id' => $location->id,
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan untuk menguji nomor laporan acak.',
            'urgency' => 'sedang',
            'item_name' => 'Kursi Audit',
            'damage_condition' => 'Kondisi audit nomor laporan.',
            'consent' => '1',
            'captcha' => '7',
        ])->assertRedirect();

        $reportNumber = Report::query()
            ->sole()
            ->report_number;

        $this->assertMatchesRegularExpression(
            '/^LAP-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/',
            $reportNumber
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^LPR\d{10}$/',
            $reportNumber
        );
    }
}
