<?php

namespace App\Http\Controllers;

use App\Constants\ModulePermissions;
use App\Jobs\SendSmsJob;
use App\Models\Cases;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response|JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_CLIENTS)) {
            abort(403);
        }

        $query = Client::with('advocate')->orderBy('id', 'desc');

        $clients = $this->paginatedOrFull(
            request(),
            $query,
            [$this, 'formatRow'],
            25,
            ['name', 'phone_number', 'address'],
            ['name', 'phone_number', 'address']
        );

        return $this->response(true, 'success', $clients, 200);
    }

    /**
     * ENG-4: shared row formatter, see CasesController::formatRow for why.
     */
    public function formatRow(Client $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'phone_number' => $row->phone_number,
            'extra_phone_number' => $row->extra_phone_number,
            'address' => $row->address,
            'advocate' => $row->advocate?->name ?? 'Unknown user',
            'advocate_id' => $row->advocate_id,
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
        if (! Auth::user()->checkPermissionTo(ModulePermissions::CREATE_CLIENTS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20|unique:clients,phone_number,NULL,id,deleted_at,NULL',
            'extra_phone_number' => 'nullable|string|max:20',
            'address' => 'required|string|max:255',
            'advocate' => 'required|integer|exists:users,id',
        ]);

        try {
            $client = Client::create([
                'name' => $request->name,
                'phone_number' => $request->phone_number,
                'extra_phone_number' => $request->extra_phone_number ?? null,
                'address' => $request->address,
                'advocate_id' => $request->advocate,
            ]);

            SendSmsJob::dispatch($request->phone_number, 'Welcome. You have been registered to the system');

            return $this->response(true, 'success', $this->formatRow($client->load('advocate')), 201);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage().
                ' '.$exception->getLine().
                ' '.$exception->getFile().
                ' '.$exception->getTraceAsString()
            );

            return $this->response(false, 'An error occurred', [], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  Client  $client
     * @return Response
     */
    public function show($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_CLIENTS)) {
            abort(403);
        }

        $client = Client::with('advocate')->findOrFail($id);

        $cases = Cases::where('client_id', $client->id)
            ->orderByDesc('id')
            ->get(['id', 'case_number', 'description', 'lifecycle_status', 'created_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'case_number' => $c->case_number,
                'title' => $c->case_number,
                'description' => $c->description,
                'status' => $c->lifecycle_status,
                'created_at' => $c->created_at,
            ]);

        return $this->response(true, 'success', [
            'id' => $client->id,
            'name' => $client->name,
            'phone_number' => $client->phone_number,
            'extra_phone_number' => $client->extra_phone_number,
            'address' => $client->address,
            'advocate' => $client->advocate?->name ?? 'Unknown user',
            'advocate_id' => $client->advocate_id,
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
            'cases' => $cases,
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(Client $client)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Client  $client
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::UPDATE_CLIENTS)) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20|unique:clients,phone_number,'.$id.',id,deleted_at,NULL',
            'extra_phone_number' => 'nullable|string|max:20',
            'address' => 'required|string|max:255',
            'advocate' => 'required|integer|exists:users,id',
        ]);

        $client = Client::findOrFail($id);
        $client->update([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'extra_phone_number' => $request->extra_phone_number,
            'address' => $request->address,
            'advocate_id' => $request->advocate,
            'updated_by' => auth()->user()->id,
        ]);

        return $this->response(true, 'success', $this->formatRow($client->fresh('advocate')), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Client  $client
     * @return Response
     */
    public function destroy($id)
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::DELETE_CLIENTS)) {
            abort(403);
        }

        $client = Client::findOrFail($id);
        $client->deleted_by = auth()->user()->id;
        $client->save();

        $client->delete();

        return $this->response(true, 'success', ['id' => (int) $id], 200);
    }

    public function clientsDropDown(): JsonResponse
    {
        if (! Auth::user()->checkPermissionTo(ModulePermissions::LIST_CLIENTS)) {
            abort(403);
        }

        $clients = Client::all();
        $clients = $clients->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
            ];
        });

        return $this->response(true, 'success', $clients, 200);
    }
}
