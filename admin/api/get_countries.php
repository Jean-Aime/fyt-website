<?php
// Absolute first line - no spaces!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../config/config.php';

    if (!isset($_GET['continent']) && !isset($_GET['continent_id'])) {
        throw new Exception('continent or continent_id is required');
    }

    if (isset($_GET['continent_id'])) {
        $stmt = $db->prepare("SELECT id, name FROM countries WHERE continent_id = ? ORDER BY name");
        $stmt->execute([intval($_GET['continent_id'])]);
    } else {
        $stmt = $db->prepare("SELECT id, name FROM countries WHERE continent = ? ORDER BY name");
        $stmt->execute([trim($_GET['continent'])]);
    }

    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $countries
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
