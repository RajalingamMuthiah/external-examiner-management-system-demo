/**
 * EEMS - JavaScript Utilities Library
 * =====================================================
 * Centralized JavaScript functions for all dashboards
 * Includes: AJAX helpers, Toast notifications, Form validation,
 * Loading indicators, and common utilities
 */

/* ============================================
   TOAST NOTIFICATION SYSTEM
   ============================================ */
const Toast = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', duration = 4000) {
        this.init();
        
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="bi ${icons[type]} toast-icon"></i>
            <div class="toast-content">
                <div class="toast-message">${message}</div>
            </div>
            <i class="bi bi-x toast-close"></i>
        `;
        
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.remove(toast));
        
        this.container.appendChild(toast);
        
        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => this.remove(toast), duration);
        }
        
        return toast;
    },
    
    remove(toast) {
        toast.style.animation = 'slideOutUp 0.3s ease-out';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    },
    
    success(message, duration) {
        return this.show(message, 'success', duration);
    },
    
    error(message, duration) {
        return this.show(message, 'error', duration);
    },
    
    warning(message, duration) {
        return this.show(message, 'warning', duration);
    },
    
    info(message, duration) {
        return this.show(message, 'info', duration);
    }
};

/* Add slideOutUp animation */
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
`;
document.head.appendChild(style);

/* ============================================
   LOADING SPINNER OVERLAY
   ============================================ */
const Loading = {
    overlay: null,
    
    show(message = 'Loading...') {
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'loading-overlay';
            this.overlay.innerHTML = `
                <div style="text-align: center; color: white;">
                    <div class="spinner" style="margin: 0 auto 1rem;"></div>
                    <div class="loading-message" style="font-size: 1.1rem; font-weight: 500;">${message}</div>
                </div>
            `;
            document.body.appendChild(this.overlay);
        } else {
            this.overlay.querySelector('.loading-message').textContent = message;
            this.overlay.style.display = 'flex';
        }
        document.body.style.overflow = 'hidden';
    },
    
    hide() {
        if (this.overlay) {
            this.overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    },
    
    updateMessage(message) {
        if (this.overlay) {
            this.overlay.querySelector('.loading-message').textContent = message;
        }
    }
};

/* ============================================
   AJAX HELPER FUNCTIONS
   ============================================ */
const Ajax = {
    /**
     * Make a GET request
     */
    get(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        
        return fetch(fullUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
    },
    
    /**
     * Make a POST request
     */
    post(url, data = {}) {
        const formData = new FormData();
        
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                formData.append(key, data[key]);
            }
        }
        
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        }).then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
    },
    
    /**
     * Make a request with loading indicator
     */
    withLoading(promise, loadingMessage = 'Processing...') {
        Loading.show(loadingMessage);
        return promise.finally(() => Loading.hide());
    }
};

/* ============================================
   FORM VALIDATION
   ============================================ */
const Validation = {
    /**
     * Validate email format
     */
    isEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    /**
     * Validate phone number
     */
    isPhone(phone) {
        const re = /^[0-9+\-\s()]{6,20}$/;
        return re.test(phone);
    },
    
    /**
     * Validate required field
     */
    required(value) {
        return value !== null && value !== undefined && value.toString().trim() !== '';
    },
    
    /**
     * Validate minimum length
     */
    minLength(value, length) {
        return value.toString().length >= length;
    },
    
    /**
     * Validate maximum length
     */
    maxLength(value, length) {
        return value.toString().length <= length;
    },
    
    /**
     * Validate number
     */
    isNumber(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    },
    
    /**
     * Show validation error on field
     */
    showError(field, message) {
        field.classList.add('error');
        
        // Remove existing error message
        const existingError = field.parentElement.querySelector('.form-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Add new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'form-error';
        errorDiv.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${message}`;
        field.parentElement.appendChild(errorDiv);
    },
    
    /**
     * Clear validation error
     */
    clearError(field) {
        field.classList.remove('error');
        const errorDiv = field.parentElement.querySelector('.form-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    },
    
    /**
     * Show success state
     */
    showSuccess(field) {
        field.classList.remove('error');
        field.classList.add('success');
        this.clearError(field);
    },
    
    /**
     * Validate form
     */
    validateForm(formElement, rules) {
        let isValid = true;
        
        for (const fieldName in rules) {
            const field = formElement.querySelector(`[name="${fieldName}"]`);
            if (!field) continue;
            
            const fieldRules = rules[fieldName];
            const value = field.value;
            
            // Required validation
            if (fieldRules.required && !this.required(value)) {
                this.showError(field, fieldRules.requiredMessage || 'This field is required');
                isValid = false;
                continue;
            }
            
            // Email validation
            if (fieldRules.email && value && !this.isEmail(value)) {
                this.showError(field, 'Please enter a valid email address');
                isValid = false;
                continue;
            }
            
            // Phone validation
            if (fieldRules.phone && value && !this.isPhone(value)) {
                this.showError(field, 'Please enter a valid phone number');
                isValid = false;
                continue;
            }
            
            // Min length validation
            if (fieldRules.minLength && value && !this.minLength(value, fieldRules.minLength)) {
                this.showError(field, `Minimum ${fieldRules.minLength} characters required`);
                isValid = false;
                continue;
            }
            
            // Max length validation
            if (fieldRules.maxLength && value && !this.maxLength(value, fieldRules.maxLength)) {
                this.showError(field, `Maximum ${fieldRules.maxLength} characters allowed`);
                isValid = false;
                continue;
            }
            
            // Custom validation
            if (fieldRules.custom) {
                const customResult = fieldRules.custom(value, field);
                if (customResult !== true) {
                    this.showError(field, customResult);
                    isValid = false;
                    continue;
                }
            }
            
            // If all validations pass
            this.showSuccess(field);
        }
        
        return isValid;
    }
};

/* ============================================
   REAL-TIME FORM VALIDATION
   ============================================ */
function enableRealTimeValidation(formElement, rules) {
    for (const fieldName in rules) {
        const field = formElement.querySelector(`[name="${fieldName}"]`);
        if (!field) continue;
        
        // Validate on blur
        field.addEventListener('blur', () => {
            const singleFieldRules = { [fieldName]: rules[fieldName] };
            Validation.validateForm(formElement, singleFieldRules);
        });
        
        // Clear error on input
        field.addEventListener('input', () => {
            if (field.classList.contains('error')) {
                Validation.clearError(field);
            }
        });
    }
}

/* ============================================
   CONFIRMATION DIALOG
   ============================================ */
function confirmDialog(message, title = 'Confirm Action') {
    return new Promise((resolve) => {
        if (confirm(`${title}\n\n${message}`)) {
            resolve(true);
        } else {
            resolve(false);
        }
    });
}

/* ============================================
   DEBOUNCE FUNCTION
   ============================================ */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/* ============================================
   FORMAT DATE
   ============================================ */
function formatDate(dateString, format = 'MMM DD, YYYY') {
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();
    
    return `${month} ${day}, ${year}`;
}

/* ============================================
   COPY TO CLIPBOARD
   ============================================ */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            Toast.success('Copied to clipboard!');
        }).catch(() => {
            Toast.error('Failed to copy');
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            Toast.success('Copied to clipboard!');
        } catch (err) {
            Toast.error('Failed to copy');
        }
        document.body.removeChild(textarea);
    }
}

/* ============================================
   SIDEBAR TOGGLE (Mobile)
   ============================================ */
function initSidebarToggle() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    
    // Create toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'sidebar-toggle';
    toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
    toggleBtn.style.cssText = `
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1031;
        background: white;
        border: none;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: none;
    `;
    
    // Show toggle button on mobile
    if (window.innerWidth <= 1024) {
        toggleBtn.style.display = 'block';
    }
    
    document.body.appendChild(toggleBtn);
    
    // Toggle sidebar
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
    
    // Close sidebar when clicking outside
    document.addEventListener('click', (e) => {
        if (sidebar.classList.contains('open') && 
            !sidebar.contains(e.target) && 
            !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth <= 1024) {
            toggleBtn.style.display = 'block';
        } else {
            toggleBtn.style.display = 'none';
            sidebar.classList.remove('open');
        }
    });
}

/* ============================================
   INITIALIZE ON PAGE LOAD
   ============================================ */
document.addEventListener('DOMContentLoaded', () => {
    // Initialize sidebar toggle for mobile
    initSidebarToggle();
    
    // Add navbar scroll effect
    const navbar = document.querySelector('.top-navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }
    
    // Initialize tooltips (if Bootstrap tooltips are used)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

/* ============================================
   EXPORT FOR GLOBAL USE
   ============================================ */
window.Toast = Toast;
window.Loading = Loading;
window.Ajax = Ajax;
window.Validation = Validation;
window.enableRealTimeValidation = enableRealTimeValidation;
window.confirmDialog = confirmDialog;
window.debounce = debounce;
window.formatDate = formatDate;
window.copyToClipboard = copyToClipboard;

/* ============================================
   REPLACE UGLY alert() GLOBALLY
   ============================================ */
window.alert = function(message) {
    Toast.info(message, 5000);
};

console.log('✅ EEMS Utilities loaded successfully');
