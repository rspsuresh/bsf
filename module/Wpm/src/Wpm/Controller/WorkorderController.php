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

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
use Application\View\Helper\Qualifier;

use Zend\Session\Container;

class WorkorderController extends AbstractActionController
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
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                $OrderType = $this->bsf->isNullCheck($postParams['OrderType'],'string');
                $WorkType = $this->bsf->isNullCheck($postParams['WorkType'],'string');

                $whereCond = array("a.CostCentreId"=>$CostCentreId);

                if ($OrderType =="work") {
                    if ($OrderType == 'work' && $WorkType != '') {
                        $whereCond['a.RequestType'] = $WorkType;
                    }

                    $select = $sql->select();
                    $select->from(array("a" => "VM_RequestRegister"))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->columns(array('RequestDate' => new Expression("FORMAT(a.RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'RequestId'))
                        ->where($whereCond);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subQuery = $sql->select();
                    $subQuery->from(array("a" => "VM_RequestRegister"))
                        ->columns(array('RequestId'))
                        ->where($whereCond);

                    $select = $sql->select();
                    $select->from(array("d" => "VM_RequestTrans"))
                        ->join(array('b' => 'VM_RequestRegister'), 'd.RequestId=b.RequestId', array('RequestDate' => new Expression("FORMAT(b.RequestDate, 'dd-MM-yyyy')"), 'RequestNo'), $select::JOIN_LEFT);

                    if ($OrderType == 'work' && $WorkType == 'activity') {
                        $select->join(array('c' => 'Proj_Resource'), 'd.ResourceId=c.ResourceId', array('Desc' => 'ResourceName'), $select::JOIN_LEFT);
                    } else if ($OrderType == 'work' && $WorkType == 'iow') {
                        $select->join(array('c' => 'Proj_ProjectIOWMaster'), 'd.IowId=c.ProjectIOWId', array('Desc' => new Expression("c.RefSerialNo + ' ' + c.Specification")), $select::JOIN_LEFT);
                    }
                    $select->columns(array('RequestTransId', 'Quantity', 'RequestId', 'Include' => new Expression("'0'")))
                        ->where->expression('d.RequestId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $bServices = array();
                } else if  ($OrderType =="service") {
                    $iTypeid = $this->bsf->isNullCheck($postParams['Typeid'],'number');
                    $requestResources='';
                    $requests='';
                    if($iTypeid != 0) {
                        $select = $sql->select();
                        $select->from(array("A" => "VM_RequestTrans"))
                            ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array('RequestDate' => new Expression("FORMAT(RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'RequestId'), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_ServiceMaster'), 'A.ResourceId=C.ServiceId', array(), $select::JOIN_INNER)
                            ->join(array('D' => 'Proj_ServiceTypeMaster'), 'C.ServiceTypeId=D.ServiceTypeId', array(), $select::JOIN_INNER)
                            ->join(array('E' => 'Proj_OHService'), 'C.ServiceId=E.ServiceId', array(), $select::JOIN_INNER)
                            ->join(array('F' => 'WF_OperationalCostCentre'), 'E.ProjectId=F.ProjectId And B.CostCentreId=F.CostCentreId', array('CostCentreName'), $select::JOIN_INNER);
                        $select->where(array("B.RequestType='Service' And B.CostCentreId=$CostCentreId And B.Approve='Y' And A.BalQty>0 and D.ServiceTypeId=$iTypeid"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("A" => "VM_RequestTrans"))
                            ->columns(array('RequestTransId','Desc'=>new Expression("Case When isnull(C.ServiceCode,'') <> '' Then C.ServiceCode + ' - ' + C.ServiceName Else C.ServiceName End"),
                                'Quantity'=>new Expression("Cast(A.BalQty As Decimal(18,3))")))
                            ->join(array('B' => 'VM_RequestRegister'), 'A.RequestId=B.RequestId', array('RequestDate' => new Expression("FORMAT(RequestDate, 'dd-MM-yyyy')"), 'RequestNo', 'RequestId'), $select::JOIN_INNER)
                            ->join(array('C' => 'Proj_ServiceMaster'), 'A.ResourceId=C.ServiceId', array(), $select::JOIN_INNER)
                            ->join(array('D' => 'Proj_ServiceTypeMaster'), 'C.ServiceTypeId=D.ServiceTypeId', array(), $select::JOIN_INNER)
                            ->join(array('E' => 'Proj_OHService'), 'C.ServiceId=E.ServiceId', array(), $select::JOIN_INNER)
                            ->join(array('F' => 'WF_OperationalCostCentre'), 'E.ProjectId=F.ProjectId And B.CostCentreId=F.CostCentreId', array('CostCentreName'), $select::JOIN_INNER);
                        $select->where(array("B.RequestType='Service' And B.CostCentreId=$CostCentreId And B.Approve='Y' And A.BalQty>0 and D.ServiceTypeId=$iTypeid"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    $select = $sql->select();
                    $select->from(array('a'=>'Proj_OHService'))
                        ->columns(array())
                        ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId = b.ServiceId',array('ServiceTypeId'),$select::JOIN_INNER)
                        ->join(array('c' => 'WF_OperationalCostCentre'), 'a.ProjectId = c.ProjectId',array(),$select::JOIN_INNER)
                        ->join(array('d' => 'Proj_ServiceTypeMaster'), 'b.ServiceTypeId = d.ServiceTypeId',array('ServiceTypeName'),$select::JOIN_LEFT)
                        ->where(array('c.CostCentreId'=>$CostCentreId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $bServices = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                }

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('requests' => $requests, 'resources' => $requestResources,'services'=>$bServices)));
                return $response;
            }
        } else {

            // get cost centres
            $select = $sql->select();
            $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                ->columns( array( 'CostCentreId', 'CostCentreName' ) )
                ->where( 'Deactivate=0' );
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->arr_costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            // vendors(contract)
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId','VendorName' ,'LogoPath'))
                ->where(array('Contract' => '1') );
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_contract_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // vendors(service)
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId','VendorName','LogoPath'))
                ->where(array('Service' => '1') );
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_service_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // vendors(lists)
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorName','RegAddress'))
                ->where(array('Contract' => '1') );
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_service_vendorslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Service Type Master
            $select = $sql->select();
            $select->from( 'Proj_ServiceTypeMaster' )
                ->columns(array('ServiceTypeId', 'ServiceTypeName'));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->serviceTypeMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Hire Type Master
            $select = $sql->select();
            $select->from( 'WPM_HireTypeMaster' )
                ->columns(array('HireTypeId', 'HireTypeName'));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->hireTypeMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function vendorsAction()
    {

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $select = $sql->select();
                $select->from(array('a' => 'Vendor_Master'))
                    ->columns(array('VendorName', 'RegAddress'))
                    ->where('Contract=1');
                $statement = $sql->getSqlStringForSqlObject($select);
                $vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(false);
                $response = $this->getResponse()->setContent(json_encode($vendors));
                return $response;

            }
        }
    }
    public function entryAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        $editId = $this->bsf->isNullCheck($this->params()->fromRoute('editId'), 'number');
        $amdId = $this->bsf->isNullCheck($this->params()->fromRoute('amdId'), 'number');

        if(!$this->getRequest()->isXmlHttpRequest() && $editId == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('wpm/wo-entry', array('controller' => 'workorder', 'action' => 'index'));
        }

        $this->_view->wbsReq = '';

        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $projectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number');
                $iowid=$this->bsf->isNullCheck($this->params()->fromPost('iowidid'), 'string');
                $response = $this->getResponse();
                switch($Type) {
                    case 'getresourcedetails':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectDetails"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('ProjectIOWId','RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=e.ProjectIOWId', array('bQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.ProjectIOWId=c.ProjectIOWId', array('cQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'c.WBSId=d.WBSId', array('WBSId', 'WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ResourceId', 'Qty'))
                            ->where("a.ResourceId=$ResourceId")
                            ->where("a.ProjectId=$projectId")
                            ->where('c.WBSId != 0');
                        //->group(new Expression('a.ResourceId,a.Qty,b.ProjectIOWId,b.RefSerialNo,b.Specification,e.Qty,c.Qty,d.WBSId,d.WBSName,d.ParentText'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        return $response;
                        break;
                    case 'price':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectResource"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array("value" => new Expression("Code + ' ' + ResourceName")), $select::JOIN_INNER)
                            ->join(array('c' => 'Proj_UOM'),' b.UnitId=c.UnitId' , array('unit'=>'UnitName'), $select::JOIN_INNER)
                            ->columns(array('data'=>new Expression("distinct a.ResourceId"),"Rate"))
                            ->where(array("a.Includeflag=1 and b.typeId=2 and A.ResourceId IN(Select ResourceId from Proj_ProjectDetails
                        Where ProjectIOWId in ($iowid)) "));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iowsprice = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iowsprice));
                        return $response;
                        break;
                    case 'getiowdetails':
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_WBSTrans'))
                            ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ProjectIOWId', 'Qty', 'WBSId', 'Rate'))
                            ->where(array('a.ProjectIOWId' => $IOWId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        return $response;
                        break;
                    case 'getwbsdetails':
                        $WBSId = $this->bsf->isNullCheck($this->params()->fromPost('WBSId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('ProjectIOWId','RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId','UnitName'))
                            ->columns(array('Qty', 'Rate', 'Amount'))
                            ->where("a.WBSId=$WBSId");
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

                        $aTable = '';
                        if($WOType == 'activity') {
                            $aTable = 'WPM_WOIOWTrans';
                            $aaTable = 'WPM_WorkBillIOWTrans';
                        } else if($WOType == 'iow') {
                            $aTable = 'WPM_WOWBSTrans';
                            $aaTable = 'WPM_WorkBillWBSTrans';
                        }

                        $wobArr = array();
                        $select = $sql->select();
                        $select->from(array("a" => $aTable))
                            ->join(array('b' => 'WPM_WOTrans'), 'a.WOTransId=b.WOTransId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_WORegister'), 'b.WORegisterId=c.WORegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WOQty' => new Expression("SUM(a.Qty)")))
                            ->where("c.CostCentreId=$ccId");
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
                            ->where("c.CostCentreId=$ccId");
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

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($wobArr));
                        return $response;
                        break;
                    case 'getqtywio':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ccId = $this->bsf->isNullCheck($this->params()->fromPost('ccId'), 'number');
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');

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

                        $wobArr = array();
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"))
                            //->join(array('b' => $aTable), 'a.WOTransId=b.WOTransId', array('WOQty' => new Expression("SUM(b.Qty)")), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_WORegister'), 'a.WORegisterId=c.WORegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WOQty' => new Expression("SUM(a.Qty)")))
                            ->where($whereCond);
                        //->group(new Expression('a.WOTransId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wo_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WOQty'] = $wo_qty['WOQty'];

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WorkBillTrans"))
                            //->join(array('b' => $aaTable), 'a.WorkBillTransId=b.WorkBillTransId', array('WBQty' => new Expression("SUM(b.Qty)")), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=c.BillRegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WBQty' => new Expression("SUM(a.CurQty)")))
                            ->where($whereCond);
                            //->group(new Expression('a.WorkBillTransId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wb_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WBQty'] = $wb_qty['WBQty'];

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
                $OrderType = $this->bsf->isNullCheck($postData['OrderType'], 'string');
                $WorkType = $this->bsf->isNullCheck($postData['WorkType'], 'string');
                if (!is_null($postData['frm_index'])) {
                    $CostCentre = $this->bsf->isNullCheck($postData['CostCentre'], 'number');
                    $VendorId = $this->bsf->isNullCheck($postData['VendorId'], 'number');
                    $requestTransIds = $postData['requestTransIds'];
                    if($requestTransIds!=''){
                        $requestTransIds=$requestTransIds;
                    }else{
                        $requestTransIds=0;
                    }
                    $this->_view->OrderType = $OrderType;
                    $this->_view->WorkType = $WorkType;
                    $this->_view->valuefrom = 0;

                    // cost center details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                        ->columns( array( 'CostCentreId', 'CostCentreName', 'ProjectId', 'WBSReqWPM', 'CompanyId') )
                        ->where( "Deactivate=0 AND CostCentreId=$CostCentre" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $projectId = $this->_view->costcenter['ProjectId'];
                    $this->_view->projectId = $projectId;
                    $this->_view->wbsReq = $this->_view->costcenter['WBSReqWPM'];

                    // vendor details
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId','VendorName'))
                        ->where( "VendorId=$VendorId" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->vendor = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_QualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','KKCess','SBCess','NetPer',
                            'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'KKCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SBCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
                    $select->where(array('a.QualType' => 'W'));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                        echo '<pre>'; print_r($qualList);die;
                    $sHtml=Qualifier::getQualifier($qualList);
                    $this->_view->qualHtml = $sHtml;

                    $sHtml=Qualifier::getQualifier($qualList,"R");
                    $this->_view->qualRHtml = $sHtml;

                    $select = $sql->select();
                    $select->from(array("a" => "WF_TermsMaster"))
                        ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,3)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross,TermType")))
                        ->where(array("TermType" => 'W'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    /*// mobilization advance

                    $select = $sql->select();
                    $select->from(array("a" => "WF_TermsMaster"))
                        ->columns(array('value'=>'Title' , 'data' => 'TermsId','TermType'))
                        ->where(array("TermType" => 'W'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/


                    if($OrderType == 'work' && $WorkType == 'activity') {
                       // print_r("activiti");die;
                        if($requestTransIds != '') {
                            $requestTransIds = implode(',',$requestTransIds);
                            // get resource lists
                            $select = $sql->select();
                            $select->from(array("a" => "VM_RequestTrans"))
                                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Desc' => new Expression("b.Code+ ' ' +b.ResourceName"), 'Rate'), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_ProjectResource'), 'b.ResourceId=d.ResourceId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                ->columns(array('DescId' => 'ResourceId'))
                                ->where('a.RequestTransId IN (' . $requestTransIds . ')')
                                ->where("d.ProjectId=".$projectId)
                                ->group(new Expression('a.ResourceId,b.Code,b.ResourceName,b.Rate,c.UnitName,c.UnitId,d.Qty'));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $subQuery = $sql->select();
                            $subQuery->from("VM_RequestTrans")
                                ->columns(array('ResourceId'))
                                ->where('RequestTransId IN (' . $requestTransIds . ')')
                                ->group(new Expression('ResourceId'));

                            $select = $sql->select();
                            $select->from(array("a" => "Proj_ProjectDetails"))
                                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                ->join(array('e' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=e.ProjectIOWId', array('bQty' => 'Qty'), $select::JOIN_LEFT)
                                ->join(array('c' => 'Proj_WBSTrans'), 'a.ProjectIOWId=c.ProjectIOWId', array('cQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_WBSMaster'), 'c.WBSId=d.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                ->columns(array('ResourceId', 'ProjectIOWId', 'Qty'))
                                ->order('a.ResourceId')
                                ->where("a.ProjectId=$projectId")
                                ->where('c.WBSId != 0')
                                ->where->expression('a.ResourceId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array("c" => "VM_RequestIowTrans"))
                                ->join(array('a' => 'VM_RequestTrans'), 'c.RequestTransId=a.RequestTransId', array('ResourceId'), $select::JOIN_LEFT)
                                ->join(array('b' => 'VM_RequestRegister'), 'a.RequestId=b.RequestId', array('RequestId', 'RequestNo', 'RequestDate'), $select::JOIN_LEFT)
                                ->columns(array('RequestTransId', 'IowId', 'WbsId', 'Qty', 'WOQty', 'BalQty'))
                                ->order('a.ResourceId')
                                ->where->expression('b.CostCentreId =' . $CostCentre . ' AND (c.Qty - c.WOQty) > 0 AND a.ResourceId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        }
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
                    }
                    else if($OrderType == 'work' && $WorkType == 'iow') {
                       // print_r("iow");die;
                        if($requestTransIds != '') {
                            $requestTransIds = implode(',', $requestTransIds);
                            // get iow lists
                            $select = $sql->select();
                            $select->from(array("a" => "VM_RequestTrans"))
                                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('Desc' => new Expression("b.RefSerialNo+ ' ' +b.Specification")), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_ProjectIOW'), 'd.ProjectIOWId=b.ProjectIOWId', array('EstQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                                ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                ->columns(array('DescId' => 'IOWId'))
                                ->where('a.RequestTransId IN (' . $requestTransIds . ')')
                                ->where("b.ProjectId=".$projectId)
                                ->group(new Expression('a.IowId,b.RefSerialNo,b.Specification,d.Rate,d.Qty,d.Rate,c.UnitName,c.UnitId'));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $subQuery = $sql->select();
                            $subQuery->from("VM_RequestTrans")
                                ->columns(array('IowId'))
                                ->where('RequestTransId IN (' . $requestTransIds . ')')
                                ->group(new Expression('IowId'));

                            $select = $sql->select();
                            $select->from(array("a" => "Proj_WBSTrans"))
                                ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                ->columns(array('ProjectIOWId', 'EstQty' => 'Qty', 'Rate'))
                                ->order('a.ProjectIOWId')
                                ->group(new Expression('a.ProjectIOWId,a.Qty,a.Rate,b.WBSId,b.WBSName,b.ParentText'))
                                ->where->expression('a.ProjectIOWId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            /*$select = $sql->select();
                            $select->from(array("a" => "Proj_ProjectDetails"))
                                ->join(array('e' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=e.ProjectIOWId', array(), $select:: JOIN_LEFT)
                                ->join(array('b' => 'Proj_UOM'), 'e.UnitId=b.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                                ->join(array('c' => 'Proj_WBSTrans'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=c.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                ->columns(array('ProjectIOWId'))
                                ->order('a.ProjectIOWId')
                                ->group(new Expression('a.ProjectIOWId,d.WBSId,d.WBSName,d.ParentText,b.UnitName'))
                                ->where->expression('a.ProjectIOWId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

                            $select = $sql->select();
                            $select->from(array("c" => "VM_RequestIowTrans"))
                                ->join(array('a' => 'VM_RequestTrans'), 'c.RequestTransId=a.RequestTransId', array('ResourceId'), $select::JOIN_LEFT)
                                ->join(array('b' => 'VM_RequestRegister'), 'a.RequestId=b.RequestId', array('RequestId', 'RequestNo', 'RequestDate'), $select::JOIN_LEFT)
                                ->columns(array('RequestTransId', 'IowId', 'WbsId', 'Qty', 'WOQty', 'BalQty'))
                                ->order('c.IowId')
                                ->where->expression('b.CostCentreId =' . $CostCentre . ' AND (c.Qty - c.WOQty) > 0 AND c.IowId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        }
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

                       /*/Select A.ProjectId,[B].[ResourceId] AS [data], C.Code + ' ' + C.ResourceName AS [value] from [Proj_ProjectIOWMaster] A
                        Left Join [Proj_ProjectResource] B On A.ProjectId=B.ProjectId
                        Left Join [Proj_Resource] C On B.[ResourceId]=[C].[ResourceId]
                        Where A.ProjectId=3 AND C.TypeId=2*/
                        // Material List
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_ProjectIOWMaster"))
                            ->columns( array())
                            ->join(array('b' => 'Proj_ProjectResource'), ' a.ProjectId=b.ProjectId',array("data" => 'ResourceId'), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_Resource'), ' b.ResourceId=c.ResourceId',array("value" => new Expression("Code + ' ' + ResourceName")), $select:: JOIN_LEFT)
                            ->where("a.ProjectId=$projectId AND c.TypeId=2")
                            ->group(array("c.Code","b.ResourceId","c.ResourceName"));
                         $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }  else if($OrderType == 'work' && $WorkType == 'turn-key') {
                        if ($requestTransIds != '') {
                            $requestTransIds = implode(',', $requestTransIds);
                        }

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSMaster"))
                            ->join(array('b' => 'Proj_WBSTrans'), 'a.WBSId=b.WBSId', array('BOQRate' => new Expression("SUM(b.Rate)"),'BOQAmount' => new Expression("SUM(b.Amount)")), $select::JOIN_LEFT)
                            ->columns(array('value' => new Expression("a.ParentText+ '->' +a.WBSName"), 'data' => 'WBSId'))
                            ->where("a.ProjectId=".$projectId)
                            ->where('a.LastLevel = 1')
                            ->group(new Expression('a.WBSId,a.ParentText,a.WBSName'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    /* $projectId=$this->_view->projectId;
                    // Material List
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_ProjectResource"))
                            ->columns( array("data" => 'ResourceId', "value" => new Expression("b.Code + ' ' + b.ResourceName")))
                            ->join(array('b' => 'Proj_Resource'), ' a.ResourceId=b.ResourceId',array(), $select:: JOIN_INNER)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array("unit" => 'UnitName'), $select:: JOIN_LEFT)
                            ->where("a.ProjectId=$projectId AND b.TypeId=2");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/


                    // Unit List
                    $select = $sql->select();
                    $select->from("Proj_UOM")
                        ->columns(array("data" => 'UnitId', "value" => 'UnitName'))
                        ->where("TypeId=2");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->woTypeId = '401';
                }
                else if($editId == 0 || $amdId != 0) {
                    // add entry
                    //echo '<pre>'; print_r($postData); die;
                    try {
                        $connection->beginTransaction();
                        //echo  $this->bsf->isNullCheck($postData['total'], 'number');die;
                        $woNo = $postData['WONo'];
                        $woCCNo = $postData['CCWONo'];
                        $woCoNo = $postData['CompWONo'];

                        if($amdId == 0) {
                            $amendment = 0;
                            $woaVNo = CommonHelper::getVoucherNo(401, date('Y-m-d', strtotime($postData['WODate'])), 0, 0, $dbAdapter, "I");
                            if ($woaVNo["genType"] == true) {
                                $woNo = $woaVNo["voucherNo"];
                            }

                            $woccaVNo = CommonHelper::getVoucherNo(401, date('Y-m-d', strtotime($postData['WODate'])), 0, $postData['CostCenterId'], $dbAdapter, "I");
                            if ($woccaVNo["genType"] == true) {
                                $woCCNo = $woccaVNo["voucherNo"];
                            }

                            $wocoaVNo = CommonHelper::getVoucherNo(401, date('Y-m-d', strtotime($postData['WODate'])), $postData['CompanyId'], 0, $dbAdapter, "I");
                            if ($wocoaVNo["genType"] == true) {
                                $woCoNo = $wocoaVNo["voucherNo"];
                            }
                        } else {
                            $woNewNo = explode('_', $woNo);
                            if(!isset($woNewNo[1])) {
                                $woNo =  $woNo.'_1';
                            } else {
                                $incWoNo = ($woNewNo[1] + 1);
                                $woNo =  $woNewNo[0].'_'.$incWoNo;
                            }
//                            print_r($woNo);die;

                            $amendment = 1;
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_WORegister');
                        $insert->Values(array('WODate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['WODate'], 'string')))
                        , 'WONo' => $this->bsf->isNullCheck($woNo, 'string')
                        , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                        , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                        , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                        , 'CCWONo' => $this->bsf->isNullCheck($woCCNo, 'string')
                        , 'CompWONo' => $this->bsf->isNullCheck($woCoNo, 'string')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['VendorId'], 'number')
                        , 'WOType' => $WorkType
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCenterId'], 'string')
                        , 'Amount' => $this->bsf->isNullCheck($postData['total'], 'number')
                        , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                        , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['Notes'], 'string')
                        , 'Amendment' => $this->bsf->isNullCheck($amendment, 'number')
                        , 'AWORegisterId' => $this->bsf->isNullCheck($amdId, 'number')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $WorkOrderId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $i=1;
                        $qRowCount =   $this->bsf->isNullCheck($postData['QualRRowId__'.$i],'number');
                        for ($k = 1; $k <= $qRowCount; $k++) {
                            $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                            $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                            $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                            $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                            $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                            $iQualTypeId= $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                            $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

                            $dCessPer = 0;
                            $dEDPer = 0;
                            $dHEdPer = 0;
                            $dKKCess=0;
                            $dSBCess=0;
                            $dCessAmt = 0;
                            $dEDAmt = 0;
                            $dHEdAmt = 0;
                            $dKKCessAmt=0;
                            $dSBCessAmt=0;

                            if ($iQualTypeId==1) {
                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                            } else if ($iQualTypeId==2) {

                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                            } else {
                                $dTaxablePer = 100;
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_WOQualTrans');
                            $insert->Values(array('WORegisterId' =>$WorkOrderId,
                                'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCess,'SBCess'=>$dSBCess,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt,'NetAmt'=>$dNetAmt,'MixType'=>'S'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $termsTotal = $postData['trowid'];
                        $valueFrom = 0;
                        if($postData['valuefrom'] == 'BaseAmount')
                        {
                            $valueFrom=0;
                        }
                        else if($postData['valuefrom'] == 'NetAmount')
                        {
                            $valueFrom=1;
                        }
                        else if($postData['valuefrom'] == 'GrossAmount')
                        {
                            $valueFrom=2;
                        }
                        for ($t = 1; $t < $termsTotal; $t++) {
                            if($this->bsf->isNullCheck($postData['termsid_' . $t],'number') > 0) {
                                $TDate = 'NULL';
                                if ($postData['date_' . $t] == '' || $postData['date_' . $t] == null) {
                                    $TDate = null;
                                } else {
                                    $TDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date_' . $t], 'string')));
                                }
                                $termsInsert = $sql->insert('WPM_WOGeneralTerms');
                                $termsInsert->values(array("WORegisterId" => $WorkOrderId, "TermsId" => $this->bsf->isNullCheck($postData['termsid_' . $t],'number'),
                                    "Per" => $this->bsf->isNullCheck($postData['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postData['value_' . $t], 'number'), "Period" => $postData['period_' . $t],
                                    "TDate" => $TDate, "TString" => $postData['string_' . $t], "ValueFromNet" => $valueFrom));
                                $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
//                            $this->_view->valuefrom = 0;

                        $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                        if($OrderType == 'work' && $WorkType == 'turn-key') {
                            for ($i = 1; $i <= $rowid; $i++) {
                                $descid = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                                $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');
                                $Variance = $this->bsf->isNullCheck($postData['variance_' . $i], 'number');
                                $Area = $this->bsf->isNullCheck($postData['area_' . $i], 'number');
                                $unitid = $this->bsf->isNullCheck($postData['unitid_' . $i], 'number');

                                if ($descid == 0 || $Amount == 0)
                                    continue;


                                $insert = $sql->insert();
                                $insert->into('WPM_WOTurnKeyTrans');
                                $insert->Values(array('WORegisterId' => $WorkOrderId, 'WBSId' => $descid, 'UnitId' => $unitid, 'Rate' => $Rate,
                                    'Amount' => $Amount, 'Area' => $Area, 'Variance' => $Variance));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $WOTurnKeyTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $unitid = $this->bsf->isNullCheck($postData['iow_' . $i . '_unitid_' . $j], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    if ($IOWId == 0 || $Amount == 0)
                                        continue;

                                    $insert = $sql->insert();
                                    $insert->into('WPM_WOTurnKeyIOWTrans');
                                    $insert->Values(array('WOTurnKeyTransId' => $WOTurnKeyTransId, 'IOWId' => $IOWId, 'UnitId' => $unitid,
                                        'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        } else {
                            //boq
                            for ($i = 1; $i <= $rowid; $i++) {
                                $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                                $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');

                                if ($DescId == 0)
                                    continue;

                                if ($OrderType == 'work' && $WorkType == 'activity') {
                                    $descIdColumn = 'ResourceId';
                                } else if ($OrderType == 'work' && $WorkType == 'iow') {
                                    $descIdColumn = 'IOWId';
                                }

                                $insert = $sql->insert();
                                $insert->into('WPM_WOTrans');
                                $insert->Values(array('WORegisterId' => $WorkOrderId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $WOTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');

                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $WBSId = $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    if (($IOWId == 0 && $WBSId == 0) || $qty == 0)
                                        continue;

                                    $IOWTransId = 0;
                                    $WBSTransId = 0;
                                    if ($OrderType == 'work' && $WorkType == 'activity') {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_WOIOWTrans');
                                        $insert->Values(array('WOTransId' => $WOTransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $IOWTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    } else if ($OrderType == 'work' && $WorkType == 'iow') {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_WOWBSTrans');
                                        $insert->Values(array('WOTransId' => $WOTransId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $WBSTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }

                                    $requestrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_rowid'], 'number');
                                    for ($k = 1; $k <= $requestrowid; $k++) {
                                        $RequestTransId = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_requesttransid_' . $k], 'number');
                                        $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_qty_' . $k], 'number');

                                        if ($RequestTransId == 0 || $qty == 0)
                                            continue;

                                        $insert = $sql->insert();
                                        $insert->into('WPM_WORequestTrans');
                                        $insert->Values(array('WOTransId' => $WOTransId, 'WOIOWTransId' => $IOWTransId, 'WOWBSTransId' => $WBSTransId, 'RequestTransId' => $RequestTransId, 'Qty' => $Qty));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        //$WOTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }
                                }
                            }

                        }

                        // terms & conditions
                        // Other Terms fields
                        $termrowid = $this->bsf->isNullCheck($postData['termrowid'], 'number');
                        for ($i = 1; $i <= $termrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['termTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['termDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('WPM_WOOtherTerms');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'TermsTitle' => $TermsTitle, 'TermsDescription' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Tech Terms
                        $techrowid = $this->bsf->isNullCheck($postData['techrowid'], 'number');
                        for ($i = 1; $i <= $techrowid; $i++) {
                            $TermsTitle = $this->bsf->isNullCheck($postData['techTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['techDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('WPM_WOTechSpec');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'Title' => $TermsTitle, 'Specification' => $TermsDescription));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Material Base Rate fields
                        $materialrowid = $this->bsf->isNullCheck($postData['materialrowid'], 'number');
                        for ($i = 1; $i <= $materialrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialId_' . $i], 'number');
                            $Rate = $this->bsf->isNullCheck($postData['materialRate_' . $i], 'number');
                            $escper = $this->bsf->isNullCheck($postData['escalationper_' . $i], 'number');
                            $NewRate = $this->bsf->isNullCheck($postData['materialnewRate_' . $i], 'number');
                            $RateBase = $this->bsf->isNullCheck($postData['materialbase_' . $i], 'string');

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('WPM_WOMaterialBaseRate');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $MaterialId, 'Rate' => $Rate, 'EscalationPer' => $escper, 'RateCondition' => $RateBase, 'ActualRate' => $NewRate));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        // Material Exclude fields
                        $materialExcrowid = $this->bsf->isNullCheck($postData['materialExcrowid'], 'number');
                        for ($i = 1; $i <= $materialExcrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialExcId_' . $i], 'number');
                            $MaterialRate = $this->bsf->isNullCheck($postData['materialExcRate_' . $i], 'number');
                            $SupplyType = $this->bsf->isNullCheck($postData['materialExcType_' . $i], 'string');

                            if ($MaterialId == 0)
                                continue;

                            $insert = $sql->insert();
                            $insert->into('WPM_WOExcludeMaterial');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $MaterialId, 'Rate' => $MaterialRate, 'SType' => $SupplyType));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        // Material Advance fields
                        $materialAdvrowid = $this->bsf->isNullCheck($postData['materialAdvrowid'], 'number');
                        for ($i = 1; $i <= $materialAdvrowid; $i++) {
                            $MaterialId = $this->bsf->isNullCheck($postData['materialAdvId_' . $i], 'number');
                            $Rate = $this->bsf->isNullCheck($postData['materialAdvRate_' . $i], 'number');

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            $insert = $sql->insert();
                            $insert->into('WPM_WOMaterialAdvance');
                            $insert->Values(array('WORegisterId' => $WorkOrderId, 'MaterialId' => $MaterialId, 'AdvPercent' => $Rate));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        if($amdId != 0) {
                            $update = $sql->update();
                            $update->table('WPM_WORegister');
                            $update->set(array('LiveWO' => 0));
                            $update->where(array('WORegisterId' => $amdId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), 'WPM-WorkOrder-Add', 'N', 'WorkOrder-Add', $WorkOrderId, 0, 0, 'WPM', $woNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'index'));
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                } else if($editId != 0) {
                    // edit entry
                    try {
                        $connection->beginTransaction();

                        $woNo = $postData['WONo'];

                        $update = $sql->update();
                        $update->table('WPM_WORegister');
                        $update->set(array('WODate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['WODate'], 'string')))
                        , 'WONo' => $this->bsf->isNullCheck($woNo, 'string')
                        , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                        , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                        , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                        , 'CCWONo' => $this->bsf->isNullCheck($postData['CCWONo'], 'string')
                        , 'CompWONo' => $this->bsf->isNullCheck($postData['CompWONo'], 'string')
                        , 'VendorId' => $this->bsf->isNullCheck($postData['VendorId'], 'number')
                        , 'WOType' => $this->bsf->isNullCheck($postData['WorkType'], 'string')
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCenterId'], 'string')
                        , 'Amount' => $this->bsf->isNullCheck($postData['total'], 'number')
                        , 'QualifiedAmount' => $this->bsf->isNullCheck($postData['qualAmt'], 'number')
                        , 'NetAmount' => $this->bsf->isNullCheck($postData['totAmount'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['Notes'], 'string')
                        ));
                        $update->where(array('WORegisterId' => $editId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Proj_WOQualTrans');
                        $delete ->where(array("WORegisterId"=>$editId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delPOPayTrans = $sql -> delete();
                        $delPOPayTrans -> from ('WPM_WOGeneralTerms')
                            -> where (array("WORegisterId" => $editId));
                        $POPayStatement = $sql->getSqlStringForSqlObject($delPOPayTrans);
                        $dbAdapter->query($POPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $termsTotal = $postData['trowid'];
                        $valueFrom = 0;
                        if($postData['valuefrom'] == 'BaseAmount')
                        {
                            $valueFrom=0;
                        }
                        else if($postData['valuefrom'] == 'NetAmount')
                        {
                            $valueFrom=1;
                        }
                        else if($postData['valuefrom'] == 'GrossAmount')
                        {
                            $valueFrom=2;
                        }
                        for ($t = 1; $t < $termsTotal; $t++) {
                            if($this->bsf->isNullCheck($postData['termsid_' . $t],'number') > 0) {
                                $TDate = 'NULL';
                                if ($postData['date_' . $t] == '' || $postData['date_' . $t] == null) {
                                    $TDate = null;
                                } else {
                                    $TDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date_' . $t], 'string')));
                                }
                                $termsInsert = $sql->insert('WPM_WOGeneralTerms');
                                $termsInsert->values(array("WORegisterId" => $editId, "TermsId" => $this->bsf->isNullCheck($postData['termsid_' . $t],'number'),
                                    "Per" => $this->bsf->isNullCheck($postData['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postData['value_' . $t], 'number'), "Period" => $postData['period_' . $t],
                                    "TDate" => $TDate, "TString" => $postData['string_' . $t], "ValueFromNet" => $valueFrom));
                                $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $i=1;
                        $qRowCount =   $this->bsf->isNullCheck($postData['QualRRowId__'.$i],'number');
                        for ($k = 1; $k <= $qRowCount; $k++) {
                            $iQualifierId = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Id_' . $k], 'number');
                            $iYesNo = isset($postData['QualR__' . $i . '_YesNo_' . $k]) ? 1 : 0;
                            $sExpression = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Exp_' . $k], 'string');
                            $dExpAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                            $dExpPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                            $iQualTypeId= $this->bsf->isNullCheck($postData['QualR__' . $i . '_TypeId_' . $k], 'number');
                            $sSign = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Sign_' . $k], 'string');

                            $dCessPer = 0;
                            $dEDPer = 0;
                            $dHEdPer = 0;
                            $dKKCess=0;
                            $dSBCess=0;
                            $dCessAmt = 0;
                            $dEDAmt = 0;
                            $dHEdAmt = 0;
                            $dKKCessAmt=0;
                            $dSBCessAmt=0;

                            if ($iQualTypeId==1) {
                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                $dCessPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessPer_' . $k], 'number');
                                $dEDPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessPer_' . $k], 'number');
                                $dHEdPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_CessAmt_' . $k], 'number');
                                $dEDAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_EduCessAmt_' . $k], 'number');
                                $dHEdAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_HEduCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');
                            } else if ($iQualTypeId==2) {

                                $dTaxablePer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxablePer_' . $k], 'number');
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPer_' . $k], 'number');
                                $dKKCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCess_' . $k], 'number');
                                $dSBCess = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCess_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetPer_' . $k], 'number');

                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxableAmt_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_TaxPerAmt_' . $k], 'number');
                                $dKKCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_KKCessAmt_' . $k], 'number');
                                $dSBCessAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_SBCessAmt_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_NetAmt_' . $k], 'number');

                            } else {
                                $dTaxablePer = 100;
                                $dTaxPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $dNetPer = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpPer_' . $k], 'number');
                                $dTaxableAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_ExpValue_' . $k], 'number');
                                $dTaxAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                                $dNetAmt = $this->bsf->isNullCheck($postData['QualR__' . $i . '_Amount_' . $k], 'number');
                            }

                            $insert = $sql->insert();
                            $insert->into('Proj_WOQualTrans');
                            $insert->Values(array('WORegisterId' =>$editId,
                                'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                                'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'KKCess'=>$dKKCess,'SBCess'=>$dSBCess,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                                'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'KKCessAmt'=>$dKKCessAmt,'SBCessAmt'=>$dSBCessAmt,'NetAmt'=>$dNetAmt,'MixType'=>'R'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }


                        $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                        if($OrderType == 'work' && $WorkType == 'turn-key') {
                            // delete boqs
                            $deleteids = rtrim($this->bsf->isNullCheck($postData['deleteids'],'string'), ",");
                            if($deleteids !== '' && $deleteids != '0') {
                                $delete = $sql->delete();
                                $delete->from('WPM_WOTurnKeyIOWTrans')
                                    ->where("WOTurnKeyTransId IN ($deleteids)");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $delete = $sql->delete();
                                $delete->from('WPM_WOTurnKeyTrans')
                                    ->where("WOTurnKeyTransId IN ($deleteids)");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            for ($i = 1; $i <= $rowid; $i++) {
                                $WOTurnKeyTransId = $this->bsf->isNullCheck($postData['WOTransId_' . $i], 'number');
                                $UpdateRow = $this->bsf->isNullCheck($postData['UpdateRow_' . $i], 'number');
                                $descid = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                                $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');
                                $Variance = $this->bsf->isNullCheck($postData['variance_' . $i], 'number');
                                $Area = $this->bsf->isNullCheck($postData['area_' . $i], 'number');
                                $unitid = $this->bsf->isNullCheck($postData['unitid_' . $i], 'number');

                                if ($descid == 0 || $Amount == 0)
                                    continue;

                                if ($UpdateRow == 0 && $WOTurnKeyTransId == 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_WOTurnKeyTrans');
                                    $insert->Values(array('WORegisterId' => $editId, 'WBSId' => $descid, 'UnitId' => $unitid, 'Rate' => $Rate,
                                        'Amount' => $Amount, 'Area' => $Area, 'Variance' => $Variance));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $WOTurnKeyTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                } else if ($UpdateRow == 1 && $WOTurnKeyTransId != 0) {
                                    $update = $sql->update();
                                    $update->table('WPM_WOTurnKeyTrans');
                                    $update->set(array('WORegisterId' => $editId, 'WBSId' => $descid, 'UnitId' => $unitid, 'Rate' => $Rate,
                                        'Amount' => $Amount, 'Area' => $Area, 'Variance' => $Variance));
                                    $update->where(array('WOTurnKeyTransId' => $WOTurnKeyTransId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $WOIOWTransId = $this->bsf->isNullCheck($postData['iow_' . $i . '_WOIOWTransId_' . $j], 'number');
                                    $UpdateRow = $this->bsf->isNullCheck($postData['iow_' . $i . '_UpdateRow_' . $j], 'number');
                                    $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $unitid = $this->bsf->isNullCheck($postData['iow_' . $i . '_unitid_' . $j], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    if ($IOWId == 0 || $Amount == 0)
                                        continue;

                                    if ($UpdateRow == 0 && $WOIOWTransId == 0) {
                                        $insert = $sql->insert();
                                        $insert->into('WPM_WOTurnKeyIOWTrans');
                                        $insert->Values(array('WOTurnKeyTransId' => $WOTurnKeyTransId, 'IOWId' => $IOWId, 'UnitId' => $unitid,
                                            'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else if ($UpdateRow == 1 && $WOIOWTransId != 0) {
                                        $update = $sql->update();
                                        $update->table('WPM_WOTurnKeyIOWTrans');
                                        $update->set(array('WOTurnKeyTransId' => $WOTurnKeyTransId, 'IOWId' => $IOWId, 'UnitId' => $unitid,
                                            'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                        $update->where(array('WOTurnKeyIOWTransId' => $WOIOWTransId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                        } else {
                            // delete boqs
                            $deleteids = rtrim($this->bsf->isNullCheck($postData['deleteids'],'string'), ",");
                            if($deleteids !== '' && $deleteids != '0') {
                                $subQuery = $sql->select();
                                $subQuery->from("WPM_WOTrans")
                                    ->columns(array('WOTransId'))
                                    ->where("WOTransId IN ($deleteids)");

                                if ($OrderType == 'work' && $WorkType == 'activity') {
                                    $subQuery1 = $sql->select();
                                    $subQuery1->from("WPM_WOIOWTrans")
                                        ->columns(array('WOIOWTransId'))
                                        ->where->expression('WOTransId IN ?', array($subQuery));

                                    $delete = $sql->delete();
                                    $delete->from('WPM_WORequestTrans')
                                        ->where->expression('WOIOWTransId IN ?', array($subQuery1));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $delete = $sql->delete();
                                    $delete->from('WPM_WOIOWTrans')
                                        ->where->expression('WOTransId IN ?', array($subQuery));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else if ($OrderType == 'work' && $WorkType == 'iow') {
                                    $subQuery2 = $sql->select();
                                    $subQuery2->from("WPM_WOWBSTrans")
                                        ->columns(array('WOWBSTransId'))
                                        ->where->expression('WOTransId IN ?', array($subQuery));

                                    $delete = $sql->delete();
                                    $delete->from('WPM_WORequestTrans')
                                        ->where->expression('WOWBSTransId IN ?', array($subQuery2));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $delete = $sql->delete();
                                    $delete->from('WPM_WOWBSTrans')
                                        ->where->expression('WOTransId IN ?', array($subQuery));
                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $delete = $sql->delete();
                                $delete->from('WPM_WOTrans')
                                    ->where("WOTransId IN ($deleteids)");
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            //boq
                            for ($i = 1; $i <= $rowid; $i++) {
                                $WOTransId = $this->bsf->isNullCheck($postData['WOTransId_' . $i], 'number');
                                $UpdateRow = $this->bsf->isNullCheck($postData['UpdateRow_' . $i], 'number');
                                $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                                $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                                $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');

                                if ($DescId == 0)
                                    continue;

                                if ($OrderType == 'work' && $WorkType == 'activity') {
                                    $descIdColumn = 'ResourceId';
                                } else if ($OrderType == 'work' && $WorkType == 'iow') {
                                    $descIdColumn = 'IOWId';
                                }

                                if ($UpdateRow == 0 && $WOTransId == 0) {
                                    $insert = $sql->insert();
                                    $insert->into('WPM_WOTrans');
                                    $insert->Values(array('WORegisterId' => $editId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $WOTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                } else if ($UpdateRow == 1 && $WOTransId != 0) {
                                    $update = $sql->update();
                                    $update->table('WPM_WOTrans');
                                    $update->set(array('WORegisterId' => $editId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                    $update->where(array('WOTransId' => $WOTransId));
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                                for ($j = 1; $j <= $iowrowid; $j++) {
                                    $WOIOWTransId = $this->bsf->isNullCheck($postData['iow_' . $i . '_WOIOWTransId_' . $j], 'number');
                                    $UpdateRow = $this->bsf->isNullCheck($postData['iow_' . $i . '_UpdateRow_' . $j], 'number');
                                    $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                    $WBSId = $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number');
                                    $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                    $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                    $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');

                                    if (($IOWId == 0 && $WBSId == 0) || $qty == 0)
                                        continue;

                                    if ($OrderType == 'work' && $WorkType == 'activity') {
                                        if ($UpdateRow == 0 && $WOIOWTransId == 0) {
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WOIOWTrans');
                                            $insert->Values(array('WOTransId' => $WOTransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $WOIOWTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($UpdateRow == 1 && $WOIOWTransId != 0) {
                                            $update = $sql->update();
                                            $update->table('WPM_WOIOWTrans');
                                            $update->set(array('WOTransId' => $WOTransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $update->where(array('WOIOWTransId' => $WOIOWTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    } else if ($OrderType == 'work' && $WorkType == 'iow') {
                                        if ($UpdateRow == 0 && $WOIOWTransId == 0) {
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WOWBSTrans');
                                            $insert->Values(array('WOTransId' => $WOTransId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $WOIOWTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($UpdateRow == 1 && $WOIOWTransId != 0) {
                                            $update = $sql->update();
                                            $update->table('WPM_WOWBSTrans');
                                            $update->set(array('WOTransId' => $WOTransId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount));
                                            $update->where(array('WOWBSTransId' => $WOIOWTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }

                                    $requestrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_rowid'], 'number');
                                    for ($k = 1; $k <= $requestrowid; $k++) {
                                        $WOIPDTransId = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_WOIPDTransId_' . $k], 'number');
                                        $UpdateRow = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_UpdateRow_' . $k], 'number');
                                        $RequestTransId = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_requesttransid_' . $k], 'number');
                                        $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_request_' . $j . '_curqty_' . $k], 'number');

                                        if ($RequestTransId == 0 || $qty == 0)
                                            continue;

                                        if ($OrderType == 'work' && $WorkType == 'activity') {
                                            $fieldName = 'WOIOWTransId';
                                        } else if ($OrderType == 'work' && $WorkType == 'iow') {
                                            $fieldName = 'WOWBSTransId';
                                        }

                                        if ($UpdateRow == 0 && $WOIPDTransId == 0) {
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WORequestTrans');
                                            $insert->Values(array('WOTransId' => $WOTransId, $fieldName => $WOIOWTransId, 'RequestTransId' => $RequestTransId, 'Qty' => $Qty));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $WOTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($UpdateRow == 1 && $WOIPDTransId != 0) {
                                            $update = $sql->update();
                                            $update->table('WPM_WORequestTrans');
                                            $update->set(array('WOTransId' => $WOTransId, $fieldName => $WOIOWTransId, 'RequestTransId' => $RequestTransId, 'Qty' => $Qty));
                                            $update->where(array('WOIPDTransId' => $WOIPDTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            }
                        }

                        // terms & conditions
                        // Other Terms fields
                        $deleteids = rtrim($this->bsf->isNullCheck($postData['termdeleteids'],'string'), ",");
                        if($deleteids != '' && $deleteids != '0') {
                            $delete = $sql->delete();
                            $delete->from('WPM_WOOtherTerms')
                                ->where("TransId IN ($deleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $termrowid = $this->bsf->isNullCheck($postData['termrowid'], 'number');
                        for ($i = 1; $i <= $termrowid; $i++) {
                            $TermsId = $this->bsf->isNullCheck($postData['termtransid_' . $i], 'number');
                            $TermsUpdateRow = $this->bsf->isNullCheck($postData['termupdaterow_' . $i], 'number');
                            $TermsTitle = $this->bsf->isNullCheck($postData['termTitle_' . $i], 'string');
                            $TermsDescription = $this->bsf->isNullCheck($postData['termDesc_' . $i], 'string');

                            if ($TermsTitle == '' || $TermsDescription == '')
                                continue;

                            if($TermsUpdateRow == 0 && $TermsId == 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_WOOtherTerms');
                                $insert->Values(array('WORegisterId' => $editId, 'TermsTitle' => $TermsTitle, 'TermsDescription' => $TermsDescription));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else if ($TermsUpdateRow == 1 && $TermsId != 0) {
                                $update = $sql->update();
                                $update->table('WPM_WOOtherTerms');
                                $update->set(array('WORegisterId' => $editId, 'TermsTitle' => $TermsTitle, 'TermsDescription' => $TermsDescription));
                                $update->where(array('TransId' => $TermsId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        // Tech Terms
                        $deleteids = rtrim($this->bsf->isNullCheck($postData['techdeleteids'],'string'), ",");
                        if($deleteids !== '' && $deleteids != '0') {
                            $delete = $sql->delete();
                            $delete->from('WPM_WOTechSpec')
                                ->where("TransId IN ($deleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $techrowid = $this->bsf->isNullCheck($postData['techrowid'], 'number');
                        for ($i = 1; $i <= $techrowid; $i++) {
                            $TechId = $this->bsf->isNullCheck($postData['termtransid_' . $i], 'number');
                            $TechUpdateRow = $this->bsf->isNullCheck($postData['termupdaterow_' . $i], 'number');
                            $TechTitle = $this->bsf->isNullCheck($postData['techTitle_' . $i], 'string');
                            $TechDescription = $this->bsf->isNullCheck($postData['techDesc_' . $i], 'string');

                            if ($TechTitle == '' || $TechDescription == '')
                                continue;

                            if($TechUpdateRow == 0 && $TechId == 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_WOTechSpec');
                                $insert->Values(array('WORegisterId' => $editId, 'Title' => $TechTitle, 'Specification' => $TechDescription));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else if ($TechUpdateRow == 1 && $TechId != 0) {
                                $update = $sql->update();
                                $update->table('WPM_WOTechSpec');
                                $update->set(array('WORegisterId' => $editId, 'Title' => $TechTitle, 'Specification' => $TechDescription));
                                $update->where(array('TransId' => $TechId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }
                        }

                        // Material Base Rate fields
                        $deleteids = rtrim($this->bsf->isNullCheck($postData['materialdeleteids'],'string'), ",");
                        if($deleteids !== '' && $deleteids != '0') {
                            $delete = $sql->delete();
                            $delete->from('WPM_WOMaterialBaseRate')
                                ->where("TransId IN ($deleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $materialrowid = $this->bsf->isNullCheck($postData['materialrowid'], 'number');
                        for ($i = 1; $i <= $materialrowid; $i++) {
                            $MaterialTransId = $this->bsf->isNullCheck($postData['materialtransid_' . $i], 'number');
                            $MaterialUpdateRow = $this->bsf->isNullCheck($postData['materialupdaterow_' . $i], 'number');
                            $MaterialId = $this->bsf->isNullCheck($postData['materialId_' . $i], 'number');
                            $Rate = $this->bsf->isNullCheck($postData['materialRate_' . $i], 'number');
                            $escper = $this->bsf->isNullCheck($postData['escalationper_' . $i], 'number');
                            $NewRate = $this->bsf->isNullCheck($postData['materialnewRate_' . $i], 'number');
                            $RateBase = $this->bsf->isNullCheck($postData['materialbase_' . $i], 'string');

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            if($MaterialUpdateRow == 0 && $MaterialTransId == 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_WOMaterialBaseRate');
                                $insert->Values(array('WORegisterId' => $editId, 'MaterialId' => $MaterialId, 'Rate' => $Rate, 'EscalationPer' => $escper, 'RateCondition' => $RateBase, 'ActualRate' => $NewRate));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else if ($MaterialUpdateRow == 1 && $MaterialTransId != 0) {
                                $update = $sql->update();
                                $update->table('WPM_WOMaterialBaseRate');
                                $update->set(array('WORegisterId' => $editId, 'MaterialId' => $MaterialId, 'Rate' => $Rate, 'EscalationPer' => $escper, 'RateCondition' => $RateBase, 'ActualRate' => $NewRate));
                                $update->where(array('TransId' => $MaterialTransId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        // Material Exclude fields
                        $deleteids = rtrim($this->bsf->isNullCheck($postData['materialexcdeleteids'],'string'), ",");
                        if($deleteids !== '' && $deleteids != '0') {
                            $delete = $sql->delete();
                            $delete->from('WPM_WOExcludeMaterial')
                                ->where("TransId IN ($deleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $materialExcrowid = $this->bsf->isNullCheck($postData['materialExcrowid'], 'number');
                        for ($i = 1; $i <= $materialExcrowid; $i++) {
                            $MaterialTransId = $this->bsf->isNullCheck($postData['materialexctransid_' . $i], 'number');
                            $MaterialUpdateRow = $this->bsf->isNullCheck($postData['materialexcupdaterow_' . $i], 'number');
                            $MaterialId = $this->bsf->isNullCheck($postData['materialExcId_' . $i], 'number');
                            $MaterialRate = $this->bsf->isNullCheck($postData['materialExcRate_' . $i], 'number');
                            $SupplyType = $this->bsf->isNullCheck($postData['materialExcType_' . $i], 'string');

                            if ($MaterialId == 0)
                                continue;

                            if($MaterialUpdateRow == 0 && $MaterialTransId == 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_WOExcludeMaterial');
                                $insert->Values(array('WORegisterId' => $editId, 'MaterialId' => $MaterialId, 'Rate' => $MaterialRate, 'SType' => $SupplyType));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else if ($MaterialUpdateRow == 1 && $MaterialTransId != 0) {
                                $update = $sql->update();
                                $update->table('WPM_WOExcludeMaterial');
                                $update->set(array('WORegisterId' => $editId, 'MaterialId' => $MaterialId, 'Rate' => $MaterialRate, 'SType' => $SupplyType));
                                $update->where(array('TransId' => $MaterialTransId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        // Material Advance fields
                        $deleteids = rtrim($this->bsf->isNullCheck($postData['materialadvdeleteids'],'string'), ",");
                        if($deleteids !== '' && $deleteids != '0') {
                            $delete = $sql->delete();
                            $delete->from('WPM_WOMaterialAdvance')
                                ->where("TransId IN ($deleteids)");
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $materialAdvrowid = $this->bsf->isNullCheck($postData['materialAdvrowid'], 'number');
                        for ($i = 1; $i <= $materialAdvrowid; $i++) {
                            $MaterialTransId = $this->bsf->isNullCheck($postData['materialadvtransid_' . $i], 'number');
                            $MaterialUpdateRow = $this->bsf->isNullCheck($postData['materialadvupdaterow_' . $i], 'number');
                            $MaterialId = $this->bsf->isNullCheck($postData['materialAdvId_' . $i], 'number');
                            $Rate = $this->bsf->isNullCheck($postData['materialAdvRate_' . $i], 'number');

                            if ($MaterialId == 0 || $Rate == '')
                                continue;

                            if($MaterialUpdateRow == 0 && $MaterialTransId == 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_WOMaterialAdvance');
                                $insert->Values(array('WORegisterId' => $editId, 'MaterialId' => $MaterialId, 'AdvPercent' => $Rate));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else if ($MaterialUpdateRow == 1 && $MaterialTransId != 0) {
                                $update = $sql->update();
                                $update->table('WPM_WOMaterialAdvance');
                                $update->set(array('WORegisterId' => $editId, 'MaterialId' => $MaterialId, 'AdvPercent' => $Rate));
                                $update->where(array('TransId' => $MaterialTransId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), 'WPM-WorkOrder-Edit', 'E', 'WorkOrder-Edit', $editId, 0, 0, 'WPM', $woNo, $this->auth->getIdentity()->UserId, 0, 0);
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'register'));
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                }
            } else {
                // get request
                if($editId != 0) {
                    // register details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WPM_WORegister' ) )
                        ->join(array('b' => 'WF_CostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->columns( array("WORegisterId", "WONo", "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')"), "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")
                        , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")
                        , "RefNo", "CCWONo", "CompWONo", "WOType", "Amount", "QualifiedAmount","NetAmount",'Narration', 'CostCentreId', 'VendorId', 'Approve') )
                        ->where( "a.DeleteFlag=0 AND a.WORegisterId=$editId" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->woregister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $OrderType = 'work';
                    $WorkType = $this->_view->woregister['WOType'];

                    $this->_view->OrderType = $OrderType;
                    $this->_view->WorkType = $WorkType;

                    // cost center details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                        ->columns( array( 'CostCentreId', 'CostCentreName', 'ProjectId', 'WBSReqWPM', 'CompanyId') )
                        ->where( "Deactivate=0 AND CostCentreId=".$this->_view->woregister['CostCentreId'] );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $projectId = $this->_view->costcenter['ProjectId'];
                    $this->_view->projectId = $projectId;
                    $this->_view->wbsReq = $this->_view->costcenter['WBSReqWPM'];

                    // vendor details
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId','VendorName','LogoPath'))
                        ->where( "VendorId=".$this->_view->woregister['VendorId'] );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->vendor = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_WOQualTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt','KKCessAmt','SBCessAmt', 'NetAmt'));
                    $select->where(array('a.MixType'=>'S','a.WORegisterId'=>$editId));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml = Qualifier::getQualifier($qualList,"R");
                    $this->_view->qualHtml = $sHtml;
                    $sHtml=Qualifier::getQualifier($qualList,"R");
                    $this->_view->qualRHtml = $sHtml;

                    $selTer = $sql -> select();
                    $selTer -> from (array("a" => "WPM_WOGeneralTerms"))
                        ->columns(array("ValueFromNet"))
                        ->where('WORegisterId='.$editId.'');
                    $terStatement = $sql->getSqlStringForSqlObject($selTer);
                    $terResult = $dbAdapter->query($terStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->valuefrom=$this->bsf->isNullCheck($terResult['ValueFromNet'],'number');

                    $select = $sql->select();
                    $select->from(array("a" => "WF_TermsMaster"))
                        //->columns(array('data' => 'TermsId',))
                        ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,3)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross")))
                        ->where(array("TermType"=>'S'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "WF_TermsMaster"))
                        //->columns(array('data' => 'TermsId',))
                        ->columns(array(new Expression("a.TermsId As data,a.SlNo,a.Title As value,CAST(b.Per As Decimal(18,3)) As Per,
                                CAST(b.Value As Decimal(18,3)) As Val,b.Period As Period,b.TDate As [Dte],b.TString As [Strg],a.Per As IsPer,
                                a.Value As IsValue,a.Period As IsPeriod,a.TDate As IsTDate,a.TSTring As IsTString,a.IncludeGross")))
                        ->join(array('b'=>'WPM_WOGeneralTerms'),'a.TermsId=b.TermsId',array(),$select::JOIN_INNER)
                        ->where(array("b.WORegisterId"=>$editId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_edit_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if($OrderType == 'work' && $WorkType == 'activity') {
                        // get resource lists
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Desc' => new Expression("b.Code+ ' ' +b.ResourceName"), 'Rate'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ProjectResource'), 'b.ResourceId=d.ResourceId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('WOTransId','DescId' => 'ResourceId','Qty','Rate', 'Amount'))
                            ->where('a.WORegisterId='.$editId)
                            ->where("d.ProjectId=".$projectId)
                            ->group(new Expression('a.WOTransId,a.ResourceId,a.Qty,a.Rate,a.Amount,b.Code,b.ResourceName,b.Rate,c.UnitName,c.UnitId,d.Qty'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_WOTrans")
                            ->columns(array('WOTransId'))
                            ->where('WORegisterId ='.$editId);

                        $resQuery = $sql->select();
                        $resQuery->from("WPM_WOTrans")
                            ->columns(array('ResourceId'))
                            ->where('WORegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOIOWTrans"))
                            ->join(array('f' => 'Proj_ProjectDetails'), 'a.IOWId=f.ProjectIOWId', array('eQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('e' => 'Proj_ProjectIOW'), 'a.IOWId=e.ProjectIOWId', array('bQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array('cQty' => 'Qty'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('WOIOWTransId', 'WOTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'Rate', 'Amount'))
                            ->group(new Expression('a.WOIOWTransId,a.WOTransId,a.IOWId,a.Qty,a.Rate,a.Amount,f.Qty,b.RefSerialNo,b.Specification,e.Qty,c.Qty,d.WBSId,d.WBSName,d.ParentText'))
                            ->where->expression('a.WOTransId IN ?', array($subQuery))
                            ->where->expression('f.ResourceId IN ?', array($resQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery1 = $sql->select();
                        $subQuery1->from("WPM_WOIOWTrans")
                            ->columns(array('WOIOWTransId'))
                            ->where->expression('WOTransId IN ?', array($subQuery));

                        $select = $sql->select();
                        $select->from(array("c" => "WPM_WORequestTrans"))
                            ->join(array('d' => 'VM_RequestIowTrans'), 'c.RequestTransId=d.RequestTransId', array('Qty', 'BalQty', 'WOQty'), $select::JOIN_LEFT)
                            ->join(array('a' => 'VM_RequestTrans'), 'c.RequestTransId=a.RequestTransId', array('ResourceId'), $select::JOIN_LEFT)
                            ->join(array('b' => 'VM_RequestRegister'), 'a.RequestId=b.RequestId', array('RequestId','RequestNo', 'RequestDate'), $select::JOIN_LEFT)
                            ->columns(array('WOIPDTransId','RequestTransId', 'IowId' => 'WOIOWTransId', 'WbsId' => 'WOWBSTransId','CurQty' => 'Qty'))
                            ->order('a.ResourceId')
                            ->where->expression('c.WOIOWTransId IN ?', array($subQuery1));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                    }
                    else if($OrderType == 'work' && $WorkType == 'iow') {
                        // get iow lists
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('Desc' => new Expression("b.RefSerialNo+ ' ' +b.Specification")), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ProjectIOW'), 'd.ProjectIOWId=b.ProjectIOWId', array('EstQty' => 'Qty', 'Rate'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('WOTransId', 'DescId' => 'IOWId', 'Qty', 'Amount'))
                            ->where('a.WORegisterId='.$editId)
                            ->where("b.ProjectId=".$projectId)
                            ->group(new Expression('a.WOTransId,a.IOWId,a.Qty,a.Amount,b.RefSerialNo,b.Specification,d.Rate,d.Qty,c.UnitName,c.UnitId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_WOTrans")
                            ->columns(array('WOTransId'))
                            ->where('WORegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOWBSTrans"))
                            ->join(array('d' => 'WPM_WOTrans'), 'a.WOTransId=d.WOTransId', array(), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'b.WBSId=c.WBSId AND d.IOWId=c.ProjectIOWId', array('EstQty' => 'Qty'), $select::JOIN_LEFT)
                            ->columns(array('WOIOWTransId' => 'WOWBSTransId', 'WOTransId', 'Qty', 'Rate', 'Amount'))
                            ->group(new Expression('a.WOWBSTransId,a.WOTransId,a.Qty,a.Rate,a.Amount,b.WBSId,b.WBSName,b.ParentText,c.Qty'))
                            ->where->expression('a.WOTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery1 = $sql->select();
                        $subQuery1->from("WPM_WOWBSTrans")
                            ->columns(array('WOWBSTransId'))
                            ->where->expression('WOTransId IN ?', array($subQuery));

                        $select = $sql->select();
                        $select->from(array("c" => "WPM_WORequestTrans"))
                            ->join(array('d' => 'VM_RequestIowTrans'), 'c.RequestTransId=d.RequestTransId', array('Qty', 'BalQty', 'WOQty'), $select::JOIN_LEFT)
                            ->join(array('a' => 'VM_RequestTrans'), 'c.RequestTransId=a.RequestTransId', array('ResourceId'), $select::JOIN_LEFT)
                            ->join(array('b' => 'VM_RequestRegister'), 'a.RequestId=b.RequestId', array('RequestId','RequestNo', 'RequestDate'), $select::JOIN_LEFT)
                            ->columns(array('WOIPDTransId','RequestTransId', 'IowId' => 'WOIOWTransId', 'WbsId' => 'WOWBSTransId','CurQty' => 'Qty'))
                            ->order('a.ResourceId')
                            ->where->expression('c.WOWBSTransId IN ?', array($subQuery1));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                    }
                    else if($OrderType == 'work' && $WorkType == 'turn-key') {
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTurnKeyTrans"))
                            ->join(array('b' => 'Proj_WBSTrans'), 'a.WBSId=b.WBSId', array('BOQRate' => new Expression("SUM(b.Rate)"),'BOQAmount' => new Expression("SUM(b.Amount)")), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSMaster'), 'a.WBSId=c.WBSId', array('Desc' => new Expression("c.ParentText+ '->' +c.WBSName")), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array('UnitName'), $select::JOIN_LEFT)
                            ->columns(array('WOTurnKeyTransId','DescId' => 'WBSId','UnitId', 'Rate', 'Amount', 'Area', 'Variance'))
                            ->where('a.WORegisterId='.$editId)
                            ->group(new Expression('a.WOTurnKeyTransId,a.WBSId,a.UnitId,c.ParentText,c.WBSName,a.Rate,a.Amount,a.Area,a.Variance,d.UnitName'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_WOTurnKeyTrans")
                            ->columns(array('WOTurnKeyTransId'))
                            ->where('WORegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTurnKeyIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array('UnitId','UnitName'), $select::JOIN_LEFT)
                            ->columns(array('WOTurnKeyIOWTransId','WOTurnKeyTransId','Qty','Rate', 'Amount','ProjectIOWId' => 'IOWId'))
                            ->where->expression('a.WOTurnKeyTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSMaster"))
                            ->join(array('b' => 'Proj_WBSTrans'), 'a.WBSId=b.WBSId', array('BOQRate' => new Expression("SUM(b.Rate)"),'BOQAmount' => new Expression("SUM(b.Amount)")), $select::JOIN_LEFT)
                            ->columns(array('value' => new Expression("a.ParentText+ '->' +a.WBSName"), 'data' => 'WBSId'))
                            ->where("a.ProjectId=".$projectId)
                            ->where('a.LastLevel = 1')
                            ->group(new Expression('a.WBSId,a.ParentText,a.WBSName'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    // Unit List
                    $select = $sql->select();
                    $select->from("Proj_UOM")
                        ->columns(array("data" => 'UnitId', "value" => 'UnitName'))
                        ->where("TypeId=2");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_units = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // Material List
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_Resource"))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("unit" => 'UnitName'), $select:: JOIN_LEFT)
                        ->columns(array("data" => 'ResourceId', "value" => new Expression("a.Code + ' ' + a.ResourceName")))
                        ->where("a.DeleteFlag=0 AND a.TypeId=2");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Terms
                    //WOOtherTerms
                    $select = $sql->select();
                    $select->from(array('a' => "WPM_WOOtherTerms"))
                        ->columns(array('TransId', 'Title' => 'TermsTitle','Desc' => 'TermsDescription'))
                        ->where("WORegisterId=$editId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_otherTerms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOTechSpec
                    $select = $sql->select();
                    $select->from(array('a' => "WPM_WOTechSpec"))
                        ->columns(array('TransId', 'Title', 'Desc' => 'Specification'))
                        ->where("WORegisterId=$editId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_techTerms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOMaterialBaseRate
                    $select = $sql->select();
                    $select->from(array('a' => "WPM_WOMaterialBaseRate"))
                        ->join(array('b' => 'Proj_Resource'), 'a.MaterialId=b.ResourceId', array('MaterialName' => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('TransId', 'MaterialId', 'Rate', 'EscalationPer', 'RateCondition', 'ActualRate'), array('MaterialName'), array('UnitName'))
                        ->where("WORegisterId=$editId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_materials = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOMaterialExclude
                    $select = $sql->select();
                    $select->from(array('a' => "WPM_WOExcludeMaterial"))
                        ->join(array('b' => 'Proj_Resource'), 'a.MaterialId=b.ResourceId', array('MaterialName' => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
                        ->columns(array('TransId', 'MaterialId', 'Rate', 'SType'), array('MaterialName'), array('UnitName'))
                        ->where("WORegisterId=$editId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_exc_materials = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WOMaterialAdvance
                    $select = $sql->select();
                    $select->from(array('a' => "WPM_WOMaterialAdvance"))
                        ->join(array('b' => 'Proj_Resource'), 'a.MaterialId=b.ResourceId', array('MaterialName' => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                        ->columns(array('TransId', 'MaterialId', 'AdvPercent'), array('MaterialName'))
                        ->where("WORegisterId=$editId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_adv_materials = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $this->_view->WORegisterId = $editId;
                }
            }
            // mobilization advance

            /*$select = $sql->select();
            $select->from(array("a" => "WF_TermsMaster"))
                ->columns(array('value'=>'Title' , 'data' => 'TermsId','TermType'))
                ->where(array("TermType" => 'S'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_mobterms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

            $aVNo = CommonHelper::getVoucherNo(401, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->woNo = $aVNo["voucherNo"];
            else
                $this->_view->woNo = "";

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function registerAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Order Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $this->_view->orderType = '';

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                if($postData['OrderType'] == 'work') {

                    $select = $sql->select();
                    $select->from(array("a" => "WPM_WORegister"))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('e' => 'vendor_master'), 'a.VendorId=e.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'WPM_WOTrans'), 'a.WORegisterId=c.WORegisterId', array('IOWId','ResourceId'), $select::JOIN_LEFT)
                        ->columns(array("WORegisterId", "WONo",'Amount'
                        ,"WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                        ,"WorkType" => new Expression("Case When c.IOWId!=0 Then 'IOW' Else 'Activity' End"),
                            "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end")
                        , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), "NetAmount"))
                        ->where("a.DeleteFlag='0'")
                        ->order('a.WORegisterId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else if($postData['OrderType'] == 'service') {

                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_SORegister'))
                        ->columns(array("SORegisterId", "SONo",'NetAmount',
                            "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),
                            "SODate" => new Expression("FORMAT(a.SODate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_ServiceTypeMaster'), 'a.ServiceTypeId = d.ServiceTypeId', array('ServiceTypeName'), $select::JOIN_LEFT);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else if($postData['OrderType'] == 'hire') {

                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_HORegister'))
                        ->columns(array("HORegisterId",'NetAmount',
                            "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),
                            "HONo", "HODate" => new Expression("FORMAT(a.HODate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_HireTypeMaster'), 'a.HireTypeId = d.HireTypeId', array('HireTypeName'), $select::JOIN_LEFT);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('result'=>$result,'oType'=>$postData['OrderType'])));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
                    //echo '<pre>'; print_r($postData); die;

                    if($postData['OrderType'] == 'work') {

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WORegister"))
                            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_WOTrans'), 'a.WORegisterId=c.WORegisterId', array('IOWId','ResourceId'), $select::JOIN_LEFT)
                            ->columns(array("WORegisterId", "WONo"
                            ,"WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                            ,"WorkType" => new Expression("Case When c.IOWId!=0 Then 'IOW' Else 'Activity' End")
                            , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), "Amount"))
                            ->where("a.DeleteFlag='0'")
                            ->order('a.WORegisterId ASC');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    } else if($postData['OrderType'] == 'service') {

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_SORegister'))
                            ->columns(array("SORegisterId", "SONo", "SODate" => new Expression("FORMAT(a.SODate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
                            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ServiceTypeMaster'), 'a.ServiceTypeId = d.ServiceTypeId', array('ServiceTypeName'), $select::JOIN_LEFT);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->soRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    } else if($postData['OrderType'] == 'hire') {

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_HORegister'))
                            ->columns(array("HORegisterId", "HONo", "HODate" => new Expression("FORMAT(a.HODate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
                            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                            ->join(array('d' => 'WPM_HireTypeMaster'), 'a.HireTypeId = d.HireTypeId', array('HireTypeName'), $select::JOIN_LEFT);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->hoRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    }

                    $this->_view->orderType = $postData['OrderType'];
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $select = $sql->select();
                $select->from(array("a" => "WPM_WORegister"))
                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'WPM_WOTrans'), 'a.WORegisterId=c.WORegisterId', array('IOWId', 'ResourceId'), $select::JOIN_LEFT)
                    ->columns(array("WORegisterId", "WONo"
                    , "WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
                    , "WorkType" => new Expression("Case When c.IOWId!=0 Then 'IOW' Else 'Activity' End")
                    , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), "Amount"))
                    ->where("a.DeleteFlag='0'")
                    ->order('a.WORegisterId ASC');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->orders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->orderType = 'work';
            }
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function billRegisterAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Bill Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();
        $this->_view->orderType = '';

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                if($postData['OrderType'] == 'work') {

                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_WorkBillRegister'))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId ', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_WORegister'), 'a.WORegisterId=d.WORegisterId', array('WONo'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WPM_WorkBillTrans'), 'a.BillRegisterId=e.BillRegisterId', array('Rate', 'CurQty'), $select::JOIN_LEFT)
                        ->columns(array("BillRegisterId", "WOBilled","BillNo", "BillDate" => new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"),
                            "FromDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"),
                            "ToDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"),
                            "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end")
                        ))
                        ->where('a.DeleteFlag=0');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else if($postData['OrderType'] == 'service') {

                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_SBRegister'))
                        ->columns(array("SBRegisterId", "SBNo", "SBDate" => new Expression("FORMAT(a.SBDate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"),
                            "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')"),
                            "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),"Amount"
                        ))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_SDRegister'), 'a.SDRegisterId = d.SDRegisterId', array('SDNo'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WPM_SORegister'), 'a.SORegisterId = e.SORegisterId', array('SONo'), $select::JOIN_LEFT);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else if($postData['OrderType'] == 'hire') {

                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_HBRegister'))
                        ->columns(array("HBRegisterId", "HBNo", "HBDate" => new Expression("FORMAT(a.HBDate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')"),
                            "Approve" => new Expression("Case When A.Approve='Y' then 'Yes' when A.Approve='P' then 'Partial' else 'No' end"),'NetAmount'
                        ))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WPM_HORegister'), 'a.HORegisterId = e.HORegisterId', array('HONo'), $select::JOIN_LEFT);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('result'=>$result,'oType'=>$postData['OrderType'])));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();


                    $this->_view->orderType = $postData['OrderType'];
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                }
            }
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }
    public function WorkOrderReportAction(){
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
        $Type = $this->bsf->isNullCheck( $this->params()->fromRoute( 'type' ), 'string' );

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

        $dir = 'public/reports/newtemp/'. $subscriberId;
        $filePath = $dir.'/v1_template.phtml';
        $filePath1 = $dir.'/f1_template.phtml';

        if ($request->isPost()) {

            $this->redirect()->toRoute( 'wpm/default', array( 'controller' => 'workorder', 'action' => 'register' ) );
        }
        $workorderId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($workorderId == 0)
            $this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );

        if (!file_exists($filePath)) {
            $filePath = 'public/reports/newtemp/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/reports/newtemp/template1.phtml';
        }
        $template = file_get_contents($filePath);
        $template1 = file_get_contents($filePath1);
        $this->_view->template = $template;
        $this->_view->footertemplate = $template1;


        // check for bill id and subscriber id
        /*$select = $sql->select();
        $select->from(array('a' => "CB_BilOrderlMaster"))
            ->join(array('b' => 'CB_WORegister'), 'a.WORegisterId=b.WorkOrderId', array('SubscriberId'), $select:: JOIN_LEFT)
            ->columns(array('BillId'))
            ->where("a.BillId=$editid AND b.SubscriberId=$subscriberId");
        $statement = $sql->getSqlStringForSqlObject($select);
        if(!$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current())
            $this->redirect()->toRoute( 'cb/clientbilling', array( 'controller' => 'clientbilling', 'action' => 'register' ) );*/

        $select = $sql->select();
        $select->from(array("a" => "WPM_WORegister"))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'vendor_master'), 'a.VendorId=c.VendorId', array('PhoneNumber',"PANNo"), $select::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "b.CompanyId=h.CompanyId", array("LogoPath","CompanyName","Address",'CompanyMailid'=>'Email','Fax'), $select::JOIN_LEFT)
            ->join(array("k"=>"WF_CostCentre"), "b.CostCentreId=k.CostCentreId", array("cAddress"=>new Expression("k.Address+''+k.Pincode")), $select::JOIN_LEFT)
            ->columns(array("WORegisterId","WOType", "WONo", "RefNo","CCWONo","WODate" => new Expression("FORMAT(a.WODate, 'dd-MM-yyyy')")
            , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"),
                "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"),
                "VendorName" => new Expression('VendorName'),
                "Amount","NetAmount"))
            ->where("a.DeleteFlag='0' and a.WORegisterId=$workorderId")
            ->order('a.WORegisterId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $woregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->woregister=$woregister;
//            echo '<pre>'; print_r($this->_view->woregister);die;
        // boq
        $select = $sql->select();
        $select->from(array("a" => "Proj_WOQualTrans"))
            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
            ->columns(array('QualifierId','tots'=>new Expression("ltrim(str(a.NetPer)) + '%'"),'NetAmt','Sign'));
        $select->where(array("a.MixType ='S' and  a.WORegisterId =$workorderId and A.NetAmt <>0"));
        $select->order('a.SortId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $woAmtinwords = $this->convertAmountToWords($woregister['NetAmount']);
        $this->_view->woAmtinwords = $woAmtinwords;

        $select = $sql->select();
        $select->from(array('a' => "WPM_WOTrans"))
            ->join(array('c' => 'proj_resource'), 'a.ResourceId=c.ResourceId', array('Code','ResourceName','ResourceId'), $select:: JOIN_LEFT)
            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select:: JOIN_LEFT)
            ->columns(array('Amount','WOTransId','Qty','Rate'))
            ->where("a.WORegisterId=$workorderId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $woboq= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
        $this->_view->woboq =$woboq;
        $this->_view->Type =$Type;

        if($Type=='aWbs'){
            $select = $sql->select();
            $select->from(array('A' => "WPM_WORegister"))
                ->join(array('B' => 'WPM_WoTrans'), 'A.WORegisterId=B.WORegisterId', array(), $select:: JOIN_INNER)
                ->join(array('C' => 'WF_OperationalCostCentre'), 'A.CostCentreId=C.CostCentreId', array(), $select:: JOIN_INNER)
                ->join(array('D' => 'Vendor_Master'), 'A.VendorId=D.VendorId', array(), $select:: JOIN_INNER)
                ->join(array('E' => 'Vendor_Contact'), 'D.VendorId=E.VendorId', array(), $select:: JOIN_LEFT)
                ->join(array('D5' => 'Vendor_Branch'), 'A.VendorId=D5.VendorId', array(), $select:: JOIN_LEFT)
                ->join(array('F' => 'WF_CityMaster'), 'D.CityId=F.CityId', array(), $select:: JOIN_LEFT)
                ->join(array('G' => 'WF_CostCentre'), 'F.CityId=G.CityId and A.CostCentreId=G.CostCentreId', array(), $select:: JOIN_LEFT)
                ->join(array('K' => 'WPM_WOIOWTrans'), 'B.WOTransId=K.WOTransId', array('Qty'), $select:: JOIN_LEFT)
                ->join(array('A1' => 'Proj_ProjectDetails'), 'A1.ResourceID=B.ResourceId', array(), $select:: JOIN_LEFT)
                ->join(array('B1' => 'Proj_ProjectIOWMaster'), 'A1.ProjectIOWId=B1.ProjectIOWId', array(), $select:: JOIN_LEFT)
                ->join(array('C1' => 'Proj_UOM'), 'B1.UnitId=C1.UnitID', array(), $select:: JOIN_LEFT)
                ->join(array('D1' => 'Proj_WBSMaster'), 'K.WbsId=D1.WBSId', array('WbsId','WbsName'), $select:: JOIN_LEFT)
//                    ->columns(array('WbsName'=>new Expression('D1.WbsName')))
                ->where("A.WORegisterId=$workorderId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $actWbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
            $this->_view->actWbs =$actWbs;
        }
        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
    public function HireOrderReportAction(){
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

        $dir = 'public/reports/hire/'. $subscriberId;
        $filePath = $dir.'/v2_template.phtml';
        $filePath1 = $dir.'/f2_template.phtml';
        $hireOrderId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );

        if ($request->isPost()) {
            $this->redirect()->toRoute( 'wpm/default', array( 'controller' => 'workorder', 'action' => 'register' ) );
        }


        if (!file_exists($filePath)) {
            $filePath = 'public/reports/hire/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/reports/hire/template1.phtml';
        }
        $template = file_get_contents($filePath);
        $template1 = file_get_contents($filePath1);
        $this->_view->template = $template;
        $this->_view->footertemplate = $template1;

        $select = $sql->select();
        $select = $sql->select();
        $select->from(array('a' => 'WPM_HORegister'))
            ->columns(array("HORegisterId", "HONo", "HOCCNo","RefNo","ContractorName" => new Expression('VendorName'),"HODate" => new Expression("FORMAT(a.HODate, 'dd-MM-yyyy')"), "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"), "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_HireTypeMaster'), 'a.HireTypeId = d.HireTypeId', array('HireTypeName'), $select::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "b.CompanyId=h.CompanyId", array("LogoPath","CompanyName"), $select::JOIN_LEFT)
            ->where("a.DeleteFlag='0' and a.HORegisterId=$hireOrderId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $horegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->hoRegister=$horegister;
//            echo '<pre>'; print_r($this->_view->woregister);die;
        // boq
//            echo $woAmtinwords = $this->convertAmountToWords($woregister['Amount']);die;
//            $this->_view->woAmtinwords = $woAmtinwords;

        $select = $sql->select();
        $select->from(array('a' => "WPM_HOTypeTrans"))
            ->join(array('c' => 'proj_resource'), 'a.ResourceId=c.ResourceId', array('Code','ResourceName'), $select:: JOIN_LEFT)
            ->columns(array('Amount','HOTypeTransId','Qty','WorkingQty','TotalQty','Rate'))
            ->where("a.HORegisterId=$hireOrderId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
    public function ServiceOrderReportAction(){
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

        $dir = 'public/reports/service/'. $subscriberId;
        $filePath = $dir.'/v3_template.phtml';
        $filePath1 = $dir.'/f3_template.phtml';
        $serviceOrderId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($serviceOrderId == 0)
            $this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );

        if (!file_exists($filePath)) {
            $filePath = 'public/reports/service/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/reports/service/template1.phtml';
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
        $select->from(array('a' => 'WPM_SORegister'))
            ->columns(array("SORegisterId", "SONo","SOCCNo","RefNo", "SODate" => new Expression("FORMAT(a.SODate, 'dd-MM-yyyy')")
            , "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')")
            , "ContractorName" => new Expression("c.VendorName")
            , "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')"),'NetAmount'))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('RegAddress','CompanyMailid','PANNo'), $select::JOIN_LEFT)
            ->join(array('d' => 'Proj_ServiceTypeMaster'), 'a.ServiceTypeId = d.ServiceTypeId', array('ServiceTypeName'), $select::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "b.CompanyId=h.CompanyId", array("LogoPath","CompanyName",'Address','Phone','Email'), $select::JOIN_LEFT)
            ->join(array("k"=>"WF_CostCentre"), "b.CostCentreId=k.CostCentreId", array("Mobile","ContactPerson",'cAddress'=>'Address'), $select::JOIN_LEFT)
            ->where("a.SORegisterId=$serviceOrderId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $soregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->soRegister=$soregister;

        $select = $sql->select();
        $select->from(array("a" => "WPM_SOQualTrans"))
            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
            ->columns(array('QualifierId','tots'=>new Expression("ltrim(str(a.NetPer)) + '%'"),'NetAmt','Sign'));
        $select->where(array("a.MixType ='S' and  a.SORegisterId =$serviceOrderId and A.NetAmt <>0"));
        $select->order('a.SortId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//            $sHtml = Qualifier::getQualifier($qualList, "R");
//            $this->_view->qualHtml = $sHtml;
//            $sHtml = Qualifier::getQualifier($qualList, "R");
//            $this->_view->qualRHtml = $sHtml;

//            echo '<pre>'; print_r($this->_view->woregister);die;
        // boq
        $soAmtinwords = $this->convertAmountToWords($soregister['NetAmount']);
        $this->_view->soAmtinwords = $soAmtinwords;

        $select = $sql->select();
        $select->from(array('a' => "WPM_SOServiceTrans"))
            ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('ServiceName'), $select:: JOIN_LEFT)
            ->columns(array('Amount','ServiceId','Qty','Rate'))
            ->where("a.SORegisterId=$serviceOrderId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
    private function convertAmountToWords($number) {
        $no = floor($number);
        $point = round($number - $no, 2) * 100;
        $hundred = null;
        $digits_1 = strlen($no);
        $i = 0;
        $str = array();
        $words = array('0' => '', '1' => 'One', '2' => 'Two',
            '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
            '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
            '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
            '13' => 'Thirteen', '14' => 'Fourteen',
            '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
            '18' => 'Eighteen', '19' =>'Nineteen', '20' => 'Twenty',
            '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty',
            '60' => 'Sixty', '70' => 'Seventy',
            '80' => 'Eighty', '90' => 'Ninety');
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');

        while ($i < $digits_1) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number] .
                    " " . $digits[$counter] . $plural . " " . $hundred
                    :
                    $words[floor($number / 10) * 10]
                    . " " . $words[$number % 10] . " "
                    . $digits[$counter] . $plural . " " . $hundred;
            } else {
                $str[] = null;
            }
        }
        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ? " and " . $words[((int)($point /10)) . '0'] . " " . $words[$point = $point % 10] . " Paise": '';

        if($result==""){
            $result = "";
        } else {
            $result = $result . "Rupees  " . $points . " Only.";
        }
        return $result;
    }

    public function checkTypeAction()
    {
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('WPM_WORegister')
                    ->columns(array("WOType"))
                    ->where("CostCentreId='".$postParams['ccId']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }
}