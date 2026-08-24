<?php

use App\Http\Controllers\APIController;
use App\Http\Controllers\AttorneyController;
use App\Http\Controllers\CasesController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\CourtTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HearingController;
use App\Http\Controllers\HearingTypeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RolesManagementController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TrustTransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('/login', [APIController::class, 'login']);
Route::post('/password-recovery', [APIController::class, 'passwordRecovery']);
Route::post('/password-reset', [APIController::class, 'reset_pass']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [APIController::class, 'logout']);

    // Dashboard
    Route::resource('dashboard', DashboardController::class);

    Route::resource('clients', ClientController::class);
    Route::get('/clientsDropDown', [ClientController::class, 'clientsDropDown']);
    Route::get('/advocates', [AttorneyController::class, 'getAdvocateUsers']);

    Route::resource('users', UserController::class);
    Route::get('/rolesDropDown', [UserController::class, 'getRolesDropdown']);

    Route::resource('courttypes', CourtTypeController::class);
    Route::resource('courts', CourtController::class);

    Route::resource('cases', CasesController::class);
    Route::get('casesDropDown', [CasesController::class, 'dropDown']);
    Route::put('/cases/{id}/status', [CasesController::class, 'transitionStatus']);

    Route::resource('tasks', TaskController::class);
    Route::resource('expenses', ExpenseController::class);
    Route::resource('expense-categories', ExpenseCategoryController::class);
    Route::resource('hearings', HearingController::class);
    Route::resource('hearingtypes', HearingTypeController::class);
    Route::resource('invoices', InvoiceController::class);
    Route::put('/send-to-admin/{id}', [InvoiceController::class, 'sendToAdmin']);
    Route::resource('invoiceItems', InvoiceItemController::class);
    Route::get('/preview-invoice/{id}', [InvoiceController::class, 'preview']);

    Route::resource('documents', DocumentController::class);
    Route::get('/documents/{groupId}/versions', [DocumentController::class, 'versions']);

    Route::get('/preview', [CasesController::class, 'preview']);

    // FUN-2: time tracking, payments/receipts, trust accounting
    Route::resource('time-entries', TimeEntryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/time-entries/generate-invoice', [TimeEntryController::class, 'generateInvoice']);

    Route::resource('payments', PaymentController::class)->only(['index', 'store', 'show', 'destroy']);

    Route::get('/trust-transactions', [TrustTransactionController::class, 'index']);
    Route::post('/trust-transactions', [TrustTransactionController::class, 'store']);
    Route::put('/trust-transactions/{id}/void', [TrustTransactionController::class, 'void']);

    // FUN-7: global search
    Route::get('/search', [SearchController::class, 'index']);

    // Self-service account settings — any authenticated user, no special
    // permission required (this only ever touches the requester's own
    // record via Auth::user(), never another user's).
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // FUN-4: server-side export / reporting
    Route::get('/export/cases', [ExportController::class, 'cases']);
    Route::get('/export/expenses', [ExportController::class, 'expenses']);
    Route::get('/export/invoices', [ExportController::class, 'invoices']);
    Route::get('/export/invoices/{id}/pdf', [ExportController::class, 'invoicePdf']);
    Route::get('/export/firm-data', [ExportController::class, 'fullFirmExport']);

    // Roles and Permissions
    Route::resource('roles', RolesManagementController::class);
    Route::get('/permissions', [RolesManagementController::class, 'allPermissions']);
    Route::get('/permissions/{name}', [RolesManagementController::class, 'getRolePermissions']);

});
