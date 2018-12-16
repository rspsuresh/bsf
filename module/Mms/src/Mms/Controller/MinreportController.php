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

class MinreportController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		$this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function minPendingAction(){
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
						$select1->from(array('A' => 'MMS_DCGroupTrans'))
							->columns(array(
								'MinDate'=> new Expression('Convert(Varchar(10),DCDate,103)'),
								'MinNo'=> new Expression('DCNo'),
								'CMinNo'=> new Expression('C.CDCNo'),
								'SiteMinNo'=> new Expression('C.SiteDCNo'),
								'Vendor'=>new Expression('V.VendorName'),
								'CostCentre'=> new Expression('CC.CostCentreName'),
								'ResourceGroup'=> new Expression('RG.ResourceGroupName'),
								'Code'=> new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End'),
								'Unit'=>new Expression('U.UnitName'),
								'MinQty'=>new Expression('CAST(A.AcceptQty As Decimal(18,6))'),
								'BillQty'=>new Expression('CAST(A.BillQty As Decimal(18,6))'),
								'BalQty'=>new Expression('CAST(A.BalQty As Decimal(18,6)) '),
								'Rate'=>new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST(isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) As Decimal(18,2)) Else CAST(A.QRate As Decimal(18,2)) End'),
								'Amount'=>new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST((A.AcceptQty*isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0)) As Decimal(18,2)) Else CAST(A.QAmount  As Decimal(18,2)) End '),
								'RejectQty'=>new Expression('CAST(A.RejectQty As Decimal(18,5))'),
								'RejectAmount'=>new Expression('CAST((A.RejectQty*Case When (A.TFactor>0 And A.FFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End) As Decimal(18,2))'),
								'Approve'=>new Expression("DATEDIFF(Day,C.DCDate,getdate()) PendingNoOfDays,Case When C.Approve='Y' Then 'Yes' When C.Approve='P' Then 'Partial' Else 'No' End")))
								
									->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_DCRegister'), 'A.DCRegisterId=C.DCRegisterId', array(), $select1::JOIN_INNER)
									->join(array('CC' => 'WF_OperationalCostCentre'),'C.CostCentreId = CC.CostCentreId  ',array(),$select1::JOIN_INNER)
									->join(array('V' => 'Vendor_Master'),'V.VendorId=C.VendorId',array(),$select1::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select1::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'CC.CompanyId=CM.CompanyId',array(),$select1::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select1::JOIN_INNER)
									->join(array('U' => 'Proj_UOM'),'A.UnitId=U.UnitId',array(),$select1::JOIN_LEFT)
							->where(array("A.BalQty>0 AND C.DCDate Between ('$fromDate') And ('$toDate') And C.Approve='Y' And C.CostCentreId IN ($CostCentreId) And C.DCOrCSM=1 And A.ShortClose=0"));
							
						$select = $sql->select();
						$select->from(array('A' => 'MMS_DCGroupTrans'))
							->columns(array(
								'MinDate'=> new Expression('Convert(Varchar(10),DCDate,103)'),
								'MinNo'=> new Expression('DCNo'),
								'CMinNo'=> new Expression('C.CDCNo'),
								'SiteMinNo'=> new Expression('C.SiteDCNo'),
								'Vendor'=>new Expression('V.VendorName'),
								'CostCentre'=> new Expression('CC.CostCentreName'),
								'ResourceGroup'=> new Expression('RG.ResourceGroupName'),
								'Code'=> new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End'),
								'Unit'=>new Expression('U.UnitName'),
								'MinQty'=>new Expression('CAST(A.AcceptQty As Decimal(18,6))'),
								'BillQty'=>new Expression('CAST(A.BillQty As Decimal(18,6))'),
								'BalQty'=>new Expression('CAST(A.BalQty As Decimal(18,6))'),
								'Rate'=>new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST(isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) As Decimal(18,2)) Else CAST(A.QRate As Decimal(18,2)) End'),
								'Amount'=>new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST((A.AcceptQty*isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0)) As Decimal(18,2)) Else CAST(A.QAmount As Decimal(18,2)) End'),
								'RejectAmount'=>new Expression('CAST(A.RejectQty As Decimal(18,6)) As RejectQty, CAST((A.RejectQty*Case When (A.TFactor>0 And A.FFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End) As Decimal(18,2))'),
								'Approve'=>new Expression("DATEDIFF(Day,C.DCDate,getdate()) PendingNoOfDays,Case When C.Approve='Y' Then 'Yes' When C.Approve='P' Then 'Partial' Else 'No' End")))
								
									->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
									->join(array('C' => 'MMS_DCRegister'), 'A.DCRegisterId=C.DCRegisterId', array(), $select::JOIN_INNER)
									->join(array('CC' => 'WF_OperationalCostCentre'),'A.CostCentreId = CC.CostCentreId',array(),$select::JOIN_INNER)
									->join(array('V' => 'Vendor_Master'),'V.VendorId=C.VendorId',array(),$select::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'CC.CompanyId=CM.CompanyId',array(),$select::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select::JOIN_INNER)
									->join(array('U' => 'Proj_UOM'),'A.UnitId=U.UnitId',array(),$select::JOIN_LEFT)
							->where(array("A.BalQty>0 AND C.DCDate Between ('$fromDate') And ('$toDate') And C.Approve='Y' And C.DCOrCSM=1 And C.CostCentreId=0 And A.CostCentreId IN ($CostCentreId) And A.ShortClose= 0"));
						$select->combine($select1,'Union ALL');
						$statement = $statement = $sql->getSqlStringForSqlObject($select); 
						$minpendingResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($minpendingResult));
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

	public function minRejectionAction(){
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
						$select1->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'MinDate'=> new Expression('Convert(Varchar(10),DCDate,103)'),
								'MinNo'=> new Expression('DCNo'),
								'Vendor'=> new Expression('V.VendorName'),
								'CostCentre'=> new Expression('CC.CostCentreName'),
								'Code'=>new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else ResourceName End'),
								'Unit'=> new Expression('U.UnitName'),
								'MinQty'=> new Expression('A.DCQty'),
								'AcceptQty'=> new Expression('A.AcceptQty'),
								'RejectQty'=>new Expression('A.RejectQty'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName')))
								
									->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select1::JOIN_INNER)
									->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_DCRegister'), 'A.DCRegisterId=C.DCRegisterId', array(), $select1::JOIN_INNER)
									->join(array('CC' => 'WF_OperationalCostCentre'),'C.CostCentreId = CC.CostCentreId',array(),$select1::JOIN_INNER)
									->join(array('V' => 'Vendor_Master'),'V.VendorId=C.VendorId',array(),$select1::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select1::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'CC.CompanyId=CM.CompanyId',array(),$select1::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select1::JOIN_INNER)
									->join(array('U' => 'Proj_UOM'),'DG.UnitId=U.UnitId',array(),$select1::JOIN_LEFT)
							->where(array("A.RejectQty>0 AND C.DCDate Between ('$fromDate') And ('$toDate') And C.Approve='Y' And C.CostCentreId IN ($CostCentreId) And C.DCOrCSM=1"));
							
						$select = $sql->select();
						$select->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'MinDate'=> new Expression('Convert(Varchar(10),DCDate,103)'),
								'MinNo'=> new Expression('DCNo'),
								'Vendor'=> new Expression('V.VendorName'),
								'CostCentre'=> new Expression('CC.CostCentreName'),
								'Code'=>new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else ResourceName End'),
								'Unit'=> new Expression('U.UnitName'),
								'MinQty'=> new Expression('A.DCQty'),
								'AcceptQty'=> new Expression('A.AcceptQty'),
								'RejectQty'=>new Expression('A.RejectQty'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName')))
								
									->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select::JOIN_INNER)
									->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
									->join(array('C' => 'MMS_DCRegister'), 'A.DCRegisterId=C.DCRegisterId', array(), $select::JOIN_INNER)
									->join(array('CC' => 'WF_OperationalCostCentre'),'A.CostCentreId = CC.CostCentreId',array(),$select::JOIN_INNER)
									->join(array('V' => 'Vendor_Master'),'V.VendorId=C.VendorId',array(),$select::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'CC.CompanyId=CM.CompanyId',array(),$select::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select::JOIN_INNER)
									->join(array('U' => 'Proj_UOM'),'DG.UnitId=U.UnitId',array(),$select::JOIN_LEFT)
							->where(array("A.RejectQty>0 AND C.DCDate Between ('$fromDate') And ('$toDate') And C.DCOrCSM=1 And C.CostCentreId=0 And A.CostCentreId IN ($CostCentreId)"));
						$select->combine($select1,'Union ALL');
						$statement = $statement = $sql->getSqlStringForSqlObject($select); 
						$minrejectionResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($minrejectionResult));
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

	public function minHistoryAction(){
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
						$select1->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'DCRegisterId'=> new Expression('A.DCRegisterId'),
								'DCTransId'=> new Expression('A.DCTransId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'CostCentreId'=>new Expression('E.CostCentreId'),
								'MinDate'=> new Expression('Convert(Varchar(10),E.DCDate,103)'),
								'MinNo'=> new Expression('DCNo'),
								'SiteMinNo'=> new Expression('E.SiteDCNo'),
								'Vendor'=> new Expression('V.VendorName'),
								'CostCentre'=>new Expression('CC.CostCentreName'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
								'Code'=>new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else R.Code End'),
								'Resource'=>new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else R.ResourceName End'),
								'Unit'=>new Expression('U.UnitName'),
								'MinQty'=>new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
								'PVNo'=>new Expression('D.PVNo'),
								'PVDate'=>new Expression('Convert(Varchar(10), D.PVDate,103)'),
								'BillQty'=>new Expression('CAST(C.BillQty As Decimal(18,6))'),
								'Rate'=>new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then CAST(isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) As Decimal(18,2)) Else CAST(A.QRate As Decimal(18,2)) End'),
								'Amount'=>new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then CAST((A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) As Decimal(18,2)) Else CAST(A.QAmount As Decimal(18,2)) End')))
								
									->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select1::JOIN_INNER)
									->join(array('B' => 'MMS_IPDTrans'), 'A.DCTransId=B.DCTransId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_PVTrans'), 'B.PVTransId=C.PVTransId', array(), $select1::JOIN_INNER)
									->join(array('D' => 'MMS_PVRegister'),'C.PVRegisterId=D.PVRegisterId',array(),$select1::JOIN_INNER)
									->join(array('R' => 'Proj_Resource'),'R.ResourceId=A.ResourceId',array(),$select1::JOIN_INNER)
									->join(array('E' => 'MMS_DCRegister'),'E.DCRegisterId=A.DCRegisterId',array(),$select1::JOIN_INNER)
									->join(array('CC' => 'WF_OperationalCostCentre'),'D.CostCentreId=CC.CostCentreId',array(),$select1::JOIN_INNER)
									->join(array('V' => 'Vendor_Master'),'V.VendorId=E.VendorId',array(),$select1::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select1::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'CC.CompanyId=CM.CompanyId',array(),$select1::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'R.ResourceGroupId=RG.ResourceGroupId',array(),$select1::JOIN_INNER)
									->join(array('U' => 'Proj_UOM'),'DG.UnitId=U.UnitId',array(),$select1::JOIN_LEFT)
							->where(array("E.DCDate Between ('$fromDate') And ('$toDate') And D.CostCentreId IN ($CostCentreId) And E.DcOrCSM=1"));
							
						$select = $sql->select();
						$select->from(array('A' => 'MMS_DCTrans'))
							->columns(array(
								'DCRegisterId'=> new Expression('A.DCRegisterId'),
								'DCTransId'=> new Expression('A.DCTransId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'CostCentreId'=>new Expression('C.CostCentreId'),
								'MinDate'=> new Expression('Convert(Varchar(10),E.DCDate,103)'),
								'MinNo'=> new Expression('DCNo'),
								'SiteMinNo'=> new Expression('E.SiteDCNo'),
								'Vendor'=> new Expression('V.VendorName'),
								'CostCentre'=>new Expression('CC.CostCentreName'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
								'Code'=>new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else R.Code End'),
								'Resource'=>new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else R.ResourceName End'),
								'Unit'=>new Expression('U.UnitName'),
								'MinQty'=>new Expression('CAST(A.AcceptQty As Decimal(18,5))'),
								'PVNo'=>new Expression('D.PVNo'),
								'PVDate'=>new Expression('Convert(Varchar(10), D.PVDate,103)'),
								'BillQty'=>new Expression('CAST(C.BillQty As Decimal(18,6))'),
								'Rate'=>new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then CAST(isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) As Decimal(18,2)) Else CAST(A.QRate As Decimal(18,2)) End'),
								'Amount'=>new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then CAST((A.AcceptQty*isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) As Decimal(18,2)) Else CAST(A.QAmount As Decimal(18,2)) End')))
								
									->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select::JOIN_INNER)
									->join(array('B' => 'MMS_IPDTrans'), 'A.DCTransId=B.DCTransId', array(), $select::JOIN_INNER)
									->join(array('C' => 'MMS_PVTrans'), 'B.PVTransId=C.PVTransId', array(), $select::JOIN_INNER)
									->join(array('D' => 'MMS_PVRegister'),'C.PVRegisterId=D.PVRegisterId',array(),$select::JOIN_INNER)
									->join(array('R' => 'Proj_Resource'),'R.ResourceId=A.ResourceId',array(),$select::JOIN_INNER)
									->join(array('E' => 'MMS_DCRegister'),'E.DcRegisterId=A.DCRegisterId',array(),$select::JOIN_INNER)
									->join(array('CC' => 'WF_OperationalCostCentre'),'C.CostCentreId=CC.CostCentreId',array(),$select::JOIN_INNER)
									->join(array('V' => 'Vendor_Master'),'V.VendorId=E.VendorId',array(),$select::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'CC.CompanyId=CM.CompanyId',array(),$select::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'R.ResourceGroupId=RG.ResourceGroupId',array(),$select::JOIN_INNER)
									->join(array('U' => 'Proj_UOM'),'DG.UnitId=U.UnitId',array(),$select::JOIN_LEFT)
							->where(array("E.DCDate Between ('$fromDate') And ('$toDate') And D.CostCentreId=0 AND C.CostCentreId IN ($CostCentreId) And E.DcOrCSM=1"));
						$select->combine($select1,'Union ALL');
						$statement = $statement = $sql->getSqlStringForSqlObject($select);  
						$minhistoryResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($minhistoryResult));
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
	public function minDetailedAction(){
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
						$select1->from(array('A' => 'MMS_DCRegister'))
							->columns(array(
								'MIN Date'=> new Expression('Convert(Varchar(10),A.DCDate,103)'),
								'MIN No'=> new Expression('A.DCNo'),
								'Site MIN Date'=> new Expression('Convert(Varchar(10),A.SiteDCDate,103)'),
								'Site MIN No'=> new Expression('A.SiteDCNo'),
								'CCMinNo'=>new Expression('A.CCDCNo'),
								'CMinNo'=> new Expression('A.CDCNo'),
								'PONo'=> new Expression('PR.PONo'),
								'CCPONo'=> new Expression('PR.CCPONo'),
								'CPONo'=> new Expression('PR.CPONo'),
								'Supplier'=>new Expression('F.VendorName'),
								'Code'=>new Expression('Case When B.ItemId <> 0 Then BR.ItemCode Else C.Code End'),
								'Resource'=>new Expression('Case When B.ItemId <> 0 Then BR.BrandName Else C.ResourceName End'),
								'Specification'=>new Expression('PT.Description'),
								'Unit'=>new Expression('U.UnitName'),
								'Qty'=>new Expression('B.AcceptQty'),
								'BaseRate'=>new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((B.Rate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.Rate End'),
								'QualRate'=>new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((B.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.QRate End'),
								'Amount'=>new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then (B.AcceptQty*isnull((B.Rate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else B.Amount End'),
								'QAmount'=>new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then (B.AcceptQty*isnull((B.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else B.QAmount End'),
								'CostCentre'=>new Expression('G.CostCentreName'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
								'Approve'=>new Expression("Case When A.Approve='Y' Then 'Yes' When A.Approve='P' Then 'Partial' Else 'No' End"),
								'BillConverted'=>new Expression("Case When B.BillQty>0 Then 'Yes' Else 'No' End")))
								
									->join(array('B' => 'MMS_DCTrans'), 'A.DCRegisterId=B.DCRegisterId', array(), $select1::JOIN_INNER)
									->join(array('DG' => 'MMS_DCGroupTrans'), 'B.DCGroupId=DG.DCGroupId And B.DCRegisterId=DG.DCRegisterId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'Proj_Resource'), 'B.ResourceId=C.ResourceId', array(), $select1::JOIN_INNER)
									->join(array('G' => 'WF_OperationalCostCentre'),'A.CostCentreId=G.CostCentreId',array(),$select1::JOIN_INNER)
									->join(array('F' => 'Vendor_Master'),'A.VendorId=F.VendorId',array(),$select1::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'B.ItemId=BR.BrandId',array(),$select1::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'G.CompanyId=CM.CompanyId',array(),$select1::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'C.ResourceGroupId=RG.ResourceGroupId',array(),$select1::JOIN_INNER)
									->join(array('PT' => 'MMS_POTrans'),'B.POTransId = PT.POTransId',array(),$select1::JOIN_LEFT)
									->join(array('PR' => 'MMS_PORegister'),'PT.PORegisterId = PR.PORegisterId',array(),$select1::JOIN_LEFT)
									->join(array('U' => 'Proj_UOM'),'DG.UnitId=U.UnitId',array(),$select1::JOIN_LEFT)
							->where(array("A.DCDate Between ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId) And A.DcOrCSM=1"));
							
						$select = $sql->select();
						$select->from(array('A' => 'MMS_DCRegister'))
							->columns(array(
								'MIN Date'=> new Expression('Convert(Varchar(10),A.DCDate,103)'),
								'MIN No'=> new Expression('A.DCNo'),
								'Site MIN Date'=> new Expression('Convert(Varchar(10),A.SiteDCDate,103)'),
								'Site MIN No'=> new Expression('A.SiteDCNo'),
								'CCMinNo'=>new Expression('A.CCDCNo'),
								'CMinNo'=> new Expression('A.CDCNo'),
								'PONo'=> new Expression('PR.PONo'),
								'CCPONo'=> new Expression('PR.CCPONo'),
								'CPONo'=> new Expression('PR.CPONo'),
								'Supplier'=> new Expression('F.VendorName'),
								'Code'=>new Expression('Case When B.ItemId <> 0 Then BR.ItemCode Else C.Code End'),
								'Resource'=>new Expression('Case When B.ItemId <> 0 Then BR.BrandName Else C.ResourceName End'),
								'Specification'=>new Expression('PT.Description'),
								'Unit'=>new Expression('U.UnitName'),
								'Qty'=>new Expression('B.AcceptQty'),
								'BaseRate'=>new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((B.Rate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.Rate End'),
								'QualRate'=>new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((B.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.QRate End'),
								'Amount'=>new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then (B.AcceptQty*isnull((B.Rate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else B.Amount End'),
								'QAmount'=>new Expression('Case When (DG.FFactor>0 And DG.TFactor>0) Then (B.AcceptQty*isnull((B.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else B.QAmount End'),
								'CostCentre'=>new Expression('G.CostCentreName'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
								'Approve'=>new Expression("Case When A.Approve='Y' Then 'Yes' When A.Approve='P' Then 'Partial' Else 'No' End"),
								'BillConverted'=>new Expression("Case When B.BillQty>0 Then 'Yes' Else 'No' End")))
								
									->join(array('B' => 'MMS_DCTrans'), 'A.DCRegisterId=B.DCRegisterId', array(), $select::JOIN_INNER)
									->join(array('DG' => 'MMS_DCGroupTrans'), 'B.DCGroupId=DG.DCGroupId And B.DCRegisterId=DG.DCRegisterId', array(), $select::JOIN_INNER)
									->join(array('C' => 'Proj_Resource'), 'B.ResourceId=C.ResourceId', array(), $select::JOIN_INNER)
									->join(array('G' => 'WF_OperationalCostCentre'),'B.CostCentreId=G.CostCentreId',array(),$select::JOIN_INNER)
									->join(array('F' => 'Vendor_Master'),'A.VendorId=F.VendorId',array(),$select::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'),'B.ItemId=BR.BrandId',array(),$select::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'G.CompanyId=CM.CompanyId',array(),$select::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'C.ResourceGroupId=RG.ResourceGroupId',array(),$select::JOIN_INNER)
									->join(array('PT' => 'MMS_POTrans'),'B.POTransId = PT.POTransId',array(),$select::JOIN_LEFT)
									->join(array('PR' => 'MMS_PORegister'),'PT.PORegisterId = PR.PORegisterId',array(),$select::JOIN_LEFT)
									->join(array('U' => 'Proj_UOM'),'DG.UnitId=U.UnitId',array(),$select::JOIN_LEFT)
							->where(array("A.DCDate Between ('$fromDate') And ('$toDate') And A.CostCentreId=0 And B.CostCentreId IN ($CostCentreId) And A.DcOrCSM=1"));
						$select->combine($select1,'Union ALL');
						$statement = $statement = $sql->getSqlStringForSqlObject($select);  
						$mindetailedResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($mindetailedResult));
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

	public function wbsMinRegisterAction(){
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
						$select1->from(array('DAT' => 'MMS_DCAnalTrans'))
							->columns(array(
								'CostCentreId'=>new Expression('DR.CostCentreId'),
								'AnalysisId'=> new Expression('DAT.AnalysisId'),
								'ResourceId'=> new Expression('DAT.ResourceId'),
								'UnitId'=> new Expression('DGT.UnitId'),
								'ItemId'=> new Expression('DAT.ItemId'),
								'AcceptQty'=>new Expression('DAT.AcceptQty'),
								'QRate'=>new Expression('Case When (DGT.FFactor>0 And DGT.TFactor>0) Then isnull((DT.QRate*DGT.TFactor),0)/nullif(DGT.FFactor,0) Else DT.QRate End'),
								'Amount'=>new Expression('DAT.AcceptQty*Case When (DGT.FFactor>0 And DGT.TFactor>0) Then isnull((DT.QRate*DGT.TFactor),0)/nullif(DGT.FFactor,0) Else DT.QRate End')))
								
									->join(array('DGT' => 'MMS_DCGroupTrans'), 'DAT.DcGroupId=DGT.DCGroupId', array(), $select1::JOIN_INNER)
									->join(array('DT' => 'MMS_DCTrans'), 'DAT.DCTransId=DT.DCTransId', array(), $select1::JOIN_INNER)
									->join(array('DR' => 'MMS_DCRegister'), 'DT.DCRegisterId=DR.DCRegisterId', array(), $select1::JOIN_INNER)	
							->where(array("DR.DCDate Between ('$fromDate') And ('$toDate') And DR.CostCentreId IN ($CostCentreId) And DR.DcOrCSM=1"));
							
						$select2 = $sql -> select();
                        $select2 -> from(array("A"=>$select1))
                             ->columns(array(
								'CostCentreId'=>new Expression('A.CostCentreId'),
								'AnalysisId'=> new Expression('A.AnalysisId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'UnitId'=> new Expression('A.UnitId'),
								'Qty'=>new Expression('SUM(CAST(A.AcceptQty As Decimal(18,6)))'),
								'QRate'=>new Expression('CAST(A.QRate as decimal(18,2))'),
								'Amount'=>new Expression('SUM(CAST( A.Amount As Decimal(18,2)))')));	
						$select2->group(new Expression("A.ResourceId,A.ItemId,A.CostCentreId,A.AnalysisId,A.QRate,A.UnitId"));
						
						$select3 = $sql -> select();
                        $select3 -> from(array("A"=>$select2))
                            ->columns(array(
								'PLevel'=>new Expression('B.ParentText'),
								'WbsName'=> new Expression('B.WbsName'),
								'CostCentre'=> new Expression('I.CostCentreName'),
								'Code'=> new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else C.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else C.ResourceName End'),
								'Unit'=>new Expression('U.UnitName'),
								'CostCentreId'=>new Expression(' A.CostCentreId'),
								'AnalysisId'=>new Expression('A.AnalysisId'),
								'ResourceId'=>new Expression('A.ResourceId'),
								'ItemId'=>new Expression('A.ItemId'),
								'Rate'=>new Expression('CAST(A.QRate as decimal(18,2))'),
								'Qty'=>new Expression('CAST(Qty as decimal(18,6))'),
								'Amount'=>new Expression('CAST(Amount as decimal(18,2))'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName')))
            	
								->join(array('B' => 'Proj_WbsMaster'), 'A.AnalysisId=B.WbsId', array(), $select3::JOIN_INNER)
								->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceId', array(), $select3::JOIN_INNER)
								->join(array('I' => 'WF_OperationalCostCentre'), 'A.CostCentreId=I.CostCentreId', array(), $select3::JOIN_INNER)
								->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $select3::JOIN_LEFT)
								->join(array('CA' => 'WF_CostCentre'), 'I.FACostCentreId=CA.CostCentreId', array(), $select3::JOIN_INNER)
								->join(array('CM' => 'WF_CompanyMaster'), 'CA.CompanyId=CM.CompanyId', array(), $select3::JOIN_INNER)	
								->join(array('RG' => 'Proj_ResourceGroup'), 'C.ResourceGroupId=RG.ResourceGroupId', array(), $select3::JOIN_INNER)	
								->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $select3::JOIN_LEFT)	
							->where(array("I.CostCentreId IN ($CostCentreId)"));
							
						$statement = $statement = $sql->getSqlStringForSqlObject($select3);  
						$wbsminResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($wbsminResult));
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

	public function wbsMinPendingAction(){
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
						$select1->from(array('DAT' => 'MMS_DCAnalTrans'))
							->columns(array(
								'CostCentreId'=>new Expression('DR.CostCentreId'),
								'AnalysisId'=> new Expression('DAT.AnalysisId'),
								'ResourceId'=> new Expression('DAT.ResourceId'),
								'ItemId'=> new Expression('DAT.ItemId'),
								'UnitId'=> new Expression('DGT.UnitId'),
								'AcceptQty'=>new Expression('DAT.AcceptQty'),
								'BillQty'=>new Expression('DAT.BillQty'),
								'BalQty'=>new Expression('DAT.BalQty'),
								'Amount'=>new Expression('DAT.AcceptQty*CASE When (DGT.FFactor>0 And DGT.TFactor>0) Then isnull((DT.QRate*DGT.TFactor),0)/nullif(DGT.FFactor,0) Else DT.QRate End')))
								
									->join(array('DGT' => 'MMS_DCGroupTrans'), 'DAT.DcGroupId=DGT.DCGroupId', array(), $select1::JOIN_INNER)
									->join(array('DT' => 'MMS_DCTrans'), 'DAT.DCTransId=DT.DCTransId', array(), $select1::JOIN_INNER)
									->join(array('DR' => 'MMS_DCRegister'), 'DT.DCRegisterId=DR.DcRegisterId', array(), $select1::JOIN_INNER)	
							->where(array("DR.DCDate Between ('$fromDate') And ('$toDate') And DR.CostCentreId IN ($CostCentreId) And DR.DCOrCSM=1 And DR.Approve='Y' And DGT.ShortClose=0"));
							
						$select2 = $sql -> select();
                        $select2 -> from(array("A"=>$select1))
                             ->columns(array(
								'CostCentreId'=>new Expression('A.CostCentreId'),
								'AnalysisId'=> new Expression('A.AnalysisId'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'UnitId'=> new Expression('A.UnitId'),
								'DCQty'=>new Expression('SUM(CAST(A.AcceptQty As Decimal(18,6)))'),
								'BillQty'=>new Expression('SUM(CAST(A.BillQty As Decimal(18,6)))'),
								'BalQty'=>new Expression('SUM(CAST(A.BalQty As Decimal(18,6)))'),
								'Amount'=>new Expression('SUM(CAST( A.Amount As Decimal(18,3)))')));	
						$select2->group(new Expression("A.ResourceId,A.ItemId,A.CostCentreId,A.AnalysisId,A.UnitId Having SUM(A.BalQty)>0"));
						
						$select3 = $sql -> select();
                        $select3 -> from(array("A"=>$select2))
                            ->columns(array(
								'PLevel'=>new Expression('B.ParentText'),
								'WbsName'=> new Expression('B.WbsName'),
								'CostCentre'=> new Expression('I.CostCentreName'),
								'Code'=> new Expression('Case When A.ItemId <> 0  Then BR.ItemCode Else C.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else C.ResourceName End'),
								'Unit'=>new Expression('U.UnitName'),
								'CostCentreId'=>new Expression(' A.CostCentreId'),
								'AnalysisId'=>new Expression('A.AnalysisId'),
								'ResourceId'=>new Expression('A.ResourceId'),
								'ItemId'=>new Expression('A.ItemId'),
								'DCQty'=>new Expression('DCQty'),
								'BillQty'=>new Expression('BillQty'),
								'BalQty'=>new Expression('BalQty'),
								'Amount'=>new Expression('Amount'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName')))
            	
								->join(array('B' => 'Proj_WbsMaster'), 'A.AnalysisId=B.WbsId', array(), $select3::JOIN_INNER)
								->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceId', array(), $select3::JOIN_INNER)
								->join(array('I' => 'WF_OperationalCostCentre'), 'A.CostCentreId=I.CostCentreId', array(), $select3::JOIN_INNER)
								->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $select3::JOIN_LEFT)
								->join(array('CA' => 'WF_CostCentre'), 'I.FACostCentreId=CA.CostCentreId', array(), $select3::JOIN_INNER)
								->join(array('CM' => 'WF_CompanyMaster'), 'CA.CompanyId=CM.CompanyId', array(), $select3::JOIN_INNER)	
								->join(array('RG' => 'Proj_ResourceGroup'), 'C.ResourceGroupId=RG.ResourceGroupId', array(), $select3::JOIN_INNER)	
								->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $select3::JOIN_LEFT)	
							->where(array("I.CostCentreId IN ($CostCentreId)"));
							
						$statement = $statement = $sql->getSqlStringForSqlObject($select3); 
						$wbsminResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($wbsminResult));
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

	public function budgetAnalysisReportAction(){
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
						$select1->from(array('PC' => 'Proj_ProjectDetails'))
							->columns(array(
								'ResourceId'=>new Expression('PC.ResourceId'),
								'CostCentreId'=> new Expression('OC.CostCentreId'),
								'EstQty'=> new Expression('CAST(PC.Qty As Decimal(18,3))'),
								'EstRate'=> new Expression('CAST(PC.Rate As Decimal(18,3))'),
								'EstAmount'=> new Expression('CAST((PC.Qty*PC.Rate) As Decimal(18,3))'),
								'ReqQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'ReqAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCRate'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferIn'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferOut'=>new Expression('CAST(0 As decimal(18,2))'),
								'NetTransfer'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransInAmt'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransOutAmt'=>new Expression('CAST(0 As decimal(18,2))')))
								
							->join(array('OC' => 'WF_OperationalCostCentre'), 'PC.ProjectId=OC.CostCentreId', array(), $select1::JOIN_INNER)
							->where(array("OC.CostCentreId IN ($CostCentreId)"));
							
						$select2 = $sql -> select();
                        $select2 -> from(array("RT"=>'VM_RequestTrans'))
                            ->columns(array(
								'ResourceId'=>new Expression('RT.ResourceId'),
								'CostCentreId'=> new Expression('RR.CostCentreId'),
								'EstQty'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstRate'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstAmount'=> new Expression('CAST(0 As decimal(18,2))'),
								'ReqQty'=>new Expression('CAST(ISNULL(SUM(RT.Quantity),0) As Decimal(18,3))'),
								'ReqAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCRate'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferIn'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferOut'=>new Expression('CAST(0 As decimal(18,2))'),
								'NetTransfer'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransInAmt'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransOutAmt'=>new Expression('CAST(0 As decimal(18,2))')))
							->join(array('RR' => 'VM_RequestRegister'), 'RT.RequestId=RR.RequestId', array(), $select2::JOIN_INNER)
							->where(array("CostCentreId IN ($CostCentreId) AND RR.RequestDate Between ('$fromDate') And ('$toDate') "));
						$select2->group(new Expression("RT.ResourceId,RR.CostCentreId"));
						$select2->combine($select1,'Union ALL');
						
						$select3 = $sql -> select();
                        $select3 -> from(array("DT"=>'MMS_DCTrans'))
                            ->columns(array(
								'ResourceId'=>new Expression('DT.ResourceId'),
								'CostCentreId'=> new Expression('DR.CostCentreId'),
								'EstQty'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstRate'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstAmount'=> new Expression('CAST(0 As decimal(18,2))'),
								'ReqQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'ReqAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCQty'=>new Expression('CAST(ISNULL (SUM(DT.AcceptQty),0) As Decimal(18,3))'),
								'DCRate'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCAmount'=>new Expression('CAST(ISNULL( SUM(Case When (DG.FFactor>0 And DG.TFactor>0) Then (DT.AcceptQty*isnull((DT.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) Else DT.QAmount End),0) As Decimal(18,3))'),
								'TransferIn'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferOut'=>new Expression('CAST(0 As decimal(18,2))'),
								'NetTransfer'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransInAmt'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransOutAmt'=>new Expression('CAST(0 As decimal(18,2))')))
            	
							->join(array('DG' => 'MMS_DCGroupTrans'), 'DT.DCGroupId=DG.DCGroupId And DT.DCRegisterId=DG.DCRegisterId', array(), $select3::JOIN_INNER)
							->join(array('DR' => 'MMS_DCRegister'), 'DT.DCRegisterId=DR.DCRegisterId', array(), $select3::JOIN_INNER)
							->where(array("DR.CostCentreId IN ($CostCentreId) And DR.DcOrCSM=1 And DR.DCDate Between ('$fromDate') And ('$toDate') "));
						$select3->group(new Expression("DT.ResourceId,DR.CostCentreId"));
						$select3->combine($select2,'Union ALL');
						
						$select4 = $sql -> select();
                        $select4 -> from(array("TT"=>'MMS_TransferTrans'))
                            ->columns(array(
								'ResourceId'=>new Expression('TT.ResourceId'),
								'CostCentreId'=> new Expression('TR.ToCostCentreId'),
								'EstQty'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstRate'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstAmount'=> new Expression('CAST(0 As decimal(18,2))'),
								'ReqQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'ReqAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCRate'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferIn'=>new Expression('CAST(ISNULL(SUM(TT.TransferQty),0) AS Decimal(18,3))'),
								'TransferOut'=>new Expression('CAST(0 As decimal(18,2))'),
								'NetTransfer'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransInAmt'=>new Expression('(CAST(ISNULL(SUM(TT.QAmount),0) AS Decimal(18,2)))'),
								'TransOutAmt'=>new Expression('CAST(0 As decimal(18,2))')))
            	
							->join(array('TR' => 'MMS_TransferRegister'), 'TT.TransferRegisterId=TR.TVRegisterId', array(), $select4::JOIN_INNER)
							->where(array("TR.ToCostCentreId IN ($CostCentreId) And TR.TVDate Between ('$fromDate') And ('$toDate') AND TT.TCostCentreId IN ($CostCentreId) "));
						$select4->group(new Expression("TT.ResourceId,TR.ToCostCentreId"));
						$select4->combine($select3,'Union ALL');
						
						$select5 = $sql -> select();
                        $select5 -> from(array("TT"=>'MMS_TransferTrans'))
                            ->columns(array(
								'ResourceId'=>new Expression('TT.ResourceId'),
								'CostCentreId'=> new Expression('TR.FromCostCentreId'),
								'EstQty'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstRate'=> new Expression('CAST(0 As decimal(18,2))'),
								'EstAmount'=> new Expression('CAST(0 As decimal(18,2))'),
								'ReqQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'ReqAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCRate'=>new Expression('CAST(0 As decimal(18,2))'),
								'DCAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferIn'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferOut'=>new Expression('CAST(ISNULL(SUM(TT.TransferQty),0) AS Decimal(18,3))'),
								'NetTransfer'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransferAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'TotalAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceQty'=>new Expression('CAST(0 As decimal(18,2))'),
								'BalanceAmount'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransInAmt'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransOutAmt'=>new Expression('(CAST(ISNULL(SUM(TT.QAmount),0) AS Decimal(18,2)))')))
            	
							->join(array('TR' => 'MMS_TransferRegister'), 'TT.TransferRegisterId=TR.TVRegisterId', array(), $select5::JOIN_INNER)
							->where(array("TR.FromCostCentreId IN ($CostCentreId) And TVDate Between ('$fromDate') And ('$toDate') AND TT.FCostCentreId IN ($CostCentreId) "));
						$select5->group(new Expression("TT.ResourceId,TR.FromCostCentreId"));
						$select5->combine($select4,'Union ALL');
						
						$select6 = $sql -> select();
                        $select6 -> from(array("A"=>$select5))
                            ->columns(array(
								'ResourceId'=>new Expression('A.ResourceId'),
								'Code'=> new Expression('RV.Code'),
								'Resource'=> new Expression('RV.ResourceName'),
								'Unit'=> new Expression('UM.UnitName'),
								'EstQty'=> new Expression('CAST( ISNULL(SUM(EstQty),0) As Decimal(18,3))'),
								'EstRate'=>new Expression('CAST( ISNULL(SUM(EstRate),0) As Decimal(18,3))'),
								'EstAmount'=> new Expression('CAST(ISNULL( SUM(EstAmount),0) As Decimal(18,3))'),
								'ReqQty'=>new Expression('CAST( ISNULL( SUM(ReqQty),0) AS Decimal(18,3))'),
								'ReqAmount'=>new Expression('(CAST(ISNULL(SUM(ReqQty),0) * ISNULL(SUM(ReqAmount),0) AS Decimal(18,3)))'),
								'DCQty'=>new Expression('CAST(ISNULL(SUM(DCQty),0) As Decimal(18,3))'),
								'DCRate'=>new Expression('(CAST(ISNULL(SUM(DCAmount),0)/ NULLIF( SUM(DCQty),0) As Decimal(18,3)))'),
								'DCAmount'=>new Expression('CAST(ISNULL( SUM(DCAmount),0) As Decimal(18,3))'),
								'TransferIn'=>new Expression('CAST(ISNULL(SUM(TransferIn),0) As Decimal(18,3))'),
								'TransferOut'=>new Expression('CAST(ISNULL(SUM(TransferOut),0) As Decimal(18,3))'),
								'NetTransfer'=>new Expression('(CAST(ISNULL(SUM(TransferIn),0) As Decimal(18,3)) - CAST(ISNULL(SUM(TransferOut),0) As Decimal(18,3)))'),
								'TransferAmount'=>new Expression('CAST(Sum(TransInAmt) As Decimal(18,3)) - CAST(Sum(TransOutAmt) As Decimal(18,3))'),
								'TotalQty'=>new Expression('(CAST(ISNULL(SUM(DCQty),0) + (CAST(ISNULL(SUM(TransferIn),0) As Decimal(18,3)) - CAST(ISNULL(SUM(TransferOut),0) As Decimal(18,3))) AS Decimal(18,3)))'),
								'TotalAmount'=>new Expression('(CAST(ISNULL(SUM(DCAmount),0) + (CAST(Sum(TransInAmt) As Decimal(18,3)) - CAST(Sum(TransOutAmt) As Decimal(18,3))) AS Decimal(18,3)))'),
								'BalanceQty'=>new Expression('CAST( CAST( ISNULL( SUM(ReqQty),0) AS Decimal(18,3)) - (CAST(ISNULL(SUM(DCQty),0) + (CAST(ISNULL(SUM(TransferIn),0) As Decimal(18,3)) - CAST(ISNULL(SUM(TransferOut),0) As Decimal(18,3))) AS Decimal(18,3))) As Decimal(18,3))'),
								'BalanceAmount'=>new Expression('(CAST(ISNULL( SUM(EstAmount),0) As Decimal(18,3))-(CAST(ISNULL(SUM(DCAmount),0) + (CAST(Sum(TransInAmt) As Decimal(18,3)) - CAST(Sum(TransOutAmt) As Decimal(18,3))) AS Decimal(18,3))))'),
								'TransInAmt'=>new Expression('CAST(0 As decimal(18,2))'),
								'TransOutAmt'=>new Expression('CAST(0 As decimal(18,2))'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName')))
            	
								->join(array('RV' => 'Proj_Resource'), 'A.ResourceId=RV.ResourceID', array(), $select6::JOIN_INNER)
								->join(array('OC' => 'WF_OperationalCostCentre'), 'OC.CostCentreId=A.CostCentreId', array(), $select6::JOIN_INNER)
								->join(array('CM' => 'WF_CompanyMaster'), 'OC.CompanyId=CM.CompanyId', array(), $select6::JOIN_INNER)
								->join(array('RG' => 'Proj_ResourceGroup'), 'RV.ResourceGroupId=RG.ResourceGroupId', array(), $select6::JOIN_INNER)
								->join(array('UM' => 'Proj_UOM'), 'RV.UnitId=UM.UnitId', array(), $select6::JOIN_LEFT)	
							->where(array("OC.CostCentreId IN ($CostCentreId)"));
						$select6->group(new Expression("A.ResourceId ,RV.ResourceName ,UM.UnitName,RV.Code,RG.ResourceGroupName"));
						
						
						
						$statement = $statement = $sql->getSqlStringForSqlObject($select6); 
						$wbsminResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($wbsminResult));
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