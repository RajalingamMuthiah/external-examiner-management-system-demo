<!-- 
    EEMS - Common Footer Scripts
    ==========================================
    Include this before </body> tag in all pages
    Usage: <?php require_once __DIR__ . '/includes/footer_scripts.php'; ?>
-->

<!-- jQuery (for legacy compatibility and Bootstrap) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" 
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

<!-- EEMS Utilities - Toast, Loading, AJAX, Validation -->
<script src="<?php echo $base_url ?? ''; ?>/public/js/utils.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo $base_url ?? ''; ?>/public/js/validation.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo $base_url ?? ''; ?>/public/js/pdf_export.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo $base_url ?? ''; ?>/public/js/global_search.js?v=<?php echo time(); ?>"></script>

<!-- Page-specific JavaScript (optional) -->
<?php if (isset($additionalJS)): ?>
    <?php foreach ($additionalJS as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Initialize Features -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show any flash messages as toasts
    <?php if (isset($_SESSION['flash_message'])): ?>
        <?php 
        $flash = $_SESSION['flash_message'];
        $flashType = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        ?>
        Toast.<?php echo $flashType; ?>('<?php echo addslashes($flash); ?>');
    <?php endif; ?>
    
    // Initialize any forms with data-validate attribute
    document.querySelectorAll('form[data-validate]').forEach(form => {
        const rules = {};
        
        // Auto-detect validation rules from input attributes
        form.querySelectorAll('[required], [type="email"], [minlength], [maxlength]').forEach(input => {
            const name = input.getAttribute('name');
            if (!name) return;
            
            rules[name] = {};
            
            if (input.hasAttribute('required')) {
                rules[name].required = true;
                rules[name].requiredMessage = input.getAttribute('data-required-message') || 'This field is required';
            }
            
            if (input.getAttribute('type') === 'email') {
                rules[name].email = true;
            }
            
            if (input.hasAttribute('minlength')) {
                rules[name].minLength = parseInt(input.getAttribute('minlength'));
            }
            
            if (input.hasAttribute('maxlength')) {
                rules[name].maxLength = parseInt(input.getAttribute('maxlength'));
            }
        });
        
        // Enable real-time validation
        if (Object.keys(rules).length > 0) {
            enableRealTimeValidation(form, rules);
            
            // Validate on submit
            form.addEventListener('submit', function(e) {
                if (!Validation.validateForm(form, rules)) {
                    e.preventDefault();
                    Toast.error('Please fix the errors in the form');
                    return false;
                }
            });
        }
    });
    
    // Initialize all AJAX forms
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const url = form.getAttribute('action') || window.location.href;
            const method = form.getAttribute('method') || 'POST';
            const loadingMessage = form.getAttribute('data-loading-message') || 'Processing...';
            
            Loading.show(loadingMessage);
            
            fetch(url, {
                method: method,
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                Loading.hide();
                
                if (data.success) {
                    Toast.success(data.message || 'Operation completed successfully');
                    
                    // Handle redirect
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                    
                    // Handle reload
                    if (data.reload) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                    
                    // Call custom callback if provided
                    if (typeof window[form.getAttribute('data-success-callback')] === 'function') {
                        window[form.getAttribute('data-success-callback')](data);
                    }
                } else {
                    Toast.error(data.message || 'An error occurred');
                    
                    // Call custom callback if provided
                    if (typeof window[form.getAttribute('data-error-callback')] === 'function') {
                        window[form.getAttribute('data-error-callback')](data);
                    }
                }
            })
            .catch(error => {
                Loading.hide();
                console.error('Error:', error);
                Toast.error('Network error. Please try again.');
            });
        });
    });
    
    // Add confirmation dialogs to data-confirm elements
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    // Don't show toast for every error, just log it
});

// Handle session timeout
let sessionTimeout;
function resetSessionTimeout() {
    clearTimeout(sessionTimeout);
    // 30 minutes of inactivity
    sessionTimeout = setTimeout(() => {
        Toast.warning('Your session is about to expire due to inactivity', 0);
        setTimeout(() => {
            window.location.href = 'logout.php?reason=timeout';
        }, 30000); // 30 seconds warning
    }, 30 * 60 * 1000);
}

// Reset timeout on user activity
['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, resetSessionTimeout, true);
});

resetSessionTimeout();

console.log('✅ EEMS Frontend initialized successfully');
</script>
