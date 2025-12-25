<?php
session_start();
require_once 'config/database.php';

include 'views/header.php';

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $listing_id = isset($_POST['listing_id']) ? $_POST['listing_id'] : null;
    $reporter_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $reason = htmlspecialchars($_POST['reason']);
    $details = htmlspecialchars($_POST['details']);
    
    $query = "INSERT INTO reports (listing_id, reporter_id, reason, status) 
              VALUES (:listing_id, :reporter_id, :reason, 'pending')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':listing_id', $listing_id);
    $stmt->bindParam(':reporter_id', $reporter_id);
    $full_reason = $reason . "\n\nDetails: " . $details;
    $stmt->bindParam(':reason', $full_reason);
    
    if($stmt->execute()) {
        $success = 'Thank you for your report. Our moderation team will review it promptly.';
    } else {
        $error = 'Failed to submit report. Please try again.';
    }
}
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h1 style="text-align: center; margin-bottom: 2rem;">Report Abuse</h1>
            
            <div class="alert alert-warning">
                <strong>⚠️ Important:</strong> False reports may result in account suspension. Please only report genuine violations.
            </div>
            
            <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="report-abuse.php">
                <?php if(isset($_GET['listing_id'])): ?>
                <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($_GET['listing_id']); ?>">
                <?php else: ?>
                <div class="form-group">
                    <label>Listing ID (if applicable)</label>
                    <input type="number" name="listing_id" placeholder="Optional">
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Reason for Report *</label>
                    <select name="reason" required>
                        <option value="">Select a reason</option>
                        <option value="Spam or Commercial">Spam or Commercial Solicitation</option>
                        <option value="Inappropriate Content">Inappropriate Content</option>
                        <option value="Scam or Fraud">Scam or Fraud</option>
                        <option value="Harassment">Harassment or Threats</option>
                        <option value="Underage Content">Underage Content</option>
                        <option value="Violence">Violence or Illegal Activity</option>
                        <option value="Impersonation">Impersonation</option>
                        <option value="False Information">False or Misleading Information</option>
                        <option value="Other">Other Violation</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Additional Details *</label>
                    <textarea name="details" rows="6" required placeholder="Please provide as much detail as possible about the violation..."></textarea>
                </div>
                
                <button type="submit" class="btn-danger btn-block">Submit Report</button>
            </form>
            
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <h3 style="margin-bottom: 1rem;">What Happens Next?</h3>
                <ul style="color: var(--text-gray); line-height: 1.8;">
                    <li>Our moderation team will review your report within 24 hours</li>
                    <li>If the content violates our <a href="terms.php">Terms of Service</a>, it will be removed</li>
                    <li>Repeat offenders may have their accounts suspended or banned</li>
                    <li>You may be contacted if we need additional information</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>