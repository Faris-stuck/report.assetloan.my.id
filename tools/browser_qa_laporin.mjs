import playwright from '/tmp/laporin-browser-qa/node_modules/playwright-core/index.js';
import fs from 'node:fs/promises';

const { chromium } = playwright;

const base = (process.env.LAPORIN_BASE_URL || 'http://127.0.0.1:18080').replace(/\/$/, '');
const outputDir = process.env.LAPORIN_QA_OUTPUT || '/tmp/laporin-browser-qa/output';
await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || '/snap/bin/chromium',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

const results = { base, checks: [], consoleErrors: [], pageErrors: [], requestFailures: [], screenshots: [] };
const check = (name, condition, detail = null) => {
  const item = { name, ok: Boolean(condition) };
  if (detail !== null) item.detail = detail;
  results.checks.push(item);
  if (!item.ok) throw new Error(`${name}: ${JSON.stringify(detail)}`);
};

const attachDiagnostics = (page, label) => {
  page.on('console', (message) => {
    if (message.type() === 'error') results.consoleErrors.push({ label, text: message.text() });
  });
  page.on('pageerror', (error) => results.pageErrors.push({ label, text: error.message }));
  page.on('requestfailed', (request) => {
    results.requestFailures.push({ label, url: request.url(), error: request.failure()?.errorText || 'unknown' });
  });
};

try {
  const desktop = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'id-ID',
    timezoneId: 'Asia/Jakarta',
  });
  const page = await desktop.newPage();
  attachDiagnostics(page, 'desktop');

  await page.goto(`${base}/`, { waitUntil: 'networkidle' });
  await page.waitForFunction(() => Boolean(window.Alpine));
  check('homepage title contains LAPORIN', (await page.title()).includes('LAPORIN'), await page.title());
  check('homepage has one visible H1', await page.locator('h1:visible').count() === 1);
  check('desktop has no horizontal overflow', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), await page.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth })));

  const classGroups = await page.locator('#reporter_class_id optgroup').evaluateAll((groups) => groups.map((group) => ({
    label: group.label,
    count: group.querySelectorAll('option').length,
    first: group.querySelector('option')?.textContent?.trim() || '',
  })));
  const totalClassOptions = classGroups.reduce((sum, group) => sum + group.count, 0);
  check('class selector has four jurusan groups', classGroups.length === 4, classGroups);
  check('class groups ordered RPL, TKR, TITL, TAV', classGroups.map((group) => group.label.split(' ')[0]).join(',') === 'RPL,TKR,TITL,TAV', classGroups);
  check('all seeded classes appear in grouped selector', totalClassOptions === 120, { totalClassOptions, classGroups });

  const reporterClass = await page.locator('#reporter_class_id option[value]:not([value=""])').first().getAttribute('value');
  await page.fill('#reporter_name', 'QA Browser Pelapor');
  await page.selectOption('#reporter_class_id', reporterClass);
  await page.locator('.step-panel:visible button', { hasText: 'Berikutnya' }).click();
  check('step 2 opens from first next button', await page.locator('.step-panel:visible h2', { hasText: 'Jenis Laporan' }).count() === 1);

  await page.check('input[name="report_type"][value="violation"]');
  await page.locator('.step-panel:visible button', { hasText: 'Berikutnya' }).click();
  check('step 3 opens and violation detail is visible', await page.locator('.step-panel:visible h3', { hasText: 'Detail Pelanggaran' }).count() === 1);

  const location = await page.locator('#location_id option[value]:not([value=""])').first().getAttribute('value');
  const incidentDate = await page.locator('#incident_date').getAttribute('max');
  await page.fill('#title', 'QA_E2E_BROWSER_PUBLIC');
  await page.selectOption('#location_id', location);
  await page.fill('#incident_date', incidentDate);
  await page.fill('#description', 'Laporan QA browser lokal untuk memverifikasi alur formulir tanpa memengaruhi data produksi.');
  await page.fill('#victim_name', 'QA Browser Korban');
  await page.fill('#bullying_type', 'Verbal');
  await page.locator('.step-panel:visible button', { hasText: 'Berikutnya' }).click();
  check('step 4 opens', await page.locator('.step-panel:visible h2', { hasText: 'Urgensi' }).count() === 1);

  await page.check('#consent');
  const captchaText = await page.locator('label[for="captcha"]').innerText();
  const captcha = captchaText.match(/(\d+)\s*\+\s*(\d+)/);
  check('captcha question can be read', Boolean(captcha), captchaText);
  await page.fill('#captcha', String(Number(captcha[1]) + Number(captcha[2])));
  await Promise.all([
    page.waitForURL(/\/lapor-sukses\//),
    page.locator('.step-panel:visible button', { hasText: 'Kirim Laporan' }).click(),
  ]);
  check('valid browser form reaches success page', await page.getByRole('heading', { name: 'Laporan berhasil masuk ke sistem' }).count() === 1, page.url());
  const reportNumber = (await page.locator('.access-code-box').nth(0).locator('.h4').innerText()).trim();
  const accessCode = (await page.locator('.access-code-box').nth(1).locator('.h4').innerText()).trim();
  check('success report number format is correct', /^LPR\d{10}$/.test(reportNumber), reportNumber);
  check('success access code format is correct', /^\d{6}$/.test(accessCode), accessCode);

  await page.getByRole('link', { name: 'Lacak Status' }).click();
  await page.fill('#report_number', reportNumber);
  await page.fill('#access_code', accessCode);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.getByRole('button', { name: 'Lacak Laporan' }).click(),
  ]);
  check('tracking result renders browser-created report', (await page.locator('body').innerText()).includes(reportNumber));
  check('tracking result shows status', (await page.locator('body').innerText()).includes('Status laporan'));

  await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
  await page.fill('#login', 'admin@laporin.local');
  await page.fill('#password', 'password123');
  await Promise.all([
    page.waitForURL(/\/dashboard$/),
    page.getByRole('button', { name: 'Masuk' }).click(),
  ]);
  check('admin login reaches dashboard', page.url().endsWith('/dashboard'));
  check('dashboard cards expose users and QR actions', await page.getByRole('link', { name: /Akun Pengguna/ }).count() === 1 && await page.getByRole('link', { name: /Kode QR/ }).count() === 1);
  const dashboardShot = `${outputDir}/dashboard-desktop.png`;
  await page.screenshot({ path: dashboardShot, fullPage: true });
  results.screenshots.push(dashboardShot);
  await desktop.close();

  const mobile = await browser.newContext({
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 1,
    isMobile: true,
    hasTouch: true,
    locale: 'id-ID',
    timezoneId: 'Asia/Jakarta',
  });
  const mobilePage = await mobile.newPage();
  attachDiagnostics(mobilePage, 'mobile');
  await mobilePage.goto(`${base}/`, { waitUntil: 'networkidle' });
  await mobilePage.waitForFunction(() => Boolean(window.Alpine));
  check('mobile has no horizontal overflow', await mobilePage.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), await mobilePage.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, innerWidth: window.innerWidth })));
  const toggler = mobilePage.getByRole('button', { name: 'Buka navigasi' });
  check('mobile navigation toggler is visible', await toggler.isVisible());
  await toggler.click();
  check('mobile navigation opens', await mobilePage.locator('#mainNav').evaluate((element) => element.classList.contains('show')));
  check('mobile navigation exposes FAQ link', await mobilePage.getByRole('link', { name: 'FAQ' }).isVisible());
  await mobilePage.getByRole('link', { name: 'FAQ' }).click();
  check('FAQ navigation works on mobile', await mobilePage.getByRole('heading', { level: 1, name: /FAQ Lapor Perundungan/ }).count() === 1, mobilePage.url());
  const mobileShot = `${outputDir}/faq-mobile.png`;
  await mobilePage.screenshot({ path: mobileShot, fullPage: true });
  results.screenshots.push(mobileShot);
  await mobile.close();

  check('no uncaught JavaScript page errors', results.pageErrors.length === 0, results.pageErrors);
  const relevantConsoleErrors = results.consoleErrors.filter((item) => !item.text.includes('favicon'));
  check('no browser console errors', relevantConsoleErrors.length === 0, relevantConsoleErrors);
  const relevantFailures = results.requestFailures.filter((item) => new URL(item.url).origin === new URL(base).origin);
  check('no same-origin request failures', relevantFailures.length === 0, relevantFailures);
} catch (error) {
  results.fatal = error.stack || String(error);
} finally {
  results.totals = {
    checks: results.checks.length,
    passed: results.checks.filter((item) => item.ok).length,
    failed: results.checks.filter((item) => !item.ok).length + (results.fatal ? 1 : 0),
  };
  await browser.close();
  await fs.writeFile(`${outputDir}/summary.json`, JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results, null, 2));
}

if (results.fatal || results.totals.failed) process.exit(1);
