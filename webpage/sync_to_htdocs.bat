@echo off
SETLOCAL

REM Paths (update these)
set SOURCE_DIR=D:\projects\thesis_checker_repo
set TARGET_DIR=C:\xampp\htdocs\thesis_checker

REM Exclude files (like .git folder)
set EXCLUDES=/XD .git /XF .gitignore

REM Sync files
echo Syncing files from %SOURCE_DIR% to %TARGET_DIR%...
robocopy "%SOURCE_DIR%" "%TARGET_DIR%" /MIR %EXCLUDES%

echo Done!
pause