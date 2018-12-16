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

class WorkbillController extends AbstractActionController
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
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Bill");
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

                switch($Type) {
                    case 'getdailyprogress':
                        $RegisterId = $this->bsf->isNullCheck($postParams['RegisterId'],'number');
                        $sType = $this->bsf->isNullCheck($postParams['sType'],'string');

                        $frWhr = '';
                        if($sType == "work") {
                            $select = $sql->select();
                            $select->from(array("a" => "WPM_DPERegister"))
                                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                                ->columns(array('NO' => new Expression('a.DPENo'), 'uRegisterId' => new Expression('a.DPERegisterId'), 'sType' => new Expression('a.WOType'), 'Include' => new Expression('1-1')))
                                ->where("a.DeleteFlag=0 AND a.WORegisterId=$RegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $frWhr = 'dpe';
                            if(empty($result1)) {
                                $select = $sql->select();
                                $select->from(array("a" => "WPM_WORegister"))
                                    ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                                    ->columns(array('NO' => new Expression('a.WONo'), 'uRegisterId' => new Expression('a.WORegisterId'), 'sType' => new Expression('a.WOType'), 'Include' => new Expression('1-1')))
                                    ->where("a.DeleteFlag=0 AND a.WORegisterId=$RegisterId");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $result1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                $frWhr = 'wo';
                            }

                            $select = $sql->select();
                            $select->from(array("a" => "WPM_DPETrans"))
                                ->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array('Date' => new Expression("Convert(varchar(10),b.DPEDate,105)")), $select::JOIN_LEFT)
                                ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_ProjectIOWMaster'), 'a.IOWId=d.ProjectIOWId', array('Specification'), $select::JOIN_LEFT)
                                ->columns(array('NO'=>new Expression('b.DPENo'), 'uRegisterId' => new Expression('b.DPERegisterId'), 'sType' => new Expression("CASE WHEN c.ResourceName != '' THEN c.ResourceName WHEN d.Specification != '' THEN d.Specification ELSE b.WOType END"), 'Qty', 'Rate', 'Amount', 'TransId' => 'DPETransId', 'Include' => new Expression('1-1')))
                                ->where("b.DeleteFlag=0 AND b.WORegisterId=$RegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            if(empty($result2)) {
                                $select = $sql->select();
                                $select->from(array("a" => "WPM_WOTrans"))
                                    ->join(array('b' => 'WPM_WORegister'), 'a.WORegisterId=b.WORegisterId', array('Date' => new Expression("Convert(varchar(10),b.WODate,105)")), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                                    ->join(array('d' => 'Proj_ProjectIOWMaster'), 'a.IOWId=d.ProjectIOWId', array('Specification'), $select::JOIN_LEFT)
                                    ->columns(array('NO'=>new Expression('b.WONo'), 'uRegisterId' => new Expression('b.WORegisterId'), 'sType' => new Expression("CASE WHEN c.ResourceName != '' THEN c.ResourceName WHEN d.Specification != '' THEN d.Specification ELSE b.WOType END"), 'Qty', 'Rate', 'Amount', 'TransId' => 'WOTransId', 'Include' => new Expression('1-1')))
                                    ->where("b.DeleteFlag=0 AND b.WORegisterId=$RegisterId");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $result2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            }
                        } else if($sType == "service") {
                            $select = $sql->select();
                            $select->from(array("a" => "WPM_SDRegister"))
                                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreId','CostCentreName'), $select::JOIN_LEFT)
                                ->join(array('f' => 'WPM_SORegister'), 'a.SORegisterId=f.SORegisterId', array(), $select::JOIN_LEFT)
                                ->join(array('c' => 'proj_ServiceTypeMaster'), 'f.ServiceTypeId=c.ServiceTypeId', array('ServiceTypeId'), $select::JOIN_LEFT)
                                ->columns(array('NO' => new Expression('a.SDNo'), 'uRegisterId' => new Expression('a.SDRegisterId'), 'sType' => new Expression('c.ServiceTypeName')))
                                ->where("a.DeleteFlag=0 AND a.SORegisterId=$RegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array("a" => "WPM_SDServiceTrans"))
                                ->join(array('b' => 'WPM_SDRegister'), 'a.SDRegisterId=b.SDRegisterId', array('Date' => new Expression("Convert(varchar(10),b.SDDate,105)")), $select::JOIN_LEFT)
                                ->join(array('c' => 'proj_ServiceMaster'), 'a.ServiceId=c.ServiceId', array('ServiceId'), $select::JOIN_LEFT)
                                ->columns(array('NO' => new Expression('b.SDNo'), 'uRegisterId' => new Expression('b.SDRegisterId'), 'sType' => new Expression('c.ServiceName'), 'Qty', 'Rate', 'Amount', 'TransId' => 'SDServiceTransId', 'Include' => new Expression('1-1')))
                                ->where("b.DeleteFlag=0 AND b.SORegisterId=$RegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $result2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        }

                        $response->setStatusCode('200');
                        $response->setContent(json_encode(array('serv' => $result1, 'bill' => $result2, 'frWhr' => $frWhr)));
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
            //Operational Cost Centre
            $select = $sql->select();
            $select->from( 'WF_OperationalCostCentre' )
                ->columns(array("CostCentreId", "CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->opCostCentre = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            //Vendor Master
            $select = $sql->select();
            $select->from( 'Vendor_Master' )
                ->columns(array('VendorId', 'VendorName'))
                ->where(array('Contract' => 1));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->vendorMaster = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}
	}


    public function entryAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Bill");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        $editId = $this->bsf->isNullCheck($this->params()->fromRoute('editId'), 'number');

        if(!$this->getRequest()->isXmlHttpRequest() && $editId == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('wpm/default', array('controller' => 'workbill', 'action' => 'index'));
        }

        $this->_view->wbsReq = '';

        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                $postData = $request->getPost();
                switch($Type) {
                    case 'getLSDetails':
                        $LSRegisterIds = $postData['LSRegisterIds'];

                        if (!is_null($LSRegisterIds) && $LSRegisterIds != '')
                            $LSRegisterIds = trim($LSRegisterIds);
                        else
                            $LSRegisterIds = 0;

                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_LSWBSTrans'))
                            ->join(array('b' => 'WPM_LSVendorTrans'), 'a.LSVendorTransId=b.LSVendorTransId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'WPM_LabourStrengthRegister'), 'b.LSRegisterId=c.LSRegisterId', array('LSRegisterId'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSName'), $select::JOIN_LEFT)
                            ->columns(array('LSWBSTransId', 'WBSId', 'Qty', 'Amount', 'OTHrs', 'OTAmount', 'NetAmount'))
                            ->where("c.LSRegisterId IN ('" . $LSRegisterIds . "')");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (count($arr_vendor) > 0) {
                            $subQuery = $sql->select();
                            $subQuery->from(array('g' => "WPM_LSVendorTrans"))
                                ->join(array('h' => 'WPM_LabourStrengthRegister'), 'g.LSRegisterId=h.LSRegisterId', array(), $select:: JOIN_LEFT)
                                ->columns(array('LSVendorTransId'))
                                ->where("h.LSRegisterId IN ('" . $LSRegisterIds . "')");
                            $select = $sql->select();
                            $select->from(array("a" => "WPM_LSTypeTrans"))
                                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceId', 'ResourceName'), $select::JOIN_LEFT)
                                ->columns(array('Qty', 'Rate', 'Amount', 'OTHrs', 'OTRate', 'OTAmount', 'NetAmount'))
                                ->where->expression('a.LSVendorTransId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_vendor_type_trans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $response->setStatusCode('200');
                            $response->setContent(json_encode(array('wbs' => $arr_vendor, 'lstype' => $arr_vendor_type_trans)));
                            return $response;
                        } else {
                            $response->setStatusCode('201');
                            $response->setContent('No data!');
                            return $response;
                        }
                        break;
                    case 'getDPETransDetails':
                        $ResType = $this->bsf->isNullCheck($this->params()->fromPost('ResType'), 'string');
                        $TransId = $this->bsf->isNullCheck($this->params()->fromPost('TransId'), 'number');
                        $TransIOWOrWBSId = $this->bsf->isNullCheck($this->params()->fromPost('TransIOWOrWBSId'), 'number');
                        $CostCentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                        $WORegisterId = $this->bsf->isNullCheck($this->params()->fromPost('WORegisterId'), 'number');
                        $VendorId = $this->bsf->isNullCheck($this->params()->fromPost('VendorId'), 'number');
                        $FormatId = $this->bsf->isNullCheck($this->params()->fromPost('FormatId'), 'number');

                        if($ResType == 'Resource') {
                            $select = $sql->select();
                            $select->from(array("z" => "WPM_WorkBillIOWTrans"))
                                ->join(array('f' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=f.WorkBillTransId', array(), $select:: JOIN_LEFT)
                                ->join(array('a' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=f.BillRegisterId', array(), $select:: JOIN_LEFT)
                                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'z.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                ->join(array('c' => 'Proj_WBSTrans'), 'z.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSID=c.WBSID', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                ->columns(array('ProjectIOWId' => 'IOWId', 'Qty' => new Expression('SUM(z.Qty)'), 'WBSID' => new Expression("'0'")))
                                ->where("a.DeleteFlag=0 AND a.CostCentreId=" . $CostCentreId . ' AND a.WORegisterId=' . $WORegisterId . ' AND a.VendorId=' . $VendorId . " AND f.RateType='F' AND z.IOWId=$TransIOWOrWBSId")
                                ->group(new Expression("z.IOWId,e.UnitName,e.UnitId,d.WBSName,d.ParentText,b.RefSerialNo,b.Specification"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array("z" => "WPM_WorkBillIOWTrans"))
                                ->join(array('f' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=f.WorkBillTransId', array('WorkBillTransId'), $select:: JOIN_LEFT)
                                ->join(array('a' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=f.BillRegisterId', array('BillDate' => new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo'), $select:: JOIN_LEFT)
                                ->join(array('b' => 'WPM_WorkBillReCertificateTrans'), 'f.WorkBillTransId=b.WorkBillTransId AND z.WorkBillIOWTransId=b.WorkBillIOWTransId', array('AdjQty' => new Expression("SUM(b.Qty)")), $select:: JOIN_LEFT)
                                ->columns(array('WorkBillIOWTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'WBSID' => new Expression("'0'"), 'IOWId'))
                                ->where("a.DeleteFlag=0 AND a.CostCentreId=" . $CostCentreId . ' AND a.WORegisterId=' . $WORegisterId . ' AND a.VendorId=' . $VendorId . " AND f.RateType='F' AND z.IOWId=$TransIOWOrWBSId AND f.FormatTypeId=$FormatId")
                                ->group(new Expression("z.WorkBillIOWTransId, z.IOWId,z.Qty,f.WorkBillTransId,a.BillDate,a.BillNo,b.Qty"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $bills = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        } else {
                            $select = $sql->select();
                            $select->from(array("z" => "WPM_WorkBillWBSTrans"))
                                ->join(array('f' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=f.WorkBillTransId', array(), $select:: JOIN_LEFT)
                                ->join(array('a' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=f.BillRegisterId', array(), $select:: JOIN_LEFT)
                                ->join(array('c' => 'Proj_WBSTrans'), 'c.WBSId=z.WBSId', array(), $select::JOIN_LEFT)
                                ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=z.WBSId', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'b.ProjectIOWId=c.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                ->columns(array('ProjectIOWId' => new Expression("'0'"), 'Qty' => new Expression('SUM(z.Qty)'), 'WBSID'))
                                ->where("a.DeleteFlag=0 AND a.CostCentreId=" . $CostCentreId . ' AND a.WORegisterId=' . $WORegisterId . ' AND a.VendorId=' . $VendorId . " AND f.RateType='F' AND z.WBSId=$TransIOWOrWBSId")
                                ->group(new Expression("z.WBSID,e.UnitName,e.UnitId,d.WBSName,d.ParentText,b.RefSerialNo,b.Specification"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array("z" => "WPM_WorkBillWBSTrans"))
                                ->join(array('f' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=f.WorkBillTransId', array('WorkBillTransId'), $select:: JOIN_LEFT)
                                ->join(array('a' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=f.BillRegisterId', array('BillDate' => new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), 'BillNo'), $select:: JOIN_LEFT)
                                ->join(array('b' => 'WPM_WorkBillReCertificateTrans'), 'f.WorkBillTransId=b.WorkBillTransId AND z.WorkBillWBSTransId=b.WorkBillWBSTransId', array('AdjQty' => new Expression("SUM(b.Qty)")), $select:: JOIN_LEFT)
                                ->columns(array('WorkBillWBSTransId', 'Qty', 'WBSID', 'IOWId' => new Expression("'0'")))
                                ->where("a.DeleteFlag=0 AND a.CostCentreId=" . $CostCentreId . ' AND a.WORegisterId=' . $WORegisterId . ' AND a.VendorId=' . $VendorId . " AND f.RateType='F' AND z.WBSId=$TransIOWOrWBSId AND f.FormatTypeId=$FormatId")
                                ->group(new Expression("z.WorkBillWBSTransId, z.WBSID,z.Qty,f.WorkBillTransId,a.BillDate,a.BillNo,b.Qty"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $bills = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        }

                        $results = array('iow' => $iows, 'bills' => $bills);

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($results));
                        return $response;
                        break;
                    case 'getwoqty':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
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
                            ->columns(array('WOQty' => new Expression("SUM(a.Qty)")))
                            ->join(array('b' => 'WPM_WOTrans'), 'a.WOTransId=b.WOTransId', array(), $select::JOIN_LEFT)
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
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');
                        $woRegId = $this->bsf->isNullCheck($this->params()->fromPost('woRegId'), 'number');

                        if($ResourceId != 0) {
                            $whereCond['a.ResourceId'] = $ResourceId;
                        } else if($IOWId != 0) {
                            $whereCond['a.IOWId'] = $IOWId;
                        }

                        $woCond = array("a.WORegisterId"=>$woRegId);
                        $wobArr = array();
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"))
                            ->columns(array('WOQty' => new Expression("SUM(a.Qty)")))
                            ->where($whereCond)
                            ->where($woCond);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wo_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WOQty'] = $wo_qty['WOQty'];

                        $wpCond = array("c.WORegisterId"=>$woRegId);
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPETrans"))
                            ->join(array('c' => 'WPM_DPERegister'), 'a.DPERegisterId=c.DPERegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WPQty' => new Expression("SUM(a.Qty)")))
                            ->where($whereCond)
                            ->where($wpCond);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wp_qty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArr['WPQty'] = $wp_qty['WPQty'];

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WorkBillTrans"))
                            ->join(array('c' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=c.BillRegisterId', array(), $select::JOIN_LEFT)
                            ->columns(array('WBQty' => new Expression("SUM(a.CurQty)")))
                            ->where($whereCond)
                            ->where($wpCond);
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
                //echo '<pre>'; print_r($postData);die;

                $WorkType = $this->bsf->isNullCheck($postData['WorkType'], 'string');
                if (!is_null($postData['frm_index'])) {
                    $WORegisterId= $this->bsf->isNullCheck($postData['Workorder'],'number');

                    $DPERegisterIds = $postData['requestTransIds'];
                    $DPERegisterNotIds = $postData['requestTransNotIds'];
                    $qryType = $postData['qryType'];

                    if(!is_null($DPERegisterIds) && $DPERegisterIds != '')
                        $DPERegisterIds = trim(implode(',',$DPERegisterIds));
                    else
                        $DPERegisterIds = 0;

                    if(!is_null($DPERegisterNotIds) && $DPERegisterNotIds != '')
                        $DPERegisterNotIds = trim(implode(',',$DPERegisterNotIds));
                    else
                        $DPERegisterNotIds = 0;

                    // workorder details
                    $select = $sql->select();
                    $select->from(array("a" => "WPM_WORegister"))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreId','CostCentreName','WBSReqWPM', 'ProjectId', 'CompanyId'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array('VendorId','VendorName'), $select::JOIN_LEFT)
                        ->columns(array('WONo', 'WORegisterId', 'WOType'))
                        ->where("a.WORegisterId=$WORegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $workorder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $projectId = $workorder['ProjectId'];
                    $this->_view->projectId = $projectId;
                    $this->_view->wbsReq = $workorder['WBSReqWPM'];
                    $this->_view->companyId = $workorder['CompanyId'];
                    if($workorder != FALSE)
                        $this->_view->workorder = $workorder;

                    // bill abstract
                    if($workorder != FALSE) {
                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_BillFormatTrans'))
                            ->join(array('b' => 'WPM_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array('TypeName', 'Sign', 'AccountTypeId', 'Header'), $select:: JOIN_LEFT)
                            ->where("CostCentreId=".$workorder['CostCentreId'])
                            ->order(array('SortId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_billformats = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($arr_billformats as &$format) {
                            switch($format['BillFormatId']) {
                                case '1': // agreement
                                    if ($workorder['WOType'] == 'iow') {
                                        // iows
                                        if($qryType == 'wo') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_WOTrans'))
                                                ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('Code' => 'RefSerialNo', 'Specification'), $select:: JOIN_LEFT)
                                                ->join(array('d' => 'Proj_ProjectIOW'), 'c.ProjectIOWId=d.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                //->columns(array('DPETransId', 'IOWId', 'ResourceId', 'Qty'))
                                                ->columns(array('Qty' => new Expression('SUM(a.Qty)'), 'IOWId', 'ResourceId', 'DPETransId' => 'WOTransId'))
                                                ->where("a.WOTransId IN (".$DPERegisterIds.")")
                                                ->group(new Expression('a.IOWId,a.ResourceId,a.WOTransId,c.RefSerialNo,c.Specification,d.Rate,e.UnitName,e.UnitId'));
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_iow) > 0) {
                                                $format['AddRow'] = $arr_iow;

                                                // wbs
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_WOTrans"))
                                                    //->join(array('h' => 'WPM_WORegister'), 'g.WORegisterId=h.WORegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('IOWId'))
                                                    ->where("g.WORegisterId=" . $WORegisterId . " AND g.IOWId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_WOWBSTrans"))
                                                    //->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.WBSId=c.WBSId', array('ProjectIOWId'), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    //->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    ->columns(array('DPEIOWTransId' => 'WOWBSTransId', 'DPETransId' => 'WOTransId', 'Qty', 'Rate', 'Amount', 'ProjectIOWId' => new Expression("'0'")))
                                                    ->group(new Expression('a.WOWBSTransId,a.WOTransId,a.Qty,a.Rate,a.Amount,d.WBSId,d.WBSName,d.ParentText'))
                                                    //->where->expression('c.ProjectIOWId IN ?', array($subQuery));
                                                    ->where("a.WOTransId IN (" . $DPERegisterIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                            }
                                        } else if($qryType == 'dpe') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_DPETrans'))
                                                //->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('Code' => 'RefSerialNo', 'Specification'), $select:: JOIN_LEFT)
                                                ->join(array('d' => 'Proj_ProjectIOW'), 'c.ProjectIOWId=d.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                //->columns(array('DPETransId', 'IOWId', 'ResourceId', 'Qty'))
                                                ->columns(array('Qty' => new Expression('SUM(a.Qty)'), 'IOWId', 'ResourceId', 'DPETransId'))
                                                ->where("a.DPETransId IN (".$DPERegisterIds.")")
                                                ->group(new Expression('a.IOWId,a.ResourceId,a.DPETransId,c.RefSerialNo,c.Specification,d.Rate,e.UnitName,e.UnitId'));
                                            //->where("b.CostCentreId=" . $workorder['CostCentreId'] . " AND a.IOWId != 0");
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_iow) > 0) {
                                                $format['AddRow'] = $arr_iow;

                                                // wbs
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_DPETrans"))
                                                    ->join(array('h' => 'WPM_DPERegister'), 'g.DPERegisterId=h.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('IOWId'))
                                                    ->where("h.WORegisterId=" . $WORegisterId . " AND g.IOWId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_DPEWBSTrans"))
                                                    //->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.WBSId=c.WBSId', array('ProjectIOWId'), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    //->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    //->columns(array('DPEIOWTransId', 'DPETransId', 'ProjectIOWId' => new Expression("'0'"), 'Qty'))
                                                    ->columns(array('DPEIOWTransId' => 'DPEWBSTransId', 'DPETransId', 'Qty', 'Rate', 'Amount', 'ProjectIOWId' => new Expression("'0'")))
                                                    ->group(new Expression('a.DPEWBSTransId,a.DPETransId,a.Qty,a.Rate,a.Amount,d.WBSId,d.WBSName,d.ParentText'))
                                                    //->where->expression('a.DPETransId IN ?', array($subQuery));
                                                    //->where->expression('c.ProjectIOWId IN ?', array($subQuery));
                                                    ->where("a.DPETransId IN (" . $DPERegisterIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            }
                                        }
                                    } else if ($workorder['WOType'] == 'activity') {
                                        // resources
                                        if($qryType == 'wo') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_WOTrans'))
                                                ->join(array('b' => 'WPM_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select:: JOIN_LEFT)
                                                ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('Code', 'Specification' => 'ResourceName', 'Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                ->join(array('f' => 'WPM_WOTrans'), 'b.WORegisterId=f.WORegisterId AND a.ResourceId=f.ResourceId', array('WOQty' => 'Qty'), $select::JOIN_LEFT)
                                                ->columns(array('DPETransId' => 'WOTransId', 'IOWId', 'ResourceId', 'EstQty' => 'Qty'))
                                                //->where("b.CostCentreId=" . $workorder['CostCentreId'] . " AND a.ResourceId != 0");
                                                ->where("a.WOTransId IN (" . $DPERegisterIds . ")");
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_resource) > 0) {
                                                $format['AddRow'] = $arr_resource;

                                                // iow
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_WOTrans"))
                                                    //->join(array('h' => 'WPM_WORegister'), 'g.WORegisterId=h.WORegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('WOTransId'))
                                                    ->where("g.WORegisterId=" . $WORegisterId . " AND g.ResourceId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_WOIOWTrans"))
                                                    ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    ->columns(array('DPEIOWTransId' => 'WOIOWTransId', 'DPETransId' => 'WOTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'WBSId', 'Rate', 'Amount'))
                                                    //->group(new Expression('a.WOIOWTransId,a.WOTransId,a.IOWId,a.Qty,b.RefSerialNo,b.Specification,d.WBSName,d.ParentText,e.UnitName,e.UnitId'))
                                                    //->where("b.ProjectId=" . $projectId)
                                                    //->where->expression('a.WOTransId IN ?', array($subQuery));
                                                    ->where("a.WOTransId IN (" . $DPERegisterIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                            }
                                        } else if($qryType == 'dpe') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_DPETrans'))
                                                ->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('Code', 'Specification' => 'ResourceName', 'Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                ->join(array('f' => 'WPM_WOTrans'), 'b.WORegisterId=f.WORegisterId AND a.ResourceId=f.ResourceId', array('WOQty' => 'Qty'), $select::JOIN_LEFT)
                                                ->columns(array('DPETransId', 'IOWId', 'ResourceId', 'EstQty' => 'Qty'))
                                                //->where("b.CostCentreId=" . $workorder['CostCentreId'] . " AND a.ResourceId != 0");
                                                ->where("a.DPETransId IN (" . $DPERegisterIds . ")");
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_resource) > 0) {
                                                $format['AddRow'] = $arr_resource;

                                                // iow
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_DPETrans"))
                                                    ->join(array('h' => 'WPM_DPERegister'), 'g.DPERegisterId=h.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('DPETransId'))
                                                    ->where("h.WORegisterId=" . $WORegisterId . " AND g.ResourceId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_DPEIOWTrans"))
                                                    ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    ->columns(array('DPEIOWTransId', 'DPETransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'WBSId', 'Rate', 'Amount'))
                                                    //->group(new Expression('a.DPEIOWTransId,a.DPETransId,a.IOWId,a.Qty,a.Rate,a.Amount,a.WBSId,d.WBSName,d.ParentText,e.UnitName,e.UnitId,b.RefSerialNo,b.Specification'))
                                                    //->where->expression('a.DPETransId IN ?', array($subQuery));
                                                    ->where("a.DPETransId IN (" . $DPERegisterIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                            }
                                        }
                                    } else if ($workorder['WOType'] == 'turn-key') {
                                        // Turn Key
                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_DPETurnKeyTrans'))
                                            ->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                                            ->join(array('c' => 'WPM_WOTurnKeyTrans'), 'a.WBSId=c.WBSId', array('Area' => new Expression('SUM(c.Area)'), 'WORate'=> new Expression('SUM(c.Rate)'), 'WOAmount' => new Expression('SUM(c.Amount)')), $select::JOIN_LEFT)
                                            ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array("Desc" => new Expression("d.ParentText + '->' + d.WBSName")), $select::JOIN_LEFT)
                                            ->columns(array('WBSId'))
                                            ->group(new Expression('a.WBSId,d.ParentText,d.WBSName'))
                                            ->where("b.DPERegisterId IN (".$DPERegisterIds.")");
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                        if (count($arr_resource) > 0) {
                                            $format['AddRow'] = $arr_resource;
                                        }
                                    }
                                    break;
                                case '2': // non-agreement
                                    if ($workorder['WOType'] == 'iow') {
                                        // iows
                                        if($qryType == 'wo') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_WOTrans'))
                                                ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('Code' => 'RefSerialNo', 'Specification'), $select:: JOIN_LEFT)
                                                ->join(array('d' => 'Proj_ProjectIOW'), 'c.ProjectIOWId=d.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                //->columns(array('DPETransId', 'IOWId', 'ResourceId', 'Qty'))
                                                ->columns(array('Qty' => new Expression('SUM(a.Qty)'), 'IOWId', 'ResourceId', 'DPETransId' => 'WOTransId'))
                                                ->where("a.WOTransId IN (".$DPERegisterNotIds.")")
                                                ->group(new Expression('a.IOWId,a.ResourceId,a.WOTransId,c.RefSerialNo,c.Specification,d.Rate,e.UnitName,e.UnitId'));
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_iow) > 0) {
                                                $format['AddRow'] = $arr_iow;

                                                // wbs
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_WOTrans"))
                                                    //->join(array('h' => 'WPM_WORegister'), 'g.WORegisterId=h.WORegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('IOWId'))
                                                    ->where("g.WORegisterId=" . $WORegisterId . " AND g.IOWId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_WOWBSTrans"))
                                                    //->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.WBSId=c.WBSId', array('ProjectIOWId'), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    //->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    ->columns(array('DPEIOWTransId' => 'WOWBSTransId', 'DPETransId' => 'WOTransId', 'Qty', 'Rate', 'Amount', 'ProjectIOWId' => new Expression("'0'")))
                                                    ->group(new Expression('a.WOWBSTransId,a.WOTransId,a.Qty,a.Rate,a.Amount,d.WBSId,d.WBSName,d.ParentText'))
                                                    //->where->expression('c.ProjectIOWId IN ?', array($subQuery));
                                                    ->where("a.WOTransId IN (" . $DPERegisterNotIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                            }
                                        } else if($qryType == 'dpe') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_DPETrans'))
                                                //->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('Code' => 'RefSerialNo', 'Specification'), $select:: JOIN_LEFT)
                                                ->join(array('d' => 'Proj_ProjectIOW'), 'c.ProjectIOWId=d.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                //->columns(array('DPETransId', 'IOWId', 'ResourceId', 'Qty'))
                                                ->columns(array('Qty' => new Expression('SUM(a.Qty)'), 'IOWId', 'ResourceId', 'DPETransId'))
                                                ->where("a.DPETransId IN (".$DPERegisterNotIds.")")
                                                ->group(new Expression('a.IOWId,a.ResourceId,a.DPETransId,c.RefSerialNo,c.Specification,d.Rate,e.UnitName,e.UnitId'));
                                            //->where("b.CostCentreId=" . $workorder['CostCentreId'] . " AND a.IOWId != 0");
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_iow) > 0) {
                                                $format['AddRow'] = $arr_iow;

                                                // wbs
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_DPETrans"))
                                                    ->join(array('h' => 'WPM_DPERegister'), 'g.DPERegisterId=h.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('IOWId'))
                                                    ->where("h.WORegisterId=" . $WORegisterId . " AND g.IOWId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_DPEWBSTrans"))
                                                    //->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.WBSId=c.WBSId', array('ProjectIOWId'), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    //->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    //->columns(array('DPEIOWTransId', 'DPETransId', 'ProjectIOWId' => new Expression("'0'"), 'Qty'))
                                                    ->columns(array('DPEIOWTransId' => 'DPEWBSTransId', 'DPETransId', 'Qty', 'Rate', 'Amount', 'ProjectIOWId' => new Expression("'0'")))
                                                    ->group(new Expression('a.DPEWBSTransId,a.DPETransId,a.Qty,a.Rate,a.Amount,d.WBSId,d.WBSName,d.ParentText'))
                                                    //->where->expression('a.DPETransId IN ?', array($subQuery));
                                                    //->where->expression('c.ProjectIOWId IN ?', array($subQuery));
                                                    ->where("a.DPETransId IN (" . $DPERegisterNotIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            }
                                        }
                                    } else if ($workorder['WOType'] == 'activity') {
                                        // resources
                                        if($qryType == 'wo') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_WOTrans'))
                                                ->join(array('b' => 'WPM_WORegister'), 'a.WORegisterId=b.WORegisterId', array(), $select:: JOIN_LEFT)
                                                ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('Code', 'Specification' => 'ResourceName', 'Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                ->join(array('f' => 'WPM_WOTrans'), 'b.WORegisterId=f.WORegisterId AND a.ResourceId=f.ResourceId', array('WOQty' => 'Qty'), $select::JOIN_LEFT)
                                                ->columns(array('DPETransId' => 'WOTransId', 'IOWId', 'ResourceId', 'EstQty' => 'Qty'))
                                                //->where("b.CostCentreId=" . $workorder['CostCentreId'] . " AND a.ResourceId != 0");
                                                ->where("a.WOTransId IN (" . $DPERegisterNotIds . ")");
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_resource) > 0) {
                                                $format['AddRow'] = $arr_resource;

                                                // iow
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_WOTrans"))
                                                    //->join(array('h' => 'WPM_WORegister'), 'g.WORegisterId=h.WORegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('WOTransId'))
                                                    ->where("g.WORegisterId=" . $WORegisterId . " AND g.ResourceId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_WOIOWTrans"))
                                                    ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    ->columns(array('DPEIOWTransId' => 'WOIOWTransId', 'DPETransId' => 'WOTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'WBSId', 'Rate', 'Amount'))
                                                    //->group(new Expression('a.WOIOWTransId,a.WOTransId,a.IOWId,a.Qty,b.RefSerialNo,b.Specification,d.WBSName,d.ParentText,e.UnitName,e.UnitId'))
                                                    //->where("b.ProjectId=" . $projectId)
                                                    //->where->expression('a.WOTransId IN ?', array($subQuery));
                                                    ->where("a.WOTransId IN (" . $DPERegisterNotIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                            }
                                        } else if($qryType == 'dpe') {
                                            $select = $sql->select();
                                            $select->from(array('a' => 'WPM_DPETrans'))
                                                ->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('Code', 'Specification' => 'ResourceName', 'Rate'), $select::JOIN_LEFT)
                                                ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                ->join(array('f' => 'WPM_WOTrans'), 'b.WORegisterId=f.WORegisterId AND a.ResourceId=f.ResourceId', array('WOQty' => 'Qty'), $select::JOIN_LEFT)
                                                ->columns(array('DPETransId', 'IOWId', 'ResourceId', 'EstQty' => 'Qty'))
                                                //->where("b.CostCentreId=" . $workorder['CostCentreId'] . " AND a.ResourceId != 0");
                                                ->where("a.DPETransId IN (" . $DPERegisterNotIds . ")");
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            if (count($arr_resource) > 0) {
                                                $format['AddRow'] = $arr_resource;

                                                // iow
                                                /*$subQuery = $sql->select();
                                                $subQuery->from(array('g' => "WPM_DPETrans"))
                                                    ->join(array('h' => 'WPM_DPERegister'), 'g.DPERegisterId=h.DPERegisterId', array(), $select:: JOIN_LEFT)
                                                    ->columns(array('DPETransId'))
                                                    ->where("h.WORegisterId=" . $WORegisterId . " AND g.ResourceId != 0");*/

                                                $select = $sql->select();
                                                $select->from(array("a" => "WPM_DPEIOWTrans"))
                                                    ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                    //->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                    ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                    ->columns(array('DPEIOWTransId', 'DPETransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'WBSId', 'Rate', 'Amount'))
                                                    //->group(new Expression('a.DPEIOWTransId,a.DPETransId,a.IOWId,a.Qty,a.Rate,a.Amount,a.WBSId,d.WBSName,d.ParentText,e.UnitName,e.UnitId,b.RefSerialNo,b.Specification'))
                                                    //->where->expression('a.DPETransId IN ?', array($subQuery));
                                                    ->where("a.DPETransId IN (" . $DPERegisterNotIds . ")");
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                            }
                                        }
                                    } else if ($workorder['WOType'] == 'turn-key') {
                                        // Turn Key
                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_DPETurnKeyTrans'))
                                            ->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                                            ->join(array('c' => 'WPM_WOTurnKeyTrans'), 'a.WBSId=c.WBSId', array('Area' => new Expression('SUM(c.Area)'), 'WORate'=> new Expression('SUM(c.Rate)'), 'WOAmount' => new Expression('SUM(c.Amount)')), $select::JOIN_LEFT)
                                            ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array("Desc" => new Expression("d.ParentText + '->' + d.WBSName")), $select::JOIN_LEFT)
                                            ->columns(array('WBSId'))
                                            ->group(new Expression('a.WBSId,d.ParentText,d.WBSName'))
                                            ->where("b.DPERegisterId IN (".$DPERegisterNotIds.")");
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                        if (count($arr_resource) > 0) {
                                            $format['AddRow'] = $arr_resource;
                                        }
                                    }
                                    break;
                                case '3': // material recovery
                                    $whereCond = "c.CostCentreId=".$workorder['CostCentreId']."  AND c.ContractorId=".$workorder['VendorId']
                                        ." AND a.FreeOrCharge='C' AND a.IssueQty-a.RecQty>0 AND c.Approve='Y' AND b.TypeID='2'";

//                                    if($WORegisterId != 0) {
//                                        $whereCond .= ' AND c.WORegisterId'.$workorder['WORegisterId'];
//                                    }

                                    $select = $sql->select();
                                    $select->from(array('a' => 'MMS_IssueTrans'))
                                        ->join(array('b' => 'Proj_Resource'), "a.ResourceId=b.ResourceId", array('Code','ResourceName'), $select:: JOIN_LEFT)
                                        ->join(array('c' => 'MMS_IssueRegister'), 'a.IssueRegisterId=c.IssueRegisterId', array('IssueType','IssueNo', 'IssueDate' => new Expression("FORMAT(c.IssueDate, 'dd-MM-yyyy')")), $select::JOIN_LEFT)
                                        ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                        ->columns(array('TransId'=>'IssueTransId','ResourceId','Qty' => new Expression("a.IssueQty-a.RecQty")
                                        , 'Rate' => 'IssueRate', 'Amount' => new Expression("((A.IssueQty - A.RecQty) * A.IssueRate)")))
                                        ->where($whereCond);
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($arr_resource ) > 0) {
                                        $format['AddRow'] = $arr_resource;

                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_WorkBillMaterialRecovery'))
                                            ->join(array('b' => 'WPM_WorkBillFormatTrans'), "b.WorkBillFormatTransId=a.WorkBillFormatTransId", array(), $select:: JOIN_LEFT)
                                            ->join(array('c' => 'WPM_WorkBillRegister'), 'c.BillRegisterId=b.BillRegisterId', array(), $select::JOIN_LEFT)
                                            ->columns(array('ResourceId', 'CumQty' => new Expression("SUM(a.Qty)")))
                                            ->where("b.BillFormatTransId=8 AND c.CostCentreId=".$workorder['CostCentreId']." AND c.VendorId=".$workorder['VendorId'])
                                            ->group(new Expression("a.ResourceId,a.Qty"));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $arr_recovery_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                        $format['Recovery'] = $arr_recovery_resource;
                                    }
                                    break;
                                case '8': // labour
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_LabourStrengthRegister'))
                                        ->join(array('b' => 'WPM_LSVendorTrans'), 'a.LSRegisterId=b.LSRegisterId', array(), $select:: JOIN_LEFT)
                                        ->columns(array('LSRegisterId','LSNo','LSDate' => new Expression("Format(a.LSDate, 'dd-MM-yyyy')"), 'Include' => new Expression("'0'")))
                                        ->where("a.CostCentreId=".$workorder['CostCentreId']." AND b.VendorId=".$workorder['VendorId']);
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_lsregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($arr_lsregister) > 0) {
                                        $format['LSRegister'] = json_encode($arr_lsregister);
                                    }
                                    break;
                                case '14': // advance recovery
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_AdvanceRegister'))
                                        ->join(array('b' => 'WPM_AdvanceTypeMaster'), 'a.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                                        ->columns(array('ARRegisterId','RefNo','RefDate' => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')"),'TypeId', 'TotalAmt' => 'CurrentPaidAmount'))
                                        ->where("a.CostCentreId=".$workorder['CostCentreId']." AND a.VendorId=".$workorder['VendorId']. " AND a.DeleteFlag = 0");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($arr_iow) > 0) {
                                        $format['AddRow'] = $arr_iow;

                                        $subQuery = $sql->select();
                                        $subQuery->from(array('g' => "WPM_AdvanceRegister"))
                                            ->columns(array('ARRegisterId'))
                                            ->where("g.CostCentreId=".$workorder['CostCentreId']." AND g.VendorId=".$workorder['VendorId']. " AND g.DeleteFlag = 0");
                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_WorkBillAdvRecovery'))
                                            ->columns(array('ARRegisterId', 'RecAmount' => new Expression("SUM(a.Amount)")))
                                            ->group(new Expression("a.ARRegisterId,a.Amount"))
                                            ->where->expression('a.ARRegisterId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $arr_recovery_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                        $format['Recovery'] = $arr_recovery_resource;
                                    }
                                    break;
                                case '39':
                                    // WO Material List
                                    $select = $sql->select();
                                    $select->from(array('a' => "WPM_WOMaterialBaseRate"))
                                        ->join(array('b' => 'Proj_Resource'), 'a.MaterialId=b.ResourceId', array("data" => 'ResourceId', "value" => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                                        ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId', 'UnitName'), $select:: JOIN_LEFT)
                                        ->columns(array('Rate', 'EscalationPer', 'RateCondition', 'ActualRate'))
                                        ->where("a.WORegisterId=$WORegisterId");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $this->_view->womateriallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    break;
                            }

                            // sum prev amount
                            $select = $sql->select();
                            $select->from(array('a' => 'WPM_WorkBillFormatTrans'))
                                ->join(array('b' => 'WPM_WorkBillRegister'), 'b.BillRegisterId=a.BillRegisterId', array(), $select::JOIN_LEFT)
                                ->join(array('c' => 'WPM_BillFormatTrans'), 'c.BillFormatTransId=a.BillFormatTransId', array(), $select:: JOIN_LEFT)
                                ->columns(array('PrevAmount' => new Expression('SUM(a.Amount)')))
                                ->where("b.DeleteFlag=0 AND b.CostCentreId=".$workorder['CostCentreId']." AND b.WORegisterId=" .$workorder['WORegisterId']
                                    ." AND b.VendorId=".$workorder['VendorId']." AND c.BillFormatTransId=".$format['BillFormatTransId']);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_formatPrev = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $format['PrevAmount'] = $arr_formatPrev['PrevAmount'];
                        }
                        $this->_view->arr_billformats = $arr_billformats;
                    }

                    // Material List
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_Resource"))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId','UnitName'), $select:: JOIN_LEFT)
                        ->columns(array("data" => 'ResourceId', "value" => new Expression("a.Code + ' ' + a.ResourceName"), 'Rate'))
                        ->where("a.DeleteFlag=0 AND a.TypeId=2");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_DPETrans'))
                        ->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('Code' =>'RefSerialNo','Specification'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_ProjectIOW'), 'c.ProjectIOWId=d.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->columns(array('DPETransId','IOWId', 'ResourceId','Qty'))
                        ->where("b.CostCentreId=".$workorder['CostCentreId']." AND a.IOWId != 0");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // Agreement List IOW
                    $select = $sql->select();
                    $select->from(array('a' => "WPM_WorkBillTrans"))
                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('value' => new Expression("c.RefSerialNo + ' ' + c.Specification")), $select:: JOIN_LEFT)
                        ->join(array('f' => 'WPM_WorkBillWBSTrans'), 'a.WorkBillTransId=f.WorkBillTransId', array('TransWBSId' => 'WBSId'), $select:: JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->columns(array('data' => 'IOWId','IOWId','Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)')))
                        ->where("a.FormatTypeId=1 AND a.IOWId <> 0 AND b.DeleteFlag=0 AND b.CostCentreId=".$workorder['CostCentreId']. ' AND b.WORegisterId='.$WORegisterId . ' AND b.VendorId='.$workorder['VendorId']. " AND a.RateType='F'")
                        ->group(new Expression("a.IOWId,a.Rate,e.UnitId,e.UnitName,c.RefSerialNo,c.Specification,f.WBSId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $recert_agreement = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($recert_agreement) > 0) {
                        $this->_view->recert_agreement = $recert_agreement;

                        // NonAgreement List IOW
                        $select = $sql->select();
                        $select->from(array('a' => "WPM_WorkBillTrans"))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('value' => new Expression("c.RefSerialNo + ' ' + c.Specification")), $select:: JOIN_LEFT)
                            ->join(array('f' => 'WPM_WorkBillWBSTrans'), 'a.WorkBillTransId=f.WorkBillTransId', array('TransWBSId' => 'WBSId'), $select:: JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('data' => 'IOWId','IOWId','Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)')))
                            ->where("a.FormatTypeId=2 AND a.IOWId <> 0 AND b.DeleteFlag=0 AND b.CostCentreId=".$workorder['CostCentreId']. ' AND b.WORegisterId='.$WORegisterId . ' AND b.VendorId='.$workorder['VendorId']. " AND a.RateType='F'")
                            ->group(new Expression("a.IOWId,a.Rate,e.UnitId,e.UnitName,c.RefSerialNo,c.Specification,f.WBSId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->recert_nonagreement = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else {
                        // Agreement List Resource
                        $select = $sql->select();
                        $select->from(array('a' => "WPM_WorkBillTrans"))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'WPM_WorkBillIOWTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('TransIOWId' => 'IOWId'), $select:: JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'a.ResourceId=d.ResourceId', array('value' => new Expression("d.Code +' '+ d.ResourceName")), $select:: JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)'),'data' => 'ResourceId','ResourceId'))
                            ->where("a.FormatTypeId=1 AND a.RateType='F' AND a.ResourceId <> 0 AND b.DeleteFlag=0 AND b.CostCentreId=". $workorder['CostCentreId'] . ' AND b.WORegisterId=' . $WORegisterId . ' AND b.VendorId=' . $workorder['VendorId'])
                            ->group(new Expression("a.ResourceId,a.Rate,e.UnitId,e.UnitName,d.Code,d.ResourceName,c.IOWId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $recert_agreement = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (count($recert_agreement) > 0) {
                            $this->_view->recert_agreement = $recert_agreement;

                            // NonAgreement List Resource
                            $select = $sql->select();
                            $select->from(array('a' => "WPM_WorkBillTrans"))
                                ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                                ->join(array('c' => 'WPM_WorkBillIOWTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('TransIOWId' => 'IOWId'), $select:: JOIN_LEFT)
                                ->join(array('d' => 'Proj_Resource'), 'a.ResourceId=d.ResourceId', array('value' => new Expression("d.Code +' '+ d.ResourceName")), $select:: JOIN_LEFT)
                                ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                ->columns(array('Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)'),'data' => 'ResourceId','ResourceId'))
                                ->where("a.FormatTypeId=2 AND a.RateType='F' AND a.ResourceId <> 0 AND b.DeleteFlag=0 AND b.CostCentreId=". $workorder['CostCentreId'] . ' AND b.WORegisterId=' . $WORegisterId . ' AND b.VendorId=' . $workorder['VendorId'])
                                ->group(new Expression("a.ResourceId,a.Rate,e.UnitId,e.UnitName,d.Code,d.ResourceName,c.IOWId"));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $this->_view->recert_nonagreement = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        }
                    }

                    $this->_view->wbTypeId = '407';
                }
                else {
                    // add / edit entry
                    //echo '<pre>'; print_r($postData);die;
                    try {
                        $connection->beginTransaction();

                        if($editId == 0) {
                            $wbaVNo = CommonHelper::getVoucherNo(407, date('Y-m-d', strtotime($postData['BillDate'])), 0, 0, $dbAdapter, "I");
                            if ($wbaVNo["genType"] == true) {
                                $wbNo = $wbaVNo["voucherNo"];
                            } else {
                                $wbNo = $postData['BillNo'];
                            }

                            $wbccaVNo = CommonHelper::getVoucherNo(407, date('Y-m-d', strtotime($postData['BillDate'])), 0, $postData['CostCentreId'], $dbAdapter, "I");
                            if ($wbccaVNo["genType"] == true) {
                                $wbCCNo = $wbccaVNo["voucherNo"];
                            } else {
                                $wbCCNo = $postData['CCBillNo'];
                            }

                            $wbcoaVNo = CommonHelper::getVoucherNo(407, date('Y-m-d', strtotime($postData['BillDate'])), $postData['CompanyId'], 0, $dbAdapter, "I");
                            if ($wbcoaVNo["genType"] == true) {
                                $wbCoNo = $wbcoaVNo["voucherNo"];
                            } else {
                                $wbCoNo = $postData['CompBillNo'];
                            }

                            $insert = $sql->insert();
                            $insert->into('WPM_WorkBillRegister');
                            $insert->Values(array('BillDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['BillDate'], 'string')))
                            , 'BillNo' => $this->bsf->isNullCheck($wbNo, 'string')
                            , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                            , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                            , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                            , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                            , 'CCBVNo' => $this->bsf->isNullCheck($wbCCNo, 'string')
                            , 'CompanyBVNo' => $this->bsf->isNullCheck($wbCoNo, 'string')
                            , 'VendorId' => $this->bsf->isNullCheck($postData['VendorId'], 'number')
                            , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCentreId'], 'string')
                            , 'AdvAmount' => $this->bsf->isNullCheck($postData['totalCumAmount'], 'number')
                            , 'PrevBilled' => $this->bsf->isNullCheck($postData['totalPrevAmount'], 'number')
                            , 'WOBilled' => $this->bsf->isNullCheck($postData['totalCurAmount'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['Narration'], 'string')
                            , 'BillType' => $this->bsf->isNullCheck($postData['WorkType'], 'string')
                            , 'WORegisterId' => $this->bsf->isNullCheck($postData['WORegisterId'], 'number')
                            ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $BillRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $inType = 'N';
                            $inName = 'WPM-WorkBill-Add';
                            $inDesc = 'WorkBill-Add';
                        } else {
                            $wbNo = $postData['BillNo'];
                            $update = $sql->update();
                            $update->table('WPM_WorkBillRegister');
                            $update->set(array('BillDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['BillDate'], 'string')))
                            , 'BillNo' => $this->bsf->isNullCheck($wbNo, 'string')
                            , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                            , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                            , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                            , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                            , 'CCBVNo' => $this->bsf->isNullCheck($postData['CCBillNo'], 'string')
                            , 'CompanyBVNo' => $this->bsf->isNullCheck($postData['CompBillNo'], 'string')
                            , 'VendorId' => $this->bsf->isNullCheck($postData['VendorId'], 'number')
                            , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCentreId'], 'string')
                            , 'AdvAmount' => $this->bsf->isNullCheck($postData['totalCumAmount'], 'number')
                            , 'PrevBilled' => $this->bsf->isNullCheck($postData['totalPrevAmount'], 'number')
                            , 'WOBilled' => $this->bsf->isNullCheck($postData['totalCurAmount'], 'number')
                            , 'Narration' => $this->bsf->isNullCheck($postData['Narration'], 'string')
                            , 'BillType' => $this->bsf->isNullCheck($postData['WorkType'], 'string')
                            , 'WORegisterId' => $this->bsf->isNullCheck($postData['WORegisterId'], 'number')
                            ));
                            $update->where(array('BillRegisterId' => $editId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $BillRegisterId = $editId;

                            $inType = 'E';
                            $inName = 'WPM-WorkBill-Edit';
                            $inDesc = 'WorkBill-Edit';
                        }

                        //boq
                        $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                        for ($i = 1; $i <= $rowid; $i++) {
                            $WorkBillFormatTransId = $this->bsf->isNullCheck($postData['WorkBillFormatTransId_' . $i], 'number');
                            $BillFormatTransId = $this->bsf->isNullCheck($postData['BillFormatTransId_' . $i], 'number');
                            $FormatTypeId = $this->bsf->isNullCheck($postData['FormatTypeId_' . $i], 'number');
                            $AccountId = $this->bsf->isNullCheck($postData['AccountId_' . $i], 'number');
                            $Sign = $this->bsf->isNullCheck($postData['Sign_' . $i], 'string');
                            $Formula = trim($this->bsf->isNullCheck($postData['Formula_' . $i], 'string'));
                            $Amount = $this->bsf->isNullCheck($postData['CurAmount_' . $i], 'number');

                            if($WorkBillFormatTransId == 0) {
                                $insert = $sql->insert();
                                $insert->into('WPM_WorkBillFormatTrans');
                                $insert->Values(array('BillRegisterId' => $BillRegisterId, 'BillFormatTransId' => $BillFormatTransId
                                , 'BillFormatId' => $FormatTypeId, 'AccountId' => $AccountId, 'Sign' => $Sign, 'Formula' => $Formula, 'Amount' => $Amount));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $BillAbsId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            } else {
                                $update = $sql->update();
                                $update->table('WPM_WorkBillFormatTrans')
                                    ->set(array('BillRegisterId' => $editId, 'BillFormatTransId' => $BillFormatTransId
                                    , 'BillFormatId' => $FormatTypeId, 'AccountId' => $AccountId, 'Sign' => $Sign, 'Formula' => $Formula, 'Amount' => $Amount))
                                    ->where(array('WorkBillFormatTransId' => $WorkBillFormatTransId));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $BillAbsId = $WorkBillFormatTransId;
                            }

                            $absrowid = $this->bsf->isNullCheck($postData['abs_'.$i.'_rowid'], 'number');
                            switch($FormatTypeId) {
                                case '1': // Agreement
                                case '2': // Non-Agreement
                                    if($WorkType == 'turn-key') {
                                        // delete
                                        $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_' . $i . '_deleteids'], 'string'), ",");
                                        if ($billtransdeleteids !== '') {
                                            $delete = $sql->delete();
                                            $delete->from('WPM_WorkBillTurnKeyTrans')
                                                ->where("WorkBillTurnKeyTransId IN ($billtransdeleteids)");
                                            $statement = $sql->getSqlStringForSqlObject($delete);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                        for ($j = 1; $j <= $absrowid; $j++) {
                                            $WorkBillTurnKeyTransId = $this->bsf->isNullCheck($postData['abs_' . $i . '_WorkBillTransId_' . $j], 'number');
                                            $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_' . $i . '_UpdateBillRow_' . $j], 'number');

                                            $wbsId = $this->bsf->isNullCheck($postData['abs_' . $i . '_wbsid_' . $j], 'number');
                                            $currate = $this->bsf->isNullCheck($postData['abs_' . $i . '_rate_' . $j], 'number');
                                            $curamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_amount_' . $j], 'number');

                                            if ($wbsId == 0 || $currate == 0 || ($UpdateBOQRow != 1 && $WorkBillTurnKeyTransId != 0))
                                                continue;

                                            if ($UpdateBOQRow == 0 && $WorkBillTurnKeyTransId == 0) { // New Row
                                                $insert = $sql->insert();
                                                $insert->into('WPM_WorkBillTurnKeyTrans');
                                                $insert->Values(array('BillRegisterId' => $BillRegisterId, 'WorkBillFormatTransId' => $BillAbsId,
                                                    'BillFormatId' => $BillFormatTransId, 'WBSId' => $wbsId,'CurRate' => $currate, 'CurAmount' => $curamt));
                                                $statement = $sql->getSqlStringForSqlObject($insert);
                                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            } else if ($UpdateBOQRow == 1 && $WorkBillTurnKeyTransId != 0) { // Update Row
                                                $update = $sql->update();
                                                $update->table('WPM_WorkBillTurnKeyTrans')
                                                    ->set(array('BillRegisterId' => $BillRegisterId, 'WorkBillFormatTransId' => $BillAbsId,
                                                        'BillFormatId' => $BillFormatTransId, 'WBSId' => $wbsId,'CurRate' => $currate, 'CurAmount' => $curamt))
                                                    ->where(array('WorkBillTurnKeyTransId' => $WorkBillTurnKeyTransId));
                                                $statement = $sql->getSqlStringForSqlObject($update);
                                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    } else {
                                        // delete boqs
                                        $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_' . $i . '_deleteids'], 'string'), ",");
                                        if ($billtransdeleteids !== '') {
                                            $delete = $sql->delete();
                                            $delete->from('WPM_WorkBillTrans')
                                                ->where("WorkBillTransId IN ($billtransdeleteids)");
                                            $statement = $sql->getSqlStringForSqlObject($delete);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                        // insert, update
                                        for ($j = 1; $j <= $absrowid; $j++) {
                                            $WorkBillTransId = $this->bsf->isNullCheck($postData['abs_' . $i . '_WorkBillTransId_' . $j], 'number');
                                            $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_' . $i . '_UpdateBillRow_' . $j], 'number');

                                            $DPETransId = $this->bsf->isNullCheck($postData['abs_' . $i . '_transid_' . $j], 'number');
                                            $IOWId = $this->bsf->isNullCheck($postData['abs_' . $i . '_iowid_' . $j], 'number');
                                            $ResourceId = $this->bsf->isNullCheck($postData['abs_' . $i . '_resourceid_' . $j], 'number');
                                            $qty = $this->bsf->isNullCheck($postData['abs_' . $i . '_qty_' . $j], 'number');
                                            $ratetype = $this->bsf->isNullCheck($postData['abs_' . $i . '_ratetype_' . $j], 'string');
                                            $partrate = $this->bsf->isNullCheck($postData['abs_' . $i . '_partrate_' . $j], 'number');
                                            $partrateper = $this->bsf->isNullCheck($postData['abs_' . $i . '_partrateper_' . $j], 'number');
                                            $rate = $this->bsf->isNullCheck($postData['abs_' . $i . '_actualrate_' . $j], 'number');
                                            $amt = $this->bsf->isNullCheck($postData['abs_' . $i . '_amount_' . $j], 'number');

                                            if ($rate == 0) {
                                                $rate = $this->bsf->isNullCheck($postData['abs_' . $i . '_rate_' . $j], 'number');
                                            }

                                            if ($ratetype == 'F') {
                                                $partrate = 0;
                                                $partrateper = 0;
                                            }

                                            if ($DPETransId == 0 || $qty == 0 || ($UpdateBOQRow != 1 && $WorkBillTransId != 0))
                                                continue;

                                            if ($UpdateBOQRow == 0 && $WorkBillTransId == 0) { // New Row
                                                $insert = $sql->insert();
                                                $insert->into('WPM_WorkBillTrans');
                                                $insert->Values(array('BillRegisterId' => $BillRegisterId, 'WorkBillFormatTransId' => $BillAbsId,
                                                    'BillFormatId' => $BillFormatTransId, 'DPETransId' => $DPETransId, 'Rate' => $rate
                                                , 'CurQty' => $qty, 'CurAmount' => $amt, 'RateType' => $ratetype, 'PartRate' => $partrate,
                                                    'PartRatePer' => $partrateper, 'IOWId' => $IOWId, 'ResourceId' => $ResourceId, 'FormatTypeId' => $FormatTypeId));
                                                $statement = $sql->getSqlStringForSqlObject($insert);
                                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $WorkBillTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                            } else if ($UpdateBOQRow == 1 && $WorkBillTransId != 0) { // Update Row
                                                $update = $sql->update();
                                                $update->table('WPM_WorkBillTrans')
                                                    ->set(array('BillRegisterId' => $BillRegisterId, 'WorkBillFormatTransId' => $BillAbsId,
                                                        'BillFormatId' => $BillFormatTransId, 'DPETransId' => $DPETransId, 'Rate' => $rate,
                                                        'CurQty' => $qty, 'CurAmount' => $amt, 'RateType' => $ratetype, 'PartRate' => $partrate,
                                                        'PartRatePer' => $partrateper, 'IOWId' => $IOWId, 'ResourceId' => $ResourceId, 'FormatTypeId' => $FormatTypeId))
                                                    ->where(array('WorkBillTransId' => $WorkBillTransId));
                                                $statement = $sql->getSqlStringForSqlObject($update);
                                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }

                                            $actiowrowid = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_rowid'], 'number');
                                            for ($k = 1; $k <= $actiowrowid; $k++) {
                                                $TransId = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_TransId_' . $k], 'number');
                                                $UpdateRow = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_UpdateRow_' . $k], 'number');

                                                $IOWId = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_iowid_' . $k], 'number');
                                                $WBSId = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_wbsid_' . $k], 'number');
                                                $qty = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_qty_' . $k], 'number');
                                                $wrate = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_rate_' . $k], 'number');
                                                $wamount = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_amount_' . $k], 'number');

                                                $measurement = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_Measurement_' . $k], 'string');
                                                $cellname = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_CellName_' . $k], 'string');
                                                $SelectedColumns = $this->bsf->isNullCheck($postData['abs_' . $i . '_act_' . $j . '_SelectedColumns_' . $k], 'string');

                                                if (($IOWId == 0 && $WBSId == 0) || $qty == 0 || ($qty == 0 && $measurement == '') || ($UpdateRow != 1 && $TransId != 0))
                                                    continue;

                                                if($WorkType == 'iow') {
                                                    $TransIdColumn = 'WorkBillWBSTransId';
                                                    $table = 'WPM_WorkBillWBSTrans';
                                                    $IOWOrWBSColumn = 'WBSId';
                                                    $IOWOrWBSValue = $WBSId;
                                                } else if($WorkType == 'activity') {
                                                    $TransIdColumn = 'WorkBillIOWTransId';
                                                    $table = 'WPM_WorkBillIOWTrans';
                                                    $IOWOrWBSColumn = 'IOWId';
                                                    $IOWOrWBSValue = $IOWId;
                                                }

                                                if ($UpdateRow == 0 && $TransId == 0) { // New Row
                                                    $insert = $sql->insert();
                                                    $insert->into($table);
                                                    $insert->Values(array($IOWOrWBSColumn => $IOWOrWBSValue, 'WorkBillTransId' => $WorkBillTransId, 'Qty' => $qty, 'Rate' => $wrate, 'Amount' => $wamount));
                                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                                } else if ($UpdateRow == 1 && $TransId != 0) { // Update Row
                                                    $update = $sql->update();
                                                    $update->table($table)
                                                        ->set(array($IOWOrWBSColumn => $IOWOrWBSValue, 'WorkBillTransId' => $WorkBillTransId, 'Qty' => $qty, 'Rate' => $wrate, 'Amount' => $wamount))
                                                        ->where(array($TransIdColumn => $TransId));
                                                    $statement = $sql->getSqlStringForSqlObject($update);
                                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }

                                                if ($measurement != '') {
                                                    $delete = $sql->delete();
                                                    $delete->from('WPM_WorkBillMeasurementTrans')
                                                        ->where("$TransIdColumn=$TransId");
                                                    $statement = $sql->getSqlStringForSqlObject($delete);
                                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                    $insert = $sql->insert();
                                                    $insert->into('WPM_WorkBillMeasurementTrans');
                                                    $insert->Values(array($TransIdColumn => $TransId, 'Measurement' => $measurement, 'CellName' => $cellname, 'SelectedColumns' => $SelectedColumns));
                                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    }
                                    break;
                                case '3': // Material Recovery
                                    // delete materials
                                    $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_'.$i.'_deleteids'], 'string'), ",");
                                    if ($billtransdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_WorkBillMaterialRecovery')
                                            ->where("WorkBillMaterialRecoveryId IN ($billtransdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillMaterialRecoveryId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillMaterialRecoveryId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $issueno = $this->bsf->isNullCheck($postData['abs_'.$i.'_issueno_' . $j], 'string');
                                        $issuedate = $this->bsf->isNullCheck($postData['abs_'.$i.'_issuedate_' . $j], 'string');
                                        if($issuedate != '')
                                            $issuedate = date('Y-m-d', strtotime($issuedate));
                                        else
                                            $issuedate = NULL;

                                        $ResourceId = $this->bsf->isNullCheck($postData['abs_'.$i.'_resourceid_' . $j], 'number');
                                        $unitid = $this->bsf->isNullCheck($postData['abs_'.$i.'_unitid_' . $j], 'number');
                                        $issueid = $this->bsf->isNullCheck($postData['abs_'.$i.'_issueid_' . $j], 'number');
                                        $typeid = $this->bsf->isNullCheck($postData['abs_'.$i.'_typeid_' . $j], 'number');
                                        $qty = $this->bsf->isNullCheck($postData['abs_'.$i.'_qty_' . $j], 'number');
                                        $rate = $this->bsf->isNullCheck($postData['abs_'.$i.'_rate_' . $j], 'number');
                                        $amt = $this->bsf->isNullCheck($postData['abs_'.$i.'_amount_' . $j], 'number');

                                        if ($ResourceId == 0 || $qty == 0|| ($UpdateBOQRow != 1 && $WorkBillMaterialRecoveryId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillMaterialRecoveryId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillMaterialRecovery');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'ResourceId' => $ResourceId, 'UnitId' => $unitid
                                            , 'IssueId' => $issueid, 'TypeId' => $typeid, 'IssueNo' => $issueno, 'IssueDate' => $issuedate
                                            , 'Rate' => $rate , 'Qty' => $qty, 'Amount' => $amt));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($UpdateBOQRow == 1 && $WorkBillMaterialRecoveryId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillMaterialRecovery')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'ResourceId' => $ResourceId, 'UnitId' => $unitid
                                                , 'IssueId' => $issueid, 'TypeId' => $typeid, 'IssueNo' => $issueno, 'IssueDate' => $issuedate
                                                , 'Rate' => $rate, 'Qty' => $qty, 'Amount' => $amt))
                                                ->where(array('WorkBillMaterialRecoveryId' => $WorkBillMaterialRecoveryId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break; // material recovery
                                case '8': // labour strength
                                    // delete ls
                                    $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_'.$i.'_deleteids'], 'string'), ",");
                                    if ($billtransdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_WorkBillLSTrans')
                                            ->where("WorkBillLSTransId IN ($billtransdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillLSTransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillLSTransId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $RegisterId = $this->bsf->isNullCheck($postData['abs_'.$i.'_lsRegisterId_' . $j], 'number');
                                        $wbsid = $this->bsf->isNullCheck($postData['abs_'.$i.'_wbsId_' . $j], 'number');
                                        $lswbstransid = $this->bsf->isNullCheck($postData['abs_'.$i.'_lsWbsTransId_' . $j], 'number');
                                        $typeid = $this->bsf->isNullCheck($postData['abs_'.$i.'_typeId_' . $j], 'number');
                                        $qty = $this->bsf->isNullCheck($postData['abs_'.$i.'_wbsQty_' . $j], 'number');
                                        $amt = $this->bsf->isNullCheck($postData['abs_'.$i.'_wbsAmount_' . $j], 'number');
                                        $othrs = $this->bsf->isNullCheck($postData['abs_'.$i.'_wbsOtHrs_' . $j], 'number');
                                        $otamount = $this->bsf->isNullCheck($postData['abs_'.$i.'_wbsOtAmount_' . $j], 'number');
                                        $netamount = $this->bsf->isNullCheck($postData['abs_'.$i.'_wbsNetAmount_' . $j], 'number');

                                        if ($lswbstransid == 0 || $qty == 0|| ($UpdateBOQRow != 1 && $WorkBillLSTransId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillLSTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillLSTrans');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'LabourStrengthId' => $RegisterId, 'WBSId' => $wbsid
                                            , 'LSWBSTransId' => $lswbstransid, 'TypeId' => $BillFormatTransId, 'Qty' => $qty, 'Amount' => $amt
                                            , 'OTHrs' => $othrs, 'OTAmount' => $otamount, 'NetAmount' => $netamount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $WorkBillLSTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        } else if ($UpdateBOQRow == 1 && $WorkBillLSTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillLSTrans')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'LabourStrengthId' => $RegisterId, 'WBSId' => $wbsid
                                                , 'LSWBSTransId' => $lswbstransid, 'TypeId' => $BillFormatTransId, 'Qty' => $qty, 'Amount' => $amt
                                                , 'OTHrs' => $othrs, 'OTAmount' => $otamount, 'NetAmount' => $netamount))
                                                ->where(array('WorkBillLSTransId' => $WorkBillLSTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                        $typerowid = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_' . $j . '_rowid'], 'number');
                                        for ($k = 1; $k <= $typerowid; $k++) {
                                            $TransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_TransId_' . $k], 'number');
                                            $UpdateRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_UpdateRow_' . $k], 'number');

                                            $resourceId = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labid_' . $k], 'number');
                                            $qty = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labqty_' . $k], 'number');
                                            $rate = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labrate_' . $k], 'number');
                                            $amount = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labamt_' . $k], 'number');
                                            $othrs = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labothrs_' . $k], 'number');
                                            $otrate = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labotrate_' . $k], 'number');
                                            $otamount = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labotamt_' . $k], 'number');
                                            $netamount = $this->bsf->isNullCheck($postData['abs_'.$i.'_type_'.$j.'_labnetamt_' . $k], 'number');

                                            if ($resourceId == 0 || $qty == 0 || ($UpdateRow != 1 && $TransId != 0))
                                                continue;

                                            if ($UpdateRow == 0 && $TransId == 0) { // New Row
                                                $insert = $sql->insert();
                                                $insert->into('WPM_WorkBillLSTypeTrans');
                                                $insert->Values(array('WorkBillLSTransId' => $WorkBillLSTransId, 'ResourceId' => $resourceId
                                                ,'Qty' => $qty,'Rate' => $rate,'Amount' => $amount, 'OTHrs' => $othrs,'OTRate' => $otrate
                                                , 'OTAmount' => $otamount,'NetAmount' => $netamount));
                                                $statement = $sql->getSqlStringForSqlObject($insert);
                                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            } else if ($UpdateRow == 1 && $TransId != 0) { // Update Row
                                                $update = $sql->update();
                                                $update->table('WPM_WorkBillLSTypeTrans')
                                                    ->set(array('WorkBillLSTransId' => $WorkBillLSTransId, 'ResourceId' => $resourceId
                                                    ,'Qty' => $qty,'Rate' => $rate,'Amount' => $amount, 'OTHrs' => $othrs,'OTRate' => $otrate
                                                    , 'OTAmount' => $otamount,'NetAmount' => $netamount))
                                                    ->where(array(' WorkBillLSTypeTransId' => $TransId));
                                                $statement = $sql->getSqlStringForSqlObject($update);
                                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                    break; // labour
                                case '13': // TDS
                                    $WorkBillTDSTransId = $this->bsf->isNullCheck($postData['abs_' . $i . '_WorkBillTDSTransId'], 'number');
                                    $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_' . $i . '_UpdateRow'], 'number');

                                    $expression = trim($this->bsf->isNullCheck($postData['abs_' . $i . '_tdsexpression'], 'string'));
                                    $tdsexpvalue = $this->bsf->isNullCheck($postData['abs_' . $i . '_tdsexpvalue'], 'number');
                                    $tdsper = $this->bsf->isNullCheck($postData['abs_' . $i . '_tdsper'], 'number');
                                    $tdsamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_tdsamt'], 'number');
                                    $taxable = $this->bsf->isNullCheck($postData['abs_' . $i . '_taxable'], 'number');
                                    $taxableamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_taxableamt'], 'number');
                                    $tax = $this->bsf->isNullCheck($postData['abs_' . $i . '_tax'], 'number');
                                    $taxamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_taxamt'], 'number');
                                    $cess = $this->bsf->isNullCheck($postData['abs_' . $i . '_cess'], 'number');
                                    $cessamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_cessamt'], 'number');
                                    $educess = $this->bsf->isNullCheck($postData['abs_' . $i . '_educess'], 'number');
                                    $educessamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_educessamt'], 'number');
                                    $heducess = $this->bsf->isNullCheck($postData['abs_' . $i . '_heducess'], 'number');
                                    $heducessamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_heducessamt'], 'number');

                                    if ($expression == '' || $tdsexpvalue == 0 || ($UpdateBOQRow != 1 && $WorkBillTDSTransId != 0))
                                        continue;

                                    if ($UpdateBOQRow == 0 && $WorkBillTDSTransId == 0) { // New Row
                                        $insert = $sql->insert();
                                        $insert->into('WPM_WorkBillTDSTrans');
                                        $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'Expression' => $expression, 'BaseAmt' => $tdsexpvalue, 'TDSNetPer' => $tdsper
                                        , 'TDSNetAmt' => $tdsamt, 'TaxablePer' => $taxable, 'TaxableAmt' => $taxableamt, 'TaxPer' => $tax, 'TaxAmt' => $taxamt
                                        , 'CessPer' => $cess, 'CessAmt' => $cessamt, 'EduCessPer' => $educess, 'EduCessAmt' => $educessamt
                                        , 'HEduCessPer' => $heducess, 'HEduCessAmt' => $heducessamt));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else if ($UpdateBOQRow == 1 && $WorkBillTDSTransId != 0) { // Update Row
                                        $update = $sql->update();
                                        $update->table('WPM_WorkBillTDSTrans')
                                            ->set(array('WorkBillFormatTransId' => $BillAbsId, 'Expression' => $expression, 'BaseAmt' => $tdsexpvalue, 'TDSNetPer' => $tdsper
                                            , 'TDSNetAmt' => $tdsamt, 'TaxablePer' => $taxable, 'TaxableAmt' => $taxableamt, 'TaxPer' => $tax, 'TaxAmt' => $taxamt
                                            , 'CessPer' => $cess, 'CessAmt' => $cessamt, 'EduCessPer' => $educess, 'EduCessAmt' => $educessamt
                                            , 'HEduCessPer' => $heducess, 'HEduCessAmt' => $heducessamt))
                                            ->where(array('WorkBillTDSTransId' => $WorkBillTDSTransId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    break;
                                case '11': // Service Tax
                                    $WorkBillServiceTaxTransId = $this->bsf->isNullCheck($postData['abs_' . $i . '_WorkBillServiceTaxTransId'], 'number');
                                    $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_' . $i . '_UpdateRow'], 'number');

                                    $expression = trim($this->bsf->isNullCheck($postData['abs_' . $i . '_tdsexpression'], 'string'));
                                    $tdsexpvalue = $this->bsf->isNullCheck($postData['abs_' . $i . '_tdsexpvalue'], 'number');
                                    $tdsper = $this->bsf->isNullCheck($postData['abs_' . $i . '_tdsper'], 'number');
                                    $tdsamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_tdsamt'], 'number');
                                    $taxable = $this->bsf->isNullCheck($postData['abs_' . $i . '_taxable'], 'number');
                                    $taxableamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_taxableamt'], 'number');
                                    $tax = $this->bsf->isNullCheck($postData['abs_' . $i . '_tax'], 'number');
                                    $taxamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_taxamt'], 'number');
                                    $kkcess = $this->bsf->isNullCheck($postData['abs_' . $i . '_kkcess'], 'number');
                                    $kkcessamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_kkcessamt'], 'number');
                                    $sbcess = $this->bsf->isNullCheck($postData['abs_' . $i . '_sbcess'], 'number');
                                    $sbcessamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_sbcessamt'], 'number');
                                    $reversetax = $this->bsf->isNullCheck($postData['abs_' . $i . '_reversetax6'], 'number');
                                    $reversetaxamt = $this->bsf->isNullCheck($postData['abs_' . $i . '_reversetaxamt'], 'number');

                                    if ($expression == '' || $tdsexpvalue == 0 || ($UpdateBOQRow != 1 && $WorkBillServiceTaxTransId != 0))
                                        continue;

                                    if ($UpdateBOQRow == 0 && $WorkBillServiceTaxTransId == 0) { // New Row
                                        $insert = $sql->insert();
                                        $insert->into('WPM_WorkBillServiceTaxTrans');
                                        $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'Expression' => $expression, 'BaseAmt' => $tdsexpvalue, 'TDSNetPer' => $tdsper
                                        , 'TDSNetAmt' => $tdsamt, 'TaxablePer' => $taxable, 'TaxableAmt' => $taxableamt, 'TaxPer' => $tax, 'TaxAmt' => $taxamt
                                        , 'KKCessPer' => $kkcess, 'KKCessAmt' => $kkcessamt, 'SBCessPer' => $sbcess, 'SBCessAmt' => $sbcessamt
                                        , 'ReverseTaxPer' => $reversetax, 'ReverseTaxAmt' => $reversetaxamt));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else if ($UpdateBOQRow == 1 && $WorkBillServiceTaxTransId != 0) { // Update Row
                                        $update = $sql->update();
                                        $update->table('WPM_WorkBillServiceTaxTrans')
                                            ->set(array('WorkBillFormatTransId' => $BillAbsId, 'Expression' => $expression, 'BaseAmt' => $tdsexpvalue, 'TDSNetPer' => $tdsper
                                            , 'TDSNetAmt' => $tdsamt, 'TaxablePer' => $taxable, 'TaxableAmt' => $taxableamt, 'TaxPer' => $tax, 'TaxAmt' => $taxamt
                                            , 'KKCessPer' => $kkcess, 'KKCessAmt' => $kkcessamt, 'SBCessPer' => $sbcess, 'SBCessAmt' => $sbcessamt
                                            , 'ReverseTaxPer' => $reversetax, 'ReverseTaxAmt' => $reversetaxamt))
                                            ->where(array('WorkBillServiceTaxTransId' => $WorkBillServiceTaxTransId));
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    break;
                                case '14': // advance recovery
                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillAdvMaterialTransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillAdvMaterialTransId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $ARRegisterId = $this->bsf->isNullCheck($postData['abs_'.$i.'_ARRegisterId_' . $j], 'string');
                                        $amt = $this->bsf->isNullCheck($postData['abs_'.$i.'_advmatcuramt_' . $j], 'number');

                                        if ($ARRegisterId == 0 || $amt == 0|| ($UpdateBOQRow != 1 && $WorkBillAdvMaterialTransId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillAdvMaterialTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillAdvRecovery');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId
                                            ,'ARRegisterId' => $ARRegisterId,'Amount' => $amt));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($UpdateBOQRow == 1 && $WorkBillAdvMaterialTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillAdvRecovery')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId
                                                ,'ARRegisterId' => $ARRegisterId,'Amount' => $amt))
                                                ->where(array('WorkBillAdvRecoveryId' => $WorkBillAdvMaterialTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break;
                                case '19': // WCT
                                    // delete
                                    $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_'.$i.'_deleteids'], 'string'), ",");
                                    if ($billtransdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_WorkBillWctTrans')
                                            ->where("WorkBillWctTransId IN ($billtransdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillWctTransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillWCTTransId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $expression = $this->bsf->isNullCheck($postData['abs_'.$i.'_wctexpression_' . $j], 'string');
                                        $expvalue = $this->bsf->isNullCheck($postData['abs_'.$i.'_wctexpvalue_' . $j], 'number');
                                        $expper = $this->bsf->isNullCheck($postData['abs_'.$i.'_wctper_' . $j], 'number');
                                        $nettax = $this->bsf->isNullCheck($postData['abs_'.$i.'_wctnettax_' . $j], 'number');

                                        if ($expression == '' || $expper == 0|| ($UpdateBOQRow != 1 && $WorkBillWctTransId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillWctTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillWctTrans');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'Expression' => $expression
                                            , 'ExpValue' => $expvalue, 'VATPer' => $expper, 'NetValue' => $nettax));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($UpdateBOQRow == 1 && $WorkBillWctTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillWctTrans')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'Expression' => $expression
                                                , 'ExpValue' => $expvalue, 'VATPer' => $expper, 'NetValue' => $nettax))
                                                ->where(array('WorkBillWctTransId' => $WorkBillWctTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break;
                                case '12': // VAT
                                    // delete
                                    $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_'.$i.'_deleteids'], 'string'), ",");
                                    if ($billtransdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_WorkBillVatTrans')
                                            ->where("WorkBillVctTransId IN ($billtransdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillVatTransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillVATTransId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $expression = $this->bsf->isNullCheck($postData['abs_'.$i.'_vatexpression_' . $j], 'string');
                                        $expvalue = $this->bsf->isNullCheck($postData['abs_'.$i.'_vatexpvalue_' . $j], 'number');
                                        $expper = $this->bsf->isNullCheck($postData['abs_'.$i.'_vatper_' . $j], 'number');
                                        $nettax = $this->bsf->isNullCheck($postData['abs_'.$i.'_vatnettax_' . $j], 'number');

                                        if ($expression == '' || $expper == 0|| ($UpdateBOQRow != 1 && $WorkBillVatTransId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillVatTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillVatTrans');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'Expression' => $expression
                                            , 'ExpValue' => $expvalue, 'VATPer' => $expper, 'NetValue' => $nettax));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($UpdateBOQRow == 1 && $WorkBillVatTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillVatTrans')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'Expression' => $expression
                                                , 'ExpValue' => $expvalue, 'VATPer' => $expper, 'NetValue' => $nettax))
                                                ->where(array('WorkBillVctTransId' => $WorkBillVatTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break;
                                case '39': // Price Escalation
                                    // delete
                                    $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_'.$i.'_deleteids'], 'string'), ",");
                                    if ($billtransdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_WorkBillPriceEscalationTrans')
                                            ->where("WorkBillPriceEscalationTransId IN ($billtransdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillPriceEscTransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillPriceEscTransId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $resourceId = $this->bsf->isNullCheck($postData['abs_'.$i.'_priceescmatid_' . $j], 'number');
                                        $qty = $this->bsf->isNullCheck($postData['abs_'.$i.'_priceescqty_' . $j], 'number');
                                        $baserate = $this->bsf->isNullCheck($postData['abs_'.$i.'_priceescbaserate_' . $j], 'number');
                                        $actualrate = $this->bsf->isNullCheck($postData['abs_'.$i.'_priceescactualrate_' . $j], 'number');
                                        $escalation = $this->bsf->isNullCheck($postData['abs_'.$i.'_priceescper_' . $j], 'number');
                                        $rate = $this->bsf->isNullCheck($postData['abs_'.$i.'_priceescrate_' . $j], 'number');
                                        $amount = $this->bsf->isNullCheck($postData['abs_'.$i.'_priceescamt_' . $j], 'number');

                                        if ($resourceId == '' || $qty == 0|| ($UpdateBOQRow != 1 && $WorkBillPriceEscTransId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillPriceEscTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillPriceEscalationTrans');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'ResourceId' => $resourceId
                                            , 'Qty' => $qty, 'BaseRate' => $baserate, 'ActualRate' => $actualrate, 'Escalation' => $escalation
                                            , 'Rate' => $rate, 'Amount' => $amount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($UpdateBOQRow == 1 && $WorkBillPriceEscTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillPriceEscalationTrans')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'ResourceId' => $resourceId
                                                , 'Qty' => $qty, 'BaseRate' => $baserate, 'ActualRate' => $actualrate, 'Escalation' => $escalation
                                                , 'Rate' => $rate, 'Amount' => $amount))
                                                ->where(array('WorkBillPriceEscalationTransId' => $WorkBillPriceEscTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break;
                                case '38': // Excess Material
                                    // delete
                                    $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_'.$i.'_deleteids'], 'string'), ",");
                                    if ($billtransdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_WorkBillExcessMaterialTrans')
                                            ->where("WorkBillExcessMaterialTransId IN ($billtransdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillExcessMatTransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillExcessMaterialTransId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $resourceId = $this->bsf->isNullCheck($postData['abs_'.$i.'_excessmatid_' . $j], 'number');
                                        $qty = $this->bsf->isNullCheck($postData['abs_'.$i.'_excessmatqty_' . $j], 'number');
                                        $rate = $this->bsf->isNullCheck($postData['abs_'.$i.'_excessmatrate_' . $j], 'number');
                                        $amount = $this->bsf->isNullCheck($postData['abs_'.$i.'_excessmatamt_' . $j], 'number');

                                        if ($resourceId == '' || $qty == 0|| ($UpdateBOQRow != 1 && $WorkBillExcessMatTransId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillExcessMatTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillExcessMaterialTrans');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'ResourceId' => $resourceId
                                            , 'Qty' => $qty, 'Rate' => $rate, 'Amount' => $amount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($UpdateBOQRow == 1 && $WorkBillExcessMatTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillExcessMaterialTrans')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'ResourceId' => $resourceId
                                                , 'Qty' => $qty, 'Rate' => $rate, 'Amount' => $amount))
                                                ->where(array('WorkBillExcessMaterialTransId' => $WorkBillExcessMatTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break;
                                case '29': // Theorectical Material
                                    // delete
                                    $billtransdeleteids = rtrim($this->bsf->isNullCheck($postData['abs_'.$i.'_deleteids'], 'string'), ",");
                                    if ($billtransdeleteids !== '') {
                                        $delete = $sql->delete();
                                        $delete->from('WPM_WorkBillTheorecticalMaterialTrans')
                                            ->where("WorkBillTheorecticalMaterialTransId IN ($billtransdeleteids)");
                                        $statement = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                    // insert, update
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $WorkBillTheoMatTransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_WorkBillTheorecticalMaterialTransId_' . $j], 'number');
                                        $UpdateBOQRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_UpdateRow_' . $j], 'number');

                                        $resourceId = $this->bsf->isNullCheck($postData['abs_'.$i.'_theomatid_' . $j], 'number');
                                        $qty = $this->bsf->isNullCheck($postData['abs_'.$i.'_theomatqty_' . $j], 'number');
                                        $rate = $this->bsf->isNullCheck($postData['abs_'.$i.'_theomatrate_' . $j], 'number');
                                        $amount = $this->bsf->isNullCheck($postData['abs_'.$i.'_theomatamt_' . $j], 'number');

                                        if ($resourceId == '' || $qty == 0|| ($UpdateBOQRow != 1 && $WorkBillTheoMatTransId != 0))
                                            continue;

                                        if ($UpdateBOQRow == 0 && $WorkBillTheoMatTransId == 0) { // New Row
                                            $insert = $sql->insert();
                                            $insert->into('WPM_WorkBillTheorecticalMaterialTrans');
                                            $insert->Values(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'ResourceId' => $resourceId
                                            , 'Qty' => $qty, 'Rate' => $rate, 'Amount' => $amount));
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        } else if ($UpdateBOQRow == 1 && $WorkBillTheoMatTransId != 0) { // Update Row
                                            $update = $sql->update();
                                            $update->table('WPM_WorkBillTheorecticalMaterialTrans')
                                                ->set(array('WorkBillFormatTransId' => $BillAbsId, 'BillFormatId' => $BillFormatTransId, 'ResourceId' => $resourceId
                                                , 'Qty' => $qty, 'Rate' => $rate, 'Amount' => $amount))
                                                ->where(array('WorkBillTheorecticalMaterialTransId' => $WorkBillTheoMatTransId));
                                            $statement = $sql->getSqlStringForSqlObject($update);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    break;
                                case '33': // Agreement Recertificate
                                case '34': // Non-Agreement Recertificate
                                    for ($j = 1; $j <= $absrowid; $j++) {
                                        $actrowid = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_' . $j . '_rowid'], 'number');
                                        for ($k = 1; $k <= $actrowid; $k++) {
                                            $recertrowid = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_' . $j . '_recertrowid'], 'number');
                                            for ($l = 1; $l <= $recertrowid; $l++) {
                                                $TransId = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_'.$j.'_recert_'.$k.'_TransId_' . $l], 'number');
                                                $UpdateRow = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_'.$j.'_recert_'.$k.'_UpdateRow_' . $l], 'number');

                                                $billtransid = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_'.$j.'_recert_'.$k.'_billtransid_' . $l], 'number');
                                                $billiowtransid = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_'.$j.'_recert_'.$k.'_billiowtransid_' . $l], 'number');
                                                $billwbstransid = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_'.$j.'_recert_'.$k.'_billwbstransid_' . $l], 'number');
                                                $qty = $this->bsf->isNullCheck($postData['abs_'.$i.'_act_'.$j.'_recert_'.$k.'_qty_' . $l], 'number');

                                                if ( ($billiowtransid == 0 && $billwbstransid == 0) || $qty == 0 || ($UpdateRow != 1 && $TransId != 0))
                                                    continue;

                                                if ($UpdateRow == 0 && $TransId == 0) { // New Row
                                                    $insert = $sql->insert();
                                                    $insert->into('WPM_WorkBillReCertificateTrans');
                                                    $insert->Values(array('WorkBillRegisterId' => $BillRegisterId, 'WorkBillTransId' => $billtransid
                                                    , 'WorkBillIOWTransId' => $billiowtransid,'WorkBillWBSTransId' => $billwbstransid,
                                                        'Qty' => $qty, 'FormatTypeId' => $FormatTypeId));
                                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                } else if ($UpdateRow == 1 && $TransId != 0) { // Update Row
                                                    $update = $sql->update();
                                                    $update->table('WPM_WorkBillReCertificateTrans')
                                                        ->set(array('WorkBillRegisterId' => $BillRegisterId, 'WorkBillTransId' => $billtransid,
                                                            'WorkBillIOWTransId' => $billiowtransid,'WorkBillWBSTransId' => $billwbstransid,
                                                            'Qty' => $qty, 'FormatTypeId' => $FormatTypeId))
                                                        ->where(array(' WorkBillReCertificateTransId' => $TransId));
                                                    $statement = $sql->getSqlStringForSqlObject($update);
                                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }

                                            }
                                        }
                                    }
                                    break;
                            }
                        }

                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'), $inName, $inType, $inDesc, $BillRegisterId, 0, 0, 'WPM', $wbNo, $this->auth->getIdentity()->UserId, 0, 0);

                        /*if($editId == 0)
                            $this->redirect()->toRoute('wpm/default', array('controller' => 'workbill', 'action' => 'index'));
                        else*/
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'bill-register'));
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                }
            } else {
                // get request
                if($editId != 0) {
                    // register details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WPM_WorkBillRegister' ) )
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreId', 'CostCentreName', 'WBSReqWPM', 'ProjectId', 'CompanyId'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array('VendorId', 'VendorName'), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_WORegister'), 'a.WORegisterId=d.WORegisterId', array('WONo', 'WORegisterId', 'WOType'), $select::JOIN_LEFT)
                        ->columns( array("BillRegisterId", "BillNo", "BillDate" => new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"), "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")
                        , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), 'Narration','WORegisterId'
                        , 'CostCentreId', 'VendorId','CompanyBVNo','RefNo','CCBVNo') )
                        ->where( "a.DeleteFlag=0 AND a.BillRegisterId=$editId" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $billregister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                    if($billregister == FALSE)
                        $this->redirect()->toRoute("wpm/default", array("controller" => "workbill","action" => "index"));

                    $this->_view->billregister = $billregister;

                    $projectId = $billregister['ProjectId'];
                    $this->_view->projectId = $projectId;
                    $this->_view->wbsReq = $billregister['WBSReqWPM'];
                    $this->_view->companyId = $billregister['CompanyId'];

                    $WorkType = $billregister['WOType'];
                    $this->_view->WorkType = $WorkType;
                    if($WorkType == 'activity')
                        $this->_view->WorkTypeName = 'Activity';
                    else if($WorkType == 'iow')
                        $this->_view->WorkTypeName = 'IOW';
                    else
                        $this->_view->WorkTypeName = 'Turn Key';

                    // bill abstract
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_WorkBillFormatTrans'))
                        ->join(array('b' => 'WPM_BillFormatTrans'), 'a.BillFormatTransId=b.BillFormatTransId', array('BillFormatId','Description'), $select:: JOIN_LEFT)
                        ->join(array('c' => 'WPM_BillFormatMaster'), 'b.BillFormatId=c.BillFormatId', array('TypeName', 'Sign', 'AccountTypeId', 'Header'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'FA_AccountMaster'), 'a.AccountId=d.AccountId', array('AccountName'), $select:: JOIN_LEFT)
                        ->columns(array('WorkBillFormatTransId','BillFormatTransId','Amount','AccountId','Formula','Sign','FormatType','PrevAmount' => new Expression('1-1')))
                        ->where("BillRegisterId=".$editId)
                        ->order(new Expression('b.SortId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arr_billformats = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($arr_billformats as &$format) {
                        switch($format['BillFormatId']) {
                            case '1':
                            case '2': // agreement and non-agreement
                                if ($WorkType == 'iow') {
                                    // iows
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_WorkBillTrans'))
                                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('Code' => 'RefSerialNo', 'Specification'), $select:: JOIN_LEFT)
                                        ->join(array('d' => 'Proj_ProjectIOW'), 'c.ProjectIOWId=d.ProjectIOWId', array(), $select::JOIN_LEFT)
                                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillFormatTransId','WorkBillTransId','DPETransId', 'IOWId', 'ResourceId', 'Rate', 'Qty' => 'CurQty',
                                            'Amount' => 'CurAmount', 'CumQty','CumAmount','RateType','PartRate','PartRatePer'))
                                        ->where("a.BillRegisterId=" . $editId . " AND a.FormatTypeId=".$format['BillFormatId']." AND a.IOWId != 0");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if (count($arr_iow) > 0) {
                                        $format['AddRow'] = $arr_iow;

                                        // wbs
                                        $subQuery = $sql->select();
                                        $subQuery->from(array('g' => "WPM_WorkBillTrans"))
                                            ->columns(array('WorkBillTransId'))
                                            ->where("g.BillRegisterId=" . $editId . " AND a.FormatTypeId=".$format['BillFormatId']." AND g.IOWId != 0");

                                            $select = $sql->select();
                                            $select->from(array("a" => "WPM_WorkBillWBSTrans"))
                                                ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                                //->join(array('c' => 'Proj_WBSTrans'), 'b.WBSId=c.WBSId', array(), $select::JOIN_LEFT)
                                                //->join(array('d' => 'Proj_ProjectIOWMaster'), 'd.ProjectIOWId=c.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                                //->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                                ->columns(array('TransId' => 'WorkBillWBSTransId', 'WorkBillTransId', 'ProjectIOWId' => new Expression("'0'"), 'Qty', 'Rate', 'Amount'))
                                                ->group(new Expression('a.WorkBillWBSTransId,a.WorkBillTransId,a.Qty,a.Rate,a.Amount,b.WBSId,b.WBSName,b.ParentText'))
                                                //->where('a.WorkBillTransId = '.$main_row['WorkBillTransId']);
                                                ->where->expression('a.WorkBillTransId IN ?', array($subQuery));
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    }
                                } else if ($WorkType == 'activity') {
                                    // resources
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_WorkBillTrans'))
                                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select::JOIN_LEFT)
                                        ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceId', array('Code', 'Specification' => 'ResourceName', 'Rate'), $select::JOIN_LEFT)
                                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                        ->join(array('f' => 'WPM_WOTrans'), 'b.WORegisterId=f.WORegisterId AND a.ResourceId=f.ResourceId', array('WOQty' => 'Qty'), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillFormatTransId','WorkBillTransId','DPETransId', 'IOWId', 'ResourceId', 'Rate', 'Qty' => 'CurQty',
                                            'Amount' => 'CurAmount', 'CumQty','CumAmount','RateType','PartRate','PartRatePer'))
                                        ->where("a.BillRegisterId=" . $editId . " AND a.FormatTypeId=".$format['BillFormatId']." AND a.ResourceId != 0");
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if (count($arr_resource) > 0) {
                                        $format['AddRow'] = $arr_resource;

                                        // iow
                                        $subQuery = $sql->select();
                                        $subQuery->from(array('g' => "WPM_WorkBillTrans"))
                                            ->columns(array('WorkBillTransId'))
                                            ->where("g.BillRegisterId=" . $editId . " AND a.FormatTypeId=".$format['BillFormatId']." AND g.ResourceId != 0");
                                        $select = $sql->select();
                                        $select->from(array("a" => "WPM_WorkBillIOWTrans"))
                                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                            ->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=c.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                                            ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                            ->columns(array('TransId'=>'WorkBillIOWTransId','WorkBillTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'Rate', 'Amount'))
                                            ->where->expression('a.WorkBillTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    }
                                } else if ($WorkType == 'turn-key') {
                                    // Turn Key
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_WorkBillTurnKeyTrans'))
                                        ->join(array('c' => 'WPM_WOTurnKeyTrans'), 'a.WBSId=c.WBSId', array('Area' => new Expression('SUM(c.Area)'), 'WORate'=> new Expression('SUM(c.Rate)'), 'WOAmount' => new Expression('SUM(c.Amount)')), $select::JOIN_LEFT)
                                        ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array("Desc" => new Expression("d.ParentText + '->' + d.WBSName")), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillTurnKeyTransId','WorkBillFormatTransId','WBSId','CurRate','CurAmount','BillFormatId'))
                                        ->group(new Expression('a.WorkBillTurnKeyTransId,a.WorkBillFormatTransId,a.WBSId,a.CurRate,a.CurAmount,a.BillFormatId,d.ParentText,d.WBSName'))
                                        ->where("a.BillRegisterId=$editId");
                                    $statement = $sql->getSqlSltringForSqlObject($select);
                                    $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if (count($arr_resource) > 0) {
                                        $format['AddRow'] = $arr_resource;
                                    }
                                }
                                break;
                            case '3': // material recovery
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillMaterialRecovery'))
                                    ->join(array('b' => 'Proj_Resource'), "a.ResourceId=b.ResourceId", array('Code','ResourceName'), $select:: JOIN_LEFT)
                                    ->join(array('e' => 'MMS_IssueTrans'), 'a.IssueId=e.IssueTransId', array(), $select::JOIN_LEFT)
                                    ->join(array('c' => 'MMS_IssueRegister'), 'e.IssueRegisterId=c.IssueRegisterId', array('IssueType','IssueNo', 'IssueDate' => new Expression("FORMAT(c.IssueDate, 'dd-MM-yyyy')")), $select::JOIN_LEFT)
                                    ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                    ->columns(array('WorkBillMaterialRecoveryId','ResourceId','Qty', 'Rate', 'Amount'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_resource ) > 0) {
                                    $format['AddRow'] = $arr_resource;

                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_WorkBillMaterialRecovery'))
                                        ->join(array('b' => 'WPM_WorkBillFormatTrans'), "b.WorkBillFormatTransId=a.WorkBillFormatTransId", array(), $select:: JOIN_LEFT)
                                        ->join(array('c' => 'WPM_WorkBillRegister'), 'c.BillRegisterId=b.BillRegisterId', array(), $select::JOIN_LEFT)
                                        ->columns(array('ResourceId', 'CumQty' => new Expression("SUM(a.Qty)")))
                                        ->where("b.BillFormatTransId=8 AND c.CostCentreId=".$billregister['CostCentreId']." AND c.VendorId=".$billregister['VendorId'])
                                        ->group(new Expression("a.ResourceId,a.Qty"));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_recovery_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    $format['Recovery'] = $arr_recovery_resource;
                                }
                                break;
                            case '8': // labour strength
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillLSTrans'))
                                    ->join(array('d' => 'Proj_WBSMaster'), 'a.WBSId=d.WBSId', array('WBSName'), $select::JOIN_LEFT)
                                    ->columns(array('WorkBillLSTransId','LSWBSTransId', 'WBSId', 'Qty', 'Amount', 'OTHrs', 'OTAmount', 'NetAmount'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_resource ) > 0) {
                                    $format['AddRow'] = $arr_resource;

                                    $subQuery = $sql->select();
                                    $subQuery->from(array('g' => "WPM_WorkBillLSTrans"))
                                        ->columns(array('WorkBillLSTransId'))
                                        ->where("g.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                    $select = $sql->select();
                                    $select->from(array("a" => "WPM_WorkBillLSTypeTrans"))
                                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceId', 'ResourceName'), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillLSTypeTransId','WorkBillLSTransId','Qty', 'Rate', 'Amount', 'OTHrs', 'OTRate', 'OTAmount', 'NetAmount'))
                                        ->where->expression('a.WorkBillLSTransId IN ?', array($subQuery));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                }

                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_LabourStrengthRegister'))
                                    ->join(array('b' => 'WPM_LSVendorTrans'), 'a.LSRegisterId=b.LSRegisterId', array(), $select:: JOIN_LEFT)
                                    ->columns(array('LSRegisterId','LSNo','LSDate' => new Expression("Format(a.LSDate, 'dd-MM-yyyy')"), 'Include' => new Expression("'0'")))
                                    ->where("a.CostCentreId=".$billregister['CostCentreId']." AND b.VendorId=".$billregister['VendorId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_lsregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_lsregister) > 0) {
                                    $format['LSRegister'] = json_encode($arr_lsregister);
                                }
                                break;
                            case '13': // TDS
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillTDSTrans'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_tds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                if(count($arr_tds ) > 0) {
                                    $format['data'] = $arr_tds;
                                }
                                break;
                            case '11': // Service Tax
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillServiceTaxTrans'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_tds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                if(count($arr_tds ) > 0) {
                                    $format['data'] = $arr_tds;
                                }
                                break;
                            case '14': // advance recovery
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillAdvRecovery'))
                                    ->join(array('c' => 'WPM_AdvanceRegister'), 'a.ARRegisterId=c.ARRegisterId', array('ARRegisterId','RefNo','RefDate' => new Expression("FORMAT(c.RefDate, 'dd-MM-yyyy')"),'TypeId', 'TotalAmt' => 'CurrentPaidAmount'), $select:: JOIN_LEFT)
                                    ->join(array('b' => 'WPM_AdvanceTypeMaster'), 'c.TypeId=b.TypeId', array('TypeName'), $select:: JOIN_LEFT)
                                    ->columns(array('WorkBillAdvRecoveryId','Amount'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_iow) > 0) {
                                    $format['AddRow'] = $arr_iow;

                                    $subQuery = $sql->select();
                                    $subQuery->from(array('g' => "WPM_WorkBillAdvRecovery"))
                                        ->columns(array('ARRegisterId'))
                                        ->where("g.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                    $select = $sql->select();
                                    $select->from(array('a' => 'WPM_WorkBillAdvRecovery'))
                                        ->columns(array('ARRegisterId', 'RecAmount' => new Expression("SUM(a.Amount)")))
                                        ->group(new Expression("a.ARRegisterId,a.Amount"))
                                        ->where->expression('a.ARRegisterId IN ?', array($subQuery));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_recovery_resource = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    $format['Recovery'] = $arr_recovery_resource;
                                }
                                break;
                            case '19': // WCT
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillWctTrans'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_iow) > 0) {
                                    $format['AddRow'] = $arr_iow;
                                }
                                break;
                            case '12': // VAT
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillVatTrans'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_iow) > 0) {
                                    $format['AddRow'] = $arr_iow;
                                }
                                break;
                            case '39': // Price Escalation
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillPriceEscalationTrans'))
                                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceId', "ResourceName" => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId', 'UnitName'), $select:: JOIN_LEFT)
                                    ->columns(array('WorkBillPriceEscalationTransId','BaseRate', 'ActualRate', 'Escalation', 'Rate', 'Amount'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_iow) > 0) {
                                    $format['AddRow'] = $arr_iow;
                                }

                                // WO Material List
                                $select = $sql->select();
                                $select->from(array('a' => "WPM_WOMaterialBaseRate"))
                                    ->join(array('b' => 'Proj_Resource'), 'a.MaterialId=b.ResourceId', array("data" => 'ResourceId', "value" => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId', 'UnitName'), $select:: JOIN_LEFT)
                                    ->columns(array('Rate', 'EscalationPer', 'RateCondition', 'ActualRate'))
                                    ->where("a.WORegisterId=".$billregister['WORegisterId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $this->_view->womateriallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                break;
                            case '38': // Excess Material
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillExcessMaterialTrans'))
                                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceId', "ResourceName" => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId', 'UnitName'), $select:: JOIN_LEFT)
                                    ->columns(array('WorkBillExcessMaterialTransId','Rate', 'Amount', 'Qty'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_iow) > 0) {
                                    $format['AddRow'] = $arr_iow;
                                }
                                break;
                            case '29': // Theorectical Material
                                $select = $sql->select();
                                $select->from(array('a' => 'WPM_WorkBillTheorecticalMaterialTrans'))
                                    ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceId', "ResourceName" => new Expression("b.Code + ' ' + b.ResourceName")), $select:: JOIN_LEFT)
                                    ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitId', 'UnitName'), $select:: JOIN_LEFT)
                                    ->columns(array('TransId'=>'WorkBillTheorecticalMaterialTransId','Rate', 'Amount', 'Qty'))
                                    ->where("a.WorkBillFormatTransId=".$format['WorkBillFormatTransId']);
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($arr_iow) > 0) {
                                    $format['AddRow'] = $arr_iow;
                                }
                                break;
                            case '33': // Agreement Recertificate
                                if($WorkType == 'activity') {
                                    $select = $sql->select();
                                    $select->from(array('a' => "WPM_WorkBillTrans"))
                                        ->join(array('z' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=z.WorkBillTransId', array('RecertTransId'=>'WorkBillReCertificateTransId'), $select:: JOIN_LEFT)
                                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                                        ->join(array('c' => 'WPM_WorkBillIOWTrans'), 'z.WorkBillIOWTransId=c.WorkBillIOWTransId', array('TransIOWId' => 'IOWId'), $select:: JOIN_LEFT)
                                        ->join(array('d' => 'Proj_Resource'), 'a.ResourceId=d.ResourceId', array('desc' => new Expression("d.Code +' '+ d.ResourceName")), $select:: JOIN_LEFT)
                                        ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillTransId','Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)'),'decid' => 'ResourceId','ResourceId'))
                                        ->where("z.FormatTypeId = 33 AND z.WorkBillRegisterId=".$editId)
                                        ->group(new Expression("z.WorkBillReCertificateTransId,a.WorkBillTransId,a.ResourceId,a.Rate,e.UnitId,e.UnitName,d.Code,d.ResourceName,c.IOWId"));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_res = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($arr_res) > 0) {
                                        $format['AddRow'] = $arr_res;

                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_WorkBillReCertificateTrans'))
                                            ->join(array('z' => 'WPM_WorkBillIOWTrans'), 'z.WorkBillIOWTransId=a.WorkBillIOWTransId', array('ProjectIOWId' => 'IOWId', 'WBSID' => new Expression("'0'")), $select::JOIN_LEFT)
                                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'z.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                            ->join(array('i' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=i.WorkBillTransId', array(), $select::JOIN_LEFT)
                                            ->join(array('c' => 'Proj_WBSTrans'), 'z.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSID=c.WBSID', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                            ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                            ->columns(array('WorkBillReCertificateTransId', 'WorkBillTransId', 'WorkBillIOWTransId', 'Qty'))
                                            ->where("a.FormatTypeId = 33 AND a.WorkBillRegisterId=".$editId);
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        $subQuery = $sql->select();
                                        $subQuery->from(array('g' => "WPM_WorkBillReCertificateTrans"))
                                            ->columns(array('WorkBillTransId'))
                                            ->where("g.FormatTypeId = 33 AND g.WorkBillRegisterId=".$editId);

                                        $select = $sql->select();
                                        $select->from(array("a" => "WPM_WorkBillTrans"))
                                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array('BillDate' => new Expression("FORMAT(b.BillDate, 'dd-MM-yyyy')"), 'BillNo'), $select::JOIN_LEFT)
                                            ->join(array('c' => 'WPM_WorkBillIOWTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('WorkBillIOWTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'WBSID' => new Expression("'0'"), 'IOWId'), $select:: JOIN_LEFT)
                                            ->join(array('d' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=d.WorkBillTransId AND c.WorkBillIOWTransId=d.WorkBillIOWTransId', array('AdjQty' => new Expression("SUM(d.Qty)"), 'CerQty' => 'Qty'), $select:: JOIN_LEFT)
                                            ->columns(array('WorkBillTransId'))
                                            ->group(new Expression("c.WorkBillIOWTransId, c.IOWId,c.Qty,a.WorkBillTransId,b.BillDate,b.BillNo,d.Qty"))
                                            ->where->expression('a.WorkBillTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['Bills'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    }
                                } else {
                                    $select = $sql->select();
                                    $select->from(array('a' => "WPM_WorkBillTrans"))
                                        ->join(array('z' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=z.WorkBillTransId', array('RecertTransId'=>'WorkBillReCertificateTransId'), $select:: JOIN_LEFT)
                                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                                        ->join(array('c' => 'WPM_WorkBillWBSTrans'), 'z.WorkBillWBSTransId=c.WorkBillWBSTransId', array('TransWBSId' => 'WBSId'), $select:: JOIN_LEFT)
                                        ->join(array('d' => 'Proj_ProjectIOWMaster'), 'a.IOWId=d.ProjectIOWId', array('desc' => new Expression("d.RefSerialNo + ' ' + d.Specification")), $select:: JOIN_LEFT)
                                        ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillTransId','Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)'),'decid' => 'IOWId','IOWId'))
                                        ->where("z.FormatTypeId = 33 AND z.WorkBillRegisterId=".$editId)
                                        ->group(new Expression("z.WorkBillReCertificateTransId,a.WorkBillTransId,a.IOWId,a.Rate,e.UnitId,e.UnitName,d.RefSerialNo,d.Specification,c.WBSId"));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_res = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($arr_res) > 0) {
                                        $format['AddRow'] = $arr_res;

                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_WorkBillReCertificateTrans'))
                                            ->join(array('z' => 'WPM_WorkBillWBSTrans'), 'z.WorkBillWBSTransId=a.WorkBillWBSTransId', array('WBSId', 'ProjectIOWId' => new Expression("'0'")), $select::JOIN_LEFT)
                                            ->join(array('i' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=i.WorkBillTransId', array(), $select::JOIN_LEFT)
                                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSID=z.WBSID', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                            ->join(array('c' => 'Proj_WBSTrans'), 'd.WBSId=c.WBSId', array(), $select::JOIN_LEFT)
                                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'c.ProjectIOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                            ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                            ->columns(array('WorkBillReCertificateTransId', 'WorkBillTransId', 'WorkBillWBSTransId', 'Qty'))
                                            ->where("a.FormatTypeId = 33 AND a.WorkBillRegisterId=".$editId);
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        $subQuery = $sql->select();
                                        $subQuery->from(array('g' => "WPM_WorkBillReCertificateTrans"))
                                            ->columns(array('WorkBillTransId'))
                                            ->where("g.FormatTypeId = 33 AND g.WorkBillRegisterId=".$editId);

                                        $select = $sql->select();
                                        $select->from(array("a" => "WPM_WorkBillTrans"))
                                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array('BillDate' => new Expression("FORMAT(b.BillDate, 'dd-MM-yyyy')"), 'BillNo'), $select::JOIN_LEFT)
                                            ->join(array('c' => 'WPM_WorkBillWBSTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('WorkBillWBSTransId', 'WBSID', 'Qty', 'ProjectIOWId' => new Expression("'0'")), $select:: JOIN_LEFT)
                                            ->join(array('d' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=d.WorkBillTransId AND c.WorkBillWBSTransId=d.WorkBillWBSTransId', array('AdjQty' => new Expression("SUM(d.Qty)"), 'CerQty' => 'Qty'), $select:: JOIN_LEFT)
                                            ->columns(array('WorkBillTransId'))
                                            ->group(new Expression("c.WorkBillWBSTransId, c.WBSId,c.Qty,a.WorkBillTransId,b.BillDate,b.BillNo,d.Qty"))
                                            ->where->expression('a.WorkBillTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['Bills'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    }
                                }
                                break;
                            case '34': // Non-Agreement Recertificate
                                if($WorkType == 'activity') {
                                    $select = $sql->select();
                                    $select->from(array('a' => "WPM_WorkBillTrans"))
                                        ->join(array('z' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=z.WorkBillTransId', array('RecertTransId'=>'WorkBillReCertificateTransId'), $select:: JOIN_LEFT)
                                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                                        ->join(array('c' => 'WPM_WorkBillIOWTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('TransIOWId' => 'IOWId'), $select:: JOIN_LEFT)
                                        ->join(array('d' => 'Proj_Resource'), 'a.ResourceId=d.ResourceId', array('desc' => new Expression("d.Code +' '+ d.ResourceName")), $select:: JOIN_LEFT)
                                        ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillTransId','Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)'),'decid' => 'ResourceId','ResourceId'))
                                        ->where("z.FormatTypeId = 34 AND z.WorkBillRegisterId=".$editId)
                                        ->group(new Expression("z.WorkBillReCertificateTransId,a.WorkBillTransId,a.ResourceId,a.Rate,e.UnitId,e.UnitName,d.Code,d.ResourceName,c.IOWId"));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_res = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($arr_res) > 0) {
                                        $format['AddRow'] = $arr_res;

                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_WorkBillReCertificateTrans'))
                                            ->join(array('z' => 'WPM_WorkBillIOWTrans'), 'z.WorkBillIOWTransId=a.WorkBillIOWTransId', array('ProjectIOWId' => 'IOWId', 'WBSID' => new Expression("'0'")), $select::JOIN_LEFT)
                                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'z.IOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                            ->join(array('i' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=i.WorkBillTransId', array(), $select::JOIN_LEFT)
                                            ->join(array('c' => 'Proj_WBSTrans'), 'z.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSID=c.WBSID', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                            ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                            ->columns(array('WorkBillReCertificateTransId', 'WorkBillTransId', 'WorkBillIOWTransId', 'Qty'))
                                            ->where("a.FormatTypeId = 34 AND a.WorkBillRegisterId=".$editId);
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        $subQuery = $sql->select();
                                        $subQuery->from(array('g' => "WPM_WorkBillReCertificateTrans"))
                                            ->columns(array('WorkBillTransId'))
                                            ->where("g.FormatTypeId = 34 AND g.WorkBillRegisterId=".$editId);

                                        $select = $sql->select();
                                        $select->from(array("a" => "WPM_WorkBillTrans"))
                                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array('BillDate' => new Expression("FORMAT(b.BillDate, 'dd-MM-yyyy')"), 'BillNo'), $select::JOIN_LEFT)
                                            ->join(array('c' => 'WPM_WorkBillIOWTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('WorkBillIOWTransId', 'ProjectIOWId' => 'IOWId', 'Qty', 'WBSID' => new Expression("'0'"), 'IOWId'), $select:: JOIN_LEFT)
                                            ->join(array('d' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=d.WorkBillTransId AND c.WorkBillIOWTransId=d.WorkBillIOWTransId', array('AdjQty' => new Expression("SUM(d.Qty)"), 'CerQty' => 'Qty'), $select:: JOIN_LEFT)
                                            ->columns(array('WorkBillTransId'))
                                            ->group(new Expression("c.WorkBillIOWTransId, c.IOWId,c.Qty,a.WorkBillTransId,b.BillDate,b.BillNo,d.Qty"))
                                            ->where->expression('a.WorkBillTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['Bills'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    }
                                } else {
                                    $select = $sql->select();
                                    $select->from(array('a' => "WPM_WorkBillTrans"))
                                        ->join(array('z' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=z.WorkBillTransId', array('RecertTransId'=>'WorkBillReCertificateTransId'), $select:: JOIN_LEFT)
                                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                                        ->join(array('c' => 'WPM_WorkBillWBSTrans'), 'z.WorkBillWBSTransId=c.WorkBillWBSTransId', array('TransWBSId' => 'WBSId'), $select:: JOIN_LEFT)
                                        ->join(array('d' => 'Proj_ProjectIOWMaster'), 'a.IOWId=d.ProjectIOWId', array('desc' => new Expression("d.RefSerialNo + ' ' + d.Specification")), $select:: JOIN_LEFT)
                                        ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                        ->columns(array('WorkBillTransId','Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)'),'decid' => 'IOWId','IOWId'))
                                        ->where("z.FormatTypeId = 34 AND z.WorkBillRegisterId=".$editId)
                                        ->group(new Expression("z.WorkBillReCertificateTransId,a.WorkBillTransId,a.IOWId,a.Rate,e.UnitId,e.UnitName,d.RefSerialNo,d.Specification,c.WBSId"));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $arr_res = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    if(count($arr_res) > 0) {
                                        $format['AddRow'] = $arr_res;

                                        $select = $sql->select();
                                        $select->from(array('a' => 'WPM_WorkBillReCertificateTrans'))
                                            ->join(array('z' => 'WPM_WorkBillWBSTrans'), 'z.WorkBillWBSTransId=a.WorkBillWBSTransId', array('WBSId', 'ProjectIOWId' => new Expression("'0'")), $select::JOIN_LEFT)
                                            ->join(array('i' => 'WPM_WorkBillTrans'), 'z.WorkBillTransId=i.WorkBillTransId', array(), $select::JOIN_LEFT)
                                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSID=z.WBSID', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                                            ->join(array('c' => 'Proj_WBSTrans'), 'd.WBSId=c.WBSId', array(), $select::JOIN_LEFT)
                                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'c.ProjectIOWId=b.ProjectIOWId', array('RefSerialNo', 'Specification'), $select::JOIN_LEFT)
                                            ->join(array('e' => 'Proj_UOM'), 'b.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                                            ->columns(array('WorkBillReCertificateTransId', 'WorkBillTransId', 'WorkBillWBSTransId', 'Qty'))
                                            ->where("a.FormatTypeId = 34 AND a.WorkBillRegisterId=".$editId);
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['AddSubRow'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        $subQuery = $sql->select();
                                        $subQuery->from(array('g' => "WPM_WorkBillReCertificateTrans"))
                                            ->columns(array('WorkBillTransId'))
                                            ->where("g.FormatTypeId = 34 AND g.WorkBillRegisterId=".$editId);

                                        $select = $sql->select();
                                        $select->from(array("a" => "WPM_WorkBillTrans"))
                                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array('BillDate' => new Expression("FORMAT(b.BillDate, 'dd-MM-yyyy')"), 'BillNo'), $select::JOIN_LEFT)
                                            ->join(array('c' => 'WPM_WorkBillWBSTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('WorkBillWBSTransId', 'WBSID', 'Qty', 'ProjectIOWId' => new Expression("'0'")), $select:: JOIN_LEFT)
                                            ->join(array('d' => 'WPM_WorkBillReCertificateTrans'), 'a.WorkBillTransId=d.WorkBillTransId AND c.WorkBillWBSTransId=d.WorkBillWBSTransId', array('AdjQty' => new Expression("SUM(d.Qty)"), 'CerQty' => 'Qty'), $select:: JOIN_LEFT)
                                            ->columns(array('WorkBillTransId'))
                                            ->group(new Expression("c.WorkBillWBSTransId, c.WBSId,c.Qty,a.WorkBillTransId,b.BillDate,b.BillNo,d.Qty"))
                                            ->where->expression('a.WorkBillTransId IN ?', array($subQuery));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $format['Bills'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    }
                                }
                                break;
                        }

                        // sum prev amount
                        $select = $sql->select();
                        $select->from(array('a' => 'WPM_WorkBillFormatTrans'))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'b.BillRegisterId=a.BillRegisterId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'WPM_BillFormatTrans'), 'c.BillFormatTransId=a.BillFormatTransId', array(), $select:: JOIN_LEFT)
                            ->columns(array('PrevAmount' => new Expression('SUM(a.Amount)')))
                            ->where("b.DeleteFlag=0 AND b.CostCentreId=".$billregister['CostCentreId']." AND b.WORegisterId=" .$billregister['WORegisterId']
                                ." AND b.VendorId=".$billregister['VendorId']." AND c.BillFormatTransId=".$format['BillFormatTransId']." AND b.BillRegisterId<".$editId);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_formatPrev = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $format['PrevAmount'] = $arr_formatPrev['PrevAmount'];
                    }
                    $this->_view->arr_billformats = $arr_billformats;

                    if($WorkType == 'activity') {
                        // get resource lists
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPETrans"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('Desc' => new Expression("b.Code+ ' ' +b.ResourceName"), 'Rate'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('DPETransId','DescId' => 'ResourceId','Qty','Rate', 'Amount'))
                            ->where('a.DPERegisterId='.$editId)
                            ->group(new Expression('a.DPETransId,a.ResourceId,a.Qty,a.Rate,a.Amount,b.Code,b.ResourceName,b.Rate,c.UnitName,c.UnitId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_DPETrans")
                            ->columns(array('DPETransId'))
                            ->where('DPERegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPEIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=c.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('DPEIOWTransId','DPETransId','ProjectIOWId' => 'IOWId', 'Qty'))
                            ->where->expression('a.DPETransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectResource"))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('value' => new Expression("Code+ ' ' +ResourceName"), 'data' => 'ResourceId'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT)
                            ->columns(array('Rate'))
                            ->where("b.TypeId=4")
                            ->order('a.ResourceId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    else if($WorkType == 'iow') {
                        // get iow lists
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPETrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.IOWId', array('Desc' => new Expression("b.RefSerialNo+ ' ' +b.Specification")), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_ProjectIOW'), 'd.ProjectIOWId=b.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('DescId' => 'IOWId'))
                            ->where('a.DPERegisterId='.$editId)
                            ->group(new Expression('a.IowId,b.RefSerialNo,b.Specification,d.Rate,c.UnitName,c.UnitId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_DPETrans")
                            ->columns(array('DPETransId'))
                            ->where('DPERegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_DPEIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.IOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=c.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('DPEIOWTransId','DPETransId','ProjectIOWId' => 'IOWId', 'Qty'))
                            ->where->expression('a.DPETransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectIOWMaster"))
                            ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT)
                            ->columns(array('value' => new Expression("a.RefSerialNo+ ' ' +a.Specification"),'data' => 'IOWId'))
                            ->order('a.IOWId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    // Material List
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_Resource"))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId','UnitName'), $select:: JOIN_LEFT)
                        ->columns(array("data" => 'ResourceId', "value" => new Expression("a.Code + ' ' + a.ResourceName"), 'Rate'))
                        ->where("a.DeleteFlag=0 AND a.TypeId=2");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_DPETrans'))
                        ->join(array('b' => 'WPM_DPERegister'), 'a.DPERegisterId=b.DPERegisterId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('Code' =>'RefSerialNo','Specification'), $select:: JOIN_LEFT)
                        ->join(array('d' => 'Proj_ProjectIOW'), 'c.ProjectIOWId=d.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->columns(array('DPETransId','IOWId', 'ResourceId','Qty'))
                        ->where("b.CostCentreId=".$billregister['CostCentreId']." AND a.IOWId != 0");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arr_iow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // Agreement List IOW
                    $select = $sql->select();
                    $select->from(array('a' => "WPM_WorkBillTrans"))
                        ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.IOWId=c.ProjectIOWId', array('value' => new Expression("c.RefSerialNo + ' ' + c.Specification")), $select:: JOIN_LEFT)
                        ->join(array('f' => 'WPM_WorkBillWBSTrans'), 'a.WorkBillTransId=f.WorkBillTransId', array('TransWBSId' => 'WBSId'), $select:: JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                        ->columns(array('data' => 'IOWId','IOWId','Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)')))
                        ->where("a.IOWId <> 0 AND b.DeleteFlag=0 AND b.CostCentreId=".$billregister['CostCentreId']. ' AND b.WORegisterId='.$billregister['WORegisterId'] . ' AND b.VendorId='.$billregister['VendorId']. " AND a.RateType='F'")
                        ->group(new Expression("a.IOWId,a.Rate,e.UnitId,e.UnitName,c.RefSerialNo,c.Specification,f.WBSId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $recert_agreement = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($recert_agreement) > 0)
                        $this->_view->recert_agreement = $recert_agreement;
                    else {
                        // Agreement List Resource
                        $select = $sql->select();
                        $select->from(array('a' => "WPM_WorkBillTrans"))
                            ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'WPM_WorkBillIOWTrans'), 'a.WorkBillTransId=c.WorkBillTransId', array('TransIOWId' => 'IOWId'), $select:: JOIN_LEFT)
                            ->join(array('d' => 'Proj_Resource'), 'a.ResourceId=d.ResourceId', array('value' => new Expression("d.Code +' '+ d.ResourceName")), $select:: JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'), 'd.UnitId=e.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('Rate', 'CurQty' => new Expression('SUM(a.CurQty)'),'CurAmount' => new Expression('SUM(a.CurAmount)'),'data' => 'ResourceId','ResourceId'))
                            ->where("a.RateType='F' AND a.ResourceId <> 0 AND b.DeleteFlag=0 AND b.CostCentreId=". $billregister['CostCentreId'] . ' AND b.WORegisterId=' . $billregister['WORegisterId'] . ' AND b.VendorId=' . $billregister['VendorId'])
                            ->group(new Expression("a.ResourceId,a.Rate,e.UnitId,e.UnitName,d.Code,d.ResourceName,c.IOWId"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $recert_agreement = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if (count($recert_agreement) > 0)
                            $this->_view->recert_agreement = $recert_agreement;
                    }

                    $this->_view->BillRegisterId = $editId;
                }
            }

            $aVNo = CommonHelper::getVoucherNo(407, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->woNo = $aVNo["voucherNo"];
            else
                $this->_view->woNo = "";

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function deleteAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $connection->beginTransaction();
                    $BillRegisterId = $this->bsf->isNullCheck($this->params()->fromPost('BillRegisterId'), 'number');
                    $response = $this->getResponse();

                    $update = $sql->update();
                    $update->table('WPM_WorkBillRegister')
                        ->set(array('DeleteFlag' => '1'))
                        ->where(array('BillRegisterId' => $BillRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $response->setStatusCode('200');
                    $response->setContent('Success');
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('201');
                    $response->setContent('No data!');
                }

                return $response;
            }
        }
    }

    public function reportAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Bill Abstract");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $connection->beginTransaction();
                    $costcentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                    $FDate = trim($this->bsf->isNullCheck($this->params()->fromPost('fromDate'), 'string'));
                    $TDate = trim($this->bsf->isNullCheck($this->params()->fromPost('toDate'), 'string'));

                    $whereCond = "b.DeleteFlag=0 AND b.CostCentreID=$costcentreId";
                    if ($FDate != '')
                        $whereCond .= " AND b.FDate >='" . date('Y-m-d', strtotime($FDate)) . "'";

                    if ($TDate != '')
                        $whereCond .= " AND b.TDate <='" . date('Y-m-d', strtotime($TDate)) . "'";


                    $select = $sql->select(array('a' => 'WPM_BillFormatTrans'));
                    $select->join(array('b' => 'WPM_BillFormatMaster'), 'a.BillFormatId=b.BillFormatId', array(), $select::JOIN_LEFT)
                        ->columns(array('Description' => new Expression("CASE WHEN (a.Description != '') THEN a.Description ELSE b.TypeName END"), 'BillFormatTransId'))
                        ->where("a.CostCentreID=$costcentreId AND a.BillFormatId <> 0")
                        ->order('a.SortId');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $billformats = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select(array('a' => 'WPM_WorkBillFormatTrans'));
                    $select->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=b.BillRegisterId', array('RefNo', 'RefDate' => new Expression("FORMAT(b.RefDate,'dd-MM-yyyy')")), $select::JOIN_LEFT)
                        ->join(array('c' => 'WPM_BillFormatTrans'), 'a.BillFormatTransId=c.BillFormatTransId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_BillFormatMaster'), 'c.BillFormatId=d.BillFormatId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Vendor_Master'), 'b.VendorId=e.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->columns(array('Amount', 'BillFormatTransId', 'BillRegisterId', 'Description' => new Expression("CASE WHEN (c.Description != '') THEN c.Description ELSE d.TypeName END")))
                        ->where($whereCond)
                        ->where("c.BillFormatId <> 0")
                        ->order("a.BillRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $response->setContent(json_encode(array('formats' => $billformats, 'rows' => $data)));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400')
                        ->setContent('Error.');
                }

                return $response;
            }
        } else {
            $costcentreId = $this->bsf->isNullCheck($this->params()->fromRoute('costcentreId'), 'number');

            // get cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->CostCentreId = $costcentreId;


            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function payableReportAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Bill Abstract");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $connection->beginTransaction();
                    $costcentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                    $FDate = trim($this->bsf->isNullCheck($this->params()->fromPost('fromDate'), 'string'));
                    $TDate = trim($this->bsf->isNullCheck($this->params()->fromPost('toDate'), 'string'));

                    $whereCond  = "a.DeleteFlag=0 AND a.CostCentreID=$costcentreId";
                    if($FDate != '')
                        $whereCond .= " AND a.FDate >='". date('Y-m-d', strtotime($FDate))."'";

                    if($TDate!= '')
                        $whereCond .= " AND a.TDate <='". date('Y-m-d', strtotime($TDate))."'";

                    $select = $sql->select(array('a' => 'WPM_WorkBillRegister'));
                    $select->join(array('b' => 'Vendor_Master'), 'a.VendorId=b.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('c' => 'WF_OperationalCostCentre'), 'a.CostCentreId=c.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->columns(array('BillAmount', 'BillRegisterId','BillType' => new Expression('UPPER(a.BillType)'), 'BillNo',
                            'BillDate' => new Expression("FORMAT(a.BillDate,'dd-MM-yyyy')"), 'Paid', 'Payable' => new Expression("a.BillAmount-a.Paid")))
                        ->where($whereCond);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $response->setContent(json_encode($data));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400')
                        ->setContent('Error.');
                }

                return $response;
            }
        } else {
            $costcentreId = $this->bsf->isNullCheck($this->params()->fromRoute('costcentreId'), 'number');

            // get cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->CostCentreId = $costcentreId;


            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function retentionpayableReportAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Bill Abstract");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $sql = new Sql($dbAdapter);
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $connection->beginTransaction();
                    $costcentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                    $FDate = trim($this->bsf->isNullCheck($this->params()->fromPost('fromDate'), 'string'));
                    $TDate = trim($this->bsf->isNullCheck($this->params()->fromPost('toDate'), 'string'));

                    $whereCond  = "c.DeleteFlag=0 AND b.BillFormatId=15 AND c.CostCentreID=$costcentreId";
                    if($FDate != '')
                        $whereCond .= " AND c.FDate >='". date('Y-m-d', strtotime($FDate))."'";

                    if($TDate!= '')
                        $whereCond .= " AND c.TDate <='". date('Y-m-d', strtotime($TDate))."'";

                    $select = $sql->select(array('a' => 'WPM_WorkBillFormatTrans'));
                    $select->join(array('b' => 'WPM_BillFormatTrans'), 'a.BillFormatTransId=b.BillFormatTransId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'WPM_WorkBillRegister'), 'a.BillRegisterId=c.BillRegisterId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'WPM_WORegister'), 'c.WORegisterId=d.WORegisterId', array('WONo', 'WODate' => new Expression("Format(d.WODate, 'dd-MM-yyyy')")), $select::JOIN_LEFT)
                        ->join(array('e' => 'Vendor_Master'), 'd.VendorId=e.VendorId', array('VendorName'), $select::JOIN_LEFT)
                        ->join(array('f' => 'WF_OperationalCostCentre'), 'd.CostCentreId=f.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
                        ->join(array('g' => 'WPM_RetentionReleaseBillTrans'), 'a.BillRegisterId=g.BillId', array('PaidAmount' => new Expression("SUM(g.CurrentAmount)")), $select::JOIN_LEFT)
                        ->columns(array('RecoveryAmount' => new Expression("SUM(a.Amount)"), 'Payable' => new Expression("SUM(a.Amount) - SUM(g.CurrentAmount)")))
                        ->where($whereCond)
                        ->group(new Expression("c.WORegisterId, d.WONo, d.WODate, e.VendorName, f.CostCentreName"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $data = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $response->setContent(json_encode($data));
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode('400')
                        ->setContent('Error.');
                }

                return $response;
            }
        } else {
            $costcentreId = $this->bsf->isNullCheck($this->params()->fromRoute('costcentreId'), 'number');

            // get cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->CostCentreId = $costcentreId;


            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function WorkBillReportAction(){
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

        $dir = 'public/reports/workbill/'. $subscriberId;
        $filePath = $dir.'/v5_template.phtml';
        $filePath1 = $dir.'/f5_template.phtml';
        $billRegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($billRegisterId == 0)
            $this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );

        if (!file_exists($filePath)) {
            $filePath = 'public/reports/workbill/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/reports/workbill/template1.phtml';
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
        $select->from(array('a' => 'WPM_WorkBillRegister'))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId ', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $select::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "b.CompanyId=h.CompanyId", array("LogoPath","CompanyName"), $select::JOIN_LEFT)
            ->columns(array("BillRegisterId", "WOBilled","BillNo","CCBVNo","BillType","RefNo",
                "BillDate" => new Expression("FORMAT(a.BillDate, 'dd-MM-yyyy')"),
                "ContractorName" => new Expression("VendorName"),
                "FromDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"),"ToDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
            ->join(array('d' => 'WPM_WORegister'), 'a.WORegisterId = d.WORegisterId', array('WONo','WOType'), $select::JOIN_LEFT)
            ->where("a.BillRegisterId=$billRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $billRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->billRegister=$billRegister;
        //echo '<pre>'; print_r($this->_view->woregister);die;
        //boq
        //echo $woAmtinwords = $this->convertAmountToWords($woregister['Amount']);die;
        //$this->_view->woAmtinwords = $woAmtinwords;

        $select = $sql->select();
        $select->from(array('a' => "wpm_workbilltrans"))
            ->join(array('c' => 'proj_resource'), 'a.ResourceId=c.ResourceId', array('Code','ResourceName'), $select:: JOIN_LEFT)
            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('RefSerialNo','Specification'), $select:: JOIN_LEFT)
            ->columns(array('CurAmount','DPETransId','CurQty','Rate'))
            ->where("a.BillRegisterId=$billRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }

    public function HireBillReportAction(){
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

        $dir = 'public/reports/hirebill/'. $subscriberId;
        $filePath = $dir.'/v6_template.phtml';
        $filePath1 = $dir.'/f6_template.phtml';
        $hbRegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($hbRegisterId == 0)
            $this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );

        if (!file_exists($filePath)) {
            $filePath = 'public/reports/hirebill/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/reports/hirebill/template1.phtml';
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
        $select->from(array('a' => 'WPM_HBRegister'))
            ->columns(array("HBRegisterId", "HBNo","HBCCNo","RefNo",
                "HBDate" => new Expression("FORMAT(a.HBDate, 'dd-MM-yyyy')"),
                "ContractorName" => new Expression("VendorName"),
                "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')"),
                "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array(), $select::JOIN_LEFT)
            ->join(array('e' => 'WPM_HORegister'), 'a.HORegisterId = e.HORegisterId', array('HONo'), $select::JOIN_LEFT)
            ->join(array('g' => 'WPM_HireTypeMaster'), 'e.HireTypeId = g.HireTypeId', array('HireTypeName'), $select::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "b.CompanyId=h.CompanyId", array("LogoPath","CompanyName"), $select::JOIN_LEFT)
            ->where("a.HBRegisterId=$hbRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $hbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->hbRegister=$hbRegister;
//            echo '<pre>'; print_r($this->_view->woregister);die;
        // boq
//            echo $woAmtinwords = $this->convertAmountToWords($woregister['Amount']);die;
//            $this->_view->woAmtinwords = $woAmtinwords;

        $select = $sql->select();
        $select->from(array('a' => "WPM_hBtypetrans"))
            ->join(array('c' => 'proj_resource'), 'a.ResourceId=c.ResourceId', array('Code','ResourceName'), $select:: JOIN_LEFT)
            ->columns(array('Amount','HBTypeTransId','Qty','WorkingQty','TotalQty','Rate'))
            ->where("a.HBRegisterId=$hbRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }

    public function ServiceBillReportAction()
    {
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

        $dir = 'public/reports/servicebill/'. $subscriberId;
        $filePath = $dir.'/v7_template.phtml';
        $filePath1 = $dir.'/f7_template.phtml';
        $sbRegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($sbRegisterId == 0)
            $this->redirect()->toRoute( 'cb/workorder', array( 'controller' => 'workorder', 'action' => 'register' ) );

        if (!file_exists($filePath)) {
            $filePath = 'public/reports/servicebill/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/reports/servicebill/template1.phtml';
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
        $select->from(array('a' => 'WPM_SBRegister'))
            ->columns(array("SBRegisterId", "SBNo","SBCCNo","RefNo", "SBDate" => new Expression("FORMAT(a.SBDate, 'dd-MM-yyyy')")
            , "FromDate" => new Expression("FORMAT(a.FromDate, 'dd-MM-yyyy')")
            , "ContractorName" => new Expression("c.VendorName")
            , "ToDate" => new Expression("FORMAT(a.ToDate, 'dd-MM-yyyy')"),'NetAmount'))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId = c.VendorId', array('RegAddress','CompanyMailid','PANNo'), $select::JOIN_LEFT)
            ->join(array('d' => 'WPM_SDRegister'), 'a.SDRegisterId = d.SDRegisterId', array('SDNo'), $select::JOIN_LEFT)
            ->join(array('e' => 'WPM_SORegister'), 'a.SORegisterId = e.SORegisterId', array('SONo'), $select::JOIN_LEFT)
            ->join(array('f' => 'Proj_ServiceTypeMaster'), 'e.ServiceTypeId = f.ServiceTypeId', array('ServiceTypeName'), $select::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "b.CompanyId=h.CompanyId", array("LogoPath","CompanyName",'Address','Phone','Email'), $select::JOIN_LEFT)
            ->join(array("k"=>"WF_CostCentre"), "b.CostCentreId=k.CostCentreId", array("Mobile","ContactPerson",'cAddress'=>'Address'), $select::JOIN_LEFT)
            ->where("a.SBRegisterId=$sbRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $sbRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->sbRegister=$sbRegister;
//            echo '<pre>'; print_r($this->_view->woregister);die;
        // boq
        $sbAmtinwords = $this->convertAmountToWords($sbRegister['NetAmount']);
        $this->_view->sbAmtinwords = $sbAmtinwords;

        $select = $sql->select();
        $select->from(array("a" => "WPM_SBQualTrans"))
            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
            ->columns(array('QualifierId','tots'=>new Expression("ltrim(str(a.NetPer)) + '%'"),'NetAmt','Sign'));
        $select->where(array("a.MixType ='S' and  a.SBRegisterId =$sbRegisterId and A.NetAmt <>0"));
        $select->order('a.SortId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->qualLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a" => "WPM_sbrecoverytrans"))
            ->join(array("b" => "WPM_ServiceRecoveryType"), "a.RecoveryTypeId=b.RecoveryTypeId", array('RecoveryTypeId','RecoveryTypeName'), $select::JOIN_INNER)
            ->columns(array('Amount','Sign'));
        $select->where(array("a.SBRegisterId =$sbRegisterId"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->recoveryLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array('a' => "WPM_SBServicetrans"))
            ->join(array('b' => 'Proj_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('ServiceName','ServiceCode'), $select:: JOIN_LEFT)
            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array('UnitName'), $select:: JOIN_LEFT)
            ->columns(array('Amount','SBServiceTransId','Qty','Rate'))
            ->where("a.SBRegisterId=$sbRegisterId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->woboq = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }


    public function getWorkOrderAction()
    {
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array("a" => "WPM_WORegister"))
                    ->columns(array('value' => 'WONo', 'data' => 'WORegisterId'))
                    ->where(array('a.CostCentreId' => $postData['ccId'], 'a.VendorId' => $postData['vId'], 'DeleteFlag' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getAccountAction()
    {
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $aTypeId = $postData['aTypeId'];

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('FA_AccountMaster')
                    ->columns(array('data' => new Expression("AccountId"), 'value' => new Expression("AccountName")))
                    ->where(array("TypeId=$aTypeId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
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
}