<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Verify email" />

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Verify email</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                Before entering the workspace, confirm the email address linked to this account.
            </p>
        </div>

        <div v-if="verificationLinkSent" class="mt-4 rounded-[8px] border border-[var(--success)]/20 bg-[rgba(46,182,125,0.08)] px-4 py-3 text-sm text-[var(--success)]">
            A new verification email has been sent.
        </div>

        <form class="mt-6 flex items-center justify-between gap-4" @submit.prevent="submit">
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Sending...' : 'Resend verification email' }}
            </button>

            <Link :href="route('logout')" method="post" as="button" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]">
                Log out
            </Link>
        </form>
    </GuestLayout>
</template>
