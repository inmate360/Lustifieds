<?php
session_start();
require_once 'config/database.php';
require_once 'classes/UserProfile.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$userProfile = new UserProfile($db);

$error = '';
$success = '';

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_photos'])) {
    if (isset($_POST['photos_data']) && is_array($_POST['photos_data'])) {
        $uploaded_count = 0;
        $failed_count = 0;
        
        foreach ($_POST['photos_data'] as $photo_data) {
            $result = $userProfile->uploadGalleryPhoto($_SESSION['user_id'], $photo_data);
            if ($result === true) {
                $uploaded_count++;
            } else {
                $failed_count++;
            }
        }
        
        if ($uploaded_count > 0) {
            $success = "Successfully uploaded {$uploaded_count} photo(s)";
        }
        if ($failed_count > 0) {
            $error = "Failed to upload {$failed_count} photo(s)";
        }
    } else {
        $error = 'No photos to upload';
    }
}

// Handle photo deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_photo'])) {
    $photo_id = $_POST['photo_id'];
    if ($userProfile->deleteGalleryPhoto($_SESSION['user_id'], $photo_id)) {
        $success = 'Photo deleted successfully';
    } else {
        $error = 'Failed to delete photo';
    }
}

// Handle set primary photo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_primary'])) {
    $photo_id = $_POST['photo_id'];
    if ($userProfile->setPrimaryPhoto($_SESSION['user_id'], $photo_id)) {
        $success = 'Primary photo updated';
    } else {
        $error = 'Failed to update primary photo';
    }
}

// Get user's existing photos
$query = "SELECT * FROM user_photos WHERE user_id = :user_id ORDER BY is_primary DESC, display_order ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$existing_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'views/header.php';
?>

<!-- Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<!-- Existing Theme Styles -->
<link rel="stylesheet" href="/assets/css/dark-blue-theme.css">
<link rel="stylesheet" href="/assets/css/creator-cards.css">
<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
.upload-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.upload-card {
    background: var(--card-bg);
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
    margin-bottom: 2rem;
    border: 2px solid var(--border-color);
}

.card-header {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    padding: 2rem;
    color: white;
}

.card-header h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-body {
    padding: 2rem;
}

.upload-zone {
    border: 3px dashed var(--border-color);
    border-radius: 20px;
    padding: 4rem 2rem;
    text-align: center;
    transition: all 0.3s;
    background: rgba(26, 31, 46, 0.3);
    cursor: pointer;
    margin-bottom: 2rem;
}

.upload-zone:hover {
    border-color: var(--primary-blue);
    background: rgba(66,103,245,0.1);
    transform: translateY(-2px);
}

.upload-zone.dragover {
    border-color: var(--success-green);
    background: rgba(52,211,153,0.1);
    border-style: solid;
}

.upload-zone-icon {
    font-size: 5rem;
    color: var(--primary-blue);
    margin-bottom: 1rem;
}

.upload-zone-text {
    color: var(--text-white);
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.upload-zone-subtext {
    color: var(--text-gray);
    font-size: 1rem;
}

.preview-section {
    margin: 2rem 0;
}

.preview-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.preview-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 16px;
    overflow: hidden;
    background: rgba(26, 31, 46, 0.5);
    border: 2px solid var(--border-color);
    transition: all 0.3s;
}

.preview-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.4);
    border-color: var(--primary-blue);
}

.preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.preview-actions {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s;
}

.preview-item:hover .preview-actions {
    opacity: 1;
}

.preview-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1.2rem;
}

.preview-btn-crop {
    background: var(--primary-blue);
    color: white;
}

.preview-btn-crop:hover {
    background: var(--secondary-blue);
    transform: scale(1.1);
}

.preview-btn-delete {
    background: var(--danger-red);
    color: white;
}

.preview-btn-delete:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.primary-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: var(--success-green);
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    z-index: 2;
}

.upload-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

/* Crop Modal */
.crop-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    animation: fadeIn 0.3s;
}

.crop-modal-content {
    background: var(--card-bg);
    margin: 2% auto;
    padding: 2rem;
    border-radius: 20px;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 80px rgba(0,0,0,0.5);
}

.crop-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.crop-modal-header h3 {
    color: var(--text-white);
    margin: 0;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.close-modal {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-gray);
    cursor: pointer;
    transition: color 0.3s;
    line-height: 1;
}

.close-modal:hover {
    color: var(--danger-red);
}

.crop-container {
    max-width: 100%;
    max-height: 500px;
    margin: 1.5rem 0;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
}

#cropImage {
    max-width: 100%;
    display: block;
}

.crop-controls {
    display: flex;
    gap: 1rem;
    margin: 1.5rem 0;
    flex-wrap: wrap;
    justify-content: center;
}

.crop-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.crop-btn-primary {
    background: var(--primary-blue);
    color: white;
}

.crop-btn-primary:hover {
    background: var(--secondary-blue);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(66,103,245,0.3);
}

.crop-btn-secondary {
    background: rgba(255,255,255,0.1);
    color: var(--text-white);
}

.crop-btn-secondary:hover {
    background: rgba(255,255,255,0.2);
}

.photo-count {
    background: rgba(66,103,245,0.2);
    border: 2px solid var(--primary-blue);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 2rem;
    color: var(--text-white);
    text-align: center;
    font-weight: 600;
}

@media (max-width: 768px) {
    .preview-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .upload-zone {
        padding: 3rem 1.5rem;
    }
    
    .crop-modal-content {
        margin: 5% 1rem;
        padding: 1.5rem;
    }
}
</style>

<div class="page-content">
    <div class="upload-container">
        <div class="upload-card">
            <div class="card-header">
                <h1>
                    <i class="bi bi-images"></i>
                    Upload Photos
                </h1>
            </div>
            
            <div class="card-body">
                <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <div class="photo-count">
                    <i class="bi bi-collection"></i> 
                    You have <strong><?php echo count($existing_photos); ?></strong> photo(s) in your gallery
                </div>
                
                <!-- Upload Zone -->
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                    <div class="upload-zone-icon">
                        <i class="bi bi-cloud-arrow-up-fill"></i>
                    </div>
                    <div class="upload-zone-text">
                        Drag & Drop your photos here
                    </div>
                    <div class="upload-zone-subtext">
                        or click to browse • JPEG, PNG • Max 5MB each • Up to 20 photos
                    </div>
                </div>
                
                <input type="file" 
                       id="fileInput" 
                       accept="image/jpeg,image/png,image/jpg" 
                       multiple 
                       style="display:none">
                
                <!-- Preview Section -->
                <div class="preview-section" id="previewSection" style="display:none">
                    <h2 class="preview-title">
                        <i class="bi bi-eye-fill"></i>
                        Selected Photos (<span id="photoCount">0</span>)
                    </h2>
                    <div class="preview-grid" id="previewGrid"></div>
                    
                    <div class="upload-actions">
                        <button type="button" class="btn-primary" onclick="uploadAllPhotos()">
                            <i class="bi bi-cloud-upload-fill"></i> Upload All Photos
                        </button>
                        <button type="button" class="btn-secondary" onclick="clearAllPreviews()">
                            <i class="bi bi-trash"></i> Clear All
                        </button>
                    </div>
                </div>
                
                <!-- Existing Photos -->
                <?php if(count($existing_photos) > 0): ?>
                <div class="preview-section" style="margin-top: 3rem;">
                    <h2 class="preview-title">
                        <i class="bi bi-folder2-open"></i>
                        Your Gallery
                    </h2>
                    <div class="preview-grid">
                        <?php foreach($existing_photos as $photo): ?>
                        <div class="preview-item">
                            <?php if($photo['is_primary']): ?>
                            <div class="primary-badge">
                                <i class="bi bi-star-fill"></i> Primary
                            </div>
                            <?php endif; ?>
                            
                            <img src="<?php echo htmlspecialchars($photo['file_path']); ?>" 
                                 alt="Photo">
                            
                            <div class="preview-actions">
                                <?php if(!$photo['is_primary']): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="photo_id" value="<?php echo $photo['id']; ?>">
                                    <button type="submit" name="set_primary" class="preview-btn preview-btn-crop" title="Set as Primary">
                                        <i class="bi bi-star"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this photo?')">
                                    <input type="hidden" name="photo_id" value="<?php echo $photo['id']; ?>">
                                    <button type="submit" name="delete_photo" class="preview-btn preview-btn-delete" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Crop Modal -->
<div id="cropModal" class="crop-modal">
    <div class="crop-modal-content">
        <div class="crop-modal-header">
            <h3><i class="bi bi-crop"></i> Crop Photo</h3>
            <span class="close-modal" onclick="closeCropModal()">&times;</span>
        </div>
        
        <div class="crop-container">
            <img id="cropImage" src="" alt="Crop">
        </div>
        
        <div class="crop-controls">
            <button type="button" class="crop-btn crop-btn-secondary" onclick="cropper.rotate(-90)">
                <i class="bi bi-arrow-counterclockwise"></i> Rotate Left
            </button>
            <button type="button" class="crop-btn crop-btn-secondary" onclick="cropper.rotate(90)">
                <i class="bi bi-arrow-clockwise"></i> Rotate Right
            </button>
            <button type="button" class="crop-btn crop-btn-secondary" onclick="cropper.reset()">
                <i class="bi bi-arrow-repeat"></i> Reset
            </button>
            <button type="button" class="crop-btn crop-btn-secondary" onclick="setAspectRatio(1)">
                <i class="bi bi-square"></i> Square
            </button>
            <button type="button" class="crop-btn crop-btn-secondary" onclick="setAspectRatio(4/3)">
                <i class="bi bi-aspect-ratio"></i> 4:3
            </button>
            <button type="button" class="crop-btn crop-btn-secondary" onclick="setAspectRatio(16/9)">
                <i class="bi bi-aspect-ratio-fill"></i> 16:9
            </button>
        </div>
        
        <div class="crop-controls">
            <button type="button" class="crop-btn crop-btn-primary" onclick="applyCrop()">
                <i class="bi bi-check-lg"></i> Apply Crop
            </button>
            <button type="button" class="crop-btn crop-btn-secondary" onclick="closeCropModal()">
                <i class="bi bi-x-lg"></i> Cancel
            </button>
        </div>
    </div>
</div>

<!-- Hidden form for upload -->
<form method="POST" id="uploadForm" style="display:none">
    <input type="hidden" name="upload_photos" value="1">
    <div id="photosDataContainer"></div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
let selectedFiles = [];
let cropper = null;
let currentCropIndex = -1;

const fileInput = document.getElementById('fileInput');
const uploadZone = document.getElementById('uploadZone');
const previewSection = document.getElementById('previewSection');
const previewGrid = document.getElementById('previewGrid');
const photoCount = document.getElementById('photoCount');

// File input change
fileInput.addEventListener('change', (e) => {
    handleFiles(Array.from(e.target.files));
});

// Drag and drop
uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    
    const files = Array.from(e.dataTransfer.files).filter(file => 
        file.type.match('image/(jpeg|png|jpg)')
    );
    
    handleFiles(files);
});

function handleFiles(files) {
    // Limit to 20 photos total
    const remainingSlots = 20 - selectedFiles.length;
    if (files.length > remainingSlots) {
        alert(`You can only upload ${remainingSlots} more photo(s). Maximum 20 photos.`);
        files = files.slice(0, remainingSlots);
    }
    
    files.forEach(file => {
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert(`${file.name} exceeds 5MB limit`);
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (e) => {
            selectedFiles.push({
                file: file,
                dataUrl: e.target.result,
                croppedDataUrl: null
            });
            updatePreview();
        };
        reader.readAsDataURL(file);
    });
}

function updatePreview() {
    if (selectedFiles.length === 0) {
        previewSection.style.display = 'none';
        return;
    }
    
    previewSection.style.display = 'block';
    photoCount.textContent = selectedFiles.length;
    previewGrid.innerHTML = '';
    
    selectedFiles.forEach((fileData, index) => {
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item';
        
        const img = document.createElement('img');
        img.src = fileData.croppedDataUrl || fileData.dataUrl;
        
        const actions = document.createElement('div');
        actions.className = 'preview-actions';
        
        const cropBtn = document.createElement('button');
        cropBtn.className = 'preview-btn preview-btn-crop';
        cropBtn.innerHTML = '<i class="bi bi-crop"></i>';
        cropBtn.title = 'Crop Photo';
        cropBtn.onclick = () => openCropModal(index);
        
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'preview-btn preview-btn-delete';
        deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
        deleteBtn.title = 'Remove';
        deleteBtn.onclick = () => removePhoto(index);
        
        actions.appendChild(cropBtn);
        actions.appendChild(deleteBtn);
        
        previewItem.appendChild(img);
        previewItem.appendChild(actions);
        previewGrid.appendChild(previewItem);
    });
}

function removePhoto(index) {
    selectedFiles.splice(index, 1);
    updatePreview();
}

function clearAllPreviews() {
    if (confirm('Clear all selected photos?')) {
        selectedFiles = [];
        fileInput.value = '';
        updatePreview();
    }
}

function openCropModal(index) {
    currentCropIndex = index;
    const fileData = selectedFiles[index];
    
    const cropImage = document.getElementById('cropImage');
    cropImage.src = fileData.dataUrl;
    
    const cropModal = document.getElementById('cropModal');
    cropModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
        if (cropper) {
            cropper.destroy();
        }
        
        cropper = new Cropper(cropImage, {
            viewMode: 2,
            dragMode: 'move',
            aspectRatio: NaN,
            autoCropArea: 1,
            restore: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false
        });
    }, 100);
}

function closeCropModal() {
    const cropModal = document.getElementById('cropModal');
    cropModal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    
    currentCropIndex = -1;
}

function setAspectRatio(ratio) {
    if (cropper) {
        cropper.setAspectRatio(ratio);
    }
}

function applyCrop() {
    if (!cropper || currentCropIndex === -1) return;
    
    const canvas = cropper.getCroppedCanvas({
        maxWidth: 1200,
        maxHeight: 1200,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });
    
    canvas.toBlob((blob) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            selectedFiles[currentCropIndex].croppedDataUrl = reader.result;
            updatePreview();
            closeCropModal();
        };
        reader.readAsDataURL(blob);
    }, 'image/jpeg', 0.9);
}

function uploadAllPhotos() {
    if (selectedFiles.length === 0) {
        alert('No photos to upload');
        return;
    }
    
    const container = document.getElementById('photosDataContainer');
    container.innerHTML = '';
    
    selectedFiles.forEach((fileData, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `photos_data[${index}]`;
        input.value = fileData.croppedDataUrl || fileData.dataUrl;
        container.appendChild(input);
    });
    
    document.getElementById('uploadForm').submit();
}

// Close modal on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('cropModal').style.display === 'block') {
        closeCropModal();
    }
});
</script>

<?php include 'views/footer.php'; ?>
