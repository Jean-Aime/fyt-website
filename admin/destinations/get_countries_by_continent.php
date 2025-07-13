<?php
header('Content-Type: application/json');
require_once 'config/config.php';

if (!isset($_GET['continent_id']) || !is_numeric($_GET['continent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid continent ID']);
    exit;
}

$continent_id = (int) $_GET['continent_id'];

try {
    $stmt = $db->prepare("SELECT id, name FROM countries WHERE continent_id = :continent_id ORDER BY name");
    $stmt->execute(['continent_id' => $continent_id]);
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'countries' => $countries]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
