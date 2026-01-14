<?php
require_once __DIR__ . '/system_settings.php';

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDateTime($datetime_str) {
    if (!$datetime_str) return 'N/A';
    return formatSystemDateTime($datetime_str, 'F j, Y H:i'); // e.g., January 1, 2024 15:30
}

function formatDateTimeShort($datetime_str) {
    if (!$datetime_str) return 'N/A';
    return formatSystemDateTime($datetime_str, 'M j, H:i'); // e.g., Jan 1, 15:30
}

function getTimeRemaining($end_time) {
    $now = time();
    $end = strtotime($end_time);
    $diff = $end - $now;
    
    if ($diff <= 0) {
        return ['expired' => true];
    }
    
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
    $minutes = floor(($diff % (60 * 60)) / 60);
    $seconds = $diff % 60;
    
    return [
        'expired' => false,
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'total_seconds' => $diff
    ];
}

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'approved':
        case 'resolved':
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'pending':
        case 'open':
            return 'bg-yellow-100 text-yellow-800';
        case 'rejected':
        case 'inactive':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getRoleBadgeClass($role) {
    switch(strtolower($role)) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'mentor':
            return 'bg-green-100 text-green-800';
        case 'volunteer':
            return 'bg-purple-100 text-purple-800';
        case 'participant':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function truncateText($text, $maxLength) {
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength) . '...';
    }
    return $text;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function sendNotification($message, $type = 'info') {
    $_SESSION['notification'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getNotification() {
    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
        return $notification;
    }
    return null;
}

function getFloorRoomName($pdo, $floor_id, $room_id) {
    $stmt = $pdo->prepare("SELECT f.floor_number, r.room_number FROM floors f JOIN rooms r ON f.id = r.floor_id WHERE f.id = ? AND r.id = ?");
    $stmt->execute([$floor_id, $room_id]);
    $location = $stmt->fetch();
    return $location ? $location['floor_number'] . ' - ' . $location['room_number'] : 'N/A';
}

function timeAgo($datetime_str) {
    if (!$datetime_str) return 'N/A';
    
    $time = strtotime($datetime_str);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months != 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years != 1 ? 's' : '') . ' ago';
    }
}
?>
