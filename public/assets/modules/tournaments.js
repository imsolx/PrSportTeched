/* Tournament page navigation and presentation. All writes use the existing
 * API actions; readiness, permissions, fixtures, and scoring remain server-owned. */
const tournamentUi = {
  page: 'list', section: 'overview', createStep: 1, allTeams: [],
};
const tournamentSections = {
  overview: { step: 8, title: 'ภาพรวมรายการ', description: 'ตรวจความพร้อมของแต่ละกีฬา แล้วเลือกส่วนที่ต้องการจัดการได้โดยตรง' },
  sports: { step: 2, title: 'กีฬาและกติกา', description: 'เลือกชุดกติกาของแต่ละกีฬาและกำหนดสังกัดเฉพาะรายการที่ต้องใช้' },
  athletes: { step: 5, title: 'นักกีฬา', description: 'จัดการทะเบียนนักกีฬาและนำเข้ารายชื่อจาก CSV' },
  teams: { step: 6, title: 'ทีมและสมาชิก', description: 'จัดการทีมแข่งขันและรายชื่อนักกีฬาในแต่ละทีม' },
  schedule: { step: 9, title: 'ตารางแข่งขัน', description: 'จัดสาย กำหนดวันเวลาและสนาม รวมถึงมอบหมายผู้รับผิดชอบแมตช์' },
  results: { step: 10, title: 'ผลการแข่งขัน', description: 'เลือกแมตช์เพื่อเปิดหน้าบันทึกผล และตรวจตารางคะแนนของรายการนี้' },
};

function bindTournamentWorkspace() {
  readTournamentRoute();
  $('#tournament-back-to-list').addEventListener('click', () => showTournamentList());
  $('#tournament-create-cancel').addEventListener('click', cancelTournamentCreate);
  $('#tournament-edit-button').addEventListener('click', openSelectedTournamentEditor);
  $('#tournament-record-results').addEventListener('click', () => showView('official'));
  $('#workflow-tournament-select').addEventListener('change', event => {
    runTask(event.currentTarget, () => openTournamentDashboard(event.target.value, tournamentUi.section));
  });
  $('#tournament-tools').addEventListener('click', event => {
    const button = event.target.closest('[data-tournament-section]');
    if (button) selectTournamentSection(button.dataset.tournamentSection);
  });
  $('#tournaments-table').addEventListener('click', event => {
    const button = event.target.closest('[data-open-tournament]');
    if (button) runTask(button, () => openTournamentDashboard(button.dataset.openTournament));
  });
  $('#tournament-readiness-bar').addEventListener('click', event => {
    const button = event.target.closest('[data-readiness-section]');
    if (button) selectTournamentSection(button.dataset.readinessSection);
  });
  $('#tournament-create-next').addEventListener('click', () => {
    if (validateTournamentCreateStep(tournamentUi.createStep)) setTournamentCreateStep(tournamentUi.createStep + 1);
  });
  $('#tournament-create-previous').addEventListener('click', () => setTournamentCreateStep(tournamentUi.createStep - 1));
  $('#tournament-form').addEventListener('submit', event => {
    if (tournamentUi.page !== 'create' || tournamentUi.createStep === 3) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (validateTournamentCreateStep(tournamentUi.createStep)) setTournamentCreateStep(tournamentUi.createStep + 1);
  }, true);
  $('#generate-fixtures-button').addEventListener('click', async event => {
    await runTask(event.currentTarget, async () => {
      const result = await request('tournaments.generateFixtures', {
        tournament_id: app.workflow.tournamentId,
        sport_id: $('#fixture-sport-select').value,
        group_count: $('#fixture-group-count').value,
      });
      await loadMatchesAdmin();
      toast(`สร้างคู่แข่งขัน ${result.matches.length} คู่แล้ว${result.bye_team_ids?.length ? ` · ${result.bye_team_ids.length} ทีมผ่านรอบแรกอัตโนมัติ` : ''}`);
    });
    updateFixtureAvailability();
  });
  $('#fixture-sport-select').addEventListener('change', updateFixtureAvailability);
  $('#workflow-edit-sports').addEventListener('click', openSelectedTournamentEditor);
  $('#workflow-department-sport').addEventListener('change', renderWorkflowDepartmentOptions);
  $('#workflow-save-departments').addEventListener('click', async event => {
    await runTask(event.currentTarget, async () => {
      await request('tournaments.departments', {
        tournament_id: app.workflow.tournamentId,
        sport_id: $('#workflow-department-sport').value,
        department_codes: $$('input[name="workflow_department_codes[]"]:checked').map(input => input.value),
      });
      await loadTournamentWorkspace(app.workflow.tournamentId, false);
      toast('บันทึกสาขาที่เข้าร่วมแล้ว');
    });
  });
}

function readTournamentRoute() {
  const params = new URLSearchParams(location.search);
  if (params.get('view') !== 'matches') return;
  tournamentUi.page = params.get('create') === '1' ? 'create' : params.has('tournament') ? 'manage' : 'list';
  tournamentUi.section = Object.hasOwn(tournamentSections, params.get('section')) ? params.get('section') : 'overview';
  if (params.has('tournament')) app.workflow.tournamentId = params.get('tournament');
}

function syncTournamentRoute(historyMode = 'replace') {
  if (app.currentView !== 'matches') return;
  const url = new URL(location.href);
  ['tournament', 'section', 'create'].forEach(key => url.searchParams.delete(key));
  url.searchParams.set('view', 'matches');
  if (tournamentUi.page === 'manage' && app.workflow.tournamentId) {
    url.searchParams.set('tournament', app.workflow.tournamentId);
    url.searchParams.set('section', tournamentUi.section);
  }
  if (tournamentUi.page === 'create') url.searchParams.set('create', '1');
  const next = url.pathname + url.search + url.hash;
  const current = location.pathname + location.search + location.hash;
  if (next === current) return;
  window.history[historyMode === 'push' ? 'pushState' : 'replaceState']({ ...history.state, view: 'matches' }, '', url);
}

function showTournamentList(historyMode = 'push') {
  restoreTournamentEditor();
  tournamentUi.page = 'list';
  renderTournamentPage();
  syncTournamentRoute(historyMode);
  window.scrollTo({ top: 0, behavior: 'instant' });
}

async function openTournamentDashboard(id, section = 'overview') {
  if (!id || !app.tournaments.some(row => String(row.id) === String(id))) return;
  tournamentUi.page = 'manage';
  tournamentUi.section = Object.hasOwn(tournamentSections, section) ? section : 'overview';
  app.workflow.tournamentId = String(id);
  await loadMatchesAdmin();
  await loadTeamsAdmin();
  renderTournamentPage();
  syncTournamentRoute('push');
  window.scrollTo({ top: 0, behavior: 'instant' });
}

function selectTournamentSection(section, scroll = false) {
  if (!Object.hasOwn(tournamentSections, section)) return;
  tournamentUi.page = 'manage';
  tournamentUi.section = section;
  app.workflow.step = tournamentSections[section].step;
  renderTournamentPage();
  syncTournamentRoute('push');
  if (scroll) $('#workflow-step-title').scrollIntoView({ block: 'start' });
}

/* Compatibility adapter for existing athlete links and feature integrations.
 * Numeric steps are no longer rendered as a management wizard. */
function setWorkflowStep(step, scroll = true) {
  const sections = { 2: 'sports', 3: 'sports', 4: 'sports', 5: 'athletes', 6: 'teams', 7: 'teams', 8: 'overview', 9: 'schedule', 10: 'results' };
  if (Number(step) === 1) return showTournamentList();
  if (Number(step) === 11) return showView(activeRole() === 'sport_head' ? 'public-news' : 'news');
  selectTournamentSection(sections[Number(step)] || 'overview', scroll);
}

function renderTournamentPage() {
  const canManage = ['super_admin', 'admin'].includes(activeRole());
  if (tournamentUi.page === 'create' && !canManage) tournamentUi.page = 'list';
  if (tournamentUi.page === 'manage' && !app.workflow.data) tournamentUi.page = 'list';
  $('#tournament-list-page').hidden = tournamentUi.page !== 'list';
  $('#tournament-create-page').hidden = tournamentUi.page !== 'create';
  $('#tournament-dashboard-page').hidden = tournamentUi.page !== 'manage';
  if (tournamentUi.page === 'create') mountTournamentCreateForm();
  if (tournamentUi.page !== 'manage') return;
  const section = tournamentUi.section;
  const details = tournamentSections[section];
  app.workflow.step = details.step;
  $('#tournament-dashboard-page').dataset.section = section;
  $('#workflow-step-title').textContent = details.title;
  $('#workflow-step-description').textContent = details.description;
  $$('[data-tournament-panel]').forEach(panel => {
    panel.hidden = !panel.dataset.tournamentPanel.split(' ').includes(section)
      || (panel.id === 'match-assignment-panel' && !canManage)
      || (panel.id === 'fixture-generator' && !canManage);
  });
  $$('#tournament-tools [data-tournament-section]').forEach(button => {
    const selected = button.dataset.tournamentSection === section;
    button.classList.toggle('active', selected);
    if (selected) button.setAttribute('aria-current', 'page');
    else button.removeAttribute('aria-current');
  });
  $('#tournament-record-results').hidden = section !== 'results' || !['super_admin', 'admin', 'sport_head', 'result_officer'].includes(activeRole());
  $('#tournament-record-results').disabled = !app.matches.length;
  $('#tournament-record-results').title = app.matches.length ? '' : 'สร้างตารางแข่งขันก่อนเปิดหน้าบันทึกผล';
  updateFixtureAvailability();
  refreshIcons($('#view-matches'));
}

function renderTournamentList() {
  const rows = app.tournaments.map(row => ({
    ...row,
    sport_count: row.sport_ids?.length || 0,
    team_count: tournamentUi.allTeams.filter(team => String(team.tournament_id) === String(row.id)).length,
  }));
  renderTable('#tournaments-table', [
    ['name', 'รายการแข่งขัน'],
    ['academic_year', 'ปี / รุ่น', value => value || '—'],
    ['sport_count', 'กีฬา', value => `${value} กีฬา`],
    ['team_count', 'ทีม', value => `${value} ทีม`],
    ['status', 'สถานะการแข่งขัน', value => workspace.labels[value] || value],
    ['actions', 'จัดการ', (_, row) => `<button type="button" class="btn small" data-open-tournament="${escapeAttr(row.id)}">จัดการ</button>` + (['super_admin', 'admin'].includes(activeRole()) ? rowActions([
      ['แก้ไข', () => fillForm('#tournament-form', row)],
      ['ลบ', () => deleteRow('tournaments.delete', row.id, loadMatchesAdmin), 'danger'],
    ]) : '')],
  ], rows);
}

async function loadTournamentWorkspace(tournamentId = '', resetStep = false) {
  const selected = String(tournamentId || app.workflow.tournamentId || app.tournaments[0]?.id || '');
  app.workflow.tournamentId = selected;
  setOptions($('#workflow-tournament-select'), app.tournaments, row => row.id, row => row.name, 'เลือกทัวร์นาเมนต์');
  $('#workflow-tournament-select').value = selected;
  if (!selected) {
    app.workflow.data = null;
    renderTournamentPage();
    return;
  }
  const data = await request('tournaments.workspace', { tournament_id: selected });
  app.workflow.data = data;
  const tournament = data.tournament;
  $('#workflow-tournament-name').textContent = tournament.name;
  $('#workflow-tournament-meta').textContent = `${tournament.starts_at || 'ยังไม่กำหนดวันเริ่ม'} – ${tournament.ends_at || 'ยังไม่กำหนดวันสิ้นสุด'} · ${workspace.labels[tournament.format] || tournament.format}`;
  setOptions($('#fixture-sport-select'), data.sports || [], row => row.id, row => row.name, 'เลือกกีฬา');
  $('#workflow-tournament-status').textContent = workspace.labels[tournament.status] || tournament.status;
  const ready = Boolean(data.steps?.[8]);
  $('#tournament-setup-status').textContent = ready ? 'ข้อมูลพร้อมจัดการแข่งขัน' : 'ยังเตรียมข้อมูลไม่ครบ';
  const resultsVisible = !['draft', 'cancelled'].includes(tournament.status);
  const completed = Number(data.totals.completed_matches || 0);
  $('#tournament-publication-status').textContent = !completed ? 'ยังไม่มีผลการแข่งขัน'
    : resultsVisible ? `แสดงผลบนเว็บไซต์ ${completed} แมตช์` : 'ผลยังไม่แสดงสาธารณะ';
  $('#tournament-summary-line').textContent = `${data.totals.sports} กีฬา · ${data.totals.teams} ทีม · ${data.totals.members} สมาชิก · ${data.totals.matches} แมตช์`;
  const checks = [
    ['sports', Boolean(data.steps[2]), 'เลือกกีฬา'],
    ['sports', Boolean(data.steps[3]), 'ชุดกติกา'],
    ['teams', Boolean(data.steps[6]), 'ทีมแข่งขัน'],
    ['teams', Boolean(data.steps[7]), 'สมาชิกทีม'],
  ];
  $('#tournament-readiness-bar').innerHTML = '<strong>ความพร้อมของข้อมูล</strong>' + checks.map(([section, done, label]) =>
    `<button type="button" class="readiness-link ${done ? 'done' : ''}" data-readiness-section="${section}">${icon(done ? 'circle-check' : 'circle-alert')}${label}: ${done ? 'มีข้อมูลแล้ว' : 'ยังไม่ครบ'}</button>`).join('');
  renderWorkflowSports();
  renderWorkflowDepartmentOptions();
  renderWorkflowReadiness();
  syncStudentScope();
  if (resetStep) tournamentUi.section = 'overview';
  renderTournamentPage();
}

function openSelectedTournamentEditor() {
  const tournament = app.workflow.data?.tournament;
  if (tournament) openEditor('tournament-form', tournament);
}

function updateFixtureAvailability() {
  const sportId = $('#fixture-sport-select').value;
  const tournament = app.workflow.data?.tournament;
  $('#fixture-group-count').closest('label').hidden = !['group', 'group_knockout'].includes(tournament?.format);
  const teams = app.teams.filter(team => String(team.tournament_id) === String(app.workflow.tournamentId)
    && String(team.sport_id) === String(sportId) && !['draft', 'rejected', 'withdrawn'].includes(team.status));
  const existing = app.matches.some(match => String(match.sport_id) === String(sportId));
  const required = ['group', 'group_knockout'].includes(tournament?.format) ? 4 : 2;
  const reason = !sportId ? 'เลือกกีฬาก่อนสร้างสายการแข่งขัน'
    : existing ? 'กีฬานี้มีแมตช์แล้ว จัดการตารางเดิมด้านล่างได้'
      : teams.length < required ? `ต้องมีทีมที่พร้อมจัดสายอย่างน้อย ${required} ทีม (ขณะนี้ ${teams.length} ทีม)` : '';
  $('#generate-fixtures-button').disabled = Boolean(reason);
  $('#fixture-requirements').textContent = reason || 'พร้อมสร้างสายตามรูปแบบการแข่งขันของรายการ';
}

function restoreTournamentEditor() {
  const form = $('#tournament-form');
  const dialog = $('#tournament-form-dialog');
  if (!form || !dialog) return;
  dialog.append(form);
  form.classList.remove('tournament-create-form');
  $$('[data-create-step]', form).forEach(part => part.hidden = false);
  $('[type="submit"]', form).hidden = false;
  $('[type="submit"] span', form).textContent = 'บันทึกรายการ';
  $('.modal-actions > .btn.ghost:not(.create-only)', form).hidden = false;
}

function openTournamentCreate() {
  if (!['super_admin', 'admin'].includes(activeRole())) return;
  restoreTournamentEditor();
  resetEditor($('#tournament-form'));
  $('#tournament-form').elements.status.value = 'draft';
  updateTournamentSportsSummary();
  tournamentUi.page = 'create';
  tournamentUi.createStep = 1;
  renderTournamentPage();
  syncTournamentRoute('push');
  $('#tournament-form [name="name"]').focus();
}

function mountTournamentCreateForm() {
  const form = $('#tournament-form');
  if (!form.classList.contains('tournament-create-form')) {
    resetEditor(form);
    form.elements.status.value = 'draft';
    updateTournamentSportsSummary();
    $('#tournament-create-form-host').append(form);
    form.classList.add('tournament-create-form');
    $('.modal-actions > .btn.ghost:not(.create-only)', form).hidden = true;
    form.dataset.snapshot = JSON.stringify(formData(form));
  }
  setTournamentCreateStep(tournamentUi.createStep);
}

function setTournamentCreateStep(step) {
  tournamentUi.createStep = Math.min(3, Math.max(1, Number(step) || 1));
  const form = $('#tournament-form');
  $$('[data-create-step]', form).forEach(part => part.hidden = Number(part.dataset.createStep) !== tournamentUi.createStep);
  $$('[data-create-indicator]').forEach(item => {
    const current = Number(item.dataset.createIndicator) === tournamentUi.createStep;
    item.classList.toggle('active', current);
    if (current) item.setAttribute('aria-current', 'step');
    else item.removeAttribute('aria-current');
  });
  $('#tournament-create-previous').hidden = tournamentUi.createStep === 1;
  $('#tournament-create-next').hidden = tournamentUi.createStep === 3;
  $('[type="submit"]', form).hidden = tournamentUi.createStep !== 3;
  $('[type="submit"] span', form).textContent = 'บันทึกแบบร่างและเปิดหน้าจัดการ';
  if (tournamentUi.createStep === 3) {
    const sports = $$('input[name="sport_ids[]"]:checked', form).map(input => app.sports.find(row => String(row.id) === input.value)?.name).filter(Boolean);
    $('#tournament-create-review').innerHTML = `<dl><div><dt>ชื่อรายการ</dt><dd>${escapeHtml(form.elements.name.value)}</dd></div><div><dt>กีฬา ${sports.length} รายการ</dt><dd>${escapeHtml(sports.join(' · '))}</dd></div><div><dt>สถานะเมื่อบันทึก</dt><dd>ฉบับร่าง</dd></div></dl>`;
  }
}

function validateTournamentCreateStep(step) {
  const form = $('#tournament-form');
  const error = $('.form-error', form);
  error.hidden = true;
  if (step === 1) {
    for (const field of $$('[data-create-step="1"] input', form)) {
      if (!field.reportValidity()) return false;
    }
    if (form.elements.starts_at.value && form.elements.ends_at.value && form.elements.ends_at.value < form.elements.starts_at.value) {
      error.textContent = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม';
      error.hidden = false;
      return false;
    }
  }
  if (step === 2 && !$$('input[name="sport_ids[]"]:checked', form).length) {
    error.textContent = 'กรุณาเลือกกีฬาอย่างน้อย 1 รายการ';
    error.hidden = false;
    return false;
  }
  return true;
}

async function cancelTournamentCreate() {
  const form = $('#tournament-form');
  if (form.dataset.busy === 'true') return;
  const changed = form.dataset.snapshot !== JSON.stringify(formData(form)) || $('#tournament-logo-file').files.length > 0;
  if (changed && !await confirmAction('ยกเลิกการสร้างรายการ?', 'ข้อมูลที่ยังไม่บันทึกจะถูกยกเลิก', 'ยกเลิกการสร้าง')) return;
  resetEditor(form);
  showTournamentList();
}

async function onTournamentSaved(saved) {
  const created = tournamentUi.page === 'create';
  if (created) {
    restoreTournamentEditor();
    app.workflow.tournamentId = String(saved.id);
    tournamentUi.page = 'manage';
    tournamentUi.section = 'overview';
  }
  await loadPublicHome();
  await loadMatchesAdmin();
  if (created) await loadTeamsAdmin();
  renderTournamentPage();
  syncTournamentRoute(created ? 'push' : 'replace');
}

function renderWorkflowSports() {
  const rows = app.workflow.data?.sports || [];
  renderTable('#workflow-sports-table', [
    ['name', 'กีฬา'],
    ['type', 'ประเภท', value => workspace.labels[value] || value],
    ['rule_name', 'ชุดกติกาที่ใช้', (value, row) => value ? `v${row.rule_version_number} · ${value}` : 'ยังไม่เลือก'],
    ['setting_status', 'สถานะ', value => ({ pending: 'ยังตั้งค่าไม่ครบ', ready: 'พร้อมใช้งาน', locked: 'ล็อกแล้ว' }[value] || value)],
    ['department_count', 'สังกัด'],
    ['team_count', 'ทีม'],
    ['actions', 'จัดการ', (_, row) => ['super_admin', 'admin'].includes(activeRole()) ? rowActions([
      ['เลือกชุดกติกา', () => openTournamentSportSetting(row)],
    ]) : '-'],
  ], rows);
}

function openTournamentSportSetting(sport) {
  const rules = app.sportRules.filter(row => String(row.sport_id) === String(sport.id));
  if (!rules.length) {
    toast(`กีฬา ${sport.name} ยังไม่มีชุดกติกา กรุณาสร้างชุดกติกาในเมนูกีฬาและชุดกติกาก่อน`, true);
    showView('sports');
    const ruleForm = $('#sport-rule-form');
    ruleForm.elements.sport_id.value = sport.id;
    ruleForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return;
  }
  const activeRule = rules.find(row => Number(row.is_active));
  setOptions($('#workflow-rule-version'), rules, row => row.id, row => `v${row.version_number} · ${row.name}${Number(row.is_active) ? ' · ชุดหลัก' : ' · เก็บประวัติ'}`, 'เลือกชุดกติกา');
  openEditor('tournament-sport-setting-form', {
    tournament_id: app.workflow.tournamentId,
    sport_id: sport.id,
    sport_name: sport.name,
    rule_version_id: sport.rule_version_id || activeRule?.id || '',
    status: sport.setting_status || 'pending',
    notes: sport.setting_notes || '',
  });
}

function renderWorkflowDepartmentOptions() {
  const sports = app.workflow.data?.sports || [];
  const select = $('#workflow-department-sport');
  const current = select.value;
  setOptions(select, sports, row => row.id, row => row.name, 'เลือกกีฬา');
  if (sports.some(row => String(row.id) === String(current))) select.value = current;
  const sport = sports.find(row => String(row.id) === String(select.value));
  const selected = new Set((sport?.department_codes || []).map(String));
  $('#workflow-department-options').innerHTML = app.departments.map(row => `
    <label class="department-choice"><input type="checkbox" name="workflow_department_codes[]" value="${escapeAttr(row.code)}" ${selected.has(String(row.code)) ? 'checked' : ''}><span><strong>${escapeHtml(row.code)}</strong>${escapeHtml(row.name)}</span></label>
  `).join('') || emptyState('ยังไม่มีข้อมูลสาขา');
  $('#workflow-department-summary').innerHTML = sports.length ? `
    <h3>สรุปสาขาที่กำหนด</h3>${sports.map(row => `<div><strong>${escapeHtml(row.name)}</strong><span>${Number(row.department_count || 0)} สาขา</span></div>`).join('')}
  ` : '<p class="muted">เลือกกีฬาในทัวร์นาเมนต์ก่อน</p>';
}

function renderWorkflowReadiness() {
  const data = app.workflow.data;
  const target = $('#workflow-readiness');
  if (!data?.sports?.length) {
    target.innerHTML = emptyState('ยังไม่มีกีฬาในทัวร์นาเมนต์');
    return;
  }
  const checks = [
    ['rule_ready', 'กติกา'], ['roster_ready', 'ทีม/สมาชิก'], ['schedule_ready', 'ตารางแข่งขัน'],
  ];
  target.innerHTML = `<div class="readiness-list">${data.sports.map(sport => `
    <article><div><strong>${escapeHtml(sport.name)}</strong><span>${sport.team_count} ทีม · ${sport.member_count} คน · ${sport.match_count} แมตช์</span></div>
    <div class="readiness-checks">${checks.map(([key, label]) => `<span class="${sport[key] ? 'done' : 'pending'}">${icon(sport[key] ? 'circle-check' : 'circle-alert')}${label}</span>`).join('')}</div></article>
  `).join('')}</div>`;
  refreshIcons(target);
}


async function loadMatchesAdmin() {
  await loadAdminRefs();
  const [tournaments, rules, allTeams] = await Promise.all([
    request('tournaments.list'),
    request('sportRules.list', { sport_id: '' }),
    request('teams.list', { tournament_id: '' }),
  ]);
  app.tournaments = tournaments;
  app.sportRules = rules;
  tournamentUi.allTeams = allTeams;
  const validSelection = app.tournaments.some(row => String(row.id) === String(app.workflow.tournamentId));
  if (!validSelection && tournamentUi.page === 'manage') {
    tournamentUi.page = 'list';
    toast('ไม่พบรายการที่เลือก หรือไม่มีสิทธิ์เข้าถึง', true);
  }
  app.workflow.tournamentId = validSelection ? String(app.workflow.tournamentId) : String(app.tournaments[0]?.id || '');
  app.matches = await request('matches.list', { tournament_id: app.workflow.tournamentId });
  await loadOfficialAssignmentsPanel();
  renderTournamentList();
  renderTable('#sport-rules-table', [
    ['sport_name', 'กีฬา'],
    ['version_number', 'เวอร์ชัน'],
    ['name', 'ชื่อกติกา'],
    ['format', 'รูปแบบ', (_, row) => `${row.period_count} ช่วง x ${row.period_minutes} นาที`],
    ['is_active', 'สถานะ', value => Number(value) ? 'ชุดหลัก' : 'เก็บประวัติ'],
    ['usage_count', 'ถูกนำไปใช้', value => `${Number(value || 0)} รายการ`],
    ['actions', 'จัดการ', (_, row) => ['super_admin', 'admin'].includes(activeRole()) ? rowActions([
      ...(!row.is_referenced ? [['แก้ไข', () => fillSportRule(row)]] : []),
      ['สร้างเวอร์ชันใหม่', () => cloneSportRule(row)],
    ]) : '-'],
  ], app.sportRules);
  renderTable('#matches-table', [
    ['match_code', 'รหัส'],
    ['tournament_name', 'รายการ'],
    ['sport_name', 'กีฬา'],
    ['teams', 'คู่แข่ง', (_, row) => `${row.team1_name} พบ ${row.team2_name}`],
    ['scheduled_at', 'เวลา'],
    ['score_label', 'คะแนน'],
    ['status_label', 'สถานะ'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ...(['super_admin', 'admin'].includes(activeRole())
        ? [['แก้ไข', () => fillForm('#match-form', row)]] : []),
      ...(['super_admin', 'admin', 'result_officer', 'sport_head'].includes(activeRole())
        ? [['เปิดหน้าบันทึก', () => openMatchCenter(row.id)]] : []),
      ...(['super_admin', 'admin'].includes(activeRole())
        ? [['ลบ', () => deleteRow('matches.delete', row.id, loadMatchesAdmin), 'danger']] : []),
    ])],
  ], app.matches);
  syncAdminOptions();
  const tournamentId = app.workflow.tournamentId;
  if (tournamentId) {
    $('#standings-tournament-filter').value = String(tournamentId);
    await loadStandings(String(tournamentId));
    await loadTournamentWorkspace(String(tournamentId), false);
  } else {
    await loadTournamentWorkspace('', false);
  }
}

async function loadOfficialAssignmentsPanel() {
  const panel = $('#match-assignment-panel');
  const permitted = ['super_admin', 'admin'].includes(activeRole());
  panel.hidden = !permitted;
  if (!permitted) return;
  app.officialOptions = await request('matches.officialOptions', {}, { silent: true });
  setOptions($('#assignment-match'), app.matches, row => row.id, row => row.display_label, 'เลือกแมตช์');
  setOptions($('#assignment-user'), app.officialOptions, row => row.id,
    row => `${row.name} (${row.role === 'sport_head' ? 'Sport Manager' : 'Match Official'} · ${row.department_code || 'ไม่ระบุสังกัด'})`, 'เลือกผู้รับผิดชอบ');
  await loadMatchAssignments();
}

async function loadMatchAssignments() {
  const matchId = $('#assignment-match').value;
  const match = app.matches.find(row => String(row.id) === String(matchId));
  setOptions($('#assignment-team'), match ? [
    { id: match.team1_id, name: match.team1_name },
    { id: match.team2_id, name: match.team2_name },
  ].filter(row => row.id) : [], row => row.id, row => row.name, 'ทั้งแมตช์ตามขอบเขตสังกัด');
  app.matchAssignments = matchId ? await request('matches.assignments', { match_id: matchId }, { silent: true }) : [];
  renderTable('#match-assignments-table', [
    ['user_id', 'ผู้รับผิดชอบ', (_, row) => app.officialOptions.find(user => String(user.id) === String(row.user_id))?.name || row.user_id],
    ['team_id', 'ทีมที่ดูแล', value => value ? (app.matches.find(item => String(item.id) === String(matchId))?.team1_id == value ? match?.team1_name : match?.team2_name) : 'ทั้งแมตช์'],
    ['actions', 'จัดการ', (_, row) => rowActions([['ถอนมอบหมาย', async () => {
      if (!await confirmAction('ถอนมอบหมายผู้รับผิดชอบ?', 'ผู้ใช้นี้จะบันทึกผลแมตช์นี้ไม่ได้ทันที', 'ถอนมอบหมาย', true)) return;
      const assignments = app.matchAssignments.filter(item => String(item.user_id) !== String(row.user_id))
        .map(item => ({ user_id: item.user_id, team_id: item.team_id || '' }));
      await request('matches.assignOfficials', { match_id: matchId, assignments });
      await loadMatchAssignments();
    }, 'danger']])],
  ], app.matchAssignments);
}
