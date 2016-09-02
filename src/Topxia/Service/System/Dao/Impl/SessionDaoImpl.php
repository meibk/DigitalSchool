<?php

namespace Topxia\Service\System\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\System\Dao\SessionDao;

class SessionDaoImpl extends BaseDao implements SessionDao
{
    protected $table = "session2";

    public function get($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function getSessionByUserId($userId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE session_user_id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($userId)) ? : null;
    }

    public function delete($id)
    {
        return $this->getConnection()->delete($this->table, array('id' => $id));
    }

    public function deleteSessionByUserId($userId)
    {
        return $this->getConnection()->delete($this->table, array('session_user_id' => $userId));
    }

    public function getOnlineCount($retentionTime)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE `session_time` BETWEEN (unix_timestamp(now())-{$retentionTime}) AND (unix_timestamp(now()));";
        return $this->getConnection()->fetchColumn($sql) ? : null;
    }

    public function getLoginCount($retentionTime)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE `session_time` BETWEEN (unix_timestamp(now())-{$retentionTime}) AND (unix_timestamp(now())) AND `user_id` > 0";
        return $this->getConnection()->fetchColumn($sql) ? : null;    
    }

    public function findLoginsByUserIds(array $userIds)
    {
        if(empty($userIds)){ return array(); }
        $marks = str_repeat('?,', count($userIds) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE user_id IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $userIds);
    }

    public function findRecentLoginsByUserIds(array $userIds)
    {
        if(empty($userIds)){ return array(); }
        $marks = str_repeat('?,', count($userIds) - 1) . '?';
        $startTime = strtotime("30 minutes ago");
        $sql ="SELECT * FROM {$this->table} WHERE user_id IN ({$marks}) AND session_time > $startTime;";
        return $this->getConnection()->fetchAll($sql, $userIds);
    }
}