<?php
session_start();
include 'views/header-dark.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card">
            <h1>Support Center</h1>
            <p>Welcome to our Support Center. Find answers to common questions or contact us for help.</p>

            <h2>📚 Quick Links</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin: 2rem 0;">
                <a href="faq.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">❓</div>
                    <h3>FAQ</h3>
                    <p style="color: var(--text-gray);">Frequently Asked Questions</p>
                </a>
                <a href="contact.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">✉️</div>
                    <h3>Contact Us</h3>
                    <p style="color: var(--text-gray);">Send us a message</p>
                </a>
                <a href="report-abuse.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🚨</div>
                    <h3>Report Abuse</h3>
                    <p style="color: var(--text-gray);">Report violations</p>
                </a>
                <a href="safety.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🛡️</div>
                    <h3>Safety Tips</h3>
                    <p style="color: var(--text-gray);">Stay safe online</p>
                </a>
            </div>

            <h2>🔧 Common Issues</h2>
            
            <h3>Account Issues</h3>
            <ul>
                <li><a href="faq.php#reset-password">I forgot my password</a></li>
                <li><a href="faq.php#verify-email">How do I verify my email?</a></li>
                <li><a href="faq.php#delete-account">How do I delete my account?</a></li>
                <li><a href="faq.php#update-profile">How do I update my profile?</a></li>
            </ul>

            <h3>Posting & Listings</h3>
            <ul>
                <li><a href="faq.php#create-listing">How do I create a listing?</a></li>
                <li><a href="faq.php#upload-images">How do I upload images?</a></li>
                <li><a href="faq.php#edit-listing">How do I edit my listing?</a></li>
                <li><a href="faq.php#delete-listing">How do I delete a listing?</a></li>
            </ul>

            <h3>Messaging</h3>
            <ul>
                <li><a href="faq.php#send-message">How do I send a message?</a></li>
                <li><a href="faq.php#read-messages">How do I read my messages?</a></li>
                <li><a href="faq.php#block-user">How do I block a user?</a></li>
            </ul>

            <h3>Payments & Subscriptions</h3>
            <ul>
                <li><a href="faq.php#upgrade-account">How do I upgrade my account?</a></li>
                <li><a href="faq.php#cancel-subscription">How do I cancel my subscription?</a></li>
                <li><a href="faq.php#refund-policy">What is your refund policy?</a></li>
                <li><a href="faq.php#featured-ads">How do featured ads work?</a></li>
            </ul>

            <h2>📞 Contact Information</h2>
            <div class="alert alert-info">
                <p><strong>Email:</strong> support@doublelist-clone.com</p>
                <p><strong>Response Time:</strong> We typically respond within 24-48 hours</p>
                <p><strong>Hours:</strong> Monday - Friday, 9AM - 5PM PST</p>
            </div>

            <h2>🌐 Additional Resources</h2>
            <ul>
                <li><a href="terms.php">Terms of Service</a></li>
                <li><a href="privacy.php">Privacy Policy</a></li>
                <li><a href="safety.php">Safety Guidelines</a></li>
            </ul>

            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <p style="text-align: center;">
                    <a href="contact.php" class="btn-primary" style="margin: 0 0.5rem;">Contact Support</a>
                    <a href="choose-location.php" class="btn-secondary" style="margin: 0 0.5rem;">Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>