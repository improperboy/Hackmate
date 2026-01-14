<?php
session_start();
require_once '../includes/db.php';
require_once '../lib/BlockchainCertificate.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

$message = '';
$error = '';
$blockchain = new BlockchainCertificate($pdo);

// Handle certificate revocation
if (isset($_GET['revoke']) && is_numeric($_GET['revoke'])) {
    $reason = $_POST['revocation_reason'] ?? 'Revoked by administrator';
    
    if ($blockchain->revokeCertificate($_GET['revoke'], $_SESSION['user_id'], $reason)) {
        $message = 'Certificate revoked successfully.';
    } else {
        $error = 'Failed to revoke certificate.';
    }
}

// Handle certificate restoration
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE blockchain_certificates 
            SET is_revoked = 0, revoked_at = NULL, revoked_by = NULL, revocation_reason = NULL
            WHERE id = ?
        ");
        $stmt->execute([$_GET['restore']]);
        $message = 'Certificate restored successfully.';
    } catch (PDOException $e) {
        $error = 'Failed to restore certificate: ' . $e->getMessage();
    }
}

// Get filter parameters
$filter_template = $_GET['template'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_participant = $_GET['participant'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($filter_template)) {
    $where_conditions[] = "bc.template_id = ?";
    $params[] = $filter_template;
}

if (!empty($filter_status)) {
    if ($filter_status === 'active') {
        $where_conditions[] = "bc.is_revoked = 0";
    } elseif ($filter_status === 'revoked') {
        $where_conditions[] = "bc.is_revoked = 1";
    }
}

if (!empty($search)) {
    $where_conditions[] = "(bc.participant_name LIKE ? OR bc.team_name LIKE ? OR bc.certificate_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get certificates with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    // Get total count
    $count_query = "
        SELECT COUNT(*) 
        FROM blockchain_certificates bc
        JOIN certificate_templates ct ON bc.template_id = ct.id
        JOIN users u ON bc.participant_id = u.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_certificates = $stmt->fetchColumn();
    $total_pages = ceil($total_certificates / $per_page);
    
    // Get certificates
    $query = "
        SELECT bc.*, ct.name as template_name, u.name as participant_name, u.email as participant_email,
               revoked_by_user.name as revoked_by_name
        FROM blockchain_certificates bc
        JOIN certificate_templates ct ON bc.template_id = ct.id
        JOIN users u ON bc.participant_id = u.id
        LEFT JOIN users revoked_by_user ON bc.revoked_by = revoked_by_user.id
        $where_clause
        ORDER BY bc.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $certificates = $stmt->fetchAll();
    
    // Get templates for filter
    $stmt = $pdo->query("SELECT id, name FROM certificate_templates ORDER BY name");
    $templates = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error fetching certificates: ' . $e->getMessage();
    $certificates = [];
    $templates = [];
    $total_certificates = 0;
    $total_pages = 0;
}

// Get statistics
$stats = $blockchain->getCertificateStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Certificates - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .certificate-id {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
        }
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-certificate me-2"></i>Blockchain Certificates</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="certificate_templates.php" class="btn btn-outline-primary">
                                <i class="fas fa-file-pdf me-2"></i>Templates
                            </a>
                            <a href="certificate_settings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <?php if ($stats): ?>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="stats-card card bg-primary text-white p-3 text-center">
                                <i class="fas fa-certificate fa-2x mb-2"></i>
                                <h4><?php echo $stats['total_certificates']; ?></h4>
                                <small>Total</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card bg-success text-white p-3 text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4><?php echo $stats['active_certificates']; ?></h4>
                                <small>Active</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card bg-warning text-white p-3 text-center">
                                <i class="fas fa-ban fa-2x mb-2"></i>
                                <h4><?php echo $stats['revoked_certificates']; ?></h4>
                                <small>Revoked</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card bg-info text-white p-3 text-center">
                                <i class="fas fa-download fa-2x mb-2"></i>
                                <h4><?php echo $stats['total_downloads']; ?></h4>
                                <small>Downloads</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card bg-secondary text-white p-3 text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?php echo $stats['unique_participants']; ?></h4>
                                <small>Participants</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card card bg-dark text-white p-3 text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h4><?php echo number_format($stats['avg_downloads_per_certificate'], 1); ?></h4>
                                <small>Avg Downloads</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="template" class="form-label">Template</label>
                                <select class="form-select" id="template" name="template">
                                    <option value="">All Templates</option>
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>" 
                                                <?php echo $filter_template == $template['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($template['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="revoked" <?php echo $filter_status === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Participant name, team, or certificate ID"
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                    <a href="?" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Certificates Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Certificates 
                            <span class="badge bg-secondary"><?php echo $total_certificates; ?></span>
                        </h5>
                        <div>
                            <small class="text-muted">
                                Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($certificates)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-certificate fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No certificates found matching your criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Participant</th>
                                            <th>Template</th>
                                            <th>Certificate ID</th>
                                            <th>Issue Date</th>
                                            <th>Downloads</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($certificates as $cert): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($cert['participant_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($cert['participant_email']); ?></small>
                                                        <?php if ($cert['team_name']): ?>
                                                            <br><small class="text-info">Team: <?php echo htmlspecialchars($cert['team_name']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($cert['template_name']); ?></span>
                                                </td>
                                                <td>
                                                    <code class="certificate-id"><?php echo substr($cert['certificate_id'], 0, 16); ?>...</code>
                                                    <br><small class="text-muted">
                                                        Created: <?php echo date('M j, Y', strtotime($cert['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($cert['issue_date'])); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $cert['download_count']; ?></span>
                                                    <?php if ($cert['last_downloaded_at']): ?>
                                                        <br><small class="text-muted">
                                                            Last: <?php echo date('M j', strtotime($cert['last_downloaded_at'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cert['is_revoked']): ?>
                                                        <span class="badge bg-danger">Revoked</span>
                                                        <?php if ($cert['revoked_at']): ?>
                                                            <br><small class="text-muted">
                                                                <?php echo date('M j, Y', strtotime($cert['revoked_at'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($cert['revoked_by_name']): ?>
                                                            <br><small class="text-muted">
                                                                by <?php echo htmlspecialchars($cert['revoked_by_name']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="<?php echo htmlspecialchars($cert['pdf_file_path']); ?>" 
                                                           target="_blank" class="btn btn-outline-primary" title="Download PDF">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="../verify_certificate.php?id=<?php echo $cert['certificate_id']; ?>" 
                                                           target="_blank" class="btn btn-outline-success" title="Verify">
                                                            <i class="fas fa-check-circle"></i>
                                                        </a>
                                                        <?php if ($cert['is_revoked']): ?>
                                                            <a href="?restore=<?php echo $cert['id']; ?>" 
                                                               class="btn btn-outline-warning" title="Restore"
                                                               onclick="return confirm('Are you sure you want to restore this certificate?')">
                                                                <i class="fas fa-undo"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-outline-danger" title="Revoke"
                                                                    onclick="revokeCertificate(<?php echo $cert['id']; ?>, '<?php echo htmlspecialchars($cert['participant_name']); ?>')">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline-info" title="Details"
                                                                onclick="showCertificateDetails(<?php echo htmlspecialchars(json_encode($cert)); ?>)">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Certificate pagination">
                                <ul class="pagination pagination-sm mb-0 justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Certificate Details Modal -->
    <div class="modal fade" id="certificateDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Certificate Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="certificateDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Revoke Certificate Modal -->
    <div class="modal fade" id="revokeCertificateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Revoke Certificate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="revokeCertificateForm">
                    <div class="modal-body">
                        <p>Are you sure you want to revoke the certificate for <strong id="revokeParticipantName"></strong>?</p>
                        <div class="mb-3">
                            <label for="revocation_reason" class="form-label">Reason for Revocation</label>
                            <textarea class="form-control" id="revocation_reason" name="revocation_reason" rows="3" 
                                      placeholder="Enter reason for revoking this certificate..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Revoke Certificate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showCertificateDetails(cert) {
            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Participant Information</h6>
                        <p><strong>Name:</strong> ${cert.participant_name}</p>
                        <p><strong>Email:</strong> ${cert.participant_email}</p>
                        <p><strong>Team:</strong> ${cert.team_name || 'Individual'}</p>
                        
                        <h6>Certificate Information</h6>
                        <p><strong>Template:</strong> ${cert.template_name}</p>
                        <p><strong>Event:</strong> ${cert.hackathon_name}</p>
                        <p><strong>Issue Date:</strong> ${new Date(cert.issue_date).toLocaleDateString()}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Blockchain Information</h6>
                        <p><strong>Certificate ID:</strong><br><code class="small">${cert.certificate_id}</code></p>
                        <p><strong>Blockchain Hash:</strong><br><code class="small">${cert.blockchain_hash}</code></p>
                        
                        <h6>Statistics</h6>
                        <p><strong>Downloads:</strong> ${cert.download_count}</p>
                        <p><strong>Created:</strong> ${new Date(cert.created_at).toLocaleString()}</p>
                        ${cert.last_downloaded_at ? `<p><strong>Last Downloaded:</strong> ${new Date(cert.last_downloaded_at).toLocaleString()}</p>` : ''}
                    </div>
                </div>
                
                ${cert.is_revoked ? `
                    <hr>
                    <div class="alert alert-warning">
                        <h6>Revocation Information</h6>
                        <p><strong>Revoked:</strong> ${new Date(cert.revoked_at).toLocaleString()}</p>
                        ${cert.revoked_by_name ? `<p><strong>Revoked By:</strong> ${cert.revoked_by_name}</p>` : ''}
                        ${cert.revocation_reason ? `<p><strong>Reason:</strong> ${cert.revocation_reason}</p>` : ''}
                    </div>
                ` : ''}
            `;
            
            document.getElementById('certificateDetailsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('certificateDetailsModal')).show();
        }

        function revokeCertificate(certificateId, participantName) {
            document.getElementById('revokeParticipantName').textContent = participantName;
            document.getElementById('revokeCertificateForm').action = '?revoke=' + certificateId;
            new bootstrap.Modal(document.getElementById('revokeCertificateModal')).show();
        }
    </script>
</body>
</html>