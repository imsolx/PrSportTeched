// News list: reuse the shared table, requests, confirmation and permission-aware server actions.
const newsListState = { type: '', menuId: '', trigger: null };

async function loadNewsAdmin() {
  const state = $('#news-list-state');
  state.hidden = false;
  state.textContent = 'กำลังโหลดข่าว…';
  $('#news-list-retry').hidden = true;
  $('#news-table').hidden = true;
  $('#news-table').setAttribute('aria-busy', 'true');
  try {
    await loadNewsReferences();
    app.news = await request('news.list');
    renderNewsAdminTable();
    state.hidden = true;
    $('#news-table').hidden = false;
  } catch (error) {
    state.textContent = error.message || 'โหลดรายการข่าวไม่สำเร็จ';
    $('#news-list-retry').hidden = false;
    $('#news-table').hidden = true;
  } finally {
    $('#news-table').removeAttribute('aria-busy');
  }
}

function renderNewsAdminTable() {
  const rows = app.news.filter(row => !newsListState.type || row.news_type === newsListState.type);
  renderTable('#news-table', [
    ['title', 'ข่าว', (_, row) => {
      const source = safeNewsUrl(row.cover_image_url, true);
      return `<div class="news-list-story"><div class="news-list-cover">${icon('image')}${source ? `<img src="${escapeAttr(source)}" alt="" loading="lazy">` : ''}</div><div><strong>${Number(row.is_pinned) === 1 ? `<span class="news-pinned" title="ปักหมุด" aria-label="ปักหมุด">${icon('pin')}</span>` : ''}${escapeHtml(row.title)}</strong><p>${escapeHtml(row.excerpt || '')}</p><small>${escapeHtml(row.news_type_label || newsTypeLabel(row.news_type))}</small></div></div>`;
    }, { html: true }],
    ['status', 'สถานะ', value => `<span class="status-badge news-status-${['draft', 'published', 'archived'].includes(value) ? value : 'draft'}">${escapeHtml(workspace.labels[value] || value)}</span>`, { html: true }],
    ['published_at', 'วันที่เผยแพร่', value => value ? formatArticleDate(value) : 'ยังไม่เผยแพร่'],
    ['actions', 'จัดการ', (_, row) => `<div class="news-row-actions"><button class="btn" type="button" data-news-edit="${escapeAttr(row.id)}">${icon('pencil')}<span>แก้ไข</span></button><button class="btn icon-button" type="button" data-news-menu="${escapeAttr(row.id)}" aria-label="จัดการข่าว ${escapeAttr(row.title)}" aria-haspopup="menu">${icon('ellipsis')}</button></div>`],
  ], rows);
  const toolbar = $('#news-table .table-toolbar');
  const search = $('input', toolbar);
  search.placeholder = 'ค้นหาชื่อข่าว…';
  search.setAttribute('aria-label', 'ค้นหาข่าว');
  const types = document.createElement('select');
  types.setAttribute('aria-label', 'กรองประเภทข่าว');
  types.innerHTML = '<option value="">ทุกประเภท</option>' + ['general', 'department', 'hall_of_fame'].map(type => `<option value="${type}">${escapeHtml(newsTypeLabel(type))}</option>`).join('');
  types.value = newsListState.type;
  types.addEventListener('change', () => {
    newsListState.type = types.value;
    const state = workspace.tables.get('#news-table');
    if (state) state.page = 1;
    renderNewsAdminTable();
  });
  toolbar.append(types);
  // Lazy table pagination redraws its markup; image fallback is handled with a capture listener below.
  refreshIcons($('#news-table'));
}

function newsTypeLabel(type) {
  return ({ general: 'ข่าวสารทั่วไป', department: 'เนื้อหาหน่วย / สาขา', hall_of_fame: 'ข่าวเกียรติประวัติ' })[type] || 'ข่าวสารทั่วไป';
}

function bindNewsManagement() {
  $('#news-create-button').addEventListener('click', () => openNewsEditor());
  $('#news-list-retry').addEventListener('click', () => loadNewsAdmin());
  $('#news-table').addEventListener('error', event => {
    if (event.target instanceof HTMLImageElement) event.target.remove();
  }, true);
  $('#news-table').addEventListener('click', event => {
    const edit = event.target.closest('[data-news-edit]');
    const more = event.target.closest('[data-news-menu]');
    const row = app.news.find(item => String(item.id) === (edit?.dataset.newsEdit || more?.dataset.newsMenu));
    if (!row) return;
    if (edit) return openNewsEditor(row);
    if (more) openNewsActionMenu(row, more);
  });
  $('#news-action-menu').addEventListener('click', event => {
    const button = event.target.closest('[data-news-action]');
    const row = app.news.find(item => String(item.id) === newsListState.menuId);
    if (!button || !row) return;
    $('#news-action-menu').hidePopover();
    runTask(button, () => handleNewsListAction(button.dataset.newsAction, row));
  });
  $('#news-action-menu').addEventListener('toggle', event => {
    if (event.newState === 'closed' && $('#news-action-menu').contains(document.activeElement)) newsListState.trigger?.focus();
  });
}

function openNewsActionMenu(row, trigger) {
  const menu = $('#news-action-menu');
  newsListState.menuId = String(row.id);
  newsListState.trigger = trigger;
  $('[data-news-action="publish"]', menu).hidden = row.status === 'published';
  $('[data-news-action="unpublish"]', menu).hidden = row.status !== 'published';
  $('[data-news-action="archive"]', menu).hidden = row.status === 'archived';
  $('[data-news-action="pin"] span', menu).textContent = Number(row.is_pinned) === 1 ? 'เลิกปักหมุด' : 'ปักหมุดข่าว';
  menu.showPopover();
  const box = trigger.getBoundingClientRect();
  menu.style.left = `${Math.max(8, Math.min(box.right - menu.offsetWidth, innerWidth - menu.offsetWidth - 8))}px`;
  menu.style.top = `${Math.max(8, Math.min(box.bottom + 6, innerHeight - menu.offsetHeight - 8))}px`;
  $('button:not([hidden])', menu)?.focus();
}

async function handleNewsListAction(action, row) {
  if (action === 'preview') return openNewsEditor(row, 'news-preview');
  if (action === 'delete') {
    if (!await confirmAction('ลบข่าวนี้?', `“${row.title}” จะถูกลบและเรียกคืนไม่ได้`, 'ลบข่าว', true)) return;
    await request('news.delete', { id: row.id });
  } else if (action === 'publish') {
    if (!await confirmAction('เผยแพร่ข่าว?', `ตรวจทาน “${row.title}” แล้ว ข่าวจะแสดงบนเว็บไซต์ทันที`, 'เผยแพร่')) return;
    await request('news.publish', { id: row.id });
  } else {
    const payload = newsPayloadFromRow(row);
    if (action === 'unpublish') {
      if (!await confirmAction('ยกเลิกเผยแพร่?', 'ข่าวจะกลับเป็นฉบับร่างและไม่แสดงบนเว็บไซต์ ข้อมูลเดิมยังอยู่', 'ยกเลิกเผยแพร่')) return;
      payload.status = 'draft';
      payload.published_at = '';
    } else if (action === 'archive') {
      if (!await confirmAction('เก็บข่าวเข้าคลัง?', 'ข่าวจะไม่แสดงบนเว็บไซต์ และยังเปิดแก้ไขได้จากรายการข่าว', 'เก็บเข้าคลัง')) return;
      payload.status = 'archived';
      payload.published_at = '';
    } else if (action === 'pin') payload.is_pinned = Number(row.is_pinned) !== 1;
    else return;
    await request('news.save', payload);
  }
  await loadNewsAdmin();
  toast('อัปเดตข่าวแล้ว');
}

// Kept for existing callers; publication still uses the original server action.
async function publishNews(id) {
  const row = app.news.find(item => String(item.id) === String(id));
  if (row) await handleNewsListAction('publish', row);
}
