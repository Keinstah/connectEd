<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\PostAddClassSubjectExamFormRequest;
use App\Http\Requests\PostEditClassSubjectExamFormRequest;
use App\Http\Controllers\Controller;
use App\ClassSubjectExamUser;
use App\ClassSubjectExam;
use App\User;
use App\ExamQuestion;
use App\ExamQuestionAnswer;
use App\StudentExamQuestionAnswer;
use App\Assessment;
use App\Profile;
use App\ClassStudent;
use App\Exam;
use App\Notification;

class ClassSubjectExamController extends Controller
{
    public function getApi(Request $request)
    {
        $class_subject_exam = ClassSubjectExam::with('exam.assessment_category');

        if ($request->has('class_subject_id'))
        {
            $class_subject_exam = $class_subject_exam->where('class_subject_id', (int) $request->class_subject_id);
        }
        else
        if (strtolower(auth()->user()->group->name) == 'student')
        {
            $class_subject_exam = $class_subject_exam->whereHas('class_subject.class_section.student', function($query) {
                $query->where('student_id', auth()->user()->id);
            });
        }

        return $class_subject_exam->with('exam')->orderBy('created_at', 'desc')->get();
    }

    public function getEdit($id)
    {
        $class_subject_exam = ClassSubjectExam::findOrFail((int) $id);
        return view('class.exam.edit', compact('class_subject_exam'));
    }

    public function postEdit(PostEditClassSubjectExamFormRequest $request)
    {
        $msg = [];

        try
        {
        	$data = $request->only('quarter');
        	$data['start'] = $request->start_date .' '. $request->start_time;
        	$data['end'] = $request->end_date .' '. $request->end_time;

            ClassSubjectExam::findOrFail((int) $request->class_subject_exam_id)->update($data);

            $msg = trans('class_subject_exam.edit.success');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors($e->getMessage());
        }

        return redirect()->back()->with(compact('msg'));
    }

    public function getView($id)
    {
        $id = (int) $id;
        $class_subject_exam = ClassSubjectExam::with('exam.assessment_category')->findOrFail($id);

        if (strtolower($class_subject_exam->exam->assessment_category->name) == 'quarterly assessment' &&
            $class_subject_exam->exam->status != 1)
        {
            abort(404);
        }

        $users = Profile::whereHas('user.class_student.class_section.subject.class_subject_exam', function($query) use($id) {
                        return $query->where('id', $id);
                    })
                    ->whereNotIn('user_id', function($query) use($id) {
                        $query->select('user_id')
                                ->from('class_subject_exam_users')
                                ->whereRaw('class_subject_exam_users.class_subject_exam_id = '. $id);
                    })
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get();

        return view('class.exam.view', compact('class_subject_exam', 'users'));
    }

    public function getTake($id)
    {
        $class_subject_exam = ClassSubjectExam::with('exam.subject', 'exam.school', 'exam.author.profile')
                                ->whereHas('class_subject_exam_user', function($query) {
                                    $query->where('user_id', auth()->user()->id);
                                })
                                ->findOrFail($id);

        $user_questions_taken_count = auth()
                                ->user()
                                ->student_exam_question_answer()
                                ->whereHas('exam_question.exam', function($query) use($id) {
                                    $query->where('id', (int) $id);
                                })
                                ->groupBy('exam_question_id')
                                ->get()
                                ->count();
        $exam_questions_count = ExamQuestion::where('exam_id', $id)->groupBy('id')->get()->count();
        $grade = null;

        if ($exam_questions_count > $user_questions_taken_count)
        {
            $show_questions = true;
        }
        else
        {
            $show_questions = false;
            $grade = $this->getGrade($id);
        }

        return view('class.exam.take', compact('class_subject_exam', 'show_questions', 'grade'));
    }

    public function getQuestion($id)
    {
        return ExamQuestion::whereHas('exam.class_subject_exam.class_subject_exam_user', function($query) {
                                $query->where('user_id', auth()->user()->id);
                            })
                            ->whereHas('exam.class_subject_exam', function($query) {
                                $query->where('start', '<=', \DB::raw('NOW()'))
                                    ->where('end', '>=', \DB::raw('NOW()'));
                            })
                            ->has('student_exam_question_answer', '<', 1)
                            ->where('exam_id', (int) $id)
                            ->orderBy('category')
                            ->orderBy(\DB::raw('RAND()'))
                            ->first();
    }

    public function getGrade($exam_id, $student_id = null)
    {
        $data = [];

        if (is_null($student_id))
        {
            $student_id = auth()->user()->id;
        }

        $data['total'] = ExamQuestionAnswer::whereHas('exam_question.exam', function($query) use($exam_id) {
                                            $query->where('id', (int) $exam_id);
                                         })
                                        ->sum('points');
        $data['score'] = StudentExamQuestionAnswer::whereHas('exam_question.exam', function($query) use($exam_id) {
                                                    $query->where('id', (int) $exam_id);
                                                 })
                                                ->where('user_id', $student_id)
                                                ->sum('points');
        return $data;
    }

    public function postAnswer(Request $request)
    {
        $this->validate($request, [
            'answer'            => 'max:255',
            'timer'             => 'integer',
            'exam_question_id'  => 'required|exists:exam_questions,id'
        ]);

        $exam_question_id = (int) $request->exam_question_id;
        $class_subject_id = (int) $request->class_subject_id;
        $class_student_id = (int) $request->class_student_id;

        $student_answer = StudentExamQuestionAnswer::where(['user_id' => $request->user()->id, 'exam_question_id' => $exam_question_id])->exists();

        if ( ! $student_answer)
        {
            $answers = $request->answer;

            if (is_array($answers))
            {
                $data_answers = [];
                foreach ($answers as $answer)
                {
                    // get the points
                    $points = (int) ExamQuestionAnswer::where([
                                        'exam_question_id'  => $exam_question_id,
                                        'answer'            => $answer
                                    ])->pluck('points');

                    $data_answers[] = [
                        'answer'            => $answer,
                        'user_id'           => (int) $request->user()->id,
                        'time_answered'     => (int) $request->timer,
                        'exam_question_id'  => $exam_question_id,
                        'points'            => $points,
                        'created_at'        => new \DateTime
                    ];
                }

                StudentExamQuestionAnswer::insert($data_answers);
            }
            else
            {
                // get the points
                $points = (int) ExamQuestionAnswer::where([
                                    'exam_question_id'  => $exam_question_id,
                                    'answer'            => $request->answer
                                ])->pluck('points');

                StudentExamQuestionAnswer::create([
                    'answer'            => $answers,
                    'user_id'           => (int) $request->user()->id,
                    'time_answered'     => (int) $request->timer,
                    'exam_question_id'  => $exam_question_id,
                    'points'            => $points,
                    'created_at'        => new \DateTime
                ]);
            }

            $exam_question = ExamQuestion::with('exam.author')->findOrFail($exam_question_id);

            $user_questions_taken_count = auth()
                                ->user()
                                ->student_exam_question_answer()
                                ->whereHas('exam_question.exam', function($query) use($exam_question) {
                                    $query->where('id', $exam_question->exam_id);
                                })
                                ->groupBy('exam_question_id')
                                ->get()
                                ->count();
            $exam_questions_count = ExamQuestion::where('exam_id', $exam_question->exam_id)
                                                ->groupBy('id')
                                                ->get()
                                                ->count();

            if ($exam_questions_count >= $user_questions_taken_count)
            {
                $grade = $this->getGrade($exam_question->exam_id);
                $class_student = ClassStudent::where('student_id', auth()->user()->id)->orderBy('created_at', 'desc')->first();
                $assessment_category_id = Exam::findOrFail($exam_question->exam_id)->assessment_category_id;
                $data = [
                    'score'                 => $grade['score'],
                    'total'                 => $grade['total'],
                    'source'                => 'Examination',
                    'recorded'              => 1,
                    'class_student_id'      => $class_student->id,
                    'class_subject_exam_id' => (int) $request->class_subject_exam_id,
                    'class_subject_id'      => (int) $request->class_subject_id,
                    'date'                  => new \DateTime,
                    'assessment_category_id'=> $assessment_category_id,
                    'quarter'               => (int) $exam_question->exam->quarter
                ];

                Assessment::create($data);

                $target_id = $class_student->student->id;

                $data = [
                    'target_id'     => $target_id,
                    'subject'       => 'Examination Completed',
                    'content'       => 'Your grade for '. $exam_question->exam->title .' is '. $grade['score'] .'/'. $grade['total'] .' ('. ($grade['score']/$grade['total'])*100 .'%).',
                    'url'           => action('ClassSubjectExamController@getTake', $request->class_subject_exam_id)
                ];
                Notification::create($data);
            }

            return ["status" => "success"];
        }
        else
        {
            return response()->json(['msg' => 'You have already answered this question.'], 422);
        }
    }

    public function getAnswer($question_id)
    {
        $exam_question = ExamQuestion::where('id', $question_id)
                                    ->where(function($query) {
                                        $query->where('category', 'multiplechoice')
                                            ->orWhere('category', 'fillintheblank');
                                    });
        if ( ! $exam_question->exists())
        {
            return abort(404);
        }

        $exam_question = $exam_question->first();
        if ($exam_question->category == 'fillintheblank')
        {
            return ExamQuestionAnswer::where('exam_question_id', $question_id)
                                    ->count();
        }
        else
        {
            return ExamQuestionAnswer::where('exam_question_id', $question_id)
                                    ->get();
        }
    }

    public function postAdd(PostAddClassSubjectExamFormRequest $request)
    {
        $msg = [];

        try
        {
        	$data = $request->only('class_subject_id', 'quarter');
        	$data['start'] = $request->start_date .' '. $request->start_time;
        	$data['end'] = $request->end_date .' '. $request->end_time;
        	$data['exam_id'] = $request->exam;
            $data['created_at'] = new \DateTime;

            $exam = Exam::findOrFail($data['exam_id']);

            if (strtolower($exam->assessment_category->name) == 'quarterly assessment' && $exam->status != 1)
            {
                throw new \Exception(trans('class_subject_exam.verified.error'));
            }

            $class_subject_exam = ClassSubjectExam::create($data);

            $data = [];
            foreach ($request->users as $user)
            {
                $data[] = [
                    'user_id' => $user,
                    'class_subject_exam_id' => $class_subject_exam->id,
                    'created_at' => new \DateTime
                ];
            }

            ClassSubjectExamUser::insert($data);

            $msg = trans('class_subject_exam.add.success');
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
            ClassSubjectExam::findOrFail($id)->delete();

            $msg = trans('class_subject_exam.delete.success');
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors($e->getMessage());
        }

        return redirect()->back()->with(compact('msg'));
    }
}
