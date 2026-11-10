<?php
// setup_admin_tables.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/app.elkaisar.com/db.php';
$db = getDB();

$db->exec("
CREATE TABLE IF NOT EXISTS `admin_user` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','supervisor','moderator') NOT NULL DEFAULT 'moderator',
  `permissions` text NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$db->exec("
CREATE TABLE IF NOT EXISTS `admin_log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_admin` int(11) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_name` varchar(100) DEFAULT NULL,
  `details` text NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `idx_admin` (`id_admin`),
  KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$db->exec("
CREATE TABLE IF NOT EXISTS `auth_rate_limit` (
  `scope` varchar(20) NOT NULL,
  `key_hash` char(64) NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`scope`, `key_hash`),
  KEY `idx_auth_rate_limit_expiry` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Create the first admin only when explicit credentials are provided.
$username = trim((string)getenv('ELKAISAR_ADMIN_USERNAME'));
$password = (string)getenv('ELKAISAR_ADMIN_PASSWORD');
$resetExistingAdmin = getenv('ELKAISAR_ADMIN_RESET_EXISTING') === '1';
$stmt = $db->query("SELECT COUNT(*) FROM `admin_user`");
$adminCount = (int)$stmt->fetchColumn();

if (($username !== '' || $password !== '') && (!preg_match('/\A[a-zA-Z0-9._-]{3,50}\z/', $username) || strlen($password) < 12 || strlen($password) > 72)) {
    throw new RuntimeException('Use a 3-50 character admin username and a password of 12-72 characters.');
}

if ($adminCount === 0 && $username !== '' && $password !== '') {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $perms = json_encode([
        'manage_players' => true,
        'edit_resources' => true,
        'manage_equip'   => true,
        'manage_items'   => true,
        'manage_luxuries'=> true,
        'ban_players'    => true,
        'manage_admins'  => true,
        'view_logs'      => true
    ]);
    $ins = $db->prepare("INSERT INTO `admin_user` (`username`, `password`, `name`, `role`, `permissions`, `status`) VALUES (?, ?, ?, 'super_admin', ?, 1)");
    $ins->execute([$username, $hashed, $username, $perms]);
    echo "Initial super admin created for username: {$username}\n";
} elseif ($adminCount > 0 && $resetExistingAdmin && $username !== '' && $password !== '') {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $update = $db->prepare("UPDATE `admin_user` SET `password` = ? WHERE `username` = ?");
    $update->execute([$hashed, $username]);
    if ($update->rowCount() === 0) {
        throw new RuntimeException("No admin account found for username: {$username}");
    }
    echo "Admin password updated for username: {$username}\n";
} else {
    echo "Admin tables initialized. Existing admins: {$adminCount}.\n";
    if ($adminCount === 0) {
        echo "Set ELKAISAR_ADMIN_USERNAME and ELKAISAR_ADMIN_PASSWORD to create the first admin.\n";
    } elseif ($username !== '' || $password !== '') {
        echo "Set ELKAISAR_ADMIN_RESET_EXISTING=1 to explicitly reset an existing admin password.\n";
    }
}

// Ensure player table has user_group and panned indices or default values
echo "Admin tables initialized successfully.\n";
