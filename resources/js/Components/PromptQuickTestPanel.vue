<script setup>
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { Bot, FileCode2, MessageSquareText, RotateCcw, SendHorizonal, Sparkles, Variable } from 'lucide-vue-next';
import { extractServerMessage } from '@/lib/forms';
import { safeJsonStringify } from '@/lib/formatters';

const props = defineProps({
    useCaseId: {
        type: [Number, String],
        default: '',
    },
    taskType: {
        type: String,
        default: '',
    },
    modelName: {
        type: String,
        default: '',
    },
    systemPrompt: {
        type: String,
        default: '',
    },
    userPromptTemplate: {
        type: String,
        default: '',
    },
    variablesSchema: {
        type: Array,
        default: () => [],
    },
    outputType: {
        type: String,
        default: 'text',
    },
    outputSchemaJson: {
        type: [Object, Array],
        default: () => ({}),
    },
    models: {
        type: Array,
        required: true,
    },
});

const messages = ref([]);
const draftInput = ref('');
const processing = ref(false);
const errorMessage = ref('');
const selectedModel = ref(props.modelName || props.models[0]?.value || '');
const settings = reactive({
    temperature: 0.2,
    max_tokens: 700,
});
const variableValues = reactive({});
const lastRun = reactive({
    compiled_prompt: '',
    format_valid: true,
    error: '',
    latency_ms: null,
    token_input: null,
    token_output: null,
});

const variableFields = computed(() =>
    (props.variablesSchema ?? []).filter((field) => field?.name && field.name !== 'input_text'),
);

const syncVariables = () => {
    const nextKeys = variableFields.value.map((field) => field.name);

    Object.keys(variableValues).forEach((key) => {
        if (!nextKeys.includes(key)) {
            delete variableValues[key];
        }
    });

    variableFields.value.forEach((field) => {
        if (!(field.name in variableValues)) {
            variableValues[field.name] = field.default ?? '';
        }
    });
};

watch(variableFields, syncVariables, { immediate: true, deep: true });
watch(
    () => props.modelName,
    (value) => {
        const nextModel = `${value ?? ''}`.trim();
        const stillValid = props.models.some((model) => model.value === selectedModel.value);

        if (nextModel && (!selectedModel.value || !stillValid)) {
            selectedModel.value = nextModel;
            return;
        }

        if (!selectedModel.value) {
            selectedModel.value = nextModel || props.models[0]?.value || '';
        }
    },
    { immediate: true },
);

const canSend = computed(() =>
    !processing.value
    && `${draftInput.value}`.trim() !== ''
    && `${selectedModel.value}`.trim() !== ''
    && `${props.userPromptTemplate}`.trim() !== '',
);

const transcript = (nextUserMessage) =>
    [...messages.value, { role: 'user', content: nextUserMessage }]
        .filter((item) => ['user', 'assistant'].includes(item.role))
        .map((item) => `${item.role === 'assistant' ? 'Assistant' : 'User'}: ${item.content}`)
        .join('\n\n');

const normalizedVariables = () =>
    Object.fromEntries(
        Object.entries(variableValues)
            .map(([key, value]) => [key, `${value ?? ''}`.trim()])
            .filter(([, value]) => value !== ''),
    );

const resetConversation = () => {
    messages.value = [];
    draftInput.value = '';
    errorMessage.value = '';
    lastRun.compiled_prompt = '';
    lastRun.format_valid = true;
    lastRun.error = '';
    lastRun.latency_ms = null;
    lastRun.token_input = null;
    lastRun.token_output = null;
};

const send = async () => {
    if (!`${props.userPromptTemplate}`.trim()) {
        errorMessage.value = 'Write the prompt first before running a quick test.';
        return;
    }

    if (!`${draftInput.value}`.trim()) {
        errorMessage.value = 'Write a message first.';
        return;
    }

    errorMessage.value = '';
    processing.value = true;
    const userMessage = draftInput.value.trim();

    try {
        const response = await axios.post(route('api.prompts.quick-test'), {
            use_case_id: props.useCaseId || null,
            task_type: props.taskType || null,
            model_name: selectedModel.value,
            system_prompt: props.systemPrompt || null,
            user_prompt_template: props.userPromptTemplate,
            variables_schema: props.variablesSchema ?? [],
            variables: normalizedVariables(),
            output_type: props.outputType,
            output_schema_json: props.outputSchemaJson ?? {},
            input_text: transcript(userMessage),
            temperature: settings.temperature,
            max_tokens: settings.max_tokens,
            preferred_model: props.modelName || null,
        });

        const result = response.data.data;
        const assistantMessage = props.outputType === 'json' && result.output_json
            ? safeJsonStringify(result.output_json, '{}')
            : result.output_text;

        messages.value.push(
            { role: 'user', content: userMessage },
            { role: 'assistant', content: assistantMessage || '(No content returned)' },
        );
        draftInput.value = '';
        lastRun.compiled_prompt = result.compiled_prompt || '';
        lastRun.format_valid = result.format_valid;
        lastRun.error = result.error || '';
        lastRun.latency_ms = result.latency_ms ?? null;
        lastRun.token_input = result.token_input ?? null;
        lastRun.token_output = result.token_output ?? null;
    } catch (error) {
        errorMessage.value = extractServerMessage(error, 'Quick test failed.');
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <section class="panel p-5">
        <ToastRelay :message="errorMessage" tone="error" />

        <PanelHeader
            title="Try this prompt"
            description="Run a quick back-and-forth without leaving the editor. This uses the current draft fields."
            :icon="MessageSquareText"
            help="This preview keeps the chat locally, turns it into one transcript, and runs the current prompt draft against the selected model."
        />

        <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="space-y-4">
                <div class="panel-muted p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-block-title">
                            <Sparkles />
                            <span>Preview conversation</span>
                        </div>
                        <button type="button" class="btn-danger" @click="resetConversation">
                            <RotateCcw class="h-4 w-4" />
                            <span>Clear preview</span>
                        </button>
                    </div>

                    <div class="mt-4 max-h-[420px] space-y-3 overflow-y-auto pr-1">
                        <div v-if="messages.length === 0" class="empty-state !mx-0 !mb-0 !mt-0">
                            Write a realistic example below and send it. The current prompt draft will answer here.
                        </div>

                        <div
                            v-for="(message, index) in messages"
                            :key="`${message.role}-${index}`"
                            class="guide-card"
                            :class="message.role === 'assistant' ? 'ml-0' : 'ml-auto max-w-[92%]'"
                        >
                            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.12em] text-[var(--muted)]">
                                <span>{{ message.role === 'assistant' ? 'Model' : 'You' }}</span>
                            </div>
                            <pre class="mt-3 whitespace-pre-wrap break-words font-sans text-sm leading-6 text-[var(--ink)]">{{ message.content }}</pre>
                        </div>
                    </div>
                </div>

                <div class="panel-muted p-4">
                    <label class="field-label">Example input</label>
                    <textarea
                        v-model="draftInput"
                        class="field-textarea"
                        placeholder="Paste a realistic customer request, support message, or business example..."
                    />

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm text-[var(--muted)]">
                            Each send uses the full preview transcript as the current input.
                        </div>
                        <button type="button" class="btn-primary" :disabled="!canSend" @click="send">
                            <SendHorizonal class="h-4 w-4" />
                            <span>{{ processing ? 'Sending...' : 'Run preview' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="panel-muted p-4">
                    <div class="text-block-title">
                        <Bot />
                        <span>Run settings</span>
                    </div>

                    <div class="mt-4 grid gap-4">
                        <div>
                            <label class="field-label">Model</label>
                            <select v-model="selectedModel" class="field-select">
                                <option value="">Select model</option>
                                <option v-for="model in models" :key="model.value" :value="model.value">
                                    {{ model.label }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="field-label">Temperature</label>
                                <input v-model="settings.temperature" type="number" min="0" max="2" step="0.1" class="field-input">
                            </div>
                            <div>
                                <label class="field-label">Max tokens</label>
                                <input v-model="settings.max_tokens" type="number" min="64" max="4096" step="1" class="field-input">
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="variableFields.length" class="panel-muted p-4">
                    <div class="text-block-title">
                        <Variable />
                        <span>Input fields</span>
                    </div>

                    <div class="mt-4 grid gap-4">
                        <div v-for="field in variableFields" :key="field.name">
                            <label class="field-label">{{ field.label || field.name }}</label>
                            <input
                                v-model="variableValues[field.name]"
                                type="text"
                                class="field-input"
                                :placeholder="field.required ? 'Required variable' : 'Optional variable'"
                            >
                        </div>
                    </div>
                </div>

                <div class="panel-muted p-4">
                    <div class="text-block-title">
                        <FileCode2 />
                        <span>Final prompt sent to the model</span>
                    </div>

                    <pre class="code-block mt-3">{{ lastRun.compiled_prompt || 'Run one preview to inspect the final prompt here.' }}</pre>

                    <div v-if="lastRun.compiled_prompt" class="summary-strip mt-4">
                        <div class="summary-item">
                            <div class="summary-item-label">Expected format</div>
                            <div class="summary-item-value">{{ lastRun.format_valid ? 'Matched' : 'Needs attention' }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Latency</div>
                            <div class="summary-item-value">{{ lastRun.latency_ms != null ? `${lastRun.latency_ms} ms` : 'N/A' }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Input tokens</div>
                            <div class="summary-item-value">{{ lastRun.token_input ?? 'N/A' }}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-item-label">Output tokens</div>
                            <div class="summary-item-value">{{ lastRun.token_output ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div v-if="lastRun.error" class="field-help mt-3 text-[var(--danger)]">
                        {{ lastRun.error }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
