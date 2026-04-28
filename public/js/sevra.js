// sevra.js - Updated with CSRF token handling for cart operations

/**
 * sevra.js — Sevra Collection frontend interactions
 * Handles: auth modal, cart drawer, quantity steppers, search, toast, page transitions
 */

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

/* ─── Cart Drawer ────────────────────────────────────────────── */
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
        // ✅ Get CSRF token before request
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

/* ─── Quantity Stepper ───────────────────────────────────────── */
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

/* ─── Size Selector ──────────────────────────────────────────── */
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

/* ─── Search ─────────────────────────────────────────────────── */
function initSearch() {
  const overlay = document.getElementById('search-overlay');
  const input   = document.getElementById('search-input');
  if (!overlay) return;

  const openSearch = () => {
    overlay.style.visibility    = 'visible';
    overlay.style.pointerEvents = 'auto';
    document.body.style.overflow = 'hidden';
    gsap.fromTo(overlay,
      { opacity: 0 },
      { opacity: 1, duration: 0.4, ease: 'power2.out',
        onComplete: () => setTimeout(() => input?.focus(), 50) }
    );
  };

  const closeSearch = () => {
    gsap.to(overlay, {
      opacity: 0, duration: 0.3,
      onComplete: () => {
        overlay.style.visibility    = 'hidden';
        overlay.style.pointerEvents = 'none';
        document.body.style.overflow = '';
        if (input) input.value = '';
      }
    });
  };

  document.querySelectorAll('[data-search-open]').forEach(btn => {
    btn.addEventListener('click', (e) => { e.preventDefault(); openSearch(); });
  });

  document.getElementById('search-close')?.addEventListener('click', closeSearch);
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closeSearch(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.style.visibility === 'visible') closeSearch();
  });

  input?.addEventListener('input', debounce(e => {
    const q = e.target.value.trim();
    if (q.length >= 2) {
      // TODO: fetch suggestions from API
    }
  }, 300));
}

/* ─── Toast ──────────────────────────────────────────────────── */
function showToast(message, duration = 2800) {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), duration);
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

/* ─── Utils ──────────────────────────────────────────────────── */
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

/* ─── Boot ───────────────────────────────────────────────────── */
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
