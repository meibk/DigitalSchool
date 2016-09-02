<?php
namespace Homework\Service\Homework\Impl;

use Topxia\Service\Common\BaseService;
use Homework\Service\Homework\ExerciseService;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\StringToolkit;

class ExerciseServiceImpl extends BaseService implements ExerciseService
{

    public function getExercise($id)
    {
        $exercise = $this->getExerciseDao()->getExercise($id);
        if (empty($exercise)) {
            return null;
        }
        $exercise['questionTypeRange'] = json_decode($exercise['questionTypeRange'], true);
        return $exercise;
    }

    public function getExerciseByLessonId($lessonId)
    {
        $exercise = $this->getExerciseDao()->getExerciseByLessonId($lessonId);
        if (empty($exercise)) {
            return null;
        }
        $exercise['questionTypeRange'] = json_decode($exercise['questionTypeRange'], true);
        return $exercise;
    }

    public function getItemSetResultByExerciseIdAndUserId($exerciseId,$userId)
    {
        $items = $this->getExerciseItemDao()->findItemsByExerciseId($exerciseId);
        $itemsResults = $this->getItemResultDao()->findItemResultsbyExerciseIdAndUserId($exerciseId,$userId);
        $indexdItems = ArrayToolkit::index($items, 'questionId');
        $indexdItemsResults = ArrayToolkit::index($itemsResults, 'questionId');

        $questions = $this->getQuestionService()->findQuestionsByIds(array_keys($indexdItems));
        $i = 0;
        $validQuestionIds = array();

        foreach ($indexdItems as $index => $item) {

            $item['question'] = empty($questions[$item['questionId']]) ? null : $questions[$item['questionId']];
            if (empty($item['parentId'])) {
                $indexdItems[$index] = $item;
                $indexdItems[$index]['itemResult'] = $indexdItemsResults[$index];
                $i = 0;
                continue;
            }

            if (empty($indexdItems[$item['parentId']]['subItems'])) {
                $indexdItems[$item['parentId']]['subItems'] = array();
                $i = 0;
            }

            $indexdItems[$item['parentId']]['subItems'][] = $item;
            $indexdItems[$item['parentId']]['subItems'][$i]['itemResult'] = $indexdItemsResults[$index];
            $i++;

            unset($indexdItems[$item['questionId']]);
        }

        $set = array(
            'items' => array_values($indexdItems),
            'questionIds' => array(),
            'total' => 0,
        );

        foreach ($set['items'] as $item) {
            if (!empty($item['subItems'])) {
                $set['total'] += count($item['subItems']);
                $set['questionIds'] = array_merge($set['questionIds'], ArrayToolkit::column($item['subItems'],'questionId'));
            } else {
                $set['total'] ++;
                $set['questionIds'][] = $item['questionId'];
            }
        }

        return $set;
    }

    public function getItemSetByExerciseId($exerciseId)
    {
        $items = $this->getExerciseItemDao()->findItemsByExerciseId($exerciseId);
        $indexdItems = ArrayToolkit::index($items, 'questionId');
        $questions = $this->getQuestionService()->findQuestionsByIds(array_keys($indexdItems));

        $validQuestionIds = array();

        foreach ($indexdItems as $index => $item) {

            $item['question'] = empty($questions[$item['questionId']]) ? null : $questions[$item['questionId']];
            if (empty($item['parentId'])) {
                $indexdItems[$index] = $item;
                continue;
            }

            if (empty($indexdItems[$item['parentId']]['subItems'])) {
                $indexdItems[$item['parentId']]['subItems'] = array();
            }

            $indexdItems[$item['parentId']]['subItems'][] = $item;
            unset($indexdItems[$item['questionId']]);
        }
        $set = array(
            'items' => array_values($indexdItems),
            'questionIds' => array(),
            'total' => 0,
        );

        foreach ($set['items'] as $item) {
            if (!empty($item['subItems'])) {
                $set['total'] += count($item['subItems']);
                $set['questionIds'] = array_merge($set['questionIds'], ArrayToolkit::column($item['subItems'],'questionId'));
            } else {
                $set['total'] ++;
                $set['questionIds'][] = $item['questionId'];
            }
        }

        return $set;
    }

    public function createExercise($fields)
    {   
        if (!ArrayToolkit::requireds($fields, array('courseId', 'lessonId', 'questionCount', 'difficulty', 'ranges', 'source'))) {
            throw $this->createServiceException('参数缺失，创建练习失败！');
        }
        $course=$this->getCourseService()->getCourse($fields['courseId']);
        $lesson=$this->getCourseService()->getLesson($fields['lessonId']);
        if(empty($course)){
            throw $this->createServiceException('课程不存在，创建练习失败！');
        }
        $exercise = $this->getExerciseByLessonId($fields['lessonId']);

        if (!empty($exercise)) {
            $this->getExerciseDao()->deleteExercise($exercise['id']);
        }

        $this->getLogService()->info('exercise', 'create', "创建练习(#{$exercise['id']})");

        $newExercise=$this->getExerciseDao()->addExercise($this->filterExerciseFields($fields));
        if(empty($course['parentId'])){
            $this->addChildExercises($fields['lessonId'],$newExercise);
        }else if(empty($lesson['contentChanged'])){
            $this->getCourseService()->updateLessonContentChanged($lesson['id'],1);
        }
        return $newExercise;
    }

    public function startExercise($id,$excludeIds)
    {
        $exercise = $this->getExerciseDao()->getExercise($id);
        if (empty($exercise)) {
            throw $this->createServiceException('课时练习不存在！');
        }

        $course = $this->getCourseService()->getCourse($exercise['courseId']);
        if (empty($course)) {
            throw $this->createServiceException('练习所属课程不存在！');
        }

        $lesson = $this->getCourseService()->getCourseLesson($exercise['courseId'], $exercise['lessonId']);
        if (empty($lesson)) {
            throw $this->createServiceException('练习所属课时不存在！');
        }

        $user = $this->getCurrentUser();

        $result = $this->getExerciseResultDao()->getExerciseResultByExerciseIdAndStatusAndUserId($id,$user->id, 'doing');

        $items = $this->getExerciseItemDao()->findItemsbyExerciseId($exercise['id']);
        if (!empty($items)) {
            $exerciseFields = $exercise;
            unset($exerciseFields['id']);
            $exercise = $this->getExerciseDao()->addExercise($exerciseFields);
            $result = $this->getExerciseResultDao()->getExerciseResultByExerciseIdAndStatusAndUserId($id,$user->id, 'doing');
        }

        if (empty($result)){
            //add questions
            $this->addExerciseItems($exercise['id'],$excludeIds);

            $exerciseResultFields = array(
                'exerciseId' => $exercise['id'],
                'courseId' => $exercise['courseId'],
                'lessonId' =>  $exercise['lessonId'],
                'userId' => $this->getCurrentUser()->id,
                'status' => 'doing',
                'usedTime' => time(),
                'createdTime' => time()
            );

            return $this->getExerciseResultDao()->addExerciseResult($exerciseResultFields);
        } else {
            return $result;
        }
    }

    public function finishExercise($course,$lesson,$courseId,$exerciseId)
    {
        $this->getStatusService()->publishStatus(array(
            'type' => 'finished_exercise',
            'objectType' => 'exercise',
            'objectId' => $exerciseId,
            'properties' => array(
                'course' => $course,
                'lesson' => $lesson,
                'exercise' => $exerciseId,
            )
        ));
    }
    private function getStatusService()
    {
        return $this->createService('User.StatusService');
    }

    public function submitExercise($id,$exercise)
    {
        $this->addItemResult($id,$exercise);
        //finished
        $rightItemCount = 0;

        $exerciseItemsRusults = $this->getItemResultDao()->findItemResultsbyExerciseId($id);

        foreach ($exerciseItemsRusults as $key => $exerciseItemRusult) {
            if ($exerciseItemRusult['status'] == 'right') {
               $rightItemCount++;
            }
        }

        $exerciseitemResult['rightItemCount'] = $rightItemCount;
        $exerciseitemResult['status'] = 'finished';
        $exerciseitemResult['updatedTime'] = time();

        $exerciseResult = $this->getExerciseResultDao()->getExerciseResultByexerciseIdAndUserId($id, $this->getCurrentUser()->id);

        $result = $this->getExerciseResultDao()->updateExerciseResult($exerciseResult['id'],$exerciseitemResult);

        return $result;
    }

    private function addItemResult($id,$exercise)
    {
        $exerciseResult = $this->getExerciseResultByExerciseIdAndUserId($id, $this->getCurrentUser()->id);
        $exerciseItems = $this->findExerciseItemsByExerciseId($id);
        $itemResult = array();
        $exerciseitemResult = array();

        foreach ($exerciseItems as $key => $exerciseItem) {
            if (!empty($exercise[$exerciseItem['questionId']])) {

                if (!empty($exercise[$exerciseItem['questionId']]['answer'])) {

                    $answer = $exercise[$exerciseItem['questionId']]['answer'];

                    if(is_array($answer)){
                        $answerArray = $answer;
                    }else{
                        $answerArray = array($answer);
                    }

                    if (count($answer)>1) {
                        $answer = implode(",", $answer);
                    } else {
                        $answer = $answer[0];
                    }

                    $result = $this->getQuestionService()->judgeQuestion($exerciseItem['questionId'], $answerArray);
                    $status = $result['status'];
                } else {
                    $answer = null;
                    $status = "noAnswer";
                }

            } else {
                $answer = null;
                $status = "noAnswer";

            }

            $itemResult['itemId'] = $exerciseItem['id'];
            $itemResult['exerciseId'] = $exerciseItem['exerciseId'];
            $itemResult['exerciseResultId'] = $exerciseResult['id'];
            $itemResult['questionId'] = $exerciseItem['questionId'];
            $itemResult['userId'] = $this->getCurrentUser()->id;
            $itemResult['status'] = $status;
            $itemResult['answer'] = $answer;

            $this->getItemResultDao()->addItemResult($itemResult);
        }
    }

    private function addExerciseItems($exerciseId,$excludeIds)
    {
        $exerciseItems = array();
        $index = 1;
        foreach ($excludeIds as $key => $excludeId) {

            $questions = $this->getQuestionService()->findQuestionsByParentId($excludeId);

            $items['seq'] = $index;
            $items['questionId'] = $excludeId;
            $items['exerciseId'] = $exerciseId;
            $items['parentId'] = 0;
            $exerciseItems[] = $this->getExerciseItemDao()->addItem($items);

            if (!empty($questions)) {
                foreach ($questions as $key => $question) {
                    $items['seq'] = $index;
                    $items['questionId'] = $question['id'];
                    $items['exerciseId'] = $exerciseId;
                    $items['parentId'] = $question['parentId'];
                    $exerciseItems[] = $this->getExerciseItemDao()->addItem($items);
                    $index++;
                }
                $index -= 1;
            }
             $index++;
        }
    }

    public function updateExercise($id, $fields)
    {
        if (!ArrayToolkit::requireds($fields, array('courseId', 'lessonId', 'questionCount', 'difficulty', 'ranges', 'source'))) {
            throw $this->createServiceException('参数缺失，更新练习失败！');
        }

        $exercise = $this->getExercise($id);
        if(empty($exercise)){
            throw $this->createServiceException('练习不存在，更新练习失败！');
        }
        $course = $this->getCourseService()->getCourse($exercise['courseId']);
        $lesson = $this->getCourseService()->getLesson($exercise['lessonId']);
        if(empty($course)){
            throw $this->createServiceException('课程不存在，更新练习失败！');
        }
        $fields=$this->filterExerciseFields($fields);
        if(empty($course['parentId'])){
            $this->updateChildExercises($exercise['lessonId'],$fields);
        }else if(empty($lesson['contentChanged'])){
            $this->getCourseService()->updateLessonContentChanged($lesson['id'],1);
        }

        $exercise = $this->getExerciseDao()->updateExercise($exercise['id'], $fields);

        $this->getLogService()->info('exercise', 'update', "编辑练习(#{$exercise['id']})");

        return $exercise;
    }

    public function deleteExercisesByLessonId($lessonId)
    {   
        $lesson=$this->getCourseService()->getLesson($lessonId);
        $course=$this->getCourseService()->getCourse($lesson['courseId']);
        if(empty($course['parentId'])){
            $this->deleteChildExercises($lessonId);
        }else if(empty($lesson['contentChanged'])){
            $this->getCourseService()->updateLessonContentChanged($lesson['id'],1);
        }
    
        $exercises = $this->getExerciseDao()->findExercisesByLessonId($lessonId);
        foreach ($exercises as $exercise) {
            $this->getExerciseItemDao()->deleteItemByExerciseId($exercise['id']); 
            $this->getItemResultDao()->deleteItemResultByExerciseId($exercise['id']); 
            $this->getExerciseDao()->deleteExercise($exercise['id']); 
            $this->getExerciseResultDao()->deleteExerciseResultByExerciseId($exercise['id']);
        }
        $this->getLogService()->info('exercise', 'delete', "删除课时(#{$lessonId})的练习");

        return true;
    }

    public function canBuildExercise($fields)
    {
        $questionsCount = count($this->getQuestions($fields));
        if ($questionsCount < $fields['questionCount']) {
            $lessNum = $fields['questionCount'] - $questionsCount;
            return array('status' => 'no', 'lessNum' => $lessNum);
        } else {
            return array('status' => 'yes');
        }
    }

    public function findExerciseItemsByExerciseId($exerciseId)
    {
        return $this->getExerciseItemDao()->findItemsByExerciseId($exerciseId);
    }

    public function findExercisesByLessonIds($lessonIds)
    {
        $exercises = $this->getExerciseDao()->findExercisesByLessonIds($lessonIds);
        return ArrayToolkit::index($exercises, 'lessonId');
    }  

    public function filterExerciseFields($fields)
    {
        $filtedFields = array();    
        $filtedFields['itemCount'] = $fields['questionCount'];
        $filtedFields['source'] = $fields['source'];
        $filtedFields['courseId'] = $fields['courseId'];
        $filtedFields['lessonId'] = $fields['lessonId'];
        $filtedFields['difficulty'] = empty($fields['difficulty']) ? '' : $fields['difficulty'];
        $filtedFields['questionTypeRange'] = json_encode($fields['ranges']);
        $filtedFields['createdUserId'] = $this->getCurrentUser()->id;
        $filtedFields['createdTime']   = time();
       
        return $filtedFields;
    }

    private function getQuestions($fields)
    {
        $conditions = array();

        if (!empty($fields['difficulty'])) {
            $conditions['difficulty'] = $fields['difficulty'];
        }

        if ($fields['source'] == 'lesson') {
            $conditions['target'] = 'course-'.$fields['courseId'].'/lesson-'.$fields['lessonId'];
        } else {
            $conditions['targetPrefix'] = 'course-'.$fields['courseId'];
        }
        $conditions['types'] = $fields['ranges'];
        $conditions['parentId'] = 0;
        $conditions['excludeUnvalidatedMaterial'] = $fields['excludeUnvalidatedMaterial'];

        $total = $this->getQuestionService()->searchQuestionsCount($conditions);

        return $this->getQuestionService()->searchQuestions($conditions, array('createdTime', 'DESC'), 0, $total);
    }

    private function canBuildWithQuestions($fields, $questions)
    {
        $missing = array();

        foreach ($fields['counts'] as $type => $needCount) {
            $needCount = intval($needCount);
            if ($needCount == 0) {
                continue;
            }

            if (empty($questions[$type])) {
                $missing[$type] = $needCount;
                continue;
            }

            if (count($questions[$type]) < $needCount) {
                $missing[$type] = $needCount - count($questions[$type]);
            }
        }

        if (empty($missing)) {
            return array('status' => 'yes');
        }

        return array('status' => 'no', 'missing' => $missing);
    }

    public function getExerciseResultByExerciseIdAndUserId($exerciseId, $userId)
    {
        return $this->getExerciseResultDao()->getExerciseResultByExerciseIdAndUserId($exerciseId, $userId);
    }

    /**--------------------------k12api----------------------------*/
    public function addExercise($exercise)
    {
        return $this->getExerciseDao()->addExercise($exercise);
    }

    public function addChildExercises($sourceLessonId,$exercise)
    {
        $lessons=$this->getCourseService()->findLessonsBySourceId($sourceLessonId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourseService()->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                $ex=$this->getExerciseByLessonId($lesson['id']);
                if(!empty($ex)){
                    $this->getExerciseDao()->deleteExercise($ex['id']);
                }
                $exercise['courseId']=$course['id'];
                $exercise['lessonId']=$lesson['id'];
                unset($exercise['id']);
                $this->getExerciseDao()->addExercise($exercise);
            }
        }
    }

    public function updateChildExercises($sourceLessonId,$fields)
    {
        $lessons=$this->getCourseService()->findLessonsBySourceId($sourceLessonId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourseService()->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                $ex=$this->getExerciseByLessonId($lesson['id']);
                $fields['courseId']=$course['id'];
                $fields['lessonId']=$lesson['id'];
                $this->getExerciseDao()->updateExercise($ex['id'], $fields);
            }
        }
    }

    public function deleteChildExercises($sourceLessonId)
    {
        $lessons=$this->getCourseService()->findLessonsBySourceId($sourceLessonId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourseService()->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                $exercises = $this->getExerciseDao()->findExercisesByLessonId($lesson['id']);
                foreach ($exercises as $exercise) {
                    $this->getExerciseItemDao()->deleteItemByExerciseId($exercise['id']); 
                    $this->getItemResultDao()->deleteItemResultByExerciseId($exercise['id']); 
                    $this->getExerciseDao()->deleteExercise($exercise['id']); 
                    $this->getExerciseResultDao()->deleteExerciseResultByExerciseId($exercise['id']);
                }
            }
        }
    }

    private function getExerciseResultDao()
    {
        return $this->createDao('Homework:Homework.ExerciseResultDao');
    }

    private function getItemResultDao()
    {
        return $this->createDao('Homework:Homework.ExerciseItemResultDao');
    }

    private function getExerciseItemDao()
    {
        return $this->createDao('Homework:Homework.ExerciseItemDao');
    }

    protected function getExerciseDao()
    {
        return $this->createDao('Homework:Homework.ExerciseDao');
    }

    protected function getCourseService()
    {
        return $this->createService('Course.CourseService');
    }

    protected function getQuestionService()
    {
        return $this->createService('Question.QuestionService');
    }

    protected function getLogService()
    {
        return $this->createService('System.LogService');        
    }

}