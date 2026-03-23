<script setup>
import { Head } from '@inertiajs/vue3';
import { BookCopy, ExternalLink, HeartHandshake } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';

defineProps({
    sources: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Acknowledgements" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1>Acknowledgements</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                    Attribution stays here, so imported prompts can read like working library entries instead of source dumps.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="panel p-5">
                <PanelHeader
                    title="Open source credits"
                    description="This workspace includes imported prompt material adapted from external repositories."
                    help="Credits live on a separate page so prompt cards, library notes, and prompt details stay focused on actual usage."
                />

                <div class="mt-4 grid gap-4">
                    <article
                        v-for="source in sources"
                        :key="source.id"
                        class="quick-link-card"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3 text-[var(--ink)]">
                                    <BookCopy class="h-4 w-4 shrink-0 text-[var(--accent)]" />
                                    <div class="font-semibold">{{ source.name }}</div>
                                </div>
                                <div class="mt-2 text-sm text-[var(--muted)]">
                                    by {{ source.author }}
                                </div>
                                <p class="mt-3 max-w-3xl text-sm leading-6 text-[var(--muted)]">
                                    {{ source.summary }}
                                </p>
                                <div class="mt-4 flex items-start gap-3 rounded-[10px] border border-[var(--line)] bg-[rgba(255,255,255,0.02)] p-4">
                                    <HeartHandshake class="mt-0.5 h-4 w-4 shrink-0 text-[var(--accent)]" />
                                    <p class="text-sm leading-6 text-[var(--muted)]">
                                        {{ source.thanks }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0">
                                <a
                                    :href="source.repository_url"
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    class="btn-secondary"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                    <span>Open repository</span>
                                </a>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
