<?php
require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';

checkAuth('mentor');
$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Guidelines - <?php echo htmlspecialchars(getSystemSetting('hackathon_name', 'HackMate')); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
                <div class="flex items-center justify-between h-16 px-4">
                    <button onclick="toggleSidebar()" class="text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">Guidelines</h1>
                    <div class="w-8"></div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto">
                <div class="p-6">
                    <!-- Page Header -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Mentor Guidelines</h1>
                                <p class="text-gray-600 mt-1">Essential guidelines and best practices for effective mentoring</p>
                            </div>
                         
                        </div>
                    </div>

                    <!-- Guidelines Content -->
                    <div class="space-y-8">
                        <!-- Role Overview -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                <i class="fas fa-user-tie text-green-500 mr-2"></i>
                                Your Role as a Mentor
                            </h2>
                            <div class="prose max-w-none text-gray-700">
                                <p class="mb-4">As a mentor in this hackathon, you play a crucial role in guiding and supporting teams throughout their journey. Your expertise and guidance can make the difference between a good project and an exceptional one.</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                        <h3 class="font-semibold text-green-900 mb-2">
                                            <i class="fas fa-lightbulb mr-2"></i>
                                            Guide & Inspire
                                        </h3>
                                        <p class="text-green-700 text-sm">Help teams brainstorm ideas, overcome challenges, and think creatively about their solutions.</p>
                                    </div>
                                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                        <h3 class="font-semibold text-blue-900 mb-2">
                                            <i class="fas fa-tools mr-2"></i>
                                            Technical Support
                                        </h3>
                                        <p class="text-blue-700 text-sm">Provide technical expertise and help teams navigate complex implementation challenges.</p>
                                    </div>
                                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                        <h3 class="font-semibold text-purple-900 mb-2">
                                            <i class="fas fa-star mr-2"></i>
                                            Evaluate Progress
                                        </h3>
                                        <p class="text-purple-700 text-sm">Assess team performance fairly and provide constructive feedback during scoring rounds.</p>
                                    </div>
                                    <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                                        <h3 class="font-semibold text-orange-900 mb-2">
                                            <i class="fas fa-heart mr-2"></i>
                                            Motivate Teams
                                        </h3>
                                        <p class="text-orange-700 text-sm">Keep teams motivated and focused, especially during challenging moments.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scoring Guidelines -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                <i class="fas fa-star text-yellow-500 mr-2"></i>
                                Scoring Guidelines
                            </h2>
                            <div class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                        <h3 class="font-semibold text-blue-900 mb-2">Innovation (25 pts)</h3>
                                        <ul class="text-sm text-blue-700 space-y-1">
                                            <li>• Originality of idea</li>
                                            <li>• Creative problem-solving</li>
                                            <li>• Unique approach</li>
                                            <li>• Market potential</li>
                                        </ul>
                                    </div>
                                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                        <h3 class="font-semibold text-green-900 mb-2">Technical (25 pts)</h3>
                                        <ul class="text-sm text-green-700 space-y-1">
                                            <li>• Code quality</li>
                                            <li>• Technical complexity</li>
                                            <li>• Implementation skill</li>
                                            <li>• Use of technology</li>
                                        </ul>
                                    </div>
                                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                        <h3 class="font-semibold text-purple-900 mb-2">Presentation (25 pts)</h3>
                                        <ul class="text-sm text-purple-700 space-y-1">
                                            <li>• Clarity of explanation</li>
                                            <li>• Demo effectiveness</li>
                                            <li>• Visual design</li>
                                            <li>• Communication skills</li>
                                        </ul>
                                    </div>
                                    <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                                        <h3 class="font-semibold text-orange-900 mb-2">Teamwork (25 pts)</h3>
                                        <ul class="text-sm text-orange-700 space-y-1">
                                            <li>• Collaboration quality</li>
                                            <li>• Task distribution</li>
                                            <li>• Team dynamics</li>
                                            <li>• Leadership</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                    <h3 class="font-semibold text-yellow-900 mb-2">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Scoring Best Practices
                                    </h3>
                                    <ul class="text-sm text-yellow-700 space-y-1">
                                        <li>• Be consistent across all teams</li>
                                        <li>• Consider the team's skill level and experience</li>
                                        <li>• Focus on effort and improvement, not just results</li>
                                        <li>• Provide constructive feedback with scores</li>
                                        <li>• Be fair and objective in your evaluation</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Communication Guidelines -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                <i class="fas fa-comments text-blue-500 mr-2"></i>
                                Communication Guidelines
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-3">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                        Do's
                                    </h3>
                                    <ul class="space-y-2 text-gray-700">
                                        <li class="flex items-start">
                                            <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                            <span>Listen actively to team concerns and ideas</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                            <span>Ask open-ended questions to encourage thinking</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                            <span>Provide specific, actionable feedback</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                            <span>Encourage experimentation and learning</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                            <span>Respond promptly to support requests</span>
                                        </li>
                                    </ul>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 mb-3">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                                        Don'ts
                                    </h3>
                                    <ul class="space-y-2 text-gray-700">
                                        <li class="flex items-start">
                                            <i class="fas fa-times text-red-500 mr-2 mt-1 text-xs"></i>
                                            <span>Don't solve problems for teams - guide them</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-times text-red-500 mr-2 mt-1 text-xs"></i>
                                            <span>Don't impose your preferred solutions</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-times text-red-500 mr-2 mt-1 text-xs"></i>
                                            <span>Don't be overly critical or discouraging</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-times text-red-500 mr-2 mt-1 text-xs"></i>
                                            <span>Don't favor certain teams over others</span>
                                        </li>
                                        <li class="flex items-start">
                                            <i class="fas fa-times text-red-500 mr-2 mt-1 text-xs"></i>
                                            <span>Don't ignore support messages or requests</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Platform Usage -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                <i class="fas fa-laptop text-purple-500 mr-2"></i>
                                Platform Usage
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="font-semibold text-gray-900 mb-2">
                                        <i class="fas fa-tachometer-alt text-blue-500 mr-2"></i>
                                        Dashboard
                                    </h3>
                                    <p class="text-sm text-gray-700">Monitor your assigned teams, view support messages, and track scoring progress.</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="font-semibold text-gray-900 mb-2">
                                        <i class="fas fa-star text-yellow-500 mr-2"></i>
                                        Scoring
                                    </h3>
                                    <p class="text-sm text-gray-700">Score teams during active mentoring rounds using the structured scoring system.</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="font-semibold text-gray-900 mb-2">
                                        <i class="fas fa-life-ring text-red-500 mr-2"></i>
                                        Support
                                    </h3>
                                    <p class="text-sm text-gray-700">Respond to team support requests and provide timely assistance.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contacts -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                <i class="fas fa-phone text-red-500 mr-2"></i>
                                Need Help?
                            </h2>
                            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                <p class="text-red-700 mb-4">If you encounter any issues or need assistance, don't hesitate to reach out:</p>
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <a href="contact_admin.php" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors text-center">
                                        <i class="fas fa-envelope mr-2"></i>
                                        Contact Admin
                                    </a>
                                    <a href="support_messages.php" class="bg-white text-red-600 px-4 py-2 rounded-lg text-sm font-medium border border-red-300 hover:bg-red-50 transition-colors text-center">
                                        <i class="fas fa-life-ring mr-2"></i>
                                        View Support Messages
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>