<?php
namespace Topxia\WebBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;

class MyTeachingController extends BaseController
{
    public function teachingAction(Request $request)
    {
        $user=$this->tryViewTeachingPage();
        $dateRangeType = $request->query->get('dateRange') ?: 0;

        $builder=new TeachingPageDataBuilder($user['id'], $this->calculateDateRange($dateRangeType));

        $builder->buildTeachingCourses();

        // To do list
        $builder->buildPendingHomeworkCounts();
        $builder->buildPendingTestpaperResultCounts();
        $builder->buildPendingQuestionCounts();

        // My teaching courses
        $builder->buildCourseLessonLearnStatus();
        $builder->buildCourseHomeworkStatus();
        $builder->buildCourseHomeworkCorrectPercentages();

        // My classes
        $builder->buildTeachingManageClasses();

        // $builder->buildTeachingThreads();
        // $builder->buildTeachingHomeworks();
        // $builder->buildTeachingTestpapers();

        return $this->render('TopxiaWebBundle:MyTeaching:teaching-k12.html.twig', $builder->getResult());
    }

    private function calculateDateRange($rangeType){
        $startDate = null;
        $endDate = null;

        if($rangeType == '0'){
            $startDate = mktime(0, 0, 0, date("m")  , date("d"), date("Y"));
            $endDate = time();
        }elseif($rangeType == '1'){
            $startDate = mktime(0, 0, 0, date("m")  , date("d")-1, date("Y"));
            $endDate = mktime(23, 59, 59, date("m")  , date("d")-1, date("Y"));
        }elseif($rangeType == '2'){
            $startDate = mktime(0, 0, 0, date("m")  , date("d")-2, date("Y"));
            $endDate = mktime(23, 59, 59, date("m")  , date("d")-2, date("Y"));
        }elseif($rangeType == '3'){
            $startDate = mktime(0, 0, 0, date("m")  , date("d")-3, date("Y"));
            $endDate = time();
        }elseif($rangeType == '7'){
            $startDate = mktime(0, 0, 0, date("m")  , date("d")-7, date("Y"));
            $endDate = time();
        }elseif($rangeType == '30'){
            $startDate = mktime(0, 0, 0, date("m")-1  , date("d"), date("Y"));
            $endDate = time();
        }

        return array('startDate'=>$startDate, 'endDate'=>$endDate);
    }

    public function teachingCoursesAction(Request $request,$classId)
    {
        $user=$this->tryViewTeachingPage();

        $courses = $this->getCourseService()->findUserTeachCourses($user['id'], 0, PHP_INT_MAX,false);
        $courseCount=count($courses);
        $coursesGroupedByClassId =ArrayToolkit::group($courses,'classId');
        $classIds= ArrayToolkit::column($courses, 'classId');

        $selectedClass=array();
        if($classId!='all' && $classId != '0'){
            $selectedClass=$this->getClassesService()->getClass($classId);

            if(empty($selectedClass)){
                throw $this->createNotFoundException('班级不存在');
            }
        }

        if($classId!='all' && !in_array($classId, $classIds)){
            throw $this->createNotFoundException('在该班级无在教课程');
        }
        
        $classes = $this->getClassesService()->findClassesByIds($classIds);

        return $this->render('TopxiaWebBundle:MyTeaching:teaching-courses.html.twig',array(
            'coursesGroupedByClassId'=>$coursesGroupedByClassId,
            'courses'=>$courses,
            'classes'=>$classes,
            'hasPublicCourse'=>in_array('0', $classIds),
            'courseCount'=>$courseCount,
            'selectedClass'=>$selectedClass,
            'classId'=>$classId
        ));
    }

	public function threadsAction(Request $request, $type)
	{
		$user=$this->tryViewTeachingPage();

		$myTeachingCourseCount = $this->getCourseService()->findUserTeachCourseCount($user['id'], true);

        if (empty($myTeachingCourseCount)) {
            return $this->render('TopxiaWebBundle:MyTeaching:threads.html.twig', array(
                'type'=>$type,
                'threads' => array()
            ));
        }

		$myTeachingCourses = $this->getCourseService()->findUserTeachCourses($user['id'], 0, $myTeachingCourseCount, true);

		$conditions = array(
			'courseIds' => ArrayToolkit::column($myTeachingCourses, 'id'),
			'type' => $type);

        $paginator = new Paginator(
            $request,
            $this->getThreadService()->searchThreadCountInCourseIds($conditions),
            20
        );

        $threads = $this->getThreadService()->searchThreadInCourseIds(
            $conditions,
            'createdNotStick',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($threads, 'latestPostUserId'));
        $courses = $this->getCourseService()->findCoursesByIds(ArrayToolkit::column($threads, 'courseId'));
        $lessons = $this->getCourseService()->findLessonsByIds(ArrayToolkit::column($threads, 'lessonId'));

    	return $this->render('TopxiaWebBundle:MyTeaching:threads.html.twig', array(
    		'paginator' => $paginator,
            'threads' => $threads,
            'users'=> $users,
            'courses' => $courses,
            'lessons' => $lessons,
            'type'=>$type
    	));
	}

    public function myTasksAction(Request $request)
    {   
        $user=$this->tryViewTeachingPage();

        $classId = $request->query->get('classId');
        $classId = empty($classId) ? 0 : $classId;
        $date = $request->query->get('date');
        $date = empty($date) ? date('Y-m-d') : $date;
        $teachCourses = $this->getCourseService()->findUserTeachCourses($user['id'], 0, PHP_INT_MAX,false);
        $courseCount = count($teachCourses);
        $courseList = ArrayToolkit::group($teachCourses,'classId');
        $classIds = array_keys($courseList);
        $teachClasses = $this->getClassesService()->findClassesByIds($classIds);

        return $this->render('TopxiaWebBundle:MyTeaching:mytasks.html.twig', array(
            'teachClasses' => $teachClasses,
            'classId' => $classId,
            'date' => $date,
            ));
    }

    public function getLessonsAction(Request $request, $classId, $date)
    {
        $user=$this->tryViewTeachingPage();
        $date = empty($date) ? date('Ymd') : str_replace(array('-','/'), '', $date);

        $result = $this->getScheduleService()->findOneDaySchedulesByUserId($classId, $user['id'], $date);
        
        return $this->render('TopxiaWebBundle:MyTeaching:mytasks-carousel.html.twig', array(
            'courses' => $result['courses'],
            'lessons' => $result['lessons'],
            'schedules' => $result['schedules'],
            'classes' => $result['classes'],
            ));
    }

    public function getFinshedLessonStudentsAction(Request $request)
    {
        $user=$this->tryViewTeachingPage();
        $lessonId = $request->query->get('lessonId');
        $start = $request->query->get('start');
        $limit = $request->query->get('limit');
        $type = $request->query->get('type');
        $classId = $request->query->get('classId');

        if($classId == 0 && $lessonId) {
            $lesson = $this->getCourseService()->getLesson($lessonId);
            $course = $this->getCourseService()->getCourse($lesson['courseId']);
            $classId = $course['classId'];
        }

        if(empty($lessonId)) {
            $studentIds = array();
            $students = array();
            $lessonId  = -1;
        } else {
           $studentMembers = $this->getClassesService()->findClassStudentMembers($classId);
           $studentMembers = ArrayToolkit::index($studentMembers?:array(), 'userId');
           $studentIds = array_keys($studentMembers);
           $students = $this->getUserService()->findUsersByIds($studentIds);
           $students = ArrayToolkit::index($students?:array(), 'id'); 
        }
        
        $conditions = array(
            'lessonId' => $lessonId,
            'status' => 'finished',
            'userIds' => $studentIds
        );
        $totalCount = $this->getCourseService()->searchLearnCount($conditions);
        $orderby = array('finishedTime', 'ASC');
        
        if($type == 'finished') {
            $learns = $this->getCourseService()->searchLearns($conditions, $orderby, $start, $limit);
            $conditions = array(
                'lessonId' => $lessonId,
                'type' => 'question',
                'userIds' => $studentIds
            );
            $courseThread = $this->getThreadService()->searchThreads($conditions, 'createdTimeASC', 0, 10000);
            $questions = ArrayToolkit::index($courseThread, 'userId');
            return $this->render('TopxiaWebBundle:MyTeaching:finished_lesson_tr.html.twig', array(
                'students' => $students,
                'learns' => $learns,
                'questions' => $questions,
                'start' => $start,
            ));
        } else {
            $learns = $this->getCourseService()->searchLearns($conditions, $orderby, 0, $totalCount);
            $finishedIds = array_keys(ArrayToolkit::index($learns?:array(), 'userId')) ;
            $notFinishedIds = array_diff($studentIds, $finishedIds);
            $limitIds = array_slice($notFinishedIds, $start, $limit, true);
            $result = array();
            foreach ($limitIds as $value) {
                $result[] = $students[$value]; 
            }
      
            return $this->render('TopxiaWebBundle:MyTeaching:not_finished_lesson_tr.html.twig', array(
                'students' => $result,
                'start' => $start,
                'total' => $totalCount,
            )); 
        }
    }

    private function tryViewTeachingPage()
    {
        $user = $this->getCurrentUser();
        if (empty($user)) {
            throw $this->createServiceException('用户不存在或者尚未登录，请先登录');
        }

        if(!$user->isTeacher()) {
            return $this->createMessageResponse('error', '您不是老师，不能查看此页面！');
        }
        return $user;
    }
    
    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Course.ThreadService');
    }

    protected function getScheduleService()
    {
        return $this->getServiceKernel()->createService('Schedule.ScheduleService');
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getClassesService()
    {
        return $this->getServiceKernel()->createService('Classes.ClassesService');
    }

    private function getTestpaperService()
    {
        return $this->getServiceKernel()->createService('Testpaper.TestpaperService');
    }

    private function getHomeworkService()
    {
        return $this->getServiceKernel()->createService('Homework.K12HomeworkService');
    }

}

class TeachingPageDataBuilder extends BaseController
{
    private $result = array();
    private $teacherId;
    private $teachingCourses = array();
    private $teachingCourseIds = array();
    private $dateRange = array();

    public function __construct($teacherId, $dateRange)
    {
        $this->teacherId = $teacherId;
        $this->dateRange = $dateRange;

        $this->teachingCourses = $this->loadMyTeachingCourses();
        $this->teachingCourseIds = ArrayToolkit::column($this->teachingCourses, "id");
    }

    private function loadMyTeachingCourses(){
        $classCourses = array();

        $allCourses = $this->getCourseService()->findUserTeachCourses($this->teacherId, 0, PHP_INT_MAX,false);

        foreach ($allCourses as $course){
            // filter out public courses
            if($course['classId'] != 0){
                $classCourses[] = ($course);
            }
        }

        return $classCourses;
    }

    public function buildTeachingCourses()
    {
        $courses = $this->teachingCourses;
        $courseCount=count($courses);
        $courseList =ArrayToolkit::group($courses,'classId');

        /**如果存在模板课程,则排除这些课程*/
        // if(isset($courseList[0])){
        //     $courseCount-=count($courseList[0]);
        //     unset($courseList[0]);
        // }

        $classIds = array_keys($courseList);
        $classes = $this->getClassesService()->findClassesByIds($classIds);
        $this->result['courses']=$courses;
        $this->result['classes']=$classes;
        $this->result['courseList']=$courseList;
        $this->result['courses']=$courses;
        $this->result['courseCount']=$courseCount;
    }

    public function buildTeachingManageClasses()
    {
        $manageClasses = $this->getClassesService()->getClassesByHeadTeacherId($this->teacherId);
        $this->result['manageClasses']=$manageClasses;
    }

    public function buildPendingHomeworkCounts(){
        $pendingHomeworkCounts = array();

        $courseIds=ArrayToolkit::column($this->teachingCourses, 'id');

        foreach ($courseIds as $courseId){
            $count=$this->getHomeworkService()->findResultsCountsByCourseIdsAndStatus(array($courseId), "reviewing");
            $pendingHomeworkCounts[$courseId] = $count;
        }

        $this->result['pendingHomeworkCounts'] = $pendingHomeworkCounts;
    }

    public function buildPendingTestpaperResultCounts(){
        $courses = $this->getCourseService()->findUserTeachCourses($this->teacherId, 0, PHP_INT_MAX,false);
        $courseIds=ArrayToolkit::column($courses, 'id');

        $this->result['pendingTestpaperResultCounts'] = $this->getTestpaperService()->findTestpaperResultCountByStatusAndTargetIds("reviewing", $courseIds);
    }

    public function buildPendingQuestionCounts(){
        $pendingQuestionCounts = array();
 
        foreach ($this->teachingCourseIds as $courseId){
            $conditions = array(
                'courseIds' => array($courseId),
                'type' => 'question',
                'postNum' => '0'
            );
            $count = $this->getThreadService()->searchThreadCountInCourseIds($conditions);
  
            $pendingQuestionCounts[$courseId] = $count;
        }

        $this->result['pendingQuestionCounts'] = $pendingQuestionCounts;
    }

    public function buildCourseLessonLearnStatus(){
        $finishedLessonCounts = array();
        $finishedLessonPercentages = array();

        $classStudentCounts = $this->findClassStudentCounts(ArrayToolkit::column($this->teachingCourses, 'classId'));

        foreach ($this->teachingCourses as $course){
            $classId = $course['classId'];
            $courseId = $course['id'];
            $courseStudentCount = $classStudentCounts[$classId];

            // How many lessons from this specific course scheduled?
            $scheduledLessons = $this->findScheduledLessons($courseId, $classId);
            $scheduledLessonsCount = count($scheduledLessons);

            // Calculate the lesson learned rate only if there's any lesson scheduled.
            if($scheduledLessonsCount > 0){
                $scheduledLessonIds = ArrayToolkit::column($scheduledLessons, 'lessonId');

                // How many lessons finished?
                $learnedCount = $this->getCourseService()->searchLearnCount(array(
                    'lessonIds'=>$scheduledLessonIds,
                    'status' => 'finished',
                    'startTime' => $this->dateRange['startDate'],
                    'endTime' => $this->dateRange['endDate']
                ));

                $totalLessonsToLearn = $courseStudentCount * $scheduledLessonsCount;
      
                $finishedLessonCounts[$courseId] = $learnedCount . '/' . $totalLessonsToLearn;

                $finishedLessonPercentages[$courseId] = round($learnedCount / $totalLessonsToLearn * 100);
            }
        }

        $this->result['finishedLessonCounts'] = $finishedLessonCounts;
        $this->result['finishedLessonPercentages'] = $finishedLessonPercentages;
    }

    public function buildCourseHomeworkStatus(){
        $finishedHomeworkCounts = array();
        $finishedHomeworkPercentages = array();

        $classStudentCounts = $this->findClassStudentCounts(ArrayToolkit::column($this->teachingCourses, 'classId'));

        foreach ($this->teachingCourses as $course){
            $classId = $course['classId'];
            $courseId = $course['id'];

            $courseStudentCount = $classStudentCounts[$classId];

            $homeworkStatus = $this->getHomeworkStatus($course);

            if(isset($homeworkStatus['homeworkCount'])){
                $homeworksCount = $homeworkStatus['homeworkCount'];
                $homeworkFinishedCount = $homeworkStatus['homeworkFinishedCount'];

                $totalHomeworksToFinish = $courseStudentCount * $homeworksCount;
      
                $finishedHomeworkCounts[$courseId] = $homeworkFinishedCount . '/' . $totalHomeworksToFinish;

                $finishedHomeworkPercentages[$courseId] = round($homeworkFinishedCount / $totalHomeworksToFinish * 100);
            }
        }

        $this->result['finishedHomeworkCounts'] = $finishedHomeworkCounts;
        $this->result['finishedHomeworkPercentages'] = $finishedHomeworkPercentages;
    }

    public function buildCourseHomeworkCorrectPercentages(){
        $homeworkRightItemCounts = array();
        $homeworkRightItemPercentages = array();

        $classStudentCounts = $this->findClassStudentCounts(ArrayToolkit::column($this->teachingCourses, 'classId'));

        foreach ($this->teachingCourses as $course){
            $classId = $course['classId'];
            $courseId = $course['id'];

            $courseStudentCount = $classStudentCounts[$classId];

            $homeworkStatus = $this->getHomeworkStatus($course);

            if(isset($homeworkStatus['homeworkCount'])){
                $homeworksCount = $homeworkStatus['homeworkCount'];
                $homeworkFinishedCount = $homeworkStatus['homeworkFinishedCount'];

                $eachHomeworkItemCounts = $this->getEachHomeworkItemCounts($homeworkStatus['homeworks']);

                $resultStatus = $this->getHomeworkResultStatus($homeworkStatus['homeworkResults'], $eachHomeworkItemCounts);

                if($resultStatus['itemCount'] != 0){
                    $homeworkRightItemCounts[$courseId] = $resultStatus['rightItemCount'] . '/' . $resultStatus['itemCount'];

                    $homeworkRightItemPercentages[$courseId] = round($resultStatus['rightItemCount'] / $resultStatus['itemCount'] * 100);
                }
            }
        }

        $this->result['homeworkRightItemCounts'] = $homeworkRightItemCounts;
        $this->result['homeworkRightItemPercentages'] = $homeworkRightItemPercentages;
    }

    private function getEachHomeworkItemCounts(array $homeworks){
        $counts = array();
        $count = 0;

        foreach ($homeworks as $homework) {
            $counts[$homework['id']] = $homework['itemCount'];
            $count = $count + $homework['itemCount'];
        }

        $counts['total'] = $count;

        return $counts;
    }

    private function getHomeworkResultStatus(array $homeworkResults, array $eachHomeworkItemCounts){

        $rightItemCount = 0;
        $itemCount = 0;

        foreach ($homeworkResults as $result) {
            $rightItemCount = $rightItemCount + $result['rightItemCount'];
            $itemCount = $itemCount + $eachHomeworkItemCounts[$result['homeworkId']] ?: 0;
        }

        return array(
            'rightItemCount'=>$rightItemCount,
            'itemCount'=>$itemCount
        );
    }

    private function getHomeworkStatus($course){
        $homeworkStatus = array();

        $classId = $course['classId'];
        $courseId = $course['id'];

        // How many lessons from this specific course scheduled?
        $scheduledLessons = $this->findScheduledLessons($courseId, $classId);
        $scheduledLessonsCount = count($scheduledLessons);

        // Calculate the homework finished rate only if there's any lesson scheduled.
        if($scheduledLessonsCount > 0 ){
            $scheduledLessonIds = ArrayToolkit::column($scheduledLessons, 'lessonId');
            
            // How many homework scheduled?
            $homeworks = $this->getHomeworkService()->findHomeworksByCourseIdAndLessonIds($courseId, $scheduledLessonIds);
            $homeworksCount = count($homeworks);

            if($homeworksCount > 0){
                // How many homework finished?
                $homeworkFinishedCount = $this->getHomeworkService()->searchResultsCount(array(
                    'lessonIds'=>$scheduledLessonIds,
                    'status' => 'finished'
                ));

                $homeworkResults = $this->getHomeworkService()->searchResults(array(
                    'lessonIds'=>$scheduledLessonIds,
                    'status' => 'finished'
                ), array('id', 'ASC'), 0, PHP_INT_MAX);

                $homeworkStatus['homeworkCount'] = $homeworksCount;
                $homeworkStatus['homeworkFinishedCount'] = $homeworkFinishedCount;
                $homeworkStatus['homeworks'] = $homeworks;
                $homeworkStatus['homeworkResults'] = $homeworkResults;
            }
        }

        return $homeworkStatus;
    }

    private function findScheduledLessons($courseId, $classId){
        $schedules = array();

        $lessons = $this->getCourseService()->getCourseLessons($courseId);

        if(isset($lessons) && count($lessons) > 0){

            $lessonIds = ArrayToolkit::column($lessons, 'id');

            $schedules = $this->getScheduleService()->searchSchedules(array(
                'lessonIds'=>$lessonIds,
                'classId'=>$classId,
                'startTime'=>date("Ymd", $this->dateRange['startDate']),
                'endTime'=>date("Ymd", $this->dateRange['endDate'])
                ));

        }

        return $schedules;
    }

    private function findClassStudentCounts($classIds){
        $result = array();

        foreach ($classIds as $classId){
            $conditions = array(
                'classId'=>$classId,
                'role' => 'STUDENT'
            );

            $count = $this->getClassesService()->searchClassMemberCount($conditions);
  
            $result[$classId] = $count;
        }

        return $result;
    }

    public function getResult(){
        return $this->result;
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Course.ThreadService');
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getClassesService()
    {
        return $this->getServiceKernel()->createService('Classes.ClassesService');
    }

    private function getTestpaperService()
    {
        return $this->getServiceKernel()->createService('Testpaper.TestpaperService');
    }

    private function getHomeworkService()
    {
        return $this->getServiceKernel()->createService('Homework.K12HomeworkService');
    }

    private function getScheduleService()
    {
        return $this->getServiceKernel()->createService('Schedule.ScheduleService');
    }
}