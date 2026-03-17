<template>
    <div class="min-h-screen bg-[var(--canvas)] px-4 py-6 lg:px-6 lg:py-8">
        <div class="mx-auto grid min-h-[calc(100vh-3rem)] max-w-6xl gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="panel hidden p-6 lg:flex lg:flex-col lg:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[var(--accent)] text-sm font-semibold text-[var(--accent-ink)]">
                            PL
                        </span>
                        <div>
                            <div class="text-sm font-semibold text-[var(--ink)]">PromptLab</div>
                            <div class="mt-1 text-sm text-[var(--muted)]">Internal prompt operations</div>
                        </div>
                    </div>

                    <div class="mt-8 space-y-3">
                        <div class="guide-card">
                            <div class="font-semibold text-[var(--ink)]">Workspace modules</div>
                            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                Tasks, prompt templates, experiments, approvals, workspaces, and access control.
                            </p>
                        </div>

                        <div class="guide-card">
                            <div class="font-semibold text-[var(--ink)]">Demo accounts</div>
                            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                                <code>admin@promptlab.local</code> / <code>password</code>
                                <br>
                                <code>team@promptlab.local</code> / <code>password</code>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-sm text-[var(--muted)]">
                    Secure access for the current PromptLab instance.
                </div>
            </aside>

            <div class="flex items-center justify-center">
                <div class="panel w-full max-w-xl p-6 sm:p-8">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
