// sevra.js - Sevra Collection frontend interactions
// Handles: auth modal, cart drawer, quantity steppers, search, toast, page transitions,
//          and product card size picker

// ✅ CSRF Token cache for cart operations
let cartCsrfToken = null;

// ✅ Helper to get CSRF token for cart AJAX requests
async function getCartCsrfToken() {
    if (cartCsrfToken) return cartCsrfToken;
    try {
        const response = await fetch('/cart/csrf-token');
        const data = await response.json();
        cartCsrfToken = data.token;
        return cartCsrfToken;
    } catch (error) {
        console.error('Failed to get CSRF token:', error);
        return null;
    }
}

/* ─── Modal (Login / Register / Reset) ──────────────────────── */
class AuthModal {
  constructor() {
    this.overlay   = document.getElementById('auth-modal');
    this.tabs      = document.querySelectorAll('.modal-tab');
    this.panels    = document.querySelectorAll('.modal-panel');
    this.closeBtn  = document.getElementById('modal-close');

    this.bindTriggers();
    this.bindClose();
    this.bindTabs();
  }

  bindTriggers() {
    document.querySelectorAll('[data-modal]').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        const mode = el.dataset.modal || 'login';
        this.open(mode);
      });
    });
  }

  bindClose() {
    if (!this.closeBtn) return;
    this.closeBtn.addEventListener('click', () => this.close());
    this.overlay?.addEventListener('click', e => {
      if (e.target === this.overlay) this.close();
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') this.close();
    });
  }

  bindTabs() {
    this.tabs.forEach(tab => {
      tab.addEventListener('click', () => this.switchTab(tab.dataset.tab));
    });
  }

  open(mode = 'login') {
    this.overlay?.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    this.switchTab(mode);
  }

  close() {
    this.overlay?.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  switchTab(name) {
    this.tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    this.panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
  }
}

/* ─── Cart Drawer ────────────────────────────────────── */
class CartDrawer {
  constructor() {
    this.drawer  = document.getElementById('cart-drawer');
    this.overlay = document.getElementById('cart-overlay');
    this.bindTriggers();
    window.openCartDrawer = () => this.open();
  }

  bindTriggers() {
    document.querySelectorAll('[data-cart-open]').forEach(el =>
      el.addEventListener('click', e => { e.preventDefault(); this.open(); })
    );
    document.querySelectorAll('[data-cart-close]').forEach(el =>
      el.addEventListener('click', () => this.close())
    );
    this.overlay?.addEventListener('click', () => this.close());
  }

  open() {
    this.drawer?.classList.add('is-open');
    this.overlay?.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  close() {
    this.drawer?.classList.remove('is-open');
    this.overlay?.classList.remove('is-open');
    document.body.style.overflow = '';
  }
}

function initAddToCart() {
  document.querySelectorAll('form[data-add-to-cart]').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const btn = form.querySelector('button[type="submit"]');
      const formData = new FormData(form);

      if (btn) btn.disabled = true;

      try {
        const token = await getCartCsrfToken();
        const headers = {
          'X-Requested-With': 'XMLHttpRequest'
        };
        if (token) {
          headers['X-CSRF-Token'] = token;
        }

        const response = await fetch(form.action || '/cart/add', {
          method: 'POST',
          body: formData,
          headers: headers
        });

        if (response.ok) {
          if (window.refreshSevraCart) {
            await window.refreshSevraCart();
          } else {
            window.location.reload();
          }
          showToast('Article ajouté au panier');
        } else if (response.status === 403) {
          showToast('Erreur de sécurité. Veuillez rafraîchir la page.');
        }
      } catch (error) {
        showToast('Erreur lors de l\'ajout');
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  });
}

/* ─── Quantity Stepper ───────────────────────────────── */
function initQtySteppers() {
  document.querySelectorAll('.qty-stepper').forEach(stepper => {
    const display = stepper.querySelector('span');
    const dec     = stepper.querySelector('[data-qty-dec]');
    const inc     = stepper.querySelector('[data-qty-inc]');
    let val = parseInt(display?.textContent || '1');

    dec?.addEventListener('click', () => {
      if (val > 1) { val--; display.textContent = val; triggerQtyChange(stepper, val); }
    });
    inc?.addEventListener('click', () => {
      val++;
      display.textContent = val;
      triggerQtyChange(stepper, val);
    });
  });
}

function triggerQtyChange(stepper, val) {
  stepper.dispatchEvent(new CustomEvent('qty:change', { detail: { qty: val }, bubbles: true }));
}

/* ─── Size Selector (product page) ──────────────────────────────────── */
function initSizeSelector() {
  document.querySelectorAll('.size-selector').forEach(group => {
    group.querySelectorAll('.size-btn:not(.out-of-stock)').forEach(btn => {
      btn.addEventListener('click', () => {
        group.querySelectorAll('.size-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        const sizeInput = group.closest('form')?.querySelector('[name="size"]');
        if (sizeInput) sizeInput.value = btn.dataset.size;
      });
    });
  });
}

/* ─── NEW: Product Card Size Picker (inline size selection) ────────── */
function initCardSizePicker() {
  // Handle "Choisir la taille" trigger
  document.querySelectorAll('.js-card-size-trigger').forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const card = trigger.closest('.product-card');
      if (!card) return;

      const picker = card.querySelector('.card-size-picker');
      if (!picker) return;

      // Hide the hover overlay button container (the trigger's parent)
      const overlayDiv = trigger.closest('.absolute.inset-0.flex.items-end.justify-center');
      if (overlayDiv) overlayDiv.style.opacity = '0';
      if (overlayDiv) overlayDiv.style.pointerEvents = 'none';

      // Show picker
      picker.classList.remove('hidden');
      picker.style.display = 'block';
    });
  });

  // Handle size button click inside picker
  document.querySelectorAll('.card-size-picker .card-size-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const picker = btn.closest('.card-size-picker');
      if (!picker) return;

      // Remove selected class from all size buttons in this picker
      picker.querySelectorAll('.card-size-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');

      // Find the associated "Ajouter au panier" button inside this picker
      const addBtn = picker.querySelector('.js-quick-add');
      if (addBtn) {
        // Store selected size as data-size on the add button
        addBtn.dataset.size = btn.dataset.size;
        // Enable the button (remove visual disabled state)
        addBtn.style.opacity = '1';
        addBtn.style.pointerEvents = 'auto';
      }
    });
  });

  // Handle cancel button inside picker
  document.querySelectorAll('.js-card-size-cancel').forEach(cancelBtn => {
    cancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const picker = cancelBtn.closest('.card-size-picker');
      if (!picker) return;
      const card = picker.closest('.product-card');
      if (!card) return;

      // Hide picker
      picker.classList.add('hidden');
      picker.style.display = 'none';

      // Reset the add button inside picker
      const addBtn = picker.querySelector('.js-quick-add');
      if (addBtn) {
        addBtn.dataset.size = '';
        addBtn.style.opacity = '0.4';
        addBtn.style.pointerEvents = 'none';
      }

      // Remove any selected size style
      picker.querySelectorAll('.card-size-btn').forEach(b => b.classList.remove('selected'));

      // Show the hover overlay again
      const overlayDiv = card.querySelector('.absolute.inset-0.flex.items-end.justify-center');
      if (overlayDiv) {
        overlayDiv.style.opacity = '';
        overlayDiv.style.pointerEvents = '';
      }
    });
  });
}

/* ─── Filter Chips (shop) ────────────────────────────────────── */
function initFilterChips() {
  document.querySelectorAll('.filter-group').forEach(group => {
    group.querySelectorAll('.filter-chip').forEach(chip => {
      chip.addEventListener('click', () => {
        const multi = group.dataset.multi !== undefined;
        if (!multi) group.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
        chip.classList.toggle('active');
        applyFilters();
      });
    });
  });
}

function applyFilters() {
  const active = [...document.querySelectorAll('.filter-chip.active')].map(c => c.dataset.filter);
  const products = document.querySelectorAll('[data-product-type]');
  products.forEach(p => {
    const type = p.dataset.productType;
    p.style.display = (!active.length || active.includes(type) || active.includes('all')) ? '' : 'none';
  });
}

/* ─── Search ─────────────────────────────────────────── */
function initSearch() {
  // Ensure an overlay exists. If the template didn't render it, create one dynamically.
  let overlay = document.getElementById('search-overlay');
  const ensureOverlay = () => {
    if (overlay) return overlay;
    const wrapper = document.createElement('div');
    wrapper.id = 'search-overlay';
    wrapper.className = 'fixed inset-0 z-[999] flex items-start justify-center pt-20 md:pt-32 px-6';
    wrapper.style.cssText = 'opacity:0; visibility:hidden; pointer-events:none; background-color: rgba(253,250,247,0.75); backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%);';
    wrapper.innerHTML = `
      <div class="relative w-full max-w-3xl" id="search-content">
        <button id="search-close" class="absolute -top-12 md:-top-16 right-0 text-shadow hover:rotate-90 transition-transform duration-300" aria-label="Fermer">
          <svg width="28" height="28" class="md:w-8 md:h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <input type="text" id="search-overlay-input" placeholder="Que recherchez-vous ?" autocomplete="off" class="w-full bg-transparent text-2xl md:text-5xl font-display italic text-shadow border-b border-shadow/10 outline-none pb-4 placeholder:text-shadow/20">
        <div id="search-results" class="hidden mt-4 max-h-[60vh] overflow-y-auto rounded-sm shadow-2xl border border-olive/10" style="background: rgba(253,250,247,0.85); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);"></div>
        <div id="search-suggestions" class="hidden mt-3 flex flex-wrap gap-2"></div>
      </div>
    `;
    document.body.appendChild(wrapper);
    overlay = wrapper;
    return overlay;
  };

  overlay = ensureOverlay();
  const input = document.getElementById('search-overlay-input');
  const resultsDiv = document.getElementById('search-results');
  const suggestionsDiv = document.getElementById('search-suggestions');

  const openSearch = () => {
    overlay.style.transition = 'opacity 0.4s ease';
    overlay.style.opacity = '0';
    overlay.style.visibility = 'visible';
    overlay.style.pointerEvents = 'auto';
    document.body.style.overflow = 'hidden';
    overlay.offsetHeight;
    overlay.style.opacity = '1';
    setTimeout(() => { try { input.focus(); } catch(e){} }, 50);
    if (!suggestionsDiv.dataset.loaded) {
      loadCategories();
      suggestionsDiv.dataset.loaded = '1';
    }
  };

  const closeSearch = () => {
    overlay.style.transition = 'opacity 0.3s ease';
    overlay.style.opacity = '0';
    setTimeout(() => {
      overlay.style.visibility = 'hidden';
      overlay.style.pointerEvents = 'none';
      document.body.style.overflow = '';
      if (input) input.value = '';
      resetSearch();
    }, 300);
  };

  function resetSearch() {
    if (input) input.value = '';
    if (resultsDiv) { resultsDiv.innerHTML = ''; resultsDiv.classList.add('hidden'); }
    if (suggestionsDiv) { suggestionsDiv.innerHTML = ''; suggestionsDiv.classList.add('hidden'); }
  }

  document.querySelectorAll('[data-search-open]').forEach(btn => {
    btn.addEventListener('click', (e) => { e.preventDefault(); openSearch(); });
  });

  document.getElementById('search-close')?.addEventListener('click', closeSearch);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closeSearch(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.style.visibility === 'visible') closeSearch();
  });

  let debounceTimer;
  async function loadCategories() {
    try {
      const res = await fetch('/api/categories');
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const categories = await res.json();
      renderSuggestions(categories);
    } catch (err) {
      renderSuggestions([
        { name: 'Ensemble Lin', slug: 'ensemble-lin' },
        { name: 'Nouveautés',   slug: 'nouveautes' }
      ]);
    }
  }

  function renderSuggestions(categories) {
    if (!categories || categories.length === 0) {
      suggestionsDiv.innerHTML = '';
      return;
    }
    suggestionsDiv.innerHTML = '';
    suggestionsDiv.classList.remove('hidden');
    categories.forEach(cat => {
      const btn = document.createElement('button');
      btn.className = 'px-4 md:px-5 py-2 border border-shadow/10 rounded-full text-shadow hover:bg-shadow hover:text-cream transition-all duration-300 text-xs md:text-sm';
      btn.textContent = cat.name;
      btn.addEventListener('click', () => { window.location.href = '/shop?category=' + encodeURIComponent(cat.slug); });
      suggestionsDiv.appendChild(btn);
    });
  }

  input?.addEventListener('input', debounce(e => {
    const q = e.target.value.trim();
    clearTimeout(debounceTimer);

    if (q.length < 2) {
      if (resultsDiv) { resultsDiv.innerHTML = ''; resultsDiv.classList.add('hidden'); }
      return;
    }

    debounceTimer = setTimeout(async () => {
      try {
        const res = await fetch('/api/search?q=' + encodeURIComponent(q));
        const products = await res.json();
        if (products.length === 0) {
          resultsDiv.innerHTML = '<div class="p-4 text-center text-sm text-mauve">Aucun résultat</div>';
        } else {
          resultsDiv.innerHTML = products.map(p => `
            <a href="/produit/${p.slug}" class="flex items-center gap-4 p-3 hover:bg-white/30 transition-colors border-b border-shadow/5 last:border-0">
              <img src="${p.image}" alt="${p.name}" class="w-12 h-16 object-cover flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <p class="font-display italic text-sm text-shadow">${p.name}</p>
                <p class="text-xs text-mauve">${p.price} DT</p>
              </div>
            </a>
          `).join('');
        }
        resultsDiv.classList.remove('hidden');
      } catch (err) {
        resultsDiv.innerHTML = '<div class="p-4 text-center text-sm text-red-400">Erreur de recherche</div>';
        resultsDiv.classList.remove('hidden');
      }
    }, 300);
  }, 300));
}

/* ─── Toast ─────────────────────────────────────────── */
let toastTimeout = null;

function showToast(message, duration = 2800) {
  if (toastTimeout) clearTimeout(toastTimeout);

  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    document.body.appendChild(toast);
  }

  toast.textContent = message;
  toast.classList.add('show');

  toastTimeout = setTimeout(() => {
    toast.classList.remove('show');
    toastTimeout = null;
  }, duration);
}
window.showToast = showToast;

/* ─── Page Enter Animation ───────────────────────────────────── */
function initPageTransition() {
  document.querySelector('main')?.classList.add('page-enter');
}

/* ─── Sticky Header Shadow ───────────────────────────────────── */
function initHeaderScroll() {
  const header = document.querySelector('.site-header');
  window.addEventListener('scroll', () => {
    header?.classList.toggle('shadow-md', window.scrollY > 12);
  }, { passive: true });
}

/* ─── Utils ─────────────────────────────────────────── */
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

/* ─── Boot ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  new AuthModal();
  new CartDrawer();
  initQtySteppers();
  initSizeSelector();
  initCardSizePicker();
  initFilterChips();
  initSearch();
  initPageTransition();
  initHeaderScroll();
  initAddToCart();
});
