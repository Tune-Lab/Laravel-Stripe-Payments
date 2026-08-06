<?php



namespace App\Http\Requests;

use App\ValueObjects\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation lives in the request object; the controller receives data it can trust.
 */
final class CreateCheckoutSessionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Minor units, not floats: the client sends 1050, meaning 10.50.
            'amount_minor_units' => ['required', 'integer', 'min:50', 'max:99999999'],
            'currency' => ['required', 'string', Rule::in(config('billing.supported_currencies'))],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount_minor_units.min' => 'The minimum charge is 0.50 in the selected currency.',
        ];
    }

    public function money(): Money
    {
        return Money::fromMinorUnits(
            minorUnits: $this->integer('amount_minor_units'),
            currency: $this->string('currency')->value(),
        );
    }

    public function description(): string
    {
        return $this->string('description')->value();
    }
}
