<?php
require_once 'includes/auth_check.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Hackathon Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg text-center max-w-md w-full">
        <i class="fas fa-exclamation-triangle text-red-500 text-6xl mb-6"></i>
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Unauthorized Access</h1>
        <p class="text-gray-600 mb-6">You do not have permission to view this page.</p>
        <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md transition-colors">
            <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
        </a>
    </div>
</body>
</html>
