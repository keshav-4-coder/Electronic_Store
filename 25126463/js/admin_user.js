/**
 * admin_users.js - User Management JavaScript
 * Handles modal interactions and form submissions
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Edit User Button Click
    const editButtons = document.querySelectorAll('.edit-user-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userData = JSON.parse(this.getAttribute('data-user'));
            
            // Populate edit modal
            document.getElementById('edit_user_id').value = userData.user_id;
            document.getElementById('edit_full_name').value = userData.full_name;
            document.getElementById('edit_username').value = userData.username;
            document.getElementById('edit_email').value = userData.email;
            document.getElementById('edit_role').value = userData.role;
            document.getElementById('edit_phone_no').value = userData.phone_no || '';
            document.getElementById('edit_address').value = userData.address || '';
            document.getElementById('edit_is_active').checked = userData.is_active == 1;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        });
    });
    
    // Delete User Button Click
    const deleteButtons = document.querySelectorAll('.delete-user-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            
            // Populate delete modal
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
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
            
            if (action && action.value === 'add') {
                // Validate password strength for new users
                const password = form.querySelector('input[name="password"]').value;
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long');
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
});