<?php
/**
 * EXAMINER RATING SYSTEM
 * ===================================
 * Rate examiners after exam completion (1-5 stars)
 * View examiner ratings and history
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_auth();
require_role(['admin', 'principal', 'vice_principal', 'hod', 'teacher'], true);

$currentUserId = get_current_user_id();
$currentUserRole = normalize_role($_SESSION['role'] ?? 'teacher');

// Handle AJAX rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        if ($action === 'submit_rating') {
            $examinerId = (int)($_POST['examiner_id'] ?? 0);
            $examId = (int)($_POST['exam_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            
            if ($examinerId <= 0 || $examId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid examiner or exam']);
                exit;
            }
            
            if ($rating < 1 || $rating > 5) {
                echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars']);
                exit;
            }
            
            // Get current user college and role
            $stmt = $pdo->prepare("SELECT college_id FROM users WHERE id = ?");
            $stmt->execute([$currentUserId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $collegeId = $user['college_id'] ?? null;
            
            if (!$collegeId) {
                echo json_encode(['success' => false, 'message' => 'College information not found']);
                exit;
            }
            
            // Call rateExaminer service function with all 8 required parameters
            $result = rateExaminer($pdo, $examinerId, $examId, $currentUserId, $currentUserRole, $collegeId, (float)$rating, $comment);
            echo json_encode($result);
            exit;
        }
        
        if ($action === 'get_examiner_ratings') {
            $examinerId = (int)($_POST['examiner_id'] ?? 0);
            
            if ($examinerId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid examiner']);
                exit;
            }
            
            // Get rating statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_ratings,
                    AVG(score) as avg_rating,
                    SUM(CASE WHEN score >= 4.5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN score >= 3.5 AND score < 4.5 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN score >= 2.5 AND score < 3.5 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN score >= 1.5 AND score < 2.5 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN score < 1.5 THEN 1 ELSE 0 END) as one_star
                FROM ratings
                WHERE examiner_id = ?
            ");
            $stmt->execute([$examinerId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent ratings with exam details
            $stmt = $pdo->prepare("
                SELECT r.*, e.title as exam_title, u.name as rater_name
                FROM ratings r
                LEFT JOIN exams e ON r.exam_id = e.id
                LEFT JOIN users u ON r.rated_by_user_id = u.id
                WHERE r.examiner_id = ?
                ORDER BY r.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$examinerId]);
            $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'ratings' => $ratings
            ]);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
        
    } catch (Exception $e) {
        error_log('Rating error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Get exam and examiner info from query parameters
$examId = (int)($_GET['exam_id'] ?? 0);
$examinerId = (int)($_GET['examiner_id'] ?? 0);
$exam = null;
$examiner = null;

if ($examId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($examinerId > 0) {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT r.rating_id) as total_ratings,
               AVG(r.score) as avg_rating
        FROM users u
        LEFT JOIN ratings r ON u.id = r.examiner_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$examinerId]);
    $examiner = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get completed exams for current user that need ratings
$completedExamsStmt = $pdo->prepare("
    SELECT DISTINCT
        e.id as exam_id,
        e.title as exam_title,
        e.exam_date,
        e.status,
        a.faculty_id as examiner_id,
        u.name as examiner_name,
        u.email as examiner_email,
        a.role as examiner_role,
        (SELECT COUNT(*) FROM ratings WHERE exam_id = e.id AND examiner_id = a.faculty_id AND rated_by_user_id = ?) as already_rated
    FROM exams e
    INNER JOIN assignments a ON e.id = a.exam_id
    INNER JOIN users u ON a.faculty_id = u.id
    WHERE e.status = 'completed'
    AND e.created_by = ?
    AND e.exam_date < CURDATE()
    ORDER BY e.exam_date DESC
");
$completedExamsStmt->execute([$currentUserId, $currentUserId]);
$completedExams = $completedExamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Examiners - EEMS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .page-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .rating-star {
            font-size: 2rem;
            color: #d1d5db;
            cursor: pointer;
            transition: all 0.2s;
        }
        .rating-star:hover,
        .rating-star.active {
            color: #fbbf24;
            transform: scale(1.1);
        }
        .star-display {
            color: #fbbf24;
        }
        .rating-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .rating-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .progress-bar-rating {
            background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%);
        }
    </style>
</head>
<body>

<!-- Page Header -->
<div class="page-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1"><i class="bi bi-star-fill text-warning me-2"></i>Rate Examiners</h2>
                <p class="text-muted mb-0">Provide feedback on examiner performance</p>
            </div>
            <div>
                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Left Panel: Rate Examiner Form -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-star me-2"></i>Submit Rating</h5>
                </div>
                <div class="card-body">
                    <?php if ($exam && $examiner): ?>
                        <!-- Pre-selected exam and examiner -->
                        <div class="alert alert-info">
                            <strong>Rating for:</strong><br>
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($examiner['name']) ?><br>
                            <i class="bi bi-calendar"></i> <?= htmlspecialchars($exam['title']) ?>
                        </div>
                        
                        <form id="ratingForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="exam_id" value="<?= $examId ?>">
                            <input type="hidden" name="examiner_id" value="<?= $examinerId ?>">
                            
                            <div class="mb-4 text-center">
                                <label class="form-label fw-semibold d-block">Your Rating</label>
                                <div id="starRating" class="d-inline-block">
                                    <i class="bi bi-star rating-star" data-rating="1"></i>
                                    <i class="bi bi-star rating-star" data-rating="2"></i>
                                    <i class="bi bi-star rating-star" data-rating="3"></i>
                                    <i class="bi bi-star rating-star" data-rating="4"></i>
                                    <i class="bi bi-star rating-star" data-rating="5"></i>
                                </div>
                                <input type="hidden" name="rating" id="ratingValue" required>
                                <div id="ratingText" class="text-muted mt-2"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Comments (Optional)</label>
                                <textarea class="form-control" name="comment" rows="4" 
                                          placeholder="Share your experience working with this examiner..."></textarea>
                            </div>

                            <div id="ratingAlert"></div>

                            <button type="submit" class="btn btn-warning w-100 btn-lg">
                                <i class="bi bi-star-fill me-2"></i>Submit Rating
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Select exam and examiner -->
                        <p class="text-muted">Select a completed exam to rate the examiners:</p>
                        
                        <?php if (empty($completedExams)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                No completed exams available for rating yet.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php 
                                $groupedExams = [];
                                foreach ($completedExams as $ce) {
                                    $groupedExams[$ce['exam_id']]['exam'] = $ce;
                                    $groupedExams[$ce['exam_id']]['examiners'][] = $ce;
                                }
                                
                                foreach ($groupedExams as $examData):
                                    $examInfo = $examData['exam'];
                                ?>
                                <div class="list-group-item">
                                    <h6 class="mb-2"><?= htmlspecialchars($examInfo['exam_title']) ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> <?= date('M d, Y', strtotime($examInfo['exam_date'])) ?>
                                    </small>
                                    
                                    <div class="mt-2">
                                        <?php foreach ($examData['examiners'] as $examiner): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                                <div>
                                                    <strong><?= htmlspecialchars($examiner['examiner_name']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($examiner['examiner_role']) ?></small>
                                                </div>
                                                <div>
                                                    <?php if ($examiner['already_rated'] > 0): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle"></i> Rated
                                                        </span>
                                                    <?php else: ?>
                                                        <a href="?exam_id=<?= $examiner['exam_id'] ?>&examiner_id=<?= $examiner['examiner_id'] ?>" 
                                                           class="btn btn-sm btn-warning">
                                                            <i class="bi bi-star"></i> Rate
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Panel: Examiner Profile & Ratings -->
        <div class="col-lg-7 mb-4">
            <?php if ($examiner): ?>
                <!-- Examiner Profile Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="bg-gradient p-4 rounded-circle d-inline-block mb-3" 
                                     style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="bi bi-person-circle text-white" style="font-size: 4rem;"></i>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <h4 class="mb-2"><?= htmlspecialchars($examiner['name']) ?></h4>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-building"></i> <?= htmlspecialchars($examiner['college_name'] ?? 'N/A') ?><br>
                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($examiner['email']) ?>
                                </p>
                                
                                <!-- Rating Display -->
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-3">
                                        <h2 class="mb-0"><?= number_format($examiner['avg_rating'] ?? 0, 1) ?></h2>
                                    </div>
                                    <div>
                                        <?php
                                        $avgRating = $examiner['avg_rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= floor($avgRating)) {
                                                echo '<i class="bi bi-star-fill star-display"></i>';
                                            } elseif ($i <= ceil($avgRating) && $avgRating - floor($avgRating) >= 0.5) {
                                                echo '<i class="bi bi-star-half star-display"></i>';
                                            } else {
                                                echo '<i class="bi bi-star text-muted"></i>';
                                            }
                                        }
                                        ?>
                                        <br>
                                        <small class="text-muted"><?= $examiner['total_ratings'] ?? 0 ?> ratings</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rating Statistics -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Rating Distribution</h6>
                    </div>
                    <div class="card-body" id="ratingDistribution">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Ratings -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Recent Ratings</h6>
                    </div>
                    <div class="card-body" id="recentRatings">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-person-circle" style="font-size: 5rem; color: #d1d5db;"></i>
                        <h5 class="mt-3 text-muted">Select an examiner to view their profile and ratings</h5>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const csrfToken = '<?= $csrfToken ?>';
const examinerId = <?= $examinerId ?: 'null' ?>;

// Star rating interaction
let selectedRating = 0;
const ratingTexts = {
    1: '⭐ Poor',
    2: '⭐⭐ Fair',
    3: '⭐⭐⭐ Good',
    4: '⭐⭐⭐⭐ Very Good',
    5: '⭐⭐⭐⭐⭐ Excellent'
};

document.querySelectorAll('.rating-star').forEach(star => {
    star.addEventListener('click', function() {
        selectedRating = parseInt(this.dataset.rating);
        updateStars(selectedRating);
        document.getElementById('ratingValue').value = selectedRating;
        document.getElementById('ratingText').textContent = ratingTexts[selectedRating];
    });
    
    star.addEventListener('mouseenter', function() {
        updateStars(parseInt(this.dataset.rating));
    });
});

document.getElementById('starRating')?.addEventListener('mouseleave', function() {
    updateStars(selectedRating);
});

function updateStars(rating) {
    document.querySelectorAll('.rating-star').forEach(star => {
        const starRating = parseInt(star.dataset.rating);
        if (starRating <= rating) {
            star.classList.remove('bi-star');
            star.classList.add('bi-star-fill', 'active');
        } else {
            star.classList.remove('bi-star-fill', 'active');
            star.classList.add('bi-star');
        }
    });
}

// Handle rating submission
document.getElementById('ratingForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (selectedRating === 0) {
        document.getElementById('ratingAlert').innerHTML = `
            <div class="alert alert-warning">Please select a rating</div>
        `;
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'submit_rating');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    
    fetch('rate_examiner.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const alertDiv = document.getElementById('ratingAlert');
        if (data.success) {
            alertDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>${data.message}
                </div>
            `;
            setTimeout(() => {
                window.location.href = 'rate_examiner.php';
            }, 2000);
        } else {
            alertDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-2"></i>${data.message}
                </div>
            `;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        document.getElementById('ratingAlert').innerHTML = `
            <div class="alert alert-danger">Network error. Please try again.</div>
        `;
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Load examiner ratings if examiner is selected
if (examinerId) {
    loadExaminerRatings(examinerId);
}

function loadExaminerRatings(examinerId) {
    const formData = new FormData();
    formData.append('action', 'get_examiner_ratings');
    formData.append('examiner_id', examinerId);
    formData.append('csrf_token', csrfToken);
    
    fetch('rate_examiner.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            renderRatingDistribution(data.stats);
            renderRecentRatings(data.ratings);
        }
    })
    .catch(err => console.error(err));
}

function renderRatingDistribution(stats) {
    const total = stats.total_ratings || 1;
    const html = `
        ${[5, 4, 3, 2, 1].map(star => `
            <div class="d-flex align-items-center mb-2">
                <div style="width: 60px;">${star} <i class="bi bi-star-fill text-warning"></i></div>
                <div class="progress flex-grow-1 mx-3" style="height: 20px;">
                    <div class="progress-bar progress-bar-rating" style="width: ${(stats[star === 5 ? 'five_star' : star === 4 ? 'four_star' : star === 3 ? 'three_star' : star === 2 ? 'two_star' : 'one_star'] / total * 100)}%"></div>
                </div>
                <div style="width: 50px;" class="text-end">${stats[star === 5 ? 'five_star' : star === 4 ? 'four_star' : star === 3 ? 'three_star' : star === 2 ? 'two_star' : 'one_star'] || 0}</div>
            </div>
        `).join('')}
    `;
    document.getElementById('ratingDistribution').innerHTML = html;
}

function renderRecentRatings(ratings) {
    if (ratings.length === 0) {
        document.getElementById('recentRatings').innerHTML = `
            <p class="text-muted text-center">No ratings yet</p>
        `;
        return;
    }
    
    const html = ratings.map(r => `
        <div class="border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    ${Array(r.rating).fill('<i class="bi bi-star-fill star-display"></i>').join('')}
                    ${Array(5 - r.rating).fill('<i class="bi bi-star text-muted"></i>').join('')}
                </div>
                <small class="text-muted">${new Date(r.created_at).toLocaleDateString()}</small>
            </div>
            <p class="mb-1"><strong>${escapeHtml(r.exam_title || 'N/A')}</strong></p>
            ${r.comment ? `<p class="text-muted small mb-0">"${escapeHtml(r.comment)}"</p>` : ''}
        </div>
    `).join('');
    
    document.getElementById('recentRatings').innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>
