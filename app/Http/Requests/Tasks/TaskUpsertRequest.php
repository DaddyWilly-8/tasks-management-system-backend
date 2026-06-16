<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class TaskUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:pending,in_progress,completed'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'due_date' => ['required', 'date'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];

        return $rules;
    }
}
