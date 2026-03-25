<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset access" />

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Reset access</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                Enter the account email and Evala will send a password reset link.
            </p>
        </div>

        <div v-if="status" class="mt-4 rounded-[8px] border border-[var(--success)]/20 bg-[rgba(46,182,125,0.08)] px-4 py-3 text-sm text-[var(--success)]">
            {{ status }}
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" class="field-input" autocomplete="username" autofocus required>
                <div v-if="form.errors.email" class="field-error">{{ form.errors.email }}</div>
            </div>

            <div class="flex items-center justify-between gap-4">
                <Link :href="route('login')" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]">
                    Back to login
                </Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Sending...' : 'Send reset link' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
