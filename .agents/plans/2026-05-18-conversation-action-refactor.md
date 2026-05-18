# Conversation × Action Architecture: Rule + Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.
>
> **Before starting:** Read `.agents/knowledge/codebase.md`
> **After completing:** Update `.agents/knowledge/codebase.md` — зафиксировать новое архитектурное правило, новые Action'ы и Response-классы, удалить упоминания `app/Telegram/Handlers/`.

**Goal:** Зафиксировать архитектурное правило «Action — единая точка входа per (UI × use-case)» в проектной спеке и привести существующий код к этому правилу одним рефакторингом без изменения внешнего поведения.

**Architecture:**
- Action владеет ингрессом и эгрессом своего UI; Handler — чистая бизнес-логика; Conversation — orchestration + state, на side-effect-точке делегирует в `Action::fromTelegram(...)`.
- Один класс на use-case, разные UI = разные методы (`__invoke` для HTTP, `fromTelegram` для Telegram).
- Conversation НЕ зовёт Handler напрямую.
- Каталог `app/Telegram/Handlers/` после рефакторинга исчезает — все его классы по факту были Action'ами под чужим именем.

**Branch:** `refactor/conversation-action-boundary` _(ответвить от `master` после мержа PR #4)_

---

## Карта файлов

### Новые файлы

| Файл | Ответственность |
|---|---|
| `.agents/specs/conversation-action-architecture.md` | Спека правила: диаграмма потока + 6 инвариантов + примеры + чек-лист для ревью |
| `app/Actions/Search/SearchByIngredientAction.php` | Action для поиска по ингредиентам; метод `fromTelegram(Nutgram, SearchByIngredientData)` |
| `app/Actions/Search/BrowseRecipesAction.php` | Замена `Telegram/Handlers/RecipeBrowseHandler`; метод `fromTelegram(Nutgram, string $browseKey, int $pos)` |
| `app/Actions/StartAction.php` | Замена `Telegram/Handlers/StartHandler`; метод `fromTelegram(Nutgram)` |
| `app/Telegram/Responses/SearchResultsResponse.php` | Инкапсуляция рендера списка рецептов с клавиатурой; используется в `SearchRecipesAction` и `SearchByIngredientAction` |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `app/Actions/Search/SearchRecipesAction.php` | Добавить `fromTelegram(Nutgram, SearchRecipesData)`: вызвать handler, сохранить ids через `BrowseContext::store`, отрендерить через `SearchResultsResponse`. Header выбирается внутри Action по `$data->q` (search vs filter) |
| `app/Actions/Search/GetRecipeAction.php` | Добавить `fromTelegram(Nutgram, string $id)`: логика как в текущем `RecipeHandler` |
| `app/Telegram/Conversations/FilterConversation.php` | `showResults()` → `app(SearchRecipesAction::class)->fromTelegram($bot, $data)` |
| `app/Telegram/Conversations/SearchByNameConversation.php` | То же для своего `showResults()` |
| `app/Telegram/Conversations/SearchByIngredientConversation.php` | `showResults()` → `app(SearchByIngredientAction::class)->fromTelegram($bot, $data)` |
| `routes/telegram.php` | `RecipeBrowseHandler::class` → `[BrowseRecipesAction::class, 'fromTelegram']`. `RecipeHandler::class` → `[GetRecipeAction::class, 'fromTelegram']`. `StartHandler::class` → `[StartAction::class, 'fromTelegram']` (обе вхождения) |
| `CLAUDE.md` | Раздел Architecture: ссылка на `.agents/specs/conversation-action-architecture.md` |
| `.agents/knowledge/codebase.md` | Убрать упоминания `app/Telegram/Handlers/`; зафиксировать новые Action'ы и Response-классы |

### Удаляемые файлы

| Файл | Причина |
|---|---|
| `app/Telegram/Handlers/RecipeHandler.php` | Заменён на `GetRecipeAction::fromTelegram` |
| `app/Telegram/Handlers/RecipeBrowseHandler.php` | Заменён на `BrowseRecipesAction::fromTelegram` |
| `app/Telegram/Handlers/StartHandler.php` | Заменён на `StartAction::fromTelegram` |
| Каталог `app/Telegram/Handlers/` | Пустой после миграции |

---

## Порядок исполнения

```
Группа 1 (последовательно): Task 1 (спека), Task 2 (SearchResultsResponse) — Task 1 первой потому что остальные на неё апеллируют в commit-message; Task 2 — зависимость для 3a/3c
Группа 2 (параллельно):     Tasks 3a, 3b, 3c, 3d, 3e — независимы: разные файлы, разные неймспейсы
Группа 3 (параллельно):     Tasks 4a, 4b, 4c, 4d — независимы: каждый Conversation в своём файле, routes/telegram.php одной задачей
Группа 4 (последовательно): Task 5 — удалить старые Telegram/Handlers/* + каталог; зависит от Группы 3 (routes уже не ссылаются)
Группа 5 (финал):           Task 6 — verification + codebase.md + CLAUDE.md + PR
```

---

## Task 1: Спека правила

**Depends on:** None
**Files:** Create `.agents/specs/conversation-action-architecture.md`

- [ ] Диаграмма: routes → Action::fromTelegram → Handler → Models; для Conversation — Conversation → Action.
- [ ] 6 инвариантов (Action = единая точка входа; Action владеет ингрессом/эгрессом; Handler чистый; Conversation orchestration; Conversation НЕ зовёт Handler; промежуточные шаги — UX, не side-effect).
- [ ] Примеры «как НЕ надо» / «как надо» (на примере `FilterConversation::showResults`).
- [ ] Чек-лист для ревью.

---

## Task 2: SearchResultsResponse

**Depends on:** None
**Files:** Create `app/Telegram/Responses/SearchResultsResponse.php`

- [ ] Конструктор: `header`, `recipes` (iterable), `browseKey`, `showVolume = true`, `overflowText = null`.
- [ ] Метод `text()` собирает шапку + список карточек + опциональный overflow.
- [ ] Метод `keyboard()` собирает inline-клавиатуру (по кнопке на рецепт + «🔙 К меню»).

---

## Tasks 3a–3e: Action'ы

**Depends on:** Task 2 (для 3a и 3c — рендер через `SearchResultsResponse`)

- [ ] **3a:** `SearchRecipesAction::fromTelegram` — вызвать handler, сохранить ids через `BrowseContext::store`, отрендерить через `SearchResultsResponse`. Header и empty-message выбираются внутри Action по `$data->q`.
- [ ] **3b:** `SearchByIngredientAction` — новый файл; кэп `RESULTS_LIMIT = 10`, overflow в шапке через `overflowText`.
- [ ] **3c:** `GetRecipeAction::fromTelegram` — `editMessageText` с клавиатурой «🔙 К поиску».
- [ ] **3d:** `BrowseRecipesAction` — пагинация ◀️/▶️, «🔙 К поиску», «🛒 Заказать»/«🍴 Форкнуть».
- [ ] **3e:** `StartAction::fromTelegram` — главное меню.

---

## Tasks 4a–4d: Conversation'ы и routes

**Depends on:** Группа 2

- [ ] **4a:** `FilterConversation::showResults` → `app(SearchRecipesAction::class)->fromTelegram($bot, $data)`. Удалить use `SearchRecipesHandler`, `BrowseContext`, `InlineKeyboardButton/Markup` если не нужны.
- [ ] **4b:** То же для `SearchByNameConversation`.
- [ ] **4c:** `SearchByIngredientConversation` → `app(SearchByIngredientAction::class)->fromTelegram($bot, $data)`.
- [ ] **4d:** `routes/telegram.php` — три замены: `RecipeBrowseHandler` → `[BrowseRecipesAction::class, 'fromTelegram']`, `RecipeHandler` → `[GetRecipeAction::class, 'fromTelegram']`, `StartHandler` → `[StartAction::class, 'fromTelegram']` (обе вхождения).

---

## Task 5: Удалить старые Handlers

**Depends on:** Группа 3 (routes уже не ссылаются)

- [ ] `git rm app/Telegram/Handlers/{RecipeHandler,RecipeBrowseHandler,StartHandler}.php`.
- [ ] Каталог `app/Telegram/Handlers/` исчезает.

---

## Task 6: Verification + codebase.md + CLAUDE.md + PR

**Depends on:** Tasks 1–5

- [ ] **Step 1: Verification**

```bash
make tests                              # all PASS
docker compose exec app ./vendor/bin/pint --test app/Actions/ app/Telegram/ routes/   # clean
grep -rn "app(.*Handler::class)" app/Telegram/   # 0 совпадений
test ! -d app/Telegram/Handlers && echo OK
```

- [ ] **Step 2: Обновить codebase.md** — новые Action'ы, `SearchResultsResponse`, удалить упоминания `app/Telegram/Handlers/`, обновить «Pattern for calling Action from Conversation».
- [ ] **Step 3: CLAUDE.md** — ссылка на новую спеку в разделе Architecture.
- [ ] **Step 4: Commit (один)**

```bash
git add ...
git commit --author="Claude <claude@anthropic.com>" -m "refactor: enforce Action as single entry point per (UI × use-case)"
```

- [ ] **Step 5: Открыть PR** — отметить в описании, что ручной smoke test через ngrok-бот нужен до мержа.

**Handoff (финальный):** все тесты pass; pint clean; `app/Telegram/Handlers/` отсутствует; codebase.md обновлён; PR открыт.

---

## Manual smoke test (для ревьюера перед мержем)

Автотестов на Telegram-флоу нет — после мержа этого рефакторинга прокликать через ngrok-бот:

- `/start` → главное меню рендерится
- «Поиск по названию» → "Negroni" → результаты + клик по карточке → карточка с навигацией
- «По ингредиентам» → "gin" → /done → результаты + карточки
- «Фильтры» → ABV «30%+» → результаты + навигация
- «Инвентарь» → ➕ → «Виски» → 500 → добавлено + список (sanity-check: Inventory-флоу не задет)
