<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\PostAddClassStudentFormRequest;
use App\Http\Controllers\Controller;
use App\ClassStudent;
use App\ClassSection;
use App\SchoolMember;
use App\User;
use Gate;

class ClassStudentController extends Controller
{
    public function getApi(Request $request)
    {
        if (Gate::denies('read-class-student'))
        {
            abort(401);
        }

        $class_student = ClassStudent::with('student.profile');

        if ($request->has('class_section_id'))
        {
            $class_student = $class_student->whereHas('class_section', function($query) use($request) {
                                $query->where('id', (int) $request->class_section_id);
                            });
        }

        if ($request->has('class_subject_id'))
        {
            $class_student = $class_student->whereHas('class_section.subject', function($query) use($request) {
                                $query->where('id', (int) $request->class_subject_id);
                            });
        }

        if ($request->has('school_id'))
        {
            $class_student = $class_student->whereHas('class_section', function($query) use($request) {
                                $query->where('school_id', (int) $request->school_id);
                            });
        }

        return $class_student->get();
    }

    public function postAdd(PostAddClassStudentFormRequest $request)
    {
        $msg = [];

        try
        {
            $data = $request->only('class_section_id');

            $user = User::where('username', $request->username);

            if ( ! $user->exists())
            {
                throw new \Exception(trans('user.not_found'));
            }

            $data['student_id'] = $user->pluck('id');
            $school_id = (int) ClassSection::firstOrFail((int) $data['class_section_id'])->school_id;

            if ( ! SchoolMember::where('user_id', $data['student_id'])->where('school_id', $school_id)->exists())
            {
                throw new \Exception(trans('class_student.not_member.error'));
            }

        	ClassStudent::create($data);

            $msg = trans('class_student.add.success');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors($e->getMessage());
        }

        return redirect()->back()->with(compact('msg'));
    }

    public function getDelete($id)
    {
        $msg = [];

        try
        {
            ClassStudent::findOrFail((int) $id)->delete();

            $msg = trans('class_student.delete.success');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors($e->getMessage());
        }

        return redirect()->back()->with(compact('msg'));
    }
}
