<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_superadmin_can_open_report_detail(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
        ]);
        $report = $this->report();

        $this->actingAs($user)
            ->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Detail laporan')
            ->assertSee($report->report_number);
    }

    private function report(array $overrides = []): Report
    {
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        return Report::create(array_merge([
            'report_number' => 'LPR'.now()->format('Ym').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Detail Test',
            'reporter_class_id' => $class->id,
            'report_type' => 'violation',
            'title' => 'Detail laporan test',
            'location_id' => $location->id,
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan untuk regression test halaman detail.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
        ], $overrides));
    }
}
