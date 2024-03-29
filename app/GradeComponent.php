<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GradeComponent extends Model
{
    protected $table = 'grade_components';
    protected $fillable = ['level', 'subject_id', 'description', 'percentage', 'created_at', 'updated_at', 'assessment_category_id', 'color'];

    public function assessment_category()
    {
    	return $this->belongsTo('\App\AssessmentCategory');
    }

    public function subject()
    {
    	return $this->belongsTo('\App\Subject');
    }
}
