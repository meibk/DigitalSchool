<?php

namespace Topxia\Service\Grade\Dao;

interface GradeDao
{
	public function getGrade($id);

	public function getGradeByName($name);

	public function findAllGrades();

	public function findGradesByGroup($group);

	public function addGrade($grade);

	public function updateGrade($grade);

	public function getMaxGradeId();

	public function deleteGrades($gradeGroup);
}