<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Forum.php';
require_once 'includes/profile_required.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Require complete profile
requireCompleteProfile($db, $_SESSION['user_id']);

$forum = new Forum($db);
$categories = $forum->getCategories();

$preselected_category = (int)($_GET['category'] ?? 0);

include 'views/header.php';
?>

<style>
.create-thread-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.create-header {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
}

.create-form {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
}

.form-section {
    margin-bottom: 2rem;
}

.form-section label {
    display: block;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.form-help {
    font-size: 0.85rem;
    color: var(--text-gray);
    margin-top: 0.5rem;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 0.75rem;
}

.category-option {
    position: relative;
}

.category-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.category-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.category-option input:checked + .category-label {
    border-color: var(--primary-blue);
    background: rgba(66, 103, 245, 0.1);
}

.category-label:hover {
    border-color: var(--primary-blue);
}

.category-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: white;
    flex-shrink: 0;
}

.category-name {
    font-weight: 600;
    color: var(--text-white);
}

.title-input {
    width: 100%;
    padding: 1rem;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--background-dark);
    color: var(--text-white);
    font-size: 1.1rem;
    font-weight: 600;
}

.title-input:focus {
    outline: none;
    border-color: var(--primary-blue);
}

.content-editor {
    width: 100%;
    min-height: 300px;
    padding: 1rem;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--background-dark);
    color: var(--text-white);
    font-size: 1rem;
    font-family: inherit;
    line-height: 1.6;
    resize: vertical;
}

.content-editor:focus {
    outline: none;
    border-color: var(--primary-blue);
}

.editor-toolbar {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.editor-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-gray);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.editor-btn:hover {
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.tags-input {
    width: 100%;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--background-dark);
    color: var(--text-white);
}

.tags-input:focus {
    outline: none;
    border-color: var(--primary-blue);
}

.tag-suggestions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    flex-wrap: wrap;
}

.tag-chip {
    padding: 0.5rem 1rem;
    background: rgba(66, 103, 245, 0.1);
    border: 1px solid rgba(66, 103, 245, 0.3);
    border-radius: 20px;
    color: var(--primary-blue);
    cursor: pointer;
    transition: all 0.2s;
}

.tag-chip:hover {
    background: rgba(66, 103, 245, 0.2);
}

.guidelines-box {
    background: rgba(245, 158, 11, 0.1);
    border: 2px solid rgba(245, 158, 11, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.guidelines-box h4 {
    color: var(--warning-orange);
    margin-bottom: 1rem;
}

.guidelines-box ul {
    margin: 0;
    padding-left: 1.5rem;
    line-height: 2;
    color: var(--text-gray);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

.submit-btn {
    padding: 1rem 2rem;
    border-radius: 12px;
    border: none;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.submit-btn.primary {
    background: linear-gradient(135deg, #4267F5, #1D9BF0);
    color: white;
}

.submit-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(66, 103, 245, 0.4);
}

.submit-btn.secondary {
    background: transparent;
    border: 2px solid var(--border-color);
    color: var(--text-gray);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .category-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .submit-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="create-thread-container">
    
    <!-- Header -->
    <div class="create-header">
        <h1><i class="bi bi-plus-circle-fill"></i> Create New Thread</h1>
        <p style="margin: 0.5rem 0 0; opacity: 0.9;">Start a new discussion in the community</p>
    </div>

    <!-- Guidelines -->
    <div class="guidelines-box">
        <h4><i class="bi bi-shield-check"></i> Community Guidelines</h4>
        <ul>
            <li>Be respectful and courteous to all members</li>
            <li>Post in the appropriate category</li>
            <li>Use a clear and descriptive title</li>
            <li>No spam, advertising, or self-promotion</li>
            <li>Keep discussions on-topic and relevant</li>
        </ul>
    </div>

    <!-- Create Form -->
    <div class="create-form">
        <form id="createThreadForm" onsubmit="submitThread(event)">
            
            <!-- Category Selection -->
            <div class="form-section">
                <label>
                    <i class="bi bi-folder-fill"></i> Select Category *
                </label>
                <div class="category-grid">
                    <?php foreach($categories as $category): ?>
                        <?php if(!$category['is_locked']): ?>
                        <div class="category-option">
                            <input type="radio" 
                                   name="category" 
                                   id="cat-<?php echo $category['id']; ?>" 
                                   value="<?php echo $category['id']; ?>"
                                   <?php echo $preselected_category == $category['id'] ? 'checked' : ''; ?>
                                   required>
                            <label for="cat-<?php echo $category['id']; ?>" class="category-label">
                                <div class="category-icon" style="background: <?php echo $category['color']; ?>">
                                    <i class="bi bi-<?php echo $category['icon']; ?>"></i>
                                </div>
                                <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                            </label>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="form-help">
                    <i class="bi bi-info-circle"></i> Choose the most relevant category for your thread
                </div>
            </div>

            <!-- Thread Title -->
            <div class="form-section">
                <label for="threadTitle">
                    <i class="bi bi-type"></i> Thread Title *
                </label>
                <input type="text" 
                       id="threadTitle" 
                       class="title-input" 
                       placeholder="Enter a clear and descriptive title..."
                       maxlength="500"
                       required>
                <div class="form-help">
                    <i class="bi bi-info-circle"></i> Make it descriptive and engaging (max 500 characters)
                </div>
            </div>

            <!-- Content Editor -->
            <div class="form-section">
                <label for="threadContent">
                    <i class="bi bi-file-text"></i> Content *
                </label>
                <div class="editor-toolbar">
                    <button type="button" class="editor-btn" onclick="insertFormatting('**', '**')" title="Bold">
                        <i class="bi bi-type-bold"></i>
                    </button>
                    <button type="button" class="editor-btn" onclick="insertFormatting('*', '*')" title="Italic">
                        <i class="bi bi-type-italic"></i>
                    </button>
                    <button type="button" class="editor-btn" onclick="insertFormatting('\n> ', '')" title="Quote">
                        <i class="bi bi-quote"></i>
                    </button>
                    <button type="button" class="editor-btn" onclick="insertFormatting('\n- ', '')" title="List">
                        <i class="bi bi-list-ul"></i>
                    </button>
                    <button type="button" class="editor-btn" onclick="insertFormatting('[', '](url)')" title="Link">
                        <i class="bi bi-link-45deg"></i>
                    </button>
                </div>
                <textarea id="threadContent" 
                          class="content-editor" 
                          placeholder="Write your post content here... You can use markdown formatting."
                          required></textarea>
                <div class="form-help">
                    <i class="bi bi-info-circle"></i> Provide detailed information and context for your discussion
                </div>
            </div>

            <!-- Tags -->
            <div class="form-section">
                <label for="threadTags">
                    <i class="bi bi-tags"></i> Tags (Optional)
                </label>
                <input type="text" 
                       id="threadTags" 
                       class="tags-input" 
                       placeholder="Add tags separated by commas (e.g., help, advice, question)">
                <div class="tag-suggestions">
                    <span class="tag-chip" onclick="addTag('question')">question</span>
                    <span class="tag-chip" onclick="addTag('help')">help</span>
                    <span class="tag-chip" onclick="addTag('advice')">advice</span>
                    <span class="tag-chip" onclick="addTag('discussion')">discussion</span>
                    <span class="tag-chip" onclick="addTag('tips')">tips</span>
                </div>
                <div class="form-help">
                    <i class="bi bi-info-circle"></i> Tags help others find your thread (max 5 tags)
                </div>
            </div>

            <!-- Submit Actions -->
            <div class="form-actions">
                <button type="button" class="submit-btn secondary" onclick="history.back()">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="submit-btn secondary" onclick="saveDraft()">
                    <i class="bi bi-save"></i> Save Draft
                </button>
                <button type="submit" class="submit-btn primary" id="submitBtn">
                    <i class="bi bi-send-fill"></i> Create Thread
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function insertFormatting(before, after) {
    const textarea = document.getElementById('threadContent');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const newText = before + selectedText + after;
    
    textarea.value = textarea.value.substring(0, start) + newText + textarea.value.substring(end);
    textarea.focus();
    textarea.selectionStart = start + before.length;
    textarea.selectionEnd = start + before.length + selectedText.length;
}

function addTag(tag) {
    const input = document.getElementById('threadTags');
    const current = input.value.trim();
    
    if(current) {
        const tags = current.split(',').map(t => t.trim());
        if(!tags.includes(tag)) {
            input.value = current + ', ' + tag;
        }
    } else {
        input.value = tag;
    }
}

function submitThread(event) {
    event.preventDefault();
    
    const btn = document.getElementById('submitBtn');
    const category = document.querySelector('input[name="category"]:checked');
    const title = document.getElementById('threadTitle').value.trim();
    const content = document.getElementById('threadContent').value.trim();
    const tags = document.getElementById('threadTags').value.trim();
    
    if(!category || !title || !content) {
        alert('Please fill in all required fields');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating Thread...';
    
    const formData = new FormData();
    formData.append('action', 'create_thread');
    formData.append('category_id', category.value);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('tags', tags);
    
    fetch('/api/forum.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Redirect to new thread
            window.location.href = '/forum-thread.php?slug=' + data.slug;
        } else {
            alert(data.error || 'Failed to create thread');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Create Thread';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Create Thread';
    });
}

function saveDraft() {
    const title = document.getElementById('threadTitle').value.trim();
    const content = document.getElementById('threadContent').value.trim();
    
    if(title || content) {
        localStorage.setItem('forum_draft', JSON.stringify({
            title: title,
            content: content,
            timestamp: Date.now()
        }));
        
        alert('Draft saved locally! It will be restored when you return to this page.');
    }
}

// Restore draft on load
window.addEventListener('DOMContentLoaded', function() {
    const draft = localStorage.getItem('forum_draft');
    if(draft) {
        try {
            const data = JSON.parse(draft);
            const age = Date.now() - data.timestamp;
            
            // Only restore if less than 24 hours old
            if(age < 86400000) {
                if(confirm('A draft was found. Would you like to restore it?')) {
                    document.getElementById('threadTitle').value = data.title;
                    document.getElementById('threadContent').value = data.content;
                }
            } else {
                localStorage.removeItem('forum_draft');
            }
        } catch(e) {
            console.error('Error restoring draft:', e);
        }
    }
});

// Auto-save draft every 30 seconds
setInterval(() => {
    const title = document.getElementById('threadTitle').value.trim();
    const content = document.getElementById('threadContent').value.trim();
    
    if(title || content) {
        localStorage.setItem('forum_draft', JSON.stringify({
            title: title,
            content: content,
            timestamp: Date.now()
        }));
    }
}, 30000);
</script>

<?php include 'views/footer.php'; ?>