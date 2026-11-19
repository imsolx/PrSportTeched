<?php
declare(strict_types=1);

require_once __DIR__ . '/Modules/Scoring/ScoringSupport.php';
require_once __DIR__ . '/Modules/Scoring/MatchResultOperations.php';
require_once __DIR__ . '/Modules/Scoring/MatchRecordingOperations.php';
require_once __DIR__ . '/Modules/Scoring/MatchEventOperations.php';
require_once __DIR__ . '/Modules/Scoring/MatchEntryOperations.php';

/**
 * Scoring application service. AppService retains the existing API facade.
 * Operations share Database::pdo(), so all writes, revisions and audits retain
 * their transaction boundaries. ScoringResolver owns rule selection; score
 * calculations remain in the existing ScoringEngine.
 */
final class ScoringService
{
    use ScoringSupport;
    use MatchResultOperations;
    use MatchRecordingOperations;
    use MatchEventOperations;
    use MatchEntryOperations;
}
