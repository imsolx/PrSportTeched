/* Admin Management only. Role keys and authorization remain server-owned. */
const adminUsers = {
  tab: 'members', options: null, loading: false, failed: false, loadId: 0,
  editingUser: null, menuUserId: '', links: new Map(), ownerId: '',
};
const adminRoleLabels = {
  super_admin: 'System Admin', admin: 'Tournament Organizer', sport_head: 'Sport Manager',
  result_officer: 'Match Official', public_relations: 'Public Relations',
};
const adminInviteStatuses = { pending: 'รอตอบรับ', accepted: 'ใช้งานแล้ว', expired: 'หมดอายุ', revoked: 'ถูกเพิกถอน' };

function bindAdminUsers() {
  $$('#view-users [data-admin-tab]').forEach(button => {
    button.addEventListener('click', () => selectAdminUsersTab(button.dataset.adminTab));
    button.addEventListener('keydown', event => {
      const tabs = $$('#view-users [data-admin-tab]');
      let index = tabs.indexOf(button);
      if (event.key === 'ArrowRight') index = (index + 1) % tabs.length;
      else if (event.key === 'ArrowLeft') index = (index + tabs.length - 1) % tabs.length;
      else if (event.key === 'Home') index = 0;
      else if (event.key === 'End') index = tabs.length - 1;
      else return;
      event.preventDefault();
      selectAdminUsersTab(tabs[index].dataset.adminTab);
      tabs[index].focus();
    });
  });
  $('#admin-users-refresh').addEventListener('click', loadUsersAdmin);
  $('#admin-users-retry').addEventListener('click', loadUsersAdmin);
  const memberHead = $('#users-table').parentElement.querySelector('.data-heading');
  memberHead.querySelector('.btn.primary').classList.remove('primary');
  const inviteFromMembers = command('เชิญทีมงาน', 'mail-plus', () => {
    selectAdminUsersTab('invitations');
    openEditor('invite-form');
  }, 'primary');
  inviteFromMembers.id = 'admin-invite-from-members';
  memberHead.append(inviteFromMembers);
  $('#invites-table').parentElement.querySelector('.data-heading > .btn').id = 'admin-create-invite';

  ['admin-member-search', 'admin-member-role-filter', 'admin-member-status-filter'].forEach(id => {
    $('#' + id).addEventListener(id.endsWith('search') ? 'input' : 'change', () => {
      resetAdminTablePage('#users-table');
      renderAdminMembers();
    });
  });
  ['admin-invite-search', 'admin-invite-status-filter'].forEach(id => {
    $('#' + id).addEventListener(id.endsWith('search') ? 'input' : 'change', () => {
      resetAdminTablePage('#invites-table');
      renderAdminInvitations();
    });
  });
  $('#users-table').addEventListener('click', event => {
    const button = event.target.closest('[data-admin-user-menu]');
    if (button) openAdminMemberMenu(button);
  });
  $('#admin-user-action-menu').addEventListener('click', event => {
    const button = event.target.closest('[data-admin-member-action]');
    if (!button) return;
    const row = app.users.find(user => String(user.id) === adminUsers.menuUserId);
    hideAdminMemberMenu();
    if (!row) return;
    if (button.dataset.adminMemberAction === 'edit') openEditor('user-form', row);
    if (button.dataset.adminMemberAction === 'status') runTask($('#admin-users-refresh'), () => setAdminMemberStatus(row));
    if (button.dataset.adminMemberAction === 'delete') runTask($('#admin-users-refresh'), () => deleteRow('users.delete', row.id, loadUsersAdmin));
  });
  $('#admin-user-action-menu').addEventListener('keydown', event => {
    const menu = event.currentTarget;
    const buttons = $$('[role="menuitem"]:not(:disabled)', menu);
    let index = buttons.indexOf(document.activeElement);
    if (event.key === 'ArrowDown') index = (index + 1) % buttons.length;
    else if (event.key === 'ArrowUp') index = (index - 1 + buttons.length) % buttons.length;
    else if (event.key === 'Home') index = 0;
    else if (event.key === 'End') index = buttons.length - 1;
    else if (event.key === 'Escape') {
      event.preventDefault();
      hideAdminMemberMenu();
      adminUsers.menuAnchor?.focus();
      return;
    } else return;
    event.preventDefault();
    buttons[index]?.focus();
  });
  $('#invites-table').addEventListener('click', event => {
    const button = event.target.closest('[data-admin-invite-action]');
    if (!button) return;
    const row = app.invites.find(invite => String(invite.id) === button.dataset.inviteId);
    if (!row) return;
    runTask(button, async () => {
      if (button.dataset.adminInviteAction === 'copy') await copyOrReplaceAdminInvite(row);
      if (button.dataset.adminInviteAction === 'revoke') await revokeInvite(row.id);
      if (button.dataset.adminInviteAction === 'renew') await replaceAdminInvite(row);
    });
  });
  for (const [id, prefix] of [['user-form', 'admin-user'], ['invite-form', 'admin-invite']]) {
    const form = $('#' + id);
    form.elements.role.addEventListener('change', () => syncAdminScopeVisibility(form, prefix));
    $('#' + prefix + '-sport-options').addEventListener('change', () => {
      form.elements.sport_ids.value = $$('[data-admin-scope-sport]:checked', form).map(input => input.value).join(',');
    });
  }
  const memberForm = $('#user-form');
  for (const name of ['password', 'password_confirm']) {
    memberForm.elements[name].addEventListener('input', () => {
      memberForm.elements.password_confirm.setCustomValidity(
        memberForm.elements.password_confirm.value && memberForm.elements.password.value !== memberForm.elements.password_confirm.value
          ? 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน' : ''
      );
    });
  }
  memberForm.addEventListener('submit', saveAdminMember);
  $('#invite-form').addEventListener('submit', createAdminInvitation);
  $('#admin-invite-settings-form').addEventListener('submit', saveAdminInviteSettings);
  $('#admin-audit-details').addEventListener('toggle', () => {
    if ($('#admin-audit-details').open) loadAuditLogs();
  });
  $('#audit-search').addEventListener('input', debounce(loadAuditLogs, 200));
  $('#logout-button').addEventListener('click', () => {
    adminUsers.links.clear();
    $('#invite-link-box').replaceChildren();
    $('#invite-link-box').hidden = true;
    hideAdminMemberMenu();
  });
  refreshIcons($('#view-users'));
}

function selectAdminUsersTab(tab) {
  if (!['members', 'invitations', 'settings'].includes(tab)) return;
  adminUsers.tab = tab;
  $$('#view-users [data-admin-tab]').forEach(button => {
    const selected = button.dataset.adminTab === tab;
    button.setAttribute('aria-selected', String(selected));
    button.tabIndex = selected ? 0 : -1;
  });
  for (const name of ['members', 'invitations', 'settings']) {
    $('#admin-panel-' + name).hidden = adminUsers.loading || adminUsers.failed || tab !== name;
  }
  hideAdminMemberMenu();
}

async function loadUsersAdmin() {
  const loadId = ++adminUsers.loadId;
  adminUsers.loading = true;
  adminUsers.failed = false;
  $('#admin-users-loading').hidden = false;
  $('#admin-users-error').hidden = true;
  $('#view-users').setAttribute('aria-busy', 'true');
  $('#admin-users-refresh').disabled = true;
  selectAdminUsersTab(adminUsers.tab);
  try {
    const [options, users, invites] = await Promise.all([
      request('users.options', {}, { silent: true }),
      request('users.list', {}, { silent: true }),
      request('invites.list', {}, { silent: true }),
    ]);
    if (loadId !== adminUsers.loadId) return;
    if (adminUsers.ownerId !== String(app.user?.id)) {
      adminUsers.links.clear();
      $('#invite-link-box').hidden = true;
      adminUsers.ownerId = String(app.user?.id);
    }
    adminUsers.options = options;
    app.users = users;
    app.invites = invites;
    configureAdminUsersOptions();
    renderAdminMembers();
    renderAdminInvitations();
    if ($('#admin-audit-details').open) await loadAuditLogs();
  } catch (error) {
    if (loadId !== adminUsers.loadId) return;
    adminUsers.failed = true;
    $('#admin-users-error p').textContent = error.message || 'โหลดข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง';
    $('#admin-users-error').hidden = false;
  } finally {
    if (loadId === adminUsers.loadId) {
      adminUsers.loading = false;
      $('#admin-users-loading').hidden = true;
      $('#view-users').removeAttribute('aria-busy');
      $('#admin-users-refresh').disabled = false;
      selectAdminUsersTab(adminUsers.tab);
    }
  }
}

function configureAdminUsersOptions() {
  const options = adminUsers.options;
  const roles = options.roles.map(row => ({ ...row, label: adminRoleLabels[row.value] || row.label }));
  for (const id of ['user-role-select', 'invite-role-select', 'admin-member-role-filter']) {
    const select = $('#' + id), previous = select.value;
    setOptions(select, roles, row => row.value, row => row.label, id.endsWith('filter') ? 'ทุกบทบาท' : 'เลือกบทบาท');
    if (roles.some(row => row.value === previous)) select.value = previous;
  }
  $('#staff-invites-enabled').checked = Boolean(options.staff_invites_enabled);
  $('#staff-invite-expiry').value = options.invite_expiry_minutes;
  $('#admin-create-invite').disabled = !options.staff_invites_enabled;
  $('#admin-invite-from-members').disabled = !options.staff_invites_enabled;
  $('#admin-invite-notice').textContent = options.staff_invites_enabled
    ? `เปิดใช้คำเชิญ · คำเชิญใหม่มีอายุ ${options.invite_expiry_minutes} นาที`
    : 'ปิดใช้คำเชิญชั่วคราว — ยังดูประวัติและเพิกถอนคำเชิญได้ เปิดใช้ได้ในแท็บตั้งค่าการรับทีมงาน';
}

function resetAdminTablePage(selector) {
  const state = workspace.tables.get(selector);
  if (state) state.page = 1;
}

function renderAdminTable(selector, columns, rows, noMatch) {
  const state = workspace.tables.get(selector);
  if (state) { state.query = ''; state.status = ''; }
  renderTable(selector, columns, rows);
  const host = $(selector);
  if (!host.previousElementSibling?.classList.contains('admin-mobile-table-hint')) {
    const hint = document.createElement('p');
    hint.className = 'admin-mobile-table-hint';
    hint.textContent = 'เลื่อนตารางแนวนอนเพื่อดูข้อมูลและปุ่มจัดการ';
    host.before(hint);
  }
  if (!rows.length) {
    const output = $(selector + ' .empty-state p');
    if (output) output.textContent = noMatch;
  }
}

function adminScopeLabel(row) {
  const role = row.role;
  if (role === 'super_admin') return 'ทั้งระบบ';
  if (role === 'admin') return 'ทุกรายการตามสิทธิ์เดิม · ยังไม่จำกัดเฉพาะทัวร์นาเมนต์';
  if (role === 'public_relations') return 'งานข่าวสารและประชาสัมพันธ์ตามสิทธิ์เดิม';
  const sportIds = String(row.sport_ids || '').split(',').filter(Boolean);
  const sports = sportIds.map(id => adminUsers.options?.sports.find(sport => String(sport.id) === id)?.name || 'กีฬา #' + id);
  const parts = [role === 'result_officer' ? 'เฉพาะแมตช์ที่ได้รับมอบหมาย' : 'เฉพาะกีฬาที่รับผิดชอบ'];
  if (sports.length) parts.push(sports.join(', '));
  else if (role === 'sport_head') parts.push('ยังไม่ระบุกีฬา');
  if (row.department_code) parts.push(row.department_name || row.department_code);
  return parts.join(' · ');
}

function adminStatusBadge(label, tone) {
  return `<span class="admin-status admin-status-${tone}">${icon(tone === 'success' ? 'circle-check' : tone === 'warning' ? 'clock-3' : 'circle-minus')}${escapeHtml(label)}</span>`;
}

function adminIdentityCell(row, markCurrentUser = false) {
  const label = row.name || row.email;
  const current = markCurrentUser && String(row.id) === String(app.user?.id) ? ' (คุณ)' : '';
  const email = row.email && row.email !== label ? `<span>${escapeHtml(row.email)}</span>` : '';
  return `<div class="admin-user-identity"><strong>${escapeHtml(label)}${current}</strong>${email}</div>`;
}

function renderAdminMembers() {
  const query = $('#admin-member-search').value.trim().toLocaleLowerCase('th');
  const role = $('#admin-member-role-filter').value;
  const status = $('#admin-member-status-filter').value;
  const rows = app.users.filter(row => (!query || `${row.name} ${row.email}`.toLocaleLowerCase('th').includes(query))
    && (!role || row.role === role) && (!status || (Boolean(row.is_active) ? 'active' : 'inactive') === status));
  renderAdminTable('#users-table', [
    ['identity', 'ชื่อและอีเมล', (_, row) => adminIdentityCell(row, true), { html: true }],
    ['role_label', 'บทบาท', (_, row) => adminRoleLabels[row.role] || row.role_label],
    ['scope', 'ขอบเขตที่รับผิดชอบ', (_, row) => adminScopeLabel(row)],
    ['active_label', 'สถานะ', (_, row) => adminStatusBadge(row.is_active ? 'เปิดใช้งาน' : 'ระงับบัญชี', row.is_active ? 'success' : 'neutral'), { html: true }],
    ['actions', 'จัดการ', (_, row) => `<button type="button" class="btn icon-button" data-admin-user-menu="${escapeAttr(row.id)}" aria-label="จัดการสมาชิก ${escapeAttr(row.name || row.email)}" aria-haspopup="menu">${icon('ellipsis')}</button>`],
  ], rows, app.users.length ? 'ไม่พบสมาชิกที่ตรงกับการค้นหาหรือตัวกรอง' : 'ยังไม่มีสมาชิก เริ่มต้นโดยเพิ่มสมาชิกหรือเชิญทีมงาน');
}

function renderAdminInvitations() {
  const query = $('#admin-invite-search').value.trim().toLocaleLowerCase('th');
  const status = $('#admin-invite-status-filter').value;
  const enabled = Boolean(adminUsers.options?.staff_invites_enabled);
  const rows = app.invites.filter(row => (!query || `${row.name} ${row.email}`.toLocaleLowerCase('th').includes(query)) && (!status || row.status === status));
  const action = (row, key, label, glyph, disabled = false) => `<button type="button" class="btn small ${key === 'revoke' ? 'danger' : ''}" data-admin-invite-action="${key}" data-invite-id="${escapeAttr(row.id)}" ${disabled ? 'disabled' : ''}>${icon(glyph)}<span>${label}</span></button>`;
  renderAdminTable('#invites-table', [
    ['identity', 'ผู้รับเชิญ', (_, row) => adminIdentityCell(row), { html: true }],
    ['role_label', 'บทบาท', (_, row) => adminRoleLabels[row.role] || row.role_label],
    ['expires_at', 'วันหมดอายุ', value => value || '—'],
    ['status_label', 'สถานะ', (_, row) => adminStatusBadge(adminInviteStatuses[row.status] || row.status, row.status === 'accepted' ? 'success' : row.status === 'pending' ? 'warning' : 'neutral'), { html: true }],
    ['actions', 'จัดการ', (_, row) => row.status === 'pending'
      ? action(row, 'copy', 'คัดลอกลิงก์', 'copy') + action(row, 'revoke', 'เพิกถอน', 'x')
      : row.status === 'expired' ? action(row, 'renew', 'สร้างคำเชิญใหม่', 'refresh-cw', !enabled) : '<span class="muted">—</span>'],
  ], rows, app.invites.length ? 'ไม่พบคำเชิญที่ตรงกับการค้นหาหรือตัวกรอง' : 'ยังไม่มีคำเชิญ สร้างคำเชิญเพื่อให้ทีมงานตั้งค่าบัญชีด้วยตนเอง');
}

function hideAdminMemberMenu() {
  const menu = $('#admin-user-action-menu');
  if (menu.matches(':popover-open')) menu.hidePopover();
}

function openAdminMemberMenu(button) {
  const row = app.users.find(user => String(user.id) === button.dataset.adminUserMenu);
  if (!row) return;
  const menu = $('#admin-user-action-menu');
  hideAdminMemberMenu();
  adminUsers.menuUserId = String(row.id);
  adminUsers.menuAnchor = button;
  const self = String(row.id) === String(app.user?.id);
  const toggle = $('[data-admin-member-action="status"]', menu);
  toggle.innerHTML = `${icon(row.is_active ? 'user-round-x' : 'user-round-check')}${row.is_active ? 'ระงับบัญชี' : 'เปิดใช้งานบัญชี'}`;
  toggle.disabled = self;
  toggle.title = self ? 'ระงับบัญชีของตัวเองไม่ได้' : '';
  $('[data-admin-member-action="delete"]', menu).disabled = self;
  refreshIcons(menu);
  menu.showPopover();
  const rect = button.getBoundingClientRect();
  menu.style.left = Math.max(8, Math.min(innerWidth - menu.offsetWidth - 8, rect.right - menu.offsetWidth)) + 'px';
  menu.style.top = (rect.bottom + menu.offsetHeight + 8 < innerHeight ? rect.bottom + 4 : Math.max(8, rect.top - menu.offsetHeight - 4)) + 'px';
  $('[role="menuitem"]', menu).focus();
}

function prepareAdminUserEditor(id, row) {
  const form = $('#' + id), prefix = id === 'user-form' ? 'admin-user' : 'admin-invite';
  const options = adminUsers.options;
  if (!options) return;
  const department = form.elements.department_code;
  setOptions(department, options.departments, item => item.code, item => item.name, 'ไม่ระบุสังกัด');
  if (row?.department_code && !options.departments.some(item => item.code === row.department_code)) {
    department.add(new Option((row.department_name || row.department_code) + ' (ข้อมูลเดิม)', row.department_code));
  }
  department.value = row?.department_code || '';
  form.elements.role.value = row?.role || '';
  form.elements.sport_ids.value = row?.sport_ids || '';
  const selected = new Set(String(row?.sport_ids || '').split(',').map(value => value.trim()).filter(Boolean));
  const sports = [...options.sports];
  for (const sportId of selected) if (!sports.some(sport => String(sport.id) === sportId)) sports.push({ id: sportId, name: 'กีฬา #' + sportId + ' (ข้อมูลเดิม)' });
  $('#' + prefix + '-sport-options').innerHTML = sports.map(sport =>
    `<label><input type="checkbox" data-admin-scope-sport value="${escapeAttr(sport.id)}" ${selected.has(String(sport.id)) ? 'checked' : ''}><span>${escapeHtml(sport.name)}</span></label>`).join('') || '<p class="muted">ยังไม่มีกีฬาที่เปิดใช้งาน</p>';
  if (id === 'user-form') {
    adminUsers.editingUser = row?.id ? { ...row } : null;
    const self = row?.id && String(row.id) === String(app.user?.id);
    form.elements.email.readOnly = Boolean(self);
    form.elements.role.disabled = Boolean(self);
    form.elements.is_active.disabled = Boolean(self);
    for (const name of ['password', 'password_confirm']) {
      form.elements[name].required = !row?.id;
      form.elements[name].setCustomValidity('');
    }
    $('#admin-user-password-fields').open = !row?.id;
    $('#admin-user-password-help').textContent = row?.id
      ? 'เว้นว่างหากไม่เปลี่ยนรหัสผ่านเดิม'
      : 'สร้างบัญชีโดยตรงต้องตั้งรหัสผ่านอย่างน้อย 8 ตัวอักษร หากให้ทีมงานตั้งรหัสผ่านเอง ให้ใช้คำเชิญทีมงาน';
    $('#admin-member-feedback').textContent = '';
  } else {
    $('#admin-invite-lifetime').textContent = `คำเชิญใหม่นี้มีอายุ ${options.invite_expiry_minutes} นาที และใช้ได้ครั้งเดียว`;
    $('#admin-invite-feedback').textContent = '';
  }
  syncAdminScopeVisibility(form, prefix);
}

function syncAdminScopeVisibility(form, prefix) {
  const role = form.elements.role.value;
  const relevant = ['sport_head', 'result_officer'].includes(role);
  const hasStoredScope = Boolean(form.elements.department_code.value || form.elements.sport_ids.value);
  $('#' + prefix + '-scope-fields').hidden = !relevant && !hasStoredScope;
  const descriptions = {
    super_admin: 'ดูแลทั้งระบบและจัดการบัญชีผู้ใช้ได้',
    admin: 'ใช้สิทธิ์เดิมของ Operation Admin ดูแลรายการแข่งขันได้ทุกรายการ ยังไม่รองรับการจำกัดเฉพาะทัวร์นาเมนต์',
    sport_head: 'ระบุกีฬาที่รับผิดชอบ หากไม่ระบุจะยังจัดการกีฬาไม่ได้ การบันทึกผลยังต้องได้รับมอบหมายรายแมตช์ตามระบบเดิม',
    result_officer: 'บันทึกผลเฉพาะแมตช์ที่ได้รับมอบหมาย กีฬาและสังกัดที่ระบุใช้จำกัดขอบเขตเพิ่มเติม',
    public_relations: 'ดูแลงานข่าวสารและประชาสัมพันธ์ตามสิทธิ์เดิม',
  };
  $('#' + prefix + '-role-help').textContent = (descriptions[role] || 'เลือกบทบาทเพื่อดูขอบเขตความรับผิดชอบ')
    + (!relevant && hasStoredScope ? ' ขอบเขตด้านบนเป็นข้อมูลที่บันทึกไว้เดิม ระบบจะไม่ล้างค่าให้อัตโนมัติ' : '');
}

async function saveAdminMember(event) {
  event.preventDefault();
  const form = event.currentTarget;
  await runForm(form, async () => {
    const data = formData(form);
    $('#admin-member-feedback').textContent = '';
    const current = adminUsers.editingUser;
    const changedAccess = current && ['role', 'department_code', 'sport_ids', 'is_active'].some(key => String(current[key] ?? '') !== String(data[key] ?? ''));
    if (changedAccess || (!current && data.role === 'super_admin')) {
      if (!await confirmAction('ยืนยันบทบาทและสิทธิ์?', `บันทึกบัญชี ${data.email} เป็น ${adminRoleLabels[data.role] || data.role} และ${data.is_active ? 'เปิดใช้งาน' : 'ระงับบัญชี'} ตามขอบเขตที่เลือก`, 'ยืนยันการเปลี่ยนแปลง')) return;
    }
    $('#admin-member-feedback').textContent = 'กำลังบันทึก…';
    await request('users.save', data, { silent: true });
    finishEditor(form);
    await loadUsersAdmin();
    toast('บันทึกสมาชิกแล้ว');
  });
  $('#admin-member-feedback').textContent = '';
}

async function setAdminMemberStatus(row) {
  if (String(row.id) === String(app.user?.id)) return;
  const label = row.is_active ? 'ระงับบัญชี' : 'เปิดใช้งานบัญชี';
  if (!await confirmAction(label + '?', `${row.name || row.email} (${row.email})${row.is_active ? ' จะเข้าสู่ระบบและใช้งานบัญชีนี้ไม่ได้' : ' จะกลับมาใช้งานได้ตามสิทธิ์เดิม'}`, label, Boolean(row.is_active))) return;
  await request('users.status', { id: row.id, is_active: !row.is_active });
  await loadUsersAdmin();
  toast(label + 'แล้ว');
}

async function createAdminInvitation(event) {
  event.preventDefault();
  const form = event.currentTarget;
  await runForm(form, async () => {
    if (!adminUsers.options?.staff_invites_enabled) throw new Error('ปิดใช้คำเชิญอยู่ กรุณาเปิดในแท็บตั้งค่าก่อน');
    $('#admin-invite-feedback').textContent = 'กำลังสร้างคำเชิญ…';
    const invite = await request('invites.create', formData(form), { silent: true });
    adminUsers.links.set(String(invite.id), invite.invite_url);
    finishEditor(form);
    renderInviteLink(invite);
    selectAdminUsersTab('invitations');
    await loadUsersAdmin();
    toast('สร้างลิงก์คำเชิญแล้ว');
  });
  $('#admin-invite-feedback').textContent = '';
}

function renderInviteLink(invite) {
  const box = $('#invite-link-box');
  box.hidden = false;
  box.innerHTML = `<strong>คำเชิญสำหรับ ${escapeHtml(invite.email)}</strong><label>ลิงก์คำเชิญ<input value="${escapeAttr(invite.invite_url)}" readonly aria-label="ลิงก์คำเชิญที่สร้าง"></label><div class="actions"><button class="btn small" type="button">คัดลอกลิงก์</button><span class="muted">หมดอายุ ${escapeHtml(invite.expires_at)}</span></div>`;
  $('button', box).addEventListener('click', () => runTask($('button', box), () => copyInviteLink(invite.invite_url)));
}

async function copyInviteLink(url) {
  try {
    await navigator.clipboard.writeText(url);
    toast('คัดลอกลิงก์คำเชิญแล้ว');
  } catch {
    const input = $('#invite-link-box input');
    if (input?.value === url) { input.focus(); input.select(); }
    toast('คัดลอกอัตโนมัติไม่ได้ กรุณาคัดลอกลิงก์จากช่องที่แสดง', true);
  }
}

async function copyOrReplaceAdminInvite(row) {
  const url = adminUsers.links.get(String(row.id));
  if (url) {
    renderInviteLink({ ...row, invite_url: url });
    return copyInviteLink(url);
  }
  await replaceAdminInvite(row, true);
}

async function replaceAdminInvite(row, copy = false) {
  if (!adminUsers.options?.staff_invites_enabled) throw new Error('เปิดใช้คำเชิญในแท็บตั้งค่าก่อนสร้างลิงก์ใหม่');
  const message = row.status === 'pending'
    ? 'ลิงก์เดิมไม่สามารถคัดลอกซ้ำหลังโหลดหน้าใหม่ได้ ต้องสร้างคำเชิญทดแทน ลิงก์เดิมจะใช้ไม่ได้ ยืนยันสร้างลิงก์ใหม่หรือไม่?'
    : `สร้างคำเชิญใหม่ให้ ${row.email} โดยใช้บทบาทและขอบเขตเดิม ลิงก์ที่หมดอายุจะยังใช้ไม่ได้`;
  if (!await confirmAction('สร้างคำเชิญใหม่?', message, 'สร้างคำเชิญใหม่')) return;
  const invite = await request('invites.create', { email: row.email, name: row.name, role: row.role, department_code: row.department_code || '', sport_ids: row.sport_ids || '' });
  adminUsers.links.set(String(invite.id), invite.invite_url);
  renderInviteLink(invite);
  await loadUsersAdmin();
  if (copy) await copyInviteLink(invite.invite_url);
  else toast('สร้างคำเชิญใหม่แล้ว');
}

async function revokeInvite(id) {
  const row = app.invites.find(invite => String(invite.id) === String(id));
  if (!await confirmAction('เพิกถอนคำเชิญ?', `คำเชิญของ ${row?.email || ''} จะใช้งานไม่ได้ทันที`, 'เพิกถอนคำเชิญ', true)) return;
  const updated = await request('invites.revoke', { id });
  adminUsers.links.delete(String(id));
  $('#invite-link-box').hidden = true;
  await loadUsersAdmin();
  toast(updated.status === 'revoked' ? 'เพิกถอนคำเชิญแล้ว' : 'คำเชิญนี้ไม่อยู่ในสถานะรอตอบรับแล้ว');
}

async function saveAdminInviteSettings(event) {
  event.preventDefault();
  const form = event.currentTarget;
  await runForm(form, async () => {
    $('#admin-settings-feedback').textContent = '';
    const data = formData(form);
    if (!data.enabled && adminUsers.options.staff_invites_enabled) {
      if (!await confirmAction('ปิดใช้คำเชิญทีมงาน?', 'จะสร้างคำเชิญใหม่และใช้คำเชิญที่รอตอบรับไม่ได้จนกว่าจะเปิดอีกครั้ง เวลาหมดอายุยังนับต่อไป', 'ปิดใช้คำเชิญ')) return;
    }
    $('#admin-settings-feedback').textContent = 'กำลังบันทึก…';
    await request('invites.toggle', data, { silent: true });
    await loadUsersAdmin();
    $('#admin-settings-feedback').textContent = 'บันทึกการตั้งค่าแล้ว';
  });
  if ($('.form-error', form).hidden && $('#admin-settings-feedback').textContent === 'กำลังบันทึก…') $('#admin-settings-feedback').textContent = '';
  if (!$('.form-error', form).hidden) $('#admin-settings-feedback').textContent = '';
}

let adminAuditLoadId = 0;
async function loadAuditLogs() {
  const loadId = ++adminAuditLoadId;
  $('#admin-audit-state').textContent = 'กำลังโหลดประวัติ…';
  try {
    const rows = await request('audit.list', { q: $('#audit-search').value }, { silent: true });
    if (loadId !== adminAuditLoadId) return;
    renderTable('#audit-full-table', [
      ['created_at', 'เวลา'], ['user_email', 'ผู้ใช้'], ['action', 'การดำเนินการ'],
      ['entity', 'ข้อมูล'], ['entity_id', 'รหัสอ้างอิง'],
      ['detail_json', 'รายละเอียด', value => String(value || '').slice(0, 180)],
    ], rows);
    $('#admin-audit-state').textContent = '';
  } catch (error) {
    if (loadId === adminAuditLoadId) $('#admin-audit-state').textContent = error.message || 'โหลดประวัติไม่สำเร็จ ลองเปิดส่วนนี้ใหม่อีกครั้ง';
  }
}
