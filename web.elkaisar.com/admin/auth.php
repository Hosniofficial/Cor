<?php
// web.elkaisar.com/admin/auth.php
require_once __DIR__ . '/../session.php';

require_once __DIR__ . '/../../app.elkaisar.com/db.php';
require_once __DIR__ . '/../auth-security.php';

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function getAdminUser() {
    return $_SESSION['admin_user'] ?? null;
}

function isLoggedIn() {
    return !empty($_SESSION['admin_user'])
        && (int)($_SESSION['admin_session_expires_at'] ?? 0) > time();
}

function requireLogin(bool $jsonResponse = false): void {
    if (!isLoggedIn()) {
        unset($_SESSION['admin_user'], $_SESSION['admin_session_expires_at']);
        if ($jsonResponse || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['state' => 'error', 'message' => 'يجب تسجيل الدخول أولاً']);
            exit;
        }
        header('Location: login.php');
        exit;
    }

    $user = $_SESSION['admin_user'];
    $stmt = getDB()->prepare(
        'SELECT `username`, `name`, `role`, `permissions`, `status`
         FROM `admin_user` WHERE `id_admin` = ? LIMIT 1'
    );
    $stmt->execute([(int)$user['id_admin']]);
    $activeUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$activeUser || (int)$activeUser['status'] !== 1) {
        unset($_SESSION['admin_user'], $_SESSION['admin_session_expires_at']);
        if ($jsonResponse) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['state' => 'error', 'message' => 'الحساب الإداري غير متاح']);
            exit;
        }
        header('Location: login.php');
        exit;
    }

    $_SESSION['admin_user'] = array_merge($user, [
        'username' => $activeUser['username'],
        'name' => $activeUser['name'],
        'role' => $activeUser['role'],
        'permissions' => json_decode($activeUser['permissions'] ?: '{}', true) ?: [],
    ]);
}

function requireValidAdminCsrf(): void {
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';
    if (!is_string($submittedToken) || !is_string($sessionToken)
        || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(419);
        echo json_encode(['state' => 'error', 'message' => 'انتهت صلاحية الطلب. حدّث الصفحة ثم حاول مجدداً.']);
        exit;
    }
}

function hasPermission($permission) {
    $user = getAdminUser();
    if (!$user) return false;
    if ($user['role'] === 'super_admin') return true;

    $perms = $user['permissions'] ?? [];
    if (is_string($perms)) {
        $perms = json_decode($perms, true) ?: [];
    }
    return !empty($perms[$permission]) || !empty($perms['all']);
}

function requirePermission($permission) {
    requireLogin();
    if (!hasPermission($permission)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['state' => 'error', 'message' => 'عذراً، ليس لديك الصلاحية الكافية لتنفيذ هذا الإجراء']);
        exit;
    }
}

function logAdminAction($action, $targetType, $targetId = null, $targetName = null, $details = null) {
    try {
        $user = getAdminUser();
        $adminId = $user ? (int)$user['id_admin'] : 0;
        $adminName = $user ? $user['name'] : 'System';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO `admin_log` (`id_admin`, `admin_name`, `action`, `target_type`, `target_id`, `target_name`, `details`, `ip_address`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $adminId,
            $adminName,
            $action,
            $targetType,
            $targetId,
            $targetName,
            is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
            $ip
        ]);
    } catch (\Exception $e) {
        error_log('[Admin audit] Failed to record action: ' . $e->getMessage());
    }
}
