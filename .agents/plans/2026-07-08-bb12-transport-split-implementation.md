# BB-12: Разделение транспортов + фундамент качества — Implementation Plan

> **Формат:** план по `_template.md`, адаптирован под **ручную работу одного человека в свободное время**
> (а не под субагент-диспетчер): все группы последовательные, каждая задача — атомарная сессия
> с чек-пойнтом, после которой можно остановиться на любой срок.
>
> **Прочитай перед стартом (если открыл файл с нулевым контекстом):**
> - `.agents/designs/2026-06-23-bb12-ui-transport-split-design.md` — целевая структура и её обоснование (§2, §3 — обязательно);
> - раздел «Контекст с нуля» ниже — достаточен, чтобы работать, не перечитывая остальное;
> - **не читай** `.agents/knowledge/codebase.md` как истину о путях классов — он частично устарел (обновляется финальной задачей этого плана).

**Goal:** каждый use-case имеет отдельные HTTP- и Telegram-точки входа, разложенные вертикальными срезами `Context → UseCase` под `app/UI/Http/` и `app/UI/Telegram/`; поведение бота и API идентично текущему; изменения защищены новой сеткой (Larastan + смоук-тесты Telegram + CI), которой до этого плана не существовало.

**Architecture:**
- Транспорты развязываются по классам, бизнес-логика остаётся в `app/Handlers/` — Handler единственный владелец семантики use-case (инварианты дизайна §3).
- Хоп `Conversation → Telegram-Action` **сохраняется** (снятие хопа — отдельный design `2026-06-23-conversation-action-rework-design.md`, вне этого плана).
- Расширения относительно исходного дизайна BB-12 (приняты по итогам арх-ревью 2026-07-08): отказ от синхронного Bus (П-2), доменные события для уведомлений в Orders (П-1), реестр callback-маршрутов (П-3), `Bar` через DI в `AddInventoryHandler` (П-7), перенос ingredient-поиска из Conversations в Handler (behavior-preserving). Каждое расширение помечено в своей задаче меткой **[расширение BB-12]**.

**Tech Stack (новое):** `larastan/larastan` (dev), Nutgram testing kit (уже в `nutgram/laravel ^1.6`), Pest arch-тесты (уже в Pest 4), GitHub Actions.

**Branch:** `BB-12_architecture_restruct` (уже существует и активна)

---

## Контекст с нуля

**Проект:** Telegram-бот бара (Laravel 13 + Octane/RoadRunner + Nutgram + PostgreSQL 18, всё в Docker).
Функционал: инвентарь, поиск рецептов, бар-сессии, заказы, избранное/оценки. Два транспорта:
Telegram (основной, `routes/telegram.php`) и HTTP JSON API (`routes/api.php`, потребитель пока внутренний).

**Как устроен код сейчас (состояние на HEAD `848191f`):**

```
app/UI/Http/Actions/{Context}/FooAction.php   ← «двухголовые» Actions: __invoke(Request) для HTTP
                                                 И fromTelegram(Nutgram) для Telegram в одном классе
app/UI/Telegram/Conversations/                ← 5 multi-step диалогов Nutgram
app/UI/Telegram/Responses/                    ← 2 рендер-класса (текст+клавиатура); остальной рендер — inline в Actions
app/UI/Middleware/                            ← auth (TG и HTTP) + can-manage
app/Handlers/{Context}/                       ← бизнес-логика, чистая от транспорта (НЕ трогаем)
app/Data/{Context}/                           ← DTO (spatie/laravel-data)      (НЕ трогаем)
app/Models/, app/Services/, app/Jobs/         ← НЕ трогаем
```

**Что делаем:** режем каждый двухголовый Action на HTTP-класс и Telegram-класс и раскладываем
по вертикальным срезам (§2 дизайна):

```
app/UI/Http/{Context}/{UseCase}/{Action, Request, Response}     # UseCase-папка если классов ≥2
app/UI/Http/{Context}/{Action}                                  # иначе — плоско
app/UI/Telegram/{Context}/{UseCase}/{Action, Conversation, Keyboard}
app/UI/Telegram/Shared/                                         # кросс-контекстный рендер и реестр callback
```

**Ключевые правила (из дизайна, повторены здесь для автономности):**
1. Handler — единственный владелец семантики; обе точки входа одного use-case зовут один Handler.
2. Zero business logic в точках входа и Keyboard: только парсинг, сборка DTO, вызов Handler, рендер.
3. Рендер финального экрана — в Keyboard-классе; промежуточные промпты Conversation могут быть inline.
4. Conversation на финальном шаге зовёт `app(TelegramFooAction::class)->fromTelegram(...)` — хоп остаётся.
5. `Auth::id()` доступен во всех точках входа (middleware `AuthenticateTelegramUser` / `auth.telegram`);
   `User::where('telegram_id'` — только внутри `app/UI/Middleware/AuthenticateTelegramUser.php`.
6. Промоут: UseCase-папка заводится при ≥2 классах или наличии Conversation/Request/Response; один класс лежит плоско.
7. Контракт `callback_data` и набор URI/методов HTTP **не меняются** — поведение идентично.

**Команды (все через Docker):**
```bash
make tests             # весь suite Pest
make pint-dirty        # линт изменённых файлов
make stan              # появится в Task A1
make handoff           # появится в Task A1: tests + stan + pint за один вызов
docker compose exec app php artisan test --filter=Имя
docker compose exec app php artisan route:list   # сверка HTTP-роутов с эталоном
```

**Известные дефекты в мигрируемых флоу** (найдены арх-ревью 2026-07-08, всплывут в Task A2
при написании смоук-тестов — чинятся там же, без зелёной сетки рефакторинг не начинаем):
- `BrowseRecipesAction:38` ждёт `?Recipe`, но `GetRecipeHandler::handle()` возвращает `GetRecipeResult`
  → листание рецептов падает фатально (`->toTelegramMessage()` на DTO).
- `PlaceOrderAction::confirm:62` кладёт `$bot->userId()` (Telegram ID) в `PlaceOrderData::userId`
  → нарушение FK `orders.user_id → users(id)`; нужно `Auth::id()`.

---

## Карта файлов (сводная)

> Детальные списки «старое → новое» — внутри задач срезов. Здесь — уровень каталогов, чтобы видеть весь объём.

### Новые файлы

| Файл / группа | Ответственность |
|---|---|
| `phpstan.neon.dist`, `phpstan-baseline.neon` | Larastan, уровень 6, baseline на текущие ошибки |
| `.github/workflows/ci.yml` | CI: tests + stan + pint на каждый PR |
| `tests/Feature/Telegram/*Test.php` | смоук-тесты всех роутов `routes/telegram.php` (Task A2) |
| `tests/Arch/LayersTest.php` | арх-инварианты новой структуры (Task D2) |
| `app/UI/Telegram/Shared/CallbackRoute.php` | реестр callback-маршрутов + guard 64 байта **[расширение]** |
| `app/UI/Telegram/Shared/RecipeCardKeyboard.php` | единственный рендер карточки рецепта |
| `app/UI/Http/{Context}/**` (~15 классов) | HTTP-Actions c route-attributes (+Request по потребности) |
| `app/UI/Telegram/{Context}/**` (~20 классов) | Telegram-Actions/Conversations/Keyboards по срезам |
| `app/Events/Orders/*.php`, `app/Listeners/Orders/*.php` | OrderPlaced/Accepted/Cancelled + queued-уведомления **[расширение]** |
| `app/Handlers/Ingredients/SearchIngredientsHandler.php` | ingredient-поиск, вынесенный из Conversations **[расширение]** |

### Изменяемые файлы

| Файл | Изменение |
|---|---|
| `Makefile` | таргеты `stan`, `handoff` |
| `composer.json` | `larastan/larastan` (dev) |
| `routes/telegram.php` | по срезам: импорты/таргеты на новые классы; паттерны — из `CallbackRoute` |
| `routes/api.php` | по срезам худеет; в финале — минимум (route-attributes) |
| `app/Handlers/Inventory/AddInventoryHandler.php` | `Bar` через DI вместо литерала `bar_id => 1` **[расширение]** |
| `app/Handlers/Orders/PlaceOrder/Accept/CancelHandler` | диспатч доменных событий **[расширение]** |
| `bootstrap/providers.php` / `app/Providers/BusServiceProvider.php` | Bus уходит по срезам, провайдер удаляется в D1 |
| `.agents/knowledge/codebase.md`, `telegram-ui.md` | финальная актуализация (пути, паттерны) |
| `AGENTS.md` | handoff-чеклист: пункты 1–2 заменяются на «`make handoff` → зелёный» |

### Удаляемые (Task D1)

| Файл / группа | Причина |
|---|---|
| `app/UI/Http/Actions/**` (17 классов) | заменены срезами |
| `app/UI/Telegram/Conversations/**`, `app/UI/Telegram/Responses/**` | переехали в срезы |
| `app/UI/Telegram/TelegramController.php`, `app/UI/Http/Controller.php` | мёртвый код (нигде не используются) |
| `app/Providers/BusServiceProvider.php` | Bus упразднён (П-2) |

---

## Порядок исполнения

Все группы **последовательные** — работа в одиночку, каждый срез трогает `routes/telegram.php`
и общие Shared-классы, параллелить нечего и незачем.

```
Фаза A (фундамент):  A1 → A2 → A3     — сетка, без которой рефакторинг слеп
Фаза B (пилот):      B1 → B2 → B3     — Shared-инфраструктура, срез Inventory, срез Recipe
   ⏸ чек-пойнт: паттерн зафиксирован; дальше — механический тираж
Фаза C (тираж):      C1 → C2 → C3 → C4 → C5
Фаза D (финал):      D1 → D2 → D3
```

**Ритм для свободного времени:** одна задача = одна сессия = один коммит. После любой задачи
проект в рабочем состоянии (`make handoff` зелёный), пауза безопасна. Если сессия прервалась
посреди задачи — `git stash`, состояние не коммитить.

---

## Критерии handoff между задачами

С Task A1 хендофф каждой задачи — одна команда: **`make handoff` → зелёный** (tests + stan + pint).
Для задач, меняющих HTTP-роуты, дополнительно: `route:list` идентичен эталону (см. Task A1, Step 3).
План — каркас, не догма: отступления допустимы, но фиксируй их прямо в этом файле рядом с задачей.

---

## Фаза A — Фундамент

### Task A1: Larastan + `make handoff` + эталон роутов

**Depends on:** None

**Files:**
- Create: `phpstan.neon.dist`, `phpstan-baseline.neon`, `docs/route-list.baseline.txt`
- Modify: `composer.json`, `Makefile`, `AGENTS.md`

- [ ] **Step 1: Larastan.** `composer require --dev larastan/larastan` (внутри контейнера).
  `phpstan.neon.dist`: extension larastan, `level: 6`, `paths: [app]`. Сгенерировать baseline
  (`--generate-baseline`) — существующие ошибки замораживаем, новые не пропускаем.
  Ожидание: baseline поймает как минимум несоответствие `GetRecipeResult`/`toTelegramMessage`
  в `BrowseRecipesAction` — **не чини сейчас**, почин — в A2 вместе с тестом (иначе фикс без страховки).
- [ ] **Step 2: Makefile.** Таргет `stan` (phpstan analyse через `docker compose exec`), таргет
  `handoff` = `tests` + `stan` + `pint-dirty-dry`.
- [ ] **Step 3: Эталон HTTP-роутов.** `docker compose exec app php artisan route:list --json`
  → сохранить в `docs/route-list.baseline.txt`. Это acceptance-инструмент всех срезов:
  после каждого изменения `routes/api.php` сравнивать набор URI/метод/middleware с эталоном.
- [ ] **Step 4: AGENTS.md.** В handoff-чеклисте пункты 1–2 заменить на «`make handoff` → зелёный»;
  упомянуть baseline-политику: «уменьшать можно, увеличивать нельзя».
- [ ] **Step 5: Commit** — `git commit -m "BB-12: larastan + make handoff + route baseline"`.

**Handoff:** `make handoff` существует и зелёный (с baseline); эталон роутов сохранён.

**Отступление от плана (зафиксировано по ходу Task A1):** при первом же `composer require` и даже
при `php artisan --version` весь бут приложения падал (`Symfony\Component\Finder\Exception\DirectoryNotFoundException:
The "/var/www/app/Http/Controllers" directory does not exist`) — `spatie/laravel-route-attributes`
не имел опубликованного конфига и сканировал дефолтный путь `app/Http/Controllers`, которого нет
с момента реорганизации UI (`848191f`). Регрессия предшествует этому плану и никак не связана с
Larastan, но блокировала абсолютно всё, включая живые Octane/Nutgram-воркеры в контейнере (они были
в крашлупе). Исправлено вручную (через `artisan vendor:publish` было невозможно — сам бут падал
раньше диспетчеризации команды): создан `config/route-attributes.php` (не было в исходной секции
Files) с `directories => [app_path('UI/Http')]` — рекурсивно покрывает текущий `Actions/` и будущие
срезы Task B2+. Проверено: `php artisan --version` проходит, контейнер `app` перезапущен, все
supervisor-процессы (`nutgram`, `octane`, `worker_00/01`, `scheduler`) в состоянии RUNNING без ошибок
в логах.

---

### Task A2: Смоук-тесты всех Telegram-роутов + фиксы вскрытых багов

**Depends on:** Task A1

**Files:**
- Create: `tests/Feature/Telegram/` — по файлу на контекст (`InventoryTest`, `SearchTest`, `SessionTest`, `OrdersTest`, `FavoritesTest`, `RatingsTest`, `StartTest`)
- Modify: `app/UI/Http/Actions/Search/BrowseRecipesAction.php`, `app/UI/Http/Actions/Orders/PlaceOrderAction.php`

- [ ] **Step 1: Освоить Nutgram testing kit.** Точный API — Context7, библиотека `nutgram/nutgram`,
  раздел Testing (`Nutgram::fake()`, `hearUpdate`/`hearCallbackQueryData`, assertions на исходящие
  вызовы вроде `assertCalled('sendMessage')`). Ориентир формы теста:
  «услышать callback → assert, что бот сделал ожидаемый вызов с ожидаемым текстом/клавиатурой».
- [ ] **Step 2: По смоук-тесту на каждый роут `routes/telegram.php`** (~18 роутов: команды, каждый
  `onCallbackQueryData`, старт каждой Conversation + её happy-path до конца). Глубина — смоук:
  «не падает + отвечает ожидаемым типом вызова», не пиксель-перфект текстов.
- [ ] **Step 3: Починить то, что упало.** Ожидаемые два падения (описаны в «Контексте с нуля»):
  - `BrowseRecipesAction`: работать с `GetRecipeResult` — проверять `$result->recipe === null`,
    рендерить `$result->recipe`; заодно передать `Auth::id()` вторым аргументом (карточка получит
    избранное/оценки — поведенчески это фикс регрессии BB-11, не новая фича).
  - `PlaceOrderAction::confirm`: `Auth::id()` вместо `$bot->userId()`.
  После фиксов удалить соответствующие записи из `phpstan-baseline.neon`.
- [ ] **Step 4: Commit** — `"BB-12: telegram smoke tests + fix browse regression + fix order user_id"`.

**Handoff:** каждый роут `routes/telegram.php` имеет смоук-тест; suite зелёный; baseline уменьшился.

---

### Task A3: CI

**Depends on:** Task A2

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1:** Workflow на `pull_request` + push в `master`: PHP 8.5, service-контейнер
  `postgres:18-alpine`, шаги: composer install → migrate → `artisan test` → `phpstan analyse` →
  `pint --test`. Env для тестов — как в `phpunit.xml`/`.env.testing` (сверить имена БД/юзера).
- [ ] **Step 2:** Прогнать: открыть draft-PR текущей ветки, убедиться что workflow зелёный. Commit.

**Handoff:** CI зелёный на draft-PR. ⏸ Хорошая точка паузы: сетка стоит, рефакторинг ещё не начат.

---

## Фаза B — Пилот (Inventory + Recipe)

### Task B1: Shared-инфраструктура: CallbackRoute **[расширение BB-12]**

**Depends on:** Task A3

**Files:**
- Create: `app/UI/Telegram/Shared/CallbackRoute.php`, `tests/Unit/UI/CallbackRouteTest.php`

- [ ] **Step 1:** Класс-реестр всех callback-маршрутов бота: для каждого маршрута — константа
  паттерна для регистрации в роутах (`recipe:{id}:show`) и фабрика готовой строки для кнопок
  (`CallbackRoute::recipeShow($id)`), с guard-ом: итоговая строка ≤ 64 байт, иначе исключение.
  Наполнение — **по мере срезов**: в B1 внести маршруты Inventory/Recipe, дальше каждый срез
  добавляет свои. Существующие строки-литералы не трогаем до их среза.
- [ ] **Step 2:** Юнит-тест: паттерн↔фабрика согласованы, guard срабатывает. Commit.

**Handoff:** `make handoff` зелёный; реестр покрывает маршруты Inventory + Recipe.

---

### Task B2: Срез Inventory

**Depends on:** Task B1

Пилотный срез — по нему калибруется паттерн для всего тиража. Маппинг (из дизайна §7):

```
# HTTP → app/UI/Http/Inventory/
InventoryAction::__invoke        → ListInventoryAction.php            #[Get('inventory')]
AddInventoryAction::__invoke     → AddInventory/AddInventoryAction.php #[Post('inventory')] + AddInventoryRequest
RemoveInventoryAction::__invoke  → RemoveInventoryAction.php           #[Delete('inventory/{id}')]

# Telegram → app/UI/Telegram/Inventory/
InventoryAction::fromTelegram        → ListInventoryAction.php
InventoryListResponse                → InventoryListKeyboard.php        (context-shared)
AddInventoryConversation             → AddInventory/AddInventoryConversation.php
AddInventoryAction::fromTelegram     → AddInventory/AddInventoryAction.php   # хоп из Conversation сохраняется
RemoveInventoryAction::fromTelegram  → RemoveInventoryAction.php
```

**Files:** Create — классы из маппинга выше; Modify — `routes/telegram.php`, `routes/api.php`,
`app/Handlers/Inventory/AddInventoryHandler.php`, `app/Handlers/Ingredients/SearchIngredientsHandler.php` (create),
`app/Providers/BusServiceProvider.php`, тесты Inventory; Delete — старые 3 Action-класса, `InventoryListResponse`.

- [ ] **Step 1: HTTP-половина.** Route-attributes на новых классах (`#[Middleware]` для auth.telegram
  и CanManage — вложенность сверить с текущими группами `routes/api.php`); убрать Inventory-роуты
  из `routes/api.php`. Проверка: `route:list` совпадает с эталоном из A1.
- [ ] **Step 2: Telegram-половина.** Перенос классов по маппингу; `routes/telegram.php` — новые
  импорты/таргеты, паттерны из `CallbackRoute`. Рендер — только в `InventoryListKeyboard`.
- [ ] **Step 3 [расширение]: Bus → DI.** Новые Actions зовут `AddInventoryHandler`/`RemoveInventoryHandler`
  напрямую через конструктор (как query-handlers); маппинги Inventory удалить из `BusServiceProvider`.
- [ ] **Step 4 [расширение]: два точечных улучшения Handler-слоя** (behavior-preserving):
  - `AddInventoryHandler`: `Bar` в конструктор, `bar_id => $this->bar->id` вместо литерала `1`;
  - ingredient-поиск из `AddInventoryConversation::searchIngredient` → новый
    `SearchIngredientsHandler` (запрос тот же: ilike по name_ru, limit 8); Conversation зовёт его через DI-метод `app(...)`.
- [ ] **Step 5: Тесты.** Существующие HTTP-тесты Inventory — обновить неймспейсы; смоук-тесты A2 —
  должны пройти без правок (поведение идентично; если пришлось править тест — это сигнал, что
  поведение уехало). Commit.

**Handoff:** `make handoff` зелёный; `route:list` = эталон; в `app/UI/Http/Actions/Inventory/` пусто.

---

### Task B3: Срез Recipe (Browse / Show) + единая карточка

**Depends on:** Task B2

Маппинг:

```
# HTTP → app/UI/Http/Recipe/
GetRecipeAction::__invoke        → GetRecipeAction.php                 #[Get('recipes/{id}')]

# Telegram → app/UI/Telegram/Recipe/
GetRecipeAction::fromTelegram    → Show/ShowRecipeAction.php
BrowseRecipesAction::fromTelegram→ Browse/BrowseRecipesAction.php
(рендер карточки из обоих)       → app/UI/Telegram/Shared/RecipeCardKeyboard.php
```

**Files:** Create — по маппингу + `RecipeCardKeyboard`; Modify — `routes/telegram.php`, `routes/api.php`, тесты; Delete — старые классы.

- [ ] **Step 1: `RecipeCardKeyboard` (Shared).** Единственное место, где собирается карточка рецепта:
  текст (`toTelegramMessage` + строка рейтинга) и клавиатура (назад / заказать-если-сессия /
  избранное / оценки). Сейчас карточка собирается в трёх местах с разным набором кнопок
  (Show — с избранным/оценками, Browse — без них, звёзды дублируются в ShowRatingPicker) —
  **сводим к варианту Show как самому полному**; Browse добавляет поверх свой ряд навигации ◀️/▶️.
  Это видимое пользователю выравнивание UX (browse-карточка получит избранное/оценки) —
  зафиксировать в telegram-ui.md на финале.
- [ ] **Step 2:** Перенос Actions по маппингу, роуты, `CallbackRoute` пополняется маршрутами
  browse/show. Смоук-тесты A2 проходят (кроме осознанной правки на новый состав кнопок browse-карточки).
- [ ] **Step 3:** Commit. ⏸ **Чек-пойнт пилота:** структура опробована на всех элементах паттерна
  (route-attributes, Conversation с хопом, context-keyboard, Shared-keyboard). Если что-то в
  правилах промоута/именования оказалось неудобным — поправь правило ЗДЕСЬ, в этом файле,
  до тиража. Дальше — механика.

**Handoff:** `make handoff` зелёный; карточка рецепта рендерится ровно одним классом.

---

## Фаза C — Тираж по контекстам

> Каждая задача повторяет механику B2/B3: перенос по маппингу, роуты через `CallbackRoute`,
> Bus → DI для своих handlers, рендер в Keyboard-классы, смоук-тесты не правятся (кроме
> осознанных и записанных исключений). Ниже — только объём и особенности каждого среза.

### Task C1: Срез Search

**Depends on:** Task B3
**Объём:** `SearchRecipesAction` (HTTP+TG), `SearchByIngredientAction` (TG-only),
3 Conversations (ByName, ByIngredient, Filter), `SearchResultsResponse` → `Telegram/Search/SearchResultsKeyboard`.
**Особенности:**
- `SearchByIngredientConversation` использует ingredient-поиск → перевести на
  `SearchIngredientsHandler` из B2 (запрос там по name_ru+name_en — расширить handler параметром, поведение сохранить).
- Известный дохлый флоу `ing:add:{id}` (кнопки уточнения ингредиента читают `text` вместо
  `callback_data` → поиск по пустой строке) — **починить попутно**, это в мигрируемом коде;
  смоук-тест на этот путь добавить.

- [ ] Перенос + роуты + Bus-чистка + тесты + Commit.

**Handoff:** `make handoff` зелёный.

### Task C2: Срез Session

**Depends on:** Task C1
**Объём:** `SessionAction`, `StartSessionAction` (оба HTTP+TG), рендер экранов сессии → `Telegram/Session/SessionKeyboard`.
**Особенности:** inline-проверку роли в `SessionAction::fromTelegram` (`auth()->user()?->role->canManage()`)
заменить на `Gate::allows('can-manage')` — та же семантика, одна точка правды авторизации.

- [ ] Перенос + роуты + Bus-чистка + тесты + Commit.

**Handoff:** `make handoff` зелёный; `route:list` = эталон.

### Task C3: Срез Orders + доменные события **[расширение BB-12]**

**Depends on:** Task C2
**Объём:** Place / Accept / Cancel / List (все HTTP+TG). Двухшаговый PlaceOrder остаётся
двумя методами одного TG-Action (`fromTelegram` + `confirm`), контракт `recipe:{id}:order:{qty}`
не меняется (моделирование как Conversation — вне плана, см. rework-design).
**Особенности — самая содержательная задача тиража:**
- [ ] **События вместо рассылок в Actions.** `OrderPlaced`, `OrderAccepted`, `OrderCancelled`
  диспатчатся из соответствующих Handlers после успешной мутации. Уведомления
  (менеджерам о новом заказе; гостю о принятии/отклонении) переезжают из приватных методов
  Actions в queued-listeners (`app/Listeners/Orders/`). Требования к listener-ам:
  ловить только Telegram-исключения, каждое падение — `Log::warning` с chat_id
  (не `catch (\Throwable) {}` молча, как сейчас). Очередь уже есть (контейнер `queue`).
  Эффект: HTTP-ветка PlaceOrder перестаёт тянуть Nutgram и ждать N рассылок.
- [ ] `ListOrdersAction::fromTelegram`: `Auth::user()` вместо запрещённого спекой
  `User::where('telegram_id', ...)`.
- [ ] Перенос + роуты + Bus-чистка + смоук-тесты (заказ теперь проходит — C-2 починен в A2;
  тест на «событие диспатчится», тест listener-а) + Commit.

**Handoff:** `make handoff` зелёный; grep `Nutgram` по `app/UI/Http/` → 0 (после этого среза HTTP чист от Telegram).

### Task C4: Срезы Favorites + Ratings (вместе — оба маленькие)

**Depends on:** Task C3
**Объём:** FavoriteToggle, ListFavorites (+Conversation), Rate, ShowRatingPicker.
**Особенности:**
- `ShowRatingPickerAction` больше не собирает звёзды сам — ряд оценок берётся из `RecipeCardKeyboard` (B3).
- Кривой контракт `ListFavoritesAction::fromTelegram(page, edit): FavoritesPage` при переносе
  выправить минимально: TG-Action остаётся рендером страницы, Conversation получает данные
  сама через `ListFavoritesHandler` (это уже допустимо — данные без side-effect), хоп остаётся
  только там, где есть side-effect. Известную проблему «выбор рецепта цифрой зовёт
  callback-ориентированный рендер» — проверить смоук-тестом; если подтверждается, рендерить
  карточку через `sendMessage`-ветку `RecipeCardKeyboard` (не `editMessageText`).

- [ ] Перенос + роуты + тесты + Commit.

**Handoff:** `make handoff` зелёный.

### Task C5: Срез Start

**Depends on:** Task C4
**Объём:** `StartAction` → `app/UI/Telegram/Start/StartCommand.php` (плоско); главное меню → `StartMenuKeyboard`.
**Особенности:** захардкоженное «В базе *203 рецепта*» заменить на `Recipe::count()`
(через существующий query-handler или напрямую в Keyboard — счётчик не бизнес-логика; кэшировать не обязательно).

- [ ] Перенос + роуты + тесты + Commit.

**Handoff:** `make handoff` зелёный; `app/UI/Http/Actions/` пуст.

---

## Фаза D — Финал

### Task D1: Чистка

**Depends on:** Task C5

**Files:** Delete — `app/UI/Http/Actions/**`, `app/UI/Telegram/Conversations/**`,
`app/UI/Telegram/Responses/**`, `app/UI/Telegram/TelegramController.php`, `app/UI/Http/Controller.php`,
`app/Providers/BusServiceProvider.php`; Modify — `bootstrap/providers.php`, `routes/api.php`.

- [ ] Удалить перечисленное (перед удалением — grep по каждому имени класса: ссылок ноль).
- [ ] `routes/api.php` — свести к остаточному минимуму (что не покрылось route-attributes);
  `route:list` = эталон A1 — финальная сверка.
- [ ] `composer dump-autoload`, `make handoff`, Commit.

**Handoff:** зелёный handoff при пустых старых каталогах.

### Task D2: Арх-тесты — новая структура как падающие правила

**Depends on:** Task D1

**Files:** Create — `tests/Arch/LayersTest.php`

- [ ] Pest arch-тесты (минимальный набор, расширять по вкусу):
  - `App\Handlers` не использует `SergiX44\Nutgram`, `Illuminate\Http`;
  - `App\UI\Http` не использует `SergiX44\Nutgram` (гарантия C3 навсегда);
  - `App\UI\Telegram` не использует `Illuminate\Http`;
  - Conversations не используют `App\Models` (после B2/C1 это правда);
  - литерал `bar_id` не встречается в `app/` вне моделей/фабрик (регрессия П-7).
- [ ] Commit.

**Handoff:** арх-тесты в suite, `make handoff` зелёный.

### Task D3: Документация + PR

**Depends on:** Task D2

**Files:** Modify — `.agents/knowledge/codebase.md`, `.agents/knowledge/telegram-ui.md`,
`.agents/specs/conversation-action-architecture.md`

- [ ] **codebase.md** — самая большая правка за всю историю файла: новые пути и структура срезов,
  паттерны «события Orders», «CallbackRoute», «Bus упразднён — все Handlers через DI»,
  статус BB-12 = ✅. Правило впредь: таблицы роутов в codebase.md **не дублировать** —
  ссылаться на `routes/telegram.php` и `route:list` (дубли — источник дрейфа).
- [ ] **telegram-ui.md** — обновить пути классов в примерах; зафиксировать выравнивание
  browse-карточки (B3) и любые осознанные отклонения, накопленные в срезах.
- [ ] **conversation-action-architecture.md** — не рерайт (это rework-design), но добавить
  сверху блок: «Инвариант "один класс на use-case для всех транспортов" снят BB-12,
  актуальные инварианты — §3 дизайна BB-12 + tests/Arch/».
- [ ] **PR:** `gh pr create --title "BB-12: транспорт-split + фундамент качества" --body "..."`
  (Summary: фундамент A1–A3; пилот и тираж срезов; события Orders; чистка; арх-тесты).

**Handoff (финальный):** suite + stan + pint зелёные; `route:list` = эталон; codebase.md актуален; PR открыт.

---

## Pre-PR проверка автора плана

- [x] Goal заполнен.
- [x] Branch указана (`BB-12_architecture_restruct`).
- [x] Карта файлов покрывает все файлы Steps (детализация «старое→новое» — в задачах срезов).
- [x] Порядок исполнения присутствует; параллельных групп нет (работа в одиночку — обоснование в разделе).
- [x] У каждой задачи есть Depends on и Files.
- [x] План — каркас: тел методов нет; зафиксированы только cross-boundary контракты (пути классов, паттерны маршрутов, имена событий).
- [x] Финальная задача содержит «Обновить codebase.md» и «Открыть PR».
- [x] Telegram UI меняется (browse-карточка, B3) → финальная задача содержит «Обновить telegram-ui.md».