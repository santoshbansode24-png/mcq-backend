@echo off
setlocal
echo ============================================================
echo !!! CRITICAL WARNING: PRODUCTION DATABASE SYNC !!!
echo ============================================================
echo This script will OVERWRITE your Railway Production Database
echo with your LOCAL data.
echo.
echo DANGER: This will WIPE ALL LIVE USER ACCOUNTS AND PROGRESS
echo that exist on Railway but NOT on your local machine.
echo.
set /p confirm="Do you REALLY want to proceed? (Type YES to confirm): "
if /i "%confirm%" neq "YES" (
    echo Sync cancelled. Safety first!
    pause
    exit /b
)

echo.
echo Exporting local database to file...
C:\xampp\mysql\bin\mysqldump.exe -u root --skip-triggers --skip-routines --hex-blob veeru_db > c:\xampp\htdocs\veeru\railway_database_export.sql

echo Importing database to Railway via PHP...
C:\xampp\php\php.exe c:\xampp\htdocs\veeru\import_to_railway.php
if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo SUCCESS! Database imported to Railway!
    echo ========================================
) else (
    echo.
    echo ========================================
    echo ERROR! Import failed. Error code: %ERRORLEVEL%
    echo ========================================
)
pause
