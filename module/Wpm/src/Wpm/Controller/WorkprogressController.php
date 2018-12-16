<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Wpm\Controller;

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

class WorkprogressController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function indexAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Progress");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                $this->_view->setTerminal(true);
                $postParams = $request->getPost();

                $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                $VendorId = $this->bsf->isNullCheck($postParams['VendorId'],'number');
                $WOId = $this->bsf->isNullCheck($postParams['WOId'],'number');
                switch($Type) {
                    case 'getworkorder':
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WORegister"))
                            ->columns(array('WONo','WORegisterId','WOType',"WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")))
                            ->where("a.DeleteFlag=0 AND a.VendorId=$VendorId AND a.CostCentreId=$CostCentreId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($result));
                        return $response;
                        break;
                    case 'resourcelist':
                        $WorkType= $this->bsf->isNullCheck($postParams['WorkType'],'string');
                        $WORegisterId= $this->bsf->isNullCheck($postParams['WORegisterId'],'number');

                        $whereCond = "c.VendorId=$VendorId AND c.CostCentreId=$CostCentreId AND c.WOType='$WorkType'";
                        if($WORegisterId != 0)
                            $whereCond = "c.VendorId=$VendorId AND c.CostCentreId=$CostCentreId AND c.WORegisterId=$WORegisterId AND c.WOType='$WorkType'";

                        if($WorkType == 'turn-key') {
                            $select = $sql->select();
                            $select->from(array("a" => "WPM_WOTurnKeyTrans"))
                                ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('Desc' => new Expression("b.ParentText + '->' + b.WBSName")), $select::JOIN_LEFT)
                                ->join(array('c' => 'WPM_WORegister'), 'a.WORegisterId=c.WORegisterId', array(), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array('UnitName'), $select::JOIN_LEFT)
                                ->columns(array( 'DescId' => 'WOTurnKeyTransId', 'Rate','Include' => new Expression('1-1')))
                                ->where($whereCond);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        } else {
                            $select = $sql->select();
                            $select->from(array("a" => "WPM_WOTrans"));

                            if ($WorkType == 'activity') {
                                $select->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('DescId' => 'ResourceId', 'Desc' => new Expression("b.Code + ' ' + b.ResourceName"), 'Rate'), $select::JOIN_LEFT);
                                $select->group(new Expression('b.ResourceId,b.Code,b.ResourceName,b.Rate,d.UnitName'));
                            } else if ($WorkType == 'iow') {
                                $select->join(array('b' => 'Proj_ProjectIOWMaster'), 'b.ProjectIOWId=a.IOWId', array('DescId' => 'ProjectIOWId', 'Desc' => new Expression("b.RefSerialNo + ' ' + b.Specification"), 'Rate'), $select::JOIN_LEFT);
                                $select->group(new Expression('b.ProjectIOWId,b.RefSerialNo,b.Specification,d.UnitName,b.Rate'));
                                $whereCond .= ' AND b.UnitId <> 0';
                            }

                            $select->join(array('c' => 'WPM_WORegister'), 'a.WORegisterId=c.WORegisterId', array(), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName'), $select::JOIN_LEFT)
                                ->columns(array('Include' => new Expression('1-1')))
                                ->where($whereCond);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        }
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($result));
                        return $response;
                        break;
                    case 'getworkorderdate':
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WORegister"))
                            ->columns(array("FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
                            ->where("a.DeleteFlag=0 AND a.WORegisterId=$WOId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($result));
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
            // get cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // vendor details
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                ->where(array('Contract' => '1'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function entryAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Progress");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        $editId = $this->bsf->isNullCheck($this->params()->fromRoute('editId'), 'number');

        if(!$this->getRequest()->isXmlHttpRequest() && $editId == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('wpm/workprogress-entry', array('controller' => 'workprogress', 'action' => 'index'));
        }

        $this->_view->wbsReq = '';

        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $projectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number');
                $response = $this->getResponse();
                switch($Type) {
                    case 'getresourcedetails':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectDetails"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('ProjectIOWId','RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=e.ProjectIOWId', array('bQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.ProjectIOWId=c.ProjectIOWId', array('cQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=c.WBSId', array('WBSId', 'WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ResourceId', 'Qty'))
                            ->where("a.ResourceId=$ResourceId")
                            ->where("a.ProjectId=$projectId")
                            ->where('c.WBSId != 0');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        return $response;
                        break;
                    case 'getiowdetails':
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSTrans"))
                            ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId', 'WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ProjectIOWId', 'Qty'))
                            ->where("a.ProjectIOWId=$IOWId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        return $response;
                        break;
                    case 'getwbsdetails':
                        $WOTurnKeyTransId = $this->bsf->isNullCheck($this->params()->fromPost('WOTurnKeyTransId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTurnKeyIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('ProjectIOWId','RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId','UnitName'), $select::JOIN_LEFT)
                            ->join(array('d' => 'WPM_WOTurnKeyTrans'), 'a.WOTurnKeyTransId=d.WOTurnKeyTransId', array('Variance'), $select::JOIN_LEFT)
                            ->columns(array('Qty', 'Rate', 'Amount'))
                            ->where("a.WOTurnKeyTransId=$WOTurnKeyTransId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_projectiows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_projectiows));
                        return $response;
                        break;
                    case 'getwoqty':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ccId = $this->bsf->isNullCheck($this->params()->fromPost('ccId'), 'number');
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');
                        $WBSId = $this->bsf->isNullCheck($this->params()->fromPost('WBSId'), 'number');
                        $WOType = $this->bsf->isNullCheck($this->params()->fromPost('WOType'), 'string');
                        $woRegId = $this->bsf->isNullCheck($this->params()->fromPost('woRegId'), 'number');

                        $aTable = '';
                        if($WOType == 'activity') {
                            $aTable = 'WPM_WOIOWTrans';
                            $aaTable = 'WPM_WorkBillIOWTrans';
                            $dpTable = 'WPM_DPEIOWTrans';
                        } else if($WOType == 'iow') {
                            $aTable = 'WPM_WOWBSTrans';
                            $aaTable = 'WPM_WorkBillWBSTrans';
                            $dpTable = 'WPM_DPEWBSTrans';
                        }

                        $wobArr = array();
                        $select = $sql->select();
                        $select->from(array("a" => $aTable))
                            ->join(array('b' => 'WPM_WOTrans'), 'a.WOTransId=b.WOTransId', array(), $select::JOIN_LEFT)
                            ->columns(array('WOQty' => new Expression("SUM(a.Qty)")))
                            ->where("b.WORegisterId=$woRegId");
                        if($WOType == 'activity') {
                            $select->where("a.IOWId=$IOWId");
                            $select->where("a.WBSId=$WBSId");
                            $select->where("b.ResourceId=$ResourceId");
                        } else if($WOType == 'iow') {
                            $select->where("a.WBSId=$WBSId");
                            $select->where("b.IOWId=$ResourceId");
                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wo_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WOQty'] = $wo_qty['WOQty'];

                        $select = $sql->select();
                        $select->from(array("a" => $aaTable))
                            ->join(array('b' => 'WPM_WorkBillTrans'), 'a.WorkBillTransId=b.WorkBillTransId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_WorkBillRegister'), 'b.BillRegisterId=c.BillRegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WBQty' => new Expression("SUM(a.Qty)")))
                            //->where("c.CostCentreId=$ccId");
                            ->where("c.WORegisterId=$woRegId");
                        if($WOType == 'activity') {
                            $select->where("a.IOWId=$IOWId");
                            $select->where("b.ResourceId=$ResourceId");
                        } else if($WOType == 'iow') {
                            $select->where("a.WBSId=$WBSId");
                            $select->where("b.IOWId=$ResourceId");
                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wb_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WBQty'] = $wb_qty['WBQty'];

                        $select = $sql->select();
                        $select->from(array("a" => $dpTable))
                            ->join(array('b' => 'WPM_DPETrans'), 'a.DPETransId=b.DPETransId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_DPERegister'), 'b.DPERegisterId=c.DPERegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WPQty' => new Expression("SUM(a.Qty)")))
                            //->where("c.CostCentreId=$ccId");
                            ->where("c.WORegisterId=$woRegId");
                        if($WOType == 'activity') {
                            $select->where("a.IOWId=$IOWId");
                            $select->where("a.WBSId=$WBSId");
                            $select->where("b.ResourceId=$ResourceId");
                        } else if($WOType == 'iow') {
                            $select->where("a.WBSId=$WBSId");
                            $select->where("b.IOWId=$ResourceId");
                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wp_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WPQty'] = $wp_qty['WPQty'];

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($wobArr));
                        return $response;
                        break;
                    case 'getqtywio':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ccId = $this->bsf->isNullCheck($this->params()->fromPost('ccId'), 'number');
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');
                        $woRegId = $this->bsf->isNullCheck($this->params()->fromPost('woRegId'), 'number');

                        $whereCond = array("c.CostCentreId"=>$ccId);
                        if($ResourceId != 0) {
                            $whereCond['a.ResourceId'] = $ResourceId;
                            $aTable = 'WPM_WOIOWTrans';
                            $aaTable = 'WPM_WorkBillIOWTrans';
                        } else if($IOWId != 0) {
                            $whereCond['a.IOWId'] = $IOWId;
                            $aTable = 'WPM_WOWBSTrans';
                            $aaTable = 'WPM_WorkBillWBSTrans';
                        }

                        $woCond = array("a.WORegisterId"=>$woRegId);
                        $wobArr = array();
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"))
                            //->join(array('b' => $aTable), 'a.WOTransId=b.WOTransId', array('WOQty' => new Expression("SUM(b.Qty)")), $select::JOIN_LEFT)
                            //->join(array('c' => 'WPM_WORegister'), 'a.WORegisterId=c.WORegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WOQty' => new Expression("SUM(a.Qty)")))
                            ->where($whereCond)
                            ->where($woCond);
                            //->group(new Expression('a.WOTransId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wo_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WOQty'] = $wo_qty['WOQty'];

                        $wpCond = array("c.WORegisterId"=>$woRegId);
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WorkBillTrans"))
                            //->join(array('b' => $aaTable), 'a.WorkBillTransId=b.WorkBillTransId', array('WBQty' => new Expression("SUM(b.Qty)")), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=c.BillRegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WBQty' => new Expression("SUM(a.CurQty)")))
                            ->where($whereCond)
                            ->where($wpCond);
                            //->group(new Expression('a.WorkBillTransId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wb_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WBQty'] = $wb_qty['WBQty'];

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPETrans"))
                            //->join(array('b' => $aTable), 'a.WOTransId=b.WOTransId', array('WOQty' => new Expression("SUM(b.Qty)")), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_DPERegister'), 'a.DPERegisterId=c.DPERegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WPQty' => new Expression("SUM(a.Qty)")))
                            ->where($whereCond)
                            ->where($wpCond);
                        //->group(new Expression('a.WOTransId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wp_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WPQty'] = $wp_qty['WPQty'];

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($wobArr));
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
                $postData = $request->getPost();

                $WorkType = $this->bsf->isNullCheck($postData['WorkType'], 'string');
                if (!is_null($postData['frm_index'])) {
                    $CostCentre = $this->bsf->isNullCheck($postData['costCentreId'], 'number');
                    $VendorId = $this->bsf->isNullCheck($postData['VendorId'], 'number');
                    $WorkType= $this->bsf->isNullCheck($postData['WorkType'],'string');
                    $WORegisterId= $this->bsf->isNullCheck($postData['WorkorderId'],'number');

                    $resourceIds = $postData['resourceIds'];

                    if(!is_null($resourceIds) && $resourceIds != '')
                        $resourceIds = trim(implode(',',$resourceIds));
                    else
                        $resourceIds = 0;

                    $FromDate= $this->bsf->isNullCheck($postData['FromDate'],'string');
                    $ToDate = $this->bsf->isNullCheck($postData['ToDate'],'string');

                    // cost center details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                        ->columns( array( 'CostCentreId', 'CostCentreName', 'ProjectId', 'WBSReqWPM', 'CompanyId' ) )
                        ->where( "Deactivate=0 AND CostCentreId=$CostCentre" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $projectId = $this->_view->costcenter['ProjectId'];
                    $this->_view->projectId = $projectId;
                    $this->_view->wbsReq = $this->_view->costcenter['WBSReqWPM'];

                    // vendor details
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId','VendorName','LogoPath'))
                        ->where( "VendorId=$VendorId" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->vendor = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if($WORegisterId != 0) {
                        // workorder details
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WORegister"))
                            ->columns(array('WONo', 'WORegisterId', 'WOType'))
                            ->where("WORegisterId=$WORegisterId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->workorder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }

                    if($resourceIds != '' && $WorkType == 'activity') {
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_Resource"))
                            ->join(array('d' => 'Proj_ProjectResource'), 'a.ResourceId=d.ResourceId', array('EstQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId', 'UnitName'), $select:: JOIN_LEFT)
                            ->columns(array('DescId' => 'ResourceId', "Desc" => new Expression("a.Code + ' ' + a.ResourceName")))
                            ->where('a.ResourceId IN (' . $resourceIds . ')')
                            ->where("d.ProjectId=".$projectId);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectDetails"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=e.ProjectIOWId', array('bQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.ProjectIOWId=c.ProjectIOWId', array('cQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'c.WBSId=d.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ResourceId', 'ProjectIOWId', 'Qty'))
                            ->order('a.ResourceId')
                            ->where('a.ResourceId IN (' . $resourceIds . ')')
                            ->where("a.ProjectId=".$projectId)
                            ->where('c.WBSId != 0');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('value' => new Expression("Code+ ' ' +ResourceName + ' ' + Case When a.RateType='M' Then '- Manual' When a.RateType='A' Then '- Machinery' Else '' End"), 'data' => 'ResourceId'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ProjectResource'), 'b.ResourceId=d.ResourceId', array('Qty'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT)
                            ->columns(array('Rate', 'RateType'))
                            ->order('a.ResourceId')
                            ->where("a.WORegisterId=$WORegisterId")
                            ->where("d.ProjectId=".$projectId);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else if ($resourceIds != '' && $WorkType == 'iow') {
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_ProjectIOWMaster"))
                            ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('EstQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                            ->columns(array('DescId' => 'ProjectIOWId', "Desc" => new Expression("a.RefSerialNo+ ' ' +a.Specification")))
                            ->where('a.ProjectIOWId IN (' . $resourceIds . ')')
                            ->where("a.ProjectId=".$projectId);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSTrans"))
                            ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ProjectIOWId', 'EstQty' => 'Qty'))
                            ->where('a.ProjectIOWId IN (' . $resourceIds . ')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'b.ProjectIOWId=a.IOWId', array('data' => 'ProjectIOWId', 'value' => new Expression("b.RefSerialNo + ' ' + b.Specification"), 'Rate'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOW'), 'b.ProjectIOWId=c.ProjectIOWId', array('Qty'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName'), $select::JOIN_LEFT)
                            ->where("a.WORegisterId=$WORegisterId")
                            ->where("b.UnitId <> 0")
                            ->where("b.ProjectId=".$projectId)
                            ->order("a.IOWId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else if ($resourceIds != '' && $WorkType == 'turn-key') {
                        $select = $sql->select();
                        $select->from(array('a' => "WPM_WOTurnKeyTrans"))
                            ->join(array('c' => 'Proj_WBSMaster'), 'a.WBSId=c.WBSId', array('WBSId',"Desc" => new Expression("c.ParentText + '->' + c.WBSName")), $select::JOIN_LEFT)
                            ->columns(array('DescId' => 'WOTurnKeyTransId', 'WOAmount' => 'Amount', 'Variance'))
                            ->where('a.WOTurnKeyTransId IN (' . $resourceIds . ')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTurnKeyIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('ProjectIOWId','RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('   UnitId','UnitName'), $select::JOIN_LEFT)
                            ->join(array('d' => 'WPM_WOTurnKeyTrans'), 'd.WOTurnKeyTransId=a.WOTurnKeyTransId', array(), $select::JOIN_LEFT)
                            ->columns(array('WOTurnKeyTransId','BOQQty' => 'Qty', 'BOQRate' => 'Rate', 'BOQAmount' => 'Amount'))
                            ->where('d.WOTurnKeyTransId IN (' . $resourceIds . ')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => "WPM_WOTurnKeyTrans"))
                            ->join(array('b' => 'WPM_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSMaster'), 'a.WBSId=c.WBSId', array('WBSId',"value" => new Expression("c.ParentText + '->' + c.WBSName")), $select::JOIN_LEFT)
                            ->columns(array('data' => 'WOTurnKeyTransId',  'WOAmount' => 'Amount', 'Variance'))
                            ->where( "b.VendorId=$VendorId AND b.CostCentreId=$CostCentre AND b.WORegisterId=$WORegisterId AND b.WOType='$WorkType'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    $this->_view->wpTypeId = '823';
                    $this->_view->FromDate = $FromDate;
                    $this->_view->ToDate = $ToDate;
                    $this->_view->WorkType = $WorkType;
                    if($WorkType == 'activity')
                        $this->_view->WorkTypeName = 'Activity';
                    else if($WorkType == 'iow')
                        $this->_view->WorkTypeName = 'IOW';
                    else
                        $this->_view->WorkTypeName = 'Turn Key';
                } else if($editId == 0) {
                    // add entry
                    try {
                        $connection->beginTransaction();
                        $WorkType = $this->bsf->isNullCheck($postData['WorkType'], 'string');

                        $wpaVNo = CommonHelper::getVoucherNo(823, date('Y-m-d', strtotime($postData['DPEDate'])), 0, 0, $dbAdapter, "I");
                        if ($wpaVNo["genType"] == true) {
                            $wpNo = $wpaVNo["voucherNo"];
                        } else {
                            $wpNo = $postData['DPENo'];
                        }

                        $wpccaVNo = CommonHelper::getVoucherNo(823, date('Y-m-d', strtotime($postData['DPEDate'])), 0, $postData['CostCenterId'], $dbAdapter, "I");
                        if ($wpccaVNo["genType"] == true) {
                            $wpCCNo = $wpccaVNo["voucherNo"];
                        } else {
                            $wpCCNo = $postData['CCDPENo'];
                        }

                        $wpcoaVNo = CommonHelper::getVoucherNo(823, date('Y-m-d', strtotime($postData['DPEDate'])), $postData['CompanyId'], 0, $dbAdapter, "I");
                        if ($wpcoaVNo["genType"] == true) {
                            $wpCoNo = $wpcoaVNo["voucherNo"];
                        } else {
                            $wpCoNo = $postData['CompDPENo'];
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_DPERegister');
                        $insert->Values(array('DPEDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['DPEDate'], 'string')))
                        , 'DPENo' => $this->bsf->isNullCheck($wpNo, 'string')
                        , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                        , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                        , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                        , 'CCDPENo' => $this->bsf->isNullCheck($wpCCNo, 'string')
                        , 'CompDPENo' => $this->bsf->isNullCheck($wpCoNo, 'string')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['VendorId'], 'number')
                        , 'WOType' => $WorkType
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCenterId'], 'string')
                        , 'Amount' => $this->bsf->isNullCheck($postData['total'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['Notes'], 'string')
                        , 'WORegisterId' => $this->bsf->isNullCheck($postData['WORegisterId'], 'number')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $DPEId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                        if($WorkType == 'turn-key') {
                            for ($i = 1; $i <= $rowid; $i++) {
                                $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $wbsid = $this->bsf->isNullCheck($postData['wbsid_' . $i], 'number');
                                $cumper = $this->bsf->isNullCheck($postData['cumpercent_' . $i], 'number');
                                $prevper = $this->bsf->isNullCheck($postData['prevpercent_' . $i], 'number');
                                $curper = $this->bsf->isNullCheck($postData['curpercent_' . $i], 'number');

                                if ($DescId == 0 || $wbsid == 0)
                                    continue;

                                $insert = $sql->insert();
                                $insert->into('WPM_DPETurnKeyTrans');
                                $insert->Values(array('DPERegisterId' => $DPEId, 'WBSId' => $wbsid, 'CumPercent' => $cumper,
                                    'PrevPercent' => $prevper, 'CurPercent' => $curper));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $DPETurnKeyTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $iowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $unitid = $this->bsf->isNullCheck($postData['iow_' . $i . '_unitid_' . $j], 'number');
                                    $boqqty = $this->bsf->isNullCheck($postData['iow_' . $i . '_boqqty_' . $j], 'number');
                                    $boqrate = $this->bsf->isNullCheck($postData['iow_' . $i . '_boqrate_' . $j], 'number');
                                    $boqamount = $this->bsf->isNullCheck($postData['iow_' . $i . '_boqamount_' . $j], 'number');
                                    $variance = $this->bsf->isNullCheck($postData['iow_' . $i . '_variance_' . $j], 'number');
                                    $qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    if ($iowid == 0 || $qty == 0)
                                        continue;

                                    $insert = $sql->insert();
                                    $insert->into('WPM_DPETurnKeyIOWTrans');
                                    $insert->Values(array('DPETurnKeyTransId' => $DPETurnKeyTransId, 'IOWId' => $iowid, 'UnitId' => $unitid,
                                        'BOQQty' => $boqqty, 'BOQRate' => $boqrate, 'BOQAmount' => $boqamount, 'Qty' => $qty, 'Rate' => $rate,
                                        'Amount' => $amount, 'Variance' => $variance));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        } else {
                            for ($i = 1; $i <= $rowid; $i++) {
                                $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                                $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');

                                if ($DescId == 0)
                                    continue;

                                if ($WorkType == 'activity') {
                                    $descIdColumn = 'ResourceId';
                                    $fieldName = 'DPEIOWTransId';
                                } else if ($WorkType == 'iow') {
                                    $descIdColumn = 'IOWId';
                                    $fieldName = 'DPEWBSTransId';
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_DPETrans');
                                $insert->Values(array('DPERegisterId' => $DPEId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $DPETransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $WBSId = $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    $measurement = $this->bsf->isNullCheck($postData['iow_' . $i . '_Measurement_' . $j], 'string');
                                    $cellname = $this->bsf->isNullCheck($postData['iow_' . $i . '_CellName_' . $j], 'string');
                                    $SelectedColumns = $this->bsf->isNullCheck($postData['iow_' . $i . '_SelectedColumns_' . $j], 'string');

                                    if (($IOWId == 0 && $WBSId == 0) || $qty == 0 || ($qty == 0 && $measurement == ''))
                                        continue;

                                    if ($WorkType == 'activity') {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_DPEIOWTrans');
                                        $insert->Values(array('DPETransId' => $DPETransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    } else if ($WorkType == 'iow') {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_DPEWBSTrans');
                                        $insert->Values(array('DPETransId' => $DPETransId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }

                                    if ($measurement != '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_DPEMeasurement')
                                            ->where("$fieldName=$TransId");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $insert = $sql->insert();
                                        $insert->into('WPM_DPEMeasurement');
                                        $insert->Values(array('DPERegisterId' => $DPETransId, $fieldName => $TransId, 'Measurement' => $measurement, 'CellName' => $cellname, 'SelectedColumns' => $SelectedColumns));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), 'WPM-WorkProgress-Add', 'N', 'WorkProgress-Add', $DPEId, 0, 0, 'WPM', $wpNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workprogress', 'action' => 'index'));
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                } else if($editId != 0) {

                    // edit entry
                    try {
                        $connection->beginTransaction();

                        $wpNo = $postData['DPENo'];

                        $update = $sql->update();
                        $update->table('WPM_DPERegister');
                        $update->set(array('DPEDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['DPEDate'], 'string')))
                        , 'DPENo' => $this->bsf->isNullCheck($wpNo, 'string')
                        , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                        , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                        , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                        , 'CCDPENo' => $this->bsf->isNullCheck($postData['CCDPENo'], 'string')
                        , 'CompDPENo' => $this->bsf->isNullCheck($postData['CompDPENo'], 'string')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['VendorId'], 'number')
                        , 'WOType' => $WorkType
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCenterId'], 'string')
                        , 'Amount' => $this->bsf->isNullCheck($postData['total'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['Notes'], 'string')
                        , 'WORegisterId' => $this->bsf->isNullCheck($postData['WORegisterId'], 'number')
                        ));
                        $update->where(array('DPERegisterId' => $editId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                        if($WorkType == 'turn-key') {
                            // delete turn key
                            $deleteids = rtrim($this->bsf->isNullCheck($postData['deleteids'],'string'), ",");
                            if($deleteids !== '' && $deleteids != '0') {
                                $delete = $sql->delete();
                                $delete->from('WPM_DPETurnKeyIOWTrans')
                                    ->where("DPETurnKeyTransId IN ($deleteids)");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $delete = $sql->delete();
                                $delete->from('WPM_DPETurnKeyTrans')
                                    ->where("DPETurnKeyTransId IN ($deleteids)");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            for ($i = 1; $i <= $rowid; $i++) {
                                $DPETurnKeyTransId = $this->bsf->isNullCheck($postData['DPETransId_' . $i], 'number');
                                $UpdateRow = $this->bsf->isNullCheck($postData['UpdateRow_' . $i], 'number');
                                $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $wbsid = $this->bsf->isNullCheck($postData['wbsid_' . $i], 'number');
                                $cumper = $this->bsf->isNullCheck($postData['cumpercent_' . $i], 'number');
                                $prevper = $this->bsf->isNullCheck($postData['prevpercent_' . $i], 'number');
                                $curper = $this->bsf->isNullCheck($postData['curpercent_' . $i], 'number');

                                if ($DescId == 0 || $wbsid == 0)
                                    continue;

                                if ($UpdateRow == 0 && $DPETurnKeyTransId == 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_DPETurnKeyTrans');
                                    $insert->Values(array('DPERegisterId' => $editId, 'WBSId' => $wbsid, 'CumPercent' => $cumper,
                                        'PrevPercent' => $prevper, 'CurPercent' => $curper));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $DPETurnKeyTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                } else if ($UpdateRow == 1 && $DPETurnKeyTransId != 0) {
                                    $update = $sql->update();
                                    $update->table('WPM_DPETurnKeyTrans');
                                    $update->set(array('DPERegisterId' => $editId, 'WBSId' => $wbsid, 'CumPercent' => $cumper,
                                        'PrevPercent' => $prevper, 'CurPercent' => $curper));
                                    $update->where(array('DPETurnKeyTransId' => $DPETurnKeyTransId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $DPETurnKeyIOWTransId = $this->bsf->isNullCheck($postData['iow_' . $i . '_DPEIOWTransId_' . $j], 'number');
                                    $UpdateRow = $this->bsf->isNullCheck($postData['iow_' . $i . '_UpdateRow_' . $j], 'number');
                                    $iowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $unitid = $this->bsf->isNullCheck($postData['iow_' . $i . '_unitid_' . $j], 'number');
                                    $boqqty = $this->bsf->isNullCheck($postData['iow_' . $i . '_boqqty_' . $j], 'number');
                                    $boqrate = $this->bsf->isNullCheck($postData['iow_' . $i . '_boqrate_' . $j], 'number');
                                    $boqamount = $this->bsf->isNullCheck($postData['iow_' . $i . '_boqamount_' . $j], 'number');
                                    $variance = $this->bsf->isNullCheck($postData['iow_' . $i . '_variance_' . $j], 'number');
                                    $qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    if ($iowid == 0 || $qty == 0)
                                        continue;

                                    if ($UpdateRow == 0 && $DPETurnKeyIOWTransId == 0) {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_DPETurnKeyIOWTrans');
                                        $insert->Values(array('DPETurnKeyTransId' => $DPETurnKeyTransId, 'IOWId' => $iowid, 'UnitId' => $unitid,
                                            'BOQQty' => $boqqty, 'BOQRate' => $boqrate, 'BOQAmount' => $boqamount, 'Qty' => $qty, 'Rate' => $rate,
                                            'Amount' => $amount, 'Variance' => $variance));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else if ($UpdateRow == 1 && $DPETurnKeyIOWTransId != 0) {
                                        $update = $sql->update();
                                        $update->table('WPM_DPETurnKeyIOWTrans');
                                        $update->set(array('DPETurnKeyTransId' => $DPETurnKeyTransId, 'IOWId' => $iowid, 'UnitId' => $unitid,
                                            'BOQQty' => $boqqty, 'BOQRate' => $boqrate, 'BOQAmount' => $boqamount, 'Qty' => $qty, 'Rate' => $rate,
                                            'Amount' => $amount, 'Variance' => $variance));
                                        $update->where(array('DPETurnKeyIOWTransId' => $DPETurnKeyIOWTransId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                        } else {
                            // delete boqs
                            $deleteids = rtrim($this->bsf->isNullCheck($postData['deleteids'],'string'), ",");
                            if($deleteids !== '' && $deleteids != '0') {
                                if ($WorkType == 'activity') {
                                    $delete = $sql->delete();
                                    $delete->from('WPM_DPEIOWTrans')
                                        ->where("DPETransId IN ($deleteids)");
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else if ($WorkType == 'iow') {
                                    $delete = $sql->delete();
                                    $delete->from('WPM_DPEWBSTrans')
                                        ->where("DPETransId IN ($deleteids)");
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $delete = $sql->delete();
                                $delete->from('WPM_DPETrans')
                                    ->where("DPETransId IN ($deleteids)");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            //boq
                            for ($i = 1; $i <= $rowid; $i++) {
                                $DPETransId = $this->bsf->isNullCheck($postData['DPETransId_' . $i], 'number');
                                $UpdateRow = $this->bsf->isNullCheck($postData['UpdateRow_' . $i], 'number');
                                $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');

                                $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');

                                if ($DescId == 0)
                                    continue;

                                if ($WorkType == 'activity') {
                                    $descIdColumn = 'ResourceId';
                                    $fieldName = 'DPEIOWTransId';
                                } else if ($WorkType == 'iow') {
                                    $descIdColumn = 'IOWId';
                                    $fieldName = 'DPEWBSTransId';
                                }

                                if ($UpdateRow == 0 && $DPETransId == 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_DPETrans');
                                    $insert->Values(array('DPERegisterId' => $editId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $DPETransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                } else if ($UpdateRow == 1 && $DPETransId != 0) {
                                    $update = $sql->update();
                                    $update->table('WPM_DPETrans');
                                    $update->set(array('DPERegisterId' => $editId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                    $update->where(array('DPETransId' => $DPETransId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $DPEIOWTransId = $this->bsf->isNullCheck($postData['iow_' . $i . '_DPEIOWTransId_' . $j], 'number');
                                    $UpdateRow = $this->bsf->isNullCheck($postData['iow_' . $i . '_UpdateRow_' . $j], 'number');
                                    $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $WBSId = $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    $measurement = $this->bsf->isNullCheck($postData['iow_' . $i . '_Measurement_' . $j], 'string');
                                    $cellname = $this->bsf->isNullCheck($postData['iow_' . $i . '_CellName_' . $j], 'string');
                                    $SelectedColumns = $this->bsf->isNullCheck($postData['iow_' . $i . '_SelectedColumns_' . $j], 'string');

                                    if (($IOWId == 0 && $WBSId == 0) || $qty == 0 || ($qty == 0 && $measurement == ''))
                                        continue;

                                    if ($UpdateRow == 0 && $DPEIOWTransId == 0) {
                                        if ($WorkType == 'activity') {
                                            $insert = $sql->insert();
                                            $insert->into('WPM_DPEIOWTrans');
                                            $insert->Values(array('DPETransId' => $DPETransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $DPEIOWTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($WorkType == 'iow') {
                                            $insert = $sql->insert();
                                            $insert->into('WPM_DPEWBSTrans');
                                            $insert->Values(array('DPETransId' => $DPETransId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $DPEIOWTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        }
                                    } else if ($UpdateRow == 1 && $DPEIOWTransId != 0) {
                                        if ($WorkType == 'activity') {
                                            $update = $sql->update();
                                            $update->table('WPM_DPEIOWTrans');
                                            $update->set(array('DPETransId' => $DPETransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $update->where(array('DPEIOWTransId' => $DPEIOWTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($WorkType == 'iow') {
                                            $update = $sql->update();
                                            $update->table('WPM_DPEWBSTrans');
                                            $update->set(array('DPETransId' => $DPETransId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $update->where(array('DPEWBSTransId' => $DPEIOWTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }

                                    if ($measurement != '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_DPEMeasurement')
                                            ->where("$fieldName=$DPEIOWTransId");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $insert = $sql->insert();
                                        $insert->into('WPM_DPEMeasurement');
                                        $insert->Values(array('DPERegisterId' => $DPETransId, $fieldName => $DPEIOWTransId, 'Measurement' => $measurement, 'CellName' => $cellname, 'SelectedColumns' => $SelectedColumns));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), 'WPM-WorkProgress-Edit', 'E', 'WorkProgress-Edit', $editId, 0, 0, 'WPM', $wpNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workprogress', 'action' => 'register'));
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                }
            } else {
                // get request
                if($editId != 0) {
                    // register details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WPM_DPERegister' ) )
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreId', 'CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array('VendorId', 'VendorName'), $select::JOIN_LEFT)
                        ->columns( array("DPERegisterId", "DPENo", "DPEDate" => new Expression("FORMAT(a.DPEDate, 'dd-MM-yyyy')"), "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")
                        , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")
                        , "RefNo", "CCDPENo", "CompDPENo", "WOType", "Amount", 'Narration','WORegisterId', 'Approve') )
                        ->where( "a.DeleteFlag=0 AND a.DPERegisterId=$editId" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $dperegister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $this->_view->dperegister = $dperegister;

                    $WorkType = $this->_view->dperegister['WOType'];
                    $this->_view->WorkType = $WorkType;
                    if($WorkType == 'activity')
                        $this->_view->WorkTypeName = 'Activity';
                    else if($WorkType == 'iow')
                        $this->_view->WorkTypeName = 'IOW';
                    else
                        $this->_view->WorkTypeName = 'Turn Key';

                    // workorder details
                    $select = $sql->select();
                    $select->from(array("a" => "WPM_WORegister"))
                        ->columns(array('WONo', 'WORegisterId', 'WOType'))
                        ->where("WORegisterId=".$this->_view->dperegister['WORegisterId']);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->workorder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // cost center details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                        ->columns( array( 'CostCentreId', 'CostCentreName', 'ProjectId', 'WBSReqWPM', 'CompanyId' ) )
                        ->where( "Deactivate=0 AND CostCentreId=".$this->_view->dperegister['CostCentreId'] );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $projectId = $this->_view->costcenter['ProjectId'];
                    $this->_view->projectId = $projectId;
                    $this->_view->wbsReq = $this->_view->costcenter['WBSReqWPM'];

                    // vendor details
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId','VendorName','LogoPath'))
                        ->where( "VendorId=".$this->_view->dperegister['VendorId'] );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->vendor = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if($WorkType == 'activity') {
                        // get resource lists
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPETrans"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Desc' => new Expression("b.Code+ ' ' +b.ResourceName"), 'Rate'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ProjectResource'), 'b.ResourceId=d.ResourceId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('DPETransId','DescId' => 'ResourceId','Qty','Rate', 'Amount'))
                            ->where('a.DPERegisterId='.$editId)
                            ->where("d.ProjectId=".$projectId)
                            ->group(new Expression('a.DPETransId,a.ResourceId,a.Qty,a.Rate,a.Amount,b.Code,b.ResourceName,b.Rate,d.Qty,c.UnitName,c.UnitId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_DPETrans")
                            ->columns(array('DPETransId'))
                            ->where('DPERegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPEIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->join(array('e' => 'WPM_DPEMeasurement' ), 'a.DPEIOWTransId=e.DPEIOWTransId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
                            ->columns(array('DPEIOWTransId','DPETransId','ProjectIOWId' => 'IOWId', 'Qty'))
                            ->where->expression('a.DPETransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectResource"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('value' => new Expression("Code+ ' ' +ResourceName + ' ' + Case When a.RateType='M' Then '- Manual' When a.RateType='A' Then '- Machinery' Else '' End"), 'data' => 'ResourceId'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT)
                            ->columns(array('Qty', 'Rate', 'RateType'))
                            ->where("a.ProjectId=".$projectId)
                            ->where("b.TypeId=4")
                            ->order('a.ResourceId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else if($WorkType == 'iow') {
                        // get iow lists
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPETrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('Desc' => new Expression("b.RefSerialNo+ ' ' +b.Specification")), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ProjectIOW'), 'd.ProjectIOWId=b.ProjectIOWId', array('EstQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('DPETransId', 'DescId' => 'IOWId', 'Qty'))
                            ->where('a.DPERegisterId='.$editId)
                            ->where("b.ProjectId=".$projectId);
                            //->group(new Expression('a.IowId,b.RefSerialNo,b.Specification,c.UnitName,c.UnitId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_DPETrans")
                            ->columns(array('DPETransId'))
                            ->where('DPERegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPEWBSTrans"))
                            ->join(array('b' => 'WPM_DPETrans'), 'a.DPETransId=b.DPETransId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'd.WBSId=c.WBSId AND b.IOWId=c.ProjectIOWId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('e' => 'WPM_DPEMeasurement' ), 'a.DPEWBSTransId=e.DPEWBSTransId', array( 'Measurement', 'CellName', 'SelectedColumns'), $select::JOIN_LEFT )
                            ->columns(array('DPEIOWTransId' => 'DPEWBSTransId', 'DPETransId', 'Qty'))
                            ->group(new Expression('a.DPEWBSTransId,a.DPETransId,a.Qty,d.WBSId,d.WBSName,d.ParentText,c.Qty,e.Measurement,e.CellName,e.SelectedColumns'))
                            ->where->expression('a.DPETransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectIOWMaster"))
                            ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('Rate', 'Qty'), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSTrans'), 'd.ProjectIOWId=a.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->columns(array('value' => new Expression("a.RefSerialNo+ ' ' +a.Specification"), 'data' => 'ProjectIOWId'))
                            ->where("a.ProjectId=".$projectId)
                            ->where('a.UnitId <> 0')
                            ->order('a.ProjectIOWId')
                            ->group(new Expression('a.ProjectIOWId,a.RefSerialNo,a.Specification,c.Rate,c.Qty,b.UnitName,b.UnitId'));
                        if($this->_view->wbsReq != '' && $this->_view->wbsReq == 1) {
                            $select->where('d.WBSId != 0');
                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else if($WorkType == 'turn-key') {
                        $select = $sql->select();
                        $select->from(array('a' => "WPM_DPETurnKeyTrans"))
                            ->join(array('b' => 'WPM_WOTurnKeyTrans'), 'a.WBSId=b.WBSId', array('WOAmount' => 'Amount'), $select::JOIN_LEFT)
                            ->join(array('d' => 'WPM_DPERegister'), 'a.DPERegisterId=d.DPERegisterId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSMaster'), 'a.WBSId=c.WBSId', array('WBSId',"Desc" => new Expression("c.ParentText + '->' + c.WBSName")), $select::JOIN_LEFT)
                            ->columns(array('DescId' => 'DPETurnKeyTransId', 'CumPercent', 'PrevPercent', 'CurPercent','DPETurnKeyTransId'))
                            ->where("a.DPERegisterId=$editId AND d.WORegisterId=b.WORegisterId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPETurnKeyIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('ProjectIOWId','RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array('UnitId','UnitName'), $select::JOIN_LEFT)
                            ->join(array('d' => 'WPM_DPETurnKeyTrans'), 'd.DPETurnKeyTransId=a.DPETurnKeyTransId', array(), $select::JOIN_LEFT)
                            ->columns(array('DPETurnKeyIOWTransId','DPETurnKeyTransId','BOQQty', 'BOQRate', 'BOQAmount','Qty', 'Rate', 'Amount','Variance'))
                            ->where("d.DPERegisterId=$editId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array('a' => "WPM_WOTurnKeyTrans"))
                            ->join(array('b' => 'WPM_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSMaster'), 'a.WBSId=c.WBSId', array('WBSId',"value" => new Expression("c.ParentText + '->' + c.WBSName")), $select::JOIN_LEFT)
                            ->columns(array('data' => 'WOTurnKeyTransId', 'WOAmount' => 'Amount', 'Variance'))
                            ->where( "b.VendorId=".$dperegister['VendorId']." AND b.CostCentreId=".$dperegister['CostCentreId']." AND b.WORegisterId=".$dperegister['WORegisterId']." AND b.WOType='$WorkType'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    // Material List
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_Resource"))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("unit" => 'UnitName'), $select:: JOIN_LEFT)
                        ->columns(array("data" => 'ResourceId', "value" => new Expression("a.Code + ' ' + a.ResourceName")))
                        ->where("a.DeleteFlag=0 AND a.TypeId=2");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->DPERegisterId = $editId;
                }
            }

            $aVNo = CommonHelper::getVoucherNo(823, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->woNo = $aVNo["voucherNo"];
            else
                $this->_view->woNo = "";

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function registerAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Progress");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_DPERegister'))
            ->columns(array("DPERegisterId", "DPENo", "DPEDate" => new Expression("FORMAT(a.DPEDate, 'dd-MM-yyyy')"), "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), "Approve" => new Expression("Case When a.Approve='Y' then 'Yes' when a.Approve='P' then 'Partial' else 'No' end")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_WORegister'), 'a.WORegisterId = d.WORegisterId', array('WONo'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->dpeRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteDpeAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $dpeRegId = $this->params()->fromPost('dpeRegId');
                    $response = $this->getResponse();

                    $subQuery = $sql->select();
                    $subQuery->from('WPM_DPETrans')
                        ->columns(array('DPETransId'))
                        ->where(array('DPERegisterId' => $dpeRegId));

                    $delete = $sql->delete();
                    $delete->from('WPM_DPEIOWTrans')
                        ->where->expression('DPETransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_DPEWBSTrans')
                        ->where->expression('DPETransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_DPETrans')
                        ->where("DPERegisterId = $dpeRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_DPERegister')
                        ->where("DPERegisterId = $dpeRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

                $response->setContent($status);
                return $response;
            }
        }

    }
    public function WorkProgressReportAction(){
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
        $subscriberId = $this->auth->getIdentity()->UserId;

        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
                    // AJAX
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("cb/default", array("controller" => "index","action" => "login"));
                    return;
                }
            }
        } else {
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        }
        $request = $this->getRequest();

        if ($request->isPost()) {
            $this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );
        }

        $dir = 'public/reports/workprogress/'. $subscriberId;
        $filePath = $dir.'/v4_template.phtml';
        $filePath1 = $dir.'/f4_template.phtml';
        $dpeRegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($dpeRegisterId == 0)
            $this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );

        if (!file_exists($filePath)) {
            $filePath = 'public/reports/workprogress/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/reports/workprogress/template1.phtml';
        }

        $template = file_get_contents($filePath);
        $template1 = file_get_contents($filePath1);
        $this->_view->template = $template;
        $this->_view->footertemplate = $template1;


        // check for bill id and subscriber id
        /*$select = $sql->select();
        $select->from(array('a' => "CB_BillMaster"))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
            ->columns(array('BillId'))
            ->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject($select);
        if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
            $this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/

        $select = $sql->select();
        $select->from(array('a' => 'WPM_DPERegister'))
            ->columns(array("DPERegisterId", "DPENo","CCDPENo","RefNo",
                "DPEDate" => new Expression("FORMAT(a.DPEDate, 'dd-MM-yyyy')"),
                "ContractorName" => new Expression('VendorName'),
                "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_WORegister'), 'a.WORegisterId = d.WORegisterId', array('WONo','WOType'), $select::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "b.CompanyId=h.CompanyId", array("LogoPath","CompanyName"), $select::JOIN_LEFT)
            ->where("a.DPERegisterId=$dpeRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $dpeRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->dpeRegister=$dpeRegister;
//            echo '<pre>'; print_r($this->_view->woregister);die;
        // boq
//            echo $woAmtinwords = $this->convertAmountToWords($woregister['Amount']);die;
//            $this->_view->woAmtinwords = $woAmtinwords;

        $select = $sql->select();
        $select->from(array('a' => "WPM_DPETrans"))
            ->join(array('c' => 'proj_resource'), 'a.ResourceId=c.ResourceId', array('Code','ResourceName'), $select:: JOIN_LEFT)
            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select:: JOIN_LEFT)
            ->columns(array('Amount','DPETransId','Qty','Rate'))
            ->where("a.DPERegisterId=$dpeRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
    /*public function registerAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || WorkOrder Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array("a" => "WPM_WORegister"))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->columns(array("WORegisterId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
            , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), "Amount"))
            ->where("a.DeleteFlag='0'")
            ->order('a.WORegisterId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('CB_WORegister')
            ->columns(array('Orders' => new Expression("Count(WorkOrderId)")))
            ->where("DeleteFlag='0' and LiveWO ='0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ordercount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('CB_WORegister')
            ->columns(array('OrderAmt' => new Expression("Sum(OrderAmount)")))
            ->where("DeleteFlag='0' and LiveWO ='0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ordervalue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' =>'CB_BillAbstract'))
            ->join(array('b' => 'CB_BillMaster'), 'a.BillId=b.BillId', array(), $select:: JOIN_LEFT)
            ->columns(array('CurAmount' => new Expression("Sum(CerCurAmount)")))
            ->where("a.BillFormatId='1' AND b.DeleteFlag='0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->workvalue = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'CB_WORegister'))
            ->join(array('b' => 'CB_ClientMaster'), 'a.ClientId=b.ClientId', array('ClientName'), $select:: JOIN_LEFT)
            ->columns(array('Amount' => new Expression("sum(OrderAmount)")), array('ClientName'))
            ->where("a.DeleteFlag='0' and a.LiveWO ='0'")
            ->group('ClientName');

        $select1 = $sql->select();
        $select1->from(array("g" => $select))
            ->columns(array('*'))
            ->order('g.Amount Desc')
            ->limit(5);
        $statement = $sql->getSqlStringForSqlObject($select1);
        $this->_view->clientorder = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $select = $sql->select();
        $select->from(array('a' => 'CB_WORegister'))
            ->join(array('b' => 'CB_ProjectMaster'), 'a.ProjectId=b.ProjectId', array(), $select::JOIN_LEFT)
            ->join(array('c' => 'CB_ProjectTypeMaster'), 'b.ProjectTypeId=c.ProjectTypeId', array('ProjectTypeName'), $select:: JOIN_LEFT)
            ->columns(array('Amount' => new Expression("sum(OrderAmount)")), array('ClientName'))
            ->where("a.DeleteFlag='0' and b.DeleteFlag='0' and c.DeleteFlag='0' and a.LiveWO ='0'")
            ->group(new Expression('c.ProjectTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projecttypeorder = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $select = $sql->select();
        $select->from(array('a' => 'CB_WORegister'))
            ->columns(array('Mon' => new Expression("month(WODate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,WODate),3) + '-' + ltrim(str(Year(WODate)))"), 'Amount' => new Expression("sum(OrderAmount)")))
            ->where("a.DeleteFlag='0' and a.LiveWO ='0'")
            ->group(new Expression('month(WODate), LEFT(DATENAME(MONTH,WODate),3),Year(WODate)'));

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->monorder = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }*/
}