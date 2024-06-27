<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInfoUserRequest extends FormRequest
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
            'first_name' => 'string|max:255|alpha|nullable',
            'last_name' => 'string|max:255|alpha|nullable',
            'location' => 'string|max:255|nullable',
            'profession' => 'string|max:255|nullable',
        ];
    }
}
