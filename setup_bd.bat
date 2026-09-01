@echo off
chcp 65001 >nul 2>&1
setlocal enabledelayedexpansion

set PSQL_BIN=C:\PostgreSQL\11\bin
set PGPASSWORD=postgres       :: замените на реальный пароль
set DB_HOST=localhost
set DB_USER=postgres
set DB_PORT=5432
set DB_NAME=user_db

set PATH=%PSQL_BIN%;%PATH%

echo ============================================================
echo   Настройка базы данных для управления пользователями
echo ============================================================
echo.

echo [1/5] Проверка утилиты psql...
where psql >nul 2>&1
if errorlevel 1 (
    echo [ОШИБКА] psql не найдена. Проверьте путь: %PSQL_BIN%
    pause
    exit /b 1
)
echo   psql найдена.
echo.

echo [2/5] Проверка подключения к PostgreSQL...
psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d postgres -c "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo [ОШИБКА] Не удалось подключиться к PostgreSQL.
    echo Выполняю пробную команду для отображения ошибки:
    psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d postgres -c "SELECT 1"
    pause
    exit /b 1
)
echo   Подключение успешно.
echo.

echo [3/5] Проверка базы данных "%DB_NAME%"...
psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d postgres -tA -c "SELECT 1 FROM pg_database WHERE datname='%DB_NAME%';" > %TEMP%\dbcheck.txt 2>nul
set /p DBEXISTS=<%TEMP%\dbcheck.txt
del %TEMP%\dbcheck.txt 2>nul

if "%DBEXISTS%"=="1" (
    echo   База данных "%DB_NAME%" уже существует.
) else (
    echo   База данных не найдена. Создаём...
    psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d postgres -c "CREATE DATABASE %DB_NAME%;" >nul 2>&1
    if errorlevel 1 (
        echo [ОШИБКА] Не удалось создать базу данных.
        psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d postgres -c "CREATE DATABASE %DB_NAME%;"
        pause
        exit /b 1
    )
    echo   База данных "%DB_NAME%" успешно создана.
)
echo.

echo [4/5] Проверка таблицы users...
psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d %DB_NAME% -c "\dt users" 2>nul | findstr "users" >nul
if errorlevel 1 (
    echo   Таблица users не найдена. Создаём...
    psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d %DB_NAME% -c "CREATE TABLE IF NOT EXISTS users (user_id VARCHAR(100) PRIMARY KEY, username VARCHAR(100) NOT NULL);" >nul 2>&1
    if errorlevel 1 (
        echo [ОШИБКА] Не удалось создать таблицу users.
        psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d %DB_NAME% -c "CREATE TABLE IF NOT EXISTS users (user_id VARCHAR(100) PRIMARY KEY, username VARCHAR(100) NOT NULL);"
        pause
        exit /b 1
    )
    echo   Таблица users успешно создана.
) else (
    echo   Таблица users уже существует.
)
echo.

echo [5/5] Проверка данных в таблице...
psql -U %DB_USER% -h %DB_HOST% -p %DB_PORT% -d %DB_NAME% -tA -c "SELECT COUNT(*) FROM users;" 2>nul > %TEMP%\count.txt
set /p COUNT=<%TEMP%\count.txt
del %TEMP%\count.txt 2>nul
if "%COUNT%"=="" set COUNT=0
echo   В таблице %COUNT% записей.
echo.

echo ============================================================
echo   Настройка завершена успешно!
echo ============================================================
pause