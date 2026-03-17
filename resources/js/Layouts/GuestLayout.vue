<template>
    <div class="min-h-screen px-4 py-8 lg:px-8">
        <div class="mx-auto grid min-h-[calc(100vh-4rem)] max-w-5xl items-stretch gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="panel hidden p-10 lg:flex lg:flex-col lg:justify-between">
                <div>
                    <div class="text-lg font-semibold">PromptLab</div>
                    <p class="mt-3 max-w-md text-sm leading-6 text-[var(--muted)]">
                        Internal prompt experimentation and evaluation tool for business teams.
                    </p>
                </div>

                <div class="grid gap-4">
                    <div class="panel-muted p-4">
                        <div class="text-sm font-medium">What this instance includes</div>
                        <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            Seeded tasks, versioned prompts, compare mode, batch runs, manual scoring, analytics, and an approved prompt library.
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
