<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueryUserActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view-activities');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'uuid', 'exists:users,id'],
            'organization_id' => ['sometimes', 'uuid', 'exists:organizations,id'],
            'activity_type' => ['sometimes', 'string', 'in:login,logout,api_call,create,update,delete,view,export'],
            'action' => ['sometimes', 'string', 'in:create,read,update,delete,execute'],
            'entity_type' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:success,failure,pending'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'requires_audit' => ['sometimes', 'boolean'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'Invalid user',
            'organization_id.exists' => 'Invalid organization',
            'date_to.after_or_equal' => 'End date must be after or equal to start date',
            'per_page.max' => 'Maximum 100 records per page',
        ];
    }
}
