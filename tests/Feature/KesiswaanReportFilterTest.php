<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Student;
use App\Models\ViolationType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KesiswaanReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $kesiswaanUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kesiswaanUser = User::factory()->create([
            'role' => 'kesiswaan',
            'is_active' => true,
        ]);
    }

    public function test_kesiswaan_index_displays_all_violation_reports()
    {
        // Create test data
        Report::factory()
            ->count(3)
            ->create(['report_type' => 'violation']);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index'));

        $response->assertStatus(200)
            ->assertViewHas('reports')
            ->assertViewHas('students')
            ->assertViewHas('types');
    }

    public function test_kesiswaan_search_filters_by_report_number()
    {
        $report1 = Report::factory()->create([
            'report_type' => 'violation',
            'report_number' => 'LAPOR-001',
            'title' => 'Bullying di Koridor',
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'report_number' => 'LAPOR-002',
            'title' => 'Tawuran Lapangan',
        ]);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', ['search' => 'LAPOR-001']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
        $this->assertEquals($report1->id, $response->viewData('reports')->first()->id);
    }

    public function test_kesiswaan_search_filters_by_title()
    {
        $report1 = Report::factory()->create([
            'report_type' => 'violation',
            'title' => 'Pencurian Uang',
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'title' => 'Terlambat Masuk',
        ]);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', ['search' => 'Pencurian']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
    }

    public function test_kesiswaan_search_filters_by_description()
    {
        $report1 = Report::factory()->create([
            'report_type' => 'violation',
            'description' => 'Siswa X mencuri dompet dari tas Y',
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'description' => 'Siswa sering tidur di kelas',
        ]);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', ['search' => 'dompet']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
    }

    public function test_kesiswaan_status_filter_works()
    {
        Report::factory()->create([
            'report_type' => 'violation',
            'status' => 'menunggu_verifikasi',
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'status' => 'selesai',
        ]);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', ['status' => 'menunggu_verifikasi']));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
        $this->assertEquals('menunggu_verifikasi', $response->viewData('reports')->first()->status);
    }

    public function test_kesiswaan_date_range_filter_works()
    {
        $today = Carbon::now();

        Report::factory()->create([
            'report_type' => 'violation',
            'created_at' => $today->copy()->subDays(5),
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'created_at' => $today,
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'created_at' => $today->copy()->addDays(5),
        ]);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', [
                'from_date' => $today->copy()->subDays(3)->format('Y-m-d'),
                'to_date' => $today->copy()->addDays(3)->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $this->assertCount(2, $response->viewData('reports'));
    }

    public function test_kesiswaan_combined_filters_work()
    {
        $today = Carbon::now();

        Report::factory()->create([
            'report_type' => 'violation',
            'title' => 'Pencurian',
            'status' => 'menunggu_verifikasi',
            'created_at' => $today,
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'title' => 'Pencurian',
            'status' => 'selesai',
            'created_at' => $today,
        ]);

        Report::factory()->create([
            'report_type' => 'violation',
            'title' => 'Tawuran',
            'status' => 'menunggu_verifikasi',
            'created_at' => $today->copy()->subDays(10),
        ]);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', [
                'search' => 'Pencurian',
                'status' => 'menunggu_verifikasi',
                'from_date' => $today->copy()->subDays(1)->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
        $this->assertEquals('Pencurian', $response->viewData('reports')->first()->title);
        $this->assertEquals('menunggu_verifikasi', $response->viewData('reports')->first()->status);
    }

    public function test_kesiswaan_invalid_status_filter_is_ignored()
    {
        Report::factory()->count(3)->create(['report_type' => 'violation']);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', ['status' => 'invalid_status']));

        $response->assertStatus(200);
        // All reports should be shown since invalid status is ignored
        $this->assertCount(3, $response->viewData('reports'));
    }

    public function test_kesiswaan_pagination_preserves_filters()
    {
        Report::factory()
            ->count(20)
            ->create([
                'report_type' => 'violation',
                'title' => 'Pencurian',
                'status' => 'menunggu_verifikasi',
            ]);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index', [
                'search' => 'Pencurian',
                'status' => 'menunggu_verifikasi',
                'page' => 2,
            ]));

        $response->assertStatus(200);
        // Check that pagination links contain the filter parameters
        $this->assertStringContainsString('search=Pencurian', $response->getContent());
        $this->assertStringContainsString('status=menunggu_verifikasi', $response->getContent());
    }

    public function test_kesiswaan_reset_button_clears_filters()
    {
        Report::factory()->count(3)->create(['report_type' => 'violation']);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index'));

        $response->assertStatus(200);
        // The reset link should go to kesiswaan.index without parameters
        $resetUrl = route('kesiswaan.index');
        $this->assertStringContainsString($resetUrl, $response->getContent());
    }

    public function test_kesiswaan_only_violation_reports_shown()
    {
        Report::factory()->create(['report_type' => 'violation']);
        Report::factory()->create(['report_type' => 'damage']);

        $response = $this->actingAs($this->kesiswaanUser)
            ->get(route('kesiswaan.index'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('reports'));
    }
}
