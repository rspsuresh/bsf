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

class StockreportController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
		$this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function stockDetailsAction(){
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
				$RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string'); 
				$CostCentreId =$this->params()->fromPost('CostCentreId');
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');

                if($fromDat == ''){
                    $fromDat =  0;
                }         
                if($fromDat == 0) {
                    $fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
                }
                else
                {
                    $fromDate=date('Y-m-d',strtotime($fromDat));
                }

                switch($Type) {  			        
               
                    case 'cost':
					if($RequestType==1){
	
						$select1 = $sql->select();
						$select1->from(array("A" => "MMS_PVTrans"))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Qty' => new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
									'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId))  
															   Then Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
															   Else SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) End 
															   Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) 
															   Else A.QRate End) Else SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) End End"),
								))
								->join(array('PG' => 'MMS_PVGroupTrans'), 'A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId', array(), $select1::JOIN_INNER)
								->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select1::JOIN_INNER)
								->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select1::JOIN_INNER)
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select1::JOIN_INNER)
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select1::JOIN_LEFT)
								->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select1::JOIN_INNER)
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select1::JOIN_INNER)
							->where (array("A.BillQty>0 AND B.PVDate<='$fromDate'  AND B.CostCentreId=$CostCentreId GROUP BY A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,OC.SEZProject,PG.FFactor,PG.TFactor,SM.StateId,CM.StateId"));                    

						$select2 = $sql->select();
						$select2->from(array("A" => "MMS_DCTrans"))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Qty' => new Expression("SUM(A.BalQty)"),
									'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) 
								   Then CAST(SUM(A.BalQty*Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((A.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.GrossRate End) As Decimal(18,3))  
								   Else CAST(SUM(A.BalQty*Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else 
								   A.QRate End) As Decimal(18,3)) End"),
								))
								->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select2::JOIN_INNER)
								->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select2::JOIN_INNER)
								->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select2::JOIN_INNER)
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select2::JOIN_INNER)
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select2::JOIN_LEFT)
								->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select2::JOIN_INNER)
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select2::JOIN_INNER)
							->where (array("A.BalQty>0 AND B.DCDate<='$fromDate' AND  B.CostCentreId=$CostCentreId And DcOrCSM=1 GROUP BY A.ResourceId,A.ItemId,B.PurchaseTypeId,OC.SEZProject,DG.FFactor,DG.TFactor,SM.StateId,CM.StateId"));                           
						$select2->combine($select1,'Union ALL');
						
						$select3 = $sql->select();
						$select3->from(array("A" => "MMS_TransferTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.TransferQty)"),
									'Amount' => new Expression("SUM(-A.Amount)"),
								))
								->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select3::JOIN_INNER)
							->where (array("A.TransferQty>0 AND  B.TVDate <='$fromDate' AND B.FromCostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select3->combine($select2,'Union ALL');
						
						$select4 = $sql->select();
						$select4->from(array("A" => "MMS_TransferTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(A.RecdQty)"),
									'Amount' => new Expression("SUM(A.RecdQty*A.Rate)"),
								))
								->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select4::JOIN_INNER)
							->where (array("A.RecdQty>0 AND B.TVDate <='$fromDate' AND B.ToCostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select4->combine($select3,'Union ALL');
						
						$select5 = $sql->select();
						$select5->from(array("A" => "MMS_PRTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.ReturnQty)"),
									'Amount' => new Expression("SUM(-Amount)"),
								))
								->join(array('B' => 'MMS_PRRegister'), 'B.PRRegisterId = A.PRRegisterId', array(), $select5::JOIN_INNER)
							->where (array("A.ReturnQty>0 AND B.PRDate <='$fromDate' AND B.CostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select5->combine($select4,'Union ALL');
						
						$select6 = $sql->select();
						$select6->from(array("A" => "MMS_IssueTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.IssueQty)"),
									'Amount' => new Expression("SUM(-Case When (A.TFactor>0 And A.FFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End)"),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select6::JOIN_INNER)
							->where (array("A.IssueQty>0 AND B.IssueDate <='$fromDate' AND B.CostCentreId=$CostCentreId And A.IssueOrReturn='I' GROUP BY ResourceId,ItemId "));
						$select6->combine($select5,'Union ALL');
						
						$select7 = $sql->select();
						$select7->from(array("A" => "MMS_IssueTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(A.IssueQty)"),
									'Amount' => new Expression("SUM(IssueAmount)"),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select7::JOIN_INNER)
							->where (array("A.IssueQty>0 AND B.IssueDate <='$fromDate'AND B.CostCentreId=$CostCentreId  And A.IssueOrReturn='R' GROUP BY ResourceId,ItemId "));                       
						$select7->combine($select6,'Union ALL');
						
						$select8 = $sql->select();
						$select8->from("MMS_Stock")
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("OpeningStock"),
									'Amount' => new Expression("OpeningStock*ORate"),
								))
							->where (array("OpeningStock>0 AND CostCentreId=$CostCentreId"));
						$select8->combine($select7,'Union ALL');
						
						$select9 = $sql->select();
						$select9->from(array("A" => $select8))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(Qty)"),
									'Amount' => new Expression("SUM(Amount)"),
								));                    
						$select9->group(array (new Expression("ResourceId,ItemId")));
						
						$select10 = $sql->select();
						$select10->from(array("A" => $select9))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'AvgRate' => new Expression("(Case When Amount > 0 Then Amount Else null End/ Case When Qty > 0 Then Qty Else null End)"),
								)); 

						$select11 = $sql->select();
						$select11->from(array("S" => "MMS_Stock"))
								->columns(array(
									'ResourceId' => new Expression('S.ResourceId'),
									'ItemId' => new Expression("S.ItemId"),
									'CostCentreId' => new Expression("S.CostCentreId"),
									'Qty' => new Expression("S.OpeningStock"),						
								))													
							->where (array("S.OpeningStock > 0 AND S.CostCentreId=$CostCentreId")); 
							
						$select12 = $sql->select();
						$select12->from(array("A" => "MMS_PVRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End"),						
								))		
								->join(array('B' => 'MMS_PVTrans'), 'A.PVRegisterId=B.PVRegisterId', array(), $select12::JOIN_INNER)
							->where (array("B.BillQty > 0 AND A.PVDate<='$fromDate'  AND A.CostCentreId=$CostCentreId")); 	
						$select12->combine($select11,'Union ALL');
						
						$select13 = $sql->select();
						$select13->from(array("A" => "MMS_DCRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("B.BalQty"),						
								))		
								->join(array('B' => 'MMS_DCTrans'), 'A.DCRegisterId=B.DCRegisterId', array(), $select13::JOIN_INNER)
							->where (array("B.BalQty > 0  AND A.DCDate<='$fromDate' AND A.CostCentreId=$CostCentreId And DcOrCSM=1")); 	
						$select13->combine($select12,'Union ALL');
						
						$select14 = $sql->select();
						$select14->from(array("A" => "MMS_TransferRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.FromCostCentreId"),
									'Qty' => new Expression("-B.TransferQty"),						
								))		
								->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select14::JOIN_INNER)
							->where (array("B.TransferQty > 0  AND A.TVDate <='$fromDate'AND A.FromCostCentreId=$CostCentreId")); 	
						$select14->combine($select13,'Union ALL');
						
						$select15 = $sql->select();
						$select15->from(array("A" => "MMS_TransferRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.ToCostCentreId"),
									'Qty' => new Expression("B.RecdQty"),						
								))		
								->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select15::JOIN_INNER)
							->where (array("B.RecdQty > 0 AND A.TVDate <='$fromDate' AND A.ToCostCentreId=$CostCentreId")); 	
						$select15->combine($select14,'Union ALL');
						
						$select16 = $sql->select();
						$select16->from(array("A" => "MMS_PRRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("-B.ReturnQty"),						
								))		
								->join(array('B' => 'MMS_PRTrans'), 'A.PRRegisterId = B.PRRegisterId', array(), $select16::JOIN_INNER)
							->where (array("B.ReturnQty > 0 AND A.PRDate <='$fromDate' AND A.CostCentreId=$CostCentreId")); 	
						$select16->combine($select15,'Union ALL');
						
						$select17 = $sql->select();
						$select17->from(array("A" => "MMS_IssueRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("-B.IssueQty"),						
								))		
								->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select17::JOIN_INNER)
							->where (array("B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<='$fromDate'  AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 ")); 	
						$select17->combine($select16,'Union ALL');
						
						$select18 = $sql->select();
						$select18->from(array("A" => "MMS_IssueRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("B.IssueQty"),						
								))		
								->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select18::JOIN_INNER)
							->where (array("B.IssueOrReturn='R' And B.IssueQty > 0  AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0")); 	
						$select18->combine($select17,'Union ALL');
						
						$select19 = $sql->select();
						$select19->from(array("A" => $select18))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),									
									'Qty' => new Expression("SUM(Qty)"),						
									'CostCentreId' => new Expression("A.CostCentreId"),						
								))										
							->where (array("A.CostCentreId=$CostCentreId GROUP BY A.ResourceId,A.CostCentreId,A.ItemId")); 	

						$select20 = $sql->select();
						$select20->from(array("A" => $select19))
								->columns(array(
									'ResourceGroupId' => new Expression('Distinct ISNULL(RG.ResourceGroupId,0)'),
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),
									'ResourceGroupName' => new Expression("RG.ResourceGroupName"),
									'Resource' => new Expression("B.ResourceName"),
									'ItemName' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else '' End"),
									'Unit' => new Expression("B1.UnitName"),
									'Op.Balance' => new Expression("CAST(SK.OpeningStock As Decimal(18,5))"),
									'Receipt' => new Expression("CAST(( ISNULL((SELECT SUM(B.BalQty)  FROM MMS_DCRegister A  
																 INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId Where B.AcceptQty > 0  AND A.DCDate<='$fromDate' 
																 AND A.CostCentreId=$CostCentreId And DcOrCSM=1 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) + 
																 ISNULL((SELECT  SUM(B.ActualQty)  FROM MMS_PVRegister A   
																 INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																 WHERE  B.BillQty > 0 AND A.PVDate<='$fromDate'  AND A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																 And B.DCRegisterId>0 And B.DCTransId>0 ),0) + ISNULL((SELECT  SUM(B.BillQty)  FROM MMS_PVRegister A   
																 INNER JOIN MMS_PVTrans B   On A.PVRegisterId=B.PVRegisterId 
																 WHERE  B.BillQty > 0 AND A.PVDate<='$fromDate'  AND A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																 And B.DCRegisterId=0 And B.DCTransId=0 ),0))  As Decimal(18,5))"),
									'BillReturn' => new Expression("CAST(ISNULL((SELECT SUM(-B.ReturnQty) FROM MMS_PRRegister A   
																	 INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId 
																	 WHERE B.ReturnQty > 0 AND A.PRDate <='$fromDate' AND A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId ),0) As Decimal(18,5)) "),
									'Transfer' => new Expression("(CAST(ISNULL((SELECT SUM(-B.TransferQty)  FROM MMS_TransferRegister A  
																 INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId 
																 WHERE B.TransferQty > 0 AND A.TVDate <='$fromDate' AND A.FromCostCentreId=$CostCentreId AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) As Decimal(18,5)) + 
																 CAST(ISNULL((SELECT  SUM(B.RecdQty) FROM MMS_TransferRegister A   
																 INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId 
																 WHERE B.RecdQty > 0 AND A.TVDate <='$fromDate' AND A.ToCostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ),0) As Decimal(18,5)) )"),
									'Issue/Return' => new Expression("(CAST(ISNULL(( SELECT SUM(-B.IssueQty) FROM MMS_IssueRegister A   
																	 INNER JOIN MMS_IssueTrans B   On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' 
																	 And B.IssueQty > 0 AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId
																	  And B.ItemId=C.ItemId ),0) As Decimal(18,5)) + CAST(ISNULL(( SELECT SUM(B.IssueQty) FROM MMS_IssueRegister A    
																	  INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0  
																	  AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) As Decimal(18,5)))"),
									'Net' => new Expression("CAST(Qty AS Decimal(18,5))"),
									'AvgRate' => new Expression("CAST (ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
									'AvgAmount' => new Expression("CAST(Qty*ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
									'LRate' => new Expression("SK.LRate"),
									'LAmount' => new Expression("CAST(Qty*ISNULL(SK.LRate,0) As Decimal(18,3))"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Child' => new Expression("Case When A.ItemId <> 0 Then 0 Else 1 End"),
								))					
								->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceID', array(), $select20::JOIN_INNER)
								->join(array('B1' => 'Proj_UOM'), ' B.UnitId=B1.UnitId', array(), $select20::JOIN_LEFT)
								->join(array('C' => $select10), 'A.ResourceId=C.ResourceId And A.ItemId=C.ItemId', array(), $select20::JOIN_LEFT)
								->join(array('BR' => 'MMS_Brand'), 'A.ItemId =BR.BrandId And A.ResourceId=BR.ResourceId', array(), $select20::JOIN_LEFT)
								->join(array('SK' => 'MMS_Stock'), 'A.ItemId=SK.ItemId And A.ResourceId=SK.ResourceId And A.CostCentreId=SK.CostCentreId', array(), $select20::JOIN_LEFT)
								->join(array('RV' => 'Proj_Resource'), 'A.ResourceId=RV.ResourceId', array(), $select20::JOIN_INNER)
								->join(array('RG' => 'Proj_ResourceGroup'), 'RV.ResourceGroupId=RG.ResourceGroupId', array(), $select20::JOIN_LEFT)
							->where (array("B.TypeId IN (2,3)"));	
						$statement = $sql->getSqlStringForSqlObject($select20);
						$stockdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();	
						
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($stockdetails));
						return $response;
						break;
					} 
					
					if($RequestType==3){
						$select1 = $sql->select();
						$select1->from(array("A" => "MMS_PVTrans"))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Qty' => new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
									'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId))  
															   Then Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
															   Else SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) End 
															   Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) 
															   Else A.QRate End) Else SUM(A.BillQty*Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) End End"),
								))
								->join(array('PG' => 'MMS_PVGroupTrans'), 'A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId', array(), $select1::JOIN_INNER)
								->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select1::JOIN_INNER)
								->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select1::JOIN_INNER)
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select1::JOIN_INNER)
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select1::JOIN_LEFT)
								->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select1::JOIN_INNER)
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select1::JOIN_INNER)
							->where (array("A.BillQty>0 AND B.PVDate<='$fromDate'  AND B.CostCentreId=$CostCentreId GROUP BY A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,OC.SEZProject,PG.FFactor,PG.TFactor,SM.StateId,CM.StateId"));                    

						$select2 = $sql->select();
						$select2->from(array("A" => "MMS_DCTrans"))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Qty' => new Expression("SUM(A.BalQty)"),
									'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) 
								   Then CAST(SUM(A.BalQty*Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((A.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.GrossRate End) As Decimal(18,3))  
								   Else CAST(SUM(A.BalQty*Case When (DG.FFactor>0 And DG.TFactor>0) Then isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else 
								   A.QRate End) As Decimal(18,3)) End"),
								))
								->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select2::JOIN_INNER)
								->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select2::JOIN_INNER)
								->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select2::JOIN_INNER)
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select2::JOIN_INNER)
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select2::JOIN_LEFT)
								->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select2::JOIN_INNER)
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select2::JOIN_INNER)
							->where (array("A.BalQty>0 AND B.DCDate<='$fromDate' AND  B.CostCentreId=$CostCentreId And DcOrCSM=1 GROUP BY A.ResourceId,A.ItemId,B.PurchaseTypeId,OC.SEZProject,DG.FFactor,DG.TFactor,SM.StateId,CM.StateId"));                           
						$select2->combine($select1,'Union ALL');
						
						$select3 = $sql->select();
						$select3->from(array("A" => "MMS_TransferTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.TransferQty)"),
									'Amount' => new Expression("SUM(-A.Amount)"),
								))
								->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select3::JOIN_INNER)
							->where (array("A.TransferQty>0 AND  B.TVDate <='$fromDate' AND B.FromCostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select3->combine($select2,'Union ALL');
						
						$select4 = $sql->select();
						$select4->from(array("A" => "MMS_TransferTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(A.RecdQty)"),
									'Amount' => new Expression("SUM(A.RecdQty*A.Rate)"),
								))
								->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select4::JOIN_INNER)
							->where (array("A.RecdQty>0 AND B.TVDate <='$fromDate' AND B.ToCostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select4->combine($select3,'Union ALL');
						
						$select5 = $sql->select();
						$select5->from(array("A" => "MMS_PRTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.ReturnQty)"),
									'Amount' => new Expression("SUM(-Amount)"),
								))
								->join(array('B' => 'MMS_PRRegister'), 'B.PRRegisterId = A.PRRegisterId', array(), $select5::JOIN_INNER)
							->where (array("A.ReturnQty>0 AND B.PRDate <='$fromDate' AND B.CostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select5->combine($select4,'Union ALL');
						
						$select6 = $sql->select();
						$select6->from(array("A" => "MMS_IssueTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.IssueQty)"),
									'Amount' => new Expression("SUM(-Case When (A.TFactor>0 And A.FFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End)"),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select6::JOIN_INNER)
							->where (array("A.IssueQty>0 AND B.IssueDate <='$fromDate' AND B.CostCentreId=$CostCentreId And A.IssueOrReturn='I' GROUP BY ResourceId,ItemId "));
						$select6->combine($select5,'Union ALL');
						
						$select7 = $sql->select();
						$select7->from(array("A" => "MMS_IssueTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(A.IssueQty)"),
									'Amount' => new Expression("SUM(IssueAmount)"),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select7::JOIN_INNER)
							->where (array("A.IssueQty>0 AND B.IssueDate <='$fromDate'AND B.CostCentreId=$CostCentreId  And A.IssueOrReturn='R' GROUP BY ResourceId,ItemId "));                       
						$select7->combine($select6,'Union ALL');
						
						$select8 = $sql->select();
						$select8->from("MMS_Stock")
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("OpeningStock"),
									'Amount' => new Expression("OpeningStock*ORate"),
								))
							->where (array("OpeningStock>0 AND CostCentreId=$CostCentreId"));
						$select8->combine($select7,'Union ALL');
						
						$select9 = $sql->select();
						$select9->from(array("A" => $select8))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(Qty)"),
									'Amount' => new Expression("SUM(Amount)"),
								));                    
						$select9->group(array (new Expression("ResourceId,ItemId")));
						
						$select10 = $sql->select();
						$select10->from(array("A" => $select9))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'AvgRate' => new Expression("(Case When Amount > 0 Then Amount Else null End/ Case When Qty > 0 Then Qty Else null End)"),
								)); 

						$select11 = $sql->select();
						$select11->from(array("S" => "MMS_Stock"))
								->columns(array(
									'ResourceId' => new Expression('S.ResourceId'),
									'ItemId' => new Expression("S.ItemId"),
									'CostCentreId' => new Expression("S.CostCentreId"),
									'Qty' => new Expression("S.OpeningStock"),						
								))													
							->where (array("S.OpeningStock > 0 AND S.CostCentreId=$CostCentreId")); 
							
						$select12 = $sql->select();
						$select12->from(array("A" => "MMS_PVRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End"),						
								))		
								->join(array('B' => 'MMS_PVTrans'), 'A.PVRegisterId=B.PVRegisterId', array(), $select12::JOIN_INNER)
							->where (array("B.BillQty > 0 AND A.PVDate<='$fromDate'  AND A.CostCentreId=$CostCentreId")); 	
						$select12->combine($select11,'Union ALL');
						
						$select13 = $sql->select();
						$select13->from(array("A" => "MMS_DCRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("B.BalQty"),						
								))		
								->join(array('B' => 'MMS_DCTrans'), 'A.DCRegisterId=B.DCRegisterId', array(), $select13::JOIN_INNER)
							->where (array("B.BalQty > 0  AND A.DCDate<='$fromDate' AND A.CostCentreId=$CostCentreId And DcOrCSM=1")); 	
						$select13->combine($select12,'Union ALL');
						
						$select14 = $sql->select();
						$select14->from(array("A" => "MMS_TransferRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.FromCostCentreId"),
									'Qty' => new Expression("-B.TransferQty"),						
								))		
								->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select14::JOIN_INNER)
							->where (array("B.TransferQty > 0  AND A.TVDate <='$fromDate'AND A.FromCostCentreId=$CostCentreId")); 	
						$select14->combine($select13,'Union ALL');
						
						$select15 = $sql->select();
						$select15->from(array("A" => "MMS_TransferRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.ToCostCentreId"),
									'Qty' => new Expression("B.RecdQty"),						
								))		
								->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select15::JOIN_INNER)
							->where (array("B.RecdQty > 0 AND A.TVDate <='$fromDate' AND A.ToCostCentreId=$CostCentreId")); 	
						$select15->combine($select14,'Union ALL');
						
						$select16 = $sql->select();
						$select16->from(array("A" => "MMS_PRRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("-B.ReturnQty"),						
								))		
								->join(array('B' => 'MMS_PRTrans'), 'A.PRRegisterId = B.PRRegisterId', array(), $select16::JOIN_INNER)
							->where (array("B.ReturnQty > 0 AND A.PRDate <='$fromDate' AND A.CostCentreId=$CostCentreId")); 	
						$select16->combine($select15,'Union ALL');
						
						$select17 = $sql->select();
						$select17->from(array("A" => "MMS_IssueRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("-B.IssueQty"),						
								))		
								->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select17::JOIN_INNER)
							->where (array("B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<='$fromDate'  AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 ")); 	
						$select17->combine($select16,'Union ALL');
						
						$select18 = $sql->select();
						$select18->from(array("A" => "MMS_IssueRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("B.IssueQty"),						
								))		
								->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select18::JOIN_INNER)
							->where (array("B.IssueOrReturn='R' And B.IssueQty > 0  AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0")); 	
						$select18->combine($select17,'Union ALL');
						
						$select19 = $sql->select();
						$select19->from(array("A" => $select18))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),									
									'Qty' => new Expression("SUM(Qty)"),						
									'CostCentreId' => new Expression("A.CostCentreId"),						
								))										
							->where (array("A.CostCentreId=$CostCentreId GROUP BY A.ResourceId,A.CostCentreId,A.ItemId")); 	

						$select20 = $sql->select();
						$select20->from(array("A" => $select19))
								->columns(array(
									'ResourceGroupId' => new Expression('Distinct ISNULL(RG.ResourceGroupId,0)'),
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),
									'ResourceGroupName' => new Expression("RG.ResourceGroupName"),
									'Resource' => new Expression("B.ResourceName"),
									'ItemName' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else '' End"),
									'Unit' => new Expression("B1.UnitName"),
									'Op.Balance' => new Expression("CAST(SK.OpeningStock As Decimal(18,5))"),
									'Receipt' => new Expression("CAST(( ISNULL((SELECT SUM(B.BalQty)  FROM MMS_DCRegister A  
																 INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId Where B.AcceptQty > 0  AND A.DCDate<='$fromDate' 
																 AND A.CostCentreId=$CostCentreId And DcOrCSM=1 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) + 
																 ISNULL((SELECT  SUM(B.ActualQty)  FROM MMS_PVRegister A   
																 INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																 WHERE  B.BillQty > 0 AND A.PVDate<='$fromDate'  AND A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																 And B.DCRegisterId>0 And B.DCTransId>0 ),0) + ISNULL((SELECT  SUM(B.BillQty)  FROM MMS_PVRegister A   
																 INNER JOIN MMS_PVTrans B   On A.PVRegisterId=B.PVRegisterId 
																 WHERE  B.BillQty > 0 AND A.PVDate<='$fromDate'  AND A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																 And B.DCRegisterId=0 And B.DCTransId=0 ),0))  As Decimal(18,5))"),
									'BillReturn' => new Expression("CAST(ISNULL((SELECT SUM(-B.ReturnQty) FROM MMS_PRRegister A   
																	 INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId 
																	 WHERE B.ReturnQty > 0 AND A.PRDate <='$fromDate' AND A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId ),0) As Decimal(18,5)) "),
									'Transfer' => new Expression("(CAST(ISNULL((SELECT SUM(-B.TransferQty)  FROM MMS_TransferRegister A  
																 INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId 
																 WHERE B.TransferQty > 0 AND A.TVDate <='$fromDate' AND A.FromCostCentreId=$CostCentreId AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) As Decimal(18,5)) + 
																 CAST(ISNULL((SELECT  SUM(B.RecdQty) FROM MMS_TransferRegister A   
																 INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId 
																 WHERE B.RecdQty > 0 AND A.TVDate <='$fromDate' AND A.ToCostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ),0) As Decimal(18,5)) )"),
									'Issue/Return' => new Expression("(CAST(ISNULL(( SELECT SUM(-B.IssueQty) FROM MMS_IssueRegister A   
																	 INNER JOIN MMS_IssueTrans B   On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' 
																	 And B.IssueQty > 0 AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId
																	  And B.ItemId=C.ItemId ),0) As Decimal(18,5)) + CAST(ISNULL(( SELECT SUM(B.IssueQty) FROM MMS_IssueRegister A    
																	  INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0  
																	  AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) As Decimal(18,5)))"),
									'Net' => new Expression("CAST(Qty AS Decimal(18,5))"),
									'AvgRate' => new Expression("CAST (ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
									'AvgAmount' => new Expression("CAST(Qty*ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
									'LRate' => new Expression("SK.LRate"),
									'LAmount' => new Expression("CAST(Qty*ISNULL(SK.LRate,0) As Decimal(18,3))"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Child' => new Expression("Case When A.ItemId <> 0 Then 0 Else 1 End"),
								))					
								->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceID', array(), $select20::JOIN_INNER)
								->join(array('B1' => 'Proj_UOM'), ' B.UnitId=B1.UnitId', array(), $select20::JOIN_LEFT)
								->join(array('C' => $select10), 'A.ResourceId=C.ResourceId And A.ItemId=C.ItemId', array(), $select20::JOIN_LEFT)
								->join(array('BR' => 'MMS_Brand'), 'A.ItemId =BR.BrandId And A.ResourceId=BR.ResourceId', array(), $select20::JOIN_LEFT)
								->join(array('SK' => 'MMS_Stock'), 'A.ItemId=SK.ItemId And A.ResourceId=SK.ResourceId And A.CostCentreId=SK.CostCentreId', array(), $select20::JOIN_LEFT)
								->join(array('RV' => 'Proj_Resource'), 'A.ResourceId=RV.ResourceId', array(), $select20::JOIN_INNER)
								->join(array('RG' => 'Proj_ResourceGroup'), 'RV.ResourceGroupId=RG.ResourceGroupId', array(), $select20::JOIN_LEFT)
							->where (array("B.TypeId IN (2,3)"));	
						$statement = $sql->getSqlStringForSqlObject($select20);
						$stockdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();	
						
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($stockdetails));
						return $response;
						break;
					}
					if($RequestType==2){
						$select1 = $sql->select();
						$select1->from(array("A" => "MMS_PVTrans"))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Qty' => new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
									'Amount' => new Expression("SUM(Case When B.ThruDC='Y' Then A.ActualQty Else A.BillQty End*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End)"),
								))
								->join(array('PG' => 'MMS_PVGroupTrans'), 'A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId', array(), $select1::JOIN_INNER)
								->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select1::JOIN_INNER)
								->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select1::JOIN_INNER)
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select1::JOIN_INNER)
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select1::JOIN_LEFT)
								->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select1::JOIN_INNER)
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select1::JOIN_INNER)
							->where (array("A.BillQty>0 AND B.PVDate<='$fromDate' 
											AND B.CostCentreId=$CostCentreId GROUP BY A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,OC.SEZProject,PG.FFactor,PG.TFactor,SM.StateId,CM.StateId"));                    

						$select2 = $sql->select();
						$select2->from(array("A" => "MMS_DCTrans"))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Qty' => new Expression("SUM(A.BalQty)"),
									'Amount' => new Expression("CAST(SUM(A.BalQty*Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.QRate End) As Decimal(18,3))"),
								))
								->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select2::JOIN_INNER)
								->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select2::JOIN_INNER)
								->join(array('OC' => 'WF_OperationalCostCentre'), 'B.CostCentreId=OC.CostCentreId', array(), $select2::JOIN_INNER)
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select2::JOIN_INNER)
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select2::JOIN_LEFT)
								->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select2::JOIN_INNER)
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select2::JOIN_INNER)
							->where (array("A.BalQty>0 AND B.DCDate<='$fromDate' AND  B.CostCentreId=$CostCentreId And DcOrCSM=1 GROUP BY A.ResourceId,A.ItemId,B.PurchaseTypeId,OC.SEZProject,DG.FFactor,DG.TFactor,SM.StateId,CM.StateId"));                           
						$select2->combine($select1,'Union ALL');
						
						$select3 = $sql->select();
						$select3->from(array("A" => "MMS_TransferTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.TransferQty)"),
									'Amount' => new Expression("SUM(-A.Amount)"),
								))
								->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select3::JOIN_INNER)
							->where (array("A.TransferQty>0 AND  B.TVDate <='$fromDate' AND B.FromCostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select3->combine($select2,'Union ALL');
						
						$select4 = $sql->select();
						$select4->from(array("A" => "MMS_TransferTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(A.RecdQty)"),
									'Amount' => new Expression("SUM(A.RecdQty*A.Rate)"),
								))
								->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select4::JOIN_INNER)
							->where (array("A.RecdQty>0 AND B.TVDate <='$fromDate' AND B.ToCostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select4->combine($select3,'Union ALL');
						
						$select5 = $sql->select();
						$select5->from(array("A" => "MMS_PRTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.ReturnQty)"),
									'Amount' => new Expression("SUM(-Amount)"),
								))
								->join(array('B' => 'MMS_PRRegister'), 'B.PRRegisterId = A.PRRegisterId', array(), $select5::JOIN_INNER)
							->where (array("A.ReturnQty>0 AND B.PRDate <='$fromDate' AND B.CostCentreId=$CostCentreId GROUP BY ResourceId,ItemId"));
						$select5->combine($select4,'Union ALL');
						
						$select6 = $sql->select();
						$select6->from(array("A" => "MMS_IssueTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(-A.IssueQty)"),
									'Amount' => new Expression("SUM(-Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End)"),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select6::JOIN_INNER)
							->where (array("A.IssueQty>0 AND B.IssueDate <='$fromDate' AND B.CostCentreId=$CostCentreId And A.IssueOrReturn='I' GROUP BY ResourceId,ItemId "));
						$select6->combine($select5,'Union ALL');
						
						$select7 = $sql->select();
						$select7->from(array("A" => "MMS_IssueTrans"))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(A.IssueQty)"),
									'Amount' => new Expression("SUM(Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End)"),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select7::JOIN_INNER)
							->where (array("A.IssueQty>0 AND B.IssueDate <='$fromDate'AND B.CostCentreId=$CostCentreId  And A.IssueOrReturn='R' GROUP BY ResourceId,ItemId "));                       
						$select7->combine($select6,'Union ALL');
						
						$select8 = $sql->select();
						$select8->from("MMS_Stock")
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("OpeningStock"),
									'Amount' => new Expression("OpeningStock*ORate"),
								))
							->where (array("OpeningStock>0 AND CostCentreId=$CostCentreId"));
						$select8->combine($select7,'Union ALL');
						
						$select9 = $sql->select();
						$select9->from(array("A" => $select8))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'Qty' => new Expression("SUM(Qty)"),
									'Amount' => new Expression("SUM(Amount)"),
								));                    
						$select9->group(array (new Expression("ResourceId,ItemId")));
						
						$select10 = $sql->select();
						$select10->from(array("A" => $select9))
								->columns(array(
									'ResourceId' => new Expression('ResourceId'),
									'ItemId' => new Expression("ItemId"),
									'AvgRate' => new Expression("(Case When Amount > 0 Then Amount Else null End/ Case When Qty > 0 Then Qty Else null End)"),
								)); 

						$select11 = $sql->select();
						$select11->from(array("S" => "MMS_Stock"))
								->columns(array(
									'ResourceId' => new Expression('S.ResourceId'),
									'ItemId' => new Expression("S.ItemId"),
									'CostCentreId' => new Expression("S.CostCentreId"),
									'Qty' => new Expression("S.OpeningStock"),						
								))													
							->where (array("S.OpeningStock > 0 AND S.CostCentreId=$CostCentreId")); 
							
						$select12 = $sql->select();
						$select12->from(array("A" => "MMS_PVRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End"),						
								))		
								->join(array('B' => 'MMS_PVTrans'), 'A.PVRegisterId=B.PVRegisterId', array(), $select12::JOIN_INNER)
							->where (array("B.BillQty > 0 AND A.PVDate<='$fromDate'  AND A.CostCentreId=$CostCentreId")); 	
						$select12->combine($select11,'Union ALL');
						
						$select13 = $sql->select();
						$select13->from(array("A" => "MMS_DCRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("B.BalQty"),						
								))		
								->join(array('B' => 'MMS_DCTrans'), 'A.DCRegisterId=B.DCRegisterId', array(), $select13::JOIN_INNER)
							->where (array("B.BalQty > 0  AND A.DCDate<='$fromDate' AND A.CostCentreId=$CostCentreId And DcOrCSM=1")); 	
						$select13->combine($select12,'Union ALL');
						
						$select14 = $sql->select();
						$select14->from(array("A" => "MMS_TransferRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.FromCostCentreId"),
									'Qty' => new Expression("-B.TransferQty"),						
								))		
								->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select14::JOIN_INNER)
							->where (array("B.TransferQty > 0  AND A.TVDate <='$fromDate'AND A.FromCostCentreId=$CostCentreId")); 	
						$select14->combine($select13,'Union ALL');
						
						$select15 = $sql->select();
						$select15->from(array("A" => "MMS_TransferRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.ToCostCentreId"),
									'Qty' => new Expression("B.RecdQty"),						
								))		
								->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select15::JOIN_INNER)
							->where (array("B.RecdQty > 0 AND A.TVDate <='$fromDate' AND A.ToCostCentreId=$CostCentreId")); 	
						$select15->combine($select14,'Union ALL');
						
						$select16 = $sql->select();
						$select16->from(array("A" => "MMS_PRRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("-B.ReturnQty"),						
								))		
								->join(array('B' => 'MMS_PRTrans'), 'A.PRRegisterId = B.PRRegisterId', array(), $select16::JOIN_INNER)
							->where (array("B.ReturnQty > 0 AND A.PRDate <='$fromDate' AND A.CostCentreId=$CostCentreId")); 	
						$select16->combine($select15,'Union ALL');
						
						$select17 = $sql->select();
						$select17->from(array("A" => "MMS_IssueRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("-B.IssueQty"),						
								))		
								->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select17::JOIN_INNER)
							->where (array("B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<='$fromDate'  AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 ")); 	
						$select17->combine($select16,'Union ALL');
						
						$select18 = $sql->select();
						$select18->from(array("A" => "MMS_IssueRegister"))
								->columns(array(
									'ResourceId' => new Expression('B.ResourceId'),
									'ItemId' => new Expression("B.ItemId"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Qty' => new Expression("B.IssueQty"),						
								))		
								->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select18::JOIN_INNER)
							->where (array("B.IssueOrReturn='R' And B.IssueQty > 0  AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0")); 	
						$select18->combine($select17,'Union ALL');
						
						$select19 = $sql->select();
						$select19->from(array("A" => $select18))
								->columns(array(
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),									
									'Qty' => new Expression("SUM(Qty)"),						
									'CostCentreId' => new Expression("A.CostCentreId"),						
								))										
							->where (array("A.CostCentreId=$CostCentreId GROUP BY A.ResourceId,A.CostCentreId,A.ItemId")); 	

						$select20 = $sql->select();
						$select20->from(array("A" => $select19))
								->columns(array(
									'ResourceGroupId' => new Expression('Distinct RG.ResourceGroupId'),
									'ResourceId' => new Expression('A.ResourceId'),
									'ItemId' => new Expression("A.ItemId"),
									'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),
									'ResourceGroupName' => new Expression("RG.ResourceGroupName"),
									'Resource' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End"),
									'ItemName' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else '' End"),
									'Unit' => new Expression("B1.UnitName"),
									'Op.Balance' => new Expression("CAST(SK.OpeningStock As Decimal(18,5))"),
									'Receipt' => new Expression("( CAST(ISNULL((SELECT SUM(B.BalQty)  FROM MMS_DCRegister A  
																	 INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId Where B.AcceptQty > 0  AND A.DCDate<='$fromDate' 
																	 AND A.CostCentreId= $CostCentreId  And DcOrCSM=1 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) +
																	 ISNULL((SELECT  SUM(B.ActualQty)  FROM MMS_PVRegister A  
																	 INNER JOIN MMS_PVTrans B   On A.PVRegisterId=B.PVRegisterId WHERE  B.BillQty > 0 AND A.PVDate<='$fromDate'  
																	 AND A.CostCentreId= $CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And B.DCRegisterId>0 And B.DCTransId>0 ),0) + 
																	 ISNULL((SELECT  SUM(B.BillQty)  FROM MMS_PVRegister A  
																	 INNER JOIN MMS_PVTrans B   On A.PVRegisterId=B.PVRegisterId WHERE  B.BillQty > 0 AND A.PVDate<='$fromDate' 
																	 AND A.CostCentreId= $CostCentreId  And B.ResourceId=C.ResourceId And B.ItemId= C.ItemId And B.DCRegisterId=0 And B.DCTransId=0 ),0) As Decimal(18,5)))"),
									'BillReturn' => new Expression("CAST(ISNULL((SELECT SUM(-B.ReturnQty) FROM MMS_PRRegister A  
																	 INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId 
																	 WHERE B.ReturnQty > 0 AND A.PRDate <='$fromDate' AND A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId ANd B.ItemId= C.ItemId ),0) As Decimal(18,5))"),
									'Transfer' => new Expression("( CAST(ISNULL((SELECT SUM(-B.TransferQty)  FROM MMS_TransferRegister A  
																	 INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate <='$fromDate' 
																	 AND A.FromCostCentreId=$CostCentreId AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) As Decimal(18,5)) +
																	 CAST(ISNULL((SELECT  SUM(B.RecdQty) FROM MMS_TransferRegister A   
																	 INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate <='$fromDate' 
																	 AND A.ToCostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ),0) As Decimal(18,5)))"),
									'Issue/Return' => new Expression("(CAST(ISNULL(( SELECT SUM(-B.IssueQty) FROM MMS_IssueRegister A  
																	 INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 
																	 AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ),0) As Decimal(18,5)) +
																	 CAST(ISNULL(( SELECT SUM(B.IssueQty) FROM MMS_IssueRegister A  
																	 INNER JOIN MMS_IssueTrans B   On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0  
																	 AND A.IssueDate<='$fromDate' AND A.CostCentreId=$CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId),0) As Decimal(18,5)))"),
									'Net' => new Expression("CAST(Qty AS Decimal(18,3))"),
									'QRate' => new Expression("CAST (ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
									'QAmount' => new Expression("CAST(Qty*ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
									'LRate' => new Expression("SK.LRate"),
									'LAmount' => new Expression("CAST(Qty*ISNULL(SK.LRate,0) As Decimal(18,3))"),
									'CostCentreId' => new Expression("A.CostCentreId"),
									'Child' => new Expression("Case When A.ItemId <> 0 Then 0 Else 1 End"),
								))					
								->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceID', array(), $select20::JOIN_INNER)
								->join(array('B1' => 'Proj_UOM'), ' B.UnitId=B1.UnitId', array(), $select20::JOIN_LEFT)
								->join(array('C' => $select10), 'A.ResourceId=C.ResourceId And A.ItemId=C.ItemId', array(), $select20::JOIN_LEFT)
								->join(array('BR' => 'MMS_Brand'), 'A.ItemId =BR.BrandId And A.ResourceId=BR.ResourceId', array(), $select20::JOIN_LEFT)
								->join(array('SK' => 'MMS_Stock'), 'A.ItemId=SK.ItemId And A.ResourceId=SK.ResourceId And A.CostCentreId=SK.CostCentreId', array(), $select20::JOIN_LEFT)
								->join(array('RV' => 'Proj_Resource'), 'A.ResourceId=RV.ResourceId', array(), $select20::JOIN_INNER)
								->join(array('RG' => 'Proj_ResourceGroup'), 'RV.ResourceGroupId=RG.ResourceGroupId', array(), $select20::JOIN_LEFT)
							->where (array("B.TypeId IN (2,3)"));	
						$statement = $sql->getSqlStringForSqlObject($select20);
						$stockdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();	
						
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($stockdetails));
						return $response;
						break;
					}
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
	public function warehousewiseStockAction(){
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
        $sql = new Sql ($dbAdapter);

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
                $postParams = $request->getPost();
                $WareHouseId = $this->bsf->isNullCheck($postParams['WareHouseId'], 'string');

                $select = $sql->select();
                $select->from(array("a" => "Proj_Resource"))
                    ->columns(array(new Expression("f.CostCentreId As CostCentreId,f.CostCentreName As CostCentreName")))
                    ->join(array('b' => 'MMS_Stock'), 'a.ResourceId =b.ResourceId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'MMS_StockTrans'), 'b.StockId=c.StockId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_WareHouseDetails'), 'c.WareHouseId=d.TransId', array(), $select::JOIN_INNER)
                    ->join(array('e' => 'MMS_WareHouse'), 'd.WareHouseId =d.WareHouseId', array(), $select::JOIN_INNER)
                    ->join(array('f' => 'WF_OperationalCostCentre'), 'b.CostCentreId=f.CostCentreId', array(), $select::JOIN_INNER)
                    ->where("e.WareHouseId IN($WareHouseId) And c.ClosingStock>0");
                $select->group(new Expression("f.CostCentreId,f.CostCentreName"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $costcentre = array();

                foreach ($requests as $arr_requests) {
                    array_push( $costcentre, $arr_requests['CostCentreName']);
                }
                $costcentreName = implode(",", $costcentre);

                //g-main query
                $selectG = $sql->select();
                $selectG->from(array("a"=>"MMS_Stock"))
                    ->columns(array(new Expression("Case When a.ItemId>0 Then g.ItemCode Else f.Code End Code,
                    Case When a.ItemId>0 Then g.BrandName Else f.ResourceName End Resource,
                    Case When a.ItemId>0 Then i.UnitName Else h.UnitName End Unit,
                    a.ResourceId,a.ItemId,e.CostCentreName, ISNULL(SUM(ISNULL(b.ClosingStock,0)),0) As ClosingStock,
                    0 As CSMClosingStock")))
                    ->join(array("b" => "MMS_StockTrans"), "a.StockId=b.StockId", array(), $selectG::JOIN_INNER)
                    ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.TransId", array(), $selectG::JOIN_INNER)
                    ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.WareHouseId", array(), $selectG::JOIN_INNER)
                    ->join(array("e" => "WF_OperationalCostCentre"), "a.CostCentreId=e.CostCentreId ", array(), $selectG::JOIN_INNER)
                    ->join(array("f" => "Proj_Resource"), "a.ResourceId=f.ResourceId", array(), $selectG::JOIN_INNER)
                    ->join(array("g" => "MMS_Brand"), "a.ResourceId=g.ResourceId And a.ItemId=g.BrandId", array(), $selectG::JOIN_LEFT)
                    ->join(array("h" => "Proj_UOM"), "f.UnitId=h.UnitId", array(), $selectG::JOIN_LEFT)
                    ->join(array("i" => "Proj_UOM"), "g.UnitId=i.UnitId", array(), $selectG::JOIN_LEFT)
                    ->Where(array("d.WareHouseId IN ($WareHouseId)"));
                $selectG->group(new Expression("a.CostCentreId,a.ResourceId,a.ItemId,e.CostCentreName,g.ItemCode,f.Code,
                      g.BrandName,f.ResourceName,h.UnitName,i.UnitName"));

                $subSel = $sql->select();
                $subSel->from(array("a"=>"MMS_DCWareHouseTrans"))
                    ->columns(array(new Expression("b.ResourceId,b.ItemId,
                    c.CostCentreId,ISNULL(a.DCQty,0)As DCQty,0 As IssueQty")))
                    ->join(array("b" => "MMS_DCGroupTrans"), "a.DCGroupId=b.DCGroupId", array(), $subSel::JOIN_INNER)
                    ->join(array("c" => "MMS_DCRegister"), "b.DCRegisterId=c.DCRegisterId", array(), $subSel::JOIN_INNER)
                    ->where(array("a.WareHouseId IN ($WareHouseId)  And c.DCOrCSM=0"));

                $subSel1 = $sql->select();
                $subSel1->from(array("a"=>"MMS_IssueWareHouseTrans"))
                    ->columns(array(new Expression("b.ResourceId,b.ItemId,
                    c.CostCentreId,0 DCQty,ISNULL(a.IssueQty,0) As IssueQty")))
                    ->join(array("b" => "MMS_IssueTrans"), "a.IssueTransId=b.IssueTransId", array(), $subSel1::JOIN_INNER)
                    ->join(array("c" => "MMS_IssueRegister"), "b.IssueRegisterId=c.IssueRegisterId", array(), $subSel1::JOIN_INNER)
                    ->where(array("a.WareHouseId IN ($WareHouseId)  And C.OwnOrCSM=1"));
                $subSel1->combine($subSel, 'Union ALL');

                $selectG1 = $sql->select();
                $selectG1->from(array("g" => $subSel1))
                    ->columns(array(new Expression("Case When g.ItemId>0 Then b.ItemCode Else a.Code End Code,
                          Case When g.ItemId>0 Then b.BrandName Else a.ResourceName End Resource,
                          Case When g.ItemId>0 Then c.UnitName Else d.UnitName End Unit,
                          g.ResourceId,g.ItemId,e.CostCentreName,0 As ClosingStock,
                          (ISNULL(SUM(ISNULL(g.DCQty,0)),0)-ISNULL(SUM(ISNULL(g.IssueQty,0)),0)) As CSMClosingStock")))
                    ->join(array("a" => "Proj_Resource"), "g.ResourceId=a.ResourceId", array(), $selectG1::JOIN_INNER)
                    ->join(array("b" => "MMS_Brand"), "a.ResourceId=b.ResourceId And b.BrandId=g.ItemId", array(), $selectG1::JOIN_LEFT)
                    ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $selectG1::JOIN_LEFT)
                    ->join(array("d" => "Proj_UOM"), "b.UnitId=d.UnitId", array(), $selectG1::JOIN_LEFT)
                    ->join(array("e" => "WF_OperationalCostCentre"),"g.CostCentreId=e.CostCentreId", array(), $selectG1::JOIN_LEFT)
                ->group(new Expression("g.CostCentreId,g.ResourceId,g.ItemId,b.ItemCode,
                a.Code,b.BrandName,a.ResourceName,c.UnitName,d.UnitName,e.CostCentreName"));
                $selectG1->combine($selectG, 'Union ALL');

                $selMain1 = $sql->select();
                $selMain1->from(array("g" => $selectG1))
                    ->columns(array(new Expression("g.Code,g.Resource,g.Unit,g.ResourceId,g.ItemId,g.CostCentreName,
		                    CAST((ISNULL(SUM(ISNULL(g.ClosingStock,0)),0)-ISNULL(SUM(ISNULL(g.CSMClosingStock,0)),0)) As Decimal(18,5)) As ClosingStock,
		                    Total = (Select CAST((ISNULL(SUM(ISNULL(g2.ClosingStock,0)),0)-ISNULL(SUM(ISNULL(g2.CSMClosingStock,0)),0)) As Decimal(18,6))
                               From (
                                    Select   a.ResourceId,a.ItemId,
                                    ISNULL(SUM(ISNULL(b.ClosingStock,0)),0) As ClosingStock,0 As CSMClosingStock From MMS_Stock a
                                        Inner Join MMS_StockTrans b On a.StockId=b.StockId
                                        Inner Join MMS_WareHouseDetails c On b.WareHouseId=c.TransId
                                        Inner Join MMS_WareHouse d On c.WareHouseId=d.WareHouseId
                                    Where c.WareHouseId IN ($WareHouseId)
                                    Group By a.CostCentreId,a.ResourceId,a.ItemId
                                    Union All

                                    Select  g1.ResourceId,g1.ItemId,0 As ClosingStock,
                                    (ISNULL(SUM(ISNULL(g1.DCQty,0)),0)-ISNULL(SUM(ISNULL(g1.IssueQty,0)),0)) As CSMClosingStock
                                    From (
                                         Select b.ResourceId,b.ItemId,c.CostCentreId,ISNULL(a.DCQty,0) As DCQty,0 As IssueQty From MMS_DCWareHouseTrans a
                                                Inner Join MMS_DCGroupTrans b On a.DCGroupId=b.DCGroupId
                                                Inner Join MMS_DCRegister c On b.DCRegisterId=c.DCRegisterId
                                         Where a.WareHouseId IN ($WareHouseId)  And c.DCOrCSM=0
                                         Union All
                                         Select b.ResourceId,b.ItemId,c.CostCentreId,0 As DCQty,ISNULL(A.IssueQty,0) As IssueQty From MMS_IssueWareHouseTrans a
                                                Inner Join MMS_IssueTrans b On a.IssueTransId=b.IssueTransId
                                                Inner Join MMS_IssueRegister c On b.IssueRegisterId=c.IssueRegisterId
                                         Where a.WareHouseId IN ($WareHouseId)  And c.OwnOrCSM=1
                                         ) g1
                                    Group By g1.CostCentreId,g1.ResourceId,g1.ItemId
                               )g2
                               Where g2.ResourceId=g.ResourceId and g2.ItemId=g.ItemId
                               Group By g2.ResourceId,g2.ItemId
                               Having (ISNULL(SUM(ISNULL(g2.ClosingStock,0)),0)-ISNULL(SUM(ISNULL(g2.CSMClosingStock,0)),0))>0)")))
                    ->group (new Expression("Code,Resource,Unit,ResourceId,ItemId,CostCentreName
							   Having (ISNULL(SUM(ISNULL(G.ClosingStock,0)),0)-ISNULL(SUM(ISNULL(G.CSMClosingStock,0)),0)) > 0"));
							    $statement = $sql->getSqlStringForSqlObject($selMain1);

                $selFirst = $sql->select();
                $selFirst->from(array("Main" =>$selMain1))
                    ->columns(array('*'));
				$selSec = "select * from(".$statement.") as Main PIVOT (Sum(Main.ClosingStock) For CostCentreName IN ([ ".str_replace(',','],[',$costcentreName)."]) ) As PVt";
				$resource = $dbAdapter->query($selSec, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $data=json_encode(array('resource'=>$resource,'requests'=>$requests));
                $response = $this->getResponse()->setContent($data);
                return $response;

			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}

            // getting the  warehouse details
            $select = $sql->select();
            $select->from(array('a' => 'MMS_WareHouse'))
                ->columns(array('WareHouseId', 'WareHouseName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			

			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function stockLedgerAction(){
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
        $sql = new Sql ($dbAdapter);


		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $costcentreId = $this->params()->fromPost('costcentreId');
                $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('resourceId'), 'number');
                $itemid = $this->bsf->isNullCheck($this->params()->fromPost('itemid'), 'number');
                $cid = $this->bsf->isNullCheck($this->params()->fromPost('common'), 'number');
                $warehouseid = $this->bsf->isNullCheck($this->params()->fromPost('warehouseId'), 'number');




                $response = $this->getResponse();
                switch($Type) {

                    case 'selectCostcentre':

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectResource"))
                            ->columns(array(new Expression("a.ResourceId,isnull(c.BrandId,0) As ItemId,
                               case when isnull(c.BrandId,0)>0 then c.ItemCode+ '' +c.BrandName Else b.code+ ' - '+b.ResourceName End as ResourceCode")))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'MMS_Brand'), 'b.ResourceId=c.ResourceId', array(), $select::JOIN_LEFT)
                            ->where('a.ProjectId IN ('.$costcentreId.')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp['arr_Resources'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($resp));
                        return $response;
                        break;


                    case 'selectResource':

                        $unit1 = $sql->select();
                        $unit1->from(array("s" => "mms_stock"))
                            ->columns(array(new Expression(" '' As TransNo,s.ResourceId,s.ItemId,s.CostCentreId,
                                    null As Date,'' As Project,
                                   'OpeningStock' As Description,'' As Type,
                                   CAST(s.OpeningStock AS decimal(18,5)) As Receipt,
                                   CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                   CAST(S.OpeningStock AS decimal(18,5)) As Qty,null As [CDate] ")))
                            ->where("s.CostCentreId IN ($cid)and s.ResourceId IN ($resourceId) and
                                     s.ItemId IN($itemid)and s.OpeningStock > 0");

                        $unit2 = $sql->select();
                        $unit2 ->from(array("a" => "mms_PVRegister"))
                            ->columns(array(new Expression(" a.PVNo As TransNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                  CONVERT(varchar(10),a.PVDate,105) As Date,
                                  '' As Project,c.VendorName,'Purchase' As Type,
                                  Case When a.ThruDC='Y' Then b.ActualQty Else b.BillQty End BillQty,
                                  CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                  Case When a.ThruDC='Y' Then b.ActualQty Else b.BillQty End BillQty,
                                  CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate")))
                            ->join(array('b' => 'mms_PVTrans'), 'a.PVRegisterId=b.PVRegisterId', array(), $unit2::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $unit2::JOIN_INNER)
                            ->where("b.DCRegisterId=0 And b.BillQty > 0 AND
                             a.PVDate<='29-Dec-2016' and a.CostCentreId IN ($cid)and b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid)");
                        $unit2->combine($unit1, 'Union ALL');


                        $unit3 = $sql->select();
                        $unit3->from(array("a" => "MMS_DCRegister"))
                            ->columns(array(new Expression("a.DCNo, b.ResourceId,b.ItemId,
                                a.CostCentreId,CONVERT(varchar(10),a.DCDate,105) As Date,'' As Project,c.VendorName, 'DC' As Type,
                                b.AcceptQty,CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                b.AcceptQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate ")))
                            ->join(array('b' => 'MMS_DCTrans'), 'a.DCRegisterId=b.DCRegisterId', array(), $unit3::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $unit3::JOIN_INNER)
                            ->where("b.AcceptQty > 0 AND a.DCDate<='29-Dec-2016' and
                                       a.CostCentreId IN ($cid)and b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid) And a.DcOrCSM=1");
                        $unit3->combine($unit2, 'Union ALL');


                        $unit4 = $sql->select();
                        $unit4->from(array("a" => "MMS_IssueRegister"))
                            ->columns(array(new Expression("a.IssueNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                      CONVERT(varchar(10),a.IssueDate,105) As IssueDate,
                                      '' As Project,Case When c.VendorName <> '' Then c.VendorName Else 'Internal' End VendorName,
                                      'Issue' As Type, CAST(0 As Decimal(18,5)) As Receipt,b.IssueQty,
                                      CAST(0 As Decimal(18,5)) As ClosingStock,-b.IssueQty,
                                      CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate")))
                            ->join(array('b' => 'MMS_IssueTrans'), 'a.IssueRegisterId = b.IssueRegisterId', array(), $unit4::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.ContractorId=c.VendorId', array(), $unit4::JOIN_INNER)
                            ->where("b.IssueOrReturn='I' And b.IssueQty > 0  AND
                                    a.IssueDate<='29-Dec-2016' AND a.CostCentreId IN ($cid)and b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid) And a.OwnOrCSM=0");
                        $unit4->combine($unit3, 'Union ALL');


                        $unit5 = $sql->select();
                        $unit5->from(array("a" => "MMS_IssueRegister"))
                            ->columns(array(new Expression("a.IssueNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                     CONVERT(varchar(10),a.IssueDate,105) As IssueDate,
                                     '' As Project,Case When c.VendorName <> '' Then c.VendorName Else 'Internal' End VendorName,
                                     'Issue-Return' As [Type], b.IssueQty,CAST(0 As Decimal(18,5)) As Issue,
                                     CAST(0 As Decimal(18,5)) As ClosingStock,b.IssueQty,
                                     CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate
                                      ")))
                            ->join(array('b' => 'MMS_IssueTrans'), 'a.IssueRegisterId = b.IssueRegisterId', array(), $unit5::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.ContractorId=c.VendorId ', array(), $unit5::JOIN_INNER)
                            ->where("b.IssueOrReturn='R' And b.IssueQty > 0  AND
                                     a.IssueDate<='29-Dec-2016' AND a.CostCentreId IN ($cid)and b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid) And a.OwnOrCSM=0");
                        $unit5->combine($unit4, 'Union ALL');

                        $unit6 = $sql->select();
                        $unit6->from(array("a" => "MMS_TransferRegister"))
                            ->columns(array(new Expression("a.TVNo,b.ResourceId,b.ItemId,a.FromCostCentreId,
                                    CONVERT(varchar(10),a.TVDate,105) As TVDate,
                                    oc.CostCentreName, '' As VendorName,'Transfer' As Type,
                                    CAST(0 As Decimal(18,5)) As Receipt,b.TransferQty,
                                    CAST(0 As Decimal(18,5)) As ClosingStock,-b.TransferQty,
                                    CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate")))
                            ->join(array('b' => 'MMS_TransferTrans'), 'a.TVRegisterId = b.TransferRegisterId', array(), $unit6::JOIN_INNER)
                            ->join(array('oc' => 'wf_OperationalCostCentre'), 'a.ToCostCentreId=oc.CostCentreId', array(), $unit6::JOIN_INNER)
                            ->join(array('oc1' => 'wf_OperationalCostCentre'), 'a.FromCostCentreId=oc1.CostCentreId', array(), $unit6::JOIN_INNER)
                            ->where("b.TransferQty > 0 AND a.TVDate<='29-Dec-2016'
                                       AND a.FromCostCentreId IN ($cid)and b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid)");
                        $unit6->combine($unit5, 'Union ALL');

                        $unit7 = $sql->select();
                        $unit7->from(array("a" => "MMS_TransferRegister"))
                            ->columns(array(new Expression("a.TVNo,b.ResourceId,b.ItemId,
                            a.ToCostCentreId,CONVERT(varchar(10),a.TVDate,105) As TVDate,
                            oc.CostCentreName, '' As VendorName,'Transfer' As Type,
                            b.RecdQty,CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                            b.RecdQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate")))
                            ->join(array('b' => 'MMS_TransferTrans'), 'a.TVRegisterId = b.TransferRegisterId', array(), $unit7::JOIN_INNER)
                            ->join(array('oc' => 'wf_OperationalCostCentre'), 'a.FromCostCentreId=oc.CostCentreId', array(), $unit7::JOIN_INNER)
                            ->join(array('oc1' => 'wf_OperationalCostCentre'), 'a.ToCostCentreId=oc.CostCentreId', array(), $unit7::JOIN_INNER)
                            ->where("b.RecdQty > 0 AND a.TVDate<='29-Dec-2016' AND a.ToCostCentreId IN ($cid)and
                                      b.ResourceId IN ($resourceId) and
                                      b.ItemId IN($itemid)");
                        $unit7->combine($unit6, 'Union ALL');

                        $unit8 = $sql->select();
                        $unit8->from(array("a" => "MMS_PRRegister"))
                            ->columns(array(new Expression("a.PRNo As TransNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                    CONVERT(varchar(10),a.PRDate,105) As Date,
                                    '' As Project,c.VendorName As Description,'BillReturn' As Type,
	                                CAST(0 As Decimal(18,5)) As Receipt, b.ReturnQty As Issue,
	                                CAST(0 As Decimal(18,5)) As ClosingStock,
	                                CAST(-b.ReturnQty As Decimal(18,5)) As Qty,
	                                CONVERT(varchar(10),a.CreatedDate,105) As CDate")))
                            ->join(array('b' => 'MMS_PRTrans'), 'a.PRRegisterId = b.PRRegisterId', array(), $unit8::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $unit8::JOIN_INNER)
                            ->where("b.ReturnQty > 0   AND a.PRDate<='29-Dec-2016'
                             AND a.CostCentreId IN ($cid)and b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid)");
                        $unit8->combine($unit7, 'Union ALL');


                        $unit9 = $sql->select();
                        $unit9->from(array("p" => $unit8))
                            ->columns(array(new Expression("p.TransNo,p.ResourceId,p.ItemId,
                                    p.CostCentreId,CONVERT(varchar(10),p.Date,105) As Date,
                                    p.Project,p.Description,p.Type,
                                    CAST(p.Receipt As Decimal(18,5)) As Receipt,
                                    p.Issue,p.ClosingStock,CAST(p.Qty As Decimal(18,5)) As Qty,
                                    CONVERT(varchar(10),p.CDate,105) As CDate
                                    ")))
                            ->join(array('rv' => 'Proj_resource'), 'p.ResourceId=rv.ResourceId', array(), $unit9::JOIN_INNER)
                            ->where("rv.TypeId=2 and p.CostCentreId IN ($cid)and p.ResourceId IN ($resourceId) and
                                     p.ItemId IN($itemid)")
                            ->order("p.CDate Desc");
                        $statement = $sql->getSqlStringForSqlObject($unit9);
                        $resp['sel_Resources'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($resp));
                        return $response;
                        break;

                    case 'selectWarehouse':

                        $select = $sql->select();
                        $select->from(array("b" => "Proj_Resource"))
                            ->columns(array(new Expression("b.ResourceId,
                                    isnull(c.BrandId,0) As ItemId,case when isnull(c.BrandId,0)>0 then
                                    c.ItemCode+ '' +c.BrandName Else b.code+ ' - '+b.ResourceName End as ResourceCode
                                    ")))
                            ->join(array('c' => 'MMS_Brand'), 'b.ResourceId=c.ResourceId', array(), $select::JOIN_LEFT)
                            ->where("b.typeid='2'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp['sel_whResource'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($resp));
                        return $response;
                        break;

                    case 'selectwareResource':

                        $unit1 = $sql->select();
                        $unit1->from(array("a" => "MMS_PRRegister"))
                            ->columns(array(new Expression("a.PRNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                     CONVERT(varchar(10),a.PRDate,105) As PRDate,
                                     oc.CostCentreName As CostCentre,'' As ToCC,c.VendorName,'BillReturn' As Type,
                                     CAST(0 As Decimal(18,5)) As Receipt, b.ReturnQty,CAST(0 As Decimal(18,5)) As ClosingStock,
                                     -b.ReturnQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate,e.WareHouseId")))
                            ->join(array('b' => 'MMS_PRTrans'), 'a.PRRegisterId = b.PRRegisterId', array(), $unit1::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $unit1::JOIN_INNER)
                            ->join(array('d' => 'MMS_PRWareHouseTrans'), 'b.PRTransId=d.PRTransId', array(), $unit1::JOIN_INNER)
                            ->join(array('e' => 'MMS_WareHouseDetails'), 'd.WareHouseId=e.TransId', array(), $unit1::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 'a.CostCentreId=oc.CostCentreId', array(), $unit1::JOIN_INNER)
                            ->where(new Expression("b.ReturnQty > 0 AND a.PRDate<='02-Jan-2017' AND e.WareHouseId IN ($cid)
                                                  And b.ResourceId IN ($resourceId) and b.ItemId IN($itemid)"));

                        $unit2 = $sql->select();
                        $unit2->from(array("a" => "MMS_TransferRegister"))
                            ->columns(array(new Expression("a.TVNo,b.ResourceId,b.ItemId,a.ToCostCentreId,
                                     CONVERT(varchar(10),a.TVDate,105) As TVDate,
                                     oc.CostCentreName As CostCentre,oc1.CostCentreName As ToCC,'','Transfer' As Type,
                                      b.RecdQty,CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                      b.RecdQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate,d.WareHouseId")))
                            ->join(array('b' => 'MMS_TransferTrans'), 'a.TVRegisterId = b.TransferRegisterId', array(), $unit2::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 'a.FromCostCentreId=oc.CostCentreId', array(), $unit2::JOIN_INNER)
                            ->join(array('oc1' => 'WF_OperationalCostCentre'), 'a.ToCostCentreId=oc1.CostCentreId', array(), $unit2::JOIN_INNER)
                            ->join(array('c' => 'MMS_TransferWareHouseTrans'), 'b.TransferTransId=c.TransferTransId And a.ToCostCentreId=c.CostCentreId', array(), $unit2::JOIN_INNER)
                            ->join(array('d' => 'MMS_WareHouseDetails'), 'c.WareHouseId=d.TransId', array(), $unit2::JOIN_INNER)
                            ->where(new Expression("b.RecdQty > 0 AND a.TVDate<='02-Jan-2017' AND
                            d.WareHouseId IN ($cid) and b.ResourceId IN ($resourceId) and b.ItemId IN($itemid)"));
                        $unit2->combine($unit1, 'Union ALL');


                        $unit3 = $sql->select();
                        $unit3->from(array("a" => "MMS_TransferRegister"))
                            ->columns(array(new Expression("a.TVNo,b.ResourceId,b.ItemId,a.FromCostCentreId,
                                        CONVERT(varchar(10),a.TVDate,105) As TVDate,
                                     oc1.CostCentreName As CostCentre, oc.CostCentreName As ToCC, '','Transfer' As Type,
                                     CAST(0 As Decimal(18,5)) As Receipt,b.TransferQty,CAST(0 As Decimal(18,5)) As ClosingStock,
                                     -b.TransferQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate,d.WareHouseId")))
                            ->join(array('b' => 'MMS_TransferTrans'), 'a.TVRegisterId = b.TransferRegisterId', array(), $unit3::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 'a.ToCostCentreId=oc.CostCentreId', array(), $unit3::JOIN_INNER)
                            ->join(array('oc1' => 'WF_OperationalCostCentre'), 'a.FromCostCentreId=oc1.CostCentreId', array(), $unit3::JOIN_INNER)
                            ->join(array('c' => 'MMS_TransferWareHouseTrans'), 'b.TransferTransId=c.TransferTransId And a.FromCostCentreId=c.CostCentreId', array(), $unit3::JOIN_INNER)
                            ->join(array('d' => 'MMS_WareHouseDetails'), 'c.WareHouseId=d.TransId', array(), $unit3::JOIN_INNER)
                            ->where(new Expression("b.TransferQty > 0 AND a.TVDate<='02-Jan-2017' AND
                             d.WareHouseId IN ($cid) And b.ResourceId IN ($resourceId) and b.ItemId IN($itemid) "));
                        $unit3->combine($unit2, 'Union ALL');


                        $unit4 = $sql->select();
                        $unit4->from(array("a" => "MMS_IssueRegister"))
                            ->columns(array(new Expression("a.IssueNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                        CONVERT(varchar(10),a.IssueDate,105) As IssueDate,
                                        oc.CostCentreName As CostCentre,'' As ToCC,
                                        Case When c.VendorName <> '' Then c.VendorName Else 'Internal' End VendorName,
                                        'Issue-Return' As Type,b.IssueQty,
                                        CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                        b.IssueQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate,e.WareHouseId")))
                            ->join(array('b' => 'MMS_IssueTrans'), 'a.IssueRegisterId = b.IssueRegisterId', array(), $unit4::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.ContractorId=c.VendorId', array(), $unit4::JOIN_LEFT)
                            ->join(array('d' => 'MMS_IssueWareHouseTrans'), 'b.IssueTransId=d.IssueTransId', array(), $unit4::JOIN_INNER)
                            ->join(array('e' => 'MMS_WareHouseDetails'), 'd.WareHouseId=e.TransId', array(), $unit4::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 'a.CostCentreId=oc.CostCentreId', array(), $unit4::JOIN_INNER)
                            ->where(new Expression("b.IssueOrReturn='R' And b.IssueQty > 0 AND
                                    a.IssueDate<='02-Jan-2017' AND e.WareHouseId IN ($cid) and
                                    a.OwnOrCSM=0 and b.ResourceId IN ($resourceId) and
                                    b.ItemId IN($itemid) "));
                        $unit4->combine($unit3, 'Union ALL');


                        $unit5 = $sql->select();
                        $unit5->from(array("a" => "MMS_IssueRegister"))
                            ->columns(array(new Expression("a.IssueNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                    CONVERT(varchar(10),a.IssueDate,105) As IssueDate,
                                    oc.CostCentreName As CostCentre,'' As ToCC,
                                    Case When c.VendorName <> '' Then c.VendorName Else 'Internal' End VendorName, 'Issue' As Type,
                                    CAST(0 As Decimal(18,5)) As Receipt,b.IssueQty, CAST(0 As Decimal(18,5)) As ClosingStock,
                                    -b.IssueQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate,e.WareHouseId")))
                            ->join(array('b' => 'MMS_IssueTrans'), 'a.IssueRegisterId = b.IssueRegisterId', array(), $unit5::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.ContractorId=c.VendorId', array(), $unit5::JOIN_LEFT)
                            ->join(array('d' => 'MMS_IssueWareHouseTrans'), 'b.IssueTransId=d.IssueTransId', array(), $unit5::JOIN_INNER)
                            ->join(array('e' => 'MMS_WareHouseDetails'), 'd.WareHouseId=e.TransId', array(), $unit5::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 'a.CostCentreId=oc.CostCentreId', array(), $unit5::JOIN_INNER)
                            ->where(new Expression("b.IssueOrReturn='I' And b.IssueQty > 0  AND
                                    a.IssueDate<='02-Jan-2017' AND e.WareHouseId in ($cid) And a.OwnOrCSM=0
                                    and b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid)"));
                        $unit5->combine($unit4, 'Union ALL');


                        $unit6 = $sql->select();
                        $unit6->from(array("a" => "MMS_DCRegister"))
                            ->columns(array(new Expression("a.DCNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                    CONVERT(varchar(10),a.DCDate,105) As DCDate,
                                    oc.CostCentreName As CostCentre,'' As ToCC,c.VendorName,
                                    'DC' As [Type],b.AcceptQty,CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                    b.AcceptQty,CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate,f.WareHouseId")))
                            ->join(array('b' => 'MMS_DCTrans'), 'a.DCRegisterId=b.DCRegisterId', array(), $unit6::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $unit6::JOIN_INNER)
                            ->join(array('d' => 'MMS_DCGroupTrans'), 'a.DCRegisterId=d.DCRegisterId And b.DCGroupId=d.DCGroupId', array(), $unit6::JOIN_INNER)
                            ->join(array('e' => 'MMS_DCWareHouseTrans'), 'd.DCGroupId=e.DCGroupId', array(), $unit6::JOIN_INNER)
                            ->join(array('f' => 'MMS_WareHouseDetails'), 'e.WareHouseId=f.TransId', array(), $unit6::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 'a.CostCentreId=oc.CostCentreId', array(), $unit6::JOIN_INNER)
                            ->where(new Expression("b.AcceptQty > 0 AND
                                    a.DCDate<='02-Jan-2017' AND f.WareHouseId in ($cid) And a.DcOrCSM=1 and
                                    b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid) "));
                        $unit6->combine($unit5, 'Union ALL');

                        $unit7 = $sql->select();
                        $unit7->from(array("a" => "MMS_PVRegister"))
                            ->columns(array(new Expression("a.PVNo,b.ResourceId,b.ItemId,a.CostCentreId,
                                    CONVERT(varchar(10),a.PVDate,105) As Date,
                                    oc.CostCentreName As CostCentre,
                                    '' As ToCC,c.VendorName,'Purchase' As Type,
                                    Case When a.ThruDC='Y' Then b.ActualQty Else b.BillQty End BillQty,
                                    CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                    Case When a.ThruDC='Y' Then b.ActualQty Else b.BillQty End BillQty,
                                    CONVERT(varchar(10),a.CreatedDate,105) As CreatedDate,f.WareHouseId")))
                            ->join(array('b' => 'MMS_PVTrans'), 'a.PVRegisterId=b.PVRegisterId', array(), $unit7::JOIN_INNER)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $unit7::JOIN_INNER)
                            ->join(array('d' => 'MMS_PVGroupTrans'), 'a.PVRegisterId=d.PVRegisterId And b.PVGroupId=d.PVGroupId', array(), $unit7::JOIN_INNER)
                            ->join(array('e' => 'MMS_PVWHTrans'), 'd.PVGroupId=e.PVGroupId', array(), $unit7::JOIN_INNER)
                            ->join(array('f' => 'MMS_WareHouseDetails'), 'e.WareHouseId=f.TransId', array(), $unit7::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 'a.CostCentreId=oc.CostCentreId', array(), $unit7::JOIN_INNER)
                            ->where(new Expression(" b.DCRegisterId=0 And b.BillQty > 0 AND
                                    a.PVDate<='02-Jan-2017' AND f.WareHouseId in ($cid) and
                                    b.ResourceId IN ($resourceId) and
                                     b.ItemId IN($itemid)"));
                        $unit7->combine($unit6, 'Union ALL');


                        $unit8 = $sql->select();
                        $unit8->from(array("s" => "MMS_Stock"))
                            ->columns(array(new Expression("distinct '' As TransNo,s.ResourceId,s.ItemId,
                                     s.CostCentreId,null As Date,oc.CostCentreName As CostCentre,'' As ToCC,
                                    'WHTransfer' As Description, '' As Type,CAST(0 As Decimal(18,5)) As Receipt,
                                     ABS(WHTQty) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                     WHTQty As Qty,null As CDate,c.WareHouseId")))
                            ->join(array('b' => 'MMS_StockTrans'), 's.StockId=b.StockId', array(), $unit8::JOIN_INNER)
                            ->join(array('c' => 'MMS_WareHouseDetails'), 'b.WareHouseId=c.TransId', array(), $unit8::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 's.CostCentreId=oc.CostCentreId', array(), $unit8::JOIN_INNER)
                            ->where(new Expression("WHTQty < 0 And c.WareHouseId in ($cid) and s.ResourceId IN ($resourceId) and
                                     s.ItemId IN($itemid)"));
                        $unit8->combine($unit7, 'Union ALL');

                        $unit9 = $sql->select();
                        $unit9->from(array("s" => "MMS_Stock"))
                            ->columns(array(new Expression("distinct '' As TransNo,s.ResourceId,s.ItemId,
                                     s.CostCentreId,null As Date,oc.CostCentreName As CostCentre,'' As ToCC,
                                    'WHTransfer' As Description, '' As Type,ABS(WHTQty) As Receipt,
                                     CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                     ABS(WHTQty) As Qty,null As CDate,c.WareHouseId")))
                            ->join(array('b' => 'MMS_StockTrans'), 's.StockId=b.StockId', array(), $unit9::JOIN_INNER)
                            ->join(array('c' => 'MMS_WareHouseDetails'), 'b.WareHouseId=c.TransId', array(), $unit9::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 's.CostCentreId=oc.CostCentreId', array(), $unit9::JOIN_INNER)
                            ->where(new Expression("WHTQty > 0 And c.WareHouseId in ($cid) and
                            s.ResourceId IN ($resourceId) and
                                     s.ItemId IN($itemid)"));
                        $unit9->combine($unit8, 'Union ALL');

                        $unit10 = $sql->select();
                        $unit10->from(array("s" => "MMS_Stock"))
                            ->columns(array(new Expression("distinct '' As TransNo,s.ResourceId,s.ItemId,
                                     s.CostCentreId,null As Date,oc.CostCentreName As CostCentre,'' As ToCC,
                                    'OpeningStock' As Description, '' As Type,CAST(b.OpeningStock AS decimal(18,5)) As Receipt,
                                     CAST(0 As Decimal(18,5)) As Issue,CAST(0 As Decimal(18,5)) As ClosingStock,
                                     CAST(b.OpeningStock AS decimal(18,5)) As Qty,null As CDate,c.WareHouseId")))
                            ->join(array('b' => 'MMS_StockTrans'), 's.StockId=b.StockId', array(), $unit10::JOIN_INNER)
                            ->join(array('c' => 'MMS_WareHouseDetails'), 'b.WareHouseId=c.TransId', array(), $unit10::JOIN_INNER)
                            ->join(array('oc' => 'WF_OperationalCostCentre'), 's.CostCentreId=oc.CostCentreId', array(), $unit10::JOIN_INNER)
                            ->where(new Expression("s.OpeningStock > 0 And c.WareHouseId in ($cid)
                             and s.ResourceId IN ($resourceId) and
                                     s.ItemId IN($itemid)"));
                        $unit10->combine($unit9, 'Union ALL');


                        $main = $sql->select();
                        $main->from(array("a" => $unit10))
                            ->columns(array(new Expression("a.TransNo,a.ResourceId,a.ItemId,a.CostCentreId,
                                      CONVERT(varchar(10),a.Date,105) As Date,
                                      a.CostCentre,a.ToCC,a.Description,
                                      a.Type,CAST(a.Receipt As Decimal(18,5)) As Receipt,a.Issue,
                                      a.ClosingStock,CAST(a.Qty As Decimal(18,5)) As Qty,
                                      CONVERT(varchar(10),a.CDate,105) As CDate
                                      ")))
                            ->join(array('rv' => 'Proj_Resource'), 'a.ResourceId=rv.ResourceId', array(), $main::JOIN_INNER)
                            ->where(new Expression("rv.TypeId=2 and  a.ResourceId IN ($resourceId) and
                                     a.ItemId IN($itemid) Order By a.CDate "));
                        $statement = $sql->getSqlStringForSqlObject($main);
                        $resp['sel_wareResources'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $qty=0;
                        foreach($resp['sel_wareResources'] As &$arr_qty){
                            $qty+=$arr_qty['Qty'];
                            $arr_qty['ClosingStock'] = $qty;

                        }
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($resp));
                        return $response;
                        break;

                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;

                }
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
            // getting the  cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // getting the  warehouse details
            $select = $sql->select();
            $select->from(array('a' => 'MMS_WareHouse'))
                ->columns(array('WareHouseId', 'WareHouseName','WareHouseNo'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
    public function monthWiseStockstatementAction(){
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
				$RequestType= $this->bsf->isNullCheck($postParams['RequestType'],'string'); 
				$CostCentreId =$this->params()->fromPost('CostCentreId');
                $fromMonth= $this->bsf->isNullCheck($postParams['fromMonth'],'string');
				if($fromMonth!=''){
					$Fdate=date('Y-m-t', strtotime($fromMonth)); 
					$Ldate=date('Y-m-01', strtotime($fromMonth)); 
					$Cdate=date('Y-m-d', strtotime($fromMonth . ' - 1 days'));
				}
                switch($Type) {  			        
               
                    case 'cost':
						if($RequestType==1){
							$select1 = $sql->select();
							$select1->from(array("S" => "MMS_Stock"))
									->columns(array(
										'ResourceId' => new Expression('S.ResourceId'),
										'ItemId' => new Expression("S.ItemId"),
										'CostCentreId' => new Expression("S.CostCentreId"),
										'Qty' => new Expression("S.OpeningStock"),						
									))								
								->where (array("S.OpeningStock > 0")); 
								
							$select2 = $sql->select();
							$select2->from(array("A" => "MMS_PVRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End"),						
									))
									->join(array("B" => "MMS_PVTrans"), "A.PVRegisterId=B.PVRegisterId", array(), $select2::JOIN_INNER)									
								->where (array("B.BillQty > 0 AND A.PVDate<=' $Fdate ' ")); 			
							$select2->combine($select1,'Union ALL');
							
							$select3 = $sql->select();
							$select3->from(array("A" => "MMS_DCRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("B.BalQty"),						
									))
									->join(array("B" => "MMS_DCTrans"), "A.DCRegisterId=B.DCRegisterId", array(), $select3::JOIN_INNER)									
								->where (array("B.BalQty > 0  AND A.DCDate<=' $Fdate' And DcOrCSM=1")); 			
							$select3->combine($select2,'Union ALL');
							
							$select4 = $sql->select();
							$select4->from(array("A" => "MMS_TransferRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.FromCostCentreId"),
										'Qty' => new Expression("-B.TransferQty"),						
									))
									->join(array("B" => "MMS_TransferTrans"), "A.TVRegisterId = B.TransferRegisterId", array(), $select4::JOIN_INNER)									
								->where (array("B.TransferQty > 0  AND A.TVDate <=' $Fdate '")); 			
							$select4->combine($select3,'Union ALL');
							
							$select5 = $sql->select();
							$select5->from(array("A" => "MMS_TransferRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.ToCostCentreId"),
										'Qty' => new Expression("B.RecdQty"),						
									))
									->join(array("B" => "MMS_TransferTrans"), "A.TVRegisterId = B.TransferRegisterId", array(), $select5::JOIN_INNER)									
								->where (array("B.RecdQty > 0 AND A.TVDate <=' $Fdate'")); 			
							$select5->combine($select4,'Union ALL');
							
							$select6 = $sql->select();
							$select6->from(array("A" => "MMS_PRRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("-B.ReturnQty"),						
									))
									->join(array("B" => "MMS_PRTrans"), "A.PRRegisterId = B.PRRegisterId", array(), $select6::JOIN_INNER)									
								->where (array("B.ReturnQty > 0 AND A.PRDate <=' $Fdate'")); 			
							$select6->combine($select5,'Union ALL');
							
							$select7 = $sql->select();
							$select7->from(array("A" => "MMS_IssueRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("-B.IssueQty"),						
									))
									->join(array("B" => "MMS_IssueTrans"), "A.IssueRegisterId = B.IssueRegisterId", array(), $select7::JOIN_INNER)									
								->where (array("B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<=' $Fdate' And A.OwnOrCSM=0")); 			
							$select7->combine($select6,'Union ALL');
							
							$select8 = $sql->select();
							$select8->from(array("A" => "MMS_IssueRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("B.IssueQty"),						
									))
									->join(array("B" => "MMS_IssueTrans"), "A.IssueRegisterId = B.IssueRegisterId", array(), $select8::JOIN_INNER)									
								->where (array("B.IssueOrReturn='R' And B.IssueQty > 0  AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0")); 			
							$select8->combine($select7,'Union ALL');
							
							$select9 = $sql->select();
							$select9->from(array("A" => $select8))
									->columns(array(
										'ResourceId' => new Expression('A.ResourceId'),
										'ItemId' => new Expression("A.ItemId"),							
										'Qty' => new Expression("ISNULL(SUM(Qty),0)"),						
									))													
								->where (array("A.CostCentreId=$CostCentreId GROUP BY A.ResourceId, A.ItemId")); 	
							
							$select10 = $sql->select();
							$select10->from(array("A" => "MMS_PVTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("Case When B.ThruDC='Y' Then ISNULL(SUM(ActualQty),0) Else ISNULL(SUM(BillQty),0) End"),						
										'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then ISNULL(SUM(A.ActualQty*A.GrossRate),0) Else ISNULL(SUM(A.BillQty*A.GrossRate),0) End Else Case When B.ThruDC='Y' Then ISNULL(SUM(A.ActualQty*A.QRate),0) Else ISNULL(SUM(A.BillQty*A.QRate),0) End End"),						
									))
									->join(array("B" => "MMS_PVRegister"), "A.PVRegisterId=B.PVRegisterId", array(), $select10::JOIN_INNER)									
									->join(array("OC" => "WF_OperationalCostCentre"), "B.CostCentreId=OC.CostCentreId", array(), $select10::JOIN_INNER)									
									->join(array("VM" => "Vendor_Master"), "B.VendorId=VM.VendorId", array(), $select10::JOIN_INNER)									
									->join(array("CM" => "WF_CityMaster"), "VM.CityId=CM.CityId", array(), $select10::JOIN_LEFT)									
									->join(array("CC" => "WF_CostCentre"), "OC.FACostCentreId=CC.CostCentreId", array(), $select10::JOIN_INNER)									
									->join(array("SM" => "WF_StateMaster"), "CC.StateId=SM.StateId", array(), $select10::JOIN_INNER)									
								->where (array("A.BillQty>0 AND B.PVDate<=' $Fdate ' GROUP BY ResourceId,ItemId,B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId")); 
						
							$select11 = $sql->select();
							$select11->from(array("A" => "MMS_DCTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(A.BalQty),0)"),						
										'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then ISNULL(SUM(A.BalQty*A.GrossRate),0) Else ISNULL(SUM(A.BalQty*A.QRate),0) End"),						
									))
									->join(array("B" => "MMS_DCRegister"), "A.DCRegisterId=B.DCRegisterId", array(), $select11::JOIN_INNER)									
									->join(array("OC" => "WF_OperationalCostCentre"), "B.CostCentreId=OC.CostCentreId", array(), $select11::JOIN_INNER)									
									->join(array("VM" => "Vendor_Master"), "B.VendorId=VM.VendorId", array(), $select11::JOIN_INNER)									
									->join(array("CM" => "WF_CityMaster"), "VM.CityId=CM.CityId", array(), $select11::JOIN_LEFT)									
									->join(array("CC" => "WF_CostCentre"), "OC.FACostCentreId=CC.CostCentreId", array(), $select11::JOIN_INNER)									
									->join(array("SM" => "WF_StateMaster"), "CC.StateId=SM.StateId", array(), $select11::JOIN_INNER)									
								->where (array("A.BalQty>0 AND B.DCDate<=' $Fdate ' And DcOrCSM=1  GROUP BY ResourceId,ItemId,B.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId")); 
							$select11->combine($select10,'Union ALL');
							
							$select12 = $sql->select();
							$select12->from(array("A" => "MMS_TransferTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(-A.TransferQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(-A.Amount),0)"),						
									))
									->join(array("B" => "MMS_TransferRegister"), "B.TVRegisterId = A.TransferRegisterId", array(), $select12::JOIN_INNER)																							
								->where (array("A.TransferQty>0 AND  B.TVDate <=' $Fdate ' GROUP BY ResourceId,ItemId")); 
							$select12->combine($select11,'Union ALL');
							
							$select13 = $sql->select();
							$select13->from(array("A" => "MMS_TransferTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(A.RecdQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(A.RecdQty*A.Rate),0)"),						
									))
									->join(array("B" => "MMS_TransferRegister"), "B.TVRegisterId = A.TransferRegisterId", array(), $select13::JOIN_INNER)																							
								->where (array("A.TransferQty>0 AND B.TVDate <=' $Fdate ' GROUP BY ResourceId,ItemId")); 
							$select13->combine($select12,'Union ALL');
							
							$select14 = $sql->select();
							$select14->from(array("A" => "MMS_PRTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(-A.ReturnQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(-Amount),0)"),						
									))
									->join(array("B" => "MMS_PRRegister"), "B.PRRegisterId = A.PRRegisterId", array(), $select14::JOIN_INNER)																							
								->where (array("A.ReturnQty>0 AND B.PRDate <=' $Fdate ' GROUP BY ResourceId,ItemId")); 
							$select14->combine($select13,'Union ALL');
							
							$select15 = $sql->select();
							$select15->from(array("A" => "MMS_IssueTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(-A.IssueQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(-Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End),0)"),						
									))
									->join(array("B" => "MMS_IssueRegister"), "B.IssueRegisterId = A.IssueRegisterId", array(), $select15::JOIN_INNER)																							
								->where (array("A.IssueQty>0 AND B.IssueDate <=' $Fdate ' And A.IssueOrReturn='I'   GROUP BY ResourceId,ItemId")); 
							$select15->combine($select14,'Union ALL');
							
							$select16 = $sql->select();
							$select16->from(array("A" => "MMS_IssueTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(A.IssueQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End),0)"),						
									))
									->join(array("B" => "MMS_IssueRegister"), "B.IssueRegisterId = A.IssueRegisterId", array(), $select16::JOIN_INNER)																							
								->where (array("A.IssueQty>0 AND B.IssueDate <=' $Fdate ' And A.IssueOrReturn='R'   GROUP BY ResourceId,ItemId")); 
							$select16->combine($select15,'Union ALL');
							
							$select17 = $sql->select();
							$select17->from(array("A" => "MMS_Stock"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("OpeningStock"),						
										'Amount' => new Expression("OpeningStock*ORate"),						
									))																									
								->where (array("OpeningStock>0")); 
							$select17->combine($select16,'Union ALL');
							
							$select18 = $sql->select();
							$select18->from(array("A" => $select17))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(Qty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(Amount),0)"),						
									));																								
							$select18->group(array (new Expression("ResourceId,ItemId")));
							
							$select19 = $sql->select();
							$select19->from(array("A" => $select18))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'AvgRate' => new Expression("(Case When Amount > 0 Then Amount Else null End/ Case When Qty > 0 Then Qty Else null End)"),												
									));	
							
							$select20 = $sql->select();
							$select20->from(array("A" => $select9))
									->columns(array(
										'ResourceGroupId' => new Expression('Distinct RG.ResourceGroupId'),
										'ResourceId' => new Expression(' A.ResourceId'),
										'ItemId' => new Expression("A.ItemId"),								
										'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),								
										'Resource' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End"),								
										'Unit' => new Expression("B.UnitId"),								
										'Op.Balance' => new Expression("SK.OpeningStock"),						
										'ORate' => new Expression("CAST(SK.ORate As Decimal(18,3))"),						
										'OValue' => new Expression("CAST(SK.OpeningStock*SK.ORate As Decimal(18,3))"),						
										'MOpeningBal' => new Expression("(Select (G1.OpeningStock+G1.DC+G1.Bill+G1.Issue+G1.IssueRet+G1.TransferOut+G1.TransferIn+G1.BillReturn)  MonthOpeningStock from 
																		(Select SUM(ISNULL(G.OpeningStock,0)) OpeningStock,SUM(ISNULL(G.DC,0)) DC,SUM(ISNULL(G.Bill,0)) Bill,SUM(ISNULL(G.Issue,0)) Issue,SUM(ISNULL(G.IssueRet,0)) IssueRet,SUM(ISNULL(G.TransferOut,0)) TransferOut,SUM(ISNULL(G.TransferIn,0)) TransferIn,SUM(ISNULL(G.BillReturn,0)) BillReturn From 
																		(Select ISNULL(OpeningStock,0) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn  From MMS_Stock  
																		WHere CostCentreId=$CostCentreId And ResourceId=C.ResourceId And ItemId=C.ItemId     
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,ISNULL(B.AcceptQty,0) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_DCRegister A  
																		Inner Join MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId And A.DCOrCSM=1 
																		WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.DCDate <= ' $Cdate ' 
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,ISNULL(B.BillQty,0) Bill,-CAST(0 As Decimal(18,3))  Issue, 
																		CAST(0 As Decimal(18,3))  IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_PVRegister A  
																		Inner Join MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId And A.ThruDC='N' 
																		WHere A.CostCentreId=$CostCentreId  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate <= ' $Cdate ' 
																		Union All 
																		Select CAST(0 As Decimal(18,3))  OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-ISNULL(B.IssueQty,0) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_IssueRegister A  
																		Inner Join MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And B.IssueOrReturn='I' And A.OWNOrCSM=0 
																		And A.IssueDate <= ' $Cdate '    
																		Union All 
																		Select CAST(0 As Decimal(18,3))  OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		ISNULL(B.IssueQty,0) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_IssueRegister A  
																		Inner Join MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId
																		WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And B.IssueOrReturn='R' And A.OWNOrCSM=0 And A.IssueDate <= '$Cdate'         
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-ISNULL(B.TransferQty,0) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_TransferRegister A  
																		Inner Join MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId WHere A.FromCostCentreId=$CostCentreId  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																		And A.TVDate <= ' $Cdate' 
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,ISNULL(B.RecdQty,0) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_TransferRegister A  
																		Inner Join MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId WHere A.ToCostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																		And A.TVDate <= ' $Cdate'         
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-ISNULL(B.ReturnQty,0) BillReturn From MMS_PRRegister A  
																		Inner Join MMS_PRTrans B  On A.PRRegisterId=B.PRRegisterId WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PRDate <= ' $Cdate ') G ) G1)"),	
										'MOpeningBalRate' => new Expression("(Select Case When G1.RecRate > 0 Then CAST(G1.RecRate As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End RecRate From 
																			(Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) RecRate,SUM(G.Qty) Qty From    
																			(Select B.BalQty Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then (isnull(B.BalQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BalQty,0)*isnull(B.QRate,0)) End Amount From MMS_DCRegister A     
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC   On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0  And DcOrCSM=1     
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.DCDate <= '$Cdate'           
																			Union All    
																			Select Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BillQty,0)*isnull(B.GrossRate,0)) End Else Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.QRate,0)) Else (isnull(B.BillQty,0)*isnull(B.QRate,0)) End End Amount From MMS_PVRegister A        
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate <= ' $Cdate '         
																			Union All  
																			Select B.RecdQty Qty, (isnull(B.RecdQty,0)*isnull(B.Rate,0)) Amount From MMS_TransferRegister A  
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId  
																			WHERE B.RecdQty > 0  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.TVDate <= '$Cdate'        
																			Union All  
																			Select B.IssueQty Qty,(isnull(B.IssueQty,0)*isnull(Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0)) Amount From MMS_IssueRegister A  
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId  
																			Where B.IssueOrReturn='R' And B.IssueQty>0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= '$Cdate'         
																			Union All 
																			SELECT SUM(-B.ReturnQty) Qty, SUM(-B.ReturnQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.ReturnQty),0) Amount FROM MMS_PRRegister A    
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId WHERE B.ReturnQty > 0  And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId And A.PRDate <= ' $Cdate '         
																			Union All  
																			SELECT SUM(-B.TransferQty) Qty,  SUM(-B.TransferQty)*isnull(SUM(B.GrossAmount),0)/nullif(SUM(B.TransferQty),0)  Amount FROM MMS_TransferRegister A      
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0  AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId And A.TVDate <= ' $Cdate'           
																			Union All  
																			SELECT SUM(-B.IssueQty) Qty,SUM(-B.IssueQty)*isnull(SUM( Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A     
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= ' $Cdate' ) G) G1 )"),	
										'MOpeningBalValue' => new Expression("(Select CAST(G1.Qty*Case When G1.RecRate>0 Then G1.RecRate Else 0 End As Decimal(18,3)) RecRate From
																			(Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) RecRate,SUM(G.Qty) Qty From    
																			(Select B.BalQty Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId) ) Then (isnull(B.BalQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BalQty,0)*isnull(B.QRate,0)) End  Amount From MMS_DCRegister A     
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0 And DcOrCSM=1     
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.DCDate <= ' $Cdate '           
																			Union All    
																			Select Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BillQty,0)*isnull(B.GrossRate,0)) End Else Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.QRate,0)) Else (isnull(B.BillQty,0)*isnull(B.QRate,0)) End End Amount From MMS_PVRegister A        
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate <= ' $Cdate '             
																			Union All  
																			Select B.RecdQty Qty,  (isnull(B.RecdQty,0)*isnull(B.Rate,0))  Amount From MMS_TransferRegister A  
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId  
																			WHERE B.RecdQty > 0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.TVDate <= ' $Cdate '                         
																			Union All  
																			Select B.IssueQty Qty,(isnull(B.IssueQty,0)*isnull(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0)) Amount From MMS_IssueRegister A   
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId  
																			Where B.IssueOrReturn='R' And B.IssueQty>0  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= ' $Cdate '                           
																			Union All 
																			SELECT SUM(-B.ReturnQty) Qty, SUM(-B.ReturnQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.ReturnQty),0) Amount FROM MMS_PRRegister A    
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId WHERE B.ReturnQty > 0 And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId And A.PRDate <= ' $Cdate '              
																			Union All  
																			SELECT SUM(-B.TransferQty) Qty,  SUM(-B.TransferQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0)  Amount FROM MMS_TransferRegister A      
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0   AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId And A.TVDate <= ' $Cdate '                         
																			Union All  
																			SELECT SUM(-B.IssueQty) Qty,SUM(-B.IssueQty)*isnull(SUM(B.IssueAmount),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A     
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= ' $Cdate ') G) G1)"),
										'MonthReceipt' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From 
																			(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																			(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC   On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0                           
																			AND A.DCDate BETWEEN ('$Ldate') And (' $Fdate') And DcOrCSM=1   
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                         
																			Union All 
																			SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																			Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0  
																			AND A.PVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),	
										'MonthReceiptRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																			(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																			(SELECT ISNULL(SUM(B.BalQty),0) Qty,Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A   
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0   
																			AND A.DCDate BETWEEN (' $Ldate ') And (' $Fdate ') And DcOrCSM=1   
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId 
																			Union All 
																			SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																			Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0  
																			AND A.PVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'MonthReceiptValue' => new Expression("(Select CAST(G1.Qty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																				(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																				(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																				INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																				INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																				INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																				LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																				INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																				INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																				Where B.BalQty > 0   
																				AND A.DCDate BETWEEN (' $Ldate ') And (' $Fdate ') And DcOrCSM=1   
																				And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                     
																				Union All 
																				SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																				Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																				INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																				INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																				INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																				LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																				INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																				INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																				WHERE  B.BillQty > 0  
																				AND A.PVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'Receipt' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From 
																	(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																	(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																	INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																	INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																	INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																	LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																	INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																	INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																	Where B.BalQty > 0   
																	AND A.DCDate<=' $Fdate ' And DcOrCSM=1   
																	And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,CM.StateId,SM.StateId                          
																	Union All 
																	SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																	Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																	INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																	INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																	INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																	LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																	INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																	INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																	WHERE  B.BillQty > 0  
																	AND A.PVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'ReceiptRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																		(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																		(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																		INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		Where B.BalQty > 0   
																		AND A.DCDate<=' $Fdate ' And DcOrCSM=1   
																		And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                           
																		Union All 
																		SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																		Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																		INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		WHERE  B.BillQty > 0  
																		AND A.PVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'ReceiptValue' => new Expression("(Select CAST(G1.Qty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																		(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																		(SELECT ISNULL(SUM(B.BalQty),0) Qty,Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A   
																		INNER JOIN MMS_DCTrans B   On A.DCRegisterId=B.DCRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		Where B.BalQty > 0   
																		AND A.DCDate<=' $Fdate ' And DcOrCSM=1   
																		And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                           
																		Union All 
																		SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																		Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																		INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC   On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		WHERE  B.BillQty > 0  
																		AND A.PVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'MonthBillReturn' => new Expression("(Select CAST(G1.ReturnQty As Decimal(18,5)) ReturnQty From 
																			(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																			(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																			WHERE B.ReturnQty > 0 AND A.PRDate BETWEEN ('$Ldate') And ('$Fdate') And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'MonthBillReturnRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																				(Select -G.ReturnQty ReturnQty,G.Amount/G.ReturnQty Rate From 
																				(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																				INNER JOIN MMS_PRTrans B   ON A.PRRegisterId = B.PRRegisterId  
																				WHERE B.ReturnQty > 0 AND A.PRDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'MonthBillReturnValue' => new Expression("(Select CAST(G1.ReturnQty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																				(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																				(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																				INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																				WHERE B.ReturnQty > 0 AND A.PRDate BETWEEN (' $Ldate ') And (' $Fdate ')  And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'BillReturn' => new Expression("(Select CAST(G1.ReturnQty As Decimal(18,5)) ReturnQty From 
																		(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																		(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																		INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																		WHERE B.ReturnQty > 0 AND A.PRDate<=' $Fdate ' And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'BillReturnRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																			(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																			(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																			WHERE B.ReturnQty > 0 AND A.PRDate<=' $Fdate ' And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'BillReturnValue' => new Expression("(Select CAST(G1.ReturnQty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																			(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																			(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																			WHERE B.ReturnQty > 0 AND A.PRDate<=' $Fdate '  And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'MonthTransfer' => new Expression("(Select CAST(G1.TransferQty As Decimal(18, 5 )) TransferQty  From 
																			(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																			(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount) Amount  FROM MMS_TransferRegister A    
																			INNER JOIN MMS_TransferTrans B   On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ') AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                           
																			Union All SELECT  SUM(B.RecdQty) TransferQty, SUM(B.RecdQty*B.Rate)  Amount FROM MMS_TransferRegister A   
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ')  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'MonthTransferRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate  From 
																			(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																			(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount)  Amount  FROM MMS_TransferRegister A    
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ') AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                             
																			Union All SELECT  SUM(B.RecdQty) TransferQty,  SUM(B.RecdQty*B.Rate)  Amount FROM MMS_TransferRegister A  
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ')  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'MonthTransferValue' => new Expression("(Select CAST((G1.TransferQty*ABS(G1.Rate)) As Decimal(18,3)) Amount  From 
																			(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																			(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount) Amount  FROM MMS_TransferRegister A    
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ')  AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                            
																			Union All SELECT  SUM(B.RecdQty) TransferQty,SUM(B.RecdQty*B.Rate) Amount FROM MMS_TransferRegister A   
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'Transfer' => new Expression("(Select CAST(G1.TransferQty As Decimal(18, 5 )) TransferQty  From 
																	(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																	(SELECT SUM(-B.TransferQty) TransferQty, SUM(B.Amount) Amount  FROM MMS_TransferRegister A    
																	INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate<=' $Fdate ' AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                             
																	Union All SELECT  SUM(B.RecdQty) TransferQty,SUM(B.RecdQty*B.Rate) Amount FROM MMS_TransferRegister A   
																	INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'TransferRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate  From 
																		(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																		(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount)  Amount  FROM MMS_TransferRegister A    
																		INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate<=' $Fdate ' AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                           
																		Union All SELECT  SUM(B.RecdQty) TransferQty, SUM(B.RecdQty*B.Rate)  Amount FROM MMS_TransferRegister A   
																		INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate<=' $Fdate '  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'TransferValue' => new Expression("(Select CAST((G1.TransferQty*ABS(G1.Rate)) As Decimal(18,3)) Amount  From 
																		(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																		(SELECT SUM(-B.TransferQty) TransferQty, SUM(B.Amount) Amount FROM MMS_TransferRegister A    
																		INNER JOIN MMS_TransferTrans B   On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate<=' $Fdate '  AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                           
																		Union All SELECT  SUM(B.RecdQty) TransferQty,SUM(B.RecdQty*B.Rate) Amount FROM MMS_TransferRegister A   
																		INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'Month-Issue/Return' => new Expression("(Select CAST(G1.IssueQty As Decimal(18, 5 )) IssueQty From 
																			(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																			(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate  
																			BETWEEN (' $Ldate ') And (' $Fdate ')  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                          
																			Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																			AND A.IssueDate BETWEEN (' $Ldate') And ('$Fdate') 
																			And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Month-Issue/ReturnRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate From 
																				(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																				(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate  
																				BETWEEN (' $Ldate ') And (' $Fdate ') And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                          
																				Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A     
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																				AND A.IssueDate BETWEEN (' $Ldate ') And (' $Fdate ')  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Month-Issue/ReturnValue' => new Expression("(Select CAST(G1.IssueQty*ABS(G1.Rate) As Decimal(18,3)) Amount From 
																				(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																				(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B   On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate  
																				BETWEEN (' $Ldate ') And (' $Fdate ') And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                          
																				Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																				AND A.IssueDate BETWEEN (' $Ldate ') And (' $Fdate ') And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Issue/Return' => new Expression("(Select CAST(G1.IssueQty As Decimal(18, 5 )) IssueQty From 
																		(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																		(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																		INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate <=' $Fdate '  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                         
																		Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																		INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																		AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Issue/ReturnRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate From 
																			(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																			(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                            
																			Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																			AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Issue/ReturnValue' => new Expression("(Select CAST(G1.IssueQty*ABS(G1.Rate) As Decimal(18,3)) Amount From 
																				(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																				(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<=' $Fdate '  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                
																				Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																				AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId)G)G1)"),
										'Net' => new Expression("CAST(Qty AS Decimal(18,3))"),
										'AvgRate' => new Expression("CAST(ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
										'AvgAmount' => new Expression("CAST(Qty*ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
										'LRate' => new Expression("SK.LRate"),
										'LAmount' => new Expression("CAST(Qty*ISNULL(SK.LRate,0) As Decimal(18,3))"),
										'Child' => new Expression("Case When A.ItemId <> 0 Then 0 Else 1 End"),
									))
									->join(array("B" => "Proj_Resource"), "A.ResourceId=B.ResourceId", array(), $select20::JOIN_INNER)																							
									->join(array("C" => $select19), "A.ResourceId=C.ResourceId And A.ItemId=C.ItemId", array(), $select20::JOIN_LEFT)																							
									->join(array("BR" =>"MMS_Brand"), "A.ItemId =BR.BrandId And A.ResourceId=BR.ResourceId", array(), $select20::JOIN_LEFT)																							
									->join(array("SK" =>"MMS_Stock"), "A.ItemId=SK.ItemId And A.ResourceId=SK.ResourceId", array(), $select20::JOIN_LEFT)																							
									->join(array("RV" =>"Proj_Resource"), "A.ResourceId=RV.ResourceId", array(), $select20::JOIN_INNER)																							
									->join(array("RG" =>"Proj_ResourceGroup"), "RV.ResourceGroupId=RG.ResourceGroupId", array(), $select20::JOIN_LEFT)																							
								->where (array("B.TypeId IN (2,3) ")); 
							
							$statement = $sql->getSqlStringForSqlObject($select20);
							$monthlystockdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();	
							
							$this->_view->setTerminal(true);
							$response = $this->getResponse()->setContent(json_encode($monthlystockdetails));
							return $response;
							break;
						}
						if($RequestType==2){
							$select1 = $sql->select();
							$select1->from(array("S" => "MMS_Stock"))
									->columns(array(
										'ResourceId' => new Expression('S.ResourceId'),
										'ItemId' => new Expression("S.ItemId"),
										'CostCentreId' => new Expression("S.CostCentreId"),
										'Qty' => new Expression("S.OpeningStock"),						
									))								
								->where (array("S.OpeningStock > 0")); 
								
							$select2 = $sql->select();
							$select2->from(array("A" => "MMS_PVRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End"),						
									))
									->join(array("B" => "MMS_PVTrans"), "A.PVRegisterId=B.PVRegisterId", array(), $select2::JOIN_INNER)									
								->where (array("B.BillQty > 0 AND A.PVDate<=' $Fdate ' ")); 			
							$select2->combine($select1,'Union ALL');
							
							$select3 = $sql->select();
							$select3->from(array("A" => "MMS_DCRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("B.BalQty"),						
									))
									->join(array("B" => "MMS_DCTrans"), "A.DCRegisterId=B.DCRegisterId", array(), $select3::JOIN_INNER)									
								->where (array("B.BalQty > 0  AND A.DCDate<=' $Fdate' And DcOrCSM=1")); 			
							$select3->combine($select2,'Union ALL');
							
							$select4 = $sql->select();
							$select4->from(array("A" => "MMS_TransferRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.FromCostCentreId"),
										'Qty' => new Expression("-B.TransferQty"),						
									))
									->join(array("B" => "MMS_TransferTrans"), "A.TVRegisterId = B.TransferRegisterId", array(), $select4::JOIN_INNER)									
								->where (array("B.TransferQty > 0  AND A.TVDate <=' $Fdate '")); 			
							$select4->combine($select3,'Union ALL');
							
							$select5 = $sql->select();
							$select5->from(array("A" => "MMS_TransferRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.ToCostCentreId"),
										'Qty' => new Expression("B.RecdQty"),						
									))
									->join(array("B" => "MMS_TransferTrans"), "A.TVRegisterId = B.TransferRegisterId", array(), $select5::JOIN_INNER)									
								->where (array("B.RecdQty > 0 AND A.TVDate <=' $Fdate'")); 			
							$select5->combine($select4,'Union ALL');
							
							$select6 = $sql->select();
							$select6->from(array("A" => "MMS_PRRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("-B.ReturnQty"),						
									))
									->join(array("B" => "MMS_PRTrans"), "A.PRRegisterId = B.PRRegisterId", array(), $select6::JOIN_INNER)									
								->where (array("B.ReturnQty > 0 AND A.PRDate <=' $Fdate'")); 			
							$select6->combine($select5,'Union ALL');
							
							$select7 = $sql->select();
							$select7->from(array("A" => "MMS_IssueRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("-B.IssueQty"),						
									))
									->join(array("B" => "MMS_IssueTrans"), "A.IssueRegisterId = B.IssueRegisterId", array(), $select7::JOIN_INNER)									
								->where (array("B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<=' $Fdate' And A.OwnOrCSM=0")); 			
							$select7->combine($select6,'Union ALL');
							
							$select8 = $sql->select();
							$select8->from(array("A" => "MMS_IssueRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),
										'ItemId' => new Expression("B.ItemId"),
										'CostCentreId' => new Expression("A.CostCentreId"),
										'Qty' => new Expression("B.IssueQty"),						
									))
									->join(array("B" => "MMS_IssueTrans"), "A.IssueRegisterId = B.IssueRegisterId", array(), $select8::JOIN_INNER)									
								->where (array("B.IssueOrReturn='R' And B.IssueQty > 0  AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0")); 			
							$select8->combine($select7,'Union ALL');
							
							$select9 = $sql->select();
							$select9->from(array("A" => $select8))
									->columns(array(
										'ResourceId' => new Expression('A.ResourceId'),
										'ItemId' => new Expression("A.ItemId"),							
										'Qty' => new Expression("ISNULL(SUM(Qty),0)"),						
									))													
								->where (array("A.CostCentreId=$CostCentreId GROUP BY A.ResourceId, A.ItemId")); 	
							
							$select10 = $sql->select();
							$select10->from(array("A" => "MMS_PVTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("Case When B.ThruDC='Y' Then ISNULL(SUM(ActualQty),0) Else ISNULL(SUM(BillQty),0) End"),						
										'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then ISNULL(SUM(A.ActualQty*A.GrossRate),0) Else ISNULL(SUM(A.BillQty*A.GrossRate),0) End Else Case When B.ThruDC='Y' Then ISNULL(SUM(A.ActualQty*A.QRate),0) Else ISNULL(SUM(A.BillQty*A.QRate),0) End End"),						
									))
									->join(array("B" => "MMS_PVRegister"), "A.PVRegisterId=B.PVRegisterId", array(), $select10::JOIN_INNER)									
									->join(array("OC" => "WF_OperationalCostCentre"), "B.CostCentreId=OC.CostCentreId", array(), $select10::JOIN_INNER)									
									->join(array("VM" => "Vendor_Master"), "B.VendorId=VM.VendorId", array(), $select10::JOIN_INNER)									
									->join(array("CM" => "WF_CityMaster"), "VM.CityId=CM.CityId", array(), $select10::JOIN_LEFT)									
									->join(array("CC" => "WF_CostCentre"), "OC.FACostCentreId=CC.CostCentreId", array(), $select10::JOIN_INNER)									
									->join(array("SM" => "WF_StateMaster"), "CC.StateId=SM.StateId", array(), $select10::JOIN_INNER)									
								->where (array("A.BillQty>0 AND B.PVDate<=' $Fdate ' GROUP BY ResourceId,ItemId,B.PurchaseTypeId,B.ThruDC,OC.SEZProject,SM.StateId,CM.StateId")); 
						
							$select11 = $sql->select();
							$select11->from(array("A" => "MMS_DCTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(A.BalQty),0)"),						
										'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then ISNULL(SUM(A.BalQty*A.GrossRate),0) Else ISNULL(SUM(A.BalQty*A.QRate),0) End"),						
									))
									->join(array("B" => "MMS_DCRegister"), "A.DCRegisterId=B.DCRegisterId", array(), $select11::JOIN_INNER)									
									->join(array("OC" => "WF_OperationalCostCentre"), "B.CostCentreId=OC.CostCentreId", array(), $select11::JOIN_INNER)									
									->join(array("VM" => "Vendor_Master"), "B.VendorId=VM.VendorId", array(), $select11::JOIN_INNER)									
									->join(array("CM" => "WF_CityMaster"), "VM.CityId=CM.CityId", array(), $select11::JOIN_LEFT)									
									->join(array("CC" => "WF_CostCentre"), "OC.FACostCentreId=CC.CostCentreId", array(), $select11::JOIN_INNER)									
									->join(array("SM" => "WF_StateMaster"), "CC.StateId=SM.StateId", array(), $select11::JOIN_INNER)									
								->where (array("A.BalQty>0 AND B.DCDate<=' $Fdate ' And DcOrCSM=1  GROUP BY ResourceId,ItemId,B.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId")); 
							$select11->combine($select10,'Union ALL');
							
							$select12 = $sql->select();
							$select12->from(array("A" => "MMS_TransferTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(-A.TransferQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(-A.Amount),0)"),						
									))
									->join(array("B" => "MMS_TransferRegister"), "B.TVRegisterId = A.TransferRegisterId", array(), $select12::JOIN_INNER)																							
								->where (array("A.TransferQty>0 AND  B.TVDate <=' $Fdate ' GROUP BY ResourceId,ItemId")); 
							$select12->combine($select11,'Union ALL');
							
							$select13 = $sql->select();
							$select13->from(array("A" => "MMS_TransferTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(A.RecdQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(A.RecdQty*A.Rate),0)"),						
									))
									->join(array("B" => "MMS_TransferRegister"), "B.TVRegisterId = A.TransferRegisterId", array(), $select13::JOIN_INNER)																							
								->where (array("A.TransferQty>0 AND B.TVDate <=' $Fdate ' GROUP BY ResourceId,ItemId")); 
							$select13->combine($select12,'Union ALL');
							
							$select14 = $sql->select();
							$select14->from(array("A" => "MMS_PRTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(-A.ReturnQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(-Amount),0)"),						
									))
									->join(array("B" => "MMS_PRRegister"), "B.PRRegisterId = A.PRRegisterId", array(), $select14::JOIN_INNER)																							
								->where (array("A.ReturnQty>0 AND B.PRDate <=' $Fdate ' GROUP BY ResourceId,ItemId")); 
							$select14->combine($select13,'Union ALL');
							
							$select15 = $sql->select();
							$select15->from(array("A" => "MMS_IssueTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(-A.IssueQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(-Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End),0)"),						
									))
									->join(array("B" => "MMS_IssueRegister"), "B.IssueRegisterId = A.IssueRegisterId", array(), $select15::JOIN_INNER)																							
								->where (array("A.IssueQty>0 AND B.IssueDate <=' $Fdate ' And A.IssueOrReturn='I'   GROUP BY ResourceId,ItemId")); 
							$select15->combine($select14,'Union ALL');
							
							$select16 = $sql->select();
							$select16->from(array("A" => "MMS_IssueTrans"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(A.IssueQty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else IssueAmount End),0)"),						
									))
									->join(array("B" => "MMS_IssueRegister"), "B.IssueRegisterId = A.IssueRegisterId", array(), $select16::JOIN_INNER)																							
								->where (array("A.IssueQty>0 AND B.IssueDate <=' $Fdate ' And A.IssueOrReturn='R'   GROUP BY ResourceId,ItemId")); 
							$select16->combine($select15,'Union ALL');
							
							$select17 = $sql->select();
							$select17->from(array("A" => "MMS_Stock"))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("OpeningStock"),						
										'Amount' => new Expression("OpeningStock*ORate"),						
									))																									
								->where (array("OpeningStock>0")); 
							$select17->combine($select16,'Union ALL');
							
							$select18 = $sql->select();
							$select18->from(array("A" => $select17))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'Qty' => new Expression("ISNULL(SUM(Qty),0)"),						
										'Amount' => new Expression("ISNULL(SUM(Amount),0)"),						
									));																								
							$select18->group(array (new Expression("ResourceId,ItemId")));
							
							$select19 = $sql->select();
							$select19->from(array("A" => $select18))
									->columns(array(
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),								
										'AvgRate' => new Expression("(Case When Amount > 0 Then Amount Else null End/ Case When Qty > 0 Then Qty Else null End)"),												
									));	
							
							$select20 = $sql->select();
							$select20->from(array("A" => $select9))
									->columns(array(
										'ResourceGroupId' => new Expression('Distinct RG.ResourceGroupId'),
										'ResourceId' => new Expression(' A.ResourceId'),
										'ItemId' => new Expression("A.ItemId"),								
										'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),								
										'Resource' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End"),								
										'Unit' => new Expression("B.UnitId"),								
										'Op.Balance' => new Expression("SK.OpeningStock"),						
										'ORate' => new Expression("CAST(SK.ORate As Decimal(18,3))"),						
										'OValue' => new Expression("CAST(SK.OpeningStock*SK.ORate As Decimal(18,3))"),						
										'MOpeningBal' => new Expression("(Select (G1.OpeningStock+G1.DC+G1.Bill+G1.Issue+G1.IssueRet+G1.TransferOut+G1.TransferIn+G1.BillReturn)  MonthOpeningStock from 
																		(Select SUM(ISNULL(G.OpeningStock,0)) OpeningStock,SUM(ISNULL(G.DC,0)) DC,SUM(ISNULL(G.Bill,0)) Bill,SUM(ISNULL(G.Issue,0)) Issue,SUM(ISNULL(G.IssueRet,0)) IssueRet,SUM(ISNULL(G.TransferOut,0)) TransferOut,SUM(ISNULL(G.TransferIn,0)) TransferIn,SUM(ISNULL(G.BillReturn,0)) BillReturn From 
																		(Select ISNULL(OpeningStock,0) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn  From MMS_Stock  
																		WHere CostCentreId=$CostCentreId And ResourceId=C.ResourceId And ItemId=C.ItemId     
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,ISNULL(B.AcceptQty,0) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_DCRegister A  
																		Inner Join MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId And A.DCOrCSM=1 
																		WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.DCDate <= ' $Cdate ' 
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,ISNULL(B.BillQty,0) Bill,-CAST(0 As Decimal(18,3))  Issue, 
																		CAST(0 As Decimal(18,3))  IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_PVRegister A  
																		Inner Join MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId And A.ThruDC='N' 
																		WHere A.CostCentreId=$CostCentreId  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate <= ' $Cdate ' 
																		Union All 
																		Select CAST(0 As Decimal(18,3))  OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-ISNULL(B.IssueQty,0) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_IssueRegister A  
																		Inner Join MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And B.IssueOrReturn='I' And A.OWNOrCSM=0 
																		And A.IssueDate <= ' $Cdate '    
																		Union All 
																		Select CAST(0 As Decimal(18,3))  OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		ISNULL(B.IssueQty,0) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_IssueRegister A  
																		Inner Join MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId
																		WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And B.IssueOrReturn='R' And A.OWNOrCSM=0 And A.IssueDate <= '$Cdate'         
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-ISNULL(B.TransferQty,0) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_TransferRegister A  
																		Inner Join MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId WHere A.FromCostCentreId=$CostCentreId  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																		And A.TVDate <= ' $Cdate' 
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,ISNULL(B.RecdQty,0) TransferIn,-CAST(0 As Decimal(18,3)) BillReturn From MMS_TransferRegister A  
																		Inner Join MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId WHere A.ToCostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																		And A.TVDate <= ' $Cdate'         
																		Union All 
																		Select CAST(0 As Decimal(18,3)) OpeningStock,CAST(0 As Decimal(18,3)) DC,CAST(0 As Decimal(18,3)) Bill,-CAST(0 As Decimal(18,3)) Issue, 
																		CAST(0 As Decimal(18,3)) IssueRet,-CAST(0 As Decimal(18,3)) TransferOut,CAST(0 As Decimal(18,3)) TransferIn,-ISNULL(B.ReturnQty,0) BillReturn From MMS_PRRegister A  
																		Inner Join MMS_PRTrans B  On A.PRRegisterId=B.PRRegisterId WHere A.CostCentreId=$CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PRDate <= ' $Cdate ') G ) G1)"),	
										'MOpeningBalRate' => new Expression("(Select Case When G1.RecRate > 0 Then CAST(G1.RecRate As Decimal(18,3)) Else CAST(0 As Decimal(18,3)) End RecRate From 
																			(Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) RecRate,SUM(G.Qty) Qty From    
																			(Select B.BalQty Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then (isnull(B.BalQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BalQty,0)*isnull(B.QRate,0)) End Amount From MMS_DCRegister A     
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC   On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0  And DcOrCSM=1     
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.DCDate <= '$Cdate'           
																			Union All    
																			Select Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BillQty,0)*isnull(B.GrossRate,0)) End Else Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.QRate,0)) Else (isnull(B.BillQty,0)*isnull(B.QRate,0)) End End Amount From MMS_PVRegister A        
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate <= ' $Cdate '         
																			Union All  
																			Select B.RecdQty Qty, (isnull(B.RecdQty,0)*isnull(B.Rate,0)) Amount From MMS_TransferRegister A  
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId  
																			WHERE B.RecdQty > 0  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.TVDate <= '$Cdate'        
																			Union All  
																			Select B.IssueQty Qty,(isnull(B.IssueQty,0)*isnull(Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0)) Amount From MMS_IssueRegister A  
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId  
																			Where B.IssueOrReturn='R' And B.IssueQty>0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= '$Cdate'         
																			Union All 
																			SELECT SUM(-B.ReturnQty) Qty, SUM(-B.ReturnQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.ReturnQty),0) Amount FROM MMS_PRRegister A    
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId WHERE B.ReturnQty > 0  And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId And A.PRDate <= ' $Cdate '         
																			Union All  
																			SELECT SUM(-B.TransferQty) Qty,  SUM(-B.TransferQty)*isnull(SUM(B.GrossAmount),0)/nullif(SUM(B.TransferQty),0)  Amount FROM MMS_TransferRegister A      
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0  AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId And A.TVDate <= ' $Cdate'           
																			Union All  
																			SELECT SUM(-B.IssueQty) Qty,SUM(-B.IssueQty)*isnull(SUM( Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A     
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= ' $Cdate' ) G) G1 )"),	
										'MOpeningBalValue' => new Expression("(Select CAST(G1.Qty*Case When G1.RecRate>0 Then G1.RecRate Else 0 End As Decimal(18,3)) RecRate From
																			(Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) RecRate,SUM(G.Qty) Qty From    
																			(Select B.BalQty Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId) ) Then (isnull(B.BalQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BalQty,0)*isnull(B.QRate,0)) End  Amount From MMS_DCRegister A     
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0 And DcOrCSM=1     
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.DCDate <= ' $Cdate '           
																			Union All    
																			Select Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.GrossRate,0)) Else (isnull(B.BillQty,0)*isnull(B.GrossRate,0)) End Else Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(B.QRate,0)) Else (isnull(B.BillQty,0)*isnull(B.QRate,0)) End End Amount From MMS_PVRegister A        
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId     
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate <= ' $Cdate '             
																			Union All  
																			Select B.RecdQty Qty,  (isnull(B.RecdQty,0)*isnull(B.Rate,0))  Amount From MMS_TransferRegister A  
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId=B.TransferRegisterId  
																			WHERE B.RecdQty > 0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.TVDate <= ' $Cdate '                         
																			Union All  
																			Select B.IssueQty Qty,(isnull(B.IssueQty,0)*isnull(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0)) Amount From MMS_IssueRegister A   
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId=B.IssueRegisterId  
																			Where B.IssueOrReturn='R' And B.IssueQty>0  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= ' $Cdate '                           
																			Union All 
																			SELECT SUM(-B.ReturnQty) Qty, SUM(-B.ReturnQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.ReturnQty),0) Amount FROM MMS_PRRegister A    
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId WHERE B.ReturnQty > 0 And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId And A.PRDate <= ' $Cdate '              
																			Union All  
																			SELECT SUM(-B.TransferQty) Qty,  SUM(-B.TransferQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0)  Amount FROM MMS_TransferRegister A      
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0   AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId And A.TVDate <= ' $Cdate '                         
																			Union All  
																			SELECT SUM(-B.IssueQty) Qty,SUM(-B.IssueQty)*isnull(SUM(B.IssueAmount),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A     
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.IssueDate <= ' $Cdate ') G) G1)"),
										'MonthReceipt' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From 
																			(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																			(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC   On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0                           
																			AND A.DCDate BETWEEN ('$Ldate') And (' $Fdate') And DcOrCSM=1   
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                         
																			Union All 
																			SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																			Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0  
																			AND A.PVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),	
										'MonthReceiptRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																			(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																			(SELECT ISNULL(SUM(B.BalQty),0) Qty,Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A   
																			INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			Where B.BalQty > 0   
																			AND A.DCDate BETWEEN (' $Ldate ') And (' $Fdate ') And DcOrCSM=1   
																			And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId 
																			Union All 
																			SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																			Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																			INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																			INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																			INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																			LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																			INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																			INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																			WHERE  B.BillQty > 0  
																			AND A.PVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'MonthReceiptValue' => new Expression("(Select CAST(G1.Qty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																				(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																				(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																				INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																				INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																				INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																				LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																				INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																				INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																				Where B.BalQty > 0   
																				AND A.DCDate BETWEEN (' $Ldate ') And (' $Fdate ') And DcOrCSM=1   
																				And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                     
																				Union All 
																				SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																				Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																				INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																				INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																				INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																				LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																				INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																				INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																				WHERE  B.BillQty > 0  
																				AND A.PVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'Receipt' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From 
																	(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																	(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																	INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																	INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																	INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																	LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																	INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																	INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																	Where B.BalQty > 0   
																	AND A.DCDate<=' $Fdate ' And DcOrCSM=1   
																	And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,CM.StateId,SM.StateId                          
																	Union All 
																	SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																	Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																	INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																	INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																	INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																	LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																	INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																	INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																	WHERE  B.BillQty > 0  
																	AND A.PVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'ReceiptRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																		(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																		(SELECT ISNULL(SUM(B.BalQty),0) Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A  
																		INNER JOIN MMS_DCTrans B  On A.DCRegisterId=B.DCRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		Where B.BalQty > 0   
																		AND A.DCDate<=' $Fdate ' And DcOrCSM=1   
																		And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                           
																		Union All 
																		SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																		Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																		INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		WHERE  B.BillQty > 0  
																		AND A.PVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'ReceiptValue' => new Expression("(Select CAST(G1.Qty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																		(Select SUM(G.Qty) Qty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) Rate   From  
																		(SELECT ISNULL(SUM(B.BalQty),0) Qty,Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then SUM(B.BalQty*B.GrossRate) Else SUM(B.BalQty*B.QRate) End Amount   FROM MMS_DCRegister A   
																		INNER JOIN MMS_DCTrans B   On A.DCRegisterId=B.DCRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC  On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		Where B.BalQty > 0   
																		AND A.DCDate<=' $Fdate ' And DcOrCSM=1   
																		And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,OC.SEZProject,SM.StateId,CM.StateId                           
																		Union All 
																		SELECT Case When A.ThruDC='Y' Then ISNULL(SUM(B.ActualQty),0) Else ISNULL(SUM(B.BillQty),0) End Qty, 
																		Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.GrossRate) Else SUM(B.BillQty*B.GrossRate) End Else Case When A.ThruDC='Y' Then SUM(B.ActualQty*B.QRate) Else SUM(B.BillQty*B.QRate) End End Amount  FROM MMS_PVRegister A     
																		INNER JOIN MMS_PVTrans B  On A.PVRegisterId=B.PVRegisterId 
																		INNER JOIN WF_OperationalCostCentre OC   On OC.CostCentreId=A.CostCentreId 
																		INNER JOIN Vendor_Master VM  ON A.VendorId=VM.VendorId 
																		LEFT JOIN WF_CityMaster CM  ON VM.CityId=CM.CityId 
																		INNER JOIN WF_CostCentre CC  ON OC.FACostCentreId=CC.CostCentreId 
																		INNER JOIN WF_StateMaster SM  ON CC.StateId=SM.StateId 
																		WHERE  B.BillQty > 0  
																		AND A.PVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId Group By A.PurchaseTypeId,A.ThruDC,OC.SEZProject,SM.StateId,CM.StateId) G) G1)"),
										'MonthBillReturn' => new Expression("(Select CAST(G1.ReturnQty As Decimal(18,5)) ReturnQty From 
																			(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																			(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																			WHERE B.ReturnQty > 0 AND A.PRDate BETWEEN ('$Ldate') And ('$Fdate') And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'MonthBillReturnRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																				(Select -G.ReturnQty ReturnQty,G.Amount/G.ReturnQty Rate From 
																				(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																				INNER JOIN MMS_PRTrans B   ON A.PRRegisterId = B.PRRegisterId  
																				WHERE B.ReturnQty > 0 AND A.PRDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'MonthBillReturnValue' => new Expression("(Select CAST(G1.ReturnQty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																				(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																				(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																				INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																				WHERE B.ReturnQty > 0 AND A.PRDate BETWEEN (' $Ldate ') And (' $Fdate ')  And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'BillReturn' => new Expression("(Select CAST(G1.ReturnQty As Decimal(18,5)) ReturnQty From 
																		(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																		(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																		INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																		WHERE B.ReturnQty > 0 AND A.PRDate<=' $Fdate ' And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'BillReturnRate' => new Expression("(Select CAST(Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Rate From 
																			(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																			(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																			WHERE B.ReturnQty > 0 AND A.PRDate<=' $Fdate ' And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'BillReturnValue' => new Expression("(Select CAST(G1.ReturnQty*Case When G1.Rate>0 Then G1.Rate Else 0 End As Decimal(18,3)) Amount From 
																			(Select -G.ReturnQty ReturnQty,ISNULL(G.Amount,0)/NULLIF(G.ReturnQty,0) Rate From 
																			(SELECT ISNULL(SUM(B.ReturnQty),0) ReturnQty,SUM(Amount) Amount FROM MMS_PRRegister A     
																			INNER JOIN MMS_PRTrans B  ON A.PRRegisterId = B.PRRegisterId  
																			WHERE B.ReturnQty > 0 AND A.PRDate<=' $Fdate '  And B.ResourceId=C.ResourceId ANd B.ItemId=C.ItemId) G) G1)"),
										'MonthTransfer' => new Expression("(Select CAST(G1.TransferQty As Decimal(18, 5 )) TransferQty  From 
																			(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																			(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount) Amount  FROM MMS_TransferRegister A    
																			INNER JOIN MMS_TransferTrans B   On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ') AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                           
																			Union All SELECT  SUM(B.RecdQty) TransferQty, SUM(B.RecdQty*B.Rate)  Amount FROM MMS_TransferRegister A   
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ')  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'MonthTransferRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate  From 
																			(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																			(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount)  Amount  FROM MMS_TransferRegister A    
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ') AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                             
																			Union All SELECT  SUM(B.RecdQty) TransferQty,  SUM(B.RecdQty*B.Rate)  Amount FROM MMS_TransferRegister A  
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ')  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'MonthTransferValue' => new Expression("(Select CAST((G1.TransferQty*ABS(G1.Rate)) As Decimal(18,3)) Amount  From 
																			(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																			(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount) Amount  FROM MMS_TransferRegister A    
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ')  AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                            
																			Union All SELECT  SUM(B.RecdQty) TransferQty,SUM(B.RecdQty*B.Rate) Amount FROM MMS_TransferRegister A   
																			INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate BETWEEN (' $Ldate ') And (' $Fdate ') And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'Transfer' => new Expression("(Select CAST(G1.TransferQty As Decimal(18, 5 )) TransferQty  From 
																	(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																	(SELECT SUM(-B.TransferQty) TransferQty, SUM(B.Amount) Amount  FROM MMS_TransferRegister A    
																	INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate<=' $Fdate ' AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                             
																	Union All SELECT  SUM(B.RecdQty) TransferQty,SUM(B.RecdQty*B.Rate) Amount FROM MMS_TransferRegister A   
																	INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'TransferRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate  From 
																		(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																		(SELECT SUM(-B.TransferQty) TransferQty,  SUM(B.Amount)  Amount  FROM MMS_TransferRegister A    
																		INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate<=' $Fdate ' AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                           
																		Union All SELECT  SUM(B.RecdQty) TransferQty, SUM(B.RecdQty*B.Rate)  Amount FROM MMS_TransferRegister A   
																		INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate<=' $Fdate '  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'TransferValue' => new Expression("(Select CAST((G1.TransferQty*ABS(G1.Rate)) As Decimal(18,3)) Amount  From 
																		(Select SUM(G.TransferQty) TransferQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.TransferQty),0) Rate From 
																		(SELECT SUM(-B.TransferQty) TransferQty, SUM(B.Amount) Amount FROM MMS_TransferRegister A    
																		INNER JOIN MMS_TransferTrans B   On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0 AND A.TVDate<=' $Fdate '  AND B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                           
																		Union All SELECT  SUM(B.RecdQty) TransferQty,SUM(B.RecdQty*B.Rate) Amount FROM MMS_TransferRegister A   
																		INNER JOIN MMS_TransferTrans B  On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0 AND A.TVDate<=' $Fdate ' And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId ) G) G1)"),
										'Month-Issue/Return' => new Expression("(Select CAST(G1.IssueQty As Decimal(18, 5 )) IssueQty From 
																			(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																			(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate  
																			BETWEEN (' $Ldate ') And (' $Fdate ')  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                          
																			Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																			AND A.IssueDate BETWEEN (' $Ldate') And ('$Fdate') 
																			And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Month-Issue/ReturnRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate From 
																				(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																				(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.IssueQty*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate  
																				BETWEEN (' $Ldate ') And (' $Fdate ') And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                          
																				Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A     
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																				AND A.IssueDate BETWEEN (' $Ldate ') And (' $Fdate ')  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Month-Issue/ReturnValue' => new Expression("(Select CAST(G1.IssueQty*ABS(G1.Rate) As Decimal(18,3)) Amount From 
																				(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																				(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B   On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate  
																				BETWEEN (' $Ldate ') And (' $Fdate ') And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                          
																				Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																				AND A.IssueDate BETWEEN (' $Ldate ') And (' $Fdate ') And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Issue/Return' => new Expression("(Select CAST(G1.IssueQty As Decimal(18, 5 )) IssueQty From 
																		(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																		(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																		INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate <=' $Fdate '  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                         
																		Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																		INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																		AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Issue/ReturnRate' => new Expression("(Select CAST(ABS(G1.Rate) As Decimal(18,3)) Rate From 
																			(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																			(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                            
																			Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																			INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																			AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId) G) G1)"),
										'Issue/ReturnValue' => new Expression("(Select CAST(G1.IssueQty*ABS(G1.Rate) As Decimal(18,3)) Amount From 
																				(Select SUM(G.IssueQty) IssueQty,ISNULL(SUM(G.Amount),0)/NULLIF(SUM(ABS(G.IssueQty)),0) Rate From 
																				(SELECT ISNULL(SUM(-B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0 AND A.IssueDate<=' $Fdate '  And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId                
																				Union All SELECT ISNULL(SUM(B.IssueQty),0) IssueQty,SUM(Case When (B.TFactor>0 And B.FFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End) Amount FROM MMS_IssueRegister A    
																				INNER JOIN MMS_IssueTrans B  On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0   
																				AND A.IssueDate<=' $Fdate ' And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId)G)G1)"),
										'Net' => new Expression("CAST(Qty AS Decimal(18,3))"),
										'AvgRate' => new Expression("CAST(ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
										'AvgAmount' => new Expression("CAST(Qty*ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
										'LRate' => new Expression("SK.LRate"),
										'LAmount' => new Expression("CAST(Qty*ISNULL(SK.LRate,0) As Decimal(18,3))"),
										'Child' => new Expression("Case When A.ItemId <> 0 Then 0 Else 1 End"),
									))
									->join(array("B" => "Proj_Resource"), "A.ResourceId=B.ResourceId", array(), $select20::JOIN_INNER)																							
									->join(array("C" => $select19), "A.ResourceId=C.ResourceId And A.ItemId=C.ItemId", array(), $select20::JOIN_LEFT)																							
									->join(array("BR" =>"MMS_Brand"), "A.ItemId =BR.BrandId And A.ResourceId=BR.ResourceId", array(), $select20::JOIN_LEFT)																							
									->join(array("SK" =>"MMS_Stock"), "A.ItemId=SK.ItemId And A.ResourceId=SK.ResourceId", array(), $select20::JOIN_LEFT)																							
									->join(array("RV" =>"Proj_Resource"), "A.ResourceId=RV.ResourceId", array(), $select20::JOIN_INNER)																							
									->join(array("RG" =>"Proj_ResourceGroup"), "RV.ResourceGroupId=RG.ResourceGroupId", array(), $select20::JOIN_LEFT)																							
								->where (array("B.TypeId IN (2,3) ")); 
							
							$statement = $sql->getSqlStringForSqlObject($select20); 
							$monthlystockdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();	
							
							$this->_view->setTerminal(true);
							$response = $this->getResponse()->setContent(json_encode($monthlystockdetails));
							return $response;
							break;
						}
					case 'default':
					$response->setStatusCode('404');
					$response->setContent('Invalid request!');
					return $response;
					break;
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
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}


	public function stockdetailsBetweenDateAction(){
	
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
							$select1->from(array("A" => "MMS_PVTrans"))
									->columns(array(
										'CostCentreId' => new Expression("B.CostCentreId"),
										'ResourceId' => new Expression("A.ResourceId"),
										'ItemId' => new Expression("A.ItemId"),
										'Qty' => new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
										'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When B.ThruDC='Y' Then
																  SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
																  Else SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End)
																  End Else Case When B.ThruDC='Y' Then
																  SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) Else
																  SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) End End"),							
									))	
									->join(array("PG" => "MMS_PVGroupTrans"), " A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId", array(), $select1::JOIN_INNER)																							
									->join(array("B" => "MMS_PVRegister"), "A.PVRegisterId=B.PVRegisterId", array(), $select1::JOIN_INNER)																							
									->join(array("OC" => "WF_OperationalCostCentre"), "B.CostCentreId=OC.CostCentreId", array(), $select1::JOIN_INNER)																							
									->join(array("VM" => "Vendor_Master"), "B.VendorId=VM.VendorId", array(), $select1::JOIN_INNER)																							
									->join(array("CM" => "WF_CityMaster"), "VM.CityId=CM.CityId", array(), $select1::JOIN_LEFT)									
									->join(array("CC" => "WF_CostCentre"), "OC.FACostCentreId=CC.CostCentreId", array(), $select1::JOIN_INNER)									
									->join(array("SM" => "WF_StateMaster"), "CC.StateId=SM.StateId", array(), $select1::JOIN_INNER)									
								->where (array("A.BillQty>0 and PVDate BETWEEN ('$fromDate') And ('$toDate') GROUP BY B.CostCentreId,A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,OC.SEZProject,PG.TFactor,PG.TFactor,SM.StateId,CM.StateId")); 
								
							$select2 = $sql->select();
							$select2->from(array("A" => "MMS_DCTrans"))
									->columns(array(
										'CostCentreId' => new Expression("B.CostCentreId"),
										'ResourceId' => new Expression("A.ResourceId"),
										'ItemId' => new Expression("A.ItemId"),
										'Qty' => new Expression("SUM(A.BalQty)"),
										'Amount' => new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And OC.SEZProject=0 And (SM.StateId=CM.StateId) )
																  Then SUM(A.BalQty*Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((A.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.GrossRate End)
																  Else SUM(A.BalQty*Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.QRate End) End"),							
									))	
									->join(array("DG" => "MMS_DCGroupTrans"), "A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId", array(), $select2::JOIN_INNER)																							
									->join(array("B" => "MMS_DCRegister"), "A.DCRegisterId=B.DCRegisterId", array(), $select2::JOIN_INNER)																							
									->join(array("OC" => "WF_OperationalCostCentre"), "B.CostCentreId=OC.CostCentreId", array(), $select2::JOIN_INNER)																							
									->join(array("VM" => "Vendor_Master"), "B.VendorId=VM.VendorId", array(), $select2::JOIN_INNER)																							
									->join(array("CM" => "WF_CityMaster"), "VM.CityId=CM.CityId", array(), $select2::JOIN_LEFT)									
									->join(array("CC" => "WF_CostCentre"), "OC.FACostCentreId=CC.CostCentreId", array(), $select2::JOIN_INNER)									
									->join(array("SM" => "WF_StateMaster"), "CC.StateId=SM.StateId", array(), $select2::JOIN_INNER)									
								->where (array("A.BalQty>0   And DcOrCSM=1   And B.DCDate BETWEEN ('$fromDate') And ('$toDate')
												GROUP BY B.CostCentreId, A.ResourceId,A.ItemId,B.PurchaseTypeId,OC.SEZProject,DG.FFactor,DG.TFactor,SM.StateId,CM.StateId")); 			
							$select2->combine($select1,'Union ALL');
							
							$select3 = $sql->select();
							$select3->from(array("A" => "MMS_TransferTrans"))
									->columns(array(
										'CostCentreId' => new Expression('A.FCostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),	
										'Qty' => new Expression("SUM(-A.TransferQty)"),						
										'Amount' => new Expression("SUM(-A.Amount)"),						
									))
									->join(array("B" => "MMS_TransferRegister"), "B.TVRegisterId = A.TransferRegisterId", array(), $select3::JOIN_INNER)									
								->where (array("A.TransferQty>0 AND B.TVDate BETWEEN ('$fromDate') And ('$toDate')     
												GROUP BY FCostCentreId,ResourceId,ItemId")); 			
							$select3->combine($select2,'Union ALL');
							
							$select4 = $sql->select();
							$select4->from(array("A" => "MMS_TransferTrans"))
									->columns(array(
										'CostCentreId' => new Expression('A.TCostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),	
										'Qty' => new Expression("SUM(A.RecdQty)"),						
										'Amount' => new Expression("SUM(A.RecdQty*A.Rate)"),						
									))
									->join(array("B" => "MMS_TransferRegister"), "B.TVRegisterId = A.TransferRegisterId", array(), $select4::JOIN_INNER)									
								->where (array("A.RecdQty>0  AND B.TVDate BETWEEN ('$fromDate') And ('$toDate')
												GROUP BY TCostCentreId,ResourceId,ItemId")); 			
							$select4->combine($select3,'Union ALL');
							
							$select5 = $sql->select();
							$select5->from(array("A" => "MMS_PRTrans"))
									->columns(array(
										'CostCentreId' => new Expression('B.CostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),	
										'Qty' => new Expression("SUM(-A.ReturnQty)"),						
										'Amount' => new Expression("SUM(-Amount)"),						
									))
									->join(array("B" => "MMS_PRRegister"), "B.PRRegisterId = A.PRRegisterId", array(), $select5::JOIN_INNER)									
								->where (array("A.ReturnQty>0 and PRDate BETWEEN ('$fromDate') And ('$toDate')
												GROUP BY CostCentreId, ResourceId,ItemId")); 			
							$select5->combine($select4,'Union ALL');
							
							$select6 = $sql->select();
							$select6->from(array("A" => "MMS_IssueTrans"))
									->columns(array(
										'CostCentreId' => new Expression('B.CostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),	
										'Qty' => new Expression("SUM(-A.IssueQty)"),						
										'Amount' => new Expression("SUM(-Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else A.IssueAmount End)"),						
									))
									->join(array("B" => "MMS_IssueRegister"), "B.IssueRegisterId = A.IssueRegisterId", array(), $select6::JOIN_INNER)									
								->where (array("A.IssueQty>0 And A.IssueOrReturn='I' and IssueDate BETWEEN ('$fromDate') And ('$toDate') GROUP BY CostCentreId,ResourceId,ItemId")); 			
							$select6->combine($select5,'Union ALL');
							
							$select7 = $sql->select();
							$select7->from(array("A" => "MMS_IssueTrans"))
									->columns(array(
										'CostCentreId' => new Expression('CostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),	
										'Qty' => new Expression("SUM(A.IssueQty)"),						
										'Amount' => new Expression("SUM(Case When (A.FFactor>0 And A.TFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else A.IssueAmount End)"),						
									))
									->join(array("B" => "MMS_IssueRegister"), "B.IssueRegisterId = A.IssueRegisterId", array(), $select7::JOIN_INNER)									
								->where (array("A.IssueQty>0 And A.IssueOrReturn='R' and IssueDate BETWEEN ('$fromDate') And ('$toDate') 
												GROUP BY B.CostCentreId, ResourceId,ItemId")); 			
							$select7->combine($select6,'Union ALL');
							
							$select8 = $sql->select();
							$select8->from("MMS_Stock")
									->columns(array(
										'CostCentreId' => new Expression('CostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),	
										'Qty' => new Expression("OpeningStock"),						
										'Amount' => new Expression("OpeningStock*ORate"),						
									))								
								->where (array("OpeningStock>0 and Date BETWEEN ('$fromDate') And ('$toDate')")); 			
							$select8->combine($select7,'Union ALL');
							
							$select9 = $sql->select();
							$select9->from(array("A" => $select8))
									->columns(array(
										'CostCentreId' => new Expression('CostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),	
										'Qty' => new Expression("SUM(Qty)"),						
										'Amount' => new Expression("SUM(Amount)"),						
									));														
							$select9->group(array (new Expression("CostCentreId, ResourceId,ItemId")));
							
							$select10 = $sql->select();
							$select10->from(array("A" => $select9))
									->columns(array(
										'CostCentreId' => new Expression('CostCentreId'),
										'ResourceId' => new Expression('ResourceId'),
										'ItemId' => new Expression("ItemId"),					
										'AvgRate' => new Expression("(Case When Amount > 0 Then Amount Else null End/ Case When Qty > 0 Then Qty Else null End)"),					
									));

							$select11 = $sql->select();
							$select11->from(array("S" => "MMS_Stock"))
									->columns(array(
										'ResourceId' => new Expression('S.ResourceId'),									
										'ItemId' => new Expression("S.ItemId"),	
										'CostCentreId' => new Expression("S.CostCentreId"),	
										'Qty' => new Expression("S.OpeningStock"),									
									))							
								->where (array("S.OpeningStock > 0")); 
							
							$select12 = $sql->select();
							$select12->from(array("A" => "MMS_PVRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),									
										'ItemId' => new Expression("B.ItemId"),	
										'CostCentreId' => new Expression("A.CostCentreId"),	
										'Qty' => new Expression("Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End"),									
									))	
									->join(array("B" => "MMS_PVTrans"), "A.PVRegisterId=B.PVRegisterId", array(), $select12::JOIN_INNER)									
								->where (array("B.BillQty > 0  AND PVDate BETWEEN ('$fromDate') And ('$toDate')")); 			
							$select12->combine($select11,'Union ALL');
							
							$select13 = $sql->select();
							$select13->from(array("A" => "MMS_DCRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),									
										'ItemId' => new Expression("B.ItemId"),	
										'CostCentreId' => new Expression("A.CostCentreId"),	
										'Qty' => new Expression("B.BalQty"),									
									))	
									->join(array("B" => "MMS_DCTrans"), "A.DCRegisterId=B.DCRegisterId", array(), $select13::JOIN_INNER)									
								->where (array("B.BalQty > 0  And DcOrCSM=1 AND DCDate BETWEEN ('$fromDate') And ('$toDate')")); 			
							$select13->combine($select12,'Union ALL');
							
							$select14 = $sql->select();
							$select14->from(array("A" => "MMS_TransferRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),									
										'ItemId' => new Expression("B.ItemId"),	
										'CostCentreId' => new Expression("A.FromCostCentreId"),	
										'Qty' => new Expression("-B.TransferQty"),									
									))	
									->join(array("B" => "MMS_TransferTrans"), "A.TVRegisterId = B.TransferRegisterId", array(), $select14::JOIN_INNER)									
								->where (array("B.TransferQty > 0  AND TVDate BETWEEN ('$fromDate') And ('$toDate')")); 			
							$select14->combine($select13,'Union ALL');
							
							$select15 = $sql->select();
							$select15->from(array("A" => "MMS_TransferRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),									
										'ItemId' => new Expression("B.ItemId"),	
										'CostCentreId' => new Expression("A.ToCostCentreId"),	
										'Qty' => new Expression("B.RecdQty"),									
									))	
									->join(array("B" => "MMS_TransferTrans"), "A.TVRegisterId = B.TransferRegisterId", array(), $select15::JOIN_INNER)									
								->where (array("B.RecdQty > 0 AND TVDate BETWEEN ('$fromDate') And ('$toDate')")); 			
							$select15->combine($select14,'Union ALL');
							
							$select16 = $sql->select();
							$select16->from(array("A" => "MMS_PRRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),									
										'ItemId' => new Expression("B.ItemId"),	
										'CostCentreId' => new Expression("A.CostCentreId"),	
										'Qty' => new Expression("-B.ReturnQty"),									
									))	
									->join(array("B" => "MMS_PRTrans"), "A.PRRegisterId = B.PRRegisterId", array(), $select16::JOIN_INNER)									
								->where (array("B.ReturnQty > 0  AND PRDate BETWEEN ('$fromDate') And ('$toDate')")); 			
							$select16->combine($select15,'Union ALL');
								
								
							$select17 = $sql->select();
							$select17->from(array("A" => "MMS_IssueRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),									
										'ItemId' => new Expression("B.ItemId"),	
										'CostCentreId' => new Expression("A.CostCentreId"),	
										'Qty' => new Expression("-B.IssueQty"),									
									))	
									->join(array("B" => "MMS_IssueTrans"), "A.IssueRegisterId = B.IssueRegisterId", array(), $select17::JOIN_INNER)									
								->where (array("B.IssueOrReturn='I' And B.IssueQty > 0 And A.OwnOrCSM=0 AND IssueDate BETWEEN ('$fromDate') And ('$toDate')")); 			
							$select17->combine($select16,'Union ALL');
							
							$select18 = $sql->select();
							$select18->from(array("A" => "MMS_IssueRegister"))
									->columns(array(
										'ResourceId' => new Expression('B.ResourceId'),									
										'ItemId' => new Expression("B.ItemId"),	
										'CostCentreId' => new Expression("A.CostCentreId"),	
										'Qty' => new Expression("B.IssueQty"),									
									))	
									->join(array("B" => "MMS_IssueTrans"), "A.IssueRegisterId = B.IssueRegisterId", array(), $select18::JOIN_INNER)									
								->where (array("B.IssueOrReturn='R' And B.IssueQty > 0 And A.OwnOrCSM=0 AND IssueDate BETWEEN('$fromDate') And ('$toDate')")); 			
							$select18->combine($select17,'Union ALL');
							
							$select19 = $sql->select();
							$select19->from(array("A" => $select18))
									->columns(array(
										'ResourceId' => new Expression('A.ResourceId'),									
										'ItemId' => new Expression("A.ItemId"),	
										'CostCentreId' => new Expression("A.CostCentreId"),	
										'Qty' => new Expression("SUM(Qty)"),									
									));								
							$select19->group(array (new Expression("A.ResourceId,A.CostCentreId,A.ItemId")));						
							
							$select20 = $sql->select();
							$select20->from(array("A" => $select19))
									->columns(array(
										'ResourceId' => new Expression('A.ResourceId'),
										'ItemId' => new Expression("A.ItemId"),
										'ResourceGroup' => new Expression("RG.ResourceGroupName"),
										'CostCentreName' => new Expression("OC.CostCentreName"),
										'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),
										'Resource' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End"),
										'Unit' => new Expression("B.UnitId"),
										'Op.Balance' => new Expression("SK.OpeningStock"),
										'ORate' => new Expression("SK.ORate"),
										'OValue' => new Expression("(SK.OpeningStock*SK.ORate)"),
										'TotalPurchase' => new Expression("(Select CAST(SUM(G.Qty) As Decimal(18,5)) Receipt From
																		   (SELECT SUM(B.BalQty) Qty  FROM MMS_DCRegister A  INNER JOIN MMs_DCTrans B On A.DCRegisterId=B.DCRegisterId Where B.BalQty > 0 
																		   AND A.CostCentreId=OC.CostCentreId And DcOrCSM=1 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId  
																		   And A.DCDate BETWEEN ('$fromDate') And ('$toDate')
																		   Union All 
																		   SELECT Case When A.ThruDC='Y' Then SUM(B.ActualQty) Else SUM(B.BillQty) End Qty  FROM MMS_PVRegister A     
																		   INNER JOIN MMS_PVTrans B On A.PVRegisterId=B.PVRegisterId WHERE  B.BillQty > 0 AND A.CostCentreId=OC.CostCentreId   
																		   And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate BETWEEN ('$fromDate') And ('$toDate') Group By A.ThruDC 
																		   Union All
																		   SELECT SUM(-B.ReturnQty) Qty From MMS_PRRegister A
																		   INNER JOIN MMS_PRTrans B On A.PRRegisterId=B.PRRegisterId 
																		   WHERE B.ReturnQty>0 And A.CostCentreId=OC.CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																		   AND A.PRDate BETWEEN ('$fromDate') And ('$toDate')
																		   ) G)"),
										'TotalPurchaseRate' => new Expression("(Select CAST(G1.RecRate As Decimal(18,3)) RecRate From 
																			   (Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) RecRate,SUM(G.Qty) Qty From  
																			   (Select B.BalQty Qty, Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId) ) Then
																			   (isnull(B.BalQty,0)*isnull(Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((B.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.GrossRate End,0)) Else 
																			   (isnull(B.BalQty,0)*isnull(Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((B.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.QRate End,0)) End Amount From MMS_DCRegister A    
																			   INNER JOIN MMS_DCTrans B On A.DCRegisterId=B.DCRegisterId   
																			   INNER JOIN MMS_DCGroupTrans DG On B.DCGroupId=DG.DCGroupId And B.DCRegisterId=DG.DCRegisterId
																			   INNER JOIN Vendor_Master VM On A.VendorId=VM.VendorId
																			   LEFT JOIN WF_CityMaster CM On VM.CityId=CM.CityId
																			   Where B.BalQty > 0 AND A.CostCentreId=OC.CostCentreId And DcOrCSM=1  And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																			   And A.DCDate BETWEEN ('$fromDate') And ('$toDate')
																				Union All   
																				Select Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End Qty,Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId) ) 
																				Then Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.GrossRate End,0))
																				Else (isnull(B.BillQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.GrossRate End,0)) End Else 
																				Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.QRate End,0))
																				Else (isnull(B.BillQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.QRate End,0)) End End Amount From MMS_PVRegister A      
																				INNER JOIN MMS_PVTrans B On A.PVRegisterId=B.PVRegisterId   
																				INNER JOIN MMS_PVGroupTrans PG On B.PVGroupId=PG.PVGroupId And B.PVRegisterId=PG.PVRegisterId
																				INNER JOIN Vendor_Master VM On A.VendorId=VM.VendorId
																				LEFT JOIN WF_CityMaster CM On VM.CityId=CM.CityId
																				WHERE  B.BillQty > 0 AND A.CostCentreId=OC.CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																				And A.PVDate BETWEEN ('$fromDate') And ('$toDate') 
																				 Union All 
																				 Select B.IssueQty Qty,(isnull(B.IssueQty,0)*isnull(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0)) Amount 
																				 From MMS_IssueRegister A INNER JOIN MMS_IssueTrans B On A.IssueRegisterId=B.IssueRegisterId Where B.IssueOrReturn='R' 
																				 And B.IssueQty>0 And A.CostCentreId=OC.CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																				 And A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																				 Union All
																				 Select -B.ReturnQty Qty,-(isnull(B.ReturnQty,0)*isnull(B.Rate,0)) Amount  From MMS_PRRegister A
																				 INNER JOIN MMS_PRTrans B On A.PRRegisterId=B.PRRegisterId  Where B.ReturnQty>0 And A.CostCentreId=OC.CostCentreId And B.ResourceId=C.ResourceId And 
																				 B.ItemId=C.ItemId And A.PRDate BETWEEN ('$fromDate') And ('$toDate')
																				 ) G) G1)"),
										'TotalPurchaseAmount' => new Expression("(Select CAST(G1.Qty*G1.Rate As Decimal(18,3)) Rate From 
																				 (Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,SUM(G.Qty) Qty From   
																				 (Select B.BalQty Qty,Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId) ) Then
																				 (isnull(B.BalQty,0)*isnull(Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((B.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.GrossRate End,0)) 
																				 Else (isnull(B.BalQty,0)*isnull(Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((B.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else B.QRate End,0)) End Amount From MMS_DCRegister A   
																				 INNER JOIN MMS_DCTrans B On A.DCRegisterId=B.DCRegisterId  
																				 INNER JOIN MMS_DCGroupTrans DG On B.DCGroupId=DG.DCGroupId And B.DCRegisterId=DG.DCRegisterId
																				 INNER JOIN Vendor_Master VM On A.VendorId=VM.VendorId
																				 LEFT JOIN WF_CityMaster CM On VM.CityId=CM.CityId
																				 Where B.BalQty > 0 AND A.CostCentreId=OC.CostCentreId And DcOrCSM=1 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId  
																				 And A.DCDate BETWEEN ('$fromDate') And ('$toDate')
																				 Union All   
																				 Select Case When A.ThruDC='Y' Then B.ActualQty Else B.BillQty End Qty,Case When ((A.PurchaseTypeId=0 Or A.PurchaseTypeId=5) And OC.SEZProject=0 And (CM.StateId=SM.StateId) ) 
																				 Then Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.GrossRate End,0))
																				 Else (isnull(B.BillQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.GrossRate End,0)) End 
																				 Else Case When A.ThruDC='Y' Then (isnull(B.ActualQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.QRate End,0))
																				 Else (isnull(B.BillQty,0)*isnull(Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((B.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else B.QRate End,0)) End End Amount From MMS_PVRegister A
																				 INNER JOIN MMS_PVTrans B On A.PVRegisterId=B.PVRegisterId
																				 INNER JOIN MMS_PVGroupTrans PG On B.PVGroupId=PG.PVGroupId And B.PVRegisterId=PG.PVRegisterId
																				 INNER JOIN Vendor_Master VM On A.VendorId=VM.VendorId
																				 LEFT JOIN WF_CityMaster CM On VM.CityId=CM.CityId
																				 WHERE  B.BillQty > 0 AND A.CostCentreId=OC.CostCentreId 
																				 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId And A.PVDate BETWEEN ('$fromDate') And ('$toDate')
																				 Union All 
																				 Select B.IssueQty Qty,(isnull(B.IssueQty,0)*isnull(Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0)) Amount From MMS_IssueRegister A 
																				 INNER JOIN MMS_IssueTrans B On A.IssueRegisterId=B.IssueRegisterId Where B.IssueOrReturn='R' And B.IssueQty>0 And 
																				 A.CostCentreId=OC.CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId 
																				 And A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																				 Union All
																				 Select -B.ReturnQty Qty,-(isnull(B.ReturnQty,0)*isnull(B.Rate,0)) Amount From MMS_PRRegister A
																				 INNER JOIN MMS_PRTrans B On A.PRRegisterId=B.PRRegisterId Where B.ReturnQty>0 And
																				 A.CostCentreId=OC.CostCentreId And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId
																				 And A.PRDate BETWEEN ('$fromDate') And ('$toDate')
																				 ) G) G1)"),
										'IssueQty' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																	 SUM(G.Qty) Qty From (SELECT SUM(B.IssueQty) Qty,SUM(B.IssueQty)*isnull(SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A    
																	 INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0    
																	 AND A.CostCentreId=OC.CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId
																	 And A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																	 ) G) G1)"),
										'IssueRate' => new Expression("(Select CAST(G1.Rate As Decimal(18,5)) Rate From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																	 SUM(G.Qty) Qty From (SELECT SUM(B.IssueQty) Qty,SUM(B.IssueQty)*isnull(SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A    
																	 INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0  
																	 AND A.CostCentreId=OC.CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId
																	 And A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																	 ) G) G1)"),
										'IssueAmount' => new Expression("(Select CAST(G1.Qty*G1.Rate As Decimal(18,5)) Rate From( 
																		 Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,SUM(G.Qty) Qty From (
																		 SELECT SUM(B.IssueQty) Qty,SUM(B.IssueQty)*isnull(SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A    
																		 INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='I' And B.IssueQty > 0   
																		 AND A.CostCentreId=OC.CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId
																		 And A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																		 ) G) G1)"),
										'IssueRetQty' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																		 SUM(G.Qty) Qty From (SELECT SUM(B.IssueQty) Qty,SUM(B.IssueQty)*isnull(SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A    
																		 INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0    
																		 AND A.CostCentreId=OC.CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId
																		 AND A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																		 ) G) G1)"),
										'IssueRetRate' => new Expression("(Select CAST(G1.Rate As Decimal(18,5)) Rate From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																		 SUM(G.Qty) Qty From (SELECT SUM(B.IssueQty) Qty,SUM(B.IssueQty)*isnull(SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A    
																		 INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0  
																		 AND A.CostCentreId=OC.CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId
																		 And A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																		 ) G) G1)"),
										'IssueRetAmount' => new Expression("(Select CAST(G1.Qty*G1.Rate As Decimal(18,5)) Rate From( 
																			 Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,SUM(G.Qty) Qty From (
																			  SELECT SUM(B.IssueQty) Qty,SUM(B.IssueQty)*isnull(SUM(Case When (B.FFactor>0 And B.TFactor>0) Then (B.IssueQty*isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.IssueAmount End),0)/nullif(SUM(B.IssueQty),0) Amount FROM MMS_IssueRegister A    
																			 INNER JOIN MMS_IssueTrans B On A.IssueRegisterId = B.IssueRegisterId WHERE  B.IssueOrReturn='R' And B.IssueQty > 0    
																			 AND A.CostCentreId=OC.CostCentreId And A.OwnOrCSM=0 And B.ResourceId=C.ResourceId And B.ItemId=C.ItemId
																			 And A.IssueDate BETWEEN ('$fromDate') And ('$toDate')
																			 ) G) G1)"),
										'TransferInQty' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																		 SUM(G.Qty) Qty From (
																		 SELECT SUM(B.RecdQty) Qty,SUM(B.RecdQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0) Amount FROM MMS_TransferRegister A     
																		 INNER JOIN MMS_TransferTrans B On A.TVRegisterId = B.TransferRegisterId 
																		 WHERE B.RecdQty > 0   AND A.ToCostCentreId=OC.CostCentreId AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId 
																		 And A.TVDate BETWEEN ('$fromDate') And ('$toDate')
																		 ) G) G1)"),
										'TransferInRate' => new Expression("(Select CAST(G1.Rate As Decimal(18,5)) Rate From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																			 SUM(G.Qty) Qty From (
																			 SELECT SUM(B.RecdQty) Qty,SUM(B.RecdQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0) Amount FROM MMS_TransferRegister A     
																			 INNER JOIN MMS_TransferTrans B On A.TVRegisterId = B.TransferRegisterId WHERE B.RecdQty > 0  AND A.ToCostCentreId=OC.CostCentreId 
																			 AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId And A.TVDate BETWEEN ('$fromDate') And ('$toDate')
																			 ) G) G1)"),
										'TransferInAmount' => new Expression("(Select CAST(G1.Qty*G1.Rate As Decimal(18,5)) Rate From( 
																			 Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,SUM(G.Qty) Qty From (
																			 SELECT SUM(B.RecdQty) Qty,SUM(B.RecdQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0) Amount FROM MMS_TransferRegister A     
																			 INNER JOIN MMS_TransferTrans B On A.TVRegisterId = B.TransferRegisterId 
																			 WHERE B.RecdQty > 0   AND A.ToCostCentreId=OC.CostCentreId AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId 
																			 And A.TVDate BETWEEN ('$fromDate') And ('$toDate')
																			 ) G) G1)"),
										'TransferOutQty' => new Expression("(Select CAST(G1.Qty As Decimal(18,5)) Qty From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																			 SUM(G.Qty) Qty From (
																			 SELECT SUM(B.TransferQty) Qty,SUM(B.TransferQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0) Amount FROM MMS_TransferRegister A     
																			 INNER JOIN MMS_TransferTrans B On A.TVRegisterId = B.TransferRegisterId 
																			 WHERE B.TransferQty > 0   AND A.FromCostCentreId=OC.CostCentreId AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId 
																			 And A.TVDate BETWEEN ('$fromDate') And ('$toDate')
																			 ) G) G1)"),
										'TransferOutRate' => new Expression("(Select CAST(G1.Rate As Decimal(18,5)) Rate From( Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,
																			 SUM(G.Qty) Qty From (
																			 SELECT SUM(B.TransferQty) Qty,SUM(B.TransferQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0) Amount FROM MMS_TransferRegister A     
																			 INNER JOIN MMS_TransferTrans B On A.TVRegisterId = B.TransferRegisterId WHERE B.TransferQty > 0  AND A.FromCostCentreId=OC.CostCentreId 
																			 AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId And A.TVDate BETWEEN ('$fromDate') And ('$toDate')
																			 ) G) G1)"),
										'TransferOutAmount' => new Expression("(Select CAST(G1.Qty*G1.Rate As Decimal(18,5)) Rate From( 
																				 Select CAST(isnull(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) As Decimal(18,3)) Rate,SUM(G.Qty) Qty From (
																				 SELECT SUM(B.TransferQty) Qty,SUM(B.TransferQty)*isnull(SUM(B.Amount),0)/nullif(SUM(B.TransferQty),0) Amount FROM MMS_TransferRegister A     
																				 INNER JOIN MMS_TransferTrans B On A.TVRegisterId = B.TransferRegisterId 
																				 WHERE B.TransferQty > 0   AND A.FromCostCentreId=OC.CostCentreId AND B.ResourceId=C.ResourceId And  B.ItemId=C.ItemId 
																				 And A.TVDate BETWEEN ('$fromDate') And ('$toDate')
																				 ) G) G1)"),
										'ClosingStockQty' => new Expression("CAST(Qty AS Decimal(18,3))"),
										'ClosingStockRate' => new Expression("CAST (ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
										'ClosingStockValue' => new Expression("CAST(Qty*ISNULL(C.AvgRate,0) AS Decimal(18,3))"),
										'LRate' => new Expression("SK.LRate"),
										'LAmount' => new Expression("CAST(Qty*ISNULL(SK.LRate,0) As Decimal(18,3))"),
										'CostCentreId' => new Expression("A.CostCentreId"),
									))					
									->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select20::JOIN_INNER)
									->join(array('C' => $select10), 'A.ResourceId=C.ResourceId And A.ItemId=C.ItemId  And  A.CostCentreId=C.CostCentreId', array(), $select20::JOIN_INNER)
									->join(array('BR' => 'MMS_Brand'), 'A.ItemId =BR.BrandId And A.ResourceId=BR.ResourceId', array(), $select20::JOIN_LEFT)
									->join(array('SK' => 'MMS_Stock'), 'A.ItemId=SK.ItemId And A.ResourceId=SK.ResourceId And A.CostCentreId=SK.CostCentreId', array(), $select20::JOIN_LEFT)
									->join(array('RV' => 'Proj_Resource'), 'A.ResourceId=RV.ResourceId', array(), $select20::JOIN_INNER)
									->join(array('OC' => 'WF_OperationalCostCentre'), 'A.CostCentreId=OC.CostCentreId', array(), $select20::JOIN_INNER)
									->join(array('CC' => 'WF_CostCentre'), 'OC.FACostCentreId=CC.CostCentreId', array(), $select20::JOIN_INNER)
									->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select20::JOIN_LEFT)
									->join(array('RG' => 'Proj_ResourceGroup'), 'RV.ResourceGroupId=RG.ResourceGroupId', array(), $select20::JOIN_LEFT)
								->where (array("B.TypeId IN (2,3) And A.CostCentreId In ($CostCentreId)"));	
							
						$statement = $sql->getSqlStringForSqlObject($select20); 
						$stockdetailsdate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($stockdetailsdate));
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

	public function materialwiseWarehouseAction(){
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
			
			$select1 = $sql->select();
			$select1->from(array("A" => "MMS_Stock"))
					->columns(array(
						'Id' => new Expression('C.Id'),									
						'ResourceId' => new Expression('A.ResourceId'),									
						'ItemId' => new Expression("A.ItemId"),	
						'Code' => new Expression("Case When A.ItemId>0 Then C.ItemCode Else C.Code End"),	
						'Resource' => new Expression("Case When A.ItemId>0 Then C.ItemName Else C.ResourceName End"),									
						'ClosingStock' => new Expression("SUM(B.ClosingStock)"),									
					))	
					->join(array("B" => "MMS_StockTrans"), "A.StockId=B.StockId", array(), $select1::JOIN_INNER)									
					->join(array("C" => "MMS_ResourceView"), "A.ResourceId=C.ResourceId And A.ItemId=C.ItemId", array(), $select1::JOIN_INNER)									
					->join(array("E" => "MMS_WareHouseDetails"), "E.TransId=B.WareHouseId", array(), $select1::JOIN_INNER)																	
					->join(array("F" => "MMS_WareHouse"), "F.WareHouseId=E.WareHouseId", array(), $select1::JOIN_INNER)									
					->join(array("G" => "MMS_CCWareHouse"), "A.CostCentreId=G.CostCentreId And F.WareHouseId=G.WareHouseId", array(), $select1::JOIN_INNER);										
			$select1->group(array (new Expression("A.ResourceId,A.ItemId,C.ItemCode,C.Code,C.ItemName,C.ResourceName,C.Id Having SUM(B.ClosingStock)>0")));
			$statement = $sql->getSqlStringForSqlObject($select1); 
			$materialdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$this->_view->materialdetails = $materialdetails; 
			
			$select2 = $sql->select();
			$select2->from(array("A" => "MMS_Stock"))
					->columns(array(
						'Id' => new Expression('C.Id'),									
						'ResourceId' => new Expression('A.ResourceId'),									
						'ItemId' => new Expression("A.ItemId"),	
						'WareHouseId' => new Expression("F.WareHouseId"),	
						'WareHouse' => new Expression("F.WareHouseName"),									
						'ClosingStock' => new Expression("SUM(B.ClosingStock)"),									
					))	
					->join(array("B" => "MMS_StockTrans"), "A.StockId=B.StockId ", array(), $select2::JOIN_INNER)									
					->join(array("C" => "MMS_ResourceView"), "A.ResourceId=C.ResourceId And A.ItemId=C.ItemId", array(), $select2::JOIN_INNER)																
					->join(array("E" => "MMS_WareHouseDetails"), "E.TransId=B.WareHouseId", array(), $select2::JOIN_INNER)									
					->join(array("F" => "MMS_WareHouse"), "F.WareHouseId=E.WareHouseId", array(), $select2::JOIN_INNER)									
					->join(array("G" => "MMS_CCWareHouse"), "A.CostCentreId=G.CostCentreId And F.WareHouseId=G.WareHouseId", array(), $select2::JOIN_INNER);										
			$select2->group(array (new Expression("A.ResourceId,A.ItemId,F.WareHouseId,F.WareHouseName,C.Id Having SUM(B.ClosingStock)>0")));
			$statement = $sql->getSqlStringForSqlObject($select2);
			$materialdetails2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
			$this->_view->materialdetails2 = $materialdetails2; 
			
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function wbsAnalysisReportAction(){
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
				
				switch($Type) {
					case 'cost':
					
					$select1 = $sql->select();
					$select1->from(array('A' => 'VM_RequestAnalTrans'))
						->columns(array(
							'ResourceId'=> new Expression('A.ResourceId'),
							'AnalysisId'=> new Expression('A.AnalysisId'),
							'RequestQty'=> new Expression('A.ReqQty'),
							'BalReqQty'=> new Expression('A.BalQty'),
							'POQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalPOQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'MinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalMinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BillQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'TransferQty'=>new Expression("CAST(0 As Decimal(18,6))")))
							
								->join(array('B' => 'VM_RequestTrans'), 'A.ReqTransId=B.RequestTransId And A.ResourceId=B.ResourceId', array(), $select1::JOIN_INNER)
								->join(array('C' => 'VM_RequestRegister'), 'B.RequestId=C.RequestId', array(), $select1::JOIN_INNER)
						->where(array("C.CostCentreId=$CostCentreId"));
						
					$select2 = $sql->select();
					$select2->from(array('A' => 'MMS_POAnalTrans'))
						->columns(array(
							'ResourceId'=> new Expression('A.ResourceId'),
							'AnalysisId'=> new Expression('A.AnalysisId'),
							'RequestQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'BalReqQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'POQty'=>new Expression('(A.POQty-A.CancelQty)'),						
							'BalPOQty'=>new Expression('A.BalQty'),						
							'MinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalMinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BillQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'TransferQty'=>new Expression("CAST(0 As Decimal(18,6))"),
							))
							
								->join(array('B' => 'MMS_POProjTrans'), new Expression('A.POProjTransId=B.POProjTransId And A.ResourceId=B.ResourceId And B.LivePO=1'), array(), $select2::JOIN_INNER)
								->join(array('C' => 'MMS_POTrans'), new Expression('B.POTransId=C.PoTransId And B.ResourceId=C.ResourceId And C.LivePO=1'), array(), $select2::JOIN_INNER)
								->join(array('D' => 'MMS_PORegister'),new Expression('C.PORegisterId=D.PORegisterId And D.LivePO=1'),array(),$select2::JOIN_INNER)				
						->where(array("D.CostCentreId=$CostCentreId"));
					$select2->combine($select1,'Union ALL');
					
					$select3 = $sql->select();
					$select3->from(array('A' => 'MMS_DCAnalTrans'))
						->columns(array(
							'ResourceId'=> new Expression('A.ResourceId'),
							'AnalysisId'=> new Expression('A.AnalysisId'),
							'RequestQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'BalReqQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'POQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalPOQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'MinQty'=>new Expression('A.AcceptQty'),						
							'BalMinQty'=>new Expression('A.BalQty'),						
							'BillQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'TransferQty'=>new Expression("CAST(0 As Decimal(18,6))"),
							))
							
								->join(array('B' => 'MMS_DCTrans'), 'A.DCTransId=B.DCTransId And A.ResourceId=B.ResourceId', array(), $select3::JOIN_INNER)
								->join(array('C' => 'MMS_DCRegister'), 'B.DCRegisterId=C.DCRegisterId', array(), $select3::JOIN_INNER)							
						->where(array("C.CostCentreId=$CostCentreId"));
					$select3->combine($select2,'Union ALL');
					
					
					$select4 = $sql->select();
					$select4->from(array('A' => 'MMS_PVAnalTrans'))
						->columns(array(
							'ResourceId'=> new Expression('A.ResourceId'),
							'AnalysisId'=> new Expression('A.AnalysisId'),
							'RequestQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'BalReqQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'POQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalPOQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'MinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalMinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BillQty'=>new Expression('A.BillQty'),						
							'TransferQty'=>new Expression("CAST(0 As Decimal(18,6))"),
							))
							
								->join(array('B' => 'MMS_PVTrans'), 'A.PVTransId=B.PVTransId And A.ResourceId=B.ResourceId', array(), $select4::JOIN_INNER)
								->join(array('C' => 'MMS_PVRegister'), new Expression("B.PVRegisterId=C.PVRegisterId And C.ThruPO='Y'"), array(), $select4::JOIN_INNER)							
						->where(array("C.CostCentreId=$CostCentreId"));
					$select4->combine($select3,'Union ALL');
					
					$select5 = $sql->select();
					$select5->from(array('A' => 'MMS_TransferAnalTrans'))
						->columns(array(
							'ResourceId'=> new Expression('A.ResourceId'),
							'AnalysisId'=> new Expression('A.AnalysisId'),
							'RequestQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'BalReqQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'POQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalPOQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'MinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BalMinQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'BillQty'=>new Expression('CAST(0 As Decimal(18,6))'),						
							'TransferQty'=>new Expression("A.TransferQty"),
							))
							
								->join(array('B' => 'MMS_TransferTrans'), 'A.TransferTransId=B.TransferTransId And A.ResourceId=B.ResourceId', array(), $select5::JOIN_INNER)
								->join(array('C' => 'MMS_TransferRegister'), 'B.TransferRegisterId = C.TVRegisterId', array(), $select5::JOIN_INNER)							
						->where(array("C.ToCostCentreId=$CostCentreId"));
					$select5->combine($select4,'Union ALL');
					
					$select6 = $sql->select();
					$select6->from(array('G' => $select5))
						->columns(array(
							'ResourceId'=> new Expression('G.ResourceId'),
							'AnalysisId'=> new Expression('G.AnalysisId'),
							'RequestQty'=> new Expression('SUM(G.RequestQty)'),
							'BalReqQty'=> new Expression('SUM(G.BalReqQty)'),
							'POQty'=>new Expression('SUM(G.POQty)'),						
							'BalPOQty'=>new Expression('SUM(G.BalPOQty)'),						
							'MinQty'=>new Expression('SUM(G.MinQty)'),						
							'BalMinQty'=>new Expression('SUM(G.BalMinQty)'),						
							'BillQty'=>new Expression('SUM(G.BillQty)'),						
							'TransferQty'=>new Expression("SUM(G.TransferQty)"),
							));				
					$select6->group(array (new Expression("G.ResourceId,G.AnalysisId")));
					
					$select7 = $sql->select();
					$select7->from(array('G1' => $select6))
						->columns(array(
							'ResourceId'=> new Expression('G1.ResourceId'),
							'AnalysisId'=> new Expression('G1.AnalysisId'),
							'PLevel'=>new Expression('G3.ParentText'),																
							'EstimateQty'=>new Expression('CAST(G2.Qty As Decimal(18,6))'),						
							'EstRate'=>new Expression('CAST(G2.Rate As Decimal(18,3))'),						
							'RequestQty'=>new Expression('CAST(G1.RequestQty As Decimal(18,6))'),						
							'BalReqQty'=>new Expression("G1.BalReqQty"),
							'POQty'=>new Expression("CAST(G1.POQty As Decimal(18,6))"),
							'BalPOQty'=>new Expression("CAST(G1.BalPOQty As Decimal(18,6))"),
							'MinQty'=>new Expression("CAST(G1.MinQty AS Decimal(18,6))"),
							'BalMinQty'=>new Expression("CAST(G1.BalMinQty As Decimal(18,6))"),
							'BillQty'=>new Expression("CAST(G1.BillQty As Decimal(18,6))"),
							'TransferQty'=>new Expression("CAST(G1.TransferQty As Decimal(18,6))"),
							'BalQty'=>new Expression("CAST((G2.Qty-(G1.MinQty+G1.BillQty+G1.TransferQty+G1.BalReqQty+G1.BalPOQty)) As Decimal(18,6))"),
							))
							
								->join(array('G2' => 'Proj_ProjectWbsResource'), 'G1.ResourceId=G2.ResourceId And G1.AnalysisId=G2.WbsId', array(), $select7::JOIN_INNER)
								->join(array('G3' => 'Proj_WbsMaster'), 'G2.WBSId=G3.WBSId', array('WBSName'), $select7::JOIN_INNER)							
								->join(array('G4' => 'Proj_Resource'), 'G1.ResourceId=G4.ResourceId', array('Code','ResourceName'), $select7::JOIN_INNER)							
						->where(array("G2.ProjectId=4"));

					$statement = $statement = $sql->getSqlStringForSqlObject($select7); 
					$wbsreport = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					$this->_view->setTerminal(true);
					$response = $this->getResponse()->setContent(json_encode($wbsreport));
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

	public function warehousewiseDetailedstockAction(){
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
				$WareHouse =$this->params()->fromPost('WareHouse'); 
				if($WareHouse == ""){
					$WareHouse =0;
				}
				$Ondate= $this->bsf->isNullCheck($postParams['AsonDate'],'string');

                if($Ondate == ''){
                    $Ondate =  0;
                }         
                if($Ondate == 0) {
                    $AsonDate = date('Y-m-d', strtotime(Date('d-m-Y')));
                }
                else
                {
                    $AsonDate=date('Y-m-d',strtotime($Ondate));
                }
				
				switch($Type) {
					case 'cost':
					
					$select1 = $sql->select();
					$select1->from(array('A' => 'MMS_StockTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('B.ResourceId'),
							'ItemId'=> new Expression('B.ItemId'),
							'Qty'=> new Expression('ISNULL(A.OpeningStock,0)'),
							'Amount'=> new Expression('(ISNULL(A.OpeningStock,0)*ISNULL(B.ORate,0))'),
							))
							->join(array('B' => 'MMS_Stock'), 'A.StockId=B.StockId', array(), $select1::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'), 'A.WareHouseId=C.TransId', array(), $select1::JOIN_INNER)
						->where(array("C.WareHouseId IN ($WareHouse) And ISNULL(A.OpeningStock,0)>0"));
						
					$select2 = $sql->select();
					$select2->from(array('A' => 'MMS_DCWareHouseTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'ResourceId'=> new Expression('C.ResourceId'),
							'ItemId'=> new Expression('C.ItemId'),
							'Qty'=> new Expression('ISNULL(A.DCQty,0)'),
							'Amount'=> new Expression('Case When ((E.PurchaseTypeId=0 Or E.PurchaseTypeId=5) And F.SEZProject=0 And (SM.StateId=CM.StateId)) Then 
													 (ISNULL(A.DCQty,0)*ISNULL(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((C.GrossRate*B.TFactor),0)/nullif(B.FFactor,0) Else C.GrossRate End,0)) Else 
													 (ISNULL(A.DCQty,0)*ISNULL(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((C.QRate*B.TFactor),0)/nullif(B.FFactor,0) Else C.QRate End,0)) End'),
							))
							->join(array('B' => 'MMS_DCGroupTrans'),'A.DCGroupId=B.DCGroupId', array(), $select2::JOIN_INNER)
							->join(array('C' => 'MMS_DCTrans'),'B.DCGroupId=C.DCGroupId And B.DCRegisterId=C.DCRegisterId', array(), $select2::JOIN_INNER)
							->join(array('D' => 'MMS_WareHouseDetails'),'A.WareHouseId=D.TransId',array(),$select2::JOIN_INNER)				
							->join(array('E' => 'MMS_DCRegister'),'B.DCRegisterId=E.DCRegisterId And C.DCRegisterId=E.DCRegisterId',array(),$select2::JOIN_INNER)				
							->join(array('F' => 'WF_OperationalCostCentre'),'E.CostCentreId=F.CostCentreId And A.CostCentreId=F.CostCentreId',array(),$select2::JOIN_INNER)				
							->join(array('VM' => 'Vendor_Master'),'E.VendorId=VM.VendorId',array(),$select2::JOIN_INNER)				
							->join(array('CM' => 'WF_CityMaster'),'VM.CityId=CM.CityId',array(),$select2::JOIN_LEFT)				
							->join(array('CC' => 'WF_CostCentre'),'F.FACostCentreId=CC.CostCentreId',array(),$select2::JOIN_INNER)				
							->join(array('SM' => 'WF_StateMaster'),'CC.StateId=SM.StateId',array(),$select2::JOIN_INNER)				
						->where(array("ISNULL(A.DCQty,0)>0 And E.DCOrCSM=1 And D.WareHouseId IN ($WareHouse) And E.DCDate <= '$AsonDate'"));
					$select2->combine($select1,'Union ALL');
					
					$select3 = $sql->select();
					$select3->from(array('A' => 'MMS_DCWareHouseTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'ResourceId'=> new Expression('C.ResourceId'),
							'ItemId'=> new Expression('C.ItemId'),
							'Qty'=> new Expression('ISNULL(A.DCQty,0)'),
							'Amount'=> new Expression('Case When ((E.PurchaseTypeId=0 Or E.PurchaseTypeId=5) And F.SEZProject=0 And (SM.StateId=CM.StateId)) Then 
													 (ISNULL(A.DCQty,0)*ISNULL(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((C.GrossRate*B.TFactor),0)/nullif(B.FFactor,0) Else C.GrossRate End,0)) Else 
													 (ISNULL(A.DCQty,0)*ISNULL(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((C.QRate*B.TFactor),0)/nullif(B.FFactor,0) Else C.QRate End,0)) End'),
							))
							->join(array('B' => 'MMS_DCGroupTrans'),'A.DCGroupId=B.DCGroupId', array(), $select3::JOIN_INNER)
							->join(array('C' => 'MMS_DCTrans'),'B.DCGroupId=C.DCGroupId And B.DCRegisterId=C.DCRegisterId', array(), $select3::JOIN_INNER)
							->join(array('D' => 'MMS_WareHouseDetails'),'A.WareHouseId=D.TransId',array(),$select3::JOIN_INNER)				
							->join(array('E' => 'MMS_DCRegister'),'B.DCRegisterId=E.DCRegisterId And C.DCRegisterId=E.DCRegisterId',array(),$select3::JOIN_INNER)				
							->join(array('F' => 'WF_OperationalCostCentre'),'C.CostCentreId=F.CostCentreId And A.CostCentreId=F.CostCentreId',array(),$select3::JOIN_INNER)				
							->join(array('VM' => 'Vendor_Master'),'E.VendorId=VM.VendorId',array(),$select3::JOIN_INNER)				
							->join(array('CM' => 'WF_CityMaster'),'VM.CityId=CM.CityId',array(),$select3::JOIN_LEFT)				
							->join(array('CC' => 'WF_CostCentre'),'F.FACostCentreId=CC.CostCentreId',array(),$select3::JOIN_INNER)				
							->join(array('SM' => 'WF_StateMaster'),'CC.StateId=SM.StateId',array(),$select3::JOIN_INNER)				
						->where(array("ISNULL(A.DCQty,0)>0 And E.DCOrCSM=1 And A.CostCentreId=0 And D.WareHouseId IN ($WareHouse) And E.DCDate <= '$AsonDate'"));
					$select3->combine($select2,'Union ALL');
					
					
					$select4 = $sql->select();
					$select4->from(array('A' => 'MMS_PVWHTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'ResourceId'=> new Expression('C.ResourceId'),
							'ItemId'=> new Expression('C.ItemId'),
							'Qty'=> new Expression('ISNULL(A.BillQty,0)'),
							'Amount'=> new Expression('Case When ((E.PurchaseTypeId=0 Or E.PurchaseTypeId=5) And F.SEZProject=0 And (SM.StateId=CM.StateId)) Then 
													 (ISNULL(A.BillQty,0)* ISNULL(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((C.GrossRate*B.TFactor),0)/nullif(B.FFactor,0) Else C.GrossRate End,0)) Else 
													 (ISNULL(A.BillQty,0)*ISNULL(Case When (B.FFactor>0 And B.TFactor>0) Then isnull((C.QRate*B.TFactor),0)/nullif(B.FFactor,0) Else C.QRate End,0)) End'),
							))
							->join(array('B' => 'MMS_PVGroupTrans'),'A.PVGroupId=B.PVGroupId', array(), $select4::JOIN_INNER)
							->join(array('C' => 'MMS_PVTrans'),'B.PVGroupId=C.PVGroupId And B.PVRegisterId=C.PVRegisterId', array(), $select4::JOIN_INNER)
							->join(array('D' => 'MMS_WareHouseDetails'),'A.WareHouseId=D.TransId',array(),$select4::JOIN_INNER)				
							->join(array('E' => 'MMS_PVRegister'),'B.PVRegisterId=E.PVRegisterId And C.PVRegisterId=E.PVRegisterId',array(),$select4::JOIN_INNER)				
							->join(array('F' => 'WF_OperationalCostCentre'),'E.CostCentreId=F.CostCentreId',array(),$select4::JOIN_INNER)				
							->join(array('VM' => 'Vendor_Master'),'E.VendorId=VM.VendorId',array(),$select4::JOIN_INNER)				
							->join(array('CM' => 'WF_CityMaster'),'VM.CityId=CM.CityId',array(),$select4::JOIN_LEFT)				
							->join(array('CC' => 'WF_CostCentre'),'F.FACostCentreId=CC.CostCentreId',array(),$select4::JOIN_INNER)				
							->join(array('SM' => 'WF_StateMaster'),'CC.StateId=SM.StateId',array(),$select4::JOIN_INNER)				
						->where(array("ISNULL(A.BillQty,0)>0 And E.ThruPO='Y' And D.WareHouseId IN ($WareHouse) And E.PVDate <= '$AsonDate'"));
					$select4->combine($select3,'Union ALL');
					
					$select5 = $sql->select();
					$select5->from(array('A' => 'MMS_TransferWareHouseTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('B.ResourceId'),
							'ItemId'=> new Expression('B.ItemId'),
							'Qty'=> new Expression('-ISNULL(A.Qty,0)'),
							'Amount'=> new Expression('-(ISNULL(A.Qty,0)*ISNULL(B.Rate,0))'),
							))
							->join(array('B' => 'MMS_TransferTrans'),'A.TransferTransId=B.TransferTransId',array(), $select5::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'),'A.WareHouseId=C.TransId', array(), $select5::JOIN_INNER)
							->join(array('D' => 'MMS_TransferRegister'),'B.TransferRegisterId=D.TVRegisterId And A.CostCentreId=D.FromCostCentreId',array(),$select5::JOIN_INNER)				
							->join(array('E' => 'WF_OperationalCostCentre'),'D.FromCostCentreId=E.CostCentreId And A.CostCentreId=E.CostCentreId',array(),$select5::JOIN_INNER)								
						->where(array("ISNULL(A.Qty,0)>0 And C.WareHouseId IN ($WareHouse) And D.TVDate <= '$AsonDate'"));
					$select5->combine($select4,'Union ALL');
					
					$select6 = $sql->select();
					$select6->from(array('A' => 'MMS_TransferWareHouseTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('B.ResourceId'),
							'ItemId'=> new Expression('B.ItemId'),
							'Qty'=> new Expression('ISNULL(A.Qty,0)'),
							'Amount'=> new Expression('(ISNULL(A.Qty,0)*ISNULL(B.Rate,0))'),
							))
							->join(array('B' => 'MMS_TransferTrans'),new Expression('A.TransferTransId=B.TransferTransId And B.RecdQty>0'),array(), $select6::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'),'A.WareHouseId=C.TransId', array(), $select6::JOIN_INNER)
							->join(array('D' => 'MMS_TransferRegister'),'B.TransferRegisterId=D.TVRegisterId And A.CostCentreId=D.ToCostCentreId',array(),$select6::JOIN_INNER)				
							->join(array('E' => 'WF_OperationalCostCentre'),'D.ToCostCentreId=E.CostCentreId And A.CostCentreId=E.CostCentreId',array(),$select6::JOIN_INNER)								
						->where(array("ISNULL(A.Qty,0)>0 And C.WareHouseId IN ($WareHouse) And D.TVDate <= '$AsonDate'"));
					$select6->combine($select5,'Union ALL');
					
					$select7 = $sql->select();
					$select7->from(array('A' => 'MMS_PRWareHouseTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('B.ResourceId'),
							'ItemId'=> new Expression('B.ItemId'),
							'Qty'=> new Expression('ISNULL(A.PRQty,0)'),					
							'Amount'=> new Expression('(ISNULL(A.PRQty,0)*ISNULL(B.QRate,0))'),
							))
							->join(array('B' => 'MMS_PRTrans'),'A.PRTransId=B.PRTransId',array(), $select7::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'),'A.WareHouseId=C.TransId', array(), $select7::JOIN_INNER)
							->join(array('D' => 'MMS_PRRegister'),'B.PRRegisterId=D.PRRegisterId',array(),$select7::JOIN_INNER)									
						->where(array("ISNULL(A.PRQty,0)>0 And C.WareHouseId IN ($WareHouse) And D.PRDate <= '$AsonDate'"));
					$select7->combine($select6,'Union ALL');
					
					$select8 = $sql->select();
					$select8->from(array('A' => 'MMS_IssueWareHouseTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('B.ResourceId'),
							'ItemId'=> new Expression('B.ItemId'),
							'Qty'=> new Expression('-ISNULL(A.IssueQty,0)'),
							'Amount'=> new Expression('-(ISNULL(A.IssueQty,0)*ISNULL(Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0))'),
							))
							->join(array('B' => 'MMS_IssueTrans'),'A.IssueTransId=B.IssueTransId',array(), $select8::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'),'A.WareHouseId=C.TransId', array(), $select8::JOIN_INNER)
							->join(array('D' => 'MMS_IssueRegister'),new Expression('B.IssueRegisterId=D.IssueRegisterId And D.IssueOrReturn=0'),array(),$select8::JOIN_INNER)									
						->where(array("ISNULL(A.IssueQty,0)>0 And B.IssueOrReturn='I' And C.WareHouseId IN ($WareHouse) And D.IssueDate <= '$AsonDate'"));
					$select8->combine($select7,'Union ALL');
					
					$select9 = $sql->select();
					$select9->from(array('A' => 'MMS_IssueWareHouseTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('B.ResourceId'),
							'ItemId'=> new Expression('B.ItemId'),
							'Qty'=> new Expression('ISNULL(A.IssueQty,0)'),							
							'Amount'=> new Expression('(ISNULL(A.IssueQty,0)*ISNULL(Case When (B.TFactor>0 And B.FFactor>0) Then isnull((B.IssueRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.IssueRate End,0))'),
							))
							->join(array('B' => 'MMS_IssueTrans'),'A.IssueTransId=B.IssueTransId',array(), $select9::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'),'A.WareHouseId=C.TransId', array(), $select9::JOIN_INNER)
							->join(array('D' => 'MMS_IssueRegister'),new Expression('B.IssueRegisterId=D.IssueRegisterId And D.IssueOrReturn=1'),array(),$select9::JOIN_INNER)									
						->where(array("ISNULL(A.IssueQty,0)>0 And B.IssueOrReturn='R' And C.WareHouseId IN ($WareHouse) And D.IssueDate <= '$AsonDate'"));
					$select9->combine($select8,'Union ALL');
					
					$select10 = $sql->select();
					$select10->from(array('A' => 'MMS_WHTTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('A.ResourceId'),
							'ItemId'=> new Expression('A.ItemId'),
							'Qty'=> new Expression('-ISNULL(A.TransferQty,0)'),
							'Amount'=> new Expression('CAST(0 As Decimal(18,6))'),
							))
							->join(array('B' => 'MMS_WHTRegister'),'A.WHTRegisterId=B.WTRegisterId',array(), $select10::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'),'B.FWHId=C.TransId', array(), $select10::JOIN_INNER)	
						->where(array("C.WareHouseId IN ($WareHouse) And B.WTDate <= '$AsonDate'"));
					$select10->combine($select9,'Union ALL');
					
					$select11 = $sql->select();
					$select11->from(array('A' => 'MMS_WHTTrans'))
						->columns(array(
							'WareHouseId'=> new Expression('C.WareHouseId'),
							'ResourceId'=> new Expression('A.ResourceId'),
							'ItemId'=> new Expression('A.ItemId'),
							'Qty'=> new Expression('ISNULL(A.TransferQty,0)'),
							'Amount'=> new Expression('CAST(0 As Decimal(18,6))'),
							))
							->join(array('B' => 'MMS_WHTRegister'),'A.WHTRegisterId=B.WTRegisterId',array(), $select11::JOIN_INNER)
							->join(array('C' => 'MMS_WareHouseDetails'),'B.TWHId=C.TransId', array(), $select11::JOIN_INNER)						
						->where(array(" C.WareHouseId IN ($WareHouse) And B.WTDate <= '$AsonDate'"));
					$select11->combine($select10,'Union ALL');
					
					$select12 = $sql->select();
					$select12->from(array('G' => $select11))
						->columns(array(
							'WareHouseId'=> new Expression('G.WareHouseId'),
							'ResourceId'=> new Expression('G.ResourceId'),
							'ItemId'=> new Expression('G.ItemId'),
							'Qty'=> new Expression('CAST(SUM(G.Qty) As Decimal(18,5))'),
							'Rate'=> new Expression('CAST(ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0) As Decimal(18,3))'),
							'Amount'=> new Expression('CAST(SUM(G.Qty) * (ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0)) As Decimal(18,3))'),
							));				
					$select12->group(array (new Expression("G.WareHouseId,G.ResourceId,G.ItemId")));
					
					$select13 = $sql->select();
					$select13->from(array('G1' => $select12))
						->columns(array(
							'WareHouse'=> new Expression('B.WareHouseName'),
							'Code'=> new Expression('Case When G1.ItemId > 0 Then D.ItemCode Else C.Code End'),
							'Resource'=> new Expression('Case When G1.ItemId > 0 Then D.BrandName Else C.ResourceName End'),
							'Unit'=> new Expression('Case When G1.ItemId > 0 Then U2.UnitName Else U1.UnitName End'),
							'ClosingStock'=> new Expression('G1.Qty'),
							'Rate'=> new Expression('G1.Rate'),
							'Amount'=> new Expression('G1.Amount'),
							))
							->join(array('B' => 'MMS_WareHouse'),'G1.WareHouseId=B.WareHouseId ',array(), $select13::JOIN_INNER)
							->join(array('C' => 'Proj_Resource'),'G1.ResourceId=C.ResourceId', array(), $select13::JOIN_INNER)						
							->join(array('D' => 'MMS_Brand'),'G1.ResourceId=D.ResourceId And G1.ItemId=D.BrandId', array(), $select13::JOIN_LEFT)						
							->join(array('U1' => 'Proj_UOM'),'C.UnitId=U1.UnitID', array(), $select13::JOIN_LEFT)						
							->join(array('U2' => 'Proj_UOM'),'D.UnitID=U2.UnitID', array(), $select13::JOIN_LEFT)						
						->where(array("G1.Qty>0"));
					$statement = $statement = $sql->getSqlStringForSqlObject($select13); 
					$warehousedetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					$this->_view->setTerminal(true);
					$response = $this->getResponse()->setContent(json_encode($warehousedetails));
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
			$select = $sql->select();
            $select->from(array('a' => 'MMS_WareHouse'))
                ->columns(array('WareHouseId', 'WareHouseName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}

	public function consolidatedStockStatementAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
		$sql = new Sql($dbAdapter);
		$request = $this->getRequest();
        $response = $this->getResponse();
		
		if($this->getRequest()->isXmlHttpRequest())	{
			$resp =  array();
			$request = $this->getRequest();
			if ($request->isPost()) {
				$postParams = $request->getPost();
				
                $Value = $this->bsf->isNullCheck($this->params()->fromPost('Value'), 'string');	
				$Company =$this->params()->fromPost('Company'); 
				if($Company == ""){
					$Company =0;
				}
				$type= $this->bsf->isNullCheck($postParams['type'],'string'); 
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
				$Ondate= $this->bsf->isNullCheck($postParams['AsonDate'],'string');
                if($Ondate == ''){
                    $Ondate =  0;
                }         
                if($Ondate == 0) {
                    $AsonDate = date('Y-m-d', strtotime(Date('d-m-Y')));
                }
                else
                {
                    $AsonDate=date('Y-m-d',strtotime($Ondate));
                }
					/*Main*/
					$select = $sql->select();
					$select->from(array('A' => 'Proj_Resource'))
						->columns(array(							
							'ResourceId'=> new Expression('A.ResourceId'),
							'ItemId'=> new Expression('ISNULL(B.BrandId,0)'),
							'ResourceGroup'=> new Expression('C.ResourceGroupName'),
							'Code'=> new Expression('Case When ISNULL(B.BrandId,0)>0 Then B.ItemCode Else A.Code End'),
							'Resource'=> new Expression('Case When ISNULL(B.BrandId,0)>0 Then B.BrandName Else A.ResourceName End'),
							'Unit'=> new Expression('Case When ISNULL(B.BrandId,0)>0 Then E.UnitName Else D.UnitName End'),
							'Op.Balance'=> new Expression('CAST(0 As Decimal(18,6))'),
							'ORate'=> new Expression('CAST(0 As Decimal(18,6))'),
							'OValue'=> new Expression('CAST(0 As Decimal(18,6))'),
							'StockInQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'StockInRate'=> new Expression('CAST(0 As Decimal(18,6))'),
							'StockInValue'=> new Expression('CAST(0 As Decimal(18,6))'),
							'StockOutQty'=> new Expression('CAST(0 As Decimal(18,6))'),
							'StockOutRate'=> new Expression('CAST(0 As Decimal(18,6))'),
							'StockOutValue'=> new Expression('CAST(0 As Decimal(18,6))'),
							))
						->join(array('B' => 'MMS_Brand'), new Expression('A.ResourceId=ISNULL(B.ResourceId,0)'), array(), $select::JOIN_LEFT)	
						->join(array('C' => 'Proj_ResourceGroup'), 'A.ResourceGroupId=C.ResourceGroupId', array(), $select::JOIN_LEFT)	
						->join(array('D' => 'Proj_UOM'), 'A.UnitId=D.UnitId', array(), $select::JOIN_LEFT)	
						->join(array('E' => 'Proj_UOM'), 'B.UnitId=E.UnitId', array(), $select::JOIN_LEFT);
					$statement = $sql->getSqlStringForSqlObject($select); 
					$consolidatedstockdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
					/*Op.Balance,ORate,OValue*/
					foreach($consolidatedstockdetails as &$consolidated) {					
						$ResourceId=$consolidated['ResourceId']; 
						$ItemId=$consolidated['ItemId']; 
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_Stock'))
							->columns(array(							
								'ResourceId'=> new Expression('ResourceId'),
								'ItemId'=> new Expression('ItemId'),
								'Op.Balance'=> new Expression('SUM(ISNULL(OpeningStock,0))'),
								'OValue'=> new Expression('SUM((ISNULL(OpeningStock,0)*ISNULL(ORate,0)))'),
								))
								->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array(), $select1::JOIN_INNER)					
							->where(array("B.CompanyId=$Company Group BY ResourceId,ItemId"));
							
						$select2 = $sql->select();
						$select2->from(array('G' => $select1))
							->columns(array(							
								'ResourceId'=> new Expression('G.ResourceId'),
								'ItemId'=> new Expression('G.ItemId'),
								'Op.Balance'=> new Expression('G.[Op.Balance]'),
								'ORate'=> new Expression('ISNULL((ISNULL(G.OValue,0)/NULLIF(G.[Op.Balance],0)),0)'),
								'OValue'=> new Expression('CAST(CAST(ISNULL((ISNULL(G.OValue,0)/NULLIF(G.[Op.Balance],0)),0) As Decimal(18,2)) * G.[Op.Balance]  As Decimal(18,2))'),
								));						
						$statement = $sql->getSqlStringForSqlObject($select2); 
						$consolidatedstockdetails1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($consolidatedstockdetails1 as $consolidated1) {
							if($consolidated1['ResourceId']==$consolidated['ResourceId']&&$consolidated1['ItemId']==$consolidated['ItemId']){
								$consolidated['Op.Balance']=$consolidated1['Op.Balance']; 
								$consolidated['ORate']=$consolidated1['ORate'];
								$consolidated['OValue']=$consolidated1['OValue'];
							}
						}
						
					/*StockOutQty,StockOutRate,StockOutValue*/						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_TransferTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression('SUM(A.TransferQty)'),
								'Amount'=> new Expression('SUM(A.TransferQty*A.Rate)'),
								))
								->join(array('B' => 'MMS_TransferRegister'), 'A.TransferRegisterId=B.TVRegisterId', array(), $select1::JOIN_INNER)					
								->join(array('C' => 'WF_OperationalCostCentre'), 'B.FromCostCentreId=C.CostCentreId ', array(), $select1::JOIN_INNER);
						if($type==1) {
							$select1->where(array("A.TransferQty>0 And C.CompanyId=$Company  And B.TVDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId"));
						}else{
						$select1->where(array("A.TransferQty>0 And C.CompanyId=$Company  And B.TVDate <= '$AsonDate' Group By A.ResourceId,A.ItemId"));
						}
						
						$select2 = $sql->select();
						$select2->from(array('A' => 'MMS_IssueTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression('SUM(A.IssueQty)'),
								'Amount'=> new Expression('SUM(A.IssueQty* Case When (A.TFactor>0 And A.FFactor>0) Then isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.IssueRate End)'),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'A.IssueRegisterId=B.IssueRegisterId', array(), $select2::JOIN_INNER)					
								->join(array('C' => 'WF_OperationalCostCentre'), 'B.CostCentreId=C.CostCentreId', array(), $select2::JOIN_INNER);
						if($type==1) {
							$select2->where(array("A.IssueQty>0 And A.IssueOrReturn='I' And C.CompanyId=$Company  And B.IssueDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId"));
						}else{
						$select2->where(array("A.IssueQty>0 And A.IssueOrReturn='I' And C.CompanyId=$Company  And B.IssueDate <= '$AsonDate' Group By A.ResourceId,A.ItemId"));
						}
						$select2->combine($select1,'Union ALL');
						
						$select3 = $sql->select();
						$select3->from(array('A' => 'MMS_PRTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression('SUM(A.ReturnQty)'),
								'Amount'=> new Expression('SUM(A.Amount)'),
								))
								->join(array('B' => 'MMS_PRRegister'), 'A.PRRegisterId=B.PRRegisterId', array(), $select3::JOIN_INNER)					
								->join(array('C' => 'WF_OperationalCostCentre'), 'B.CostCentreId=C.CostCentreId', array(), $select3::JOIN_INNER);
						if($type==1) {
							$select3->where(array("A.ReturnQty>0  And C.CompanyId= $Company And B.PRDate Between ('$fromDate') And ('$toDate')  Group By A.ResourceId,A.ItemId"));
						}else{
						$select3->where(array("A.ReturnQty>0  And C.CompanyId= $Company And B.PRDate <= '$AsonDate' Group By A.ResourceId,A.ItemId"));
						}
						$select3->combine($select2,'Union ALL');
						
						$select4 = $sql->select();
						$select4->from(array('A' => 'MMS_PVRateAdjustment'))
							->columns(array(							
								'ResourceId'=> new Expression('B.ResourceId'),
								'ItemId'=> new Expression('B.ItemId'),
								'Qty'=> new Expression('CAST(0 As Decimal(18,6))'),
								'Amount'=> new Expression('SUM(Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.Amount*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.Amount End)'),
								))
								->join(array('B' => 'MMS_PVTrans'), 'A.PVTransId=B.PVTransId And A.PVRegisterId=B.PVRegisterId', array(), $select4::JOIN_INNER)					
								->join(array('PG' => 'MMS_PVGroupTrans'), 'B.PVGroupId=PG.PVGroupId And B.PVRegisterId=PG.PVRegisterId', array(), $select4::JOIN_INNER)					
								->join(array('C' => 'MMS_PVRegister'), 'B.PVRegisterId=C.PVRegisterId', array(), $select4::JOIN_INNER)					
								->join(array('D' => 'WF_OperationalCostCentre'), 'C.CostCentreId=D.CostCentreId', array(), $select4::JOIN_INNER);
						if($type==1) {
							$select4->where(array("A.Type='C' And D.CompanyId=$Company And C.PVDate Between ('$fromDate') And ('$toDate') Group By B.ResourceId,B.ItemId,C.CostCentreId,PG.FFactor,PG.TFactor"));
						}else{
						$select4->where(array("A.Type='C' And D.CompanyId=$Company And C.PVDate <= '$AsonDate' Group By B.ResourceId,B.ItemId,C.CostCentreId,PG.FFactor,PG.TFactor"));
						}
						$select4->combine($select3,'Union ALL');
						
						$select5 = $sql->select();
						$select5->from(array('G' => $select4))
							->columns(array(							
								'ResourceId'=> new Expression('G.ResourceId'),
								'ItemId'=> new Expression('G.ItemId'),
								'StockOutQty'=> new Expression('SUM(G.Qty)'),
								'StockOutRate'=> new Expression('ISNULL(ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0),0)'),
								'StockOutValue'=> new Expression('CAST( ISNULL(ISNULL(SUM(G.Amount),0)/NULLIF(SUM(G.Qty),0),0) * SUM(G.Qty)  As Decimal(18,2))'),
								));							
						$select5->group(array (new Expression("G.ResourceId,G.ItemId")));
					 	$statement = $sql->getSqlStringForSqlObject($select5);
						$consolidatedstockdetails2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
						foreach($consolidatedstockdetails2 as $consolidated2) {
							if($consolidated2['ResourceId']==$consolidated['ResourceId']&&$consolidated2['ItemId']==$consolidated['ItemId']){
								$consolidated['StockOutQty']=$consolidated2['StockOutQty'];
								$consolidated['StockOutRate']=$consolidated2['StockOutRate'];
								$consolidated['StockOutValue']=$consolidated2['StockOutValue'];
							}
						}

						/*StockInQty,StockInRate,StockInValue*/						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_Stock'))
							->columns(array(							
								'ResourceId'=> new Expression('ResourceId'),
								'ItemId'=> new Expression('ItemId'),
								'Op.Balance'=> new Expression('SUM(ISNULL(OpeningStock,0))'),
								'OValue'=> new Expression('SUM((ISNULL(OpeningStock,0)*ISNULL(ORate,0)))'),
								))
								->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array(), $select1::JOIN_INNER)														
							->where(array("B.CompanyId=$Company Group BY ResourceId,ItemId"));
							
						$select2 = $sql->select();
						$select2->from(array('G' => $select1))
							->columns(array(							
								'ResourceId'=> new Expression('G.ResourceId'),
								'ItemId'=> new Expression('G.ItemId'),
								'Qty'=> new Expression('G.[Op.Balance]'),
								'Amount'=> new Expression('CAST(CAST(ISNULL((ISNULL(G.OValue,0)/NULLIF(G.[Op.Balance],0)),0) As Decimal(18,2)) * G.[Op.Balance] As Decimal(18,2))'),
								));
						
						$select3 = $sql->select();
						$select3->from(array('A' => 'MMS_PVTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And E.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' Then 
	SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
	Else SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
	End Else Case When B.ThruDC='Y' THEN SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) 
	Else SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) End End"),
								))
								->join(array('PG' => 'MMS_PVGroupTrans'), 'A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId', array(), $select3::JOIN_INNER)														
								->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select3::JOIN_INNER)														
								->join(array('C' => 'MMS_DCTrans'), 'A.DCTransId=C.DCTransId And A.DCRegisterId=C.DCRegisterId', array(), $select3::JOIN_INNER)														
								->join(array('D' => 'MMS_DCRegister'), 'C.DCRegisterId=D.DCRegisterId', array(), $select3::JOIN_INNER)														
								->join(array('E' => 'WF_OperationalCostCentre'), 'B.CostCentreId=E.CostCentreId', array(), $select3::JOIN_INNER)														
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select3::JOIN_INNER)														
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select3::JOIN_LEFT)														
								->join(array('CC' => 'WF_CostCentre'), 'E.FACostCentreId=CC.CostCentreId', array(), $select3::JOIN_INNER)														
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select3::JOIN_INNER);
							if($type==1) {
							$select3->where(array("(A.BillQty>0 Or A.ActualQty>0) And E.CompanyId=$Company And D.DCDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId,B.ThruDC,B.PurchaseTypeId,E.SEZProject,PG.TFactor,PG.FFactor,SM.StateId,CM.StateId"));
							}else{
							$select3->where(array("(A.BillQty>0 Or A.ActualQty>0) And E.CompanyId=$Company And D.DCDate <= '$AsonDate' Group By A.ResourceId,A.ItemId,B.ThruDC,B.PurchaseTypeId,E.SEZProject,PG.TFactor,PG.FFactor,SM.StateId,CM.StateId"));
							}
						$select3->combine($select2,'Union ALL');
						
						$select4 = $sql->select();
						$select4->from(array('A' => 'MMS_PVTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And C.SEZProject=0 And (SM.StateId=CM.StateId) ) Then Case When B.ThruDC='Y' Then 
	SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End)  
	Else SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
	End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) 
	Else SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) End End"),
								))
								->join(array('PG' => 'MMS_PVGroupTrans'), 'A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId', array(), $select4::JOIN_INNER)														
								->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select4::JOIN_INNER)																																		
								->join(array('C' => 'WF_OperationalCostCentre'), 'A.CostCentreId=C.CostCentreId', array(), $select4::JOIN_INNER)														
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select4::JOIN_INNER)														
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select4::JOIN_LEFT)														
								->join(array('CC' => 'WF_CostCentre'), 'C.FACostCentreId=CC.CostCentreId', array(), $select4::JOIN_INNER)														
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select4::JOIN_INNER);
							if($type==1) {
							$select4->where(array("(A.BillQty>0 Or A.ActualQty>0) And B.CostCentreId=0  And C.CompanyId=$Company And B.PVDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,C.SEZProject,PG.TFactor,PG.FFactor,SM.StateId,CM.StateId "));
							}else{
							$select4->where(array("(A.BillQty>0 Or A.ActualQty>0) And B.CostCentreId=0  And C.CompanyId=$Company And B.PVDate <= '$AsonDate' Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,C.SEZProject,PG.TFactor,PG.FFactor,SM.StateId,CM.StateId "));
							}
						$select4->combine($select3,'Union ALL');
						
						$select5 = $sql->select();
						$select5->from(array('A' => 'MMS_PVTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("Case When B.ThruDC='Y' Then SUM(A.ActualQty) Else SUM(A.BillQty) End"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And C.SEZProject=0 And (SM.StateId=CM.StateId)) Then Case When B.ThruDC='Y' 
	Then SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End)  
	Else SUM(A.BillQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.GrossRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.GrossRate End) 
	End Else Case When B.ThruDC='Y' Then SUM(A.ActualQty*Case When (PG.TFactor>0 And PG.FFactor>0) Then isnull((A.QRate*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.QRate End) 
	Else SUM(A.BillQty*A.QRate) End End"),
								))
								->join(array('PG' => 'MMS_PVGroupTrans'), 'A.PVGroupId=PG.PVGroupId And A.PVRegisterId=PG.PVRegisterId', array(), $select5::JOIN_INNER)														
								->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select5::JOIN_INNER)																																		
								->join(array('C' => 'WF_OperationalCostCentre'), 'B.CostCentreId=C.CostCentreId', array(), $select5::JOIN_INNER)														
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select5::JOIN_INNER)														
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select5::JOIN_LEFT)														
								->join(array('CC' => 'WF_CostCentre'), 'C.FACostCentreId=CC.CostCentreId', array(), $select5::JOIN_INNER)														
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select5::JOIN_INNER);
							if($type==1) {
							$select5->where(array("(A.BillQty>0 Or A.ActualQty>0) And B.ThruPO='Y' And C.CompanyId=$Company And B.PVDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,C.SEZProject,PG.FFactor,PG.TFactor,SM.StateId,CM.StateId"));
							}else{
							$select5->where(array("(A.BillQty>0 Or A.ActualQty>0) And B.ThruPO='Y' And C.CompanyId=$Company And B.PVDate <= '$AsonDate' Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,B.ThruDC,C.SEZProject,PG.FFactor,PG.TFactor,SM.StateId,CM.StateId"));
							}
						$select5->combine($select4,'Union ALL');
						
						$select6 = $sql->select();
						$select6->from(array('A' => 'MMS_DCTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("SUM(A.BalQty)"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And C.SEZProject=0 And (CM.StateId=SM.StateId) ) Then 
	SUM(A.BalQty*Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((A.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.GrossRate End) 
	Else SUM(A.BalQty*Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.QRate End) End"),
								))
								->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select6::JOIN_INNER)														
								->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select6::JOIN_INNER)																																		
								->join(array('C' => 'WF_OperationalCostCentre'), 'B.CostCentreId=C.CostCentreId', array(), $select6::JOIN_INNER)														
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select6::JOIN_INNER)														
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select6::JOIN_LEFT)														
								->join(array('CC' => 'WF_CostCentre'), 'C.FACostCentreId=CC.CostCentreId', array(), $select6::JOIN_INNER)														
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select6::JOIN_INNER);
							if($type==1) {
							$select6->where(array("A.BalQty>0 And B.DCOrCSM=1  And C.CompanyId=$Company And B.DCDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,C.SEZProject,DG.TFactor,DG.FFactor,SM.StateId,CM.StateId"));
							}else{
							$select6->where(array("A.BalQty>0 And B.DCOrCSM=1  And C.CompanyId=$Company And B.DCDate <= '$AsonDate' Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,C.SEZProject,DG.TFactor,DG.FFactor,SM.StateId,CM.StateId"));
							}
						$select6->combine($select5,'Union ALL');
						
						$select7 = $sql->select();
						$select7->from(array('A' => 'MMS_DCTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("SUM(A.BalQty)"),
								'Amount'=> new Expression("Case When ((B.PurchaseTypeId=0 Or B.PurchaseTypeId=5) And C.SEZProject=0 And (CM.StateId=SM.StateId)) Then 
	SUM(A.BalQty*Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((A.GrossRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.GrossRate End) 
	Else SUM(A.BalQty*Case When (DG.TFactor>0 And DG.FFactor>0) Then isnull((A.QRate*DG.TFactor),0)/nullif(DG.FFactor,0) Else A.QRate End) End"),
								))
								->join(array('DG' => 'MMS_DCGroupTrans'), 'A.DCGroupId=DG.DCGroupId And A.DCRegisterId=DG.DCRegisterId', array(), $select7::JOIN_INNER)														
								->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select7::JOIN_INNER)																																		
								->join(array('C' => 'WF_OperationalCostCentre'), 'A.CostCentreId=C.CostCentreId', array(), $select7::JOIN_INNER)														
								->join(array('VM' => 'Vendor_Master'), 'B.VendorId=VM.VendorId', array(), $select7::JOIN_INNER)														
								->join(array('CM' => 'WF_CityMaster'), 'VM.CityId=CM.CityId', array(), $select7::JOIN_LEFT)														
								->join(array('CC' => 'WF_CostCentre'), 'C.FACostCentreId=CC.CostCentreId', array(), $select7::JOIN_INNER)														
								->join(array('SM' => 'WF_StateMaster'), 'CC.StateId=SM.StateId', array(), $select7::JOIN_INNER);
							if($type==1) {
							$select7->where(array("A.BalQty>0 And B.CostCentreId=0 And B.DCOrCSM=1 And C.CompanyId=$Company And B.DCDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,C.SEZProject,DG.FFactor,DG.TFactor,SM.StateId,CM.StateId"));
							}else{
							$select7->where(array("A.BalQty>0 And B.CostCentreId=0 And B.DCOrCSM=1 And C.CompanyId=$Company And B.DCDate <= '$AsonDate' Group By A.ResourceId,A.ItemId,B.PurchaseTypeId,C.SEZProject,DG.FFactor,DG.TFactor,SM.StateId,CM.StateId"));
							}
						$select7->combine($select6,'Union ALL');
						
						$select8 = $sql->select();
						$select8->from(array('A' => 'MMS_IssueTrans'))
							->columns(array(							
								'ResourceId'=> new Expression('A.ResourceId'),
								'ItemId'=> new Expression('A.ItemId'),
								'Qty'=> new Expression("SUM(A.IssueQty)"),
								'Amount'=> new Expression("SUM(Case When (A.TFactor>0 And A.FFactor>0) Then (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0)) Else A.IssueAmount End)"),
								))
								->join(array('B' => 'MMS_IssueRegister'), 'A.IssueRegisterId=B.IssueRegisterId', array(), $select8::JOIN_INNER)														
								->join(array('C' => 'WF_OperationalCostCentre'), 'B.CostCentreId=C.CostCentreId', array(), $select8::JOIN_INNER);
							if($type==1) {
							$select8->where(array("A.IssueQty>0  And A.IssueOrReturn='R' And C.CompanyId=$Company  And B.IssueDate Between ('$fromDate') And ('$toDate') Group By A.ResourceId,A.ItemId "));
							}else{
							$select8->where(array("A.IssueQty>0  And A.IssueOrReturn='R' And C.CompanyId=$Company  And B.IssueDate <= '$AsonDate' Group By A.ResourceId,A.ItemId "));
							}
						$select8->combine($select7,'Union ALL');
						
						$select9 = $sql->select();
						$select9->from(array('A' => 'MMS_PVRateAdjustment'))
							->columns(array(							
								'ResourceId'=> new Expression('B.ResourceId'),
								'ItemId'=> new Expression('B.ItemId'),
								'Qty'=> new Expression("CAST(0 As Decimal(18,6))"),
								'Amount'=> new Expression("SUM(Case When (PG.FFactor>0 And PG.TFactor>0) Then isnull((A.Amount*PG.TFactor),0)/nullif(PG.FFactor,0) Else A.Amount End)"),
								))
								->join(array('B' => 'MMS_PVTrans'), 'A.PVTransId=B.PVTransId And A.PVRegisterId=B.PVRegisterId', array(), $select9::JOIN_INNER)														
								->join(array('PG' => 'MMS_PVGroupTrans'), 'B.PVGroupId=PG.PVGroupId And B.PVRegisterId=PG.PVRegisterId', array(), $select9::JOIN_INNER)																																															
								->join(array('C' => 'MMS_PVRegister'), 'B.PVRegisterId=C.PVRegisterId', array(), $select9::JOIN_INNER)																																															
								->join(array('D' => 'WF_OperationalCostCentre'), 'C.CostCentreId=D.CostCentreId', array(), $select9::JOIN_INNER);
							if($type==1) {
							$select9->where(array("A.Type='D' And D.CompanyId= $Company  And C.PVDate Between ('$fromDate') And ('$toDate') Group By B.ResourceId,B.ItemId,C.CostCentreId,PG.FFactor,PG.TFactor"));
							}else{
							$select9->where(array("A.Type='D' And D.CompanyId= $Company  And C.PVDate <= '$AsonDate'  Group By B.ResourceId,B.ItemId,C.CostCentreId,PG.FFactor,PG.TFactor"));
							}
						$select9->combine($select8,'Union ALL');
						
						$select10 = $sql->select();
						$select10->from(array('G' => $select9))
							->columns(array(							
								'ResourceId'=> new Expression('G.ResourceId'),
								'ItemId'=> new Expression('G.ItemId'),
								'StockInQty'=> new Expression("SUM(G.Qty)"),
								'StockInRate'=> new Expression("ISNULL(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0)"),
								'StockInValue'=> new Expression("CAST( ISNULL(isnull(SUM(G.Amount),0)/nullif(SUM(G.Qty),0),0) * SUM(G.Qty) As Decimal(18,2))"),
								));							
						$select10->group(array (new Expression("G.ResourceId,G.ItemId")));
						$statement = $sql->getSqlStringForSqlObject($select10);
						$consolidatedstockdetails3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();	
						foreach($consolidatedstockdetails3 as $consolidated3) {
							if($consolidated3['ResourceId']==$consolidated['ResourceId'] && $consolidated3['ItemId']==$consolidated['ItemId']){
								$consolidated['StockInQty']=$consolidated3['StockInQty'];
								$consolidated['StockInRate']=$consolidated3['StockInRate'];
								$consolidated['StockInValue']=$consolidated3['StockInValue'];
							}
						}
					}
					
					$this->_view->setTerminal(true);
					$response = $this->getResponse()->setContent(json_encode($consolidatedstockdetails));
					return $response;
					break;
					
				
			}	
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
                //Write your Normal form post code here

            }
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

    public function detailedstockstatementAction(){
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
                switch($Type) {
                    case 'detailss':
                        $companyId =$this->params()->fromPost('CompanyId');
                        $costcentreId = $this->params()->fromPost('CostCentreId');
                        $asOn= $this->bsf->isNullCheck($postParams['asOn'],'string');
                        if($asOn == ''){
                            $asOn =  0;
                        }
                        if($asOn == 0) {
                            $asOn = date('Y-m-d', strtotime(Date('d-m-Y')));
                        }
                        else
                        {
                            $asOn=date('Y-m-d',strtotime($asOn));
                        }


                        $selQ1 = $sql -> select();
                        $selQ1->from(array('a' => 'mms_stock'))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,a.OpeningStock As Qty,Amount=a.OpeningStock*a.ORate,a.CostCentreId As CostCentreId  ")))
                            ->join(array('b' => 'wf_operationalcostcentre'), 'a.costcentreid=b.costcentreid ',array(),$selQ1::JOIN_INNER)
                            ->where("a.OpeningStock>0 and b.CompanyId=$companyId");
                        if($costcentreId <> 0)
                        {
                            $selQ1 -> where(" b.CostCentreId IN ($costcentreId)");
                        }


                        $selQ2 = $sql -> select();
                        $selQ2->from(array('a' => 'MMS_PVRateAdjustment'))
                            ->columns(array(new Expression("b.ResourceId,b.ItemId,0 Qty,Amount=SUM(Case When (c.FFactor>0 and c.TFactor>0) Then isnull((-a.Amount*c.TFactor),0)/nullif(c.FFactor,0) Else -a.Amount End),
                               d.CostCentreId As CostCentreId ")))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId',array(),$selQ2::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVGroupTrans'),'b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId',array(),$selQ2::JOIN_INNER )
                            ->join(array('d' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$selQ2::JOIN_INNER)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'d.CostCentreId=e.CostCentreId',array(),$selQ2::JOIN_INNER)
                            ->where("d.PVDate <= '$asOn' and a.Type='C' and e.CompanyId=$companyId  ");
                        if($costcentreId <> 0)
                        {
                            $selQ2 -> where (" d.CostCentreId IN ($costcentreId)");
                        }
                        $selQ2 -> group (new Expression("b.ResourceId,b.ItemId,d.CostCentreId,c.FFactor,c.TFactor"));
                        $selQ2->combine($selQ1,'Union All');


                        $selQ3 = $sql -> select();
                        $selQ3->from(array('a' => 'MMS_PVRateAdjustment'))
                            ->columns(array(new Expression("b.ResourceId,b.ItemId,0 Qty,Amount=SUM(Case When (c.FFactor>0 and c.TFactor>0) Then isnull((a.Amount*c.TFactor),0)/nullif(c.FFactor,0) Else a.Amount End),
                               d.CostCentreId As CostCentreId")))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId and a.PVRegisterId=b.PVRegisterId',array(),$selQ3::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVGroupTrans'),'b.PVGroupId=c.PVGroupId and b.PVRegisterId=c.PVRegisterId',array(),$selQ3::JOIN_INNER )
                            ->join(array('d' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$selQ3::JOIN_INNER)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'d.CostCentreId=e.CostCentreId',array(),$selQ3::JOIN_INNER)
                            ->where("d.PVDate <= '$asOn' and a.Type='D' and e.CompanyId=$companyId ");
                        if($costcentreId <> 0)
                        {
                            $selQ3 -> where (" d.CostCentreId IN ($costcentreId)");
                        }
                        $selQ3 -> group(new Expression("b.ResourceId,b.ItemId,e.CostCentreId,c.FFactor,c.TFactor,d.CostCentreId"));
                        $selQ3->combine($selQ2,'Union All');

                        $selQ4 = $sql -> select();
                        $selQ4 -> from (array('a' => 'MMS_IssueTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(a.IssueQty),Amount=SUM(Case When (A.FFactor>0 And A.TFactor>0) Then  (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0))
                                        Else IssueAmount End),b.CostCentreId As CostCentreId")))
                            ->join(array('b' => 'MMS_IssueRegister'),'b.IssueRegisterId=a.IssueRegisterId',array(),$selQ4::JOIN_INNER)
                            ->join(array('c' => 'Proj_Resource'),'a.ResourceId=c.ResourceId',array(),$selQ4::JOIN_INNER)
                            ->join(array('d' => 'Proj_ResourceGroup'),'c.ResourceGroupId=d.ResourceGroupId',array(),$selQ4::JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'b.CostCentreId=e.CostCentreId',array(),$selQ4::JOIN_INNER)
                            ->where("a.IssueQty>0 and b.IssueDate <= '$asOn' and e.CompanyId=$companyId and a.IssueOrReturn='R' ");
                        if($costcentreId <> 0)
                        {
                            $selQ4 -> where (" b.CostCentreId IN ($costcentreId)");
                        }
                        $selQ4 -> group(new Expression("d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId"));
                        $selQ4->combine($selQ3,'Union All');


                        $selQ5 = $sql -> select();
                        $selQ5 -> from (array('a' => 'MMS_IssueTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(-a.IssueQty),Amount=SUM(-Case When (A.FFactor>0 And A.TFactor>0) Then  (A.IssueQty*isnull((A.IssueRate*A.TFactor),0)/nullif(A.FFactor,0))
                                    Else IssueAmount End),b.CostCentreId As CostCentreId ")))
                            ->join(array('b' => 'MMS_IssueRegister'),'b.IssueRegisterId=a.IssueRegisterId',array(),$selQ4::JOIN_INNER)
                            ->join(array('c' => 'Proj_Resource'),'a.ResourceId=c.ResourceId',array(),$selQ4::JOIN_INNER)
                            ->join(array('d' => 'Proj_ResourceGroup'),'c.ResourceGroupId=d.ResourceGroupId',array(),$selQ4::JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'b.CostCentreId=e.CostCentreId',array(),$selQ4::JOIN_INNER)
                            ->where("a.IssueQty>0 and b.IssueDate <= '$asOn' and e.CompanyId=$companyId and a.IssueOrReturn='R' ");
                        if($costcentreId <> 0)
                        {
                            $selQ5 -> where ("  b.CostCentreId IN ($costcentreId)");
                        }
                        $selQ5 -> group(new Expression("d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId"));
                        $selQ5->combine($selQ4,'Union All');

                        $selQ6 = $sql -> select();
                        $selQ6 -> from(array('a' => 'MMS_PRTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(-a.ReturnQty),Amount=SUM(-a.Amount),b.CostCentreId As CostCentreId")))
                            ->join(array('b' => 'MMS_PRRegister') ,'a.PRRegisterId=b.PRRegisterId',array(),$selQ6::JOIN_INNER )
                            ->join(array('c' => 'Proj_Resource'),'a.ResourceId=c.ResourceId',array(),$selQ6::JOIN_INNER)
                            ->join(array('d' => 'Proj_ResourceGroup'),'c.ResourceGroupId=d.ResourceGroupId',array(),$selQ6::JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'b.CostCentreId=e.CostCentreId',array(),$selQ6::JOIN_INNER)
                            ->where("a.ReturnQty>0 and b.PRDate <= '$asOn' and e.CompanyId=$companyId ");
                        if($costcentreId <> 0)
                        {
                            $selQ6 -> where ("   b.CostCentreId IN ($costcentreId) ");
                        }
                        $selQ6 -> group(new Expression("d.ResourceGroupId,a.ResourceId,a.ItemId,b.CostCentreId"));
                        $selQ6->combine($selQ5,'Union All');


                        $selQ7 = $sql -> select();
                        $selQ7 -> from(array('a' => 'MMS_TransferTrans'))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(a.RecdQty),Amount=SUM(a.RecdQty*a.Rate),b.ToCostCentreId As CostCentreId")))
                            ->join(array('b' => 'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$selQ7::JOIN_INNER)
                            ->join(array('c' => 'Proj_Resource'),'a.ResourceId=c.ResourceId',array(),$selQ7::JOIN_INNER)
                            ->join(array('d' => 'Proj_ResourceGroup'),'c.ResourceGroupId=d.ResourceGroupId',array(),$selQ7::JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'b.ToCostCentreId=e.CostCentreId',array(),$selQ7::JOIN_INNER)
                            ->where("a.RecdQty>0 and b.TVDate <= '$asOn' and e.CompanyId=$companyId ");
                        if($costcentreId <> 0)
                        {
                            $selQ7 -> where ("  b.ToCostCentreId IN ($costcentreId) ");
                        }
                        $selQ7 -> group(new Expression("d.ResourceGroupId,a.ResourceId,a.ItemId,b.ToCostCentreId"));
                        $selQ7->combine($selQ6,'Union All');



                        $selQ8 = $sql -> select();
                        $selQ8 -> from(array('a' => 'MMS_TransferTrans'))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(-a.TransferQty),Amount=SUM(-a.Amount),b.FromCostCentreId As CostCentreId")))
                            ->join(array('b' => 'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$selQ8::JOIN_INNER)
                            ->join(array('c' => 'Proj_Resource'),'a.ResourceId=c.ResourceId',array(),$selQ8::JOIN_INNER)
                            ->join(array('d' => 'Proj_ResourceGroup'),'c.ResourceGroupId=d.ResourceGroupId',array(),$selQ8::JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'b.FromCostCentreId=e.CostCentreId',array(),$selQ8::JOIN_INNER)
                            ->where("a.TransferQty>0 and b.TVDate <= '$asOn' and e.CompanyId=$companyId ");
                        if($costcentreId <> 0)
                        {
                            $selQ8 -> where ("  b.FromCostCentreId IN ($costcentreId) ");
                        }
                        $selQ8 -> group(new Expression("d.ResourceGroupId,a.ResourceId,a.ItemId,b.FromCostCentreId"));
                        $selQ8->combine($selQ7,'Union All');

                        $selQ9 = $sql -> select();
                        $selQ9 -> from(array('a' => 'MMS_DCTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(a.BalQty),Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 and (h.StateId=j.StateId))
                                then SUM(a.BalQty*Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                                else a.GrossRate End) Else Sum(a.BalQty*Case when (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) else
                                a.QRate End) End ,a.CostCentreId As CostCentreId  ")))
                            ->join(array('b' => 'MMS_DCGroupTrans'),'a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId',array(),$selQ9::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'a.DCRegisterId=c.DCRegisterId',array(),$selQ9::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'),'a.ResourceId=d.ResourceId',array(),$selQ9::JOIN_INNER)
                            ->join(array('e' => 'Proj_ResourceGroup'),'d.ResourceGroupId=e.ResourceGroupId',array(),$selQ9::JOIN_LEFT)
                            ->join(array('f' => 'WF_OperationalCostCentre'),'a.CostCentreId=f.CostCentreId',array(),$selQ9::JOIN_INNER)
                            ->join(array('g' => 'Vendor_Master'),'c.VendorId=g.VendorId',array(),$selQ9::JOIN_INNER)
                            ->join(array('h' => 'WF_CityMaster'),'g.CityId=h.CityId',array(),$selQ9::JOIN_LEFT)
                            ->join(array('i' => 'WF_CostCentre'),'f.FACostCentreId=i.CostCentreId',array(),$selQ9::JOIN_INNER)
                            ->join(array('j' => 'WF_StateMaster'),'i.StateId=j.StateId',array(),$selQ9::JOIN_INNER)
                            ->where("a.BalQty>0 and c.DCDate<='$asOn' and f.CompanyId=$companyId and c.CostCentreId=0 and c.DcOrCsm=1");
                        if($costcentreId <> 0)
                        {
                            $selQ9 -> where("  a.CostCentreId IN ($costcentreId) ");
                        }
                        $selQ9 -> group(new Expression("e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,a.CostCentreId,f.SEZProject,
                          b.FFactor,b.TFactor,j.StateId,h.StateId "));
                        $selQ9->combine($selQ8,'Union All');

                        $selQ10 = $sql -> select();
                        $selQ10 -> from(array('a' => 'MMS_DCTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=SUM(a.BalQty),Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 and (h.StateId=j.StateId))
                                then SUM(a.BalQty*Case When (b.FFactor>0 and b.TFactor>0) then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                                else a.GrossRate End) Else Sum(a.BalQty*Case when (b.FFactor>0 and b.TFactor>0) then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) else
                                a.QRate End) End ,a.CostCentreId   ")))
                            ->join(array('b' => 'MMS_DCGroupTrans'),'a.DCGroupId=b.DCGroupId and a.DCRegisterId=b.DCRegisterId',array(),$selQ10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'a.DCRegisterId=c.DCRegisterId',array(),$selQ10::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'),'a.ResourceId=d.ResourceId',array(),$selQ10::JOIN_INNER)
                            ->join(array('e' => 'Proj_ResourceGroup'),'d.ResourceGroupId=e.ResourceGroupId',array(),$selQ10::JOIN_LEFT)
                            ->join(array('f' => 'WF_OperationalCostCentre'),'a.CostCentreId=f.CostCentreId',array(),$selQ10::JOIN_INNER)
                            ->join(array('g' => 'Vendor_Master'),'c.VendorId=g.VendorId',array(),$selQ10::JOIN_INNER)
                            ->join(array('h' => 'WF_CityMaster'),'g.CityId=h.CityId',array(),$selQ10::JOIN_LEFT)
                            ->join(array('i' => 'WF_CostCentre'),'f.FACostCentreId=i.CostCentreId',array(),$selQ10::JOIN_INNER)
                            ->join(array('j' => 'WF_StateMaster'),'i.StateId=j.StateId',array(),$selQ10::JOIN_INNER)
                            ->where("a.BalQty>0 and c.DCDate<='$asOn' and f.CompanyId=$companyId and c.DcOrCsm=1");
                        if($costcentreId <> 0)
                        {
                            $selQ10 -> where("  c.CostCentreId IN ($costcentreId) ");
                        }
                        $selQ10 -> group(new Expression("e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,a.CostCentreId,f.SEZProject,
                          b.FFactor,b.TFactor,j.StateId,h.StateId "));
                        $selQ10->combine($selQ9,'Union All');


                        $selQ11 = $sql -> select();
                        $selQ11 -> from(array('a' => 'MMS_PVTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case when c.ThruDC='Y' Then SUM(a.ActualQty) else SUM(a.BillQty) end,
                               Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 And (h.StateId=j.StateId))
                             Then Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                             Else a.GrossRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                             Else a.GrossRate End) End Else Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0)
                             Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                             Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End,c.CostCentreId As CostCentreId   ")))
                            ->join(array('b' => 'MMS_PVGroupTrans'),'a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId',array(),$selQ11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$selQ11::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'),'a.ResourceId=d.ResourceId',array(),$selQ11::JOIN_INNER)
                            ->join(array('e' => 'Proj_ResourceGroup'),'d.ResourceGroupId=e.ResourceGroupId',array(),$selQ11::JOIN_LEFT)
                            ->join(array('f' => 'WF_OperationalCostCentre'),'c.CostCentreId=f.CostCentreId',array(),$selQ11::JOIN_INNER)
                            ->join(array('g' => 'Vendor_Master'),'c.VendorId=g.VendorId',array(),$selQ11::JOIN_INNER)
                            ->join(array('h' => 'WF_CityMaster'),'g.CityId=h.CityId',array(),$selQ11::JOIN_INNER)
                            ->join(array('i' => 'WF_CostCentre'),'f.FACostCentreId=i.CostCentreId',array(),$selQ11::JOIN_INNER)
                            ->join(array('j' => 'WF_StateMaster'),'i.StateId=j.StateId',array(),$selQ11::JOIN_INNER)
                            ->where("a.BillQty>0 and c.PVDate<='$asOn' and f.CompanyId=$companyId and c.ThruPO='Y' ");
                        if($costcentreId <> 0)
                        {
                            $selQ11 -> where("  c.CostCentreId IN ($costcentreId) ");
                        }
                        $selQ11 -> group (new Expression("e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,
                              c.CostCentreId,f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId "));
                        $selQ11->combine($selQ10,'Union All');


                        $selQ12 = $sql -> select();
                        $selQ12 -> from(array('a' => 'MMS_PVTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case when c.ThruDC='Y' Then SUM(a.ActualQty) else SUM(a.BillQty) end,
                             Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And f.SEZProject=0 And (h.StateId=j.StateId))
                             Then Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                             Else a.GrossRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                             Else a.GrossRate End) End Else Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0)
                             Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                             Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End,c.CostCentreId As CostCentreId    ")))
                            ->join(array('b' => 'MMS_PVGroupTrans'),'a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId',array(),$selQ12::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$selQ12::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'),'a.ResourceId=d.ResourceId',array(),$selQ12::JOIN_INNER)
                            ->join(array('e' => 'Proj_ResourceGroup'),'d.ResourceGroupId=e.ResourceGroupId',array(),$selQ12::JOIN_LEFT)
                            ->join(array('f' => 'WF_OperationalCostCentre'),'a.CostCentreId=f.CostCentreId',array(),$selQ12::JOIN_INNER)
                            ->join(array('g' => 'Vendor_Master'),'c.VendorId=g.VendorId',array(),$selQ12::JOIN_INNER)
                            ->join(array('h' => 'WF_CityMaster'),'g.CityId=h.CityId',array(),$selQ12::JOIN_INNER)
                            ->join(array('i' => 'WF_CostCentre'),'f.FACostCentreId=i.CostCentreId',array(),$selQ12::JOIN_INNER)
                            ->join(array('j' => 'WF_StateMaster'),'i.StateId=j.StateId',array(),$selQ12::JOIN_INNER)
                            ->where("a.BillQty>0 and c.PVDate<='$asOn' and c.CostCentreId=0 and f.CompanyId=$companyId and c.ThruPO='Y' ");
                        if($costcentreId <> 0)
                        {
                            $selQ12 -> where("  a.CostCentreId IN ($costcentreId) ");
                        }
                        $selQ12 -> group (new Expression("e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,
                              c.CostCentreId,f.SEZProject,b.FFactor,b.TFactor,h.StateId,j.StateId "));

                        $selQ12->combine($selQ11,'Union All');


                        $selQ13 = $sql -> select();
                        $selQ13 -> from(array('a' => 'MMS_PVTrans' ))
                            ->columns(array(new Expression("a.ResourceId,a.ItemId,Qty=Case when c.ThruDC='Y' then sum(a.ActualQty) Else sum(a.BillQty) end,
                                    Amount=Case When ((c.PurchaseTypeId=0 Or c.PurchaseTypeId=5) And h.SEZProject=0 And (j.StateId=l.StateId))
                             Then Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                             Else a.GrossRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0) Then isnull((a.GrossRate*b.TFactor),0)/nullif(b.FFactor,0)
                             Else a.GrossRate End) End Else Case When c.ThruDC='Y' Then SUM(a.ActualQty*Case When (b.FFactor>0 And b.TFactor>0)
                             Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) Else SUM(a.BillQty*Case When (b.FFactor>0 And b.TFactor>0)
                             Then isnull((a.QRate*b.TFactor),0)/nullif(b.FFactor,0) Else a.QRate End) End End,c.CostCentreId As CostCentreId  ")))
                            ->join(array('b' => 'MMS_PVGroupTrans'),'a.PVGroupId=b.PVGroupId and a.PVRegisterId=b.PVRegisterId',array(),$selQ13::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'a.PVRegisterId=c.PVRegisterId',array(),$selQ13::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'),'a.ResourceId=d.ResourceId',array(),$selQ13::JOIN_INNER)
                            ->join(array('e' => 'Proj_ResourceGroup'),'d.ResourceGroupId=e.ResourceGroupId',array(),$selQ13::JOIN_LEFT)
                            ->join(array('f' => 'MMS_DCTrans'),'a.DCTransId=f.DCTransId and a.DCRegisterId=f.DCRegisterId',array(),$selQ13::JOIN_INNER)
                            ->join(array('g' => 'MMS_DCRegister'),'f.DCRegisterId=g.DCRegisterId',array(),$selQ13::JOIN_INNER)
                            ->join(array('h' => 'WF_OperationalCostCentre'),'c.CostCentreId=h.CostCentreId',array(),$selQ13::JOIN_INNER)
                            ->join(array('i' => 'Vendor_Master'),'c.VendorId=i.VendorId',array(),$selQ13::JOIN_INNER)
                            ->join(array('j' => 'WF_CityMaster'),'i.CityId=j.CityId',array(),$selQ13::JOIN_LEFT)
                            ->join(array('k' => 'WF_CostCentre'),'h.FACostCentreId=k.CostCentreId',array(),$selQ13::JOIN_INNER)
                            ->join(array('l' => 'WF_StateMaster'),'k.StateId=l.StateId',array(),$selQ13::JOIN_INNER)
                            ->where("a.BillQty>0 and g.DCDate<='$asOn' and h.CompanyId=$companyId ");
                        if($costcentreId <> 0)
                        {
                            $selQ13 -> where ("  c.CostCentreId IN ($costcentreId) ");
                        }
                        $selQ13 -> group(new Expression("e.ResourceGroupId,a.ResourceId,a.ItemId,c.PurchaseTypeId,c.ThruDC,
                                   c.CostCentreId,h.SEZProject,b.FFactor,b.TFactor,j.StateId,l.StateId "));
                        $selQ13->combine($selQ12,'Union All');



                        $selQ14 = $sql -> select();
                        $selQ14 -> from(array('g1' => $selQ13))
                            ->columns(array(new Expression("g1.ResourceId,g1.ItemId,Qty=SUM(g1.Qty),
                                Rate=Case when CAST(isnull(isnull(SUM(g1.Amount),0)/nullif(sum(g1.Qty),0),0) As Decimal(18,6))<0 then 0 else
                                Cast(isnull(isnull(SUM(g1.Amount),0)/nullif(sum(g1.Qty),0),0) As Decimal(18,3)) End,
                                 Amount=Case when SUM(g1.Amount)<0 then 0 when sum(g1.Qty) <=0 then 0 else SUM(g1.Amount) end,g1.CostCentreId As CostCentreId ")));
                        $selQ14 -> group(new Expression("g1.ResourceId,g1.ItemId,g1.CostCentreId"));



                        $selQ15 = $sql -> select();
                        $selQ15 -> from(array('g2' => $selQ14))
                            ->columns(array(new Expression("oc.CostCentreName,rg.ResourceGroupId,rg.ResourceGroupName,g2.ResourceId,g2.ItemId,
                                Case when g2.itemid>0 then br.ItemCode else rv.Code end Code,case when g2.itemid>0  then br.BrandName else rv.ResourceName end Resource,
                                Case when g2.itemid>0 then u.UnitName else u1.UnitName End Unit,g2.Qty As Quantity,
                                 g2.Rate As Rate,g2.Amount,g2.CostCentreId As CostCentreId ")))
                            ->join(array('rv' => 'Proj_Resource'),'rv.ResourceId=g2.ResourceId',array(),$selQ15::JOIN_INNER)
                            ->join(array('rg' => 'Proj_ResourceGroup'),'rv.ResourceGroupId=rg.ResourceGroupId',array(),$selQ15::JOIN_LEFT )
                            ->join(array('br' => 'MMS_Brand'),'g2.ResourceId=br.ResourceId and g2.ItemId=br.BrandId',array(),$selQ15::JOIN_LEFT)
                            ->join(array('u' => 'Proj_UOM'),'br.UnitId=u.UnitId',array(),$selQ15::JOIN_LEFT)
                            ->join(array('u1' => 'Proj_UOM'),'rv.UnitId=u1.UnitId',array(),$selQ15::JOIN_LEFT)
                            ->join(array('oc' => 'WF_OperationalCostCentre'),'g2.CostCentreId=oc.CostCentreId',array(),$selQ15::JOIN_INNER)
                            ->where("rv.TypeId=2");




                        $statement = $sql->getSqlStringForSqlObject($selQ15);
                        $detStStmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($detStStmt));
                        return $response;
                        break;

                    case 'selectcc':
                        $companyId = $this->bsf->isNullCheck($this->params()->fromPost('companyId'), 'number');
                        $selCC = $sql -> select();
                        $selCC->from('WF_OperationalCostCentre')
                            ->columns(array(new Expression("CostCentreId As data,CostCentreName As value")))
                            ->where("CompanyId=$companyId");
                        $ccStatement = $sql->getSqlStringForSqlObject($selCC);
                        $resp['resultCC'] = $dbAdapter->query($ccStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($resp['resultCC']));
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
            $select = $sql->select();
            $select->from(array('a' => 'WF_CompanyMaster'))
                ->columns(array('CompanyId', 'CompanyName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_company = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}