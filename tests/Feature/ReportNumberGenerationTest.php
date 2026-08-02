<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Report;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_report_number_uses_year_month_plus_four_random_digits_not_legacy_sequence(): void
    {
        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-07-29 08:00:00', 'Asia/Jakarta'));

        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $this->withSession(['math_captcha_answer' => 7, 'report_submit_token' => Str::uuid()->toString()])
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Nomor Random',
                'reporter_phone' => '+628****7893',
                'reporter_email' => 'pelapor@example.com',
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => 'Test nomor laporan random',
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi laporan untuk menguji nomor laporan random.',
                'urgency' => 'sedang',
                'item_name' => 'Kursi audit',
                'damage_condition' => 'Kondisi audit nomor laporan random.',
                'priority' => 'sedang',
                'consent' => '1',
                'captcha' => '7',
            ])
            ->assertRedirect();

        $reportNumber = Report::query()->sole()->report_number;

        $this->assertMatchesRegularExpression('/^LPR202607\d{4}$/', $reportNumber);
        $this->assertDoesNotMatchRegularExpression('/^LPR-?2026-?07-?\d{5}$/', $reportNumber);

        Carbon::setTestNow();
    }
}
