#!/usr/bin/env python3
import base64
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import time
from html import unescape
from urllib.parse import urljoin, urlparse, urldefrag

import requests

BASE = os.environ.get('LAPORIN_BASE_URL', 'https://report.assetloan.my.id').rstrip('/')
CONTAINER = os.environ.get('LAPORIN_CONTAINER', 'app')
PASSWORD = os.environ.get('LAPORIN_QA_PASSWORD') or f"QaE2E{secrets.token_hex(8)}7"
TIMEOUT = 30

summary = {
    'base': BASE,
    'setup': {},
    'checks': [],
    'errors': [],
    'crawl': {},
    'cleanup': None,
}

BAD_TEXT = [
    'Call to undefined method',
    'Undefined variable',
    'Trying to access array offset',
    'SQLSTATE[',
    'Stack trace:',
    'Illuminate\\Database\\QueryException',
]

PHP_BOOT = r'''
<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
'''

CLEANUP_PHP_BODY = r'''
use App\Models\{BullyingDetail,DamageCategory,DamageDetail,HomeroomClass,QrCode,Report,ReportAttachment,ReportNote,ReportStatusHistory,SchoolClass,StaffUnit,Student,StudentViolation,Subject,TeacherAssignment,User,ViolationType};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

DB::transaction(function () {
    $reportIds = Report::withTrashed()->where('title', 'like', 'QA_E2E_%')->pluck('id');
    foreach ($reportIds as $rid) { Storage::disk('private')->deleteDirectory('report-attachments/'.$rid); }
    if ($reportIds->isNotEmpty()) {
        ReportNote::whereIn('report_id', $reportIds)->delete();
        ReportStatusHistory::whereIn('report_id', $reportIds)->delete();
        ReportAttachment::whereIn('report_id', $reportIds)->delete();
        StudentViolation::whereIn('report_id', $reportIds)->delete();
        BullyingDetail::whereIn('report_id', $reportIds)->delete();
        DamageDetail::whereIn('report_id', $reportIds)->delete();
        Report::withTrashed()->whereIn('id', $reportIds)->forceDelete();
    }

    $qaUserIds = User::withTrashed()
        ->where(function ($query) {
            $query->where('email', 'like', 'qa-e2e-%@laporin.local')
                ->orWhere('email', 'qa-flow-created@example.test');
        })
        ->pluck('id');
    $qaClassIds = SchoolClass::where('class_name', 'like', 'QA_E2E_%')->pluck('id');
    $qaStudentIds = Student::where('nis', 'like', 'QA_E2E_%')->pluck('id');

    if ($qaStudentIds->isNotEmpty()) { StudentViolation::whereIn('student_id', $qaStudentIds)->delete(); }
    if ($qaUserIds->isNotEmpty()) {
        HomeroomClass::whereIn('homeroom_user_id', $qaUserIds)->delete();
        TeacherAssignment::whereIn('teacher_user_id', $qaUserIds)->delete();
        Student::whereIn('user_id', $qaUserIds)->delete();
        User::withTrashed()->whereIn('id', $qaUserIds)->forceDelete();
    }
    if ($qaClassIds->isNotEmpty()) {
        HomeroomClass::whereIn('class_id', $qaClassIds)->delete();
        TeacherAssignment::whereIn('class_id', $qaClassIds)->delete();
        Student::whereIn('class_id', $qaClassIds)->delete();
    }

    QrCode::where('qr_name', 'like', 'QA_E2E_%')->delete();
    Subject::where('subject_name', 'like', 'QA_E2E_%')->delete();
    StaffUnit::where('unit_name', 'like', 'QA_E2E_%')->delete();
    ViolationType::where('violation_name', 'like', 'QA_E2E_%')->delete();
    DamageCategory::where('category_name', 'like', 'QA_E2E_%')->delete();
    SchoolClass::where('class_name', 'like', 'QA_E2E_%')->delete();
});
'''

SETUP_PHP_BODY = CLEANUP_PHP_BODY + r'''
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$result = DB::transaction(function () {
    $password = getenv('LAPORIN_QA_PASSWORD');
    if (! is_string($password) || $password === '') {
        throw new RuntimeException('LAPORIN_QA_PASSWORD is required for QA setup.');
    }
    $roles = ['superadmin','kesiswaan','sarpras','wali_kelas','guru','siswa'];
    $users = [];
    foreach ($roles as $role) {
        $emailRole = str_replace('_', '-', $role);
        $user = new User();
        $user->forceFill([
            'name' => 'QA_E2E '.ucwords(str_replace('_',' ', $role)),
            'email' => 'qa-e2e-'.$emailRole.'@laporin.local',
            'password' => Hash::make($password),
            'role' => $role,
            'is_active' => true,
        ])->save();
        $users[$role] = $user;
    }

    $class = SchoolClass::create([
        'class_name' => 'QA_E2E_XI_AUDIT',
        'grade_level' => 'XI',
        'major' => 'QA',
        'academic_year' => '2026/2027',
        'room_name' => 'QA-E2E',
        'is_active' => true,
    ]);
    $subject = Subject::create(['subject_name' => 'QA_E2E_Subject', 'is_active' => true]);
    $staffUnit = StaffUnit::create(['unit_name' => 'QA_E2E_Staff_Unit', 'is_active' => true]);
    $violationType = ViolationType::create(['violation_name' => 'QA_E2E_Violation_Type', 'point_reduction' => 9, 'description' => 'QA E2E', 'created_by' => $users['superadmin']->id, 'is_active' => true]);
    $damageCategory = DamageCategory::create(['category_name' => 'QA_E2E_Damage_Category', 'is_active' => true]);
    $student = Student::create(['user_id' => $users['siswa']->id, 'nis' => 'QA_E2E_NIS_'.Str::upper(Str::random(6)), 'name' => 'QA_E2E Student', 'class_id' => $class->id, 'parent_phone' => '+6281234500000', 'point' => 100]);
    HomeroomClass::create(['homeroom_user_id' => $users['wali_kelas']->id, 'class_id' => $class->id, 'academic_year' => '2026/2027']);
    TeacherAssignment::create(['teacher_user_id' => $users['guru']->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'academic_year' => '2026/2027']);

    $qrIdentifier = 'qa-e2e-'.Str::lower(Str::random(8));
    $qr = QrCode::create([
        'qr_identifier' => $qrIdentifier,
        'qr_name' => 'QA_E2E_QR_SETUP',
        'qr_type' => 'general',
        'target_url' => route('public.report.qr', $qrIdentifier),
        'created_by' => $users['superadmin']->id,
        'is_active' => true,
    ]);

    $nextNumber = function () {
        do {
            $candidate = 'LPR'.now()->format('Ym').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Report::where('report_number', $candidate)->exists());
        return $candidate;
    };
    $makeReport = function (string $suffix, string $type, string $status = 'menunggu_verifikasi') use ($nextNumber, $class) {
        $report = Report::create([
            'report_number' => $nextNumber(),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'QA_E2E Reporter '.$suffix,
            'reporter_class_id' => $class->id,
            'report_type' => $type,
            'title' => 'QA_E2E_'.$suffix,
            'related_class_id' => $class->id,
            'incident_date' => now()->toDateString(),
            'description' => 'QA_E2E live flow report '.$suffix,
            'urgency' => 'sedang',
            'status' => $status,
            'assigned_to_role' => $type === 'violation' ? 'kesiswaan' : 'sarpras',
            'consent_accepted_at' => now(),
        ]);
        ReportStatusHistory::create(['report_id' => $report->id, 'actor_type' => 'reporter', 'new_status' => $status, 'public_note' => 'QA E2E setup.']);
        return $report;
    };

    $detail = $makeReport('DETAIL_ATTACHMENT', 'violation', 'sedang_ditangani');
    BullyingDetail::create(['report_id' => $detail->id, 'victim_name' => 'QA_E2E Victim', 'victim_class_id' => $class->id]);
    Storage::disk('private')->put('report-attachments/'.$detail->id.'/qa-e2e-proof.txt', 'QA_E2E proof attachment');
    $attachment = ReportAttachment::create([
        'report_id' => $detail->id,
        'uploader_type' => 'reporter',
        'original_name' => 'qa-e2e-proof.txt',
        'stored_name' => 'qa-e2e-proof.txt',
        'file_path' => 'report-attachments/'.$detail->id.'/qa-e2e-proof.txt',
        'mime_type' => 'text/plain',
        'file_size' => 24,
        'attachment_type' => 'initial_evidence',
    ]);

    $kProcess = $makeReport('KESISWAAN_PROCESS', 'violation');
    BullyingDetail::create(['report_id' => $kProcess->id, 'victim_name' => 'QA_E2E Process Victim', 'victim_class_id' => $class->id]);
    $kReject = $makeReport('KESISWAAN_REJECT', 'violation');
    BullyingDetail::create(['report_id' => $kReject->id, 'victim_name' => 'QA_E2E Reject Victim', 'victim_class_id' => $class->id]);
    $sProcess = $makeReport('SARPRAS_PROCESS', 'damage');
    DamageDetail::create(['report_id' => $sProcess->id, 'item_name' => 'QA_E2E Chair', 'damage_condition' => 'QA_E2E condition', 'priority' => 'sedang']);

    return [
        'users' => collect($users)->map(fn($u) => ['id' => $u->id, 'email' => $u->email, 'role' => $u->role])->all(),
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'staff_unit_id' => $staffUnit->id,
        'violation_type_id' => $violationType->id,
        'damage_category_id' => $damageCategory->id,
        'student_id' => $student->id,
        'qr_id' => $qr->id,
        'qr_identifier' => $qrIdentifier,
        'detail_report_id' => $detail->id,
        'attachment_id' => $attachment->id,
        'kesiswaan_process_report_id' => $kProcess->id,
        'kesiswaan_reject_report_id' => $kReject->id,
        'sarpras_process_report_id' => $sProcess->id,
        'direct_track_report_number' => $detail->report_number,
        'direct_track_access_code' => '123456',
    ];
});
echo json_encode($result, JSON_PRETTY_PRINT);
'''

CLEANUP_FULL_PHP = PHP_BOOT + CLEANUP_PHP_BODY + "\necho json_encode(['cleanup'=>'ok']);\n"
SETUP_FULL_PHP = PHP_BOOT + SETUP_PHP_BODY


def run_php(code: str, label: str):
    proc = subprocess.run(
        ['docker', 'exec', '-i', '-u', 'www-data', '-e', f'LAPORIN_QA_PASSWORD={PASSWORD}', CONTAINER, 'php'],
        input=code,
        text=True,
        capture_output=True,
        timeout=120,
    )
    if proc.returncode != 0:
        raise RuntimeError(f'{label} failed: {proc.stderr}\nSTDOUT={proc.stdout}')
    out = proc.stdout.strip()
    try:
        return json.loads(out or '{}')
    except json.JSONDecodeError as exc:
        raise RuntimeError(f'{label} returned non-json: {out[:1000]}') from exc


def record(name, ok, detail=None):
    item = {'name': name, 'ok': bool(ok)}
    if detail is not None:
        item['detail'] = detail
    summary['checks'].append(item)
    if not ok:
        summary['errors'].append(item)


def snippet(resp):
    text = resp.text if hasattr(resp, 'text') else ''
    return re.sub(r'\s+', ' ', text[:500])


def check_response(name, resp, allowed=(200,), must_contain=None, must_not_bad=True):
    ok = resp.status_code in allowed
    detail = {'status': resp.status_code, 'url': resp.url}
    if must_contain:
        missing = [m for m in must_contain if m not in resp.text]
        if missing:
            ok = False
            detail['missing'] = missing
    if must_not_bad and getattr(resp, 'text', None):
        hits = [b for b in BAD_TEXT if b in resp.text]
        if hits:
            ok = False
            detail['bad_text'] = hits
    if not ok:
        detail['snippet'] = snippet(resp)
    record(name, ok, detail)
    return ok


def csrf_from(html):
    # Laravel/Blade may emit input attributes in either order. Parse the
    # complete input element instead of depending on name/value ordering.
    for tag in re.findall(r'<input\b[^>]*>', html, flags=re.I | re.S):
        name = re.search(r'\bname\s*=\s*["\']_token["\']', tag, flags=re.I)
        value = re.search(r'\bvalue\s*=\s*["\']([^"\']+)["\']', tag, flags=re.I)
        if name and value:
            return unescape(value.group(1))

    # Some Laravel layouts expose the same token through the CSRF meta tag.
    meta = re.search(r'<meta\b[^>]*name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']', html, flags=re.I | re.S)
    if not meta:
        meta = re.search(r'<meta\b[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']csrf-token["\']', html, flags=re.I | re.S)
    if meta:
        return unescape(meta.group(1))

    if 'cf-chl-' in html or 'Just a moment...' in html or 'challenge-platform' in html:
        raise RuntimeError('CSRF token not found: Cloudflare challenge was returned. Run E2E against the application origin/internal base, or provide a Cloudflare test bypass.')
    title = re.search(r'<title[^>]*>(.*?)</title>', html, flags=re.I | re.S)
    title_text = re.sub(r'\s+', ' ', unescape(title.group(1))).strip() if title else 'unknown page'
    raise RuntimeError(f'CSRF token not found: response is not a Laravel form page (title={title_text!r})')


def captcha_answer(html):
    m = re.search(r'CAPTCHA.*?(\d+)\s*\+\s*(\d+)', html, flags=re.I | re.S)
    if not m:
        raise RuntimeError('captcha question not found')
    return str(int(m.group(1)) + int(m.group(2)))


def incident_date_from_form(html):
    match = re.search(r'name=["\']incident_date["\'][^>]*max=["\']([^"\']+)', html)
    if match:
        return unescape(match.group(1))
    # The max attribute is a UI hint, not the validation contract. Keep the
    # E2E test resilient if the markup changes while Laravel still enforces
    # before_or_equal:today on the server.
    return time.strftime('%Y-%m-%d')


def access_code_from_success(html):
    m = re.search(r'id=["\']access-code-value["\'][^>]*>(\d{6})<', html)
    if not m:
        m = re.search(r'\b(\d{6})\b', html)
    if not m:
        raise RuntimeError('access code not found in success page')
    return m.group(1)


def report_number_from_success(html):
    m = re.search(r'\b(LAP-[A-Z2-9]{6}-[A-Z2-9]{6}|LPR\d{10})\b', html)
    if not m:
        raise RuntimeError('report number not found in success page')
    return m.group(1)


def new_session():
    s = requests.Session()
    s.headers.update({
        'User-Agent': 'LAPORIN-QA-E2E/1.0',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    })
    # Browser-like navigation context. Laravel's back() validation responses
    # use Referer; requests does not add it automatically, so validation POSTs
    # could otherwise redirect back to the POST-only endpoint and produce 405.
    s._laporin_last_page = None
    return s


def _localize_redirect(location):
    if not location:
        return location
    target = urljoin(BASE + '/', location)
    target_parts = urlparse(target)
    base_parts = urlparse(BASE)
    # Origin/E2E runs may use an internal HTTP base while Laravel's APP_URL is
    # the public HTTPS domain. Keep redirects on the selected E2E origin.
    if target_parts.netloc and target_parts.netloc != base_parts.netloc:
        target = urljoin(BASE + '/', target_parts.path.lstrip('/'))
        if target_parts.query:
            target += '?' + target_parts.query
    elif target_parts.netloc == base_parts.netloc and target_parts.scheme != base_parts.scheme:
        target = base_parts.scheme + '://' + target_parts.netloc + target_parts.path
        if target_parts.query:
            target += '?' + target_parts.query
    return target


def _normalize_origin_cookies(s):
    # Production uses Secure cookies. When E2E intentionally targets the
    # internal HTTP origin, requests would otherwise keep the session cookie
    # but refuse to send it, making every wizard step look like a new session.
    if urlparse(BASE).scheme != 'https':
        for cookie in s.cookies:
            cookie.secure = False


def _request(s, method, path, data=None, files=None, **kw):
    follow = kw.pop('allow_redirects', True)
    url = urljoin(BASE + '/', path.lstrip('/'))
    url = _localize_redirect(url)
    headers = dict(kw.pop('headers', {}) or {})
    if method.upper() == 'POST' and s._laporin_last_page and 'Referer' not in headers:
        headers['Referer'] = s._laporin_last_page
    if headers:
        kw['headers'] = headers
    response = s.request(method, url, data=data or {}, files=files, timeout=TIMEOUT, allow_redirects=False, **kw)
    _normalize_origin_cookies(s)
    if not follow:
        return response
    for _ in range(8):
        if response.status_code not in (301, 302, 303, 307, 308):
            return response
        location = response.headers.get('Location')
        if not location:
            return response
        url = _localize_redirect(location)
        next_method = method
        # Browser semantics: Laravel commonly uses 302 for POST -> redirect
        # responses. Follow 301/302/303 as GET, while preserving the method for
        # explicit 307/308 redirects.
        if response.status_code in (301, 302, 303) and method.upper() != 'HEAD':
            next_method = 'GET'
            data = None
            files = None
        response = s.request(next_method, url, data=data or {}, files=files, timeout=TIMEOUT, allow_redirects=False, **kw)
        _normalize_origin_cookies(s)
        method = next_method
    return response


def get(s, path, **kw):
    response = _request(s, 'GET', path, **kw)
    if response.status_code < 500:
        s._laporin_last_page = response.url
    return response


def post(s, path, data=None, files=None, **kw):
    # Production deliberately limits public tracking. The regression suite
    # respects that control and, if another QA run has consumed the shared
    # IP budget, waits for the server-advertised retry window once.
    is_tracking = path == '/lacak' or path.startswith('/lacak/')
    if is_tracking:
        time.sleep(6.2)
    response = _request(s, 'POST', path, data=data, files=files, **kw)
    if is_tracking and response.status_code == 429:
        retry_after = response.headers.get('Retry-After')
        try:
            wait_seconds = max(1, int(retry_after)) + 1
        except (TypeError, ValueError):
            wait_seconds = 61
        time.sleep(wait_seconds)
        response = _request(s, 'POST', path, data=data, files=files, **kw)
    return response


def login(email):
    s = new_session()
    r = get(s, '/login')
    check_response(f'login page for {email}', r, must_contain=['Masuk Pengelola LAPORIN'])
    token = csrf_from(r.text)
    r = post(s, '/login', {'_token': token, 'login': email, 'password': PASSWORD}, allow_redirects=True)
    check_response(f'login submit {email}', r, must_contain=['Dasbor laporan'])
    return s


def internal_links(html):
    links = set()
    for href in re.findall(r'<a\s+[^>]*href=["\']([^"\']+)', html, flags=re.I):
        href = unescape(href)
        href, _ = urldefrag(href)
        if not href or href.startswith(('mailto:', 'tel:', 'javascript:')):
            continue
        absolute = urljoin(BASE + '/', href)
        p = urlparse(absolute)
        b = urlparse(BASE)
        if p.scheme in ('http', 'https') and p.netloc == b.netloc:
            path = p.path + (('?' + p.query) if p.query else '')
            if path.startswith('/cdn-cgi/'):
                continue
            links.add(path)
    return sorted(links)


def crawl_pages(label, session, paths):
    visited = set()
    queue = list(paths)
    results = []
    while queue and len(visited) < 80:
        path = queue.pop(0)
        if path in visited:
            continue
        visited.add(path)
        r = get(session, path, allow_redirects=True)
        ok = r.status_code < 500 and r.status_code not in (404,)
        if any(b in r.text for b in BAD_TEXT):
            ok = False
        results.append({'path': path, 'status': r.status_code, 'final': r.url, 'ok': ok})
        record(f'{label} GET {path}', ok, {'status': r.status_code, 'final': r.url} if ok else {'status': r.status_code, 'final': r.url, 'snippet': snippet(r)})
        ctype = r.headers.get('content-type','')
        if 'text/html' in ctype:
            for link in internal_links(r.text):
                # stay inside role-appropriate menus plus public/auth links; skip logout because POST-only.
                if link not in visited and len(queue) < 80:
                    if link.startswith(('/admin','/dashboard','/reports','/download-attachment','/kesiswaan','/sarpras','/siswa','/lacak','/lapor','/login','/')):
                        queue.append(link)
    summary['crawl'][label] = results


try:
    setup = run_php(SETUP_FULL_PHP, 'setup')
    summary['setup'] = setup
    raw_users = setup.get('users', {})
    users = {}
    if isinstance(raw_users, dict):
        for key, value in raw_users.items():
            if isinstance(value, dict):
                users[key] = value
                if value.get('role'):
                    users[value['role']] = value
    else:
        for value in raw_users:
            if isinstance(value, dict) and value.get('role'):
                users[value['role']] = value
    summary['setup_user_roles'] = sorted(users.keys())

    # Public pages and QR report form.
    public = new_session()
    public_paths = ['/', '/lapor', '/lacak', '/login', f"/lapor/{setup['qr_identifier']}"]
    crawl_pages('public', public, public_paths)

    # Public multi-page wizard regression: Step 1 -> Step 2 -> Step 3 -> Step 4.
    def wizard_page(session, step):
        r = get(session, f'/lapor/langkah/{step}', allow_redirects=True)
        check_response(f'public wizard step {step} GET', r, must_contain=[f'Langkah {step}'])
        return r

    def wizard_post(session, step, data, files=None):
        page = wizard_page(session, step)
        payload = dict(data)
        payload['_token'] = csrf_from(page.text)
        m = re.search(r'name=["\']report_submit_token["\'][^>]*value=["\']([^"\']+)', page.text)
        if not m:
            raise RuntimeError(f'wizard step {step}: report_submit_token missing')
        payload['report_submit_token'] = unescape(m.group(1))
        return post(session, f'/lapor/langkah/{step}', payload, files=files, allow_redirects=False)

    def advance_public_wizard(session, report_type='violation', captcha_override=None, title='QA_E2E_PUBLIC_VIOLATION'):
        r = get(session, '/lapor')
        check_response('public wizard landing initializes session', r, must_contain=['Langkah 1', 'Identitas Pelapor'])
        step1 = wizard_page(session, 1)
        record('step 1 has no reporter category selector', not any(x in step1.text for x in ['Siswa', 'Guru', 'Staf', 'reporter_type']), {'status': step1.status_code})
        r = wizard_post(session, 1, {'reporter_name':'QA_E2E Public Reporter','reporter_class_id':setup['class_id'],'reporter_absence_number':'19','reporter_phone':'081234500000','reporter_email':'qa-e2e-public@example.test'})
        record('public wizard step 1 -> step 2 redirect', r.status_code in (302,303) and '/lapor/langkah/2' in r.headers.get('location',''), {'status':r.status_code,'location':r.headers.get('location')})
        r = wizard_post(session, 2, {'report_type':report_type})
        record('public wizard step 2 -> step 3 redirect', r.status_code in (302,303) and '/lapor/langkah/3' in r.headers.get('location',''), {'status':r.status_code,'location':r.headers.get('location')})
        step3 = wizard_page(session, 3)
        details = {'title':title,'urgency':'sedang','incident_date':incident_date_from_form(step3.text)}
        if report_type == 'violation':
            details.update({'related_class_id':setup['class_id'],'alleged_actor_name':'QA_E2E Alleged Actor','description':'QA_E2E valid public violation report through the four-step wizard.'})
        else:
            details.update({'item_name':'QA_E2E Facility','damage_condition':'QA_E2E damage condition','description':'QA_E2E valid public damage report through the four-step wizard.'})
        r = wizard_post(session, 3, details)
        record('public wizard step 3 -> step 4 redirect', r.status_code in (302,303) and '/lapor/langkah/4' in r.headers.get('location',''), {'status':r.status_code,'location':r.headers.get('location')})
        step4 = wizard_page(session, 4)
        if 'captcha' not in step4.text.lower():
            Path('/tmp/laporin-e2e-step4.html').write_text(step4.text, encoding='utf-8')
            raise RuntimeError(f'Step 4 page missing CAPTCHA; status={step4.status_code} url={step4.url}')
        answer = captcha_override if captcha_override is not None else captcha_answer(step4.text)
        return wizard_post(session, 4, {'consent':'1','captcha':answer}), step4

    invalid = new_session()
    r, _ = advance_public_wizard(invalid, captcha_override='0', title='QA_E2E_PUBLIC_INVALID')
    check_response('public wizard invalid captcha redirects', r, allowed=(302,303), must_not_bad=True)
    record('invalid captcha redirects back to step 4', '/lapor/langkah/4' in r.headers.get('location',''), {'status':r.status_code,'location':r.headers.get('location')})

    public = new_session()
    r, _ = advance_public_wizard(public, report_type='violation', title='QA_E2E_PUBLIC_VIOLATION')
    check_response('public wizard valid final submit redirects', r, allowed=(302,303), must_not_bad=True)
    success_location = r.headers.get('location','')
    record('public wizard success redirect generated', '/lapor-sukses/' in success_location, {'status':r.status_code,'location':success_location})
    if not success_location:
        raise RuntimeError('Public wizard did not produce a success redirect')
    r = get(public, success_location, allow_redirects=True)
    check_response('public wizard valid submit success page', r, must_contain=['Laporan Berhasil Diterima','Kode Akses'])
    if 'LPR' not in r.text:
        Path('/tmp/laporin-e2e-success.html').write_text(r.text, encoding='utf-8')
    public_report_number = report_number_from_success(r.text)
    public_access_code = access_code_from_success(r.text)
    record('public report number format current', bool(re.match(r'^(?:LAP-[A-Z2-9]{6}-[A-Z2-9]{6}|LPR\d{10})$', public_report_number)), {'report_number':public_report_number})

    damage = new_session()
    r = get(damage, '/lapor')
    check_response('damage wizard landing', r, must_contain=['Langkah 1'])
    r = wizard_post(damage, 1, {'reporter_name':'QA_E2E Damage Reporter','reporter_class_id':setup['class_id'],'reporter_phone':'081234500001','reporter_email':'qa-e2e-damage@example.test'})
    record('damage wizard step 1 -> 2', r.status_code in (302,303) and '/lapor/langkah/2' in r.headers.get('location',''), {'status':r.status_code,'location':r.headers.get('location')})
    r = wizard_post(damage, 2, {'report_type':'damage'})
    record('damage wizard step 2 -> 3', r.status_code in (302,303) and '/lapor/langkah/3' in r.headers.get('location',''), {'status':r.status_code,'location':r.headers.get('location')})
    damage_step3 = wizard_page(damage, 3)
    record('damage step 3 renders damage fields', all(x in damage_step3.text for x in ['item_name','damage_condition','description_damage']), {'status':damage_step3.status_code})
    # The current Blade view keeps both field groups in the DOM for the wizard,
    # but disables/hides the inactive group. Test that UI contract instead of
    # incorrectly requiring the inactive field to be absent from HTML.
    marker = 'data-report-type-content="violation"'
    pos = damage_step3.text.find(marker)
    violation_group_hidden = pos >= 0 and 'd-none' in damage_step3.text[max(0, pos - 120):pos + 180] and 'disabled' in damage_step3.text[pos:pos + 300]
    record('damage step 3 hides and disables violation group', violation_group_hidden, {'status':damage_step3.status_code})

    # Track public valid, add info, confirm complete using a separate session.
    # Keeping the direct tracking flow in its own session also prevents the
    # production tracking rate limiter from counting unrelated tracking
    # searches against this state-transition regression.
    tracking = new_session()
    r = get(tracking, '/lacak')
    token = csrf_from(r.text)
    r = post(tracking, '/lacak', {'_token': token, 'report_number': public_report_number, 'access_code': public_access_code}, allow_redirects=True)
    check_response('tracking search valid public report', r, must_contain=[public_report_number, 'Status laporan'])

    run_php(PHP_BOOT + f"""
use App\\Models\\Report;
Report::findOrFail({setup['detail_report_id']})->update(['status'=>'memerlukan_informasi']);
echo json_encode(['ok'=>true]);
""", 'set detail memerlukan_informasi')
    r = get(tracking, '/lacak')
    token = csrf_from(r.text)
    r = post(tracking, '/lacak', {'_token': token, 'report_number': setup['direct_track_report_number'], 'access_code': setup['direct_track_access_code']}, allow_redirects=True)
    check_response('tracking memerlukan info renders add-info button', r, must_contain=['Tambahkan Informasi', setup['direct_track_report_number']])
    token = csrf_from(r.text)
    r = post(tracking, f"/lacak/{setup['detail_report_id']}/info", {'_token': token, 'note': 'QA_E2E tambahan info dari pelapor'}, allow_redirects=True)
    check_response('tracking add-info submit works', r, must_contain=['Informasi tambahan dikirim'])

    run_php(PHP_BOOT + f"""
use App\\Models\\Report;
Report::findOrFail({setup['detail_report_id']})->update(['status'=>'menunggu_konfirmasi']);
echo json_encode(['ok'=>true]);
""", 'set detail menunggu_konfirmasi')
    r = get(tracking, '/lacak')
    token = csrf_from(r.text)
    r = post(tracking, '/lacak', {'_token': token, 'report_number': setup['direct_track_report_number'], 'access_code': setup['direct_track_access_code']}, allow_redirects=True)
    check_response('tracking menunggu konfirmasi renders confirm button', r, must_contain=['Konfirmasi Selesai'])
    token = csrf_from(r.text)
    r = post(tracking, f"/lacak/{setup['detail_report_id']}/confirm", {'_token': token}, allow_redirects=True)
    check_response('tracking confirm complete works', r, must_contain=['selesai'])


    sessions = {
        'superadmin': login(users['superadmin']['email']),
        'kesiswaan': login(users['kesiswaan']['email']),
        'sarpras': login(users['sarpras']['email']),
        'wali_kelas': login(users['wali_kelas']['email']),
        'guru': login(users['guru']['email']),
        'siswa': login(users['siswa']['email']),
    }

    master_paths = ['/admin/master/classes','/admin/master/subjects','/admin/master/staff-units','/admin/master/violation-types','/admin/master/damage-categories']
    crawl_pages('superadmin', sessions['superadmin'], ['/dashboard','/admin/users','/admin/qrcodes','/admin/audit','/kesiswaan','/sarpras', f"/reports/{setup['detail_report_id']}", f"/download-attachment/{setup['attachment_id']}"] + master_paths)
    crawl_pages('kesiswaan', sessions['kesiswaan'], ['/dashboard','/kesiswaan', f"/reports/{setup['detail_report_id']}"])
    crawl_pages('sarpras', sessions['sarpras'], ['/dashboard','/sarpras'])
    crawl_pages('wali_kelas', sessions['wali_kelas'], ['/dashboard', f"/reports/{setup['detail_report_id']}"])
    crawl_pages('guru', sessions['guru'], ['/dashboard', f"/reports/{setup['detail_report_id']}"])
    crawl_pages('siswa', sessions['siswa'], ['/dashboard'])

    # Admin forms/buttons: user invalid/valid/update, QR invalid/valid/download/deactivate, master invalid/valid/update/delete.
    # Crawling many authenticated pages is intentionally isolated from the
    # mutation checks. Start a fresh authenticated session for admin actions
    # so a redirect encountered during the crawl cannot poison the form tests.
    admin = login(users['superadmin']['email'])
    r = get(admin, '/admin/users')
    token = csrf_from(r.text)
    r = post(admin, '/admin/users', {'_token': token, 'name': 'QA_E2E Weak User', 'email': 'qa-flow-created@example.test', 'password': 'weak', 'role': 'guru', 'is_active': '1'}, allow_redirects=True)
    check_response('admin user invalid password validation', r, allowed=(302,303), must_not_bad=True)
    # Validation responses are redirects. Re-fetch the form so the next POST
    # always uses a fresh CSRF token and the session's flashed validation state
    # cannot be mistaken for the form itself.
    r = get(admin, '/admin/users')
    check_response('admin user form available after validation redirect', r, must_contain=['Tambah User'])
    token = csrf_from(r.text)
    r = post(admin, '/admin/users', {'_token': token, 'name': 'QA_E2E Created User', 'email': 'qa-flow-created@example.test', 'password': PASSWORD, 'role': 'guru', 'phone': '+62812000000', 'is_active': '1'}, allow_redirects=True)
    check_response('admin user create button works', r, must_contain=['User dibuat'])

    # QR management currently supports only a general QR name. The previous
    # E2E assumed the old class QR API, so it was testing fields that
    # production no longer accepts. Validate the current contract instead.
    r = get(admin, '/admin/qrcodes')
    token = csrf_from(r.text)
    r = post(admin, '/admin/qrcodes', {'_token': token, 'qr_name': 'QA_E2E_QR_INVALID<>',}, allow_redirects=True)
    check_response('admin QR invalid name validation', r, must_contain=['Nama QR hanya boleh berisi'])

    r = get(admin, '/admin/qrcodes')
    token = csrf_from(r.text)
    r = post(admin, '/admin/qrcodes', {'_token': token, 'qr_name': 'QA_E2E_QR_HTTP'}, allow_redirects=True)
    check_response('admin QR create button works', r, must_contain=['QR umum berhasil dibuat', 'QA_E2E_QR_HTTP'])
    m = re.search(r'/admin/qrcodes/(\d+)/download', r.text)
    if m:
        qr_id = m.group(1)
        r = get(admin, f'/admin/qrcodes/{qr_id}/download')
        check_response('admin QR download SVG works', r, allowed=(200,), must_not_bad=False)
        record('admin QR download content type is SVG', 'image/svg+xml' in r.headers.get('content-type', '').lower(), {'content_type': r.headers.get('content-type')})
        r = get(admin, '/admin/qrcodes')
        token = csrf_from(r.text)
        r = post(admin, f'/admin/qrcodes/{qr_id}/deactivate', {'_token': token}, allow_redirects=True)
        check_response('admin QR deactivate button works', r, must_contain=['QR dinonaktifkan'])
    else:
        record('admin QR download link present after create', False, {'snippet': snippet(r)})

    master_payloads = {
        'classes': ({}, {'class_name':'QA_E2E_MASTER_CLASS','grade_level':'XII','major':'QA','academic_year':'2026/2027','room_name':'QA-M','is_active':'1'}),
        'subjects': ({}, {'subject_name':'QA_E2E_MASTER_SUBJECT','is_active':'1'}),
        'staff-units': ({}, {'unit_name':'QA_E2E_MASTER_STAFF','is_active':'1'}),
        'violation-types': ({}, {'violation_name':'QA_E2E_MASTER_VIOLATION','point_reduction':'5','description':'QA','is_active':'1'}),
        'damage-categories': ({}, {'category_name':'QA_E2E_MASTER_DAMAGE','is_active':'1'}),
    }
    required_markers = {
        'classes': 'class name', 'subjects': 'subject name', 'staff-units': 'unit name', 'violation-types': 'violation name', 'damage-categories': 'category name'
    }
    for resource, (_, payload) in master_payloads.items():
        r = get(admin, f'/admin/master/{resource}')
        token = csrf_from(r.text)
        # Validation responses are redirects. Do not follow the redirect here;
        # re-fetch the form explicitly so the flashed validation state cannot be
        # mistaken for the form page by the E2E parser.
        r = post(admin, f'/admin/master/{resource}', {'_token': token}, allow_redirects=False)
        record(f'admin master {resource} invalid validation redirect', r.status_code in (302,303), {'status': r.status_code, 'location': r.headers.get('Location')})
        r = get(admin, f'/admin/master/{resource}')
        check_response(f'admin master {resource} form after validation redirect', r, must_contain=[required_markers[resource]])
        token = csrf_from(r.text)
        data = {'_token': token, **payload}
        r = post(admin, f'/admin/master/{resource}', data, allow_redirects=True)
        check_response(f'admin master {resource} create button works', r, must_contain=['Data tersimpan'])
        # update/delete newest QA row by parsing action URL from page
        urls = re.findall(rf'/admin/master/{re.escape(resource)}/(\d+)', r.text)
        if urls:
            item_id = urls[0]
            token = csrf_from(r.text)
            update_payload = dict(payload)
            first_key = next(k for k in update_payload if k != 'is_active')
            if isinstance(update_payload[first_key], str):
                update_payload[first_key] = update_payload[first_key] + '_UPDATED'
            r = post(admin, f'/admin/master/{resource}/{item_id}', {'_token': token, '_method': 'PUT', **update_payload}, allow_redirects=True)
            check_response(f'admin master {resource} update button works', r, must_contain=['Data diperbarui'])
            token = csrf_from(r.text)
            r = post(admin, f'/admin/master/{resource}/{item_id}', {'_token': token, '_method': 'DELETE'}, allow_redirects=True)
            check_response(f'admin master {resource} delete button works', r, must_contain=['Data dihapus/nonaktif'])
        else:
            record(f'admin master {resource} update/delete link present', False, {'snippet': snippet(r)})

    # Kesiswaan process/reject buttons.
    k = sessions['kesiswaan']
    r = get(k, '/kesiswaan')
    check_response('kesiswaan page shows process and reject buttons', r, must_contain=['Proses', 'Tolak'])
    token = csrf_from(r.text)
    r = post(k, f"/kesiswaan/reports/{setup['kesiswaan_process_report_id']}/process", {'_token': token, 'student_id': setup['student_id'], 'violation_type_id': setup['violation_type_id'], 'note': 'QA_E2E process note'}, allow_redirects=True)
    check_response('kesiswaan process OK button works', r, must_contain=['Pelanggaran diproses'])
    r = get(k, '/kesiswaan')
    token = csrf_from(r.text)
    r = post(k, f"/kesiswaan/reports/{setup['kesiswaan_reject_report_id']}/reject", {'_token': token}, allow_redirects=True)
    check_response('kesiswaan reject missing reason validation', r, must_contain=['reason'])
    token = csrf_from(r.text)
    r = post(k, f"/kesiswaan/reports/{setup['kesiswaan_reject_report_id']}/reject", {'_token': token, 'reason': 'QA_E2E reject reason'}, allow_redirects=True)
    check_response('kesiswaan reject button works', r, must_not_bad=True)

    # Sarpras process: invalid priority, schedule, finish with photo upload.
    ssp = sessions['sarpras']
    r = get(ssp, '/sarpras')
    check_response('sarpras page shows save and photo inputs', r, must_contain=['Simpan', 'Foto setelah diperbaiki', 'Waktu Perbaikan'])
    token = csrf_from(r.text)
    r = post(ssp, f"/sarpras/reports/{setup['sarpras_process_report_id']}/process", {'_token': token, 'priority': 'invalid'}, allow_redirects=True)
    check_response('sarpras invalid priority validation', r, must_contain=['priority'])
    token = csrf_from(r.text)
    future = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(time.time() + 86400))
    r = post(ssp, f"/sarpras/reports/{setup['sarpras_process_report_id']}/process", {'_token': token, 'priority': 'tinggi', 'scheduled_repair_at': future, 'note': 'QA_E2E scheduled'}, allow_redirects=True)
    check_response('sarpras schedule button works', r, must_contain=['Laporan kerusakan diproses'])
    r = get(ssp, '/sarpras')
    token = csrf_from(r.text)
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
    r = post(ssp, f"/sarpras/reports/{setup['sarpras_process_report_id']}/process", {'_token': token, 'priority': 'tinggi', 'note': 'QA_E2E done'}, files={'repair_photo': ('repair.png', png, 'image/png')}, allow_redirects=True)
    check_response('sarpras finish with repair photo button works', r, must_contain=['Laporan kerusakan diproses'])

    # Detail note/download and role access. There is no /wali-confirm or
    # student PDF route in the current production route table, so the old E2E
    # checks for those endpoints were stale rather than production failures.
    run_php(PHP_BOOT + f"""
use App\\Models\\Report;
Report::findOrFail({setup['detail_report_id']})->update(['status'=>'sedang_ditangani']);
 echo json_encode(['ok'=>true]);
""", 'reset detail sedang_ditangani')
    admin = sessions['superadmin']
    r = get(admin, f"/reports/{setup['detail_report_id']}")
    check_response('report detail shows note and attachment buttons', r, must_contain=['Tambah Catatan', 'qa-e2e-proof.txt'])
    token = csrf_from(r.text)
    r = post(admin, f"/reports/{setup['detail_report_id']}/notes", {'_token': token, 'note': '', 'visibility': 'wrong'}, allow_redirects=True)
    check_response('report note invalid validation', r, must_contain=['note', 'visibility'])
    token = csrf_from(r.text)
    r = post(admin, f"/reports/{setup['detail_report_id']}/notes", {'_token': token, 'note': 'QA_E2E admin public note', 'visibility': 'reporter_visible'}, allow_redirects=True)
    check_response('report note submit button works', r, must_contain=['Catatan ditambahkan', 'QA_E2E admin public note'])
    r = get(admin, f"/download-attachment/{setup['attachment_id']}")
    check_response('attachment download button works', r, allowed=(200,), must_not_bad=False)

    w = sessions['wali_kelas']
    r = get(w, f"/reports/{setup['detail_report_id']}")
    check_response('wali can open report detail', r, must_contain=['QA_E2E_DETAIL_ATTACHMENT'])

    # Current role protection.
    r = get(sessions['siswa'], '/admin/users', allow_redirects=False)
    record('siswa blocked from admin users', r.status_code == 403, {'status': r.status_code})
    r = get(sessions['kesiswaan'], '/sarpras', allow_redirects=False)
    record('kesiswaan blocked from sarpras menu', r.status_code == 403, {'status': r.status_code})
    r = get(new_session(), '/dashboard', allow_redirects=False)
    record('guest dashboard redirects to login', r.status_code in (302,303) and '/login' in r.headers.get('location',''), {'status': r.status_code, 'location': r.headers.get('location')})

    # Logout button.
    r = get(sessions['guru'], '/dashboard')
    token = csrf_from(r.text)
    r = post(sessions['guru'], '/logout', {'_token': token}, allow_redirects=True)
    ok_logout = r.status_code == 200 and ('Login' in r.text or 'Laporkan' in r.text or 'Kirim Laporan' in r.text)
    record('logout button works', ok_logout, {'status': r.status_code, 'final': r.url})

finally:
    try:
        cleanup = run_php(CLEANUP_FULL_PHP, 'cleanup')
        summary['cleanup'] = cleanup
    except Exception as exc:
        summary['cleanup'] = {'error': str(exc)}
        summary['errors'].append({'name': 'cleanup', 'ok': False, 'detail': str(exc)})

summary['totals'] = {
    'checks': len(summary['checks']),
    'passed': sum(1 for c in summary['checks'] if c['ok']),
    'failed': sum(1 for c in summary['checks'] if not c['ok']),
}
print(json.dumps(summary, indent=2, ensure_ascii=False))
if summary['totals']['failed']:
    sys.exit(1)
