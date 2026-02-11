/**
 * admin_products.js - Product Management JavaScript
 * Handles modal interactions, image preview, and form submissions
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Image Preview Functionality
    function setupImagePreview(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        
        if (input && preview) {
            input.addEventListener('change', function() {
                const file = this.files[0];
                
                if (file) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="image-preview-remove" onclick="clearImagePreview('${inputId}', '${previewId}')">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                    };
                    
                    reader.readAsDataURL(file);
                } else {
                    clearImagePreview(inputId, previewId);
                }
            });
        }
    }
    
    // Clear Image Preview
    window.clearImagePreview = function(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        
        if (input) input.value = '';
        if (preview) {
            preview.innerHTML = `
                <div class="image-preview-placeholder">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Click to upload image</span>
                </div>
            `;
        }
    };
    
    // Setup image previews for add and edit modals
    setupImagePreview('add_product_image', 'add_image_preview');
    setupImagePreview('edit_product_image', 'edit_image_preview');
    
    // Edit Product Button Click
    const editButtons = document.querySelectorAll('.edit-product-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const productId = this.getAttribute('data-product-id');
            
            try {
                // Fetch product data via AJAX
                const response = await fetch(`/25126463/admin/get_product.php?id=${productId}`);
                const product = await response.json();
                
                if (product.success) {
                    const data = product.data;
                    
                    // Populate edit modal
                    document.getElementById('edit_product_id').value = data.product_id;
                    document.getElementById('edit_product_name').value = data.product_name;
                    document.getElementById('edit_category_id').value = data.category_id;
                    document.getElementById('edit_seller_id').value = data.seller_id || '';
                    document.getElementById('edit_description').value = data.description || '';
                    document.getElementById('edit_price').value = data.price;
                    document.getElementById('edit_stock').value = data.stock;
                    document.getElementById('edit_is_active').checked = data.is_active == 1;
                    
                    // Show current image if exists
                    const imagePreview = document.getElementById('edit_image_preview');
                    if (data.product_image) {
                        imagePreview.innerHTML = `
                            <img src="/25126463/uploads/products/${data.product_image}" alt="Current Image">
                            <button type="button" class="image-preview-remove" onclick="clearImagePreview('edit_product_image', 'edit_image_preview')">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                    } else {
                        clearImagePreview('edit_product_image', 'edit_image_preview');
                    }
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
                    modal.show();
                } else {
                    alert('Failed to load product data');
                }
            } catch (error) {
                console.error('Error fetching product:', error);
                alert('An error occurred while loading product data');
            }
        });
    });
    
    // Delete Product Button Click
    const deleteButtons = document.querySelectorAll('.delete-product-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            
            // Populate delete modal
            document.getElementById('delete_product_id').value = productId;
            document.getElementById('delete_product_name').textContent = productName;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
            modal.show();
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Form validation
    const forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = form.querySelector('input[name="action"]');
            
            if (action && (action.value === 'add' || action.value === 'edit')) {
                // Validate required fields
                const productName = form.querySelector('input[name="product_name"]');
                const price = form.querySelector('input[name="price"]');
                const stock = form.querySelector('input[name="stock"]');
                
                if (!productName.value.trim()) {
                    e.preventDefault();
                    alert('Product name is required');
                    productName.focus();
                    return false;
                }
                
                if (!price.value || parseFloat(price.value) <= 0) {
                    e.preventDefault();
                    alert('Price must be greater than 0');
                    price.focus();
                    return false;
                }
                
                if (!stock.value || parseInt(stock.value) < 0) {
                    e.preventDefault();
                    alert('Stock cannot be negative');
                    stock.focus();
                    return false;
                }
            }
        });
    });
    
    // Search on Enter key
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    }
    
    // Price formatting
    const priceInputs = document.querySelectorAll('input[name="price"]');
    priceInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const value = parseFloat(this.value);
            if (!isNaN(value)) {
                this.value = value.toFixed(2);
            }
        });
    });
    
    // Stock validation (integers only)
    const stockInputs = document.querySelectorAll('input[name="stock"]');
    stockInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });
});

// Toggle form submission loading state
function showLoading(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    button.disabled = true;
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    }, 3000);
}

// Confirmation before form submission
function confirmAction(message) {
    return confirm(message);
}