(() => {
  'use strict';

  const config = window.PRSET;
  const state = { matches: [], rules: [], center: null, sets: [], busy: false };
  const $ = selector => document.querySelector(selector);
  const $$ = selector => [...document.querySelectorAll(selector)];
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);

  document.addEventListener('DOMContentLoaded', init);

  async function init() {
    bindControls();
    try {
      const [matches, rules] = await Promise.all([
        request('matches.list'),
        request('sportRules.list', { sport_id: '' }),
      ]);
      state.matches = matches;
      state.rules = rules;
      renderMatchOptions();
      const matchId = new URLSearchParams(location.search).get('match_id') || '';
      if (matchId) {
        $('#set-match-select').value = matchId;
        await loadMatch(matchId);
      }
    } catch (error) {
      showToast(error.message, true);
    }
  }

  function bindControls() {
    $('#set-match-select').addEventListener('change', event => loadMatch(event.target.value));
    $('#set-start').addEventListener('click', startMatch);
    $('#set-add').addEventListener('click', addSet);
    $('#set-finish').addEventListener('click', finishMatch);
    $$('[data-point-side]').forEach(button => button.addEventListener('click', () => addPoint(button.dataset.pointSide, button)));
    $('#set-result-update').addEventListener('click', updateOfficialResult);
    $('#set-result-delete').addEventListener('click', deleteOfficialResult);
  }

  function renderMatchOptions() {
    const ruleTypes = new Map(state.rules.map(rule => [String(rule.id), String(rule.scoring_type || '').toUpperCase()]));
    const rows = state.matches.filter(match => ruleTypes.get(String(match.rule_version_id)) === 'SET');
    const groups = [
      ['live', 'กำลังแข่งขัน'],
      ['scheduled', 'รอแข่งขัน'],
      ['completed', 'จบแล้ว'],
    ];
    $('#set-match-select').innerHTML = `<option value="">เลือกแมตช์ SET</option>${groups.map(([status, label]) => {
      const matches = rows.filter(match => String(match.status) === status);
      return matches.length ? `<optgroup label="${label}">${matches.map(match =>
        `<option value="${escapeHtml(match.id)}">${escapeHtml(matchOptionLabel(match, label))}</option>`
      ).join('')}</optgroup>` : '';
    }).join('')}`;
    if (!rows.length) $('#set-match-select').innerHTML = '<option value="">ยังไม่มีแมตช์รูปแบบ SET</option>';
  }

  function matchOptionLabel(match, statusLabel = '') {
    const code = match.match_code || `Match #${match.id}`;
    const teams = `${match.team1_name || 'ยังไม่กำหนดทีม'} พบ ${match.team2_name || 'ยังไม่กำหนดทีม'}`;
    const context = [match.sport_name, match.round_name, match.scheduled_at, match.venue].filter(Boolean).join(' · ');
    return `[${statusLabel}] ${code} · ${teams}${context ? ` · ${context}` : ''}`;
  }

  async function loadMatch(matchId) {
    if (!matchId) {
      state.center = null;
      state.sets = [];
      $('#set-empty').hidden = false;
      $('#set-workspace').hidden = true;
      syncUrl('');
      return;
    }
    try {
      const data = await request('matches.center', { match_id: matchId });
      const type = String(data.resolved_scoring_type || data.rule?.scoring_type || '').toUpperCase();
      if (type !== 'SET') throw new Error(`แมตช์นี้ใช้รูปแบบ ${type || 'อื่น'} ไม่ใช่ SET`);
      state.center = data;
      const source = data.recording_result?.result_data_value ||
        (data.recording_draft?.scoring_type === 'SET' ? data.recording_draft.result_data : {});
      state.sets = normalizeSets(source?.sets || []);
      $('#set-empty').hidden = true;
      $('#set-workspace').hidden = false;
      ensureMatchOption(data.match);
      $('#set-match-select').value = String(matchId);
      syncUrl(matchId);
      renderCenter();
    } catch (error) {
      state.center = null;
      state.sets = [];
      $('#set-empty').hidden = false;
      $('#set-workspace').hidden = true;
      showToast(error.message, true);
    }
  }

  function normalizeSets(rows) {
    return (Array.isArray(rows) ? rows : []).map((row, index) => ({
      number: index + 1,
      home: Math.max(0, Number.parseInt(row.home, 10) || 0),
      away: Math.max(0, Number.parseInt(row.away, 10) || 0),
    }));
  }

  function ensureMatchOption(match) {
    if ([...$('#set-match-select').options].some(option => option.value === String(match.id))) return;
    const option = document.createElement('option');
    option.value = String(match.id);
    option.textContent = matchOptionLabel(match, match.status_label || match.status || '');
    $('#set-match-select').append(option);
  }

  function syncUrl(matchId) {
    const url = new URL(location.href);
    if (matchId) url.searchParams.set('match_id', matchId);
    else url.searchParams.delete('match_id');
    history.replaceState({}, '', url);
  }

  function ruleConfig() {
    return state.center?.rule?.scoring_config_data || {};
  }

  function bestOf() {
    return Math.max(1, Number.parseInt(ruleConfig().best_of, 10) || 3);
  }

  function neededSets() {
    return Math.floor(bestOf() / 2) + 1;
  }

  function setTarget(index) {
    const rule = ruleConfig();
    if (index + 1 === bestOf() && Number(rule.final_set_points) > 0) return Number(rule.final_set_points);
    if (Number(rule.normal_set_points) > 0) return Number(rule.normal_set_points);
    return Math.max(0, Number(rule.points_to_win_set || rule.points_to_win || 0));
  }

  function setWins(rows = state.sets) {
    return rows.reduce((wins, row, index) => {
      if (!isSetComplete(row, index)) return wins;
      if (Number(row.home) > Number(row.away)) wins.home += 1;
      if (Number(row.away) > Number(row.home)) wins.away += 1;
      return wins;
    }, { home: 0, away: 0 });
  }

  function isSetComplete(row, index) {
    const home = Number(row.home || 0);
    const away = Number(row.away || 0);
    if (home === away) return false;
    const target = setTarget(index);
    if (target <= 0) return true;
    const high = Math.max(home, away);
    const low = Math.min(home, away);
    const winBy = Math.max(1, Number(ruleConfig().win_by || 2));
    const cap = Math.max(0, Number(ruleConfig().max_points || 0));
    const validAtCap = cap > 0 && high === cap && low === cap - 1;
    return high >= target && (validAtCap || high - low >= winBy);
  }

  function renderCenter() {
    const { match, recording_draft: draft, recording_result: result } = state.center;
    const status = String(match.status || 'scheduled');
    $('#set-status').textContent = match.status_label || ({ scheduled: 'รอแข่งขัน', live: 'กำลังแข่งขัน', completed: 'จบแล้ว' }[status] || status);
    $('#set-status').className = `status ${status}`;
    $('#set-sport').textContent = match.sport_name || 'กีฬา';
    $('#set-round').textContent = match.round_name || 'รอบการแข่งขัน';
    $('#set-meta').textContent = [match.tournament_name, match.match_code, match.scheduled_at, match.venue].filter(Boolean).join(' · ') || '—';
    $('#set-team1-name').textContent = match.team1_name || 'ทีม 1';
    $('#set-team2-name').textContent = match.team2_name || 'ทีม 2';
    $('#set-head-home').textContent = match.team1_name || 'ทีม 1';
    $('#set-head-away').textContent = match.team2_name || 'ทีม 2';
    const rule = ruleConfig();
    $('#set-best-of').textContent = `Best of ${bestOf()}`;
    const target = Number(rule.points_to_win_set || rule.points_to_win || 0);
    $('#set-rule-detail').textContent = `ชนะ ${neededSets()} เซต${target ? ` · ${target} คะแนน/เซต` : ''} · ห่าง ${Number(rule.win_by || 2)}`;
    const wins = setWins();
    $('#set-team1-wins').textContent = wins.home;
    $('#set-team2-wins').textContent = wins.away;
    $('#set-current-label').textContent = state.sets.length ? `เซตปัจจุบัน ${state.sets.length}` : 'ยังไม่มีเซต';
    $('#set-count').textContent = `${state.sets.length} เซต`;
    $('#set-draft-status').textContent = result ? 'ผลการแข่งขันได้รับการยืนยันแล้ว' : draft
      ? `บันทึกแบบร่างล่าสุด ${draft.created_at}` : 'ยังไม่มีแบบร่าง';
    renderSetRows();
    renderResultAdmin();
    renderHistory();
    syncAvailability(status);
  }

  function renderSetRows() {
    const live = state.center?.match?.status === 'live';
    $('#set-rows').innerHTML = state.sets.length ? state.sets.map((row, index) => `
      <article class="set-row" data-set-row="${index}">
        <strong>เซต ${index + 1}</strong>
        <input type="number" min="0" max="999" step="1" data-set-side="home" value="${row.home}" aria-label="คะแนน ${escapeHtml(state.center.match.team1_name)} เซต ${index + 1}" ${live ? '' : 'disabled'}>
        <input type="number" min="0" max="999" step="1" data-set-side="away" value="${row.away}" aria-label="คะแนน ${escapeHtml(state.center.match.team2_name)} เซต ${index + 1}" ${live ? '' : 'disabled'}>
        <span class="set-row-actions"><button type="button" data-save-set="${index}" ${live ? '' : 'disabled'}>บันทึก</button><button type="button" class="delete" data-delete-set="${index}" ${live ? '' : 'disabled'}>ลบ</button></span>
      </article>`).join('') : '<div class="set-empty-row">ยังไม่มีเซต กด “เพิ่มเซต” เพื่อเริ่มบันทึกคะแนน</div>';
    $$('[data-save-set]').forEach(button => button.addEventListener('click', () => saveSet(Number(button.dataset.saveSet), button)));
    $$('[data-delete-set]').forEach(button => button.addEventListener('click', () => deleteSet(Number(button.dataset.deleteSet), button)));
  }

  function renderResultAdmin() {
    const result = state.center?.recording_result;
    $('#set-result-admin').hidden = !result;
    if (!result) return;
    $('#set-result-revision').textContent = `Revision ${Number(result.revision_number || 1)}`;
    $('#set-result-rows').innerHTML = state.sets.map((row, index) => `
      <div class="result-set-row" data-result-set="${index}"><strong>เซต ${index + 1}</strong>
        <input type="number" min="0" max="999" value="${row.home}" data-result-side="home" aria-label="ผลทีมเหย้าเซต ${index + 1}">
        <input type="number" min="0" max="999" value="${row.away}" data-result-side="away" aria-label="ผลทีมเยือนเซต ${index + 1}">
      </div>`).join('');
    $('#set-result-reason').value = '';
  }

  function renderHistory() {
    const rows = state.center?.recording_history || [];
    $('#set-history-count').textContent = `${rows.length} รายการ`;
    $('#set-history').innerHTML = rows.length ? rows.map(row => `
      <article class="history-item"><time>${escapeHtml(row.created_at || '—')}</time><strong>${escapeHtml(row.note || 'บันทึกแบบร่าง SET')}</strong><small>${escapeHtml(row.created_by || '')}</small></article>
    `).join('') : '<div class="history-empty">ยังไม่มีประวัติการบันทึก</div>';
  }

  function syncAvailability(status) {
    const live = status === 'live';
    const completed = status === 'completed';
    const busy = state.busy;
    const currentComplete = state.sets.length === 0 || isSetComplete(state.sets.at(-1), state.sets.length - 1);
    $('#set-start').disabled = busy || live || completed;
    $('#set-add').disabled = busy || !live || !currentComplete || state.sets.length >= bestOf() || hasWinner();
    $('#set-finish').disabled = busy || !live || !hasWinner();
    $$('[data-point-side]').forEach(button => { button.disabled = busy || !live || state.sets.length === 0 || currentComplete || hasWinner(); });
    $$('[data-set-side], [data-save-set], [data-delete-set]').forEach(control => { control.disabled = busy || !live; });
  }

  function hasWinner(rows = state.sets) {
    const wins = setWins(rows);
    return wins.home >= neededSets() || wins.away >= neededSets();
  }

  function readSetRow(index) {
    const host = $(`[data-set-row="${index}"]`);
    if (!host) return null;
    const home = Number(host.querySelector('[data-set-side="home"]').value);
    const away = Number(host.querySelector('[data-set-side="away"]').value);
    if (!Number.isInteger(home) || home < 0 || !Number.isInteger(away) || away < 0) throw new Error('คะแนนต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป');
    return { number: index + 1, home, away };
  }

  async function startMatch() {
    if (!state.center || state.center.match.status !== 'scheduled') return;
    await run($('#set-start'), async () => {
      const match = state.center.match;
      await request('matches.clock', { match_id: match.id, clock_action: 'start', clock_seconds: 0, period_number: 1 });
      await loadMatch(String(match.id));
      if (state.sets.length === 0) {
        state.sets.push({ number: 1, home: 0, away: 0 });
        await persistDraft('เริ่มเซต 1');
      }
      showToast('เริ่มการแข่งขันและเปิดเซต 1 แล้ว');
    });
  }

  async function addSet() {
    const currentComplete = state.sets.length === 0 || isSetComplete(state.sets.at(-1), state.sets.length - 1);
    if (!state.center || state.center.match.status !== 'live' || !currentComplete || state.sets.length >= bestOf() || hasWinner()) return;
    state.sets.push({ number: state.sets.length + 1, home: 0, away: 0 });
    try {
      await persistDraft(`เพิ่มเซต ${state.sets.length}`);
      showToast(`เพิ่มเซต ${state.sets.length} แล้ว`);
    } catch (error) {
      state.sets.pop();
      renderCenter();
      showToast(error.message, true);
    }
  }

  async function addPoint(side, button) {
    if (!state.sets.length || hasWinner()) return;
    const index = state.sets.length - 1;
    state.sets[index][side] += 1;
    await run(button, async () => {
      try {
        await persistDraft(`เซต ${index + 1} · ${side === 'home' ? state.center.match.team1_name : state.center.match.team2_name} +1`);
        showToast('เพิ่มคะแนนแล้ว');
      } catch (error) {
        state.sets[index][side] -= 1;
        renderCenter();
        throw error;
      }
    });
  }

  async function saveSet(index, button) {
    await run(button, async () => {
      const previous = { ...state.sets[index] };
      const row = readSetRow(index);
      state.sets[index] = row;
      try {
        await persistDraft(`แก้ไขคะแนนเซต ${index + 1}`);
        showToast(`บันทึกเซต ${index + 1} แล้ว`);
      } catch (error) {
        state.sets[index] = previous;
        renderSetRows();
        throw error;
      }
    });
  }

  async function deleteSet(index, button) {
    if (!confirm(`ลบเซต ${index + 1} หรือไม่? ประวัติเดิมจะยังอยู่ใน Snapshot`)) return;
    await run(button, async () => {
      const previous = state.sets.map(row => ({ ...row }));
      state.sets.splice(index, 1);
      state.sets.forEach((row, rowIndex) => { row.number = rowIndex + 1; });
      try {
        await persistDraft(`ลบเซต ${index + 1}`);
        showToast(`ลบเซต ${index + 1} แล้ว`);
      } catch (error) {
        state.sets = previous;
        renderSetRows();
        throw error;
      }
    });
  }

  async function persistDraft(note) {
    if (state.busy || !state.center) throw new Error('ระบบกำลังบันทึก กรุณารอสักครู่');
    state.busy = true;
    const matchId = String(state.center.match.id);
    $('#set-draft-status').textContent = 'กำลังบันทึกคะแนน...';
    syncAvailability(String(state.center.match.status));
    try {
      await request('matches.recordingDraft', {
        match_id: matchId,
        scoring_type: 'SET',
        result_data: { sets: state.sets },
        action_note: note,
        expected_revision: Number(state.center.match.action_revision || 0),
      });
      await loadMatch(matchId);
    } finally {
      state.busy = false;
      if (state.center) syncAvailability(String(state.center.match.status));
    }
  }

  function officialRows() {
    return $$('[data-result-set]').map((host, index) => ({
      number: index + 1,
      home: Number(host.querySelector('[data-result-side="home"]').value),
      away: Number(host.querySelector('[data-result-side="away"]').value),
    }));
  }

  async function finishMatch() {
    if (!state.center || state.center.match.status !== 'live' || !hasWinner()) return;
    if (!confirm(`ยืนยันผล ${state.center.match.team1_name} ${setWins().home} - ${setWins().away} ${state.center.match.team2_name} หรือไม่?`)) return;
    await run($('#set-finish'), async () => {
      const match = state.center.match;
      await request('matches.result', {
        match_id: match.id,
        scoring_type: 'SET',
        expected_revision: 0,
        result_data: { sets: state.sets },
      });
      await loadMatch(String(match.id));
      showToast('ยืนยันผลการแข่งขันเรียบร้อยแล้ว');
    });
  }

  async function updateOfficialResult() {
    if (!state.center?.recording_result) return;
    const reason = $('#set-result-reason').value.trim();
    if (!reason) return showToast('กรุณาระบุเหตุผลในการแก้ไขผลการแข่งขัน', true);
    const rows = officialRows();
    if (rows.some(row => !Number.isInteger(row.home) || row.home < 0 || !Number.isInteger(row.away) || row.away < 0)) return showToast('คะแนนต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป', true);
    if (!confirm('ยืนยันการแก้ไขผลการแข่งขันหรือไม่?')) return;
    await run($('#set-result-update'), async () => {
      const matchId = String(state.center.match.id);
      await request('matches.result', {
        match_id: matchId,
        scoring_type: 'SET',
        reason,
        expected_revision: Number(state.center.recording_result.revision_number || 0),
        result_data: { sets: rows },
      });
      await loadMatch(matchId);
      showToast('แก้ไขผลและสร้าง Revision ใหม่แล้ว');
    });
  }

  async function deleteOfficialResult() {
    if (!state.center?.recording_result) return;
    const reason = $('#set-result-reason').value.trim();
    if (!reason) return showToast('กรุณาระบุเหตุผลในการยกเลิกผลการแข่งขัน', true);
    if (!confirm('ยกเลิกผลทางการและเปิดแมตช์กลับมาแก้ไขหรือไม่?')) return;
    await run($('#set-result-delete'), async () => {
      const matchId = String(state.center.match.id);
      await request('matches.resultDelete', {
        match_id: matchId,
        reason,
        expected_revision: Number(state.center.recording_result.revision_number || 0),
      });
      await loadMatch(matchId);
      showToast('ยกเลิกผลทางการแล้ว สามารถแก้ไขแต่ละเซตต่อได้');
    });
  }

  async function request(action, payload = {}) {
    const response = await fetch(`${config.apiUrl}?action=${encodeURIComponent(action)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrf },
      body: JSON.stringify(payload),
    });
    const text = await response.text();
    let result;
    try { result = JSON.parse(text); }
    catch { throw new Error(`API ตอบกลับไม่ถูกต้อง (${response.status})`); }
    if (!response.ok || !result.ok) throw new Error(result.error || `API error ${response.status}`);
    return result.data;
  }

  async function run(control, task) {
    if (control) control.disabled = true;
    try { await task(); }
    catch (error) { showToast(error.message || 'ดำเนินการไม่สำเร็จ', true); }
    finally { if (state.center) syncAvailability(String(state.center.match.status)); }
  }

  let toastTimer;
  function showToast(message, error = false) {
    const toast = $('#set-toast');
    toast.textContent = message;
    toast.className = `toast${error ? ' error' : ''}`;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 4200);
  }
})();
