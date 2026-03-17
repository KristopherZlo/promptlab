<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UndoBanner from '@/Components/UndoBanner.vue';
import { useUndoableAction } from '@/lib/useUndoableAction';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useUrlState } from '@/lib/urlState';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
        default: '',
    },
});

const user = usePage().props.auth.user;

const profileForm = useForm({
    first_name: user.first_name || '',
    last_name: user.last_name || '',
    email: user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({
    password: '',
});

const accountDeletion = useUndoableAction();
const activeTab = useUrlState({
    key: 'tab',
    defaultValue: 'profile',
    allowedValues: ['profile', 'password', 'danger'],
});

const saveProfile = () => {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
    });
};

const updatePassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const scheduleAccountDeletion = () => {
    if (!deleteForm.password) {
        deleteForm.setError('password', 'Confirm with your password before scheduling account deletion.');
        return;
    }

    deleteForm.clearErrors();
    accountDeletion.scheduleAction({
        label: 'This account will be permanently deleted and you will be signed out.',
        onCommit: () => new Promise((resolve, reject) => {
            deleteForm.delete(route('profile.destroy'), {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () => reject(new Error('Account deletion failed.')),
            });
        }),
    });
};
</script>

<template>
    <Head title="Account" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Account</h1>
                <p class="mt-1 text-sm text-[var(--muted)]">
                    Manage your PromptLab identity, password, and account access from one page.
                </p>
            </div>
        </template>

        <div class="page-frame">
            <aside class="page-frame-rail">
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'profile' }"
                    @click="activeTab = 'profile'"
                >
                    <span>Profile</span>
                </button>
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'password' }"
                    @click="activeTab = 'password'"
                >
                    <span>Password</span>
                </button>
                <button
                    type="button"
                    class="page-frame-tab"
                    :class="{ 'page-frame-tab-active': activeTab === 'danger' }"
                    @click="activeTab = 'danger'"
                >
                    <span>Danger zone</span>
                </button>
            </aside>

            <div class="page-frame-content">
                <section class="panel p-5">
                    <h2 class="section-title">Account summary</h2>
                    <div class="summary-list mt-4">
                        <div class="summary-row">
                            <span>Name</span>
                            <span>{{ user.display_name || user.name }}</span>
                        </div>
                        <div class="summary-row">
                            <span>First name</span>
                            <span>{{ user.first_name || 'Not set' }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Last name</span>
                            <span>{{ user.last_name || 'Not set' }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Email</span>
                            <span>{{ user.email }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Role</span>
                            <span class="capitalize">{{ user.role }}</span>
                        </div>
                    </div>
                </section>

                <div v-if="activeTab === 'profile'" class="panel p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="section-title">Profile details</h2>
                            <p class="mt-2 text-sm text-[var(--muted)]">Update the name and email used inside the workspace.</p>
                        </div>
                        <button type="button" class="btn-primary" :disabled="profileForm.processing" @click="saveProfile">
                            {{ profileForm.processing ? 'Saving...' : 'Save details' }}
                        </button>
                    </div>

                    <div v-if="profileForm.recentlySuccessful || status === 'profile-updated'" class="mt-4 rounded-[8px] border border-[var(--success)]/20 bg-[rgba(46,182,125,0.08)] px-4 py-3 text-sm text-[var(--success)]">
                        Profile updated.
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="field-label" for="first_name">First name</label>
                            <input id="first_name" v-model="profileForm.first_name" type="text" class="field-input" autocomplete="given-name">
                            <div v-if="profileForm.errors.first_name" class="field-error">{{ profileForm.errors.first_name }}</div>
                        </div>

                        <div>
                            <label class="field-label" for="last_name">Last name</label>
                            <input id="last_name" v-model="profileForm.last_name" type="text" class="field-input" autocomplete="family-name">
                            <div v-if="profileForm.errors.last_name" class="field-error">{{ profileForm.errors.last_name }}</div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="field-label" for="email">Email</label>
                            <input id="email" v-model="profileForm.email" type="email" class="field-input" autocomplete="username">
                            <div v-if="profileForm.errors.email" class="field-error">{{ profileForm.errors.email }}</div>
                        </div>
                    </div>

                    <div v-if="mustVerifyEmail && user.email_verified_at === null" class="mt-4 guide-card">
                        <div class="font-bold">Email verification pending</div>
                        <div class="mt-2 text-sm leading-6 text-[var(--muted)]">
                            This email is not verified yet.
                            <Link :href="route('verification.send')" method="post" as="button" class="font-bold text-[var(--accent)] hover:underline">
                                Send a new verification email
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-else-if="activeTab === 'password'" class="panel p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="section-title">Password</h2>
                            <p class="mt-2 text-sm text-[var(--muted)]">Change the password for this account.</p>
                        </div>
                        <button type="button" class="btn-secondary" :disabled="passwordForm.processing" @click="updatePassword">
                            {{ passwordForm.processing ? 'Saving...' : 'Update password' }}
                        </button>
                    </div>

                    <div v-if="passwordForm.recentlySuccessful" class="mt-4 rounded-[8px] border border-[var(--success)]/20 bg-[rgba(46,182,125,0.08)] px-4 py-3 text-sm text-[var(--success)]">
                        Password updated.
                    </div>

                    <div class="mt-5 grid gap-4">
                        <div>
                            <label class="field-label" for="current_password">Current password</label>
                            <input id="current_password" v-model="passwordForm.current_password" type="password" class="field-input" autocomplete="current-password">
                            <div v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="field-label" for="password">New password</label>
                                <input id="password" v-model="passwordForm.password" type="password" class="field-input" autocomplete="new-password">
                                <div v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</div>
                            </div>

                            <div>
                                <label class="field-label" for="password_confirmation">Confirm password</label>
                                <input id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" class="field-input" autocomplete="new-password">
                                <div v-if="passwordForm.errors.password_confirmation" class="field-error">{{ passwordForm.errors.password_confirmation }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="panel p-5">
                    <h2 class="section-title">Danger zone</h2>
                    <p class="mt-2 text-sm text-[var(--muted)]">
                        Deleting the account signs you out and permanently removes your workspace access.
                    </p>

                    <div class="mt-5 space-y-4">
                        <UndoBanner
                            v-if="accountDeletion.pendingAction.active"
                            :label="accountDeletion.pendingAction.label"
                            :seconds-remaining="accountDeletion.pendingAction.secondsRemaining"
                            :busy="accountDeletion.pendingAction.busy"
                            @undo="accountDeletion.cancelAction"
                            @commit="accountDeletion.commitAction"
                        />

                        <div>
                            <label class="field-label" for="delete_password">Confirm with password</label>
                            <input id="delete_password" v-model="deleteForm.password" type="password" class="field-input" autocomplete="current-password">
                            <div v-if="deleteForm.errors.password" class="field-error">{{ deleteForm.errors.password }}</div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-[8px] border border-[var(--danger)] px-3.5 py-2.5 text-sm font-bold text-[var(--danger)] transition hover:bg-[rgba(224,30,90,0.08)]"
                            :disabled="deleteForm.processing"
                            @click="scheduleAccountDeletion"
                        >
                            {{ deleteForm.processing ? 'Deleting...' : 'Delete account' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
