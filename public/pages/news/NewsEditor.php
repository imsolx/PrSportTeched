<section class="view app-panel news-editor-page" id="view-news-editor" hidden>
  <header class="section-head news-page-heading">
    <div><button class="btn ghost news-back" data-news-back type="button"><i data-lucide="arrow-left"></i><span>กลับรายการข่าว</span></button><h2 id="news-editor-title">สร้างข่าว</h2></div>
    <span class="news-save-feedback" id="news-save-feedback" role="status">ยังไม่ได้บันทึก</span>
  </header>
  <p class="form-error" id="news-editor-load-error" role="alert" hidden></p>
  <?php require __DIR__ . '/../../components/news/news-form.php'; ?>
</section>
