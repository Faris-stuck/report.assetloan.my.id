<?php

namespace App\Http\Controllers;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\QrCode;
use App\Http\Requests\PublicReportRequest;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Subject;
use App\Services\PublicReport\PublicReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicReportController extends Controller
{
    private const CLASS_MAJOR_LABELS = [
        'RPL' => 'Rekayasa Perangkat Lunak',
        'TKR' => 'Teknik Kendaraan Ringan',
        'TITL' => 'Teknik Instalasi Tenaga Listrik',
        'TAV' => 'Teknik Elektronika Audio Video',
    ];

    private PublicReportService $service;

    public function __construct(PublicReportService $service)
    {
        $this->service = $service;
    }

    public function create(?string $qr = null): View
    {
        $qrCode = $qr ? QrCode::where('qr_identifier', $qr)->where('is_active', true)->firstOrFail() : null;
        if ($qrCode) {
            $qrCode->increment('scan_count');
        }

        session(['report_submit_token' => (string) Str::uuid()]);
        session(['math_captcha_answer' => ($a = random_int(1, 9)) + ($b = random_int(1, 9))]);

        $majorOrder = array_flip(array_keys(self::CLASS_MAJOR_LABELS));
        $classes = SchoolClass::where('is_active', true)->get()->sort(function (SchoolClass $left, SchoolClass $right) use ($majorOrder): int {
            $leftMajor = strtoupper(trim((string) ($left->major ?: 'LAINNYA')));
            $rightMajor = strtoupper(trim((string) ($right->major ?: 'LAINNYA')));
            $leftRank = $majorOrder[$leftMajor] ?? PHP_INT_MAX;
            $rightRank = $majorOrder[$rightMajor] ?? PHP_INT_MAX;

            return ($leftRank <=> $rightRank)
                ?: strnatcasecmp($leftMajor, $rightMajor)
                ?: strnatcasecmp((string) $left->grade_level, (string) $right->grade_level)
                ?: strnatcasecmp($left->class_name, $right->class_name);
        });

        $classesByMajor = $classes->groupBy(
            fn (SchoolClass $class): string => strtoupper(trim((string) ($class->major ?: 'LAINNYA')))
        );

        return view('public.report-form', [
            'qrCode' => $qrCode,
            'captchaQuestion' => "$a + $b",
            'classesByMajor' => $classesByMajor,
            'classMajorLabels' => self::CLASS_MAJOR_LABELS,
            'subjects' => Subject::where('is_active', true)->orderBy('subject_name')->get(),
            'staffUnits' => StaffUnit::where('is_active', true)->orderBy('unit_name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('location_name')->get(),
            'damageCategories' => DamageCategory::where('is_active', true)->orderBy('category_name')->get(),
        ]);
    }

    public function store(PublicReportRequest $request): RedirectResponse
    {
        $sessionToken = session('report_submit_token');
        $submittedToken = (string) $request->input('report_submit_token', '');

        if ($submittedToken !== '' && (! $sessionToken || ! hash_equals((string) $sessionToken, $submittedToken))) {
            throw ValidationException::withMessages([
                'form' => 'Sesi formulir sudah habis atau Anda sudah mengirim laporan sebelumnya. Silakan buka halaman baru untuk membuat laporan.',
            ]);
        }

        $validated = $request->validated();

        if ((int) $validated['captcha'] !== (int) session('math_captcha_answer')) {
            throw ValidationException::withMessages([
                'captcha' => 'CAPTCHA salah. Hitung ulang pertanyaan yang tampil lalu isi dengan angka yang benar.',
            ]);
        }

        [$report, $accessCode, $notificationSent] = $this->service->create($request, $validated);

        session()->forget(['math_captcha_answer', 'report_submit_token']);
        $request->session()->regenerateToken();

        $redirect = redirect()
            ->route('public.report.success', $report->public_token)
            ->with('access_code', $accessCode)
            ->with('success_report_id', $report->id);

        if ($report->reporter_email) {
            $redirect = $redirect->with('notification_message', $notificationSent
                ? "Notifikasi email konfirmasi laporan telah dikirim ke {$report->reporter_email}. Silakan cek kotak masuk atau folder spam jika tidak terlihat dalam beberapa menit."
                : "Permintaan notifikasi email gagal dikirim ke {$report->reporter_email}. Laporan tetap diterima dan nomor laporan + kode akses sudah dapat digunakan untuk pelacakan.");
        }

        return $redirect;
    }

    public function success(Report $report): View|RedirectResponse
    {
        if ((int) session('success_report_id') !== $report->id) {
            return redirect()
                ->route('track.form')
                ->withErrors(['report_number' => 'Halaman sukses hanya bisa dibuka setelah laporan berhasil dikirim. Untuk melihat laporan, masukkan nomor laporan dan kode akses.']);
        }

        session()->forget('success_report_id');

        return view('public.success', ['report' => $report, 'accessCode' => session('access_code')]);
    }
}
