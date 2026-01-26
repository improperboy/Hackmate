-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql305.infinityfree.com
-- Generation Time: Jan 10, 2026 at 03:06 PM
-- Server version: 11.4.9-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_39620646_hackmateold`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL COMMENT 'team, user, submission, etc.',
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional action details',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `floors`
--

CREATE TABLE `floors` (
  `id` int(11) NOT NULL,
  `floor_number` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `floors`
--

INSERT INTO `floors` (`id`, `floor_number`, `description`, `created_at`) VALUES
(1, 'F1', 'First Floor - Main Event Area', '2025-09-26 19:43:22'),
(2, 'F2', 'Second Floor - Development Zones', '2025-09-26 19:43:22'),
(6, 'f6', '', '2025-11-13 10:44:15');

-- --------------------------------------------------------

--
-- Table structure for table `github_repositories`
--

CREATE TABLE `github_repositories` (
  `id` int(11) NOT NULL,
  `github_url` varchar(500) NOT NULL,
  `repository_name` varchar(255) NOT NULL,
  `repository_owner` varchar(255) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `status` enum('verified','pending','invalid') DEFAULT 'pending',
  `github_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Cached GitHub API response data',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `join_requests`
--

CREATE TABLE `join_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','expired') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mentoring_rounds`
--

CREATE TABLE `mentoring_rounds` (
  `id` int(11) NOT NULL,
  `round_name` varchar(255) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `max_score` int(11) NOT NULL DEFAULT 100,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `mentoring_rounds`
--

INSERT INTO `mentoring_rounds` (`id`, `round_name`, `start_time`, `end_time`, `max_score`, `description`, `is_active`, `created_at`) VALUES
(5, 'Team Formation & Ideation', '2024-03-15 09:00:00', '2024-03-15 12:00:00', 50, 'Initial team formation and project ideation phase', 1, '2025-09-26 19:43:45'),
(6, 'Mid-Progress Review', '2024-03-15 14:00:00', '2024-03-15 17:00:00', 75, 'Mid-hackathon progress review and guidance', 1, '2025-09-26 19:43:45'),
(7, 'Technical Implementation', '2024-03-16 09:00:00', '2024-03-16 12:00:00', 100, 'Technical implementation and development review', 1, '2025-09-26 19:43:45'),
(8, 'Final Presentation', '2025-11-14 14:00:00', '2025-11-15 18:00:00', 150, 'Final project presentation and judging', 1, '2025-09-26 19:43:45');

-- --------------------------------------------------------

--
-- Table structure for table `mentor_assignments`
--

CREATE TABLE `mentor_assignments` (
  `id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `mentor_assignments`
--

INSERT INTO `mentor_assignments` (`id`, `mentor_id`, `floor_id`, `room_id`, `created_at`) VALUES
(13, 43, 1, 1, '2025-11-13 17:35:14'),
(17, 42, 1, 1, '2025-11-14 06:06:56');

-- --------------------------------------------------------

--
-- Table structure for table `mentor_recommendations`
--

CREATE TABLE `mentor_recommendations` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `match_score` decimal(5,2) NOT NULL,
  `skill_match_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `type` enum('announcement','support','urgent','general','team_update','score_update') NOT NULL DEFAULT 'general',
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `target_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of target roles',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `receive_announcements` tinyint(1) DEFAULT 1,
  `receive_support_notifications` tinyint(1) DEFAULT 1,
  `receive_team_updates` tinyint(1) DEFAULT 1,
  `receive_score_updates` tinyint(1) DEFAULT 1,
  `receive_invitation_notifications` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 0,
  `push_notifications` tinyint(1) DEFAULT 1,
  `quiet_hours_start` time DEFAULT '22:00:00',
  `quiet_hours_end` time DEFAULT '08:00:00',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `notification_preferences`
--

INSERT INTO `notification_preferences` (`id`, `user_id`, `receive_announcements`, `receive_support_notifications`, `receive_team_updates`, `receive_score_updates`, `receive_invitation_notifications`, `email_notifications`, `push_notifications`, `quiet_hours_start`, `quiet_hours_end`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, 1, 0, 1, '22:00:00', '08:00:00', '2025-09-26 19:43:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `link_url` varchar(500) DEFAULT NULL COMMENT 'Optional external link',
  `link_text` varchar(255) DEFAULT NULL COMMENT 'Link display text',
  `author_id` int(11) NOT NULL,
  `is_pinned` tinyint(1) DEFAULT 0 COMMENT 'Pin important announcements',
  `target_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Target specific roles (null = all)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `title`, `content`, `link_url`, `link_text`, `author_id`, `is_pinned`, `target_roles`, `created_at`, `updated_at`) VALUES
(1, 'Welcome to HackMate 2025!', 'Welcome to our hackathon management system. Please check the dashboard regularly for updates and announcements. Good luck with your projects!', NULL, NULL, 1, 1, NULL, '2025-09-26 19:43:22', '2025-09-27 05:16:57'),
(3, 'htfhax', 'aZHTFAZyj', NULL, NULL, 1, 0, NULL, '2025-11-14 05:41:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh_key` text DEFAULT NULL,
  `auth_key` text DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `capacity` int(11) DEFAULT 4,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `floor_id`, `room_number`, `capacity`, `description`, `created_at`) VALUES
(1, 1, 'R101', 6, 'Main Auditorium', '2025-09-26 19:43:22'),
(3, 1, 'R103', 4, 'Team Room B', '2025-09-26 19:43:22'),
(5, 2, 'R201', 4, 'Development Room A', '2025-09-26 19:43:22'),
(7, 2, 'R203', 4, 'Development Room C', '2025-09-26 19:43:22'),
(16, 6, '101', 2, NULL, '2025-11-13 10:44:47');

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `round_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`id`, `name`, `category`, `created_at`) VALUES
(1, 'JavaScript', 'programming', '2025-09-26 19:47:17'),
(2, 'Python', 'programming', '2025-09-26 19:47:17'),
(3, 'Java', 'programming', '2025-09-26 19:47:17'),
(4, 'C++', 'programming', '2025-09-26 19:47:17'),
(5, 'C#', 'programming', '2025-09-26 19:47:17'),
(6, 'PHP', 'programming', '2025-09-26 19:47:17'),
(7, 'TypeScript', 'programming', '2025-09-26 19:47:17'),
(8, 'Go', 'programming', '2025-09-26 19:47:17'),
(9, 'Rust', 'programming', '2025-09-26 19:47:17'),
(10, 'Swift', 'programming', '2025-09-26 19:47:17'),
(11, 'Kotlin', 'programming', '2025-09-26 19:47:17'),
(12, 'React', 'frontend', '2025-09-26 19:47:17'),
(13, 'Vue.js', 'frontend', '2025-09-26 19:47:17'),
(14, 'Angular', 'frontend', '2025-09-26 19:47:17'),
(15, 'HTML', 'frontend', '2025-09-26 19:47:17'),
(16, 'CSS', 'frontend', '2025-09-26 19:47:17'),
(17, 'Sass', 'frontend', '2025-09-26 19:47:17'),
(18, 'Bootstrap', 'frontend', '2025-09-26 19:47:17'),
(19, 'Tailwind CSS', 'frontend', '2025-09-26 19:47:17'),
(20, 'jQuery', 'frontend', '2025-09-26 19:47:17'),
(21, 'Node.js', 'backend', '2025-09-26 19:47:17'),
(22, 'Express.js', 'backend', '2025-09-26 19:47:17'),
(23, 'Django', 'backend', '2025-09-26 19:47:17'),
(24, 'Flask', 'backend', '2025-09-26 19:47:17'),
(25, 'Spring Boot', 'backend', '2025-09-26 19:47:17'),
(26, 'Laravel', 'backend', '2025-09-26 19:47:17'),
(27, 'ASP.NET', 'backend', '2025-09-26 19:47:17'),
(28, 'FastAPI', 'backend', '2025-09-26 19:47:17'),
(29, 'MySQL', 'database', '2025-09-26 19:47:17'),
(30, 'PostgreSQL', 'database', '2025-09-26 19:47:17'),
(31, 'MongoDB', 'database', '2025-09-26 19:47:17'),
(32, 'Redis', 'database', '2025-09-26 19:47:17'),
(33, 'SQLite', 'database', '2025-09-26 19:47:17'),
(34, 'Oracle', 'database', '2025-09-26 19:47:17'),
(35, 'Cassandra', 'database', '2025-09-26 19:47:17'),
(36, 'Firebase', 'database', '2025-09-26 19:47:17'),
(37, 'AWS', 'cloud', '2025-09-26 19:47:17'),
(38, 'Azure', 'cloud', '2025-09-26 19:47:17'),
(39, 'Google Cloud', 'cloud', '2025-09-26 19:47:17'),
(40, 'Docker', 'devops', '2025-09-26 19:47:17'),
(41, 'Kubernetes', 'devops', '2025-09-26 19:47:17'),
(42, 'Jenkins', 'devops', '2025-09-26 19:47:17'),
(43, 'Git', 'devops', '2025-09-26 19:47:17'),
(44, 'Linux', 'devops', '2025-09-26 19:47:17'),
(45, 'React Native', 'mobile', '2025-09-26 19:47:17'),
(46, 'Flutter', 'mobile', '2025-09-26 19:47:17'),
(47, 'iOS Development', 'mobile', '2025-09-26 19:47:17'),
(48, 'Android Development', 'mobile', '2025-09-26 19:47:17'),
(49, 'Machine Learning', 'ai', '2025-09-26 19:47:17'),
(50, 'Deep Learning', 'ai', '2025-09-26 19:47:17'),
(51, 'TensorFlow', 'ai', '2025-09-26 19:47:17'),
(52, 'PyTorch', 'ai', '2025-09-26 19:47:17'),
(53, 'Data Analysis', 'data', '2025-09-26 19:47:17'),
(54, 'Pandas', 'data', '2025-09-26 19:47:17'),
(55, 'NumPy', 'data', '2025-09-26 19:47:17'),
(56, 'Scikit-learn', 'ai', '2025-09-26 19:47:17'),
(57, 'GraphQL', 'api', '2025-09-26 19:47:17'),
(58, 'REST API', 'api', '2025-09-26 19:47:17'),
(59, 'Microservices', 'architecture', '2025-09-26 19:47:17'),
(60, 'Blockchain', 'emerging', '2025-09-26 19:47:17'),
(61, 'IoT', 'emerging', '2025-09-26 19:47:17'),
(62, 'Cybersecurity', 'security', '2025-09-26 19:47:17'),
(63, 'UI/UX Design', 'design', '2025-09-26 19:47:17'),
(64, 'Project Management', 'management', '2025-09-26 19:47:17');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `github_link` varchar(500) NOT NULL,
  `live_link` varchar(500) DEFAULT NULL,
  `tech_stack` text NOT NULL,
  `demo_video` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'Project description',
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submission_settings`
--

CREATE TABLE `submission_settings` (
  `id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `max_file_size` int(11) DEFAULT 10485760 COMMENT 'Max file size in bytes (10MB default)',
  `allowed_extensions` varchar(255) DEFAULT 'pdf,doc,docx,zip,rar' COMMENT 'Comma-separated file extensions',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `submission_settings`
--

INSERT INTO `submission_settings` (`id`, `start_time`, `end_time`, `is_active`, `max_file_size`, `allowed_extensions`, `created_at`, `updated_at`) VALUES
(2, '2025-11-14 08:00:00', '2025-11-15 22:00:00', 1, 52428800, 'pdf,doc,docx,zip,rar,tar.gz,ppt,pptx', '2025-09-26 19:43:45', '2025-11-14 06:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL,
  `from_role` enum('participant','volunteer','mentor') NOT NULL,
  `to_role` enum('mentor','volunteer','admin') NOT NULL,
  `subject` varchar(255) DEFAULT NULL COMMENT 'Message subject',
  `message` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `floor_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL COMMENT 'Notes added when resolving the message'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0 COMMENT 'Can be accessed by non-admin users',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'hackathon_name', 'HackMate', 'string', 'Name of the hackathon event', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(2, 'hackathon_description', 'Hackathon Management System', 'string', 'Description of the hackathon', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(3, 'max_team_size', '4', 'integer', 'Maximum number of members per team', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(4, 'min_team_size', '1', 'integer', 'Minimum number of members per team', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(5, 'registration_open', '1', 'boolean', 'Whether registration is currently open', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(6, 'hackathon_start_date', '2025-09-27T08:00', 'string', 'Hackathon start date and time', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(7, 'hackathon_end_date', '2025-09-30T02:30', 'string', 'Hackathon end date and time', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(8, 'contact_email', 'divyanshuomar856@gmail.com', 'string', 'Contact email for support', 1, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(9, 'timezone', 'Asia/Kolkata', 'string', 'System timezone', 0, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(10, 'maintenance_mode', '0', 'boolean', 'Enable maintenance mode', 0, '2025-09-26 19:43:22', '2026-01-05 14:46:57'),
(11, 'rankings_visible', '0', 'boolean', 'Whether team rankings are visible to participants', 1, '2025-09-26 19:43:22', '2025-11-14 06:05:06'),
(24, 'show_mentoring_scores_to_participants', '0', 'boolean', 'Whether participants can see actual mentoring scores or just feedback and status', 0, '2025-10-07 13:15:16', '2026-01-05 14:46:57');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `idea` text DEFAULT NULL,
  `problem_statement` text DEFAULT NULL,
  `tech_skills` text DEFAULT NULL,
  `theme_id` int(11) DEFAULT NULL,
  `leader_id` int(11) DEFAULT NULL,
  `floor_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `name`, `idea`, `problem_statement`, `tech_skills`, `theme_id`, `leader_id`, `floor_id`, `room_id`, `status`, `created_at`, `updated_at`) VALUES
(21, 'ifinity', 'xxjbkud', 'aacskkj', 'dcnk', 10, 40, 1, 1, 'approved', '2025-11-14 05:34:26', '2025-11-14 05:36:34');

-- --------------------------------------------------------

--
-- Table structure for table `team_invitations`
--

CREATE TABLE `team_invitations` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL COMMENT 'Team leader sending invitation',
  `to_user_id` int(11) NOT NULL COMMENT 'User receiving invitation',
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `message` text DEFAULT NULL COMMENT 'Personal message from team leader',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `joined_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `user_id`, `team_id`, `status`, `joined_at`) VALUES
(19, 40, 21, 'approved', '2025-11-14 05:36:34');

-- --------------------------------------------------------

--
-- Table structure for table `team_skill_requirements`
--

CREATE TABLE `team_skill_requirements` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `importance_level` enum('nice-to-have','important','critical') DEFAULT 'important',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `themes`
--

CREATE TABLE `themes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color_code` varchar(7) DEFAULT '#3B82F6' COMMENT 'Hex color code for theme display',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `themes`
--

INSERT INTO `themes` (`id`, `name`, `description`, `color_code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Education', 'Educational technology solutions, e-learning platforms, and academic tools', '#10B981', 1, '2025-09-26 19:43:22', NULL),
(2, 'Fintech', 'Financial technology, payment solutions, and banking innovations', '#F59E0B', 1, '2025-09-26 19:43:22', NULL),
(3, 'Blockchain', 'Cryptocurrency, smart contracts, and decentralized applications', '#8B5CF6', 1, '2025-09-26 19:43:22', NULL),
(4, 'Healthcare', 'Medical technology, health monitoring, and wellness applications', '#EF4444', 1, '2025-09-26 19:43:22', NULL),
(5, 'Environment', 'Sustainability, climate change solutions, and green technology', '#22C55E', 1, '2025-09-26 19:43:22', NULL),
(6, 'Social Impact', 'Community solutions, social good, and humanitarian technology', '#EC4899', 1, '2025-09-26 19:43:22', NULL),
(7, 'Gaming', 'Game development, interactive entertainment, and virtual reality', '#F97316', 1, '2025-09-26 19:43:22', NULL),
(8, 'IoT & Hardware', 'Internet of Things, embedded systems, and hardware innovations', '#06B6D4', 1, '2025-09-26 19:43:22', NULL),
(9, 'AI & Machine Learning', 'Artificial intelligence, data science, and automation solutions', '#6366F1', 1, '2025-09-26 19:43:22', NULL),
(10, 'Open Innovation', 'Creative solutions that don\'t fit into specific categories', '#64748B', 1, '2025-09-26 19:43:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','mentor','participant','volunteer') NOT NULL,
  `tech_stack` text DEFAULT NULL COMMENT 'Comma-separated list of technologies/skills',
  `floor` varchar(10) DEFAULT NULL,
  `room` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `tech_stack`, `floor`, `room`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@hackathon.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administration, Database Management, Web Development', NULL, NULL, '2025-09-26 19:43:22', NULL),
(32, 'Volunteer1 ', 'volunteer1@gmail.com', '$2y$10$kL1i9rHNKI3sJCVFlS.rSeWjZ9/UZqnes8ZT0WUcpIyE90BofI/ji', 'volunteer', NULL, NULL, NULL, '2025-11-04 05:55:57', NULL),
(39, 'volunteer2', 'volunteer2@gmail.com', '$2y$10$DiZA8LUPE8z5gMdgiE25wOSw0F1e6eUgvCQ3B1zRmZhJ4vpyfMsp6', 'volunteer', '', NULL, NULL, '2025-11-06 06:53:03', NULL),
(40, 'participant1', 'participant1@gmail.com', '$2y$10$.y5OGlxh9WmCu7DWb1LnJe3fAtGGJayxPRXtYmzVgF9b2v8IgkJ9a', 'participant', '', NULL, NULL, '2025-11-06 08:16:04', NULL),
(41, 'participant2', 'participant2@gmail.com', '$2y$10$n0X9Fg9LjIbBsCTy7.puGeLG8r3xjnLtjXORxW4iJCz/XC8jPrt8m', 'participant', '', NULL, NULL, '2025-11-06 08:16:24', NULL),
(42, 'mentor1', 'mentor1@gmail.com', '$2y$10$Uttaef.dtrzOSXgSe77IxuBPnWfocfi88wpqarbcd92VsW/Ypy6iG', 'mentor', 'React', NULL, NULL, '2025-11-06 09:19:42', NULL),
(43, 'mentor2', 'mentor2@gmail.com', '$2y$10$cDj6yPAPcqRzY/4K9C7NhuaFKyG/us/206TQf884Mb4/3yroBOTOe', 'mentor', 'Node.js', NULL, NULL, '2025-11-06 09:20:09', NULL),
(46, 'Divyanshu Gupta', 'divyanshuomar856@gmail.com', '$2y$10$Tyr4Q/fMhSHUp8Z1qA9Zl.mdCt4Isy4pPYE1bxrgdhtLjkSivfYyO', 'admin', NULL, NULL, NULL, '2025-11-13 07:55:17', NULL),
(47, 'Aditi Narang', 'narangaditi709@gmail.com', '$2y$10$F3M3NgEvKewQva0D3i7/9eCA0z5QyKG0nlx7m/MalurkG1TFhR3km', 'participant', 'AWS', NULL, NULL, '2026-01-05 14:48:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_skills`
--

CREATE TABLE `user_skills` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `proficiency_level` enum('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_assignments`
--

CREATE TABLE `volunteer_assignments` (
  `id` int(11) NOT NULL,
  `volunteer_id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `volunteer_assignments`
--

INSERT INTO `volunteer_assignments` (`id`, `volunteer_id`, `floor_id`, `room_id`, `created_at`) VALUES
(4, 32, 1, 1, '2025-11-13 17:39:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_logs_user` (`user_id`),
  ADD KEY `idx_activity_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_activity_logs_created` (`created_at`);

--
-- Indexes for table `floors`
--
ALTER TABLE `floors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `floor_number` (`floor_number`),
  ADD KEY `idx_floors_number` (`floor_number`);

--
-- Indexes for table `github_repositories`
--
ALTER TABLE `github_repositories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_github_repositories_submitted_by` (`submitted_by`),
  ADD KEY `idx_github_repositories_status` (`status`);

--
-- Indexes for table `join_requests`
--
ALTER TABLE `join_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_join_requests_user_team_status` (`user_id`,`team_id`,`status`),
  ADD KEY `idx_join_requests_user_team_count` (`user_id`,`team_id`,`created_at`),
  ADD KEY `idx_join_requests_team_pending` (`team_id`,`status`),
  ADD KEY `idx_join_requests_user_pending` (`user_id`,`status`),
  ADD KEY `idx_join_requests_status_created` (`status`,`created_at`),
  ADD KEY `idx_join_requests_user_status` (`user_id`,`status`);

--
-- Indexes for table `mentoring_rounds`
--
ALTER TABLE `mentoring_rounds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mentoring_rounds_time` (`start_time`,`end_time`),
  ADD KEY `idx_mentoring_rounds_active` (`is_active`);

--
-- Indexes for table `mentor_assignments`
--
ALTER TABLE `mentor_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mentor_floor_room` (`mentor_id`,`floor_id`,`room_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `idx_mentor_assignments_mentor` (`mentor_id`),
  ADD KEY `idx_mentor_assignments_location` (`floor_id`,`room_id`);

--
-- Indexes for table `mentor_recommendations`
--
ALTER TABLE `mentor_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mentor_recommendations_participant` (`participant_id`),
  ADD KEY `idx_mentor_recommendations_mentor` (`mentor_id`),
  ADD KEY `idx_mentor_recommendations_score` (`match_score`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_logs_type` (`type`),
  ADD KEY `idx_notification_logs_created` (`created_at`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_preferences` (`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_posts_author` (`author_id`),
  ADD KEY `idx_posts_pinned` (`is_pinned`),
  ADD KEY `idx_posts_created` (`created_at`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_endpoint` (`user_id`,`endpoint`(255)),
  ADD KEY `idx_push_subscriptions_user_active` (`user_id`,`is_active`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_floor_room` (`floor_id`,`room_number`),
  ADD KEY `idx_rooms_floor` (`floor_id`),
  ADD KEY `idx_rooms_capacity` (`capacity`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mentor_team_round` (`mentor_id`,`team_id`,`round_id`),
  ADD KEY `idx_scores_team` (`team_id`),
  ADD KEY `idx_scores_mentor` (`mentor_id`),
  ADD KEY `idx_scores_round` (`round_id`),
  ADD KEY `idx_scores_created_at` (`created_at`),
  ADD KEY `idx_scores_team_round` (`team_id`,`round_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_submission` (`team_id`),
  ADD KEY `idx_submissions_submitted_at` (`submitted_at`),
  ADD KEY `idx_submissions_team_submitted` (`team_id`,`submitted_at`);

--
-- Indexes for table `submission_settings`
--
ALTER TABLE `submission_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_submission_settings_active` (`is_active`),
  ADD KEY `idx_submission_settings_time` (`start_time`,`end_time`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_support_messages_admin_status` (`to_role`,`status`,`created_at`),
  ADD KEY `idx_support_messages_from` (`from_id`),
  ADD KEY `idx_support_messages_location` (`floor_id`,`room_id`),
  ADD KEY `idx_support_messages_priority` (`priority`,`status`),
  ADD KEY `idx_support_messages_status_priority` (`status`,`priority`,`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_system_settings_key` (`setting_key`),
  ADD KEY `idx_system_settings_public` (`is_public`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `idx_teams_theme` (`theme_id`),
  ADD KEY `idx_teams_leader` (`leader_id`),
  ADD KEY `idx_teams_status` (`status`),
  ADD KEY `idx_teams_location` (`floor_id`,`room_id`),
  ADD KEY `idx_teams_created_at` (`created_at`),
  ADD KEY `idx_teams_status_created` (`status`,`created_at`),
  ADD KEY `idx_teams_theme_status` (`theme_id`,`status`);

--
-- Indexes for table `team_invitations`
--
ALTER TABLE `team_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_user_invitation` (`team_id`,`to_user_id`),
  ADD KEY `idx_team_invitations_to_user` (`to_user_id`,`status`),
  ADD KEY `idx_team_invitations_from_user` (`from_user_id`),
  ADD KEY `idx_team_invitations_team` (`team_id`,`status`),
  ADD KEY `idx_team_invitations_created_at` (`created_at`),
  ADD KEY `idx_team_invitations_status_created` (`status`,`created_at`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_team` (`user_id`,`team_id`),
  ADD KEY `idx_team_members_user` (`user_id`),
  ADD KEY `idx_team_members_team` (`team_id`),
  ADD KEY `idx_team_members_status` (`status`),
  ADD KEY `idx_team_members_team_status` (`team_id`,`status`);

--
-- Indexes for table `team_skill_requirements`
--
ALTER TABLE `team_skill_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_skill` (`team_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_themes_active` (`is_active`),
  ADD KEY `idx_themes_name` (`name`),
  ADD KEY `idx_themes_active_name` (`is_active`,`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_created_at` (`created_at`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_notification` (`user_id`,`notification_id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `idx_user_notifications_user_read` (`user_id`,`read_at`),
  ADD KEY `idx_user_notifications_user_unread` (`user_id`,`read_at`,`created_at`);

--
-- Indexes for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_skill` (`user_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `volunteer_assignments`
--
ALTER TABLE `volunteer_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_volunteer_assignment` (`volunteer_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `idx_volunteer_assignments_location` (`floor_id`,`room_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `floors`
--
ALTER TABLE `floors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `github_repositories`
--
ALTER TABLE `github_repositories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `join_requests`
--
ALTER TABLE `join_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `mentoring_rounds`
--
ALTER TABLE `mentoring_rounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `mentor_assignments`
--
ALTER TABLE `mentor_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `mentor_recommendations`
--
ALTER TABLE `mentor_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `submission_settings`
--
ALTER TABLE `submission_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `team_invitations`
--
ALTER TABLE `team_invitations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `team_skill_requirements`
--
ALTER TABLE `team_skill_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `themes`
--
ALTER TABLE `themes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_skills`
--
ALTER TABLE `user_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteer_assignments`
--
ALTER TABLE `volunteer_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `github_repositories`
--
ALTER TABLE `github_repositories`
  ADD CONSTRAINT `github_repositories_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `join_requests`
--
ALTER TABLE `join_requests`
  ADD CONSTRAINT `join_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `join_requests_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_assignments`
--
--
-- Constraints for table `mentor_recommendations`
--
ALTER TABLE `mentor_recommendations`
  ADD CONSTRAINT `mentor_recommendations_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mentor_recommendations_ibfk_2` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_assignments`
--
ALTER TABLE `mentor_assignments`
  ADD CONSTRAINT `mentor_assignments_ibfk_1` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mentor_assignments_ibfk_2` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mentor_assignments_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_preferences`
--
--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_3` FOREIGN KEY (`round_id`) REFERENCES `mentoring_rounds` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `support_messages_ibfk_1` FOREIGN KEY (`from_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_messages_ibfk_2` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_messages_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_messages_ibfk_4` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teams_ibfk_2` FOREIGN KEY (`leader_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teams_ibfk_3` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teams_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `team_invitations`
--
ALTER TABLE `team_invitations`
  ADD CONSTRAINT `team_invitations_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_invitations_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_invitations_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_members_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_skill_requirements`
--
ALTER TABLE `team_skill_requirements`
  ADD CONSTRAINT `team_skill_requirements_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_skill_requirements_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
--
-- Constraints for table `user_skills`
--
ALTER TABLE `user_skills`
  ADD CONSTRAINT `user_skills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_notifications_ibfk_2` FOREIGN KEY (`notification_id`) REFERENCES `notification_logs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_assignments`
--
ALTER TABLE `volunteer_assignments`
  ADD CONSTRAINT `volunteer_assignments_ibfk_1` FOREIGN KEY (`volunteer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_assignments_ibfk_2` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_assignments_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
