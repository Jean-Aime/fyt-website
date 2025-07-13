<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('content.create');

$page_title = 'Itinerary Builder';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $duration = (int) $_POST['duration'];
        $difficulty = $_POST['difficulty'];
        $price_range = $_POST['price_range'];
        $destinations = $_POST['destinations'];
        $itinerary_data = $_POST['itinerary_data']; // JSON data
        $template_name = sanitizeInput($_POST['template_name']);
        $save_as_template = isset($_POST['save_as_template']) ? 1 : 0;

        // Insert itinerary
        $stmt = $db->prepare("
            INSERT INTO itineraries (title, description, duration, difficulty, price_range, destinations, itinerary_data, created_by, save_as_template, template_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $title,
            $description,
            $duration,
            $difficulty,
            $price_range,
            json_encode($destinations),
            $itinerary_data,
            $_SESSION['user_id'],
            $save_as_template,
            $template_name
        ]);

        $itinerary_id = $db->lastInsertId();

        $auth->logActivity($_SESSION['user_id'], 'itinerary_created', "Created itinerary: $title");
        $success = 'Itinerary created successfully!';

    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get saved inventory
$hotels = $db->query("SELECT * FROM inventory_hotels WHERE status = 'active' ORDER BY name")->fetchAll();
$guides = $db->query("SELECT * FROM inventory_guides WHERE status = 'active' ORDER BY name")->fetchAll();
$vendors = $db->query("SELECT * FROM inventory_vendors WHERE status = 'active' ORDER BY name")->fetchAll();
$activities = $db->query("SELECT * FROM inventory_activities WHERE status = 'active' ORDER BY name")->fetchAll();

// Get templates
$templates = $db->query("SELECT * FROM itineraries WHERE save_as_template = 1 ORDER BY created_at DESC")->fetchAll();

// Get destinations
$destinations = $db->query("SELECT DISTINCT destination FROM tours ORDER BY destination")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/dragula/3.7.3/dragula.min.css" rel="stylesheet">
    <style>
        .itinerary-builder {
            display: grid;
            grid-template-columns: 300px 1fr 300px;
            gap: 20px;
            height: calc(100vh - 200px);
        }
        
        .inventory-panel, .timeline-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow-y: auto;
        }
        
        .main-builder {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow-y: auto;
        }
        
        .panel-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        
        .inventory-tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .inventory-tab {
            padding: 8px 12px;
            border: none;
            background: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .inventory-tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .inventory-items {
            display: none;
        }
        
        .inventory-items.active {
            display: block;
        }
        
        .inventory-item {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: grab;
            transition: all 0.3s ease;
        }
        
        .inventory-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .inventory-item.dragging {
            opacity: 0.5;
        }
        
        .item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .item-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .item-price {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .timeline {
            position: relative;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-color);
        }
        
        .day-container {
            position: relative;
            margin-bottom: 30px;
            padding-left: 50px;
        }
        
        .day-marker {
            position: absolute;
            left: 10px;
            top: 10px;
            width: 20px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .day-content {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border: 2px dashed #ddd;
            min-height: 100px;
            transition: all 0.3s ease;
        }
        
        .day-content.drag-over {
            border-color: var(--primary-color);
            background: rgba(212, 165, 116, 0.1);
        }
        
        .day-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .day-title {
            font-weight: 600;
            color: #333;
        }
        
        .day-controls {
            display: flex;
            gap: 5px;
        }
        
        .day-control-btn {
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            cursor: pointer;
            font-size: 0.8em;
        }
        
        .activity-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            position: relative;
            cursor: move;
        }
        
        .activity-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .activity-name {
            font-weight: 600;
            color: #333;
        }
        
        .activity-type {
            font-size: 0.8em;
            color: #666;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        .activity-details {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }
        
        .activity-time {
            font-size: 0.8em;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .activity-controls {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 3px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .activity-item:hover .activity-controls {
            opacity: 1;
        }
        
        .activity-control-btn {
            width: 20px;
            height: 20px;
            border: none;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            cursor: pointer;
            font-size: 0.7em;
        }
        
        .builder-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .builder-controls {
            display: flex;
            gap: 10px;
        }
        
        .template-selector {
            margin-bottom: 20px;
        }
        
        .template-item {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .template-item:hover {
            background: #e9ecef;
            border-color: var(--primary-color);
        }
        
        .template-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .template-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .itinerary-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-weight: 600;
        }
        
        .summary-value {
            color: var(--primary-color);
        }
        
        .add-day-btn {
            width: 100%;
            padding: 15px;
            border: 2px dashed var(--primary-color);
            background: none;
            border-radius: 10px;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 1.1em;
            transition: all 0.3s ease;
        }
        
        .add-day-btn:hover {
            background: rgba(212, 165, 116, 0.1);
        }
        
        .search-inventory {
            margin-bottom: 15px;
        }
        
        .search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9em;
        }
        
        .empty-state {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
        
        @media (max-width: 1200px) {
            .itinerary-builder {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .inventory-panel, .timeline-panel {
                order: 2;
            }
            
            .main-builder {
                order: 1;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <div class="content-header">
                    <div class="content-title">
                        <h1>Itinerary Builder</h1>
                        <p>Create detailed day-by-day itineraries using your saved inventory</p>
                    </div>
                    <div class="content-actions">
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Content
                        </a>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                <?php endif; ?>
                
                <form method="POST" id="itineraryForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="itinerary_data" id="itineraryData">
                    <input type="hidden" name="destinations" id="destinationsData">
                    
                    <div class="itinerary-builder">
                        <!-- Inventory Panel -->
                        <div class="inventory-panel">
                            <h3 class="panel-title">Inventory</h3>
                            
                            <div class="search-inventory">
                                <input type="text" class="search-input" placeholder="Search inventory..." id="inventorySearch">
                            </div>
                            
                            <div class="inventory-tabs">
                                <button type="button" class="inventory-tab active" data-tab="hotels">Hotels</button>
                                <button type="button" class="inventory-tab" data-tab="guides">Guides</button>
                                <button type="button" class="inventory-tab" data-tab="activities">Activities</button>
                                <button type="button" class="inventory-tab" data-tab="vendors">Vendors</button>
                            </div>
                            
                            <!-- Hotels -->
                            <div class="inventory-items active" id="hotels">
                                <?php foreach ($hotels as $hotel): ?>
                                        <div class="inventory-item" draggable="true" data-type="hotel" data-id="<?php echo $hotel['id']; ?>">
                                            <div class="item-name"><?php echo htmlspecialchars($hotel['name']); ?></div>
                                            <div class="item-details">
                                                <?php echo htmlspecialchars($hotel['location']); ?><br>
                                                Rating: <?php echo $hotel['rating']; ?>/5<br>
                                                <span class="item-price">$<?php echo number_format($hotel['price_per_night'], 2); ?>/night</span>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Guides -->
                            <div class="inventory-items" id="guides">
                                <?php foreach ($guides as $guide): ?>
                                        <div class="inventory-item" draggable="true" data-type="guide" data-id="<?php echo $guide['id']; ?>">
                                            <div class="item-name"><?php echo htmlspecialchars($guide['name']); ?></div>
                                            <div class="item-details">
                                                <?php echo htmlspecialchars($guide['specialization']); ?><br>
                                                Languages: <?php echo htmlspecialchars($guide['languages']); ?><br>
                                                <span class="item-price">$<?php echo number_format($guide['daily_rate'], 2); ?>/day</span>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Activities -->
                            <div class="inventory-items" id="activities">
                                <?php foreach ($activities as $activity): ?>
                                        <div class="inventory-item" draggable="true" data-type="activity" data-id="<?php echo $activity['id']; ?>">
                                            <div class="item-name"><?php echo htmlspecialchars($activity['name']); ?></div>
                                            <div class="item-details">
                                                Duration: <?php echo $activity['duration']; ?><br>
                                                Difficulty: <?php echo $activity['difficulty']; ?><br>
                                                <span class="item-price">$<?php echo number_format($activity['price'], 2); ?>/person</span>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Vendors -->
                            <div class="inventory-items" id="vendors">
                                <?php foreach ($vendors as $vendor): ?>
                                        <div class="inventory-item" draggable="true" data-type="vendor" data-id="<?php echo $vendor['id']; ?>">
                                            <div class="item-name"><?php echo htmlspecialchars($vendor['name']); ?></div>
                                            <div class="item-details">
                                                Service: <?php echo htmlspecialchars($vendor['service_type']); ?><br>
                                                Location: <?php echo htmlspecialchars($vendor['location']); ?><br>
                                                <span class="item-price">$<?php echo number_format($vendor['base_price'], 2); ?></span>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Main Builder -->
                        <div class="main-builder">
                            <div class="builder-header">
                                <div>
                                    <input type="text" name="title" class="form-control" placeholder="Itinerary Title" required style="font-size: 1.2em; font-weight: 600; margin-bottom: 10px;">
                                    <textarea name="description" class="form-control" placeholder="Brief description..." rows="2"></textarea>
                                </div>
                                <div class="builder-controls">
                                    <button type="button" class="btn btn-outline" onclick="loadTemplate()">
                                        <i class="fas fa-file-import"></i> Load Template
                                    </button>
                                    <button type="button" class="btn btn-outline" onclick="previewItinerary()">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Itinerary
                                    </button>
                                </div>
                            </div>
                            
                            <div class="itinerary-summary">
                                <div class="summary-item">
                                    <span class="summary-label">Duration:</span>
                                    <span class="summary-value" id="totalDuration">0 days</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Total Activities:</span>
                                    <span class="summary-value" id="totalActivities">0</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Estimated Cost:</span>
                                    <span class="summary-value" id="estimatedCost">$0.00</span>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Difficulty Level:</label>
                                    <select name="difficulty" class="form-control">
                                        <option value="easy">Easy</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="challenging">Challenging</option>
                                        <option value="extreme">Extreme</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Price Range:</label>
                                    <select name="price_range" class="form-control">
                                        <option value="budget">Budget ($0-$500)</option>
                                        <option value="mid-range">Mid-range ($500-$1500)</option>
                                        <option value="luxury">Luxury ($1500+)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="timeline" id="timeline">
                                <div class="day-container">
                                    <div class="day-marker">1</div>
                                    <div class="day-content" data-day="1">
                                        <div class="day-header">
                                            <div class="day-title">Day 1</div>
                                            <div class="day-controls">
                                                <button type="button" class="day-control-btn" onclick="removeDay(1)" title="Remove Day">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="empty-state">
                                            Drag activities, hotels, or guides here to build your itinerary
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="add-day-btn" onclick="addDay()">
                                <i class="fas fa-plus"></i> Add Another Day
                            </button>
                            
                            <div style="margin-top: 20px;">
                                <label>
                                    <input type="checkbox" name="save_as_template"> Save as Template
                                </label>
                                <input type="text" name="template_name" class="form-control" placeholder="Template name..." style="margin-top: 10px; display: none;" id="templateNameInput">
                            </div>
                        </div>
                        
                        <!-- Templates Panel -->
                        <div class="timeline-panel">
                            <h3 class="panel-title">Templates</h3>
                            
                            <div class="template-selector">
                                <?php if (empty($templates)): ?>
                                        <div class="empty-state">No templates available</div>
                                <?php else: ?>
                                        <?php foreach ($templates as $template): ?>
                                                <div class="template-item" onclick="loadTemplateData(<?php echo $template['id']; ?>)">
                                                    <div class="template-name"><?php echo htmlspecialchars($template['template_name']); ?></div>
                                                    <div class="template-details">
                                                        <?php echo $template['duration']; ?> days • <?php echo ucfirst($template['difficulty']); ?><br>
                                                        Created: <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
                                                    </div>
                                                </div>
                                        <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <h4 style="margin-top: 30px; margin-bottom: 15px;">Quick Actions</h4>
                            <button type="button" class="btn btn-outline btn-block" onclick="clearItinerary()">
                                <i class="fas fa-trash"></i> Clear All
                            </button>
                            <button type="button" class="btn btn-outline btn-block" onclick="duplicateDay()">
                                <i class="fas fa-copy"></i> Duplicate Last Day
                            </button>
                            <button type="button" class="btn btn-outline btn-block" onclick="exportItinerary()">
                                <i class="fas fa-download"></i> Export PDF
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dragula/3.7.3/dragula.min.js"></script>
    <script>
        // Global variables
        let itineraryData = {};
        let dayCount = 1;
        let totalCost = 0;
        let totalActivities = 0;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeDragAndDrop();
            setupEventListeners();
            updateSummary();
        });
        
        function initializeDragAndDrop() {
            // Initialize dragula for inventory items to day containers
            const inventoryContainers = document.querySelectorAll('.inventory-items');
            const dayContainers = document.querySelectorAll('.day-content');
            
            dragula([...inventoryContainers, ...dayContainers], {
                copy: function(el, source) {
                    return source.classList.contains('inventory-items');
                },
                accepts: function(el, target) {
                    return target.classList.contains('day-content');
                }
            }).on('drop', function(el, target, source, sibling) {
                if (target.classList.contains('day-content')) {
                    handleItemDrop(el, target);
                }
            });
        }
        
        function setupEventListeners() {
            // Inventory tabs
            document.querySelectorAll('.inventory-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    switchInventoryTab(this.dataset.tab);
                });
            });
            
            // Search inventory
            document.getElementById('inventorySearch').addEventListener('input', function() {
                filterInventory(this.value);
            });
            
            // Save as template checkbox
            document.querySelector('input[name="save_as_template"]').addEventListener('change', function() {
                const templateNameInput = document.getElementById('templateNameInput');
                if (this.checked) {
                    templateNameInput.style.display = 'block';
                    templateNameInput.required = true;
                } else {
                    templateNameInput.style.display = 'none';
                    templateNameInput.required = false;
                }
            });
            
            // Form submission
            document.getElementById('itineraryForm').addEventListener('submit', function(e) {
                prepareFormData();
            });
        }
        
        function switchInventoryTab(tabName) {
            // Update active tab
            document.querySelectorAll('.inventory-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            
            // Show corresponding inventory items
            document.querySelectorAll('.inventory-items').forEach(items => {
                items.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
        }
        
        function filterInventory(searchTerm) {
            const activeTab = document.querySelector('.inventory-items.active');
            const items = activeTab.querySelectorAll('.inventory-item');
            
            items.forEach(item => {
                const name = item.querySelector('.item-name').textContent.toLowerCase();
                const details = item.querySelector('.item-details').textContent.toLowerCase();
                
                if (name.includes(searchTerm.toLowerCase()) || details.includes(searchTerm.toLowerCase())) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        function handleItemDrop(element, target) {
            const dayNumber = target.dataset.day;
            const itemType = element.dataset.type;
            const itemId = element.dataset.id;
            
            // Remove empty state if present
            const emptyState = target.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Create activity item
            const activityItem = createActivityItem(element, dayNumber);
            target.appendChild(activityItem);
            
            // Update itinerary data
            if (!itineraryData[dayNumber]) {
                itineraryData[dayNumber] = [];
            }
            
            itineraryData[dayNumber].push({
                type: itemType,
                id: itemId,
                name: element.querySelector('.item-name').textContent,
                details: element.querySelector('.item-details').textContent,
                time: '09:00',
                duration: '2 hours'
            });
            
            // Remove the dragged copy
            element.remove();
            
            updateSummary();
        }
        
        function createActivityItem(originalElement, dayNumber) {
            const div = document.createElement('div');
            div.className = 'activity-item';
            div.dataset.day = dayNumber;
            
            const name = originalElement.querySelector('.item-name').textContent;
            const details = originalElement.querySelector('.item-details').textContent;
            const type = originalElement.dataset.type;
            
            div.innerHTML = `
                <div class="activity-controls">
                    <button type="button" class="activity-control-btn" onclick="removeActivity(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="activity-header">
                    <div class="activity-name">${name}</div>
                    <div class="activity-type">${type}</div>
                </div>
                <div class="activity-details">${details}</div>
                <div class="activity-time">
                    <input type="time" value="09:00" onchange="updateActivityTime(this)" style="border: none; background: none; color: var(--primary-color); font-weight: 600;">
                    <span> - Duration: </span>
                    <input type="text" value="2 hours" onchange="updateActivityDuration(this)" style="border: none; background: none; color: var(--primary-color); font-weight: 600; width: 80px;">
                </div>
            `;
            
            return div;
        }
        
        function addDay() {
            dayCount++;
            const timeline = document.getElementById('timeline');
            
            const dayContainer = document.createElement('div');
            dayContainer.className = 'day-container';
            dayContainer.innerHTML = `
                <div class="day-marker">${dayCount}</div>
                <div class="day-content" data-day="${dayCount}">
                    <div class="day-header">
                        <div class="day-title">Day ${dayCount}</div>
                        <div class="day-controls">
                            <button type="button" class="day-control-btn" onclick="removeDay(${dayCount})" title="Remove Day">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="empty-state">
                        Drag activities, hotels, or guides here to build your itinerary
                    </div>
                </div>
            `;
            
            timeline.appendChild(dayContainer);
            
            // Reinitialize drag and drop for new day container
            initializeDragAndDrop();
            updateSummary();
        }
        
        function removeDay(dayNumber) {
            if (dayCount <= 1) {
                alert('You must have at least one day in your itinerary.');
                return;
            }
            
            if (confirm('Are you sure you want to remove this day?')) {
                const dayContainer = document.querySelector(`[data-day="${dayNumber}"]`).closest('.day-container');
                dayContainer.remove();
                
                // Remove from itinerary data
                delete itineraryData[dayNumber];
                
                // Renumber remaining days
                renumberDays();
                updateSummary();
            }
        }
        
        function renumberDays() {
            const dayContainers = document.querySelectorAll('.day-container');
            dayCount = dayContainers.length;
            
            dayContainers.forEach((container, index) => {
                const newDayNumber = index + 1;
                const marker = container.querySelector('.day-marker');
                const content = container.querySelector('.day-content');
                const title = container.querySelector('.day-title');
                const removeBtn = container.querySelector('.day-control-btn');
                
                marker.textContent = newDayNumber;
                content.dataset.day = newDayNumber;
                title.textContent = `Day ${newDayNumber}`;
                removeBtn.setAttribute('onclick', `removeDay(${newDayNumber})`);
                
                // Update activity items
                const activities = content.querySelectorAll('.activity-item');
                activities.forEach(activity => {
                    activity.dataset.day = newDayNumber;
                });
            });
            
            // Update itinerary data
            const newItineraryData = {};
            Object.keys(itineraryData).sort().forEach((key, index) => {
                newItineraryData[index + 1] = itineraryData[key];
            });
            itineraryData = newItineraryData;
        }
        
        function removeActivity(button) {
            const activityItem = button.closest('.activity-item');
            const dayContent = activityItem.closest('.day-content');
            const dayNumber = dayContent.dataset.day;
            
            activityItem.remove();
            
            // Remove from itinerary data
            if (itineraryData[dayNumber]) {
                // Find and remove the activity (simplified - in real implementation, you'd need better identification)
                itineraryData[dayNumber].pop();
            }
            
            // Add empty state if no activities left
            if (dayContent.querySelectorAll('.activity-item').length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.textContent = 'Drag activities, hotels, or guides here to build your itinerary';
                dayContent.appendChild(emptyState);
            }
            
            updateSummary();
        }
        
        function updateActivityTime(input) {
            // Update the activity time in itinerary data
            updateSummary();
        }
        
        function updateActivityDuration(input) {
            // Update the activity duration in itinerary data
            updateSummary();
        }
        
        function updateSummary() {
            // Calculate total activities
            totalActivities = 0;
            Object.values(itineraryData).forEach(day => {
                totalActivities += day.length;
            });
            
            // Update display
            document.getElementById('totalDuration').textContent = `${dayCount} days`;
            document.getElementById('totalActivities').textContent = totalActivities;
            document.getElementById('estimatedCost').textContent = `$${totalCost.toFixed(2)}`;
            
            // Update duration input
            document.querySelector('input[name="duration"]').value = dayCount;
        }
        
        function loadTemplate() {
            // Show template selection modal or load from sidebar
            alert('Select a template from the Templates panel on the right');
        }
        
        function loadTemplateData(templateId) {
            if (confirm('Loading this template will replace your current itinerary. Continue?')) {
                // Fetch template data via AJAX
                fetch(`../api/get-template.php?id=${templateId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Clear current itinerary
                            clearItinerary();
                            
                            // Load template data
                            const templateData = JSON.parse(data.template.itinerary_data);
                            
                            // Populate form fields
                            document.querySelector('input[name="title"]').value = data.template.title + ' (Copy)';
                            document.querySelector('textarea[name="description"]').value = data.template.description;
                            document.querySelector('select[name="difficulty"]').value = data.template.difficulty;
                            document.querySelector('select[name="price_range"]').value = data.template.price_range;
                            
                            // Rebuild itinerary
                            rebuildItinerary(templateData);
                        } else {
                            alert('Error loading template');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error loading template');
                    });
            }
        }
        
        function rebuildItinerary(templateData) {
            // Implementation for rebuilding itinerary from template data
            itineraryData = templateData;
            
            // Clear timeline
            const timeline = document.getElementById('timeline');
            timeline.innerHTML = '';
            
            // Rebuild days
            dayCount = Object.keys(templateData).length;
            Object.keys(templateData).forEach(dayNumber => {
                // Create day container and populate with activities
                // This would need to be implemented based on your template data structure
            });
            
            updateSummary();
        }
        
        function clearItinerary() {
            if (confirm('Are you sure you want to clear the entire itinerary?')) {
                itineraryData = {};
                dayCount = 1;
                
                const timeline = document.getElementById('timeline');
                timeline.innerHTML = `
                    <div class="day-container">
                        <div class="day-marker">1</div>
                        <div class="day-content" data-day="1">
                            <div class="day-header">
                                <div class="day-title">Day 1</div>
                                <div class="day-controls">
                                    <button type="button" class="day-control-btn" onclick="removeDay(1)" title="Remove Day">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="empty-state">
                                Drag activities, hotels, or guides here to build your itinerary
                            </div>
                        </div>
                    </div>
                `;
                
                initializeDragAndDrop();
                updateSummary();
            }
        }
        
        function duplicateDay() {
            if (dayCount === 0) return;
            
            const lastDay = itineraryData[dayCount];
            if (lastDay && lastDay.length > 0) {
                addDay();
                itineraryData[dayCount] = [...lastDay];
                
                // Rebuild the new day's activities
                const newDayContent = document.querySelector(`[data-day="${dayCount}"]`);
                const emptyState = newDayContent.querySelector('.empty-state');
                if (emptyState) emptyState.remove();
                
                lastDay.forEach(activity => {
                    // Create activity element (simplified)
                    const activityElement = document.createElement('div');
                    activityElement.className = 'activity-item';
                    activityElement.innerHTML = `
                        <div class="activity-controls">
                            <button type="button" class="activity-control-btn" onclick="removeActivity(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="activity-header">
                            <div class="activity-name">${activity.name}</div>
                            <div class="activity-type">${activity.type}</div>
                        </div>
                        <div class="activity-details">${activity.details}</div>
                        <div class="activity-time">
                            <input type="time" value="${activity.time}" onchange="updateActivityTime(this)" style="border: none; background: none; color: var(--primary-color); font-weight: 600;">
                            <span> - Duration: </span>
                            <input type="text" value="${activity.duration}" onchange="updateActivityDuration(this)" style="border: none; background: none; color: var(--primary-color); font-weight: 600; width: 80px;">
                        </div>
                    `;
                    newDayContent.appendChild(activityElement);
                });
                
                updateSummary();
            } else {
                alert('No activities in the last day to duplicate');
            }
        }
        
        function previewItinerary() {
            // Open preview in new window or modal
            const previewWindow = window.open('', '_blank', 'width=800,height=600');
            previewWindow.document.write(generatePreviewHTML());
        }
        
        function generatePreviewHTML() {
            let html = `
                <html>
                <head>
                    <title>Itinerary Preview</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .day { margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
                        .day-title { font-size: 1.5em; font-weight: bold; color: #333; margin-bottom: 15px; }
                        .activity { margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px; }
                        .activity-name { font-weight: bold; }
                        .activity-time { color: #666; font-size: 0.9em; }
                    </style>
                </head>
                <body>
                    <h1>${document.querySelector('input[name="title"]').value || 'Untitled Itinerary'}</h1>
                    <p>${document.querySelector('textarea[name="description"]').value || 'No description'}</p>
            `;
            
            Object.keys(itineraryData).forEach(dayNumber => {
                html += `<div class="day">`;
                html += `<div class="day-title">Day ${dayNumber}</div>`;
                
                itineraryData[dayNumber].forEach(activity => {
                    html += `
                        <div class="activity">
                            <div class="activity-name">${activity.name}</div>
                            <div class="activity-time">${activity.time} - ${activity.duration}</div>
                            <div>${activity.details}</div>
                        </div>
                    `;
                });
                
                html += `</div>`;
            });
            
            html += `</body></html>`;
            return html;
        }
        
        function exportItinerary() {
            // Generate PDF export (would need PDF library)
            alert('PDF export functionality would be implemented here');
        }
        
        function prepareFormData() {
            // Prepare itinerary data for submission
            document.getElementById('itineraryData').value = JSON.stringify(itineraryData);
            
            // Prepare destinations data
            const destinations = [];
            Object.values(itineraryData).forEach(day => {
                day.forEach(activity => {
                    // Extract destinations from activities (simplified)
                    if (activity.type === 'hotel' || activity.type === 'activity') {
                        // Extract location from details
                        const locationMatch = activity.details.match(/Location: ([^<\n]+)/);
                        if (locationMatch) {
                            const destination = locationMatch[1].trim();
                            if (!destinations.includes(destination)) {
                                destinations.push(destination);
                            }
                        }
                    }
                });
            });
            
            document.getElementById('destinationsData').value = JSON.stringify(destinations);
            
            // Set duration
            const durationInput = document.createElement('input');
            durationInput.type = 'hidden';
            durationInput.name = 'duration';
            durationInput.value = dayCount;
            document.getElementById('itineraryForm').appendChild(durationInput);
        }
        
        // Initialize drag and drop
        initializeDragAndDrop();
    </script>
</body>
</html>
