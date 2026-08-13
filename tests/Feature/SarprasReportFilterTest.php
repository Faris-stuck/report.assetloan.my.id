<?php

namespace Tests\Feature;

use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SarprasReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $sarprasUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sarprasUser = User::factory()->create([
            'role' => 'sarpras',
            'is_active' => true,
        ]);
    }

    public function test_sarpras_index_displays_all_damage_reports()
    {
        Report::factory()
            ->count(3)
            ->create(['report_type' => 'damage'])
            ->each(function ($report) {
                DamageDetail::factory()->create(['report_id' => $report->id]);
            });

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index'));

        $response->assertStatus(200)
            ->assertViewHas('reports');
    }

    public function test_sarpras_search_filters_by_report_number()
    {
        $report1 = Report::factory()->create([
            'report_type' => 'damage',
            'report_number' => 'LAPOR-001',
            'title' => 'Pintu Rusak',
        ]);
        DamageDetail::factory()->create(['report_id' => $report1->id]);

        $report2 = Report::factory()->create([
            'report_type' => 'damage',
            'report_number' => 'LAPOR-002',
            'title' => 'AC Mati',
        ]);
        DamageDetail::factory()->create(['report_id' => $report2->id]);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', ['search' => 'LAPOR-001']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
        $this->assertEquals($report1->id, $response->viewData('reports')->first()->id);
    }

    public function test_sarpras_search_filters_by_title()
    {
        $report1 = Report::factory()->create([
            'report_type' => 'damage',
            'title' => 'Jendela Pecah',
        ]);
        DamageDetail::factory()->create(['report_id' => $report1->id]);

        $report2 = Report::factory()->create([
            'report_type' => 'damage',
            'title' => 'Lantai Retak',
        ]);
        DamageDetail::factory()->create(['report_id' => $report2->id]);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', ['search' => 'Jendela']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
    }

    public function test_sarpras_search_filters_by_description()
    {
        $report1 = Report::factory()->create([
            'report_type' => 'damage',
            'description' => 'Mesin fotocopy di ruang TU rusak total',
        ]);
        DamageDetail::factory()->create(['report_id' => $report1->id]);

        $report2 = Report::factory()->create([
            'report_type' => 'damage',
            'description' => 'Pintu toilet tidak bisa ditutup',
        ]);
        DamageDetail::factory()->create(['report_id' => $report2->id]);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', ['search' => 'fotocopy']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
    }

    public function test_sarpras_status_filter_works()
    {
        $report1 = Report::factory()->create([
            'report_type' => 'damage',
            'status' => 'menunggu_verifikasi',
        ]);
        DamageDetail::factory()->create(['report_id' => $report1->id]);

        $report2 = Report::factory()->create([
            'report_type' => 'damage',
            'status' => 'selesai',
        ]);
        DamageDetail::factory()->create(['report_id' => $report2->id]);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', ['status' => 'menunggu_verifikasi']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
        $this->assertEquals('menunggu_verifikasi', $response->viewData('reports')->first()->status);
    }

    public function test_sarpras_priority_filter_works()
    {
        $report1 = Report::factory()->create(['report_type' => 'damage']);
        DamageDetail::factory()->create([
            'report_id' => $report1->id,
            'priority' => 'darurat',
        ]);

        $report2 = Report::factory()->create(['report_type' => 'damage']);
        DamageDetail::factory()->create([
            'report_id' => $report2->id,
            'priority' => 'rendah',
        ]);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', ['priority' => 'darurat']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
        $this->assertEquals('darurat', $response->viewData('reports')->first()->damageDetail->priority);
    }

    public function test_sarpras_date_range_filter_works()
    {
        $today = Carbon::now();

        $report1 = Report::factory()->create([
            'report_type' => 'damage',
            'created_at' => $today->copy()->subDays(5),
        ]);
        DamageDetail::factory()->create(['report_id' => $report1->id]);

        $report2 = Report::factory()->create([
            'report_type' => 'damage',
            'created_at' => $today,
        ]);
        DamageDetail::factory()->create(['report_id' => $report2->id]);

        $report3 = Report::factory()->create([
            'report_type' => 'damage',
            'created_at' => $today->copy()->addDays(5),
        ]);
        DamageDetail::factory()->create(['report_id' => $report3->id]);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', [
                'from_date' => $today->copy()->subDays(3)->format('Y-m-d'),
                'to_date' => $today->copy()->addDays(3)->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
    }

    public function test_sarpras_combined_filters_work()
    {
        $today = Carbon::now();

        $report1 = Report::factory()->create([
            'report_type' => 'damage',
            'title' => 'AC Rusak',
            'status' => 'menunggu_verifikasi',
            'created_at' => $today,
        ]);
        DamageDetail::factory()->create([
            'report_id' => $report1->id,
            'priority' => 'tinggi',
        ]);

        $report2 = Report::factory()->create([
            'report_type' => 'damage',
            'title' => 'AC Rusak',
            'status' => 'selesai',
            'created_at' => $today,
        ]);
        DamageDetail::factory()->create([
            'report_id' => $report2->id,
            'priority' => 'tinggi',
        ]);

        $report3 = Report::factory()->create([
            'report_type' => 'damage',
            'title' => 'Pintu Rusak',
            'status' => 'menunggu_verifikasi',
            'created_at' => $today->copy()->subDays(10),
        ]);
        DamageDetail::factory()->create([
            'report_id' => $report3->id,
            'priority' => 'rendah',
        ]);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', [
                'search' => 'AC',
                'status' => 'menunggu_verifikasi',
                'priority' => 'tinggi',
                'from_date' => $today->copy()->subDays(1)->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
        $this->assertEquals('AC Rusak', $response->viewData('reports')->first()->title);
        $this->assertEquals('menunggu_verifikasi', $response->viewData('reports')->first()->status);
        $this->assertEquals('tinggi', $response->viewData('reports')->first()->damageDetail->priority);
    }

    public function test_sarpras_invalid_priority_filter_is_ignored()
    {
        Report::factory()
            ->count(3)
            ->create(['report_type' => 'damage'])
            ->each(function ($report) {
                DamageDetail::factory()->create(['report_id' => $report->id]);
            });

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', ['priority' => 'invalid_priority']));

        $response->assertStatus(200);
        $this->assertCount(3, $response->viewData('reports'));
    }

    public function test_sarpras_pagination_preserves_filters()
    {
        Report::factory()
            ->count(20)
            ->create([
                'report_type' => 'damage',
                'title' => 'AC Rusak',
                'status' => 'menunggu_verifikasi',
            ])
            ->each(function ($report) {
                DamageDetail::factory()->create([
                    'report_id' => $report->id,
                    'priority' => 'tinggi',
                ]);
            });

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index', [
                'search' => 'AC',
                'status' => 'menunggu_verifikasi',
                'priority' => 'tinggi',
                'page' => 2,
            ]));

        $response->assertStatus(200);
        // Check that pagination links contain the filter parameters
        $this->assertStringContainsString('search=AC', $response->getContent());
        $this->assertStringContainsString('status=menunggu_verifikasi', $response->getContent());
        $this->assertStringContainsString('priority=tinggi', $response->getContent());
    }

    public function test_sarpras_only_damage_reports_shown()
    {
        $report1 = Report::factory()->create(['report_type' => 'damage']);
        DamageDetail::factory()->create(['report_id' => $report1->id]);

        Report::factory()->create(['report_type' => 'violation']);

        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.index'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
    }
}
