// Presentation helpers only. The original named form controls remain the API source of truth.
function syncNewsComposer() {
  const form = $('#news-form');
  for (const [key, limit] of [['title', 150], ['body', 5000]]) {
    const count = [...form.elements[key].value].length;
    const counter = $(`#news-${key}-count`);
    counter.textContent = `${count.toLocaleString('en-US')}/${limit.toLocaleString('en-US')}`;
    counter.title = `ความยาวแนะนำไม่เกิน ${limit.toLocaleString('en-US')} ตัวอักษร ข้อความเดิมจะไม่ถูกตัด`;
    counter.classList.toggle('news-count-over', count > limit);
  }
  $('#news-identity-category').value = form.elements.news_type.value;
  const kind = form.elements.news_type.value === 'hall_of_fame' ? 'honor' : newsUi.kind === 'honor' ? 'general' : newsUi.kind || 'general';
  $$('[data-news-kind]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.newsKind === kind)));
  $('#news-post-visibility').textContent = form.elements.status.value === 'published' ? 'สาธารณะ' : 'สาธารณะเมื่อเผยแพร่';
}

function syncNewsAiReferences() {
  const form = $('#news-form');
  $$('[data-news-reference]').forEach(select => {
    const original = form.elements[select.dataset.newsReference];
    select.replaceChildren(...[...original.options].map(option => option.cloneNode(true)));
    select.value = original.value;
  });
}

function syncNewsAiMode() {
  const topic = $('#news-ai-mode').value === 'topic';
  $('#news-ai-match-fields').hidden = topic;
  $('#news-ai-topic-field').hidden = !topic;
  $('#news-ai-story-field').hidden = topic;
  $('#news-ai-error').hidden = true;
}

function syncNewsAiDrawer() {
  const panel = $('#news-ai-panel');
  const modal = panel.open && matchMedia('(max-width: 900px)').matches && app.currentView === 'news-editor';
  $('#news-ai-backdrop').hidden = !modal;
  document.body.classList.toggle('news-ai-drawer-open', modal);
  if (modal) {
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'news-ai-heading');
  } else {
    panel.removeAttribute('role'); panel.removeAttribute('aria-modal'); panel.removeAttribute('aria-labelledby');
  }
  $$('[data-news-ai-open]').forEach(button => button.setAttribute('aria-expanded', String(panel.open)));
}

function openNewsAiPanel(focus = true) {
  const panel = $('#news-ai-panel');
  newsUi.aiReturnFocus = document.activeElement;
  panel.open = true;
  syncNewsAiDrawer();
  if (focus) {
    if (!matchMedia('(max-width: 900px)').matches) panel.scrollIntoView({block:'nearest', behavior:'instant'});
    $('#news-ai-mode').focus({preventScroll: true});
  }
}

function closeNewsAiPanel() {
  $('#news-ai-panel').open = false;
  syncNewsAiDrawer();
  if (newsUi.aiReturnFocus?.isConnected && newsUi.aiReturnFocus.getClientRects().length) newsUi.aiReturnFocus.focus({preventScroll:true});
}

function bindNewsComposer() {
  const form = $('#news-form');
  form.addEventListener('input', syncNewsComposer);
  $('#news-identity-category').addEventListener('change', event => {
    form.elements.news_type.value = event.target.value;
    form.elements.news_type.dispatchEvent(new Event('change', {bubbles:true}));
  });
  $$('[data-news-kind]').forEach(button => button.addEventListener('click', () => {
    const kind = button.dataset.newsKind;
    newsUi.kind = kind;
    // Five writing intents use the existing three publishing categories, without new DB values.
    if (kind === 'honor') form.elements.news_type.value = 'hall_of_fame';
    else if (form.elements.news_type.value === 'hall_of_fame') form.elements.news_type.value = 'general';
    if (['competition', 'result'].includes(kind)) {
      $('#news-ai-mode').value = 'match';
      $('#news-ai-story-type').value = kind === 'result' ? 'result' : 'preview';
      syncNewsAiMode();
    }
    syncNewsCategory();
    markNewsDirty();
  }));
  $$('[data-news-reference]').forEach(select => select.addEventListener('change', () => {
    const original = form.elements[select.dataset.newsReference];
    original.value = select.value;
    original.dispatchEvent(new Event('change', {bubbles:true}));
  }));
  $('#news-insert-link').addEventListener('click', () => {
    $('#news-advanced-settings').open = true;
    form.elements.source_url.focus();
    form.elements.source_url.scrollIntoView({block:'center'});
  });
  $$('[data-news-ai-open]').forEach(button => button.addEventListener('click', () => openNewsAiPanel()));
  $('#news-ai-mode').addEventListener('change', () => {
    newsUi.generation++;
    syncNewsAiMode();
  });
  $('#news-ai-panel').addEventListener('toggle', syncNewsAiDrawer);
  $('#news-ai-close').addEventListener('click', closeNewsAiPanel);
  $('#news-ai-backdrop').addEventListener('click', closeNewsAiPanel);
  matchMedia('(max-width: 900px)').addEventListener('change', syncNewsAiDrawer);
  document.addEventListener('keydown', event => {
    if (!document.body.classList.contains('news-ai-drawer-open') || document.querySelector('dialog[open]')) return;
    if (event.key === 'Escape') { event.preventDefault(); closeNewsAiPanel(); }
    if (event.key === 'Tab') {
      const controls = $$('summary,button,input,select,textarea,a[href],[tabindex="0"]', $('#news-ai-panel')).filter(el => !el.disabled && el.getClientRects().length);
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {event.preventDefault();last?.focus();}
      else if (!event.shiftKey && document.activeElement === last) {event.preventDefault();first?.focus();}
    }
  });
  $$('[data-news-preview-device]').forEach(button => button.addEventListener('click', () => {
    $('#news-preview-content').dataset.device = button.dataset.newsPreviewDevice;
    $$('[data-news-preview-device]').forEach(control => control.setAttribute('aria-pressed', String(control === button)));
  }));
  $('#news-cover-remove').addEventListener('click', () => {
    newsUi.coverToken++;
    $('#news-file').value = '';
    $('#news-cover-url').value = '';
    updateNewsCoverPreview();
    markNewsDirty();
  });
  const drop = $('#news-cover-preview');
  drop.addEventListener('click', () => $('#news-file').click());
  drop.addEventListener('keydown', event => {
    if (['Enter', ' '].includes(event.key)) {event.preventDefault();$('#news-file').click();}
  });
  for (const name of ['dragenter', 'dragover']) drop.addEventListener(name, event => {event.preventDefault();drop.classList.add('news-drag-over');});
  drop.addEventListener('dragleave', () => drop.classList.remove('news-drag-over'));
  drop.addEventListener('drop', event => {
    event.preventDefault();drop.classList.remove('news-drag-over');
    const files = event.dataTransfer.files;
    if (files.length !== 1) return toast('กรุณาเลือกรูปปกครั้งละ 1 ภาพ', true);
    const transfer = new DataTransfer();transfer.items.add(files[0]);
    $('#news-file').files = transfer.files;
    $('#news-file').dispatchEvent(new Event('change', {bubbles:true}));
  });
}
