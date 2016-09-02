<?php

use Composer\Autoload\ClassLoader;

require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../app/AppKernel.php';

$loader = new Twig_Loader_Filesystem(__DIR__ . '/templates');
$twig = new Twig_Environment($loader, array(
    'cache' => false,
));

$twig->addGlobal('edusho_version', \Topxia\System::VERSION);

$step =intval(empty($_GET['step']) ? 0 : $_GET['step']);

$functionName = 'install_step' . $step;

$functionName();

use Topxia\Service\Common\ServiceKernel;
use Topxia\Service\User\CurrentUser;
use Topxia\Service\CloudPlatform\KeyApplier;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\ParameterBag;
use Topxia\WebBundle\Command\PluginRegisterCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Topxia\WebBundle\Twig\Extension\DataDict;

function check_installed()
{
	if (file_exists(__DIR__ . '/../../app/data/install.lock')) {
		exit('already install.');
	}
}

function install_step0()
{
	check_installed();
	global $twig;
	echo $twig->render('step-0.html.twig', array('step' => 0));
}

function install_step1()
{
	check_installed();
	global $twig;
	$pass = true;

	$env = array();
	$env['os'] = PHP_OS;
	$env['phpVersion'] = PHP_VERSION;
	$env['phpVersionOk'] = version_compare(PHP_VERSION, '5.3.0') >= 0;
	$env['pdoMysqlOk'] = extension_loaded('pdo_mysql');
	$env['uploadMaxFilesize'] = ini_get('upload_max_filesize');
	$env['uploadMaxFilesizeOk'] = intval($env['uploadMaxFilesize']) >= 2;
	$env['postMaxsize'] = ini_get('post_max_size');
	$env['postMaxsizeOk'] = intval($env['postMaxsize']) >= 8;
	$env['maxExecutionTime'] = ini_get('max_execution_time');
	$env['maxExecutionTimeOk'] = ini_get('max_execution_time') >= 30;
	$env['mbstringOk'] = extension_loaded('mbstring');
	$env['gdOk'] = extension_loaded('gd');
	$env['curlOk'] = extension_loaded('curl');
	
	if (!$env['phpVersionOk'] or 
		!$env['pdoMysqlOk'] or 
		!$env['uploadMaxFilesizeOk'] or 
		!$env['postMaxsizeOk'] or 
		!$env['maxExecutionTimeOk'] or
		!$env['mbstringOk'] or
		!$env['curlOk'] or
		!$env['gdOk']) {
		$pass = false;
	}

	$paths = array(
		'app/config/parameters.yml',
		'app/data/udisk',
		'app/data/private_files',
		'web/files',
		'web/bundles',
		'app/cache',
		'app/data',
		'app/logs',
	);

	$checkedPaths = array();
	foreach ($paths as $path) {
		$checkedPath = __DIR__ . '/../../' . $path;
		if($path == 'web/bundles') {
			$checked = is_writable($checkedPath) && is_readable($checkedPath);
		} else {
			$checked = is_executable($checkedPath) && is_writable($checkedPath) && is_readable($checkedPath);
		}
		
		if (PHP_OS == 'WINNT') {
			$checked = true;
		}
		if (!$checked) {
			$pass = false;
		}
		$checkedPaths[$path] = $checked;
	}
	$safemode = ini_get('safe_mode');
	if($safemode == 'On')
	   $pass = false;

	echo $twig->render('step-1.html.twig', array(
		'step' => 1,
		'env' => $env,
		'paths' => $checkedPaths,
		'safemode' => $safemode,
		'pass' => $pass
	));
}

function install_step2()
{
	check_installed();
	global $twig;

	$yaml = new Yaml();
	$parameters = $yaml->parse('../../app/config/parameters.yml');
	$database_name = $parameters['parameters']['database_name'];
	$database_user = $parameters['parameters']['database_user'];
	$database_password = $parameters['parameters']['database_password'];

	$error = null;
    $post = array('database_name' => $database_name, 'database_user' => $database_user, 'database_password' => $database_password);
	if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {
        $post = $_POST;

        $replace = empty($_POST['database_replace']) ? false : true;

		$error = _create_database($_POST, $replace);
		if (empty($error)) {
			$error = _create_config($_POST);
		}
		if (empty($error)) {
			header("Location: start-install.php?step=3");
			exit(); 
		}
	}

	echo $twig->render('step-2.html.twig', array(
		'step' => 2,
		'error' => $error,
        'post' => $post,
	));
}

function install_step3()
{
	check_installed();
	global $twig;

	$connection = _create_connection();

	$yaml = new Yaml();
	$parameters = $yaml->parse('../../app/config/parameters_default.yml'); 

	$parameterBag = new ParameterBag($parameters);
	$serviceKernel = ServiceKernel::create('prod', true);
	$serviceKernel->setConnection($connection);
	$serviceKernel->setParameterBag($parameterBag);

	$error = null;
	if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {
        $init = new SystemInit();
        $admin = $init->initAdmin($_POST);
        $init->initSiteSettings($_POST);
        $init->initSchoolSetting($_POST);
        $init->initRegisterSetting($admin);
        $init->initMailerSetting($_POST['sitename']);
        $init->initPaymentSetting();
        $init->initStorageSetting();
        $init->initTag();
        $init->initCategory();
        $connection->beginTransaction();

        try{
          	$init->initSubject();
          	$init->initMaterial();
            $connection->commit();
        } catch(\Exception $e) {
            $connection->rollback();
            throw $e;
        }

        $connection->beginTransaction();
        try{
        	$init->initEduMaterial();
        	$init->initKnowledge();
            $connection->commit();
        } catch(\Exception $e) {
            $connection->rollback();
            throw $e;
        }
  
        $init->initFile();
        $init->initPages();
        $init->initNavigations();
        $init->initBlocks();
        $init->initThemes();
        $init->initLockFile();
        $init->initRefundSetting();
        $init->initArticleSetting();

        header("Location: start-install.php?step=4");
		exit();
	}

	echo $twig->render('step-3.html.twig', array(
		'step' => 3,
		'error' => $error,
		'request' => $_POST,
	));
}

function install_step4()
{
	global $twig;
	
        $userAgent = 'EduSoho Install Client 1.0';
        $connectTimeout = 10;
        $timeout = 10;
        $url = "http://open.edusoho.com/api/v1/block/two_dimension_code";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_URL, $url );
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true);

	echo $twig->render('step-4.html.twig', array(
		'step' => 4,
		"response"=>$response,
	));
}


/**
 * 生产Key
 */
function install_step999()
{
    session_start();

    $connection = _create_connection();
    $serviceKernel = ServiceKernel::create('prod', true);
    $serviceKernel->setConnection($connection);

    $init = new SystemInit();

    $key = $init->initKey();
    install_plugins();
    echo json_encode($key);
}

function install_plugins()
{
	$kernel = new AppKernel('dev', false);
	$kernel->boot();
	$application = new Application($kernel);
    $application->add(new PluginRegisterCommand());
    $command = $application->find('plugin:register');
    $commandTester = new CommandTester($command);
	$pluginsFilter = array('Homework');
	foreach ($pluginsFilter as $code) {
		$commandTester->execute(
		    array('command' => $command->getName(), 'code' => $code, 'mode' => 'appstore')
		);
	}
}

function _create_database($config, $replace)
{
	try {
		$pdo = new PDO("mysql:host={$config['database_host']};port={$config['database_port']}", "{$config['database_user']}", "{$config['database_password']}");

		$pdo->exec("SET NAMES utf8");

		$result = $pdo->exec("create database `{$config['database_name']}`;");
		if (empty($result) and !$replace) {
			return "数据库{$config['database_name']}已存在，创建失败，请删除或者勾选覆盖数据库之后再安装！";
		}

		$pdo->exec("USE `{$config['database_name']}`;");

		$sql = file_get_contents('./edusoho.sql');
		$result = $pdo->exec($sql);
		if ($result === false) {
			return "创建数据库表结构失败，请删除数据库后重试！";
		}

		return null;

	} catch (\PDOException $e) {
		return '数据库连接不上，请检查数据库服务器、用户名、密码是否正确!';
	}
}

function _create_config($config)
{
	$secret = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
	$config = "parameters:
    database_driver: pdo_mysql
    database_host: {$config['database_host']}
    database_port: {$config['database_port']}
    database_name: {$config['database_name']}
    database_user: {$config['database_user']}
    database_password: '{$config['database_password']}'
    mailer_transport: smtp
    mailer_host: 127.0.0.1
    mailer_user: null
    mailer_password: null
    locale: zh_CN
    secret: {$secret}
    user_partner: none";

    file_put_contents(__DIR__ . "/../../app/config/parameters.yml", $config);
}

function _create_connection()
{
     $factory = new \Doctrine\Bundle\DoctrineBundle\ConnectionFactory(array());	
     $parameters = file_get_contents(__DIR__ . "/../../app/config/parameters.yml");
     $parameters = \Symfony\Component\Yaml\Yaml::parse($parameters);
     $parameters = $parameters['parameters'];

     $connection = $factory->createConnection(
     	array(
     		'dbname' => $parameters['database_name'], 
     		'host' => $parameters['database_host'], 
     		'port' => $parameters['database_port'], 
     		'user' => $parameters['database_user'], 
     		'password' => $parameters['database_password'], 
     		'charset' => 'UTF8', 
     		'driver' => $parameters['database_driver'], '
     		driverOptions' => array())
     	,
     	new \Doctrine\DBAL\Configuration(),
     	null,
     	array('enum' => 'string')
 	);

    $connection->exec("SET NAMES utf8");

    return $connection;
}

class SystemInit
{

	public function initAdmin($user)
	{
		$user['number'] = 't001';
	    $user = $user = $this->getUserService()->register($user);
	    $user['roles'] =  array('ROLE_USER', 'ROLE_TEACHER', 'ROLE_SUPER_ADMIN');
	    $user['currentIp'] = '127.0.0.1';

	    $currentUser = new CurrentUser();
	    $currentUser->fromArray($user);
	    ServiceKernel::instance()->setCurrentUser($currentUser);

	    $this->getUserService()->changeUserRoles($user['id'], array('ROLE_USER', 'ROLE_TEACHER', 'ROLE_SUPER_ADMIN'));
	    return $this->getUserService()->getUser($user['id']);
	}

    public function initKey()
    {
        $applier = new KeyApplier();

        $users = $this->getUserService()->searchUsers(array('roles' => 'ROLE_SUPER_ADMIN'), array('createdTime', 'DESC'), 0, 1);

        if (empty($users) or empty($users[0])) {
            return array('error' => '管理员账号不存在，创建Key失败');
        }
        $keys = $applier->applyKey($users[0], 'k12', 'install');

        if (empty($keys['accessKey']) or empty($keys['secretKey'])) {
            return array('error' => 'Key生成失败，请检查服务器网络后，重试！');
        }

        $settings = $this->getSettingService()->get('storage', array());

        $settings['cloud_access_key'] = $keys['accessKey'];
        $settings['cloud_secret_key'] = $keys['secretKey'];
        $settings['cloud_key_applied'] = 1;

        $this->getSettingService()->set('storage', $settings);

        return $keys;
    }

	public function initRefundSetting()
	{

		$setting = array(
            'maxRefundDays' => 10,
            'applyNotification' => '您好，您退款的课程为{{course}}，管理员已收到您的退款申请，请耐心等待退款审核结果。',
            'successNotification' => '您好，您申请退款课程{{course}} 审核通过，将为您退款{{amount}}元。',
            'failedNotification' => '您好，您申请退款课程{{course}} 审核未通过，请与管理员再协商解决纠纷。',
        );
        $setting = $this->getSettingService()->set('refund', $setting);

	}

    public function initArticleSetting()
    {
        $setting = array(
            'name' => '资讯频道', 'pageNums' => 20
        );
        $setting = $this->getSettingService()->set('article', $setting);
    }

	public function initSiteSettings($settings)
	{
	    $default = array(
	        'name'=> $settings['sitename'],
	        'slogan'=>'',
	        'url'=>'',
	        'logo'=>'',
	        'seo_keywords'=>'',
	        'seo_description'=>'',
	        'master_email'=> $settings['email'],
	        'icp'=>'',
	        'analytics'=>'',
	        'status'=>'open',
	        'closed_note'=>'',
	        'homepage_template'=>'less'
	    );

	    $this->getSettingService()->set('site', $default);
	}

	public function initSchoolSetting($settings)
	{
		$default = array(
            'primarySchool' => 0,
            'middleSchool' => 0,
            'highSchool' => 0,
            'homepagePicture' => '',
        );
		$school = array();
		$settings['primarySchool'] == '1' ? $school['primarySchool'] = 1 : $school['primarySchool'] = 0;
		$settings['middleSchool'] == '1' ? $school['middleSchool'] = 1 : $school['middleSchool'] = 0;
		$settings['highSchool'] == '1' ? $school['highSchool'] = 1 : $school['highSchool'] = 0;
		$school = array_merge($default, $school);
		$this->getSettingService()->set('school', $school);

		// $this->createDafultClasses($settings);
	}

	public function createDafultClasses($settings)
	{
		$className = array('1班', '2班', '3班', '4班', '5班');
		$year = date('Y');
		$createdTime = time();
		$class = array();
		$class['year'] = $year;
		$class['term'] = 'first';
		$class['headTeacherId'] = 1;
		$class['enabled'] = 0;
		$class['createdTime'] = $createdTime;
	
		$this->getClassesDao()->getConnection()->beginTransaction();
		try{
			if($settings['primarySchool'] == '1') {
				for ($i=1; $i <= 6; $i++) { 
					foreach ($className as $value) {
						$class['name'] = $value;
						$class['gradeId'] = $i;
						$this->getClassesService()->createClass($class);
					}
				}
			}
			if($settings['middleSchool'] == '1') {
				for ($i=7; $i <= 9 ; $i++) {
					foreach ($className as $key => $value) {
					 	$class['name'] = $value;
						$class['gradeId'] = $i;
						$this->getClassesService()->createClass($class);
					}
				}
			}
			if($settings['highSchool'] == '1') {
				for ($i=10; $i <= 12 ; $i++) {
					foreach ($className as $key => $value) {
				 	 	$class['name'] = $value;
				 		$class['gradeId'] = $i;
				 		$this->getClassesService()->createClass($class);
					}
				}
			}

			$this->getClassesDao()->getConnection()->commit();
		} catch (Exception $e) {
			$this->getClassesDao()->getConnection()->rollBack();
		}

	}

	public function initRegisterSetting($user)
	{
	    $emailBody = <<<'EOD'
Hi, {{nickname}}

欢迎加入{{sitename}}!

请点击下面的链接完成注册：

{{verifyurl}}

如果以上链接无法点击，请将上面的地址复制到你的浏览器(如IE)的地址栏中打开，该链接地址24小时内打开有效。

感谢对{{sitename}}的支持！

{{sitename}} {{siteurl}}

(这是一封自动产生的email，请勿回复。)
EOD;

	    $default = array(
	        'register_mode'=>'opened',
	        'email_activation_title' => '请激活您的{{sitename}}账号',
	        'email_activation_body' => trim($emailBody),
	        'welcome_enabled' => 'opened',
	        'welcome_sender' => $user['nickname'],
	        'welcome_methods' => array(),
	        'welcome_title' => '欢迎加入{{sitename}}',
	        'welcome_body' => '您好{{nickname}}，我是{{sitename}}的管理员，欢迎加入{{sitename}}，祝您学习愉快。如有问题，随时与我联系。',
	    );

	    $this->getSettingService()->set('auth', $default);
	}

	public function initMailerSetting($sitename)
	{
	    $default = array(
	        'enabled'=>0,
	        'host'=>'smtp.example.com',
	        'port'=>'25',
	        'username'=>'user@example.com',
	        'password'=>'',
	        'from'=>'user@example.com',
	        'name'=> $sitename,
	    );
	    $this->getSettingService()->set('mailer', $default);
	}

	public function initPaymentSetting()
	{
	    $default = array(
	        'enabled'=>0,
	        'bank_gateway'=>'none',
	        'alipay_enabled'=>0,
	        'alipay_key'=>'',
	        'alipay_secret' => '',
	    );
	    $this->getSettingService()->set('payment', $default);
	}

	public function initStorageSetting()
	{
	    $default = array(
	        'upload_mode'=>'local',
	        'cloud_access_key'=>'',
            'cloud_secret_key'=>'',
	        'cloud_api_server'=>'http://api.edusoho.net',
            'cloud_bucket'=>'',
	    );

	    $this->getSettingService()->set('storage', $default);
	}

	public function initTag()
	{
		$defaultTag = $this->getTagService()->getTagByName('默认标签');
		if (!$defaultTag) {
			$this->getTagService()->addTag(array('name' => '默认标签'));
		}
	}

	public function initCategory()
	{
		$group = $this->getCategoryService()->addGroup(array(
			'name' => '课程分类',
			'code' => 'course',
			'depth' => 2,
		));

		$this->getCategoryService()->createCategory(array(
			'name' => '默认分类',
			'code' => 'default',
			'weight' => 100,
			'groupId' => $group['id'],
			'parentId' => 0,
		));
	}

	public function initSubject()
	{
		$group = $this->getCategoryService()->addGroup(array(
			'name' => '学科-教材',
			'code' => 'subject_material',
			'depth' => 1,
		));

		$subjectData = include(__DIR__ . '/../../src/Topxia/Service/Taxonomy/SubjectData.php');
		foreach($subjectData as $schoolCode => $subjects) {
			$schoolName = ($schoolCode == 'es_xx') ? '小学' : ($schoolCode == 'es_cz' ? '初中' : '高中');
			$parent = $this->getCategoryService()->createCategory(array(
				'name' => $schoolName,
				'code' => $schoolCode,
				'weight' => 0,
				'groupId' => $group['id'],
				'parentId' => 0,
			));
			foreach ($subjects as $code => $name) {
				$this->getCategoryService()->createCategory(array(
					'name' => $name,
					'code' => $code,
					'weight' => 0,
					'groupId' => $group['id'],
					'parentId' => $parent['id'],
				));
			}
		}

	}

	public function initMaterial()
	{
		$group = $this->getCategoryService()->getGroupByCode('subject_material');

		$EduMaterialData = include(__DIR__ . '/../../src/Topxia/Service/Taxonomy/EduMaterialData.php');
		foreach($EduMaterialData as $EduMaterials) {
			foreach ($EduMaterials as $code => $materials) {
				$parentCategory = $this->getCategoryService()->getCategoryByCode($code);
				foreach ($materials as $mcode => $name) {
					$this->getCategoryService()->createCategory(array(
					'name' => $name,
					'code' => $mcode,
					'weight' => 0,
					'groupId' => $group['id'],
					'parentId' => $parentCategory['id'],
					));
				}
				
			}
		}

	}

	public function initEduMaterial()
	{
		$grades=DataDict::dict('gradeName');
		$mappingData = include(__DIR__ . '/../../src/Topxia/Service/Taxonomy/MaterialMappingData.php');

		foreach ($mappingData as $subjectCode => $materialCode) {
			foreach ($grades as $gradeId=>$grade) {
				$eduMaterial['gradeId']=$gradeId;
				$subject = $this->getCategoryService()->getCategoryByCode($subjectCode);
				$eduMaterial['subjectId']=$subject['id'];
				$material = $this->getCategoryService()->getCategoryByCode($materialCode);
				$eduMaterial['materialId']=$material['id'];
				$eduMaterial['materialName']=$material['name'];
				$this->getEduMaterialService()->addEduMaterial($eduMaterial);
			}
		}
		
	}

	public function initKnowledge()
	    {
	    	$dir = __DIR__ . '/../../src/Topxia/Service/Taxonomy/';  
			if (is_dir($dir)){
				if ($dh = opendir($dir)){
					while (($file = readdir($dh))!= false){
						$filepath = $dir.$file;
						if(preg_match('/.*\.xlsx$/', $filepath)) {
							$this->loadExcel($filepath);
						}
						
					}
					closedir($dh);
				}
			}
	    	
	    }

    public function loadExcel($filepath)
    {
    	$objPHPExcel = PHPExcel_IOFactory::load($filepath);
    	$workSheets = $objPHPExcel->getAllSheets();
    	foreach ($workSheets as $key => $workSheet) {
    		$highestRow = $workSheet->getHighestRow(); 
    		$subjectCode = trim(($workSheet->getCellByColumnAndRow(0, 1)->getValue()));
    		$materialCode = trim(($workSheet->getCellByColumnAndRow(1, 1)->getValue()));
    		$gradeId = trim(($workSheet->getCellByColumnAndRow(2, 1)->getValue()));
    		$term = trim(($workSheet->getCellByColumnAndRow(3, 1)->getValue()));
    		$subject = $this->getCategoryService()->getCategoryByCode($subjectCode);
    		$material = $this->getCategoryService()->getCategoryByCode($materialCode);
    		$subjectId = $subject['id'];
    		$materialId = $material['id'];
    		$knowledge = array(
    			'subjectId' => $subjectId,
    			'materialId' => $materialId,
    			'term' => $term,
    			'gradeId' => $gradeId,
    			'weight' => 0
    		);
    		$chapterId = 0;
    		$unitId = 0;
    		$knowledge1Id = 0;
    		$knowledge2Id = 0;
    		for ($row = 2;$row <= $highestRow;$row++) { 
    			$chapterTitle = trim($workSheet->getCellByColumnAndRow(0, $row)->getValue());
    			$unitTitle = trim($workSheet->getCellByColumnAndRow(1, $row)->getValue());
    			$knowledge1 = trim($workSheet->getCellByColumnAndRow(2, $row)->getValue());
    			$knowledge2 = trim($workSheet->getCellByColumnAndRow(3, $row)->getValue());
    			$knowledge3 = trim($workSheet->getCellByColumnAndRow(4, $row)->getValue());
    			if(empty($chapterTitle) && empty($unitTitle) && empty($knowledge1) && empty($knowledge2) && empty($knowledge3)) {
    				break;
    			}
    			if($chapterTitle) {
    				$knowledge['name'] = $chapterTitle;
    				$knowledge['parentId'] = 0;
    				$newKnowledge = $this->getKnowledgeService()->createKnowledge($knowledge);
    				$chapterId = $newKnowledge['id'];
    			}
    			if($unitTitle) {
    				$knowledge['name'] = $unitTitle;
    				$knowledge['parentId'] = $chapterId;
    				$newKnowledge = $this->getKnowledgeService()->createKnowledge($knowledge);
    				$unitId = $newKnowledge['id'];
    			}

    			if($knowledge1) {
    				$knowledge['name'] = $knowledge1;
    				$knowledge['parentId'] = $unitId;
    				$newKnowledge = $this->getKnowledgeService()->createKnowledge($knowledge);
    				$knowledge1Id = $newKnowledge['id'];
    			}

    			if($knowledge2) {
    				$knowledge['name'] = $knowledge2;
    				$knowledge['parentId'] = $knowledge1Id;
    				$newKnowledge = $this->getKnowledgeService()->createKnowledge($knowledge);
    				$knowledge2Id = $newKnowledge['id'];
    			}  

    			if($knowledge3) {
    				$knowledge['name'] = $knowledge3;
    				$knowledge['parentId'] = $knowledge2Id;
    				$newKnowledge = $this->getKnowledgeService()->createKnowledge($knowledge);
    			}    

    		}
    	}
    }

	public function initFile()
	{
		$this->getFileService()->addFileGroup(array(
			'name' => '默认文件组',
			'code' => 'default',
			'public' => 1,
		));

		$this->getFileService()->addFileGroup(array(
			'name' => '缩略图',
			'code' => 'thumb',
			'public' => 1,
		));

		$this->getFileService()->addFileGroup(array(
			'name' => '课程',
			'code' => 'course',
			'public' => 1,
		));

		$this->getFileService()->addFileGroup(array(
			'name' => '用户',
			'code' => 'user',
			'public' => 1,
		));

		$this->getFileService()->addFileGroup(array(
			'name' => '课程私有文件',
			'code' => 'course_private',
			'public' => 0,
		));

        $this->getFileService()->addFileGroup(array(
            'name' => '资讯',
            'code' => 'article',
            'public' => 1,
        ));

	}

	public function initPages()
	{
        $this->getContentService()->createContent(array(
            'title'=>'关于我们',
            'type'=>'page',
            'alias'=>'aboutus',
            'body'=>'',
            'template'=>'default',
            'status'=>'published',
        ));

        $this->getContentService()->createContent(array(
            'title'=>'常见问题',
            'type'=>'page',
            'alias'=>'questions',
            'body'=>'',
            'template'=>'default',
            'status'=>'published',
        ));
	}

	public function initNavigations()
	{
        $this->getNavigationService()->createNavigation(array(
            'name'=>'师资力量', 
            'url'=> 'teacher', 
            'sequence' => 1,
            'isNewWin'=>0,
            'isOpen'=> 1,
            'type'=>'top'
        ));

        $this->getNavigationService()->createNavigation(array(
            'name'=>'常见问题', 
            'url'=> 'page/questions', 
            'sequence' => 2,
            'isNewWin'=>0,
            'isOpen'=> 1,
            'type'=>'top'
        ));

        $this->getNavigationService()->createNavigation(array(
            'name' => '关于我们', 
            'url' => 'page/aboutus',
            'sequence' => 2,
            'isNewWin' => 0,
            'isOpen' => 1,
            'type' => 'top'
        ));
	}

    public function initThemes()
    {
        $this->getSettingService()->set('theme', array('uri' => 'default'));
    }

	public function initBlocks()
	{
        $block = $this->getBlockService()->createBlock(array(
            'code'=>'home_top_banner',
                'title'=>'首页轮播图',
                'tips'=>'图片尺寸为1140*380像素'
        ));

        $content = <<<'EOD'
<a href="#"><img src="/assets/img/placeholder/carousel-1140x380-1.jpg"></a>
<a href="#"><img src="/assets/img/placeholder/carousel-1140x380-1.jpg"></a>
<a href="#"><img src="/assets/img/placeholder/carousel-1140x380-1.jpg"></a>
EOD;

		$this->getBlockService()->updateContent($block['id'], $content);

	}

	public function initLockFile()
	{
		file_put_contents(__DIR__ . '/../../app/data/install.lock', '');
	}

	private function getUserService()
	{
		return ServiceKernel::instance()->createService('User.UserService');
	}

	private function getClassesDao()
	{
		return ServiceKernel::instance()->createDao('Classes.ClassesDao');
	}

    private function getClassesService()
    {
        return ServiceKernel::instance()->createService('Classes.ClassesService');
    }

	private function getSettingService()
	{
		return ServiceKernel::instance()->createService('System.SettingService');
	}

	private function getCategoryService()
	{
		return ServiceKernel::instance()->createService('Taxonomy.CategoryService');
	}

	private function getTagService()
	{
		return ServiceKernel::instance()->createService('Taxonomy.TagService');
	}

	private function getFileService()
	{
		return ServiceKernel::instance()->createService('Content.FileService');
	}

    protected function getContentService()
    {
        return ServiceKernel::instance()->createService('Content.ContentService');
    }

    protected function getBlockService()
    {
        return ServiceKernel::instance()->createService('Content.BlockService');
    }

    protected function getNavigationService()
    {
        return ServiceKernel::instance()->createService('Content.NavigationService');
    }

	protected function getEduMaterialService()
    {
        return ServiceKernel::instance()->createService('Course.EduMaterialService');
    }

    	private function getKnowledgeService()
	{
	    return ServiceKernel::instance()->createService('Taxonomy.KnowledgeService');
	}
    protected function postRequest($url, $params)
    {
        $userAgent = 'EduSoho Install Client 1.0';

        $connectTimeout = 10;

        $timeout = 10;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_URL, $url );

        // curl_setopt($curl, CURLINFO_HEADER_OUT, TRUE );

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    
}