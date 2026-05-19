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

## «Как НЕ надо»

```php
// FilterConversation::showResults
$results = app(SearchRecipesHandler::class)->handle($data); // ❌ обход Action
$bot->sendMessage(...);                                      // ❌ рендер в Conversation
```

## «Как надо»

```php
// FilterConversation::showResults
app(SearchRecipesAction::class)->fromTelegram($bot, $data); // ✅ Action отвечает и за поиск, и за рендер
```

## Чек-лист для ревью

- [ ] `grep -rn "app(.*Handler::class)" app/Telegram/` → 0 совпадений
- [ ] Все callback/command routes в `routes/telegram.php` указывают на `[Action::class, 'fromTelegram']` (или `Action::class` для invokable HTTP-Action)
- [ ] В `app/Telegram/Handlers/` нет классов с бизнес-вызовами (или каталог не существует)
