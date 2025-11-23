/**
 * Global Search Component
 * Keyboard shortcut: Ctrl+K or Cmd+K
 * Searches across exams, users, assignments
 */

window.GlobalSearch = {
    searchModal: null,
    searchInput: null,
    searchResults: null,
    searchTimeout: null,
    currentIndex: -1,
    
    /**
     * Initialize global search
     */
    init: function() {
        this.createSearchModal();
        this.bindKeyboardShortcut();
        this.bindEvents();
    },
    
    /**
     * Create search modal HTML
     */
    createSearchModal: function() {
        const modalHTML = `
            <div class="modal fade" id="globalSearchModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-body p-0">
                            <div class="search-header">
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control border-0 shadow-none" 
                                           id="globalSearchInput" 
                                           placeholder="Search exams, users, assignments... (Ctrl+K)"
                                           autocomplete="off">
                                    <button class="btn border-0" type="button" data-bs-dismiss="modal">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="search-results" id="globalSearchResults">
                                <div class="search-empty">
                                    <i class="bi bi-search"></i>
                                    <p>Start typing to search...</p>
                                    <div class="search-shortcuts">
                                        <span class="shortcut-hint"><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                                        <span class="shortcut-hint"><kbd>Enter</kbd> Open</span>
                                        <span class="shortcut-hint"><kbd>Esc</kbd> Close</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add to body
        const div = document.createElement('div');
        div.innerHTML = modalHTML;
        document.body.appendChild(div.firstElementChild);
        
        this.searchModal = new bootstrap.Modal(document.getElementById('globalSearchModal'));
        this.searchInput = document.getElementById('globalSearchInput');
        this.searchResults = document.getElementById('globalSearchResults');
    },
    
    /**
     * Bind keyboard shortcut (Ctrl+K)
     */
    bindKeyboardShortcut: function() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K or Cmd+K
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.show();
            }
            
            // ESC to close
            if (e.key === 'Escape' && this.searchModal._isShown) {
                this.hide();
            }
        });
    },
    
    /**
     * Bind search events
     */
    bindEvents: function() {
        // Search on input
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                this.showEmptyState();
                return;
            }
            
            // Debounce search
            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 300);
        });
        
        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            const items = this.searchResults.querySelectorAll('.search-result-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.currentIndex = Math.min(this.currentIndex + 1, items.length - 1);
                this.highlightItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.currentIndex = Math.max(this.currentIndex - 1, 0);
                this.highlightItem(items);
            } else if (e.key === 'Enter' && this.currentIndex >= 0) {
                e.preventDefault();
                items[this.currentIndex].click();
            }
        });
        
        // Focus input when modal shown
        document.getElementById('globalSearchModal').addEventListener('shown.bs.modal', () => {
            this.searchInput.focus();
        });
        
        // Reset on modal hide
        document.getElementById('globalSearchModal').addEventListener('hidden.bs.modal', () => {
            this.searchInput.value = '';
            this.currentIndex = -1;
            this.showEmptyState();
        });
    },
    
    /**
     * Perform search via AJAX
     */
    performSearch: function(query) {
        this.searchResults.innerHTML = '<div class="search-loading"><div class="spinner-border spinner-border-sm"></div> Searching...</div>';
        
        Ajax.get('api/global_search.php', { q: query })
            .then(data => {
                this.displayResults(data, query);
            })
            .catch(error => {
                console.error('Search error:', error);
                this.searchResults.innerHTML = '<div class="search-error"><i class="bi bi-exclamation-triangle"></i> Search failed. Please try again.</div>';
            });
    },
    
    /**
     * Display search results
     */
    displayResults: function(data, query) {
        this.currentIndex = -1;
        
        if (!data.exams.length && !data.users.length && !data.assignments.length) {
            this.searchResults.innerHTML = `
                <div class="search-empty">
                    <i class="bi bi-inbox"></i>
                    <p>No results found for "${this.escapeHtml(query)}"</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        
        // Exams section
        if (data.exams.length > 0) {
            html += '<div class="search-section">';
            html += '<div class="search-section-title"><i class="bi bi-calendar-check"></i> Exams</div>';
            data.exams.forEach(exam => {
                html += this.renderExamItem(exam, query);
            });
            html += '</div>';
        }
        
        // Users section
        if (data.users.length > 0) {
            html += '<div class="search-section">';
            html += '<div class="search-section-title"><i class="bi bi-people"></i> Users</div>';
            data.users.forEach(user => {
                html += this.renderUserItem(user, query);
            });
            html += '</div>';
        }
        
        // Assignments section
        if (data.assignments.length > 0) {
            html += '<div class="search-section">';
            html += '<div class="search-section-title"><i class="bi bi-clipboard-check"></i> Assignments</div>';
            data.assignments.forEach(assignment => {
                html += this.renderAssignmentItem(assignment, query);
            });
            html += '</div>';
        }
        
        this.searchResults.innerHTML = html;
    },
    
    /**
     * Render exam result item
     */
    renderExamItem: function(exam, query) {
        const highlighted = this.highlightText(exam.exam_name || exam.title, query);
        const statusClass = exam.status === 'Approved' ? 'success' : exam.status === 'Pending' ? 'warning' : 'danger';
        
        return `
            <div class="search-result-item" onclick="GlobalSearch.openExam(${exam.exam_id})">
                <div class="search-item-content">
                    <div class="search-item-title">${highlighted}</div>
                    <div class="search-item-meta">
                        <span class="badge bg-${statusClass}">${exam.status}</span>
                        <span>${exam.subject || 'N/A'}</span>
                        <span>${new Date(exam.exam_date).toLocaleDateString()}</span>
                    </div>
                    <div class="search-item-description">${exam.department || exam.college_name || ''}</div>
                </div>
                <i class="bi bi-arrow-right"></i>
            </div>
        `;
    },
    
    /**
     * Render user result item
     */
    renderUserItem: function(user, query) {
        const highlighted = this.highlightText(user.name, query);
        const roleClass = user.post === 'admin' ? 'danger' : user.post === 'vice_principal' ? 'primary' : user.post === 'hod' ? 'info' : 'secondary';
        
        return `
            <div class="search-result-item" onclick="GlobalSearch.openUser(${user.user_id})">
                <div class="search-item-content">
                    <div class="search-item-title">${highlighted}</div>
                    <div class="search-item-meta">
                        <span class="badge bg-${roleClass}">${user.post}</span>
                        <span>${user.email}</span>
                    </div>
                    <div class="search-item-description">${user.college_name || ''}</div>
                </div>
                <i class="bi bi-arrow-right"></i>
            </div>
        `;
    },
    
    /**
     * Render assignment result item
     */
    renderAssignmentItem: function(assignment, query) {
        const highlighted = this.highlightText(assignment.exam_name, query);
        
        return `
            <div class="search-result-item" onclick="GlobalSearch.openAssignment(${assignment.assignment_id})">
                <div class="search-item-content">
                    <div class="search-item-title">${highlighted}</div>
                    <div class="search-item-meta">
                        <span>Faculty: ${assignment.faculty_name}</span>
                        <span>${new Date(assignment.exam_date).toLocaleDateString()}</span>
                    </div>
                </div>
                <i class="bi bi-arrow-right"></i>
            </div>
        `;
    },
    
    /**
     * Highlight search term in text
     */
    highlightText: function(text, query) {
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return this.escapeHtml(text).replace(regex, '<mark>$1</mark>');
    },
    
    /**
     * Highlight selected item
     */
    highlightItem: function(items) {
        items.forEach((item, index) => {
            if (index === this.currentIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    },
    
    /**
     * Show empty state
     */
    showEmptyState: function() {
        this.searchResults.innerHTML = `
            <div class="search-empty">
                <i class="bi bi-search"></i>
                <p>Start typing to search...</p>
                <div class="search-shortcuts">
                    <span class="shortcut-hint"><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                    <span class="shortcut-hint"><kbd>Enter</kbd> Open</span>
                    <span class="shortcut-hint"><kbd>Esc</kbd> Close</span>
                </div>
            </div>
        `;
    },
    
    /**
     * Show search modal
     */
    show: function() {
        this.searchModal.show();
    },
    
    /**
     * Hide search modal
     */
    hide: function() {
        this.searchModal.hide();
    },
    
    /**
     * Navigate to exam details
     */
    openExam: function(examId) {
        this.hide();
        window.location.href = `view_exam_details.php?exam_id=${examId}`;
    },
    
    /**
     * Navigate to user profile
     */
    openUser: function(userId) {
        this.hide();
        window.location.href = `manage_users.php?user_id=${userId}`;
    },
    
    /**
     * Navigate to assignment
     */
    openAssignment: function(assignmentId) {
        this.hide();
        window.location.href = `dashboard.php?assignment=${assignmentId}`;
    },
    
    /**
     * Escape HTML
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Escape regex special characters
     */
    escapeRegex: function(text) {
        return text.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
    }
};

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined') {
        GlobalSearch.init();
    }
});
