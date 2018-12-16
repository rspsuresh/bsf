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

class BrokerController extends AbstractActionController
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

	public function registerAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		// $this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		
		if($this->getRequest()->isXmlHttpRequest()){
			$request = $this->getRequest();
			$response = $this->getResponse();
			if ($request->isPost()) {
			//Write your Ajax post code here
				$postParam = $request->getPost();
			}
		    } else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here			
			}
			else{
				$select = $sql->select();
				$select ->from(array("a"=>'Crm_BrokerMaster'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->brokerReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$select = $sql->select();
				$select ->from(array("a"=>'Crm_BrokerMaster'))
				        ->columns(array(new Expression("a.BrokerId,CONVERT(varchar(10),a.BrokerDate,105) as BrokerDate,a.BrokerName,a.EmailId,a.Mobile,'' Projects")))
				        ->order('a.BrokerId desc');
				$statement = $sql->getSqlStringForSqlObject($select); 
				$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$icount = 0;
				foreach ($results as $resu){
					$brokerId = $resu['BrokerId'];
					
					//Lead Projects List Append
					$strCCName="";
					$selectMultiCC = $sql->select();
					$selectMultiCC->from(array("a"=>"Crm_BrokerProjects"));
					$selectMultiCC->columns(array("ProjectId"),array("ProjectName"))
										->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiCC::JOIN_INNER);
					$selectMultiCC->where(array("a.BrokerId"=>$brokerId));
					$statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC); 
					$resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					$proj = array();
					if($resultMultiCC ){
						foreach($resultMultiCC as $multiCC){
							array_push($proj, $multiCC['ProjectName']);
						}
						$strCCName = implode(",", $proj);
					}
					$results[$icount]['Projects']=$strCCName;
					$icount=$icount+1;
			}
			$this->_view->brokerRegister=$results;
			
		}
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
	}}

	public function followupAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		// $this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		$brokerId = $this->params()->fromRoute('BrokerId');
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
					 $connection = $dbAdapter->getDriver()->getConnection();
					 $connection->beginTransaction();
					 //Write your Normal form post code here
					  $postParams = $request->getPost();
							   $insert      = $sql->insert('Crm_BrokerFollowup');
								$newData     = array(
								'RefDate' =>  $this->bsf->isNullCheck( date('m-d-Y',strtotime($postParams['RefDate'])), 'string'),
								'BrokerId' => $brokerId,
								//'FollowupBrokerId' => $this->bsf->isNullCheck($postParams['Broker'], 'number'),
								'ProjectsId' =>  $this->bsf->isNullCheck($postParams['Project'], 'number'),
								'LeadId'=> $this->bsf->isNullCheck($postParams['Lead'], 'number'),
								'NextCallDate'=> $this->bsf->isNullCheck( date('m-d-Y',strtotime($postParams['nextcall'])), 'string'),
								'Remarks'  =>  $this->bsf->isNullCheck($postParams['Remarks'], 'string'),					
								);
								$insert->values($newData);
							    $statement = $sql->getSqlStringForSqlObject($insert); 
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								$registerId = $dbAdapter->getDriver()->getLastGeneratedValue();
								$connection->commit();
                                CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-BrokerFollowup-Entry-Add','N','CRM-BrokerFollowup-Entry',$registerId,0, 0, 'CRM', '',$userId, 0 ,0);

                                $this->redirect()->toRoute('crm/default', array('controller' => 'broker', 'action' => 'followup-register'));
					}
					else{

					    //broker select
						$select = $sql->select();
						$select->from(array('a' => 'Crm_BrokerMaster'))
							->columns(array('BrokerId', 'BrokerName'));
						$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->responseBro= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						//project select
						$select = $sql->select();
						$select->from(array('a' => 'Proj_ProjectMaster'))
							->columns(array('ProjectId', 'ProjectName'));
						$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->responseProject = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						//Lead select
						$select = $sql->select();
						$select->from(array('a' => 'Crm_Leads'))
							->columns(array('LeadId', 'LeadName'))
							->where(array('a.BrokerId'=>$brokerId));
						$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						//current broker select
						$select = $sql->select();
						$select->from(array("a"=>'Crm_BrokerMaster'))
                              ->where(array('a.BrokerId' => $brokerId));
						$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->Broker = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
						
						$select = $sql->select();
						$select->from(array("a"=>'Crm_BrokerFollowup'))
							->columns(array('RefDate'=>new Expression("CONVERT(varchar(10),RefDate,105)"),'FollowupId','BrokerId','ProjectsId','LeadId','Remarks','NextCallDate'))
							->join(array("b"=>"Proj_ProjectMaster"),"a.ProjectsId=b.ProjectId",array("ProjectName"),$select::JOIN_LEFT)
							->join(array("d"=>"Crm_Leads"),"a.LeadId=d.LeadId",array("LeadName"),$select::JOIN_LEFT)
							->join(array("c"=>"Crm_BrokerMaster"),"a.FollowupBrokerId=c.BrokerId",array("BrokerName"),$select::JOIN_LEFT)
							    ->where(array('a.BrokerId'=>$brokerId))
				                 ->order("a.FollowupId Desc");
						$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->responseBroker = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

						$select = $sql->select();
						$select->from(array('a' => 'Crm_BrokerMaster'))
							->columns(array('*'))
						    ->where(array('a.BrokerId'=>$brokerId))	;
						$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->Brokerdet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
						
						$selectMultiProject = $sql->select();
						$selectMultiProject->from(array("a"=>"Crm_BrokerProjects"));
						$selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
											->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
						$selectMultiProject->where(array("a.BrokerId"=>$brokerId));
						$statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject); 
						$this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
					}
					//Common function
					$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
					
					return $this->_view;
				}
	}
	
	public function followupDetailsAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		// $this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
		$sql = new Sql($dbAdapter);
		$followupId = $this->params()->fromRoute('FollowupId');
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
				$postParams = $request->getPost();
				//begin trans try block example starts
				$connection = $dbAdapter->getDriver()->getConnection();
				$connection->beginTransaction();
				try {
					
					$update = $sql->update();
					$update->table('Crm_BrokerFollowup');
					$update->set(array(
						'RefDate' =>  $this->bsf->isNullCheck( date('m-d-Y',strtotime($postParams['RefDate'])), 'string'),
						//'FollowupBrokerId' => $this->bsf->isNullCheck($postParams['Broker'], 'number'),
						'BrokerId' => $this->bsf->isNullCheck($postParams['bname'], 'number'),
						'ProjectsId' =>  $this->bsf->isNullCheck($postParams['Project'], 'number'),
						'LeadId'=> $this->bsf->isNullCheck($postParams['Lead'], 'number'),
						'Remarks'  =>  $this->bsf->isNullCheck($postParams['Remarks'], 'string'),
						'NextCallDate'=> $this->bsf->isNullCheck( date('m-d-Y',strtotime($postParams['nextcall'])), 'string'),									
					));
					$update->where(array('FollowupId'=>$followupId));
					$statement = $sql->getSqlStringForSqlObject($update);
					$resultUpdate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-BrokerFollowup-Entry-Modify','E','CRM-BrokerFollowup-Entry',$followupId,0, 0, 'CRM', '',$userId, 0 ,0);

                    $this->redirect()->toRoute('crm/followup-register', array('controller' => 'broker', 'action' => 'followup-register'));
												
				} catch(PDOException $e){
					$connection->rollback();
					print "Error!: " . $e->getMessage() . "</br>";
				}
			//begin trans try block example ends
			}
			else{
				//broker select
				$select = $sql->select();
				$select->from(array('a' => 'Crm_BrokerMaster'))
					->columns(array('BrokerId', 'BrokerName'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->responseBro= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				//project select
				$select = $sql->select();
				$select->from(array('a' => 'Proj_ProjectMaster'))
					->columns(array('ProjectId', 'ProjectName'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->responseProject = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				//lead select
				$select = $sql->select();
				$select->from(array('a' => 'Crm_Leads'))
					->columns(array('LeadId', 'LeadName'))
					->join(array("c"=>"Crm_BrokerFollowup"),"a.BrokerId=c.BrokerId",array("BrokerId"),$select::JOIN_LEFT)
					->where(array('c.FollowupId'=>$followupId));
				$statement = $sql->getSqlStringForSqlObject($select); 
				$this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				//followup last
				$select = $sql->select();
				$select->from(array("a"=>'Crm_BrokerFollowup'))
						->columns(array('RefDate'=>new Expression("CONVERT(varchar(10),RefDate,105)"),'BrokerId','ProjectsId','FollowupBrokerId','LeadId','Remarks','NextCallDate'),
						array("BrokerName"), array("LeadName"),array("ProjectName"))
						->join(array("b"=>"Proj_ProjectMaster"),"a.ProjectsId=b.ProjectId",array("ProjectName"),$select::JOIN_LEFT)
						->join(array("c"=>"Crm_BrokerMaster"),"a.BrokerId=c.BrokerId",array("BrokerName","BrokerDate"),$select::JOIN_LEFT)
						->join(array("d"=>"Crm_Leads"),"a.LeadId=d.LeadId",array("LeadName"),$select::JOIN_LEFT)
                        ->where(array('a.FollowupId' => $followupId));

			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->responseBroker = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			
			$select = $sql->select();
			$select->from('Crm_BrokerFollowup')
				   ->columns(array('BrokerId'))
                   ->where(array('FollowupId' => $followupId));
    		$statement = $sql->getSqlStringForSqlObject($select);
			$lid  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$ld=$lid['BrokerId'];			
			
			$select = $sql->select();
			$select->from(array('a' => 'Crm_BrokerMaster'))
				->columns(array('*'))
				->where(array('a.BrokerId'=>$ld))	;
			$statement = $sql->getSqlStringForSqlObject($select);
			$this->_view->Brokerdet= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			
			$selectMultiProject = $sql->select();
			$selectMultiProject->from(array("a"=>"Crm_BrokerProjects"));
			$selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
								->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
			$selectMultiProject->where(array("a.BrokerId"=>$ld));
			$statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject); 
			$this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			}
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function followupRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		// $this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql     = new Sql($dbAdapter);
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			$response = $this->getResponse();
			if ($request->isPost()) {
			//Write your Ajax post code here
				$postParam = $request->getPost();
				
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
			}
			else{
				$select = $sql->select();
				$select->from(array("a"=>'Crm_BrokerFollowup'))
					->columns(array('RefDate'=>new Expression("CONVERT(varchar(10),RefDate,105)"),'FollowupId','BrokerId','ProjectsId','LeadId','Remarks'),
					array("BrokerName"), array("LeadName"),array("ProjectName"))
					->join(array("b"=>"Proj_ProjectMaster"),"a.ProjectsId=b.ProjectId",array("ProjectName"),$select::JOIN_LEFT)
					->join(array("c"=>"Crm_BrokerMaster"),"a.BrokerId=c.BrokerId",array("BrokerName"),$select::JOIN_LEFT)
					->join(array("d"=>"Crm_Leads"),"a.LeadId=d.LeadId",array("LeadName"),$select::JOIN_LEFT)
				   ->order('a.FollowupId desc');
				 $statement = $sql->getSqlStringForSqlObject($select); 
				$this->_view->followupRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			}
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}
	}
	
	public function detailsAction(){
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
		$select = $sql->select();
			
			$response = $this->getResponse();
			$request = $this->getRequest();
			// $request = $this->getRequest();
			if($this->getRequest()->isXmlHttpRequest())	{
				
				//$resp = array();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				$detailId = $postParams['cid'];
				
				//Write your Ajax post code here
				$select = $sql->select();
				 $select->from(array("a"=>'Crm_BrokerMaster'))
						->where(array("a.BrokerId"=>$detailId));
			    $statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
				$selectMultiProject = $sql->select();
				$selectMultiProject->from(array("a"=>"Crm_BrokerProjects"));
				$selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
									->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
				$selectMultiProject->where(array("a.BrokerId"=>$detailId));
				$statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject); 
				$this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				
				$this->_view->setTerminal(true);
				return $this->_view;
			}
		} else {
				if ($request->isPost()) {
					//Write your Normal form post code here
				}
				$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
				
				return $this->_view;
		}
	}
public function historyAction(){
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
          $brokerId = $this->params()->fromRoute('BrokerId');
	     
       
					
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			$select = $sql->select();
			if ($request->isPost()) {
				//Write your Ajax post code here
				
		
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}
			else{
				 $select = $sql->select();
				$select->from(array("a"=>'Crm_BrokerFollowup'))
					   ->columns(array('*'))
					   ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectsId=b.ProjectId", array('ProjectName'), $select::JOIN_INNER)
					   ->where(array("a.BrokerId"=>$brokerId))
						->order("a.RefDate Desc");
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->responseFollowdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				//broker select
						$select = $sql->select();
						$select->from(array('a' => 'Crm_BrokerMaster'))
							->columns(array('BrokerId', 'BrokerName'));
						$statement = $sql->getSqlStringForSqlObject($select);
						$this->_view->responseBro= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$select = $sql->select();
				$select ->from(array("a"=>'Crm_BrokerMaster'))
						->columns(array(new Expression("a.BrokerId,CONVERT(varchar(10),a.BrokerDate,105) as BrokerDate,a.BrokerName,a.EmailId,a.Mobile,a.Address")))
					   ->join(array("b"=>"Crm_BrokerFollowup"), "a.BrokerId=b.BrokerId", array('RefDate','ProjectsId','Remarks','NextCallDate','FollowupBrokerId'), $select::JOIN_INNER)
					   ->where(array('a.BrokerId'=>$brokerId))
					   ->order("b.FollowupId Desc");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$this->_view->fullDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
			}
			
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}	
	

	public function brokerFollowupEntryAction(){
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
        $sql    = new Sql($dbAdapter);
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
				$postParams = $request->getPost();
				
				$brokerId = $postParams['brokerId'];
				$this->redirect()->toRoute('crm/followup-page', array('controller' => 'broker', 'action' => 'followup', 'leadId' => $brokerId));
			}
			else {
				$select = $sql->select();
				$select->from('Crm_BrokerMaster')
				 ->columns(array('BrokerId','BrokerName'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->resultBroker = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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

	public function entryAction(){
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
        $sql    = new Sql($dbAdapter);
		$userId = $this->auth->getIdentity()->UserId;
		$brokerId = $this->params()->fromRoute('BrokerId');
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
				$postParams = $request->getPost();
						//begin trans try block example starts
						$connection = $dbAdapter->getDriver()->getConnection();
						$connection->beginTransaction();
						try {
							$select = $sql->select();
							$select->from('Crm_BrokerMaster')
								   ->columns(array('BrokerId'))
								   ->where(array('BrokerId'=>$brokerId));
							$statement = $sql->getSqlStringForSqlObject($select);
							$brokerIdcount  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							$brokercnt=count($brokerIdcount);
			    
							if($brokercnt==0){
								$insert      = $sql->insert('Crm_BrokerMaster');
								$newData     = array(
								'BrokerName' =>  $this->bsf->isNullCheck($postParams['brokername'], 'string'),
								//'Type' => $this->bsf->isNullCheck($postParams['type'], 'string'),
								'BrokerDate' => $this->bsf->isNullCheck( date('m-d-Y',strtotime($postParams['brokerDate'])), 'string'),
								'Address' =>  $this->bsf->isNullCheck($postParams['address'], 'string'),
								//'State'=> $this->bsf->isNullCheck($postParams['state'], 'string'),
								//'WorkNature'  =>  $this->bsf->isNullCheck($postParams['worknature'], 'string'),					
								'EmailId'  =>  $this->bsf->isNullCheck($postParams['email'], 'string'),					
								'Mobile'  =>  $this->bsf->isNullCheck($postParams['mobile'], 'number')					
								);
								$insert->values($newData);
								$statement = $sql->getSqlStringForSqlObject($insert); 
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								$brokerIdlast = $dbAdapter->getDriver()->getLastGeneratedValue();
								foreach ($postParams['projectsId'] as $value){
									$select = $sql->insert('Crm_BrokerProjects');
									$newData = array(
										'BrokerId' => $brokerIdlast,
										'ProjectId'=> $value,
									);
									$select->values($newData);
									$statement = $sql->getSqlStringForSqlObject($select); 
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}

								$connection->commit();
                                CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-Broker-Entry-Add','N','CRM-Broker-Entry',$brokerIdlast,0, 0, 'CRM', '',$userId, 0 ,0);

                                $this->redirect()->toRoute('crm/default', array('controller' => 'broker', 'action' => 'register'));
							}
							else{
								$delete = $sql->delete();
								$delete->from('Crm_BrokerProjects')
											->where(array('BrokerId' => $brokerId,));
								   $DelStatement = $sql->getSqlStringForSqlObject($delete);
									$deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

								$update = $sql->update();
								$update->table('Crm_BrokerMaster');
								$update->set(array(
								'BrokerName' =>  $this->bsf->isNullCheck($postParams['brokername'], 'string'),
								//'Type' => $this->bsf->isNullCheck($postParams['type'], 'string'),
								'BrokerDate' => $this->bsf->isNullCheck( date('m-d-Y',strtotime($postParams['brokerDate'])), 'string'),
								'Address' =>  $this->bsf->isNullCheck($postParams['address'], 'string'),
								//'State'=> $this->bsf->isNullCheck($postParams['state'], 'string'),
								//'WorkNature'  =>  $this->bsf->isNullCheck($postParams['worknature'], 'string'),					
								'EmailId'  =>  $this->bsf->isNullCheck($postParams['email'], 'string'),					
								'Mobile'  =>  $this->bsf->isNullCheck($postParams['mobile'], 'number')			
								));
								$update->where(array('BrokerId'=>$brokerId));
								$statement = $sql->getSqlStringForSqlObject($update);
								$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								foreach ($postParams['projectsId'] as $value){
									$select = $sql->insert('Crm_BrokerProjects');
									$newData = array(
										'BrokerId' => $brokerId,
										'ProjectId'=> $value,
									);
									$select->values($newData);
									$statement = $sql->getSqlStringForSqlObject($select); 
									$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
								}

								$connection->commit();
                                CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-Broker-Entry-Modify','E','CRM-Broker-Entry',$brokerId,0, 0, 'CRM', '',$userId, 0 ,0);
                                $FeedId = $this->params()->fromQuery('FeedId');
                                $AskId = $this->params()->fromQuery('AskId');
                                if((isset($FeedId) && $FeedId!="")) {
                                    $this->redirect()->toRoute('crm/default', array('controller' => 'broker', 'action' => 'register'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                                } else {
                                    $this->redirect()->toRoute('crm/default', array('controller' => 'broker', 'action' => 'register'));
                                }
//                                $this->redirect()->toRoute('crm/default', array('controller' => 'broker', 'action' => 'register'));
							}
							
						} catch(PDOException $e){
							$connection->rollback();
							print "Error!: " . $e->getMessage() . "</br>";
						}
				
			}
			else{
				
				$select = $sql->select();
				$select->from('Crm_BrokerMaster')
					   ->columns(array(new Expression("BrokerId,CONVERT(varchar(10),BrokerDate,105) as BrokerDate,BrokerName,Mobile,EmailId,Address")))
					   ->where(array('BrokerId'=>$brokerId));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->broker= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
				//selecting values for LeadProjects
				$select = $sql->select();
				$select->from('Proj_ProjectMaster')
					   ->columns(array('ProjectId','ProjectName'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->resultsLeadProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$select = $sql->select();
				$select->from('Crm_BrokerProjects')
					   ->columns(array('ProjectId'))
					   ->where(array("BrokerId"=>$brokerId));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->resultsMulti  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$this->leadProjects = array();
				foreach($this->_view->resultsMulti as $this->resultsMulti) {
					$this->leadProjects[] = $this->resultsMulti['ProjectId'];
				}
				$this->_view->leadProjects = $this->leadProjects;
		
				
			}
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function fullDetailsAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		// $this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
		$brokerId = $this->params()->fromRoute('BrokerId');
		if($this->getRequest()->isXmlHttpRequest()){
			$request = $this->getRequest();
			$response = $this->getResponse();
			if ($request->isPost()) {
			//Write your Ajax post code here
				$postParam = $request->getPost();
			}
		    } else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here			
			}
			else{
			
				$select = $sql->select();
				$select ->from(array("a"=>'Crm_BrokerMaster'))
				        ->columns(array(new Expression("a.BrokerId,CONVERT(varchar(10),a.BrokerDate,105) as BrokerDate,a.Type,a.BrokerName,a.EmailId,a.Mobile,a.Address")))
				     ->join(array("b"=>"Crm_BrokerFollowup"), "a.BrokerId=b.BrokerId", array("NextCallDate"), $select::JOIN_LEFT)
					 ->where(array('a.BrokerId'=>$brokerId))
					 ->order("b.FollowupId Desc");
				$statement = $sql->getSqlStringForSqlObject($select); 
				$this->_view->fullDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				
			
				
		        //broker select
				$select = $sql->select();
				$select->from(array('a' => 'Crm_BrokerMaster'))
					->columns(array('BrokerId', 'BrokerName'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->responseBro= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$selectMultiProject = $sql->select();
				$selectMultiProject->from(array("a"=>"Crm_BrokerProjects"));
				$selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
									->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
				$selectMultiProject->where(array("a.BrokerId"=>$brokerId));
				$statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject); 
				$this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
				
		}
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
	}}


	public function editAction(){
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
		}  else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
				$brokerId = $postParams['brokerId'];
				
				$this->redirect()->toRoute('crm/broker-entry', array('controller' => 'broker', 'action' => 'entry','BrokerId' =>$brokerId));
			}
			else {
				$select = $sql->select();
				$select->from('Crm_BrokerMaster')
				 ->columns(array('BrokerId','BrokerName'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->resultsBroker = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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

	public function reportProjectWiseAction(){
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
			else{
				
				$select = $sql->select();
				$select->from(array("a"=>'Crm_BrokerMaster'))
				       ->columns(array('BrokerName','BrokerId'))
					   ->join(array("b"=>"Crm_Leads"),"a.BrokerId=b.BrokerId",array("LeadName"),$select::JOIN_INNER)
					   ->join(array("c"=>"Crm_LeadProjects"), "b.LeadId=c.LeadId", array(), $select::JOIN_LEFT)
					   ->join(array("d"=>"Proj_ProjectMaster"), "c.ProjectId=d.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
					   ->order("a.BrokerName asc");
					    if($projectId!=0) {
							$select->where("c.ProjectId=$projectId");
						}
			    $statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->reg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
				//graph//
				$subQry1 = $sql->select();
				$subQry1->from(array('a' =>'Crm_Leads'))
				->join(array("c"=>"Crm_LeadProjects"),"a.LeadId=c.LeadId",array(),$select::JOIN_INNER);
				if(isset($projectId)!=0) {
						   
							$subQry1->where("c.ProjectId=$projectId");
					   }
				  $subQry1  ->columns(array('LeadId'))
					  ->join(array("b"=>"Crm_BrokerMaster"),"a.BrokerId=b.BrokerId",array('BrokerName'),$select::JOIN_INNER);
					 
				      
				$stmt = $sql->getSqlStringForSqlObject($subQry1);
				
				 $select = $sql->select();
                    $select->from(array("g"=>$subQry1))
                            ->columns(array('BrokerName',
                              'TotalLeads' => new Expression('count(LeadId)'),
							   'Finalise'=>new Expression('1-1')))
							->group(array('g.BrokerName'));
							
							
							
					$subQry2 = $sql->select();
					$subQry2->from(array('a' =>'Crm_UnitBooking'))
					         ->columns(array('LeadId'))
					       ->join(array("b"=>"Crm_Leads"),"a.LeadId=b.LeadId",array(),$select::JOIN_INNER)
						 ->join(array("c"=>"Crm_BrokerMaster"),"c.BrokerId=b.BrokerId",array('BrokerName'),$select::JOIN_INNER)
						 ->join(array("d"=>"Crm_LeadProjects"),"b.LeadId=d.LeadId",array(),$select::JOIN_INNER);
				       if(isset($projectId)!=0) {
						   
							$subQry2->where("d.ProjectId=$projectId");
					   }    
					   
					$select2 = $sql->select();
								$select2->from(array("g"=>$subQry2))
										->columns(array('BrokerName',
										  'TotalLeads' => new Expression('1-1'),
										  'Finalise' => new Expression('count(LeadId)')))
										->group(array('g.BrokerName'));
					$select2->combine($select,'Union ALL');
				
                  $select3 = $sql->select();
                    $select3->from(array("h"=>$select2))
                            ->columns(array('BrokerName',
                              'TotalLeads' => new Expression('Sum(h.TotalLeads)'),
                              'Finalise' => new Expression('Sum(h.Finalise)')))
                            ->group(array('h.BrokerName'));
		
			    $stmt = $sql->getSqlStringForSqlObject($select3); 
				$arrWvl= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					  
			   $arrdate = array();
				$arrFinalCount = array();
				$arrDropCount = array();
				foreach($arrWvl as $wvl){
					$arrdate[] = $wvl['BrokerName'];
					$arrFinalCount[] = (int)$wvl['TotalLeads'];
					$arrDropCount[] = (int)$wvl['Finalise'];
				}
				$this->_view->jsonarrWvlDate = json_encode($arrdate);
				$this->_view->jsonarrWvlFinal = json_encode($arrFinalCount);
				$this->_view->jsonarrWvlDrop = json_encode($arrDropCount);
		  
		  
				//selecting values for LeadProjects
				$select = $sql->select();
				$select->from('Proj_ProjectMaster')
					   ->columns(array('ProjectId','ProjectName'));
				$statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$this->_view->projectId = $projectId;
			}
			
			
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

}