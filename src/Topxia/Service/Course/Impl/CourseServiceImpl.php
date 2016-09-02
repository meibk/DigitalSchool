<?php
namespace Topxia\Service\Course\Impl;

use Symfony\Component\HttpFoundation\File\File;
use Topxia\Service\Common\BaseService;
use Topxia\Service\Common\ServiceEvent;
use Topxia\Service\Course\CourseService;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\StringToolkit;
use Topxia\Service\Util\LiveClientFactory;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CourseServiceImpl extends BaseService implements CourseService
{

	/**
	 * Course API
	 */

	public function findCoursesByIds(array $ids)
	{
		$courses = CourseSerialize::unserializes(
            $this->getCourseDao()->findCoursesByIds($ids)
        );
        return ArrayToolkit::index($courses, 'id');
	}

	public function findCoursesByClassId($classId)
	{
		return $this->getCourseDao()->findCoursesByClassId($classId);
	}

	public function findCoursesByLikeTitle($title)
	{
		$coursesUnserialized = $this->getCourseDao()->findCoursesByLikeTitle($title);
		$courses = CourseSerialize::unserializes($coursesUnserialized);
        return ArrayToolkit::index($courses, 'id');		
	}

	public function findCoursesByTagIdsAndStatus(array $tagIds, $status, $start, $limit)
	{
		$courses = CourseSerialize::unserializes(
            $this->getCourseDao()->findCoursesByTagIdsAndStatus($tagIds, $status, $start, $limit)
        );
        return ArrayToolkit::index($courses, 'id');
	}

	public function findCoursesByAnyTagIdsAndStatus(array $tagIds, $status, $orderBy, $start, $limit)
	{
		$courses = CourseSerialize::unserializes(
            $this->getCourseDao()->findCoursesByAnyTagIdsAndStatus($tagIds, $status, $orderBy, $start, $limit)
        );
        return ArrayToolkit::index($courses, 'id');
    }

	public function findMinStartTimeByCourseId($courseId)
	{
		return  $this->getLessonDao()->findMinStartTimeByCourseId($courseId);
	}

	public function getLesson($id)
	{
		return $this->getLessonDao()->getLesson($id);
	}

	public function findLessonsByIds(array $ids)
	{
		$lessons = $this->getLessonDao()->findLessonsByIds($ids);
		$lessons = LessonSerialize::unserializes($lessons);
        return ArrayToolkit::index($lessons, 'id');
	}

	public function findLessonLearnsByIds($userId, array $lessonIds)
	{
		$lessonLearns = $this->getLessonLearnDao()->findLessonLearnsByIds($userId, $lessonIds);
		return ArrayToolkit::index($lessonLearns, 'lessonId');
	}

	public function findLessonsByCourseIds(array $courseIds)
	{
		$lessons = $this->getLessonDao()->findLessonsByCourseIds($courseIds);
		$lessons = LessonSerialize::unserializes($lessons);
        return ArrayToolkit::index($lessons, 'id');
	}
	
	public function getCourse($id, $inChanging = false)
	{
		return CourseSerialize::unserialize($this->getCourseDao()->getCourse($id));
	}

	public function getCoursesCount()
	{
		return $this->getCourseDao()->getCoursesCount();
	}

	public function findCoursesCountByLessThanCreatedTime($endTime)
	{
	        	return $this->getCourseDao()->findCoursesCountByLessThanCreatedTime($endTime);
	}

	public function analysisCourseSumByTime($endTime)
    	{
        		return $this->getCourseDao()->analysisCourseSumByTime($endTime);
    	}

	public function searchCourses($conditions, $sort = 'latest', $start, $limit)
	{
		$conditions = $this->_prepareCourseConditions($conditions);
		if ($sort == 'popular') {
			$orderBy =  array('hitNum', 'DESC');
		} else if ($sort == 'recommended') {
			$orderBy = array('recommendedTime', 'DESC');
		} else if ($sort == 'Rating') {
			$orderBy = array('Rating' , 'DESC');
		} else if ($sort == 'hitNum') {
			$orderBy = array('hitNum' , 'DESC');
		} else if ($sort == 'studentNum') {
			$orderBy = array('studentNum' , 'DESC');
		} elseif ($sort == 'recommendedSeq') {
			$orderBy = array('recommendedSeq', 'ASC');
		}elseif ($sort == 'createdTimeByAsc') {
			$orderBy = array('createdTime', 'ASC');
		}elseif($sort == 'freeNow'){
			$orderBy =array('freeEndTime','ASC');
		}elseif($sort == 'freeComing'){
			$orderBy =array('freeStartTime','ASC');
		}else {
			$orderBy = array('createdTime', 'DESC');
		}
		
		return CourseSerialize::unserializes($this->getCourseDao()->searchCourses($conditions, $orderBy, $start, $limit));
	}

	public function searchCourseCount($conditions)
	{
		$conditions = $this->_prepareCourseConditions($conditions);
		return $this->getCourseDao()->searchCourseCount($conditions);
	}

	private function _prepareCourseConditions($conditions)
	{
		$conditions = array_filter($conditions,function($value) {
			if($value == '0') {
				return true;
			}else {
				return !empty($value);
			}
			
		});
		if (isset($conditions['date'])) {
			$dates = array(
				'yesterday'=>array(
					strtotime('yesterday'),
					strtotime('today'),
				),
				'today'=>array(
					strtotime('today'),
					strtotime('tomorrow'),
				),
				'this_week' => array(
					strtotime('Monday this week'),
					strtotime('Monday next week'),
				),
				'last_week' => array(
					strtotime('Monday last week'),
					strtotime('Monday this week'),
				),
				'next_week' => array(
					strtotime('Monday next week'),
					strtotime('Monday next week', strtotime('Monday next week')),
				),
				'this_month' => array(
					strtotime('first day of this month midnight'), 
					strtotime('first day of next month midnight'),
				),
				'last_month' => array(
					strtotime('first day of last month midnight'),
					strtotime('first day of this month midnight'),
				),
				'next_month' => array(
					strtotime('first day of next month midnight'),
					strtotime('first day of next month midnight', strtotime('first day of next month midnight')),
				),
			);

			if (array_key_exists($conditions['date'], $dates)) {
				$conditions['startTimeGreaterThan'] = $dates[$conditions['date']][0];
				$conditions['startTimeLessThan'] = $dates[$conditions['date']][1];
				unset($conditions['date']);
			}
		}

		if (isset($conditions['creator'])) {
			$users = $this->getUserService()->searchUsers(
				array('truename'=>$conditions['creator']),
				array('createdTime', 'ASC'),
				0,
				PHP_INT_MAX);
			$conditions['userIds'] = empty($users) ? array(-1):ArrayToolkit::column($users,'id');
			unset($conditions['creator']);
		}

		if (isset($conditions['categoryId'])) {
			$childrenIds = $this->getCategoryService()->findCategoryChildrenIds($conditions['categoryId']);
			$conditions['categoryIds'] = array_merge(array($conditions['categoryId']), $childrenIds);
			unset($conditions['categoryId']);
		}

		if(isset($conditions['nickname'])){
			$user = $this->getUserService()->getUserByNickname($conditions['nickname']);
			$conditions['userId'] = $user ? $user['id'] : -1;
			unset($conditions['nickname']);
		}
		
		return $conditions;
	}

	public function findUserLearnCourses($userId, $start, $limit)
	{
		$members = $this->getMemberDao()->findMembersByUserIdAndRole($userId, 'student', $start, $limit);

		$courses = $this->findCoursesByIds(ArrayToolkit::column($members, 'courseId'));
		foreach ($members as $member) {
			if (empty($courses[$member['courseId']])) {
				continue;
			}
			$courses[$member['courseId']]['memberIsLearned'] = $member['isLearned'];
			$courses[$member['courseId']]['memberLearnedNum'] = $member['learnedNum'];
		}
		return $courses;
	}

	public function findUserLearnCourseCount($userId)
	{
		return $this->getMemberDao()->findMemberCountByUserIdAndRole($userId, 'student', 0);
	}

	public function findUserLeaningCourseCount($userId)
	{
		return $this->getMemberDao()->findMemberCountByUserIdAndRoleAndIsLearned($userId, 'student', 0);
	}

	public function findUserLeaningCourses($userId, $start, $limit)
	{
		$members = $this->getMemberDao()->findMembersByUserIdAndRoleAndIsLearned($userId, 'student', '0', $start, $limit);

		$courses = $this->findCoursesByIds(ArrayToolkit::column($members, 'courseId'));

		$sortedCourses = array();
		foreach ($members as $member) {
			if (empty($courses[$member['courseId']])) {
				continue;
			}
			$course = $courses[$member['courseId']];
			$course['memberIsLearned'] = 0;
			$course['memberLearnedNum'] = $member['learnedNum'];
			$sortedCourses[] = $course;
		}
		return $sortedCourses;
	}

	public function findUserLeanedCourseCount($userId)
	{
		return $this->getMemberDao()->findMemberCountByUserIdAndRoleAndIsLearned($userId, 'student', 1);
	}

	public function findUserLeanedCourses($userId, $start, $limit)
	{
		$members = $this->getMemberDao()->findMembersByUserIdAndRoleAndIsLearned($userId, 'student', '1', $start, $limit);
		$courses = $this->findCoursesByIds(ArrayToolkit::column($members, 'courseId'));

		$sortedCourses = array();
		foreach ($members as $member) {
			if (empty($courses[$member['courseId']])) {
				continue;
			}
			$course = $courses[$member['courseId']];
			$course['memberIsLearned'] = 1;
			$course['memberLearnedNum'] = $member['learnedNum'];
			$sortedCourses[] = $course;
		}
		return $sortedCourses;
	}

	public function findUserTeachCourseCount($userId, $onlyPublished = true)
	{
		return $this->getMemberDao()->findMemberCountByUserIdAndRole($userId, 'teacher', $onlyPublished);
	}

	public function findUserTeachCourses($userId, $start, $limit, $onlyPublished = true)
	{
		$members = $this->getMemberDao()->findMembersByUserIdAndRole($userId, 'teacher', $start, $limit, $onlyPublished);

		$courses = $this->findCoursesByIds(ArrayToolkit::column($members, 'courseId'));

		/**
		 * @todo 以下排序代码有共性，需要重构成一函数。
		 */
		$sortedCourses = array();
		foreach ($members as $member) {
			if (empty($courses[$member['courseId']])) {
				continue;
			}
			$sortedCourses[] = $courses[$member['courseId']];
		}
		return $sortedCourses;
	}

	public function findUserFavoritedCourseCount($userId)
	{
		return $this->getFavoriteDao()->getFavoriteCourseCountByUserId($userId);
	}

	public function findUserFavoritedCourses($userId, $start, $limit)
	{
		$courseFavorites = $this->getFavoriteDao()->findCourseFavoritesByUserId($userId, $start, $limit);
		$favoriteCourses = $this->getCourseDao()->findCoursesByIds(ArrayToolkit::column($courseFavorites, 'courseId'));
		return CourseSerialize::unserializes($favoriteCourses);
	}	

	public function createCourse($course)
	{
		if (!ArrayToolkit::requireds($course, array('title'))) {
			throw $this->createServiceException('缺少必要字段，创建课程失败！');
		}

		$hasCatalog = $course['hasCatalog'];
		$course = ArrayToolkit::parts($course, array('title', 'type','about', 'categoryId', 'tags', 'price', 'startTime', 'endTime', 'locationId', 'address', 'gradeId', 'subjectId', 'materialId', 'term'));

		$course['gradeId'] = empty($course['gradeId']) ? 0 : $course['gradeId'];
		$course['materialId'] = empty($course['materialId']) ? 0 : $course['materialId'];
		$course['subjectId'] = empty($course['subjectId']) ? 0 : $course['subjectId'];
		$course['status'] = 'draft';
        $course['about'] = !empty($course['about']) ? $this->getHtmlPurifier()->purify($course['about']) : '';
        $course['tags'] = !empty($course['tags']) ? $course['tags'] : '';
		$course['userId'] = $this->getCurrentUser()->id;
		$course['createdTime'] = time();
		//$course['teacherIds'] = array($course['userId']);
		$course = $this->getCourseDao()->addCourse(CourseSerialize::serialize($course));
		
		if($hasCatalog) {
			$firstLevel = $this->getKnowledgeService()->findNodesData(0, array(
				'subjectId' => $course['subjectId'], 
				'gradeId' => $course['gradeId'],
				'materialId' => $course['materialId'],
				'term' => $course['term']));
			foreach ($firstLevel as $knowledge) {
				$chapter = array(
					'title' => $knowledge['name'],
					'type' => 'chapter',
					'courseId' => $course['id']
				);
				$this->createChapter($chapter);
				$children = $this->getKnowledgeService()->findKnowledgeByParentId($knowledge['id']);
				foreach ($children as $child) {
					$unit = array(
						'title' => $child['name'],
						'type' => 'unit',
						'courseId' => $course['id']
					); 
					$this->createChapter($unit);
				}
			}
		}
		$member = array(
			'courseId' => $course['id'],
			'userId' => $course['userId'],
			'role' => 'teacher',
			'createdTime' => time(),
		);

		$this->getMemberDao()->addMember($member);

		$course = $this->getCourse($course['id']);

		$this->getLogService()->info('course', 'create', "创建课程《{$course['title']}》(#{$course['id']})");

		return $course;
	}

	public function updateCourse($id, $fields)
	{
		$course = $this->getCourseDao()->getCourse($id);
		if (empty($course)) {
			throw $this->createServiceException('课程不存在，更新失败！');
		}
		$fields = $this->_filterCourseFields($fields);

		$this->getLogService()->info('course', 'update', "更新课程《{$course['title']}》(#{$course['id']})的信息", $fields);

		$fields = CourseSerialize::serialize($fields);
		if(empty($course['parentId'])){
            $this->updateChildCourses($id, $fields);
        }else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($id, array('structureChanged'=>1));
        }
		return CourseSerialize::unserialize(
			$this->getCourseDao()->updateCourse($id, $fields)
		);
	}

	public function updateCourseCounter($id, $counter)
	{
		$fields = ArrayToolkit::parts($counter, array('rating', 'ratingNum', 'lessonNum', 'giveCredit'));
		$course = $this->getCourse($id);
        if(empty($course)){
            throw $this->createServiceException('课程不存在，更新计数器失败！');
        }
        if (empty($fields)) {
			throw $this->createServiceException('参数不正确，更新计数器失败！');
		}
        if(empty($course['parentId'])){
            $this->updateChildCourses($id, $fields);
        }
		$this->getCourseDao()->updateCourse($id, $fields);
	}

	private function _filterCourseFields($fields)
	{
		$fields = ArrayToolkit::filter($fields, array(
			'title' => '',
			'subtitle' => '',
			'about' => '',
			'expiryDay' => 0,
			'showStudentNumType' => 'opened',
			'serializeMode' => 'none',
			'categoryId' => 0,
			'vipLevelId' => 0,
			'goals' => array(),
			'audiences' => array(),
			'tags' => '',
			'price' => 0.00,
			'coinPrice'=>0.00,
			'startTime' => 0,
			'endTime'  => 0,
			'locationId' => 0,
			'address' => '',
			'maxStudentNum' => 0,
			'classId' => 0,
			'term' => 'first',
			'gradeId' => 0,
			'subjectId' => 0,
			'materialId' => 0,
			'freeStartTime' => 0,
			'freeEndTime' => 0,
			'compulsory' => 0,
			'deadlineNotify' => 'none',
			'daysOfNotifyBeforeDeadline' => 0

		));
		if (!empty($fields['about'])) {
			$fields['about'] = $this->purifyHtml($fields['about'],true);
		}

		if (!empty($fields['tags'])) {
			$fields['tags'] = explode(',', $fields['tags']);
			$fields['tags'] = $this->getTagService()->findTagsByNames($fields['tags']);
			array_walk($fields['tags'], function(&$item, $key) {
				$item = (int) $item['id'];
			});
		}
		return $fields;
	}

    public function changeCoursePicture ($courseId, $filePath, array $options)
    {
        $course = $this->getCourseDao()->getCourse($courseId);
        if (empty($course)) {
            throw $this->createServiceException('课程不存在，图标更新失败！');
        }

        $pathinfo = pathinfo($filePath);
        $imagine = new Imagine();
        $rawImage = $imagine->open($filePath);

        $largeImage = $rawImage->copy();
        $largeImage->crop(new Point($options['x'], $options['y']), new Box($options['width'], $options['height']));
        $largeImage->resize(new Box(480, 270));
        $largeFilePath = "{$pathinfo['dirname']}/{$pathinfo['filename']}_large.{$pathinfo['extension']}";
        $largeImage->save($largeFilePath, array('quality' => 90));
        $largeFileRecord = $this->getFileService()->uploadFile('course', new File($largeFilePath));

        $largeImage->resize(new Box(304, 171));
        $middleFilePath = "{$pathinfo['dirname']}/{$pathinfo['filename']}_middle.{$pathinfo['extension']}";
        $largeImage->save($middleFilePath, array('quality' => 90));
        $middleFileRecord = $this->getFileService()->uploadFile('course', new File($middleFilePath));

        $largeImage->resize(new Box(96, 54));
        $smallFilePath = "{$pathinfo['dirname']}/{$pathinfo['filename']}_small.{$pathinfo['extension']}";
        $largeImage->save($smallFilePath, array('quality' => 90));
        $smallFileRecord = $this->getFileService()->uploadFile('course', new File($smallFilePath));

        $fields = array(
        	'smallPicture' => $smallFileRecord['uri'],
        	'middlePicture' => $middleFileRecord['uri'],
        	'largePicture' => $largeFileRecord['uri'],
    	);

        if(empty($course['parentId']) || !empty($course['structureChanged'])){
	    	@unlink($filePath);
	    	$oldPictures = array(
	            'smallPicture' => $course['smallPicture'] ? $this->getKernel()->getParameter('topxia.upload.public_directory') . '/' . str_replace('public://', '', $course['smallPicture']) : null,
	            'middlePicture' => $course['middlePicture'] ? $this->getKernel()->getParameter('topxia.upload.public_directory') . '/' . str_replace('public://', '', $course['middlePicture']) : null,
	            'largePicture' => $course['largePicture'] ? $this->getKernel()->getParameter('topxia.upload.public_directory') . '/' . str_replace('public://', '', $course['largePicture']) : null
	        );
	        array_map(function($oldPicture){
	        	if (!empty($oldPicture)){
		            @unlink($oldPicture);
	        	}
	        }, $oldPictures);
        }

		$this->getLogService()->info('course', 'update_picture', "更新课程《{$course['title']}》(#{$course['id']})图片", $fields);
        if(empty($course['parentId'])){
            $this->updateChildCourses($courseId, $fields);
        }else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($courseId, array('structureChanged'=>1));
        }
        return $this->getCourseDao()->updateCourse($courseId, $fields);
    }

	public function recommendCourse($id, $number)
	{
		$course = $this->tryAdminCourse($id);

		if (!is_numeric($number)) {
			throw $this->createAccessDeniedException('推荐课程序号只能为数字！');
		}

		$course = $this->getCourseDao()->updateCourse($id, array(
			'recommended' => 1,
			'recommendedSeq' => (int)$number,
			'recommendedTime' => time(),
		));

		$this->getLogService()->info('course', 'recommend', "推荐课程《{$course['title']}》(#{$course['id']}),序号为{$number}");

		return $course;
	}

	public function hitCourse($id)
	{
		$checkCourse = $this->getCourse($id);

		if(empty($checkCourse)){
			throw $this->createServiceException("课程不存在，操作失败。");
		}

		$this->getCourseDao()->waveCourse($id, 'hitNum', +1);
	}

	public function cancelRecommendCourse($id)
	{
		$course = $this->tryAdminCourse($id);

		$this->getCourseDao()->updateCourse($id, array(
			'recommended' => 0,
			'recommendedTime' => 0,
			'recommendedSeq' => 0,
		));

		$this->getLogService()->info('course', 'cancel_recommend', "取消推荐课程《{$course['title']}》(#{$course['id']})");
	}

	public function deleteCourse($id)
	{
		$course = $this->tryAdminCourse($id);

		// Decrease the course lesson files usage counts, if there are files used by the course lessons.
		$lessons = $this->getLessonDao()->findLessonsByCourseId($id);

		if(!empty($lessons)){
			$fileIds = ArrayToolkit::column($lessons, "mediaId");

			if(!empty($fileIds)){
				$this->getUploadFileService()->decreaseFileUsedCount(array_unique($fileIds));
			}
		}

		// Delete all linked course materials (the UsedCount of each material file will also be decreaased.)
		$this->getCourseMaterialService()->deleteMaterialsByCourseId($id);

		// Delete course related data
		if(empty($course['parentId'])){
			$this->deleteChildCourses($course['id']);
		}

		$this->getMemberDao()->deleteMembersByCourseId($id);
		$this->getLessonDao()->deleteLessonsByCourseId($id);
		$this->getChapterDao()->deleteChaptersByCourseId($id);

		$this->getCourseDao()->deleteCourse($id);

		if($course["type"] == "live"){
			$this->getCourseLessonReplayDao()->deleteLessonReplayByCourseId($id);
		}

		$this->getLogService()->info('course', 'delete', "删除课程《{$course['title']}》(#{$course['id']})");

		return true;
	}

	public function publishCourse($id)
	{
		$course = $this->tryManageCourse($id);
        if(empty($course['parentId'])){
            $this->updateChildCourses($id, array('status' => 'published'));
        }else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($course['id'], array('structureChanged'=>1));
        }
		$this->getCourseDao()->updateCourse($id, array('status' => 'published'));
		$this->getLogService()->info('course', 'publish', "发布课程《{$course['title']}》(#{$course['id']})");
	}

	public function closeCourse($id)
	{
		$course = $this->tryManageCourse($id);
        if(empty($course['parentId'])){
            $this->updateChildCourses($id, array('status' => 'closed'));
        }else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($course['id'], array('structureChanged'=>1));
        }
		$this->getCourseDao()->updateCourse($id, array('status' => 'closed'));
		$this->getLogService()->info('course', 'close', "关闭课程《{$course['title']}》(#{$course['id']})");
	}

	public function favoriteCourse($courseId)
	{
		$user = $this->getCurrentUser();
		if (empty($user['id'])) {
			throw $this->createAccessDeniedException();
		}

		$course = $this->getCourse($courseId);
		if($course['status']!='published'){
			throw $this->createServiceException('不能收藏未发布课程');
		}

		if (empty($course)) {
			throw $this->createServiceException("该课程不存在,收藏失败!");
		}

		$favorite = $this->getFavoriteDao()->getFavoriteByUserIdAndCourseId($user['id'], $course['id']);
		if($favorite){
			throw $this->createServiceException("该收藏已经存在，请不要重复收藏!");
		}
		//添加动态
		$this->getStatusService()->publishStatus(array(
			'type' => 'favorite_course',
			'objectType' => 'course',
			'objectId' => $courseId,
			'properties' => array(
				'course' => $this->simplifyCousrse($course),
			)
		));

		$this->getFavoriteDao()->addFavorite(array(
			'courseId'=>$course['id'],
			'userId'=>$user['id'], 
			'createdTime'=>time()
		));

		return true;
	}

	public function unFavoriteCourse($courseId)
	{
		$user = $this->getCurrentUser();
		if (empty($user['id'])) {
			throw $this->createAccessDeniedException();
		}

		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createServiceException("该课程不存在,收藏失败!");
		}

		$favorite = $this->getFavoriteDao()->getFavoriteByUserIdAndCourseId($user['id'], $course['id']);
		if(empty($favorite)){
			throw $this->createServiceException("你未收藏本课程，取消收藏失败!");
		}

		$this->getFavoriteDao()->deleteFavorite($favorite['id']);

		return true;
	}

	public function hasFavoritedCourse($courseId)
	{
		$user = $this->getCurrentUser();
		if (empty($user['id'])) {
			return false;
		}

		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createServiceException("课程{$courseId}不存在");
		}

		$favorite = $this->getFavoriteDao()->getFavoriteByUserIdAndCourseId($user['id'], $course['id']);

		return $favorite ? true : false;
	}

	public function analysisCourseDataByTime($startTime,$endTime)
	{
    	return $this->getCourseDao()->analysisCourseDataByTime($startTime,$endTime);
	}

	public function waveLearningTime($lessonId,$userId,$time)
	{
		$learn=$this->getLessonLearnDao()->getLearnByUserIdAndLessonId($userId,$lessonId);

		if($learn['status']!="finished")
		$this->getLessonLearnDao()->updateLearn($learn['id'], array(
				'learnTime' => $learn['learnTime']+intval($time),
		));
	}

	public function waveWatchingTime($userId,$lessonId,$time)
	{
		$learn=$this->getLessonLearnDao()->getLearnByUserIdAndLessonId($userId,$lessonId);

		if($learn['status']!="finished" && $learn['videoStatus']=="playing")
		$this->getLessonLearnDao()->updateLearn($learn['id'], array(
				'watchTime' => $learn['watchTime']+intval($time),
		));
	}

	public function watchPlay($userId,$lessonId)
	{
		$learn=$this->getLessonLearnDao()->getLearnByUserIdAndLessonId($userId,$lessonId);

		$this->getLessonLearnDao()->updateLearn($learn['id'], array(
				'videoStatus' => 'playing',
		));
	}

	public function watchPaused($userId,$lessonId)
	{
		$learn=$this->getLessonLearnDao()->getLearnByUserIdAndLessonId($userId,$lessonId);

		$this->getLessonLearnDao()->updateLearn($learn['id'], array(
				'videoStatus' => 'paused',
		));
	}

	public function uploadCourseFile($targetType, $targetId, array $fileInfo=array(), $implemtor='local', UploadedFile $originalFile=null)
	{
		return $this->getUploadFileService()->addFile($targetType, $targetId, $fileInfo, $implemtor, $originalFile);
	}


	private function autosetCourseFields($courseId)
	{
		$fields = array('type' => 'text', 'lessonNum' => 0);
		$lessons = $this->getCourseLessons($courseId);
		if (empty($lessons)) {
			$this->getCourseDao()->updateCourse($courseId, $fields);
			return ;
		}

        $counter = array('text' => 0, 'video' => 0);

        foreach ($lessons as $lesson) {
            $counter[$lesson['type']] ++;
            $fields['lessonNum'] ++;
        }

        $percents = array_map(function($value) use ($fields) {
        	return $value / $fields['lessonNum'] * 100;
        }, $counter);

        if ($percents['video'] > 50) {
            $fields['type'] = 'video';
        } else {
            $fields['type'] = 'text';
        }

		$this->getCourseDao()->updateCourse($courseId, $fields);

	}

	/**
	 * Lesslon API
	 */

	public function getCourseLesson($courseId, $lessonId)
	{
		$lesson = $this->getLessonDao()->getLesson($lessonId);
		if (empty($lesson) or ($lesson['courseId'] != $courseId)) {
			return null;
		}
		return LessonSerialize::unserialize($lesson);
	}

	public function findCourseDraft($courseId,$lessonId, $userId)
	{
		$draft = $this->getCourseDraftDao()->findCourseDraft($courseId,$lessonId, $userId);
		if (empty($draft) or ($draft['userId'] != $userId)) {
			return null;
		}
		return LessonSerialize::unserialize($draft);
	}

	public function getCourseLessons($courseId)
	{
		$lessons = $this->getLessonDao()->findLessonsByCourseId($courseId);
		return LessonSerialize::unserializes($lessons);
	}

	public function deleteCourseDrafts($courseId,$lessonId, $userId)
	{
		 return   $this->getCourseDraftDao()->deleteCourseDrafts($courseId,$lessonId, $userId);
	}

	public function findLessonsByTypeAndMediaId($type, $mediaId)
	{
		$lessons = $this->getLessonDao()->findLessonsByTypeAndMediaId($type, $mediaId);
		return LessonSerialize::unserializes($lessons);
	}

	public function searchLessons($conditions, $orderBy, $start, $limit)
	{
		return $this->getLessonDao()->searchLessons($conditions, $orderBy, $start, $limit);
	}

	public function searchLessonCount($conditions)
	{
		return $this->getLessonDao()->searchLessonCount($conditions);
	}

	public function getCourseDraft($id)
	{
	        return $this->getCourseDraftDao()->getCourseDraft($id);
	}

	public function createCourseDraft($draft)
	{
		$draft = ArrayToolkit::parts($draft, array('userId', 'title', 'courseId', 'summary', 'content','lessonId','createdTime'));
		$draft['userId'] = $this->getCurrentUser()->id;
		$draft['createdTime'] = time();
		$draft = $this->getCourseDraftDao()->addCourseDraft($draft);
		return $draft;
	}

	public function createLesson($lesson)
	{
		$lesson = ArrayToolkit::filter($lesson, array(
			'courseId' => 0,
			'chapterId' => 0,
			'free' => 0,
			'title' => '',
			'summary' => '',
			'tags' => array(),
			'type' => 'text',
			'content' => '',
			'media' => array(),
			'mediaId' => 0,
			'length' => 0,
			'startTime' => 0,
			'giveCredit' => 0,
			'requireCredit' => 0,
			'liveProvider' => 'none',
		));

		if (!ArrayToolkit::requireds($lesson, array('courseId', 'title', 'type'))) {
			throw $this->createServiceException('参数缺失，创建课时失败！');
		}

		if (empty($lesson['courseId'])) {
			throw $this->createServiceException('添加课时失败，课程ID为空。');
		}

		$course = $this->getCourse($lesson['courseId'], true);
		if (empty($course)) {
			throw $this->createServiceException('添加课时失败，课程不存在。');
		}

		if (!in_array($lesson['type'], array('text', 'audio', 'video', 'testpaper', 'live', 'ppt','document'))) {
			throw $this->createServiceException('课时类型不正确，添加失败！');
		}


		$this->fillLessonMediaFields($lesson);

		//课程内容的过滤 @todo
		// if(isset($lesson['content'])){
		// 	$lesson['content'] = $this->purifyHtml($lesson['content']);
		// }
		if (isset($fields['title'])) {
			$fields['title'] = $this->purifyHtml($fields['title']);
		}

		// 课程处于发布状态时，新增课时，课时默认的状态为“未发布"
		$lesson['status'] = $course['status'] == 'published' ? 'unpublished' : 'published';
		$lesson['free'] = empty($lesson['free']) ? 0 : 1;
		$lesson['number'] = $this->getNextLessonNumber($lesson['courseId']);
		$lesson['seq'] = $this->getNextCourseItemSeq($lesson['courseId']);
		$lesson['userId'] = $this->getCurrentUser()->id;
		$lesson['createdTime'] = time();

		$lastChapter = $this->getChapterDao()->getLastChapterByCourseId($lesson['courseId']);
		$lesson['chapterId'] = empty($lastChapter) ? 0 : $lastChapter['id'];
		if ($lesson['type'] == 'live') {
			$lesson['endTime'] = $lesson['startTime'] + $lesson['length']*60;
		}

		$lesson = $this->getLessonDao()->addLesson(
			LessonSerialize::serialize($lesson)
		);

		// Increase the linked file usage count, if there's a linked file used by this lesson.
		if(!empty($lesson['mediaId'])){
			$this->getUploadFileService()->increaseFileUsedCount(array($lesson['mediaId']));
		}

		if(empty($course['parentId'])){
            $this->addChildLessons($lesson);
        }else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($course['id'], array('structureChanged'=>1));
        }

		$this->updateCourseCounter($course['id'], array(
			'lessonNum' => $this->getLessonDao()->getLessonCountByCourseId($course['id']),
			'giveCredit' => $this->getLessonDao()->sumLessonGiveCreditByCourseId($course['id']),
		));

		$this->getLogService()->info('course', 'add_lesson', "添加课时《{$lesson['title']}》({$lesson['id']})", $lesson);

		return $lesson;
	}

	public function analysisLessonDataByTime($startTime,$endTime)
	{
	    	return $this->getLessonDao()->analysisLessonDataByTime($startTime,$endTime);
	}

	private function fillLessonMediaFields(&$lesson)
	{
		if (in_array($lesson['type'], array('video', 'audio', 'ppt','document'))) {
			$media = empty($lesson['media']) ? null : $lesson['media'];
			if (empty($media) or empty($media['source']) or empty($media['name'])) {
				throw $this->createServiceException("media参数不正确，添加课时失败！");
			}

			if ($media['source'] == 'self') {
				$media['id'] = intval($media['id']);
				if (empty($media['id'])) {
					throw $this->createServiceException("media id参数不正确，添加/编辑课时失败！");
				}
				$file = $this->getUploadFileService()->getFile($media['id']);
				if (empty($file)) {
					throw $this->createServiceException('文件不存在，添加/编辑课时失败！');
				}

				$lesson['mediaId'] = $file['id'];
				$lesson['mediaName'] = $file['filename'];
				$lesson['mediaSource'] = 'self';
				$lesson['mediaUri'] = '';
			} else {
				if (empty($media['uri'])) {
					throw $this->createServiceException("media uri参数不正确，添加/编辑课时失败！");
				}
				$lesson['mediaId'] = 0;
				$lesson['mediaName'] = $media['name'];
				$lesson['mediaSource'] = $media['source'];
				$lesson['mediaUri'] = $media['uri'];
			}
		} elseif ($lesson['type'] == 'testpaper') {
			$lesson['mediaId'] = $lesson['mediaId'];
		} elseif ($lesson['type'] == 'live') {
		} else {
			$lesson['mediaId'] = 0;
			$lesson['mediaName'] = '';
			$lesson['mediaSource'] = '';
			$lesson['mediaUri'] = '';
		}

		unset($lesson['media']);

		return $lesson;
	}

	public function updateCourseDraft($courseId,$lessonId, $userId,$fields)
	{
		$draft = $this->findCourseDraft($courseId,$lessonId, $userId);

		if (empty($draft)) {
			throw $this->createServiceException('草稿不存在，更新失败！');
		}
		

		$fields = $this->_filterDraftFields($fields);
		
		$this->getLogService()->info('draft', 'update', "更新草稿《{$draft['title']}》(#{$draft['id']})的信息", $fields);

		$fields = LessonSerialize::serialize($fields);
		
		return LessonSerialize::unserialize(
			$this->getCourseDraftDao()->updateCourseDraft($courseId,$lessonId, $userId,$fields)
		);

	}

	private function _filterDraftFields($fields)
	{
		$fields = ArrayToolkit::filter($fields, array(
			'title' => '',
			'summary' => '',
			'content' => '',
			'createdTime' => 0
		));
		return $fields;
	}

	public function updateLesson($courseId, $lessonId, $fields)
	{
		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createServiceException("课程(#{$courseId})不存在！");
		}

		$lesson = $this->getCourseLesson($courseId, $lessonId);
		if (empty($lesson)) {
			throw $this->createServiceException("课时(#{$lessonId})不存在！");
		}

		$fields = ArrayToolkit::filter($fields, array(
			'title' => '',
			'summary' => '',
			'content' => '',
			'media' => array(),
			'mediaId' => 0,
			'free' => 0,
			'length' => 0,
			'startTime' => 0,
			'giveCredit' => 0,
			'requireCredit' => 0,
		));

		if (isset($fields['title'])) {
			$fields['title'] = $this->purifyHtml($fields['title']);
		}

		$fields['type'] = $lesson['type'];
		if ($fields['type'] == 'live') {
			$fields['endTime'] = $fields['startTime'] + $fields['length']*60;
		}
		
		$this->fillLessonMediaFields($fields);
		
		if(empty($course['parentId'])){
			$this->updateChildLessons($lessonId, LessonSerialize::serialize($fields));
		}else if(empty($lesson['contentChanged'])){
			$this->getLessonDao()->updateLesson($lessonId,array('contentChanged'=>1));
		}

		$updatedLesson = LessonSerialize::unserialize(
			$this->getLessonDao()->updateLesson($lessonId, LessonSerialize::serialize($fields))
		);

		$this->updateCourseCounter($course['id'], array(
			'giveCredit' => $this->getLessonDao()->sumLessonGiveCreditByCourseId($course['id']),
		));

		// Update link count of the course lesson file, if the lesson file is changed
		if($fields['mediaId'] != $lesson['mediaId']){
			// Incease the link count of the new selected lesson file
			if(!empty($fields['mediaId'])){
				$this->getUploadFileService()->increaseFileUsedCount(array($fields['mediaId']));
			}

			// Decrease the link count of the original lesson file
			if(!empty($lesson['mediaId'])){
				$this->getUploadFileService()->decreaseFileUsedCount(array($lesson['mediaId']));
			}
		}

		$this->getLogService()->info('course', 'update_lesson', "更新课时《{$updatedLesson['title']}》({$updatedLesson['id']})", $updatedLesson);

		return $updatedLesson;
	}

	public function deleteLesson($courseId, $lessonId)
	{
		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createServiceException("课程(#{$courseId})不存在！");
		}

		$lesson = $this->getCourseLesson($courseId, $lessonId, true);
		if (empty($lesson)) {
			throw $this->createServiceException("课时(#{$lessonId})不存在！");
		}

		// 更新已学该课时学生的计数器
		$learnCount = $this->getLessonLearnDao()->findLearnsCountByLessonId($lessonId);
		if ($learnCount > 0) {
			$learns = $this->getLessonLearnDao()->findLearnsByLessonId($lessonId, 0, $learnCount);
			foreach ($learns as $learn) {
				if ($learn['status'] == 'finished') {
					$member = $this->getCourseMember($learn['courseId'], $learn['userId']);
					if ($member) {
						$memberFields = array();
						$memberFields['learnedNum'] = $this->getLessonLearnDao()->getLearnCountByUserIdAndCourseIdAndStatus($learn['userId'], $learn['courseId'], 'finished') - 1;
						$memberFields['isLearned'] = $memberFields['learnedNum'] >= $course['lessonNum'] ? 1 : 0;
						$this->getMemberDao()->updateMember($member['id'], $memberFields);
					}
				}
			}
		}

		if(empty($course['parentId'])){
			$this->deleteChildLessons($lessonId);
		}else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($course['id'], array('structureChanged'=>1));
        }
		$this->getLessonLearnDao()->deleteLearnsByLessonId($lessonId);

		$this->getLessonDao()->deleteLesson($lessonId);

		// 更新课时序号
		$this->updateCourseCounter($course['id'], array(
			'lessonNum' => $this->getLessonDao()->getLessonCountByCourseId($course['id'])
		));
		// [END] 更新课时序号

		// Decrease the course lesson file usage count, if there's a linked file used by this lesson.
		if(!empty($lesson['mediaId'])){
			$this->getUploadFileService()->decreaseFileUsedCount(array($lesson['mediaId']));
		}

		// Delete all linked course materials (the UsedCount of each material file will also be decreaased.)
		$this->getCourseMaterialService()->deleteMaterialsByLessonId($lessonId);

		$this->getLogService()->info('lesson', 'delete', "删除课程《{$course['title']}》(#{$course['id']})的课时 {$lesson['title']}");

		// $this->autosetCourseFields($courseId);
	}

	public function findLearnsCountByLessonId($lessonId)
	{
		return  $this->getLessonLearnDao()->findLearnsCountByLessonId($lessonId);
	}

	public function analysisLessonFinishedDataByTime($startTime,$endTime)
	{
		return $this->getLessonLearnDao()->analysisLessonFinishedDataByTime($startTime,$endTime);
	}

	public function searchAnalysisLessonViewCount($conditions)
	{
		return $this->getLessonViewDao()->searchLessonViewCount($conditions);
	}

	public function getAnalysisLessonMinTime($type)
	{
		if (!in_array($type, array('all','cloud','net','local'))) {
			throw $this->createServiceException("error");
		}

		return $this->getLessonViewDao()->getAnalysisLessonMinTime($type);
	}

	public function searchAnalysisLessonView($conditions, $orderBy, $start, $limit)
	{
		return $this->getLessonViewDao()->searchLessonView($conditions, $orderBy, $start, $limit);
	}

	public function analysisLessonViewDataByTime($startTime,$endTime,$conditions)
	{
		return $this->getLessonViewDao()->searchLessonViewGroupByTime($startTime,$endTime,$conditions);
	}

	public function publishLesson($courseId, $lessonId)
	{
		$course = $this->tryManageCourse($courseId);

		$lesson = $this->getCourseLesson($courseId, $lessonId);
		if (empty($lesson)) {
			throw $this->createServiceException("课时#{$lessonId}不存在");
		}
        if(empty($course['parentId'])){
            $this->updateChildLessons($lessonId,array('status' => 'published'));
        }else if(empty($lesson['contentChanged'])){
			$this->getLessonDao()->updateLesson($lessonId,array('contentChanged'=>1));
		}
		$this->getLessonDao()->updateLesson($lesson['id'], array('status' => 'published'));
	}

	public function unpublishLesson($courseId, $lessonId)
	{
		$course = $this->tryManageCourse($courseId);

		$lesson = $this->getCourseLesson($courseId, $lessonId);
		if (empty($lesson)) {
			throw $this->createServiceException("课时#{$lessonId}不存在");
		}
		if(empty($course['parentId'])){
            $this->updateChildLessons($lessonId,array('status' => 'unpublished'));
        }else if(empty($lesson['contentChanged'])){
			$this->getLessonDao()->updateLesson($lessonId,array('contentChanged'=>1));
		}
		$this->getLessonDao()->updateLesson($lesson['id'], array('status' => 'unpublished'));
	}

	public function getNextLessonNumber($courseId)
	{
		return $this->getLessonDao()->getLessonCountByCourseId($courseId) + 1;
	}

	public function liveLessonTimeCheck($courseId,$lessonId,$startTime,$length)
	{	
		$course = $this->getCourseDao()->getCourse($courseId);

		if (empty($course)) {
			throw $this->createServiceException('此课程不存在！');
		}

		$thisStartTime = $thisEndTime = 0;


		if ($lessonId) {
			$liveLesson = $this->getCourseLesson($course['id'], $lessonId);
			$thisStartTime = empty($liveLesson['startTime']) ? 0 : $liveLesson['startTime'];
			$thisEndTime = empty($liveLesson['endTime']) ? 0 : $liveLesson['endTime'];
		} else {
			$lessonId = "";
		}

		$startTime = is_numeric($startTime) ? $startTime : strtotime($startTime);
		$endTime = $startTime + $length*60;

		$thisLessons = $this->getLessonDao()->findTimeSlotOccupiedLessonsByCourseId($courseId,$startTime,$endTime,$lessonId);

		if (($length/60) > 8) {
			 return array('error_timeout','时长不能超过8小时！');
		}

		if ($thisLessons) {
			return array('error_occupied','该时段内已有直播课时存在，请调整直播开始时间');
		}

		return array('success','');
	}

	public function calculateLiveCourseLeftCapacityInTimeRange($startTime, $endTime, $excludeLessonId)
	{
        $client = LiveClientFactory::createClient();
        $liveStudentCapacity = $client->getCapacity();
        $liveStudentCapacity = empty($liveStudentCapacity['capacity']) ? 0 : $liveStudentCapacity['capacity'];

		$lessons = $this->getLessonDao()->findTimeSlotOccupiedLessons($startTime, $endTime, $excludeLessonId);

		$courseIds = ArrayToolkit::column($lessons,'courseId');
		$courseIds = array_unique($courseIds);
		$courseIds = array_values($courseIds);
		$courses = $this->getCourseDao()->findCoursesByIds($courseIds);
		$maxStudentNum = ArrayToolkit::column($courses,'maxStudentNum');
		$timeSlotOccupiedStuNums = array_sum($maxStudentNum);

		return $liveStudentCapacity - $timeSlotOccupiedStuNums;
	}

	public function canLearnLesson($courseId, $lessonId)
	{
		list($course, $member) = $this->tryTakeCourse($courseId);
		$lesson = $this->getCourseLesson($courseId, $lessonId);
		if (empty($lesson) or $lesson['courseId']!=$courseId) {
			throw $this->createNotFoundException();
		}
		$user = $this->getCurrentUser();


		if (empty($lesson['requireCredit'])) {
			return array('status' => 'yes');
		}

		if ($member['credit'] >= $lesson['requireCredit']) {
			return array('status' => 'yes');
		}

		return array('status' => 'no', 'message' => sprintf('本课时需要%s学分才能学习，您当前学分为%s分。', $lesson['requireCredit'], $member['credit']));
	}

	public function startLearnLesson($courseId, $lessonId)
	{
		list($course, $member) = $this->tryTakeCourse($courseId);
		$user = $this->getCurrentUser();

		$lesson = $this->getCourseLesson($courseId, $lessonId);

	$this->getStatusService()->publishStatus(array(
		'type' => 'start_learn_lesson',
		'objectType' => 'lesson',
		'objectId' => $lessonId,
		'properties' => array(
			'course' => $this->simplifyCousrse($course),
			'lesson' => $this->simplifyLesson($lesson),
		)
	));

		if (!empty($lesson) && $lesson['type'] != 'video') {

			$learn = $this->getLessonLearnDao()->getLearnByUserIdAndLessonId($user['id'], $lessonId);
			if ($learn) {
				return false;
			}

			$this->getLessonLearnDao()->addLearn(array(
				'userId' => $user['id'],
				'courseId' => $courseId,
				'lessonId' => $lessonId,
				'status' => 'learning',
				'startTime' => time(),
				'finishedTime' => 0,
			));

			return true;
		}

		$createLessonView['courseId'] = $courseId;
		$createLessonView['lessonId'] = $lessonId;
		$createLessonView['fileId'] = $lesson['mediaId'];

		$file = array();
		if (!empty($createLessonView['fileId'])) {
			$file = $this->getUploadFileService()->getFile($createLessonView['fileId']);
		}

		$createLessonView['fileStorage'] = empty($file) ? "net" : $file['storage'];
		$createLessonView['fileType'] = $lesson['type'];
		$createLessonView['fileSource'] = $lesson['mediaSource'];

		$this->createLessonView($createLessonView);

		$learn = $this->getLessonLearnDao()->getLearnByUserIdAndLessonId($user['id'], $lessonId);
		if ($learn) {
			return false;
		}

		$this->getLessonLearnDao()->addLearn(array(
			'userId' => $user['id'],
			'courseId' => $courseId,
			'lessonId' => $lessonId,
			'status' => 'learning',
			'startTime' => time(),
			'finishedTime' => 0,
		));



		return true;
	}

	public function createLessonView($createLessonView)
	{
		$createLessonView = ArrayToolkit::parts($createLessonView, array('courseId', 'lessonId','fileId', 'fileType', 'fileStorage', 'fileSource'));
		$createLessonView['userId'] = $this->getCurrentUser()->id;
		$createLessonView['createdTime'] = time();

		$lessonView = $this->getLessonViewDao()->addLessonView	($createLessonView);

		$lesson = $this->getCourseLesson($createLessonView['courseId'], $createLessonView['lessonId']);

		$this->getLogService()->info('course', 'create', "{$this->getCurrentUser()->nickname}观看课时《{$lesson['title']}》");

		return $lessonView;
	}

	public function finishLearnLesson($courseId, $lessonId)
	{
		list($course, $member) = $this->tryLearnCourse($courseId);

		$lesson = $this->getCourseLesson($courseId, $lessonId);
		if (empty($lesson)) {
			throw $this->createServiceException("课时#{$lessonId}不存在！");
		}

		$learn = $this->getLessonLearnDao()->getLearnByUserIdAndLessonId($member['userId'], $lessonId);
		
		if ($learn){
			if($learn['status'] != 'finished') {
				$this->getLessonLearnDao()->updateLearn($learn['id'], array(
					'status' => 'finished',
					'finishedTime' => time(),
				));
			} else {
				// Lesson already learned, skip all further updates
				return;
			}
		} else {
			$this->getLessonLearnDao()->addLearn(array(
				'userId' => $member['userId'],
				'courseId' => $courseId,
				'lessonId' => $lessonId,
				'status' => 'finished',
				'startTime' => time(),
				'finishedTime' => time(),
			));
		}

		$learns = $this->getLessonLearnDao()->findLearnsByUserIdAndCourseIdAndStatus($member['userId'], $course['id'], 'finished');
		$totalCredits = $this->getLessonDao()->sumLessonGiveCreditByLessonIds(ArrayToolkit::column($learns, 'lessonId'));

		$memberFields = array();
		$memberFields['learnedNum'] = count($learns);
		$memberFields['isLearned'] = $memberFields['learnedNum'] >= $course['lessonNum'] ? 1 : 0;
		$memberFields['credit'] = $totalCredits;

		$this->getStatusService()->publishStatus(array(
			'type' => 'learned_lesson',
			'objectType' => 'lesson',
			'objectId' => $lessonId,
			'properties' => array(
				'course' => $this->simplifyCousrse($course),
				'lesson' => $this->simplifyLesson($lesson),
			)
		));

		$param['userId'] = $member['userId'];
		$param['type'] = 'add';
		$param['lessonType'] = $lesson['type'];
		$this->getDispatcher()->dispatch('lesson.learned', new ServiceEvent($param));
		
		$this->getMemberDao()->updateMember($member['id'], $memberFields);
	}

	public function searchLearnCount($conditions)
	{
		return $this->getLessonLearnDao()->searchLearnCount($conditions);
	}

	public function searchLearns($conditions,$orderBy,$start,$limit)
	{
		return $this->getLessonLearnDao()->searchLearns($conditions,$orderBy,$start,$limit);
	}

	public function searchLearnTime($conditions)
	{	
		return $this->getLessonLearnDao()->searchLearnTime($conditions);
	}

	public function searchWatchTime($conditions)
	{
		return $this->getLessonLearnDao()->searchWatchTime($conditions);
	}
	
	public function findLatestFinishedLearns($start, $limit)
	{
		return $this->getLessonLearnDao()->findLatestFinishedLearns($start, $limit);
	}

	public function cancelLearnLesson($courseId, $lessonId)
	{
		list($course, $member) = $this->tryLearnCourse($courseId);

		$lesson = $this->getCourseLesson($courseId, $lessonId);
		$learn = $this->getLessonLearnDao()->getLearnByUserIdAndLessonId($member['userId'], $lessonId);
		if (empty($learn)) {
			throw $this->createServiceException("课时#{$lessonId}尚未学习，取消学习失败。");
		}

		if ($learn['status'] != 'finished') {
			throw $this->createServiceException("课时#{$lessonId}尚未学完，取消学习失败。");
		}

		$this->getLessonLearnDao()->updateLearn($learn['id'], array(
			'status' => 'learning',
			'finishedTime' => 0,
		));

		$learns = $this->getLessonLearnDao()->findLearnsByUserIdAndCourseIdAndStatus($member['userId'], $course['id'], 'finished');
		$totalCredits = $this->getLessonDao()->sumLessonGiveCreditByLessonIds(ArrayToolkit::column($learns, 'lessonId'));

		$memberFields = array();
		$memberFields['learnedNum'] = count($learns);
		$memberFields['isLearned'] = $memberFields['learnedNum'] >= $course['lessonNum'] ? 1 : 0;
		$memberFields['credit'] = $totalCredits;

		$param['userId'] = $member['userId'];
		$param['type'] = 'decrease';
		$param['lessonType'] = $lesson['type'];
		$this->getDispatcher()->dispatch('lesson.learned', new ServiceEvent($param));

		$this->getMemberDao()->updateMember($member['id'], $memberFields);
	}

	public function getUserLearnLessonStatus($userId, $courseId, $lessonId)
	{
		$learn = $this->getLessonLearnDao()->getLearnByUserIdAndLessonId($userId, $lessonId);
		if (empty($learn)) {
			return null;
		}

		return $learn['status'];
	}

	public function getUserLearnLessonStatuses($userId, $courseId)
	{
		$learns = $this->getLessonLearnDao()->findLearnsByUserIdAndCourseId($userId, $courseId) ? : array();

		$statuses = array();
		foreach ($learns as $learn) {
			$statuses[$learn['lessonId']] = $learn['status'];
		}

		return $statuses;
	}

	public function getUserNextLearnLesson($userId, $courseId)
	{
		$lessonIds = $this->getLessonDao()->findLessonIdsByCourseId($courseId);

		$learns = $this->getLessonLearnDao()->findLearnsByUserIdAndCourseIdAndStatus($userId, $courseId, 'finished');

		$learnedLessonIds = ArrayToolkit::column($learns, 'lessonId');

		$unlearnedLessonIds = array_diff($lessonIds, $learnedLessonIds);
		$nextLearnLessonId = array_shift($unlearnedLessonIds);
		if (empty($nextLearnLessonId)) {
			return null;
		}
		return $this->getLessonDao()->getLesson($nextLearnLessonId);
	}

	public function getNextLearnLessonByUserId($userId)
	{
		return $this->getLessonLearnDao()->getNextLearnLessonByUserId($userId);
	}

	public function findUserLessonLearns($userId)
	{
		return $this->getLessonLearnDao()->findLearnByUserId($userId);
	}

	public function getChapter($courseId, $chapterId)
	{
		$chapter = $this->getChapterDao()->getChapter($chapterId);
		if (empty($chapter) or $chapter['courseId'] != $courseId) {
			return null;
		}
		return $chapter;
	}

	public function getCourseChapters($courseId)
	{
		return $this->getChapterDao()->findChaptersByCourseId($courseId);
	}

	public function createChapter($chapter)
	{
        $course = $this->tryManageCourse($chapter['courseId']);
        if(empty($course)){
            throw $this->createServiceException("课程不存在，添加失败！");
        }
        if (!in_array($chapter['type'], array('chapter', 'unit'))) {
            throw $this->createServiceException("章节类型不正确，添加失败！");
        }

        if ($chapter['type'] == 'unit') {
            list($chapter['number'], $chapter['parentId']) = $this->getNextUnitNumberAndParentId($chapter['courseId']);
        } else {
            $chapter['number'] = $this->getNextChapterNumber($chapter['courseId']);
            $chapter['parentId'] = 0;
        }
        $chapter['seq'] = $this->getNextCourseItemSeq($chapter['courseId']);
        $chapter['createdTime'] = time();
        $newChapter=$this->getChapterDao()->addChapter($chapter);
        if(empty($course['parentId'])){
        	$this->addChildChapters($newChapter['id'],$chapter);
        }else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($course['id'], array('structureChanged'=>1));
        }
		return $newChapter;
	}

	public function updateChapter($courseId, $chapterId, $fields)
	{
		$chapter = $this->getChapter($courseId, $chapterId);
        $course = $this->getCourse($courseId);
        if(empty($course)){
            throw $this->createServiceException("课程不存在不存在！");
        }
		if (empty($chapter)) {
			throw $this->createServiceException("章节#{$chapterId}不存在！");
		}
		$fields = ArrayToolkit::parts($fields, array('title'));
        if(empty($course['parentId'])){
            $this->updateChildChapters($courseId, $chapterId, $fields);
        }else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($courseId, array('structureChanged'=>1));
        }
		return $this->getChapterDao()->updateChapter($chapterId, $fields);
	}

	public function deleteChapter($courseId, $chapterId)
	{
		$course = $this->tryManageCourse($courseId);

		$deletedChapter = $this->getChapter($course['id'], $chapterId);
		if (empty($deletedChapter)) {
			throw $this->createServiceException(sprintf('章节(ID:%s)不存在，删除失败！', $chapterId));
		}
		if(empty($course['parentId'])){
			$this->deleteChildChapters($deletedChapter);
		}else if(empty($course['structureChanged'])){
        	$this->getCourseDao()->updateCourse($courseId, array('structureChanged'=>1));
        }
		$this->getChapterDao()->deleteChapter($deletedChapter['id']);

		$prevChapter = array('id' => 0);
		foreach ($this->getCourseChapters($course['id']) as $chapter) {
			if ($chapter['number'] < $deletedChapter['number']) {
				$prevChapter = $chapter;
			}
		}

		$lessons = $this->getLessonDao()->findLessonsByChapterId($deletedChapter['id']);
		foreach ($lessons as $lesson) {
			$this->getLessonDao()->updateLesson($lesson['id'], array('chapterId' => $prevChapter['id']));
		}
	}
	

	public function getNextChapterNumber($courseId)
	{
		$counter = $this->getChapterDao()->getChapterCountByCourseIdAndType($courseId, 'chapter');
		return $counter + 1;
	}

	public function getNextUnitNumberAndParentId($courseId)
	{
		$lastChapter = $this->getChapterDao()->getLastChapterByCourseIdAndType($courseId, 'chapter');

		$parentId = empty($lastChapter) ? 0 : $lastChapter['id'];

		$unitNum = 1 + $this->getChapterDao()->getChapterCountByCourseIdAndTypeAndParentId($courseId, 'unit', $parentId);

		return array($unitNum, $parentId);
	}













	public function getCourseItems($courseId)
	{
		$lessons = LessonSerialize::unserializes(
			$this->getLessonDao()->findLessonsByCourseId($courseId)
		);

		$chapters = $this->getChapterDao()->findChaptersByCourseId($courseId);

		$items = array();
		foreach ($lessons as $lesson) {
			$lesson['itemType'] = 'lesson';
			$items["lesson-{$lesson['id']}"] = $lesson;
		}

		foreach ($chapters as $chapter) {
			$chapter['itemType'] = 'chapter';
			$items["chapter-{$chapter['id']}"] = $chapter;
		}

		uasort($items, function($item1, $item2){
			return $item1['seq'] > $item2['seq'];
		});

		return $this->sortItems($items,array_keys($items));
	}


	public function sortCourseItems($courseId, array $itemIds)
	{
		$items = $this->getCourseItems($courseId);
        $course = $this->getCourse($courseId);
		$existedItemIds = array_keys($items);

        if(empty($course)){
            throw $this->createServiceException('课程不存在');
        }
		if (count($itemIds) != count($existedItemIds)) {
			throw $this->createServiceException('itemdIds参数不正确');
		}
        $diffItemIds = array_diff($itemIds, array_keys($items));
		if (!empty($diffItemIds)) {
			throw $this->createServiceException('itemdIds参数不正确');
		}
		$this->sortItems($items,$itemIds,$course);
	}





















	private function getNextCourseItemSeq($courseId)
	{
		$chapterMaxSeq = $this->getChapterDao()->getChapterMaxSeqByCourseId($courseId);
		$lessonMaxSeq = $this->getLessonDao()->getLessonMaxSeqByCourseId($courseId);
		return ($chapterMaxSeq > $lessonMaxSeq ? $chapterMaxSeq : $lessonMaxSeq) + 1;
	}

	public function addMemberExpiryDays($courseId, $userId, $day)
	{
		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);

		if ($member['deadline'] > 0){
			$deadline = $day*24*60*60+$member['deadline'];
		} else {
			$deadline = $day*24*60*60+time();
		}

		return $this->getMemberDao()->updateMember($member['id'], array(
			'deadline' => $deadline
		));
	}

	/**
	 * Member API
	 */
	public function searchMemberCount($conditions)
	{	
		$conditions = $this->_prepareCourseConditions($conditions);
		return $this->getMemberDao()->searchMemberCount($conditions);
	}

	public function countMembersByStartTimeAndEndTime($startTime,$endTime)
	{	
		return $this->getMemberDao()->countMembersByStartTimeAndEndTime($startTime,$endTime);
	}

	public function findWillOverdueCourses()
	{
		$currentUser = $this->getCurrentUser();
		if (!$currentUser->isLogin()) {
			throw $this->createServiceException('用户未登录');
		}
		$courseMembers = $this->getMemberDao()->findCourseMembersByUserId($currentUser["id"]);

		$courseIds = ArrayToolkit::column($courseMembers, "courseId");
		$courses = $this->findCoursesByIds($courseIds);

		$courseMembers = ArrayToolkit::index($courseMembers, "courseId");

		$shouldNotifyCourses = array();
		$shouldNotifyCourseMembers = array();
		
		$currentTime = time();
		foreach ($courses as $key => $course) {
			if($course['deadlineNotify'] == "active") {
				$courseMember = $courseMembers[$course["id"]];
				if($currentTime < $courseMember["deadline"]  && ($course["daysOfNotifyBeforeDeadline"]*24*60*60+$currentTime) > $courseMember["deadline"]){
					$shouldNotifyCourses[] = $course;
					$shouldNotifyCourseMembers[] = $courseMember;
				}
			}
		}
		return array($shouldNotifyCourses, $shouldNotifyCourseMembers);
	}

	public function searchMembers($conditions, $orderBy, $start, $limit)
	{
		$conditions = $this->_prepareCourseConditions($conditions);
		return $this->getMemberDao()->searchMembers($conditions, $orderBy, $start, $limit);
	}
	
	public function searchMember($conditions, $start, $limit)
	{
		$conditions = $this->_prepareCourseConditions($conditions);
		return $this->getMemberDao()->searchMember($conditions, $start, $limit);
	}

	public function searchMemberIds($conditions, $sort = 'latest', $start, $limit)
	{	
		$conditions = $this->_prepareCourseConditions($conditions);
		if ($sort = 'latest') {
			$orderBy = array('createdTime', 'DESC');
		} 
		return $this->getMemberDao()->searchMemberIds($conditions, $orderBy, $start, $limit);
	}

	public function updateCourseMember($id, $fields)
	{
		return $this->getMemberDao()->updateMember($id, $fields);
	}

	public function getCourseMember($courseId, $userId)
	{
		return $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
	}

	public function findCourseStudents($courseId, $start, $limit)
	{
		return $this->getMemberDao()->findMembersByCourseIdAndRole($courseId, 'student', $start, $limit);
	}

	public function findCourseStudentsByCourseIds($courseIds)
	{
		return $this->getMemberDao()->getMembersByCourseIds($courseIds);
	}

	public function getCourseStudentCount($courseId)
	{
		return $this->getMemberDao()->findMemberCountByCourseIdAndRole($courseId, 'student');
	}

	public function findCourseTeachers($courseId)
	{
		return $this->getMemberDao()->findMembersByCourseIdAndRole($courseId, 'teacher', 0, self::MAX_TEACHER);
	}

	public function isCourseTeacher($courseId, $userId)
	{
		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if(!$member){
			return false;
		} else {
			return empty($member) or $member['role'] != 'teacher' ? false : true;
		}
	}

	public function isCourseStudent($courseId, $userId)
	{
		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if(!$member){
			return false;
		} else {
			return empty($member) or $member['role'] != 'student' ? false : true;
		}
	}

	public function setCourseTeachers($courseId, $teachers)
	{
		// 过滤数据
		$teacherMembers = array();
		foreach (array_values($teachers) as $index => $teacher) {
			if (empty($teacher['id'])) {
				throw $this->createServiceException("教师ID不能为空，设置课程(#{$courseId})教师失败");
			}
			$user = $this->getUserService()->getUser($teacher['id']);

			if (empty($user)) {
				throw $this->createServiceException("用户不存在或没有教师角色，设置课程(#{$courseId})教师失败");
			}

			$teacherMembers[] = array(
				'courseId' => $courseId,
				'userId' => $user['id'],
				'role' => 'teacher',
				'seq' => $index,
				'isVisible' => empty($teacher['isVisible']) ? 0 : 1,
				'createdTime' => time(),
			);
		}
		// 先清除所有的已存在的教师学生
		$existTeacherMembers = $this->findCourseTeachers($courseId);
		foreach ($existTeacherMembers as $member) {
			$this->getMemberDao()->deleteMember($member['id']);
		}
		$course = $this->getCourse($courseId);

		// 逐个插入新的教师的学生数据
		$visibleTeacherIds = array();
		foreach ($teacherMembers as $member) {
			// 存在学生信息，说明该用户先前是学生学生，则删除该学生信息。
			$existMember = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $member['userId']);
			if ($existMember) {
				$this->getMemberDao()->deleteMember($existMember['id']);
			}
			$this->getMemberDao()->addMember($member);
			if ($member['isVisible']) {
				$visibleTeacherIds[] = $member['userId'];
			}
		}

		$this->getLogService()->info('course', 'update_teacher', "更新课程#{$courseId}的教师", $teacherMembers);

		// 更新课程的teacherIds，该字段为课程可见教师的ID列表
		$fields = array('teacherIds' => $visibleTeacherIds);
		$this->getCourseDao()->updateCourse($courseId, CourseSerialize::serialize($fields));
	}

	/**
	 * @todo 当用户拥有大量的课程老师角色时，这个方法效率是有就有问题咯！鉴于短期内用户不会拥有大量的课程老师角色，先这么做着。
	 */
	public function cancelTeacherInAllCourses($userId)
	{
		$count = $this->getMemberDao()->findMemberCountByUserIdAndRole($userId, 'teacher', false);
		$members = $this->getMemberDao()->findMembersByUserIdAndRole($userId, 'teacher', 0, $count, false);
		foreach ($members as $member) {
			$course = $this->getCourse($member['courseId']);

			$this->getMemberDao()->deleteMember($member['id']);

			$fields = array(
				'teacherIds' => array_diff($course['teacherIds'], array($member['userId']))
			);
			$this->getCourseDao()->updateCourse($member['courseId'], CourseSerialize::serialize($fields));
		}

		$this->getLogService()->info('course', 'cancel_teachers_all', "取消用户#{$userId}所有的课程老师角色");
	}

	public function remarkStudent($courseId, $userId, $remark)
	{
		$member = $this->getCourseMember($courseId, $userId);
		if (empty($member)) {
			throw $this->createServiceException('课程学生不存在，备注失败!');
		}
		$fields = array('remark' => empty($remark) ? '' : (string) $remark);
		return $this->getMemberDao()->updateMember($member['id'], $fields);
	}

	public function becomeStudent($courseId, $userId, $info = array())
	{
		$course = $this->getCourse($courseId);

		if (empty($course)) {
			throw $this->createNotFoundException();
		}

		if($course['status'] != 'published') {
			throw $this->createServiceException('不能加入未发布课程');
		}

		$user = $this->getUserService()->getUser($userId);
		if (empty($user)) {
			throw $this->createServiceException("用户(#{$userId})不存在，加入课程失败！");
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if ($member) {
			throw $this->createServiceException("用户(#{$userId})已加入该课程！");
		}

		$levelChecked = '';
		if (!empty($info['becomeUseMember'])) {
			$levelChecked = $this->getVipService()->checkUserInMemberLevel($user['id'], $course['vipLevelId']);
			if ($levelChecked != 'ok') {
				throw $this->createServiceException("用户(#{$userId})不能以会员身份加入课程！");
			}
			$userMember = $this->getVipService()->getMemberByUserId($user['id']);
		}

		if ($course['expiryDay'] > 0) {
			//如果处在限免期，则deadline为限免结束时间 减 当前时间
			$deadline = $course['expiryDay']*24*60*60 + time();

			if($course['freeStartTime'] <= time() && $course['freeEndTime'] > time()){
				if($course['freeEndTime'] < $deadline){
					$deadline = $course['freeEndTime'];
				}	
			}
		} else {
			$deadline = 0;
			//如果处在限免期，则deadline为限免结束时间 减 当前时间
			if($course['freeStartTime'] <= time() && $course['freeEndTime'] > time() && $levelChecked != 'ok'){
				$deadline = $course['freeEndTime'];
			}
		}

		if (!empty($info['orderId'])) {
			$order = $this->getOrderService()->getOrder($info['orderId']);
			if (empty($order)) {
				throw $this->createServiceException("订单(#{$info['orderId']})不存在，加入课程失败！");
			}
		} else {
			$order = null;
		}

		$fields = array(
			'courseId' => $courseId,
			'userId' => $userId,
			'orderId' => empty($order) ? 0 : $order['id'],
			'deadline' => $deadline,
			'levelId' => empty($info['becomeUseMember']) ? 0 : $userMember['levelId'],
			'role' => 'student',
			'remark' => empty($order['note']) ? '' : $order['note'],
			'createdTime' => time()
		);

		if (empty($fields['remark'])) {
			$fields['remark'] = empty($info['note']) ? '' : $info['note'];
		}

		$member = $this->getMemberDao()->addMember($fields);

		$setting = $this->getSettingService()->get('course', array());
		if (!empty($setting['welcome_message_enabled']) && !empty($course['teacherIds'])) {
			$message = $this->getWelcomeMessageBody($user, $course);
	        $this->getMessageService()->sendMessage($course['teacherIds'][0], $user['id'], $message);
	    }

		$fields = array(
			'studentNum'=> $this->getCourseStudentCount($courseId),
		);
	    if ($order) {
	    	$fields['income'] = $this->getOrderService()->sumOrderPriceByTarget('course', $courseId);
	    }
		$this->getCourseDao()->updateCourse($courseId, $fields);

		$this->getStatusService()->publishStatus(array(
			'type' => 'become_student',
			'objectType' => 'course',
			'objectId' => $courseId,
			'properties' => array(
				'course' => $this->simplifyCousrse($course),
			)
		));

		return $member;
	}

	public function becomeGuest($courseId, $userId)
	{
		$course = $this->getCourse($courseId);

		if (empty($course)) {
			throw $this->createNotFoundException();
		}

		if($course['status'] != 'published') {
			throw $this->createServiceException('不能加入未发布课程');
		}

		$user = $this->getUserService()->getUser($userId);
		if (empty($user)) {
			throw $this->createServiceException("用户(#{$userId})不存在，加入课程失败！");
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if ($member) {
			throw $this->createServiceException("用户(#{$userId})已加入该课程！");
		}

		if ($course['expiryDay'] > 0) {
			$deadline = $course['expiryDay']*24*60*60 + time();
		} else {
			$deadline = 0;
		}

		$fields = array(
			'courseId' => $courseId,
			'userId' => $userId,
			'orderId' => 0,
			'deadline' => $deadline,
			'levelId' => 0,
			'role' => 'guest',
			'remark' => '',
			'createdTime' => time()
		);

		$member = $this->getMemberDao()->addMember($fields);

		return $member;
	}

	private function getWelcomeMessageBody($user, $course)
    {
        $setting = $this->getSettingService()->get('course', array());
        $valuesToBeReplace = array('{{nickname}}', '{{course}}');
        $valuesToReplace = array($user['nickname'], $course['title']);
        $welcomeMessageBody = str_replace($valuesToBeReplace, $valuesToReplace, $setting['welcome_message_body']);
        return $welcomeMessageBody;
    }

	public function removeStudent($courseId, $userId)
	{
		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createNotFoundException("课程(#${$courseId})不存在，退出课程失败。");
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if (empty($member) or ($member['role'] != 'student')) {
			throw $this->createServiceException("用户(#{$userId})不是课程(#{$courseId})的学生，退出课程失败。");
		}

		$this->getMemberDao()->deleteMember($member['id']);

		$this->getCourseDao()->updateCourse($courseId, array(
			'studentNum' => $this->getCourseStudentCount($courseId),
		));

		$this->getLogService()->info('course', 'remove_student', "课程《{$course['title']}》(#{$course['id']})，移除学生#{$member['id']}");
	}

	public function lockStudent($courseId, $userId)
	{
		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createNotFoundException("课程(#${$courseId})不存在，封锁学生失败。");
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if (empty($member) or ($member['role'] != 'student')) {
			throw $this->createServiceException("用户(#{$userId})不是课程(#{$courseId})的学生，封锁学生失败。");
		}

		if ($member['locked']) {
			return ;
		}

		$this->getMemberDao()->updateMember($member['id'], array('locked' => 1));
	}

	public function unlockStudent($courseId, $userId)
	{
		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createNotFoundException("课程(#${$courseId})不存在，封锁学生失败。");
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if (empty($member) or ($member['role'] != 'student')) {
			throw $this->createServiceException("用户(#{$userId})不是课程(#{$courseId})的学生，解封学生失败。");
		}

		if (empty($member['locked'])) {
			return ;
		}

		$this->getMemberDao()->updateMember($member['id'], array('locked' => 0));
	}

	public function increaseLessonQuizCount($lessonId){
	    $lesson = $this->getLessonDao()->getLesson($lessonId);
	    $lesson['quizNum'] += 1;
	    $this->getLessonDao()->updateLesson($lesson['id'],$lesson);

	}
	public function resetLessonQuizCount($lessonId,$count){
	    $lesson = $this->getLessonDao()->getLesson($lessonId);
	    $lesson['quizNum'] = $count;
	    $this->getLessonDao()->updateLesson($lesson['id'],$lesson);
	}
	
	public function increaseLessonMaterialCount($lessonId){
	    $lesson = $this->getLessonDao()->getLesson($lessonId);
        $course =$this->getCourse($lesson['courseId']);
	    $lesson['materialNum'] += 1;
        if (empty($course['parentId'])) {
            $fields['materialNum']=$lesson['materialNum'];
            $this->updateChildLessons($lesson['id'],$fields);
        }
	    $this->getLessonDao()->updateLesson($lesson['id'],$lesson);

	}
	public function resetLessonMaterialCount($lessonId,$count){
	    $lesson = $this->getLessonDao()->getLesson($lessonId);
        $course =$this->getCourse($lesson['courseId']);
	    $lesson['materialNum'] = $count;
        if (empty($course['parentId'])) {
            $fields['materialNum']=$count;
            $this->updateChildLessons($lesson['id'],$fields);
        }
	    $this->getLessonDao()->updateLesson($lesson['id'],$lesson);
	}

	public function setMemberNoteNumber($courseId, $userId, $number)
	{
		$member = $this->getCourseMember($courseId, $userId);
		if (empty($member)) {
			return false;
		}

		$this->getMemberDao()->updateMember($member['id'], array(
			'noteNum' => (int) $number,
			'noteLastUpdateTime' => time(),
		));

		return true;
	}


	/**
	 * @todo refactor it.
	 */
	public function tryManageCourse($courseId)
	{
		$user = $this->getCurrentUser();
		if (!$user->isLogin()) {
			throw $this->createAccessDeniedException('未登录用户，无权操作！');
		}

		$course = $this->getCourseDao()->getCourse($courseId);
		if (empty($course)) {
			throw $this->createNotFoundException();
		}
		if (!$this->hasCourseManagerRole($courseId, $user['id'])) {
			throw $this->createAccessDeniedException('您不是课程的教师或管理员，无权操作！');
		}

		return CourseSerialize::unserialize($course);
	}

	public function tryAdminCourse($courseId)
	{
		$course = $this->getCourseDao()->getCourse($courseId);
		if (empty($course)) {
			throw $this->createNotFoundException();
		}

		$user = $this->getCurrentUser();
		if (empty($user->id)) {
			throw $this->createAccessDeniedException('未登录用户，无权操作！');
		}

		if (count(array_intersect($user['roles'], array('ROLE_ADMIN', 'ROLE_SUPER_ADMIN'))) == 0) {
			throw $this->createAccessDeniedException('您不是管理员，无权操作！');
		}

		return CourseSerialize::unserialize($course);
	}

	public function canManageCourse($courseId)
	{
		$user = $this->getCurrentUser();
		if (!$user->isLogin()) {
			return false;
		}
		if ($user->isAdmin()) {
			return true;
		}

		$course = $this->getCourse($courseId);
		if (empty($course)) {
			return $user->isAdmin();
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $user->id);
		if ($member and ($member['role'] == 'teacher')) {
			return true;
		}

		return false;
	}


	public function tryTakeCourse($courseId)
	{
		$course = $this->getCourse($courseId);
		if (empty($course)) {
			throw $this->createNotFoundException();
		}
		$user = $this->getCurrentUser();
		if (!$user->isLogin()) {
			throw $this->createAccessDeniedException('您尚未登录用户，请登录后再查看！');
		}
		
		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $user['id']);
		if (count(array_intersect($user['roles'], array('ROLE_ADMIN', 'ROLE_SUPER_ADMIN'))) > 0) {
			return array($course, $member);
		}

		if (empty($member)) {
			throw $this->createAccessDeniedException('您不是课程学生，不能查看课程内容！');
		}

		return array($course, $member);
	}

	public function isMemberNonExpired($course, $member)
	{
		if (empty($course) or empty($member)) {
			throw $this->createServiceException("course, member参数不能为空");
		}

		/*
		如果课程设置了限免时间，那么即使expiryDay为0，学生到了deadline也不能参加学习
		if ($course['expiryDay'] == 0) {
			return true;
		}
		*/
		if ($member['deadline'] == 0) {
			return true;
		}

		if ($member['deadline'] > time()) {
			return true;
		}

		return false;
	}

	public function canTakeCourse($course)
	{
		$course = !is_array($course) ? $this->getCourse(intval($course)) : $course;
		if (empty($course)) {
			return false;
		}

		$user = $this->getCurrentUser();
		if (!$user->isLogin()) {
			return false;
		}

		if (count(array_intersect($user['roles'], array('ROLE_ADMIN', 'ROLE_SUPER_ADMIN'))) > 0) {
			return true;
		}
		//临时增加这行代码，紧急解决复制课程的试卷无法考试的bug.
		if($course['parentId'] == 0) {
			return true;
		}
		$this->tryBecomeCourseMember($user['id'], $course['classId'], $course['id']);
		
		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($course['id'], $user['id']);
		if ($member) {
			return true;
		}

		return false;
	}

	public function tryBecomeCourseMember($userId, $classId, $courseId)
	{
		$canViewRoles = array('STUDENT', 'PARENT');
		$classMember = $this->getClassesService()->getMemberByUserIdAndClassId($userId, $classId);
		$courseMember = $this->getCourseMember($courseId, $userId);
		$member = array();
		if(empty($courseMember) && $classMember && in_array($classMember['role'], $canViewRoles)) {
			if($classMember['role'] == 'STUDENT') {
				$member = $this->becomeStudent($courseId, $userId);
			} else {
				$member = $this->becomeGuest($courseId, $userId);
			}
		}

		return $member;
	}

	public function tryLearnCourse($courseId)
	{
		$course = $this->getCourseDao()->getCourse($courseId);
		if (empty($course)) {
			throw $this->createNotFoundException();
		}

		$user = $this->getCurrentUser();
		if (empty($user)) {
			throw $this->createAccessDeniedException('未登录用户，无权操作！');
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $user['id']);
		if (empty($member) or !in_array($member['role'], array('admin', 'teacher', 'student'))) {
			throw $this->createAccessDeniedException('您不是课程学生，不能学习！');
		}

		return array($course, $member);
	}

	public function getCourseAnnouncement($courseId, $id)
	{
		$announcement = $this->getAnnouncementDao()->getAnnouncement($id);
		if (empty($announcement) or $announcement['courseId'] != $courseId) {
			return null;
		}
		return $announcement;
	}

	public function findAnnouncements($courseId, $start, $limit)
	{
		return $this->getAnnouncementDao()->findAnnouncementsByCourseId($courseId, $start, $limit);
	}

	public function findAnnouncementsByCourseIds(array $ids, $start, $limit)
	{
		return $this->getAnnouncementDao()->findAnnouncementsByCourseIds($ids,$start, $limit);
	}
	
	public function createAnnouncement($courseId, $fields)
	{
		$course = $this->tryManageCourse($courseId);
        if (!ArrayToolkit::requireds($fields, array('content'))) {
        	$this->createNotFoundException("课程公告数据不正确，创建失败。");
        }

        if(isset($fields['content'])){
        	$fields['content'] = $this->purifyHtml($fields['content']);
        }

		$announcement = array();
		$announcement['courseId'] = $course['id'];
		$announcement['content'] = $fields['content'];
		$announcement['userId'] = $this->getCurrentUser()->id;
		$announcement['createdTime'] = time();
		return $this->getAnnouncementDao()->addAnnouncement($announcement);
	}



	public function updateAnnouncement($courseId, $id, $fields)
	{
		$course = $this->tryManageCourse($courseId);

        $announcement = $this->getCourseAnnouncement($courseId, $id);
        if(empty($announcement)) {
        	$this->createNotFoundException("课程公告{$id}不存在。");
        }

        if (!ArrayToolkit::requireds($fields, array('content'))) {
        	$this->createNotFoundException("课程公告数据不正确，更新失败。");
        }
        
        if(isset($fields['content'])){
        	$fields['content'] = $this->purifyHtml($fields['content']);
        }

        return $this->getAnnouncementDao()->updateAnnouncement($id, array(
        	'content' => $fields['content']
    	));
	}

	public function deleteCourseAnnouncement($courseId, $id)
	{
		$course = $this->tryManageCourse($courseId);
		$announcement = $this->getCourseAnnouncement($courseId, $id);
		if(empty($announcement)) {
			$this->createNotFoundException("课程公告{$id}不存在。");
		}

		$this->getAnnouncementDao()->deleteAnnouncement($id);
	}
	
	public function generateLessonReplay($courseId,$lessonId)
	{
		$course = $this->tryManageCourse($courseId);
		$lesson = $this->getLessonDao()->getLesson($lessonId);
		$mediaId = $lesson["mediaId"];
		$client = LiveClientFactory::createClient();
		$replayList = $client->createReplayList($mediaId, "录播回放", $lesson["liveProvider"]);

		if(array_key_exists("error", $replayList)){
			return $replayList;
		}
		
		$this->getCourseLessonReplayDao()->deleteLessonReplayByLessonId($lessonId);

		if(array_key_exists("data", $replayList)){
			$replayList = json_decode($replayList["data"], true);
		}

		foreach ($replayList as $key => $replay) {
			$fields = array();
			$fields["courseId"] = $courseId;
			$fields["lessonId"] = $lessonId;
			$fields["title"] = $replay["subject"];
			$fields["replayId"] = $replay["id"];
			$fields["userId"] = $this->getCurrentUser()->id;
			$fields["createdTime"] = time();
			$this->getCourseLessonReplayDao()->addCourseLessonReplay($fields);
		}
		$fields = array(
			"replayStatus" => "generated"
		);
		$this->getLessonDao()->updateLesson($lessonId, $fields);
		return $replayList;
	}

	public function entryReplay($lessonId, $courseLessonReplayId)
	{
		$lesson = $this->getLessonDao()->getLesson($lessonId);
		list($course, $member) = $this->tryTakeCourse($lesson['courseId']);

		$courseLessonReplay = $this->getCourseLessonReplayDao()->getCourseLessonReplay($courseLessonReplayId);
		$user = $this->getCurrentUser();

		$args = array(
			'liveId' => $lesson["mediaId"], 
			'replayId' => $courseLessonReplay["replayId"], 
			'provider' => $lesson["liveProvider"],
            'user' => $user['email'],
            'nickname' => $user['nickname']
		);

		$client = LiveClientFactory::createClient();
		$url = $client->entryReplay($args);
		return $url['url'];
	}

	public function getCourseLessonReplayByLessonId($lessonId)
	{
		return $this->getCourseLessonReplayDao()->getCourseLessonReplayByLessonId($lessonId);
	}

	public function deleteCourseLessonReplayByLessonId($lessonId)
	{
		$this->getCourseLessonReplayDao()->deleteLessonReplayByLessonId($lessonId);
	}

    public function copyCourseForClass($parentId, $classId, $compulsory, $teacherId)
    {
        $this->getCourseDao()->getConnection()->beginTransaction();
        try {
            $newCourse=$this->copyCourse($parentId,$teacherId,$classId,$compulsory);
            $chapterIdMap=$this->copyChapters($parentId,$newCourse['id']);
            $testpaperIdMap=$this->copyTestpapers($parentId,$newCourse['id']);
            $lessonIdMap=$this->copyLessons($parentId,$newCourse['id'],$chapterIdMap,$testpaperIdMap);
            $this->copyMaterials($parentId,$newCourse['id'],$lessonIdMap);
            $this->copyHomeworks($parentId,$newCourse['id'],$lessonIdMap);
            $this->copyExercises($parentId,$newCourse['id'],$lessonIdMap);
			$this->increaseFileUsedCount($newCourse['id']);
            
            //commit if no error
            $this->getCourseDao()->getConnection()->commit();

        } catch(\Exception $e) {
            //roll back if has error
            $this->getCourseDao()->getConnection()->rollback();
            throw $e; 
        }

        return CourseSerialize::unserialize($newCourse);
    }

    private function increaseFileUsedCount($courseId){
    	// Increase the course lesson files usage counts, if there are files used by the course lessons.
		$lessons = $this->getLessonDao()->findLessonsByCourseId($courseId);

		if(!empty($lessons)){
			$fileIds = ArrayToolkit::column($lessons, "mediaId");

			if(!empty($fileIds)){
				$this->getUploadFileService()->increaseFileUsedCount(array_unique($fileIds));
			}
		}
    }

    public function updateCourseStructureChanged($courseId,$structureChanged)
    {
    	$this->getCourseDao()->updateCourse($courseId, array('structureChanged'=>$structureChanged));
    }

    public function updateLessonContentChanged($lessonId,$contentChanged)
    {
    	$this->getLessonDao()->updateLesson($lessonId,array('contentChanged'=>$contentChanged));
    }

    public function findCoursesByParentId($parentId)
    {
        return $this->getCourseDao()->findCoursesByParentId($parentId);
    }

    public function findLessonsBySourceId($sourceId)
    {
        return $this->getLessonDao()->findLessonsBySourceId($sourceId);
    }

    public function classHasCourse($classId, $parentId)
    {
    	$course = $this->getCourseDao()
			->findCourseByClassIdAndParentId($classId, $parentId);
		if (!empty($course)) {
			return true;
		} else {
			return false;
		}
	}

    //复制主课程本体
    private function copyCourse($parentId,$teacherId,$classId,$compulsory)
    {
        $class = $this->getClassesService()->getClass($classId);
        $templateCourse = $this->getCourseDao()->getCourse($parentId);
        unset($templateCourse['id']);
        $templateCourse['parentId'] = $parentId;
        $templateCourse['classId'] = $classId;
        $templateCourse['gradeId'] = $class['gradeId'];
        $templateCourse['term'] = $class['term'];
        $templateCourse['compulsory'] = $compulsory;
        $newCourse=$this->getCourseDao()->addCourse($templateCourse);
        //设置课程老师
        $this->setCourseTeachers(
            $newCourse['id'], 
            array(
                0 => array('id' => $teacherId,'isVisible' => 1)
            )
        );
        $member = $this->getClassesService()->getMemberByUserIdAndClassId($teacherId, $classId);
        if(empty($member)) {
            $newClassMember = array();
            $newClassMember['classId'] = $classId;
            $newClassMember['userId'] = $teacherId;
            $newClassMember['role'] = 'TEACHER';
            $newClassMember['createdTime'] = time();
            $this->getClassesService()->addClassMember($newClassMember);
        }
        return $newCourse;
    }

    //复制课时结构
    private function copyChapters($parentId,$newCourseId)
    {
        $chapters = $this->getChapterDao()->findChaptersByCourseId($parentId);

        $chapterIdMap = array();
        $newChapters = array();
        foreach ($chapters as $chapter) {
            $oldId = $chapter['id'];
            unset($chapter['id']);
            $chapter['courseId'] = $newCourseId;
            $chapter['sourceId']=$oldId;
            $newChapter = $this->getChapterDao()->addChapter($chapter);
            $chapterIdMap[$oldId] = $newChapter['id'];
            $newChapters[] = $newChapter; 
        }

        //修改复制的Chapter的parentId
        foreach ($newChapters as $newChapter) {
            if(array_key_exists($newChapter['parentId'], $chapterIdMap)) {
                $newChapter['parentId'] = $chapterIdMap[$newChapter['parentId']];
                $this->getChapterDao()->updateChapter($newChapter['id'],
                    array('parentId'=>$newChapter['parentId']));
            }
        }
        return $chapterIdMap;
    }

    //复制试卷
    private function copyTestpapers($parentId,$newCourseId)
    {
        $testpapers=$this->getTestpaperService()->findAllTestpapersByTarget($parentId);
        $testpaperIdMap=array();
        foreach ($testpapers as $testpaper) {
            $oldId=$testpaper['id'];
            $testpaper['target']='course-'.$newCourseId;
            $testpaper['sourceId']=$oldId;
            unset($testpaper['id']);
            $newTestpaper=$this->getTestpaperService()->addTestpaper($testpaper,'copy');

            $items=$this->getTestpaperService()->getTestpaperItems($oldId);
            foreach ($items as $item) {
                $item['testId']=$newTestpaper['id'];
                unset($item['id']);
                $this->getTestpaperService()->addTestpaperItem($item);
            }
            $testpaperIdMap[$oldId]=$newTestpaper['id'];
        }
        return $testpaperIdMap;
    }

    //复制课时
    private function copyLessons($parentId,$newCourseId,$chapterIdMap,$testpaperIdMap)
    {
        $lessons = $this->getLessonDao()->findLessonsByCourseId($parentId);
        $newLessons = array();
        $lessonIdMap=array();
        foreach ($lessons as $lesson) {
            $oldId=$lesson['id'];
            $lesson['courseId'] = $newCourseId;
            $lesson['sourceId'] = $oldId;
            $lesson['materialNum']=0;
            unset($lesson['id']);
            $newLesson = $this->getLessonDao()->addLesson($lesson);
            $newLessons[] = $newLesson;
            $lessonIdMap[$oldId]=$newLesson['id'];
        }

        //修改复制的Lesson的chapterId
        foreach ($newLessons as $newLesson) {
            if(array_key_exists($newLesson['chapterId'], $chapterIdMap)) {
                $newLesson['chapterId'] = $chapterIdMap[$newLesson['chapterId']];
                $conditions=array('chapterId'=>$newLesson['chapterId']);
                if($newLesson['type']=='testpaper' && array_key_exists($newLesson['mediaId'], $testpaperIdMap)){
                    $conditions['mediaId']=$testpaperIdMap[$newLesson['mediaId']];
                }
                $this->getLessonDao()->updateLesson($newLesson['id'],$conditions);
            }
        }
        return $lessonIdMap;
    }

    //复制课时材料
    private function copyMaterials($parentId,$newCourseId,$lessonIdMap)
    {
        $materials=$this->getCourseMaterialService()->findCourseMaterials($parentId,0,PHP_INT_MAX);
        foreach ($materials as $material) {
            $material['courseId']=$newCourseId;
            $material['lessonId']=$lessonIdMap[$material['lessonId']];
            unset($material['id']);
            $this->getCourseMaterialService()->uploadMaterial($material);

            // Forcely reset the lesson content changed flag
            $this->updateLessonContentChanged($material['lessonId'], 0);
        }
    }

    //复制作业
    private function copyHomeworks($parentId,$newCourseId,$lessonIdMap)
    {
        $lessons = $this->getLessonDao()->findLessonsByCourseId($parentId);
        $lessonIds=ArrayToolkit::column($lessons,'id');
        $homeworks=$this->getHomeworkService()->findHomeworksByCourseIdAndLessonIds($parentId, $lessonIds);
        
        foreach ($homeworks as $homework) {
            $oldId=$homework['id'];
            $homework['courseId']=$newCourseId;
            $homework['lessonId']=$lessonIdMap[$homework['lessonId']];
            unset($homework['id']);
            $newHomework=$this->getHomeworkService()->addHomework($homework);

            $items=$this->getHomeworkService()->findItemsByHomeworkId($oldId);
            foreach ($items as $item) {
                $item['homeworkId']=$newHomework['id'];
                unset($item['id']);
                $this->getHomeworkService()->addHomeworkItem($item);
            }
        }
    }

    //复制练习
    private function copyExercises($parentId,$newCourseId,$lessonIdMap)
    {
        $lessons = $this->getLessonDao()->findLessonsByCourseId($parentId);
        $lessonIds=ArrayToolkit::column($lessons,'id');
        $exercises=$this->getExerciseServise()->findExercisesByLessonIds($lessonIds);

        foreach ($exercises as $exercise) {
            $exercise['courseId']=$newCourseId;
            $exercise['lessonId']=$lessonIdMap[$exercise['lessonId']];
            unset($exercise['id']);
            $newExercise=$this->getExerciseServise()->addExercise($exercise);
        }
    }

    //模版课程修改时，修改所有子课程
    private function updateChildCourses($parentId, $fields)
    {
        $courses=$this->findCoursesByParentId($parentId);
        foreach ($courses as $course) {
            if(empty($course['structureChanged'])){
                $this->getCourseDao()->updateCourse($course['id'],$fields);
            }
        }
    }

    //模板课程删除时，复制出来的课程删除
    private function deleteChildCourses($parentCourseId)
    {
    	$courses=$this->findCoursesByParentId($parentCourseId);
        foreach ($courses as $course) {
        	if(empty($course['structureChanged'])){
		    	$this->getMemberDao()->deleteMembersByCourseId($course['id']);
				$this->getLessonDao()->deleteLessonsByCourseId($course['id']);
				$this->getChapterDao()->deleteChaptersByCourseId($course['id']);

				$this->getCourseDao()->deleteCourse($course['id']);

				if($course["type"] == "live"){
					$this->getCourseLessonReplayDao()->deleteLessonReplayByCourseId($course['id']);
				}
            }
		}
    }

    //模板课程章节新增时，子课程同步新增
    private function addChildChapters($sourceId,$chapter)
    {
    	$courses=$this->findCoursesByParentId($chapter['courseId']);
        foreach ($courses as $course) {
            if(empty($course['structureChanged'])){
                $fields=$chapter;
                //若模板课程是在某个章下加的节，则在子课程中找出对应的章数据
                if(!empty($chapter['parentId'])){
                    $parentChapter=$this->getChapterDao()->getChapterBySourceIdAndCourseId($chapter['parentId'],$course['id']);
                    $fields['parentId']=$parentChapter['id'];
                }
                $fields['courseId']=$course['id'];
                $fields['sourceId']=$sourceId;
                $this->getChapterDao()->addChapter($fields);
            }
        }
    }

    //模板课程章节修改时，子课程章节修改
    private function updateChildChapters($parentCourseId,$sourceId,$fields)
    {
        $courses=$this->findCoursesByParentId($parentCourseId);
        foreach ($courses as $course) {
            if(empty($course['structureChanged'])){
                $childChapter=$this->getChapterDao()->getChapterBySourceIdAndCourseId($sourceId,$course['id']);
                $this->getChapterDao()->updateChapter($childChapter['id'], $fields);
            }
        }
    }
    //模板课程章节删除时，子课程章节删除
    private function deleteChildChapters($sourceChapter)
    {
        $courses=$this->findCoursesByParentId($sourceChapter['courseId']);
        foreach ($courses as $course) {
            if(empty($course['structureChanged'])){
                $childChapter=$this->getChapterDao()->getChapterBySourceIdAndCourseId($sourceChapter['id'],$course['id']);
                $this->getChapterDao()->deleteChapter($childChapter['id']);
                $prevChapter = array('id' => 0);
                foreach ($this->getCourseChapters($course['id']) as $chapter) {
                    if ($chapter['number'] < $childChapter['number']) {
                        $prevChapter = $chapter;
                    }
                }

                $lessons = $this->getLessonDao()->findLessonsByChapterId($childChapter['id']);
                foreach ($lessons as $lesson) {
                    $this->getLessonDao()->updateLesson($lesson['id'], array('chapterId' => $prevChapter['id']));
                }
            }
        }
    }

    //模板课程新增课时，子课程也新增课时
    private function addChildLessons($sourceLesson){
        $courses=$this->findCoursesByParentId($sourceLesson['courseId']);
        $oldLessonId=$sourceLesson['id'];
        $oldChpaterId=$sourceLesson['chapterId'];
        $oldLessonMediaId = $sourceLesson['mediaId'];

        foreach ($courses as $course) {
            if(empty($course['structureChanged'])){
                $childChapter=$this->getChapterDao()->getChapterBySourceIdAndCourseId($oldChpaterId,$course['id']);
                $sourceLesson['chapterId']=$childChapter['id'] ?: '0';
                $sourceLesson['sourceId']=$oldLessonId;
                $sourceLesson['courseId']=$course['id'];
                unset($sourceLesson['id']);
                if($sourceLesson['type']=='testpaper'){
                    $childTestPaper=$this->getTestpaperService()->getTestpaperBySourceIdAndTarget($oldLessonMediaId, $course['id']);
                    $sourceLesson['mediaId']=$childTestPaper['id'];
                }
                $this->getLessonDao()->addLesson(
                    LessonSerialize::serialize($sourceLesson)
                );
            }
        }
    }
    private function sortChildLessons($sourceId,$fields)
    {
    	$lessons=$this->findLessonsBySourceId($sourceId);
    	$oldChpaterId=$fields['chapterId'];
        foreach ($lessons as $lesson) {
            $course=$this->getCourse($lesson['courseId']);
            if(empty($course['structureChanged'])){
                $childChapter=$this->getChapterDao()->getChapterBySourceIdAndCourseId($oldChpaterId,$course['id']);
                $fields['chapterId']=$childChapter['id'];
                $this->getLessonDao()->updateLesson($lesson['id'], $fields);
            }
        }
    }
    //随模板课时的修改，子课时修改
    private function updateChildLessons($sourceId,$fields)
    {
    	$oldLessonMediaId = isset($fields['mediaId']) ? $fields['mediaId'] : null;

        $lessons=$this->findLessonsBySourceId($sourceId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                
                if(isset($oldLessonMediaId) && isset($fields['type']) && $fields['type']=='testpaper'){
                    $childTestPaper=$this->getTestpaperService()->getTestpaperBySourceIdAndTarget($oldLessonMediaId, $course['id']);
                    $fields['mediaId']=$childTestPaper['id'];
                }

                $this->getLessonDao()->updateLesson($lesson['id'], $fields);
            }
        }
    }

    //模板课时删除，子课时删除(同时删除material和learnlesson数据)
    private function deleteChildLessons($sourceId)
    {
        $lessons=$this->findLessonsBySourceId($sourceId);
        foreach ($lessons as $lesson) {
            $course=$this->getCourse($lesson['courseId']);
            if(empty($course['structureChanged']) && empty($lesson['contentChanged'])){
                $this->getLessonLearnDao()->deleteLearnsByLessonId($lesson['id']);
                $this->getCourseMaterialService()->deleteMaterialsByLessonId($lesson['id']);
                $this->getLessonDao()->deleteLesson($lesson['id']);
            }
        }
    }

	private function sortItems(array $items,array $itemIds,$course=array())
	{
		$lessonNum = $chapterNum = $unitNum = $seq = 0;
		$currentChapter = $rootChapter = array('id' => 0);
		$itemArray=array();
		foreach ($itemIds as $itemId) {
			$seq ++;
			list($type, ) = explode('-', $itemId);
			switch ($type) {
				case 'lesson':
					$lessonNum ++;
					$item = $items[$itemId];
					$fields = array('number' => $lessonNum, 'seq' => $seq, 'chapterId' => $currentChapter['id']);
					if ($fields['number'] != $item['number'] or $fields['seq'] != $item['seq'] or $fields['chapterId'] != $item['chapterId']) {
						if(!empty($course) && empty($course['parentId'])){
                            $this->sortChildLessons($item['id'], $fields);
                        }else if(!empty($course) && empty($course['structureChanged'])){
				        	$this->getCourseDao()->updateCourse($course['id'], array('structureChanged'=>1));
				        }
                        $item=$this->getLessonDao()->updateLesson($item['id'], $fields);
						$item['itemType'] = 'lesson';
					}
					$itemArray["lesson-{$item['id']}"] = $item;

					break;
				case 'chapter':
					$item = $currentChapter = $items[$itemId];
				    if ($item['type'] == 'unit') {
				    	$unitNum ++;
						$fields = array('number' => $unitNum, 'seq' => $seq, 'parentId' => $rootChapter['id']);
				    } else {
				    	$chapterNum ++;
				    	$unitNum = 0;
						$rootChapter = $item;
						$fields = array('number' => $chapterNum, 'seq' => $seq, 'parentId' => 0);
				    }
					if ($fields['parentId'] != $item['parentId'] or $fields['number'] != $item['number'] or $fields['seq'] != $item['seq']) {
                        if(!empty($course) && empty($course['parentId'])){
                            $this->updateChildChapters($course['id'],$item['id'], $fields);
                        }else if(!empty($course) && empty($course['structureChanged'])){
				        	$this->getCourseDao()->updateCourse($course['id'], array('structureChanged'=>1));
				        }
                        $item=$this->getChapterDao()->updateChapter($item['id'], $fields);
						$item['itemType'] = 'chapter';
					}
					$itemArray["chapter-{$item['id']}"] = $item;
					break;
			}
		}

		return $itemArray;
	}

	private function getCourseLessonReplayDao()
    {
        return $this->createDao('Course.CourseLessonReplayDao');
    }

    private function getAnnouncementDao()
    {
    	return $this->createDao('Course.CourseAnnouncementDao');
    }

	private function hasCourseManagerRole($courseId, $userId) 
	{
		if($this->getUserService()->hasAdminRoles($userId)){
			return true;
		}

		$member = $this->getMemberDao()->getMemberByCourseIdAndUserId($courseId, $userId);
		if ($member and ($member['role'] == 'teacher')) {
			return true;
		}

		return false;
	}

	private function simplifyCousrse($course)
	{
		return array(
			'id' => $course['id'],
			'title' => $course['title'],
			'picture' => $course['middlePicture'],
			'type' => $course['type'],
			'rating' => $course['rating'],
			'about' => StringToolkit::plain($course['about'], 100),
			'price' => $course['price'],
		);
	}

	private function simplifyLesson($lesson)
	{
		return array(
			'id' => $lesson['id'],
			'number' => $lesson['number'],
			'type' => $lesson['type'],
			'title' => $lesson['title'],
			'summary' => StringToolkit::plain($lesson['summary'], 100),
		);
	}

    private function getCourseDao ()
    {
        return $this->createDao('Course.CourseDao');
    }

    private function getOrderDao ()
    {
        return $this->createDao('Course.OrderDao');
    }

    private function getFavoriteDao ()
    {
        return $this->createDao('Course.FavoriteDao');
    }

    private function getMemberDao ()
    {
        return $this->createDao('Course.CourseMemberDao');
    }

    private function getLessonDao ()
    {
        return $this->createDao('Course.LessonDao');
    }

        private function getCourseDraftDao ()
    {
        return $this->createDao('Course.CourseDraftDao');
    }

    private function getLessonLearnDao ()
    {
        return $this->createDao('Course.LessonLearnDao');
    }

    private function getLessonViewedDao ()
    {
        return $this->createDao('Course.LessonViewedDao');
    }

    private function getLessonViewDao ()
    {
        return $this->createDao('Course.LessonViewDao');
    }

    private function getChapterDao()
    {
        return $this->createDao('Course.CourseChapterDao');
    }

    private function getCategoryService()
    {
    	return $this->createService('Taxonomy.CategoryService');
    }

    private function getFileService()
    {
    	return $this->createService('Content.FileService');
    }

    private function getUserService()
    {
    	return $this->createService('User.UserService');
    }

    private function getOrderService()
    {
    	return $this->createService('Order.OrderService');
    }

    private function getVipService()
    {
    	return $this->createService('Vip:Vip.VipService');
    }

    private function getReviewService()
    {
    	return $this->createService('Course.ReviewService');
    }

    protected function getLogService()
    {
        return $this->createService('System.LogService');        
    }

    private function getDiskService()
    {
        return $this->createService('User.DiskService');
    }

    private function getUploadFileService()
    {
        return $this->createService('File.UploadFileService');
    }

    private function getMessageService(){
        return $this->createService('User.MessageService');
    }

    private function getSettingService()
    {
        return $this->createService('System.SettingService');
    }

    private function getLevelService()
    {
    	return $this->createService('User.LevelService');
    }
    
    private function getTagService()
    {
        return $this->createService('Taxonomy.TagService');
    }

    private function getClassesService()
    {
        return $this->createService('Classes.ClassesService');
    }

    private function getStatusService()
    {
        return $this->createService('User.StatusService');
    }

    private function getKnowledgeService()
    {
        return $this->createService('Taxonomy.KnowledgeService');
    }

    private function getCourseMaterialService()
    {
        return $this->createService('Course.MaterialService');
    }

    private function getTestpaperService()
    {
        return $this->createService('Testpaper.TestpaperService');
    }

    private function getHomeworkService()
    {
        return $this->createService('Homework.K12HomeworkService');
    }

    private function getExerciseServise()
    {
        return $this->createService('Homework.K12ExerciseService');
    }
}

class CourseSerialize
{
    public static function serialize(array &$course)
    {
    	if (isset($course['tags'])) {
    		if (is_array($course['tags']) and !empty($course['tags'])) {
    			$course['tags'] = '|' . implode('|', $course['tags']) . '|';
    		} else {
    			$course['tags'] = '';
    		}
    	}
    	
    	if (isset($course['goals'])) {
    		if (is_array($course['goals']) and !empty($course['goals'])) {
    			$course['goals'] = '|' . implode('|', $course['goals']) . '|';
    		} else {
    			$course['goals'] = '';
    		}
    	}

    	if (isset($course['audiences'])) {
    		if (is_array($course['audiences']) and !empty($course['audiences'])) {
    			$course['audiences'] = '|' . implode('|', $course['audiences']) . '|';
    		} else {
    			$course['audiences'] = '';
    		}
    	}

    	if (isset($course['teacherIds'])) {
    		if (is_array($course['teacherIds']) and !empty($course['teacherIds'])) {
    			$course['teacherIds'] = '|' . implode('|', $course['teacherIds']) . '|';
    		} else {
    			$course['teacherIds'] = null;
    		}
    	}

        return $course;
    }


    public static function unserialize(array $course = null)
    {
    	if (empty($course)) {
    		return $course;
    	}

		$course['tags'] = empty($course['tags']) ? array() : explode('|', trim($course['tags'], '|'));

		if(empty($course['goals'] )) {
			$course['goals'] = array();
		} else {
			$course['goals'] = explode('|', trim($course['goals'], '|'));
		}

		if(empty($course['audiences'] )) {
			$course['audiences'] = array();
		} else {
			$course['audiences'] = explode('|', trim($course['audiences'], '|'));
		}

		if(empty($course['teacherIds'] )) {
			$course['teacherIds'] = array();
		} else {
			$course['teacherIds'] = explode('|', trim($course['teacherIds'], '|'));
		}

		return $course;
    }

    public static function unserializes(array $courses)
    {
    	return array_map(function($course) {
    		return CourseSerialize::unserialize($course);
    	}, $courses);
    }
}


class LessonSerialize
{
    public static function serialize(array $lesson)
    {
        return $lesson;
    }

    public static function unserialize(array $lesson = null)
    {
        return $lesson;
    }

    public static function unserializes(array $lessons)
    {
    	return array_map(function($lesson) {
    		return LessonSerialize::unserialize($lesson);
    	}, $lessons);
    }
}