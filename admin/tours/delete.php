<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('tours.delete');

$tour_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$tour_id) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Get tour details
$stmt = $db->prepare("SELECT id, title, featured_image, gallery, brochure_pdf FROM tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Check if tour has bookings
$stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE tour_id = ?");
$stmt->execute([$tour_id]);
$booking_count = $stmt->fetchColumn();

if ($booking_count > 0) {
    header('Location: index.php?error=Cannot delete tour with existing bookings');
    exit;
}

try {
    $db->beginTransaction();

    // Delete related records
    $db->prepare("DELETE FROM tour_addons WHERE tour_id = ?")->execute([$tour_id]);
    $db->prepare("DELETE FROM itineraries WHERE tour_id = ?")->execute([$tour_id]);
    $db->prepare("DELETE FROM tour_inventory WHERE tour_id = ?")->execute([$tour_id]);
    $db->prepare("DELETE FROM tour_reviews WHERE tour_id = ?")->execute([$tour_id]);

    // Delete media files
    if ($tour['featured_image'] && file_exists('../../' . $tour['featured_image'])) {
        unlink('../../' . $tour['featured_image']);
    }

    if ($tour['gallery']) {
        $gallery = json_decode($tour['gallery'], true);
        if (is_array($gallery)) {
            foreach ($gallery as $image) {
                if (file_exists('../../' . $image)) {
                    unlink('../../' . $image);
                }
            }
        }
    }

    if ($tour['brochure_pdf'] && file_exists('../../' . $tour['brochure_pdf'])) {
        unlink('../../' . $tour['brochure_pdf']);
    }

    // Delete the tour
    $stmt = $db->prepare("DELETE FROM tours WHERE id = ?");
    $stmt->execute([$tour_id]);

    $db->commit();

    // Log activity
    $auth->logActivity($_SESSION['user_id'], 'tour_deleted', "Deleted tour: " . $tour['title']);

    header('Location: index.php?success=Tour deleted successfully');
    exit;

} catch (Exception $e) {
    $db->rollBack();
    error_log("Error deleting tour: " . $e->getMessage());
    header('Location: index.php?error=Error deleting tour');
    exit;
}
?>
