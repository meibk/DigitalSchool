<?php

namespace Topxia\Service\Grade\Impl;

use Topxia\Service\Common\BaseService;
use Topxia\Service\Grade\GradeService;
use Topxia\WebBundle\Twig\Extension\DataDict;


class GradeServiceImpl extends BaseService implements GradeService {

    public function findAllGrades()
    {
          return $this->getGradeDao()->findAllGrades();
    }

    public function getGrade($id){
        return $this->getGradeDao()->getGrade($id);
    }

    public function getGradeByName($name){
        return $this->getGradeDao()->getGradeByName($name);
    }

    public function findGradesByGroup($group){
        return $this->getGradeDao()->findGradesByGroup($group);
    }

    public function saveGrade($grade){
        if(empty($grade['id'])){
            $maxGradeId = $this->getGradeDao()->getMaxGradeId();
            $grade['id'] = $maxGradeId + 1;
        }

        return $this->getGradeDao()->addGrade($grade);
    }

    public function initGradeSettings(){
        $schoolSettings = $this->getSettingService()->get('school', array());

        // Delete all existing grades
        $this->deleteGrades('primary');
        $this->deleteGrades('middle');
        $this->deleteGrades('high');

        // Generate default grades
        if(isset($schoolSettings['primarySchool'])){
            $primaryGrades = $this->generateDefaultGrades('primary');

            $this->saveGrades($primaryGrades);
        }
        
        if(isset($schoolSettings['middleSchool'])){
            $middleGrades = $this->generateDefaultGrades('middle');

            $this->saveGrades($middleGrades);
        }

        if(isset($schoolSettings['highSchool'])){
            $highGrades = $this->generateDefaultGrades('high');

            $this->saveGrades($highGrades);
        }
    }

    private function saveGrades($grades){
        $seq = 1;

        foreach ($grades as $grade) {
            if(!empty($grade)){
                $grade['seq'] = $seq ++;
                $this->saveGrade($grade);
            }
        }
    }

    private function generateDefaultGrades($gradeGroup){
        $defaultGrades = array();

        $defaultSchoolData = DataDict::dict($gradeGroup . 'School');

        foreach ($defaultSchoolData as $key => $value) {
            $grade = array();
            $grade['id'] = $key;
            $grade['name'] = $value;
            $grade['seq'] = $key;
            $grade['gradeGroup'] = $gradeGroup;


            array_push($defaultGrades, $grade); 
        }

        return $defaultGrades;
    }

    public function deleteGrades($gradeGroup){
        return $this->getGradeDao()->deleteGrades($gradeGroup);
    }

    private function getSettingService()
    {
        return $this->createService('System.SettingService');
    }

    private function getGradeDao() 
    {
        return $this->createDao('Grade.GradeDao');
    }
}
