const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientJsPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
const wsServerPath = path.join(__dirname, '..', 'caesar-ws', 'ws-server.js');
const messagePhpPath = path.join(__dirname, '..', 'caesar-web', 'api', 'message.php');
const serverPhpPath = path.join(__dirname, '..', 'caesar-web', 'server.php');
const wsHelperPath = path.join(__dirname, '..', 'caesar-web', 'api', 'ws_helper.php');

test('ws-server defines /api/internal/notify_mail and dispatches Mail.sentTo to target players', () => {
    const wsServer = fs.readFileSync(wsServerPath, 'utf8');

    assert.ok(
        wsServer.includes('/api/internal/notify_mail'),
        'ws-server must register /api/internal/notify_mail route'
    );
    assert.ok(
        wsServer.includes('sendToPlayer(pid, payload)') || wsServer.includes('sendToPlayer('),
        'ws-server notify_mail must dispatch payload to player sockets via sendToPlayer'
    );
    assert.ok(
        wsServer.includes("let classPath = 'Mail.sentTo'"),
        'notify_mail must default classPath to Mail.sentTo'
    );
});

test('message.php and ws_helper notify ws-server on direct and guild messages', () => {
    const msgPhp = fs.readFileSync(messagePhpPath, 'utf8');
    const wsHelper = fs.readFileSync(wsHelperPath, 'utf8');

    assert.ok(
        wsHelper.includes('function notifyWsMail'),
        'ws_helper must define notifyWsMail helper'
    );
    assert.ok(
        wsHelper.includes('http://127.0.0.1:8081/api/internal/notify_mail'),
        'notifyWsMail must call ws-server internal endpoint'
    );
    assert.ok(
        msgPhp.includes("notifyWsMail($idTo, 'Mail.sentTo')"),
        'send_mail_to in message.php must notify recipient via notifyWsMail'
    );
    assert.ok(
        msgPhp.includes("notifyWsMail($recipients, 'Mail.sentTo')"),
        'SEND_GUILD_MAIL in message.php must notify guild members via notifyWsMail'
    );
});

test('message.php MSG_NUMBERS queries total counts for pagination and unread counts for badges', () => {
    const msgPhp = fs.readFileSync(messagePhpPath, 'utf8');

    // Must query total counts for pagination (not restricted to seen = 0)
    assert.ok(
        msgPhp.includes('SELECT COUNT(*) FROM `msg_income` WHERE `id_player` = ?') &&
        msgPhp.includes('SELECT COUNT(*) FROM `msg_out` WHERE `id_player` = ?'),
        'MSG_NUMBERS must query total message counts for msg_income and msg_out'
    );
    // Must return unread counts for badges
    assert.ok(
        msgPhp.includes('unread_income') && msgPhp.includes('unread_diff'),
        'MSG_NUMBERS must return unread counts for badges'
    );
});

test('caesar-client.js handles real-time Mail.sentTo, updates refreshMsg, and prevents 1/0 pagination', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // Mail.sentTo handler updates notification count and refreshes messages
    assert.ok(
        clientJs.includes('Caesar.WsLib.Mail.sentTo = function(data) {') &&
        clientJs.includes('PLAYER_NOTIF.msg_in = Number(PLAYER_NOTIF.msg_in) + 1') &&
        clientJs.includes('Fixed.refreshPlayerNotif()'),
        'Caesar.WsLib.Mail.sentTo must increment msg_in and refresh notifications'
    );

    // refreshMsg syncs unread counts and updates pagination navigator
    assert.ok(
        clientJs.includes('function refreshMsg(callBack)') &&
        clientJs.includes('PLAYER_NOTIF.msg_in = Number(MSG_NUM.unread_income)'),
        'refreshMsg must accept callback and sync unread counts'
    );

    // Opening mailbox dialog calls refreshMsg
    assert.ok(
        clientJs.includes('case "messages":') &&
        clientJs.includes('refreshMsg()'),
        'Opening messages must call refreshMsg'
    );

    // Footer never shows 1 / 0
    assert.ok(
        clientJs.includes('/10) || 1'),
        'Footer must fallback to 1 page to avoid 1 / 0 display'
    );
});

test('server.php initializes PLAYER_NOTIF from database instead of hardcoded zeroes', () => {
    const serverPhp = fs.readFileSync(serverPhpPath, 'utf8');

    assert.ok(
        serverPhp.includes('$initialPlayerNotif') &&
        serverPhp.includes('SELECT COUNT(*) FROM `msg_income` WHERE `id_player` = ? AND `seen` = 0'),
        'server.php must query unread notification counts on startup'
    );
    assert.ok(
        serverPhp.includes('var PLAYER_NOTIF     = <?= json_encode($initialPlayerNotif'),
        'server.php must inject initialPlayerNotif into JavaScript'
    );
});

test('Outbox Delete All and Delete Selected support empty state and msg_out/msg_outcome routing', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');
    const msgPhp = fs.readFileSync(messagePhpPath, 'utf8');

    // dialogBoxcontent_msgOutcome must not skip rendering when json_data is empty
    assert.ok(
        !clientJs.includes('if (json_data.length > 0) {\r\n                    $(".box_content").replaceWith(output);') &&
        !clientJs.includes('if (json_data.length > 0) {\n                    $(".box_content").replaceWith(output);'),
        'dialogBoxcontent_msgOutcome must render empty rows even when json_data is empty'
    );

    // #delete-all button must refresh dialogBoxcontent_msgOutcome on msg_out or msg_outcome
    assert.ok(
        clientJs.includes('total_msg.table === "msg_out" || total_msg.table === "msg_outcome"'),
        '#delete-all must handle msg_out and msg_outcome'
    );

    // message.php must handle msg_out and msg_outcome in delete_msg and DELETE_ALL_UNREAD
    assert.ok(
        msgPhp.includes("$table === 'msg_out' || $table === 'msg_outcome'"),
        'message.php must accept msg_outcome alias for msg_out deletion'
    );
});
