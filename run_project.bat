@echo off
echo ========================================================
echo   Binamira Medical Clinic - Starting Project Servers
echo ========================================================

:: Check if MySQL is already running on port 3306
netstat -ano | findstr ":3306 " >nul 2>&1
if %errorlevel% neq 0 (
    echo [1/2] Starting MySQL Server...
    start "MySQL Server" /min "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe" --datadir="C:\Users\Acer\.gemini\antigravity-ide\scratch\mysql_data" --port=3306 --console
    timeout /t 3 /nobreak >nul
) else (
    echo [1/2] MySQL is already running on port 3306.
)

:: Check if PHP server is running on port 8000
netstat -ano | findstr ":8000 " >nul 2>&1
if %errorlevel% neq 0 (
    echo [2/2] Starting PHP Web Server on http://localhost:8000...
    start "PHP Server" /min "C:\Users\Acer\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -S localhost:8000
    timeout /t 2 /nobreak >nul
) else (
    echo [2/2] PHP Web Server is already running on http://localhost:8000.
)

echo.
echo ========================================================
echo   Application is live at: http://localhost:8000
echo ========================================================
echo.
echo Demo Accounts:
echo   Doctor: doctor@binamira.com / password
echo   Nurse:  nurse@binamira.com / password
echo.
start http://localhost:8000
pause
