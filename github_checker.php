<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/github_checker_component.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Repository Checker - HackMate</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .navbar-brand {
            font-weight: bold;
        }
        
        .main-content {
            padding: 2rem 0;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-code me-2"></i>
                HackMate
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="github_checker.php">
                            <i class="fab fa-github me-1"></i>
                            GitHub Checker
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($user_name); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container text-center">
            <div class="feature-icon">
                <i class="fab fa-github"></i>
            </div>
            <h1 class="display-4 mb-3">GitHub Repository Checker</h1>
            <p class="lead">
                Verify and submit your GitHub repositories for the hackathon. 
                We'll check if they exist and ensure original submissions only.
            </p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container main-content">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php echo renderGitHubChecker($user_id); ?>
            </div>
        </div>
        
        <!-- How it Works Section -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            How It Works
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <i class="fas fa-link fa-2x text-primary mb-2"></i>
                                    <h6>1. Submit URL</h6>
                                    <p class="text-muted small">
                                        Enter your GitHub repository URL in the format: 
                                        https://github.com/username/repository
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <i class="fas fa-search fa-2x text-info mb-2"></i>
                                    <h6>2. Verification</h6>
                                    <p class="text-muted small">
                                        We check if the repository exists on GitHub and hasn't been 
                                        submitted before by any participant.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center mb-3">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <h6>3. Confirmation</h6>
                                    <p class="text-muted small">
                                        If valid and original, your repository is saved and you'll 
                                        see it in your submissions list.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success">
                                    <i class="fas fa-check me-2"></i>
                                    Valid Examples
                                </h6>
                                <ul class="list-unstyled small text-muted">
                                    <li><code>https://github.com/facebook/react</code></li>
                                    <li><code>https://github.com/microsoft/vscode</code></li>
                                    <li><code>https://github.com/your-username/your-project</code></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-danger">
                                    <i class="fas fa-times me-2"></i>
                                    Invalid Examples
                                </h6>
                                <ul class="list-unstyled small text-muted">
                                    <li><code>github.com/username/repo</code> (missing https://)</li>
                                    <li><code>https://gitlab.com/username/repo</code> (not GitHub)</li>
                                    <li><code>https://github.com/username</code> (missing repository)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">
                <i class="fas fa-code me-2"></i>
                HackMate - GitHub Repository Checker
            </p>
            <small class="text-muted">Ensuring original and valid submissions for hackathons</small>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>