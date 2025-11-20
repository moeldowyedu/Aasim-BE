<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'project_manager_id' => ['nullable', 'uuid', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a project name.',
            'code.required' => 'Please enter a project code.',
            'organization_id.required' => 'Please select an organization.',
            'organization_id.exists' => 'The selected organization does not exist.',
            'end_date.after_or_equal' => 'End date must be equal to or after start date.',
            'budget.numeric' => 'Budget must be a valid number.',
            'budget.min' => 'Budget cannot be negative.',
            'status.in' => 'Please select a valid status.',
            'priority.in' => 'Please select a valid priority.',
        ];
    }
}
