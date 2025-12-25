<?php
session_start();
require_once 'config/database.php';
require_once 'classes/ContactForm.php';

$database = new Database();
$db = $database->getConnection();

$contact = new ContactForm($db);
$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if(empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        $result = $contact->submit($name, $email, $subject, $message);
        if($result['success']) {
            $success = 'Your message has been sent! We\'ll get back to you soon.';
        } else {
            $error = $result['error'];
        }
    }
}

include 'views/header.php';
?>

<div class="mx-auto max-w-4xl px-4 py-12">
  
  <!-- Header -->
  <div class="mb-8 text-center">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl border-2 border-gh-border bg-gradient-to-br from-gh-panel to-gh-panel2 text-2xl">
      <i class="bi bi-envelope-fill text-gh-accent"></i>
    </div>
    <h1 class="text-4xl font-extrabold tracking-tight">Contact Us</h1>
    <p class="mt-3 text-gh-muted">Have a question or feedback? We'd love to hear from you!</p>
  </div>

  <div class="grid gap-6 lg:grid-cols-3">
    
    <!-- Contact Info -->
    <div class="space-y-4">
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gh-accent/10 text-gh-accent">
          <i class="bi bi-chat-dots-fill text-xl"></i>
        </div>
        <h3 class="mb-2 font-bold">Live Chat</h3>
        <p class="text-sm text-gh-muted">Chat with our support team in real-time</p>
      </div>

      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gh-success/10 text-gh-success">
          <i class="bi bi-envelope-fill text-xl"></i>
        </div>
        <h3 class="mb-2 font-bold">Email</h3>
        <p class="text-sm text-gh-muted">support@turnpage.com</p>
      </div>

      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gh-warning/10 text-gh-warning">
          <i class="bi bi-clock-fill text-xl"></i>
        </div>
        <h3 class="mb-2 font-bold">Response Time</h3>
        <p class="text-sm text-gh-muted">Usually within 24 hours</p>
      </div>
    </div>

    <!-- Contact Form -->
    <div class="lg:col-span-2">
      <?php if(!empty($success)): ?>
        <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
          <div class="flex items-start gap-3">
            <i class="bi bi-check-circle-fill text-lg text-gh-success"></i>
            <div class="flex-1 text-sm">
              <span class="font-semibold text-gh-success">Success!</span>
              <span class="text-gh-fg"> <?php echo htmlspecialchars($success); ?></span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if(!empty($error)): ?>
        <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
          <div class="flex items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill text-lg text-gh-danger"></i>
            <div class="flex-1 text-sm">
              <span class="font-semibold text-gh-danger">Error:</span>
              <span class="text-gh-fg"> <?php echo htmlspecialchars($error); ?></span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" class="rounded-xl border border-gh-border bg-gh-panel shadow-lg">
        <div class="space-y-6 p-6">
          
          <div class="grid gap-6 sm:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">
                Name <span class="text-gh-danger">*</span>
              </label>
              <input type="text" 
                     name="name" 
                     required 
                     placeholder="Your name"
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold text-gh-fg">
                Email <span class="text-gh-danger">*</span>
              </label>
              <input type="email" 
                     name="email" 
                     required 
                     placeholder="your@email.com"
                     class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
            </div>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-gh-fg">
              Subject <span class="text-gh-danger">*</span>
            </label>
            <input type="text" 
                   name="subject" 
                   required 
                   placeholder="What's this about?"
                   class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-gh-fg">
              Message <span class="text-gh-danger">*</span>
            </label>
            <textarea name="message" 
                      rows="6" 
                      required 
                      placeholder="Tell us more..."
                      class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50"></textarea>
          </div>

        </div>

        <div class="border-t border-gh-border bg-gh-panel2 px-6 py-4">
          <button type="submit" 
                  class="w-full rounded-lg bg-gh-accent px-6 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
            <i class="bi bi-send-fill mr-2"></i>
            Send Message
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'views/footer.php'; ?>
