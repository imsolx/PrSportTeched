// Public news pages and article rendering. Shared helpers/state remain in workspace.js and app.js.
// Loaded as a classic script; listeners are registered once from bindForms().

async function renderPublicNewsPage() {
  const tournamentId = $('#news-tournament-filter')?.value || '';
  const rows = await request('public.news', { tournament_id: tournamentId });
  renderNewsCards('#public-news-page', rows);
}

async function renderPublicNewsDetail() {
  const id = new URLSearchParams(window.location.search).get('id') || '';
  const target = $('#public-news-detail');
  target.innerHTML = '<div class="article-loading">กำลังโหลดข่าว...</div>';
  if (!id) {
    renderArticleNotFound(target);
    return;
  }
  const [selected, rows] = await Promise.all([
    request('public.news', { id }),
    request('public.news'),
  ]);
  const row = selected[0];
  if (!row) {
    renderArticleNotFound(target);
    return;
  }
  document.title = `${row.title || 'ข่าวสาร'} · ${baseDocumentTitle}`;
  renderArticlePage(target, row, rows);
}


function renderNewsCards(selector, rows) {
  const target = $(selector);
  if (!rows || !rows.length) {
    target.innerHTML = emptyState('ยังไม่มีข่าว');
    return;
  }
  target.innerHTML = rows.map(row => `
    <article class="news-card">
      <div class="news-image">
        ${row.cover_image_url ? `<img src="${escapeAttr(row.cover_image_url)}" alt="">` : '<span>SPORT TOURNAMENT</span>'}
      </div>
      <div class="news-body">
        <span class="badge">${escapeHtml(row.news_type_label || 'ข่าวสาร')}</span>
        <h3>${escapeHtml(row.title || '')}</h3>
        <p>${escapeHtml(row.excerpt || row.body || '')}</p>
        <a class="news-read btn ghost" href="?view=public-news-detail&amp;id=${encodeURIComponent(row.id)}">อ่านข่าว ${icon('arrow-up-right')}</a>
        <small class="muted">${escapeHtml(row.published_at || row.created_at || '')}</small>
      </div>
    </article>
  `).join('');
  $$('.news-read', target).forEach((button, index) => {
    button.setAttribute('aria-label', `อ่านข่าว ${rows[index].title || ''}`);
    button.addEventListener('click', event => {
      event.preventDefault();
      openNews(rows[index], rows);
    });
  });
  refreshIcons(target);
}


function openNews(row, newsRows = []) {
  if (!row?.id) return;
  const url = new URL(window.location.href);
  url.search = '';
  url.searchParams.set('view', 'public-news-detail');
  url.searchParams.set('id', row.id);
  window.history.pushState({ view: 'public-news-detail', id: String(row.id) }, '', url);
  showView('public-news-detail');
}

function renderArticlePage(target, row, newsRows = []) {
  const related = newsRows.filter(item => String(item.id) !== String(row.id)).slice(0, 3);
  const lead = String(row.excerpt || '').trim();
  const body = String(row.body || row.excerpt || '').trim();
  target.innerHTML = `
    <header class="article-reader-bar">
      <div class="article-reader-brand"><span>SPORT TOURNAMENT</span><strong>ข่าวกีฬา</strong></div>
      <span class="article-reader-section">${escapeHtml(row.news_type_label || 'ข่าวสาร')}</span>
      <div class="article-reader-actions">
        <button type="button" class="btn ghost article-copy-link">${icon('link')}<span>คัดลอกลิงก์</span></button>
        <button type="button" class="btn icon-button article-close" aria-label="กลับหน้าข่าว" title="กลับหน้าข่าว">${icon('x')}</button>
      </div>
    </header>
    <article class="article-reader">
      <button type="button" class="article-back">${icon('arrow-left')}<span>กลับหน้าข่าวทั้งหมด</span></button>
      <header class="article-heading">
        <span class="article-kicker">${escapeHtml(row.news_type_label || 'ข่าวประชาสัมพันธ์')}</span>
        <h1 id="article-title">${escapeHtml(row.title || '')}</h1>
        ${lead && lead !== body ? `<p class="article-lead">${escapeHtml(lead)}</p>` : ''}
        <div class="article-publish-meta">
          <span>เผยแพร่โดย ฝ่ายประชาสัมพันธ์กีฬา</span>
          <time>${escapeHtml(formatArticleDate(row.published_at || row.created_at || ''))}</time>
        </div>
      </header>
      ${row.cover_image_url ? `<figure class="article-hero"><img src="${escapeAttr(row.cover_image_url)}" alt="ภาพประกอบ ${escapeAttr(row.title || '')}" onerror="this.closest('figure').remove()"><figcaption>ภาพประกอบข่าวประชาสัมพันธ์การแข่งขัน</figcaption></figure>` : ''}
      <div class="article-content-layout">
        <aside class="article-context" aria-label="ข้อมูลข่าว">
          <span>หมวดข่าว</span><strong>${escapeHtml(row.news_type_label || 'ข่าวสาร')}</strong>
          ${row.department_name ? `<span>หน่วยงาน</span><strong>${escapeHtml(row.department_name)}</strong>` : ''}
        </aside>
        <div class="article-body">${formatArticleBody(body)}</div>
        ${related.length ? `<aside class="article-related" aria-label="ข่าวที่เกี่ยวข้อง"><h2>ข่าวที่เกี่ยวข้อง</h2>${related.map(item => `
          <button type="button" class="article-related-card" data-related-news="${escapeAttr(item.id)}">
            <span>${escapeHtml(item.news_type_label || 'ข่าวสาร')}</span>
            <strong>${escapeHtml(item.title || '')}</strong>
            <small>${escapeHtml(formatArticleDate(item.published_at || item.created_at || ''))}</small>
          </button>`).join('')}</aside>` : ''}
      </div>
      <footer class="article-footer-actions">
        ${row.source_url ? `<a class="btn primary article-source-link" href="${escapeAttr(row.source_url)}" target="_blank" rel="noopener noreferrer">${icon('external-link')}<span>เปิดเว็บไซต์ที่เกี่ยวข้อง</span></a>` : ''}
        <button type="button" class="btn article-copy-link">${icon('link')}<span>คัดลอกลิงก์ข่าว</span></button>
      </footer>
    </article>`;
  const back = () => {
    const url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('view', 'public-news');
    window.history.pushState({ view: 'public-news' }, '', url);
    showView('public-news');
  };
  $$('.article-back, .article-close', target).forEach(button => button.addEventListener('click', back));
  $$('.article-copy-link', target).forEach(button => button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(window.location.href);
      toast('คัดลอกลิงก์ข่าวแล้ว');
    } catch (error) {
      toast('ไม่สามารถคัดลอกลิงก์ได้ กรุณาคัดลอกจากแถบที่อยู่', true);
    }
  }));
  $$('.article-related-card', target).forEach(card => card.addEventListener('click', () => {
    const next = newsRows.find(item => String(item.id) === String(card.dataset.relatedNews));
    if (!next) return;
    openNews(next, newsRows);
  }));
  refreshIcons(target);
}

function renderArticleNotFound(target) {
  target.innerHTML = `<div class="article-empty">${icon('newspaper')}<h1>ไม่พบข่าวที่ต้องการ</h1><p>ข่าวนี้อาจถูกย้าย ยกเลิกเผยแพร่ หรือลิงก์ไม่ถูกต้อง</p><button type="button" class="btn primary">กลับหน้าข่าวทั้งหมด</button></div>`;
  $('button', target).addEventListener('click', () => {
    const url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('view', 'public-news');
    window.history.replaceState({ view: 'public-news' }, '', url);
    showView('public-news');
  });
  refreshIcons(target);
}

function formatArticleBody(value) {
  const paragraphs = String(value || '').split(/\n\s*\n/).map(part => part.trim()).filter(Boolean);
  return paragraphs.length
    ? paragraphs.map(paragraph => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`).join('')
    : '<p>ยังไม่มีรายละเอียดข่าว</p>';
}

function formatArticleDate(value) {
  if (!value) return 'ยังไม่ระบุเวลาเผยแพร่';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat('th-TH', { dateStyle: 'long', timeStyle: 'short' }).format(date);
}

function bindPublicNewsFilters() {
  $('#news-tournament-filter').addEventListener('change', () => renderPublicNewsPage().catch(error => toast(error.message || 'โหลดข่าวไม่สำเร็จ', true)));
}
