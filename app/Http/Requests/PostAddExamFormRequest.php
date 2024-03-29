<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use Gate;

class PostAddExamFormRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('create-exam');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title'         => 'required|max:255',
            'assessment_category_id'     => 'required|exists:assessment_categories,id',
            'subject'       => 'required|exists:subjects,id',
            'exam_type_id'  => 'required|exists:exam_types,id'
        ];
    }
}
