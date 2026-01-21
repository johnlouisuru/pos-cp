// display/js/kitchen.js
let refreshInterval;
let refreshTime = 15;
let currentStation = 'all';
let soundEnabled = true;

// display/js/kitchen.js - FIXED
function loadKitchenDisplay(stationId = 'all') {
    currentStation = stationId;
    
    // Show loading
    $('#kitchen-stations').html(`
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading kitchen orders...</span>
            </div>
            <p class="mt-2">Loading kitchen display...</p>
        </div>
    `);
    
    $.ajax({
        url: 'kitchen-api/get-kitchen-orders.php', // Make sure this path is correct
        method: 'GET',
        data: { station_id: stationId },
        dataType: 'json',
        success: function(data) {
            console.log('Kitchen API Response:', data); // Debug log
            
            if (data.success) {
                if (data.stations && data.stations.length > 0) {
                    renderKitchenDisplay(data.stations);
                } else {
                    $('#kitchen-stations').html(`
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No items to prepare</h4>
                            <p class="text-muted">All caught up!</p>
                        </div>
                    `);
                }
                updateStats(data.stats || {});
                updateLastUpdated();
                
                if (soundEnabled && data.newItems > 0) {
                    playNotificationSound();
                }
            } else {
                $('#kitchen-stations').html(`
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            API Error: ${data.message || 'Unknown error'}
                            ${data.debug ? '<br><small>' + data.debug + '</small>' : ''}
                        </div>
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            $('#kitchen-stations').html(`
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Failed to load kitchen orders. Error: ${error}
                        <br><small>Check console for details</small>
                    </div>
                </div>
            `);
        }
    });
}

// Update the renderKitchenDisplay function to pass all parameters
function renderKitchenDisplay(stations) {
    if (!stations || stations.length === 0) {
        $('#kitchen-stations').html(`
            <div class="col-12 text-center py-5">
                <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No items to prepare</h4>
                <p class="text-muted">All caught up!</p>
            </div>
        `);
        return;
    }
    
    let html = '';
    
    stations.forEach(station => {
        const color = station.color_code || '#3498db';
        const icon = getStationIcon(station.name);
        
        html += `
            <div class="col-lg-6 mb-4">
                <div class="station-card" style="border-top-color: ${color};">
                    <div class="station-header">
                        <div class="station-name">
                            <i class="${icon}"></i>
                            ${station.name}
                        </div>
                        <div class="station-count">
                            ${station.item_count} items
                        </div>
                    </div>
                    
                    <div class="station-items">
        `;
        
        if (station.items.length === 0) {
            html += `<div class="text-center py-3 text-muted">
                        <i class="fas fa-check-circle"></i>
                        All done!
                    </div>`;
        } else {
            station.items.forEach(item => {
                const urgencyClass = isItemUrgent(item) ? 'urgent' : '';
                const timeAgo = getTimeAgo(item.order_created);
                
                html += `
                    <div class="order-item ${item.item_status} ${urgencyClass}" 
                         onclick="showItemModal(
                             ${item.item_id}, 
                             '${escapeHtml(item.order_number)}', 
                             '${escapeHtml(item.product_name)}', 
                             '${escapeHtml(item.special_request || '')}',
                             ${item.quantity},
                             '${escapeHtml(item.display_name || '')}',
                             '${item.item_status}'
                         )">
                        <div class="item-header">
                            <div>
                                <span class="order-number">${item.order_number}</span>
                                <span class="customer-name">• ${item.display_name || 'Counter Order'}</span>
                            </div>
                            <div class="item-timer">
                                ${timeAgo}
                            </div>
                        </div>
                        
                        <div class="item-details">
                            <div class="item-name">
                                <span class="quantity-badge">${item.quantity}x</span>
                                ${item.product_name}
                            </div>
                            <div class="item-status-badge ${getStatusBadgeClass(item.item_status)}">
                                ${item.item_status}
                            </div>
                        </div>
                        
                        ${item.special_request ? `
                            <div class="item-special">
                                <i class="fas fa-sticky-note me-1"></i>
                                ${escapeHtml(item.special_request)}
                            </div>
                        ` : ''}
                        
                        <div class="item-actions">
                            ${item.item_status === 'pending' ? `
                                <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); updateItemStatusDirect(${item.item_id}, 'preparing')">
                                    <i class="fas fa-play me-1"></i> Start
                                </button>
                            ` : ''}
                            
                            ${item.item_status === 'preparing' ? `
                                <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); updateItemStatusDirect(${item.item_id}, 'ready')">
                                    <i class="fas fa-check me-1"></i> Ready
                                </button>
                            ` : ''}
                            
                            <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); showItemModal(
                                ${item.item_id}, 
                                '${escapeHtml(item.order_number)}', 
                                '${escapeHtml(item.product_name)}', 
                                '${escapeHtml(item.special_request || '')}',
                                ${item.quantity},
                                '${escapeHtml(item.display_name || '')}',
                                '${item.item_status}'
                            )">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        
        html += `
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#kitchen-stations').html(html);
}


// Update statistics
function updateStats(stats) {
    $('#total-items').text(stats.total_items || 0);
    $('#urgent-count').text(stats.urgent_items ? `${stats.urgent_items} urgent` : '0 urgent');
    
    if (stats.urgent_items > 0) {
        $('#urgent-count').addClass('urgent-badge');
    } else {
        $('#urgent-count').removeClass('urgent-badge');
    }
}

// Check if item is urgent (waiting > 10 minutes)
function isItemUrgent(item) {
    if (item.status !== 'pending') return false;
    
    const orderTime = new Date(item.order_created);
    const now = new Date();
    const minutesDiff = (now - orderTime) / (1000 * 60);
    
    return minutesDiff > 10;
}

// Get time ago string
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins === 1) return '1 min ago';
    if (diffMins < 60) return `${diffMins} mins ago`;
    
    const diffHours = Math.floor(diffMins / 60);
    return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
}

// Show item modal with all details
function showItemModal(itemId, orderNumber, productName, specialRequest, quantity, customerName, itemStatus) {
    // Set modal data
    $('#modal-order-number').text(orderNumber);
    $('#modal-customer-name').text(customerName || 'Counter Order');
    $('#modal-item-name').text(productName);
    $('#modal-item-quantity').text(quantity + 'x');
    $('#modal-item-status').text(itemStatus).removeClass().addClass('badge ' + getStatusBadgeClass(itemStatus));
    
    // Handle special request
    const specialRequestElement = $('#modal-special-request');
    if (specialRequest && specialRequest.trim() !== '') {
        specialRequestElement.find('span').text(specialRequest);
        specialRequestElement.show();
    } else {
        specialRequestElement.find('span').text('No special instructions');
        specialRequestElement.removeClass('alert-warning').addClass('alert-light');
    }
    
    // Store item ID in modal for later use
    $('#itemModal').data('item-id', itemId);
    $('#itemModal').data('item-status', itemStatus);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('itemModal'));
    modal.show();
}

// Helper: Get Bootstrap badge class for status
function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'bg-warning text-dark',
        'preparing': 'bg-info',
        'ready': 'bg-success',
        'cancelled': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

// Update item status from modal
function updateItemStatus(newStatus) {
    const itemId = $('#itemModal').data('item-id');
    updateItemStatusDirect(itemId, newStatus);
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('itemModal'));
    modal.hide();
}

// Update item status directly
function updateItemStatusDirect(itemId, newStatus) {
    if (!confirm(`Change item status to "${newStatus}"?`)) return;
    
    $.ajax({
        url: 'kitchen-api/update-item-status.php',
        method: 'POST',
        data: {
            item_id: itemId,
            status: newStatus
        },
        success: function(response) {
            if (response.success) {
                showAlert('Item status updated!', 'success');
                loadKitchenDisplay(currentStation);
            } else {
                showAlert('Failed: ' + response.message, 'danger');
            }
        },
        error: function() {
            showAlert('Network error. Please try again.', 'danger');
        }
    });
}

// Auto-refresh system
function startAutoRefresh() {
    // Countdown timer
    setInterval(() => {
        refreshTime--;
        $('#refresh-countdown').text(refreshTime);
        
        if (refreshTime <= 0) {
            refreshKitchenDisplay();
            refreshTime = 15;
        }
    }, 1000);
    
    // Full refresh every 15 seconds
    setInterval(refreshKitchenDisplay, 15000);
}

// Refresh display
function refreshKitchenDisplay() {
    loadKitchenDisplay(currentStation);
    refreshTime = 15;
}

// Update last updated time
function updateLastUpdated() {
    const now = new Date();
    const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    $('#last-updated').text('Updated: ' + timeString);
}

// Play notification sound
function playNotificationSound() {
    if (soundEnabled) {
        const audio = new Audio('/pos/display/sounds/kitchen-notification.mp3');
        audio.play().catch(e => console.log('Audio play failed:', e));
    }
}

// Show alert
function showAlert(message, type = 'info') {
    const alert = $(`
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(alert);
    setTimeout(() => alert.remove(), 3000);
}

// Utility: Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Toggle fullscreen
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

// Event listeners
$(document).ready(function() {
    // Sound toggle
    $('#soundToggle').change(function() {
        soundEnabled = this.checked;
        localStorage.setItem('kitchenSoundEnabled', soundEnabled);
    });
    
    // Load sound preference
    const savedSoundPref = localStorage.getItem('kitchenSoundEnabled');
    if (savedSoundPref !== null) {
        soundEnabled = savedSoundPref === 'true';
        $('#soundToggle').prop('checked', soundEnabled);
    }
});