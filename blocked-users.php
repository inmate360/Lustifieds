<?php
session_start();
require_once 'config/database.php';
require_once 'classes/BlockedUsers.php';
require_once 'classes/CSRF.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$blockedUsers = new BlockedUsers($db);

$success = '';
$error = '';

// Handle unblock
if(isset($_POST['unblock']) && isset($_POST['user_id'])) {
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $result = $blockedUsers->unblockUser($_SESSION['user_id'], (int)$_POST['user_id']);
        if($result['success']) {
            $success = 'User unblocked successfully';
        } else {
            $error = $result['error'] ?? 'Failed to unblock user';
        }
    }
}

// Get blocked users list
$blocked_list = $blockedUsers->getBlockedUsers($_SESSION['user_id']);

$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">

<style>
.blocked-users-container {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 20px;
}

.blocked-user-item {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.blocked-user-info {
    flex: 1;
}

.blocked-user-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-white);
}

.blocked-user-meta {
    color: var(--text-gray);
    font-size: 0.9rem;
}
</style>

<div class="page-content">
    <div class="blocked-users-container">
        <h1 style="margin-bottom: 0.5rem;">🚫 Blocked Users</h1>
        <p style="color: var(--text-gray); margin-bottom: 2rem;">
            Manage users you've blocked from contacting you
        </p>

        <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(count($blocked_list) > 0): ?>
            <?php foreach($blocked_list as $blocked): ?>
            <div class="blocked-user-item">
                <div class="blocked-user-info">
                    <div class="blocked-user-name">
                        <?php echo htmlspecialchars($blocked['username']); ?>
                    </div>
                    <div class="blocked-user-meta">
                        Blocked on <?php echo date('M j, Y', strtotime($blocked['blocked_at'])); ?>
                        <?php if($blocked['reason']): ?>
                        <br>Reason: <?php echo htmlspecialchars($blocked['reason']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" style="display: inline;">
                    <?php echo CSRF::getHiddenInput(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $blocked['blocked_id']; ?>">
                    <button type="submit" name="unblock" class="btn-secondary">
                        Unblock
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
            <h3>No Blocked Users</h3>
            <p style="color: var(--text-gray);">
                You haven't blocked anyone yet
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/footer.php'; ?>