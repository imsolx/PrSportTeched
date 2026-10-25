// Real PHP/API/SQLite regression: classic script loading, route bases and event ownership.
const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdtemp, writeFile, rm } = require('node:fs/promises');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = await mkdtemp(path.join(os.tmpdir(), 'prsport-scoring-modules-'));
  const db = path.join(artifacts, 'test.sqlite');
  const router = path.join(artifacts, 'router.php');
  // Also mount the real application under /prsport to exercise the XAMPP base path.
  // No response fixtures: every PHP request executes the actual application.
  await writeFile(router, `<?php
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$relative = preg_replace('#^/prsport(?=/|$)#', '', $uri);
$file = realpath($_SERVER['DOCUMENT_ROOT'] . ($relative === '/' ? '/index.php' : $relative));
$root = realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR;
if (!$file || !str_starts_with($file, $root) || !is_file($file)) { http_response_code(404); return; }
if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['SCRIPT_NAME'] = $uri === '/' || $uri === '/prsport/' ? $uri . 'index.php' : $uri;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    require $file;
} else {
    $types = ['js'=>'application/javascript', 'css'=>'text/css', 'svg'=>'image/svg+xml', 'png'=>'image/png', 'ttf'=>'font/ttf'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file);
}
`);
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => {
      const value = socket.address().port;
      socket.close(() => resolve(value));
    });
  });
  const server = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['-d', 'display_errors=1', '-S', `127.0.0.1:${port}`, '-t', root, router], {
    cwd: root, env: { ...process.env, PRSPORT_DB_DSN: `sqlite:${db}` }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'],
  });
  const phpErrors = [];
  server.stderr.on('data', data => {
    for (const line of data.toString().split(/\r?\n/)) {
      if (/PHP (Warning|Notice|Deprecated|Fatal|Parse)|Uncaught/.test(line)) phpErrors.push(line);
    }
  });
  let browser;
  try {
    const base = `http://127.0.0.1:${port}`;
    for (let i = 0; i < 50; i++) {
      if (await fetch(base).then(r => r.ok).catch(() => false)) break;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const cdp = await context.newCDPSession(page);
    const errors = [];
    const fontFailures = new Set();
    const calls = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
    });
    page.on('request', request => {
      const action = new URL(request.url()).searchParams.get('action');
      if (action) calls.push(action);
    });
    page.on('response', response => {
      if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`);
    });
    page.on('requestfailed', request => {
      const url = new URL(request.url());
      if (['fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname)) {
        fontFailures.add(`${request.failure()?.errorText} ${url.hostname}`);
      } else errors.push(`${request.failure()?.errorText} ${url}`);
    });
    const count = action => calls.filter(value => value === action).length;
    async function listeners(selector, type) {
      const { result } = await cdp.send('Runtime.evaluate', { expression: `document.querySelector(${JSON.stringify(selector)})` });
      expect(result.objectId, selector).toBeTruthy();
      try {
        const { listeners } = await cdp.send('DOMDebugger.getEventListeners', { objectId: result.objectId });
        return listeners.filter(listener => listener.type === type).length;
      } finally { await cdp.send('Runtime.releaseObject', { objectId: result.objectId }); }
    }
    await page.goto(base);
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    const fixture = await page.evaluate(async () => {
      const result = {};
      for (const type of ['SCORE', 'SET', 'TIME']) {
        const sport = await request('sports.save', { name: `Module test ${type}`, type: 'team', gender: 'open', min_players: 1, max_players: 2, icon: 'general', scoring_type: type });
        const tournament = await request('tournaments.save', { name: `Module tournament ${type}`, sport_ids: [sport.id], format: 'round_robin' });
        const home = await request('teams.save', { name: `${type} Home`, tournament_id: tournament.id, sport_id: sport.id, department_code: '130' });
        const away = await request('teams.save', { name: `${type} Away`, tournament_id: tournament.id, sport_id: sport.id, department_code: '140' });
        result[type] = await request('matches.save', { tournament_id: tournament.id, sport_id: sport.id, team1_id: home.id, team2_id: away.id, round_name: `${type} module round` });
      }
      return result;
    });

    for (const prefix of ['', '/prsport']) {
      for (const entry of ['/', '/public/index.php']) {
        await page.goto(`${base}${prefix}${entry}`);
        await expect(page.locator('#view-public')).toBeVisible();
        const modules = await page.locator('script[src]').evaluateAll(scripts => scripts.map(script => new URL(script.src).pathname).filter(url => /\/scoring\/(scoring-config|match-selector)\.js$/.test(url)));
        expect(modules).toEqual([`${prefix}/public/assets/scoring/scoring-config.js`, `${prefix}/public/assets/scoring/match-selector.js`]);
        await page.evaluate(() => showView('public-sports'));
        await expect(page.locator('#public-sports-table img').first()).toBeVisible();
        await expect.poll(() => page.locator('#public-sports-table img').evaluateAll(images => images.every(img => img.complete && img.naturalWidth > 0))).toBe(true);
        const styles = await page.evaluate(async () => {
          await document.fonts.load('16px Sarabun', 'กีฬา');
          return { family: getComputedStyle(document.body).fontFamily, localFont: document.fonts.check('16px Sarabun', 'กีฬา') };
        });
        expect(styles.family).toContain('Sarabun');
        expect(styles.localFont).toBe(true);
        if (!prefix && entry === '/public/index.php') {
          await page.screenshot({ path: path.join(artifacts, 'public-entry-sports.png'), fullPage: true });
        }
        await page.evaluate(() => showView('official'));
        await expect(page.locator(`[data-official-match="${fixture.SCORE.id}"]`)).toBeVisible();
        await page.evaluate(() => { bindScoringSelector(); bindScoringSelector(); bindScoringConfig(); bindScoringConfig(); renderOfficialMatchLists(); renderOfficialMatchLists(); });
        expect(await listeners('#official-refresh', 'click')).toBe(1);
        expect(await listeners('#official-back-button', 'click')).toBe(1);
        expect(await listeners('#scoring-type', 'change')).toBe(1);
        expect(await listeners(`[data-official-match="${fixture.TIME.id}"]`, 'click')).toBe(1);
        const beforeRefresh = count('matches.list');
        await page.locator('#official-refresh').click();
        await page.waitForLoadState('networkidle');
        expect(count('matches.list') - beforeRefresh).toBe(1);
        expect(await listeners(`[data-official-match="${fixture.TIME.id}"]`, 'click')).toBe(1);
        await page.locator(`[data-official-match="${fixture.TIME.id}"]`).click();
        await expect(page.locator('#toast')).toContainText('หน้าบันทึก TIME ยังอยู่ระหว่างจัดทำ');
        for (const type of ['SCORE', 'SET']) {
          const mode = type.toLowerCase();
          await page.locator(`[data-official-match="${fixture[type].id}"]`).click();
          await expect(page).toHaveURL(`${base}${prefix}/public/scoring/${mode}.php?match_id=${fixture[type].id}`);
          await expect(page.locator(`#${mode}-workspace`)).toBeVisible();
          await expect(page.locator('script[src*="match-selector.js"], script[src*="scoring-config.js"], script[src*="/assets/app.js"]')).toHaveCount(0);
          expect(await listeners(`#${mode}-match-select`, 'change')).toBe(1);
          // Native match picker search/selection and reload use the real persisted match.
          await page.locator(`#${mode}-match-select`).selectOption('');
          await expect(page.locator(`#${mode}-workspace`)).toBeHidden();
          const beforeLoad = count('matches.center');
          await page.locator(`#${mode}-match-select`).selectOption(String(fixture[type].id));
          await expect(page.locator(`#${mode}-workspace`)).toBeVisible();
          await page.waitForLoadState('networkidle');
          expect(count('matches.center') - beforeLoad).toBe(1);
          await page.reload();
          await expect(page.locator(`#${mode}-match-select`)).toHaveValue(String(fixture[type].id));
          await expect(page.locator(`#${mode}-workspace`)).toBeVisible();
          await page.locator('.back-link').click();
          await expect(page.locator('#view-official')).toBeVisible();
        }
      }
    }

    await page.goto(`${base}/public/scoring/score.php?match_id=${fixture.SCORE.id}`);
    let before = count('matches.clock');
    await page.locator('#score-clock-start').click();
    await expect(page.locator('#score-status')).toContainText('กำลังแข่งขัน');
    expect(count('matches.clock') - before).toBe(1);
    expect(await listeners('[data-score-side=home]', 'click')).toBe(1);
    before = count('matches.event');
    await page.locator('[data-score-side=home]').click();
    await expect(page.locator('#score-team1-value')).toHaveText('1');
    await page.waitForLoadState('networkidle');
    expect(count('matches.event') - before).toBe(1);
    await page.reload();
    await expect(page.locator('#score-team1-value')).toHaveText('1');
    await page.locator('.correction-panel summary').click();
    await page.locator('#score-correct-team1').fill('0');
    before = count('matches.scoreCorrection');
    await page.locator('[data-correct-side=home]').click();
    await expect(page.locator('#score-toast')).toContainText('กรุณาระบุเหตุผล');
    expect(count('matches.scoreCorrection')).toBe(before);
    await page.locator('#score-correct-team1-reason').fill('Module regression correction');
    await page.locator('[data-correct-side=home]').click();
    await expect(page.locator('#score-team1-value')).toHaveText('0');
    await page.waitForLoadState('networkidle');
    expect(count('matches.scoreCorrection') - before).toBe(1);
    await page.screenshot({ path: path.join(artifacts, 'score-desktop.png'), fullPage: true });
    before = count('matches.result');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#score-finish').click();
    await expect(page.locator('#score-status')).toContainText('จบแล้ว');
    await page.waitForLoadState('networkidle');
    expect(count('matches.result') - before).toBe(1);
    await page.reload();
    await expect(page.locator('#score-status')).toContainText('จบแล้ว');

    await page.goto(`${base}/public/scoring/set.php?match_id=${fixture.SET.id}`);
    before = count('matches.clock');
    const beforeDraft = count('matches.recordingDraft');
    await page.locator('#set-start').click();
    await expect(page.locator('#set-toast')).toContainText('เปิดเซต 1');
    expect(count('matches.clock') - before).toBe(1);
    expect(count('matches.recordingDraft') - beforeDraft).toBe(1);
    expect(await listeners('[data-save-set="0"]', 'click')).toBe(1);
    before = count('matches.recordingDraft');
    await page.locator('[data-point-side=home]').click();
    await expect(page.locator('[data-set-row="0"] [data-set-side=home]')).toHaveValue('1');
    await page.waitForLoadState('networkidle');
    expect(count('matches.recordingDraft') - before).toBe(1);
    await page.locator('[data-set-row="0"] [data-set-side=home]').fill('0');
    before = count('matches.recordingDraft');
    await page.locator('[data-save-set="0"]').click();
    await expect(page.locator('#set-toast')).toContainText('บันทึกเซต 1 แล้ว');
    await page.waitForLoadState('networkidle');
    expect(count('matches.recordingDraft') - before).toBe(1);
    expect(await listeners('[data-save-set="0"]', 'click')).toBe(1);
    await page.reload();
    await expect(page.locator('[data-set-row="0"] [data-set-side=home]')).toHaveValue('0');
    await expect(page.locator('#set-finish')).toBeDisabled();
    for (let index = 0; index < 2; index++) {
      if (index) {
        before = count('matches.recordingDraft');
        await page.locator('#set-add').click();
        await expect(page.locator(`[data-set-row="${index}"]`)).toBeVisible();
        await page.waitForLoadState('networkidle');
        expect(count('matches.recordingDraft') - before).toBe(1);
      }
      await page.locator(`[data-set-row="${index}"] [data-set-side=home]`).fill('21');
      before = count('matches.recordingDraft');
      await page.locator(`[data-save-set="${index}"]`).click();
      await expect(page.locator('#set-team1-wins')).toHaveText(String(index + 1));
      await page.waitForLoadState('networkidle');
      expect(count('matches.recordingDraft') - before).toBe(1);
    }
    before = count('matches.result');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#set-finish').click();
    await expect(page.locator('#set-status')).toContainText('จบแล้ว');
    await page.waitForLoadState('networkidle');
    expect(count('matches.result') - before).toBe(1);
    await page.reload();
    await expect(page.locator('#set-status')).toContainText('จบแล้ว');
    await expect(page.locator('#set-team1-wins')).toHaveText('2');
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.screenshot({ path: path.join(artifacts, 'set-mobile.png'), fullPage: true });
    expect(errors).toEqual([]);
    expect(phpErrors).toEqual([]);
    console.log('PASS root/subdirectory + normal/public entries, images, local font, selector refresh, legacy mode blocking, SCORE/SET selection/reload, validation, start/finish, one API write and one listener per action.');
    console.log('Google Fonts network observations:', [...fontFailures]);
    console.log(`Screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    const exited = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await exited;
    await rm(db, { force: true });
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
