<?php

namespace App\Http\Requests\Admin\Rooms;

use App\Enums\Permission;
use App\Enums\RoomBlockType;
use App\Services\Booking\StayPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::RoomBlocksManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(RoomBlockType::class)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'Blocks cannot start in the past.',
            'end_date.after' => 'The block must end after it starts.',
        ];
    }

    public function blockType(): RoomBlockType
    {
        return RoomBlockType::from($this->string('type')->toString());
    }

    public function period(): StayPeriod
    {
        return new StayPeriod($this->string('start_date')->toString(), $this->string('end_date')->toString());
    }
}
