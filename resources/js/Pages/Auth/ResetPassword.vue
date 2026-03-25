<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Set new password" />

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Set new password</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                Choose a new password for your Evala account.
            </p>
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" class="field-input" autocomplete="username" required autofocus>
                <div v-if="form.errors.email" class="field-error">{{ form.errors.email }}</div>
            </div>

            <div>
                <label class="field-label" for="password">New password</label>
                <input id="password" v-model="form.password" type="password" class="field-input" autocomplete="new-password" required>
                <div v-if="form.errors.password" class="field-error">{{ form.errors.password }}</div>
            </div>

            <div>
                <label class="field-label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="field-input" autocomplete="new-password" required>
                <div v-if="form.errors.password_confirmation" class="field-error">{{ form.errors.password_confirmation }}</div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save new password' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
