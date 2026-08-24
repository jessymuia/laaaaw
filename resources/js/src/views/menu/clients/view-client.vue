<template>
    <div class="modern-view-client">
        <teleport to="#breadcrumb">
            <ul class="navbar-nav flex-row">
                <li>
                    <div class="page-header">
                        <nav class="breadcrumb-one" aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="javascript:;" @click="$router.push('/clients')">Clients</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><span>Client Details</span></li>
                            </ol>
                        </nav>
                    </div>
                </li>
            </ul>
        </teleport>

        <!-- Header with Actions -->
        <div class="client-header">
            <button class="btn-back" @click="$router.push('/clients')">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Clients
            </button>
            <div class="header-actions">
                <button class="btn-action-header btn-edit" @click="editClient">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Edit Client
                </button>
                <button class="btn-action-header btn-delete" @click="deleteClient">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    Delete
                </button>
            </div>
        </div>

        <div class="client-content">
            <!-- Client Profile Card -->
            <div class="client-profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        {{ clientData.name ? clientData.name.charAt(0).toUpperCase() : 'C' }}
                    </div>
                    <div class="profile-info">
                        <h1 class="client-name">{{ clientData.name || 'Client Name' }}</h1>
                        <div class="client-meta">
                            <span class="meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                Client since {{ formatDate(clientData.created_at) }}
                            </span>
                            <span class="meta-item status-active">
                                <span class="status-dot"></span>
                                Active Client
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="details-grid">
                <!-- Contact Information -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon contact-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </div>
                        <h3 class="card-title">Contact Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Primary Phone</span>
                            <span class="info-value">{{ clientData.phone_number || 'Not provided' }}</span>
                        </div>
                        <div class="info-row" v-if="clientData.extra_phone_number">
                            <span class="info-label">Alternative Phone</span>
                            <span class="info-value">{{ clientData.extra_phone_number }}</span>
                        </div>
                        <div class="info-row" v-if="clientData.email">
                            <span class="info-label">Email Address</span>
                            <span class="info-value email">{{ clientData.email }}</span>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon location-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </div>
                        <h3 class="card-title">Location Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-row full-width">
                            <span class="info-label">Address</span>
                            <span class="info-value">{{ clientData.address || 'Not provided' }}</span>
                        </div>
                        <div class="info-row" v-if="clientData.city">
                            <span class="info-label">City</span>
                            <span class="info-value">{{ clientData.city }}</span>
                        </div>
                    </div>
                </div>

                <!-- Advocate Information -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon advocate-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <h3 class="card-title">Assigned Advocate</h3>
                    </div>
                    <div class="card-body">
                        <div class="advocate-profile">
                            <div class="advocate-avatar">
                                {{ clientData.advocate ? clientData.advocate.charAt(0).toUpperCase() : 'A' }}
                            </div>
                            <div class="advocate-details">
                                <h4 class="advocate-name">{{ clientData.advocate || 'Not assigned' }}</h4>
                                <span class="advocate-role">Lead Advocate</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon info-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                        </div>
                        <h3 class="card-title">Additional Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Client ID</span>
                            <span class="info-value">#{{ clientData.id || '000' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Registration Date</span>
                            <span class="info-value">{{ formatDate(clientData.created_at) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Last Updated</span>
                            <span class="info-value">{{ formatDate(clientData.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cases/Matters Section -->
            <div class="cases-section">
                <div class="section-header">
                    <h2 class="section-title">Associated Cases</h2>
                    <button class="btn-add-case">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Case
                    </button>
                </div>
                
                <div class="cases-grid">
                    <template v-if="clientData.cases && clientData.cases.length > 0">
                        <div class="case-card" v-for="case_item in clientData.cases" :key="case_item.id">
                            <div class="case-header">
                                <span class="case-number">Case #{{ case_item.case_number }}</span>
                                <span class="case-status" :class="case_item.status">{{ case_item.status }}</span>
                            </div>
                            <h4 class="case-title">{{ case_item.title }}</h4>
                            <p class="case-description">{{ case_item.description }}</p>
                            <div class="case-footer">
                                <span class="case-date">{{ formatDate(case_item.created_at) }}</span>
                                <button class="btn-view-case">View Details →</button>
                            </div>
                        </div>
                    </template>

                    <div class="empty-state" v-else>
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="12" y1="18" x2="12" y2="12"></line>
                            <line x1="9" y1="15" x2="15" y2="15"></line>
                        </svg>
                        <h3>No cases yet</h3>
                        <p>This client doesn't have any associated cases.</p>
                    </div>
                </div>
            </div>

            <!-- Trust Ledger Section (FUN-2) -->
            <div class="trust-section">
                <div class="section-header">
                    <h2 class="section-title">Trust Account</h2>
                    <div class="trust-balance-badge" :class="{ 'zero-balance': trustBalance <= 0 }">
                        Balance: {{ formatMoney(trustBalance) }}
                    </div>
                </div>

                <div class="trust-grid">
                    <!-- New transaction form -->
                    <div class="trust-form-card">
                        <h4>Record a Transaction</h4>
                        <form @submit.prevent="submitTrustTransaction">
                            <div class="form-group">
                                <label class="form-label">Type</label>
                                <select v-model="trustForm.type" class="form-select modern-select">
                                    <option value="deposit">Deposit</option>
                                    <option value="disbursement">Disbursement</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0.01" v-model="trustForm.amount" class="form-control modern-input" placeholder="0.00" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Related case (optional)</label>
                                <select v-model="trustForm.case_id" class="form-select modern-select">
                                    <option value="">None</option>
                                    <option v-for="c in clientData.cases" :key="c.id" :value="c.id">{{ c.case_number }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <input type="text" v-model="trustForm.description" class="form-control modern-input" placeholder="e.g. Initial retainer" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reference number (optional)</label>
                                <input type="text" v-model="trustForm.reference_number" class="form-control modern-input" placeholder="e.g. receipt/cheque number" />
                            </div>
                            <button type="submit" class="btn-submit-trust" :disabled="trustSubmitting">
                                {{ trustSubmitting ? 'Saving...' : 'Record Transaction' }}
                            </button>
                        </form>
                    </div>

                    <!-- Transaction history -->
                    <div class="trust-history-card">
                        <h4>Transaction History</h4>
                        <div v-if="trustLoading" class="trust-loading">Loading...</div>
                        <div v-else-if="!trustTransactions.length" class="empty-state small">
                            <p>No trust transactions yet.</p>
                        </div>
                        <ul v-else class="trust-list">
                            <li v-for="t in trustTransactions" :key="t.id" :class="{ voided: t.voided }">
                                <div class="trust-list-main">
                                    <span class="trust-type" :class="t.type">{{ t.type === 'deposit' ? 'Deposit' : 'Disbursement' }}</span>
                                    <span class="trust-desc">{{ t.description }}</span>
                                    <span class="trust-date">{{ t.date }}{{ t.voided ? ' — VOIDED' : '' }}</span>
                                </div>
                                <div class="trust-list-side">
                                    <span class="trust-amount" :class="t.type">
                                        {{ t.type === 'deposit' ? '+' : '-' }}{{ formatMoney(t.amount) }}
                                    </span>
                                    <button v-if="!t.voided" type="button" class="btn-void" @click="voidTrustTransaction(t)" title="Void this transaction">
                                        Void
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from "../../../api";
import { useMeta } from '@/composables/use-meta';
import { useToast } from '@/composables/use-toast';
import { useConfirmDelete } from '@/composables/use-confirm-delete';
import AppDatePicker from '@/components/ui/app-date-picker.vue';

useMeta({ title: 'View Client' });

const { showMessage } = useToast();
const { confirmDelete } = useConfirmDelete();

const router = useRouter();
const route = useRoute();

const clientData = ref({
    id: '',
    name: '',
    phone_number: '',
    extra_phone_number: '',
    email: '',
    address: '',
    city: '',
    advocate: '',
    created_at: '',
    updated_at: '',
    cases: []
});

// Trust ledger (FUN-2)
const trustBalance = ref(0);
const trustTransactions = ref([]);
const trustLoading = ref(true);
const trustForm = ref({ type: 'deposit', amount: '', description: '', reference_number: '', case_id: '' });
const trustSubmitting = ref(false);

onMounted(() => {
    fetchClientData();
    fetchTrustLedger();
});

const fetchClientData = () => {
    const clientId = route.params.id;
    
    axios.get(`/api/clients/${clientId}`)
        .then(response => {
            clientData.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching client data:', error);
            showMessage('Error loading client data', 'error');
        });
};

const fetchTrustLedger = () => {
    trustLoading.value = true;
    axios.get('/api/trust-transactions', { params: { client_id: route.params.id } })
        .then(response => {
            trustBalance.value = response.data.data.balance;
            trustTransactions.value = response.data.data.transactions;
        })
        .catch(error => {
            // A 403 here just means this user lacks trust-ledger
            // permissions — the rest of the client page still works, so
            // don't surface an error toast for that, only log it.
            console.error('Error fetching trust ledger:', error);
        })
        .finally(() => {
            trustLoading.value = false;
        });
};

const submitTrustTransaction = () => {
    if (!trustForm.value.amount || !trustForm.value.description) return;

    trustSubmitting.value = true;
    axios.post('/api/trust-transactions', {
        client_id: route.params.id,
        case_id: trustForm.value.case_id || null,
        type: trustForm.value.type,
        amount: trustForm.value.amount,
        description: trustForm.value.description,
        reference_number: trustForm.value.reference_number || null,
    })
        .then(() => {
            showMessage(`${trustForm.value.type === 'deposit' ? 'Deposit' : 'Disbursement'} recorded successfully.`);
            trustForm.value = { type: 'deposit', amount: '', description: '', reference_number: '', case_id: '' };
            fetchTrustLedger();
        })
        .catch(error => {
            showMessage(error.response?.data?.message || 'Error recording transaction.', 'error');
        })
        .finally(() => {
            trustSubmitting.value = false;
        });
};

const voidTrustTransaction = (transaction) => {
    window.Swal.fire({
        title: 'Void this transaction?',
        text: 'This cannot be undone. The entry stays on the record but is excluded from the balance.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        confirmButtonText: 'Void it',
    }).then((result) => {
        if (!result.isConfirmed) return;

        axios.put(`/api/trust-transactions/${transaction.id}/void`)
            .then(() => {
                showMessage('Transaction voided.');
                fetchTrustLedger();
            })
            .catch(error => {
                showMessage(error.response?.data?.message || 'Error voiding transaction.', 'error');
            });
    });
};

const formatMoney = (value) => {
    const n = Number(value) || 0;
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(date).toLocaleDateString('en-US', options);
};

const editClient = () => {
    router.push({ name: 'clients', query: { edit: clientData.value.id } });
};

const deleteClient = () => {
    confirmDelete({
        url: `/api/clients/${clientData.value.id}`,
        itemLabel: clientData.value.name,
        onSuccess: () => {
            router.push('/clients');
        },
    });
};
</script>