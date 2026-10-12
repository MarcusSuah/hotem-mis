<?php

namespace App\Http\Requests\Admin\Operations;

use Illuminate\Contracts\Validation\ValidationRule;

class CompleteTaskRequest extends WorkTaskRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'checklist' => ['present', 'array'],
            'checklist.*.item' => ['required', 'string', 'max:255'],
            'checklist.*.done' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<array{item: string, done: bool}>
     */
    public function checklist(): array
    {
        return array_values(array_map(
            fn (array $line): array => ['item' => (string) $line['item'], 'done' => filter_var($line['done'], FILTER_VALIDATE_BOOLEAN)],
            (array) $this->validated('checklist', []),
        ));
    }
}
