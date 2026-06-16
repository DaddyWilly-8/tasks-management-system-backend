<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\TaskUpsertRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,in_progress,completed'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'in:title,priority,due_date,created_at,status'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $tasks = Task::query()
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            })
            ->when(isset($validated['status']), function ($query) use ($validated) {
                $query->where('status', $validated['status']);
            })
            ->when(isset($validated['priority']), function ($query) use ($validated) {
                $query->where('priority', $validated['priority']);
            })
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $search = '%' . $validated['search'] . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                            $creatorQuery->where('name', 'like', $search);
                        })
                        ->orWhereHas('assignee', function ($assigneeQuery) use ($search) {
                            $assigneeQuery->where('name', 'like', $search);
                        });
                });
            })
            ->orderBy($sortBy, $sortOrder)
            ->with(['creator:id,name,email', 'assignee:id,name,email'])
            ->paginate($perPage);

        return response()->json($tasks);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $task = Task::query()
            ->where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            })
            ->with(['creator:id,name,email', 'assignee:id,name,email'])
            ->first();

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], 404);
        }

        return response()->json($task);
    }

    public function store(TaskUpsertRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $task = Task::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'priority' => $validated['priority'] ?? 'medium',
            'due_date' => $validated['due_date'],
            'created_by' => $user->id,
            'assigned_to' => $validated['assigned_to'],
        ])->load(['creator:id,name,email', 'assignee:id,name,email']);

        return response()->json([
            'message' => 'Task created successfully',
        ], 201);
    }
    
    public function update(TaskUpsertRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $task = Task::query()
            ->where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            })
            ->first();

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], 404);
        }

        if ((int) $task->created_by !== (int) $user->id) {
            return response()->json([
                'message' => 'Only the task creator can update this task.',
            ], 403);
        }

        $validated = $request->validated();

        $task->update($validated);
        $task->load(['creator:id,name,email', 'assignee:id,name,email']);

        return response()->json([
            'message' => 'Task updated successfully',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $task = Task::query()
            ->where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            })
            ->first();

        if (!$task) {
            return response()->json([
                'message' => 'Task not found',
            ], 404);
        }

        if ((int) $task->created_by !== (int) $user->id) {
            return response()->json([
                'message' => 'Only the task creator can delete this task.',
            ], 403);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully',
        ]);
    }
}
