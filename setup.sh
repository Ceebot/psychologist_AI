#!/bin/bash
set -e

# =============================================================================
# setup.sh — развёртывание psychologist_AI (Docker + миграции)
# =============================================================================

echo "===> 1. Проверка Docker и Docker Compose..."
command -v docker >/dev/null 2>&1 || { echo "Ошибка: Docker не установлен."; exit 1; }
command -v docker compose >/dev/null 2>&1 || command -v docker-compose >/dev/null 2>&1 || { echo "Ошибка: Docker Compose не найден."; exit 1; }

echo "===> 2. Запуск контейнеров (сборка образов при первом запуске)..."
docker compose up -d --build

echo "===> 3. Ожидание готовности PostgreSQL (≈5 сек)..."
sleep 5

echo "===> 4. Выполнение миграций в контейнере php..."
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction

echo ""
echo "=== Готово. Приложение доступно: ==="
echo "  • Приложение:  http://localhost:9090"
echo "  • Adminer (БД): http://localhost:9091"
echo ""
