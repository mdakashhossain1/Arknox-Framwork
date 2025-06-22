@echo off
REM Arknox Framework - Windows Installation Script
REM 
REM This script helps set up Arknox Framework on Windows systems
REM with all required dependencies and optimal configuration.

setlocal enabledelayedexpansion

REM Colors for Windows (limited support)
set "GREEN=[92m"
set "YELLOW=[93m"
set "RED=[91m"
set "BLUE=[94m"
set "NC=[0m"

echo.
echo    ___         _                    
echo   / _ \  _ __ ^| ^| __ _ __   _____  __
echo  / /_\ \^| '__^|^| ^|/ /^| '_ \ / _ \ \/ /
echo /  _  \^| ^|   ^|   ^< ^| ^| ^| ^| (^_) ^>  ^< 
echo \_/ \_/^|_^|   ^|_^|\_\^|_^| ^|_^|\___/_/\_\
echo.
echo %YELLOW%Arknox Framework - Windows Installation%NC%
echo.

echo %BLUE%[INFO]%NC% Starting Arknox Framework installation on Windows...
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo %GREEN%[SUCCESS]%NC% Running with administrator privileges
) else (
    echo %YELLOW%[WARNING]%NC% Not running as administrator. Some features may require elevation.
)

REM Check if PHP is installed
php --version >nul 2>&1
if errorlevel 1 (
    echo %RED%[ERROR]%NC% PHP is not installed or not in PATH.
    echo.
    echo Please install PHP 8.1+ from one of these options:
    echo 1. Download from: https://windows.php.net/download/
    echo 2. Install XAMPP: https://www.apachefriends.org/
    echo 3. Install WAMP: https://www.wampserver.com/
    echo 4. Use Chocolatey: choco install php
    echo.
    pause
    exit /b 1
) else (
    for /f "tokens=2 delims= " %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
    echo %GREEN%[SUCCESS]%NC% PHP !PHP_VERSION! is installed
)

REM Check PHP version
for /f "tokens=1,2,3 delims=." %%a in ("%PHP_VERSION%") do (
    set MAJOR=%%a
    set MINOR=%%b
)

if %MAJOR% LSS 8 (
    echo %RED%[ERROR]%NC% Arknox Framework requires PHP 8.1 or higher. You have !PHP_VERSION!
    echo Please upgrade your PHP installation.
    pause
    exit /b 1
)

if %MAJOR% EQU 8 if %MINOR% LSS 1 (
    echo %RED%[ERROR]%NC% Arknox Framework requires PHP 8.1 or higher. You have !PHP_VERSION!
    echo Please upgrade your PHP installation.
    pause
    exit /b 1
)

REM Check if Composer is installed
composer --version >nul 2>&1
if errorlevel 1 (
    echo %YELLOW%[WARNING]%NC% Composer is not installed.
    echo.
    echo Please install Composer from: https://getcomposer.org/download/
    echo.
    set /p install_composer="Would you like to download Composer installer? (y/n): "
    if /i "!install_composer!"=="y" (
        echo %BLUE%[INFO]%NC% Opening Composer download page...
        start https://getcomposer.org/download/
    )
    pause
    exit /b 1
) else (
    for /f "tokens=3 delims= " %%i in ('composer --version') do set COMPOSER_VERSION=%%i
    echo %GREEN%[SUCCESS]%NC% Composer !COMPOSER_VERSION! is installed
)

REM Check if Git is installed
git --version >nul 2>&1
if errorlevel 1 (
    echo %YELLOW%[WARNING]%NC% Git is not installed.
    echo.
    echo Please install Git from: https://git-scm.com/download/win
    echo.
    set /p install_git="Would you like to download Git installer? (y/n): "
    if /i "!install_git!"=="y" (
        echo %BLUE%[INFO]%NC% Opening Git download page...
        start https://git-scm.com/download/win
    )
) else (
    for /f "tokens=3 delims= " %%i in ('git --version') do set GIT_VERSION=%%i
    echo %GREEN%[SUCCESS]%NC% Git !GIT_VERSION! is installed
)

REM Check if Node.js is installed
node --version >nul 2>&1
if errorlevel 1 (
    echo %YELLOW%[WARNING]%NC% Node.js is not installed.
    echo.
    echo Please install Node.js from: https://nodejs.org/
    echo.
    set /p install_node="Would you like to download Node.js installer? (y/n): "
    if /i "!install_node!"=="y" (
        echo %BLUE%[INFO]%NC% Opening Node.js download page...
        start https://nodejs.org/
    )
) else (
    for /f "tokens=1 delims= " %%i in ('node --version') do set NODE_VERSION=%%i
    echo %GREEN%[SUCCESS]%NC% Node.js !NODE_VERSION! is installed
)

echo.
echo %BLUE%[INFO]%NC% Checking PHP extensions...

REM Check required PHP extensions
set required_extensions=pdo mbstring openssl json curl
for %%e in (%required_extensions%) do (
    php -m | findstr /i "%%e" >nul
    if !errorlevel! == 0 (
        echo %GREEN%[SUCCESS]%NC% Required extension '%%e' is loaded
    ) else (
        echo %RED%[ERROR]%NC% Required extension '%%e' is missing
    )
)

REM Check optional PHP extensions
set optional_extensions=gd zip bcmath intl
for %%e in (%optional_extensions%) do (
    php -m | findstr /i "%%e" >nul
    if !errorlevel! == 0 (
        echo %GREEN%[SUCCESS]%NC% Optional extension '%%e' is loaded
    ) else (
        echo %YELLOW%[WARNING]%NC% Optional extension '%%e' is not loaded
    )
)

echo.
echo %BLUE%[INFO]%NC% Checking database options...

REM Check for MySQL
mysql --version >nul 2>&1
if errorlevel 1 (
    echo %YELLOW%[WARNING]%NC% MySQL is not installed or not in PATH
    echo Consider installing XAMPP or WAMP for easy MySQL setup
) else (
    echo %GREEN%[SUCCESS]%NC% MySQL is available
)

REM Check for SQLite
php -r "echo extension_loaded('sqlite3') ? 'SQLite3 available' : 'SQLite3 not available';" 2>nul
if errorlevel 1 (
    echo %YELLOW%[WARNING]%NC% SQLite3 extension not available
) else (
    echo %GREEN%[SUCCESS]%NC% SQLite3 is available
)

echo.
set /p create_project="Would you like to create a new Arknox project? (y/n): "
if /i "!create_project!"=="y" (
    set /p project_name="Enter project name: "
    
    if not "!project_name!"=="" (
        echo %BLUE%[INFO]%NC% Creating new Arknox project: !project_name!
        
        REM Create project directory
        if not exist "!project_name!" mkdir "!project_name!"
        cd "!project_name!"
        
        REM Initialize project structure
        echo %BLUE%[INFO]%NC% Setting up project structure...
        
        REM Setup environment (if arknox.bat exists)
        if exist "arknox.bat" (
            call arknox.bat env:setup
        )
        
        echo %GREEN%[SUCCESS]%NC% Project '!project_name!' created successfully!
        echo.
        echo %BLUE%[INFO]%NC% To get started:
        echo   cd !project_name!
        echo   arknox serve
    )
)

echo.
echo %GREEN%[SUCCESS]%NC% 🎉 Arknox Framework setup completed!
echo.
echo %BLUE%[INFO]%NC% 📋 System Summary:
echo   ✓ PHP !PHP_VERSION!
if defined COMPOSER_VERSION echo   ✓ Composer !COMPOSER_VERSION!
if defined GIT_VERSION echo   ✓ Git !GIT_VERSION!
if defined NODE_VERSION echo   ✓ Node.js !NODE_VERSION!

echo.
echo %BLUE%[INFO]%NC% 🚀 Quick start commands:
echo   arknox --version          Show framework version
echo   arknox about              Show framework information  
echo   arknox serve              Start development server
echo   arknox make:model User    Create a new model
echo.
echo %BLUE%[INFO]%NC% 💡 Windows Development Tips:
echo   • Use Windows Terminal or PowerShell for better CLI experience
echo   • Consider using XAMPP or WAMP for local development environment
echo   • Install Windows Subsystem for Linux (WSL) for Unix-like experience
echo   • Use Visual Studio Code with PHP extensions for development
echo.
echo %BLUE%[INFO]%NC% 📚 Documentation: https://docs.arknox.dev
echo %BLUE%[INFO]%NC% 🌐 Website: https://arknox.dev
echo.
echo %GREEN%[SUCCESS]%NC% Happy coding with Arknox Framework! 🚀
echo.
pause
