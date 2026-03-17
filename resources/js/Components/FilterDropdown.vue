<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Check, ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    icon: {
        type: [Object, Function],
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    selected: {
        type: [String, Number],
        default: '',
    },
    selectedLabel: {
        type: String,
        default: '',
    },
    width: {
        type: String,
        default: '240px',
    },
    clearLabel: {
        type: String,
        default: 'Clear',
    },
    clearable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['select', 'clear']);

const root = ref(null);
const menu = ref(null);
const open = ref(false);
const menuReady = ref(false);
const menuStyle = ref({
    '--filter-menu-width': '240px',
});

const hasValue = computed(() => `${props.selected ?? ''}`.trim() !== '');
const resolvedSelectedLabel = computed(() => {
    if (props.selectedLabel) {
        return props.selectedLabel;
    }

    const selectedOption = props.options.find((option) => `${option.value}` === `${props.selected}`);

    return selectedOption?.label ?? '';
});

const closeMenu = () => {
    open.value = false;
    menuReady.value = false;
};

const toggleMenu = () => {
    if (open.value) {
        closeMenu();
        return;
    }

    openMenu();
};

const openMenu = async () => {
    open.value = true;
    menuReady.value = false;
    menuStyle.value = {
        '--filter-menu-width': props.width,
        top: '0px',
        left: '0px',
    };

    await positionMenu();
};

const selectOption = (value) => {
    emit('select', value);
    closeMenu();
};

const clearSelection = () => {
    emit('clear');
    closeMenu();
};

const handlePointerDown = (event) => {
    if (
        !open.value ||
        (
            !root.value?.contains(event.target) &&
            !menu.value?.contains(event.target)
        )
    ) {
        closeMenu();
    }
};

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        closeMenu();
    }
};

const positionMenu = async () => {
    if (!open.value || !root.value) {
        return;
    }

    await nextTick();

    const triggerRect = root.value.getBoundingClientRect();
    const menuElement = menu.value;

    if (!menuElement) {
        return;
    }

    const menuWidth = menuElement.offsetWidth;
    const menuHeight = menuElement.offsetHeight;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    let left = triggerRect.left;
    let top = triggerRect.bottom + 8;

    if (left + menuWidth > viewportWidth - 16) {
        left = Math.max(16, triggerRect.right - menuWidth);
    }

    if (top + menuHeight > viewportHeight - 16) {
        top = Math.max(16, triggerRect.top - menuHeight - 8);
    }

    menuStyle.value = {
        '--filter-menu-width': props.width,
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
    };
    menuReady.value = true;
};

onMounted(() => {
    document.addEventListener('mousedown', handlePointerDown);
    document.addEventListener('keydown', handleEscape);
    document.addEventListener('scroll', positionMenu, true);
    window.addEventListener('resize', positionMenu);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handlePointerDown);
    document.removeEventListener('keydown', handleEscape);
    document.removeEventListener('scroll', positionMenu, true);
    window.removeEventListener('resize', positionMenu);
});
</script>

<template>
    <div ref="root" class="filter-dropdown">
        <button
            type="button"
            class="filter-chip"
            :class="{ 'filter-chip-active': open || hasValue }"
            @click="toggleMenu"
        >
            <component :is="icon" v-if="icon" class="filter-chip-icon" />
            <span class="filter-chip-label">{{ hasValue ? resolvedSelectedLabel : label }}</span>
            <ChevronDown class="filter-chip-caret" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menu"
                class="filter-menu"
                :style="{
                    ...menuStyle,
                    visibility: menuReady ? 'visible' : 'hidden',
                }"
            >
                <slot :close="closeMenu">
                    <div class="filter-menu-options">
                        <button
                            v-for="option in options"
                            :key="`${label}-${option.value}`"
                            type="button"
                            class="filter-menu-option"
                            :class="{ 'filter-menu-option-active': `${option.value}` === `${selected}` }"
                            @click="selectOption(option.value)"
                        >
                            <span>{{ option.label }}</span>
                            <Check v-if="`${option.value}` === `${selected}`" class="filter-menu-check" />
                        </button>
                    </div>

                    <button v-if="clearable" type="button" class="filter-menu-clear" @click="clearSelection">
                        {{ clearLabel }}
                    </button>
                </slot>
            </div>
        </Teleport>
    </div>
</template>
