<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
        default: '',
    },
    invitation: {
        type: Object,
        default: null,
    },
    invitationToken: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: props.invitation?.email ?? '',
    password: '',
    remember: false,
    invitation_token: props.invitationToken ?? '',
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
            <h1 class="text-2xl font-semibold tracking-tight">Sign in</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                Access the internal PromptLab workspace on this instance.
            </p>
        </div>

        <div v-if="status" class="mt-4 rounded-[8px] border border-[var(--success)]/20 bg-[rgba(53,94,75,0.08)] px-4 py-3 text-sm text-[var(--success)]">
            {{ status }}
        </div>

        <div v-if="invitation" class="mt-4 rounded-[8px] border border-[var(--line)] bg-[rgba(255,255,255,0.03)] px-4 py-3 text-sm text-[var(--muted)]">
            Invitation for <span class="text-[var(--ink)]">{{ invitation.team?.name || 'workspace' }}</span>.
            Sign in as <span class="text-[var(--ink)]">{{ invitation.email }}</span> to accept the {{ invitation.role }} role.
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
                    :readonly="!!invitation"
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

            <div v-if="invitation" class="text-sm text-[var(--muted)]">
                Need a new account?
                <Link :href="route('register', { invitation: invitationToken })" class="app-inline-link">Create an account for this invite</Link>
            </div>
        </form>
    </GuestLayout>
</template>
