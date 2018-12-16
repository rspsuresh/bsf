<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Portal\Controller;

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

class VendorController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function indexAction(){ 
		/* if(!$this->auth->hasIdentity()) {
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
		} */
	}

	public function rfqRegisterAction(){
		//$this->layout('layout/portal');
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		$vendorDetail = new Container('vendorDetail');
		
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		if(!$vendorDetail->vendorId)
			$this->redirect()->toRoute("portal/default", array("controller" => "vendor","action" => "vendor-login"));
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$resp = array();
				$vendorId = $vendorDetail->vendorId;
				//Write your Ajax post code here
				$selectRFQ = $sql->select();
				$selectRFQ->from(array("a"=>"VM_RFQRegister"));
				$selectRFQ->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,
								CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,
								CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve")), array(), array("TypeName"))
							->join(array("b"=>"VM_RFQVendorTrans"), "a.RFQRegId=b.RFQId", array(), $selectRFQ::JOIN_INNER)
							->join(array("c"=>"Proj_ResourceType"), "a.RFQType=c.TypeId", array("TypeName"), $selectRFQ::JOIN_LEFT);
				$selectRFQ->where(array("b.VendorId"=>$vendorId))
					->order("a.RFQDate Desc");
				$statement = $sql->getSqlStringForSqlObject($selectRFQ);
				$resp['result'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;			
		} else if($request->isPost()) {
				//Write your Normal form post code here				
		}
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
		return $this->_view;
	}

	public function responseEntryAction(){
		//$this->layout("layout/portal");
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
		$response = $this->getResponse();
		$sql = new Sql($dbAdapter);
		$vendorDetail = new Container('vendorDetail');
		if(!$vendorDetail->vendorId)
			$this->redirect()->toRoute("portal/default", array("controller" => "vendor","action" => "vendor-login"));
		
		$rfqRegId = $this->params()->fromRoute('rfqid');
		$vendorId = $vendorDetail->vendorId;
		//Checking for Validation 
		$iTechVerificationFound=0;
		$iBidafterVerification=0;
		$sValid="";
		$select = $sql->select();
		$select->from(array('a'=>'VM_RFQRegister'))
			   ->columns(array('BidafterVerification','TechVerification'))
			   ->where(array("a.RFQRegId"=>$rfqRegId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsrfqFound = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsrfqFound){
		//$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
		}
		else { 
			$iTechVerificationFound = $resultsrfqFound['TechVerification'];
			$iBidafterVerification = $resultsrfqFound['BidafterVerification'];
		}

		if ($iTechVerificationFound==1 && $iBidafterVerification==1)
		{
			$selectTechEntryFound = $sql->select();
			$selectTechEntryFound->from(array("a"=>"VM_TechInfoRegister"))
					->columns(array("RegId","Valid"))					
					->where(array("a.RFQId"=>$rfqRegId, "a.VendorId"=>$vendorId));
			$statement = $sql->getSqlStringForSqlObject($selectTechEntryFound);
			$resultsTechEntryFound = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			if(!$resultsTechEntryFound){
			//$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
			}
			else { 
				$sValid = $resultsTechEntryFound['Valid'];
			}
		
			if($sValid != "A")
			{
				echo "<script>alert('Your document needs to be validated')</script>";
				$this->redirect()->toRoute('portal/rfqrequested-detail', array('controller' => 'vendor','action' => 'rfqrequested-detail','rfqid' => $rfqRegId));
			}
		}
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$result =  "";
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		} else if ($request->isPost()) {						
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			$files = $request->getFiles();
			try {
				$regId= $postParams['regId'];
				$isPortal=1;
				if($regId==0)
				{
					$pathname = '';
					if($files['additional_files']['name']){
						$dir = 'public/uploads/vendor/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
						if(!is_dir($dir))
							mkdir($dir, 0755, true);
						
						$path = $dir.$files['additional_files']['name'];
						move_uploaded_file($files['additional_files']['tmp_name'], $dir.$files['additional_files']['name']);
						$pathname = explode('public/', $path)[1];
					}
					
					$registerInsert = $sql->insert('VM_RequestFormVendorRegister');
					$registerInsert->values(array("Entrydate"=>date('Y-m-d'), "VendorId"=>$postParams['VendorId'],
							"RFQId"=>$postParams['rfqId'],"BidComments"=>$postParams['bidComments'],
							"AddDocumentName"=>$postParams['addDocumentName'],
							"AddDocumentPath"=>$pathname,"Isportal"=>$isPortal ));						
					$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
					$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$regId = $dbAdapter->getDriver()->getLastGeneratedValue();

					$resJson = json_decode($postParams['hidResTrans'], true);
					foreach($resJson as $res){
						//insert VM_RFVTrans
						$rfqvtransInsert = $sql->insert('VM_RFVTrans');
						$rfqvtransInsert->values(array("RegId"=>$regId, "ResourceId"=>$res, "Quantity"=>$postParams['quantity_'.$res],
						"Rate"=>$postParams['rate_'.$res],"Amount"=>($postParams['quantity_'.$res] * $postParams['rate_'.$res]) ));
								
						$rfvtransStatement = $sql->getSqlStringForSqlObject($rfqvtransInsert);
						$dbAdapter->query($rfvtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}

					$submittaljson = json_decode($postParams['hidSubmittal'], true);
					/*For admin --> public/uploads/vendor/rfq/response/rfqId/vendorId/filename --*/
					foreach($submittaljson as $submittal){
						$pathname = '';
						if($files['files_'.$submittal]['name']){
							$dir = 'public/uploads/vendor/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
							if(!is_dir($dir))
								mkdir($dir, 0755, true);
							
							$ext = pathinfo($files['files_'.$submittal]['name'], PATHINFO_EXTENSION);
							$path = $dir.$postParams['submittalName_'.$submittal].'.'.$ext;
							move_uploaded_file($files['files_'.$submittal]['tmp_name'], $path);
							$pathname = explode('public/', $path)[1];
						}					
						//insert VM_RFVSubmittalTrans
						$rfvSubmittalInsert = $sql->insert('VM_RFVSubmittalTrans');
						$rfvSubmittalInsert->values(array("RegId"=>$regId, "VendorId"=>$postParams['VendorId'],
						"RFQSubmittalTransId"=>$submittal,"SubmittalDocPath"=> $pathname));
								
						$rfvSubmittaltransStatement = $sql->getSqlStringForSqlObject($rfvSubmittalInsert);
						$dbAdapter->query($rfvSubmittaltransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					
					//VM_RFVTerms
					$termsjson = json_decode($postParams['hidTerms'], true);
					foreach($termsjson as $terms){				
						$rfvTermsInsert = $sql->insert('VM_RFVTerms');
						$rfvTermsInsert->values(array("RegisterId"=>$regId,"TermsId"=>$terms,
						"ValueFromNet"=>$postParams['valueFromNet_'.$terms],"Per"=>$postParams['percentage_'.$terms],
						"Value"=>$postParams['value_'.$terms],"Period"=>$postParams['period_'.$terms],
						//"TDate"=>$postParams['Date_'.$terms],"TString"=>$postParams['Str_'.$terms]
						));
						$rfvTermstransStatement = $sql->getSqlStringForSqlObject($rfvTermsInsert);
						$dbAdapter->query($rfvTermstransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					
					//RFQSubmit Status
					$rfqVendorTransUpdate = $sql->update();
					$rfqVendorTransUpdate->table('VM_RFQVendorTrans');
					$rfqVendorTransUpdate->set(array(					
						'PortalStatus' => 2
					 ));
					$rfqVendorTransUpdate->where(array('RFQId'=>$postParams['rfqId'], 'VendorId'=>$postParams['VendorId']));			
					$rfqVendorTransStatement = $sql->getSqlStringForSqlObject($rfqVendorTransUpdate);
					$rfqVendorTransResults = $dbAdapter->query($rfqVendorTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						
				}else {				
					//delete VM_RFVTrans
					$selectRFVTrans   = $sql->delete();
					$selectRFVTrans->from("VM_RFVTrans")
							->where(array('RegId'=>$regId));
					$DelRFVTransStatement = $sql->getSqlStringForSqlObject($selectRFVTrans);
					$register1 = $dbAdapter->query($DelRFVTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					//delete VM_RFVTerms
					$selVM_RFVTerms   = $sql->delete();
					$selVM_RFVTerms->from("VM_RFVTerms")
							->where(array('RegisterId'=>$regId));
					$DelVM_RFVTermsStatement = $sql->getSqlStringForSqlObject($selVM_RFVTerms);
					$register2 = $dbAdapter->query($DelVM_RFVTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$pathname = $postParams['addDocumentPath'];
					
					if($files['additional_files']['name']){
						$dir = 'public/uploads/vendor/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
						if(!is_dir($dir))
							mkdir($dir, 0755, true);
						
						unlink('public/'.$pathname);
						$path = $dir.$files['additional_files']['name'];
						move_uploaded_file($files['additional_files']['tmp_name'], $dir.$files['additional_files']['name']);
						$pathname = explode('public/', $path)[1];
					}
								
					//update VM_RequestFormVendorRegister
					$registerUpdate = $sql->update();
					$registerUpdate->table('VM_RequestFormVendorRegister');
					$registerUpdate->set(array(
						'Entrydate' => date('Y-m-d'),
						'VendorId' => $postParams['VendorId'],
						//'RFQId' => $postParams['rfqId'],					
						'BidComments' => $postParams['bidComments'],
						'AddDocumentName'=>$postParams['addDocumentName'],
						'AddDocumentPath'=>$pathname,
						'Isportal'=>$isPortal
					 ));
					$registerUpdate->where(array('RegId'=>$regId));			
					$registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
					$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					

					$resJson = json_decode($postParams['hidResTrans'], true);
					foreach($resJson as $res){
						//insert VM_RFVTrans
						$rfqvtransInsert = $sql->insert('VM_RFVTrans');
						$rfqvtransInsert->values(array("RegId"=>$regId, "ResourceId"=>$res, "Quantity"=>$postParams['quantity_'.$res],
						"Rate"=>$postParams['rate_'.$res],"Amount"=>($postParams['quantity_'.$res] * $postParams['rate_'.$res]) ));
								
						$rfvtransStatement = $sql->getSqlStringForSqlObject($rfqvtransInsert);
						$dbAdapter->query($rfvtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}

					$submittaljson = json_decode($postParams['hidSubmittal'], true);
					/*For admin --> public/uploads/vendor/rfq/response/rfqId/vendorId/filename --*/
					foreach($submittaljson as $submittal){
						$pathname = $postParams['submittalPath_'.$submittal];
						if($files['files_'.$submittal]['name']){
							$dir = 'public/uploads/vendor/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
							if(!is_dir($dir))
								mkdir($dir, 0755, true);
							
							$ext = pathinfo($files['files_'.$submittal]['name'], PATHINFO_EXTENSION);
							$path = $dir.$postParams['submittalName_'.$submittal].'.'.$ext;
							move_uploaded_file($files['files_'.$submittal]['tmp_name'], $path);
							$pathname = explode('public/', $path)[1];
						}					
						//update VM_RFVSubmittalTrans
						$rfvSubmittalUpdate = $sql->update();
						$rfvSubmittalUpdate->table('VM_RFVSubmittalTrans');
						$rfvSubmittalUpdate->set(array(
							'SubmittalDocPath' => $pathname						  
						 ));
						$rfvSubmittalUpdate->where(array('TransId'=>$submittal,'RegId'=>$regId));	
							
						$rfvSubmittaltransStatement = $sql->getSqlStringForSqlObject($rfvSubmittalUpdate);
						$dbAdapter->query($rfvSubmittaltransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					
					//VM_RFVTerms
					$termsjson = json_decode($postParams['hidTerms'], true);
					foreach($termsjson as $terms){				
						$rfvTermsInsert = $sql->insert('VM_RFVTerms');
						$rfvTermsInsert->values(array("RegisterId"=>$regId,"TermsId"=>$terms,
						"ValueFromNet"=>$postParams['valueFromNet_'.$terms],"Per"=>$postParams['percentage_'.$terms],
						"Value"=>$postParams['value_'.$terms],"Period"=>$postParams['period_'.$terms],
						//"TDate"=>$postParams['Date_'.$terms],"TString"=>$postParams['Str_'.$terms]
						));
						$rfvTermstransStatement = $sql->getSqlStringForSqlObject($rfvTermsInsert);
						$dbAdapter->query($rfvTermstransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
					}
					
				}
			
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends					
		}
		
		$rfvRegId = 0;
		
		$selectRFV = $sql->select();
		$selectRFV->from('VM_RequestFormVendorRegister')
			   ->columns(array('*'))
			   ->where(array("RFQId"=>$rfqRegId, "VendorId"=>$vendorId));
		$statementRFVFound = $sql->getSqlStringForSqlObject($selectRFV);
		$resultsRFVVen = $dbAdapter->query($statementRFVFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsRFVVen){
		} else{ $rfvRegId=$resultsRFVVen['RegId'];
		}
		
		$select = $sql->select();
		if($rfvRegId==0)
		{
			$select->from(array("a"=>"VM_RFQRegister"))
				   ->columns(array(new Expression("0 as RegId,a.RFQRegId RFQId,a.RFQNo,'' as BidComments,'' as AddDocumentName,'' as AddDocumentPath,b.VendorId")),array("VendorId"))
				   ->join(array("b"=>"VM_RFQVendorTrans"), "a.RFQRegId=b.RFQId", array(), $select::JOIN_INNER)
				   ->where(array("a.RFQRegId"=>$rfqRegId, "b.VendorId"=>$vendorId));
		} else {
			$select->from(array('a'=>'VM_RequestFormVendorRegister'))
				   ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'BidComments','AddDocumentName','AddDocumentPath'),array('RFQNo'))
				   ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo"), $select::JOIN_INNER)
				   ->where(array("a.RegId"=>$rfvRegId));
		}			   
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVen){
			$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
		}
		
		$selectVendor1 = $sql->select();
		if($rfvRegId==0)
		{
			//Already Entry made for this vendor
			$selectVendorFound = $sql->select(); 
			$selectVendorFound->from(array("a"=>"VM_RequestFormVendorRegister"))
						->columns(array("VendorId"))
						->where(array('a.RFQId'=>$rfqRegId ));
			//Valid vendor 
			$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
						->columns(array("VendorId"), array("VendorName"))
						->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
						->where(array('a.RFQId'=>$rfqRegId))
						->where->notIn('a.VendorId',$selectVendorFound);
		} else {
			$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
						->columns(array("VendorId"), array("VendorName"))
						->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
						->where(array('a.RFQId'=>$rfqRegId));
		}		
		$rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$resSelect = $sql->select();
		if($rfvRegId==0)
		{
			$resSelect->from(array("a"=>"VM_RFQTrans"))
				->columns(array("ResourceId","Quantity"=>new Expression("sum(isnull(a.Quantity,0)) "),"Rate"=>new Expression("1-1")),array("Code","ResourceName"), array("UnitName"))
				->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resSelect::JOIN_INNER)
				->join(array('c'=>'Proj_UOM'), 'b.UnitId=c.UnitId', array("UnitName"), $resSelect:: JOIN_LEFT)
				->where(array('a.RFQId'=>$rfqRegId))
				->group(new expression('a.ResourceId,b.Code,b.ResourceName,c.UnitName'));
		} else {
			$resSelect->from(array("a"=>"VM_RFVTrans"))
				->columns(array("TransId","ResourceId","Quantity","Rate","Amount"),array("Code","ResourceName"), array("UnitName"))
				->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resSelect::JOIN_INNER)
				->join(array('c'=>'Proj_UOM'), 'b.UnitId=c.UnitId', array("UnitName"), $resSelect:: JOIN_LEFT)
				->where(array('a.RegId'=>$rfvRegId));
		}		
		$resStmt = $sql->getSqlStringForSqlObject($resSelect);
		$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		
		
		$Termselect = $sql->select();
		if($rfvRegId==0)
		{
			$Termselect->from(array("a"=>"VM_RFQTerms"))
				->columns(array(new Expression("a.TermsId,0 ValueFromNet,0 Per,0 Value,'' Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
					b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
				->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $Termselect::JOIN_INNER);
			$Termselect->where(array('a.RFQId'=>$rfqRegId));
		} else {
			$Termselect->from(array("a"=>"VM_RFVTerms"))
				->columns(array(new Expression("a.TermsId,a.ValueFromNet,a.Per,a.Value,a.Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
					b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
				->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $Termselect::JOIN_INNER);
			$Termselect->where(array('a.RegisterId'=>$rfvRegId));
		}
		$Termsstatement = $sql->getSqlStringForSqlObject($Termselect);
		$resultsFillTermdet= $dbAdapter->query($Termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*Submittal query*/
		$selectSubmittal = $sql->select(); 
		if($rfvRegId==0)
		{
			$selectSubmittal->from(array("a"=>"VM_RFQSubmittalTrans"))
				->columns(array("TransId","SubmittalName"))
				->where(array('RFQId' => $rfqRegId ));		
		} else {
			$selectSubmittal->from(array("a"=>"VM_RFVSubmittalTrans"))
				->columns(array("TransId", "RFQSubmittalTransId","SubmittalDocPath"),array("SubmittalName"))
				->join(array("b"=>"VM_RFQSubmittalTrans"), "a.RFQSubmittalTransId=b.TransId", array("SubmittalName"), $selectSubmittal::JOIN_INNER)
				->where(array('a.RegId' => $rfvRegId ));
		}				
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->rfqRegId = $rfqRegId;
		$this->_view->rfvRegId = $rfvRegId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->resultsRFVVen = $resultsRFVVen;
		$this->_view->resResult = $resResult;
		$this->_view->resultsFillTermdet = $resultsFillTermdet;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
		return $this->_view;
	}

	public function vendorLoginAction(){
		$this->layout("layout/portal");
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		$vendorDetail = new Container('vendorDetail');
		
		/*Renderer and config objects*/
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);		
		$request = $this->getRequest();
		$response = $this->getResponse();
		
		/*Ajax Request*/
		if($request->isXmlHttpRequest()){
			$resp = array();			
			if($request->isPost()){
				$postParam = $request->getPost();
				$selectVendor = $sql->select();
				$selectVendor->from("Vendor_Master")
							->columns(array("VendorId", "VendorName", "AllowOnline"))
							->where(array("UserName"=>$postParam['email'], "Password"=>$postParam['password']));
				$statement = $sql->getSqlStringForSqlObject($selectVendor);
				$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$resp['data'] = 1;
				foreach($results as $data){
					if($data['AllowOnline'] == 1){
						$resp['data'] = 2;
						$vendorDetail->vendorId = $data['VendorId'];
						$vendorDetail->vendorName = $data['VendorName'];						
					}
					else{
						$resp['data'] = 3;
					}
				}			
			}
			$this->_view->setTerminal(true);
			$response->setContent(json_encode($resp));
			return $response;				
		}
		else if($request->isPost()){
			$postParam = $request->getPost();
			$selectVendor = $sql->select();
			$selectVendor->from("Vendor_Master")
						->columns(array("VendorId", "VendorName", "AllowOnline"))
						->where(array("UserName"=>$postParam['email'], "Password"=>$postParam['password']));
			$statement = $sql->getSqlStringForSqlObject($selectVendor);
			$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$error = 'Username password is incorrect';
			foreach($results as $data){
				if($data['AllowOnline'] == 1){
					$error = '';
					$vendorDetail->vendorId = $data['VendorId'];
					$vendorDetail->vendorName = $data['VendorName'];
					$this->redirect()->toRoute("portal/default", array("controller" => "vendor","action" => "rfq-register"));
				}
				else{
					$error = "You haven't right to access this account. Please check with admin";
				}
			}
			$this->_view->error = $error;
		}
		return $this->_view;
	}

	public function rfqsentRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		$this->layout('layout/portal');
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();
		$vendorDetail = new Container('vendorDetail');
		if(!$vendorDetail->vendorId)
			$this->redirect()->toRoute("portal/default", array("controller" => "vendor","action" => "vendor-login"));
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$resp = array();
				$vendorId = $vendorDetail->vendorId;
				//Write your Ajax post code here
				$selectRFV = $sql->select();
				$selectRFV->from(array("a"=>"VM_RequestFormVendorRegister"));
				$selectRFV->columns(array(new Expression("a.RegId,Convert(varchar(10),a.Entrydate,105) as Entrydate ,a.BidComments,b.RFQNo,c.VendorName,
								CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve")))
							->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array(), $selectRFV::JOIN_INNER)
							->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array(), $selectRFV::JOIN_INNER)
							->where(array("a.VendorId"=>$vendorId))
							->order("a.Entrydate Desc");
				$statement = $sql->getSqlStringForSqlObject($selectRFV);
				$resp['result'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
			}
		} else if($request->isPost()) {
				//Write your Normal form post code here				
		}			
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
		return $this->_view;
	}

	public function rfqDetailviewAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout('layout/portal');
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$rfvId = $this->params()->fromRoute('regid');
		$request = $this->getRequest();
		$response = $this->getResponse();
		$vendorDetail = new Container('vendorDetail');
		if(!$vendorDetail->vendorId)
			$this->redirect()->toRoute("portal/default", array("controller" => "vendor","action" => "vendor-login"));
		
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
		}
		
		$select = $sql->select();
		$select->from('VM_RequestFormVendorRegister')
			   ->columns(array('RegId'))
			   ->where(array("RegId"=>$rfvId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsVen)==0){
			$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
		}
		
				
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectCurRequest->columns(array(new Expression("a.RegId,b.RFQRegId,Convert(varchar(10),a.Entrydate,105) as Entrydate ,a.BidComments,b.RFQNo,c.VendorName,
								CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve,
								a.BidComments,a.AddDocumentName,a.AddDocumentPath")))
							->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array(), $selectCurRequest::JOIN_INNER)
							->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array(), $selectCurRequest::JOIN_INNER);
		$selectCurRequest->where(array("a.RegId"=>$rfvId));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest); 
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$resSelect = $sql->select();
		$resSelect->from(array("a"=>"VM_RFVTrans"))
				->columns(array("TransId","ResourceId","Quantity","Rate","Amount"),array("Code","ResourceName"), array("UnitName"))
				->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resSelect::JOIN_INNER)
				->join(array('c'=>'Proj_UOM'), 'b.UnitId=c.UnitId', array("UnitName"), $resSelect:: JOIN_LEFT)
				->where(array('a.RegId'=>$rfvId));		
		$resStmt = $sql->getSqlStringForSqlObject($resSelect);
		$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$Termselect = $sql->select();
		$Termselect->from(array("a"=>"VM_RFVTerms"))
				->columns(array(new Expression("a.TermsId,a.ValueFromNet,a.Per,a.Value,a.Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
					b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
				->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $Termselect::JOIN_INNER);
		$Termselect->where(array('a.RegisterId'=>$rfvId));
		$Termsstatement = $sql->getSqlStringForSqlObject($Termselect);
		$resultsFillTermdet= $dbAdapter->query($Termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*Submittal query*/
		$selectSubmittal = $sql->select(); 		
		$selectSubmittal->from(array("a"=>"VM_RFVSubmittalTrans"))
			->columns(array("TransId", "RFQSubmittalTransId","SubmittalDocPath"),array("SubmittalName"))
			->join(array("b"=>"VM_RFQSubmittalTrans"), "a.RFQSubmittalTransId=b.TransId", array("SubmittalName"), $selectSubmittal::JOIN_INNER)
			->where(array('a.RegId' => $rfvId ));
						
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		$this->_view->rfvId = $rfvId;
		$this->_view->results = $results;
		$this->_view->resResult = $resResult;
		$this->_view->resultsFillTermdet = $resultsFillTermdet;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
		return $this->_view;
		
	}

	public function techInfoAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout('layout/portal');
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);

		$request = $this->getRequest();
		$response = $this->getResponse();
		$vendorDetail = new Container('vendorDetail');
		if(!$vendorDetail->vendorId)
			$this->redirect()->toRoute("portal/default", array("controller" => "vendor","action" => "vendor-login"));
		
		$rfqRegId = $this->params()->fromRoute('rfqid');
		$vendorId =$vendorDetail->vendorId;
		
		$iTechVerificationFound=0;
		$iBidafterVerification=0;
		$select = $sql->select();
		$select->from(array('a'=>'VM_RFQRegister'))
			   ->columns(array('BidafterVerification','TechVerification'))
			   ->where(array("a.RFQRegId"=>$rfqRegId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsrfqFound = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsrfqFound){
		//$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
		}
		else { 
		$iTechVerificationFound = $resultsrfqFound['TechVerification'];
		$iBidafterVerification = $resultsrfqFound['BidafterVerification'];
		}
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		} else if($request->isPost()) {			
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			$files = $request->getFiles();
			try {
			
			$regId= $postParams['regId'];
			$isPortal=1;
			if($regId==0)
			{
				$registerInsert = $sql->insert('VM_TechInfoRegister');
				$registerInsert->values(array("Entrydate"=>date('Y-m-d'),"SubmittedOn" => date('Y-m-d', strtotime($postParams['validFrom'])), "VendorId"=>$vendorId,
						"RFQId"=>$postParams['rfqId'],"Narration"=>$postParams['bidComments'],"Isportal"=>$isPortal ));						
				$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$regId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				$techjson = json_decode($postParams['hidTechverification'], true);
				/*For admin --> public/uploads/vendor/rfq/technical/rfqId/vendorId/filename --*/
				foreach($techjson as $techVer){
					$pathname = '';
					if($files['files_'.$techVer]['name']){
						$dir = 'public/uploads/vendor/rfq/technical/'.$postParams['rfqId'].'/'.$vendorId.'/';
						if(!is_dir($dir))
							mkdir($dir, 0755, true);
						
						$ext = pathinfo($files['files_'.$techVer]['name'], PATHINFO_EXTENSION);
						$path = $dir.$postParams['documentName_'.$techVer].'.'.$ext;
						move_uploaded_file($files['files_'.$techVer]['tmp_name'], $path);
						$pathname = explode('public/', $path)[1];
					}					
					//insert VM_RFVSubmittalTrans
					$rfvTechVerInsert = $sql->insert('VM_RFVTechVerificationTrans');
					$rfvTechVerInsert->values(array("RegId"=>$regId, "VendorId"=>$vendorId,
					"RFQTechTransId"=>$techVer,"TechDocPath"=> $pathname));
							
					$rfvTechtransStatement = $sql->getSqlStringForSqlObject($rfvTechVerInsert);
					$dbAdapter->query($rfvTechtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}				
			} else {
				//update VM_TechInfoRegister
				$registerUpdate = $sql->update();
				$registerUpdate->table('VM_TechInfoRegister');
				$registerUpdate->set(array(
					'Entrydate' => date('Y-m-d'),
					'VendorId' => $vendorId,
					'SubmittedOn' => date('Y-m-d', strtotime($postParams['validFrom'])),					
					'Narration' => $postParams['bidComments'],
					'Isportal' => $isPortal
				 ));
				$registerUpdate->where(array('RegId'=>$regId));			
				$registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);				

				$techjson = json_decode($postParams['hidTechverification'], true);
				/*For admin --> public/uploads/vendor/rfq/technical/rfqId/vendorId/filename --*/
				foreach($techjson as $techVer){
					$pathname = $postParams['documentPath_'.$techVer];
					if($files['files_'.$techVer]['name']){
						$dir = 'public/uploads/vendor/rfq/technical/'.$postParams['rfqId'].'/'.$vendorId.'/';
						if(!is_dir($dir))
							mkdir($dir, 0755, true);
						
						$ext = pathinfo($files['files_'.$techVer]['name'], PATHINFO_EXTENSION);
						$path = $dir.$postParams['documentName_'.$techVer].'.'.$ext;
						move_uploaded_file($files['files_'.$techVer]['tmp_name'], $path);
						$pathname = explode('public/', $path)[1];
					}					
					//update VM_RFVTechVerificationTrans
					$rfvTechVerUpdate = $sql->update();
					$rfvTechVerUpdate->table('VM_RFVTechVerificationTrans');
					$rfvTechVerUpdate->set(array(
						'TechDocPath' => $pathname						  
					 ));
					$rfvTechVerUpdate->where(array('TransId'=>$techVer,'RegId'=>$regId));	
						
					$rfvTechtransStatement = $sql->getSqlStringForSqlObject($rfvTechVerUpdate);
					$dbAdapter->query($rfvTechtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
			}
							
				$connection->commit();
	
				if($iTechVerificationFound==1 && $iBidafterVerification==0)
				{
					$this->redirect()->toRoute('portal/response-entry', array('controller' => 'vendor','action' => 'response-entry','rfqid' => $rfqRegId));
				}
				else if ($iTechVerificationFound==1 && $iBidafterVerification==1)
				{
					$this->redirect()->toRoute('portal/rfqrequested-detail', array('controller' => 'vendor','action' => 'rfqrequested-detail','rfqid' => $rfqRegId));
				}
				
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends
					
		}		
		
		$regId =0;
				
		$select = $sql->select();
		$select->from(array('a'=>'VM_TechInfoRegister'))
			   ->columns(array('RegId'))
			   ->where(array("a.RFQId"=>$rfqRegId, "a.VendorId"=>$vendorId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVenFound = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVenFound){
		}
		else { $regId=$resultsVenFound['RegId'];}

		$select = $sql->select();
		if($regId!=0)
		{
			$select->from(array('a'=>'VM_TechInfoRegister'))
				   ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),
				   'ValidFrom'=>new Expression("Convert(varchar(10),a.SubmittedOn,105)"),'Narration','Valid'),array("RFQNo","TechVerification"), array("VendorName"))
				   ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo","TechVerification"), $select::JOIN_INNER)
				   ->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array("VendorName"), $select::JOIN_INNER)
				   ->where(array("a.RegId"=>$regId));
		} else {
			$select->from(array('a'=>'VM_RFQRegister'))
				   ->columns(array(new Expression("a.RFQRegId as RFQId, a.RFQNo, Convert(varchar(10),GETDATE(),105) as Entrydate, Convert(varchar(10),
				   GETDATE(),105) as ValidFrom ,a.TechVerification,'' as Narration,'' as Valid,b.VendorId,c.VendorName")))
				    ->join(array("b"=>"VM_RFQVendorTrans"), "a.RFQRegId=b.RFQId", array(), $select::JOIN_INNER)
				   ->join(array("c"=>"Vendor_Master"), "b.VendorId=c.VendorId", array(), $select::JOIN_INNER)
				   ->where(array("a.RFQRegId"=>$rfqRegId, "b.VendorId"=>$vendorId));
		}
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVen){
			$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
		} else {		
			 if($resultsVen['TechVerification']==0 || $resultsVen['Valid']=="A")
			 {
				$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
			 }
		}
		
		/*load Technical doc query*/
		$selectRFVTech = $sql->select();
		if($regId!=0)
		{
			$selectRFVTech->from(array("a"=>"VM_RFVTechVerificationTrans"))
				->columns(array("TransId", "RFQTechTransId","TechDocPath"),array("DocumentName","Description","DocumentFormat"))
				->join(array("b"=>"VM_RFQTechVerificationTrans"), "a.RFQTechTransId=b.TransId", array("DocumentName","Description","DocumentFormat"), $selectRFVTech::JOIN_INNER)
				->where(array('a.RegId' => $regId ));
		} else {
		
			$selectRFVTech->from(array("a"=>"VM_RFQTechVerificationTrans"))
				->columns(array(new Expression("a.TransId,a.DocumentName,a.Description,a.DocumentFormat,'' as TechDocPath")))
				->join(array("b"=>"VM_RFQVendorTrans"), "a.RFQId=b.RFQId", array(), $selectRFVTech::JOIN_INNER)
				->where(array('a.RFQId' => $rfqRegId, "b.VendorId"=>$vendorId ));
		}
		
		$rfqTechStatement = $sql->getSqlStringForSqlObject($selectRFVTech);
		$rfqTechResult = $dbAdapter->query($rfqTechStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->regId = $regId;
		$this->_view->rfqRegId = $rfqRegId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->rfqTechResult = $rfqTechResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
		return $this->_view;
	}
	
	public function rfqrequestedDetailAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout('layout/portal');
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$RfqId = $this->params()->fromRoute('rfqid');
		$request = $this->getRequest();
		$response = $this->getResponse();
		$vendorDetail = new Container('vendorDetail');
		if(!$vendorDetail->vendorId)
			$this->redirect()->toRoute("portal/default", array("controller" => "vendor","action" => "vendor-login"));
		
		$vendorId =$vendorDetail->vendorId;
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
		}

		$select = $sql->select();
		$select->from('VM_RFQRegister')
			   ->columns(array('RFQRegId'))
			   ->where(array("RFQRegId"=>$RfqId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		if(count($resultsVen)==0){
			$this->redirect()->toRoute('portal/default', array('controller' => 'vendor','action' => 'rfq-register'));
		}
		
		//Vendor Entry done against RFQ
		$rfqVendorTransUpdate = $sql->update();
		$rfqVendorTransUpdate->table('VM_RFQVendorTrans');
		$rfqVendorTransUpdate->set(array(					
			'PortalStatus' => 1
		 ));
		$rfqVendorTransUpdate->where(array('RFQId'=>$RfqId, 'VendorId'=>$vendorId, 'PortalStatus'=>0));			
		$rfqVendorTransStatement = $sql->getSqlStringForSqlObject($rfqVendorTransUpdate);
		$rfqVendorTransResults = $dbAdapter->query($rfqVendorTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
						
		$selectCurRequest = $sql->select();
		$selectCurRequest->from(array("a"=>"VM_RFQRegister"));
		$selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,a.Approve,
								CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,CASE WHEN a.Submittal=1 THEN 'Yes' Else 'No' END as Submittal,CASE WHEN a.BidafterVerification=1 THEN 'Yes' Else 'No' END as BidafterVerification,
								a.Narration,a.ContactName,a.ContactNo,a.Designation,a.ContactAddress,a.BidInformation,a.SubmittalNarration,
								multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
							->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
		$selectCurRequest->where(array("a.RFQRegId"=>$RfqId));
		$statement = $sql->getSqlStringForSqlObject($selectCurRequest); 
		$results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


		$selectTechEntryFound = $sql->select();
		$selectTechEntryFound->from(array("a"=>"VM_TechInfoRegister"))
				->columns(array("RegId","Valid"))					
				->where(array("a.RFQId"=>$RfqId, "a.VendorId"=>$vendorId));
		$statement = $sql->getSqlStringForSqlObject($selectTechEntryFound);
		$resultsTechEntryFound = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$selectPortalStatusFound = $sql->select();
		$selectPortalStatusFound->from(array("a"=>"VM_RFQVendorTrans"))
				->columns(array("PortalStatus"))					
				->where(array("a.RFQId"=>$RfqId, "a.VendorId"=>$vendorId));
		$statementPortalStatus = $sql->getSqlStringForSqlObject($selectPortalStatusFound);
		$resultsPortalStatusFound = $dbAdapter->query($statementPortalStatus, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		$selectMultiCC = $sql->select();
		$selectMultiCC->from(array("a"=>"VM_RFQMultiCCTrans"));
		$selectMultiCC->columns(array("CostCentreId"),array("CostCentreName"))
							->join(array("b"=>"WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array("CostCentreName"), $selectMultiCC::JOIN_INNER);
		$selectMultiCC->where(array("a.RFQId"=>$RfqId));
		$statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC); 
		$resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectSubmittalDet = $sql->select();
		$selectSubmittalDet->from(array("a"=>"VM_RFQSubmittalTrans"));
		$selectSubmittalDet->columns(array("SubmittalName"))
							->where(array("a.RFQId"=>$RfqId));
		$statementSubmittalDet = $sql->getSqlStringForSqlObject($selectSubmittalDet); 
		$resultSubmittal = $dbAdapter->query($statementSubmittalDet, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$selectTechVer = $sql->select(); 
		$selectTechVer->from(array("a"=>"VM_RFQTechVerificationTrans"))
			->where(array('a.RFQId' => $RfqId ));				
		$rfqTechVerStatement = $sql->getSqlStringForSqlObject($selectTechVer);
		$rfqTechVerResult = $dbAdapter->query($rfqTechVerStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$rfvRegId=0;
		$select = $sql->select();
		$select->from('VM_RequestFormVendorRegister')
			   ->columns(array('RegId'))
			   ->where(array("RFQId"=>$RfqId, "VendorId"=>$vendorId));
		$statementResponseFound = $sql->getSqlStringForSqlObject($select);
		$resultsResponseFound = $dbAdapter->query($statementResponseFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsResponseFound){
		} else { $rfvRegId=$resultsResponseFound['RegId'];
		}
		
		
		$this->_view->RfqId = $RfqId;
		$this->_view->rfvRegId = $rfvRegId;
		$this->_view->results = $results;
		$this->_view->resultMultiCC = $resultMultiCC;
		$this->_view->resultSubmittal = $resultSubmittal;
		$this->_view->rfqTechVerResult = $rfqTechVerResult;
		$this->_view->resultsTechEntryFound = $resultsTechEntryFound;
		$this->_view->resultsPortalStatusFound = $resultsPortalStatusFound;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);			
		return $this->_view;
		
	}
}