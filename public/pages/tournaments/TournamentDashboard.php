<section id="tournament-dashboard-page" hidden>
  <div class="section-head tournament-dashboard-heading">
    <div><button class="btn ghost" type="button" id="tournament-back-to-list"><i data-lucide="arrow-left"></i><span>รายการทั้งหมด</span></button><h2 id="workflow-tournament-name">จัดการทัวร์นาเมนต์</h2><p class="muted" id="workflow-tournament-meta"></p></div>
    <div class="tournament-workspace-controls">
      <label>เปลี่ยนรายการ <select id="workflow-tournament-select" aria-label="เลือกทัวร์นาเมนต์"></select></label>
      <button class="btn tournament-only" id="tournament-edit-button" type="button"><i data-lucide="settings-2"></i><span>แก้ไขข้อมูลรายการ</span></button>
    </div>
  </div>
  <dl class="tournament-status-strip">
    <div><dt>การตั้งค่ารายการ</dt><dd id="tournament-setup-status"></dd></div>
    <div><dt>สถานะการแข่งขัน</dt><dd id="workflow-tournament-status"></dd></div>
    <div><dt>การเผยแพร่ผล</dt><dd id="tournament-publication-status"></dd></div>
  </dl>
  <div class="tournament-summary-line" id="tournament-summary-line"></div>
  <div class="tournament-readiness-bar" id="tournament-readiness-bar" aria-label="ความพร้อมของรายการ"></div>
  <nav class="tournament-tools" id="tournament-tools" aria-label="ส่วนจัดการทัวร์นาเมนต์">
    <button type="button" class="btn" data-tournament-section="overview">ภาพรวม</button>
    <button type="button" class="btn" data-tournament-section="sports">กีฬาและกติกา</button>
    <button type="button" class="btn" data-tournament-section="athletes">นักกีฬา</button>
    <button type="button" class="btn" data-tournament-section="teams">ทีมและสมาชิก</button>
    <button type="button" class="btn" data-tournament-section="schedule">ตารางแข่งขัน</button>
    <button type="button" class="btn" data-tournament-section="results">ผลการแข่งขัน</button>
  </nav>
  <div class="workflow-content">
    <header class="workflow-stage-head">
      <div><h3 id="workflow-step-title">ภาพรวมรายการ</h3><p id="workflow-step-description"></p></div>
      <button class="btn primary result-only" id="tournament-record-results" type="button"><i data-lucide="monitor-play"></i><span>เปิดหน้าบันทึกผล</span></button>
    </header>
    <div class="grid">
        <section class="data-section workflow-stage" id="workflow-sports-section" data-workflow-step="2" hidden>
          <div class="data-heading"><div><h2>กีฬาที่จัดในทัวร์นาเมนต์</h2><p class="muted">รายการนี้อ้างอิงจากข้อมูลทัวร์นาเมนต์ แก้ไขจากจุดเดียวเพื่อไม่ให้ข้อมูลซ้ำกัน</p></div><button class="btn" id="workflow-edit-sports" type="button"><i data-lucide="settings-2"></i><span>แก้ไขรายการกีฬา</span></button></div>
          <div class="table-wrap" id="workflow-sports-table"></div>
        </section>
        <form class="card form-card" id="tournament-sport-setting-form">
          <h2>กำหนดชุดกติกาที่ใช้</h2>
          <p class="muted">เลือกจากชุดกติกากลางของกีฬานี้ ระบบจะเก็บเวอร์ชันที่เลือกไว้กับทัวร์นาเมนต์และแมตช์</p>
          <input type="hidden" name="tournament_id">
          <input type="hidden" name="sport_id">
          <label>กีฬา <input name="sport_name" readonly></label>
          <label>ชุดกติกาที่ใช้ <select name="rule_version_id" id="workflow-rule-version" required></select></label>
          <label>สถานะการตั้งค่า <select name="status"><option value="pending">ยังตั้งค่าไม่ครบ</option><option value="ready">พร้อมใช้งาน</option><option value="locked">ล็อกการตั้งค่า</option></select></label>
          <label>หมายเหตุ <textarea name="notes" placeholder="รายละเอียดเฉพาะของกีฬานี้ในทัวร์นาเมนต์"></textarea></label>
          <div class="actions"><button class="btn primary" type="submit">ยืนยันชุดกติกาที่ใช้</button></div>
        </form>
        <section class="data-section workflow-stage" id="workflow-departments-section" data-workflow-step="4" hidden>
          <div class="data-heading"><div><h2>สังกัดที่เข้าร่วม (ถ้ามี)</h2><p class="muted">ใช้เมื่อรายการแข่งขันต้องการแยกทีมตามหน่วยงาน ขั้นตอนนี้เว้นได้</p></div></div>
          <div class="workflow-setting-layout">
            <div>
              <label>กีฬา <select id="workflow-department-sport"></select></label>
              <div class="department-checklist" id="workflow-department-options"></div>
              <div class="actions"><button class="btn primary" id="workflow-save-departments" type="button"><i data-lucide="check"></i><span>บันทึกสังกัดที่เข้าร่วม</span></button></div>
            </div>
            <aside class="workflow-summary" id="workflow-department-summary"></aside>
          </div>
        </section>
        <section class="data-section workflow-stage" id="workflow-readiness-section" data-workflow-step="8" hidden>
          <div class="data-heading"><div><h2>ความพร้อมรายกีฬา</h2><p class="muted">สถานะข้อมูลนี้แยกจากสถานะการแข่งขัน และไม่รวมงานประชาสัมพันธ์</p></div></div>
          <div id="workflow-readiness"></div>
        </section>
        <section class="card full workflow-stage" id="fixture-generator" data-workflow-step="9" hidden>
          <h2>จัดสายการแข่งขัน</h2>
          <p class="muted">สร้างคู่ตามรูปแบบที่กำหนดในทัวร์นาเมนต์ แล้วกำหนดวัน เวลา และสนามในแต่ละแมตช์ รอบที่ยังไม่ทราบทีมจะแสดงเป็นช่องรอทีมเพื่อให้เจ้าหน้าที่เติมภายหลัง</p>
          <div class="form-row"><label>กีฬา <select id="fixture-sport-select"></select></label><label>จำนวนกลุ่ม (สำหรับรอบแบ่งกลุ่ม) <input id="fixture-group-count" type="number" min="2" max="8" value="2"></label></div>
          <button class="btn primary" id="generate-fixtures-button" type="button">สร้างสายการแข่งขัน</button>
          <p class="muted" id="fixture-requirements" role="status"></p>
        </section>
        <form class="card form-card" id="match-form">
          <h2>แมตช์</h2>
          <input type="hidden" name="id">
          <label>รายการแข่ง <select name="tournament_id" id="match-tournament-select" required></select></label>
          <div class="form-row">
            <label>รอบ <input name="round_name" value="รอบแรก"></label>
            <label>เลขแมตช์ <input name="match_number"></label>
          </div>
          <div class="form-row">
            <label>ทีม 1 <select name="team1_id" id="match-team1-select"></select></label>
            <label>ทีม 2 <select name="team2_id" id="match-team2-select"></select></label>
          </div>
          <label>วันเวลา <input type="datetime-local" name="scheduled_at"></label>
          <label>สนาม <input name="venue"></label>
          <label>ผู้ตัดสิน <input name="referee"></label>
          <label>สถานะ <select name="status"><option value="scheduled">scheduled</option><option value="live">live</option><option value="completed">completed</option><option value="postponed">postponed</option><option value="cancelled">cancelled</option></select></label>
          <div class="actions"><button class="btn primary" type="submit">บันทึกแมตช์</button><button class="btn ghost reset-form" type="button">ล้างฟอร์ม</button></div>
        </form>
        <div class="card full"><h2>แมตช์ทั้งหมด</h2><div class="table-wrap" id="matches-table"></div></div>
        <section class="card full" id="match-assignment-panel" hidden>
          <h2>มอบหมายผู้รับผิดชอบแมตช์</h2>
          <p class="muted">Match Official และ Sport Manager ต้องได้รับมอบหมายรายแมตช์ก่อนบันทึกผล และยังต้องอยู่ในขอบเขตกีฬา/สังกัดที่ดูแล</p>
          <form id="match-assignment-form" class="event-entry">
            <label>แมตช์ <select id="assignment-match" required></select></label>
            <label>ผู้รับผิดชอบ <select id="assignment-user" required></select></label>
            <label>ทีมที่ดูแล <select id="assignment-team"><option value="">ทั้งแมตช์ตามขอบเขตสังกัด</option></select></label>
            <button class="btn primary" type="submit">เพิ่มผู้รับผิดชอบ</button>
          </form>
          <div class="table-wrap" id="match-assignments-table"></div>
        </section>
        <section class="data-section full"><div class="data-heading"><h2>ตารางคะแนน</h2><select id="standings-tournament-filter" aria-label="เลือกรายการแข่งขัน"></select></div><div class="table-wrap" id="standings-table"></div></section>

    </div>
  </div>
</section>
