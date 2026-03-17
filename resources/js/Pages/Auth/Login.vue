<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Log in</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                Access PromptLab on this XAMPP instance to run compare tests, review outputs, and manage approved prompts.
            </p>
        </div>

        <div v-if="status" class="mt-4 rounded-[8px] border border-[var(--success)]/20 bg-[rgba(53,94,75,0.08)] px-4 py-3 text-sm text-[var(--success)]">
            {{ status }}
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="field-input"
                    autocomplete="username"
                    autofocus
                    required
                >
                <div v-if="form.errors.email" class="field-error">{{ form.errors.email }}</div>
            </div>

            <div>
                <label class="field-label" for="password">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="field-input"
                    autocomplete="current-password"
                    required
                >
                <div v-if="form.errors.password" class="field-error">{{ form.errors.password }}</div>
            </div>

            <label class="check-row">
                <input v-model="form.remember" type="checkbox">
                <div>
                    <div class="text-sm font-medium">Remember me</div>
                    <div class="mt-1 text-xs text-[var(--muted)]">Keep the current browser session signed in.</div>
                </div>
            </label>

            <div class="flex items-center justify-between gap-4">
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]">
                    Forgot your password?
                </Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Logging in...' : 'Log in' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
