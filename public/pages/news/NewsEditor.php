<section class="view app-panel news-editor-page" id="view-news-editor" hidden>
  <header class="section-head news-page-heading">
    <div><button class="btn ghost news-back" data-news-back type="button"><i data-lucide="arrow-left"></i><span>กลับรายการข่าว</span></button><h2 id="news-editor-title">สร้างข่าวประชาสัมพันธ์</h2><p>เขียนและเผยแพร่ข่าวสารการแข่งขันกีฬา ในรูปแบบที่คุ้นเคย ใช้งานง่ายเหมือนการโพสต์ทั่วไป</p></div>
    <div class="news-heading-tools">
      <div class="news-publish-actions">
        <button class="btn" id="news-save-draft" form="news-form" type="submit" data-news-save="draft"><i data-lucide="save"></i><span>บันทึกร่าง</span></button>
        <button class="btn" id="news-preview-button" type="button"><i data-lucide="eye"></i><span>ดูตัวอย่าง</span></button>
        <button class="btn primary" id="news-publish-button" form="news-form" type="submit" data-news-save="published"><i data-lucide="send"></i><span>เผยแพร่</span></button>
      </div>
      <span class="news-save-feedback" id="news-save-feedback" role="status">ยังไม่ได้บันทึก</span>
    </div>
  </header>
  <p class="form-error" id="news-editor-load-error" role="alert" hidden></p>
  <?php require __DIR__ . '/../../components/news/news-form.php'; ?>
  <button type="button" id="news-ai-backdrop" aria-label="ปิดผู้ช่วย AI" hidden></button>
</section>
