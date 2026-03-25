# Case Study: Customer Support Email Summarization

This seeded workflow is the clearest example of what Evala is built for: turning prompt iteration into something a team can review, measure, and reuse.

## The Business Problem

Support teams often receive long customer emails that combine multiple issues:

- billing confusion
- login or access failures
- an urgent deadline
- an implied expectation about ownership and next steps

A weak summary hides that operational context. The result is slower triage, unclear ownership, and inconsistent handoffs between support and operations.

The task in Evala is to transform those emails into short internal notes that are immediately useful for the person who has to act on them.

## Baseline Prompt

The first version is intentionally simple.

```text
System prompt
You summarize incoming support emails for an internal team.

User prompt
Summarize the following customer message in one short paragraph: {{input_text}}
```

Why it is not enough:

- it produces readable text, but usually collapses urgency and requested action together
- it gives the reviewer no strong structure for comparing outputs
- it is easy for the model to sound plausible while missing the operational handoff details

## Improved Prompt

The third version in the seeded demo is much more operational.

```text
System prompt
You summarize customer support emails into operational handoff notes.

User prompt
Read the message and produce:
Core issue
Urgency
Requested action
Short summary

{{input_text}}
```

What changed:

- the prompt now asks for a handoff format instead of a generic summary
- urgency becomes explicit instead of being buried in prose
- ownership becomes easier because the requested action is a first-class field
- reviewers can compare outputs on structure, completeness, and usefulness

## Example Input

```text
Hi team, our account has been charged twice this week and we still cannot
access the portal after resetting the password. We have a customer demo
tomorrow morning, so please confirm what you need from us and when this
will be fixed.
```

## Measured Quality Lift

The seeded compare experiment evaluates three prompt versions on the same message.

| Version | Clarity | Correctness | Completeness | Tone | Average |
|---|---:|---:|---:|---:|---:|
| v1 baseline | 3 | 3 | 3 | 3 | 3.0 |
| v2 structured bullets | 4 | 4 | 4 | 4 | 4.0 |
| v3 handoff format | 5 | 4 | 5 | 4 | 4.5 |

Why the winning version scored higher:

- the core issue is clearer
- urgency is surfaced immediately
- actionability is better for the internal team
- the format is easier to scan during triage

The seeded library promotion then approves `v3` as the preferred version for urgent support email handling.

## Why This Matters for the Business

This is a small workflow, but it demonstrates the real value of the product:

- teams can move from "this prompt feels better" to observable evaluation results
- improvements are preserved as prompt versions instead of disappearing into chat history
- the best version can be promoted into a shared library for consistent reuse
- the experiment trail makes the reasoning visible for demos, workshops, and stakeholder reviews

In other words, Evala does not just generate text. It creates a workflow for improving operational AI prompts with traceable evidence.
