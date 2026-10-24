<form id="news-form" class="news-editor-layout" novalidate hidden>
  <input type="hidden" name="id">
  <input type="hidden" name="status" value="draft">
  <input type="hidden" name="published_at">
  <div class="news-editor-main">
    <section class="news-editor-section news-method-section">
      <h3 id="news-method-title">วิธีสร้างข่าว</h3>
      <div class="news-methods" role="radiogroup" aria-labelledby="news-method-title">
        <label><input type="radio" name="news_creation_method" value="manual" checked><span><strong>เขียนเอง</strong><small>เขียนและจัดรูปแบบด้วยตนเอง</small></span></label>
        <label><input type="radio" name="news_creation_method" value="match"><span><strong>จากการแข่งขัน</strong><small>ดึงข้อมูลจากแมตช์ในระบบ</small></span></label>
        <label><input type="radio" name="news_creation_method" value="ai"><span><strong>ใช้ AI</strong><small>สร้างร่างแล้วตรวจทานก่อนใช้</small></span></label>
      </div>
    </section>
    <section class="news-editor-section news-writing-section">
      <?php require __DIR__ . '/news-competition.php'; ?>
      <div class="news-writing-fields">
        <p class="form-error" role="alert" hidden></p>
        <label>หัวข้อข่าว <span class="news-required">*</span><input name="title" required placeholder="กรอกหัวข้อข่าว" autocomplete="off"></label>
        <label>คำโปรย<textarea name="excerpt" rows="3" placeholder="ข้อความสั้นสำหรับแสดงในหน้ารายการข่าว"></textarea></label>
        <label>เนื้อหาข่าว<textarea name="body" rows="11" placeholder="เขียนรายละเอียดข่าวที่นี่…"></textarea></label>
        <p class="news-field-help" id="news-honor-help" hidden>ข่าวเกียรติประวัติ: ระบุชื่อนักกีฬา รางวัล และผลงานที่ตรวจสอบแล้วในเนื้อหาข่าว ระบบจะไม่สร้างรายชื่อหรือผลงานขึ้นเอง</p>
        <?php require __DIR__ . '/news-honor.php'; ?>
      </div>
    </section>
    <?php require __DIR__ . '/news-ai-panel.php'; ?>
    <?php require __DIR__ . '/news-draft-review.php'; ?>
  </div>
  <aside class="news-editor-inspector" aria-label="รูปปกและการเผยแพร่">
    <?php require __DIR__ . '/news-cover.php'; ?>
    <?php require __DIR__ . '/news-publishing.php'; ?>
    <details class="news-editor-section news-disclosure" id="news-advanced-settings">
      <summary><i data-lucide="settings-2"></i><span>ตั้งค่าขั้นสูง</span><i data-lucide="chevron-down"></i></summary>
      <div class="news-disclosure-body">
        <label>ลิงก์ย่อ<input name="slug" placeholder="สร้างจากหัวข้อเมื่อเว้นว่าง"></label>
        <label>ลิงก์เว็บไซต์ที่เกี่ยวข้อง<input name="source_url" type="url" placeholder="https://example.com"></label>
        <label>หน่วย / สาขาที่รับผิดชอบ<select name="department_code" class="department-select optional"><option value="">ไม่ระบุ</option></select></label>
        <label>เทมเพลตข้อความ<select id="news-template-select"><option value="">เลือกเทมเพลต</option><option value="announcement">ประกาศทั่วไป</option><option value="preview">ก่อนการแข่งขัน</option><option value="result">สรุปผลการแข่งขัน</option><option value="honor">รางวัลเกียรติประวัติ</option></select></label>
      </div>
    </details>
  </aside>
</form>
