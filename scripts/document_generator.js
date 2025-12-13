/**
 * EEMS Document Generation Utilities
 * ===================================
 * JavaScript helpers for generating PDF documents from exam data
 */

/**
 * Generate document and open in new window
 * @param {string} type - Document type (exam_schedule, invitation_letter, duty_roster, exam_report)
 * @param {number} examId - Exam ID (required for most documents)
 * @param {number} inviteId - Invite ID (required for invitation letters)
 */
function generateDocument(type, examId, inviteId) {
    if (!type) {
        alert('Document type is required');
        return;
    }
    
    let url = 'api/generate_document.php?type=' + encodeURIComponent(type);
    
    if (examId) {
        url += '&exam_id=' + parseInt(examId);
    }
    
    if (inviteId) {
        url += '&invite_id=' + parseInt(inviteId);
    }
    
    // Open in new window optimized for printing
    const printWindow = window.open(
        url,
        'EEMS_Document',
        'width=900,height=800,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no'
    );
    
    if (!printWindow) {
        alert('Please allow popups for this site to generate documents');
    }
}

/**
 * Show document options dropdown for an exam
 * @param {number} examId - Exam ID
 */
function showDocumentMenu(examId) {
    const menu = `
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-file-earmark-pdf"></i> Documents
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="generateDocument('exam_schedule', ${examId}); return false;">
                    <i class="bi bi-calendar-event me-2"></i>Exam Schedule
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="generateDocument('duty_roster', ${examId}); return false;">
                    <i class="bi bi-list-check me-2"></i>Duty Roster
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="generateDocument('exam_report', ${examId}); return false;">
                    <i class="bi bi-file-text me-2"></i>Exam Report
                </a></li>
            </ul>
        </div>
    `;
    return menu;
}

/**
 * Download all documents for an exam as a batch
 * @param {number} examId - Exam ID
 */
function downloadAllDocuments(examId) {
    const types = ['exam_schedule', 'duty_roster', 'exam_report'];
    
    if (confirm('This will open 3 documents in separate windows. Continue?')) {
        types.forEach((type, index) => {
            setTimeout(() => {
                generateDocument(type, examId);
            }, index * 500); // Stagger by 500ms to avoid popup blocker
        });
    }
}

/**
 * Generate invitation letter for specific invite
 * @param {number} inviteId - Invite ID
 */
function generateInvitationLetter(inviteId) {
    generateDocument('invitation_letter', null, inviteId);
}

/**
 * Add download buttons to exam cards dynamically
 * Call this after DOM is loaded
 */
function addDocumentButtonsToExamCards() {
    const examCards = document.querySelectorAll('[data-exam-id]');
    
    examCards.forEach(card => {
        const examId = card.getAttribute('data-exam-id');
        
        // Check if button already exists
        if (card.querySelector('.doc-download-btn')) {
            return;
        }
        
        // Create button group
        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group btn-group-sm doc-download-btn';
        btnGroup.innerHTML = `
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-download"></i> Download
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="generateDocument('exam_schedule', ${examId}); return false;">
                    <i class="bi bi-calendar-event me-2"></i>Schedule
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="generateDocument('duty_roster', ${examId}); return false;">
                    <i class="bi bi-list-check me-2"></i>Roster
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="generateDocument('exam_report', ${examId}); return false;">
                    <i class="bi bi-file-text me-2"></i>Report
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="downloadAllDocuments(${examId}); return false;">
                    <i class="bi bi-files me-2"></i>Download All
                </a></li>
            </ul>
        `;
        
        // Find action button container and append
        const actionContainer = card.querySelector('.exam-actions, .card-footer, .btn-group');
        if (actionContainer) {
            actionContainer.appendChild(btnGroup);
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addDocumentButtonsToExamCards);
} else {
    addDocumentButtonsToExamCards();
}
