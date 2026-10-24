const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdir, rm } = require('node:fs/promises');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = process.env.PRSPORT_UI_ARTIFACTS || path.join(os.tmpdir(), `prsport-set-page-${Date.now()}`);
  await mkdir(artifacts, { recursive: true });
  const db = path.join(artifacts, 'set-page.sqlite');
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => { const value = socket.address().port; socket.close(() => resolve(value)); });
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
    const page = await browser.newPage({ viewport: { width: 1440, height: 950 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`); });

    await page.goto(base);
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();

    const fixture = await page.evaluate(async () => {
      const sport = await request('sports.save', {
        name: 'วอลเลย์บอล SET UI', type: 'team', gender: 'open', min_players: 6, max_players: 12,
        icon: 'volleyball', scoring_type: 'SET',
      });
      const tournament = await request('tournaments.save', { name: 'ทดสอบหน้า SET', sport_ids: [sport.id], format: 'round_robin' });
      const home = await request('teams.save', { name: 'ทีมสีน้ำเงิน', tournament_id: tournament.id, sport_id: sport.id, department_code: '130' });
      const away = await request('teams.save', { name: 'ทีมสีทอง', tournament_id: tournament.id, sport_id: sport.id, department_code: '140' });
      const match = await request('matches.save', {
        tournament_id: tournament.id, sport_id: sport.id, team1_id: home.id, team2_id: away.id,
        round_name: 'รอบชิงชนะเลิศ', venue: 'สนามวอลเลย์บอล', scheduled_at: '2026-10-15T15:00',
      });
      return { match };
    });

    await page.evaluate(() => showView('official'));
    await page.locator(`[data-official-match="${fixture.match.id}"]`).click();
    await expect(page).toHaveURL(new RegExp(`/public/scoring/set\\.php\\?match_id=${fixture.match.id}$`));
    await expect(page).toHaveTitle('บันทึกคะแนนเป็นเซต (SET)');
    await expect(page.locator('#set-workspace')).toBeVisible();
    await expect(page.locator('#set-best-of')).toHaveText('Best of 3');

    await page.locator('#set-start').click();
    await expect(page.locator('#set-status')).toContainText('กำลังแข่งขัน');
    await expect(page.locator('#set-toast')).toContainText('เปิดเซต 1');
    await expect(page.locator('[data-set-row]')).toHaveCount(1);
    await expect(page.locator('[data-set-row="0"] [data-set-side="home"]')).toHaveValue('0');
    await expect(page.locator('[data-point-side="home"]')).toBeEnabled();
    await page.locator('[data-point-side="home"]').click();
    await expect(page.locator('#set-team1-wins')).toHaveText('0');
    await expect(page.locator('#set-add')).toBeDisabled();
    await expect(page.locator('[data-set-row="0"] [data-set-side="home"]')).toHaveValue('1');
    await page.locator('[data-set-row="0"] [data-set-side="home"]').fill('21');
    await page.locator('[data-set-row="0"] [data-set-side="away"]').fill('17');
    await page.locator('[data-save-set="0"]').click();
    await expect(page.locator('#set-team1-wins')).toHaveText('1');

    await page.locator('#set-add').click();
    await expect(page.locator('[data-set-row]')).toHaveCount(2);
    await page.locator('[data-set-row="1"] [data-set-side="home"]').fill('18');
    await page.locator('[data-set-row="1"] [data-set-side="away"]').fill('21');
    await page.locator('[data-save-set="1"]').click();
    await expect(page.locator('#set-team2-wins')).toHaveText('1');

    page.once('dialog', dialog => dialog.accept());
    await page.locator('[data-delete-set="1"]').click();
    await expect(page.locator('[data-set-row]')).toHaveCount(1);
    await page.locator('#set-add').click();
    await page.locator('[data-set-row="1"] [data-set-side="home"]').fill('21');
    await page.locator('[data-set-row="1"] [data-set-side="away"]').fill('19');
    await page.locator('[data-save-set="1"]').click();
    await expect(page.locator('#set-team1-wins')).toHaveText('2');
    await expect(page.locator('#set-finish')).toBeEnabled();
    await expect(page.locator('#set-history-count')).not.toHaveText('0 รายการ');
    await page.screenshot({ path: path.join(artifacts, 'set-live-desktop.png'), fullPage: true });

    page.once('dialog', dialog => dialog.accept());
    await page.locator('#set-finish').click();
    await expect(page.locator('#set-status')).toContainText('จบแล้ว');
    await expect(page.locator('#set-result-admin')).toBeVisible();
    await page.locator('[data-result-set="0"] [data-result-side="away"]').fill('18');
    await page.locator('#set-result-reason').fill('แก้ไขตามใบบันทึกกรรมการ');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#set-result-update').click();
    await expect(page.locator('#set-result-revision')).toHaveText('Revision 2');
    await page.screenshot({ path: path.join(artifacts, 'set-result-desktop.png'), fullPage: true });

    await page.locator('#set-result-reason').fill('เปิดแมตช์เพื่อแก้ไขข้อมูลเดิม');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#set-result-delete').click();
    await expect(page.locator('#set-status')).toContainText('กำลังแข่งขัน');
    await expect(page.locator('#set-result-admin')).toBeHidden();
    await expect(page.locator('[data-set-row]')).toHaveCount(2);

    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.screenshot({ path: path.join(artifacts, 'set-mobile.png'), fullPage: true });
    expect(errors).toEqual([]);
    console.log(`PASS SET routing, set CRUD snapshots, rule validation, official result CRUD, and responsive layout. Screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    const exited = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await exited;
    await rm(db, { force: true });
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
