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
    await expect(page.locator('#sport-icon-picker .sport-icon-choice img')).toHaveCount(13);
    await page.locator('#sport-icon-picker [data-sport-icon="basketball"]').click();
    await expect(page.locator('#sport-form [name=icon]')).toHaveValue('basketball');
    await page.locator('#sport-form [name=name]').fill('UI Test Sport');
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
      news: [{ id: 'news-form' }], users: [{ id: 'user-form' }],
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
        await heading.getByRole('button', { name: id === 'result-form' ? 'บันทึกผล' : `เพิ่ม${label}`, exact: true }).click();
        await expect(page.locator(`#${id}-dialog`)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator(`#${id}-dialog`)).toBeHidden();
      }
    }
    await go('users');
    await expect(page.locator('.tab[data-view-button="tasks"]')).toHaveCount(0);
    await expect(page.locator('#user-role-select option[value="viewer"]')).toHaveCount(0);
    await expect(page.locator('#invite-role-select option[value="viewer"]')).toHaveCount(0);
    await expect(page.locator('#staff-invites-enabled')).toBeChecked();
    await page.locator('#audit-search').fill('login_failed');
    await expect(page.locator('#audit-full-table')).toContainText('login_failed');
    await page.locator('#staff-invites-enabled').uncheck();
    await expect.poll(async () => page.evaluate(async () => (await request('users.options')).staff_invites_enabled)).toBe(false);
    await page.locator('#staff-invites-enabled').check();
    await expect.poll(async () => page.evaluate(async () => (await request('users.options')).staff_invites_enabled)).toBe(true);
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
    await expect(page.locator('#report-create-button')).toContainText('สรุปผลรายงาน');
    await page.locator('#report-create-button').click();
    await expect(page.locator('#report-builder')).toBeVisible();
    await expect(page.locator('[data-report-step="3"]')).toBeVisible();
    await page.locator('#report-summary-button').click();
    await expect(page.locator('#report-output')).toContainText('สรุปผลลงในแบบรายงานทางการแล้ว');
    await expect(page.locator('#report-editor-form [name=title]')).not.toHaveValue('');
    await page.locator('#report-next-step').click();
    await expect(page.locator('#stored-report-preview iframe')).toBeVisible();
    await expect(page.locator('#report-download-pdf')).toBeVisible();
    await capture({ path: path.join(artifacts, 'report-book-preview.png'), fullPage: true });
    const pdfDownload = page.waitForEvent('download');
    await page.locator('#report-download-pdf').click();
    const downloadedReport = await pdfDownload;
    await downloadedReport.saveAs(path.join(artifacts, 'report-book.pdf'));
    expect(downloadedReport.suggestedFilename()).toMatch(/\.pdf$/i);

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
    await expect.poll(() => page.locator('#workflow-steps').evaluate(element => getComputedStyle(element).display)).toBe('flex');
    await page.locator('#workflow-tournament-select').selectOption(String(fixture.multi.id));
    await expect(page.locator('#workflow-tournament-name')).toContainText('Multi Sport UI Tournament');
    await page.evaluate(() => setWorkflowStep(2, false));
    await expect(page.locator('#workflow-step-count')).toHaveText('ขั้นที่ 2 จาก 11');
    await expect(page.locator('#workflow-step-title')).toHaveText('กีฬาที่จัดในทัวร์นาเมนต์');
    await page.locator('#workflow-next-button').click();
    await expect(page.locator('#workflow-step-title')).toHaveText('กำหนดชุดกติกาที่ใช้');
    await expect(page.locator('[data-workflow-step-button="3"]')).toHaveAttribute('aria-current', 'step');
    await page.locator('#workflow-previous-button').click();
    await expect(page.locator('#workflow-step-title')).toHaveText('กีฬาที่จัดในทัวร์นาเมนต์');
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
    await page.locator('#match-form [name=scheduled_at]').fill('2026-10-20T18:30');
    await page.locator('#match-form [type=submit]').click();
    await expect(page.locator('#match-form-dialog')).toBeHidden();
    await expect.poll(async () => page.evaluate(async id => (await request('matches.list', { tournament_id: id })).length, fixture.tournament.id)).toBe(1);
    await expect(page.locator('#matches-table tbody')).toContainText('UI Team A');
    await page.evaluate(() => showView('public'));
    await expect(page.locator('#public-schedule .is-upcoming')).toBeVisible();
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
    await expect(page.locator('#match-form [name=scheduled_at]')).toHaveValue('2026-10-20T18:30');
    await page.locator('#match-form-dialog .modal-head .icon-button').click();
    await expect.poll(async () => await page.locator('.confirm-dialog').isVisible() || await page.locator('#match-form-dialog').isHidden()).toBe(true);
    if (await page.locator('.confirm-dialog').isVisible()) {
      await page.locator('.confirm-dialog').getByRole('button', { name: 'ยกเลิกการแก้ไข', exact: true }).click();
    }
    await expect(page.locator('#match-form-dialog')).toBeHidden();
    await page.locator('#matches-table').getByRole('button', { name: 'ศูนย์แข่งขัน', exact: true }).click();
    await expect(page.locator('#match-center-content')).toBeVisible();
    await expect(page.locator('#center-roster1 .match-roster-member')).toHaveCount(3);
    await expect(page.locator('#center-roster1')).toContainText('กิตติพงษ์');
    await expect(page.locator('#match-event-form [type=submit]')).toBeDisabled();
    await page.locator('#clock-start').click();
    await expect(page.locator('#center-status')).toHaveClass(/status-live/);
    await page.locator('#event-type').selectOption('foul');
    await page.locator('#event-team').selectOption(String(fixture.teams[0].id));
    await page.locator('#event-student').selectOption(String(fixture.athlete.id));
    await page.locator('#match-event-form [type=submit]').click();
    await expect(page.locator('#match-events-table tbody tr')).toHaveCount(1);
    await expect(page.locator('#match-events-table tbody')).toContainText('กิตติพงษ์');
    await page.locator('#match-finish').click();
    await expect(page.locator('#result-form-dialog')).toBeVisible();
    await page.locator('#result-form [name=team1_score]').fill('3');
    await page.locator('#result-form [name=team2_score]').fill('1');
    await page.locator('#result-form [type=submit]').click();
    await expect(page.locator('#result-form-dialog')).toBeHidden();
    await expect(page.locator('#center-score')).toContainText('3 - 1');
    await expect(page.locator('#center-status')).toHaveClass(/status-completed/);
    await expect(page.locator('#matches-table tbody')).toContainText('3');
    expect(await page.evaluate(async () => (await request('public.results'))[0].team1_score)).toBe('3');
    await page.evaluate(() => showView('public'));
    await expect(page.locator('#public-home-results .result-versus .upcoming-team')).toHaveCount(2);
    await expect(page.locator('#public-home-results .upcoming-team-mark img')).toHaveCount(0);
    await page.evaluate(() => showView('matches'));
    await page.locator('#matches-table').getByRole('button', { name: 'ศูนย์แข่งขัน', exact: true }).click();
    await expect(page.locator('#match-center-content')).toBeVisible();
    await expect(page.locator('#center-score')).toContainText('3 - 1');
    await expect(page.locator('#match-events-table tbody tr')).toHaveCount(1);
    expect((await page.evaluate(async id => request('standings.list', { tournament_id: id }), fixture.tournament.id))[0].points).toBe('3');
    await capture({ path: path.join(artifacts, 'competition-center-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('#match-center-panel').scrollIntoViewIfNeeded();
    await page.screenshot({ path: path.join(artifacts, 'competition-center-mobile.png'), fullPage: false, animations: 'disabled', style: '#toast { visibility: hidden; }' });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.evaluate(() => showView('official'));
    await expect(page.locator('#view-official')).toBeVisible();
    await expect(page.locator('#official-center-host > #match-center-panel')).toBeVisible();
    await expect(page.locator('#official-completed-matches .official-match-item')).toHaveCount(1);
    await expect(page.locator('#official-event-feed .official-event-item')).toHaveCount(1);
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
    await page.locator('#matches-table select[aria-label="กรองสถานะ"]').selectOption({ index: 1 });
    await expect(page.locator('#matches-table tbody tr')).toHaveCount(1);

    await go('news');
    await page.getByRole('button', { name: 'เพิ่มข่าวประชาสัมพันธ์', exact: true }).click();
    await page.locator('#news-template-select').selectOption('honor');
    await expect(page.locator('#news-form [name=news_type]')).toHaveValue('hall_of_fame');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(/ผู้ได้รับรางวัล/);
    expect(await page.locator('#news-ai-match option').count()).toBeGreaterThan(1);
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
        await page.locator('#athlete-faculty-filter').selectOption(String(fixture.tournament.id));
        await page.locator('#athlete-department-filter').selectOption(String(fixture.teams[0].sport_id));
        await page.locator('#athlete-sport-filter').selectOption(String(fixture.teams[0].id));
        await noOverflow();
        await capture({ path: path.join(artifacts, 'public-athletes-flow-mobile.png'), fullPage: true });
        await page.evaluate(() => showView('public-bracket'));
        await page.locator('#bracket-tournament-filter').selectOption(String(draw.tournament.id));
        await page.locator('#bracket-sport-filter').selectOption(String(draw.sportId));
        await expect(page.locator('#public-bracket-rounds .bracket-group')).toHaveCount(2);
        await noOverflow();
        await capture({ path: path.join(artifacts, 'public-bracket-mobile.png'), fullPage: true });
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
    await page.evaluate(async data => request('matches.save', {
      tournament_id: data.tournamentId,
      sport_id: data.sportId,
      team1_id: data.team1Id,
      team2_id: data.team2Id,
      round_name: 'รอบชิงชนะเลิศ',
      match_number: 2,
      scheduled_at: '2026-10-25T19:00',
      venue: 'สนามกีฬากลาง',
      status: 'scheduled',
    }), {
      tournamentId: fixture.tournament.id,
      sportId: fixture.teams[0].sport_id,
      team1Id: fixture.teams[0].id,
      team2Id: fixture.teams[1].id,
    });
    await page.locator('#logout-button').click();
    await expect(page.locator('#view-public')).toBeVisible();
    await expect(page.locator('#public-home-results .is-completed')).toBeVisible();
    await expect(page.locator('#public-home-results .result-score')).toContainText('3');
    await expect(page.locator('#public-home-results .result-status')).toContainText('จบการแข่งขัน');
    await page.locator('.split:has(.public-match-scroll)').screenshot({ path: path.join(artifacts, 'public-matchday-desktop.png'), animations: 'disabled', style: '.topbar { visibility: hidden !important; }' });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#page-progress')).toBeHidden();
    await noOverflow();
    await capture({ path: path.join(artifacts, 'public-mobile.png'), fullPage: true });
    await expect(page.locator('#public-schedule .is-upcoming')).toBeVisible();
    expect(await page.locator('.split:has(.public-match-scroll)').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length)).toBe(1);
    await page.locator('.split:has(.public-match-scroll)').screenshot({ path: path.join(artifacts, 'public-matchday-mobile.png'), animations: 'disabled', style: '.topbar { visibility: hidden !important; }' });
    await page.locator('.news-read').first().click();
    await expect(page.locator('#view-public-news-detail')).toBeVisible();
    expect(await page.locator('#view-public-news-detail').evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    await capture({ path: path.join(artifacts, 'news-article-mobile.png') });
    await page.locator('.article-back').click();
    expect(errors).toEqual([]);
    console.log('PASS: separate news detail route/linking, login/error recovery, AI news draft, banner render/upload, sport CRUD, discard/delete confirmation, clean create form, search/pagination/status filtering, all editor dialogs, CSV modal, match creation/edit/result, and responsive layouts at 320/390/768/1440px.');
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
