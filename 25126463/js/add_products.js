/**
 * add_products.js - Enhanced Add Products JavaScript
 * Handles file upload, URL upload, and form validation
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ================================================================
    // FILE UPLOAD HANDLING
    // ================================================================
    
    const fileInput = document.getElementById('product_image');
    const fileLabel = document.getElementById('fileLabel');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const removeBtn = document.getElementById('removeImage');
    
    if (fileInput) {
        // File input change
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPG, JPEG, PNG, GIF, or WEBP)');
                    fileInput.value = '';
                    return;
                }
                
                // Validate file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    fileInput.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    fileLabel.style.display = 'none';
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Remove image
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                fileInput.value = '';
                fileLabel.style.display = 'block';
                imagePreview.style.display = 'none';
                previewImg.src = '';
            });
        }
        
        // Drag and drop
        const wrapper = fileInput.closest('.file-upload-wrapper');
        
        if (wrapper) {
            wrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                fileLabel.style.borderColor = '#40c4ff';
                fileLabel.style.backgroundColor = 'rgba(64, 196, 255, 0.1)';
            });
            
            wrapper.addEventListener('dragleave', function(e) {
                e.preventDefault();
                fileLabel.style.borderColor = '#1a3a4a';
                fileLabel.style.backgroundColor = '#081822';
            });
            
            wrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                fileLabel.style.borderColor = '#1a3a4a';
                fileLabel.style.backgroundColor = '#081822';
                
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        }
    }
    
    // ================================================================
    // UPLOAD METHOD TOGGLE
    // ================================================================
    
    const methodFile = document.getElementById('method_file');
    const methodUrl = document.getElementById('method_url');
    const fileSection = document.getElementById('file-upload-section');
    const urlSection = document.getElementById('url-upload-section');
    
    if (methodFile && methodUrl) {
        methodFile.addEventListener('change', function() {
            if (this.checked) {
                fileSection.style.display = 'block';
                urlSection.style.display = 'none';
                // Clear URL input
                document.getElementById('image_url').value = '';
                document.getElementById('urlPreview').style.display = 'none';
            }
        });
        
        methodUrl.addEventListener('change', function() {
            if (this.checked) {
                fileSection.style.display = 'none';
                urlSection.style.display = 'block';
                // Clear file input
                if (fileInput) {
                    fileInput.value = '';
                    fileLabel.style.display = 'block';
                    imagePreview.style.display = 'none';
                }
            }
        });
    }
    
    // ================================================================
    // URL UPLOAD HANDLING
    // ================================================================
    
    const imageUrlInput = document.getElementById('image_url');
    const previewUrlBtn = document.getElementById('previewUrl');
    const urlPreview = document.getElementById('urlPreview');
    const urlPreviewImg = document.getElementById('urlPreviewImg');
    const closeUrlPreview = document.getElementById('closeUrlPreview');
    
    // Preview URL button
    if (previewUrlBtn && imageUrlInput) {
        previewUrlBtn.addEventListener('click', function() {
            const url = imageUrlInput.value.trim();
            
            if (!url) {
                alert('Please enter an image URL');
                return;
            }
            
            if (!isValidUrl(url)) {
                alert('Please enter a valid URL');
                return;
            }
            
            loadUrlPreview(url);
        });
    }
    
    // Auto preview on blur (when user leaves the input field)
    if (imageUrlInput) {
        imageUrlInput.addEventListener('blur', function() {
            const url = this.value.trim();
            if (url && isValidUrl(url)) {
                loadUrlPreview(url);
            }
        });
        
        // Preview on Enter key
        imageUrlInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const url = this.value.trim();
                if (url && isValidUrl(url)) {
                    loadUrlPreview(url);
                }
            }
        });
    }
    
    // Close URL preview
    if (closeUrlPreview) {
        closeUrlPreview.addEventListener('click', function() {
            urlPreview.style.display = 'none';
            urlPreviewImg.src = '';
        });
    }
    
    // Function to validate URL
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    // Function to load URL preview
    function loadUrlPreview(url) {
        urlPreview.classList.add('loading');
        urlPreview.style.display = 'block';
        urlPreviewImg.style.display = 'none';
        
        urlPreviewImg.onload = function() {
            urlPreview.classList.remove('loading');
            urlPreviewImg.style.display = 'block';
        };
        
        urlPreviewImg.onerror = function() {
            urlPreview.classList.remove('loading');
            urlPreview.style.display = 'none';
            alert('Unable to load image from this URL. Please check:\n\n1. The URL is correct and accessible\n2. The URL points directly to an image file\n3. The image format is supported (JPG, PNG, GIF, WEBP)\n4. The image server allows external access');
        };
        
        urlPreviewImg.src = url;
    }
    
    // ================================================================
    // FORM VALIDATION
    // ================================================================
    
    const productForm = document.getElementById('productForm');
    
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            const productName = document.getElementById('product_name').value.trim();
            const category = document.getElementById('category_id').value;
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock').value);
            const uploadMethod = document.querySelector('input[name="image_upload_method"]:checked').value;
            
            // Validate product name
            if (!productName) {
                e.preventDefault();
                alert('Please enter a product name');
                document.getElementById('product_name').focus();
                return false;
            }
            
            // Validate category
            if (!category) {
                e.preventDefault();
                alert('Please select a category');
                document.getElementById('category_id').focus();
                return false;
            }
            
            // Validate price
            if (isNaN(price) || price <= 0) {
                e.preventDefault();
                alert('Please enter a valid price greater than 0');
                document.getElementById('price').focus();
                return false;
            }
            
            // Validate stock
            if (isNaN(stock) || stock < 0) {
                e.preventDefault();
                alert('Please enter a valid stock quantity (0 or greater)');
                document.getElementById('stock').focus();
                return false;
            }
            
            // Validate image upload based on method
            if (uploadMethod === 'file') {
                const fileInput = document.getElementById('product_image');
                if (fileInput && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (file.size > 5 * 1024 * 1024) {
                        e.preventDefault();
                        alert('Image file size must be less than 5MB');
                        return false;
                    }
                }
            } else if (uploadMethod === 'url') {
                const imageUrl = document.getElementById('image_url').value.trim();
                if (imageUrl && !isValidUrl(imageUrl)) {
                    e.preventDefault();
                    alert('Please enter a valid image URL');
                    document.getElementById('image_url').focus();
                    return false;
                }
            }
            
            // Show loading state on submit button
            const submitBtn = productForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                submitBtn.disabled = true;
                
                // Re-enable button after 3 seconds in case of issues
                setTimeout(function() {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    }
    
    // ================================================================
    // AUTO-DISMISS ALERTS
    // ================================================================
    
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // ================================================================
    // PRICE FORMATTING
    // ================================================================
    
    const priceInput = document.getElementById('price');
    if (priceInput) {
        priceInput.addEventListener('blur', function() {
            const value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        });
    }
    
    // ================================================================
    // STOCK VALIDATION (INTEGERS ONLY)
    // ================================================================
    
    const stockInput = document.getElementById('stock');
    if (stockInput) {
        stockInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    // ================================================================
    // CATEGORY SEARCH/FILTER
    // ================================================================
    
    const categorySelect = document.getElementById('category_id');
    if (categorySelect) {
        // Add search functionality for categories
        categorySelect.addEventListener('keydown', function(e) {
            // Enable browser's built-in search for select elements
            // Modern browsers support this automatically
        });
    }
    
    // ================================================================
    // TOOLTIP INITIALIZATION (if using Bootstrap tooltips)
    // ================================================================
    
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
});

// ================================================================
// HELPER FUNCTIONS
// ================================================================

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Validate image dimensions (optional enhancement)
function validateImageDimensions(file, minWidth, minHeight) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                if (img.width >= minWidth && img.height >= minHeight) {
                    resolve(true);
                } else {
                    reject(`Image dimensions must be at least ${minWidth}x${minHeight}px`);
                }
            };
            img.onerror = function() {
                reject('Failed to load image');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}