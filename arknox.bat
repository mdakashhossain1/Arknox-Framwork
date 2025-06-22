@echo off
REM Arknox Framework CLI for Windows
REM 
REM Cross-platform command line interface for Arknox Framework
REM 
REM @package Arknox
REM @version 1.0.0
REM @author Arknox Team

setlocal enabledelayedexpansion

REM Check if PHP is available
php --version >nul 2>&1
if errorlevel 1 (
    echo Error: PHP is not installed or not in PATH.
    echo Please install PHP 8.1+ and add it to your system PATH.
    echo.
    echo You can download PHP from: https://windows.php.net/download/
    echo Or install XAMPP from: https://www.apachefriends.org/
    exit /b 1
)

REM Check PHP version
for /f "tokens=2 delims= " %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i

REM Simple version check (basic comparison)
for /f "tokens=1,2,3 delims=." %%a in ("%PHP_VERSION%") do (
    set MAJOR=%%a
    set MINOR=%%b
    set PATCH=%%c
)

if %MAJOR% LSS 8 (
    echo Arknox Framework requires PHP 8.1 or higher. You are running %PHP_VERSION%
    exit /b 1
)

if %MAJOR% EQU 8 if %MINOR% LSS 1 (
    echo Arknox Framework requires PHP 8.1 or higher. You are running %PHP_VERSION%
    exit /b 1
)

REM Get the directory where this batch file is located
set ARKNOX_ROOT=%~dp0
set ARKNOX_ROOT=%ARKNOX_ROOT:~0,-1%

REM Check if running from correct directory
if not exist "%ARKNOX_ROOT%\composer.json" (
    echo Error: Please run this command from the Arknox Framework root directory.
    exit /b 1
)

REM Check if dependencies are installed
if not exist "%ARKNOX_ROOT%\vendor\autoload.php" (
    echo Error: Dependencies not installed. Please run 'composer install' first.
    exit /b 1
)

REM Set environment variables
set ARKNOX_APP=%ARKNOX_ROOT%\app
set ARKNOX_CONFIG=%ARKNOX_ROOT%\config
set ARKNOX_STORAGE=%ARKNOX_ROOT%\storage
set ARKNOX_PUBLIC=%ARKNOX_ROOT%\public

REM Change to the framework directory
cd /d "%ARKNOX_ROOT%"

REM Run the PHP CLI script
php "%ARKNOX_ROOT%\arknox" %*

REM Capture exit code
set EXIT_CODE=%ERRORLEVEL%

REM Return to original directory
cd /d "%~dp0"

REM Exit with the same code as the PHP script
exit /b %EXIT_CODE%
