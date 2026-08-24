<template>
    <div class="modal fade" :id="modalId" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" :class="sizeClass" role="document">
            <div class="modal-content crud-modal-content">
                <div class="modal-header">
                    <slot name="header">
                        <h4 class="modal-title">{{ title }}</h4>
                    </slot>
                    <button
                        :id="`${modalId}-closebtn`"
                        type="button"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                        class="btn-close"
                    ></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="$emit('submit')">
                        <slot></slot>
                        <div class="crud-modal-actions">
                            <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-submit" :disabled="submitting">
                                {{ submitting ? 'Saving...' : submitLabel }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

/**
 * §4 point 2 / UI-4: the shared "modal chrome" every create/edit form in
 * this app re-implements by hand (header, close button, form wrapper,
 * cancel/submit footer) — this doesn't replace each module's own fields
 * (those stay module-specific, passed in via the default slot), only the
 * repeated shell around them.
 *
 * Programmatic open/close still goes through Bootstrap's native data
 * attributes (data-bs-toggle="modal" data-bs-target="#<modalId>"), same
 * as every existing modal in the app, so this is a drop-in replacement
 * for a module's own <div class="modal">...</div> markup, not a new
 * interaction pattern to learn.
 *
 * Usage:
 *   <CrudModal modal-id="client_modal" :title="modal.title" :submit-label="modal.button"
 *              :submitting="submitting" @submit="submitForm">
 *     <div class="form-group">...</div>
 *   </CrudModal>
 */
const props = defineProps({
    modalId: { type: String, required: true },
    title: { type: String, default: '' },
    submitLabel: { type: String, default: 'Save' },
    submitting: { type: Boolean, default: false },
    size: { type: String, default: 'default' }, // 'default' | 'lg' | 'xl'
});

defineEmits(['submit']);

const sizeClass = computed(() => {
    if (props.size === 'lg') return 'modal-lg';
    if (props.size === 'xl') return 'modal-xl';
    return '';
});
</script>

<style lang="scss" scoped>
.crud-modal-content {
    border-radius: var(--radius-lg);
    border: none;
    overflow: hidden;
    background: var(--card-bg);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-600), var(--primary-800));
    color: var(--text-inverse);
    border-bottom: none;

    .modal-title {
        color: var(--text-inverse);
        font-weight: var(--font-weight-bold);
        font-size: var(--font-size-base);
    }

    .btn-close {
        filter: brightness(0) invert(1);
    }
}

.crud-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--border-color);
}

.btn-cancel {
    padding: 10px 20px;
    background: var(--surface);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-weight: var(--font-weight-semibold);
    font-size: var(--font-size-sm);
    cursor: pointer;
    transition: background var(--transition-fast);

    &:hover {
        background: var(--neutral-100);
    }
}

.btn-submit {
    padding: 10px 24px;
    background: linear-gradient(135deg, var(--primary-600), var(--primary-800));
    color: var(--text-inverse);
    border: none;
    border-radius: var(--radius-md);
    font-weight: var(--font-weight-semibold);
    font-size: var(--font-size-sm);
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}
</style>
