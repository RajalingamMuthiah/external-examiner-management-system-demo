/**
 * EEMS Form Validation JavaScript
 * Client-side validation for better UX
 * Version: 1.0
 */

class FormValidation {
    constructor(formId) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.error('Form not found: ' + formId);
            return;
        }
        
        this.errors = {};
        this.init();
    }
    
    init() {
        // Add Bootstrap validation classes
        this.form.classList.add('needs-validation');
        this.form.setAttribute('novalidate', '');
        
        // Attach submit handler
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Real-time validation on blur
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearError(input));
        });
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        // Clear previous errors
        this.errors = {};
        this.clearAllErrors();
        
        // Validate all fields
        const inputs = this.form.querySelectorAll('input, select, textarea');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        if (isValid) {
            // Check for custom validation
            if (typeof this.form.customValidation === 'function') {
                const customErrors = this.form.customValidation();
                if (customErrors && Object.keys(customErrors).length > 0) {
                    this.errors = customErrors;
                    this.displayErrors();
                    return false;
                }
            }
            
            // Submit form
            this.form.submit();
        } else {
            // Show error summary
            this.showErrorSummary();
            
            // Scroll to first error
            const firstError = this.form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
        
        return false;
    }
    
    validateField(input) {
        const name = input.name;
        const value = input.value.trim();
        const type = input.type;
        
        // Skip disabled or readonly fields
        if (input.disabled || input.readOnly) {
            return true;
        }
        
        // Required validation
        if (input.hasAttribute('required') && !value) {
            this.setError(input, this.getFieldLabel(input) + ' is required.');
            return false;
        }
        
        // Skip further validation if empty and not required
        if (!value) {
            this.clearError(input);
            return true;
        }
        
        // Email validation
        if (type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                this.setError(input, 'Please enter a valid email address.');
                return false;
            }
        }
        
        // Number validation
        if (type === 'number') {
            const num = parseFloat(value);
            
            if (isNaN(num)) {
                this.setError(input, 'Please enter a valid number.');
                return false;
            }
            
            if (input.hasAttribute('min') && num < parseFloat(input.min)) {
                this.setError(input, `Value must be at least ${input.min}.`);
                return false;
            }
            
            if (input.hasAttribute('max') && num > parseFloat(input.max)) {
                this.setError(input, `Value must not exceed ${input.max}.`);
                return false;
            }
        }
        
        // Min length validation
        if (input.hasAttribute('minlength')) {
            const minLength = parseInt(input.getAttribute('minlength'));
            if (value.length < minLength) {
                this.setError(input, `Must be at least ${minLength} characters.`);
                return false;
            }
        }
        
        // Max length validation
        if (input.hasAttribute('maxlength')) {
            const maxLength = parseInt(input.getAttribute('maxlength'));
            if (value.length > maxLength) {
                this.setError(input, `Must not exceed ${maxLength} characters.`);
                return false;
            }
        }
        
        // Pattern validation
        if (input.hasAttribute('pattern')) {
            const pattern = new RegExp(input.getAttribute('pattern'));
            if (!pattern.test(value)) {
                const patternMsg = input.getAttribute('data-pattern-message') || 'Invalid format.';
                this.setError(input, patternMsg);
                return false;
            }
        }
        
        // Date validation
        if (type === 'date') {
            const date = new Date(value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (input.hasAttribute('data-future') && date < today) {
                this.setError(input, 'Date must be in the future.');
                return false;
            }
            
            if (input.hasAttribute('data-past') && date > today) {
                this.setError(input, 'Date must be in the past.');
                return false;
            }
        }
        
        // File validation
        if (type === 'file' && input.files.length > 0) {
            const file = input.files[0];
            
            // Check file size
            const maxSize = input.hasAttribute('data-max-size') 
                ? parseInt(input.getAttribute('data-max-size')) 
                : 10485760; // 10 MB default
            
            if (file.size > maxSize) {
                const sizeMB = (maxSize / 1048576).toFixed(1);
                this.setError(input, `File size must not exceed ${sizeMB} MB.`);
                return false;
            }
            
            // Check file type
            if (input.hasAttribute('accept')) {
                const allowedTypes = input.getAttribute('accept').split(',').map(t => t.trim());
                const fileExt = '.' + file.name.split('.').pop().toLowerCase();
                const fileMime = file.type;
                
                const isAllowed = allowedTypes.some(type => {
                    return type === fileExt || type === fileMime || 
                           (type.endsWith('/*') && fileMime.startsWith(type.replace('/*', '')));
                });
                
                if (!isAllowed) {
                    this.setError(input, 'Invalid file type. Allowed: ' + allowedTypes.join(', '));
                    return false;
                }
            }
        }
        
        // Password confirmation
        if (input.hasAttribute('data-confirm')) {
            const confirmId = input.getAttribute('data-confirm');
            const confirmInput = document.getElementById(confirmId);
            
            if (confirmInput && value !== confirmInput.value) {
                this.setError(input, 'Passwords do not match.');
                return false;
            }
        }
        
        this.clearError(input);
        return true;
    }
    
    setError(input, message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        
        // Create or update feedback element
        let feedback = input.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.insertBefore(feedback, input.nextSibling);
        }
        
        feedback.textContent = message;
        feedback.style.display = 'block';
        
        this.errors[input.name] = message;
    }
    
    clearError(input) {
        input.classList.remove('is-invalid');
        
        if (input.value.trim()) {
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
        }
        
        const feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.style.display = 'none';
        }
        
        delete this.errors[input.name];
    }
    
    clearAllErrors() {
        const inputs = this.form.querySelectorAll('.is-invalid, .is-valid');
        inputs.forEach(input => {
            input.classList.remove('is-invalid', 'is-valid');
        });
        
        const feedbacks = this.form.querySelectorAll('.invalid-feedback');
        feedbacks.forEach(feedback => {
            feedback.style.display = 'none';
        });
        
        // Clear error summary
        const summary = document.getElementById('errorSummary');
        if (summary) {
            summary.remove();
        }
    }
    
    showErrorSummary() {
        // Remove existing summary
        const existing = document.getElementById('errorSummary');
        if (existing) {
            existing.remove();
        }
        
        if (Object.keys(this.errors).length === 0) {
            return;
        }
        
        const summary = document.createElement('div');
        summary.id = 'errorSummary';
        summary.className = 'alert alert-danger alert-dismissible fade show';
        summary.setAttribute('role', 'alert');
        
        let html = '<strong><i class="bi bi-exclamation-circle"></i> Please fix the following errors:</strong><ul class="mb-0 mt-2">';
        
        for (const [field, message] of Object.entries(this.errors)) {
            html += `<li>${message}</li>`;
        }
        
        html += '</ul><button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        summary.innerHTML = html;
        
        this.form.insertBefore(summary, this.form.firstChild);
    }
    
    getFieldLabel(input) {
        // Try to find associated label
        const label = this.form.querySelector(`label[for="${input.id}"]`);
        if (label) {
            return label.textContent.replace('*', '').trim();
        }
        
        // Fallback to placeholder or name
        return input.getAttribute('placeholder') || 
               input.name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    displayErrors() {
        for (const [fieldName, message] of Object.entries(this.errors)) {
            const input = this.form.querySelector(`[name="${fieldName}"]`);
            if (input) {
                this.setError(input, message);
            }
        }
        
        this.showErrorSummary();
    }
}

/**
 * AJAX Form Handler with validation
 */
class AjaxForm {
    constructor(formId, options = {}) {
        this.form = document.getElementById(formId);
        if (!this.form) {
            console.error('Form not found: ' + formId);
            return;
        }
        
        this.options = {
            url: options.url || this.form.action,
            method: options.method || this.form.method || 'POST',
            onSuccess: options.onSuccess || this.defaultSuccess,
            onError: options.onError || this.defaultError,
            submitButton: options.submitButton || this.form.querySelector('button[type="submit"]'),
            loadingText: options.loadingText || 'Processing...'
        };
        
        this.validator = new FormValidation(formId);
        this.init();
    }
    
    init() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        // Validate form
        const inputs = this.form.querySelectorAll('input, select, textarea');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validator.validateField(input)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            this.validator.showErrorSummary();
            return false;
        }
        
        // Disable submit button
        const btn = this.options.submitButton;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + this.options.loadingText;
        
        // Prepare form data
        const formData = new FormData(this.form);
        
        try {
            const response = await fetch(this.options.url, {
                method: this.options.method,
                body: formData
            });
            
            const data = await response.json();
            
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = originalText;
            
            if (data.success) {
                this.options.onSuccess(data);
            } else {
                this.options.onError(data);
            }
            
        } catch (error) {
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = originalText;
            
            this.options.onError({
                success: false,
                message: 'Network error. Please check your connection and try again.'
            });
        }
        
        return false;
    }
    
    defaultSuccess(data) {
        showToast('success', data.message || 'Success!');
        
        // Redirect if URL provided
        if (data.redirect) {
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1500);
        }
    }
    
    defaultError(data) {
        if (data.error && data.error.message) {
            showToast('danger', data.error.message);
        } else if (data.message) {
            showToast('danger', data.message);
        } else {
            showToast('danger', 'An error occurred. Please try again.');
        }
    }
}

/**
 * Toast notification helper
 */
function showToast(type, message, duration = 5000) {
    // Remove existing toasts
    const existing = document.querySelectorAll('.toast-notification');
    existing.forEach(t => t.remove());
    
    const icons = {
        success: 'check-circle',
        danger: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast-notification alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <i class="bi bi-${icons[type]} me-2"></i>
        <strong>${message}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto dismiss
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, duration);
}

/**
 * Confirm action helper
 */
function confirmAction(message, onConfirm, onCancel = null) {
    if (confirm(message)) {
        onConfirm();
    } else if (onCancel) {
        onCancel();
    }
}

/**
 * Loading indicator
 */
function showLoading(container = 'body') {
    const loading = document.createElement('div');
    loading.id = 'globalLoading';
    loading.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
    loading.style.cssText = 'background: rgba(0,0,0,0.5); z-index: 10000;';
    loading.innerHTML = `
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
    `;
    
    document.querySelector(container).appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('globalLoading');
    if (loading) {
        loading.remove();
    }
}

/**
 * Initialize all form validations on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize forms with data-validate attribute
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        if (form.id) {
            new FormValidation(form.id);
        }
    });
});
