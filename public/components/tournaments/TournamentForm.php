<form class="card form-card" id="tournament-form">
  <h2>รายการแข่งขัน</h2>
  <input type="hidden" name="id">
  <section data-create-step="1" class="tournament-form-part">
    <h3 class="create-only">ข้อมูลรายการ</h3>
    <label>ชื่อรายการ <input name="name" required></label>
    <div class="form-row">
      <label>โลโก้รายการแข่งขัน <input type="file" id="tournament-logo-file" accept="image/jpeg,image/png,image/webp"></label>
      <label>ลิงก์โลโก้ <input name="logo_url"></label>
    </div>
    <label>ปี / รุ่น (ถ้ามี) <input name="academic_year" placeholder="เช่น 2569"></label>
    <div class="form-row"><label>วันเริ่มการแข่งขัน <input type="date" name="starts_at"></label><label>วันสิ้นสุดการแข่งขัน <input type="date" name="ends_at"></label></div>
  </section>
  <section data-create-step="2" class="tournament-form-part">
    <h3 class="create-only">เลือกกีฬาที่จัด</h3>
    <p class="muted create-only">เลือกอย่างน้อย 1 กีฬา โดยใช้ข้อมูลกีฬากลางที่มีอยู่แล้ว</p>
    <fieldset class="sport-picker-fieldset">
      <legend>กีฬา <span class="required-mark">เลือกได้หลายรายการ</span></legend>
      <div class="sport-picker-toolbar"><label>ประเภท <select id="tournament-sport-type" aria-label="กรองประเภทกีฬา"><option value="">ทั้งหมด</option><option value="team">ทีม</option><option value="individual">บุคคล</option></select></label></div>
      <div class="sport-dropdown">
        <button class="dropdown-button" id="tournament-sports-toggle" type="button" aria-expanded="false" aria-controls="tournament-sports-options"><span id="tournament-sports-summary">เลือกกีฬา</span><i data-lucide="chevron-down"></i></button>
        <div id="tournament-sports-options" class="sport-picker" role="group" aria-label="เลือกกีฬาสำหรับทัวร์นาเมนท์" hidden></div>
      </div>
    </fieldset>
  </section>
  <section data-create-step="3" class="tournament-form-part">
    <h3 class="create-only">รูปแบบและยืนยัน</h3>
    <label>รูปแบบการแข่งขัน <select name="format"><option value="knockout">แพ้คัดออก</option><option value="round_robin">พบกันหมด</option><option value="group">แบ่งกลุ่ม</option><option value="group_knockout">แบ่งกลุ่มและแพ้คัดออก</option></select></label>
    <label class="tournament-edit-status">สถานะการแข่งขัน <select name="status"><option value="draft">ฉบับร่าง</option><option value="data_entry">กำลังเตรียมข้อมูล</option><option value="ready">พร้อมแข่งขัน</option><option value="in_progress">กำลังแข่งขัน</option><option value="completed">จบการแข่งขัน</option><option value="cancelled">ยกเลิก</option></select></label>
    <div id="tournament-create-review" class="create-only"></div>
    <p class="muted create-only">บันทึกเป็นฉบับร่างก่อน จากนั้นจัดการกติกา ทีม และตารางแข่งขันในหน้าจัดการรายการ</p>
  </section>
  <div class="actions">
    <button class="btn ghost create-only" id="tournament-create-previous" type="button"><i data-lucide="arrow-left"></i><span>ย้อนกลับ</span></button>
    <button class="btn primary create-only" id="tournament-create-next" type="button"><span>ถัดไป</span><i data-lucide="arrow-right"></i></button>
    <button class="btn primary" type="submit">บันทึกรายการ</button>
  </div>
</form>
