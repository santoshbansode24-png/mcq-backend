@echo off
setlocal
title Veeru Database Importer (PHP Method)

echo ========================================================
echo   VEERU DATABASE IMPORTER (PHP Method)
echo   We are using PHP to bypass the Plugin Error.
echo ========================================================
echo.

"C:\xampp\php\php.exe" "C:\xampp\htdocs\veeru\backend\cloud_importer.php"

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Something went wrong.
    pause
    exit /b %errorlevel%
)

pause
