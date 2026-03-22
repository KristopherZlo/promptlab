# PromptLab

PromptLab is a full-stack internal AI experimentation workspace built to help teams move from ad hoc prompting to structured, measurable, reusable generative AI work.

This is not a chatbot wrapper and not a toy demo. It is a product-oriented system for:

- designing prompt workflows around real business tasks
- comparing prompt versions on realistic inputs
- validating outputs and reviewing quality
- tracking experiments and analytics over time
- approving the strongest prompt versions into a reusable library
- managing workspace-level AI connections, permissions, and audit visibility

Repository name: `PromptFactory`  
Product name in the UI: `PromptLab`

## Why This Project Exists

I built PromptLab as portfolio evidence for roles that combine:

- applied generative AI
- internal tool development
- experimentation and iteration
- analytics and documentation
- cross-functional demos for technical and non-technical stakeholders

The core idea is simple: many teams want to use LLMs, but their prompt work quickly becomes fragmented, hard to measure, and difficult to trust. PromptLab turns that work into a real internal workflow.

## Why It Is Relevant For AI / Digital Internship Roles

This project is intentionally aligned with roles that expect practical AI application, experimentation, and communication, not only coding.

| What employers want to see | Evidence in PromptLab |
|---|---|
| Ability to design generative AI solutions | Prompt experimentation workspace, prompt library, structured prompt versioning, quick tests, compare runs, batch experiments |
| Ability to build internal tools for business needs | Task-first workflow, approval flow, audit visibility, role-based workspace model, team-scoped AI connections |
| Ability to analyze results and document findings | Experiment summaries, evaluation data, analytics dashboard, use-case detail views, structured README and product docs |
| Ability to support demos, workshops, and discussion | Seeded business-facing demo scenarios, clear UI flow, explainable metrics, short demo script |
| Programming fundamentals | Laravel 12, Vue 3, Inertia, queue jobs, realtime updates, Python-backed optimization runtime |
| Understanding of LLMs and prompt engineering | Prompt compilation, provider abstraction, structured output validation, model whitelisting, prompt optimization workflow |

If I were using one sentence in an application, it would be this:

> I built PromptLab as an internal AI experimentation platform where teams can version prompts, run structured experiments, evaluate outputs, optimize prompt drafts, and turn successful prompt work into reusable internal assets.

## Product Overview

In the UI, business workflows are called **Tasks**. In the data model, they are stored as `UseCase`.

PromptLab supports a complete internal prompt workflow:

1. Define a business task.
2. Add test cases that represent realistic inputs.
3. Create prompt templates and iterate through versions.
4. Run quick tests, compare prompt variants, or execute batch experiments.
5. Validate structured outputs and review quality.
6. Analyze results through summaries and metrics.
7. Approve the strongest prompt version into a reusable library.
8. Optionally start prompt optimization from evaluated data.

## What The Product Can Do

### Core workflow

- Create and manage business tasks with goals, descriptions, ownership, and saved examples
- Create prompt templates with an initial version in one request
- Maintain prompt version history with metadata and preferred model selection
- Run draft-level quick tests before saving a full experiment
- Run single, compare, and batch experiments
- Review experiment details with output data, token counts, latency, and validation state
- Store approved prompt versions in a reusable internal library

### Evaluation and analytics

- Manual scoring for clarity, correctness, completeness, tone, and hallucination risk
- Structured output validation for JSON-based tasks
- Automatic checks against expected text fragments and expected JSON subsets
- Overview analytics for top prompts, top models, and recent activity
- Use-case level summaries to identify the strongest prompt for a business task

### Team and governance features

- Multi-workspace / multi-team structure
- Role-based access and team switching
- Workspace-scoped AI connection management
- Audit logging for important actions
- Approval flow before a prompt becomes reusable team knowledge

### AI-specific capabilities

- Provider abstraction behind a clean service boundary
- Support for mock and OpenAI-compatible runtime flows
- Strict workspace model validation and connection ownership checks
- Queue-backed experiment execution with retry classification
- Realtime experiment progress updates with Laravel Reverb
- GEPA-based prompt optimization flow that can generate a derived prompt draft from test-case data

## Portfolio Highlights

The strongest portfolio signal here is not one individual feature. It is the combination of product thinking and engineering discipline:

- This project frames AI as an internal workflow problem, not only as a chat UI problem.
- It models prompt engineering as versioned, testable, reviewable work.
- It includes data, evaluation, approvals, and reuse instead of stopping at "send prompt, get answer".
- It demonstrates attention to architecture, async processing, permissions, and documentation.
- It is seeded and structured so it can be demoed to a technical lead, a product person, or a business stakeholder.

## Example Business Scenarios

The project is intentionally seeded with realistic, demo-friendly use cases:

- Customer Email Summarization
- Ticket Categorization
- Rewrite for Business Tone
- Meeting Note Summarization

These scenarios make the system easier to understand for non-developers and easier to present in interviews or demos.

## Key AI Workflows

### 1. Prompt authoring

- Create a prompt template
- Store the first prompt version immediately
- Keep subsequent prompt changes as explicit revisions
- Attach task type, model preference, notes, schema, and change summaries

### 2. Quick testing

- Test an unsaved or draft prompt directly from the prompt editor
- Preview the compiled prompt
- Validate whether the output format is acceptable before running larger experiments

### 3. Structured experimentation

- `single`: run one prompt version on one input
- `compare`: run multiple versions against the same input
- `batch`: run a saved prompt version across many test cases

This is the part that makes PromptLab meaningfully different from a generic prompt playground.

### 4. Evaluation

- Manual rubric-based review for qualitative judgment
- Automatic checks against expected outputs for faster feedback loops
- Structured JSON validation for tasks that require machine-readable output

### 5. Prompt optimization

PromptLab also includes a prompt optimization workflow:

- select a source prompt version
- reuse eligible test cases as train/validation data
- run a GEPA-backed optimization job
- receive a derived prompt draft with optimization metadata

This is a strong portfolio signal because it shows understanding of experimentation loops beyond manual prompt tweaking.

## Technical Stack

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

### Runtime and AI integration

- mock provider for safe local development
- OpenAI-compatible provider integration
- Python-backed GEPA runtime for prompt optimization

## Architecture

The backend is structured around explicit responsibilities instead of putting all behavior into controllers or models.

| Layer | Responsibility |
|---|---|
| Controllers | HTTP coordination only |
| Form Requests | validation and request rules |
| Resources | stable payload shaping for UI and API |
| Services | business workflows, orchestration, analytics, providers, optimization |
| Jobs | async execution and retry / failure semantics |
| Provider contracts | decoupling model integrations from application logic |

Important services include:

- `app/Services/ExperimentService.php`
- `app/Services/AnalyticsService.php`
- `app/Services/PromptCompiler.php`
- `app/Services/StructuredOutputValidator.php`
- `app/Services/LLMProviderManager.php`
- `app/Services/PromptOptimizationService.php`
- `app/Services/GepaPromptOptimizer.php`

## Engineering Decisions I Would Be Comfortable Discussing In An Interview

- Why prompt work should be modeled as versioned assets instead of chat history
- Why experiment execution belongs in async jobs instead of inline web requests
- Why provider integration should sit behind an abstraction layer
- Why structured output validation matters for business-facing AI workflows
- Why workspace boundaries and model whitelisting matter in multi-team AI tools
- Why analytics payloads should be summary-shaped instead of loading deep relational trees
- Why prompt optimization needs guardrails, datasets, and a reproducible runtime

## Data Model

The main entities are:

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
- `TeamInvitation`
- `LlmConnection`
- `ActivityLog`
- `PromptOptimizationRun`

This separation is intentional. It keeps business tasks, prompt assets, experiments, evaluation, approvals, and governance from collapsing into one vague "AI record".

## Product Signals Beyond CRUD

What makes this a stronger portfolio project than a standard CRUD app:

- async experiment processing
- realtime progress events
- workspace-level permissions
- model and connection validation
- structured JSON output validation
- automatic and manual evaluation
- approval workflow and library promotion
- prompt optimization pipeline
- analytics designed for decision-making instead of raw data dumps

## Documentation And Demo Readiness

This repository is meant to be easy to present.

Supporting material includes:

- a structured README
- product planning notes in [`PLAN.md`](./PLAN.md)
- UX flow documentation in [`docs/user-life-cycle-map.md`](./docs/user-life-cycle-map.md)

That documentation is part of the portfolio value: it shows I can explain systems clearly, not only build them.

## Suggested Demo Flow

This project demos well in 5 to 7 minutes.

1. Open the dashboard and show recent experiment activity.
2. Open a Task and explain the business problem behind it.
3. Move into prompt templates and show version history.
4. Run a quick test or compare experiment.
5. Open the experiment result and explain validation + scoring.
6. Show analytics or best-prompt signals.
7. Open the library and explain how approved prompt knowledge becomes reusable.
8. If relevant, show the prompt optimization workflow and the derived draft version.

## Running Locally

### Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- MySQL / MariaDB
- optional XAMPP workflow for local hosting

### Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

If you want live progress updates for experiments:

```bash
php artisan reverb:start
```

If you want the standard Laravel dev loop:

```bash
composer run dev
```

## Demo Accounts

- `admin@promptlab.local` / `password`
- `team@promptlab.local` / `password`

## Verification

Verified locally with:

```bash
php artisan test
npm run build
```

## What This Project Says About Me As A Candidate

PromptLab shows that I can:

- identify a real business problem around AI adoption
- turn that problem into a product with clear workflows
- build the backend and frontend of the solution
- think about evaluation, reuse, governance, and analytics
- document the system in a way that helps non-technical stakeholders understand it
- build something that is both technically discussable and demo-ready

That is the reason this repository exists.

## Future Directions

Possible next steps:

- richer automatic evaluation heuristics
- prompt diff views between revisions
- CSV dataset import
- comments and collaboration around experiments
- more provider integrations
- exportable experiment history
- stronger approval policies
- multilingual UX improvements

## License

This project is shared as a portfolio and internal-tool showcase built on top of the Laravel ecosystem.
