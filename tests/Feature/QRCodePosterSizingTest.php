<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QRCodePosterSizingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_a1_poster_keeps_original_design_viewbox(): void
    {
        [$admin, $qr] =
            $this->makeQr();

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.qrcodes.download',
                    [
                        'qrCode' => $qr,
                        'paper' => 'a1',
                    ]
                )
            );

        $response->assertOk();

        $svg =
            $response->getContent();

        $this->assertStringContainsString(
            'width="594mm"',
            $svg
        );

        $this->assertStringContainsString(
            'height="841mm"',
            $svg
        );

        $this->assertStringContainsString(
            'viewBox="0 0 1122 1402"',
            $svg
        );
    }

    public function test_default_poster_is_a4(): void
    {
        [$admin, $qr] =
            $this->makeQr();

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.qrcodes.download',
                    [
                        'qrCode' => $qr,
                    ]
                )
            );

        $response->assertOk();

        $svg =
            $response->getContent();

        $this->assertStringContainsString(
            'width="210mm"',
            $svg
        );

        $this->assertStringContainsString(
            'height="297mm"',
            $svg
        );
    }

    public function test_a5_poster_size_is_supported(): void
    {
        [$admin, $qr] =
            $this->makeQr();

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.qrcodes.download',
                    [
                        'qrCode' => $qr,
                        'paper' => 'a5',
                    ]
                )
            );

        $response->assertOk();

        $svg =
            $response->getContent();

        $this->assertStringContainsString(
            'width="148mm"',
            $svg
        );

        $this->assertStringContainsString(
            'height="210mm"',
            $svg
        );
    }

    public function test_desk_poster_is_100_by_125_mm(): void
    {
        [$admin, $qr] =
            $this->makeQr();

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.qrcodes.download',
                    [
                        'qrCode' => $qr,
                        'paper' => 'desk',
                    ]
                )
            );

        $response->assertOk();

        $svg =
            $response->getContent();

        $this->assertStringContainsString(
            'width="100mm"',
            $svg
        );

        $this->assertStringContainsString(
            'height="125mm"',
            $svg
        );
    }

    public function test_desk_mini_poster_is_80_by_100_mm(): void
    {
        [$admin, $qr] =
            $this->makeQr();

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.qrcodes.download',
                    [
                        'qrCode' => $qr,
                        'paper' => 'desk-mini',
                    ]
                )
            );

        $response->assertOk();

        $svg =
            $response->getContent();

        $this->assertStringContainsString(
            'width="80mm"',
            $svg
        );

        $this->assertStringContainsString(
            'height="100mm"',
            $svg
        );
    }

    public function test_invalid_paper_size_is_rejected(): void
    {
        [$admin, $qr] =
            $this->makeQr();

        $response = $this
            ->actingAs($admin)
            ->get(
                route(
                    'admin.qrcodes.download',
                    [
                        'qrCode' => $qr,
                        'paper' => 'a99',
                    ]
                )
            );

        $response->assertStatus(302);

        $response->assertSessionHasErrors(
            'paper'
        );
    }

    public function test_refreshing_same_qr_does_not_inflate_scan_count(): void
    {
        [, $qr] =
            $this->makeQr();

        $url = route(
            'public.report.qr',
            $qr->qr_identifier
        );

        $this->get($url)
            ->assertOk();

        $this->get($url)
            ->assertOk();

        $this->get($url)
            ->assertOk();

        $this->assertSame(
            1,
            (int) $qr
                ->fresh()
                ->scan_count
        );
    }

    private function makeQr(): array
    {
        $admin =
            User::factory()->create([
                'role' =>
                    'superadmin',

                'is_active' =>
                    true,
            ]);

        $identifier =
            'test-qr-'
            .str()
                ->lower(
                    str()->random(8)
                );

        $qr = QrCode::create([
            'qr_identifier' =>
                $identifier,

            'qr_name' =>
                'QR Test Poster',

            'qr_type' =>
                'general',

            'class_id' =>
                null,

            'location_id' =>
                null,

            'target_url' =>
                route(
                    'public.report.qr',
                    $identifier
                ),

            'created_by' =>
                $admin->id,

            'is_active' =>
                true,

            'scan_count' =>
                0,
        ]);

        return [
            $admin,
            $qr,
        ];
    }
}
