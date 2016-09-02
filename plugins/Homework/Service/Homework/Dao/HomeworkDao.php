<?php

namespace Homework\Service\Homework\Dao;

interface HomeworkDao
{
    public function getHomework($id);

    public function findHomeworksByCourseIdAndLessonIds($courseId, $lessonIds);
    
    public function findHomeworksByCourseId($courseId);
    
    public function findHomeworksByCreatedUserId($userId);
    
    public function getHomeworkByLessonId($lessonId);

    public function addHomework($fields);

    public function updateHomework($id,$fields);

    public function deleteHomework($id);
}