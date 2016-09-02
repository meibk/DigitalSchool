<?php

namespace Topxia\Service\User\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\User\Dao\UserDao;
use Topxia\Common\DaoException;
use PDO;

class UserDaoImpl extends BaseDao implements UserDao
{
    protected $table = 'user';


    protected $allowedOrderByFields = array('point');


    public function getUser($id, $lock = false)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        if ($lock) {
            $sql .= " FOR UPDATE";
        }
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function getUserByNumber($number){
        $sql = "SELECT * FROM {$this->table} WHERE number = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($number)) ? : null;
    }

    public function findUsersByNumbers(array $numbers)
    {
        if(empty($numbers)){ return array(); }
        $marks = str_repeat('?,', count($numbers) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE number IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $numbers);
    }

    public function findUserByEmail($email)
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($email));
    }

    public function findUserByNickname($nickname)
    {
        $sql = "SELECT * FROM {$this->table} WHERE nickname = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($nickname));
    }

    public function findUserByMobile($mobile)
    {
        $sql = "SELECT * FROM {$this->table} WHERE mobile = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($mobile));
    }
    public function findUsersByIds(array $ids)
    {
        if(empty($ids)){ return array(); }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE id IN ({$marks}) ORDER BY createdTime;";
        return $this->getConnection()->fetchAll($sql, $ids);
    }

    public function findUsersByIdsAndOrder(array $ids, array $orderBy)
    {
        if(empty($ids)){ return array(); }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $orderBy = $this->checkOrderBy($orderBy, $this->allowedOrderByFields);
        $sql ="SELECT * FROM {$this->table} WHERE id IN ({$marks}) ORDER BY " . $orderBy[0] . " " . $orderBy[1] . ";";
        return $this->getConnection()->fetchAll($sql, $ids);
    }

    public function findUsersWithAvatarByRandom($count){
        $sql = "SELECT * FROM {$this->table} WHERE roles = '|ROLE_USER|' AND smallAvatar <> '' ORDER BY RAND() LIMIT " . $count;
        return $this->getConnection()->fetchAll($sql, array());
    }

    public function searchUsers($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createUserQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array();
    }

    public function searchUserCount($conditions)
    {
        $builder = $this->createUserQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    private function createUserQueryBuilder($conditions)
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
        if (isset($conditions['roles'])) {
            $conditions['roles'] = "%{$conditions['roles']}%";
        }

        if (isset($conditions['role'])) {
            $conditions['role'] = "|{$conditions['role']}|";
        }

        if(isset($conditions['keywordType']) && isset($conditions['keyword'])) {
            $conditions[$conditions['keywordType']]=$conditions['keyword'];
            unset($conditions['keywordType']);
            unset($conditions['keyword']);
        }

        if (isset($conditions['nickname'])) {
            $conditions['nickname'] = "%{$conditions['nickname']}%";
        }

        if (isset($conditions['numberLike'])) {
            $conditions['numberLike'] = "%{$conditions['numberLike']}%";
        }

        if (isset($conditions['truename'])) {
            $conditions['truename'] = "%{$conditions['truename']}%";
        }

        $builder=$this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'user')
            ->andWhere('promoted = :promoted')
            ->andWhere('roles LIKE :roles')
            ->andWhere('roles = :role')
            ->andWhere('nickname LIKE :nickname')
            ->andWhere('truename LIKE :truename')
            ->andWhere('number = :number')
            ->andWhere('number LIKE:numberLike')
            ->andWhere('loginIp = :loginIp')
            ->andWhere('approvalStatus = :approvalStatus')
            ->andWhere('email = :email')
            ->andWhere('mobile = :mobile')
            ->andWhere('level = :level')
            ->andWhere('createdTime >= :startTime')
            ->andWhere('createdTime <= :endTime')
            ->andWhere('locked = :locked')
            ->andWhere('level >= :greatLevel')
            ->andWhere('id IN (:ids)');

        return $builder;
    }

    public function addUser($user)
    {
        $affected = $this->getConnection()->insert($this->table, $user);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert user error.');
        }
        return $this->getUser($this->getConnection()->lastInsertId());
    }

    public function updateUser($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getUser($id);
    }

    public function waveCounterById($id, $name, $number)
    {
        $names = array('newMessageNum', 'newNotificationNum');
        if (!in_array($name, $names)) {
            throw $this->createDaoException('counter name error');
        }
        $sql = "UPDATE {$this->table} SET {$name} = {$name} + ? WHERE id = ? LIMIT 1";
        return $this->getConnection()->executeQuery($sql, array($number, $id));
    }

    public function clearCounterById($id, $name)
    {
        $names = array('newMessageNum', 'newNotificationNum');
        if (!in_array($name, $names)) {
            throw $this->createDaoException('counter name error');
        }
        $sql = "UPDATE {$this->table} SET {$name} = 0 WHERE id = ? LIMIT 1";
        return $this->getConnection()->executeQuery($sql, array($id));
    }

    public function analysisRegisterDataByTime($startTime,$endTime)
    {
        $sql="SELECT count(id) as count, from_unixtime(createdTime,'%Y-%m-%d') as date FROM `{$this->table}` WHERE`createdTime`>={$startTime} and `createdTime`<={$endTime} group by from_unixtime(`createdTime`,'%Y-%m-%d') order by date ASC ";
        return $this->getConnection()->fetchAll($sql);
    }

    public function analysisUserSumByTime($endTime)
    {
         $sql="SELECT date , max(a.Count) as count from (SELECT from_unixtime(o.createdTime,'%Y-%m-%d') as date,( SELECT count(id) as count FROM  {$this->table}   i   WHERE   i.createdTime<=o.createdTime  )  as Count from {$this->table}  o  where o.createdTime<={$endTime} order by 1,2) as a group by date ";
         return $this->getConnection()->fetchAll($sql);
    }

        public function findUsersCountByLessThanCreatedTime($endTime)
    {
         
        $sql="SELECT count(id) as count FROM `{$this->table}` WHERE  `createdTime`<={$endTime}  ";

        return $this->getConnection()->fetchColumn($sql);
    }

}