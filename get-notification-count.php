<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

require_once 'config/database.php';
require_once 'classes/SmartNotifications.php';

$database = new Database();
$db = $database->getConnection();
$notifications = new SmartNotifications($db);

$count = $notifications->getUnreadCount($_SESSION['user_id']);

echo json_encode(['count' => $count]);
?>