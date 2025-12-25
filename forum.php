<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';

$database = new Database();
$db = $database->getConnection();

$forum = new Forum($db);
$categories = $forum->getCategories();

// Get recent threads across all categories
$query = "SELECT t.*, u.username, c.name as category_name, c.slug as category_slug, c.color 
          FROM forum_threads t 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN forum_categories c ON t.category_id = c.id 
          WHERE t.is_deleted = FALSE 
          ORDER BY t.created_at DESC LIMIT 10";
$stmt = $db->query($query);
$recent_threads = $stmt->fetchAll();

// Get forum stats
$query = "SELECT 
            (SELECT COUNT(*) FROM forum_threads WHERE is_deleted = FALSE) as total_threads,
            (SELECT COUNT(*) FROM forum_posts WHERE is_deleted = FALSE) as total_posts,
            (SELECT COUNT(DISTINCT user_id) FROM forum_threads) as total_members";
$stmt = $db->query($query);
$stats = $stmt->fetch();

include 'views/header.php';
?>

<div class="mx-auto max-w-7xl px-4 py-8">
  
  <!-- Header -->
  <div class="mb-8 text-center">
    <h1 class="mb-3 flex items-center justify-center gap-3 text-4xl font-extrabold tracking-tight">
      <i class="bi bi-chat-square-dots-fill text-gh-accent"></i>
      Community Forum
    </h1>
    <p class="text-gh-muted">Connect, discuss, and share with the Turnpage community</p>
  </div>

  <!-- Stats -->
  <div class="mb-8 grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-gh-border bg-gh-panel p-6 text-center">
      <div class="mb-2 text-3xl font-black text-gh-accent"><?php echo number_format($stats['total_threads']); ?></div>
      <div class="text-sm font-semibold text-gh-muted">Discussions</div>
    </div>
    <div class="rounded-xl border border-gh-border bg-gh-panel p-6 text-center">
      <div class="mb-2 text-3xl font-black text-gh-success"><?php echo number_format($stats['total_posts']); ?></div>
      <div class="text-sm font-semibold text-gh-muted">Messages</div>
    </div>
    <div class="rounded-xl border border-gh-border bg-gh-panel p-6 text-center">
      <div class="mb-2 text-3xl font-black text-gh-warning"><?php echo number_format($stats['total_members']); ?></div>
      <div class="text-sm font-semibold text-gh-muted">Members</div>
    </div>
  </div>

  <div class="grid gap-6 lg:grid-cols-4">
    
    <!-- Categories Sidebar -->
    <div class="lg:col-span-1">
      <div class="rounded-xl border border-gh-border bg-gh-panel p-6">
        <h3 class="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-gh-muted">
          <i class="bi bi-list-ul"></i>
          Categories
        </h3>
        <nav class="space-y-2">
          <?php foreach($categories as $cat): ?>
            <a href="forum-category.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>" 
               class="group flex items-center gap-3 rounded-lg border border-gh-border bg-gh-panel2 p-3 transition-colors hover:bg-white/5">
              <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg" 
                   style="background: <?php echo htmlspecialchars($cat['color']); ?>20; color: <?php echo htmlspecialchars($cat['color']); ?>">
                <i class="bi bi-<?php echo htmlspecialchars($cat['icon'] ?? 'chat'); ?>"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm line-clamp-1"><?php echo htmlspecialchars($cat['name']); ?></div>
                <div class="text-xs text-gh-muted"><?php echo $cat['thread_count']; ?> threads</div>
              </div>
              <i class="bi bi-chevron-right text-gh-muted transition-transform group-hover:translate-x-1"></i>
            </a>
          <?php endforeach; ?>
        </nav>

        <?php if(isset($_SESSION['user_id'])): ?>
          <a href="forum-new-thread.php" 
             class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-gh-accent px-4 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
            <i class="bi bi-plus-circle-fill"></i>
            New Thread
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Threads -->
    <div class="lg:col-span-3">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-xl font-bold">
          <i class="bi bi-fire text-gh-warning"></i>
          Recent Discussions
        </h2>
        <a href="forum-all.php" 
           class="text-sm font-semibold text-gh-accent hover:underline">
          View All →
        </a>
      </div>

      <?php if(empty($recent_threads)): ?>
        <div class="rounded-xl border border-gh-border bg-gh-panel p-12 text-center">
          <i class="bi bi-chat-square-text mb-4 text-5xl text-gh-muted opacity-50"></i>
          <h3 class="mb-2 text-lg font-bold">No Threads Yet</h3>
          <p class="mb-6 text-sm text-gh-muted">Be the first to start a discussion!</p>
          <?php if(isset($_SESSION['user_id'])): ?>
            <a href="forum-new-thread.php" 
               class="inline-flex items-center gap-2 rounded-lg bg-gh-accent px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110">
              <i class="bi bi-plus-circle-fill"></i>
              Start First Thread
            </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach($recent_threads as $thread): ?>
            <a href="forum-thread.php?id=<?php echo $thread['id']; ?>" 
               class="group block rounded-xl border border-gh-border bg-gh-panel transition-colors hover:bg-gh-panel2">
              <div class="flex items-start gap-4 p-4">
                
                <!-- Avatar -->
                <div class="flex-shrink-0">
                  <div class="flex h-12 w-12 items-center justify-center rounded-full border-2 border-gh-border bg-gh-panel2 text-base font-bold">
                    <?php echo strtoupper(substr($thread['username'], 0, 1)); ?>
                  </div>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold" 
                          style="background: <?php echo htmlspecialchars($thread['color']); ?>20; color: <?php echo htmlspecialchars($thread['color']); ?>">
                      <?php echo htmlspecialchars($thread['category_name']); ?>
                    </span>
                    
                    <?php if($thread['is_pinned']): ?>
                      <span class="inline-flex items-center gap-1 rounded-full border border-gh-warning bg-gh-warning/10 px-2.5 py-0.5 text-xs font-bold text-gh-warning">
                        <i class="bi bi-pin-angle-fill"></i> Pinned
                      </span>
                    <?php endif; ?>
                    
                    <?php if($thread['is_locked']): ?>
                      <span class="inline-flex items-center gap-1 rounded-full border border-gh-muted bg-gh-muted/10 px-2.5 py-0.5 text-xs font-bold text-gh-muted">
                        <i class="bi bi-lock-fill"></i> Locked
                      </span>
                    <?php endif; ?>
                  </div>

                  <h3 class="mb-2 font-bold text-gh-fg group-hover:text-gh-accent line-clamp-2">
                    <?php echo htmlspecialchars($thread['title']); ?>
                  </h3>

                  <div class="flex flex-wrap items-center gap-3 text-xs text-gh-muted">
                    <span class="flex items-center gap-1">
                      <i class="bi bi-person-fill"></i>
                      <?php echo htmlspecialchars($thread['username']); ?>
                    </span>
                    <span>•</span>
                    <span class="flex items-center gap-1">
                      <i class="bi bi-clock"></i>
                      <?php echo date('M j, Y', strtotime($thread['created_at'])); ?>
                    </span>
                    <span>•</span>
                    <span class="flex items-center gap-1">
                      <i class="bi bi-chat-fill"></i>
                      <?php echo $thread['reply_count']; ?> replies
                    </span>
                    <span>•</span>
                    <span class="flex items-center gap-1">
                      <i class="bi bi-eye-fill"></i>
                      <?php echo $thread['view_count']; ?> views
                    </span>
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
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'views/footer.php'; ?>
