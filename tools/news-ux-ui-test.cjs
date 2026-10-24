const { chromium, expect } = require('@playwright/test');
const { spawn } = require('node:child_process');
const { mkdir, rm, readFile } = require('node:fs/promises');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');

async function runNewsUxFlow({ page, base, artifacts, generatedUploads, root, cleanupFixtures = false }) {
  const counts = new Map();
  const uploads = [];
  const onRequest = request => {
    const action = new URL(request.url()).searchParams.get('action');
    if (action) counts.set(action, (counts.get(action) || 0) + 1);
  };
  const onResponse = response => {
    if (!response.url().includes('action=news.upload')) return;
    uploads.push(response.json().then(body => {
      if (body.ok && body.data.name) generatedUploads.push(path.join(root, 'public/uploads/news', path.basename(body.data.name)));
    }).catch(() => {}));
  };
  page.on('request', onRequest);
  page.on('response', onResponse);
  const capture = async name => {
    await expect(page.locator('#page-progress')).toBeHidden();
    await page.evaluate(() => { document.activeElement?.blur(); scrollTo({top:0,behavior:'instant'}); });
    await page.screenshot({ path: path.join(artifacts, name + '.png'), animations: 'disabled', style: '#toast { visibility: hidden; }' });
  };
  const checkWidth = async () => expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
  // Do not await a navigation promise while its confirmation waits for the next UI action.
  const go = view => page.evaluate(name => { void showView(name, { history: 'push' }); }, view);
  const confirm = name => page.locator('.confirm-dialog').getByRole('button', { name, exact: true }).click();
  const saveDraft = async () => {
    const response = page.waitForResponse(item => item.url().includes('action=news.save'));
    await page.locator('#news-save-draft').click();
    const body = await (await response).json();
    expect(body.ok, JSON.stringify(body)).toBe(true);
    await expect(page.locator('#news-save-feedback')).toContainText('บันทึกฉบับร่างแล้ว');
    await expect(page.locator('#news-form')).not.toHaveAttribute('aria-busy', 'true');
    return body.data;
  };
  let fixture, article, honor;
  try {
    fixture = await page.evaluate(async () => {
      const sport = await request('sports.save', { name: 'ฟุตซอลประชาสัมพันธ์', type: 'team', gender: 'open', icon: 'football', min_players: 1, max_players: 5, reserve_players: 0, scoring_type: 'SCORE' });
      const tournament = await request('tournaments.save', { name: 'การแข่งขันกีฬาคณะครุศาสตร์อุตสาหกรรม', sport_ids: [sport.id], format: 'round_robin', status: 'data_entry' });
      const home = await request('teams.save', { tournament_id: tournament.id, sport_id: sport.id, name: 'ทีมวิศวกรรม', department_code: '130' });
      const away = await request('teams.save', { tournament_id: tournament.id, sport_id: sport.id, name: 'ทีมครุศาสตร์', department_code: '140' });
      const athlete = await request('students.save', { first_name: 'กิตติ', last_name: 'ใจดี', department_code: '130' });
      await request('teamMembers.save', { team_id: home.id, student_id: athlete.id, role: 'player', shirt_number: '9' });
      const scheduled = await request('matches.save', { tournament_id: tournament.id, sport_id: sport.id, team1_id: home.id, team2_id: away.id, round_name: 'รอบแบ่งกลุ่ม', match_number: 1, scheduled_at: '2026-11-01T18:30', venue: 'สนามกีฬาคณะ' });
      const completed = await request('matches.save', { tournament_id: tournament.id, sport_id: sport.id, team1_id: home.id, team2_id: away.id, round_name: 'รอบชิงชนะเลิศ', match_number: 2, scheduled_at: '2026-10-23T18:30', venue: 'สนามกีฬาคณะ' });
      await request('matches.result', { match_id: completed.id, scoring_type: 'SCORE', team1_score: 3, team2_score: 1 });
      return { sport, tournament, home, away, athlete, scheduled, completed };
    });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await go('news');
    await expect(page).toHaveURL(/view=news$/);
    await expect(page.locator('#view-news h2')).toHaveText('ข่าวสารและประชาสัมพันธ์');
    await expect(page.locator('#news-form-dialog')).toHaveCount(0);
    await expect(page.locator('#news-table th')).toHaveText(['ข่าว', 'สถานะ', 'วันที่เผยแพร่', 'จัดการ']);
    await page.locator('#news-table input[type=search]').fill('no-such-news-ux-item');
    await expect(page.locator('#news-table .empty-state')).toBeVisible();
    await page.locator('#news-table input[type=search]').fill('');
    await page.locator('#news-table select[aria-label="กรองประเภทข่าว"]').selectOption('hall_of_fame');
    await expect(page.locator('#news-table .empty-state')).toBeVisible();
    await page.locator('#news-table select[aria-label="กรองประเภทข่าว"]').selectOption('');
    await page.locator('#news-create-button').click();
    await expect(page.locator('#view-news-editor')).toBeVisible();
    await expect(page.locator('#news-form')).toBeVisible();
    await expect(page).toHaveURL(/view=news-editor$/);
    await expect(page.locator('.tab[data-view-button=news]')).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#news-ai-panel')).toHaveAttribute('open', '');
    await expect(page.locator('#news-competition-panel')).not.toHaveAttribute('open', '');
    await page.locator('#news-save-draft').click();
    await expect(page.locator('#news-form .form-error')).toContainText('หัวข้อข่าว');
    expect(counts.get('news.save') || 0).toBe(0);

    const title = 'เตรียมพร้อมการแข่งขันกีฬาคณะ';
    const body = 'ร่วมติดตามการแข่งขันของนักกีฬาในรายการประจำคณะ\n\nทีมงานพร้อมรายงานข่าวและข้อมูลการแข่งขันจากระบบ';
    await page.locator('#news-form [name=title]').fill(title);
    await page.locator('#news-excerpt-settings > summary').click();
    await page.locator('#news-form [name=excerpt]').fill('ติดตามความพร้อมของทีมและกำหนดการแข่งขัน');
    await page.locator('#news-form [name=body]').fill(body);
    await capture('news-editor-manual-desktop');
    await expect(page.locator('[name=news_creation_method]')).toHaveCount(0);
    for (const panel of ['news-competition-panel', 'news-ai-panel', 'news-ai-panel', 'news-competition-panel']) {
      await page.locator(`#${panel} > summary`).click();
      await expect(page.locator('#news-form [name=title]')).toHaveValue(title);
      await expect(page.locator('#news-form [name=body]')).toHaveValue(body);
    }
    expect(counts.get('ai.news') || 0).toBe(0);
    const firstSaves = counts.get('news.save') || 0;
    article = await saveDraft();
    expect(counts.get('news.save')).toBe(firstSaves + 1);
    expect(article.status).toBe('draft');
    await expect(page).toHaveURL(new RegExp('view=news-editor&id=' + article.id + '$'));
    expect(await page.evaluate(id => request('public.news', { id }), article.id)).toEqual([]);

    // A slow response must not silently mark newer typing as persisted.
    let releaseSave, reachedSave;
    const holdSave = new Promise(resolve => {releaseSave=resolve;});
    const saveReached = new Promise(resolve => {reachedSave=resolve;});
    const slowSaveRoute = '**/api.php?action=news.save';
    await page.route(slowSaveRoute, async route => {reachedSave();await holdSave;await route.continue();});
    await page.locator('#news-save-draft').click();
    await saveReached;
    await expect(page.locator('#news-publish-button')).toBeDisabled();
    const laterBody = body + '\n\nข้อความที่พิมพ์ระหว่างบันทึก';
    await page.locator('#news-form [name=body]').fill(laterBody);
    releaseSave();
    await expect(page.locator('#news-form')).not.toHaveAttribute('aria-busy','true');
    await expect(page.locator('#news-save-feedback')).toContainText('ยังมีข้อมูล');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(laterBody);
    const stored = await page.evaluate(async id => (await request('news.list')).find(row => String(row.id) === String(id)), article.id);
    expect(stored.body).toBe(body);
    await page.unroute(slowSaveRoute);
    await page.locator('#news-form [name=body]').fill(body);

    // The optional topic mode uses the real endpoint without borrowing an unrelated match.
    await page.locator('[data-news-kind=result]').click();
    await expect(page.locator('#news-form [name=body]')).toHaveValue(body);
    await page.locator('[data-news-kind=general]').click();
    await page.locator('[data-news-ai-open]').click();
    await page.locator('#news-ai-mode').selectOption('topic');
    await expect(page.locator('#news-ai-match-fields')).toBeHidden();
    const topic = 'เชิญร่วมกิจกรรมกีฬาเพื่อสุขภาพ สนามกีฬาคณะ กรุณาติดตามกำหนดการจากผู้จัด';
    await page.locator('#news-ai-topic').fill(topic);
    await page.locator('#news-ai-button').click();
    await expect(page.locator('#news-draft-body')).toHaveValue(topic);
    await expect(page.locator('#news-form [name=body]')).toHaveValue(body);
    await expect(page.locator('#news-ai-status')).toContainText('ไม่ได้สร้างด้วย AI');
    await page.locator('#news-draft-verified').check();
    await page.locator('#news-draft-append').click();
    await expect(page.locator('#news-form [name=body]')).toHaveValue(body + '\n\n' + topic);
    await expect(page.locator('#news-form [name=title]')).toHaveValue(title);
    await page.locator('#news-form [name=body]').fill(body);
    await page.locator('#news-ai-button').click();
    await expect(page.locator('#news-ai-panel')).not.toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('#news-draft-review')).toBeVisible();
    await page.locator('#news-draft-verified').check();
    await page.locator('#news-draft-replace').click();
    await confirm('กลับ');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(body);
    await page.locator('#news-draft-replace').click();
    await confirm('นำร่างมาใช้');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(topic);
    await expect(page.locator('#news-form [name=title]')).toHaveValue(title);
    await expect(page.locator('#news-form [name=excerpt]')).toHaveValue('ติดตามความพร้อมของทีมและกำหนดการแข่งขัน');
    await page.locator('#news-form [name=body]').fill(body);
    await page.locator('#news-ai-mode').selectOption('match');

    // Preview uses text-safe DOM and never invokes save/upload/publication.
    const savesBeforePreview = counts.get('news.save');
    await page.locator('#news-form [name=title]').fill('<img src=x onerror="window.newsXss=1">');
    await page.locator('#news-form [name=body]').fill('<script>window.newsXss=1</script>\n\nข้อความตัวอย่าง');
    await page.locator('#news-advanced-settings summary').click();
    await page.locator('#news-form [name=source_url]').fill('javascript:alert(1)');
    await page.locator('#news-preview-button').click();
    await expect(page.locator('#view-news-preview')).toBeVisible();
    await expect(page.locator('#news-preview-content h1')).toHaveText('<img src=x onerror="window.newsXss=1">');
    await expect(page.locator('#news-preview-content script, #news-preview-content h1 img, #news-preview-content a')).toHaveCount(0);
    expect(await page.evaluate(() => window.newsXss || 0)).toBe(0);
    expect(counts.get('news.save')).toBe(savesBeforePreview);
    await page.locator('#news-preview-back').click();
    await page.locator('#news-form [name=title]').fill(title);
    await page.locator('#news-form [name=body]').fill(body);
    await page.locator('#news-form [name=source_url]').fill('https://www.rmutt.ac.th/');

    await page.locator('#news-competition-panel > summary').click();
    await page.locator('[data-news-reference=tournament_id]').selectOption(String(fixture.tournament.id));
    await page.locator('[data-news-reference=sport_id]').selectOption(String(fixture.sport.id));
    await expect(page.locator('#news-form [name=tournament_id]')).toHaveValue(String(fixture.tournament.id));
    await expect(page.locator('#news-form [name=sport_id]')).toHaveValue(String(fixture.sport.id));
    await page.locator('#news-team-link').selectOption(String(fixture.home.id));
    await page.locator('#news-match-link').selectOption(String(fixture.scheduled.id));
    await expect(page.locator('#news-match-summary')).toContainText('ยังไม่ยืนยันผล');
    await expect(page.locator('#news-form [name=team1_score], #news-form [name=team2_score]')).toHaveCount(0);
    await page.locator('#news-from-match-button').click();
    await expect(page.locator('#news-draft-review')).toBeVisible();
    await expect(page.locator('#news-draft-body')).toHaveValue(/ผลยังไม่ได้รับการยืนยัน/);
    await expect(page.locator('#news-form [name=body]')).toHaveValue(body);
    await page.locator('#news-draft-apply').click();
    await confirm('กลับ');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(body);
    await page.locator('#news-draft-apply').click();
    await confirm('นำร่างมาใช้');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(/ผลยังไม่ได้รับการยืนยัน/);
    await expect(page.locator('#news-competition-panel')).toHaveAttribute('open', '');
    await page.locator('[data-news-ai-open]').click();
    await page.locator('#news-ai-options').evaluate(element => {element.open=true;});
    await page.locator('#news-ai-story-type').selectOption('result');
    const aiBeforeUnconfirmed = counts.get('ai.news') || 0;
    await page.locator('#news-ai-button').click();
    await expect(page.locator('#toast')).toContainText('ผลยังไม่ยืนยัน');
    expect(counts.get('ai.news') || 0).toBe(aiBeforeUnconfirmed);

    await page.locator('#news-match-link').selectOption(String(fixture.completed.id));
    await expect(page.locator('#news-match-summary .confirmed')).toHaveText('ยืนยันผลแล้ว');
    await expect(page.locator('#news-match-summary')).toContainText('3 - 1');
    await expect(page.locator('[data-news-reference=match_id]')).toHaveValue(String(fixture.completed.id));
    const contentBeforeAi = await page.locator('#news-form [name=body]').inputValue();
    await page.locator('#news-ai-tone').selectOption('energetic');
    await page.locator('#news-ai-instruction').fill('ชื่นชมนักกีฬาและขอบคุณผู้ชม');
    await page.locator('#news-ai-button').click();
    await expect(page.locator('#news-draft-source')).toContainText('local-summary');
    await expect(page.locator('#news-ai-status')).toContainText('ไม่ได้สร้างด้วย AI');
    await page.locator('#news-ai-options').evaluate(element => {element.open=false;});
    await expect(page.locator('#news-form [name=body]')).toHaveValue(contentBeforeAi);
    await page.setViewportSize({width:1536,height:1024});
    await capture('news-social-editor-ai-1536');
    await page.setViewportSize({width:1440,height:1000});
    expect(counts.get('ai.news')).toBe(aiBeforeUnconfirmed + 1);
    await page.locator('#news-draft-apply').click();
    await expect(page.locator('#toast')).toContainText('ทำเครื่องหมายยืนยัน');
    await page.locator('#news-draft-verified').check();
    await page.locator('#news-draft-apply').click();
    await confirm('นำร่างมาใช้');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(/3 - 1/);
    const aiBody = await page.locator('#news-form [name=body]').inputValue();
    const editedBody = aiBody + '\n\nทีมงานขอขอบคุณนักกีฬาและผู้ชมทุกท่าน';
    await page.locator('#news-form [name=body]').fill(editedBody);
    await page.locator('#news-form [name=is_pinned]').check();

    await page.locator('#banner-open-button').click();
    await expect(page.locator('#view-news-banner')).toBeVisible();
    await expect(page.locator('dialog[open]')).toHaveCount(0);
    await page.locator('#banner-template').selectOption('result');
    await page.locator('#banner-headline').fill('ทีมวิศวกรรม 3 – 1 ทีมครุศาสตร์');
    await page.locator('#banner-element-scale').evaluate(element => { element.value = '115'; element.dispatchEvent(new Event('input', { bubbles: true })); });
    await expect(page.locator('#banner-element-scale-value')).toHaveText('115%');
    await expect.poll(() => page.locator('#banner-canvas').evaluate(canvas => canvas.toDataURL().length)).toBeGreaterThan(10000);
    expect(await page.evaluate(() => bannerStudio.bounds.headline.y + bannerStudio.bounds.headline.height <= bannerStudio.bounds.subtitle.y - 16)).toBe(true);
    await capture('news-banner-desktop');
    const downloadEvent = page.waitForEvent('download');
    await page.locator('#banner-download-button').click();
    const download = await downloadEvent;
    expect(download.suggestedFilename()).toMatch(/^prsport-banner-.*\.png$/);
    const png = await readFile(await download.path());
    expect(png.readUInt32BE(16)).toBe(1200);
    expect(png.readUInt32BE(20)).toBe(675);
    await page.locator('#banner-close-button').click();
    await expect(page.locator('#news-form [name=body]')).toHaveValue(editedBody);
    await page.locator('#banner-open-button').click();
    await expect(page.locator('#banner-headline')).toHaveValue('ทีมวิศวกรรม 3 – 1 ทีมครุศาสตร์');
    await expect(page.locator('#banner-element-scale')).toHaveValue('115');
    const uploadEvent = page.waitForResponse(response => response.url().includes('action=news.upload'));
    await page.locator('#banner-use-button').click();
    const uploaded = await (await uploadEvent).json();
    expect(Object.keys(uploaded.data).sort()).toEqual(['id', 'name', 'thumbnail_url', 'url']);
    await expect(page.locator('#view-news-editor')).toBeVisible();
    await expect(page.locator('#news-cover-preview img')).toBeVisible();
    await expect(page.locator('#news-form [name=body]')).toHaveValue(editedBody);
    await expect(page.locator('#news-form [name=match_id]')).toHaveValue(String(fixture.completed.id));
    article = await saveDraft();
    expect(article.cover_image_url).toBe(uploaded.data.url);
    await page.locator('#news-preview-button').click();
    await expect(page.locator('#news-preview-content .article-body')).toContainText('ทีมงานขอขอบคุณ');
    await expect(page.locator('#news-preview-content img')).toBeVisible();
    await capture('news-preview-desktop');
    await page.locator('#news-preview-back').click();
    const savesBeforePublish = counts.get('news.save');
    await page.locator('#news-publish-button').click();
    await confirm('กลับ');
    expect(counts.get('news.save')).toBe(savesBeforePublish);
    await page.locator('#news-publish-button').click();
    const publishResponse = page.waitForResponse(response => response.url().includes('action=news.save'));
    await confirm('เผยแพร่');
    expect((await (await publishResponse).json()).ok).toBe(true);
    await expect(page.locator('#news-editor-status')).toHaveText('เผยแพร่แล้ว');
    await expect(page.locator('#news-save-feedback')).toContainText('บันทึกและเผยแพร่แล้ว');
    await go('news');
    let row = page.locator('#news-table tbody tr').filter({ has: page.locator(`[data-news-edit="${article.id}"]`) });
    await expect(row.locator('.news-pinned')).toHaveCount(1);
    await expect(row.locator('img')).toBeVisible();
    await capture('news-management-desktop');

    const menuAction = async action => {
      await row.locator('[data-news-menu]').click();
      await page.locator(`#news-action-menu [data-news-action=${action}]`).click();
    };
    await menuAction('unpublish');
    await confirm('ยกเลิกเผยแพร่');
    await expect(row.locator('[data-column=status]')).toHaveText('ฉบับร่าง');
    expect(await page.evaluate(id => request('public.news', { id }), article.id)).toEqual([]);
    await menuAction('pin');
    await expect(row.locator('.news-pinned')).toHaveCount(0);
    const unpinned = await page.evaluate(async id => (await request('news.list')).find(item => String(item.id) === String(id)), article.id);
    expect(Number(unpinned.is_pinned)).toBe(0);
    expect(String(unpinned.match_id)).toBe(String(fixture.completed.id));
    await menuAction('publish');
    await confirm('เผยแพร่');
    await expect(row.locator('[data-column=status]')).toHaveText('เผยแพร่แล้ว');
    const publicRow = await page.evaluate(id => request('public.news', { id }), article.id);
    expect(publicRow).toHaveLength(1);
    await page.evaluate(async id => {
      const url = new URL(location.href); url.search = new URLSearchParams({ view: 'public-news-detail', id });
      history.pushState({}, '', url); await showView('public-news-detail', { history: 'none' });
    }, String(article.id));
    await expect(page.locator('#article-title')).toHaveText(publicRow[0].title);
    await expect(page.locator('#public-news-detail .article-body')).toContainText('ทีมงานขอขอบคุณ');
    await go('news');
    await row.locator('[data-news-edit]').click();
    await expect(page.locator('#news-form [name=body]')).toHaveValue(editedBody);
    // A failed save must preserve the current editor and show a usable error.
    await page.locator('#news-form [name=title]').fill('การแก้ไขที่ยังไม่บันทึก');
    await go('dashboard');
    await expect(page.locator('.confirm-dialog')).toBeVisible();
    await confirm('กลับ');
    await expect(page.locator('#view-news-editor')).toBeVisible();
    await expect(page.locator('#news-form [name=title]')).toHaveValue('การแก้ไขที่ยังไม่บันทึก');
    await go('news');
    await confirm('ออกโดยไม่บันทึก');
    await expect(page.locator('#view-news')).toBeVisible();

    for (const width of [320, 390, 768]) {
      await page.setViewportSize({ width, height: 844 });
      await checkWidth();
      await capture(`news-management-${width}`);
      await row.locator('[data-news-edit]').click();
      await expect(page.locator('#news-form')).toBeVisible();
      await checkWidth();
      await expect(page.locator('#news-publish-button')).toBeInViewport();
      await capture(`news-editor-${width}`);
      await page.locator('[data-news-ai-open]').click();
      await expect(page.locator('#news-ai-panel')).toHaveAttribute('role', 'dialog');
      await expect(page.locator('#news-ai-backdrop')).toBeVisible();
      await page.locator('#news-ai-mode').selectOption('topic');
      await page.locator('#news-ai-topic').fill('ข้อมูลร่างยังอยู่เมื่อปิดผู้ช่วย');
      await capture(`news-ai-drawer-${width}`);
      await page.locator('#news-ai-close').click();
      await expect(page.locator('#news-ai-backdrop')).toBeHidden();
      await expect(page.locator('#news-form [name=body]')).toHaveValue(editedBody);
      await page.locator('[data-news-ai-open]').click();
      await expect(page.locator('#news-ai-topic')).toHaveValue('ข้อมูลร่างยังอยู่เมื่อปิดผู้ช่วย');
      await page.keyboard.press('Escape');
      await expect(page.locator('#news-ai-backdrop')).toBeHidden();
      await page.locator('#banner-open-button').click();
      await expect(page.locator('#view-news-banner')).toBeVisible();
      await checkWidth();
      await capture(`news-banner-${width}`);
      await page.locator('#banner-close-button').click();
      await expect(page.locator('#news-form [name=body]')).toHaveValue(editedBody);
      await go('news');
    }
    await page.setViewportSize({ width: 1440, height: 1000 });

    // Cover upload is a real file -> original news.upload API, separate from generated covers.
    await page.locator('#news-create-button').click();
    await expect(page.locator('#news-form')).toBeVisible();
    await expect(page.locator('#news-form [name=id]')).toHaveValue('');
    await page.locator('#news-form [name=title]').fill('ข่าวเกียรติประวัติ');
    await page.locator('#news-form [name=body]').fill('แสดงผลงานที่ตรวจสอบแล้วจากทีมงานประชาสัมพันธ์');
    await page.locator('#news-type-select').selectOption('hall_of_fame');
    await expect(page.locator('#news-honor-help')).toBeVisible();
    await page.locator('#news-honor-load').click();
    await expect(page.locator('#news-honor-data-state')).toContainText('ใช้เฉพาะรายชื่อ');
    await page.locator('#news-honor-athlete').selectOption(String(fixture.home.id) + ':' + String(fixture.athlete.id));
    await expect(page.locator('#news-honor-name')).toHaveValue(/กิตติ ใจดี/);
    await page.locator('#news-honor-achievement').fill('รางวัลนักกีฬายอดเยี่ยม');
    await page.locator('#news-honor-apply').click();
    await expect(page.locator('#news-form [name=body]')).toHaveValue(/กิตติ ใจดี/);
    await page.locator('#news-file').setInputFiles({ name: 'news-ux-upload.png', mimeType: 'image/png', buffer: png });
    await expect(page.locator('#news-cover-preview img')).toBeVisible();
    await page.locator('#news-cover-remove').click();
    await expect(page.locator('#news-cover-preview img')).toHaveCount(0);
    await expect(page.locator('#news-cover-url')).toHaveValue('');
    await page.locator('#news-file').setInputFiles({ name: 'news-ux-upload.png', mimeType: 'image/png', buffer: png });
    await expect(page.locator('#news-cover-preview img')).toBeVisible();
    honor = await saveDraft();
    expect(String(honor.id)).not.toBe(String(article.id));
    expect(honor.body).toContain('รางวัลนักกีฬายอดเยี่ยม');
    await go('news');
    await page.locator('#news-table select[aria-label="กรองประเภทข่าว"]').selectOption('hall_of_fame');
    await expect(page.locator('#news-table tbody tr')).toHaveCount(1);
    await expect(page.locator('#news-table tbody tr')).toContainText('ข่าวเกียรติประวัติ');
    await page.locator('#news-table select[aria-label="กรองประเภทข่าว"]').selectOption('');
    await row.locator('[data-news-menu]').click();
    await page.locator('#news-action-menu [data-news-action=archive]').click();
    await confirm('เก็บเข้าคลัง');
    await expect(row.locator('[data-column=status]')).toHaveText('เก็บเข้าคลัง');
    await page.locator('#news-table select[aria-label="กรองสถานะ"]').selectOption('archived');
    await expect(page.locator('#news-table tbody tr')).toHaveCount(1);
    await page.locator('#news-table select[aria-label="กรองสถานะ"]').selectOption('');
    await row.locator('[data-news-edit]').click();
    await expect(page.locator('#news-editor-status')).toHaveText('เก็บเข้าคลัง');
    const failureRoute = '**/api.php?action=news.save';
    await page.route(failureRoute, route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: 'บันทึกไม่สำเร็จในการทดสอบ' }) }));
    await page.locator('#news-save-draft').click();
    await expect(page.locator('#news-form .form-error')).toContainText('บันทึกไม่สำเร็จ');
    await expect(page.locator('#news-form [name=body]')).toHaveValue(editedBody);
    await page.unroute(failureRoute);
    await saveDraft();
    await go('news');

    // Delete confirmation can be cancelled; clean up only records created by this test.
    await row.locator('[data-news-menu]').click();
    await page.locator('#news-action-menu [data-news-action=delete]').click();
    await confirm('กลับ');
    await expect(row).toBeVisible();
    await row.locator('[data-news-menu]').click();
    await page.locator('#news-action-menu [data-news-action=delete]').click();
    await confirm('ลบข่าว');
    await expect(row).toHaveCount(0);
    await page.evaluate(id => request('news.delete', { id }), honor.id);
    await go('news');
    await checkNewsPermissions(page, base);
    if (cleanupFixtures) {
      await page.evaluate(async f => {
        for (const match of [f.scheduled, f.completed]) await request('matches.delete', { id: match.id });
        // Keep teams with recalculated standings until the isolated SQLite fixture is discarded.
        // Do not change production deletion rules to accommodate test teardown.
      }, fixture);
    }
    console.log('PASS news UX: manual/match/AI review, unconfirmed-result guard, preserved text, Banner Studio/export/upload, draft/preview/publication/unpublication, pin, archive, XSS-safe preview, filters, discard/save errors, responsive 320/390/768/1440.');
  } catch (error) {
    await page.screenshot({path:path.join(artifacts,'news-failure.png'),animations:'disabled'}).catch(()=>{});
    console.error('News failure state:', await page.evaluate(()=>({pending:Boolean(newsUi.pendingDraft),ai:newsUi.pendingDraft?.ai,verified:document.querySelector('#news-draft-verified').checked,error:document.querySelector('#news-ai-error').textContent,toast:document.querySelector('#toast').textContent,mode:document.querySelector('#news-ai-mode').value,dialogs:[...document.querySelectorAll('dialog[open]')].map(e=>e.textContent)})));
    throw error;
  } finally {
    await Promise.all(uploads);
    page.off('request', onRequest);
    page.off('response', onResponse);
  }
}

async function checkNewsPermissions(page, base) {
  for (const role of ['public_relations', 'result_officer', 'sport_head', 'guest']) {
    let email;
    if (role !== 'guest') {
      email = role + '-news-' + Date.now() + '@news-ui-test.local';
      await page.evaluate(data => request('users.save', data), { email, name: role, role, password: 'Test@12345' });
    }
    const context = await page.context().browser().newContext();
    try {
      let session;
      if (email) session = await (await context.request.post(base + '/api.php?action=auth.login', { data: { email, password: 'Test@12345' } })).json();
      else session = await (await context.request.get(base + '/api.php?action=auth.current')).json();
      expect(session.ok).toBe(true);
      const headers = { 'X-CSRF-Token': session.data.csrf };
      if (role === 'public_relations') {
        const created = await (await context.request.post(base + '/api.php?action=news.save', { headers, data: { title: 'ข่าวจากฝ่ายประชาสัมพันธ์', slug: 'pr-role-' + Date.now(), status: 'draft' } })).json();
        expect(created.ok).toBe(true);
        const staffPage = await context.newPage();
        await staffPage.goto(base + '/?view=news-editor&id=' + created.data.id);
        await expect(staffPage.locator('#news-form')).toBeVisible();
        await expect(staffPage.locator('#news-form [name=title]')).toHaveValue('ข่าวจากฝ่ายประชาสัมพันธ์');
        expect(await staffPage.locator('#news-match-link option').count()).toBeGreaterThan(1);
        const published = await (await context.request.post(base + '/api.php?action=news.publish', { headers, data: { id: created.data.id } })).json();
        expect(published.data.status).toBe('published');
        await context.request.post(base + '/api.php?action=news.delete', { headers, data: { id: created.data.id } });
      } else {
        for (const action of ['news.list', 'news.save', 'news.publish', 'news.delete', 'news.upload', 'matches.forNews', 'ai.news']) {
          const denied = await context.request.post(base + '/api.php?action=' + action, { headers, data: { id: 1, title: 'Denied', role: 'super_admin' } });
          expect(denied.status(), role + ' / ' + action).toBe(403);
          const payload = await denied.json();
          expect(Object.keys(payload).sort()).toEqual(['error', 'ok']);
          expect(payload.ok).toBe(false);
        }
        if (role !== 'guest') {
          const blockedPage = await context.newPage();
          await blockedPage.goto(base + '/?view=news-editor&id=1');
          await expect(blockedPage.locator('#view-news-editor')).toBeHidden();
          await expect(blockedPage.locator('#toast')).toContainText('ไม่มีสิทธิ์');
        }
      }
    } finally { await context.close(); }
  }
  console.log('PASS news server permissions: PR editor/create/publish; guest, Sport Manager and Match Official denied all protected news APIs even with forged role payload.');
}

async function main() {
  const root = path.resolve(__dirname, '..');
  const artifacts = process.env.PRSPORT_UI_ARTIFACTS || path.join(os.tmpdir(), 'prsport-news-ux-' + Date.now());
  await mkdir(artifacts, { recursive: true });
  const db = path.join(artifacts, 'news-ux.sqlite');
  const port = await new Promise(resolve => { const socket = net.createServer(); socket.listen(0, '127.0.0.1', () => { const port = socket.address().port; socket.close(() => resolve(port)); }); });
  const server = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['-d', `upload_tmp_dir=${artifacts}`, '-d', `sys_temp_dir=${artifacts}`, '-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', root], {
    cwd: root, env: { ...process.env, PRSPORT_DB_DSN: `sqlite:${db}`, GEMINI_API_KEY: '', GEMINI_MODEL: 'local-summary' }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'],
  });
  const phpErrors = [];
  server.stderr.on('data', chunk => { if (/PHP (?:Warning|Notice|Fatal|Parse)|Uncaught /i.test(chunk)) phpErrors.push(chunk.toString()); });
  const generatedUploads = [];
  let browser;
  try {
    const base = `http://127.0.0.1:${port}`;
    for (let i = 0; i < 50; i++) { if (await fetch(base).then(r => r.ok).catch(() => false)) break; await new Promise(r => setTimeout(r, 100)); }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, acceptDownloads: true });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text()); });
    await page.goto(base + '/?view=news');
    await page.locator('#login-form [name=email]').fill('admin@rmutt.local');
    await page.locator('#login-form [name=password]').fill('Temp@1234');
    await page.locator('#login-form [type=submit]').click();
    await expect(page.locator('#view-dashboard')).toBeVisible();
    await runNewsUxFlow({ page, base, artifacts, generatedUploads, root });
    expect(errors).toEqual([]);
    expect(phpErrors).toEqual([]);
    console.log(`PASS: page identity/render, interactions, console/PHP health. URL: ${base}; screenshots: ${artifacts}`);
  } finally {
    if (browser) await browser.close();
    const exit = new Promise(resolve => server.once('exit', resolve)); server.kill(); await exit;
    await rm(db, { force: true });
    await Promise.all(generatedUploads.map(file => rm(file, { force: true })));
  }
}
module.exports = { runNewsUxFlow };
if (require.main === module) main().catch(error => { console.error(error); process.exitCode = 1; });
