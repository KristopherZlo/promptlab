<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create account" />

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Create account</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                Create a PromptLab workspace account for this environment.
            </p>
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="field-label" for="first_name">First name</label>
                    <input id="first_name" v-model="form.first_name" type="text" class="field-input" autocomplete="given-name" autofocus required>
                    <div v-if="form.errors.first_name" class="field-error">{{ form.errors.first_name }}</div>
                </div>

                <div>
                    <label class="field-label" for="last_name">Last name</label>
                    <input id="last_name" v-model="form.last_name" type="text" class="field-input" autocomplete="family-name" required>
                    <div v-if="form.errors.last_name" class="field-error">{{ form.errors.last_name }}</div>
                </div>
            </div>

            <div>
                <label class="field-label" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" class="field-input" autocomplete="username" required>
                <div v-if="form.errors.email" class="field-error">{{ form.errors.email }}</div>
            </div>

            <div>
                <label class="field-label" for="password">Password</label>
                <input id="password" v-model="form.password" type="password" class="field-input" autocomplete="new-password" required>
                <div v-if="form.errors.password" class="field-error">{{ form.errors.password }}</div>
            </div>

            <div>
                <label class="field-label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="field-input" autocomplete="new-password" required>
                <div v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</div>
            </div>

            <div class="flex items-center justify-between gap-4">
                <Link :href="route('login')" class="text-sm text-[var(--muted)] hover:text-[var(--ink)]">
                    Already have an account?
                </Link>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating...' : 'Create account' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
