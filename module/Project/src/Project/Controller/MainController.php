<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Project\Controller;

use Zend\Json\Expr;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\Session as SessionStorage;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Zend\Session\Container;
use Application\View\Helper\Qualifier;

class MainController extends AbstractActionController
{
    public function __construct()
    {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();

        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function resourceAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Resource");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('v' => 'Proj_ResourceType'))
            ->columns(array('TypeId','TypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view ->typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $typeId = $postData['cboType'];
            $groupId = $postData['cboGroup'];
            $subGroupId = $postData['cboSubGroup'];

            return $this->redirect()->toRoute('project/resGroup', array('controller' => 'Resource', 'action' => 'Index','typeId'=>$typeId,'groupId'=>$groupId,'subGroupId'=>$subGroupId));
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function codesetupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Code Setup");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();

            $igentype = ($postData['gentype'] == 'manual') ? 0 : 1;
            $ptype = (isset($postData['ptype'])) ? 1 : 0;
            $pgroup = (isset($postData['pgroup'])) ? 1 : 0;

            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                if ($postData['codefound'] == 1) {
                    $update = $sql->update();
                    $update->table('Proj_ResourceCodeSetup');
                    $update->set(array(
                        'GenType' => $igentype, 'Prefix' => $postData['prefix'], 'PType' => $ptype, 'PGroup' => $pgroup, 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'GroupLevel' => $postData['grouplevel'], 'CountLevel' => $postData['countlevel'], 'Separator' => $postData['separator']
                    ));

                    $statement = $sql->getSqlStringForSqlObject($update);
                } else {
                    $insert = $sql->insert();
                    $insert->into('Proj_ResourceCodeSetup');
                    $insert->Values(array('GenType' => $igentype, 'Prefix' => $postData['prefix'], 'PType' => $ptype, 'PGroup' => $pgroup, 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'GroupLevel' => $postData['grouplevel'], 'CountLevel' => $postData['countlevel'], 'Separator' => $postData['separator']));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                }
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $connection->commit();
                $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));

            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }
        }

        $select = $sql->select();
        $select->from('Proj_ResourceCodeSetup');
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->code = $code;
        $this->_view->codefound = (!empty($code)) ? 1 : 0;

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function revisionnamesetupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Revision Name Setup");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $sql = new Sql($dbAdapter);

            $tPrefix = $this->bsf->isNullCheck($postData['tprefix'],'string');
            $tWidth = $this->bsf->isNullCheck($postData['twidth'],'number');
            $tSeperator= $this->bsf->isNullCheck($postData['tseparator'],'string');
            $tsuffix= $this->bsf->isNullCheck($postData['tsuffix'],'string');

            $wPrefix = $this->bsf->isNullCheck($postData['wprefix'],'string');
            $wWidth = $this->bsf->isNullCheck($postData['wwidth'],'number');
            $wSeperator= $this->bsf->isNullCheck($postData['wseparator'],'string');
            $wsuffix= $this->bsf->isNullCheck($postData['wsuffix'],'string');

            $bPrefix = $this->bsf->isNullCheck($postData['bprefix'],'string');
            $bWidth = $this->bsf->isNullCheck($postData['bwidth'],'number');
            $bSeperator= $this->bsf->isNullCheck($postData['bseparator'],'string');
            $bsuffix= $this->bsf->isNullCheck($postData['bsuffix'],'string');

            $pPrefix = $this->bsf->isNullCheck($postData['pprefix'],'string');
            $pWidth = $this->bsf->isNullCheck($postData['pwidth'],'number');
            $pSeperator= $this->bsf->isNullCheck($postData['pseparator'],'string');
            $psuffix= $this->bsf->isNullCheck($postData['psuffix'],'string');

            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $connection->beginTransaction();

                $update = $sql->update();
                $update->table('Proj_RevisionNameSetup');
                $update->set(array('Prefix' => $tPrefix, 'Suffix' => $tsuffix, 'Width' => $tWidth, 'Separator' => $tSeperator));
                $update->where(array('StageId' => 1));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_RevisionNameSetup');
                $update->set(array('Prefix' => $wPrefix, 'Suffix' => $wsuffix, 'Width' => $wWidth, 'Separator' => $wSeperator));
                $update->where(array('StageId' => 2));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $update = $sql->update();
                $update->table('Proj_RevisionNameSetup');
                $update->set(array('Prefix' => $bPrefix, 'Suffix' => $bsuffix, 'Width' => $bWidth, 'Separator' => $bSeperator));
                $update->where(array('StageId' => 3));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_RevisionNameSetup');
                $update->set(array('Prefix' => $pPrefix, 'Suffix' => $psuffix, 'Width' => $pWidth, 'Separator' => $pSeperator));
                $update->where(array('StageId' => 4));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $connection->commit();
                $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));
            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }
        }
        $select = $sql->select();
        $select->from('Proj_RevisionNameSetup');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->setup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function schedulenamesetupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Schedule Name Setup");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $sql = new Sql($dbAdapter);

            $tPrefix = $this->bsf->isNullCheck($postData['tprefix'],'string');
            $tWidth = $this->bsf->isNullCheck($postData['twidth'],'number');
            $tSeperator= $this->bsf->isNullCheck($postData['tseparator'],'string');
            $tsuffix= $this->bsf->isNullCheck($postData['tsuffix'],'string');

            $wPrefix = $this->bsf->isNullCheck($postData['wprefix'],'string');
            $wWidth = $this->bsf->isNullCheck($postData['wwidth'],'number');
            $wSeperator= $this->bsf->isNullCheck($postData['wseparator'],'string');
            $wsuffix= $this->bsf->isNullCheck($postData['wsuffix'],'string');

            $bPrefix = $this->bsf->isNullCheck($postData['bprefix'],'string');
            $bWidth = $this->bsf->isNullCheck($postData['bwidth'],'number');
            $bSeperator= $this->bsf->isNullCheck($postData['bseparator'],'string');
            $bsuffix= $this->bsf->isNullCheck($postData['bsuffix'],'string');

            $pPrefix = $this->bsf->isNullCheck($postData['pprefix'],'string');
            $pWidth = $this->bsf->isNullCheck($postData['pwidth'],'number');
            $pSeperator= $this->bsf->isNullCheck($postData['pseparator'],'string');
            $psuffix= $this->bsf->isNullCheck($postData['psuffix'],'string');

            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $connection->beginTransaction();

                $update = $sql->update();
                $update->table('Proj_ScheduleNameSetup');
                $update->set(array('Prefix' => $tPrefix, 'Suffix' => $tsuffix, 'Width' => $tWidth, 'Separator' => $tSeperator));
                $update->where(array('StageId' => 1));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_ScheduleNameSetup');
                $update->set(array('Prefix' => $wPrefix, 'Suffix' => $wsuffix, 'Width' => $wWidth, 'Separator' => $wSeperator));
                $update->where(array('StageId' => 2));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $update = $sql->update();
                $update->table('Proj_ScheduleNameSetup');
                $update->set(array('Prefix' => $bPrefix, 'Suffix' => $bsuffix, 'Width' => $bWidth, 'Separator' => $bSeperator));
                $update->where(array('StageId' => 3));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_ScheduleNameSetup');
                $update->set(array('Prefix' => $pPrefix, 'Suffix' => $psuffix, 'Width' => $pWidth, 'Separator' => $pSeperator));
                $update->where(array('StageId' => 4));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $connection->commit();
                $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));

            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }
        }

        $select = $sql->select();
        $select->from('Proj_ScheduleNameSetup');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->setup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function resgroupmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Resource Group Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");


        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array('a' => 'Proj_ResourceGroup'))
            ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
            ->columns(array('ResourceGroupId','ResourceGroupName','ParentId', 'Code','LastLevel','TypeId'), array('TypeName'))
            ->where(array('DeleteFlag' => 0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();;

//        $sql = new Sql($dbAdapter);
//        $select = $sql->select();
//        $select->from('Proj_ResourceGroup')
//            ->columns(array('ResourceGroupId', 'ResourceGroupName','ParentId'));
//
//        $statement = $sql->getSqlStringForSqlObject($select);


        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function resourcemasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Resource Group Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array('a' => 'Proj_Resource'))
            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array('ResourceGroupName'), $select:: JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
            ->join(array('d' => 'Proj_ResourceType'), 'a.TypeId=d.TypeId', array('TypeName'), $select:: JOIN_LEFT)
            ->columns(array('ResourceId','Code','ResourceName','Rate','TypeId'))
            ->where(array('a.DeleteFlag' => 0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ressourcemaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Resource Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function uomregisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || UOM Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array('a' => 'Proj_UOM'))
            ->join(array('b' =>'Proj_UnitType'),'a.TypeId=b.TypeId',array('TypeName'), $select:: JOIN_LEFT)
            ->columns(array('UnitId', 'UnitName','UnitDescription','TypeId','SysDefault'))
            ->order('a.UnitName');
        $statement = $sql->getSqlStringForSqlObject($select);

        $this->_view->UOM = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_UnitType')
            ->columns(array('data' => 'TypeId', 'value' =>'TypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unittype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }


    public function getuomRegisterAction() {

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $postParams = $request->getPost();
                $sOption =  $this->bsf->isNullCheck($postParams['SortOption'],'string');
                $sSearchString= $this->bsf->isNullCheck($postParams['SearchString'],'string');

                $select = $sql->select();
                $select->from(array('a' => 'Proj_UOM'))
                    ->join(array('b' =>'Proj_UnitType'),'a.TypeId=b.TypeId',array('TypeName'), $select:: JOIN_LEFT)
                    ->columns(array('UnitId', 'UnitName','UnitDescription','TypeId','SysDefault'));

                if ($sSearchString !="") {
                    $where = "";
                    $sSearchString = '%' . $sSearchString .'%';
                    $where =  $where . " a.UnitName like('".$sSearchString."')";
                    $where =  $where . " or a.UnitDescription like('".$sSearchString."')";
                    $where =  $where . " or b.TypeName like('".$sSearchString."')";
                    $select->where($where);
                }

                if ($sOption == "Unit Name") {
                    $select->order('a.UnitName');
                } else if ($sOption == "Unit Description") {
                    $select->order('a.UnitDescription');
                } else if ($sOption == "Type Name") {
                    $select->order('b.TypeName');
                }

                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $data = array();
                $data['trans'] = $results;
                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }


    public function workgroupmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Work Group Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function worktypemasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Work Type Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function iowmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || IOW Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function getiowdetailsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $resId = $postParams['resId'];
                $sql = new Sql($dbAdapter);

                // iowmaster details
                $select = $sql->select();
                $select->from(array('a' => 'Proj_IOWMaster'))
                    ->join(array('b' => 'Proj_WorkGroupMaster'), 'a.WorkGroupId=b.WorkGroupId', array('ConcreteMix','Cement','Sand','Metal','Thickness'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.Unitid', array('UnitName'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_IOWRate'), 'a.IOWId=d.IOWId', array('WastageAmt','BaseRate','QualifierValue','TotalRate','NetRate','RWastageAmt','RBaseRate','RQualifierValue','RTotalRate','RNetRate'), $select:: JOIN_LEFT)
                    ->columns(array('*'))
                    ->where(array("a.IOWId=$resId"));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                // rate analysis details
                $select = $sql->select();
                $select->from(array('a' => 'Proj_IOWRateAnalysis'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_IOWMaster'), 'a.SubIOWId=c.IOWId', array('SerialNo', 'Specification'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName'=>new Expression('d.UnitName')), $select:: JOIN_LEFT)
                    ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('IOWUnitName'=>new Expression('e.UnitName')), $select:: JOIN_LEFT)
                    ->columns(array('RFCTransId', 'IncludeFlag', 'ReferenceId','SubIOWId', 'ResourceId', 'Qty', 'Rate', 'Amount', 'Formula', 'MixType','TransType', 'Description','Wastage','Weightage','SortId','RateType'))
                    ->where(array("a.IOWId=$resId"));
                $select->order('a.SortId ASC');

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->analysis = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getiowmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_IOWMaster'))
                ->join(array('b' => 'Proj_WorkGroupMaster'), 'a.WorkGroupId=b.WorkGroupId', array('WorkGroupName'), $select:: JOIN_LEFT)
                ->columns(array('IOWId', 'ParentId','SerialNo','Specification','UnitId'),array('WorkGroupName'))
                ->where(array('a.DeleteFlag' => 0));

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public function getresourcedetailsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $resId = $postParams['resId'];
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=c.ResourceGroupId', array('ResourceGroupName'), $select:: JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.Unitid', array('UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('*'),array('TypeName'), array('ResourceGroupName'),array('UnitName'))
                    ->where(array("a.ResourceId=$resId"));

                $statement = $sql->getSqlStringForSqlObject($select);
                $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($details['MaterialType'] == 'S') {
                    $select = $sql->select();
                    $select->from('Proj_ResourceSteelTrans')
                        ->where(array("ResourceId=$resId"));

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->steelTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                if ($details['TypeId'] == '4') {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ResourceActivityTrans'))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ResourceType'), 'b.TypeId=c.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=d.ResourceGroupId', array('ResourceGroupName'), $select:: JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.Unitid', array('UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('*'),array('Code', 'ResourceName'),array('TypeName'), array('ResourceGroupName'),array('UnitName'))
                        ->where(array("a.MResourceId=$resId"));

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->analysis = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $this->_view->details = $details;

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getresourcegroupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ResourceGroup'))
                ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                ->columns(array('ResourceGroupId','ResourceGroupName','ParentId', 'Code','LastLevel'), array('TypeName'))
                ->where(array('DeleteFlag' => 0));

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public function getresourcegroupdetailsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $resId = $postParams['resId'];

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('a' => 'Proj_ResourceGroup'))
                    ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                    ->columns(array('*'),array('TypeName'))
                    ->where(array("ResourceGroupId=$resId"));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getresourcemasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_Resource'))
                ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array('ResourceGroupName'), $select:: JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                ->join(array('d' => 'Proj_ResourceType'), 'a.TypeId=d.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                ->columns(array('ResourceId','Code','ResourceName','Rate'))
                ->where(array('a.DeleteFlag' => 0));

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public function getworkgroupdetailsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $resId = $postParams['resId'];
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Proj_WorkGroupMaster')
                    ->where(array("WorkGroupId=$resId"));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_WorkGroupAnalysis'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code','Rate','ResourceName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('IncludeFlag','ReferenceId','ResourceId','Qty','CFormula','Type','Description','TransType'))
                    ->where(array("a.WorkGroupId=$resId"));
                $select->order('a.SortId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->workgroupanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getworkgroupmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_WorkGroupMaster'))
                ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId', array('WorkType'), $select:: JOIN_LEFT)
                ->columns(array('WorkGroupId','SerialNo','WorkGroupName'), array('WorkType'))
                ->where(array('DeleteFlag' => 0));

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }
    public function getrevisionmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $iProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number');
                $select = $sql->select();
                $select->from(array('a' => 'Proj_RevisionMaster'))
                    ->join(array('b' => 'Proj_RFCRegister'), 'a.RFCRegisterId=b.RFCRegisterId', array('RefNo', 'RFCType'), $select:: JOIN_LEFT)
                    ->columns(array('RFCRegisterId','RevisionId','CreateDate', 'RevisionName', 'Type' => new Expression("Case When RevisionType='B' Then 'Budget' else 'Plan' end")))
                    ->where(array('a.ProjectId' => $iProjectId));
                $select->order('a.OrderId ASC');

                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $response = $this->getResponse();
                $response->setContent(json_encode($results->toArray()));
                return $response;
            }
        }
    }
    public function getworktypeanalysisAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $WTId = $postParams['resId'];

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('a' => 'Proj_WorkTypeAnalysis'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code'), $select:: JOIN_LEFT)
                    ->columns(array('WorkTypeId', 'IncludeFlag','ReferenceId','ResourceId','Qty', 'TransType', 'Description'))
                    ->where(array("WorkTypeId=$WTId"));

                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $response = $this->getResponse();
                $response->setContent(json_encode($results->toArray()));
                return $response;
            }
        }
    }

    public function getworktypedetailsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $resId = $postParams['resId'];
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('Proj_WorkTypeMaster')
                    ->where(array("WorkTypeId=$resId"));

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_WorkTypeAnalysis'))
                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Code', 'ResourceName'), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                    ->columns(array('IncludeFlag','ReferenceId','ResourceId','Qty','CFormula','Type','TransType', 'Description'))
                    ->where(array("a.WorkTypeId=$resId"));
                $select->order('a.SortId ASC');

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->worktypeanal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }

    public function getworktypemasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_WorkTypeMaster'))
                ->columns(array('WorkTypeId','WorkType'));

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public function resourcegroupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

//        $sql = new Sql($dbAdapter);
//        $select = $sql->select();
//        $select->from(array('a' => 'Proj_RFCREsourceGroupTrans'))
//               ->columns(array('ResourceGroupName'));
//               //->where("RFCRegisterId=11518");
//        $statement = $sql->getSqlStringForSqlObject($select);
//        echo $statement = str_replace("AS [a]", "AS [a] with (ReadPast)", $statement);
//        $pendingwork = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());
//
//        var_dump($pendingwork);
//        die;


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $response = $this->getResponse();
                $postParams = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('v' => 'resourcegroup'))
                    ->columns(array('ResourceGroupId','ResourceGroupName','TypeId'))
                    ->where ->like('TypeId',$postParams['TypeId']);

                $statement = $sql->getSqlStringForSqlObject($select);
                $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $html="<label>Resource Group</label>&nbsp;&nbsp;";
                $html.="<select name='cboGroup' id='cboGroup' onchange='return resourceSubGroup(this.value)'>";
                $html.="<option value='0'>Select Resource Group</option>";
                foreach ($results as $rs)
                {
                    $html.="<option value='".$rs['ResourceGroupId']."'";
                    $html.=">".$rs['ResourceGroupName']."</option>";
                }
                $html.="</select>";
                $response->setStatusCode(200);
                $response->setContent($html);
                return $response;
            }
        }
    }

    public function resourcesubgroupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $response = $this->getResponse();
                $postParams = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array('v' => 'resourcesubgroup'))
                    ->columns(array('ResourcesubGroupId', 'ResourcesubGroupName'))
                    ->where->like('ResourceGroupId', $postParams['GroupId']);

                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $html="<label>Resource Sub Group</label>&nbsp;&nbsp;";
                $html.="<select name='cboSubGroup' id='cboSubGroup'>";
                $html.="<option value='0'>Select Resource Sub Group</option>";
                foreach ($results as $rs)
                {
                    $html.="<option value='".$rs['ResourcesubGroupId']."'";
                    $html.=">".$rs['ResourcesubGroupName']."</option>";
                }
                $html.="</select>";

                $response->setStatusCode(200);
                $response->setContent($html);
                return $response;
            }
        }
    }

    public function insertworkgroupAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();

            $iWGId = $postData['rfcUId'];
            $iRowId = $postData['rowid'];
            $iRowIdR = $postData['rowidR'];

            $sql = new Sql($dbAdapter);

            if ($iWGId == 0) {
                $insert = $sql->insert();
                $insert->into('Proj_WorkGroupMaster');
                $insert->Values(array('WorkTypeId' => $postData['worktype'], 'WorkGroupName' => $postData['workgroup'], 'AutoRateAnalysis' => 1));
                $statement = $sql->getSqlStringForSqlObject($insert);

                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $iWGId = $dbAdapter->getDriver()->getLastGeneratedValue();
            } else {
                $update = $sql->update();
                $update->table('Proj_WorkGroupMaster');
                $update->set(array(
                    'WorkTypeId' => $postData['worktype'], 'WorkGroupName' => $postData['workgroup'], 'AutoRateAnalysis' => 1
                ));
                $update->where(array('WorkGroupId' => $iWGId));
                $statement = $sql->getSqlStringForSqlObject($update);

                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            for ($i = 1; $i <= $iRowId; $i++) {

                $iresid = $postData['resid_' . $i];
                if ($iresid != 0) {

                    $check_value = isset($postData['inc_' . $i]) ? 1 : 0;

                    $insert = $sql->insert();
                    $insert->into('Proj_WorkGroupAnalysis');
                    $insert->Values(array('WorkGroupId' => $iWGId, 'IncludeFlag' => $check_value, 'ReferenceId' => $i, 'ResourceId' => $iresid, 'Qty' => $postData['qty_' . $i], 'Type' => 'S'));
                    $statement = $sql->getSqlStringForSqlObject($insert);

                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }
            }

            for ($i = 1; $i <= $iRowIdR; $i++) {

                $iresid = $postData['residR_' . $i];
                if ($iresid != 0) {

                    $check_value = isset($postData['incR_' . $i]) ? 1 : 0;

                    $insert = $sql->insert();
                    $insert->into('Proj_WorkGroupAnalysis');
                    $insert->Values(array('WorkGroupId' => $iWGId, 'IncludeFlag' => $check_value, 'ReferenceId' => $i, 'ResourceId' => $iresid, 'Qty' => $postData['qtyR_' . $i], 'Type' => 'R'));
                    $statement = $sql->getSqlStringForSqlObject($insert);

                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }
            }
            $this->redirect()->toRoute('project/default', array('controller' => 'main', 'action' => 'workgroup'));
        }
    }

    public function insertworktyperfcAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $sql = new Sql($dbAdapter);
            $postData = $request->getPost();
            $iRowId = $postData['rowid'];

            for ($i = 1; $i <= $iRowId; $i++) {
                $iTypeId = $postData['typeid_' . $i];
                $iTransId = $postData['wrowid_' . $i];
                $iTransIdR = $postData['wrowid_R' . $i];
                for ($j = 1; $j <= $iTransId; $j++) {
                    if (isset($postData['type_' . $i . 'resid_' . $j])) {
                        $iresid = $postData['type_' . $i . 'resid_' . $j];
                        if ($iresid != 0) {

                            $check_value = isset($postData['type_' . $i . 'inc_' . $j]) ? 1 : 0;

                            $insert = $sql->insert();
                            $insert->into('Proj_WorkTypeAnalysis');
                            $insert->Values(array('WorkTypeId' => $iTypeId, 'IncludeFlag' => $check_value, 'ReferenceId' => $j, 'ResourceId' => $iresid, 'Qty' => $postData['type_' . $i . 'qty_' . $j], 'Type' => 'S'));
                            $statement = $sql->getSqlStringForSqlObject($insert);

                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }
                for ($j = 1; $j <= $iTransIdR; $j++) {
                    if (isset($postData['typeR_' . $i . 'resid_' . $j])) {
                        $iresid = $postData['typeR_' . $i . 'resid_' . $j];
                        if ($iresid != 0) {

                            $check_value = isset($postData['typeR_' . $i . 'inc_' . $j]) ? 1 : 0;

                            $insert = $sql->insert();
                            $insert->into('Proj_WorkTypeAnalysis');
                            $insert->Values(array('WorkTypeId' => $iTypeId, 'IncludeFlag' => $check_value, 'ReferenceId' => $j, 'ResourceId' => $iresid, 'Qty' => $postData['typeR_' . $i . 'qty_' . $j], 'Type' => 'R'));
                            $statement = $sql->getSqlStringForSqlObject($insert);

                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }

            }
            $this->redirect()->toRoute('project/default', array('controller' => 'main', 'action' => 'worktype'));
        }
    }

    public  function updateuomAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();

            $iRowId = $postData['rowid'];

            for ($i = 1; $i <= $iRowId; $i++) {
                $sql = new Sql($dbAdapter);
                $update = $sql->update();
                $update->table('Proj_UOM');

                if ($postData['sysdefault_' . $i] == 0) {
                    $update->set(array(
                        'UnitDescription' => $postData['unitdes_' . $i],
                        'UnitName' => $postData['unitname_' . $i],
                        'TypeId' => $postData['unittypeid_' . $i],
                    ));
                } else {
                    $update->set(array(
                        'UnitDescription' => $postData['unitdes_' . $i],
                    ));
                }
                $update->where(array('UnitId' => $postData['unitid_' . $i]));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            }
            $this->redirect()->toRoute('project/default', array('controller' => 'main', 'action' => 'uomregister'));
        }
    }

    public function measurementTemplateAction() {
        if ( !$this->auth->hasIdentity() ) {
            if ( $this->getRequest()->isXmlHttpRequest() ) {
                echo "session-expired";
                exit();
            }
            else {
                $this->redirect()->toRoute( "application/default", array( "controller" => "index", "action" => "index" ) );
            }
        }

        $this->getServiceLocator()->get( "ViewHelperManager" )->get( "HeadTitle" )->set( " Measurement Template" );
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );

        $select = $sql->select();
        $select->from( 'Proj_MeasurementTemplate' )
            ->columns( array( 'TemplateId', 'TemplateName' ) )
            ->where( "DeleteFlag='0'" )
            ->order( 'TemplateId' );
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->templateReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }

    public function deletemeasurementtemplateAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $TemplateId = $this->params()->fromPost('TemplateId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from('Proj_MeasurementTemplate')
                        ->columns(array('TemplateName'))
                        ->where(array('TemplateId' => $TemplateId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $template = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    if(!$template) {
                        $response->setStatusCode(201)->setContent('Failed');
                        return $response;
                    }

                    $update = $sql->update();
                    $update->table('Proj_MeasurementTemplate')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                        ->where(array('TemplateId' => $TemplateId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function addmeasurementtemplateAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $templateName = $this->params()->fromPost('TemplateName');
                    $Description = $this->params()->fromPost('Description');
                    $CellName = $this->params()->fromPost('CellName');
                    $SelectedColumns = $this->params()->fromPost('SelectedColumns');

                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('Proj_MeasurementTemplate');
                    $insert->Values(array('TemplateName' => $templateName,'Description'=>$Description
                    ,'CellName'=> $CellName, 'SelectedColumns' => $SelectedColumns));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $templateId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $select = $sql->select();
                    $select->from( 'Proj_MeasurementTemplate' )
                        ->columns( array( 'TemplateId', 'TemplateName' ))
                        ->where( "TemplateId='$templateId'" );
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $connection->commit();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }
                $response->setContent($status);
                return $response;
            }
        }
    }

    public function updatemeasurementtemplateAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();

                try {
                    $TemplateId = $this->params()->fromPost('TemplateId');
                    $TemplateName = $this->params()->fromPost('TemplateName');
                    $Description = $this->params()->fromPost('Description');
                    $CellName = $this->params()->fromPost('CellName');
                    $SelectedColumns = $this->params()->fromPost('SelectedColumns');

                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('Proj_MeasurementTemplate')
                        ->set(array('TemplateName' => $TemplateName,'Description'=> $Description, 'CellName'=> $CellName
                        , 'SelectedColumns' => $SelectedColumns))
                        ->where(array('TemplateId' => $TemplateId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $select = $sql->select();
                    $select->from( 'Proj_MeasurementTemplate' )
                        ->columns( array( 'TemplateId', 'TemplateName' ))
                        ->where( "TemplateId='$TemplateId'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if($results)
                        return $this->getResponse()->setContent(json_encode($results));

                    return $this->getResponse()->setStatus(201)->setContent('Not Found');

                } catch (PDOException $e) {
                    $connection->rollback();
                    $this->getResponse()->setStatusCode('400');
                }
            }
        }
    }

    public function checkmeasurementtemplateFoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $templateId = $this->params()->fromPost('TemplateId');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($templateId != null){
                        $templateName = $this->params()->fromPost('templateName');
                        $select->from( array( 'c' => 'Proj_MeasurementTemplate' ))
                            ->columns( array( 'TemplateId'))
                            ->where( "TemplateName='$templateName' and TemplateId<> '$templateId' and DeleteFlag=0");
                    } else{
                        $templateName = $this->params()->fromPost('templateNamenew');

                        $select->from( array( 'c' => 'Proj_MeasurementTemplate' ))
                            ->columns( array( 'TemplateId'))
                            ->where( "TemplateName='$templateName' AND DeleteFlag=0");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode(array('results' => $results));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }


    public function getmeasurementtemplateAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);

                $TemplateId = $this->bsf->isNullCheck($this->params()->fromPost('TemplateId'), 'number');
                $select = $sql->select();
                $select->from('Proj_MeasurementTemplate')
                    ->columns( array('Description','CellName', 'SelectedColumns'))
                    ->where( "TemplateId='$TemplateId'" );
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($results)
                    return $this->getResponse()->setContent(json_encode($results));

                return $this->getResponse()->setStatus(201)->setContent('Not Found');
            }
        }
    }

    public function othercostmasterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Other Cost Master");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_OHTypeMaster')
            ->columns(array('data' => 'OHTypeId', 'value' =>'OHTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ohtypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from( array( 'a' => 'Proj_OHMaster' ))
            ->join(array('b' =>'Proj_OHTypeMaster'),'a.OHTypeId=b.OHTypeId',array('OHTypeName'), $select:: JOIN_LEFT)
            ->columns( array( 'OHId', 'OHName', 'OHTypeId' ) )
            ->where('a.DeleteFlag=0')
            ->order( 'a.OHId' );
        $statement = $sql->getSqlStringForSqlObject( $select );
        $this->_view->ohReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function checkothercostfoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $ohId = $this->params()->fromPost('ohId');
                    $ohName = $this->params()->fromPost('ohName');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($ohId != null || $ohId != 0){
                        $select->from( array( 'a' => 'Proj_OHMaster' ))
                            ->columns( array( 'OHId'))
                            ->where( "OHName='$ohName' and OHId<>'$ohId' and DeleteFlag=0");
                    } else{
                        $select->from( array( 'a' => 'Proj_OHMaster' ))
                            ->columns( array( 'OHId'))
                            ->where( "OHName='$ohName' and DeleteFlag=0");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode(array('results' => $results));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }


    public function checkservicefoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $serviceId = $this->params()->fromPost('serviceId');
                    $serviceName = $this->params()->fromPost('serviceName');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($serviceId != null || $serviceId != 0){
                        $select->from( array( 'a' => 'Proj_ServiceMaster' ))
                            ->columns( array( 'ServiceId'))
                            ->where( "ServiceName='$serviceName' and ServiceId<>'$serviceId' and DeleteFlag=0");
                    } else{
                        $select->from( array( 'a' => 'Proj_ServiceMaster' ))
                            ->columns( array( 'ServiceId'))
                            ->where( "ServiceName='$serviceName' and DeleteFlag=0");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode(array('results' => $results));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function checkadminExpensefoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $expenseId = $this->params()->fromPost('expenseId');
                    $expenseName = $this->params()->fromPost('expenseName');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($expenseId  != null || $expenseId  != 0){
                        $select->from( array( 'a' => 'Proj_AdminExpenseMaster' ))
                            ->columns( array( 'ExpenseId'))
                            ->where( "ExpenseName='$expenseName' and ExpenseId<>'$expenseId' and DeleteFlag=0");
                    } else{
                        $select->from( array( 'a' => 'Proj_AdminExpenseMaster' ))
                            ->columns( array( 'ExpenseId'))
                            ->where( "ExpenseName='$expenseName' and DeleteFlag=0");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode(array('results' => $results));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function editothercostAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $ohId = $this->bsf->isNullCheck( $postData['ohId'], 'number' );
                    $ohName = $this->bsf->isNullCheck( $postData['ohName'], 'string' );
                    $ohType = $this->bsf->isNullCheck( $postData['ohType'], 'number' );

                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('Proj_OHMaster');
                    $update->set(array('OHName' => $ohName, 'OHTypeId' => $ohType))
                        ->where("OHId=$ohId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_OHMaster' ))
                        ->join(array('b' =>'Proj_OHTypeMaster'),'a.OHTypeId=b.OHTypeId',array('OHTypeName'), $select:: JOIN_LEFT)
                        ->columns( array( 'OHId', 'OHName', 'OHTypeId' ) )
                        ->where("a.DeleteFlag=0 AND OHId=$ohId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function addothercostAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $ohId = $this->bsf->isNullCheck( $postData['ohId'], 'number' );
                    $ohName = $this->bsf->isNullCheck( $postData['ohName'], 'string' );
                    $ohType = $this->bsf->isNullCheck( $postData['ohType'], 'number' );

                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('Proj_OHMaster');
                    $insert->Values(array('OHName' => $ohName, 'OHTypeId' => $ohType));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $ohId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_OHMaster' ))
                        ->join(array('b' =>'Proj_OHTypeMaster'),'a.OHTypeId=b.OHTypeId',array('OHTypeName'), $select:: JOIN_LEFT)
                        ->columns( array( 'OHId', 'OHName', 'OHTypeId' ) )
                        ->where("a.DeleteFlag=0 AND OHId=$ohId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function deleteothercostAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $OHId = $this->bsf->isNullCheck($this->params()->fromPost('OHId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select1 = $sql->select();
                            $select1->from('Proj_RFCOHTrans')
                                ->columns(array('OHId'))
                                ->where(array('OHId' => $OHId));
                            $statement = $sql->getSqlStringForSqlObject( $select1 );
                            $result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if(count($result) > 0) {
                                return $response->setStatusCode( 201 )->setContent( $status );
                            }

                            return $response->setStatusCode('200')->setContent('Not used');
                            break;
                        case 'update':
                            $connection->beginTransaction();

                            $update = $sql->update();
                            $update->table('Proj_OHMaster')
                                ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                ->where(array('OHId' => $OHId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $connection->commit();

                            $status = 'deleted';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                return $response->setContent($status);
            }
        }
    }

    public function editadminexpenseAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $expenseId = $this->bsf->isNullCheck( $postData['expenseId'], 'number' );
                    $expenseName = $this->bsf->isNullCheck( $postData['expenseName'], 'string' );
                    $account = $this->bsf->isNullCheck( $postData['account'], 'number' );

                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('Proj_AdminExpenseMaster');
                    $update->set(array('ExpenseName' => $expenseName, 'AccountId' => $account))
                        ->where("ExpenseId=$expenseId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_AdminExpenseMaster' ))
                        ->join(array('b' =>'FA_AccountMaster'),'a.AccountId=b.AccountId',array('AccountName'), $select:: JOIN_LEFT)
                        ->columns( array( 'ExpenseId', 'ExpenseName', 'AccountId' ) )
                        ->where("a.DeleteFlag=0 AND ExpenseId=$expenseId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function addadminexpenseAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $expenseId = $this->bsf->isNullCheck( $postData['expenseId'], 'number' );
                    $expenseName = $this->bsf->isNullCheck( $postData['expenseName'], 'string' );
                    $account = $this->bsf->isNullCheck( $postData['account'], 'number' );

                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('Proj_AdminExpenseMaster');
                    $insert->Values(array('ExpenseName' => $expenseName, 'AccountId' => $account));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $expenseId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();


                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_AdminExpenseMaster' ))
                        ->join(array('b' =>'FA_AccountMaster'),'a.AccountId=b.AccountId',array('AccountName'), $select:: JOIN_LEFT)
                        ->columns( array( 'ExpenseId', 'ExpenseName', 'AccountId' ) )
                        ->where("a.DeleteFlag=0 AND ExpenseId=$expenseId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function deleteadminexpenseAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $expenseId = $this->bsf->isNullCheck($this->params()->fromPost('expenseId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select1 = $sql->select();
                            $select1->from('Proj_RFCOHAdminExpenseTrans')
                                ->columns(array('ExpenseId'))
                                ->where(array('ExpenseId' => $expenseId));
                            $statement = $sql->getSqlStringForSqlObject( $select1 );
                            $result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if(count($result) > 0) {
                                return $response->setStatusCode( 201 )->setContent( $status );
                            }

                            return $response->setStatusCode('200')->setContent('Not used');
                            break;
                        case 'update':
                            $connection->beginTransaction();

                            $update = $sql->update();
                            $update->table('Proj_AdminExpenseMaster')
                                ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                ->where(array('ExpenseId' => $expenseId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $connection->commit();

                            $status = 'deleted';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                return $response->setContent($status);
            }
        }
    }



    public function deleteserviceAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                    $serviceId = $this->bsf->isNullCheck($this->params()->fromPost('serviceId'), 'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'), 'string');
                    $response = $this->getResponse();

                    switch($Type) {
                        case 'check':
                            // check for already exists
                            $select1 = $sql->select();
                            $select1->from('Proj_RFCOHServiceTrans')
                                ->columns(array('ServiceId'))
                                ->where(array('ServiceId' => $serviceId));
                            $statement = $sql->getSqlStringForSqlObject( $select1 );
                            $result = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if(count($result) > 0) {
                                return $response->setStatusCode( 201 )->setContent( $status );
                            }

                            return $response->setStatusCode('200')->setContent('Not used');
                            break;
                        case 'update':
                            $connection->beginTransaction();

                            $update = $sql->update();
                            $update->table('Proj_ServiceMaster')
                                ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'Remarks' => $Remarks))
                                ->where(array('ServiceId' => $serviceId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $connection->commit();

                            $status = 'deleted';
                            break;
                    }
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                return $response->setContent($status);
            }
        }
    }

    public function editserviceAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $serviceId = $this->bsf->isNullCheck( $postData['serviceId'], 'number' );
                    $serviceName = $this->bsf->isNullCheck( $postData['serviceName'], 'string' );
                    $serviceType = $this->bsf->isNullCheck( $postData['serviceType'], 'number' );
                    $unit = $this->bsf->isNullCheck( $postData['unit'], 'number' );

                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('Proj_ServiceMaster');
                    $update->set(array('ServiceName' => $serviceName, 'ServiceTypeId' => $serviceType,'UnitId' => $unit))
                        ->where("ServiceId=$serviceId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_ServiceMaster' ))
                        ->join(array('b' =>'Proj_ServiceTypeMaster'),'a.ServiceTypeId=b.ServiceTypeId',array('ServiceTypeName'), $select:: JOIN_LEFT)
                        ->join(array('c' =>'Proj_UOM'),'a.UnitId=c.UnitId',array('UnitName'), $select:: JOIN_LEFT)
                        ->columns( array( 'ServiceId', 'ServiceName', 'ServiceTypeId','UnitId') )
                        ->where("a.DeleteFlag=0 AND ServiceId=$serviceId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function addserviceAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $serviceId = $this->bsf->isNullCheck( $postData['serviceId'], 'number' );
                    $serviceName = $this->bsf->isNullCheck( $postData['serviceName'], 'string' );
                    $serviceType = $this->bsf->isNullCheck( $postData['serviceType'], 'number' );
                    $unit = $this->bsf->isNullCheck( $postData['unit'], 'number' );

                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('Proj_ServiceMaster');
                    $insert->Values(array('ServiceName' => $serviceName, 'ServiceTypeId' => $serviceType,'UnitId'=> $unit));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $serviceId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_ServiceMaster' ))
                        ->join(array('b' =>'Proj_ServiceTypeMaster'),'a.ServiceTypeId=b.ServiceTypeId',array('ServiceTypeName'), $select:: JOIN_LEFT)
                        ->join(array('c' =>'Proj_UOM'),'a.UnitId=c.UnitId',array('UnitName'), $select:: JOIN_LEFT)
                        ->columns( array( 'ServiceId', 'ServiceName', 'ServiceTypeId','UnitId'))
                        ->where("a.DeleteFlag=0 AND ServiceId=$serviceId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }


    public function editprojectworkgroupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $workgroupslno = $this->bsf->isNullCheck( $postData['workgroupslno'], 'string' );
                    $iPWorkGroupId= $this->bsf->isNullCheck( $postData['PWorkGroupId'], 'number' );
                    $sPWorkGroupName= $this->bsf->isNullCheck( $postData['workgroupName'], 'string' );
                    $iWorkGroupId = $this->bsf->isNullCheck( $postData['workgroupid'], 'number' );
                    $worktypeid = $this->bsf->isNullCheck( $postData['worktypeid'], 'number' );

                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('Proj_ProjectWorkGroup');
                    $update->set(array('WorkGroupName' => $sPWorkGroupName, 'WorkGroupId' => $iWorkGroupId,
                        'WorkTypeId' => $worktypeid, 'SerialNo' => $workgroupslno))
                        ->where("PWorkGroupId=$iPWorkGroupId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_ProjectWorkGroup ' ))
                        ->join(array('b' =>'Proj_WorkGroupMaster'),'a.WorkGroupId=b.WorkGroupId',array('WorkGroupName'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=c.WorkTypeId',array('WorkTypeId', 'WorkType'), $select::JOIN_LEFT)
                        ->columns( array('PWorkGroupId','WorkGroupId','SerialNo','ProjectWorkGroup'=> new Expression('a.WorkGroupName'),'SortId'))
                        ->where("PWorkGroupId=$iPWorkGroupId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function checkprojectworkgroupfoundAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $iPWorkGroupId= $this->bsf->isNullCheck( $postData['PWorkGroupId'], 'number' );
                    $sPWorkGroupName= $this->bsf->isNullCheck( $postData['workgroupName'], 'string' );
                    $iProjectId = $this->bsf->isNullCheck( $postData['projectId'], 'number' );

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $select = $sql->select();
                    if($iPWorkGroupId != 0){
                        $select->from( array( 'a' => 'Proj_ProjectWorkGroup' ))
                            ->columns( array( 'PWorkGroupId'))
                            ->where( "WorkGroupName='$sPWorkGroupName' and ProjectId = $iProjectId  and PWorkGroupId<>'$iPWorkGroupId'");
                    } else{
                        $select->from( array( 'a' => 'Proj_ProjectWorkGroup' ))
                            ->columns( array( 'PWorkGroupId'))
                            ->where( "WorkGroupName='$sPWorkGroupName' and ProjectId = $iProjectId");
                    }

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode(array('results' => $results));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function addprojectworkgroupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postData = $request->getPost();
                    $response = $this->getResponse();

                    $workgroupslno = $this->bsf->isNullCheck( $postData['workgroupslno'], 'string' );
                    $sPWorkGroupName= $this->bsf->isNullCheck( $postData['workgroupName'], 'string' );
                    $iWorkGroupId = $this->bsf->isNullCheck( $postData['workgroupid'], 'number' );
                    $worktypeid = $this->bsf->isNullCheck( $postData['worktypeid'], 'number' );
                    $iProjectId = $this->bsf->isNullCheck( $postData['projectId'], 'number' );

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_ProjectWorkGroup ' ))
                        ->where("SerialNo='$workgroupslno' AND ProjectId='$iProjectId'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $workgroupname = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if($workgroupname != FALSE) {
                        $response->setStatusCode('201');
                        $response->setContent('SerialNo already exists!');
                        return $response;
                    }

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_ProjectWorkGroup ' ))
                        ->where("WorkGroupName='$sPWorkGroupName' AND ProjectId='$iProjectId'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $workgroupname = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if($workgroupname != FALSE) {
                        $response->setStatusCode('201');
                        $response->setContent('Project Workgroup Name already exists!');
                        return $response;
                    }
                    $connection->beginTransaction();

                    $insert = $sql->insert();
                    $insert->into('Proj_ProjectWorkGroup');
                    $insert->Values(array('WorkGroupName' => $sPWorkGroupName, 'WorkGroupId' => $iWorkGroupId,'ProjectId' =>$iProjectId,
                        'WorkTypeId' => $worktypeid, 'SerialNo' => $workgroupslno));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $iPWorkGroupId= $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();

                    $select = $sql->select();
                    $select->from( array( 'a' => 'Proj_ProjectWorkGroup ' ))
                        ->join(array('b' =>'Proj_WorkGroupMaster'),'a.WorkGroupId=b.WorkGroupId',array('WorkGroupName'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=c.WorkTypeId',array('WorkTypeId', 'WorkType'), $select::JOIN_LEFT)
                        ->columns( array('PWorkGroupId','WorkGroupId','SerialNo','ProjectWorkGroup'=> new Expression('a.WorkGroupName'),'SortId'))
                        ->where("PWorkGroupId=$iPWorkGroupId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $status = json_encode($results);
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }

                $response->setContent($status);
                return $response;
            }
        }
    }


    public function photoProgressAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $iProjectId=0;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('type'),'string');
                $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'),'number');
                //Load photos
                if($Type == 'date') {

                    //Load Date
                    $select = $sql->select();
                    $select->from('Proj_PhotoProgress')
                        ->columns(array('TransId','ProjectId','Date',"DateFormat" => new Expression("FORMAT(Date, 'dd/MM/yyyy')")))
                        ->where(array('ProjectId'=>$ProjectId))
                        ->order('Date');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $result = json_encode($result);

                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent($result);
                    return $response;

                } else if($Type == 'photo') {
                    $result =  "";
                    $TransId = $this->bsf->isNullCheck($this->params()->fromPost('TransId'),'number');

//                    $dDate =  date('Y-m-d', strtotime($this->params()->fromPost('date')));
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_PhotoProgressFiles'))
                        ->join(array('b' => 'Proj_PhotoProgress'), 'b.TransId=a.PhotoProgressTransId', array(), $select::JOIN_INNER)
                        ->where(array('b.TransId' =>$TransId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $result = json_encode($result);

                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent($result);
                    return $response;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

                $postData = $request->getPost();
                $files = $request->getFiles();

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                $iProjectId = $this->bsf->isNullCheck( $postData['Project'], 'number' );
                $dDate = date('Y-m-d', strtotime( $postData[ 'date' ]));

                $TransId =0;
                $select = $sql->select();
                $select->from('Proj_PhotoProgress')
                    ->columns(array('TransId'))
                    ->where(array('ProjectId'=>$iProjectId,'Date'=>$dDate));
                $statement = $sql->getSqlStringForSqlObject($select);
                $reg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($reg)) $TransId = $reg['TransId'];

                if ($TransId ==0) {
                    $insert = $sql->insert();
                    $insert->into('Proj_PhotoProgress');
                    $insert->Values(array('ProjectId' =>$iProjectId, 'Date' =>$dDate));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                }

                // save photos & video
                foreach ( $files[ 'attachedfiles' ] as $file ) {
                    if ( !$file[ 'name' ] )
                        continue;

                    $dir = 'public/uploads/project/photoprogress/' . $TransId . '/';
                    $filename = $this->bsf->uploadFile( $dir, $file );

                    // update valid files only
                    if ( !$filename )
                        continue;

                    $url = '/uploads/project/photoprogress/' . $TransId . '/' . $filename;

                    $imgExts = array( 'jpeg', 'jpg', 'png' );
                    $videoExts = array( 'mp4' );
                    $ext = pathinfo( $file[ 'name' ], PATHINFO_EXTENSION );
                    if ( in_array( $ext, $imgExts ) )
                        $type = 'image';
                    else if ( in_array( $ext, $videoExts ) )
                        $type = 'video';

                    $insert = $sql->insert();
                    $insert->into( 'Proj_PhotoProgressFiles' );
                    $insert->Values( array( 'PhotoProgressTransId' => $TransId, 'URL' => $url, 'FileType' => $type ) );
                    $statement = $sql->getSqlStringForSqlObject( $insert );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                }

                $connection->commit();
            }

            $this->_view->iProjectId = $iProjectId;

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->where(array('DeleteFlag'=> '0'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->ProjectName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Load Date
            $select = $sql->select();
            $select->from('Proj_PhotoProgress');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Datebox = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Count for each date
            $select = $sql->select();
            $select->from(array('a' => 'Proj_PhotoProgress'))
                ->columns(array('TransId','ProjectId','Date','Count'=>new Expression("Count(a.TransId)")))
                ->join(array('b' => 'Proj_PhotoProgressFiles'), 'a.TransId=b.PhotoProgressTransId', array(), $select::JOIN_INNER)
                ->group(new Expression("a.TransId,a.ProjectId,a.Date"))
                ->order('a.Date');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->DateCount = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $sql = new Sql($dbAdapter);
            // get project list
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'))
                ->where('DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function servicemasterAction(){
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }


            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ServiceTypeMaster')
                ->columns(array('data' => 'ServiceTypeId', 'value' =>'ServiceTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->servicetypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_UOM')
                ->columns(array('data' => 'UnitId', 'value' =>'UnitName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->unit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_ServiceMaster' ))
                ->join(array('b' =>'Proj_ServiceTypeMaster'),'a.ServiceTypeId=b.ServiceTypeId',array('ServiceTypeName'), $select:: JOIN_LEFT)
                ->join(array('c' =>'Proj_UOM'),'a.UnitId=c.UnitId',array('UnitName'), $select:: JOIN_LEFT)
                ->columns( array('ServiceId', 'ServiceName', 'ServiceTypeId','UnitId'))
                ->where('a.DeleteFlag=0')
                ->order( 'a.ServiceName' );
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->serviceReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function adminexpenseAction(){
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('FA_AccountMaster')
                ->columns(array('data' => 'AccountId', 'value' =>'AccountName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->expensetypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_AdminExpenseMaster' ))
                ->join(array('b' =>'FA_AccountMaster'),'a.AccountId=b.AccountId',array('AccountName'), $select:: JOIN_LEFT)
                ->columns( array( 'ExpenseId', 'ExpenseName', 'AccountId' ) )
                ->where('a.DeleteFlag=0')
                ->order( 'a.ExpenseName' );
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->expenseReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();



            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function holidaysettingAction(){
        //$this->layout("layout/layout");

        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $postData = $request->getPost();
                    $sql = new Sql($dbAdapter);

                    $projectId = $postData['projectId'];

                    $delete = $sql->delete('Proj_WeekHoliday')
                        ->where(array('ProjectId'=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete('Proj_Holiday')
                        ->where(array('ProjectId'=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $weekday = $postData['weekday'];
                    foreach($weekday as $value) {
                        $insert = $sql->insert();
                        $insert->into('Proj_WeekHoliday');
                        $insert->Values(array('ProjectId' => $projectId, 'WeekDay' => $value));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $count =  $this->bsf->isNullCheck($postData['hrowid'],'number');
                    for ($i = 1; $i <= $count; $i++) {
                        $hday = $this->bsf->isNullCheck($postData['hdate_'.$i],'date');
                        $note = $this->bsf->isNullCheck($postData['hremarks_'.$i],'string');

                        if ($hday ==""  || $note =="") continue;

                        $insert = $sql->insert();
                        $insert->into('Proj_Holiday');
                        $insert->Values(array('ProjectId' => $projectId, 'HDate' => date('Y-m-d', strtotime($hday)),'Note'=>$note));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();

                    $this->redirect()->toRoute('project/holidaysetting', array('controller' => 'main', 'action' => 'holidaysetting'));

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            $select = $sql->select();
            $select->from('Proj_Holiday')
                ->columns(array('HDate', 'Note'))
                ->where(array('ProjectId' => $iProjectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectholidays = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_WeekHoliday')
                ->columns(array('WeekDay'))
                ->where(array('ProjectId' => $iProjectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $weekdays = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $wdays = array();
            foreach ($weekdays as $value)
            {
                array_push($wdays, $value['WeekDay']);
            }

            $this->_view->projectId = $iProjectId;
            $this->_view->projectweekdays= $wdays;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function projectworkgroupAction(){
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" || Project Workgroup");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sProjType =  $this->bsf->isNullCheck($this->params()->fromRoute('projecttype'),'string');
            if ($sProjType == "") $sProjType="B";

            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));

            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_WorkGroupMaster'))
                ->join(array('b' => 'Proj_WorkTypeMaster'), 'a.WorkTypeId=b.WorkTypeId',array('WorkTypeId', 'WorkType'), $select::JOIN_LEFT)
                ->columns(array('data' => 'WorkGroupId', 'value' =>'WorkGroupName'))
                ->where(array('DeleteFlag' => 0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->wgmaster= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_WorkTypeMaster'))
                ->columns(array('data' => 'WorkTypeId', 'value' =>'WorkType'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_worktype= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_ProjectWorkGroup ' ))
                ->join(array('b' =>'Proj_WorkGroupMaster'),'a.WorkGroupId=b.WorkGroupId',array('WorkGroupName'), $select:: JOIN_LEFT)
                ->join(array('c' =>'Proj_WorkTypeMaster'),'a.WorkTypeId=c.WorkTypeId',array('WorkTypeId', 'WorkType'), $select:: JOIN_LEFT)
                ->columns( array('PWorkGroupId','WorkGroupId','SerialNo','ProjectWorkGroup'=> new Expression('a.WorkGroupName'),'SortId'))
                ->where(array('a.ProjectId'=>$iProjectId))
                ->order( 'a.SortId');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->projectwg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $this->_view->projectId = $iProjectId;
            $this->_view->projectType = $sProjType;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function projectiowsortorderAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" || Project BOQ");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $this->_view->setTerminal(true);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $statusCode = 200;
                $result = 'No Data.';
                try {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $postData = $request->getPost();
                    $type = $this->bsf->isNullCheck($postData['type'], 'string');
                    switch($type) {
                        case 'workgroups':
                            $connection->beginTransaction();
                            $iRowId = $this->bsf->isNullCheck($postData['wgrowid'], 'number');
                            for ($i = 1; $i <= $iRowId; $i++) {
                                $iWGId = $this->bsf->isNullCheck($postData['pworkgroupid_'.$i], 'number');
                                $iSortId = $this->bsf->isNullCheck($postData['wsortid_'.$i], 'number');

                                $update = $sql->update();
                                $update->table('Proj_ProjectWorkGroup');
                                $update->set(array('SortId' => $iSortId));
                                $update->where(array('PWorkGroupId' => $iWGId));

                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $connection->commit();
                            break;
                        case 'iows':
                            $connection->beginTransaction();
                            $iRowId = $this->bsf->isNullCheck($postData['iowrowid'], 'number');
                            for ($i = 1; $i <= $iRowId; $i++) {
                                $iProjectIOWId = $this->bsf->isNullCheck($postData['projectiowid_'.$i], 'number');
                                $iSortId = $this->bsf->isNullCheck($postData['sortid_'.$i], 'number');

                                $update = $sql->update();
                                $update->table('Proj_ProjectIOWMaster');
                                $update->set(array('SortId' => $iSortId));
                                $update->where(array('ProjectIOWId' => $iProjectIOWId));

                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $connection->commit();

                            $result = 'success';
                            break;
                        case 'getiows':
                            $iProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number');
                            $PWorkGroupId = $this->bsf->isNullCheck($postData['PWorkGroupId'], 'number');
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Proj_ProjectIOWMaster' ))
                                ->columns( array('PWorkGroupId','ProjectIOWId','SerialNo','Specification','SortId'))
                                ->where(array('a.ProjectId'=>$iProjectId, 'a.PWorkGroupId' => $PWorkGroupId))
                                ->order( 'a.SortId');
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $iowlist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            $result = json_encode($iowlist);
                            break;
                        default:
                            $statusCode = 401;
                            break;
                    }
                } catch(PDOException $ex) {
                    $connection->rollback();
                    $statusCode = 400;
                }

                $response = $this->getResponse()
                    ->setStatusCode($statusCode)
                    ->setContent($result);
                return $response;
            }
        } else {
            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sProjType =  $this->bsf->isNullCheck($this->params()->fromRoute('projecttype'),'string');
            if ($sProjType =="") $sProjType="B";

            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from( array( 'a' => 'Proj_ProjectWorkGroup' ))
                ->columns( array('PWorkGroupId','SerialNo','WorkGroupName','SortId'))
                ->where(array('a.ProjectId'=>$iProjectId))
                ->order( 'a.SortId');
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->wglist = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            $this->_view->projectId = $iProjectId;
            $this->_view->projectType = $sProjType;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function wbsmasterAction(){
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }


            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectId'),'number');

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function othercostAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Other Cost Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $post_params = $request->getPost();
                $project_id = $this->bsf->isNullCheck($post_params['project_id'], 'number');
                $type = $this->bsf->isNullCheck($post_params['type_name'], 'string');

                $select = $sql->select();
                $select->from('Proj_OHTypeMaster')
                    ->columns(array('data' => 'OHTypeId', 'value' =>'OHTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $ohtypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $arr_ohtrans = array();
                foreach($ohtypes as $otrans) {
                    $OHTypeId = $otrans['data'];
                    // get rfc oh trans
                    $select = $sql->select();
                    if ($type=="P")  $select->from(array('a'=>'Proj_OHAbstractPlan'));
                    else $select->from( array('a'=>'Proj_OHAbstract'));
                    $select->join( array( 'b' => 'Proj_OHMaster' ), 'a.OHId=b.OHId', array( 'OHName', 'OHTypeId' ), $select::JOIN_LEFT )
                        ->join( array( 'c' => 'Proj_OHTypeMaster' ), 'b.OHTypeId=c.OHTypeId', array( 'OHTypeName' ), $select::JOIN_LEFT )
                        ->where('a.ProjectId='.$project_id.' AND b.OHTypeId='.$OHTypeId);
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $ohtrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    if($OHTypeId == '9' || $OHTypeId == '10') {
                        $arr_ohtrans = array_merge($arr_ohtrans, $ohtrans);
                        continue;
                    }

                    // get rfc type trans
                    foreach ( $ohtrans as &$trans ) {
                        switch($OHTypeId) {
                            case '1':
                                //oh item trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHItemPlan'));
                                else $select->from( array('a'=>'Proj_OHItem'));
                                $select->join( array( 'b' => 'Proj_ProjectIOWMaster' ), 'a.ProjectIOWId=b.ProjectIOWId', array( 'DescTypeId' => 'ProjectIOWId','Desc' =>new Expression("SerialNo + ' ' + Specification") ), $select::JOIN_LEFT )
                                    ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'TransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                break;
                            case '2':
                                //oh material trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHMaterialPlan'));
                                else $select->from( array('a'=>'Proj_OHMaterial'));
                                $select->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array( 'DescTypeId' => 'ResourceId','Desc' =>'ResourceName' ), $select::JOIN_LEFT )
                                    ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'TransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                break;
                            case '3':
                                //oh labour trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHLabourPlan'));
                                else $select->from( array('a'=>'Proj_OHLabour'));
                                $select->join( array( 'b' => 'Proj_Resource' ), 'a.ResourceId=b.ResourceId', array(  'DescTypeId' => 'ResourceId','Desc' =>'ResourceName'  ), $select::JOIN_LEFT )
                                    ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'TransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                break;
                            case '4':
                                //oh service trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHServicePlan'));
                                else $select->from( array('a'=>'Proj_OHService'));
                                $select->join( array( 'b' => 'Proj_ServiceMaster' ), 'a.ServiceId=b.ServiceId', array( 'DescTypeId' => 'ServiceId', 'Desc' => 'ServiceName' ), $select::JOIN_LEFT )
                                    ->join( array( 'c' => 'Proj_UOM' ), 'b.UnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'TransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                break;
                            case '5':
                                //oh machinery trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHMachineryPlan'));
                                else $select->from( array('a'=>'Proj_OHMachinery'));
                                $select->join( array( 'b' => 'Proj_Resource' ), 'a.MResourceId=b.ResourceId', array( 'ResourceName' ), $select::JOIN_LEFT )
                                    ->join( array( 'c' => 'Proj_UOM' ), 'b.WorkUnitId=c.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'MachineryTransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                // material details trans
                                foreach($typetrans as &$typedetailtrans) {
                                    $select = $sql->select();
                                    if ($type=="P") $select->from(array('a' =>'Proj_OHMachineryDetailsPlan'));
                                    else $select->from( array('a'=>'Proj_OHMachineryDetails'));
                                    $select->join( array( 'b' => 'Proj_ProjectIOWMaster' ), 'a.ProjectIOWId=b.ProjectIOWId', array( 'Name' => new Expression("SerialNo + ' ' + Specification") ), $select::JOIN_LEFT )
                                        ->where( 'a.MachineryTransId=' .$typedetailtrans[ 'MachineryTransId' ] );
                                    $statement = $sql->getSqlStringForSqlObject( $select );
                                    $rfcmdetailstrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                                    $typedetailtrans['details'] = $rfcmdetailstrans;
                                }
                                break;
                            case '6':
                                //oh admin expense trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHAdminExpensePlan'));
                                else $select->from( array('a'=>'Proj_OHAdminExpense'));
                                $select->join( array( 'b' => 'Proj_AdminExpenseMaster' ), 'a.ExpenseId=b.ExpenseId', array( 'DescTypeId' => 'ExpenseId', 'Desc' => 'ExpenseName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'TransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                break;
                            case '7':
                                //oh salary trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHSalaryPlan'));
                                else $select->from( array('a'=>'Proj_OHSalary'));
                                $select->join( array( 'b' => 'WF_PositionMaster' ), 'a.PositionId=b.PositionId', array( 'DescTypeId' => 'PositionId', 'Desc' => 'PositionName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'TransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                break;
                            case '8':
                                //oh fuel trans
                                $select = $sql->select();
                                if ($type=="P") $select->from(array('a' =>'Proj_OHFuelPlan'));
                                else $select->from( array('a'=>'Proj_OHFuel'));
                                $select->join( array( 'b' => 'Proj_Resource' ), 'a.MResourceId=b.ResourceId', array( 'DescTypeId' => 'ResourceId', 'Desc' => 'ResourceName' ), $select::JOIN_LEFT )
                                    ->join( array( 'c' => 'Proj_Resource' ), 'a.FResourceId=c.ResourceId', array( 'FuelId' => 'ResourceId', 'Fuel' => 'ResourceName' ), $select::JOIN_LEFT )
                                    ->join( array( 'd' => 'Proj_UOM' ), 'c.UnitId=d.UnitId', array( 'UnitId', 'UnitName' ), $select::JOIN_LEFT )
                                    ->columns(array('RFCTypeTransId' => 'TransId', '*'))
                                    ->where( 'a.OHAbsId=' .$trans[ 'OHAbsId' ] );
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $typetrans = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                break;
                        }

                        if(count($typetrans))
                            $trans['typeTrans'] = $typetrans;
                    }

                    $arr_ohtrans = array_merge($arr_ohtrans, $ohtrans);
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array_reverse($arr_ohtrans)));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {

            }

            // get project list
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'))
                ->where('DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function iowmasterviewAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Project BOQ");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $wgid= $this->params()->fromRoute('wgid');
        $this->_view->gpage = $this->params()->fromRoute('page');

        if($wgid == '') $wgid ='0';

//        if($wgid == '') {
//            $this->redirect()->toRoute('project/iowmasterview', array('controller' => 'main', 'action' => 'iowmasterview'));
//        }
        $this->_view->wgid = $wgid;

        $sessionProjBoqSearch = new Container('sessionProjBoqSearch');

        if($this->_view->gpage == '') {
            $sessionProjBoqSearch->search = array();
        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $sessionProjBoqSearch->search = $postData;

            $select = $sql->select();
            $select->from('Proj_WorkGroupMaster');
            if ($postData['wgid'] != '' && $postData['wgid'] != '0') $select->where('WorkGroupId= ' . $postData['wgid']);
            $statement = $sql->getSqlStringForSqlObject($select);
            $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $projboq = array();
            foreach($projWGRes as $pwg) {
                if($postData['serialNo'] != '' || $postData['specification'] != '') {
                    if($postData['serialNo'] == $pwg['SerialNo'] || stristr($postData['specification'],$pwg['WorkGroupName'])) {
                        $projboq['Type'][] = '1';
                        $projboq['SerialNo'][] = $pwg['SerialNo'];
                        $projboq['Name'][] = $pwg['WorkGroupName'];
                        $projboq['Unit'][] = '';
                        $projboq['Rate'][] = '';
                        $projboq['IOWId'][] = '';
                        $projboq['Header'][] = '1';
                    }
                }

                $select = $sql->select();
                $select->from(array('a' => 'Proj_IOWMaster'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId','Header'))
                    ->where(array('a.WorkGroupId' => $pwg['WorkGroupId']))
                    ->where(array('a.DeleteFlag' => 0))
                    ->order('a.SlNo');
                if($postData['serialNo'] != '') {
                    $select->where->like('a.SerialNo', "%".$postData['serialNo']."%");
                }
                if($postData['specification'] != '') {
                    $select->where->like('a.Specification', "%".$postData['specification']."%");
                }
                if($postData['rate'] != '') {
                    $select->where(array('a.Rate' => $postData['rate']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach($projIowPRes as $piowp) {
                    $projboq['Type'][] = '2';
                    $projboq['SerialNo'][] = $piowp['SerialNo'];
                    $projboq['Name'][] = $piowp['Specification'];
                    $projboq['Unit'][] = $piowp['UnitName'];
                    $projboq['Rate'][] = $piowp['Rate'];
                    $projboq['IOWId'][] = $piowp['IOWId'];
                    $projboq['Header'][] = $piowp['Header'];
                }
            }
            $this->_view->projboq = $projboq;
        } else {
            if(count($sessionProjBoqSearch->search) > 0) {
                $select = $sql->select();
                $select->from('Proj_WorkGroupMaster');
                if ($sessionProjBoqSearch->search['wgid'] != '' && $sessionProjBoqSearch->search['wgid'] != '0') $select->where('WorkGroupId= ' . $sessionProjBoqSearch->search['wgid']);

                $statement = $sql->getSqlStringForSqlObject($select);
                $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $projboq = array();
                foreach($projWGRes as $pwg) {
                    if($sessionProjBoqSearch->search['serialNo'] != '' || $sessionProjBoqSearch->search['specification'] != '') {
                        if($sessionProjBoqSearch->search['serialNo'] == $pwg['SerialNo'] || stristr($sessionProjBoqSearch->search['specification'],$pwg['WorkGroupName'])) {
                            $projboq['Type'][] = '1';
                            $projboq['SerialNo'][] = $pwg['SerialNo'];
                            $projboq['Name'][] = $pwg['WorkGroupName'];
                            $projboq['Unit'][] = '';
                            $projboq['Rate'][] = '';
                            $projboq['IOWId'][] = '';
                            $projboq['Header'][] = '1';

                        }
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_IOWMaster'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId','Header'))
                        ->where(array('a.WorkGroupId' => $pwg['WorkGroupId']))
                        ->where(array('a.DeleteFlag' => 0))
                        ->order('a.SlNo');
                    if($sessionProjBoqSearch->search['serialNo'] != '') {
                        $select->where->like('a.SerialNo', "%".$sessionProjBoqSearch->search['serialNo']."%");
                    }
                    if($sessionProjBoqSearch->search['specification'] != '') {
                        $select->where->like('a.Specification', "%".$sessionProjBoqSearch->search['specification']."%");
                    }
                    if($sessionProjBoqSearch->search['rate'] != '') {
                        $select->where(array('a.Rate' => $sessionProjBoqSearch->search['rate']));
                    }
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($projIowPRes as $piowp) {
                        $projboq['Type'][] = '2';
                        $projboq['SerialNo'][] = $piowp['SerialNo'];
                        $projboq['Name'][] = $piowp['Specification'];
                        $projboq['Unit'][] = $piowp['UnitName'];
                        $projboq['Rate'][] = $piowp['Rate'];
                        $projboq['IOWId'][] = $piowp['IOWId'];
                        $projboq['Header'][] = $piowp['Header'];
                    }
                }
                $this->_view->projboq = $projboq;
            } else {
                $sessionProjBoqSearch->search = array();
                // General
                $select = $sql->select();
                $select->from('Proj_WorkGroupMaster');
                if ($wgid != '' && $wgid != '0') $select->where('WorkGroupId = ' . $wgid);
                $statement = $sql->getSqlStringForSqlObject($select);
                $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $projboq = array();
                foreach($projWGRes as $pwg) {
                    $projboq['Type'][] = '1';
                    $projboq['SerialNo'][] = $pwg['SerialNo'];
                    $projboq['Name'][] = $pwg['WorkGroupName'];
                    $projboq['Unit'][] = '';
                    $projboq['Rate'][] = '';
                    $projboq['IOWId'][] = '';
                    $projboq['Header'][] = '1';


                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_IOWMaster'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId','Header'))
                        ->where(array('a.WorkGroupId' => $pwg['WorkGroupId']))
                        ->where(array('a.DeleteFlag' => 0))
                        ->order('a.SlNo');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($projIowPRes as $piowp) {
                        $projboq['Type'][] = '2';
                        $projboq['SerialNo'][] = $piowp['SerialNo'];
                        $projboq['Name'][] = $piowp['Specification'];
                        $projboq['Unit'][] = $piowp['UnitName'];
                        $projboq['Rate'][] = $piowp['Rate'];
                        $projboq['IOWId'][] = $piowp['IOWId'];
                        $projboq['Header'][] = $piowp['Header'];

//                        $select = $sql->select();
//                        $select->from(array('a' => 'Proj_IOWMaster'))
//                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
//                            ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId'))
//                            ->where(array('a.WorkGroupId' => $pwg['WorkGroupId'], 'a.ParentId' => $piowp['IOWId']))
//                            ->where(array('a.DeleteFlag' => 0));
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $projIowCRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                        foreach($projIowCRes as $piowc) {
//                            $projboq['Type'][] = '3';
//                            $projboq['SerialNo'][] = $piowc['SerialNo'];
//                            $projboq['Name'][] = $piowc['Specification'];
//                            $projboq['Unit'][] = $piowc['UnitName'];
//                            $projboq['Rate'][] = $piowc['Rate'];
//                            $projboq['IOWId'][] = $piowc['IOWId'];
//                        }
                    }
                }
                $this->_view->projboq = $projboq;
            }
        }

        // For Search
        $select = $sql->select();
        $select->from('Proj_WorkGroupMaster');
        if ($wgid != '' && $wgid != '0') $select->where('WorkGroupId = ' . $wgid);

        $statement = $sql->getSqlStringForSqlObject($select);
        $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $searchboq = array();
        foreach($projWGRes as $pwg) {
            $searchboq['SerialNo'][] = $pwg['SerialNo'];
            $searchboq['Name'][] = $pwg['WorkGroupName'];

            $select = $sql->select();
            $select->from(array('a' => 'Proj_IOWMaster'))
                ->where(array('a.WorkGroupId' => $pwg['WorkGroupId']));
            $statement = $sql->getSqlStringForSqlObject($select);
            $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach($projIowPRes as $piowp) {
                $searchboq['SerialNo'][] = $piowp['SerialNo'];
                $searchboq['Name'][] = $piowp['Specification'];
            }
        }

        $arrSerialNos = array();
        $arrSpecifications = array();
        if(!empty($searchboq)) {
            for($i=0;$i<count($searchboq['SerialNo']);$i++) {
                $arrSerialNos[] = $searchboq['SerialNo'][$i];
                $arrSpecifications[] = $searchboq['Name'][$i];
            }
        }
        $this->_view->serialNos = $arrSerialNos;
        $this->_view->specifications = $arrSpecifications;
        $this->_view->search = $sessionProjBoqSearch->search;
        // For Search

        // project lists
        $select = $sql->select();
        $select->from('Proj_WorkGroupMaster')
            ->where(array('DeleteFlag' => 0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->wglists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function wbsviewAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $connection->commit();
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function rgcodesetupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Code Setup");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();

            $igentype = ($postData['gentype'] == 'manual') ? 0 : 1;
            $ptype = (isset($postData['ptype'])) ? 1 : 0;
            $pgroup = (isset($postData['pgroup'])) ? 1 : 0;

            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                if ($postData['codefound'] == 1) {
                    $update = $sql->update();
                    $update->table('Proj_RGCodeSetup');
                    $update->set(array(
                        'GenType' => $igentype, 'Prefix' => $postData['prefix'], 'PType' => $ptype, 'PGroup' => $pgroup, 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'GroupLevel' => $postData['grouplevel'], 'CountLevel' => $postData['countlevel'], 'Separator' => $postData['separator']
                    ));

                    $statement = $sql->getSqlStringForSqlObject($update);
                } else {
                    $insert = $sql->insert();
                    $insert->into('Proj_RGCodeSetup');
                    $insert->Values(array('GenType' => $igentype, 'Prefix' => $postData['prefix'], 'PType' => $ptype, 'PGroup' => $pgroup, 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'GroupLevel' => $postData['grouplevel'], 'CountLevel' => $postData['countlevel'], 'Separator' => $postData['separator']));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                }
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $connection->commit();
                $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));

            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }
        }

        $select = $sql->select();
        $select->from('Proj_RGCodeSetup');
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->code = $code;
        $this->_view->codefound = (!empty($code)) ? 1 : 0;

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function restypecodeAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Code Setup");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();

            $slabour = $this->bsf->isNullCheck($postData['labourcode'],'string');
            $smaterial = $this->bsf->isNullCheck($postData['materialcode'],'string');
            $sasset= $this->bsf->isNullCheck($postData['assetcode'],'string');
            $sactvity= $this->bsf->isNullCheck($postData['activitycode'],'string');
            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $connection->beginTransaction();
                $update = $sql->update();
                $update->table('Proj_ResourceType');
                $update->set(array(
                    'TypeCode' => $slabour
                ));
                $update->where(array('TypeId' => 1));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_ResourceType');
                $update->set(array(
                    'TypeCode' => $smaterial
                ));
                $update->where(array('TypeId' => 2));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_ResourceType');
                $update->set(array(
                    'TypeCode' => $sasset
                ));
                $update->where(array('TypeId' => 3));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Proj_ResourceType');
                $update->set(array(
                    'TypeCode' => $sactvity
                ));
                $update->where(array('TypeId' => 4));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $connection->commit();
                $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));

            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }
        }

        $labourcode="";
        $materialcode="";
        $assetcode="";
        $activitycode="";

        $select = $sql->select();
        $select->from('Proj_ResourceType')
            ->columns(array('TypeId','TypeCode'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach ($code as $trans) {
            $itypeid = $trans['TypeId'];
            $stypecode = $trans['TypeCode'];
            if ($itypeid == 1) $labourcode = $stypecode;
            if ($itypeid == 2) $materialcode = $stypecode;
            if ($itypeid == 3) $assetcode = $stypecode;
            if ($itypeid == 4) $activitycode = $stypecode;
        }

        $this->_view->labourcode = $labourcode;
        $this->_view->materialcode = $materialcode;
        $this->_view->assetcode = $assetcode;
        $this->_view->activitycode = $activitycode;

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }


    public function iowmasterprintAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Project BOQ");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $wgid= $this->params()->fromRoute('wgid');
        $this->_view->gpage = $this->params()->fromRoute('page');

        if($wgid == '') $wgid ='0';

//        if($wgid == '') {
//            $this->redirect()->toRoute('project/iowmasterview', array('controller' => 'main', 'action' => 'iowmasterview'));
//        }
        $this->_view->wgid = $wgid;

        $sessionProjBoqSearch = new Container('sessionProjBoqSearch');

        if($this->_view->gpage == '') {
            $sessionProjBoqSearch->search = array();
        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $sessionProjBoqSearch->search = $postData;

            $select = $sql->select();
            $select->from('Proj_WorkGroupMaster');
            if ($postData['wgid'] != '' && $postData['wgid'] != '0') $select->where('WorkGroupId= ' . $postData['wgid']);
            $statement = $sql->getSqlStringForSqlObject($select);
            $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $projboq = array();
            foreach($projWGRes as $pwg) {
                if($postData['serialNo'] != '' || $postData['specification'] != '') {
                    if($postData['serialNo'] == $pwg['SerialNo'] || stristr($postData['specification'],$pwg['WorkGroupName'])) {
                        $projboq['Type'][] = '1';
                        $projboq['SerialNo'][] = $pwg['SerialNo'];
                        $projboq['Name'][] = $pwg['WorkGroupName'];
                        $projboq['Unit'][] = '';
                        $projboq['Rate'][] = '';
                        $projboq['IOWId'][] = '';
                    }
                }

                $select = $sql->select();
                $select->from(array('a' => 'Proj_IOWMaster'))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId'))
                    ->where(array('a.WorkGroupId' => $pwg['WorkGroupId']))
                    ->where(array('a.DeleteFlag' => 0));
                if($postData['serialNo'] != '') {
                    $select->where->like('a.SerialNo', "%".$postData['serialNo']."%");
                }
                if($postData['specification'] != '') {
                    $select->where->like('a.Specification', "%".$postData['specification']."%");
                }
                if($postData['rate'] != '') {
                    $select->where(array('a.Rate' => $postData['rate']));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach($projIowPRes as $piowp) {
                    if($piowp['ParentId'] == 0) {
                        $projboq['Type'][] = '2';
                    } else if($piowp['ParentId'] != 0) {
                        $projboq['Type'][] = '3';
                    }
                    $projboq['SerialNo'][] = $piowp['SerialNo'];
                    $projboq['Name'][] = $piowp['Specification'];
                    $projboq['Unit'][] = $piowp['UnitName'];
                    $projboq['Rate'][] = $piowp['Rate'];
                    $projboq['IOWId'][] = $piowp['IOWId'];
                }
            }
            $this->_view->projboq = $projboq;
        } else {
            if(count($sessionProjBoqSearch->search) > 0) {
                $select = $sql->select();
                $select->from('Proj_WorkGroupMaster');
                if ($sessionProjBoqSearch->search['wgid'] != '' && $sessionProjBoqSearch->search['wgid'] != '0') $select->where('WorkGroupId= ' . $sessionProjBoqSearch->search['wgid']);

                $statement = $sql->getSqlStringForSqlObject($select);
                $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $projboq = array();
                foreach($projWGRes as $pwg) {
                    if($sessionProjBoqSearch->search['serialNo'] != '' || $sessionProjBoqSearch->search['specification'] != '') {
                        if($sessionProjBoqSearch->search['serialNo'] == $pwg['SerialNo'] || stristr($sessionProjBoqSearch->search['specification'],$pwg['WorkGroupName'])) {
                            $projboq['Type'][] = '1';
                            $projboq['SerialNo'][] = $pwg['SerialNo'];
                            $projboq['Name'][] = $pwg['WorkGroupName'];
                            $projboq['Unit'][] = '';
                            $projboq['Rate'][] = '';
                            $projboq['IOWId'][] = '';
                        }
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_IOWMaster'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId'))
                        ->where(array('a.WorkGroupId' => $pwg['WorkGroupId']))
                        ->where(array('a.DeleteFlag' => 0));
                    if($sessionProjBoqSearch->search['serialNo'] != '') {
                        $select->where->like('a.SerialNo', "%".$sessionProjBoqSearch->search['serialNo']."%");
                    }
                    if($sessionProjBoqSearch->search['specification'] != '') {
                        $select->where->like('a.Specification', "%".$sessionProjBoqSearch->search['specification']."%");
                    }
                    if($sessionProjBoqSearch->search['rate'] != '') {
                        $select->where(array('a.Rate' => $sessionProjBoqSearch->search['rate']));
                    }
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($projIowPRes as $piowp) {
                        if($piowp['ParentId'] == 0) {
                            $projboq['Type'][] = '2';
                        } else if($piowp['ParentId'] != 0) {
                            $projboq['Type'][] = '3';
                        }
                        $projboq['SerialNo'][] = $piowp['SerialNo'];
                        $projboq['Name'][] = $piowp['Specification'];
                        $projboq['Unit'][] = $piowp['UnitName'];
                        $projboq['Rate'][] = $piowp['Rate'];
                        $projboq['IOWId'][] = $piowp['IOWId'];
                    }
                }
                $this->_view->projboq = $projboq;
            } else {
                $sessionProjBoqSearch->search = array();
                // General
                $select = $sql->select();
                $select->from('Proj_WorkGroupMaster');
                if ($wgid != '' && $wgid != '0') $select->where('WorkGroupId = ' . $wgid);
                $statement = $sql->getSqlStringForSqlObject($select);
                $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $projboq = array();
                foreach($projWGRes as $pwg) {
                    $projboq['Type'][] = '1';
                    $projboq['SerialNo'][] = $pwg['SerialNo'];
                    $projboq['Name'][] = $pwg['WorkGroupName'];
                    $projboq['Unit'][] = '';
                    $projboq['Rate'][] = '';
                    $projboq['IOWId'][] = '';

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_IOWMaster'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId'))
                        ->where(array('a.WorkGroupId' => $pwg['WorkGroupId'], 'a.ParentId' => 0))
                        ->where(array('a.DeleteFlag' => 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($projIowPRes as $piowp) {
                        $projboq['Type'][] = '2';
                        $projboq['SerialNo'][] = $piowp['SerialNo'];
                        $projboq['Name'][] = $piowp['Specification'];
                        $projboq['Unit'][] = $piowp['UnitName'];
                        $projboq['Rate'][] = $piowp['Rate'];
                        $projboq['IOWId'][] = $piowp['IOWId'];

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_IOWMaster'))
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId = b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                            ->columns(array('SerialNo','Specification','Rate','IOWId','ParentId'))
                            ->where(array('a.WorkGroupId' => $pwg['WorkGroupId'], 'a.ParentId' => $piowp['IOWId']))
                            ->where(array('a.DeleteFlag' => 0));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $projIowCRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($projIowCRes as $piowc) {
                            $projboq['Type'][] = '3';
                            $projboq['SerialNo'][] = $piowc['SerialNo'];
                            $projboq['Name'][] = $piowc['Specification'];
                            $projboq['Unit'][] = $piowc['UnitName'];
                            $projboq['Rate'][] = $piowc['Rate'];
                            $projboq['IOWId'][] = $piowc['IOWId'];
                        }
                    }
                }
                $this->_view->projboq = $projboq;
            }
        }

        // For Search
        $select = $sql->select();
        $select->from('Proj_WorkGroupMaster');
        if ($wgid != '' && $wgid != '0') $select->where('WorkGroupId = ' . $wgid);

        $statement = $sql->getSqlStringForSqlObject($select);
        $projWGRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $searchboq = array();
        foreach($projWGRes as $pwg) {
            $searchboq['SerialNo'][] = $pwg['SerialNo'];
            $searchboq['Name'][] = $pwg['WorkGroupName'];

            $select = $sql->select();
            $select->from(array('a' => 'Proj_IOWMaster'))
                ->where(array('a.WorkGroupId' => $pwg['WorkGroupId']));
            $statement = $sql->getSqlStringForSqlObject($select);
            $projIowPRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach($projIowPRes as $piowp) {
                $searchboq['SerialNo'][] = $piowp['SerialNo'];
                $searchboq['Name'][] = $piowp['Specification'];
            }
        }

        $arrSerialNos = array();
        $arrSpecifications = array();
        if(!empty($searchboq)) {
            for($i=0;$i<count($searchboq['SerialNo']);$i++) {
                $arrSerialNos[] = $searchboq['SerialNo'][$i];
                $arrSpecifications[] = $searchboq['Name'][$i];
            }
        }
        $this->_view->serialNos = $arrSerialNos;
        $this->_view->specifications = $arrSpecifications;
        $this->_view->search = $sessionProjBoqSearch->search;
        // For Search

        // project lists
        $select = $sql->select();
        $select->from('Proj_WorkGroupMaster')
            ->where(array('DeleteFlag' => 0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->wglists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function worktypecodesetupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {

                $postData = $request->getPost();

                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $connection->beginTransaction();
                    $sql = new Sql($dbAdapter);
                    $iRowId = $this->bsf->isNullCheck($postData['rowid'], 'number');
                    for ($i = 1; $i <= $iRowId; $i++) {
                        $sCode = $this->bsf->isNullCheck($postData['code_' . $i], 'string');
                        $sOldCode = $this->bsf->isNullCheck($postData['oldcode_' . $i], 'string');
                        if ($sCode != $sOldCode) {
                            $iTypeId = $this->bsf->isNullCheck($postData['worktypeid_' . $i], 'number');
                            $update = $sql->update();
                            $update->table('Proj_WorkTypeMaster');
                            $update->set(array(
                                'Code' => $sCode
                            ));
                            $update->where(array('WorkTypeId' => $iTypeId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $connection->commit();
                    $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400');
                }


                //Write your Normal form post code here


            }


            $select = $sql->select();
            $select->from('Proj_WorkTypeMaster')
                ->columns(array('WorkTypeId','WorkType','Code'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $wotype = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->wotype = $wotype;

            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function workgroupcodesetupAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Code Setup");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();

            $igentype = ($postData['gentype'] == 'manual') ? 0 : 1;
            $ptype = (isset($postData['ptype'])) ? 1 : 0;
            $pgroup = (isset($postData['pgroup'])) ? 1 : 0;

            $connection = $dbAdapter->getDriver()->getConnection();
            try {
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);
                if ($postData['codefound'] == 1) {
                    $update = $sql->update();
                    $update->table('Proj_WorkGroupCodeSetup');
                    $update->set(array(
                        'GenType' => $igentype, 'Prefix' => $postData['prefix'], 'PType' => $ptype, 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'Separator' => $postData['separator']
                    ));

                    $statement = $sql->getSqlStringForSqlObject($update);
                } else {
                    $insert = $sql->insert();
                    $insert->into('Proj_WorkGroupCodeSetup');
                    $insert->Values(array('GenType' => $igentype, 'Prefix' => $postData['prefix'], 'PType' => $ptype, 'Suffix' => $postData['suffix'], 'width' => $postData['width'], 'Separator' => $postData['separator']));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                }
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $connection->commit();
                $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));

            } catch (PDOException $e) {
                $connection->rollback();
                $response->setStatusCode('400');
            }
        }

        $select = $sql->select();
        $select->from('Proj_WorkGroupCodeSetup');
        $statement = $sql->getSqlStringForSqlObject($select);
        $code = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->code = $code;
        $this->_view->codefound = (!empty($code)) ? 1 : 0;

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function wbsscheduleviewAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $connection->commit();
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function revisionmasterAction(){
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }


            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectId'),'number');

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function copyprojectboqAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
                $iFormProjectId = $this->bsf->isNullCheck($postData['fromprojectId'], 'number');
                $iToProjectId = $this->bsf->isNullCheck($postData['toprojectId'], 'number');
                $bQty = isset($postData['checkQty']) ? 1 : 0;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $update = $sql->update();
                    $update->table('Proj_ProjectWorkGroup');
                    $update->set(array('CopyId' => 0));
                    $update->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Proj_WBSMaster');
                    $update->set(array('CopyId' => 0));
                    $update->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Proj_ProjectIOWMaster');
                    $update->set(array('CopyId' => 0));
                    $update->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $select = $sql->select();
                    $select->from('Proj_ProjectWorkGroup')
                        ->columns(array('PWorkGroupId','SerialNo','WorkGroupName','WorkGroupId','SortId','WorkTypeId'))
                        ->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {

                        $insert = $sql->insert();
                        $insert->into('Proj_ProjectWorkGroup');
                        $insert->Values(array('ProjectId' => $iToProjectId, 'SerialNo' => $trans['SerialNo'],'WorkGroupName' => $trans['WorkGroupName'],'WorkGroupId' => $trans['WorkGroupId'],
                            'SortId' => $trans['SortId'], 'WorkTypeId' => $trans['WorkTypeId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ipwgid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $update = $sql->update();
                        $update->table('Proj_ProjectWorkGroup');
                        $update->set(array('CopyId' => $ipwgid ));
                        $update->where(array('PWorkGroupId' => $trans['PWorkGroupId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $select = $sql->select();
                    $select->from('Proj_WBSMaster')
                        ->columns(array('WBSId','ParentId','WBSName','LastLevel','SortOrder','ParentText','IOWUsed'))
                        ->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {

                        $insert = $sql->insert();
                        $insert->into('Proj_WBSMaster');
                        $insert->Values(array('ProjectId' => $iToProjectId, 'ParentId' => $trans['ParentId'],'WBSName' => $trans['WBSName'],
                            'LastLevel' => $trans['LastLevel'], 'SortOrder' => $trans['SortOrder'],'ParentText' => $trans['ParentText'],'IOWUsed' => $trans['IOWUsed']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ipwgid = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $update = $sql->update();
                        $update->table('Proj_WBSMaster');
                        $update->set(array('CopyId' => $ipwgid ));
                        $update->where(array('WBSId' => $trans['WBSId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }


                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_WBSMaster'))
                        ->join(array('b' =>'Proj_WBSMaster'),'a.ParentId=b.WBSId',array('CopyId'), $select:: JOIN_INNER)
                        ->columns(array('ParentId' => new Expression('DISTINCT(a.ParentId)')))
                        ->where(array('a.ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {
                        $update = $sql->update();
                        $update->table('Proj_WBSMaster');
                        $update->set(array('ParentId' => $trans['CopyId'],'CUpdate'=>1));
                        $update->where(array('ParentId' => $trans['ParentId'],'ProjectId'=>$iToProjectId,'CUpdate'=>0));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectIOWMaster'))
                        ->join(array('b' =>'Proj_ProjectWorkGroup'),'a.PWorkGroupId=b.PWorkGroupId',array('PWorkGroupId'=>new Expression("isnull(b.CopyId,0)")), $select:: JOIN_INNER)
                        ->columns(array('ProjectIOWId','IOWId','ParentId','WorkGroupId','SerialNo','RefSerialNo','Header',
                            'Specification','ShortSpec','UnitId','MeasurementType','AgtType','WorkingQty','SortId',
                            'RWorkingQty','CementRatio','SandRatio','MetalRatio','ThickQty','MixType','SlNo','SRate','RRate',
                            'ParentText','Rate','WorkTypeId'))
                        ->where(array('a.ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_ProjectIOWMaster');
                        $insert->Values(array('ProjectId' => $iToProjectId, 'IOWId'=> $trans['IOWId'],'ParentId'=> $trans['ParentId'],'WorkGroupId'=> $trans['WorkGroupId'],'SerialNo'=> $trans['SerialNo'],'RefSerialNo'=> $trans['RefSerialNo'],'Header'=> $trans['Header'],
                            'Specification'=> $trans['Specification'],'ShortSpec'=> $trans['ShortSpec'],'UnitId'=> $trans['UnitId'],'MeasurementType'=> $trans['MeasurementType'],'AgtType'=> $trans['AgtType'],'WorkingQty'=> $trans['WorkingQty'],'SortId'=> $trans['SortId'],
                            'RWorkingQty'=> $trans['RWorkingQty'],'CementRatio'=> $trans['CementRatio'],'SandRatio'=> $trans['SandRatio'],'MetalRatio'=> $trans['MetalRatio'],'ThickQty'=> $trans['ThickQty'],'MixType'=> $trans['MixType'],'SlNo'=> $trans['SlNo'],'SRate'=> $trans['SRate'],'RRate'=> $trans['RRate'],
                            'ParentText'=> $trans['ParentText'],'Rate'=> $trans['Rate'],'WorkTypeId'=> $trans['WorkTypeId'],'PWorkGroupId'=> $trans['PWorkGroupId']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ipwgid = $dbAdapter->getDriver()->getLastGeneratedValue();


                        $update = $sql->update();
                        $update->table('Proj_ProjectIOWMaster');
                        $update->set(array('CopyId' => $ipwgid ));
                        $update->where(array('ProjectIOWId' => $trans['ProjectIOWId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectIOWMaster'))
                        ->join(array('b' =>'Proj_ProjectIOWMaster'),'a.ParentId=b.ProjectIOWId',array('CopyId'), $select:: JOIN_INNER)
                        ->columns(array('ParentId' => new Expression('DISTINCT(a.ParentId)')))
                        ->where(array('a.ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {
                        $update = $sql->update();
                        $update->table('Proj_ProjectIOWMaster');
                        $update->set(array('ParentId' => $trans['CopyId'],'CUpdate'=>1));
                        $update->where(array('ParentId' => $trans['ParentId'], 'ProjectId'=>$iToProjectId,'CUpdate'=>0));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectIOW'))
                        ->join(array('b' =>'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId',array('ProjectIOWId'=>new Expression("isnull(b.CopyId,0)")), $select:: JOIN_INNER)
                        ->columns(array('Qty','Rate','Amount','QualRate','QualAmount','WastageAmt','BaseRate','QualifierValue','TotalRate','NetRate','RWastageAmt','RBaseRate','RQualifierValue','RTotalRate','RNetRate','PrevQty','CurQty'))
                        ->where(array('a.ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {
                        $dQty = 0;
                        $dAmt = 0;
                        $dQAmt = 0;
                        if ($bQty ==1) {
                            $dQty = $trans['Qty'];
                            $dAmt = $trans['Amount'];
                            $dQAmt = $trans['QualAmount'];
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_ProjectIOW');
                        $insert->Values(array('ProjectId' => $iToProjectId, 'ProjectIOWId'=> $trans['ProjectIOWId'],'Qty'=> $dQty,'Rate'=> $trans['Rate'],'Amount'=> $dAmt,
                            'QualRate'=> $trans['QualRate'],'QualAmount'=> $dQAmt,'WastageAmt'=> $trans['WastageAmt'],'BaseRate'=> $trans['BaseRate'],
                            'QualifierValue'=> $trans['QualifierValue'],'TotalRate'=> $trans['TotalRate'],'NetRate'=> $trans['NetRate'],'RWastageAmt'=> $trans['RWastageAmt'],
                            'RBaseRate'=> $trans['RBaseRate'],'RQualifierValue'=> $trans['RQualifierValue'],'RTotalRate'=> $trans['RTotalRate'],'RNetRate'=> $trans['RNetRate'],'PrevQty'=> $trans['PrevQty'],'CurQty'=> $trans['PrevQty']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        ///Select Qty,Amount,QualAmount from Proj_ProjectIOW
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_WBSTrans'))
                        ->join(array('b' =>'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId',array('ProjectIOWId'=>new Expression("isnull(b.CopyId,0)")), $select:: JOIN_INNER)
                        ->join(array('c' =>'Proj_WBSMaster'),'a.WBSId=c.WBSId',array('WBSId'=>new Expression("c.CopyId")), $select:: JOIN_INNER)
                        ->columns(array('SerialNo','Qty','Rate','Amount'))
                        ->where(array('a.ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {
                        $dQty = 0;
                        $dAmt = 0;
                        if ($bQty ==1) {
                            $dQty = $trans['Qty'];
                            $dAmt = $trans['Amount'];
                        }


                        $insert = $sql->insert();
                        $insert->into('Proj_WBSTrans');
                        $insert->Values(array('ProjectId' => $iToProjectId,'WBSId'=>$trans['WBSId'],
                            'ProjectIOWId'=> $trans['ProjectIOWId'],'SerialNo'=>$trans['SerialNo'],
                            'Qty'=> $dQty,'Rate'=> $trans['Rate'],'Amount'=> $dAmt));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectRateAnalysis'))
                        ->join(array('b' =>'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId',array('ProjectIOWId'=>new Expression("isnull(b.CopyId,0)")), $select:: JOIN_INNER)
                        ->join(array('c' =>'Proj_ProjectIOWMaster'),'a.SubIOWId=c.ProjectIOWId',array('SubIOWId'=>new Expression("isnull(c.CopyId,0)")), $select:: JOIN_INNER)
                        ->columns(array('IncludeFlag','ReferenceId','ResourceId','SubIOWId','Description','Qty','Rate','Amount','Formula','MixType','TransType','SortId','RateType','Wastage','WastageQty','WastageAmount','Weightage'))
                        ->where(array('a.ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {
                        $insert = $sql->insert();
                        $insert->into('Proj_ProjectRateAnalysis');
                        $insert->Values(array('ProjectId' => $iToProjectId,'ProjectIOWId'=>$trans['ProjectIOWId'],'SubIOWId'=>$trans['SubIOWId'],
                            'IncludeFlag'=>$trans['IncludeFlag'],'ReferenceId'=>$trans['ReferenceId'],'ResourceId'=>$trans['ResourceId'],
                            'Description'=>$trans['Description'],'Qty'=>$trans['Qty'],'Rate'=>$trans['Rate'],'Amount'=>$trans['Amount'],'Formula'=>$trans['Formula'],
                            'MixType'=>$trans['MixType'],'TransType'=>$trans['TransType'],'SortId'=>$trans['SortId'],'RateType'=>$trans['RateType'],
                            'Wastage'=>$trans['Wastage'],'WastageQty'=>$trans['WastageQty'],'WastageAmount'=>$trans['WastageAmount'],'Weightage'=>$trans['Weightage']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    if ($bQty ==1) {
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ProjectDetails'))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('ProjectIOWId' => new Expression("isnull(b.CopyId,0)")), $select:: JOIN_INNER)
                            ->columns(array('ResourceId', 'Qty', 'Rate', 'Amount', 'IncludeFlag', 'QualifierId', 'NT', 'Weightage', 'MixType'))
                            ->where(array('a.ProjectId' => $iFormProjectId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($pworkgroup as $trans) {
                            $insert = $sql->insert();
                            $insert->into('Proj_ProjectDetails');
                            $insert->Values(array('ProjectId' => $iToProjectId, 'ProjectIOWId' => $trans['ProjectIOWId'],
                                'ResourceId' => $trans['ResourceId'], 'Qty' => $trans['Qty'], 'Rate' => $trans['Rate'], 'Amount' => $trans['Amount'],
                                'IncludeFlag' => $trans['IncludeFlag'], 'QualifierId' => $trans['QualifierId'], 'NT' => $trans['NT'], 'Weightage' => $trans['Weightage'], 'MixType' => $trans['MixType']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $select = $sql->select();
                    $select->from('Proj_ProjectResource')
                        ->columns(array('ResourceId','IncludeFlag','NT','RateType','Rate','Qty','Amount'))
                        ->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pworkgroup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pworkgroup as $trans) {
                        $dQty = 0;
                        $dAmt = 0;
                        if ($bQty ==1) {
                            $dQty = $trans['Qty'];
                            $dAmt = $trans['Amount'];
                        }

                        $insert = $sql->insert();
                        $insert->into('Proj_ProjectResource');
                        $insert->Values(array('ProjectId' => $iToProjectId, 'ResourceId'=>$trans['ResourceId'],'IncludeFlag'=>$trans['IncludeFlag'],'NT'=>$trans['NT'],
                            'RateType'=>$trans['RateType'],'Rate'=>$trans['Rate'],'Qty'=>$dQty,'Amount'=>$dAmt));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $update = $sql->update();
                    $update->table('Proj_ProjectWorkGroup');
                    $update->set(array('CopyId' => 0));
                    $update->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Proj_WBSMaster');
                    $update->set(array('CopyId' => 0));
                    $update->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Proj_ProjectIOWMaster');
                    $update->set(array('CopyId' => 0));
                    $update->where(array('ProjectId' => $iFormProjectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                } catch (PDOException $e) {
                    $connection->rollback();
                }

                $this->redirect()->toRoute('project/projboq', array('controller' => 'rfc', 'action' => 'projboq','projectId'=>$iToProjectId,'type'=>'B'));
            }

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('data' => 'ProjectId', 'value' =>'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->fromprojects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            //begin trans try block example starts
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function gettoprojectAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $iProjectId= $this->bsf->isNullCheck($postParams['ProjectId'],'number');

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('data' => 'ProjectId', 'value' =>'ProjectName'))
                    ->where("ProjectId <> $iProjectId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
    public function checkprojectboqfoundAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $iProjectId= $this->bsf->isNullCheck($postParams['ProjectId'],'number');

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Proj_ProjectIOWMaster')
                    ->columns(array('ProjectIOWId'))
                    ->where("ProjectId <> $iProjectId");
                echo $statement = $sql->getSqlStringForSqlObject($select);
                $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $ans ="No";
                if (!empty($data)) $ans = "BOQ";

                if ($ans =="No") {
                    $select = $sql->select();
                    $select->from('Proj_WBSMaster')
                        ->columns(array('WBSId'))
                        ->where("ProjectId <> $iProjectId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if (!empty($data)) $ans = "WBS";
                }

                $response = $this->getResponse();
                $response->setContent($ans);
                return $response;
            }
        }
    }
}