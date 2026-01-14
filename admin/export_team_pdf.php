<?php
// Prevent any output before PDF generation
ob_start();

// Disable BOM and ensure clean output
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
require_once '../includes/utils.php';
require_once '../vendor/autoload.php'; // Include Composer's autoloader for Dompdf

checkAuth('admin');

// Clear any output buffer and ensure clean state
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['team_id'])) {
    die('Team ID is required.');
}

$team_id = intval($_GET['team_id']);

// Fetch team details
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.name as leader_name, u.email as leader_email,
               f.floor_number, r.room_number,
               (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
        FROM teams t 
        LEFT JOIN users u ON t.leader_id = u.id
        LEFT JOIN floors f ON t.floor_id = f.id
        LEFT JOIN rooms r ON t.room_id = r.id
        WHERE t.id = ?
    ");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

if (!$team) {
    die('Team not found.');
}

// Fetch team members
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role, tm.joined_at 
        FROM team_members tm 
        JOIN users u ON tm.user_id = u.id 
        WHERE tm.team_id = ?
        ORDER BY tm.joined_at ASC
    ");
    $stmt->execute([$team_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error fetching members: ' . $e->getMessage());
}

// Fetch team submission
try {
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error fetching submission: ' . $e->getMessage());
}

// Fetch team scores
try {
    $stmt = $pdo->prepare("
        SELECT s.score, s.comment, mr.round_name, mr.max_score, u.name as mentor_name, s.created_at
        FROM scores s 
        JOIN mentoring_rounds mr ON s.round_id = mr.id 
        JOIN users u ON s.mentor_id = u.id
        WHERE s.team_id = ?
        ORDER BY mr.start_time DESC, u.name ASC
    ");
    $stmt->execute([$team_id]);
    $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If scores table doesn't exist or has issues, just set empty array
    $scores = [];
}

// HTML content for the PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Team Report - ' . htmlspecialchars($team['name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
        h1 { color: #4A0E6F; text-align: center; margin-bottom: 20px; }
        h2 { color: #6B21A8; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 20px; margin-bottom: 10px; }
        h3 { color: #8B5CF6; margin-top: 15px; margin-bottom: 8px; }
        .section { margin-bottom: 20px; padding: 10px; border: 1px solid #eee; border-radius: 5px; }
        .info-grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-row { display: table-row; }
        .info-label, .info-value { display: table-cell; padding: 5px 0; vertical-align: top; }
        .info-label { font-weight: bold; width: 120px; color: #555; }
        .info-value { color: #333; }
        .member-list, .score-list { list-style: none; padding: 0; margin: 0; }
        .member-item, .score-item { background-color: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-bottom: 8px; border-radius: 4px; }
        .member-item strong, .score-item strong { color: #333; }
        .comment { font-style: italic; color: #666; margin-top: 5px; }
        .status-badge { 
            display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: bold;
            color: white; 
        }
        .status-approved { background-color: #22C55E; }
        .status-pending { background-color: #F59E0B; }
        .status-rejected { background-color: #EF4444; }
        .text-muted { color: #777; font-size: 10px; }
        .break-all { word-break: break-all; }
    </style>
</head>
<body>
    <h1>Hackathon Team Report</h1>
    <h1 style="color: #4A0E6F;">' . htmlspecialchars($team['name']) . '</h1>

    <div class="section">
        <h2>Team Overview</h2>
        <div class="info-grid">
            <div class="info-row"><div class="info-label">Team Name:</div><div class="info-value">' . htmlspecialchars($team['name']) . '</div></div>
            <div class="info-row"><div class="info-label">Leader:</div><div class="info-value">' . htmlspecialchars($team['leader_name']) . ' (' . htmlspecialchars($team['leader_email']) . ')</div></div>
            <div class="info-row"><div class="info-label">Members:</div><div class="info-value">' . htmlspecialchars($team['member_count']) . '/4</div></div>
            <div class="info-row"><div class="info-label">Location:</div><div class="info-value">' . ($team['floor_number'] ? htmlspecialchars($team['floor_number'] . ' - ' . $team['room_number']) : 'N/A') . '</div></div>
            <div class="info-row"><div class="info-label">Status:</div><div class="info-value">
                <span class="status-badge status-' . strtolower($team['status']) . '">' . htmlspecialchars(ucfirst($team['status'])) . '</span>
            </div></div>
            <div class="info-row"><div class="info-label">Created:</div><div class="info-value">' . formatDateTime($team['created_at']) . '</div></div>
        </div>
        <h3>Project Idea:</h3>
        <p>' . nl2br(htmlspecialchars($team['idea'] ?: 'Not provided yet')) . '</p>
        <h3>Problem Statement:</h3>
        <p>' . nl2br(htmlspecialchars($team['problem_statement'] ?: 'Not provided yet')) . '</p>
    </div>

    <div class="section">
        <h2>Team Members</h2>
        <ul class="member-list">
';
foreach ($members as $member) {
    $html .= '
            <li class="member-item">
                <strong>' . htmlspecialchars($member['name']) . '</strong> (' . htmlspecialchars($member['email']) . ')<br>
                <span class="text-muted">' . htmlspecialchars(ucfirst($member['role'])) . '</span>
                ' . ($member['id'] == $team['leader_id'] ? '<span style="font-weight: bold; color: #F59E0B; margin-left: 10px;">(Leader)</span>' : '') . '
            </li>
    ';
}
$html .= '
        </ul>
    </div>

    <div class="section">
        <h2>Project Submission</h2>
';
if ($submission) {
    $html .= '
        <div class="info-grid">
            <div class="info-row"><div class="info-label">Status:</div><div class="info-value">Submitted</div></div>
            <div class="info-row"><div class="info-label">Submitted At:</div><div class="info-value">' . formatDateTime($submission['submitted_at']) . '</div></div>
            <div class="info-row"><div class="info-label">GitHub:</div><div class="info-value break-all"><a href="' . htmlspecialchars($submission['github_link']) . '">' . htmlspecialchars($submission['github_link']) . '</a></div></div>
            <div class="info-row"><div class="info-label">Live Demo:</div><div class="info-value break-all">' . ($submission['live_link'] ? '<a href="' . htmlspecialchars($submission['live_link']) . '">' . htmlspecialchars($submission['live_link']) . '</a>' : 'N/A') . '</div></div>
            <div class="info-row"><div class="info-label">Tech Stack:</div><div class="info-value">' . htmlspecialchars($submission['tech_stack']) . '</div></div>
            <div class="info-row"><div class="info-label">Demo Video:</div><div class="info-value break-all">' . ($submission['demo_video'] ? '<a href="' . htmlspecialchars($submission['demo_video']) . '">' . htmlspecialchars($submission['demo_video']) . '</a>' : 'N/A') . '</div></div>
        </div>
    ';
} else {
    $html .= '<p>No project submission yet.</p>';
}
$html .= '
    </div>

    <div class="section">
        <h2>Mentor Scores & Feedback</h2>
';
if (!empty($scores)) {
    $html .= '<ul class="score-list">';
    foreach ($scores as $score) {
        $html .= '
            <li class="score-item">
                <strong>' . htmlspecialchars($score['round_name']) . '</strong> by ' . htmlspecialchars($score['mentor_name']) . '<br>
                Score: <strong>' . htmlspecialchars($score['score']) . '/' . htmlspecialchars($score['max_score']) . '</strong><br>
                <span class="comment">' . nl2br(htmlspecialchars($score['comment'] ?: 'No comment provided.')) . '</span><br>
                <span class="text-muted">Scored on ' . formatDateTime($score['created_at']) . '</span>
            </li>
        ';
    }
    $html .= '</ul>';
} else {
    $html .= '<p>No scores or feedback recorded yet.</p>';
}
$html .= '
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #888;">
        Report generated on ' . date('F j, Y, H:i:s') . '
    </div>
</body>
</html>
';

// Configure Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false); // Disable for security
$options->set('defaultFont', 'Arial');
$options->set('isPhpEnabled', false); // Disable PHP execution in templates

try {
    $dompdf = new Dompdf($options);
    
    // Load HTML
    $dompdf->loadHtml($html);
    
    // Setup the paper size and orientation
    $dompdf->setPaper('A4', 'portrait');
    
    // Render the HTML as PDF
    $dompdf->render();
    
    // Get PDF content
    $pdf_content = $dompdf->output();
    
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Team_Report_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $team['name']) . '.pdf"');
    header('Content-Length: ' . strlen($pdf_content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output the PDF content directly
    echo $pdf_content;
    exit;
    
} catch (Exception $e) {
    // If PDF generation fails, show error
    header('Content-Type: text/html');
    die('PDF Generation Error: ' . htmlspecialchars($e->getMessage()));
}
?>
