<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
use Topxia\Common\StringToolkit;

class UserController extends BaseController
{
    public function headerBlockAction($user)
    {
        $userProfile = $this->getUserService()->getUserProfile($user['id']);
        $user = array_merge($user, $userProfile);

        if ($this->getCurrentUser()->isLogin()) {
            $isFollowed = $this->getUserService()->isFollowed($this->getCurrentUser()->id, $user['id']);
        } else {
            $isFollowed = false;
        }
        /**为任课老师准备*/
        $courseCount=0;
        /**为班主任准备*/
        $headTeacherCount=0;
        /**为学生准备*/
        $class=array();
        if(in_array('ROLE_TEACHER', $user['roles'])) {
            
            //$courses=$this->getCourseService()->findUserTeachCourses($user['id'], 0, PHP_INT_MAX);
            $conditions=array(
                'teacherIds'=>$user['id'],
                'status' => 'published',
                'defaultClassId'=>0,
            );
            $courseCount=$this->getCourseService()->searchCourseCount($conditions);
            $headTeacherCount=$this->getClassService()->searchClassCount(array('headTeacherId'=>$user['id']));
        }else{
            $conditions=array(
                'userId'=>$user['id'],
                'roles'=>array('STUDENT')
            );
            $classMembers=$this->getClassService()->searchClassMembers($conditions, array('createdTime', 'DESC'), 0, PHP_INT_MAX);
            if(count($classMembers)>0){
                $class=$this->getClassService()->getClass($classMembers[0]['classId']);
            }
        }
        return $this->render('TopxiaWebBundle:User:header-block.html.twig', array(
            'user' => $user,
            'isFollowed' => $isFollowed,
            'courseCount'=>$courseCount,
            'headTeacherCount'=>$headTeacherCount,
            'cl'=>count($class)>0 ? $class : null
        ));
    }

    public function showAction(Request $request, $id)
    {
        $user = $this->tryGetUser($id);

        // Only teacher's information is visible to guest. Student's information needs logged in.
        if(in_array('ROLE_TEACHER', $user['roles'])) {
            return $this->_teachAction($user);
        }else{
            $currentUser = $this->getCurrentUser();

            if (!$currentUser->isLogin()) {
                return $this->redirect($this->generateUrl('login'));
            }else{
                return $this->_learnAction($user);
            }
        }

    }

    public function learnAction(Request $request, $id)
    {
        $user = $this->tryGetUser($id);
        return $this->_learnAction($user);
    }

    public function teachAction(Request $request, $id)
    {
        $user = $this->tryGetUser($id);
        return $this->_teachAction($user);
    }

    public function favoritedAction(Request $request, $id)
    {
        $user = $this->tryGetUser($id);
        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->findUserFavoritedCourseCount($user['id']),
            10
        );

        $courses = $this->getCourseService()->findUserFavoritedCourses(
            $user['id'],
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        return $this->render('TopxiaWebBundle:User:courses.html.twig', array(
            'user' => $user,
            'courses' => $courses,
            'paginator' => $paginator,
            'type' => 'favorited',
        ));
    }

    public function groupAction(Request $request, $id)
    {
        $user = $this->tryGetUser($id);

        $admins=$this->getGroupService()->searchMembers(array('userId'=>$user['id'],'role'=>'admin'),
            array('createdTime',"DESC"),0,1000
            );
        $owners=$this->getGroupService()->searchMembers(array('userId'=>$user['id'],'role'=>'owner'),
            array('createdTime',"DESC"),0,1000
            );
        $members=array_merge($admins,$owners);
        $groupIds = ArrayToolkit::column($members, 'groupId');
        $adminGroups=$this->getGroupService()->getGroupsByids($groupIds);

        $paginator=new Paginator(
            $this->get('request'),
            $this->getGroupService()->searchMembersCount(array('userId'=>$user['id'],'role'=>'member')),
            12
            );

        $members=$this->getGroupService()->searchMembers(array('userId'=>$user['id'],'role'=>'member'),array('createdTime',"DESC"),$paginator->getOffsetCount(),
                $paginator->getPerPageCount());

        $groupIds = ArrayToolkit::column($members, 'groupId');
        $groups=$this->getGroupService()->getGroupsByids($groupIds);


        return $this->render('TopxiaWebBundle:User:group.html.twig', array(
            'user' => $user,
            'type' => 'group',
            'adminGroups'=>$adminGroups,
            'paginator'=>$paginator,
            'groups'=>$groups
        ));
    }

    public function followingAction(Request $request, $id)
    {
        $user = $this->tryGetUser($id);
        $followings = $this->getUserService()->findAllUserFollowing($user['id']);

        return $this->render('TopxiaWebBundle:User:friend.html.twig', array(
            'user' => $user,
            'friends' => $followings,
            'friendNav' => 'following',
        ));

    }

    public function followerAction(Request $request, $id)
    {
        $user = $this->tryGetUser($id);
        $followers=$this->getUserService()->findAllUserFollower($user['id']);

        return $this->render('TopxiaWebBundle:User:friend.html.twig', array(
            'user' => $user,
            'friends' => $followers,
            'friendNav' => 'follower',
        ));
    }

    public function remindCounterAction(Request $request)
    {
        $user = $this->getCurrentUser();
        $counter = array('newMessageNum' => 0, 'newNotificationNum' => 0);
        if ($user->isLogin()) {
            $counter['newMessageNum'] = $user['newMessageNum'];
            $counter['newNotificationNum'] = $user['newNotificationNum'];
        }
        return $this->createJsonResponse($counter);
    }

    public function unfollowAction(Request $request, $id)
    {
        $user = $this->getCurrentUser();
        if (!$user->isLogin()) {
            throw $this->createAccessDeniedException();
        }

        $this->getUserService()->unFollow($user['id'], $id);

        $userShowUrl = $this->generateUrl('user_show', array('id' => $user['id']), true);
        $message = "用户<a href='{$userShowUrl}' target='_blank'>{$user['truename']}</a>对你已经取消了关注！";
        $this->getNotificationService()->notify($id, 'default', $message);

        return $this->createJsonResponse(true);
    }

    public function followAction(Request $request, $id)
    {
        $user = $this->getCurrentUser();
        if (!$user->isLogin()) {
            throw $this->createAccessDeniedException();
        }
        $this->getUserService()->follow($user['id'], $id);

        $userShowUrl = $this->generateUrl('user_show', array('id' => $user['id']), true);
        $message = "用户<a href='{$userShowUrl}' target='_blank'>{$user['truename']}</a>已经关注了你！";
        $this->getNotificationService()->notify($id, 'default', $message);

        return $this->createJsonResponse(true);
    }

    public function checkPasswordAction(Request $request)
    {
        $password = $request->query->get('value');
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isLogin()) {
            $response = array('success' => false, 'message' => '请先登入');
        }

        if (!$this->getUserService()->verifyPassword($currentUser['id'], $password)) {
            $response = array('success' => false, 'message' => '输入的密码不正确');
        }else{
            $response = array('success' => true, 'message' => '');
        }
        return $this->createJsonResponse($response);
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Course.ThreadService');
    }

    protected function getNoteService()
    {
        return $this->getServiceKernel()->createService('Course.NoteService');
    }

    protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

    protected function getClassService(){

        return $this->getServiceKernel()->createService('Classes.ClassesService');
    }

    private function tryGetUser($id)
    {
        $user = $this->getUserService()->getUser($id);
        if (empty($user)) {
            throw $this->createNotFoundException();
        }
        return $user;
    }

    private function _learnAction($user)
    {
        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->findUserLearnCourseCount($user['id']),
            10
        );

        $courses = $this->getCourseService()->findUserLearnCourses(
            $user['id'],
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        return $this->render('TopxiaWebBundle:User:courses.html.twig', array(
            'user' => $user,
            'courses' => $courses,
            'paginator' => $paginator,
            'type' => 'learn',
        ));
    }

    private function _teachAction($user)
    {
        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->findUserTeachCourseCount($user['id']),
            10
        );

        $courses = $this->getCourseService()->findUserTeachCourses(
            $user['id'],
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );
        foreach ($courses as $key=>$course) {
            if(empty($course['classId'])){
                unset($courses[$key]);
            }
        }
        return $this->render('TopxiaWebBundle:User:courses.html.twig', array(
            'user' => $user,
            'courses' => $courses,
            'paginator' => $paginator,
            'type' => 'teach',
        ));
    }

    private function getGroupService() 
    {   
        return $this->getServiceKernel()->createService('Group.GroupService');
    }

}