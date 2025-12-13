/**
 * EEMS Mobile Utilities
 * Mobile-specific enhancements and optimizations
 * Version: 1.0
 */

class MobileUtils {
    constructor() {
        this.isMobile = this.detectMobile();
        this.isTouch = this.detectTouch();
        this.init();
    }
    
    init() {
        if (this.isMobile) {
            document.body.classList.add('is-mobile');
            this.setupMobileOptimizations();
        }
        
        if (this.isTouch) {
            document.body.classList.add('is-touch');
            this.setupTouchOptimizations();
        }
        
        this.setupViewportFixes();
        this.setupMobileMenu();
        this.setupMobileTables();
        this.setupMobileModals();
        this.setupPullToRefresh();
    }
    
    detectMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
               window.innerWidth < 768;
    }
    
    detectTouch() {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }
    
    /**
     * Mobile-specific optimizations
     */
    setupMobileOptimizations() {
        // Prevent zoom on double tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, { passive: false });
        
        // Faster click events
        this.setupFastClick();
        
        // Optimize font rendering
        document.body.style.webkitFontSmoothing = 'antialiased';
        document.body.style.mozOsxFontSmoothing = 'grayscale';
    }
    
    setupFastClick() {
        // Modern browsers don't need this, but for older iOS devices
        document.addEventListener('touchstart', function() {}, { passive: true });
    }
    
    setupTouchOptimizations() {
        // Add touch-friendly hover effects
        document.querySelectorAll('a, button, .btn').forEach(element => {
            element.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            }, { passive: true });
            
            element.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.classList.remove('touch-active');
                }, 150);
            }, { passive: true });
        });
    }
    
    /**
     * Viewport fixes for mobile browsers
     */
    setupViewportFixes() {
        // Fix viewport height on mobile (accounts for address bar)
        const setVH = () => {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        };
        
        setVH();
        window.addEventListener('resize', setVH);
        window.addEventListener('orientationchange', setVH);
    }
    
    /**
     * Mobile menu enhancements
     */
    setupMobileMenu() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;
        
        const toggler = navbar.querySelector('.navbar-toggler');
        const collapse = navbar.querySelector('.navbar-collapse');
        
        if (toggler && collapse) {
            // Close menu on link click
            collapse.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (collapse.classList.contains('show')) {
                        toggler.click();
                    }
                });
            });
            
            // Close menu on outside click
            document.addEventListener('click', (e) => {
                if (!navbar.contains(e.target) && collapse.classList.contains('show')) {
                    toggler.click();
                }
            });
            
            // Prevent body scroll when menu is open
            toggler.addEventListener('click', () => {
                setTimeout(() => {
                    if (collapse.classList.contains('show')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                }, 350);
            });
        }
    }
    
    /**
     * Convert tables to mobile cards
     */
    setupMobileTables() {
        if (!this.isMobile) return;
        
        document.querySelectorAll('table.mobile-responsive').forEach(table => {
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const card = document.createElement('div');
                card.className = 'table-mobile-card';
                
                cells.forEach((cell, index) => {
                    const item = document.createElement('div');
                    item.className = 'mobile-card-item';
                    item.innerHTML = `
                        <span class="card-label">${headers[index] || ''}:</span>
                        <span class="card-value">${cell.innerHTML}</span>
                    `;
                    card.appendChild(item);
                    
                    if (index < cells.length - 1) {
                        card.appendChild(document.createElement('hr'));
                    }
                });
                
                row.parentNode.insertBefore(card, row);
                row.style.display = 'none';
            });
            
            table.querySelector('thead').style.display = 'none';
        });
    }
    
    /**
     * Mobile modal optimizations
     */
    setupMobileModals() {
        if (!this.isMobile) return;
        
        document.addEventListener('show.bs.modal', (e) => {
            const modal = e.target;
            
            // Make modal full screen on mobile
            modal.querySelector('.modal-dialog')?.classList.add('modal-fullscreen-sm-down');
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
            
            // Add swipe to close
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                let startY = 0;
                let currentY = 0;
                
                modalContent.addEventListener('touchstart', (e) => {
                    startY = e.touches[0].clientY;
                }, { passive: true });
                
                modalContent.addEventListener('touchmove', (e) => {
                    currentY = e.touches[0].clientY;
                    const diff = currentY - startY;
                    
                    if (diff > 0 && modalContent.scrollTop === 0) {
                        modalContent.style.transform = `translateY(${diff}px)`;
                    }
                }, { passive: true });
                
                modalContent.addEventListener('touchend', () => {
                    const diff = currentY - startY;
                    
                    if (diff > 100) {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) bsModal.hide();
                    }
                    
                    modalContent.style.transform = '';
                    startY = 0;
                    currentY = 0;
                }, { passive: true });
            }
        });
        
        document.addEventListener('hidden.bs.modal', () => {
            document.body.style.overflow = '';
        });
    }
    
    /**
     * Pull to refresh functionality
     */
    setupPullToRefresh() {
        if (!this.isTouch) return;
        
        let startY = 0;
        let currentY = 0;
        let isPulling = false;
        
        const refreshIndicator = this.createRefreshIndicator();
        
        document.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
                isPulling = true;
            }
        }, { passive: true });
        
        document.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            
            currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            
            if (diff > 0 && diff < 150) {
                refreshIndicator.style.transform = `translateY(${diff}px)`;
                refreshIndicator.style.opacity = diff / 150;
            }
        }, { passive: true });
        
        document.addEventListener('touchend', () => {
            if (!isPulling) return;
            
            const diff = currentY - startY;
            
            if (diff > 80) {
                this.triggerRefresh(refreshIndicator);
            } else {
                refreshIndicator.style.transform = '';
                refreshIndicator.style.opacity = '0';
            }
            
            isPulling = false;
            startY = 0;
            currentY = 0;
        }, { passive: true });
    }
    
    createRefreshIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'pull-to-refresh-indicator';
        indicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
        indicator.style.cssText = `
            position: fixed;
            top: -50px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s;
        `;
        document.body.appendChild(indicator);
        return indicator;
    }
    
    triggerRefresh(indicator) {
        indicator.classList.add('refreshing');
        indicator.style.transform = 'translateY(20px)';
        indicator.querySelector('i').style.animation = 'spin 1s linear infinite';
        
        // Dispatch refresh event
        document.dispatchEvent(new CustomEvent('pulltorefresh'));
        
        // Auto-hide after 2 seconds
        setTimeout(() => {
            indicator.style.transform = '';
            indicator.style.opacity = '0';
            indicator.classList.remove('refreshing');
        }, 2000);
    }
    
    /**
     * Optimize images for mobile
     */
    optimizeImages() {
        document.querySelectorAll('img[data-src-mobile]').forEach(img => {
            if (this.isMobile) {
                img.src = img.dataset.srcMobile;
            }
        });
    }
    
    /**
     * Bottom sheet component
     */
    createBottomSheet(title, content) {
        const sheet = document.createElement('div');
        sheet.className = 'bottom-sheet';
        sheet.innerHTML = `
            <div class="bottom-sheet-backdrop"></div>
            <div class="bottom-sheet-content">
                <div class="bottom-sheet-handle"></div>
                <div class="bottom-sheet-header">
                    <h5>${title}</h5>
                    <button class="btn-close" aria-label="Close"></button>
                </div>
                <div class="bottom-sheet-body">
                    ${content}
                </div>
            </div>
        `;
        
        sheet.style.cssText = `
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            display: none;
        `;
        
        const backdrop = sheet.querySelector('.bottom-sheet-backdrop');
        backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
        `;
        
        const sheetContent = sheet.querySelector('.bottom-sheet-content');
        sheetContent.style.cssText = `
            position: relative;
            background: white;
            border-radius: 20px 20px 0 0;
            padding: 1.5rem;
            transform: translateY(100%);
            transition: transform 0.3s;
            max-height: 80vh;
            overflow-y: auto;
        `;
        
        // Close handlers
        const close = () => {
            sheetContent.style.transform = 'translateY(100%)';
            setTimeout(() => {
                sheet.style.display = 'none';
                sheet.remove();
            }, 300);
        };
        
        backdrop.addEventListener('click', close);
        sheet.querySelector('.btn-close').addEventListener('click', close);
        
        // Swipe to close
        let startY = 0;
        sheetContent.addEventListener('touchstart', (e) => {
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        sheetContent.addEventListener('touchmove', (e) => {
            const currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            
            if (diff > 0 && sheetContent.scrollTop === 0) {
                sheetContent.style.transform = `translateY(${diff}px)`;
            }
        }, { passive: true });
        
        sheetContent.addEventListener('touchend', (e) => {
            const currentY = e.changedTouches[0].clientY;
            const diff = currentY - startY;
            
            if (diff > 100) {
                close();
            } else {
                sheetContent.style.transform = 'translateY(0)';
            }
        }, { passive: true });
        
        document.body.appendChild(sheet);
        
        // Show sheet
        setTimeout(() => {
            sheet.style.display = 'block';
            setTimeout(() => {
                sheetContent.style.transform = 'translateY(0)';
            }, 10);
        }, 10);
        
        return sheet;
    }
    
    /**
     * Vibration feedback
     */
    vibrate(pattern = 10) {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }
    
    /**
     * Share API
     */
    async share(data) {
        if (navigator.share) {
            try {
                await navigator.share(data);
                return true;
            } catch (err) {
                console.error('Share failed:', err);
                return false;
            }
        }
        return false;
    }
}

/**
 * PWA Utilities
 */
class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.init();
    }
    
    init() {
        this.setupInstallPrompt();
        this.setupOfflineDetection();
        this.setupServiceWorker();
    }
    
    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
        });
    }
    
    showInstallButton() {
        const button = document.getElementById('install-app-button');
        if (button) {
            button.style.display = 'block';
            button.addEventListener('click', () => this.promptInstall());
        }
    }
    
    async promptInstall() {
        if (!this.deferredPrompt) return;
        
        this.deferredPrompt.prompt();
        const result = await this.deferredPrompt.userChoice;
        
        if (result.outcome === 'accepted') {
            console.log('App installed');
        }
        
        this.deferredPrompt = null;
    }
    
    setupOfflineDetection() {
        window.addEventListener('online', () => {
            this.showToast('You are back online', 'success');
        });
        
        window.addEventListener('offline', () => {
            this.showToast('You are offline', 'warning');
        });
    }
    
    setupServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.error('Service Worker registration failed:', err));
        }
    }
    
    showToast(message, type) {
        // Use existing toast function
        if (typeof showToast === 'function') {
            showToast(type, message);
        }
    }
}

/**
 * Initialize on DOM ready
 */
document.addEventListener('DOMContentLoaded', () => {
    window.mobileUtils = new MobileUtils();
    window.pwaManager = new PWAManager();
});

/**
 * Export utilities
 */
window.MobileUtils = MobileUtils;
window.PWAManager = PWAManager;
