<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wpm\Controller;

use JsonSchema\Constraints\Format;
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
use Application\View\Helper\CommonHelper;
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

    public function indexAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" WPM Reports");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $this->_view->setTerminal(true);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $result =  "";
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {

            }

            // get cost centres
            $select = $sql->select();
            $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                ->columns( array( 'CostCentreId', 'CostCentreName' ) )
                ->where( 'Deactivate=0' );
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->arr_costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function WorkBillTypeAction(){
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
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
                $BillType= $this->bsf->isNullCheck($postParams['BillType'],'string');

                if($CostCentreId == ""){
                    $CostCentreId =0;
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
                        $select1->from(array('A' => 'wpm_workbilltrans'))
                            ->columns(array(
                                'BillRegisterId' => new Expression('A.BillRegisterId'),
                                'WorkBillTransId' => new Expression('A.WorkBillTransId'),
                                'BillDate' => new Expression('Convert(Varchar(10),B.BillDate,103)'),
                                'Specification' => new Expression('C.Specification'),
                                'BillNo' => new Expression("B.BillNo"),
                                'SerialNo' => new Expression("C.RefSerialNo"),
                                'CostCentreID' => new Expression("B.CostCentreID"),
                                'Qty' => new Expression("CAST(A.CurQty As Decimal(18,6))"),
                                'Rate' => new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'Amount' => new Expression("CAST(A.CurAmount As Decimal(18,3))")
                            ))
                            ->join(array('B' => 'wpm_workbillregister'), "A.BillRegisterId=B.BillRegisterId", array(), $select1::JOIN_LEFT)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array(), $select1::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'A.ResourceId=D.ResourceId', array('Code', 'ResourceName'), $select1::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'C.UnitId=F.UnitId', array('UnitName'), $select1::JOIN_LEFT)
                            ->join(array('E' => 'vendor_master'), 'B.VendorId=E.VendorId', array('VendorName'), $select1::JOIN_LEFT);
                        if ($BillType == "IOW") {
                            $ResourceId = 0;
                            $select1->where(array("B.BillDate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId"));

                        } elseif ($BillType == "Activity") {
                            $IOWId = 0;
                            $select1->where(array("B.BillDate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.IOWId=$IOWId"));

                        } elseif ($BillType == '') {
                            $ResourceId = 0;
                            $IOWId = 0;
                            $select1->where(array("B.BillDate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId And A.IOWId=$IOWId"));

                        }
                    echo     $statement = $sql->getSqlStringForSqlObject($select1); die;
                        $OrderBillType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($OrderBillType));
                        return $response;
                        break;
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
//            $fromDat = $this->params()->fromRoute('dateFrom');
//            $toDat = $this->params()->fromRoute('dateTo');
//            $BillType = $this->params()->fromRoute('BillType');
//            $CostCentreId = $this->params()->fromRoute('CostCentre');

//            $this->_view->OrderBillType=$OrderBillType;

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
    public function WorkOrderTypeAction(){
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
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
                $BillType= $this->bsf->isNullCheck($postParams['BillType'],'string');

                if($CostCentreId == ""){
                    $CostCentreId =0;
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
                        $select1->from(array('A' => 'WPM_WOTrans'))
                            ->columns(array(
                                'WORegisterId' => new Expression('A.WORegisterId'),
                                'WOTransId' => new Expression('A.WOTransId'),
                                'WODate' => new Expression('Convert(Varchar(10),B.WODate,103)'),
                                'Specification' => new Expression('C.Specification'),
                                'WONo' => new Expression("B.WONo"),
                                'SerialNo' => new Expression("C.RefSerialNo"),
                                'CostCentreID' => new Expression("B.CostCentreID"),
                                'Qty' => new Expression("CAST(A.Qty As Decimal(18,6))"),
                                'Rate' => new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'Amount' => new Expression("CAST(A.Amount As Decimal(18,3))")
                            ))
                            ->join(array('B' => 'WPM_WORegister'), "A.WORegisterId=B.WORegisterId", array(), $select1::JOIN_LEFT)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array(), $select1::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'A.ResourceId=D.ResourceId', array('Code', 'ResourceName','ResourceId'), $select1::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'C.UnitId=F.UnitId', array('UnitName'), $select1::JOIN_LEFT)
                            ->join(array('E' => 'vendor_master'), 'B.VendorId=E.VendorId', array('VendorName'), $select1::JOIN_LEFT);
                        if ($BillType == "IOW") {
                            $ResourceId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId"));

                        } elseif ($BillType == "Activity") {
                            $IOWId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.IOWId=$IOWId"));

                        } elseif ($BillType == '') {
                            $ResourceId = 0;
                            $IOWId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId And A.IOWId=$IOWId"));

                        }
                        $statement = $sql->getSqlStringForSqlObject($select1);
                        $OrderBillType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($OrderBillType));
                        return $response;
                        break;
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
//            $fromDat = $this->params()->fromRoute('dateFrom');
//            $toDat = $this->params()->fromRoute('dateTo');
//            $BillType = $this->params()->fromRoute('BillType');
//            $CostCentreId = $this->params()->fromRoute('CostCentre');

//            $this->_view->OrderBillType=$OrderBillType;
//            $fromDate="2010-05-05";
//            $toDate="2017-01-05";
//            $BillType="IOW";
//            $CostCentreId=4;

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
    public function WorkProgressTypeAction(){
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
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
                $BillType= $this->bsf->isNullCheck($postParams['BillType'],'string');

                if($CostCentreId == ""){
                    $CostCentreId =0;
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
                        $select1->from(array('A' => 'WPM_DPETrans'))
                            ->columns(array(
                                'DPERegisterId' => new Expression('A.DPERegisterId'),
                                'DPETransId' => new Expression('A.DPETransId'),
                                'DPEDate' => new Expression('Convert(Varchar(10),B.DPEDate,103)'),
                                'Specification' => new Expression('C.Specification'),
                                'DPENo' => new Expression("B.DPENo"),
                                'SerialNo' => new Expression("C.RefSerialNo"),
                                'CostCentreID' => new Expression("B.CostCentreID"),
                                'Qty' => new Expression("CAST(A.Qty As Decimal(18,6))"),
                                'Rate' => new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'Amount' => new Expression("CAST(A.Amount As Decimal(18,3))")
                            ))
                            ->join(array('B' => 'WPM_DPERegister'), "A.DPERegisterId=B.DPERegisterId", array(), $select1::JOIN_LEFT)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array(), $select1::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'A.ResourceId=D.ResourceId', array('Code', 'ResourceName'), $select1::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'C.UnitId=F.UnitId', array('UnitName'), $select1::JOIN_LEFT)
                            ->join(array('E' => 'vendor_master'), 'B.VendorId=E.VendorId', array('VendorName'), $select1::JOIN_LEFT);
                        if ($BillType == "IOW") {
                            $ResourceId = 0;
                            $select1->where(array("B.DPEDate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId"));

                        } elseif ($BillType == "Activity") {
                            $IOWId = 0;
                            $select1->where(array("B.DPEDate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.IOWId=$IOWId"));

                        } elseif ($BillType == '') {
                            $ResourceId = 0;
                            $IOWId = 0;
                            $select1->where(array("B.DPEDate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId And A.IOWId=$IOWId"));

                        }
                        $statement = $sql->getSqlStringForSqlObject($select1);
                        $OrderBillType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($OrderBillType));
                        return $response;
                        break;
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
//            $fromDat = $this->params()->fromRoute('dateFrom');
//            $toDat = $this->params()->fromRoute('dateTo');
//            $BillType = $this->params()->fromRoute('BillType');
//            $CostCentreId = $this->params()->fromRoute('CostCentre');

//            $fromDate="2010-05-05";
//            $toDate="2017-01-05";
//            $BillType="IOW";
//            $CostCentreId=4;

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
    public function WorkOrderBillAction(){
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
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
                $BillType= $this->bsf->isNullCheck($postParams['BillType'],'string');

                if($CostCentreId == ""){
                    $CostCentreId =0;
                }
//                if($fromDat == ''){
//                    $fromDat =  0;
//                }
//                if($toDat == ''){
//                    $toDat = 0;
//                }
//
//                if($fromDat == 0){
//                    $fromDate =  0;
//                }
//                if($toDat == 0){
//                    $toDate = 0;
//                }
//                if($fromDat == 0) {
//                    $fromDate = date('Y-m-d', strtotime(Date('d-m-Y')));
//                }
//                else
//                {
//                    $fromDate=date('Y-m-d',strtotime($fromDat));
//                }
//                if($toDat == 0) {
//                    $toDate = date('Y-m-d', strtotime(Date('d-m-Y')));
//                }
//                else
//                {
//                    $toDate =date('Y-m-d',strtotime($toDat));
//                }
                switch($Type) {
                    case 'cost':
                        /*$select1 = $sql->select();
                        $select1->from(array('A' => 'wpm_workbilltrans'))
                            ->columns(array(
                                'BillRegisterId' => new Expression('A.BillRegisterId'),
                                'WorkBillTransId' => new Expression('A.WorkBillTransId'),
                                'BillDate' => new Expression('Convert(Varchar(10),B.BillDate,103)'),

                                'Specification' => new Expression('C.Specification'),
                                'SerialNo' => new Expression("C.RefSerialNo"),
                                'CostCentreID' => new Expression("B.CostCentreID"),
                                'IOWId' => new Expression("C.IOWId"),
                                'Code' => new Expression("D.Code"),
                                'ResourceName' => new Expression("D.ResourceName"),
                                'ResourceId' => new Expression("D.ResourceId"),
                                'UnitName' => new Expression("F.UnitName"),
                                'WORegisterId' => new Expression("G.WORegisterId"),
                                'WOTransId' => new Expression("G.WOTransId"),
                                'VendorName' => new Expression("E.VendorName"),
                                'bQty' => new Expression("CAST(A.CurQty As Decimal(18,6))"),
                                'bRate' => new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'bAmount' => new Expression("CAST(A.CurAmount As Decimal(18,3))"),
                                'Qty' => new Expression("CAST(0 As Decimal(18,6))"),
                                'Rate' => new Expression("CAST(0 As Decimal(18,3))"),
                                'Amount' => new Expression("CAST(0 As Decimal(18,3))"),
                                'tQty' => new Expression("CAST(0 As Decimal(18,6))")
                            ))
                            ->join(array('B' => 'wpm_workbillregister'), "A.BillRegisterId=B.BillRegisterId", array(), $select1::JOIN_INNER)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array(), $select1::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'A.ResourceId=D.ResourceId', array(), $select1::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'C.UnitId=F.UnitId', array(), $select1::JOIN_LEFT)
                            ->join(array('G' => 'WPM_WOTrans'), 'G.WORegisterId=B.WORegisterId', array(), $select1::JOIN_INNER)
                            ->join(array('E' => 'vendor_master'), 'B.VendorId=E.VendorId', array(), $select1::JOIN_LEFT);

                        $select2 = $sql->select();
                        $select2->from(array('H' => $select1))
                            ->columns(array(
                                'BillRegisterId' => new Expression('H.BillRegisterId'),
                                'WorkBillTransId' => new Expression('H.WorkBillTransId'),
                                'BillDate' => new Expression('H.BillDate'),
                                'Specification' => new Expression('H.Specification'),
                                'SerialNo' => new Expression("H.SerialNo"),
                                'CostCentreID' => new Expression("H.CostCentreID"),
                                'IOWId' => new Expression("H.IOWId"),
                                'Code' => new Expression("H.Code"),
                                'ResourceName' => new Expression("H.ResourceName"),
                                'ResourceId' => new Expression("H.ResourceId"),
                                'UnitName' => new Expression("H.UnitName"),
                                'WORegisterId' => new Expression("H.WORegisterId"),
                                'WOTransId' => new Expression("H.WOTransId"),
                                'VendorName' => new Expression("H.VendorName"),
                                'bQty' => new Expression("H.bQty"),
                                'bRate' => new Expression("H.bRate"),
                                'bAmount' => new Expression("H.bAmount"),
                                'Qty' => new Expression("CAST(A.Qty As Decimal(18,6))"),
                                'Rate' => new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'Amount' => new Expression("CAST(A.Amount As Decimal(18,3))"),
                                'tQty' => new Expression('CAST(A.Qty-H.bQty As Decimal(18,6))')
                                //'tQty' => new Expression('CAST(H.bQty-H.Qty As Decimal(18,6))')
                            ))
                            ->join(array('B' => 'WPM_WORegister'), "B.WORegisterId=H.WORegisterId", array('WONo'), $select2::JOIN_INNER)
                            ->join(array('A' => 'WPM_WOTrans'), "A.WORegisterId=H.WORegisterId", array(), $select2::JOIN_INNER)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'H.IOWId=C.ProjectIOWId', array(), $select2::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'H.ResourceId=D.ResourceId', array(), $select2::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'C.UnitId=F.UnitId', array(), $select2::JOIN_LEFT)
                            ->join(array('E' => 'vendor_master'), 'B.VendorId=E.VendorId', array(), $select2::JOIN_LEFT);
                        if ($BillType == "IOW") {
                            $ResourceId = 0;
                            $select2->where(array("B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId"));

                        } elseif ($BillType == "Activity") {
                            $IOWId = 0;
                            $select2->where(array(" B.CostCentreId IN ($CostCentreId) And A.IOWId=$IOWId"));

                        } elseif ($BillType == '') {
                            $ResourceId = 0;
                            $IOWId = 0;
                            $select2->where(array("B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId And A.IOWId=$IOWId"));

                        }
                        $statement = $sql->getSqlStringForSqlObject($select2);*/

                        $subQuery = $sql->select();
                        $subQuery->from(array('A' => 'wpm_workbilltrans'))
                            ->columns(array(
                                'WORegisterId' =>new Expression("Distinct B.WORegisterId "),
                                'BQty'=>new Expression("Sum(CAST(A.CurQty As Decimal(18,6)))"),
                                'BRate'=>new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'BAmount'=>new Expression("Sum(CAST(A.CurAmount As Decimal(18,3)))")
                            ))
                            ->join(array('B' => 'wpm_workbillregister'), 'A.BillRegisterId=B.BillRegisterId', array(), $subQuery::JOIN_INNER)
                            ->group(array("B.WORegisterId","A.Rate"));


//
                        $select1 = $sql->select();
                        $select1->from(array('A' => 'WPM_WORegister'))
                            ->columns(array(
                                'CostCentreID' => new Expression('A.CostCentreId'),
                                'WORegisterId' => new Expression('B.WORegisterId'),
                                'SerialNo' => new Expression("isnull(C.SerialNo,'')"),
                                'Specification' => new Expression("isnull(C.Specification,'')"),
                                'Code' => new Expression("isnull(D.Code,'')"),
                                'ResourceName' => new Expression("isnull(D.ResourceName,'')"),
                                'UnitName' => new Expression("isnull(F.UnitName,'')"),
                                'Qty' => new Expression("isnull(B.Qty,0)"),
                                'Rate' => new Expression("isnull(B.Rate,0)"),
                                'Amount' => new Expression("isnull(B.Amount,0)"),
                                'VendorName' => new Expression("isnull(E.VendorName,'')"),
                                'bQty' => new Expression("isnull(H.BQty,0)"),
                                'bRate' => new Expression("isnull(H.BRate,0)"),
                                'bAmount' => new Expression("isnull(H.BAmount,0)"),
                                'WONo'=> new Expression('A.WONo'),
                                'tQty' => new Expression("isnull((B.Qty-H.BQty),0)"),
                            ))
                            ->join(array('B' => 'WPM_WOTrans'), 'A.WORegisterId=B.WORegisterId', array(), $select1::JOIN_INNER)
                            ->join(array('E' => 'vendor_master'), "A.VendorId=E.VendorId", array(), $select1::JOIN_LEFT)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'B.IOWId=C.ProjectIOWId', array(), $select1::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'B.ResourceId=D.ResourceId', array(), $select1::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'C.UnitId=F.UnitId', array(), $select1::JOIN_LEFT)
                            ->join(array('H' => $subQuery), 'A.WORegisterId= H.WORegisterId', array(), $select1::JOIN_LEFT);
                        if ($BillType == "IOW") {
                            $ResourceId = 0;
                            $select1->where(array("A.CostCentreId=1 AND A.WOType='iow' AND A.LiveWO=1"));

                        } elseif ($BillType == "Activity") {
                            $IOWId = 0;
                            $select1->where(array(" A.CostCentreId=1 AND A.WOType='activity' AND A.LiveWO=1"));
                        }
                        $statement = $sql->getSqlStringForSqlObject($select1);
                        $OrderBillType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                       // print_r($OrderBillType);die;
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($OrderBillType));
                        return $response;
                        break;
                       // B.WODate Between ('$fromDate') AND ('$toDate') And
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
            $fromDat = $this->params()->fromRoute('dateFrom');
            $toDat = $this->params()->fromRoute('dateTo');
            $BillType = $this->params()->fromRoute('BillType');
            $CostCentreId = $this->params()->fromRoute('CostCentre');

//            $fromDate="2010-05-05";
//            $toDate="2017-01-05";
//            $BillType="IOW";
//            $CostCentreId=4;


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
    public function OrderBillProgressAction(){
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
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
                $BillType= $this->bsf->isNullCheck($postParams['BillType'],'string');

                if($CostCentreId == ""){
                    $CostCentreId =0;
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
                        $select1->from(array('A' => 'WPM_WOTrans'))
                            ->columns(array(
                                'WORegisterId' => new Expression('A.WORegisterId'),
                                'WOTransId' => new Expression('A.WOTransId'),
                                'WODate' => new Expression('Convert(Varchar(10),B.WODate,103)'),
                                'Specification' => new Expression('C.Specification'),
                                'WONo' => new Expression("B.WONo"),
                                'SerialNo' => new Expression("C.RefSerialNo"),
                                'CostCentreID' => new Expression("B.CostCentreID"),
                                'Qty' => new Expression("CAST(A.Qty As Decimal(18,6))"),
                                'DPEQty' => new Expression("CAST(X.Qty As Decimal(18,6))"),
                                'Rate' => new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'Amount' => new Expression("CAST(A.Amount As Decimal(18,3))")
                            ))
                            ->join(array('B' => 'WPM_WORegister'), "A.WORegisterId=B.WORegisterId", array(), $select1::JOIN_INNER)
                            ->join(array('Z' => 'wpm_workbillregister'), "Z.WORegisterId=A.WORegisterId", array('BillRegisterId'), $select1::JOIN_INNER)
                            ->join(array('Y' => 'WPM_DPERegister'), "Y.WORegisterId=A.WORegisterId", array('DPERegisterId'), $select1::JOIN_INNER)
                            ->join(array('W' => 'wpm_workbilltrans'), "Z.BillRegisterId=W.BillRegisterId", array('CurQty'), $select1::JOIN_INNER)
                            ->join(array('X' => 'WPM_DPETrans'), "Y.DPERegisterId=X.DPERegisterId", array(), $select1::JOIN_INNER)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array(), $select1::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'A.ResourceId=D.ResourceId', array('Code', 'ResourceName','ResourceId'), $select1::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'D.UnitId=F.UnitId', array('UnitName'), $select1::JOIN_LEFT)
                            ->join(array('E' => 'vendor_master'),'B.VendorId=E.VendorId', array('VendorName'), $select1::JOIN_LEFT);
//                ->join(array('I' => 'WF_OperationalCostCentre'), 'I.CostCentreId=B.CostCentreID', array('CostCentreName'), $select1::JOIN_LEFT);
                        if ($BillType == "IOW") {
                            $ResourceId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId"));

                        } elseif ($BillType == "Activity") {
                            $IOWId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.IOWId=$IOWId"));

                        } elseif ($BillType == '') {
                            $ResourceId = 0;
                            $IOWId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId And A.IOWId=$IOWId"));

                        }
                        $statement = $sql->getSqlStringForSqlObject($select1);
                        $OrderBillType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($OrderBillType));
                        return $response;
                        break;
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
//            $fromDat = $this->params()->fromRoute('dateFrom');
//            $toDat = $this->params()->fromRoute('dateTo');
//            $BillType = $this->params()->fromRoute('BillType');
//            $CostCentreId = $this->params()->fromRoute('CostCentre');

//            $this->_view->OrderBillType=$OrderBillType;
//            $fromDate="2010-05-05";
//            $toDate="2017-01-05";
//            $BillType="IOW";
//            $CostCentreId=4;

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
    public function WorkOrderHistoryAction(){
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
                $fromDat= $this->bsf->isNullCheck($postParams['fromDate'],'string');
                $toDat= $this->bsf->isNullCheck($postParams['toDate'],'string');
                $BillType= $this->bsf->isNullCheck($postParams['BillType'],'string');

                if($CostCentreId == ""){
                    $CostCentreId =0;
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
                        $select1->from(array('A' => 'WPM_WOTrans'))
                            ->columns(array(
                                'WORegisterId' => new Expression('A.WORegisterId'),
                                'WOTransId' => new Expression('A.WOTransId'),
                                'WODate' => new Expression('Convert(Varchar(10),B.WODate,103)'),
                                'Specification' => new Expression('C.Specification'),
                                'WONo' => new Expression("B.WONo"),
                                'SerialNo' => new Expression("C.RefSerialNo"),
                                'CostCentreID' => new Expression("B.CostCentreID"),
                                'WOQty' => new Expression("CAST(A.Qty As Decimal(18,6))"),
                                'WORate' => new Expression("CAST(A.Rate As Decimal(18,3))"),
                                'WOAmount' => new Expression("CAST(A.Amount As Decimal(18,3))"),
                                'DPEQty' => new Expression("CAST(X.Qty As Decimal(18,6))"),
                                'DPERate' => new Expression("CAST(X.Rate As Decimal(18,3))"),
                                'DPEAmount' => new Expression("CAST(X.Amount As Decimal(18,3))"),
                                'BillQty' => new Expression("CAST(W.CurQty As Decimal(18,6))"),
                                'BillRate' => new Expression("CAST(W.Rate As Decimal(18,3))"),
                                'BillAmount' => new Expression("CAST(W.CurAmount As Decimal(18,3))"),
                                'EstQty' => new Expression("CAST(0 As Decimal(18,6))"),
                                'EstRate' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstAmount' => new Expression("CAST(0 As Decimal(18,3))"),
                            ))
                            ->join(array('B' => 'WPM_WORegister'), "A.WORegisterId=B.WORegisterId", array(), $select1::JOIN_INNER)
                            ->join(array('Z' => 'wpm_workbillregister'), "Z.WORegisterId=A.WORegisterId", array('BillRegisterId','BillNo'), $select1::JOIN_INNER)
                            ->join(array('Y' => 'WPM_DPERegister'), "Y.WORegisterId=A.WORegisterId", array('DPERegisterId','DPENo'), $select1::JOIN_INNER)
                            ->join(array('W' => 'wpm_workbilltrans'), "Z.BillRegisterId=W.BillRegisterId", array('WorkBillTransId'), $select1::JOIN_INNER)
                            ->join(array('X' => 'WPM_DPETrans'), "Y.DPERegisterId=X.DPERegisterId", array('DPETransId'), $select1::JOIN_INNER)
                            ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array(), $select1::JOIN_LEFT)
                            ->join(array('D' => 'proj_resource'), 'A.ResourceId=D.ResourceId', array('Code', 'ResourceName','ResourceId'), $select1::JOIN_LEFT)
                            ->join(array('F' => 'Proj_Uom'), 'C.UnitId=F.UnitId', array('UnitName'), $select1::JOIN_LEFT)
                            ->join(array('E' => 'vendor_master'), 'B.VendorId=E.VendorId', array('VendorName'), $select1::JOIN_LEFT);
                        if ($BillType == "IOW") {
                            $ResourceId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId"));

                        } elseif ($BillType == "Activity") {
                            $IOWId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.IOWId=$IOWId"));

                        } elseif ($BillType == '') {
                            $ResourceId = 0;
                            $IOWId = 0;
                            $select1->where(array("B.WODate Between ('$fromDate') AND ('$toDate') And B.CostCentreId IN ($CostCentreId) And A.ResourceId=$ResourceId And A.IOWId=$IOWId"));

                        }
                        $statement = $sql->getSqlStringForSqlObject($select1);
                        $OrderBillType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($OrderBillType));
                        return $response;
                        break;
                }

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
//            $fromDat = $this->params()->fromRoute('dateFrom');
//            $toDat = $this->params()->fromRoute('dateTo');
//            $BillType = $this->params()->fromRoute('BillType');
//            $CostCentreId = $this->params()->fromRoute('CostCentre');

//            $this->_view->OrderBillType=$OrderBillType;
//            $fromDate="2010-05-05";
//            $toDate="2017-01-05";
//            $BillType="IOW";
//            $CostCentreId=4;
//
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
    public function VoucherAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );

        $request = $this->getRequest();
        if($type=="work"){
            $dir = 'public/reports/newtemp/'. $UserId;
            $filePath = $dir.'/v1_template.phtml';
        }
        else if($type=="hire"){
            $dir = 'public/reports/hire/'. $UserId;
            $filePath = $dir.'/v2_template.phtml';
        }
        else if($type=="service"){
            $dir = 'public/reports/service/'. $UserId;
            $filePath = $dir.'/v3_template.phtml';
        }
        else if($type=="workprogress"){
            $dir = 'public/reports/workprogress/'. $UserId;
            $filePath = $dir.'/v4_template.phtml';
        }
        else if($type=="workbill"){
            $dir = 'public/reports/workbill/'. $UserId;
            $filePath = $dir.'/v5_template.phtml';
        }
        else if($type=="hirebill"){
            $dir = 'public/reports/hirebill/'. $UserId;
            $filePath = $dir.'/v6_template.phtml';
        }
        else if($type=="servicebill"){
            $dir = 'public/reports/servicebill/'. $UserId;
            $filePath = $dir.'/v7_template.phtml';
        }


        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
			if(!is_dir($dir)){
				mkdir($dir);
			}
            file_put_contents($filePath, $content);
            if($type=="work" ||$type=="hire" ||$type=="service"){
                $this->redirect()->toRoute("wpm/default", array("controller" => "workorder","action" => "register"));
            } else if($type=="workprogress"){
                $this->redirect()->toRoute("wpm/default", array("controller" => "workprogress","action" => "register"));
            } else if($type=="workbill" ||$type=="hirebill" |$type=="servicebill"){
                $this->redirect()->toRoute("wpm/default", array("controller" => "workorder","action" => "bill-register"));
            }
// else if($type=="receipt"){
//                $this->redirect()->toRoute("cb/receipt", array("controller" => "receipt","action" => "register"));
//            }
        } else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
//            if($type != 'workorder' && $type != 'rabill' && $type != 'receipt')
//                $this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );

            if (!file_exists($filePath)) {
                if($type=="work"){
                    $filePath = 'public/reports/newtemp/template.phtml';
                }
                else if($type=="hire"){
                    $filePath = 'public/reports/hire/template.phtml';
                }
                else if($type=="service"){
                    $filePath = 'public/reports/service/template.phtml';
                }
                else if($type=="workprogress"){
                    $filePath = 'public/reports/workprogress/template.phtml';
                }
                else if($type=="workbill"){
                    $filePath = 'public/reports/workbill/template.phtml';
                }
                else if($type=="hirebill"){
                    $filePath = 'public/reports/hirebill/template.phtml';
                }
                else if($type=="servicebill"){
                    $filePath = 'public/reports/servicebill/template.phtml';
                }
            }
            $this->_view->type = $type;
//            print_r($filePath);die;

            $template = file_get_contents($filePath);
            $this->_view->template = $template;

            // csrf Key
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        }
        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
    public function footerAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || Client Billing");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $UserId = $this->auth->getIdentity()->UserId;
        $type = $this->bsf->isNullCheck( $this->params()->fromRoute('type' ), 'string' );

        $request = $this->getRequest();
        if($type=="work1"){
            $dir1 = 'public/reports/newtemp/'. $UserId;
            $filePath1 = $dir1.'/f1_template.phtml';
        }
        else if($type=="hire1"){
            $dir1 = 'public/reports/hire/'. $UserId;
            $filePath1 = $dir1.'/f2_template.phtml';
        }
        else if($type=="service1"){
            $dir1 = 'public/reports/service/'. $UserId;
            $filePath1 = $dir1.'/f3_template.phtml';
        }
        else if($type=="workprogress1"){
            $dir1 = 'public/reports/workprogress/'. $UserId;
            $filePath1 = $dir1.'/f4_template.phtml';
        }
        else if($type=="workbill1"){
            $dir1 = 'public/reports/workbill/'. $UserId;
            $filePath1 = $dir1.'/f5_template.phtml';
        }
        else if($type=="hirebill1"){
            $dir1 = 'public/reports/hirebill/'. $UserId;
            $filePath1 = $dir1.'/f6_template.phtml';
        }
        else if($type=="servicebill1"){
            $dir1 = 'public/reports/servicebill/'. $UserId;
            $filePath1 = $dir1.'/f7_template.phtml';
        }


        if ($request->isPost()) {
            $content=$request->getPost('htmlcontent');
            if(!is_dir($dir1)){
                mkdir($dir1);
            }
            file_put_contents($filePath1, $content);

            if($type=="work1" ||$type=="hire1" ||$type=="service1"){
                $this->redirect()->toRoute("wpm/default", array("controller" => "workorder","action" => "register"));
            } else if($type=="workprogress1"){
                $this->redirect()->toRoute("workprogress/register", array("controller" => "workprogress","action" => "register"));
            } else if($type=="workbill1" ||$type=="hirebill1" |$type=="servicebill1"){
                $this->redirect()->toRoute("workorder/bill-register", array("controller" => "workorder","action" => "bill-register"));
            }
// else if($type=="receipt"){
//                $this->redirect()->toRoute("cb/receipt", array("controller" => "receipt","action" => "register"));
//            }
        } else {
            $type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );
//            if($type != 'workorder' && $type != 'rabill' && $type != 'receipt')
//                $this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );

            if (!file_exists($filePath1)) {
                if($type=="work1"){
                    $filePath1 = 'public/reports/newtemp/template1.phtml';
                }
                else if($type=="hire1"){
                    $filePath1 = 'public/reports/hire/template1.phtml';
                }
                else if($type=="service1"){
                    $filePath1 = 'public/reports/service/template1.phtml';
                }
                else if($type=="workprogress1"){
                    $filePath1 = 'public/reports/workprogress/template1.phtml';
                }
                else if($type=="workbill1"){
                    $filePath1 = 'public/reports/workbill/template1.phtml';
                }
                else if($type=="hirebill1"){
                    $filePath1 = 'public/reports/hirebill/template1.phtml';
                }
                else if($type=="servicebill1"){
                    $filePath1 = 'public/reports/servicebill/template1.phtml';
                }
            }
            $this->_view->type = $type;
//            print_r($this->_view->type);die;

            $template = file_get_contents($filePath1);
            $this->_view->footertemplate = $template;

            // csrf Key
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        }
        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }

}