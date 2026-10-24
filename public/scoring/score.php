<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::currentUser();
$allowed = in_array((string) ($user['role'] ?? ''), [
    Auth::ROLE_SUPER_ADMIN,
    Auth::ROLE_ADMIN,
    Auth::ROLE_RESULT_OFFICER,
    Auth::ROLE_SPORT_HEAD,
], true);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$appBase = preg_replace('#/public/scoring$#', '', $scriptDir) ?? '';
$assetBase = $appBase . '/public/assets';
$backUrl = $appBase . '/?view=official';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>บันทึกคะแนนสะสม (SCORE)</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/tailwind.css?v=' . (string) filemtime(__DIR__ . '/../assets/tailwind.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/scoring/score.css?v=' . (string) filemtime(__DIR__ . '/../assets/scoring/score.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php if (!$allowed): ?>
  <main class="access-panel">
    <img src="<?= htmlspecialchars($assetBase . '/sports/football.svg', ENT_QUOTES, 'UTF-8') ?>" alt="">
    <h1>ไม่สามารถเปิดหน้าบันทึกคะแนนได้</h1>
    <p>กรุณาเข้าสู่ระบบด้วยบัญชีผู้ดูแลผลการแข่งขันก่อนใช้งานหน้านี้</p>
    <a class="button primary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">กลับไปเข้าสู่ระบบ</a>
  </main>
<?php else: ?>
  <header class="score-topbar">
    <a class="back-link" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="กลับศูนย์ควบคุมการแข่งขัน">←</a>
    <div class="brand-block">
      <strong>SPORT TOURNAMENT</strong>
      <span>ศูนย์บันทึกผลการแข่งขัน</span>
    </div>
    <div class="mode-mark"><span>รูปแบบ</span><strong>SCORE</strong></div>
    <div class="user-block"><span><?= htmlspecialchars((string) ($user['name'] ?: $user['email']), ENT_QUOTES, 'UTF-8') ?></span><a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">กลับหน้าหลัก</a></div>
  </header>

  <main class="score-page">
    <section class="page-intro">
      <div>
        <p>คะแนนสะสม</p>
        <h1>บันทึกผลแบบ SCORE</h1>
        <span>สำหรับฟุตบอล ฟุตซอล บาสเกตบอล และกีฬาที่ตัดสินจากทีมที่ได้คะแนนรวมมากกว่า</span>
      </div>
      <label class="match-picker">เลือกแมตช์
        <select id="score-match-select" aria-label="เลือกแมตช์รูปแบบคะแนนสะสม">
          <option value="">กำลังโหลดรายการ...</option>
        </select>
      </label>
    </section>

    <section id="score-empty" class="empty-panel">
      <img src="<?= htmlspecialchars($assetBase . '/sports/football.svg', ENT_QUOTES, 'UTF-8') ?>" alt="">
      <h2>เลือกแมตช์เพื่อเริ่มบันทึกคะแนน</h2>
      <p>หน้านี้จะแสดงเฉพาะแมตช์ที่ผูกกับกติกา SCORE เท่านั้น</p>
    </section>

    <div id="score-workspace" class="score-workspace" hidden>
      <section class="match-context">
        <div><span id="score-status" class="status">รอแข่งขัน</span><strong id="score-sport">กีฬา</strong></div>
        <div class="context-copy"><h2 id="score-round">รอบการแข่งขัน</h2><p id="score-meta">—</p></div>
        <div class="clock-box"><span>เวลาการแข่งขัน</span><strong id="score-clock">00:00</strong><small id="score-period">ช่วงที่ 1</small></div>
      </section>

      <section class="scoreboard" aria-label="กระดานคะแนน">
        <article class="team-panel" data-team-side="home">
          <img id="score-team1-logo" alt="">
          <div><span>ทีมเหย้า</span><h2 id="score-team1-name">ทีม 1</h2></div>
          <strong id="score-team1-value" class="team-score">0</strong>
          <button type="button" class="score-add" data-score-side="home">+1 คะแนน</button>
        </article>
        <div class="versus"><span>คะแนนรวม</span><b>VS</b><small>คะแนนมากกว่าชนะ</small></div>
        <article class="team-panel away" data-team-side="away">
          <img id="score-team2-logo" alt="">
          <div><span>ทีมเยือน</span><h2 id="score-team2-name">ทีม 2</h2></div>
          <strong id="score-team2-value" class="team-score">0</strong>
          <button type="button" class="score-add" data-score-side="away">+1 คะแนน</button>
        </article>
      </section>

      <section class="control-strip" aria-label="ควบคุมการแข่งขัน">
        <button type="button" id="score-clock-start" class="button primary">เริ่มการแข่งขัน</button>
        <button type="button" id="score-clock-pause" class="button">พักเวลา</button>
        <button type="button" id="score-clock-next" class="button">ช่วงถัดไป</button>
        <button type="button" id="score-clock-overtime" class="button">ต่อเวลา</button>
        <button type="button" id="score-finish" class="button danger">ยืนยันผลและจบการแข่งขัน</button>
      </section>

      <section id="score-result-admin" class="result-admin" hidden>
        <header>
          <div><span>ผลการแข่งขันทางการ</span><h2>แก้ไขหรือยกเลิกผลที่บันทึกแล้ว</h2></div>
          <strong id="score-result-revision">Revision 1</strong>
        </header>
        <div class="result-admin-grid">
          <label><span id="score-result-team1-label">ทีม 1</span><input id="score-result-team1" type="number" min="0" max="9999"></label>
          <label><span id="score-result-team2-label">ทีม 2</span><input id="score-result-team2" type="number" min="0" max="9999"></label>
          <label class="result-reason">เหตุผลในการแก้ไข/ยกเลิก<input id="score-result-reason" maxlength="250" placeholder="จำเป็นสำหรับการเปลี่ยนผลเดิม"></label>
          <button type="button" id="score-result-update" class="button primary">บันทึกการแก้ไขผล</button>
          <button type="button" id="score-result-delete" class="button danger-outline">ยกเลิกผลและเปิดแมตช์แก้ไข</button>
        </div>
        <p>การแก้ไขทุกครั้งจะสร้าง revision ใหม่ ส่วนการยกเลิกผลจะเปิดแมตช์กลับเป็นสถานะกำลังแข่งขันและเก็บเหตุผลไว้ใน Audit Log</p>
      </section>

      <div class="workspace-columns">
        <section class="entry-panel">
          <header><div><span>บันทึกเหตุการณ์</span><h2>เพิ่มคะแนน ฟาวล์ หรือเหตุการณ์ในสนาม</h2></div></header>
          <form id="score-event-form">
            <input type="hidden" name="event_id" id="score-event-id">
            <label>เหตุการณ์<select name="event_type" id="score-event-type" required></select></label>
            <label>ทีม<select name="team_id" id="score-event-team"></select></label>
            <label>นักกีฬา<select name="student_id" id="score-event-student"></select></label>
            <label>คะแนน<input name="points" id="score-event-points" type="number" step="1"></label>
            <label class="wide">หมายเหตุ<input name="note" maxlength="250" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></label>
            <label class="wide" id="score-event-edit-reason-row" hidden>เหตุผลในการแก้ไข<input name="edit_reason" maxlength="250" placeholder="ระบุเหตุผลเพื่อเก็บใน Audit Log"></label>
            <div class="event-form-actions wide">
              <button type="submit" id="score-event-submit" class="button primary">บันทึกเหตุการณ์</button>
              <button type="button" id="score-event-cancel-edit" class="button" hidden>ยกเลิกการแก้ไข</button>
            </div>
          </form>
          <p id="score-event-help" class="field-help">เริ่มการแข่งขันก่อนบันทึกเหตุการณ์</p>

          <details class="correction-panel">
            <summary>แก้ไขคะแนนรวม</summary>
            <div class="correction-grid">
              <label><span id="score-correct-team1-label">ทีม 1</span><input id="score-correct-team1" type="number" min="0" max="9999"></label>
              <label>เหตุผล<input id="score-correct-team1-reason" maxlength="250"></label>
              <button type="button" class="button" data-correct-side="home">บันทึกคะแนนทีมเหย้า</button>
              <label><span id="score-correct-team2-label">ทีม 2</span><input id="score-correct-team2" type="number" min="0" max="9999"></label>
              <label>เหตุผล<input id="score-correct-team2-reason" maxlength="250"></label>
              <button type="button" class="button" data-correct-side="away">บันทึกคะแนนทีมเยือน</button>
            </div>
          </details>
        </section>

        <section class="timeline-panel">
          <header><div><span>เหตุการณ์ล่าสุด</span><h2>ลำดับการแข่งขัน</h2></div><strong id="score-event-count">0 รายการ</strong></header>
          <div id="score-event-list" class="event-list"></div>
        </section>
      </div>
    </div>
  </main>

  <div id="score-toast" class="toast" role="status" aria-live="polite" hidden></div>
  <script>
    window.PRSCORE = <?= json_encode([
        'apiUrl' => $appBase . '/api.php',
        'csrf' => Auth::csrfToken(),
        'backUrl' => $backUrl,
        'assetBase' => $assetBase,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="<?= htmlspecialchars($assetBase . '/scoring/score.js?v=' . (string) filemtime(__DIR__ . '/../assets/scoring/score.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
</body>
</html>
