<?php
namespace Topxia\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\FileToolkit;
use Topxia\Common\Paginator;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use Topxia\Common\SimpleValidator;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;
use Topxia\Common\ConvertIpToolkit;
use Topxia\WebBundle\DataDict\UserRoleDict;

class UserController extends BaseController 
{

    public function indexAction (Request $request)
    {
        $fields = $request->query->all();

        $conditions = array(
            'roles'=>'',
            'keywordType'=>'',
            'keyword'=>''
        );

        if(!empty($fields)){
            $conditions =$fields;
        }
        $paginator = new Paginator(
            $this->get('request'),
            $this->getUserService()->searchUserCount($conditions),
            20
        );

        $users = $this->getUserService()->searchUsers(
            $conditions,
            array('createdTime', 'DESC'),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $app = $this->getAppService()->findInstallApp("UserImporter");
        $enabled = $this->getSettingService()->get('plugin_userImporter_enabled');
        
        $showUserExport = false;
        if(!empty($app) && array_key_exists('version', $app) && $enabled){
            $showUserExport = version_compare($app['version'], "1.0.2", ">=");
        }

        return $this->render('TopxiaAdminBundle:User:index.html.twig', array(
            'users' => $users ,
            'paginator' => $paginator,
            'showUserExport' => $showUserExport
        ));
    }

    public function emailCheckAction(Request $request)
    {
        $email = $request->query->get('value');
        $email = str_replace('!', '.', $email);
        //list($result, $message) = $this->getAuthService()->checkEmail($email);
        $user=$this->getUserService()->getUserByEmail($email);
        $userId = $request->query->get('id');
        if (empty($user) || (!empty($userId) && $userId==$user['id'])) {
            $response = array('success' => true, 'message' => '该Email地址可以使用');
        } else {
            $response = array('success' => false, 'message' => 'email已存在!');
        }
        return $this->createJsonResponse($response);



        // $mobile = $request->query->get('value');
        // $userId = $request->query->get('id');
        // $user=$this->getUserService()->getUserByMobile($mobile);
        // if(empty($user) || (!empty($userId) && $userId==$user['id'])){
        //     $response = array('success' => true, 'message' => '该手机号可以使用');
        // }else {
        //     $response = array('success' => false, 'message' => '手机号已存在!');
        // }
        // return $this->createJsonResponse($response);
    }

    /**已废弃*/
    public function nicknameCheckAction(Request $request)
    {
        $nickname = $request->query->get('value');
        list($result, $message) = $this->getAuthService()->checkUsername($nickname);
        if ($result == 'success') {
            $response = array('success' => true, 'message' => '该昵称可以使用');
        } else {
            $response = array('success' => false, 'message' => $message);
        }
        return $this->createJsonResponse($response);
    }

    public function numberCheckAction(Request $request)
    {
        $number = $request->query->get('value');
        $userId = $request->query->get('id');
        
        $user=$this->getUserService()->getUserByNumber($number);
        if(empty($user) || (!empty($userId) && $userId==$user['id'])){
            $response = array('success' => true, 'message' => '该学号/工号可以使用');
        }else{
            $response = array('success' => false, 'message' => '学号/工号已存在!');
        }
        return $this->createJsonResponse($response);
    }

    public function mobileCheckAction(Request $request)
    {
        $mobile = $request->query->get('value');
        $userId = $request->query->get('id');
        $user=$this->getUserService()->getUserByMobile($mobile);
        if(empty($user) || (!empty($userId) && $userId==$user['id'])){
            $response = array('success' => true, 'message' => '该手机号可以使用');
        }else {
            $response = array('success' => false, 'message' => '手机号已存在!');
        }
        return $this->createJsonResponse($response);
    }

    public function createAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $formData = $request->request->all();
            $userData['number'] = $formData['number'];
            $userData['email'] = empty($formData['email'])?$formData['number'].'@example.com':$formData['email'];
            $userData['nickname'] = $formData['number'];
            $userData['truename'] = $formData['truename'];
            $userData['password'] = $formData['password'];
            $userData['createdIp'] = $request->getClientIp();
            $user = $this->getAuthService()->register($userData);
            $this->get('session')->set('registed_email', $user['email']);

            $roles[] = 'ROLE_USER';

            if($formData['type'] == 'student' && $formData['classId']){
                $this->getClassesService()->importStudents($formData['classId'], array($user['id']));
            } else if($formData['type']=='teacher'){
                array_push($roles, 'ROLE_TEACHER');
            } else if($formData['type']=='admin'){
                $roles=array_merge($roles, array('ROLE_TEACHER','ROLE_SUPER_ADMIN'));
            }
            $this->getUserService()->changeUserRoles($user['id'], $roles);
            $this->getLogService()->info('user', 'add', "管理员添加新用户 {$user['nickname']} ({$user['id']})");

            return $this->redirect($this->generateUrl('admin_user'));
        }

        $type = $request->query->get('type');

        $grades = array();
        $classes = array();

        if($type == 'student'){
            $grades = $this->getGradeService()->findAllGrades();

            $grades = ArrayToolkit::index($grades, "id");

            $classes = $this->getClassesService()->searchClasses(
                array(),
                array(),
                0,
                PHP_INT_MAX
            );
        }

        return $this->render('TopxiaAdminBundle:User:create-modal.html.twig',array(
            'type'=>$type,
            'grades'=>$grades,
            'classes'=>$classes
        ));
    }

    public function editAction(Request $request, $id)
    {
        $user = $this->getUserService()->getUser($id);
        $profile = $this->getUserService()->getUserProfile($user['id']);
        $profile['title'] = $user['title'];

        $currentClass = $this->getClassesService()->findClassByUserNumber($user['number']);

        if ($request->getMethod() == 'POST') {
            $fields=$request->request->all();
            $profile = $this->getUserService()->updateUserProfile($user['id'], $fields);
            $this->getUserService()->changeTrueName($user['id'],$fields['truename']);
            $this->getUserService()->changeMobile($user['id'],$fields['mobile']);
            $this->getUserService()->changeEmail($user['id'],$fields['email']);
            $this->getUserService()->changeNumber($user['id'],$fields['number']);

            // If the updated user is a student and his/her class is changed
            if(count($user['roles']) == 1 && in_array('ROLE_USER', $user['roles'] )){
                if($currentClass['id'] != $fields['classId']){
                    $this->getClassesService()->deleteClassMemberByUserId($user['id']);

                    if(!empty($fields['classId'])){
                        $this->getClassesService()->importStudents($fields['classId'], array($user['id']));
                    }
                }
            }

            $this->getLogService()->info('user', 'edit', "管理员编辑用户资料 {$user['nickname']} (#{$user['id']})", $profile);
            
            return $this->redirect($this->generateUrl('settings'));
        }

        $fields=$this->getFields();

        $grades = array();
        $classes = array();

        if(count($user['roles']) == 1 && in_array('ROLE_USER', $user['roles'] )){
            $grades = $this->getGradeService()->findAllGrades();

            $grades = ArrayToolkit::index($grades, "id");

            $classes = $this->getClassesService()->searchClasses(
                array(),
                array(),
                0,
                PHP_INT_MAX
            );
        }

        return $this->render('TopxiaAdminBundle:User:edit-modal.html.twig', array(
            'user' => $user,
            'profile'=>$profile,
            'fields'=>$fields,
            'grades'=>$grades,
            'classes'=>$classes,
            'currentClassId'=>$currentClass ? $currentClass['id'] : ''
        ));
    }

    public function showAction(Request $request, $id)
    {
        $user = $this->getUserService()->getUser($id);
        $profile = $this->getUserService()->getUserProfile($id);
        $profile['title'] = $user['title'];
        $relations=$this->getUserService()->findUserRelationsByFromIdAndType($id,'family');
        $children=$this->getUserService()->findUsersByIds(ArrayToolkit::column($relations, 'toId'));
        $fields=$this->getFields();
        return $this->render('TopxiaAdminBundle:User:show-modal.html.twig', array(
            'user' => $user,
            'profile' => $profile,
            'fields'=>$fields,
            'relation'=>empty($relations)?'--':$relations[0]['relation'],
            'children'=>$children
        ));
    }

    public function rolesAction(Request $request, $id)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_SUPER_ADMIN')
            and false === $this->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUserService()->getUser($id);
        $currentUser = $this->getCurrentUser();
        if ($request->getMethod() == 'POST') {
            $roles = $request->request->get('roles');

            $this->getUserService()->changeUserRoles($user['id'], $roles);

            $dataDict = new UserRoleDict();
            $roleDict = $dataDict->getDict();
            $role = "";
            $roleCount = count($roles);
            $deletedRoles = array_diff($user['roles'], $roles);
            $addedRoles = array_diff($roles, $user['roles']);
            if(!empty($deletedRoles) || !empty($addedRoles) ){
                for ($i=0; $i<$roleCount; $i++) {
                    $role .= $roleDict[$roles[$i]];
                    if ($i<$roleCount - 1){
                        $role .= "、";
                    }
                }
                $this->getNotifiactionService()->notify($user['id'],'default',"您被“{$currentUser['nickname']}”设置为“{$role}”身份。");
            }

            if (in_array('ROLE_TEACHER', $user['roles']) && !in_array('ROLE_TEACHER', $roles)) {
                $this->getCourseService()->cancelTeacherInAllCourses($user['id']);
            }

            $user = $this->getUserService()->getUser($id);

            return $this->render('TopxiaAdminBundle:User:user-table-tr.html.twig', array(
            'user' => $user,
            ));
        }
           
        return $this->render('TopxiaAdminBundle:User:roles-modal.html.twig', array(
            'user' => $user,
        ));
    }

    public function avatarAction(Request $request, $id)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUserService()->getUser($id);

        $form = $this->createFormBuilder()
            ->add('avatar', 'file')
            ->getForm();
        if ($request->getMethod() == 'POST') {
            $form->bind($request);
            if ($form->isValid()) {
                $data = $form->getData();
                $file = $data['avatar'];

                if (!FileToolkit::isImageFile($file)) {
                    return $this->createMessageResponse('error', '上传图片格式错误，请上传jpg, gif, png格式的文件。');
                }

                $filenamePrefix = "user_{$user['id']}_";
                $hash = substr(md5($filenamePrefix . time()), -8);
                $ext = $file->getClientOriginalExtension();
                $filename = $filenamePrefix . $hash . '.' . $ext;

                $directory = $this->container->getParameter('topxia.upload.public_directory') . '/tmp';
                $file = $file->move($directory, $filename);

                $fileName = str_replace('.', '!', $file->getFilename());

                $avatarData = $this->avatar_2($id, $fileName);
                return $this->render('TopxiaAdminBundle:User:user-avatar-crop-modal.html.twig', array(
                    'user' => $user,
                    'filename' => $fileName,
                    'pictureUrl' => $avatarData['pictureUrl'],
                    'naturalSize' => $avatarData['naturalSize'],
                    'scaledSize' => $avatarData['scaledSize']
                ));
            }
        }

        $hasPartnerAuth = $this->getAuthService()->hasPartnerAuth();
        if ($hasPartnerAuth) {
            $partnerAvatar = $this->getAuthService()->getPartnerAvatar($user['id'], 'big');
        } else {
            $partnerAvatar = null;
        }

        return $this->render('TopxiaAdminBundle:User:user-avatar-modal.html.twig', array(
            'form' => $form->createView(),
            'user' => $this->getUserService()->getUser($user['id']),
            'partnerAvatar' => $partnerAvatar,
        ));
    }

    private function getFields()
    {
        $fields=$this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();
        for($i=0;$i<count($fields);$i++){
            if(strstr($fields[$i]['fieldName'], "textField")) $fields[$i]['type']="text";
            if(strstr($fields[$i]['fieldName'], "varcharField")) $fields[$i]['type']="varchar";
            if(strstr($fields[$i]['fieldName'], "intField")) $fields[$i]['type']="int";
            if(strstr($fields[$i]['fieldName'], "floatField")) $fields[$i]['type']="float";
            if(strstr($fields[$i]['fieldName'], "dateField")) $fields[$i]['type']="date";
        }

        return $fields;
    }

    private function avatar_2 ($id, $filename)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $currentUser = $this->getCurrentUser();

        $filename = str_replace('!', '.', $filename);
        $filename = str_replace(array('..' , '/', '\\'), '', $filename);
        $pictureFilePath = $this->container->getParameter('topxia.upload.public_directory') . '/tmp/' . $filename;

        try {
            $imagine = new Imagine();
            $image = $imagine->open($pictureFilePath);
        } catch (\Exception $e) {
            @unlink($pictureFilePath);
            return $this->createMessageResponse('error', '该文件为非图片格式文件，请重新上传。');
        }

        $naturalSize = $image->getSize();
        $scaledSize = $naturalSize->widen(270)->heighten(270);
        $pictureUrl = 'tmp/' . $filename;

        return array(
            'naturalSize' => $naturalSize,
            'scaledSize' => $scaledSize,
            'pictureUrl' => $pictureUrl
        );
    }

    public function avatarCropAction(Request $request, $id)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if($request->getMethod() == 'POST') {
            $options = $request->request->all();
            $filename = $request->query->get('filename');
            $filename = str_replace('!', '.', $filename);
            $filename = str_replace(array('..' , '/', '\\'), '', $filename);
            $pictureFilePath = $this->container->getParameter('topxia.upload.public_directory') . '/tmp/' . $filename;
            $this->getUserService()->changeAvatar($id, realpath($pictureFilePath), $options);

            // return $this->redirect($this->generateUrl('admin_user'));
            return $this->createJsonResponse(true);
        }

        
    }

    public function lockAction($id)
    {
        $this->getUserService()->lockUser($id);
        return $this->renderPage($id);
    }

    public function unlockAction($id)
    {
        $this->getUserService()->unlockUser($id);
        return $this->renderPage($id);
    }

    public function selectAction(Request $request)
    {
        $truename = $request->query->get('q');
        $limit = $request->query->get('page_limit');
        $conditions = array(
            'truename'=> $truename,
        );
        $users = $this->getUserService()->searchUsers(
            $conditions,
            array('truename','ASC'),
            0,
            $limit);

        $response = array();
        foreach ($users as $key => $user) {
            $temp = array();
            $temp['id'] = $user['id'];
            $temp['name'] = $user['truename'];
            $response[] = $temp;
        }
        return new Response(json_encode($response)); 
    }

    private function renderPage($id){
        $user=$this->getUserService()->getUser($id);
        if(in_array('ROLE_PARENT', $user['roles'])){
            $relations=$this->getUserService()->findUserRelationsByFromIdAndType($id,'family');
            $children=$this->getUserService()->findUsersByIds(ArrayToolkit::column($relations, 'toId'));
            $classMembers=$this->getClassesService()->findClassMembersByUserIds(ArrayToolkit::column($relations, 'toId'));
            $classes=$this->getClassesService()->findClassesByIds(ArrayToolkit::column($classMembers, 'classId'));
            $classMembers=ArrayToolkit::index($classMembers, 'userId');
            $classes=ArrayToolkit::index($classes, 'id');
            return $this->render('TopxiaAdminBundle:Parent:parent-table-tr.html.twig', array(
                'user' => $user,
                'relations'=>$relations,
                'children'=>$children,
                'classMembers' =>$classMembers,
                'classes' =>$classes,
            ));
        }else if(in_array('ROLE_TEACHER', $user['roles'])){
            return $this->render('TopxiaAdminBundle:Teacher:teacher-table-tr.html.twig', array(
                'user' => $user,
            ));
        }else{
            $class=$this->getClassesService()->findClassByUserNumber($user['number']);
            return $this->render('TopxiaAdminBundle:Student:student-table-tr.html.twig', array(
                'user' => $user,
                'class'=>$class
            ));
                
        }
    }

    public function sendPasswordResetEmailAction(Request $request, $id)
    {
        $user = $this->getUserService()->getUser($id);
        if (empty($user)) {
            throw $this->createNotFoundException();
        }

        $token = $this->getUserService()->makeToken('password-reset', $user['id'], strtotime('+1 day'));

        try {
            $this->sendEmail(
                $user['email'],
                "重设{$user['nickname']}在{$this->setting('site.name', 'EDUSOHO')}的密码",
                $this->renderView('TopxiaWebBundle:PasswordReset:reset.txt.twig', array(
                    'user' => $user,
                    'token' => $token,
                )), 'html'
            );
            $this->getLogService()->info('user', 'send_password_reset', "管理员给用户 ${user['nickname']}({$user['id']}) 发送密码重置邮件");
        } catch(\Exception $e) {
            $this->getLogService()->error('user', 'send_password_reset', "管理员给用户 ${user['nickname']}({$user['id']}) 发送密码重置邮件失败：". $e->getMessage());
            throw $e;
        }


        return $this->createJsonResponse(true);
    }

    public function sendEmailVerifyEmailAction(Request $request, $id)
    {
        $user = $this->getUserService()->getUser($id);
        if (empty($user)) {
            throw $this->createNotFoundException();
        }

        $token = $this->getUserService()->makeToken('email-verify', $user['id'], strtotime('+1 day'));
        $auth = $this->getSettingService()->get('auth', array());
        try {
            $this->sendEmail(
                $user['email'],
                "请激活你的帐号，完成注册",
                $this->renderView('TopxiaWebBundle:Register:email-verify.txt.twig', array(
                    'user' => $user,
                    'token' => $token,
                ))
            );
            $this->getLogService()->info('user', 'send_email_verify', "管理员给用户 ${user['nickname']}({$user['id']}) 发送Email验证邮件");
        } catch(\Exception $e) {
            $this->getLogService()->error('user', 'send_email_verify', "管理员给用户 ${user['nickname']}({$user['id']}) 发送Email验证邮件失败：". $e->getMessage());
            throw $e;
        }

        return $this->createJsonResponse(true);
    }

    public function changePasswordAction(Request $request, $userId)
    {
        $currentUser = $this->getCurrentUser();
        $user = $this->getUserService()->getUser($userId);
        if(!in_array('ROLE_SUPER_ADMIN', $currentUser['roles'])){
            throw $this->createAccessDeniedException();
        }
        
        if ($request->getMethod() == 'POST') {
            $formData = $request->request->all();
            $this->getAuthService()->changePassword($user['id'], null, $formData['newPassword']);
            return $this->createJsonResponse(true);
        }
        
        return $this->render('TopxiaAdminBundle:User:change-password-modal.html.twig', array(
            'user' => $user
        ));

    }

    protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

    protected function getLogService()
    {
        return $this->getServiceKernel()->createService('System.LogService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getAuthService()
    {
        return $this->getServiceKernel()->createService('User.AuthService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    protected function getClassesService()
    {
        return $this->getServiceKernel()->createService('Classes.ClassesService');
    }

    protected function getGradeService()
    {
        return $this->getServiceKernel()->createService('Grade.GradeService');
    }

    protected function getNotifiactionService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }
}