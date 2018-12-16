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

class WpmdashboardController extends AbstractActionController
{
	public function __construct()	{
		$this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}

	public function wpmdashboardAction(){
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
        $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');

        if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here
                $postData = $request->getPost();
                $type=$this->bsf->isNullCheck($postData['type'], 'string');

                if($type == 'ls') {
                    $date = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['lsDate'], 'string')));

                    $lsLabourSelect = $sql->select();
                    $lsLabourSelect->from(array('A' => "WPM_LSLabourTrans"))
                        ->join(array('B' => 'WPM_LabourStrengthRegister'), 'A.LSRegisterId=B.LSRegisterId', array('CostCentreId'), $lsLabourSelect:: JOIN_INNER)
                        ->columns(array('Qty'))
                        ->where(array("LSDate='$date'"));
                    $lsVendorSelect = $sql->select();
                    $lsVendorSelect->from(array('A' => "WPM_LSVendorTrans"))
                        ->join(array('B' => 'WPM_LabourStrengthRegister'), 'A.LSRegisterId=B.LSRegisterId', array('CostCentreId'), $lsVendorSelect:: JOIN_INNER)
                        ->columns(array('Qty'))
                        ->where(array("LSDate='$date'"));
                    $lsVendorSelect->combine($lsLabourSelect, 'Union ALL');
                    $TotSelect = $sql->select();
                    $TotSelect->from(array('G' => $lsVendorSelect))
                        ->columns(array('Qty' => new Expression("sum(G.Qty)")))
                        ->join(array('c' => 'WF_OperationalCostCentre'), ' G.CostCentreId=C.CostCentreId', array('CostCentreId', 'CostCentreName'), $lsVendorSelect:: JOIN_INNER)
                        ->group(array("C.CostCentreId", "C.CostCentreName"));
                    $statement = $sql->getSqlStringForSqlObject($TotSelect);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }else if($type == 'dpe'){
                    $dpeDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['dpeDate'], 'string')));

                    $dpeSelect = $sql->select();
                    $dpeSelect->from(array('A' => 'WPM_DPETrans'))
                        ->columns(array('Amount'=>new Expression("sum(A.Amount)")))
                        ->join(array('B' => 'WPM_DPERegister'), 'A.DPERegisterId=B.DPERegisterId', array(), $dpeSelect:: JOIN_INNER)
                        ->join(array('C' => 'WF_OperationalCostCentre'), ' B.CostCentreId=C.CostCentreId', array('CostCentreId','CostCentreName'), $dpeSelect:: JOIN_INNER)
                        ->where(array("B.DPEDate='$dpeDate'"))
                        ->group(array("C.CostCentreName","C.CostCentreId"));
                    $statement = $sql->getSqlStringForSqlObject($dpeSelect);
                    $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

				$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent(json_encode($result));
                return $response;
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Normal form post code here
				
			}


            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'))
                ->where('DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->projectId = $iProjectId;

            //order by value
            $WOSelect = $sql->select();
            $WOSelect->from(array('A' => "WPM_WORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $WOSelect:: JOIN_INNER)
                ->join(array('C' => 'Vendor_Master'), 'A.VendorId=C.VendorId', array('VendorName'), $WOSelect:: JOIN_INNER)
                ->columns(array('OrderDate'=>'WODate','OrderNo'=>'WONo','NetAmount','OrderType'=>new Expression("'WorkOrder'")));

            $HOSelect = $sql->select();
            $HOSelect->from(array('A' => "WPM_HORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $HOSelect:: JOIN_INNER)
                ->join(array('C' => 'Vendor_Master'), 'A.VendorId=C.VendorId', array('VendorName'), $HOSelect:: JOIN_INNER)
                ->columns(array('OrderDate'=>'HODate','OrderNo'=>'HONo','NetAmount','OrderType'=>new Expression("'HireOrder'")));
            $HOSelect->combine($WOSelect, 'Union ALL');

            $SOSelect = $sql->select();
            $SOSelect->from(array('A' => "WPM_SORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $SOSelect:: JOIN_INNER)
                ->join(array('C' => 'Vendor_Master'), 'A.VendorId=C.VendorId', array('VendorName'), $SOSelect:: JOIN_INNER)
                ->columns(array('OrderDate'=>'SODate','OrderNo'=>'SONo','NetAmount','OrderType'=>new Expression("'ServiceOrder'")));
            $SOSelect->combine($HOSelect, 'Union ALL');

            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $SOSelect))
                ->columns(array('OrderDate'=>new Expression("Top 5 (Convert(Varchar(10),G.OrderDate,103))"),'OrderNo','NetAmount','OrderType','CostCentreName','VendorName','CostCentreId'))
                ->order("G.NetAmount DESC");
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_topOrders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //work graph
            $WOSelect = $sql->select();
            $WOSelect->from(array('A' => "WPM_WORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $WOSelect:: JOIN_INNER)
                ->columns(array('NetAmount'));
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $WOSelect))
                ->columns(array('Amount'=>new Expression("sum(G.NetAmount)"),'CostCentreName','CostCentreId'))
                ->group(array("G.CostCentreId","G.CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_workGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //service Graph
            $SOSelect = $sql->select();
            $SOSelect->from(array('A' => "WPM_SORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $SOSelect:: JOIN_INNER)
                ->columns(array('NetAmount'));
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $SOSelect))
                ->columns(array('Amount'=>new Expression("sum(G.NetAmount)"),'CostCentreName','CostCentreId'))
                ->group(array("G.CostCentreId","G.CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_serviceGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //hire Graph
            $HOSelect = $sql->select();
            $HOSelect->from(array('A' => "WPM_HORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $HOSelect:: JOIN_INNER)
                ->columns(array('NetAmount'));
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $HOSelect))
                ->columns(array('Amount'=>new Expression("sum(G.NetAmount)"),'CostCentreName','CostCentreId'))
                ->group(array("G.CostCentreId","G.CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_hireGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Labour Strength Graph
            $date = date('Y-m-d', strtotime(Date('d-m-Y')));
            $this->_view->date=$date;

            $lsLabourSelect = $sql->select();
            $lsLabourSelect->from(array('A' => "WPM_LSLabourTrans"))
                ->join(array('B' => 'WPM_LabourStrengthRegister'), 'A.LSRegisterId=B.LSRegisterId', array('CostCentreId'), $lsLabourSelect:: JOIN_INNER)
                ->columns(array('Qty'))
                ->where(array("LSDate='$date'"));
            $lsVendorSelect = $sql->select();
            $lsVendorSelect->from(array('A' => "WPM_LSVendorTrans"))
                ->join(array('B' => 'WPM_LabourStrengthRegister'), 'A.LSRegisterId=B.LSRegisterId', array('CostCentreId'), $lsVendorSelect:: JOIN_INNER)
                ->columns(array('Qty'))
                ->where(array("LSDate='$date'"));
            $lsVendorSelect->combine($lsLabourSelect, 'Union ALL');
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $lsVendorSelect))
                ->columns(array('Qty'=>new Expression("sum(G.Qty)")))
                ->join(array('c' => 'WF_OperationalCostCentre'), ' G.CostCentreId=C.CostCentreId', array('CostCentreId','CostCentreName'), $lsVendorSelect:: JOIN_INNER)
                ->group(array("C.CostCentreId","C.CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_lsGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //dpe graph
            $dpeSelect = $sql->select();
            $dpeSelect->from(array('A' => 'WPM_DPETrans'))
                ->columns(array('Amount'=>new Expression("sum(A.Amount)")))
                ->join(array('B' => 'WPM_DPERegister'), 'A.DPERegisterId=B.DPERegisterId', array(), $dpeSelect:: JOIN_INNER)
                ->join(array('C' => 'WF_OperationalCostCentre'), ' B.CostCentreId=C.CostCentreId', array('CostCentreId','CostCentreName'), $dpeSelect:: JOIN_INNER)
                ->where(array("B.DPEDate='$date'"))
                ->group(array("C.CostCentreName","C.CostCentreId"));
            $statement = $sql->getSqlStringForSqlObject($dpeSelect);
            $this->_view->arr_dpeGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //order by value
                $WOSelect = $sql->select();
                $WOSelect->from(array('A' => "WPM_WORegister"))
                    ->columns(array('NetAmount'));

                $HOSelect = $sql->select();
                $HOSelect->from(array('A' => "WPM_HORegister"))
                    ->columns(array('NetAmount'));
                $HOSelect->combine($WOSelect, 'Union ALL');

                $SOSelect = $sql->select();
                $SOSelect->from(array('A' => "WPM_SORegister"))
                    ->columns(array('NetAmount'));
                $SOSelect->combine($HOSelect, 'Union ALL');

                $TotSelect = $sql->select();
                $TotSelect->from(array('G' => $SOSelect))
                    ->columns(array('Amount'=>new Expression("sum(G.NetAmount)")));
                $statement = $sql->getSqlStringForSqlObject($TotSelect);
                $this->_view->orderValues = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //Bill Value
                $WBSelect = $sql->select();
                $WBSelect->from(array('A' => "WPM_WorkBillRegister"))
                    ->columns(array('NetAmount'=>'BillAmount','PaidAmount','PayableAmount'=>new Expression("A.BillAmount-A.PaidAmount")));

                $HBSelect = $sql->select();
                $HBSelect->from(array('A' => "WPM_HBRegister"))
                    ->columns(array('NetAmount','PaidAmount','PayableAmount'=>new Expression("A.NetAmount-A.PaidAmount")));
                $HBSelect->combine($WBSelect, 'Union ALL');

                $SBSelect = $sql->select();
                $SBSelect->from(array('A' => "WPM_SBRegister"))
                    ->columns(array('NetAmount','PaidAmount','PayableAmount'=>new Expression("A.NetAmount-A.PaidAmount")));
                $SBSelect->combine($HBSelect, 'Union ALL');

                $TotSelect = $sql->select();
                $TotSelect->from(array('G' => $SBSelect))
                    ->columns(array('Amount' => new Expression("sum(G.NetAmount)"),'PayableAmount' => new Expression("sum(G.PayableAmount)"),
                        'PaidAmount' => new Expression("sum(G.PaidAmount)")));
                $statement = $sql->getSqlStringForSqlObject($TotSelect);
                $this->_view->BillValues = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //adv Paid
                $advSelect = $sql->select();
                $advSelect->from(array('G' => 'WPM_advanceRegister'))
                    ->columns(array('Amount' => new Expression("sum(G.PaidAmount)")));
                $statement = $sql->getSqlStringForSqlObject($advSelect);
                $this->_view->advPaid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //adv Deduct
                $select1 = $sql->select();
                $select1->from(array('a' => 'WPM_WorkBillFormatTrans'))
                    ->columns(array(
                        'RecoveryAmount' => new Expression("isnull(Sum(a.Amount),0)"),
                    ))
                    ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array(), $select1::JOIN_INNER)
                    ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select1::JOIN_INNER)
                    ->where(array("C.Sign='-' and B.Approve='Y' and B.BillCertify=0 and C.FormatTypeId=9 "));
                $statement = $sql->getSqlStringForSqlObject($select1);
                $this->_view->advDeduct = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->advReceivable= $this->_view->advPaid['Amount']-$this->_view->advDeduct['RecoveryAmount'];

                $select1 = $sql->select();
                $select1->from(array('a' => 'WPM_WorkBillFormatTrans'))
                    ->columns(array(
                        'RecoveryAmount' => new Expression("isnull(Sum(a.Amount),0)"),
                    ))
                    ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array(), $select1::JOIN_INNER)
                    ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select1::JOIN_INNER)
                    ->where(array("C.Sign='-' and B.Approve='Y' and B.BillCertify=0 and C.FormatTypeId=10 "));
                $statement = $sql->getSqlStringForSqlObject($select1);
                $this->_view->retention = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            //Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
    public function wpmActDashboardAction(){
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

        $iProjectId =  $this->bsf->isNullCheck($this->params()->fromRoute('projectid'),'number');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postData = $request->getPost();
                $costCentreId=$this->bsf->isNullCheck($postData['costCentreId'], 'number');
                $type=$this->bsf->isNullCheck($postData['type'], 'string');

                if($type == 'ls') {
                    $date = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['lsDate'], 'string')));
                    $lsLabourSelect = $sql->select();
                    $lsLabourSelect->from(array('A' => "WPM_LSLabourTrans"))
                        ->join(array('B' => 'WPM_LabourStrengthRegister'), 'A.LSRegisterId=B.LSRegisterId', array(), $lsLabourSelect:: JOIN_INNER)
                        ->join(array('C' => 'WPM_LabourMaster'), ' A.LabourId=C.LabourId', array('ResourceId'=>'LabourTypeId'), $lsLabourSelect:: JOIN_INNER)
                        ->columns(array('Qty'))
                        ->where(array("B.LSDate='$date' and B.CostCentreId=$costCentreId"));
                    $lsVendorSelect = $sql->select();
                    $lsVendorSelect->from(array('A' => "WPM_LSTypeTrans"))
                        ->join(array('B' => 'WPM_LSVendorTrans'), 'A.LSVendorTransId=B.LSVendorTransId', array(), $lsVendorSelect:: JOIN_INNER)
                        ->join(array('C' => 'WPM_LabourStrengthRegister'), 'B.LSRegisterId=C.LSRegisterId', array(), $lsVendorSelect:: JOIN_INNER)
                        ->columns(array('Qty','ResourceId'))
                        ->where(array("C.LSDate='$date' and C.CostCentreId=$costCentreId"));
                    $lsVendorSelect->combine($lsLabourSelect, 'Union ALL');
                    $TotSelect = $sql->select();
                    $TotSelect->from(array('G' => $lsVendorSelect))
                        ->columns(array('Qty'=>new Expression("sum(G.Qty)")))
                        ->join(array('D' => 'Proj_Resource'), ' G.ResourceId=D.ResourceId', array('ResourceName'), $lsVendorSelect:: JOIN_INNER)
                        ->group(array("D.ResourceName"));
                    $statement = $sql->getSqlStringForSqlObject($TotSelect);
                    $result= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }else if($type == 'dpe'){
                    $dpeDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['dpeDate'], 'string')));
                    $lsLabourSelect = $sql->select();
                    $lsLabourSelect->from(array('A' => "WPM_DPETrans"))
                        ->join(array('B' => 'WPM_DPERegister'), 'A.DPERegisterId=B.DPERegisterId', array(), $lsLabourSelect:: JOIN_INNER)
                        ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array('WorkGroupId'), $lsLabourSelect:: JOIN_INNER)
                        ->columns(array('Amount'))
                        ->where(array("B.DPEDate='$dpeDate' and B.CostCentreId=$costCentreId and C.IOWId<>0"));
                    $lsVendorSelect = $sql->select();
                    $lsVendorSelect->from(array('A' => "WPM_DPEIOWTrans"))
                        ->join(array('B' => 'WPM_DPETrans'), 'A.DPETransId=B.DPETransId', array(), $lsVendorSelect:: JOIN_INNER)
                        ->join(array('C' => 'WPM_DPERegister'), 'B.DPERegisterId=C.DPERegisterId', array(), $lsVendorSelect:: JOIN_INNER)
                        ->join(array('D' => 'Proj_ProjectIOWMaster'), 'A.IOWId=D.ProjectIOWId', array('WorkGroupId'), $lsVendorSelect:: JOIN_INNER)
                        ->columns(array('Amount'=>new Expression("A.Qty*B.Rate")))
                        ->where(array("C.DPEDate='$dpeDate' and C.CostCentreId=$costCentreId and B.IOWId=0"));
                    $lsVendorSelect->combine($lsLabourSelect, 'Union ALL');
                    $TotSelect = $sql->select();
                    $TotSelect->from(array('G' => $lsVendorSelect))
                        ->columns(array('Amount'=>new Expression("sum(G.Amount)")))
                        ->join(array('E' => 'Proj_ProjectWorkGroup'), 'G.WorkGroupId=E.WorkGroupId', array('WorkGroupName'), $lsVendorSelect:: JOIN_INNER)
                        ->group(array("E.WorkGroupName"));
                    $statement = $sql->getSqlStringForSqlObject($TotSelect);
                    $this->_view->arr_dpeGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            if ($iProjectId == 0)
                $this->redirect()->toRoute('wpm/default', array('controller' => 'wpmdashboard', 'action' => 'wpmdashboard'));

            $date = date('Y-m-d', strtotime(Date('d-m-Y')));
            $this->_view->date=$date;

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'))
                ->where('DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->projectId = $iProjectId;

            //order by value
            $WOSelect = $sql->select();
            $WOSelect->from(array('A' => "WPM_WORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $WOSelect:: JOIN_INNER)
                ->join(array('C' => 'Vendor_Master'), 'A.VendorId=C.VendorId', array('VendorName'), $WOSelect:: JOIN_INNER)
                ->columns(array('OrderDate'=>'WODate','OrderNo'=>'WONo','NetAmount','OrderType'=>new Expression("'WorkOrder'")));

            $HOSelect = $sql->select();
            $HOSelect->from(array('A' => "WPM_HORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $HOSelect:: JOIN_INNER)
                ->join(array('C' => 'Vendor_Master'), 'A.VendorId=C.VendorId', array('VendorName'), $HOSelect:: JOIN_INNER)
                ->columns(array('OrderDate'=>'HODate','OrderNo'=>'HONo','NetAmount','OrderType'=>new Expression("'HireOrder'")));
            $HOSelect->combine($WOSelect, 'Union ALL');

            $SOSelect = $sql->select();
            $SOSelect->from(array('A' => "WPM_SORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $SOSelect:: JOIN_INNER)
                ->join(array('C' => 'Vendor_Master'), 'A.VendorId=C.VendorId', array('VendorName'), $SOSelect:: JOIN_INNER)
                ->columns(array('OrderDate'=>'SODate','OrderNo'=>'SONo','NetAmount','OrderType'=>new Expression("'ServiceOrder'")));
            $SOSelect->combine($HOSelect, 'Union ALL');

            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $SOSelect))
                ->columns(array('OrderDate'=>new Expression("Top 5 (Convert(Varchar(10),G.OrderDate,103))"),'OrderNo','NetAmount','OrderType','CostCentreName','VendorName','CostCentreId'))
                ->where(array("CostCentreId=$iProjectId"))
                ->order("G.NetAmount DESC");
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->topOrdersWithProjects= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //work graph
            $WOSelect = $sql->select();
            $WOSelect->from(array('A' => "WPM_WORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $WOSelect:: JOIN_INNER)
                ->columns(array('NetAmount'));
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $WOSelect))
                ->columns(array('Amount'=>new Expression("sum(G.NetAmount)"),'CostCentreName','CostCentreId'))
                ->where(array("CostCentreId=$iProjectId"))
                ->group(array("G.CostCentreId","G.CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_workGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //service Graph
            $SOSelect = $sql->select();
            $SOSelect->from(array('A' => "WPM_SORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $SOSelect:: JOIN_INNER)
                ->columns(array('NetAmount'));
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $SOSelect))
                ->columns(array('Amount'=>new Expression("sum(G.NetAmount)"),'CostCentreName','CostCentreId'))
                ->where(array("CostCentreId=$iProjectId"))
                ->group(array("G.CostCentreId","G.CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_serviceGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //hire Graph
            $HOSelect = $sql->select();
            $HOSelect->from(array('A' => "WPM_HORegister"))
                ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreName','CostCentreId'), $HOSelect:: JOIN_INNER)
                ->columns(array('NetAmount'));
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $HOSelect))
                ->columns(array('Amount'=>new Expression("sum(G.NetAmount)"),'CostCentreName','CostCentreId'))
                ->where(array("CostCentreId=$iProjectId"))
                ->group(array("G.CostCentreId","G.CostCentreName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_hireGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //ls Graph
            $lsLabourSelect = $sql->select();
            $lsLabourSelect->from(array('A' => "WPM_LSLabourTrans"))
                ->join(array('B' => 'WPM_LabourStrengthRegister'), 'A.LSRegisterId=B.LSRegisterId', array(), $lsLabourSelect:: JOIN_INNER)
                ->join(array('C' => 'WPM_LabourMaster'), ' A.LabourId=C.LabourId', array('ResourceId'=>'LabourTypeId'), $lsLabourSelect:: JOIN_INNER)
                ->columns(array('Qty'))
                ->where(array("B.LSDate='$date' and B.CostCentreId=$iProjectId"));
            $lsVendorSelect = $sql->select();
            $lsVendorSelect->from(array('A' => "WPM_LSTypeTrans"))
                ->join(array('B' => 'WPM_LSVendorTrans'), 'A.LSVendorTransId=B.LSVendorTransId', array(), $lsVendorSelect:: JOIN_INNER)
                ->join(array('C' => 'WPM_LabourStrengthRegister'), 'B.LSRegisterId=C.LSRegisterId', array(), $lsVendorSelect:: JOIN_INNER)
                ->columns(array('Qty','ResourceId'))
                ->where(array("C.LSDate='$date' and C.CostCentreId=$iProjectId"));
            $lsVendorSelect->combine($lsLabourSelect, 'Union ALL');
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $lsVendorSelect))
                ->columns(array('Qty'=>new Expression("sum(G.Qty)")))
                ->join(array('D' => 'Proj_Resource'), ' G.ResourceId=D.ResourceId', array('ResourceName'), $lsVendorSelect:: JOIN_INNER)
                ->group(array("D.ResourceName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_lsGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //dpe Graph
            $lsLabourSelect = $sql->select();
            $lsLabourSelect->from(array('A' => "WPM_DPETrans"))
                ->join(array('B' => 'WPM_DPERegister'), 'A.DPERegisterId=B.DPERegisterId', array(), $lsLabourSelect:: JOIN_INNER)
                ->join(array('C' => 'Proj_ProjectIOWMaster'), 'A.IOWId=C.ProjectIOWId', array('WorkGroupId'), $lsLabourSelect:: JOIN_INNER)
                ->columns(array('Amount'))
                ->where(array("B.DPEDate='$date' and B.CostCentreId=$iProjectId and C.IOWId<>0"));
            $lsVendorSelect = $sql->select();
            $lsVendorSelect->from(array('A' => "WPM_DPEIOWTrans"))
                ->join(array('B' => 'WPM_DPETrans'), 'A.DPETransId=B.DPETransId', array(), $lsVendorSelect:: JOIN_INNER)
                ->join(array('C' => 'WPM_DPERegister'), 'B.DPERegisterId=C.DPERegisterId', array(), $lsVendorSelect:: JOIN_INNER)
                ->join(array('D' => 'Proj_ProjectIOWMaster'), 'A.IOWId=D.ProjectIOWId', array('WorkGroupId'), $lsVendorSelect:: JOIN_INNER)
                ->columns(array('Amount'=>new Expression("A.Qty*B.Rate")))
                ->where(array("C.DPEDate='$date' and C.CostCentreId=$iProjectId and B.IOWId=0"));
            $lsVendorSelect->combine($lsLabourSelect, 'Union ALL');
            $TotSelect = $sql->select();
            $TotSelect->from(array('G' => $lsVendorSelect))
                ->columns(array('Amount'=>new Expression("sum(G.Amount)")))
                ->join(array('E' => 'Proj_ProjectWorkGroup'), 'G.WorkGroupId=E.WorkGroupId', array('WorkGroupName'), $lsVendorSelect:: JOIN_INNER)
                ->group(array("E.WorkGroupName"));
            $statement = $sql->getSqlStringForSqlObject($TotSelect);
            $this->_view->arr_dpeGraph = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //order value
            if($iProjectId !=0) {
                $WOSelect = $sql->select();
                $WOSelect->from(array('A' => "WPM_WORegister"))
                    ->join(array('B' => 'WF_OperationalCostCentre'), ' A.CostCentreId=B.CostCentreId', array('CostCentreId'), $WOSelect:: JOIN_INNER)
                    ->columns(array('NetAmount'));

                $HOSelect = $sql->select();
                $HOSelect->from(array('A' => "WPM_HORegister"))
                    ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreId'), $HOSelect:: JOIN_INNER)
                    ->columns(array('NetAmount'));
                $HOSelect->combine($WOSelect, 'Union ALL');

                $SOSelect = $sql->select();
                $SOSelect->from(array('A' => "WPM_SORegister"))
                    ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreId'), $SOSelect:: JOIN_INNER)
                    ->columns(array('NetAmount'));
                $SOSelect->combine($HOSelect, 'Union ALL');

                $TotSelect = $sql->select();
                $TotSelect->from(array('G' => $SOSelect))
                    ->columns(array('Amount' => new Expression("sum(G.NetAmount)"), 'CostCentreId'))
                    ->where(array("CostCentreId=$iProjectId"))
                    ->group(array("G.CostCentreId"));
                $statement = $sql->getSqlStringForSqlObject($TotSelect);
                $this->_view->orderValues = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //Bill Value
                $WBSelect = $sql->select();
                $WBSelect->from(array('A' => "WPM_WorkBillRegister"))
                    ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreId'), $WBSelect:: JOIN_INNER)
                    ->columns(array('NetAmount'=>'BillAmount','PaidAmount','PayableAmount'=>new Expression("A.BillAmount-A.PaidAmount")));

                $HBSelect = $sql->select();
                $HBSelect->from(array('A' => "WPM_HBRegister"))
                    ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreId'), $HBSelect:: JOIN_INNER)
                    ->columns(array('NetAmount','PaidAmount','PayableAmount'=>new Expression("A.NetAmount-A.PaidAmount")));
                $HBSelect->combine($WBSelect, 'Union ALL');

                $SBSelect = $sql->select();
                $SBSelect->from(array('A' => "WPM_SBRegister"))
                    ->join(array('B' => 'WF_OperationalCostCentre'), 'A.CostCentreId=B.CostCentreId', array('CostCentreId'), $SBSelect:: JOIN_INNER)
                    ->columns(array('NetAmount','PaidAmount','PayableAmount'=>new Expression("A.NetAmount-A.PaidAmount")));
                $SBSelect->combine($HBSelect, 'Union ALL');

                $TotSelect = $sql->select();
                $TotSelect->from(array('G' => $SBSelect))
                    ->columns(array('Amount' => new Expression("sum(G.NetAmount)"),'PayableAmount' => new Expression("sum(G.PayableAmount)"), 'CostCentreId','PaidAmount' => new Expression("sum(G.PaidAmount)")))
                    ->where(array("CostCentreId=$iProjectId"))
                    ->group(array("G.CostCentreId"));
                $statement = $sql->getSqlStringForSqlObject($TotSelect);
                $this->_view->BillValues = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //adv Paid
                $advSelect = $sql->select();
                $advSelect->from(array('G' => 'WPM_advanceRegister'))
                    ->columns(array('Amount' => new Expression("sum(G.PaidAmount)"), 'CostCentreId'))
                    ->where(array("CostCentreId=$iProjectId"))
                    ->group(array("G.CostCentreId"));
                $statement = $sql->getSqlStringForSqlObject($advSelect);
                $this->_view->advPaid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                //adv Deduct
                $select1 = $sql->select();
                $select1->from(array('a' => 'WPM_WorkBillFormatTrans'))
                    ->columns(array(
                        'RecoveryAmount' => new Expression("isnull(Sum(a.Amount),0)"),
                    ))
                    ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array('CostCentreId'), $select1::JOIN_INNER)
                    ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select1::JOIN_INNER)
                    ->where(array("C.Sign='-' and B.Approve='Y' and B.BillCertify=0 and C.FormatTypeId=9 and CostCentreId=$iProjectId Group by b.CostCentreId"));
                $statement = $sql->getSqlStringForSqlObject($select1);
                $this->_view->advDeduct = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->advReceivable= $this->_view->advPaid['Amount']-$this->_view->advDeduct['RecoveryAmount'];

                $select1 = $sql->select();
                $select1->from(array('a' => 'WPM_WorkBillFormatTrans'))
                    ->columns(array(
                        'RecoveryAmount' => new Expression("isnull(Sum(a.Amount),0)"),
                    ))
                    ->join(array('b' => 'WPM_WorkBillRegister'), 'a.BillRegisterId = b.BillRegisterId', array('CostCentreId'), $select1::JOIN_INNER)
                    ->join(array('c' => 'WPM_BillFormatMaster'), 'a.BillFormatId = c.BillFormatId', array(), $select1::JOIN_INNER)
                    ->where(array("C.Sign='-' and B.Approve='Y' and B.BillCertify=0 and C.FormatTypeId=9 and CostCentreId=$iProjectId Group by b.CostCentreId"));
                $statement = $sql->getSqlStringForSqlObject($select1);
                $this->_view->retention = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}