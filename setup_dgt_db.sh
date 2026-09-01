#!/bin/bash

# Настройки подключения
PGPASSWORD=postgres
export PGPASSWORD
DB_HOST=localhost
DB_USER=postgres
DB_PORT=5432
DB_NAME=user_db

# Цвета для вывода (опционально)
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "============================================================"
echo "  Настройка базы данных для управления пользователями"
echo "============================================================"
echo ""

# 1. Проверка наличия psql
echo -e "[1/4] Проверка утилиты psql..."
if ! command -v psql &> /dev/null; then
    echo -e "${RED}[ОШИБКА] psql не найдена. Установите postgresql-client.${NC}"
    exit 1
fi
echo -e "  ${GREEN}psql найдена.${NC}"
echo ""

# 2. Проверка подключения
echo "[2/4] Проверка подключения к PostgreSQL..."
if ! psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d postgres -c "SELECT 1" &> /dev/null; then
    echo -e "${RED}[ОШИБКА] Не удалось подключиться к PostgreSQL.${NC}"
    echo "Выполняю пробную команду для отображения ошибки:"
    psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d postgres -c "SELECT 1"
    exit 1
fi
echo -e "  ${GREEN}Подключение успешно.${NC}"
echo ""

# 3. Проверка существования базы данных (правильная логика)
echo "[3/4] Проверка базы данных \"$DB_NAME\"..."
DB_EXISTS=$(psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d postgres -tA -c "SELECT 1 FROM pg_database WHERE datname='$DB_NAME';")
if [ "$DB_EXISTS" == "1" ]; then
    echo -e "  ${YELLOW}База данных \"$DB_NAME\" уже существует.${NC}"
else
    echo -e "  ${YELLOW}База данных не найдена. Создаём...${NC}"
    if ! psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d postgres -c "CREATE DATABASE $DB_NAME;" &> /dev/null; then
        echo -e "${RED}[ОШИБКА] Не удалось создать базу данных.${NC}"
        psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d postgres -c "CREATE DATABASE $DB_NAME;"
        exit 1
    fi
    echo -e "  ${GREEN}База данных \"$DB_NAME\" успешно создана.${NC}"
fi
echo ""

# 4. Проверка таблицы users
echo "[4/4] Проверка таблицы users..."
TABLE_EXISTS=$(psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -tA -c "SELECT 1 FROM information_schema.tables WHERE table_name='users';")
if [ "$TABLE_EXISTS" == "1" ]; then
    echo -e "  ${YELLOW}Таблица users уже существует.${NC}"
else
    echo -e "  ${YELLOW}Таблица users не найдена. Создаём...${NC}"
    if ! psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -c "CREATE TABLE users (user_id VARCHAR(100) PRIMARY KEY, username VARCHAR(100) NOT NULL);" &> /dev/null; then
        echo -e "${RED}[ОШИБКА] Не удалось создать таблицу users.${NC}"
        psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -c "CREATE TABLE users (user_id VARCHAR(100) PRIMARY KEY, username VARCHAR(100) NOT NULL);"
        exit 1
    fi
    echo -e "  ${GREEN}Таблица users успешно создана.${NC}"
fi
echo ""

# Вывод количества записей (опционально)
COUNT=$(psql -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -tA -c "SELECT COUNT(*) FROM users;" 2>/dev/null)
if [ -z "$COUNT" ]; then
    COUNT=0
fi
echo -e "  В таблице ${GREEN}$COUNT${NC} записей."
echo ""

echo "============================================================"
echo -e "${GREEN}  Настройка завершена успешно!${NC}"
echo "============================================================"