<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

$country_id = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;

if ($country_id > 0) {
    $stmt = $db->prepare("SELECT id, name FROM regions WHERE country_id = ?");
    $stmt->execute([$country_id]);
    $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'regions' => $regions
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid country ID']);
}
