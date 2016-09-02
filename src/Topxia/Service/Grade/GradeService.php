<?php

namespace Topxia\Service\Grade;

interface GradeService
{
    public function getGrade($id);
	
    public function findAllGrades();

    public function findGradesByGroup($group);

    public function saveGrade($grade);

    public function deleteGrades($gradeGroup);

    public function initGradeSettings();
}