<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and is a participant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'participant') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

// Check if certificate downloads are enabled
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM certificate_settings WHERE setting_key = 'certificate_download_enabled'");
    $stmt->execute();
    $download_enabled = $stmt->fetchColumn();
    
    if (!$download_enabled) {
        $error = 'Certificate downloads are currently disabled. Please check back later.';
    }
} catch (PDOException $e) {
    $download_enabled = false;
    $error = 'Certificate system is temporarily unavailable.';
}

// Get user's certificates
$certificates = [];
if ($download_enabled) {
    try {
        $stmt = $pdo->prepare("
            SELECT bc.*, ct.name as template_name, ct.description as template_description
            FROM blockchain_certificates bc
            JOIN certificate_templates ct ON bc.template_id = ct.id
            WHERE bc.participant_id = ? AND bc.is_revoked = 0
            ORDER BY bc.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $certificates = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error fetching certificates: ' . $e->getMessage();
    }
}

// Handle certificate download
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM blockchain_certificates 
            WHERE id = ? AND participant_id = ? AND is_revoked = 0
        ");
        $stmt->execute([$_GET['download'], $_SESSION['user_id']]);
        $cert = $stmt->fetch();
        
        if ($cert && file_exists($cert['pdf_file_path'])) {
            // Update download count
            $update_stmt = $pdo->prepare("
                UPDATE blockchain_certificates 
                SET download_count = download_count + 1, last_downloaded_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute([$cert['id']]);
            
            // Force download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="certificate_' . $cert['participant_name'] . '.pdf"');
            header('Content-Length: ' . filesize($cert['pdf_file_path']));
            readfile($cert['pdf_file_path']);
            exit();
        } else {
            $error = 'Certificate file not found or access denied.';
        }
    } catch (PDOException $e) {
        $error = 'Error downloading certificate: ' . $e->getMessage();
    }
}

// Get user info
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, t.name as team_name 
        FROM users u 
        LEFT JOIN team_members tm ON u.id = tm.user_id 
        LEFT JOIN teams t ON tm.team_id = t.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch();
} catch (PDOException $e) {
    $user_info = ['name' => 'Unknown', 'email' => '', 'team_name' => null];
}

// Get system settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('hackathon_name', 'contact_email')");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - <?php echo htmlspecialchars($settings['hackathon_name'] ?? 'Hackathon'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .certificate-card {
            border: 2px solid #007bff;
            border-radius: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .certificate-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        }
        .certificate-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .hash-display {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            word-break: break-all;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-certificate me-2"></i>
                <?php echo htmlspecialchars($settings['hackathon_name'] ?? 'Hackathon'); ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php">Dashboard</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="display-6">
                            <i class="fas fa-certificate text-primary me-3"></i>
                            My Certificates
                        </h1>
                        <p class="text-muted">Download and verify your blockchain-secured certificates</p>
                    </div>
                    <div class="text-end">
                        <h6 class="mb-1">Welcome, <?php echo htmlspecialchars($user_info['name']); ?></h6>
                        <small class="text-muted">
                            <?php if ($user_info['team_name']): ?>
                                Team: <?php echo htmlspecialchars($user_info['team_name']); ?>
                            <?php else: ?>
                                Individual Participant
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
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

        <?php if ($download_enabled): ?>
            <!-- Statistics -->
            <?php if (!empty($certificates)): ?>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card p-3 text-center">
                            <i class="fas fa-certificate fa-2x mb-2"></i>
                            <h4><?php echo count($certificates); ?></h4>
                            <small>Total Certificates</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card p-3 text-center">
                            <i class="fas fa-download fa-2x mb-2"></i>
                            <h4><?php echo array_sum(array_column($certificates, 'download_count')); ?></h4>
                            <small>Total Downloads</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card p-3 text-center">
                            <i class="fas fa-calendar fa-2x mb-2"></i>
                            <h4><?php echo date('M Y', strtotime($certificates[0]['created_at'])); ?></h4>
                            <small>Latest Certificate</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Certificates -->
            <?php if (empty($certificates)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-certificate fa-4x text-muted mb-4"></i>
                    <h3 class="text-muted">No Certificates Available</h3>
                    <p class="text-muted">You don't have any certificates yet. Certificates will appear here once they are generated by the administrators.</p>
                    
                    <div class="mt-4">
                        <a href="../index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Go to Dashboard
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($certificates as $cert): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="certificate-card">
                                <div class="certificate-header p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1">
                                                <i class="fas fa-award me-2"></i>
                                                <?php echo htmlspecialchars($cert['template_name']); ?>
                                            </h5>
                                            <small class="opacity-75">
                                                <?php echo htmlspecialchars($cert['template_description'] ?: 'Certificate of Participation'); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-light text-primary">
                                            <i class="fas fa-shield-alt me-1"></i>Verified
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">PARTICIPANT</small>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($cert['participant_name']); ?></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">TEAM</small>
                                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($cert['team_name'] ?: 'Individual'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">EVENT</small>
                                            <p class="mb-0"><?php echo htmlspecialchars($cert['hackathon_name']); ?></p>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">ISSUE DATE</small>
                                            <p class="mb-0"><?php echo date('M j, Y', strtotime($cert['issue_date'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">CERTIFICATE ID</small>
                                        <div class="hash-display"><?php echo htmlspecialchars($cert['certificate_id']); ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">BLOCKCHAIN HASH</small>
                                        <div class="hash-display"><?php echo htmlspecialchars($cert['blockchain_hash']); ?></div>
                                    </div>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <small class="text-muted">Downloads</small>
                                            <p class="mb-0 fw-bold text-primary"><?php echo $cert['download_count']; ?></p>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Generated</small>
                                            <p class="mb-0 fw-bold text-success"><?php echo date('M j', strtotime($cert['created_at'])); ?></p>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Status</small>
                                            <p class="mb-0 fw-bold text-success">Active</p>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="?download=<?php echo $cert['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-download me-2"></i>Download Certificate
                                        </a>
                                        <a href="../verify_certificate.php?id=<?php echo $cert['certificate_id']; ?>" 
                                           target="_blank" class="btn btn-outline-success">
                                            <i class="fas fa-check-circle me-2"></i>Verify Online
                                        </a>
                                        <button class="btn btn-outline-secondary" 
                                                onclick="shareCertificate('<?php echo $cert['certificate_id']; ?>', '<?php echo htmlspecialchars($cert['participant_name']); ?>')">
                                            <i class="fas fa-share me-2"></i>Share Certificate
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Information Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Your Certificates</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-shield-alt text-success me-2"></i>Blockchain Security</h6>
                            <p class="small">Your certificates are secured using blockchain technology, making them tamper-proof and verifiable worldwide.</p>
                            
                            <h6><i class="fas fa-download text-primary me-2"></i>Download Anytime</h6>
                            <p class="small">Download your certificates as many times as needed. Each download is tracked for security purposes.</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-share text-info me-2"></i>Easy Sharing</h6>
                            <p class="small">Share your certificate verification link with employers, institutions, or anyone who needs to verify your achievement.</p>
                            
                            <h6><i class="fas fa-check-circle text-warning me-2"></i>Instant Verification</h6>
                            <p class="small">Anyone can verify your certificate authenticity using the certificate ID or blockchain hash.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareCertificate(certificateId, participantName) {
            const verificationUrl = window.location.origin + '/verify_certificate.php?id=' + certificateId;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Certificate Verification - ' + participantName,
                    text: 'Verify my blockchain-secured certificate',
                    url: verificationUrl
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(verificationUrl).then(() => {
                    alert('Verification URL copied to clipboard!\n\n' + verificationUrl);
                }).catch(() => {
                    // Fallback for older browsers
                    prompt('Copy this verification URL:', verificationUrl);
                });
            }
        }
    </script>
</body>
</html>