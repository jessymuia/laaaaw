<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Models\Cases;
use App\Models\Client;
use App\Models\Hearing;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * UI-11: firm-specific KPIs, replacing the generic template dashboard
     * (visitor/traffic charts) with what a law firm's staff actually need
     * at a glance: hearings in the next 7 days, tasks due, unbilled
     * (billable, not-yet-invoiced) work, outstanding invoice balances, and
     * recent case activity.
     *
     * Every scoped section respects the same visibility a user already
     * has via their own module permissions/list scoping — the dashboard
     * shows a personalized summary, not a firm-wide report (that's
     * FUN-4's admin-only export).
     */
    public function index(): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::VIEW_DASHBOARD)) {
            abort(403);
        }

        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $today = Carbon::today();

        $stats = [
            'total_clients' => Client::count(),
            'total_cases' => Cases::count(),
            'open_cases' => Cases::where('lifecycle_status', 'open')->count(),
        ];

        $upcomingHearings = Hearing::with(['case', 'court'])
            ->whereBetween('hearing_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->orderBy('hearing_date')
            ->limit(10)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'case' => $h->case?->case_number ?? 'Unknown case',
                'court' => $h->court?->name ?? 'Unknown court',
                'date' => Carbon::parse($h->hearing_date)->format('d/m/Y'),
                'days_away' => $today->diffInDays(Carbon::parse($h->hearing_date), false),
            ]);

        $tasksQuery = Task::with('assignee')->where('task_status', '!=', 'completed');
        if (! $isAdmin) {
            $tasksQuery->where('assigned_to', $user->id);
        }
        $tasksDue = $tasksQuery->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'assignee' => $t->assignee?->name ?? 'Unassigned',
                'due_date' => Carbon::parse($t->due_date)->format('d/m/Y'),
                'overdue' => Carbon::parse($t->due_date)->isPast(),
                'priority' => $t->priority,
            ]);

        $unbilledQuery = TimeEntry::with(['case', 'user'])
            ->where('billable', true)
            ->where('billed', false);
        if (! $isAdmin) {
            $unbilledQuery->where('user_id', $user->id);
        }
        $unbilledTotal = (clone $unbilledQuery)->get()->sum('amount');
        $unbilledByCase = (clone $unbilledQuery)->get()
            ->groupBy('case_id')
            ->map(fn ($entries, $caseId) => [
                'case_id' => $caseId,
                'case' => $entries->first()->case?->case_number ?? 'Unknown case',
                'hours' => $entries->sum('hours'),
                'amount' => $entries->sum('amount'),
            ])
            ->values()
            ->take(10);

        $invoicesQuery = Invoice::with('client')->where('workflow_status', 'submitted');
        if (! $isAdmin) {
            $invoicesQuery->where('created_by', $user->id);
        }
        $outstandingInvoices = $invoicesQuery->get()
            ->filter(fn ($inv) => $inv->total_amount > $inv->amount_paid)
            ->sortByDesc(fn ($inv) => $inv->total_amount - $inv->amount_paid)
            ->take(10)
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'client' => $inv->client?->name ?? 'Unknown client',
                'outstanding' => $inv->total_amount - $inv->amount_paid,
                'due_date' => Carbon::parse($inv->invoice_due_date)->format('d/m/Y'),
                'overdue' => Carbon::parse($inv->invoice_due_date)->isPast(),
            ]);
        $outstandingTotal = $outstandingInvoices->sum('outstanding');

        $recentCases = Cases::with('client')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'case_number' => $c->case_number,
                'client' => $c->client?->name ?? 'Unknown client',
                'status' => $c->lifecycle_status,
                'updated_at' => $c->updated_at->diffForHumans(),
            ]);

        return $this->response(true, 'success', [
            'stats' => $stats,
            'upcoming_hearings' => $upcomingHearings,
            'tasks_due' => $tasksDue,
            'unbilled_total' => round($unbilledTotal, 2),
            'unbilled_by_case' => $unbilledByCase,
            'outstanding_invoices' => $outstandingInvoices->values(),
            'outstanding_total' => round($outstandingTotal, 2),
            'recent_cases' => $recentCases,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
