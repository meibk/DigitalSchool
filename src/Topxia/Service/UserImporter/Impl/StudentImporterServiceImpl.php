<?php
namespace Topxia\Service\UserImporter\Impl;

use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Topxia\Service\UserImporter\BaseImporterService;
use Topxia\Service\UserImporter\StudentImporterService;
use PHPExcel_IOFactory;
use PHPExcel_Cell;

class StudentImporterServiceImpl extends BaseImporterService implements StudentImporterService
{
    private $otherNecessaryFields = array('number' => '学号');

    public function importUserByUpdate($students, $classId)
    {
        $this->getUserDao()->getConnection()->beginTransaction();
        try{
            $newStudents = array();

            for($i=0;$i<count($students);$i++){
                $student = $this->getUserDao()->getUserByNumber($students[$i]["number"]);

                // Update existing student
                if($student) {
                    $student=UserSerialize::unserialize($student);
                    $this->getUserService()->changePassword($student["id"],$students[$i]["password"]);
                    $this->getUserService()->changeEmail($student["id"],$students[$i]["email"]);
                    $this->getUserService()->changeTrueName($student["id"],$students[$i]["truename"]);
                    empty($students[$i]['mobile']) ? : $this->getUserService()->changeMobile($student["id"],$students[$i]['mobile']);
                    $this->getUserService()->updateUserProfile($student["id"],$students[$i]); 
                } else {
                    array_push($newStudents, $students[$i]); 
                }
                             
            }

            // Import non-existing students
            if(count($newStudents) > 0){
                $this->importUserByIgnore($newStudents, $classId);
            }

            $this->getUserDao()->getConnection()->commit();

        }catch(\Exception $e){
            $this->getUserDao()->getConnection()->rollback();
            throw $e;
        }
    }

    public function importUserByIgnore($students, $classId)
    {
        set_time_limit(120);

        $this->getUserDao()->getConnection()->beginTransaction();

        try{
            $classService = $this->getClassesService();
            $gradeService = $this->getGradeService();

            for($i=0;$i<count($students);$i++){
                $student = $this->createUser($students[$i]);
                $student['number'] = $students[$i]['number'];
                $student['nickname'] = $students[$i]['number'];
                $student['roles'] = array('ROLE_USER');

                $gradeName = $students[$i]['gradeName'];
                $className = $students[$i]['className'];

                $student = UserSerialize::unserialize(
                    $this->getUserDao()->addUser(UserSerialize::serialize($student))
                );

                $profile = $this->createUserProfile($student['id'], $students[$i]);
                $this->getProfileDao()->addProfile($profile);

                // Add the student into the proper class
                if(!empty($gradeName) and !empty($className)){
                    $grade = $gradeService->getGradeByName($gradeName);

                    if(!empty($grade)){
                        $class = $classService->getClassByGradeAndName($grade['id'], $className);

                        if(!empty($class)){
                            $this->getClassesService()->importStudents($class["id"], array($student['id']));
                        }
                    }


                }
            }

            $this->getUserDao()->getConnection()->commit();

        }catch(\Exception $e){
            $this->getUserDao()->getConnection()->rollback();
            throw $e;
        }
    }

    public function checkUserData($file, $rule, $classId)
    {
        set_time_limit(60);

        $result = array();
        $errorInfos = array();
        $checkInfo = array();
        $numberArray = array();
        $emailArray = array();
        $mobileArray = array();
        $allStuentData = array();
        $numberRepeatInfo = "";
        $emailRepeatInfo = "";
        $checkEmail = false;
        $checkMobile = false;
        $userService = $this->getUserService();
        $classService = $this->getClassesService();

        $objPHPExcel = PHPExcel_IOFactory::load($file);
        $objWorksheet = $objPHPExcel->getActiveSheet();
        $highestRow = $objWorksheet->getHighestRow(); 

        $highestColumn = $objWorksheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);   

        if($highestRow>3000){
            $result['status'] ='failed';
            $result['type'] = 'over_line_limit';
            $result['message'] = 'Excel超过3000行数据!';
            return $result;
        }

        $fieldArray = $this->getFieldArray($this->otherNecessaryFields);
        $execelTitle = array();
        for ($col = 0;$col < $highestColumnIndex;$col++)
        {
            $title = $this->trim($objWorksheet->getCellByColumnAndRow($col, 2)->getValue());
            if($title) {
                $execelTitle[$col] = $title."";
            }
            if($title == '邮箱') {
                $checkEmail = true;
            }
            if($title == '手机号码') {
                $checkMobile = true;
            }
        }

        $errorInfo = $this->checkNecessaryFields($execelTitle,$this->otherNecessaryFields); 
        if($errorInfo) {
            $result['status'] ='failed';
            $result['type'] = 'lack_fields';
            $result['message'] = $errorInfo;
            return $result; 
        }
        $matchFields = $this->matchExcelTitle($execelTitle, $fieldArray);
        for ($row = 3;$row <= $highestRow;$row++) 
        {
            $rowData = array();
            for ($col = 0;$col < $highestColumnIndex;$col++)
            {
                 $colData = $objWorksheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                 $rowData[$col]=$colData."";
                 unset($colData);
            }
            $student = array();
            foreach ($matchFields as $key => $value) {
                if($key == 'truename' || $key == 'number') {
                    $student[$key] = $this->trim($rowData[$value - 1]);
                } else {
                   $student[$key] = $rowData[$value - 1]; 
                }
                
            }
            unset($rowData);
            //姓名和学号为空直接跳过这行
            if(empty($student['number'])){
                $errorInfos[] = "第".$row."行的学号为空，请检查数据．";
                continue;
            } 

            if (empty($student['truename'])){
                $errorInfos[] = "第".$row."行的姓名为空，请检查数据．";
                continue;
            }

            if(!$checkEmail) {
                $student['email'] = $student['number'] . '@' . 'edusoho' . '.' . 'com';
            }

            $errorInfo = $this->validFields($student, $row, $matchFields, $checkEmail);
            
            if($errorInfo) {
                $errorInfos = array_merge($errorInfos, $errorInfo);
            }

            $student['gender'] = $student['gender'] == '男' ? 'male' : 'female';
            $numberArray[$row] = $student['number'];
            $emailArray[$row] = $student['email']; 
            if($checkMobile && $student['mobile'])  {
                $mobileArray[$row] = $student['mobile']; 
            }

            if($rule == "ignore" && $classService->findClassByUserNumber($student['number'])) {
                $errorInfos[] = '学号为' . $student['number'] . '已存在其他班级，请检查';     
                continue;
            }
            $existSeacher = $userService->getUserByNumber($student['number']);
            $existMobile = $existSeacher ? $existSeacher['mobile'] : null;
            if($checkMobile && $student['mobile'] && $existMobile != $student['mobile'] && !$userService->isMobileAvaliable($student['mobile'])) {
                $errorInfos[] = '第' . $row . '行手机号码为' . $student['mobile'] . '已存在，请检查数据．';
            }
            if($checkEmail && $rule=="ignore" && !$userService->isEmailAvaliable($student['email'])) {          
                $checkInfo[] = "第".$row."行的邮箱已存在，已略过．";
                continue;
            }
            if($checkEmail && $rule=="update" && !$userService->isEmailAvaliable($student['email'])) {          
                $errorInfos[] = "第".$row."行的邮箱已存在，请检查数据．";
                continue;
            }
            if(!$userService->isNumberAvaliable($student['number'])) { 

                if($rule=="ignore") {
                    $checkInfo[]="第".$row."行的学号已存在，已略过"; 
                    continue;
                }
                if($rule=="update") {
                    $checkInfo[]="第".$row."行的学号已存在，将会更新";          
                }
                $allStuentData[]= $student;            
                continue;
            }

            $allStuentData[]= $student;
            unset($student);
        }

   
        $numberRepeatInfo = $this->arrayRepeat($numberArray, "学号");

        $emailRepeatInfo = array();
        $mobileRepeatInfo = array();

        if($checkEmail) {
            $emailRepeatInfo = $this->arrayRepeat($emailArray, "邮箱");
        }

        if($checkMobile) {
            $mobileRepeatInfo = $this->arrayRepeat($mobileArray, '手机号码');
        }
        $errorInfos = array_merge($errorInfos, $numberRepeatInfo, $emailRepeatInfo, $mobileRepeatInfo);
        $result['status'] ='success';
        $result['errorInfos'] = $errorInfos;
        $result['checkInfo'] = $checkInfo;
        $result['allStuentData'] = $allStuentData;
        return $result;
    }

 

}

class UserSerialize
{
    public static function serialize(array $user)
    {
        $user['roles'] = empty($user['roles']) ? '' :  '|' . implode('|', $user['roles']) . '|';
        return $user;
    }

    public static function unserialize(array $user = null)
    {
        if (empty($user)) {
            return null;
        }
        $user['roles'] = empty($user['roles']) ? array() : explode('|', trim($user['roles'], '|')) ;
        return $user;
    }

    public static function unserializes(array $users)
    {
        return array_map(function($user) {
            return UserSerialize::unserialize($user);
        }, $users);
    }

}