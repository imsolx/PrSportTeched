const app = {
  apiUrl: window.PRSPORT.apiUrl,
  csrf: window.PRSPORT.csrf,
  user: window.PRSPORT.user,
  publicData: null,
  sports: [],
  tournaments: [],
  departments: [],
  students: [],
  teams: [],
  members: [],
  matches: [],
  newsMatches: [],
  news: [],
  users: [],
  invites: [],
  tasks: [],
  taskAssignees: [],
  reports: [],
  sportRules: [],
  organizations: null,
  matchCenter: null,
  clockTimer: null,
  reportOptions: null,
  reportStep: 1,
  activeReport: null,
  currentView: 'public',
  allowedViews: [],
  workflow: { tournamentId: '', step: 1, data: null },
};

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

document.addEventListener('DOMContentLoaded', () => {
  initWorkspace();
  initBannerStudio();
  bindNavigation();
  bindAuth();
  bindForms();
  bindTournamentWorkspace();
  updateSessionUi();
  const inviteToken = new URLSearchParams(window.location.search).get('invite');
  if (inviteToken) {
    openInviteAccept(inviteToken);
  } else {
    const requestedView = new URLSearchParams(window.location.search).get('view');
    showView(requestedView && $(`#view-${requestedView}`) ? requestedView : 'public');
  }
});

function bindNavigation() {
  $$('[data-view-button]').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      showView(button.dataset.viewButton);
    });
  });
}

function bindTournamentWorkspace() {
  $$('[data-workflow-step-button]').forEach(button => {
    button.addEventListener('click', () => setWorkflowStep(Number(button.dataset.workflowStepButton)));
  });
  $('#workflow-tournament-select').addEventListener('change', event => loadTournamentWorkspace(event.target.value, false));
  $('#workflow-settings-button').addEventListener('click', openWorkflowSettings);
  $('#workflow-previous-button').addEventListener('click', () => setWorkflowStep(app.workflow.step - 1));
  $('#workflow-next-button').addEventListener('click', () => setWorkflowStep(app.workflow.step + 1));
  $('#workflow-edit-sports').addEventListener('click', () => openSelectedTournamentEditor());
  $('#workflow-department-sport').addEventListener('change', renderWorkflowDepartmentOptions);
  $('#workflow-save-departments').addEventListener('click', async event => {
    await runTask(event.currentTarget, async () => {
      const sportId = $('#workflow-department-sport').value;
      const departmentCodes = $$('input[name="workflow_department_codes[]"]:checked').map(input => input.value);
      await request('tournaments.departments', {
        tournament_id: app.workflow.tournamentId,
        sport_id: sportId,
        department_codes: departmentCodes,
      });
      await loadTournamentWorkspace(app.workflow.tournamentId, false);
      toast('บันทึกสาขาที่เข้าร่วมแล้ว');
    });
  });
  $$('[data-workflow-view]').forEach(button => {
    button.addEventListener('click', () => showView(button.dataset.workflowView));
  });
}

function setWorkflowStep(step, scroll = true) {
  app.workflow.step = Math.min(11, Math.max(1, Number(step) || 1));
  $$('[data-workflow-step]').forEach(section => {
    const steps = String(section.dataset.workflowStep).split(/\s+/).map(Number);
    section.hidden = !steps.includes(app.workflow.step);
  });
  $$('[data-workflow-step-button]').forEach(button => {
    const number = Number(button.dataset.workflowStepButton);
    button.classList.toggle('active', number === app.workflow.step);
    button.setAttribute('aria-current', number === app.workflow.step ? 'step' : 'false');
  });
  const details = workflowStepDetails(app.workflow.step);
  const complete = Boolean(app.workflow.data?.steps?.[app.workflow.step]);
  $('#workflow-step-count').textContent = `ขั้นที่ ${app.workflow.step} จาก 11`;
  $('#workflow-step-title').textContent = details.title;
  $('#workflow-step-description').textContent = details.description;
  $('#workflow-settings-button span').textContent = details.action;
  $('#workflow-step-state').textContent = complete ? 'เสร็จแล้ว' : 'กำลังทำ';
  $('#workflow-step-state').classList.toggle('complete', complete);
  $('#workflow-previous-button').disabled = app.workflow.step === 1;
  $('#workflow-next-button').disabled = app.workflow.step === 11;
  $('#workflow-next-button span').textContent = app.workflow.step === 10 ? 'ไปข่าวและรายงาน' : 'ขั้นถัดไป';
  $('#workflow-navigation-hint').textContent = complete
    ? 'ขั้นนี้เรียบร้อยแล้ว สามารถไปขั้นถัดไปได้'
    : details.hint;
  if (scroll) {
    const activeButton = $(`[data-workflow-step-button="${app.workflow.step}"]`);
    activeButton?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    $('#workflow-step-title').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function workflowStepDetails(step) {
  return ({
    1: { title: 'ข้อมูลทัวร์นาเมนต์', action: app.workflow.tournamentId ? 'แก้ไขข้อมูลทัวร์นาเมนต์' : 'สร้างทัวร์นาเมนต์', description: 'กำหนดชื่อ ปีการศึกษา รูปแบบการแข่งขัน และสถานะของรายการ', hint: 'เริ่มจากสร้างทัวร์นาเมนต์และบันทึกข้อมูลหลัก' },
    2: { title: 'เลือกกีฬาที่จัด', action: 'เลือกและแก้ไขกีฬา', description: 'เลือกกีฬาทั้งหมดที่จะจัดในทัวร์นาเมนต์นี้', hint: 'ต้องเลือกกีฬาอย่างน้อย 1 รายการก่อนตั้งค่ากติกา' },
    3: { title: 'ตั้งกติกาแต่ละกีฬา', action: 'ตั้งค่ากติกา', description: 'กำหนดชุดกติกาและยืนยันความพร้อมแยกเป็นรายกีฬา', hint: 'ตั้งค่ากติกาของกีฬาแต่ละรายการให้เป็นพร้อมใช้งาน' },
    4: { title: 'กำหนดสาขาที่เข้าร่วม', action: 'เลือกสาขา', description: 'ระบุสาขาที่ส่งทีมเข้าร่วม โดยเลือกแยกตามชนิดกีฬา', hint: 'เลือกกีฬา แล้วบันทึกสาขาที่เข้าร่วมให้ครบ' },
    5: { title: 'นำเข้ารายชื่อนักศึกษา', action: 'เริ่มนำเข้ารายชื่อ', description: 'เตรียมข้อมูลนักศึกษาที่จะนำไปจัดเป็นสมาชิกของแต่ละทีม', hint: 'เพิ่มรายชื่อนักศึกษาหรือนำเข้าจากไฟล์ CSV' },
    6: { title: 'สร้างทีมแข่งขัน', action: 'สร้างทีมใหม่', description: 'สร้างทีมให้ตรงกับกีฬาและสาขาที่กำหนดไว้', hint: 'เพิ่มทีมอย่างน้อย 1 ทีมในทัวร์นาเมนต์นี้' },
    7: { title: 'จัดสมาชิกเข้าทีม', action: 'เพิ่มสมาชิกใหม่', description: 'เลือกนักศึกษา บทบาท และหมายเลขเสื้อให้สมาชิกแต่ละทีม', hint: 'เพิ่มสมาชิกให้ทีมที่สร้างไว้ก่อนตรวจความพร้อม' },
    8: { title: 'ตรวจสอบความพร้อม', action: 'ตรวจรายการที่ยังขาด', description: 'ตรวจว่ากติกา สาขา ทีม และสมาชิกพร้อมสำหรับทุกกีฬา', hint: 'แก้รายการสีส้มให้ครบก่อนสร้างตารางแข่งขัน' },
    9: { title: 'สร้างตารางแข่งขัน', action: 'สร้างแมตช์ใหม่', description: 'กำหนดคู่แข่งขัน รอบ วันเวลา สนาม และผู้ตัดสิน', hint: 'สร้างแมตช์ตามรูปแบบการแข่งขันที่กำหนด' },
    10: { title: 'บันทึกผลการแข่งขัน', action: 'เปิดฟอร์มบันทึกผล', description: 'บันทึกคะแนน ผู้ชนะ เวลา และเหตุการณ์ของแต่ละแมตช์', hint: 'เลือกแมตช์ที่แข่งขันแล้วเพื่อบันทึกผล' },
    11: { title: 'จัดทำข่าวและรายงาน', action: 'จัดทำข่าว', description: 'นำผลการแข่งขันที่ยืนยันแล้วไปจัดทำข่าวและรายงานสรุป', hint: 'เลือกจัดทำข่าวประชาสัมพันธ์หรือรายงานผล' },
  })[step];
}

function openSelectedTournamentEditor() {
  const tournament = app.workflow.data?.tournament;
  openEditor('tournament-form', tournament || null);
}

function openWorkflowSettings() {
  const step = app.workflow.step;
  if (step <= 2) return openSelectedTournamentEditor();
  if (step === 3) {
    const sport = app.workflow.data?.sports?.[0];
    if (sport) openTournamentSportSetting(sport);
    else toast('กรุณาเลือกกีฬาในทัวร์นาเมนต์ก่อน', true);
    return;
  }
  if (step === 4) {
    $('#workflow-department-sport').focus();
    return;
  }
  if (step === 5) {
    openStudentImport();
    return;
  }
  if (step === 6) return openEditor('team-form', { tournament_id: app.workflow.tournamentId });
  if (step === 7) return openEditor('member-form');
  if (step === 9) return openEditor('match-form', { tournament_id: app.workflow.tournamentId });
  if (step === 10) return openEditor('result-form');
  if (step === 11) return showView('news');
  $('#workflow-readiness').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function loadTournamentWorkspace(tournamentId = '', resetStep = false) {
  const selected = String(tournamentId || app.workflow.tournamentId || app.tournaments[0]?.id || '');
  app.workflow.tournamentId = selected;
  setOptions($('#workflow-tournament-select'), app.tournaments, row => row.id, row => row.name, 'เลือกทัวร์นาเมนต์');
  $('#workflow-tournament-select').value = selected;
  if (!selected) {
    app.workflow.data = null;
    $('#workflow-tournament-name').textContent = 'ยังไม่มีทัวร์นาเมนต์';
    $('#workflow-tournament-meta').textContent = 'สร้างทัวร์นาเมนต์เพื่อเริ่มจัดการข้อมูล';
    $('#workflow-notice span').textContent = 'เริ่มจากสร้างทัวร์นาเมนต์ แล้วเลือกกีฬาที่จัด';
    updateWorkflowProgress();
    setWorkflowStep(1, false);
    return;
  }
  const data = await request('tournaments.workspace', { tournament_id: selected });
  app.workflow.data = data;
  const tournament = data.tournament;
  $('#workflow-tournament-name').textContent = tournament.name;
  $('#workflow-tournament-meta').textContent = `ปีการศึกษา ${tournament.academic_year || '-'} · ${tournament.sport_name || 'ยังไม่เลือกกีฬา'}`;
  $('#workflow-tournament-status').textContent = workspace.labels[tournament.status] || tournament.status;
  $('#workflow-notice span').textContent = `ขั้นที่แนะนำถัดไป: ${data.suggested_step} · ${$$('[data-workflow-step-button]')[data.suggested_step - 1]?.textContent.trim() || ''}`;
  $$('[data-workflow-step-button]').forEach(button => {
    const number = Number(button.dataset.workflowStepButton);
    button.classList.toggle('complete', Boolean(data.steps[number]));
    button.classList.toggle('attention', number === Number(data.suggested_step));
  });
  updateWorkflowProgress();
  renderWorkflowSports();
  renderWorkflowDepartmentOptions();
  renderWorkflowReadiness();
  syncStudentScope();
  if (resetStep) app.workflow.step = 1;
  setWorkflowStep(app.workflow.step, false);
  refreshIcons($('#view-matches'));
}

function updateWorkflowProgress() {
  const steps = app.workflow.data?.steps || {};
  const complete = Array.from({ length: 11 }, (_, index) => Boolean(steps[index + 1])).filter(Boolean).length;
  $('#workflow-progress-label').textContent = `ความคืบหน้า ${complete} จาก 11 ขั้น`;
  $('#workflow-progress-bar').style.width = `${Math.round((complete / 11) * 100)}%`;
}

function renderWorkflowSports() {
  const rows = app.workflow.data?.sports || [];
  renderTable('#workflow-sports-table', [
    ['name', 'กีฬา'],
    ['type', 'ประเภท', value => workspace.labels[value] || value],
    ['rule_name', 'กติกาที่ใช้', value => value || 'ยังไม่เลือก'],
    ['setting_status', 'สถานะ', value => ({ pending: 'ยังตั้งค่าไม่ครบ', ready: 'พร้อมใช้งาน', locked: 'ล็อกแล้ว' }[value] || value)],
    ['department_count', 'สาขา'],
    ['team_count', 'ทีม'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['ตั้งค่ากติกา', () => openTournamentSportSetting(row)],
      ['สร้างชุดกติกา', () => openNewTournamentRule(row)],
    ])],
  ], rows);
}

function openTournamentSportSetting(sport) {
  const rules = app.sportRules.filter(row => String(row.sport_id) === String(sport.id));
  setOptions($('#workflow-rule-version'), rules, row => row.id, row => `v${row.version_number} · ${row.name}`, 'เลือกชุดกติกา');
  openEditor('tournament-sport-setting-form', {
    tournament_id: app.workflow.tournamentId,
    sport_id: sport.id,
    sport_name: sport.name,
    rule_version_id: sport.rule_version_id || '',
    status: sport.setting_status || 'pending',
    notes: sport.setting_notes || '',
  });
}

function openNewTournamentRule(sport) {
  openEditor('sport-rule-form', {
    sport_id: sport.id,
    name: `${app.workflow.data?.tournament?.name || 'ทัวร์นาเมนต์'} · ${sport.name}`,
    category: sport.type || '',
    player_type: sport.type || 'team',
    team_size: sport.max_players || 1,
    is_active: 1,
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
    ['rule_ready', 'กติกา'], ['departments_ready', 'สาขา'], ['roster_ready', 'ทีม/สมาชิก'], ['schedule_ready', 'ตารางแข่งขัน'],
  ];
  target.innerHTML = `<div class="readiness-list">${data.sports.map(sport => `
    <article><div><strong>${escapeHtml(sport.name)}</strong><span>${sport.team_count} ทีม · ${sport.member_count} คน · ${sport.match_count} แมตช์</span></div>
    <div class="readiness-checks">${checks.map(([key, label]) => `<span class="${sport[key] ? 'done' : 'pending'}">${icon(sport[key] ? 'circle-check' : 'circle-alert')}${label}</span>`).join('')}</div></article>
  `).join('')}</div>`;
  refreshIcons(target);
}

function bindAuth() {
  $('#login-toggle').addEventListener('click', () => $('#login-dialog').showModal());
  $('#login-cancel').addEventListener('click', () => $('#login-dialog').close());
  $('#profile-toggle').addEventListener('click', openProfileDialog);
  $('#profile-cancel').addEventListener('click', () => {
    $('#profile-form').reset();
    $('#profile-dialog').close();
  });
  $('#logout-button').addEventListener('click', async () => {
    await runTask($('#logout-button'), async () => {
    const result = await request('auth.logout');
    app.user = result.user;
    app.csrf = result.csrf;
    updateSessionUi();
    showView('public');
    toast('ออกจากระบบแล้ว');
    });
  });
  $('#login-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
    const result = await request('auth.login', formData(form));
    app.user = result.user;
    app.csrf = result.csrf;
    $('#login-dialog').close();
    form.reset();
    updateSessionUi();
    await loadAdminRefs();
    showView('dashboard');
    toast('เข้าสู่ระบบแล้ว');
    });
  });
  $('#profile-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
      const result = await request('auth.profile', formData(form));
      app.user = result.user;
      app.csrf = result.csrf;
      form.reset();
      $('#profile-dialog').close();
      updateSessionUi();
      toast('บันทึกบัญชีแล้ว');
    });
  });
}

function bindForms() {
  bindSaveForm('#sport-form', 'sports.save', async () => {
    await loadSportsAdmin();
    await loadPublicHome();
  });
  bindSaveForm('#sport-rule-form', 'sportRules.save', async () => {
    await loadSportsAdmin();
    if (app.workflow.tournamentId) await loadTournamentWorkspace(app.workflow.tournamentId, false);
  });
  bindSaveForm('#organization-form', 'organizations.save', async () => {
    await loadSportsAdmin();
    await loadAdminRefs();
  });
  bindSaveForm('#student-form', 'students.save', loadStudentsAdmin);
  $('#student-scope-faculty').addEventListener('change', () => {
    $('#student-scope-department').value = '';
    $('#student-scope-sport').value = '';
    syncStudentScope();
  });
  $('#student-scope-department').addEventListener('change', () => {
    $('#student-scope-sport').value = '';
    syncStudentScope();
  });
  $('#student-scope-sport').addEventListener('change', renderScopedStudents);
  $('#student-photo-file').addEventListener('change', async event => {
    const file = event.target.files?.[0];
    syncStudentPhotoPreview(file ? await fileToDataUrl(file) : $('#student-photo-url').value);
  });
  $('#student-photo-url').addEventListener('input', event => syncStudentPhotoPreview(event.target.value));
  bindSaveForm('#tournament-form', 'tournaments.save', async () => {
    await loadMatchesAdmin();
    await loadPublicHome();
  });
  bindSaveForm('#tournament-sport-setting-form', 'tournaments.sportSetting', async () => {
    await loadTournamentWorkspace(app.workflow.tournamentId, false);
  });
  bindSaveForm('#team-form', 'teams.save', async () => {
    await loadTeamsAdmin();
    await loadMatchesAdmin();
    await loadPublicAthletes();
  });
  bindSaveForm('#member-form', 'teamMembers.save', async () => {
    await loadTeamsAdmin();
    if (app.workflow.tournamentId) await loadTournamentWorkspace(app.workflow.tournamentId, false);
    await loadPublicAthletes();
  });
  bindSaveForm('#match-form', 'matches.save', async () => {
    await loadMatchesAdmin();
    await loadPublicHome();
  });
  bindSaveForm('#task-form', 'tasks.save', async () => {
    await loadTasksAdmin();
    await loadDashboardCountsOnly();
  });
  bindSaveForm('#user-form', 'users.save', loadUsersAdmin);
  $('#invite-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
      const invite = await request('invites.create', formData(form));
      renderInviteLink(invite.invite_url);
      form.reset();
      await loadUsersAdmin();
      toast('สร้างลิงก์คำเชิญแล้ว');
    });
  });
  $('#accept-invite-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
      const result = await request('invites.accept', formData(form));
      app.user = result.user;
      app.csrf = result.csrf;
      updateSessionUi();
      window.history.replaceState({}, document.title, window.location.pathname);
      await loadAdminRefs();
      showView('dashboard');
      toast('ตั้งค่าบัญชีและเข้าสู่ระบบแล้ว');
    });
  });

  $('#result-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
    const data = formData(form);
    await request('matches.result', data);
    finishEditor(form);
    await loadMatchesAdmin();
    await loadPublicHome();
    toast('บันทึกผลการแข่งขันแล้ว');
    });
  });

  $('#news-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
    const file = $('#news-file').files[0];
    if (file) {
      const dataUrl = await fileToDataUrl(file);
      const upload = await request('news.upload', { data_url: dataUrl, file_name: file.name });
      $('#news-cover-url').value = upload.url;
      updateNewsCoverPreview(upload.url);
    }
    await request('news.save', formData(form));
    finishEditor(form);
    await loadNewsAdmin();
    await loadPublicHome();
    toast('บันทึกข่าวแล้ว');
    });
  });

  bindTaskButton('#students-csv-check', async () => {
    const rows = scopedStudentImportRows();
    const result = await request('ai.csv', { kind: 'students', rows });
    $('#students-import-result').textContent = summarizeObject(result);
  });
  bindTaskButton('#students-csv-import', async () => {
    const rows = scopedStudentImportRows();
    const result = await request('students.import', { rows });
    $('#students-import-result').textContent = importSummary(result);
    await loadStudentsAdmin();
  });
  bindTaskButton('#members-csv-check', async () => {
    const rows = parseCsv($('#members-csv').value);
    const result = await request('ai.csv', { kind: 'team_members', rows });
    $('#members-import-result').textContent = summarizeObject(result);
  });
  bindTaskButton('#members-csv-import', async () => {
    const rows = parseCsv($('#members-csv').value);
    const result = await request('teamMembers.import', { rows });
    $('#members-import-result').textContent = importSummary(result);
    await loadTeamsAdmin();
    await loadPublicAthletes();
  });

  bindTaskButton('#dashboard-ai-button', async () => {
    const result = await request('ai.dashboard');
    renderAiOutput('#dashboard-ai-output', result);
  });
  bindTaskButton('#news-ai-button', async () => {
    const result = await request('ai.news', {
      sport_id: $('#news-ai-sport').value,
      tournament_id: $('#news-ai-tournament').value,
      match_id: $('#news-ai-match').value,
      story_type: $('#news-ai-story-type').value,
      tone: $('#news-ai-tone').value,
      instruction: $('#news-ai-instruction').value,
    });
    const form = $('#news-form');
    form.elements.title.value = result.title || '';
    form.elements.excerpt.value = result.excerpt || '';
    form.elements.body.value = result.body || '';
    form.elements.status.value = 'draft';
    $('#news-ai-status').textContent = result.provider === 'google-gemini' ? `Gemini: ${result.model}` : 'ร่างอัตโนมัติในระบบ';
    syncBannerFromNews({
      sport: result.sport_name,
      headline: result.banner_title || result.title,
      subtitle: result.banner_subtitle || result.tournament_name || result.excerpt,
    });
    toast(`ร่างข่าวใส่ฟอร์มแล้ว (${result.model || 'local-summary'})`);
  });
  $('#news-ai-sport').addEventListener('change', () => syncNewsAiOptions('sport'));
  $('#news-ai-tournament').addEventListener('change', () => syncNewsAiOptions('tournament'));
  $('#news-file').addEventListener('change', async event => {
    const file = event.target.files[0];
    if (file) updateNewsCoverPreview(await fileToDataUrl(file));
  });
  $('#news-cover-url').addEventListener('input', event => updateNewsCoverPreview(event.target.value));
  $('#report-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
    syncReportAiFields();
    const payload = collectReportPayload();
    payload.instruction = form.elements.instruction.value || '';
    const result = await request('reports.aiDraft', payload);
    applyReportAiDraft(result);
    });
  });
  $('#report-create-button').addEventListener('click', () => openReportBuilder());
  $('#report-back-button').addEventListener('click', closeReportBuilder);
  $('#report-next-step').addEventListener('click', nextReportStep);
  $('#report-prev-step').addEventListener('click', () => setReportStep(Math.max(1, app.reportStep - 1)));
  $('#report-save-draft').addEventListener('click', () => saveReportEditor('draft', false));
  $('#report-add-kpi').addEventListener('click', () => addKpiRow());
  $$('.budget-fields input[type="number"]').forEach(input => input.addEventListener('input', updateBudgetCalculation));
  $('#report-tournament-select').addEventListener('change', syncReportSourceLabel);
  ['report-search', 'report-year-filter', 'report-category-filter', 'report-template-filter', 'report-status-filter'].forEach(id => {
    document.getElementById(id).addEventListener(id === 'report-search' ? 'input' : 'change', debounce(loadReportsAdmin, 180));
  });

  $('#home-tournament-filter').addEventListener('change', event => filterPublicHome(event.target.value));
  $('#schedule-tournament-filter').addEventListener('change', event => loadPublicSchedule(event.target.value));
  $('#results-tournament-filter').addEventListener('change', event => loadPublicResults(event.target.value));
  $('#athlete-faculty-filter').addEventListener('change', () => {
    $('#athlete-department-filter').value = '';
    $('#athlete-sport-filter').value = '';
    syncAthleteDepartmentOptions();
    syncAthleteSportOptions();
    renderAthleteBoards();
  });
  $('#athlete-department-filter').addEventListener('change', () => {
    $('#athlete-sport-filter').value = '';
    syncAthleteSportOptions();
    renderAthleteBoards();
  });
  $('#athlete-sport-filter').addEventListener('change', renderAthleteBoards);
  $('#athlete-team-filter').addEventListener('input', renderAthleteBoards);
  $('#match-tournament-select').addEventListener('change', syncMatchTeamOptions);
  $('#result-match-select').addEventListener('change', syncResultWinnerOptions);
  $('#team-form [name="tournament_id"]').addEventListener('change', syncTeamSportOptions);
  $('#organization-type').addEventListener('change', syncOrganizationFields);
  $('#match-center-select').addEventListener('change', event => loadMatchCenter(event.target.value));
  $('#standings-tournament-filter').addEventListener('change', event => loadStandings(event.target.value));
  $('#event-team').addEventListener('change', syncEventStudents);
  $('#event-type').addEventListener('change', syncEventPoints);
  $('#clock-start').addEventListener('click', () => controlMatchClock('start'));
  $('#clock-pause').addEventListener('click', () => controlMatchClock('pause'));
  $('#clock-next').addEventListener('click', () => controlMatchClock('next_period'));
  $('#clock-overtime').addEventListener('click', () => controlMatchClock('overtime'));
  $('#match-event-form').addEventListener('submit', saveMatchEventFromForm);
  $('#tournament-sport-type').addEventListener('change', syncTournamentSportOptions);
  $('#tournament-sports-toggle').addEventListener('click', () => {
    const options = $('#tournament-sports-options');
    const isOpen = !options.hidden;
    options.hidden = isOpen;
    $('#tournament-sports-toggle').setAttribute('aria-expanded', String(!isOpen));
  });
  document.addEventListener('click', event => {
    if (event.target.closest('.sport-dropdown')) return;
    const options = $('#tournament-sports-options');
    if (!options || options.hidden) return;
    options.hidden = true;
    $('#tournament-sports-toggle').setAttribute('aria-expanded', 'false');
  });

  $$('.reset-form').forEach(button => {
    button.addEventListener('click', () => button.closest('form').reset());
  });
}

function bindSaveForm(selector, action, afterSave) {
  $(selector).addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
      const data = formData(form);
      const imageFiles = {
        '#sport-form': { selector: '#sport-logo-file', field: 'logo_url', kind: 'logo' },
        '#team-form': { selector: '#team-logo-file', field: 'logo_url', kind: 'logo' },
        '#tournament-form': { selector: '#tournament-logo-file', field: 'logo_url', kind: 'logo' },
        '#organization-form': { selector: '#organization-logo-file', field: 'logo_url', kind: 'logo' },
        '#student-form': { selector: '#student-photo-file', field: 'photo_url', kind: 'profile' },
      };
      const imageConfig = imageFiles[selector];
      if (imageConfig) {
        const file = $(imageConfig.selector)?.files?.[0];
        if (file) {
          const upload = await request('media.upload', { data_url: await fileToDataUrl(file), kind: imageConfig.kind });
          data[imageConfig.field] = upload.url;
          if (form.elements[imageConfig.field]) form.elements[imageConfig.field].value = upload.url;
        }
      }
      if (selector === '#sport-rule-form') {
        data.event_types = String(data.event_types_text || '').split(/\r?\n/).map(line => {
          const [code, label, points = '0'] = line.split('|').map(value => value.trim());
          return code && label ? { code, label, points: Number(points) || 0 } : null;
        }).filter(Boolean);
        delete data.event_types_text;
      }
      if (selector === '#tournament-form' && (!Array.isArray(data.sport_ids) || !data.sport_ids.length)) {
        throw new Error('กรุณาเลือกกีฬาอย่างน้อย 1 รายการ');
      }
      await request(action, data);
      finishEditor(form);
      await afterSave();
      toast('บันทึกข้อมูลแล้ว');
    });
  });
}

async function request(action, payload = {}, options = {}) {
  workspace.pending++;
  $('#page-progress').hidden = false;
  try {
  const response = await fetch(`${app.apiUrl}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': app.csrf,
    },
    body: JSON.stringify(payload),
  });
  const result = await response.json().catch(() => ({ ok: false, error: 'API ตอบกลับไม่ถูกต้อง' }));
  if (!response.ok || !result.ok) {
    const message = result.error || `API error ${response.status}`;
    if (!options.silent) toast(message, true);
    throw new Error(message);
  }
  return result.data;
  } finally {
    workspace.pending--;
    $('#page-progress').hidden = workspace.pending === 0;
  }
}

function updateSessionUi() {
  const loggedIn = app.user && app.user.role && app.user.role !== 'guest';
  $('#current-user-label').textContent = loggedIn ? (app.user.name || app.user.email) : 'ผู้เยี่ยมชม';
  $('#login-toggle').hidden = loggedIn;
  $('#profile-toggle').hidden = !loggedIn;
  $('#logout-button').hidden = !loggedIn;

  const roleViews = {
    super_admin: ['dashboard', 'sports', 'students', 'matches', 'news', 'tasks', 'users', 'reports'],
    admin: ['dashboard', 'sports', 'students', 'matches', 'news', 'tasks', 'users', 'reports'],
    sport_head: ['dashboard', 'sports', 'matches', 'tasks', 'reports'],
    registration_officer: ['dashboard', 'students', 'tasks', 'reports'],
    result_officer: ['dashboard', 'matches', 'tasks', 'reports'],
    public_relations: ['dashboard', 'news', 'tasks', 'reports'],
    viewer: ['dashboard', 'reports'],
  };
  app.allowedViews = roleViews[app.user?.role] || [];
  syncNavigationMode();
  updateShortcuts();
  refreshIcons($('.session-box'));
}

function syncNavigationMode() {
  const loggedIn = app.user && app.user.role && app.user.role !== 'guest';
  const publicView = app.currentView.startsWith('public');
  const adminMode = Boolean(loggedIn && !publicView);
  document.body.classList.toggle('admin-mode', adminMode);
  $$('.admin-link').forEach(button => {
    button.hidden = !adminMode || !app.allowedViews.includes(button.dataset.viewButton);
  });

  const switchButton = $('#frontend-view-button');
  switchButton.hidden = !loggedIn;
  switchButton.dataset.viewButton = publicView ? 'dashboard' : 'public';
  switchButton.href = publicView ? '?view=dashboard' : './';
  switchButton.title = publicView ? 'กลับหลังบ้าน' : 'ดูหน้าเว็บไซต์';
  switchButton.setAttribute('aria-label', switchButton.title);
  switchButton.innerHTML = `${icon(publicView ? 'layout-dashboard' : 'monitor')}<span>${switchButton.title}</span>`;
  refreshIcons(switchButton);
}

function openProfileDialog() {
  const form = $('#profile-form');
  form.reset();
  form.elements.email.value = app.user?.email || '';
  form.elements.name.value = app.user?.name || '';
  $('#profile-dialog').showModal();
  refreshIcons($('#profile-dialog'));
}

async function showView(name) {
  if (['dashboard', 'sports', 'students', 'teams', 'matches', 'news', 'tasks', 'users', 'reports'].includes(name) && (!app.user || app.user.role === 'guest')) {
    $('#login-dialog').showModal();
    return;
  }
  $$('.tab').forEach(button => button.classList.toggle('active', button.dataset.viewButton === name));
  $$('.view').forEach(view => view.hidden = true);
  const target = $(`#view-${name}`);
  if (target) {
    target.hidden = false;
  }
  app.currentView = name;
  $('#view-public-home-extra').hidden = name !== 'public';
  updateWorkspaceView(name);
  syncNavigationMode();
  window.scrollTo({ top: 0, behavior: 'instant' });

  try {
    if (name === 'public') await loadPublicHome();
    if (name === 'public-sports') renderPublicSportsPage();
    if (name === 'public-athletes') await loadPublicAthletes();
    if (name === 'public-schedule') await loadPublicSchedule($('#schedule-tournament-filter').value || '');
    if (name === 'public-results') await loadPublicResults($('#results-tournament-filter').value || '');
    if (name === 'public-news') renderPublicNewsPage();
    if (name === 'dashboard') await loadDashboard();
    if (name === 'sports') await loadSportsAdmin();
    if (name === 'students') await loadStudentsAdmin();
    if (name === 'teams') await loadTeamsAdmin();
    if (name === 'matches') {
      await loadMatchesAdmin();
      await loadTeamsAdmin();
      if (['super_admin', 'admin', 'registration_officer'].includes(app.user?.role)) await loadStudentsAdmin();
      else renderTable('#students-table', [['notice', 'ข้อมูล', () => 'สิทธิ์ของคุณดูแลขั้นตอนอื่นในทัวร์นาเมนต์']], [{ notice: '' }]);
    }
    if (name === 'news') await loadNewsAdmin();
    if (name === 'tasks') await loadTasksAdmin();
    if (name === 'users') await loadUsersAdmin();
    if (name === 'reports') await loadReportOptions();
    if (name === 'invite-accept') return;
    refreshIcons();
  } catch (error) {
    console.error(error);
    toast(error.message || 'โหลดข้อมูลไม่สำเร็จ', true);
  }
}

async function loadPublicHome() {
  app.publicData = await request('public.home');
  const data = app.publicData;
  $('#m-sports').textContent = data.summary.sports;
  $('#m-schedule').textContent = data.summary.scheduledMatches;
  $('#m-results').textContent = data.summary.completedMatches;
  $('#m-news').textContent = data.summary.news;
  renderChips('#public-sports', data.sports, row => `${row.name} · ${workspace.labels[row.gender] || row.gender}`);
  renderMatchCards('#public-schedule', data.schedule);
  renderNewsCards('#public-news', data.news.slice(0, 3));
  renderNewsCards('#public-hall', data.hallOfFame);
  app.sports = data.sports;
  app.tournaments = data.tournaments;
  syncPublicFilters();
}

async function filterPublicHome(tournamentId) {
  const schedule = await request('public.schedule', { tournament_id: tournamentId });
  renderMatchCards('#public-schedule', schedule);
}

function syncPublicFilters() {
  setOptions($('#home-tournament-filter'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ทุกทัวร์นาเมนท์');
  setOptions($('#schedule-tournament-filter'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ทุกทัวร์นาเมนท์');
  setOptions($('#results-tournament-filter'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ทุกทัวร์นาเมนท์');
}

function renderPublicSportsPage() {
  const rows = (app.publicData && app.publicData.sports) || app.sports || [];
  renderTable('#public-sports-table', [
    ['logo_url', 'โลโก้', value => value ? 'มี' : '-'],
    ['name', 'กีฬา'],
    ['type', 'ประเภท'],
    ['gender', 'เพศ'],
    ['max_players', 'ผู้เล่น'],
    ['reserve_players', 'สำรอง'],
    ['rules', 'กติกาย่อ'],
  ], rows);
}

async function loadPublicAthletes() {
  const data = await request('public.athletes');
  app.athleteBoards = data.teams || [];
  if (!app.sports.length) {
    app.sports = data.sports || [];
  }
  syncAthleteFacultyOptions();
  syncAthleteDepartmentOptions();
  syncAthleteSportOptions();
  renderAthleteBoards();
}

function athleteFacultyName(board) {
  return String(board.faculty_name || 'ไม่ระบุคณะ');
}

function syncAthleteFacultyOptions() {
  const select = $('#athlete-faculty-filter');
  const current = select.value;
  const rows = [...new Set((app.athleteBoards || []).map(athleteFacultyName))]
    .sort((a, b) => a.localeCompare(b, 'th'))
    .map(name => ({ name }));
  setOptions(select, rows, row => row.name, row => row.name, 'เลือกคณะ');
  if (rows.some(row => row.name === current)) select.value = current;
}

function syncAthleteDepartmentOptions() {
  const faculty = $('#athlete-faculty-filter').value;
  const select = $('#athlete-department-filter');
  const current = select.value;
  const seen = new Map();
  (app.athleteBoards || []).filter(board => athleteFacultyName(board) === faculty).forEach(board => {
    seen.set(String(board.department_code || ''), board.department_name || board.department_code || 'ไม่ระบุสาขา');
  });
  const rows = [...seen].map(([code, name]) => ({ code, name })).sort((a, b) => a.name.localeCompare(b.name, 'th'));
  setOptions(select, rows, row => row.code, row => row.name, 'เลือกสาขา');
  select.disabled = !faculty;
  if (rows.some(row => row.code === current)) select.value = current;
}

function syncAthleteSportOptions() {
  const faculty = $('#athlete-faculty-filter').value;
  const department = $('#athlete-department-filter').value;
  const select = $('#athlete-sport-filter');
  const current = select.value;
  const seen = new Map();
  (app.athleteBoards || []).filter(board => athleteFacultyName(board) === faculty && String(board.department_code || '') === department).forEach(board => {
    seen.set(String(board.sport_id || ''), board.sport_name || 'ไม่ระบุกีฬา');
  });
  const rows = [...seen].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'th'));
  setOptions(select, rows, row => row.id, row => row.name, 'เลือกกีฬา');
  select.disabled = !department;
  if (rows.some(row => row.id === current)) select.value = current;
}

function renderAthleteBoards() {
  const faculty = $('#athlete-faculty-filter').value;
  const department = $('#athlete-department-filter').value;
  const sport = $('#athlete-sport-filter').value;
  const ready = Boolean(faculty && department && sport);
  const currentStep = sport ? 4 : department ? 3 : faculty ? 2 : 1;
  $$('[data-athlete-flow-step]').forEach(element => {
    const step = Number(element.dataset.athleteFlowStep);
    element.classList.toggle('complete', step < currentStep || (ready && step === 4));
    element.classList.toggle('active', step === currentStep);
  });
  if (window.innerWidth <= 900) {
    $(`[data-athlete-flow-step="${currentStep}"]`)?.scrollIntoView({ behavior: 'auto', block: 'nearest', inline: 'center' });
  }
  $('#athlete-department-filter').disabled = !faculty;
  $('#athlete-sport-filter').disabled = !department;
  $('#athlete-results').hidden = !ready;
  const notice = !faculty
    ? 'เริ่มจากเลือกคณะ เพื่อดูสาขาที่มีนักกีฬาเข้าร่วม'
    : !department
      ? 'เลือกสาขาในคณะที่เลือก เพื่อดูชนิดกีฬาที่มีทีมเข้าร่วม'
      : !sport
        ? 'เลือกกีฬา เพื่อแสดงทีมและรายชื่อนักกีฬา'
        : 'เลือกครบแล้ว รายชื่อนักกีฬาแสดงอยู่ด้านล่าง';
  $('#athlete-flow-notice span').textContent = notice;
  $('#athlete-flow-notice').classList.toggle('complete', ready);
  if (!ready) {
    $('#athlete-total-count').textContent = '0 นักกีฬา';
    $('#athlete-board-list').innerHTML = '';
    refreshIcons($('#view-public-athletes'));
    return;
  }
  const query = ($('#athlete-team-filter').value || '').trim().toLowerCase();
  const rows = (app.athleteBoards || []).filter(row => {
    if (athleteFacultyName(row) !== faculty || String(row.department_code || '') !== department || String(row.sport_id || '') !== sport) return false;
    if (!query) return true;
    return [row.team_name, row.department_name, row.sport_name, ...(row.players || []).flatMap(player => [player.full_name, player.student_code, player.field_position])]
      .join(' ').toLowerCase().includes(query);
  }).map(board => ({
    ...board,
    players: (board.players || []).filter(player => !query || [player.full_name, player.student_code, player.field_position, board.team_name].join(' ').toLowerCase().includes(query)),
  }));
  const target = $('#athlete-board-list');
  const playerCount = rows.reduce((total, board) => total + board.players.length, 0);
  $('#athlete-total-count').textContent = `${playerCount} นักกีฬา`;
  const sportName = rows[0]?.sport_name || $('#athlete-sport-filter').selectedOptions[0]?.textContent || 'กีฬา';
  const departmentName = rows[0]?.department_name || $('#athlete-department-filter').selectedOptions[0]?.textContent || 'สาขา';
  $('#athlete-results-title').textContent = `${sportName} · ${departmentName}`;
  if (!rows.length) {
    target.innerHTML = emptyState('ไม่พบรายชื่อนักกีฬาตามคำค้นหา');
    return;
  }
  target.innerHTML = rows.map(board => `
    <section class="athlete-squad">
      <header class="athlete-squad-head">
        <div class="athlete-squad-identity">${board.team_logo_url ? `<img src="${escapeAttr(board.team_logo_url)}" alt="โลโก้ ${escapeAttr(board.team_name || '')}">` : icon('shield')}
          <div><span>${escapeHtml(board.tournament_name || board.sport_name || '')}</span><h4>${escapeHtml(board.team_name || '-')}</h4></div>
        </div>
        <strong>${board.players.length} ผู้เล่น</strong>
      </header>
      <div class="athlete-player-grid">${board.players.map(player => `
        <article class="athlete-player-card">
          <div class="athlete-player-visual">
            ${board.team_logo_url ? `<img class="athlete-team-watermark" src="${escapeAttr(board.team_logo_url)}" alt="" aria-hidden="true">` : ''}
            <span>${escapeHtml(player.shirt_number || '—')}</span>
            ${player.photo_url
              ? `<img class="athlete-profile-photo" src="${escapeAttr(player.photo_url)}" alt="รูปโปรไฟล์ ${escapeAttr(player.full_name || 'นักกีฬา')}">`
              : `<div class="athlete-player-initials">${escapeHtml(playerInitials(player.full_name || 'นักกีฬา'))}</div>`}
          </div>
          <div class="athlete-player-info">
            <small>${escapeHtml(player.field_position || player.role || 'นักกีฬา')}</small>
            <h5>${escapeHtml(player.full_name || '-')}</h5>
            <p>${escapeHtml(player.student_code || 'ไม่ระบุรหัสนักศึกษา')}</p>
          </div>
        </article>
      `).join('') || '<p class="athlete-empty-team">ทีมนี้ยังไม่มีรายชื่อสมาชิก</p>'}</div>
    </section>
  `).join('');
  refreshIcons(target);
}

function playerInitials(name) {
  return String(name).trim().split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase() || 'TS';
}

function syncStudentPhotoPreview(url = '') {
  const target = $('#student-photo-preview');
  if (!target) return;
  const source = String(url || '').trim();
  target.innerHTML = source
    ? `<img src="${escapeAttr(source)}" alt="ตัวอย่างรูปโปรไฟล์">`
    : icon('user-round');
  refreshIcons(target);
}

async function loadPublicSchedule(tournamentId = '') {
  const rows = await request('public.schedule', { tournament_id: tournamentId });
  renderMatchCards('#public-schedule-page', rows);
}

async function loadPublicResults(tournamentId = '') {
  const [rows, standings] = await Promise.all([
    request('public.results', { tournament_id: tournamentId }),
    request('public.standings', { tournament_id: tournamentId }),
  ]);
  renderMatchCards('#public-results-page', rows);
  renderTable('#public-standings-table', [
    ['sport_name', 'กีฬา'], ['rank_number', 'อันดับ'], ['team_name', 'ทีม'],
    ['played', 'แข่ง'], ['won', 'ชนะ'], ['drawn', 'เสมอ'], ['lost', 'แพ้'],
    ['score_difference', '+/-'], ['points', 'คะแนน'],
  ], standings);
}

function renderPublicNewsPage() {
  const rows = (app.publicData && app.publicData.news) || [];
  renderNewsCards('#public-news-page', rows);
}

async function loadAdminRefs() {
  const tasks = [
    request('sports.list', {}, { silent: true }).then(rows => app.sports = rows).catch(() => {}),
    request('tournaments.list', {}, { silent: true }).then(rows => app.tournaments = rows).catch(() => {}),
    request('departments.list', {}, { silent: true }).then(rows => app.departments = rows).catch(() => {}),
    request('teams.list', { tournament_id: '' }, { silent: true }).then(rows => app.teams = rows).catch(() => {}),
    request('matches.list', { tournament_id: '' }, { silent: true }).then(rows => app.matches = rows).catch(() => {}),
  ];
  await Promise.all(tasks);
  syncAdminOptions();
}

function syncAdminOptions() {
  $$('.sport-select').forEach(select => setOptions(select, app.sports, row => row.id, row => row.name, 'เลือกกีฬา'));
  syncTournamentSportOptions();
  $$('.tournament-select').forEach(select => setOptions(select, app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, select.classList.contains('optional') ? 'ทั้งหมด' : 'เลือกรายการแข่ง'));
  $$('.department-select').forEach(select => setOptions(select, app.departments, row => row.code, row => `${row.code} · ${row.name}`, select.classList.contains('optional') ? 'ไม่ระบุ' : 'เลือกสาขา'));
  setOptions($('#member-team-select'), app.teams, row => row.id, row => `${row.name} · ${row.tournament_name || '-'}`, 'เลือกทีม');
  setOptions($('#match-tournament-select'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'เลือกรายการแข่ง');
  setOptions($('#result-match-select'), app.matches, row => row.id, row => row.display_label, 'เลือกแมตช์');
  setOptions($('#match-center-select'), app.matches, row => row.id, row => row.display_label, 'เลือกแมตช์');
  setOptions($('#standings-tournament-filter'), app.tournaments, row => row.id, row => row.name, 'เลือกรายการแข่งขัน');
  setOptions($('#news-team-link'), app.teams, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ไม่ระบุทีม');
  setOptions($('#news-match-link'), app.matches, row => row.id, row => row.display_label, 'ไม่ระบุแมตช์');
  if ($('#invite-role-select') && $('#user-role-select')?.options.length) {
    $('#invite-role-select').innerHTML = $('#user-role-select').innerHTML;
  }
  syncMatchTeamOptions();
  syncTeamSportOptions();
  syncResultWinnerOptions();
  syncStudentScope();
}

function syncTeamSportOptions() {
  const select = $('#team-form [name="sport_id"]');
  const tournamentId = $('#team-form [name="tournament_id"]')?.value;
  if (!select) return;
  const tournament = app.tournaments.find(row => String(row.id) === String(tournamentId));
  const ids = (tournament?.sport_ids || []).map(String);
  const rows = ids.length ? app.sports.filter(row => ids.includes(String(row.id))) : app.sports;
  setOptions(select, rows, row => row.id, row => row.name, 'เลือกกีฬา');
}

function syncTournamentSportOptions() {
  const host = $('#tournament-sports-options');
  if (!host) return;
  const selected = new Set($$('input[name="sport_ids[]"]', host).filter(input => input.checked).map(input => input.value));
  const type = $('#tournament-sport-type')?.value || '';
  host.innerHTML = app.sports.map(row => `
    <label class="sport-choice" data-sport-type="${escapeAttr(row.type || '')}" ${type && row.type !== type ? 'hidden' : ''}><input type="checkbox" name="sport_ids[]" value="${escapeAttr(row.id)}" ${selected.has(String(row.id)) ? 'checked' : ''}><span>${escapeHtml(row.name)}</span><small>${escapeHtml(workspace.labels[row.type] || row.type || '')}</small></label>
  `).join('') || `<span class="muted">ยังไม่มีข้อมูลกีฬา กรุณาเพิ่มกีฬาก่อน</span>`;
  $$('input[name="sport_ids[]"]', host).forEach(input => input.addEventListener('change', updateTournamentSportsSummary));
  updateTournamentSportsSummary();
}

function updateTournamentSportsSummary() {
  const selected = $$('input[name="sport_ids[]"]', $('#tournament-sports-options')).filter(input => input.checked);
  const summary = $('#tournament-sports-summary');
  if (!summary) return;
  summary.textContent = selected.length ? `เลือกแล้ว ${selected.length} รายการ` : 'เลือกกีฬา';
}

function syncMatchTeamOptions() {
  const tournamentId = $('#match-tournament-select').value;
  const rows = app.teams.filter(row => !tournamentId || String(row.tournament_id) === String(tournamentId));
  setOptions($('#match-team1-select'), rows, row => row.id, row => row.name, 'ไม่ระบุ');
  setOptions($('#match-team2-select'), rows, row => row.id, row => row.name, 'ไม่ระบุ');
}

function syncResultWinnerOptions() {
  const match = app.matches.find(row => String(row.id) === String($('#result-match-select').value));
  const rows = match ? [
    { id: match.team1_id, name: match.team1_name },
    { id: match.team2_id, name: match.team2_name },
  ].filter(row => row.id) : [];
  setOptions($('#result-winner-select'), rows, row => row.id, row => row.name, 'ไม่ระบุ / เสมอ');
}

async function loadDashboard() {
  await loadAdminRefs();
  const data = await request('dashboard.summary');
  $('#d-active-sports').textContent = data.counts.activeSports;
  $('#d-approved-teams').textContent = data.counts.approvedTeams;
  $('#d-students').textContent = data.counts.students;
  $('#d-completed').textContent = data.counts.completedMatches;
  $('#d-open-tasks').textContent = data.counts.openTasks || 0;
  renderTable('#audit-table', [
    ['created_at', 'เวลา'],
    ['user_email', 'ผู้ใช้'],
    ['action', 'การดำเนินการ'],
    ['entity', 'ข้อมูล'],
    ['entity_id', 'รหัสอ้างอิง'],
  ], data.recentAudit || []);
}

async function loadDashboardCountsOnly() {
  const data = await request('dashboard.summary', {}, { silent: true });
  $('#d-open-tasks').textContent = data.counts.openTasks || 0;
}

async function loadSportsAdmin() {
  const [sports, rules, organizations] = await Promise.all([
    request('sports.list'),
    request('sportRules.list', { sport_id: '' }),
    request('organizations.list'),
  ]);
  app.sports = sports;
  app.sportRules = rules;
  app.organizations = organizations;
  renderTable('#sports-table', [
    ['id', 'ID'],
    ['name', 'กีฬา'],
    ['type', 'ประเภท'],
    ['gender', 'เพศ'],
    ['is_active', 'เปิดใช้', value => value ? 'เปิด' : 'ปิด'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#sport-form', row)],
      [row.is_active ? 'ปิด' : 'เปิด', () => toggleSport(row)],
      ['ลบ', () => deleteRow('sports.delete', row.id, loadSportsAdmin), 'danger'],
    ])],
  ], app.sports);
  renderTable('#sport-rules-table', [
    ['sport_name', 'กีฬา'],
    ['version_number', 'เวอร์ชัน'],
    ['name', 'ชื่อกติกา'],
    ['format', 'รูปแบบ', (_, row) => `${row.period_count} ช่วง x ${row.period_minutes} นาที`],
    ['overtime_enabled', 'ต่อเวลา', value => value ? 'มี' : 'ไม่มี'],
    ['is_active', 'ใช้งาน', value => Number(value) ? 'ใช้งาน' : 'เก็บประวัติ'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillSportRule(row)],
    ])],
  ], app.sportRules);
  const organizationRows = [
    ...(organizations.universities || []).map(row => ({ ...row, entity_type: 'university', level_label: 'มหาวิทยาลัย', parent_name: '-' })),
    ...(organizations.faculties || []).map(row => ({ ...row, entity_type: 'faculty', level_label: 'คณะ', parent_name: row.university_name || '-' })),
    ...(organizations.departments || []).map(row => ({ ...row, entity_type: 'department', level_label: 'สาขา', parent_name: row.faculty_name || '-' })),
  ];
  renderTable('#organizations-table', [
    ['level_label', 'ระดับ'], ['code', 'รหัส'], ['name', 'ชื่อ'], ['parent_name', 'ต้นสังกัด'],
    ['logo_url', 'โลโก้', value => value ? 'มี' : '-'],
    ['actions', 'จัดการ', (_, row) => rowActions([['แก้ไข', () => fillForm('#organization-form', row)]])],
  ], organizationRows);
  syncAdminOptions();
  syncOrganizationOptions();
}

function fillSportRule(row) {
  fillForm('#sport-rule-form', {
    ...row,
    event_types_text: (row.event_types_data || []).map(item => `${item.code}|${item.label}|${item.points || 0}`).join('\n'),
    custom_rules: row.custom_rules_text || '',
  });
}

function syncOrganizationOptions() {
  if (!app.organizations) return;
  setOptions($('#organization-university'), app.organizations.universities || [], row => row.id, row => row.name, 'เลือกมหาวิทยาลัย');
  setOptions($('#organization-faculty'), app.organizations.faculties || [], row => row.id, row => row.name, 'เลือกคณะ');
  syncOrganizationFields();
}

function syncOrganizationFields() {
  const type = $('#organization-type')?.value || 'department';
  $('#organization-university')?.closest('label')?.toggleAttribute('hidden', type !== 'faculty');
  $('#organization-faculty')?.closest('label')?.toggleAttribute('hidden', type !== 'department');
}

async function toggleSport(row) {
  await request('toggleSport', { args: [row.id, !Number(row.is_active)] });
  await loadSportsAdmin();
  toast('เปลี่ยนสถานะกีฬาแล้ว');
}

async function loadStudentsAdmin() {
  if (!app.departments.length) await loadAdminRefs();
  app.students = await request('students.list', {});
  renderScopedStudents();
}

function studentScopeValues() {
  return {
    facultyId: $('#student-scope-faculty')?.value || '',
    departmentCode: $('#student-scope-department')?.value || '',
    sportId: $('#student-scope-sport')?.value || '',
  };
}

function studentScopeReady() {
  const scope = studentScopeValues();
  return Boolean(scope.facultyId && scope.departmentCode && scope.sportId);
}

function syncStudentScope() {
  const facultySelect = $('#student-scope-faculty');
  if (!facultySelect) return;
  const scope = studentScopeValues();
  const faculties = [...new Map(app.departments.map(row => [
    String(row.faculty_id || `name:${row.faculty_name || 'unassigned'}`),
    { id: String(row.faculty_id || `name:${row.faculty_name || 'unassigned'}`), name: row.faculty_name || 'ไม่ระบุคณะ' },
  ])).values()];
  setOptions(facultySelect, faculties, row => row.id, row => row.name, 'เลือกคณะ/หน่วยงาน');
  if (faculties.some(row => row.id === scope.facultyId)) facultySelect.value = scope.facultyId;

  const facultyId = facultySelect.value;
  const departments = app.departments.filter(row => String(row.faculty_id || `name:${row.faculty_name || 'unassigned'}`) === facultyId);
  const departmentSelect = $('#student-scope-department');
  setOptions(departmentSelect, departments, row => row.code, row => `${row.code} · ${row.name}`, 'เลือกสาขา');
  departmentSelect.disabled = !facultyId;
  if (departments.some(row => String(row.code) === scope.departmentCode)) departmentSelect.value = scope.departmentCode;

  const departmentCode = departmentSelect.value;
  const workflowSports = app.workflow.data?.sports || [];
  const sports = workflowSports.length ? workflowSports : app.sports;
  const sportSelect = $('#student-scope-sport');
  setOptions(sportSelect, sports, row => row.id, row => row.name, 'เลือกกีฬา');
  sportSelect.disabled = !departmentCode;
  if (sports.some(row => String(row.id) === scope.sportId)) sportSelect.value = scope.sportId;
  renderScopedStudents();
}

function renderScopedStudents() {
  const target = $('#students-table');
  if (!target) return;
  const scope = studentScopeValues();
  const actionButtons = $$('[data-student-scope-action]');
  actionButtons.forEach(button => {
    button.disabled = !studentScopeReady();
    button.title = studentScopeReady() ? '' : 'เลือกคณะ สาขา และกีฬาให้ครบก่อน';
  });
  const status = $('#student-scope-status');
  if (!scope.facultyId) status.textContent = 'เริ่มจากเลือกคณะ/หน่วยงาน';
  else if (!scope.departmentCode) status.textContent = 'ขั้นต่อไป เลือกสาขา';
  else if (!scope.sportId) status.textContent = 'ขั้นต่อไป เลือกกีฬา';
  else {
    const department = app.departments.find(row => String(row.code) === scope.departmentCode);
    const sport = (app.workflow.data?.sports || app.sports).find(row => String(row.id) === scope.sportId);
    status.textContent = `${department?.name || scope.departmentCode} · ${sport?.name || 'กีฬาที่เลือก'}`;
  }
  $('#student-scope').classList.toggle('complete', studentScopeReady());
  if (!studentScopeReady()) {
    target.innerHTML = emptyState('เลือกคณะ สาขา และกีฬาให้ครบ เพื่อดูและจัดการรายชื่อนักศึกษา');
    refreshIcons(target);
    return;
  }

  const teamIds = new Set(app.teams
    .filter(team => String(team.department_code || '') === scope.departmentCode && String(team.sport_id || '') === scope.sportId)
    .map(team => String(team.id)));
  const rosterStudentIds = new Set(app.members
    .filter(member => teamIds.has(String(member.team_id)))
    .map(member => String(member.student_id)));
  const rows = app.students.filter(row => String(row.department_code || '') === scope.departmentCode);
  renderTable('#students-table', [
    ['id', 'ID'],
    ['photo_url', 'รูป', value => value ? `<img class="student-table-photo" src="${escapeAttr(value)}" alt="">` : icon('user-round'), { html: true }],
    ['student_code', 'รหัส'],
    ['full_name', 'ชื่อ', (_, row) => [row.prefix, row.first_name, row.last_name].filter(Boolean).join(' ')],
    ['department_name', 'สาขา'],
    ['roster_status', 'สถานะกีฬา', (_, row) => rosterStudentIds.has(String(row.id))
      ? '<span class="status-badge success">อยู่ในทีมแล้ว</span>'
      : '<span class="status-badge">รอจัดทีม</span>', { html: true }],
    ['phone', 'โทรศัพท์'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#student-form', row)],
      ['ลบ', () => deleteRow('students.delete', row.id, loadStudentsAdmin), 'danger'],
    ])],
  ], rows);
}

function focusMissingStudentScope() {
  const scope = studentScopeValues();
  const target = !scope.facultyId ? $('#student-scope-faculty') : !scope.departmentCode ? $('#student-scope-department') : $('#student-scope-sport');
  target?.focus();
  toast('กรุณาเลือกคณะ สาขา และกีฬาให้ครบก่อน', true);
}

function openScopedStudentEditor() {
  if (!studentScopeReady()) return focusMissingStudentScope();
  openEditor('student-form', { department_code: studentScopeValues().departmentCode });
}

function openStudentImport() {
  if (!studentScopeReady()) return focusMissingStudentScope();
  $('#students-csv')?.closest('dialog')?.showModal();
}

function scopedStudentImportRows() {
  if (!studentScopeReady()) {
    focusMissingStudentScope();
    throw new Error('กรุณาเลือกขอบเขตข้อมูลให้ครบก่อนนำเข้า');
  }
  const departmentCode = studentScopeValues().departmentCode;
  const rows = parseCsv($('#students-csv').value).map(row => ({ ...row, department_code: row.department_code || departmentCode }));
  if (rows.some(row => String(row.department_code) !== departmentCode)) {
    throw new Error(`ไฟล์ CSV ต้องเป็นสาขา ${departmentCode} เท่านั้น`);
  }
  return rows;
}

async function loadTeamsAdmin() {
  if (!app.sports.length || !app.tournaments.length || !app.departments.length) await loadAdminRefs();
  const tournamentId = app.workflow.tournamentId || '';
  app.teams = await request('teams.list', { tournament_id: tournamentId });
  const members = await request('teamMembers.list', { team_id: '' });
  const teamIds = new Set(app.teams.map(row => String(row.id)));
  app.members = tournamentId ? members.filter(row => teamIds.has(String(row.team_id))) : members;
  renderTable('#teams-table', [
    ['id', 'ID'],
    ['name', 'ทีม'],
    ['tournament_name', 'รายการ'],
    ['sport_name', 'กีฬา'],
    ['department_name', 'สาขา'],
    ['status', 'สถานะ', value => workspace.labels[value] || value],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#team-form', row)],
      ['อนุมัติ', () => setTeamStatus(row.id, 'approved')],
      ['ปฏิเสธ', () => setTeamStatus(row.id, 'rejected'), 'danger'],
      ['ลบ', () => deleteRow('teams.delete', row.id, loadTeamsAdmin), 'danger'],
    ])],
  ], app.teams);
  renderTable('#members-table', [
    ['id', 'ID'],
    ['team_name', 'ทีม'],
    ['student_code', 'รหัส'],
    ['full_name', 'ชื่อ'],
    ['field_position', 'ตำแหน่ง'],
    ['shirt_number', 'เบอร์'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#member-form', row)],
      ['ลบ', () => deleteRow('teamMembers.delete', row.id, loadTeamsAdmin), 'danger'],
    ])],
  ], app.members);
  syncAdminOptions();
}

async function setTeamStatus(id, status) {
  await request('teams.status', { id, status });
  await loadTeamsAdmin();
  if (app.workflow.tournamentId) await loadTournamentWorkspace(app.workflow.tournamentId, false);
  toast('เปลี่ยนสถานะทีมแล้ว');
}

async function loadMatchesAdmin() {
  await loadAdminRefs();
  const [tournaments, rules] = await Promise.all([
    request('tournaments.list'),
    request('sportRules.list', { sport_id: '' }),
  ]);
  app.tournaments = tournaments;
  app.sportRules = rules;
  const validSelection = app.tournaments.some(row => String(row.id) === String(app.workflow.tournamentId));
  app.workflow.tournamentId = validSelection ? String(app.workflow.tournamentId) : String(app.tournaments[0]?.id || '');
  app.matches = await request('matches.list', { tournament_id: app.workflow.tournamentId });
  renderTable('#tournaments-table', [
    ['id', 'ID'],
    ['name', 'รายการ'],
    ['logo_url', 'โลโก้', value => value ? 'มี' : '-'],
    ['sport_name', 'กีฬา'],
    ['academic_year', 'ปี'],
    ['format', 'รูปแบบ'],
    ['status', 'สถานะ', value => workspace.labels[value] || value],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#tournament-form', row)],
      ['ลบ', () => deleteRow('tournaments.delete', row.id, loadMatchesAdmin), 'danger'],
    ])],
  ], app.tournaments);
  renderTable('#sport-rules-table', [
    ['sport_name', 'กีฬา'],
    ['version_number', 'เวอร์ชัน'],
    ['name', 'ชื่อกติกา'],
    ['format', 'รูปแบบ', (_, row) => `${row.period_count} ช่วง x ${row.period_minutes} นาที`],
    ['is_active', 'ใช้งาน', value => Number(value) ? 'ใช้งาน' : 'เก็บประวัติ'],
    ['actions', 'จัดการ', (_, row) => rowActions([['แก้ไข', () => fillSportRule(row)]])],
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
      ['แก้ไข', () => fillForm('#match-form', row)],
      ['ศูนย์แข่งขัน', () => openMatchCenter(row.id)],
      ['บันทึกผล', () => openEditor('result-form', { match_id: row.id, team1_score: row.team1_score, team2_score: row.team2_score })],
      ['ลบ', () => deleteRow('matches.delete', row.id, loadMatchesAdmin), 'danger'],
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

async function openMatchCenter(matchId) {
  $('#match-center-select').value = String(matchId);
  await loadMatchCenter(String(matchId));
  setWorkflowStep(10, false);
  $('#match-center-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function loadMatchCenter(matchId) {
  clearInterval(app.clockTimer);
  app.clockTimer = null;
  app.matchCenter = null;
  $('#match-center-empty').hidden = Boolean(matchId);
  $('#match-center-content').hidden = !matchId;
  if (!matchId) return;
  const data = await request('matches.center', { match_id: matchId });
  app.matchCenter = data;
  const match = data.match;
  $('#center-team1').textContent = match.team1_name;
  $('#center-team2').textContent = match.team2_name;
  $('#center-score').textContent = `${match.team1_score || 0} - ${match.team2_score || 0}`;
  $('#center-period').textContent = `ช่วงที่ ${match.current_period || 1}`;
  $('#match-event-form [name="match_id"]').value = match.id;
  setOptions($('#event-type'), data.rule.event_types_data || [], row => row.code, row => row.label, 'เลือกเหตุการณ์');
  setOptions($('#event-team'), [
    { id: match.team1_id, name: match.team1_name },
    { id: match.team2_id, name: match.team2_name },
  ].filter(row => row.id), row => row.id, row => row.name, 'ไม่ระบุทีม');
  updateCenterClock(Number(match.clock_seconds) || 0);
  syncEventStudents();
  syncEventPoints();
  renderTable('#match-events-table', [
    ['time', 'เวลา', (_, row) => `${Math.floor(Number(row.elapsed_seconds) / 60)}:${String(Number(row.elapsed_seconds) % 60).padStart(2, '0')}`],
    ['event_type', 'เหตุการณ์', value => eventTypeLabel(value)],
    ['team_name', 'ทีม'], ['student_name', 'นักกีฬา'], ['points', 'แต้ม'], ['note', 'หมายเหตุ'],
    ['actions', 'จัดการ', (_, row) => Number(row.is_voided) ? 'ยกเลิกแล้ว' : rowActions([['ยกเลิกเหตุการณ์', () => voidMatchEvent(row.id)]])],
  ], data.events || []);
  if (match.clock_status === 'running') startLocalClock();
  refreshIcons($('#match-center-panel'));
}

function eventTypeLabel(code) {
  return app.matchCenter?.rule?.event_types_data?.find(row => row.code === code)?.label || code;
}

function syncEventStudents() {
  if (!app.matchCenter) return;
  const teamId = $('#event-team').value;
  const match = app.matchCenter.match;
  const rows = String(teamId) === String(match.team1_id) ? app.matchCenter.team1_members : String(teamId) === String(match.team2_id) ? app.matchCenter.team2_members : [];
  setOptions($('#event-student'), rows || [], row => row.student_id, row => `${row.shirt_number || '-'} ${row.full_name}`, 'ไม่ระบุนักกีฬา');
}

function syncEventPoints() {
  const definition = app.matchCenter?.rule?.event_types_data?.find(row => row.code === $('#event-type').value);
  $('#event-points').value = definition ? Number(definition.points || 0) : 0;
}

function updateCenterClock(seconds) {
  if (!app.matchCenter) return;
  app.matchCenter.match.clock_seconds = seconds;
  $('#center-clock').textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

function startLocalClock() {
  clearInterval(app.clockTimer);
  app.clockTimer = setInterval(() => updateCenterClock(Number(app.matchCenter.match.clock_seconds || 0) + 1), 1000);
}

async function controlMatchClock(clockAction) {
  if (!app.matchCenter) return;
  await request('matches.clock', {
    match_id: app.matchCenter.match.id,
    clock_action: clockAction,
    clock_seconds: app.matchCenter.match.clock_seconds,
    period_number: app.matchCenter.match.current_period,
  });
  await loadMatchCenter(String(app.matchCenter.match.id));
}

async function saveMatchEventFromForm(event) {
  event.preventDefault();
  const form = event.currentTarget;
  await runForm(form, async () => {
    const payload = formData(form);
    payload.elapsed_seconds = app.matchCenter.match.clock_seconds;
    payload.period_number = app.matchCenter.match.current_period;
    await request('matches.event', payload);
    form.elements.note.value = '';
    await loadMatchCenter(payload.match_id);
    await loadMatchesAdmin();
    toast('บันทึกเหตุการณ์แล้ว');
  });
}

async function voidMatchEvent(id) {
  if (!await confirmAction('ยกเลิกเหตุการณ์นี้?', 'เหตุการณ์จะยังอยู่ในประวัติ แต่ไม่นำไปคำนวณคะแนน', 'ยกเลิกเหตุการณ์', true)) return;
  await request('matches.eventVoid', { id });
  await loadMatchCenter(String(app.matchCenter.match.id));
  await loadMatchesAdmin();
}

async function loadStandings(tournamentId) {
  const rows = tournamentId ? await request('standings.list', { tournament_id: tournamentId }) : [];
  renderTable('#standings-table', [
    ['sport_name', 'กีฬา'], ['rank_number', 'อันดับ'], ['team_name', 'ทีม'], ['department_name', 'สาขา'],
    ['played', 'แข่ง'], ['won', 'ชนะ'], ['drawn', 'เสมอ'], ['lost', 'แพ้'],
    ['scored', 'ได้'], ['conceded', 'เสีย'], ['score_difference', '+/-'], ['points', 'คะแนน'],
  ], rows);
}

async function loadNewsAdmin() {
  if (!app.departments.length) await loadAdminRefs();
  const [news, matches, aiSettings] = await Promise.all([
    request('news.list'),
    request('matches.forNews'),
    request('ai.models', {}, { silent: true }).catch(() => null),
  ]);
  app.news = news;
  app.newsMatches = matches;
  app.aiSettings = aiSettings;
  $('#news-ai-status').textContent = aiSettings?.configured ? `Gemini: ${aiSettings.default_model}` : 'AI สำรองในระบบ';
  syncNewsAiOptions();
  renderTable('#news-table', [
    ['id', 'ID'],
    ['title', 'หัวข้อ'],
    ['news_type_label', 'ประเภท'],
    ['status', 'สถานะ'],
    ['is_pinned', 'ปักหมุด', value => value ? 'ใช่' : 'ไม่'],
    ['published_at', 'เผยแพร่'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#news-form', row)],
      ['เผยแพร่', () => publishNews(row.id)],
      ['ลบ', () => deleteRow('news.delete', row.id, loadNewsAdmin), 'danger'],
    ])],
  ], app.news);
}

function syncNewsAiOptions(source = '') {
  const sportSelect = $('#news-ai-sport');
  const tournamentSelect = $('#news-ai-tournament');
  const matchSelect = $('#news-ai-match');
  const sports = [...new Map(app.newsMatches.filter(row => row.sport_id).map(row => [String(row.sport_id), { id: row.sport_id, name: row.sport_name }])).values()];
  setOptions(sportSelect, sports, row => row.id, row => row.name, 'ทุกกีฬา');
  if (source === 'sport') tournamentSelect.value = '';
  const tournaments = [...new Map(app.newsMatches
    .filter(row => !sportSelect.value || String(row.sport_id) === sportSelect.value)
    .map(row => [String(row.tournament_id), { id: row.tournament_id, name: row.tournament_name }])).values()];
  setOptions(tournamentSelect, tournaments, row => row.id, row => row.name, 'ทุกรายการ');
  const visibleMatches = app.newsMatches.filter(row =>
    (!sportSelect.value || String(row.sport_id) === sportSelect.value)
    && (!tournamentSelect.value || String(row.tournament_id) === tournamentSelect.value));
  setOptions(matchSelect, visibleMatches, row => row.id, row => row.display_label, 'เลือกแมตช์ล่าสุดอัตโนมัติ');
}

async function loadTasksAdmin() {
  const [tasks, assignees] = await Promise.all([
    request('tasks.list'),
    request('tasks.assignees', {}, { silent: true }).catch(() => []),
  ]);
  app.tasks = tasks;
  app.taskAssignees = assignees;
  setOptions($('#task-assignee-select'), app.taskAssignees, row => row.email, row => `${row.name || row.email} (${row.role || '-'})`, 'ไม่ระบุ');
  renderTaskBoard(tasks);
  renderTable('#tasks-table', [
    ['title', 'งาน'],
    ['category_label', 'หมวด'],
    ['priority_label', 'ความสำคัญ'],
    ['status_label', 'สถานะ'],
    ['due_label', 'กำหนดเสร็จ'],
    ['assignee_label', 'ผู้รับผิดชอบ'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#task-form', row)],
      [row.status === 'done' ? 'เปิดใหม่' : 'เสร็จแล้ว', () => setTaskStatus(row.id, row.status === 'done' ? 'todo' : 'done')],
      ['ลบ', () => deleteRow('tasks.delete', row.id, loadTasksAdmin), 'danger'],
    ])],
  ], tasks);
}

function renderTaskBoard(rows) {
  const groups = {
    todo: rows.filter(row => row.status === 'todo').length,
    doing: rows.filter(row => row.status === 'doing').length,
    blocked: rows.filter(row => row.status === 'blocked').length,
    done: rows.filter(row => row.status === 'done').length,
  };
  const overdue = rows.filter(row => row.is_overdue).length;
  $('#task-board').innerHTML = `
    <div class="task-lane"><span>ต้องทำ</span><strong>${groups.todo}</strong></div>
    <div class="task-lane"><span>กำลังทำ</span><strong>${groups.doing}</strong></div>
    <div class="task-lane warning"><span>ติดปัญหา</span><strong>${groups.blocked}</strong></div>
    <div class="task-lane danger"><span>เกินกำหนด</span><strong>${overdue}</strong></div>
  `;
}

async function setTaskStatus(id, status) {
  await request('tasks.status', { id, status });
  await loadTasksAdmin();
  await loadDashboardCountsOnly();
  toast(status === 'done' ? 'ปิดงานแล้ว' : 'เปิดงานกลับมาทำต่อแล้ว');
}

async function publishNews(id) {
  await request('news.publish', { id });
  await loadNewsAdmin();
  await loadPublicHome();
  toast('เผยแพร่ข่าวแล้ว');
}

async function loadUsersAdmin() {
  const options = await request('users.options');
  app.departments = options.departments || [];
  app.sports = options.sports || app.sports;
  setOptions($('#user-role-select'), options.roles || [], row => row.value, row => row.label, 'เลือกบทบาท');
  setOptions($('#invite-role-select'), options.roles || [], row => row.value, row => row.label, 'เลือกบทบาท');
  syncAdminOptions();
  const [users, invites] = await Promise.all([
    request('users.list'),
    request('invites.list'),
  ]);
  app.users = users;
  app.invites = invites;
  renderTable('#users-table', [
    ['id', 'ID'],
    ['email', 'อีเมล'],
    ['name', 'ชื่อ'],
    ['role_label', 'บทบาท'],
    ['department_name', 'สาขา'],
    ['sport_names', 'กีฬา'],
    ['password_status', 'รหัสผ่าน'],
    ['active_label', 'สถานะ'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['แก้ไข', () => fillForm('#user-form', row)],
      ['ลบ', () => deleteRow('users.delete', row.id, loadUsersAdmin), 'danger'],
    ])],
  ], app.users);
  renderTable('#invites-table', [
    ['email', 'อีเมล'],
    ['name', 'ชื่อ'],
    ['role_label', 'บทบาท'],
    ['status_label', 'สถานะ'],
    ['expires_at', 'หมดอายุ'],
    ['expires_in_minutes', 'คงเหลือ', value => Number(value) > 0 ? `${value} นาที` : '-'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ...(row.invite_url ? [['คัดลอก', () => copyInviteLink(row.invite_url)]] : []),
      [row.status === 'pending' ? 'ยกเลิก' : 'ดูแล้ว', () => row.status === 'pending' ? revokeInvite(row.id) : Promise.resolve(), row.status === 'pending' ? 'danger' : ''],
    ])],
  ], app.invites);
}

function renderInviteLink(url) {
  const box = $('#invite-link-box');
  box.hidden = false;
  box.innerHTML = `
    <strong>ลิงก์คำเชิญ</strong>
    <input value="${escapeAttr(url)}" readonly>
    <button class="btn small" type="button">คัดลอกลิงก์</button>
    <p class="muted">ลิงก์นี้หมดอายุภายใน 45 นาที</p>
  `;
  $('button', box).addEventListener('click', () => copyInviteLink(url));
}

async function copyInviteLink(url) {
  await navigator.clipboard.writeText(url);
  toast('คัดลอกลิงก์คำเชิญแล้ว');
}

async function revokeInvite(id) {
  await request('invites.revoke', { id });
  await loadUsersAdmin();
  toast('ยกเลิกคำเชิญแล้ว');
}

async function openInviteAccept(token) {
  $('#invite-token-input').value = token;
  showView('invite-accept');
  try {
    const invite = await request('invites.preview', { token });
    $('#invite-email-input').value = invite.email || '';
    $('#invite-name-input').value = invite.name || '';
    $('#invite-preview-text').textContent = `คำเชิญสำหรับบทบาท ${invite.role_label || invite.role || '-'} หมดอายุ ${invite.expires_at || ''}`;
  } catch (error) {
    $('#invite-preview-text').textContent = error.message || 'คำเชิญไม่พร้อมใช้งาน';
    $('#accept-invite-form button[type="submit"]').disabled = true;
    toast($('#invite-preview-text').textContent, true);
  }
}

async function loadReportOptions() {
  const [options, aiSettings] = await Promise.all([
    request('reports.options'),
    request('ai.models', {}, { silent: true }).catch(() => ({ configured: false, default_model: 'local-summary' })),
  ]);
  app.reportOptions = options;
  app.aiSettings = aiSettings;
  ensureAiReportTools(aiSettings);
  renderReportCategories();
  syncReportFilters();
  await loadReportsAdmin();
}

async function loadReportsAdmin() {
  const filters = {
    query: $('#report-search').value,
    academic_year: $('#report-year-filter').value,
    category_id: $('#report-category-filter').value,
    template_id: $('#report-template-filter').value,
    status: $('#report-status-filter').value,
  };
  app.reports = await request('reports.list', filters);
  renderReportsDashboard();
}

function renderReportsDashboard() {
  const rows = app.reports;
  const totals = {
    all: rows.length,
    draft: rows.filter(row => row.status === 'draft').length,
    review: rows.filter(row => row.status === 'review').length,
    published: rows.filter(row => row.status === 'published').length,
  };
  $('#report-summary-strip').innerHTML =
    '<div><span>รายงานทั้งหมด</span><strong>' + totals.all + '</strong></div>' +
    '<div><span>แบบร่าง</span><strong>' + totals.draft + '</strong></div>' +
    '<div><span>รอตรวจสอบ</span><strong>' + totals.review + '</strong></div>' +
    '<div><span>เผยแพร่แล้ว</span><strong>' + totals.published + '</strong></div>';
  const permissions = app.reportOptions?.permissions || {};
  renderTable('#reports-table', [
    ['title', 'ชื่อรายงาน'],
    ['category_name', 'หมวดหมู่'],
    ['template_name', 'ประเภทแบบฟอร์ม'],
    ['academic_year', 'ปีการศึกษา'],
    ['report_date_label', 'วันที่จัดทำ'],
    ['prepared_by', 'ผู้จัดทำ'],
    ['status_label', 'สถานะ'],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ['ดูรายงาน', () => viewStoredReport(row.id)],
      ...(permissions.edit ? [['แก้ไข', () => editStoredReport(row.id)]] : []),
      ['ดาวน์โหลด', () => exportStoredReport(row.id)],
      ...(permissions.edit && row.status === 'draft' ? [['ส่งตรวจ', () => setReportStatus(row.id, 'review')]] : []),
      ...(permissions.edit && row.status === 'review' ? [['เสร็จสมบูรณ์', () => setReportStatus(row.id, 'completed')]] : []),
      ...(permissions.publish && row.status === 'completed' ? [['เผยแพร่', () => setReportStatus(row.id, 'published')]] : []),
      ...(permissions.publish ? [['ลบ', () => deleteRow('reports.delete', row.id, loadReportsAdmin), 'danger']] : []),
    ])],
  ], rows);
  $('#report-create-button').hidden = !permissions.edit;
}

function syncReportFilters() {
  const options = app.reportOptions || { categories: [], templates: [], tournaments: [], settings: {} };
  setOptions($('#report-category-filter'), options.categories, row => row.id, row => row.name, 'ทุกหมวดหมู่');
  setOptions($('#report-template-filter'), options.templates, row => row.id, row => row.name, 'ทุกแบบฟอร์ม');
  setOptions($('#report-tournament-select'), options.tournaments, row => row.id, row => row.name + (row.sport_name ? ' (' + row.sport_name + ')' : ''), 'ข้อมูลการแข่งขันทั้งหมด');
  const years = [...new Set(options.tournaments.map(row => row.academic_year).filter(Boolean))];
  const currentYear = options.settings.current_academic_year;
  if (currentYear && !years.includes(currentYear)) years.unshift(currentYear);
  setOptions($('#report-year-filter'), years.map(value => ({ value })), row => row.value, row => row.value, 'ทุกปี');
}

function renderReportCategories() {
  const host = $('#report-category-choices');
  const categories = app.reportOptions?.categories || [];
  host.innerHTML = '';
  categories.forEach(category => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'report-choice';
    button.dataset.value = category.id;
    button.innerHTML = '<span class="report-choice-icon">' + icon(category.icon || 'file-text') + '</span><strong>' + escapeHtml(category.name) + '</strong><small>' + escapeHtml(category.description || '') + '</small><span class="choice-check">' + icon('check') + '</span>';
    button.addEventListener('click', () => selectReportCategory(category));
    host.append(button);
  });
  refreshIcons(host);
}

function selectReportCategory(category) {
  const form = $('#report-editor-form');
  form.elements.category_id.value = category.id;
  form.elements.template_id.value = '';
  $$('.report-choice', $('#report-category-choices')).forEach(button => button.classList.toggle('selected', button.dataset.value === String(category.id)));
  $('#report-selected-category-label').textContent = category.name;
  renderReportTemplates(category.id);
}

function renderReportTemplates(categoryId) {
  const host = $('#report-template-choices');
  const templates = (app.reportOptions?.templates || []).filter(row => String(row.category_id) === String(categoryId));
  host.innerHTML = '';
  templates.forEach(template => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'report-choice template-choice';
    button.dataset.value = template.id;
    button.innerHTML = '<span class="report-choice-icon">' + icon('file-text') + '</span><strong>' + escapeHtml(template.name) + '</strong><small>' + escapeHtml(template.description || '') + '</small><span class="choice-check">' + icon('check') + '</span>';
    button.addEventListener('click', () => selectReportTemplate(template));
    host.append(button);
  });
  refreshIcons(host);
}

function selectReportTemplate(template) {
  const form = $('#report-editor-form');
  form.elements.template_id.value = template.id;
  $$('.report-choice', $('#report-template-choices')).forEach(button => button.classList.toggle('selected', button.dataset.value === String(template.id)));
  applyReportSections(template.sections || []);
}

function applyReportSections(sections) {
  $$('[data-report-section]', $('#report-editor-form')).forEach(section => {
    const name = section.dataset.reportSection;
    section.hidden = !sections.includes(name) && name !== 'general';
  });
}

function openReportBuilder(report = null) {
  const form = $('#report-editor-form');
  form.reset();
  app.activeReport = report;
  $('#report-dashboard').hidden = true;
  $('#report-builder').hidden = false;
  $('#report-builder-title').textContent = report ? 'แก้ไขรายงาน' : 'สร้างรายงานใหม่';
  form.elements.report_date.value = new Date().toISOString().slice(0, 10);
  form.elements.academic_year.value = app.reportOptions?.settings?.current_academic_year || '';
  form.elements.prepared_by.value = app.user?.name || app.user?.email || '';
  form.elements.status.value = 'draft';
  $('#report-kpi-editor').innerHTML = '';
  addKpiRow();
  $('#report-attachments-list').innerHTML = '';
  updateBudgetCalculation();
  renderReportCategories();
  if (report) {
    fillReportEditor(report);
    setReportStep(3);
  } else {
    setReportStep(1);
  }
  refreshIcons($('#report-builder'));
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function closeReportBuilder() {
  $('#report-builder').hidden = true;
  $('#report-dashboard').hidden = false;
  app.activeReport = null;
  loadReportsAdmin();
}

function fillReportEditor(report) {
  const form = $('#report-editor-form');
  const direct = ['id', 'category_id', 'template_id', 'status', 'title', 'academic_year', 'fiscal_year', 'semester', 'start_date', 'end_date', 'tournament_id', 'department', 'responsible_person', 'prepared_by', 'report_date', 'executive_summary'];
  direct.forEach(key => { if (form.elements[key]) form.elements[key].value = report[key] ?? ''; });
  const groups = [report.general || {}, report.performance || {}, report.outcomes || {}, report.problems || {}];
  groups.forEach(group => Object.entries(group).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value ?? ''; }));
  form.elements.budget_allocated.value = report.budget?.allocated ?? 0;
  form.elements.budget_actual.value = report.budget?.actual ?? 0;
  form.elements.budget_note.value = report.budget?.note ?? '';
  $('#report-kpi-editor').innerHTML = '';
  (report.kpis?.length ? report.kpis : [{}]).forEach(addKpiRow);
  const category = app.reportOptions.categories.find(row => String(row.id) === String(report.category_id));
  if (category) selectReportCategory(category);
  const template = app.reportOptions.templates.find(row => String(row.id) === String(report.template_id));
  if (template) selectReportTemplate(template);
  updateBudgetCalculation();
  renderReportAttachments(report.attachments || []);
  renderReportSource(report.sports_data);
  syncReportAiFields();
}

function setReportStep(step) {
  app.reportStep = step;
  $$('[data-report-step]').forEach(panel => panel.hidden = Number(panel.dataset.reportStep) !== step);
  $$('[data-report-step-indicator]').forEach(item => {
    const number = Number(item.dataset.reportStepIndicator);
    item.classList.toggle('active', number === step);
    item.classList.toggle('done', number < step);
  });
  $('#report-prev-step').hidden = step === 1;
  $('#report-save-draft').hidden = step !== 3 || !app.reportOptions?.permissions?.edit;
  $('#report-next-step').hidden = step === 4;
  $('#report-ai-assist').hidden = step !== 3 || !app.reportOptions?.permissions?.ai;
  refreshIcons($('#report-builder'));
}

async function nextReportStep() {
  const form = $('#report-editor-form');
  if (app.reportStep === 1) {
    if (!form.elements.category_id.value) return toast('กรุณาเลือกหมวดหมู่รายงาน', true);
    setReportStep(2);
    return;
  }
  if (app.reportStep === 2) {
    if (!form.elements.template_id.value) return toast('กรุณาเลือกประเภทแบบฟอร์ม', true);
    setReportStep(3);
    return;
  }
  if (app.reportStep === 3) {
    if (!form.reportValidity()) return;
    const report = await saveReportEditor(form.elements.status.value || 'draft', true);
    if (report) {
      await renderStoredReportPreview(report);
      setReportStep(4);
    }
  }
}

async function saveReportEditor(status = 'draft', quiet = false) {
  const form = $('#report-editor-form');
  if (!form.reportValidity()) return null;
  form.dataset.busy = 'true';
  form.setAttribute('aria-busy', 'true');
  try {
    const payload = collectReportPayload();
    payload.status = status;
    let report = await request('reports.save', payload);
    form.elements.id.value = report.id;
    form.elements.status.value = report.status;
    await uploadPendingReportFiles(report.id);
    report = await request('reports.get', { id: report.id });
    app.activeReport = report;
    renderReportAttachments(report.attachments || []);
    renderReportSource(report.sports_data);
    syncReportAiFields();
    if (!quiet) toast('บันทึกรายงานแล้ว');
    return report;
  } catch (error) {
    toast(error.message || 'บันทึกรายงานไม่สำเร็จ', true);
    return null;
  } finally {
    delete form.dataset.busy;
    form.removeAttribute('aria-busy');
  }
}

function collectReportPayload() {
  const form = $('#report-editor-form');
  const value = name => form.elements[name]?.value ?? '';
  return {
    id: value('id'),
    category_id: value('category_id'),
    template_id: value('template_id'),
    status: value('status') || 'draft',
    title: value('title'),
    academic_year: value('academic_year'),
    fiscal_year: value('fiscal_year'),
    semester: value('semester'),
    start_date: value('start_date'),
    end_date: value('end_date'),
    tournament_id: value('tournament_id'),
    department: value('department'),
    responsible_person: value('responsible_person'),
    prepared_by: value('prepared_by'),
    report_date: value('report_date'),
    executive_summary: value('executive_summary'),
    general: {
      project_name: value('project_name') || value('title'),
      competition_name: selectedTournamentName(),
      operation_date: value('operation_date') || value('start_date'),
      location: value('location'),
      rationale: value('rationale'),
      objectives: value('objectives'),
      target_group: value('target_group'),
    },
    performance: { operation_detail: value('operation_detail') },
    outcomes: {
      performance_result: value('performance_result'),
      benefits: value('benefits'),
      participant_impact: value('participant_impact'),
    },
    problems: {
      obstacles: value('obstacles'),
      solutions: value('solutions'),
      recommendations: value('recommendations'),
    },
    kpis: collectKpis(),
    budget: {
      allocated: value('budget_allocated'),
      actual: value('budget_actual'),
      note: value('budget_note'),
    },
  };
}

function applyReportAiDraft(result) {
  const draft = result.draft || {};
  const form = $('#report-editor-form');
  const set = (name, value, overwrite = true) => {
    if (!form.elements[name]) return;
    if (!overwrite && form.elements[name].value) return;
    if (value === undefined || value === null || value === '') return;
    form.elements[name].value = value;
  };

  set('title', draft.title);
  set('executive_summary', draft.executive_summary);
  Object.entries(draft.general || {}).forEach(([key, value]) => set(key, value));
  Object.entries(draft.performance || {}).forEach(([key, value]) => set(key, value));
  Object.entries(draft.outcomes || {}).forEach(([key, value]) => set(key, value));
  Object.entries(draft.problems || {}).forEach(([key, value]) => set(key, value));

  if (draft.budget) {
    set('budget_allocated', draft.budget.allocated, false);
    set('budget_actual', draft.budget.actual, false);
    set('budget_note', draft.budget.note);
    updateBudgetCalculation();
  }

  if (Array.isArray(draft.kpis) && draft.kpis.length) {
    $('#report-kpi-editor').innerHTML = '';
    draft.kpis.forEach(addKpiRow);
  }

  $('#report-output').innerHTML = `
    <article class="formal-report-card compact">
      <div class="ai-meta"><span class="badge">${escapeHtml(result.provider || 'local-summary')}</span><span>${escapeHtml(result.model || '')}</span></div>
      <h3>เติมร่างรายงานลงในแบบฟอร์มแล้ว</h3>
      <p>${escapeHtml(draft.executive_summary || 'AI ได้ช่วยเรียบเรียงข้อมูลตามหมวดหมู่รายงานที่เลือกเรียบร้อยแล้ว กรุณาตรวจทานความถูกต้องก่อนบันทึกหรือเผยแพร่')}</p>
      ${draft.fallback_reason ? `<p class="muted">ใช้ร่างสำรองในระบบ เนื่องจาก: ${escapeHtml(draft.fallback_reason)}</p>` : ''}
    </article>
  `;
  toast('AI เติมร่างรายงานลงในฟอร์มแล้ว');
  refreshIcons($('#report-builder'));
}

function selectedTournamentName() {
  const select = $('#report-tournament-select');
  return select.value ? select.options[select.selectedIndex]?.textContent || '' : '';
}

function addKpiRow(data = {}) {
  const row = document.createElement('div');
  row.className = 'kpi-row';
  row.innerHTML =
    '<label>ตัวชี้วัด<input data-kpi="name" value="' + escapeAttr(data.name || '') + '" placeholder="ชื่อตัวชี้วัด"></label>' +
    '<label>หน่วย<input data-kpi="unit" value="' + escapeAttr(data.unit || '') + '"></label>' +
    '<label>เป้าหมาย<input data-kpi="target" type="number" min="0" step="0.01" value="' + escapeAttr(data.target_value ?? data.target ?? 0) + '"></label>' +
    '<label>ผลจริง<input data-kpi="actual" type="number" min="0" step="0.01" value="' + escapeAttr(data.actual_value ?? data.actual ?? 0) + '"></label>' +
    '<label>วิธีคำนวณ<select data-kpi="calculation_type"><option value="percentage">คำนวณร้อยละ</option><option value="manual">กำหนดเอง</option></select></label>' +
    '<label>ความสำเร็จ (%)<input data-kpi="achievement" type="number" min="0" step="0.01" value="' + escapeAttr(data.achievement ?? 0) + '"></label>' +
    '<button class="btn icon-button danger" type="button" title="ลบ KPI" aria-label="ลบ KPI">' + icon('trash-2') + '</button>';
  $('[data-kpi="calculation_type"]', row).value = data.calculation_type || 'percentage';
  $('[data-kpi="calculation_type"]', row).addEventListener('change', () => updateKpiRow(row));
  $$('input', row).forEach(input => input.addEventListener('input', () => updateKpiRow(row)));
  $('button', row).addEventListener('click', () => {
    row.remove();
    if (!$('#report-kpi-editor').children.length) addKpiRow();
  });
  $('#report-kpi-editor').append(row);
  updateKpiRow(row);
  refreshIcons(row);
}

function updateKpiRow(row) {
  const type = $('[data-kpi="calculation_type"]', row).value;
  const target = Number($('[data-kpi="target"]', row).value || 0);
  const actual = Number($('[data-kpi="actual"]', row).value || 0);
  const achievement = $('[data-kpi="achievement"]', row);
  achievement.readOnly = type === 'percentage';
  if (type === 'percentage') achievement.value = target > 0 ? ((actual / target) * 100).toFixed(2) : '0.00';
}

function collectKpis() {
  return $$('.kpi-row', $('#report-kpi-editor')).map(row => {
    const result = {};
    $$('[data-kpi]', row).forEach(field => result[field.dataset.kpi] = field.value);
    return result;
  }).filter(row => row.name.trim());
}

function updateBudgetCalculation() {
  const form = $('#report-editor-form');
  const allocated = Number(form.elements.budget_allocated.value || 0);
  const actual = Number(form.elements.budget_actual.value || 0);
  form.elements.budget_remaining.value = (allocated - actual).toFixed(2);
  form.elements.budget_usage.value = (allocated > 0 ? actual / allocated * 100 : 0).toFixed(2);
}

function syncReportSourceLabel() {
  const text = selectedTournamentName();
  $('#report-source-summary').textContent = text
    ? 'ระบบจะดึงทีม นักกีฬา ชนิดกีฬา และผลการแข่งขันจาก ' + text
    : 'ระบบจะคำนวณข้อมูลจากรายการแข่งขันทั้งหมดที่มีอยู่ในระบบ';
  syncReportAiFields();
}

function renderReportSource(data) {
  const summary = data?.summary || {};
  $('#report-source-summary').textContent = 'พบ ' + (summary.sports || 0) + ' ชนิดกีฬา, ' + (summary.competitions || 0) + ' การแข่งขัน, ' + (summary.departments || 0) + ' หน่วยงาน และนักกีฬา ' + (summary.athletes || 0) + ' คน';
  const matches = data?.matches || [];
  $('#report-results-preview').innerHTML = matches.length
    ? '<div class="compact-result-list">' + matches.slice(0, 8).map(row => '<div><span>' + escapeHtml(row.sport_name || '-') + '</span><strong>' + escapeHtml(row.team1_name + ' ' + row.score_label + ' ' + row.team2_name) + '</strong></div>').join('') + '</div>' + (matches.length > 8 ? '<p class="muted">และอีก ' + (matches.length - 8) + ' รายการ</p>' : '')
    : '<p class="muted">ยังไม่มีข้อมูลการแข่งขันในขอบเขตที่เลือก</p>';
}

function renderReportAttachments(files) {
  $('#report-attachments-list').innerHTML = files.length
    ? '<ul class="attachment-list">' + files.map(file => '<li><a href="' + escapeAttr(file.file_url) + '" target="_blank" rel="noopener">' + escapeHtml(file.file_name) + '</a><span>' + escapeHtml(file.note || '') + '</span></li>').join('') + '</ul>'
    : '<p class="muted">ยังไม่มีไฟล์แนบ</p>';
}

async function uploadPendingReportFiles(reportId) {
  const input = $('#report-attachment-file');
  const files = [...input.files];
  for (const file of files) {
    await request('reports.attachment', {
      report_id: reportId,
      file_name: file.name,
      data_url: await fileToDataUrl(file),
      note: $('#report-attachment-note').value,
    });
  }
  if (files.length) {
    input.value = '';
    $('#report-attachment-note').value = '';
  }
}

async function renderStoredReportPreview(report) {
  const exported = await request('reports.export', { id: report.id });
  $('#stored-report-preview').innerHTML =
    '<div class="report-preview-toolbar"><span class="status-badge success">' + escapeHtml(report.status_label) + '</span><div class="actions">' +
    '<a class="btn small" href="' + escapeAttr(exported.word_url) + '" download>' + icon('file-down') + '<span>ดาวน์โหลด Word</span></a>' +
    '<a class="btn small" href="' + escapeAttr(exported.document_url) + '" download>' + icon('braces') + '<span>ข้อมูล JSON</span></a>' +
    '<a class="btn small primary" href="' + escapeAttr(exported.report_url) + '" target="_blank" rel="noopener">' + icon('printer') + '<span>พิมพ์ / บันทึก PDF</span></a></div></div>' +
    '<iframe title="ตัวอย่างรายงาน ' + escapeAttr(report.title) + '" src="' + escapeAttr(exported.report_url) + '"></iframe>';
  refreshIcons($('#stored-report-preview'));
}

async function viewStoredReport(id) {
  const report = await request('reports.get', { id });
  openReportBuilder(report);
  await renderStoredReportPreview(report);
  setReportStep(4);
}

async function editStoredReport(id) {
  openReportBuilder(await request('reports.get', { id }));
}

async function exportStoredReport(id) {
  const result = await request('reports.export', { id });
  window.open(result.report_url, '_blank', 'noopener');
}

async function setReportStatus(id, status) {
  await request('reports.status', { id, status });
  await loadReportsAdmin();
  toast('เปลี่ยนสถานะรายงานแล้ว');
}

function syncReportAiFields() {
  const editor = $('#report-editor-form');
  const aiForm = $('#report-form');
  if (!editor || !aiForm) return;
  const category = app.reportOptions?.categories?.find(row => String(row.id) === String(editor.elements.category_id.value));
  aiForm.elements.report_type.value = category?.code === 'competition' ? 'competition' : 'executive';
  aiForm.elements.tournament_id.value = editor.elements.tournament_id.value;
  aiForm.elements.academic_year.value = editor.elements.academic_year.value;
}

function debounce(callback, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => callback(...args), delay);
  };
}

function ensureAiReportTools(settings) {
  const head = $('#report-ai-tools') || $('#view-reports .section-head');
  if (!head) return;
  let status = $('#ai-provider-status');
  if (!status) {
    status = document.createElement('span');
    status.id = 'ai-provider-status';
    status.className = 'badge';
    head.append(status);
  }
  status.textContent = settings.configured ? `Gemini: ${settings.default_model}` : 'AI: local-summary';

  if ($('#ai-system-button')) return;
  const testButton = document.createElement('button');
  testButton.id = 'ai-test-button';
  testButton.className = 'btn small';
  testButton.type = 'button';
  testButton.innerHTML = `${icon('plug-zap')}<span>ทดสอบ AI</span>`;
  testButton.addEventListener('click', () => runTask(testButton, async () => {
    const result = await request('ai.test');
    renderAiTestResult(result);
    toast(result.ok ? 'Gemini เชื่อมต่อสำเร็จ' : 'ยังเชื่อม Gemini ไม่สำเร็จ', !result.ok);
  }));
  head.append(testButton);

  const button = document.createElement('button');
  button.id = 'ai-system-button';
  button.className = 'btn small';
  button.type = 'button';
  button.innerHTML = `${icon('sparkles')}<span>สรุปภาพรวม AI</span>`;
  button.addEventListener('click', () => runTask(button, async () => {
    const result = await request('ai.system', { instruction: $('#report-form [name=instruction]').value || '' });
    renderAiOutput('#report-output', result);
  }));
  head.append(button);
  refreshIcons(head);
}

function renderAiTestResult(result) {
  $('#report-output').innerHTML = `
    <div class="ai-meta"><span class="badge">${escapeHtml(result.provider || 'local-summary')}</span><span>${escapeHtml(result.model || '')}</span></div>
    <p><strong>${result.ok ? 'เชื่อมต่อ AI สำเร็จ' : 'เชื่อมต่อ AI ยังไม่สำเร็จ'}</strong></p>
    <p>${escapeHtml(result.message || '')}</p>
    <div class="ai-diagnostics">
      <span>DNS: ${escapeHtml(result.dns || '-')}</span>
      <span>TCP 443: ${result.tcp_443 ? 'ผ่าน' : 'ไม่ผ่าน'}</span>
      <span>PHP cURL: ${result.php_curl ? 'เปิดใช้งาน' : 'ไม่พร้อม'}</span>
      ${result.tcp_error ? `<span>Network: ${escapeHtml(result.tcp_error)}</span>` : ''}
    </div>
    ${result.latency_ms ? `<p class="muted">Latency: ${escapeHtml(result.latency_ms)} ms</p>` : ''}
  `;
}

function renderReportResult(result) {
  $('#report-output').innerHTML = `
    <article class="formal-report-card">
      <div class="ai-meta"><span class="badge">${escapeHtml(result.provider || 'local-summary')}</span><span>${escapeHtml(result.model || '')}</span></div>
      <header class="formal-report-head">
        <p class="eyebrow">รายงานระบบบริหารจัดการแข่งขันกีฬา</p>
        <h3>${escapeHtml(result.report_type_label || result.title || 'รายงานระบบกีฬา')}</h3>
        <dl>
          <div><dt>เลขที่เอกสาร</dt><dd>${escapeHtml(result.report_id || result.document_id || '-')}</dd></div>
          <div><dt>วันที่จัดทำ</dt><dd>${escapeHtml(result.created_at_label || result.created_at || '-')}</dd></div>
          <div><dt>ผู้จัดทำ</dt><dd>${escapeHtml(result.generated_by || '-')}</dd></div>
        </dl>
      </header>
      <section>
        <h4>สรุปผู้บริหาร</h4>
        <p>${escapeHtml(result.executive_summary || '')}</p>
      </section>
      ${renderMetricTable(result.metrics)}
      ${renderList('ประเด็นที่ควรดำเนินการต่อ', result.analysis)}
      ${renderList('ความเสี่ยงและข้อควรพิจารณา', result.risks)}
      ${renderList('ข้อเสนอแนะ', result.recommendations)}
      <div class="report-actions">
        ${result.word_url ? `<a class="btn primary" href="${escapeAttr(result.word_url)}" target="_blank" rel="noopener" download>ดาวน์โหลด Word</a>` : ''}
        ${result.report_url ? `<a class="btn primary" href="${escapeAttr(result.report_url)}" target="_blank" rel="noopener" download>ดาวน์โหลดรายงาน HTML</a>` : ''}
        <button class="btn" type="button" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
        <a class="btn ghost" href="${escapeAttr(result.document_url)}" target="_blank" rel="noopener">ข้อมูล JSON</a>
        <a class="btn ghost" href="${escapeAttr(result.sheet_url)}" target="_blank" rel="noopener">ตาราง CSV</a>
      </div>
    </article>
  `;
}

function renderMetricTable(metrics = {}) {
  const labels = {
    sports: 'จำนวนกีฬา',
    activeSports: 'กีฬาเปิดใช้งาน',
    teams: 'จำนวนทีม',
    approvedTeams: 'ทีมที่อนุมัติ',
    students: 'จำนวนนักศึกษา',
    tournaments: 'จำนวนรายการแข่งขัน',
    matches: 'จำนวนแมตช์',
    completedMatches: 'แมตช์ที่บันทึกผลแล้ว',
    openTasks: 'งานคงค้าง',
    overdueTasks: 'งานเกินกำหนด',
  };
  const rows = Object.entries(labels)
    .filter(([key]) => Object.prototype.hasOwnProperty.call(metrics, key))
    .map(([key, label]) => `<tr><th>${escapeHtml(label)}</th><td>${escapeHtml(metrics[key])}</td></tr>`)
    .join('');
  return rows ? `<section><h4>ตัวชี้วัดจากระบบ</h4><table class="report-metrics"><tbody>${rows}</tbody></table></section>` : '';
}

function renderAiOutput(selector, result) {
  const target = $(selector);
  target.innerHTML = `
    <div class="ai-meta"><span class="badge">${escapeHtml(result.provider || 'local-summary')}</span><span>${escapeHtml(result.model || '')}</span></div>
    <p>${escapeHtml(result.summary || '')}</p>
    ${renderList('งานถัดไป', result.next_actions)}
    ${renderList('ความเสี่ยง', result.risks)}
    ${renderList('ข้อเสนอแนะ', result.recommendations)}
    ${result.fallback_reason ? `<p class="muted">Fallback: ${escapeHtml(result.fallback_reason)}</p>` : ''}
  `;
}

function renderList(title, rows) {
  if (!Array.isArray(rows) || !rows.length) return '';
  return `<h4>${escapeHtml(title)}</h4><ul>${rows.map(row => `<li>${escapeHtml(row)}</li>`).join('')}</ul>`;
}

function renderChips(selector, rows, labeler) {
  const target = $(selector);
  target.innerHTML = rows.length
    ? rows.map(row => `<span class="chip">${escapeHtml(labeler(row))}</span>`).join('')
    : emptyState('ยังไม่มีข้อมูล');
}

function renderMatchCards(selector, rows) {
  const target = $(selector);
  if (!rows || !rows.length) {
    target.innerHTML = emptyState('ยังไม่มีแมตช์');
    return;
  }
  target.innerHTML = rows.map(row => `
    <article class="match-card">
      <div class="score-panel">
        <span class="badge ${row.status === 'live' ? 'live' : ''}">${escapeHtml(row.status_label || row.status || '-')}</span>
        <div class="score">${escapeHtml(row.score_label || '-- : --')}</div>
        <small>${escapeHtml(row.match_code || '')}</small>
      </div>
      <div class="match-info">
        ${row.tournament_logo_url ? `<img class="tournament-mark" src="${escapeAttr(row.tournament_logo_url)}" alt="โลโก้ ${escapeAttr(row.tournament_name || 'รายการแข่งขัน')}">` : ''}
        <div class="teams">
          <span>${escapeHtml(row.team1_name || 'ทีม 1')}</span>
          <span class="vs">VS</span>
          <span>${escapeHtml(row.team2_name || 'ทีม 2')}</span>
        </div>
        <div class="meta">
          <span>${escapeHtml(row.sport_name || '')}</span>
          <span>${escapeHtml(row.tournament_name || '')}</span>
          <span>${escapeHtml(row.scheduled_at || 'ยังไม่ระบุเวลา')}</span>
          ${row.venue ? `<span>${escapeHtml(row.venue)}</span>` : ''}
        </div>
      </div>
    </article>
  `).join('');
}

function renderNewsCards(selector, rows) {
  const target = $(selector);
  if (!rows || !rows.length) {
    target.innerHTML = emptyState('ยังไม่มีข่าว');
    return;
  }
  target.innerHTML = rows.map(row => `
    <article class="news-card">
      <div class="news-image">
        ${row.cover_image_url ? `<img src="${escapeAttr(row.cover_image_url)}" alt="">` : '<span>TECHED SPORT</span>'}
      </div>
      <div class="news-body">
        <span class="badge">${escapeHtml(row.news_type_label || 'ข่าวสาร')}</span>
        <h3>${escapeHtml(row.title || '')}</h3>
        <p>${escapeHtml(row.excerpt || row.body || '')}</p>
        <button type="button" class="news-read btn ghost">อ่านข่าว ${icon('arrow-up-right')}</button>
        <small class="muted">${escapeHtml(row.published_at || row.created_at || '')}</small>
      </div>
    </article>
  `).join('');
  $$('.news-read', target).forEach((button, index) => {
    button.setAttribute('aria-label', `อ่านข่าว ${rows[index].title || ''}`);
    button.addEventListener('click', () => openNews(rows[index]));
  });
  refreshIcons(target);
}

function renderTable(selector, columns, rows) {
  renderDataTable(selector, columns, rows);
}

function rowActions(actions) {
  const id = `a${Math.random().toString(16).slice(2)}`;
  queueMicrotask(() => {
    const host = document.getElementById(id);
    if (!host) return;
    actions.forEach(([label, handler, tone]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `btn icon-button ${tone || ''}`.trim();
      button.title = label;
      button.setAttribute('aria-label', label);
      const glyphs = { 'ดูรายงาน': 'eye', 'ดาวน์โหลด': 'download', 'ส่งตรวจ': 'send', 'เสร็จสมบูรณ์': 'badge-check', 'แก้ไข': 'pencil', 'ลบ': 'trash-2', 'เปิด': 'toggle-left', 'ปิด': 'toggle-right', 'อนุมัติ': 'check', 'ปฏิเสธ': 'x', 'เผยแพร่': 'send', 'บันทึกผล': 'clipboard-check', 'เสร็จแล้ว': 'check-check', 'เปิดใหม่': 'rotate-ccw' };
      button.innerHTML = icon(glyphs[label] || 'ellipsis');
      button.addEventListener('click', () => runTask(button, handler));
      host.appendChild(button);
    });
    refreshIcons(host);
  });
  return `<div class="row-actions" id="${id}"></div>`;
}

async function deleteRow(action, id, reload) {
  if (!await confirmAction('ลบรายการนี้?', `รายการ #${id} จะถูกลบออกจากระบบ และไม่สามารถเรียกคืนได้`, 'ลบรายการ', true)) return;
  await request(action, { id });
  await reload();
  toast('ลบข้อมูลแล้ว');
}

function fillForm(selector, row) {
  openEditor(selector.replace('#', ''), row);
  if (selector === '#user-form') {
    const form = $(selector);
    if (form.elements.password) form.elements.password.value = '';
    if (form.elements.password_confirm) form.elements.password_confirm.value = '';
  }
}

function formData(form) {
  const data = {};
  [...form.elements].forEach(field => {
    if (!field.name || field.type === 'file') return;
    if (field.name.endsWith('[]')) {
      const key = field.name.slice(0, -2);
      if (!Array.isArray(data[key])) data[key] = [];
      if (field.checked) data[key].push(field.value);
      return;
    }
    if (field.type === 'checkbox') {
      data[field.name] = field.checked;
      return;
    }
    data[field.name] = field.value;
  });
  return data;
}

function setOptions(select, rows, valueOf, labelOf, emptyLabel) {
  if (!select) return;
  const current = select.value;
  select.innerHTML = `<option value="">${escapeHtml(emptyLabel)}</option>` + rows.map(row => {
    const value = valueOf(row);
    return `<option value="${escapeAttr(value)}">${escapeHtml(labelOf(row))}</option>`;
  }).join('');
  if ([...select.options].some(option => option.value === current)) {
    select.value = current;
  }
}

function parseCsv(text) {
  const rows = [];
  let row = [];
  let cell = '';
  let quoted = false;
  const input = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
  for (let i = 0; i < input.length; i++) {
    const char = input[i];
    const next = input[i + 1];
    if (char === '"') {
      if (quoted && next === '"') {
        cell += '"';
        i++;
      } else {
        quoted = !quoted;
      }
      continue;
    }
    if (char === ',' && !quoted) {
      row.push(cell);
      cell = '';
      continue;
    }
    if (char === '\n' && !quoted) {
      row.push(cell);
      rows.push(row);
      row = [];
      cell = '';
      continue;
    }
    cell += char;
  }
  row.push(cell);
  rows.push(row);
  const clean = rows.filter(item => item.some(value => String(value).trim() !== ''));
  if (clean.length < 2) return [];
  const headers = clean[0].map(value => value.trim());
  return clean.slice(1).map(values => Object.fromEntries(headers.map((header, index) => [header, (values[index] || '').trim()])));
}

function importSummary(result) {
  return `ทั้งหมด ${result.total || 0} แถว, เพิ่ม ${result.created ? result.created.length : 0}, อัปเดต ${result.updated ? result.updated.length : 0}, ข้าม ${result.skipped ? result.skipped.length : 0}, error ${result.errors ? result.errors.length : 0}`;
}

function summarizeObject(value) {
  if (!value || typeof value !== 'object') return String(value || '');
  return Object.entries(value).map(([key, item]) => {
    if (Array.isArray(item)) return `${key}: ${item.join('\n- ')}`;
    if (item && typeof item === 'object') return `${key}: ${JSON.stringify(item)}`;
    return `${key}: ${item}`;
  }).join('\n');
}

function fileToDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

function emptyState(text) {
  return `<div class="empty-state">${icon('inbox')}<p>${escapeHtml(text)}</p></div>`;
}

function toast(message, isError = false) {
  const el = $('#toast');
  const activeDialog = $$('dialog[open]').at(-1);
  (activeDialog || document.body).append(el);
  el.innerHTML = `${icon(isError ? 'circle-alert' : 'circle-check')}<span>${escapeHtml(message)}</span>`;
  el.classList.toggle('error', isError);
  el.hidden = false;
  refreshIcons(el);
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => el.hidden = true, 3400);
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function escapeAttr(value) {
  return escapeHtml(value);
}
