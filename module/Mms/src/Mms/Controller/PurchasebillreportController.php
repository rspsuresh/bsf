<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Mms\Controller;

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

class PurchasebillreportController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		$this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function vatAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');	
				$CostCentreId =$this->params()->fromPost('CostCentreId'); 
				if($CostCentreId == ""){
					$CostCentreId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				$toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
			 	$CompanyId= $this->bsf->isNullCheck($postParams['CompanyId'],'string');
					
					if($fromDat == ''){
					$fromDat =  0;
					}
					if($toDat == ''){
						$toDat = 0;
					}

					if($fromDat == 0){
						$fromDate =  0;
					}
					if($toDat == 0){
						$toDate = 0;
					}
					if($fromDat == 0) {
						$fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$fromDate=date('Y-m-d',strtotime($fromDat));
					}
					if($toDat == 0) {
						$toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$toDate =date('Y-m-d',strtotime($toDat));
					}
					
					switch($Type) {
						case 'company':
						
						$CompanySelect = $sql->select();
						$CompanySelect->from(array('A'=>'WF_OperationalCostCentre'))
							->columns(array("CostCentreId", "CostCentreName"))
							->join(array('B' => 'WF_CompanyMaster'), 'A.CompanyId=B.CompanyId', array(), $CompanySelect::JOIN_LEFT);
							if($CompanyId!=0) {
							$CompanySelect->where(array("B.CompanyId" => $CompanyId));
							}
						$CompanyStatement = $sql->getSqlStringForSqlObject($CompanySelect); 
						$CompanyResult = $dbAdapter->query($CompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($CompanyResult));
                        return $response;
                        break;
						
						case 'cost':
						
						$select = $sql -> select();
                        $select -> from(array("A"=>'Proj_QualifierMaster'))
                            ->columns(array(
								'QualifierName'=> new Expression('Distinct QualifierName'),
								))
								->join(array('B' => 'Proj_QualifierTrans'), 'A.QualifierId=B.QualifierId', array(), $select::JOIN_INNER)
							->where(array("A.QualifierTypeId In (3,6,7) And B.QualType='M' And (A.QualifierId IN (select QualifierId from MMS_PVQualTrans ))"));
						$statement = $sql->getSqlStringForSqlObject($select); 	
						$requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						$resid = array();
						
						foreach($requests as $resIds) {
                        array_push( $resid, $resIds['QualifierName']);
						}
						$resIDS = implode(",", $resid);
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_PVRegister'))
							->columns(array(
								'PVRegisterId'=> new Expression('A.PVRegisterId'),
								'PVDate'=> new Expression('Convert(Varchar(10),A.PVDate,103)'),
								'PVNo'=> new Expression('A.PVNo'),
								'BillNo'=> new Expression(' A.BillNo'),
								'BillDate'=>new Expression('Convert(Varchar(10),A.BillDate,103)'),
								'Vendor'=> new Expression('B.VendorName'),
								'CostCentre'=> new Expression('C.CostCentreName'),
								'QualifierName'=> new Expression('F.QualifierName'),
								'Amount'=> new Expression('A.Amount'),
								'GrossAmount'=>new Expression('A.GrossAmount'),
								'BillAmount'=>new Expression('A.BillAmount'),
								'VatAmount'=>new Expression('SUM(E.NetAmt)')))
								
									->join(array('B' => 'Vendor_Master'), 'A.VendorId=B.VendorId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'WF_OperationalCostCentre'), 'A.CostCentreId=C.CostCentreId', array(), $select1::JOIN_INNER)
									->join(array('D' => 'WF_CompanyMaster'),'C.CompanyId=D.CompanyId',array(),$select1::JOIN_INNER)
									->join(array('E' => 'MMS_PVQualTrans'),'A.PVRegisterId=E.PVRegisterId',array(),$select1::JOIN_INNER)
									->join(array('F' => 'Proj_QualifierMaster'),'E.QualifierId=F.QualifierId',array(),$select1::JOIN_INNER)
	
							->where(array("PVDate Between ('$fromDate') And ('$toDate') And  C.CostCentreId IN ($CostCentreId) And F.QualifierTypeId=3"));
						$select1->group(new Expression("A.PVRegisterId,A.PVDate,A.PVNo,A.BillNo,A.BillDate,B.VendorName,C.CostCentreName,A.Amount,A.GrossAmount,A.BillAmount,F.QualifierName"));
						$statement = $sql->getSqlStringForSqlObject($select1);
						
						
						$select2 = $sql->select();
                        $select2->from(array("Main"=>$select1))
							->columns(array('*'));
						$Company = "select * from (".$statement.") as Main PIVOT (SUM(Main.VatAmount) For QualifierName IN ([ ".str_replace(',','],[',$resIDS)."])) As Pvt ";
						$resource = $dbAdapter->query($Company, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$data=json_encode(array('resource'=>$resource,'requests'=>$requests));
						$response = $this->getResponse()->setContent($data);
						return $response;
						
						case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
					}	
			}	
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$companySelect = $sql->select();
            $companySelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $companyStatement = $sql->getSqlStringForSqlObject($companySelect); 
            $this->_view->arr_company = $dbAdapter->query( $companyStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function paymentDetailsAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');	
				$CostCentreId =$this->params()->fromPost('CostCentreId'); 
				if($CostCentreId == ""){
					$CostCentreId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				$toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
			 	$CompanyId= $this->bsf->isNullCheck($postParams['CompanyId'],'string');
			 	$VendorId= $this->bsf->isNullCheck($postParams['VendorName'],'string');
				if($VendorId == ""){
					$VendorId =0;
				}
					
					if($fromDat == ''){
					$fromDat =  0;
					}
					if($toDat == ''){
						$toDat = 0;
					}

					if($fromDat == 0){
						$fromDate =  0;
					}
					if($toDat == 0){
						$toDate = 0;
					}
					if($fromDat == 0) {
						$fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$fromDate=date('Y-m-d',strtotime($fromDat));
					}
					if($toDat == 0) {
						$toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$toDate =date('Y-m-d',strtotime($toDat));
					}
					
					switch($Type) {
						case 'company':
						
						$CompanySelect = $sql->select();
						$CompanySelect->from(array('A'=>'WF_OperationalCostCentre'))
							->columns(array("CostCentreId", "CostCentreName"))
							->join(array('B' => 'WF_CompanyMaster'), 'A.CompanyId=B.CompanyId', array(), $CompanySelect::JOIN_LEFT);
							if($CompanyId!=0) {
							$CompanySelect->where(array("B.CompanyId" => $CompanyId));
							}
						$CompanyStatement = $sql->getSqlStringForSqlObject($CompanySelect); 
						$CompanyResult = $dbAdapter->query($CompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($CompanyResult));
                        return $response;
                        break;
						
						case 'cost':
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_PVRegister'))
							->columns(array(
								'RowNo'=>new Expression('Distinct ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId Asc)'),
								'PVNo'=> new Expression('A.PVNo'),
								'CCPVNo'=> new Expression('A.CCPVNo'),
								'CPVNo'=> new Expression('A.CPVNo'),
								'PVDate'=> new Expression('Convert(Varchar(10),A.PVDate,103)'),
								'BillNo'=>new Expression('A.BillNo'),
								'BillDate'=>new Expression('Convert(Varchar(10),A.BillDate,103)'),
								'PVRegisterId'=>new Expression('A.PVRegisterId'),
								'CostCentre'=>new Expression('D.CostCentreName'),
								'Vendor'=>new Expression('C.VendorName'),
								'BillAmount'=>new Expression('Case When ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId Asc) = 1 Then  A.BillAmount Else 0 End'),
								'PaidAmount'=>new Expression('ISNULL(AD.Amount,0)'),
								'PayAdviceDate'=>new Expression('PD.PayAdviceDate'),
								'ProcessDuration'=>new Expression('DATEDIFF(dd, A.BillDate, PD.PayAdviceDate)'),
								'PayAdviceNo'=>new Expression("ISNULL(PD.PayAdviceNo,'')"),
								'RefNo'=>new Expression("ISNULL(AD.RefNo,'')"),
								'ApproveAmount'=>new Expression('ISNULL(PT.ApproveAmount,0)'),
								'RefDate'=>new Expression('AD.RefDate'),
								'ChequeNo'=>new Expression("ISNULL(AD.ChqNo,'')"),
								'ChequeDate'=>new Expression('AD.ChqDate'),
								'Advance'=>new Expression('isnull(B.Advance,0)'),
								'Balance'=>new Expression('Case When (ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId Asc) = 1) Then (A.BillAmount- Isnull(B.PaidAmount,0) - isnull(B.Advance,0)) Else 0 End'),
								'AgeDays'=>new Expression('DATEDIFF(dd, A.BillDate, ISNULL(PD.PayAdviceDate,Getdate()))')))
								
									->join(array('B' => 'FA_BillRegister'), new Expression("A.PVRegisterId=B.ReferenceId AND B.RefType='PV'"), array(), $select1::JOIN_LEFT)
									->join(array('C' => 'Vendor_Master'), 'A.VendorId=C.VendorId', array(), $select1::JOIN_INNER)
									->join(array('D' => 'WF_OperationalCostCentre'), 'A.CostCentreID=D.CostCentreId', array(), $select1::JOIN_INNER)	
									->join(array('CM' => 'WF_CompanyMaster'), 'D.CompanyId=CM.CompanyId', array(), $select1::JOIN_INNER)	
									->join(array('PT' => 'FA_PayAdviceTrans'), 'B.BillRegisterId=PT.BillRegisterId', array(), $select1::JOIN_LEFT)	
									->join(array('PD' => 'FA_PayAdviceDet'), 'PD.PayAdviceId=PT.PayAdviceId', array(), $select1::JOIN_LEFT)	
									->join(array('AD' => 'FA_Adjustment'), 'AD.BillRegisterId=B.BillRegisterId And PT.EntryId=AD.EntryId AND AD.AdviceId=PT.PayAdviceId', array(), $select1::JOIN_LEFT);
							if($VendorId!=0) {
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.Approve='Y'  And A.VendorId IN ($VendorId)"));
							}
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.Approve='Y'"));
							
						
							
						$statement = $sql->getSqlStringForSqlObject($select1); 
						$paymentdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($paymentdetails));
						return $response;
						break;
						
						case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
					}	
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}	
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$companySelect = $sql->select();
            $companySelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $companyStatement = $sql->getSqlStringForSqlObject($companySelect); 
            $this->_view->arr_company = $dbAdapter->query( $companyStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$Vendor = $sql->select();
            $Vendor->from(array("A"=>'Vendor_Master'))
                   ->columns(array('VendorId', 'VendorName'));
			$statement = $sql->getSqlStringForSqlObject($Vendor);
            $this->_view->VendorName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function detailedRegisterAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');	
				$CostCentreId =$this->params()->fromPost('CostCentreId'); 
				if($CostCentreId == ""){
					$CostCentreId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				$toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
			 	$CompanyId= $this->bsf->isNullCheck($postParams['CompanyId'],'string');
			 	$VendorId= $this->bsf->isNullCheck($postParams['VendorName'],'string');
				if($VendorId == ""){
					$VendorId =0;
				}
					
					if($fromDat == ''){
					$fromDat =  0;
					}
					if($toDat == ''){
						$toDat = 0;
					}

					if($fromDat == 0){
						$fromDate =  0;
					}
					if($toDat == 0){
						$toDate = 0;
					}
					if($fromDat == 0) {
						$fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$fromDate=date('Y-m-d',strtotime($fromDat));
					}
					if($toDat == 0) {
						$toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$toDate =date('Y-m-d',strtotime($toDat));
					}
					
					switch($Type) {
						case 'company':
						
						$CompanySelect = $sql->select();
						$CompanySelect->from(array('A'=>'WF_OperationalCostCentre'))
							->columns(array("CostCentreId", "CostCentreName"))
							->join(array('B' => 'WF_CompanyMaster'), 'A.CompanyId=B.CompanyId', array(), $CompanySelect::JOIN_LEFT);
							if($CompanyId!=0) {
							$CompanySelect->where(array("B.CompanyId" => $CompanyId));
							}
						$CompanyStatement = $sql->getSqlStringForSqlObject($CompanySelect); 
						$CompanyResult = $dbAdapter->query($CompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($CompanyResult));
                        return $response;
                        break;
						
						case 'cost':
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_PVRegister'))
							->columns(array(
								'PVRegisterId'=>new Expression('A.PVRegisterId'),
								'PVGroupId'=> new Expression('H.PVGroupId'),
								'ResourceId'=> new Expression('H.ResourceId'),
								'ItemId'=> new Expression('H.ItemId'),
								'PVDate'=> new Expression('Convert(Varchar(10),A.PVDate,103)'),
								'PVNo'=>new Expression('A.PVNo'),
								'BillDate'=>new Expression('Convert(Varchar(10),A.BillDate,103)'),
								'BillNo'=>new Expression('A.BillNo'),
								'CostCentre'=>new Expression('G.CostCentreName'),
								'Vendor'=>new Expression('F.VendorName'),
								'TINNo'=>new Expression('VS.TINNo'),
								'Code'=>new Expression('Case When H.ItemId>0 Then BR.ItemCode Else C.Code End'),
								'Resource'=>new Expression('Case When H.ItemId>0 Then BR.BrandName Else C.ResourceName End'),
								'Unit'=>new Expression('U.UnitName'),
								'BillQty'=>new Expression("Case When (ROW_NUMBER() OVER(PARTITION by H.PVGroupId Order by H.PVGroupId Asc))=1 Then H.BillQty Else 0 End"),
								'BaseRate'=>new Expression("Case When (ROW_NUMBER() OVER(PARTITION by H.PVGroupId Order by H.PVGroupId Asc))=1 Then Case When (H.TFactor>0 And H.FFactor>0) Then isnull((H.Rate*H.TFactor),0)/nullif(H.FFactor,0) Else H.Rate End Else 0 End"),
								'QualRate'=>new Expression('Case When (ROW_NUMBER() OVER(PARTITION by H.PVGroupId Order by H.PVGroupId Asc))=1 Then Case When (H.TFactor>0 And H.FFactor>0) Then isnull((H.QRate*H.TFactor),0)/nullif(H.FFactor,0) Else H.QRate End Else 0 End'),
								'Amount'=>new Expression('Case When (ROW_NUMBER() OVER(PARTITION by H.PVGroupId Order by H.PVGroupId Asc))=1 Then Case When (H.TFactor>0 And H.FFactor>0) Then (H.BillQty * isnull((H.Rate*H.TFactor),0)/nullif(H.FFactor,0)) Else H.Amount End Else 0 End'),
								'QAmount'=>new Expression("Case When (ROW_NUMBER() OVER(PARTITION by H.PVGroupId Order by H.PVGroupId Asc))=1 Then Case When (H.TFactor>0 And H.FFactor>0) Then (H.BillQty * isnull((H.QRate*H.TFactor),0)/nullif(H.FFactor,0)) Else H.QAmount End Else 0 End"),
								'BillAmount'=>new Expression('Case When (ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId Asc))=1 Then A.BillAmount Else 0 End'),
								'ExtraCharges'=>new Expression("Case When (ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId Asc))=1 Then (Select SUM(PVP.Value) From MMS_PVPaymentTerms PVP Inner Join WF_TermsMaster TM  On PVP.TermsId=TM.TermsId And TM.TermType='S' Where PVP.PVRegisterId=A.PVRegisterId) Else 0 End"),
								'QualifierName'=>new Expression('E.QualifierName'),
								'QualifierId'=>new Expression('E.QualifierId'),
								'Sign'=>new Expression('D.Sign'),
								'ExpPer'=>new Expression('D.ExpPer'),
								'Expression'=>new Expression('D.Expression'),
								'QAmt'=>new Expression('D.NetAmt'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
								'Transport'=>new Expression('Case When (ROW_NUMBER() OVER(PARTITION by A.PVRegisterId Order by A.PVRegisterId Asc))=1 Then A.Transport Else 0 End'),
								'PurchaseType'=>new Expression('PT.PurchaseTypeName'),
								'AccountName'=>new Expression('AM.AccountName'),
								'Reg/UnReg'=>new Expression("Case When LTRIM(RTRIM(STINNo)) <> '' Then 'Registered' Else 'UnRegistered' End"),
								'CSTPurchase'=>new Expression("Case When CSTPurchase=1 Then 'Yes' Else 'No' End"),
								'Approve'=>new Expression("Case When A.Approve='Y' Then 'Yes' Else 'No' End")))
								
									->join(array('H' => 'MMS_PVGroupTrans'),"A.PVRegisterId=H.PVRegisterId", array(), $select1::JOIN_INNER)
									->join(array('C' => 'Proj_Resource'), 'H.ResourceId=C.ResourceId', array(), $select1::JOIN_INNER)
									->join(array('D' => 'MMS_PVQualTrans'), 'H.PVGroupId=D.PVGroupId and H.ResourceId=D.ResourceId And H.ItemId=D.ItemId', array(), $select1::JOIN_LEFT)	
									->join(array('E' => 'Proj_QualifierMaster'), 'D.QualifierId=E.QualifierId', array(), $select1::JOIN_LEFT)	
									->join(array('F' => 'Vendor_Master'), 'A.VendorId=F.VendorId', array(), $select1::JOIN_INNER)	
									->join(array('VS' => 'Vendor_Statutory'), 'VS.VendorID=F.VendorId', array(), $select1::JOIN_LEFT)	
									->join(array('G' => 'WF_OperationalCostCentre'), 'A.CostCentreId=G.CostCentreId', array(), $select1::JOIN_INNER)	
									->join(array('CM' => 'WF_CompanyMaster'), 'G.CompanyId=CM.CompanyId', array(), $select1::JOIN_INNER)	
									->join(array('BR' => 'MMS_Brand'), 'H.ItemId=BR.BrandId And H.ResourceId=BR.ResourceId', array(), $select1::JOIN_LEFT)	
									->join(array('RG' => 'Proj_ResourceGroup'), 'C.ResourceGroupId=RG.ResourceGroupId', array(), $select1::JOIN_INNER)	
									->join(array('PT' => 'MMS_PurchaseType'), 'A.PurchaseTypeId=PT.PurchaseTypeId', array(), $select1::JOIN_LEFT)	
									->join(array('AM' => 'FA_AccountMaster'), 'H.PurchaseTypeId=AM.AccountId', array(), $select1::JOIN_LEFT)	
									->join(array('U' => 'Proj_UOM'), 'H.UnitId=U.UnitId', array(), $select1::JOIN_LEFT);	
							if($VendorId!=0) {
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And F.VendorId IN ($VendorId)"));
							}
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId)"));
							
						
							
						$statement = $sql->getSqlStringForSqlObject($select1); 
						$detailedregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($detailedregister));
						return $response;
						break;
						
						case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
					}	
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}	
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$companySelect = $sql->select();
            $companySelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $companyStatement = $sql->getSqlStringForSqlObject($companySelect); 
            $this->_view->arr_company = $dbAdapter->query( $companyStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$Vendor = $sql->select();
            $Vendor->from(array("A"=>'Vendor_Master'))
                   ->columns(array('VendorId', 'VendorName'));
			$statement = $sql->getSqlStringForSqlObject($Vendor);
            $this->_view->VendorName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function wbsPurchasebillRegisterAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');	
				$CostCentreId =$this->params()->fromPost('CostCentreId'); 
				if($CostCentreId == ""){
					$CostCentreId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				$toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
			 	$CompanyId= $this->bsf->isNullCheck($postParams['CompanyId'],'string');
			 	$VendorId= $this->bsf->isNullCheck($postParams['VendorName'],'string');
				if($VendorId == ""){
					$VendorId =0;
				}
					
					if($fromDat == ''){
					$fromDat =  0;
					}
					if($toDat == ''){
						$toDat = 0;
					}

					if($fromDat == 0){
						$fromDate =  0;
					}
					if($toDat == 0){
						$toDate = 0;
					}
					if($fromDat == 0) {
						$fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$fromDate=date('Y-m-d',strtotime($fromDat));
					}
					if($toDat == 0) {
						$toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$toDate =date('Y-m-d',strtotime($toDat));
					}
					
					switch($Type) {
						case 'company':
						
						$CompanySelect = $sql->select();
						$CompanySelect->from(array('A'=>'WF_OperationalCostCentre'))
							->columns(array("CostCentreId", "CostCentreName"))
							->join(array('B' => 'WF_CompanyMaster'), 'A.CompanyId=B.CompanyId', array(), $CompanySelect::JOIN_LEFT);
							if($CompanyId!=0) {
							$CompanySelect->where(array("B.CompanyId" => $CompanyId));
							}
						$CompanyStatement = $sql->getSqlStringForSqlObject($CompanySelect); 
						$CompanyResult = $dbAdapter->query($CompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($CompanyResult));
                        return $response;
                        break;
						
						case 'cost':
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_PVRegister'))
							->columns(array(
								'PVRegisterId'=>new Expression('A.PVRegisterId'),
								'ResourceId'=> new Expression('B.ResourceId'),
								'ItemId'=> new Expression('B.ItemId'),
								'PVNo'=> new Expression('A.PVNo'),
								'CCPVNo'=> new Expression('A.CCPVNo'),
								'CPVNo'=>new Expression('A.CPVNo'),
								'PVDate'=>new Expression('Convert(Varchar(10),A.PVDate,103)'),
								'CostCentre'=>new Expression('K.CostCentreName'),
								'Vendor'=>new Expression('E.VendorName'),
								'Code'=>new Expression('Case When (B.ItemId>0) Then F.ItemCode Else D.Code End'),
								'Resource'=>new Expression('Case When (B.ItemId>0) Then F.BrandName Else D.ResourceName End'),
								'Unit'=>new Expression('L.UnitName'),								
								'AnalysisId'=>new Expression('C.AnalysisId'),								
								'PLevel'=>new Expression('G.ParentText'),								
								'WbsName'=>new Expression('G.WbsName'),								
								'EstQty'=>new Expression('CAST(PD.Qty As Decimal(18,3))'),								
								'Qty'=>new Expression('C.BillQty'),								
								'Rate'=>new Expression('Case When (B.FFactor>0 And B.TFactor>0) Then isnull((B.QRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.QRate End'),								
								'Amount'=>new Expression("Case When (B.FFactor>0 And B.TFactor>0) Then (C.BillQty*isnull((B.QRate*B.TFactor),0)/nullif(B.FFactor,0)) Else (C.BillQty*B.QRate) End")))
								
									->join(array('B' => 'MMS_PVGroupTrans'),"A.PVRegisterId=B.PVRegisterId", array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_PVAnalTrans'), 'B.PVGroupId=C.PvGroupID', array(), $select1::JOIN_INNER)
									->join(array('D' => 'Proj_Resource'), 'B.ResourceId=D.ResourceId', array(), $select1::JOIN_INNER)	
									->join(array('PD' => 'Proj_ProjectDetails'), 'A.CostCentreId=PD.ProjectId And B.ResourceId=PD.ResourceId', array(), $select1::JOIN_LEFT)	
									->join(array('E' => 'Vendor_Master'), 'A.VendorId=E.VendorId', array(), $select1::JOIN_INNER)	
									->join(array('F' => 'MMS_Brand'), 'B.ResourceId=F.ResourceId And B.ItemId=F.BrandId', array(), $select1::JOIN_LEFT)	
									->join(array('G' => 'Proj_WbsMaster'), 'C.AnalysisId=G.WbsId', array(), $select1::JOIN_LEFT)	
									->join(array('K' => 'WF_OperationalCostCentre'), 'A.CostCentreId=K.CostCentreId', array(), $select1::JOIN_INNER)	
									->join(array('L' => 'Proj_UOM'), 'B.UnitId=L.UnitId', array(), $select1::JOIN_LEFT);	
							if($VendorId!=0) {
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.ThruPO='Y' And A.VendorId IN ($VendorId)"));
							}
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.ThruPO='Y'"));
						
						$select2 = $sql->select();
						$select2->from(array('A' => 'MMS_PVRegister'))
							->columns(array(
								'PVRegisterId'=>new Expression('A.PVRegisterId'),
								'ResourceId'=> new Expression('B.ResourceId'),
								'ItemId'=> new Expression('B.ItemId'),
								'PVNo'=> new Expression('A.PVNo'),
								'CCPVNo'=> new Expression('A.CCPVNo'),
								'CPVNo'=>new Expression('A.CPVNo'),
								'PVDate'=>new Expression('Convert(Varchar(10),A.PVDate,103)'),
								'CostCentre'=>new Expression('K.CostCentreName'),
								'Vendor'=>new Expression('E.VendorName'),
								'Code'=>new Expression('Case When (B.ItemId>0) Then F.ItemCode Else D.Code End'),
								'Resource'=>new Expression('Case When (B.ItemId>0) Then F.BrandName Else D.ResourceName End'),
								'Unit'=>new Expression('L.UnitName'),								
								'EstQty'=>new Expression('PD.Qty'),								
								'AnalysisId'=>new Expression('C.AnalysisId'),								
								'PLevel'=>new Expression('W.ParentText'),								
								'WbsName'=>new Expression('W.WbsName'),											
								'Qty'=>new Expression('C.BillQty'),								
								'Rate'=>new Expression('Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((B.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.QRate End'),								
								'Amount'=>new Expression("Case When (PG.FFactor>0 And PG.TFactor>0) Then (C.BillQty*isnull((B.QRate*PG.TFactor),0)/nullif(PG.FFactor,0)) Else (C.BillQty*B.QRate) End")))
								
									->join(array('B' => 'MMS_PVTrans'),"A.PVRegisterId=B.PVRegisterId", array(), $select2::JOIN_INNER)
									->join(array('PG' => 'MMS_PVGroupTrans'), 'B.PVGroupId=PG.PVGroupId And B.PVRegisterId=PG.PVRegisterId', array(), $select2::JOIN_INNER)
									->join(array('C' => 'MMS_PVATrans'), 'B.PVTransId=C.PVTransId', array(), $select2::JOIN_INNER)	
									->join(array('D' => 'Proj_Resource'), 'B.ResourceId=D.ResourceId', array(), $select2::JOIN_INNER)	
									->join(array('PD' => 'Proj_ProjectDetails'), 'A.CostCentreId=PD.ProjectId And B.ResourceId=PD.ResourceId', array(), $select2::JOIN_LEFT)	
									->join(array('E' => 'Vendor_Master'), 'A.VendorId=E.VendorId', array(), $select2::JOIN_INNER)	
									->join(array('F' => 'MMS_Brand'), 'B.ResourceId=F.ResourceId And B.ItemId=F.BrandId', array(), $select2::JOIN_LEFT)	
									->join(array('W' => 'Proj_WbsMaster'), 'C.AnalysisId=W.WbsId', array(), $select2::JOIN_LEFT)	
									->join(array('K' => 'WF_OperationalCostCentre'), 'A.CostCentreId=K.CostCentreId', array(), $select2::JOIN_INNER)	
									->join(array('L' => 'Proj_UOM'), 'PG.UnitId=L.UnitId', array(), $select2::JOIN_LEFT);	
							if($VendorId!=0) {
							$select2->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.ThruDC='Y' And A.VendorId IN ($VendorId)"));
							}
							$select2->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.ThruDC='Y'"));
						
						
							
						$select3 = $sql->select();
						$select3->from(array('G' =>$select2))
							->columns(array(
								'PVRegisterId'=> new Expression('G.PVRegisterId'),
								'ResourceId'=> new Expression('G.ResourceId'),
								'ItemId'=> new Expression('G.ItemId'),
								'PVNo'=> new Expression('G.PVNo'),
								'CCPVNo'=>new Expression('G.CCPVNo'),
								'CPVNo'=> new Expression('G.CPVNo'),
								'PVDate'=> new Expression('Convert(Varchar(10),G.PVDate,103)'),
								'CostCentre'=> new Expression('G.CostCentre'),
								'Vendor'=> new Expression('G.Vendor'),
								'Code'=>new Expression('G.Code'),
								'Resource'=>new Expression('G.Resource'),
								'Unit'=>new Expression('G.Unit'),
								'AnalysisId'=>new Expression('G.AnalysisId'),
								'PLevel'=>new Expression('G.PLevel'),
								'WbsName'=>new Expression('G.WbsName'),
								'EstQty'=>new Expression('CAST(G.EstQty As Decimal(18,3))'),
								'Qty'=>new Expression('SUM(G.Qty)'),
								'Rate'=>new Expression('SUM(G.Rate)/Count(G.Rate)'),
								'Amount'=>new Expression('SUM(G.Amount)')));
						$select3->group(new Expression("G.PVRegisterId,G.ResourceId,G.ItemId,G.PVNo,G.EstQty,G.CCPVNo,G.CPVNo,G.PVDate,G.CostCentre,G.Vendor,G.Code,G.Resource,G.Unit,G.AnalysisId,G.PLevel,G.WbsName"));
						$select3->combine($select1,'Union ALL');
						
						$statement = $sql->getSqlStringForSqlObject($select3);
						$purchasebillregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($purchasebillregister));
						return $response;
						break;
						
						case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
					}	
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}	
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$companySelect = $sql->select();
            $companySelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $companyStatement = $sql->getSqlStringForSqlObject($companySelect); 
            $this->_view->arr_company = $dbAdapter->query( $companyStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$Vendor = $sql->select();
            $Vendor->from(array("A"=>'Vendor_Master'))
                   ->columns(array('VendorId', 'VendorName'));
			$statement = $sql->getSqlStringForSqlObject($Vendor);
            $this->_view->VendorName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function purchasebillResourceAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');	
				$CostCentreId =$this->params()->fromPost('CostCentreId'); 
				if($CostCentreId == ""){
					$CostCentreId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				$toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
			 	$CompanyId= $this->bsf->isNullCheck($postParams['CompanyId'],'string');
			 	$VendorId= $this->bsf->isNullCheck($postParams['VendorName'],'string');
				if($VendorId == ""){
					$VendorId =0;
				}
					
					if($fromDat == ''){
					$fromDat =  0;
					}
					if($toDat == ''){
						$toDat = 0;
					}

					if($fromDat == 0){
						$fromDate =  0;
					}
					if($toDat == 0){
						$toDate = 0;
					}
					if($fromDat == 0) {
						$fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$fromDate=date('Y-m-d',strtotime($fromDat));
					}
					if($toDat == 0) {
						$toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$toDate =date('Y-m-d',strtotime($toDat));
					}
					
					switch($Type) {
						case 'company':
						
						$CompanySelect = $sql->select();
						$CompanySelect->from(array('A'=>'WF_OperationalCostCentre'))
							->columns(array("CostCentreId", "CostCentreName"))
							->join(array('B' => 'WF_CompanyMaster'), 'A.CompanyId=B.CompanyId', array(), $CompanySelect::JOIN_LEFT);
							if($CompanyId!=0) {
							$CompanySelect->where(array("B.CompanyId" => $CompanyId));
							}
						$CompanyStatement = $sql->getSqlStringForSqlObject($CompanySelect); 
						$CompanyResult = $dbAdapter->query($CompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($CompanyResult));
                        return $response;
                        break;
						
						case 'cost':
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_PVRegister'))
							->columns(array(
								'ResourceGroup'=>new Expression('H.ResourceGroupName'),
								'Code'=> new Expression('Case When B.ItemId>0 Then D.ItemCode Else C.Code End'),
								'Resource'=> new Expression('Case When B.ItemId>0 Then D.BrandName Else C.ResourceName End'),
								'Unit'=> new Expression('E.UnitName'),
								'Quantity'=> new Expression('Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.BillQty*B.TFactor),0)/nullif(B.FFactor,0) Else B.BillQty End'),
								'Amount'=>new Expression('Case When (B.TFactor>0 And B.FFactor>0) Then (isnull((B.BillQty*B.TFactor),0)/nullif(B.FFactor,0)*B.QRate) Else B.QAmount End')))
								
									->join(array('B' => 'MMS_PVGroupTrans'),"A.PVRegisterId=B.PVRegisterId", array(), $select1::JOIN_INNER)
									->join(array('C' => 'Proj_Resource'), 'B.ResourceId=C.ResourceId', array(), $select1::JOIN_INNER)	
									->join(array('D' => 'MMS_Brand'), 'B.ResourceId=D.ResourceId And B.ItemId=D.BrandId', array(), $select1::JOIN_LEFT)	
									->join(array('E' => 'Proj_UOM'), 'B.UnitId=E.UnitID', array(), $select1::JOIN_LEFT)	
									->join(array('H' => 'Proj_ResourceGroup'), 'C.ResourceGroupId=H.ResourceGroupID', array(), $select1::JOIN_INNER);	
							if($VendorId!=0) {
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.VendorId IN ($VendorId)"));
							}
							$select1->where(array("A.PVDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId)"));
						
							
						$select2 = $sql->select();
						$select2->from(array('G' =>$select1))
							->columns(array(
								'ResourceGroup'=>new Expression('G.ResourceGroup'),
								'Code'=> new Expression('G.Code'),
								'Resource'=> new Expression('G.Resource'),
								'Unit'=> new Expression('G.Unit'),
								'Quantity'=> new Expression('CAST(isnull(SUM(G.Quantity),0) As Decimal(18,6))'),
								'Rate'=>new Expression('Cast((ISNULL(SUM(G.Amount),0)/nullif(SUM(G.Quantity),0)) As Decimal(18,2))'),
								'Amount'=>new Expression('CAST((isnull(SUM(G.Quantity),0) * (isnull(SUM(G.Amount),0)/nullif(SUM(G.Quantity),0))) As Decimal(18,2))')));
									
						$select2->group(new Expression("G.ResourceGroup,G.Code,G.Resource,G.Unit"));
						
						$statement = $sql->getSqlStringForSqlObject($select2);
						$purchasebillresource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($purchasebillresource));
						return $response;
						break;
						
						case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
					}	
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}	
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$companySelect = $sql->select();
            $companySelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $companyStatement = $sql->getSqlStringForSqlObject($companySelect); 
            $this->_view->arr_company = $dbAdapter->query( $companyStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$Vendor = $sql->select();
            $Vendor->from(array("A"=>'Vendor_Master'))
                   ->columns(array('VendorId', 'VendorName'));
			$statement = $sql->getSqlStringForSqlObject($Vendor);
            $this->_view->VendorName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function purchasebillHistoryAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');	
				$CostCentreId =$this->params()->fromPost('CostCentreId'); 
				if($CostCentreId == ""){
					$CostCentreId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				$toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
			 	$CompanyId= $this->bsf->isNullCheck($postParams['CompanyId'],'string');
			 	$VendorId= $this->bsf->isNullCheck($postParams['VendorName'],'string');
				if($VendorId == ""){
					$VendorId =0;
				}
					
					if($fromDat == ''){
					$fromDat =  0;
					}
					if($toDat == ''){
						$toDat = 0;
					}

					if($fromDat == 0){
						$fromDate =  0;
					}
					if($toDat == 0){
						$toDate = 0;
					}
					if($fromDat == 0) {
						$fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$fromDate=date('Y-m-d',strtotime($fromDat));
					}
					if($toDat == 0) {
						$toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$toDate =date('Y-m-d',strtotime($toDat));
					}
					
					switch($Type) {
						case 'company':
						
						$CompanySelect = $sql->select();
						$CompanySelect->from(array('A'=>'WF_OperationalCostCentre'))
							->columns(array("CostCentreId", "CostCentreName"))
							->join(array('B' => 'WF_CompanyMaster'), 'A.CompanyId=B.CompanyId', array(), $CompanySelect::JOIN_LEFT);
							if($CompanyId!=0) {
							$CompanySelect->where(array("B.CompanyId" => $CompanyId));
							}
						$CompanyStatement = $sql->getSqlStringForSqlObject($CompanySelect); 
						$CompanyResult = $dbAdapter->query($CompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						$this->_view->setTerminal(true);
                        $response->setContent(json_encode($CompanyResult));
                        return $response;
                        break;
						
						case 'cost':
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'RegisterId'=>new Expression('A.DCRegisterId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'CostCentreId'=> new Expression('E.CostCentreId'),
								'MINDate'=> new Expression('CONVERT(Varchar(10),E.DCDate,103)'),
								'MINNo'=> new Expression('E.DCNo'),
								'SiteMINDate'=> new Expression('Convert(Varchar(10),E.SiteDCDate,103)'),
								'SiteMINNo'=> new Expression('E.SiteDCNo'),
								'CostCentre'=> new Expression('CC.CostCentreName'),
								'Supplier'=> new Expression('V.VendorName'),
								'Code'=> new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else R.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else R.ResourceName End'),
								'Unit'=> new Expression('U.UnitName'),
								'MINQty'=> new Expression('CAST(A.AcceptQty As Decimal(18,6))'),
								'PVDate'=> new Expression("''"),
								'PVNo'=> new Expression("''"),
								'BillDate'=> new Expression("''"),
								'BillNo'=> new Expression("''"),
								'BillQty'=> new Expression('CAST(0 As Decimal(18,6))'),
								'Rate'=> new Expression('CAST(0 As Decimal(18,3))'),
								'QRate'=> new Expression('CAST(0 As Decimal(18,3))'),
								'Amount'=> new Expression('CAST(0 As Decimal(18,3))'),
								'QAmount'=> new Expression('CAST(0 As Decimal(18,3))'),
								'Type'=> new Expression("'DC'"),
								'BillAmount'=>new Expression('CAST(0 As Decimal(18,3))')))
								
									->join(array('DG' => 'MMS_DCGroupTrans'),"A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId", array(), $select1::JOIN_INNER)
									->join(array('R' => 'Proj_Resource'), 'R.ResourceID=A.ResourceId', array(), $select1::JOIN_INNER)	
									->join(array('E' => 'MMS_DCRegister'),new Expression('E.DcRegisterId=A.DCRegisterId And E.DcOrCSM=1'), array(), $select1::JOIN_INNER)	
									->join(array('CC' => 'WF_OperationalCostCentre'), 'E.CostCentreId=CC.CostCentreId', array(), $select1::JOIN_INNER)	
									->join(array('V' => 'Vendor_Master'), 'V.VendorId=E.VendorId', array(), $select1::JOIN_INNER)	
									->join(array('BR' => 'MMS_Brand'), 'A.ResourceId=BR.ResourceId And A.ItemId=BR.BrandId', array(), $select1::JOIN_LEFT)	
									->join(array('CM' => 'WF_CompanyMaster'), 'CC.CompanyId=CM.CompanyId', array(), $select1::JOIN_INNER)	
									->join(array('RG' => 'Proj_ResourceGroup'), 'R.ResourceGroupId=RG.ResourceGroupId', array(), $select1::JOIN_INNER)	
									->join(array('U' => 'Proj_UOM'), 'DG.UnitId=U.UnitID', array(), $select1::JOIN_LEFT);	
							if($VendorId!=0) {
							$select1->where(array(" A.DCTransId NOT IN (Select DCTransId From MMS_PVTrans ) And E.DCDate Between ('$fromDate') And ('$toDate') And E.CostCentreId IN ($CostCentreId) And E.VendorId IN ($VendorId)"));
							}
							$select1->where(array("A.DCTransId NOT IN (Select DCTransId From MMS_PVTrans ) And E.DCDate Between ('$fromDate') And ('$toDate') And E.CostCentreId IN ($CostCentreId)"));
						
							
						$select2 = $sql->select();
						$select2->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'RegisterId'=>new Expression('A.DCRegisterId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'CostCentreId'=> new Expression('E.CostCentreId'),
								'MINDate'=> new Expression('CONVERT(Varchar(10),E.DCDate,103)'),
								'MINNo'=> new Expression('E.DCNo'),
								'SiteMINDate'=> new Expression('Convert(Varchar(10),E.SiteDCDate,103)'),
								'SiteMINNo'=> new Expression('E.SiteDCNo'),
								'CostCentre'=> new Expression('CC.CostCentreName'),
								'Supplier'=> new Expression('V.VendorName'),
								'Code'=> new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else R.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else R.ResourceName End'),
								'Unit'=> new Expression('U.UnitName'),
								'MINQty'=> new Expression('CAST(A.AcceptQty As Decimal(18,6))'),
								'PVDate'=> new Expression('Convert(Varchar(10), D.PVDate,103)'),
								'PVNo'=> new Expression('D.PVNo'),
								'BillDate'=> new Expression('CONVERT(Varchar(10),D.BillDate,103)'),
								'BillNo'=> new Expression('D.BillNo'),
								'BillQty'=> new Expression('CAST(C.BillQty As Decimal(18,6))'),
								'Rate'=> new Expression(' Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((C.Rate*PG.TFactor),0)/nullif(PG.FFactor,0) Else C.Rate End'),
								'QRate'=> new Expression('Case When (PG.FFactor>0 And PG.TFactor>0) Then  isnull((C.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else C.QRate End'),
								'Amount'=> new Expression('Case When (PG.FFactor>0 And PG.TFactor>0) Then (C.BillQty*isnull((C.Rate*PG.TFactor),0)/nullif(PG.FFactor,0)) Else C.Amount End'),
								'QAmount'=> new Expression('Case When (PG.FFactor>0 And PG.TFactor>0) Then (C.BillQty*isnull((C.QRate*PG.TFactor),0)/nullif(PG.FFactor,0)) Else C.QAmount End'),
								'Type'=> new Expression("'DC'"),
								'BillAmount'=>new Expression('D.BillAmount')))
								
									->join(array('B' => 'MMS_IPDTrans'),"A.DCTransId=B.DCTransId", array(), $select2::JOIN_INNER)
									->join(array('C' => 'MMS_PVTrans'), 'B.PVTransId=C.PVTransId', array(), $select2::JOIN_INNER)	
									->join(array('PG' => 'MMS_PVGroupTrans'),'C.PVGroupId=PG.PVGroupId And C.PVRegisterId=PG.PVRegisterId', array(), $select2::JOIN_INNER)	
									->join(array('D' => 'MMS_PVRegister'), 'C.PVRegisterId=D.PVRegisterId', array(), $select2::JOIN_INNER)	
									->join(array('R' => 'Proj_Resource'), 'R.ResourceID=A.ResourceId', array(), $select2::JOIN_INNER)	
									->join(array('E' => 'MMS_DCRegister'),new Expression('E.DCRegisterId=A.DCRegisterId And E.DcOrCSM=1'), array(), $select2::JOIN_INNER)	
									->join(array('CC' => 'WF_OperationalCostCentre'), 'E.CostCentreId=CC.CostCentreId', array(), $select2::JOIN_INNER)	
									->join(array('V' => 'Vendor_Master'), 'V.VendorId=E.VendorId', array(), $select2::JOIN_INNER)	
									->join(array('BR' => 'MMS_Brand'), 'A.ResourceId=BR.ResourceId And A.ItemId=BR.BrandId', array(), $select2::JOIN_LEFT)	
									->join(array('CM' => 'WF_CompanyMaster'), 'CC.CompanyId=CM.CompanyId', array(), $select2::JOIN_INNER)	
									->join(array('RG' => 'Proj_ResourceGroup'), 'R.ResourceGroupId=RG.ResourceGroupId', array(), $select2::JOIN_INNER)	
									->join(array('U' => 'Proj_UOM'), 'PG.UnitId=U.UnitId', array(), $select2::JOIN_LEFT);	
							if($VendorId!=0) {
							$select2->where(array("E.DCDate Between ('$fromDate') And ('$toDate') And E.CostCentreId IN ($CostCentreId) And E.VendorId IN ($VendorId)"));
							}
							$select2->where(array("E.DCDate Between ('$fromDate') And ('$toDate') And E.CostCentreId IN ($CostCentreId)"));
							$select2->combine($select1,'Union ALL');
							
						$select3 = $sql->select();
						$select3->from(array('A' => 'MMS_PVGroupTrans'))
							->columns(array(
								'RegisterId'=>new Expression('A.PVRegisterId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'CostCentreId'=> new Expression('E.CostCentreId'),
								'MINDate'=> new Expression('CONVERT(Varchar(10),B.PVDate,103)'),
								'MINNo'=> new Expression('B.PVNo'),
								'SiteMINDate'=> new Expression("''"),
								'SiteMINNo'=> new Expression("''"),
								'CostCentre'=> new Expression('E.CostCentreName'),
								'Supplier'=> new Expression('F.VendorName'),
								'Code'=> new Expression('Case When A.ItemId>0 Then D.ItemCode Else C.Code End'),
								'Resource'=> new Expression('Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End'),
								'Unit'=> new Expression('I.UnitName'),
								'MINQty'=> new Expression('CAST(A.BillQty As Decimal(18,6))'),
								'PVDate'=> new Expression('CONVERT(Varchar(10),B.PVDate,103)'),
								'PVNo'=> new Expression('B.PVNo'),
								'BillDate'=> new Expression('CONVERT(Varchar(10),B.BillDate,103)'),
								'BillNo'=> new Expression('B.BillNo'),
								'BillQty'=> new Expression('CAST(A.BillQty As Decimal(18,6))'),
								'Rate'=> new Expression('Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.Rate*A.TFactor),0)/nullif(A.FFactor,0) Else A.Rate End'),
								'QRate'=> new Expression('Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End'),
								'Amount'=> new Expression('Case When (A.FFactor>0 And A.TFactor>0) Then (A.BillQty*isnull((A.Rate*A.TFactor),0)/nullif(A.FFactor,0)) Else A.Amount End'),
								'QAmount'=> new Expression('Case When (A.FFactor>0 And A.TFactor>0) Then (A.BillQty*isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0)) Else A.QAmount End'),
								'Type'=> new Expression("'PV'"),
								'BillAmount'=>new Expression('B.BillAmount')))
								
									->join(array('B' => 'MMS_PVRegister'),"A.PVRegisterId=B.PVRegisterId", array(), $select3::JOIN_INNER)
									->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceID', array(), $select3::JOIN_INNER)	
									->join(array('D' => 'MMS_Brand'),'A.ResourceId=C.ResourceID And A.ItemId=D.BrandId', array(), $select3::JOIN_LEFT)	
									->join(array('E' => 'WF_OperationalCostCentre'), 'B.CostCentreId=E.CostCentreId', array(), $select3::JOIN_INNER)	
									->join(array('F' => 'Vendor_Master'), 'B.VendorId=F.VendorId', array(), $select3::JOIN_INNER)	
									->join(array('G' => 'WF_CompanyMaster'),'E.CompanyId=G.CompanyId', array(), $select3::JOIN_INNER)	
									->join(array('H' => 'Proj_ResourceGroup'), 'C.ResourceGroupId=H.ResourceGroupID', array(), $select3::JOIN_INNER)	
									->join(array('I' => 'Proj_UOM'), 'A.UnitId=I.UnitID', array(), $select3::JOIN_LEFT);	
							if($VendorId!=0) {
							$select3->where(array("B.PVDate Between ('$fromDate') And ('$toDate') And B.ThruPO='Y' And B.CostCentreId IN ($CostCentreId) And B.VendorId IN ($VendorId)"));
							}
							$select3->where(array("B.PVDate Between ('$fromDate') And ('$toDate') And B.ThruPO='Y' And B.CostCentreId IN ($CostCentreId)"));
							$select3->combine($select2,'Union ALL');
							
						$select4 = $sql->select();
						$select4->from(array('G' =>$select3))
							->columns(array(
								'RegisterId'=> new Expression('G.RegisterId'),
								'ResourceId'=> new Expression('G.ResourceId'),
								'ItemId'=> new Expression('G.ItemId'),
								'CostCentreId'=> new Expression('G.CostCentreId'),
								'MINDate'=>new Expression('CONVERT(Varchar(10),G.MINDate,103)'),
								'MINNo'=> new Expression('G.MINNo'),
								'SiteMINDate'=> new Expression('G.SiteMINDate'),
								'SiteMINNo'=> new Expression('G.SiteMINNo'),
								'CostCentre'=> new Expression('G.CostCentre'),
								'Supplier'=>new Expression('G.Supplier'),
								'Code'=>new Expression('G.Code'),
								'Resource'=>new Expression('G.Resource'),
								'Unit'=>new Expression('G.Unit'),
								'MINQty'=>new Expression('G.MINQty'),
								'PVDate'=>new Expression('G.PVDate'),
								'PVNo'=>new Expression('G.PVNo'),
								'BillNo'=>new Expression('G.BillNo'),
								'BillDate'=>new Expression('G.BillDate'),
								'BillQty'=>new Expression('G.BillQty'),
								'Rate'=>new Expression('G.Rate'),
								'QRate'=>new Expression('G.QRate'),
								'Amount'=>new Expression('G.Amount'),
								'QAmount'=>new Expression('G.QAmount'),
								'BillAmount'=>new Expression('G.BillAmount'),
								'Type'=>new Expression('G.Type')))
							->order("G.MINDate Desc");
							
						$statement= $sql->getSqlStringForSqlObject($select4); 
						$purchasebillhistory = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($purchasebillhistory));
						return $response;
						break;
						
						case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
					}	
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}	
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$companySelect = $sql->select();
            $companySelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $companyStatement = $sql->getSqlStringForSqlObject($companySelect); 
            $this->_view->arr_company = $dbAdapter->query( $companyStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			
			$Vendor = $sql->select();
            $Vendor->from(array("A"=>'Vendor_Master'))
                   ->columns(array('VendorId', 'VendorName'));
			$statement = $sql->getSqlStringForSqlObject($Vendor);
            $this->_view->VendorName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function monthlyPurchaseDetailsAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();

			}
		} else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $CostCentreId = $this->params()->fromRoute('CostCentre');
            $this->_view->CostCentreId=$CostCentreId;
            $this->_view->fromdat = $this->params()->fromRoute('fromDate');
            $this->_view->todat = $this->params()->fromRoute('toDate');
            $fromDate=date('Y-m-d', strtotime(Date('d-m-Y')));
            $toDate=date('Y-m-d', strtotime(Date('d-m-Y')));


            if($this->_view->fromdat!=""){
                $fromDate= date('Y-m-d', strtotime($this->_view->fromdat));
            }
            if($this->_view->todat!=""){
                $toDate= date('Y-m-d', strtotime($this->_view->todat));
            }
            $datedet = array();
            if($fromDate<=$toDate) {
                $select = $sql->select();
                $select->from("")
                    ->columns(array('Monthcount'=> new Expression("DATEDIFF(MONTH,'$fromDate','$toDate') + 1")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $mothCOuntList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $cont=$mothCOuntList[0]['Monthcount'];

                for($i=0; $i<$cont; $i++) {
                    $tDate = date('Y-m-d', strtotime("+". $i ." month", strtotime($fromDate)));
                    $tMonth = date('M', strtotime($tDate));
                    $tMonthNo = date('m', strtotime($tDate));
                    $tYear = date('Y', strtotime($tDate));

                    $monthText = $tMonth . ', '. $tYear;

                    $dumArr=array();
                    $dumArr = array(
                        'Year' => $tYear,
                        'Month' => $tMonthNo,
                        'MonthDesc' => $tMonth
                    );
                    $datedet[] =$dumArr;
                }
            }
            if($CostCentreId == ""){
                $CostCentreId =0;
            }           $E=0;
            $dumArr = array();
            $select1 = $sql->select();
            $select1->from(array('A' =>'MMS_PVRegister'))
                ->columns(array(
                    'VendorId'=> new Expression('distinct B.VendorId'),
                    'VendorName'=> new Expression('B.VendorName')))
                ->join(array('B' => 'Vendor_Master'),"A.VendorId=B.VendorId", array(), $select1::JOIN_INNER)
                ->where("A.CostCentreId IN (".$CostCentreId.")");

            $select2 = $sql->select();
            $select2->from(array('G' =>$select1))
                ->columns(array(
                    'SlNo'=> new Expression('ROW_NUMBER() OVER (ORDER BY G.VendorId)'),
                    'VendorId'=> new Expression('G.VendorId'),
                    'VendorName'=> new Expression('G.VendorName')));

            $statement = $sql->getSqlStringForSqlObject($select2);
            $vendor = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            $arrLists =array();
            $dFDate=date('Y-m-d', strtotime(Date('d-m-Y')));
            $dLDate=date('Y-m-d', strtotime(Date('d-m-Y')));
            $dtFSDate=date('Y-m-d', strtotime(Date('d-m-Y')));
            $dtFLDate=date('Y-m-d', strtotime(Date('d-m-Y')));
            $dtAsOn=date('Y-m-d', strtotime(Date('d-m-Y')));
            $total=0;
            foreach($vendor as &$vendordetails) {
                if(count($vendordetails) > 0){
                    $VendorId=$vendordetails['VendorId'];
                    if($arrLists > 0) {
                        $dtAsOn = (date('Y-m-d', strtotime($fromDate . ' - 1 days')));
                        $select3 = $sql->select();
                        $select3->from(array('A' => 'MMS_PVTrans'))
                            ->columns(array(
                                'VendorId' => new Expression('B.VendorId'),
                                'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.GrossRate)
									 Else SUM(A.BillQty*A.GrossRate) End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.QRate) Else SUM(A.BillQty*A.QRate) End End")))
                            ->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select3::JOIN_INNER)
                            ->join(array('DT' => 'MMS_DCTrans'), 'A.DCTransId=DT.DCTransId And A.DCRegisterId=DT.DCRegisterId', array(), $select3::JOIN_INNER)
                            ->join(array('DR' => 'MMS_DCRegister'), 'DT.DCRegisterId=DR.DcRegisterId', array(), $select3::JOIN_INNER)
                            ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select3::JOIN_INNER)
                            ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select3::JOIN_INNER)
                            ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select3::JOIN_LEFT)
                            ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select3::JOIN_INNER)
                            ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select3::JOIN_INNER)
                            ->where(array("A.BillQty>0 AND B.PVDate <= '$dtAsOn' And B.CostCentreId IN ($CostCentreId) And VM.VendorId=$VendorId"));
                        $select3->group(new Expression("B.VendorId, B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId"));

                        $select4 = $sql->select();
                        $select4->from(array('A' => 'MMS_PVTrans'))
                            ->columns(array(
                                'VendorId' => new Expression('B.VendorId'),
                                'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then
									 SUM(A.ActualQty*A.GrossRate)  Else SUM(A.BillQty*A.GrossRate) End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.QRate) Else
									 SUM(A.BillQty*A.QRate) End End")))
                            ->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select4::JOIN_INNER)
                            ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select4::JOIN_INNER)
                            ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select4::JOIN_INNER)
                            ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select4::JOIN_LEFT)
                            ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select4::JOIN_INNER)
                            ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select4::JOIN_INNER)
                            ->where(array("A.BillQty>0 AND B.PVDate <= '$dtAsOn'And B.ThruPO='Y' And B.CostCentreId IN ($CostCentreId) And VM.VendorId=$VendorId"));
                        $select4->group(new Expression("B.VendorId, B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId"));
                        $select4->combine($select3, 'Union ALL');

                        $select5 = $sql->select();
                        $select5->from(array('A' => 'MMS_DCTrans'))
                            ->columns(array(
                                'VendorId' => new Expression('B.VendorId'),
                                'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId))
									 Then SUM(A.BalQty*A.GrossRate) Else SUM(A.BalQty*A.QRate) End")))
                            ->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select5::JOIN_INNER)
                            ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select5::JOIN_INNER)
                            ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select5::JOIN_INNER)
                            ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select5::JOIN_LEFT)
                            ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select5::JOIN_INNER)
                            ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select5::JOIN_INNER)
                            ->where(array("A.BalQty>0 AND B.DCDate <= '$dtAsOn' And B.DcOrCSM=1 And B.CostCentreId IN ($CostCentreId) And VM.VendorId=$VendorId"));
                        $select5->group(new Expression("B.VendorId, B.PurchaseTypeId,B.CostCentreId,OC.SEZProject,SM.StateId,CM.StateId"));
                        $select5->combine($select4, 'Union ALL');

                        $select6 = $sql->select();
                        $select6->from(array('G' => $select5))
                            ->columns(array(
                                'VendorId' => new Expression('G.VendorId'),
                                'VendorName' => new Expression('B.VendorName'),
                                "amount1" => new Expression('CAST(SUM(G.Amount) As Decimal(18,2))')))
                            ->join(array('B' => 'Vendor_Master'), 'G.VendorId=B.VendorId', array(), $select6::JOIN_INNER);
                        $select6->group(new Expression("G.VendorId,B.VendorName"));
//                                Purchase Value As On('$dtAsOn')=amount1
                        $statement = $sql->getSqlStringForSqlObject($select6);
                        $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $purchasevalue=0;
                        $paidAmount=0;
                        $vendorDetailsAmt=0;
                        $Diffamount=0;
                        $exAmount=0;
                        $advPaid=0;

                        foreach ($details as &$vendordetails1) {
                            if (count($vendordetails1) > 0) {
                                $VendorId = $vendordetails1['VendorId'];
                                $purchasevalue = $vendordetails1["amount1"];
//                                    $purchasevalue=$vendordetails1["Purchase Value As On('$dtAsOn')"];
                                $select7 = $sql->select();
                                $select7->from(array('A' => 'MMS_PORegister'))
                                    ->columns(array(
                                        'VendorId' => new Expression('A.VendorId'),
                                        'PaidAmount' => new Expression("SUM(B.PaidAmount)")))
                                    ->join(array('B' => 'MMS_POPaymentTerms'), 'A.PORegisterId=B.PORegisterId', array(), $select7::JOIN_INNER)
                                    ->join(array('C' => 'WF_TermsMaster'), 'B.TermsId=C.TermsId', array(), $select7::JOIN_INNER)
                                    ->where(array("C.Title='Advance' And A.CostCentreId IN ($CostCentreId)And A.PODate <='$fromDate' And A.VendorId=$VendorId"));
                                $select7->group(new Expression("A.VendorId"));
                                $statement = $sql->getSqlStringForSqlObject($select7);
                                $details1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                foreach ($details1 as &$vendordetails2) {
                                    if (count($vendordetails2) > 0) {
                                        $paidAmount=$vendordetails2['PaidAmount'];
                                        $select8 = $sql->select();
                                        $select8->from(array('A' => 'MMS_PVRegister'))
                                            ->columns(array(
                                                'VendorId' => new Expression('A.VendorId'),
                                                'Amount' => new Expression("SUM(B.Amount)")))
                                            ->join(array('B' => 'MMS_AdvAdjustment'), 'A.PVRegisterId=B.BillRegisterId', array(), $select8::JOIN_INNER)
                                            ->join(array('C' => 'WF_TermsMaster'), 'B.TermsId=C.TermsId', array(), $select8::JOIN_INNER)
                                            ->where(array("C.Title='Advance' And A.CostCentreId IN ($CostCentreId)And A.PVDate <='$fromDate' And A.VendorId=$VendorId"));
                                        $select8->group(new Expression("A.VendorId"));
                                        $statement = $sql->getSqlStringForSqlObject($select8);
                                        $details2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        foreach ($details2 as &$vendordetails3) {
                                            if (count($vendordetails3) > 0) {
                                                $vendorDetailsAmt=$vendordetails3['Amount'];
                                                $select9 = $sql->select();
                                                $select9->from(array('A' => 'MMS_PORegister'))
                                                    ->columns(array(
                                                        'VendorId' => new Expression('A.VendorId'),
                                                        'PaidAmount' => new Expression("SUM(B.PaidAmount)")))
                                                    ->join(array('B' => 'MMS_POPaymentTerms'), 'A.PORegisterId=B.PORegisterId', array(), $select9::JOIN_INNER)
                                                    ->join(array('C' => 'WF_TermsMaster'), 'B.TermsId=C.TermsId', array(), $select9::JOIN_INNER)
                                                    ->where(array("C.Title='Advance' And A.CostCentreId IN ($CostCentreId)And A.PODate Between ('$fromDate') And ('$toDate')  And A.VendorId=$VendorId"));
                                                $select9->group(new Expression("A.VendorId"));
                                                $statement = $sql->getSqlStringForSqlObject($select9);
                                                $details3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                foreach ($details3 as &$vendordetails4) {
                                                    if (count($vendordetails4) > 0) {
                                                        $advPaid=$vendordetails4['PaidAmount'];
                                                        $select10 = $sql->select();
                                                        $select10->from(array('A' => 'MMS_PVRegister'))
                                                            ->columns(array(
                                                                'VendorId' => new Expression('A.VendorId'),
                                                                'Amount' => new Expression("SUM(B.Amount)")))
                                                            ->join(array('B' => 'MMS_AdvAdjustment'), 'A.PVRegisterId=B.BillRegisterId', array(), $select10::JOIN_INNER)
                                                            ->join(array('C' => 'WF_TermsMaster'), 'B.TermsId=C.TermsId', array(), $select10::JOIN_INNER)
                                                            ->where(array("C.Title='Advance' And A.CostCentreId IN ($CostCentreId)And A.PVDate Between ('$fromDate') And ('$toDate')  And A.VendorId=$VendorId"));
                                                        $select10->group(new Expression("A.VendorId"));
                                                        $statement = $sql->getSqlStringForSqlObject($select10);
                                                        $details4 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                        foreach ($details4 as &$vendordetails5) {
                                                            if (count($vendordetails5) > 0) {
//                                                    $dtAsOn  = (date('Y-m-d', strtotime($fromDate  . ' - 1 days')));
                                                                $dtFSDate = $fromDate;
                                                                $dtFLDate = date('Y-m-d', strtotime($fromDate . ' + 6 days'));
                                                                $select3= $sql->select();
                                                                $select3->from(array('A' => 'MMS_PVTrans'))
                                                                    ->columns(array(
                                                                        'VendorId' => new Expression('B.VendorId'),
                                                                        'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.GrossRate)
									 Else SUM(A.BillQty*A.GrossRate) End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.QRate) Else SUM(A.BillQty*A.QRate) End End")))
                                                                    ->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select3::JOIN_INNER)
                                                                    ->join(array('DT' => 'MMS_DCTrans'), 'A.DCTransId=DT.DCTransId And A.DCRegisterId=DT.DCRegisterId', array(), $select3::JOIN_INNER)
                                                                    ->join(array('DR' => 'MMS_DCRegister'), 'DT.DCRegisterId=DR.DcRegisterId', array(), $select3::JOIN_INNER)
                                                                    ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select3::JOIN_INNER)
                                                                    ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select3::JOIN_INNER)
                                                                    ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select3::JOIN_LEFT)
                                                                    ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select3::JOIN_INNER)
                                                                    ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select3::JOIN_INNER)
                                                                    ->where(array("A.BillQty>0 AND B.PVDate BETWEEN ('$dtFSDate') And ('$dtFLDate')  And B.CostCentreId IN ($CostCentreId) "));
                                                                $select3->group(new Expression("B.VendorId, B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId"));

                                                                $select4= $sql->select();
                                                                $select4->from(array('A' => 'MMS_PVTrans'))
                                                                    ->columns(array(
                                                                        'VendorId' => new Expression('B.VendorId'),
                                                                        'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then
                                SUM(A.ActualQty*A.GrossRate)  Else SUM(A.BillQty*A.GrossRate) End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.QRate) Else
                                SUM(A.BillQty*A.QRate) End End")))
                                                                    ->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select4::JOIN_INNER)
                                                                    ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select4::JOIN_INNER)
                                                                    ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select4::JOIN_INNER)
                                                                    ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select4::JOIN_LEFT)
                                                                    ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select4::JOIN_INNER)
                                                                    ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select4::JOIN_INNER)
                                                                    ->where(array("A.BillQty>0 AND B.PVDate BETWEEN ('$dtFSDate') And ('$dtFLDate') And B.ThruPO='Y'  And B.CostCentreId IN ($CostCentreId)"));
                                                                $select4->group(new Expression("B.VendorId, B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId"));
                                                                $select4->combine($select3, 'Union ALL');

                                                                $select5= $sql->select();
                                                                $select5->from(array('A' => 'MMS_DCTrans'))
                                                                    ->columns(array(
                                                                        'VendorId' => new Expression('B.VendorId'),
                                                                        'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(A.BalQty*A.GrossRate) Else SUM(A.BalQty*A.QRate) End")))
                                                                    ->join(array('B' => 'MMS_DCRegister'), ' A.DCRegisterId=B.DCRegisterId', array(), $select5::JOIN_INNER)
                                                                    ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select5::JOIN_INNER)
                                                                    ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select5::JOIN_INNER)
                                                                    ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select5::JOIN_LEFT)
                                                                    ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select5::JOIN_INNER)
                                                                    ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select5::JOIN_INNER)
                                                                    ->where(array("A.BillQty>0 AND B.DCDate BETWEEN ('$dtFSDate') And ('$dtFLDate') And B.DcOrCSM=1  And B.CostCentreId IN ($CostCentreId)"));
                                                                $select5->group(new Expression("B.VendorId, B.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId"));
                                                                $select5->combine($select4, 'Union ALL');

                                                                $select6= $sql->select();
                                                                $select6->from(array('G' => $select5))
                                                                    ->columns(array(
                                                                        'VendorId' => new Expression('G.VendorId'),
                                                                        'VendorName' => new Expression('B.VendorName'),
                                                                        'DiffAmount' => new Expression("CAST(SUM(G.Amount) As Decimal(18,2))")))
                                                                    ->join(array('B' => 'Vendor_Master'), 'G.VendorId=B.VendorId', array(), $select6::JOIN_INNER);
                                                                $select6->group(new Expression("G.VendorId, B.VendorName"));
                                                                $statement = $sql->getSqlStringForSqlObject($select6);
                                                                $details10 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                                foreach($details10 as &$vendorDetails10){
                                                                    if(count($vendorDetails10) > 0){
                                                                        $Diffamount=$vendorDetails10['DiffAmount'];
                                                                        $dFDate = date('Y-m-d', strtotime($dtFLDate . ' + 1 days'));
                                                                        $dLDate = date('Y-m-d', strtotime($dtFLDate . ' + 6 days'));

                                                                        if($dFDate > $toDate)
                                                                        {
                                                                            $dLDate =$toDate;
                                                                        }

                                                                        $select3= $sql->select();
                                                                        $select3->from(array('A' => 'MMS_DCTrans'))
                                                                            ->columns(array(
                                                                                'VendorId' => new Expression('B.VendorId'),
                                                                                'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(A.BalQty*A.GrossRate) Else SUM(A.BalQty*A.QRate) End")))
                                                                            ->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select3::JOIN_INNER)
                                                                            ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select3::JOIN_INNER)
                                                                            ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select3::JOIN_INNER)
                                                                            ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select3::JOIN_LEFT)
                                                                            ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select3::JOIN_INNER)
                                                                            ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select3::JOIN_INNER)
                                                                            ->where(array("A.BalQty>0 AND B.DCDate BETWEEN ('21/Nov/2016') And ('21/Dec/2016') And B.DcOrCSM=1 And B.CostCentreId in ($CostCentreId) "));
                                                                        $select3->group(new Expression("B.VendorId, B.PurchaseTypeId,B.CostCentreId,OC.SEZProject,SM.StateId,CM.StateId"));

                                                                        $select4= $sql->select();
                                                                        $select4->from(array('A' => 'MMS_PVTrans'))
                                                                            ->columns(array(
                                                                                'VendorId' => new Expression('B.VendorId'),
                                                                                'Amount' => new Expression(" Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then
                                    SUM(A.ActualQty*A.GrossRate)  Else SUM(A.BillQty*A.GrossRate) End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.QRate) Else
                                    SUM(A.BillQty*A.QRate) End End")))
                                                                            ->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select4::JOIN_INNER)
                                                                            ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select4::JOIN_INNER)
                                                                            ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select4::JOIN_INNER)
                                                                            ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select4::JOIN_LEFT)
                                                                            ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select4::JOIN_INNER)
                                                                            ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select4::JOIN_INNER)
                                                                            ->where(array("A.BillQty>0 AND B.PVDate BETWEEN ('21/Nov/2016') And ('21/Dec/2016') And B.ThruPO='Y'  And B.CostCentreId IN ($CostCentreId)"));
                                                                        $select4->group(new Expression("B.VendorId, B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId"));
                                                                        $select4->combine($select3, 'Union ALL');

                                                                        $select5= $sql->select();
                                                                        $select5->from(array('A' => 'MMS_PVTrans'))
                                                                            ->columns(array(
                                                                                'VendorId' => new Expression('B.VendorId'),
                                                                                'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.GrossRate)
                                    Else SUM(A.BillQty*A.GrossRate) End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.QRate) Else SUM(A.BillQty*A.QRate) End End")))
                                                                            ->join(array('B' => 'MMS_PVRegister'), ' A.PVRegisterId=B.PVRegisterId', array(), $select5::JOIN_INNER)
                                                                            ->join(array('DT' => 'MMS_DCTrans'), 'A.DCTransId=DT.DCTransId And A.DCRegisterId=DT.DCRegisterId', array(), $select5::JOIN_INNER)
                                                                            ->join(array('DR' => 'MMS_DCRegister'), ' DT.DCRegisterId=DR.DCRegisterId', array(), $select5::JOIN_INNER)
                                                                            ->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select5::JOIN_INNER)
                                                                            ->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select5::JOIN_INNER)
                                                                            ->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select5::JOIN_LEFT)
                                                                            ->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select5::JOIN_INNER)
                                                                            ->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select5::JOIN_INNER)
                                                                            ->where(array(" A.BillQty>0 AND B.PVDate BETWEEN ('21/Nov/2016') And ('21/Dec/2016')  And B.CostCentreId IN ($CostCentreId)"));
                                                                        $select5->group(new Expression(" B.VendorId, B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId"));
                                                                        $select5->combine($select4, 'Union ALL');

                                                                        $select6= $sql->select();
                                                                        $select6->from(array('G' => $select5))
                                                                            ->columns(array(
                                                                                'VendorId' => new Expression('G.VendorId'),
                                                                                'VendorName' => new Expression('B.VendorName'),
                                                                                'exAmount' => new Expression("CAST(SUM(G.Amount) As Decimal(18,2))")))
                                                                            ->join(array('B' => 'Vendor_Master'), 'G.VendorId=B.VendorId', array(), $select6::JOIN_INNER);
                                                                        $select6->group(new Expression("G.VendorId, B.VendorName"));
                                                                        $statement = $sql->getSqlStringForSqlObject($select6);
                                                                        $details9 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                                        foreach($details9 as &$vendorDetails9){
                                                                            if(count($vendorDetails9) > 0){
                                                                                $exAmount=$vendorDetails9['exAmount'];
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }

                                                    }
                                                }
                                            }

                                        }
                                    }
                                }
                            }
                        }

                    }

                }
               $total = $purchasevalue + $paidAmount - $vendorDetailsAmt;
                $dumArr = array(
                    'SlNo' => $vendordetails['SlNo'],
                    'SupplierId' => $vendordetails['VendorId'],
                    'SupplierName' => $vendordetails['VendorName'],
                    "A" => $purchasevalue,//Purchase Value As On('$dtAsOn')
                    "B" => $paidAmount,//Advance Paid On('$dtAsOn')
                    "C" => $vendorDetailsAmt,//Advance Ded As On('$dtAsOn')
                    "Total" => $total,
                    "D " => $advPaid,//Advance Paid ('$fromDate') And ('$toDate')
                    "E " => $Diffamount,//('$dtFSDate') TO ('$dtFLDate')
                    "F" => $exAmount//('$dFDate') TO ('$dLDate')
                );
                $arrLists[] = $dumArr;
            }

//            print_r($arrLists);die;

            $this->_view->arrLists =$arrLists;
            $this->_view->dtAsOn =$dtAsOn;
            $this->_view->dtFSDate =$dtFSDate;
            $this->_view->dtFLDate =$dtFLDate;
            $this->_view->dFDate =$dFDate;
            $this->_view->dLDate =$dLDate;
            $this->_view->fromDate =$fromDate;
            $this->_view->toDate =$toDate;
            $this->_view->total =$total;
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
	}

    public function purchasebillDeviationAction(){
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $CostCentreId =$this->params()->fromPost('CostCentreId');
                if($CostCentreId == ""){
                    $CostCentreId =0;
                }

                switch($Type) {
                    case 'cost':
                        $select8 = $sql->select();
                        $select8->from(array('A' =>"MMS_DCTrans"))
                            ->columns(array(
                                'ResourceId'=>new Expression('A.ResourceId'),
                                'Qty'=> new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
                                'Amount'=> new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then (A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else A.QAmount End')
                            ))
                            ->join(array('DG' => 'MMS_DCGroupTrans'),'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId',array(),$select8::JOIN_INNER)
                            ->join(array('B' => 'MMS_DCRegister'),'A.DCRegisterId=B.DCRegisterId',array(),$select8::JOIN_INNER)
                            ->where(array("B.CostCentreId in($CostCentreId) And DATEPART(m, B.DCDate) <> DATEPART(m, DATEADD(m, 0, getdate())) Union All
Select A.ResourceId,CAST(A.BillQty As Decimal(18,5)) Qty,A.QAmount from MMS_PVTrans A
Inner Join MMS_PVRegister B  On A.PVRegisterId=B.PVRegisterId
Where B.ThruPO='Y' And B.CostCentreId in($CostCentreId) And DATEPART(m, B.PVDate) <> DATEPART(m, DATEADD(m, 0, getdate()))"));

                        $select9 = $sql->select();
                        $select9->from(array('A' =>$select8))
                            ->columns(array(
                                'ResourceId'=>new Expression('A.ResourceId'),
                                'PUQty'=> new Expression('CAST(SUM(A.Qty) As Decimal(18,5)) '),
                                'PURate'=> new Expression('(SUM(A.Amount)/SUM(A.Qty))'),
                                'PUAmount'=> new Expression('SUM(A.Qty) * (SUM(A.Amount)/SUM(A.Qty))')
                            ));
                        $select9->group(new Expression("A.ResourceId"));

                        $select10 = $sql->select();
                        $select10->from(array('A' =>"MMS_DCTrans"))
                            ->columns(array(
                                'ResourceId'=>new Expression('A.ResourceId'),
                                'Qty'=> new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
                                'Amount'=> new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then (A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else  A.QAmount End')
                            ))
                            ->join(array('DG' => 'MMS_DCGroupTrans'),'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId',array(),$select10::JOIN_INNER)
                            ->join(array('B' => 'MMS_DCRegister'),'A.DCRegisterId=B.DCRegisterId',array(),$select10::JOIN_INNER)
                            ->where(array(" B.CostCentreId in($CostCentreId) And DATEPART(m, B.DCDate) = DATEPART(m, DATEADD(m, 0, getdate())) Union All
Select A.ResourceId,CAST(A.BillQty As Decimal(18,5)) Qty,A.QAmount from MMS_PVTrans A
Inner Join MMS_PVRegister B  On A.PVRegisterId=B.PVRegisterId
Where B.ThruPO='Y' And  B.CostCentreId in($CostCentreId) And DATEPART(m, B.PVDate) = DATEPART(m, DATEADD(m, 0, getdate()))"));

                        $select11 = $sql->select();
                        $select11->from(array('A' =>$select10))
                            ->columns(array(
                                'ResourceId'=>new Expression('A.ResourceId'),
                                'PDQty'=> new Expression('CAST(SUM(A.Qty) As Decimal(18,5)) '),
                                'PDRate'=> new Expression('(SUM(A.Amount)/SUM(A.Qty))'),
                                'PDAmount'=> new Expression('SUM(A.Qty) * (SUM(A.Amount)/SUM(A.Qty))')
                            ));
                        $select11->group(new Expression("A.ResourceId"));

                        $select20 = $sql->select();
                        $select20->from(array('A' =>"MMS_PVTrans"))
                            ->columns(array(
                                'ResourceId'=>new Expression('A.ResourceId'),
                                'Qty'=> new Expression('CAST(A.BillQty As Decimal(18,5))'),
                                'Amount'=> new Expression('A.QAmount')
                            ))
                            ->join(array('B' => 'MMS_PVRegister'),'A.PVRegisterId=B.PVRegisterId',array(),$select20::JOIN_INNER)
                            ->where(array(" B.ThruPO='Y' And B.CostCentreId in($CostCentreId)"));

                        $select12 = $sql->select();
                        $select12->from(array('A' =>"MMS_DCTrans"))
                            ->columns(array(
                                'ResourceId'=>new Expression('A.ResourceId'),
                                'Qty'=> new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
                                'Amount'=> new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then
            (A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else A.QAmount End')
                            ))
                            ->join(array('DG' => 'MMS_DCGroupTrans'),'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId',array(),$select12::JOIN_INNER)
                            ->join(array('B' => 'MMS_DCRegister'),'A.DCRegisterId=B.DCRegisterId',array(),$select10::JOIN_INNER)
                            ->where(array(" B.CostCentreId in($CostCentreId)"));
                        $select12->combine($select20,'Union ALL');


                        $select13 = $sql->select();
                        $select13->from(array('A' =>$select12))
                            ->columns(array(
                                'ResourceId'=>new Expression('A.ResourceId'),
                                'TQty'=> new Expression('CAST(SUM(A.Qty) As Decimal(18,5))'),
                                'TAmount'=> new Expression('SUM(A.Amount)')
                            ));
                        $select13->group(new Expression("A.ResourceId"));
                        $statement = $sql->getSqlStringForSqlObject($select13);

                        $select = $sql->select();
                        $select->from(array('A' =>"Proj_ProjectDetails"))
                            ->columns(array(
                                'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
                                'Resource'=> new Expression('B.ResourceName'),
                                'Unit'=> new Expression('C.UnitName'),
                                'EstQty'=> new Expression('A.Qty'),
                                'EstRate'=> new Expression('A.Rate'),
                                'EstAmount'=> new Expression('(A.Qty*A.Rate)'),
                                'PUQty'=> new Expression('Z.PUQty'),
                                'PURate'=> new Expression('Z.PURate'),
                                'PUAmount'=> new Expression('Z.PUAmount'),
                                'PDQty'=> new Expression('Y.PDQty'),
                                'PDRate'=> new Expression('Y.PDRate'),
                                'PDAmount'=> new Expression('Y.PDAmount'),
                                'TQty'=> new Expression('X.TQty'),
                                'TAmount'=> new Expression('X.TAmount'),
                                'BQty'=> new Expression('CAST((A.Qty-X.TQty) As Decimal(18,5))'),
                                'BAmount'=> new Expression('CAST(((A.Qty*A.Rate)-X.TAmount) As Decimal(18,3))'),
                                'PurAmount'=> new Expression('(X.TQty*A.Rate)'),
                                'DeviationAmt'=> new Expression('CAST(((X.TQty*A.Rate)-X.TAmount) As Decimal(18,3))'),
                                'DeviationPer'=> new Expression('CAST(((((X.TQty*A.Rate)-X.TAmount)*100)/(X.TQty*A.Rate)) As Decimal(18,3))')
                            ))
                            ->join(array('B' => 'Proj_Resource'),'A.ResourceId=B.ResourceId',array("ResourceId","Code"),$select::JOIN_INNER)
                            ->join(array('C' => 'Proj_UOM'),'B.UnitId=C.UnitId',array(),$select::JOIN_INNER)
                            ->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select::JOIN_INNER)
                            ->join(array('Z' => $select9),'Z.ResourceId=A.ResourceId',array(),$select::JOIN_LEFT)
                            ->join(array('Y' => $select11),'Y.ResourceId=A.ResourceId',array(),$select::JOIN_LEFT)
                            ->join(array('X' => $select13),'X.ResourceId=A.ResourceId',array(),$select::JOIN_LEFT)
                            ->where(array("A.ProjectId in ($CostCentreId) And B.TypeID IN (2,3)"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $purchasebill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($purchasebill));
                        return $response;
                        break;

                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

//            $select8 = $sql->select();
//            $select8->from(array('A' =>"MMS_DCTrans"))
//                ->columns(array(
//                    'ResourceId'=>new Expression('A.ResourceId'),
//                    'Qty'=> new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
//                    'Amount'=> new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then (A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else A.QAmount End')
//                ))
//                ->join(array('DG' => 'MMS_DCGroupTrans'),'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId',array(),$select8::JOIN_INNER)
//                ->join(array('B' => 'MMS_DCRegister'),'A.DCRegisterId=B.DCRegisterId',array(),$select8::JOIN_INNER)
//                ->where(array("DATEPART(m, B.DCDate) <> DATEPART(m, DATEADD(m, 0, getdate())) Union All
//Select A.ResourceId,CAST(A.BillQty As Decimal(18,5)) Qty,A.QAmount from MMS_PVTrans A
//Inner Join MMS_PVRegister B  On A.PVRegisterId=B.PVRegisterId
//Where B.ThruPO='Y' And DATEPART(m, B.PVDate) <> DATEPART(m, DATEADD(m, 0, getdate()))"));
//
//            $select9 = $sql->select();
//            $select9->from(array('A' =>$select8))
//                ->columns(array(
//                    'ResourceId'=>new Expression('A.ResourceId'),
//                    'PUQty'=> new Expression('CAST(SUM(A.Qty) As Decimal(18,5)) '),
//                    'PURate'=> new Expression('(SUM(A.Amount)/SUM(A.Qty))'),
//                    'PUAmount'=> new Expression('SUM(A.Qty) * (SUM(A.Amount)/SUM(A.Qty))')
//                ));
//            $select9->group(new Expression("A.ResourceId"));
//
//            $select10 = $sql->select();
//            $select10->from(array('A' =>"MMS_DCTrans"))
//                ->columns(array(
//                    'ResourceId'=>new Expression('A.ResourceId'),
//                    'Qty'=> new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
//                    'Amount'=> new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then (A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else  A.QAmount End')
//                ))
//                ->join(array('DG' => 'MMS_DCGroupTrans'),'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId',array(),$select10::JOIN_INNER)
//                ->join(array('B' => 'MMS_DCRegister'),'A.DCRegisterId=B.DCRegisterId',array(),$select10::JOIN_INNER)
//                ->where(array("DATEPART(m, B.DCDate) = DATEPART(m, DATEADD(m, 0, getdate())) Union All
//Select A.ResourceId,CAST(A.BillQty As Decimal(18,5)) Qty,A.QAmount from MMS_PVTrans A
//Inner Join MMS_PVRegister B  On A.PVRegisterId=B.PVRegisterId
//Where B.ThruPO='Y' And DATEPART(m, B.PVDate) = DATEPART(m, DATEADD(m, 0, getdate()))"));
//
//            $select11 = $sql->select();
//            $select11->from(array('A' =>$select10))
//                ->columns(array(
//                    'ResourceId'=>new Expression('A.ResourceId'),
//                    'PDQty'=> new Expression('CAST(SUM(A.Qty) As Decimal(18,5)) '),
//                    'PDRate'=> new Expression('(SUM(A.Amount)/SUM(A.Qty))'),
//                    'PDAmount'=> new Expression('SUM(A.Qty) * (SUM(A.Amount)/SUM(A.Qty))')
//                ));
//            $select11->group(new Expression("A.ResourceId"));
//
//            $select20 = $sql->select();
//            $select20->from(array('A' =>"MMS_PVTrans"))
//                ->columns(array(
//                    'ResourceId'=>new Expression('A.ResourceId'),
//                    'Qty'=> new Expression('CAST(A.BillQty As Decimal(18,5))'),
//                    'Amount'=> new Expression('A.QAmount')
//                ))
//                ->join(array('B' => 'MMS_PVRegister'),'A.PVRegisterId=B.PVRegisterId',array(),$select20::JOIN_INNER)
//                ->where(array(" B.ThruPO='Y'"));
//
//            $select12 = $sql->select();
//            $select12->from(array('A' =>"MMS_DCTrans"))
//                ->columns(array(
//                    'ResourceId'=>new Expression('A.ResourceId'),
//                    'Qty'=> new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
//                    'Amount'=> new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then
//            (A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else A.QAmount End')
//                ))
//                ->join(array('DG' => 'MMS_DCGroupTrans'),'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId',array(),$select12::JOIN_INNER)
//                ->join(array('B' => 'MMS_DCRegister'),'A.DCRegisterId=B.DCRegisterId',array(),$select10::JOIN_INNER);
//             $select12->combine($select20,'Union ALL');
//
//
//            $select13 = $sql->select();
//            $select13->from(array('A' =>$select12))
//                ->columns(array(
//                    'ResourceId'=>new Expression('A.ResourceId'),
//                    'TQty'=> new Expression('CAST(SUM(A.Qty) As Decimal(18,5))'),
//                    'TAmount'=> new Expression('SUM(A.Amount)')
//                ));
//            $select13->group(new Expression("A.ResourceId"));
//            $statement = $sql->getSqlStringForSqlObject($select13);
//
//            $select = $sql->select();
//            $select->from(array('A' =>"Proj_ProjectDetails"))
//                ->columns(array(
//                    'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
//                    'Resource'=> new Expression('B.ResourceName'),
//                    'Unit'=> new Expression('C.UnitName'),
//                    'EstQty'=> new Expression('A.Qty'),
//                    'EstRate'=> new Expression('A.Rate'),
//                    'EstAmount'=> new Expression('(A.Qty*A.Rate)'),
//                    'PUQty'=> new Expression('Z.PUQty'),
//                    'PURate'=> new Expression('Z.PURate'),
//                    'PUAmount'=> new Expression('Z.PUAmount'),
//                    'PDQty'=> new Expression('Y.PDQty'),
//                    'PDRate'=> new Expression('Y.PDRate'),
//                    'PDAmount'=> new Expression('Y.PDAmount'),
//                    'TQty'=> new Expression('X.TQty'),
//                    'TAmount'=> new Expression('X.TAmount'),
//                    'BQty'=> new Expression('CAST((A.Qty-X.TQty) As Decimal(18,5))'),
//                    'BAmount'=> new Expression('CAST(((A.Qty*A.Rate)-X.TAmount) As Decimal(18,3))'),
//                    'PurAmount'=> new Expression('(X.TQty*A.Rate)'),
//                    'DeviationAmt'=> new Expression('CAST(((X.TQty*A.Rate)-X.TAmount) As Decimal(18,3))'),
//                    'DeviationPer'=> new Expression('CAST(((((X.TQty*A.Rate)-X.TAmount)*100)/(X.TQty*A.Rate)) As Decimal(18,3))')
//                ))
//                ->join(array('B' => 'Proj_Resource'),'A.ResourceId=B.ResourceId',array("ResourceId","Code"),$select::JOIN_INNER)
//                ->join(array('C' => 'Proj_UOM'),'B.UnitId=C.UnitId',array(),$select::JOIN_INNER)
//                ->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select::JOIN_INNER)
//                ->join(array('Z' => $select9),'Z.ResourceId=A.ResourceId',array(),$select::JOIN_LEFT)
//                ->join(array('Y' => $select11),'Y.ResourceId=A.ResourceId',array(),$select::JOIN_LEFT)
//                ->join(array('X' => $select13),'X.ResourceId=A.ResourceId',array(),$select::JOIN_LEFT)
//                ->where(array("B.TypeID IN (2,3)"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $this->_view->arr_purchasebill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }


	public function materialPurchaseAmountAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');	
				$CostCentreId =$this->params()->fromPost('CostCentreId'); 
				if($CostCentreId == ""){
					$CostCentreId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				$toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
			 	
					if($fromDat == ''){
					$fromDat =  0;
					}
					if($toDat == ''){
						$toDat = 0;
					}

					if($fromDat == 0){
						$fromDate =  0;
					}
					if($toDat == 0){
						$toDate = 0;
					}
					if($fromDat == 0) {
						$fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$fromDate=date('Y-m-d',strtotime($fromDat));
					}
					if($toDat == 0) {
						$toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
					}
					else
					{
						$toDate =date('Y-m-d',strtotime($toDat));
					}
					
					switch($Type) {
						case 'cost':
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_PVTrans'))
							->columns(array(
								'PurchaseTypeId'=>new Expression('B.PurchaseTypeId'),
								'CostCentreId'=> new Expression('B.CostCentreId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then  
                    Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End)  
                    Else SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End ) End Else Case When B.ThruDC='Y' Then 
                    SUM(A.ActualQty*Case When (PG.TFactor>0  And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End ) Else 
                    SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) End End")))
								
									->join(array('PG' => 'MMS_PVGroupTrans'),"A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId", array(), $select1::JOIN_INNER)
									->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select1::JOIN_INNER)	
									->join(array('DT' => 'MMS_DCTrans'),'A.DCTransId=DT.DCTransId And A.DCRegisterId=DT.DCRegisterId', array(), $select1::JOIN_INNER)	
									->join(array('DR' => 'MMS_DCRegister'), 'DT.DCRegisterId=DR.DCRegisterId', array(), $select1::JOIN_INNER)	
									->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select1::JOIN_INNER)	
									->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select1::JOIN_INNER)	
									->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select1::JOIN_LEFT)	
									->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select1::JOIN_INNER)	
									->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select1::JOIN_INNER)	
									
							->where(array(" A.BillQty>0 AND DR.DCDate BETWEEN ('$fromDate') AND ('$toDate') AND B.CostCentreId IN ($CostCentreId)"));
						$select1->group(new Expression("A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,B.CostCentreId,OC.SEZProject,SM.StateId,CM.StateId"));
						
						$select2 = $sql->select();
						$select2->from(array('A' => 'MMS_PVTrans'))
							->columns(array(
								'PurchaseTypeId'=>new Expression('B.PurchaseTypeId'),
								'CostCentreId'=> new Expression('A.CostCentreId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId))  
                    Then Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
                    Else SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End ) End Else Case When B.ThruDC='Y' Then 
                    SUM(A.ActualQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) Else 
                    SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End ) End End")))
								
									->join(array('PG' => 'MMS_PVGroupTrans'),"A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId", array(), $select2::JOIN_INNER)
									->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select2::JOIN_INNER)	
									->join(array('OC' => 'WF_OperationalCostCentre'), 'A.CostCentreId=OC.CostCentreId', array(), $select2::JOIN_INNER)	
									->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select2::JOIN_INNER)	
									->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select2::JOIN_LEFT)	
									->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select2::JOIN_INNER)	
									->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select2::JOIN_INNER)	
									
							->where(array("A.BillQty>0 AND B.PVDate BETWEEN ('$fromDate') AND ('$toDate') AND A.CostCentreId IN ($CostCentreId) And B.CostCentreId=0"));
						$select2->group(new Expression("A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC ,A.CostCentreId,OC.SEZProject,SM.StateId,CM.StateId"));
						$select2->combine($select1,'Union ALL');
						
						$select3 = $sql->select();
						$select3->from(array('A' => 'MMS_PVTrans'))
							->columns(array(
								'PurchaseTypeId'=>new Expression('B.PurchaseTypeId'),
								'CostCentreId'=> new Expression('B.CostCentreId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then  
                    Case When B.ThruDC='Y' Then SUM(A.ActualQty*A.GrossRate)  Else 
                    SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) End Else Case When B.ThruDC='Y' Then 
                    SUM(A.ActualQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) Else 
                    SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) End End")))
								
									->join(array('PG' => 'MMS_PVGroupTrans'),"A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId", array(), $select3::JOIN_INNER)
									->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select3::JOIN_INNER)	
									->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select3::JOIN_INNER)	
									->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select3::JOIN_INNER)	
									->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select3::JOIN_LEFT)	
									->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select3::JOIN_INNER)	
									->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select3::JOIN_INNER)	
									
							->where(array("A.BillQty>0 AND B.PVDate BETWEEN ('$fromDate') AND ('$toDate') AND B.CostCentreId IN ($CostCentreId) And B.ThruPO='Y'"));
						$select3->group(new Expression("A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,B.CostCentreId,OC.SEZProject,SM.StateId,CM.StateId"));
						$select3->combine($select2,'Union ALL');
						
						$select4 = $sql->select();
						$select4->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'PurchaseTypeId'=>new Expression('B.PurchaseTypeId'),
								'CostCentreId'=> new Expression('B.CostCentreId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("SUM(A.BalQty)"),
								'Amount'=> new Expression("SUM(A.BalQty*Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((A.Rate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.Rate End)")))
								
									->join(array('DG' => 'MMS_DCGroupTrans'),"A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId", array(), $select4::JOIN_INNER)
									->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select4::JOIN_INNER)	
									->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select4::JOIN_INNER)
									
							->where(array("A.BalQty>0 AND B.DCDate BETWEEN ('$fromDate') AND ('$toDate') AND B.CostCentreId IN ($CostCentreId) AND B.DcOrCSM=1"));
						$select4->group(new Expression("A.ResourceId,A.ItemId,B.PurchaseTypeId,B.CostCentreId,OC.SEZProject"));
						$select4->combine($select3,'Union ALL');
						
						$select5 = $sql->select();
						$select5->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'PurchaseTypeId'=>new Expression('B.PurchaseTypeId'),
								'CostCentreId'=> new Expression('A.CostCentreId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("SUM(A.BalQty)"),
								'Amount'=> new Expression("SUM(A.BalQty*Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((A.Rate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.Rate End)")))
								
									->join(array('DG' => 'MMS_DCGroupTrans'),"A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId", array(), $select5::JOIN_INNER)
									->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select5::JOIN_INNER)	
									->join(array('OC' => 'WF_OperationalCostCentre'), 'A.CostCentreId=OC.CostCentreId', array(), $select5::JOIN_INNER)
									
							->where(array("A.BalQty>0 AND B.DCDate BETWEEN ('$fromDate') AND ('$toDate') AND A.CostCentreId IN ($CostCentreId) And B.CostCentreId=0 And B.DcOrCSM=1"));
						$select5->group(new Expression("A.ResourceId,A.ItemId,B.PurchaseTypeId,A.CostCentreId,OC.SEZProject"));
						$select5->combine($select4,'Union ALL');
						
						$select6 = $sql->select();
						$select6->from(array('G' => $select5))
							->columns(array(
								'PurchaseTypeId'=>new Expression('G.PurchaseTypeId'),
								'CostCentreId'=> new Expression('G.CostCentreId'),
								'ResourceId'=> new Expression('G.ResourceId'),
								'ItemId'=> new Expression('G.ItemId'),
								'Qty'=> new Expression("CAST(SUM(G.Qty) As Decimal(18,6))"),
								'CostCentre'=> new Expression("K.CostCentreName"),
								'Code'=> new Expression("Case When G.ItemId>0 Then I.ItemCode Else H.Code End"),
								'Resource'=> new Expression("Case When G.ItemId>0 Then I.BrandName Else H.ResourceName End"),
								'PurchaseType'=> new Expression("J.PurchaseTypeName"),
								'Amount'=> new Expression("CAST(SUM(G.Amount) As Decimal(18,3))")))
								
									->join(array('H' => 'Proj_Resource'),"G.ResourceId=H.ResourceID", array(), $select6::JOIN_INNER)
									->join(array('I' => 'MMS_Brand'), 'G.ResourceId=I.ResourceId And G.ItemId=I.BrandId', array(), $select6::JOIN_LEFT)	
									->join(array('J' => 'MMS_PurchaseType'), 'G.PurchaseTypeId=J.PurchaseTypeId', array(), $select6::JOIN_INNER)
									->join(array('K' => 'WF_OperationalCostCentre'), 'K.CostCentreId=G.CostCentreId', array(), $select6::JOIN_INNER);
							
						$select6->group(new Expression("G.PurchaseTypeId,G.CostCentreId,G.ResourceId,G.ItemId,K.CostCentreName,I.ItemCode,H.Code,I.BrandName,H.ResourceName,J.PurchaseTypeName"));
						
						$statement= $sql->getSqlStringForSqlObject($select6); 
						$purchasebillhistory = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($purchasebillhistory));
						return $response;
						break;
						
						case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
					}	
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}	
			$projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();	
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
}