@echo off

REM This file is a part of the PZ Bot project.
REM Copyright (c) 2024-present Valithor Obsidion [valithor@valzargaming.com]

REM Check if launch parameters are provided
if "%~1"=="" (
    echo Usage: %0 SERVER_IP [RCON_PASSWORD] [RCON_PORT] [MCRCON_PATH]
    exit /b 1
)

set SERVER_IP=%~1
set RCON_PASSWORD=%~2
set RCON_PORT=%~3
set MCRCON_PATH=%~4

if "%SERVER_IP%"=="" (
    echo Error: Failed to resolve server IP address.
    exit /b 1
)

REM Use mcrcon to get player count
%MCRCON_PATH% -H %SERVER_IP% -P %RCON_PORT% -p %RCON_PASSWORD% "players" "a" > pz_output.txt

REM Check if the command was successful
if ERRORLEVEL 1 (
    echo Error: Failed to execute mcrcon command.
    exit /b 1
)

REM Read pz_output.txt, skipping the first line and removing the first character from subsequent lines
setlocal enabledelayedexpansion

REM Skip the first line using 'more' command and process subsequent lines
(for /f "skip=1 tokens=* delims=" %%i in (pz_output.txt) do (
    set "line=%%i"
    echo !line:~1!
)) > pz_players.txt

REM Display pz_players.txt content
type pz_players.txt

REM Clean up temporary files
del pz_output.txt
del pz_players.txt

endlocal