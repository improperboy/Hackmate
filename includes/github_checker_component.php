<?php
// GitHub Repository Checker Component
function renderGitHubChecker($user_id = null) {
    if (!$user_id) {
        return '<div class="alert alert-warning">Please log in to use the GitHub repository checker.</div>';
    }
    
    ob_start();
    ?>
    <div class="github-checker-container">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fab fa-github me-2"></i>
                    GitHub Repository Checker
                </h5>
            </div>
            <div class="card-body">
                <form id="githubCheckerForm">
                    <div class="mb-3">
                        <label for="githubUrl" class="form-label">GitHub Repository URL</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fab fa-github"></i>
                            </span>
                            <input 
                                type="url" 
                                class="form-control" 
                                id="githubUrl" 
                                name="github_url"
                                placeholder="https://github.com/username/repository"
                                required
                            >
                            <button class="btn btn-primary" type="submit" id="checkRepoBtn">
                                <span class="btn-text">Check Repository</span>
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                        </div>
                        <div class="form-text">
                            Enter a valid GitHub repository URL. We'll check if it exists and hasn't been submitted before.
                        </div>
                    </div>
                </form>
                
                <!-- Results Area -->
                <div id="checkerResults" class="mt-3"></div>
                
                <!-- My Submissions -->
                <div class="mt-4">
                    <h6>My Submitted Repositories</h6>
                    <div id="myRepositories" class="mt-2">
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            <span class="ms-2">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .github-checker-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .repo-item {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        background: #f8f9fa;
    }
    
    .repo-item.verified {
        border-color: #198754;
        background: #d1e7dd;
    }
    
    .repo-item.pending {
        border-color: #ffc107;
        background: #fff3cd;
    }
    
    .repo-item.invalid {
        border-color: #dc3545;
        background: #f8d7da;
    }
    
    .repo-meta {
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    .repo-stats {
        display: flex;
        gap: 15px;
        margin-top: 8px;
    }
    
    .repo-stat {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    .url-validation {
        border-left: 4px solid #dc3545;
        padding-left: 10px;
        margin-top: 5px;
        font-size: 0.875rem;
        color: #dc3545;
    }
    
    .url-validation.valid {
        border-color: #198754;
        color: #198754;
    }
    
    .btn-loading {
        pointer-events: none;
        opacity: 0.6;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('githubCheckerForm');
        const urlInput = document.getElementById('githubUrl');
        const checkBtn = document.getElementById('checkRepoBtn');
        const resultsDiv = document.getElementById('checkerResults');
        const myReposDiv = document.getElementById('myRepositories');
        
        // Real-time URL validation
        urlInput.addEventListener('input', function() {
            validateGitHubUrl(this.value);
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            checkRepository();
        });
        
        // Load user's repositories on page load
        loadMyRepositories();
        
        function validateGitHubUrl(url) {
            const pattern = /^https:\/\/github\.com\/[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+\/?$/;
            const isValid = pattern.test(url);
            
            // Remove existing validation message
            const existingValidation = urlInput.parentNode.parentNode.querySelector('.url-validation');
            if (existingValidation) {
                existingValidation.remove();
            }
            
            if (url && !isValid) {
                const validationDiv = document.createElement('div');
                validationDiv.className = 'url-validation';
                validationDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Invalid GitHub URL format';
                urlInput.parentNode.parentNode.appendChild(validationDiv);
                checkBtn.disabled = true;
            } else if (url && isValid) {
                const validationDiv = document.createElement('div');
                validationDiv.className = 'url-validation valid';
                validationDiv.innerHTML = '<i class="fas fa-check me-1"></i>Valid GitHub URL format';
                urlInput.parentNode.parentNode.appendChild(validationDiv);
                checkBtn.disabled = false;
            } else {
                checkBtn.disabled = false;
            }
        }
        
        function checkRepository() {
            const url = urlInput.value.trim();
            
            if (!url) {
                showResult('error', 'Please enter a GitHub repository URL');
                return;
            }
            
            setLoading(true);
            
            fetch('/api/github_checker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ github_url: url })
            })
            .then(response => response.json())
            .then(data => {
                setLoading(false);
                
                if (data.success) {
                    showResult('success', data.message, data.repository);
                    urlInput.value = '';
                    loadMyRepositories(); // Refresh the list
                } else {
                    showResult('error', data.error || 'An error occurred', data.existing_submission);
                }
            })
            .catch(error => {
                setLoading(false);
                console.error('Error:', error);
                showResult('error', 'Network error. Please try again.');
            });
        }
        
        function loadMyRepositories() {
            fetch('/api/github_checker.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRepositories(data.repositories);
                } else {
                    myReposDiv.innerHTML = '<div class="text-muted">No repositories submitted yet.</div>';
                }
            })
            .catch(error => {
                console.error('Error loading repositories:', error);
                myReposDiv.innerHTML = '<div class="text-danger">Error loading repositories.</div>';
            });
        }
        
        function displayRepositories(repositories) {
            if (repositories.length === 0) {
                myReposDiv.innerHTML = '<div class="text-muted">No repositories submitted yet.</div>';
                return;
            }
            
            const html = repositories.map(repo => {
                const githubData = repo.github_data ? JSON.parse(repo.github_data) : {};
                const statusClass = repo.status;
                const statusIcon = {
                    'verified': 'fas fa-check-circle text-success',
                    'pending': 'fas fa-clock text-warning',
                    'invalid': 'fas fa-times-circle text-danger'
                }[repo.status] || 'fas fa-question-circle';
                
                return `
                    <div class="repo-item ${statusClass}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <a href="${repo.github_url}" target="_blank" class="text-decoration-none">
                                        ${repo.repository_owner}/${repo.repository_name}
                                    </a>
                                </h6>
                                ${githubData.description ? `<p class="mb-2 text-muted">${githubData.description}</p>` : ''}
                                <div class="repo-stats">
                                    ${githubData.language ? `<span class="repo-stat"><i class="fas fa-code"></i> ${githubData.language}</span>` : ''}
                                    ${githubData.stars ? `<span class="repo-stat"><i class="fas fa-star"></i> ${githubData.stars}</span>` : ''}
                                    ${githubData.forks ? `<span class="repo-stat"><i class="fas fa-code-branch"></i> ${githubData.forks}</span>` : ''}
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-${statusClass === 'verified' ? 'success' : statusClass === 'pending' ? 'warning' : 'danger'}">
                                    <i class="${statusIcon}"></i> ${repo.status}
                                </span>
                                <div class="repo-meta mt-1">
                                    Submitted: ${new Date(repo.created_at).toLocaleDateString()}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            myReposDiv.innerHTML = html;
        }
        
        function showResult(type, message, data = null) {
            let alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            let icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            
            let html = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="${icon} me-2"></i>
                    <strong>${message}</strong>
            `;
            
            if (data && type === 'success') {
                html += `
                    <div class="mt-2">
                        <small>
                            Repository: <strong>${data.owner}/${data.name}</strong><br>
                            ${data.github_data.description ? `Description: ${data.github_data.description}<br>` : ''}
                            ${data.github_data.language ? `Language: ${data.github_data.language}` : ''}
                        </small>
                    </div>
                `;
            }
            
            html += `
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            resultsDiv.innerHTML = html;
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    const alert = resultsDiv.querySelector('.alert');
                    if (alert) {
                        alert.remove();
                    }
                }, 5000);
            }
        }
        
        function setLoading(loading) {
            const btnText = checkBtn.querySelector('.btn-text');
            const spinner = checkBtn.querySelector('.spinner-border');
            
            if (loading) {
                checkBtn.classList.add('btn-loading');
                btnText.textContent = 'Checking...';
                spinner.classList.remove('d-none');
                checkBtn.disabled = true;
            } else {
                checkBtn.classList.remove('btn-loading');
                btnText.textContent = 'Check Repository';
                spinner.classList.add('d-none');
                checkBtn.disabled = false;
            }
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
?>