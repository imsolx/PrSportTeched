const bannerStudio = {
  canvas: null,
  context: null,
  photo: null,
  fallbackPhoto: null,
  logo: null,
  template: 'matchday',
  accent: '#f4c400',
  align: 'left',
  imageScale: 100,
  imageX: 50,
  imageY: 50,
  selected: 'headline',
  dragging: null,
  bounds: {},
  positions: {},
};

const bannerTemplatePositions = {
  matchday: {
    sport: { x: 7, y: 23 },
    headline: { x: 7, y: 39 },
    subtitle: { x: 7, y: 76 },
  },
  result: {
    sport: { x: 6, y: 23 },
    headline: { x: 6, y: 40 },
    subtitle: { x: 6, y: 77 },
  },
  spotlight: {
    sport: { x: 7, y: 58 },
    headline: { x: 7, y: 70 },
    subtitle: { x: 7, y: 89 },
  },
};

function initBannerStudio() {
  const canvas = $('#banner-canvas');
  if (!canvas) return;
  bannerStudio.canvas = canvas;
  bannerStudio.context = canvas.getContext('2d');
  setBannerTemplate('matchday');

  $('#banner-open-button').addEventListener('click', () => openBannerStudio());
  $('#banner-close-button').addEventListener('click', () => $('#banner-dialog').close());
  bindDialogDismiss($('#banner-dialog'), () => $('#banner-dialog').close());

  $('#banner-template').addEventListener('change', event => setBannerTemplate(event.target.value));
  $('#banner-accent').addEventListener('input', event => { bannerStudio.accent = event.target.value; renderBanner(); });
  $('#banner-align').addEventListener('change', event => {
    bannerStudio.align = event.target.value;
    const anchor = { left: 7, center: 50, right: 93 }[bannerStudio.align];
    Object.values(bannerStudio.positions).forEach(position => { position.x = anchor; });
    syncBannerElementControls();
    renderBanner();
  });
  ['sport', 'headline', 'subtitle'].forEach(key => {
    $(`#banner-${key}`).addEventListener('input', renderBanner);
  });
  ['scale', 'x', 'y'].forEach(key => {
    $(`#banner-image-${key}`).addEventListener('input', event => {
      const property = key === 'scale' ? 'imageScale' : `image${key.toUpperCase()}`;
      bannerStudio[property] = Number(event.target.value);
      renderBanner();
    });
  });
  $('#banner-active-element').addEventListener('change', event => selectBannerElement(event.target.value));
  $('#banner-element-x').addEventListener('input', event => updateSelectedBannerPosition('x', Number(event.target.value)));
  $('#banner-element-y').addEventListener('input', event => updateSelectedBannerPosition('y', Number(event.target.value)));
  $('#banner-image-file').addEventListener('change', async event => {
    const file = event.target.files[0];
    if (!file) return;
    bannerStudio.photo = await loadBannerImage(await fileToDataUrl(file));
    bannerStudio.imageScale = 100;
    bannerStudio.imageX = 50;
    bannerStudio.imageY = 50;
    syncBannerImageControls();
    renderBanner();
  });

  canvas.addEventListener('pointerdown', startBannerDrag);
  canvas.addEventListener('pointermove', moveBannerDrag);
  canvas.addEventListener('pointerup', stopBannerDrag);
  canvas.addEventListener('pointercancel', stopBannerDrag);
  canvas.addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    const step = event.shiftKey ? 2 : .5;
    const position = bannerStudio.positions[bannerStudio.selected];
    if (event.key === 'ArrowLeft') position.x -= step;
    if (event.key === 'ArrowRight') position.x += step;
    if (event.key === 'ArrowUp') position.y -= step;
    if (event.key === 'ArrowDown') position.y += step;
    position.x = Math.max(3, Math.min(97, position.x));
    position.y = Math.max(5, Math.min(95, position.y));
    syncBannerElementControls();
    renderBanner();
  });

  $('#banner-download-button').addEventListener('click', () => runTask($('#banner-download-button'), downloadBanner));
  $('#banner-use-button').addEventListener('click', () => runTask($('#banner-use-button'), useBannerAsCover));
  refreshIcons($('#banner-dialog'));

  const assetBaseUrl = document.querySelector('script[src$="banner-studio.js"]')?.src || window.location.href;
  Promise.all([
    loadBannerImage(new URL('court.jpg', assetBaseUrl).href),
    loadBannerImage(new URL('logo.png', assetBaseUrl).href),
    document.fonts?.ready || Promise.resolve(),
  ]).then(([photo, logo]) => {
    bannerStudio.fallbackPhoto = photo;
    bannerStudio.logo = logo;
    renderBanner();
  }).catch(() => renderBanner());
}

function prepareNewsEditor(row) {
  updateNewsCoverPreview(row?.cover_image_url || '');
  $('#news-ai-status').textContent = app.aiSettings?.configured ? `Gemini: ${app.aiSettings.default_model}` : 'พร้อมสร้างร่าง';
  syncBannerFromNews({
    sport: '',
    headline: row?.title || '',
    subtitle: row?.excerpt || '',
  });
}

function updateNewsCoverPreview(source = '') {
  const host = $('#news-cover-preview');
  if (!host) return;
  host.replaceChildren();
  if (!source) {
    host.innerHTML = `${icon('image')}<span>ยังไม่มีรูปปก</span>`;
    refreshIcons(host);
    return;
  }
  const image = document.createElement('img');
  image.src = source;
  image.alt = 'ตัวอย่างรูปปกข่าว';
  image.addEventListener('error', () => {
    host.innerHTML = `${icon('image-off')}<span>ไม่สามารถแสดงรูปปกได้</span>`;
    refreshIcons(host);
  }, { once: true });
  host.append(image);
}

function syncBannerFromNews({ sport = '', headline = '', subtitle = '' } = {}) {
  if (sport) $('#banner-sport').value = sport;
  if (headline) $('#banner-headline').value = headline;
  if (subtitle) $('#banner-subtitle').value = subtitle;
  renderBanner();
}

async function openBannerStudio() {
  const newsForm = $('#news-form');
  const match = app.newsMatches.find(row => String(row.id) === $('#news-ai-match').value);
  const tournament = app.newsMatches.find(row => String(row.tournament_id) === $('#news-ai-tournament').value);
  $('#banner-sport').value = match?.sport_name || tournament?.sport_name || $('#news-ai-sport option:checked')?.textContent.replace('ทุกกีฬา', '') || $('#banner-sport').value;
  $('#banner-headline').value = newsForm.elements.title.value || $('#banner-headline').value || 'การแข่งขันกีฬาครุศาสตร์อุตสาหกรรม';
  $('#banner-subtitle').value = match?.tournament_name || tournament?.tournament_name || newsForm.elements.excerpt.value || $('#banner-subtitle').value;

  const file = $('#news-file').files[0];
  const coverUrl = $('#news-cover-url').value.trim();
  try {
    if (file) bannerStudio.photo = await loadBannerImage(await fileToDataUrl(file));
    else if (coverUrl) bannerStudio.photo = await loadBannerImage(coverUrl);
  } catch (error) {
    bannerStudio.photo = null;
    toast('โหลดรูปปกเดิมไม่ได้ กรุณาเลือกรูปการแข่งขันใหม่', true);
  }
  $('#banner-dialog').showModal();
  syncBannerImageControls();
  syncBannerElementControls();
  renderBanner();
}

function setBannerTemplate(template) {
  bannerStudio.template = bannerTemplatePositions[template] ? template : 'matchday';
  bannerStudio.positions = JSON.parse(JSON.stringify(bannerTemplatePositions[bannerStudio.template]));
  if ($('#banner-template')) $('#banner-template').value = bannerStudio.template;
  syncBannerElementControls();
  renderBanner();
}

function selectBannerElement(key) {
  if (!bannerStudio.positions[key]) return;
  bannerStudio.selected = key;
  syncBannerElementControls();
  renderBanner();
}

function updateSelectedBannerPosition(axis, value) {
  bannerStudio.positions[bannerStudio.selected][axis] = value;
  renderBanner();
}

function syncBannerImageControls() {
  $('#banner-image-scale').value = String(bannerStudio.imageScale);
  $('#banner-image-x').value = String(bannerStudio.imageX);
  $('#banner-image-y').value = String(bannerStudio.imageY);
}

function syncBannerElementControls() {
  if (!$('#banner-active-element') || !bannerStudio.positions[bannerStudio.selected]) return;
  $('#banner-active-element').value = bannerStudio.selected;
  $('#banner-element-x').value = String(bannerStudio.positions[bannerStudio.selected].x);
  $('#banner-element-y').value = String(bannerStudio.positions[bannerStudio.selected].y);
}

function loadBannerImage(source) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    if (!String(source).startsWith('data:')) image.crossOrigin = 'anonymous';
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('โหลดรูปภาพไม่สำเร็จ'));
    image.src = source;
  });
}

function renderBanner(showSelection = true) {
  const canvas = bannerStudio.canvas;
  const ctx = bannerStudio.context;
  if (!canvas || !ctx || !bannerStudio.positions.headline) return;
  const width = canvas.width;
  const height = canvas.height;
  const template = bannerStudio.template;
  const photo = bannerStudio.photo || bannerStudio.fallbackPhoto;
  ctx.clearRect(0, 0, width, height);
  ctx.fillStyle = '#061f42';
  ctx.fillRect(0, 0, width, height);

  if (template === 'result') {
    ctx.fillStyle = '#f7f8fb';
    ctx.fillRect(0, 0, width, height);
    if (photo) drawBannerCover(photo, { x: 510, y: 0, width: 690, height });
    ctx.fillStyle = bannerStudio.accent;
    ctx.fillRect(492, 0, 18, height);
  } else {
    if (photo) drawBannerCover(photo, { x: 0, y: 0, width, height });
    if (template === 'matchday') {
      ctx.fillStyle = 'rgba(6,31,66,.88)';
      ctx.fillRect(0, 0, 650, height);
      ctx.fillStyle = bannerStudio.accent;
      ctx.fillRect(650, 0, 14, height);
    } else {
      ctx.fillStyle = 'rgba(6,31,66,.90)';
      ctx.fillRect(0, 355, width, 320);
      ctx.fillStyle = bannerStudio.accent;
      ctx.fillRect(0, 345, width, 10);
    }
  }

  drawBannerBrand(template);
  bannerStudio.bounds = {};
  const darkText = template === 'result';
  drawBannerText('sport', $('#banner-sport')?.value || 'TECH ED SPORT', {
    size: 27,
    weight: 700,
    color: darkText ? '#135aa3' : bannerStudio.accent,
    maxWidth: template === 'result' ? 420 : template === 'matchday' ? 520 : 980,
    maxLines: 1,
    lineHeight: 1.2,
  });
  drawBannerText('headline', $('#banner-headline')?.value || 'การแข่งขันกีฬาครุศาสตร์อุตสาหกรรม', {
    size: template === 'spotlight' ? 58 : 62,
    weight: 800,
    color: darkText ? '#061f42' : '#ffffff',
    maxWidth: template === 'result' ? 420 : template === 'matchday' ? 540 : 1020,
    maxLines: template === 'spotlight' ? 2 : 3,
    lineHeight: 1.2,
  });
  drawBannerText('subtitle', $('#banner-subtitle')?.value || 'มหาวิทยาลัยเทคโนโลยีราชมงคลธัญบุรี', {
    size: 27,
    weight: 500,
    color: darkText ? '#536075' : '#eef2f7',
    maxWidth: template === 'result' ? 420 : template === 'matchday' ? 530 : 1000,
    maxLines: 2,
    lineHeight: 1.35,
  });

  if (showSelection && bannerStudio.bounds[bannerStudio.selected]) {
    const box = bannerStudio.bounds[bannerStudio.selected];
    ctx.save();
    ctx.strokeStyle = bannerStudio.accent;
    ctx.lineWidth = 3;
    ctx.setLineDash([10, 7]);
    ctx.strokeRect(box.x - 10, box.y - 8, box.width + 20, box.height + 16);
    ctx.restore();
  }
}

function drawBannerCover(image, region) {
  const ctx = bannerStudio.context;
  const zoom = bannerStudio.imageScale / 100;
  const scale = Math.max(region.width / image.naturalWidth, region.height / image.naturalHeight) * zoom;
  const sourceWidth = Math.min(image.naturalWidth, region.width / scale);
  const sourceHeight = Math.min(image.naturalHeight, region.height / scale);
  const sourceX = (image.naturalWidth - sourceWidth) * (bannerStudio.imageX / 100);
  const sourceY = (image.naturalHeight - sourceHeight) * (bannerStudio.imageY / 100);
  ctx.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, region.x, region.y, region.width, region.height);
}

function drawBannerBrand(template) {
  const ctx = bannerStudio.context;
  const logo = bannerStudio.logo;
  const box = template === 'result' ? { x: 42, y: 30, width: 310, height: 76 } : { x: 840, y: 30, width: 318, height: 78 };
  ctx.fillStyle = 'rgba(255,255,255,.94)';
  ctx.fillRect(box.x, box.y, box.width, box.height);
  if (!logo) return;
  const scale = Math.min((box.width - 24) / logo.naturalWidth, (box.height - 16) / logo.naturalHeight);
  const width = logo.naturalWidth * scale;
  const height = logo.naturalHeight * scale;
  ctx.drawImage(logo, box.x + (box.width - width) / 2, box.y + (box.height - height) / 2, width, height);
}

function drawBannerText(key, text, options) {
  const ctx = bannerStudio.context;
  const position = bannerStudio.positions[key];
  const anchorX = position.x / 100 * bannerStudio.canvas.width;
  const topY = position.y / 100 * bannerStudio.canvas.height;
  const fontFamily = 'Prompt, Sarabun, Tahoma, sans-serif';
  let size = options.size;
  let lines = [];
  do {
    ctx.font = `${options.weight} ${size}px ${fontFamily}`;
    lines = wrapBannerText(ctx, text, options.maxWidth);
    if (lines.length <= options.maxLines || size <= 24) break;
    size -= 2;
  } while (size >= 24);
  if (lines.length > options.maxLines) {
    lines = lines.slice(0, options.maxLines);
    while (ctx.measureText(`${lines[lines.length - 1]}...`).width > options.maxWidth && lines[lines.length - 1].length > 2) {
      lines[lines.length - 1] = lines[lines.length - 1].slice(0, -1);
    }
    lines[lines.length - 1] += '...';
  }
  const lineHeight = size * options.lineHeight;
  const widths = lines.map(line => ctx.measureText(line).width);
  const blockWidth = Math.max(...widths, 1);
  const blockHeight = lines.length * lineHeight;
  let left = anchorX;
  if (bannerStudio.align === 'center') left = anchorX - blockWidth / 2;
  if (bannerStudio.align === 'right') left = anchorX - blockWidth;
  ctx.fillStyle = options.color;
  ctx.textAlign = bannerStudio.align;
  ctx.textBaseline = 'top';
  lines.forEach((line, index) => ctx.fillText(line, anchorX, topY + index * lineHeight));
  bannerStudio.bounds[key] = { x: left, y: topY, width: blockWidth, height: blockHeight };
}

function wrapBannerText(ctx, text, maxWidth) {
  const clean = String(text || '').trim().replace(/\s+/g, ' ');
  if (!clean) return [''];
  const segmenter = typeof Intl.Segmenter === 'function' ? new Intl.Segmenter('th', { granularity: 'word' }) : null;
  const words = segmenter ? [...segmenter.segment(clean)].map(item => item.segment) : clean.split(' ');
  const lines = [];
  let line = '';
  words.forEach(word => {
    const candidate = line + word;
    if (line && ctx.measureText(candidate).width > maxWidth) {
      lines.push(line.trim());
      line = word;
    } else {
      line = candidate;
    }
  });
  if (line.trim()) lines.push(line.trim());
  return lines.length ? lines : [clean];
}

function bannerPointerPosition(event) {
  const rect = bannerStudio.canvas.getBoundingClientRect();
  return {
    x: (event.clientX - rect.left) * bannerStudio.canvas.width / rect.width,
    y: (event.clientY - rect.top) * bannerStudio.canvas.height / rect.height,
  };
}

function startBannerDrag(event) {
  const point = bannerPointerPosition(event);
  const hit = Object.entries(bannerStudio.bounds).reverse().find(([, box]) =>
    point.x >= box.x - 14 && point.x <= box.x + box.width + 14
    && point.y >= box.y - 14 && point.y <= box.y + box.height + 14);
  if (!hit) return;
  selectBannerElement(hit[0]);
  bannerStudio.dragging = hit[0];
  bannerStudio.canvas.setPointerCapture(event.pointerId);
}

function moveBannerDrag(event) {
  if (!bannerStudio.dragging) return;
  const point = bannerPointerPosition(event);
  const position = bannerStudio.positions[bannerStudio.dragging];
  position.x = Math.max(3, Math.min(97, point.x / bannerStudio.canvas.width * 100));
  position.y = Math.max(5, Math.min(95, point.y / bannerStudio.canvas.height * 100));
  syncBannerElementControls();
  renderBanner();
}

function stopBannerDrag(event) {
  if (!bannerStudio.dragging) return;
  if (bannerStudio.canvas.hasPointerCapture(event.pointerId)) bannerStudio.canvas.releasePointerCapture(event.pointerId);
  bannerStudio.dragging = null;
}

function bannerDataUrl() {
  renderBanner(false);
  try {
    return bannerStudio.canvas.toDataURL('image/png', 1);
  } finally {
    renderBanner(true);
  }
}

async function downloadBanner() {
  const link = document.createElement('a');
  link.download = `prsport-banner-${Date.now()}.png`;
  link.href = bannerDataUrl();
  link.click();
  toast('ดาวน์โหลดแบนเนอร์ PNG แล้ว');
}

async function useBannerAsCover() {
  let dataUrl;
  try {
    dataUrl = bannerDataUrl();
  } catch (error) {
    throw new Error('ส่งออกรูปไม่ได้ กรุณาอัปโหลดรูปการแข่งขันจากเครื่องแทนลิงก์ภายนอก');
  }
  const upload = await request('news.upload', { data_url: dataUrl, file_name: `banner-${Date.now()}.png` });
  $('#news-cover-url').value = upload.url;
  $('#news-file').value = '';
  updateNewsCoverPreview(upload.url);
  $('#banner-dialog').close();
  toast('นำแบนเนอร์ไปใช้เป็นรูปปกข่าวแล้ว');
}
