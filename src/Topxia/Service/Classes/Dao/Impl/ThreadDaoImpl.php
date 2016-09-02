<?php

namespace Topxia\Service\Classes\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Classes\Dao\ThreadDao;

class ThreadDaoImpl extends BaseDao implements ThreadDao
{

	protected $table = 'class_thread';

	public function getThread($id)
	{
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
	}

	public function findLatestThreadsByType($type, $start, $limit)
	{
        $sql = "SELECT * FROM {$this->table} WHERE type = ? ORDER BY createdTime DESC";
        return $this->getConnection()->fetchAll($sql, array($type)) ? : array();
	}

	public function findEliteThreadsByType($type, $status, $start, $limit)
	{
		$sql = "SELECT * FROM {$this->table} WHERE type = ? AND isElite = ? ORDER BY createdTime DESC LIMIT {$start}, {$limit}";
        return $this->getConnection()->fetchAll($sql, array($type, $status)) ? : array();
	}

    public function findThreadsByUserIdAndType($userId, $type)
    {
        $sql = "SELECT * FROM {$this->table} WHERE userId = ? AND type = ? ORDER BY createdTime DESC";
        return $this->getConnection()->fetchAll($sql, array($userId, $type));
    }

	public function findThreadsByClassId($classId, $orderBy, $start, $limit)
	{
        $this->filterStartLimit($start, $limit);
        // @todo: fixed me.
		$orderBy = join (' ', $orderBy);
        $sql = "SELECT * FROM {$this->table} WHERE classId = ? ORDER BY {$orderBy} LIMIT {$start}, {$limit}";
        return $this->getConnection()->fetchAll($sql, array($classId)) ? : array();
	}

	public function findThreadsByClassIdAndType($classId, $type, $orderBy, $start, $limit)
	{
        $this->filterStartLimit($start, $limit);
        // @todo: fixed me.
		$orderBy = join (' ', $orderBy);
        $sql = "SELECT * FROM {$this->table} WHERE classId = ? AND type = ? ORDER BY {$orderBy} LIMIT {$start}, {$limit}";
        return $this->getConnection()->fetchAll($sql, array($classId, $type)) ? : array();
	}

	public function searchThreads($conditions, $orderBys, $start, $limit)
	{
        $this->filterStartLimit($start, $limit);
		$builder = $this->createThreadSearchQueryBuilder($conditions)
			->select('*')
			->setFirstResult($start)
			->setMaxResults($limit);
		foreach ($orderBys as $orderBy) {
			$builder->addOrderBy($orderBy[0], $orderBy[1]);
		}

		return $builder->execute()->fetchAll() ? : array();
	}

	public function searchThreadCount($conditions)
	{
		$builder = $this->createThreadSearchQueryBuilder($conditions)
			->select('COUNT(id)');
		return $builder->execute()->fetchColumn(0);
	}

	private function createThreadSearchQueryBuilder($conditions)
	{
		if (isset($conditions['title'])) {
			$conditions['title'] = "%{$conditions['title']}%";
		}

		if (isset($conditions['content'])) {
			$conditions['content'] = "%{$conditions['content']}%";
		}
		
		$builder = $this->createDynamicQueryBuilder($conditions)
			->from($this->table, $this->table)
			->andWhere('classId = :classId')
			->andWhere('userId = :userId')
			->andWhere('type = :type')
			->andWhere('isStick = :isStick')
			->andWhere('isElite = :isElite')
            ->andWhere('postNum = :postNum')
            ->andWhere('postNum > :postNumLargerThan')
			->andWhere('title LIKE :title')
			->andWhere('content LIKE :content');

		return $builder;
	}

	public function addThread($fields)
	{
        $affected = $this->getConnection()->insert($this->table, $fields);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert class thread error.');
        }
        return $this->getThread($this->getConnection()->lastInsertId());
	}

	public function updateThread($id, $fields)
	{
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getThread($id);
	}

	public function deleteThread($id)
	{
		return $this->getConnection()->delete($this->table, array('id' => $id));
	}

	public function waveThread($id, $field, $diff)
	{
		$fields = array('postNum', 'hitNum', 'followNum');
		if (!in_array($field, $fields)) {
			throw \InvalidArgumentException(sprintf("%s字段不允许增减，只有%s才被允许增减", $field, implode(',', $fields)));
		}
		$sql = "UPDATE {$this->table} SET {$field} = {$field} + ? WHERE id = ? LIMIT 1";
        return $this->getConnection()->executeQuery($sql, array($diff, $id));
	}

}