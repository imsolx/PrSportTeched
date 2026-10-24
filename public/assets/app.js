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
  sportRules: [],
  organizations: null,
  officialOptions: [],
  matchAssignments: [],
  currentView: 'public',
  allowedViews: [],
  presentationRole: null,
  workflow: { tournamentId: '', step: 1, data: null },
};
const baseDocumentTitle = document.title;

const rolePresentationOptions = [
  {
    value: 'super_admin', label: 'System Admin', icon: 'shield-check',
    description: 'ควบคุมระบบทั้งหมด รวมถึงบัญชีผู้ใช้งาน',
    views: ['dashboard', 'sports', 'students', 'matches', 'official', 'news', 'users'],
    access: ['แดชบอร์ด', 'กีฬาและกติกา', 'นักกีฬา', 'ทัวร์นาเมนต์', 'บันทึกผล', 'ข่าวสาร', 'ผู้ดูแลระบบ'],
  },
  {
    value: 'admin', label: 'Operation Admin', icon: 'layout-dashboard',
    description: 'จัดการงานแข่งขันและทะเบียนนักกีฬาทุกส่วน ยกเว้นบัญชีผู้ใช้',
    views: ['dashboard', 'sports', 'students', 'matches', 'official', 'news'],
    access: ['แดชบอร์ด', 'กีฬาและกติกา', 'นักกีฬา', 'ทัวร์นาเมนต์', 'บันทึกผล', 'ข่าวสาร'],
  },
  {
    value: 'sport_head', label: 'Sport Manager', icon: 'trophy',
    description: 'ดูแลงานแข่งขันและผลของกีฬาที่รับผิดชอบ',
    views: ['dashboard', 'matches', 'official'],
    access: ['แดชบอร์ด', 'ทัวร์นาเมนต์', 'ศูนย์ควบคุมการแข่งขัน'],
  },
  {
    value: 'result_officer', label: 'Match Official', icon: 'timer',
    description: 'บันทึกและยืนยันผลการแข่งขันที่ได้รับมอบหมาย',
    views: ['official'],
    access: ['ศูนย์ควบคุมการแข่งขัน'],
  },
  {
    value: 'public_relations', label: 'ฝ่ายประชาสัมพันธ์', icon: 'newspaper',
    description: 'จัดทำและเผยแพร่ข่าวการแข่งขัน',
    views: ['dashboard', 'news'],
    access: ['แดชบอร์ด', 'ข่าวสาร'],
  },
];
const presentationStorageKey = 'prsport.presentationRole';
const actualRole = () => app.user?.role || 'guest';
const activeRole = () => app.presentationRole || actualRole();
const rolePresentation = role => rolePresentationOptions.find(option => option.value === role);
const defaultAdminView = role => role === 'result_officer' ? 'official' : 'dashboard';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const sportIconOptions = [
  ['football', 'ฟุตบอล'], ['futsal', 'ฟุตซอล'], ['volleyball', 'วอลเลย์บอล'], ['basketball', 'บาสเกตบอล'],
  ['badminton', 'แบดมินตัน'], ['table-tennis', 'ปิงปอง'], ['tennis', 'เทนนิส'],
  ['running', 'กรีฑา'], ['swimming', 'ว่ายน้ำ'], ['sepak-takraw', 'เซปักตะกร้อ'],
  ['petanque', 'เปตอง'], ['esports', 'อีสปอร์ต'], ['general', 'ทั่วไป'],
];
const sportIconAliases = {
  futsal: 'futsal', goal: 'football', soccer: 'football',
  athletics: 'running', jogging: 'running', sprint: 'running',
  pingpong: 'table-tennis', 'table_tennis': 'table-tennis', tabletennis: 'table-tennis',
  takraw: 'sepak-takraw', 'sepak_takraw': 'sepak-takraw',
  petanque: 'petanque', 'pétanque': 'petanque',
};
const sportIconNameRules = [
  ['sepak-takraw', ['เซปักตะกร้อ', 'ตะกร้อ', 'sepak takraw', 'takraw']],
  ['table-tennis', ['เทเบิลเทนนิส', 'ปิงปอง', 'table tennis', 'ping pong', 'pingpong']],
  ['badminton', ['แบดมินตัน', 'badminton']],
  ['volleyball', ['วอลเลย์บอล', 'volleyball']],
  ['basketball', ['บาสเกตบอล', 'basketball']],
  ['petanque', ['เปตอง', 'petanque', 'pétanque']],
  ['swimming', ['ว่ายน้ำ', 'swimming']],
  ['running', ['วิ่ง', 'กรีฑา', 'running', 'athletics', 'sprint']],
  ['esports', ['อีสปอร์ต', 'กีฬาอิเล็กทรอนิกส์', 'esport', 'e-sport']],
  ['tennis', ['เทนนิส', 'tennis']],
  ['futsal', ['ฟุตซอล', 'futsal']],
  ['football', ['ฟุตบอล', 'football', 'soccer']],
];
const flaticonSportFiles = {
  futsal: 'futsal.png',
  football: 'football.png',
  volleyball: 'volleyball.png',
  basketball: 'basketball.png',
  badminton: 'badminton.png',
  'table-tennis': 'table-tennis.png',
  running: 'running.png',
  'sepak-takraw': 'sepak-takraw.png',
  petanque: 'petanque.png',
};
const sportIconFile = key => flaticonSportFiles[key] || `${key}.svg`;
const sportIconAsset = (value, sportName = '') => {
  const rawKey = String(value || '').trim().toLowerCase();
  const directKey = sportIconAliases[rawKey] || rawKey;
  const validKeys = new Set(sportIconOptions.map(([id]) => id));
  const searchText = `${sportName} ${value}`.trim().toLocaleLowerCase('th');
  const inferredKey = sportIconNameRules.find(([, terms]) => terms.some(term => searchText.includes(term)))?.[0];
  if (inferredKey) return `public/assets/sports/${sportIconFile(inferredKey)}`;
  if (validKeys.has(directKey) && directKey !== 'general') return `public/assets/sports/${sportIconFile(directKey)}`;

  const key = inferredKey || (validKeys.has(directKey) ? directKey : 'general');
  return `public/assets/sports/${sportIconFile(key)}`;
};

document.addEventListener('DOMContentLoaded', () => {
  initWorkspace();
  bindSportIconPicker();
  initBannerStudio();
  bindNavigation();
  bindAuth();
  bindRoleSwitcher();
  bindForms();
  bindAdminUsers();
  bindTournamentWorkspace();
  restorePresentationRole();
  updateSessionUi();
  const inviteToken = new URLSearchParams(window.location.search).get('invite');
  if (inviteToken) {
    openInviteAccept(inviteToken);
  } else {
    const requestedView = new URLSearchParams(window.location.search).get('view');
    const initialView = requestedView && $(`#view-${requestedView}`) ? requestedView : 'public';
    showView(initialView, { history: 'replace' });
  }
});

window.addEventListener('popstate', () => {
  const requestedView = new URLSearchParams(window.location.search).get('view');
  showView(requestedView && $(`#view-${requestedView}`) ? requestedView : 'public', { history: 'none' });
});

function bindNavigation() {
  $$('[data-view-button]').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      if (button.dataset.viewButton === 'matches') tournamentUi.page = 'list';
      if (button.dataset.viewButton === 'public-standings' && button.closest('#view-public-results')) {
        $('#public-standings-tournament-filter').value = $('#results-tournament-filter').value;
      }
      showView(button.dataset.viewButton, { history: 'push' });
    });
  });
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
    clearPresentationRole();
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
    clearPresentationRole();
    $('#login-dialog').close();
    form.reset();
    updateSessionUi();
    await loadAdminRefs();
    showView(defaultAdminView(actualRole()));
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

function bindRoleSwitcher() {
  const dialog = $('#role-switch-dialog');
  $('#role-switch-button').addEventListener('click', openRoleSwitcher);
  $('#role-switch-close').addEventListener('click', () => dialog.close());
  $('#role-switch-reset').addEventListener('click', () => setPresentationRole(null));
  $('#role-preview-exit').addEventListener('click', () => setPresentationRole(null));
  bindDialogDismiss(dialog, () => dialog.close());
}

function restorePresentationRole() {
  if (actualRole() !== 'super_admin') {
    sessionStorage.removeItem(presentationStorageKey);
    return;
  }
  const storedRole = sessionStorage.getItem(presentationStorageKey);
  if (storedRole && storedRole !== 'super_admin' && rolePresentation(storedRole)) {
    app.presentationRole = storedRole;
  }
}

function clearPresentationRole() {
  app.presentationRole = null;
  sessionStorage.removeItem(presentationStorageKey);
}

function renderRoleSwitcher() {
  const selectedRole = activeRole();
  const selected = rolePresentation(selectedRole);
  $('#role-switch-status').innerHTML = `
    <span>มุมมองปัจจุบัน</span>
    <strong>${icon(selected?.icon || 'user')}${escapeHtml(selected?.label || selectedRole)}</strong>
    <small>${app.presentationRole ? 'กำลังจำลองมุมมองสำหรับการพรีเซนต์' : 'มุมมองตามสิทธิ์บัญชีจริง'}</small>`;
  $('#role-switch-grid').innerHTML = rolePresentationOptions.map(option => `
    <button class="role-option${option.value === selectedRole ? ' active' : ''}" type="button" data-presentation-role="${option.value}" aria-pressed="${option.value === selectedRole}">
      <span class="role-option-icon">${icon(option.icon)}</span>
      <span class="role-option-copy"><strong>${escapeHtml(option.label)}</strong><small>${escapeHtml(option.description)}</small></span>
      <span class="role-option-state">${option.value === selectedRole ? `${icon('check')} กำลังดู` : 'ดูมุมมองนี้'}</span>
      <span class="role-access-list">${option.access.map(item => `<span>${escapeHtml(item)}</span>`).join('')}</span>
    </button>`).join('');
  $$('[data-presentation-role]', $('#role-switch-grid')).forEach(button => {
    button.addEventListener('click', () => setPresentationRole(button.dataset.presentationRole));
  });
  $('#role-switch-reset').hidden = !app.presentationRole;
  refreshIcons($('#role-switch-dialog'));
}

function openRoleSwitcher() {
  renderRoleSwitcher();
  $('#role-switch-dialog').showModal();
}

async function setPresentationRole(role) {
  if (actualRole() !== 'super_admin') return;
  const nextRole = role && role !== actualRole() && rolePresentation(role) ? role : null;
  app.presentationRole = nextRole;
  if (nextRole) sessionStorage.setItem(presentationStorageKey, nextRole);
  else sessionStorage.removeItem(presentationStorageKey);
  if ($('#role-switch-dialog').open) $('#role-switch-dialog').close();
  updateSessionUi();
  await showView(defaultAdminView(activeRole()), { history: 'push' });
  toast(nextRole ? `กำลังจำลองมุมมอง ${rolePresentation(nextRole).label}` : 'กลับสู่มุมมอง System Admin แล้ว');
}

function bindForms() {
  $('#scoring-type')?.addEventListener('change', () => syncScoringConfigFields(true));
  syncScoringConfigFields();
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
  bindSaveForm('#tournament-form', 'tournaments.save', onTournamentSaved);
  bindSaveForm('#tournament-sport-setting-form', 'tournaments.sportSetting', async saved => {
    await loadTournamentWorkspace(app.workflow.tournamentId, false);
    if (Number(saved?.migrated_matches || 0) > 0) {
      toast(`เปลี่ยนชุดกติกาแล้ว · อัปเดตแมตช์ที่ยังไม่เริ่ม ${saved.migrated_matches} แมตช์`);
    }
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
  $('#assignment-match').addEventListener('change', loadMatchAssignments);
  $('#match-assignment-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
      const matchId = $('#assignment-match').value;
      const userId = $('#assignment-user').value;
      const teamId = $('#assignment-team').value;
      const assignments = app.matchAssignments.filter(row => String(row.user_id) !== String(userId))
        .map(row => ({ user_id: row.user_id, team_id: row.team_id || '' }));
      assignments.push({ user_id: userId, team_id: teamId });
      await request('matches.assignOfficials', { match_id: matchId, assignments });
      await loadMatchAssignments();
      toast('มอบหมายผู้รับผิดชอบแล้ว');
    });
  });
  $('#accept-invite-form').addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    await runForm(form, async () => {
      const result = await request('invites.accept', formData(form));
      app.user = result.user;
      app.csrf = result.csrf;
      clearPresentationRole();
      updateSessionUi();
      window.history.replaceState({}, document.title, window.location.pathname);
      await loadAdminRefs();
      showView(defaultAdminView(activeRole()));
      toast('ตั้งค่าบัญชีและเข้าสู่ระบบแล้ว');
    });
  });

  bindNewsEditor();

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
  bindNewsAi();
  bindNewsCoverInputs();
  $('#home-tournament-filter').addEventListener('change', event => filterPublicHome(event.target.value));
  $('#schedule-tournament-filter').addEventListener('change', event => loadPublicSchedule(event.target.value));
  $('#results-tournament-filter').addEventListener('change', event => loadPublicResults(event.target.value));
  $('#public-standings-tournament-filter').addEventListener('change', event => loadPublicStandingsPage(event.target.value));
  $('#bracket-tournament-filter').addEventListener('change', event => {
    syncBracketSportOptions(event.target.value);
    loadPublicBracket(event.target.value, '');
  });
  $('#bracket-sport-filter').addEventListener('change', event => loadPublicBracket($('#bracket-tournament-filter').value, event.target.value));
  bindPublicNewsFilters();
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
  $('#team-form [name="tournament_id"]').addEventListener('change', syncTeamSportOptions);
  $('#organization-type').addEventListener('change', syncOrganizationFields);
  $('#standings-tournament-filter').addEventListener('change', event => loadStandings(event.target.value));
  $('#official-refresh').addEventListener('click', () => loadOfficialCenter(true));
  $('#official-back-button').addEventListener('click', () => showView(app.allowedViews.includes('dashboard') ? 'dashboard' : 'official'));
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
    button.addEventListener('click', () => {
      const form = button.closest('form');
      form.reset();
      if (form.id === 'sport-form') setSportIconSelection('general');
    });
  });
}

function bindSportIconPicker() {
  const form = $('#sport-form');
  const picker = $('#sport-icon-picker');
  if (!form || !picker) return;
  picker.innerHTML = sportIconOptions.map(([id, label]) => `
    <button type="button" class="sport-icon-choice" data-sport-icon="${id}" aria-pressed="false" title="${label}">
      <img src="${sportIconAsset(id)}" alt=""><span>${label}</span>
    </button>`).join('');
  picker.addEventListener('click', event => {
    const button = event.target.closest('[data-sport-icon]');
    if (button) setSportIconSelection(button.dataset.sportIcon);
  });
  form.addEventListener('reset', () => queueMicrotask(() => {
    if (!form.elements.id?.value) setSportIconSelection('general');
  }));
  setSportIconSelection(form.elements.icon.value || 'general');
}

function setSportIconSelection(value) {
  const form = $('#sport-form');
  const key = sportIconAliases[String(value || '').toLowerCase()] || String(value || '').toLowerCase();
  const selected = sportIconOptions.some(([id]) => id === key) ? key : 'general';
  if (form?.elements.icon) form.elements.icon.value = selected;
  $$('#sport-icon-picker [data-sport-icon]').forEach(button => {
    const active = button.dataset.sportIcon === selected;
    button.classList.toggle('selected', active);
    button.setAttribute('aria-pressed', String(active));
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
          const [code, label, points = '0', participant = 'optional'] = line.split('|').map(value => value.trim());
          return code && label ? {
            code,
            label,
            points: Number(points) || 0,
            participant: ['athlete', 'team', 'optional'].includes(participant) ? participant : 'optional',
          } : null;
        }).filter(Boolean);
        delete data.event_types_text;
      }
      if (selector === '#tournament-form' && (!Array.isArray(data.sport_ids) || !data.sport_ids.length)) {
        throw new Error('กรุณาเลือกกีฬาอย่างน้อย 1 รายการ');
      }
      const saved = await request(action, data);
      finishEditor(form);
      await afterSave(saved);
      if (selector !== '#tournament-sport-setting-form' || Number(saved?.migrated_matches || 0) === 0) toast('บันทึกข้อมูลแล้ว');
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
  const role = activeRole();
  const roleOption = rolePresentation(role);
  $('#current-user-label').textContent = loggedIn ? (app.user.name || app.user.email) : 'ผู้เยี่ยมชม';
  $('#current-user-label').title = loggedIn ? `สิทธิ์บัญชีจริง: ${rolePresentation(actualRole())?.label || actualRole()}` : '';
  $('#login-toggle').hidden = loggedIn;
  $('#profile-toggle').hidden = !loggedIn;
  $('#logout-button').hidden = !loggedIn;
  $('#role-switch-button').hidden = actualRole() !== 'super_admin';
  const canRecordResults = ['super_admin', 'admin', 'result_officer', 'sport_head'].includes(role);
  $$('.result-only').forEach(element => element.hidden = !canRecordResults);
  $$('.tournament-only').forEach(element => element.hidden = !['super_admin', 'admin'].includes(role));
  $$('.schedule-only').forEach(element => element.hidden = !['super_admin', 'admin', 'sport_head'].includes(role));
  $$('.athlete-only').forEach(element => element.hidden = !['super_admin', 'admin'].includes(role));
  ['workflow-save-departments', 'workflow-edit-sports'].forEach(id => {
    const control = document.getElementById(id);
    if (control) control.hidden = !['super_admin', 'admin'].includes(role);
  });
  app.allowedViews = roleOption?.views || [];
  const previewBar = $('#role-preview-bar');
  previewBar.hidden = !app.presentationRole;
  document.body.classList.toggle('role-preview-active', Boolean(app.presentationRole));
  if (app.presentationRole) {
    $('#role-preview-label').textContent = roleOption?.label || role;
    $('#role-switch-button span').textContent = roleOption?.label || 'สลับมุมมอง';
  } else {
    $('#role-switch-button span').textContent = 'สลับมุมมอง';
  }
  syncNavigationMode();
  updateShortcuts();
  refreshIcons($('.topbar'));
  refreshIcons(previewBar);
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
  const adminTarget = defaultAdminView(activeRole());
  switchButton.dataset.viewButton = publicView ? adminTarget : 'public';
  switchButton.href = publicView ? `?view=${adminTarget}` : './';
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

function syncViewRoute(name, historyMode = 'replace') {
  if (historyMode === 'none' || name === 'invite-accept') return;
  const url = new URL(window.location.href);
  url.searchParams.delete('invite');
  if (name === 'public') url.searchParams.delete('view');
  else url.searchParams.set('view', name);
  if (name !== 'public-news-detail' && !isNewsEditorView(name)) url.searchParams.delete('id');
  if (name !== 'matches') ['tournament', 'section', 'create'].forEach(key => url.searchParams.delete(key));

  const state = { ...(window.history.state || {}), view: name };
  const nextUrl = `${url.pathname}${url.search}${url.hash}`;
  const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
  if (historyMode === 'push' && nextUrl !== currentUrl) {
    window.history.pushState(state, '', url);
  } else {
    window.history.replaceState(state, '', url);
  }
}

async function showView(name, options = {}) {
  if (!await guardNewsNavigation(name, options)) return;
  const newsWorkspace = isNewsEditorView(name);
  if (name === 'matches' && options.history === 'none') readTournamentRoute();
  const adminViews = ['dashboard', 'sports', 'students', 'teams', 'matches', 'official', 'news', 'users'];
  const requestedView = name;
  const permissionView = newsWorkspace ? 'news' : requestedView;
  let navigationView = newsWorkspace ? 'news' : name;
  // The athlete table lives inside the tournament workflow (step 5). Keep the
  // legacy students route useful by opening the athlete management section instead of the
  // now-empty shell view left behind after the sections are re-parented.
  if (name === 'students' && $('#students-table')?.closest('#view-matches')) {
    name = 'matches';
    navigationView = 'students';
    app.workflow.step = 5;
    tournamentUi.page = 'manage';
    tournamentUi.section = 'athletes';
  }
  if (adminViews.includes(permissionView) && (!app.user || app.user.role === 'guest')) {
    $('#login-dialog').showModal();
    return;
  }
  if (adminViews.includes(permissionView) && !app.allowedViews.includes(permissionView) && !['teams'].includes(permissionView)) {
    toast('ไม่มีสิทธิ์ใช้งานส่วนนี้', true);
    return;
  }
  if (name !== 'official') mountOfficialMatchCenter(false);
  $$('.tab').forEach(button => button.classList.toggle('active', button.dataset.viewButton === navigationView));
  const activePublicTab = document.querySelector(`body:not(.admin-mode) .tabs .tab[data-view-button="${navigationView}"]`);
  if (activePublicTab && innerWidth <= 600) {
    const navigation = activePublicTab.parentElement;
    navigation.scrollTo({ left: activePublicTab.offsetLeft - (navigation.clientWidth - activePublicTab.offsetWidth) / 2, behavior: 'smooth' });
  }
  $$('.view').forEach(view => view.hidden = true);
  const target = $(`#view-${name}`);
  if (target) {
    target.hidden = false;
  }
  app.currentView = name;
  syncViewRoute(newsWorkspace ? name : navigationView, options.history || 'replace');
  if (name !== 'public-news-detail') document.title = baseDocumentTitle;
  $('#view-public-home-extra').hidden = name !== 'public';
  updateWorkspaceView(newsWorkspace ? 'news' : name);
  syncNavigationMode();
  window.scrollTo({ top: 0, behavior: 'instant' });

  try {
    if (name === 'public') await loadPublicHome();
    if (name === 'public-sports') renderPublicSportsPage();
    if (name === 'public-athletes') await loadPublicAthletes();
    if (name === 'public-schedule') await loadPublicSchedule($('#schedule-tournament-filter').value || '');
    if (name === 'public-results') await loadPublicResults($('#results-tournament-filter').value || '');
    if (name === 'public-standings') await loadPublicStandingsPage($('#public-standings-tournament-filter').value || '');
    if (name === 'public-bracket') await loadPublicBracket($('#bracket-tournament-filter').value || '', $('#bracket-sport-filter').value || '');
    if (name === 'public-news') await renderPublicNewsPage();
    if (name === 'public-news-detail') await renderPublicNewsDetail();
    if (name === 'dashboard') await loadDashboard();
    if (name === 'sports') await loadSportsAdmin();
    if (name === 'students') await loadStudentsAdmin();
    if (name === 'teams') await loadTeamsAdmin();
    if (name === 'matches') {
      await loadMatchesAdmin();
      await loadTeamsAdmin();
      if (['super_admin', 'admin', 'sport_head'].includes(activeRole())) await loadStudentsAdmin();
      else renderTable('#students-table', [['notice', 'ข้อมูล', () => 'สิทธิ์ของคุณดูแลขั้นตอนอื่นในทัวร์นาเมนต์']], [{ notice: '' }]);
      renderTournamentPage();
      syncTournamentRoute('replace');
    }
    if (name === 'official') await loadOfficialCenter();
    if (name === 'news') await loadNewsAdmin();
    if (newsWorkspace) {
      const ready = await loadNewsEditor(options);
      if (ready === null) return;
      if (!ready) {
        $('.view').forEach(view => view.hidden = true);
        $('#view-news-editor').hidden = false;
        app.currentView = 'news-editor';
        syncViewRoute('news-editor', 'replace');
      } else if (name === 'news-preview') await showNewsPreview();
      else if (name === 'news-banner') renderBanner();
    }
    if (name === 'users') await loadUsersAdmin();
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
  syncPublicHomeMatchFilters(data);
  renderPublicHomeMatches(data.schedule, data.results);
  renderNewsCards('#public-news', data.news.slice(0, 3));
  renderNewsCards('#public-hall', data.hallOfFame);
  app.sports = data.sports;
  app.tournaments = data.tournaments;
  syncPublicFilters();
  setOptions($('#home-tournament-filter'), app.tournaments, row => row.id, row => tournamentDisplayName(row.name), 'ทุกทัวร์นาเมนท์');
  renderPublicHomeMatches(data.schedule, data.results);
  startPublicLiveUpdates();
}

function startPublicLiveUpdates() {
  if (app.publicLiveTimer) return;
  app.publicLiveTimer = window.setInterval(async () => {
    if (app.currentView !== 'public') return;
    try {
      const data = await request('public.home', {}, { silent: true });
      app.publicData = data;
      renderPublicHomeMatches(data.schedule, data.results);
      $('#m-schedule').textContent = data.summary.scheduledMatches;
      $('#m-results').textContent = data.summary.completedMatches;
    } catch (error) {
      console.warn('Live public scoreboard refresh failed', error);
    }
  }, 30000);
}

async function filterPublicHome(tournamentId) {
  const schedule = await request('public.schedule', { tournament_id: tournamentId });
  renderMatchCards('#public-schedule', schedule);
}

function syncPublicFilters() {
  setOptions($('#home-tournament-filter'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ทุกทัวร์นาเมนท์');
  setOptions($('#schedule-tournament-filter'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ทุกทัวร์นาเมนท์');
  setOptions($('#results-tournament-filter'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ทุกทัวร์นาเมนท์');
  setOptions($('#public-standings-tournament-filter'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'เลือกรายการแข่งขัน');
  setOptions($('#bracket-tournament-filter'), app.tournaments, row => row.id, row => row.name, 'เลือกทัวร์นาเมนต์');
  syncBracketSportOptions($('#bracket-tournament-filter').value);
  setOptions($('#news-tournament-filter'), app.tournaments, row => row.id, row => row.name, 'ข่าวทุกทัวร์นาเมนต์');
}

function syncBracketSportOptions(tournamentId = '') {
  const select = $('#bracket-sport-filter');
  const tournament = (app.tournaments || []).find(row => String(row.id) === String(tournamentId));
  const sportIds = new Set((tournament?.sport_ids || (tournament?.sport_id ? [tournament.sport_id] : [])).map(String));
  const sports = (app.sports || []).filter(row => sportIds.has(String(row.id)));
  setOptions(select, sports, row => row.id, row => row.name, 'เลือกกีฬาที่ต้องการดู');
  select.disabled = !tournamentId || !sports.length;
}

function syncPublicHomeMatchFilters(data) {
  const tournament = $('#home-tournament-filter');
  const date = $('#home-date-filter');
  const department = $('#home-department-filter');
  if (!tournament || !date || !department) return;
  setOptions(tournament, data.tournaments || [], row => row.id, row => tournamentDisplayName(row.name), 'ทุกทัวร์นาเมนท์');
  const teams = new Map();
  [...(data.schedule || []), ...(data.results || [])].forEach(row => {
    [[row.team1_id, row.team1_name], [row.team2_id, row.team2_name]].forEach(([id, name]) => {
      if (id) teams.set(String(id), name || id);
    });
  });
  department.innerHTML = `<option value="">ทุกทีม</option>${[...teams.entries()].sort((a, b) => String(a[1]).localeCompare(String(b[1]), 'th')).map(([id, name]) => `<option value="${escapeAttr(id)}">${escapeHtml(name)}</option>`).join('')}`;
  if (!date.dataset.bound) {
    [tournament, date, department].forEach(input => input.addEventListener('change', () => renderPublicHomeMatches(app.publicData.schedule, app.publicData.results)));
    date.dataset.bound = 'true';
  }
}

function tournamentDisplayName(name) {
  const cleaned = String(name || '').replace(/\s*[-–—]\s*ทุกกีฬา(?:\s*\([^)]*\))?\s*$/u, '').trim();
  return cleaned || String(name || 'ไม่ระบุทัวร์นาเมนท์');
}

function bindPublicCalendar() {
  const input = $('#home-date-filter');
  const trigger = $('#home-date-trigger');
  const popover = $('#home-calendar-popover');
  if (!input || !trigger || !popover || trigger.dataset.bound) return;
  trigger.dataset.bound = 'true';
  const today = new Date();
  const todayValue = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  if (!input.value) input.value = todayValue;
  app.homeCalendarMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  trigger.textContent = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(today);
  trigger.addEventListener('click', () => {
    popover.hidden = !popover.hidden;
    trigger.setAttribute('aria-expanded', String(!popover.hidden));
    if (!popover.hidden) renderPublicCalendar();
  });
  document.addEventListener('click', event => {
    if (!event.target.closest('#home-calendar-picker')) {
      popover.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }
  });
  renderPublicCalendar();
}

function renderPublicCalendar() {
  const input = $('#home-date-filter');
  const popover = $('#home-calendar-popover');
  const trigger = $('#home-date-trigger');
  if (!input || !popover || !trigger) return;
  const month = app.homeCalendarMonth || new Date();
  const year = month.getFullYear();
  const monthIndex = month.getMonth();
  const monthName = new Intl.DateTimeFormat('en-US', { month: 'short', year: 'numeric' }).format(month);
  const firstDay = (new Date(year, monthIndex, 1).getDay() + 6) % 7;
  const days = new Date(year, monthIndex + 1, 0).getDate();
  const selected = input.value || '';
  const cells = [];
  for (let i = 0; i < firstDay; i += 1) cells.push('<span class="calendar-day muted-day"></span>');
  for (let day = 1; day <= days; day += 1) {
    const value = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    cells.push(`<button type="button" class="calendar-day${value === selected ? ' selected' : ''}" data-calendar-date="${value}">${day}</button>`);
  }
  const monthOptions = Array.from({ length: 12 }, (_, index) => `<option value="${index}"${index === monthIndex ? ' selected' : ''}>${new Intl.DateTimeFormat('en-US', { month: 'short' }).format(new Date(2000, index, 1))}</option>`).join('');
  const yearOptions = Array.from({ length: 11 }, (_, index) => year - 5 + index).map(value => `<option value="${value}"${value === year ? ' selected' : ''}>${value}</option>`).join('');
  popover.innerHTML = `<div class="calendar-head"><div class="calendar-month-select"><select data-calendar-month aria-label="เลือกเดือน">${monthOptions}</select><select data-calendar-year aria-label="เลือกปี">${yearOptions}</select></div><div><button type="button" data-calendar-nav="prev" aria-label="เดือนก่อนหน้า">‹</button><button type="button" data-calendar-nav="next" aria-label="เดือนถัดไป">›</button></div></div><div class="calendar-weekdays">${['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'].map(day => `<span>${day}</span>`).join('')}</div><div class="calendar-grid">${cells.join('')}</div>`;
  popover.querySelector('[data-calendar-month]').addEventListener('change', event => {
    app.homeCalendarMonth = new Date(year, Number(event.target.value), 1);
    renderPublicCalendar();
  });
  popover.querySelector('[data-calendar-year]').addEventListener('change', event => {
    app.homeCalendarMonth = new Date(Number(event.target.value), monthIndex, 1);
    renderPublicCalendar();
  });
  popover.querySelectorAll('[data-calendar-nav]').forEach(button => button.addEventListener('click', () => {
    app.homeCalendarMonth = new Date(year, monthIndex + (button.dataset.calendarNav === 'next' ? 1 : -1), 1);
    renderPublicCalendar();
  }));
  popover.querySelectorAll('[data-calendar-date]').forEach(button => button.addEventListener('click', () => {
    input.value = button.dataset.calendarDate;
    trigger.textContent = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${input.value}T00:00:00`));
    popover.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    renderPublicHomeMatches(app.publicData.schedule, app.publicData.results);
  }));
}

function renderPublicHomeMatches(scheduleRows, resultRows) {
  bindPublicCalendar();
  const tournamentId = $('#home-tournament-filter')?.value || '';
  const date = $('#home-date-filter')?.value || '';
  const teamId = $('#home-department-filter')?.value || '';
  const baseFilter = row => (!tournamentId || String(row.tournament_id) === String(tournamentId))
    && (!teamId || [row.team1_id, row.team2_id].map(String).includes(String(teamId)));
  const schedule = (scheduleRows || []).filter(row => baseFilter(row) && (!date || String(row.scheduled_at || '').slice(0, 10) >= date));
  const results = (resultRows || []).filter(baseFilter);
  renderPublicMatchList('#public-schedule', schedule, false);
  renderPublicMatchList('#public-home-results', results, true);
  $('#public-schedule-count').textContent = `${schedule.length} คู่`;
  $('#public-results-count').textContent = `${results.length} คู่`;
}

function renderPublicMatchList(selector, rows, completed) {
  const target = $(selector);
  if (!target) return;
  target.innerHTML = rows.length ? rows.map(row => completed ? renderCompletedMatch(row) : renderUpcomingMatch(row)).join('')
    : emptyState(completed ? 'ยังไม่มีผลการแข่งขันตามตัวกรอง' : 'ยังไม่มีคู่แข่งขันตามตัวกรอง');
  refreshIcons(target);
}

function renderCompletedMatch(row) {
  if (row.race_entries?.length) return renderRaceResultCard(row);
  const date = formatUpcomingMatchDate(row.confirmed_at || row.scheduled_at);
  const sportAsset = row.sport_logo_url || sportIconAsset(row.sport_icon, row.sport_name);
  const firstScore = Number(row.team1_score ?? 0);
  const secondScore = Number(row.team2_score ?? 0);
  const firstWon = firstScore > secondScore;
  const secondWon = secondScore > firstScore;
  const winner = firstWon ? row.team1_name : secondWon ? row.team2_name : 'ผลเสมอ';
  return `<article class="public-match-row is-completed">
    <img class="result-sport-art" src="${escapeAttr(sportAsset)}" alt="" aria-hidden="true" onerror="this.remove()">
    <div class="result-match-head">
      <div><span class="result-label">FULL TIME</span><strong>${escapeHtml(tournamentDisplayName(row.tournament_name))}</strong></div>
      <span class="result-status">${icon('circle-check')} จบการแข่งขัน</span>
    </div>
    <div class="result-sport-name">${escapeHtml(row.sport_name || 'ไม่ระบุกีฬา')} · ${escapeHtml(row.round_name || 'รอบการแข่งขัน')}</div>
    <div class="result-versus">
      ${upcomingTeam(row, 1, firstWon)}
      <div class="result-score"><strong>${escapeHtml(String(firstScore))}</strong><span>:</span><strong>${escapeHtml(String(secondScore))}</strong></div>
      ${upcomingTeam(row, 2, secondWon)}
    </div>
    <div class="result-match-foot">
      <span>${icon(firstWon || secondWon ? 'trophy' : 'handshake')} ${escapeHtml(firstWon || secondWon ? `ผู้ชนะ ${winner}` : winner)}</span>
      <span>${icon('calendar-check')} ${escapeHtml(`${date.day} ${date.month} · ${date.time}`)}</span>
    </div>
  </article>`;
}

function renderUpcomingMatch(row) {
  if (!row.team1_id && !row.team2_id) return renderRaceScheduleCard(row);
  const date = formatUpcomingMatchDate(row.scheduled_at);
  const sportAsset = row.sport_logo_url || sportIconAsset(row.sport_icon, row.sport_name);
  return `<article class="public-match-row is-upcoming">
    <img class="upcoming-sport-art" src="${escapeAttr(sportAsset)}" alt="" aria-hidden="true" onerror="this.remove()">
    <div class="upcoming-match-head">
      <div><span class="upcoming-label">NEXT MATCH</span><strong>${escapeHtml(tournamentDisplayName(row.tournament_name))}</strong></div>
      <div class="upcoming-date"><b>${escapeHtml(date.day)}</b><span>${escapeHtml(date.month)}</span></div>
    </div>
    <div class="upcoming-sport-name">${escapeHtml(row.sport_name || 'ไม่ระบุกีฬา')} · ${escapeHtml(row.round_name || 'รอบการแข่งขัน')}</div>
    <div class="upcoming-versus">
      ${upcomingTeam(row, 1)}
      <span class="upcoming-vs">VS</span>
      ${upcomingTeam(row, 2)}
    </div>
    <div class="upcoming-match-foot">
      <span>${icon('clock-3')}${escapeHtml(date.time)}</span>
      <span>${icon('map-pin')}${escapeHtml(row.venue || 'รอประกาศสนาม')}</span>
    </div>
  </article>`;
}

function upcomingTeam(row, side, winner = false) {
  const logo = row[`team${side}_logo_url`] || row[`team${side}_department_logo_url`] || '';
  const name = row[`team${side}_name`] || `ทีม ${side}`;
  const initial = escapeHtml(String(name).trim().charAt(0) || String(side));
  const mark = `<span>${initial}</span>${logo ? `<img src="${escapeAttr(logo)}" alt="" onerror="this.remove()">` : ''}`;
  return `<div class="upcoming-team${winner ? ' is-winner' : ''}"><div class="upcoming-team-mark">${mark}</div><strong>${escapeHtml(name)}</strong></div>`;
}

function formatUpcomingMatchDate(value) {
  const date = value ? new Date(String(value).replace(' ', 'T')) : null;
  if (!date || Number.isNaN(date.getTime())) return { day: '--', month: 'TBA', time: 'รอประกาศเวลา' };
  return {
    day: new Intl.DateTimeFormat('th-TH', { day: '2-digit' }).format(date),
    month: new Intl.DateTimeFormat('th-TH', { month: 'short' }).format(date),
    time: new Intl.DateTimeFormat('th-TH', { hour: '2-digit', minute: '2-digit' }).format(date) + ' น.',
  };
}

function publicScoreboardTeam(row, side, completed) {
  const logo = row[`team${side}_logo_url`] || row[`team${side}_department_logo_url`] || '';
  const name = row[`team${side}_name`] || `ทีม ${side}`;
  const score = completed ? String(row[`team${side}_score`] ?? 0) : '-';
  return `<div class="scoreboard-team${logo ? ' has-logo' : ''}">
    ${logo ? `<img src="${escapeAttr(logo)}" alt="" onerror="const team=this.parentElement;this.remove();team.classList.remove('has-logo')">` : ''}
    <strong>${escapeHtml(name)}</strong><b>${escapeHtml(score)}</b>
  </div>`;
}

function renderPublicSportsPage() {
  const genderSelect = $('#public-sport-gender-filter');
  const formatSelect = $('#public-sport-format-filter');
  if (!genderSelect.dataset.bound) {
    [genderSelect, formatSelect].forEach(select => select.addEventListener('change', renderPublicSportsPage));
    genderSelect.dataset.bound = 'true';
  }
  const gender = genderSelect.value;
  const format = formatSelect.value;
  const rows = ((app.publicData && app.publicData.sports) || app.sports || []).filter(row =>
    (!gender || row.gender === gender) && (!format || sportFormatKey(row) === format));
  renderTable('#public-sports-table', [
    ['logo_url', 'โลโก้', (_, row) => `<img class="sport-table-logo" src="${escapeAttr(row.logo_url || sportIconAsset(row.icon, row.name))}" alt="ไอคอนกีฬา ${escapeAttr(row.name || 'กีฬา')}" onerror="this.src='${escapeAttr(sportIconAsset(row.icon, row.name))}'">`, { html: true }],
    ['name', 'กีฬา'],
    ['type', 'ประเภท'],
    ['gender', 'เพศ'],
    ['max_players', 'ผู้เล่น'],
    ['reserve_players', 'สำรอง'],
    ['rules', 'คำอธิบายย่อ'],
  ], rows);
}

function sportFormatKey(row) {
  if (Number(row.sport_max_players ?? row.max_players ?? 1) === 2) return 'double';
  return String(row.sport_type ?? row.type ?? '') === 'individual' ? 'single' : 'team';
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
  return String(board.tournament_name || 'ไม่ระบุทัวร์นาเมนต์');
}

function syncAthleteFacultyOptions() {
  const select = $('#athlete-faculty-filter');
  const current = select.value;
  const rows = [...new Map((app.athleteBoards || []).map(board => [String(board.tournament_id), { id: String(board.tournament_id), name: athleteFacultyName(board) }])).values()]
    .sort((a, b) => a.name.localeCompare(b.name, 'th'));
  setOptions(select, rows, row => row.id, row => row.name, 'เลือกทัวร์นาเมนต์');
  if (rows.some(row => row.id === current)) select.value = current;
}

function syncAthleteDepartmentOptions() {
  const tournamentId = $('#athlete-faculty-filter').value;
  const select = $('#athlete-department-filter');
  const current = select.value;
  const seen = new Map();
  (app.athleteBoards || []).filter(board => String(board.tournament_id) === tournamentId).forEach(board => {
    seen.set(String(board.sport_id || ''), board.sport_name || 'ไม่ระบุกีฬา');
  });
  const rows = [...seen].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'th'));
  setOptions(select, rows, row => row.id, row => row.name, 'เลือกกีฬา');
  select.disabled = !tournamentId;
  if (rows.some(row => row.id === current)) select.value = current;
}

function syncAthleteSportOptions() {
  const tournamentId = $('#athlete-faculty-filter').value;
  const sportId = $('#athlete-department-filter').value;
  const select = $('#athlete-sport-filter');
  const current = select.value;
  const seen = new Map();
  (app.athleteBoards || []).filter(board => String(board.tournament_id) === tournamentId
    && String(board.sport_id || '') === sportId).forEach(board => {
    seen.set(String(board.team_id || ''), board.team_name || 'ไม่ระบุทีม');
  });
  const rows = [...seen].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name, 'th'));
  setOptions(select, rows, row => row.id, row => row.name, 'เลือกทีม');
  select.disabled = !sportId;
  if (rows.some(row => row.id === current)) select.value = current;
}

function renderAthleteBoards() {
  const tournamentId = $('#athlete-faculty-filter').value;
  const sportId = $('#athlete-department-filter').value;
  const teamId = $('#athlete-sport-filter').value;
  const ready = Boolean(tournamentId && sportId && teamId);
  const currentStep = teamId ? 4 : sportId ? 3 : tournamentId ? 2 : 1;
  $$('[data-athlete-flow-step]').forEach(element => {
    const step = Number(element.dataset.athleteFlowStep);
    element.classList.toggle('complete', step < currentStep || (ready && step === 4));
    element.classList.toggle('active', step === currentStep);
  });
  if (window.innerWidth <= 900) {
    $(`[data-athlete-flow-step="${currentStep}"]`)?.scrollIntoView({ behavior: 'auto', block: 'nearest', inline: 'center' });
  }
  $('#athlete-department-filter').disabled = !tournamentId;
  $('#athlete-sport-filter').disabled = !sportId;
  $('#athlete-results').hidden = !ready;
  const notice = !tournamentId
    ? 'เริ่มจากเลือกทัวร์นาเมนต์'
    : !sportId
      ? 'เลือกกีฬาที่จัดในทัวร์นาเมนต์นี้'
      : !teamId
        ? 'เลือกทีมเพื่อดูรายชื่อนักกีฬา'
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
    if (String(row.tournament_id) !== tournamentId || String(row.sport_id || '') !== sportId || String(row.team_id) !== teamId) return false;
    if (!query) return true;
    return [row.team_name, row.department_name, row.sport_name, ...(row.players || []).flatMap(player => [player.full_name, player.student_code, player.field_position, player.role, player.shirt_number])]
      .join(' ').toLowerCase().includes(query);
  }).map(board => ({
    ...board,
    players: (board.players || []).filter(player => !query || [player.full_name, player.student_code, player.field_position, player.role, player.shirt_number, board.team_name].join(' ').toLowerCase().includes(query)),
  }));
  const target = $('#athlete-board-list');
  const playerCount = rows.reduce((total, board) => total + board.players.filter(player => String(player.role || '').toLowerCase() !== 'coach').length, 0);
  const coachCount = rows.reduce((total, board) => total + board.players.filter(player => String(player.role || '').toLowerCase() === 'coach').length, 0);
  $('#athlete-total-count').textContent = `${playerCount} นักกีฬา${coachCount ? ` · ${coachCount} โค้ช` : ''}`;
  const sportName = rows[0]?.sport_name || $('#athlete-department-filter').selectedOptions[0]?.textContent || 'กีฬา';
  const teamName = rows[0]?.team_name || $('#athlete-sport-filter').selectedOptions[0]?.textContent || 'ทีม';
  $('#athlete-results-title').textContent = `${sportName} · ${teamName}`;
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
        <strong>${board.players.filter(player => String(player.role || '').toLowerCase() !== 'coach').length} นักกีฬา${board.players.some(player => String(player.role || '').toLowerCase() === 'coach') ? ` · ${board.players.filter(player => String(player.role || '').toLowerCase() === 'coach').length} โค้ช` : ''}</strong>
      </header>
      <div class="athlete-zone-list">${athletePlayerZones(board).map(zone => `<section class="athlete-position-zone">
        <header><div><span>${zone.kind === 'coach' ? 'ทีมงาน' : 'ตำแหน่ง'}</span><h5>${escapeHtml(zone.label)}</h5></div><strong>${zone.players.length} คน</strong></header>
        <ol class="athlete-name-list">${zone.players.map(player => `
          <li class="athlete-name-row">
            ${player.shirt_number && zone.kind !== 'coach' ? `<span class="athlete-shirt-number">${escapeHtml(player.shirt_number)}</span>` : ''}
            <strong>${escapeHtml(player.full_name || '-')}</strong>
            ${zone.kind !== 'coach' && ['captain', 'substitute'].includes(String(player.role || '').toLowerCase()) ? `<small>${escapeHtml(athleteRoleLabel(player.role))}</small>` : ''}
          </li>
        `).join('')}</ol>
      </section>`).join('') || '<p class="athlete-empty-team">ทีมนี้ยังไม่มีรายชื่อสมาชิก</p>'}</div>
    </section>
  `).join('');
  refreshIcons(target);
}

function athleteRoleLabel(role) {
  return ({ captain: 'กัปตัน', coach: 'โค้ช', substitute: 'ตัวสำรอง', player: 'ผู้เล่น' })[String(role || '').toLowerCase()] || String(role || 'ผู้เล่น');
}

function athletePlayerZones(board) {
  if (String(board.sport_type || '') !== 'team') {
    const players = sortAthleteNames((board.players || []).filter(player => String(player.role || '').toLowerCase() !== 'coach'));
    return players.length ? [{ label: 'รายชื่อนักกีฬา', kind: 'player', players }] : [];
  }
  const groups = new Map();
  (board.players || []).forEach(player => {
    const role = String(player.role || 'player').toLowerCase();
    const kind = role === 'coach' ? 'coach' : 'player';
    const label = kind === 'coach' ? 'โค้ช' : (String(player.field_position || '').trim() || 'ไม่ระบุตำแหน่ง');
    const key = `${kind}:${label}`;
    if (!groups.has(key)) groups.set(key, { label, kind, players: [] });
    groups.get(key).players.push(player);
  });
  return [...groups.values()]
    .sort((a, b) => (a.kind === 'coach' ? -1 : b.kind === 'coach' ? 1 : a.label.localeCompare(b.label, 'th')))
    .map(group => ({ ...group, players: sortAthleteNames(group.players) }));
}

function sortAthleteNames(players) {
  return [...players].sort((a, b) => {
    const shirtA = Number.parseInt(a.shirt_number, 10);
    const shirtB = Number.parseInt(b.shirt_number, 10);
    if (Number.isFinite(shirtA) && Number.isFinite(shirtB) && shirtA !== shirtB) return shirtA - shirtB;
    if (Number.isFinite(shirtA) !== Number.isFinite(shirtB)) return Number.isFinite(shirtA) ? -1 : 1;
    return String(a.full_name || '').localeCompare(String(b.full_name || ''), 'th');
  });
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

async function loadPublicStandingsPage(tournamentId = '') {
  if (!tournamentId) {
    const filter = $('#public-standings-tournament-filter');
    tournamentId = [...filter.options].find(option => option.value)?.value || '';
    filter.value = tournamentId;
  }
  const standings = await request('public.standings', { tournament_id: tournamentId });
  renderTable('#public-standings-page-table', [
    ['sport_name', 'กีฬา'], ['rank_number', 'อันดับ'], ['team_name', 'ทีม'],
    ['played', 'แข่ง'], ['won', 'ชนะ'], ['drawn', 'เสมอ'], ['lost', 'แพ้'],
    ['score_difference', '+/-'], ['points', 'คะแนน'],
  ], standings);
}

async function loadPublicBracket(tournamentId = '', sportId = '') {
  const target = $('#public-bracket-rounds');
  const stageHead = $('#bracket-stage-head');
  target.classList.remove('single-round', 'group-list-view');
  if (!tournamentId) {
    target.innerHTML = emptyState('เลือกทัวร์นาเมนต์เพื่อดูสายการแข่งขัน');
    stageHead.hidden = true;
    return;
  }
  if (!sportId) {
    target.innerHTML = emptyState('เลือกกีฬาที่ต้องการดูก่อน ระบบจะแสดงสายการแข่งขันตามลำดับรอบ');
    stageHead.hidden = true;
    return;
  }
  const tournament = (app.tournaments || []).find(row => String(row.id) === String(tournamentId));
  const sport = (app.sports || []).find(row => String(row.id) === String(sportId));
  const rounds = await request('public.bracket', { tournament_id: tournamentId, sport_id: sportId });
  target.classList.toggle('single-round', rounds.length === 1);
  target.classList.toggle('group-list-view', rounds.length > 0 && rounds.every(round => bracketRoundIsTeamList(round, tournament)));
  $('#bracket-stage-sport').textContent = sport?.name || 'กีฬา';
  $('#bracket-stage-title').textContent = tournament?.name || 'สายการแข่งขัน';
  stageHead.hidden = false;
  target.innerHTML = rounds.length ? rounds.map(round => renderBracketRound(round, tournament)).join('') : emptyState('ยังไม่มีสายการแข่งขันสำหรับกีฬานี้');
}

function matchStatusClass(status) {
  return ['scheduled', 'live', 'completed', 'cancelled', 'postponed'].includes(status) ? status : 'scheduled';
}

function renderBracketRound(round, tournament) {
  const matches = round.matches || [];
  const isGroupList = bracketRoundIsTeamList(round, tournament);
  if (isGroupList) {
    const teams = bracketRoundTeams(matches);
    return `<section class="bracket-group team-list">
      <header><h3>${escapeHtml(round.name)}</h3><span>${teams.length} ทีม</span></header>
      <ol class="bracket-group-team-list">${teams.map(team => `
        <li>${bracketTeamMark(team.name, team.logo)}<strong>${escapeHtml(team.name)}</strong></li>
      `).join('')}</ol>
    </section>`;
  }
  return `<section class="bracket-group">
    <header><h3>${escapeHtml(round.name)}</h3><span>${matches.length} คู่</span></header>
    <div class="bracket-group-matches">${matches.map(match => `
      <article class="bracket-match">
        <div class="bracket-match-meta"><span>คู่ที่ ${escapeHtml(String(match.match_number || '-'))}</span><time>${escapeHtml(formatBracketDate(match.scheduled_at))}</time></div>
        ${bracketTeamRow(match, 1)}
        ${bracketTeamRow(match, 2)}
        <small class="bracket-match-status status-${matchStatusClass(match.status)}">${escapeHtml(match.status_label || '')}</small>
      </article>
    `).join('')}</div>
  </section>`;
}

function bracketRoundIsTeamList(round, tournament) {
  return String(round.name || '').startsWith('กลุ่ม') || (tournament?.format === 'round_robin' && round.name === 'พบกันหมด');
}

function bracketRoundTeams(matches) {
  const teams = new Map();
  matches.forEach(match => [1, 2].forEach(side => {
    const id = match[`team${side}_id`];
    if (!id || teams.has(String(id))) return;
    const name = match[`team${side}_name`] || `ทีม ${side}`;
    teams.set(String(id), {
      name,
      logo: match[`team${side}_logo_url`] || match[`team${side}_department_logo_url`] || '',
      initial: String(name).trim().charAt(0).toUpperCase() || '?',
    });
  }));
  return [...teams.values()].sort((a, b) => a.name.localeCompare(b.name, 'th'));
}

function bracketTeamRow(match, side) {
  const teamId = match[`team${side}_id`];
  const name = teamId ? (match[`team${side}_name`] || `ทีม ${side}`) : 'รอทีม';
  const logo = match[`team${side}_logo_url`] || match[`team${side}_department_logo_url`] || '';
  const score = match.status === 'completed' ? String(match[`team${side}_score`] ?? 0) : '-';
  const otherScore = Number(match[`team${side === 1 ? 2 : 1}_score`] ?? 0);
  const isWinner = match.status === 'completed' && Number(match[`team${side}_score`] ?? 0) > otherScore;
  return `<div class="bracket-team-row team-${side}${isWinner ? ' winner' : ''}${teamId ? '' : ' pending'}">
    ${bracketTeamMark(name, logo)}
    <strong>${escapeHtml(name)}</strong>
    <b>${escapeHtml(score)}</b>
  </div>`;
}

function bracketTeamMark(name, logo = '') {
  const initial = String(name || '').trim().charAt(0).toUpperCase() || '?';
  return `<span class="bracket-team-mark"><span aria-hidden="true">${escapeHtml(initial)}</span>${logo ? `<img src="${escapeAttr(logo)}" alt="" onerror="this.remove()">` : ''}</span>`;
}

function formatBracketDate(value) {
  if (!value) return 'ยังไม่กำหนดเวลา';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

async function loadAdminRefs() {
  const requests = [
    request('sports.list', {}, { silent: true }).then(rows => app.sports = rows).catch(() => {}),
    request('tournaments.list', {}, { silent: true }).then(rows => app.tournaments = rows).catch(() => {}),
    request('departments.list', {}, { silent: true }).then(rows => app.departments = rows).catch(() => {}),
    request('teams.list', { tournament_id: '' }, { silent: true }).then(rows => app.teams = rows).catch(() => {}),
    request('matches.list', { tournament_id: '' }, { silent: true }).then(rows => app.matches = rows).catch(() => {}),
  ];
  await Promise.all(requests);
  syncAdminOptions();
}

function syncAdminOptions() {
  $$('.sport-select').forEach(select => setOptions(select, app.sports, row => row.id, row => row.name, 'เลือกกีฬา'));
  syncTournamentSportOptions();
  $$('.tournament-select').forEach(select => setOptions(select, app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, select.classList.contains('optional') ? 'ทั้งหมด' : 'เลือกรายการแข่ง'));
  $$('.department-select').forEach(select => setOptions(select, app.departments, row => row.code, row => `${row.code} · ${row.name}`, select.classList.contains('optional') ? 'ไม่ระบุ' : 'เลือกสาขา'));
  setOptions($('#member-team-select'), app.teams, row => row.id, row => `${row.name} · ${row.tournament_name || '-'}`, 'เลือกทีม');
  setOptions($('#match-tournament-select'), app.tournaments, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'เลือกรายการแข่ง');
  setOptions($('#standings-tournament-filter'), app.tournaments, row => row.id, row => row.name, 'เลือกรายการแข่งขัน');
  setOptions($('#news-team-link'), app.teams, row => row.id, row => `${row.name} (${row.sport_name || '-'})`, 'ไม่ระบุทีม');
  setOptions($('#news-match-link'), app.matches, row => row.id, row => row.display_label, 'ไม่ระบุแมตช์');
  if ($('#invite-role-select') && $('#user-role-select')?.options.length) {
    $('#invite-role-select').innerHTML = $('#user-role-select').innerHTML;
  }
  syncMatchTeamOptions();
  syncTeamSportOptions();
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

function syncScoringConfigFields(applyDefaults = false) {
  const type = $('#scoring-type')?.value || 'SCORE';
  syncLegacyModeOptions($('#scoring-type'));
  $$('[data-config-for]').forEach(field => {
    field.hidden = !field.dataset.configFor.split(/\s+/).includes(type);
  });
  if (applyDefaults) {
    const form = $('#sport-rule-form');
    form.elements.include_in_standings.checked = ['SCORE', 'ROUND', 'PERIOD_SCORE', 'SET', 'END_SCORE', 'DECISION', 'COMBAT', 'MAP'].includes(type);
    form.elements.scoring_mode.value = ({ SCORE: 'points', ROUND: 'points', PERIOD_SCORE: 'points', SET: 'sets', END_SCORE: 'points', TIME: 'time', RACE_POSITION: 'rank' })[type] || 'points';
    form.elements.win_by.value = ['TIME', 'RACE_POSITION', 'STROKE', 'LOWEST'].includes(type) ? 'lowest' : type === 'SET' ? 'sets' : 'highest';
    if (['ROUND', 'END_SCORE'].includes(type)) form.elements.target_score.value = type === 'ROUND' ? '0' : '13';
  }
}

function syncLegacyModeOptions(select) {
  const group = select?.querySelector('[data-legacy-modes]');
  if (!group) return;
  const legacy = ['PERIOD_SCORE', 'END_SCORE', 'POINT_RANKING', 'COMBAT', 'RACE_POSITION', 'STROKE', 'MAP'];
  group.hidden = !legacy.includes(select.value);
}

function detectScoringType(match, rule) {
  const configured = String(rule?.scoring_type || '').toUpperCase();
  if (configured && (configured !== 'SCORE' || (rule?.created_by && rule.created_by !== 'migration'))) return configured;
  const text = `${match?.sport_icon || ''} ${match?.sport_name || ''}`.toLocaleLowerCase('th');
  const families = [
    ['HEIGHT', ['กระโดดสูง', 'ค้ำถ่อ', 'high jump', 'pole vault']],
    ['DISTANCE', ['กระโดดไกล', 'เขย่ง', 'ทุ่มน้ำหนัก', 'ขว้างจักร', 'ขว้างหอก', 'long jump', 'shot put', 'discus', 'javelin']],
    ['POINT_RANKING', ['ยิมนาสติก', 'เชียร์ลีดดิ้ง', 'เต้น', 'gymnastic', 'cheer', 'dance']],
    ['COMBAT', ['มวย', 'เทควันโด', 'คาราเต้', 'boxing', 'muay', 'taekwondo', 'karate']],
    ['STROKE', ['กอล์ฟ', 'golf']],
    ['MAP', ['esport', 'e-sport', 'อีสปอร์ต', 'กีฬาอิเล็กทรอนิกส์']],
    ['END_SCORE', ['petanque', 'pétanque', 'เปตอง']],
    ['PERIOD_SCORE', ['basketball', 'บาสเกตบอล', 'american football']],
    ['SET', ['volleyball', 'badminton', 'table-tennis', 'table tennis', 'tennis', 'sepak', 'วอลเลย์บอล', 'แบดมินตัน', 'เทเบิลเทนนิส', 'ปิงปอง', 'เทนนิส', 'ตะกร้อ']],
    ['RACE_POSITION', ['running', 'swimming', 'cycling', 'วิ่ง', 'ว่ายน้ำ', 'จักรยาน', 'ผลัด', 'race']],
  ];
  return families.find(([, terms]) => terms.some(term => text.includes(term)))?.[0] || configured || 'SCORE';
}

function defaultScoringConfig(type) {
  return ({
    SCORE: { allow_draw: true, include_in_standings: true },
    PERIOD_SCORE: { periods: 4, allow_overtime: true, include_in_standings: true },
    SET: { best_of: 3, points_to_win_set: 21, win_by: 2, max_points: 30, include_in_standings: true },
    ROUND: { target_score: 0, include_in_standings: true },
    END_SCORE: { target_score: 13, include_in_standings: true },
    JUDGED: { aggregation: 'SUM', include_in_standings: false },
    TARGET: { aggregation: 'SUM', include_in_standings: false },
    COUNT: { win_by: 'HIGHEST', include_in_standings: false },
    LOWEST: { include_in_standings: false },
    DECISION: { include_in_standings: true },
    COMBINED: { win_by: 'HIGHEST', include_in_standings: false },
    POINT_RANKING: { aggregation: 'SUM', include_in_standings: false },
    MAP: { best_of: 3, include_in_standings: true },
    COMBAT: { include_in_standings: true },
  })[type] || { include_in_standings: false };
}

async function loadDashboard() {
  await loadAdminRefs();
  const data = await request('dashboard.summary');
  $('#d-active-sports').textContent = data.counts.activeSports;
  $('#d-approved-teams').textContent = data.counts.approvedTeams;
  $('#d-students').textContent = data.counts.students;
  $('#d-completed').textContent = data.counts.completedMatches;
  renderTable('#audit-table', [
    ['created_at', 'เวลา'],
    ['user_email', 'ผู้ใช้'],
    ['action', 'การดำเนินการ'],
    ['entity', 'ข้อมูล'],
    ['entity_id', 'รหัสอ้างอิง'],
  ], data.recentAudit || []);
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
    ['scoring_type', 'แบบบันทึก'],
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
    ['format', 'รูปแบบ', (_, row) => `${row.scoring_type || 'SCORE'} · ${row.period_count} ช่วง`],
    ['overtime_enabled', 'ต่อเวลา', value => value ? 'มี' : 'ไม่มี'],
    ['is_active', 'สถานะ', value => Number(value) ? 'ชุดหลัก' : 'เก็บประวัติ'],
    ['usage_count', 'ถูกนำไปใช้', value => `${Number(value || 0)} รายการ`],
    ['actions', 'จัดการ', (_, row) => rowActions([
      ...(!row.is_referenced ? [['แก้ไข', () => fillSportRule(row)]] : []),
      ['สร้างเวอร์ชันใหม่', () => cloneSportRule(row)],
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
    scoring_config: row.scoring_config_text || '',
    event_types_text: (row.event_types_data || []).map(item => `${item.code}|${item.label}|${item.points || 0}|${item.participant || 'optional'}`).join('\n'),
    custom_rules: row.custom_rules_text || '',
  });
  syncScoringConfigFields();
}

function cloneSportRule(row) {
  fillSportRule({
    ...row,
    id: '',
    name: `${row.name} (ปรับปรุง)`,
    is_active: 1,
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
  return true;
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
    button.disabled = false;
    button.title = '';
  });
  const status = $('#student-scope-status');
  if (!scope.facultyId && !scope.departmentCode && !scope.sportId) status.textContent = 'แสดงนักกีฬาทั้งหมด';
  else if (!scope.departmentCode) status.textContent = 'เลือกกลุ่มย่อยเพื่อกรองเพิ่มเติม';
  else if (!scope.sportId) status.textContent = 'เลือกกีฬาเพื่อกรองเพิ่มเติม';
  else {
    const department = app.departments.find(row => String(row.code) === scope.departmentCode);
    const sport = (app.workflow.data?.sports || app.sports).find(row => String(row.id) === scope.sportId);
    status.textContent = `${department?.name || scope.departmentCode} · ${sport?.name || 'กีฬาที่เลือก'}`;
  }
  $('#student-scope').classList.toggle('complete', Boolean(scope.facultyId || scope.departmentCode || scope.sportId));

  const teamIds = new Set(app.teams
    .filter(team => (!scope.departmentCode || String(team.department_code || '') === scope.departmentCode)
      && (!scope.sportId || String(team.sport_id || '') === scope.sportId))
    .map(team => String(team.id)));
  const rosterStudentIds = new Set(app.members
    .filter(member => teamIds.has(String(member.team_id)))
    .map(member => String(member.student_id)));
  const rows = app.students.filter(row => !scope.departmentCode || String(row.department_code || '') === scope.departmentCode);
  renderTable('#students-table', [
    ['id', 'ID'],
    ['student_code', 'รหัส'],
    ['full_name', 'ชื่อ', (_, row) => [row.prefix, row.first_name, row.last_name].filter(Boolean).join(' ')],
    ['department_name', 'สังกัด'],
    ['roster_status', 'สถานะกีฬา', (_, row) => rosterStudentIds.has(String(row.id))
      ? '<span class="status-badge success">อยู่ในทีมแล้ว</span>'
      : '<span class="status-badge">รอจัดทีม</span>', { html: true }],
    ['phone', 'โทรศัพท์'],
    ['actions', 'จัดการ', (_, row) => ['super_admin', 'admin'].includes(activeRole()) ? rowActions([
      ['แก้ไข', () => fillForm('#student-form', row)],
      ['ลบ', () => deleteRow('students.delete', row.id, loadStudentsAdmin), 'danger'],
    ]) : '-'],
  ], rows);
}

function focusMissingStudentScope() {
  const scope = studentScopeValues();
  const target = !scope.facultyId ? $('#student-scope-faculty') : !scope.departmentCode ? $('#student-scope-department') : $('#student-scope-sport');
  target?.focus();
  toast('กรุณาเลือกคณะ สาขา และกีฬาให้ครบก่อน', true);
}

function openScopedStudentEditor() {
  openEditor('student-form', { department_code: studentScopeValues().departmentCode || 'OPEN' });
}

function openStudentImport() {
  $('#students-csv')?.closest('dialog')?.showModal();
}

function scopedStudentImportRows() {
  const departmentCode = studentScopeValues().departmentCode;
  return parseCsv($('#students-csv').value).map(row => ({ ...row, department_code: row.department_code || departmentCode || 'OPEN' }));
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


async function openMatchCenter(matchId) {
  if (!matchId) return;
  const match = app.matches.find(row => String(row.id) === String(matchId));
  const rule = match ? app.sportRules.find(item => String(item.id) === String(match.rule_version_id)) : null;
  const dedicatedUrl = match ? dedicatedRecordingPageUrl(match, rule) : '';
  if (dedicatedUrl) {
    window.location.assign(dedicatedUrl);
    return;
  }
  const scoringType = detectScoringType(match, rule);
  toast(`หน้าบันทึก ${scoringType} ยังอยู่ระหว่างจัดทำ ระบบเดิมถูกยกเลิกแล้ว`, true);
}

function mountOfficialMatchCenter(active) {
  document.body.classList.toggle('official-console-active', Boolean(active));
}

async function loadOfficialCenter(force = false) {
  mountOfficialMatchCenter(true);
  if (force || !app.matches.length) await loadAdminRefs();
  if (!app.sportRules.length) {
    app.sportRules = await request('sportRules.list', { sport_id: '' }, { silent: true }).catch(() => []);
  }
  renderOfficialMatchLists('');
}

function renderOfficialMatchLists(selectedId = '') {
  const groups = {
    '#official-live-matches': app.matches.filter(row => row.status === 'live'),
    '#official-upcoming-matches': app.matches.filter(row => row.status === 'scheduled'),
    '#official-completed-matches': app.matches.filter(row => row.status === 'completed').slice(0, 5),
  };
  Object.entries(groups).forEach(([selector, rows]) => {
    const host = $(selector);
    host.innerHTML = rows.length ? rows.map(row => {
      const rule = app.sportRules.find(item => String(item.id) === String(row.rule_version_id));
      const isRace = ['TIME', 'RACE_POSITION'].includes(detectScoringType(row, rule));
      const displayParts = row.status === 'completed' && row.result_summary?.display_score
        ? String(row.result_summary.display_score).split(/\s+-\s+/) : [];
      const scoringType = detectScoringType(row, rule);
      const dedicatedUrl = dedicatedRecordingPageUrl(row, rule);
      return `
      <div class="official-match-entry">
      <button class="official-match-item ${String(row.id) === String(selectedId) ? 'selected' : ''}" type="button" data-official-match="${escapeAttr(row.id)}">
        <span class="official-match-topline"><span class="official-match-state status-${matchStatusClass(row.status)}">${escapeHtml(row.status_label || workspace.labels[row.status] || row.status)}</span><b class="official-match-mode">${escapeHtml(scoringType)} · กติกา v${escapeHtml(rule?.version_number || '-')}</b></span>
        ${row.team1_id && row.team2_id && !isRace
          ? `<strong>${escapeHtml(row.team1_name || '-')} <b>${escapeHtml(displayParts[0] ?? row.team1_score ?? '-')}</b></strong><strong>${escapeHtml(row.team2_name || '-')} <b>${escapeHtml(displayParts[1] ?? row.team2_score ?? '-')}</b></strong>`
          : `<strong>${escapeHtml(row.sport_name || 'การแข่งขันจับเวลา')}</strong><span>${escapeHtml(row.round_name || '')}</span>`}
        <small>${escapeHtml([row.match_code || `Match #${row.id}`, row.round_name, row.scheduled_at, row.venue].filter(Boolean).join(' · '))}</small>
      </button>
      ${dedicatedUrl ? `<a class="official-score-page-link" href="${escapeAttr(dedicatedUrl)}">เปิดหน้าบันทึก ${escapeHtml(scoringType)} <span aria-hidden="true">→</span></a>` : ''}
      </div>
    `; }).join('') : '<p class="official-list-empty">ไม่มีรายการ</p>';
  });
  $$('[data-official-match]').forEach(button => button.addEventListener('click', async () => {
    const id = button.dataset.officialMatch;
    await openMatchCenter(String(id));
  }));
}

function dedicatedRecordingPageUrl(match, rule) {
  const pageByType = { SCORE: 'score.php', SET: 'set.php' };
  const page = pageByType[detectScoringType(match, rule)];
  return page ? scoreRecordingPageUrl(match.id, page) : '';
}

function scoreRecordingPageUrl(matchId, page = 'score.php') {
  const apiUrl = new URL(app.apiUrl, window.location.href);
  apiUrl.pathname = apiUrl.pathname.replace(/\/api\.php$/, `/public/scoring/${page}`);
  apiUrl.search = '';
  apiUrl.searchParams.set('match_id', String(matchId));
  return apiUrl.toString();
}

function formatRaceTime(milliseconds) {
  return (Number(milliseconds) / 1000).toFixed(3);
}
async function loadStandings(tournamentId) {
  const rows = tournamentId ? await request('standings.list', { tournament_id: tournamentId }) : [];
  renderTable('#standings-table', [
    ['sport_name', 'กีฬา'], ['rank_number', 'อันดับ'], ['team_name', 'ทีม'], ['department_name', 'สาขา'],
    ['played', 'แข่ง'], ['won', 'ชนะ'], ['drawn', 'เสมอ'], ['lost', 'แพ้'],
    ['scored', 'ได้'], ['conceded', 'เสีย'], ['score_difference', '+/-'], ['points', 'คะแนน'],
  ], rows);
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

function debounce(callback, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => callback(...args), delay);
  };
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
  if (!target) return;
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
  const resultsView = selector.includes('results');
  target.classList.toggle('match-list-results', resultsView);
  target.innerHTML = rows.map(row => row.race_entries?.length ? renderRaceResultCard(row) : (!row.team1_id && !row.team2_id) ? renderRaceScheduleCard(row) : resultsView ? `
    <article class="match-card result-match-card">
      <header class="result-card-context">
        <div><span class="badge match-status status-${matchStatusClass(row.status)}">${escapeHtml(row.status_label || row.status || '-')}</span><small>${escapeHtml(row.match_code || '')}</small></div>
        <strong>${escapeHtml(row.sport_name || 'กีฬา')}</strong>
      </header>
      <div class="result-card-scoreboard">
        <div class="result-card-team">
          ${(row.team1_logo_url || row.team1_department_logo_url) ? `<img src="${escapeAttr(row.team1_logo_url || row.team1_department_logo_url)}" alt="" onerror="this.onerror=null;this.src='public/assets/logo.png'">` : `<span>${escapeHtml(String(row.team1_name || '1').trim().charAt(0))}</span>`}
          <strong>${escapeHtml(row.team1_name || 'ทีม 1')}</strong>
        </div>
        <div class="result-score-lockup"><small>FULL TIME</small><b>${escapeHtml(row.score_label || '-- : --')}</b></div>
        <div class="result-card-team">
          ${(row.team2_logo_url || row.team2_department_logo_url) ? `<img src="${escapeAttr(row.team2_logo_url || row.team2_department_logo_url)}" alt="" onerror="this.onerror=null;this.src='public/assets/logo.png'">` : `<span>${escapeHtml(String(row.team2_name || '2').trim().charAt(0))}</span>`}
          <strong>${escapeHtml(row.team2_name || 'ทีม 2')}</strong>
        </div>
      </div>
      <footer class="result-card-meta">
        <span>${icon('trophy')}${escapeHtml(row.tournament_name || '')}</span>
        <span>${icon('calendar-days')}${escapeHtml(row.scheduled_at || 'ยังไม่ระบุเวลา')}</span>
        ${row.venue ? `<span>${icon('map-pin')}${escapeHtml(row.venue)}</span>` : ''}
      </footer>
    </article>
  ` : `
    <article class="match-card">
      <div class="score-panel">
        <span class="badge match-status status-${matchStatusClass(row.status)}">${escapeHtml(row.status_label || row.status || '-')}</span>
        <div class="score">${escapeHtml(row.score_label || '-- : --')}</div>
        <small>${escapeHtml(row.match_code || '')}</small>
      </div>
      <div class="match-info">
        <div class="match-detail-row match-sport-row">
          <span class="match-detail-label">กีฬา</span>
          <strong>${escapeHtml(row.sport_name || '-')}</strong>
        </div>
        <div class="match-team-row">
          <span class="match-detail-label">สาขา</span>
          <div class="teams">
            <span>${escapeHtml(row.team1_name || 'ทีม 1')}</span>
            <span class="vs">VS</span>
            <span>${escapeHtml(row.team2_name || 'ทีม 2')}</span>
          </div>
        </div>
        <div class="match-detail-row match-round-row">
          <span class="match-detail-label">สาย/กลุ่ม</span>
          <strong>${escapeHtml(row.round_name || '-')}</strong>
        </div>
        <div class="meta">
          <span>${escapeHtml(row.tournament_name || '')}</span>
          <span>${escapeHtml(row.scheduled_at || 'ยังไม่ระบุเวลา')}</span>
          ${row.venue ? `<span>${escapeHtml(row.venue)}</span>` : ''}
        </div>
      </div>
    </article>
  `).join('');
  refreshIcons(target);
}

function renderRaceResultCard(row) {
  const ranked = (row.race_entries || []).filter(entry => entry.rank_number).slice(0, 8);
  return `<article class="match-card race-public-card"><header><span class="badge status-completed">ผลทางการ</span><strong>${escapeHtml(row.sport_name || 'กีฬา')} · ${escapeHtml(row.round_name || 'รอบแข่งขัน')}</strong></header>
    <p>${escapeHtml(row.tournament_name || '')} · ${escapeHtml(row.scheduled_at || '')}</p>
    <ol>${ranked.map(entry => `<li><b>${escapeHtml(entry.rank_number)}</b><span>${escapeHtml(entry.display_name)}<small>${escapeHtml(entry.department_name || entry.department_code || '')}</small></span><strong>${formatRaceTime(entry.verified_time_ms)} วินาที</strong><em>${escapeHtml(entry.points)} คะแนน</em></li>`).join('')}</ol>
    ${(row.race_entries || []).filter(entry => !entry.rank_number).map(entry => `<small>${escapeHtml(entry.display_name)} · ${escapeHtml(entry.result_status)}</small>`).join('')}</article>`;
}

function renderRaceScheduleCard(row) {
  return `<article class="match-card race-public-card"><header><span class="badge status-${matchStatusClass(row.status)}">${escapeHtml(row.status_label || 'รอแข่งขัน')}</span><strong>${escapeHtml(row.sport_name || 'กีฬา')} · ${escapeHtml(row.round_name || 'รอบแข่งขัน')}</strong></header>
    <p>${escapeHtml(row.tournament_name || '')} · ${escapeHtml(row.scheduled_at || 'รอประกาศเวลา')} · ${escapeHtml(row.venue || 'รอประกาศสนาม')}</p></article>`;
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
  if (selector === '#sport-form') setSportIconSelection(row.icon);
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
