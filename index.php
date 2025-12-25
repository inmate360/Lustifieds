<?php
session_start();
require_once 'config/database.php';
require_once 'includes/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

// Check for maintenance mode
if(checkMaintenanceMode($db)) {
    header('Location: maintenance.php');
    exit();
}

// Redirect to location selection if no city is selected
if(!isset($_SESSION['current_city'])) {
    header('Location: choose-location.php');
    exit();
}

// Redirect to city page
header('Location: city.php?location=' . $_SESSION['current_city']);
exit();
?>