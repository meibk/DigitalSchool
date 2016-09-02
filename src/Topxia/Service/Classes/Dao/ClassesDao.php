<?php

namespace Topxia\Service\Classes\Dao;

interface ClassesDao
{
    const TABLENAME = 'class';

    public function getClass($id);

    public function getClassByGradeAndName($gradeId, $className);

    public function findClassesByIds(array $ids);
  
    public function findClassesByHeadTeacherId($headTeacherId);  

    public function searchClasses($conditions, $orderBy, $start, $limit);

    public function searchClassCount($conditions);

    public function createClass($class);

    public function updateClass($id, $fields);

    public function updateClassStudentNum($num,$id);

    public function deleteClass($id);

}