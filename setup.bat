@echo off
chcp 65001 >nul
title RekaCy - Setup
cd /d "%~dp0"

where php >nul 2>&1 || (echo [ERROR] PHP tidak ditemukan. Pastikan PHP ada di PATH. & pause & exit /b 1)
where composer >nul 2>&1 || (echo [ERROR] Composer tidak ditemukan. & pause & exit /b 1)
where npm >nul 2>&1 || (echo [ERROR] Node.js/npm tidak ditemukan. & pause & exit /b 1)

echo [1/5] Memasang paket PHP  (butuh internet, hanya pertama kali)
call composer install --no-interaction
if errorlevel 1 goto :gagal

echo [2/5] Menyiapkan database SQLite + data demo
if not exist database\database.sqlite type nul > database\database.sqlite
call php artisan optimize:clear
call php artisan migrate:fresh --seed --force
if errorlevel 1 goto :gagal

echo [3/5] Memasang paket frontend
call npm install
if errorlevel 1 goto :gagal

echo [4/5] Build tampilan
call npm run build
if errorlevel 1 goto :gagal

echo [5/5] Menjalankan RekaCy di http://127.0.0.1:8000  (+ WebSocket Reverb di jendela lain)
echo.
echo Akun demo (password: password):
echo   admin@rekacy.test   operator@rekacy.test   customer@rekacy.test   courier@rekacy.test
echo.
start "RekaCy Reverb (jangan ditutup)" cmd /k php artisan reverb:start
timeout /t 2 /nobreak >nul
start "" http://127.0.0.1:8000
call php artisan serve
goto :eof

:gagal
echo.
echo [GAGAL] Ada langkah yang error. Salin pesan error di atas lalu kirim ke Claude.
pause
exit /b 1
