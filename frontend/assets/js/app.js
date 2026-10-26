// ============================================================
// BUYOURS — Frontend JS
// Uses window.BASE_URL (set in header.php) for all API calls
// ============================================================

// -------- API helper --------
function apiUrl(endpoint) {
    const base = (window.BASE_URL || '/').replace(/\/$/, '');
    return base + '/backend/api/' + endpoint;
}

async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
    };
    if (data) options.body = JSON.stringify(data);

    try {
        const response = await fetch(apiUrl(endpoint), options);
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON from', endpoint, text);
            return { success: false, message: 'Server returned invalid response' };
        }
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error occurred' };
    }
}

// -------- Notifications --------
function showNotification(message, type = 'success') {
    let notification = document.querySelector('.notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.className = 'notification';
        document.body.appendChild(notification);
    }
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <span>${type === 'success' ? '✓' : '✕'}</span>
        <span>${message}</span>
    `;
    setTimeout(() => notification.classList.add('show'), 10);
    clearTimeout(window._notifTimer);
    window._notifTimer = setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// ============================================================
// CART FUNCTIONS
// ============================================================

/**
 * Add to cart — the main function
 * @param {number} productId
 * @param {string} size       required
 * @param {number} quantity
 * @param {string} color      optional
 */
async function addToCart(productId, size, quantity = 1, color = '') {
    if (!size) {
        showNotification('Please select a size', 'error');
        return false;
    }

    const result = await apiCall('cart.php?action=add', 'POST', {
        product_id: productId,
        size: size,
        quantity: quantity,
        color: color || '',
    });

    if (result.success) {
        await updateCartCount();
        showNotification(result.message || 'Added to cart!', 'success');
        return true;
    } else {
        showNotification(result.message || 'Failed to add to cart', 'error');
        return false;
    }
}

/**
 * Quick add from product card — with color support
 */
window.quickAddToCart = async function (productId, productName, sizesJson, colorsJson) {
    let size = '';
    let color = '';

    try {
        const sizes = typeof sizesJson === 'string' ? JSON.parse(sizesJson) : sizesJson;
        const colors = typeof colorsJson === 'string' ? JSON.parse(colorsJson) : colorsJson;

        if (Array.isArray(sizes) && sizes.length > 0) {
            size = await promptForSize(sizes, productName);
            if (!size) return; // user cancelled
        }

        if (Array.isArray(colors) && colors.length === 1) {
            color = colors[0];
        } else if (Array.isArray(colors) && colors.length > 1) {
            color = colors[0]; // default to first; user can change on product page
        }
    } catch (e) {
        console.log('JSON parse error', e);
    }

    await addToCart(productId, size, 1, color);
};

/**
 * Ask the user to pick a size via modal (fallback to prompt if no modal)
 */
function promptForSize(sizes, productName) {
    return new Promise((resolve) => {
        // Build a small modal
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
        overlay.innerHTML = `
            <div style="background:#fff;padding:25px;border-radius:12px;max-width:400px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.3);">
                <h3 style="margin-bottom:8px;color:#2c1810;">${productName}</h3>
                <p style="color:#757575;font-size:14px;margin-bottom:15px;">Choose a size:</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
                    ${sizes.map(s => `
                        <button type="button" data-size="${s}"
                            style="padding:10px 16px;border:2px solid #e0e0e0;border-radius:8px;background:#fff;cursor:pointer;font-weight:600;min-width:50px;">
                            ${s}
                        </button>
                    `).join('')}
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" data-cancel style="padding:10px 20px;border:none;background:#f5f5f5;border-radius:8px;cursor:pointer;">Cancel</button>
                    <button type="button" data-confirm style="padding:10px 20px;border:none;background:#ff6b9d;color:#fff;border-radius:8px;cursor:pointer;font-weight:600;">Add to Cart</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        let selected = '';

        overlay.querySelectorAll('[data-size]').forEach(btn => {
            btn.addEventListener('click', () => {
                overlay.querySelectorAll('[data-size]').forEach(b => {
                    b.style.borderColor = '#e0e0e0';
                    b.style.background = '#fff';
                    b.style.color = '#2c1810';
                });
                btn.style.borderColor = '#ff6b9d';
                btn.style.background = '#ff6b9d';
                btn.style.color = '#fff';
                selected = btn.dataset.size;
            });
        });

        overlay.querySelector('[data-cancel]').addEventListener('click', () => {
            overlay.remove();
            resolve('');
        });

        overlay.querySelector('[data-confirm]').addEventListener('click', () => {
            if (!selected) {
                showNotification('Please choose a size', 'error');
                return;
            }
            overlay.remove();
            resolve(selected);
        });
    });
}

async function updateCartItem(cartItemId, quantity) {
    const result = await apiCall('cart.php?action=update', 'POST', {
        cart_item_id: cartItemId,
        quantity: quantity,
    });

    if (result.success) {
        await updateCartCount();
        updateCartTotal(result.total);

        const itemRow = document.querySelector(`[data-cart-item="${cartItemId}"]`);
        if (itemRow) {
            const priceEl = itemRow.querySelector('.item-total');
            if (priceEl) {
                priceEl.textContent = formatMoney(result.item_total || 0);
            }
        }
    } else {
        showNotification(result.message || 'Failed to update cart', 'error');
    }
}

async function removeCartItem(cartItemId) {
    const result = await apiCall('cart.php?action=remove', 'POST', {
        cart_item_id: cartItemId,
    });

    if (result.success) {
        await updateCartCount();
        const itemRow = document.querySelector(`[data-cart-item="${cartItemId}"]`);
        if (itemRow) itemRow.remove();
        updateCartTotal(result.total);
        checkEmptyCart();
        showNotification('Item removed', 'success');
    } else {
        showNotification(result.message || 'Failed to remove item', 'error');
    }
}

async function updateCartCount() {
    const result = await apiCall('cart.php?action=count');
    const countEl = document.querySelector('.cart-count');
    if (countEl && result.count !== undefined) {
        countEl.textContent = result.count;
        countEl.style.display = result.count > 0 ? 'flex' : 'none';
    }
}

function formatMoney(amount) {
    return 'Rs. ' + Math.round(amount).toLocaleString();
}

function updateCartTotal(total) {
    const totalEl = document.querySelector('.cart-total');
    const subtotalEl = document.querySelector('.cart-subtotal');
    const shippingEl = document.querySelector('.cart-shipping');

    const shipping = total > 5000 ? 0 : 400;
    const finalTotal = total + shipping;

    if (subtotalEl) subtotalEl.textContent = formatMoney(total);
    if (shippingEl) shippingEl.textContent = shipping === 0 ? 'FREE' : formatMoney(shipping);
    if (totalEl) totalEl.textContent = formatMoney(finalTotal);
}

function checkEmptyCart() {
    const cartItems = document.querySelectorAll('[data-cart-item]');
    const cartContent = document.querySelector('.cart-items');
    if (cartItems.length === 0 && cartContent) {
        cartContent.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Looks like you haven't added any items yet.</p>
                <a href="products.php" class="btn btn-primary" style="margin-top: 20px;">Shop Now</a>
            </div>
        `;
    }
}

// ============================================================
// PRODUCT FILTERING
// ============================================================
async function filterProducts(formData) {
    const result = await apiCall('products.php?action=filter', 'POST', formData);
    if (result.success && result.html) {
        const grid = document.querySelector('.products-grid');
        if (grid) grid.innerHTML = result.html;
    }
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

// ============================================================
// DOM READY — bind everything
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // --- Product detail page: size selector ---
    const sizeOptions = document.querySelectorAll('.size-option');
    sizeOptions.forEach(option => {
        option.addEventListener('click', function () {
            sizeOptions.forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            const input = this.querySelector('input');
            if (input) input.checked = true;
        });
    });

    // --- Product detail page: qty stepper ---
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = this.parentElement.querySelector('.qty-input');
            if (!input) return;
            let value = parseInt(input.value) || 1;
            if (this.textContent.trim() === '+') value = Math.min(value + 1, 10);
            else value = Math.max(value - 1, 1);
            input.value = value;
        });
    });

    // --- Product detail page: Add to Cart button ---
    const addBtn = document.getElementById('add-to-cart-btn');
    if (addBtn) {
        addBtn.addEventListener('click', async function () {
            const productId = this.dataset.productId;
            const selectedSize = document.querySelector('input[name="size"]:checked');
            const quantity = parseInt(document.querySelector('.qty-input')?.value) || 1;
            const colorEl = document.querySelector('.color-swatch.active');
            const color = colorEl ? colorEl.dataset.color : '';

            if (!selectedSize) {
                showNotification('Please select a size', 'error');
                return;
            }

            this.disabled = true;
            await addToCart(productId, selectedSize.value, quantity, color);
            this.disabled = false;
        });
    }

    // --- Cart page: qty update ---
    document.querySelectorAll('.cart-qty-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const cartItemId = this.dataset.cartItemId;
            const input = this.closest('.quantity-controls').querySelector('.qty-input');
            let value = parseInt(input.value) || 1;
            const action = this.dataset.action;
            if (action === 'increase') value = Math.min(value + 1, 10);
            else value = Math.max(value - 1, 1);
            input.value = value;
            updateCartItem(cartItemId, value);
        });
    });

    // --- Cart page: remove ---
    document.querySelectorAll('.remove-item-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const cartItemId = this.dataset.cartItemId;
            if (confirm('Remove this item from cart?')) {
                removeCartItem(cartItemId);
            }
        });
    });

    // --- Product listing: add-cart-btn fallback (in case onclick not set) ---
    document.querySelectorAll('.add-cart-btn').forEach(btn => {
        if (btn.onclick) return; // already has onclick
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.quickAddToCart(
                this.dataset.productId,
                this.dataset.productName,
                this.dataset.sizes,
                this.dataset.colors
            );
        });
    });

    // --- Mobile menu ---
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navMain = document.querySelector('.nav-main');
    if (mobileMenuBtn && navMain) {
        mobileMenuBtn.addEventListener('click', () => navMain.classList.toggle('show'));
    }

    // --- Gallery thumbs ---
    document.querySelectorAll('.gallery-thumb').forEach((thumb, i) => {
        thumb.addEventListener('click', function () {
            document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const img = this.querySelector('img');
            const mainImg = document.getElementById('main-product-image');
            if (mainImg && img) mainImg.src = img.src;
        });
    });

    // --- Initialize cart count ---
    updateCartCount();
});

// ============================================================
// ADMIN helpers
// ============================================================
async function deleteProduct(productId) {
    if (!confirm('Delete this product?')) return;
    const result = await apiCall('products.php?action=delete', 'POST', { product_id: productId });
    if (result.success) {
        showNotification('Product deleted', 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showNotification(result.message || 'Failed', 'error');
    }
}

async function updateOrderStatus(orderId, status) {
    const result = await apiCall('orders.php?action=update_status', 'POST', {
        order_id: orderId,
        status: status,
    });
    if (result.success) showNotification('Order updated', 'success');
    else showNotification('Failed', 'error');
}

function displayRating(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<span style="color:${i <= rating ? '#ffb347' : '#ddd'};">★</span>`;
    }
    return html;
}