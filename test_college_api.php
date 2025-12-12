<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test College API - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">College API Test Page</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Test: Get Colleges</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary" onclick="testGetColleges()">
                            <i class="fas fa-play"></i> Test Get Colleges
                        </button>
                        <pre id="colleges-result" class="mt-3 bg-light p-3 border" style="max-height: 300px; overflow-y: auto;"></pre>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Test: Get Departments</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label>College ID:</label>
                            <input type="number" id="dept-college-id" class="form-control" value="1" placeholder="Enter college ID">
                        </div>
                        <button class="btn btn-success" onclick="testGetDepartments()">
                            <i class="fas fa-play"></i> Test Get Departments
                        </button>
                        <pre id="departments-result" class="mt-3 bg-light p-3 border" style="max-height: 300px; overflow-y: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Live College & Department Selector (Like User Profile)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="test-college" class="form-label">Select College</label>
                                <select class="form-select" id="test-college">
                                    <option value="">Loading colleges...</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="test-department" class="form-label">Select Department</label>
                                <select class="form-select" id="test-department" disabled>
                                    <option value="">Select college first...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info" id="selection-info">
                            <strong>Selected:</strong> <span id="selected-text">Nothing selected yet</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">API Endpoints</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item">
                                <strong>GET</strong> <code>/api/colleges.php?action=get_colleges</code>
                                <span class="badge bg-primary">Get all colleges</span>
                            </li>
                            <li class="list-group-item">
                                <strong>GET</strong> <code>/api/colleges.php?action=get_departments&college_id=1</code>
                                <span class="badge bg-success">Get departments for college</span>
                            </li>
                            <li class="list-group-item">
                                <strong>POST</strong> <code>/api/colleges.php</code> with <code>action=add_college&name=...</code>
                                <span class="badge bg-warning">Add new college (admin only)</span>
                            </li>
                            <li class="list-group-item">
                                <strong>POST</strong> <code>/api/colleges.php</code> with <code>action=add_department&college_id=...&name=...</code>
                                <span class="badge bg-info">Add new department (admin/principal)</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <script>
        // Test Get Colleges
        function testGetColleges() {
            const resultDiv = document.getElementById('colleges-result');
            resultDiv.textContent = 'Loading...';
            
            fetch('api/colleges.php?action=get_colleges')
                .then(response => response.json())
                .then(data => {
                    resultDiv.textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    resultDiv.textContent = 'Error: ' + error.message;
                });
        }
        
        // Test Get Departments
        function testGetDepartments() {
            const collegeId = document.getElementById('dept-college-id').value;
            const resultDiv = document.getElementById('departments-result');
            resultDiv.textContent = 'Loading...';
            
            fetch(`api/colleges.php?action=get_departments&college_id=${collegeId}`)
                .then(response => response.json())
                .then(data => {
                    resultDiv.textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    resultDiv.textContent = 'Error: ' + error.message;
                });
        }
        
        // Live selector - Load colleges on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCollegesLive();
            
            // Add change event for college dropdown
            document.getElementById('test-college').addEventListener('change', function() {
                const collegeId = this.value;
                const collegeName = this.options[this.selectedIndex].text;
                
                if (collegeId) {
                    loadDepartmentsLive(collegeId);
                    document.getElementById('selected-text').textContent = `College: ${collegeName}`;
                } else {
                    const deptSelect = document.getElementById('test-department');
                    deptSelect.disabled = true;
                    deptSelect.innerHTML = '<option value="">Select college first...</option>';
                    document.getElementById('selected-text').textContent = 'Nothing selected yet';
                }
            });
            
            // Add change event for department dropdown
            document.getElementById('test-department').addEventListener('change', function() {
                const collegeSelect = document.getElementById('test-college');
                const collegeName = collegeSelect.options[collegeSelect.selectedIndex].text;
                const deptName = this.options[this.selectedIndex].text;
                
                if (this.value) {
                    document.getElementById('selected-text').textContent = 
                        `College: ${collegeName}, Department: ${deptName}`;
                }
            });
        });
        
        // Load colleges for live selector
        function loadCollegesLive() {
            const select = document.getElementById('test-college');
            select.innerHTML = '<option value="">Loading...</option>';
            
            fetch('api/colleges.php?action=get_colleges')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        select.innerHTML = '<option value="">Select your college...</option>';
                        
                        data.colleges.forEach(college => {
                            const option = document.createElement('option');
                            option.value = college.id;
                            option.textContent = `${college.name} (${college.department_count} depts, ${college.user_count} users)`;
                            select.appendChild(option);
                        });
                    } else {
                        select.innerHTML = '<option value="">Error loading colleges</option>';
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => {
                    select.innerHTML = '<option value="">Error loading colleges</option>';
                    console.error('Error:', error);
                });
        }
        
        // Load departments for live selector
        function loadDepartmentsLive(collegeId) {
            const select = document.getElementById('test-department');
            select.disabled = true;
            select.innerHTML = '<option value="">Loading...</option>';
            
            fetch(`api/colleges.php?action=get_departments&college_id=${collegeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        select.innerHTML = '<option value="">Select your department...</option>';
                        
                        data.departments.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = `${dept.name} (${dept.user_count} users)`;
                            select.appendChild(option);
                        });
                        
                        select.disabled = false;
                    } else {
                        select.innerHTML = '<option value="">Error loading departments</option>';
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => {
                    select.innerHTML = '<option value="">Error loading departments</option>';
                    console.error('Error:', error);
                });
        }
    </script>
</body>
</html>
