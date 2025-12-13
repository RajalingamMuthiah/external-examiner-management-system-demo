/**
 * EEMS Accessibility Module
 * WCAG 2.1 Level AA Compliance
 * Version: 1.0
 */

class AccessibilityManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupKeyboardNavigation();
        this.setupARIA();
        this.setupFocusManagement();
        this.setupAnnouncements();
        this.setupReducedMotion();
    }
    
    /**
     * Keyboard Navigation Support
     */
    setupKeyboardNavigation() {
        // Tab trap for modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeTopModal();
            }
            
            if (e.key === 'Tab') {
                this.handleTabNavigation(e);
            }
        });
        
        // Arrow key navigation for lists
        document.querySelectorAll('[role="listbox"], [role="menu"]').forEach(list => {
            this.setupArrowNavigation(list);
        });
        
        // Enter/Space for buttons
        document.querySelectorAll('[role="button"]:not(button)').forEach(btn => {
            btn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    btn.click();
                }
            });
        });
    }
    
    setupArrowNavigation(container) {
        const items = container.querySelectorAll('[role="option"], [role="menuitem"]');
        
        container.addEventListener('keydown', (e) => {
            const currentIndex = Array.from(items).indexOf(document.activeElement);
            let nextIndex;
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    nextIndex = (currentIndex + 1) % items.length;
                    items[nextIndex].focus();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    nextIndex = (currentIndex - 1 + items.length) % items.length;
                    items[nextIndex].focus();
                    break;
                case 'Home':
                    e.preventDefault();
                    items[0].focus();
                    break;
                case 'End':
                    e.preventDefault();
                    items[items.length - 1].focus();
                    break;
            }
        });
    }
    
    handleTabNavigation(e) {
        const modal = document.querySelector('.modal.show');
        if (!modal) return;
        
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length === 0) return;
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.shiftKey) {
            if (document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    }
    
    closeTopModal() {
        const modal = document.querySelector('.modal.show');
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    }
    
    /**
     * ARIA Attributes Setup
     */
    setupARIA() {
        // Add ARIA labels to unlabeled buttons
        document.querySelectorAll('button:not([aria-label]):not([aria-labelledby])').forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon && !btn.textContent.trim()) {
                const iconClass = icon.className;
                let label = this.guessLabelFromIcon(iconClass);
                if (label) {
                    btn.setAttribute('aria-label', label);
                }
            }
        });
        
        // Add ARIA labels to form controls without labels
        document.querySelectorAll('input, select, textarea').forEach(input => {
            if (!input.id || !document.querySelector(`label[for="${input.id}"]`)) {
                const placeholder = input.getAttribute('placeholder');
                if (placeholder && !input.getAttribute('aria-label')) {
                    input.setAttribute('aria-label', placeholder);
                }
            }
        });
        
        // Mark required fields
        document.querySelectorAll('[required]').forEach(field => {
            field.setAttribute('aria-required', 'true');
        });
        
        // Add live regions for dynamic content
        this.createLiveRegion();
    }
    
    guessLabelFromIcon(iconClass) {
        const iconMap = {
            'edit': 'Edit',
            'trash': 'Delete',
            'delete': 'Delete',
            'close': 'Close',
            'search': 'Search',
            'filter': 'Filter',
            'download': 'Download',
            'upload': 'Upload',
            'save': 'Save',
            'cancel': 'Cancel',
            'check': 'Confirm',
            'plus': 'Add',
            'minus': 'Remove',
            'eye': 'View',
            'pencil': 'Edit',
            'gear': 'Settings',
            'cog': 'Settings'
        };
        
        for (const [key, label] of Object.entries(iconMap)) {
            if (iconClass.includes(key)) {
                return label;
            }
        }
        
        return null;
    }
    
    createLiveRegion() {
        if (document.getElementById('a11y-announcer')) return;
        
        const announcer = document.createElement('div');
        announcer.id = 'a11y-announcer';
        announcer.setAttribute('role', 'status');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        document.body.appendChild(announcer);
    }
    
    /**
     * Focus Management
     */
    setupFocusManagement() {
        // Store focus before modal opens
        document.addEventListener('show.bs.modal', (e) => {
            e.target.previousFocus = document.activeElement;
        });
        
        // Restore focus when modal closes
        document.addEventListener('hidden.bs.modal', (e) => {
            if (e.target.previousFocus) {
                e.target.previousFocus.focus();
            }
        });
        
        // Focus first input when modal opens
        document.addEventListener('shown.bs.modal', (e) => {
            const firstInput = e.target.querySelector('input, select, textarea, button');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // Skip links
        this.createSkipLinks();
    }
    
    createSkipLinks() {
        if (document.querySelector('.skip-to-main')) return;
        
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.className = 'skip-to-main';
        skipLink.textContent = 'Skip to main content';
        document.body.insertBefore(skipLink, document.body.firstChild);
        
        // Ensure main content has ID
        const main = document.querySelector('main') || document.querySelector('.main-content');
        if (main && !main.id) {
            main.id = 'main-content';
            main.setAttribute('tabindex', '-1');
        }
    }
    
    /**
     * Screen Reader Announcements
     */
    setupAnnouncements() {
        this.announcer = document.getElementById('a11y-announcer');
    }
    
    announce(message, priority = 'polite') {
        if (!this.announcer) {
            this.createLiveRegion();
            this.announcer = document.getElementById('a11y-announcer');
        }
        
        this.announcer.setAttribute('aria-live', priority);
        this.announcer.textContent = '';
        
        setTimeout(() => {
            this.announcer.textContent = message;
        }, 100);
    }
    
    announceError(message) {
        this.announce(message, 'assertive');
    }
    
    announceSuccess(message) {
        this.announce(message, 'polite');
    }
    
    /**
     * Reduced Motion Support
     */
    setupReducedMotion() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        
        if (prefersReducedMotion.matches) {
            document.body.classList.add('reduce-motion');
        }
        
        prefersReducedMotion.addEventListener('change', (e) => {
            if (e.matches) {
                document.body.classList.add('reduce-motion');
            } else {
                document.body.classList.remove('reduce-motion');
            }
        });
    }
    
    /**
     * Color Contrast Checker
     */
    checkContrast(foreground, background) {
        const getLuminance = (color) => {
            const rgb = this.hexToRgb(color);
            const [r, g, b] = [rgb.r, rgb.g, rgb.b].map(val => {
                val = val / 255;
                return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        
        const l1 = getLuminance(foreground);
        const l2 = getLuminance(background);
        const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
        
        return {
            ratio: ratio.toFixed(2),
            aa: ratio >= 4.5,
            aaa: ratio >= 7,
            aaLarge: ratio >= 3,
            aaaLarge: ratio >= 4.5
        };
    }
    
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }
    
    /**
     * Form Accessibility Enhancements
     */
    enhanceFormAccessibility(form) {
        // Add fieldsets for related fields
        const formGroups = form.querySelectorAll('.form-group');
        
        // Error announcement
        const errors = form.querySelectorAll('.invalid-feedback:not(.d-none)');
        if (errors.length > 0) {
            const errorMessages = Array.from(errors).map(e => e.textContent).join('. ');
            this.announceError(`Form has ${errors.length} error${errors.length > 1 ? 's' : ''}: ${errorMessages}`);
        }
        
        // Success announcement
        form.addEventListener('submit', (e) => {
            if (form.checkValidity()) {
                this.announceSuccess('Form submitted successfully');
            }
        });
    }
    
    /**
     * Table Accessibility
     */
    enhanceTableAccessibility(table) {
        // Add scope to headers
        table.querySelectorAll('thead th').forEach(th => {
            if (!th.getAttribute('scope')) {
                th.setAttribute('scope', 'col');
            }
        });
        
        // Add row headers if applicable
        table.querySelectorAll('tbody tr').forEach(tr => {
            const firstCell = tr.querySelector('td:first-child, th:first-child');
            if (firstCell && firstCell.tagName === 'TH' && !firstCell.getAttribute('scope')) {
                firstCell.setAttribute('scope', 'row');
            }
        });
        
        // Add caption if missing
        if (!table.querySelector('caption')) {
            const caption = document.createElement('caption');
            caption.className = 'sr-only';
            caption.textContent = 'Data table';
            table.insertBefore(caption, table.firstChild);
        }
    }
}

/**
 * Responsive Image Helper
 */
class ResponsiveImages {
    static lazyLoad() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img.lazy').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    static addAltText() {
        document.querySelectorAll('img:not([alt])').forEach(img => {
            console.warn('Image missing alt text:', img.src);
            img.alt = 'Image'; // Fallback
        });
    }
}

/**
 * Mobile Touch Gestures
 */
class TouchGestures {
    constructor(element) {
        this.element = element;
        this.startX = 0;
        this.startY = 0;
        this.distX = 0;
        this.distY = 0;
        this.threshold = 50;
        
        this.element.addEventListener('touchstart', (e) => this.handleStart(e), { passive: true });
        this.element.addEventListener('touchmove', (e) => this.handleMove(e), { passive: true });
        this.element.addEventListener('touchend', (e) => this.handleEnd(e), { passive: true });
    }
    
    handleStart(e) {
        const touch = e.touches[0];
        this.startX = touch.clientX;
        this.startY = touch.clientY;
    }
    
    handleMove(e) {
        if (!this.startX || !this.startY) return;
        
        const touch = e.touches[0];
        this.distX = touch.clientX - this.startX;
        this.distY = touch.clientY - this.startY;
    }
    
    handleEnd(e) {
        if (Math.abs(this.distX) > this.threshold) {
            if (this.distX > 0) {
                this.element.dispatchEvent(new CustomEvent('swiperight'));
            } else {
                this.element.dispatchEvent(new CustomEvent('swipeleft'));
            }
        }
        
        if (Math.abs(this.distY) > this.threshold) {
            if (this.distY > 0) {
                this.element.dispatchEvent(new CustomEvent('swipedown'));
            } else {
                this.element.dispatchEvent(new CustomEvent('swipeup'));
            }
        }
        
        this.startX = 0;
        this.startY = 0;
        this.distX = 0;
        this.distY = 0;
    }
}

/**
 * Performance Optimization
 */
class PerformanceOptimizer {
    static debounce(func, wait) {
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
    
    static throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    static lazyLoadScripts(scripts) {
        scripts.forEach(src => {
            const script = document.createElement('script');
            script.src = src;
            script.defer = true;
            document.body.appendChild(script);
        });
    }
}

/**
 * Initialize on DOM ready
 */
document.addEventListener('DOMContentLoaded', () => {
    // Initialize accessibility
    window.a11yManager = new AccessibilityManager();
    
    // Enhance images
    ResponsiveImages.lazyLoad();
    ResponsiveImages.addAltText();
    
    // Enhance all forms
    document.querySelectorAll('form').forEach(form => {
        window.a11yManager.enhanceFormAccessibility(form);
    });
    
    // Enhance all tables
    document.querySelectorAll('table').forEach(table => {
        window.a11yManager.enhanceTableAccessibility(table);
    });
    
    // Add touch gestures to swipeable elements
    document.querySelectorAll('[data-swipeable]').forEach(element => {
        new TouchGestures(element);
    });
    
    // Optimize scroll events
    const optimizedScroll = PerformanceOptimizer.throttle(() => {
        // Handle scroll events here
        const scrollTop = window.pageYOffset;
        document.dispatchEvent(new CustomEvent('optimizedScroll', { detail: { scrollTop } }));
    }, 100);
    
    window.addEventListener('scroll', optimizedScroll, { passive: true });
    
    // Optimize resize events
    const optimizedResize = PerformanceOptimizer.debounce(() => {
        // Handle resize events here
        document.dispatchEvent(new CustomEvent('optimizedResize'));
    }, 250);
    
    window.addEventListener('resize', optimizedResize);
});

/**
 * Export global helpers
 */
window.announce = (message, priority) => {
    if (window.a11yManager) {
        window.a11yManager.announce(message, priority);
    }
};

window.announceError = (message) => {
    if (window.a11yManager) {
        window.a11yManager.announceError(message);
    }
};

window.announceSuccess = (message) => {
    if (window.a11yManager) {
        window.a11yManager.announceSuccess(message);
    }
};
