// js/login.js
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('togglePassword');
    if (!toggleBtn) return;

    const passwordField = document.getElementById('password');
    const icon = toggleBtn.querySelector('i');

    toggleBtn.addEventListener('click', () => {
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            passwordField.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    });
});