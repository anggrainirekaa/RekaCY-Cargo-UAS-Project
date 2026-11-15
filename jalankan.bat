@echo off
chcp 65001 >nul
title RekaCy - Server
cd /d "%~dp0"
echo Menjalankan WebSocket (Reverb) di jendela terpisah...
start "RekaCy Reverb (jangan ditutup)" cmd /k php artisan reverb:start
timeout /t 2 /nobreak >nul
start "" http://127.0.0.1:8000
php artisan serve
