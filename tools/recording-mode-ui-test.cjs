const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdir, rm } = require('node:fs/promises');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = path.join(os.tmpdir(), `prsport-recording-${Date.now()}`);
  await mkdir(artifacts, { recursive: true });
  const db = path.join(artifacts, 'recording.sqlite');
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
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base);
    await page.locator('#login-toggle').click();
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    await page.evaluate(() => showView('sports'));
    await page.getByRole('button', { name: 'เพิ่มกีฬา', exact: true }).click();
    await page.locator('#sport-form [name=name]').fill('กีฬาแบบใดก็ได้');
    await page.locator('#sport-scoring-type').selectOption('SET');
    await page.screenshot({ path: path.join(artifacts, 'sport-select-desktop.png') });
    await page.locator('#sport-form [type=submit]').click();
    await expect(page.locator('#sport-form-dialog')).toBeHidden();
    const types = ['SCORE', 'SET', 'ROUND', 'TIME', 'DISTANCE', 'HEIGHT', 'JUDGED', 'TARGET', 'COUNT', 'LOWEST', 'DECISION', 'COMBINED'];
    const matches = await page.evaluate(async types => {
      const existing = (await request('sports.list')).find(row => row.name === 'กีฬาแบบใดก็ได้');
      const sports = [existing];
      for (const type of types.slice(2)) sports.push(await request('sports.save', {
        name: `กีฬาอิสระ ${type}`, type: 'team', gender: 'open', min_players: 1, max_players: 2, icon: 'football', scoring_type: type,
      }));
      sports.unshift(await request('sports.save', {
        name: 'คะแนนอิสระ', type: 'team', gender: 'open', min_players: 1, max_players: 2, icon: 'football', scoring_type: 'SCORE',
      }));
      const tournament = await request('tournaments.save', { name: '12 recording modes', sport_ids: sports.map(row => row.id), format: 'round_robin' });
      const result = {};
      for (let i = 0; i < types.length; i++) {
        const sport = sports[i];
        const home = await request('teams.save', { name: `${types[i]} Home`, tournament_id: tournament.id, sport_id: sport.id, department_code: '130' });
        const away = await request('teams.save', { name: `${types[i]} Away`, tournament_id: tournament.id, sport_id: sport.id, department_code: '140' });
        result[types[i]] = await request('matches.save', { tournament_id: tournament.id, sport_id: sport.id, team1_id: home.id, team2_id: away.id, round_name: 'ทดสอบฟอร์ม' });
      }
      return { result, tournament };
    }, types);
    await page.evaluate(() => showView('official'));
    await page.locator(`[data-official-match="${matches.result.SCORE.id}"]`).click();
    await expect(page).toHaveURL(new RegExp(`/public/scoring/score\\.php\\?match_id=${matches.result.SCORE.id}$`));
    await expect(page.locator('#score-workspace')).toBeVisible();
    await page.goto(`${base}/?view=official`);
    await expect(page.locator('#view-official')).toBeVisible();
    await page.locator(`[data-official-match="${matches.result.SET.id}"]`).click();
    await expect(page).toHaveURL(new RegExp(`/public/scoring/set\\.php\\?match_id=${matches.result.SET.id}$`));
    await expect(page.locator('#set-workspace')).toBeVisible();
    await page.goto(`${base}/?view=official`);
    await expect(page.locator('#view-official')).toBeVisible();
    await expect(page.locator('#match-center-panel, #result-form, #mode-recording, #race-center')).toHaveCount(0);
    for (const type of types.slice(2)) {
      await page.locator(`[data-official-match="${matches.result[type].id}"]`).click();
      await expect(page.locator('#toast')).toContainText(`หน้าบันทึก ${type} ยังอยู่ระหว่างจัดทำ`);
      await expect(page).toHaveURL(new RegExp('/\\?view=official$'));
    }
    await page.screenshot({ path: path.join(artifacts, 'dedicated-routing-only-desktop.png') });
    expect(errors).toEqual([]);
    console.log(`PASS dedicated SCORE/SET routing, legacy recording UI removal, and unsupported-mode blocking. Screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    const exited = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await exited;
    await rm(db, { force: true });
  }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
