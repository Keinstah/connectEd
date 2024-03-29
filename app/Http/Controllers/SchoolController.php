<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\PostEditSchoolFormRequest;
use App\Http\Controllers\Controller;
use App\School;
use App\SchoolMember;

class SchoolController extends Controller
{
    public function getData(Request $request)
    {
        $data = [];

        $school = School::with('class_section', 'exam', 'lesson');

        if ($request->has('school_id'))
        {
            $school = $school->findOrFail((int) $request->school_id);
        }

        $data['labels'] = [
            'Teachers',
            'Students',
            'Sections',
            'Exams',
            'Lessons'
        ];

        $data['datasets'][0] = [
                'label' => 'Teacher', 
                'fillColor' => 'rgba(96, 179, 255, 0.5)',
                'strokeColor' => 'rgba(16, 127, 190, 0.8)',
                'highlightFill' => 'rgba(66, 163, 224, 0.75)',
                'highlightStroke' => 'rgba(76, 171, 240, 1)'
        ];
        $section = number_format($school->class_section->count());
        $exam = number_format($school->exam->count());
        $lesson = number_format($school->lesson->count());

        $data['datasets'][0]['data'] = [
            number_format($school->whereHas('member.user.group', function($query) {
                $query->where('name', 'Teacher');
            })->count()),
            number_format($school->whereHas('member.user.group', function($query) {
                $query->where('name', 'Student');
            })->count()),
            $section,
            $exam,
            $lesson
        ];

        return $data;
    }

    public function getView($id)
    {
    	$school = School::with('member', 'class_section')->findOrFail((int) $id);

    	return view('school.index', compact('school'));
    }

    public function getEdit($id)
    {
    	$school = School::findOrFail((int) $id);

    	if ( ! SchoolMember::where(['user_id' => auth()->user()->id, 'school_id' => $id])->exists() ||
    		strtolower(auth()->user()->group->name) != 'school admin')
    	{
    		return abort(401);
    	}

    	return view('school.edit', compact('school'));
    }

    public function postEdit(PostEditSchoolFormRequest $request)
    {
        $msg = [];

        try
        {
            $data = $request->only('name', 'address', 'description', 'website', 'contact_no', 'mission', 'vision', 'motto', 'goal');
            School::findOrFail((int) $request->school_id)->update($data);

            $msg = trans('school.edit.success');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors($msg);
        }

        return redirect()->back()->with(compact('msg'));
    }
}
