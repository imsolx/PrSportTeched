    <section class="view content-band athlete-page" id="view-public-athletes" hidden>
      <header class="athlete-page-head">
        <div>
          <h2>รายชื่อนักกีฬา</h2>
          <p>เลือกทัวร์นาเมนต์ กีฬา และทีมที่ต้องการดู</p>
        </div>
        <span id="athlete-total-count">0 นักกีฬา</span>
      </header>
      <nav class="athlete-flow" aria-label="ขั้นตอนค้นหานักกีฬา">
        <div class="active" data-athlete-flow-step="1"><span>1</span><strong>ทัวร์นาเมนต์</strong><small>เลือกรายการ</small></div>
        <div data-athlete-flow-step="2"><span>2</span><strong>กีฬา</strong><small>เลือกชนิดกีฬา</small></div>
        <div data-athlete-flow-step="3"><span>3</span><strong>ทีม</strong><small>เลือกทีม</small></div>
        <div data-athlete-flow-step="4"><span>4</span><strong>ผู้เล่น</strong><small>ดูรายชื่อนักกีฬา</small></div>
      </nav>
      <div class="athlete-filter-panel">
        <label><span>1. ทัวร์นาเมนต์</span><select id="athlete-faculty-filter" aria-label="เลือกทัวร์นาเมนต์"></select></label>
        <label><span>2. กีฬา</span><select id="athlete-department-filter" aria-label="เลือกกีฬา" disabled></select></label>
        <label><span>3. ทีม</span><select id="athlete-sport-filter" aria-label="เลือกทีม" disabled></select></label>
      </div>
      <div class="athlete-flow-notice" id="athlete-flow-notice"><i data-lucide="mouse-pointer-click"></i><span>เริ่มจากเลือกทัวร์นาเมนต์</span></div>
      <section class="athlete-results" id="athlete-results" hidden>
        <div class="athlete-results-head">
          <div><span>รายชื่อนักกีฬา</span><h3 id="athlete-results-title">ผู้เล่นทั้งหมด</h3></div>
          <label class="athlete-search"><i data-lucide="search"></i><input id="athlete-team-filter" type="search" placeholder="ค้นหาชื่อผู้เล่น รหัสนักกีฬา หรือทีม" aria-label="ค้นหานักกีฬา"></label>
        </div>
        <div class="athlete-board-list" id="athlete-board-list"></div>
      </section>
    </section>
