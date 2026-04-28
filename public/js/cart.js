// cart.js - Updated with CSRF token handling

document.addEventListener('DOMContentLoaded', () => {

  // ✅ CSRF Token cache
  let cartCsrfToken = null;

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

  function toast(msg) {
    const el = document.querySelector('.toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2800);
  }

  function fmt(val) {
    return parseFloat(val).toFixed(2).replace('.', '.') + ' DT';
  }

  /**
   * Fetches the current page again via AJAX to pluck out the fresh drawer content.
   * This ensures the drawer updates without a full page refresh.
   */
  async function refreshDrawer() {
    try {
      const res = await fetch(window.location.href);
      const html = await res.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');

      // 1. Update the items list
      const newContent = doc.querySelector('#cart-items-list');
      const currentDrawer = document.querySelector('#cart-items-list');
      if (newContent && currentDrawer) {
        currentDrawer.innerHTML = newContent.innerHTML;
      }

      // 2. Update the footer/total
      const newFooter = doc.querySelector('#cart-drawer .border-t');
      const currentFooter = document.querySelector('#cart-drawer .border-t');
      if (newFooter && currentFooter) {
        currentFooter.innerHTML = newFooter.innerHTML;
      }

      // 3. Recompute UI (Badge, totals)
      recomputeSummary();

      // 4. Open the drawer if it's not already open
      if (typeof window.openCartDrawer === 'function') {
        window.openCartDrawer();
      } else {
        document.getElementById('cart-drawer')?.classList.add('is-open');
        document.getElementById('cart-overlay')?.classList.add('is-open');
      }
    } catch (err) {
      console.error('Failed to refresh drawer:', err);
    }
  }

  function recomputeSummary() {
    const uniqueItems = new Map();

    // 1. Gather unique items
    document.querySelectorAll('.cart-item[data-item-key]').forEach(row => {
      const key = row.dataset.itemKey;
      const priceEl = row.querySelector('.item-line-total');
      const price = parseFloat(priceEl?.dataset.price ?? 0);
      const qty = parseInt(priceEl?.dataset.qty ?? 1);
      uniqueItems.set(key, { price, qty });
    });

    let subtotal = 0;
    let totalQty = 0;
    uniqueItems.forEach(item => {
      subtotal += item.price * item.qty;
      totalQty += item.qty;
    });

    // 2. Update Subtotal / Total text
    document.querySelectorAll('.page-subtotal, #drawer-total').forEach(el => {
      el.textContent = fmt(subtotal);
    });

    // 3. Update Badge
    const badge = document.getElementById('drawer-count-badge');
    if (badge) {
      badge.textContent = totalQty;
      badge.style.display = totalQty > 0 ? 'flex' : 'none';
    }
  }

  // ── Global Listeners ────────────────────────────────────────────────

  // Listen for the "Product Added" event (Triggered by your product page script)
  document.addEventListener('cart:add-success', () => {
    refreshDrawer();
  });

  // ── Event Delegation ────────────────────────────────────────────────

  document.addEventListener('click', async (e) => {

    // --- REMOVE ITEM ---
    const removeBtn = e.target.closest('.remove-item-btn');
    if (removeBtn) {
      e.preventDefault();
      const key = removeBtn.dataset.itemKey;
      const rows = document.querySelectorAll(`.cart-item[data-item-key="${key}"]`);

      rows.forEach(row => {
        row.style.opacity = '0.4';
        row.style.pointerEvents = 'none';
      });

      try {
        // ✅ Get CSRF token
        const token = await getCartCsrfToken();
        const headers = {
          'Content-Type': 'application/json'
        };
        if (token) {
          headers['X-CSRF-Token'] = token;
        }

        const res = await fetch('/cart/remove', {
          method: 'POST',
          headers: headers,
          body: JSON.stringify({ item_key: key }),
        });

        if (res.ok) {
          rows.forEach(row => row.remove());
          recomputeSummary();
          // If completely empty, show empty state
          if (document.querySelectorAll('.cart-item[data-item-key]').length === 0) {
            const emptyEl = document.getElementById('cart-empty');
            const innerEl = document.getElementById('cart-items-inner');
            if (emptyEl) emptyEl.style.display = 'flex';
            if (innerEl) innerEl.style.display = 'none';
          }
        } else if (res.status === 403) {
          toast('Erreur de sécurité. Veuillez rafraîchir la page.');
          cartCsrfToken = null; // Reset token
        }
      } catch {
        rows.forEach(row => { row.style.opacity = '1'; row.style.pointerEvents = ''; });
        toast('Erreur — réessayez.');
      }
      return;
    }

    // --- QTY STEPPER ---
    const qtyBtn = e.target.closest('.page-qty-btn');
    if (qtyBtn) {
      e.preventDefault();
      const key = qtyBtn.dataset.itemKey;
      const delta = parseInt(qtyBtn.dataset.delta);
      const displays = document.querySelectorAll(`.page-qty-display[data-item-key="${key}"]`);
      const lineTotals = document.querySelectorAll(`.item-line-total[data-item-key="${key}"]`);

      if (!displays.length) return;

      const current = parseInt(displays[0].textContent);
      const newQty = Math.max(0, Math.min(10, current + delta));

      if (newQty === 0) {
        document.querySelector(`.remove-item-btn[data-item-key="${key}"]`)?.click();
        return;
      }

      displays.forEach(d => d.textContent = newQty);
      lineTotals.forEach(lt => {
        lt.dataset.qty = newQty;
        const price = parseFloat(lt.dataset.price ?? 0);
        lt.textContent = fmt(price * newQty);
      });

      recomputeSummary();

      try {
        // ✅ Get CSRF token
        const token = await getCartCsrfToken();
        const headers = {
          'Content-Type': 'application/json'
        };
        if (token) {
          headers['X-CSRF-Token'] = token;
        }

        const response = await fetch('/cart/update', {
          method: 'POST',
          headers: headers,
          body: JSON.stringify({ item_key: key, qty: newQty }),
        });

        if (response.status === 403) {
          toast('Erreur de sécurité. Veuillez rafraîchir la page.');
          cartCsrfToken = null;
        }
      } catch { /* Fail silently */ }
    }
  });

  // ✅ Expose refresh function globally
  window.refreshSevraCart = async function() {
    console.log("Refreshing Drawer...");
    try {
        const res = await fetch(window.location.href);
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Update items list
        const newItems = doc.querySelector('#cart-items-list');
        if (newItems) document.querySelector('#cart-items-list').innerHTML = newItems.innerHTML;

        // Update footer (total)
        const newFooter = doc.querySelector('#cart-drawer .border-t');
        if (newFooter) document.querySelector('#cart-drawer .border-t').innerHTML = newFooter.innerHTML;

        // Re-run the count/badge logic
        if (typeof recomputeSummary === 'function') recomputeSummary();

        // Open the drawer
        document.getElementById('cart-drawer')?.classList.add('is-open');
        document.getElementById('cart-overlay')?.classList.add('is-open');

    } catch (e) { console.error("Drawer refresh failed", e); }
  };

});
