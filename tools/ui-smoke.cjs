const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdir, rm, readFile } = require('node:fs/promises');
const path = require('node:path');
const net = require('node:net');
const { runNewsUxFlow } = require('./news-ux-ui-test.cjs');

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = process.env.PRSPORT_UI_ARTIFACTS || path.join(root, 'storage', 'ui-checks');
  const newsBaselineCss = process.env.PRSPORT_NEWS_BASELINE
    ? await readFile(path.join(process.env.PRSPORT_NEWS_BASELINE, 'public/assets/workspace.css'), 'utf8') : null;
  let newsVisualChecks = 0;
  let newsRasterOnlyDifferences = 0;
  await mkdir(artifacts, { recursive: true });
  const db = path.join(artifacts, `test-${Date.now()}.sqlite`);
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => { const port = socket.address().port; socket.close(() => resolve(port)); });
  });
  const server = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['-d', `upload_tmp_dir=${artifacts}`, '-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', root], {
    cwd: root, env: { ...process.env, PRSPORT_DB_DSN: `sqlite:${db}`, GEMINI_API_KEY: '', GEMINI_MODEL: 'local-summary' }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'],
  });
  const phpErrors = [];
  server.stderr.on('data', chunk => {
    for (const line of chunk.toString().split(/\r?\n/)) {
      if (/PHP (?:Warning|Notice|Deprecated|Fatal error|Parse error)|Uncaught /i.test(line)) phpErrors.push(line);
    }
  });
  let browser;
  const generatedUploads = [];
  try {
    const base = `http://127.0.0.1:${port}`;
    const upcomingDay = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
    const upcomingMatchAt = `${upcomingDay}T18:30`;
    const secondUpcomingMatchAt = `${upcomingDay}T19:00`;
    for (let attempt = 0; attempt < 50; attempt++) {
      if (await fetch(base).then(r => r.ok).catch(() => false)) break;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    const consoleErrors = [];
    const actionRequests = new Map();
    page.on('request', request => {
      const action = new URL(request.url()).searchParams.get('action');
      if (action) actionRequests.set(action, (actionRequests.get(action) || 0) + 1);
    });
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) {
        consoleErrors.push(message.text());
      }
    });
    const capture = async options => {
      await expect(page.locator('#page-progress')).toBeHidden();
      await page.evaluate(() => {
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
        document.scrollingElement.scrollTop = 0;
        window.scrollTo(0, 0);
      });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      const screenshotOptions = { ...options, animations: 'disabled', style: '#toast { visibility: hidden; }' };
      const actual = await page.screenshot(screenshotOptions);
      if (newsBaselineCss && /^(?:dashboard-desktop|sports-390|tournament-dashboard-mobile)/.test(path.basename(options.path))) {
        const layoutSnapshot = () => [...document.querySelectorAll('body *')]
          .filter(element => element.getClientRects().length && !element.closest('#toast'))
          .map(element => {
            const rect = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            const properties = [...style].sort().map(key => [key, style.getPropertyValue(key)]);
            const backdrop = element.tagName === 'DIALOG' ? getComputedStyle(element, '::backdrop') : null;
            return [element.tagName, element.id, rect.x, rect.y, rect.width, rect.height, properties,
              backdrop ? [...backdrop].sort().map(key => [key, backdrop.getPropertyValue(key)]) : null];
          });
        const actualLayout = await page.evaluate(layoutSnapshot);
        // Compare all original computed styles above, including shadows/blur. Only normalize those
        // GPU effects for the paired raster captures; the user-facing screenshot keeps the real UI.
        const { path: actualPath, ...comparisonOptions } = screenshotOptions;
        comparisonOptions.style += '\n*, *::before, *::after, dialog::backdrop { backdrop-filter: none !important; filter: none !important; box-shadow: none !important; text-shadow: none !important; }';
        const comparableActual = await page.screenshot(comparisonOptions);
        await page.evaluate(css => {
          const sheet = document.querySelector('link[href*="/assets/workspace.css"]');
          const original = document.createElement('style');
          original.id = 'news-refactor-baseline-style';
          original.textContent = css.replace(/url\((['"]?)([^'"\)]+)\1\)/g, (_, quote, url) =>
            `url("${new URL(url, sheet.href).href}")`);
          sheet.after(original);
          sheet.disabled = true;
          document.querySelectorAll('link[href*="/modules/news/"]').forEach(link => link.disabled = true);
        }, newsBaselineCss);
        try {
          const baseline = await page.screenshot({ ...comparisonOptions, path: options.path.replace(/\.png$/, '-baseline-css.png') });
          expect(await page.evaluate(layoutSnapshot), `Unchanged layout/styles: ${path.basename(options.path)}`).toEqual(actualLayout);
          if (!baseline.equals(comparableActual)) {
            // Chromium may rasterize unchanged blur/anti-aliasing up to two channel levels differently
            // after stylesheet invalidation. Compare pixels, not PNG encoding, with a strict noise cap.
            const difference = await page.evaluate(async buffers => {
              const load = data => new Promise((resolve, reject) => {
                const image = new Image(); image.onload = () => resolve(image); image.onerror = reject;
                image.src = `data:image/png;base64,${data}`;
              });
              const [actualImage, baselineImage] = await Promise.all(buffers.map(load));
              const canvas = document.createElement('canvas');
              canvas.width = actualImage.width; canvas.height = actualImage.height;
              const context = canvas.getContext('2d', { willReadFrequently: true });
              context.drawImage(actualImage, 0, 0);
              const a = context.getImageData(0, 0, canvas.width, canvas.height).data;
              context.clearRect(0, 0, canvas.width, canvas.height);
              context.drawImage(baselineImage, 0, 0);
              const b = context.getImageData(0, 0, canvas.width, canvas.height).data;
              let maxDelta = 0, pixels = 0;
              for (let i = 0; i < a.length; i += 4) {
                let delta = 0;
                for (let channel = 0; channel < 4; channel++) delta = Math.max(delta, Math.abs(a[i + channel] - b[i + channel]));
                if (delta) pixels++;
                maxDelta = Math.max(maxDelta, delta);
              }
              return { maxDelta, pixels, total: canvas.width * canvas.height };
            }, [comparableActual.toString('base64'), baseline.toString('base64')]);
            expect(difference.maxDelta, 'No visible color changes').toBeLessThanOrEqual(2);
            expect(difference.pixels, 'Only minor blur rasterization noise').toBeLessThanOrEqual(difference.total * 0.005);
            newsRasterOnlyDifferences++;
          }
          newsVisualChecks++;
        } finally {
          await page.evaluate(() => {
            document.getElementById('news-refactor-baseline-style')?.remove();
            document.querySelector('link[href*="/assets/workspace.css"]').disabled = false;
            document.querySelectorAll('link[href*="/modules/news/"]').forEach(link => link.disabled = false);
          });
        }
      }
    };
    await page.goto(base);
    await page.waitForFunction(() => document.querySelectorAll('.editor-dialog').length >= 10);
    await expect(page.locator('#page-progress')).toBeHidden();
    await page.evaluate(() => document.fonts.ready);
    await expect(page.locator('#view-public')).toBeVisible();
    await expect(page).not.toHaveURL(/(?:\?|&)view=/);
    await page.goto(`${base}/?view=reports`);
    await expect(page.locator('#view-public')).toBeVisible();
    await expect(page).not.toHaveURL(/(?:\?|&)view=reports/);
    await page.locator('.tab[data-view-button="public-news"]').click();
    await expect(page).toHaveURL(/view=public-news/);
    await page.reload();
    await expect(page.locator('#view-public-news')).toBeVisible();
    await page.locator('.tab[data-view-button="public"]').click();
    await expect(page).not.toHaveURL(/(?:\?|&)view=/);
    await page.reload();
    await expect(page.locator('#view-public')).toBeVisible();
    await capture({ path: path.join(artifacts, 'public-desktop.png'), fullPage: true });
    await page.locator('.news-read').first().click();
    await expect(page).toHaveURL(/view=public-news-detail&id=1/);
    await expect(page.locator('#view-public-news-detail')).toBeVisible();
    await expect(page.locator('#view-public-news-detail #article-title')).not.toHaveText('');
    await expect(page.locator('#view-public-news-detail .article-publish-meta')).toBeVisible();
    await expect(page.locator('#view-public-news-detail .article-body p')).not.toHaveCount(0);
    await expect(page.locator('#view-public-news-detail .article-source-link')).toHaveAttribute('href', 'https://www.rmutt.ac.th/');
    await capture({ path: path.join(artifacts, 'news-article-desktop.png') });
    await page.reload();
    await expect(page.locator('#view-public-news-detail #article-title')).toHaveText('เปิดระบบ Sport Tournament');
    await page.locator('.article-back').click();
    await expect(page).toHaveURL(/view=public-news/);
    await expect(page.locator('#view-public-news')).toBeVisible();
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('incorrect-password');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#login-form .form-error')).toBeVisible();
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    await expect(page.locator('#page-progress')).toBeHidden();
    await expect(page.locator('[data-view-button="reports"]')).toHaveCount(0);
    await expect(page.locator('#view-reports')).toHaveCount(0);
    await expect(page.locator('[data-workflow-view="reports"]')).toHaveCount(0);
    await expect(page.locator('#role-switch-button')).toBeVisible();
    await page.locator('#role-switch-button').click();
    await expect(page.locator('#role-switch-dialog')).toBeVisible();
    await expect(page.locator('[data-presentation-role]')).toHaveCount(5);
    await expect(page.locator('[data-presentation-role="registration_officer"]')).toHaveCount(0);
    await capture({ path: path.join(artifacts, 'role-switcher-desktop.png'), fullPage: false });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#role-switch-dialog')).toBeVisible();
    await capture({ path: path.join(artifacts, 'role-switcher-mobile.png'), fullPage: false });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('[data-presentation-role="result_officer"]').click();
    await expect(page.locator('#role-preview-bar')).toBeVisible();
    await expect(page.locator('#role-preview-label')).toHaveText('Match Official');
    await expect(page.locator('#view-official')).toBeVisible();
    await expect(page.locator('.admin-link[data-view-button="official"]')).toBeVisible();
    await expect(page.locator('.admin-link[data-view-button="users"]')).toBeHidden();
    await expect.poll(() => page.locator('.admin-link:visible').evaluateAll(nodes => nodes.map(node => node.dataset.viewButton))).toEqual(['official']);
    await capture({ path: path.join(artifacts, 'role-preview-match-official-desktop.png'), fullPage: false });
    const roleMenuMatrix = {
      admin: ['dashboard', 'sports', 'students', 'matches', 'official', 'news'],
      sport_head: ['dashboard', 'matches', 'official'],
      public_relations: ['dashboard', 'news'],
    };
    await page.evaluate(async () => {
      const sport = (await request('sports.list'))[0];
      await request('tournaments.save', { name: 'Role navigation tournament', sport_ids: [sport.id], format: 'knockout', status: 'draft' });
    });
    for (const [role, visibleViews] of Object.entries(roleMenuMatrix)) {
      await page.locator('#role-switch-button').click();
      await page.locator(`[data-presentation-role="${role}"]`).click();
      await expect(page.locator('#page-progress')).toBeHidden();
      await expect.poll(() => page.locator('.admin-link:visible').evaluateAll(nodes => nodes.map(node => node.dataset.viewButton))).toEqual(visibleViews);
      if (role === 'sport_head') {
        await page.locator('[data-view-button="matches"]').click();
        await expect(page.locator('#view-matches')).toBeVisible();
        await expect(page.locator('#tournament-list-page')).toBeVisible();
        await expect(page.locator('[data-workflow-step-button]')).toHaveCount(0);
        await page.locator('[data-open-tournament]').first().click();
        for (const section of ['overview', 'sports', 'athletes', 'teams', 'schedule', 'results']) {
          await page.locator(`[data-tournament-section="${section}"]`).click();
          await expect(page.locator('#tournament-dashboard-page')).toHaveAttribute('data-section', section);
        }
        await expect(page.locator('#tournament-edit-button')).toBeHidden();
        await expect(page.locator('#fixture-generator')).toBeHidden();
        await expect(page.locator('[data-workflow-view="news"]')).toHaveCount(0);
        await capture({ path: path.join(artifacts, 'sport-manager-workflow-desktop.png'), fullPage: false });
        await page.setViewportSize({ width: 390, height: 844 });
        await capture({ path: path.join(artifacts, 'sport-manager-workflow-mobile.png'), fullPage: false });
        await page.setViewportSize({ width: 1440, height: 1000 });
        await expect(page.locator('#tournament-record-results')).toBeVisible();
      }
    }
    await page.locator('#role-switch-button').click();
    await page.locator('[data-presentation-role="super_admin"]').click();
    await expect(page.locator('#role-preview-bar')).toBeHidden();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    await expect(page.locator('.admin-link[data-view-button="users"]')).toBeVisible();
    await capture({ path: path.join(artifacts, 'dashboard-desktop.png'), fullPage: true });
    await page.locator('#dashboard-ai-button').click();
    await expect(page.locator('#dashboard-ai-output .ai-meta')).toContainText('local-summary');

    const go = async name => {
      if (await page.locator('#menu-toggle').isVisible()) await page.locator('#menu-toggle').click();
      await page.locator(`.tab[data-view-button="${name}"]`).click();
      await expect(page.locator(`#view-${name}`)).toBeVisible();
      await expect(page.locator('#page-progress')).toBeHidden();
    };
    const selectStudentScope = async () => {
      if (!await page.locator('#student-scope-faculty').inputValue()) {
        await page.locator('#student-scope-faculty').selectOption({ index: 1 });
      }
      await expect(page.locator('#student-scope-department')).toBeEnabled();
      if (!await page.locator('#student-scope-department').inputValue()) {
        await page.locator('#student-scope-department').selectOption({ index: 1 });
      }
      await expect(page.locator('#student-scope-sport')).toBeEnabled();
      if (!await page.locator('#student-scope-sport').inputValue()) {
        await page.locator('#student-scope-sport').selectOption({ index: 1 });
      }
      await expect(page.locator('#student-scope')).toHaveClass(/complete/);
    };
    const noOverflow = async () => expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await go('sports');
    await expect(page).toHaveURL(/view=sports/);
    await page.reload();
    await expect(page.locator('#view-sports')).toBeVisible();
    await expect(page).toHaveURL(/view=sports/);
    await expect(page.locator('#sports-table [aria-label="ลบ"] svg').first()).toBeVisible();
    await page.getByRole('button', { name: 'เพิ่มกีฬา', exact: true }).click();
    await expect(page.locator('#sport-icon-picker .sport-icon-choice img')).toHaveCount(13);
    await page.locator('#sport-icon-picker [data-sport-icon="basketball"]').click();
    await expect(page.locator('#sport-form [name=icon]')).toHaveValue('basketball');
    await page.locator('#sport-form [name=name]').fill('UI Test Sport');
    await page.locator('#sport-scoring-type').selectOption('SCORE');
    await page.keyboard.press('Escape');
    await expect(page.locator('.confirm-dialog')).toBeVisible();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'กลับ', exact: true }).click();
    await expect(page.locator('#sport-form [name=name]')).toHaveValue('UI Test Sport');
    await expect(page.locator('#sport-icon-picker [data-sport-icon="basketball"]')).toHaveAttribute('aria-pressed', 'true');
    await capture({ path: path.join(artifacts, 'sport-modal-desktop.png') });
    await page.route('**/api.php?action=sports.save', route => route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ ok: false, error: 'Test validation error' }) }));
    await page.locator('#sport-form [type=submit]').click();
    await expect(page.locator('#sport-form .form-error')).toContainText('Test validation error');
    await expect(page.locator('#sport-form [name=name]')).toHaveValue('UI Test Sport');
    await page.unroute('**/api.php?action=sports.save');
    await page.locator('#sport-form [type=submit]').click();
    await expect(page.locator('#sport-form-dialog')).toBeHidden();
    const search = page.locator('#sports-table input[type=search]');
    await search.fill('UI Test Sport');
    await expect(page.locator('#sports-table tbody tr')).toHaveCount(1);
    await page.locator('#sports-table').getByRole('button', { name: 'แก้ไข', exact: true }).click();
    await expect(page.locator('#sport-form [name=name]')).toHaveValue('UI Test Sport');
    await expect(page.locator('#sport-form [name=icon]')).toHaveValue('basketball');
    await expect(page.locator('#sport-scoring-type')).toBeEnabled();
    await page.locator('#sport-scoring-type').selectOption('SET');
    await page.locator('#sport-form [name=name]').fill('UI Test Sport Edited');
    await page.locator('#sport-form [type=submit]').click();
    await expect(page.locator('#sport-form-dialog')).toBeHidden();
    await expect(page.locator('#sports-table tbody')).toContainText('UI Test Sport Edited');
    await page.locator('#sports-table').getByRole('button', { name: 'แก้ไข', exact: true }).click();
    await expect(page.locator('#sport-scoring-type')).toHaveValue('SET');
    await expect(page.locator('#sport-scoring-help')).toContainText('แมตช์เดิมยังใช้กติกาเดิม');
    await page.locator('#sport-form-dialog .modal-head .icon-button').click();
    await page.getByRole('button', { name: 'เพิ่มกีฬา', exact: true }).click();
    await page.locator('#sport-form [name=name]').fill('UI Test Sport Edited');
    await page.locator('#sport-scoring-type').selectOption('SCORE');
    await page.locator('#sport-form [type=submit]').click();
    await expect(page.locator('#sport-form .form-error')).toContainText('ชื่อกีฬานี้มีอยู่แล้ว');
    await page.keyboard.press('Escape');
    await page.locator('.confirm-dialog').getByRole('button', { name: 'กลับ', exact: true }).click();
    await page.locator('#sport-form-dialog .modal-head .icon-button').click();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'ยกเลิกการแก้ไข', exact: true }).click();
    await expect(page.locator('#sport-form-dialog')).toBeHidden();
    await page.getByRole('button', { name: 'เพิ่มกีฬา', exact: true }).click();
    await expect(page.locator('#sport-form [name=id]')).toHaveValue('');
    await expect(page.locator('#sport-form [name=name]')).toHaveValue('');
    await page.keyboard.press('Escape');
    await expect(page.locator('#sport-form-dialog')).toBeHidden();
    await page.locator('#sports-table').getByRole('button', { name: 'ลบ', exact: true }).click();
    await expect(page.locator('.confirm-dialog')).toBeVisible();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'กลับ', exact: true }).click();
    await expect(page.locator('#sports-table tbody tr')).toHaveCount(1);
    await page.locator('#sports-table').getByRole('button', { name: 'ลบ', exact: true }).click();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'ลบรายการ', exact: true }).click();
    await expect(page.locator('#sports-table .empty-state')).toBeVisible();

    // Seed only the disposable test database to exercise pagination.
    await page.evaluate(async () => {
      for (let i = 1; i <= 12; i++) await request('sports.save', { name: `Pagination ${i}`, type: 'team', gender: 'mixed', min_players: 1, max_players: 5, reserve_players: 0, scoring_type: 'SCORE' });
    });
    await go('sports');
    await search.fill('');
    await expect(page.locator('#sports-table tbody tr')).toHaveCount(10);
    await page.locator('#sports-table').getByRole('button', { name: 'หน้าถัดไป', exact: true }).click();
    await expect(page.locator('#sports-table tbody tr')).toHaveCount(5);
    await search.fill('does not exist');
    await expect(page.locator('#sports-table .empty-state')).toBeVisible();
    await search.fill('');
    await capture({ path: path.join(artifacts, 'sports-desktop.png'), fullPage: true });

    // Creation has its own short wizard; existing tournaments open directly.
    await go('matches');
    await expect(page.locator('#tournament-list-page')).toBeVisible();
    await expect(page.locator('#tournament-dashboard-page')).toBeHidden();
    await page.getByRole('button', { name: 'เพิ่มรายการแข่งขัน', exact: true }).click();
    await expect(page.locator('#tournament-create-page')).toBeVisible();
    await expect(page.locator('#tournament-form-dialog')).toBeHidden();
    await expect(page.locator('[data-create-indicator]')).toHaveCount(3);
    await page.locator('#tournament-create-next').click();
    await expect(page.locator('[data-create-step="1"]')).toBeVisible();
    await page.locator('#tournament-form [name=name]').fill('UI Draft Tournament');
    await page.locator('#tournament-form [name=academic_year]').fill('2569');
    await page.locator('#tournament-create-next').click();
    await expect(page.locator('[data-create-step="2"]')).toBeVisible();
    await page.locator('#tournament-create-next').click();
    await expect(page.locator('#tournament-form .form-error')).toContainText('เลือกกีฬา');
    await page.locator('#tournament-sports-toggle').click();
    await page.locator('#tournament-form input[name="sport_ids[]"]').nth(0).check();
    await page.locator('#tournament-form input[name="sport_ids[]"]').nth(1).check();
    await page.locator('#tournament-create-next').click();
    await expect(page.locator('#tournament-create-review')).toContainText('UI Draft Tournament');
    await capture({ path: path.join(artifacts, 'tournament-create-desktop.png') });
    await page.setViewportSize({ width: 390, height: 844 });
    await noOverflow();
    await expect(page.locator('#tournament-form [type=submit]')).toBeVisible();
    await capture({ path: path.join(artifacts, 'tournament-create-mobile.png'), fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });
    const draftResponse = page.waitForResponse(response => response.url().includes('action=tournaments.save'));
    await page.locator('#tournament-form [type=submit]').click();
    const draft = (await (await draftResponse).json()).data;
    expect(draft.status).toBe('draft');
    expect(draft.sport_ids).toHaveLength(2);
    await expect(page.locator('#tournament-dashboard-page')).toBeVisible();
    await expect(page.locator('#tournament-create-page')).toBeHidden();
    await expect(page.locator('#workflow-tournament-name')).toHaveText('UI Draft Tournament');
    await expect(page.locator('#tournament-setup-status')).toHaveText('ยังเตรียมข้อมูลไม่ครบ');
    await expect(page.locator('#workflow-tournament-status')).toHaveText('ฉบับร่าง');
    await expect(page.locator('#tournament-publication-status')).toHaveText('ยังไม่มีผลการแข่งขัน');
    await expect(page).toHaveURL(new RegExp('tournament=' + draft.id));
    await page.reload();
    await expect(page.locator('#workflow-tournament-name')).toHaveText('UI Draft Tournament');
    await expect(page.locator('#tournament-dashboard-page')).toBeVisible();
    await page.locator('[data-tournament-section="teams"]').click();
    await page.locator('[data-tournament-section="sports"]').click();
    await page.goBack();
    await expect(page.locator('#tournament-dashboard-page')).toHaveAttribute('data-section', 'teams');
    await expect(page.locator('#page-progress')).toBeHidden();
    await page.goForward();
    await expect(page.locator('#tournament-dashboard-page')).toHaveAttribute('data-section', 'sports');
    await expect(page.locator('#page-progress')).toBeHidden();
    await page.locator('[data-tournament-section="schedule"]').click();
    await page.locator('#fixture-sport-select').selectOption(String(draft.sport_ids[0]));
    await expect(page.locator('#generate-fixtures-button')).toBeDisabled();
    await expect(page.locator('#fixture-requirements')).toContainText('อย่างน้อย 2 ทีม');
    await page.locator('[data-tournament-section="teams"]').click();
    await expect(page.locator('#teams-table')).toBeVisible();
    await expect(page.locator('#members-table')).toBeVisible();
    await page.locator('#tournament-back-to-list').click();
    await expect(page.locator('#tournament-list-page')).toBeVisible();
    const draftRow = page.locator('#tournaments-table tbody tr', { hasText: 'UI Draft Tournament' });
    await expect(draftRow).toContainText('2 กีฬา');
    await expect(draftRow).toContainText('0 ทีม');
    await capture({ path: path.join(artifacts, 'tournament-list-desktop.png'), fullPage: true });
    await draftRow.getByRole('button', { name: 'จัดการ', exact: true }).click();
    await expect(page.locator('#tournament-dashboard-page')).toHaveAttribute('data-section', 'overview');
    await expect(page.locator('[data-workflow-step-button], #workflow-publish-section')).toHaveCount(0);
    await capture({ path: path.join(artifacts, 'tournament-dashboard-desktop.png') });
    await page.setViewportSize({ width: 390, height: 844 });
    await noOverflow();
    await capture({ path: path.join(artifacts, 'tournament-dashboard-mobile.png'), fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });

    const sections = {
      sports: [{ id: 'organization-form' }, { id: 'sport-rule-form' }],
      matches: [
        { id: 'student-form', step: 5 },
        { id: 'team-form', step: 6 }, { id: 'member-form', step: 7 }, { id: 'match-form', step: 9 },
      ],
      users: [{ id: 'user-form' }],
    };
    for (const [view, forms] of Object.entries(sections)) {
      await go(view);
      await noOverflow();
      for (const item of forms) {
        const { id, step } = item;
        if (step) await page.evaluate(step => setWorkflowStep(step, false), step);
        // The public command opens each editor; assertions inspect the resulting dialog.
        const heading = step
          ? page.locator(`#view-${view} [data-workflow-step~="${step}"] .data-heading`)
          : page.locator(`#view-${view} .data-heading`);
        const label = await page.evaluate(id => workspace.forms[id][0], id);
        if (id === 'student-form') {
          await expect(heading.getByRole('button', { name: `เพิ่ม${label}`, exact: true })).toBeEnabled();
          await expect(page.locator('#student-scope-department')).toBeDisabled();
          await selectStudentScope();
          await expect(heading.getByRole('button', { name: `เพิ่ม${label}`, exact: true })).toBeEnabled();
          await capture({ path: path.join(artifacts, 'student-scope-desktop.png'), fullPage: true });
        }
        await heading.getByRole('button', { name: `เพิ่ม${label}`, exact: true }).click();
        await expect(page.locator(`#${id}-dialog`)).toBeVisible();
        if (id === 'sport-rule-form') {
          await expect(page.locator('#scoring-type > option')).toHaveCount(12);
          await page.locator('#scoring-type').selectOption('SET');
          await expect(page.locator('#sport-rule-form [name=best_of]')).toBeVisible();
          await expect(page.locator('#scoring-config-json')).toBeVisible();
          await page.locator('#scoring-type').selectOption('SCORE');
        }
        await page.keyboard.press('Escape');
        await expect(page.locator(`#${id}-dialog`)).toBeHidden();
      }
    }
    await go('users');
    await expect(page.locator('.tab[data-view-button="tasks"]')).toHaveCount(0);
    await expect(page.locator('#user-role-select option[value="viewer"]')).toHaveCount(0);
    await expect(page.locator('#invite-role-select option[value="viewer"]')).toHaveCount(0);
    await expect(page.locator('#user-role-select option[value="registration_officer"]')).toHaveCount(0);
    await expect(page.locator('#invite-role-select option[value="registration_officer"]')).toHaveCount(0);
    await page.locator('[data-admin-tab="settings"]').click();
    await expect(page.locator('#staff-invites-enabled')).toBeChecked();
    await page.locator('#admin-audit-details > summary').click();
    await page.locator('#audit-search').fill('login_failed');
    await expect(page.locator('#audit-full-table')).toContainText('login_failed');
    await page.locator('#staff-invites-enabled').uncheck();
    await page.locator('#admin-invite-settings-form [type=submit]').click();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'ปิดใช้คำเชิญ', exact: true }).click();
    await expect.poll(async () => page.evaluate(async () => (await request('users.options')).staff_invites_enabled)).toBe(false);
    await page.locator('#staff-invites-enabled').check();
    await page.locator('#admin-invite-settings-form [type=submit]').click();
    await expect.poll(async () => page.evaluate(async () => (await request('users.options')).staff_invites_enabled)).toBe(true);
    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#view-matches')).toBeVisible();
    await expect(page.locator('#page-progress')).toBeHidden();
    await page.evaluate(() => setWorkflowStep(5, false));
    await selectStudentScope();
    await page.getByRole('button', { name: 'นำเข้า CSV', exact: true }).click();
    await expect(page.locator('.import-dialog[open]')).toBeVisible();
    await page.keyboard.press('Escape');
    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#view-matches')).toBeVisible();
    await page.evaluate(() => setWorkflowStep(5, false));
    await selectStudentScope();
    await page.locator('[data-workflow-step~="5"] .data-heading').getByRole('button', { name: 'เพิ่มนักกีฬา', exact: true }).click();
    await page.locator('#student-form [name=student_code]').fill('UI-ATHLETE-01');
    await page.locator('#student-form [name=first_name]').fill('กิตติพงษ์');
    await page.locator('#student-form [name=last_name]').fill('ทดสอบระบบ');
    await expect(page.locator('#student-form [name=department_code]')).toHaveValue(await page.locator('#student-scope-department').inputValue());
    await expect(page.locator('#student-photo-file')).toBeHidden();
    await page.locator('#student-form [type=submit]').click();
    await expect(page.locator('#student-form-dialog')).toBeHidden();
    await expect(page.locator('#students-table tbody')).toContainText('กิตติพงษ์');
    await expect(page.locator('#students-table tbody')).not.toContainText('<img');

    const fixture = await page.evaluate(async () => {
      const sport = (await request('sports.list'))[0];
      const allSports = await request('sports.list');
      const department = (await request('departments.list'))[0];
      const multi = await request('tournaments.save', { name: 'Multi Sport UI Tournament', sport_ids: allSports.slice(0, 2).map(row => row.id), format: 'knockout', status: 'registration' });
      if (!Array.isArray(multi.sport_ids) || multi.sport_ids.length !== 2) throw new Error('multi-sport tournament create failed');
      const edited = await request('tournaments.save', { id: multi.id, sport_ids: allSports.slice(0, 1).map(row => row.id), name: multi.name, format: multi.format, status: multi.status });
      if (!Array.isArray(edited.sport_ids) || edited.sport_ids.length !== 1) throw new Error('multi-sport tournament edit failed');
      const tournament = await request('tournaments.save', { name: 'UI Tournament · กีฬาสี RMUTT Sport Demo 2569 - ทุกกีฬา (37380)', sport_id: sport.id, format: 'knockout', status: 'registration' });
      const teams = [];
      for (const [index, name] of [
        'UI Team A · ฟุตซอล สาขาวิชานวัตกรรมการเรียนรู้และเทคโนโลยีสารสนเทศ',
        'UI Team B · ฟุตซอล สาขาวิชาวิศวกรรมคอมพิวเตอร์',
      ].entries()) {
        teams.push(await request('teams.save', { name, tournament_id: tournament.id, department_code: department.code, logo_url: `/missing-team-logo-${index + 1}.png` }));
      }
      const athlete = (await request('students.list', { q: 'UI-ATHLETE-01' }))[0];
      const defender = await request('students.save', { first_name: 'ปกรณ์', last_name: 'แนวรับ', department_code: department.code });
      const coach = await request('students.save', { first_name: 'สมชาย', last_name: 'ผู้ฝึกสอน', department_code: department.code });
      await request('teamMembers.save', { team_id: teams[0].id, student_id: athlete.id, role: 'captain', shirt_number: '10', field_position: 'กองหน้า' });
      await request('teamMembers.save', { team_id: teams[0].id, student_id: defender.id, role: 'player', shirt_number: '4', field_position: 'กองหลัง' });
      await request('teamMembers.save', { team_id: teams[0].id, student_id: coach.id, role: 'coach', field_position: '' });
      return { tournament, teams, multi, athlete };
    });
    const draw = await page.evaluate(async () => {
      const sport = (await request('sports.list'))[0];
      const otherSport = (await request('sports.list')).find(row => String(row.id) !== String(sport.id));
      const tournament = await request('tournaments.save', { name: 'Open Draw UI', sport_ids: [sport.id, otherSport.id], format: 'group', status: 'ready' });
      for (const name of ['Open A', 'Open B', 'Open C', 'Open D', 'Open E', 'Open F', 'Open G', 'Open H']) {
        await request('teams.save', { name, tournament_id: tournament.id, sport_id: sport.id });
      }
      await loadMatchesAdmin();
      return { tournament, sportId: sport.id, otherSportId: otherSport.id };
    });
    await page.locator('#workflow-tournament-select').selectOption(String(draw.tournament.id));
    await page.evaluate(() => setWorkflowStep(9, false));
    await page.locator('#fixture-sport-select').selectOption(String(draw.sportId));
    await page.locator('#generate-fixtures-button').click();
    await expect.poll(async () => page.evaluate(async id => (await request('matches.list', { tournament_id: id })).length, draw.tournament.id)).toBe(12);
    await page.evaluate(() => showView('public'));
    await page.evaluate(() => showView('public-bracket'));
    await expect(page.locator('#bracket-sport-filter')).toBeDisabled();
    await page.locator('#bracket-tournament-filter').selectOption(String(draw.tournament.id));
    await expect(page.locator('#bracket-sport-filter')).toBeEnabled();
    await expect(page.locator('#bracket-sport-filter option')).toHaveCount(3);
    await expect(page.locator('#public-bracket-rounds')).toContainText('เลือกกีฬาที่ต้องการดูก่อน');
    await page.locator('#bracket-sport-filter').selectOption(String(draw.otherSportId));
    await expect(page.locator('#public-bracket-rounds')).toContainText('ยังไม่มีสายการแข่งขันสำหรับกีฬานี้');
    await page.locator('#bracket-sport-filter').selectOption(String(draw.sportId));
    await expect(page.locator('#public-bracket-rounds')).toContainText('Open A');
    await expect(page.locator('#public-bracket-rounds .bracket-group')).toHaveCount(2);
    await expect(page.locator('#public-bracket-rounds .bracket-match')).toHaveCount(0);
    await expect(page.locator('#public-bracket-rounds .bracket-group-team-list li')).toHaveCount(8);
    await capture({ path: path.join(artifacts, 'public-bracket-desktop.png'), fullPage: true });
    await page.evaluate(() => showView('public-athletes'));
    await expect(page.locator('#view-public-athletes')).toBeVisible();
    await expect(page.locator('#athlete-department-filter')).toBeDisabled();
    await page.locator('#athlete-faculty-filter').selectOption(String(fixture.tournament.id));
    await expect(page.locator('#athlete-department-filter')).toBeEnabled();
    await page.locator('#athlete-department-filter').selectOption(String(fixture.teams[0].sport_id));
    await expect(page.locator('#athlete-sport-filter')).toBeEnabled();
    await page.locator('#athlete-sport-filter').selectOption(String(fixture.teams[0].id));
    await expect(page.locator('#athlete-results')).toBeVisible();
    await expect(page.locator('[data-athlete-flow-step="4"]')).toHaveClass(/active/);
    await expect(page.locator('.athlete-profile-photo, .athlete-player-visual, .athlete-player-card')).toHaveCount(0);
    await expect(page.locator('.athlete-position-zone')).toHaveCount(3);
    await expect(page.locator('.athlete-position-zone').nth(0)).toContainText('โค้ช');
    await expect(page.locator('.athlete-position-zone')).toContainText(['โค้ช', 'กองหน้า', 'กองหลัง']);
    await expect(page.locator('.athlete-name-row')).toHaveCount(3);
    await expect(page.locator('#athlete-board-list')).toContainText('กิตติพงษ์');
    await expect(page.locator('#athlete-board-list')).toContainText('สมชาย');
    await capture({ path: path.join(artifacts, 'public-athletes-flow-desktop.png'), fullPage: true });
    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#view-matches')).toBeVisible();
    await expect(page.locator('#page-progress')).toBeHidden();
    await expect.poll(() => page.evaluate(() => Boolean(document.querySelector('link[href*="workspace.css?v="]')))).toBe(true);
    await expect(page.locator('#workflow-steps')).toHaveCount(0);
    await page.evaluate(id => openTournamentDashboard(id), String(fixture.multi.id));
    await expect(page.locator('#workflow-tournament-name')).toContainText('Multi Sport UI Tournament');
    await page.evaluate(() => setWorkflowStep(2, false));
    await expect(page.locator('#workflow-step-title')).toHaveText('กีฬาและกติกา');
    await page.locator('[data-tournament-section="results"]').click();
    await expect(page.locator('#workflow-step-title')).toHaveText('ผลการแข่งขัน');
    await expect(page.locator('#tournament-tools [aria-current="page"]')).toHaveText('ผลการแข่งขัน');
    await page.locator('[data-tournament-section="sports"]').click();
    await expect(page.locator('#workflow-step-title')).toHaveText('กีฬาและกติกา');
    await capture({ path: path.join(artifacts, 'tournament-workflow-desktop.png'), fullPage: true });
    await page.evaluate(() => setWorkflowStep(1, false));
    const multiTournamentRow = page.locator('#tournaments-table tbody tr', { hasText: 'Multi Sport UI Tournament' });
    await multiTournamentRow.getByRole('button', { name: 'แก้ไข', exact: true }).click();
    await capture({ path: path.join(artifacts, 'tournament-modal-desktop.png') });
    await expect(page.locator('#tournament-form input[name="sport_ids[]"]:checked')).toHaveCount(1);
    await page.locator('#tournament-sports-toggle').click();
    await page.locator('#tournament-sport-type').selectOption('individual');
    await expect(page.locator('#tournament-sports-options .sport-choice:not([hidden])')).not.toHaveCount(0);
    await page.locator('#tournament-sport-type').selectOption('');
    await page.locator('#tournament-form input[name="sport_ids[]"]').nth(1).check();
    await expect(page.locator('#tournament-form input[name="sport_ids[]"]:checked')).toHaveCount(2);
    const tournamentRequest = page.waitForRequest(request => request.url().includes('action=tournaments.save'));
    const tournamentResponse = page.waitForResponse(response => response.url().includes('action=tournaments.save'));
    await page.locator('#tournament-form [type=submit]').click();
    const tournamentPayload = (await tournamentRequest).postDataJSON();
    const tournamentResult = await (await tournamentResponse).json();
    if (new Set(tournamentPayload.sport_ids || []).size !== 2) throw new Error(`tournament form payload lost selected sports: ${JSON.stringify(tournamentPayload)}`);
    if (tournamentResult.data?.sport_ids?.length !== 2) throw new Error(`tournament API did not retain selected sports: payload=${JSON.stringify(tournamentPayload)} response=${JSON.stringify(tournamentResult)}`);
    await expect.poll(async () => page.evaluate(async () => (await request('tournaments.list')).find(row => row.name === 'Multi Sport UI Tournament')?.sport_ids?.length)).toBe(2);
    await page.evaluate(id => openTournamentDashboard(id), String(fixture.tournament.id));
    await expect(page.locator('#workflow-tournament-name')).toContainText('UI Tournament');
    await page.evaluate(() => setWorkflowStep(9, false));
    await page.getByRole('button', { name: 'เพิ่มแมตช์', exact: true }).click();
    await page.locator('#match-tournament-select').selectOption(String(fixture.tournament.id));
    await page.locator('#match-team1-select').selectOption(String(fixture.teams[0].id));
    await page.locator('#match-team2-select').selectOption(String(fixture.teams[1].id));
    await page.locator('#match-form [name=scheduled_at]').fill(upcomingMatchAt);
    await page.locator('#match-form [type=submit]').click();
    await expect(page.locator('#match-form-dialog')).toBeHidden();
    await expect.poll(async () => page.evaluate(async id => (await request('matches.list', { tournament_id: id })).length, fixture.tournament.id)).toBe(1);
    await expect(page.locator('#matches-table tbody')).toContainText('UI Team A');
    await page.evaluate(() => showView('public'));
    await expect(page.locator('#public-schedule .is-upcoming').first()).toBeVisible();
    await expect(page.locator('#public-schedule .upcoming-vs')).toHaveText('VS');
    await capture({ path: path.join(artifacts, 'public-upcoming-desktop.png'), fullPage: false });
    await page.evaluate(() => showView('public-bracket'));
    await page.locator('#bracket-tournament-filter').selectOption(String(fixture.tournament.id));
    await page.locator('#bracket-sport-filter').selectOption(String(fixture.teams[0].sport_id));
    await expect(page.locator('#public-bracket-rounds .bracket-match')).toHaveCount(1);
    await expect(page.locator('#public-bracket-rounds .bracket-team-row')).toHaveCount(2);
    await expect(page.locator('#public-bracket-rounds')).toHaveClass(/single-round/);
    await expect(page.locator('#public-bracket-rounds .bracket-team-mark img')).toHaveCount(0);
    const bracketLayout = await page.locator('#public-bracket-rounds .bracket-match').evaluate(match => {
      const rows = [...match.querySelectorAll('.bracket-team-row')];
      return {
        matchWidth: match.getBoundingClientRect().width,
        rows: rows.map(row => ({
          width: row.getBoundingClientRect().width,
          clientWidth: row.clientWidth,
          scrollWidth: row.scrollWidth,
          columns: getComputedStyle(row).gridTemplateColumns,
        })),
      };
    });
    expect(bracketLayout.matchWidth).toBeGreaterThan(500);
    for (const row of bracketLayout.rows) {
      expect(Math.abs(row.width - bracketLayout.matchWidth)).toBeLessThanOrEqual(1);
      expect(row.scrollWidth).toBeLessThanOrEqual(row.clientWidth + 1);
      expect(row.columns).toMatch(/^44px .+ 44px$/);
    }
    await capture({ path: path.join(artifacts, 'public-bracket-knockout-desktop.png'), fullPage: true });
    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#matches-table tbody')).toContainText('UI Team A');
    await page.locator('#matches-table').getByRole('button', { name: 'แก้ไข', exact: true }).click();
    await expect(page.locator('#match-team1-select')).toHaveValue(String(fixture.teams[0].id));
    await expect(page.locator('#match-team2-select')).toHaveValue(String(fixture.teams[1].id));
    await expect(page.locator('#match-form [name=scheduled_at]')).toHaveValue(upcomingMatchAt);
    await page.locator('#match-form-dialog .modal-head .icon-button').click();
    await expect.poll(async () => await page.locator('.confirm-dialog').isVisible() || await page.locator('#match-form-dialog').isHidden()).toBe(true);
    if (await page.locator('.confirm-dialog').isVisible()) {
      await page.locator('.confirm-dialog').getByRole('button', { name: 'ยกเลิกการแก้ไข', exact: true }).click();
    }
    await expect(page.locator('#match-form-dialog')).toBeHidden();
    const scoreMatchId = await page.evaluate(() => app.matches[0].id);
    await expect(page.locator('#match-center-panel, #result-form, #match-event-form, #mode-recording, #race-center')).toHaveCount(0);
    await page.evaluate(() => showView('official'));
    await expect(page.locator('#view-official')).toBeVisible();
    await expect(page.locator('#official-center-host .official-routing-empty')).toBeVisible();
    await expect(page.locator('#official-live-matches, #official-upcoming-matches, #official-completed-matches')).toHaveCount(3);
    await page.locator(`[data-official-match="${scoreMatchId}"]`).click();
    await expect(page).toHaveURL(new RegExp(`/public/scoring/score\\.php\\?match_id=${scoreMatchId}$`));
    await expect(page.locator('#score-workspace')).toBeVisible();
    await page.goto(`${base}/?view=official`);
    await expect(page.locator('#view-official')).toBeVisible();
    await capture({ path: path.join(artifacts, 'dedicated-recording-router-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: path.join(artifacts, 'dedicated-recording-router-mobile.png'), fullPage: false, animations: 'disabled', style: '#toast { visibility: hidden; }' });
    await page.setViewportSize({ width: 1440, height: 1000 });
    const officialRolePolicy = await page.evaluate(() => {
      const previous = app.user;
      app.user = { ...previous, role: 'result_officer' };
      updateSessionUi();
      const policy = { views: [...app.allowedViews], matchesHidden: document.querySelector('[data-view-button="matches"]').hidden };
      app.user = previous;
      updateSessionUi();
      return policy;
    });
    expect(officialRolePolicy.views).toContain('official');
    expect(officialRolePolicy.views).not.toContain('matches');
    expect(officialRolePolicy.matchesHidden).toBe(true);
    await capture({ path: path.join(artifacts, 'match-official-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: path.join(artifacts, 'match-official-mobile.png'), fullPage: false, animations: 'disabled', style: '#toast { visibility: hidden; }' });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.evaluate(() => showView('matches'));
    await page.evaluate(() => setWorkflowStep(9, false));
    await expect(page.locator('#matches-table select[aria-label="กรองสถานะ"]')).toBeVisible();

    await runNewsUxFlow({ page, base, artifacts, generatedUploads, root, cleanupFixtures: true });

    for (const width of [320, 390, 768]) {
      await page.setViewportSize({ width, height: 844 });
      await go('sports');
      await noOverflow();
      await capture({ path: path.join(artifacts, `sports-${width}.png`), fullPage: true });
      await page.getByRole('button', { name: 'เพิ่มกีฬา', exact: true }).click();
      const modal = page.locator('#sport-form-dialog');
      await expect(modal).toBeVisible();
      const box = await modal.boundingBox();
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
      expect(await modal.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
      await capture({ path: path.join(artifacts, `sport-modal-${width}.png`) });
      await page.keyboard.press('Escape');
      if (width === 390) {
        await go('matches');
        await page.evaluate(() => setWorkflowStep(2, false));
        await noOverflow();
        await capture({ path: path.join(artifacts, 'tournament-workflow-mobile.png'), fullPage: true });
        await page.evaluate(() => setWorkflowStep(5, false));
        await selectStudentScope();
        await noOverflow();
        await capture({ path: path.join(artifacts, 'student-scope-mobile.png'), fullPage: true });
        await page.evaluate(() => showView('public-athletes'));
        await page.locator('#athlete-faculty-filter').selectOption(String(fixture.tournament.id));
        await page.locator('#athlete-department-filter').selectOption(String(fixture.teams[0].sport_id));
        await page.locator('#athlete-sport-filter').selectOption(String(fixture.teams[0].id));
        await noOverflow();
        await capture({ path: path.join(artifacts, 'public-athletes-flow-mobile.png'), fullPage: true });
        await page.evaluate(async () => { await loadPublicHome(); showView('public-bracket'); });
        await page.locator('#bracket-tournament-filter').selectOption(String(draw.tournament.id));
        await page.locator('#bracket-sport-filter').selectOption(String(draw.sportId));
        await expect(page.locator('#public-bracket-rounds .bracket-group')).toHaveCount(2);
        await noOverflow();
        await capture({ path: path.join(artifacts, 'public-bracket-mobile.png'), fullPage: true });
        await page.evaluate(() => showView('matches'));
        await expect(page.locator('#view-matches')).toBeVisible();
      }

    }
    await page.evaluate(async data => request('matches.save', {
      tournament_id: data.tournamentId,
      sport_id: data.sportId,
      team1_id: data.team1Id,
      team2_id: data.team2Id,
      round_name: 'รอบชิงชนะเลิศ',
      match_number: 2,
      scheduled_at: data.scheduledAt,
      venue: 'สนามกีฬากลาง',
      status: 'scheduled',
    }), {
      tournamentId: fixture.tournament.id,
      sportId: fixture.teams[0].sport_id,
      team1Id: fixture.teams[0].id,
      team2Id: fixture.teams[1].id,
      scheduledAt: secondUpcomingMatchAt,
    });
    await page.locator('#logout-button').click();
    await expect(page.locator('#view-public')).toBeVisible();
    await expect(page.locator('#public-schedule .is-upcoming').first()).toBeVisible();
    await expect(page.locator('#public-home-results .empty-state')).toBeVisible();
    await page.locator('.split:has(.public-match-scroll)').screenshot({ path: path.join(artifacts, 'public-matchday-desktop.png'), animations: 'disabled', style: '.topbar { visibility: hidden !important; }' });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#page-progress')).toBeHidden();
    await noOverflow();
    await capture({ path: path.join(artifacts, 'public-mobile.png'), fullPage: true });
    await expect(page.locator('#public-schedule .is-upcoming').first()).toBeVisible();
    expect(await page.locator('.split:has(.public-match-scroll)').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(1);
    await page.locator('.split:has(.public-match-scroll)').screenshot({ path: path.join(artifacts, 'public-matchday-mobile.png'), animations: 'disabled', style: '.topbar { visibility: hidden !important; }' });
    await page.locator('.news-read').first().click();
    await expect(page.locator('#view-public-news-detail')).toBeVisible();
    expect(await page.locator('#view-public-news-detail').evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    await capture({ path: path.join(artifacts, 'news-article-mobile.png') });
    await page.locator('.article-back').click();
    // The public/mobile scenario above deliberately logged out. Start a real new session first.
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    await expect(page.locator('#page-progress')).toBeHidden();
    // Both supported PHP entry points must retain asset URLs, routing, and the session.
    for (const entry of ['/', '/public/']) {
      await page.goto(`${base}${entry}?view=news`);
      await expect(page.locator('#view-news')).toBeVisible();
      await expect(page.locator('#news-table tbody tr').first()).toBeVisible();
      expect(await page.evaluate(() => app.user.role)).toBe('super_admin');
      await page.locator('#news-create-button').click();
      await expect(page.locator('#news-form')).toBeVisible();
      await expect(page.locator('#view-news-editor')).toBeVisible();
      await page.locator('[data-news-back]').click();
      await expect(page.locator('#view-news')).toBeVisible();
      await page.goto(`${base}${entry}?view=public-news-detail&id=1`);
      await expect(page.locator('#article-title')).toHaveText('เปิดระบบ Sport Tournament');
      await expect(page).toHaveTitle(/เปิดระบบ Sport Tournament/);
    }
    expect(errors).toEqual([]);
    expect(consoleErrors).toEqual([]);
    expect(phpErrors).toEqual([]);
    console.log('PASS: dedicated recording routing, legacy recording UI removal, news, CRUD editors, CSV, match creation, and responsive layouts at 320/390/768/1440px.');
    console.log(`Screenshots: ${artifacts}`);
    if (newsBaselineCss) console.log(`PASS: ${newsVisualChecks} views have identical geometry/all computed styles, including dialog backdrops; paired raster checks normalize only GPU blur/shadows (${newsRasterOnlyDifferences} minor raster differences).`);
  } finally {
    if (browser) await browser.close();
    const exited = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await exited;
    await rm(db, { force: true });
    await Promise.all(generatedUploads.map(file => rm(file, { force: true })));
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
