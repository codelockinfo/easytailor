<?php
/**
 * Contact Messages Page
 * Site Admin - View all contact form submissions
 */

require_once '../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    header('Location: ../admin/login.php');
    exit;
}

// Connect to database
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get all contact messages
$query = "SELECT * FROM user_contact ORDER BY created_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$totalMessages = count($messages);
$loggedInMessages = count(array_filter($messages, fn($m) => $m['user_logged'] == 1));
$guestMessages = $totalMessages - $loggedInMessages;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Site Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            box-shadow: 10px 0 25px rgba(79, 70, 229, 0.2);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .brand {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-logo {
            height: 100px;
            width: auto;
            object-fit: contain;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            border-radius: 12px;
            color: rgba(255,255,255,0.85);
            margin-bottom: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .nav-link.active, .nav-link:hover {
            background: rgba(255,255,255,0.18);
            color: white;
        }
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
        }
        .page-header {
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }
        .badge-user {
            background-color: #28a745;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-guest {
            background-color: #6c757d;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .modal-header.bg-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        .modal-body .section-title {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.75rem;
            margin-top: 1rem;
        }
        .modal-body .section-title:first-child {
            margin-top: 0;
        }
        .response-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <img src="../uploads/logos/main-logo.png" alt="TailorPro" class="brand-logo" onerror="this.style.display='none';">
            </div>
            <nav>
                <a href="index.php?section=requests" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    Email Requests
                </a>
                <a href="index.php?section=company" class="nav-link">
                    <i class="fas fa-building"></i>
                    Company Snapshot
                </a>
                <a href="contact_messages.php" class="nav-link active">
                    <i class="fas fa-comments"></i>
                    Contact Messages
                </a>
            </nav>
            <div class="mt-auto">
                <a href="../admin/logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h2 class="mb-1">Contact Messages</h2>
                <p class="text-muted mb-0">View all contact form submissions from users</p>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalMessages; ?></div>
                        <div class="stat-label">Total Messages</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $loggedInMessages; ?></div>
                        <div class="stat-label">From Logged In Users</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $guestMessages; ?></div>
                        <div class="stat-label">From Guests</div>
                    </div>
                </div>
            </div>

            <!-- Messages Table -->
            <div class="table-container">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No contact messages found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $msg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($msg['id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($msg['name']); ?></strong></td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($msg['emailId']); ?>">
                                                <?php echo htmlspecialchars($msg['emailId']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewMessage(<?php echo htmlspecialchars(json_encode($msg)); ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Message Detail Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="messageModalLabel"><i class="fas fa-comments me-2"></i>Contact Message Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="messageDetails">
                        <!-- Details will be populated by JavaScript -->
                    </div>
                    
                    <div class="response-section">
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-reply me-2"></i>Send Response</h6>
                        <form id="responseForm">
                            <input type="hidden" id="responseMessageId" name="message_id">
                            <input type="hidden" id="responseEmail" name="email">
                            <input type="hidden" id="responseName" name="name">
                            
                            <div class="mb-3">
                                <label for="responseSubject" class="form-label fw-bold text-muted">Subject</label>
                                <input type="text" class="form-control" id="responseSubject" name="subject" value="Re: Your Contact Form Submission" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="responseMessage" class="form-label fw-bold text-muted">Response Message</label>
                                <textarea class="form-control" id="responseMessage" name="message" rows="6" required placeholder="Type your response here..."></textarea>
                            </div>
                            
                            <div id="responseAlert" class="alert d-none" role="alert"></div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="responseForm" class="btn btn-primary" id="sendResponseBtn">
                        <i class="fas fa-paper-plane me-2"></i>Send Response
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let messageModal;
        
        document.addEventListener('DOMContentLoaded', function() {
            messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
        });
        
        function viewMessage(msg) {
            const userTypeClass = msg.user_logged == 1 ? 'success' : 'secondary';
            const userTypeText = msg.user_logged == 1 ? 'Logged In' : 'Guest';
            
            // Populate details with theme matching structure
            const detailsHtml = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Message ID</label>
                        <p class="mb-0">#${msg.id}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">User Type</label>
                        <p class="mb-0">
                            <span class="badge bg-${userTypeClass}">${userTypeText}</span>
                        </p>
                    </div>
                    ${msg.user_id ? `
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">User ID</label>
                        <p class="mb-0">#${msg.user_id}</p>
                    </div>
                    ` : ''}
                    <div class="col-12 mb-2">
                        <hr>
                        <h6 class="fw-bold text-muted mb-2"><i class="fas fa-user me-2"></i>Contact Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Full Name</label>
                                <p class="mb-0 small">${escapeHtml(msg.name)}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Email</label>
                                <p class="mb-0 small">
                                    <a href="mailto:${escapeHtml(msg.emailId)}">
                                        <i class="fas fa-envelope me-1"></i>${escapeHtml(msg.emailId)}
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mb-2">
                        <hr>
                        <h6 class="fw-bold text-muted mb-2"><i class="fas fa-comment-dots me-2"></i>Message</h6>
                        <div class="row">
                            <div class="col-12 mb-1">
                                <p class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(msg.message)}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-clock me-2"></i>Timeline</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created At</label>
                                <p class="mb-0">${formatDate(msg.created_date)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('messageDetails').innerHTML = detailsHtml;
            
            // Set form values
            document.getElementById('responseMessageId').value = msg.id;
            document.getElementById('responseEmail').value = msg.emailId;
            document.getElementById('responseName').value = msg.name;
            document.getElementById('responseMessage').value = '';
            document.getElementById('responseSubject').value = 'Re: Your Contact Form Submission';
            
            // Reset alert
            const alert = document.getElementById('responseAlert');
            alert.classList.add('d-none');
            alert.classList.remove('alert-success', 'alert-danger');
            
            // Show modal
            messageModal.show();
        }
        
        // Handle form submission
        document.getElementById('responseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const sendBtn = document.getElementById('sendResponseBtn');
            const originalBtnText = sendBtn.innerHTML;
            const alert = document.getElementById('responseAlert');
            
            // Disable button
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            
            // Hide previous alerts
            alert.classList.add('d-none');
            
            // Determine correct path based on current location
            const currentPath = window.location.pathname;
            const ajaxPath = 'ajax/send_contact_response.php';
            
            fetch(ajaxPath, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert.classList.remove('d-none', 'alert-danger');
                    alert.classList.add('alert-success');
                    alert.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + (data.message || 'Response sent successfully!');
                    
                    // Clear form
                    document.getElementById('responseMessage').value = '';
                    
                    // Optionally close modal after 2 seconds
                    setTimeout(() => {
                        messageModal.hide();
                    }, 2000);
                } else {
                    alert.classList.remove('d-none', 'alert-success');
                    alert.classList.add('alert-danger');
                    alert.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + (data.message || 'Failed to send response. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert.classList.remove('d-none', 'alert-success');
                alert.classList.add('alert-danger');
                alert.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Network error. Please check your connection and try again.';
            })
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalBtnText;
            });
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const day = date.getDate();
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const month = monthNames[date.getMonth()];
            const year = date.getFullYear();
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'pm' : 'am';
            const displayHours = hours % 12 || 12;
            const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
            
            return `${day} ${month} ${year} at ${displayHours}:${displayMinutes} ${ampm}`;
        }
    </script>
</body>
</html>
