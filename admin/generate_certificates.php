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

// Get template ID from URL
$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

// Get template info
try {
    $stmt = $pdo->prepare("SELECT * FROM certificate_templates WHERE id = ? AND is_active = 1");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch();
    
    if (!$template) {
        $error = 'Template not found or inactive.';
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Get hackathon settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('hackathon_name', 'hackathon_start_date', 'hackathon_end_date')");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

// Handle certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificates'])) {
    $selected_participants = $_POST['participants'] ?? [];
    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    
    if (empty($selected_participants)) {
        $error = 'Please select at least one participant.';
    } else {
        $generated_count = 0;
        $errors = [];
        
        foreach ($selected_participants as $participant_id) {
            try {
                // Get participant and team info
                $stmt = $pdo->prepare("
                    SELECT u.id, u.name, u.email, t.name as team_name, t.id as team_id
                    FROM users u
                    LEFT JOIN team_members tm ON u.id = tm.user_id
                    LEFT JOIN teams t ON tm.team_id = t.id
                    WHERE u.id = ? AND u.role = 'participant'
                ");
                $stmt->execute([$participant_id]);
                $participant = $stmt->fetch();
                
                if (!$participant) {
                    $errors[] = "Participant ID $participant_id not found.";
                    continue;
                }
                
                // Check if certificate already exists
                $stmt = $pdo->prepare("SELECT id FROM blockchain_certificates WHERE participant_id = ? AND template_id = ?");
                $stmt->execute([$participant_id, $template_id]);
                if ($stmt->fetch()) {
                    $errors[] = "Certificate already exists for " . $participant['name'];
                    continue;
                }
                
                // Generate certificate data
                $certificate_data = [
                    'participant_name' => $participant['name'],
                    'participant_email' => $participant['email'],
                    'team_name' => $participant['team_name'] ?: 'Individual Participant',
                    'hackathon_name' => $settings['hackathon_name'] ?? 'Hackathon',
                    'issue_date' => $issue_date,
                    'template_id' => $template_id,
                    'generated_at' => date('Y-m-d H:i:s')
                ];
                
                // Generate unique certificate ID
                $certificate_id = hash('sha256', json_encode($certificate_data) . time() . $participant_id);
                
                // Generate blockchain hash
                $blockchain_hash = hash('sha256', $certificate_id . json_encode($certificate_data));
                
                // Create certificate directory
                $cert_dir = '../uploads/certificates/';
                if (!is_dir($cert_dir)) {
                    mkdir($cert_dir, 0755, true);
                }
                
                // Generate PDF certificate (simplified - you would use a PDF library like TCPDF or FPDF)
                $pdf_filename = $certificate_id . '.pdf';
                $pdf_path = $cert_dir . $pdf_filename;
                
                // For now, copy the template and add a simple text overlay
                // In a real implementation, you would use a PDF library to fill form fields
                if (copy($template['pdf_file_path'], $pdf_path)) {
                    // Insert certificate record
                    $stmt = $pdo->prepare("
                        INSERT INTO blockchain_certificates 
                        (certificate_id, participant_id, team_id, template_id, participant_name, team_name, 
                         hackathon_name, issue_date, certificate_data, pdf_file_path, blockchain_hash) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $certificate_id,
                        $participant_id,
                        $participant['team_id'],
                        $template_id,
                        $participant['name'],
                        $participant['team_name'],
                        $settings['hackathon_name'] ?? 'Hackathon',
                        $issue_date,
                        json_encode($certificate_data),
                        $pdf_path,
                        $blockchain_hash
                    ]);
                    
                    $generated_count++;
                } else {
                    $errors[] = "Failed to generate PDF for " . $participant['name'];
                }
                
            } catch (PDOException $e) {
                $errors[] = "Database error for " . ($participant['name'] ?? "participant $participant_id") . ": " . $e->getMessage();
            }
        }
        
        if ($generated_count > 0) {
            $message = "Successfully generated $generated_count certificate(s).";
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    }
}

// Get all participants with their team info
try {
    $stmt = $pdo->query("
        SELECT u.id, u.name, u.email, t.name as team_name,
               CASE WHEN bc.id IS NOT NULL THEN 1 ELSE 0 END as has_certificate
        FROM users u
        LEFT JOIN team_members tm ON u.id = tm.user_id
        LEFT JOIN teams t ON tm.team_id = t.id
        LEFT JOIN blockchain_certificates bc ON u.id = bc.participant_id AND bc.template_id = $template_id
        WHERE u.role = 'participant'
        ORDER BY u.name
    ");
    $participants = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching participants: ' . $e->getMessage();
    $participants = [];
}

// Get existing certificates for this template
try {
    $stmt = $pdo->prepare("
        SELECT bc.*, u.name as participant_name, u.email as participant_email
        FROM blockchain_certificates bc
        JOIN users u ON bc.participant_id = u.id
        WHERE bc.template_id = ?
        ORDER BY bc.created_at DESC
    ");
    $stmt->execute([$template_id]);
    $existing_certificates = $stmt->fetchAll();
} catch (PDOException $e) {
    $existing_certificates = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Certificates - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-certificate me-2"></i>Generate Certificates
                        <?php if ($template): ?>
                            <small class="text-muted">- <?php echo htmlspecialchars($template['name']); ?></small>
                        <?php endif; ?>
                    </h1>
                    <a href="certificate_templates.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Templates
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($template): ?>
                    <!-- Template Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Template Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($template['name']); ?></p>
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($template['description'] ?: 'No description'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>File:</strong> 
                                        <a href="<?php echo htmlspecialchars($template['pdf_file_path']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($template['pdf_file_name']); ?>
                                        </a>
                                    </p>
                                    <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($template['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Generate Certificates Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Generate New Certificates</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="issue_date" class="form-label">Issue Date</label>
                                        <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-end h-100">
                                            <button type="button" class="btn btn-outline-secondary me-2" onclick="selectAll()">
                                                Select All
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="selectNone()">
                                                Select None
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Select Participants</label>
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-sm table-striped">
                                            <thead class="sticky-top bg-light">
                                                <tr>
                                                    <th width="50">
                                                        <input type="checkbox" id="select_all" onchange="toggleAll(this)">
                                                    </th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Team</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($participants as $participant): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" name="participants[]" 
                                                                   value="<?php echo $participant['id']; ?>"
                                                                   class="participant-checkbox"
                                                                   <?php echo $participant['has_certificate'] ? 'disabled' : ''; ?>>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($participant['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($participant['email']); ?></td>
                                                        <td><?php echo htmlspecialchars($participant['team_name'] ?: 'No Team'); ?></td>
                                                        <td>
                                                            <?php if ($participant['has_certificate']): ?>
                                                                <span class="badge bg-success">Certificate Exists</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">No Certificate</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <button type="submit" name="generate_certificates" class="btn btn-primary">
                                    <i class="fas fa-certificate me-2"></i>Generate Selected Certificates
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Existing Certificates -->
                    <?php if (!empty($existing_certificates)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Generated Certificates</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Participant</th>
                                                <th>Team</th>
                                                <th>Certificate ID</th>
                                                <th>Issue Date</th>
                                                <th>Downloads</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($existing_certificates as $cert): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($cert['participant_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($cert['participant_email']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($cert['team_name'] ?: 'Individual'); ?></td>
                                                    <td>
                                                        <code class="small"><?php echo substr($cert['certificate_id'], 0, 16); ?>...</code>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($cert['issue_date'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $cert['download_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($cert['is_revoked']): ?>
                                                            <span class="badge bg-danger">Revoked</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="<?php echo htmlspecialchars($cert['pdf_file_path']); ?>" 
                                                               target="_blank" class="btn btn-outline-primary">
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                            <a href="verify_certificate.php?id=<?php echo $cert['certificate_id']; ?>" 
                                                               target="_blank" class="btn btn-outline-info">
                                                                <i class="fas fa-check-circle"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.participant-checkbox:not(:disabled)');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.participant-checkbox:not(:disabled)');
            checkboxes.forEach(cb => cb.checked = true);
            document.getElementById('select_all').checked = true;
        }

        function selectNone() {
            const checkboxes = document.querySelectorAll('.participant-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('select_all').checked = false;
        }
    </script>
</body>
</html>