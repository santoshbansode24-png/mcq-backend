@echo off
echo Importing database to Railway MySQL...
C:\xampp\mysql\bin\mysql.exe -h yemanotc.proxy.rlwy.net -P 24548 -u root -pMvvImvVmcEUnrvMncVtVDbyYhqdcTuu7 railway < c:\xampp\htdocs\veeru\railway_database_export.sql
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
