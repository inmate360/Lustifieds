<?php
session_start();
include 'views/header.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card">
            <h1>Frequently Asked Questions</h1>

            <h2 id="account">Account & Profile</h2>
            
            <h3 id="create-account">How do I create an account?</h3>
            <p>Click the "Sign Up" button in the top navigation, fill in your email, username, and password, then click "Register". You'll be able to start posting immediately.</p>

            <h3 id="reset-password">I forgot my password. How do I reset it?</h3>
            <p>On the login page, click "Forgot Password". Enter your email address and we'll send you instructions to reset your password.</p>

            <h3 id="update-profile">How do I update my profile information?</h3>
            <p>Log in and go to your account settings. Here you can update your email, username, phone number, and other profile details.</p>

            <h3 id="delete-account">How do I delete my account?</h3>
            <p>Go to account settings and look for the "Delete Account" option at the bottom. Note that this action is permanent and cannot be undone.</p>

            <h2 id="listings">Listings & Posts</h2>
            
            <h3 id="create-listing">How do I create a listing?</h3>
            <p>After logging in and selecting your city, click the "+ Post Ad" button. Fill in the title, description, and other details, then submit.</p>

            <h3 id="upload-images">How do I upload images to my listing?</h3>
            <p>After creating a listing, go to "My Listings" and click "Manage Images". You can upload up to 10 images per listing. Supported formats: JPG, PNG, GIF, WebP. Maximum file size: 5MB per image.</p>

            <h3 id="edit-listing">How do I edit my listing?</h3>
            <p>Go to "My Listings" from the navigation menu. Find the listing you want to edit and click the "Edit" button. Make your changes and save.</p>

            <h3 id="delete-listing">How do I delete a listing?</h3>
            <p>In "My Listings", find the listing and click the "Delete" button. Confirm the deletion. Note: Deleted listings cannot be recovered.</p>

            <h3 id="listing-duration">How long do listings stay active?</h3>
            <p>Regular listings stay active for 30 days. Featured listings duration depends on the package purchased (1, 3, 7, 14, or 30 days).</p>

            <h2 id="messaging">Messaging</h2>
            
            <h3 id="send-message">How do I send a message to someone?</h3>
            <p>Open the listing you're interested in and click the "Send Message" button. This will open a conversation with the poster.</p>

            <h3 id="read-messages">How do I read my messages?</h3>
            <p>Click "Messages" in the navigation menu. You'll see all your conversations. Unread messages are highlighted with a notification badge.</p>

            <h2 id="payments">Payments & Subscriptions</h2>
            
            <h3 id="free-features">What features are free?</h3>
            <p>Free accounts can post up to 3 active listings, browse all categories, search listings, and send messages.</p>

            <h3 id="upgrade-account">How do I upgrade my account?</h3>
            <p>Click "Membership" in the navigation menu, choose a plan that suits your needs, and complete the payment process.</p>

            <h3 id="payment-methods">What payment methods do you accept?</h3>
            <p>We accept all major credit cards (Visa, MasterCard, American Express, Discover) processed securely through Stripe.</p>

            <h3 id="cancel-subscription">How do I cancel my subscription?</h3>
            <p>Go to "Membership" and click "Cancel Subscription". Your subscription will remain active until the end of the current billing period.</p>

            <h3 id="featured-ads">How do featured ads work?</h3>
            <p>Featured ads appear at the top of search results with special highlighting. To feature an ad, go to your listing and click "Feature This Ad", choose a duration, complete payment, and wait for moderator approval (usually within 24 hours).</p>

            <h2 id="safety">Safety & Privacy</h2>
            
            <h3 id="report-listing">How do I report a suspicious listing?</h3>
            <p>Click the "Report" button on the listing, select a reason, and provide details. Our moderation team will review it promptly.</p>

            <h3 id="stay-safe">How can I stay safe?</h3>
            <p>Please read our comprehensive <a href="safety.php">Safety Tips</a> page. Always meet in public places, tell someone where you're going, and trust your instincts.</p>

            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <p style="text-align: center;">
                    <strong>Can't find what you're looking for?</strong><br>
                    <a href="contact.php" class="btn-primary" style="margin-top: 1rem;">Contact Support</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>