<?php

namespace Homework\Service\Homework\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Homework\Service\Homework\Dao\ExerciseItemDao;

class ExerciseItemDaoImpl extends BaseDao implements ExerciseItemDao
{
    protected $table = 'exercise_item';

    public function getItem($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return  $this->getConnection()->fetchAssoc($sql,array($id)) ? : array();
    }

    public function addItem($items)
    {
        $affect = $this->getConnection()->insert($this->table,$items);
        if ($affect <= 0) {
            throw $this->createDaoException('insert exerciseItem error!');
        }
        return $this->getItem($this->getConnection()->lastInsertId());
    }

    public function deleteItem($id)
    {
        return $this->getConnection()->delete($this->table,array('id'=>$id));
    }
    
    public function deleteItemByExerciseId($exerciseId)
    {
        return $this->getConnection()->delete($this->table,array('exerciseId'=>$exerciseId));
    }

    public function findItemsByExerciseId($exerciseId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE exerciseId = ?";
        return $this->getConnection()->fetchAll($sql,array($exerciseId)) ? : array();
    }
}