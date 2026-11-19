<?php
declare(strict_types=1);

/** Lifecycle checks use persisted evidence, not the status supplied by a browser. */
final class MatchScoringGuard
{
    public static function transaction(callable $write)
    {
        $pdo = Database::pdo();
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $value = $write();
            if ($owns) $pdo->commit();
            return $value;
        } catch (Throwable $error) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function lock(string $id): array
    {
        $pdo = Database::pdo();
        if (!$pdo->inTransaction()) throw new LogicException('Match lock requires a transaction');
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $query = $pdo->prepare('SELECT * FROM matches WHERE id = :id' . $suffix);
        $query->execute([':id' => $id]);
        $match = $query->fetch();
        if (!$match) throw new RuntimeException('ไม่พบแมตช์ที่เลือก');
        return $match;
    }

    public static function lockRule(string $id): array
    {
        $pdo = Database::pdo();
        if (!$pdo->inTransaction()) throw new LogicException('Rule lock requires a transaction');
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $query = $pdo->prepare('SELECT * FROM sport_rule_versions WHERE id = :id' . $suffix);
        $query->execute([':id' => $id]);
        $rule = $query->fetch();
        if (!$rule) throw new InvalidArgumentException('ไม่พบชุดกติกาที่เลือก');
        return $rule;
    }

    public static function untouched(array $match): bool
    {
        if ($match['status'] !== 'scheduled' || ($match['clock_status'] ?? 'stopped') !== 'stopped'
            || (int) ($match['clock_seconds'] ?? 0) !== 0 || (int) ($match['action_revision'] ?? 0) !== 0
            || (int) ($match['current_period'] ?? 1) !== 1 || (int) ($match['overtime_count'] ?? 0) !== 0
            || ($match['team1_score'] ?? null) !== null || ($match['team2_score'] ?? null) !== null
            || !empty($match['confirmed_at'])) return false;
        $sql = "SELECT
            (SELECT COUNT(*) FROM match_events WHERE match_id = :a) +
            (SELECT COUNT(*) FROM match_results WHERE match_id = :b) +
            (SELECT COUNT(*) FROM match_periods WHERE match_id = :c AND (status <> 'pending' OR (started_at IS NOT NULL AND started_at <> ''))) +
            (SELECT COUNT(*) FROM match_entries WHERE match_id = :d AND (result_status <> 'PENDING' OR revision_number > 0 OR verified_time_ms IS NOT NULL OR provisional_time_ms IS NOT NULL)) +
            (SELECT COUNT(*) FROM audit_logs WHERE entity = 'match' AND entity_id = :e AND action IN ('clock_start', 'result', 'result_update', 'result_delete', 'recording_draft', 'record_entry_result', 'confirm_entry_results')) AS evidence";
        $query = Database::pdo()->prepare($sql);
        $query->execute(array_fill_keys([':a', ':b', ':c', ':d', ':e'], (string) $match['id']));
        return (int) $query->fetchColumn() === 0;
    }

    public static function assertReconfiguration(array $current, array $next): void
    {
        foreach (['sport_id', 'tournament_id', 'rule_version_id', 'team1_id', 'team2_id'] as $key) {
            if ((string) ($current[$key] ?? '') !== (string) ($next[$key] ?? '') && !self::untouched($current)) {
                throw new RuntimeException('แมตช์เริ่มดำเนินการแล้ว ไม่สามารถเปลี่ยนกติกาหรือผู้เข้าแข่งขันได้');
            }
        }
        foreach (['team1_score', 'team2_score'] as $key) {
            if ((string) ($current[$key] ?? '') !== (string) ($next[$key] ?? '')) {
                throw new InvalidArgumentException('กรุณาบันทึกคะแนนผ่านหน้าบันทึกผลการแข่งขัน');
            }
        }
    }
}
