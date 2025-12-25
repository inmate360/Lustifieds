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

$thread_id = (int)($_GET['thread'] ?? 0);

if(!$thread_id) {
    header('Location: messages-inbox.php');
    exit();
}

$thread = $pm->getThread($thread_id, $_SESSION['user_id']);

if(!$thread) {
    header('Location: messages-inbox.php');
    exit();
}

// Determine other user
$other_user = ($thread['starter_id'] == $_SESSION['user_id']) 
    ? ['id' => $thread['recipient_id'], 'username' => $thread['recipient_name']] 
    : ['id' => $thread['starter_id'], 'username' => $thread['starter_name']];

// Mark as read
$pm->markAsRead($thread_id, $_SESSION['user_id']);

// Handle reply
$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply'])) {
    $message = trim($_POST['message']);
    
    if(empty($message)) {
        $error = 'Message cannot be empty';
    } else {
        $result = $pm->sendReply($thread_id, $_SESSION['user_id'], $message);
        
        if($result['success']) {
            header('Location: messages-view.php?thread=' . $thread_id . '&sent=1');
            exit();
        } else {
            $error = $result['error'];
        }
    }
}

include 'views/header.php';
?>

<div class="mx-auto max-w-5xl px-4 py-8">
  
  <!-- Header -->
  <div class="mb-6">
    <div class="mb-4 flex items-center gap-3">
      <a href="messages-inbox.php" 
         class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gh-border bg-gh-panel transition-colors hover:bg-white/5">
        <i class="bi bi-arrow-left"></i>
      </a>
      <div class="flex-1">
        <h1 class="text-2xl font-extrabold tracking-tight">
          <?php echo htmlspecialchars($thread['subject']); ?>
        </h1>
        <p class="mt-1 text-sm text-gh-muted">
          Conversation with <span class="font-semibold text-gh-fg"><?php echo htmlspecialchars($other_user['username']); ?></span>
        </p>
      </div>
      <a href="messages-compose.php?to=<?php echo $other_user['id']; ?>" 
         class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
        <i class="bi bi-pencil"></i>
        New Thread
      </a>
    </div>
  </div>

  <!-- Success Message -->
  <?php if(isset($_GET['sent'])): ?>
    <div class="mb-6 rounded-lg border border-gh-border bg-gh-panel p-4">
      <div class="flex items-start gap-3">
        <i class="bi bi-check-circle-fill text-lg text-gh-success"></i>
        <div class="flex-1 text-sm">
          <span class="font-semibold text-gh-success">Message sent!</span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Error Message -->
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

  <!-- Messages Thread -->
  <div class="mb-6 space-y-4">
    <?php foreach($thread['messages'] as $msg): ?>
      <?php $is_mine = ($msg['sender_id'] == $_SESSION['user_id']); ?>
      
      <div class="flex gap-4 <?php echo $is_mine ? 'flex-row-reverse' : ''; ?>">
        <!-- Avatar -->
        <div class="flex-shrink-0">
          <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 <?php echo $is_mine ? 'border-gh-accent bg-gh-accent/10' : 'border-gh-border bg-gh-panel2'; ?> text-sm font-bold">
            <?php echo strtoupper(substr($msg['sender_name'], 0, 1)); ?>
          </div>
        </div>

        <!-- Message Bubble -->
        <div class="max-w-[70%]">
          <div class="mb-1 flex items-center gap-2 <?php echo $is_mine ? 'flex-row-reverse' : ''; ?>">
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
            <span class="text-xs text-gh-muted">
              <?php echo date('M j, Y \a\t g:i A', strtotime($msg['created_at'])); ?>
            </span>
          </div>
          
          <div class="<?php echo $is_mine ? 'bg-gh-accent text-white' : 'border border-gh-border bg-gh-panel'; ?> rounded-2xl px-4 py-3">
            <p class="text-sm leading-relaxed whitespace-pre-wrap">
              <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
            </p>
            
            <?php if(!empty($msg['attachment_url'])): ?>
              <a href="<?php echo htmlspecialchars($msg['attachment_url']); ?>" 
                 target="_blank"
                 class="mt-3 inline-flex items-center gap-2 rounded-lg border <?php echo $is_mine ? 'border-white/20 bg-white/10 hover:bg-white/20' : 'border-gh-border bg-gh-panel2 hover:bg-white/5'; ?> px-3 py-2 text-sm font-semibold transition-colors">
                <i class="bi bi-paperclip"></i>
                <span>View Attachment</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Reply Form -->
  <div class="rounded-xl border border-gh-border bg-gh-panel shadow-lg">
    <form method="POST" class="p-6">
      <label class="mb-3 block text-sm font-bold">Reply to this thread</label>
      <textarea name="message" 
                rows="4" 
                required 
                placeholder="Type your reply..."
                class="w-full rounded-lg border border-gh-border bg-gh-panel2 px-4 py-3 text-gh-fg placeholder-gh-muted transition-all focus:border-gh-accent focus:outline-none focus:ring-2 focus:ring-gh-accent/50"></textarea>
      
      <div class="mt-4 flex items-center justify-between">
        <span class="text-xs text-gh-muted">Press Ctrl+Enter to send quickly</span>
        <button type="submit" 
                name="reply"
                class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
          <i class="bi bi-send-fill"></i>
          Send Reply
        </button>
      </div>
    </form>
  </div>

</div>

<script>
// Ctrl+Enter to submit
document.querySelector('textarea[name="message"]').addEventListener('keydown', function(e) {
  if((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
    this.form.submit();
  }
});

// Auto-scroll to bottom
window.addEventListener('load', function() {
  window.scrollTo(0, document.body.scrollHeight);
});
</script>

<?php include 'views/footer.php'; ?>
