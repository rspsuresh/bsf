<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Project\Controller;

use JsonSchema\Constraints\Format;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Zend\Authentication\Result;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\Session as SessionStorage;
use Zend\Session\Container;

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
class ReportController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function resourcelistAction(){
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
            } else {
                $sql = new Sql($dbAdapter);
                $select = $sql->select();

                $select->from(array('a' => 'Proj_Resource'))
                    ->join(array('b' => 'Proj_UOM'),'a.UnitId=b.UnitId', array('UnitName'),$select::JOIN_LEFT)
                    ->columns(array('Code','ResourceName','Rate'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->reslists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }


    public  function getresourcewithgroupAction() {
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
                ->join(array('c' => 'Proj_ResourceType'), 'a.TypeId=c.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                ->join(array('d' => 'Proj_UOM'),'a.UnitId=d.UnitId', array('UnitName'),$select::JOIN_LEFT)
                ->columns(array('Code','ResourceName','Rate'));

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public  function getworkgrouplistAction() {
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
                ->columns(array('WorkGroupId','SerialNo','WorkGroupName'));

            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public  function getworktypelistAction() {
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

    public  function getresourcegrouplistAction() {
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ResourceGroup'))
                ->join(array('b' => 'Proj_ResourceType'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                ->columns(array('ResourceGroupId', 'ResourceGroupName','ParentId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public  function getiowlistAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_IOWMaster'))
                ->join(array('b' => 'Proj_WorkGroupMaster'), 'a.WorkGroupId=b.WorkGroupId', array('WorkGroupName'), $select:: JOIN_LEFT)
                ->columns(array('IOWId', 'SerialNo','Specification','ParentId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $response = $this->getResponse();
            $response->setContent(json_encode($results->toArray()));
            return $response;
        }
    }

    public function resourcelistwithgroupAction(){
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

    public function workgrouplistAction(){
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

    public function resourcegrouplistAction(){
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

    public function worktypelistAction(){
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

    public function iowlistAction(){
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

    public function schedulevscompletionAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');

            $sql = new Sql($dbAdapter);

            $select1 = $sql->select();
            $select1->from(array('a' => 'Proj_ScheduleDetails'))
                ->join(array('b' => 'Proj_Schedule'), new Expression('a.ProjectIOWId=b.ProjectIOWId and a.WBSId=b.WBSId'), array(), $select1::JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select1::JOIN_INNER)
                ->columns(array('Mon' => new Expression("month(sDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,sDate),3) + '-' + ltrim(str(Year(sDate)))"), 'ShAmount' => new Expression("sum(a.SQty*c.QualRate)"),'CompAmount'=>new Expression("CAST(0 As Decimal(18,2))"),'ShPer'=>new Expression("CAST(0 As Decimal(18,2))"),'CompPer'=>new Expression("CAST(0 As Decimal(18,2))")))
                ->where("a.ProjectId=$iProjectId")
                ->group(new Expression('month(sDate), LEFT(DATENAME(MONTH,sDate),3),Year(sDate)'));

            $select2 = $sql->select();
            $select2->from(array('a' => 'Proj_SchCompletion'))
                ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select2::JOIN_INNER)
                ->columns(array('Mon' => new Expression("month(sDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,sDate),3) + '-' + ltrim(str(Year(sDate)))"),'ShAmount'=>new Expression("CAST(0 As Decimal(18,2))"), 'CompAmount' => new Expression("sum(a.Qty*b.QualRate)"),'ShPer'=>new Expression("CAST(0 As Decimal(18,2))"),'CompPer'=>new Expression("CAST(0 As Decimal(18,2))")))
                ->where("a.ProjectId=$iProjectId")
                ->group(new Expression('month(sDate), LEFT(DATENAME(MONTH,sDate),3),Year(sDate)'));
            $select2->combine($select1,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$select2))
                ->columns(array('Mon', 'Mondata', 'ShAmount'=>new Expression("sum(g.ShAmount)") ,'CompAmount'=> new Expression("sum(g.CompAmount)"),'ShPer'=> new Expression("sum(g.ShPer)"),'CompPer'=> new Expression("sum(g.CompPer)")))
                ->group(array('g.Mon','g.Mondata'))
                ->order( 'g.Mon');
            $statement = $sql->getSqlStringForSqlObject($select3);
            $schedulelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ScheduleDetails'))
                ->join(array('b' => 'Proj_Schedule'), new Expression('a.ProjectIOWId=b.ProjectIOWId and a.WBSId=b.WBSId'), array(), $select::JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_INNER)
                ->columns(array('ShAmount' => new Expression("sum(a.SQty*c.QualRate)")))
                ->where("a.ProjectId=$iProjectId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $dShAmt=0;
            if (!empty($shAmt)) $dShAmt = floatval($this->bsf->isNullCheck($shAmt['ShAmount'],'number'));

            if ($dShAmt !=0) {
                $dTSh = 0;
                $dTComp = 0;
                for ($i = 0; $i < count($schedulelist); $i++) {

                    $dTSh = $dTSh + floatval($this->bsf->isNullCheck($schedulelist[$i]['ShAmount'], 'number'));
                    $dTComp = $dTComp + floatval($this->bsf->isNullCheck($schedulelist[$i]['CompAmount'], 'number'));

                    $schedulelist[$i]['ShPer'] = ($dTSh/$dShAmt)*100 ;
                    $schedulelist[$i]['CompPer'] = ($dTComp/$dShAmt)*100 ;
                }
            }

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;

            $this->_view->shcomp = $schedulelist;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function projectprogressAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');

            $sql = new Sql($dbAdapter);

            $select1 = $sql->select();
            $select1->from(array('a' => 'Proj_ScheduleDetails'))
                ->join(array('b' => 'Proj_Schedule'), new Expression('a.ProjectIOWId=b.ProjectIOWId and a.WBSId=b.WBSId'), array(), $select1::JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select1::JOIN_INNER)
                ->columns(array('Mon' => new Expression("month(sDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,sDate),3) + '-' + ltrim(str(Year(sDate)))"), 'ShAmount' => new Expression("sum(a.SQty*c.QualRate)"),'CompAmount'=>new Expression("CAST(0 As Decimal(18,2))")))
                ->where("a.ProjectId=$iProjectId")
                ->group(new Expression('month(sDate), LEFT(DATENAME(MONTH,sDate),3),Year(sDate)'));

            $select2 = $sql->select();
            $select2->from(array('a' => 'Proj_SchCompletion'))
                ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select2::JOIN_INNER)
                ->columns(array('Mon' => new Expression("month(sDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,sDate),3) + '-' + ltrim(str(Year(sDate)))"),'ShAmount'=>new Expression("CAST(0 As Decimal(18,2))"), 'CompAmount' => new Expression("sum(a.Qty*b.QualRate)")))
                ->where("a.ProjectId=$iProjectId")
                ->group(new Expression('month(sDate), LEFT(DATENAME(MONTH,sDate),3),Year(sDate)'));
            $select2->combine($select1,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$select2))
                ->columns(array('Mon', 'Mondata', 'ShAmount'=>new Expression("sum(g.ShAmount)") ,'CompAmount'=> new Expression("sum(g.CompAmount)")))
                ->group(array('g.Mon','g.Mondata'))
                ->order( 'g.Mon');
            $statement = $sql->getSqlStringForSqlObject($select3);
            $schedulelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ScheduleDetails'))
                ->join(array('b' => 'Proj_Schedule'), new Expression('a.ProjectIOWId=b.ProjectIOWId and a.WBSId=b.WBSId'), array(), $select::JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_INNER)
                ->columns(array('ShAmount' => new Expression("sum(a.SQty*c.QualRate)")))
                ->where("a.ProjectId=$iProjectId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $dTotalValue=0;
            if (!empty($shAmt)) $dTotalValue = floatval($this->bsf->isNullCheck($shAmt['ShAmount'],'number'));

            $select = $sql->select();
            $select->from(array('a' => 'Proj_SchCompletion'))
                ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
                ->columns(array('CompAmount' => new Expression("sum(a.Qty*b.QualRate)")))
                ->where("a.ProjectId=$iProjectId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $compAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $dTotalCompValue=0;
            if (!empty($compAmt)) $dTotalCompValue = floatval($this->bsf->isNullCheck($compAmt['CompAmount'],'number'));

            $dDate = date('Y-m-d');

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ScheduleDetails'))
                ->join(array('b' => 'Proj_Schedule'), new Expression('a.ProjectIOWId=b.ProjectIOWId and a.WBSId=b.WBSId'), array(), $select::JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_INNER)
                ->columns(array('ShAmount' => new Expression("sum(a.SQty*c.QualRate)")))
                ->where("a.ProjectId=$iProjectId and a.sDate >= '" . date('d-M-Y', strtotime($dDate)) . "'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $dCurrentSHValue=0;
            if (!empty($shAmt)) $dCurrentSHValue = floatval($this->bsf->isNullCheck($shAmt['ShAmount'],'number'));

            $select = $sql->select();
            $select->from(array('a' => 'Proj_SchCompletion'))
                ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
                ->columns(array('CompAmount' => new Expression("sum(a.Qty*b.QualRate)")))
                ->where("a.ProjectId=$iProjectId and a.sDate >= '" . date('d-M-Y', strtotime($dDate)) . "'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $compAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $dCurrentCompValue=0;
            if (!empty($compAmt)) $dCurrentCompValue = floatval($this->bsf->isNullCheck($compAmt['CompAmount'],'number'));

            $dRemainValue = $dTotalValue - $dCurrentCompValue;
            $dRemainSHValue = $dTotalValue - $dCurrentSHValue;

            $dCurrentStatus=0;
            $dCurrentProgressRate=0;
            $dRequiredProgressRate=0;

            if ($dTotalValue !=0) $dCurrentStatus = ($dTotalCompValue/$dTotalValue)*100;
            if ($dCurrentSHValue !=0) $dCurrentProgressRate = ($dCurrentCompValue/$dCurrentSHValue)*100;
            if ($dRemainSHValue !=0) $dRequiredProgressRate = ($dRemainValue/$dRemainSHValue)*100;

            $this->_view->currentStatus = $dCurrentStatus;
            $this->_view->currentProgressRate = $dCurrentProgressRate;
            $this->_view->requiredProgressRate = $dRequiredProgressRate;

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;

            $this->_view->shcomp = $schedulelist;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function projectedcashflowAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');

            $sql = new Sql($dbAdapter);


            $select1 = $sql->select();
            $select1->from(array('a' => 'Proj_ScheduleDetails'))
                ->join(array('b' => 'Proj_Schedule'), new Expression('a.ProjectIOWId=b.ProjectIOWId and a.WBSId=b.WBSId'), array(), $select1::JOIN_INNER)
                ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array(), $select1::JOIN_INNER)
                ->columns(array('Mon' => new Expression("month(sDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,sDate),3) + '-' + ltrim(str(Year(sDate)))"), 'ShAmount' => new Expression("sum(a.SQty*c.QualRate)"),'CompAmount'=>new Expression("CAST(0 As Decimal(18,2))")))
                ->where("a.ProjectId=$iProjectId")
                ->group(new Expression('month(sDate), LEFT(DATENAME(MONTH,sDate),3),Year(sDate)'));


            //Outflow Chanage from WO Revision/ CRM Data

            $select2 = $sql->select();
            $select2->from(array('a' => 'Proj_SchCompletion'))
                ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select2::JOIN_INNER)
                ->columns(array('Mon' => new Expression("month(sDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,sDate),3) + '-' + ltrim(str(Year(sDate)))"),'ShAmount'=>new Expression("CAST(0 As Decimal(18,2))"), 'CompAmount' => new Expression("sum(a.Qty*b.QualRate)")))
                ->where("a.ProjectId=$iProjectId")
                ->group(new Expression('month(sDate), LEFT(DATENAME(MONTH,sDate),3),Year(sDate)'));
            $select2->combine($select1,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$select2))
                ->columns(array('Mon', 'Mondata', 'ShAmount'=>new Expression("sum(g.ShAmount)") ,'CompAmount'=> new Expression("sum(g.CompAmount)")))
                ->group(array('g.Mon','g.Mondata'))
                ->order( 'g.Mon');
            $statement = $sql->getSqlStringForSqlObject($select3);
            $schedulelist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->shcomp = $schedulelist;

            $this->_view->projectId = $iProjectId;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function resourcerequirementAction(){
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
            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $iTypeId =  $this->bsf->isNullCheck($this->params()->fromRoute('typeid'),'number');
            $sql = new Sql($dbAdapter);

            //Project Name
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Type
            $select = $sql->select();
            $select->from('Proj_ResourceType')
                ->columns(array('TypeId', 'TypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->typelists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//            if($iTypeId != 0) {
//                //Resource Type
//                $select = $sql->select();
//                $select->from('Proj_Resource')
//                    ->columns(array('ResourceId', 'ResourceName'))
//                    ->where(array('TypeId'=>$iTypeId));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->ResourceLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            }




            $this->_view->projectId = $iProjectId;
            $this->_view->typeId = $iTypeId;
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function reportlistAction(){
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
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function landbankregisterAction(){
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
                //Write your Normal form post code here

            }
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->join(array('b' => 'Proj_SaleTypeMaster'),'a.SaleTypeId=b.SaleTypeId', array('SaleTypeName'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'),'a.TotalAreaUnitId=c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                ->join(array('d' => 'WF_CityMaster'),'a.CityId=d.CityId', array('CityName'), $select::JOIN_LEFT)
                ->columns(array('EnquiryId','RefDate','DateFormat'=>new Expression("Format(RefDate, 'dd/MM/yyyy')"), 'RefNo', 'PropertyName', 'LandCost','LandStatus','LandArea' => new Expression("str(TotalArea)+' '+C.UnitName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->landregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function ratehistoryAction(){
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
                //Write your Normal form post code here

            }

            //begin trans try block example ends
//            Select * from Proj_Resource
//            Select * from Proj_ProjectMaster
//            Select * from Vendor_Master
            //Resource
            $select = $sql->select();
            $select->from('Proj_Resource')
                ->columns(array('ResourceId', 'ResourceName'))
                ->where(array('TypeId'=>'2'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Project
            $select = $sql->select();
            $select->from('WF_OperationalCostCentre')
                ->columns(array('ProjectId'=>new Expression('CostCentreId'), 'ProjectName'=>new Expression('CostCentreName')));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Project = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Vendor
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId', 'VendorName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('MMS_PVRegister');
            $select->columns(array('BillDate'=>new Expression("Min(BillDate)")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $mindate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if (!empty($mindate)) $dmindate =$mindate['BillDate'];
            else  $dmindate = strtotime(date('m-01-Y'));
            $this->_view->mindate = $dmindate;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function pivotreportAction(){

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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();

            $select->from(array('a' => 'Proj_ScheduleDetails'))
                ->join(array('b' => 'Proj_Schedule'),'a.ShTransId=b.ShTransId', array('Specification'),$select::JOIN_LEFT)
                ->columns(array('ShTransId','sDate'=>new Expression("FORMAT(sDate, 'dd-MM-yyyy')"),'Qty'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->reslists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function revisionreportAction(){

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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select->from(array('a' => 'Proj_ProjectIOWMaster'))
                ->join(array('b' => 'Proj_ProjectIOWTrans'),'a.ProjectIOWId=b.ProjectIOWId', array('Qty','Rate','Amount'),$select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'),'a.UnitId=c.UnitId', array('UnitName'),$select::JOIN_LEFT)
                ->join(array('d' => 'Proj_RevisionMaster'),'b.RevisionId=d.RevisionId', array('RevisionName'),$select::JOIN_LEFT)
                ->columns(array('ProjectIOWId','SerialNo','Specification'))
                ->where("a.ProjectId=$iProjectId and A.UnitId<>0");

            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->iowlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;
            $this->_view->revtype= $sType;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function criticalactivityAction(){
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

        $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
        $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');


        $this->_view->projectId = $iProjectId;
        $this->_view->revtype= $sType;

        $sql = new Sql($dbAdapter);

        $this->_view->strText = '';
        //$this->_view->projectId = '';
        //$this->_view->typeName = '';

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $icount = 0;
            $stdate = "";
            $eddate = "";
            $strText = "";
            $ParentId = 0;
            $typename = $postData['typeName'];

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_SchedulePlan'));
            else $select->from(array('v' => 'Proj_Schedule'));

            $select->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
            $select->where(array('ProjectId' => $postData['projectId'], 'Parent' => $ParentId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            foreach($typeResult as $row) {
                $ParentId = $row['WBSId'];
                if ($icount == 0) {
                    $strText.= '{ ';

                    $select = $sql->select();
                    $select->from(array('v' => 'Proj_Schedule'))
                        ->columns(array("EndDate"))
                        ->order("EndDate DESC")
                        ->limit(1);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    foreach($typeResult as $rowDay) {
                        $eddate=date('m/d/Y',strtotime($rowDay['EndDate']));
                    }

                    $select = $sql->select();
                    $select->from(array('v' => 'Proj_Schedule'))
                        ->columns(array("StartDate"))
                        ->order("StartDate ASC")
                        ->limit(1);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    foreach($typeResult as $rowDayed) {
                        $stdate=date('m/d/Y',strtotime($rowDayed['StartDate']));
                    }
                } else {
                    $strText.= ', { ';
                }

                $strText.= '"TaskID" : ' . $row['Id'] . ' ,';
                $strText.= '"TaskName" : "' . $row['Specification'] . '" ,';
                $strText.= '"StartDate" : "' . date('m/d/Y',strtotime($row['StartDate'])) . '" ,';
                $strText.= '"EndDate" : "' . date('m/d/Y',strtotime($row['EndDate'])) . '" ,';
                $strText.= '"Duration" : ' . $row['Duration'] . ' ,';
                $strText.= '"Progress" : "' . $row['Progress'] . '", ';
                $strText.= '"parent" : "' . $row['Parent'] . '", ';
                $strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
                $strText.= '"iowid" : "' . $row['ProjectIOWId'] . '" , ';
                $strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';

                $icount1 = 0;

                $select = $sql->select();
                $select->from(array('v' => 'Proj_Schedule'))
                    ->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
                $select->where(array('Parent' => $ParentId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach($typeResult as $row1) {
                    if ($icount1 ==0) {
                        $strText.= '"Children": [ ';
                        $strText.= "{";
                    } else {
                        $strText.= ", {";
                    }
                    $strText.= '"TaskID" : ' . $row1['Id'] . ' ,';
                    $strText.= '"TaskName" : "' . $row1['Specification'] . '" ,';
                    $strText.= '"StartDate" : "' . date('m/d/Y',strtotime($row1['StartDate'])) . '" ,';
                    $strText.= '"EndDate" : "' . date('m/d/Y',strtotime($row1['EndDate'])) . '" ,';
                    $strText.= '"Duration" : ' . $row1['Duration'] . ' ,';
                    $strText.= '"Progress" : "' . $row1['Progress'] . '" ,';
                    $strText.= '"parent" : "' . $row1['Parent'] . '", ';
                    $strText.= '"Predecessor" : "' . $row1['Predecessor'] . '" , ';
                    $strText.= '"iowid" : "' . $row1['ProjectIOWId'] . '" , ';
                    $strText.= '"wbsid" : "' . $row1['WBSId'] . '" , ';

                    $strText.= '} ';
                    $icount1=$icount1+1;
                }
                if ($icount1 >=1) {
                    $strText.= ' ] ';
                }
                $icount = $icount+1;
                $strText.= '} ';
            }

            $strHDay = "";

            $select = $sql->select();
            $select->from('Proj_Holiday')
                ->columns(array("day"=>'HDate', "label"=>'Note'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $hdaylist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $ihStart = 0;
            foreach($hdaylist as $hrow) {
                if ($ihStart==0) {
                    $strHDay.= "{";
                } else {
                    $strHDay.= ", {";
                }

                $strHDay.= 'day : "' . date("m-d-Y",strtotime($hrow['day'])) . '",';
                $strHDay.= 'label : "' . $hrow['label'] . ' ",';
                $strHDay.= 'background : "' . "yellowgreen" .'"}';

                $ihStart = $ihStart+1;
            }

            $this->_view->strText = $strText;
            $this->_view->stdate = $stdate;
            $this->_view->eddate = $eddate;
            $this->_view->strHDay = $strHDay;
            //   $this->_view->projectId = $postData['projectId'];
            //  $this->_view->typeName = $postData['typeName'];
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId', 'ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function resourcedetailReportAction(){
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
            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if ($sType=="P") $select->from(array('a' => 'Proj_ProjectDetailsPlan'));
            else $select->from(array('a' => 'Proj_ProjectDetails'));

            $select->join(array('b' => 'Proj_Resource'),'a.ResourceId=b.ResourceId', array('ResourceName'),$select::JOIN_INNER)
                ->join(array('c' => 'Proj_UOM'),'b.UnitId=c.UnitId', array('UnitName'),$select::JOIN_INNER)
                ->join(array('d' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=d.ProjectIOWId', array('SerialNo','Specification'),$select::JOIN_INNER)
                ->columns(array('ResourceId','Qty','Rate','Amount'))
                ->where("a.ProjectId=$iProjectId");

            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->ResourceDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;
            $this->_view->revtype= $sType;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function slippagereportAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;
            $this->_view->revtype= $sType;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function planquantityReportAction(){

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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            /*$sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'number');*/

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select->from(array('a' => 'Proj_ProjectIOWMaster'))
                ->join(array('b' => 'Proj_ProjectIOWTrans'),'a.ProjectIOWId=b.ProjectIOWId', array('Qty'),$select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'),'a.UnitId=c.UnitId', array('UnitName'),$select::JOIN_LEFT)
                ->join(array('d' => 'Proj_RevisionMaster'),'b.RevisionId=d.RevisionId', array('RevisionName'),$select::JOIN_LEFT)
                ->columns(array('ProjectIOWId','SerialNo','Specification'))
                ->where("a.ProjectId=$iProjectId and A.UnitId<>0");

            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->iowlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;
            /*$this->_view->revtype= $sType;*/

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function wbsallotedReportAction(){

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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            /*Select A.ProjectIOWId,B.SerialNo,B.Specification,C.UnitName,A.Qty TotalQty,D.Qty WBSAllotedQty,Case When D.Qty<A.Qty then 'Unalloted' else 'Alloted' end Allot From Proj_ProjectIOW A
            Inner Join Proj_ProjectIOWMaster B on A.ProjectIOWId=b.ProjectIOWId
            Inner Join Proj_UOM C on B.UnitId=C.UnitId
            Left Join (Select ProjectIOWId,Sum(Qty) Qty from Proj_WBSTrans Where ProjectId=1 group by ProjectIOWId) D on A.ProjectIOWId=D.ProjectIOWId
            where A.ProjectId=1*/
            if($sType == "P") {
                $subquery = $sql->select();
                $subquery->from('Proj_WBSTransPlan')
                    ->where(array('ProjectId'=>$iProjectId))
                    ->columns(array('ProjectIOWId' , 'Qty'=> new Expression("Sum(Qty)")))
                    ->group('ProjectIOWId');
                $subquery = $sql->getSqlStringForSqlObject($subquery);

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectIOWPlan'))
                    ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo','Specification'),$select::JOIN_INNER)
                    ->join(array('c' => 'Proj_UOM'),'b.UnitId=c.UnitId', array('UnitName'),$select::JOIN_INNER)
                    ->join(array('d' =>new Expression('('.$subquery.')')),'a.ProjectIOWId=d.ProjectIOWId', array('WBSAllotedQty'=>'Qty'),$select::JOIN_LEFT)
                    ->columns(array('ProjectIOWId','TotalQty' =>new Expression('a.Qty'),'Allot'=>new Expression("Case When d.Qty < a.Qty then 'Unalloted' else 'Alloted' end")))
                    ->where("a.ProjectId=$iProjectId");

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->wbslists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            } else if($sType == "B") {
                $subquery = $sql->select();
                $subquery->from('Proj_WBSTrans')
                    ->where(array('ProjectId'=>$iProjectId))
                    ->columns(array('ProjectIOWId' , 'Qty'=> new Expression("Sum(Qty)")))
                    ->group('ProjectIOWId');
                $subquery = $sql->getSqlStringForSqlObject($subquery);

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectIOW'))
                    ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo','Specification'),$select::JOIN_INNER)
                    ->join(array('c' => 'Proj_UOM'),'b.UnitId=c.UnitId', array('UnitName'),$select::JOIN_INNER)
                    ->join(array('d' =>new Expression('('.$subquery.')')),'a.ProjectIOWId=d.ProjectIOWId', array('WBSAllotedQty'=>'Qty'),$select::JOIN_LEFT)
                    ->columns(array('ProjectIOWId','TotalQty' =>new Expression('a.Qty'),'Allot'=>new Expression("Case When d.Qty < a.Qty then 'Unalloted' else 'Alloted' end")))
                    ->where("a.ProjectId=$iProjectId");

                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->wbslists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $this->_view->projectId = $iProjectId;
            $this->_view->revtype= $sType;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function wbsrevisionAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {

                $dbAdapter->query('TRUNCATE TABLE Proj_TmpWBS', $dbAdapter::QUERY_MODE_EXECUTE);
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('Proj_WBSMaster');
                $select->columns(array('WBSId','ParentId','WBSName','WBSId'))
                    ->where(array("ProjectId=$iProjectId"));

                $insert = $sql->insert();
                $insert->into('Proj_TmpWBS');
                $insert->columns(array('TmpId', 'ParentId', 'Description','WBSId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $select = $sql->select();
                $select->from(array('a' => 'Proj_WBSTrans'))
                    ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo','Specification'),$select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'),'b.UnitId=c.UnitId', array('UnitName'),$select::JOIN_LEFT)
                    ->columns(array('WBSId','WBSId','ProjectIOWId','Qty','Rate','Amount'))
                    ->where(array("a.ProjectId=$iProjectId"));
                $statement = $sql->getSqlStringForSqlObject($select);

                $insert = $sql->insert();
                $insert->into('Proj_TmpWBS');
                $insert->columns(array('ParentId','WBSId','ProjectIOWId','Qty','Rate','Amount','SerialNo','Description','UnitName'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $imaxId=0;
                $select = $sql->select();
                $select->from('Proj_WBSMaster');
                $select->columns(array('WBSId'=>new Expression("Max(WBSId)")))
                    ->where(array("ProjectId=$iProjectId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $maxid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($maxid)) $imaxId = intval($this->bsf->isNullCheck($maxid['WBSId'],'number'));

                $iId=0;
                $select = $sql->select();
                $select->from('Proj_TmpWBS');
                $select->columns(array('Id'=>new Expression('Max(Id)')))
                    ->where("TmpId<>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $tmpid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($maxid)) $iId = intval($this->bsf->isNullCheck($tmpid['Id'],'number'));

                $update = $sql->update();
                $update->table('Proj_TmpWBS');
                $update->set(array('TmpId' => new Expression("$imaxId+(Id-$iId)")));
                $update->where(array('TmpId' => 0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->select();
                $select->from('Proj_TmpWBS');
                $statement = $sql->getSqlStringForSqlObject($select);
                $tmpwbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $connection->commit();

                $select = $sql->select();
                $select->from('Proj_RevisionMaster');
                $select->columns(array('RevisionId','RevisionName'))
                    ->where("ProjectId=$iProjectId and RevisionType='$sType'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $revmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'Proj_WBSTransRevTrans'))
                    ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo','Specification'),$select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'),'b.UnitId=c.UnitId', array('UnitName'),$select::JOIN_LEFT)
                    ->columns(array('WBSId','WBSId','ProjectIOWId','Qty','Rate','Amount'))
                    ->where(array("a.ProjectId=$iProjectId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $wsbtrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $tmptrans = array();
                foreach ($tmpwbs as $wtrans) {
                    $key = $wtrans['WBSId'] . $wtrans['ProjectIOWId'];
                    $tmptrans[$key] = $wtrans;
                }

                foreach ($revmaster as $rev) {

                    $iRevId = $rev['RevisionId'];
                    $sQty = $rev['RevisionName'] . '-Qty';
                    $sRate = $rev['RevisionName'] . '-Rate';
                    $sAmount = $rev['RevisionName'] . '-Amount';

                    foreach ($tmptrans as &$record) {
                        $record[$sQty] = floatval(0);
                        $record[$sRate] = floatval(0);
                        $record[$sAmount] = floatval(0);
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_WBSTransRevTrans'))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo','Specification'),$select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'),'b.UnitId=c.UnitId', array('UnitName'),$select::JOIN_LEFT)
                        ->columns(array('WBSId','WBSId','ProjectIOWId','Qty','Rate','Amount'))
                        ->where(array("a.ProjectId=$iProjectId and a.RevisionId = $iRevId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $wsbtrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($wsbtrans as $trans) {
                        $skey = $trans['WBSId'] . $trans['ProjectIOWId'];
                        if(array_key_exists($skey, $tmptrans)) {
                            $tmptrans[$skey][$sQty] =  $trans['Qty'];
                            $tmptrans[$skey][$sRate] =  $trans['Rate'];
                            $tmptrans[$skey][$sAmount] =  $trans['Amount'];
                        }
                    }
                }

                $wbsarr = array();
                foreach ($tmptrans as $value)
                {
                    array_push($wbsarr, $value);
                }

                $this->_view->revmaster= $revmaster ;
                $this->_view->tmpwbs= $wbsarr;

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->projectId= $iProjectId;
                $this->_view->revtype= $sType;

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

    public function wbsitemabstractAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {

                $dbAdapter->query('TRUNCATE TABLE Proj_TmpWBS', $dbAdapter::QUERY_MODE_EXECUTE);
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from('Proj_WBSMaster');
                $select->columns(array('WBSId','ParentId','WBSName','WBSId'))
                    ->where(array("ProjectId=$iProjectId"));

                $insert = $sql->insert();
                $insert->into('Proj_TmpWBS');
                $insert->columns(array('TmpId', 'ParentId', 'Description','WBSId'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->select();
                if ($sType=="P") $select->from(array('a' => 'Proj_WBSTransPlan'));
                else $select->from(array('a' => 'Proj_WBSTrans'));

                $select->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo','Specification'),$select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'),'b.UnitId=c.UnitId', array('UnitName'),$select::JOIN_LEFT)
                    ->columns(array('WBSId','WBSId','ProjectIOWId','Qty','Rate','Amount'))
                    ->where(array("a.ProjectId=$iProjectId"));
                $statement = $sql->getSqlStringForSqlObject($select);

                $insert = $sql->insert();
                $insert->into('Proj_TmpWBS');
                $insert->columns(array('ParentId','WBSId','ProjectIOWId','Qty','Rate','Amount','SerialNo','Description','UnitName'));
                $insert->Values($select);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $imaxId=0;
                $select = $sql->select();
                $select->from('Proj_WBSMaster');
                $select->columns(array('WBSId'=>new Expression("Max(WBSId)")))
                    ->where(array("ProjectId=$iProjectId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $maxid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($maxid)) $imaxId = intval($this->bsf->isNullCheck($maxid['WBSId'],'number'));

                $iId=0;
                $select = $sql->select();
                $select->from('Proj_TmpWBS');
                $select->columns(array('Id'=>new Expression('Max(Id)')))
                    ->where("TmpId<>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $tmpid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($maxid)) $iId = intval($this->bsf->isNullCheck($tmpid['Id'],'number'));

                $update = $sql->update();
                $update->table('Proj_TmpWBS');
                $update->set(array('TmpId' => new Expression("$imaxId+(Id-$iId)")));
                $update->where(array('TmpId' => 0));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->select();
                $select->from('Proj_TmpWBS');
                $statement = $sql->getSqlStringForSqlObject($select);
                $tmpwbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $connection->commit();

                //Project Names
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->tmpwbs= $tmpwbs;
                $this->_view->projectId= $iProjectId;
                $this->_view->revtype= $sType;

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

    public function itemwisewbsReportAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            //Project Names
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //WBS Item Wise Abstrace
            /*Select (B.SerialNo+' '+ B.Specification) Specification,C.WBSName,A.Qty,A.Rate,Amount from Proj_WBSTrans A
            Inner Join Proj_ProjectIOWMaster B on A.ProjectIOWId=B.ProjectIOWId
            Inner Join Proj_WBSMaster C on A.WBSId=C.WBSId
            Where A.ProjectId=1*/
            if($sType == "P") $select->from(array('a'=>'Proj_WBSTransPlan'));
            else $select->from(array('a'=>'Proj_WBSTrans'));

            $select->join(array('b' => 'Proj_ProjectIOWMaster'),'a.ProjectIOWId=b.ProjectIOWId', array('Specification'=>new Expression("(B.SerialNo+' '+ B.Specification)")),$select::JOIN_INNER)
                ->join(array('c' => 'Proj_WBSMaster'),'a.WBSId=c.WBSId', array('WBSName','ParentText'),$select::JOIN_INNER)
                ->columns(array('Qty','Rate','Amount'))
                ->where(array("a.ProjectId=$iProjectId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->itemwisewbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            $this->_view->projectId= $iProjectId;
            $this->_view->revtype= $sType;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function workorderregisterReportAction(){
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
            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array("a" => "Proj_WORegister"))
                ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('NameOfWork'), $select::JOIN_LEFT)
                ->join(array('c' => 'CB_ClientMaster'), 'a.ClientId=c.ClientId', array('ClientName'), $select::JOIN_LEFT)
                ->join(array('d' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=d.TenderEnquiryId', array(), $select::JOIN_LEFT)
                ->join(array("e" => "CB_ProjectTypeMaster"), "d.ProjectTypeId =e.ProjectTypeId", array("ProjectTypeName"), $select::JOIN_LEFT)
                ->columns(array("WORegisterId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"), "EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')")
                , "PeriodType", "Duration", "OrderAmount"))
                ->where("A.LiveWO = 1")
                ->order('a.WORegisterId ASC');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function contractenquiryReportAction(){
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderEnquiry'))
                ->join(array('b' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=b.TenderEnquiryId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_TenderType'), 'b.TenderTypeId=c.TenderTypeId', array('TenderTypeName'), $select::JOIN_LEFT)
                ->join(array('d' => 'Proj_ProjectTypeMaster'), 'b.ProjectTypeId=d.ProjectTypeId', array('ProjectTypeName'), $select::JOIN_LEFT)
                ->columns(array('TenderEnquiryId', 'RefNo', 'RefDate', 'NameOfWork', 'ProposalCost','ProjectDuration','Duration',
                    'DocumentPurchase','Quoted','Submitted','BidWin','OrderReceived','WorkStarted','TechnicalSpecificationId','EnquirySiteInvestigationId'));
            $select->order('a.RefDate DESC');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrEnquires  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function quotationregisterReportAction(){
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from(array("a" => "Proj_TenderQuotationRegister"))
                ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('ProjectName'=> new Expression("NameOfWork"),'ClientName'), $select::JOIN_LEFT)
                ->join(array('d' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=d.TenderEnquiryId', array(), $select::JOIN_LEFT)
                ->join(array("e" => "CB_ProjectTypeMaster"), "d.ProjectTypeId =e.ProjectTypeId", array("ProjectTypeName"), $select::JOIN_LEFT)
                ->columns(array("QuotationId", "TenderEnquiryId", "RefNo", "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")))
                ->order('a.QuotationId ASC');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function quotationcomparisonAction(){
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
            $iEnquiryId = $this->bsf->isNullCheck($this->params()->fromRoute('enquiryid'), 'number');

            $sql = new Sql($dbAdapter);
            //Enquiry Names
            $select = $sql->select();
            $select->from(array("a" => "Proj_TenderEnquiry"))
                ->columns(array("TenderEnquiryId", "NameOfWork"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Enquirylists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array("a" => "Proj_TenderQuotationRegister"))
                ->columns(array("QuotationId"))
                ->where(array('TenderEnquiryId' => $iEnquiryId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $resQuotationId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $arr="";
            foreach($resQuotationId as $value) {
                $arr=$arr.$value['QuotationId'] . ",";
            }
            $arr = trim($arr, ",");
            $arr =  $this->bsf->isNullCheck($arr,'string');
            if($arr != '') {
                $select = $sql->select();
                $select->from(array("a" => "Proj_TenderQuotationTrans"))
                    ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_TenderQuotationRegister'), 'a.QuotationId=c.QuotationId', array('RefNo'), $select::JOIN_INNER)
                    ->columns(array("PrevQuotationTransId", "RefSerialNo", "Specification", "Qty",'Rate','Amount'))
                    ->where(array('a.QuotationId IN ('.$arr.')'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->Quotationlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            $this->_view->EnquiryId = $iEnquiryId;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function quotationproposalAction(){
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
            $iQuotationId = $this->bsf->isNullCheck($this->params()->fromRoute('quotationid'), 'number');

            $sql = new Sql($dbAdapter);
            //Quotation Register
            $select = $sql->select();
            $select->from("Proj_TenderQuotationRegister")
                ->columns(array('QuotationId','RefDate','RefNo','TenderEnquiryId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Quotationlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Tender Quotation Register

//            Select A.RefNo,A.RefDate,B.TenderNo,B.TenderDate from Proj_TenderQuotationRegister A
//            Inner Join Proj_TenderDetails B on A.TenderEnquiryId=B.TenderEnquiryId
//            Where QuotationId=4
            $select = $sql->select();
            $select->from(array("a" => "Proj_TenderQuotationRegister"))
                ->join(array('b' => 'Proj_TenderDetails'), 'a.TenderEnquiryId=b.TenderEnquiryId', array('TenderNo','TenderDate'), $select::JOIN_INNER)
                ->columns(array("RefNo", "RefDate"))
                ->where(array('a.QuotationId'=>$iQuotationId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->QuotationRegisterlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

//            Select A.SerialNo,A.Specification,B.UnitName,A.Qty,A.Rate,A.Amount from Proj_TenderQuotationTrans A
//            Inner Join Proj_UOM B on A.UnitId=B.UnitId
//            Where QuotationId=4
            //Tender Quotation Trans
            $select = $sql->select();
            $select->from(array("a" => "Proj_TenderQuotationTrans"))
                ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName'), $select::JOIN_INNER)
                ->columns(array("SerialNo", "Specification", "Qty",'Rate','Amount'))
                ->where(array('a.QuotationId'=>$iQuotationId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->QuotationTranslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->QuotationId = $iQuotationId;
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function schedulereportAction(){
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

            $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
            $sType =  $this->bsf->isNullCheck($this->params()->fromRoute('type'),'string');

            $sql = new Sql($dbAdapter);

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectName'))
                ->where("ProjectId=$iProjectId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $projectname = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $sProjName="";
            if (!empty($projectname)) $sProjName=$projectname['ProjectName'];
            $sTitle = "Schedule Print - ". $sProjName;

            if ($sType=="P") $sTitle =  $sTitle . ' (Plan)';
            else $sTitle =  $sTitle . ' (Budget)';


            $select = $sql->select();
            if ($sType=="P") $select->from(array('a' => 'Proj_SchedulePlan'));
            else $select->from(array('a' => 'Proj_Schedule'));
            $select->columns(array('Id','Parent','Specification','StartDate' => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')") ,'EndDate' => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')"),'Duration' => new Expression("str(a.Duration)+ ' Days'") ,'Predecessor'))
                ->where("a.ProjectId=$iProjectId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->shdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;
            $this->_view->revtype= $sType;
            $this->_view->shtitle= $sTitle;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function quotationreportAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Quotation Report");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $this->_view->setTerminal(true);

                $TenderEnquiryId = $this->bsf->isNullCheck($request->getPost('TenderEnquiryId'),'number');
                $select = $sql->select();
                $select->from(array('a'=>'Proj_TenderQuotationTrans'))
                    ->join(array('b' => 'Proj_TenderQuotationRegister'), 'a.QuotationId=b.QuotationId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                    ->columns(array('SerialNo', 'RefSerialNo', 'Specification', 'Rate', 'Qty', 'Amount','QuotationId','QuotationTransId'))
                    ->where("b.TenderEnquiryId=$TenderEnquiryId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $arr_quotations = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(sizeof($arr_quotations) > 0) {
                    $response = $this->getResponse()->setContent(json_encode($arr_quotations));
                } else {
                    $response = $this->getResponse()
                        ->setStatusCode('201')
                        ->setContent('No data.');
                }
                return $response;
            }
        } else {

            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('NameOfWork', 'TenderEnquiryId'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $arr_enquires = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(sizeof($arr_enquires) > 0)
                $this->_view->arr_enquires = $arr_enquires;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function getratehistoryAction()
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
                $resourceId= intval($this->bsf->isNullCheck($postParams['resourceId'],'number'));
                $vendorId= intval($this->bsf->isNullCheck($postParams['vendorId'],'number'));
                $projectId= intval($this->bsf->isNullCheck($postParams['projectId'],'number'));
                $fromDate= $this->bsf->isNullCheck($postParams['fromDate'],'date');
                $toDate= $this->bsf->isNullCheck($postParams['toDate'],'date');

                $sql = new Sql($dbAdapter);

                $where = "b.BillDate >= '" . date('d-M-Y', strtotime($fromDate)) . "' and b.BillDate <='". date('d-M-Y', strtotime($toDate)) ."'";
                $where =  $where . " and a.ResourceId  = " . $resourceId;
                if ($projectId !=0) $where =  $where . " and b.CostCentreId  = " . $projectId;
                if ($vendorId !=0) $where =  $where . " and b.VendorId  = " . $vendorId;

                $select = $sql->select();
                $select->from(array('a'=>'MMS_PVTRans'))
                    ->join(array('b' => 'MMS_PVRegister'), 'a.PVRegisterId=b.PVRegisterId', array('BillDate'=>new Expression("Format(BillDate, 'dd-MM-yyyy')")), $select::JOIN_LEFT)
                    ->columns(array('Rate'));
                $select->where($where);
                $select->order('b.BillDate');
                $statement = $sql->getSqlStringForSqlObject($select);
                $trans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a'=>'MMS_PVTRans'))
                    ->join(array('b' => 'MMS_PVRegister'), 'a.PVRegisterId=b.PVRegisterId', array('BillDate'=>new Expression("Format(BillDate, 'dd-MM-yyyy')"),'BillNo'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Vendor_Master'), 'b.VendorId=c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                    ->join(array('d' => 'WF_OperationalCostCentre'), 'b.CostcentreId=d.CostcentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->columns(array('PVTransId','Rate'));
                $select->where($where);
                $select->order('b.BillDate');

                $statement = $sql->getSqlStringForSqlObject($select);
                $billtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $data = array();
                $data['trans'] = $trans;
                $data['billtrans'] = $billtrans;

                $response = $this->getResponse();
                $response->setContent(json_encode($data));

                return $response;
            }
        }
    }

    public function getratehistoryprojectAction()
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
                $resourceId= intval($this->bsf->isNullCheck($postParams['resourceId'],'number'));

                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from(array('a'=>'Proj_ProjectResource'))
                    ->join(array('b' => 'Proj_ProjectMaster'), 'a.ProjectId=b.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                    ->columns(array('ProjectId','Rate'));
                $select->where(array('a.ResourceId'=>$resourceId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $trans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $data = array();
                $data['trans'] = $trans;

                $response = $this->getResponse();
                $response->setContent(json_encode($data));

                return $response;
            }
        }
    }

    public function resourcerateinprojectAction(){
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

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_Resource')
                ->columns(array('ResourceId', 'ResourceName'))
                ->where('TypeId <>3');
//                ->where(array('TypeId'=>'2'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->Resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function projectsummaryAction(){
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

            $projectId = $this->bsf->isNullCheck( $this->params()->fromRoute('projectId'), 'number' );

            $sql = new Sql($dbAdapter);
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectName'))
                ->where(array('ProjectId'=>$projectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $projMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $sProjName = "";
            if (!empty($projMaster)) $sProjName=$projMaster['ProjectName'];

            $this->_view->projectId =$projectId;
            $this->_view->projectName =$sProjName;


            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('data' => 'ProjectId', "value"=>'ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function getprojectsummaryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dboqAmt = 0;
                $dohAmt = 0;
                $dnetAmt = 0;

                $select = $sql->select();
                $select->from('Proj_ProjectIOW')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dboqAmt = $results['Amount'];

                $select = $sql->select();
                $select->from('Proj_OHAbstract')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dohAmt = $results['Amount'];

                $dnetAmt =  $dboqAmt + $dohAmt;
                $data['BOQAmt'] =  $dboqAmt;
                $data['OHAmt'] =  $dohAmt;
                $data['NetAmt'] =  $dnetAmt;

                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }

    public function getworkgroupsummaryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dboqAmt = 0;

                $select = $sql->select();
                $select->from('Proj_ProjectIOW')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dboqAmt = $results['Amount'];

                if ($dboqAmt !=0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectIOW'))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectWorkGroup'), 'b.PWorkGroupId=c.PWorkGroupId', array(), $select::JOIN_LEFT)
                        ->columns(array('WorkGroup' => new Expression("c.WorkGroupName"), 'Amount' => new Expression("sum(a.Amount)"),'Per'=>new Expression("(sum(a.Amount)/$dboqAmt)*100")))
                        ->where(array("a.ProjectId" => $projectId))
                        ->group(array('c.WorkGroupName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else  $results = [];

                $response = $this->getResponse();
                $response->setContent(json_encode($results ));
                return $response;
            }
        }
    }
    public function getohsummaryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dboqAmt = 0;

                $select = $sql->select();
                $select->from('Proj_OHAbstract')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dboqAmt = $results['Amount'];

                if ($dboqAmt !=0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_OHAbstract'))
                        ->join(array('b' => 'Proj_OHMaster'), 'a.OHId=b.OHId', array(), $select::JOIN_LEFT)
                        ->columns(array('OHName' => new Expression("b.OHName"), 'Amount' => new Expression("sum(a.Amount)"), 'Per' => new Expression("(sum(a.Amount)/$dboqAmt)*100")))
                        ->where(array("a.ProjectId" => $projectId))
                        ->group(array('b.OHName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else $results =[];

                $response = $this->getResponse();
                $response->setContent(json_encode($results));
                return $response;
            }
        }
    }
    public function getresourcetypesummaryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dboqAmt = 0;

                $select = $sql->select();
                $select->from('Proj_ProjectResource')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dboqAmt = $results['Amount'];

                if ($dboqAmt !=0) {

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectResource'))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ResourceType'), 'b.TypeId=c.TypeId', array(), $select::JOIN_LEFT)
                        ->columns(array('ResourceType' => new Expression("c.TypeName"), 'Amount' => new Expression("sum(a.Amount)"),'Per' => new Expression("(sum(a.Amount)/$dboqAmt)*100")))
                        ->where(array("a.ProjectId" => $projectId))
                        ->group(array('c.TypeName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else $results = [];

                $response = $this->getResponse();
                $response->setContent(json_encode($results));
                return $response;
            }
        }
    }
    public function getresourcesummaryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dboqAmt = 0;

                $select = $sql->select();
                $select->from('Proj_ProjectResource')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dboqAmt = $results['Amount'];

                if ($dboqAmt!=0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectResource'))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $select::JOIN_LEFT)
                        ->columns(array('ResourceName' => new Expression("b.ResourceName"), 'Unit' => new Expression("c.UnitName"), 'Qty' => new Expression("Sum(a.Qty)"), 'Rate', 'Amount' => new Expression("sum(a.Amount)"),'Per' => new Expression("(sum(a.Amount)/$dboqAmt)*100")))
                        ->where(array("a.ProjectId" => $projectId))
                        ->group(array('b.ResourceName','c.UnitName','a.Rate'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else $results = [];

                $response = $this->getResponse();
                $response->setContent(json_encode($results));
                return $response;
            }
        }
    }

    public function getbudgetboqsummaryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dboqAmt = 0;

                $select = $sql->select();
                $select->from('Proj_ProjectIOW')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dboqAmt = $results['Amount'];

                if ($dboqAmt!=0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectIOW'))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $select::JOIN_LEFT)
                        ->columns(array('SerialNo' => new Expression("b.RefSerialNo"),'Specification'=>new Expression("b.Specification"),'Unit' => new Expression("c.UnitName"), 'Qty', 'Rate', 'Amount','Per' => new Expression("(a.Amount/$dboqAmt)*100")))
                        ->where(array("a.ProjectId" => $projectId))
                        ->order(array('b.SortId','b.RefSerialNo'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else $results = [];

                $response = $this->getResponse();
                $response->setContent(json_encode($results));
                return $response;
            }
        }
    }
    public function getplanboqsummaryAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dboqAmt = 0;

                $select = $sql->select();
                $select->from('Proj_ProjectIOWPlan')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dboqAmt = $results['Amount'];

                if ($dboqAmt!=0) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ProjectIOWPlan'))
                        ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $select::JOIN_LEFT)
                        ->columns(array('SerialNo' => new Expression("b.RefSerialNo"),'Specification'=>new Expression("b.Specification"),'Unit' => new Expression("c.UnitName"), 'Qty', 'Rate', 'Amount','Per' => new Expression("(a.Amount/$dboqAmt)*100")))
                        ->where(array("a.ProjectId" => $projectId))
                        ->order(array('b.SortId','b.RefSerialNo'));

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else $results = [];

                $response = $this->getResponse();
                $response->setContent(json_encode($results));
                return $response;
            }
        }
    }
    public function getbudgetvsplanAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $dbudgetAmt=0;
                $dplanAmt=0;
                $dVar =0;

                $select = $sql->select();
                $select->from('Proj_ProjectIOW')
                    ->columns(array('Amount'=>new Expression("sum(Amount)")))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dbudgetAmt = $results['Amount'];

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectIOWPlan'))
                    ->join(array('b' => 'Proj_ProjectIOWPlan'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                    ->columns(array('Amount'=>new Expression("sum(b.Qty*a.Rate)")))
                    ->where(array("a.ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($results)) $dplanAmt = $results['Amount'];

                if ($dbudgetAmt !=0) $dVar = ($dplanAmt/$dbudgetAmt)*100;


                $select = $sql->select();
                $select->from(array('a' => 'Proj_ProjectIOW'))
                    ->join(array('b' => 'Proj_ProjectIOWPlan'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_UOM'), 'c.UnitId=d.UnitId', array(), $select::JOIN_LEFT)
                    ->columns(array('SerialNo' => new Expression("c.RefSerialNo"),
                        'Specification'=>new Expression("c.Specification"),'Unit' => new Expression("d.UnitName"),'Rate',
                        'BudgetQty'=> new Expression("a.Qty"),'BudgetAmount' => new Expression("a.Amount"),
                        'PlanQty'=> new Expression("b.Qty"),'PlanAmount' => new Expression("b.Qty*a.Rate"),
                        'Per' => new Expression("case when a.Amount <>0 then ((b.Qty*a.Rate)/a.Amount)*100 else 0 end")))
                    ->where(array("a.ProjectId" => $projectId))
                    ->order(array('c.SortId','c.RefSerialNo'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $data['arrData'] =$results;
                $data['variance'] =$dVar;

                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
    public function getresourcerequirementAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $dFromDate= $this->bsf->isNullCheck($postParams['fromDate'],'date');
                $dToDate= $this->bsf->isNullCheck($postParams['toDate'],'date');
                $typeId= $this->bsf->isNullCheck($postParams['typeId'],'number');
                $iRepType =$this->bsf->isNullCheck($postParams['repTypeId'],'number');
                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));
                $periodtype =  intval($this->bsf->isNullCheck($postParams['periodType'],'number'));
                $data = array();
                $details = array();
                $grouptotal =array();

                if ($iRepType ==1) {
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ScheduleDetails'))
                        ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'Proj_UOM'), 'd.WorkUnitId=f.UnitId', array(), $select::JOIN_LEFT)
                        ->columns(array('ResourceId' => new Expression("b.ResourceId"), 'Code' => new Expression("d.Code"), 'ResourceName' => new Expression("d.ResourceName"), 'Unit' => new Expression("Case When D.TypeId=3 then F.UnitName else E.UnitName End"),
                            'Qty' => new Expression("sum((B.Qty/C.WorkingQty)*A.SQty)"), 'Rate' => new Expression("b.Rate"), 'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));

                    $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                    $where = $where . " and a.ProjectId = " . $projectId;
                    if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                    $select->where($where);

                    $select->group(new Expression('b.ResourceId,d.Code,d.ResourceName,e.UnitName,f.UnitName,b.Rate,d.TypeId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ScheduleDetails'))
                        ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ResourceType'), 'd.TypeId=e.TypeId', array(), $select::JOIN_LEFT)
                        ->columns(array('TypeName' => new Expression("e.TypeName"), 'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));
                    $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                    $where = $where . " and a.ProjectId = " . $projectId;
                    if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                    $select->where($where);

                    $select->group(new Expression('e.TypeName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $typetotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_ScheduleDetails'))
                        ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ResourceGroup'), 'd.ResourceGroupId=e.ResourceGroupId', array(), $select::JOIN_LEFT)
                        ->columns(array('ResourceGroupName' => new Expression("e.ResourceGroupName"), 'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));
                    $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                    $where = $where . " and a.ProjectId = " . $projectId;
                    if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                    $select->where($where);

                    $select->group(new Expression('e.ResourceGroupName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $grouptotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $data['details'] = $details;
                    $data['typetotal'] = $typetotal;
                    $data['grouptotal'] = $grouptotal;

                } else {
                    if ($periodtype==3) {

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ScheduleDetails'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('f' => 'Proj_UOM'), 'd.WorkUnitId=f.UnitId', array(), $select::JOIN_LEFT)
                            ->columns(array('SDate'=>new Expression("Format(SDate, 'dd/MM/yyyy')"),'ResourceId' => new Expression("b.ResourceId"), 'Code' => new Expression("d.Code"), 'ResourceName' => new Expression("d.Code + '  ' +d.ResourceName + ' ('+ ltrim(str(b.Rate)) + '/'+ Case When D.TypeId=3 then F.UnitName else E.UnitName End +')'"),
                                'Qty' => new Expression("sum((B.Qty/C.WorkingQty)*A.SQty)"), 'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));

                        $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                        $where = $where . " and a.ProjectId = " . $projectId;
                        if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                        $select->where($where);

                        $select->group(new Expression('a.SDate,b.ResourceId,d.Code,d.ResourceName,e.UnitName,f.UnitName,b.Rate,d.TypeId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ScheduleDetails'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->columns(array('SDate'=>new Expression("Format(SDate, 'dd/MM/yyyy')"),'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));

                        $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                        $where = $where . " and a.ProjectId = " . $projectId;
                        if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                        $select->where($where);

                        $select->group(new Expression('a.SDate'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $grouptotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else if ($periodtype==2) {

                        $dSDate = date('d-M-Y', strtotime($dFromDate));

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ScheduleDetails'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('f' => 'Proj_UOM'), 'd.WorkUnitId=f.UnitId', array(), $select::JOIN_LEFT)
                            ->columns(array('SDate'=>new Expression("'Week'+ltrim(str((DatePart(wk,a.SDate)-DatePart(wk,'$dSDate')+1)))"),'ResourceId' => new Expression("b.ResourceId"), 'Code' => new Expression("d.Code"), 'ResourceName' => new Expression("d.Code + '  ' +d.ResourceName + ' ('+ ltrim(str(b.Rate)) + '/'+ Case When D.TypeId=3 then F.UnitName else E.UnitName End +')'"),
                                'Qty' => new Expression("sum((B.Qty/C.WorkingQty)*A.SQty)"), 'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));

                        $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                        $where = $where . " and a.ProjectId = " . $projectId;
                        if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                        $select->where($where);

                        $select->group(new Expression('DatePart(wk,a.SDate),b.ResourceId,d.Code,d.ResourceName,e.UnitName,f.UnitName,b.Rate,d.TypeId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ScheduleDetails'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->columns(array('SDate'=>new Expression("'Week'+ltrim(str((DatePart(wk,a.SDate)-DatePart(wk,'$dSDate')+1)))"),'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));

                        $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                        $where = $where . " and a.ProjectId = " . $projectId;
                        if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                        $select->where($where);

                        $select->group(new Expression('DatePart(wk,a.SDate)'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $grouptotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    } else if ($periodtype==1) {

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ScheduleDetails'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('f' => 'Proj_UOM'), 'd.WorkUnitId=f.UnitId', array(), $select::JOIN_LEFT)
                            ->columns(array('SDate'=>new Expression("Format(Convert(Date,'01/'+str(month(a.SDate))+'/'+str(year(a.SDate)),103), 'MMM yyyy')"),'ResourceId' => new Expression("b.ResourceId"), 'Code' => new Expression("d.Code"), 'ResourceName' => new Expression("d.Code + '  ' +d.ResourceName + ' ('+ ltrim(str(b.Rate)) + '/'+ Case When D.TypeId=3 then F.UnitName else E.UnitName End +')'"),
                                'Qty' => new Expression("sum((B.Qty/C.WorkingQty)*A.SQty)"), 'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));

                        $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                        $where = $where . " and a.ProjectId = " . $projectId;
                        if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                        $select->where($where);

                        $select->group(new Expression('Month(a.SDate),Year(a.SDate),b.ResourceId,d.Code,d.ResourceName,e.UnitName,f.UnitName,b.Rate,d.TypeId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ScheduleDetails'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                            ->columns(array('SDate'=>new Expression("Format(Convert(Date,'01/'+str(month(a.SDate))+'/'+str(year(a.SDate)),103), 'MMM yyyy')"),'Amount' => new Expression("sum(((B.Qty/C.WorkingQty)*A.SQty)*B.Rate)")));

                        $where = "a.SDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.SDate <='" . date('d-M-Y', strtotime($dToDate)) . "'";
                        $where = $where . " and a.ProjectId = " . $projectId;
                        if ($typeId != 0) $where = $where . " and d.TypeId  = " . $typeId;
                        $select->where($where);

                        $select->group(new Expression('month(a.SDate),year(a.SDate)'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $grouptotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    }
                    $data['details'] = $details;
                    $data['grouptotal'] = $grouptotal;
                }

                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
    public function getprojectShDateAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_Schedule'))
                    ->columns(array('FDate'=>new Expression("Format(Min(StartDate), 'dd/MM/yyyy')"),'EDate' => new Expression("Format(Max(EndDate), 'dd/MM/yyyy')")))
                    ->where(array('ProjectId'=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $response = $this->getResponse();
                $response->setContent(json_encode($arrDate));
                return $response;
            }
        }
    }
}