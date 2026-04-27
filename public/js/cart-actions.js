/**
 * Sevra Collection - Global Cart Actions
 * Integrated Fix for Card IDs and Detail Page
 */
(function() {
    "use strict";

    document.addEventListener('DOMContentLoaded', () => {
        console.log('Sevra Cart System: Active');

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

            // 3. Gather Data
            if (isProductPage) {
                productId = document.getElementById('product-id-input')?.value;
                quantity = document.getElementById('qty-display')?.textContent || 1;

                // Size validation for product page
                const sizeGrid = document.getElementById('size-grid');
                const selectedSize = document.querySelector('#size-grid .size-btn.selected');
                if (sizeGrid && !selectedSize) {
                    const errorMsg = document.getElementById('size-error');
                    if (errorMsg) errorMsg.style.display = 'block';
                    return;
                }
            } else {
                // Card Logic: Get ID from the button dataset or parent article
                productId = btn.dataset.productId || btn.getAttribute('data-product-id');
                if (!productId || productId === "") {
                    productId = btn.closest('article')?.dataset.id;
                }
            }

            // 4. Verification
            if (!productId) {
                console.error('Sevra Cart: No Product ID found.');
                return;
            }

            // 5. UI Loading State
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span style="opacity:0.5">...</span>';

            try {
                const response = await fetch('/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: parseInt(quantity)
                    })
                });

                if (response.ok) {
                    // Success triggers
                    if (window.refreshSevraCart) await window.refreshSevraCart();
                    if (window.showToast) window.showToast('Ajouté ✓');
                    if (window.cartDrawer) window.cartDrawer.open();
                } else {
                    // Error fallback: Redirect to product page
                    const cardLink = btn.closest('article')?.querySelector('a')?.href;
                    if (cardLink) window.location.href = cardLink;
                }
            } catch (err) {
                console.error('Sevra Cart: Fetch Error', err);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });
})();
