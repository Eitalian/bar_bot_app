# Phase 1.1 — Роли и контроль доступа

**Date:** 2026-04-27
**Branch:** `feature/bb5-s1_roles-access`
**Task:** bb5-s1

---

## Контекст

Введение ролей пользователей и ограничение доступа к управлению баром.
Phase 1 позволяла любому пользователю добавлять/удалять из инвентаря. Phase 1.1 закрывает это через роли.

---

## Роли

`guest` (по умолчанию) — только просмотр инвентаря.
`bartender` / `owner` — полное управление баром (добавление/удаление инвентаря и будущие фичи: сессии, заказы).

Назначение ролей — вручную в базе данных.

---

## Data Layer

### Миграция

Новый файл `2026_04_27_000003_alter_users_add_role.php`:

```sql
CREATE TYPE user_role AS ENUM ('guest', 'bartender', 'owner');

ALTER TABLE users
    ADD COLUMN role user_role NOT NULL DEFAULT 'guest';
```

### PHP Enum

`app/Enums/UserRole.php`:

```php
enum UserRole: string
{
    case Guest     = 'guest';
    case Bartender = 'bartender';
    case Owner     = 'owner';

    public function canManage(): bool
    {
        return match($this) {
            self::Bartender, self::Owner => true,
            self::Guest                  => false,
        };
    }
}
```

### User Model

- Добавить `role` в `$fillable`
- Добавить cast: `'role' => UserRole::class`
- Добавить метод: `canManageInventory(): bool` → `$this->role->canManage()`
- Добавить `UserRole` в аннотацию `@property`

---

## Authorization

### Gate

`AuthorizationServiceProvider` (новый, регистрируется в `bootstrap/providers.php`):

```php
Gate::define('can-manage', fn(User $u) => $u->role->canManage());
```

Единая точка управления — все будущие фичи управления баром используют тот же gate.

### Middleware

Один класс `app/Middleware/CanManageMiddleware.php` для обоих каналов:

```php
final class CanManageMiddleware
{
    // Laravel HTTP pipeline
    public function handle($request, Closure $next): mixed
    {
        Gate::authorize('can-manage');
        return $next($request);
    }

    // Nutgram bot pipeline (метод уточнить по исходникам Nutgram)
    public function __invoke(Nutgram $bot, callable $next): void
    {
        Gate::authorize('can-manage');
        $next($bot);
    }
}
```

> **Примечание:** Точный интерфейс Nutgram middleware (метод `__invoke` vs другой) требует верификации по исходникам `vendor/nutgram/nutgram/src/`.

### HTTP Routes

```php
// routes/api.php
Route::middleware(CanManageMiddleware::class)->group(function () {
    Route::post('/inventory', AddInventoryAction::class);
    Route::delete('/inventory/{id}', [RemoveInventoryAction::class, '__invoke']);
});
```

### Telegram Routes

```php
// routes/telegram.php
$bot->group(function (Nutgram $bot) {
    $bot->onCallbackQueryData('inventory:add', fn(Nutgram $bot) => AddInventoryConversation::begin($bot));
    $bot->onCallbackQueryData('inventory:remove:{id}', [RemoveInventoryAction::class, 'fromTelegram']);
})->middleware(CanManageMiddleware::class);
```

### Обработка ошибок Telegram

```php
$bot->onException(AuthorizationException::class, function (Nutgram $bot) {
    $bot->answerCallbackQuery('🚫 Нет доступа', show_alert: true);
});
```

---

## Что НЕ входит в Phase 1.1

- UI для назначения ролей (только ручное редактирование БД)
- Разграничение `bartender` vs `owner` (оба `canManage() = true`)
- Авторизация HTTP API через токены (Phase 6)
