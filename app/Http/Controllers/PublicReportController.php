<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Models\DamageCategory;
use App\Models\QrCode;
use App\Http\Requests\PublicReportRequest;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Subject;
use App\Services\PublicReport\PublicReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

    /** Maksimal laporan yang benar-benar TERKIRIM per perangkat dalam satu jendela. */
    private const MAX_REPORTS_PER_WINDOW = 5;

    /** Panjang jendela kuota laporan: 2 jam. */
    private const REPORT_WINDOW_SECONDS = 7200;

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
                'qr_code_id' => $qrCode?->id,
            ];

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
                'report_submit_token' => $submitToken,
                'math_captcha_answer' => $captchaAnswer,
            ]);
        } catch (Throwable $exception) {
            /*
             * Form tetap dirender agar pelapor tidak kehilangan halaman, tapi
             * kegagalan di sini berarti 'math_captcha_answer' tidak tersimpan,
             * sehingga setiap submit berikutnya ditolak validasi CAPTCHA.
             * Jangan telan tanpa jejak: tanpa log, gejala di produksi hanya
             * "CAPTCHA selalu salah" tanpa penyebab yang bisa dilacak.
             */
            Log::error('Gagal menyiapkan state wizard laporan publik.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return view('public.report-form', [
            'wizardStep' => 1,
            'qrCode' => $qrCode,
            'reportSubmitToken' => $submitToken,
            'captchaQuestion' => "$a + $b",
            ...$this->wizardMasterData(),
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

        // A failed submit already flashed what the user just typed via withInput().
        // Seeding the saved draft on top of it would silently discard their edits,
        // so the freshly flashed input has to win field by field.
        $flashed = session()->getOldInput();
        session()->flashInput(
            is_array($flashed) && $flashed !== []
                ? array_merge($wizardData, $flashed)
                : $wizardData
        );

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

        // Damage reports intentionally use a compact form: the item name and
        // one description are sufficient for the public UI. Keep the legacy
        // persistence contract by deriving title/condition server-side.
        if ($step === 3 && (($incoming['report_type'] ?? $draft['report_type'] ?? null) === 'damage')) {
            $itemName = trim((string) ($incoming['item_name'] ?? $draft['item_name'] ?? ''));
            $description = trim((string) ($incoming['description'] ?? $draft['description'] ?? ''));
            if ($itemName !== '') {
                $incoming['title'] = $itemName;
            }
            if ($description !== '') {
                $incoming['damage_condition'] = $description;
            }
        }

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

        /*
         * Kuota bisnis: maksimal 5 laporan TERKIRIM per perangkat dalam 2 jam.
         *
         * Sebelumnya pemeriksaan ini HANYA ada di store(), yang tidak lagi
         * dipakai formulir publik -- form mem-post ke wizard. Akibatnya aturan
         * yang didokumentasikan tidak pernah benar-benar berlaku di produksi:
         * satu perangkat bisa mengirim laporan tanpa batas selama masih lolos
         * throttle per-menit. Diperiksa di sini, setelah CAPTCHA lolos dan
         * sebelum consume key dipakai, supaya percobaan yang gagal validasi
         * atau salah CAPTCHA tidak ikut menghabiskan kuota pelapor.
         *
         * Memakai identitas perangkat, bukan IP publik, karena satu IP sekolah
         * dipakai banyak siswa. Saat kuota habis pelapor menerima pesan di
         * dalam formulir dan situs tetap bisa diakses (melacak laporan lama,
         * membaca panduan), bukan dilempar ke halaman 429.
         */
        $deviceRateKey = $this->deviceRateKey($request);
        if (RateLimiter::tooManyAttempts($deviceRateKey, self::MAX_REPORTS_PER_WINDOW)) {
            return redirect()->route('public.report.step', 4)
                ->withErrors(['form' => 'Batas pengiriman tercapai: maksimal 5 laporan per perangkat dalam 2 jam. Laporan yang sudah masuk tetap bisa dipantau di halaman Lacak Laporan.'])
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

        // Laporan benar-benar tersimpan: baru sekarang kuota perangkat dipakai.
        RateLimiter::hit($deviceRateKey, self::REPORT_WINDOW_SECONDS);

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
            'description',
            'reporter_position', 'bullying_type', 'victim_name',
            'victim_class_id', 'alleged_actor_name', 'alleged_actor_class_id',
            'witness_name', 'impact_description', 'item_name', 'item_category',
            'damage_condition', 'suspected_cause', 'priority', 'incident_time',
        ];

        return array_intersect_key($all, array_flip($keys));
    }

    private function renderReportWizard(int $step): View
    {
        $token = (string) session('report_submit_token', '');
        $forms = session('report_submit_forms', []);
        $state = is_array($forms) ? ($forms[$token] ?? []) : [];

        return view('public.report-form', [
            'wizardStep' => $step,
            'qrCode' => null,
            'reportSubmitToken' => $token,
            'captchaQuestion' => $this->captchaQuestion($state),
            ...$this->wizardMasterData(),
        ]);
    }

    /**
     * Data master yang dipakai identik oleh dua entry point wizard, create()
     * dan renderReportWizard(). Blok pengurutan jurusan plus tiga query
     * master-data ini sebelumnya ditulis dua kali, sehingga urutan kelas bisa
     * menyimpang antar langkah begitu salah satu sisi saja diubah.
     *
     * Keempat daftar ini nyaris tidak pernah berubah tapi dibaca di setiap
     * render langkah wizard, jadi disimpan di cache dengan kunci
     * laporin:reference:* (TTL 1 jam) dan di-invalidasi oleh AdminService
     * begitu data master diubah.
     */
    private function wizardMasterData(): array
    {
        $majorOrder = array_flip(array_keys(self::CLASS_MAJOR_LABELS));
        $classes = $this->referenceList('classes', fn () => SchoolClass::where('is_active', true)->get())
            ->sort(function (SchoolClass $left, SchoolClass $right) use ($majorOrder): int {
                $leftMajor = strtoupper(trim((string) ($left->major ?: 'LAINNYA')));
                $rightMajor = strtoupper(trim((string) ($right->major ?: 'LAINNYA')));
                $leftRank = $majorOrder[$leftMajor] ?? PHP_INT_MAX;
                $rightRank = $majorOrder[$rightMajor] ?? PHP_INT_MAX;

                return ($leftRank <=> $rightRank)
                    ?: strnatcasecmp($leftMajor, $rightMajor)
                    ?: strnatcasecmp((string) $left->grade_level, (string) $right->grade_level)
                    ?: strnatcasecmp($left->class_name, $right->class_name);
            });

        return [
            'classesByMajor' => $classes->groupBy(
                fn (SchoolClass $class): string => strtoupper(trim((string) ($class->major ?: 'LAINNYA')))
            ),
            'classMajorLabels' => self::CLASS_MAJOR_LABELS,
            'subjects' => $this->referenceList('subjects', fn () => Subject::where('is_active', true)->orderBy('subject_name')->get()),
            'staffUnits' => $this->referenceList('staff_units', fn () => StaffUnit::where('is_active', true)->orderBy('unit_name')->get()),
            'damageCategories' => $this->referenceList('damage_categories', fn () => DamageCategory::where('is_active', true)->orderBy('category_name')->get()),
        ];
    }

    /**
     * Cache miss atau backend cache yang sedang bermasalah tidak boleh
     * menjatuhkan form publik: jatuh kembali ke query langsung.
     */
    private function referenceList(string $name, callable $query): Collection
    {
        try {
            $cached = CacheHelper::remember('laporin:reference:'.$name, 3600, $query);

            if ($cached instanceof Collection) {
                return $cached;
            }
        } catch (Throwable $exception) {
            Log::warning('Gagal membaca cache data master wizard.', [
                'key' => 'laporin:reference:'.$name,
                'exception' => $exception::class,
            ]);
        }

        return $query();
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
        $submittedToken = (string) $request->input('report_submit_token', '');
        $legacySessionToken = session('report_submit_token');
        $formStates = session('report_submit_forms', []);

        if (! is_array($formStates)) {
            $formStates = [];
        }

        $formState = (
            $submittedToken !== ''
            && isset($formStates[$submittedToken])
            && is_array($formStates[$submittedToken])
        ) ? $formStates[$submittedToken] : null;

        $legacyMatches = (
            $submittedToken !== ''
            && is_string($legacySessionToken)
            && $legacySessionToken !== ''
            && hash_equals($legacySessionToken, $submittedToken)
        );

        if ($formState === null && ! $legacyMatches) {
            throw ValidationException::withMessages([
                'form' => 'Sesi formulir sudah habis atau laporan ini sudah pernah dikirim. Muat ulang formulir lalu coba kembali.',
            ]);
        }

        $validated = $request->validated();

        /*
         * Kuota bisnis: maksimal 5 laporan TERKIRIM per perangkat dalam 2 jam.
         *
         * Pemeriksaan di sini hanya MEMBACA penghitung. Kuota baru dipakai
         * (RateLimiter::hit) setelah laporan benar-benar tersimpan di bawah,
         * sehingga percobaan yang gagal validasi, salah CAPTCHA, atau dobel
         * submit tidak ikut menghabiskan kuota pelapor.
         *
         * Sengaja memakai identitas perangkat, bukan IP publik, karena satu IP
         * sekolah dipakai banyak siswa. Saat kuota habis pelapor menerima pesan
         * di dalam form dan situs tetap bisa diakses (melacak laporan lama,
         * membaca panduan), bukan dilempar ke halaman 429.
         */
        $deviceRateKey = $this->deviceRateKey($request);
        if (RateLimiter::tooManyAttempts($deviceRateKey, self::MAX_REPORTS_PER_WINDOW)) {
            throw ValidationException::withMessages([
                'form' => 'Batas pengiriman tercapai: maksimal 5 laporan per perangkat dalam 2 jam. Laporan yang sudah masuk tetap bisa dipantau di halaman Lacak Laporan.',
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

        /*
         * Jawaban CAPTCHA hanya boleh dibaca dari state form yang dibuat
         * server. Fallback ke session legacy dipakai hanya bila token yang
         * dikirim cocok dengan token session lama.
         */
        $expectedCaptcha = $formState['captcha_answer']
            ?? ($legacyMatches ? session('math_captcha_answer') : null);

        if (! is_numeric($expectedCaptcha) || (int) ($validated['captcha'] ?? -1) !== (int) $expectedCaptcha) {
            throw ValidationException::withMessages([
                'captcha' => 'CAPTCHA salah. Hitung ulang pertanyaan yang tampil lalu isi dengan angka yang benar.',
            ]);
        }

        $consumeKey = 'laporin:public-report:consume:'.hash('sha256', $submittedToken);
        if (! Cache::add($consumeKey, true, now()->addMinutes(30))) {
            throw ValidationException::withMessages([
                'form' => 'Laporan sedang atau sudah diproses. Jangan menekan tombol kirim berulang kali.',
            ]);
        }

        try {
            [$report, $accessCode, $notificationSent] = $this->service->create($request, $validated);
        } catch (Throwable $exception) {
            Cache::forget($consumeKey);

            throw $exception;
        }

        // Laporan benar-benar tersimpan: baru sekarang kuota perangkat dipakai.
        RateLimiter::hit($deviceRateKey, self::REPORT_WINDOW_SECONDS);

        unset($formStates[$submittedToken]);
        try {
            session(['report_submit_forms' => $formStates]);
        } catch (Throwable $exception) {
            // Aman untuk ditelan: laporan sudah tersimpan, dan idempotency
            // dijaga oleh consume key di Cache, bukan oleh session ini.
            // Sisa state form paling lama ikut terbuang oleh cutoff 30 menit.
        }

        if ($legacyMatches) {
            session()->forget(['math_captcha_answer', 'report_submit_token']);
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
            return redirect()->route('track.form')->withErrors([
                'report_number' => 'Halaman sukses hanya bisa dibuka setelah laporan berhasil dikirim. Untuk melihat laporan, masukkan nomor laporan dan kode akses.',
            ]);
        }

        // Deliberately not forgetting success_report_id: the access code is stored
        // only as a bcrypt hash, so dropping it after a single render meant one
        // refresh permanently locked the reporter out of their own report.
        // The session lifetime is the boundary instead.
        return view('public.success', ['report' => $report, 'accessCode' => session('access_code')]);
    }
    private function deviceRateKey(Request $request): string
    {
        $deviceId = (string) ($request->cookie('laporin_device_id') ?: ($request->ip() ?? 'unknown'));
        return 'laporin:public-reports:device:'.hash_hmac('sha256', $deviceId, config('app.key'));
    }

}

