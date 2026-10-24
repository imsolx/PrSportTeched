const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdir, rm } = require('node:fs/promises');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = process.env.PRSPORT_UI_ARTIFACTS || path.join(os.tmpdir(), `prsport-score-page-${Date.now()}`);
  await mkdir(artifacts, { recursive: true });
  const db = path.join(artifacts, 'score-page.sqlite');
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => {
      const value = socket.address().port;
      socket.close(() => resolve(value));
    });
  });
  const server = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['-d', 'display_errors=1', '-d', 'display_startup_errors=1', '-S', `127.0.0.1:${port}`, '-t', root], {
    cwd: root,
    env: { ...process.env, PRSPORT_DB_DSN: `sqlite:${db}` },
    windowsHide: true,
    stdio: 'ignore',
  });
  let browser;
  try {
    const base = `http://127.0.0.1:${port}`;
    for (let attempt = 0; attempt < 50; attempt++) {
      if (await fetch(base).then(response => response.ok).catch(() => false)) break;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
      if (response.status() >= 400 && response.url().includes('/public/scoring/')) {
        errors.push(`${response.status()} ${response.url()}`);
      }
    });
    page.on('requestfailed', request => {
      if (request.url().includes('/public/scoring/')) errors.push(`${request.failure()?.errorText || 'request failed'} ${request.url()}`);
    });

    await page.goto(base);
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();

    const fixture = await page.evaluate(async () => {
      const sport = await request('sports.save', {
        name: 'ฟุตบอล SCORE UI', type: 'team', gender: 'open', min_players: 11, max_players: 18,
        icon: 'football', scoring_type: 'SCORE',
      });
      const tournament = await request('tournaments.save', { name: 'ทดสอบหน้าคะแนนสะสม', sport_ids: [sport.id], format: 'round_robin' });
      const home = await request('teams.save', { name: 'มหาวิทยาลัยสีน้ำเงิน', tournament_id: tournament.id, sport_id: sport.id, department_code: '130' });
      const away = await request('teams.save', { name: 'มหาวิทยาลัยสีทอง', tournament_id: tournament.id, sport_id: sport.id, department_code: '140' });
      const match = await request('matches.save', {
        tournament_id: tournament.id, sport_id: sport.id, team1_id: home.id, team2_id: away.id,
        round_name: 'รอบชิงชนะเลิศ', venue: 'สนามกีฬากลาง', scheduled_at: '2026-10-15T15:00',
      });
      return { match };
    });

    await page.evaluate(() => showView('official'));
    const matchCard = page.locator(`[data-official-match="${fixture.match.id}"]`);
    await expect(matchCard).toBeVisible();
    await matchCard.click();
    await expect(page).toHaveURL(new RegExp(`/public/scoring/score\\.php\\?match_id=${fixture.match.id}$`));
    await expect(page).toHaveTitle('บันทึกคะแนนสะสม (SCORE)');
    await expect(page.locator('#score-workspace')).toBeVisible();
    await expect(page.locator('#score-match-select')).toHaveValue(String(fixture.match.id));
    await expect(page.locator('#score-round')).toHaveText('รอบชิงชนะเลิศ');
    await expect(page.locator('#score-team1-value')).toHaveText('0');

    await page.locator('#score-clock-start').click();
    await expect(page.locator('#score-status')).toContainText('กำลังแข่งขัน');
    await page.locator('[data-score-side=home]').click();
    await expect(page.locator('#score-team1-value')).toHaveText('1');
    await page.locator('[data-edit-event]').click();
    await page.locator('#score-event-points').fill('2');
    await page.locator('#score-event-form [name=note]').fill('แก้ไขคะแนนทดสอบ');
    await page.locator('#score-event-form [name=edit_reason]').fill('ตรวจสอบข้อมูลเก่าแล้ว');
    await page.locator('#score-event-submit').click();
    await expect(page.locator('#score-team1-value')).toHaveText('2');
    await expect(page.locator('#score-event-list')).toContainText('แก้ไขแล้ว');

    page.once('dialog', dialog => dialog.accept());
    await page.locator('[data-void-event]').click();
    await expect(page.locator('#score-team1-value')).toHaveText('0');
    await expect(page.locator('#score-event-count')).toContainText('1 ยกเลิก');

    await page.locator('[data-score-side=home]').click();
    await expect(page.locator('#score-team1-value')).toHaveText('1');
    await expect(page.locator('#score-event-count')).toContainText('1 ใช้งาน');
    await page.screenshot({ path: path.join(artifacts, 'score-live-desktop.png'), fullPage: true });

    page.once('dialog', dialog => dialog.accept());
    await page.locator('#score-finish').click();
    await expect(page.locator('#score-status')).toContainText('จบแล้ว');
    await expect(page.locator('#score-finish')).toBeDisabled();
    await expect(page.locator('#score-result-admin')).toBeVisible();

    await page.locator('#score-result-team1').fill('3');
    await page.locator('#score-result-team2').fill('1');
    await page.locator('#score-result-reason').fill('แก้ไขผลจากเอกสารกรรมการ');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#score-result-update').click();
    await expect(page.locator('#score-team1-value')).toHaveText('3');
    await expect(page.locator('#score-team2-value')).toHaveText('1');
    await expect(page.locator('#score-result-revision')).toHaveText('Revision 2');
    await page.screenshot({ path: path.join(artifacts, 'score-result-updated-desktop.png'), fullPage: true });

    await page.locator('#score-result-reason').fill('ยกเลิกผลเพื่อแก้ข้อมูลแมตช์เก่า');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#score-result-delete').click();
    await expect(page.locator('#score-status')).toContainText('กำลังแข่งขัน');
    await expect(page.locator('#score-result-admin')).toBeHidden();
    await expect(page.locator('#score-team1-value')).toHaveText('1');
    await expect(page.locator('#score-team2-value')).toHaveText('0');

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#score-workspace')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.screenshot({ path: path.join(artifacts, 'score-completed-mobile.png'), fullPage: true });
    expect(errors).toEqual([]);
    console.log(`PASS SCORE automatic route, event CRUD, result CRUD/reopen, scoring consistency, and responsive layout. Screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    const exited = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await exited;
    await rm(db, { force: true });
  }
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
