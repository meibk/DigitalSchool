<?php 

namespace Homework\Service\Homework\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Homework\Service\Homework\Dao\HomeworkDao;

class HomeworkDaoImpl extends BaseDao Implements HomeworkDao
{
	protected $table = 'homework';

	public function getHomework($id)
	{
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->getConnection()->fetchAssoc($sql,array($id)) ? : null;
	}

    public function findHomeworksByCourseId($courseId)
    {
        if (empty($courseId)) {
            return array();
        }

        $sql = "SELECT * FROM {$this->table} WHERE courseId = ?";
        return $this->getConnection()->fetchAll($sql,array($courseId)) ? : array();
    }

    public function getHomeworkByLessonId($lessonId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE lessonId = ? Limit 1";
        return $this->getConnection()->fetchAssoc($sql, array($lessonId)) ? : null;
    }

    public function findHomeworksByCourseIdAndLessonIds($courseId, $lessonIds)
    {
        if(empty($lessonIds)){
            return array();
        }
        
        $marks = str_repeat('?,', count($lessonIds) - 1) . '?';

        $sql ="SELECT * FROM {$this->table} WHERE courseId = {$courseId} AND lessonId IN ({$marks});";
        
        return $this->getConnection()->fetchAll($sql, $lessonIds);
    }

    public function findHomeworksByCreatedUserId($userId)
    {
        if (empty($userId)) {
            return array();
        }

        $sql = "SELECT * FROM {$this->table} WHERE createdUserId = ?";
        return $this->getConnection()->fetchAll($sql,array($userId)) ? : array();
    }

	public function addHomework($fields)
	{
		$affect = $this->getConnection()->insert($this->table,$fields);
		if ($affect <= 0) {
			throw $this->createDaoException('insert homework error!');
		}
		return $this->getHomework($this->getConnection()->lastInsertId());
	}

    public function updateHomework($id,$fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getHomework($id);
    }

    public function deleteHomework($id)
    {
        return $this->getConnection()->delete($this->table, array('id' => $id));
    }
}