<?php
session_start();
require_once 'config/database.php';
require_once 'classes/PrivateMessaging.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$pm = new PrivateMessaging($db);

$error = '';
$success = '';
$recipient_id = isset($_GET['to']) ? (int)$_GET['to'] : null;
$recipient_name = '';

// Get recipient info if specified
if($recipient_id) {
    $query = "SELECT username FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $recipient_id);
    $stmt->execute();
    $recipient = $stmt->fetch();
    $recipient_name = $recipient['username'] ?? '';
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recipient_id = (int)$_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if(empty($recipient_id) || empty($subject) || empty($message)) {
        $error = 'All fields are required';
    } else {
        $attachment = !empty($_FILES['attachment']['tmp_name']) ? $_FILES['attachment'] : null;
        $result = $pm->createThread($_SESSION['user_id'], $recipient_id, $subject, $message, $attachment);
        
        if($result['success']) {
            header('Location: messages-view.php?thread=' . $result['thread_id'] . '&sent=1');
            exit();
        } else {
            $error = $result['error'];
        }
    }
}

include 'views/header.php';
?>

<div class="mx-auto max-w-4xl px-4 py-8">
  
  <!-- Header -->
  <div class="mb-6 flex items-center justify-between">
    <div>
      <h1 class="flex items-center gap-3 text-3xl font-extrabold tracking-tight">
        <i class="bi bi-pencil-square text-gh-accent"></i>
        Compose Message
      </h1>
      <p class="mt-2 text-sm text-gh-muted">Send a private message to another user</p>
    </div>
    <a href="messages-inbox.php" 
       class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
      <i class="bi bi-arrow-left"></i> Back to Inbox
    </a>
  </div>

  <!-- Error Alert -->
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

  <!-- Compose Form -->
  <form method="POST" enctype="multipart/form-data" class="rounded-xl border border-gh-border bg-gh-panel shadow-lg">
    <div class="space-y-6 p-6">
      
      <!-- Recipient Field with Autocomplete -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          To <span class="text-gh-danger">*</span>
        </label>
        <div class="relative">
          <input type="text" 
                 id="recipient-search"
                 placeholder="Start typing username..."
                 value="<?php echo htmlspecialchars($recipient_name); ?>"
                 autocomplete="off"
                 class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 pr-10 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" 
                 required />
          <i class="bi bi-search pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gh-muted"></i>
          
          <!-- Autocomplete Dropdown -->
          <div id="user-suggestions" 
               class="absolute z-50 mt-2 hidden w-full overflow-hidden rounded-lg border border-gh-border bg-gh-panel shadow-xl">
            <!-- Results populated via JS -->
          </div>
        </div>
        <input type="hidden" 
               id="recipient-id" 
               name="recipient_id" 
               value="<?php echo $recipient_id ?? ''; ?>" 
               required />
        <p class="mt-1.5 text-xs text-gh-muted">Search for a user by username</p>
      </div>

      <!-- Subject -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          Subject <span class="text-gh-danger">*</span>
        </label>
        <input type="text" 
               name="subject" 
               required 
               maxlength="200"
               placeholder="Enter message subject"
               class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50" />
      </div>

      <!-- Message -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          Message <span class="text-gh-danger">*</span>
        </label>
        <textarea name="message" 
                  rows="10" 
                  required 
                  maxlength="5000"
                  placeholder="Type your message here..."
                  class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50"></textarea>
        <p class="mt-1.5 text-xs text-gh-muted">Maximum 5000 characters</p>
      </div>

      <!-- Attachment (Optional) -->
      <div>
        <label class="mb-2 block text-sm font-semibold text-gh-fg">
          Attachment <span class="text-xs font-normal text-gh-muted">(Optional)</span>
        </label>
        <div class="flex items-center gap-3">
          <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2.5 text-sm font-semibold transition-colors hover:bg-white/5">
            <i class="bi bi-paperclip"></i>
            <span>Choose File</span>
            <input type="file" 
                   name="attachment" 
                   accept="image/*,.pdf,.doc,.docx,.txt"
                   class="hidden" 
                   onchange="updateFileName(this)" />
          </label>
          <span id="file-name" class="text-sm text-gh-muted">No file chosen</span>
        </div>
        <p class="mt-1.5 text-xs text-gh-muted">Images, PDFs, or documents (max 5MB)</p>
      </div>

    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between border-t border-gh-border bg-gh-panel2 px-6 py-4">
      <a href="messages-inbox.php" 
         class="text-sm font-semibold text-gh-muted hover:text-gh-fg hover:underline">
        Cancel
      </a>
      <button type="submit" 
              class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
        <i class="bi bi-send-fill"></i>
        Send Message
      </button>
    </div>
  </form>

</div>

<script>
// File name display
function updateFileName(input) {
  const fileName = input.files[0]?.name || 'No file chosen';
  document.getElementById('file-name').textContent = fileName;
}

// Username Autocomplete
const searchInput = document.getElementById('recipient-search');
const suggestionsDiv = document.getElementById('user-suggestions');
const recipientIdInput = document.getElementById('recipient-id');

let searchTimeout;

searchInput.addEventListener('input', function() {
  const query = this.value.trim();
  
  clearTimeout(searchTimeout);
  
  if(query.length < 2) {
    suggestionsDiv.classList.add('hidden');
    recipientIdInput.value = '';
    return;
  }
  
  searchTimeout = setTimeout(() => {
    fetch(`api/search-users.php?q=${encodeURIComponent(query)}`)
      .then(r => r.json())
      .then(data => {
        if(data.success && data.users.length > 0) {
          displaySuggestions(data.users);
        } else {
          suggestionsDiv.innerHTML = '<div class="p-4 text-center text-sm text-gh-muted">No users found</div>';
          suggestionsDiv.classList.remove('hidden');
        }
      })
      .catch(err => {
        console.error('Search error:', err);
      });
  }, 300);
});

function displaySuggestions(users) {
  suggestionsDiv.innerHTML = users.map(user => `
    <div class="user-suggestion flex cursor-pointer items-center gap-3 border-b border-gh-border p-3 transition-colors hover:bg-white/5 last:border-b-0" 
         data-id="${user.id}" 
         data-username="${user.username}">
      <div class="flex h-10 w-10 items-center justify-center rounded-full border border-gh-border bg-gh-panel2 text-sm font-bold">
        ${user.username.substring(0, 1).toUpperCase()}
      </div>
      <div class="flex-1">
        <div class="font-semibold">${user.username}</div>
        <div class="text-xs text-gh-muted">${user.last_seen || 'New user'}</div>
      </div>
      <i class="bi bi-chevron-right text-gh-muted"></i>
    </div>
  `).join('');
  
  suggestionsDiv.classList.remove('hidden');
  
  // Add click handlers
  document.querySelectorAll('.user-suggestion').forEach(el => {
    el.addEventListener('click', function() {
      const userId = this.dataset.id;
      const username = this.dataset.username;
      
      searchInput.value = username;
      recipientIdInput.value = userId;
      suggestionsDiv.classList.add('hidden');
    });
  });
}

// Close suggestions when clicking outside
document.addEventListener('click', function(e) {
  if(!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
    suggestionsDiv.classList.add('hidden');
  }
});
</script>

<?php include 'views/footer.php'; ?>
