# Phase 3.1: Заказы коктейлей — Design

**Date:** 2026-05-26
**Branch:** `feature/bb8_orders`

---

## Goal

Гости могут заказывать коктейли во время активной бар-сессии, бармен принимает или отклоняет заказы с выбором количества, гость получает уведомление о решении и может просмотреть свои заказы за вечер.

---

## Ключевые решения

### Accept flow: один тап, не два

Уведомление барменю сразу содержит кнопки количества + отказ — никакой промежуточной «Принять»-кнопки и никакого Conversation. Максимум — 5 порций, кнопки в убывающем порядке (наиболее вероятный выбор — первый):

```
[✅ Все (×5)]  [✅ ×4]  [✅ ×3]  [✅ ×2]  [✅ ×1]
[❌ Отказать]
```

Callback patterns:
- `order:qty:{orderId}:{n}` — принять с количеством n (1–5)
- `order:cancel:{orderId}` — отказать

После тапа у бармена: кнопки из уведомления убираются (`editMessageReplyMarkup` → пустой). Никакого дополнительного текста бармену не нужен — действие само говорит за себя.

### Условие кнопки «Заказать»

Кнопка `[🛒 Заказать]` появляется только при наличии активной `BarSession`. Проверка инвентаря не делается — это на усмотрение бармена.

### Уведомления гостю

При accept или cancel — бот шлёт новое сообщение на `telegram_id` гостя (через `$order->user->telegram_id`). При accept: «✅ Заказ принят! {name} ×N — уже готовим 🍸». При cancel: «❌ Заказ на {name} отклонён 😔».

### Страница заказов гостя

Доступна из двух точек:
1. Кнопка «📋 Мои заказы» в `StartAction` — показывается всем при активной сессии
2. Кнопка «📋 Мои заказы» на карточке рецепта — появляется после успешного размещения заказа (заменяет «🛒 Заказать»)

Callback pattern: `orders:my`

Содержимое: список заказов пользователя в текущей сессии с иконкой статуса. Если заказов нет — пустое состояние.

---

## Трудные моменты

### DB: session_id BIGINT → SMALLINT

Существующая таблица `orders` (заглушка Phase 1) имеет `session_id BIGINT` без FK. Phase 3 пересоздала `bar_sessions` с `SMALLINT PK` и CASCADE-дропнула FK. Нужна ALTER-миграция:
- `ALTER COLUMN session_id TYPE SMALLINT`
- Восстановить `fk_orders_session_id → bar_sessions(id)`

Тип `order_status_type` (`pending` / `accepted` / `cancelled`) уже создан в исходной миграции — не пересоздавать.

### Идемпотентность accept/cancel

Если бармен тапнет дважды (например, отредактированное сообщение ещё видно) — второй вызов должен вернуть `answerCallbackQuery("Заказ уже обработан")` без изменения данных. Handler проверяет статус до UPDATE.

### Отправка сообщения гостю из обработчика

`AcceptOrderHandler` и `CancelOrderHandler` — чистая бизнес-логика без доступа к Nutgram. Отправку уведомления гостю делает **Action**, уже после вызова Handler, используя `$bot->sendMessage(chat_id: $guestTelegramId)`.

---

## Контракты между компонентами

### Callback patterns (routes/telegram.php)

| Pattern | Action | Middleware |
|---|---|---|
| `recipe:order:{id}` | `PlaceOrderAction::fromTelegram` | — |
| `order:qty:{id}:{n}` | `AcceptOrderAction::fromTelegram` | `CanManageMiddleware` |
| `order:cancel:{id}` | `CancelOrderAction::fromTelegram` | `CanManageMiddleware` |
| `orders:my` | `ListOrdersAction::fromTelegram` | — |

### Новые классы

| Класс | Расположение |
|---|---|
| `PlaceOrderAction` | `app/Actions/Orders/` |
| `AcceptOrderAction` | `app/Actions/Orders/` |
| `CancelOrderAction` | `app/Actions/Orders/` |
| `ListOrdersAction` | `app/Actions/Orders/` |
| `PlaceOrderHandler` | `app/Handlers/Orders/` |
| `AcceptOrderHandler` | `app/Handlers/Orders/` |
| `CancelOrderHandler` | `app/Handlers/Orders/` |
| `ListGuestOrdersHandler` | `app/Handlers/Orders/` |
| `OrderStatus` (enum) | `app/Enums/` |

### Изменения в существующих файлах

| Файл | Изменение |
|---|---|
| `GetRecipeAction` | Добавить кнопку `recipe:order:{id}` при активной сессии |
| `BrowseRecipesAction` | Заменить noop-кнопку «Заказать» на `recipe:order:{id}` |
| `StartAction` | Добавить кнопку `orders:my` при активной сессии |
| `routes/telegram.php` | Новые 4 callback-маршрута |
| `routes/api.php` | `GET /api/sessions/{id}/orders`, `PATCH /api/orders/{id}` |

---

## HTTP API

- `GET /api/sessions/{id}/orders` — список заказов сессии (для бармена)
- `PATCH /api/orders/{id}` — обновить статус/количество (для внешних клиентов)

Аутентификация: `auth.telegram` middleware (как в остальных API-маршрутах).
