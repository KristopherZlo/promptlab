# Evala

Evala is an internal AI experimentation workspace for teams that need more than a chat box.

It organizes prompt work around a simple product flow:

**Tasks -> Prompts -> Experiments -> Library**

Instead of keeping prompts in chats, docs, or random notes, Evala gives teams one place to:

- define business-facing AI tasks
- version prompt drafts over time
- run quick tests, compare runs, and batch experiments
- validate structured outputs
- review quality manually and automatically
- promote strong prompt versions into a reusable internal library
- manage workspace-level model connections, permissions, and audit visibility

The result is a portfolio project that behaves much closer to a real internal product than a typical AI demo.

## Real Usage Scenarios

Evala is easiest to understand through concrete internal workflows:

### 1. Support ticket triage

An operations team defines a task for incoming support tickets, stores representative examples as test cases, iterates on classification prompts, and compares prompt versions before promoting the most reliable one into the shared library.

### 2. Customer email summarization

A service team creates a prompt workflow that turns long customer threads into short internal summaries, tests tone and structure on real examples, and keeps an experiment trail that shows which prompt version actually improved clarity.

### 3. Business-tone rewriting

A communications or back-office team drafts prompts that rewrite rough internal text into a more consistent business tone, reviews outputs manually, and keeps approved versions reusable across the workspace instead of rewriting the same prompt from scratch.

## Featured Case Study

The strongest seeded example is a customer support email summarization workflow that starts with a weak baseline prompt and ends with a reusable handoff prompt promoted into the internal library.

- full write-up: [`docs/case-study.md`](./docs/case-study.md)
- quality lift in the seeded compare run: average score improved from `3.0` to `4.5`
- business outcome: faster support triage, clearer urgency handling, and more consistent ownership

## Screenshots

The screenshots below are generated from the actual app UI with Playwright, using the seeded `showcase@evala.local` profile, and published to [`docs/screenshots/latest`](./docs/screenshots/latest).

| Dashboard | Task Directory |
|---|---|
| ![Dashboard preview](docs/screenshots/latest/dashboard.png) | ![Task directory preview](docs/screenshots/latest/task-directory.png) |

| Task Detail | Prompt Revisions |
|---|---|
| ![Task detail preview](docs/screenshots/latest/task-detail.png) | ![Prompt revisions preview](docs/screenshots/latest/prompt-revisions.png) |

| Experiment Compare | Library Catalog |
|---|---|
| ![Experiment compare preview](docs/screenshots/latest/experiment-compare.png) | ![Library catalog preview](docs/screenshots/latest/library-catalog.png) |

| Library Entry | Experiment Playground |
|---|---|
| ![Library entry preview](docs/screenshots/latest/library-entry.png) | ![Playground preview](docs/screenshots/latest/playground.png) |

## Architecture At A Glance

```mermaid
flowchart LR
    UI[Vue 3 + Inertia UI] --> HTTP[Laravel controllers + form requests]
    HTTP --> Services[Workflow services]
    Services --> DB[(Workspace data)]
    Services --> Jobs[Queued experiment jobs]
    Jobs --> Providers[LLM providers]
    Jobs --> Gepa[GEPA optimizer]
    Jobs --> Events[Reverb updates]
    Events --> UI
```

This is a workflow-driven Laravel app rather than a thin wrapper around one model API. A more detailed breakdown lives in [`docs/architecture.md`](./docs/architecture.md).

Key engineering decisions:

- experiments are created first and executed asynchronously after commit
- models are validated against a workspace-specific whitelist on the server
- realtime channels are authorized with workspace membership rules
- experiment retries are limited to transient upstream failures
- evaluation data is stored next to prompt versions and experiment runs
- provider integrations live behind contracts instead of leaking into controllers
- optimization still returns to a human review flow

## One-Command Demo

On a fresh clone, the fastest way to see the project working is:

```bash
php scripts/demo.php
```

What this command does:

- installs Composer dependencies if `vendor/` is missing
- installs frontend dependencies if `node_modules/` is missing
- creates `.env` from `.env.example`
- switches a fresh local install to SQLite demo defaults
- runs migrations and seeds the workspace demo data
- builds production assets
- starts the Laravel server, queue worker, and Reverb websocket server

The local app opens at `http://127.0.0.1:8000`.

Useful variants:

- `php scripts/demo.php --setup-only` to prepare the project without starting services
- `php scripts/demo.php --run-only` to start the services after setup
- `php scripts/demo.php --with-gepa` to also install the local GEPA optimization runtime

## Overview

Evala is built around the idea that AI work inside a company should be:

- structured instead of ad hoc
- testable instead of intuitive-only
- visible instead of hidden in chat history
- reusable instead of repeatedly reinvented

In the UI, business workflows are called **Tasks**. In the backend model, they are stored as `UseCase`.

Each task can have:

- test cases with expected output
- prompt templates
- multiple prompt versions
- experiment history
- evaluations
- best-performing prompt signals

## What It Does

### Prompt workflow

- Create prompt templates with an initial version in one request
- Maintain revision history with change summaries and model preferences
- Run quick draft tests without creating a full experiment
- Promote approved prompt versions into a shared prompt library

### Experiment workflow

- `single` experiments for one prompt on one input
- `compare` experiments for multiple prompt versions on the same input
- `batch` experiments across saved test cases
- queued execution with progress tracking and retry classification
- realtime experiment updates via Laravel Reverb

### Evaluation workflow

- manual review with clarity, correctness, completeness, tone, and hallucination risk
- structured JSON output validation
- automatic checks against expected text fragments and JSON subsets
- analytics summaries for prompts, models, and use cases

### Team workflow

- multi-workspace structure
- team switching and role-based access
- workspace-scoped AI connection management
- audit visibility for important actions

### Optimization workflow

- start a prompt optimization run from a saved prompt version
- reuse eligible test cases as train/validation examples
- run a GEPA-backed optimization job
- create a derived prompt draft from the best candidate

## Why This Project Exists

Most prompt work inside teams breaks down quickly:

- prompts live in chat history
- nobody remembers which version actually worked
- experiments are repeated manually
- outputs are hard to compare
- business stakeholders cannot see what improved and why

Evala turns that into a proper internal workflow. It is meant to feel like the kind of AI tool a digital unit or product team could actually use for experimentation, demos, and internal learning.

## Main Product Flows

### 1. Task-first workflow

Start from a business task, not a model picker. The task defines the context, goal, and test data before prompt iteration begins.

### 2. Prompt versioning

Prompt templates behave like prompt families. Each family can evolve through multiple versions with explicit metadata, notes, and a preferred model.

### 3. Controlled experimentation

Instead of guessing whether a prompt is better, users can run structured experiments and compare outputs directly.

### 4. Evaluation and approval

Good prompt versions are not just "saved". They are reviewed, scored, and then moved into a safer reuse layer through the library.

### 5. Optimization from data

Prompt optimization is treated as another workflow step, not magic. It starts from a real prompt version and real test cases, then produces a derived draft that can still be reviewed by a human.

## Demo Use Cases

The seeded examples are intentionally business-facing:

- Customer Email Summarization
- Ticket Categorization
- Rewrite for Business Tone
- Meeting Note Summarization

These scenarios make the system easier to demo to both technical and non-technical audiences.

## Tech Stack

### Backend

- PHP 8.2+
- Laravel 12
- MariaDB / MySQL
- Laravel Reverb
- queued jobs for experiment processing

### Frontend

- Vue 3
- Inertia.js
- Vite
- Tailwind CSS
- Blade app shell

### AI runtime

- mock provider for local development
- OpenAI-compatible provider integration
- Python-backed GEPA runtime for prompt optimization

## Repository Notes

- Repository folder name: `PromptFactory`
- Product name in the app: `Evala`
- Additional planning notes: [`PLAN.md`](./PLAN.md)
- Architecture notes: [`docs/architecture.md`](./docs/architecture.md)
- UX flow reference: [`docs/user-life-cycle-map.md`](./docs/user-life-cycle-map.md)

## Local Setup

### Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- optional MySQL / MariaDB for a non-demo database

### Quick Demo Bootstrap

```bash
php scripts/demo.php
```

This is the intended portfolio demo path. It uses SQLite by default on a fresh install, seeds the workspace with business-facing examples, builds the frontend, and starts the app.

### Setup Only

```bash
composer setup
```

That command prepares dependencies, `.env`, database schema, seeded data, and built assets without launching the long-running processes.

### Manual Setup

If you want to manage the environment yourself:

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

### Development

Standard local loop:

```bash
composer run dev
```

Realtime experiment updates:

```bash
php artisan reverb:start
```

The demo bootstrap uses built assets. For active frontend work, use `composer run dev` instead of the one-command demo launcher.

## Demo Accounts

- `showcase@evala.local` / `password`
- `admin@promptlab.local` / `password`
- `team@promptlab.local` / `password`

## Verification

Verified locally with:

```bash
php artisan test
npm run build
```

## Automated UI Screenshots

Evala can regenerate its GitHub screenshots directly from the browser UI.

```bash
npm run build
npm run ui:screenshots:install
npm run ui:screenshots
```

Default behavior:

- reads `APP_URL` from `.env` or `.env.example`
- signs in with `showcase@evala.local` / `password`
- resolves the seeded customer-support showcase flow from the app API
- captures `login`, `register`, `dashboard`, `task-directory`, `task-detail`, `prompt-revisions`, `experiment-compare`, `library-catalog`, `library-entry`, and `playground`
- stores a timestamped run in `interface-screenshots-auto`
- archives previously published README screenshots into `docs/screenshots/archive` before replacing them
- republishes the dark-theme showcase images into `docs/screenshots/latest`

Useful overrides:

- `SCREENSHOT_BASE_URL`
- `SCREENSHOT_AUTH_EMAIL`
- `SCREENSHOT_AUTH_PASSWORD`
- `SCREENSHOT_VIEWPORT`
- `SCREENSHOT_OUTPUT_DIR`
- `SCREENSHOT_PUBLISH_DIR`

## Roadmap

Possible next steps:

- richer automatic evaluation heuristics
- prompt diffs between versions
- CSV dataset import
- comments around experiments and approvals
- additional provider integrations
- exportable experiment history
- stronger approval policies

## License

This project is shared as a portfolio and internal-tool showcase built on top of the Laravel ecosystem.
