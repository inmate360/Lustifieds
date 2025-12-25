<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Report.php';
require_once 'classes/CSRF.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$report = new Report($db);

$listing_id = (int)($_GET['listing_id'] ?? 0);
$user_id = (int)($_GET['user_id'] ?? 0);

if(!$listing_id && !$user_id) {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $reported_type = $listing_id ? 'listing' : 'user';
        $reported_id = $listing_id ?: $user_id;
        $reason = $_POST['reason'];
        $details = trim($_POST['details']);
        
        if(empty($reason)) {
            $error = 'Please select a reason';
        } elseif(empty($details)) {
            $error = 'Please provide details';
        } else {
            $result = $report->create(
                $_SESSION['user_id'],
                $reported_type,
                $reported_id,
                $reason,
                $details
            );
            
            if($result['success']) {
                $success = 'Report submitted successfully. Our team will review it.';
            } else {
                $error = $result['error'] ?? 'Failed to submit report';
            }
        }
    }
}

$csrf_token = CSRF::getToken();

include 'views/header.php';
?>

<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/light-theme.css">

<style>
.report-container {
    max-width: 700px;
    margin: 2rem auto;
    padding: 0 20px;
}

.report-header {
    background: linear-gradient(135deg, var(--danger-red), #dc2626);
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
    color: white;
}

.report-form {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
}

.reason-option {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.reason-option:hover {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.05);
}

.reason-option input[type="radio"] {
    width: 20px;
    height: 20px;
}

.reason-option.selected {
    border-color: var(--danger-red);
    background: rgba(239, 68, 68, 0.1);
}

.reason-label {
    flex: 1;
}

.reason-title {
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 0.25rem;
}

.reason-description {
    font-size: 0.85rem;
    color: var(--text-gray);
}

.warning-box {
    background: rgba(245, 158, 11, 0.1);
    border: 2px solid var(--warning-orange);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.success-message {
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid var(--success-green);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
}
</style>

<div class="page-content">
    <div class="report-container">
        <?php if($success): ?>
        <!-- Success Message -->
        <div class="success-message">
            <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
            <h2 style="color: var(--success-green); margin-bottom: 1rem;">Report Submitted</h2>
            <p style="color: var(--text-gray); margin-bottom: 2rem;">
                Thank you for helping keep Turnpage safe. Our moderation team will review this report within 24 hours.
            </p>
            <a href="<?php echo $listing_id ? '/listing.php?id=' . $listing_id : '/'; ?>" class="btn-primary">
                Go Back
            </a>
        </div>
        <?php else: ?>
        
        <!-- Report Header -->
        <div class="report-header">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🚨</div>
            <h1>Report <?php echo $listing_id ? 'Listing' : 'User'; ?></h1>
            <p style="opacity: 0.95;">Help us maintain a safe community</p>
        </div>

        <?php if($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Warning -->
        <div class="warning-box">
            <strong style="color: var(--warning-orange);">⚠️ Important</strong>
            <ul style="margin: 0.5rem 0 0 1.5rem; line-height: 1.8; color: var(--text-gray);">
                <li>False reports may result in account suspension</li>
                <li>Reports are reviewed by our moderation team</li>
                <li>You will not be notified of the outcome</li>
                <li>Serious violations may be reported to authorities</li>
            </ul>
        </div>

        <!-- Report Form -->
        <div class="report-form">
            <form method="POST">
                <?php echo CSRF::getHiddenInput(); ?>
                
                <h3 style="margin-bottom: 1.5rem; color: var(--text-white);">Select a Reason</h3>
                
                <div class="reason-option" onclick="selectReason('spam', this)">
                    <input type="radio" name="reason" value="spam" id="reason_spam" required>
                    <label for="reason_spam" class="reason-label">
                        <div class="reason-title">🚫 Spam or Scam</div>
                        <div class="reason-description">Unsolicited commercial content, phishing attempts, or fraudulent activity</div>
                    </label>
                </div>
                
                <div class="reason-option" onclick="selectReason('inappropriate', this)">
                    <input type="radio" name="reason" value="inappropriate_content" id="reason_inappropriate" required>
                    <label for="reason_inappropriate" class="reason-label">
                        <div class="reason-title">⚠️ Inappropriate Content</div>
                        <div class="reason-description">Content that violates community guidelines or terms of service</div>
                    </label>
                </div>
                
                <div class="reason-option" onclick="selectReason('harassment', this)">
                    <input type="radio" name="reason" value="harassment" id="reason_harassment" required>
                    <label for="reason_harassment" class="reason-label">
                        <div class="reason-title">😠 Harassment or Abuse</div>
                        <div class="reason-description">Threatening behavior, hate speech, or abusive language</div>
                    </label>
                </div>
                
                <div class="reason-option" onclick="selectReason('fake', this)">
                    <input type="radio" name="reason" value="fake_profile" id="reason_fake" required>
                    <label for="reason_fake" class="reason-label">
                        <div class="reason-title">👤 Fake Profile</div>
                        <div class="reason-description">Impersonation, stolen photos, or misleading information</div>
                    </label>
                </div>
                
                <div class="reason-option" onclick="selectReason('illegal', this)">
                    <input type="radio" name="reason" value="illegal_activity" id="reason_illegal" required>
                    <label for="reason_illegal" class="reason-label">
                        <div class="reason-title">⚖️ Illegal Activity</div>
                        <div class="reason-description">Prostitution, drug sales, or other illegal activities</div>
                    </label>
                </div>
                
                <div class="reason-option" onclick="selectReason('underage', this)">
                    <input type="radio" name="reason" value="underage" id="reason_underage" required>
                    <label for="reason_underage" class="reason-label">
                        <div class="reason-title">🔞 Underage User</div>
                        <div class="reason-description">Suspected minor or underage content</div>
                    </label>
                </div>
                
                <div class="reason-option" onclick="selectReason('other', this)">
                    <input type="radio" name="reason" value="other" id="reason_other" required>
                    <label for="reason_other" class="reason-label">
                        <div class="reason-title">❓ Other</div>
                        <div class="reason-description">Other violation not listed above</div>
                    </label>
                </div>
                
                <div class="form-group" style="margin-top: 2rem;">
                    <label>Additional Details *</label>
                    <textarea name="details" 
                              rows="6" 
                              required 
                              minlength="20"
                              placeholder="Please provide specific details about why you're reporting this. Include any relevant information that will help our team investigate."></textarea>
                    <small style="color: var(--text-gray); display: block; margin-top: 0.5rem;">
                        Minimum 20 characters
                    </small>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn-danger" style="flex: 1;">
                        Submit Report
                    </button>
                    <a href="<?php echo $listing_id ? '/listing.php?id=' . $listing_id : '/'; ?>" class="btn-secondary" style="flex: 1; text-align: center; padding: 0.75rem;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<!-- Theme Toggle -->
<?php include 'components/theme-toggle.php'; ?>

<script>
function selectReason(reason, element) {
    // Remove selected class from all
    document.querySelectorAll('.reason-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    // Add selected class to clicked
    element.classList.add('selected');
    
    // Check the radio button
    document.getElementById('reason_' + reason).checked = true;
}
</script>

<?php include 'views/footer.php'; ?>