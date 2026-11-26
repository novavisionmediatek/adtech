@echo off
REM VidyaSetu - start the portal on http://localhost:8000
cd /d "%~dp0"
if not exist config.php copy config.example.php config.php >nul
set PHP=C:\php-8.3.33\php.exe
if not exist "%PHP%" set PHP=php
echo Starting VidyaSetu at http://localhost:8000  (Ctrl+C to stop)
"%PHP%" -d extension=openssl -d upload_max_filesize=300M -d post_max_size=310M -d max_execution_time=300 -d memory_limit=256M -S localhost:8000 -t public public\index.php
