<?php

namespace App\Http\Requests\Event;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(EventType::values())],
            'status' => ['nullable', 'string', Rule::in([
                EventStatus::Scheduled->value,
            ])],
            'location' => ['nullable', 'string', 'max:255'],
            'is_online' => ['boolean'],
            'online_url' => ['nullable', 'url', 'max:1024'],
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'all_day' => ['boolean'],
            'reminder_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'organizer_user_id' => ['required', 'integer', 'exists:users,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.after_or_equal' => 'Thời điểm bắt đầu không được ở quá khứ.',
            'ends_at.after' => 'Thời điểm kết thúc phải sau thời điểm bắt đầu.',
            'reminder_at.before_or_equal' => 'Thời điểm nhắc phải trước hoặc bằng lúc bắt đầu.',
            'title.min' => 'Tiêu đề phải có ít nhất 3 ký tự.',
            'attendee_ids.*.distinct' => 'Mỗi người tham gia chỉ được chọn một lần.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_online' => $this->boolean('is_online'),
            'all_day' => $this->boolean('all_day'),
        ]);
    }
}
