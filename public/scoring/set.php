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
  <title>บันทึกคะแนนเป็นเซต (SET)</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/scoring/set.css?v=' . (string) filemtime(__DIR__ . '/../assets/scoring/set.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php if (!$allowed): ?>
  <main class="access-panel">
    <h1>ไม่สามารถเปิดหน้าบันทึกคะแนนได้</h1>
    <p>กรุณาเข้าสู่ระบบด้วยบัญชีผู้ดูแลผลการแข่งขัน</p>
    <a class="button primary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">กลับไปเข้าสู่ระบบ</a>
  </main>
<?php else: ?>
  <header class="set-topbar">
    <a class="back-link" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="กลับศูนย์ควบคุมการแข่งขัน">←</a>
    <div class="brand-block"><strong>SPORT TOURNAMENT</strong><span>ศูนย์บันทึกผลการแข่งขัน</span></div>
    <div class="mode-mark"><span>รูปแบบ</span><strong>SET</strong></div>
    <div class="user-block"><span><?= htmlspecialchars((string) ($user['name'] ?: $user['email']), ENT_QUOTES, 'UTF-8') ?></span><a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">กลับหน้าหลัก</a></div>
  </header>

  <main class="set-page">
    <section class="page-intro">
      <div><h1>บันทึกผลแบบ SET</h1><p>สำหรับวอลเลย์บอล แบดมินตัน เทนนิส และกีฬาที่ตัดสินจากจำนวนเซต</p></div>
      <label class="match-picker">เลือกแมตช์
        <select id="set-match-select" aria-label="เลือกแมตช์รูปแบบคะแนนเป็นเซต"><option value="">กำลังโหลดรายการ...</option></select>
      </label>
    </section>

    <section id="set-empty" class="empty-panel">
      <h2>เลือกแมตช์เพื่อเริ่มบันทึกคะแนน</h2>
      <p>หน้านี้แสดงเฉพาะแมตช์ที่ใช้กติกา SET</p>
    </section>

    <div id="set-workspace" class="set-workspace" hidden>
      <section class="match-context">
        <div><span id="set-status" class="status">รอแข่งขัน</span><strong id="set-sport">กีฬา</strong></div>
        <div class="context-copy"><h2 id="set-round">รอบการแข่งขัน</h2><p id="set-meta">—</p></div>
        <div class="rule-box"><span>กติกา</span><strong id="set-best-of">Best of 3</strong><small id="set-rule-detail">ชนะ 2 เซต</small></div>
      </section>

      <section class="set-scoreboard">
        <article class="team-summary">
          <span>ทีมเหย้า</span><h2 id="set-team1-name">ทีม 1</h2><strong id="set-team1-wins">0</strong><small>เซต</small>
          <button type="button" class="point-button" data-point-side="home">+1 คะแนนในเซตปัจจุบัน</button>
        </article>
        <div class="versus"><span>ผลรวมเซต</span><b>VS</b><small id="set-current-label">ยังไม่มีเซต</small></div>
        <article class="team-summary away">
          <span>ทีมเยือน</span><h2 id="set-team2-name">ทีม 2</h2><strong id="set-team2-wins">0</strong><small>เซต</small>
          <button type="button" class="point-button" data-point-side="away">+1 คะแนนในเซตปัจจุบัน</button>
        </article>
      </section>

      <section class="control-strip">
        <button type="button" id="set-start" class="button primary">เริ่มการแข่งขัน</button>
        <button type="button" id="set-add" class="button">เพิ่มเซต</button>
        <button type="button" id="set-finish" class="button danger">ยืนยันผลและจบการแข่งขัน</button>
      </section>

      <section class="set-editor-panel">
        <header><div><h2>คะแนนแต่ละเซต</h2><p id="set-draft-status">ยังไม่มีแบบร่าง</p></div><strong id="set-count">0 เซต</strong></header>
        <div class="set-table-head"><span>เซต</span><span id="set-head-home">ทีม 1</span><span id="set-head-away">ทีม 2</span><span>จัดการ</span></div>
        <div id="set-rows" class="set-rows"></div>
      </section>

      <section id="set-result-admin" class="result-admin" hidden>
        <header><div><h2>แก้ไขหรือยกเลิกผลทางการ</h2><p>การแก้ไขจะสร้าง Revision ใหม่</p></div><strong id="set-result-revision">Revision 1</strong></header>
        <div id="set-result-rows" class="set-result-rows"></div>
        <label class="result-reason">เหตุผลในการแก้ไข/ยกเลิก<input id="set-result-reason" maxlength="250" placeholder="จำเป็นสำหรับการเปลี่ยนผลเดิม"></label>
        <div class="result-actions">
          <button type="button" id="set-result-update" class="button primary">บันทึกการแก้ไขผล</button>
          <button type="button" id="set-result-delete" class="button danger-outline">ยกเลิกผลและเปิดแมตช์แก้ไข</button>
        </div>
      </section>

      <section class="history-panel">
        <header><div><h2>ประวัติการบันทึก</h2><p>ทุกการเพิ่ม แก้ไข และลบเซตจะเก็บเป็น Snapshot</p></div><strong id="set-history-count">0 รายการ</strong></header>
        <div id="set-history" class="history-list"></div>
      </section>
    </div>
  </main>

  <div id="set-toast" class="toast" role="status" aria-live="polite" hidden></div>
  <script>
    window.PRSET = <?= json_encode([
        'apiUrl' => $appBase . '/api.php',
        'csrf' => Auth::csrfToken(),
        'backUrl' => $backUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="<?= htmlspecialchars($assetBase . '/scoring/set.js?v=' . (string) filemtime(__DIR__ . '/../assets/scoring/set.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
</body>
</html>
