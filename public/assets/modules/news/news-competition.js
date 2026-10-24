// A single, database-backed competition selection is shared by the editor, AI and Banner Studio.
async function loadNewsReferences() {
  if (!app.departments.length) await loadAdminRefs();
  const [matches, settings] = await Promise.all([
    request('matches.forNews'),
    request('ai.models', {}, { silent: true }).catch(() => null),
  ]);
  app.newsMatches = matches;
  app.aiSettings = settings;
}

function newsMatches() {
  return [...new Map([...app.matches, ...app.newsMatches].map(row => [String(row.id), row])).values()];
}

function selectedNewsMatch() {
  return newsMatches().find(row => String(row.id) === $('#news-match-link').value) || null;
}

function newsMatchIsOfficial(match) {
  return Boolean(match && match.status === 'completed' && String(match.confirmed_at || '').trim());
}

function newsMatchPair(match) {
  const teams = [match.team1_id ? match.team1_name : '', match.team2_id ? match.team2_name : ''].filter(Boolean);
  return teams.length ? teams.join(' พบ ') : [match.sport_name, match.match_code].filter(Boolean).join(' · ');
}

function newsMatchFingerprint(match) {
  return JSON.stringify([match.id, match.status, match.confirmed_at, match.result_revision_number, match.score_label, match.team1_id, match.team2_id, match.scheduled_at]);
}

async function refreshNewsMatch(id) {
  const rows = await request('matches.forNews');
  app.newsMatches = rows;
  let match = rows.find(row => String(row.id) === String(id));
  if (!match) {
    const tournamentId = $('#news-form [name="tournament_id"]').value;
    const all = await request('matches.list', { tournament_id: tournamentId });
    match = all.find(row => String(row.id) === String(id));
    if (match) app.newsMatches.push(match);
  }
  if (!match) throw new Error('ไม่พบแมตช์อ้างอิง กรุณาเลือกข้อมูลการแข่งขันอีกครั้ง');
  renderNewsMatchSummary();
  return match;
}

function setNewsReferenceOptions(select, rows, emptyLabel, value, preserveMissing = false) {
  const unique = [...new Map(rows.filter(row => row.id !== null && row.id !== undefined && row.id !== '').map(row => [String(row.id), row])).values()];
  setOptions(select, unique, row => row.id, row => row.name, emptyLabel);
  if (value && !unique.some(row => String(row.id) === String(value)) && preserveMissing) {
    const option = new Option(`ข้อมูลเดิม #${value} (ไม่อยู่ในรายการที่โหลด)`, String(value));
    select.append(option);
  }
  select.value = [...select.options].some(option => option.value === String(value || '')) ? String(value || '') : '';
}

function syncNewsCompetitionOptions(source = '', record = null) {
  const form = $('#news-form');
  const keys = ['tournament_id', 'sport_id', 'team_id', 'match_id'];
  const values = Object.fromEntries(keys.map(key => [key, String(record ? record[key] || '' : form.elements[key].value)]));
  const all = newsMatches();
  if (['tournament', 'sport', 'team'].includes(source)) values.match_id = '';
  if (source === 'match' && values.match_id) {
    const match = all.find(row => String(row.id) === values.match_id);
    if (match) {
      values.tournament_id = String(match.tournament_id || '');
      values.sport_id = String(match.sport_id || '');
      if (values.team_id && ![match.team1_id, match.team2_id].map(String).includes(values.team_id)) values.team_id = '';
    }
  }
  const preserve = Boolean(record) || !source;
  setNewsReferenceOptions(form.elements.tournament_id, [...app.tournaments.map(row => ({ id: row.id, name: row.name })), ...all.map(row => ({ id: row.tournament_id, name: row.tournament_name }))], 'ไม่ระบุทัวร์นาเมนต์', values.tournament_id, preserve);
  const tournament = app.tournaments.find(row => String(row.id) === form.elements.tournament_id.value);
  const sportIds = (tournament?.sport_ids || []).map(String);
  let sports = [...app.sports.map(row => ({ id: row.id, name: row.name })), ...all.map(row => ({ id: row.sport_id, name: row.sport_name }))];
  if (form.elements.tournament_id.value) {
    const allowed = new Set([...sportIds, ...all.filter(row => String(row.tournament_id) === form.elements.tournament_id.value).map(row => String(row.sport_id))]);
    if (tournament?.sport_id) allowed.add(String(tournament.sport_id));
    if (allowed.size) sports = sports.filter(row => allowed.has(String(row.id)));
  }
  setNewsReferenceOptions(form.elements.sport_id, sports, 'ไม่ระบุกีฬา', values.sport_id, preserve);
  const tournamentId = form.elements.tournament_id.value;
  const sportId = form.elements.sport_id.value;
  const scoped = all.filter(row => (!tournamentId || String(row.tournament_id) === tournamentId) && (!sportId || String(row.sport_id) === sportId));
  const linkedTeams = scoped.flatMap(row => [
    { id: row.team1_id, name: row.team1_name }, { id: row.team2_id, name: row.team2_name },
  ]);
  const teams = app.teams.filter(row => (!tournamentId || String(row.tournament_id) === tournamentId) && (!sportId || String(row.sport_id) === sportId));
  setNewsReferenceOptions(form.elements.team_id, [...teams, ...linkedTeams], 'ไม่ระบุทีม', values.team_id, preserve);
  const teamId = form.elements.team_id.value;
  const matches = scoped.filter(row => !teamId || [row.team1_id, row.team2_id].map(String).includes(teamId));
  setNewsReferenceOptions(form.elements.match_id, matches.map(row => ({ id: row.id, name: row.display_label })), 'เลือกแมตช์อ้างอิง', values.match_id, preserve);
  renderNewsMatchSummary();
}

function renderNewsMatchSummary() {
  const match = selectedNewsMatch();
  const target = $('#news-match-summary');
  const source = $('#news-ai-source');
  $('#news-from-match-button').disabled = !match;
  if (!match) {
    target.innerHTML = '<p>เลือกแมตช์เพื่อดูทีม วันแข่งขัน และผลจากระบบ</p>';
    source.textContent = 'ยังไม่ได้เลือกแมตช์ — เปิดส่วนเชื่อมข้อมูลการแข่งขันเพื่อเลือกแหล่งข้อมูล';
    return;
  }
  const official = newsMatchIsOfficial(match);
  const score = match.status === 'completed' ? match.score_label : 'ยังไม่มีผลการแข่งขัน';
  target.innerHTML = `<div class="news-match-heading"><strong>${escapeHtml(newsMatchPair(match))}</strong><span class="news-result-state ${official ? 'confirmed' : 'unconfirmed'}">${icon(official ? 'badge-check' : 'clock-3')}${official ? 'ยืนยันผลแล้ว' : 'ยังไม่ยืนยันผล'}</span></div>
    <dl><div><dt>รายการ / กีฬา</dt><dd>${escapeHtml(match.tournament_name || '')} · ${escapeHtml(match.sport_name || '')}</dd></div><div><dt>วันแข่งขัน</dt><dd>${escapeHtml(match.scheduled_at || 'ยังไม่กำหนด')}</dd></div><div><dt>ผลจากระบบ</dt><dd>${escapeHtml(score)}${official ? '' : ' (ยังไม่ใช่ผลทางการ)'}</dd></div>${match.venue ? `<div><dt>สถานที่</dt><dd>${escapeHtml(match.venue)}</dd></div>` : ''}</dl>
    ${official ? '' : '<p class="news-match-warning">ผลยังไม่ได้รับการยืนยัน ร่างจากระบบจะไม่กล่าวอ้างผลเป็นทางการ และ AI แบบสรุปผลจะใช้ไม่ได้</p>'}`;
  source.textContent = `แหล่งข้อมูล: ${newsMatchPair(match)} · ${official ? 'ยืนยันผลแล้ว' : 'ยังไม่ยืนยันผล'}`;
  refreshIcons(target);
}

function buildNewsMatchDraft(match) {
  const official = newsMatchIsOfficial(match);
  const pair = newsMatchPair(match);
  const lines = [
    `การแข่งขัน${match.sport_name || ''} รายการ ${match.tournament_name || ''}`,
    pair,
    match.scheduled_at ? `วันแข่งขัน ${match.scheduled_at}` : '',
    match.venue ? `สถานที่ ${match.venue}` : '',
    official ? `ผลที่ยืนยันแล้ว: ${match.score_label}` : 'ผลยังไม่ได้รับการยืนยัน โปรดติดตามประกาศอย่างเป็นทางการจากฝ่ายจัดการแข่งขัน',
  ].filter(Boolean);
  return { title: `${official ? 'สรุปผล' : 'อัปเดต'}การแข่งขัน ${pair}`, excerpt: `${match.tournament_name || match.sport_name || ''} · ${official ? match.score_label : 'รอยืนยันผลการแข่งขัน'}`, body: lines.join('\n\n'), match_id: String(match.id), sourceFingerprint: newsMatchFingerprint(match), sport_name: match.sport_name, banner_title: official ? `${pair} · ${match.score_label}` : pair, banner_subtitle: match.tournament_name };
}

function bindNewsCompetition() {
  ['tournament_id', 'sport_id', 'team_id', 'match_id'].forEach(key => {
    $('#news-form').elements[key].addEventListener('change', () => {
      syncNewsCompetitionOptions(key.replace('_id', ''));
      markNewsDirty();
    });
  });
  bindTaskButton('#news-from-match-button', async () => {
    const id = $('#news-match-link').value;
    if (!id) throw new Error('กรุณาเลือกแมตช์ก่อนสร้างร่าง');
    const match = await refreshNewsMatch(id);
    if ($('#news-match-link').value !== id) throw new Error('แหล่งข้อมูลเปลี่ยนแล้ว กรุณาสร้างร่างอีกครั้ง');
    stageNewsDraft(buildNewsMatchDraft(match), 'ข้อมูลการแข่งขันจากระบบ');
  });
}
