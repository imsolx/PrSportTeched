    <section class="view public-hero" id="view-public">
      <div class="hero-copy">
        <p class="eyebrow">แพลตฟอร์มจัดการแข่งขัน</p>
        <h1>SPORT TOURNAMENT</h1>
        <p>ทุกการแข่งขัน ทุกความสำเร็จ<br>ติดตามรายการ ทีม และผลการแข่งขันในที่เดียว</p>
        <div class="actions">
          <button class="btn primary" type="button" data-view-button="public-schedule">ดูตารางแข่งขัน</button>
          <button class="btn" type="button" data-view-button="public-news">อ่านข่าวล่าสุด</button>
        </div>
      </div>
      <div class="stats-grid">
        <div><strong id="m-sports">0</strong><span>กีฬา</span></div>
        <div><strong id="m-schedule">0</strong><span>แมตช์รอแข่ง</span></div>
        <div><strong id="m-results">0</strong><span>ผลจบแล้ว</span></div>
        <div><strong id="m-news">0</strong><span>ข่าวเผยแพร่</span></div>
      </div>
    </section>

    <section class="view content-band" id="view-public-home-extra">
      <div class="section-head">
        <h2>รายการแข่งขันล่าสุด</h2>
        <div class="public-match-filters" aria-label="ตัวกรองการแข่งขัน">
          <label>ทัวร์นาเมนท์<select id="home-tournament-filter"></select></label>
          <label>กรองวันที่แข่งขัน
            <div class="calendar-picker" id="home-calendar-picker">
              <input id="home-date-filter" type="hidden">
              <button class="calendar-trigger" id="home-date-trigger" type="button" aria-expanded="false">เลือกวันที่</button>
              <div class="calendar-popover" id="home-calendar-popover" hidden></div>
            </div>
          </label>
          <label>ทีม<select id="home-department-filter"></select></label>
        </div>
      </div>
      <div class="split">
        <div class="upcoming-panel">
          <h3>ตารางใกล้ถึง</h3>
          <span class="panel-count" id="public-schedule-count">0 คู่</span>
          <div class="public-match-scroll" id="public-schedule"></div>
        </div>
        <div class="results-panel">
          <h3>ผลการแข่งขัน</h3>
          <span class="panel-count" id="public-results-count">0 คู่</span>
          <div class="public-match-scroll" id="public-home-results"></div>
        </div>
      </div>
      <div class="section-head">
        <h2>ข่าวล่าสุด</h2>
        <button class="btn small" type="button" data-view-button="public-news">ดูทั้งหมด</button>
      </div>
      <div class="news-grid" id="public-news"></div>
      <h2>Hall of Fame</h2>
      <div class="news-grid compact" id="public-hall"></div>
    </section>
