<?php

namespace App\Http\Requests\AuthRequest;

use Illuminate\Validation\Rules;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'username' => 'required|min:6|max:25|max:255|unique:users|regex:/(^[a-zA-Z]+[a-zA-Z0-9\\-]*$)/u',
            'email' => 'required|string|unique:users|email:rfc,dns,filter|max:255',
            'password' => ['required', 'confirmed', Rules\Password::default(), 'max:255']
        ];
    }
}
