// js/register.js
document.addEventListener('DOMContentLoaded', () => {
    // ── Eye toggles ─────────────────────────────────────
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirm  = document.getElementById('toggleConfirm');

    if (togglePassword) {
        togglePassword.addEventListener('click', () => {
            const pw = document.getElementById('password');
            const icon = togglePassword.querySelector('i');
            pw.type = pw.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }

    if (toggleConfirm) {
        toggleConfirm.addEventListener('click', () => {
            const cp = document.getElementById('confirm_password');
            const icon = toggleConfirm.querySelector('i');
            cp.type = cp.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }

    // ── Password strength & match check ─────────────────
    const pwInput    = document.getElementById('password');
    const cpInput    = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthDiv = document.getElementById('passwordStrength');
    const feedback   = document.getElementById('confirmFeedback');

    function evaluateStrength(password) {
        if (!password) {
            strengthDiv.style.display = 'none';
            return;
        }

        strengthDiv.style.display = 'block';

        let score = 0;
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        const width = (score / 5) * 100;
        strengthBar.style.width = width + '%';

        strengthBar.className = 'strength-' + (
            score >= 4 ? 'strong' :
            score >= 3 ? 'good'   :
            score >= 2 ? 'fair'   : 'weak'
        );
    }

    function checkMatch() {
        if (!cpInput.value) {
            feedback.textContent = '';
            feedback.className = '';
            return;
        }

        if (pwInput.value === cpInput.value) {
            feedback.textContent = 'Passwords match';
            feedback.className = 'text-success';
        } else {
            feedback.textContent = 'Passwords do not match';
            feedback.className = 'text-danger';
        }
    }

    if (pwInput && cpInput) {
        pwInput.addEventListener('input', () => {
            evaluateStrength(pwInput.value);
            checkMatch();
        });

        cpInput.addEventListener('input', checkMatch);
    }
});