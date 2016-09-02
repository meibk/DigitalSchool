<?php

namespace Topxia\Service\Course\Dao;

interface CourseDao
{
    const TABLENAME = 'course';

    public function getCourse($id);

    public function getCoursesCount();

    public function findCoursesByIds(array $ids);

    public function findCoursesByParentId($parentId);

    public function findCoursesByTagIdsAndStatus(array $tagIds, $status, $start, $limit);

    public function findCoursesByAnyTagIdsAndStatus(array $tagIds, $status, $orderBy, $start, $limit);

    public function searchCourses($conditions, $orderBy, $start, $limit);

    public function searchCourseCount($conditions);

    public function addCourse($course);

    public function updateCourse($id, $fields);

    public function deleteCourse($id);
    
    public function waveCourse($id,$field,$diff);

    public function analysisCourseDataByTime($startTime,$endTime);

    public function findCourseByClassIdAndParentId($classId, $parentId);

    public function findCoursesByClassId($classId);

    public function findCoursesCountByLessThanCreatedTime($endTime);

    public function analysisCourseSumByTime($endTime);

    public function findCoursesByLikeTitle($title);

}