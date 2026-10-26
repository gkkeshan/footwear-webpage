// ============================================================
// BUYOURS — Admin JS
// Uses window.BASE_URL (set in admin-header.php) for all API calls
// ============================================================

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

// Admin product delete
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

// Admin order status update
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

function getColorValue(colorName) {
    const colorMap = {
        'Pink': '#ffb6c1',
        'White': '#ffffff',
        'Purple': '#8b5fbf',
        'Black': '#000000',
        'Gold': '#ffd700',
        'Brown': '#8b4513',
        'Silver': '#c0c0c0',
        'Navy': '#000080',
        'Tan': '#d2b48c'
    };
    return colorMap[colorName] || '#808080';
}
