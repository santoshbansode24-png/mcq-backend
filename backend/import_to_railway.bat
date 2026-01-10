@echo off
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
