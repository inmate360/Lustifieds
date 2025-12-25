<?php
session_start();
include 'views/header-dark.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card">
            <h1>Privacy Policy</h1>
            <p><em>Last Updated: <?php echo date('F j, Y'); ?></em></p>

            <h2>1. Information We Collect</h2>
            
            <h3>Information You Provide</h3>
            <ul>
                <li><strong>Account Information:</strong> Email address, username, password, phone number (optional)</li>
                <li><strong>Profile Information:</strong> Age, gender, location preferences</li>
                <li><strong>Listing Content:</strong> Titles, descriptions, images you upload</li>
                <li><strong>Messages:</strong> Communications with other users</li>
                <li><strong>Payment Information:</strong> Processed securely through Stripe (we don't store card details)</li>
            </ul>

            <h3>Information Automatically Collected</h3>
            <ul>
                <li><strong>Usage Data:</strong> Pages visited, time spent, features used</li>
                <li><strong>Device Information:</strong> IP address, browser type, operating system</li>
                <li><strong>Cookies:</strong> Session cookies for authentication and preferences</li>
            </ul>

            <h2>2. How We Use Your Information</h2>
            <p>We use the collected information for:</p>
            <ul>
                <li>Providing and improving our Service</li>
                <li>Processing your transactions</li>
                <li>Communicating with you about your account</li>
                <li>Sending notifications about messages and activity</li>
                <li>Enforcing our Terms of Service</li>
                <li>Preventing fraud and abuse</li>
                <li>Complying with legal obligations</li>
            </ul>

            <h2>3. Information Sharing</h2>
            <p>We do not sell your personal information. We may share information with:</p>
            <ul>
                <li><strong>Other Users:</strong> Information in your public listings and profile</li>
                <li><strong>Service Providers:</strong> Payment processors, hosting providers</li>
                <li><strong>Legal Authorities:</strong> When required by law or to protect rights</li>
                <li><strong>Business Transfers:</strong> In case of merger, acquisition, or sale</li>
            </ul>

            <h2>4. Data Security</h2>
            <p>We implement appropriate security measures including:</p>
            <ul>
                <li>Password hashing and encryption</li>
                <li>Secure HTTPS connections</li>
                <li>Regular security audits</li>
                <li>Access controls and monitoring</li>
            </ul>
            <p><strong>Note:</strong> No method of transmission over the Internet is 100% secure.</p>

            <h2>5. Your Rights and Choices</h2>
            
            <h3>Access and Update</h3>
            <p>You can access and update your account information at any time through your account settings.</p>

            <h3>Delete Account</h3>
            <p>You may request account deletion by contacting us. Some information may be retained for legal purposes.</p>

            <h3>Marketing Communications</h3>
            <p>You can opt-out of marketing emails by following the unsubscribe link or contacting us.</p>

            <h3>Cookies</h3>
            <p>You can control cookies through your browser settings, though this may affect functionality.</p>

            <h2>6. Data Retention</h2>
            <p>We retain your information for as long as your account is active or as needed to provide services. After account deletion:</p>
            <ul>
                <li>Most personal data is deleted within 30 days</li>
                <li>Some data may be retained for legal compliance</li>
                <li>Backup copies may exist for up to 90 days</li>
            </ul>

            <h2>7. Children's Privacy</h2>
            <p><strong>Our Service is not intended for users under 18 years of age.</strong> We do not knowingly collect information from minors. If you believe we have inadvertently collected such information, please contact us immediately.</p>

            <h2>8. International Users</h2>
            <p>Our Service is based in the United States. By using the Service, you consent to the transfer of your information to the United States.</p>

            <h2>9. Third-Party Links</h2>
            <p>Our Service may contain links to third-party websites. We are not responsible for the privacy practices of these sites.</p>

            <h2>10. Changes to Privacy Policy</h2>
            <p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page.</p>

            <h2>11. Contact Us</h2>
            <p>If you have questions about this Privacy Policy, please contact us at:</p>
            <p><a href="contact.php">contact.php</a> or email: privacy@doublelist-clone.com</p>

            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <p style="text-align: center;">
                    <a href="terms.php" class="btn-secondary" style="margin: 0 0.5rem;">Terms of Service</a>
                    <a href="safety.php" class="btn-secondary" style="margin: 0 0.5rem;">Safety Tips</a>
                    <a href="choose-location.php" class="btn-primary" style="margin: 0 0.5rem;">Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>