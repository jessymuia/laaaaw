<template>
    <div class="modern-view-page">
        <teleport to="#breadcrumb">
            <ul class="navbar-nav flex-row">
                <li>
                    <div class="page-header">
                        <nav class="breadcrumb-one" aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="javascript:;">Cases</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><span>Case Details</span></li>
                            </ol>
                        </nav>
                    </div>
                </li>
            </ul>
        </teleport>

        <!-- Case Information Card -->
        <div class="elevated-card">
            <div class="card-header">
                <div class="header-content">
                    <h2 class="card-title">Case Information</h2>
                    <span class="case-badge">{{ caseD.case_number || 'N/A' }}</span>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label class="info-label">Case Number</label>
                        <div class="info-value">{{ caseD.case_number || 'N/A' }}</div>
                    </div>

                    <div class="info-item">
                        <label class="info-label">Client</label>
                        <div class="info-value">{{ caseD.client || 'N/A' }}</div>
                    </div>

                    <div class="info-item">
                        <label class="info-label">Assigned Attorney</label>
                        <div class="info-value">{{ caseD.assigned || 'N/A' }}</div>
                    </div>

                    <div class="info-item">
                        <label class="info-label">Case Type</label>
                        <div class="info-value">{{ caseD.type || 'N/A' }}</div>
                    </div>

                    <div class="info-item">
                        <label class="info-label">Start Date</label>
                        <div class="info-value">{{ caseD.start_date || 'N/A' }}</div>
                    </div>

                    <div class="info-item">
                        <label class="info-label">Police Station</label>
                        <div class="info-value">{{ caseD.police_station || 'N/A' }}</div>
                    </div>

                    <div class="info-item">
                        <label class="info-label">Court</label>
                        <div class="info-value">{{ caseD.court || 'N/A' }}</div>
                    </div>

                    <div class="info-item">
                        <label class="info-label">Opposing Party</label>
                        <div class="info-value">{{ caseD.opposing_party || 'N/A' }}</div>
                    </div>

                    <div class="info-item full-width">
                        <label class="info-label">Description</label>
                        <div class="info-value description-value">{{ caseD.description || 'No description provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Case Documents Card -->
        <div class="elevated-card">
            <div class="card-header">
                <h2 class="card-title">Case Documents</h2>
                <button class="btn-add" @click="openAddDocumentModal" data-bs-toggle="modal" data-bs-target="#clientD_modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Document
                </button>
            </div>
            
            <div class="card-body">
                <div class="custom-table">
                    <v-client-table :data="itemsD" :columns="columnsD" :options="table_optionD">
                        <template #actions="props">
                            <div class="actions text-center">
                                <button type="button" class="btn-action btn-preview" @click="preview(props.row.id)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    Preview
                                </button>
                                <button type="button" class="btn-action btn-delete" @click="deleteDocument(props.row)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </template>
                    </v-client-table>
                </div>
            </div>
        </div>

        <!-- Case Hearings Card -->
        <div class="elevated-card">
            <div class="card-header">
                <h2 class="card-title">Case Hearings</h2>
                <button class="btn-add" @click="openAddHearingModal" data-bs-toggle="modal" data-bs-target="#clientH_modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Hearing
                </button>
            </div>
            
            <div class="card-body">
                <div class="custom-table">
                    <v-client-table :data="itemsH" :columns="columnsH" :options="table_optionH">
                        <template #actions="props">
                            <div class="actions text-center">
                                <button type="button" class="btn-action btn-edit" @click="update_rowH(props.row)" data-bs-toggle="modal" data-bs-target="#clientH_modal">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Update
                                </button>
                            </div>
                        </template>
                    </v-client-table>
                </div>
            </div>
        </div>

        <!-- Case Expenses Card -->
        <div class="elevated-card">
            <div class="card-header">
                <h2 class="card-title">Case Expenses</h2>
                <span class="expenses-total-badge" data-testid="case-expenses-total">
                    Total: {{ formatMoney(totalExpenses) }}
                </span>
            </div>
            
            <div class="card-body">
                <div class="custom-table">
                    <v-client-table :data="itemsE" :columns="columnsE" :options="table_optionE">
                        <template #actions="props">
                            <div class="actions text-center">
                                <button type="button" class="btn-action btn-delete" @click="deleteExpense(props.row)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </template>
                    </v-client-table>
                </div>
            </div>
        </div>

        <!-- Time Entries Card (FUN-2) -->
        <TimeEntriesPanel v-if="caseD.id" :case-id="caseD.id" :client-id="caseD.client_id" />

        <!-- Modals (keep existing modals) -->
        <CrudModal modal-id="clientH_modal" :title="modalH.title" :submit-label="modalH.button" size="xl" @submit="submit_form4H">
            <div class="row">
                <div id="select_menu" class="form-group form-group">
                    <select v-model="clientH.court_id" class="form-select"
                            :class="[hasError('court_id') ? 'is-invalid' : (is_submit_form4H ? (clientH.court_id ? 'is-valid' : 'is-invalid') : '')]"
                            @change="clearErrors('court_id')">
                        <option value="">Select Court</option>
                        <option v-for="data in courts" :key="data.id" :value="data.id">{{ data.name }}</option>
                    </select>
                    <div class="invalid-feedback">{{ hasError('court_id') ? fieldError('court_id') : 'Please Select the Court' }}</div>
                </div>
                <div class="col-md-12 form-group">
                    <div class="form-group">
                        <label for="date">Hearing Date="'##/##/####'" (dd/mm/yyyy)</label>
                        <input v-model="clientH.hearing_date" type="text" id="date" class="form-control"
                               :class="[hasError('hearing_date') ? 'is-invalid' : (is_submit_form4H ? (clientH.hearing_date ? 'is-valid' : 'is-invalid') : '')]"
                               v-maska="'##/##/####'" placeholder="__/__/____" @input="clearErrors('hearing_date')" />
                        <div class="invalid-feedback">{{ hasError('hearing_date') ? fieldError('hearing_date') : 'Please provide a valid date.' }}</div>
                    </div>
                </div>
                <div class="col-md-12 form-group">
                    <div id="select_menu" class="form-group form-group">
                        <select v-model="clientH.hearing_type" class="form-select"
                                :class="[hasError('hearing_type') ? 'is-invalid' : (is_submit_form4H ? (clientH.hearing_type ? 'is-valid' : 'is-invalid') : '')]"
                                @change="clearErrors('hearing_type')">
                            <option value="">Select Hearing Type</option>
                            <option v-for="data in types" :key="data.id" :value="data.id">{{ data.name }}</option>
                        </select>
                        <div class="invalid-feedback">{{ hasError('hearing_type') ? fieldError('hearing_type') : 'Please Select the type' }}</div>
                    </div>
                </div>
                <div class="col-md-12 form-group">
                    <label for="validationCustom01">Notes</label>
                    <input type="text" v-model="clientH.notes" class="form-control"
                        :class="[hasError('notes') ? 'is-invalid' : (is_submit_form4H ? (clientH.notes ? 'is-valid' : 'is-invalid') : '')]"
                        id="validationCustom01" placeholder="Notes" @input="clearErrors('notes')" />
                    <div class="invalid-feedback">{{ hasError('notes') ? fieldError('notes') : 'Please fill the Notes' }}</div>
                </div>
                <div class="col-md-12 form-group">
                    <label for="validationCustom01">Outcomes</label>
                    <input type="text" v-model="clientH.outcome" class="form-control"
                        :class="[hasError('outcome') ? 'is-invalid' : (is_submit_form4H ? (clientH.outcome ? 'is-valid' : 'is-invalid') : '')]"
                        id="validationCustom01" placeholder="Description" @input="clearErrors('outcome')" />
                    <div class="invalid-feedback">{{ hasError('outcome') ? fieldError('outcome') : 'Please fill the outcomes' }}</div>
                </div>
            </div>
        </CrudModal>

        <CrudModal modal-id="clientD_modal" :title="modalD.title" :submit-label="modalD.button" @submit="submit_form4D">
            <div class="row">
                <div class="col-md-12 form-group">
                    <label for="validationCustom01">Title</label>
                    <input type="text" v-model="clientD.title" class="form-control"
                        :class="[hasError('title') ? 'is-invalid' : (is_submit_form4D ? (clientD.title ? 'is-valid' : 'is-invalid') : '')]"
                        id="validationCustom01" placeholder="Document Title" @input="clearErrors('title')" />
                    <div class="invalid-feedback">{{ hasError('title') ? fieldError('title') : 'Please fill the title' }}</div>
                </div>
                <div class="error-text text-danger">* Please upload one document at a time</div>
                <div class="col-md-12 form-group">
                    <label for="validationCustom01">File Upload</label>
                    <label class="custom-file-container__custom-file">
                        <input type="file" class="custom-file-container__custom-file__custom-file-input" @change="handleFileInputChange" />
                        <input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
                        <span class="custom-file-container__custom-file__custom-file-control"></span>
                    </label>
                    <div class="custom-file-container__image-preview"></div>
                </div>
                <div class="col-md-12 mt-3">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                            role="progressbar" :style="{ width: progress + '%' }"
                            :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                            {{ progress }}%
                        </div>
                    </div>
                </div>
            </div>
        </CrudModal>
    </div>
</template>

<script setup>
import {onMounted, ref, computed} from 'vue';
import '@/assets/sass/scrollspyNav.scss';
import '@/assets/sass/users/account-setting.scss';
import { useMeta } from '@/composables/use-meta';
import { useFormErrors } from '@/composables/use-form-errors';
import { useToast } from '@/composables/use-toast';
import { useConfirmDelete } from '@/composables/use-confirm-delete';
import CrudModal from '@/components/ui/crud-modal.vue';
import {useRouter} from "vue-router";
import axios from "../../../api";
import TimeEntriesPanel from './time-entries-panel.vue';

useMeta({ title: 'View Case' });

const { hasError, fieldError, clearErrors, setErrorsFromResponse } = useFormErrors();
const { showMessage } = useToast();
const { confirmDelete } = useConfirmDelete();

const columns = ref(['case_number', 'description', 'client', 'attorney', 'start_date', 'end_date','case_type','police_station','court','opposing_party', 'actions']);
const items = ref([]);
const types = ref([]);
const uploading = ref(false);
const progress = ref(0);
const table_option = ref({
    perPage: 10,
    perPageValues: [5, 10, 20, 50],
    skin: 'table table-hover',
    columnsClasses: { actions: 'actions text-center' },
    pagination: { nav: 'scroll', chunk: 5 },
    texts: {
        count: 'Showing {from} to {to} of {count}',
        filter: '',
        filterPlaceholder: 'Search...',
        limit: 'Results:',
    },
    sortable: ['case_number', 'description', 'client', 'attorney', 'start_date', 'end_date','case_type','police_station','court','opposing_party'],
    sortIcon: {
        base: 'sort-icon-none',
        up: 'sort-icon-asc',
        down: 'sort-icon-desc',
    },
    resizableColumns: false,
});

const columnsH = ref(['case', 'court','hearing_date','hearing_type_name','notes','outcome', 'actions']);
const itemsH = ref([]);
const table_optionH = ref({
    perPage: 10,
    perPageValues: [5, 10, 20, 50],
    skin: 'table table-hover',
    columnsClasses: { actions: 'actions text-center' },
    pagination: { nav: 'scroll', chunk: 5 },
    texts: {
        count: 'Showing {from} to {to} of {count}',
        filter: '',
        filterPlaceholder: 'Search...',
        limit: 'Results:',
    },
    sortable: ['case', 'court','hearing_date','hearing_type','notes','outcome'],
    sortIcon: {
        base: 'sort-icon-none',
        up: 'sort-icon-asc',
        down: 'sort-icon-desc',
    },
    resizableColumns: false,
});

const columnsD = ref(['title', 'filename',  'actions']);
const itemsD = ref([]);
const table_optionD = ref({
    perPage: 10,
    perPageValues: [5, 10, 20, 50],
    skin: 'table table-hover',
    columnsClasses: { actions: 'actions text-center' },
    pagination: { nav: 'scroll', chunk: 5 },
    texts: {
        count: 'Showing {from} to {to} of {count}',
        filter: '',
        filterPlaceholder: 'Search...',
        limit: 'Results:',
    },
    sortable: ['title', 'filename'],
    sortIcon: {
        base: 'sort-icon-none',
        up: 'sort-icon-asc',
        down: 'sort-icon-desc',
    },
    resizableColumns: false,
});

const itemsE = ref([]);

// FUN-2/journey-5: "Case Expenses" needed a visible running total that
// recalculates as expenses are added/removed — there was none anywhere
// in the app until now (see the ExpenseController::index case_id filter
// fix this total also depends on).
const totalExpenses = computed(() =>
    itemsE.value.reduce((sum, expense) => sum + Number(expense.amount || 0), 0)
);

const formatMoney = (value) => {
    const n = Number(value) || 0;
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
const columnsE = ref(['case', 'expense_date','amount','category','description','vendor','payment_method','invoice_number','user', 'actions']);
const table_optionE = ref({
    perPage: 10,
    perPageValues: [5, 10, 20, 50],
    skin: 'table table-hover',
    columnsClasses: { actions: 'actions text-center' },
    pagination: { nav: 'scroll', chunk: 5 },
    texts: {
        count: 'Showing {from} to {to} of {count}',
        filter: '',
        filterPlaceholder: 'Search...',
        limit: 'Results:',
    },
    sortable: ['case', 'court','hearing_date','hearing_type','notes','outcome'],
    sortIcon: {
        base: 'sort-icon-none',
        up: 'sort-icon-asc',
        down: 'sort-icon-desc',
    },
    resizableColumns: false,
});

const modalH = ref({title: "", button: ""});
const modalD = ref({title: "", button: ""});
const caseD = ref({});
const courts = ref([]);
const is_submit_form4D = ref(false);
const router = useRouter();
const selected = ref(null);
const selected_file = ref(null);
const months = ref(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);

// UI-4/UI-8 fix: this used to keep a raw Bootstrap Modal instance
// (initPopup/deleteConfirmation) plus a delete_task() that showed
// "deleted successfully" and closed the modal unconditionally —
// *before* the delete request even resolved, so a failed delete still
// told the user it worked. useConfirmDelete only reports success once
// the request actually succeeds, and failure gets its own message.
const deleteDocument = (item) => {
    confirmDelete({
        url: `/api/documents/${item.id}`,
        itemLabel: item.title || 'this document',
        onSuccess: () => { fetchData(); },
    });
};

const deleteExpense = (item) => {
    confirmDelete({
        url: `/api/expenses/${item.id}`,
        itemLabel: item.description || 'this expense',
        onSuccess: () => { fetchData(); },
    });
};

const handleFileInputChange = (event) => {
    selected_file.value = event.target.files[0];
    if (selected_file.value) {
        uploading.value = true;
        progress.value = 0;
        simulateUpload();
    }
};

const simulateUpload = () => {
    let interval = setInterval(() => {
        if (progress.value >= 100) {
            clearInterval(interval);
            setTimeout(() => {
               uploading.value = false;
            }, 500);
        } else {
            progress.value += 10;
        }
    }, 500);
};

const is_submit_form4H = ref(false);
const clientH = ref({id: "",case_id:"",court_id : "", hearing_date:"",hearing_type:"", notes:"", outcome:""});

const submit_form4H = () => {
    is_submit_form4H.value = true;
    clearErrors();
    clientH.value.case_id = localStorage.getItem('caseId');
    if (clientH.value.case_id && clientH.value.hearing_type && clientH.value.hearing_date && clientH.value.court_id) {
        if(clientH.value.id > 0) {
            axios.put(`/api/hearings/${clientH.value.id}`, clientH.value)
                .then(response => {
                    itemsH.value = response.data.data;
                    showMessage('Hearing updated successfully.');
                    document.getElementById('clientH_modal-closebtn').click();
                })
                .catch(error => {
                    showMessage(setErrorsFromResponse(error, 'Error updating hearing. Please try again.'), 'error');
                });
        } else {
            axios.post('/api/hearings', clientH.value)
                .then(response => {
                    itemsH.value = response.data.data;
                    showMessage('Hearing added successfully.');
                    document.getElementById('clientH_modal-closebtn').click();
                })
                .catch(error => {
                    showMessage(setErrorsFromResponse(error, 'Error adding hearing. Please try again.'), 'error');
                });
        }
        clientH.value.id = "";
        clientH.value.hearing_date = "";
        clientH.value.hearing_type = "";
        clientH.value.notes = "";
        clientH.value.outcome = "";
        modalH.value.title = "Add Hearing";
        modalH.value.button = "Add";
        is_submit_form4H.value = false;
    }
};

const clientD = ref({id: "",title:"", case_id:"",file:null});

const submit_form4D = () => {
    is_submit_form4D.value = true;
    clearErrors();
    clientD.value.case_id = localStorage.getItem('caseId')
    if (clientD.value.case_id && clientD.value.title ) {
        // Bug fix: this used to also support an "update" branch, but it
        // referenced clientH (the hearing form's state) instead of
        // clientD, and DocumentController::update() is an empty no-op
        // stub on the backend regardless — there was no working edit
        // path here, and there's no "Edit Document" button that ever
        // triggered it (update_rowD, which used to populate this state
        // for editing, was dead code with no caller). Document
        // versioning (FUN-5) models a changed document as a new version,
        // not an in-place edit, so a single "add" flow is the correct
        // shape here, not a missing feature.
        let formData = new FormData();
        formData.append('case_id', clientD.value.case_id);
        formData.append('title', clientD.value.title);
        formData.append('file', selected_file.value);

        axios.post('/api/documents',  formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            itemsD.value.push(response.data.data);
            showMessage('Doc added successfully.');
            document.getElementById('clientD_modal-closebtn').click();
        })
            .catch(error => {
                // UI-6 robustness fix: this used to do
                // error.response.data.errors["file"][0] directly —
                // if the failure wasn't a "file" validation error
                // (a different field, a network error, a 500), that
                // line itself threw, and the user got no message of
                // any kind. setErrorsFromResponse degrades safely to
                // a generic message for any error shape.
                showMessage(setErrorsFromResponse(error, 'Error adding document. Please try again.'), 'error');
            });
        clientD.value.id = "";
        clientD.value.title = "";
        clientD.value.file = null;
        selected_file.value = null;
        modalD.value.title = "Add Document";
        modalD.value.button = "Add";
        is_submit_form4D.value = false;
    }
};

const preview = (id) => {
    axios.get('/api/preview', {
        params: {
            id : id
        },
        responseType: 'blob'
    })
        .then(response => {
            const blob = new Blob([response.data], {type:'application/pdf'  });
            const url = window.URL.createObjectURL(blob);
            window.open(url);
        })
        .catch(error => {
            showMessage('Error viewing document. Please try again.');
            console.error(error);
        });
}

const update_rowH = (item) => {
    clearErrors();
    clientH.value.id = item.id;
    clientH.value.case_id = localStorage.getItem('caseId');
    clientH.value.court_id = item.court_id;
    clientH.value.hearing_date = item.hearing_date;
    clientH.value.notes = item.notes;
    clientH.value.outcome = item.outcome;
    clientH.value.hearing_type = item.hearing_type;
    modalH.value.title = "Update Hearing";
    modalH.value.button = "Update";
};

// Bug fix: the "Add Hearing"/"Add Document" card-header buttons had no
// click handler at all, just data-bs-toggle="modal" — so after editing
// a hearing (which sets modalH to "Update Hearing"/"Update" and fills
// clientH with that row's data), clicking "Add Hearing" reopened the
// exact same modal still showing "Update" and the previous row's data,
// not a blank form for a new one.
const openAddHearingModal = () => {
    clearErrors();
    clientH.value = {id: "",case_id:"",court_id : "", hearing_date:"",hearing_type:"", notes:"", outcome:""};
    modalH.value.title = "Add Hearing";
    modalH.value.button = "Add";
};

const openAddDocumentModal = () => {
    clearErrors();
    clientD.value = {id: "",title:"", case_id:"",file:null};
    selected_file.value = null;
    modalD.value.title = "Add Document";
    modalD.value.button = "Add";
};

const fetchData = () => {
    modalH.value.title = "Add Hearing";
    modalH.value.button = "Add";
    modalD.value.title = "Add Document";
    modalD.value.button = "Add";

    axios.get('/api/hearings')
        .then(response => {
            itemsH.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching hearings:', error);
            showMessage('Could not load hearings. Please try refreshing the page.', 'error');
        });

    axios.get('/api/hearingtypes')
        .then(response => {
            types.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching types:', error);
            showMessage('Could not load hearing types. Please try refreshing the page.', 'error');
        });

    // Bug fix: this previously fetched /api/expenses with no filter at
    // all, showing every expense in the entire firm on every case's page
    // regardless of which case was actually being viewed.
    axios.get('/api/expenses', { params: { case_id: localStorage.getItem('caseId') } })
        .then(response => {
            itemsE.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching expenses:', error);
            showMessage('Could not load case expenses. Please try refreshing the page.', 'error');
        });

    let caseId = localStorage.getItem('caseId');
    axios.get(`/api/documents/${caseId}`)
        .then(response => {
            itemsD.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching docs:', error);
            showMessage('Could not load case documents. Please try refreshing the page.', 'error');
        });

    axios.get(`/api/cases/${caseId}`)
        .then(response => {
            caseD.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching case:', error);
            showMessage('Could not load this case. Please try refreshing the page.', 'error');
        });
        
    axios.get('/api/courts')
        .then(response => {
            courts.value = response.data.data;
        })
        .catch(error => {
            console.error('Error fetching courts:', error);
            showMessage('Could not load courts. Please try refreshing the page.', 'error');
        });
};

onMounted(() => {
    fetchData();
});
</script>

<style lang="scss" scoped>
.modern-view-page {
    padding: 24px;
    background: #f8f9fa;
    min-height: 100vh;
}

/* Elevated Card Styles */
.elevated-card {
    background: white;
    border-radius: 12px;
    box-shadow: 6px 6px 20px rgba(0, 0, 0, 0.08), 
                3px 3px 10px rgba(0, 0, 0, 0.04);
    margin-bottom: 28px;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}

.elevated-card:hover {
    transform: translateY(-4px) translateX(-2px);
    box-shadow: 8px 8px 24px rgba(0, 0, 0, 0.12), 
                4px 4px 12px rgba(0, 0, 0, 0.06);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 28px 32px;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
}

.expenses-total-badge {
    font-size: 13px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(249, 115, 22, 0.1);
    color: #f97316;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}

.card-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    letter-spacing: -0.5px;
}

.case-badge {
    padding: 8px 20px;
    background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-800) 100%);
    color: white;
    border-radius: 24px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
}

.card-body {
    padding: 32px;
}

/* Info Grid Styles */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 0;
}

.info-value {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    padding: 14px 18px;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-radius: 10px;
    border-left: 4px solid var(--primary-600);
    min-height: 52px;
    display: flex;
    align-items: center;
    box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.2s;
}

.info-value:hover {
    box-shadow: 3px 3px 12px rgba(0, 0, 0, 0.08);
    transform: translateX(2px);
}

.description-value {
    min-height: 140px;
    align-items: flex-start;
    line-height: 1.7;
    white-space: pre-wrap;
}

.btn-add {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-800) 100%);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.4);
}

/* Table Styles */
.custom-table {
    margin-top: 16px;
}

/* Action Buttons */
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
    font-weight: 600;
    margin: 0 4px;
}

.btn-preview {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: white;
}

.btn-preview:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.btn-edit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-delete {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .modern-view-page {
        padding: 16px;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .card-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }

    .btn-add {
        width: 100%;
        justify-content: center;
    }

    .card-body {
        padding: 20px;
    }
}
</style>
