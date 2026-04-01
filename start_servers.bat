@echo off
echo ===================================================
echo   CodeCanvas Server Starter
echo ===================================================
echo.
echo Starting PHP user server at localhost:8000...
start php -S localhost:8000 -t public
echo Starting Admin Dashboard at localhost:8080...
start php -S localhost:8080 -t admin
echo.
echo Both servers running!
echo User App: http://localhost:8000/dashboard.php
echo Admin Panel: http://localhost:8080/dashboard.php
echo.
pause
