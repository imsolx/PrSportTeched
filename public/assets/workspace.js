const workspace = {
  forms: {
    'sport-form': ['กีฬา', 'sports-table', 'trophy'],
    'sport-rule-form': ['กติกากีฬา', 'sport-rules-table', 'scroll-text'],
    'organization-form': ['หน่วยงาน', 'organizations-table', 'building-2'],
    'student-form': ['นักกีฬา', 'students-table', 'users'],
    'team-form': ['ทีม', 'teams-table', 'users'],
    'member-form': ['สมาชิกทีม', 'members-table', 'user-plus'],
    'tournament-form': ['รายการแข่งขัน', 'tournaments-table', 'medal'],
    'tournament-sport-setting-form': ['กติกาในทัวร์นาเมนต์', 'workflow-sports-table', 'settings-2'],
    'match-form': ['แมตช์', 'matches-table', 'calendar-days'],
    'user-form': ['สมาชิก', 'users-table', 'shield-check'],
    'invite-form': ['คำเชิญทีมงาน', 'invites-table', 'mail-plus'],
  },
  labels: {
    team: 'ทีม', individual: 'บุคคล', open: 'ทั่วไป', male: 'ชาย', female: 'หญิง', mixed: 'ผสม',
    submitted: 'รออนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ไม่อนุมัติ', locked: 'ล็อกแล้ว',
    draft: 'ฉบับร่าง', data_entry: 'กำลังเตรียมข้อมูล', ready: 'พร้อมแข่งขัน', registration: 'กำลังเตรียมข้อมูล', closed: 'ปิดแก้ไขข้อมูล', in_progress: 'กำลังแข่งขัน',
    completed: 'จบการแข่งขัน', cancelled: 'ยกเลิก', scheduled: 'รอแข่งขัน', live: 'กำลังแข่งขัน',
    postponed: 'เลื่อนการแข่งขัน', published: 'เผยแพร่แล้ว', archived: 'เก็บเข้าคลัง',
    todo: 'ต้องทำ', doing: 'กำลังทำ', blocked: 'ติดปัญหา', done: 'เสร็จแล้ว',
    low: 'ต่ำ', normal: 'ปกติ', high: 'สูง', urgent: 'เร่งด่วน',
    general: 'ทั่วไป', content: 'ข่าวสาร', system: 'ระบบ', competition: 'การแข่งขัน',
    knockout: 'แพ้คัดออก', round_robin: 'พบกันหมด', group: 'แบ่งกลุ่ม', group_knockout: 'แบ่งกลุ่มและแพ้คัดออก',
  },
  tables: new Map(),
  pending: 0,
};

function icon(name) {
  return `<i data-lucide="${name}" aria-hidden="true"></i>`;
}

function refreshIcons(root = document) {
  if (!window.lucide) return;
  root.querySelectorAll('[data-lucide]').forEach(el => {
    const name = el.dataset.lucide.replace(/(^|-)([a-z0-9])/g, (_, dash, letter) => letter.toUpperCase());
    if (lucide.icons[name]) el.replaceWith(lucide.createElement(lucide.icons[name], { width: 18, height: 18, 'aria-hidden': 'true' }));
  });
}

function command(label, glyph, handler, tone = '') {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = `btn ${tone}`;
  button.innerHTML = `${icon(glyph)}<span>${escapeHtml(label)}</span>`;
  button.addEventListener('click', () => runTask(button, handler));
  return button;
}

function bindTaskButton(selector, handler) {
  const button = $(selector);
  button.addEventListener('click', () => runTask(button, handler));
}

function initWorkspace() {
  const navigationIcons = {
    public: 'house', 'public-sports': 'trophy', 'public-athletes': 'users', 'public-schedule': 'calendar-days',
    'public-results': 'medal', 'public-standings': 'list-ordered', 'public-bracket': 'git-merge', 'public-news': 'newspaper', dashboard: 'layout-dashboard', sports: 'trophy',
    students: 'graduation-cap', teams: 'users', matches: 'calendar-days', official: 'monitor-play', news: 'newspaper', users: 'shield-check',
  };
  $$('.tab').forEach(button => {
    button.innerHTML = `${icon(navigationIcons[button.dataset.viewButton])}<span>${button.textContent}</span>`;
  });
  $('#menu-toggle').addEventListener('click', () => setNavigation(!document.body.classList.contains('navigation-open')));
  $('#navigation-backdrop').addEventListener('click', () => setNavigation(false));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setNavigation(false); });

  Object.entries(workspace.forms).forEach(([id, [label, table, glyph]]) => {
    const form = document.getElementById(id);
    const dialog = document.createElement('dialog');
    dialog.id = `${id}-dialog`;
    dialog.className = 'editor-dialog';
    dialog.setAttribute('aria-labelledby', `${id}-title`);
    dialog.innerHTML = `<header class="modal-head"><div class="modal-icon">${icon(glyph)}</div><h2 id="${id}-title">${label}</h2></header>`;
    const close = command('ปิด', 'x', () => closeEditor(dialog), 'icon-button');
    close.setAttribute('aria-label', 'ปิด');
    close.title = 'ปิด';
    $('.modal-head', dialog).append(close);
    $('h2', form).remove();
    form.className = 'editor-form';
    const footer = $('.actions', form) || document.createElement('div');
    if (!footer.parentNode) {
      footer.className = 'actions';
      footer.append($('[type="submit"]', form));
      form.append(footer);
    }
    footer.classList.add('modal-actions');
    const reset = $('.reset-form', form);
    if (reset) reset.remove();
    footer.prepend(command('ยกเลิก', 'x', () => closeEditor(dialog), 'ghost'));
    const submit = $('[type="submit"]', form);
    submit.innerHTML = `${icon('check')}<span>${submit.textContent}</span>`;
    const error = document.createElement('p');
    error.className = 'form-error';
    error.setAttribute('role', 'alert');
    error.hidden = true;
    footer.before(error);
    dialog.append(form);
    document.body.append(dialog);
    bindDialogDismiss(dialog, () => closeEditor(dialog));
    const section = document.getElementById(table).parentElement;
    section.className = 'data-section';
    let head = $('.data-heading', section);
    if (!head) {
      head = document.createElement('div');
      head.className = 'data-heading';
      head.append($('h2', section));
      section.prepend(head);
    }
    if (id !== 'tournament-sport-setting-form') {
      const createLabel = id === 'invite-form' ? 'สร้างคำเชิญ' : `เพิ่ม${label}`;
      const createButton = command(
        createLabel,
        'plus',
        () => id === 'student-form' ? openScopedStudentEditor() : openEditor(id),
        'primary'
      );
      if (id === 'student-form') createButton.dataset.studentScopeAction = 'create';
      if (['tournament-form', 'sport-rule-form'].includes(id)) createButton.classList.add('tournament-only');
      if (['team-form', 'member-form', 'match-form'].includes(id)) createButton.classList.add('schedule-only');
      if (id === 'student-form') createButton.classList.add('athlete-only');
      head.append(createButton);
    }
  });

  ['students', 'members'].forEach(kind => {
    const panel = $(`#${kind}-csv`).parentElement;
    const dialog = document.createElement('dialog');
    dialog.className = 'import-dialog';
    dialog.setAttribute('aria-labelledby', `${kind}-import-title`);
    const heading = $('h2', panel);
    heading.id = `${kind}-import-title`;
    panel.className = 'import-content';
    panel.append(command('ปิด', 'x', () => dialog.close(), 'ghost'));
    dialog.append(panel);
    document.body.append(dialog);
    bindDialogDismiss(dialog, () => dialog.close());
    const importButton = command('นำเข้า CSV', 'upload', () => kind === 'students' ? openStudentImport() : dialog.showModal());
    if (kind === 'students') importButton.dataset.studentScopeAction = 'import';
    $(`#${kind}-table`).closest('.data-section').querySelector('.data-heading').append(importButton);
  });

  const competitionGrid = $('#view-matches .grid');
  const panels = [
    ['#workflow-sports-section', 'sports', '2'],
    ['#workflow-departments-section', 'sports', '4'],
    ['#students-table', 'athletes', '5'],
    ['#teams-table', 'teams', '6'],
    ['#members-table', 'teams', '7'],
    ['#workflow-readiness-section', 'overview', '8'],
    ['#fixture-generator', 'schedule', '9'],
    ['#matches-table', 'schedule results', '9 10'],
    ['#standings-table', 'results', '10'],
    ['#match-assignment-panel', 'schedule', '9'],
  ];
  panels.forEach(([selector, sections, legacySteps]) => {
    const element = $(selector);
    const panel = element?.matches('.table-wrap') ? element.closest('.data-section') : element;
    if (!panel || !competitionGrid) return;
    panel.dataset.tournamentPanel = sections;
    panel.dataset.workflowStep = legacySteps;
    competitionGrid.append(panel);
  });
  const teamTab = $('.tab[data-view-button="teams"]');
  if (teamTab) teamTab.hidden = true;
  const studentTab = $('.tab[data-view-button="students"]');
  if (studentTab) studentTab.hidden = true;

  $$('.app-panel').forEach(panel => {
    if (panel.id === 'view-matches') return;
    if ($(':scope > .section-head', panel)) return;
    const name = panel.id.replace('view-', '');
    const tab = $(`.tab[data-view-button="${name}"]`);
    const title = tab?.textContent || $('h2', panel)?.textContent || 'จัดการข้อมูล';
    const head = document.createElement('div');
    head.className = 'section-head page-heading';
    head.innerHTML = `<div><p class="section-kicker">SPORT TOURNAMENT / จัดการข้อมูล</p><h2>${escapeHtml(title)}</h2></div>`;
    panel.prepend(head);
  });
  $$('select option').forEach(option => {
    if (workspace.labels[option.value] && option.textContent === option.value) option.textContent = workspace.labels[option.value];
  });
  $$('.section-head select').forEach(select => select.setAttribute('aria-label', select.id === 'athlete-sport-filter' ? 'กรองกีฬา' : 'กรองรายการแข่งขัน'));
  $('#athlete-team-filter').setAttribute('aria-label', 'ค้นหาชื่อนักกีฬาหรือทีม');
  $('#login-toggle').prepend(document.createRange().createContextualFragment(icon('log-in')));
  $('#logout-button').prepend(document.createRange().createContextualFragment(icon('log-out')));
  bindDialogDismiss($('#login-dialog'), () => $('#login-dialog').close());
  const loginError = document.createElement('p');
  loginError.className = 'form-error';
  loginError.setAttribute('role', 'alert');
  loginError.hidden = true;
  $('#login-form .actions').before(loginError);
  $('#login-form').addEventListener('reset', () => { loginError.hidden = true; });
  refreshIcons();
}

function setNavigation(open) {
  document.body.classList.toggle('navigation-open', open);
  $('#menu-toggle').setAttribute('aria-expanded', String(open));
  $('#navigation-backdrop').hidden = !open;
}

function updateWorkspaceView(name) {
  document.body.classList.toggle('workspace-view', !name.startsWith('public'));
  setNavigation(false);
  $$('.tab').forEach(tab => {
    if (tab.dataset.viewButton === name) tab.setAttribute('aria-current', 'page');
    else tab.removeAttribute('aria-current');
  });
}

function updateShortcuts() {
  const host = $('#dashboard-shortcuts');
  host.replaceChildren();
  $$('.admin-link:not([hidden])').filter(tab => tab.dataset.viewButton !== 'dashboard').forEach(tab => {
    host.append(command(tab.textContent, { sports: 'trophy', students: 'graduation-cap', teams: 'users', matches: 'calendar-days', official: 'monitor-play', news: 'newspaper', users: 'shield-check' }[tab.dataset.viewButton], () => showView(tab.dataset.viewButton)));
  });
  refreshIcons(host);
}

function bindDialogDismiss(dialog, dismiss) {
  dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(); });
  dialog.addEventListener('click', event => {
    const box = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dismiss();
  });
}

function resetEditor(form) {
  form.reset();
  if (form.elements.id) form.elements.id.value = '';
  const error = $('.form-error', form);
  if (error) error.hidden = true;
}

function openEditor(id, row = null) {
  if (id === 'news-form') return openNewsEditor(row);
  if (id === 'tournament-form' && !row?.id) return openTournamentCreate();
  if (id === 'tournament-form') restoreTournamentEditor();
  const form = document.getElementById(id);
  resetEditor(form);
  if (id === 'match-form') {
    form.elements.tournament_id.value = row?.tournament_id || '';
    syncMatchTeamOptions();
  }
  if (row) Object.entries(row).forEach(([key, value]) => {
    if (id === 'tournament-form' && key === 'sport_ids' && Array.isArray(value)) {
      $$('input[name="sport_ids[]"]', form).forEach(field => { field.checked = value.map(String).includes(field.value); });
      return;
    }
    const field = form.elements.namedItem(key);
    if (!field || field.type === 'file' || field.type === 'password') return;
    if (field.type === 'checkbox') field.checked = Boolean(Number(value));
    else if (field.type === 'datetime-local') field.value = String(value || '').replace(' ', 'T').slice(0, 16);
    else if (id === 'tournament-form' && key === 'status') field.value = ({ registration: 'data_entry', closed: 'ready' }[value] || value || 'draft');
    else field.value = value ?? '';
  });
  if (id === 'sport-form') {
    const mode = form.elements.scoring_type;
    mode.disabled = false;
    document.getElementById('sport-scoring-help').textContent = row?.id
      ? 'เมื่อเปลี่ยนรูปแบบ ระบบจะสร้างชุดกติกาเวอร์ชันใหม่ให้อัตโนมัติ แมตช์เดิมยังใช้กติกาเดิม ส่วนแมตช์ใหม่จะใช้รูปแบบใหม่นี้'
      : 'รูปแบบที่เลือกจะสร้างเป็นกติกาหลัก และแมตช์ใหม่จะใช้แบบบันทึกตามกติกานี้';
  }
  if (id === 'tournament-form' && typeof updateTournamentSportsSummary === 'function') updateTournamentSportsSummary();
  if (id === 'team-form' && typeof syncTeamSportOptions === 'function') {
    const sportId = row?.sport_id || '';
    syncTeamSportOptions();
    form.elements.sport_id.value = sportId;
  }
  if (id === 'organization-form' && typeof syncOrganizationFields === 'function') syncOrganizationFields();
  if (id === 'student-form' && typeof syncStudentPhotoPreview === 'function') syncStudentPhotoPreview(form.elements.photo_url?.value || '');
  if (['user-form', 'invite-form'].includes(id)) prepareAdminUserEditor(id, row);
  const label = workspace.forms[id][0];
  $(`#${id}-title`).textContent = `${row ? 'แก้ไข' : 'เพิ่ม'}${label}`;
  if (id === 'invite-form') $(`#${id}-title`).textContent = 'สร้างคำเชิญทีมงาน';
  form.dataset.snapshot = JSON.stringify(formData(form));
  form.closest('dialog').showModal();
  refreshIcons(form.closest('dialog'));
  const focusField = $('input:not([type="hidden"]), select, textarea', form);
  focusField?.focus();
}

async function closeEditor(dialog) {
  const form = $('form', dialog);
  if (form.dataset.busy === 'true') return;
  const changed = form.dataset.snapshot !== JSON.stringify(formData(form)) || $$('input[type="file"]', form).some(field => field.files.length);
  if (changed && !await confirmAction('ยกเลิกการแก้ไข?', 'ข้อมูลที่ยังไม่บันทึกจะถูกยกเลิก', 'ยกเลิกการแก้ไข')) return;
  dialog.close();
}

function confirmAction(title, message, accept = 'ยืนยัน', danger = false) {
  return new Promise(resolve => {
    const dialog = document.createElement('dialog');
    dialog.className = 'confirm-dialog';
    dialog.setAttribute('aria-labelledby', 'confirmation-title');
    dialog.setAttribute('aria-describedby', 'confirmation-description');
    dialog.innerHTML = `<div class="confirm-symbol ${danger ? 'danger' : ''}">${icon(danger ? 'trash-2' : 'circle-help')}</div><h2 id="confirmation-title">${escapeHtml(title)}</h2><p id="confirmation-description">${escapeHtml(message)}</p><div class="actions"></div>`;
    const finish = value => { restoreToast(dialog); dialog.close(); dialog.remove(); resolve(value); };
    const cancel = command('กลับ', 'arrow-left', () => finish(false));
    $('.actions', dialog).append(cancel, command(accept, danger ? 'trash-2' : 'check', () => finish(true), danger ? 'danger solid' : 'primary'));
    document.body.append(dialog);
    bindDialogDismiss(dialog, () => finish(false));
    refreshIcons(dialog);
    dialog.showModal();
    cancel.focus();
  });
}

async function runTask(control, task) {
  if (control.disabled || control.dataset.busy === 'true') return;
  control.disabled = true;
  control.dataset.busy = 'true';
  try { return await task(); }
  catch (error) { toast(error.message || 'ดำเนินการไม่สำเร็จ กรุณาลองใหม่', true); }
  finally { control.disabled = false; delete control.dataset.busy; }
}

async function runForm(form, task) {
  if (form.dataset.busy === 'true') return;
  form.dataset.busy = 'true';
  form.setAttribute('aria-busy', 'true');
  const controls = $$('button', form);
  const states = controls.map(button => button.disabled);
  controls.forEach(button => button.disabled = true);
  const errorOutput = $('.form-error', form);
  if (errorOutput) errorOutput.hidden = true;
  try { await task(); }
  catch (error) {
    if (errorOutput) {
      errorOutput.textContent = error.message || 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
      errorOutput.hidden = false;
      errorOutput.scrollIntoView({ block: 'nearest' });
    } else toast(error.message || 'ดำเนินการไม่สำเร็จ', true);
  } finally {
    delete form.dataset.busy;
    form.removeAttribute('aria-busy');
    controls.forEach((button, index) => button.disabled = states[index]);
  }
}

function finishEditor(form) {
  form.closest('dialog')?.close();
  resetEditor(form);
}

function restoreToast(dialog) {
  const notice = dialog.querySelector('#toast');
  if (notice) document.body.append(notice);
}

function renderDataTable(selector, columns, rows) {
  const host = $(selector);
  let state = workspace.tables.get(selector);
  if (!state) {
    state = { query: '', status: '', page: 1, size: 10 };
    workspace.tables.set(selector, state);
  }
  Object.assign(state, { rows: rows || [], columns });
  host.replaceChildren();
  host.classList.add('data-table');
  const toolbar = document.createElement('div');
  toolbar.className = 'table-toolbar';
  toolbar.innerHTML = `<label class="table-search">${icon('search')}<input type="search" placeholder="ค้นหาในรายการ..." aria-label="ค้นหา${escapeAttr(host.parentElement.querySelector('h2,h3')?.textContent || 'รายการ')}" value="${escapeAttr(state.query)}"></label>`;
  const search = $('input', toolbar);
  search.addEventListener('input', () => { state.query = search.value; state.page = 1; draw(); });
  const statusColumn = columns.find(([key]) => key === 'status' || key === 'status_label');
  if (statusColumn) {
    const select = document.createElement('select');
    select.setAttribute('aria-label', 'กรองสถานะ');
    const statuses = [...new Set(state.rows.map(row => row[statusColumn[0]]).filter(Boolean))];
    if (!statuses.includes(state.status)) state.status = '';
    select.innerHTML = '<option value="">ทุกสถานะ</option>' + statuses.map(status => `<option value="${escapeAttr(status)}">${escapeHtml(workspace.labels[status] || status)}</option>`).join('');
    select.value = state.status;
    select.addEventListener('change', () => { state.status = select.value; state.page = 1; draw(); });
    toolbar.append(select);
  }
  const viewport = document.createElement('div');
  viewport.className = 'table-scroll';
  viewport.tabIndex = 0;
  viewport.setAttribute('role', 'region');
  viewport.setAttribute('aria-label', 'รายการข้อมูล');
  const footer = document.createElement('div');
  footer.className = 'table-footer';
  host.append(toolbar, viewport, footer);

  function draw() {
    const query = state.query.trim().toLocaleLowerCase('th');
    const filtered = state.rows.filter(row => (!state.status || row[statusColumn[0]] === state.status) && (!query || columns.filter(([key]) => key !== 'actions').some(([key, , formatter]) => {
      const value = formatter ? formatter(row[key], row) : row[key];
      return `${value ?? ''} ${workspace.labels[value] || ''}`.toLocaleLowerCase('th').includes(query);
    })));
    const pages = Math.max(1, Math.ceil(filtered.length / state.size));
    state.page = Math.min(state.page, pages);
    const start = (state.page - 1) * state.size;
    const visible = filtered.slice(start, start + state.size);
    viewport.innerHTML = visible.length ? `<table><thead><tr>${columns.map(([, label]) => `<th scope="col">${escapeHtml(label)}</th>`).join('')}</tr></thead><tbody>${visible.map(row => {
      const rank = Number.parseInt(row.rank_number, 10);
      const rowClass = Number.isFinite(rank) && rank > 0 ? ` class="data-rank-${rank}"` : '';
      return `<tr${rowClass}>${columns.map(([key, , formatter, options]) => {
      let value = formatter ? formatter(row[key], row) : row[key];
      if (key === 'actions') return `<td class="action-cell" data-column="actions">${value}</td>`;
      if (options?.html) return `<td data-column="${escapeAttr(key)}">${value}</td>`;
      value = workspace.labels[value] || value;
      const status = ['status', 'status_label', 'active_label'].includes(key);
      const statusTone = ['live', 'completed', 'scheduled', 'cancelled', 'postponed'].includes(row.status) ? row.status : '';
      const statusClass = statusTone || (['approved', 'published'].includes(row.status) ? 'success' : ['rejected'].includes(row.status) ? 'danger' : '');
      return `<td data-column="${escapeAttr(key)}">${status ? `<span class="status-badge status-${escapeAttr(statusClass)} ${statusClass === 'success' || statusClass === 'danger' ? statusClass : ''}">${escapeHtml(value ?? '-')}</span>` : escapeHtml(value ?? '-')}</td>`;
    }).join('')}</tr>`;
    }).join('')}</tbody></table>` : emptyState(query || state.status ? 'ไม่พบรายการที่ตรงกับการค้นหา' : 'ยังไม่มีข้อมูล');
    footer.replaceChildren();
    const count = document.createElement('span');
    count.setAttribute('aria-live', 'polite');
    count.textContent = `${filtered.length ? start + 1 : 0}-${Math.min(start + state.size, filtered.length)} จาก ${filtered.length} รายการ`;
    const paging = document.createElement('div');
    paging.className = 'pagination';
    const prev = command('หน้าก่อน', 'chevron-left', () => { state.page--; draw(); }, 'icon-button');
    const next = command('หน้าถัดไป', 'chevron-right', () => { state.page++; draw(); }, 'icon-button');
    prev.setAttribute('aria-label', 'หน้าก่อน'); prev.title = 'หน้าก่อน'; prev.disabled = state.page === 1;
    next.setAttribute('aria-label', 'หน้าถัดไป'); next.title = 'หน้าถัดไป'; next.disabled = state.page === pages;
    const position = document.createElement('span');
    position.textContent = `${state.page} / ${pages}`;
    paging.append(prev, position, next);
    footer.append(count, paging);
    refreshIcons(host);
  }
  draw();
}
