<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Require admin access
requireRole('admin');

$user_name = $_SESSION['user_name'];

// Get all submitted repositories with user information
try {
    $stmt = $pdo->prepare("
        SELECT 
            gr.*,
            u.name as submitted_by_name,
            u.email as submitted_by_email,
            u.role as submitted_by_role
        FROM github_repositories gr 
        JOIN users u ON gr.submitted_by = u.id 
        ORDER BY gr.created_at DESC
    ");
    $stmt->execute();
    $repositories = $stmt->fetchAll();

    // Get statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_repos,
            COUNT(CASE WHEN status = 'verified' THEN 1 END) as verified_repos,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_repos,
            COUNT(CASE WHEN status = 'invalid' THEN 1 END) as invalid_repos,
            COUNT(DISTINCT submitted_by) as unique_users
        FROM github_repositories
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching repositories: " . $e->getMessage());
    $repositories = [];
    $stats = ['total_repos' => 0, 'verified_repos' => 0, 'pending_repos' => 0, 'invalid_repos' => 0, 'unique_users' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Repositories - Admin Panel</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }

        .main-content {
            margin-left: 0;
        }

        @media (min-width: 768px) {
            .main-content {
                margin-left: 250px;
            }
        }

        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-card.total {
            border-left-color: #007bff;
        }

        .stats-card.verified {
            border-left-color: #28a745;
        }

        .stats-card.pending {
            border-left-color: #ffc107;
        }

        .stats-card.invalid {
            border-left-color: #dc3545;
        }

        .repo-url {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .github-data-preview {
            max-width: 200px;
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed top-0 start-0 bg-dark text-white p-3 d-none d-md-block" style="width: 250px; z-index: 1000;">
        <div class="text-center mb-4">
            <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a href="dashboard.php" class="nav-link text-white">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="github_repositories.php" class="nav-link text-white active bg-primary rounded">
                    <i class="fab fa-github me-2"></i>GitHub Repositories
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="users.php" class="nav-link text-white">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="teams.php" class="nav-link text-white">
                    <i class="fas fa-users-cog me-2"></i>Teams
                </a>
            </li>
        </ul>

        <div class="mt-auto pt-4">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i>
                    <span><?php echo htmlspecialchars($user_name); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark">
                    <li><a class="dropdown-item" href="../profile.php">Profile</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <span class="navbar-brand mb-0 h1">
                    <i class="fab fa-github me-2"></i>
                    GitHub Repositories Management
                </span>

                <div class="navbar-nav ms-auto">
                    <span class="navbar-text">
                        Welcome, <?php echo htmlspecialchars($user_name); ?>
                    </span>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container-fluid p-4">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stats-card total">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Total Repositories</h6>
                                    <h3 class="mb-0"><?php echo $stats['total_repos']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fab fa-github fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card stats-card verified">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Verified</h6>
                                    <h3 class="mb-0 text-success"><?php echo $stats['verified_repos']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card stats-card pending">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Pending</h6>
                                    <h3 class="mb-0 text-warning"><?php echo $stats['pending_repos']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card stats-card invalid">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Unique Users</h6>
                                    <h3 class="mb-0 text-info"><?php echo $stats['unique_users']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Repositories Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fab fa-github me-2"></i>
                        Submitted Repositories
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="repositoriesTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Repository</th>
                                    <th>Owner/Name</th>
                                    <th>Submitted By</th>
                                    <th>Status</th>
                                    <th>GitHub Data</th>
                                    <th>Submitted Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repositories as $repo): ?>
                                    <?php
                                    $github_data = $repo['github_data'] ? json_decode($repo['github_data'], true) : [];
                                    $status_class = [
                                        'verified' => 'success',
                                        'pending' => 'warning',
                                        'invalid' => 'danger'
                                    ][$repo['status']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="repo-url">
                                                <a href="<?php echo htmlspecialchars($repo['github_url']); ?>"
                                                    target="_blank"
                                                    class="text-decoration-none"
                                                    title="<?php echo htmlspecialchars($repo['github_url']); ?>">
                                                    <?php echo htmlspecialchars($repo['github_url']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($repo['repository_owner']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($repo['repository_name']); ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($repo['submitted_by_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($repo['submitted_by_email']); ?></small>
                                                <br>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($repo['submitted_by_role']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo ucfirst($repo['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="github-data-preview">
                                                <?php if (!empty($github_data)): ?>
                                                    <?php if (isset($github_data['language'])): ?>
                                                        <small><i class="fas fa-code"></i> <?php echo htmlspecialchars($github_data['language']); ?></small><br>
                                                    <?php endif; ?>
                                                    <?php if (isset($github_data['stars'])): ?>
                                                        <small><i class="fas fa-star"></i> <?php echo $github_data['stars']; ?></small><br>
                                                    <?php endif; ?>
                                                    <?php if (isset($github_data['description']) && $github_data['description']): ?>
                                                        <small class="text-muted" title="<?php echo htmlspecialchars($github_data['description']); ?>">
                                                            <?php echo htmlspecialchars(substr($github_data['description'], 0, 50)) . (strlen($github_data['description']) > 50 ? '...' : ''); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small class="text-muted">No data</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('M j, Y', strtotime($repo['created_at'])); ?>
                                                <br>
                                                <?php echo date('g:i A', strtotime($repo['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info"
                                                    onclick="viewDetails(<?php echo $repo['id']; ?>)"
                                                    title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="<?php echo htmlspecialchars($repo['github_url']); ?>"
                                                    target="_blank"
                                                    class="btn btn-outline-primary"
                                                    title="Open on GitHub">
                                                    <i class="fab fa-github"></i>
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
        </div>
    </div>

    <!-- Repository Details Modal -->
    <div class="modal fade" id="repoDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Repository Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="repoDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#repositoriesTable').DataTable({
                order: [
                    [5, 'desc']
                ], // Sort by submitted date descending
                pageLength: 25,
                responsive: true,
                columnDefs: [{
                        orderable: false,
                        targets: [6]
                    } // Disable sorting on actions column
                ]
            });
        });

        function viewDetails(repoId) {
            // Find the repository data
            const repositories = <?php echo json_encode($repositories); ?>;
            const repo = repositories.find(r => r.id == repoId);

            if (!repo) {
                alert('Repository not found');
                return;
            }

            const githubData = repo.github_data ? JSON.parse(repo.github_data) : {};

            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Repository Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>URL:</strong></td><td><a href="${repo.github_url}" target="_blank">${repo.github_url}</a></td></tr>
                            <tr><td><strong>Owner:</strong></td><td>${repo.repository_owner}</td></tr>
                            <tr><td><strong>Name:</strong></td><td>${repo.repository_name}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-${repo.status === 'verified' ? 'success' : repo.status === 'pending' ? 'warning' : 'danger'}">${repo.status}</span></td></tr>
                            <tr><td><strong>Submitted:</strong></td><td>${new Date(repo.created_at).toLocaleString()}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Submitted By</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${repo.submitted_by_name}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${repo.submitted_by_email}</td></tr>
                            <tr><td><strong>Role:</strong></td><td><span class="badge bg-secondary">${repo.submitted_by_role}</span></td></tr>
                        </table>
                    </div>
                </div>
                
                ${Object.keys(githubData).length > 0 ? `
                <hr>
                <h6>GitHub Data</h6>
                <div class="row">
                    <div class="col-12">
                        <table class="table table-sm">
                            ${githubData.description ? `<tr><td><strong>Description:</strong></td><td>${githubData.description}</td></tr>` : ''}
                            ${githubData.language ? `<tr><td><strong>Language:</strong></td><td>${githubData.language}</td></tr>` : ''}
                            ${githubData.stars !== undefined ? `<tr><td><strong>Stars:</strong></td><td>${githubData.stars}</td></tr>` : ''}
                            ${githubData.forks !== undefined ? `<tr><td><strong>Forks:</strong></td><td>${githubData.forks}</td></tr>` : ''}
                            ${githubData.created_at ? `<tr><td><strong>Created:</strong></td><td>${new Date(githubData.created_at).toLocaleDateString()}</td></tr>` : ''}
                            ${githubData.updated_at ? `<tr><td><strong>Updated:</strong></td><td>${new Date(githubData.updated_at).toLocaleDateString()}</td></tr>` : ''}
                        </table>
                    </div>
                </div>
                ` : '<p class="text-muted">No GitHub data available</p>'}
            `;

            document.getElementById('repoDetailsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('repoDetailsModal')).show();
        }
    </script>
</body>

</html>