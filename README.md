# 🍹 Bar Bot

Telegram-бот для управления баром на Laravel 12 + RoadRunner + Nutgram + PostgreSQL.

## Стек

- **Laravel 12** + **Laravel Octane** (RoadRunner)
- **Nutgram** — Telegram Bot фреймворк
- **PostgreSQL 18**
- **ngrok** — туннель для webhook локально
- **Docker Compose**

## Быстрый старт

### 1. Клонируем и настраиваем

```bash
cp .env.example .env
```

Заполни в `.env`:
```
TELEGRAM_BOT_TOKEN=   # от @BotFather
TELEGRAM_WEBHOOK_SECRET=  # любая строка: openssl rand -hex 32
NGROK_AUTHTOKEN=      # с https://dashboard.ngrok.com (бесплатный аккаунт)
APP_KEY=              # сгенерируем ниже
```

### 2. Кладём данные

```bash
cp /path/to/recipes_final.json data/
```

### 3. Запускаем

```bash
docker compose up -d
```

### 4. Инициализация

```bash
# Генерация ключа
docker compose exec app php artisan key:generate

# Миграции
docker compose exec app php artisan migrate

# Импорт рецептов
docker compose exec app php artisan bar:import

# Узнаём URL ngrok
curl http://localhost:4040/api/tunnels | python3 -m json.tool
# Ищем "public_url" вида https://xxxx.ngrok-free.app

# Регистрируем webhook (вставь свой ngrok URL)
docker compose exec app php artisan bar:webhook:set https://xxxx.ngrok-free.app
```

### 5. Готово!

Открой бота в Telegram и отправь `/start`.

---

## Команды бота

| Команда | Описание |
|---------|----------|
| `/start` | Приветствие и меню |
| `/search` | Поиск по названию |
| `/ingredients` | Поиск по ингредиентам |
| `/filter` | Фильтры (крепость, объём, бокал, теги) |
| `/help` | Помощь |

---

## Структура проекта

```
bar-bot/
├── app/
│   ├── Models/                    # Recipe, Ingredient, RecipeIngredient, RecipeTag
│   ├── Telegram/
│   │   ├── Handlers/              # StartHandler, RecipeHandler
│   │   └── Conversations/         # SearchByName, SearchByIngredient, Filter
│   └── Console/Commands/          # ImportRecipes, SetWebhook
├── database/migrations/           # Схема БД
├── routes/telegram.php            # Роуты бота
├── data/                          # recipes_final.json
├── docker/Dockerfile
└── docker-compose.yml
```

---

## Вкусоматика (TODO)

Поле `taste_tags` в таблице `recipes` готово для вкусоматики.
Планируемые теги: `sweet`, `sour`, `bitter`, `fruity`, `smoky`, `herbal`, `spicy`, `creamy`.

Заполнить можно командой (напишем позже):
```bash
php artisan bar:taste:fill
```

---

## Полезные команды

```bash
# Логи
docker compose logs -f app

# Перезапуск
docker compose restart app

# Сбросить и пересоздать БД
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan bar:import

# Удалить webhook
docker compose exec app php artisan nutgram:delete-webhook
```
