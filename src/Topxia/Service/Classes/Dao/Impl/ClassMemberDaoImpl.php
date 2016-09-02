<?php

namespace Topxia\Service\Classes\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Classes\Dao\ClassMemberDao;
use Topxia\Common\DaoException;
use PDO;

class ClassMemberDaoImpl extends BaseDao implements ClassMemberDao
{
    protected $table = 'class_member';

    public function getClassMember($id){
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

     public function getMemberByUserId($userId)
     {
        $sql = "SELECT * FROM {$this->table} WHERE userId = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($userId)) ? : null;
    }

    public function getMemberByClassIdAndRole($classId, $role)
    {
        $sql = "SELECT * FROM {$this->table} WHERE classId = ? AND role = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($classId, $role)) ? : null;
    }

    public function getMemberByUserIdAndClassId($userId, $classId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE userId = ? AND classId = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($userId, $classId)) ? : null;
    }

    public function getStudentMemberByUserIdAndClassId($userId, $classId){
        $sql = "SELECT * FROM {$this->table} WHERE userId = ? AND role =? ";
        $conditions=array($userId,'STUDENT');
        if(!empty($classId)){
            $sql.="AND classId = ? ";
            $conditions[]=$classId;
        }
        $sql.="LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, $conditions) ? : null;
    }

    public function findStudentMembersByUserIdsAndClassId($userIds, $classId)
    {
        if(empty($userIds)){ return array(); }
        $marks = str_repeat('?,', count($userIds) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE userId IN ({$marks}) and classId=?;";
        $userIds[]=$classId;
        return $this->getConnection()->fetchAll($sql, $userIds);
    }

    public function findMembersByClassIdAndRole($classId, $role)
    {
        $sql ="SELECT * FROM {$this->table} WHERE classId = ? AND role = ?;";
        return $this->getConnection()->fetchAll($sql, array($classId, $role));
    }

    public function findClassMembersByUserIds(array $userIds)
    {
        if(empty($userIds)){ return array(); }
        $marks = str_repeat('?,', count($userIds) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE userId IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $userIds);
    }

    public function searchClassMembers($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createClassMemberQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array();
    }

    public function searchClassMemberCount($conditions)
    {
        $builder = $this->createClassMemberQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    public function addClassMember($classMember){
        $affected = $this->getConnection()->insert($this->table, $classMember);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert classMember error.');
        }
        return $this->getClassMember($this->getConnection()->lastInsertId());
    }

    public function updateClassMember($fields, $id)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getClassMember($id);
    }
    public function deleteClassMemberByUserId($userId){
        return $this->getConnection()->delete($this->table,  array('userId' => $userId));
    }
    
    private function createClassMemberQueryBuilder($conditions)
    {
        $conditions = array_filter($conditions,function($v){
            if($v === 0){
                return true;
            }
                
            if(empty($v)){
                return false;
            }
            return true;
        });
        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'class_member')
            ->andWhere('classId = :classId')
            ->andWhere('role = :role')
            ->andWhere('userId = :userId')
            ->andWhere('role IN (:roles)');
            
        return $builder;
    }


}