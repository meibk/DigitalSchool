<?php  
namespace Homework\Service\Homework\Impl;

use Topxia\Service\Common\BaseService;
use Homework\Service\Homework\HomeworkService;
use Topxia\Common\ArrayToolkit;

class HomeworkServiceImpl extends BaseService implements HomeworkService
{

	public function getHomework($id)
	{
		return $this->getHomeworkDao()->getHomework($id);
	}

	public function findHomeworksByCourseIdAndLessonIds($courseId, $lessonIds)
	{
		$homeworks = $this->getHomeworkDao()->findHomeworksByCourseIdAndLessonIds($courseId, $lessonIds);
        return ArrayToolkit::index($homeworks, 'lessonId');
	}

    public function findHomeworksByCourseIdAndstatus($courseId, $status)
    {
        return $this->getHomeworkDao()->findHomeworksByCourseIdAndstatus($courseId, $status);
    }

	public function findHomeworksByCreatedUserId($userId)
	{
		return $this->getHomeworkDao()->findHomeworksByCreatedUserId($userId);
	}

	public function getHomeworkByLessonId($lessonId)
	{
		return $this->getHomeworkDao()->getHomeworkByLessonId($lessonId);
	}

	public function getResult($id)
	{
        return $this->getResultDao()->getResult($id);
	}

	public function createHomework($courseId,$lessonId,$fields)
	{
		if(empty($fields)){
			$this->createServiceException("内容为空，创建作业失败！");
		}

		$course = $this->getCourseService()->getCourse($courseId);

		if (empty($course)) {
			throw $this->createServiceException('课程不存在，创建作业失败！');
		}

		$lesson = $this->getCourseService()->getCourseLesson($courseId,$lessonId);

		if (empty($lesson)) {
			throw $this->createServiceException('课时不存在，创建作业失败！');
		}

		$excludeIds = $fields['excludeIds'];

		if (empty($excludeIds)) {
			$this->createServiceException("题目不能为空，创建作业失败！");
		}

		unset($fields['excludeIds']);

		$fields = $this->filterHomeworkFields($fields,$mode = 'add');
        $fields['courseId'] = $courseId;
        $fields['lessonId'] = $lessonId;
        $excludeIds = explode(',',$excludeIds);
        $fields['itemCount'] = count($excludeIds);
        $fields['updatedUserId'] = 0;
        $fields['updatedTime'] = 0;
        $homework = $this->getHomeworkDao()->addHomework($fields);
		$this->addHomeworkItems($homework['id'],$excludeIds);

        if(empty($course['parentId'])){
            $this->addChildHomeworks($lesson['id'],$homework,$excludeIds);
        }else if(empty($lesson['contentChanged'])){
            $this->getCourseService()->updateLessonContentChanged($lesson['id'],1);
        }
		
		$this->getLogService()->info('homework','create','创建课程{$courseId}课时{$lessonId}的作业');
		
		return $homework;
	}

    public function updateHomework($id, $fields)
    {
    	$homework = $this->getHomework($id);

    	if (empty($homework)) {
    		throw $this->createServiceException('作业不存在，更新作业失败！');
    	}
        $course = $this->getCourseService()->getCourse($homework['courseId']);
        $lesson =$this->getCourseService()->getLesson($homework['lessonId']);
        if (empty($course)) {
            throw $this->createServiceException('课程不存在，更新作业失败！');
        }

		$fields = $this->filterHomeworkFields($fields,$mode = 'edit');

        if(empty($course['parentId'])){
            $this->updateChildHomeworks($homework['lessonId'],$fields);
        }else if(empty($lesson['contentChanged'])){
            $this->getCourseService()->updateLessonContentChanged($lesson['id'],1);
        }

		$homework = $this->getHomeworkDao()->updateHomework($id,$fields);

		$this->getLogService()->info('homework','update','更新课程{$courseId}课时{$lessonId}的{$id}作业');
		
		return $homework;
    }

    public function removeHomework($id)
    {
        $homework = $this->getHomework($id);
        if (empty($homework)) {
            throw $this->createServiceException('作业不存在，删除作业失败！');
        }
        $course = $this->getCourseService()->getCourse($homework['courseId']);
        if(empty($course)){
            throw $this->createServiceException('课程不存在，删除作业失败！');
        }
        $lesson=$this->getCourseService()->getLesson($homework['lessonId']);
        if(empty($course['parentId'])){
            $this->deleteChildHomeworks($homework['lessonId']);
        }else if(empty($lesson['contentChanged'])){
            $this->getCourseService()->updateLessonContentChanged($lesson['id'],1);
        }
        $this->deleteHomeworkItemsByHomeworkId($id);
        $this->getHomeworkDao()->deleteHomework($id);
        $this->getItemResultDao()->deleteItemResultsByHomeworkId($id);
        $this->getResultDao()->deleteResultsByHomeworkId($id);

        return true;
    }

    public function showHomework($id)
    {
        $itemResults = $this->getTestpaperItemResultDao()->findTestResultsByTestpaperResultId($testpaperResultId);
        $itemResults = ArrayToolkit::index($itemResults, 'questionId');

        $testpaperResult = $this->getTestpaperResultDao()->getTestpaperResult($testpaperResultId);

        $items = $this->getTestpaperItems($testpaperResult['testId']);
        $items = ArrayToolkit::index($items, 'questionId');

        $questions = $this->getQuestionService()->findQuestionsByIds(ArrayToolkit::column($items, 'questionId'));
        $questions = ArrayToolkit::index($questions, 'id');

        $questions = $this->completeQuestion($items, $questions);

        $formatItems = array();
        foreach ($items as $questionId => $item) {

            if (array_key_exists($questionId, $itemResults)){
                $questions[$questionId]['testResult'] = $itemResults[$questionId];
            }

            $items[$questionId]['question'] = $questions[$questionId];

            if ($item['parentId'] != 0) {
                if (!array_key_exists('items', $items[$item['parentId']])) {
                    $items[$item['parentId']]['items'] = array();
                }
                $items[$item['parentId']]['items'][$questionId] = $items[$questionId];
                $formatItems['material'][$item['parentId']]['items'][$item['seq']] = $items[$questionId];
                unset($items[$questionId]);
            } else {
                $formatItems[$item['questionType']][$item['questionId']] = $items[$questionId];
            }

        }

        if ($isAccuracy){
            $accuracy = $this->makeAccuracy($items);
        }

        ksort($formatItems);
        return array(
            'formatItems' => $formatItems,
            'accuracy' => $isAccuracy ? $accuracy : null
        );
    }

    public function startHomework($id)
    {
        $homework = $this->getHomeworkDao()->getHomework($id);

        if (empty($homework)) {
            throw $this->createServiceException('课时作业不存在！');
        }

        $course = $this->getCourseService()->getCourse($homework['courseId']);
        if (empty($course)) {
            throw $this->createServiceException('作业所属课程不存在！');
        }

        $lesson = $this->getCourseService()->getCourseLesson($homework['courseId'], $homework['lessonId']);
        if (empty($lesson)) {
            throw $this->createServiceException('作业所属课时不存在！');
        }

        $user = $this->getCurrentUser();

        $homeworkResult = $this->getResultDao()->getResultByHomeworkIdAndUserId($id,$user->id);

        $result = $this->getResultDao()->getResultByHomeworkIdAndStatusAndUserId($id,'doing',$user->id);
        if (empty($result)){
            $homeworkResult = array(
                'homeworkId' => $homework['id'],
                'courseId' => $homework['courseId'],
                'lessonId' =>  $homework['lessonId'],
                'userId' => $this->getCurrentUser()->id,
                'checkTeacherId' => $homework['createdUserId'],
                'status' => 'doing',
                'usedTime' => time(),
                'createdTime' => time()
            );

            return $this->getResultDao()->addResult($homeworkResult);
        } else {
            return $result;
        }
    }

    public function checkHomework($id,$userId,$checkHomeworkData)
    {
        $homeworkResult = $this->getResultDao()->getResultByHomeworkIdAndUserId($id,$userId);
        if (empty($homeworkResult)) {
            throw $this->createServiceException();
        }
        $fields['status'] = 'finished';
        $fields['checkedTime'] = time();
        $fields['teacherSay'] = empty($checkHomeworkData['teacherFeedback']) ? "" : $checkHomeworkData['teacherFeedback'];
        $this->getResultDao()->updateResult($homeworkResult['id'],$fields);

        if (empty($checkHomeworkData['questionIds'])) {
            return true;
        }

        foreach ($checkHomeworkData['questionIds'] as $key => $questionId) {
                if (!empty($checkHomeworkData['teacherSay'][$key])) {
                $itemResult = $this->getItemResultDao()->getItemResultByResultIdAndQuestionId($homeworkResult['id'],$questionId);
                $this->getItemResultDao()->updateItemResult($itemResult['id'],array('teacherSay'=>$checkHomeworkData['teacherSay'][$key]));
            }
        }
        return true;
    }

    public function finishHomework($course,$lesson,$courseId,$homeworkId)
    {
        $this->getStatusService()->publishStatus(array(
            'type' => 'finished_homework',
            'objectType' => 'homework',
            'objectId' => $homeworkId,
            'properties' => array(
                'course' => $course,
                'homework' => $homeworkId,
                'lesson' => $lesson,
            )
        ));
    }
    private function getStatusService()
    {
        return $this->createService('User.StatusService');
    }


    public function submitHomework($id,$homework)
    {
        $this->addItemResult($id, $homework);

        $homeworkResult = $this->getResultDao()->getResultByHomeworkIdAndUserId($id, $this->getCurrentUser()->id);

        $rightItemCount = 0;

        $homeworkItemsRusults = $this->getItemResultDao()->findItemResultsbyHomeworkId($id);

        foreach ($homeworkItemsRusults as $key => $homeworkItemRusult) {
            if ($homeworkItemRusult['status'] == 'right' and $homeworkItemRusult['homeworkResultId'] == $homeworkResult['id'] ){
               $rightItemCount++;
            }
        }

        $homeworkitemResult['rightItemCount'] = $rightItemCount;
        $homeworkitemResult['status'] = 'reviewing';
        $homeworkitemResult['updatedTime'] = time();

        $result = $this->getResultDao()->updateResult($homeworkResult['id'],$homeworkitemResult);

        return $result;
    }

    public function saveHomework($id,$homework)
    {
        $userId = $this->getCurrentUser()->id;
        $homeworkItemResults = $this->getItemResultDao()->findItemResultsbyHomeworkIdAndUserId($id,$userId);
        if (empty($homeworkItemResults)) {
           $this->addItemResult($id,$homework);
        }
        $homeworkResult = $this->getResultDao()->getResultByHomeworkIdAndUserId($id, $userId);
        foreach ($homework as $questionId => $value) {
            $answer = $value['answer'];
            if (count($answer) > 1) {
                $answer = implode(",", $answer);
            } else {
                $answer = $answer[0];
            }
            $itemResult = $this->getItemResultDao()->getItemResultByResultIdAndQuestionId($homeworkResult['id'],$questionId);
            $this->getItemResultDao()->updateItemResult($itemResult['id'],array('answer'=>$answer));
        }
        return $homeworkResult;
    }

    public function getResultByLessonIdAndUserId($lessonId, $userId)
    {
        return $this->getResultDao()->getResultByLessonIdAndUserId($lessonId, $userId);
    }

    public function getResultByHomeworkId($homeworkId)
    {
        return $this->getResultDao()->getResultByHomeworkId($homeworkId);
    }

    public function getResultByHomeworkIdAndUserId($homeworkId, $userId)
    {
    	return $this->getResultDao()->getResultByHomeworkIdAndUserId($homeworkId, $userId);
    }

    public function searchResults($conditions, $orderBy, $start, $limit)
    {
    	return $this->getResultDao()->searchResults($conditions, $orderBy, $start, $limit);
    }

    public function searchResultsCount($conditions)
    {
    	return $this->getResultDao()->searchResultsCount($conditions);
    }

    public function findResultsByLessonId($lessonId)
    {
    	return $this->getResultDao()->findResultsByLessonId($lessonId);
    }
    
    public function findResultsByLessonIdAndStatus($lessonId,$status)
    {
        return $this->getResultDao()->findResultsByLessonIdAndStatus($lessonId,$status);
    }

    public function findResultsByHomeworkIds($homeworkIds)
    {
    	return $this->getResultDao()->findResultsByHomeworkIds($homeworkIds);
    }

    public function findResultsByStatusAndCheckTeacherId($status,$checkTeacherId,$orderBy,$start,$limit)
    {
        return $this->getResultDao()->findResultsByStatusAndCheckTeacherId($status,$checkTeacherId,$orderBy,$start,$limit);
    }

    public function findResultsCountsByStatusAndCheckTeacherId($status,$checkTeacherId)
    {
        return $this->getResultDao()->findResultsCountsByStatusAndCheckTeacherId($status,$checkTeacherId);
    }

    public function findResultsByCourseIdAndStatus($courseId, $status ,$orderBy,$start,$limit)
    {
        return $this->getResultDao()->findResultsByCourseIdAndStatus($courseId, $status ,$orderBy,$start,$limit);
    }

    public function findResultsCountsByCourseIdAndStatus($courseId, $status)
    {
        return $this->getResultDao()->findResultsCountsByCourseIdAndStatus($courseId, $status);
    }

    public function findResultsByCourseIdsAndStatus(array $courseIds,$status,$orderBy,$start,$limit)
    {
        return $this->getResultDao()->findResultsByCourseIdsAndStatus($courseIds, $status ,$orderBy,$start,$limit);
    }
    
    public function findResultsCountsByCourseIdsAndStatus(array $courseIds,$status)
    {
        return $this->getResultDao()->findResultsCountsByCourseIdsAndStatus($courseIds, $status);
    }

    public function findItemsByHomeworkId($homeworkId)
    {
		return $this->getHomeworkItemDao()->findItemsByHomeworkId($homeworkId);
    }

    public function getItemSetByHomeworkId($homeworkId)
    {
        $items = $this->getHomeworkItemDao()->findItemsByHomeworkId($homeworkId);
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

    public function getItemSetResultByHomeworkIdAndUserId($homeworkId,$userId)
    {
        $items = $this->getHomeworkItemDao()->findItemsByHomeworkId($homeworkId);
        $itemsResults = $this->getItemResultDao()->findItemResultsbyHomeworkIdAndUserId($homeworkId,$userId);
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

    public function findItemResultsbyHomeworkIdAndUserId($homeworkId,$userId)
    {
        return $this->getItemResultDao()->findItemResultsbyHomeworkIdAndUserId($homeworkId,$userId);
    }

	public function createHomeworkItems($homeworkId, $items)
	{
		$homework = $this->getHomework($homeworkId);

		if (empty($homework)) {
			throw $this->createServiceException('课时作业不存在，创建作业题目失败！');
		}

		$this->getHomeworkItemDao()->addItem($items);
	}

    private function addItemResult($id,$homework)
    {
        $homeworkResult = $this->getResultByHomeworkIdAndUserId($id, $this->getCurrentUser()->id);
        $homeworkItems = $this->findItemsByHomeworkId($id);
        $itemResult = array();
        $homeworkitemResult = array();
        foreach ($homeworkItems as $key => $homeworkItem) {
            if (!empty($homework[$homeworkItem['questionId']])) {

                if (!empty($homework[$homeworkItem['questionId']]['answer'])) {

                    $answer = $homework[$homeworkItem['questionId']]['answer'];

                    $answerArray = array();

                    if (count($answer)>1) {
                        $answerArray = $answer;
                        $answer = implode(",", $answerArray);
                    } else {
                        $answer = $answer[0];
                        $answerArray = array($answer);
                    }

                    $result = $this->getQuestionService()->judgeQuestion($homeworkItem['questionId'], $answerArray);
                    $status = $result['status'];
                } else {
                    $answer = null;
                    $status = "noAnswer";
                }

            } else {
                $answer = null;
                $status = "noAnswer";

            }

            $itemResult['itemId'] = $homeworkItem['id'];
            $itemResult['homeworkId'] = $homeworkItem['homeworkId'];
            $itemResult['homeworkResultId'] = $homeworkResult['id'];
            $itemResult['questionId'] = $homeworkItem['questionId'];
            $itemResult['userId'] = $this->getCurrentUser()->id;
            $itemResult['status'] = $status;
            $itemResult['answer'] = $answer;
            $this->getItemResultDao()->addItemResult($itemResult);
        }
    }

	private function addHomeworkItems($homeworkId,$excludeIds)
	{
        $homeworkItems = array();
        $homeworkItemsSub = array();
        $includeItemsSubIds = array();
        $index = 1;

		foreach ($excludeIds as $key => $excludeId) {

            $questions = $this->getQuestionService()->findQuestionsByParentId($excludeId);

            $items['seq'] = $index;
            $items['questionId'] = $excludeId;
            $items['homeworkId'] = $homeworkId;
            $items['parentId'] = 0;
            $homeworkItems[] = $this->getHomeworkItemDao()->addItem($items);
           

            if (!empty($questions)) {
                foreach ($questions as $key => $question) {
                    $items['seq'] = $index;
                    $items['questionId'] = $question['id'];
                    $items['homeworkId'] = $homeworkId;
                    $items['parentId'] = $question['parentId'];
                    $homeworkItems[] = $this->getHomeworkItemDao()->addItem($items);
                    $index++;  
                }
                $index -= 1;
            }
             $index++;
        }
	}

    private function deleteHomeworkItemsByHomeworkId($homeworkId)
    {
    	$homeworkItems = $this->getHomeworkItemDao()->findItemsByHomeworkId($homeworkId);

    	foreach ($homeworkItems as $key => $homeworkItem) {
    		$this->getHomeworkItemDao()->deleteItem($homeworkItem['id']);
    	}

    }



















    /**--------------------------k12api----------------------------*/
    public function addHomework($homework)
    {
        return $this->getHomeworkDao()->addHomework($homework);
    }

    public function addHomeworkItem($item)
    {
        return $this->getHomeworkItemDao()->addItem($item);
    }
    
    public function addChildHomeworks($sourceLessonId,$homework,$itemIds)
    {
        $lessons=$this->getCourseService()->findLessonsBySourceId($sourceLessonId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourseService()->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                $homework['courseId']=$course['id'];
                $homework['lessonId']=$lesson['id'];
                unset($homework['id']);
                $newHomework = $this->getHomeworkDao()->addHomework($homework);
                $this->addHomeworkItems($newHomework['id'],$itemIds);
            }
        }
    }

    public function deleteChildHomeworks($sourceLessonId)
    {
        $lessons=$this->getCourseService()->findLessonsBySourceId($sourceLessonId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourseService()->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                $homework=$this->getHomeworkByLessonId($lesson['id']);
                $this->deleteHomeworkItemsByHomeworkId($homework['id']);
                $this->getHomeworkDao()->deleteHomework($homework['id']);
                $this->getItemResultDao()->deleteItemResultsByHomeworkId($homework['id']);
                $this->getResultDao()->deleteResultsByHomeworkId($homework['id']);
            }
        }
    }

    public function updateChildHomeworks($sourceLessonId,$fields)
    {
        $lessons=$this->getCourseService()->findLessonsBySourceId($sourceLessonId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourseService()->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                $homework=$this->getHomeworkByLessonId($lesson['id']);
                $this->getHomeworkDao()->updateHomework($homework['id'],$fields);
            }
        }
    }














    private function getCourseService()
    {
        return $this->createService('Course.CourseService');
    }

    private function getHomeworkService()
    {
        return $this->createService('Course.HomeworkService');
    }

    private function getQuestionService()
    {
    	return $this->createService('Question.QuestionService');
    }

    private function getLogService()
    {
    	return $this->createService('System.LogService');
    }

    private function getHomeworkDao()
    {
    	return $this->createDao('Homework:Homework.HomeworkDao');
    }

	private function getHomeworkItemDao()
    {
    	return $this->createDao('Homework:Homework.HomeworkItemDao');
    }

    private function getItemResultDao()
    {
        return $this->createDao('Homework:Homework.HomeworkItemResultDao');
    }

    private function getResultDao()
    {
    	return $this->createDao('Homework:Homework.HomeworkResultDao');
    }

	private function filterHomeworkFields($fields,$mode)
	{
		$fields['description'] = $fields['description'];

		if ($mode == 'add') {
			$fields['createdUserId'] = $this->getCurrentUser()->id;
			$fields['createdTime'] = time();
		}

		if ($mode == 'edit') {
			$fields['updatedUserId'] = $this->getCurrentUser()->id;
			$fields['updatedTime'] = time();
		}

		return $fields;
	}
}