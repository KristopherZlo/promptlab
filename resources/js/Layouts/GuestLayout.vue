<template>
    <div class="min-h-screen bg-[var(--canvas)] px-4 py-8 lg:px-8">
        <div class="mx-auto grid min-h-[calc(100vh-4rem)] max-w-5xl items-stretch gap-6 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="panel hidden p-10 lg:flex lg:flex-col lg:justify-between">
                <div>
                    <div class="text-lg font-semibold">PromptLab Workspace</div>
                    <p class="mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">
                        Internal workspace for AI tasks, prompt revisions, experiments, and approvals.
                    </p>
                </div>

                <div class="grid gap-4">
                    <div class="panel-muted p-4">
                        <div class="text-sm font-medium">Operational modules</div>
                        <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            Tasks, prompt templates, experiments, approved library, workspace roles, and audit visibility.
                        </p>
                    </div>
                    <div class="panel-muted p-4">
                        <div class="text-sm font-medium">Demo accounts</div>
                        <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            <code>admin@promptlab.local</code> / <code>password</code>
                            <br>
                            <code>team@promptlab.local</code> / <code>password</code>
                        </p>
                    </div>
                </div>
            </div>

            <div class="panel mx-auto flex w-full max-w-xl flex-col justify-center p-6 sm:p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
