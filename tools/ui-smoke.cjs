const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdir, rm } = require('node:fs/promises');
const path = require('node:path');
const net = require('node:net');

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = path.join(root, 'storage', 'ui-checks');
  await mkdir(artifacts, { recursive: true });
  const db = path.join(artifacts, `test-${Date.now()}.sqlite`);
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => { const port = socket.address().port; socket.close(() => resolve(port)); });
  });
  const server = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['-d', `upload_tmp_dir=${artifacts}`, '-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', root], {
    cwd: root, env: { ...process.env, PRSPORT_DB_DSN: `sqlite:${db}`, GEMINI_API_KEY: '', GEMINI_MODEL: 'local-summary' }, windowsHide: true, stdio: 'ignore',
  });
  let browser;
  const generatedUploads = [];
  try {
    const base = `http://127.0.0.1:${port}`;
    for (let attempt = 0; attempt < 50; attempt++) {
      if (await fetch(base).then(r => r.ok).catch(() => false)) break;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const capture = async options => {
      await page.evaluate(() => {
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
        document.scrollingElement.scrollTop = 0;
        window.scrollTo(0, 0);
      });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      await page.screenshot({ ...options, animations: 'disabled', style: '#toast { visibility: hidden; }' });
    };
    await page.goto(base);
    await page.waitForFunction(() => document.querySelectorAll('.editor-dialog').length >= 10);
    await expect(page.locator('#page-progress')).toBeHidden();
    await page.evaluate(() => document.fonts.ready);
    await capture({ path: path.join(artifacts, 'public-desktop.png'), fullPage: true });
    await page.locator('.news-read').first().click();
    await expect(page.locator('.article-dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('.article-dialog')).toHaveCount(0);
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('incorrect-password');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#login-form .form-error')).toBeVisible();
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    await expect(page.locator('#page-progress')).toBeHidden();
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
    await expect(page.locator('#sports-table [aria-label="ลบ"] svg').first()).toBeVisible();
    await page.getByRole('button', { name: 'เพิ่มกีฬา', exact: true }).click();
    await page.locator('#sport-form [name=name]').fill('UI Test Sport');
    await page.keyboard.press('Escape');
    await expect(page.locator('.confirm-dialog')).toBeVisible();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'กลับ', exact: true }).click();
    await expect(page.locator('#sport-form [name=name]')).toHaveValue('UI Test Sport');
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
    await page.locator('#sport-form [name=name]').fill('UI Test Sport Edited');
    await page.locator('#sport-form [type=submit]').click();
    await expect(page.locator('#sport-form-dialog')).toBeHidden();
    await expect(page.locator('#sports-table tbody')).toContainText('UI Test Sport Edited');
    await page.getByRole('button', { name: 'เพิ่มกีฬา', exact: true }).click();
    await page.locator('#sport-form [name=name]').fill('UI Test Sport Edited');
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
      for (let i = 1; i <= 12; i++) await request('sports.save', { name: `Pagination ${i}`, type: 'team', gender: 'mixed', min_players: 1, max_players: 5, reserve_players: 0 });
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

    const sections = {
      sports: [{ id: 'organization-form' }],
      matches: [
        { id: 'tournament-form', step: 1 }, { id: 'sport-rule-form', step: 3 }, { id: 'student-form', step: 5 },
        { id: 'team-form', step: 6 }, { id: 'member-form', step: 7 }, { id: 'match-form', step: 9 }, { id: 'result-form', step: 10 },
      ],
      news: [{ id: 'news-form' }], tasks: [{ id: 'task-form' }], users: [{ id: 'user-form' }],
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
          await expect(heading.getByRole('button', { name: `เพิ่ม${label}`, exact: true })).toBeDisabled();
          await expect(page.locator('#student-scope-department')).toBeDisabled();
          await selectStudentScope();
          await expect(heading.getByRole('button', { name: `เพิ่ม${label}`, exact: true })).toBeEnabled();
          await capture({ path: path.join(artifacts, 'student-scope-desktop.png'), fullPage: true });
        }
        await heading.getByRole('button', { name: id === 'result-form' ? 'บันทึกผล' : `เพิ่ม${label}`, exact: true }).click();
        await expect(page.locator(`#${id}-dialog`)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator(`#${id}-dialog`)).toBeHidden();
      }
    }
    await go('tasks');
    await page.evaluate(() => {
      const label = workspace.forms['task-form'][0];
      const button = [...document.querySelectorAll('#view-tasks .data-heading button')].find(node => node.textContent.includes(label));
      button.click();
    });
    await expect(page.locator('#task-form-dialog')).toBeVisible();
    await page.locator('#task-form [name=title]').fill('UI Test Task');
    await page.locator('#task-form [name=description]').fill('Prepare venue and publish schedule.');
    await page.locator('#task-form [name=category]').selectOption('competition');
    await page.locator('#task-form [name=priority]').selectOption('urgent');
    await page.locator('#task-form [name=status]').selectOption('todo');
    await page.locator('#task-form [name=due_at]').fill('2026-09-16T09:00');
    await page.locator('#task-form [type=submit]').click();
    await expect(page.locator('#task-form-dialog')).toBeHidden();
    await expect(page.locator('#tasks-table tbody')).toContainText('UI Test Task');
    await page.locator('#tasks-table tbody tr', { hasText: 'UI Test Task' }).getByRole('button').nth(1).click();
    await expect.poll(async () => page.evaluate(async () => (await request('tasks.list')).find(row => row.title === 'UI Test Task')?.status)).toBe('done');
    await page.locator('#tasks-table tbody tr', { hasText: 'UI Test Task' }).getByRole('button').last().click();
    await page.locator('.confirm-dialog').getByRole('button').last().click();
    await expect.poll(async () => page.evaluate(async () => (await request('tasks.list')).some(row => row.title === 'UI Test Task'))).toBe(false);
    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#view-matches')).toBeVisible();
    await expect(page.locator('#page-progress')).toBeHidden();
    await page.evaluate(() => setWorkflowStep(5, false));
    await selectStudentScope();
    await page.getByRole('button', { name: 'นำเข้า CSV', exact: true }).click();
    await expect(page.locator('.import-dialog[open]')).toBeVisible();
    await page.keyboard.press('Escape');
    await go('reports');
    await noOverflow();
    await expect(page.locator('#ai-provider-status')).toBeVisible();

    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#view-matches')).toBeVisible();
    await page.evaluate(() => setWorkflowStep(5, false));
    await selectStudentScope();
    await page.locator('[data-workflow-step~="5"] .data-heading').getByRole('button', { name: 'เพิ่มนักศึกษา', exact: true }).click();
    await page.locator('#student-form [name=student_code]').fill('UI-ATHLETE-01');
    await page.locator('#student-form [name=first_name]').fill('กิตติพงษ์');
    await page.locator('#student-form [name=last_name]').fill('ทดสอบระบบ');
    await expect(page.locator('#student-form [name=department_code]')).toHaveValue(await page.locator('#student-scope-department').inputValue());
    await page.locator('#student-photo-file').setInputFiles(path.join(root, 'public', 'assets', 'logo.png'));
    await expect(page.locator('#student-photo-preview img')).toBeVisible();
    await page.locator('#student-form [type=submit]').click();
    await expect(page.locator('#student-form-dialog')).toBeHidden();
    await expect(page.locator('#students-table .student-table-photo')).toBeVisible();
    await expect(page.locator('#students-table tbody')).not.toContainText('<img');
    const profilePhotoUrl = await page.evaluate(async () => (await request('students.list', { q: 'UI-ATHLETE-01' }))[0]?.photo_url || '');
    expect(profilePhotoUrl).toContain('/uploads/profiles/profile-');
    generatedUploads.push(path.join(root, 'public', 'uploads', 'profiles', path.basename(new URL(profilePhotoUrl, base).pathname)));

    const fixture = await page.evaluate(async () => {
      const sport = (await request('sports.list'))[0];
      const allSports = await request('sports.list');
      const department = (await request('departments.list'))[0];
      const multi = await request('tournaments.save', { name: 'Multi Sport UI Tournament', sport_ids: allSports.slice(0, 2).map(row => row.id), format: 'knockout', status: 'registration' });
      if (!Array.isArray(multi.sport_ids) || multi.sport_ids.length !== 2) throw new Error('multi-sport tournament create failed');
      const edited = await request('tournaments.save', { id: multi.id, sport_ids: allSports.slice(0, 1).map(row => row.id), name: multi.name, format: multi.format, status: multi.status });
      if (!Array.isArray(edited.sport_ids) || edited.sport_ids.length !== 1) throw new Error('multi-sport tournament edit failed');
      const tournament = await request('tournaments.save', { name: 'UI Tournament', sport_id: sport.id, format: 'knockout', status: 'registration' });
      const teams = [];
      for (const name of ['UI Team A', 'UI Team B']) teams.push(await request('teams.save', { name, tournament_id: tournament.id, department_code: department.code }));
      const athlete = (await request('students.list', { q: 'UI-ATHLETE-01' }))[0];
      await request('teamMembers.save', { team_id: teams[0].id, student_id: athlete.id, role: 'captain', shirt_number: '10', field_position: 'กองหน้า' });
      return { tournament, teams, multi };
    });
    await page.evaluate(() => showView('public-athletes'));
    await expect(page.locator('#view-public-athletes')).toBeVisible();
    await expect(page.locator('#athlete-department-filter')).toBeDisabled();
    await page.locator('#athlete-faculty-filter').selectOption({ index: 1 });
    await expect(page.locator('#athlete-department-filter')).toBeEnabled();
    await page.locator('#athlete-department-filter').selectOption({ index: 1 });
    await expect(page.locator('#athlete-sport-filter')).toBeEnabled();
    await page.locator('#athlete-sport-filter').selectOption({ index: 1 });
    await expect(page.locator('#athlete-results')).toBeVisible();
    await expect(page.locator('[data-athlete-flow-step="4"]')).toHaveClass(/active/);
    await expect(page.locator('.athlete-profile-photo')).toBeVisible();
    await expect(page.locator('.athlete-profile-photo')).toHaveAttribute('src', /uploads\/profiles\/profile-/);
    await capture({ path: path.join(artifacts, 'public-athletes-flow-desktop.png'), fullPage: true });
    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#view-matches')).toBeVisible();
    await expect(page.locator('#page-progress')).toBeHidden();
    await expect.poll(() => page.evaluate(() => Boolean(document.querySelector('link[href*="workspace.css?v="]')))).toBe(true);
    await expect.poll(() => page.locator('#workflow-steps').evaluate(element => getComputedStyle(element).display)).toBe('flex');
    await page.locator('#workflow-tournament-select').selectOption(String(fixture.multi.id));
    await expect(page.locator('#workflow-tournament-name')).toContainText('Multi Sport UI Tournament');
    await page.evaluate(() => setWorkflowStep(2, false));
    await expect(page.locator('#workflow-step-count')).toHaveText('ขั้นที่ 2 จาก 11');
    await expect(page.locator('#workflow-step-title')).toHaveText('เลือกกีฬาที่จัด');
    await page.locator('#workflow-next-button').click();
    await expect(page.locator('#workflow-step-title')).toHaveText('ตั้งกติกาแต่ละกีฬา');
    await expect(page.locator('[data-workflow-step-button="3"]')).toHaveAttribute('aria-current', 'step');
    await page.locator('#workflow-previous-button').click();
    await expect(page.locator('#workflow-step-title')).toHaveText('เลือกกีฬาที่จัด');
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
    await page.locator('#workflow-tournament-select').selectOption(String(fixture.tournament.id));
    await expect(page.locator('#workflow-tournament-name')).toContainText('UI Tournament');
    await page.evaluate(() => setWorkflowStep(9, false));
    await page.getByRole('button', { name: 'เพิ่มแมตช์', exact: true }).click();
    await page.locator('#match-tournament-select').selectOption(String(fixture.tournament.id));
    await page.locator('#match-team1-select').selectOption(String(fixture.teams[0].id));
    await page.locator('#match-team2-select').selectOption(String(fixture.teams[1].id));
    await page.locator('#match-form [name=scheduled_at]').fill('2026-09-15T10:30');
    await page.locator('#match-form [type=submit]').click();
    await expect(page.locator('#match-form-dialog')).toBeHidden();
    await expect.poll(async () => page.evaluate(async id => (await request('matches.list', { tournament_id: id })).length, fixture.tournament.id)).toBe(1);
    await expect(page.locator('#matches-table tbody')).toContainText('UI Team A');
    await page.locator('#matches-table').getByRole('button', { name: 'แก้ไข', exact: true }).click();
    await expect(page.locator('#match-team1-select')).toHaveValue(String(fixture.teams[0].id));
    await expect(page.locator('#match-team2-select')).toHaveValue(String(fixture.teams[1].id));
    await expect(page.locator('#match-form [name=scheduled_at]')).toHaveValue('2026-09-15T10:30');
    await page.locator('#match-form-dialog .modal-head .icon-button').click();
    await expect.poll(async () => await page.locator('.confirm-dialog').isVisible() || await page.locator('#match-form-dialog').isHidden()).toBe(true);
    if (await page.locator('.confirm-dialog').isVisible()) {
      await page.locator('.confirm-dialog').getByRole('button', { name: 'ยกเลิกการแก้ไข', exact: true }).click();
    }
    await expect(page.locator('#match-form-dialog')).toBeHidden();
    await page.locator('#matches-table').getByRole('button', { name: 'บันทึกผล', exact: true }).click();
    await page.locator('#result-form [name=team1_score]').fill('3');
    await page.locator('#result-form [name=team2_score]').fill('1');
    await page.locator('#result-form [type=submit]').click();
    await expect(page.locator('#result-form-dialog')).toBeHidden();
    await expect(page.locator('#matches-table tbody')).toContainText('3');
    expect(await page.evaluate(async () => (await request('public.results'))[0].team1_score)).toBe('3');
    await page.locator('#matches-table').getByRole('button', { name: 'ศูนย์แข่งขัน', exact: true }).click();
    await expect(page.locator('#match-center-content')).toBeVisible();
    await expect(page.locator('#center-score')).toContainText('3 - 1');
    await page.locator('#event-type').selectOption('foul');
    await page.locator('#event-team').selectOption(String(fixture.teams[0].id));
    await page.locator('#match-event-form [type=submit]').click();
    await expect(page.locator('#match-events-table tbody tr')).toHaveCount(1);
    await expect(page.locator('#center-score')).toContainText('3 - 1');
    expect((await page.evaluate(async id => request('standings.list', { tournament_id: id }), fixture.tournament.id))[0].points).toBe('3');
    await capture({ path: path.join(artifacts, 'competition-center-desktop.png'), fullPage: true });
    await page.locator('#matches-table select[aria-label="กรองสถานะ"]').selectOption({ index: 1 });
    await expect(page.locator('#matches-table tbody tr')).toHaveCount(1);

    await go('news');
    await page.getByRole('button', { name: 'เพิ่มข่าวประชาสัมพันธ์', exact: true }).click();
    await expect(page.locator('#news-ai-match option')).toHaveCount(2);
    await page.locator('#news-ai-match').selectOption({ index: 1 });
    await page.locator('#news-ai-tone').selectOption('energetic');
    await page.locator('#news-ai-instruction').fill('ชื่นชมนักกีฬาและเชิญชวนติดตามการแข่งขันครั้งต่อไป');
    await page.locator('#news-ai-button').click();
    await expect(page.locator('#news-form [name=title]')).not.toHaveValue('');
    await expect(page.locator('#news-ai-status')).toContainText('ร่างอัตโนมัติ');
    await page.locator('#banner-open-button').click();
    await expect(page.locator('#banner-dialog')).toBeVisible();
    await page.locator('#banner-template').selectOption('result');
    await page.locator('#banner-headline').fill('UI TEAM A 3 - 1 UI TEAM B');
    await expect.poll(async () => page.locator('#banner-canvas').evaluate(canvas => canvas.toDataURL().length)).toBeGreaterThan(10000);
    await capture({ path: path.join(artifacts, 'news-banner-studio.png') });
    await page.locator('#banner-use-button').click();
    await expect(page.locator('#banner-dialog')).toBeHidden();
    await expect(page.locator('#news-cover-url')).toHaveValue(/uploads\/news\/banner-/);
    const generatedBannerUrl = await page.locator('#news-cover-url').inputValue();
    generatedUploads.push(path.join(root, 'public', 'uploads', 'news', path.basename(new URL(generatedBannerUrl, base).pathname)));
    await expect(page.locator('#news-cover-preview img')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('.confirm-dialog')).toBeVisible();
    await page.locator('.confirm-dialog').getByRole('button', { name: 'ยกเลิกการแก้ไข', exact: true }).click();
    await expect(page.locator('#news-form-dialog')).toBeHidden();

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
        await page.locator('#athlete-faculty-filter').selectOption({ index: 1 });
        await page.locator('#athlete-department-filter').selectOption({ index: 1 });
        await page.locator('#athlete-sport-filter').selectOption({ index: 1 });
        await noOverflow();
        await capture({ path: path.join(artifacts, 'public-athletes-flow-mobile.png'), fullPage: true });
        await page.evaluate(() => showView('matches'));
        await expect(page.locator('#view-matches')).toBeVisible();
      }
      await go('news');
      await page.getByRole('button', { name: 'เพิ่มข่าวประชาสัมพันธ์', exact: true }).click();
      expect(await page.locator('#news-form-dialog').evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
      await page.locator('#banner-open-button').click();
      await expect(page.locator('#banner-dialog')).toBeVisible();
      expect(await page.locator('#banner-dialog').evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
      if (width === 390) {
        await capture({ path: path.join(artifacts, 'news-banner-mobile.png') });
        await page.locator('.banner-workspace').scrollIntoViewIfNeeded();
        await capture({ path: path.join(artifacts, 'news-banner-mobile-canvas.png') });
      }
      await page.locator('#banner-close-button').click();
      await expect(page.locator('#banner-dialog')).toBeHidden();
      await page.keyboard.press('Escape');
    }
    await page.locator('#logout-button').click();
    await expect(page.locator('#view-public')).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#page-progress')).toBeHidden();
    await noOverflow();
    await capture({ path: path.join(artifacts, 'public-mobile.png'), fullPage: true });
    expect(errors).toEqual([]);
    console.log('PASS: login/error recovery, AI news draft, banner render/upload, sport CRUD, discard/delete confirmation, clean create form, search/pagination/status filtering, all editor dialogs, CSV modal, match creation/edit/result, and responsive layouts at 320/390/768/1440px.');
    console.log(`Screenshots: ${artifacts}`);
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
