<?php
namespace Topxia\Service\Grade\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Grade\Dao\GradeDao;

class GradeDaoImpl extends BaseDao implements GradeDao
{
    const TABLENAME = 'grade';
    protected $table = self::TABLENAME;
    protected $cachedByName = array();

    public function getGrade($id){
        $sql ="SELECT * FROM {$this->table} where id = ? LIMIT 1;";

        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function getGradeByName($name){
        $self = $this;
        return $this->cachedCall($name, function($name) use ($self) {
            $sql ="SELECT * FROM {$self->getTablename()} where name = ? LIMIT 1;";
            return $self->getConnection()->fetchAssoc($sql, array($name)) ? : null;
        });
    }

    public function findAllGrades()
    {
        $sql ="SELECT * FROM {$this->table} order by gradeGroup, seq;";

        return $this->getConnection()->fetchAll($sql, array());
    }

    public function findGradesByGroup($group){
        $sql ="SELECT * FROM {$this->table} where gradeGroup = ? order by seq;";

        return $this->getConnection()->fetchAll($sql, array($group));
    }

    public function addGrade($grade){
        $affected = $this->getConnection()->insert($this->table, $grade);

        if ($affected <= 0) {

            throw $this->createDaoException('Insert grade error.');
        }

        return $this->getGrade($this->getConnection()->lastInsertId());
    }

    public function updateGrade($grade){
        $this->getConnection()->update($this->table, $grade, array('id' => $grade['id']));

        return $this->getGrade($grade['id']);
    }

    public function getMaxGradeId(){
        $sql ="SELECT MAX(id) FROM {$this->table};";

        return $this->getConnection()->fetchColumn($sql);
    }

    public function deleteGrades($gradeGroup){
        $sql ="DELETE FROM {$this->table} WHERE gradeGroup = ?;";

        return $this->getConnection()->executeQuery($sql, array($gradeGroup));
    }

    protected function cachedCall($name, $callback)
    {
        if (isset($this->cachedByName[$name])) {
            return $this->cachedByName[$name];
        }

        $this->cachedByName[$name] = $callback($name);

        return $this->cachedByName[$name];
    }

    public function getTablename()
    {
        return self::TABLENAME;
    }
}