# PromptLab

Internal prompt experimentation, evaluation, and approval workspace for business teams working with LLMs.

PromptLab is not "another chatbot". It is a team tool for structured AI experimentation: define a business task, create prompt revisions, test them on realistic examples, compare outputs, score quality, approve the strongest version, and keep reusable prompt knowledge in one place.

This project was built to look and behave like a real internal product, not a toy portfolio app.

## Portfolio Summary

**PromptLab** demonstrates:

- full-stack Laravel + Vue product development
- LLM workflow design beyond a single chat screen
- internal tool thinking for teams, not just end users
- measurable prompt iteration with revision history
- business-oriented UX, approvals, ownership, and audit visibility
- SOLID-oriented backend structure with clean separation of concerns

If you need one sentence for an application or interview:

> I built PromptLab as an internal experimentation tool for business teams using LLMs. It helps teams test prompt revisions, compare outputs, evaluate quality, approve reusable versions, and keep AI experimentation measurable instead of ad hoc.

## The Problem

Most team prompt work becomes chaotic very quickly:

- prompts live in chats, notes, or docs
- versions are not tracked properly
- experiments are repeated manually
- nobody knows which version is actually better
- outputs are hard to compare and hard to explain to stakeholders
- successful prompt patterns get lost instead of reused

That makes AI adoption feel random, fragile, and hard to trust.

## The Product

PromptLab turns prompt work into a repeatable internal workflow:

1. Define a business task.
2. Create or refine prompt revisions.
3. Run single, compare, or batch experiments.
4. Evaluate outputs manually.
5. Approve the best revision.
6. Reuse it through the approved prompt library.

In the UI, business workflows are called **Tasks**.  
In the backend data model, they are stored as `UseCase`.

## What Users Can Do

- Create business tasks with goals, descriptions, and saved test cases
- Create prompt templates and revision history for each task
- Run one prompt on one example
- Compare multiple prompt revisions side by side
- Run batch tests across saved task cases
- Score outputs with manual evaluation criteria
- Approve a revision for team reuse
- Search the approved prompt library
- Manage team members, roles, API connections, and audit history
- See who created or updated tasks and when

## Core UX Flows

### 1. Task-first workflow

The intended order is:

- open a Task
- inspect the business goal and saved examples
- move to Prompt Templates
- edit or create a revision
- run the revision in Playground
- review and approve only what is ready

### 2. Prompt revision workflow

Prompt Templates behave like a lightweight git-style revision history:

- one template = one prompt family
- many revisions = controlled prompt changes over time
- newest revisions are visible first
- each revision carries run counts, score signals, and approval state

### 3. Approval workflow

Approval is explicit. A revision is not considered team-ready just because it exists.

The team can:

- select a revision
- review its scores and run history
- fill approval metadata
- approve it for the shared library

Approved revisions then appear in the **Approved Prompt Library** as the safe reuse layer.

### 4. Safe deletion flow

Destructive actions do not delete immediately where undo matters.

For example:

- removing a team member
- deleting an AI connection
- deleting an account

These actions first enter a short undo state instead of disappearing instantly.

## Demo-Ready Use Cases

The project is seeded with realistic examples:

- **Customer Email Summarization**
- **Ticket Categorization**
- **Rewrite for Business Tone**
- **Meeting Note Summarization**

These make the demo feel business-facing instead of abstract.

## Key Features

### Workspace and team features

- team switching
- team roles and permissions
- audit trail for key actions
- ownership and timestamps on tasks
- OpenAI-compatible API connection management

### Prompt workflow features

- task management
- prompt template CRUD
- revision history
- revision metadata and scores
- approval and library handoff

### Experiment workflow features

- single run
- compare mode
- batch mode
- variable inputs
- prompt preview before execution
- structured evaluation

### Quality and visibility features

- manual scoring
- structured output validation
- top prompt metrics
- recent experiments
- approved prompt library search

## Tech Stack

### Backend

- Laravel 12
- PHP 8.2+
- MariaDB / MySQL
- Laravel Reverb
- queue-backed batch processing

### Frontend

- Vue 3
- Inertia.js
- Blade app shell
- Lucide icons

### Runtime

- XAMPP-friendly local setup
- mock provider for development and demo safety
- OpenAI-compatible provider support for real model calls

## Architecture

The backend is intentionally structured around clear responsibilities.

### SOLID-oriented layers

| Layer | Responsibility |
|---|---|
| Controllers | HTTP flow and coordination only |
| Form Requests | Validation and request rules |
| Resources | Stable response shaping for UI/API |
| Services | Business logic, orchestration, analytics, provider handling |
| Provider contracts | Decouple model integrations from application logic |

### Important service classes

- `app/Services/ExperimentService.php`
- `app/Services/AnalyticsService.php`
- `app/Services/PromptCompiler.php`
- `app/Services/StructuredOutputValidator.php`
- `app/Services/LLMProviderManager.php`
- `app/Services/ModelProviders/Contracts/LLMProvider.php`

### Example architectural decisions

- prompt compilation is separated from HTTP controllers
- model execution is abstracted behind a provider interface
- validation logic for structured outputs is isolated
- analytics calculations do not live inside controllers or Vue pages
- UI gets shaped data through resources instead of raw models

## Data Model

Main entities:

- `UseCase`
- `PromptTemplate`
- `PromptVersion`
- `TestCase`
- `Experiment`
- `ExperimentRun`
- `Evaluation`
- `LibraryEntry`
- `Team`
- `TeamMembership`
- `LlmConnection`
- `ActivityLog`

This separation keeps task definitions, prompt revisions, experiments, scoring, approvals, and team governance from collapsing into one giant model.

## Prompt Execution Flow

1. User selects a task and one or more prompt revisions.
2. Variables are resolved into a compiled prompt.
3. The selected provider executes the request.
4. The result is stored with latency, token counts, status, and metadata.
5. If the task expects structured JSON, format validation is applied.
6. Reviewers score the output manually.
7. Analytics and approval state update from stored run history.

## Evaluation Model

Manual review supports:

- clarity
- correctness
- completeness
- tone
- manual format validity
- hallucination risk
- free-text notes

Structured validation supports:

- valid / invalid JSON detection
- required field checks
- primitive type checks through a lightweight schema object

## Running Locally on XAMPP

This project is designed to run well inside an XAMPP workflow.

### Expected local URL

- `http://localhost/PromptFactory/public`

### Prerequisites

- XAMPP Apache
- XAMPP MySQL or MariaDB
- PHP available in PATH
- Composer available in PATH
- Node.js and npm available in PATH

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Configure environment

Copy `.env.example` to `.env` if needed and set your database values.

Important local values typically look like:

```env
APP_URL=http://localhost/PromptFactory/public
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=promptlab
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Create database and seed demo data

```bash
php artisan key:generate
php artisan migrate --seed
```

### 4. Build frontend assets

For normal XAMPP use:

```bash
npm run build
```

Optional dev mode:

```bash
npm run dev
```

### 5. Start Reverb for live experiment updates

```bash
php artisan reverb:start
```

### 6. Start XAMPP services

- start **Apache**
- start **MySQL**

Then open:

- `http://localhost/PromptFactory/public`

## Demo Accounts

- `admin@promptlab.local` / `password`
- `team@promptlab.local` / `password`

## Real AI Connection Setup

PromptLab supports an OpenAI-compatible provider flow from the UI.

### How to connect a real model

1. Open **Team Workspace**
2. Go to **AI connections**
3. Create a connection
4. Enter:
   - connection name
   - base URL
   - API key
   - one or more model names
5. Mark the connection active
6. Optionally mark it as the team default

Once saved, those models become available in:

- **Prompt Templates**
- **Playground**

For local development, the project also includes a mock provider so the UI works even without a paid API.

## Suggested Demo Script

This product demos well in 5-7 minutes.

### Fast demo flow

1. Open **Dashboard** and show recent experiment activity.
2. Open **Tasks** and select `Customer Email Summarization`.
3. Show task ownership and linked prompt work.
4. Open **Prompt Templates** and show revision history.
5. Open the **Approval** tab to show what approved work means.
6. Go to **Playground**.
7. Walk through the steps: Setup -> Versions -> Input -> Review.
8. Run one compare experiment.
9. Open the result and show scoring.
10. Open **Approved Prompt Library** and explain reuse.

### What to say during the demo

- "This is an internal workflow tool, not just a chat interface."
- "The goal is to make prompt work measurable and reusable."
- "Prompt revisions are versioned and reviewed before approval."
- "Teams can compare outputs instead of guessing."
- "Approved prompts become reusable internal knowledge."

## Why This Is a Strong Portfolio Project

This project is strong for an internship or junior product engineering application because it shows more than CRUD.

It shows that you can:

- frame a real business problem
- design internal tooling, not only consumer UI
- structure AI experimentation into a repeatable workflow
- connect frontend UX decisions to backend architecture
- discuss tradeoffs honestly
- present a system clearly to both technical and non-technical reviewers

## Limitations

This is a strong MVP, not a finished enterprise platform.

Current limits:

- manual evaluation is still subjective
- output quality depends on the chosen model
- prompt performance may shift with different input distributions
- the tool organizes experiments but does not guarantee correctness
- compliance and security hardening are not production-complete
- advanced collaboration features are intentionally out of scope

## Future Improvements

- prompt diff view between revisions
- automatic prompt improvement suggestions based on failed runs, low scores, and format violations
- comments on runs and approvals
- CSV dataset import
- automatic evaluation heuristics
- richer approval policies
- export for experiment history and evaluations
- deeper search and tagging
- more provider integrations
- multilingual UI

## Verification

Verified locally with:

```bash
php artisan migrate --force
php artisan test
npm run build
```

## License

This project is provided as a portfolio/internal-tool showcase built on top of the Laravel ecosystem.
