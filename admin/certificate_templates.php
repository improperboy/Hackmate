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

// Handle template upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_template'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $error = 'Template name is required.';
    } elseif (!isset($_FILES['pdf_template']) || $_FILES['pdf_template']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid PDF file.';
    } else {
        $file = $_FILES['pdf_template'];
        $allowed_extensions = ['pdf'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error = 'Only PDF files are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size must be less than 10MB.';
        } else {
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/certificate_templates/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO certificate_templates 
                        (name, description, pdf_file_path, pdf_file_name, pdf_file_size, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $name,
                        $description,
                        $file_path,
                        $file['name'],
                        $file['size'],
                        $_SESSION['user_id']
                    ]);
                    
                    $message = 'Certificate template uploaded successfully!';
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    unlink($file_path); // Remove uploaded file on database error
                }
            } else {
                $error = 'Failed to upload file.';
            }
        }
    }
}

// Handle template deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        // Get template info first
        $stmt = $pdo->prepare("SELECT pdf_file_path FROM certificate_templates WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $template = $stmt->fetch();
        
        if ($template) {
            // Delete file
            if (file_exists($template['pdf_file_path'])) {
                unlink($template['pdf_file_path']);
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM certificate_templates WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            
            $message = 'Template deleted successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error deleting template: ' . $e->getMessage();
    }
}

// Handle template activation/deactivation
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    try {
        $stmt = $pdo->prepare("UPDATE certificate_templates SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$_GET['toggle']]);
        $message = 'Template status updated successfully!';
    } catch (PDOException $e) {
        $error = 'Error updating template status: ' . $e->getMessage();
    }
}

// Get all templates
try {
    $stmt = $pdo->query("
        SELECT ct.*, u.name as created_by_name 
        FROM certificate_templates ct 
        LEFT JOIN users u ON ct.created_by = u.id 
        ORDER BY ct.created_at DESC
    ");
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching templates: ' . $e->getMessage();
    $templates = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Templates - Admin</title>
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
                    <h1 class="h2"><i class="fas fa-certificate me-2"></i>Certificate Templates</h1>
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

                <!-- Upload Template Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload New Template</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Template Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pdf_template" class="form-label">PDF Template *</label>
                                        <input type="file" class="form-control" id="pdf_template" name="pdf_template" accept=".pdf" required>
                                        <div class="form-text">Maximum file size: 10MB. Only PDF files allowed.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional description of the template"></textarea>
                            </div>
                            <button type="submit" name="upload_template" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Template
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Templates List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Existing Templates</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($templates)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-certificate fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No certificate templates uploaded yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>File</th>
                                            <th>Size</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($templates as $template): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($template['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($template['description'] ?: 'No description'); ?>
                                                </td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($template['pdf_file_path']); ?>" 
                                                       target="_blank" class="text-decoration-none">
                                                        <i class="fas fa-file-pdf text-danger me-1"></i>
                                                        <?php echo htmlspecialchars($template['pdf_file_name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php echo number_format($template['pdf_file_size'] / 1024, 1); ?> KB
                                                </td>
                                                <td>
                                                    <?php if ($template['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($template['created_by_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y g:i A', strtotime($template['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="?toggle=<?php echo $template['id']; ?>" 
                                                           class="btn btn-outline-<?php echo $template['is_active'] ? 'warning' : 'success'; ?>"
                                                           onclick="return confirm('Are you sure you want to <?php echo $template['is_active'] ? 'deactivate' : 'activate'; ?> this template?')">
                                                            <i class="fas fa-<?php echo $template['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </a>
                                                        <a href="generate_certificates.php?template_id=<?php echo $template['id']; ?>" 
                                                           class="btn btn-outline-primary">
                                                            <i class="fas fa-certificate"></i>
                                                        </a>
                                                        <a href="?delete=<?php echo $template['id']; ?>" 
                                                           class="btn btn-outline-danger"
                                                           onclick="return confirm('Are you sure you want to delete this template? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>