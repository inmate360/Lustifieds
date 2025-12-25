<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    exit();
}

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Update user's online status and last activity
$query = "UPDATE users 
          SET is_online = TRUE, last_activity = CURRENT_TIMESTAMP 
          WHERE id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

echo json_encode(['success' => true]);
?>