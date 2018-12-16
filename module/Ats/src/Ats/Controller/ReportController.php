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

	public function reportlistAction(){
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

	public function pendingrequestAction(){
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
				$postParams = $request->getPost();
				
				$CostCentreId =$this->params()->fromPost('CostCentreId');
				if($CostCentreId == ""){
					$CostCentreId =0;
				}else{
                    $CostCentreId = trim(implode(',',$this->params()->fromPost('CostCentreId')));
                }
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string'); 
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
				
                if($RequestType == 'Material' && $RequestType != ''){ 
                    $RequestType=2;
                } 
				if($RequestType == 'Asset' && $RequestType != ''){
					$RequestType=3;
				}
				if($RequestType == 'Labour' && $RequestType != ''){
					$RequestType=1;
				}
				if($RequestType == 'Activity' && $RequestType != ''){
					$RequestType=4;
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
					$select = $sql->select();
					$select->from(array('A' => 'VM_RequestTrans'))
						->columns(array(
							'RequestDate'=> new Expression('Convert(Varchar(10),C.RequestDate,103)'),
							'RequestNo'=> new Expression('C.RequestNo'),
							'RefNo'=> new Expression('C.RefNo'),
							'CostCentre'=> new Expression('CC.CostCentreName'),
							'Code'=>new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
							'ResourceGroup'=> new Expression('RG.ResourceGroupName'),
							'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End'),
							'Specification'=> new Expression('A.Remarks'),
							'Unit'=> new Expression('UN.UnitName '),
							'ReqQty'=>new Expression('CAST(A.Quantity as Decimal(18,2))'),
							'POQty'=>new Expression('CAST(A.IndentQty as Decimal(18,2))'),
							'TransferQty'=>new Expression('CAST(A.TransferQty as Decimal(18,2))'),
							'BalQty'=>new Expression('CAST(A.BalQty as Decimal(18,2))'),
							'SiteStock'=>new Expression('CAST(ST.ClosingStock as Decimal(18,2))')))
							
								->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
								->join(array('C' => 'VM_RequestRegister'), 'A.RequestId=C.RequestId', array(), $select::JOIN_INNER)
								->join(array('CC' => 'WF_OperationalCostCentre'),'C.CostCentreId=CC.CostCentreId',array(),$select::JOIN_INNER)
								->join(array('BR' => 'MMS_Brand'),'A.ItemId=BR.BrandId',array(),$select::JOIN_LEFT)
								->join(array('RG' => 'Proj_ResourceGroup'),'B.ResourceGroupId=RG.ResourceGroupId',array(),$select::JOIN_INNER)
								->join(array('ST' => 'MMS_Stock'),'A.ResourceId=ST.ResourceId and A.ItemId=ST.ItemId and C.CostCentreId=ST.CostCentreId',array(),$select::JOIN_INNER)
								->join(array('UN' => 'Proj_Uom'),'A.UnitId=UN.UnitId',array(),$select::JOIN_LEFT)
						->where(" A.BalQty>0 And C.RequestDate Between ('$fromDate') And ('$toDate') And C.Approve='Y' And B.TypeId= $RequestType And CC.CostCentreId IN ($CostCentreId)");
				 	$statement = $statement = $sql->getSqlStringForSqlObject($select); 
					$PendingRequest = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
				$this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($PendingRequest));
                return $response;
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

	public function detailedRequestRegisterAction(){
	
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
			$DetailedRequest = array();
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
				$CostCentreId =$this->params()->fromPost('CostCentreId');
				if($CostCentreId == ""){
					$CostCentreId =0;
				}else{
                    $CostCentreId = trim(implode(',',$this->params()->fromPost('CostCentreId')));
                }
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string'); 
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
				
				$select = $sql->select();
				$select->from(array('A' => 'VM_RequestRegister'))
					->columns(array(
						'RequestId'=> new Expression('A.RequestId'),
						'RequestDate'=> new Expression('Convert(Varchar(10),A.RequestDate,103)'),
						'RequestNo'=> new Expression('A.RequestNo'),
						'RefNo'=> new Expression('A.RefNo '),
						'CostCentre'=> new Expression('B.CostCentreName')))	
					->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array(), $select::JOIN_INNER)
					->where("A.RequestDate Between ('$fromDate') And ('$toDate') And A.RequestType='$RequestType' And A.CostCentreId IN ($CostCentreId)");
				$statement = $statement = $sql->getSqlStringForSqlObject($select); 
				$DetailedRequest['resource'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				$DetailedRequest['decision'] = array();
                $DetailedRequest['project'] = array();
				
				if($RequestType == 'Material' && $RequestType != ''){ 
                    $RequestType=2;
                } 
				if($RequestType == 'Asset' && $RequestType != ''){
					$RequestType=3;
				}
				if($RequestType == 'Labour' && $RequestType != ''){
					$RequestType=1;
				}
				if($RequestType == 'Activity' && $RequestType != ''){
					$RequestType=4;
				}
				
				foreach ($DetailedRequest['resource'] as $DetailedRequest1) {
					$select = $sql->select();
					$select->from(array('A' => 'VM_RequestRegister'))
						->columns(array(
							'RequestId'=> new Expression('A.RequestId'),
							'Code'=>new Expression('Case When B.ItemId <> 0 Then BR.ItemCode Else C.Code End'),
							'Resource'=> new Expression('Case When B.ItemId <> 0 Then BR.BrandName Else C.ResourceName End'),
							'Specification'=> new Expression('B.Remarks'),
							'Unit'=> new Expression('U.UnitName'),
							'Qty'=> new Expression('CAST(B.Quantity As Decimal(18,3))'),
							'ReqDate'=> new Expression('Convert(Varchar(10),B.ReqDate,103)'),
							'ResourceGroup'=> new Expression('RG.ResourceGroupName')))
							
							->join(array('B' => 'VM_RequestTrans'), 'A.RequestId=B.RequestId ', array(), $select::JOIN_INNER)
							->join(array('C' => 'Proj_Resource'), 'B.ResourceId=C.ResourceId', array(), $select::JOIN_INNER)
							->join(array('G' => 'WF_OperationalCostCentre'),'A.CostCentreId=G.CostCentreId',array(),$select::JOIN_INNER)
							->join(array('BR' => 'MMS_Brand'),'B.ResourceId=BR.ResourceId And B.ItemId=BR.BrandId ',array(),$select::JOIN_LEFT)
							->join(array('U' => 'Proj_UOM'),'C.UnitId=U.UnitId ',array(),$select::JOIN_LEFT)
							->join(array('RG' => 'Proj_ResourceGroup'),'C.ResourceGroupId=RG.ResourceGroupId ',array(),$select::JOIN_INNER)
						->where(array('A.RequestId' => $DetailedRequest1['RequestId'],"A.RequestDate Between ('$fromDate') And ('$toDate') And C.TypeId=$RequestType And A.CostCentreId IN ($CostCentreId)"));
					$statement = $statement = $sql->getSqlStringForSqlObject($select);
					$Request = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
					foreach ($Request as $RequestRegister) {
						array_push($DetailedRequest['project'], $RequestRegister);
					}
					array_push($DetailedRequest['decision'], $DetailedRequest1);
				}
			}
			
			$this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($DetailedRequest));
            return $response;
			
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
			
			
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function requestHistoryAction(){
	
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
			$resp = array();
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
				$CostCentreId =$this->params()->fromPost('CostCentreId');
				if($CostCentreId == ""){
					$CostCentreId =0;
				}else{
                    $CostCentreId = trim(implode(',',$this->params()->fromPost('CostCentreId')));
                }
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string'); 
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
				$select = $sql->select();
				$select->from(array('A' => 'VM_RequestRegister'))
					->columns(array(
						'RequestId'=> new Expression('A.RequestId'),
						'RequestDate'=> new Expression('Convert(Varchar(10),A.RequestDate,103)'),
						'RequestNo'=> new Expression('A.RequestNo'),
						'RefNo'=> new Expression('A.RefNo '),
						'CostCentre'=> new Expression('B.CostCentreName')))	
					->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array(), $select::JOIN_INNER)
					->where("A.RequestDate Between ('$fromDate') And ('$toDate') And A.RequestType='$RequestType' And A.CostCentreId IN ($CostCentreId)");
				$statement = $statement = $sql->getSqlStringForSqlObject($select);
				$resp['resource'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				$resp['decision'] = array();
                $resp['project'] = array();
                $project = array();
                $resp['wbs'] = array();
				$wbsData = array();
				
				if($RequestType == 'Material' && $RequestType != ''){ 
                    $RequestType=2;
                } 
				if($RequestType == 'Asset' && $RequestType != ''){
					$RequestType=3;
				}
				if($RequestType == 'Labour' && $RequestType != ''){
					$RequestType=1;
				}
				if($RequestType == 'Activity' && $RequestType != ''){
					$RequestType=4;
				}
				
				foreach($resp['resource'] as $res){
                    $decisionSelect = $sql->select();
                    $decisionSelect->from(array("A" => "VM_RequestTrans"))
                        ->columns(array(
							'RequestId'=> new Expression('A.RequestId'),
							'RequestTransId'=> new Expression('A.RequestTransId'),
							'Code'=>new Expression('Case When A.ItemId>0 Then D.ItemCode Else C.Code End'),
							'Resource'=> new Expression('Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End'),
							'Unit'=> new Expression('E.UnitName'),
							'ReqQty'=> new Expression('CAST(A.Quantity As Decimal(18,2))'),
							'POQty'=> new Expression('CAST(A.IndentQty As Decimal(18,2))'),
							'TransferQty'=> new Expression('CAST(A.TransferQty As Decimal(18,2))'),
							'BalQty'=> new Expression('CAST(A.BalQty As Decimal(18,2))')))
							
							->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array(), $decisionSelect::JOIN_INNER)
							->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceId', array(), $decisionSelect::JOIN_INNER)
							->join(array('D' => 'MMS_Brand'),' A.ItemId=D.BrandId And A.ResourceId=D.ResourceId',array(),$decisionSelect::JOIN_LEFT)
							->join(array('E' => 'Proj_UOM'),'A.UnitId=E.UnitId',array(),$decisionSelect::JOIN_LEFT)
						->where(array('A.RequestId' => $res['RequestId'],"B.RequestDate Between ('$fromDate') And ('$toDate') And C.TypeId=$RequestType And B.CostCentreId IN ($CostCentreId)"));
                    $decisionStatement = $sql->getSqlStringForSqlObject($decisionSelect);
                    $decisionResult = $dbAdapter->query($decisionStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			
					foreach($decisionResult as $dec){
						$projectSelect = $sql->select();
						$projectSelect->from(array("A" => "VM_RequestDecision"))
						   ->columns(array(
								'RequestTransId'=> new Expression('Distinct D.RequestTransId'),
								'DecisionId'=>new Expression('B.DecisionId'),
								'DecTransId'=> new Expression('C.TransId'),
								'DecisionNo'=> new Expression('A.RDecisionNo'),
								'DecisionDate'=> new Expression('Convert(Varchar(10),A.DecDate,103)')))
								
								->join(array('B' => 'VM_ReqDecTrans'), ' A.DecisionId=B.DecisionId', array(), $projectSelect::JOIN_INNER)
								->join(array('C' => 'VM_ReqDecQtyTrans'), ' B.DecisionId=C.DecisionId', array(), $projectSelect::JOIN_INNER)
								->join(array('D' => 'VM_RequestTrans'),' C.ReqTransId=D.RequestTransId',array(),$projectSelect::JOIN_INNER)
								->join(array('E' => 'VM_RequestRegister'),'D.RequestId=E.RequestId',array(),$projectSelect::JOIN_INNER)
							->where(array('D.RequestTransId' => $dec['RequestTransId'],"E.RequestDate Between ('$fromDate') And ('$toDate') And A.RequestType=$RequestType And E.CostCentreId IN ($CostCentreId)"));
						$projectStatement = $sql->getSqlStringForSqlObject($projectSelect);
						$projectResult = $dbAdapter->query($projectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						
						foreach($projectResult as $cost){
							$select1 = $sql->select();
							$select1->from(array("A"=>"MMS_POTrans"))
								->columns(array(
									'DecTransId'=> new Expression('D.DecTransId'),
									'DecisionId'=>new Expression('D.DecisionId'),
									'TransNo'=> new Expression('C.PONo'),
                                    'CostCentre'=>new Expression('J.CostCentreName'),
									'TransDate'=> new Expression('CONVERT(Varchar(10),C.PODate,103)'),
									'Qty'=> new Expression(' CAST((D.Qty-D.CancelQty) As Decimal(18,2))'),
									'EntryType'=> new Expression("'PO'")))
									
									->join(array("B"=>"MMS_POProjTrans"), "A.POTransId=B.POTransId", array(), $select1::JOIN_INNER)
									->join(array("C"=>"MMS_PORegister"), "A.PORegisterId=C.PORegisterId", array(), $select1::JOIN_INNER)
									->join(array("D"=>"MMS_IPDTrans"), " A.POTransId=D.POTransId ", array(), $select1::JOIN_INNER)
									->join(array("E"=>"VM_ReqDecQtyTrans"), "D.DecTransId=E.TransId And D.DecisionId=E.DecisionId", array(), $select1::JOIN_INNER)
									->join(array("F"=>"VM_RequestTrans"), "E.ReqTransId=F.RequestTransId ", array(), $select1::JOIN_INNER)
									->join(array("G"=>"VM_RequestRegister"), "F.RequestId=G.RequestId", array(), $select1::JOIN_INNER)
                                    ->join(array("J" =>"WF_OperationalCostCentre"),'J.CostCentreId=B.CostCentreId',array(),$select1::JOIN_INNER)
							   ->where(array('D.DecTransId' => $cost['DecTransId'],"G.RequestDate Between ('$fromDate') And ('$toDate') And C.LivePO= 1 And B.CostCentreId IN ($CostCentreId) And D.Status='P'"));
							   
							$wbsSelect= $sql->select();
							$wbsSelect->from(array("A" => "MMS_TransferTrans"))
							   ->columns(array(
									'DecTransId'=> new Expression('D.DecTransId'),
									'DecisionId'=>new Expression('D.DecisionId'),
                                    'TransNo'=> new Expression('B.TVNo'),
									'CostCentre'=>new Expression('J.CostCentreName'),
									'TransDate'=> new Expression('CONVERT(Varchar(10),B.TVDate,103)'),
									'Qty'=> new Expression(' CAST((D.Qty-D.CancelQty) As Decimal(18,2))'),
									'EntryType'=> new Expression("'Transfer'")))
									->join(array('B' => 'MMS_TransferRegister'), ' A.TransferRegisterId=B.TVRegisterId ', array(), $wbsSelect::JOIN_INNER)
									->join(array('D' => 'MMS_IPDTrans'), 'A.TransferTransId=D.TransferTransId ', array(), $wbsSelect::JOIN_INNER)
									->join(array('E' => 'VM_ReqDecQtyTrans'),'D.DecTransId=E.TransId And D.DecisionId=E.DecisionId',array(),$wbsSelect::JOIN_INNER)
									->join(array('F' => 'VM_RequestTrans'),'E.ReqTransId=F.RequestTransId ',array(),$wbsSelect::JOIN_INNER)
									->join(array('G' => 'VM_RequestRegister'),'F.RequestId=G.RequestId',array(),$wbsSelect::JOIN_INNER)
									->join(array('J' => 'WF_OperationalCostCentre'),'J.CostCentreId=B.FromCostCentreId',array(),$wbsSelect::JOIN_INNER)
								->where(array('D.DecTransId' => $cost['DecTransId'],"G.RequestDate Between ('$fromDate') And ('$toDate') And B.ToCostCentreId IN ($CostCentreId) And D.Status='T'"));
							$wbsSelect->combine($select1,'Union ALL');
							$wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
							$wbsResult = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
							array_push($resp['project'], $cost);
							foreach($wbsResult as $wbs){
								array_push($resp['wbs'], $wbs);
							}
						}
						array_push($resp['decision'], $dec);
                    }
                }
				
			}
           // print_r($resp); die;
			$this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($resp));
            return $response;
			
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

	public function requestWbsRegisterAction(){
	
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
				
				$CostCentreId =$this->params()->fromPost('CostCentreId');
				if($CostCentreId == ""){
					$CostCentreId =0;
				}else{
                    $CostCentreId = trim(implode(',',$this->params()->fromPost('CostCentreId')));
                }
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string'); 
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');

                if($RequestType == 'Material' && $RequestType != ''){ 
                    $RequestType=2;
                } 
				if($RequestType == 'Asset' && $RequestType != ''){
					$RequestType=3;
				}
				if($RequestType == 'Labour' && $RequestType != ''){
					$RequestType=1;
				}
				if($RequestType == 'Activity' && $RequestType != ''){
					$RequestType=4;
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
					$select = $sql->select();
					$select->from(array('A' => 'VM_RequestAnalTrans'))
						->columns(array(
							'RequestDate'=> new Expression('Convert(Varchar(10),C.RequestDate,103)'),
							'RequestNo'=> new Expression('C.RequestNo'),
							'Priority'=> new Expression("Case When C.Priority= '3' Then 'High' When C.Priority='1' Then 'Low' Else 'Medium' End"),
							'WBSName'=> new Expression("D.ParentText+'->'+D.WBSName"),
							'CostCentre'=>new Expression('H.CostCentreName'),
							'ResourceGroup'=> new Expression('G.ResourceGroupName'),
							'Code'=> new Expression('Case When A.ItemId>0 Then F.ItemCode Else E.Code End'),
							'Resource'=> new Expression('Case When A.ItemId>0 Then F.BrandName Else E.ResourceName End'),
							'Remarks'=> new Expression('B.Remarks'),
							'Specification'=> new Expression('B.Specification'),
							'Unit'=>new Expression('I.UnitName'),
							'Qty'=>new Expression('SUM(ISNULL(A.ReqQty,0))'),
							'ReqDate'=>new Expression('Convert(Varchar(10),B.ReqDate,103)')))
							
								->join(array('B' => 'VM_RequestTrans'), 'A.ReqTransId=B.RequestTransId', array(), $select::JOIN_INNER)
								->join(array('C' => 'VM_RequestRegister'), ' B.RequestId=C.RequestId', array(), $select::JOIN_INNER)
								->join(array('D' => 'Proj_WBSMaster'),'A.AnalysisId=D.WBSId',array(),$select::JOIN_INNER)
								->join(array('E' => 'Proj_Resource'),'A.ResourceId=E.ResourceId',array(),$select::JOIN_INNER)
								->join(array('F' => 'MMS_Brand'),'A.ItemId=F.BrandId And A.ResourceId=F.ResourceId',array(),$select::JOIN_LEFT)
								->join(array('G' => 'Proj_ResourceGroup'),'E.ResourceGroupId=G.ResourceGroupId',array(),$select::JOIN_LEFT)
								->join(array('H' => 'WF_OperationalCostCentre'),'C.CostCentreId=H.CostCentreId',array(),$select::JOIN_INNER)
								->join(array('I' => 'Proj_UOM'),'B.UnitId=I.UnitId',array(),$select::JOIN_LEFT)
						->where("C.RequestDate Between ('$fromDate') And ('$toDate') And E.TypeId= $RequestType And C.CostCentreId IN ($CostCentreId)");
					$select->group(new Expression("C.RequestDate,C.RequestNo,C.Priority,D.ParentText,D.WBSName,H.CostCentreName,G.ResourceGroupName,A.ItemId,F.ItemCode,E.Code,F.BrandName,E.ResourceName,B.Remarks,B.Specification,I.UnitName,B.ReqDate"));	
					$statement = $statement = $sql->getSqlStringForSqlObject($select);
					$WBSregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
				$this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($WBSregister));
                return $response;
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

	public function requestWbsPendingAction(){
	
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
				$postParams = $request->getPost();
				
				$CostCentreId =$this->params()->fromPost('CostCentreId');
				if($CostCentreId == ""){
					$CostCentreId =0;
				}else{
                    $CostCentreId = trim(implode(',',$this->params()->fromPost('CostCentreId')));
                }
				
                $RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string'); 
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');

                if($RequestType == 'Material' && $RequestType != ''){ 
                    $RequestType=2;
                } 
				if($RequestType == 'Asset' && $RequestType != ''){
					$RequestType=3;
				}
				if($RequestType == 'Labour' && $RequestType != ''){
					$RequestType=1;
				}
				if($RequestType == 'Activity' && $RequestType != ''){
					$RequestType=4;
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
					$select = $sql->select();
					$select->from(array('A' => 'VM_RequestAnalTrans'))
						->columns(array(
							'RequestDate'=> new Expression('Convert(Varchar(10),C.RequestDate,103)'),
							'RequestNo'=> new Expression('C.RequestNo'),
							'Priority'=> new Expression("Case When C.Priority='3' Then 'High' When C.Priority='1' Then 'Low' Else 'Medium' End"),
							'WBSName'=> new Expression("D.ParentText+'->'+D.WBSName"),
							'CostCentre'=>new Expression('H.CostCentreName'),
							'ResourceGroup'=> new Expression('G.ResourceGroupName'),
							'Code'=> new Expression('Case When A.ItemId>0 Then F.ItemCode Else E.Code End'),
							'Resource'=> new Expression('Case When A.ItemId>0 Then F.BrandName Else E.ResourceName End'),
							'Remarks'=> new Expression('B.Remarks'),
							'Specification'=> new Expression('B.Specification'),
							'Unit'=>new Expression('I.UnitName'),
							'ReqDate'=>new Expression('Convert(Varchar(10),B.ReqDate,103)'),
							'Qty'=>new Expression('CAST(SUM(ISNULL(A.ReqQty,0)) As Decimal(18,2))'),
							'POQty'=>new Expression('CAST(SUM(ISNULL(A.IndentQty,0)) As Decimal(18,2))'),
							'TransferQty'=>new Expression('CAST(SUM(ISNULL(A.TransferQty,0)) As Decimal(18,2))'),
							'BalQty'=>new Expression('CAST(SUM(ISNULL(A.BalQty,0)) As Decimal(18,2))')))
							
								->join(array('B' => 'VM_RequestTrans'), 'A.ReqTransId=B.RequestTransId', array(), $select::JOIN_INNER)
								->join(array('C' => 'VM_RequestRegister'), ' B.RequestId=C.RequestId', array(), $select::JOIN_INNER)
								->join(array('D' => 'Proj_WBSMaster'),'A.AnalysisId=D.WBSId',array(),$select::JOIN_INNER)
								->join(array('E' => 'Proj_Resource'),'A.ResourceId=E.ResourceId',array(),$select::JOIN_INNER)
								->join(array('F' => 'MMS_Brand'),'A.ItemId=F.BrandId And A.ResourceId=F.ResourceId',array(),$select::JOIN_LEFT)
								->join(array('G' => 'Proj_ResourceGroup'),'E.ResourceGroupId=G.ResourceGroupId',array(),$select::JOIN_LEFT)
								->join(array('H' => 'WF_OperationalCostCentre'),'C.CostCentreId=H.CostCentreId',array(),$select::JOIN_INNER)
								->join(array('I' => 'Proj_UOM'),'B.UnitId=I.UnitId',array(),$select::JOIN_LEFT)
						->where("C.RequestDate Between ('$fromDate') And ('$toDate') And E.TypeId= $RequestType And C.CostCentreId IN ($CostCentreId)");
					$select->group(new Expression("C.RequestDate,C.RequestNo,C.Priority,D.ParentText,D.WBSName,H.CostCentreName,G.ResourceGroupName,A.ItemId,F.ItemCode,E.Code,F.BrandName,E.ResourceName,B.Remarks,B.Specification,I.UnitName,B.ReqDate HAVING CAST(SUM(ISNULL(A.BalQty,0)) As Decimal(18,2))>0"));	
					$statement = $statement = $sql->getSqlStringForSqlObject($select); 
					$WBSpending = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
				$this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($WBSpending));
                return $response;
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
	
	public function designAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Ats");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $UserId; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/design/requestregister/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("ats/default", array("controller" => "index","action" => "display-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/design/requestregister/template.phtml';
                }				
            }
            $this->_view->type = $type;
            $template = file_get_contents($filePath);
            $this->_view->template = $template;

            // csrf Key
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        }
        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
	
	public function designfooterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Ats");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/designfooter/requestregister/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("ats/default", array("controller" => "index","action" => "display-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/designfooter/requestregister/footertemplate.phtml';
                }				
            }
            $this->_view->type = $type;
            $footertemplate = file_get_contents($filePath);
            $this->_view->footertemplate = $footertemplate;

            // csrf Key
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        }
        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
}