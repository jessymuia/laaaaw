<template>
    <div class="elevated-card payments-card">
        <div class="card-header">
            <h2 class="card-title">Payments</h2>
            <div class="header-actions">
                <span class="balance-badge" :class="{ paid: outstandingBalance <= 0 }">
                    Outstanding: {{ formatMoney(outstandingBalance) }}
                </span>
                <button
                    v-if="workflowStatus === 'submitted' && outstandingBalance > 0"
                    type="button"
                    class="btn-record-payment"
                    @click="openForm"
                    data-bs-toggle="modal"
                    data-bs-target="#payment_modal"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Record Payment
                </button>
            </div>
        </div>

        <div class="card-body">
            <div v-if="workflowStatus === 'draft'" class="payments-note">
                Payments can be recorded once this invoice is submitted.
            </div>
            <div v-if="loading" class="pay-loading">Loading payments...</div>
            <div v-else-if="!payments.length" class="pay-empty">
                No payments recorded yet.
            </div>
            <ul v-else class="payments-list">
                <li v-for="p in payments" :key="p.id">
                    <div class="pay-main">
                        <span class="pay-receipt">{{ p.receipt_number }}</span>
                        <span class="pay-method">{{ formatMethod(p.method) }}</span>
                        <span class="pay-date">{{ p.payment_date }}</span>
                        <span class="pay-by">Received by {{ p.received_by }}</span>
                    </div>
                    <div class="pay-side">
                        <span class="pay-amount">{{ formatMoney(p.amount) }}</span>
                        <button type="button" class="btn-void" @click="voidPayment(p)" title="Void this payment">Void</button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Record payment modal -->
        <div class="modal fade" id="payment_modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content modern-modal">
                    <div class="modal-header">
                        <h4 class="modal-title">Record Payment</h4>
                        <button id="pay_closebtn" type="button" data-bs-dismiss="modal" aria-label="Close" class="btn-close"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="submitPayment">
                            <div class="form-group">
                                <label class="form-label">Amount <span class="required">*</span></label>
                                <input type="number" step="0.01" min="0.01" :max="outstandingBalance" v-model="form.amount" class="form-control modern-input"
                                    :class="[hasError('amount') ? 'is-invalid' : '']" @input="clearErrors('amount')" />
                                <div class="form-hint">Outstanding balance: {{ formatMoney(outstandingBalance) }}</div>
                                <div class="invalid-feedback">{{ fieldError('amount') }}</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Payment Date <span class="required">*</span></label>
                                <AppDatePicker v-model="form.payment_date" :invalid="submitted && !form.payment_date" />
                            </div>

                            <div class="form-group">
                                <label class="form-label">Method <span class="required">*</span></label>
                                <select v-model="form.method" class="form-select modern-select"
                                    :class="[hasError('method') ? 'is-invalid' : '']" @change="clearErrors('method')">
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="card">Card</option>
                                    <option value="other">Other</option>
                                </select>
                                <div class="invalid-feedback">{{ fieldError('method') }}</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Reference Number</label>
                                <input type="text" v-model="form.reference_number" class="form-control modern-input" placeholder="e.g. transaction ID, cheque number" />
                            </div>

                            <div class="form-group">
                                <label class="form-label">Notes</label>
                                <input type="text" v-model="form.notes" class="form-control modern-input" />
                            </div>

                            <button type="submit" class="btn-submit" :disabled="submitting">
                                {{ submitting ? 'Saving...' : 'Record Payment' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from '../../../api';
import { useToast } from '@/composables/use-toast';
import { useFormErrors } from '@/composables/use-form-errors';
import AppDatePicker from '@/components/ui/app-date-picker.vue';

// FUN-2 frontend: payments/receipts against an invoice. Self-contained,
// mirroring the TimeEntriesPanel pattern used on the case detail page.
const props = defineProps({
    invoiceId: { type: [String, Number], required: true },
    totalAmount: { type: [String, Number], default: 0 },
    amountPaid: { type: [String, Number], default: 0 },
    workflowStatus: { type: String, default: '' },
});

const emit = defineEmits(['updated']);

const { showMessage } = useToast();
const { hasError, fieldError, clearErrors, setErrorsFromResponse } = useFormErrors();

const payments = ref([]);
const loading = ref(true);
const submitting = ref(false);
const submitted = ref(false);
const form = ref({ amount: '', payment_date: '', method: 'cash', reference_number: '', notes: '' });

const outstandingBalance = computed(() => {
    const outstanding = Number(props.totalAmount || 0) - Number(props.amountPaid || 0);
    return outstanding > 0 ? outstanding : 0;
});

const formatMoney = (value) => {
    const n = Number(value) || 0;
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatMethod = (method) => {
    const labels = {
        cash: 'Cash',
        bank_transfer: 'Bank Transfer',
        mobile_money: 'Mobile Money',
        cheque: 'Cheque',
        card: 'Card',
        other: 'Other',
    };
    return labels[method] || method;
};

const fetchPayments = () => {
    loading.value = true;
    axios.get('/api/payments', { params: { invoice_id: props.invoiceId } })
        .then(response => {
            payments.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching payments:', error);
            showMessage('Could not load payments. Please try refreshing the page.', 'error');
        })
        .finally(() => {
            loading.value = false;
        });
};

const openForm = () => {
    clearErrors();
    submitted.value = false;
    form.value = { amount: '', payment_date: '', method: 'cash', reference_number: '', notes: '' };
};

const submitPayment = () => {
    submitted.value = true;
    clearErrors();

    if (!form.value.amount || !form.value.payment_date || !form.value.method) return;

    submitting.value = true;
    axios.post('/api/payments', {
        invoice_id: props.invoiceId,
        amount: form.value.amount,
        payment_date: form.value.payment_date,
        method: form.value.method,
        reference_number: form.value.reference_number || null,
        notes: form.value.notes || null,
    })
        .then(() => {
            showMessage('Payment recorded successfully.');
            document.getElementById('pay_closebtn').click();
            fetchPayments();
            emit('updated');
        })
        .catch(error => {
            showMessage(setErrorsFromResponse(error, 'Error recording payment.'), 'error');
        })
        .finally(() => {
            submitting.value = false;
        });
};

const voidPayment = (payment) => {
    window.Swal.fire({
        title: 'Void this payment?',
        text: `This will remove ${payment.receipt_number} (${formatMoney(payment.amount)}) from the invoice's paid total. This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        confirmButtonText: 'Void it',
    }).then((result) => {
        if (!result.isConfirmed) return;

        axios.delete(`/api/payments/${payment.id}`)
            .then(() => {
                showMessage('Payment voided.');
                fetchPayments();
                emit('updated');
            })
            .catch(error => {
                showMessage(error.response?.data?.message || 'Error voiding payment.', 'error');
            });
    });
};

// Re-fetch if the parent swaps to a different invoice without remounting
// this component (unlikely on this page today, but keeps the panel
// correct if that ever changes).
watch(() => props.invoiceId, fetchPayments);

onMounted(() => {
    fetchPayments();
});
</script>

<style lang="scss" scoped>
.payments-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.balance-badge {
    font-size: 12px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(231, 81, 90, 0.1);
    color: #e7515a;

    &.paid {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
}

.btn-record-payment {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    background: linear-gradient(135deg, var(--primary-600), var(--primary-800));
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.payments-note {
    padding: 12px 16px;
    background: rgba(107, 114, 128, 0.08);
    border-radius: 8px;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 16px;
}

.pay-loading, .pay-empty {
    padding: 24px 0;
    text-align: center;
    color: #6b7280;
    font-size: 13px;
}

.payments-list {
    list-style: none;
    margin: 0;
    padding: 0;

    li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);

        &:last-child {
            border-bottom: none;
        }
    }
}

.pay-main {
    display: flex;
    flex-direction: column;
    gap: 2px;

    .pay-receipt {
        font-weight: 700;
        font-size: 13px;
    }

    .pay-method, .pay-date, .pay-by {
        font-size: 12px;
        color: #6b7280;
    }
}

.pay-side {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.pay-amount {
    font-weight: 700;
    color: #10b981;
    font-size: 14px;
}

.btn-void {
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid #e7515a;
    color: #e7515a;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;

    &:hover {
        background: #e7515a;
        color: white;
    }
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
}

.form-hint {
    font-size: 11px;
    color: #6b7280;
    margin-top: 4px;
}

.required {
    color: #e7515a;
}

.btn-submit {
    width: 100%;
    padding: 10px;
    background: linear-gradient(135deg, var(--primary-600), var(--primary-800));
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

.invalid-feedback {
    display: none;
    font-size: 12px;
    color: #e7515a;
    margin-top: 4px;
}

.is-invalid ~ .invalid-feedback {
    display: block;
}
</style>
