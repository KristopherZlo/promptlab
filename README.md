# Evala

![Evala header](resources/images/github-page-header.png)

Evala is an internal AI experimentation workspace for teams that need more than a chat box.

It organizes prompt work around a simple product flow:

**Tasks -> Prompts -> Experiments -> Library**

## Screenshots

The gallery below is generated from the actual app UI with Playwright and published to [`docs/screenshots/latest`](./docs/screenshots/latest). It covers guest flows, invitations, registration, unverified email verification, the main workspace, prompt iteration, experiments, library views, admin pages, and account screens in both themes.

<details>
<summary>Dark theme gallery</summary>

### Authentication

![Login](docs/screenshots/latest/dark/login.png)
![Register](docs/screenshots/latest/dark/register.png)
![Forgot password](docs/screenshots/latest/dark/forgot-password.png)
![Reset password](docs/screenshots/latest/dark/reset-password.png)
![Verify email](docs/screenshots/latest/dark/verify-email.png)
![Confirm password](docs/screenshots/latest/dark/confirm-password.png)

### Invitations

![Invitation landing](docs/screenshots/latest/dark/invitation.png)
![Invitation login](docs/screenshots/latest/dark/invitation-login.png)
![Invitation register](docs/screenshots/latest/dark/invitation-register.png)

### Workspace

![Getting started](docs/screenshots/latest/dark/getting-started.png)
![Dashboard](docs/screenshots/latest/dark/dashboard.png)
![Task directory](docs/screenshots/latest/dark/task-directory.png)
![Task detail](docs/screenshots/latest/dark/task-detail.png)

### Prompts

![Prompt library](docs/screenshots/latest/dark/prompt-catalog.png)
![Prompt create](docs/screenshots/latest/dark/prompt-create.png)
![Prompt details](docs/screenshots/latest/dark/prompt-details.png)
![Prompt history](docs/screenshots/latest/dark/prompt-revisions.png)
![Prompt optimize](docs/screenshots/latest/dark/prompt-optimize.png)
![Prompt library](docs/screenshots/latest/dark/prompt-library.png)

### Experiments

![Experiment results](docs/screenshots/latest/dark/experiment-results.png)
![Experiment summary](docs/screenshots/latest/dark/experiment-summary.png)
![Playground](docs/screenshots/latest/dark/playground.png)

### Library

![Library catalog](docs/screenshots/latest/dark/library-catalog.png)
![Library entry](docs/screenshots/latest/dark/library-entry.png)

### Administration

![Users access members](docs/screenshots/latest/dark/admin-members.png)
![Users access invitations](docs/screenshots/latest/dark/admin-invitations.png)
![Users access roles](docs/screenshots/latest/dark/admin-roles.png)
![Workspaces current](docs/screenshots/latest/dark/admin-workspaces.png)
![Workspaces create](docs/screenshots/latest/dark/admin-workspaces-create.png)
![Model connections](docs/screenshots/latest/dark/admin-ai-connections.png)
![Model connections editor](docs/screenshots/latest/dark/admin-ai-connections-editor.png)
![Audit history](docs/screenshots/latest/dark/admin-audit-log.png)

### Account

![Profile](docs/screenshots/latest/dark/profile.png)
![Acknowledgements](docs/screenshots/latest/dark/acknowledgements.png)

</details>

<details>
<summary>Light theme gallery</summary>

### Authentication

![Login](docs/screenshots/latest/light/login.png)
![Register](docs/screenshots/latest/light/register.png)
![Forgot password](docs/screenshots/latest/light/forgot-password.png)
![Reset password](docs/screenshots/latest/light/reset-password.png)
![Verify email](docs/screenshots/latest/light/verify-email.png)
![Confirm password](docs/screenshots/latest/light/confirm-password.png)

### Invitations

![Invitation landing](docs/screenshots/latest/light/invitation.png)
![Invitation login](docs/screenshots/latest/light/invitation-login.png)
![Invitation register](docs/screenshots/latest/light/invitation-register.png)

### Workspace

![Getting started](docs/screenshots/latest/light/getting-started.png)
![Dashboard](docs/screenshots/latest/light/dashboard.png)
![Task directory](docs/screenshots/latest/light/task-directory.png)
![Task detail](docs/screenshots/latest/light/task-detail.png)

### Prompts

![Prompt library](docs/screenshots/latest/light/prompt-catalog.png)
![Prompt create](docs/screenshots/latest/light/prompt-create.png)
![Prompt details](docs/screenshots/latest/light/prompt-details.png)
![Prompt history](docs/screenshots/latest/light/prompt-revisions.png)
![Prompt optimize](docs/screenshots/latest/light/prompt-optimize.png)
![Prompt library](docs/screenshots/latest/light/prompt-library.png)

### Experiments

![Experiment results](docs/screenshots/latest/light/experiment-results.png)
![Experiment summary](docs/screenshots/latest/light/experiment-summary.png)
![Playground](docs/screenshots/latest/light/playground.png)

### Library

![Library catalog](docs/screenshots/latest/light/library-catalog.png)
![Library entry](docs/screenshots/latest/light/library-entry.png)

### Administration

![Users access members](docs/screenshots/latest/light/admin-members.png)
![Users access invitations](docs/screenshots/latest/light/admin-invitations.png)
![Users access roles](docs/screenshots/latest/light/admin-roles.png)
![Workspaces current](docs/screenshots/latest/light/admin-workspaces.png)
![Workspaces create](docs/screenshots/latest/light/admin-workspaces-create.png)
![Model connections](docs/screenshots/latest/light/admin-ai-connections.png)
![Model connections editor](docs/screenshots/latest/light/admin-ai-connections-editor.png)
![Audit history](docs/screenshots/latest/light/admin-audit-log.png)

### Account

![Profile](docs/screenshots/latest/light/profile.png)
![Acknowledgements](docs/screenshots/latest/light/acknowledgements.png)

</details>

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

An operations team defines a task for incoming support tickets, stores representative examples as scenarios, iterates on classification prompts, and compares prompt versions before promoting the most reliable one into the shared library.

### 2. Customer email summarization

A service team creates a prompt workflow that turns long customer threads into short internal summaries, tests tone and structure on real examples, and keeps an experiment trail that shows which prompt version actually improved clarity.

### 3. Business-tone rewriting

A communications or back-office team drafts prompts that rewrite rough internal text into a more consistent business tone, reviews outputs manually, and keeps approved versions reusable across the workspace instead of rewriting the same prompt from scratch.

## Featured Case Study

The strongest seeded example is a customer support email summarization workflow that starts with a weak baseline prompt and ends with a reusable handoff prompt promoted into the internal library.

- full write-up: [`docs/case-study.md`](./docs/case-study.md)
- quality lift in the seeded compare run: average score improved from `3.0` to `4.5`
- business outcome: faster support triage, clearer urgency handling, and more consistent ownership

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

- scenarios with expected output
- prompts
- multiple prompt versions
- experiment history
- evaluations
- best-performing prompt signals

## What It Does

### Prompt workflow

- Create prompts with an initial version in one request
- Maintain prompt history with change summaries and model preferences
- Run quick draft tests without creating a full experiment
- Promote approved prompt versions into a shared prompt library

### Experiment workflow

- `single` experiments for one prompt on one input
- `compare` experiments for multiple prompt versions on the same input
- `batch` experiments across saved scenarios
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
- reuse eligible scenarios as train/validation examples
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

Prompts can evolve through multiple saved versions with explicit metadata, notes, and a preferred model.

### 3. Controlled experimentation

Instead of guessing whether a prompt is better, users can run structured experiments and compare outputs directly.

### 4. Evaluation and approval

Good prompt versions are not just "saved". They are reviewed, scored, and then moved into a safer reuse layer through the library.

### 5. Optimization from data

Prompt optimization is treated as another workflow step, not magic. It starts from a real prompt version and real scenarios, then produces a derived draft that can still be reviewed by a human.

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

### Requirements

- PHP 8.2+ with SQLite support for the default local demo
- Composer
- Node.js 20+ and npm
- optional MySQL / MariaDB if you do not want to use the SQLite demo database
- optional Playwright Chromium if you want to regenerate the README screenshots
- optional Python 3.11+ only if you want to run the local GEPA optimization runtime

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
- `unverified@evala.local` / `password`

The seeded invitation flow is available at `/join/evala-showcase-invite`.

## Testing and Verification

### Run the backend test suite

```bash
php artisan test
```

You can also use:

```bash
composer test
```

### Validate the production frontend build

```bash
npm run build
```

### Regenerate the browser screenshot gallery

```bash
npm run ui:screenshots
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
- signs in with the seeded `showcase@evala.local` and `unverified@evala.local` accounts where needed
- uses the seeded `evala-showcase-invite` token for invitation flows
- resolves the seeded customer-support showcase flow from the app API
- captures guest auth, invitation, verification, workspace, prompt, experiment, library, admin, and account pages
- publishes both light and dark theme galleries
- stores a timestamped run in `interface-screenshots-auto`
- archives the previous `docs/screenshots/latest` bundle into `docs/screenshots/archive` before replacing it
- keeps `docs/screenshots/archive` out of the public repository via `.gitignore`
- republishes the current README gallery into `docs/screenshots/latest/light` and `docs/screenshots/latest/dark`

Useful overrides:

- `SCREENSHOT_BASE_URL`
- `SCREENSHOT_AUTH_EMAIL`
- `SCREENSHOT_AUTH_PASSWORD`
- `SCREENSHOT_UNVERIFIED_EMAIL`
- `SCREENSHOT_UNVERIFIED_PASSWORD`
- `SCREENSHOT_INVITATION_TOKEN`
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
