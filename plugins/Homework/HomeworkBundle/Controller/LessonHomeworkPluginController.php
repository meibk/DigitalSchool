<?php
namespace Homework\HomeworkBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\WebBundle\Controller\BaseController;

class LessonHomeworkPluginController extends BaseController
{

    public function listAction (Request $request)
    {
        $user = $this->getCurrentUser();
        list($course, $member) = $this->getCourseService()->tryTakeCourse($request->query->get('courseId'));

        $lesson = $this->getCourseService()->getCourseLesson($course['id'], $request->query->get('lessonId'));

        $homework = $this->getHomeworkService()->getHomeworkByLessonId($lesson['id']);
        $exercise = $this->getExerciseService()->getExerciseByLessonId($lesson['id']);
        $homeworkResult = $this->getHomeworkService()->getResultByHomeworkIdAndUserId($homework['id'], $user['id']);
        $homeworkItemsResult = $this->getHomeworkService()->findItemResultsbyHomeworkIdAndUserId($homework['id'], $user['id']);

        return $this->render('HomeworkBundle:LessonHomeworkPlugin:list.html.twig', array(
            'course' => $course,
            'lesson' => $lesson,
            'homework' => $homework,
            'exercise' => $exercise,
            'homeworkResult' => $homeworkResult,
            'homeworkItemsResult' => $homeworkItemsResult
        ));
    }

    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    private function getHomeworkService()
    {
        return $this->getServiceKernel()->createService('Homework:Homework.HomeworkService');
    } 

    private function getExerciseService()
    {
        return $this->getServiceKernel()->createService('Homework:Homework.ExerciseService');
    }

}