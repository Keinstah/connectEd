<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyAssessmentsTable5 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assessments', function (Blueprint $t)
        {
            $t->boolean('recorded');
            $t->date('date');
            $t->dropColumn('student_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assessments', function (Blueprint $t)
        {
            $t->dropColumn('recorded', 'date');
            $t->integer('student_id');
        });
    }
}
