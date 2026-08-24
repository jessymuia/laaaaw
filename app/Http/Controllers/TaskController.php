<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Jobs\SendSmsJob;
use App\Mail\AssignedTask;
use App\Mail\ReassignedTask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_TASK)) {
            abort(403);
        }

        $query = Task::with('assignee')->orderBy('id', 'desc');

        if (! Auth::user()->checkPermissionTo('create-roles')) {
            $query->where('assigned_to', auth()->user()->id);
        }

        $tasks = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['title', 'due_date', 'priority'],
            ['title', 'description']
        );

        return $this->response(true, 'success', $tasks, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(Task $row): array
    {
        return [
            'id' => $row->id,
            'description' => $row->description,
            'title' => $row->title,
            'assigned_to' => $row->assigned_to,
            'advocate' => $row->assignee?->name ?? 'Unknown user',
            'due_date' => Carbon::parse($row->due_date)->format('d/m/Y'),
            'priority' => $row->priority,
            'status' => $row->task_status,
        ];
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
    public function store(Request $request): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_TASK)) {
            abort(403);
        }

        $request->validate([
            'description' => 'required|string',
            'title' => 'required|string|max:255',
            'assigned_to' => 'required|integer|exists:users,id',
            'due_date' => 'required|date_format:d/m/Y',
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:pending,in_progress,completed,overdue',
        ]);

        $task = Task::create([
            'description' => $request->description,
            'title' => $request->title,
            'assigned_to' => $request->assigned_to,
            'due_date' => Carbon::createFromFormat('d/m/Y', $request->due_date)->format('Y-m-d'),
            'priority' => $request->priority,
            'task_status' => $request->status,
        ]);

        if ($task) {
            $user = User::withTrashed()->find($request->assigned_to);

            if ($user) {
                SendSmsJob::dispatch($user->phone_number, "You have been assigned task:\nTitle : ".$task->title.".\nDescription : ".$task->description);
                if ($user->email) {
                    Mail::to($user->email)->queue(new AssignedTask($task));
                }
            }
        }

        return $this->response(true, 'success', $this->formatRow($task->fresh('assignee')), 201);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(Task $task)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Task  $task
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_TASK)) {
            abort(403);
        }

        $request->validate([
            'description' => 'required|string',
            'title' => 'required|string|max:255',
            'assigned_to' => 'required|integer|exists:users,id',
            'due_date' => 'required|date_format:d/m/Y',
            'priority' => 'required|in:high,medium,low',
            'status' => 'required|in:pending,in_progress,completed,overdue',
        ]);

        $task = Task::findOrFail($id);
        $orig = $task->getOriginal();
        $task->update([
            'description' => $request->description,
            'title' => $request->title,
            'assigned_to' => $request->assigned_to,
            'due_date' => Carbon::createFromFormat('d/m/Y', $request->due_date)->format('Y-m-d'),
            'priority' => $request->priority,
            'task_status' => $request->status,
            'updated_by' => auth()->user()->id,
        ]);

        if ($task && $orig['assigned_to'] != $task->assigned_to) {
            $newAssignee = User::withTrashed()->find($request->assigned_to);
            if ($newAssignee) {
                SendSmsJob::dispatch($newAssignee->phone_number, "You have been assigned task:\nTitle : ".$task->title.".\nDescription : ".$task->description);
                if ($newAssignee->email) {
                    Mail::to($newAssignee->email)->queue(new AssignedTask($task));
                }
            }

            $previousAssignee = User::withTrashed()->find($orig['assigned_to']);
            if ($previousAssignee) {
                SendSmsJob::dispatch($previousAssignee->phone_number, "Someone else has been assigned task:\nTitle : ".$task->title.".\n.Description : ".$task->description);
                if ($previousAssignee->email) {
                    Mail::to($previousAssignee->email)->queue(new ReassignedTask($task));
                }
            }
        }

        return $this->response(true, 'success', $this->formatRow($task->fresh('assignee')), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Task  $task
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_TASK)) {
            abort(403);
        }

        $task = Task::findOrFail($id);
        $task->deleted_by = auth()->user()->id;
        $task->save();

        $task->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }

    public function assigned(): JsonResponse
    {
        $query = Task::with('assignee')->where('assigned_to', Auth::user()->id)->orderBy('id', 'desc');

        $tasks = $this->paginatedOrFull(request(), $query, function ($row) {
            return [
                'id' => $row->id,
                'description' => $row->description,
                'title' => $row->title,
                'assigned_to' => $row->assigned_to,
                'advocate' => $row->assignee?->name ?? 'Unknown user',
                'due_date' => Carbon::parse($row->due_date)->format('d/m/Y'),
                'priority' => $row->priority,
                'status' => $row->status,
                'task_status' => $row->task_status,
            ];
        });

        return $this->response(true, 'success', $tasks, 200);
    }
}
