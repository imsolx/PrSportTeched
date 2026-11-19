/* Scoring configuration UI and legacy type compatibility (no scoring calculations).
 * Classic script: loaded before app.js; functions run after its shared $/$$ helpers exist.
 * Keep type inference/defaults unchanged until the server resolver phase.
 */
const bindScoringConfig = (() => {
  let bound = false;
  return () => {
    if (bound) return;
    $('#scoring-type')?.addEventListener('change', () => syncScoringConfigFields(true));
    syncScoringConfigFields();
    bound = true;
  };
})();

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

// Recording routes use server metadata; rule/name inference is never a routing fallback.
function detectScoringType(match, rule) {
  return String(match?.resolved_scoring_type || '');
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

