<?php

namespace Topxia\Service\Classes\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Classes\Dao\ClassesDao;

class ClassesDaoImpl extends BaseDao implements ClassesDao
{
    protected $cachedById = array();
    protected $cachedByGradeAndName = array();

    public function getClass($id)
    {
        $self = $this;
        return $this->cachedCall($id, function($id) use ($self) {
            $sql = "SELECT * FROM {$self->getTablename()} WHERE id = ? LIMIT 1";
            return $self->getConnection()->fetchAssoc($sql, array($id)) ? : null;
        });
    }

    public function getClassByGradeAndName($gradeId, $className){
        $self = $this;
        return $this->cachedCallByGradeAndName($gradeId, $className, function($gradeId, $className) use ($self) {
            $sql ="SELECT * FROM {$self->getTablename()} WHERE gradeId = ? AND name = ? LIMIT 1;";
            return $self->getConnection()->fetchAssoc($sql, array($gradeId, $className)) ? : null;
        });
    }

    public function findClassesByIds(array $ids)
    {
        if(empty($ids)){ return array(); }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="SELECT * FROM {$this->getTablename()} WHERE id IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $ids);
    }

    public function findClassesByHeadTeacherId($headTeacherId)
    {
        $sql ="SELECT * FROM {$this->getTablename()} WHERE enabled = 1 and headTeacherId = ?;";
        return $this->getConnection()->fetchAll($sql, array($headTeacherId)) ? : null;
    }  

    public function searchClasses($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $defaultOrderby = array('gradeId' => 'ASC', 'name' => 'ASC');
        $orderBy = array_merge($defaultOrderby, $orderBy);
        $builder = $this->_createSearchQueryBuilder($conditions)
        ->select('*')
        ->setFirstResult($start)
        ->setMaxResults($limit);
        
        foreach ($orderBy as $key => $value) {
            $builder->addOrderBy($key, $value);
        }

        return $builder->execute()->fetchAll() ? : array(); 
    }

    public function searchClassCount($conditions)
    {
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    public function createClass($class)
    {
        $affected = $this->getConnection()->insert(self::TABLENAME, $class);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert class error.');
        }
        return $this->getClass($this->getConnection()->lastInsertId());
    }

    public function updateClass($id, $fields)
    {
        $this->getConnection()->update(self::TABLENAME, $fields, array('id' => $id));
        return $this->getClass($id);
    }
    
    public function updateClassStudentNum($num,$id){
        $sql ="UPDATE {$this->getTablename()} SET studentNum=studentNum+(?) WHERE id =?;";
        return $this->getConnection()->executeQuery($sql,array($num,$id));
    }

    public function deleteClass($id)
    {
        return $this->getConnection()->delete(self::TABLENAME, array('id' => $id));
    }

    private function _createSearchQueryBuilder($conditions)
    {

        $builder = $this->createDynamicQueryBuilder($conditions)
        ->from(self::TABLENAME, 'class')
        ->andWhere('enabled = :enabled')
        ->andWhere('gradeId = :gradeId')
        ->andWhere('headTeacherId = :headTeacherId')
        ->andWhere('year = :year');

        return $builder;
    }

    public function getTablename()
    {
        return self::TABLENAME;
    }

    protected function cachedCall($id, $callback)
    {
        if (isset($this->cachedById[$id])) {
            return $this->cachedById[$id];
        }

        $this->cachedById[$id] = $callback($id);

        return $this->cachedById[$id];
    }

    protected function cachedCallByGradeAndName($gradeId, $className, $callback)
    {
        $key = $gradeId . "|" . $className;

        if (isset($this->cachedByGradeAndName[$key])) {
            return $this->cachedByGradeAndName[$key];
        }

        $this->cachedByGradeAndName[$key] = $callback($gradeId, $className);

        return $this->cachedByGradeAndName[$key];
    }
    

}