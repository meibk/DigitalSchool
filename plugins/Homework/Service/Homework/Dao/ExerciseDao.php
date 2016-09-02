<?php

namespace Homework\Service\Homework\Dao;

interface ExerciseDao
{

    public function getExercise($id);

    public function getExerciseByLessonId($lessonId);
    
    public function findExercisesByCourseId($courseId);

    public function findExercisesByLessonId($lessonId);

    public function addExercise($fields);

    public function updateExercise($id, $fields);

    public function deleteExercise($id);

    public function deleteExercisesByLessonId($lessonId);

    public function findExercisesByLessonIds($lessonIds);

}