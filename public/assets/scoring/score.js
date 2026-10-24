(() => {
  'use strict';

  const config = window.PRSCORE;
  const state = { matches: [], rules: [], center: null, timer: null, editingEventId: '' };
  const $ = selector => document.querySelector(selector);
  const $$ = selector => [...document.querySelectorAll(selector)];
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
  const requestId = () => globalThis.crypto?.randomUUID?.() || `score_${Date.now()}_${Math.random().toString(36).slice(2)}`;

  document.addEventListener('DOMContentLoaded', init);

  async function init() {
    bindControls();
    try {
      const [matches, rules] = await Promise.all([request('matches.list'), request('sportRules.list', { sport_id: '' })]);
      state.matches = matches;
      state.rules = rules;
      renderMatchOptions();
      const matchId = new URLSearchParams(location.search).get('match_id') || '';
      if (matchId) {
        $('#score-match-select').value = matchId;
        await loadMatch(matchId);
      }
    } catch (error) {
      showToast(error.message, true);
    }
  }

  function bindControls() {
    $('#score-match-select').addEventListener('change', event => loadMatch(event.target.value));
    $('#score-clock-start').addEventListener('click', () => controlClock('start'));
    $('#score-clock-pause').addEventListener('click', () => controlClock('pause'));
    $('#score-clock-next').addEventListener('click', () => controlClock('next_period'));
    $('#score-clock-overtime').addEventListener('click', () => controlClock('overtime'));
    $('#score-finish').addEventListener('click', finishMatch);
    $$('.score-add').forEach(button => button.addEventListener('click', () => addPoint(button.dataset.scoreSide, button)));
    $('#score-event-form').addEventListener('submit', saveEvent);
    $('#score-event-cancel-edit').addEventListener('click', resetEventEditor);
    $('#score-event-type').addEventListener('change', syncEventDefinition);
    $('#score-event-team').addEventListener('change', syncAthletes);
    $$('[data-correct-side]').forEach(button => button.addEventListener('click', () => correctScore(button.dataset.correctSide, button)));
    $('#score-result-update').addEventListener('click', updateOfficialResult);
    $('#score-result-delete').addEventListener('click', deleteOfficialResult);
  }

  function renderMatchOptions() {
    const ruleTypes = new Map(state.rules.map(rule => [String(rule.id), String(rule.scoring_type || '').toUpperCase()]));
    const scoreMatches = state.matches.filter(match => ruleTypes.get(String(match.rule_version_id)) === 'SCORE');
    const select = $('#score-match-select');
    const groups = [['live', 'กำลังแข่งขัน'], ['scheduled', 'รอแข่งขัน'], ['completed', 'จบแล้ว']];
    select.innerHTML = `<option value="">เลือกแมตช์ SCORE</option>${groups.map(([status, label]) => {
      const rows = scoreMatches.filter(match => String(match.status) === status);
      return rows.length ? `<optgroup label="${label}">${rows.map(match =>
        `<option value="${escapeHtml(match.id)}">${escapeHtml(matchOptionLabel(match, label))}</option>`
      ).join('')}</optgroup>` : '';
    }).join('')}`;
    if (!scoreMatches.length) select.innerHTML = '<option value="">ยังไม่มีแมตช์รูปแบบ SCORE</option>';
  }

  function matchOptionLabel(match, statusLabel = '') {
    const code = match.match_code || `Match #${match.id}`;
    const teams = `${match.team1_name || 'ยังไม่กำหนดทีม'} พบ ${match.team2_name || 'ยังไม่กำหนดทีม'}`;
    const context = [match.sport_name, match.round_name, match.scheduled_at, match.venue].filter(Boolean).join(' · ');
    return `[${statusLabel}] ${code} · ${teams}${context ? ` · ${context}` : ''}`;
  }

  async function loadMatch(matchId) {
    clearInterval(state.timer);
    state.timer = null;
    if (!matchId) {
      state.center = null;
      $('#score-empty').hidden = false;
      $('#score-workspace').hidden = true;
      syncUrl('');
      return;
    }
    try {
      const data = await request('matches.center', { match_id: matchId });
      const type = String(data.resolved_scoring_type || data.rule?.scoring_type || '').toUpperCase();
      if (type !== 'SCORE') throw new Error(`แมตช์นี้ใช้รูปแบบ ${type || 'อื่น'} ไม่ใช่ SCORE`);
      state.center = data;
      $('#score-empty').hidden = true;
      $('#score-workspace').hidden = false;
      if (![...$('#score-match-select').options].some(option => option.value === String(matchId))) {
        const option = document.createElement('option');
        option.value = String(matchId);
        option.textContent = matchOptionLabel(data.match, data.match.status_label || data.match.status || '');
        $('#score-match-select').append(option);
      }
      $('#score-match-select').value = String(matchId);
      syncUrl(matchId);
      renderCenter();
    } catch (error) {
      state.center = null;
      $('#score-empty').hidden = false;
      $('#score-workspace').hidden = true;
      showToast(error.message, true);
    }
  }

  function syncUrl(matchId) {
    const url = new URL(location.href);
    if (matchId) url.searchParams.set('match_id', matchId);
    else url.searchParams.delete('match_id');
    history.replaceState({}, '', url);
  }

  function renderCenter() {
    const { match, rule, events = [] } = state.center;
    const status = String(match.status || 'scheduled');
    $('#score-status').textContent = match.status_label || ({ scheduled: 'รอแข่งขัน', live: 'กำลังแข่งขัน', completed: 'จบแล้ว' }[status] || status);
    $('#score-status').className = `status ${status}`;
    $('#score-sport').textContent = match.sport_name || 'กีฬา';
    $('#score-round').textContent = match.round_name || 'รอบการแข่งขัน';
    $('#score-meta').textContent = [match.tournament_name, match.match_code, match.scheduled_at, match.venue].filter(Boolean).join(' · ') || '—';
    $('#score-period').textContent = `ช่วงที่ ${Number(match.current_period || 1)}`;
    setClock(Number(match.clock_seconds || 0));
    setTeam('1', match.team1_name || 'ทีม 1', match.team1_score || 0, match.team1_logo_url || match.team1_department_logo_url);
    setTeam('2', match.team2_name || 'ทีม 2', match.team2_score || 0, match.team2_logo_url || match.team2_department_logo_url);
    $('#score-correct-team1').value = Number(match.team1_score || 0);
    $('#score-correct-team2').value = Number(match.team2_score || 0);
    $('#score-correct-team1-label').textContent = match.team1_name || 'ทีม 1';
    $('#score-correct-team2-label').textContent = match.team2_name || 'ทีม 2';
    renderEventOptions(rule.event_types_data || []);
    renderTeamOptions();
    renderEvents(events);
    renderResultAdmin();
    resetEventEditor();
    syncAvailability(status);
    if (match.clock_status === 'running') startLocalClock();
  }

  function setTeam(side, name, score, logo) {
    $(`#score-team${side}-name`).textContent = name;
    $(`#score-team${side}-value`).textContent = Number(score || 0);
    const image = $(`#score-team${side}-logo`);
    image.src = logo || `${config.assetBase}/sports/general.svg`;
    image.alt = `ตราสัญลักษณ์ ${name}`;
    image.onerror = () => { image.src = `${config.assetBase}/sports/general.svg`; };
  }

  function renderEventOptions(definitions) {
    const selected = $('#score-event-type').value;
    $('#score-event-type').innerHTML = `<option value="">เลือกเหตุการณ์</option>${definitions.map(item =>
      `<option value="${escapeHtml(item.code)}">${escapeHtml(item.label)}</option>`
    ).join('')}`;
    if (definitions.some(item => item.code === selected)) $('#score-event-type').value = selected;
    syncEventDefinition();
  }

  function renderTeamOptions() {
    const match = state.center.match;
    const selected = $('#score-event-team').value;
    $('#score-event-team').innerHTML = `<option value="">ไม่ระบุทีม</option>
      <option value="${escapeHtml(match.team1_id || '')}">${escapeHtml(match.team1_name || 'ทีม 1')}</option>
      <option value="${escapeHtml(match.team2_id || '')}">${escapeHtml(match.team2_name || 'ทีม 2')}</option>`;
    if ([String(match.team1_id), String(match.team2_id)].includes(selected)) $('#score-event-team').value = selected;
    syncAthletes();
  }

  function syncEventDefinition() {
    if (!state.center) return;
    const definition = (state.center.rule.event_types_data || []).find(item => item.code === $('#score-event-type').value);
    $('#score-event-points').value = definition ? Number(definition.points || 0) : 0;
    const participant = definition?.participant || 'optional';
    const editingItem = state.editingEventId ? state.center.events.find(item => String(item.id) === String(state.editingEventId)) : null;
    const quickTeamScore = Boolean(editingItem?.metadata?.quick_team_score);
    $('#score-event-team').required = participant === 'team' || participant === 'athlete' || Number(definition?.points || 0) !== 0;
    $('#score-event-student').required = participant === 'athlete' && !quickTeamScore;
    $('#score-event-help').textContent = participant === 'athlete' && !quickTeamScore
      ? 'เหตุการณ์นี้ต้องเลือกทีมและนักกีฬา'
      : participant === 'team' || quickTeamScore ? 'เหตุการณ์นี้ต้องเลือกทีม' : 'สามารถระบุทีมและนักกีฬาเพิ่มเติมได้';
  }

  function syncAthletes() {
    if (!state.center) return;
    const match = state.center.match;
    const teamId = $('#score-event-team').value;
    const rows = String(teamId) === String(match.team1_id) ? state.center.team1_members :
      String(teamId) === String(match.team2_id) ? state.center.team2_members : [];
    $('#score-event-student').innerHTML = `<option value="">ไม่ระบุนักกีฬา</option>${(rows || []).map(item =>
      `<option value="${escapeHtml(item.student_id)}">${escapeHtml(item.shirt_number || '-')} · ${escapeHtml(item.full_name || '-')}</option>`
    ).join('')}`;
    syncEventDefinition();
  }

  function syncAvailability(status) {
    const match = state.center.match;
    const live = status === 'live';
    const completed = status === 'completed';
    $$('.score-add').forEach(button => { button.disabled = !live; });
    $$('#score-event-form input, #score-event-form select, #score-event-form button').forEach(control => { control.disabled = !live; });
    $$('[data-correct-side]').forEach(button => { button.disabled = !live; });
    $$('.correction-grid input').forEach(input => { input.disabled = !live; });
    $('#score-clock-start').disabled = live || completed;
    $('#score-clock-pause').disabled = completed || match.clock_status !== 'running';
    $('#score-clock-next').disabled = !live;
    $('#score-clock-overtime').disabled = !live || !Number(state.center.rule.overtime_enabled);
    $('#score-finish').disabled = !live;
  }

  function renderResultAdmin() {
    const result = state.center?.recording_result;
    const panel = $('#score-result-admin');
    panel.hidden = !result;
    if (!result) return;
    const match = state.center.match;
    $('#score-result-revision').textContent = `Revision ${Number(result.revision_number || 1)}`;
    $('#score-result-team1-label').textContent = match.team1_name || 'ทีม 1';
    $('#score-result-team2-label').textContent = match.team2_name || 'ทีม 2';
    $('#score-result-team1').value = Number(match.team1_score || 0);
    $('#score-result-team2').value = Number(match.team2_score || 0);
    $('#score-result-reason').value = '';
  }

  async function addPoint(side, button) {
    if (!state.center || button.disabled) return;
    const { match, rule } = state.center;
    const definition = (rule.event_types_data || []).find(item => Number(item.points || 0) > 0);
    if (!definition) return showToast('ชุดกติกานี้ไม่มีเหตุการณ์ที่เพิ่มคะแนน', true);
    const teamId = side === 'home' ? match.team1_id : match.team2_id;
    await run(button, async () => {
      await request('matches.event', {
        request_id: requestId(), expected_revision: Number(match.action_revision || 0), match_id: match.id,
        event_type: definition.code, team_id: teamId, student_id: '', points: 1, added_time: 0,
        note: '', quick_team_score: true, elapsed_seconds: match.clock_seconds, period_number: match.current_period,
      });
      await loadMatch(String(match.id));
      showToast('เพิ่ม 1 คะแนนแล้ว');
    });
  }

  async function saveEvent(event) {
    event.preventDefault();
    if (!state.center) return;
    const form = event.currentTarget;
    const match = state.center.match;
    const data = Object.fromEntries(new FormData(form).entries());
    const editingId = state.editingEventId;
    const original = editingId ? state.center.events.find(item => String(item.id) === String(editingId)) : null;
    await run(form.querySelector('[type=submit]'), async () => {
      if (editingId) {
        await request('matches.eventUpdate', {
          ...data, id: editingId, expected_revision: Number(match.action_revision || 0),
          elapsed_seconds: Number(original?.elapsed_seconds || 0), period_number: Number(original?.period_number || 1),
          added_time: Number(original?.added_time || 0),
        });
      } else {
        await request('matches.event', {
          ...data, request_id: requestId(), expected_revision: Number(match.action_revision || 0), match_id: match.id,
          elapsed_seconds: match.clock_seconds, period_number: match.current_period, added_time: 0,
        });
      }
      await loadMatch(String(match.id));
      showToast(editingId ? 'แก้ไขเหตุการณ์แล้ว' : 'บันทึกเหตุการณ์แล้ว');
    });
  }

  function startEditEvent(id) {
    const item = state.center?.events?.find(event => String(event.id) === String(id));
    if (!item || Number(item.is_voided) || item.metadata?.score_correction) return;
    state.editingEventId = String(id);
    const form = $('#score-event-form');
    $('#score-event-id').value = String(id);
    $('#score-event-type').value = item.event_type || '';
    $('#score-event-team').value = item.team_id || '';
    syncAthletes();
    $('#score-event-student').value = item.student_id || '';
    $('#score-event-points').value = Number(item.points || 0);
    form.elements.note.value = item.note || '';
    form.elements.edit_reason.value = '';
    $('#score-event-edit-reason-row').hidden = false;
    $('#score-event-cancel-edit').hidden = false;
    $('#score-event-submit').textContent = 'บันทึกการแก้ไขเหตุการณ์';
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function resetEventEditor() {
    state.editingEventId = '';
    const form = $('#score-event-form');
    form.reset();
    $('#score-event-id').value = '';
    $('#score-event-edit-reason-row').hidden = true;
    $('#score-event-cancel-edit').hidden = true;
    $('#score-event-submit').textContent = 'บันทึกเหตุการณ์';
    if (state.center) {
      renderEventOptions(state.center.rule.event_types_data || []);
      renderTeamOptions();
      syncAvailability(String(state.center.match.status));
    }
  }

  async function correctScore(side, button) {
    if (!state.center) return;
    const match = state.center.match;
    const suffix = side === 'home' ? 'team1' : 'team2';
    const reason = $(`#score-correct-${suffix}-reason`).value.trim();
    if (!reason) return showToast('กรุณาระบุเหตุผลในการแก้ไขคะแนน', true);
    await run(button, async () => {
      await request('matches.scoreCorrection', {
        match_id: match.id, team_id: match[`${suffix}_id`], target_score: $(`#score-correct-${suffix}`).value,
        reason, expected_revision: Number(match.action_revision || 0),
      });
      await loadMatch(String(match.id));
      showToast('แก้ไขคะแนนและบันทึกเหตุผลแล้ว');
    });
  }

  async function controlClock(action) {
    if (!state.center) return;
    const match = state.center.match;
    await run($(`#score-clock-${action === 'next_period' ? 'next' : action}`) || $('#score-clock-start'), async () => {
      await request('matches.clock', { match_id: match.id, clock_action: action, clock_seconds: match.clock_seconds, period_number: match.current_period });
      await loadMatch(String(match.id));
    });
  }

  async function finishMatch() {
    if (!state.center || state.center.match.status !== 'live') return;
    const match = state.center.match;
    const winner = Number(match.team1_score || 0) === Number(match.team2_score || 0) ? 'ผลเสมอ' :
      Number(match.team1_score || 0) > Number(match.team2_score || 0) ? match.team1_name : match.team2_name;
    if (!confirm(`ยืนยันผล ${match.team1_name} ${match.team1_score || 0} - ${match.team2_score || 0} ${match.team2_name}\nผู้ชนะ: ${winner}`)) return;
    await run($('#score-finish'), async () => {
      if (match.clock_status === 'running') {
        await request('matches.clock', { match_id: match.id, clock_action: 'pause', clock_seconds: match.clock_seconds, period_number: match.current_period });
      }
      const detail = await request('matches.resultDetail', { match_id: match.id });
      await request('matches.result', {
        match_id: match.id, scoring_type: 'SCORE', expected_revision: Number(detail.result?.revision_number || 0),
        result_data: { regular_score: { home: Number(match.team1_score || 0), away: Number(match.team2_score || 0) } },
      });
      await loadMatch(String(match.id));
      showToast('ยืนยันผลการแข่งขันเรียบร้อยแล้ว');
    });
  }

  async function updateOfficialResult() {
    if (!state.center?.recording_result) return;
    const reason = $('#score-result-reason').value.trim();
    if (!reason) return showToast('กรุณาระบุเหตุผลในการแก้ไขผลการแข่งขัน', true);
    const match = state.center.match;
    const home = Number($('#score-result-team1').value);
    const away = Number($('#score-result-team2').value);
    if (!Number.isInteger(home) || home < 0 || !Number.isInteger(away) || away < 0) {
      return showToast('คะแนนต้องเป็นจำนวนเต็มตั้งแต่ 0 ขึ้นไป', true);
    }
    if (!confirm(`ยืนยันแก้ไขผลเป็น ${home} - ${away} หรือไม่?`)) return;
    await run($('#score-result-update'), async () => {
      await request('matches.result', {
        match_id: match.id, scoring_type: 'SCORE', reason,
        expected_revision: Number(state.center.recording_result.revision_number || 0),
        result_data: { regular_score: { home, away } },
      });
      await loadMatch(String(match.id));
      showToast('แก้ไขผลการแข่งขันและสร้าง revision ใหม่แล้ว');
    });
  }

  async function deleteOfficialResult() {
    if (!state.center?.recording_result) return;
    const reason = $('#score-result-reason').value.trim();
    if (!reason) return showToast('กรุณาระบุเหตุผลในการยกเลิกผลการแข่งขัน', true);
    if (!confirm('ยกเลิกผลทางการและเปิดแมตช์กลับมาแก้ไขหรือไม่? คะแนนและเหตุการณ์เดิมจะยังคงอยู่')) return;
    const matchId = String(state.center.match.id);
    await run($('#score-result-delete'), async () => {
      await request('matches.resultDelete', {
        match_id: matchId, reason, expected_revision: Number(state.center.recording_result.revision_number || 0),
      });
      await loadMatch(matchId);
      showToast('ยกเลิกผลทางการแล้ว สามารถแก้ไขคะแนนและเหตุการณ์ต่อได้');
    });
  }

  function renderEvents(events) {
    const rows = events || [];
    const activeCount = rows.filter(item => !Number(item.is_voided)).length;
    $('#score-event-count').textContent = rows.length === activeCount ? `${activeCount} รายการ` : `${activeCount} ใช้งาน · ${rows.length - activeCount} ยกเลิก`;
    $('#score-event-list').innerHTML = rows.length ? rows.map(item => {
      const seconds = Number(item.elapsed_seconds || 0);
      const label = item.metadata?.score_correction ? `แก้คะแนน ${item.metadata.from} → ${item.metadata.to}` :
        (state.center.rule.event_types_data || []).find(def => def.code === item.event_type)?.label || item.event_type;
      const voided = Number(item.is_voided) === 1;
      const editable = state.center.match.status === 'live' && !voided && !item.metadata?.score_correction;
      return `<article class="event-row${voided ? ' voided' : ''}">
        <time>${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}</time>
        <div><strong>${escapeHtml(label)}${item.metadata?.edited ? ' · แก้ไขแล้ว' : ''}</strong><span>${escapeHtml([item.team_name, item.student_name].filter(Boolean).join(' · '))}</span>${item.note ? `<small>${escapeHtml(item.note)}</small>` : ''}</div>
        <b>${Number(item.points) ? `${Number(item.points) > 0 ? '+' : ''}${escapeHtml(item.points)}` : ''}</b>
        ${voided ? '<span class="event-voided-label">ยกเลิกแล้ว</span>' : editable ? `<span class="event-actions"><button type="button" data-edit-event="${escapeHtml(item.id)}">แก้ไข</button><button class="danger-link" type="button" data-void-event="${escapeHtml(item.id)}">ยกเลิก</button></span>` : '<span></span>'}
      </article>`;
    }).join('') : '<div class="event-empty">ยังไม่มีเหตุการณ์ในการแข่งขัน</div>';
    $$('[data-edit-event]').forEach(button => button.addEventListener('click', () => startEditEvent(button.dataset.editEvent)));
    $$('[data-void-event]').forEach(button => button.addEventListener('click', () => voidEvent(button.dataset.voidEvent, button)));
  }

  async function voidEvent(id, button) {
    if (!confirm('ยกเลิกเหตุการณ์นี้และคำนวณคะแนนใหม่หรือไม่?')) return;
    const match = state.center.match;
    await run(button, async () => {
      await request('matches.eventVoid', { id, expected_revision: Number(match.action_revision || 0) });
      await loadMatch(String(match.id));
      showToast('ยกเลิกเหตุการณ์แล้ว');
    });
  }

  function setClock(seconds) {
    if (!state.center) return;
    state.center.match.clock_seconds = seconds;
    $('#score-clock').textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
  }

  function startLocalClock() {
    clearInterval(state.timer);
    state.timer = setInterval(() => setClock(Number(state.center?.match?.clock_seconds || 0) + 1), 1000);
  }

  async function request(action, payload = {}) {
    const response = await fetch(`${config.apiUrl}?action=${encodeURIComponent(action)}`, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrf }, body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({ ok: false, error: 'API ตอบกลับไม่ถูกต้อง' }));
    if (!response.ok || !result.ok) throw new Error(result.error || `API error ${response.status}`);
    return result.data;
  }

  async function run(control, task) {
    if (control) control.disabled = true;
    try { await task(); }
    catch (error) { showToast(error.message || 'ดำเนินการไม่สำเร็จ', true); }
    finally { if (control && state.center) syncAvailability(String(state.center.match.status)); }
  }

  let toastTimer;
  function showToast(message, error = false) {
    const toast = $('#score-toast');
    toast.textContent = message;
    toast.className = `toast${error ? ' error' : ''}`;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 4200);
  }
})();
