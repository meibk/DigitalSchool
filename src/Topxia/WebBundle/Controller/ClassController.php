<?php
namespace Topxia\WebBundle\Controller;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;

class ClassController extends ClassBaseController
{
    public function showAction(Request $request, $classId)
    {
        return $this->forward('TopxiaWebBundle:ClassThread:list', array('classId' => $classId), $request->query->all());
    }

    public function headerBlockAction($class, $classNav)
    {
        $headTeacher = $this->getClassesService()->getClassHeadTeacher($class['id']);
        $user = $this->getCurrentUser();
        return $this->render('TopxiaWebBundle:Class:header-block.html.twig', array(
            'class' => $class,
            'classNav' => $classNav,
            'user' => $user,
            'headTeacher' => $headTeacher,
        ));
    }

    public function userInfoAction($class, $userId)
    {   
        $user = $this->getCurrentUser();
        if($user->isAdmin()) {
            return $this->forward('TopxiaWebBundle:Class:admin', array('class' => $class, 'userId' => $userId));
        }
        $member = $this ->getClassesService()->getMemberByUserIdAndClassId($userId, $class['id']);
        $role = strstr($member['role'],'TEACHER') ? 'teacher' : strtolower($member['role']); 
        return $this->forward('TopxiaWebBundle:Class:' . $role, array('class' => $class, 'userId' => $userId));
    }

    public function myTasksAction($class, $userId)
    {   
        $user = $this->getCurrentUser();

        $classId = $class['id'];

        $schedule = $this->getScheduleService()->findOneDaySchedules($classId, date("Ymd"));

        $scheduledLessons = $this->getMyScheduledLessons($schedule, $classId, $userId);
        $myHomeworks = $this->getMyHomeworks($schedule, $classId, $userId);

        $allTasks = array_merge($scheduledLessons, $myHomeworks);

        $allTasks = $this->setPoints($allTasks);

        return $this->render('TopxiaWebBundle:Class:student-tasks-block.html.twig',array(
            'allTasks'=>$allTasks
            ));
    }

    private function setPoints($tasks){
        $results = array();

        $pointSettings = $this->getSettingService()->get('point', array());

        foreach ($tasks as $task) {
            if($task['type'] == 'lesson'){
                $task['score'] = isset($pointSettings['accomplishLesson']) ? $pointSettings['accomplishLesson'] : 2;
            } elseif ($task['type'] == 'testpaper'){
                $task['score'] = isset($pointSettings['accomplishTest']) ? $pointSettings['accomplishTest'] : 3;
            } elseif ($task['type'] == 'homework'){
                $task['score'] = isset($pointSettings['accomplishHomework']) ? $pointSettings['accomplishHomework'] : 3;
            }

            array_push($results, $task);
        }

        return $results;

    }

    private function getMyScheduledLessons($schedule, $classId, $userId){
        $tasks = array();

        if(count($schedule['lessons']) > 0){
            $finishedLessonIds = $this->getFinishedLessonIds(ArrayToolkit::column($schedule['lessons'], 'id'), $userId);

            foreach ($schedule['lessons'] as $lesson) {
                if (!in_array($lesson['id'], $finishedLessonIds)){
                    array_push($tasks, $this->lessonToTask($schedule, $lesson));
                }
            }
        }

        return $tasks;
    }

    private function lessonToTask($schedule, $lesson){
        $task = array();

        $task['lessonId'] = $lesson['id'];
        $task['type'] = $lesson['type'] == 'testpaper' ? 'testpaper' : 'lesson';
        $task['courseId'] = $lesson['courseId'];
        $task['courseTitle'] = $schedule['courses'][$lesson['courseId']]['title'];
        $task['title'] = $lesson['title'];
        $task['displayTitle'] = '《' . $task['courseTitle'] . '》' . $task['title'];

        return $task;
    }

    private function homeworkToTask($schedule, $homework){
        $task = array();

        $task['lessonId'] = $homework['lessonId'];
        $task['type'] = 'homework';
        $task['courseId'] = $homework['courseId'];
        $task['courseTitle'] = $schedule['courses'][$homework['courseId']]['title'] ?: '';
        $task['title'] = $schedule['lessons'][$homework['lessonId']]['title'];
        $task['displayTitle'] = '《' . $task['courseTitle'] . '》' . $task['title'];

        return $task;
    }

    private function getFinishedLessonIds($lessonIds, $userId){
        $finishedLessons = $this->getCourseService()->searchLearns(array(
            'lessonIds'=>$lessonIds,
            'userId'=>$userId,
            'status'=>'finished'
            ), array('id', 'ASC'), 0, PHP_INT_MAX);

        return ArrayToolkit::column($finishedLessons, 'lessonId');
    }

    private function getMyHomeworks($schedule, $classId, $userId){
        $tasks = array();
        $allHomeworks = array();

        foreach ($schedule['lessons'] as $lesson) {
            $homework = $this->getHomeworkService()->getHomeworkByLessonId($lesson['id']);

            if(isset($homework)){
                $homeworkResult = $this->getHomeworkService()->getResultByHomeworkIdAndUserId($homework['id'], $userId);

                if(empty($homeworkResult) || $homeworkResult['status'] == 'doing'){
                    array_push($allHomeworks, $homework);
                }
            }
        }

        foreach ($allHomeworks as $homework) {
            array_push($tasks, $this->homeworkToTask($schedule, $homework));
        }

        return $tasks;
    }

    public function adminAction($class, $userId)
    {
        return $this->render('TopxiaWebBundle:Class:admin-block.html.twig',array(
            'class' => $class)); 
    }

    public function teacherAction($class, $userId)
    {
        $isSignedToday = $this->getSignService()->isSignedToday($userId, 'class_sign', $class['id']);
        return $this->render('TopxiaWebBundle:Class:teacher-block.html.twig',array(
            'class' => $class,
            'isSignedToday' => $isSignedToday));
    }

    public function parentAction($class, $userId)
    {
        $isSignedToday = $this->getSignService()->isSignedToday($userId, 'class_sign', $class['id']);
        return $this->render('TopxiaWebBundle:Class:parent-block.html.twig', array(
            'class' => $class,
            'isSignedToday' => $isSignedToday
            ));
    }

    public function scheduleAction($classId, $userId, $viewType)
    {
        $date = $viewType == 'today' ? date('Ymd') : date('Ymd', strtotime('+ 1 day'));
        $results = $this->getScheduleService()->findOneDaySchedules($classId, $date);

        $lessonIds = array_keys($results['lessons']); 
        $userLearns = $this->getCourseService()->findLessonLearnsByIds($userId, $lessonIds);
        $lastSchedule = array();
        $headSchedule = array();
        foreach ($results['schedules'] as $key => $schedule) {
            if(isset($userLearns[$schedule['lessonId']]) && $userLearns[$schedule['lessonId']]['status'] == 'finished') {
                $schedule['status'] = 'finished';
                $lastSchedule[] = $schedule;
            } else {
                $schedule['status'] = 'notFinished';
                $headSchedule[] = $schedule;
            }   
        }
        $newSchedules = array_merge($headSchedule, $lastSchedule);
        return $this->render('TopxiaWebBundle:Class:schedule-list.html.twig', array(
            'courses' => $results['courses'],
            'lessons' => $results['lessons'],
            'teachers' => $results['teachers'],
            'schedules' => $newSchedules,
            'classId' => $classId,
            'viewType' => $viewType,
            )); 
    }

    public function studentAction($class, $userId)
    {

        $user = $this->getCurrentUser();
        $classMember = $this->getClassesService()->refreashStudentRank($user['id'], $class['id']);
        $nextLearnLesson = $this->getCourseService()->getNextLearnLessonByUserId($user['id']);
        $nextCourse = array();
        $nextLesson = array();
        if($nextLearnLesson) {
            $nextCourse = $this->getCourseService()->getCourse($nextLearnLesson['courseId']);
            $nextLesson = $this->getCourseService()->getCourseLesson($nextLearnLesson['courseId'], $nextLearnLesson['lessonId']);
        }
        
        $isSignedToday = $this->getSignService()->isSignedToday($user['id'], 'class_sign', $class['id']);
        return $this->render('TopxiaWebBundle:Class:student-block.html.twig',array(
            'class' => $class,
            'user' => $user,
            'nextCourse' => $nextCourse,
            'nextLesson' => $nextLesson,
            'classMember' => $classMember,
            'isSignedToday' => $isSignedToday));
    }

    public function statusBlockAction($class)
    {
        $members = $this->getClassesService()->findClassStudentMembers($class['id']);

        $userIds = ArrayToolkit::column($members, 'userId');

        $statuses = $this->getStatusService()->findStatusesByUserIds($userIds, 0, 10);

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($statuses, 'userId'));
        
        return $this->render('TopxiaWebBundle:Class:status-block.html.twig', array(
            'statuses' => $statuses,
            'users' => $users,

        ));
    }

    protected function getStatusService()
    {
        return $this->getServiceKernel()->createService('User.StatusService');
    }

    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    private function getSignService()
    {
        return $this->getServiceKernel()->createService('Sign.SignService');
    }

    private function getScheduleService()
    {
        return $this->getServiceKernel()->createService('Schedule.ScheduleService');
    }

    private function getHomeworkService()
    {
        return $this->getServiceKernel()->createService('Homework.K12HomeworkService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }
}