# PromptLab Plan

## Product Goal

Build a demo-ready internal tool for business teams that experiment with LLM prompts and need a structured way to store prompt versions, compare outputs, evaluate quality, and reuse approved prompt patterns.

## Problem

Without a dedicated internal tool, prompt work usually ends up fragmented:

- prompts live in chats, docs, or notes
- manual experiments are not repeatable
- nobody knows which version performs best
- business stakeholders cannot see measurable results

PromptLab fixes that by giving teams one workflow for experimentation, scoring, and reuse.

## Target Users

- digital teams
- product and innovation teams
- analysts
- developers
- AI prototype owners
- managers who need visible experiment results

## MVP Scope

- 3-4 real business use cases
- prompt template CRUD
- prompt versioning
- single run
- compare run for 2-3 versions
- batch run across saved test cases
- manual evaluation
- basic analytics dashboard
- approved prompt library

## Core Entities

- `UseCase`
- `PromptTemplate`
- `PromptVersion`
- `TestCase`
- `Experiment`
- `ExperimentRun`
- `Evaluation`
- `LibraryEntry`

## Key Screens

### Dashboard

- use case count
- prompt count
- run count
- recent experiments
- top performing prompts
- failed format outputs
- top models by average score

### Use Cases

- business description
- linked prompt templates
- test cases
- best prompt for the use case

### Prompt Templates

- filters by use case, task type, status, author, model
- template metadata
- version list
- version editor
- library promotion

### Playground

- prompt selection
- model selection
- single / compare / batch modes
- variable inputs
- assembled prompt preview

### Experiment Detail

- side-by-side outputs for compare mode
- batch run review
- scoring panel
- runtime metadata

### Library

- approved prompt versions
- recommended model
- best-for usage notes
- reusable internal prompt knowledge base

## Technical Stack

- Laravel 12
- Vue 3 + Inertia
- MariaDB on XAMPP
- Laravel Reverb
- queue jobs for batch processing
- mock provider plus OpenAI-compatible provider layer

## SOLID Implementation Rules

- Controllers orchestrate only HTTP concerns
- FormRequests own validation rules
- Resources own API shaping
- Services own business logic
- Provider contracts decouple model integrations
- Prompt execution, validation, and analytics live in separate services

## Execution Flow

1. User selects a use case and prompt version.
2. System resolves variables and compiles the prompt.
3. Selected model provider returns an output.
4. Structured validation checks output format if needed.
5. Result is stored with latency, tokens, and status.
6. Reviewer scores the output.
7. Analytics aggregates scores and pass rates.
8. Best prompt version can be promoted into the library.

## Demo Use Cases

### Customer Email Summarization

- input: long customer message
- output: summary, urgency, requested action

### Ticket Categorization

- input: support request
- output: structured JSON classification

### Rewrite for Business Tone

- input: rough internal or client-facing text
- output: polished business rewrite

### Meeting Note Summarization

- input: raw notes
- output: decisions, actions, owners, deadlines

## Delivery Phases

### Phase 1

- bootstrap Laravel + Vue app
- configure XAMPP database
- create schema and seed data

### Phase 2

- use case CRUD
- prompt template CRUD
- prompt version editor

### Phase 3

- single and compare execution flow
- experiment storage
- manual evaluation

### Phase 4

- batch runs via queue
- progress updates
- analytics dashboard
- library promotion

### Phase 5

- polish UI
- write README
- add smoke tests
- prepare demo script

## Demo Script

1. Open dashboard and show experiment overview.
2. Open `Customer Email Summarization`.
3. Open several prompt versions.
4. Run compare in playground on one real message.
5. Show different outputs and scoring.
6. Show analytics and pass rates.
7. Promote the strongest version to library.

## MVP Limits

- no advanced RBAC
- no collaborative comments
- no CSV dataset import
- no auto-evaluation
- no production compliance tooling

## Version 2 Ideas

- prompt diff view
- comments on runs
- export to CSV
- shareable experiment links
- schema builder
- prompt tags and search upgrades
- multilingual UI

## Success Criteria

- project is demo-ready in 5-7 minutes
- flows are understandable to both technical and business viewers
- experiments are repeatable
- best prompts are measurable and reusable
- architecture is clean enough to discuss confidently in interview context
