/**
 * EEMS - Real-Time Form Validation
 * =====================================================
 * Enhanced form validation with visual feedback
 */

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    const feedback = [];
    
    if (password.length >= 8) {
        strength += 25;
    } else {
        feedback.push('At least 8 characters');
    }
    
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
        strength += 25;
    } else {
        feedback.push('Mix of uppercase and lowercase');
    }
    
    if (/[0-9]/.test(password)) {
        strength += 25;
    } else {
        feedback.push('At least one number');
    }
    
    if (/[^a-zA-Z0-9]/.test(password)) {
        strength += 25;
    } else {
        feedback.push('Special character (!@#$%^&*)');
    }
    
    let level = 'weak';
    let color = '#ef4444';
    
    if (strength >= 75) {
        level = 'strong';
        color = '#10b981';
    } else if (strength >= 50) {
        level = 'medium';
        color = '#f59e0b';
    }
    
    return { strength, level, color, feedback };
}

// Add password strength meter
function addPasswordStrengthMeter(passwordField) {
    const container = document.createElement('div');
    container.className = 'password-strength-container';
    container.style.cssText = `
        margin-top: 0.5rem;
        display: none;
    `;
    
    container.innerHTML = `
        <div class="password-strength-bar" style="
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        ">
            <div class="password-strength-fill" style="
                height: 100%;
                width: 0%;
                transition: all 0.3s ease;
                background: #ef4444;
            "></div>
        </div>
        <div class="password-strength-text" style="
            font-size: 0.85rem;
            font-weight: 500;
        "></div>
        <ul class="password-strength-feedback" style="
            font-size: 0.75rem;
            color: #6b7280;
            margin: 0.25rem 0 0 1.25rem;
            padding: 0;
        "></ul>
    `;
    
    passwordField.parentElement.appendChild(container);
    
    const fill = container.querySelector('.password-strength-fill');
    const text = container.querySelector('.password-strength-text');
    const feedbackList = container.querySelector('.password-strength-feedback');
    
    passwordField.addEventListener('input', () => {
        const value = passwordField.value;
        
        if (!value) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        const result = checkPasswordStrength(value);
        
        fill.style.width = result.strength + '%';
        fill.style.background = result.color;
        text.textContent = `Password strength: ${result.level.toUpperCase()}`;
        text.style.color = result.color;
        
        feedbackList.innerHTML = '';
        result.feedback.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item;
            feedbackList.appendChild(li);
        });
    });
}

// Email validation with real-time checking
function addEmailValidation(emailField) {
    let timeout;
    
    emailField.addEventListener('input', () => {
        clearTimeout(timeout);
        
        const value = emailField.value.trim();
        
        if (!value) {
            Validation.clearError(emailField);
            return;
        }
        
        // Basic format check
        if (!Validation.isEmail(value)) {
            Validation.showError(emailField, 'Please enter a valid email address');
            return;
        }
        
        // Clear error if valid
        Validation.showSuccess(emailField);
        
        // Optional: Check if email exists in database
        timeout = setTimeout(() => {
            // This would be an AJAX call to check email availability
            // For now, we just show success
        }, 500);
    });
}

// Phone number formatting and validation
function addPhoneValidation(phoneField) {
    phoneField.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        
        // Format as (XXX) XXX-XXXX for 10-digit US numbers
        if (value.length <= 10) {
            if (value.length > 6) {
                value = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6)}`;
            } else if (value.length > 3) {
                value = `(${value.slice(0, 3)}) ${value.slice(3)}`;
            } else if (value.length > 0) {
                value = `(${value}`;
            }
        }
        
        e.target.value = value;
        
        // Validate
        const digitsOnly = value.replace(/\D/g, '');
        if (digitsOnly.length === 10 || digitsOnly.length === 0) {
            Validation.clearError(phoneField);
            if (digitsOnly.length === 10) {
                Validation.showSuccess(phoneField);
            }
        } else {
            Validation.showError(phoneField, 'Please enter a valid 10-digit phone number');
        }
    });
}

// Date validation (must be future date)
function addFutureDateValidation(dateField, message = 'Date must be in the future') {
    dateField.addEventListener('change', () => {
        const selectedDate = new Date(dateField.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate <= today) {
            Validation.showError(dateField, message);
        } else {
            Validation.showSuccess(dateField);
        }
    });
}

// Confirm password matching
function addPasswordConfirmation(passwordField, confirmField) {
    function check() {
        if (!confirmField.value) {
            Validation.clearError(confirmField);
            return;
        }
        
        if (passwordField.value !== confirmField.value) {
            Validation.showError(confirmField, 'Passwords do not match');
        } else {
            Validation.showSuccess(confirmField);
        }
    }
    
    passwordField.addEventListener('input', check);
    confirmField.addEventListener('input', check);
}

// Auto-initialize validation on common fields
document.addEventListener('DOMContentLoaded', () => {
    // Password strength meters
    document.querySelectorAll('input[type="password"][name="password"]:not([name*="confirm"])').forEach(field => {
        addPasswordStrengthMeter(field);
    });
    
    // Email validation
    document.querySelectorAll('input[type="email"]').forEach(field => {
        addEmailValidation(field);
    });
    
    // Phone validation
    document.querySelectorAll('input[type="tel"], input[name="phone"]').forEach(field => {
        addPhoneValidation(field);
    });
    
    // Future date validation
    document.querySelectorAll('input[type="date"][data-future]').forEach(field => {
        addFutureDateValidation(field);
    });
    
    // Password confirmation
    document.querySelectorAll('input[type="password"][name="password"]').forEach(passwordField => {
        const confirmField = document.querySelector('input[type="password"][name="password_confirm"], input[type="password"][name="confirm_password"]');
        if (confirmField) {
            addPasswordConfirmation(passwordField, confirmField);
        }
    });
    
    // Required field indicators
    document.querySelectorAll('[required]').forEach(field => {
        const label = document.querySelector(`label[for="${field.id}"]`) || 
                     field.previousElementSibling;
        
        if (label && label.tagName === 'LABEL' && !label.querySelector('.required-indicator')) {
            const indicator = document.createElement('span');
            indicator.className = 'required-indicator';
            indicator.textContent = ' *';
            indicator.style.color = '#ef4444';
            label.appendChild(indicator);
        }
    });
});

console.log('✅ Real-time form validation initialized');
