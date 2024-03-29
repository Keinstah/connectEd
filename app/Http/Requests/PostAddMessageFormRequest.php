<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class PostAddMessageFormRequest extends Request
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
            'content'       => 'required|max:1000',
            'to_id'         => 'required|exists:users,id'
        ];
    }
}
