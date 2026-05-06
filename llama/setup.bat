@echo off
title SSCMS Local AI Setup
color 0A
echo ============================================
echo  SSCMS Offline AI Setup - llama.cpp
echo ============================================
echo.

:: Check if llama-cli.exe exists
if exist "%~dp0llama-cli.exe" (
    echo [OK] llama-cli.exe found
) else (
    echo [MISSING] llama-cli.exe not found!
    echo.
    echo Please download llama.cpp from:
    echo https://github.com/ggerganov/llama.cpp/releases/latest
    echo.
    echo Download the Windows AVX2 build, extract, and copy:
    echo   llama-cli.exe  ^(and any .dll files^)
    echo into this folder: %~dp0
    echo.
    pause
    exit /b 1
)

:: Check for GGUF model
dir /b "%~dp0..\ai-model\*.gguf" >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] GGUF model found in /ai-model/
) else (
    echo [MISSING] No .gguf model in /ai-model/
    echo.
    echo Download TinyLlama from:
    echo https://huggingface.co/TheBloke/TinyLlama-1.1B-Chat-v1.0-GGUF
    echo File: tinyllama-1.1b-chat-v1.0.Q4_K_M.gguf
    echo Place it in: %~dp0..\ai-model\
    echo.
    pause
    exit /b 1
)

echo.
echo [TEST] Running a quick inference test...
echo.

for /f "delims=" %%i in ('dir /b "%~dp0..\ai-model\*.gguf"') do set MODEL_FILE=%~dp0..\ai-model\%%i

"%~dp0llama-cli.exe" -m "%MODEL_FILE%" --threads 2 -n 30 --temp 0.5 -p "[INST] Say hello in one sentence. [/INST]" 2>nul

echo.
echo ============================================
if %errorlevel% equ 0 (
    echo  Setup complete! Local AI is ready.
) else (
    echo  Test failed. Check llama-cli.exe and model.
)
echo ============================================
pause
