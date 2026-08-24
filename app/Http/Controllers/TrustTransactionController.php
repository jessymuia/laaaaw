<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\TrustTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TrustTransactionController extends Controller
{
    /**
     * Ledger for a single client, most recent first, plus the current
     * balance. A client_id is required — trust funds are always tracked
     * per client, never as one firm-wide pool.
     */
    public function index(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_TRUST_TRANSACTIONS)) {
            abort(403);
        }

        $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $query = TrustTransaction::with(['case', 'recordedBy'])
            ->where('client_id', $request->client_id)
            ->orderBy('id', 'desc');

        $transactions = $this->paginatedOrFull($request, $query, function ($row) {
            return [
                'id' => $row->id,
                'type' => $row->type,
                'amount' => $row->amount,
                'balance_after' => $row->balance_after,
                'description' => $row->description,
                'reference_number' => $row->reference_number,
                'case' => $row->case?->case_number,
                'recorded_by' => $row->recordedBy?->name ?? 'Unknown user',
                'voided' => ! is_null($row->voided_at),
                'date' => $row->created_at->format('d/m/Y H:i'),
            ];
        });

        return $this->response(true, 'success', [
            'balance' => TrustTransaction::balanceForClient($request->client_id),
            'transactions' => $transactions,
        ], 200);
    }

    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_TRUST_TRANSACTIONS)) {
            abort(403);
        }

        $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'case_id' => 'nullable|integer|exists:cases,id',
            'type' => 'required|in:deposit,disbursement',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reference_number' => 'nullable|string|max:255',
        ]);

        try {
            $transaction = TrustTransaction::post(
                $request->client_id,
                $request->case_id,
                $request->type,
                (float) $request->amount,
                $request->description,
                $request->reference_number,
                auth()->user()->id
            );
        } catch (\RuntimeException $e) {
            return $this->response(false, $e->getMessage(), null, 422);
        }

        return $this->response(true, 'success', ['balance_after' => $transaction->balance_after], 200);
    }

    /**
     * Void a transaction. This never edits or deletes the original entry —
     * it stamps voided_at/voided_by so the entry is excluded from balance
     * calculations going forward, while the audit trail stays intact.
     * Voiding a deposit is only permitted while the client's balance can
     * still cover it (i.e. the funds haven't already been disbursed).
     */
    public function void(Request $request, $id): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::VOID_TRUST_TRANSACTIONS)) {
            abort(403);
        }

        $transaction = TrustTransaction::findOrFail($id);

        if ($transaction->voided_at) {
            return $this->response(false, 'Transaction is already voided', null, 422);
        }

        if ($transaction->type === 'deposit') {
            $balanceExcludingThis = TrustTransaction::balanceForClient($transaction->client_id) - $transaction->amount;
            if ($balanceExcludingThis < 0) {
                return $this->response(false, 'Cannot void this deposit: funds have already been disbursed', null, 422);
            }
        }

        $transaction->voided_at = now();
        $transaction->voided_by = auth()->user()->id;
        $transaction->updated_by = auth()->user()->id;
        $transaction->save();

        return $this->response(true, 'success', [
            'balance' => TrustTransaction::balanceForClient($transaction->client_id),
        ], 200);
    }
}
