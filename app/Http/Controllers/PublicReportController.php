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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

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

        try {
            if ($qrCode) {
                /*
                 * Refresh pada browser/session yang sama
                 * tidak boleh terus menambah scan_count.
                 *
                 * Satu QR dihitung maksimal sekali
                 * per session selama 30 menit.
                 */
                $scanSessionKey =
                    'laporin_qr_scan_'.$qrCode->id;

                $lastScanAt = (int) session(
                    $scanSessionKey,
                    0
                );

                if (
                    $lastScanAt === 0
                    || now()->timestamp - $lastScanAt >= 1800
                ) {
                    $qrCode->increment(
                        'scan_count'
                    );

                    session([
                        $scanSessionKey =>
                            now()->timestamp,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            // Jika session/cache/storage tidak tersedia (misal Redis mati), tetap lanjutkan.
            // Halaman harus tetap menampilkan form agar user dapat melapor.
        }

        /*
         * Setiap tab/form memiliki token + CAPTCHA sendiri.
         * Membuka tab kedua tidak lagi membuat tab pertama invalid.
         */
        $submitToken = (string) Str::uuid();

        $a = random_int(1, 9);
        $b = random_int(1, 9);

        $captchaAnswer = $a + $b;

        $formStates = [];

        try {
            $formStates = session(
                'report_submit_forms',
                []
            );

            if (! is_array($formStates)) {
                $formStates = [];
            }

            /*
             * Buang form lama agar session tidak terus membesar.
             */
            $cutoff = now()
                ->subMinutes(30)
                ->timestamp;

            $formStates = array_filter(
                $formStates,
                static fn (mixed $state): bool =>
                    is_array($state)
                    && (int) ($state['created_at'] ?? 0) >= $cutoff
            );

            $formStates[$submitToken] = [
                'captcha_answer' => $captchaAnswer,
                'captcha_a' => $a,
                'captcha_b' => $b,
                'wizard_data' => [],
                'created_at' => now()->timestamp,

                /*
                 * Security:
                 * QR attribution diikat ke form token di server.
                 * Jangan mengambil qr_code_id dari browser saat submit.
                 */
                'qr_code_id' => $qrCode?->id,
            ];

            /*
             * Maksimal lima form/tab aktif per session.
             */
            if (count($formStates) > 5) {

                uasort(
                    $formStates,
                    static fn (array $left, array $right): int =>
                        ((int) ($left['created_at'] ?? 0))
                        <=>
                        ((int) ($right['created_at'] ?? 0))
                );

                $formStates = array_slice(
                    $formStates,
                    -5,
                    null,
                    true
                );
            }

            session([
                'report_submit_forms' => $formStates,

                /*
                 * Legacy testing compatibility.
                 */
                'report_submit_token' => $submitToken,
                'math_captcha_answer' => $captchaAnswer,
            ]);
        } catch (Throwable $exception) {
            // Jika penyimpanan sesi gagal, tetap buka form tanpa menonaktifkan tampilan.
            // Kunci submit akan dikirim lewat hidden input untuk validasi request server-side.
        }

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
            'wizardStep' => 1,
            'qrCode' => $qrCode,
            'reportSubmitToken' => $submitToken,
            'captchaQuestion' => "$a + $b",
            'classesByMajor' => $classesByMajor,
            'classMajorLabels' => self::CLASS_MAJOR_LABELS,
            'subjects' => Subject::where('is_active', true)->orderBy('subject_name')->get(),
            'staffUnits' => StaffUnit::where('is_active', true)->orderBy('unit_name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('location_name')->get(),
            'damageCategories' => DamageCategory::where('is_active', true)->orderBy('category_name')->get(),
        ]);
    }

    public function wizardStep(int $step): View|RedirectResponse
    {
        if ($step < 1 || $step > 4) {
            return redirect()->route('public.report');
        }

        $token = (string) session('report_submit_token', '');
        $forms = session('report_submit_forms', []);
        $state = is_array($forms) ? ($forms[$token] ?? null) : null;

        if ($token === '' || ! is_array($state)) {
            return redirect()->route('public.report')
                ->withErrors(['form' => 'Sesi formulir sudah habis. Silakan mulai laporan baru.']);
        }

        $wizardData = is_array($state['wizard_data'] ?? null) ? $state['wizard_data'] : [];
        session()->flashInput($wizardData);

        return $this->renderReportWizard($step);
    }

    public function wizardStoreStep(Request $request, int $step): RedirectResponse
    {
        if ($step < 1 || $step > 4) {
            return redirect()->route('public.report');
        }

        $token = (string) $request->input('report_submit_token', '');
        $forms = session('report_submit_forms', []);
        $state = is_array($forms) ? ($forms[$token] ?? null) : null;

        if ($token === '' || ! is_array($state)) {
            return redirect()->route('public.report')
                ->withErrors(['form' => 'Sesi formulir sudah habis. Silakan mulai laporan baru.']);
        }

        $draft = is_array($state['wizard_data'] ?? null) ? $state['wizard_data'] : [];
        $incoming = $request->except(['_token', 'report_submit_token']);

        // The public form is student-only. Keep reporter_type as an internal
        // server value so the persistence/validation contract remains intact;
        // it is never exposed as a category or selectable field in the UI.
        if ($step === 1) {
            $incoming['reporter_type'] = 'siswa';
            unset($incoming['reporter_subject_id'], $incoming['reporter_staff_unit_id']);
        }

        if ($step < 4) {
            $data = array_merge($draft, $incoming);
            $rules = $this->wizardRules($step, $data);
            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return redirect()->route('public.report.step', $step)
                    ->withErrors($validator)
                    ->withInput();
            }

            $validated = $validator->validated();
            $draft = array_merge($draft, $validated);
            $forms[$token]['wizard_data'] = $draft;
            session(['report_submit_forms' => $forms]);

            return redirect()->route('public.report.step', $step + 1);
        }

        // Step 4 is the only point where the actual report is created.
        $data = array_merge($draft, $incoming);
        if ($request->hasFile('attachments')) {
            $data['attachments'] = $request->file('attachments');
        }
        $data['report_submit_token'] = $token;
        $data['qr_code_id'] = isset($state['qr_code_id']) ? (int) $state['qr_code_id'] : null;

        $fullRequest = new PublicReportRequest();
        $rules = $fullRequest->rules();
        $validator = Validator::make($data, $rules, $fullRequest->messages());

        if ($validator->fails()) {
            return redirect()->route('public.report.step', 4)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $expectedCaptcha = $state['captcha_answer'] ?? null;
        if (! is_numeric($expectedCaptcha) || (int) ($validated['captcha'] ?? -1) !== (int) $expectedCaptcha) {
            return redirect()->route('public.report.step', 4)
                ->withErrors(['captcha' => 'CAPTCHA salah. Hitung ulang pertanyaan yang tampil lalu isi dengan angka yang benar.'])
                ->withInput();
        }

        $consumeKey = 'laporin:public-report:consume:'.hash('sha256', $token);
        if (! Cache::add($consumeKey, true, now()->addMinutes(30))) {
            return redirect()->route('public.report.step', 4)
                ->withErrors(['form' => 'Laporan sedang atau sudah diproses. Jangan menekan tombol kirim berulang kali.'])
                ->withInput();
        }

        $serviceRequest = Request::create(
            $request->url(),
            'POST',
            collect($data)->except(['attachments'])->all(),
            $request->cookies->all(),
            $request->hasFile('attachments') ? ['attachments' => $request->file('attachments')] : [],
            $request->server->all(),
            $request->getContent()
        );
        $serviceRequest->setLaravelSession($request->session());

        try {
            [$report, $accessCode, $notificationSent] = $this->service->create($serviceRequest, $validated);
        } catch (Throwable $exception) {
            Cache::forget($consumeKey);
            throw $exception;
        }

        unset($forms[$token]);
        session([
            'report_submit_forms' => $forms,
            'report_form_submitted' => true,
            'success_report_id' => $report->id,
            'access_code' => $accessCode,
        ]);

        $request->session()->regenerateToken();

        $redirect = redirect()
            ->route('public.report.success', $report->public_token)
            ->with('access_code', $accessCode)
            ->with('success_report_id', $report->id);

        if ($report->reporter_email) {
            $redirect = $redirect->with('notification_message', $notificationSent
                ? "Notifikasi email konfirmasi laporan telah dimasukkan ke antrean pengiriman untuk {$report->reporter_email}. Silakan cek kotak masuk atau folder spam jika belum terlihat dalam beberapa menit."
                : "Permintaan notifikasi email gagal dikirim ke {$report->reporter_email}. Laporan tetap diterima dan nomor laporan + kode akses sudah dapat digunakan untuk pelacakan.");
        }

        return $redirect;
    }

    private function wizardRules(int $step, array $data): array
    {
        $request = new PublicReportRequest();
        $all = $request->rules();

        if ($step === 1) {
            return array_intersect_key($all, array_flip([
                'reporter_type', 'reporter_name', 'reporter_class_id',
                'reporter_absence_number', 'reporter_phone', 'reporter_email',
            ]));
        }

        if ($step === 2) {
            return ['report_type' => $all['report_type']];
        }

        $keys = [
            'title', 'urgency', 'incident_date', 'related_class_id',
            'location_id', 'custom_location', 'description',
            'reporter_position', 'bullying_type', 'victim_name',
            'victim_class_id', 'alleged_actor_name', 'alleged_actor_class_id',
            'witness_name', 'impact_description', 'item_name', 'item_category',
            'damage_condition', 'suspected_cause', 'priority', 'incident_time',
        ];

        return array_intersect_key($all, array_flip($keys));
    }

    private function renderReportWizard(int $step): View
    {
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

        $classesByMajor = $classes->groupBy(fn (SchoolClass $class): string => strtoupper(trim((string) ($class->major ?: 'LAINNYA'))));
        $token = (string) session('report_submit_token', '');
        $forms = session('report_submit_forms', []);
        $state = is_array($forms) ? ($forms[$token] ?? []) : [];

        return view('public.report-form', [
            'wizardStep' => $step,
            'qrCode' => null,
            'reportSubmitToken' => $token,
            'captchaQuestion' => $this->captchaQuestion($state),
            'classesByMajor' => $classesByMajor,
            'classMajorLabels' => self::CLASS_MAJOR_LABELS,
            'subjects' => Subject::where('is_active', true)->orderBy('subject_name')->get(),
            'staffUnits' => StaffUnit::where('is_active', true)->orderBy('unit_name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('location_name')->get(),
            'damageCategories' => DamageCategory::where('is_active', true)->orderBy('category_name')->get(),
        ]);
    }

    private function captchaQuestion(array $state): string
    {
        $answer = (int) ($state['captcha_answer'] ?? 0);
        $a = (int) ($state['captcha_a'] ?? 0);
        $b = (int) ($state['captcha_b'] ?? 0);
        return ($a > 0 && $b > 0 && $a + $b === $answer) ? "$a + $b" : '0 + 0';
    }

    public function store(PublicReportRequest $request): RedirectResponse
    {
        $submittedToken = (string) $request->input(
            'report_submit_token',
            ''
        );

        $legacySessionToken = session(
            'report_submit_token'
        );

        $formStates = session(
            'report_submit_forms',
            []
        );

        if (! is_array($formStates)) {
            $formStates = [];
        }

        $formState = (
            $submittedToken !== ''
            && isset($formStates[$submittedToken])
            && is_array($formStates[$submittedToken])
        )
            ? $formStates[$submittedToken]
            : null;

        /*
         * Compatibility dengan session lama dan feature test.
         */
        $legacyMatches = (
            $submittedToken !== ''
            && is_string($legacySessionToken)
            && $legacySessionToken !== ''
            && hash_equals(
                $legacySessionToken,
                $submittedToken
            )
        );

        if (
            $formState === null
            && ! $legacyMatches
        ) {
            throw ValidationException::withMessages([
                'form' => 'Sesi formulir sudah habis atau laporan ini sudah pernah dikirim. Muat ulang formulir lalu coba kembali.',
            ]);
        }

        $validated = $request->validated();

        // Five successfully validated report submissions per browser/device
        // identifier in a rolling two-hour window. This is independent of the
        // public Internet IP, which can be shared by many devices.
        $deviceRateKey = $this->deviceRateKey($request);
        $deviceAttempt = RateLimiter::increment($deviceRateKey, 7200);
        if ($deviceAttempt > 5) {
            RateLimiter::decrement($deviceRateKey, 7200);
            throw ValidationException::withMessages([
                'form' => 'Batas pengiriman tercapai: maksimal 5 laporan per perangkat dalam 2 jam. Coba lagi setelah batas waktu berakhir.',
            ]);
        }

        /*
         * Security:
         * Jangan percaya qr_code_id yang dikirim browser.
         *
         * QR ID harus berasal dari form state yang dibuat server
         * ketika halaman /lapor/{qr} dibuka.
         */
        $validated['qr_code_id'] = isset($formState['qr_code_id'])
            ? (int) $formState['qr_code_id']
            : null;

        $expectedCaptcha =
            $formState['captcha_answer']
            ?? session('math_captcha_answer');

        if (
            ! is_numeric($expectedCaptcha)
            || (int) $validated['captcha']
                !== (int) $expectedCaptcha
        ) {
            throw ValidationException::withMessages([
                'captcha' => 'CAPTCHA salah. Hitung ulang pertanyaan yang tampil lalu isi dengan angka yang benar.',
            ]);
        }

        /*
         * Cache::add bersifat atomic pada Redis.
         * Request paralel dengan token sama hanya satu yang lolos.
         */
        $consumeKey =
            'laporin:public-report:consume:'
            .hash(
                'sha256',
                $submittedToken
            );

        if (! Cache::add(
            $consumeKey,
            true,
            now()->addMinutes(30)
        )) {
            throw ValidationException::withMessages([
                'form' => 'Laporan sedang atau sudah diproses. Jangan menekan tombol kirim berulang kali.',
            ]);
        }

        try {

            [
                $report,
                $accessCode,
                $notificationSent,
            ] = $this->service->create(
                $request,
                $validated
            );

        } catch (Throwable $exception) {

            /*
             * Penyimpanan gagal:
             * izinkan user mencoba ulang.
             */
            Cache::forget($consumeKey);
            RateLimiter::decrement($deviceRateKey, 7200);

            throw $exception;
        }

        /*
         * Hanya token tab yang berhasil dikirim yang dihapus.
         */
        unset(
            $formStates[$submittedToken]
        );

        try {
            session(['report_submit_forms' => $formStates]);
        } catch (Throwable $exception) {
            // Biarkan user tetap dapat mengakses form jika sesi tidak tersedia.
        }

        if ($legacyMatches) {
            session()->forget([
                'math_captcha_answer',
                'report_submit_token',
            ]);
        }

        $request->session()->regenerateToken();

        $redirect = redirect()
            ->route('public.report.success', $report->public_token)
            ->with('access_code', $accessCode)
            ->with('success_report_id', $report->id);

        if ($report->reporter_email) {
            $redirect = $redirect->with('notification_message', $notificationSent
                ? "Notifikasi email konfirmasi laporan telah dimasukkan ke antrean pengiriman untuk {$report->reporter_email}. Silakan cek kotak masuk atau folder spam jika belum terlihat dalam beberapa menit."
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
    private function deviceRateKey(Request $request): string
    {
        $deviceId = (string) ($request->cookie('laporin_device_id') ?: ($request->ip() ?? 'unknown'));
        return 'laporin:public-reports:device:'.hash_hmac('sha256', $deviceId, config('app.key'));
    }

}

