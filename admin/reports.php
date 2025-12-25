<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=/admin/reports.php');
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

// Ensure reports table exists
try {
    $create_table = "CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        report_type ENUM('listing', 'user', 'message') NOT NULL,
        reported_id INT NOT NULL,
        reason VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
        action_taken TEXT,
        resolved_by INT NULL,
        resolved_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_reporter (reporter_id)
    )";
    $db->exec($create_table);
} catch(PDOException $e) {
    error_log("Error creating reports table: " . $e->getMessage());
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['resolve_report'])) {
        try {
            $query = "UPDATE reports SET status = 'resolved', action_taken = :action, resolved_by = :admin_id, resolved_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':action', $_POST['action_taken']);
            $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $_POST['report_id'], PDO::PARAM_INT);
            
            if($stmt->execute()) {
                $success = 'Report resolved successfully!';
            }
        } catch(PDOException $e) {
            $error = 'Failed to resolve report';
        }
    }
    
    if(isset($_POST['dismiss_report'])) {
        try {
            $query = "UPDATE reports SET status = 'dismissed', resolved_by = :admin_id, resolved_at = NOW() WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $_POST['report_id'], PDO::PARAM_INT);
            
            if($stmt->execute()) {
                $success = 'Report dismissed';
            }
        } catch(PDOException $e) {
            $error = 'Failed to dismiss report';
        }
    }
}

// Get pending reports
try {
    $query = "SELECT r.*, u.username as reporter_name FROM reports r LEFT JOIN users u ON r.reporter_id = u.id WHERE r.status = 'pending' ORDER BY r.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $pending_reports = [];
}

// Get recent resolved reports
try {
    $query = "SELECT r.*, u.username as reporter_name, a.username as admin_name FROM reports r LEFT JOIN users u ON r.reporter_id = u.id LEFT JOIN users a ON r.resolved_by = a.id WHERE r.status IN ('resolved', 'dismissed') ORDER BY r.resolved_at DESC LIMIT 20";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recent_reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management - Admin</title>
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
        .data-table th{background:rgba(66,103,245,0.1);padding:1rem;text-align:left;color:var(--text-white);font-weight:600;border-bottom:2px solid var(--border-color)}
        .data-table td{padding:1rem;border-bottom:1px solid var(--border-color);color:var(--text-gray)}
        .data-table tr:hover{background:rgba(66,103,245,0.05)}
        .action-btn{padding:.5rem 1rem;border-radius:8px;font-size:.85rem;margin-right:.5rem;border:none;cursor:pointer;font-weight:500;transition:all .3s}
        .action-btn.view{background:rgba(6,182,212,0.2);color:var(--info-cyan)}
        .action-btn.edit{background:rgba(245,158,11,0.2);color:var(--warning-orange)}
        .action-btn.delete{background:rgba(239,68,68,0.2);color:var(--danger-red)}
        .action-btn:hover{transform:translateY(-2px)}
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:9999;justify-content:center;align-items:center}
        .modal-content{background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:2rem;max-width:500px;width:90%}
        .form-group{margin-bottom:1.5rem}
        .form-group label{display:block;margin-bottom:.5rem;color:var(--text-white);font-weight:500}
        .form-group select{width:100%;padding:.875rem;background:rgba(255,255,255,0.05);border:2px solid var(--border-color);border-radius:8px;color:var(--text-white);font-size:1rem}
        .btn{padding:.875rem 1.75rem;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:1rem;transition:all .3s}
        .btn-success{background:linear-gradient(135deg,var(--success-green),#059669);color:white}
        .btn-secondary{background:rgba(107,114,128,0.2);color:var(--text-white);border:2px solid var(--border-color)}
        .btn:hover{transform:translateY(-2px)}
        .empty-state{text-align:center;padding:3rem;background:rgba(66,103,245,0.05);border-radius:10px}
        @media (max-width:768px){.admin-nav{grid-template-columns:repeat(auto-fit,minmax(120px,1fr))}}
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>🚨 Reports Management</h1>
            <p style="color:var(--text-gray)">Review and resolve user reports</p>
        </div>

        <div class="admin-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="users.php">👥 Users</a>
            <a href="listings.php">📝 Listings</a>
            <a href="upgrades.php">💎 Upgrades</a>
            <a href="reports.php" class="active">🚨 Reports</a>
            <a href="moderate-listings.php">⚖️ Moderate</a>
            <a href="announcements.php">📢 Announcements</a>
            <a href="categories.php">📁 Categories</a>
            <a href="settings.php">⚙️ Settings</a>
        </div>

        <?php if($success):?><div class="alert alert-success"><span style="font-size:1.5rem">✓</span><span><?php echo htmlspecialchars($success);?></span></div><?php endif;?>
        <?php if($error):?><div class="alert alert-error"><span style="font-size:1.5rem">✗</span><span><?php echo htmlspecialchars($error);?></span></div><?php endif;?>

        <div class="admin-section">
            <h2>⏳ Pending Reports (<?php echo count($pending_reports);?>)</h2>
            <?php if(count($pending_reports)>0):?>
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Reporter</th><th>Type</th><th>Reported ID</th><th>Reason</th><th>Description</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($pending_reports as $report):?>
                        <tr>
                            <td><?php echo $report['id'];?></td>
                            <td><?php echo htmlspecialchars($report['reporter_name']);?></td>
                            <td><span style="padding:.25rem .75rem;border-radius:12px;font-size:.85rem;background:rgba(66,103,245,0.2);color:var(--info-cyan)"><?php echo ucfirst($report['report_type']);?></span></td>
                            <td><?php if($report['report_type']=='listing'):?><a href="../listing.php?id=<?php echo $report['reported_id'];?>" style="color:var(--primary-blue)" target="_blank">View</a><?php elseif($report['report_type']=='user'):?><a href="../profile.php?id=<?php echo $report['reported_id'];?>" style="color:var(--primary-blue)" target="_blank">View</a><?php else:?>ID: <?php echo $report['reported_id'];?><?php endif;?></td>
                            <td style="color:var(--danger-red)"><?php echo htmlspecialchars($report['reason']);?></td>
                            <td><div style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($report['description']);?></div></td>
                            <td><?php echo date('M j, Y',strtotime($report['created_at']));?></td>
                            <td>
                                <button class="action-btn edit" onclick="showResolveModal(<?php echo $report['id'];?>)">Resolve</button>
                                <form method="POST" style="display:inline"><input type="hidden" name="report_id" value="<?php echo $report['id'];?>"><button type="submit" name="dismiss_report" class="action-btn delete" onclick="return confirm('Dismiss this report?')">Dismiss</button></form>
                            </td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>
            <?php else:?>
            <div class="empty-state"><div style="font-size:3rem;margin-bottom:1rem">✓</div><h3>No Pending Reports</h3><p style="color:var(--text-gray)">All reports have been reviewed</p></div>
            <?php endif;?>
        </div>

        <div class="admin-section">
            <h2>📋 Recent Actions</h2>
            <div style="overflow-x:auto">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Reporter</th><th>Type</th><th>Reason</th><th>Status</th><th>Action</th><th>Resolved By</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_reports as $report):?>
                        <tr>
                            <td><?php echo $report['id'];?></td>
                            <td><?php echo htmlspecialchars($report['reporter_name']);?></td>
                            <td><?php echo ucfirst($report['report_type']);?></td>
                            <td><?php echo htmlspecialchars($report['reason']);?></td>
                            <td style="color:<?php echo $report['status']=='resolved'?'var(--success-green)':'var(--text-gray)';?>"><?php echo ucfirst($report['status']);?></td>
                            <td><?php echo htmlspecialchars($report['action_taken']??'N/A');?></td>
                            <td><?php echo htmlspecialchars($report['admin_name']);?></td>
                            <td><?php echo date('M j, Y',strtotime($report['resolved_at']));?></td>
                        </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="resolveModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom:1rem">Resolve Report</h3>
            <form method="POST">
                <input type="hidden" name="report_id" id="resolveReportId">
                <div class="form-group">
                    <label>Action Taken</label>
                    <select name="action_taken" required>
                        <option value="">Select action...</option>
                        <option value="Content removed">Content removed</option>
                        <option value="User warned">User warned</option>
                        <option value="User suspended">User suspended</option>
                        <option value="User banned">User banned</option>
                        <option value="No action needed">No action needed</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <button type="button" class="btn btn-secondary" onclick="closeResolveModal()">Cancel</button>
                    <button type="submit" name="resolve_report" class="btn btn-success">Resolve</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showResolveModal(id){document.getElementById('resolveReportId').value=id;document.getElementById('resolveModal').style.display='flex'}
        function closeResolveModal(){document.getElementById('resolveModal').style.display='none'}
        document.getElementById('resolveModal').addEventListener('click',function(e){if(e.target===this)closeResolveModal()})
    </script>
</body>
</html>