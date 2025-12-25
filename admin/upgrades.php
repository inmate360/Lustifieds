<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=/admin/upgrades.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT id, username, is_admin FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user || !$user['is_admin']) {
        header('Location: ../index.php');
        exit();
    }
} catch(PDOException $e) {
    error_log("Admin verification error: " . $e->getMessage());
    die("Database error.");
}

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['approve_upgrade'])) {
        try {
            $subscription_id = $_POST['subscription_id'];
            
            // Get subscription details
            $query = "SELECT * FROM user_subscriptions WHERE id = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $subscription_id, PDO::PARAM_INT);
            $stmt->execute();
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($subscription) {
                // Get plan details
                $query = "SELECT * FROM membership_plans WHERE id = :plan_id LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':plan_id', $subscription['plan_id'], PDO::PARAM_INT);
                $stmt->execute();
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $duration_days = $plan['duration_days'] ?? 30;
                $start_date = date('Y-m-d H:i:s');
                $end_date = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
                
                $query = "UPDATE user_subscriptions SET status = 'active', start_date = :start_date, end_date = :end_date, approved_by = :admin_id, approved_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':start_date', $start_date);
                $stmt->bindParam(':end_date', $end_date);
                $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':id', $subscription_id, PDO::PARAM_INT);
                
                if($stmt->execute()) {
                    $success = 'Upgrade approved successfully!';
                }
            }
        } catch(PDOException $e) {
            error_log("Error approving upgrade: " . $e->getMessage());
            $error = 'Failed to approve upgrade';
        }
    }
    
    if(isset($_POST['reject_upgrade'])) {
        try {
            $subscription_id = $_POST['subscription_id'];
            $rejection_reason = $_POST['rejection_reason'] ?? 'Payment verification failed';
            
            $query = "UPDATE user_subscriptions SET status = 'rejected', rejection_reason = :reason, approved_by = :admin_id, approved_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':reason', $rejection_reason);
            $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $subscription_id, PDO::PARAM_INT);
            
            if($stmt->execute()) {
                $success = 'Upgrade rejected';
            }
        } catch(PDOException $e) {
            error_log("Error rejecting upgrade: " . $e->getMessage());
            $error = 'Failed to reject upgrade';
        }
    }
}

// Get pending upgrades
try {
    $query = "SELECT us.*, u.username, u.email, mp.name as plan_name, mp.price FROM user_subscriptions us LEFT JOIN users u ON us.user_id = u.id LEFT JOIN membership_plans mp ON us.plan_id = mp.id WHERE us.status = 'pending' ORDER BY us.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_upgrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $pending_upgrades = [];
}

// Get recent actions
try {
    $query = "SELECT us.*, u.username, u.email, mp.name as plan_name, mp.price, a.username as admin_name FROM user_subscriptions us LEFT JOIN users u ON us.user_id = u.id LEFT JOIN membership_plans mp ON us.plan_id = mp.id LEFT JOIN users a ON us.approved_by = a.id WHERE us.status IN ('active', 'rejected') ORDER BY us.approved_at DESC LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_upgrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recent_upgrades = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Upgrades - Admin</title>
    <style>
        :root{--bg-dark:#0a0a0f;--bg-card:#1a1a2e;--border-color:#2d2d44;--primary-blue:#4267f5;--text-white:#ffffff;--text-gray:#a0a0b0;--success-green:#10b981;--warning-orange:#f59e0b;--danger-red:#ef4444;--info-cyan:#06b6d4}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg-dark);color:var(--text-white);line-height:1.6}
        .admin-container{max-width:1400px;margin:0 auto;padding:1rem}
        .admin-header{background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:2rem;margin-bottom:2rem}
        .admin-header h1{font-size:2rem;background:linear-gradient(135deg,var(--primary-blue),var(--info-cyan));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:.5rem}
        .admin-nav{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:2rem}
        .admin-nav a{padding:1rem;background:var(--bg-card);border:2px solid var(--border-color);border-radius:12px;text-decoration:none;color:var(--text-white);text-align:center;transition:all .3s;font-weight:500}
        .admin-nav a:hover{background:rgba(66,103,245,0.1);border-color:var(--primary-blue);transform:translateY(-2px)}
        .admin-nav a.active{background:linear-gradient(135deg,rgba(66,103,245,0.2),rgba(6,182,212,0.2));border-color:var(--primary-blue)}
        .admin-section{background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:2rem;margin-bottom:2rem}
        .admin-section h2{color:var(--primary-blue);margin-bottom:1.5rem;font-size:1.5rem}
        .alert{padding:1rem 1.5rem;border-radius:8px;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem}
        .alert-success{background:rgba(16,185,129,0.2);border:1px solid var(--success-green);color:var(--success-green)}
        .alert-error{background:rgba(239,68,68,0.2);border:1px solid var(--danger-red);color:var(--danger-red)}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:rgba(66,103,245,0.1);padding:1rem;text-align:left;color:var(--text-white);font-weight:600;border-bottom:2px solid var(--border-color);font-size:.9rem}
        .data-table td{padding:1rem;border-bottom:1px solid var(--border-color);color:var(--text-gray);font-size:.9rem}
        .data-table tr:hover{background:rgba(66,103,245,0.05)}
        .btn{padding:.75rem 1.5rem;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:.9rem;transition:all .3s}
        .btn-success{background:linear-gradient(135deg,var(--success-green),#059669);color:white}
        .btn-danger{background:linear-gradient(135deg,var(--danger-red),#dc2626);color:white}
        .btn-secondary{background:rgba(107,114,128,0.2);color:var(--text-white);border:2px solid var(--border-color)}
        .btn:hover{transform:translateY(-2px)}
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:9999;justify-content:center;align-items:center}
        .modal-content{background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:2rem;max-width:500px;width:90%}
        .form-group{margin-bottom:1.5rem}
        .form-group label{display:block;margin-bottom:.5rem;color:var(--text-white);font-weight:500}
        .form-group textarea{width:100%;padding:.875rem;background:rgba(255,255,255,0.05);border:2px solid var(--border-color);border-radius:8px;color:var(--text-white);font-size:1rem;resize:vertical}
        .empty-state{text-align:center;padding:3rem;background:rgba(66,103,245,0.05);border-radius:10px}
        @media (max-width:768px){.admin-nav{grid-template-columns:repeat(auto-fit,minmax(120px,1fr))}.data-table{font-size:.8rem}}
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>💎 Premium Upgrade Management</h1>
            <p style="color:var(--text-gray)">Review and approve premium membership purchases</p>
        </div>

        <div class="admin-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="users.php">👥 Users</a>
            <a href="listings.php">📝 Listings</a>
            <a href="upgrades.php" class="active">💎 Upgrades</a>
            <a href="reports.php">🚨 Reports</a>
            <a href="moderate-listings.php">⚖️ Moderate</a>
            <a href="announcements.php">📢 Announcements</a>
            <a href="categories.php">📁 Categories</a>
            <a href="settings.php">⚙️ Settings</a>
        </div>

        <?php if($success):?><div class="alert alert-success"><span style="font-size:1.5rem">✓</span><span><?php echo htmlspecialchars($success);?></span></div><?php endif;?>
        <?php if($error):?><div class="alert alert-error"><span style="font-size:1.5rem">✗</span><span><?php echo htmlspecialchars($error);?></span></div><?php endif;?>

        <div class="admin-section">
            <h2>⏳ Pending Upgrades (<?php echo count($pending_upgrades);?>)</h2>
            <?php if(count($pending_upgrades)>0):?>
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Plan</th><th>Price</th><th>Payment</th><th>Transaction ID</th><th>Requested</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($pending_upgrades as $upgrade):?>
                        <tr>
                            <td><?php echo $upgrade['id'];?></td>
                            <td><a href="../profile.php?id=<?php echo $upgrade['user_id'];?>" style="color:var(--primary-blue)"><?php echo htmlspecialchars($upgrade['username']);?></a></td>
                            <td><?php echo htmlspecialchars($upgrade['email']);?></td>
                            <td><strong style="color:var(--primary-blue)"><?php echo htmlspecialchars($upgrade['plan_name']);?></strong></td>
                            <td><strong style="color:var(--success-green)">$<?php echo number_format($upgrade['price'],2);?></strong></td>
                            <td><?php echo htmlspecialchars($upgrade['payment_method']??'N/A');?></td>
                            <td><code style="background:rgba(66,103,245,0.1);padding:.25rem .5rem;border-radius:4px;font-size:.85rem"><?php echo htmlspecialchars(substr($upgrade['transaction_id']??'N/A',0,20));?></code></td>
                            <td><?php echo date('M j, Y g:i A',strtotime($upgrade['created_at']));?></td>
                            <td>
                                <form method="POST" style="display:inline"><input type="hidden" name="subscription_id" value="<?php echo $upgrade['id'];?>"><button type="submit" name="approve_upgrade" class="btn btn-success" onclick="return confirm('Approve this upgrade?')">✓ Approve</button></form>
                                <button class="btn btn-danger" onclick="showRejectModal(<?php echo $upgrade['id'];?>)">✗ Reject</button>
                            </td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <?php else:?>
            <div class="empty-state"><div style="font-size:3rem;margin-bottom:1rem">✓</div><h3>No Pending Upgrades</h3><p style="color:var(--text-gray)">All upgrade requests have been processed</p></div>
            <?php endif;?>
        </div>

        <div class="admin-section">
            <h2>📋 Recent Actions</h2>
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>User</th><th>Plan</th><th>Price</th><th>Status</th><th>Approved By</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_upgrades as $upgrade):?>
                        <tr>
                            <td><?php echo $upgrade['id'];?></td>
                            <td><?php echo htmlspecialchars($upgrade['username']);?></td>
                            <td><?php echo htmlspecialchars($upgrade['plan_name']);?></td>
                            <td>$<?php echo number_format($upgrade['price'],2);?></td>
                            <td style="color:<?php echo $upgrade['status']=='active'?'var(--success-green)':'var(--danger-red)';?>"><?php echo ucfirst($upgrade['status']);?></td>
                            <td><?php echo htmlspecialchars($upgrade['admin_name']??'System');?></td>
                            <td><?php echo date('M j, Y',strtotime($upgrade['approved_at']));?></td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="text-align:center;padding:2rem;color:var(--text-gray)">
            <p><a href="dashboard.php" style="color:var(--primary-blue);text-decoration:none">← Back to Dashboard</a></p>
        </div>
    </div>

    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom:1rem">Reject Upgrade Request</h3>
            <form method="POST">
                <input type="hidden" name="subscription_id" id="rejectSubscriptionId">
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea name="rejection_reason" rows="4" required placeholder="e.g., Payment verification failed, Invalid payment method, etc."></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" name="reject_upgrade" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRejectModal(id){document.getElementById('rejectSubscriptionId').value=id;document.getElementById('rejectModal').style.display='flex'}
        function closeRejectModal(){document.getElementById('rejectModal').style.display='none'}
        document.getElementById('rejectModal').addEventListener('click',function(e){if(e.target===this)closeRejectModal()})
    </script>
</body>
</html>