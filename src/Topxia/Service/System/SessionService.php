<?php 
namespace Topxia\Service\System;

interface SessionService
{
    public function get($id);

    public function clear ($id);

    public function clearByUserId ($userId);

    public function findLoginsByUserIds(array $userIds);

    public function findRecentLoginsByUserIds(array $userIds);
}