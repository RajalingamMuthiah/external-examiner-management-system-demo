/**
 * PDF Export Utilities
 * Functions for triggering PDF downloads from dashboards
 */

window.PDFExport = {
    /**
     * Export current view to PDF
     */
    export: function(type, filters = {}) {
        // Show loading
        Loading.show('Generating PDF...');
        
        // Build URL
        const params = new URLSearchParams({
            type: type,
            format: 'pdf',
            ...filters
        });
        
        const url = `${window.base_url || ''}/api/export_pdf.php?${params.toString()}`;
        
        // Download PDF
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Export failed');
            }
            return response.blob();
        })
        .then(blob => {
            // Create download link
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = `${type}_${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(downloadUrl);
            document.body.removeChild(a);
            
            Loading.hide();
            Toast.success('PDF downloaded successfully!');
        })
        .catch(error => {
            console.error('Export error:', error);
            Loading.hide();
            Toast.error('Failed to generate PDF. Please try again.');
        });
    },
    
    /**
     * Export users list
     */
    exportUsers: function() {
        const filters = {};
        
        // Get filters from page if available
        const statusFilter = document.querySelector('#status_filter');
        const postFilter = document.querySelector('#post_filter');
        
        if (statusFilter && statusFilter.value) {
            filters.status = statusFilter.value;
        }
        if (postFilter && postFilter.value) {
            filters.post = postFilter.value;
        }
        
        this.export('users', filters);
    },
    
    /**
     * Export exams schedule
     */
    exportExams: function() {
        const filters = {};
        
        const collegeFilter = document.querySelector('#college_filter');
        const statusFilter = document.querySelector('#status_filter');
        
        if (collegeFilter && collegeFilter.value) {
            filters.college = collegeFilter.value;
        }
        if (statusFilter && statusFilter.value) {
            filters.status = statusFilter.value;
        }
        
        this.export('exams', filters);
    },
    
    /**
     * Export faculty workload
     */
    exportWorkload: function() {
        const filters = {};
        
        const collegeFilter = document.querySelector('#college_filter');
        if (collegeFilter && collegeFilter.value) {
            filters.college = collegeFilter.value;
        }
        
        this.export('workload', filters);
    },
    
    /**
     * Export analytics report
     */
    exportAnalytics: function() {
        this.export('analytics', {});
    },
    
    /**
     * Add export button to page
     */
    addExportButton: function(container, type, label = 'Export PDF') {
        if (typeof container === 'string') {
            container = document.querySelector(container);
        }
        
        if (!container) {
            console.warn('Export button container not found');
            return;
        }
        
        const btn = document.createElement('button');
        btn.className = 'btn btn-outline-primary btn-sm';
        btn.innerHTML = '<i class="bi bi-file-pdf"></i> ' + label;
        
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            
            switch(type) {
                case 'users':
                    this.exportUsers();
                    break;
                case 'exams':
                    this.exportExams();
                    break;
                case 'workload':
                    this.exportWorkload();
                    break;
                case 'analytics':
                    this.exportAnalytics();
                    break;
                default:
                    this.export(type);
            }
        });
        
        container.appendChild(btn);
    },
    
    /**
     * Initialize export buttons on page
     */
    init: function() {
        // Auto-add export buttons to elements with data-pdf-export attribute
        document.querySelectorAll('[data-pdf-export]').forEach(element => {
            const type = element.getAttribute('data-pdf-export');
            const label = element.getAttribute('data-pdf-label') || 'Export PDF';
            
            const btn = document.createElement('button');
            btn.className = 'btn btn-outline-primary btn-sm';
            btn.innerHTML = '<i class="bi bi-file-pdf"></i> ' + label;
            
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                switch(type) {
                    case 'users':
                        this.exportUsers();
                        break;
                    case 'exams':
                        this.exportExams();
                        break;
                    case 'workload':
                        this.exportWorkload();
                        break;
                    case 'analytics':
                        this.exportAnalytics();
                        break;
                    default:
                        this.export(type);
                }
            });
            
            element.appendChild(btn);
        });
    }
};

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Loading !== 'undefined' && typeof Toast !== 'undefined') {
        PDFExport.init();
    }
});
