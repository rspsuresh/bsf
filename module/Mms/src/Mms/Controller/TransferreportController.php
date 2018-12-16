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

class TransferreportController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		$this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function detailedTransferRegisterAction(){
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
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_TransferRegister'))
							->columns(array(
								'TVNo'=> new Expression('A.TVNo'),
								'TVDate'=> new Expression('Convert(Varchar(10),A.TVDate,103)'),
								'FromCostCentre'=> new Expression('D.CostCentreName'),							
								'ToCostCentre'=> new Expression('E.CostCentreName'),
								'Code'=>new Expression('Case When B.ItemId <> 0 Then BR.ItemCode Else F.Code End'),
								'Resource'=> new Expression('Case When B.ItemId <> 0 Then BR.BrandName Else F.ResourceName End'),
								'Unit'=> new Expression('U.UnitName'),
								'TransferAccount'=> new Expression('AMTA.AccountName'),
								'PurchaseAccount'=> new Expression('AMPA.AccountName'),
								'TransferQty'=> new Expression('B.TransferQty'),
								'Rate'=> new Expression('CAST(B.QRate As Decimal(18,3))'),
								'Amount'=> new Expression('CAST(B.QAmount AS Decimal(18,3))'),
								'ResourceGroup'=> new Expression('RG.ResourceGroupName')))
								
									->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId=B.TransferRegisterId', array(), $select1::JOIN_INNER)
									->join(array('D' => 'WF_OperationalCostCentre'), 'A.FromCostCentreId=D.CostCentreId', array(), $select1::JOIN_LEFT)
									->join(array('E' => 'WF_OperationalCostCentre'), 'A.ToCostCentreId=E.CostCentreId', array(), $select1::JOIN_LEFT)
									->join(array('F' => 'Proj_Resource'), 'B.ResourceId=F.ResourceID', array(), $select1::JOIN_LEFT)
									->join(array('BR' => 'MMS_Brand'), 'B.ItemId=BR.BrandId', array(), $select1::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'), 'D.CompanyId=CM.CompanyId', array(), $select1::JOIN_INNER)
									->join(array('AMTA' => 'FA_AccountMaster'), 'AMTA.AccountId=B.TransferTypeId', array(), $select1::JOIN_LEFT)
									->join(array('AMPA' => 'FA_AccountMaster'), 'AMPA.AccountId=B.PurchaseTypeId', array(), $select1::JOIN_LEFT)
									->join(array('RG' => 'Proj_ResourceGroup'), 'F.ResourceGroupId=RG.ResourceGroupId', array(), $select1::JOIN_INNER)
									->join(array('U' => 'Proj_Uom'), 'B.UnitId=U.UnitId', array(), $select1::JOIN_LEFT)
							->where(array("A.TVDate BetWeen ('$fromDate') And ('$toDate') And A.FromCostCentreId IN ($CostCentreId) Order By A.TVDate"));						
						$statement = $sql->getSqlStringForSqlObject($select1);
						$transferregisterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($transferregisterResult));
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
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function wbsTransferRegisterAction(){
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
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_TransferAnalTrans'))
							->columns(array(
								'PLevel'=> new Expression('d.ParentText'),
								'WbsName'=> new Expression('d.WbsName'),
								'CostCentre'=> new Expression('f.CostCentreName'),							
								'Code'=> new Expression('Case when a.itemid > 0 then g.ItemCode else e.code end'),
								'Resource'=> new Expression('Case when a.itemid > 0 then g.brandname else e.ResourceName End'),
								'unit'=> new Expression('j.unitname'),
								'qty'=> new Expression('sum(CAST(a.transferqty as decimal(18,6)))'),
								'Amount'=> new Expression('sum(cast(a.transferqty*b.qrate as decimal(18,2)))'),
								'resourcegroup'=> new Expression('i.resourcegroupname')))								
									->join(array('B' => 'MMS_TransferTrans'), 'A.TransferTransId=B.TransferTransId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_TransferRegister'), 'B.TransferRegisterId=C.TVRegisterId', array(), $select1::JOIN_INNER)
									->join(array('D' => 'Proj_WbsMaster'), 'A.AnalysisId=D.WbsId', array(), $select1::JOIN_INNER)
									->join(array('E' => 'Proj_Resource'), 'A.ResourceId=E.ResourceId', array(), $select1::JOIN_INNER)
									->join(array('f' => 'WF_Operationalcostcentre'), 'C.FromCostCentreId=f.CostCentreId', array(), $select1::JOIN_INNER)
									->join(array('g' => 'MMS_Brand'), 'A.ResourceId=g.ResourceId And A.ItemId=g.BrandId', array(), $select1::JOIN_LEFT)
									->join(array('h' => 'WF_CompanyMaster'), 'f.companyid=h.companyid', array(), $select1::JOIN_INNER)
									->join(array('i' => 'Proj_ResourceGroup'), 'e.ResourceGroupId=i.ResourceGroupId', array(), $select1::JOIN_INNER)
									->join(array('j' => 'proj_uom'), 'b.unitid=j.unitid', array(), $select1::JOIN_LEFT)
									->where(array("a.TransferQty > 0 and c.tvdate between ('$fromDate') And ('$toDate') And c.FromCostCentreId IN ($CostCentreId) group by d.parenttext,d.wbsname,f.costcentrename,g.itemcode,e.code,g.brandname,e.resourcename,j.unitname,a.resourceid,a.itemid,i.ResourceGroupName"));						
						$statement = $sql->getSqlStringForSqlObject($select1);
						$wbsregisterResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($wbsregisterResult));
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
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function transitReportAction(){
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
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_TransferRegister'))
							->columns(array(
								'TVNo'=> new Expression('A.TVNo'),
								'TVDate'=> new Expression('Convert(Varchar(10),A.TVDate,103)'),
								'FromCostCentre'=> new Expression('D.CostCentreName'),							
								'ToCostCentre'=> new Expression('E.CostCentreName'),
								'Code'=>new Expression('Case When B.ItemId <> 0 Then BR.ItemCode Else F.Code End'),
								'Resource'=> new Expression('Case When B.ItemId <> 0 Then BR.BrandName Else F.ResourceName End'),
								'Unit'=> new Expression('U.UnitName'),
								'TransferQty'=> new Expression('CAST(B.TransferQty As Decimal(18,6))'),
								'Rate'=> new Expression('CAST(B.QRate As Decimal(18,2))'),
								'Amount'=> new Expression('CAST(B.QAmount AS Decimal(18,2))'),
								'ReceiptQty'=> new Expression('CAST(B.RecdQty As Decimal(18,6))'),
								'BalanceQty'=> new Expression('CAST((B.TransferQty-B.RecdQty) As Decimal(18,6))'),
								'ResourceGroup'=> new Expression('RG.ResourceGroupName')))
								
									->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId=B.TransferRegisterId ', array(), $select1::JOIN_INNER)
									->join(array('D' => 'WF_OperationalCostCentre'), 'A.FromCostCentreId=D.CostCentreId', array(), $select1::JOIN_LEFT)
									->join(array('E' => 'WF_OperationalCostCentre'), 'A.ToCostCentreId=E.CostCentreId', array(), $select1::JOIN_LEFT)
									->join(array('F' => 'Proj_Resource'), 'B.ResourceId=F.ResourceID', array(), $select1::JOIN_LEFT)
									->join(array('BR' => 'MMS_Brand'), 'B.ItemId=BR.BrandId', array(), $select1::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'), 'D.CompanyId=CM.CompanyId', array(), $select1::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'), 'F.ResourceGroupId=RG.ResourceGroupId', array(), $select1::JOIN_INNER)
									->join(array('U' => 'Proj_Uom'), 'B.UnitId=U.UnitId', array(), $select1::JOIN_LEFT)
							->where(array("A.TVDate BetWeen ('$fromDate') And ('$toDate') And CAST((B.TransferQty-B.RecdQty) As Decimal(18, 6))>0 And B.ShortClose=0 
									And A.FromCostCentreId IN ($CostCentreId) And A.Approve='Y' Order By A.TVDate"));						
						$statement = $sql->getSqlStringForSqlObject($select1);
						$transitreportResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($transitreportResult));
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
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
}