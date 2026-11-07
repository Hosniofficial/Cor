@echo off
chcp 65001 >nul
title قيصر الشرق - Local Servers

echo.
echo  ╔══════════════════════════════════════════════════════╗
echo  ║        قيصر الشرق  —  Local Dev Launcher            ║
echo  ╚══════════════════════════════════════════════════════╝
echo.

set BASE=C:\Users\Pro\Desktop\Ceasar\web.elkaisar.com (2)

REM ── 0. Check and start MySQL from XAMPP ───────────────────────────────────
echo [*] Checking MySQL service on port 3306...
netstat -ano | findstr :3306 | findstr LISTENING >nul
if %errorlevel% neq 0 (
    echo [0] Starting MySQL (XAMPP)...
    start "MySQL" cmd /k "C:\xampp\mysql_start.bat"
    timeout /t 3 /nobreak >nul
) else (
    echo [0] MySQL is already running.
)

REM ── 1. web.elkaisar.com on port 8080 ──────────────────────────────────────
echo [1] Starting web.elkaisar.com  →  http://localhost:8080
start "web :8080" cmd /k "php -S 0.0.0.0:8080 -t ""%BASE%\web.elkaisar.com"" ""%BASE%\web.elkaisar.com\router.php"""

REM ── 2. app.elkaisar.com on port 8090 ──────────────────────────────────────
echo [2] Starting app.elkaisar.com  →  http://localhost:8090
start "app :8090" cmd /k "php -S 0.0.0.0:8090 -t ""%BASE%\app.elkaisar.com"" ""%BASE%\app.elkaisar.com\router.php"""

REM ── 3. ws.elkaisar.com on port 8081 (WS + HTTP) ───────────────────────────
echo [3] Starting ws.elkaisar.com   →  ws://localhost:8081
start "ws  :8081" cmd /k "node ""%BASE%\ws-server.js"""

REM ── Wait a moment then open browser ───────────────────────────────────────
timeout /t 2 /nobreak >nul
echo.
echo [*] Opening game in browser...
start "" "http://localhost:8080/local-server.php"

echo.
echo  ┌──────────────────────────────────────────────────────────┐
echo  │  Servers are running!                                    │
echo  │                                                           │
echo  │  Game URL:  http://localhost:8080/local-server.php        │
echo  │  Reset DB:  http://localhost:8080/reset-player.php        │
echo  └──────────────────────────────────────────────────────────┘
echo.
pause
