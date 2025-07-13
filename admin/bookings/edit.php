<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('bookings.edit');

$booking_id = (int)($_GET['id'] ?? 0);

if (!$booking_id) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_POST && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $tour_id = (int)$_POST['tour_id'];
        $tour_date = $_POST['tour_date'];
        $adults = (int)$_POST['adults'];
        $children = (int)$_POST['children'];
        $infants = (int)$_POST['infants'];
        $total_amount = (float)$_POST['total_amount'];
        $special_requests = trim($_POST['special_requests']);
        $dietary_requirements = trim($_POST['dietary_requirements']);
        $medical_conditions = trim($_POST['medical_conditions']);
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
        
        $stmt = $db->prepare("
            UPDATE bookings SET 
                tour_id = ?, tour_date = ?, adults = ?, children = ?, infants = ?,
                total_amount = ?, special_requests = ?, dietary_requirements = ?,
                medical_conditions = ?, emergency_contact_name = ?, emergency_contact_phone = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $tour_id, $tour_date, $adults, $children, $infants,
            $total_amount, $special_requests, $dietary_requirements,
            $medical_conditions, $emergency_contact_name, $emergency_contact_phone,
            $booking_id
        ]);
        
        $auth->logActivity($_SESSION['user_id'], 'booking_updated', "Updated booking ID: $booking_id");
        header("Location: view.php?id=$booking_id&success=Booking updated successfully");
        exit;
        
    } catch (Exception $e) {
        $error = 'Error updating booking: ' . $e->getMessage();
    }
}

// Get booking details
$stmt = $db->prepare("
    SELECT b.*, u.first_name, u.last_name, u.email, t.title as tour_title
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN tours t ON b.tour_id = t.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: index.php?error=Booking not found');
    exit;
}

// Get available tours
$tours = $db->query("
    SELECT id, title, price_adult, price_child, price_infant 
    FROM tours 
    WHERE status = 'active' 
    ORDER BY title
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Edit Booking - #' . $booking['booking_reference'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <div class="content-header">
                    <div class="content-title">
                        <h2>Edit Booking</h2>
                        <p>Modify booking details for #<?php echo htmlspecialchars($booking['booking_reference']); ?></p>
                    </div>
                    <div class="content-actions">
                        <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Booking
                        </a>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-container">
                    <form method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-section">
                            <h3>Booking Details</h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="tour_id">Tour <span class="required">*</span></label>
                                    <select id="tour_id" name="tour_id" class="form-control" required onchange="updatePricing()">
                                        <option value="">Select Tour</option>
                                        <?php foreach ($tours as $tour): ?>
                                            <option value="<?php echo $tour['id']; ?>" 
                                                    data-adult-price="<?php echo $tour['price_adult']; ?>"
                                                    data-child-price="<?php echo $tour['price_child']; ?>"
                                                    data-infant-price="<?php echo $tour['price_infant']; ?>"
                                                    <?php echo $tour['id'] == $booking['tour_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tour['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tour_date">Tour Date <span class="required">*</span></label>
                                    <input type="date" id="tour_date" name="tour_date" class="form-control" 
                                           value="<?php echo htmlspecialchars($booking['tour_date']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="adults">Adults <span class="required">*</span></label>
                                    <input type="number" id="adults" name="adults" class="form-control" 
                                           value="<?php echo $booking['adults']; ?>" min="1" required onchange="updatePricing()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="children">Children</label>
                                    <input type="number" id="children" name="children" class="form-control" 
                                           value="<?php echo $booking['children']; ?>" min="0" onchange="updatePricing()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="infants">Infants</label>
                                    <input type="number" id="infants" name="infants" class="form-control" 
                                           value="<?php echo $booking['infants']; ?>" min="0" onchange="updatePricing()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="total_amount">Total Amount <span class="required">*</span></label>
                                    <input type="number" id="total_amount" name="total_amount" class="form-control" 
                                           value="<?php echo $booking['total_amount']; ?>" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Emergency Contact</h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="emergency_contact_name">Emergency Contact Name</label>
                                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" 
                                           class="form-control" value="<?php echo htmlspecialchars($booking['emergency_contact_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" 
                                           class="form-control" value="<?php echo htmlspecialchars($booking['emergency_contact_phone']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Special Requirements</h3>
                            
                            <div class="form-group">
                                <label for="special_requests">Special Requests</label>
                                <textarea id="special_requests" name="special_requests" class="form-control" rows="3"
                                          placeholder="Any special requests or requirements..."><?php echo htmlspecialchars($booking['special_requests']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="dietary_requirements">Dietary Requirements</label>
                                <textarea id="dietary_requirements" name="dietary_requirements" class="form-control" rows="3"
                                          placeholder="Any dietary restrictions or preferences..."><?php echo htmlspecialchars($booking['dietary_requirements']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="medical_conditions">Medical Conditions</label>
                                <textarea id="medical_conditions" name="medical_conditions" class="form-control" rows="3"
                                          placeholder="Any medical conditions or medications..."><?php echo htmlspecialchars($booking['medical_conditions']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function updatePricing() {
            const tourSelect = document.getElementById('tour_id');
            const selectedOption = tourSelect.options[tourSelect.selectedIndex];
            
            if (selectedOption.value) {
                const adultPrice = parseFloat(selectedOption.dataset.adultPrice) || 0;
                const childPrice = parseFloat(selectedOption.dataset.childPrice) || 0;
                const infantPrice = parseFloat(selectedOption.dataset.infantPrice) || 0;
                
                const adults = parseInt(document.getElementById('adults').value) || 0;
                const children = parseInt(document.getElementById('children').value) || 0;
                const infants = parseInt(document.getElementById('infants').value) || 0;
                
                const total = (adults * adultPrice) + (children * childPrice) + (infants * infantPrice);
                document.getElementById('total_amount').value = total.toFixed(2);
            }
        }
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
