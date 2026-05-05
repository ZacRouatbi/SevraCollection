/**
 * Sevra Collection - Global Cart Actions
 * Fixed: toast disappears correctly after 2.8 seconds
 * Uses window.showToast (creates it if missing)
 */
(function() {
    "use strict";

    // ✅ CSRF Token cache
    let cartCsrfToken = null;

    // ✅ Toast timer handle (to cancel previous timeout)
let toastTimeout = null;

function showToast(message, duration = 2800) {
  // Remove any existing toast first
  const existingToast = document.querySelector('.toast');
  if (existingToast) existingToast.remove();
  if (toastTimeout) clearTimeout(toastTimeout);

  // Create new toast
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);

  // Force reflow then add 'show' class for animation
  toast.offsetHeight;
  toast.classList.add('show');

  // Schedule removal
  toastTimeout = setTimeout(() => {
    toast.classList.remove('show');
    // After transition ends (0.35s), remove from DOM
    setTimeout(() => {
      if (toast.parentNode) toast.remove();
    }, 350);
    toastTimeout = null;
  }, duration);
}

    // Make it globally available (used in other scripts)
    window.showToast = window.showToast || showToast;

    async function getCartCsrfToken() {
        if (cartCsrfToken) return cartCsrfToken;
        try {
            const response = await fetch('/cart/csrf-token');
            if (!response.ok) throw new Error('Failed to fetch CSRF token');
            const data = await response.json();
            cartCsrfToken = data.token;
            return cartCsrfToken;
        } catch (error) {
            console.error('Failed to get CSRF token:', error);
            return null;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.addEventListener('click', async (e) => {
            // 1. Detect the button
            const btn = e.target.closest('.js-quick-add, .js-add-to-cart');
            if (!btn) return;

            // 2. Stop default behavior
            e.preventDefault();
            e.stopPropagation();

            const isProductPage = btn.classList.contains('js-add-to-cart');
            let productId;
            let quantity = 1;
            let size = null;
            let color = null;

            // 3. Gather Data
            if (isProductPage) {
                // Get product ID
                productId = document.getElementById('product-id-input')?.value;

                // Get quantity
                const qtyDisplay = document.getElementById('qty-display');
                quantity = qtyDisplay ? parseInt(qtyDisplay.textContent) : 1;

                // Get selected size (if size selector exists)
                const selectedSizeBtn = document.querySelector('#size-grid .size-btn.selected');
                if (selectedSizeBtn) {
                    size = selectedSizeBtn.dataset.size;
                }

                // Check if size is required but not selected
                const sizeGrid = document.getElementById('size-grid');
                if (sizeGrid && !selectedSizeBtn) {
                    const errorMsg = document.getElementById('size-error');
                    if (errorMsg) {
                        errorMsg.style.display = 'block';
                        errorMsg.textContent = 'Veuillez sélectionner une taille.';
                    }
                    return;
                }

                // Get selected color (if color selector exists)
                const selectedColorBtn = document.querySelector('#color-grid .size-btn.selected');
                if (selectedColorBtn) {
                    color = selectedColorBtn.dataset.color;
                }
            } else {
                // Card Logic: Get ID from the button dataset or parent article
                productId = btn.dataset.productId || btn.getAttribute('data-product-id');
                if (!productId || productId === "") {
                    const article = btn.closest('article');
                    productId = article?.dataset.id || article?.getAttribute('data-id');
                }
            }

            // 4. Verification
            if (!productId) {
                console.error('Sevra Cart: No Product ID found.');
                showToast('Erreur: produit non trouvé');
                return;
            }

            // 5. UI Loading State
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span style="opacity:0.5">...</span>';

            try {
                const token = await getCartCsrfToken();

                const headers = {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                if (token) {
                    headers['X-CSRF-Token'] = token;
                }

                const requestBody = {
                    product_id: parseInt(productId),
                    quantity: parseInt(quantity)
                };

                if (size) requestBody.size = size;
                if (color) requestBody.color = color;

                const response = await fetch('/cart/add', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(requestBody)
                });

                if (response.ok) {
                    const data = await response.json();

                    // Success triggers
                    if (window.refreshSevraCart) {
                        await window.refreshSevraCart();
                    }
                    showToast('Ajouté au panier ✓');

                    if (window.cartDrawer) {
                        window.cartDrawer.open();
                    }

                    if (data.count && window.updateCartBadge) {
                        window.updateCartBadge(data.count);
                    }
                } else if (response.status === 403) {
                    const errorData = await response.json();
                    console.error('CSRF token invalid:', errorData);
                    showToast('Erreur de sécurité. Veuillez rafraîchir la page.');
                    cartCsrfToken = null;
                } else {
                    const errorData = await response.json();
                    console.error('Cart add error:', response.status, errorData);
                    showToast(errorData.error || 'Erreur lors de l\'ajout au panier');
                }
            } catch (err) {
                console.error('Sevra Cart: Fetch Error', err);
                showToast('Erreur de connexion');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });
})();
