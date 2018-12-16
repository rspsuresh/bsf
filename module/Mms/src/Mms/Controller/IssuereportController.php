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

class IssuereportController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function issueReturnAction(){
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
                $CompanyId= $this->bsf->isNullCheck($postParams['CompanyId'],'string');
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
                        $CompanySelect->from(array('A' => 'WF_OperationalCostCentre'))
                            ->columns(array("CostCentreId", "CostCentreName"))
                            ->join(array('B' => 'WF_CompanyMaster'), 'A.CompanyId=B.CompanyId', array(), $CompanySelect::JOIN_LEFT);
                        if ($CompanyId != 0) {
                            $CompanySelect->where(array("B.CompanyId" => $CompanyId));
                        }
                        $CompanyStatement = $sql->getSqlStringForSqlObject($CompanySelect);
                        $CompanyResult = $dbAdapter->query($CompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($CompanyResult));
                        return $response;
                        break;

                    case 'cost':
                        $sel = $sql->select();
                        $sel->from(array("IT" => "MMS_IssueTrans"))
                            ->columns(array(
                                'ResourceId' => new Expression('ResourceId'),
                                'ItemId' => new Expression("ItemId"),
                                'CostCentreId' => new Expression("CostCentreId"),
                                'UnitId' => new Expression("UnitId"),
                                'Issue' => new Expression(" SUM(CASE WHEN IT.IssueOrReturn='I' THEN IssueQty ELSE 0 END)"),
                                'Return' => new Expression("SUM(CASE WHEN IT.IssueOrReturn='R' THEN IssueQty ELSE 0 END)"),
                            ))
                            ->join(array('IR' => 'MMS_IssueRegister'), 'IT.IssueRegisterId=IR.IssueRegisterId', array(), $sel::JOIN_INNER)
                            ->Where (array("IR.IssueDate BETWEEN ('$fromDate') And ('$toDate')"))
                            ->group(array("ResourceId","CostCentreId","ItemId","UnitId"));

                        $sel2 = $sql->select();
                        $sel2 -> from(array("A"=>$sel))
                            ->columns(array("Return",
                                'ResourceId' => new Expression("A.ResourceId"),
                                'ItemId' => new Expression("A.ItemId"),
                                'CostCentreId' => new Expression("A.CostCentreId"),
                                'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),
                                'Resource' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End"),
                                'Unit' => new Expression("U.UnitName"),
                                'CostCentreName' => new Expression("C.CostCentreName"),
                                'Issue' => new Expression("A.Issue"),
                                'Balance' => new Expression("CAST(A.Issue-A.[Return] As Decimal(18,3))"),
                                'ResourceGroupName' => new Expression("RG.ResourceGroupName")
                            ))
                            ->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $sel::JOIN_INNER)
                            ->join(array('C' => 'WF_OperationalCostCentre'), 'C.CostCentreId =A.CostCentreId', array(), $sel::JOIN_INNER)
                            ->join(array('BR' => 'MMS_Brand'), ' A.ItemId=BR.BrandId', array(), $sel::JOIN_LEFT)
                            ->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $sel::JOIN_LEFT)
                            ->join(array('CM' => 'WF_CompanyMaster'), 'C.CompanyId=CM.CompanyId', array(), $sel::JOIN_INNER)
                            ->join(array('RG' => 'Proj_ResourceGroup'), 'B.ResourceGroupId=RG.ResourceGroupId', array(), $sel::JOIN_INNER)
                            ->Where (array(" A.CostCentreId in ($CostCentreId)"));
                        $statement = $sql->getSqlStringForSqlObject($sel2);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($arr_stock));
                        return $response;
                }
            }
        }  else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here

			}
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


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

	public function detailedIssueAction(){
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
						$select1->from(array('A' => 'MMS_IssueTrans'))
							->columns(array(
								'IssueDate'=> new Expression(' Convert(Varchar(10),C.IssueDate,103)'),
								'IssueNo'=> new Expression('C.IssueNo'),
								'Vendor'=> new Expression("ISNULL(V.VendorName,'Internal')"),
								'CostCentre'=> new Expression('CC.CostCentreName'),
								'Code'=>new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
								'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else ResourceName End'),
								'Unit'=> new Expression('UV.UnitName'),
								'IssueQty'=> new Expression('A.IssueQty'),
								'Rate'=> new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0) Else CAST(A.IssueRate As Decimal(18,2)) End'),
								'Amount'=>new Expression('Case When (A.TFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else CAST(A.IssueAmount As Decimal(18,2)) End'),
								'Remarks'=>new Expression('A.Remarks'),
								'ResourceGroup'=>new Expression('RG.ResourceGroupName'),
								'RefDate'=>new Expression('Convert(Varchar(10),C.Otherdate,103)'),
								'RefNo'=>new Expression('C.OtherNo'),
								'I/R'=>new Expression("Case When A.IssueOrReturn='I' Then 'Issue' Else 'Return' End"),
								'FreeOrCharge'=>new Expression("Case When A.FreeOrCharge='C' Then 'Chargeable' Else 'Free' End")))
								
									->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_IssueRegister'), 'A.IssueRegisterId=C.IssueRegisterId', array(), $select1::JOIN_INNER)
									->join(array('CC' => 'WF_OperationalCostCentre'),'C.CostCentreId = CC.CostCentreId',array(),$select1::JOIN_INNER)
									->join(array('V' => 'Vendor_Master'),'V.VendorId=C.ContractorId',array(),$select1::JOIN_LEFT)
									->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select1::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'),'CC.CompanyId=CM.CompanyId',array(),$select1::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select1::JOIN_INNER)
									->join(array('UV' => 'Proj_UOM'),'A.UnitId=UV.UnitId',array(),$select1::JOIN_LEFT)
							->where(array("C.IssueDate Between ('$fromDate') And ('$toDate') And C.CostCentreId IN ($CostCentreId) Order By C.IssueDate"));
							
						
						$statement = $sql->getSqlStringForSqlObject($select1); 
						$detailedissueResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($detailedissueResult));
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

	public function wbsIssueRegisterAction(){
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
						$select1->from(array('A' => 'MMS_IssueAnalTrans'))
							->columns(array(
								'IssueDate'=> new Expression(' Convert(Varchar(10),C.IssueDate,103)'),
								'PLevel'=> new Expression('D.ParentText'),
								'WbsName'=> new Expression('D.WbsName'),							
								'CostCentre'=> new Expression('G.CostCentreName'),
								'Code'=>new Expression(' Case When a.itemid>0 then f.itemcode else e.code end'),
								'Resource'=> new Expression('Case when a.itemid>0 then f.brandname else e.ResourceName end'),
								'Unit'=> new Expression('J.UnitName'),
								'IssueQty'=> new Expression('SUM(CAST(ISNULL(A.IssueQty,0) As Decimal(18,6)))'),
								'ResourceGroup'=>new Expression('SUM(CAST(ISNULL(A.IssueQty*B.IssueRate,0) As Decimal(18,2))) As Amount,I.ResourceGroupName')))
								
									->join(array('B' => 'MMS_IssueTrans'), 'A.IssueTransId=B.IssueTransId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_IssueRegister'), 'B.IssueRegisterId=c.IssueRegisterId', array(), $select1::JOIN_INNER)
									->join(array('D' => 'Proj_WbsMaster'),'A.AnalysisId=D.WbsId',array(),$select1::JOIN_INNER)
									->join(array('E' => 'Proj_Resource'),'A.ResourceId=E.ResourceId',array(),$select1::JOIN_INNER)
									->join(array('F' => 'MMS_Brand'),'A.ResourceId=F.ResourceId And A.ItemId=F.BrandID',array(),$select1::JOIN_LEFT)
									->join(array('G' => 'WF_OperationalCostCentre'),'C.CostCentreId=G.CostCentreId',array(),$select1::JOIN_INNER)
									->join(array('H' => 'WF_CompanyMaster'),'G.CompanyId=H.CompanyId',array(),$select1::JOIN_INNER)
									->join(array('I' => 'Proj_ResourceGroup'),'E.ResourceGroupId=I.ResourceGroupId',array(),$select1::JOIN_INNER)
									->join(array('J' => 'Proj_Uom'),'B.UnitId=J.UnitId',array(),$select1::JOIN_LEFT)
							->where(array("C.IssueDate Between ('$fromDate') And ('$toDate') And C.CostCentreId IN ($CostCentreId) Group By C.IssueDate,D.ParentText,D.WbsName,G.CostCentreName,a.ItemId,f.ItemCode,e.Code,f.BrandName,e.ResourceName,J.UnitName,I.ResourceGroupName"));
						
						$statement = $sql->getSqlStringForSqlObject($select1); 
						$wbsissueResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($wbsissueResult));
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

	public function issuefreeChargeableAction(){
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
						$select1->from(array('IT' => 'mms_IssueTrans'))
							->columns(array(
								'IssueDate'=> new Expression('Convert(Varchar(10),IR.IssueDate,103)'),
								'ResourceId'=> new Expression('ResourceId'),
								'ItemId'=> new Expression('ItemId'),							
								'CostCentreId'=> new Expression('CostCentreId'),
								'UnitId'=>new Expression('IT.UnitId'),
								'Chargeable'=> new Expression('SUM(IT.IssueQty)'),
								'Free'=> new Expression('CAST(0 As decimal(18,2))')))
								
									->join(array('IR' => 'mms_IssueRegister'), 'IT.IssueRegisterId=IR.IssueRegisterId', array(), $select1::JOIN_INNER)
							->where(array("IR.IssueDate Between ('$fromDate') And ('$toDate') And IR.CostCentreId IN ($CostCentreId) And IT.FreeOrCharge='C' Group By IR.IssueDate, ResourceId,ItemId,CostCentreId,UnitId"));
							
						$select2 = $sql->select();
						$select2->from(array('IT' => 'MMS_IssueTrans'))
							->columns(array(
								'IssueDate'=> new Expression('Convert(Varchar(10),IR.IssueDate,103)'),
								'ResourceId'=> new Expression('ResourceId'),
								'ItemId'=> new Expression('ItemId'),							
								'CostCentreId'=> new Expression('CostCentreId'),
								'UnitId'=>new Expression('IT.UnitId'),
								'Chargeable'=> new Expression('CAST(0 As decimal(18,2))'),
								'Free'=> new Expression('SUM(IT.IssueQty)')))
								
									->join(array('IR' => 'MMS_IssueRegister'), 'IT.IssueRegisterId=IR.IssueRegisterId', array(), $select2::JOIN_INNER)
							->where(array("IR.IssueDate Between ('$fromDate') And ('$toDate') And IR.CostCentreId IN ($CostCentreId) And IT.FreeOrCharge='F' Group By IR.IssueDate,ResourceId,ItemId,CostCentreId,UnitId"));
						$select2->combine($select1,'Union ALL');
						
						$select3 = $sql->select();
						$select3->from(array('A1' => $select2))
							->columns(array(
								'IssueDate'=> new Expression('Convert(Varchar(10),A1.IssueDate,103)'),
								'ResourceId'=> new Expression('A1.ResourceId'),
								'ItemId'=> new Expression('A1.ItemId'),							
								'CostCentreId'=> new Expression('CostCentreId'),
								'UnitId'=>new Expression('A1.UnitId'),
								'CostCentreId'=> new Expression('A1.CostCentreId'),
								'Chargeable'=> new Expression('SUM(A1.Chargeable)'),
								'Free'=> new Expression('SUM(A1.Free)')))									
						->group(new Expression("A1.IssueDate,A1.ResourceId,A1.ItemId,A1.CostCentreId,A1.UnitId"));
						
						$select4 = $sql->select();
						$select4->from(array('A' => $select3))
							->columns(array(
								'IssueDate'=> new Expression('Convert(Varchar(10),A.IssueDate,103)'),
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),							
								'Code'=> new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
								'Resource'=>new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End'),
								'CostCentreId'=> new Expression('A.CostCentreId'),
								'CostCentre'=> new Expression('C.CostCentreId'),
								'Chargeable'=> new Expression('A.Chargeable'),
								'Free'=> new Expression('A.Free'),
								'Unit'=> new Expression('U.UnitName'),
								'ResourceGroup'=> new Expression('RG.ResourceGroupName')))
								
									->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceID', array(), $select4::JOIN_INNER)
									->join(array('C' => 'WF_OperationalCostCentre'), 'C.CostCentreId =A.CostCentreId', array(), $select4::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $select4::JOIN_LEFT)
									->join(array('CM' => 'WF_CompanyMaster'), 'C.CompanyId=CM.CompanyId', array(), $select4::JOIN_INNER)
									->join(array('RG' => 'Proj_ResourceGroup'), 'B.ResourceGroupId=RG.ResourceGroupId', array(), $select4::JOIN_INNER)
									->join(array('U' => 'Proj_Uom'), 'A.UnitId=U.UnitId', array(), $select4::JOIN_LEFT)
							->where(array("A.CostCentreId IN ($CostCentreId)"));
						
						$statement = $sql->getSqlStringForSqlObject($select4);
						$freeVschargeable = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($freeVschargeable));
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

	public function issuereturnHistoryAction(){
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
						$select1->from(array('A' => 'MMS_IssueRegister'))
							->columns(array(
								'IssueRegisterId'=> new Expression('A.IssueRegisterId'),
								'IssueTransId'=> new Expression('B.IssueTransId'),
								'ResourceId'=> new Expression('B.ResourceId'),							
								'ItemId'=> new Expression('B.ItemId'),
								'IssueNo'=>new Expression('A.IssueNo'),
								'IssueDate'=> new Expression('Convert(Varchar(10),A.IssueDate,103)'),
								'CostCentre'=> new Expression('E.CostCentreName'),
								'Code'=> new Expression('Case When B.ItemId>0 Then C.ItemCode Else D.Code End'),
								'Resource'=> new Expression('Case When B.ItemId>0 Then C.BrandName Else D.ResourceName End'),
								'Unit'=> new Expression('Case When B.TUnitId>0 Then U2.UnitName Else U1.UnitName End'),
								'IssueQty'=> new Expression('B.IssueQty'),
								'ReturnQty'=> new Expression('B.ReturnQty'),
								'AdjustmentQty'=> new Expression('B.AdjustmentQty'),
								'BalQty'=> new Expression('(B.IssueQty-(B.ReturnQty+B.AdjustmentQty))')))
								
									->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId=B.IssueRegisterId', array(), $select1::JOIN_INNER)
									->join(array('C' => 'MMS_Brand'), 'B.ResourceId=C.ResourceId And B.ItemId=C.BrandId', array(), $select1::JOIN_LEFT)
									->join(array('D' => 'Proj_Resource'), 'B.ResourceId=D.ResourceID', array(), $select1::JOIN_INNER)
									->join(array('E' => 'WF_OperationalCostCentre'), 'A.CostCentreId=E.CostCentreId', array(), $select1::JOIN_INNER)
									->join(array('U1' => 'Proj_UOM'), 'B.UnitId=U1.UnitId', array(), $select1::JOIN_LEFT)
									->join(array('U2' => 'Proj_UOM'), 'B.TUnitId=U2.UnitId', array(), $select1::JOIN_LEFT)
							->where(array("A.IsReturnable=1 And A.IssueOrReturn=0 And A.IssueDate BetWeen ('$fromDate') And ('$toDate') And A.CostCentreId IN ($CostCentreId)"));						
						$statement = $sql->getSqlStringForSqlObject($select1);
						$returnhistoryResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($returnhistoryResult));
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