<?php

namespace Topxia\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\FileToolkit;
use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Topxia\WebBundle\Twig\Extension\DataDict;
use Topxia\WebBundle\Util\ChineseTool;

class SchoolController extends BaseController
{
    public function schoolSettingAction(Request $request) 
    {
        // Save submitted settings
        if ($request->getMethod() == 'POST') {
            $school = $request->request->all();

            $this->getSettingService()->set('school', $school);

            // Delete all existing grades
            $this->getGradeService()->deleteGrades("primary");
            $this->getGradeService()->deleteGrades("middle");
            $this->getGradeService()->deleteGrades("high");

            // Insert new grades
            $primaryGrades = $this->processGradeSettings("primary", $school);
            $middleGrades = $this->processGradeSettings("middle", $school);
            $highGrades = $this->processGradeSettings("high", $school);

            $allGrades = array_merge($primaryGrades, $middleGrades, $highGrades);

            $this->saveGrades($allGrades);

            $this->getLogService()->info('school', 'update_settings', "更新学校设置", $school);
            $this->setFlashMessage('success', '学校信息设置已保存！');
        }

        $school = $this->getSettingService()->get('school', array());

        // Get existing grade settings
        if(isset($school['primarySchool'])){
            $primaryGrades = $this->getGradeService()->findGradesByGroup('primary');
        }
        
        if(isset($school['middleSchool'])){
            $middleGrades = $this->getGradeService()->findGradesByGroup('middle');
        }

        if(isset($school['highSchool'])){
            $highGrades = $this->getGradeService()->findGradesByGroup('high');
        }

        // Generate default grades if no existing grades
        if(empty($primaryGrades)){
            $primaryGrades = $this->generateDefaultGrades('primary');
        }

        if(empty($middleGrades)){
            $middleGrades = $this->generateDefaultGrades('middle');
        }

        if(empty($highGrades)){
            $highGrades = $this->generateDefaultGrades('high');
        }

        $default = array(
            'primarySchool' => 0,
            'primaryYear' => 6,
            'middleSchool' => 0,
            'middleYear' => 3,
            'highSchool' => 0,
            'highYear' => 3,
            'homepagePicture' => '',
        );

        $school = array_merge($default, $school);

        return $this->render('TopxiaAdminBundle:School:school-setting.html.twig', array(
            'school' => $school,
            'primaryGrades' => !empty($primaryGrades) ? $primaryGrades: array(),
            'middleGrades' => !empty($middleGrades) ? $middleGrades : array(),
            'highGrades' => !empty($highGrades) ? $highGrades: array()
        ));
    }

    private function processGradeSettings($gradeGroup, $schoolSettings){
        $grades = array();

        if(!empty($schoolSettings[$gradeGroup . 'School'])){

            if(empty($schoolSettings[$gradeGroup . 'Year']) or $schoolSettings[$gradeGroup . 'Year'] == 'default'){
                $grades = $this->generateDefaultGrades($gradeGroup);
            }elseif (!empty($schoolSettings[$gradeGroup . "-grades"])){
                $grades = $this->parseGradeSettings($gradeGroup, $schoolSettings[$gradeGroup . "-grades"]);
            }
        }

        return $grades;
    }

    private function saveGrades($grades){
        $seq = 1;
        $newGrades = array();
        $updateGrades = array();

        foreach ($grades as $grade) {
            $grade['seq'] = $seq ++;

            if(empty($grade['id'])){
                array_push($newGrades, $grade);
            }else{
                array_push($updateGrades, $grade);
            }
        }

        // We must save the updated grades at first to avoid grade id overriding.
        if(!empty($updateGrades)){
            $this->saveGradeList($updateGrades);
        }

        if(!empty($newGrades)){
            $this->saveGradeList($newGrades);
        }
    }

    private function saveGradeList($grades){
        foreach ($grades as $grade) {
            $this->getGradeService()->saveGrade($grade);
        }
    }

    private function parseGradeSettings($gradeGroup, $gradeSettings){
        $grades = array();

        if(!empty($gradeSettings)){
            foreach($gradeSettings as $gradeSetting){
                $grade = $this->parseGradeSetting($gradeGroup, $gradeSetting);

                array_push($grades, $grade);
            }
        }

        return $grades;
    }

    private function parseGradeSetting($gradeGroup, $gradeSetting){
        $grade = array();

        $nameAndId = explode("|", $gradeSetting);

        $grade['id'] = $nameAndId[1] ?: '';
        $grade['name'] = $nameAndId[0];
        $grade['seq'] = 1;
        $grade['gradeGroup'] = $gradeGroup;

        return $grade;
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

    public function classSettingAction(Request $request) 
    {
        $conditions = $request->query->all();
            
        $paginator = new Paginator(
            $this->get('request'),
            $this->getClassesService()->searchClassCount($conditions),
            9);

        $classes = $this->getClassesService()->searchClasses(
            $conditions,
            array(),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $userIds = ArrayToolkit::column($classes, 'headTeacherId');
        $users = $this->getUserService()->findUsersByIds($userIds);
        $users = ArrayToolkit::index($users, 'id');
        foreach ($classes as $key => $class) {
            $headTeacher = $this->getUserService()->getUser($class['headTeacherId']);
            $class['headTeacherName'] = $users[$class['headTeacherId']]['truename'];
            $classes[$key] = $class;
        }  
        
        return $this->render('TopxiaAdminBundle:School:class-setting.html.twig',array(
            'classes' => $classes,
            'paginator' => $paginator,
        ));
    }

    public function pointSettingAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $point = $request->request->all();
            $this->getSettingService()->set('point', $point);
            $this->getLogService()->info('point', 'update_settings', "更新学习积分设置", $point);
            $this->setFlashMessage('success', '学习积分设置已保存！');
        }

        $point = $this->getSettingService()->get('point', array());
        $default = array(
            'name' => '学分',
            'show'=>1,
            'accomplishLesson' => 2,
            'shareNote' => 3,
            'noteByLiked' => 2,
            'accomplishTest' => 3,
            'accomplishHomework' => 3,
            'accomplishPractice' => 3,
            'accomplishSign' => 1,
        );

        $point = array_merge($default, $point);
      
        return $this->render('TopxiaAdminBundle:School:point-setting.html.twig', array(
            'point' => $point
        ));
    }

    public function knowledgeSettingAction(Request $request)
    {
        return $this->redirect($this->generateUrl('admin_knowledge'));
    }

    public function homepageSettingAction(Request $request){
        // Save submitted settings
        if ($request->getMethod() == 'POST') {
            $settings = $request->request->all();

            $default = array(
                "heroSlidesEnabled"=>"0",
                "studentFacesWallEnabled"=>"0",
                "recommendTeachersEnabled"=>"0",
                "latestArticleEnabled"=>"0",
                "hotGroupsEnabled"=>"0"
                );

            $settings = array_merge($default, $settings);

            $this->getSettingService()->set('homepage', $settings);

            $this->getLogService()->info('homepage', 'update_settings', "更新学校主页设置", $settings);
            $this->setFlashMessage('success', '学校主页设置已保存！');
        }

        $settings = $this->getSettingService()->get('homepage', array());

        $default = array(
            'studentNum' => 20,
            'teacherNum' => 4,
            'articleNum' => 6,
            'groupNum' => 4
        );

        $settings = array_merge($default, $settings);

        return $this->render('TopxiaAdminBundle:School:homepage-setting.html.twig', array('settings' => $settings));
    }

    public function eduMaterialSettingAction(Request $request,$schoolType)
    {
        $school = $this->getSettingService()->get('school', array());
        if(array_key_exists('primarySchool', $school) && ($schoolType=='primarySchool' || $schoolType=='all')){
             // $grades=DataDict::dict('primarySchool');
            $grades = $this->getGradeService()->findGradesByGroup("primary");

            $subjects=$this->getCategoryService()->findSubjectCategoriesByGradeId($grades[0]['id']);
            $schoolType='primarySchool';
        }

        if(array_key_exists('middleSchool', $school) && ($schoolType=='middleSchool' || $schoolType=='all')){
            $grades = $this->getGradeService()->findGradesByGroup("middle");
            $subjects=$this->getCategoryService()->findSubjectCategoriesByGradeId($grades[0]['id']);
            $schoolType='middleSchool';
        }

        if(array_key_exists('highSchool', $school) && ($schoolType=='highSchool' || $schoolType=='all')){
            $grades = $this->getGradeService()->findGradesByGroup("high");
            $subjects=$this->getCategoryService()->findSubjectCategoriesByGradeId($grades[0]['id']);
            $schoolType='highSchool';
        }
        if($schoolType=='all'){
            return $this->render('TopxiaAdminBundle:School:eduMaterial-setting.html.twig', array(
                'school'=>$school,
                'schoolType'=>$schoolType,
            ));
        }
        $materials=$this->getCategoryService()->findCategoriesByParentIds(ArrayToolkit::column($subjects,'id'));
        $materials=ArrayToolkit::group($materials,'parentId');
        $eduMaterials=ArrayToolkit::group($this->getEduMaterialService()->findAllEduMaterials(),'subjectId');
        foreach ($eduMaterials as $key => $eduMaterialList) {
            $eduMaterials[$key]=ArrayToolkit::index($eduMaterialList,'gradeId');
        }

        return $this->render('TopxiaAdminBundle:School:eduMaterial-setting.html.twig', array(
            'school'=>$school,
            'schoolType'=>$schoolType,
            'grades'=>$grades,
            'subjects'=>$subjects,
            'materials'=>$materials,
            'eduMaterials'=>$eduMaterials
        ));
    }

    public function subjectAddAction(Request $request)
    {
        $schoolType = $request->query->get('schoolType');
        if($request->getMethod() == 'POST'){
            $fields = $request->request->all();
            if($schoolType=='primarySchool'){
                $beforeCode='es_xx';
                $gradeIds=array(1,2,3,4,5,6);
            }
            if($schoolType=='middleSchool'){
                $beforeCode='es_cz';
                $gradeIds=array(7,8,9);
            }
            if($schoolType=='highSchool'){
                $beforeCode='es_gz';
                $gradeIds=array(10,11,12);
            }
            $school=$this->getCategoryService()->getCategoryByCode($beforeCode);
            $group=$this->getCategoryService()->getGroupByCode('subject_material');

            $subject['name']=$fields['name'];
            $subject['code']=$beforeCode.'_'.time();
            $subject['weight']=1;
            $subject['groupId']=$group['id'];
            $subject['parentId']=$school['id'];
            $subject=$this->getCategoryService()->createCategory($subject);
            foreach ($gradeIds as $gradeId) {
                $eduMaterial['gradeId']=$gradeId;
                $eduMaterial['subjectId']=$subject['id'];
                $eduMaterial['materialId']=0;
                $eduMaterial['materialName']='';
                $this->getEduMaterialService()->addEduMaterial($eduMaterial);
            }
            return $this->createJsonResponse(true);
        }
        return $this->render('TopxiaAdminBundle:School:subject-create-modal.html.twig',array(
            'schoolType'=>$schoolType
        ));
    }

    public function subjectUpdateAction(Request $request,$subjectId)
    {
        $schoolType = $request->query->get('schoolType');
        $subject=$this->getCategoryService()->getCategory($subjectId);
        if($request->getMethod() == 'POST'){
            $fields = $request->request->all();
            $subject=$this->getCategoryService()->updateCategory($subjectId,$fields);
            return $this->createJsonResponse(empty($subject)?false:true);
        }
        return $this->render('TopxiaAdminBundle:School:subject-create-modal.html.twig',array(
            'schoolType'=>$schoolType,
            'subject'=>$subject
        ));
    }

    public function eduMaterialUpdateAction(Request $request)
    {
        $fields = $request->request->all();
        $eduMaterial=$this->getEduMaterialService()->updateEduMaterial($fields['eduMaterialId'],$fields);
        $result=empty($eduMaterial)?false:true;
        return $this->createJsonResponse($result);
    }

    public function classEditorAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $fields = $request->request->all();
            $classId = $fields['classId'];
            unset($fields['classId']);
            if($classId) {
                $class = $this->getClassesService()->updateClass($classId, $fields);
                return new Response('success');
            } else {
                $class = $this->getClassesService()->createClass($fields);
                return new Response('sucess');
            }
        }

        $type = $request->query->get('type');
        $search = $request->query->get('search');
        if($type == 'create') {
                return $this->render('TopxiaAdminBundle:School:class-editor.html.twig',array('search' => $search));
        } else {
            $classId = $request->query->get('classId');
            $class = $this->getClassesService()->getClass($classId);
            $headTheacher = $this->getUserService()->getUser($class['headTeacherId']);
            $class['headTeacherName'] = $headTheacher['truename'];
            return $this->render('TopxiaAdminBundle:School:class-editor.html.twig',array(
                'class' => $class,
                'search' => $search,
            ));
        }

        
    }

    public function classDeleteAction(Request $request, $classId)
    {
        $this->getClassesService()->deleteClass($classId);
        return $this->redirect($this->generateUrl('admin_school_classes_setting'));
    }

    public function classCourseManageAction(Request $request, $classId)
    {
        $conditions =array(
            'classId' => $classId,
            'status' => 'published'
        );

        $class = $this->getClassesService()->getClass($classId);
        
        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'latest',
            0,
            1000
        );
        foreach ($courses as $key => $course) {
            foreach ($course['teacherIds'] as $key2 => $id) {

                $headTeacher = $this->getUserService()->getUser($id);
                $course['teachername'][$key2] = $headTeacher['truename'];

            }
            $courses[$key] = $course;
        }

        return $this->render('TopxiaAdminBundle:School:class-course-manage.html.twig',array(
            'courses' => $courses,
            'class' => $class,
        ));
    }

    public function classCourseAddAction(Request $request, $classId)
    {
        if($request->getMethod() == 'POST') {
            $fields = $request->request->all();
            $hasCourse = $this->getCourseService()->classHasCourse($classId, $fields['parentId']);
            if ($hasCourse) {
                throw $this->createNotFoundException("已经添加该课程");
            } else {
                $this->getCourseService()->copyCourseForClass(
                    $fields['parentId'],
                    $classId,
                    $fields['compulsory'],
                    $fields['teacherId']);
            }

            return new Response(json_encode("success"));
        }

        $params = $request->query->all();
        $params['classId'] = $classId;
        $classCourses = $this->getCourseService()->findCoursesByClassId($classId);
        if (empty($classCourses)) {
            $classCourses=array();
        }
        $excludeIds = ArrayToolkit::column($classCourses, 'parentId');
        $conditions = array(
            'status' => 'published',
            'parentId' => 0,
            'excludeIds' => $excludeIds
        );
        if($params['public'] == '1') {
            $conditions['gradeId'] = 0;
        }else{
            $conditions['gradeId'] = $params['gradeId'];
        }

        if(!empty($params['gradeId'])){
            $grade = $this->getGradeService()->getGrade($params['gradeId']);
        }

        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->searchCourseCount($conditions),
            5);

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'latest',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $userIds = ArrayToolkit::column($courses, 'userId');
        $users = $this->getUserService()->findUsersByIds($userIds);
        $users = ArrayToolkit::index($users, 'id');
        foreach ($courses as $key => $course) {
            $creator = $users[$course['userId']];
            $course['creatorName'] = $creator['truename'];
            $courses[$key] = $course;
        }

        return $this->render('TopxiaAdminBundle:School:class-course-add-modal.html.twig',array(
            'class' => $params,
            'grade' => $grade,
            'courses' => $courses,
            'paginator' => $paginator,            
        ));
    }

    public function classCourseRemoveAction(Request $request,$classId,$courseId)
    {

        $this->getCourseService()->closeCourse($courseId);
        return $this->redirect($this->generateUrl('admin_school_class_course_manage',array('classId'=>$classId)));
    }

    public function homePageUploadAction(Request $request)
    {
        $school = $this->getSettingService()->get('school', array());
        $newFileName='school-homepage'.time();
        $fileLocation = $this->savePicture($request, 'homepagePicture', 'school', $newFileName);
        
        $school['homepagePicture'] = $fileLocation['path'];

        $this->getSettingService()->set('school', $school);
        $this->getLogService()->info('school', 'update_settings', "更新学校首页图片", array('homepagePicture' => $school['homepagePicture']));

        $response = array(
            'path' => $fileLocation['path'],
            'url' =>  $this->container->get('templating.helper.assets')->getUrl($fileLocation['url']),
        );

        return new Response(json_encode($response));
    }

    public function homePageRemoveAction(Request $request)
    {
        $setting = $this->getSettingService()->get("school");
        $setting['homepagePicture'] = '';

        $this->getSettingService()->set('school', $setting);

        $this->getLogService()->info('school', 'update_settings', "移除移除学校首页图片");

        return $this->createJsonResponse(true);
    }

    public function classIconUploadAction(Request $request)
    {
        $fileLocation = $this->savePicture($request, 'icon', 'school/class/icon', '');

        $response = array(
            'path' => $fileLocation['path'],
            'url' =>  $this->container->get('templating.helper.assets')->getUrl($fileLocation['url']),
        );

        return new Response(json_encode($response));
    }

    public function classBackgroundImgUploadAction(Request $request)
    {
        $fileLocation = $this->savePicture($request, 'backgroundImg', 'school/class/backgroundImg', '');
        
        $response = array(
            'path' => $fileLocation['path'],
            'url' =>  $this->container->get('templating.helper.assets')->getUrl($fileLocation['url']),
        );

        return new Response(json_encode($response));
    }
    
    public function teacherNameAction(Request $request)
    {
        $teachername = $request->query->get('q');
        $conditions = array(
            'roles' => 'ROLE_TEACHER',
            'truename'=> $teachername 
            );
        $total = $this->getUserService()->searchUserCount($conditions);
        $teachers = $this->getUserService()->searchUsers(
            $conditions,
            array('id','ASC'),
            0,
            $total);

        $response = array();
        foreach ($teachers as $key => $teacher) {
            $temp = array();
            $temp['id'] = $teacher['id'];
            $temp['name'] = $teacher['truename'];
            $response[] = $temp;
        }
        return new Response(json_encode($response)); 
    }

    public function studentListAction (Request $request,$classId)
    {   
        $class=$this->getClassesService()->getClass($classId);
        $conditions = array(
            'classId'=>$classId,
            'roles'=>array('STUDENT')
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getClassesService()->searchClassMemberCount($conditions),
            20
        );
        $classMembers = $this->getClassesService()->searchClassMembers(
            $conditions,
            array('createdTime', 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );
        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($classMembers, 'userId'));
        return $this->render('TopxiaAdminBundle:School:student-list.html.twig', array(
            'users' => $users ,
            'class'=>$class,
            'paginator' => $paginator
        ));
    }

    public function studentImportAction(Request $request, $classId)
    {
        if ($request->getMethod() == 'POST') {
            $formData = $request->request->all();
            $formData['numbers'] = str_replace('，', ',', $formData['numbers']); 
            $formData['numbers'] = str_replace("\n", ',', $formData['numbers']); 
            $formData['numbers'] = str_replace("\r", ',', $formData['numbers']); 
            $formData['numbers'] = str_replace(' ', ',', $formData['numbers']); 
            $numbers = explode(',', $formData['numbers']);
            $userIds=array();
            foreach ($numbers as $number) {
                $number=trim($number);
                if($number==''){
                    continue;
                }
                $user=$this->getUserService()->getUserByNumber($number);
                if(empty($user) || in_array('ROLE_TEACHER', $user['roles'])){
                    return $this->createJsonResponse('学号'.$number.'的学生，账号还未建立！');
                }
                $studentMember=$this->getClassesService()->getStudentMemberByUserIdAndClassId($user['id'],null);
                
                if(!empty($studentMember) && $studentMember['classId']!=$classId){
                    $existClass = $this->getClassesService()->getClass($studentMember['classId']);
                    $gradeName = DataDict::text('gradeName', $existClass['gradeId']);
                    return $this->createJsonResponse($user['truename'].'('.'学号'.$number.')'.'已被分配到'.$gradeName.$existClass['name'].'，请先到该班级中把他移除！');
                }
                if(!empty($studentMember) && $studentMember['classId']==$classId){
                    continue;
                }

                $userIds[]=$user['id'];
            }
            $this->getClassesService()->importStudents($classId,$userIds);
            $relations=$this->getUserService()->findUserRelationsByToIdsAndType($userIds,'family');
            $parentIds=ArrayToolkit::column($relations,'fromId');
            $this->getClassesService()->importParents($classId,$parentIds);
            
            return $this->createJsonResponse(true);
        }
        return $this->render('TopxiaAdminBundle:School:student-import-modal.html.twig', array(
            'classId' => $classId
        ));
    }
    
    public function studentRemoveAction(Request $request, $userId ,$classId)
    {
        $this->getClassesService()->deleteClassMemberByUserId($userId);
        $this->getClassesService()->updateClassStudentNum(-1,$classId);
        $relations=$this->getUserService()->findUserRelationsByToIdAndType($userId,'family');
        foreach ($relations as $relation) {
            $relationPs=$this->getUserService()->findUserRelationsByFromIdAndType($relation['fromId'],'family');
            $classMembers=$this->getClassesService()->findStudentMembersByUserIdsAndClassId(ArrayToolkit::column($relationPs,'toId'),$classId);
            if(empty($classMembers)){
                $this->getClassesService()->deleteClassMemberByUserId($relation['fromId']);
            }
        }
        return $this->createJsonResponse(true);
    }

    private function savePicture(Request $request, $uploadFileName, $folder, $newFileName = '')
    {
        $result = array();
        $file = $request->files->get($uploadFileName);
        if (!FileToolkit::isImageFile($file)) {
            throw $this->createAccessDeniedException('图片格式不正确！');
        }
        if(!$newFileName) {
            $newFileName = time() . '.' . $file->getClientOriginalExtension();
        } else {
            $newFileName = $newFileName . '.' . $file->getClientOriginalExtension();
        }
            
        $directory = "{$this->container->getParameter('topxia.upload.public_directory')}/" . $folder;
        $file = $file->move($directory, $newFileName);

        $result['path'] = $folder . "/{$newFileName}";
        $result['url'] = $this->container->getParameter('topxia.upload.public_url_path') .  '/' . $result['path'];
        return $result;
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getClassesService()
    {
        return $this->getServiceKernel()->createService('Classes.ClassesService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }
    protected function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.CategoryService');
    }

    protected function getEduMaterialService()
    {
        return $this->getServiceKernel()->createService('Course.EduMaterialService');
    }

    protected function getGradeService()
    {
        return $this->getServiceKernel()->createService('Grade.GradeService');
    }
}