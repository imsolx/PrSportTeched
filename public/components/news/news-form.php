<form id="news-form" class="news-editor-layout" novalidate hidden>
  <input type="hidden" name="id">
  <input type="hidden" name="status" value="draft">
  <input type="hidden" name="published_at">
  <div class="news-editor-main">
    <section class="news-editor-section news-kind-section" aria-labelledby="news-kind-heading">
      <h3 id="news-kind-heading">เลือกประเภทข่าว</h3>
      <div class="news-kind-options" role="group" aria-label="รูปแบบการเขียนข่าว">
        <button type="button" data-news-kind="general" aria-pressed="true"><i data-lucide="newspaper"></i><span>ข่าวทั่วไป</span></button>
        <button type="button" data-news-kind="competition" aria-pressed="false"><i data-lucide="megaphone"></i><span>ข่าวการแข่งขัน</span></button>
        <button type="button" data-news-kind="result" aria-pressed="false"><i data-lucide="chart-no-axes-combined"></i><span>สรุปผลการแข่งขัน</span></button>
        <button type="button" data-news-kind="honor" aria-pressed="false"><i data-lucide="award"></i><span>เกียรติประวัติ</span></button>
        <button type="button" data-news-kind="announcement" aria-pressed="false"><i data-lucide="star"></i><span>ประกาศ</span></button>
      </div>
    </section>
    <section class="news-editor-section news-writing-section">
      <header class="news-post-identity">
        <span class="news-post-avatar" aria-hidden="true">TECH<span>ED</span></span>
        <div><strong>TECHED SPORT</strong><div class="news-identity-meta">
          <label class="news-identity-category"><span class="news-sr-only">หมวดหมู่ข่าว</span><select id="news-identity-category" aria-label="หมวดหมู่ข่าวในโพสต์"><option value="general">ข่าวสารทั่วไป</option><option value="department">เนื้อหาหน่วย / สาขา</option><option value="hall_of_fame">ข่าวเกียรติประวัติ</option></select></label>
          <span class="news-visibility" title="ฉบับร่างจะยังไม่แสดงต่อสาธารณะ"><i data-lucide="globe"></i><span id="news-post-visibility">สาธารณะเมื่อเผยแพร่</span></span>
        </div></div>
      </header>
      <div class="news-writing-fields">
        <p class="form-error" role="alert" hidden></p>
        <label class="news-title-field"><span class="news-sr-only">หัวข้อข่าว (จำเป็น)</span><input name="title" required placeholder="เขียนหัวข้อข่าวที่นี่..." autocomplete="off" aria-describedby="news-title-count"><span class="news-character-count" id="news-title-count">0/150</span></label>
        <label class="news-body-field"><span class="news-sr-only">เนื้อหาข่าว</span><textarea name="body" rows="7" placeholder="วันนี้มีข่าวกีฬาอะไรอยากประชาสัมพันธ์?" aria-describedby="news-body-count"></textarea><span class="news-character-count" id="news-body-count">0/5,000</span></label>
        <div class="news-media-toolbar" role="group" aria-label="เพิ่มเนื้อหาในโพสต์">
          <label class="btn news-upload-label"><input type="file" id="news-file" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="เลือกรูปปกข่าว"><i data-lucide="image"></i><span>รูปภาพ</span></label>
          <button class="btn" id="banner-open-button" type="button"><i data-lucide="panels-top-left"></i><span>Banner Studio</span></button>
          <button class="btn" id="news-insert-link" type="button"><i data-lucide="link"></i><span>ลิงก์</span></button>
          <button class="btn news-ai-trigger" type="button" data-news-ai-open aria-controls="news-ai-panel"><i data-lucide="sparkles"></i><span>AI ช่วยร่างข่าว</span></button>
        </div>
        <?php require __DIR__ . '/news-cover.php'; ?>
        <details class="news-excerpt-settings" id="news-excerpt-settings">
          <summary>คำโปรยข่าว <small>(ไม่บังคับ)</small><i data-lucide="chevron-down"></i></summary>
          <label>ข้อความสั้นสำหรับแสดงในหน้ารายการข่าว<textarea name="excerpt" rows="3" placeholder="เว้นว่างได้ หรือเขียนข้อความแนะนำข่าวสั้น ๆ"></textarea></label>
        </details>
        <p class="news-field-help" id="news-honor-help" hidden>ข่าวเกียรติประวัติ: ระบุชื่อนักกีฬา รางวัล และผลงานที่ตรวจสอบแล้วในเนื้อหาข่าว ระบบจะไม่สร้างรายชื่อหรือผลงานขึ้นเอง</p>
        <?php require __DIR__ . '/news-honor.php'; ?>
      </div>
    </section>
    <?php require __DIR__ . '/news-competition.php'; ?>
  </div>
  <aside class="news-editor-inspector" aria-label="ผู้ช่วย AI และการเผยแพร่">
    <?php require __DIR__ . '/news-ai-panel.php'; ?>
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
