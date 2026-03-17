<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm password" />

        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Confirm password</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                This action needs a password confirmation before you continue.
            </p>
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label" for="password">Password</label>
                <input id="password" v-model="form.password" type="password" class="field-input" autocomplete="current-password" autofocus required>
                <div v-if="form.errors.password" class="field-error">{{ form.errors.password }}</div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Checking...' : 'Confirm password' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
