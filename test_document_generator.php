<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Generator Test - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Document Generator Test</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> Enter an exam ID or invite ID to generate documents. Documents will open in a new window ready for printing or saving as PDF.
                </div>
                
                <div class="row g-3">
                    <!-- Exam Schedule -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-calendar-event text-primary me-2"></i>Exam Schedule</h5>
                                <p class="card-text">Generate comprehensive exam schedule with examiner assignments, timings, and venue details.</p>
                                <div class="mb-3">
                                    <label class="form-label">Exam ID</label>
                                    <input type="number" class="form-control" id="scheduleExamId" placeholder="Enter exam ID" value="1">
                                </div>
                                <button class="btn btn-primary w-100" onclick="generateDoc('exam_schedule', document.getElementById('scheduleExamId').value)">
                                    <i class="bi bi-download me-2"></i>Generate Schedule
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invitation Letter -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-envelope text-success me-2"></i>Invitation Letter</h5>
                                <p class="card-text">Generate formal invitation letter for external examiners with exam details and official letterhead.</p>
                                <div class="mb-3">
                                    <label class="form-label">Invite ID</label>
                                    <input type="number" class="form-control" id="inviteId" placeholder="Enter invite ID" value="1">
                                </div>
                                <button class="btn btn-success w-100" onclick="generateDoc('invitation_letter', null, document.getElementById('inviteId').value)">
                                    <i class="bi bi-download me-2"></i>Generate Invitation
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Duty Roster -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-list-check text-warning me-2"></i>Duty Roster</h5>
                                <p class="card-text">Generate duty roster showing all assigned examiners with their roles and contact information.</p>
                                <div class="mb-3">
                                    <label class="form-label">Exam ID</label>
                                    <input type="number" class="form-control" id="rosterExamId" placeholder="Enter exam ID" value="1">
                                </div>
                                <button class="btn btn-warning w-100" onclick="generateDoc('duty_roster', document.getElementById('rosterExamId').value)">
                                    <i class="bi bi-download me-2"></i>Generate Roster
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exam Report -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-file-text text-danger me-2"></i>Exam Report</h5>
                                <p class="card-text">Generate comprehensive exam report with summary, approval history, ratings, and statistics.</p>
                                <div class="mb-3">
                                    <label class="form-label">Exam ID</label>
                                    <input type="number" class="form-control" id="reportExamId" placeholder="Enter exam ID" value="1">
                                </div>
                                <button class="btn btn-danger w-100" onclick="generateDoc('exam_report', document.getElementById('reportExamId').value)">
                                    <i class="bi bi-download me-2"></i>Generate Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h5>Document Features:</h5>
                    <ul>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Professional college letterhead</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Complete exam details and timing</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Examiner assignments with contact info</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Signature sections for officials</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Print-optimized layout</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Browser "Save as PDF" compatible</li>
                    </ul>
                </div>
                
                <div class="alert alert-secondary mt-3">
                    <strong>Tip:</strong> After document opens, use your browser's Print function (Ctrl+P) and select "Save as PDF" to download.
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <script>
        function generateDoc(type, examId, inviteId) {
            let url = 'api/generate_document.php?type=' + type;
            
            if (examId) {
                url += '&exam_id=' + examId;
            }
            if (inviteId) {
                url += '&invite_id=' + inviteId;
            }
            
            // Open in new window
            window.open(url, '_blank', 'width=900,height=800,scrollbars=yes,resizable=yes');
        }
    </script>
</body>
</html>
