// ============================================================
// INTRANET — JavaScript Principal
// ============================================================

// Força scroll ao topo — corrige F5 e bfcache
if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
window.scrollTo(0, 0);
// pageshow cobre F5 e navegação pelo histórico (bfcache)
window.addEventListener('pageshow', function(e) {
  window.scrollTo(0, 0);
});
// load cobre carregamento normal
window.addEventListener('load', function() {
  window.scrollTo(0, 0);
});

document.addEventListener('DOMContentLoaded', () => {
  // --- Dark Mode ---
  const saved = localStorage.getItem('hmp_dark') || document.documentElement.dataset.theme;
  if (saved === 'dark') applyDark(true);

  document.querySelectorAll('.dark-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const isDark = document.documentElement.dataset.theme === 'dark';
      applyDark(!isDark);
      const apiBase = window.location.pathname.includes("/admin/") ? "../api" : "api"; fetch(apiBase + '/toggle_dark.php', { method: 'POST' }).catch(() => {});
    });
  });

  function applyDark(on) {
    document.documentElement.dataset.theme = on ? 'dark' : 'light';
    localStorage.setItem('hmp_dark', on ? 'dark' : 'light');
    document.querySelectorAll('.dark-toggle').forEach(b => b.classList.toggle('on', on));
  }

  // --- Mobile Menu ---
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const navbarNav = document.getElementById('navbarNav');
  if (mobileBtn && navbarNav) {
    mobileBtn.addEventListener('click', () => {
      navbarNav.classList.toggle('open');
    });
  }

  // --- Admin Sidebar Toggle ---
  const sidebarBtn = document.getElementById('sidebarToggle');
  const sidebar    = document.getElementById('adminSidebar');
  if (sidebarBtn && sidebar) {
    sidebarBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', e => {
      if (!sidebar.contains(e.target) && !sidebarBtn.contains(e.target))
        sidebar.classList.remove('open');
    });
  }

  // --- Dropdown ---
  document.querySelectorAll('.dropdown').forEach(d => {
    d.addEventListener('click', e => {
      e.stopPropagation();
      document.querySelectorAll('.dropdown.open').forEach(o => { if (o !== d) o.classList.remove('open'); });
      d.classList.toggle('open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
  });

  // --- Toast ---
  window.showToast = (msg, type = 'success') => {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const icons = { success: 'check_circle', error: 'error', warning: 'warning', info: 'info' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type === 'error' ? 'error' : 'success'}`;
    toast.innerHTML = `<span class="material-icons">${icons[type] || 'info'}</span><span>${msg}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100px)'; toast.style.transition = '.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
  };

  // --- Auto-dismiss alerts ---
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    setTimeout(() => alert.remove(), 5000);
  });

  // --- Confirm delete ---
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm || 'Tem certeza?')) e.preventDefault();
    });
  });

  // --- Rich Text Editor (simple contenteditable) ---
  const editor = document.getElementById('content-editor');
  const contentInput = document.getElementById('content-hidden');
  if (editor && contentInput) {
    editor.addEventListener('input', () => { contentInput.value = editor.innerHTML; });
    document.querySelectorAll('.editor-btn[data-cmd]').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const cmd = btn.dataset.cmd;
        const val = btn.dataset.val || null;
        document.execCommand(cmd, false, val);
        editor.focus();
        contentInput.value = editor.innerHTML;
      });
    });
  }

  // --- Image preview ---
  document.querySelectorAll('[data-preview]').forEach(input => {
    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file) return;
      const target = document.getElementById(input.dataset.preview);
      if (!target) return;
      const reader = new FileReader();
      reader.onload = e => { target.src = e.target.result; target.style.display = 'block'; };
      reader.readAsDataURL(file);
    });
  });

  // --- Module drag sort (admin) ---
  initDragSort();

  // --- Search ---
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', debounce(() => {
      const q = searchInput.value.trim();
      if (q.length > 2) {
        window.location.href = 'index.php?page=busca&q=' + encodeURIComponent(q);
      }
    }, 600));
  }

  // --- Stats counter animation ---
  document.querySelectorAll('.stat-value[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count);
    let current = 0;
    const step = Math.max(1, Math.floor(target / 40));
    const timer = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = current.toLocaleString('pt-BR');
      if (current >= target) clearInterval(timer);
    }, 25);
  });
});

// --- Simple drag-sort for modules ---
function initDragSort() {
  const sortable = document.getElementById('sortableList');
  if (!sortable) return;
  let dragged = null;
  sortable.querySelectorAll('[draggable]').forEach(item => {
    item.addEventListener('dragstart', () => { dragged = item; item.style.opacity = '.5'; });
    item.addEventListener('dragend', () => { item.style.opacity = '1'; updateSortOrder(); });
    item.addEventListener('dragover', e => { e.preventDefault(); const r = item.getBoundingClientRect(); if (e.clientY < r.top + r.height / 2) item.before(dragged); else item.after(dragged); });
  });
  function updateSortOrder() {
    const ids = [...sortable.querySelectorAll('[data-id]')].map(i => i.dataset.id);
    const base = window.location.pathname.includes('/admin/') ? '../api/sort_modules.php' : 'api/sort_modules.php';
    fetch(base, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids }) });
  }
}

// --- Debounce ---
function debounce(fn, delay) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}

// ============================================================
// SLIDER DE PUBLICAÇÃO (capa + galeria unificados)
// data-interval no .post-slider define o tempo em ms
// ============================================================
function initPostSlider(el) {
  const track   = el.querySelector('.post-slider-track');
  const slides  = el.querySelectorAll('.post-slider-slide');
  const dots    = el.querySelectorAll('.post-slider-dot');
  const counter = el.querySelector('.psc-current');
  if (!track || !slides.length) return;

  const total    = slides.length;
  const interval = parseInt(el.dataset.interval || '7000', 10);
  let current = 0;
  let timer   = null;

  // CSS flex:0 0 100% handles sizing — JS only needs to translate
  function goTo(idx) {
    current = ((idx % total) + total) % total;
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
    if (counter) counter.textContent = current + 1;
  }

  function next() { goTo(current + 1); }
  function prev() { goTo(current - 1); }
  function startAuto() {
    clearInterval(timer);
    if (total > 1) timer = setInterval(next, interval);
  }
  function stopAuto() { clearInterval(timer); }

  el.querySelector('.post-slider-btn.next')?.addEventListener('click', e => {
    e.stopPropagation(); stopAuto(); next(); startAuto();
  });
  el.querySelector('.post-slider-btn.prev')?.addEventListener('click', e => {
    e.stopPropagation(); stopAuto(); prev(); startAuto();
  });

  dots.forEach((d, i) => d.addEventListener('click', () => {
    stopAuto(); goTo(i); startAuto();
  }));

  // Touch / swipe
  let tx = 0;
  el.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, {passive: true});
  el.addEventListener('touchend', e => {
    const diff = tx - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) { stopAuto(); diff > 0 ? next() : prev(); startAuto(); }
  });

  goTo(0);
  startAuto();
}

// ============================================================
// LIGHTBOX UNIFICADO — imagens com navegação + vídeos
// ============================================================
(function() {
  let lb, lbInner, lbCounter, lbPrev, lbNext, lbCaption;
  let images = [];
  let current = 0;
  let mode = 'image'; // 'image' | 'video'

  function build() {
    lb = document.createElement('div');
    lb.id = 'lb-overlay';
    lb.className = 'lightbox-overlay';
    lb.innerHTML = `
      <div class="lb-counter"></div>
      <button class="lb-close"><span class="material-icons">close</span></button>
      <button class="lb-prev"><span class="material-icons">chevron_left</span></button>
      <div class="lb-inner"></div>
      <button class="lb-next"><span class="material-icons">chevron_right</span></button>
      <div class="lb-caption"></div>`;
    document.body.appendChild(lb);

    lbInner   = lb.querySelector('.lb-inner');
    lbCounter = lb.querySelector('.lb-counter');
    lbCaption = lb.querySelector('.lb-caption');
    lbPrev    = lb.querySelector('.lb-prev');
    lbNext    = lb.querySelector('.lb-next');

    lb.querySelector('.lb-close').addEventListener('click', closeLb);
    lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });
    lbPrev.addEventListener('click', e => { e.stopPropagation(); showIdx(current - 1); });
    lbNext.addEventListener('click', e => { e.stopPropagation(); showIdx(current + 1); });

    document.addEventListener('keydown', e => {
      if (!lb.classList.contains('open')) return;
      if (e.key === 'Escape')     closeLb();
      if (e.key === 'ArrowRight' && mode === 'image') showIdx(current + 1);
      if (e.key === 'ArrowLeft'  && mode === 'image') showIdx(current - 1);
    });

    let tx = 0;
    lb.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, {passive:true});
    lb.addEventListener('touchend', e => {
      if (mode !== 'image') return;
      const diff = tx - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 40) showIdx(current + (diff > 0 ? 1 : -1));
    });
  }

  function showIdx(idx) {
    if (!images.length) return;
    current = ((idx % images.length) + images.length) % images.length;
    const item = images[current];
    lbInner.innerHTML = `<div class="lb-img-wrap"><img src="${item.src}" alt="${item.caption || ''}"></div>`;
    if (lbCaption) lbCaption.textContent = item.caption || '';
    if (lbCounter) lbCounter.textContent = images.length > 1 ? `${current + 1} / ${images.length}` : '';
    lbPrev.style.display = images.length > 1 ? 'flex' : 'none';
    lbNext.style.display = images.length > 1 ? 'flex' : 'none';
  }

  function openVideo(url, title) {
    if (!lb) build();
    mode = 'video';
    // Stop auto-play on sliders
    document.querySelectorAll('.post-slider').forEach(el => el.dispatchEvent(new Event('mouseenter')));
    lbInner.innerHTML = `
      <div class="lb-video-wrap">
        <iframe src="${url}" allowfullscreen allow="autoplay; encrypted-media"></iframe>
      </div>`;
    if (lbCaption) lbCaption.textContent = title || '';
    if (lbCounter) lbCounter.textContent = '';
    lbPrev.style.display = 'none';
    lbNext.style.display = 'none';
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeLb() {
    // Stop video (remove iframe to stop playback)
    if (mode === 'video') lbInner.innerHTML = '';
    lb.classList.remove('open');
    document.body.style.overflow = '';
    mode = 'image';
  }

  window.openLightbox = function(img) {
    if (!lb) build();
    mode = 'image';
    const slider = img.closest('.post-slider');
    if (slider) {
      const imgs = slider.querySelectorAll('.post-slider-slide img');
      images = Array.from(imgs).map(i => ({ src: i.src, caption: i.alt || '' }));
      current = Array.from(imgs).indexOf(img);
    } else {
      images = [{ src: img.src, caption: img.alt || '' }];
      current = 0;
    }
    showIdx(current);
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
  };

  window.openVideoLightbox = function(url, title) {
    openVideo(url, title || '');
  };
})();

// Init sliders when DOM is ready (or immediately if already loaded)
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.post-slider').forEach(initPostSlider);
  });
} else {
  document.querySelectorAll('.post-slider').forEach(initPostSlider);
}
