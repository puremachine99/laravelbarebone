<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'secondary_email' => $this->secondaryEmailRules($userId),
            'username' => $this->usernameRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email:rfc,strict',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate the user's secondary (company) email.
     * Optional, but must be a @smartid.co.id address when provided.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function secondaryEmailRules(?int $userId = null): array
    {
        return [
            'nullable',
            'string',
            'email:rfc,strict',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! Str::endsWith(Str::lower($value), '@smartid.co.id')) {
                    $fail(__('The :attribute must be a company (@smartid.co.id) email address.'));
                }
            },
            $userId === null
                ? Rule::unique(User::class, 'secondary_email')
                : Rule::unique(User::class, 'secondary_email')->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate the user's username.
     * Optional, but must be unique and alphanumeric (no symbols) when provided.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?int $userId = null): array
    {
        return [
            'nullable',
            'string',
            'min:3',
            'max:30',
            'regex:/^[A-Za-z0-9]+$/',
            $userId === null
                ? Rule::unique(User::class, 'username')
                : Rule::unique(User::class, 'username')->ignore($userId),
        ];
    }
}
