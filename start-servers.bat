@echo off
title CAESER OF ARABS - Local Servers

echo.
echo  ==========================================================
echo          CAESER OF ARABS - Local Dev Launcher
echo  ==========================================================
echo.

set "BASE=%~dp0"
set "CAESAR_DB_NAME=caesar_test"
set "CAESAR_ENV=development"
set "CAESAR_API_PORT=8092"
set "CAESAR_API_URL=http://127.0.0.1:8092"
set "CAESAR_TLS_DIR=%LOCALAPPDATA%\CAESER\local-tls"
set "CAESAR_TLS_CERT_FILE=%CAESAR_TLS_DIR%\localhost.crt"
set "CAESAR_TLS_KEY_FILE=%CAESAR_TLS_DIR%\localhost.key"
set "CAESAR_REQUIRE_TLS=1"

if not exist "%CAESAR_TLS_CERT_FILE%" (
    echo [!] Local TLS certificate is missing.
    echo     Run: powershell -ExecutionPolicy Bypass -File "%~dp0setup-local-tls.ps1"
    exit /b 1
)
if not exist "%CAESAR_TLS_KEY_FILE%" (
    echo [!] Local TLS private key is missing.
    echo     Run: powershell -ExecutionPolicy Bypass -File "%~dp0setup-local-tls.ps1"
    exit /b 1
)

if /I "%~1"=="test" goto use_test_environment
goto environment_ready

:use_test_environment
set "CAESAR_DB_NAME=caesar_test"
set "CAESAR_ENV=test"

:environment_ready

echo [*] Using database: %CAESAR_DB_NAME%

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

REM 1. caesar-web on port 8082
echo [1] Starting caesar-web at http://localhost:8082
set "CAESAR_ENABLE_GUEST_LOGIN=1"
start "web :8082" /D "%BASE%" "C:\xampp\php\php.exe" -S 127.0.0.1:8082 -t "%BASE%caesar-web" "%BASE%caesar-web\router.php"

REM 2. caesar-api on port 8092
echo [2] Starting caesar-api at http://localhost:8092
set "CAESAR_ENABLE_GUEST_LOGIN="
start "app :8092" /D "%BASE%" "C:\xampp\php\php.exe" -S 127.0.0.1:8092 -t "%BASE%caesar-api" "%BASE%caesar-api\router.php"

REM 3. caesar-ws on port 8081 (WebSocket and HTTP)
echo [3] Starting caesar-ws at wss://localhost:8081
start "ws :8081" /D "%BASE%caesar-ws" node "%BASE%caesar-ws\ws-server.js"

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
