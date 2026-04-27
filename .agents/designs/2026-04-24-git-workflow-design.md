# Git Workflow Design

**Date:** 2026-04-24
**Status:** Approved

## Context

Репозиторий переведён в публичный. До этого вся работа велась напрямую в `master`. Вводим branch-based workflow: Claude создаёт ветку, делает работу, открывает PR — разработчик ревьюит и мержит вручную на GitHub.

## Branch Naming

| Тип | Формат | Пример |
|-----|--------|--------|
| Задача | `feature/bb{N}_{slug}` | `feature/bb4_bar-session-flow` |
| Подзадача | `feature/bb{N}-s{M}_{slug}` | `feature/bb4-s1_start-conversation` |

`slug` — kebab-case, до 4 слов, без артиклей.

## PR Format

**Заголовок:** `bb{N}: описание` или `bb{N}-s{M}: описание`

```
bb4: bar session flow
bb4-s1: start conversation state
```

**Тело** — только секция `## Summary` с bullet points:

```markdown
## Summary
- Добавлен обработчик начала бар-сессии
- Conversation хранит состояние через Nutgram-сериализацию
- Миграция добавляет таблицу bar_sessions
```

## Workflow

1. Перед началом задачи — создать ветку `feature/bb{N}_{slug}`
2. Работать на ветке; коммиты по прежнему `type(bb-N): description`
3. По завершении — открыть PR через `gh pr create`
4. Мерж выполняет разработчик вручную на GitHub

## Supporting Files

- `.github/pull_request_template.md` — шаблон для PR, создаваемых вручную
- `CLAUDE.md` — секция "Git Workflow" для контекста Claude
