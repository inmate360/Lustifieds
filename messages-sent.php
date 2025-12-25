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

$page = (int)($_GET['page'] ?? 1);
$threads = $pm->getSentMessages($_SESSION['user_id'], $page, 20);

include 'views/header.php';
?>

<div class="mx-auto max-w-6xl px-4 py-8">
  
  <!-- Header -->
  <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="flex items-center gap-3 text-3xl font-extrabold tracking-tight">
        <i class="bi bi-send-fill text-gh-accent"></i>
        Sent Messages
      </h1>
      <p class="mt-2 text-sm text-gh-muted">Messages you've sent</p>
    </div>
    <a href="messages-compose.php" 
       class="inline-flex items-center justify-center gap-2 rounded-lg bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110">
      <i class="bi bi-pencil-square"></i>
      Compose Message
    </a>
  </div>

  <div class="grid gap-6 lg:grid-cols-4">
    
    <!-- Sidebar -->
    <div class="lg:col-span-1">
      <div class="rounded-xl border border-gh-border bg-gh-panel p-4">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gh-muted">Folders</h3>
        <nav class="space-y-1">
          <a href="messages-inbox.php" 
             class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-gh-fg transition-colors hover:bg-white/5">
            <i class="bi bi-inbox-fill"></i>
            <span>Inbox</span>
          </a>
          <a href="messages-sent.php" 
             class="flex items-center gap-3 rounded-lg bg-gh-accent px-3 py-2 text-sm font-semibold text-white transition-colors">
            <i class="bi bi-send-fill"></i>
            <span>Sent</span>
          </a>
        </nav>
      </div>
    </div>

    <!-- Messages List -->
    <div class="lg:col-span-3">
      <?php if(empty($threads)): ?>
        <!-- Empty State -->
        <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
          <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full border-2 border-gh-border bg-gh-panel2">
            <i class="bi bi-send text-4xl text-gh-muted opacity-50"></i>
          </div>
          <h3 class="text-lg font-bold">No Sent Messages</h3>
          <p class="mt-2 text-sm text-gh-muted">You haven't sent any messages yet.</p>
          <a href="messages-compose.php" 
             class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-pencil-square"></i>
            Send Your First Message
          </a>
        </div>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach($threads as $thread): ?>
            <a href="messages-view.php?thread=<?php echo $thread['id']; ?>" 
               class="group block rounded-xl border border-gh-border bg-gh-panel transition-colors hover:bg-gh-panel2">
              <div class="flex items-start gap-4 p-4">
                
                <!-- Avatar -->
                <div class="flex-shrink-0">
                  <div class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-gh-border bg-gh-panel2 text-base font-bold">
                    <?php echo strtoupper(substr($thread['recipient_name'], 0, 1)); ?>
                  </div>
                </div>

                <!-- Content -->
                <div class="min-w-0 flex-1">
                  <div class="mb-1 flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2">
                      <span class="text-xs text-gh-muted">To:</span>
                      <h3 class="font-bold text-gh-fg group-hover:text-gh-accent">
                        <?php echo htmlspecialchars($thread['recipient_name']); ?>
                      </h3>
                    </div>
                    <span class="text-xs text-gh-muted">
                      <?php echo date('M j, g:i A', strtotime($thread['last_message_at'])); ?>
                    </span>
                  </div>
                  
                  <p class="mb-2 text-sm font-semibold text-gh-fg line-clamp-1">
                    <?php echo htmlspecialchars($thread['subject']); ?>
                  </p>
                  
                  <p class="text-sm text-gh-muted line-clamp-2">
                    <?php echo htmlspecialchars($thread['last_message_preview']); ?>
                  </p>
                  
                  <div class="mt-2 text-xs text-gh-muted">
                    <i class="bi bi-chat-fill"></i>
                    <?php echo $thread['message_count']; ?> message<?php echo $thread['message_count'] != 1 ? 's' : ''; ?>
                  </div>
                </div>

                <!-- Arrow -->
                <div class="flex-shrink-0">
                  <i class="bi bi-chevron-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if(count($threads) >= 20): ?>
          <div class="mt-6 flex items-center justify-center gap-2">
            <?php if($page > 1): ?>
              <a href="?page=<?php echo $page - 1; ?>" 
                 class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
                <i class="bi bi-chevron-left"></i> Previous
              </a>
            <?php endif; ?>
            
            <span class="rounded-lg border border-gh-border bg-gh-panel2 px-4 py-2 text-sm font-semibold">
              Page <?php echo $page; ?>
            </span>
            
            <a href="?page=<?php echo $page + 1; ?>" 
               class="inline-flex items-center gap-2 rounded-lg border border-gh-border bg-gh-panel px-4 py-2 text-sm font-semibold transition-colors hover:bg-white/5">
              Next <i class="bi bi-chevron-right"></i>
            </a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php include 'views/footer.php'; ?>
