<?php
// admin/display-control.php (UPDATED)
require_once '../includes/db.php';
require_once '../includes/display-functions.php';

// Check admin authentication
// session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $field = str_replace('setting_', '', $key);
                $displayType = $_POST['display_type'] ?? 'customer';
                
                updateDisplaySetting($displayType, $field, $value);
            }
        }
        $success = "Settings updated successfully!";
    }
    
    if (isset($_POST['cleanup_old'])) {
        $affected = cleanupOldDisplayEntries();
        $cleanupMsg = "Old entries cleaned up. Removed: " . $affected . " records";
    }
    
    if (isset($_POST['reset_display'])) {
        // Clear all display entries
        $stmt = $pdo->exec("TRUNCATE TABLE order_display_status");
        $resetMsg = "Display cleared. All order entries removed.";
    }
}

// Get all display settings
$displaySettings = getAllDisplaySettings();
$customerSettings = getDisplaySettings();

// Get display statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing,
        SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready
    FROM order_display_status 
    WHERE display_until IS NULL OR display_until > NOW()
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Display Controls</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .setting-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .setting-group h6 {
            color: #007bff;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="fas fa-tv"></i> Display System Controls</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($cleanupMsg)): ?>
            <div class="alert alert-info"><?php echo $cleanupMsg; ?></div>
        <?php endif; ?>
        
        <?php if (isset($resetMsg)): ?>
            <div class="alert alert-warning"><?php echo $resetMsg; ?></div>
        <?php endif; ?>
        
        <!-- Statistics Card -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Active</h5>
                        <h2><?php echo $stats['total_orders'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Waiting</h5>
                        <h2><?php echo $stats['waiting'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Preparing</h5>
                        <h2><?php echo $stats['preparing'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Ready</h5>
                        <h2><?php echo $stats['ready'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Display URLs -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-link"></i> Display URLs</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="input-group mb-3">
                            <span class="input-group-text">Processing Orders</span>
                            <input type="text" class="form-control" 
                                   value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/../display/'; ?>" 
                                   readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard(this)">Copy</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group mb-3">
                            <span class="input-group-text">Kitchen View</span>
                            <input type="text" class="form-control" 
                                   value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/../display/kitchen.php'; ?>" 
                                   readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard(this)">Copy</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">Order Status</span>
                            <input type="text" class="form-control" 
                                   value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/../order-status.php'; ?>" 
                                   readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard(this)">Copy</button>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Display these on TVs/monitors in your restaurant. The "Order Status" URL can be given to customers.
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Settings Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cog"></i> Display Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Display Type to Configure:</label>
                        <select name="display_type" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($displaySettings as $setting): ?>
                                <option value="<?php echo $setting['display_type']; ?>" 
                                    <?php echo ($setting['display_type'] == 'customer') ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($setting['display_type']); ?> Display
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php 
                    // Get selected display settings
                    $selectedType = $_POST['display_type'] ?? 'customer';
                    $selectedSettings = array_filter($displaySettings, function($s) use ($selectedType) {
                        return $s['display_type'] == $selectedType;
                    });
                    $selectedSettings = reset($selectedSettings) ?: [];
                    ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Refresh Interval (seconds)</label>
                            <input type="number" name="setting_refresh_interval" 
                                   value="<?php echo htmlspecialchars($selectedSettings['refresh_interval'] ?? 30); ?>" 
                                   class="form-control" min="5" max="300">
                            <div class="form-text">How often the display auto-refreshes</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Display Items</label>
                            <input type="number" name="setting_max_display_items" 
                                   value="<?php echo htmlspecialchars($selectedSettings['max_display_items'] ?? 10); ?>" 
                                   class="form-control" min="1" max="50">
                            <div class="form-text">Maximum orders to show at once</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Show Completed For (minutes)</label>
                            <input type="number" name="setting_show_completed_for" 
                                   value="<?php echo htmlspecialchars($selectedSettings['show_completed_for'] ?? 5); ?>" 
                                   class="form-control" min="0" max="60">
                            <div class="form-text">How long to keep completed orders on display</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Auto Remove After (minutes)</label>
                            <input type="number" name="setting_auto_remove_after" 
                                   value="<?php echo htmlspecialchars($selectedSettings['auto_remove_after'] ?? 120); ?>" 
                                   class="form-control" min="10" max="1440">
                            <div class="form-text">Remove old orders after X minutes</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Theme</label>
                            <select name="setting_theme" class="form-select">
                                <option value="default" <?php echo ($selectedSettings['theme'] ?? 'default') == 'default' ? 'selected' : ''; ?>>Default</option>
                                <option value="dark" <?php echo ($selectedSettings['theme'] ?? '') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                <option value="light" <?php echo ($selectedSettings['theme'] ?? '') == 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="minimal" <?php echo ($selectedSettings['theme'] ?? '') == 'minimal' ? 'selected' : ''; ?>>Minimal</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Active</label>
                            <select name="setting_is_active" class="form-select">
                                <option value="1" <?php echo ($selectedSettings['is_active'] ?? 1) ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo !($selectedSettings['is_active'] ?? 1) ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <div class="form-text">Turn display on/off</div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                        
                        <button type="submit" name="cleanup_old" class="btn btn-warning" 
                                onclick="return confirm('Clean up old display entries? This will remove completed orders.')">
                            <i class="fas fa-broom"></i> Cleanup Old Entries
                        </button>
                        
                        <button type="submit" name="reset_display" class="btn btn-danger" 
                                onclick="return confirm('WARNING: This will clear ALL orders from the display. Are you sure?')">
                            <i class="fas fa-trash"></i> Reset Display
                        </button>
                        
                        <a href="../display/" target="_blank" class="btn btn-success">
                            <i class="fas fa-external-link-alt"></i> View Display
                        </a>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to POS
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Current Display Preview -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-eye"></i> Current Display Preview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Customer Display Settings:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Refresh Interval:</span>
                                <span class="badge bg-primary"><?php echo $customerSettings['refresh_interval']; ?> seconds</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Max Items:</span>
                                <span class="badge bg-primary"><?php echo $customerSettings['max_display_items']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Show Completed:</span>
                                <span class="badge bg-primary"><?php echo $customerSettings['show_completed_for']; ?> minutes</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Theme:</span>
                                <span class="badge bg-primary"><?php echo ucfirst($customerSettings['theme']); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Display Status:</h6>
                        <div class="alert <?php echo ($selectedSettings['is_active'] ?? 1) ? 'alert-success' : 'alert-danger'; ?>">
                            <i class="fas <?php echo ($selectedSettings['is_active'] ?? 1) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            Display is currently <strong><?php echo ($selectedSettings['is_active'] ?? 1) ? 'ACTIVE' : 'INACTIVE'; ?></strong>
                        </div>
                        <p class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            When inactive, the display will show a "Closed" message instead of orders.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard(button) {
            const input = button.previousElementSibling;
            input.select();
            document.execCommand('copy');
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
    </script>
</body>
</html>