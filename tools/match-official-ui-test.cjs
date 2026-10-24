const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdir, rm } = require('node:fs/promises');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = process.env.PRSPORT_UI_ARTIFACTS || path.join(os.tmpdir(), `prsport-official-${Date.now()}`);
  await mkdir(artifacts, { recursive: true });
  const db = path.join(artifacts, 'official.sqlite');
  const port = await new Promise(resolve => {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1', () => { const value = socket.address().port; socket.close(() => resolve(value)); });
  });
  const server = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', root], {
    cwd: root, env: { ...process.env, PRSPORT_DB_DSN: `sqlite:${db}` }, windowsHide: true, stdio: 'ignore',
  });
  let browser;
  try {
    const base = `http://127.0.0.1:${port}`;
    for (let i = 0; i < 50; i++) {
      if (await fetch(base).then(response => response.ok).catch(() => false)) break;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1672, height: 941 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base);
    await expect(page.locator('#view-public')).toBeVisible();
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    const fixture = await page.evaluate(async () => {
      const sport = await request('sports.save', { name: 'วิ่ง 100 เมตร UI test', type: 'individual', gender: 'open', min_players: 1, max_players: 1, icon: 'running', scoring_type: 'TIME' });
      const tournament = await request('tournaments.save', { name: 'Official UI race test', sport_ids: [sport.id], format: 'round_robin', status: 'data_entry' });
      const match = await request('matches.save', { tournament_id: tournament.id, sport_id: sport.id, round_name: 'วิ่ง 100 เมตร · ฮีต 1', scheduled_at: '2026-10-14T14:30' });
      const official = await request('users.save', { email: 'race-official@test.local', name: 'Race Official', role: 'result_officer', sport_ids: String(sport.id), password: 'Test@12345' });
      return { sport, tournament, match, official };
    });
    await page.evaluate(() => showView('matches'));
    await expect(page.locator('#match-assignment-panel')).toBeVisible();
    await page.locator('#assignment-match').selectOption(String(fixture.match.id));
    await page.locator('#assignment-user').selectOption(String(fixture.official.id));
    await page.locator('#match-assignment-form [type=submit]').click();
    await expect(page.locator('#match-assignments-table')).toContainText('Race Official');
    const relay = await page.evaluate(async ({ sport, tournament, official }) => {
      const home = await request('teams.save', { name: 'Relay A', tournament_id: tournament.id, sport_id: sport.id, department_code: '130' });
      const away = await request('teams.save', { name: 'Relay B', tournament_id: tournament.id, sport_id: sport.id, department_code: '140' });
      const match = await request('matches.save', { tournament_id: tournament.id, sport_id: sport.id,
        team1_id: home.id, team2_id: away.id, round_name: 'ผลัด 4 × 100 เมตร', scheduled_at: '2026-10-14T15:00' });
      await request('matches.assignOfficials', { match_id: match.id, assignments: [{ user_id: official.id }] });
      return match;
    }, fixture);
    await page.locator('#logout-button').click();
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('race-official@test.local');
    await page.locator('#login-form [name=password]').fill('Test@12345');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-official')).toBeVisible();
    await expect(page.locator('#match-center-panel, #race-center, #match-event-form, #result-form')).toHaveCount(0);
    await expect(page.locator('#official-center-host .official-routing-empty')).toBeVisible();
    await page.locator(`[data-official-match="${relay.id}"]`).click();
    await expect(page.locator('#toast')).toContainText('หน้าบันทึก TIME ยังอยู่ระหว่างจัดทำ');
    await expect(page).toHaveURL(new RegExp('/\\?view=official$'));
    await page.locator(`[data-official-match="${fixture.match.id}"]`).click();
    await expect(page.locator('#toast')).toContainText('ระบบเดิมถูกยกเลิกแล้ว');
    await page.screenshot({ path: path.join(artifacts, 'official-router-desktop.png') });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.screenshot({ path: path.join(artifacts, 'official-router-mobile.png') });
    expect(errors).toEqual([]);
    console.log(`PASS match assignment, routing-only official console, legacy UI removal, and mobile layout. Screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    const exited = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await exited;
    await rm(db, { force: true });
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
