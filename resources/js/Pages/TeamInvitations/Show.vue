<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { UserPlus } from 'lucide-vue-next';
import { formatRoleLabel } from '@/lib/forms';
import { formatDateTime } from '@/lib/formatters';
import { routeWithQuery } from '@/lib/urlState';

const props = defineProps({
    invitation: {
        type: Object,
        default: null,
    },
    canAccept: {
        type: Boolean,
        required: true,
    },
    emailMatches: {
        type: Boolean,
        required: true,
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const layoutComponent = computed(() => (isAuthenticated.value ? AuthenticatedLayout : GuestLayout));
const loginHref = computed(() => routeWithQuery('login', {}, { invitation: props.invitation?.token ?? '' }));
const registerHref = computed(() => routeWithQuery('register', {}, { invitation: props.invitation?.token ?? '' }));

const acceptInvitation = () => {
    if (!props.invitation) {
        return;
    }

    router.post(route('team-invitations.accept', props.invitation.token));
};
</script>

<template>
    <component :is="layoutComponent">
        <Head title="Workspace Invitation" />

        <div class="page-frame-content">
            <section class="panel p-5">
                <PanelHeader
                    title="Workspace invitation"
                    description="Review the invitation details before joining the workspace."
                    :icon="UserPlus"
                    help="Shows who invited you, which workspace you are joining, and whether the current account can accept the invitation."
                />

                <div v-if="invitation" class="mt-4 space-y-5">
                    <div class="summary-strip">
                        <div class="summary-item">
                            <div class="summary-item-label">Workspace</div>
                            <div class="summary-item-value">{{ invitation.team?.name || 'Unknown workspace' }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Invited email</div>
                            <div class="summary-item-value">{{ invitation.email }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Role</div>
                            <div class="summary-item-value">{{ formatRoleLabel(invitation.role) }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Status</div>
                            <div class="summary-item-value capitalize">{{ invitation.status }}</div>
                        </div>
                    </div>

                    <div class="panel-muted p-4">
                        <div class="summary-list">
                            <div class="summary-row">
                                <span>Invited by</span>
                                <span>{{ invitation.invited_by || 'Unknown sender' }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Created</span>
                                <span>{{ invitation.created_at ? formatDateTime(invitation.created_at) : 'No date' }}</span>
                            </div>
                            <div class="summary-row">
                                <span>Expires</span>
                                <span>{{ invitation.expires_at ? formatDateTime(invitation.expires_at) : 'No expiry' }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!isAuthenticated" class="flex flex-wrap gap-3">
                        <Link :href="loginHref" class="btn-primary">Log in to accept</Link>
                        <Link :href="registerHref" class="btn-secondary">Create account</Link>
                    </div>

                    <div v-else-if="canAccept" class="flex flex-wrap gap-3">
                        <button type="button" class="btn-primary" @click="acceptInvitation">Accept invitation</button>
                        <Link :href="route('dashboard')" class="btn-secondary">Back to dashboard</Link>
                    </div>

                    <div v-else class="text-sm text-[var(--muted)]">
                        <span v-if="!emailMatches">
                            Sign in with <span class="text-[var(--ink)]">{{ invitation.email }}</span> to accept this invitation.
                        </span>
                        <span v-else-if="invitation.status === 'accepted'">
                            This invitation has already been accepted.
                        </span>
                        <span v-else>
                            This invitation is no longer available.
                        </span>
                    </div>
                </div>

                <div v-else class="empty-state mt-4">
                    This invitation link is not valid anymore.
                </div>
            </section>
        </div>
    </component>
</template>
