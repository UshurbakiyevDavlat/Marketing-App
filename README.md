# MARKY - Платформа для отправки маркетинговых писем и аналитики

MARKY — это серверное приложение, разработанное на Laravel, предназначенное для управления маркетинговыми email-кампаниями и анализа их эффективности. Фронтенд часть будет реализована отдельно.

## Основные функции

- Управление списками рассылки
- Создание и отправка email-кампаний
- Сбор и анализ статистики по открытию и кликам
- Управление пользователями и их ролями
- Биллинг функционал
- Распределение ограничений согласно оплаченному тарифу

## Технологический стек

- **Backend:** Laravel 11
- **База данных:** PostgreSQL
- **Контейнеризация:** Docker

## Требования

- Docker и Docker Compose
- PHP 8.0 или выше
- Composer

## Установка и запуск

~~~
1. git clone https://github.com/UshurbakiyevDavlat/Marketing-App.git
2. cd Marketing-App 
3. docker compose exec marky_app /bin/bash
4. cp .env.example .env
5. composer install
6. php artisan key:generate
7. docker-compose up -d
8. php artisan migrate
~~~
