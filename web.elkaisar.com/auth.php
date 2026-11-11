<?php
require_once __DIR__ . '/session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respondAuth(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function authInput(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

require_once __DIR__ . '/../app.elkaisar.com/db.php';
require_once __DIR__ . '/auth-security.php';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (in_array($action, ['login', 'register', 'guest_login'], true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        respondAuth(['state' => 'error', 'message' => 'طريقة الطلب غير مدعومة.'], 405);
    }

    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !is_string($sessionToken)
        || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        respondAuth(['state' => 'error', 'message' => 'انتهت صلاحية الصفحة. حدّثها ثم حاول مجدداً.'], 419);
    }
}

if ($action === 'guest_login') {
    if (getenv('ELKAISAR_ENABLE_GUEST_LOGIN') !== '1'
        || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        respondAuth(['state' => 'error', 'message' => 'تسجيل دخول الضيف متاح للتطوير المحلي فقط.'], 403);
    }

    $db = getDB();
    if (consumeAuthRateLimit($db, 'guest', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 10)) {
        respondAuth(['state' => 'error', 'message' => 'طلبات كثيرة. حاول بعد 15 دقيقة.'], 429);
    }
    $player = $db->query('SELECT * FROM `player` ORDER BY `id_player` ASC LIMIT 1')->fetch();
    if (!$player) {
        $created = createFreshPlayer('البطل سيزر', 1);
        $stmt = $db->prepare('SELECT * FROM `player` WHERE `id_player` = ?');
        $stmt->execute([$created['playerId']]);
        $player = $stmt->fetch();
    }

    $gameToken = bin2hex(random_bytes(32));
    $db->prepare('UPDATE `player` SET `p_token` = ? WHERE `id_player` = ?')
        ->execute([$gameToken, (int)$player['id_player']]);
    session_regenerate_id(true);
    unset($_SESSION['selected_server_id']);
    $_SESSION['player_id'] = (int)$player['id_player'];
    $_SESSION['player_token'] = $gameToken;
    $_SESSION['player_name'] = $player['name'];
    $_SESSION['player_session_expires_at'] = time() + ELKAISAR_SESSION_TTL;
    respondAuth([
        'state' => 'ok',
        'message' => 'تم تسجيل الدخول السريع بنجاح!',
        'player_id' => (int)$player['id_player'],
        'name' => $player['name'],
        'redirect' => 'profile.php',
    ]);
}

if ($action === 'login') {
    $login = authInput('login');
    $password = $_POST['password'] ?? '';
    $db = getDB();
    $rateLimitIdentity = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (consumeAuthRateLimit($db, 'player_login', $rateLimitIdentity, 10)) {
        respondAuth(['state' => 'error', 'message' => 'طلبات دخول كثيرة. حاول بعد 15 دقيقة.'], 429);
    }
    if ($login === '' || !is_string($password) || $password === '') {
        respondAuth(['state' => 'error', 'message' => 'أدخل اسم المستخدم أو البريد الإلكتروني وكلمة المرور.'], 422);
    }

    $stmt = $db->prepare(
        'SELECT ua.*, p.name AS player_name, p.user_group
         FROM `user_auth` ua
         JOIN `player` p ON ua.id_player = p.id_player
         WHERE ua.username = ? OR ua.email = ? OR p.name = ?
         ORDER BY CASE WHEN ua.username = ? THEN 0 WHEN ua.email = ? THEN 1 ELSE 2 END
         LIMIT 1'
    );
    $stmt->execute([$login, $login, $login, $login, $login]);
    $user = $stmt->fetch();

    if (!$user || strlen($password) > 72 || !password_verify($password, $user['password'])) {
        respondAuth(['state' => 'error', 'message' => 'بيانات الدخول غير صحيحة.'], 401);
    }

    clearAuthRateLimit($db, 'player_login', $rateLimitIdentity);
    $gameToken = bin2hex(random_bytes(32));
    $db->prepare('UPDATE `player` SET `p_token` = ? WHERE `id_player` = ?')
        ->execute([$gameToken, (int)$user['id_player']]);
    session_regenerate_id(true);
    unset($_SESSION['selected_server_id']);
    $_SESSION['player_id'] = (int)$user['id_player'];
    $_SESSION['player_token'] = $gameToken;
    $_SESSION['player_name'] = $user['player_name'];
    $_SESSION['user_group'] = (int)$user['user_group'];
    $_SESSION['player_session_expires_at'] = time() + ELKAISAR_SESSION_TTL;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    respondAuth([
        'state' => 'ok',
        'message' => 'مرحباً بك مجدداً أيها القائد ' . $user['player_name'],
        'player_id' => (int)$user['id_player'],
        'name' => $user['player_name'],
        'redirect' => 'profile.php',
    ]);
}

if ($action === 'register') {
    $username = authInput('username');
    $email = strtolower(authInput('email'));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $avatarValue = $_POST['avatar'] ?? '1';
    $avatar = is_scalar($avatarValue) ? (int)$avatarValue : 1;
    $db = getDB();
    if (consumeAuthRateLimit($db, 'register', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 8)) {
        respondAuth(['state' => 'error', 'message' => 'طلبات تسجيل كثيرة. حاول بعد 15 دقيقة.'], 429);
    }

    if (mb_strlen($username, 'UTF-8') < 2 || mb_strlen($username, 'UTF-8') > 25
        || !preg_match('/^[\p{L}\p{N}_. -]+$/u', $username)) {
        respondAuth(['state' => 'error', 'message' => 'اسم المستخدم من حرفين إلى 25 حرفاً، ويمكن أن يحتوي أحرفاً وأرقاماً ومسافات وشرطة.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        respondAuth(['state' => 'error', 'message' => 'أدخل بريداً إلكترونياً صالحاً.'], 422);
    }
    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
        respondAuth(['state' => 'error', 'message' => 'كلمة المرور يجب أن تكون من 8 إلى 72 خانة.'], 422);
    }
    if (!is_string($passwordConfirm) || !hash_equals($password, $passwordConfirm)) {
        respondAuth(['state' => 'error', 'message' => 'تأكيد كلمة المرور غير مطابق.'], 422);
    }
    $avatar = max(1, min(12, $avatar));
    try {
        $db->beginTransaction();

        $stmt = $db->prepare('SELECT 1 FROM `user_auth` WHERE `email` = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $db->rollBack();
            respondAuth(['state' => 'error', 'message' => 'هذا البريد الإلكتروني مسجل بالفعل.'], 409);
        }

        $stmt = $db->prepare(
            'SELECT 1 FROM `user_auth` WHERE `username` = ?
             UNION ALL
             SELECT 1 FROM `player` WHERE `name` = ?
             LIMIT 1'
        );
        $stmt->execute([$username, $username]);
        if ($stmt->fetchColumn()) {
            $db->rollBack();
            respondAuth(['state' => 'error', 'message' => 'اسم المستخدم مستخدم بالفعل. اختر اسماً آخر.'], 409);
        }

        $fresh = createFreshPlayer($username, $avatar);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare(
            'INSERT INTO `user_auth` (`username`, `email`, `password`, `id_player`)
             VALUES (?, ?, ?, ?)'
        )->execute([$username, $email, $hashedPassword, $fresh['playerId']]);
        $db->commit();
        clearAuthRateLimit($db, 'register', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    } catch (PDOException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ((int)($error->errorInfo[1] ?? 0) === 1062) {
            respondAuth(['state' => 'error', 'message' => 'اسم المستخدم أو البريد الإلكتروني مسجل بالفعل.'], 409);
        }
        error_log('[Auth] Account registration failed: ' . $error->getMessage());
        respondAuth(['state' => 'error', 'message' => 'تعذر إنشاء الحساب حالياً. حاول مرة أخرى لاحقاً.'], 500);
    } catch (RuntimeException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[Auth] Starter city placement failed: ' . $error->getMessage());
        respondAuth(['state' => 'error', 'message' => 'تعذر العثور على موقع صالح للمدينة. حاول مرة أخرى لاحقاً.'], 503);
    }

    respondAuth([
        'state' => 'ok',
        'message' => 'تم إنشاء الحساب. سجّل الدخول لبدء اللعب.',
        'username' => $username,
        'redirect' => 'login.php?mode=login',
    ], 201);
}

if ($action === 'check') {
    $playerId = (int)($_SESSION['player_id'] ?? 0);
    if ($playerId > 0) {
        $stmt = getDB()->prepare(
            'SELECT `id_player`, `name`, `user_group`, `avatar`, `gold`, `prestige`, `p_token`
             FROM `player` WHERE `id_player` = ?'
        );
        $stmt->execute([$playerId]);
        $player = $stmt->fetch();
        $sessionToken = $_SESSION['player_token'] ?? '';
        if ($player && is_string($sessionToken) && $sessionToken !== ''
            && hash_equals((string)$player['p_token'], $sessionToken)) {
            unset($player['p_token']);
            respondAuth(['logged_in' => true, 'player' => $player]);
        }

        unset(
            $_SESSION['player_id'],
            $_SESSION['player_name'],
            $_SESSION['user_group'],
            $_SESSION['selected_server_id'],
            $_SESSION['player_session_expires_at'],
            $_SESSION['player_token']
        );
        respondAuth(['logged_in' => false, 'reason' => 'account_replaced']);
    }
    respondAuth(['logged_in' => false]);
}

respondAuth(['state' => 'error', 'message' => 'إجراء غير معروف.'], 400);
