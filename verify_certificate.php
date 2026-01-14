<?php
require_once 'includes/db.php';

$verification_result = null;
$certificate = null;
$error = '';

// Handle verification request
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    $start_time = microtime(true);
    
    // Get verification input
    $verification_input = '';
    $verification_method = '';
    
    if (isset($_GET['id'])) {
        $verification_input = trim($_GET['id']);
        $verification_method = 'certificate_id';
    } elseif (isset($_POST['certificate_id']) && !empty($_POST['certificate_id'])) {
        $verification_input = trim($_POST['certificate_id']);
        $verification_method = 'certificate_id';
    } elseif (isset($_POST['participant_name']) && !empty($_POST['participant_name'])) {
        $verification_input = trim($_POST['participant_name']);
        $verification_method = 'participant_name';
    } elseif (isset($_POST['blockchain_hash']) && !empty($_POST['blockchain_hash'])) {
        $verification_input = trim($_POST['blockchain_hash']);
        $verification_method = 'hash';
    }
    
    if (!empty($verification_input)) {
        try {
            $sql = "
                SELECT bc.*, ct.name as template_name, u.name as participant_name, u.email as participant_email
                FROM blockchain_certificates bc
                JOIN certificate_templates ct ON bc.template_id = ct.id
                JOIN users u ON bc.participant_id = u.id
                WHERE ";
            
            $params = [];
            
            switch ($verification_method) {
                case 'certificate_id':
                    $sql .= "bc.certificate_id = ?";
                    $params[] = $verification_input;
                    break;
                case 'participant_name':
                    $sql .= "LOWER(bc.participant_name) LIKE LOWER(?)";
                    $params[] = '%' . $verification_input . '%';
                    break;
                case 'hash':
                    $sql .= "bc.blockchain_hash = ?";
                    $params[] = $verification_input;
                    break;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $certificate = $stmt->fetch();
            
            if ($certificate) {
                if ($certificate['is_revoked']) {
                    $verification_result = 'revoked';
                } else {
                    $verification_result = 'valid';
                    
                    // Update download count if accessed via direct link
                    if (isset($_GET['id'])) {
                        $update_stmt = $pdo->prepare("
                            UPDATE blockchain_certificates 
                            SET download_count = download_count + 1, last_downloaded_at = NOW() 
                            WHERE certificate_id = ?
                        ");
                        $update_stmt->execute([$certificate['certificate_id']]);
                    }
                }
            } else {
                $verification_result = 'not_found';
            }
            
            // Log verification attempt
            $end_time = microtime(true);
            $response_time = round(($end_time - $start_time) * 1000); // Convert to milliseconds
            
            $log_stmt = $pdo->prepare("
                INSERT INTO certificate_verification_logs 
                (certificate_id, verifier_ip, verifier_user_agent, verification_method, verification_input, verification_result, response_time_ms)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $certificate ? $certificate['certificate_id'] : $verification_input,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $verification_method,
                $verification_input,
                $verification_result,
                $response_time
            ]);
            
        } catch (PDOException $e) {
            $error = 'Verification system temporarily unavailable. Please try again later.';
            error_log('Certificate verification error: ' . $e->getMessage());
        }
    } else {
        $error = 'Please provide a certificate ID, participant name, or blockchain hash to verify.';
    }
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
    <title>Certificate Verification - <?php echo htmlspecialchars($settings['hackathon_name'] ?? 'Hackathon'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .verification-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 0;
        }
        .certificate-card {
            border: 2px solid #28a745;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .certificate-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 1.5rem;
        }
        .verification-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
        }
        .hash-display {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-certificate me-2"></i>
                <?php echo htmlspecialchars($settings['hackathon_name'] ?? 'Hackathon'); ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Home
                </a>
            </div>
        </div>
    </nav>

    <div class="container verification-container">
        <div class="text-center mb-4">
            <h1 class="display-4">
                <i class="fas fa-shield-alt text-primary me-3"></i>
                Certificate Verification
            </h1>
            <p class="lead text-muted">Verify the authenticity of blockchain-secured certificates</p>
        </div>

        <!-- Verification Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Verify Certificate</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="certificate_id" class="form-label">Certificate ID</label>
                            <input type="text" class="form-control" id="certificate_id" name="certificate_id" 
                                   placeholder="Enter certificate ID" 
                                   value="<?php echo isset($_POST['certificate_id']) ? htmlspecialchars($_POST['certificate_id']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="participant_name" class="form-label">Participant Name</label>
                            <input type="text" class="form-control" id="participant_name" name="participant_name" 
                                   placeholder="Enter participant name"
                                   value="<?php echo isset($_POST['participant_name']) ? htmlspecialchars($_POST['participant_name']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="blockchain_hash" class="form-label">Blockchain Hash</label>
                        <input type="text" class="form-control" id="blockchain_hash" name="blockchain_hash" 
                               placeholder="Enter blockchain hash for verification"
                               value="<?php echo isset($_POST['blockchain_hash']) ? htmlspecialchars($_POST['blockchain_hash']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Verify Certificate
                    </button>
                </form>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Verification Results -->
        <?php if ($verification_result): ?>
            <div class="certificate-card">
                <?php if ($verification_result === 'valid'): ?>
                    <div class="certificate-header text-center">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <h3>Certificate Verified</h3>
                        <span class="badge verification-badge bg-light text-success">
                            <i class="fas fa-shield-alt me-1"></i>AUTHENTIC
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">PARTICIPANT DETAILS</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($certificate['participant_name']); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($certificate['participant_email']); ?></p>
                                <p class="mb-3"><strong>Team:</strong> <?php echo htmlspecialchars($certificate['team_name'] ?: 'Individual Participant'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">CERTIFICATE DETAILS</h6>
                                <p class="mb-1"><strong>Event:</strong> <?php echo htmlspecialchars($certificate['hackathon_name']); ?></p>
                                <p class="mb-1"><strong>Template:</strong> <?php echo htmlspecialchars($certificate['template_name']); ?></p>
                                <p class="mb-1"><strong>Issue Date:</strong> <?php echo date('F j, Y', strtotime($certificate['issue_date'])); ?></p>
                                <p class="mb-3"><strong>Downloads:</strong> <?php echo $certificate['download_count']; ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-muted">BLOCKCHAIN VERIFICATION</h6>
                                <p class="mb-1"><strong>Certificate ID:</strong></p>
                                <div class="hash-display mb-2"><?php echo htmlspecialchars($certificate['certificate_id']); ?></div>
                                
                                <p class="mb-1"><strong>Blockchain Hash:</strong></p>
                                <div class="hash-display mb-3"><?php echo htmlspecialchars($certificate['blockchain_hash']); ?></div>
                                
                                <p class="mb-1"><strong>Generated:</strong> <?php echo date('F j, Y g:i A', strtotime($certificate['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="<?php echo htmlspecialchars($certificate['pdf_file_path']); ?>" 
                               target="_blank" class="btn btn-success me-2">
                                <i class="fas fa-download me-2"></i>Download Certificate
                            </a>
                            <button class="btn btn-outline-primary" onclick="shareVerification()">
                                <i class="fas fa-share me-2"></i>Share Verification
                            </button>
                        </div>
                    </div>
                    
                <?php elseif ($verification_result === 'revoked'): ?>
                    <div class="certificate-header text-center bg-danger">
                        <i class="fas fa-times-circle fa-3x mb-3"></i>
                        <h3>Certificate Revoked</h3>
                        <span class="badge verification-badge bg-light text-danger">
                            <i class="fas fa-ban me-1"></i>REVOKED
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>This certificate has been revoked.</strong><br>
                            Revoked on: <?php echo date('F j, Y g:i A', strtotime($certificate['revoked_at'])); ?><br>
                            <?php if ($certificate['revocation_reason']): ?>
                                Reason: <?php echo htmlspecialchars($certificate['revocation_reason']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <p><strong>Original Participant:</strong> <?php echo htmlspecialchars($certificate['participant_name']); ?></p>
                        <p><strong>Original Issue Date:</strong> <?php echo date('F j, Y', strtotime($certificate['issue_date'])); ?></p>
                    </div>
                    
                <?php elseif ($verification_result === 'not_found'): ?>
                    <div class="certificate-header text-center bg-warning">
                        <i class="fas fa-question-circle fa-3x mb-3"></i>
                        <h3>Certificate Not Found</h3>
                        <span class="badge verification-badge bg-light text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>NOT FOUND
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>No certificate found matching your search criteria.</strong><br>
                            Please check the certificate ID, participant name, or blockchain hash and try again.
                        </div>
                        
                        <h6>Possible reasons:</h6>
                        <ul>
                            <li>The certificate ID or hash is incorrect</li>
                            <li>The participant name is misspelled</li>
                            <li>The certificate has not been issued yet</li>
                            <li>The certificate was issued by a different organization</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Information Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Certificate Verification</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-shield-alt text-success me-2"></i>Blockchain Security</h6>
                        <p class="small">All certificates are secured using blockchain technology with SHA-256 hashing to ensure authenticity and prevent tampering.</p>
                        
                        <h6><i class="fas fa-clock text-info me-2"></i>Real-time Verification</h6>
                        <p class="small">Certificate status is verified in real-time against our secure database and blockchain records.</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-search text-primary me-2"></i>Multiple Verification Methods</h6>
                        <p class="small">Verify certificates using certificate ID, participant name, or blockchain hash for maximum flexibility.</p>
                        
                        <h6><i class="fas fa-download text-warning me-2"></i>Secure Downloads</h6>
                        <p class="small">Download authentic certificates directly from our secure servers with download tracking.</p>
                    </div>
                </div>
                
                <?php if (!empty($settings['contact_email'])): ?>
                    <hr>
                    <p class="mb-0 text-center">
                        <small>For questions about certificate verification, contact: 
                            <a href="mailto:<?php echo htmlspecialchars($settings['contact_email']); ?>">
                                <?php echo htmlspecialchars($settings['contact_email']); ?>
                            </a>
                        </small>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareVerification() {
            const url = window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: 'Certificate Verification',
                    text: 'Verified certificate for <?php echo isset($certificate) ? htmlspecialchars($certificate['participant_name']) : ''; ?>',
                    url: url
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Verification URL copied to clipboard!');
                });
            }
        }
    </script>
</body>
</html>