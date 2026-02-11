/**
 * deals.js - Deals Page JavaScript
 * Handles countdown timer, add to cart, wishlist, and interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ================================================================
    // FLASH SALE COUNTDOWN TIMER
    // ================================================================
    
    function startCountdown() {
        // Set countdown to end at midnight (or customize as needed)
        const now = new Date();
        const tomorrow = new Date(now);
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(0, 0, 0, 0);
        
        const countdownElement = document.getElementById('countdown');
        if (!countdownElement) return;
        
        const hoursEl = document.getElementById('hours');
        const minutesEl = document.getElementById('minutes');
        const secondsEl = document.getElementById('seconds');
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = tomorrow - now;
            
            if (distance < 0) {
                // Reset to next day
                tomorrow.setDate(tomorrow.getDate() + 1);
                return;
            }
            
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            hoursEl.textContent = hours.toString().padStart(2, '0');
            minutesEl.textContent = minutes.toString().padStart(2, '0');
            secondsEl.textContent = seconds.toString().padStart(2, '0');
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
    
    startCountdown();
    
    // ================================================================
    // ADD TO CART FUNCTIONALITY
    // ================================================================
    
    const addToCartButtons = document.querySelectorAll('.btn-add-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const originalText = this.innerHTML;
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            this.disabled = true;
            
            // Send AJAX request to add to cart
            fetch('/25126463/customer/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success state
                    this.innerHTML = '<i class="fas fa-check"></i> Added!';
                    this.style.background = 'linear-gradient(135deg, #28a745, #218838)';
                    
                    // Show notification
                    showNotification('Success!', `${productName} added to cart`, 'success');
                    
                    // Update cart count in header
                    updateCartCount();
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.background = '';
                        this.disabled = false;
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Failed to add to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.innerHTML = '<i class="fas fa-times"></i> Error';
                this.style.background = 'linear-gradient(135deg, #dc3545, #c82333)';
                
                showNotification('Error', error.message || 'Failed to add to cart', 'error');
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.background = '';
                    this.disabled = false;
                }, 2000);
            });
        });
    });
    
    // ================================================================
    // WISHLIST FUNCTIONALITY
    // ================================================================
    
    const wishlistButtons = document.querySelectorAll('.wishlist-btn');
    
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const productId = this.getAttribute('data-product-id');
            const icon = this.querySelector('i');
            
            // Toggle icon
            if (icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                this.style.background = '#ff6b6b';
                
                // Add to wishlist
                addToWishlist(productId);
                showNotification('Added to Wishlist', 'Product added to your wishlist', 'success');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                this.style.background = '';
                
                // Remove from wishlist
                removeFromWishlist(productId);
                showNotification('Removed', 'Product removed from wishlist', 'info');
            }
        });
    });
    
    function addToWishlist(productId) {
        // Store in localStorage or send to server
        let wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
        if (!wishlist.includes(productId)) {
            wishlist.push(productId);
            localStorage.setItem('wishlist', JSON.stringify(wishlist));
        }
    }
    
    function removeFromWishlist(productId) {
        let wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
        wishlist = wishlist.filter(id => id !== productId);
        localStorage.setItem('wishlist', JSON.stringify(wishlist));
    }
    
    // Load wishlist state on page load
    function loadWishlistState() {
        const wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
        
        wishlistButtons.forEach(button => {
            const productId = button.getAttribute('data-product-id');
            if (wishlist.includes(productId)) {
                const icon = button.querySelector('i');
                icon.classList.remove('far');
                icon.classList.add('fas');
                button.style.background = '#ff6b6b';
            }
        });
    }
    
    loadWishlistState();
    
    // ================================================================
    // SORT FUNCTIONALITY
    // ================================================================
    
    const sortSelect = document.getElementById('sort-select');
    
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const url = new URL(window.location);
            url.searchParams.set('sort', this.value);
            window.location = url;
        });
    }
    
    // ================================================================
    // NEWSLETTER FORM
    // ================================================================
    
    const newsletterForm = document.getElementById('newsletter-form');
    
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[type="email"]').value;
            const button = this.querySelector('button');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
            button.disabled = true;
            
            // Simulate newsletter subscription (replace with actual API call)
            setTimeout(() => {
                showNotification('Subscribed!', 'You will now receive exclusive deal notifications', 'success');
                button.innerHTML = '<i class="fas fa-check"></i> Subscribed';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    this.reset();
                }, 3000);
            }, 1500);
        });
    }
    
    // ================================================================
    // NOTIFICATION SYSTEM
    // ================================================================
    
    function showNotification(title, message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `deal-notification ${type}`;
        
        const iconMap = {
            success: 'check-circle',
            error: 'exclamation-circle',
            info: 'info-circle',
            warning: 'exclamation-triangle'
        };
        
        notification.innerHTML = `
            <i class="fas fa-${iconMap[type]}"></i>
            <div class="notification-content">
                <strong>${title}</strong>
                <p>${message}</p>
            </div>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(notification);
        
        // Trigger animation
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Close button
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });
        
        // Auto close after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
    
    // ================================================================
    // UPDATE CART COUNT
    // ================================================================
    
    function updateCartCount() {
        fetch('/25126463/customer/get_cart_count.php')
            .then(response => response.json())
            .then(data => {
                const cartBadge = document.querySelector('.cart-count');
                if (cartBadge && data.count !== undefined) {
                    cartBadge.textContent = data.count;
                    
                    // Animate the badge
                    cartBadge.style.animation = 'none';
                    setTimeout(() => {
                        cartBadge.style.animation = 'bounce 0.5s ease';
                    }, 10);
                }
            })
            .catch(error => console.error('Error updating cart count:', error));
    }
    
    // ================================================================
    // SMOOTH SCROLL FOR CATEGORY NAVIGATION
    // ================================================================
    
    const categoriesSlider = document.querySelector('.categories-slider');
    
    if (categoriesSlider) {
        let isDown = false;
        let startX;
        let scrollLeft;
        
        categoriesSlider.addEventListener('mousedown', (e) => {
            isDown = true;
            categoriesSlider.style.cursor = 'grabbing';
            startX = e.pageX - categoriesSlider.offsetLeft;
            scrollLeft = categoriesSlider.scrollLeft;
        });
        
        categoriesSlider.addEventListener('mouseleave', () => {
            isDown = false;
            categoriesSlider.style.cursor = 'grab';
        });
        
        categoriesSlider.addEventListener('mouseup', () => {
            isDown = false;
            categoriesSlider.style.cursor = 'grab';
        });
        
        categoriesSlider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - categoriesSlider.offsetLeft;
            const walk = (x - startX) * 2;
            categoriesSlider.scrollLeft = scrollLeft - walk;
        });
    }
    
    // ================================================================
    // LAZY LOADING FOR IMAGES
    // ================================================================
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
});

// Add notification styles dynamically
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .deal-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(145deg, #0c2531 0%, #081822 100%);
        border: 1px solid #1a3a4a;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        max-width: 400px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        z-index: 10000;
        transform: translateX(500px);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .deal-notification.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .deal-notification i:first-child {
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    
    .deal-notification.success {
        border-color: #28a745;
    }
    
    .deal-notification.success i:first-child {
        color: #28a745;
    }
    
    .deal-notification.error {
        border-color: #dc3545;
    }
    
    .deal-notification.error i:first-child {
        color: #dc3545;
    }
    
    .deal-notification.info {
        border-color: #17a2b8;
    }
    
    .deal-notification.info i:first-child {
        color: #17a2b8;
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-content strong {
        color: #c0d4dd;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .notification-content p {
        color: #627d8a;
        margin: 0;
        font-size: 0.9rem;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: #627d8a;
        cursor: pointer;
        padding: 0.5rem;
        transition: color 0.3s ease;
    }
    
    .notification-close:hover {
        color: #c0d4dd;
    }
    
    @media (max-width: 576px) {
        .deal-notification {
            right: 10px;
            left: 10px;
            max-width: none;
        }
    }
`;
document.head.appendChild(notificationStyles);