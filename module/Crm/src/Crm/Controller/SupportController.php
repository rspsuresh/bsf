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

class SupportController extends AbstractActionController
{
	public function __construct()	{
		$this->bsf = new \BuildsuperfastClass();
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function ticketEntryAction(){
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
		$ticketId = $this->params()->fromRoute('TicketId');

		$sql = new Sql($dbAdapter);
		$userName = $this->auth->getIdentity()->EmployeeName;
		$userId = $this->auth->getIdentity()->UserId;
		$leadId = $this->params()->fromRoute('leadId');
		
		if($ticketId !=0){
			$select = $sql->select();
           $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
           ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
           //->join(array("d" =>"Crm_Leads"),"a.LeadId=d.LeadId",array("Email"), $select::JOIN_LEFT)
		   ->where("a.TicketId=$ticketId");
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->ticketedit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			
		}
		//Executives//
		$select = $sql->select();
		$select->from('WF_Users')
		       ->columns(array('UserId','EmployeeName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//Lead Name//
		$select = $sql->select(); 
		$select->from('Crm_Leads')
		       ->columns(array('data' => 'LeadId', 'value'=>'LeadName','mail'=>'Email','phone'=>'Mobile'))
        ->where("LeadType =1");
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();	
		
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {

                if ($request->isPost()) {
                    $postData = $request->getPost();
					//Print_r($postData);die;
					$requester = $this->bsf->isNullCheck($postData['requester'],'string');
					$leadId = $this->bsf->isNullCheck($postData['LeadId'],'number');
					$mailId = $this->bsf->isNullCheck($postData['mailId'],'string');
					$phone = $this->bsf->isNullCheck($postData['phonenumber'],'number');
					$subject = $this->bsf->isNullCheck($postData['subject'],'string');
                    $type = $this->bsf->isNullCheck($postData['type'],'string');
                    $status = $this->bsf->isNullCheck($postData['status'],'string');
					$description = $this->bsf->isNullCheck($postData['description'],'string');
                    //$tags = $this->bsf->isNullCheck($postData['tags'],'string');
                    $priority = $this->bsf->isNullCheck($postData['priority'],'string');
                    
                    $tGroup = $this->bsf->isNullCheck($postData['tgroup'],'string');
                    $updatecli = $this->bsf->isNullCheck($postData['updatecli'],'number');
					$executiveId = $this->bsf->isNullCheck($postData['executiveId'],'number');
					$date=date('m-d-Y H:i:s');
					
					if($ticketId ==0){
						$insert  = $sql->insert('Crm_TicketRegister');
						$newData = array(
							'CreatedDate' => date('m-d-y'),
							'Requester' => $requester,
							'LeadId' => $leadId,
							'Subject' => $subject,
							'Type' => $type,
							'Status' => $status,
							'Priority' => $priority,
							'Email' => $mailId,
							'Mobile' => $phone,
							'Description' => $description,
							'TGroup' => $tGroup,
							'ExecutiveId' => $executiveId,
						);
						$insert->values($newData);
						$statement = $sql->getSqlStringForSqlObject($insert);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						$ticketId = $dbAdapter->getDriver()->getLastGeneratedValue();
						if($type=='Lead' && $leadId!=''){
							$insert  = $sql->insert('Crm_Leads');
							$newData = array(
								'LeadName' => $requester,
								'Mobile' => $this->bsf->isNullCheck($phone,'string'),
								'Email' => $this->bsf->isNullCheck($mailId,'string'),
								'UserId' => $this->bsf->isNullCheck($userId,'number'),
								'NextCallDate'=>date('m-d-Y H:i:s'),
							);
							$insert->values($newData);
							$statement = $sql->getSqlStringForSqlObject($insert);
							$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
							
						}
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-Support-Ticket-Entry-Add','N','CRM-Support-Ticket-Entry',$ticketId,0, 0, 'CRM','',$userId, 0 ,0);

                        $mailData = array(
							array(
								'name' => 'TICKETID',
								'content' => $ticketId
							),
							array(
								'name' => 'DATE',
								'content' => $date
							),
							array(
								'name' => 'STATUS',
								'content' => $status
							)
						);
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
						$viewRenderer->MandrilSendMail()->sendMailTo($postData['mailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);
						
						// $ticketId = $dbAdapter->getDriver()->getLastGeneratedValue();
						// foreach ($postData['tags'] as $value){
						// $select = $sql->insert('Crm_TicketTags');
						// $newData = array(
							// 'TicketId' => $ticketId,
							// 'TagName'=> $value,
						// );
						// $select->values($newData);
						// $statement = $sql->getSqlStringForSqlObject($select); 
						// $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						// }
					} else {
						$update = $sql->update();
                        $update->table('Crm_TicketRegister');
                        $update->set(array(
							'ModifiedDate' => date('m-d-y'),
							'Requester' => $requester,
							'LeadId' => $leadId,
							'Subject' => $subject,
							'Type' => $type,
							'Description' => $description,
							'CliUpdate' => $updatecli,
							'Status' => $status,
							'Priority' => $priority,
							'TGroup' => $tGroup,
							'ExecutiveId' => $executiveId,
						));
                        $update->where(array('TicketId'=>$ticketId));
   					    $statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-Support-Ticket-Entry-Modify','N','CRM-Support-Ticket-Entry',$ticketId,0, 0, 'CRM','',$userId, 0 ,0);

                    }
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'support', 'action' => 'ticket-register'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'support', 'action' => 'ticket-register'));
                    }

					$this->redirect()->toRoute('crm/default', array('controller' => 'support', 'action' => 'ticket-register'));
					if($postData['updatecli']==1){
						echo "test"; 
							$mailData = array(
						array(
							'name' => 'TICKETID',
							'content' => $ticketId
						),
						array(
							'name' => 'DATE',
							'content' => $date
						),
						array(
							'name' => 'STATUS',
							'content' => $status
						));
						$config = $this->getServiceLocator()->get('config');
						$from = $config['email']['crm'];

						$viewRenderer->MandrilSendMail()->sendMailTo('arnikaa15@gmail.com',$from,'New Client Ticket','crm_ticket_new',$mailData);
					}
					//$this->redirect()->toRoute('crm/ticket-register', array('controller' => 'Support', 'action' => 'ticket-register'));
				}
				
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
		}$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function newAction(){
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
		$userName = $this->auth->getIdentity()->EmployeeName;
		$userId = $this->auth->getIdentity()->UserId;
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				//Print_r($postParams);die;
				$request = $postParams['request'];
				$phone = $postParams['phoneId'];
				$email = $postParams['emailId'];
				
			    $insert  = $sql->insert('Crm_Leads');
                    $newData = array(
                        'LeadName' => $this->bsf->isNullCheck($request,'string'),
						'UserId' => $this->bsf->isNullCheck($userId,'number'),
						'Mobile' => $this->bsf->isNullCheck($phone,'number'),
						'EMail' => $this->bsf->isNullCheck($email,'string'),
						'NextCallDate'=>date('m-d-Y H:i:s'),
                    );
                    $insert->values($newData);
                 $statement = $sql->getSqlStringForSqlObject($insert); 
				   $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
				   
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

	public function ticketRegisterAction(){
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
		$status = $this->params()->fromRoute('status');
        $sql = new Sql($dbAdapter);
		
		
		$where="";
		if(isset($status)){
		
			$where =" where status =".$status;
			$select = $sql->select();
			$select->from('Crm_TicketRegister')
				->where(array("Status"=>$status));
			$statement = $sql->getSqlStringForSqlObject($select); 
			$this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->status = $status;
			
		}
		
		
		
		//selecting values from Executive Table
		$select = $sql->select();
		$select->from('WF_Users')
		       ->columns(array('UserId'=>'UserId','UserName' => 'EmployeeName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/* added lines */
		
        $select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
            ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo","LeadId"), $select::JOIN_LEFT)
            ->where("a.DeleteFlag='0'")
			->order('a.TicketId desc');
			if(isset($status)){
				$select->where(array('a.Status' => $status));
			}
        $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->ticket = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$postParams = $request->getPost();
				$ticketId = $postParams['ticketId'];
				$executiveId = $postParams['executiveId'];
                $update = $sql->update();
						$select->table('Crm_TicketRegister');
						$select->set(array(	
                        'ExecutiveId' => $executiveId
                    ));
                $update->where(array('TicketId'=>$ticketId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
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

	public function clientbasedserviceAction(){
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
		$ticketId = $this->params()->fromRoute('TicketId'); 
		$sql = new Sql($dbAdapter);
		
		$select = $sql->select();
        $select->from(array("a" => "PM_ServiceMaster"))
            ->columns(array('ServiceId','ServiceName'));
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->service = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->columns(array('TicketId','Requester'));
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->ticketreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				$leadId=$postParams['LeadId'];
				$select->from(array('a' => 'KF_UnitMaster'))
			->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
			->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
			->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
			->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
			->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
		 ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
			->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
			->join(array('h' => 'crm_Unitbooking'), 'h.UnitId=a.UnitId', array('LeadId','BuyerName' => 'BookingName'), $select::JOIN_LEFT)
			//->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
			->where(array("h.LeadId"=>$leadId));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent(json_encode($unitInfo));
				$response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                $response->setStatusCode(200);
				return $response;
			}
		} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			//try {

                if ($request->isPost()) {
                    //Write your Normal form post code here
                    $postData = $request->getPost();
				  
				$ticketId = $this->bsf->isNullCheck($postData['ticketId'],'number');
					foreach($postData as $key => $data) {
                        if(preg_match('/^service_[\d]+$/', $key)) {

                            preg_match_all('/^service_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $serviceId = $this->bsf->isNullCheck($postData['service_' . $id], 'number');
                            if($serviceId <= 0) {
                                continue;
                            }

                            $serviceDoneTrans = array(
                                'TicketId' => $ticketId,
                                'ServiceId' => $serviceId,
								'CreatedDate'=>date('m-d-Y H:i:s')
                                
                            );

                            $insert = $sql->insert('Crm_ClientService');
                            $insert->values($serviceDoneTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
						//$this->redirect()->toRoute('crm/servicedone-register', array('controller' => 'property', 'action' => 'servicedone-register'));
                    }
					
				}

				$connection->commit();
			/*} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}*/

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            //$this->_view->qualHtml = $qualHtml;
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function portalTicketEntryAction(){
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
		$ticketId = $this->params()->fromRoute('TicketId');
		
		$sql = new Sql($dbAdapter);
		$userName = $this->auth->getIdentity()->EmployeeName;
		$userId = $this->auth->getIdentity()->UserId;
		$leadId = $this->params()->fromRoute('leadId');
		
		if($ticketId !=0){
			$select = $sql->select();
           $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
           ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
           //->join(array("d" =>"Crm_Leads"),"a.LeadId=d.LeadId",array("Email"), $select::JOIN_LEFT)
		   ->where("a.TicketId=$ticketId");
         $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->ticketedit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			
		}
		
		//Executives//
		$select = $sql->select();
		$select->from('WF_Users')
		       ->columns(array('UserId','EmployeeName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//Lead Name//
		$select = $sql->select(); 
		$select->from('Crm_Leads')
		       ->columns(array('data' => 'LeadId', 'value'=>'LeadName','mail'=>'Email','phone'=>'Mobile'));
		$statement = $sql->getSqlStringForSqlObject($select); 
		$this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();	
		
		
		 if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}} else {
			$request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {

                if ($request->isPost()) {
                    $postData = $request->getPost();
					//Print_r($postData);die;
					$requester = $this->bsf->isNullCheck($postData['requester'],'string');
					$leadId = $this->bsf->isNullCheck($postData['LeadId'],'number');
					$mailId = $this->bsf->isNullCheck($postData['mailId'],'string');
					$phone = $this->bsf->isNullCheck($postData['phonenumber'],'number');
					$subject = $this->bsf->isNullCheck($postData['subject'],'string');
                    $type = $this->bsf->isNullCheck($postData['type'],'string');
                    $status = $this->bsf->isNullCheck($postData['status'],'string');
					$description = $this->bsf->isNullCheck($postData['description'],'string');
                    //$tags = $this->bsf->isNullCheck($postData['tags'],'string');
                    $priority = $this->bsf->isNullCheck($postData['priority'],'string');
                    
                    $tGroup = $this->bsf->isNullCheck($postData['tgroup'],'string');
                    $updatecli = $this->bsf->isNullCheck($postData['updatecli'],'number');
					$executiveId = $this->bsf->isNullCheck($postData['executiveId'],'number');
					$date=date('m-d-Y H:i:s');
					
					if($ticketId ==0){
					$insert  = $sql->insert('Crm_TicketRegister');
                    $newData = array(
                        'CreatedDate' => date('m-d-y'),
                        'Requester' => $requester,
                        'LeadId' => $leadId,
                        'Subject' => $subject,
                        'Type' => $type,
                        'Status' => $status,
                        'Priority' => $priority,
                        'Email' => $mailId,
                        'Mobile' => $phone,
                        'Description' => $description,
                        'TGroup' => $tGroup,
                        'ExecutiveId' => $executiveId,
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					$ticketId = $dbAdapter->getDriver()->getLastGeneratedValue();
					if($type=='Lead' && $leadId!=''){
						$insert  = $sql->insert('Crm_Leads');
                        $newData = array(
						'LeadName' => $requester,
                        'Mobile' => $this->bsf->isNullCheck($phone,'string'),
                        'Email' => $this->bsf->isNullCheck($mailId,'string'),
                        'UserId' => $this->bsf->isNullCheck($userId,'number'),
						'NextCallDate'=>date('m-d-Y H:i:s'),
						);
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					
					
					/*$mailData = array(
					array(
						'name' => 'TICKETID',
						'content' => $ticketId
					),
					array(
						'name' => 'DATE',
						'content' => $date
					),
					array(
						'name' => 'STATUS',
						'content' => $status
					)
				);
					$sm = $this->getServiceLocator();
					 $config = $sm->get('application')->getConfig();
                     $viewRenderer->MandrilSendMail()->sendMailTo($postData['mailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);*/
					
					// $ticketId = $dbAdapter->getDriver()->getLastGeneratedValue();
					// foreach ($postData['tags'] as $value){
					// $select = $sql->insert('Crm_TicketTags');
					// $newData = array(
						// 'TicketId' => $ticketId,
						// 'TagName'=> $value,
					// );
					// $select->values($newData);
					// $statement = $sql->getSqlStringForSqlObject($select); 
					// $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					// }
					}
					else{
						$update = $sql->update();
                        $update->table('Crm_TicketRegister');
                        $update->set(array(
                        'ModifiedDate' => date('m-d-y'),
                        'Requester' => $requester,
                        'LeadId' => $leadId,
                        'Subject' => $subject,
                        'Type' => $type,
                        'Description' => $description,
                        'CliUpdate' => $updatecli,
                        'Status' => $status,
                        'Priority' => $priority,
                        'TGroup' => $tGroup,
                        'ExecutiveId' => $executiveId,
                    ));
                        $update->where(array('TicketId'=>$ticketId));
   					    $statement = $sql->getSqlStringForSqlObject($update);
						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
						if($postData['updatecli']==1){
						$mailData = array(
					array(
						'name' => 'TICKETID',
						'content' => $ticketId
					),
					array(
						'name' => 'DATE',
						'content' => $date
					),
					array(
						'name' => 'STATUS',
						'content' => $status
					)
				);
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                 $viewRenderer->MandrilSendMail()->sendMailTo($postData['MailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);
					}
					}
				$connection->commit();
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute('crm/ticket-register', array('controller' => 'pm', 'action' => 'ticket-register'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('crm/ticket-register', array('controller' => 'pm', 'action' => 'ticket-register'));
                    }

//                $this->redirect()->toRoute('crm/ticket-register', array('controller' => 'pm', 'action' => 'ticket-register'));
				}
				
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
		}$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	public function portalTicketViewAction(){
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

	public function portalTicketRegisterAction(){
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
		$status = $this->params()->fromRoute('status');
        $sql = new Sql($dbAdapter);
		
		
		$where="";
		if(isset($status)){
		
			$where =" where status =".$status;
			$select = $sql->select();
			$select->from('Crm_TicketRegister')
				->where(array("Status"=>$status));
			$statement = $sql->getSqlStringForSqlObject($select); 
			$this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->status = $status;
			
		}
		
		
		
		//selecting values from Executive Table
		$select = $sql->select();
		$select->from('WF_Users')
		       ->columns(array('UserId'=>'UserId','UserName' => 'EmployeeName'));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/* added lines */
		
        $select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
           ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
            ->where("a.DeleteFlag='0'")
			->order('a.TicketId desc');
			if(isset($status)){
				$select->where(array('a.Status' => $status));
			}
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ticket = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$postParams = $request->getPost();
				$ticketId = $postParams['ticketId'];
				$executiveId = $postParams['executiveId'];
                $update = $sql->update();
						$select->table('Crm_TicketRegister');
						$select->set(array(	
                        'ExecutiveId' => $executiveId
                    ));
                $update->where(array('TicketId'=>$ticketId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
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

    public function forumAction() {

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $connection->commit();
                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {


                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}