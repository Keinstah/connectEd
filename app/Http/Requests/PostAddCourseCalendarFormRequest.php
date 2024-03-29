<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class PostAddCourseCalendarFormRequest extends Request
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
            'title'     => 'required|max:255',
            'description' => 'max:1000',
            'date_from' => 'required|date',
            'date_to' => 'date'
        ];
    }
}
