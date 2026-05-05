// sevra.js - Sevra Collection frontend interactions
// Handles: auth modal, cart drawer, quantity steppers, search, toast, page transitions

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

/* ─── Size Selector ──────────────────────────────────── */
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
  const overlay = document.getElementById('search-overlay');
  const input   = document.getElementById('search-input');
  if (!overlay) return;

  // ✅ FIXED: Pure CSS transitions instead of GSAP
  const openSearch = () => {
    overlay.style.transition = 'opacity 0.4s ease';
    overlay.style.opacity = '0';
    overlay.style.visibility = 'visible';
    overlay.style.pointerEvents = 'auto';
    document.body.style.overflow = 'hidden';

    // Trigger reflow before starting transition
    overlay.offsetHeight;

    overlay.style.opacity = '1';

    // setTimeout(() => {
    //   input?.focus();
    //   if (!categoriesLoaded) loadCategories();
    // }, 50);
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
    resultsDiv.innerHTML = '';
    resultsDiv.classList.add('hidden');
    suggestionsDiv.innerHTML = '';
    suggestionsDiv.classList.add('hidden');
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
  let categoriesLoaded = false;
  const resultsDiv = document.getElementById('search-results');
  const suggestionsDiv = document.getElementById('search-suggestions');

  // ── Load categories (cached via server) ─────────────────
  async function loadCategories() {
    try {
      const res = await fetch('/api/categories');
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const categories = await res.json();
      renderSuggestions(categories);
    } catch (err) {
      // Fallback if API fails
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
      btn.addEventListener('click', () => {
        window.location.href = '/shop?category=' + encodeURIComponent(cat.slug);
      });
      suggestionsDiv.appendChild(btn);
    });
    categoriesLoaded = true;
  }

  input?.addEventListener('input', debounce(e => {
    const q = e.target.value.trim();
    clearTimeout(debounceTimer);

    if (q.length < 2) {
      resultsDiv.innerHTML = '';
      resultsDiv.classList.add('hidden');
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
  // Clear any pending removal
  if (toastTimeout) clearTimeout(toastTimeout);

  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    document.body.appendChild(toast);
  }

  toast.textContent = message;
  toast.classList.add('show');

  // Store the timeout ID so we can cancel it next time
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
  initFilterChips();
  initSearch();
  initPageTransition();
  initHeaderScroll();
  initAddToCart();
});
