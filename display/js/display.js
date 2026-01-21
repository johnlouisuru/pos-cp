// display/js/display.js - COMPLETE VERSION
let refreshInterval;
let refreshTime = parseInt(document.getElementById('refresh-interval').textContent) || 30;
let soundEnabled = true;
let currentOrders = []; // Store current orders for comparison

// Auto-refresh system
function startAutoRefresh() {
    const intervalSeconds = refreshTime;
    
    // Countdown timer
    setInterval(() => {
        refreshTime--;
        document.getElementById('refresh-countdown').textContent = refreshTime;
        
        if (refreshTime <= 0) {
            refreshDisplay();
            refreshTime = intervalSeconds;
        }
    }, 1000);
    
    // Full refresh every interval
    setInterval(refreshDisplay, intervalSeconds * 1000);
}

// Refresh display content
function refreshDisplay() {
    $.ajax({
        url: 'api/get-orders.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Check for new orders (compare with currentOrders)
                const newOrderCount = countNewOrders(data.orders);
                
                updateOrdersDisplay(data.orders);
                updateOrderCount(data.orders.length);
                updateLastUpdated();
                getNextOrderNumber();
                
                // Play sound if new orders
                if (soundEnabled && newOrderCount > 0) {
                    playNotificationSound();
                    showAlert(`New order received! (${newOrderCount} new)`, 'success');
                }
                
                // Store current orders for next comparison
                currentOrders = data.orders;
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to refresh orders:', error);
            showAlert('Failed to refresh orders. Check connection.', 'danger');
        }
    });
    
    // Reset countdown to current interval
    refreshTime = parseInt(document.getElementById('refresh-interval').textContent) || 30;
}

// Count new orders by comparing with previous
function countNewOrders(newOrders) {
    if (currentOrders.length === 0) return newOrders.length;
    
    const currentOrderIds = currentOrders.map(order => order.order_id);
    const newOrderIds = newOrders.map(order => order.order_id);
    
    // Find orders that are in newOrders but not in currentOrders
    const newOrderCount = newOrderIds.filter(id => !currentOrderIds.includes(id)).length;
    
    return newOrderCount;
}

// Update the display with new orders
function updateOrdersDisplay(orders) {
    const container = $('#orders-container');
    
    if (orders.length === 0) {
        container.html(`
            <div class="no-orders text-center py-5">
                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                <h3 class="text-muted">No orders in progress</h3>
                <p class="text-muted">Waiting for new orders...</p>
            </div>
        `);
        return;
    }
    
    // Generate HTML for each order
    let html = '';
    
    orders.forEach(order => {
        const statusClass = 'status-' + order.status;
        const statusIcons = {
            'waiting': 'fas fa-clock',
            'preparing': 'fas fa-utensils',
            'ready': 'fas fa-check-circle',
            'completed': 'fas fa-box'
        };
        
        // Format time
        const orderTime = new Date(order.order_created);
        const timeString = orderTime.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        // Get items for this order
        const items = order.items ? order.items.split(' | ').slice(0, 3) : []; // Show first 3 items
        
        html += `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="order-card ${statusClass}">
                    <div class="order-header">
                        <div class="order-meta">
                            <span class="order-number badge bg-dark">${escapeHtml(order.order_number)}</span>
                            <span class="order-type badge bg-secondary">${order.order_type.toUpperCase()}</span>
                        </div>
                        <h5 class="customer-name">
                            <i class="fas fa-user"></i>
                            ${escapeHtml(order.display_name)}
                        </h5>
                        <div class="order-timing">
                            <span class="order-time">
                                <i class="fas fa-clock"></i>
                                ${timeString}
                            </span>
                            •
                            <span class="estimated-time">
                                <i class="fas fa-hourglass-half"></i>
                                Est: ${order.estimated_time} min
                            </span>
                        </div>
                    </div>
                    
                    <div class="order-body">
                        <div class="order-items">
                            <h6><i class="fas fa-list"></i> Items:</h6>
                            <ul class="item-list">
        `;
        
        // Add items
        if (items.length > 0) {
            items.forEach(item => {
                html += `<li>${escapeHtml(item)}</li>`;
            });
            
            if (order.items && order.items.split(' | ').length > 3) {
                html += `<li class="text-muted">+${order.items.split(' | ').length - 3} more items</li>`;
            }
        } else {
            html += `<li class="text-muted">No items listed</li>`;
        }
        
        html += `
                            </ul>
                        </div>
                        
                        <div class="order-footer">
                            <div class="status-indicator">
                                <i class="${statusIcons[order.status]}"></i>
                                <span class="status-text">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>
                            </div>
                            <div class="order-amount">
                                <i class="fas fa-receipt"></i>
                                ₱${parseFloat(order.total_amount).toFixed(2)}
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-actions">
                        ${getStatusButtons(order.order_id, order.order_number, order.status)}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.html(html);
}

// Generate status buttons based on current status
function getStatusButtons(orderId, orderNumber, currentStatus) {
    let buttons = '';
    
    switch(currentStatus) {
        case 'waiting':
            buttons = `
                <button class="btn btn-sm btn-warning" 
                        onclick="updateOrderStatus(${orderId}, 'preparing', '${orderNumber}')">
                    <i class="fas fa-play"></i> Start Preparing
                </button>
                <button class="btn btn-sm btn-danger" 
                        onclick="cancelOrder(${orderId}, '${orderNumber}')">
                    <i class="fas fa-times"></i> Cancel
                </button>
            `;
            break;
            
        case 'preparing':
            buttons = `
                <button class="btn btn-sm btn-success" 
                        onclick="updateOrderStatus(${orderId}, 'ready', '${orderNumber}')">
                    <i class="fas fa-check"></i> Mark Ready
                </button>
                <button class="btn btn-sm btn-danger" 
                        onclick="cancelOrder(${orderId}, '${orderNumber}')">
                    <i class="fas fa-times"></i> Cancel
                </button>
            `;
            break;
            
        case 'ready':
            buttons = `
                <button class="btn btn-sm btn-primary" 
                        onclick="updateOrderStatus(${orderId}, 'completed', '${orderNumber}')">
                    <i class="fas fa-box"></i> Mark Served
                </button>
                <button class="btn btn-sm btn-info" 
                        onclick="notifyCustomer('${orderNumber}')">
                    <i class="fas fa-bell"></i> Notify
                </button>
                <button class="btn btn-sm btn-danger" 
                        onclick="cancelOrder(${orderId}, '${orderNumber}')">
                    <i class="fas fa-times"></i> Cancel
                </button>
            `;
            break;
            
        default:
            buttons = `
                <button class="btn btn-sm btn-secondary" disabled>
                    <i class="fas fa-ban"></i> No Actions
                </button>
            `;
    }
    
    // Always show details button
    // buttons += `
    //     <button class="btn btn-sm btn-outline-secondary" 
    //             onclick="viewOrderDetails(${orderId})">
    //         <i class="fas fa-eye"></i> Details
    //     </button>
    // `;
    
    return buttons;
}

// Utility functions
function updateOrderCount(count) {
    $('#order-count').text(count + ' active order' + (count !== 1 ? 's' : ''));
}

function updateLastUpdated() {
    const now = new Date();
    const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second: '2-digit'});
    $('#last-updated').text('Updated: ' + timeString);
}

function getNextOrderNumber() {
    $.ajax({
        url: 'api/get-next-order.php',
        success: function(data) {
            if (data.success) {
                $('#next-order-number').text(data.nextNumber);
            }
        }
    });
}

function playNotificationSound() {
    if (soundEnabled) {
        const sound = document.getElementById('notification-sound');
        sound.currentTime = 0;
        sound.play().catch(e => console.log('Audio play failed:', e));
    }
}

function showAlert(message, type = 'info') {
    // Remove any existing alerts
    $('.alert.position-fixed').remove();
    
    const alert = $(`
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(alert);
    
    // Auto-remove after 5 seconds for success/info, 10 seconds for danger
    const timeout = type === 'danger' ? 10000 : 5000;
    setTimeout(() => {
        alert.alert('close');
    }, timeout);
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.log('Error enabling fullscreen:', err.message);
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// Order management functions
function cancelOrder(orderId, orderNumber) {
    if (!confirm(`Cancel order ${orderNumber}? This cannot be undone.`)) return;
    
    $.ajax({
        url: 'api/cancel-order.php',
        method: 'POST',
        data: {
            order_id: orderId,
            order_number: orderNumber
        },
        success: function(response) {
            if (response.success) {
                showAlert(`Order ${orderNumber} cancelled!`, 'danger');
                refreshDisplay();
            } else {
                showAlert('Failed to cancel order: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Network error. Please try again.', 'danger');
        }
    });
}

function updateOrderStatus(orderId, newStatus, orderNumber = '') {
    const statusMessages = {
        'preparing': 'Start preparing this order?',
        'ready': 'Mark order as ready for pickup?',
        'completed': 'Mark order as served/completed?'
    };
    
    const message = statusMessages[newStatus] || `Change order status to "${newStatus}"?`;
    
    if (!confirm(message)) return;
    
    $.ajax({
        url: 'api/update-status.php',
        method: 'POST',
        data: {
            order_id: orderId,
            status: newStatus,
            order_number: orderNumber
        },
        success: function(response) {
            if (response.success) {
                const successMessage = orderNumber 
                    ? `Order ${orderNumber} updated to ${newStatus}!`
                    : `Order status updated to ${newStatus}!`;
                    
                showAlert(successMessage, 'success');
                refreshDisplay();
                
                // Play sound for important status changes
                if (['ready', 'completed'].includes(newStatus) && soundEnabled) {
                    playNotificationSound();
                }
            } else {
                showAlert('Failed to update status: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Network error. Please try again.', 'danger');
        }
    });
}

function viewOrderDetails(orderId) {
    window.open(`../../order-details.php?order_id=${orderId}`, '_blank', 'width=800,height=600');
}

function notifyCustomer(orderNumber) {
    if (!confirm(`Send notification to customer for order ${orderNumber}?`)) return;
    
    $.ajax({
        url: 'api/notify-customer.php',
        method: 'POST',
        data: { order_number: orderNumber },
        success: function(response) {
            if (response.success) {
                showAlert('Customer notified!', 'success');
            } else {
                showAlert('Failed to notify customer: ' + response.message, 'warning');
            }
        },
        error: function() {
            showAlert('Network error. Please try again.', 'danger');
        }
    });
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize on page load
$(document).ready(function() {
    // Update current time every second
    function updateCurrentTime() {
        const now = new Date();
        $('#current-time').text(now.toLocaleTimeString('en-US', { 
            hour12: true, 
            hour: '2-digit', 
            minute: '2-digit' 
        }));
    }
    
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
    
    // Sound toggle
    $('#soundToggle').change(function() {
        soundEnabled = this.checked;
        localStorage.setItem('soundEnabled', soundEnabled);
        showAlert(`Sound ${soundEnabled ? 'enabled' : 'disabled'}`, 'info');
    });
    
    // Load sound preference
    const savedSoundPref = localStorage.getItem('soundEnabled');
    if (savedSoundPref !== null) {
        soundEnabled = savedSoundPref === 'true';
        $('#soundToggle').prop('checked', soundEnabled);
    }
    
    // Initialize auto-refresh
    startAutoRefresh();
    
    // Get next order number
    getNextOrderNumber();
    
    // Keyboard shortcuts
    $(document).keydown(function(e) {
        // Refresh with F5
        if (e.key === 'F5') {
            e.preventDefault();
            refreshDisplay();
            showAlert('Manual refresh initiated', 'info');
        }
        
        // Toggle fullscreen with F11
        if (e.key === 'F11') {
            e.preventDefault();
            toggleFullscreen();
        }
    });
    
    // Force initial refresh
    setTimeout(refreshDisplay, 100);
});

// Export functions for global access (if needed)
window.refreshDisplay = refreshDisplay;
window.cancelOrder = cancelOrder;
window.updateOrderStatus = updateOrderStatus;
window.toggleFullscreen = toggleFullscreen;