<?php
// display/kitchen.php
require_once '../includes/db.php';
require_once '../includes/display-functions.php';

// Check if kitchen staff is logged in (optional, can be public)
// $isAuthenticated = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'kitchen';
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kitchen') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👨‍🍳 Kitchen Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/kitchen.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --burger-color: #e74c3c;
            --fry-color: #f39c12;
            --drink-color: #3498db;
            --pizza-color: #2ecc71;
            --dessert-color: #9b59b6;
        }
    </style>
</head>
<body class="kitchen-body">
    <!-- Header -->
    <header class="kitchen-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="kitchen-title">
                        <i class="fas fa-utensils"></i> Kitchen Display System
                    </h1>
                    <div class="kitchen-subtitle">
                        <span id="current-time"></span>
                        • 
                        <span id="station-name">All Stations</span>
                        •
                        <span id="last-updated">Updated: Just now</span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="kitchen-controls">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-light" onclick="refreshKitchenDisplay()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button class="btn btn-sm btn-outline-light" onclick="toggleFullscreen()">
                                <i class="fas fa-expand"></i> Fullscreen
                            </button>
                        </div>
                        <div class="form-check form-switch d-inline-block ms-2">
                            <input class="form-check-input" type="checkbox" id="soundToggle" checked>
                            <label class="form-check-label text-light" for="soundToggle">Sound</label>
                        </div>
                        <a class="btn btn-sm btn-outline-light" href="../admin/logout.php">
                                <i class="fas fa-door-open"></i> Logout
                            </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Station Filter -->
    <div class="container mt-3">
        <div class="station-filter">
            <button class="btn station-btn active" data-station="all">
                <i class="fas fa-th"></i> All Stations
            </button>
            <?php
            // Get all active kitchen stations
            $stations = $pdo->query("SELECT * FROM kitchen_stations WHERE is_active = 1 ORDER BY display_order")->fetchAll();
            
            foreach ($stations as $station) {
                $color = $station['color_code'];
                $icon = getStationIcon($station['name']);
                echo "<button class='btn station-btn' data-station='{$station['id']}' style='border-left-color: {$color};'>
                        <i class='{$icon}'></i> {$station['name']}
                      </button>";
            }
            ?>
        </div>
    </div>

    <!-- Kitchen Stations Grid -->
    <main class="container mt-4">
        <div class="row" id="kitchen-stations">
            <!-- Stations will be loaded via JavaScript -->
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading kitchen orders...</span>
                </div>
                <p class="mt-2">Loading kitchen display...</p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="kitchen-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="kitchen-status">
                        <i class="fas fa-clipboard-list"></i>
                        <span id="total-items">0</span> items to prepare • 
                        <span id="urgent-count" class="urgent-badge">0 urgent</span>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="refresh-info">
                        <i class="fas fa-sync"></i>
                        Auto-refreshing every 
                        <span id="refresh-interval">30</span> seconds
                        <span id="refresh-countdown" class="countdown">30</span>s
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modals -->
    <!-- <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Item Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Order: <strong id="modal-order-number"></strong></p>
                    <p>Item: <strong id="modal-item-name"></strong></p>
                    <p>Special: <span id="modal-special-request" class="text-muted"></span></p>
                    
                    <div class="status-options mt-3">
                        <button class="btn btn-warning btn-sm" onclick="updateItemStatus('pending')">
                            <i class="fas fa-clock"></i> Pending
                        </button>
                        <button class="btn btn-info btn-sm" onclick="updateItemStatus('preparing')">
                            <i class="fas fa-utensils"></i> Preparing
                        </button>
                        <button class="btn btn-success btn-sm" onclick="updateItemStatus('ready')">
                            <i class="fas fa-check"></i> Ready
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="updateItemStatus('cancelled')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Add this modal HTML before the closing </body> tag, after the scripts -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Update Item Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="order-info mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Order:</strong></p>
                            <h6 id="modal-order-number" class="text-primary">WALK-IN-2601001</h6>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Customer:</strong></p>
                            <h6 id="modal-customer-name" class="text-secondary">Counter Order</h6>
                        </div>
                    </div>
                </div>
                
                <div class="item-details mb-3 p-3 bg-light rounded">
                    <p class="mb-1"><strong>Item:</strong></p>
                    <h5 id="modal-item-name" class="text-dark">Bacon Burger</h5>
                    
                    <div class="mt-2">
                        <span class="badge bg-secondary" id="modal-item-quantity">1x</span>
                        <span class="badge bg-info ms-2" id="modal-item-status">Pending</span>
                    </div>
                </div>
                
                <div class="special-request mb-3">
                    <p class="mb-1"><strong>Special Instructions:</strong></p>
                    <div id="modal-special-request" class="alert alert-warning py-2">
                        <i class="fas fa-sticky-note me-2"></i>
                        <span>No special instructions</span>
                    </div>
                </div>
                
                <div class="status-actions text-center">
                    <h6 class="mb-3">Update Status:</h6>
                    <div class="btn-group-vertical w-100" role="group">
                        <button type="button" class="btn btn-warning btn-lg mb-2" onclick="updateItemStatus('pending')">
                            <i class="fas fa-clock me-2"></i> Mark as Pending
                        </button>
                        <button type="button" class="btn btn-info btn-lg mb-2" onclick="updateItemStatus('preparing')">
                            <i class="fas fa-utensils me-2"></i> Mark as Preparing
                        </button>
                        <button type="button" class="btn btn-success btn-lg mb-2" onclick="updateItemStatus('ready')">
                            <i class="fas fa-check me-2"></i> Mark as Ready
                        </button>
                        <!-- <button type="button" class="btn btn-danger btn-lg" onclick="updateItemStatus('cancelled')">
                            <i class="fas fa-times me-2"></i> Cancel Item
                        </button> -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateItemStatus('preparing')">
                    <i class="fas fa-play me-1"></i> Start Preparation
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/kitchen.js"></script>
    
    <script>
        // Helper function for station icons
        function getStationIcon(stationName) {
            const icons = {
                'Burger': 'fas fa-hamburger',
                'Fry': 'fas fa-bacon',
                'Drink': 'fas fa-glass-whiskey',
                'Pizza': 'fas fa-pizza-slice',
                'Dessert': 'fas fa-ice-cream',
                'default': 'fas fa-utensils'
            };
            
            for (const [key, icon] of Object.entries(icons)) {
                if (stationName.includes(key)) return icon;
            }
            return icons.default;
        }
        
        // Initialize
        $(document).ready(function() {
            updateTime();
            setInterval(updateTime, 1000);
            loadKitchenDisplay();
            startAutoRefresh();
            
            // Station filter
            $('.station-btn').click(function() {
                $('.station-btn').removeClass('active');
                $(this).addClass('active');
                const stationId = $(this).data('station');
                $('#station-name').text($(this).text().trim());
                loadKitchenDisplay(stationId);
            });
        });
        
        function updateTime() {
            const now = new Date();
            $('#current-time').text(now.toLocaleTimeString('en-US', { 
                hour12: true, 
                hour: '2-digit', 
                minute: '2-digit' 
            }));
        }
    </script>
</body>
</html>

<?php
function getStationIcon($stationName) {
    $icons = [
        'Burger' => 'fas fa-hamburger',
        'Fry' => 'fas fa-bacon',
        'Drink' => 'fas fa-glass-whiskey',
        'Pizza' => 'fas fa-pizza-slice',
        'Dessert' => 'fas fa-ice-cream'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($stationName, $key) !== false) {
            return $icon;
        }
    }
    
    return 'fas fa-utensils';
}
?>