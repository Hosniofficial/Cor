@echo off
title Caesar of Arabs - Local Servers

echo.
echo  ==========================================================
echo          Caesar of Arabs - Local Dev Launcher
echo  ==========================================================
echo.

set "BASE=%~dp0"
set "ELKAISAR_DB_NAME=elkaisar_prod_ready_20261109"
set "ELKAISAR_ENV=development"
set "ELKAISAR_API_PORT=8092"
set "ELKAISAR_API_URL=http://127.0.0.1:8092"

if /I "%~1"=="test" goto use_test_environment
goto environment_ready

:use_test_environment
set "ELKAISAR_DB_NAME=elkaisar_test"
set "ELKAISAR_ENV=test"

:environment_ready

echo [*] Using database: %ELKAISAR_DB_NAME%

REM 0. Check and start MySQL from XAMPP
echo [*] Checking MySQL service on port 3306...
netstat -ano | findstr :3306 | findstr LISTENING >nul
if errorlevel 1 goto start_mysql
echo [0] MySQL is already running.
goto mysql_checked

:start_mysql
echo [0] Starting MySQL (XAMPP)...
start "MySQL" cmd /k "C:\xampp\mysql_start.bat"
timeout /t 3 /nobreak >nul

:mysql_checked

REM 1. web.elkaisar.com on port 8082
echo [1] Starting web.elkaisar.com at http://localhost:8082
set "ELKAISAR_ENABLE_GUEST_LOGIN=1"
start "web :8082" /D "%BASE%" "C:\xampp\php\php.exe" -S 127.0.0.1:8082 -t "%BASE%web.elkaisar.com" "%BASE%web.elkaisar.com\router.php"

REM 2. app.elkaisar.com on port 8092
echo [2] Starting app.elkaisar.com at http://localhost:8092
set "ELKAISAR_ENABLE_GUEST_LOGIN="
start "app :8092" /D "%BASE%" "C:\xampp\php\php.exe" -S 127.0.0.1:8092 -t "%BASE%app.elkaisar.com" "%BASE%app.elkaisar.com\router.php"

REM 3. ws.elkaisar.com on port 8081 (WebSocket and HTTP)
echo [3] Starting ws.elkaisar.com at ws://localhost:8081
start "ws :8081" /D "%BASE%" node "%BASE%ws-server.js"

REM Wait a moment, then open the browser
timeout /t 2 /nobreak >nul
echo.
echo [*] Opening game in browser...
start "" "http://localhost:8082/"

echo.
echo  Servers started.
echo  Game URL: http://localhost:8082/
echo.
pause
