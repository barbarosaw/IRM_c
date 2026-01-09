<?php
/**
 * Email Images Gallery View
 */
if (!defined('AW_SYSTEM')) {
    die('Direct access not allowed');
}
?>

<style>
/* Email Images Gallery Styles */
.image-upload-zone {
    border: 3px dashed #dee2e6;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    cursor: pointer;
}

.image-upload-zone:hover,
.image-upload-zone.dragover {
    border-color: #0d6efd;
    background: linear-gradient(135deg, #e7f1ff 0%, #cfe2ff 100%);
}

.image-upload-zone i {
    font-size: 48px;
    color: #6c757d;
    margin-bottom: 15px;
}

.image-upload-zone.dragover i {
    color: #0d6efd;
    transform: scale(1.1);
}

.image-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.image-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    cursor: pointer;
}

.image-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.image-card .image-preview {
    width: 100%;
    height: 150px;
    object-fit: cover;
    background: #f8f9fa;
}

.image-card .image-info {
    padding: 12px;
}

.image-card .image-name {
    font-size: 13px;
    font-weight: 500;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 5px;
}

.image-card .image-meta {
    font-size: 11px;
    color: #6c757d;
}

.image-actions-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-card:hover .image-actions-overlay {
    opacity: 1;
}

.image-card .image-preview-container {
    position: relative;
    height: 150px;
}

/* Modal Image Editor Styles */
.image-editor-canvas {
    max-width: 100%;
    max-height: 400px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

.editor-tools {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.editor-tools .btn {
    display: flex;
    align-items: center;
    gap: 5px;
}

.copy-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.copy-option {
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.copy-option:hover {
    border-color: #0d6efd;
    background: #e7f1ff;
}

.copy-option i {
    font-size: 24px;
    margin-bottom: 8px;
    color: #0d6efd;
}

.copy-option h6 {
    margin-bottom: 5px;
    font-weight: 600;
}

.copy-option p {
    font-size: 12px;
    color: #6c757d;
    margin: 0;
}

.upload-progress {
    display: none;
    margin-top: 20px;
}

.search-box {
    max-width: 400px;
}

/* Toast notification */
.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}

/* Image dimensions badge */
.dimension-badge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
}

/* Size warnings */
.size-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 10px 15px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.size-warning i {
    color: #ffc107;
    font-size: 20px;
}
</style>

<main class="col-md-10 ms-sm-auto px-md-4 py-3">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>modules/email/">Email</a></li>
            <li class="breadcrumb-item active">Images</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-images me-2"></i>Email Images</h2>
            <p class="text-muted mb-0">Upload and manage images for email templates</p>
        </div>
        <div>
            <a href="../headers/" class="btn btn-outline-primary me-2">
                <i class="fas fa-heading me-1"></i>Headers
            </a>
            <a href="../footers/" class="btn btn-outline-primary">
                <i class="fas fa-shoe-prints me-1"></i>Footers
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Upload Zone -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="image-upload-zone" id="uploadZone">
                <i class="fas fa-cloud-upload-alt"></i>
                <h5>Drag & Drop Images Here</h5>
                <p class="text-muted mb-2">or click to browse</p>
                <p class="text-muted small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Allowed formats: JPG, PNG, GIF, WebP | Max size: 750KB
                </p>
                <input type="file" id="fileInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
            </div>
            
            <!-- Upload Progress -->
            <div class="upload-progress" id="uploadProgress">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">
                        <span class="progress-text">Uploading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="d-flex gap-3 align-items-center">
                <div class="input-group search-box">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search images..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
                <span class="text-muted ms-auto">
                    <i class="fas fa-images me-1"></i><?php echo count($images); ?> image(s)
                </span>
            </form>
        </div>
    </div>

    <!-- Image Gallery -->
    <?php if (empty($images)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <?php echo empty($search) ? 'No images uploaded yet. Upload your first image above!' : 'No images found matching your search.'; ?>
        </div>
    <?php else: ?>
        <div class="image-gallery">
            <?php foreach ($images as $image): ?>
                <?php 
                $image_url = $base_url . '/uploads/email_images/' . $image['filename'];
                $relative_url = '/uploads/email_images/' . $image['filename'];
                ?>
                <div class="image-card" data-image='<?php echo json_encode([
                    'id' => $image['id'],
                    'filename' => $image['filename'],
                    'original_name' => $image['original_name'],
                    'url' => $image_url,
                    'relative_url' => $relative_url,
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'size' => $image['file_size'],
                    'mime_type' => $image['mime_type'],
                    'uploaded_by' => $image['uploader_name'],
                    'created_at' => $image['created_at']
                ]); ?>'>
                    <div class="image-preview-container">
                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                             alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                             class="image-preview"
                             loading="lazy">
                        <span class="dimension-badge"><?php echo $image['width']; ?>x<?php echo $image['height']; ?></span>
                        <div class="image-actions-overlay">
                            <button class="btn btn-sm btn-light" onclick="event.stopPropagation(); openImageModal(this.closest('.image-card'))" title="View & Copy">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); openEditModal(this.closest('.image-card'))" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); confirmDelete(<?php echo $image['id']; ?>, '<?php echo addslashes($image['original_name']); ?>')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="image-info">
                        <div class="image-name" title="<?php echo htmlspecialchars($image['original_name']); ?>">
                            <?php echo htmlspecialchars($image['original_name']); ?>
                        </div>
                        <div class="image-meta">
                            <?php echo number_format($image['file_size'] / 1024, 1); ?> KB
                            &bull; <?php echo date('M d, Y', strtotime($image['created_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Image View/Copy Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-image me-2"></i>Image Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <img id="modalImage" src="" alt="" class="img-fluid rounded" style="max-height: 300px;">
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Filename:</strong> <span id="modalFilename"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Dimensions:</strong> <span id="modalDimensions"></span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Size:</strong> <span id="modalSize"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Uploaded:</strong> <span id="modalDate"></span>
                    </div>
                </div>

                <hr>
                
                <h6 class="mb-3"><i class="fas fa-copy me-2"></i>Copy Options</h6>
                <div class="copy-options">
                    <div class="copy-option" onclick="copyToClipboard('url')">
                        <i class="fas fa-link"></i>
                        <h6>Copy URL</h6>
                        <p>Copy the direct image URL</p>
                    </div>
                    <div class="copy-option" onclick="copyToClipboard('img')">
                        <i class="fas fa-code"></i>
                        <h6>Copy IMG Tag</h6>
                        <p>Copy HTML &lt;img&gt; tag</p>
                    </div>

                <div class="mt-3">
                    <label class="form-label"><strong>Direct URL:</strong></label>
                    <div class="input-group">
                        <input type="text" id="imageUrlInput" class="form-control" readonly>
                        <button class="btn btn-outline-primary" onclick="copyToClipboard('url')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Editor Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="editor-tools">
                    <button class="btn btn-outline-primary" onclick="rotateImage(-90)" title="Rotate Left">
                        <i class="fas fa-undo"></i> Rotate Left
                    </button>
                    <button class="btn btn-outline-primary" onclick="rotateImage(90)" title="Rotate Right">
                        <i class="fas fa-redo"></i> Rotate Right
                    </button>
                    <button class="btn btn-outline-primary" onclick="flipImage('horizontal')" title="Flip Horizontal">
                        <i class="fas fa-arrows-alt-h"></i> Flip H
                    </button>
                    <button class="btn btn-outline-primary" onclick="flipImage('vertical')" title="Flip Vertical">
                        <i class="fas fa-arrows-alt-v"></i> Flip V
                    </button>
                    <div class="vr mx-2"></div>
                    <div class="input-group" style="width: 200px;">
                        <span class="input-group-text"><i class="fas fa-expand-arrows-alt"></i></span>
                        <input type="number" id="resizeWidth" class="form-control" placeholder="Width">
                        <span class="input-group-text">x</span>
                        <input type="number" id="resizeHeight" class="form-control" placeholder="Height">
                    </div>
                    <button class="btn btn-outline-primary" onclick="resizeImage()" title="Resize">
                        <i class="fas fa-compress"></i> Resize
                    </button>
                    <button class="btn btn-outline-info" onclick="toggleCrop()" id="cropBtn" title="Crop">
                        <i class="fas fa-crop-alt"></i> Crop
                    </button>
                    <button class="btn btn-outline-secondary" onclick="resetEditor()" title="Reset">
                        <i class="fas fa-sync"></i> Reset
                    </button>
                </div>
                
                <div class="text-center position-relative" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <canvas id="editCanvas" class="image-editor-canvas"></canvas>
                    <div id="cropOverlay" style="display: none; position: absolute; border: 2px dashed #0d6efd; background: rgba(13, 110, 253, 0.1); cursor: move;"></div>
                </div>
                
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Original: <span id="originalDimensions"></span></span>
                        <span class="text-muted ms-3">Current: <span id="currentDimensions"></span></span>
                    </div>
                    <div>
                        <label class="me-2">Quality:</label>
                        <input type="range" id="qualitySlider" min="10" max="100" value="90" style="width: 100px;">
                        <span id="qualityValue">90%</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="saveEditedImage()">
                    <i class="fas fa-save me-1"></i>Save as New
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this image?</p>
                <p class="text-muted" id="deleteImageName"></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone. If this image is used in email templates, it will no longer display.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteForm" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="image_id" id="deleteImageId">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Current image data
let currentImageData = null;
let editCanvas, editCtx;
let originalImage = null;
let currentRotation = 0;
let isFlippedH = false;
let isFlippedV = false;
let isCropping = false;
let cropStart = { x: 0, y: 0 };
let cropEnd = { x: 0, y: 0 };

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Upload zone events
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    
    uploadZone.addEventListener('click', () => fileInput.click());
    
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
        handleFiles(e.dataTransfer.files);
    });
    
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
    
    // Image card click
    document.querySelectorAll('.image-card').forEach(card => {
        card.addEventListener('click', function() {
            openImageModal(this);
        });
    });
    
    // Initialize canvas
    editCanvas = document.getElementById('editCanvas');
    editCtx = editCanvas.getContext('2d');
    
    // Quality slider
    document.getElementById('qualitySlider').addEventListener('input', function() {
        document.getElementById('qualityValue').textContent = this.value + '%';
    });
});

// Handle file uploads
function handleFiles(files) {
    const validFiles = Array.from(files).filter(file => {
        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
            showToast('Invalid file type: ' + file.name, 'danger');
            return false;
        }
        if (file.size > 750 * 1024) {
            showToast('File too large (max 750KB): ' + file.name, 'danger');
            return false;
        }
        return true;
    });
    
    if (validFiles.length === 0) return;
    
    const progressDiv = document.getElementById('uploadProgress');
    const progressBar = progressDiv.querySelector('.progress-bar');
    const progressText = progressDiv.querySelector('.progress-text');
    
    progressDiv.style.display = 'block';
    
    let uploaded = 0;
    const total = validFiles.length;
    
    validFiles.forEach((file, index) => {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');
        
        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            uploaded++;
            const percent = Math.round((uploaded / total) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = `Uploaded ${uploaded}/${total}`;
            
            if (data.success) {
                showToast('Uploaded: ' + file.name, 'success');
            } else {
                showToast('Failed: ' + data.message, 'danger');
            }
            
            if (uploaded === total) {
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        })
        .catch(error => {
            uploaded++;
            showToast('Error uploading: ' + file.name, 'danger');
            
            if (uploaded === total) {
                setTimeout(() => {
                    progressDiv.style.display = 'none';
                }, 2000);
            }
        });
    });
}

// Open image modal
function openImageModal(card) {
    currentImageData = JSON.parse(card.dataset.image);
    
    document.getElementById('modalImage').src = currentImageData.url;
    document.getElementById('modalFilename').textContent = currentImageData.original_name;
    document.getElementById('modalDimensions').textContent = currentImageData.width + ' x ' + currentImageData.height + ' px';
    document.getElementById('modalSize').textContent = (currentImageData.size / 1024).toFixed(1) + ' KB';
    document.getElementById('modalDate').textContent = currentImageData.created_at;
    document.getElementById('imageUrlInput').value = currentImageData.url;
    
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}

// Copy to clipboard
async function copyToClipboard(type) {
    try {
        let textToCopy = '';
        
        switch (type) {
            case 'url':
                textToCopy = currentImageData.url;
                await navigator.clipboard.writeText(textToCopy);
                showToast('URL copied to clipboard!', 'success');
                break;
                
            case 'img':
                textToCopy = `<img src="${currentImageData.url}" alt="${currentImageData.original_name}" width="${currentImageData.width}" height="${currentImageData.height}">`;
                await navigator.clipboard.writeText(textToCopy);
                showToast('IMG tag copied to clipboard!', 'success');
                break;
                
        }
    } catch (error) {
        console.error('Copy failed:', error);
        showToast('Failed to copy. Please try again.', 'error');
    }
}

// Open edit modal
function openEditModal(card) {
    currentImageData = JSON.parse(card.dataset.image);
    
    // Reset editor state
    currentRotation = 0;
    isFlippedH = false;
    isFlippedV = false;
    isCropping = false;
    document.getElementById('cropBtn').classList.remove('active');
    document.getElementById('cropOverlay').style.display = 'none';
    
    // Load image
    originalImage = new Image();
    originalImage.crossOrigin = 'anonymous';
    originalImage.onload = function() {
        document.getElementById('originalDimensions').textContent = originalImage.width + ' x ' + originalImage.height;
        document.getElementById('resizeWidth').value = originalImage.width;
        document.getElementById('resizeHeight').value = originalImage.height;
        drawImage();
    };
    originalImage.src = currentImageData.url;
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Draw image on canvas
function drawImage() {
    if (!originalImage) return;
    
    const maxWidth = 700;
    const maxHeight = 400;
    
    let width = originalImage.width;
    let height = originalImage.height;
    
    // Apply rotation dimensions
    if (currentRotation === 90 || currentRotation === 270 || currentRotation === -90) {
        [width, height] = [height, width];
    }
    
    // Scale down if needed
    const scale = Math.min(1, maxWidth / width, maxHeight / height);
    width *= scale;
    height *= scale;
    
    editCanvas.width = width;
    editCanvas.height = height;
    
    editCtx.save();
    editCtx.translate(width / 2, height / 2);
    editCtx.rotate(currentRotation * Math.PI / 180);
    
    if (isFlippedH) editCtx.scale(-1, 1);
    if (isFlippedV) editCtx.scale(1, -1);
    
    const drawWidth = currentRotation === 90 || currentRotation === 270 || currentRotation === -90 ? height : width;
    const drawHeight = currentRotation === 90 || currentRotation === 270 || currentRotation === -90 ? width : height;
    
    editCtx.drawImage(originalImage, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
    editCtx.restore();
    
    updateCurrentDimensions();
}

// Update current dimensions display
function updateCurrentDimensions() {
    let w = parseInt(document.getElementById('resizeWidth').value) || originalImage.width;
    let h = parseInt(document.getElementById('resizeHeight').value) || originalImage.height;
    
    if (currentRotation === 90 || currentRotation === 270 || currentRotation === -90) {
        [w, h] = [h, w];
    }
    
    document.getElementById('currentDimensions').textContent = w + ' x ' + h;
}

// Rotate image
function rotateImage(degrees) {
    currentRotation = (currentRotation + degrees) % 360;
    if (currentRotation < 0) currentRotation += 360;
    
    // Swap resize dimensions
    const w = document.getElementById('resizeWidth').value;
    const h = document.getElementById('resizeHeight').value;
    document.getElementById('resizeWidth').value = h;
    document.getElementById('resizeHeight').value = w;
    
    drawImage();
}

// Flip image
function flipImage(direction) {
    if (direction === 'horizontal') {
        isFlippedH = !isFlippedH;
    } else {
        isFlippedV = !isFlippedV;
    }
    drawImage();
}

// Resize image
function resizeImage() {
    const newWidth = parseInt(document.getElementById('resizeWidth').value);
    const newHeight = parseInt(document.getElementById('resizeHeight').value);
    
    if (!newWidth || !newHeight || newWidth < 10 || newHeight < 10) {
        showToast('Please enter valid dimensions (min 10px)', 'danger');
        return;
    }
    
    if (newWidth > 2000 || newHeight > 2000) {
        showToast('Maximum dimension is 2000px', 'danger');
        return;
    }
    
    // Create temp canvas for resize
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = newWidth;
    tempCanvas.height = newHeight;
    const tempCtx = tempCanvas.getContext('2d');
    
    tempCtx.drawImage(editCanvas, 0, 0, newWidth, newHeight);
    
    editCanvas.width = newWidth;
    editCanvas.height = newHeight;
    editCtx.drawImage(tempCanvas, 0, 0);
    
    updateCurrentDimensions();
    showToast('Image resized!', 'success');
}

// Toggle crop mode
function toggleCrop() {
    isCropping = !isCropping;
    const btn = document.getElementById('cropBtn');
    
    if (isCropping) {
        btn.classList.add('active', 'btn-info');
        btn.classList.remove('btn-outline-info');
        showToast('Click and drag on the image to select crop area', 'info');
        
        // Add crop event listeners
        editCanvas.style.cursor = 'crosshair';
        editCanvas.addEventListener('mousedown', startCrop);
        editCanvas.addEventListener('mousemove', updateCrop);
        editCanvas.addEventListener('mouseup', endCrop);
    } else {
        btn.classList.remove('active', 'btn-info');
        btn.classList.add('btn-outline-info');
        editCanvas.style.cursor = 'default';
        editCanvas.removeEventListener('mousedown', startCrop);
        editCanvas.removeEventListener('mousemove', updateCrop);
        editCanvas.removeEventListener('mouseup', endCrop);
        document.getElementById('cropOverlay').style.display = 'none';
    }
}

let isCropDragging = false;

function startCrop(e) {
    const rect = editCanvas.getBoundingClientRect();
    cropStart = {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
    isCropDragging = true;
    
    const overlay = document.getElementById('cropOverlay');
    overlay.style.display = 'block';
    overlay.style.left = cropStart.x + 'px';
    overlay.style.top = cropStart.y + 'px';
    overlay.style.width = '0px';
    overlay.style.height = '0px';
}

function updateCrop(e) {
    if (!isCropDragging) return;
    
    const rect = editCanvas.getBoundingClientRect();
    cropEnd = {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
    
    const overlay = document.getElementById('cropOverlay');
    const left = Math.min(cropStart.x, cropEnd.x);
    const top = Math.min(cropStart.y, cropEnd.y);
    const width = Math.abs(cropEnd.x - cropStart.x);
    const height = Math.abs(cropEnd.y - cropStart.y);
    
    overlay.style.left = left + 'px';
    overlay.style.top = top + 'px';
    overlay.style.width = width + 'px';
    overlay.style.height = height + 'px';
}

function endCrop(e) {
    if (!isCropDragging) return;
    isCropDragging = false;
    
    const rect = editCanvas.getBoundingClientRect();
    cropEnd = {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
    
    const x = Math.min(cropStart.x, cropEnd.x);
    const y = Math.min(cropStart.y, cropEnd.y);
    const width = Math.abs(cropEnd.x - cropStart.x);
    const height = Math.abs(cropEnd.y - cropStart.y);
    
    if (width > 10 && height > 10) {
        // Apply crop
        const imageData = editCtx.getImageData(x, y, width, height);
        editCanvas.width = width;
        editCanvas.height = height;
        editCtx.putImageData(imageData, 0, 0);
        
        document.getElementById('resizeWidth').value = Math.round(width);
        document.getElementById('resizeHeight').value = Math.round(height);
        updateCurrentDimensions();
        
        showToast('Image cropped!', 'success');
    }
    
    document.getElementById('cropOverlay').style.display = 'none';
    toggleCrop(); // Exit crop mode
}

// Reset editor
function resetEditor() {
    currentRotation = 0;
    isFlippedH = false;
    isFlippedV = false;
    
    if (originalImage) {
        document.getElementById('resizeWidth').value = originalImage.width;
        document.getElementById('resizeHeight').value = originalImage.height;
        drawImage();
    }
    
    showToast('Editor reset!', 'info');
}

// Save edited image
function saveEditedImage() {
    const quality = document.getElementById('qualitySlider').value / 100;
    const newWidth = parseInt(document.getElementById('resizeWidth').value);
    const newHeight = parseInt(document.getElementById('resizeHeight').value);
    
    // Create final canvas with target dimensions
    const finalCanvas = document.createElement('canvas');
    finalCanvas.width = newWidth;
    finalCanvas.height = newHeight;
    const finalCtx = finalCanvas.getContext('2d');
    
    finalCtx.drawImage(editCanvas, 0, 0, newWidth, newHeight);
    
    // Convert to blob and upload
    finalCanvas.toBlob(function(blob) {
        // Check size
        if (blob.size > 750 * 1024) {
            showToast('Edited image exceeds 750KB. Try reducing quality or dimensions.', 'danger');
            return;
        }
        
        const formData = new FormData();
        const extension = currentImageData.mime_type === 'image/png' ? 'png' : 'jpg';
        const filename = currentImageData.original_name.replace(/\.[^/.]+$/, '') + '_edited.' + extension;
        
        formData.append('image', blob, filename);
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');
        
        fetch('upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Edited image saved!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed to save: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showToast('Error saving image', 'danger');
        });
    }, currentImageData.mime_type === 'image/png' ? 'image/png' : 'image/jpeg', quality);
}

// Delete confirmation
function confirmDelete(id, name) {
    document.getElementById('deleteImageId').value = id;
    document.getElementById('deleteImageName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Show toast notification
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const id = 'toast-' + Date.now();
    
    const icons = {
        success: 'fa-check-circle',
        danger: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast show bg-${type} text-white`;
    toast.id = id;
    toast.innerHTML = `
        <div class="toast-body d-flex align-items-center">
            <i class="fas ${icons[type]} me-2"></i>
            ${message}
            <button type="button" class="btn-close btn-close-white ms-auto" onclick="document.getElementById('${id}').remove()"></button>
        </div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        const el = document.getElementById(id);
        if (el) el.remove();
    }, 4000);
}
</script>
