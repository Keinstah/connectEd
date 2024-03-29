<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\PostAddClassSubjectExamUserFormRequest;
use App\Http\Controllers\Controller;
use App\ClassSubjectExamUser;
use App\ClassSubjectExam;
use App\ExamQuestionAnswer;
use App\StudentExamQuestionAnswer;

class ClassSubjectExamUserController extends Controller
{
    public function getApi(Request $request)
    {
        $class_subject_exam_user = ClassSubjectExamUser::select('assessments.score', 'assessments.total', 'class_subject_exam_users.*')
                ->with('user.profile', 'class_subject_exam')->orderBy('created_at', 'desc');

        if ($request->has('class_subject_exam_id'))
        {
            $class_subject_exam_user = $class_subject_exam_user->where('class_subject_exam_users.class_subject_exam_id', (int) $request->get('class_subject_exam_id'));
        }

        return $class_subject_exam_user
                        ->leftJoin('profiles', 'profiles.user_id', '=', 'class_subject_exam_users.user_id')
                        ->leftJoin('class_students', 'class_students.student_id', '=', 'class_subject_exam_users.user_id')
                        ->leftJoin('assessments', function($join) {
                            $join->on('assessments.class_student_id', '=', 'class_students.id');
                            $join->on('assessments.class_subject_exam_id', '=', 'class_subject_exam_users.class_subject_exam_id');
                        })
                        ->orderBy('profiles.last_name')
                        ->orderBy('profiles.first_name')
                        ->groupBy('profiles.user_id')
                        ->get();
    }

    public function getView($class_subject_exam_id, $user_id)
    {
        $class_subject_exam = ClassSubjectExam::with(['exam.question' => function($query) {
                                                        $query->orderBy('category');
                                                    },
                                                    'exam.question.answer',
                                                    'exam.question.student_exam_question_answer'])
                                    ->whereHas('exam.question.student_exam_question_answer', function($query) use($user_id) {
                                        $query->where('user_id', $user_id);
                                    })
                                    ->findOrFail($class_subject_exam_id);
        $grade = [];
        $grade['total'] = ExamQuestionAnswer::whereHas('exam_question.exam', function($query) use($class_subject_exam) {
                                            $query->where('id', (int) $class_subject_exam->exam_id);
                                         })
                                        ->sum('points');
        $grade['score'] = StudentExamQuestionAnswer::whereHas('exam_question.exam', function($query) use($class_subject_exam) {
                                                    $query->where('id', (int) $class_subject_exam->exam_id);
                                                 })
                                                ->where('user_id', $user_id)
                                                ->sum('points');
        return view('class.exam.user.view', compact('class_subject_exam', 'grade'));
    }

    public function postAdd(PostAddClassSubjectExamUserFormRequest $request)
    {
        $msg = [];

        try
        {
            $data = [];
            foreach ($request->users as $user)
            {
                $class_subject_exam_user = ClassSubjectExamUser::where('user_id', $user)
                                        ->where('class_subject_exam_id', (int) $request->class_subject_exam_id);

                if ($class_subject_exam_user->exists())
                {
                    throw new \Exception(trans('class_subject_exam_user.already_exists'));
                    break;
                }

                $data[] = [
                    'user_id' => $user,
                    'class_subject_exam_id' => (int) $request->class_subject_exam_id,
                    'created_at' => new \DateTime
                ];
            }

            ClassSubjectExamUser::insert($data);

            $msg = trans('class_subject_exam_user.add.success');
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
            ClassSubjectExamUser::findOrFail($id)->delete();

            $msg = trans('class_subject_exam_user.delete.success');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors($e->getMessage());
        }

        return redirect()->back()->with(compact('msg'));
    }
}
