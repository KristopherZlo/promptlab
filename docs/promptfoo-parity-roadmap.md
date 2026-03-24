# Promptfoo Parity Roadmap

This project already overlaps with a meaningful part of `promptfoo`, but it is not at full parity yet. This document keeps the scope explicit and ties the imported `agency-agents` library to the evaluation roadmap.

## Already In Place

- Prompt authoring with version history
- Single, compare, and batch experiment modes
- Manual reviewer scoring and notes
- Structured JSON format validation
- Shared prompt library with approvals
- Team-scoped model connections and audit log

## Added In This Iteration

- `agency-agents` import path into the native Prompt Library via `php artisan library:import-agency-agents`
- Automatic batch checks based on `TestCase.expected_output` and `TestCase.expected_json`
- Experiment result payloads and UI now surface automatic pass/fail signals next to manual review

## Remaining Promptfoo-Style Gaps

### Phase 1

- Reusable assertion types beyond the current built-in text/json expectations
- Per-test-case assertion configuration instead of deriving checks only from `expected_output` / `expected_json`
- Assertion summaries in broader analytics and prompt leaderboards

### Phase 2

- Config-driven eval definitions similar to `promptfooconfig.yaml`
- Dataset import/export for test cases, variables, and expected outputs
- Richer provider matrix and provider-specific runtime options

### Phase 3

- LLM-judge and rubric-based assertions
- Red-team and adversarial safety suites
- CLI and CI-friendly reporting for pull requests and pipelines

## Agency Agents Positioning

The imported `agency-agents` catalog is treated as a first-class source of prompt templates:

- Each folder becomes an `Agency Agents / ...` use-case bucket
- Each agent markdown file becomes a prompt template with a ready `v1`
- Each imported version is approved into the shared library immediately

This keeps the library usable today while the deeper `promptfoo` evaluation surface is implemented in phases.
