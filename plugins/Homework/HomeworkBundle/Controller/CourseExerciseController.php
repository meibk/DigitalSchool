<?php 
namespace Homework\HomeworkBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\WebBundle\Controller\BaseController;
use Topxia\Common\ArrayToolkit;

class CourseExerciseController extends BaseController
{

	public function startDoAction(Request $Request,$courseId, $exerciseId)
	{
        if (!(is_numeric($courseId)&&is_numeric($exerciseId))){
            throw new \RuntimeException("Id must be number.");
        }

        list($course, $member) = $this->getCourseService()->tryTakeCourse($courseId);

        $exercise = $this->getExerciseService()->getExercise($exerciseId);
        if (empty($exercise)) {
            throw $this->createNotFoundException();
        }

        if ($exercise['courseId'] != $course['id']) {
            throw $this->createNotFoundException();
        }

        $lesson = $this->getCourseService()->getCourseLesson($exercise['courseId'], $exercise['lessonId']);
        if (empty($lesson)) {
            return $this->createMessageResponse('info','作业所属课时不存在！');
        }

        $typeRange = $exercise['questionTypeRange'];
        $typeRange = $this->getquestionTypeRangeStr($typeRange);
        $excludeIds = $this->getRandQuestionIds($typeRange,$exercise['itemCount'],$exercise['source'],$courseId,$exercise['lessonId']);

        $result = $this->getExerciseService()->startExercise($exerciseId,$excludeIds);

        return $this->redirect($this->generateUrl('course_exercise_do', 
            array(
                'courseId' => $result['courseId'],
                'exerciseId' => $result['exerciseId'],
                'resultId' => $result['id'],
            ))
        );
	}



	public function doAction(Request $Request,$courseId,$exerciseId,$resultId)
	{
        $exercise = $this->getExerciseService()->getExercise($exerciseId);
        if (empty($exercise['itemCount']) && empty($exercise['questionTypeRange'])) {
        	throw $this->createNotFoundException();
        }

        list($course, $member) = $this->getCourseService()->tryTakeCourse($exercise['courseId']);

        if ($exercise['courseId'] != $course['id']) {
            throw $this->createNotFoundException();
        }

        $lesson = $this->getCourseService()->getCourseLesson($exercise['courseId'], $exercise['lessonId']);
        
        if (empty($lesson)) {
            return $this->createMessageResponse('info','练习所属课时不存在！');
        }

        $itemSet = $this->getExerciseService()->getItemSetByExerciseId($exercise['id']);
		return $this->render('HomeworkBundle:CourseExercise:do.html.twig', array(
            'exercise' => $exercise,
            'itemSet' => $itemSet,
            'itemCount' => count($itemSet['items']),
            'course' => $course,
            'lesson' => $lesson,
            'questionStatus' => 'doing',
            'questionFor' => 'exercise',
        ));
	}

    public function submitAction(Request $request,$courseId,$exerciseId)
    {
        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            $data = !empty($data['data']) ? $data['data'] : array();
            $result = $this->getExerciseService()->submitExercise($exerciseId,$data);
            $course = $this->getCourseService()->getCourse($courseId);
            $lesson = $this->getCourseService()->getCourseLesson($courseId,$result['lessonId']);
            $this->getExerciseService()->finishExercise($course,$lesson,$courseId,$exerciseId);
            if (!empty($result) && !empty($result['lessonId'])) {
               return $this->createJsonResponse(
                    array(
                        'courseId' => $courseId,
                        'lessonId' => $result['lessonId'],
                        'exerciseId' => $exerciseId,
                        'resultId' => $result['id'],
                        'userId' => $this->getCurrentUser()->id
                        )
                );
            }
        }
    }

    public function resultAction(Request $request, $courseId, $exerciseId, $resultId ,$userId)
    {
        $user = $this->getCurrentUser();
        if (!$user->isLogin()) {
            throw $this->createAccessDeniedException('您尚未登录用户，请登录后再查看！');
        }

        $rolesCount = count(array_intersect($user['roles'], array('ROLE_ADMIN', 'ROLE_SUPER_ADMIN')));
        if ($userId != $user->id && $rolesCount == 0) {
            throw $this->createNotFoundException('不能查看别人的结果页！');
        }

        list($course, $member) = $this->getCourseService()->tryTakeCourse($courseId);
        $exercise = $this->getExerciseService()->getExercise($exerciseId);

        if (empty($exercise)) {
            throw $this->createNotFoundException();
        }

        if ($exercise['courseId'] != $course['id']) {
            throw $this->createNotFoundException();
        }

        $lesson = $this->getCourseService()->getCourseLesson($exercise['courseId'], $exercise['lessonId']);

        if (empty($lesson)) {
            return $this->createMessageResponse('info','作业所属课时不存在！');
        }

        $itemSetResult = $this->getExerciseService()->getItemSetResultByExerciseIdAndUserId($exercise['id'],$userId);
        return $this->render('HomeworkBundle:CourseExercise:result.html.twig', array(
            'exercise' => $exercise,
            'itemSetResult' => $itemSetResult,
            'course' => $course,
            'lesson' => $lesson,
            'questionStatus' => 'finished'
        ));
    }

	private function getquestionTypeRangeStr(array $questionTypeRange)
	{
        $questionTypeRangeStr = "";
		foreach ($questionTypeRange as $key => $questionType) {
			$questionTypeRangeStr .= "'{$questionType}',";
		}
        return substr($questionTypeRangeStr, 0,-1);
	}

    private function getAllQuestions($typeRange, $questionSource, $courseId, $lessonId){
        $questionsCount = $this->getQuestionService()->findQuestionsCountbyTypesAndSource($typeRange,$questionSource,$courseId,$lessonId);

        $questions = $this->getQuestionService()->findQuestionsByTypesAndSourceAndExcludeUnvalidatedMaterial($typeRange, 0, $questionsCount,$questionSource,$courseId,$lessonId);

        return ArrayToolkit::index($questions,'id');
    }

    private function getRandQuestionIds($typeRange, $itemCount, $questionSource, $courseId, $lessonId)
    {
        $currentCourseQizs = $this->getAllQuestions($typeRange, $questionSource, $courseId, $lessonId);

        $lesson = $this->getCourseService()->getLesson($lessonId);
        $course = $this->getCourseService()->getCourse($courseId);

        // Merge with parent's questions when both this lesson and the course are still linked.
        if($lesson['sourceId'] != '0' && $lesson['contentChanged'] == '0' && $course['structureChanged'] == '0'){
            $parentLessonId = $lesson['sourceId'];
            $parentCourseId = $course['parentId'];

            $parentCourseQizs = $this->getAllQuestions($typeRange, $questionSource, $parentCourseId, $parentLessonId);

            $currentCourseQizs += $parentCourseQizs;
        }

        $includeIds = array_rand($currentCourseQizs, $itemCount);

        if (!is_array($includeIds)) {
            $includeIds = array($includeIds);
        }

        return $includeIds;
    }

	private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

	private function getExerciseService()
	{
        return $this->getServiceKernel()->createService('Homework:Homework.ExerciseService');
	}

	private function getQuestionService()
    {
        return $this->getServiceKernel()->createService('Question.QuestionService');
    }
}