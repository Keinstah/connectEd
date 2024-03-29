<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use Gate;

class PostSaveSettingsPasswordFormRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return ! Gate::denies('password-settings');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'npassword' => 'required|confirmed',
            'cpassword' => 'required'
        ];
    }
}
