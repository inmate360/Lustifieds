<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';

if(!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: browse-members.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$blocked_user_id = $_GET['id'];

// Can't block yourself
if($blocked_user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot block yourself';
    header('Location: browse-members.php');
    exit();
}

$reason = $_POST['reason'] ?? 'User blocked';

if($userProfile->blockUser($_SESSION['user_id'], $blocked_user_id, $reason)) {
    $_SESSION['success'] = 'User has been blocked';
} else {
    $_SESSION['error'] = 'Failed to block user or user already blocked';
}

header('Location: browse-members.php');
exit();
?>