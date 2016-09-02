<?php
namespace Topxia\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;

class ClassController extends BaseController 
{

    public function listAction(Request $request){
        $schoolSetting=$this->getSettingService()->get('school');

        $schools=array();
        if(isset($schoolSetting['primarySchool'])) {
            $schools['primarySchool']=array(
                    'name'=>'小学',
                    'grades'=>$this->getGradeService()->findGradesByGroup('primary')
                );
        }

        if(isset($schoolSetting['middleSchool'])) {
            $schools['middleSchool']=array(
                'name'=>'初中',
                'grades'=>$this->getGradeService()->findGradesByGroup('middle')
            );
        }

        if(isset($schoolSetting['highSchool'])) {
            $schools['highSchool']=array(
                'name'=>'高中',
                'grades'=>$this->getGradeService()->findGradesByGroup('high')
            );
        }
        $classList = $this->getClassesService()->searchClasses(
            array(),
            array(),
            0,
            PHP_INT_MAX
        );

        $classList = ArrayToolkit::group($classList,'gradeId');
        return $this->render('TopxiaAdminBundle:Class:class-list-modal.html.twig',array(
            'schools'=>$schools,
            'classList'=>$classList
        ));
    }

    protected function getClassesService()
    {
        return $this->getServiceKernel()->createService('Classes.ClassesService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getGradeService()
    {
        return $this->getServiceKernel()->createService('Grade.GradeService');
    }
}