<?php

namespace Topxia\DataTag;

use Topxia\DataTag\DataTag;

class RecommendStudentsDataTag extends CourseBaseDataTag implements DataTag  {
    /**
     * 首页学生照片墙用户列表
     * 可传入的参数：
     *   count    必需 学生数量，取值不能超过100
     * 
     * @param  array $arguments 参数
     * @return array 学生照片墙用户列表
     */
    public function getData(array $arguments)
    {	
        $this->checkCount($arguments);
        
    	$users = $this->getUserService()->findUsersWithAvatarByRandom($arguments['count']);

        return $this->unsetUserPasswords($users);
    }

}
