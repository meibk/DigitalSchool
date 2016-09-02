<?php

namespace Topxia\Service\Classes;

interface ClassesService
{
    /**
    *ClassService API
    */
    public function getClass($id);

    public function getClassByGradeAndName($gradeId, $className);

    public function findClassesByIds(array $ids);

    public function searchClasses($conditions, $sort = 'latest', $start, $limit);

    public function searchClassCount($conditions);

    public function getStudentClass($userId);

    public function getClassHeadTeacher($classId);

    public function getClassesByHeadTeacherId($headTeacherId);

    public function createClass($class);

    public function updateClass($id, $fields);

    public function updateClassStudentNum($num,$id);

    public function deleteClass($id);

    public function checkPermission($name, $classId);

    public function getMemberByUserIdAndClassId($userId, $classId);

    public function getStudentMemberByUserIdAndClassId($userId, $classId);

    public function findStudentMembersByUserIdsAndClassId($userIds, $classId);

    public function findClassStudentMembers($classId);

    public function findClassMemberByUserNumber($number, $classId);

    public function findClassByUserNumber($number);

    public function findClassMembersByUserIds(array $userIds);

    public function searchClassMembers(array $conditions, array $orderBy, $start, $limit);

    public function searchClassMemberCount(array $conditions);

    public function addClassMember(array $classMember);

    public function changeClassMemberRole($userId, $classId, $role);

    public function updateClassMember(array $fields, $id);

    public function deleteClassMemberByUserId($userId);

    public function importStudents($classId, array $userIds);

    public function importParents($classId, array $userIds);

    public function refreashStudentRank($userId, $classId);

}