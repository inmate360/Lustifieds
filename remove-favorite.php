<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';

if(!isset($_SESSION['user_id']) || !isset($_POST['user_id'])) {
    header('Location: browse-members.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$favorited_user_id = $_POST['user_id'];

if($userProfile->removeFavorite($_SESSION['user_id'], $favorited_user_id)) {
    $_SESSION['success'] = 'Removed from favorites';
} else {
    $_SESSION['error'] = 'Failed to remove from favorites';
}

// Redirect back to referring page or profile
$referer = $_SERVER['HTTP_REFERER'] ?? 'profile.php?id=' . $favorited_user_id;
header('Location: ' . $referer);
exit();
?>