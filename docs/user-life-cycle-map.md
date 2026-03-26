# Evala User Life Cycle Map

This map is the UX reference for navigation, labels, and CTA cleanup.

## Core Rules

- Daily work follows `Tasks -> Prompts -> Experiments -> Library`.
- `How to Start` is the first-stop page for an empty workspace and for first-time entry after registration or invite acceptance.
- `Tasks` is the daily home for an active workspace.
- Administrative pages support the workflow but do not compete with it.
- Each page should have one obvious primary action.

## Screen Map

### How to Start

- Why people come here: first entry, empty workspace, or when they need a clear next step.
- Frequency: occasional.
- Primary action:
  - empty workspace: `Create first task`
  - task only: `Add first prompt`
  - prompt ready: `Run first experiment`
  - runs exist: `View latest result` or `Open last task`
- Secondary actions: at most two context links to the last task, prompt, result, or library entry.
- Next step: move into the core workflow.

### Tasks List

- Why people come here: find the business task and decide what to work on next.
- Frequency: highest.
- Primary action: `Open task`.
- Secondary actions: `Add task`, `Add prompt`, `Test this task`.
- Next step: task detail.

### Task Detail

- Why people come here: review scope, scenarios, prompts, and recent runs for one task.
- Frequency: highest.
- Primary action: depends on state, but should usually point to the next unfinished step.
- Secondary actions: edit the task, open prompts, run tests on saved cases.
- Next step: prompt work or experiment work.

### Prompts List

- Why people come here: find the right saved prompt for a task.
- Frequency: highest.
- Primary action: `Open prompt`.
- Secondary actions: `Add prompt`, `Test this prompt`.
- Next step: prompt editor or experiments.

### Prompt Editor

- Why people come here: change wording, input fields, saved versions, and library handoff state.
- Frequency: high.
- Primary action: `Save changes` or `Run experiments`.
- Secondary actions: create version, open task, open library handoff.
- Next step: experiments or library.

### Experiments Wizard

- Why people come here: run a single test, compare versions, or run a batch.
- Frequency: high.
- Primary action: `Run experiment`.
- Secondary actions: go back to task or prompt context.
- Next step: experiment result.

### Experiment Result

- Why people come here: review output, compare candidates, and decide what to keep.
- Frequency: high.
- Primary action: `View result` or `Add to library` when appropriate.
- Secondary actions: open source task, open source prompt.
- Next step: prompt changes or library reuse.

### Library List

- Why people come here: reuse an already approved prompt.
- Frequency: medium.
- Primary action: `Open saved prompt` at the entry level.
- Secondary actions: `Run experiments`, `Open source prompt`.
- Next step: experiments or source prompt.

### Library Entry

- Why people come here: inspect one approved prompt in detail.
- Frequency: medium.
- Primary action: `Run experiments`.
- Secondary actions: `View prompt version`, `Open library handoff`.
- Next step: experiments or prompt editor.

### Dashboard

- Why people come here: get an overview and see attention items.
- Frequency: medium.
- Primary action: `Open tasks`.
- Secondary actions: direct links to results, problem cases, and prompt versions.
- Next step: back into the core workflow.

### Users & Access

- Why people come here: add members and manage roles.
- Frequency: low.
- Primary action: `Add member`.
- Secondary actions: `Edit access`, `Revoke invitation`.
- Next step: return to workflow screens.

### Model Connections

- Why people come here: configure model providers for the workspace.
- Frequency: low.
- Primary action: `Add connection`.
- Secondary actions: `Test connection`, `Edit connection`.
- Next step: return to workflow screens.

### Workspace Settings

- Why people come here: create a workspace or inspect the current one.
- Frequency: low.
- Primary action: `Add workspace`.
- Secondary actions: none that switch context.
- Next step: switching happens only in the sidebar.

## Label Rules

- Prefer result-based labels:
  - `Open task`
  - `Add prompt`
  - `Run experiment`
  - `View result`
  - `Add to library`
- Avoid abstract labels:
  - `Open`
  - `Manage`
  - `Review`
  - `Run`
- If a label changes global context, it must clearly say so.

## Navigation Rules

- Sidebar holds global destinations only.
- Page tabs hold local context only.
- Admin/supporting pages stay in a separate section from production work.
- The sidebar order should keep frequent screens together:
  - `Tasks`
  - `Prompts`
  - `Experiments`
  - `Library`
  - `Dashboard`
