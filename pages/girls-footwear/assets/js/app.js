// Girls' Footwear E-Commerce JavaScript
// Handles AJAX operations, cart updates, and interactivity

// API base URL
const API_BASE = '../api/';

// Notification system
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <span>${type === 'success' ? '✓' : '✕'}</span>
        <span>${message}</span>
    `;
    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// AJAX helper
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        },
    };

    if (data) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, options);
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error occurred' };
    }
}

// Cart Functions
async function addToCart(productId, size, quantity = 1) {
    const result = await apiCall('cart.php?action=add', 'POST', {
        product_id: productId,
        size: size,
        quantity: quantity,
    });

    if (result.success) {
        updateCartCount();
        showNotification('Added to cart!', 'success');
    } else {
        showNotification(result.message || 'Failed to add to cart', 'error');
    }
}

async function updateCartItem(cartItemId, quantity) {
    const result = await apiCall('cart.php?action=update', 'POST', {
        cart_item_id: cartItemId,
        quantity: quantity,
    });

    if (result.success) {
        updateCartCount();
        updateCartTotal(result.total);
        // Update the specific item price
        const itemRow = document.querySelector(`[data-cart-item="${cartItemId}"]`);
        if (itemRow) {
            const priceEl = itemRow.querySelector('.item-total');
            if (priceEl) {
                priceEl.textContent = `$${(result.item_total || 0).toFixed(2)}`;
            }
        }
        showNotification('Cart updated', 'success');
    } else {
        showNotification(result.message || 'Failed to update cart', 'error');
    }
}

async function removeCartItem(cartItemId) {
    const result = await apiCall('cart.php?action=remove', 'POST', {
        cart_item_id: cartItemId,
    });

    if (result.success) {
        updateCartCount();
        const itemRow = document.querySelector(`[data-cart-item="${cartItemId}"]`);
        if (itemRow) {
            itemRow.remove();
        }
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

function updateCartTotal(total) {
    const totalEl = document.querySelector('.cart-total');
    const subtotalEl = document.querySelector('.cart-subtotal');
    const shippingEl = document.querySelector('.cart-shipping');
    const taxEl = document.querySelector('.cart-tax');

    if (totalEl) {
        totalEl.textContent = `$${(total || 0).toFixed(2)}`;
    }
    if (subtotalEl) {
        subtotalEl.textContent = `$${(total || 0).toFixed(2)}`;
    }
    if (shippingEl) {
        const shipping = total > 75 ? 0 : 5.99;
        shippingEl.textContent = shipping === 0 ? 'FREE' : `$${shipping.toFixed(2)}`;
    }
    if (taxEl && total !== undefined) {
        const shipping = total > 75 ? 0 : 5.99;
        const subtotal = total;
        const tax = (subtotal + shipping) * 0.08;
        taxEl.textContent = `$${tax.toFixed(2)}`;
    }
}

function checkEmptyCart() {
    const cartItems = document.querySelectorAll('[data-cart-item]');
    const emptyState = document.querySelector('.empty-state');
    const cartContent = document.querySelector('.cart-items');

    if (cartItems.length === 0 && emptyState && cartContent) {
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

// Product Filtering
async function filterProducts(formData) {
    const result = await apiCall('products.php?action=filter', 'POST', formData);

    if (result.success && result.html) {
        const productsGrid = document.querySelector('.products-grid');
        if (productsGrid) {
            productsGrid.innerHTML = result.html;
        }
    }
}

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search functionality
const searchInput = document.getElementById('search-input');
if (searchInput) {
    searchInput.addEventListener('input', debounce((e) => {
        const query = e.target.value.trim();
        if (query.length >= 2) {
            filterProducts({ search: query });
        }
    }, 300));
}

// Product page interactions
document.addEventListener('DOMContentLoaded', function() {
    // Size selector
    const sizeOptions = document.querySelectorAll('.size-option');
    sizeOptions.forEach(option => {
        option.addEventListener('click', function() {
            sizeOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            this.querySelector('input').checked = true;
        });
    });

    // Quantity selector
    const qtyBtns = document.querySelectorAll('.qty-btn');
    qtyBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.qty-input');
            let value = parseInt(input.value) || 1;

            if (this.textContent === '+') {
                value = Math.min(value + 1, 10);
            } else {
                value = Math.max(value - 1, 1);
            }

            input.value = value;
        });
    });

    // Add to cart button
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const selectedSize = document.querySelector('input[name="size"]:checked');
            const quantity = parseInt(document.querySelector('.qty-input')?.value) || 1;

            if (!selectedSize) {
                showNotification('Please select a size', 'error');
                return;
            }

            addToCart(productId, selectedSize.value, quantity);
        });
    }

    // Cart quantity updates
    document.querySelectorAll('.cart-qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const cartItemId = this.dataset.cartItemId;
            const input = this.closest('.quantity-controls').querySelector('.qty-input');
            let value = parseInt(input.value) || 1;

            if (this.textContent === '+') {
                value = Math.min(value + 1, 10);
            } else {
                value = Math.max(value - 1, 1);
            }

            updateCartItem(cartItemId, value);
            input.value = value;
        });
    });

    // Remove from cart
    document.querySelectorAll('.remove-item-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const cartItemId = this.dataset.cartItemId;
            if (confirm('Remove this item from cart?')) {
                removeCartItem(cartItemId);
            }
        });
    });

    // Product filter form
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.addEventListener('change', debounce(function() {
            const formData = new FormData(filterForm);
            const data = Object.fromEntries(formData.entries());
            filterProducts(data);
        }, 500));
    }

    // Sort select
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const formData = new FormData(filterForm || document.createElement('form'));
            formData.set('sort', this.value);
            filterProducts(Object.fromEntries(formData.entries()));
        });
    }

    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navMain = document.querySelector('.nav-main');
    if (mobileMenuBtn && navMain) {
        mobileMenuBtn.addEventListener('click', function() {
            navMain.classList.toggle('show');
        });
    }

    // Gallery thumbnails
    const galleryThumbs = document.querySelectorAll('.gallery-thumb');
    galleryThumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
            galleryThumbs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const mainImage = document.querySelector('.gallery-main');
            if (mainImage) {
                mainImage.textContent = this.textContent;
            }
        });
    });

    // Checkout step navigation
    const checkoutSteps = document.querySelectorAll('.checkout-step');
    checkoutSteps.forEach(step => {
        step.addEventListener('click', function() {
            const stepNum = this.dataset.step;
            // Could implement step navigation here
        });
    });

    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;

            this.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                } else {
                    input.classList.remove('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields', 'error');
            }
        });
    });

    // Initialize cart count
    updateCartCount();

    // Image lazy loading
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
});

// Simple add to cart function (global scope for direct onclick)
window.quickAddToCart = async function(productId, productName, sizesJson) {
    let size = 'default';
    if (sizesJson) {
        try {
            const sizes = JSON.parse(sizesJson);
            if (sizes && sizes.length > 0) {
                size = sizes[0];
            }
        } catch (e) {
            console.log('Error parsing sizes');
        }
    }

    const result = await apiCall('cart.php?action=add', 'POST', {
        product_id: productId,
        size: size,
        quantity: 1
    });

    if (result.success) {
        await updateCartCount();
        showNotification(productName + ' added to cart!', 'success');
    } else {
        showNotification(result.message || 'Failed to add to cart', 'error');
    }
};

// Add click handlers for add-cart-btn buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.add-cart-btn').forEach(function(btn) {
        btn.onclick = function(e) {
            e.preventDefault();
            const productId = btn.getAttribute('data-product-id');
            const productName = btn.getAttribute('data-product-name');
            const sizesJson = btn.getAttribute('data-sizes');
            window.quickAddToCart(productId, productName, sizesJson);
        };
    });
});

// Checkout process
const checkoutStepsElements = document.querySelectorAll('.step');
const checkoutForms = document.querySelectorAll('.checkout-form-container');

function showStep(stepNumber) {
    checkoutStepsElements.forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index + 1 < stepNumber) {
            step.classList.add('completed');
        } else if (index + 1 === stepNumber) {
            step.classList.add('active');
        }
    });

    checkoutForms.forEach((form, index) => {
        form.style.display = index + 1 === stepNumber ? 'block' : 'none';
    });
}

// Quick view product modal
function openQuickView(productSlug) {
    // Could implement quick view modal here
    window.location.href = `product.php?slug=${productSlug}`;
}

// Admin functions
async function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product?')) {
        const result = await apiCall('products.php?action=delete', 'POST', { product_id: productId });
        if (result.success) {
            showNotification('Product deleted', 'success');
            location.reload();
        } else {
            showNotification('Failed to delete product', 'error');
        }
    }
}

async function updateOrderStatus(orderId, status) {
    const result = await apiCall('orders.php?action=update-status', 'POST', {
        order_id: orderId,
        status: status,
    });

    if (result.success) {
        showNotification('Order status updated', 'success');
    } else {
        showNotification('Failed to update status', 'error');
    }
}

// Rating display helper
function displayRating(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            html += '<span style="color: #ffb347;">★</span>';
        } else {
            html += '<span style="color: #ddd;">★</span>';
        }
    }
    return html;
}
