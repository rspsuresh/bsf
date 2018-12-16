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

	public function pendingpoAction(){
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
                        $select = $sql->select();
                        $select->from(array('a' => 'MMS_POTrans'))
                            ->columns(array(
                                'PORegisterId' => new Expression('c.PORegisterId'),
                                'POTransId' => new Expression('a.POTransId'),
                                'PODate' => new Expression('Convert(Varchar(10),C.PODate,103)'),
                                'PONo' => new Expression('PONo'),
                                'CPONo' => new Expression('c.CPONo'),
                                'ReqNo' => new Expression('c.ReqNo'),
                                'VendorName' => new Expression('v.VendorName'),
                                'CostCentre' => new Expression('cc.CostCentreName'),
                                'ResourceId' => new Expression('a1.ResourceId'),
                                'ResourceGroup' => new Expression('rg.ResourceGroupName'),
                                'Resource' => new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End Code, Case When A.ItemId <> 0 Then BR.BrandName Else B.ResourceName End'),
                                'Specification' => new Expression('a.Description'),
                                'Unit' => new Expression('u.UnitName'),
                                'POQty' => new Expression('a1.POQty'),
                                'Rate' => new Expression('Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End'),
                                'POAmount' => new Expression('(A1.POQty*Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End)'),
                                'MinQty' => new Expression('a1.AcceptQty'),
                                'CancelQty' => new Expression('a1.CancelQty'),
                                'BalQty' => new Expression('a1.BalQty'),
                                'MinAmount' => new Expression('(A1.AcceptQty*Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End)'),
                                'BillQty' => new Expression('a1.BillQty'),
                                'PendingNoOfDays' => new Expression('DATEDIFF(Day,C.PODate,getdate())'),
                                'Amount' => new Expression('(A1.BalQty*Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End)')
                            ))
                            ->join(array('a1' => 'MMS_POProjTrans'), 'A.PoTransId=A1.POTransId', array(), $select::JOIN_INNER)
                            ->join(array('b' => 'Proj_Resource'), ' A.ResourceId=B.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'), 'A.PORegisterId=C.PORegisterId', array(), $select::JOIN_INNER)
                            ->join(array('cc' => 'WF_OperationalCostCentre'), 'A1.CostCentreId= CC.CostCentreId', array(), $select::JOIN_INNER)
                            ->join(array('v' => 'Vendor_Master'), ' V.VendorId=C.VendorId', array(), $select::JOIN_INNER)
                            ->join(array('br' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $select::JOIN_LEFT)
                            ->join(array('cm' => 'WF_CompanyMaster'), 'CC.CompanyId=CM.CompanyId', array(), $select::JOIN_INNER)
                            ->join(array('rg' => 'Proj_ResourceGroup'), 'B.ResourceGroupId=RG.ResourceGroupId', array(), $select::JOIN_INNER)
                            ->join(array('u' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $select::JOIN_LEFT)
                            ->where("A1.BalQty>0 AND C.PODate BETWEEN ('$fromDate') and ('$toDate') And C.Approve='Y' AND C.LivePO=1
                         And A.ShortClose=0  and A1.CostCentreId IN ($CostCentreId)  Order By C.PODate Asc");
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($register));
                        return $response;
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
            }
        }
    public function pendingHistoryAction(){
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

                if($fromDat == 0){
                    $fromDatdetae =  0;
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
                        $subselect = $sql->select();
                        $subselect->from(array('A' => 'MMS_POTrans'))
                            ->columns(array(
                                'PORegisterId' => new Expression('A.PORegisterId'),
                                'ResourceId' => new Expression('A.ResourceId'),
                                'PODate' => new Expression('Convert(Varchar(10),E.PODate,103)'),
                                'ReqDate' => new Expression('Convert(Varchar(10),E.ReqDate,103)'),
                                'MinDate' => new Expression('Convert(Varchar(10),D.DCDate,103)'),
                                'ItemId' => new Expression('A.ItemId'),
                                'CostCentreId' => new Expression('A1.CostCentreId'),
                                'CostCentre' => new Expression('CC.CostCentreName'),
                                'PONo' => new Expression('E.PONo'),
                                'CPONo' => new Expression('E.CPONo'),
                                'ReqNo' => new Expression('E.ReqNo'),
                                'VendorName' => new Expression('V.VendorName'),
                                'ResourceGroup' => new Expression('RG.ResourceGroupName'),
                                'Resource' => new Expression('R.ResourceName'),
                                'Specification' => new Expression('A.Description'),
                                'Unit' => new Expression('u.UnitName'),
                                'POQty' => new Expression('CAST(A1.POQty As Decimal(18,6))'),
                                'MinNo' => new Expression('D.DCNo'),
                                'CMinNo' => new Expression('D.CDCNo'),
                                'AcceptQty' => new Expression('CAST(C.DCQty As Decimal(18,6)) As MinQty,CAST(C.AcceptQty As Decimal(18,6))'),
                                'RejectQty' => new Expression('CAST(C.RejectQty As Decimal(18,6))'),
                                'Rejection(%)' => new Expression('Case When (C.DCQty>0 And C.RejectQty>0) Then CAST(((C.RejectQty/C.DCQty)*100) As Decimal(18,6)) Else Cast(0 As Decimal(18,6)) End'),
                                'Rate' => new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST(isnull((A.QRate * A.TFactor),0)/nullif(A.FFactor,0) As Decimal(18,2)) Else CAST(A.QRate As Decimal(18,2)) End'),
                                'Amount' => new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST((A1.POQty * isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0)) As Decimal(18,2))  Else CAST(A.QAmount As Decimal(18,2)) End'),
                                'MinAmount' => new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then CAST((C.AcceptQty* isnull((C.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) As Decimal(18,2)) Else CAST(C.QAmount As Decimal(18,2)) End'),
                                'DelayInDelivery' => new Expression('Case When DATEDIFF(dd,E.ReqDate,D.DCDate)>0 Then DATEDIFF(dd,E.ReqDate,D.DCDate) Else 0 End')
                            ))
                            ->join(array('A1' => 'MMS_POProjTrans'), 'A.PoTransId=A1.POTransId', array(), $subselect::JOIN_INNER)
                            ->join(array('B' => 'MMS_IPDTrans'), 'A.POTransId=B.POTransId', array(), $subselect::JOIN_INNER)
                            ->join(array('B1' => 'MMS_IPDProjTrans'), 'B.IPDTransId=B1.IPDTransId', array(), $subselect::JOIN_INNER)
                            ->join(array('C' => 'MMS_DCTrans'), 'B.DCTransId=C.DCTransId', array(), $subselect::JOIN_INNER)
                            ->join(array('DG' => 'MMS_DCGroupTrans'), 'C.DCGroupId=DG.DCGroupId And C.DCRegisterId=DG.DCRegisterId', array(), $subselect::JOIN_INNER)
                            ->join(array('D' => 'MMS_DCRegister'), 'C.DcRegisterId=D.DCRegisterId', array(), $subselect::JOIN_INNER)
                            ->join(array('R' => 'Proj_Resource'), 'R.ResourceId=A.ResourceId', array(), $subselect::JOIN_INNER)
                            ->join(array('E' => 'MMS_PORegister'), 'E.PORegisterId=A.PORegisterId', array(), $subselect::JOIN_INNER)
                            ->join(array('V' => 'Vendor_Master'), 'V.VendorId=E.VendorId', array(), $subselect::JOIN_INNER)
                            ->join(array('CC' => 'WF_OperationalCostCentre'), 'A1.CostCentreId=CC.CostCentreId And D.CostCentreId=CC.CostCentreId', array(), $subselect::JOIN_INNER)
                            ->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $subselect::JOIN_LEFT)
                            ->join(array('CM' => 'WF_CompanyMaster'), 'CC.CompanyId=CM.CompanyId', array(), $subselect::JOIN_INNER)
                            ->join(array('RG' => 'Proj_ResourceGroup'), 'R.ResourceGroupId=RG.ResourceGroupId', array(), $subselect::JOIN_INNER)
                            ->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $subselect::JOIN_LEFT)
                            ->where("E.PODate BETWEEN ('$fromDate') and ('$toDate') And D.CostCentreId=0 And  A1.CostCentreId IN ($CostCentreId)");

                        $select = $sql->select();
                        $select->from(array('A' => 'MMS_POTrans'))
                            ->columns(array(
                                'PORegisterId' => new Expression('A.PORegisterId'),
                                'ResourceId' => new Expression('A.ResourceId'),
                                'PODate' => new Expression('Convert(Varchar(10),E.PODate,103)'),
                                'ReqDate' => new Expression('Convert(Varchar(10),E.ReqDate,103)'),
                                'MinDate' => new Expression('Convert(Varchar(10),D.DCDate,103)'),
                                'ItemId' => new Expression('A.ItemId'),
                                'CostCentreId' => new Expression('A1.CostCentreId'),
                                'CostCentre' => new Expression('CC.CostCentreName'),
                                'PONo' => new Expression('E.PONo'),
                                'CPONo' => new Expression('E.CPONo'),
                                'ReqNo' => new Expression('E.ReqNo'),
                                'VendorName' => new Expression('V.VendorName'),
                                'ResourceGroup' => new Expression('RG.ResourceGroupName'),
                                'Resource' => new Expression('R.ResourceName'),
                                'Specification' => new Expression('A.Description'),
                                'Unit' => new Expression('u.UnitName'),
                                'POQty' => new Expression('CAST(A1.POQty As Decimal(18,6))'),
                                'MinNo' => new Expression('D.DCNo'),
                                'CMinNo' => new Expression('D.CDCNo'),
                                'AcceptQty' => new Expression('CAST(C.DCQty As Decimal(18,6)) As MinQty,CAST(C.AcceptQty As Decimal(18,6))'),
                                'RejectQty' => new Expression('CAST(C.RejectQty As Decimal(18,6))'),
                                'Rejection(%)' => new Expression('Case When (C.DCQty>0 And C.RejectQty>0) Then CAST(((C.RejectQty/C.DCQty)*100) As Decimal(18,6)) Else Cast(0 As Decimal(18,6)) End'),
                                'Rate' => new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST(isnull((A.QRate * A.TFactor),0)/nullif(A.FFactor,0) As Decimal(18,2)) Else CAST(A.QRate As Decimal(18,2)) End'),
                                'Amount' => new Expression('Case When (A.TFactor>0 And A.FFactor>0) Then CAST((A1.POQty * isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0)) As Decimal(18,2))  Else CAST(A.QAmount As Decimal(18,2)) End'),
                                'MinAmount' => new Expression('Case When (DG.TFactor>0 And DG.FFactor>0) Then CAST((C.AcceptQty* isnull((C.QRate*DG.TFactor),0)/nullif(DG.FFactor,0)) As Decimal(18,2)) Else CAST(C.QAmount As Decimal(18,2)) End'),
                                'DelayInDelivery' => new Expression('Case When DATEDIFF(dd,E.ReqDate,D.DCDate)>0 Then DATEDIFF(dd,E.ReqDate,D.DCDate) Else 0 End')
                            ))
                            ->join(array('A1' => 'MMS_POProjTrans'), 'A.PoTransId=A1.POTransId', array(), $select::JOIN_INNER)
                            ->join(array('B' => 'MMS_IPDTrans'), 'A.POTransId=B.POTransId', array(), $select::JOIN_INNER)
                            ->join(array('B1' => 'MMS_IPDProjTrans'), 'B.IPDTransId=B1.IPDTransId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'MMS_DCTrans'), 'B.DCTransId=C.DCTransId', array(), $select::JOIN_INNER)
                            ->join(array('DG' => 'MMS_DCGroupTrans'), 'C.DCGroupId=DG.DCGroupId And C.DCRegisterId=DG.DCRegisterId', array(), $select::JOIN_INNER)
                            ->join(array('D' => 'MMS_DCRegister'), 'C.DcRegisterId=D.DCRegisterId And A1.CostCentreId=D.CostCentreId', array(), $select::JOIN_INNER)
                            ->join(array('R' => 'Proj_Resource'), 'R.ResourceId=A.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('E' => 'MMS_PORegister'), 'E.PORegisterId=A.PORegisterId', array(), $select::JOIN_INNER)
                            ->join(array('V' => 'Vendor_Master'), 'V.VendorId=E.VendorId', array(), $select::JOIN_INNER)
                            ->join(array('CC' => 'WF_OperationalCostCentre'), 'A1.CostCentreId=CC.CostCentreId And D.CostCentreId=CC.CostCentreId', array(), $select::JOIN_INNER)
                            ->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $select::JOIN_LEFT)
                            ->join(array('CM' => 'WF_CompanyMaster'), 'CC.CompanyId=CM.CompanyId', array(), $select::JOIN_INNER)
                            ->join(array('RG' => 'Proj_ResourceGroup'), 'R.ResourceGroupId=RG.ResourceGroupId', array(), $select::JOIN_INNER)
                            ->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $select::JOIN_LEFT)
                            ->where("E.PODate BETWEEN ('$fromDate') and ('$toDate') And  A1.CostCentreId IN ($CostCentreId)");
                        $select->combine($subselect, 'Union ALL');
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($register));
                        return $response;
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function detailedPoAction(){
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
                        $sel->from(array("A" => "MMS_PORegister"))
                            ->columns(array(
                                'PORegisterId' => new Expression('A.PORegisterId'),
                                'POTransId' => new Expression("B.POTransId"),
                                'ResourceId' => new Expression("B.ResourceId"),
                                'ItemId' => new Expression("B.ItemId"),
                                'CostCentreId' => new Expression("H.CostCentreId"),
                                'PODate' => new Expression("Convert(Varchar(10),A.PODate,103)"),
                                'ReqDate' => new Expression("Convert(Varchar(10),A.ReqDate,103)"),
                                'PONo' => new Expression("A.PONo"),
                                'CCPONo' => new Expression("A.CCPONo"),
                                'CPONo' => new Expression("A.CPONo"),
                                'CostCentre' => new Expression("G.CostCentreName"),
                                'Vendor' => new Expression("F.VendorName"),
                                'Code' => new Expression("Case When B.ItemId>0 Then BR.ItemCode Else C.Code End"),
                                'Resource' => new Expression("Case When B.ItemId>0 Then BR.BrandName Else C.ResourceName End"),
                                'Specification' => new Expression("B.Description"),
                                'Unit' => new Expression("U.UnitName"),
                                'Qty' => new Expression("B.POQty"),
                                'BaseRate' => new Expression("Case When (B.FFactor>0 And B.TFactor>0) Then isnull((B.Rate*B.TFactor),0)/nullif(B.FFactor,0) Else B.Rate End"),
                                'QualRate' => new Expression("Case When (B.FFactor>0 And B.TFactor>0) Then isnull((B.QRate*B.TFactor),0)/nullif(B.FFactor,0) Else B.QRate End"),
                                'Amount' => new Expression("Case When (B.FFactor>0 And B.TFactor>0) Then (B.POQty*isnull((B.Rate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.Amount End"),
                                'QAmount' => new Expression("Case When (B.FFactor>0 And B.TFactor>0) Then (B.POQty*isnull((B.QRate*B.TFactor),0)/nullif(B.FFactor,0)) Else B.QAmount End"),
                                'NetAmount' => new Expression("A.NetAmount"),
                                'QualifierName' => new Expression("E.QualifierName"),
                                'Sign' => new Expression("D.Sign"),
                                'Expression' => new Expression("D.Expression"),
                                'ExpPer' => new Expression("(CAST(D.NetPer As Varchar)+'%')"),
                                'QAmt' => new Expression("D.NetAmt"),
                                'ReqNo' => new Expression("A.ReqNo"),
                                'Narration' => new Expression("A.Narration"),
                                'RowNumber' => new Expression("ROW_NUMBER() OVER(PARTITION by B.POTransId Order by B.POTransId Asc)"),
                                'RowNumber1' => new Expression("ROW_NUMBER() OVER(PARTITION by A.PORegisterId Order by A.PORegisterId Asc)"),
                            ))
                            ->join(array('B' => 'MMS_POTrans'), 'A.PORegisterId=B.PORegisterId', array(), $sel::JOIN_INNER)
                            ->join(array('H' => 'MMS_POProjTrans'), 'B.PoTransId=H.POTransId', array(), $sel::JOIN_INNER)
                            ->join(array('C' => 'Proj_Resource'), 'B.ResourceId=C.ResourceId', array(), $sel::JOIN_INNER)
                            ->join(array('D' => 'MMS_POQualTrans'), 'B.PoTransId=D.POTransId and B.ResourceId=D.ResourceId And B.ItemId=D.ItemId', array(), $sel::JOIN_LEFT)
                            ->join(array('E' => 'Proj_QualifierMaster'), 'D.QualifierId=E.QualifierId', array(), $sel::JOIN_LEFT)
                            ->join(array('F' => 'Vendor_Master'), 'A.VendorId=F.VendorId', array(), $sel::JOIN_INNER)
                            ->join(array('G' => 'WF_OperationalCostCentre'), 'H.CostCentreId=G.CostCentreId', array(), $sel::JOIN_INNER)
                            ->join(array('CM' => 'WF_CompanyMaster'), 'G.CompanyId=CM.CompanyId', array(), $sel::JOIN_INNER)
                            ->join(array('BR' => 'MMS_Brand'), 'B.ItemId=BR.BrandId And B.ResourceId=BR.ResourceId', array(), $sel::JOIN_LEFT)
                            ->join(array('U' => 'Proj_UOM'), 'B.UnitId=U.UnitId', array(), $sel::JOIN_LEFT)
                            ->Where (array("A.PODate BETWEEN ('$fromDate') and ('$toDate')  And A.LivePO=1 and G.CostCentreId IN ($CostCentreId)"));

                        $sel2 = $sql->select();
                        $sel2 -> from(array("G"=>$sel))
                            ->columns(array(
                                'PORegisterId' => new Expression('G.PORegisterId'),
                                'ResourceId' => new Expression("G.ResourceId"),
                                'ItemId' => new Expression("G.ItemId"),
                                'CostCentreId' => new Expression("G.CostCentreId"),
                                'PODate' => new Expression("Convert(Varchar(10),G.PODate,103)"),
                                'ReqDate' => new Expression("Convert(Varchar(10),G.ReqDate,103)"),
                                'CostCentre' => new Expression("G.CostCentre"),
                                'Vendor' => new Expression("G.Vendor"),
                                'ResourceGroup' => new Expression("RG.ResourceGroupName"),
                                'Resource' => new Expression("G.Resource"),
                                'Code' => new Expression("G.Code"),
                                'Specification' => new Expression("G.Specification"),
                                'Unit' => new Expression("G.Unit"),
                                'Qty' => new Expression("Case When G.RowNumber=1 Then G.Qty Else 0 End"),
                                'BaseRate' => new Expression("Case When G.RowNumber=1 Then G.BaseRate Else 0 End"),
                                'QualRate' => new Expression("Case When G.RowNumber=1 Then G.QualRate Else 0 End"),
                                'Amount' => new Expression("Case When G.RowNumber=1 Then G.Amount Else 0 End"),
                                'QAmount' => new Expression("Case When G.RowNumber=1 Then G.QAmount Else 0 End"),
                                'NetAmount' => new Expression("Case When G.RowNumber1=1 Then G.NetAmount Else 0 End"),
                                'ExtraCharges' => new Expression("Case When G.RowNumber1=1 Then (Select SUM(A.Value) From MMS_POPaymentTerms A Inner Join WF_TermsMaster B On A.TermsId=B.TermsId And B.TermType='S' And B.AccountUpdate=1 And B.IncludeGross=1 Where A.PORegisterId=G.PORegisterId) Else 0 End"),
                                'QualifierName' => new Expression("Case When G.RowNumber=1 Then G.QualifierName Else '' End"),
                                'AddLessFlag' => new Expression("Case When G.RowNumber=1 Then G.Sign Else '' End"),
                                'Expression' => new Expression("Case When G.RowNumber=1 Then G.Expression Else '' End"),
                                'ExpPer' => new Expression("Case When G.RowNumber=1 Then G.ExpPer Else '' End"),
                                'QAmt' => new Expression("Case When G.RowNumber=1 Then G.QAmt Else 0 End"),
                                'ReqNo' => new Expression("G.ReqNo"),
                                'Narration' => new Expression("G.Narration"),
                                'RowNumber1' => new Expression("G.RowNumber1"),
                                'RowNumber' => new Expression("G.RowNumber"),
                            ))
                            ->join(array('R' => 'Proj_Resource'), 'G.ResourceId=R.ResourceId', array(), $sel::JOIN_INNER)
                            ->join(array('RG' => 'Proj_ResourceGroup'), 'R.ResourceGroupId=RG.ResourceGroupId', array(), $sel::JOIN_INNER);
                        $statement = $sql->getSqlStringForSqlObject($sel2);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock));
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function wbswisePodetailAction(){
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
                        $sel->from(array("PAT" => "MMS_POAnalTrans"))
                            ->columns(array(
                                'CostCentreId' => new Expression('PPT.CostCentreId'),
                                'AnalysisId' => new Expression("PAT.AnalysisId"),
                                'ResourceId' => new Expression("PAT.ResourceId"),
                                'ItemId' => new Expression("PAT.ItemId"),
                                'POQty' => new Expression("PAT.POQty"),
                                'QRate' => new Expression("Case When (PT.FFactor>0 And PT.TFactor>0) Then isnull((PT.QRate*PT.TFactor),0)/nullif(PT.FFactor,0) Else PT.QRate End"),
                                'Amount' => new Expression("PAT.POQty*Case When (PT.FFactor>0 And PT.TFactor>0) Then isnull((PT.QRate*PT.TFactor),0)/nullif(PT.FFactor,0) Else PT.QRate End"),
                                'UnitId' => new Expression("PT.UnitId")))
                            ->join(array('PPT' => 'MMS_POProjTrans'), 'PAT.POProjTransId=PPT.POProjTransId', array(), $sel::JOIN_INNER)
                            ->join(array('PT' => 'MMS_POTrans'), 'PPT.POTransId=PT.PoTransId', array(), $sel::JOIN_INNER)
                            ->join(array('PR' => 'MMS_PORegister'), 'PT.PORegisterId=PR.PORegisterId', array(), $sel::JOIN_INNER)
                            ->Where ("PR.PODate BETWEEN ('$fromDate') And ('$toDate') And PR.CostCentreId IN ($CostCentreId)");

                        $sel1 = $sql->select();
                        $sel1 -> from(array("A"=>$sel))
                            ->columns(array(
                                'CostCentreId' => new Expression('A.CostCentreId'),
                                'AnalysisId' => new Expression("A.AnalysisId"),
                                'ResourceId' => new Expression("A.ResourceId"),
                                'ItemId' => new Expression("A.ItemId"),
                                'Qty' => new Expression("SUM(CAST(A.POQty As Decimal(18,6)))"),
                                'Rate' => new Expression("A.QRate"),
                                'Amount' => new Expression("SUM(CAST( A.Amount As Decimal(18,3)))"),
                                'UnitId' => new Expression("A.UnitId")))
                            ->group(array("A.ResourceId","A.CostCentreId","A.AnalysisId","A.ItemId","A.QRate","A.UnitId"));

                        $sel2 = $sql->select();
                        $sel2 -> from(array("A"=>$sel1))
                            ->columns(array(
                                'ParentText' => new Expression('B.ParentText'),
                                'WbsName' => new Expression("B.WbsName"),
                                'CostCentreName' => new Expression("I.CostCentreName"),
                                'ResourceGroupName' => new Expression("RG.ResourceGroupName"),
                                'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else C.Code End"),
                                'Resource' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else C.ResourceName End"),
                                'Amount' => new Expression("SUM(CAST( A.Amount As Decimal(18,3)))"),
                                'CostCentreId' => new Expression("A.CostCentreId"),
                                'AnalysisId' => new Expression("A.AnalysisId"),
                                'ResourceId' => new Expression("A.ResourceId"),
                                'ItemId' => new Expression("A.ItemId"),
                                'Qty' => new Expression("A.Qty"),
                                'Rate' => new Expression("A.Rate"),
                                'Amount' => new Expression("A.Amount")
                                ))
                            ->join(array('B' => 'Proj_WbsMaster'), 'A.AnalysisId=B.WbsId', array(), $sel::JOIN_INNER)
                            ->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceID', array(), $sel::JOIN_INNER)
                            ->join(array('I' => 'WF_OperationalCostCentre'), 'A.CostCentreId=I.CostCentreId', array(), $sel::JOIN_INNER)
                            ->join(array('CA' => 'WF_CostCentre'), 'I.FACostCentreId=CA.CostCentreId', array(), $sel::JOIN_INNER)
                            ->join(array('CM' => 'WF_CompanyMaster'), ' CA.CompanyId=CM.CompanyId', array(), $sel::JOIN_INNER)
                            ->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $sel::JOIN_LEFT)
                            ->join(array('RG' => 'Proj_ResourceGroup'), 'C.ResourceGroupId=RG.ResourceGroupId', array(), $sel::JOIN_INNER)
                            ->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $sel::JOIN_LEFT)
                            ->where(array("I.CostCentreId IN ($CostCentreId)"));
                        $statement = $sql->getSqlStringForSqlObject($sel2);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock));
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function wbswisePopendingAction(){
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
                        $select = $sql->select();
                        $select->from(array('a' => 'MMS_POAnalTrans'))
                            ->columns(array(
                                'PLevel' => new Expression('c.ParentText'),
                                'WbsName' => new Expression('c.WbsName'),
                                'CostCentre' => new Expression('j.CostCentreName'),
                                'ResourceGroup' => new Expression('ResourceGroupName'),
                                'Code' => new Expression('Case When A.ItemId <> 0 Then BR.ItemCode Else D.Code End'),
                                'Resource' => new Expression('Case When A.ItemId <> 0 Then BR.BrandName Else D.ResourceName End'),
                                'Unit' => new Expression('u.UnitName'),
                                'POQty' => new Expression('ISNULL(SUM(A.POQty),0)'),
                                'DCQty' => new Expression('ISNULL(SUM(A.AcceptQty),0)'),
                                'BalQty' => new Expression('ISNULL(SUM(A.BalQty),0)')
                            ))
                            ->join(array('b' => 'MMS_POProjTrans'), 'A.POProjTransId=B.POProjTransId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'Proj_WbsMaster'), 'A.AnalysisId=C.WbsId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'), 'A.ResourceId=D.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('h' => 'MMS_POTrans'), 'B.POTransId=H.PoTransId', array(), $select::JOIN_INNER)
                            ->join(array('I' => 'MMS_PORegister'), 'H.PORegisterId=I.PORegisterId', array(), $select::JOIN_INNER)
                            ->join(array('j' => 'WF_OperationalCostCentre'), 'B.CostCentreId=J.CostCentreId', array(), $select::JOIN_INNER)
                            ->join(array('br' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $select::JOIN_LEFT)
                            ->join(array('ca' => 'WF_CostCentre'), 'J.FACostCentreId=CA.CostCentreId', array(), $select::JOIN_INNER)
                            ->join(array('cm' => 'WF_CompanyMaster'), 'CA.CompanyId=CM.CompanyId', array(), $select::JOIN_INNER)
                            ->join(array('rg' => 'Proj_ResourceGroup'), 'D.ResourceGroupId=RG.ResourceGroupId', array(), $select::JOIN_INNER)
                            ->join(array('u' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $select::JOIN_LEFT)
                            ->where("I.PODate BETWEEN ('$fromDate') and ('$toDate') And B.CostCentreId IN ($CostCentreId) And I.Approve='Y' And H.ShortClose=0
                             Group By C.ParentText,C.WbsName,D.Code,D.ResourceName,U.UnitName,J.CostCentreName,A.ItemId,
                             BR.ItemCode,RG.ResourceGroupName,BR.BrandName Having SUM(A.BalQty)>0");
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($register));
                        return $response;
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function wbswisePoregisterAction(){
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
                        $sel->from(array("PAT" => "MMS_POAnalTrans"))
                            ->columns(array(
                                'CostCentreId' => new Expression('PPT.CostCentreId'),
                                'AnalysisId' => new Expression("PAT.AnalysisId"),
                                'ResourceId' => new Expression("PAT.ResourceId"),
                                'ItemId' => new Expression("PAT.ItemId"),
                                'POQty' => new Expression("PAT.POQty"),
                                'Amount' => new Expression("PAT.POQty*Case When (PT.FFactor>0 And PT.TFactor>0) Then isnull((PT.QRate*PT.TFactor),0)/nullif(PT.FFactor,0) Else PT.QRate End"),
                                'UnitId' => new Expression("PT.UnitId"),
                                'PONo' => new Expression("PR.PONo"),
                                'CCPONo' => new Expression("PR.CCPONo"),
                                'CPONo' => new Expression("PR.CPONo")
                            ))
                            ->join(array('PPT' => 'MMS_POProjTrans'), 'PAT.POProjTransId=PPT.POProjTransId', array(), $sel::JOIN_INNER)
                            ->join(array('PT' => 'MMS_POTrans'), 'PPT.POTransId=PT.PoTransId', array(), $sel::JOIN_INNER)
                            ->join(array('PR' => 'MMS_PORegister'), 'PT.PORegisterId=PR.PORegisterId', array(), $sel::JOIN_INNER)
                            ->Where ("PR.PODate BETWEEN ('$fromDate') And ('$toDate') And PR.CostCentreId IN ($CostCentreId)");

                        $sel1 = $sql->select();
                        $sel1 -> from(array("A"=>$sel))
                            ->columns(array(
                                'CostCentreId' => new Expression('A.CostCentreId'),
                                'AnalysisId' => new Expression("A.AnalysisId"),
                                'ResourceId' => new Expression("A.ResourceId"),
                                'ItemId' => new Expression("A.ItemId"),
                                'Qty' => new Expression("(CAST(A.POQty As Decimal(18,6)))"),
                                'Amount' => new Expression("(CAST( A.Amount As Decimal(18,3)))"),
                                'UnitId' => new Expression("A.UnitId"),
                                'CPONo' => new Expression("A.CPONo"),
                                'CCPONo' => new Expression("A.CCPONo"),
                                'PONo' => new Expression("A.PONo")
                            ));

                        $sel2 = $sql->select();
                        $sel2 -> from(array("A"=>$sel1))
                            ->columns(array(
                                'PLevel' => new Expression('B.ParentText'),
                                'WbsName' => new Expression("B.WbsName"),
                                'CostCentre' => new Expression("I.CostCentreName"),
                                'ResourceGroup' => new Expression("RG.ResourceGroupName"),
                                'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else C.Code End"),
                                'Resource' => new Expression(" Case When A.ItemId <> 0 Then BR.BrandName Else C.ResourceName End"),
                                'Amount' => new Expression("A.Amount"),
                                'CostCentreId' => new Expression("A.CostCentreId"),
                                'AnalysisId' => new Expression("A.AnalysisId"),
                                'ResourceId' => new Expression("A.ResourceId"),
                                'ItemId' => new Expression("A.ItemId"),
                                'Qty' => new Expression("A.Qty"),
                                'Amount' => new Expression("A.Amount"),
                                'Unit' => new Expression("U.UnitName"),
                                'PONo' => new Expression("A.PONo"),
                                'CCPONo' => new Expression("A.CCPONo"),
                                'CPONo' => new Expression("A.CPONo")
                            ))

                            ->join(array('B' => 'Proj_WbsMaster'), 'A.AnalysisId=B.WbsId', array(), $sel::JOIN_INNER)
                            ->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceID', array(), $sel::JOIN_INNER)
                            ->join(array('I' => 'WF_OperationalCostCentre'), 'A.CostCentreId=I.CostCentreId', array(), $sel::JOIN_INNER)
                            ->join(array('CA' => 'WF_CostCentre'), 'I.FACostCentreId=CA.CostCentreId', array(), $sel::JOIN_INNER)
                            ->join(array('CM' => 'WF_CompanyMaster'), ' CA.CompanyId=CM.CompanyId', array(), $sel::JOIN_INNER)
                            ->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $sel::JOIN_LEFT)
                            ->join(array('RG' => 'Proj_ResourceGroup'), 'C.ResourceGroupId=RG.ResourceGroupId', array(), $sel::JOIN_INNER)
                            ->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $sel::JOIN_LEFT)
                            ->where(array("I.CostCentreId IN ($CostCentreId)"));
                        $statement = $sql->getSqlStringForSqlObject($sel2);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock));
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function pendingDeliveryAction(){
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
                        $sel->from(array("A" => "MMS_POTrans "))
                            ->columns(array(
                                'PORegisterId' => new Expression('C.PORegisterId'),
                                'POTransId' => new Expression("A.POTransId"),
                                'CompanyId' => new Expression("CM.CompanyId"),
                                'CostCentreId' => new Expression("CC.CostCentreId"),
                                'PODate' => new Expression("C.PODate"),
                                'PONo' => new Expression("C.PONo"),
                                'CCPONo' => new Expression("C.CCPONo"),
                                'CPONo' => new Expression("C.CPONo"),
                                'ReqDate' => new Expression(" Case When ISNULL(PS.POTransId,0) > 0 Then Case When (Select Max(ReqDate) From MMS_POSchedule Where POTransId=PS.POTransId And ReqDate <= ' DelUpto + ') <= ' DelUpto + ' Then (Select Max(ReqDate) From MMS_POSchedule Where POTransId=PS.POTransId And ReqDate <= '$fromDate' ) Else PS.ReqDate End Else A.ReqDate End"),
                                'ReqNo' => new Expression("C.ReqNo"),
                                'Vendor' => new Expression("V.VendorName"),
                                'CostCentre' => new Expression("CC.CostCentreName"),
                                'ResourceId' => new Expression("A1.ResourceId"),
                                'ResourceGroup' => new Expression("RG.ResourceGroupName"),
                                'Code' => new Expression("Case When A.ItemId <> 0 Then BR.ItemCode Else B.Code End"),
                                'Resource' => new Expression("Case When A.ItemId <> 0 Then BR.BrandName Else ResourceName End"),
                                'Specification' => new Expression("A.Description"),
                                'Unit' => new Expression("U.UnitName"),
                                'POQty' => new Expression(" Case When ISNULL(PS.POTransId,0) > 0 Then Case When (Select Max(ReqDate) From MMS_POSchedule Where POTransId=PS.POTransId And ReqDate <= ' DelUpto + ') <= ' DelUpto + ' Then ISNULL(PS.Qty,0) Else ISNULL(PS.Qty,0) End Else A1.POQty End"),
                                'Rate' => new Expression("Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End"),
                                'DCQty' => new Expression("A1.AcceptQty"),
                                'DCAmount' => new Expression("(A1.AcceptQty * Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End)"),
                                'BillQty' => new Expression("A1.BillQty"),
                                'CancelQty' => new Expression("A1.CancelQty"),
                                'BalQty' => new Expression("A1.BalQty"),
                                'Amount' => new Expression("(A1.BalQty * Case When (A.FFactor>0 And A.TFactor>0) Then isnull((A.QRate*A.TFactor),0)/nullif(A.FFactor,0) Else A.QRate End)"),
                                'PendingNoOfDays' => new Expression(" DATEDIFF(Day,C.PODate,getdate())")
                            ))
                            ->join(array('A1' => 'MMS_POProjTrans'), 'A.PoTransId=A1.POTransId', array(), $sel::JOIN_INNER)
                            ->join(array('B' => 'Proj_Resource'), 'A.ResourceId=B.ResourceId', array(), $sel::JOIN_INNER)
                            ->join(array('C' => 'MMS_PORegister'), 'A.PORegisterId=C.PORegisterId', array(), $sel::JOIN_INNER)
                            ->join(array('CC' => 'WF_OperationalCostCentre'), 'A1.CostCentreId= CC.CostCentreId', array(), $sel::JOIN_INNER)
                            ->join(array('V' => 'Vendor_Master'), 'V.VendorId=C.VendorId', array(), $sel::JOIN_INNER)
                            ->join(array('BR' => 'MMS_Brand'), 'A.ItemId=BR.BrandId', array(), $sel::JOIN_LEFT)
                            ->join(array('CM' => 'WF_CompanyMaster'), 'CC.CompanyId=CM.CompanyId', array(), $sel::JOIN_INNER)
                            ->join(array('RG' => 'Proj_ResourceGroup'), " B.ResourceGroupId=RG.ResourceGroupId", array(), $sel::JOIN_INNER)
                            ->join(array('PS' => 'MMS_POSchedule'), new Expression("A.POTransId=PS.POTransId And PS.ReqDate <= '$fromDate' "), array(), $sel::JOIN_LEFT)
                            ->join(array('U' => 'Proj_UOM'), 'A.UnitId=U.UnitId', array(), $sel::JOIN_LEFT)
                            ->Where ("A1.BalQty>0  And C.Approve='Y' And C.ShortClose=0 And C.LivePO=1 ");

                        $sel1 = $sql->select();
                        $sel1 -> from(array("G"=>$sel))
                            ->columns(array(
                                'PORegisterId' => new Expression('G.PORegisterId'),
                                'POTransId' => new Expression("G.POTransId"),
                                'CostCentreId' => new Expression("G.CostCentreId"),
                                'CompanyId' => new Expression("G.CompanyId"),
                                'PODate' => new Expression("G.PODate"),
                                'PONo' => new Expression("G.PONo"),
                                'CPONo' => new Expression("G.CPONo"),
                                'CCPONo' => new Expression("G.CCPONo"),
                                'ReqNo' => new Expression("G.ReqNo"),
                                'ReqDate' => new Expression("G.ReqDate"),
                                'ResourceGroup' => new Expression("G.ResourceGroup"),
                                'Vendor' => new Expression("G.Vendor"),
                                'CostCentre' => new Expression("G.CostCentre"),
                                'Code' => new Expression("G.Code"),
                                'Resource' => new Expression("G.Resource"),
                                'Specification' => new Expression("G.Specification"),
                                'Unit' => new Expression("G.Unit"),
                                'POQty' => new Expression("SUM(G.POQty)"),
                                'Rate' => new Expression("G.Rate"),
                                'POAmount' => new Expression("(SUM(G.POQty)* G.Rate)"),
                                'DCQty' => new Expression("Case When G.DCQty > SUM(G.POQty) Then SUM(G.POQty) Else G.DCQty End"),
                                'DCAmount' => new Expression("Case When G.DCQty > SUM(G.POQty) Then (SUM(G.POQty) * G.Rate) Else (G.DCQty * G.Rate) End"),
                                'BillQty' => new Expression("Case When G.BillQty > SUM(G.POQty) Then SUM(G.POQty) Else G.BillQty End"),
                                'CancelQty' => new Expression("Case When G.CancelQty > SUM(G.POQty) Then SUM(G.POQty) Else G.CancelQty End"),
                                'PendingNoOfDays' => new Expression("G.PendingNoOfDays")))
                            ->group(array("G.PORegisterId","G.POTransId", "G.CostCentreId","G.CompanyId", "G.PODate" ,"G.PONo","G.CCPONo",
                                "G.CPONo","G.ReqNo","G.ReqDate","G.ResourceGroup","G.Vendor","G.CostCentre","G.ResourceGroup","G.Code",
                                "G.Resource","G.Specification","G.Unit","G.Rate","G.DCQty","G.DCAmount","G.BillQty","G.CancelQty ",
                                "G.BalQty" ,"G.Amount","G.PendingNoOfDays"));

                        $sel2 = $sql->select();
                        $sel2 -> from(array("G1"=>$sel1))
                            ->columns(array(
                                'PORegisterId' => new Expression('G1.PORegisterId'),
                                'POTransId' => new Expression("G1.POTransId"),
                                'CostCentreId' => new Expression("G1.CostCentreId"),
                                'CompanyId' => new Expression("G1.CompanyId"),
                                'PODate' => new Expression("Convert(Varchar(10),G1.PODate,103)"),
                                'PONo' => new Expression("G1.PONo"),
                                'CPONo' => new Expression("G1.CPONo"),
                                'CCPONo' => new Expression("G1.CCPONo"),
                                'ReqNo' => new Expression("G1.ReqNo"),
                                'ReqDate' => new Expression("Convert(Varchar(10),G1.ReqDate,103)"),
                                'ResourceGroup' => new Expression("G1.ResourceGroup"),
                                'Vendor' => new Expression("G1.Vendor"),
                                'CostCentre' => new Expression("G1.CostCentre"),
                                'Code' => new Expression("G1.Code"),
                                'Resource' => new Expression("G1.Resource"),
                                'Specification' => new Expression("G1.Specification"),
                                'Unit' => new Expression("G1.Unit"),
                                'POQty' => new Expression("G1.POQty"),
                                'Rate' => new Expression("G1.Rate"),
                                'POAmount' => new Expression("G1.POAmount"),
                                'DCQty' => new Expression("G1.DCQty"),
                                'DCAmount' => new Expression("G1.DCAmount"),
                                'BillQty' => new Expression("G1.BillQty"),
                                'CancelQty' => new Expression("G1.CancelQty"),
                                'BalQty' => new Expression("(G1.POQty-G1.DCQty-G1.BillQty-G1.CancelQty)"),
                                'PendingAmount' => new Expression("((G1.POQty-G1.DCQty-G1.BillQty-G1.CancelQty)*G1.Rate)"),
                                'PendingNoOfDays' => new Expression("G1.PendingNoOfDays")))
                            ->where(array("G1.ReqDate <= '$fromDate' And (G1.POQty-G1.DCQty-G1.BillQty-G1.CancelQty)>0 and G1.CostCentreId IN ($CostCentreId)  Order By G1.PODate Asc"));
                        $statement = $sql->getSqlStringForSqlObject($sel2);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock));
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function poAdvanceAction(){
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
                        $select = $sql->select();
                        $select->from(array('A' => 'MMS_PORegister'))
                            ->columns(array(
                                'PoRegisterId' => new Expression('distinct A.PoRegisterId'),
                                'PONo' => new Expression('A.PONo'),
                                'CCPONo' => new Expression('A.CCPONo'),
                                'CPONo' => new Expression('A.CPONo'),
                                'PODate' => new Expression('Convert(Varchar(10),A.PODate,103)'),
                                'Advance' => new Expression('CAST(B.Value+B.PAdvValue As Decimal(18,3))'),
                                'Vendor' => new Expression('V.VendorName'),
                                'AmountPaid' => new Expression('CAST(B.PaidAmount+B.PPaidAmount As Decimal(18,3))'),
                                'ChequeNo' => new Expression("Stuff((Select distinct ',' + H.ChqNo From MMS_PORegister F Left Join FA_BillRegister G  on F.RefId = G.BillRegisterId Left Join FA_Adjustment H  on G.BillRegisterId = H.BillRegisterId Where A.PORegisterId = F.PORegisterId FOR XML path('')),1,1,'')"),
                                'ChequeDate' => new Expression("Stuff((Select distinct ',' + CONVERT(Varchar(10),K.ChqDate,103) From MMS_PORegister I Left Join FA_BillRegister J  on I.RefId = J.BillRegisterId Left Join FA_Adjustment K  on J.BillRegisterId = K.BillRegisterId Where A.PORegisterId = I.PORegisterId FOR XML path('')),1,1,'')")
                            ))
                            ->join(array('B' => 'MMS_POPaymentTerms'), 'A.PORegisterId=B.PORegisterId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'WF_TermsMaster'), 'B.TermsId=C.TermsId', array(), $select::JOIN_INNER)
                            ->join(array('V' => 'Vendor_Master'), 'A.VendorId = V.VendorId', array(), $select::JOIN_INNER)
                            ->join(array('D' => 'FA_BillRegister'), 'A.RefId = D.BillRegisterId', array(), $select::JOIN_LEFT)
                            ->join(array('E' => 'FA_Adjustment'), 'D.BillRegisterId = E.BillRegisterId', array(), $select::JOIN_LEFT)
                            ->where("A.PODate BETWEEN ('$fromDate') And ('$toDate') And C.Title='Advance' And A.CostCentreId IN ($CostCentreId)");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_poadv = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_poadv));
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function poadvanceHistoryAction(){
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
                        $select = $sql->select();
                        $select->from(array('a' => 'MMS_PORegister'))
                            ->columns(array(
                                'PORegisterId' => new Expression('A.PORegisterId'),
                                'CostCentre' => new Expression('F.CostCentreName'),
                                'PVDate' => new Expression('Convert(Varchar(10),E.PVDate,103)'),
                                'PONo' => new Expression('A.PONo'),
                                'VendorName' => new Expression('G.VendorName'),
                                'Advance' => new Expression('CAST(B.Value As Decimal(18,3))'),
                                'Balance' => new Expression('CAST((B.Value-B.AdjustAmount) As Decimal(18,3))'),
                                'PVNo' => new Expression('E.PVNo'),
                                'AdjustAmount' => new Expression('Cast(D.Amount As Decimal(18,3))')
                            ))
                            ->join(array('B' => 'MMS_POPaymentTerms'), 'A.PORegisterId=B.PORegisterId', array(), $select::JOIN_INNER)
                            ->join(array('C' => 'WF_TermsMaster'), new Expression("B.TermsId=C.TermsId  And C.Title='Advance'"), array(), $select::JOIN_INNER)
                            ->join(array('D' => 'MMS_AdvAdjustment'), 'A.PORegisterId=D.PORegisterId', array(), $select::JOIN_INNER)
                            ->join(array('E' => 'MMS_PVRegister'), ' D.BillRegisterId=E.PVRegisterId', array(), $select::JOIN_INNER)
                            ->join(array('F' => 'WF_OperationalCostCentre'), 'A.CostCentreId=F.CostCentreId And E.CostCentreId=F.CostCentreId', array(), $select::JOIN_INNER)
                            ->join(array('G' => 'Vendor_Master'), 'A.VendorId=G.VendorId', array(), $select::JOIN_INNER)
                            ->where("E.PVDate BETWEEN ('$fromDate') and ('$toDate') and F.CostCentreId IN ($CostCentreId) ");
                        $statement = $statement = $sql->getSqlStringForSqlObject($select);
                        $register = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($register));
                        return $response;
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

            $projSelect = $sql->select();
            $projSelect->from('WF_CompanyMaster')
                ->columns(array('CompanyId', 'CompanyName'));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_company = $dbAdapter->query( $projStatement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

	public function poshortCloseAction(){
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
						case 'cost':
						
						$select1 = $sql->select();
						$select1->from(array('A' => 'MMS_PORegister'))
							->columns(array(
								'PORegisterId'=>new Expression('A.PORegisterId'),
								'POTransId'=> new Expression('B.POTransId'),
								'PODate'=> new Expression('Convert(Varchar(10),A.PODate,103)'),
								'PONo'=> new Expression('A.PONo'),
								'CCPONo'=> new Expression("A.CCPONo"),
								'CPONo'=> new Expression("A.CPONo"),
								'CostCentre'=> new Expression("F.CostCentreName"),
								'Code'=> new Expression("Case When B.ItemId>0 Then D.ItemCode Else C.Code End"),
								'Resource'=> new Expression("Case When B.ItemId>0 Then D.BrandName Else C.ResourceName End"),
								'Unit'=> new Expression("E.UnitName"),
								'MINQty'=> new Expression("CAST(B.AcceptQty As Decimal(18,6))"),
								'ShortCloseQty'=> new Expression("CAST(B.BalQty As Decimal(18,6))"),
								'BalQty'=> new Expression("CAST(B.BalQty As Decimal(18,6))"),
								'Remarks'=> new Expression("G.Remarks")))
								
									->join(array('B' => 'MMS_POTrans'),"A.PORegisterId=B.PORegisterId", array(), $select1::JOIN_INNER)
									->join(array('C' => 'Proj_Resource'), 'B.ResourceId=C.ResourceId', array(), $select1::JOIN_INNER)	
									->join(array('D' => 'MMS_Brand'),'B.ResourceId=D.ResourceId And B.ItemId=D.BrandId', array(), $select1::JOIN_LEFT)	
									->join(array('E' => 'Proj_Uom'), 'B.UnitId=E.UnitId', array(), $select1::JOIN_LEFT)
									->join(array('F' => 'WF_OperationalCostCentre'), 'A.CostCentreId=F.CostCentreId', array(), $select1::JOIN_INNER)
									->join(array('G' => 'MMS_POShortCloseReg'), 'A.PORegisterId=G.PORegisterId', array(), $select1::JOIN_INNER)	

							->where(array("A.ShortClose=1 And B.ShortClose=1 And A.PODate Between ('$fromDate') AND ('$toDate') And A.CostCentreId IN ($CostCentreId)"));
						$statement= $sql->getSqlStringForSqlObject($select1); 
						$poshortclose = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	
						$this->_view->setTerminal(true);
						$response = $this->getResponse()->setContent(json_encode($poshortclose));
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
	public function designAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $UserId; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/po/header/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("mms/default", array("controller" => "purchase","action" => "display-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/po/header/template.phtml';
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

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/po/footer/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("mms/default", array("controller" => "purchase","action" => "display-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/po/footer/footertemplate.phtml';
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
	public function minheaderAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $UserId; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/min/header/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("mms/default", array("controller" => "min","action" => "register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/min/header/template.phtml';
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
	public function minfooterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/min/footer/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("mms/default", array("controller" => "min","action" => "register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/min/footer/footertemplate.phtml';
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
	public function minconversionHeaderAction(){
       if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/minconversion/header/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("mms/default", array("controller" => "minconversion","action" => "conversionregister"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/minconversion/header/template.phtml';
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
	public function minconversionFooterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/minconversion/footer/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("mms/default", array("controller" => "minconversion","action" => "conversionregister"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/minconversion/footer/footertemplate.phtml';
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
	public function purchasebillHeaderAction(){
       if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/purchasebill/header/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("mms/default", array("controller" => "purchasebill","action" => "purchasebill-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/purchasebill/header/template.phtml';
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
	public function purchasebillFooterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/purchasebill/footer/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("mms/default", array("controller" => "purchasebill","action" => "purchasebill-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/purchasebill/footer/footertemplate.phtml';
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
	public function issueHeaderAction(){
       if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/issue/header/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("mms/default", array("controller" => "issue","action" => "issue-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/issue/header/template.phtml';
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
	public function issueFooterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/issue/footer/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("mms/default", array("controller" => "issue","action" => "issue-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/issue/footer/footertemplate.phtml';
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
	public function transdispatchHeaderAction(){
       if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/transdispatch/header/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("mms/default", array("controller" => "transfer","action" => "display-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/transdispatch/header/template.phtml';
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
	public function transdispatchFooterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/transdispatch/footer/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("mms/default", array("controller" => "transfer","action" => "display-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/transdispatch/footer/footertemplate.phtml';
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
	public function billreturnHeaderAction(){
       if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/billreturn/header/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work"){
                $this->redirect()->toRoute("mms/default", array("controller" => "billreturn","action" => "returnbill-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/billreturn/header/template.phtml';
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
	public function billreturnFooterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );
		//echo $type; die;

        $request = $this->getRequest();
        if($type=="work1"){
            $dir = 'public/billreturn/footer/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
		
        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);

            if($type=="work1"){
                $this->redirect()->toRoute("mms/default", array("controller" => "billreturn","action" => "returnbill-register"));
            } 

        } 
		else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

            if (!file_exists($filePath)) {
                if($type=="work1"){
                    $filePath = 'public/billreturn/footer/footertemplate.phtml';
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