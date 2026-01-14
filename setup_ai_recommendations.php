<?php
require_once 'includes/db.php';

/**
 * Setup script for AI Mentor Recommendation System
 * This script creates the necessary database tables and initial data
 */

try {
    echo "<h2>Setting up AI Mentor Recommendation System...</h2>\n";
    
    // Read and execute the SQL file
    $sql_file = 'sql/mentor_recommendation_system.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("SQL file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split SQL statements (simple approach)
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    echo "<p>Executing " . count($statements) . " SQL statements...</p>\n";
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $success_count++;
            
            // Show progress for table creation
            if (preg_match('/CREATE TABLE.*?(\w+)/i', $statement, $matches)) {
                echo "<p>✓ Created table: {$matches[1]}</p>\n";
            } elseif (preg_match('/INSERT.*?INTO\s+(\w+)/i', $statement, $matches)) {
                echo "<p>✓ Inserted data into: {$matches[1]}</p>\n";
            }
            
        } catch (PDOException $e) {
            $error_count++;
            echo "<p style='color: orange;'>⚠ Warning: " . $e->getMessage() . "</p>\n";
        }
    }
    
    echo "<h3>Setup Results:</h3>\n";
    echo "<p>✓ Successfully executed: $success_count statements</p>\n";
    if ($error_count > 0) {
        echo "<p style='color: orange;'>⚠ Warnings/Errors: $error_count (likely tables already exist)</p>\n";
    }
    
    // Verify table creation
    $tables_to_check = ['skills', 'user_skills', 'mentor_recommendations', 'team_skill_requirements'];
    $existing_tables = [];
    
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            $existing_tables[] = $table;
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Table '$table' not found or accessible</p>\n";
        }
    }
    
    if (count($existing_tables) === count($tables_to_check)) {
        echo "<p style='color: green;'><strong>✓ All required tables are ready!</strong></p>\n";
    }
    
    // Show some statistics
    echo "<h3>Current Statistics:</h3>\n";
    
    try {
        $skill_count = $pdo->query("SELECT COUNT(*) FROM skills")->fetchColumn();
        echo "<p>• Skills in database: $skill_count</p>\n";
        
        $users_with_tech_stack = $pdo->query("SELECT COUNT(*) FROM users WHERE tech_stack IS NOT NULL AND tech_stack != ''")->fetchColumn();
        echo "<p>• Users with tech stack data: $users_with_tech_stack</p>\n";
        
        $user_skills_count = $pdo->query("SELECT COUNT(*) FROM user_skills")->fetchColumn();
        echo "<p>• User-skill mappings: $user_skills_count</p>\n";
        
        $mentors_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'")->fetchColumn();
        $participants_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'participant'")->fetchColumn();
        
        echo "<p>• Mentors: $mentors_count</p>\n";
        echo "<p>• Participants: $participants_count</p>\n";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Could not retrieve statistics: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>Next Steps:</h3>\n";
    echo "<ol>\n";
    echo "<li><a href='admin/migrate_user_skills.php'>Run the skill migration script</a> to convert existing tech_stack data</li>\n";
    echo "<li><a href='admin/ai_mentor_recommendations.php'>Access the AI Recommendations panel</a> to generate mentor recommendations</li>\n";
    echo "<li><a href='admin/dashboard.php'>Return to the admin dashboard</a></li>\n";
    echo "</ol>\n";
    
    echo "<p style='color: green; font-weight: bold; margin-top: 20px;'>🎉 AI Mentor Recommendation System setup completed!</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Setup failed:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p>Please check your database connection and permissions.</p>\n";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Recommendations Setup - HackMate</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            background-color: #f5f5f5;
        }
        
        h2, h3 {
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 5px;
        }
        
        p {
            margin: 10px 0;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #007cba;
        }
        
        ol, ul {
            background: white;
            padding: 20px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
        }
        
        a {
            color: #007cba;
            text-decoration: none;
            font-weight: bold;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Content is generated by PHP above -->
    </div>
</body>
</html>