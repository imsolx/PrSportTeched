<section class="view app-panel news-management" id="view-news" hidden>
  <header class="section-head news-page-heading">
    <div><h2>ข่าวสารและประชาสัมพันธ์</h2><p>จัดการข่าว ตรวจทาน และเผยแพร่ในที่เดียว</p></div>
    <button class="btn primary" id="news-create-button" type="button"><i data-lucide="plus"></i><span>สร้างข่าว</span></button>
  </header>
  <section class="news-list-section" aria-label="รายการข่าว">
    <p class="news-list-state" id="news-list-state" role="status" hidden></p>
    <button class="btn" id="news-list-retry" type="button" hidden>ลองโหลดอีกครั้ง</button>
    <div class="table-wrap" id="news-table" aria-label="ข่าวทั้งหมด"></div>
  </section>
  <?php require __DIR__ . '/../../components/news/news-action-menu.php'; ?>
</section>
