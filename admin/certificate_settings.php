<?php
session_start();
require_once '../includes/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../unauthorized.php');
    exit();
}

$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings_to_update = [
        'certificates_enabled' => isset($_POST['certificates_enabled']) ? '1' : '0',
        'auto_generate_certificates' => isset($_POST['auto_generate_certificates']) ? '1' : '0',
        'certificate_download_enabled' => isset($_POST['certificate_download_enabled']) ? '1' : '0',
        'verification_enabled' => isset($_POST['verification_enabled']) ? '1' : '0',
        'max_template_file_size' => (int)$_POST['max_template_file_size'],
        'allowed_template_extensions' => trim($_POST['allowed_template_extensions']),
        'certificate_validity_years' => (int)$_POST['certificate_validity_years'],
        'blockchain_provider' => trim($_POST['blockchain_provider']),
        'verification_base_url' => trim($_POST['verification_base_url'])
    ];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($settings_to_update as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO certificate_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            
            $type = is_numeric($value) ? 'integer' : (in_array($value, ['0', '1']) ? 'boolean' : 'string');
            $stmt->execute([$key, $value, $type]);
        }
        
        $pdo->commit();
        $message = 'Certificate settings updated successfully!';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get current settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM certificate_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $error = 'Error fetching settings: ' . $e->getMessage();
    $settings = [];
}

// Get certificate statistics
try {
    $stats_query = "
        SELECT 
            COUNT(*) as total_certificates,
            COUNT(CASE WHEN is_revoked = 0 THEN 1 END) as active_certificates,
            COUNT(CASE WHEN is_revoked = 1 THEN 1 END) as revoked_certificates,
            SUM(download_count) as total_downloads,
            COUNT(DISTINCT participant_id) as unique_participants,
            COUNT(DISTINCT template_id) as templates_used
        FROM blockchain_certificates
    ";
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch();
    
    // Get verification statistics
    $verification_stats_query = "
        SELECT 
            COUNT(*) as total_verifications,
            COUNT(CASE WHEN verification_result = 'valid' THEN 1 END) as valid_verifications,
            COUNT(CASE WHEN verification_result = 'invalid' THEN 1 END) as invalid_verifications,
            COUNT(CASE WHEN verification_result = 'not_found' THEN 1 END) as not_found_verifications,
            COUNT(CASE WHEN verification_result = 'revoked' THEN 1 END) as revoked_verifications
        FROM certificate_verification_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $stmt = $pdo->query($verification_stats_query);
    $verification_stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $stats = ['total_certificates' => 0, 'active_certificates' => 0, 'revoked_certificates' => 0, 
              'total_downloads' => 0, 'unique_participants' => 0, 'templates_used' => 0];
    $verification_stats = ['total_verifications' => 0, 'valid_verifications' => 0, 
                          'invalid_verifications' => 0, 'not_found_verifications' => 0, 'revoked_verifications' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-icon {
            font-size: 2rem;
            opacity: 0.8;
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
                    <h1 class="h2"><i class="fas fa-cog me-2"></i>Certificate Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="certificate_templates.php" class="btn btn-outline-primary">
                                <i class="fas fa-certificate me-2"></i>Templates
                            </a>
                            <a href="blockchain_certificates.php" class="btn btn-outline-success">
                                <i class="fas fa-list me-2"></i>All Certificates
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
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stats-card card bg-primary text-white p-3 text-center">
                            <i class="fas fa-certificate stats-icon"></i>
                            <h4 class="mt-2"><?php echo $stats['total_certificates']; ?></h4>
                            <small>Total Certificates</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card bg-success text-white p-3 text-center">
                            <i class="fas fa-check-circle stats-icon"></i>
                            <h4 class="mt-2"><?php echo $stats['active_certificates']; ?></h4>
                            <small>Active</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card bg-warning text-white p-3 text-center">
                            <i class="fas fa-ban stats-icon"></i>
                            <h4 class="mt-2"><?php echo $stats['revoked_certificates']; ?></h4>
                            <small>Revoked</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card bg-info text-white p-3 text-center">
                            <i class="fas fa-download stats-icon"></i>
                            <h4 class="mt-2"><?php echo $stats['total_downloads']; ?></h4>
                            <small>Downloads</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card bg-secondary text-white p-3 text-center">
                            <i class="fas fa-users stats-icon"></i>
                            <h4 class="mt-2"><?php echo $stats['unique_participants']; ?></h4>
                            <small>Participants</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card bg-dark text-white p-3 text-center">
                            <i class="fas fa-shield-alt stats-icon"></i>
                            <h4 class="mt-2"><?php echo $verification_stats['total_verifications']; ?></h4>
                            <small>Verifications (30d)</small>
                        </div>
                    </div>
                </div>

                <!-- Settings Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Certificate System Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <!-- General Settings -->
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">General Settings</h6>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="certificates_enabled" 
                                                   name="certificates_enabled" 
                                                   <?php echo ($settings['certificates_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="certificates_enabled">
                                                <strong>Enable Certificate System</strong>
                                                <br><small class="text-muted">Master switch for the entire certificate system</small>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="auto_generate_certificates" 
                                                   name="auto_generate_certificates"
                                                   <?php echo ($settings['auto_generate_certificates'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="auto_generate_certificates">
                                                <strong>Auto-Generate Certificates</strong>
                                                <br><small class="text-muted">Automatically generate certificates for all participants</small>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="certificate_download_enabled" 
                                                   name="certificate_download_enabled"
                                                   <?php echo ($settings['certificate_download_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="certificate_download_enabled">
                                                <strong>Enable Certificate Downloads</strong>
                                                <br><small class="text-muted">Allow participants to download their certificates</small>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="verification_enabled" 
                                                   name="verification_enabled"
                                                   <?php echo ($settings['verification_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="verification_enabled">
                                                <strong>Enable Public Verification</strong>
                                                <br><small class="text-muted">Allow public verification of certificates</small>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="certificate_validity_years" class="form-label">Certificate Validity (Years)</label>
                                        <input type="number" class="form-control" id="certificate_validity_years" 
                                               name="certificate_validity_years" min="1" max="50"
                                               value="<?php echo htmlspecialchars($settings['certificate_validity_years'] ?? '10'); ?>">
                                        <div class="form-text">How long certificates remain valid</div>
                                    </div>
                                </div>
                                
                                <!-- Technical Settings -->
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">Technical Settings</h6>
                                    
                                    <div class="mb-3">
                                        <label for="max_template_file_size" class="form-label">Max Template File Size (MB)</label>
                                        <input type="number" class="form-control" id="max_template_file_size" 
                                               name="max_template_file_size" min="1" max="100"
                                               value="<?php echo htmlspecialchars(($settings['max_template_file_size'] ?? 10485760) / 1048576); ?>">
                                        <div class="form-text">Maximum size for certificate template uploads</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="allowed_template_extensions" class="form-label">Allowed Template Extensions</label>
                                        <input type="text" class="form-control" id="allowed_template_extensions" 
                                               name="allowed_template_extensions"
                                               value="<?php echo htmlspecialchars($settings['allowed_template_extensions'] ?? 'pdf'); ?>">
                                        <div class="form-text">Comma-separated list of allowed file extensions</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="blockchain_provider" class="form-label">Blockchain Provider</label>
                                        <select class="form-select" id="blockchain_provider" name="blockchain_provider">
                                            <option value="internal" <?php echo ($settings['blockchain_provider'] ?? 'internal') === 'internal' ? 'selected' : ''; ?>>
                                                Internal (SHA-256 Hashing)
                                            </option>
                                            <option value="ethereum" <?php echo ($settings['blockchain_provider'] ?? '') === 'ethereum' ? 'selected' : ''; ?>>
                                                Ethereum Blockchain
                                            </option>
                                            <option value="polygon" <?php echo ($settings['blockchain_provider'] ?? '') === 'polygon' ? 'selected' : ''; ?>>
                                                Polygon Network
                                            </option>
                                        </select>
                                        <div class="form-text">Blockchain technology used for certificate verification</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="verification_base_url" class="form-label">Verification Base URL</label>
                                        <input type="url" class="form-control" id="verification_base_url" 
                                               name="verification_base_url" placeholder="https://yourdomain.com"
                                               value="<?php echo htmlspecialchars($settings['verification_base_url'] ?? ''); ?>">
                                        <div class="form-text">Base URL for certificate verification links</div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between">
                                <div>
                                    <button type="submit" name="update_settings" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Settings
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                        <i class="fas fa-undo me-2"></i>Reset
                                    </button>
                                </div>
                                <div>
                                    <a href="certificate_templates.php" class="btn btn-outline-primary">
                                        <i class="fas fa-certificate me-2"></i>Manage Templates
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Verification Statistics -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Verification Statistics (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-2">
                                <h4 class="text-primary"><?php echo $verification_stats['total_verifications']; ?></h4>
                                <small class="text-muted">Total Attempts</small>
                            </div>
                            <div class="col-md-2">
                                <h4 class="text-success"><?php echo $verification_stats['valid_verifications']; ?></h4>
                                <small class="text-muted">Valid</small>
                            </div>
                            <div class="col-md-2">
                                <h4 class="text-danger"><?php echo $verification_stats['invalid_verifications']; ?></h4>
                                <small class="text-muted">Invalid</small>
                            </div>
                            <div class="col-md-2">
                                <h4 class="text-warning"><?php echo $verification_stats['not_found_verifications']; ?></h4>
                                <small class="text-muted">Not Found</small>
                            </div>
                            <div class="col-md-2">
                                <h4 class="text-secondary"><?php echo $verification_stats['revoked_verifications']; ?></h4>
                                <small class="text-muted">Revoked</small>
                            </div>
                            <div class="col-md-2">
                                <h4 class="text-info">
                                    <?php 
                                    $success_rate = $verification_stats['total_verifications'] > 0 
                                        ? round(($verification_stats['valid_verifications'] / $verification_stats['total_verifications']) * 100, 1)
                                        : 0;
                                    echo $success_rate . '%';
                                    ?>
                                </h4>
                                <small class="text-muted">Success Rate</small>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>