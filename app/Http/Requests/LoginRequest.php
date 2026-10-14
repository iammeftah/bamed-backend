<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $p = preg_replace('/[\s\-().]/', '', (string) $this->input('phone'));

        if (str_starts_with($p, '00212')) {
            $p = '+' . substr($p, 2);
        } elseif (str_starts_with($p, '212')) {
            $p = '+' . $p;
        } elseif (preg_match('/^0[5-7]\d{8}$/', $p)) {
            $p = '+212' . substr($p, 1);
        }

        $this->merge(['phone' => $p]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+212[5-7]\d{8}$/'],
            'password' => ['required', 'string'],
        ];
    }
}