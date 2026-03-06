<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * Create a new ticket.
     * POST /api/v1/tickets
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'ticket_type' => 'required|string|in:ride,booking,payment,account,other',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'metadata' => 'nullable|array',
        ]);

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'ticket_type' => $validated['ticket_type'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'OPEN',
            'metadata' => $validated['metadata'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'ticket_type' => $ticket->ticket_type,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get all tickets (filtered by role).
     * GET /api/v1/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Officers and Admins can see all tickets, users see only their own
        if ($user->isSuperAdmin() || $user->role === 'ADMIN' || $user->role === 'OFFICER') {
            $query = Ticket::query();
        } else {
            $query = Ticket::where('user_id', $user->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('ticket_type')) {
            $query->where('ticket_type', $request->ticket_type);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets->map(fn($ticket) => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'ticket_type' => $ticket->ticket_type,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'last_page' => $tickets->lastPage(),
            ],
        ]);
    }

    /**
     * Get ticket details.
     * GET /api/v1/tickets/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = request()->user();

        $ticket = Ticket::findOrFail($id);

        // Check authorization
        if (!$user->isSuperAdmin() && 
            $user->role !== 'ADMIN' && 
            $user->role !== 'OFFICER' && 
            $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this ticket',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'ticket_type' => $ticket->ticket_type,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'metadata' => $ticket->metadata,
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'assigned_to' => $ticket->assignedTo ? [
                    'id' => $ticket->assignedTo->id,
                    'name' => $ticket->assignedTo->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Update a ticket.
     * PUT /api/v1/tickets/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $ticket = Ticket::findOrFail($id);

        // Check authorization - only admins/officers can update
        if (!$user->isSuperAdmin() && 
            $user->role !== 'ADMIN' && 
            $user->role !== 'OFFICER') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this ticket',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'ticket_type' => 'sometimes|string|in:ride,booking,payment,account,other',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'status' => 'sometimes|string|in:OPEN,IN_PROGRESS,RESOLVED,CLOSED',
            'assigned_to' => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $updateData = $validated;

        // If resolving, set resolved_at
        if (isset($validated['status']) && $validated['status'] === 'RESOLVED') {
            $updateData['resolved_at'] = now();
        }

        $ticket->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'data' => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'updated_at' => $ticket->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a ticket.
     * DELETE /api/v1/tickets/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = request()->user();

        $ticket = Ticket::findOrFail($id);

        // Check authorization
        if (!$user->isSuperAdmin() && 
            $user->role !== 'ADMIN' && 
            $ticket->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this ticket',
            ], 403);
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully',
        ]);
    }

    /**
     * Validate a ticket.
     * PUT /api/v1/tickets/{id}/validate
     */
    public function validate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Check authorization
        if (!$user->isSuperAdmin() && 
            $user->role !== 'ADMIN' && 
            $user->role !== 'OFFICER') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to validate tickets',
            ], 403);
        }

        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'is_valid' => 'required|boolean',
            'validation_notes' => 'nullable|string|max:500',
        ]);

        $ticket->update([
            'is_validated' => true,
            'validated_at' => now(),
            'validated_by' => $user->id,
            'validation_notes' => $validated['validation_notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['is_valid'] ? 'Ticket validated successfully' : 'Ticket marked as invalid',
            'data' => [
                'id' => $ticket->id,
                'is_validated' => $ticket->is_validated,
                'validated_at' => $ticket->validated_at?->toIso8601String(),
            ],
        ]);
    }
}
