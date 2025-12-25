<?php
require_once 'config/database.php';
require_once 'classes/Location.php';

header('Content-Type: application/json');

if(!isset($_GET['state_id'])) {
    echo json_encode([]);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$location = new Location($db);

$cities = $location->getCitiesByState($_GET['state_id']);

echo json_encode($cities);
?>