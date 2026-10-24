<section class="news-editor-section news-publishing-section">
  <h3><i data-lucide="send"></i>การเผยแพร่</h3>
  <div class="news-status-line"><span>สถานะ</span><span id="news-editor-status" class="status-badge">ฉบับร่าง</span></div>
  <label>หมวดหมู่ข่าว<select name="news_type" id="news-type-select"><option value="general">ข่าวสารทั่วไป</option><option value="department">เนื้อหาหน่วย / สาขา</option><option value="hall_of_fame">ข่าวเกียรติประวัติ</option></select></label>
  <label class="checkbox-line"><input type="checkbox" name="is_pinned"> ปักหมุดข่าว</label>
  <div class="news-publish-actions">
    <button class="btn" id="news-preview-button" type="button"><i data-lucide="eye"></i><span>ดูตัวอย่าง</span></button>
    <button class="btn" id="news-save-draft" type="submit" data-news-save="draft"><i data-lucide="save"></i><span>บันทึกร่าง</span></button>
    <button class="btn primary" id="news-publish-button" type="submit" data-news-save="published"><i data-lucide="send"></i><span>เผยแพร่</span></button>
  </div>
</section>
