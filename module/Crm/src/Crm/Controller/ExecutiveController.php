<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Crm\Controller;

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

class ExecutiveController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }
    public function indexAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');
        return $this->_view;
    }
    public function _lastday($month, $year) {
        if (empty($month)) {
            $month = date('m');
        }
        if (empty($year)) {
            $year = date('Y');
        }
        $result = strtotime("{$year}-{$month}-01");
        $result = strtotime('-1 second', strtotime('+1 month', $result));
        return date('d', $result);
    }
    public function targetAction(){
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
		$userId = $this->auth->getIdentity()->UserId;
        $targetId= $this->params()->fromRoute('targetId');
        //ProjectMaster//
        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->order('ProjectId desc');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsProject = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $select = $sql->select();
        //Period Master//
        $select->from('Crm_MonthMaster')
            ->columns(array('MonthId','MonthDivide'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsMonth = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Executive Master//
        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserId','UserName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsExecutive = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
		    $exeId = $postParams['ExeId'];
            $targetfrom = $this->bsf->isNullCheck($postParams['target_from'], 'string');
            $targetperiod = $this->bsf->isNullCheck($postParams['periodId'], 'number');
            $projectid = $this->bsf->isNullCheck($postParams['projectId'], 'number');
            $count = $this->bsf->isNullCheck($postParams['targetcount'], 'number');

            //target Master insert//
            $insert  = $sql->insert('Crm_TargetRegister');
            $newData = array(
                'TargetPeriod' => $targetperiod,
                'ProjectId' => $projectid,
                'TargetFrom' => $targetfrom,
                'CreatedDate' => date('m-d-Y H:i:s'),
                'Terms' => $count
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $targetresult =  $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            $targetId = $dbAdapter->getDriver()->getLastGeneratedValue();
            //target trans//

            for($i=0;$i<$count;$i++) {
                foreach($exeId as $exe){
                    $td= $this->_lastday(date('m', strtotime($postParams['targetToPeriod'][$i])),date('Y', strtotime($postParams['targetToPeriod'][$i])));
                    $select = $sql->insert('Crm_TargetTrans');
                    $newData = array(
                        'FMonth' => date('m', strtotime($postParams['targetFromPeriod'][$i])),
                        'FYear'=> date('Y', strtotime($postParams['targetFromPeriod'][$i])),
                        'TMonth' => date('m', strtotime($postParams['targetToPeriod'][$i])),
                        'TYear'=> date('Y', strtotime($postParams['targetToPeriod'][$i])),
                        'ExecutiveId'=>$exe,
                        'TValue'=>$postParams['targetAmount_'.$i.'_'.$exe],
                        'TUnits'=>$postParams['targetUnit_'.$i.'_'.$exe],
                        'TargetId'=>$targetId,
                        'FromDate' => date('Y', strtotime($postParams['targetFromPeriod'][$i])).'-'.date('m', strtotime($postParams['targetFromPeriod'][$i])).'-01',
                        'ToDate' =>  date('Y', strtotime($postParams['targetToPeriod'][$i])).'-'.date('m', strtotime($postParams['targetToPeriod'][$i])).'-'.$td
                    );
                    $select->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
            }
			$connection->commit();
            CommonHelper::insertLog(date('Y-m-d H:i:s'),'Executive-Target-Entry-Add','N','Executive-Target-Entry',$targetId,0, 0, 'CRM','',$userId, 0 ,0);
            $FeedId = $this->params()->fromQuery('FeedId');
            $AskId = $this->params()->fromQuery('AskId');
            if((isset($FeedId) && $FeedId!="")) {
                $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
            } else {
                $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"));
            }
//            $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"));
        }
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function targetTransAction(){
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
            $response = $this->getResponse();

            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projectId=$postParams['project'];
                //select executives from Lead Register

                $PositionTypeId=array(5,2);
                $sub = $sql->select();
                $sub->from(array('a'=>'WF_PositionMaster'))
                        ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                        ->columns(array('PositionId'))
                        ->where(array("b.PositionTypeId"=>$PositionTypeId));

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId','EmployeeName','UserName'))
                ->where->expression("PositionId IN ?",array($sub));
                $select->where(array("DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultsExe= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select = $sql->select();
//                $select ->from(array("a"=>'Crm_Leads'))
//                    ->join(array("c"=>"Crm_LeadProjects"),"a.LeadId=c.LeadId",array(),$select::JOIN_INNER)
//                    ->join(array("d"=>"WF_Users"),"a.ExecutiveId=d.UserId",array("UserId"),$select::JOIN_LEFT)
//                    ->columns(array("ProjectId"))
//                    ->where(array("c.ProjectId"=>$projectId));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                //onload values//
                $this->_view->results=$resultsExe;
                $this->_view->targetType=$postParams['targettype'];
                $this->_view->targetAmount=$postParams['tamount'];
                $this->_view->targetUnit=$postParams['tunit'];
                $this->_view->targetFrom=$postParams['tfrom'];
                $this->_view->iCount=$postParams['count'];

                $this->_view->setTerminal(true);
                return $this->_view;
            }
        }
    }

	public function registerAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" || Executive Register");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from( array('a' => 'Crm_TargetRegister'))
            ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Crm_MonthMaster'), 'c.MonthId=a.TargetPeriod', array('MonthDivide'), $select::JOIN_LEFT)
            ->columns(array('TargetId','ProjectId','TargetFrom','CreatedDate'=> new Expression("FORMAT(a.CreatedDate,'dd-MM-yyyy')")))
            ->where("a.DeleteFlag='0'")
			 ->order("a.TargetId desc");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->targetDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


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
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

    public function deleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
		$userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $TargetId = $this->bsf->isNullCheck($this->params()->fromPost('TargetId'), 'number');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $update = $sql->update();
                    $update->table('Crm_TargetRegister')
                        ->set(array('DeleteFlag' => '1','ModifiedDate' => date('Y/m/d H:i:s')))
                        ->where(array('TargetId' => $TargetId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Executive-Target-Entry-Delete','D','Executive-Target-Entry',$TargetId,0, 0, 'CRM','',$userId, 0 ,0);

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

    public function editAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" || Executive Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		$userId = $this->auth->getIdentity()->UserId;
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
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $postParams = $request->getPost();
                    $exeId = $postParams['ExeId'];
                    $targetId = $postParams['Target_Id'];
                    $count = $postParams['target_count'];
                    $connection->beginTransaction();

                    //Delete target trans//
                    $delete = $sql->delete();
                    $delete->from('Crm_TargetTrans')
                        ->where("TargetId='$targetId'");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    //Insert target trans//
                    for ($i = 0; $i < $count; $i++) {
                        foreach ($exeId as $exe) {
                            $td= $this->_lastday(date('m', strtotime($postParams['targetToPeriod'][$i])),date('Y', strtotime($postParams['targetToPeriod'][$i])));

                            $insert = $sql->insert('Crm_TargetTrans');
                            $newData = array(
                                'FMonth' => date('m', strtotime($postParams['targetFromPeriod'][$i])),
                                'FYear' => date('Y', strtotime($postParams['targetFromPeriod'][$i])),
                                'TMonth' => date('m', strtotime($postParams['targetToPeriod'][$i])),
                                'TYear' => date('Y', strtotime($postParams['targetToPeriod'][$i])),
                                'ExecutiveId' => $exe,
                                'TValue' => $postParams['targetAmount_' . $i . '_' . $exe],
                                'TUnits' => $postParams['targetUnit_' . $i . '_' . $exe],
                                'TargetId' => $targetId,
                                'FromDate' => date('Y', strtotime($postParams['targetFromPeriod'][$i])).'-'.date('m', strtotime($postParams['targetFromPeriod'][$i])).'-01',
                                'ToDate' =>  date('Y', strtotime($postParams['targetToPeriod'][$i])).'-'.date('m', strtotime($postParams['targetToPeriod'][$i])).'-'.$td
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Update target register modified date
                    $update = $sql->update();
                    $update->table('Crm_TargetRegister')
                        ->set(array('ModifiedDate'=> date('m-d-Y H:i:s')))
                        ->where("TargetId='$targetId'");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Executive-Target-Entry-Modify','E','Executive-Target-Entry',$targetId,0, 0, 'CRM','',$userId, 0 ,0);
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"));

                    }
//                    $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
                $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"));
            } else {
                $editId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'TargetId' ), 'number' );
                if($editId==0){
                    $this->redirect()->toRoute("crm/default", array("controller" => "executive","action" => "register"));
                }
                $select = $sql->select();
                $select->from( array('a' => 'Crm_TargetRegister'))
                    ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId', array('ProjectName','ProjectId'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Crm_MonthMaster'),'c.MonthId=a.TargetPeriod',array('MonthDivide'),$select::JOIN_LEFT)
                    ->columns(array('TargetId','ProjectId','TargetFrom','CreatedDate','Terms','TargetPeriod'))
                    ->where("a.DeleteFlag='0' AND a.TargetId='$editId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->targetDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select ->from(array("a"=>'Crm_TargetRegister'))
                    ->columns(array('TargetPeriod'))
                    ->where("a.DeleteFlag='0' AND a.TargetId='$editId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $resTargetReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
				$PeriodType=$resTargetReg['TargetPeriod'];
				
				$select = $sql->select();
                $select ->from(array("a"=>'Crm_TargetTrans'))
                    ->join(array("b"=>"WF_Users"),"a.ExecutiveId=b.UserId",array("UserName"),$select::JOIN_INNER)                   
                    ->columns(array('ExecutiveId'),array("UserName"))
                    ->where("a.DeleteFlag='0' AND a.TargetId='$editId'")
                    ->group(new Expression("a.ExecutiveId,b.UserName"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $executiveList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				
				$select = $sql->select();
                $select ->from(array("a"=>'Crm_TargetTrans'))                  
                    ->columns(array('FMonth','FYear','TMonth','TYear','MonthValue' => new Expression("FMonth") ))
                    ->where("a.DeleteFlag='0' AND a.TargetId='$editId'")
                    ->group(new Expression("a.FMonth,a.FYear,a.TMonth,a.TYear"))
					->order(new Expression("a.FYear,a.TYear,MonthValue ASC"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $transList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
				
				$arrUnitLists= array();
				
				foreach($transList as &$transLists) {
                    $cFromMonth = date("M", mktime(0, 0, 0, $transLists['FMonth']));
                    $cToMonth = date("M", mktime(0, 0, 0, $transLists['TMonth']));
                    $FMonth=$transLists['FMonth'];
                    $FYear=$transLists['FYear'];
                    $TMonth=$transLists['TMonth'];
                    $TYear=$transLists['TYear'];

                    $dumArr=array();
                    $strDesc="";
                    if($PeriodType==1){
                        $strDesc=$cFromMonth. " " .$transLists['FYear'];
                    } else{
                        $strDesc=$cFromMonth. " " .$transLists['FYear']. " - " .$cToMonth. " " .$transLists['TYear'];
                    }
                    $dumArr = array(
                        'Description' => $strDesc,
                        'Unit' => 0,
                        'Amount' => 0
                    );
                    $totUnit=0;
                    $totAmount=0;
                    foreach($executiveList as &$executiveLists) {
                        $ExecutiveId=$executiveLists['ExecutiveId'];

                        $selectAmt = $sql->select();
                        $selectAmt->from(array("a"=>"Crm_TargetTrans"))
                            ->columns(array('TValue', 'TUnits'));
                        $selectAmt->where("a.TargetId='$editId' and a.ExecutiveId='$ExecutiveId' and a.FMonth='$FMonth' and a.FYear='$FYear' and a.TMonth='$TMonth' and a.TYear='$TYear' ");
                        $statement = $statement = $sql->getSqlStringForSqlObject($selectAmt);
                        $unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $dumArr['Unit_'.$ExecutiveId] = $unitdet['TUnits'];
                        $dumArr['Amount_'.$ExecutiveId] = $unitdet['TValue'];

                        $totUnit +=$unitdet['TUnits'];
                        $totAmount +=$unitdet['TValue'];
                    }
				$dumArr['Unit'] = $totUnit;
				$dumArr['Amount'] = $totAmount;
				$arrUnitLists[] =$dumArr;
			    }
                $this->_view->executiveList = $executiveList;
                $this->_view->arrUnitLists = $arrUnitLists;
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function treeViewAction(){
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

        $projectId = $this->params()->fromRoute('projectId');

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
            $select ->from(array("a"=>'Crm_Leads'))
                ->join(array("c"=>"Crm_LeadProjects"),"a.LeadId=c.LeadId",array("ProjectId"),$select::JOIN_INNER)
                ->join(array("d"=>"WF_Users"),"a.ExecutiveId=d.UserId",array("UserId"),$select::JOIN_INNER)
                ->columns(array(new Expression('DISTINCT(d.UserName) as UserName, a.ExecutiveId as ExecutiveId')),array("ProjectId"),array("UserName"))
                ->where(array("c.ProjectId"=>$projectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $results = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $store_all_id = array();
            foreach($results as $actityMaster) {
                array_push($store_all_id, $actityMaster['UserId']);
            }
            //echo '<pre>'; print_r($store_all_id); die;
            $this->_view->store_all_id = $store_all_id;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}