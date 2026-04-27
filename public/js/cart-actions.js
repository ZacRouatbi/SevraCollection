/**
 * Sevra Collection - Global Cart Actions
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('Sevra Cart System: Active');

    document.addEventListener('click', async (e) => {
        // Find the button (Detail page or Card)
        const btn = e.target.closest('.js-add-to-cart, .js-quick-add');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const isProductPage = btn.classList.contains('js-add-to-cart');

        // --- GATHER DATA ---
        let productId, quantity, selectedSize = null, selectedColor = null;

        if (isProductPage) {
            productId = document.getElementById('product-id-input')?.value;
            quantity = document.getElementById('qty-display')?.textContent || 1;

            const sizeBtn = document.querySelector('#size-grid .size-btn.selected');
            const colorBtn = document.querySelector('#color-grid .size-btn.selected');

            // Validation
            if (document.getElementById('size-grid') && !sizeBtn) {
                document.getElementById('size-error').style.display = 'block';
                return;
            }
            selectedSize = sizeBtn?.dataset.size;
            selectedColor = colorBtn?.dataset.color;
        } else {
            // CARD LOGIC: Get ID directly from the button attribute
            productId = btn.getAttribute('data-product-id');
            quantity = 1;
        }

        console.log(`Attempting to add Product ID: ${productId}`);

        if (!productId || productId === "") {
            console.error("Cart Error: No Product ID found on button.");
            return;
        }

        // --- UI STATE ---
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="animate-pulse">...</span>';

        try {
            const res = await fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: parseInt(quantity),
                    size: selectedSize,
                    color: selectedColor
                }),
            });

            if (res.ok) {
                if (window.refreshSevraCart) await window.refreshSevraCart();
                if (window.showToast) window.showToast('Ajouté ✓');
                if (window.cartDrawer) window.cartDrawer.open();
            } else {
                // FAIL: If it's a card, redirect to details (maybe it needs a size selection)
                if (!isProductPage) {
                    const link = btn.closest('article').querySelector('a').href;
                    window.location.href = link;
                }
            }
        } catch (err) {
            console.error("Fetch Error:", err);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
});
