<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    position: {
        type: String,
        default: 'down',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white',
    },
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const widthClass = computed(() => {
    return {
        48: 'w-48',
        56: 'w-56',
        64: 'w-64',
    }[props.width.toString()];
});

const alignmentClasses = computed(() => {
    if (props.align === 'left') {
        return 'start-0';
    } else if (props.align === 'right') {
        return 'end-0';
    } else {
        return '';
    }
});

const positionClasses = computed(() => {
    if (props.position === 'up') {
        return 'bottom-full mb-2';
    }

    return 'top-full mt-2';
});

const originClasses = computed(() => {
    if (props.position === 'up') {
        if (props.align === 'left') {
            return 'origin-bottom-left';
        }

        if (props.align === 'right') {
            return 'origin-bottom-right';
        }

        return 'origin-bottom';
    }

    if (props.align === 'left') {
        return 'origin-top-left';
    }

    if (props.align === 'right') {
        return 'origin-top-right';
    }

    return 'origin-top';
});

const open = ref(false);
</script>

<template>
    <div class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Dropdown Overlay -->
        <div
            v-show="open"
            class="fixed inset-0 z-40"
            @click="open = false"
        ></div>

        <Transition
            enter-active-class="transition-opacity ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="open"
                class="absolute z-50"
                :class="[widthClass, alignmentClasses, positionClasses, originClasses, 'max-w-[calc(100vw-1rem)]']"
                style="display: none"
                @click="open = false"
            >
                <div
                    class="overflow-hidden"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
