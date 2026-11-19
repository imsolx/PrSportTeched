-- Phase 4 inventory: SELECT only. Run on an explicit database COPY using a
-- read-only connection, not through app/bootstrap.php (which can migrate/backfill).
-- Results are review queues, not permission to backfill or rewrite historical data.
-- SQLite, MySQL and PostgreSQL syntax; JSON validity/shape needs a separate check.

-- 1. Counts. These are not production counts until run against an approved copy.
SELECT 'rules' AS entity, COUNT(*) AS row_count FROM sport_rule_versions
UNION ALL SELECT 'tournament_sport_settings', COUNT(*) FROM tournament_sport_settings
UNION ALL SELECT 'matches', COUNT(*) FROM matches
UNION ALL SELECT 'results', COUNT(*) FROM match_results
UNION ALL SELECT 'result_revisions', COUNT(*) FROM match_result_revisions
UNION ALL SELECT 'entries', COUNT(*) FROM match_entries;

-- 2. Stored type inventory. Do not rename codes to the conceptual V2 catalog.
SELECT scoring_type, COUNT(*) AS row_count FROM sport_rule_versions GROUP BY scoring_type;
SELECT scoring_type, COUNT(*) AS row_count FROM match_results GROUP BY scoring_type;

-- 3. Missing/orphan match rules, including historical rows without an FK.
SELECT m.id AS match_id, m.rule_version_id, m.status
FROM matches m LEFT JOIN sport_rule_versions r ON r.id = m.rule_version_id
WHERE m.rule_version_id IS NULL OR r.id IS NULL;

-- 4. Cross-sport references: a plain rule-id FK would not prevent these.
SELECT m.id AS match_id, m.sport_id AS match_sport_id, r.sport_id AS rule_sport_id
FROM matches m JOIN sport_rule_versions r ON r.id = m.rule_version_id
WHERE m.sport_id IS NULL OR m.sport_id <> r.sport_id;
SELECT s.id AS setting_id, s.sport_id, s.rule_version_id
FROM tournament_sport_settings s LEFT JOIN sport_rule_versions r ON r.id = s.rule_version_id
WHERE r.id IS NULL OR s.sport_id <> r.sport_id;

-- 5. Missing event-level setting. Existing code may fall back to active sport rule.
SELECT m.id AS match_id, m.tournament_id, m.sport_id, m.rule_version_id
FROM matches m LEFT JOIN tournament_sport_settings s
  ON s.tournament_id = m.tournament_id AND s.sport_id = m.sport_id
WHERE s.id IS NULL;

-- 6. Pinned history may legitimately differ from the CURRENT setting. Review only.
SELECT m.id AS match_id, m.status, m.rule_version_id AS pinned_rule_id,
       s.rule_version_id AS current_setting_rule_id
FROM matches m JOIN tournament_sport_settings s
  ON s.tournament_id = m.tournament_id AND s.sport_id = m.sport_id
WHERE m.rule_version_id <> s.rule_version_id;

-- 7. Result/rule disagreement must not be repaired by guessing from sport names.
SELECT m.id AS match_id, r.scoring_type AS rule_type, x.scoring_type AS result_type
FROM matches m JOIN sport_rule_versions r ON r.id = m.rule_version_id
JOIN match_results x ON x.match_id = m.id
WHERE r.scoring_type <> x.scoring_type;

-- 8. Empty config/snapshot needs provenance and type-specific validation.
-- '{}' and '[]' are review candidates, not proof of invalid historical results.
SELECT id AS rule_id, scoring_type FROM sport_rule_versions
WHERE scoring_config IS NULL OR TRIM(scoring_config) IN ('', '{}', '[]');
SELECT id AS result_id, match_id, scoring_type FROM match_results
WHERE config_snapshot IS NULL OR TRIM(config_snapshot) IN ('', '{}', '[]');

-- 9. Some migrated SCORE rules remain eligible for name inference at runtime.
SELECT id AS rule_id, sport_id FROM sport_rule_versions
WHERE scoring_type = 'SCORE' AND (created_by IS NULL OR TRIM(created_by) IN ('', 'migration'));

-- 10. Completed entry workflows do NOT normally create match_results.
-- Separate them from legacy team-score records; neither group may be auto-converted.
SELECT m.id AS match_id,
       CASE WHEN EXISTS (SELECT 1 FROM match_entries e WHERE e.match_id = m.id)
            THEN 'ENTRY_WORKFLOW_REVIEW' ELSE 'LEGACY_RESULT_REVIEW' END AS review_group
FROM matches m LEFT JOIN match_results x ON x.match_id = m.id
WHERE m.status = 'completed' AND x.id IS NULL;

-- 11. Scheduled rows with evidence needing review before rule reassignment.
-- Entrant registration alone is not proof that competition has started.
SELECT m.id AS match_id, m.clock_status, m.clock_seconds, m.action_revision
FROM matches m WHERE m.status = 'scheduled' AND (
  m.clock_status <> 'stopped' OR m.clock_seconds > 0 OR m.action_revision > 0
  OR EXISTS (SELECT 1 FROM match_periods p WHERE p.match_id = m.id AND p.started_at IS NOT NULL AND p.started_at <> '')
  OR EXISTS (SELECT 1 FROM match_events e WHERE e.match_id = m.id)
  OR EXISTS (SELECT 1 FROM match_results x WHERE x.match_id = m.id)
  OR EXISTS (SELECT 1 FROM match_entries e WHERE e.match_id = m.id AND e.result_status <> 'PENDING')
);
