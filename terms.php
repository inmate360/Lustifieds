<?php
session_start();
include 'views/header-dark.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card">
            <h1>Terms of Service</h1>
            <p><em>Last Updated: <?php echo date('F j, Y'); ?></em></p>

            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using DoubleList Clone ("the Service"), you accept and agree to be bound by the terms and provision of this agreement.</p>

            <h2>2. Use License</h2>
            <p>Permission is granted to temporarily use the Service for personal, non-commercial purposes. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
            <ul>
                <li>Modify or copy the materials</li>
                <li>Use the materials for any commercial purpose or public display</li>
                <li>Attempt to reverse engineer any software contained on the Service</li>
                <li>Remove any copyright or other proprietary notations</li>
                <li>Transfer the materials to another person or "mirror" the materials on any other server</li>
            </ul>

            <h2>3. User Conduct</h2>
            <p>You agree to use the Service only for lawful purposes. You agree not to:</p>
            <ul>
                <li>Post false, inaccurate, misleading, or defamatory content</li>
                <li>Impersonate any person or entity</li>
                <li>Post content that violates any law or regulation</li>
                <li>Harass, threaten, or harm another person</li>
                <li>Spam, solicit, or scam other users</li>
                <li>Post content involving minors</li>
                <li>Use automated systems to access the Service</li>
            </ul>

            <h2>4. Age Requirement</h2>
            <p><strong>You must be at least 18 years old to use this Service.</strong> By using this Service, you represent and warrant that you are of legal age.</p>

            <h2>5. Content Policy</h2>
            <p>All users are responsible for the content they post. We reserve the right to remove any content that violates these terms or is otherwise objectionable.</p>

            <h3>Prohibited Content:</h3>
            <ul>
                <li>Content involving minors in any way</li>
                <li>Content depicting violence or illegal activities</li>
                <li>Content that infringes intellectual property rights</li>
                <li>Spam or commercial solicitation</li>
                <li>Personal information of others without consent</li>
            </ul>

            <h2>6. Account Termination</h2>
            <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason, including breach of these Terms.</p>

            <h2>7. Disclaimer</h2>
            <p>The Service is provided "as is". We make no warranties, expressed or implied, and hereby disclaim all warranties including without limitation, implied warranties of merchantability, fitness for a particular purpose, and non-infringement.</p>

            <h2>8. Limitation of Liability</h2>
            <p>In no event shall DoubleList Clone or its suppliers be liable for any damages arising out of the use or inability to use the Service.</p>

            <h2>9. Privacy</h2>
            <p>Your use of the Service is also governed by our <a href="privacy.php">Privacy Policy</a>.</p>

            <h2>10. Changes to Terms</h2>
            <p>We reserve the right to modify these terms at any time. We will notify users of any changes by posting the new Terms of Service on this page.</p>

            <h2>11. Contact Information</h2>
            <p>If you have any questions about these Terms, please <a href="contact.php">contact us</a>.</p>

            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <p style="text-align: center;">
                    <a href="privacy.php" class="btn-secondary" style="margin: 0 0.5rem;">Privacy Policy</a>
                    <a href="safety.php" class="btn-secondary" style="margin: 0 0.5rem;">Safety Tips</a>
                    <a href="choose-location.php" class="btn-primary" style="margin: 0 0.5rem;">Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>