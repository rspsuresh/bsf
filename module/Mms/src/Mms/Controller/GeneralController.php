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

class GeneralController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function fastMovingAction(){
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
				//Write your Ajax post code here
                $postParams = $request->getPost();

			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}
            $CostCentreId = $this->params()->fromRoute('CostCentre');
            $WareHouseId = $this->params()->fromRoute('WareHouse');
            $this->_view->CostCentreId=$CostCentreId;
            $this->_view->WareHouseId=$WareHouseId;
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
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where(array("CostCentreId"=>$CostCentreId));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->costcenter = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $WHSelect = $sql->select();
            $WHSelect->from('MMS_WareHouse')
                ->columns(array('WareHouseId', 'WareHouseName'))
                ->where(array("ParentId = 0"));
            $WHStatement = $sql->getSqlStringForSqlObject($WHSelect);
            $this->_view->arr_WareHouse = $dbAdapter->query( $WHStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $datedet = array();
            if($fromDate<=$toDate) {
                $select = $sql->select();
                $select->from("")
                    ->columns(array('Monthcount'=> new Expression("DATEDIFF(MONTH,'$fromDate','$toDate') + 1")));
                $statement = $statement = $sql->getSqlStringForSqlObject($select);
                $mothCOuntList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $cont=$mothCOuntList[0]['Monthcount'];
                //echo $cont;

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
            if($WareHouseId == ""){
                $WareHouseId =0;
            }
            if($CostCentreId == ""){
                $CostCentreId =0;
            }

            if($WareHouseId > 0){
                $select2 = $sql->select();
                $select2->from(array('A' => 'mms_DCRegister'))
                    ->columns(array(
                        'SerialNumber' => new Expression('Row_number() over (order by  H.CostCentreName Asc)'),
                        'ResourceId' => new Expression('B.ResourceId'),
                        'ItemId' => new Expression('B.ItemId'),
                        'CostCentreId' => new Expression('A.CostCentreId'),
                        'Code' => new Expression('Case When B.ItemId>0 Then D.ItemCode Else C.Code End'),
                        'Resource' => new Expression('Case When B.ItemId>0 Then D.BrandName Else C.ResourceName End'),
                        'Unit' => new Expression('Case When B.ItemId>0 Then E1.UnitName Else E.UnitName End'),
                        'CostCentre' => new Expression('H.CostCentreName')
                    ))
                    ->join(array('B' => 'mms_DCGroupTrans'), " A.DCRegisterId=B.DCRegisterId", array(), $select2::JOIN_INNER)
                    ->join(array('F' => 'mms_DCWareHouseTrans'), 'B.DCGroupId=F.DCGroupId', array(), $select2::JOIN_INNER)
                    ->join(array('C' => 'proj_Resource'), ' B.ResourceId=C.ResourceId', array(), $select2::JOIN_INNER)
                    ->join(array('D' => 'mms_Brand'), 'B.ItemId=D.BrandId And B.ResourceId=D.ResourceId', array(), $select2::JOIN_LEFT)
                    ->join(array('E' => 'proj_UOM'), 'B.UnitId=E.UnitId', array(), $select2::JOIN_LEFT)
                    ->join(array('E1' => 'proj_UOM'), 'B.UnitId=E1.UnitId', array(), $select2::JOIN_LEFT)
                    ->join(array('H' => 'wf_OperationalCostCentre'), 'A.CostCentreId=H.CostCentreId', array(), $select2::JOIN_INNER)
                    ->join(array('CW' => 'mms_CCWareHouse'), 'H.CostCentreId=CW.CostCentreId', array("WareHouseId"), $select2::JOIN_INNER)
                    ->join(array('I' => 'MMS_WareHouse'), 'CW.WareHouseId=I.WareHouseId', array("WareHouseName"), $select2::JOIN_INNER);
                $select2->where(array("CW.WareHouseId IN ($WareHouseId)"));
                $select2->group(new Expression("CW.WareHouseId,A.CostCentreId,B.ResourceId,B.ItemId,C.Code ,C.ResourceName ,
                E.UnitName,E1.UnitName, H.CostCentreName ,I.WareHouseName,D.ItemCode,D.BrandName"));

                $select3 = $sql->select();
                $select3->from(array('G' => $select2))
                    ->columns(array("SerialNumber", "ResourceId", "ItemId", "Code", "Resource", "Unit"))
                    ->order("G.CostCentre CostCentre");
                $statement = $sql->getSqlStringForSqlObject($select3);
                $Register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }else {
                $select4 = $sql->select();
                $select4->from(array('A' => 'mms_DCRegister'))
                    ->columns(array(
                        'SerialNumber' => new Expression('Row_number() over (order by  H.CostCentreName Asc)'),
                        'ResourceId' => new Expression('B.ResourceId'),
                        'ItemId' => new Expression('B.ItemId'),
                        'CostCentreId' => new Expression('A.CostCentreId'),
                        'Code' => new Expression('Case When B.ItemId>0 Then D.ItemCode Else C.Code End'),
                        'Resource' => new Expression('Case When B.ItemId>0 Then D.BrandName Else C.ResourceName End'),
                        'Unit' => new Expression('Case When B.ItemId>0 Then E1.UnitName Else E.UnitName End'),
                        'CostCentre' => new Expression('H.CostCentreName')
                    ))
                    ->join(array('B' => 'mms_DCGroupTrans'), " A.DCRegisterId=B.DCRegisterId", array(), $select4::JOIN_INNER)
                    ->join(array('C' => 'proj_Resource'), ' B.ResourceId=C.ResourceId', array(), $select4::JOIN_INNER)
                    ->join(array('D' => 'mms_Brand'), 'B.ItemId=D.BrandId And B.ResourceId=D.ResourceId', array(), $select4::JOIN_LEFT)
                    ->join(array('E' => 'proj_UOM'), 'B.UnitId=E.UnitId', array(), $select4::JOIN_LEFT)
                    ->join(array('E1' => 'proj_UOM'), 'B.UnitId=E1.UnitId', array(), $select4::JOIN_LEFT)
                    ->join(array('H' => 'wf_OperationalCostCentre'), 'A.CostCentreId=H.CostCentreId', array(), $select4::JOIN_INNER);
                $select4->where(array(" H.CostCentreId IN ($CostCentreId)"));
                $select4->group(new Expression("A.CostCentreId,B.ResourceId,B.ItemId,C.Code ,C.ResourceName ,E.UnitName,
            E1.UnitName, H.CostCentreName ,D.ItemCode,D.BrandName"));

                $select5 = $sql->select();
                $select5->from(array('G' => $select4))
                    ->columns(array("SerialNumber", "ResourceId", "ItemId", "Code", "Resource", "Unit"))
                    ->order("G.CostCentre CostCentre");
                $statement1 = $sql->getSqlStringForSqlObject($select5);
                $Register = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $arrUnitLists= array();
            $arrUnitLists1= array();
            $i=0;
            $ParId=0;

            foreach($Register as &$projLists) {
                $dumArr=array();
                $dumArr = array(
                    'ResourceId' => $projLists['ResourceId'],
                    'SerialNumber' => $projLists['SerialNumber'],
                    'ItemId' => $projLists['ItemId'],
                    'Code' => $projLists['Code'],
                    'Resource' => $projLists['Resource'],
                    'Unit' => $projLists['Unit'],
                    'AvgQty' => 0
                );

                $ResourceId=$projLists['ResourceId'];
                $ItemId=$projLists['ItemId'];
                $Res[] = array();
                $dum=array();
                $dum= array(
                    'ResourceId' => $ResourceId
                );
                $Res[] =$dum;
                $total=0;
                $avg=0;
                foreach($datedet as &$datedets) {
                    $Month = $datedets['Month'];
                    $Year = $datedets['Year'];
                    if($WareHouseId > 0){
                        $sIds = "G.WareHouseId = $WareHouseId";
                    }else{
                    $sIds = "A.CostCentreId = $CostCentreId";
                    }
                    $select7 = $sql->select();
                    $select7->from(array('A' => 'mms_DCRegister'))
                        ->columns(array(
                            'ResourceId' => new Expression('B.ResourceId'),
                            'ItemId' => new Expression('B.ItemId'),
                            'Qty' => new Expression('SUM(B.AcceptQty)'),
                            'CostCentreId' => new Expression('A.CostCentreId')
                        ))
                        ->join(array('B' => 'mms_DCGroupTrans'), " A.DCRegisterId=B.DCRegisterId", array(), $select7::JOIN_INNER)
                        ->join(array('F' => 'mms_DCWareHouseTrans'), 'B.DCGroupId=F.DCGroupId', array(), $select7::JOIN_INNER)
                        ->join(array('G' => 'mms_CCWareHouse'), ' A.CostCentreId=G.CostCentreId', array(), $select7::JOIN_INNER)
                        ->join(array('I' => 'mms_WareHouse'), 'G.WareHouseId=I.WareHouseId', array(), $select7::JOIN_INNER);
                    $select7->where("MONTH(A.DCDate)=$Month and Year(A.DCDate)=$Year  ");
                    $select7->where(array("$sIds Group By B.ResourceId,B.ItemId,G.WareHouseId,A.CostCentreId"));
                    $statement2 = $sql->getSqlStringForSqlObject($select7);
                    $Register1 = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $dumArr['Qty_'.$Month.$Year] = $Register1['Qty'];
                    $total=+$Register1['Qty'];

                }
                $avg=$total/$cont;
                $dumArr['AvgQty'] = $avg;

                $arrUnitLists[] =$dumArr;
            }
            $this->_view->arr_datedet =$datedet;
            $this->_view->arrUnitLists =$arrUnitLists;

           /* echo '<pre>';
			print_r($arrUnitLists);
			echo '</pre>';
			die;*/
//			Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}


	public function abcAnalysisReportAction(){
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
				$WareHouseId =$this->params()->fromPost('WareHouse'); 
				if($WareHouseId == ""){
					$WareHouseId =0;
				}
				$fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
				if($fromDat == ''){
				$fromDat =  0;
				}
				if($fromDat == 0){
					$fromDate =  0;
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
					
					$select = $sql->select();
					$select->from(array("S" => "MMS_Stock"))
						->columns(array(new Expression("Distinct S.ResourceId,S.ItemId, S.CostCentreId,S.OpeningStock Qty,0 AvgRate,C.WareHouseId  as WareHouseId")))
						->join(array('B' => 'MMS_StockTrans'), 'S.StockId=B.StockId', array(), $select::JOIN_INNER)
						->join(array('C' => 'MMS_WareHouseDetails'), 'B.WareHouseId=C.TransId', array(), $select::JOIN_INNER)
						->where("S.OpeningStock > 0  And C.WareHouseId=$WareHouseId ");
						
					$select1 = $sql->select();
					$select1->from(array("A" => "MMS_PVRegister"))
						->columns(array(new Expression("B.ResourceId,B.ItemId,A.CostCentreId,B.BillQty Qty,0 AvgRate,E.WareHouseId as WareHouseId")))
						->join(array('B' => 'MMS_PVTrans'), 'A.PVRegisterId=B.PVRegisterId', array(), $select1::JOIN_INNER)
						->join(array('C' => 'MMS_PVGroupTrans'), 'B.PVGroupId=C.PVGroupId And B.PVRegisterId=C.PVRegisterId', array(), $select1::JOIN_INNER)
						->join(array('D' => 'MMS_PVWHTrans'), 'C.PVGroupId=D.PVGroupId', array(), $select1::JOIN_INNER)
						->join(array('E' => 'MMS_WareHouseDetails'), 'D.WareHouseId=E.TransId', array(), $select1::JOIN_INNER)
						->where("B.DCRegisterId=0 And B.BillQty > 0 And A.ThruDC='N' And A.PVDate <= '$fromDate' And E.WareHouseId=$WareHouseId");
					$select1->combine($select,'Union ALL');
					
					$select2 = $sql->select();
					$select2->from(array("A" => "MMS_DCRegister"))
						->columns(array(new Expression("B.ResourceId,B.ItemId,A.CostCentreId,B.AcceptQty Qty,0 AvgRate,E.WareHouseId as WareHouseId")))
						->join(array('B' => 'MMS_DCTrans'), 'A.DCRegisterId=B.DCRegisterId', array(), $select2::JOIN_INNER)
						->join(array('C' => 'MMS_DCGroupTrans'), 'B.DCGroupId=C.DCGroupId And B.DCRegisterId=C.DCRegisterId', array(), $select2::JOIN_INNER)
						->join(array('D' => 'MMS_DCWareHouseTrans'), 'C.DCGroupId=D.DCGroupId', array(), $select2::JOIN_INNER)
						->join(array('E' => 'MMS_WareHouseDetails'), 'D.WareHouseId=E.TransId', array(), $select2::JOIN_INNER)
						->where("B.AcceptQty > 0  And DcOrCSM=1 And A.DCDate <= '$fromDate'  And E.WareHouseId=$WareHouseId");
					$select2->combine($select1,'Union ALL');
					
					$select3 = $sql->select();
					$select3->from(array("A" => "MMS_TransferRegister"))
						->columns(array(new Expression("B.ResourceId,B.ItemId, A.FromCostCentreId,-B.TransferQty Qty,0 AvgRate,D.WareHouseId as WareHouseId")))
						->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select3::JOIN_INNER)
						->join(array('C' => 'MMS_TransferWareHouseTrans'), 'B.TransferTransId=C.TransferTransId', array(), $select3::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId =D.TransId', array(), $select3::JOIN_INNER)
						->where("B.TransferQty > 0 And A.TVDate <= '$fromDate' And D.WareHouseId=$WareHouseId");
					$select3->combine($select2,'Union ALL');
					
					$select4 = $sql->select();
					$select4->from(array("A" => "MMS_TransferRegister"))
						->columns(array(new Expression("B.ResourceId,B.ItemId,  A.ToCostCentreId,B.TransferQty Qty,0 AvgRate,D.WareHouseId as WareHouseId")))
						->join(array('B' => 'MMS_TransferTrans'), 'A.TVRegisterId = B.TransferRegisterId', array(), $select4::JOIN_INNER)
						->join(array('C' => 'MMS_TransferWareHouseTrans'), 'B.TransferTransId=C.TransferTransId', array(), $select4::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select4::JOIN_INNER)
						->where("B.RecdQty > 0 And A.TVDate <= '$fromDate' And D.WareHouseId=$WareHouseId");
					$select4->combine($select3,'Union ALL');
					
					$select5 = $sql->select();
					$select5->from(array("A" => "MMS_PRRegister"))
						->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId, -B.ReturnQty Qty,0 AvgRate,D.WareHouseId as WareHouseId")))
						->join(array('B' => 'MMS_PRTrans'), 'A.PRRegisterId = B.PRRegisterId', array(), $select5::JOIN_INNER)
						->join(array('C' => 'MMS_PRWareHouseTrans'), 'B.PRTransId=C.PRTransId', array(), $select5::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select5::JOIN_INNER)
						->where("B.ReturnQty > 0 And A.PRDate <= '$fromDate' And D.WareHouseId=$WareHouseId");
					$select5->combine($select4,'Union ALL');
					
					$select6 = $sql->select();
					$select6->from(array("A" => "MMS_IssueRegister"))
						->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,-B.IssueQty Qty,0 AvgRate,D.WareHouseId as WareHouseId")))
						->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select6::JOIN_INNER)
						->join(array('C' => 'MMS_IssueWareHouseTrans'), 'B.IssueTransId=C.IssueTransId', array(), $select6::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select6::JOIN_INNER)
						->where("B.IssueOrReturn='I' And B.IssueQty > 0 And A.OwnOrCSM=0  ANd A.IssueDate <= '$fromDate' And D.WareHouseId=$WareHouseId");
					$select6->combine($select5,'Union ALL');
					
					$select7 = $sql->select();
					$select7->from(array("A" => "MMS_IssueRegister"))
						->columns(array(new Expression("B.ResourceId,B.ItemId, A.CostCentreId,B.IssueQty Qty,0 AvgRate,D.WareHouseId as WareHouseId")))
						->join(array('B' => 'MMS_IssueTrans'), 'A.IssueRegisterId = B.IssueRegisterId', array(), $select7::JOIN_INNER)
						->join(array('C' => 'MMS_IssueWareHouseTrans'), 'B.IssueTransId=C.IssueTransId', array(), $select7::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select7::JOIN_INNER)
						->where("B.IssueOrReturn='R' And B.IssueQty > 0  And A.OwnOrCSM=0 And A.IssueDate <= '$fromDate' And D.WareHouseId=$WareHouseId");
					$select7->combine($select6,'Union ALL');
					
					$select8 = $sql->select();
					$select8->from(array("A" => $select7))
							->columns(array(
							'ResourceId'=>new Expression('A.ResourceId'),
							'ItemId'=> new Expression('A.ItemId'),
							'Qty'=> new Expression('SUM(Qty)'),
							'CostCentreId'=> new Expression('A.CostCentreId'),
							'WareHouseId'=> new Expression('A.WareHouseId')
							));
					$select8->group(new Expression("A.ResourceId,A.CostCentreId,A.ItemId,A.WareHouseId"));	
					
					$select9 = $sql->select();
					$select9->from(array("A" => 'MMS_PVTrans'))
							->columns(array(
							'CostCentreId'=>new Expression('B.CostCentreId'),
							'ResourceId'=> new Expression('A.ResourceId'),
							'ItemId'=> new Expression('A.ItemId'),
							'WareHouseId'=> new Expression('E.WareHouseId'),
							'Qty'=> new Expression('SUM(A.BillQty)'),
							'Amount'=> new Expression('SUM(A.QAmount)')
							))
						->join(array('B' => 'MMS_PVRegister'), 'A.PVRegisterId=B.PVRegisterId', array(), $select9::JOIN_INNER)
						->join(array('C' => 'MMS_PVGroupTrans'), 'A.PVGroupId=C.PVGroupId And A.PVRegisterId=C.PVRegisterId', array(), $select9::JOIN_INNER)
						->join(array('D' => 'MMS_PVWHTrans'), 'C.PVGroupId=D.PVGroupId', array(), $select9::JOIN_INNER)
						->join(array('E' => 'MMS_WareHouseDetails'), 'D.WareHouseId=E.TransId ', array(), $select9::JOIN_INNER)
						->where("A.DCRegisterId=0 And A.BillQty>0   And B.ThruDC='N'  And B.PVDate <= '$fromDate' AND E.WareHouseId=$WareHouseId GROUP BY B.CostCentreId,A.ResourceId,A.ItemId,E.WareHouseId");
					
					$select10 = $sql->select();
					$select10->from(array("A" => "MMS_DCTrans"))
						->columns(array(
							'CostCentreId'=>new Expression('B.CostCentreId'),
							'ResourceId'=> new Expression('A.ResourceId'),
							'ItemId'=> new Expression('A.ItemId'),
							'WareHouseId'=> new Expression('E.WareHouseId'),
							'Qty'=> new Expression('SUM(A.AcceptQty)'),
							'Amount'=> new Expression('SUM(A.QAmount)')
							))
						->join(array('B' => 'MMS_DCRegister'), 'A.DCRegisterId=B.DCRegisterId', array(), $select10::JOIN_INNER)
						->join(array('C' => 'MMS_DCGroupTrans'), 'A.DCGroupId=C.DCGroupId And A.DCRegisterId=C.DCRegisterId', array(), $select10::JOIN_INNER)
						->join(array('D' => 'MMS_DCWareHouseTrans'), 'C.DCGroupId=D.DCGroupId', array(), $select10::JOIN_INNER)
						->join(array('E' => 'MMS_WareHouseDetails'), 'D.WareHouseId=E.TransId', array(), $select10::JOIN_INNER)
						->where("A.AcceptQty>0   And DcOrCSM=1 And B.DCDate <= '$fromDate' And E.WareHouseId=$WareHouseId GROUP BY B.CostCentreId,A.ResourceId,A.ItemId,E.WareHouseId ");
					$select10->combine($select9,'Union ALL');
					
					$select11 = $sql->select();
					$select11->from(array("A" => "MMS_TransferTrans"))
						->columns(array(
							'CostCentreId'=>new Expression('A.FCostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'Qty'=> new Expression('SUM(-A.TransferQty)'),
							'Amount'=> new Expression('SUM(-QAmount)')
							))
						->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select11::JOIN_INNER)
						->join(array('C' => 'MMS_TransferWareHouseTrans'), 'A.TransferTransId=C.TransferTransId', array(), $select11::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select11::JOIN_INNER)
						->where("A.TransferQty>0  And B.TVDate <= '$fromDate'  And D.WareHouseId=$WareHouseId GROUP BY FCostCentreId,ResourceId,ItemId ,D.WareHouseId");
					$select11->combine($select10,'Union ALL');
					
					$select12 = $sql->select();
					$select12->from(array("A" => "MMS_TransferTrans"))
						->columns(array(
							'CostCentreId'=>new Expression('A.TCostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'Qty'=> new Expression('SUM(A.TransferQty)'),
							'Amount'=> new Expression('SUM(QAmount)')
							))
						->join(array('B' => 'MMS_TransferRegister'), 'B.TVRegisterId = A.TransferRegisterId', array(), $select12::JOIN_INNER)
						->join(array('C' => 'MMS_TransferWareHouseTrans'), 'A.TransferTransId=C.TransferTransId', array(), $select12::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select12::JOIN_INNER)
						->where("A.TransferQty>0  And B.TVDate <= '$fromDate'  And D.WareHouseId=$WareHouseId GROUP BY TCostCentreId,ResourceId,ItemId,D.WareHouseId ");
					$select12->combine($select11,'Union ALL');
					
					$select13 = $sql->select();
					$select13->from(array("A" => "MMS_PRTrans"))
						->columns(array(
							'CostCentreId'=>new Expression('B.CostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'Qty'=> new Expression('SUM(-A.ReturnQty)'),
							'Amount'=> new Expression('SUM(-QAmount)')
							))
						->join(array('B' => 'MMS_PRRegister'), 'B.PRRegisterId = A.PRRegisterId', array(), $select13::JOIN_INNER)
						->join(array('C' => 'MMS_PRWareHouseTrans'), 'A.PRTransId=C.PRTransId', array(), $select13::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select13::JOIN_INNER)
						->where("A.ReturnQty>0  And B.PRDate <= '$fromDate'  And D.TransId=$WareHouseId GROUP BY B.CostCentreId, ResourceId,ItemId,D.WareHouseId");
					$select13->combine($select12,'Union ALL');
					
					$select14 = $sql->select();
					$select14->from(array("A" => "MMS_IssueTrans"))
						->columns(array(
							'CostCentreId'=>new Expression('B.CostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'Qty'=> new Expression('SUM(-A.IssueQty)'),
							'Amount'=> new Expression('SUM(-IssueAmount)')
							))
						->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select14::JOIN_INNER)
						->join(array('C' => 'MMS_IssueWareHouseTrans'), 'A.IssueTransId=C.IssueTransId', array(), $select14::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId ', array(), $select14::JOIN_INNER)
						->where("A.IssueQty>0   And A.IssueOrReturn='I'  And B.IssueDate <= '$fromDate'  And D.WareHouseId=$WareHouseId GROUP BY B.CostCentreId,ResourceId,ItemId,D.WareHouseId");
					$select14->combine($select13,'Union ALL');
					
					$select15 = $sql->select();
					$select15->from(array("A" => "MMS_IssueTrans"))
						->columns(array(
							'CostCentreId'=>new Expression('B.CostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('D.WareHouseId'),
							'Qty'=> new Expression('SUM(A.IssueQty)'),
							'Amount'=> new Expression('SUM(IssueAmount)')
							))
						->join(array('B' => 'MMS_IssueRegister'), 'B.IssueRegisterId = A.IssueRegisterId', array(), $select15::JOIN_INNER)
						->join(array('C' => 'MMS_IssueWareHouseTrans'), 'A.IssueTransId=C.IssueTransId', array(), $select15::JOIN_INNER)
						->join(array('D' => 'MMS_WareHouseDetails'), 'C.WareHouseId=D.TransId', array(), $select15::JOIN_INNER)
						->where("A.IssueQty>0    And A.IssueOrReturn='R'  And B.IssueDate <= '$fromDate'  And D.WareHouseId=$WareHouseId GROUP BY B.CostCentreId, ResourceId,ItemId ,D.WareHouseId");
					$select15->combine($select14,'Union ALL');
					
					$select16 = $sql->select();
					$select16->from(array("A" => "MMS_Stock"))
						->columns(array(
							'CostCentreId'=>new Expression('Distinct A.CostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('C.WareHouseid'),
							'Qty'=> new Expression('A.OpeningStock'),
							'Amount'=> new Expression('A.OpeningStock*ORate')
							))
						->join(array('B' => 'MMS_StockTrans'), 'A.StockId=B.StockId', array(), $select16::JOIN_INNER)
						->join(array('C' => 'MMS_WareHouseDetails'), 'B.WareHouseId=C.Transid', array(), $select16::JOIN_INNER)
						->where("A.OpeningStock>0 And C.WareHouseid=$WareHouseId");
					$select16->combine($select15,'Union ALL');
					
					$select17 = $sql->select();
					$select17->from(array("A" => $select16))
							->columns(array(
							'CostCentreId'=>new Expression('CostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('WareHouseId'),
							'Qty'=> new Expression('SUM(Qty)'),
							'Amount'=> new Expression('SUM(Amount)')
							));
					$select17->group(new Expression("CostCentreId, ResourceId,ItemId,WareHouseId"));
					
					$select18 = $sql->select();
					$select18->from(array("A" => $select17))
							->columns(array(
							'CostCentreId'=>new Expression('CostCentreId'),
							'ResourceId'=> new Expression('ResourceId'),
							'ItemId'=> new Expression('ItemId'),
							'WareHouseId'=> new Expression('WareHouseId'),
							'AvgRate'=> new Expression('(isnull(Amount,0)/nullif(Qty,0))')));	

					$select19 = $sql->select();
					$select19->from(array("A" => $select8))
							->columns(array(
							'ResourceId'=>new Expression('Distinct A.ResourceId'),
							'ItemId'=> new Expression('A.ItemId'),
							'WareHouseId'=> new Expression('A.WareHouseId'),
							'ResourceGroup'=> new Expression('RG.ResourceGroupName'),
							'Code'=> new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End'),
							'Resource'=> new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End'),
							'Unit'=> new Expression('D.UnitName'),
							'Net'=> new Expression('CAST(ISNULL(Qty,0) AS Decimal(18,3))'),
							'AvgRate'=> new Expression('CAST(ISNULL(AvgRate,0) AS Decimal(18,3))')))
						->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $select19::JOIN_INNER)
						->join(array('D' => 'Proj_UOM'), 'B.UnitId=D.UnitId', array(), $select19::JOIN_LEFT)
						->join(array('C' => $select18), 'A.ResourceId = C.ResourceId And A.ItemId = C.ItemId And A.CostCentreId = C.CostCentreId  And A.WareHouseId = C.WareHouseId', array(), $select19::JOIN_LEFT)
						->join(array('BR' => 'MMS_Brand'), 'A.ItemId =BR.BrandId And A.ResourceId=BR.ResourceId', array(), $select19::JOIN_LEFT)
						->join(array('SK' => 'MMS_Stock'), 'A.ItemId=SK.ItemId And A.ResourceId=SK.ResourceId And A.CostCentreId=SK.CostCentreId', array(), $select19::JOIN_LEFT)
						->join(array('RV' => 'Proj_Resource'), 'A.ResourceId=RV.ResourceId', array(), $select19::JOIN_INNER)
						->join(array('OC' => 'WF_OperationalCostCentre'), 'A.CostCentreId=OC.CostCentreId', array(), $select19::JOIN_INNER)
						->join(array('RG' => 'Proj_ResourceGroup'), 'RV.ResourceGroupId=RG.ResourceGroupId', array(), $select19::JOIN_INNER)
						->join(array('CW' => 'MMS_CCWareHouse'), 'OC.CostCentreId=CW.CostCentreId', array(), $select19::JOIN_INNER)
						->join(array('WH' => 'MMS_WareHouse'), 'CW.WareHouseId=WH.WareHouseId And A.WareHouseId=WH.WareHouseId', array(), $select19::JOIN_INNER)
					->where("B.TypeId IN (2,3) And WH.WareHouseId=$WareHouseId");
					
					$select20 = $sql->select();
					$select20->from(array("G" => $select19))
							->columns(array(
							'ResourceId'=>new Expression('G.ResourceId'),
							'ItemId'=> new Expression('G.ItemId'),
							'ResourceGroup'=> new Expression('G.ResourceGroup'),
							'Code'=> new Expression('G.Code'),
							'Resource'=> new Expression('G.Resource'),
							'Unit'=> new Expression('G.Unit'),
							'Rate'=> new Expression('ISNULL(SUM(Net),0) Qty,ISNULL(SUM(G.AvgRate),0)'),
							'Amount'=> new Expression('CAST((ISNULL(SUM(Net),0) * ISNULL(SUM(AvgRate),0))  As Decimal(18,3))'),
							'Group'=> new Expression("Case When ISNULL(SUM(G.AvgRate),0) >= (Select AFrom From MMS_ABCAnalysisMaster) Then 'A' When ISNULL(SUM(G.AvgRate),0) >= (Select BFrom From MMS_ABCAnalysisMaster) Then 'B' When ISNULL(SUM(G.AvgRate),0) >= (Select CFrom From MMS_ABCAnalysisMaster) Then 'C' End")));
					$select20->group(new Expression("G.ResourceId,G.ItemId,G.ResourceGroup,G.Code,G.Resource,G.Unit  Having SUM(G.AvgRate)>0"));
					
					$statement= $sql->getSqlStringForSqlObject($select20);
					$abcanalysisreport = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

					$this->_view->setTerminal(true);
					$response = $this->getResponse()->setContent(json_encode($abcanalysisreport));
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
            $projSelect->from('MMS_WareHouse')
                ->columns(array('WareHouseId', 'WareHouseName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect); 
            $this->_view->arr_warehouse = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
}