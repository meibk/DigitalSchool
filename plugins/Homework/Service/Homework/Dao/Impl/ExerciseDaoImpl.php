<?php

namespace Homework\Service\Homework\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Homework\Service\Homework\Dao\ExerciseDao;

class ExerciseDaoImpl extends BaseDao implements ExerciseDao
{
    protected $table = 'exercise';

    public function getExercise($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function getExerciseByLessonId($lessonId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE lessonId = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($lessonId)) ? : null;
    }

    public function findExercisesByCourseId($courseId)
    {
        if (empty($courseId)) {
            return array();
        }

        $sql = "SELECT * FROM {$this->table} WHERE courseId = ?";
        return $this->getConnection()->fetchAll($sql,array($courseId)) ? : array();
    }

    public function findExercisesByLessonId($lessonId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE lessonId = ? ";
        return $this->getConnection()->fetchAll($sql, array($lessonId)) ? : array();
    }

    public function addExercise($fields)
    {      
        $affected = $this->getConnection()->insert($this->table, $fields);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert exercise error.');
        }
        return $this->getExercise($this->getConnection()->lastInsertId());
    }

    public function deleteExercise($id)
    {
        return $this->getConnection()->delete($this->table, array('id' => $id));
    }

    public function deleteExercisesByLessonId($lessonId)
    {
        return $this->getConnection()->delete($this->table, array('lessonId' => $lessonId));
    }
    
    public function updateExercise($id, $fields)
    {   
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getExercise($id);
    }

    public function findExercisesByLessonIds($lessonIds)
    {   
        if(empty($lessonIds)){
            return array();
        }
        $marks = str_repeat('?,', count($lessonIds) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE lessonId IN ({$marks});";
        
        return $this->getConnection()->fetchAll($sql, $lessonIds);
    }

}