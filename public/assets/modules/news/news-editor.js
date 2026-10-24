// Full-page news editor; shared state lives only for the current page session (no secrets/local storage).
const newsUi = { editing: false, record: null, snapshot: '', lastUrl: '', pendingDraft: null, appliedDraft: null, coverToken: 0, generation: 0, loadGeneration: 0, leaving: false, confirming: false, bannerDirty: false };
const newsPayloadKeys = ['id', 'title', 'slug', 'excerpt', 'body', 'cover_image_url', 'source_url', 'news_type', 'department_code', 'status', 'is_pinned', 'published_at', 'sport_id', 'tournament_id', 'team_id', 'match_id'];
const newsEditorViews = ['news-editor', 'news-preview', 'news-banner'];
const isNewsEditorView = name => newsEditorViews.includes(name);

function newsPayload() {
  const form = $('#news-form');
  return Object.fromEntries(newsPayloadKeys.map(key => {
    const field = form.elements[key];
    return [key, field?.type === 'checkbox' ? field.checked : field?.value || ''];
  }));
}

function newsPayloadFromRow(row) {
  return Object.fromEntries(newsPayloadKeys.map(key => [key, key === 'is_pinned' ? Number(row[key]) === 1 : row[key] ?? '']));
}

function safeNewsUrl(value, image = false) {
  const raw = String(value || '').trim();
  if (!raw) return '';
  if (image && /^data:image\/(?:png|jpeg|webp|gif);base64,[a-z0-9+/=\s]+$/i.test(raw)) return raw;
  try {
    const url = new URL(raw, window.location.href);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  } catch (_) { return ''; }
}

function isNewsDirty() {
  return newsUi.editing && (JSON.stringify(newsPayload()) !== newsUi.snapshot || $('#news-file').files.length > 0 || newsUi.bannerDirty || Boolean(newsUi.pendingDraft) || Boolean($('#news-honor-name').value.trim() || $('#news-honor-achievement').value.trim()));
}

function markNewsDirty() {
  $('#news-save-feedback').textContent = isNewsDirty() ? 'มีการเปลี่ยนแปลงที่ยังไม่บันทึก' : newsUi.record?.id ? 'บันทึกแล้ว' : 'ยังไม่ได้บันทึก';
}

async function guardNewsNavigation(name, options = {}) {
  if (!newsUi.editing) return true;
  if (actualRole() === 'guest') {
    newsUi.editing = false;
    newsUi.pendingDraft = null;
    $('#news-form').reset();
    return true;
  }
  const replacing = Object.prototype.hasOwnProperty.call(options, 'newsRecord');
  if (isNewsEditorView(name) && !replacing) return true;
  if (name === app.currentView && !replacing) return true;
  if ($('#news-form').dataset.busy === 'true' || newsUi.confirming) {
    toast('กำลังบันทึก กรุณารอสักครู่', true);
    return false;
  }
  if (isNewsDirty()) {
    if (newsUi.leaving) return false;
    newsUi.leaving = true;
    let leave = false;
    try {
      leave = await confirmAction('ออกจากข่าวที่ยังไม่บันทึก?', 'ข้อความ ร่างที่ยังไม่ได้นำมาใช้ และรูปปกที่ยังไม่บันทึกจะไม่ถูกเก็บลงระบบ', 'ออกโดยไม่บันทึก');
    } finally { newsUi.leaving = false; }
    if (!leave) {
      if (options.history === 'none' && newsUi.lastUrl) history.replaceState(history.state, '', newsUi.lastUrl);
      return false;
    }
  }
  newsUi.editing = false;
  newsUi.generation++;
  return true;
}

async function openNewsEditor(row = null, destination = 'news-editor') {
  return showView(destination, { history: 'push', newsRecord: row });
}

function syncNewsEditorRoute() {
  const url = new URL(window.location.href);
  const id = $('#news-form').elements.id.value;
  if (id) url.searchParams.set('id', id);
  else url.searchParams.delete('id');
  history.replaceState({ ...(history.state || {}), view: app.currentView }, '', url);
  newsUi.lastUrl = url.pathname + url.search;
}

async function loadNewsEditor(options = {}) {
  const forced = Object.prototype.hasOwnProperty.call(options, 'newsRecord');
  const routeId = new URLSearchParams(location.search).get('id') || '';
  if (!forced && newsUi.editing && String($('#news-form').elements.id.value) === routeId) {
    syncNewsEditorRoute();
    return true;
  }
  const error = $('#news-editor-load-error');
  const loadToken = ++newsUi.loadGeneration;
  error.hidden = true;
  $('#news-form').hidden = true;
  $('#news-save-feedback').textContent = 'กำลังเปิดข่าว…';
  try {
    await loadAdminRefs();
    await loadNewsReferences();
    let row = forced ? options.newsRecord : null;
    if (!forced && routeId) {
      app.news = await request('news.list');
      row = app.news.find(item => String(item.id) === routeId);
      if (!row) throw new Error('ไม่พบข่าว หรือข่าวถูกลบแล้ว กรุณากลับไปหน้ารายการข่าว');
    }
    if (loadToken !== newsUi.loadGeneration || !isNewsEditorView(app.currentView)) return null;
    prepareNewsEditor(row);
    syncNewsEditorRoute();
    return true;
  } catch (failure) {
    if (loadToken !== newsUi.loadGeneration || !isNewsEditorView(app.currentView)) return null;
    error.textContent = failure.message || 'เปิดข่าวไม่สำเร็จ';
    error.hidden = false;
    $('#news-form').hidden = true;
    return false;
  }
}

function prepareNewsEditor(row) {
  const form = $('#news-form');
  form.hidden = false;
  form.reset();
  // Hidden inputs use their current value as the reset default. Always clear identity explicitly
  // so creating a second article cannot overwrite the previously edited one.
  form.elements.id.value = row?.id || '';
  form.elements.status.value = row?.status || 'draft';
  form.elements.published_at.value = row?.published_at || '';
  newsUi.coverToken++;
  newsUi.honorPlayers = [];
  $('#news-honor-athlete').innerHTML = '<option value="">เลือกรายชื่อหลังโหลดข้อมูล</option>';
  $('#news-honor-data-state').textContent = '';
  newsUi.record = row ? { ...row } : null;
  newsUi.pendingDraft = null;
  newsUi.appliedDraft = null;
  newsUi.bannerDirty = false;
  newsUi.generation++;
  $('#news-draft-review').hidden = true;
  $('.form-error', form).hidden = true;
  $('#news-ai-panel').open = false;
  $('#news-advanced-settings').open = false;
  $('#news-template-select').value = '';
  for (const key of newsPayloadKeys) {
    const field = form.elements[key];
    if (!field || ['sport_id', 'tournament_id', 'team_id', 'match_id'].includes(key)) continue;
    if (field.type === 'checkbox') field.checked = Number(row?.[key]) === 1;
    else if (row && row[key] !== undefined && row[key] !== null) field.value = String(row[key]);
  }
  if (row?.department_code && ![...form.elements.department_code.options].some(option => option.value === row.department_code)) {
    form.elements.department_code.append(new Option(`ข้อมูลเดิม #${row.department_code}`, row.department_code));
    form.elements.department_code.value = row.department_code;
  }
  $('#news-competition-panel').open = Boolean(row?.match_id);
  syncNewsCompetitionOptions('', row || {});
  updateNewsCoverPreview(row?.cover_image_url || '');
  $('#banner-sport').value = '';
  $('#banner-headline').value = row?.title || '';
  $('#banner-subtitle').value = row?.excerpt || '';
  $('#banner-image-file').value = '';
  bannerStudio.photo = null;
  bannerStudio.newsSyncSnapshot = null;
  bannerStudio.coverSource = null;
  bannerStudio.textScales = { sport: 1, headline: 1, subtitle: 1 };
  setBannerTemplate('matchday');
  $('#news-ai-status').textContent = app.aiSettings?.configured ? `Gemini: ${app.aiSettings.default_model}` : 'ยังไม่ได้เชื่อมต่อ AI — จะใช้แม่แบบในระบบ';
  $('#news-ai-story-type').value = newsMatchIsOfficial(selectedNewsMatch()) ? 'result' : 'preview';
  $('#news-editor-title').textContent = row?.id ? 'แก้ไขข่าว' : 'สร้างข่าว';
  syncNewsCategory();
  syncNewsPublishingUi();
  newsUi.editing = true;
  newsUi.snapshot = JSON.stringify(newsPayload());
  markNewsDirty();
}

function syncNewsCategory() {
  const form = $('#news-form');
  $('#news-honor-help').hidden = form.elements.news_type.value !== 'hall_of_fame';
  $('#news-honor-panel').hidden = form.elements.news_type.value !== 'hall_of_fame';
  // Category changes never reset content, references or optional writing tools.
}

function syncNewsPublishingUi() {
  const status = $('#news-form').elements.status.value || 'draft';
  const badge = $('#news-editor-status');
  badge.className = `status-badge news-status-${['draft', 'published', 'archived'].includes(status) ? status : 'draft'}`;
  badge.textContent = workspace.labels[status] || 'ฉบับร่าง';
  $('#news-save-draft span').textContent = status === 'published' ? 'ยกเลิกเผยแพร่' : 'บันทึกร่าง';
  $('#news-publish-button span').textContent = status === 'published' ? 'บันทึกการแก้ไข' : 'เผยแพร่';
}

function validateNewsEditor() {
  const form = $('#news-form');
  for (const field of $$('input,select,textarea', form)) {
    if (!field.willValidate || field.checkValidity()) continue;
    let ancestor = field.parentElement;
    while (ancestor && ancestor !== form) {
      if (ancestor instanceof HTMLDetailsElement) ancestor.open = true;
      ancestor = ancestor.parentElement;
    }
    field.reportValidity();
    throw new Error(field.name === 'title' ? 'กรุณากรอกหัวข้อข่าว' : field.validationMessage || 'กรุณาตรวจสอบข้อมูล');
  }
  if (!form.elements.title.value.trim()) throw new Error('กรุณากรอกหัวข้อข่าว');
  const source = form.elements.source_url.value.trim();
  if (source && (!/^https?:\/\//i.test(source) || !safeNewsUrl(source))) {
    $('#news-advanced-settings').open = true;
    throw new Error('ลิงก์เว็บไซต์ต้องขึ้นต้นด้วย http:// หรือ https://');
  }
  if (form.elements.cover_image_url.value.trim() && !safeNewsUrl(form.elements.cover_image_url.value, true)) throw new Error('ลิงก์รูปปกไม่ถูกต้อง กรุณาใช้ http://, https:// หรือไฟล์รูปภาพที่รองรับ');
  const file = $('#news-file').files[0];
  if (file && (file.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type))) {
    throw new Error('กรุณาใช้ไฟล์ JPG, PNG, WebP หรือ GIF ขนาดไม่เกิน 5MB');
  }
}

async function saveNewsEditor(status) {
  const form = $('#news-form');
  if (form.dataset.busy === 'true' || newsUi.confirming) return;
  $('.form-error', form).hidden = true;
  try { validateNewsEditor(); } catch (error) {
    $('.form-error', form).textContent = error.message;
    $('.form-error', form).hidden = false;
    return;
  }
  const wasPublished = newsUi.record?.status === 'published';
  if (status === 'published' || wasPublished) {
    newsUi.confirming = true;
    try {
      const accepted = await confirmAction(
        status === 'published' ? (wasPublished ? 'บันทึกข่าวที่เผยแพร่แล้ว?' : 'เผยแพร่ข่าว?') : 'ยกเลิกเผยแพร่?',
        status === 'published' ? 'ตรวจทานชื่อ ข้อมูลการแข่งขัน และรูปปกแล้วหรือไม่ การบันทึกนี้จะแสดงบนเว็บไซต์ทันที' : 'ข่าวจะกลับเป็นฉบับร่างและไม่แสดงบนเว็บไซต์',
        status === 'published' ? (wasPublished ? 'บันทึกการแก้ไข' : 'เผยแพร่') : 'ยกเลิกเผยแพร่'
      );
      if (!accepted) return;
    } finally { newsUi.confirming = false; }
  }
  $('#news-save-feedback').textContent = 'กำลังบันทึก…';
  await runForm(form, async () => {
    if (status === 'published' && newsUi.appliedDraft?.match_id) {
      if ($('#news-match-link').value !== String(newsUi.appliedDraft.match_id)) throw new Error('แมตช์อ้างอิงเปลี่ยนจากร่างที่นำมาใช้ กรุณาตรวจทานและสร้างร่างใหม่ก่อนเผยแพร่');
      const current = await refreshNewsMatch(newsUi.appliedDraft.match_id);
      if (newsMatchFingerprint(current) !== newsUi.appliedDraft.sourceFingerprint) throw new Error('ข้อมูลผลการแข่งขันเปลี่ยนแล้ว กรุณาสร้างร่างและตรวจทานอีกครั้งก่อนเผยแพร่');
    }
    const file = $('#news-file').files[0];
    if (file) {
      const upload = await request('news.upload', { data_url: await fileToDataUrl(file), file_name: file.name });
      $('#news-cover-url').value = upload.url;
      $('#news-file').value = '';
      updateNewsCoverPreview(upload.url);
    }
    const payload = newsPayload();
    payload.status = status;
    if (status !== 'published') payload.published_at = '';
    const result = await request('news.save', payload);
    form.elements.id.value = result.id;
    form.elements.status.value = result.status;
    form.elements.published_at.value = result.published_at || '';
    if (result.slug) form.elements.slug.value = result.slug;
    newsUi.record = { ...payload, ...result };
    newsUi.snapshot = JSON.stringify(newsPayload());
    syncNewsEditorRoute();
    syncNewsPublishingUi();
    $('#news-editor-title').textContent = 'แก้ไขข่าว';
    const feedback = status === 'published' ? 'บันทึกและเผยแพร่แล้ว' : 'บันทึกฉบับร่างแล้ว';
    $('#news-save-feedback').textContent = isNewsDirty() ? feedback + ' · ยังมีข้อมูลที่ยังไม่ได้นำมาใช้' : feedback;
    toast(status === 'published' ? 'เผยแพร่ข่าวแล้ว' : 'บันทึกฉบับร่างแล้ว');
  });
  if (!$('.form-error', form).hidden) $('#news-save-feedback').textContent = 'บันทึกไม่สำเร็จ ข้อมูลในฟอร์มยังอยู่';
}

function updateNewsCoverPreview(value = '') {
  const source = safeNewsUrl(value, true);
  const host = $('#news-cover-preview');
  host.replaceChildren();
  if (!source) {
    host.innerHTML = `${icon('image')}<span>ยังไม่มีรูปปก</span><small>อัตราส่วนแนะนำ 16:9</small>`;
    refreshIcons(host);
    return;
  }
  const image = document.createElement('img');
  image.src = source;
  image.alt = 'ตัวอย่างรูปปกข่าว';
  image.addEventListener('error', () => {
    host.innerHTML = `${icon('image-off')}<span>โหลดรูปปกไม่ได้ กรุณาตรวจสอบไฟล์หรือลิงก์</span>`;
    refreshIcons(host);
  }, { once: true });
  host.append(image);
}

async function showNewsPreview() {
  const form = $('#news-form');
  const payload = newsPayload();
  let cover = safeNewsUrl(payload.cover_image_url, true);
  if ($('#news-file').files[0]) cover = await fileToDataUrl($('#news-file').files[0]);
  const target = $('#news-preview-content');
  const source = safeNewsUrl(payload.source_url);
  target.innerHTML = `<article class="news-preview-article"><header><span class="news-preview-category">${escapeHtml(newsTypeLabel(payload.news_type))}</span><h1>${escapeHtml(payload.title || 'ยังไม่มีหัวข้อข่าว')}</h1>${payload.excerpt ? `<p class="news-preview-lead">${escapeHtml(payload.excerpt)}</p>` : ''}<p class="news-field-help">${escapeHtml(workspace.labels[payload.status] || 'ฉบับร่าง')}${payload.published_at ? ' · ' + escapeHtml(formatArticleDate(payload.published_at)) : ' · ยังไม่เผยแพร่'}</p></header>${cover ? `<figure><img src="${escapeAttr(cover)}" alt="รูปปกข่าว"></figure>` : ''}<div class="article-body">${formatArticleBody(payload.body || '')}</div>${source ? `<a class="btn" href="${escapeAttr(source)}" target="_blank" rel="noopener noreferrer">${icon('external-link')}<span>เว็บไซต์ที่เกี่ยวข้อง</span></a>` : ''}</article>`;
  refreshIcons(target);
  newsUi.lastUrl = location.pathname + location.search;
}

function bindNewsEditor() {
  bindNewsManagement();
  bindNewsCompetition();
  $('#news-form').addEventListener('submit', event => {
    event.preventDefault();
    saveNewsEditor(event.submitter?.dataset.newsSave || 'draft');
  });
  $('#news-form').addEventListener('input', () => {
    $('.form-error', $('#news-form')).hidden = true;
    markNewsDirty();
  });
  $('#news-form').addEventListener('change', markNewsDirty);
  $('#news-type-select').addEventListener('change', syncNewsCategory);
  $$('[data-news-back]').forEach(button => button.addEventListener('click', () => showView('news', { history: 'push' })));
  $('#news-preview-button').addEventListener('click', () => showView('news-preview', { history: 'push' }));
  $('#news-preview-back').addEventListener('click', () => showView('news-editor', { history: 'push' }));
  bindNewsDraftReview();
  bindNewsHonor();
  window.addEventListener('beforeunload', event => {
    if (isNewsDirty()) { event.preventDefault(); event.returnValue = ''; }
  });
}

function bindNewsCoverInputs() {
  $('#news-file').addEventListener('change', async event => {
    const file = event.target.files[0];
    const token = ++newsUi.coverToken;
    if (!file) return updateNewsCoverPreview($('#news-cover-url').value);
    if (file.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
      event.target.value = '';
      toast('กรุณาใช้ไฟล์ JPG, PNG, WebP หรือ GIF ขนาดไม่เกิน 5MB', true);
      return;
    }
    const data = await fileToDataUrl(file);
    if (token === newsUi.coverToken) updateNewsCoverPreview(data);
  });
  $('#news-cover-url').addEventListener('input', event => {
    newsUi.coverToken++;
    $('#news-file').value = '';
    updateNewsCoverPreview(event.target.value);
  });
}
