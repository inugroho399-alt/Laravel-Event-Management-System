<?php

namespace App\Http\Requests;

use App\Enums\RegistrationStatus;
use App\Models\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateTicketTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var TicketType $ticket */
        $ticket = $this->route('ticket');

        return Gate::allows('update', $ticket);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'quota' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var TicketType $ticket */
            $ticket = $this->route('ticket');

            if ($ticket) {
                $registeredCount = $ticket->registrations()
                    ->where('status', '!=', RegistrationStatus::Cancelled->value)
                    ->count();

                $newQuota = (int) $this->input('quota');

                if ($newQuota < $registeredCount) {
                    $validator->errors()->add(
                        'quota',
                        "The quota cannot be less than the {$registeredCount} tickets already registered."
                    );
                }
            }
        });
    }
}
