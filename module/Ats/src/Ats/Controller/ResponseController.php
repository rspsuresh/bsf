<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Ats\Controller;

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


class ResponseController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function responseEntryAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
		$sql = new Sql($dbAdapter);
		$rfqRegId = $this->params()->fromRoute('rfqid');
		$vendorId = $this->params()->fromRoute('vendorid');
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$result =  "";
				$this->_view->setTerminal(true);
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
				//print_r($postParams);die;
				$pathname = '';
				if($files['additional_files']['name']){
					$dir = 'public/uploads/ats/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
					if(!is_dir($dir))
						mkdir($dir, 0755, true);
					
					$path = $dir.$files['additional_files']['name'];
					move_uploaded_file($files['additional_files']['tmp_name'], $dir.$files['additional_files']['name']);
					$pathname = explode('public/', $path)[1];
				}
				
				$isPortal=0;
				$registerInsert = $sql->insert('VM_RequestFormVendorRegister');
				$registerInsert->values(array(
                        "Entrydate"=>date('Y-m-d'),
                        "VendorId"=>$this->bsf->isNullCheck($postParams['VendorId'],'number'),
						"RFQId"=>$this->bsf->isNullCheck($postParams['rfqId'],'number'),
                        "BidComments"=>$this->bsf->isNullCheck($postParams['bidComments'],'string'),
						"AddDocumentName"=>$this->bsf->isNullCheck($postParams['addDocumentName'],'string'),
						"AddDocumentPath"=>$pathname,
                        "Isportal"=>$isPortal
                ));
				$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$regId = $dbAdapter->getDriver()->getLastGeneratedValue();

				$resJson = json_decode($postParams['hidResTrans'], true);
				foreach($resJson as $res){
					//insert VM_RFVTrans
					$rfqvtransInsert = $sql->insert('VM_RFVTrans');
					$rfqvtransInsert->values(array(
                        "RegId"=>$regId,
                        "ResourceId"=>$res,
                        "ItemId"=>$this->bsf->isNullCheck($postParams['itemid_'.$res],'number'),
                        "Quantity"=>$this->bsf->isNullCheck($postParams['quantity_'.$res],'number'),
					    "Rate"=>$this->bsf->isNullCheck($postParams['rate_'.$res],'number'),
                        "Amount"=>$this->bsf->isNullCheck(($postParams['quantity_'.$res] * $postParams['rate_'.$res]),'number')
                    ));
					$rfvtransStatement = $sql->getSqlStringForSqlObject($rfqvtransInsert);
					$dbAdapter->query($rfvtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}

				$submittaljson = json_decode($postParams['hidSubmittal'], true);
				/*For admin --> public/uploads/vendor/rfq/response/rfqId/vendorId/filename --*/
				foreach($submittaljson as $submittal){
					$pathname = '';
					if($files['files_'.$submittal]['name']){
						$dir = 'public/uploads/ats/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
						if(!is_dir($dir))
							mkdir($dir, 0755, true);
						
						//$ext = pathinfo($files['files_'.$submittal]['name'], PATHINFO_EXTENSION);
						$path = $dir.$files['files_'.$submittal]['name'];
						move_uploaded_file($files['files_'.$submittal]['tmp_name'],$dir.$files['files_'.$submittal]['name']);
						$pathname = explode('public/', $path)[1];
					}			
					//insert VM_RFVSubmittalTrans
					$rfvSubmittalInsert = $sql->insert('VM_RFVSubmittalTrans');
					$rfvSubmittalInsert->values(array(
                        "RegId"=>$regId,
                        "VendorId"=>$this->bsf->isNullCheck($postParams['VendorId'],'number'),
					    "RFQSubmittalTransId"=>$submittal,
                        "SubmittalDocPath"=> $pathname
                    ));
					$rfvSubmittaltransStatement = $sql->getSqlStringForSqlObject($rfvSubmittalInsert); 
					$dbAdapter->query($rfvSubmittaltransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				//VM_RFVTerms
				$termsjson = json_decode($postParams['hidTerms'], true);
				foreach($termsjson as $terms){				
					$rfvTermsInsert = $sql->insert('VM_RFVTerms');
					$rfvTermsInsert->values(array(
                        "RegisterId"=>$regId,
                        "TermsId"=>$terms,
					    "ValueFromNet"=>$this->bsf->isNullCheck($postParams['valueFromNet_'.$terms],'number'),
                        "Per"=>$this->bsf->isNullCheck($postParams['percentage_'.$terms],'number'),
					    "Value"=>$this->bsf->isNullCheck($postParams['value_'.$terms],'number'),
                        "Period"=>$this->bsf->isNullCheck($postParams['period_'.$terms],'number')
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
				$rfqVendorTransUpdate->where(array(
                    'RFQId'=>$this->bsf->isNullCheck($postParams['rfqId'],'number'),
                    'VendorId'=>$this->bsf->isNullCheck($postParams['VendorId'],'number')
                ));
				$rfqVendorTransStatement = $sql->getSqlStringForSqlObject($rfqVendorTransUpdate);
				$rfqVendorTransResults = $dbAdapter->query($rfqVendorTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
							
				$connection->commit();
				$this->redirect()->toRoute('ats/rfq-detailed', array('controller' => 'rfq','action' => 'rfqresponse-track','rfqid' => $rfqRegId));
				//$this->redirect()->toRoute('ats/response-detailview', array('controller' => 'response','action' => 'response-detailview','regid' => $regId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			
		}
		
		$iTechVerificationFound=0;
		$select = $sql->select();
		$select->from('VM_RFQRegister')
			   ->columns(array('RFQRegId', 'RFQNo','TechVerification','Submittal','RFQType'))
			   ->where(array("RFQRegId"=>$rfqRegId));
		 $statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$Submittal= $resultsVen['Submittal'];
		//echo $Submittal; die;
		
		if(!$resultsVen){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
		} else {
			$iTechVerificationFound= $resultsVen['TechVerification'];
		}

		$selectVendorFound = $sql->select();
		//Already Entry made for this vendor
		$selectVendorFound->from(array("a"=>"VM_RequestFormVendorRegister"))
					->columns(array("VendorId"))
					->where(array('a.RFQId'=>$rfqRegId ));
						
		$selectVendor1 = $sql->select();		
		if($iTechVerificationFound==0)
		{
			//Valid vendor  
			$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
						->columns(array("VendorId"), array("VendorName"))
						->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
						->where(array('a.RFQId'=>$rfqRegId))
						->where->notIn('a.VendorId',$selectVendorFound);					
		} else{
			//Valid vendor for Technical Entry done
			$selectVendor1->from(array("a"=>"VM_TechInfoRegister"))
						->columns(array("VendorId"), array("VendorName"))
						->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
						->where(array('a.RFQId'=>$rfqRegId))
						->where->notIn('a.VendorId',$selectVendorFound);	
		}
        $rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if($resultsVen['RFQType'] == '2' || $resultsVen['RFQType'] == '3' || $resultsVen['RFQType'] == '4') {
            $resSelect = $sql->select();
            $resSelect->from(array("a" => "VM_RFQTrans"))
                ->columns(array(new Expression("a.ResourceId,a.ItemId,SUM(ISNULL(a.Quantity,0)) As Quantity,
                          Case When a.ItemId>0 Then d.ItemCode Else b.Code End As Code,Case when a.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,
                          c.UnitName")))
                ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array(), $resSelect::JOIN_INNER)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $resSelect:: JOIN_LEFT)
                ->join(array('d' => "MMS_Brand"), "a.ResourceId=d.ResourceId and a.ItemId=d.BrandId", array(), $resSelect::JOIN_LEFT)
                ->where(array('a.RFQId' => $rfqRegId))
                ->group(new expression('a.ResourceId,a.ItemId,b.Code,b.ResourceName,c.UnitName,d.ItemCode,d.BrandName'));
            $resStmt = $sql->getSqlStringForSqlObject($resSelect);
            $resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        else if($resultsVen['RFQType'] == '5') {
            $resSelect = $sql->select();
            $resSelect->from(array("a" => "VM_RFQTrans"))
                ->columns(array(new Expression("a.IowId As ResourceId,a.ItemId,SUM(ISNULL(a.Quantity,0)) As Quantity,
                          b.RefSerialNo As Code,b.Specification As ResourceName,
                          c.UnitName")))
                ->join(array("b" => "Proj_ProjectIowMaster"), "a.IowId=b.ProjectIowId", array(), $resSelect::JOIN_INNER)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $resSelect:: JOIN_LEFT)
                ->where(array('a.RFQId' => $rfqRegId))
                ->group(new expression('a.IowId,a.ItemId,b.RefSerialNo,b.Specification,c.UnitName'));
            $resStmt = $sql->getSqlStringForSqlObject($resSelect);
            $resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        else if ($resultsVen['RFQType'] == '6') {
            $resSelect = $sql->select();
            $resSelect->from(array("a" => "VM_RFQTrans"))
                ->columns(array(new Expression("a.ResourceId As ResourceId,a.ItemId,SUM(ISNULL(a.Quantity,0)) As Quantity,
                          b.ServiceCode As Code,b.ServiceName As ResourceName,
                          c.UnitName")))
                ->join(array("b" => "Proj_ServiceMaster"), "a.ResourceId=b.ServiceId", array(), $resSelect::JOIN_INNER)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $resSelect:: JOIN_LEFT)
                ->where(array('a.RFQId' => $rfqRegId))
                ->group(new expression('a.ResourceId,a.ItemId,b.ServiceCode,b.ServiceName,c.UnitName'));
            $resStmt = $sql->getSqlStringForSqlObject($resSelect);
            $resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
			
		$Termselect = $sql->select(); 
		$Termselect->from(array("a"=>"VM_RFQTerms"))
			->columns(array(new Expression("a.TermsId,a.ValueFromNet,a.Per,a.Value,a.Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
				b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
			->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $Termselect::JOIN_INNER);
		$Termselect->where(array('a.RFQId'=>$rfqRegId));
		$Termsstatement = $sql->getSqlStringForSqlObject($Termselect);
		$resultsFillTermdet= $dbAdapter->query($Termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*Submittal query*/
		$selectSubmittal = $sql->select(); 
		$selectSubmittal->from(array("a"=>"VM_RFQSubmittalTrans"))
			->columns(array("TransId","SubmittalName"))
			->where(array('RFQId' => $rfqRegId ));				
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->vendorId = $vendorId;
		$this->_view->rfqRegId = $rfqRegId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->resResult = $resResult;
		$this->_view->Submittal = $Submittal;
		$this->_view->resultsFillTermdet = $resultsFillTermdet;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}

	public function editResponseAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
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
		}
		else if ($request->isPost()) {
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			$files = $request->getFiles();
			try {				
				$regId=$postParams['regId'];
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
					$dir = 'public/uploads/ats/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
					if(!is_dir($dir))
						mkdir($dir, 0755, true);
					
					unlink('public/'.$pathname);
					$path = $dir.$files['additional_files']['name'];
					move_uploaded_file($files['additional_files']['tmp_name'], $dir.$files['additional_files']['name']);
					$pathname = explode('public/', $path)[1];
				}
				$isPortal=0;			
				//update VM_RequestFormVendorRegister
				$registerUpdate = $sql->update();
				$registerUpdate->table('VM_RequestFormVendorRegister');
				$registerUpdate->set(array(
					'Entrydate' => date('Y-m-d'),
					'VendorId' => $this->bsf->isNullCheck($postParams['VendorId'],'number'),
					//'RFQId' => $postParams['rfqId'],					
					'BidComments' => $this->bsf->isNullCheck($postParams['bidComments'],'string'),
					'AddDocumentName'=>$this->bsf->isNullCheck($postParams['addDocumentName'],'string'),
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
					$rfqvtransInsert->values(array(
                        "RegId"=>$regId,
                        "ResourceId"=>$res,
                        "ItemId"=>$this->bsf->isNullCheck($postParams['itemid_'.$res],'number'),
                        "Quantity"=>$this->bsf->isNullCheck($postParams['quantity_'.$res],'number'),
					    "Rate"=>$this->bsf->isNullCheck($postParams['rate_'.$res],'number'),
                        "Amount"=>$this->bsf->isNullCheck(($postParams['quantity_'.$res] * $postParams['rate_'.$res]),'number')
                    ));
					 $rfvtransStatement = $sql->getSqlStringForSqlObject($rfqvtransInsert);
					$dbAdapter->query($rfvtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}

				$submittaljson = json_decode($postParams['hidSubmittal'], true);
				/*For admin --> public/uploads/vendor/rfq/response/rfqId/vendorId/filename --*/
				foreach($submittaljson as $submittal){
					$pathname = $postParams['submittalPath_'.$submittal];
					if($files['files_'.$submittal]['name']){
						$dir = 'public/uploads/ats/rfq/response/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
						if(!is_dir($dir))
							mkdir($dir, 0755, true);
						
						unlink('public/'.$pathname);
						$path = $dir.$files['files_'.$submittal]['name'];
						move_uploaded_file($files['files_'.$submittal]['tmp_name'], $dir.$files['files_'.$submittal]['name']);
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
					$rfvTermsInsert->values(array(
                        "RegisterId"=>$regId,
                        "TermsId"=>$terms,
					    "ValueFromNet"=>$this->bsf->isNullCheck($postParams['valueFromNet_'.$terms],'number'),
                        "Per"=>$this->bsf->isNullCheck($postParams['percentage_'.$terms],'number'),
					    "Value"=>$this->bsf->isNullCheck($postParams['value_'.$terms],'number'),
                        "Period"=>$this->bsf->isNullCheck($postParams['period_'.$terms],'number')
					//"TDate"=>$postParams['Date_'.$terms],"TString"=>$postParams['Str_'.$terms]
					));
					$rfvTermstransStatement = $sql->getSqlStringForSqlObject($rfvTermsInsert);
					$dbAdapter->query($rfvTermstransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$connection->commit();
				$this->redirect()->toRoute('ats/response-detailview', array('controller' => 'response','action' => 'response-detailview','regid' => $regId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends
		}
		$regId = $this->params()->fromRoute('regid');
		$rfqRegId=0;
		$select = $sql->select();
		$select->from(array('a'=>'VM_RequestFormVendorRegister'))
			   ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'BidComments','AddDocumentName','AddDocumentPath'),array('RFQNo'))
			   ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo"), $select::JOIN_INNER)
			   ->where(array("a.RegId"=>$regId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVen){
			$this->redirect()->toRoute('ats/default', array('controller' => 'response','action' => 'response-register'));
		}
		else { $rfqRegId=$resultsVen['RFQId'];}
		
		$selectVendor1 = $sql->select(); 
		$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
					->columns(array("VendorId"), array("VendorName"))
					->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
					->where(array('a.RFQId'=>$rfqRegId));
		$rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$resRFVtransSelect = $sql->select(); 
		$resRFVtransSelect->from(array("a"=>"VM_RFVTrans"))
				->columns(array("TransId","ResourceId","Quantity","Rate","Amount"),array("Code","ResourceName"), array("UnitName"))
				->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resRFVtransSelect::JOIN_INNER)
				->join(array('c'=>'Proj_UOM'), 'b.UnitId=c.UnitId', array("UnitName"), $resRFVtransSelect:: JOIN_LEFT)
				->where(array('a.RegId'=>$regId));
		$resStmt = $sql->getSqlStringForSqlObject($resRFVtransSelect);
		$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		
		
		/*load Terms query*/
		$TermRFVselect = $sql->select(); 
		$TermRFVselect->from(array("a"=>"VM_RFVTerms"))
			->columns(array(new Expression("a.TermsId,a.ValueFromNet,a.Per,a.Value,a.Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
				b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
			->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $TermRFVselect::JOIN_INNER);
		$TermRFVselect->where(array('a.RegisterId'=>$regId));
		$Termsstatement = $sql->getSqlStringForSqlObject($TermRFVselect);
		$resultsFillTermdet= $dbAdapter->query($Termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*load Submittal query*/
		$selectRFVSubmittal = $sql->select(); 
		$selectRFVSubmittal->from(array("a"=>"VM_RFVSubmittalTrans"))
			->columns(array("TransId", "RFQSubmittalTransId","SubmittalDocPath"),array("SubmittalName"))
			->join(array("b"=>"VM_RFQSubmittalTrans"), "a.RFQSubmittalTransId=b.TransId", array("SubmittalName"), $selectRFVSubmittal::JOIN_INNER)
			->where(array('a.RegId' => $regId ));				
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectRFVSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->regId = $regId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->resResult = $resResult;
		$this->_view->resultsFillTermdet = $resultsFillTermdet;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		
		return $this->_view;
	}

	public function responseRegisterAction(){
		if(!$this->auth->hasIdentity()) {
			if($this->getRequest()->isXmlHttpRequest())	{
				echo "session-expired"; exit();
			} else {
				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
			}
		}
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
		$dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
		$response = $this->getResponse();

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$resp = array();

				$this->_view->setTerminal(true);
				$response->setContent(json_encode($resp));
				return $response;
				
			}			
		} else if($request->isPost()) {
				//Write your Normal form post code here				
		}
		
		$selectTotsentRFQ = $sql->select();
		$selectTotsentRFQ->from(array("a"=>"VM_RFQRegister"));
		$selectTotsentRFQ->columns(array(new Expression("a.RFQRegId,COUNT(*) as totalsent,0 Received")))
					->join(array("c"=>"VM_RFQVendorTrans"), "a.RFQRegId=c.RFQId", array(), $selectTotsentRFQ::JOIN_LEFT)
                    ->where (array("DeleteFlag"=>0))
					->group(new expression('a.RFQRegId'));
					
		$selectTotRecRFQ = $sql->select();
		$selectTotRecRFQ->from(array("a"=>"VM_RFQRegister"));
		$selectTotRecRFQ->columns(array(new Expression("a.RFQRegId,0 totalsent,COUNT(c.VendorId) as Received")))
					->join(array("c"=>"VM_RequestFormVendorRegister"), "a.RFQRegId=c.RFQId", array(), $selectTotRecRFQ::JOIN_INNER)
                    ->where (array("a.DeleteFlag"=>0))
					->group(new expression('a.RFQRegId'));					
		$selectTotRecRFQ->combine($selectTotsentRFQ,'Union ALL');
			
		$selectRFQ = $sql->select(); 
		$selectRFQ->from(array("G"=>$selectTotRecRFQ))
				->columns(array(new Expression("G.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate, 
					CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification, 
					sum(G.totalsent) as totalsent,Sum(G.Received) as Received,Sum(G.totalsent-G.Received) as Pending, 
					CASE WHEN a.Approve='Y' THEN 'Yes' WHEN a.Approve='P' THEN 'Partial' Else 'No' END as Approve,b.TypeName  ") ))
				->join(array("a"=>"VM_RFQRegister"), "G.RFQRegId=a.RFQRegId", array(), $selectRFQ::JOIN_INNER)
				->join(array('b'=>'Proj_ResourceType'), 'a.RFQType=b.TypeId', array(), $selectRFQ:: JOIN_LEFT)
                ->where (array("a.DeleteFlag"=>0))
				->group(new expression('G.RFQRegId,a.RFQDate,a.RFQNo,b.TypeName,a.TechVerification,a.Approve'))
				->order("a.RFQDate Desc");
				
		$statement = $sql->getSqlStringForSqlObject($selectRFQ);		
		$result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);		
		
		$this->_view->result = $result;
		return $this->_view;		
	}

	public function techinfoEntryAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
		$rfqRegId = $this->params()->fromRoute('rfqid');
		$vendorId = $this->params()->fromRoute('vendorid');
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
		} else if ($request->isPost()) {
			
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			   // echo"<pre>";
               // print_r($postParams);
               // echo"</pre>";
               // die;
               // return;
			$files = $request->getFiles();
			try {
			
				$isPortal=0;
			
				$registerInsert = $sql->insert('VM_TechInfoRegister');
				$registerInsert->values(array(
                    "Entrydate"=>date('Y-m-d'),
                    "SubmittedOn" => date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['validFrom'],'date'))),
                    "VendorId"=>$this->bsf->isNullCheck($postParams['VendorId'],'number'),
					"RFQId"=>$this->bsf->isNullCheck($postParams['rfqId'],'number'),
                    "Narration"=>$this->bsf->isNullCheck($postParams['bidComments'],'string'),
                    "Isportal" => $isPortal
                ));
				$registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				
				$regId = $dbAdapter->getDriver()->getLastGeneratedValue();
				
				$techjson = json_decode($postParams['hidTechverification'], true);
				/*For admin --> public/uploads/vendor/rfq/technical/rfqId/vendorId/filename --*/
				foreach($techjson as $techVer){
					$pathname = '';
					if($files['files_'.$techVer]['name']){
						$dir = 'public/uploads/vendor/rfq/technical/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
						if(!is_dir($dir))
							mkdir($dir, 0755, true);
						
						$ext = pathinfo($files['files_'.$techVer]['name'], PATHINFO_EXTENSION);
						$path = $dir.$postParams['documentName_'.$techVer].'.'.$ext;
						move_uploaded_file($files['files_'.$techVer]['tmp_name'], $path);
						$pathname = explode('public/', $path)[1];
					}					
					//insert VM_RFVSubmittalTrans
					$rfvTechVerInsert = $sql->insert('VM_RFVTechVerificationTrans');
					$rfvTechVerInsert->values(array(
                        "RegId"=>$regId,
                        "VendorId"=>$this->bsf->isNullCheck($postParams['VendorId'],'number'),
					    "RFQTechTransId"=>$techVer,
                        "TechDocPath"=> $pathname
                    ));
					$rfvTechtransStatement = $sql->getSqlStringForSqlObject($rfvTechVerInsert);
					$dbAdapter->query($rfvTechtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
										
				$connection->commit();
				$this->redirect()->toRoute('ats/rfq-detailed', array('controller' => 'rfq','action' => 'rfqresponse-track','rfqid' => $rfqRegId));
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}			
		}
		
		
		$select = $sql->select();
		$select->from('VM_RFQRegister')
			   ->columns(array('RFQRegId', 'RFQNo','TechVerification'))
			   ->where(array("RFQRegId"=>$rfqRegId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVen){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
		}
		else{
			$sTechValid=$resultsVen['TechVerification'];
			if($sTechValid==0)
			{
				$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
			}
		}
		//Already Entry made for this vendor
		$selectVendorFound = $sql->select(); 
		$selectVendorFound->from(array("a"=>"VM_TechInfoRegister"))
					->columns(array("VendorId"))
					->where(array('a.RFQId'=>$rfqRegId ));
		//Valid vendor 
		$selectVendor1 = $sql->select(); 
		$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
					->columns(array("VendorId"), array("VendorName"))
					->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
					->where(array('a.RFQId'=>$rfqRegId))
					->where->notIn('a.VendorId',$selectVendorFound);
	    $rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		/*Submittal query*/
		$selectTech = $sql->select(); 
		$selectTech->from(array("a"=>"VM_RFQTechVerificationTrans"))
			->columns(array("TransId","DocumentName","Description","DocumentFormat"))
			->where(array('RFQId' => $rfqRegId ));				
		$rfqTechStatement = $sql->getSqlStringForSqlObject($selectTech);
		$rfqTechResult = $dbAdapter->query($rfqTechStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$this->_view->vendorId = $vendorId;
		$this->_view->rfqRegId = $rfqRegId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqTechResult = $rfqTechResult;
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}

	public function editTechinfoAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
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
		} else if ($request->isPost()) {
			
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			$files = $request->getFiles();
			try {
			
			$regId= $postParams['regId'];
			$isPortal=0;
			//update VM_TechInfoRegister
				$registerUpdate = $sql->update();
				$registerUpdate->table('VM_TechInfoRegister');
				$registerUpdate->set(array(
					'Entrydate' => date('Y-m-d'),
					'VendorId' => $this->bsf->isNullCheck($postParams['VendorId'],'number'),
					'SubmittedOn' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['validFrom'],'date'))),
					//'RFQId' => $postParams['rfqId'],
					'Isportal' => $isPortal,
					'Narration' =>$this->bsf->isNullCheck($postParams['bidComments'],'string')
				 ));
				$registerUpdate->where(array('RegId'=>$regId));			
				$registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
				$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);				

				$techjson = json_decode($postParams['hidTechverification'], true);
				/*For admin --> public/uploads/vendor/rfq/technical/rfqId/vendorId/filename --*/
				foreach($techjson as $techVer){
					$pathname = $postParams['documentPath_'.$techVer];
					if($files['files_'.$techVer]['name']){
						$dir = 'public/uploads/vendor/rfq/technical/'.$postParams['rfqId'].'/'.$postParams['VendorId'].'/';
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
			
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends			
		}
		$regId = $this->params()->fromRoute('regid');
		$rfqRegId=0;
		$select = $sql->select();
		$select->from(array('a'=>'VM_TechInfoRegister'))
			   ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'ValidFrom'=>new Expression("Convert(varchar(10),a.SubmittedOn,105)"),'Narration'),array('RFQNo'))
			   ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo"), $select::JOIN_INNER)
			   ->where(array("a.RegId"=>$regId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVen){
			$this->redirect()->toRoute('ats/default', array('controller' => 'rfq','action' => 'rfq-register'));
		}
		else { $rfqRegId=$resultsVen['RFQId'];}
		
		$selectVendor1 = $sql->select(); 
		$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
					->columns(array("VendorId"), array("VendorName"))
					->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
					->where(array('a.RFQId'=>$rfqRegId));
        $rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		/*load Technical doc query*/
		$selectRFVTech = $sql->select(); 
		$selectRFVTech->from(array("a"=>"VM_RFVTechVerificationTrans"))
			->columns(array("TransId", "RFQTechTransId","TechDocPath"),array("DocumentName","Description","DocumentFormat"))
			->join(array("b"=>"VM_RFQTechVerificationTrans"), "a.RFQTechTransId=b.TransId", array("DocumentName","Description","DocumentFormat"), $selectRFVTech::JOIN_INNER)
			->where(array('a.RegId' => $regId ));				
		$rfqTechStatement = $sql->getSqlStringForSqlObject($selectRFVTech);
		$rfqTechResult = $dbAdapter->query($rfqTechStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->regId = $regId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqTechResult = $rfqTechResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}

	public function responseDetailviewAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
		$sql = new Sql($dbAdapter);
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$postParam = $request->getPost();
				if($postParam['mode'] == 'ValidResponse'){
					$iValid=1;
					$vendorUpdate = $sql->update("VM_RequestFormVendorRegister");
					$vendorUpdate->set(array("Valid"=>$iValid,"Validon"=>date('Y-m-d')))
								->where(array("RegId"=>($postParam['regId'])));
					$statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);					
				}
				else if($postParam['mode'] == 'InValidResponse'){
					$iValid=2;
					$vendorUpdate = $sql->update("VM_RequestFormVendorRegister");
					$vendorUpdate->set(array("Valid"=>$iValid,"Validon"=>date('Y-m-d')))
								->where(array("RegId"=>($postParam['regId'])));
					$statement = $sql->getSqlStringForSqlObject($vendorUpdate);
					$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);					
				}
								
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		}
		else if ($request->isPost()) {
			
		}
		$regId = $this->params()->fromRoute('regid');
		$rfqRegId=0;
		$select = $sql->select();
		$select->from(array('a'=>'VM_RequestFormVendorRegister'))
			   ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'BidComments','AddDocumentName','AddDocumentPath','ResponseStatus'),array('VendorName'))
			   ->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $select::JOIN_INNER)
			   ->where(array("a.RegId"=>$regId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVen){
			$this->redirect()->toRoute('ats/default', array('controller' => 'response','action' => 'response-register'));
		}
		else { $rfqRegId=$resultsVen['RFQId'];}

        $select = $sql->select();
        $select -> from(array("a" => "VM_RFQRegister"));
        $select -> columns(array("QuotType","RFQType"))
            ->where(array("a.RFQRegId"=>$rfqRegId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $type = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if($type['QuotType'] == 'Q'){
            $selectCurRequest = $sql->select();
            $selectCurRequest->from(array("a" => "VM_RFQRegister"));
            $selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,a.Approve,
                            CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,CASE WHEN a.Submittal=1 THEN 'Yes' Else 'No' END as Submittal,CASE WHEN a.BidafterVerification=1 THEN 'Yes' Else 'No' END as BidafterVerification,
                            a.Narration,a.ContactName,a.ContactNo,a.Designation,a.ContactAddress,a.BidInformation,a.SubmittalNarration,
                            Case When a.RFQType=2 Then 'Material' When a.RFQType=3 Then 'Asset' When a.RFQType=4 Then 'Activity' When a.RFQType=5 Then 'IOW'
                             When a.RFQType=6 Then 'Service' When a.RFQType=7 Then 'TurnKey' Else 'Otehr' End As TypeName,
                            multiCC = STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
                            INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
                            where a.RFQRegId = t.RFQId
                            FOR XML PATH (''))
                            , 1, 1, '')")));
            $selectCurRequest->where(array("a.RFQRegId" => $rfqRegId));

        }else{
            $selectCurRequest = $sql->select();
            $selectCurRequest->from(array("a"=>"VM_RFQRegister"));
            $selectCurRequest->columns(array(new Expression("a.RFQRegId,a.RFQNo,Convert(varchar(10),a.RFQDate,105) as RFQDate,Convert(varchar(10),a.FinalBidDate,105) as FinalBidDate,a.Approve,
								CASE WHEN a.TechVerification=1 THEN 'Yes' Else 'No' END as verification,CASE WHEN a.Submittal=1 THEN 'Yes' Else 'No' END as Submittal,CASE WHEN a.BidafterVerification=1 THEN 'Yes' Else 'No' END as BidafterVerification,
								a.Narration,a.ContactName,a.ContactNo,a.Designation,a.ContactAddress,a.BidInformation,a.SubmittalNarration,
								multiEN = STUFF((SELECT ', ' + b1.NameOfWork FROM VM_RFQMultiCCTrans t
								INNER JOIN Proj_TenderEnquiry b1 on t.EnquiryId=b1.TenderEnquiryId
								where a.RFQRegId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')")),array("TypeName"))
                ->join(array("b"=>"Proj_ResourceType"), "a.RFQType=b.TypeId", array("TypeName"), $selectCurRequest::JOIN_LEFT);
            $selectCurRequest->where(array("a.RFQRegId"=>$rfqRegId));
        }
        $statement = $sql->getSqlStringForSqlObject($selectCurRequest);
        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if($type['RFQType'] == '2' || $type['RFQType'] == '3' || $type['RFQType'] == '4' ) {
            $resRFVtransSelect = $sql->select();
            $resRFVtransSelect->from(array("a" => "VM_RFVTrans"))
                ->columns(array(new Expression("a.TransId,a.ResourceId,a.ItemId,a.Quantity,a.Rate,a.Amount,
                     Case When a.ItemId>0 Then d.ItemCode Else b.Code End As Code,Case When a.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,
                     c.UnitName ")))
//				->columns(array("TransId","ResourceId","Quantity","Rate","Amount"),array("Code","ResourceName"), array("UnitName"))
                ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array(), $resRFVtransSelect::JOIN_INNER)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $resRFVtransSelect:: JOIN_LEFT)
                ->join(array('d' => "MMS_Brand"), 'a.ResourceId=d.ResourceId and a.ItemId=d.BrandId', array(), $resRFVtransSelect::JOIN_LEFT)
                ->where(array('a.RegId' => $regId));
            $resStmt = $sql->getSqlStringForSqlObject($resRFVtransSelect);
            $resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        else if($type['RFQType'] == '5') {
            $resRFVtransSelect = $sql->select();
            $resRFVtransSelect->from(array("a" => "VM_RFVTrans"))
                ->columns(array(new Expression("a.TransId,a.ResourceId,a.ItemId,a.Quantity,a.Rate,a.Amount,
                     b.RefSerialNo As Code,b.Specification As ResourceName,
                     c.UnitName ")))
                ->join(array("b" => "Proj_ProjectIowMaster"), "a.ResourceId=b.ProjectIowId", array(), $resRFVtransSelect::JOIN_INNER)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $resRFVtransSelect:: JOIN_LEFT)
                ->where(array('a.RegId' => $regId));
            $resStmt = $sql->getSqlStringForSqlObject($resRFVtransSelect);
            $resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        else if($type['RFQType'] == '6') {
            $resRFVtransSelect = $sql->select();
            $resRFVtransSelect->from(array("a" => "VM_RFVTrans"))
                ->columns(array(new Expression("a.TransId,a.ResourceId,a.ItemId,a.Quantity,a.Rate,a.Amount,
                     b.ServiceCode As Code,b.ServiceName As ResourceName,
                     c.UnitName ")))
                ->join(array("b" => "Proj_ServiceMaster"), "a.ResourceId=b.ServiceId", array(), $resRFVtransSelect::JOIN_INNER)
                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array(), $resRFVtransSelect:: JOIN_LEFT)
                ->where(array('a.RegId' => $regId));
            $resStmt = $sql->getSqlStringForSqlObject($resRFVtransSelect);
            $resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
		/*load Terms query*/
		$TermRFVselect = $sql->select(); 
		$TermRFVselect->from(array("a"=>"VM_RFVTerms"))
			->columns(array(new Expression("a.TermsId,a.ValueFromNet,a.Per,a.Value,a.Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
				b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
			->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $TermRFVselect::JOIN_INNER);
		$TermRFVselect->where(array('a.RegisterId'=>$regId));
		$Termsstatement = $sql->getSqlStringForSqlObject($TermRFVselect);
		$resultsFillTermdet= $dbAdapter->query($Termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*load Submittal query*/
		$selectRFVSubmittal = $sql->select(); 
		$selectRFVSubmittal->from(array("a"=>"VM_RFVSubmittalTrans"))
			->columns(array("TransId", "RFQSubmittalTransId","SubmittalDocPath"),array("SubmittalName"))
			->join(array("b"=>"VM_RFQSubmittalTrans"), "a.RFQSubmittalTransId=b.TransId", array("SubmittalName"), $selectRFVSubmittal::JOIN_INNER)
			->where(array('a.RegId' => $regId ));				
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectRFVSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->regId = $regId;
		$this->_view->type = $type;
		$this->_view->results = $results;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->resResult = $resResult;
		$this->_view->resultsFillTermdet = $resultsFillTermdet;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;
	}

	public function responseDetailsAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
		$sql = new Sql($dbAdapter);
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$postParam = $request->getPost();
				if($postParam['mode'] == 'AcceptedResponse'){
					//Reject
					/*$RFVRejectUpdate = $sql->update("VM_RequestFormVendorRegister");
					$RFVRejectUpdate->set(array("ResponseStatus"=>'R',"ResponseStatusDate"=>date('Y-m-d')))
								->where(array("RFQId"=>$postParam['rfqRegId']));
					$RFVRejectstatement = $sql->getSqlStringForSqlObject($RFVRejectUpdate);
					$dbAdapter->query($RFVRejectstatement, $dbAdapter::QUERY_MODE_EXECUTE);*/
					//Accepted			
					$RFVAcceptUpdate = $sql->update("VM_RequestFormVendorRegister");
					$RFVAcceptUpdate->set(array("ResponseStatus"=>'A',"ResponseStatusDate"=>date('Y-m-d')))
								->where(array("RegId"=>($postParam['regId'])));
					$RFVAcceptstatement = $sql->getSqlStringForSqlObject($RFVAcceptUpdate);
					$dbAdapter->query($RFVAcceptstatement, $dbAdapter::QUERY_MODE_EXECUTE);

					$RFVAcceptMaterilUpdate = $sql->update("VM_RFVTrans");
					$RFVAcceptMaterilUpdate->set(array('Status'=>1))
								->where(array("RegId"=>($postParam['regId'])));
					$RFVAcceptMaterilstatement = $sql->getSqlStringForSqlObject($RFVAcceptMaterilUpdate);
					$dbAdapter->query($RFVAcceptMaterilstatement, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				$result =  "";
				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);
				return $response;
			}
		}
		else if ($request->isPost()) {
			//begin trans try block example starts
			/*$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}*/
			//begin trans try block example ends
		}
			
		$regId = $this->params()->fromRoute('regid');
		$rfqRegId=0;
        $select = $sql->select();
        $select -> from(array("a" => "VM_RFQRegister"))
            ->columns(array("QuotType"))
            ->where(array("a.RFQRegId" => $regId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $res = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		if($res['QuotType'] == 'Q'){
            $select = $sql->select();
            $select->from(array('a'=>'VM_RequestFormVendorRegister'))
                ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'BidComments','AddDocumentName','AddDocumentPath','ResponseStatus','Valid'),array('RFQNo',"BidInformation" ))
                ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo","BidInformation",'RFQDate'=>new Expression("Convert(varchar(10),b.RFQDate,105)"),'multiCC'=>new Expression("STUFF((SELECT ', ' + b1.CostCentreName FROM VM_RFQMultiCCTrans t
								INNER JOIN WF_OperationalCostCentre b1 on t.CostCentreId=b1.CostCentreId
								where a.RFQId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')") ), $select::JOIN_INNER)
                ->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array("VendorName", "RegAddress", "PinCode","LogoPath"), $select::JOIN_LEFT)
                ->join(array('d'=>'WF_CityMaster'), 'c.CityId=d.CityId', array('CityId', 'CityName'), $select:: JOIN_LEFT)
                ->join(array('e'=>'WF_StateMaster'), 'e.StateId=d.StateId', array('StateId', 'StateName'), $select:: JOIN_LEFT)
                ->join(array('f' => 'WF_CountryMaster'), 'f.CountryId=e.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_LEFT)
                ->join(array('g' => 'Vendor_Contact'), 'g.VendorId=a.VendorId', array('ContactNo1', 'Email1'), $select:: JOIN_LEFT)
                ->where(array("a.RegId"=>$regId));
        }else{
            $select = $sql->select();
            $select->from(array('a'=>'VM_RequestFormVendorRegister'))
                ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'BidComments','AddDocumentName','AddDocumentPath','ResponseStatus','Valid'),array('RFQNo',"BidInformation" ))
                ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo","BidInformation",'RFQDate'=>new Expression("Convert(varchar(10),b.RFQDate,105)"),
                               'multiEN'=>new Expression("STUFF((SELECT ', ' + b1.NameofWork FROM VM_RFQMultiCCTrans t
								INNER JOIN Proj_TenderEnquiry b1 on t.EnquiryId=b1.TenderEnquiryId
								where a.RFQId = t.RFQId
								FOR XML PATH (''))
								, 1, 1, '')") ), $select::JOIN_INNER)
                ->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array("VendorName", "RegAddress", "PinCode","LogoPath"), $select::JOIN_LEFT)
                ->join(array('d'=>'WF_CityMaster'), 'c.CityId=d.CityId', array('CityId', 'CityName'), $select:: JOIN_LEFT)
                ->join(array('e'=>'WF_StateMaster'), 'e.StateId=d.StateId', array('StateId', 'StateName'), $select:: JOIN_LEFT)
                ->join(array('f' => 'WF_CountryMaster'), 'f.CountryId=e.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_LEFT)
                ->join(array('g' => 'Vendor_Contact'), 'g.VendorId=a.VendorId', array('ContactNo1', 'Email1'), $select:: JOIN_LEFT)
                ->where(array("a.RegId"=>$regId));
        }
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();




		if(!$resultsVen){
			$this->redirect()->toRoute('ats/default', array('controller' => 'response','action' => 'response-register'));
		}
		else { $rfqRegId=$resultsVen['RFQId'];}
		
		$selectVendor1 = $sql->select(); 
		$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
					->columns(array("VendorId"), array("VendorName"))
					->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
					->where(array('a.RFQId'=>$rfqRegId));
		 $rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$resRFVtransSelect = $sql->select(); 
		$resRFVtransSelect->from(array("a"=>"VM_RFVTrans"))
				->columns(array("TransId","ResourceId","Quantity","Rate","Amount"),array("Code","ResourceName"), array("UnitName"))
				->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resRFVtransSelect::JOIN_INNER)
				->join(array('c'=>'Proj_UOM'), 'b.UnitId=c.UnitId', array("UnitName"), $resRFVtransSelect:: JOIN_LEFT)
				->where(array('a.RegId'=>$regId));
		$resStmt = $sql->getSqlStringForSqlObject($resRFVtransSelect);
		$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		
		
		/*load Terms query*/
		$TermRFVselect = $sql->select(); 
		$TermRFVselect->from(array("a"=>"VM_RFVTerms"))
			->columns(array(new Expression("a.TermsId,a.ValueFromNet,a.Per,a.Value,a.Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
				b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
			->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $TermRFVselect::JOIN_INNER);
		$TermRFVselect->where(array('a.RegisterId'=>$regId));
		$Termsstatement = $sql->getSqlStringForSqlObject($TermRFVselect);
		$resultsFillTermdet= $dbAdapter->query($Termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*load Submittal query*/
		$selectRFVSubmittal = $sql->select(); 
		$selectRFVSubmittal->from(array("a"=>"VM_RFVSubmittalTrans"))
			->columns(array("TransId", "RFQSubmittalTransId","SubmittalDocPath"),array("SubmittalName"))
			->join(array("b"=>"VM_RFQSubmittalTrans"), "a.RFQSubmittalTransId=b.TransId", array("SubmittalName"), $selectRFVSubmittal::JOIN_INNER)
			->where(array('a.RegId' => $regId ));				
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectRFVSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->regId = $regId;
		$this->_view->res = $res;
		$this->_view->rfqRegId = $rfqRegId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->resResult = $resResult;
		$this->_view->resultsFillTermdet = $resultsFillTermdet;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;		
	}

	public function acceptMaterialAction(){
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
		$request = $this->getRequest();
		$response = $this->getResponse();
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
		}
		else if ($request->isPost()){
			//begin trans try block example starts
			$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			$postParams = $request->getPost();
			try {
				$transId = json_decode($postParams['transId'], true);
				$regId = ($postParams['regId']);
				$materialUpdate = $sql->update('VM_RFVTrans');
				$materialUpdate->set(array('Status'=>0))
								->where(array('RegId'=>$regId));
				$materialStmt = $sql->getSqlStringForSqlObject($materialUpdate);
				$dbAdapter->query($materialStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				
				if(count($transId) > 0){
					$materialUpdateYes = $sql->update('VM_RFVTrans');
					$materialUpdateYes->set(array('Status'=>1))
									->where(array('TransId'=>$transId));
					$materialStmtYes = $sql->getSqlStringForSqlObject($materialUpdateYes);
					$dbAdapter->query($materialStmtYes, $dbAdapter::QUERY_MODE_EXECUTE);
					
					$registerUpdate = $sql->update();
					$registerUpdate->table('VM_RequestFormVendorRegister');
					$registerUpdate->set(array(
						'ResponseStatusDate' => date('Y-m-d'),
						'ResponseStatus' => 'P'
					 ));
					$registerUpdate->where(array('RegId'=>$regId));					
					$registerStmt = $sql->getSqlStringForSqlObject($registerUpdate);
					$dbAdapter->query($registerStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				} else {
					$registerUpdate = $sql->update();
					$registerUpdate->table('VM_RequestFormVendorRegister');
					$registerUpdate->set(array(
						'ResponseStatusDate' => date('Y-m-d'),
						'ResponseStatus' => ''
					 ));
					$registerUpdate->where(array('RegId'=>$regId));					
					$registerStmt = $sql->getSqlStringForSqlObject($registerUpdate);
					$dbAdapter->query($registerStmt, $dbAdapter::QUERY_MODE_EXECUTE);
				}
				
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}
			//begin trans try block example ends
		}

		$regId = $this->params()->fromRoute('regid');
		$rfqRegId=0;
		$select = $sql->select();
		$select->from(array('a'=>'VM_RequestFormVendorRegister'))
			   ->columns(array('RegId', 'RFQId','VendorId','Entrydate'=>new Expression("Convert(varchar(10),a.Entrydate,105)"),'BidComments','AddDocumentName','AddDocumentPath','ResponseStatus'),array('RFQNo'))
			   ->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array("RFQNo","BidInformation"), $select::JOIN_INNER)
			   ->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array("VendorName", "RegAddress", "PinCode","LogoPath"), $select::JOIN_LEFT)
				->join(array('d'=>'WF_CityMaster'), 'c.CityId=d.CityId', array('CityId', 'CityName'), $select:: JOIN_LEFT)
			   ->join(array('e'=>'WF_StateMaster'), 'e.StateId=d.StateId', array('StateId', 'StateName'), $select:: JOIN_LEFT)
			   ->join(array('f' => 'WF_CountryMaster'), 'f.CountryId=e.CountryId', array('CountryId', 'CountryName'), $select:: JOIN_LEFT)
			   ->join(array('g' => 'Vendor_Contact'), 'g.VendorId=a.VendorId', array('ContactNo1', 'Email1'), $select:: JOIN_LEFT)
			   ->where(array("a.RegId"=>$regId));
		$statementFound = $sql->getSqlStringForSqlObject($select);
		$resultsVen = $dbAdapter->query($statementFound, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		if(!$resultsVen){
			$this->redirect()->toRoute('ats/default', array('controller' => 'response','action' => 'response-register'));
		}
		else { $rfqRegId=$resultsVen['RFQId'];}
		
		$selectVendor1 = $sql->select(); 
		$selectVendor1->from(array("a"=>"VM_RFQVendorTrans"))
					->columns(array("VendorId"), array("VendorName"))
					->join(array("b"=>new Expression("Vendor_Master")), "a.VendorId=b.VendorId", array("VendorName"), $selectVendor1::JOIN_INNER)
					->where(array('a.RFQId'=>$rfqRegId));
		 $rfqVendorStatement = $sql->getSqlStringForSqlObject($selectVendor1);
		$rfqVendorResult = $dbAdapter->query($rfqVendorStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		/*$selectPartialMaterial = $sql->select();
		$selectPartialMaterial->from(array("a1"=>"VM_RFVTrans"))
				->columns(array("ResourceId"))
				->join(array("a2"=>"VM_RequestFormVendorRegister"), "a1.RegId=a2.RegId", array(), $selectPartialMaterial::JOIN_INNER)
				->where(array('a2.RFQId'=>$rfqRegId,
								'a1.Status'=>1 ));
		//$selectPartialMaterial->where->and->expression('a1.RegId not like ?', $regId);	
		$selectPartialMaterial->where->notEqualTo('a1.RegId', $regId);*/
		
		$resRFVtransSelect = $sql->select(); 
		$resRFVtransSelect->from(array("a"=>"VM_RFVTrans"))
				->columns(array("TransId","ResourceId","Quantity","Rate","Amount", "Status"),array("Code","ResourceName"), array("UnitName"))
				->join(array("b"=>"Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code","ResourceName"), $resRFVtransSelect::JOIN_INNER)
				->join(array('c'=>'Proj_UOM'), 'b.UnitId=c.UnitId', array("UnitName"), $resRFVtransSelect:: JOIN_LEFT)
				->where(array('a.RegId'=>$regId));		
		//$resRFVtransSelect->where->notIn('a.ResourceId',$selectPartialMaterial);
					
		$resStmt = $sql->getSqlStringForSqlObject($resRFVtransSelect);
		$resResult = $dbAdapter->query($resStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();		
		
		/*load Terms query*/
		$TermRFVselect = $sql->select(); 
		$TermRFVselect->from(array("a"=>"VM_RFVTerms"))
			->columns(array(new Expression("a.TermsId,a.ValueFromNet,a.Per,a.Value,a.Period , b.SlNo, b.Title as Terms, b.Per as IsPer , 
				b.Value as IsValue , b.Period as IsPeriod, b.TDate as IsDate, b.TString as IsString, b.SysDefault as IsDef, b.IncludeGross as IGross")))
			->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array(), $TermRFVselect::JOIN_INNER);
		$TermRFVselect->where(array('a.RegisterId'=>$regId));
		$Termsstatement = $sql->getSqlStringForSqlObject($TermRFVselect);
		$resultsFillTermdet= $dbAdapter->query($Termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*load Submittal query*/
		$selectRFVSubmittal = $sql->select(); 
		$selectRFVSubmittal->from(array("a"=>"VM_RFVSubmittalTrans"))
			->columns(array("TransId", "RFQSubmittalTransId","SubmittalDocPath"),array("SubmittalName"))
			->join(array("b"=>"VM_RFQSubmittalTrans"), "a.RFQSubmittalTransId=b.TransId", array("SubmittalName"), $selectRFVSubmittal::JOIN_INNER)
			->where(array('a.RegId' => $regId ));				
		$rfqSubmittalStatement = $sql->getSqlStringForSqlObject($selectRFVSubmittal);
		$rfqSubmittalResult = $dbAdapter->query($rfqSubmittalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		$this->_view->regId = $regId;
		$this->_view->rfqRegId = $rfqRegId;
		$this->_view->resultsVen = $resultsVen;
		$this->_view->resResult = $resResult;
		$this->_view->resultsFillTermdet = $resultsFillTermdet;
		$this->_view->rfqVendorResult = $rfqVendorResult;
		$this->_view->rfqSubmittalResult = $rfqSubmittalResult;		
		//Common function
		$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		return $this->_view;		
	}

	public function rfqVendorsAction(){
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
		$request = $this->getRequest();
		//$vNo = CommonHelper::GetVendorContact(2,$dbAdapter);
		$rfqId = $this->params()->fromRoute('rfqid');
		$selectRFV = $sql->select();
		$selectRFV->from(array("a"=>"VM_RequestFormVendorRegister"));
		$selectRFV->columns(array(new Expression("a.RegId,a.VendorId,Convert(varchar(10),a.Entrydate,105) as Entrydate ,a.BidComments,a.ResponseStatus,a.Valid,b.RFQNo,a.RFQId,
					Convert(varchar(10),b.RFQDate,105) as RFQDate,c.VendorName,c.LogoPath,c.RegAddress")))
					->join(array("b"=>"VM_RFQRegister"), "a.RFQId=b.RFQRegId", array(), $selectRFV::JOIN_INNER)
					->join(array("c"=>"Vendor_Master"), "a.VendorId=c.VendorId", array(), $selectRFV::JOIN_INNER)
					->where(array("a.RFQId"=>$rfqId))
					->order("a.Entrydate Desc");
		$statement = $sql->getSqlStringForSqlObject($selectRFV);
		$this->_view->result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectResCount = $sql->select();
		$selectResCount->from(array("a"=>"VM_RFVTrans"))
					->columns(array(new Expression("b.VendorId,count(a.Status) as countList")))
					->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegId=b.RegId", array(), $selectResCount::JOIN_INNER)
					->where(array("b.RFQId"=>$rfqId));
		$selectResCount->where('a.Rate <> 0')
					->group("b.VendorId");
		$statementResCount = $sql->getSqlStringForSqlObject($selectResCount);
		$this->_view->resultCount = $dbAdapter->query($statementResCount, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selectVendorAmt = $sql->select();
		$selectVendorAmt->from(array("a"=>"VM_RFVTrans"))
					->columns(array(new Expression("b.VendorId,CAST(sum(isnull(a.Quantity*a.Rate,0)) As Decimal(18,3)) as amount")))
					->join(array("b"=>"VM_RequestFormVendorRegister"), "a.RegId=b.RegId", array(), $selectVendorAmt::JOIN_INNER)
					->where(array("b.RFQId"=>$rfqId));
		$selectVendorAmt->where('a.Rate <> 0')
					->group("b.VendorId");
		$statementVendorAmt = $sql->getSqlStringForSqlObject($selectVendorAmt);
		$this->_view->resultAmount = $dbAdapter->query($statementVendorAmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		/*$selectFilledVendor = $sql->select(); 
		$selectFilledVendor->from(array("a"=>"VM_RequestFormVendorRegister"))
				->columns(array("VendorId"))
				->where(array('a.RFQId'=>$rfqId  ));
		
		$selectVendorContact = $sql->select();
		$selectVendorContact->from(array("a"=>"Vendor_Contact"))
				->columns(array(new Expression("a.CPerson1, a.ContactNo1,a.VendorID, ROW_NUMBER() OVER (PARTITION BY a.VendorId ORDER BY a.TransId DESC ) AS RN")))
				->where(array("a.ContactType"=>'1'))
				->where->expression('VendorID IN ?', array($selectFilledVendor));
					
		$selectmaster = $sql->select(); 
		$selectmaster->from(array("g"=>$selectVendorContact))
				->columns(array("CPerson1","ContactNo1","VendorID"))
				->where(array("g.RN"=>'1'));
					
		$statementVendorContact = $sql->getSqlStringForSqlObject($selectmaster);
		$this->_view->resultVenContact = $dbAdapter->query($statementVendorContact, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		*/
		
		if($this->getRequest()->isXmlHttpRequest())	{
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
			/*$connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {
				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}*/
			//begin trans try block example ends
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
		}
        $this->_view->RfqId=$rfqId;
		return $this->_view;
	}
}