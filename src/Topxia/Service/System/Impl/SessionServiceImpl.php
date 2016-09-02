<?php 

namespace Topxia\Service\System\Impl;

use Topxia\Service\Common\BaseService;
use Topxia\Service\System\SessionService;

class SessionServiceImpl extends BaseService implements SessionService
{
    public function get($id)
    {
    	return $this->getSessionDao()->get($id);
    }

    public function clear($id)
    {
    	return $this->getSessionDao()->delete($id);
    }

	public function clearByUserId($userId)
	{
    	return $this->getSessionDao()->deleteSessionByUserId($userId);
	}

    public function findLoginsByUserIds(array $userIds)
    {
        return $this->getSessionDao()->findLoginsByUserIds($userIds);
    }

    public function findRecentLoginsByUserIds(array $userIds)
    {
        return $this->getSessionDao()->findRecentLoginsByUserIds($userIds);
    }

	private function getSessionDao()
	{
		return $this->createDao('System.SessionDao');
	}
	
}