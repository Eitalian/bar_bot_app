# Conversation × Action Architecture

Архитектурное правило: **Action — единая точка входа per (UI × use-case)**. Conversation отвечает за оркестрацию и UX, Handler — за чистую бизнес-логику. Side-effect выполняется ТОЛЬКО через Action.

## Диаграмма

```
routes/api.php       → Action::__invoke(Request)        → Handler → Models
routes/cli.php       → Action::__invoke(...)            → Handler → Models
routes/telegram.php
  ├─ stateless callback / command  → Action::fromTelegram(Nutgram, ...)         → Handler → Models
  └─ multi-step flow               → Conversation → Action::fromTelegram(...)   → Handler → Models
```

## Инварианты

1. **Action = единая точка входа в поведение** для конкретного `UI × use-case`. Один класс на use-case; разные UI = разные методы на этом классе (`__invoke` для HTTP, `fromTelegram` для Telegram, и т.д.).
2. **Action владеет ингрессом и эгрессом** в форме своего UI. Парсит входной payload, форматирует ответ.
3. **Handler = чистая бизнес-логика**, transport-agnostic. Принимает DTO, возвращает result. Вызывается **только из Action**.
4. **Conversation = orchestration + state + UI-rendering** для multi-step Telegram UX. Допустимая толщина: парсинг `callback_data`, держание state в свойствах (Nutgram сериализует), `sendMessage` для промежуточных экранов, `next()` для роутинга.
5. **Conversation НЕ зовёт Handler напрямую.** На side-effect-точке Conversation делегирует в `Action::fromTelegram(...)`.
6. Промежуточные шаги (выбор фильтра, накопление state) — UX-навигация, не side-effect; Action для них не создаём.

## «Как НЕ надо» — #1: Conversation зовёт Handler напрямую

```php
// FilterConversation::showResults
$results = app(SearchRecipesHandler::class)->handle($data); // ❌ обход Action
$bot->sendMessage(...);                                      // ❌ рендер в Conversation
```

## «Как надо» — #1

```php
// FilterConversation::showResults
app(SearchRecipesAction::class)->fromTelegram($bot, $data); // ✅ Action отвечает и за поиск, и за рендер
```

## «Как НЕ надо» — #2: один use-case разбит на два Action по транспорту

Telegram и HTTP реализуются в разных фазах. Агент второй фазы видит `AcceptOrderAction` только с
`fromTelegram` — и создаёт отдельный `UpdateOrderAction::__invoke` вместо того, чтобы добавить
`__invoke` в существующий класс. Это нарушает инвариант «один класс на use-case».

```php
// ❌ НЕПРАВИЛЬНО: use-case "принять заказ" разбит на два класса
// AcceptOrderAction::fromTelegram(...)  — Telegram
// UpdateOrderAction::__invoke(...)      — HTTP, агрегирует accept+cancel через поле status

// ✅ ПРАВИЛЬНО: оба транспорта — методы одного класса
final class AcceptOrderAction
{
    public function __invoke(Request $request, int $id): JsonResponse  // HTTP
    { ... }

    public function fromTelegram(Nutgram $bot, string $id, int $n): void  // Telegram
    { ... }
}
```

**Правило:** если для use-case уже существует Action с `fromTelegram` — HTTP-вход добавляется
методом `__invoke` в тот же класс. Класс `UpdateFooAction` / `ManageFooAction` — сигнал нарушения.

## Auth в `fromTelegram`

Middleware `AuthenticateTelegramUser` вызывается раньше любого Action и выполняет `Auth::setUser($user)`. Это означает, что `Auth::id()` доступен во **всех** `fromTelegram`-методах без дополнительных запросов.

```php
// ❌ ЛИШНИЙ ЗАПРОС к БД — user уже аутентифицирован middleware
$userId = User::where('telegram_id', $bot->userId())->value('id');

// ✅ Auth::id() всегда корректен в fromTelegram (и в Action, и в Conversation)
$userId = Auth::id();
```

**Grep-маркер:** `User::where('telegram_id'` встречаться должен ТОЛЬКО в `app/Telegram/Middleware/AuthenticateTelegramUser.php`.

## Чек-лист для ревью

- [ ] `grep -rn "app(.*Handler::class)" app/Telegram/` → 0 совпадений
- [ ] `grep -rn "User::where('telegram_id'" app/Actions/ app/Telegram/Conversations/` → 0 совпадений
- [ ] Все callback/command routes в `routes/telegram.php` указывают на `[Action::class, 'fromTelegram']` (или `Action::class` для invokable HTTP-Action)
- [ ] В `app/Telegram/Handlers/` нет классов с бизнес-вызовами (или каталог не существует)
- [ ] Нет `Update*Action` / `Manage*Action` классов, если для того же use-case уже существует `AcceptXAction` / `CancelXAction` с `fromTelegram`
