/* Match selection and navigation only. SCORE/SET recording stays in score.js/set.js.
 * Classic script: loaded after scoring-config.js and before app.js.
 * Uses the live app state, request/loadAdminRefs, showView, workspace labels,
 * matchStatusClass and escaping/toast helpers; never copies state or binds on load.
 * Public function names remain available to app.js and the tournament module.
 */
const bindScoringSelector = (() => {
  let bound = false;
  return () => {
    if (bound) return;
    $('#official-refresh').addEventListener('click', () => loadOfficialCenter(true));
    $('#official-back-button').addEventListener('click', () => showView(app.allowedViews.includes('dashboard') ? 'dashboard' : 'official'));
    bound = true;
  };
})();

async function openMatchCenter(matchId) {
  if (!matchId) return;
  let center;
  try {
    center = await request('matches.center', { match_id: String(matchId) });
  } catch (error) {
    toast(error.message, true);
    return;
  }
  const match = { ...center.match, resolved_scoring_type: center.resolved_scoring_type, recorder_page: center.recorder_page };
  const rule = center.rule;
  const dedicatedUrl = dedicatedRecordingPageUrl(match, rule);
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
      const scoringType = detectScoringType(row, rule) || 'ไม่พร้อมบันทึก';
      const dedicatedUrl = dedicatedRecordingPageUrl(row, rule);
      return `
      <div class="official-match-entry">
      <button class="official-match-item ${String(row.id) === String(selectedId) ? 'selected' : ''}" type="button" data-official-match="${escapeAttr(row.id)}">
        <span class="official-match-topline"><span class="official-match-state status-${matchStatusClass(row.status)}">${escapeHtml(row.status_label || workspace.labels[row.status] || row.status)}</span><b class="official-match-mode">${escapeHtml(scoringType)} · กติกา v${escapeHtml(rule?.version_number || '-')}</b></span>
        ${row.team1_id && row.team2_id && !isRace
          ? `<strong>${escapeHtml(row.team1_name || '-')} <b>${escapeHtml(displayParts[0] ?? row.team1_score ?? '-')}</b></strong><strong>${escapeHtml(row.team2_name || '-')} <b>${escapeHtml(displayParts[1] ?? row.team2_score ?? '-')}</b></strong>`
          : `<strong>${escapeHtml(row.sport_name || 'การแข่งขันจับเวลา')}</strong><span>${escapeHtml(row.round_name || '')}</span>`}
        <small>${escapeHtml([row.match_code || `Match #${row.id}`, row.round_name, row.scheduled_at, row.venue].filter(Boolean).join(' · '))}</small>
        ${row.scoring_error ? `<small>${escapeHtml(row.scoring_error)}</small>` : ''}
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
  if (!page || match?.scoring_error || page !== match?.recorder_page) return '';
  return page ? scoreRecordingPageUrl(match.id, page) : '';
}

function scoreRecordingPageUrl(matchId, page = 'score.php') {
  const pageUrl = new URL(`${window.PRSPORT.scoringBaseUrl}/${page}`, window.location.href);
  pageUrl.searchParams.set('match_id', String(matchId));
  return pageUrl.toString();
}

