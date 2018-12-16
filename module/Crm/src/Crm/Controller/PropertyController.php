<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Crm\Controller;

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
use DOMPDF;

class PropertyController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function entryAction() {
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

        // Project/Unit/Buyer
        $select = $sql->select();
        $select->from(array("a" => "Crm_UnitDetails"))
            ->join(array('u' => 'Crm_UnitBooking'), new expression("a.UnitId=u.UnitId and u.DeleteFlag=0"), array(), $select::JOIN_INNER)
            ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadId'), $select::JOIN_INNER)
            ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
            ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
            ->where("a.UnitId not in(select UnitId from PM_PMRegister)");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $regId = $this->params()->fromRoute('regId');

        $this->_view->arrVNo = CommonHelper::getVoucherNo(809, date('Y-m-d'), 0, 0, $dbAdapter, "");

        if(isset($regId) && $regId !=""){
            $select = $sql->select();
            $select->from(array('a' => 'PM_PMRegister'))
                ->columns(array('PMRegisterId', 'PMNo','PMDate', 'UnitId', 'Address', 'IsReady','StartDate','FurnishType'))
                ->where(array("a.PMRegisterId"=>$regId));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->regInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if(isset($this->_view->regInfo) && !empty($this->_view->regInfo)){
                $select = $sql->select();
                $select->from(array('a' => 'KF_UnitMaster'))
                    ->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
                    ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
                    ->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
                    ->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
                    ->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
                    ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                    ->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
                    ->join(array('h' => 'crm_Unitbooking'), 'h.UnitId=a.UnitId', array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
                    ->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
                    ->where(array("a.UnitId"=>$this->_view->regInfo['UnitId']));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadId'), $select::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                    ->where(array("a.UnitId"=>$this->_view->regInfo['UnitId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $unitId = $this->params()->fromPost('UnitId');
                $leadId = $this->params()->fromPost('LeadId');
                //Write your Ajax post code here
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Crm_LeadAddress')
                    ->columns(array('Address1', 'PanNo'))
                    ->where(array("LeadId" => $leadId, "AddressType" => "P", "DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $perLeadDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'KF_UnitMaster'))
                    ->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
                    ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
                    ->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
                    ->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
                    ->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
                    ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                    ->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
                    ->join(array('h' => 'crm_Unitbooking'), 'h.UnitId=a.UnitId', array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
                    ->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
                    ->where(array("a.UnitId"=>$unitId));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->setTerminal(true);
                $result=array($perLeadDetails,$unitInfo);
                $response = $this->getResponse()->setContent(json_encode($result));
                //$response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                $response->setStatusCode(200);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postParams = $request->getPost();
                    $pMDate = date('Y-m-d', strtotime($postParams['refdate']));
                    $unitId = $this->bsf->isNullCheck($postParams['unitId'],'number');
                    $address = $this->bsf->isNullCheck($postParams['address'],'string');
                    $isReady =  $this->bsf->isNullCheck($postParams['readyforoccupation'],'number');
                    $startDate = date('Y-m-d', strtotime($postParams['StartDate']));
                    $furnishType = $this->bsf->isNullCheck($postParams['Furnishing'],'string');

                    $arrVNo = CommonHelper::getVoucherNo(809, date('m-d-Y',strtotime($pMDate)), 0, 0, $dbAdapter, "I");

                    if($arrVNo['genType']== true){
                        $pMNo = $arrVNo['voucherNo'];
                    } else {
                        $pMNo = $this->bsf->isNullCheck($postParams['refno'],'string');
                    }

                    //PM_PMRegister
                    if(isset($regId) && !empty($this->_view->regInfo)){
                        $update = $sql->update();
                        $update->table('PM_PMRegister');
                        $update->set(array('PMNo' => $pMNo
                        , 'PMDate' => $pMDate
                        , 'UnitId' => $unitId
                        , 'Address' => $address
                        , 'StartDate' => $startDate
                        , 'IsReady' => $isReady
                        , 'FurnishType' => $furnishType));
                        $update->where("PMRegisterId=$regId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Property-Entry-Modify','E','Property-Entry',$regId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    } else {
                        $insert = $sql->insert();
                        $insert->into('PM_PMRegister');
                        $insert->Values(array('PMNo' => $pMNo
                        , 'PMDate' => $pMDate
                        , 'UnitId' => $unitId
                        , 'Address' => $address
                        , 'StartDate' => $startDate
                        , 'IsReady' => $isReady
                        , 'FurnishType' => $furnishType));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $regId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Property-Entry-Add','N','Property-Entry',$regId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    }
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        if($regId!=""){
                            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "deposit","unitId"=>$unitId,"regId"=>$regId),array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        } else {
                            $this->redirect()->toRoute("crm/entry", array("controller" => "property","action" => "entry"),array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        }
                    } else {
                        if($regId!=""){
                            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "deposit","unitId"=>$unitId,"regId"=>$regId));
                        } else {
                            $this->redirect()->toRoute("crm/entry", array("controller" => "property","action" => "entry"));
                        }
                    }
//					if($regId!=""){
//						$this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "deposit","unitId"=>$unitId,"regId"=>$regId));
//					} else {
//						$this->redirect()->toRoute("crm/entry", array("controller" => "property","action" => "entry"));
//					}
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function depositAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $unitId = $this->params()->fromRoute('unitId');
        $regId = $this->params()->fromRoute('regId');
        $this->_view->regId = $regId;
        if(!isset($unitId) || $unitId == ""){
            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "entry"));
        }
        if(!isset($regId) || $regId == ""){
            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "entry"));
        }

        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if(isset($regId) && $regId !=""){
            $select = $sql->select();
            $select->from(array('a' => 'PM_PMFeesTrans'))
                ->where(array("a.PMRegisterId"=>$regId));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->regInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        }

        $select = $sql->select();
        $select->from(array('a' => 'KF_UnitMaster'))
            ->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
            ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
            ->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
            ->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
            ->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
            ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
            ->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
            ->join(array('h' => 'crm_Unitbooking'), 'h.UnitId=a.UnitId', array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
            ->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
            ->where(array("a.UnitId"=>$unitId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postParams = $request->getPost();
                    $availDeposite = $this->bsf->isNullCheck($postParams['availDeposite'],'number');
                    if($availDeposite == 1){
                        $depAmount = $this->bsf->isNullCheck($postParams['depAmount'],'number');
                    } else {
                        $depAmount = 0;
                    }
                    $availMainPeriod = $this->bsf->isNullCheck($postParams['availMainPeriod'],'number');
                    if($availMainPeriod == 1){
                        $mainPeriod = $this->bsf->isNullCheck($postParams['mainPeriod'],'number');
                        $mainTerms = $this->bsf->isNullCheck($postParams['main_terms'],'string');
                    } else {
                        $mainPeriod = 0;
                        $mainTerms = "";
                    }
                    $availSerPeriod = $this->bsf->isNullCheck($postParams['availSerPeriod'],'number');
                    if($availSerPeriod == 1){
                        $mainCharge = $this->bsf->isNullCheck($postParams['mainCharge'],'number');
                    } else {
                        $mainCharge = 0;
                    }
                    $availMainFee = $this->bsf->isNullCheck($postParams['availMainFee'],'number');
                    if($availMainFee == 1){
                        $MainFee = $this->bsf->isNullCheck($postParams['MainFee'],'number');
                        $dueDay = date('d', strtotime($postParams['dueDay']));
                    } else  {
                        $MainFee = 0;
                        $dueDay = 0;
                    }

                    if(isset($this->_view->regInfo) && !empty($this->_view->regInfo)){
                        $update = $sql->update();
                        $update->table('PM_PMFeesTrans');
                        $update->set(array('PMRegisterId' => $regId
                        , 'IsDeposit' => $availDeposite
                        , 'DepositAmount' => $depAmount
                        , 'IsMaintenance' => $availMainPeriod
                        , 'MaintenancePeriod' => $mainPeriod
                        , 'MaintenanceTerms' => $mainTerms
                        , 'IsAMC' => $availSerPeriod
                        , 'AMCAmount' => $mainCharge
                        , 'IsMMFees' => $availMainFee
                        , 'MMAmount' => $MainFee
                        , 'MMDueDay' => $dueDay));
                        $update->where("PMRegisterId=$regId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $connection->commit();

                    } else {
                        $insert = $sql->insert();
                        $insert->into('PM_PMFeesTrans');
                        $insert->Values(array('PMRegisterId' => $regId
                        , 'IsDeposit' => $availDeposite
                        , 'DepositAmount' => $depAmount
                        , 'IsMaintenance' => $availMainPeriod
                        , 'MaintenancePeriod' => $mainPeriod
                        , 'MaintenanceTerms' => $mainTerms
                        , 'IsAMC' => $availSerPeriod
                        , 'AMCAmount' => $mainCharge
                        , 'IsMMFees' => $availMainFee
                        , 'MMAmount' => $MainFee
                        , 'MMDueDay' => $dueDay));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $connection->commit();
                    }
                    if($regId!=""){
                        $this->redirect()->toRoute("crm/services", array("controller" => "property","action" => "services","unitId"=>$unitId,"regId"=>$regId));
                    } else {
                        $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "deposit","unitId"=>$unitId,"regId"=>$regId));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function servicesAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $unitId = $this->params()->fromRoute('unitId');
        $regId = $this->params()->fromRoute('regId');

        $this->_view->unitId = $unitId;
        $this->_view->regId = $regId;

        if(!isset($unitId) || $unitId == ""){
            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "entry"));
        }
        if(!isset($regId) || $regId == ""){
            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "entry"));
        }

        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");


        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'PM_ServiceMaster'))
            ->columns(array('value'=> new expression('ServiceName'),'data'=> new expression('ServiceId')));

        //->where(array("a.PMRegisterId"=>$regId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->services = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(isset($regId) && $regId !=""){
            $select = $sql->select();
            $select->from(array('a' => 'PM_PMCoveredServiceTrans'))
                ->join(array('b' => 'PM_ServiceMaster'), 'b.ServiceId=a.ServiceId',array('*'), $select::JOIN_LEFT)
                ->where(array("a.PMRegisterId"=>$regId));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->regInfoCovered = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'PM_PMUncoveredServiceTrans'))
                ->join(array('b' => 'PM_ServiceMaster'), 'b.ServiceId=a.ServiceId',array('*'), $select::JOIN_LEFT)
                ->where(array("a.PMRegisterId"=>$regId));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->regInfoUnCovered = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }

        $select = $sql->select();
        $select->from(array('a' => 'KF_UnitMaster'))
            ->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
            ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
            ->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
            ->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
            ->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
            ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
            ->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
            ->join(array('h' => 'crm_Unitbooking'), 'h.UnitId=a.UnitId', array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
            ->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
            ->where(array("a.UnitId"=>$unitId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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
                $postParams = $request->getPost();
                //  var_dump($postParams);die;
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $arrCover = array();
                    $arrUncover = array();
                    foreach($postParams as $key => $data) {
                        if(preg_match('/^covService[a-z]+_[\d]+$/i', $key)) {
                            preg_match_all('/^covService([a-z]+)_([\d]+)$/i', $key, $arrMatches);
                            $arrCover[$arrMatches[2][0]][$arrMatches[1][0]] = $data;
                        } elseif(preg_match('/^uncService[a-z]+_[\d]+$/i', $key)) {
                            preg_match_all('/^uncService([a-z]+)_([\d]+)$/i', $key, $arrMatches);
                            $arrUncover[$arrMatches[2][0]][$arrMatches[1][0]] = $data;
                        }
                    }
                    if(isset($this->_view->regInfoCovered) && !empty($this->_view->regInfoCovered)){
                        foreach($arrCover as $Cover){
                            if(isset($Cover['Trans']) && $Cover['Trans'] !=""){
                                $transId = $Cover['Trans'];
                                $update = $sql->update();
                                $update->table('PM_PMCoveredServiceTrans');
                                $update->set(array('PMRegisterId' => $regId
                                , 'ServiceId' => $Cover['Name']
                                , 'TransType' => $Cover['Type']
                                , 'TimesCount' => $Cover['Value']));
                                $update->where("PMRegisterId=$regId and TransId=$transId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } elseif($Cover['Name']!="") {
                                try {
                                    $insert = $sql->insert();
                                    $insert->into('PM_PMCoveredServiceTrans');
                                    $insert->Values(array('PMRegisterId' => $regId
                                    , 'ServiceId' => $Cover['Name']
                                    , 'TransType' => $Cover['Type']
                                    , 'TimesCount' => $Cover['Value']));
                                    echo	$statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } catch(\PDOException $ex){

                                }
                            }

                        }


                    } else {
                        foreach($arrCover as $Cover){
                            if($Cover['Name'] !=""){
                                try {
                                    $insert = $sql->insert();
                                    $insert->into('PM_PMCoveredServiceTrans');
                                    $insert->Values(array('PMRegisterId' => $regId
                                    , 'ServiceId' => $Cover['Name']
                                    , 'TransType' => $Cover['Type']
                                    , 'TimesCount' => $Cover['Value']));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } catch(\PDOException $ex){

                                }
                            }

                        }
                    }
                    if(isset($this->_view->regInfoUnCovered) && !empty($this->_view->regInfoUnCovered)){
                        foreach($arrUncover as $Uncover){
                            if(isset($Uncover['Trans']) && $Uncover['Trans'] !=""){
                                $transId = $Uncover['Trans'];
                                $update = $sql->update();
                                $update->table('PM_PMUncoveredServiceTrans');
                                $update->set(array('PMRegisterId' => $regId
                                , 'ServiceId' => $Uncover['Name']
                                , 'TransType' => $Uncover['Type']
                                , 'Amount' => $Uncover['Value']));
                                $update->where("PMRegisterId=$regId and TransId=$transId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } elseif($Uncover['Name']!="") {
                                try {
                                    $insert = $sql->insert();
                                    $insert->into('PM_PMUncoveredServiceTrans');
                                    $insert->Values(array('PMRegisterId' => $regId
                                    , 'ServiceId' => $Uncover['Name']
                                    , 'TransType' => $Uncover['Type']
                                    , 'Amount' => $Uncover['Value']));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } catch(\PDOException $ex){

                                }
                            }
                        }

                    } else {
                        foreach($arrUncover as $Uncover){
                            if($Uncover['Name'] !=""){
                                try{
                                    $insert = $sql->insert();
                                    $insert->into('PM_PMUncoveredServiceTrans');
                                    $insert->Values(array('PMRegisterId' => $regId
                                    , 'ServiceId' => $Uncover['Name']
                                    , 'TransType' => $Uncover['Type']
                                    , 'Amount' => $Uncover['Value']));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } catch(\PDOException $ex){

                                }
                            }
                        }
                    }

                    $connection->commit();
                    if($regId!=""){
                        $this->redirect()->toRoute("crm/rent", array("controller" => "property","action" => "rent","unitId"=>$unitId,"regId"=>$regId));
                    } else {
                        $this->redirect()->toRoute("crm/services", array("controller" => "property","action" => "services","unitId"=>$unitId,"regId"=>$regId));
                    }
                }
                catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function rentalAgreementAction() {
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
        $rentlead = $this->params()->fromRoute('rentalId');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $leadId = $this->bsf->isNullCheck($postData['LeadId'], 'number');
                    $unitId = $this->bsf->isNullCheck($postData['UnitId'], 'number');

                    $select = $sql->select();
                    $select->from("KF_UnitMaster")
                        ->columns(array("UnitArea"))
                        ->where(array("UnitId"=>$unitId,"DeleteFlag" => '0'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $buildUpArea = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('Crm_LeadAddress')
                        ->columns(array('Address1', 'PanNo'))
                        ->where(array("LeadId" => $leadId, "AddressType" => "P", "DeleteFlag" => '0'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $perLeadDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('Crm_LeadAddress')
                        ->columns(array('Address1', 'POAId'))
                        ->where(array("LeadId" => $leadId, "AddressType" => "POA", "DeleteFlag" => '0'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $poaLeadDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if ($poaLeadDetails['POAId'] != 0) {

                        $select = $sql->select();
                        $select->from('Crm_LeadPOAInfo')
                            ->columns(array("ApplicantName"))
                            ->where(array("POAId" => $poaLeadDetails['POAId'], "DeleteFlag" => '0'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $poaName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    } else {
                        $poaName="";
                    }
                    $levelIdDetails = array($perLeadDetails,$poaLeadDetails,$poaName,$buildUpArea);
                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                    return $response;
                } catch(PDOException $e) {
                    $connection->rollback();
                    $levelIdDetails="";
                    $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                    return $response;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
                //echo '<pre>'; print_r($postData); die;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $rentalRegisterId = $this->bsf->isNullCheck($postData['rentalregisterid'], 'number');
                    $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                    if(isset($rentalRegisterId) && $rentalRegisterId!=0 && $mode == "edit" ) {
                        $files = $request->getFiles();
                        $refDate = NULL;
                        $agreeDate = NULL;
                        $agreeStart = NULL;
                        $endDate = NULL;
                        if ($postData['refdate']) {
                            $refDate = date('Y-m-d', strtotime($postData['refdate']));
                        }
                        if ($postData['agreedate']) {
                            $agreeDate = date('Y-m-d', strtotime($postData['agreedate']));
                        }
                        if ($postData['agreestart']) {
                            $agreeStart = date('Y-m-d', strtotime($postData['agreestart']));
                        }
                        if ($postData['enddate']) {
                            $endDate = date('Y-m-d', strtotime($postData['enddate']));
                        }
                        //Owner Details Edit
                        $unitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                        $lead = $this->bsf->isNullCheck($postData['lead'], 'number');
                        $refNo = $this->bsf->isNullCheck($postData['refno'], 'string');
                        $ownerAddress = $this->bsf->isNullCheck($postData['address'], 'string');
                        $panNo = $this->bsf->isNullCheck($postData['pan_no'], 'string');
                        $powerAttorney = $this->bsf->isNullCheck($postData['powerattorney'], 'number');
                        $attorneyName = $this->bsf->isNullCheck($postData['attorney_name'], 'string');
                        $attorneyAddress = $this->bsf->isNullCheck($postData['attorney_address'], 'string');
                        $templateId = $this->bsf->isNullCheck($postData['changeTemplate'], 'number');
                        $templateContent = htmlentities($postData['templateContentHide']);

                        $update = $sql->update();
                        $update->table('PM_RentalRegister');
                        $update->set(array('UnitId' => $unitId
                        , 'Address' => $ownerAddress
                        , 'PANNo' => $panNo
                        , 'IsPowerOfAttorney' => $powerAttorney
                        , 'PAName' => $attorneyName
                        , 'RefNo' => $refNo
                        , 'RefDate' => $refDate
                        , 'LeadId' => $lead
                        , 'RentalType' => "R"
                        , 'PAAddress' => $attorneyAddress
                        , 'TemplateId' => $templateId
                        , 'TemplateContent' => $templateContent));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Tenant Info edit
                        $tenantName = $this->bsf->isNullCheck($postData['tenantname'], 'string');
                        $tenantAddress = $this->bsf->isNullCheck($postData['per_address'], 'string');
                        $tenantPanNo = $this->bsf->isNullCheck($postData['tenant_pan_no'], 'string');

                        $update = $sql->update();
                        $update->table('PM_RentalTenantTrans');
                        $update->set(array('LeaserName' => $tenantName
                        , 'PANNo' => $tenantPanNo
                        , 'Address' => $tenantAddress));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Property Details Edit
                        $propertyType = $this->bsf->isNullCheck($postData['property_type'], 'string');
                        $bedRoomNo = $this->bsf->isNullCheck($postData['bedroomno'], 'number');
                        $bathRoomNo = $this->bsf->isNullCheck($postData['bathroomno'], 'number');
                        $propertyArea = $this->bsf->isNullCheck($postData['area'], 'number');
                        $propertyAddress = $this->bsf->isNullCheck($postData['proper_address'], 'string');

                        $update = $sql->update();
                        $update->table('PM_RentalPropertyTrans');
                        $update->set(array('PropertyType' => $propertyType
                        , 'NoOfBedRoom' => $bedRoomNo
                        , 'Area' => $propertyArea
                        , 'PropertyAddress' => $propertyAddress
                        , 'NoOfBathRoom' => $bathRoomNo));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Agreement Details Edit
                        $agreeDuration = $this->bsf->isNullCheck($postData['agreeduration'], 'number');
                        $agreeTerminate = $this->bsf->isNullCheck($postData['terminate'], 'number');
                        $noticePeriod = $this->bsf->isNullCheck($postData['noticeperiod'], 'number');
                        $noticeType = $this->bsf->isNullCheck($postData['notice_type'], 'string');
                        $leasePeriodType = $this->bsf->isNullCheck($postData['duration_type'], 'string');

                        $update = $sql->update();
                        $update->table('PM_RentalAgreementTrans');
                        $update->set(array('AgtDate' => $agreeDate
                        , 'LeasePeriodType' => $leasePeriodType
                        , 'LeasePeriod' => $agreeDuration
                        , 'StartDate' => $agreeStart
                        , 'EndDate' => $endDate
                        , 'IsNoticePeriod' => $agreeTerminate
                        , 'NoticePeriod' => $noticePeriod
                        , 'NoticePeriodType' => $noticeType));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Rent and Security deposit Edit
                        $monthlyRent = $this->bsf->isNullCheck($postData['monthlyrent'], 'number');
                        $dueDay = $this->bsf->isNullCheck($postData['dueday'], 'number');
                        $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'string');
                        $depositAmount = $this->bsf->isNullCheck($postData['depositamount'], 'number');
                        $agreeExpiration = $this->bsf->isNullCheck($postData['expiration'], 'string');

                        $update = $sql->update();
                        $update->table('PM_RentalRentTrans');
                        $update->set(array('RentAmount' => $monthlyRent
                        , 'DueDay' => $dueDay
                        , 'IsSecurityDeposit' => $securityDeposit
                        , 'SDAmount' => $depositAmount
                        , 'RenewalType' => $agreeExpiration));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Other Charge Edit
                        $chargeCount = $this->bsf->isNullCheck($postData['charge_count'], 'number');
                        for ($i = 1; $i <= $chargeCount; $i++) {
                            if ($postData['charge_id_' . $i] != "" && $postData['charge_id_' . $i] != "0") {
                                $chargeId = $this->bsf->isNullCheck($postData['charge_id_' . $i], 'number');
                                $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                $chargeTransId = $this->bsf->isNullCheck($postData['charge_transid_' . $i], 'number');
                                if($chargeTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalOtherCostTrans');
                                    $update->set(array('ServiceId' => $chargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$chargeTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ServiceId' => $chargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            } else {
                                if($postData['charge_name_' . $i]!="" || $postData['charge_name_' . $i]!=0) {
                                    if ($postData['charge_transid_' . $i] != "" || $postData['charge_transid_' . $i] != 0) {
                                        $chargeTransId = $this->bsf->isNullCheck($postData['charge_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalOtherCostTrans')
                                            ->where(array('TransId' => $chargeTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                    $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                    $chargeName = $this->bsf->isNullCheck($postData['charge_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_ServiceMaster');
                                    $insert->Values(array('ServiceName' => $chargeName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChargeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ServiceId' => $newChargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Facilities edit
                        $householdCount = $this->bsf->isNullCheck($postData['household_count'], 'number');
                        for ($i = 1; $i <= $householdCount; $i++) {
                            if ($postData['household_id_' . $i] != "" && $postData['household_id_' . $i] != "0") {
                                $householdId = $this->bsf->isNullCheck($postData['household_id_' . $i], 'number');
                                $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                                $householdTransId = $this->bsf->isNullCheck($postData['household_transid_' . $i], 'number');
                                if($householdTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalFurnitureTrans');
                                    $update->set(array('FurnitureId' => $householdId
                                    , 'Qty' => $householdValue));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$householdTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalFurnitureTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'FurnitureId' => $householdId
                                    , 'Qty' => $householdValue));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            } else {
                                if ($postData['household_name_' . $i] != "" || $postData['household_name_' . $i] != 0) {

                                    if ($postData['household_transid_' . $i] != "" || $postData['household_transid_' . $i] != 0) {
                                        $householdTransId = $this->bsf->isNullCheck($postData['household_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalFurnitureTrans')
                                            ->where(array('TransId' => $householdTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                                    $householdName = $this->bsf->isNullCheck($postData['household_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_FurnitureMaster');
                                    $insert->Values(array('FurnitureName' => $householdName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newFurnitureId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalFurnitureTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'FurnitureId' => $newFurnitureId
                                    , 'Qty' => $householdValue));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }

                        //Amenity edit
                        $amenityCount = $this->bsf->isNullCheck($postData['amenity_count'], 'number');
                        for ($i = 1; $i <= $amenityCount; $i++) {
                            if ($postData['amenity_id_' . $i] != "" && $postData['amenity_id_' . $i] != "0") {
                                $amenityId = $this->bsf->isNullCheck($postData['amenity_id_' . $i], 'number');
                                $amenityTransId = $this->bsf->isNullCheck($postData['amenity_transid_' . $i], 'number');
                                if($amenityTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalAmenityTrans');
                                    $update->set(array('AmenityId' => $amenityId));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$amenityTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalAmenityTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'AmenityId' => $amenityId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            } else {
                                if ($postData['amenity_name_' . $i] != "" || $postData['amenity_name_' . $i] != 0) {

                                    if ($postData['amenity_transid_' . $i] != "" || $postData['amenity_transid_' . $i] != 0) {
                                        $amenityTransId = $this->bsf->isNullCheck($postData['amenity_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalAmenityTrans')
                                            ->where(array('TransId' => $amenityTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $amenityName = $this->bsf->isNullCheck($postData['amenity_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_AmenityMaster');
                                    $insert->Values(array('AmenityName' => $amenityName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newAmenityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalAmenityTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'AmenityId' => $newAmenityId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }

                        //Checklist edit
                        $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                        for ($i = 1; $i <= $checklistCount; $i++) {
                            if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                                $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');
                                $checklistTransId = $this->bsf->isNullCheck($postData['checklist_transid_' . $i], 'number');
                                if($checklistTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalCheckListTrans');
                                    $update->set(array('ChecklistId' => $checklistId));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$checklistTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ChecklistId' => $checklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            } else {
                                if ($postData['checklist_name_' . $i] != "" || $postData['checklist_name_' . $i] != 0) {

                                    if ($postData['checklist_transid_' . $i] != "" || $postData['checklist_transid_' . $i] != 0) {
                                        $checklistTransId = $this->bsf->isNullCheck($postData['checklist_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalCheckListTrans')
                                            ->where(array('TransId' => $checklistTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $checklistName = $this->bsf->isNullCheck($postData['checklist_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('Proj_CheckListMaster');
                                    $insert->Values(array('CheckListName' => $checklistName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChecklistId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ChecklistId' => $newChecklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                        //Document Edit
                        $documentCount = $this->bsf->isNullCheck($postData['document_count'], 'number');
                        for ($i = 1; $i <= $documentCount; $i++) {
                            $documentTransId = $this->bsf->isNullCheck($postData['document_transid_' . $i], 'number');
                            $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');
                            if ($documentTransId != 0) {
                                if ($postData['document_name_' . $i] != "") {
                                    $update = $sql->update();
                                    $update->table('PM_RentalDocumentTrans');
                                    $update->set(array('DocumentName' => $documentName));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$documentTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else {
                                if ($postData['document_name_' . $i] != "") {

                                    $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalDocumentTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'DocumentName' => $documentName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    if ($files['document_' . $i]['name']) {
                                        $dir = 'public/uploads/crm/agreement/';
                                        if (!is_dir($dir))
                                            mkdir($dir, 0755, true);

                                        $docExt = pathinfo($files['document_' . $i]['name'], PATHINFO_EXTENSION);
                                        $path = $dir . $TransId . '.' . $docExt;
                                        move_uploaded_file($files['document_' . $i]['tmp_name'], $path);

                                        $updateDocExt = $sql->update();
                                        $updateDocExt->table('PM_RentalDocumentTrans');
                                        $updateDocExt->set(array(
                                            'URL' => $this->bsf->isNullCheck($docExt, 'string')
                                        ))
                                            ->where(array('TransId' => $TransId));
                                        $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                        $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                }
                            }
                        }
                        //Schedule & payment Edit
                        $delete = $sql->delete();
                        $delete->from('PM_RentalPaymentScheduleTrans')
                            ->where(array('RentalRegisterId' => $rentalRegisterId));
                        $stmt = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        for ($i = 0; $i < $agreeDuration; $i++) {
                            $scheduleDate = NULL;
                            if ($postData['schDate_' . $i]) {
                                $scheduleDate = date('Y-m-d', strtotime($postData['schDate_' . $i]));
                            }
                            $scheduleAmount = $this->bsf->isNullCheck($postData['schAmt_' . $i], 'number');
                            $insert = $sql->insert();
                            $insert->into('PM_RentalPaymentScheduleTrans');
                            $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                            , 'ScheduleDate' => $scheduleDate
                            , 'ScheduleType' => "N"
                            , 'ScheduleAmount' => $scheduleAmount));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Agreement-Residential-Modify','E','Lease-Agreement-Residential',$rentalRegisterId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    } else {
                        $files = $request->getFiles();
                        $refDate = NULL;
                        $agreeDate = NULL;
                        $agreeStart = NULL;
                        $endDate = NULL;
                        if ($postData['refdate']) {
                            $refDate = date('Y-m-d', strtotime($postData['refdate']));
                        }
                        if ($postData['agreedate']) {
                            $agreeDate = date('Y-m-d', strtotime($postData['agreedate']));
                        }
                        if ($postData['agreestart']) {
                            $agreeStart = date('Y-m-d', strtotime($postData['agreestart']));
                        }

                        if ($postData['enddate']) {
                            $endDate = date('Y-m-d', strtotime($postData['enddate']));
                        }
                        //Owner Info Added
                        $lead = $this->bsf->isNullCheck($postData['lead'], 'number');
                        $unitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                        $refNo = $this->bsf->isNullCheck($postData['refno'], 'string');
                        $ownerAddress = $this->bsf->isNullCheck($postData['address'], 'string');
                        $panNo = $this->bsf->isNullCheck($postData['pan_no'], 'string');
                        $powerAttorney = $this->bsf->isNullCheck($postData['powerattorney'], 'number');
                        $attorneyName = $this->bsf->isNullCheck($postData['attorney_name'], 'string');
                        $attorneyAddress = $this->bsf->isNullCheck($postData['attorney_address'], 'string');
                        $templateId = $this->bsf->isNullCheck($postData['changeTemplate'], 'number');
                        $templateContent = htmlentities($postData['templateContentHide']);

                        $insert = $sql->insert();
                        $insert->into('PM_RentalRegister');
                        $insert->Values(array('UnitId' => $unitId
                        , 'Address' => $ownerAddress
                        , 'PANNo' => $panNo
                        , 'IsPowerOfAttorney' => $powerAttorney
                        , 'PAName' => $attorneyName
                        , 'RefNo' => $refNo
                        , 'LeadId' => $lead
                        , 'RefDate' => $refDate
                        , 'RentalType' => "R"
                        , 'PAAddress' => $attorneyAddress
                        , 'TemplateId' => $templateId
                        , 'TemplateContent' => $templateContent));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Last Inserted Rental Register Id
                        $RentalRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //Tenant Info Added
                        $tenantName = $this->bsf->isNullCheck($postData['tenantname'], 'string');
                        $tenantAddress = $this->bsf->isNullCheck($postData['per_address'], 'string');
                        $tenantPanNo = $this->bsf->isNullCheck($postData['tenant_pan_no'], 'string');

                        $insert = $sql->insert();
                        $insert->into('PM_RentalTenantTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'LeaserName' => $tenantName
                        , 'PANNo' => $tenantPanNo
                        , 'Address' => $tenantAddress));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Property Details Added
                        $propertyType = $this->bsf->isNullCheck($postData['property_type'], 'string');
                        $bedRoomNo = $this->bsf->isNullCheck($postData['bedroomno'], 'number');
                        $bathRoomNo = $this->bsf->isNullCheck($postData['bathroomno'], 'number');
                        $propertyArea = $this->bsf->isNullCheck($postData['area'], 'number');
                        $propertyAddress = $this->bsf->isNullCheck($postData['proper_address'], 'string');

                        $insert = $sql->insert();
                        $insert->into('PM_RentalPropertyTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'PropertyType' => $propertyType
                        , 'NoOfBedRoom' => $bedRoomNo
                        , 'Area' => $propertyArea
                        , 'PropertyAddress' => $propertyAddress
                        , 'NoOfBathRoom' => $bathRoomNo));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Agreement Details Added
                        $agreeDuration = $this->bsf->isNullCheck($postData['agreeduration'], 'number');
                        $agreeTerminate = $this->bsf->isNullCheck($postData['terminate'], 'number');
                        $noticePeriod = $this->bsf->isNullCheck($postData['noticeperiod'], 'number');
                        $noticeType = $this->bsf->isNullCheck($postData['notice_type'], 'string');
                        $leasePeriodType = $this->bsf->isNullCheck($postData['duration_type'], 'string');

                        $insert = $sql->insert();
                        $insert->into('PM_RentalAgreementTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'AgtDate' => $agreeDate
                        , 'LeasePeriod' => $agreeDuration
                        , 'LeasePeriodType' => $leasePeriodType
                        , 'StartDate' => $agreeStart
                        , 'EndDate' => $endDate
                        , 'IsNoticePeriod' => $agreeTerminate
                        , 'NoticePeriod' => $noticePeriod
                        , 'NoticePeriodType' => $noticeType));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Rent and Security deposit
                        $monthlyRent = $this->bsf->isNullCheck($postData['monthlyrent'], 'number');
                        $dueDay = $this->bsf->isNullCheck($postData['dueday'], 'number');
                        $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'string');
                        $depositAmount = $this->bsf->isNullCheck($postData['depositamount'], 'number');
                        $agreeExpiration = $this->bsf->isNullCheck($postData['expiration'], 'string');
                        $insert = $sql->insert();

                        $insert->into('PM_RentalRentTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'RentAmount' => $monthlyRent
                        , 'DueDay' => $dueDay
                        , 'IsSecurityDeposit' => $securityDeposit
                        , 'SDAmount' => $depositAmount
                        , 'RenewalType' => $agreeExpiration));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Other Charges added
                        $chargeCount = $this->bsf->isNullCheck($postData['charge_count'], 'number');
                        for ($i = 1; $i <= $chargeCount; $i++) {
                            if ($postData['charge_id_' . $i] != "" && $postData['charge_id_' . $i] != "0") {
                                $chargeId = $this->bsf->isNullCheck($postData['charge_id_' . $i], 'number');
                                $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalOtherCostTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'ServiceId' => $chargeId
                                , 'Amount' => $chargeValue
                                , 'TransType' => $chargeType));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }
                            else {
                                if ($postData['charge_name_' . $i] != "" || $postData['charge_name_' . $i] != 0) {

                                    $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                    $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                    $chargeName = $this->bsf->isNullCheck($postData['charge_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_ServiceMaster');
                                    $insert->Values(array('ServiceName' => $chargeName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChargeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'ServiceId' => $newChargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Facilities added
                        $householdCount = $this->bsf->isNullCheck($postData['household_count'], 'number');
                        for ($i = 1; $i <= $householdCount; $i++) {
                            if ($postData['household_id_' . $i] != "" && $postData['household_id_' . $i] != "0") {
                                $householdId = $this->bsf->isNullCheck($postData['household_id_' . $i], 'number');
                                $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalFurnitureTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'FurnitureId' => $householdId
                                , 'Qty' => $householdValue));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                if ($postData['household_name_' . $i] != "" || $postData['household_name_' . $i] != 0) {

                                    $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                                    $householdName = $this->bsf->isNullCheck($postData['household_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_FurnitureMaster');
                                    $insert->Values(array('FurnitureName' => $householdName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newFurnitureId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalFurnitureTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'FurnitureId' => $newFurnitureId
                                    , 'Qty' => $householdValue));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Amenity Added
                        $amenityCount = $this->bsf->isNullCheck($postData['amenity_count'], 'number');
                        for ($i = 1; $i <= $amenityCount; $i++) {
                            if ($postData['amenity_id_' . $i] != "" && $postData['amenity_id_' . $i] != "0") {
                                $amenityId = $this->bsf->isNullCheck($postData['amenity_id_' . $i], 'number');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalAmenityTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'AmenityId' => $amenityId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                if ($postData['amenity_name_' . $i] != "" || $postData['amenity_name_' . $i] != 0) {

                                    $amenityName = $this->bsf->isNullCheck($postData['amenity_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_AmenityMaster');
                                    $insert->Values(array('AmenityName' => $amenityName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newAmenityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalAmenityTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'AmenityId' => $newAmenityId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Checklist added
                        $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                        for ($i = 1; $i <= $checklistCount; $i++) {
                            if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                                $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalCheckListTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'ChecklistId' => $checklistId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                if ($postData['checklist_name_' . $i] != "" || $postData['checklist_name_' . $i] != 0) {

                                    $checklistName = $this->bsf->isNullCheck($postData['checklist_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('Proj_CheckListMaster');
                                    $insert->Values(array('CheckListName' => $checklistName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChecklistId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'ChecklistId' => $newChecklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Document added
                        $documentCount = $this->bsf->isNullCheck($postData['document_count'], 'number');
                        for ($i = 1; $i <= $documentCount; $i++) {
                            if ($postData['document_name_' . $i] != "") {

                                $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalDocumentTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'DocumentName' => $documentName));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                if ($files['document_' . $i]['name']) {
                                    $dir = 'public/uploads/crm/agreement/';
                                    if (!is_dir($dir))
                                        mkdir($dir, 0755, true);

                                    $docExt = pathinfo($files['document_' . $i]['name'], PATHINFO_EXTENSION);
                                    $path = $dir . $TransId . '.' . $docExt;
                                    move_uploaded_file($files['document_' . $i]['tmp_name'], $path);

                                    $updateDocExt = $sql->update();
                                    $updateDocExt->table('PM_RentalDocumentTrans');
                                    $updateDocExt->set(array(
                                        'URL' => $this->bsf->isNullCheck($docExt, 'string')
                                    ))
                                        ->where(array('TransId' => $TransId));
                                    $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                    $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                            }
                        }

                        //Schedule & payment Added
                        for ($i = 0; $i < $agreeDuration; $i++) {
                            $scheduleDate = NULL;
                            if ($postData['schDate_' . $i]) {
                                $scheduleDate = date('Y-m-d', strtotime($postData['schDate_' . $i]));
                            }
                            $scheduleAmount = $this->bsf->isNullCheck($postData['schAmt_' . $i], 'number');
                            $insert = $sql->insert();
                            $insert->into('PM_RentalPaymentScheduleTrans');
                            $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                            , 'ScheduleDate' => $scheduleDate
                            , 'ScheduleType' => "N"
                            , 'ScheduleAmount' => $scheduleAmount));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Agreement-Residential-Add','N','Lease-Agreement-Residential',$RentalRegisterId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    }

                } catch(PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                $FeedId = $this->params()->fromQuery('FeedId');
                $AskId = $this->params()->fromQuery('AskId');
                if(isset($FeedId) && $FeedId!="") {
                    $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "rental-agreement"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                } else {
                    $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"));
                }

//                return $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "rental-agreement"));
            } else {

                $rentalRegisterId = $this->bsf->isNullCheck($this->params()->fromRoute('rentalId'), 'number');
                $mode = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'string');
                if(isset($rentalRegisterId) && $rentalRegisterId!=0 && $mode=="edit") {
                    $select = $sql->select();
                    $select->from("PM_RentalRegister")
                        ->columns(array('UnitId', 'Address','LeadId', 'PANNo', 'IsPowerOfAttorney', 'PAName', 'RefNo', 'RefDate', 'RentalType', 'PAAddress','TemplateId','TemplateContent'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $ownerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $OwnerUnit = $ownerDetail['UnitId'];
                    $this->_view->ownerDetail = $ownerDetail;

                    $aVNo = CommonHelper::getVoucherNo(810, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->genType = $aVNo["genType"];
                    if ($aVNo["genType"] == false){
                        $this->_view->svNo = "";}
                    else {
                        $this->_view->svNo = $aVNo["voucherNo"];
                    }


                    $select = $sql->select();
                    $select->from(array("a" => "Crm_UnitDetails"))
                        ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                        ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadId'), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                        ->where("a.UnitId=$OwnerUnit");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ownerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from("PM_RentalTenantTrans")
                        ->columns(array('LeaserName', 'PANNo', 'Address'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tenantDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from("PM_RentalPropertyTrans")
                        ->columns(array('PropertyType', 'NoOfBedRoom', 'Area', 'PropertyAddress', 'NoOfBathRoom'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->propertyDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from("PM_RentalAgreementTrans")
                        ->columns(array('AgtDate', 'LeasePeriod', 'LeasePeriodType', 'StartDate', 'IsNoticePeriod', 'NoticePeriod', 'NoticePeriodType'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->agreementDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from("PM_RentalRentTrans")
                        ->columns(array('RentAmount', 'DueDay', 'IsSecurityDeposit', 'SDAmount', 'RenewalType'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->secureRentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalOtherCostTrans"))
                        ->join(array('b' => 'PM_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('ServiceName'), $select::JOIN_INNER)
                        ->columns(array('ServiceId', 'Amount', 'TransType', 'TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->otherDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->chargeCount = count($this->_view->otherDetail);

                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalFurnitureTrans"))
                        ->join(array('b' => 'PM_FurnitureMaster'), 'a.FurnitureId=b.FurnitureId', array('FurnitureName'), $select::JOIN_INNER)
                        ->columns(array('FurnitureId', 'Qty','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->furnitureDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->holdCount = count($this->_view->furnitureDetail);

                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalAmenityTrans"))
                        ->join(array('b' => 'PM_AmenityMaster'), 'a.AmenityId=b.AmenityId', array('AmenityName'), $select::JOIN_INNER)
                        ->columns(array('AmenityId','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->amenityDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->amenityCount = count($this->_view->amenityDetail);

                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalCheckListTrans"))
                        ->join(array('b' => 'Proj_CheckListMaster'), 'a.ChecklistId=b.ChecklistId', array('CheckListName'), $select::JOIN_INNER)
                        ->columns(array('ChecklistId','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->checklistDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->checklistCount = count($this->_view->checklistDetail);

                    //Document
                    $select = $sql->select();
                    $select->from("PM_RentalDocumentTrans")
                        ->columns(array('DocumentName','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->documentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->documentCount = count($this->_view->documentDetail);

                    $this->_view->rentalRegisterId=$rentalRegisterId;
                }

                //Query for Project/Unit/Owner
                $select = $sql->select();
                $select->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadId','LeadName'), $select::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array('UnitNo'), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                    ->columns(array('data' => 'UnitId','value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("PM_ServiceMaster")
                    ->columns(array('data' => 'ServiceId', 'value' => new Expression('ServiceName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->serviceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("Proj_CheckListMaster")
                    ->columns(array('data' => 'CheckListId', 'value' => new Expression('CheckListName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->checkList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("PM_AmenityMaster")
                    ->columns(array('data' => 'AmenityId', 'value' => new Expression('AmenityName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->amenityList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("PM_FurnitureMaster")
                    ->columns(array('data' => 'FurnitureId', 'value' => new Expression('FurnitureName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->householdList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Fetching data from agreement template
                $select = $sql->select();
                $select->from('PM_AgreementTemplate')
                    ->columns(array('TemplateId','TemplateName'))
                    ->where("AgreementTypeId = '1'")
                    ->where("DeleteFlag = '0'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->agreementTemplates = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_Leads')
                    ->columns(array('LeadName'))
                    ->where(array("LeadId" => $rentalRegisterId,"DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenantName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Crm_LeadAddress')
                    ->columns(array('Address1','PanNo'))
                    ->where(array("LeadId" => $rentalRegisterId,"AddressType" => "P","DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenantPanAdd = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->mode = $mode;
                $this->_view->rentlead= $rentlead;
            }

            $this->_view->mergeTags = $this->bsf->getMergeTags(1);
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    /**
     * @return ViewModel
     */
    public function agreementRenewalAction()
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
            $response = $this->getResponse();
            if ($request->isPost()) {
                //Fetching data from Payment Schedule
                //$response->setContent(json_encode($results));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $rentalType = $this->bsf->isNullCheck($postData['rentaltype'], 'string');
                    $rentalRegisterId = $this->bsf->isNullCheck($postData['agreeno'], 'number');
                    $mode = $this->bsf->isNullCheck($postData['modetype'], 'string');

                    if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"edit") == 0) {
                        $files = $request->getFiles();

                        $validityDate = null;
                        if(isset($postData['validitydate'])){
                            $validityDate = date('Y-m-d', strtotime($postData['validitydate']));
                        }
                        $refDate = null;
                        if(isset($postData['refdate'])){
                            $refDate = date('Y-m-d', strtotime($postData['refdate']));

                        }
                        //Agreement Details Added
                        $agreeCostChange = $this->bsf->isNullCheck($postData['costchange'], 'number');
                        $agreeDuration = $this->bsf->isNullCheck($postData['agreeduration'], 'number');
                        $durationType = $this->bsf->isNullCheck($postData['duration_type'], 'string');
                        $refNo = $this->bsf->isNullCheck($postData['refno'], 'number');
                        $agreementNo = $this->bsf->isNullCheck($postData['agreementNo'], 'string');
                        $templateId = $this->bsf->isNullCheck($postData['changeTemplate'], 'number');
                        $templateContent = htmlentities($postData['templateContentHide']);

                        $update = $sql->update();
                        $update->table('PM_RentalRegister');
                        $update->set(array('RefNo' => $refNo
                        , 'RefDate' => $refDate
                        ,'AgreementNo'=>$agreementNo
                        , 'TemplateId' => $templateId
                        , 'TemplateContent' => $templateContent));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('PM_RentalAgreementTrans');
                        $update->set(array('LeasePeriod' => $agreeDuration
                        , 'EndDate' => $validityDate
                        , 'CostImpact' => $agreeCostChange));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        if($agreeCostChange==1) {

                            //Rent and Security deposit Edit
                            $monthlyRent = $this->bsf->isNullCheck($postData['monthlyrent'], 'number');
                            $dueDay = $this->bsf->isNullCheck($postData['dueday'], 'number');
                            $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'string');
                            $depositAmount = $this->bsf->isNullCheck($postData['depositamount'], 'number');
                            $agreeExpiration = $this->bsf->isNullCheck($postData['expiration'], 'string');

                            $update = $sql->update();
                            $update->table('PM_RentalRentTrans');
                            $update->set(array('RentAmount' => $monthlyRent
                            , 'DueDay' => $dueDay
                            , 'IsSecurityDeposit' => $securityDeposit
                            , 'SDAmount' => $depositAmount
                            , 'RenewalType' => $agreeExpiration));
                            $update->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Other Charge Edit
                            $chargeCount = $this->bsf->isNullCheck($postData['charge_count'], 'number');
                            for ($i = 1; $i <= $chargeCount; $i++) {
                                if ($postData['charge_id_' . $i] != "" && $postData['charge_id_' . $i] != "0") {
                                    $chargeId = $this->bsf->isNullCheck($postData['charge_id_' . $i], 'number');
                                    $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                    $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                    $chargeTransId = $this->bsf->isNullCheck($postData['charge_transid_' . $i], 'number');
                                    if ($chargeTransId != 0) {
                                        $update = $sql->update();
                                        $update->table('PM_RentalOtherCostTrans');
                                        $update->set(array('ServiceId' => $chargeId
                                        , 'Amount' => $chargeValue
                                        , 'TransType' => $chargeType));
                                        $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$chargeTransId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else {
                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalOtherCostTrans');
                                        $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                        , 'ServiceId' => $chargeId
                                        , 'Amount' => $chargeValue
                                        , 'TransType' => $chargeType));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } else {
                                    if ($postData['charge_name_' . $i] != "" || $postData['charge_name_' . $i] != 0) {
                                        if ($postData['charge_transid_' . $i] != "" || $postData['charge_transid_' . $i] != 0) {
                                            $chargeTransId = $this->bsf->isNullCheck($postData['charge_transid_' . $i], 'number');
                                            $delete = $sql->delete();
                                            $delete->from('PM_RentalOtherCostTrans')
                                                ->where(array('TransId' => $chargeTransId));
                                            $stmt = $sql->getSqlStringForSqlObject($delete);
                                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                        $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                        $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                        $chargeName = $this->bsf->isNullCheck($postData['charge_name_' . $i], 'string');

                                        $insert = $sql->insert();
                                        $insert->into('PM_ServiceMaster');
                                        $insert->Values(array('ServiceName' => $chargeName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $newChargeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalOtherCostTrans');
                                        $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                        , 'ServiceId' => $newChargeId
                                        , 'Amount' => $chargeValue
                                        , 'TransType' => $chargeType));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                            //Checklist edit
                            $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                            for ($i = 1; $i <= $checklistCount; $i++) {
                                if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                                    $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');
                                    $checklistTransId = $this->bsf->isNullCheck($postData['checklist_transid_' . $i], 'number');
                                    if ($checklistTransId != 0) {
                                        $update = $sql->update();
                                        $update->table('PM_RentalCheckListTrans');
                                        $update->set(array('ChecklistId' => $checklistId));
                                        $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$checklistTransId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else {
                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalCheckListTrans');
                                        $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                        , 'ChecklistId' => $checklistId));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    }
                                } else {
                                    if ($postData['checklist_name_' . $i] != "" || $postData['checklist_name_' . $i] != 0) {

                                        if ($postData['checklist_transid_' . $i] != "" || $postData['checklist_transid_' . $i] != 0) {
                                            $checklistTransId = $this->bsf->isNullCheck($postData['checklist_transid_' . $i], 'number');
                                            $delete = $sql->delete();
                                            $delete->from('PM_RentalCheckListTrans')
                                                ->where(array('TransId' => $checklistTransId));
                                            $stmt = $sql->getSqlStringForSqlObject($delete);
                                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                        $checklistName = $this->bsf->isNullCheck($postData['checklist_name_' . $i], 'string');

                                        $insert = $sql->insert();
                                        $insert->into('Proj_CheckListMaster');
                                        $insert->Values(array('CheckListName' => $checklistName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $newChecklistId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalCheckListTrans');
                                        $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                        , 'ChecklistId' => $newChecklistId));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                            //Document Edit
                            $documentCount = $this->bsf->isNullCheck($postData['document_count'], 'number');
                            for ($i = 1; $i <= $documentCount; $i++) {
                                $documentTransId = $this->bsf->isNullCheck($postData['document_transid_' . $i], 'number');
                                $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');
                                if ($documentTransId != 0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalDocumentTrans');
                                    $update->set(array('DocumentName' => $documentName));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$documentTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    if ($postData['document_name_' . $i] != "") {

                                        $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');

                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalDocumentTrans');
                                        $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                        , 'DocumentName' => $documentName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        if ($files['document_' . $i]['name']) {
                                            $dir = 'public/uploads/crm/agreement/';
                                            if (!is_dir($dir))
                                                mkdir($dir, 0755, true);

                                            $docExt = pathinfo($files['document_' . $i]['name'], PATHINFO_EXTENSION);
                                            $path = $dir . $TransId . '.' . $docExt;
                                            move_uploaded_file($files['document_' . $i]['tmp_name'], $path);

                                            $updateDocExt = $sql->update();
                                            $updateDocExt->table('PM_RentalDocumentTrans');
                                            $updateDocExt->set(array(
                                                'URL' => $this->bsf->isNullCheck($docExt, 'string')
                                            ))
                                                ->where(array('TransId' => $TransId));
                                            $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                            $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                    }
                                }
                            }
                            if(isset($rentalType) && strcmp(trim($rentalType),"R") == 0) {
                                $delete = $sql->delete();
                                $delete->from('PM_RentalPaymentScheduleTrans')
                                    ->where("RentalRegisterId=$rentalRegisterId AND ScheduleType='R'");
                                $stmt = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);


                                $paySchArray = $postData['newpaydate'];
                                for ($i = 0; $i < $agreeDuration; $i++) {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalPaymentScheduleTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ScheduleDate' => date('Y-m-d', strtotime($paySchArray[$i]))
                                    , 'ScheduleAmount' => $monthlyRent
                                    , 'ScheduleType' => "R"));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Renewal-Modify','E','Lease-Renewal',$rentalRegisterId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    } else if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"add") == 0) {
                        //Agreement Details Added
                        $files = $request->getFiles();
                        $agreeCostChange = $this->bsf->isNullCheck($postData['costchange'], 'number');
                        $agreeDuration = $this->bsf->isNullCheck($postData['agreeduration'], 'number');
                        $refNo = $this->bsf->isNullCheck($postData['refno'], 'number');
                        $agreementNo = $this->bsf->isNullCheck($postData['agreementNo'], 'string');
                        $templateId = $this->bsf->isNullCheck($postData['changeTemplate'], 'number');
                        $templateContent = htmlentities($postData['templateContentHide']);

                        $validityDate = null;
                        if(isset($postData['validitydate'])){
                            $validityDate = date('Y-m-d', strtotime($postData['validitydate']));

                        }
                        $refDate = null;
                        if(isset($postData['refdate'])){
                            $refDate = date('Y-m-d', strtotime($postData['refdate']));

                        }
                        if(isset($rentalType) && strcmp(trim($rentalType),"R") == 0) {
                            $update = $sql->update();
                            $update->table('PM_RentalRegister');
                            $update->set(array('Deactive' => 1));
                            $update->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            // Old Data of Agreement
                            $select = $sql->select();
                            $select->from("PM_RentalRegister")
                                ->columns(array('UnitId', 'Address', 'LeadId','PANNo', 'IsPowerOfAttorney', 'AgreementNo','PAName', 'RefNo', 'RefDate', 'RentalType', 'PAAddress'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $ownerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from("PM_RentalTenantTrans")
                                ->columns(array('LeaserName', 'PANNo', 'Address'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $tenantDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from("PM_RentalPropertyTrans")
                                ->columns(array('PropertyType', 'NoOfBedRoom', 'Area', 'PropertyAddress', 'NoOfBathRoom'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $propertyDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from("PM_RentalAgreementTrans")
                                ->columns(array('AgtDate', 'LeasePeriod', 'LeasePeriodType', 'StartDate', 'IsNoticePeriod', 'NoticePeriod', 'NoticePeriodType'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $agreementDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from(array("a" => "PM_RentalFurnitureTrans"))
                                ->join(array('b' => 'PM_FurnitureMaster'), 'a.FurnitureId=b.FurnitureId', array('FurnitureName'), $select::JOIN_INNER)
                                ->columns(array('FurnitureId', 'Qty', 'TransId'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $furnitureDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                            $select = $sql->select();
                            $select->from(array("a" => "PM_RentalAmenityTrans"))
                                ->join(array('b' => 'PM_AmenityMaster'), 'a.AmenityId=b.AmenityId', array('AmenityName'), $select::JOIN_INNER)
                                ->columns(array('AmenityId', 'TransId'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $amenityDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();

                            $select = $sql->select();
                            $select->from("PM_RentalPaymentScheduleTrans")
                                ->columns(array('ScheduleAmount', 'ScheduleDate','ScheduleType'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $paymentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                            //Add old Data

                            //Owner Info Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalRegister');
                            $insert->Values(array('UnitId' => $ownerDetail['UnitId']
                            , 'Address' => $ownerDetail['Address']
                            , 'PANNo' => $ownerDetail['PANNo']
                            , 'IsPowerOfAttorney' => $ownerDetail['IsPowerOfAttorney']
                            , 'PAName' => $ownerDetail['PAName']
                            , 'RefNo' => $refNo
                            , 'AgreementNo'=> $agreementNo
                            , 'RefDate' => $refDate
                            , 'RentalType' => $ownerDetail['RentalType']
                            , 'PAAddress' => $ownerDetail['PAAddress']
                            , 'PrevRentalRegisterId' => $rentalRegisterId
                            , 'TemplateId' => $templateId
                            , 'TemplateContent' => $templateContent));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $newRentalRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            //Tenant Info Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalTenantTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'LeaserName' => $tenantDetail['LeaserName']
                            , 'PANNo' => $tenantDetail['PANNo']
                            , 'Address' => $tenantDetail['Address']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Property Details Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalPropertyTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'PropertyType' => $propertyDetail['PropertyType']
                            , 'NoOfBedRoom' => $propertyDetail['NoOfBedRoom']
                            , 'Area' => $propertyDetail['Area']
                            , 'PropertyAddress' => $propertyDetail['PropertyAddress']
                            , 'NoOfBathRoom' => $propertyDetail['NoOfBathRoom']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Rent and Security deposit
                            $monthlyRent = $this->bsf->isNullCheck($postData['monthlyrent'], 'number');
                            $dueDay = $this->bsf->isNullCheck($postData['dueday'], 'number');
                            $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'string');
                            $depositAmount = $this->bsf->isNullCheck($postData['depositamount'], 'number');
                            $agreeExpiration = $this->bsf->isNullCheck($postData['expiration'], 'string');

                            $insert = $sql->insert();
                            $insert->into('PM_RentalRentTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'RentAmount' => $monthlyRent
                            , 'DueDay' => $dueDay
                            , 'IsSecurityDeposit' => $securityDeposit
                            , 'SDAmount' => $depositAmount
                            , 'RenewalType' => $agreeExpiration));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            //Agreement Details Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalAgreementTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'AgtDate' => $agreementDetail['AgtDate']
                            , 'LeasePeriod' => $agreeDuration
                            , 'LeasePeriodType' => $agreementDetail['LeasePeriodType']
                            , 'StartDate' => $agreementDetail['StartDate']
                            , 'EndDate' => $validityDate
                            , 'IsNoticePeriod' => $agreementDetail['IsNoticePeriod']
                            , 'NoticePeriod' => $agreementDetail['NoticePeriod']
                            , 'CostImpact' => $agreeCostChange
                            , 'NoticePeriodType' => $agreementDetail['NoticePeriodType']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Facilities added
                            foreach($furnitureDetail as $furData):
                                $insert = $sql->insert();
                                $insert->into('PM_RentalFurnitureTrans');
                                $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                , 'FurnitureId' => $furData['FurnitureId']
                                , 'Qty' => $furData['Qty']));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            endforeach;

                            //Amenity Added
                            foreach($amenityDetail as $amtyData):
                                $insert = $sql->insert();
                                $insert->into('PM_RentalAmenityTrans');
                                $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                , 'AmenityId' => $amtyData['AmenityId']));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            endforeach;

                            //Payment Detail
                            foreach($paymentDetail as $payData):
                                $insert = $sql->insert();
                                $insert->into('PM_RentalPaymentScheduleTrans');
                                $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                , 'ScheduleDate' => $payData['ScheduleDate']
                                , 'ScheduleAmount' => $payData['ScheduleAmount']
                                , 'ScheduleType' => $payData['ScheduleType']));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            endforeach;

                            $paySchArray = $postData['newpaydate'];
                            for($i=0;$i<$agreeDuration;$i++){
                                $insert = $sql->insert();
                                $insert->into('PM_RentalPaymentScheduleTrans');
                                $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                , 'ScheduleDate' =>date('Y-m-d', strtotime($paySchArray[$i]))
                                , 'ScheduleAmount' => $monthlyRent
                                , 'ScheduleType' => "R"));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            //Other Charges added
                            $chargeCount = $this->bsf->isNullCheck($postData['charge_count'], 'number');
                            for ($i = 1; $i <= $chargeCount; $i++) {
                                if ($postData['charge_id_' . $i] != "" && $postData['charge_id_' . $i] != "0") {
                                    $chargeId = $this->bsf->isNullCheck($postData['charge_id_' . $i], 'number');
                                    $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                    $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                    , 'ServiceId' => $chargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    if ($postData['charge_name_' . $i] != "" || $postData['charge_name_' . $i] != 0) {

                                        $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                        $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                        $chargeName = $this->bsf->isNullCheck($postData['charge_name_' . $i], 'string');

                                        $insert = $sql->insert();
                                        $insert->into('PM_ServiceMaster');
                                        $insert->Values(array('ServiceName' => $chargeName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $newChargeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalOtherCostTrans');
                                        $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                        , 'ServiceId' => $newChargeId
                                        , 'Amount' => $chargeValue
                                        , 'TransType' => $chargeType));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }

                            //Checklist added
                            $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                            for ($i = 1; $i <= $checklistCount; $i++) {
                                if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                                    $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                    , 'ChecklistId' => $checklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    if ($postData['checklist_name_' . $i] != "" || $postData['checklist_name_' . $i] != 0) {

                                        $checklistName = $this->bsf->isNullCheck($postData['checklist_name_' . $i], 'string');

                                        $insert = $sql->insert();
                                        $insert->into('Proj_CheckListMaster');
                                        $insert->Values(array('CheckListName' => $checklistName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $newChecklistId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalCheckListTrans');
                                        $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                        , 'ChecklistId' => $newChecklistId));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }

                            //Document added
                            $documentCount = $this->bsf->isNullCheck($postData['document_count'], 'number');
                            for ($i = 1; $i <= $documentCount; $i++) {
                                if ($postData['document_name_' . $i] != "") {

                                    $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalDocumentTrans');
                                    $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                    , 'DocumentName' => $documentName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    if ($files['document_' . $i]['name']) {
                                        $dir = 'public/uploads/crm/agreement/';
                                        if (!is_dir($dir))
                                            mkdir($dir, 0755, true);

                                        $docExt = pathinfo($files['document_' . $i]['name'], PATHINFO_EXTENSION);
                                        $path = $dir . $TransId . '.' . $docExt;
                                        move_uploaded_file($files['document_' . $i]['tmp_name'], $path);

                                        $updateDocExt = $sql->update();
                                        $updateDocExt->table('PM_RentalDocumentTrans');
                                        $updateDocExt->set(array(
                                            'URL' => $this->bsf->isNullCheck($docExt, 'string')
                                        ))
                                            ->where(array('TransId' => $TransId));
                                        $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                        $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                }
                            }
                        } else if(isset($rentalType) && strcmp(trim($rentalType),"C") == 0) {

                            $update = $sql->update();
                            $update->table('PM_RentalRegister');
                            $update->set(array('Deactive' => 1));
                            $update->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            // Old Data of Agreement
                            $select = $sql->select();
                            $select->from("PM_RentalRegister")
                                ->columns(array('UnitId', 'Address', 'PANNo', 'IsPowerOfAttorney', 'PAName','AgreementNo', 'RefNo', 'RefDate', 'RentalType', 'PAAddress'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $ownerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            //Tenant Details
                            $select = $sql->select();
                            $select->from("PM_RentalTenantTrans")
                                ->columns(array('LeaserName', 'PANNo', 'Address','BusinessType','CompanyType','EstablishmentYear'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $tenantDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            //property Details
                            $select = $sql->select();
                            $select->from("PM_RentalPropertyTrans")
                                ->columns(array('Area', 'PropertyAddress','PropertyTransId'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $propertyDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            //Agreement Details
                            $select = $sql->select();
                            $select->from("PM_RentalAgreementTrans")
                                ->columns(array('AgtDate', 'LeasePeriod', 'LeasePeriodType', 'StartDate', 'IsNoticePeriod', 'NoticePeriod', 'NoticePeriodType','LockInPeriod','LockInPeriodType','RentFreePeriod','RentFreePeriodType'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $agreementDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            //Security Deposit
                            $select = $sql->select();
                            $select->from("PM_RentalRentTrans")
                                ->columns(array('SDBasedOn', 'SDAmount', 'IsPaymentSchedule', 'RenewalType','EscalationRentPercent','EscalationRentPeriod','EscalationRentPeriodType'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $secureDeposit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $select = $sql->select();
                            $select->from("PM_RentalPaymentScheduleTrans")
                                ->columns(array('ScheduleName', 'SchedulePer','TransId','ScheduleType'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $secureDepositSchedule = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                            $sDepositCount = count($this->_view->secureDepositSchedule);

                            //Rent Details
                            $select = $sql->select();
                            $select->from("PM_RentalRentTrans")
                                ->columns(array('RentAmount','RentPerPeriod', 'DueDay','GracePeriod','GracePeriodType','LateFeesBasedOn','LateFees','LateFeesType','MaximumLateFees'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $RentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            //Facilities
                            $select = $sql->select();
                            $select->from(array("a" => "PM_RentalFurnitureTrans"))
                                ->join(array('b' => 'PM_FurnitureMaster'), 'a.FurnitureId=b.FurnitureId', array('FurnitureName'), $select::JOIN_INNER)
                                ->columns(array('FurnitureId', 'Qty','TransId'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $furnitureDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                            $holdCount = count($this->_view->furnitureDetail);

                            //Amenity
                            $select = $sql->select();
                            $select->from(array("a" => "PM_RentalAmenityTrans"))
                                ->join(array('b' => 'PM_AmenityMaster'), 'a.AmenityId=b.AmenityId', array('AmenityName'), $select::JOIN_INNER)
                                ->columns(array('AmenityId','TransId'))
                                ->where("RentalRegisterId=$rentalRegisterId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $amenityDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                            $amenityCount = count($this->_view->amenityDetail);

                            //Add old Data

                            //Owner Info Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalRegister');
                            $insert->Values(array('UnitId' => $ownerDetail['UnitId']
                            , 'Address' => $ownerDetail['Address']
                            , 'PANNo' => $ownerDetail['PANNo']
                            , 'IsPowerOfAttorney' => $ownerDetail['IsPowerOfAttorney']
                            , 'PAName' => $ownerDetail['PAName']
                            , 'RefNo' => $refNo
                            , 'AgreementNo'=>$agreementNo
                            , 'RefDate' => $refDate
                            , 'RentalType' => $ownerDetail['RentalType']
                            , 'PAAddress' => $ownerDetail['PAAddress']
                            , 'PrevRentalRegisterId' => $rentalRegisterId
                            , 'TemplateId' => $templateId
                            , 'TemplateContent' => $templateContent));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $newRentalRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            //Tenant Info Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalTenantTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'LeaserName' => $tenantDetail['LeaserName']
                            , 'PANNo' => $tenantDetail['PANNo']
                            , 'Address' => $tenantDetail['Address']
                            , 'BusinessType' => $tenantDetail['BusinessType']
                            , 'CompanyType' => $tenantDetail['CompanyType']
                            , 'EstablishmentYear' => $tenantDetail['EstablishmentYear']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            //Property Details Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalPropertyTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'Area' => $propertyDetail['Area']
                            , 'PropertyAddress' => $propertyDetail['PropertyAddress']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //Rent and Security deposit
                            $monthlyRent = $this->bsf->isNullCheck($postData['monthlyrent'], 'number');
                            $dueDay = $this->bsf->isNullCheck($postData['dueday'], 'number');
                            $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'string');
                            $depositAmount = $this->bsf->isNullCheck($postData['depositamount'], 'number');
                            $agreeExpiration = $this->bsf->isNullCheck($postData['expiration'], 'string');

                            //Security deposit schedule added
                            foreach($secureDepositSchedule as $schData):
                                $insert = $sql->insert();
                                $insert->into('PM_RentalPaymentScheduleTrans');
                                $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                , 'ScheduleName' => $schData['ScheduleName']
                                , 'SchedulePer' => $schData['SchedulePer']
                                , 'ScheduleType' => "R"));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            endforeach;


                            $insert = $sql->insert();
                            $insert->into('PM_RentalRentTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'RentAmount' => $monthlyRent
                            , 'RentPerPeriod' => $RentDetail['RentPerPeriod']
                            , 'GracePeriod' => $RentDetail['GracePeriod']
                            , 'GracePeriodType' => $RentDetail['GracePeriodType']
                            , 'LateFeesBasedOn' => $RentDetail['LateFeesBasedOn']
                            , 'LateFees' => $RentDetail['LateFees']
                            , 'LateFeesType' => $RentDetail['LateFeesType']
                            , 'MaximumLateFees' => $RentDetail['MaximumLateFees']
                            , 'DueDay' => $dueDay
                            , 'IsSecurityDeposit' => $securityDeposit
                            , 'SDAmount' => $depositAmount
                            , 'SDBasedOn' => $secureDeposit['SDBasedOn']
                            , 'IsPaymentSchedule' => $secureDeposit['IsPaymentSchedule']
                            , 'EscalationRentPercent' => $secureDeposit['EscalationRentPercent']
                            , 'EscalationRentPeriod' => $secureDeposit['EscalationRentPeriod']
                            , 'EscalationRentPeriodType' => $secureDeposit['EscalationRentPeriodType']
                            , 'RenewalType' => $agreeExpiration));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            //Agreement Details Added
                            $insert = $sql->insert();
                            $insert->into('PM_RentalAgreementTrans');
                            $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                            , 'AgtDate' => $agreementDetail['AgtDate']
                            , 'LeasePeriod' => $agreeDuration
                            , 'LeasePeriodType' => $agreementDetail['LeasePeriodType']
                            , 'StartDate' => $agreementDetail['StartDate']
                            , 'EndDate' => $validityDate
                            , 'IsNoticePeriod' => $agreementDetail['IsNoticePeriod']
                            , 'NoticePeriod' => $agreementDetail['NoticePeriod']
                            , 'CostImpact' => $agreeCostChange
                            , 'NoticePeriodType' => $agreementDetail['NoticePeriodType']));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                            //Facilities added
                            foreach($furnitureDetail as $furData):
                                $insert = $sql->insert();
                                $insert->into('PM_RentalFurnitureTrans');
                                $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                , 'FurnitureId' => $furData['FurnitureId']
                                , 'Qty' => $furData['Qty']));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            endforeach;

                            //Amenity Added
                            foreach($amenityDetail as $amtyData):
                                $insert = $sql->insert();
                                $insert->into('PM_RentalAmenityTrans');
                                $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                , 'AmenityId' => $amtyData['AmenityId']));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            endforeach;

                            //Other Charges added
                            $chargeCount = $this->bsf->isNullCheck($postData['charge_count'], 'number');
                            for ($i = 1; $i <= $chargeCount; $i++) {
                                if ($postData['charge_id_' . $i] != "" && $postData['charge_id_' . $i] != "0") {
                                    $chargeId = $this->bsf->isNullCheck($postData['charge_id_' . $i], 'number');
                                    $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                    $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                    , 'ServiceId' => $chargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {
                                    if ($postData['charge_name_' . $i] != "" || $postData['charge_name_' . $i] != 0) {

                                        $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                        $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                        $chargeName = $this->bsf->isNullCheck($postData['charge_name_' . $i], 'string');

                                        $insert = $sql->insert();
                                        $insert->into('PM_ServiceMaster');
                                        $insert->Values(array('ServiceName' => $chargeName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $newChargeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalOtherCostTrans');
                                        $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                        , 'ServiceId' => $newChargeId
                                        , 'Amount' => $chargeValue
                                        , 'TransType' => $chargeType));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    }
                                }
                            }

                            //Checklist added
                            $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                            for ($i = 1; $i <= $checklistCount; $i++) {
                                if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                                    $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                    , 'ChecklistId' => $checklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    if ($postData['checklist_name_' . $i] != "" || $postData['checklist_name_' . $i] != 0) {

                                        $checklistName = $this->bsf->isNullCheck($postData['checklist_name_' . $i], 'string');

                                        $insert = $sql->insert();
                                        $insert->into('Proj_CheckListMaster');
                                        $insert->Values(array('CheckListName' => $checklistName));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $newChecklistId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalCheckListTrans');
                                        $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                        , 'ChecklistId' => $newChecklistId));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }

                            //Document added
                            $documentCount = $this->bsf->isNullCheck($postData['document_count'], 'number');
                            for ($i = 1; $i <= $documentCount; $i++) {
                                if ($postData['document_name_' . $i] != "") {

                                    $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalDocumentTrans');
                                    $insert->Values(array('RentalRegisterId' => $newRentalRegisterId
                                    , 'DocumentName' => $documentName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    if ($files['document_' . $i]['name']) {
                                        $dir = 'public/uploads/crm/agreement/';
                                        if (!is_dir($dir))
                                            mkdir($dir, 0755, true);

                                        $docExt = pathinfo($files['document_' . $i]['name'], PATHINFO_EXTENSION);
                                        $path = $dir . $TransId . '.' . $docExt;
                                        move_uploaded_file($files['document_' . $i]['tmp_name'], $path);

                                        $updateDocExt = $sql->update();
                                        $updateDocExt->table('PM_RentalDocumentTrans');
                                        $updateDocExt->set(array(
                                            'URL' => $this->bsf->isNullCheck($docExt, 'string')
                                        ))
                                            ->where(array('TransId' => $TransId));
                                        $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                        $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                }
                            }
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Renewal-Add','N','Lease-Renewal',$newRentalRegisterId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    }
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"));
                    }

//                    return $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"));
                } catch(PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $select = $sql->select();
                $select->from("PM_ServiceMaster")
                    ->columns(array('data' => 'ServiceId', 'value' => new Expression('ServiceName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->serviceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("Proj_CheckListMaster")
                    ->columns(array('data' => 'CheckListId', 'value' => new Expression('CheckListName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->checkList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Fetching data from agreement template
                $select = $sql->select();
                $select->from('PM_AgreementTemplate')
                    ->columns(array('TemplateId','TemplateName'))
                    ->where("AgreementTypeId = '3'")
                    ->where("DeleteFlag = '0'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->agreementTemplates = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->modeType = 'add';
                if($this->params()->fromRoute('mode')) {
                    $this->_view->modeType = $this->params()->fromRoute('mode');
                }

                $mode = $this->bsf->isNullCheck($this->_view->modeType, 'string');
                $rentalRegisterId = $this->bsf->isNullCheck($this->params()->fromRoute('rentalId'), 'number');
                $this->_view->rentalRegisterId=$rentalRegisterId;

                $select = $sql->select();
                $select->from("PM_RentalRegister")
                    ->columns(array('UnitId', 'RefNo', 'AgreementNo','RefDate','RentalType','TemplateId','TemplateContent'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $ownerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $ownerUnit = $ownerDetail['UnitId'];
                $this->_view->ownerDetail = $ownerDetail;
                $this->_view->ownerUnit = $ownerUnit;

                $select = $sql->select();
                $select->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadName'), $select::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array('UnitNo'), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                    ->columns(array('UnitId'))
                    ->where("a.UnitId=$ownerUnit");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->ownerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from("PM_RentalTenantTrans")
                    ->columns(array('LeaserName'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenantDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from("PM_RentalRentTrans")
                    ->columns(array('SDAmount','RentAmount'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->sDepositAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from("PM_RentalAgreementTrans")
                    ->columns(array('EndDate'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $endDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->eDate = $endDate['EndDate'];

                $select = $sql->select();
                $select->from("PM_RentalRentTrans")
                    ->columns(array('DueDay'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->dueDay = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from("PM_RentalRentTrans")
                    ->columns(array('RentAmount', 'DueDay', 'IsSecurityDeposit', 'SDAmount', 'RenewalType'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->secureRentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "PM_RentalOtherCostTrans"))
                    ->join(array('b' => 'PM_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('ServiceName'), $select::JOIN_INNER)
                    ->columns(array('ServiceId', 'Amount', 'TransType', 'TransId'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->otherDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                $this->_view->chargeCount = count($this->_view->otherDetail);

                $select = $sql->select();
                $select->from(array("a" => "PM_RentalCheckListTrans"))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.ChecklistId=b.ChecklistId', array('CheckListName'), $select::JOIN_INNER)
                    ->columns(array('ChecklistId','TransId'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->checklistDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                $this->_view->checklistCount = count($this->_view->checklistDetail);

                //Document
                $select = $sql->select();
                $select->from("PM_RentalDocumentTrans")
                    ->columns(array('DocumentName','TransId'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->documentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                $this->_view->documentCount = count($this->_view->documentDetail);

                $select = $sql->select();
                $select->from("PM_RentalAgreementTrans")
                    ->columns(array('LeasePeriod','EndDate','CostImpact'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->agreementDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"add") == 0) {


                } else if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"edit") == 0) {


                    $select = $sql->select();
                    $select->from("pm_rentalpaymentscheduletrans")
                        ->columns(array('ScheduleDate'))
                        ->where("RentalRegisterId=$rentalRegisterId AND ScheduleType='N'")
                        ->order('ScheduleDate DESC')->limit(1);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->prevSchDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }
            }
            $this->_view->mergeTags = $this->bsf->getMergeTags(3);
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }


    public function rentAction(){
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

        $unitId = $this->params()->fromRoute('unitId');
        $regId = $this->params()->fromRoute('regId');

        $this->_view->unitId = $unitId;
        $this->_view->regId = $regId;

        if(!isset($unitId) || $unitId == ""){
            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "entry"));
        }
        if(!isset($regId) || $regId == ""){
            $this->redirect()->toRoute("crm/deposit", array("controller" => "property","action" => "entry"));
        }

        $sql = new Sql($dbAdapter);

        if(isset($regId) && $regId !=""){
            $select = $sql->select();
            $select->from(array('a' => 'PM_PMRentOutTrans'))
                ->where(array("a.PMRegisterId"=>$regId));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->regInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        }

        $select = $sql->select();
        $select->from(array('a' => 'KF_UnitMaster'))
            ->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
            ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
            ->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
            ->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
            ->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
            ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
            ->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
            ->join(array('h' => 'crm_Unitbooking'), 'h.UnitId=a.UnitId', array('BuyerName' => 'BookingName'), $select::JOIN_LEFT)
            ->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
            ->where(array("a.UnitId"=>$unitId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postParams = $request->getPost();
                    $IsRentOut = $this->bsf->isNullCheck($postParams['availRentProp'],'number');
                    if($IsRentOut == 1){
                        $rentAmount = $this->bsf->isNullCheck($postParams['perMountAmount'],'number');
                        $isIncludeMMFees = $this->bsf->isNullCheck($postParams['availIncluded'],'number');
                        $dServiceAmt = $this->bsf->isNullCheck($postParams['serviceAmount'],'number');
                        $reason = "";
                    } else {
                        $reason = $this->bsf->isNullCheck($postParams['reason'],'string');
                        $rentAmount = 0;
                        $isIncludeMMFees = 0;
                        $dServiceAmt = 0;
                    }

                    $bankAccountNo = $this->bsf->isNullCheck($postParams['accountNo'],'number');
                    $bankAccountName = $this->bsf->isNullCheck($postParams['accountName'],'string');
                    $bankName = $this->bsf->isNullCheck($postParams['bankName'],'string');
                    $ifsc = $this->bsf->isNullCheck($postParams['ifsc'],'string');

                    if(isset($this->_view->regInfo) && !empty($this->_view->regInfo)){
                        $update = $sql->update();
                        $update->table('PM_PMRentOutTrans');
                        $update->set(array(
                            'IsRentOut' => $IsRentOut
                        , 'Reason' => $reason
                        , 'RentAmount' => $rentAmount
                        , 'IsIncludeMMFees' => $isIncludeMMFees
                        , 'ServiceAmount' => $dServiceAmt
                        , 'BankAccountNo' => $bankAccountNo
                        , 'BankAccountName' => $bankAccountName
                        , 'BankName' => $bankName
                        , 'IFSCCode' => $ifsc));
                        $update->where(array('PMRegisterId'=>$regId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $connection->commit();
                    } else {
                        $insert = $sql->insert();
                        $insert->into('PM_PMRentOutTrans');
                        $insert->Values(array('PMRegisterId' => $regId
                        , 'IsRentOut' => $IsRentOut
                        , 'Reason' => $reason
                        , 'RentAmount' => $rentAmount
                        , 'IsIncludeMMFees' => $isIncludeMMFees
                        , 'ServiceAmount' => $dServiceAmt
                        , 'BankAccountNo' => $bankAccountNo
                        , 'BankAccountName' => $bankAccountName
                        , 'BankName' => $bankName
                        , 'IFSCCode' => $ifsc));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $connection->commit();
                    }
                    if($regId!=""){
                        $this->redirect()->toRoute("crm/register", array("controller" => "property","action" => "register"));
                    } else {
                        $this->redirect()->toRoute("crm/rent", array("controller" => "property","action" => "rent","unitId"=>$unitId,"regId"=>$regId));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function agreementTemplateAction()
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
            $response = $this->getResponse();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                $result="";
                if ($mode == 'tempType') {
                    $tempType = $this->bsf->isNullCheck($postData['tempTypeVal'], 'number');
                    $mergeTags = $this->bsf->getMergeTags($tempType);
                    $result = json_encode($mergeTags);
                } else if($mode=="check") {
                    $tempId = $this->bsf->isNullCheck($postData['tempId'], 'number');
                    $tempTypeId = $this->bsf->isNullCheck($postData['tempTypeId'], 'number');

                    if($tempId!=0) {
                        $select = $sql->select();
                        $select->from('PM_AgreementTemplate')
                            ->columns(array("Count" => new Expression("Count(*)")))
                            ->where(array("TemplateName" => $postData['tempName'],"DeleteFlag"=>0,"AgreementTypeId"=>$tempTypeId));
                        $select->where("TemplateId <> '".$tempId."'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result = json_encode($result);
                    } else {
                        $select = $sql->select();
                        $select->from('PM_AgreementTemplate')
                            ->columns(array("Count" => new Expression("Count(*)")))
                            ->where(array("TemplateName" => $postData['tempName'],"DeleteFlag"=>0,"AgreementTypeId"=>$tempTypeId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $result = json_encode($result);
                    }
                }
                $this->_view->setTerminal(true);
                $response->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();
                    $mode = $this->bsf->isNullCheck($postData['mode'], 'number');
                    if($mode==0){

                        $insert = $sql->insert();
                        $insert->into('PM_AgreementTemplate');
                        $insert->Values(array('TemplateName' => $this->bsf->isNullCheck($postData['templateName'], 'string')
                        , 'AgreementTypeId' => $this->bsf->isNullCheck($postData['templateType'], 'number')
                        , 'TemplateContent' => $this->bsf->isNullCheck($postData['templateContent'], 'string')
                        , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')
                        ,'CreatedDate'=>date('Y-m-d H:i:s')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {

                        $update = $sql->update();
                        $update->table('PM_AgreementTemplate');
                        $update->set(array('TemplateName' => $this->bsf->isNullCheck($postData['templateName'], 'string')
                        , 'TemplateContent' => $this->bsf->isNullCheck($postData['templateContent'], 'string')
                        ,'ModifiedDate'=>date('Y-m-d H:i:s')));
                        $update->where(array("TemplateId"=>$mode));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();
                    $this->redirect()->toRoute('crm/default', array('controller' => 'property', 'action' => 'agreement-template-register'));

                } catch(PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                try {
                    $TempId = $this->bsf->isNullCheck($this->params()->fromRoute('TempId'), 'number');

                    if($TempId!=0) {
                        $select = $sql->select();
                        $select->from('PM_AgreementTemplate')
                            ->columns(array('*'))
                            ->where(array("TemplateId"=> $TempId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->TempDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }
                    $this->_view->mode=$TempId;
                    $select = $sql->select();
                    $select->from('Crm_AgreementTypeMaster')
                        ->columns(array('AgreementTypeId','AgreementTypeName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->templateTypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //Common function
                    $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                    return $this->_view;
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage(); die;
                }
            }
        }
    }

    public function registerAction(){
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
                $postParam = $request->getPost();
                $tname=$postParam['tname'];
                if($postParam['mode']=='renew'){
                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalRegister"))
                        ->columns(array('RentalRegisterId'))
                        ->where(array("a.PrevRentalRegisterId"=>$tname));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }
                else if($postParam['mode']=='cancel'){
                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalCancelRegister"))
                        ->columns(array('RentalCancelRegisterId'))
                        ->where(array("a.RentalRegisterId"=>$tname));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($results));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                //$leadId = $postParams['LeadId'];
                // Write your Normal form post code here
                //$this->redirect()->toRoute('crm/entry-edit', array('controller' => 'lead', 'action' => 'entry-edit', 'leadId' => $leadId));

            } else {
                $projectId = $this->params()->fromRoute('projectId');

                $where="";
                if(isset($projectId)){

                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'))
                        ->where(array("ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);

                    $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->projectId = $projectId;

                }

                $select = $sql->select();
                $select->from(array('a'=>'Crm_LeadProjects'))
                    ->join(array('b' => 'Crm_Leads'), 'a.LeadId=b.LeadId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId=c.ProjectId', array(), $select::JOIN_INNER)
                    ->columns(array('ProjectId','ProjectName'=>new expression("c.ProjectName")))
                    ->where(array('a.DeleteFlag' => 0))
                    ->group(new expression("a.ProjectId,c.ProjectName"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array('a' =>'PM_PMRegister'))
                    ->columns(array('IsReady' => new expression('count(IsReady)')));
                if(isset($projectId)){
                    $select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('b.ProjectId' => $projectId));
                }
                $select->where(array('IsReady'=>'1'));
                $select->where(array('a.CancelId'=>0));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->ReadyCount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' =>'PM_PMRegister'))
                    ->columns(array('PMRegisterId' => new expression('count(*)')));
                if(isset($projectId)){
                    $select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('b.ProjectId' => $projectId));
                }
                $select->where(array('a.CancelId'=>0));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->totalCount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' =>'PM_PMRentOutTrans'))
                    ->columns(array('IsRentOut' => new expression('count(IsRentOut)')));
                if(isset($projectId)){
                    $select -> join(array("b" => "PM_PMRegister"), new expression('a.PMRegisterId=b.PMRegisterId and b.CancelId=0'), array(), $select::JOIN_INNER);
                    $select -> join(array("c" => "KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('c.ProjectId' => $projectId));
                }
                $select	->where(array('IsRentOut'=>'1'));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->totalrentout = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' =>'PM_RentalRegister'))
                    ->columns(array('RentalRegisterId' => new expression('count(*)')));
                if(isset($projectId)){
                    $select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('b.ProjectId' => $projectId));
                }
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->totalrent = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' =>'PM_PMRegister'))
                    ->columns(array('PMRegisterId' => new expression('count(*)')));
                if(isset($projectId)){
                    $select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('b.ProjectId' => $projectId));
                }
                $select->where(array('a.FurnishType'=>'S'));
                $select->where(array('a.CancelId'=>0));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->totalSold = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' =>'PM_RentalRegister'))
                    ->columns(array('RentalRegisterId'));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->rentalcancel = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' =>'PM_PMFeesTrans'))
                    ->columns(array('Amount' => new expression('SUM(MMAmount)')));
                if(isset($projectId)){
                    $select -> join(array("b" => "PM_PMRegister"), new expression('a.PMRegisterId=b.PMRegisterId and b.CancelId=0'), array(), $select::JOIN_INNER);
                    $select -> join(array("c" => "KF_UnitMaster"), "c.UnitId=b.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('c.ProjectId' => $projectId));
                }
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->sum = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "PM_RentalAgreementTrans"))
                    ->columns(array('RentalRegisterId' => new expression('count(*)')));
                if(isset($projectId)){
                    $select -> join(array("b" => "PM_RentalRegister"), "a.RentalRegisterId=b.RentalRegisterId", array(), $select::JOIN_INNER);
                    $select -> join(array("c" => "KF_UnitMaster"), "b.UnitId=c.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('c.ProjectId' => $projectId));
                }
                $select->where(array("a.AgtDate between GetDate() and  GetDate()+7"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->Aggrementcount = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();

                $select = $sql->select();
                $select->from(array("a" => "PM_RentalAgreementTrans"))
                    ->join(array('e' => 'PM_RentalRegister'), 'a.RentalRegisterId=e.RentalRegisterId', array('RefNo'), $select::JOIN_LEFT)
                    ->join(array('c' => 'KF_UnitMaster'), 'c.UnitId=e.UnitId', array('UnitNo'), $select::JOIN_LEFT)
                    ->join(array('b' => 'PM_PMRegister'), new expression('e.UnitId=b.UnitId and b.CancelId=0'), array(), $select::JOIN_LEFT)
                    ->join(array('d' => 'Crm_UnitBooking'), 'e.UnitId=d.UnitId', array('BookingName','BookingDate'), $select::JOIN_LEFT)
                    ->join(array('f' => 'PM_RentalTenantTrans'), 'a.RentalRegisterId=f.RentalRegisterId', array('LeaserName',"Add"=>'Address'), $select::JOIN_LEFT)
                    ->where(array("a.AgtDate between GetDate() and  GetDate()+7"));
                if(isset($projectId)){
                    $select->where(array('c.ProjectId' => $projectId));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->Aggrement = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                $sql = new Sql($dbAdapter);

                $select = $sql->select();
                $select->from(array("a" => "KF_UnitMaster"))
                    ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_UnitBooking"), "e.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array('LeadName'), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                    ->join(array("f" => "PM_RentBillRegister"),"e.RentalRegisterId=f.RentalRegisterId",array('PVNo','RegisterId','PVDate','TotalAmountPayable'),$select::JOIN_LEFT)
                    ->columns(array('UnitNo','UnitId'))
                    ->where("f.DeleteFlag='0'");
                if(isset($projectId)){
                    $select->where(array('c.ProjectId' => $projectId));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->payList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "PM_RentBillRegister"))
                    ->columns(array('RegisterId' => new expression('count(*)')))
                    ->where(array('a.DeleteFlag' => 0));
                if(isset($projectId)){
                  //  $select -> join(array("b" => "PM_RentalRegister"), "a.RentalRegisterId=b.RentalRegisterId", array(), $select::JOIN_INNER);
                    $select -> join(array("c" => "KF_UnitMaster"), "a.UnitId=c.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('c.ProjectId' => $projectId));
                }
               $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->rentalcount = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();




                $select = $sql->select();
                $select->from(array("a" => "PM_PMRegister"))
                    ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array('UnitNo',), $select::JOIN_INNER)
                    ->join(array('g' => 'PM_PMRentOutTrans'), 'a.PMRegisterId=g.PMRegisterId', array('IsRentOut'), $select::JOIN_LEFT)
                    ->join(array('c' => 'PM_PMFeesTrans'), 'a.PMRegisterId=c.PMRegisterId', array('DepositAmount','MMAmount'), $select::JOIN_LEFT)
                    ->join(array('d' => 'Crm_UnitBooking'), new expression('a.UnitId=d.UnitId and d.DeleteFlag=0'), array('BookingName','BookingDate'), $select::JOIN_INNER)
                    ->order('a.PMRegisterId desc');
                $select->where(array('a.CancelId' => 0));
                if(isset($projectId)){
                    $select->where(array('b.ProjectId' => $projectId));
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $registervalue = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                foreach($registervalue as &$data){
                    if ($data['IsRentOut']==1){
                        $select = $sql->select();
                        $select->from(array("a" => "PM_RentalRegister"))
                            // ->join(array('e' => 'PM_RentBillRegister'), 'e.RentalRegisterId=a.RentalRegisterId', ('TotalAmountPayable'), $select::JOIN_INNER)
                            ->join(array('b' => 'PM_RentalRentTrans'), 'b.RentalRegisterId=a.RentalRegisterId', array('RentAmount','DueDay','GracePeriod','LateFees','SDAmount','RenewalType'), $select::JOIN_INNER)
                            ->join(array('c' => 'PM_RentalAgreementTrans'), 'a.RentalRegisterId=c.RentalRegisterId', array('AgtDate','StartDate','LeasePeriod'), $select::JOIN_INNER)
                            ->join(array('d' => 'PM_RentalTenantTrans'), 'a.RentalRegisterId=d.RentalRegisterId', array('LeaserName',"Add"=>'Address','TransId'), $select::JOIN_INNER)
                            ->where(array("a.UnitId"=>$data['UnitId']));
                        if(isset($projectId)){
                            $select -> join(array("e" => "KF_UnitMaster"), "a.UnitId=e.UnitId", array(), $select::JOIN_INNER);
                            $select->where(array('e.ProjectId' => $projectId));
                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $data['rental'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                    $select1 = $sql->select();
                    $select1->from(array("a" => "PM_RentBillRegister"))
                         ->join(array('e' => 'Crm_ReceiptRegister'), new Expression("a.UnitId=e.UnitId and e.ReceiptAgainst='R'"), ('Amount'), $select::JOIN_INNER)
                        ->where(array("a.UnitId"=>$data['UnitId'],"a.DeleteFlag"=>0));
                    $select1 ->order('a.RegisterId desc');
                    if(isset($projectId)){
                        $select1 -> join(array("e" => "KF_UnitMaster"), "a.UnitId=e.UnitId", array(), $select::JOIN_INNER);
                        $select1->where(array('e.ProjectId' => $projectId));
                    }
                    $statement = $sql->getSqlStringForSqlObject($select1);
                    $data['rentalBill'] = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    }
                }

                $this->_view->registervalue = $registervalue;


            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function getTemplateContentAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('PM_AgreementTemplate')
                    ->columns(array('TemplateContent'))
                    ->where("TemplateId = '".$postParams['tempId']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function maintenanceBillAction() {

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $connection->commit();
                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $postData = $request->getPost();

                    $registerId = $this->bsf->isNullCheck($postData['RegisterId'], 'number');
                    $refDate = $this->bsf->isNullCheck($postData['RefDate'], 'string');
                    if(strtotime($refDate) == FALSE){
                        $refDate = date('Y-m-d');
                    } else {
                        $refDate = date('Y-m-d', strtotime($refDate));
                    }

                    $fromDate = $this->bsf->isNullCheck($postData['FromDate'], 'string');
                    if(strtotime($fromDate) == FALSE) {
                        $fromDate = '';
                    } else {
                        $fromDate = date('Y-m-d', strtotime($fromDate));
                    }

                    $toDate = $this->bsf->isNullCheck($postData['ToDate'], 'string');
                    if(strtotime($toDate) == FALSE) {
                        $toDate = '';
                    } else {
                        $toDate = date('Y-m-d', strtotime($toDate));
                    }

                    $arrServices = array();

                    foreach($postData as $key => $data) {
                        if(preg_match('/^service_[a-z0-9]+/i', $key)) {
                            // services
                            $arrMatches = explode('_', $key);
                            $arrServices[$arrMatches[1]][$arrMatches[2]] = $data;
                        }
                    }

                    $sVno= $this->bsf->isNullCheck($postData['RefNo'], 'string');
                    $aVNo = CommonHelper::getVoucherNo(808, date('Y-m-d', strtotime($postData['campaigndate'])), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == true) {
                        $sVno = $aVNo["voucherNo"];
                    }

                    if($registerId == 0) {
                        // insert
                        $insert = $sql->insert();
                        $insert->into('PM_MaintenanceBillRegister')
                            ->values(array(
                                'RefDate' => $refDate,
                                'RefNo' => $sVno,
                                'UnitId' => $this->bsf->isNullCheck($postData['UnitId'], 'number'),
                                'FromDate' => $fromDate,
                                'ToDate' => $toDate,
                                'GrossAmount' => $this->bsf->isNullCheck($postData['GrossAmount'], 'number'),
                                'QualAmount' => $this->bsf->isNullCheck($postData['QualAmount'], 'number'),
                                'NetAmount' => $this->bsf->isNullCheck($postData['NetAmount'], 'number'),
                                'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string'),
                                'CreatedDate' => date('Y-m-d H:i:s')
                            ));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        foreach($arrServices as $service) {
                            $serviceId = $this->bsf->isNullCheck($service['Id'], 'number');
                            if($serviceId == 0) {
                                continue;
                            }

                            $insert = $sql->insert();
                            $insert->into( 'PM_MaintenanceBillTrans' )
                                ->values(array(
                                    'RegisterId' => $registerId,
                                    'ServiceId' => $serviceId,
                                    'Type' => $this->bsf->isNullCheck($service['Type'], 'string'),
                                    'Qty' => $this->bsf->isNullCheck($service['Qty'], 'number'),
                                    'Rate' => $this->bsf->isNullCheck($service['Rate'], 'number'),
                                    'Amount' => $this->bsf->isNullCheck($service['Amount'], 'number')
                                ));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-MaintenanceBill-Entry-Add','N','CRM-MaintenanceBill-Entry',$registerId,0, 0, 'CRM', $sVno,$userId, 0 ,0);

                    } else {
                        // update
                        $update = $sql->update();
                        $update->table('PM_MaintenanceBillRegister')
                            ->set(array(
                                'RefDate' => $refDate,
                                'RefNo' => $sVno,
                                'UnitId' => $this->bsf->isNullCheck($postData['UnitId'], 'number'),
                                'LeadId' => $this->bsf->isNullCheck($postData['LeadId'], 'number'),
                                'FromDate' => $fromDate,
                                'ToDate' => $toDate,
                                'GrossAmount' => $this->bsf->isNullCheck($postData['GrossAmount'], 'number'),
                                'QualAmount' => $this->bsf->isNullCheck($postData['QualAmount'], 'number'),
                                'NetAmount' => $this->bsf->isNullCheck($postData['NetAmount'], 'number'),
                                'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')
                            ))
                            ->where(array('RegisterId' => $registerId));
                        $stmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('PM_MaintenanceBillTrans')
                            ->where(array('RegisterId' => $registerId));
                        $stmt = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        foreach($arrServices as $service) {
                            $serviceId = $this->bsf->isNullCheck($service['Id'], 'number');
                            if($serviceId == 0) {
                                continue;
                            }

                            $insert = $sql->insert();
                            $insert->into( 'PM_MaintenanceBillTrans' )
                                ->values(array(
                                    'RegisterId' => $registerId,
                                    'ServiceId' => $serviceId,
                                    'Type' => $this->bsf->isNullCheck($service['Type'], 'string'),
                                    'Qty' => $this->bsf->isNullCheck($service['Qty'], 'number'),
                                    'Rate' => $this->bsf->isNullCheck($service['Rate'], 'number'),
                                    'Amount' => $this->bsf->isNullCheck($service['Amount'], 'number')
                                ));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-MaintenanceBill-Entry-Modify','E','CRM-MaintenanceBill-Entry',$registerId,0, 0, 'CRM', $sVno,$userId, 0 ,0);

                    }
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute("crm/default", array("controller" => "property", "action" => "maintenance-bill-register"),array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute("crm/default", array("controller" => "property", "action" => "maintenance-bill-register"));

                    }
//                    $this->redirect()->toRoute("crm/default", array("controller" => "property", "action" => "maintenance-bill-register"));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $registerId = $this->bsf->isNullCheck($this->params()->fromRoute('registerId'), 'number');
                    if($registerId != 0) {
                        // update

                        $this->_view->registerId = $registerId;

                        $select = $sql->select();
                        $select->from(array('a' => 'PM_MaintenanceBillRegister'))
                            ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectMaster'), 'c.ProjectId=b.ProjectId', array('ProjectId'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Crm_Leads'), 'd.LeadId=a.LeadId', array(), $select::JOIN_LEFT)
                            ->columns(array('BuyerName' => new Expression("c.ProjectName + ' - ' + b.UnitNo + ' - ' + d.LeadName"),
                                '*'))
                            ->where(array('RegisterId' => $registerId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $MBRegister = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if(!empty($MBRegister)) {
                            $this->_view->MBRegister = $MBRegister;
                        }

                    }

                    // units
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitBooking'))
                        ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectMaster'), 'c.ProjectId=b.ProjectId', array('ProjectId'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Crm_Leads'), 'd.LeadId=a.LeadId', array(), $select::JOIN_LEFT)
                        ->columns(array('BuyerName' => new Expression("c.ProjectName + ' - ' + b.UnitNo + ' - ' + d.LeadName"),
                            'UnitId'))
                        ->where(array('a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(!empty($arrUnits)) {
                        $this->_view->arrUnits = $arrUnits;
                    }

                    $aVNo = CommonHelper::getVoucherNo(808, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->genType = $aVNo["genType"];
                    if ($aVNo["genType"] == false)
                        $this->_view->svNo = "";
                    else
                        $this->_view->svNo = $aVNo["voucherNo"];

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage(); die;
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    // AJAX Request
    public function maintenanceServicesAction() {

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here
                    $postData = $request->getPost();

                    $ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number');
                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $UnitId = $this->bsf->isNullCheck($postData['UnitId'], 'number');
                    if($UnitId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }

                    $FromDate = $this->bsf->isNullCheck($postData['FromDate'], 'string');
                    if(strtotime($FromDate) == FALSE) {
                        throw new \Exception('Invalid From-Date!');
                    }
                    $ToDate = $this->bsf->isNullCheck($postData['ToDate'], 'string');
                    if(strtotime($ToDate) == FALSE) {
                        throw new \Exception('Invalid To-Date!');
                    }

                    if(strtotime($FromDate) > strtotime($ToDate)) {
                        throw new \Exception('To-Date should be lessar than From-Date!');
                    }

                    $RegisterId = $this->bsf->isNullCheck($postData['RegisterId'], 'number');

                    $arrUnitServices = array();
                    if($RegisterId != 0) {
                        $select = $sql->select();
                        $select->from( array( 'a' => 'PM_MaintenanceBillTrans' ) )
                            ->join( array( 'b' => 'PM_MaintenanceBillRegister' ), 'a.RegisterId=b.RegisterId', array(), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'PM_ServiceMaster' ), 'c.ServiceId=a.ServiceId', array( 'ServiceName' ), $select::JOIN_LEFT )
                            ->where( array( 'b.RegisterId' => $RegisterId ) );
                        $stmt = $sql->getSqlStringForSqlObject( $select );
                        $arrUnitServices = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    } else {
                        $select = $sql->select();
                        $select->from( array( 'a' => 'Crm_ServiceDoneTrans' ) )
                            ->columns(array('ServiceId', 'Rate' => 'Amount', 'Qty' => new Expression("'0'"), 'Amount' => new Expression("'0'")))
                            ->join( array( 'b' => 'Crm_ServiceDone' ), 'a.ServiceDoneRegId=b.ServiceDoneRegId', array(), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'PM_ServiceMaster' ), 'c.ServiceId=a.ServiceId', array( 'ServiceName' ), $select::JOIN_LEFT )
                            ->join( array( 'd' => 'PM_PMUncoveredServiceTrans' ), 'd.ServiceId=a.ServiceId', array( 'Type' => 'TransType' ), $select::JOIN_LEFT )
                            ->join( array( 'e' => 'PM_PMRegister' ), 'e.PMRegisterId=d.PMRegisterId', array(), $select::JOIN_LEFT )
                            ->where( array( 'b.UnitId' => $UnitId , 'e.UnitId' => $UnitId) );
                        $stmt = $sql->getSqlStringForSqlObject( $select );
                        $arrUnitServices = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        if(!empty($arrUnitServices)) {

                            $timeDifference = strtotime($ToDate) - strtotime($FromDate);
                            $noOfDays = ceil($timeDifference / (60 * 60 * 24));
                            $noOfWeeks = ceil($noOfDays / 7);
                            $noOfMonths = ceil($noOfDays / 30);

//                            echo 'No of Days => ' . $noOfDays;
//                            echo 'No of Weeks => ' . $noOfWeeks;
//                            echo 'No of Months => ' . $noOfMonths;
                            foreach ( $arrUnitServices as &$unitService ) {
                                switch ( $unitService[ 'Type' ] ) {
                                    case 'D':
                                        // Daily
                                        $unitService['Qty'] = $noOfDays;
                                        break;
                                    case 'W':
                                        // Weekly
                                        $unitService['Qty'] = $noOfWeeks;
                                        break;
                                    case 'M':
                                        // Monthly
                                        $unitService['Qty'] = $noOfMonths;
                                        break;
                                    case 'R':
                                        // Reading
                                        $unitService['Qty'] = 1;
                                        break;
                                    case 'Q':
                                        // Required
                                        $unitService['Qty'] = 1;
                                        break;
                                }

                                if($unitService['Qty'] != 0 && $unitService['Rate'] != 0) {
                                    $unitService['Amount'] = $unitService['Qty'] * $unitService['Rate'];
                                }
                            }
                        }
                    }
                    $returnData = array(
                        'unitServices' => $arrUnitServices
                    );

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ProjUncoveredServiceTrans'))
                        ->columns(array('data' => 'ServiceId', 'Amount', 'Type'))
                        ->join(array('b' => 'PM_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('value' => 'ServiceName'), $select::JOIN_LEFT)
                        ->where(array('ProjectId' => $ProjectId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrProjServices = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $returnData['ProjectServices'] = $arrProjServices;

                    $result =  json_encode($returnData);
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }
    public function maintenancePrintAction(){
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

        $mainId = $this->bsf->isNullCheck($this->params()->fromRoute('MaintainId'), 'number');
        // Print_r($mainId);die;
        if($mainId == 0) {
            $this->redirect()->toRoute("crm/default", array("controller" => "bill","action" => "progress-register"));
        }
        $sql = new Sql($dbAdapter);
        $selectStage = $sql->select();
        $selectStage->from(array("a"=>"PM_MaintenanceBillRegister"))
            ->where(array('a.RegisterId' => $mainId,'a.DeleteFlag'=>0));
        $statement = $sql->getSqlStringForSqlObject($selectStage);
        $maintainBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
        //unitTable
        $this->_view->progressBill = $maintainBill;
        $selectUnit = $sql->select();
        $selectUnit->from(array("a"=>"PM_MaintenanceBillRegister"))
            ->join(array("b"=>"PM_MaintenanceBillTrans"), "a.RegisterId=b.RegisterId", array(), $selectUnit::JOIN_INNER)
            // ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $selectUnit::JOIN_LEFT)
            ->join(array("e"=>"KF_UnitMaster"), "a.UnitId=e.UnitId", array('UnitNo'), $selectUnit::JOIN_INNER)
            ->join(array("f"=>"Crm_UnitBooking"), "f.UnitId=a.UnitId", array('BuyerName' => 'BookingName'), $selectStage::JOIN_INNER)
            ->join(array("g"=>"Crm_Leads"), "g.LeadId=f.LeadId", array('Mobile', 'Email','LeadName'), $selectStage::JOIN_INNER)
            ->join(array("h" => "Proj_ProjectMaster"), "e.ProjectId=h.ProjectId", array('*'), $selectStage::JOIN_INNER)
            ->join(array("i" => "WF_CompanyMaster"), "i.CompanyId=h.CompanyId", array('CompanyName'=>'CompanyName','CompanyAddress'=>'Address','CompanyEMail'=>'Email','CompanyMobile'=>'Mobile'), $selectStage::JOIN_LEFT)
            ->join(array("j"=>"Crm_UnitType"), "j.UnitTypeId=e.UnitTypeId", array('CreditDays', 'IntPercent'), $selectStage::JOIN_INNER)
            ->where(array('a.RegisterId' => $mainId,'a.DeleteFlag'=>0));
        $statement = $sql->getSqlStringForSqlObject($selectUnit);
        $selectUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if(empty($selectUnit)) {
            throw new \Exception('Bill not found!');
        }
        $SelectReceiptType = $sql->select();
        $SelectReceiptType->from(array("a"=>"PM_MaintenanceBillTrans"))
            ->join(array("b"=>"PM_MaintenanceBillRegister"), "a.RegisterId=b.RegisterId", array('*'), $SelectReceiptType::JOIN_INNER)
            ->join(array("c"=>"PM_ServiceMaster"), "a.ServiceId=c.ServiceId", array('ServiceName'), $SelectReceiptType::JOIN_INNER)
            ->where(array('a.RegisterId' => $mainId));
        $statement = $sql->getSqlStringForSqlObject($SelectReceiptType);
        $maintainBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $pdfhtml = $this->generateMaintenanceBillPdf($selectUnit, $maintainBill);
        // $pdfhtml;
        require_once("/vendor/dompdf/dompdf/dompdf_config.inc.php");
        $dompdf = new DOMPDF();

        $dompdf->load_html($pdfhtml);
        $dompdf->set_paper("A4");
        $dompdf->render();
        //$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
        $canvas = $dompdf->get_canvas();
        $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

        $dompdf->stream("MaintenanceBill.pdf");
    }

    private function generateMaintenanceBillPdf( $selectUnit, $maintainBill) {

        $pdfhtml = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,400italic,600,600italic,700,700italic,800,300,300italic,800italic' rel='stylesheet' type='text/css'>
<title>PDF</title>
<style>
.clearfix:after {
content: "";
display: table;
clear: both;
}

a {
text-decoration: underline;
}

body {
position: relative;
width: 612px;
margin: 0 auto;
color: #001028;
background: #FFFFFF;
font-size: 12px;
font-family: "Open Sans",sans-serif;
}

header {
padding: 10px 0;
}

#logo {
text-align: center;
margin-bottom: 10px;
}

#logo img {
width: 90px;
}

h1 {
border-top: 1px solid  #5D6975;
border-bottom: 1px solid  #5D6975;
color: #5D6975;
font-size: 2.4em;
line-height: 1.4em;
font-weight: normal;
text-align: center;
margin: 0 0 20px 0;
background: url(dimension.png);
}

.project {float: left;}
.project span {color: #5D6975;text-align: right;width: 87px;margin-right: 10px;display: inline-block;font-size: 14px;}
.company {float: right;text-align: right;font-size: 14px;}
.project div,.company div {white-space: nowrap;}

2
.project2 {float:right;}
.widspn span {width: 164px;}
.project2 span {
color: #5D6975;
text-align: right;
width: 300px;
margin-right: 10px;
display: inline-block;
font-size: 14px;
}
.pd10{ padding:2px;}

table {
width: 100%;
border-collapse: collapse;
border-spacing: 0;
margin-bottom: 20px;

page-break-inside:auto;
}

table tr {
page-break-inside:avoid;
page-break-after:auto;
}

table tr:nth-child(2n-1) td {
background: #F5F5F5;
}

table th,
table td {
text-align: center;
}

table th {
padding: 5px 20px;
color: #5D6975;
border-bottom: 1px solid #C1CED9;
white-space: nowrap;
font-weight: 600;
}

table .service,
table .desc {
text-align: left;
}

table td {
padding: 5px;
text-align: right;
}

table td.service,
table td.desc {
vertical-align: top;
}

table td.unit,
table td.qty,
table td.total {
font-size: 15px;}
notices .notice {
color: #5D6975;
font-size: 1.2em;
}

.project.widspn {
page-break-after: always;
}
.project.widspn:last-of-type {
page-break-after: avoid;
}

footer {
color: #5D6975;
width: 100%;
height: 30px;
position: absolute;
bottom: 0;
border-top: 1px solid #C1CED9;
padding: 8px 0;
text-align: center;
}
</style>
</head>
<body style="border:1px solid #000;">
	<header class="clearfix" style="padding-left:10px; padding-right:10px;">
        <div id="logo">

        </div>
        <h1>Bulidsuperfast Invoicebill</h1>
    </header>
    <div align="center" style="width:100%;">
        <table align="center" style="width:98%;">
            <tbody>
                <tr>
                    <td style="text-align:left !important;">
                        <div class="project">
                            <div class="pd10"><span>REF No. : </span>{$selectUnit['RefNo']}</div>
                            <div class="pd10"><span>CLIENT : </span>{$selectUnit['LeadName']} </div>
                            <div class="pd10"><span>Unit No. : </span>{$selectUnit['UnitNo']}</div>
                            <div class="pd10"><span>EMAIL : </span>{$selectUnit['Email']}</div>
                            <div class="pd10"><span>MOBILE No. : </span>{$selectUnit['Mobile']}</div>
                            <div class="pd10"><span>DATE : </span>{$selectUnit['RefDate']}</div>
                        </div>
                    </td>
                    <td>
                        <div class="company" style="padding: 0px !important">
                            <div >{$selectUnit['CompanyName']}</div>
                            <div>{$selectUnit['CompanyAddress']}</div>
                            <div>{$selectUnit['CompanyMobile']}</div>
                            <div>{$selectUnit['CompanyEMail']}</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <main>
        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
            <p style="padding: 0 15px ; ">This is with reference to the booking of your unit in our Project <span style="color:#000;">{$selectUnit['ProjectName']}.</span> We are pleased to inform you that we have completed the below Stage.</p>
        </div>
        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
            <p style="padding: 0 15px ; ">You are therefore requested to make the payment before the due date.</p>
        </div>
        <div align="center" style="width:100%;">
            <table align="center" style="width:98%;">
                <thead  style="font-size: 16px ! important; ">
                    <tr>
                        <th class="desc">ServiceName</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
EOT;
        foreach($maintainBill as $maintainBill) {
            $pdfhtml .= <<<EOT
                    <tr>
                        <td class="desc">{$maintainBill['ServiceName']}</td>
                        <td class="unit">{$maintainBill['Qty']}</td>
                        <td class="qty">{$maintainBill['Rate']}</td>
                        <td class="total">{$maintainBill['Amount']}</td>
                    </tr>
EOT;
        }
        $pdfhtml .= <<<EOT
					<tr>
                        <td colspan="3" >Tax Payable</td>
                        <td class="total"><b>{$maintainBill['QualAmount']} </b></td>
                    </tr>
                    <tr>
                        <td colspan="3" >Net Payable</td>
                        <td class="total"><b>{$selectUnit['NetAmount']}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="project widspn" style="margin-top:20px; width:100%;"></div>
        <div class="clearfix"></div>
    </main>
    <div style="font-size: 15px; color:#4E4C4C; padding:10px 15px;">
        <p>Thanking you and assuring of our best services at all times.</p>
        <p style="padding: 0 15px ; ">For {$selectUnit['CompanyName']}</p>
        <p style="padding: 0 15px ; "><b>(Authorised Signatory)</b></p>
    </div>
</body>
</html>
EOT;
        return $pdfhtml;
    }
    public function inventoryBillAction() {

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $connection->commit();
                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $postData = $request->getPost();

                    $registerId = $this->bsf->isNullCheck($postData['RegisterId'], 'number');
                    $refDate = $this->bsf->isNullCheck($postData['RefDate'], 'string');
                    if(strtotime($refDate) == FALSE){
                        $refDate = date('Y-m-d');
                    } else {
                        $refDate = date('Y-m-d', strtotime($refDate));
                    }

                    $arrServices = array();

                    foreach($postData as $key => $data) {
                        if(preg_match('/^service_[a-z0-9]+/i', $key)) {
                            // services
                            $arrMatches = explode('_', $key);
                            $arrServices[$arrMatches[1]][$arrMatches[2]] = $data;
                        }
                    }

                    $sVno= $this->bsf->isNullCheck($postData['RefNo'], 'string');
                    $aVNo = CommonHelper::getVoucherNo(820, date('Y-m-d', strtotime($postData['campaigndate'])), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == true) {
                        $sVno = $aVNo["voucherNo"];
                    }

                    if($registerId == 0) {
                        // insert
                        $insert = $sql->insert();
                        $insert->into('PM_InventoryBillRegister')
                            ->values(array(
                                'RefDate' => $refDate,
                                'RefNo' => $sVno,
                                'UnitId' => $this->bsf->isNullCheck($postData['UnitId'], 'number'),
                                'LeadId' => $this->bsf->isNullCheck($postData['LeadId'], 'number'),
                                'GrossAmount' => $this->bsf->isNullCheck($postData['GrossAmount'], 'number'),
                                'QualAmount' => $this->bsf->isNullCheck($postData['QualAmount'], 'number'),
                                'NetAmount' => $this->bsf->isNullCheck($postData['NetAmount'], 'number'),
                                'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string'),
                                'CreatedDate' => date('Y-m-d H:i:s')
                            ));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        foreach($arrServices as $service) {
                            $serviceId = $this->bsf->isNullCheck($service['Id'], 'number');
                            if($serviceId == 0) {
                                continue;
                            }

                            $insert = $sql->insert();
                            $insert->into( 'PM_InventoryBillTrans' )
                                ->values(array(
                                    'RegisterId' => $registerId,
                                    'MaterialId' => $serviceId,
                                    'Qty' => $this->bsf->isNullCheck($service['Qty'], 'number'),
                                    'Rate' => $this->bsf->isNullCheck($service['Rate'], 'number'),
                                    'Amount' => $this->bsf->isNullCheck($service['Amount'], 'number')
                                ));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-InventoryBill-Entry-Add','N','CRM-InventoryBill-Entry',$registerId,0, 0, 'CRM', $sVno,$userId, 0 ,0);

                    } else {
                        // update
                        $update = $sql->update();
                        $update->table('PM_InventoryBillRegister')
                            ->set(array(
                                'RefDate' => $refDate,
                                'RefNo' => $sVno,
                                'UnitId' => $this->bsf->isNullCheck($postData['UnitId'], 'number'),
                                'GrossAmount' => $this->bsf->isNullCheck($postData['GrossAmount'], 'number'),
                                'QualAmount' => $this->bsf->isNullCheck($postData['QualAmount'], 'number'),
                                'NetAmount' => $this->bsf->isNullCheck($postData['NetAmount'], 'number'),
                                'Remarks' => $this->bsf->isNullCheck($postData['Remarks'], 'string')
                            ))
                            ->where(array('RegisterId' => $registerId));
                        $stmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('PM_InventoryBillTrans')
                            ->where(array('RegisterId' => $registerId));
                        $stmt = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        foreach($arrServices as $service) {
                            $serviceId = $this->bsf->isNullCheck($service['Id'], 'number');
                            if($serviceId == 0) {
                                continue;
                            }

                            $insert = $sql->insert();
                            $insert->into( 'PM_InventoryBillTrans' )
                                ->values(array(
                                    'RegisterId' => $registerId,
                                    'MaterialId' => $serviceId,
                                    'Qty' => $this->bsf->isNullCheck($service['Qty'], 'number'),
                                    'Rate' => $this->bsf->isNullCheck($service['Rate'], 'number'),
                                    'Amount' => $this->bsf->isNullCheck($service['Amount'], 'number')
                                ));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-InventoryBill-Entry-Modify','E','CRM-InventoryBill-Entry',$registerId,0, 0, 'CRM', $sVno,$userId, 0 ,0);

                    }
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute("crm/register", array("controller" => "property", "action" => "inventory-bill-register"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute("crm/register", array("controller" => "property", "action" => "inventory-bill-register"));
                    }
//                    $this->redirect()->toRoute("crm/register", array("controller" => "property", "action" => "inventory-bill-register"));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $registerId = $this->bsf->isNullCheck($this->params()->fromRoute('registerId'), 'number');
                    if($registerId != 0) {
                        // update

                        $this->_view->registerId = $registerId;

                        $select = $sql->select();
                        $select->from(array('a' => 'PM_InventoryBillRegister'))
                            ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectMaster'), 'c.ProjectId=b.ProjectId', array('ProjectId'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Crm_Leads'), 'd.LeadId=a.LeadId', array(), $select::JOIN_LEFT)
                            ->columns(array('BuyerName' => new Expression("c.ProjectName + ' - ' + b.UnitNo + ' - ' + d.LeadName"),
                                '*'))
                            ->where(array('RegisterId' => $registerId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $InvRegister = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if(!empty($InvRegister)) {
                            $this->_view->InvRegister = $InvRegister;
                        }

                    }

                    // units
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitBooking'))
                        ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectMaster'), 'c.ProjectId=b.ProjectId', array('ProjectId'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Crm_Leads'), 'd.LeadId=a.LeadId', array(), $select::JOIN_LEFT)
                        ->columns(array('BuyerName' => new Expression("c.ProjectName + ' - ' + b.UnitNo + ' - ' + d.LeadName"),
                            'UnitId', 'LeadId'))
                        ->where(array('a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(!empty($arrUnits)) {
                        $this->_view->arrUnits = $arrUnits;
                    }

                    $aVNo = CommonHelper::getVoucherNo(820, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->genType = $aVNo["genType"];
                    if ($aVNo["genType"] == false)
                        $this->_view->svNo = "";
                    else
                        $this->_view->svNo = $aVNo["voucherNo"];

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage(); die;
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    // AJAX Request
    public function inventoryServicesAction() {

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here
                    $postData = $request->getPost();

                    $ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number');
                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $UnitId = $this->bsf->isNullCheck($postData['UnitId'], 'number');
                    if($UnitId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }

                    $RegisterId = $this->bsf->isNullCheck($postData['RegisterId'], 'number');

                    $arrExistsInvServices = array();
                    if($RegisterId != 0) {
                        $select = $sql->select();
                        $select->from( array( 'a' => 'PM_InventoryBillTrans' ) )
                            ->join( array( 'b' => 'Proj_Resource' ), 'a.MaterialId=b.ResourceId', array('MaterialName' => 'ResourceName'), $select::JOIN_LEFT )
                            ->join( array( 'c' => 'Proj_UOM' ), 'c.UnitId=b.UnitId', array( 'MUnitName' => 'UnitName' ), $select::JOIN_LEFT )
                            ->where( array( 'a.RegisterId' => $RegisterId ) );
                        $stmt = $sql->getSqlStringForSqlObject( $select );
                        $arrExistsInvServices = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    }
                    $returnData = array(
                        'existsInvServices' => $arrExistsInvServices
                    );

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array('data' => 'ResourceId', 'value' => 'ResourceName', 'Rate'))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('MUnitName' => 'UnitName'), $select::JOIN_LEFT);
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrInvServices = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $returnData['InventoryServices'] = $arrInvServices;

                    $result =  json_encode($returnData);
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }

    public function maintenanceBillRegisterAction() {

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $connection->commit();
                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $select = $sql->select();
                    $select->from(array('a' => 'PM_MaintenanceBillRegister'))
                        ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array('UnitName' => 'UnitNo'), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrMaintenanceBills = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->jsonMaintenanceBills = json_encode($arrMaintenanceBills);

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function inventoryBillRegisterAction() {

        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $connection->commit();
                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        } else {
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $select = $sql->select();
                    $select->from(array('a' => 'PM_InventoryBillRegister'))
                        ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array('UnitName' => 'UnitNo'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Crm_Leads'), 'c.LeadId=a.LeadId', array('LeadName'), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrInvBills = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->jsonInvBills = json_encode($arrInvBills);

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function servicedoneAction(){
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
        //$iQualCount = 0;
        $sql = new Sql($dbAdapter);
        $userId = $this->auth->getIdentity()->UserId;
        $this->_view->arrVNo = CommonHelper::getVoucherNo(818, date('Y-m-d'), 0, 0, $dbAdapter, "");

        $select = $sql->select();
        $select->from(array("a" => "KF_UnitMaster"))
            ->join(array("b" => "Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
            ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
            ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        /* $select = $sql->select();
                    $select->from(array("a" => "Proj_QualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId'), $select::JOIN_INNER)
                        ->columns(array('QualifierId','YesNo','RefId' => new Expression("'R'+ rtrim(ltrim(str(RefId)))"),'Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','NetPer',
                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
                    $select->where(array('a.QualType' => 'C'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml=Qualifier::getQualifier($qualList);
                    $iQualCount = $iQualCount+1;
                    $sHtml = str_replace('__1','_'.$iQualCount,$sHtml);
                    $qualHtml = $sHtml; */
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
            //try {
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                //Write your Normal form post code here
                $postData = $request->getPost();

                $arrTransVNo = CommonHelper::getVoucherNo(818, date('m-d-Y', strtotime($postData['serveice_date'])), 0, 0, $dbAdapter, "I");
                if($arrTransVNo['genType']== true){
                    $serviceDoneNo = $arrTransVNo['voucherNo'];
                } else {
                    $serviceDoneNo = $postData['serviceNo'];
                }

                $insert = $sql->insert('Crm_ServiceDone');
                $insertData = array(
                    'RefNo'  => $serviceDoneNo,
                    'ServiceDoneDate' => date('m-d-Y', strtotime($postData['serveice_date'])),
                    'UnitId' => $postData['unitId']
                );
                $insert->values($insertData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $serviceDoneRegId = $dbAdapter->getDriver()->getLastGeneratedValue();

                foreach($postData as $key => $data) {
                    if(preg_match('/^serviceId_[\d]+$/', $key)) {

                        preg_match_all('/^serviceId_([\d]+)$/', $key, $arrMatches);
                        $id = $arrMatches[1][0];

                        $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $id], 'number');
                        if($serviceId <= 0) {
                            continue;
                        }

                        $serviceDoneTrans = array(
                            'ServiceDoneRegId' => $serviceDoneRegId,
                            'ServiceId' => $serviceId,
                            'Amount' => $this->bsf->isNullCheck($postData['transAmount_' . $id], 'number')
                        );

                        $insert = $sql->insert('Crm_ServiceDoneTrans');
                        $insert->values($serviceDoneTrans);
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                }
                /*
                //Qualifier
                $j=1;
                $qRowCount =   $this->bsf->isNullCheck($postData['QualRowId_'.$j],'number');
                for ($k = 1; $k <= $qRowCount; $k++) {
                    $iQualifierId = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Id_' . $k], 'number');
                    $iYesNo = isset($postData['Qual_' . $j . '_YesNo_' . $k]) ? 1 : 0;
                    $sExpression = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Exp_' . $k], 'string');
                    $dExpAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
                    $dExpPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                    $iQualTypeId= $this->bsf->isNullCheck($postData['Qual_' . $j . '_TypeId_' . $k], 'number');
                    $sSign = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Sign_' . $k], 'string');

                    if ($iQualTypeId==1 ||$iQualTypeId==2) {
                        $dTaxablePer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxablePer_' . $k], 'number');
                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPer_' . $k], 'number');
                        $dCessPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessPer_' . $k], 'number');
                        $dEDPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessPer_' . $k], 'number');
                        $dHEdPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessPer_' . $k], 'number');
                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetPer_' . $k], 'number');

                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxableAmt_' . $k], 'number');
                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_TaxPerAmt_' . $k], 'number');
                        $dCessAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_CessAmt_' . $k], 'number');
                        $dEDAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_EduCessAmt_' . $k], 'number');
                        $dHEdAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_HEduCessAmt_' . $k], 'number');
                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_NetAmt_' . $k], 'number');
                    } else {
                        $dTaxablePer = 100;
                        $dTaxPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                        $dCessPer = 0;
                        $dEDPer = 0;
                        $dHEdPer = 0;
                        $dNetPer = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpPer_' . $k], 'number');
                        $dTaxableAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_ExpValue_' . $k], 'number');
                        $dTaxAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
                        $dCessAmt = 0;
                        $dEDAmt = 0;
                        $dHEdAmt = 0;
                        $dNetAmt = $this->bsf->isNullCheck($postData['Qual_' . $j . '_Amount_' . $k], 'number');
                    }

                    $insert = $sql->insert();
                    $insert->into('Crm_ExtraBillQualifierTrans');
                    $insert->Values(array('extraBillRegId' => $extraBillRegId,
                    'QualifierId'=>$iQualifierId,'YesNo'=>$iYesNo,'Expression'=>$sExpression,'ExpPer'=>$dExpPer,'TaxablePer'=>$dTaxablePer,'TaxPer'=>$dTaxPer,
                    'Sign'=>$sSign,'SurCharge'=>$dCessPer,'EDCess'=>$dEDPer,'HEDCess'=>$dHEdPer,'NetPer'=>$dNetPer,'ExpressionAmt'=>$dExpAmt,'TaxableAmt'=>$dTaxableAmt,
                    'TaxAmt'=>$dTaxAmt,'SurChargeAmt'=>$dCessAmt,'EDCessAmt'=>$dEDAmt,'HEDCessAmt'=>$dHEdAmt,'NetAmt'=>$dNetAmt));

                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                } */
                $connection->commit();
                CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-ServiceDone-Entry-Add','N','CRM-ServiceDone-Entry',$serviceDoneRegId,0, 0, 'CRM', $serviceDoneNo,$userId, 0 ,0);
                $FeedId = $this->params()->fromQuery('FeedId');
                $AskId = $this->params()->fromQuery('AskId');
                if(isset($FeedId) && $FeedId!="") {
                    $this->redirect()->toRoute('crm/servicedone-register', array('controller' => 'property', 'action' => 'servicedone-register'),array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                } else {
                    $this->redirect()->toRoute('crm/servicedone-register', array('controller' => 'property', 'action' => 'servicedone-register'));
                }
//                    $this->redirect()->toRoute('crm/servicedone-register', array('controller' => 'property', 'action' => 'servicedone-register'));
            }


            /*} catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }*/

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            //$this->_view->qualHtml = $qualHtml;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    // AJAX Request
    public function servicelistAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        if ($request->isPost()
            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
            // CSRF attack
            if($this->getRequest()->isXmlHttpRequest())	{
                // AJAX
                $response->setStatusCode(401);
                $response->setContent('CSRF attack');
                return $response;
            } else {
                // Normal
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $servicereg = $this->params()->fromRoute('ServiceDoneRegId');
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $UnitId = $this->bsf->isNullCheck($this->params()->fromPost('UnitId'), 'number' );
                    $regId = $this->bsf->isNullCheck($this->params()->fromPost('regId'), 'number' );

                    if($UnitId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }

                    if($regId!=0){

                        $subQuery = $sql->select();
                        $subQuery->from(array("a" => "Crm_ServiceDoneTrans"))
                            ->join(array("b" => "Crm_ServiceDone"), "a.ServiceDoneRegId=b.ServiceDoneRegId", array(), $subQuery::JOIN_INNER)
                            ->columns(array('ServiceId'))
                            ->where("UnitId= $UnitId and b.ServiceDoneRegId <> $regId" );
                        // extra item list
                        $select = $sql->select();
                        $select->from(array("a" => "PM_PMUncoveredServiceTrans"))
                            ->join(array("b" => "PM_ServiceMaster"), "a.ServiceId=b.ServiceId", array('data' => 'ServiceId', 'value' => 'ServiceName'), $select::JOIN_INNER)
                            ->join(array("c" => "PM_PMRegister"), "a.PMRegisterId=c.PMRegisterId", array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new expression("a.Amount")),array('data' => 'ServiceId', 'value' => 'ServiceName'))
                            ->where(array('c.UnitId'=>$UnitId))
                            ->where->expression('a.ServiceId Not IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arrServiceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    }
                    else{// subQuery

                        $subQuery = $sql->select();
                        $subQuery->from(array("a" => "Crm_ServiceDoneTrans"))
                            ->join(array("b" => "Crm_ServiceDone"), "a.ServiceDoneRegId=b.ServiceDoneRegId", array(), $subQuery::JOIN_INNER)
                            ->columns(array('ServiceId'))
                            ->where(array('UnitId'=>$UnitId));
                        // extra item list
                        $select = $sql->select();
                        $select->from(array("a" => "PM_PMUncoveredServiceTrans"))
                            ->join(array("b" => "PM_ServiceMaster"), "a.ServiceId=b.ServiceId", array('data' => 'ServiceId', 'value' => 'ServiceName'), $select::JOIN_INNER)
                            ->join(array("c" => "PM_PMRegister"), "a.PMRegisterId=c.PMRegisterId", array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>'Amount'),array('data' => 'ServiceId', 'value' => 'ServiceName'))
                            ->where(array('c.UnitId'=>$UnitId))
                            ->where->expression('a.ServiceId Not IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arrServiceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    $result =  json_encode(array('service_list' => $arrServiceList));
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);

                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }
    public function commercialAgreementAction() {
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
        $rentlead = $this->params()->fromRoute('rentalId');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $leadId = $this->bsf->isNullCheck($postData['LeadId'], 'number');
                    $unitId = $this->bsf->isNullCheck($postData['UnitId'], 'number');

                    $select = $sql->select();
                    $select->from("KF_UnitMaster")
                        ->columns(array("UnitArea"))
                        ->where(array("UnitId"=>$unitId,"DeleteFlag" => '0'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $buildUpArea = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('Crm_LeadAddress')
                        ->columns(array('Address1', 'PanNo'))
                        ->where(array("LeadId" => $leadId, "AddressType" => "P", "DeleteFlag" => '0'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $perLeadDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('Crm_LeadAddress')
                        ->columns(array('Address1', 'POAId'))
                        ->where(array("LeadId" => $leadId, "AddressType" => "POA", "DeleteFlag" => '0'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $poaLeadDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    if ($poaLeadDetails['POAId'] != 0) {

                        $select = $sql->select();
                        $select->from('Crm_LeadPOAInfo')
                            ->columns(array("ApplicantName"))
                            ->where(array("POAId" => $poaLeadDetails['POAId'], "DeleteFlag" => '0'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $poaName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    } else {
                        $poaName="";
                    }
                    $levelIdDetails = array($perLeadDetails,$poaLeadDetails,$poaName,$buildUpArea);
                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                    return $response;
                } catch(PDOException $e) {
                    $connection->rollback();
                    $levelIdDetails="";
                    $response = $this->getResponse()->setContent(json_encode($levelIdDetails));
                    return $response;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
                //echo '<pre>'; print_r($postData); die;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $rentalRegisterId = $this->bsf->isNullCheck($postData['rentalregisterid'], 'number');
                    $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                    if(isset($rentalRegisterId) && $rentalRegisterId!=0 && $mode == "edit" ) {
                        $files = $request->getFiles();
                        $refDate = NULL;
                        $agreeDate = NULL;
                        $agreeStart = NULL;
                        $endDate = NULL;
                        if ($postData['refdate']) {
                            $refDate = date('Y-m-d', strtotime($postData['refdate']));
                        }
                        if ($postData['agreedate']) {
                            $agreeDate = date('Y-m-d', strtotime($postData['agreedate']));
                        }
                        if ($postData['agreestart']) {
                            $agreeStart = date('Y-m-d', strtotime($postData['agreestart']));
                        }
                        if ($postData['enddate']) {
                            $endDate = date('Y-m-d', strtotime($postData['enddate']));
                        }
                        //Owner Details Edit
                        $unitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                        $lead = $this->bsf->isNullCheck($postData['lead'], 'number');
                        $refNo = $this->bsf->isNullCheck($postData['refno'], 'string');
                        $ownerAddress = $this->bsf->isNullCheck($postData['address'], 'string');
                        $panNo = $this->bsf->isNullCheck($postData['pan_no'], 'string');
                        $powerAttorney = $this->bsf->isNullCheck($postData['powerattorney'], 'number');
                        $attorneyName = $this->bsf->isNullCheck($postData['attorney_name'], 'string');
                        $attorneyAddress = $this->bsf->isNullCheck($postData['attorney_address'], 'string');
                        $templateId = $this->bsf->isNullCheck($postData['changeTemplate'], 'number');
                        $templateContent = htmlentities($postData['templateContentHide']);

                        $update = $sql->update();
                        $update->table('PM_RentalRegister');
                        $update->set(array('UnitId' => $unitId
                        , 'Address' => $ownerAddress
                        , 'PANNo' => $panNo
                        , 'IsPowerOfAttorney' => $powerAttorney
                        , 'PAName' => $attorneyName
                        , 'RefNo' => $refNo
                        , 'LeadId' => $lead
                        , 'RefDate' => $refDate
                        , 'RentalType' => "C"
                        , 'PAAddress' => $attorneyAddress
                        , 'TemplateId' => $templateId
                        , 'TemplateContent' => $templateContent));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Tenant Info edit
                        $companyName = $this->bsf->isNullCheck($postData['companyname'], 'string');
                        $businessType = $this->bsf->isNullCheck($postData['businesstype'], 'string');
                        $typeFirm = $this->bsf->isNullCheck($postData['typefirm'], 'string');
                        $yearEstablish = $this->bsf->isNullCheck($postData['year_establish'], 'number');
                        $registerAddress = $this->bsf->isNullCheck($postData['register_address'], 'string');
                        $tenantPanNo = $this->bsf->isNullCheck($postData['tenant_panno'], 'string');

                        $update = $sql->update();
                        $update->table('PM_RentalTenantTrans');
                        $update->set(array('LeaserName' => $companyName
                        , 'PANNo' => $tenantPanNo
                        , 'Address' => $registerAddress
                        , 'BusinessType' => $businessType
                        , 'CompanyType' => $typeFirm
                        , 'EstablishmentYear' => $yearEstablish));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Property Details Edit
                        $rentalArea = $this->bsf->isNullCheck($postData['rental_area'], 'number');
                        $properAddress = $this->bsf->isNullCheck($postData['proper_address'], 'string');

                        $update = $sql->update();
                        $update->table('PM_RentalPropertyTrans');
                        $update->set(array('Area' => $rentalArea
                        , 'PropertyAddress' => $properAddress));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $propertyTransId = $this->bsf->isNullCheck($postData['propertytransid'], 'number');

                        $delete = $sql->delete();
                        $delete->from('PM_RentalPropertyFloorTrans')
                            ->where(array('PropertyTransId' => $propertyTransId));
                        $stmt = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        $paymentId = $postData['payment'];
                        foreach ($paymentId as $floorId):
                            if ($floorId != null) {
                                $insert = $sql->insert();
                                $insert->into('PM_RentalPropertyFloorTrans');
                                $insert->Values(array('PropertyTransId' => $propertyTransId
                                , 'FloorId' => $floorId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        endforeach;

                        //Agreement Details Edit
                        $leaseTerms = $this->bsf->isNullCheck($postData['lease_terms'], 'number');
                        $lockPeriod = $this->bsf->isNullCheck($postData['lock_period'], 'number');
                        $freePeriod = $this->bsf->isNullCheck($postData['free_period'], 'number');
                        $noticePeriod = $this->bsf->isNullCheck($postData['noticeperiod'], 'number');
                        $yearTerms = $this->bsf->isNullCheck($postData['year_terms'], 'string');
                        $yearLock = $this->bsf->isNullCheck($postData['year_lock'], 'string');
                        $monthPeriod = $this->bsf->isNullCheck($postData['month_period'], 'string');
                        $monthNotice = $this->bsf->isNullCheck($postData['month_notice'], 'string');
                        $terminateNotice = $this->bsf->isNullCheck($postData['terminate'], 'number');

                        $update = $sql->update();
                        $update->table('PM_RentalAgreementTrans');
                        $update->set(array('AgtDate' => $agreeDate
                        , 'LeasePeriod' => $leaseTerms
                        , 'LeasePeriodType' => $yearTerms
                        , 'LockInPeriod' => $lockPeriod
                        , 'LockInPeriodType' => $yearLock
                        , 'RentFreePeriod' => $freePeriod
                        , 'RentFreePeriodType' => $monthPeriod
                        , 'StartDate' => $agreeStart
                        , 'EndDate' => $endDate
                        , 'IsNoticePeriod' => $terminateNotice
                        , 'NoticePeriod' => $noticePeriod
                        , 'NoticePeriodType' => $monthNotice));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Security deposit Edit
                        $securityBased = $this->bsf->isNullCheck($postData['security_based'], 'string');
                        $securityAmt = $this->bsf->isNullCheck($postData['security_amt'], 'number');
                        $paymentDeposit = $this->bsf->isNullCheck($postData['payment_deposit'], 'number');
                        $sDepositCount = $this->bsf->isNullCheck($postData['sDeposit_count'], 'number');
                        $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'string');

                        $delete = $sql->delete();
                        $delete->from('PM_RentalPaymentScheduleTrans')
                            ->where(array('RentalRegisterId' => $rentalRegisterId));
                        $stmt = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        if($paymentDeposit!=0) {
                            for ($i = 1; $i <= $sDepositCount; $i++) {
                                if ($postData['sDeposit_name_' . $i] != "") {
                                    $sDepositName = $this->bsf->isNullCheck($postData['sDeposit_name_' . $i], 'string');
                                    $sDepositPer = $this->bsf->isNullCheck($postData['sDeposit_value_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalPaymentScheduleTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ScheduleName' => $sDepositName
                                    , 'ScheduleType' => "N"
                                    , 'SchedulePer' => $sDepositPer));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                        //Rent details Edit
                        $rentAmt = $this->bsf->isNullCheck($postData['rent_amt'], 'number');
                        $rentType = $this->bsf->isNullCheck($postData['rent_type'], 'string');
                        $dueDay = $this->bsf->isNullCheck($postData['due_rent'], 'number');
                        $gracePeriod = $this->bsf->isNullCheck($postData['grace_period'], 'number');
                        $graceType = $this->bsf->isNullCheck($postData['grace_days'], 'string');
                        $lateFeesBased = $this->bsf->isNullCheck($postData['latefees'], 'string');
                        $lateFeesAmt = $this->bsf->isNullCheck($postData['late_fees_day'], 'number');
                        $lateFeesType = $this->bsf->isNullCheck($postData['late_days'], 'string');
                        $maxLateFees = $this->bsf->isNullCheck($postData['late_fees_amt'], 'number');
                        $escPer = $this->bsf->isNullCheck($postData['escalationper'], 'number');
                        $escValid = $this->bsf->isNullCheck($postData['escalationvalid'], 'number');
                        $escType = $this->bsf->isNullCheck($postData['escalation_type'], 'string');

                        $update = $sql->update();
                        $update->table('PM_RentalRentTrans');
                        $update->set(array('RentAmount' => $rentAmt
                        , 'RentPerPeriod' => $rentType
                        , 'DueDay' => $dueDay
                        , 'GracePeriod' => $gracePeriod
                        , 'GracePeriodType' => $graceType
                        , 'LateFeesBasedOn' => $lateFeesBased
                        , 'LateFees' => $lateFeesAmt
                        , 'LateFeesType' => $lateFeesType
                        , 'MaximumLateFees' => $maxLateFees
                        , 'SDBasedOn' => $securityBased
                        , 'SDAmount' => $securityAmt
                        , 'IsPaymentSchedule' => $paymentDeposit
                        , 'EscalationRentPercent' => $escPer
                        , 'EscalationRentPeriod' => $escValid
                        , 'EscalationRentPeriodType' => $escType
                        , 'RenewalType' => $securityDeposit));
                        $update->where("RentalRegisterId=$rentalRegisterId");
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Other Charge Edit
                        $chargeCount = $this->bsf->isNullCheck($postData['charge_count'], 'number');
                        for ($i = 1; $i <= $chargeCount; $i++) {
                            if ($postData['charge_id_' . $i] != "" && $postData['charge_id_' . $i] != "0") {
                                $chargeId = $this->bsf->isNullCheck($postData['charge_id_' . $i], 'number');
                                $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                $chargeTransId = $this->bsf->isNullCheck($postData['charge_transid_' . $i], 'number');
                                if($chargeTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalOtherCostTrans');
                                    $update->set(array('ServiceId' => $chargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$chargeTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ServiceId' => $chargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else {
                                if($postData['charge_name_' . $i]!="" || $postData['charge_name_' . $i]!=0) {
                                    if ($postData['charge_transid_' . $i] != "" || $postData['charge_transid_' . $i] != 0) {
                                        $chargeTransId = $this->bsf->isNullCheck($postData['charge_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalOtherCostTrans')
                                            ->where(array('TransId' => $chargeTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                    $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                    $chargeName = $this->bsf->isNullCheck($postData['charge_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_ServiceMaster');
                                    $insert->Values(array('ServiceName' => $chargeName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChargeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ServiceId' => $newChargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Facilities edit
                        $householdCount = $this->bsf->isNullCheck($postData['household_count'], 'number');
                        for ($i = 1; $i <= $householdCount; $i++) {
                            if ($postData['household_id_' . $i] != "" && $postData['household_id_' . $i] != "0") {
                                $householdId = $this->bsf->isNullCheck($postData['household_id_' . $i], 'number');
                                $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                                $householdTransId = $this->bsf->isNullCheck($postData['household_transid_' . $i], 'number');
                                if($householdTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalFurnitureTrans');
                                    $update->set(array('FurnitureId' => $householdId
                                    , 'Qty' => $householdValue));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$householdTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalFurnitureTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'FurnitureId' => $householdId
                                    , 'Qty' => $householdValue));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            } else {
                                if ($postData['household_name_' . $i] != "" || $postData['household_name_' . $i] != 0) {

                                    if ($postData['household_transid_' . $i] != "" || $postData['household_transid_' . $i] != 0) {
                                        $householdTransId = $this->bsf->isNullCheck($postData['household_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalFurnitureTrans')
                                            ->where(array('TransId' => $householdTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                                    $householdName = $this->bsf->isNullCheck($postData['household_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_FurnitureMaster');
                                    $insert->Values(array('FurnitureName' => $householdName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newFurnitureId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalFurnitureTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'FurnitureId' => $newFurnitureId
                                    , 'Qty' => $householdValue));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }

                        //Amenity edit
                        $amenityCount = $this->bsf->isNullCheck($postData['amenity_count'], 'number');
                        for ($i = 1; $i <= $amenityCount; $i++) {
                            if ($postData['amenity_id_' . $i] != "" && $postData['amenity_id_' . $i] != "0") {
                                $amenityId = $this->bsf->isNullCheck($postData['amenity_id_' . $i], 'number');
                                $amenityTransId = $this->bsf->isNullCheck($postData['amenity_transid_' . $i], 'number');
                                if($amenityTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalAmenityTrans');
                                    $update->set(array('AmenityId' => $amenityId));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$amenityTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalAmenityTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'AmenityId' => $amenityId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            } else {
                                if ($postData['amenity_name_' . $i] != "" || $postData['amenity_name_' . $i] != 0) {

                                    if ($postData['amenity_transid_' . $i] != "" || $postData['amenity_transid_' . $i] != 0) {
                                        $amenityTransId = $this->bsf->isNullCheck($postData['amenity_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalAmenityTrans')
                                            ->where(array('TransId' => $amenityTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $amenityName = $this->bsf->isNullCheck($postData['amenity_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_AmenityMaster');
                                    $insert->Values(array('AmenityName' => $amenityName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newAmenityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalAmenityTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'AmenityId' => $newAmenityId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }

                        //Checklist edit
                        $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                        for ($i = 1; $i <= $checklistCount; $i++) {
                            if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                                $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');
                                $checklistTransId = $this->bsf->isNullCheck($postData['checklist_transid_' . $i], 'number');
                                if($checklistTransId!=0) {
                                    $update = $sql->update();
                                    $update->table('PM_RentalCheckListTrans');
                                    $update->set(array('ChecklistId' => $checklistId));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$checklistTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                } else {
                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ChecklistId' => $checklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else {
                                if ($postData['checklist_name_' . $i] != "" || $postData['checklist_name_' . $i] != 0) {

                                    if ($postData['checklist_transid_' . $i] != "" || $postData['checklist_transid_' . $i] != 0) {
                                        $checklistTransId = $this->bsf->isNullCheck($postData['checklist_transid_' . $i], 'number');
                                        $delete = $sql->delete();
                                        $delete->from('PM_RentalCheckListTrans')
                                            ->where(array('TransId' => $checklistTransId));
                                        $stmt = $sql->getSqlStringForSqlObject($delete);
                                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                    $checklistName = $this->bsf->isNullCheck($postData['checklist_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('Proj_CheckListMaster');
                                    $insert->Values(array('CheckListName' => $checklistName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChecklistId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'ChecklistId' => $newChecklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Document Edit
                        $documentCount = $this->bsf->isNullCheck($postData['document_count'], 'number');
                        for ($i = 1; $i <= $documentCount; $i++) {
                            $documentTransId = $this->bsf->isNullCheck($postData['document_transid_' . $i], 'number');
                            $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');
                            if ($documentTransId != 0) {
                                if ($postData['document_name_' . $i] != "") {
                                    $update = $sql->update();
                                    $update->table('PM_RentalDocumentTrans');
                                    $update->set(array('DocumentName' => $documentName));
                                    $update->where("RentalRegisterId=$rentalRegisterId AND TransId=$documentTransId");
                                    $statement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else {
                                if ($postData['document_name_' . $i] != "") {

                                    $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalDocumentTrans');
                                    $insert->Values(array('RentalRegisterId' => $rentalRegisterId
                                    , 'DocumentName' => $documentName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    if ($files['document_' . $i]['name']) {
                                        $dir = 'public/uploads/crm/agreement/';
                                        if (!is_dir($dir))
                                            mkdir($dir, 0755, true);

                                        $docExt = pathinfo($files['document_' . $i]['name'], PATHINFO_EXTENSION);
                                        $path = $dir . $TransId . '.' . $docExt;
                                        move_uploaded_file($files['document_' . $i]['tmp_name'], $path);

                                        $updateDocExt = $sql->update();
                                        $updateDocExt->table('PM_RentalDocumentTrans');
                                        $updateDocExt->set(array(
                                            'URL' => $this->bsf->isNullCheck($docExt, 'string')
                                        ))
                                            ->where(array('TransId' => $TransId));
                                        $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                        $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }

                                }
                            }
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Agreement-Commercial-Modify','E','Lease-Agreement-Commercial',$rentalRegisterId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    } else {

                        $files = $request->getFiles();
                        //Owner Info Added
                        $refDate = NULL;
                        if ($postData['refdate']) {
                            $refDate = date('Y-m-d', strtotime($postData['refdate']));
                        }
                        $unitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                        $refNo = $this->bsf->isNullCheck($postData['refno'], 'string');
                        $lead = $this->bsf->isNullCheck($postData['lead'], 'number');
                        $ownerAddress = $this->bsf->isNullCheck($postData['address'], 'string');
                        $panNo = $this->bsf->isNullCheck($postData['pan_no'], 'string');
                        $powerAttorney = $this->bsf->isNullCheck($postData['powerattorney'], 'number');
                        $attorneyName = $this->bsf->isNullCheck($postData['attorney_name'], 'string');
                        $attorneyAddress = $this->bsf->isNullCheck($postData['attorney_address'], 'string');
                        $templateId = $this->bsf->isNullCheck($postData['changeTemplate'], 'number');
                        $templateContent = htmlentities($postData['templateContentHide']);


                        $insert = $sql->insert();
                        $insert->into('PM_RentalRegister');
                        $insert->Values(array('UnitId' => $unitId
                        , 'Address' => $ownerAddress
                        , 'PANNo' => $panNo
                        , 'IsPowerOfAttorney' => $powerAttorney
                        , 'PAName' => $attorneyName
                        , 'RefNo' => $refNo
                        , 'LeadId' => $lead
                        , 'RefDate' => $refDate
                        , 'RentalType' => "C"
                        , 'PAAddress' => $attorneyAddress
                        , 'TemplateId' => $templateId
                        , 'TemplateContent' => $templateContent));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Last Inserted Rental Register Id
                        $RentalRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //Tenant Info Added
                        $companyName = $this->bsf->isNullCheck($postData['companyname'], 'string');
                        $businessType = $this->bsf->isNullCheck($postData['businesstype'], 'string');
                        $typeFirm = $this->bsf->isNullCheck($postData['typefirm'], 'string');
                        $yearEstablish = $this->bsf->isNullCheck($postData['year_establish'], 'number');
                        $registerAddress = $this->bsf->isNullCheck($postData['register_address'], 'string');
                        $tenantPanNo = $this->bsf->isNullCheck($postData['tenant_panno'], 'string');

                        $insert = $sql->insert();
                        $insert->into('PM_RentalTenantTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'LeaserName' => $companyName
                        , 'BusinessType' => $businessType
                        , 'CompanyType' => $typeFirm
                        , 'EstablishmentYear' => $yearEstablish
                        , 'Address' => $registerAddress
                        , 'PANNo' => $tenantPanNo));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Property Info Added
                        $rentalArea = $this->bsf->isNullCheck($postData['rental_area'], 'number');
                        $properAddress = $this->bsf->isNullCheck($postData['proper_address'], 'string');

                        $insert = $sql->insert();
                        $insert->into('PM_RentalPropertyTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'Area' => $rentalArea
                        , 'PropertyAddress' => $properAddress));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //Last Inserted Property Trans Id
                        $PropertyTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $paymentId = $postData['payment'];
                        foreach ($paymentId as $floorId):
                            if ($floorId != null) {
                                $insert = $sql->insert();
                                $insert->into('PM_RentalPropertyFloorTrans');
                                $insert->Values(array('PropertyTransId' => $PropertyTransId
                                , 'FloorId' => $floorId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        endforeach;

                        //agreement Details Added
                        $leaseTerms = $this->bsf->isNullCheck($postData['lease_terms'], 'number');
                        $lockPeriod = $this->bsf->isNullCheck($postData['lock_period'], 'number');
                        $freePeriod = $this->bsf->isNullCheck($postData['free_period'], 'number');
                        $noticePeriod = $this->bsf->isNullCheck($postData['noticeperiod'], 'number');
                        $yearTerms = $this->bsf->isNullCheck($postData['year_terms'], 'string');
                        $yearLock = $this->bsf->isNullCheck($postData['year_lock'], 'string');
                        $monthPeriod = $this->bsf->isNullCheck($postData['month_period'], 'string');
                        $monthNotice = $this->bsf->isNullCheck($postData['month_notice'], 'string');
                        $terminateNotice = $this->bsf->isNullCheck($postData['terminate'], 'number');
                        $agreeDate = NULL;
                        if ($postData['agreedate']) {
                            $agreeDate = date('Y-m-d', strtotime($postData['agreedate']));
                        }
                        $agreeStart = NULL;
                        if ($postData['agreestart']) {
                            $agreeStart = date('Y-m-d', strtotime($postData['agreestart']));
                        }
                        $endDate = NULL;
                        if ($postData['enddate']) {
                            $endDate = date('Y-m-d', strtotime($postData['enddate']));
                        }
                        $insert = $sql->insert();
                        $insert->into('PM_RentalAgreementTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'AgtDate' => $agreeDate
                        , 'LeasePeriod' => $leaseTerms
                        , 'LeasePeriodType' => $yearTerms
                        , 'LockInPeriod' => $lockPeriod
                        , 'LockInPeriodType' => $yearLock
                        , 'RentFreePeriod' => $freePeriod
                        , 'RentFreePeriodType' => $monthPeriod
                        , 'StartDate' => $agreeStart
                        , 'EndDate' => $endDate
                        , 'IsNoticePeriod' => $terminateNotice
                        , 'NoticePeriod' => $noticePeriod
                        , 'NoticePeriodType' => $monthNotice));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Security Deposit
                        $securityBased = $this->bsf->isNullCheck($postData['security_based'], 'string');
                        $securityAmt = $this->bsf->isNullCheck($postData['security_amt'], 'number');
                        $paymentDeposit = $this->bsf->isNullCheck($postData['payment_deposit'], 'number');
                        $sDepositCount = $this->bsf->isNullCheck($postData['sDeposit_count'], 'number');
                        $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'string');

                        if($paymentDeposit!=0) {
                            for ($i = 1; $i <= $sDepositCount; $i++) {
                                if ($postData['sDeposit_name_' . $i] != "") {
                                    $sDepositName = $this->bsf->isNullCheck($postData['sDeposit_name_' . $i], 'string');
                                    $sDepositPer = $this->bsf->isNullCheck($postData['sDeposit_value_' . $i], 'number');

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalPaymentScheduleTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'ScheduleName' => $sDepositName
                                    , 'ScheduleType' => "N"
                                    , 'SchedulePer' => $sDepositPer));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }
                        //Rent Details
                        $rentAmt = $this->bsf->isNullCheck($postData['rent_amt'], 'number');
                        $rentType = $this->bsf->isNullCheck($postData['rent_type'], 'string');
                        $dueDay = $this->bsf->isNullCheck($postData['due_rent'], 'number');
                        $gracePeriod = $this->bsf->isNullCheck($postData['grace_period'], 'number');
                        $graceType = $this->bsf->isNullCheck($postData['grace_days'], 'string');
                        $lateFeesBased = $this->bsf->isNullCheck($postData['latefees'], 'string');
                        $lateFeesAmt = $this->bsf->isNullCheck($postData['late_fees_day'], 'number');
                        $lateFeesType = $this->bsf->isNullCheck($postData['late_days'], 'string');
                        $maxLateFees = $this->bsf->isNullCheck($postData['late_fees_amt'], 'number');
                        $escPer = $this->bsf->isNullCheck($postData['escalationper'], 'number');
                        $escValid = $this->bsf->isNullCheck($postData['escalationvalid'], 'number');
                        $escType = $this->bsf->isNullCheck($postData['escalation_type'], 'string');


                        $insert = $sql->insert();
                        $insert->into('PM_RentalRentTrans');
                        $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                        , 'RentAmount' => $rentAmt
                        , 'RentPerPeriod' => $rentType
                        , 'DueDay' => $dueDay
                        , 'GracePeriod' => $gracePeriod
                        , 'GracePeriodType' => $graceType
                        , 'LateFeesBasedOn' => $lateFeesBased
                        , 'LateFees' => $lateFeesAmt
                        , 'LateFeesType' => $lateFeesType
                        , 'MaximumLateFees' => $maxLateFees
                        , 'SDBasedOn' => $securityBased
                        , 'SDAmount' => $securityAmt
                        , 'IsPaymentSchedule' => $paymentDeposit
                        , 'EscalationRentPercent' => $escPer
                        , 'EscalationRentPeriod' => $escValid
                        , 'EscalationRentPeriodType' => $escType
                        , 'RenewalType' => $securityDeposit));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //Other Charges added
                        $chargeCount = $this->bsf->isNullCheck($postData['charge_count'], 'number');
                        for ($i = 1; $i <= $chargeCount; $i++) {
                            if ($postData['charge_id_' . $i] != "" && $postData['charge_id_' . $i] != "0") {
                                $chargeId = $this->bsf->isNullCheck($postData['charge_id_' . $i], 'number');
                                $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalOtherCostTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'ServiceId' => $chargeId
                                , 'Amount' => $chargeValue
                                , 'TransType' => $chargeType));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                if ($postData['charge_name_' . $i] != "" || $postData['charge_name_' . $i] != 0) {

                                    $chargeValue = $this->bsf->isNullCheck($postData['charge_value_' . $i], 'number');
                                    $chargeType = $this->bsf->isNullCheck($postData['charge_type_' . $i], 'string');
                                    $chargeName = $this->bsf->isNullCheck($postData['charge_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_ServiceMaster');
                                    $insert->Values(array('ServiceName' => $chargeName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChargeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalOtherCostTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'ServiceId' => $newChargeId
                                    , 'Amount' => $chargeValue
                                    , 'TransType' => $chargeType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Facilities added
                        $householdCount = $this->bsf->isNullCheck($postData['household_count'], 'number');
                        for ($i = 1; $i <= $householdCount; $i++) {
                            if ($postData['household_id_' . $i] != "" && $postData['household_id_' . $i] != "0") {
                                $householdId = $this->bsf->isNullCheck($postData['household_id_' . $i], 'number');
                                $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalFurnitureTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'FurnitureId' => $householdId
                                , 'Qty' => $householdValue));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                if ($postData['household_name_' . $i] != "" || $postData['household_name_' . $i] != 0) {

                                    $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                                    $householdName = $this->bsf->isNullCheck($postData['household_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_FurnitureMaster');
                                    $insert->Values(array('FurnitureName' => $householdName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newFurnitureId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalFurnitureTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'FurnitureId' => $newFurnitureId
                                    , 'Qty' => $householdValue));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Amenity Added
                        $amenityCount = $this->bsf->isNullCheck($postData['amenity_count'], 'number');
                        for ($i = 1; $i <= $amenityCount; $i++) {
                            if ($postData['amenity_id_' . $i] != "" && $postData['amenity_id_' . $i] != "0") {
                                $amenityId = $this->bsf->isNullCheck($postData['amenity_id_' . $i], 'number');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalAmenityTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'AmenityId' => $amenityId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                if ($postData['amenity_name_' . $i] != "" || $postData['amenity_name_' . $i] != 0) {

                                    $amenityName = $this->bsf->isNullCheck($postData['amenity_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('PM_AmenityMaster');
                                    $insert->Values(array('AmenityName' => $amenityName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newAmenityId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalAmenityTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'AmenityId' => $newAmenityId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }

                        //Checklist added
                        $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                        for ($i = 1; $i <= $checklistCount; $i++) {
                            if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                                $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalCheckListTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'ChecklistId' => $checklistId));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                if ($postData['checklist_name_' . $i] != "" || $postData['checklist_name_' . $i] != 0) {

                                    $checklistName = $this->bsf->isNullCheck($postData['checklist_name_' . $i], 'string');

                                    $insert = $sql->insert();
                                    $insert->into('Proj_CheckListMaster');
                                    $insert->Values(array('CheckListName' => $checklistName));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $newChecklistId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $insert = $sql->insert();
                                    $insert->into('PM_RentalCheckListTrans');
                                    $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                    , 'ChecklistId' => $newChecklistId));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }


                        //Document Added
                        $documentCount = $this->bsf->isNullCheck($postData['document_count'], 'number');
                        for ($i = 1; $i <= $documentCount; $i++) {
                            if ($postData['document_name_' . $i] != "") {

                                $documentName = $this->bsf->isNullCheck($postData['document_name_' . $i], 'string');

                                $insert = $sql->insert();
                                $insert->into('PM_RentalDocumentTrans');
                                $insert->Values(array('RentalRegisterId' => $RentalRegisterId
                                , 'DocumentName' => $documentName));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $TransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                if ($files['document_' . $i]['name']) {
                                    $dir = 'public/uploads/crm/agreement/';
                                    if (!is_dir($dir))
                                        mkdir($dir, 0755, true);

                                    $docExt = pathinfo($files['document_' . $i]['name'], PATHINFO_EXTENSION);
                                    $path = $dir . $TransId . '.' . $docExt;
                                    move_uploaded_file($files['document_' . $i]['tmp_name'], $path);

                                    $updateDocExt = $sql->update();
                                    $updateDocExt->table('PM_RentalDocumentTrans');
                                    $updateDocExt->set(array(
                                        'URL' => $this->bsf->isNullCheck($docExt, 'string')
                                    ))
                                        ->where(array('TransId' => $TransId));
                                    $updateDocExtStmt = $sql->getSqlStringForSqlObject($updateDocExt);
                                    $dbAdapter->query($updateDocExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                            }
                        }
                        $connection->commit();
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Agreement-Commercial-Add','N','Lease-Agreement-Commercial',$RentalRegisterId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);

                    }
                } catch(PDOException $e) {
                    $connection->rollback();
                }
                $FeedId = $this->params()->fromQuery('FeedId');
                $AskId = $this->params()->fromQuery('AskId');
                if(isset($FeedId) && $FeedId != "") {
                    $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "commercial-agreement"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                } else {
                    $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"));
                }

//                return $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "commercial-agreement"));

            } else {
                $rentalRegisterId = $this->bsf->isNullCheck($this->params()->fromRoute('rentalId'), 'number');
                $mode = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'string');

                if(isset($rentalRegisterId) && $rentalRegisterId!=0 && $mode=="edit") {
                    //Owner Details
                    $select = $sql->select();
                    $select->from("PM_RentalRegister")
                        ->columns(array('UnitId', 'Address','LeadId', 'PANNo', 'IsPowerOfAttorney', 'PAName', 'RefNo', 'RefDate', 'RentalType', 'PAAddress','TemplateId','TemplateContent'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $ownerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $OwnerUnit = $ownerDetail['UnitId'];
                    $this->_view->ownerDetail = $ownerDetail;



                    $select = $sql->select();
                    $select->from(array("a" => "Crm_UnitDetails"))
                        ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                        ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadId'), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                        ->where("a.UnitId=$OwnerUnit");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->ownerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //Tenant Details
                    $select = $sql->select();
                    $select->from("PM_RentalTenantTrans")
                        ->columns(array('LeaserName', 'PANNo', 'Address','BusinessType','CompanyType','EstablishmentYear'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tenantDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //property Details
                    $select = $sql->select();
                    $select->from("PM_RentalPropertyTrans")
                        ->columns(array('Area', 'PropertyAddress','PropertyTransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $propertyDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $propertyTransId=$propertyDetail['PropertyTransId'];

                    $select = $sql->select();
                    $select->from("PM_RentalPropertyFloorTrans")
                        ->columns(array('FloorId'))
                        ->where("PropertyTransId=$propertyTransId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->propertyFloorDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->propertyDetail=$propertyDetail;

                    //Agreement Details
                    $select = $sql->select();
                    $select->from("PM_RentalAgreementTrans")
                        ->columns(array('AgtDate', 'LeasePeriod', 'LeasePeriodType', 'StartDate', 'IsNoticePeriod', 'NoticePeriod', 'NoticePeriodType','LockInPeriod','LockInPeriodType','RentFreePeriod','RentFreePeriodType'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->agreementDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //Security Deposit
                    $select = $sql->select();
                    $select->from("PM_RentalRentTrans")
                        ->columns(array('SDBasedOn', 'SDAmount', 'IsPaymentSchedule', 'RenewalType'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->secureDeposit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from("PM_RentalPaymentScheduleTrans")
                        ->columns(array('ScheduleName', 'SchedulePer','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->secureDepositSchedule = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->sDepositCount = count($this->_view->secureDepositSchedule);

                    //Rent Details
                    $select = $sql->select();
                    $select->from("PM_RentalRentTrans")
                        ->columns(array('RentAmount','RentPerPeriod', 'DueDay','GracePeriod','GracePeriodType','LateFeesBasedOn','LateFees','LateFeesType','MaximumLateFees','EscalationRentPercent','EscalationRentPeriod','EscalationRentPeriodType'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->RentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //Other Charge
                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalOtherCostTrans"))
                        ->join(array('b' => 'PM_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('ServiceName'), $select::JOIN_INNER)
                        ->columns(array('ServiceId', 'Amount', 'TransType', 'TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->otherDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->chargeCount = count($this->_view->otherDetail);

                    //Facilities
                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalFurnitureTrans"))
                        ->join(array('b' => 'PM_FurnitureMaster'), 'a.FurnitureId=b.FurnitureId', array('FurnitureName'), $select::JOIN_INNER)
                        ->columns(array('FurnitureId', 'Qty','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->furnitureDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->holdCount = count($this->_view->furnitureDetail);

                    //Amenity
                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalAmenityTrans"))
                        ->join(array('b' => 'PM_AmenityMaster'), 'a.AmenityId=b.AmenityId', array('AmenityName'), $select::JOIN_INNER)
                        ->columns(array('AmenityId','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->amenityDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->amenityCount = count($this->_view->amenityDetail);

                    //Checklist
                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalCheckListTrans"))
                        ->join(array('b' => 'Proj_CheckListMaster'), 'a.ChecklistId=b.ChecklistId', array('CheckListName'), $select::JOIN_INNER)
                        ->columns(array('ChecklistId','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->checklistDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->checklistCount = count($this->_view->checklistDetail);

                    //Document
                    $select = $sql->select();
                    $select->from("PM_RentalDocumentTrans")
                        ->columns(array('DocumentName','TransId'))
                        ->where("RentalRegisterId=$rentalRegisterId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->documentDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                    $this->_view->documentCount = count($this->_view->documentDetail);

                    $this->_view->rentalRegisterId=$rentalRegisterId;
                }

                //Query for Project/Unit/Owner
                $select = $sql->select();
                $select->from(array("a" => "Crm_UnitDetails"))
                    ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadId','LeadName'), $select::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array('UnitNo'), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("PM_ServiceMaster")
                    ->columns(array('data' => 'ServiceId', 'value' => new Expression('ServiceName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->serviceList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("Proj_CheckListMaster")
                    ->columns(array('data' => 'CheckListId', 'value' => new Expression('CheckListName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->checkList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("PM_AmenityMaster")
                    ->columns(array('data' => 'AmenityId', 'value' => new Expression('AmenityName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->amenityList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from("PM_FurnitureMaster")
                    ->columns(array('data' => 'FurnitureId', 'value' => new Expression('FurnitureName')));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->householdList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Fetching data from agreement template
                $select = $sql->select();
                $select->from('PM_AgreementTemplate')
                    ->columns(array('TemplateId','TemplateName'))
                    ->where("AgreementTypeId = '2'")
                    ->where("DeleteFlag = '0'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->agreementTemplates = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('KF_FloorMaster')
                    ->columns(array('FloorId','FloorName'))
                    ->where("DeleteFlag = '0'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->floorProp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_Leads')
                    ->columns(array('LeadName'))
                    ->where(array("LeadId" => $rentalRegisterId,"DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenantName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Crm_LeadAddress')
                    ->columns(array('Address1','PanNo'))
                    ->where(array("LeadId" => $rentalRegisterId,"AddressType" => "O","DeleteFlag" => '0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->tenantPanAdd = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->mode = $mode;
                $this->_view->rentlead = $rentlead;

            }

            $this->_view->mergeTags = $this->bsf->getMergeTags(2);
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function servicedoneRegisterAction(){
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

        //total Payment//
        $select = $sql->select();
        $select->from(array('a' =>'Crm_ServiceDone'))
            ->columns(array('ServiceDoneRegisterId' => new expression('count(*)')))
            ->where(array('DeleteFlag'=>'0'));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->paymentreg = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array("a" => "Crm_ServiceDone"))
            ->columns(array(new Expression("a.serviceDoneRegId,a.ServiceDoneDate,a.RefNo,COUNT(b.ServiceId) as Totalservice,sum(b.Amount) as TotalAmount")))
            ->join(array('b' => 'Crm_ServiceDoneTrans'), 'a.serviceDoneRegId=b.serviceDoneRegId', array(), $select::JOIN_LEFT)
            ->join(array('c' => 'KF_UnitMaster'), 'a.UnitId=c.UnitId', array('UnitNo'), $select::JOIN_LEFT)
            ->join(array("d" => "Crm_UnitBooking"), 'a.UnitId=d.UnitId', array(), $select::JOIN_INNER)
            ->join(array("e" => "Crm_Leads"), "e.LeadId=d.LeadId", array('LeadName'), $select::JOIN_INNER)
            ->join(array("f" => "Proj_ProjectMaster"), "c.ProjectId=f.ProjectId", array('ProjectName'), $select::JOIN_INNER)
            ->where(array('a.DeleteFlag' => 0))
            ->group(new expression('a.serviceDoneRegId,a.ServiceDoneDate,a.RefNo,a.UnitId,c.UnitNo,e.LeadName,f.ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->serviceReg = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        return $this->_view;
    }
    public function servicedoneDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('serviceDoneRegId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('Crm_ServiceDone')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('serviceDoneRegId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-ServiceDone-Entry-Delete','D','CRM-ServiceDone-Entry',$RegisterId,0, 0, 'CRM', '',$userId, 0 ,0);

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function dashboardAction(){
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

        //  adding project wise filter
        $projectId = $this->params()->fromRoute('projectId');
        $where="";
        if(isset($projectId)){

            $where =" where projectId =".$projectId;

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId','ProjectName'))
                ->where(array("ProjectId"=>$projectId));
            $statement = $sql->getSqlStringForSqlObject($select);

            $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->projectId = $projectId;

        }
        $select = $sql->select();
        $select->from(array('a'=>'Crm_LeadProjects'))
            ->join(array('b' => 'Crm_Leads'), 'a.LeadId=b.LeadId', array(), $select::JOIN_INNER)
            ->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId=c.ProjectId', array(), $select::JOIN_INNER)
            ->columns(array('ProjectId','ProjectName'=>new expression("c.ProjectName")))
            ->where(array('a.DeleteFlag' => 0))
            ->group(new expression("a.ProjectId,c.ProjectName"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Agreement Expired in next 7 days//
        $select = $sql->select();
        $select ->from(array("a" => "PM_RentalAgreementTrans"))
            ->join(array('e' => 'PM_RentalRegister'), 'a.RentalRegisterId=e.RentalRegisterId', array('RefNo'), $select::JOIN_LEFT)
            ->join(array('c' => 'KF_UnitMaster'), 'c.UnitId=e.UnitId', array('UnitNo'), $select::JOIN_LEFT)
            ->join(array('b' => 'PM_PMRegister'), 'e.UnitId=b.UnitId', array(), $select::JOIN_LEFT)
            ->join(array('d' => 'Crm_UnitBooking'), 'e.UnitId=d.UnitId', array('BookingName','BookingDate'), $select::JOIN_LEFT)
            ->join(array('f' => 'PM_RentalTenantTrans'), 'a.RentalRegisterId=f.RentalRegisterId', array('LeaserName',"Add"=>'Address'), $select::JOIN_LEFT)
            ->where(array("a.AgtDate between GetDate() and  GetDate()+7"));
        if(isset($projectId)){
            $select->where(array('c.ProjectId' => $projectId));
        }
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->Aggrement = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        //total units
        $select = $sql->select();
        $select ->from(array('a' =>'KF_UnitMaster'))
            ->columns(array('TotalUnits' => new expression('count(*)')));
        if(isset($projectId)){
            $select-> where("a.ProjectId=$projectId ");
        }
        $stmt = $sql->getSqlStringForSqlObject($select);
        $TotalUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //unoccupied units//
        $select = $sql->select();
        $select->from(array('a' =>'PM_RentalRegister'));
        if(isset($projectId)){
            $select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
            $select-> where("b.ProjectId=$projectId ");
        }
        $select->columns(array('UnitId' => new expression('count(*)')));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $BookingUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->RemaingUnits = $TotalUnits['TotalUnits'] - $BookingUnits['UnitId'];
        $this->_view->BookingUnits = $BookingUnits['UnitId'];



        //Monthwise Rental Count//
        $today = date('Y-m-d');
        $arrMonthVariation = array();
        for($i=0; $i<6; $i++) {
            $tDate = date('Y-m-d', strtotime($today . '+' . $i .'months'));
            $tMonth = date('M', strtotime($tDate));
            $tMonthNo = date('m', strtotime($tDate));
            $tYear = date('Y', strtotime($tDate));

            $select = $sql->select();
            $select->from(array("a"=>'PM_RentalRegister'))
                ->columns(array(
                    'RefId' => new Expression('count(*)')))
                ->where->expression('MONTH(RefDate) = ?', array($tMonthNo));

            $stmt = $sql->getSqlStringForSqlObject($select);
            $arrHvc= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $monthText = $tMonth . '-'. $tYear;
            $arrMonthVariation[(6 + $i)] = array(
                'Month' => $monthText,
                'Target' => $arrHvc['RefId']);

            $arrCat = array();
            $arrInvs = array();
            foreach($arrMonthVariation as $Camp){
                $arrCat[] = $Camp['Month'];
                $arrInvs[] = (int)$Camp['Target'];
            }}
        $this->_view->jsonarrCampCat = json_encode($arrCat);
        $this->_view->jsonarrCampInvs = json_encode($arrInvs);

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

            return $this->_view	;	}
    }
    public function servicedoneEditAction(){
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
        $servicereg = $this->params()->fromRoute('ServiceDoneRegId');
        $sql = new Sql($dbAdapter);
        $userId = $this->auth->getIdentity()->UserId;
        $this->_view->arrVNo = CommonHelper::getVoucherNo(818, date('Y-m-d'), 0, 0, $dbAdapter, "");

        $request = $this->getRequest();
        $response = $this->getResponse();

        $select = $sql->select();
        $select->from(array("f" => "Crm_ServiceDone"))
            ->join(array("a" => "KF_UnitMaster"), "a.UnitId=f.UnitId", array(), $select::JOIN_INNER)
            ->join(array("b" => "Crm_UnitBooking"), "f.UnitId=b.UnitId", array(), $select::JOIN_INNER)
            ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
            ->columns(array('RefNo','ServiceDoneDate','ServiceDoneRegId','data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
            ->where(array('f.ServiceDoneRegId'=>$servicereg));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array("a" => "Crm_ServiceDoneTrans"))
            ->join(array("b" => "PM_ServiceMaster"), "a.ServiceId=b.ServiceId", array(), $select::JOIN_INNER)
            ->columns(array('ServiceId'=>'ServiceId','ItemDescription' => new Expression("b.ServiceName"),'Amount'=>'Amount'))
            ->where(array('a.ServiceDoneRegId'=>$servicereg));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->unitTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $result="";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            //try {
            if ($request->isPost()) {
                $postData = $request->getPost();
                //Print_r($postData);die;
                $arrTransVNo = CommonHelper::getVoucherNo(818, date('m-d-Y', strtotime($postData['serveice_date'])), 0, 0, $dbAdapter, "I");
                if($arrTransVNo['genType']== true){
                    $serviceDoneNo = $arrTransVNo['voucherNo'];
                } else {
                    $serviceDoneNo = $postData['serviceNo'];
                }

                $delete = $sql->delete();
                $delete->from('Crm_ServiceDoneTrans')
                    ->where(array('ServiceDoneRegId' => $servicereg));
                $DelStatement = $sql->getSqlStringForSqlObject($delete);
                $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('Crm_ServiceDone');
                $update->set(array(
                    'RefNo'=>$serviceDoneNo,
                    'ServiceDoneDate'=>date('m-d-y',strtotime($postData['service_date'])),
                ));
                $update->where(array('ServiceDoneRegId'=>$servicereg));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach($postData as $key => $data) {
                    if(preg_match('/^serviceId_[\d]+$/', $key)) {

                        preg_match_all('/^serviceId_([\d]+)$/', $key, $arrMatches);
                        $id = $arrMatches[1][0];

                        $serviceId = $this->bsf->isNullCheck($postData['serviceId_' . $id], 'number');
                        if($serviceId <= 0) {
                            continue;
                        }

                        $extraItemDoneTrans = array(
                            'ServiceDoneRegId' => $servicereg,
                            'ServiceId' => $serviceId,
                            'Amount' => $this->bsf->isNullCheck($postData['transAmount_' . $id], 'number')
                        );

                        $insert = $sql->insert('Crm_ServiceDoneTrans');
                        $insert->values($extraItemDoneTrans);
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
            }
            $connection->commit();
            CommonHelper::insertLog(date('Y-m-d H:i:s'),'CRM-ServiceDone-Entry-Modify','E','CRM-ServiceDone-Entry',$servicereg,0, 0, 'CRM', $serviceDoneNo,$userId, 0 ,0);


            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            //$this->_view->qualHtml = $qualHtml;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function agreementCancellationAction()
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

        $request = $this->getRequest();

        if ($request->isPost()) {
            //Write your Normal form post code here
            $postData = $request->getPost();
            $mode = $this->bsf->isNullCheck($postData['modetype'], 'string');
            $templateId = $this->bsf->isNullCheck($postData['changeTemplate'], 'number');
            $templateContent = htmlentities($postData['templateContentHide']);

            $rentalRegisterId = $this->bsf->isNullCheck($postData['agreeno'], 'number');
            if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"add") == 0) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $refNo = $this->bsf->isNullCheck($postData['refno'], 'number');
                    $agreeNo = $this->bsf->isNullCheck($postData['agreeno'], 'number');
                    $unitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                    $cancelType = $this->bsf->isNullCheck($postData['cancellation'], 'string');
                    $cancelReason = $this->bsf->isNullCheck($postData['reason'], 'string');
                    $householdCount = $this->bsf->isNullCheck($postData['household_count'], 'number');
                    $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                    $otherCount = $this->bsf->isNullCheck($postData['other_count'], 'number');
                    $deductionCount = $this->bsf->isNullCheck($postData['deduction_count'], 'number');
                    $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'number');
                    $securityDepName = $this->bsf->isNullCheck($postData['security_name'], 'string');
                    $totalPayable = $this->bsf->isNullCheck($postData['totalpayable'], 'number');

                    $userId = $this->auth->getIdentity()->UserId;

                    $vacateDate = null;
                    if (isset($postData['vacatedate'])) {
                        $vacateDate = date('Y-m-d', strtotime($postData['vacatedate']));

                    }
                    $refDate = null;
                    if (isset($postData['refdate'])) {
                        $refDate = date('Y-m-d', strtotime($postData['refdate']));
                    }

                    $insert = $sql->insert();
                    $insert->into('PM_RentalCancelRegister');
                    $insert->Values(array('RentalRegisterId' => $agreeNo
                    , 'RefNo' => $refNo
                    , 'RefDate' => $refDate
                    , 'CancellationType' => $cancelType
                    , 'EffectFrom' => $vacateDate
                    , 'Payable' => $totalPayable
                    , 'Reason' => $cancelReason
                    , 'TemplateId' => $templateId
                    , 'TemplateContent' => $templateContent));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $rentalCancelRegId = $dbAdapter->getDriver()->getLastGeneratedValue();


                    //Facilities added
                    for ($i = 1; $i <= $householdCount; $i++) {
                        if ($postData['household_id_' . $i] != "" && $postData['household_id_' . $i] != "0") {
                            $householdId = $this->bsf->isNullCheck($postData['household_id_' . $i], 'number');
                            $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                            $householdActualValue = $this->bsf->isNullCheck($postData['household_actualvalue_' . $i], 'number');
                            $householdCheck = $this->bsf->isNullCheck($postData['household_Checkbox_' . $i], 'number');
                            if($householdCheck==1){
                                $householdCheck=1;
                            } else{
                                $householdCheck=0;
                            }
                            $insert = $sql->insert();
                            $insert->into('PM_RentalCancelFurnitureTrans');
                            $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                            , 'FurnitureId' => $householdId
                            , 'ActualQty' => $householdActualValue
                            , 'Verified' => $householdCheck
                            , 'Qty' => $householdValue));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }

                    //Checklist added
                    for ($i = 1; $i <= $checklistCount; $i++) {
                        if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {
                            $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');
                            $checklistCheck = $this->bsf->isNullCheck($postData['checklist_Checkbox_' . $i], 'number');
                            if ($checklistCheck == 1) {
                                $status = "Y";
                            } else {
                                $status = "N";
                            }
                            if (isset($postData['submiton_' . $i])) {
                                $submitOn = null;
                                $submitOn = date('Y-m-d', strtotime($postData['submiton_' . $i]));

                                $insert = $sql->insert();
                                $insert->into('PM_RentalCancelCheckListTrans');
                                $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                                , 'ChecklistId' => $checklistId
                                , 'SubmitOn' => $submitOn
                                , 'ExecutiveId' => $userId
                                , 'Status' => $status));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                            } else {
                                $insert = $sql->insert();
                                $insert->into('PM_RentalCancelCheckListTrans');
                                $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                                , 'ChecklistId' => $checklistId
                                , 'SubmitOn' => date('Y-m-d')
                                , 'ExecutiveId' => $userId
                                , 'Status' => $status));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                            }
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //other added
                    for ($i = 1; $i <= $otherCount; $i++) {
                        if ($postData['other_name_' . $i] != "") {
                            $otherName = $this->bsf->isNullCheck($postData['other_name_' . $i], 'string');
                            $otherAmount = $this->bsf->isNullCheck($postData['other_amount_' . $i], 'string');

                            $insert = $sql->insert();
                            $insert->into('PM_RentalCancelPayableTrans');
                            $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                            , 'Description' => $otherName
                            , 'Amount' => $otherAmount
                            , 'TransType' => "A"));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Deduction added
                    for ($i = 1; $i <= $deductionCount; $i++) {
                        if ($postData['deduction_name_' . $i] != "") {
                            $deductionName = $this->bsf->isNullCheck($postData['deduction_name_' . $i], 'string');
                            $deductionAmount = $this->bsf->isNullCheck($postData['deduction_amount_' . $i], 'number');

                            $insert = $sql->insert();
                            $insert->into('PM_RentalCancelPayableTrans');
                            $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                            , 'Description' => $deductionName
                            , 'Amount' => $deductionAmount
                            , 'TransType' => "D"));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //Security Deposit
                    $insert = $sql->insert();
                    $insert->into('PM_RentalCancelPayableTrans');
                    $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                    , 'Description' => $securityDepName
                    , 'Amount' => $securityDeposit
                    , 'TransType' => "A"));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Cancellation-Add','N','Lease-Cancellation',$rentalCancelRegId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);
                } catch(PDOException $e) {
                    $connection->rollback();
                }
            } else if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"edit") == 0) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $refNo = $this->bsf->isNullCheck($postData['refno'], 'number');
                    $agreeNo = $this->bsf->isNullCheck($postData['agreeno'], 'number');
                    $unitId = $this->bsf->isNullCheck($postData['unitid'], 'number');
                    $cancelType = $this->bsf->isNullCheck($postData['cancellation'], 'string');
                    $cancelReason = $this->bsf->isNullCheck($postData['reason'], 'string');
                    $householdCount = $this->bsf->isNullCheck($postData['household_count'], 'number');
                    $checklistCount = $this->bsf->isNullCheck($postData['checklist_count'], 'number');
                    $otherCount = $this->bsf->isNullCheck($postData['other_count'], 'number');
                    $deductionCount = $this->bsf->isNullCheck($postData['deduction_count'], 'number');
                    $securityDeposit = $this->bsf->isNullCheck($postData['securitydeposit'], 'number');
                    $securityDepName = $this->bsf->isNullCheck($postData['security_name'], 'string');
                    $totalPayable = $this->bsf->isNullCheck($postData['totalpayable'], 'number');
                    $rentalCancelRegId = $this->bsf->isNullCheck($postData['rentalcancelid'], 'number');

                    $userId = $this->auth->getIdentity()->UserId;

                    $vacateDate = null;
                    if (isset($postData['vacatedate'])) {
                        $vacateDate = date('Y-m-d', strtotime($postData['vacatedate']));

                    }
                    $refDate = null;
                    if (isset($postData['refdate'])) {
                        $refDate = date('Y-m-d', strtotime($postData['refdate']));
                    }


                    $update = $sql->update();
                    $update->table('PM_RentalCancelRegister');
                    $update->set(array('RefNo' => $refNo
                    , 'RefDate' => $refDate
                    , 'RentalRegisterId' => $rentalRegisterId
                    , 'CancellationType' => $cancelType
                    , 'EffectFrom' => $vacateDate
                    , 'Payable' => $totalPayable
                    , 'Reason' => $cancelReason
                    , 'TemplateId' => $templateId
                    , 'TemplateContent' => $templateContent));
                    $update->where("RentalCancelRegisterId=$rentalCancelRegId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    for ($i = 1; $i <= $householdCount; $i++) {
                        if ($postData['household_id_' . $i] != "" && $postData['household_id_' . $i] != "0") {

                            $householdId = $this->bsf->isNullCheck($postData['household_id_' . $i], 'number');
                            $householdValue = $this->bsf->isNullCheck($postData['household_value_' . $i], 'number');
                            $householdActualValue = $this->bsf->isNullCheck($postData['household_actualvalue_' . $i], 'number');
                            $householdTransId = $this->bsf->isNullCheck($postData['household_transid_' . $i], 'number');
                            $householdCheck = $this->bsf->isNullCheck($postData['household_Checkbox_' . $i], 'number');

                            if($householdCheck==1){
                                $householdCheck=1;
                            } else{
                                $householdCheck=0;
                            }
                            if ($householdTransId != 0) {

                                $update = $sql->update();
                                $update->table('PM_RentalCancelFurnitureTrans');
                                $update->set(array('RentalCancelRegisterId' => $rentalCancelRegId
                                , 'FurnitureId' => $householdId
                                , 'ActualQty' => $householdActualValue
                                , 'Verified' => $householdCheck
                                , 'Qty' => $householdValue));
                                $update->where("RentalCancelRegisterId=$rentalCancelRegId AND TransId=$householdTransId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                    for ($i = 1; $i <= $checklistCount; $i++) {
                        if ($postData['checklist_id_' . $i] != "" && $postData['checklist_id_' . $i] != "0") {

                            $checklistId = $this->bsf->isNullCheck($postData['checklist_id_' . $i], 'number');
                            $checklistCheck = $this->bsf->isNullCheck($postData['checklist_Checkbox_' . $i], 'number');
                            $checklistTransId = $this->bsf->isNullCheck($postData['checklist_transid_' . $i], 'number');
                            if($checklistTransId!=0) {
                                if ($checklistCheck == 1) {
                                    $status = "Y";
                                } else {
                                    $status = "N";
                                }
                                if (isset($postData['submiton_' . $i])) {
                                    $submitOn = null;
                                    $submitOn = date('Y-m-d', strtotime($postData['submiton_' . $i]));
                                    if (isset($postData['submiton_' . $i])) {
                                        $update = $sql->update();
                                        $update->table('PM_RentalCancelCheckListTrans');
                                        $update->set(array('RentalCancelRegisterId' => $rentalCancelRegId
                                        , 'ChecklistId' => $checklistId
                                        , 'SubmitOn' => $submitOn
                                        , 'ExecutiveId' => $userId
                                        , 'Status' => $status));
                                        $update->where("RentalCancelRegisterId=$rentalCancelRegId AND TransId=$checklistTransId");
                                        $statement = $sql->getSqlStringForSqlObject($update);
                                    } else {
                                        $insert = $sql->insert();
                                        $insert->into('PM_RentalCancelCheckListTrans');
                                        $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                                        , 'ChecklistId' => $checklistId
                                        , 'SubmitOn' => date('Y-m-d')
                                        , 'ExecutiveId' => $userId
                                        , 'Status' => $status));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                    }
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }

                    for ($i = 1; $i <= $otherCount; $i++) {
                        if ($postData['other_name_' . $i] != "") {
                            $otherName = $this->bsf->isNullCheck($postData['other_name_' . $i], 'string');
                            $otherAmount = $this->bsf->isNullCheck($postData['other_amount_' . $i], 'string');
                            $otherId = $this->bsf->isNullCheck($postData['other_id_' . $i], 'string');

                            if($otherId!=0) {
                                $update = $sql->update();
                                $update->table('PM_RentalCancelPayableTrans');
                                $update->set(array('RentalCancelRegisterId' => $rentalCancelRegId
                                , 'Description' => $otherName
                                , 'Amount' => $otherAmount
                                , 'TransType' => "A"));
                                $update->where("RentalCancelRegisterId=$rentalCancelRegId AND TransId=$otherId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                $insert = $sql->insert();
                                $insert->into('PM_RentalCancelPayableTrans');
                                $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                                , 'Description' => $otherName
                                , 'Amount' => $otherAmount
                                , 'TransType' => "A"));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }

                    //Deduction added
                    for ($i = 1; $i <= $deductionCount; $i++) {
                        if ($postData['deduction_name_' . $i] != "") {
                            $deductionName = $this->bsf->isNullCheck($postData['deduction_name_' . $i], 'string');
                            $deductionAmount = $this->bsf->isNullCheck($postData['deduction_amount_' . $i], 'number');
                            $deductionId = $this->bsf->isNullCheck($postData['deduction_id_' . $i], 'number');

                            if($deductionId!=0) {
                                $update = $sql->update();
                                $update->table('PM_RentalCancelPayableTrans');
                                $update->set(array('RentalCancelRegisterId' => $rentalCancelRegId
                                , 'Description' => $deductionName
                                , 'Amount' => $deductionAmount
                                , 'TransType' => "D"));
                                $update->where("RentalCancelRegisterId=$rentalCancelRegId AND TransId=$deductionId");
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            } else {
                                $insert = $sql->insert();
                                $insert->into('PM_RentalCancelPayableTrans');
                                $insert->Values(array('RentalCancelRegisterId' => $rentalCancelRegId
                                , 'Description' => $deductionName
                                , 'Amount' => $deductionAmount
                                , 'TransType' => "D"));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                    //Security Deposit
                    $securityId = $this->bsf->isNullCheck($postData['securityid'], 'number');
                    $update = $sql->update();
                    $update->table('PM_RentalCancelPayableTrans');
                    $update->set(array('RentalCancelRegisterId' => $rentalCancelRegId
                    , 'Description' => $securityDepName
                    , 'Amount' => $securityDeposit
                    , 'TransType' => "A"));
                    $update->where("RentalCancelRegisterId=$rentalCancelRegId AND TransId=$securityId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lease-Cancellation-Modify','E','Lease-Cancellation',$rentalCancelRegId,0, 0, 'CRM','',$this->auth->getIdentity()->UserId, 0 ,0);
                } catch(PDOException $e) {
                    $connection->rollback();
                }
            }
            $FeedId = $this->params()->fromQuery('FeedId');
            $AskId = $this->params()->fromQuery('AskId');
            if(isset($FeedId) && $FeedId!="") {
                $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
            } else {
                $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"));
            }
//                return $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "register"));

            //return $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "agreement-cancellation"));
        } else {
            //Fetching data from agreement template
            $select = $sql->select();
            $select->from('PM_AgreementTemplate')
                ->columns(array('TemplateId','TemplateName'))
                ->where("AgreementTypeId = '4'")
                ->where("DeleteFlag = '0'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->agreementTemplates = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->modeType = 'add';
            if($this->params()->fromRoute('mode')) {
                $this->_view->modeType = $this->params()->fromRoute('mode');
            }

            $mode = $this->bsf->isNullCheck($this->_view->modeType, 'string');
            $rentalRegisterId = $this->bsf->isNullCheck($this->params()->fromRoute('rentalId'), 'number');

            //Agreement Detail
            $select = $sql->select();
            $select->from("PM_RentalRegister")
                ->columns(array('UnitId','RefNo', 'RefDate', 'RentalType'))
                ->where("RentalRegisterId=$rentalRegisterId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $ownerDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $ownerUnit=$ownerDetail['UnitId'];
            $this->_view->ownerDetail=$ownerDetail;
            $this->_view->ownerUnit=$ownerUnit;

            $select = $sql->select();
            $select->from(array("a" => "Crm_UnitDetails"))
                ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
                ->join(array("b" => "Crm_Leads"), "u.LeadId=b.LeadId", array('LeadName'), $select::JOIN_INNER)
                ->join(array("m" => "KF_UnitMaster"), "a.UnitId=m.UnitId", array('UnitNo'), $select::JOIN_INNER)
                ->join(array("c" => "Proj_ProjectMaster"), "m.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                ->columns(array('UnitId'))
                ->where("a.UnitId=$ownerUnit");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->ownerName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from("PM_RentalTenantTrans")
                ->columns(array('LeaserName'))
                ->where("RentalRegisterId=$rentalRegisterId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->tenantDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from("PM_RentalAgreementTrans")
                ->columns(array('EndDate'))
                ->where("RentalRegisterId=$rentalRegisterId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->agreeDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from("PM_RentalRentTrans")
                ->columns(array('SDAmount','RentAmount'))
                ->where("RentalRegisterId=$rentalRegisterId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->sDepositAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $this->_view->rentalRegisterId=$rentalRegisterId;

            if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"add") == 0) {
                //HouseHold
                $select = $sql->select();
                $select->from(array("a" => "PM_RentalFurnitureTrans"))
                    ->join(array('b' => 'PM_FurnitureMaster'), 'a.FurnitureId=b.FurnitureId', array('FurnitureName'), $select::JOIN_INNER)
                    ->columns(array('FurnitureId', 'Qty', 'TransId'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->furnitureDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                $this->_view->holdCount = count($this->_view->furnitureDetail);

                //Checklist
                $select = $sql->select();
                $select->from(array("a" => "PM_RentalCheckListTrans"))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.ChecklistId=b.ChecklistId', array('CheckListName'), $select::JOIN_INNER)
                    ->columns(array('ChecklistId', 'TransId'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->checklistDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toarray();
                $this->_view->checklistCount = count($this->_view->checklistDetail);

            } else if(isset($rentalRegisterId) && $rentalRegisterId!=0 && strcmp(trim($mode),"edit") == 0) {
                $select = $sql->select();
                $select->from("PM_RentalCancelRegister")
                    ->columns(array('RefNo', 'RefDate', 'CancellationType', 'EffectFrom', 'Payable','Reason','RentalCancelRegisterId','TemplateId','TemplateContent'))
                    ->where("RentalRegisterId=$rentalRegisterId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $cancelRegDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $rentalCancelId = $cancelRegDetail['RentalCancelRegisterId'];
                $this->_view->cancelRegDetail=$cancelRegDetail;
                $this->_view->rentalCancelId=$rentalCancelId;

                $select = $sql->select();
                $select->from(array("a"=>"PM_RentalCancelFurnitureTrans"))
                    ->join(array('b' => 'PM_FurnitureMaster'), 'a.FurnitureId=b.FurnitureId', array('FurnitureName'), $select::JOIN_INNER)
                    ->columns(array('FurnitureId', 'Qty', 'Verified','ActualQty','TransId'))
                    ->where("RentalCancelRegisterId=$rentalCancelId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->furnitureDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->holdCount = count($this->_view->furnitureDetail);

                $select = $sql->select();
                $select->from(array("a"=>"PM_RentalCancelCheckListTrans"))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.ChecklistId=b.ChecklistId', array('CheckListName'), $select::JOIN_INNER)
                    ->columns(array('ChecklistId', 'SubmitOn', 'ExecutiveId','Status','TransId'))
                    ->where("RentalCancelRegisterId=$rentalCancelId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->checklistDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->checklistCount = count($this->_view->checklistDetail);

                $select = $sql->select();
                $select->from("PM_RentalCancelPayableTrans")
                    ->columns(array('Description', 'Amount','TransId'))
                    ->where("RentalCancelRegisterId=$rentalCancelId AND TransType='A' AND Description != 'Security Deposit'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->additionDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->additionCount = count($this->_view->additionDetail);

                $select = $sql->select();
                $select->from("PM_RentalCancelPayableTrans")
                    ->columns(array('Description', 'Amount','TransId'))
                    ->where("RentalCancelRegisterId=$rentalCancelId AND TransType='D'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->deductionDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->deductionCount = count($this->_view->deductionDetail);

                $select = $sql->select();
                $select->from("PM_RentalCancelPayableTrans")
                    ->columns(array('Amount','TransId'))
                    ->where("RentalCancelRegisterId=$rentalCancelId AND TransType='A' AND Description='Security Deposit'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->securityDeposit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            }
        }

        $this->_view->mergeTags = $this->bsf->getMergeTags(4);

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function regDetailAction(){
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
        $userName = $this->auth->getIdentity()->EmployeeName;
        $userId = $this->auth->getIdentity()->UserId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Print_r($postParams);die;
                $request = $postParams['regId'];
                $select = $sql->select();
                $select->from(array("a" => "PM_PMRegister"))
                    ->join(array('f' => 'PM_PMRentOutTrans'), 'a.PMRegisterId=f.PMRegisterId', array('IsRentOut'), $select::JOIN_INNER)
                    ->join(array('e' => 'PM_RentalRegister'), 'a.UnitId=e.UnitId', array('RefNo'), $select::JOIN_INNER)
                    ->join(array('b' => 'PM_RentalRentTrans'), 'e.RentalRegisterId=b.RentalRegisterId', array('RentAmount','RentalRegisterId','DueDay','GracePeriod','LateFees','SDAmount'), $select::JOIN_INNER)
                    ->join(array('c' => 'PM_RentalAgreementTrans'), 'e.RentalRegisterId=c.RentalRegisterId', array('AgtDate','StartDate','LeasePeriod'), $select::JOIN_INNER)
                    ->join(array('d' => 'PM_RentalTenantTrans'), 'e.RentalRegisterId=d.RentalRegisterId', array('LeaserName',"Add"=>'Address','TransId'), $select::JOIN_INNER)
                    ->where(array("a.PMRegisterId"=>$request,"f.IsRentOut"=>1));
                if(isset($projectId)){
                    $select -> join(array("e" => "KF_UnitMaster"), "a.UnitId=e.UnitId", array(), $select::JOIN_INNER);
                    $select->where(array('e.ProjectId' => $projectId));;
                }
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->regdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }
        }
    }
    public function rentalEntryAction(){
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
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();
        $userId = $this->auth->getIdentity()->UserId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $mode = $this->bsf->isNullCheck($postData['mode'], 'string');
                if($mode=="pre") {

                    $unitId = $this->bsf->isNullCheck($postData['UnitId'], 'string');

                    $select = $sql->select();
                    $select->from('PM_RentBillRegister')
                        ->columns(array('*'))
                        ->where(array('DeleteFlag' => 0,'UnitId'=>$unitId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $list['previous'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('PM_RentBillRegister')
                        ->columns(array("tAmount"=>new Expression("sum(TotalAmountPayable)")))
                        ->where(array('UnitId'=>$unitId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $list['overAllBill'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ReceiptRegister'))
                        ->columns(array("tAmount"=>new Expression("sum(a.Amount)")))
                        ->where(array("a.UnitId"=>$unitId ,"ReceiptAgainst"=>"R","a.DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $list['overAllPaid'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $list['reAmt']=false;
                    $list['oAmt']=false;
                    $list['date']="";
                    if(isset($list['previous']) && $list['previous']['RegisterId']!=null) {
                        $rId=$list['previous']['RegisterId'];
                        $list['date'] = date('d-m-Y', strtotime($list['previous']['PVDate']));
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array("tAmount"=>new Expression("sum(a.Amount)")))
                            ->where(array("a.RentRegisterId"=>$rId ,"a.DeleteFlag"=>0));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $list['reAmt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'PM_RentBillTrans'))
                            ->columns(array("tAmount"=>new Expression("sum(a.Amount)")))
                            ->where(array("a.RegisterId"=>$rId ,'TransType' => 'R'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $list['oAmt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    }

                } else {
                    $select = $sql->select();
                    $select->from(array('a' =>'PM_RentBillRegister'))
                        ->columns(array('RentalRegisterId'))
                        ->join(array("b"=>"PM_RentalRentTrans"), "a.RentalRegisterId=b.RentalRegisterId", array('RentAmount'), $select::JOIN_INNER)
                        ->where(array('a.UnitId' => $postData['unitId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $list['rental'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($list));
                return $response;

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                try {

                    $postParams = $request->getPost();
                    if ($postParams['entry_date']) {
                        $pvDate = date('Y-m-d H:i:s', strtotime($postParams['entry_date']));
                    } else {
                        $pvDate = date('Y-m-d H:i:s');
                    }
                    if ($postParams['target_from']) {
                        $tFrom = date('Y-m-d H:i:s', strtotime($postParams['target_from']));
                    } else {
                        $tFrom = date('Y-m-d H:i:s');
                    }
                    if ($postParams['target_to']) {
                        $tTo = date('Y-m-d H:i:s', strtotime($postParams['target_to']));
                    } else {
                        $tTo = date('Y-m-d H:i:s');
                    }
                    if ($postParams['dueDateVal']) {
                        $dueDateVal = date('Y-m-d H:i:s', strtotime($postParams['dueDateVal']));
                    } else {
                        $dueDateVal = date('Y-m-d H:i:s');
                    }

                    // $pvNo = $extraRequestNo;
                    $unitId = $this->bsf->isNullCheck($postParams['unitId'], 'number');
                    $rentalCharge = $this->bsf->isNullCheck($postParams['rent_charge'], 'number');
                    $mainBillAmt = $this->bsf->isNullCheck($postParams['main_bill'], 'number');
                    $ebChargeAmt = $this->bsf->isNullCheck($postParams['eb_charge'], 'number');
                    $teleChargeAmt = $this->bsf->isNullCheck($postParams['tele_charge'], 'number');
                    $mainBillId = $this->bsf->isNullCheck($postParams['main_id'], 'number');
                    $ebChargeId = $this->bsf->isNullCheck($postParams['eb_id'], 'number');
                    $teleChargeId = $this->bsf->isNullCheck($postParams['tele_id'], 'number');
                    $grossAmt = $this->bsf->isNullCheck($postParams['gross_amount'], 'number');
                    $serviceTax = $this->bsf->isNullCheck($postParams['service_tax'], 'number');
                    $totalAmount = $this->bsf->isNullCheck($postParams['total_amount'], 'number');
                    $lastBill = $this->bsf->isNullCheck($postParams['last_bill'], 'number');
                    $lateFee = $this->bsf->isNullCheck($postParams['late_fee'], 'number');
                    $excessAmt = $this->bsf->isNullCheck($postParams['excess_amt'], 'number');
                    $totalPayable = $this->bsf->isNullCheck($postParams['total_payable'], 'number');
                    $totalPayAmount = $this->bsf->isNullCheck($postParams['totalpayamount'], 'number');
                    $remarks = $this->bsf->isNullCheck($postParams['remarks'], 'string');
                    $tenant = $this->bsf->isNullCheck($postParams['tenant_name'], 'string');
                    $RentalRegisterId = $this->bsf->isNullCheck($postParams['RentalRegId'], 'number');

                    $connection->beginTransaction();
                    $sVno = $this->bsf->isNullCheck($postParams['voucher_no'], 'string');
                    $aVNo = CommonHelper::getVoucherNo(821, date('Y-m-d', strtotime($pvDate)), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == true) {
                        $sVno = $aVNo["voucherNo"];
                    }
                    $update = $sql->update();
                    $update->table('PM_RentBillRegister');
                    $update->set(array('DeleteFlag' => 1,'PreviousBill'=>0));
                    $update->where(array('UnitId' => $unitId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('PM_RentBillRegister')
                        ->columns(array('RegisterId'))
                        ->where(array('DeleteFlag' => 1,'UnitId'=>$unitId))
                        ->order('RegisterId desc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $previous = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $update = $sql->update();
                    $update->table('PM_RentBillRegister');
                    $update->set(array('PreviousBill' => 1));
                    $update->where(array('RegisterId' => $previous['RegisterId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $insert = $sql->insert('PM_RentBillRegister');
                    $newData = array(
                        'PVDate' => $pvDate,
                        'PVNo' => $sVno,
                        'UnitId' => $unitId,
                        'TenantName' => $tenant,
                        'RentalCharge' => $rentalCharge,
                        'GrossAmount' => $grossAmt,
                        'RentPeriodFrom' =>$tFrom,
                        'RentPeriodTo' =>$tTo,
                        'ServiceTax' => $serviceTax,
                        'TotalAmount' => $totalAmount,
                        'PreviousBillDue' => $lastBill,
                        'LateFees' => $lateFee,
                        'TotalPayable' => $totalPayable,
                        'TotalAmountPayable' => $totalPayAmount,
                        'RentalRegisterId' => $RentalRegisterId,
                        'Remarks' => $remarks,
                        'DueDate' => $dueDateVal,
                        'ExcessAmount' =>$excessAmt
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    $service = array("$mainBillId" => "$mainBillAmt", "$ebChargeId" => "$ebChargeAmt", "$teleChargeId" => "$teleChargeAmt");
                    foreach ($service as $id => $amount):
                        if ($id == 0) {
                            continue;
                        }
                        $insert = $sql->insert();
                        $insert->into('PM_RentBillTrans');
                        $insert->Values(array('RegisterId' => $registerId, 'ServiceId' => $id, 'TransType' => 'R', 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    endforeach;

                    $connection->commit();

                    CommonHelper::insertLog(date('Y-m-d H:i:s'), 'RentalBill-Entry-Add', 'N', 'RentalBill-Entry', $registerId, 0, 0, 'CRM', $sVno, $userId, 0, 0);
                    $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "rental-register"));

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            } else {
                $aVNo = CommonHelper::getVoucherNo(821, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == false){
                    $this->_view->svNo = "";}
                else {
                    $this->_view->svNo = $aVNo["voucherNo"];}

                $select = $sql->select();
                $select->from(array("a" => "KF_UnitMaster"))
                    ->join(array("e" => "PM_RentalRegister"), "a.UnitId=e.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_UnitBooking"), "e.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                    ->join(array("h" => "Crm_ProjPropertyManagement"), "c.ProjectId=h.ProjectId", array('DueDayOfMonth'), $select::JOIN_LEFT)
                    ->join(array("f" => "PM_RentalTenantTrans"), "e.RentalRegisterId=f.RentalRegisterId", array('LeaserName'), $select::JOIN_LEFT)
                    ->join(array("g" => "PM_RentalRentTrans"), "e.RentalRegisterId=g.RentalRegisterId", array('RentalRegisterId', 'RentAmount', 'RentPerPeriod'), $select::JOIN_LEFT)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach ($unitList as &$list) {
                    $select = $sql->select();
                    $select->from(array("a" => "PM_RentalOtherCostTrans"))
                        ->join(array("b" => "PM_ServiceMaster"), "a.ServiceId=b.ServiceId", array('ServiceName', 'ServiceId'), $select::JOIN_LEFT)
                        ->columns(array('Amount'))
                        ->where(array("RentalRegisterId" => $list['RentalRegisterId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $list['services'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $startDate= date('Y-m-d')." 00:00:00";
                $select = $sql->select();
                $select->from(array('a' => 'FA_QualPeriod'))
                    ->columns(array('PeriodId','PeriodName','FDate' => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')") ,'TDate' => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
                    ->where("a.QualType ='S'");
                $select->where->greaterThanOrEqualTo('TDate', $startDate)
                    ->lessThanOrEqualTo('FDate', $startDate);
                $statement = $sql->getSqlStringForSqlObject($select);
                $periodlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($periodlist==false) {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_WorkTypeMaster'))
                        ->join(array("b"=>"FA_ServiceTaxSetting"), new Expression("a.WorkType=b.WorkType and b.PeriodId=0"), array('TaxablePer','TaxPer','SurCharge','KKCess','NetTax','ReversePer','SBCess'), $select::JOIN_LEFT)
                        ->columns(array('WorkTypeName','WorkType'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->stlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();;

                } else {
                    $perId = $periodlist['PeriodId'];
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_WorkTypeMaster'))
                        ->join(array("b"=>"FA_ServiceTaxSetting"), new Expression("a.WorkType=b.WorkType and b.PeriodId=$perId"), array('TaxablePer','TaxPer','SurCharge','KKCess','NetTax','ReversePer','SBCess'), $select::JOIN_LEFT)
                        ->columns(array('WorkTypeName','WorkType'))
                        ->where("a.WorkType ='G'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->stlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();;

                }

                $this->_view->unitList = $unitList;
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function paymentRegisterAction(){
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
        //total Payment//

        $select = $sql->select();
        $select->from(array('a' =>'PM_PaymentRegister'))
            ->columns(array('RegisterId' => new expression('count(*)')))
            ->where(array('DeleteFlag'=>'0'));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->paymentreg = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array("a" => "PM_PaymentRegister"))
            ->join(array('p' => 'KF_UnitMaster'), 'a.UnitId=p.UnitId', array('UnitNo'), $select::JOIN_LEFT)
            ->join(array("b" => "Crm_UnitBooking"), 'a.UnitId=b.UnitId', array(), $select::JOIN_INNER)
            ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array('LeadName'), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "p.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
            ->order('a.RegisterId desc');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->payment = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        return $this->_view;
    }

    public function paymentAction(){
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
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $select = $sql->select();
                $select->from(array('a' =>'PM_RentalRegister'))
                    ->columns(array('RentalRegisterId'))
                    ->join(array("b"=>"PM_RentalRentTrans"), "a.RentalRegisterId=b.RentalRegisterId", array('RentAmount'), $select::JOIN_INNER)
                    ->where(array('a.UnitId' => $postData['unitId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $list['rental'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' =>'PM_MaintenanceBillRegister'))
                    ->join(array("b"=>"PM_MaintenanceBillTrans"), "a.RegisterId=b.RegisterId", array('Amount'), $select::JOIN_LEFT)
                    ->join(array("c"=>"PM_ServiceMaster"), "b.ServiceId=c.serviceId", array('ServiceName','ServiceId'), $select::JOIN_LEFT)
                    ->where(array('a.UnitId' => $postData['unitId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $list['payment'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' =>'PM_MaintenanceBillRegister'))
                    ->columns(array("Total"=>new Expression("Sum(b.Amount)")))
                    ->join(array("b"=>"PM_MaintenanceBillTrans"), "a.RegisterId=b.RegisterId", array(), $select::JOIN_LEFT)
                    ->where(array('a.UnitId' => $postData['unitId']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $list['total'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($list));
                return $response;

            }
        } else {
            $request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {

                if ($request->isPost()) {
                    $postData = $request->getPost();
                    //Print_r($postData);die;
                    $pvDate = $this->bsf->isNullCheck($postData['pay_date'],'string');
                    $pvNo = $this->bsf->isNullCheck($postData['pay_no'],'string');
                    $unitId = $this->bsf->isNullCheck($postData['unitId'],'string');
                    $rentalRegId = $this->bsf->isNullCheck($postData['rentalRegId'],'number');
                    $receiptAmount = $this->bsf->isNullCheck($postData['rent_amount'],'number');
                    $paymentAmt = $this->bsf->isNullCheck($postData['payment_amount'],'number');
                    $totalPayAmount = $this->bsf->isNullCheck($postData['amount'],'number');
                    $chequeno = $this->bsf->isNullCheck($postData['cheque_no'],'string');
                    $chequedate = $this->bsf->isNullCheck($postData['cheque_date'],'string');
                    $bank = $this->bsf->isNullCheck($postData['bank_name'],'string');
                    $remarks = $this->bsf->isNullCheck($postData['remarks'],'string');
                    $count=$this->bsf->isNullCheck($postData['RowCount'],'number');
                    $insert  = $sql->insert('PM_PaymentRegister');
                    $newData = array(
                        'PVDate' => date('Y/m/d H:i:s', strtotime($pvDate)),
                        'PVNo' => $pvNo,
                        'UnitId' => $unitId,
                        'ReceiptAmount' => $receiptAmount,
                        'RentalRegisterId' => $rentalRegId,
                        'PaymentAmount' => $paymentAmt,
                        'Amount' => $totalPayAmount,
                        'ChequeNo' => $chequeno,
                        'ChequeDate' => date('Y/m/d H:i:s', strtotime($chequedate)),
                        'BankName' => $bank,
                        'Remarks' => $remarks
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    $count=$postData['RowCount'];
                    if($count!=0){
                        for($i=1;$i<=$count;$i++){
                            $ser=$postData['service_'.$i];
                            $sam=$postData['sample_'.$i];

                            $select = $sql->insert('PM_PaymentTrans');
                            $newData = array(
                                'RegisterId' =>$registerId,
                                'ServiceId' =>$this->bsf->isNullCheck($sam,'number'),
                                'TransType' => 'P',
                                'Amount' =>$this->bsf->isNullCheck($ser,'number'),
                            );
                            $select->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }}

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Payment-Entry-Add','N','Payment-Entry',$registerId,0, 0, 'CRM', $pvNo,$userId, 0 ,0);
                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute('crm/register', array('controller' => 'property', 'action' => 'payment-register'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('crm/register', array('controller' => 'property', 'action' => 'payment-register'));
                    }
//                    $this->redirect()->toRoute('crm/register', array('controller' => 'pm', 'action' => 'payment-register'));
                } else {
                    $select = $sql->select();
                    $select->from(array("a" => "KF_UnitMaster"))
                        ->join(array("b" => "Crm_UnitBooking"), new Expression("a.UnitId=b.UnitId and b.DeleteFlag=0"), array(), $select::JOIN_INNER)
                        ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                        ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function rentalRegisterAction(){
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
        /* added lines */
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "KF_UnitMaster"))
            ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
            ->join(array("b" => "Crm_UnitBooking"), "e.UnitId=b.UnitId", array(), $select::JOIN_INNER)
            ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array('LeadName'), $select::JOIN_INNER)
            ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array('ProjectName'), $select::JOIN_INNER)
            ->join(array("f" => "PM_RentBillRegister"),"e.RentalRegisterId=f.RentalRegisterId",array('PVNo','RegisterId','PVDate','TotalAmountPayable'),$select::JOIN_LEFT)
            ->columns(array('UnitNo','UnitId'))
            ->where("f.DeleteFlag='0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->payList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        /*
        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from(array("a" => "KF_UnitMaster"))
            ->join(array("b" => "Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
           ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
             ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
            ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
            ->join(array("g" => "PM_RentalRentTrans"),"e.RentalRegisterId=g.RentalRegisterId",array('RentalRegisterId','RentAmount'),$select::JOIN_LEFT)
            ->join(array("f" => "PM_MaintenanceBillRegister"),"a.UnitId=f.UnitId",array('RegisterId','NetAmount'),$select::JOIN_INNER)
             ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")));
        $statement = $sql->getSqlStringForSqlObject($select);
        $unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($unitList as &$list) {
            $select = $sql->select();
            $select->from(array("a" => "PM_MaintenanceBillTrans"))
                ->join(array("b" => "PM_ServiceMaster"),"a.ServiceId=b.ServiceId",array('ServiceName','ServiceId'),$select::JOIN_LEFT)
                ->columns(array('Amount'))
                ->where(array("RegisterId" => $list['RegisterId']));
            $statement = $sql->getSqlStringForSqlObject($select);
            $list['services'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        $this->_view->unitList = $unitList;*/


        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

        return $this->_view;
    }

    public function rentalEditAction(){
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
        $userId = $this->auth->getIdentity()->UserId;

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
                try {
                    $connection = $dbAdapter->getDriver()->getConnection();
                    $connection->beginTransaction();

                    $postParams = $request->getPost();

                    if ($postParams['entry_date']) {
                        $pvDate = date('Y-m-d H:i:s', strtotime($postParams['entry_date']));
                    } else {
                        $pvDate = date('Y-m-d H:i:s');
                    }
                    if ($postParams['target_from']) {
                        $tFrom = date('Y-m-d H:i:s', strtotime($postParams['target_from']));
                    } else {
                        $tFrom = date('Y-m-d H:i:s');
                    }
                    if ($postParams['target_to']) {
                        $tTo = date('Y-m-d H:i:s', strtotime($postParams['target_to']));
                    } else {
                        $tTo = date('Y-m-d H:i:s');
                    }
                    if ($postParams['dueDateVal']) {
                        $dueDateVal = date('Y-m-d H:i:s', strtotime($postParams['dueDateVal']));
                    } else {
                        $dueDateVal = date('Y-m-d H:i:s');
                    }

                    // $pvNo = $extraRequestNo;
                    $unitId = $this->bsf->isNullCheck($postParams['unitId'], 'number');
                    $rentalCharge = $this->bsf->isNullCheck($postParams['rent_charge'], 'number');
                    $mainBillAmt = $this->bsf->isNullCheck($postParams['main_bill'], 'number');
                    $ebChargeAmt = $this->bsf->isNullCheck($postParams['eb_charge'], 'number');
                    $teleChargeAmt = $this->bsf->isNullCheck($postParams['tele_charge'], 'number');
                    $mainBillId = $this->bsf->isNullCheck($postParams['main_id'], 'number');
                    $ebChargeId = $this->bsf->isNullCheck($postParams['eb_id'], 'number');
                    $teleChargeId = $this->bsf->isNullCheck($postParams['tele_id'], 'number');
                    $grossAmt = $this->bsf->isNullCheck($postParams['gross_amount'], 'number');
                    $serviceTax = $this->bsf->isNullCheck($postParams['service_tax'], 'number');
                    $totalAmount = $this->bsf->isNullCheck($postParams['total_amount'], 'number');
                    $lastBill = $this->bsf->isNullCheck($postParams['last_bill'], 'number');
                    $lateFee = $this->bsf->isNullCheck($postParams['late_fee'], 'number');
                    $excessAmt = $this->bsf->isNullCheck($postParams['excess_amt'], 'number');
                    $totalPayable = $this->bsf->isNullCheck($postParams['total_payable'], 'number');
                    $totalPayAmount = $this->bsf->isNullCheck($postParams['totalpayamount'], 'number');
                    $remarks = $this->bsf->isNullCheck($postParams['remarks'], 'string');
                    $tenantName = $this->bsf->isNullCheck($postParams['tenant_name'], 'string');
                    $RentalRegisterId = $this->bsf->isNullCheck($postParams['RentalRegId'], 'number');
                    $RegisterId = $this->bsf->isNullCheck($postParams['RegisterId'], 'number');
                    $pvNo = $this->bsf->isNullCheck($postParams['voucher_no'], 'string');

                    $update = $sql->update();
                    $update->table('PM_RentBillRegister');
                    $update->set(array('DeleteFlag' => 1,'PreviousBill'=>0));
                    $update->where("UnitId=$unitId and RegisterId<>$RegisterId");
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from('PM_RentBillRegister')
                        ->columns(array('RegisterId'))
                        ->where(array('DeleteFlag' => 1,'UnitId'=>$unitId))
                        ->order('RegisterId desc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $previous = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $update = $sql->update();
                    $update->table('PM_RentBillRegister');
                    $update->set(array('PreviousBill' => 1));
                    $update->where(array('RegisterId' => $previous['RegisterId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $update = $sql->update();
                    $update->table('PM_RentBillRegister')
                        ->set(array('PVDate' => $pvDate,
                            'PVNo' => $pvNo,
                            'UnitId' => $unitId,
                            'RentalCharge' => $rentalCharge,
                            'GrossAmount' => $grossAmt,
                            'RentPeriodFrom' =>$tFrom,
                            'RentPeriodTo' =>$tTo,
                            'ServiceTax' => $serviceTax,
                            'TotalAmount' => $totalAmount,
                            'PreviousBillDue' => $lastBill,
                            'LateFees' => $lateFee,
                            'TotalPayable' => $totalPayable,
                            'TotalAmountPayable' => $totalPayAmount,
                            'RentalRegisterId' => $RentalRegisterId,
                            'Remarks' => $remarks,
                            'DueDate' => $dueDateVal,
                            'TenantName' => $tenantName,
                            'ExcessAmount' => $excessAmt))
                        ->where(array('RegisterId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('PM_RentBillTrans')
                        ->where("RegisterId='$RegisterId'");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $service = array("$mainBillId"=>"$mainBillAmt", "$ebChargeId"=>"$ebChargeAmt", "$teleChargeId"=>"$teleChargeAmt");
                    foreach($service as $id => $amount):
                        if($id == 0){
                            continue;
                        }
                        $insert = $sql->insert();
                        $insert->into( 'PM_RentBillTrans' );
                        $insert->Values( array( 'RegisterId' => $RegisterId, 'ServiceId' => $id, 'TransType' => 'P', 'Amount' => $amount));
                        $statement = $sql->getSqlStringForSqlObject( $insert );
                        $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
                    endforeach;
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'RentalBill-Entry-Modify','E','RentalBill-Entry',$RegisterId,0, 0, 'CRM', $pvNo,$userId, 0 ,0);

                    $this->redirect()->toRoute("crm/default", array("controller" => "property","action" => "rental-register"));
                } catch (PDOException $e) {
                    $connection->rollback();
                }
            }
            else{
                $RegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'RegisterId' ), 'number' );
                if($RegisterId==0){
                    $this->redirect()->toRoute("crm/default", array("controller" => "pm", "action" => "rental-register"));
                }

                $select = $sql->select();
                $select->from(array("a" => "KF_UnitMaster"))
                    ->join(array("e" =>"PM_RentalRegister"),"a.UnitId=e.UnitId",array(), $select::JOIN_INNER)
                    ->join(array("b" => "Crm_UnitBooking"), "e.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("d" => "Crm_Leads"), "d.LeadId=b.LeadId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "a.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                    ->join(array("h" => "Crm_ProjPropertyManagement"), "c.ProjectId=h.ProjectId", array('DueDayOfMonth'), $select::JOIN_LEFT)
                    ->join(array("g" => "PM_RentalTenantTrans"),"e.RentalRegisterId=g.RentalRegisterId",array('LeaserName'),$select::JOIN_LEFT)
                    ->join(array("f" => "PM_RentBillRegister"),"e.RentalRegisterId=f.RentalRegisterId",array('PVNo','RegisterId','PVDate','RentalCharge','GrossAmount','RentPeriodFrom','RentPeriodTo','ServiceTax','TotalAmount','LateFees','TotalPayable','TotalAmountPayable','PreviousBillDue','RentalRegisterId','Remarks','DueDate','TenantName'),$select::JOIN_LEFT)
                    ->columns(array('data' => 'UnitId', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName + ')'")))
                    ->where("f.DeleteFlag='0'AND f.RegisterId='$RegisterId'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $payVal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $unitId = $payVal['data'];

                $this->_view->payVal=$payVal;
                //Service Tax
                $select = $sql->select();
                $select->from(array("a" => "PM_RentBillTrans"))
                    ->join(array("b" => "PM_ServiceMaster"),"a.ServiceId=b.ServiceId",array('ServiceName'),$select::JOIN_LEFT)
                    ->columns(array('ServiceId','Amount'))
                    ->where(array("a.RegisterId" => $RegisterId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->payService = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $startDate= date('Y-m-d')." 00:00:00";
                $select = $sql->select();
                $select->from(array('a' => 'FA_QualPeriod'))
                    ->columns(array('PeriodId','PeriodName','FDate' => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')") ,'TDate' => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
                    ->where("a.QualType ='S'");
                $select->where->greaterThanOrEqualTo('TDate', $startDate)
                    ->lessThanOrEqualTo('FDate', $startDate);
                $statement = $sql->getSqlStringForSqlObject($select);
                $periodlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($periodlist==false) {
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_WorkTypeMaster'))
                        ->join(array("b"=>"FA_ServiceTaxSetting"), new Expression("a.WorkType=b.WorkType and b.PeriodId=0"), array('TaxablePer','TaxPer','SurCharge','KKCess','NetTax','ReversePer','SBCess'), $select::JOIN_LEFT)
                        ->columns(array('WorkTypeName','WorkType'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->stlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();;

                } else {
                    $perId = $periodlist['PeriodId'];
                    $select = $sql->select();
                    $select->from(array('a' => 'WPM_WorkTypeMaster'))
                        ->join(array("b"=>"FA_ServiceTaxSetting"), new Expression("a.WorkType=b.WorkType and b.PeriodId=$perId"), array('TaxablePer','TaxPer','SurCharge','KKCess','NetTax','ReversePer','SBCess'), $select::JOIN_LEFT)
                        ->columns(array('WorkTypeName','WorkType'))
                        ->where("a.WorkType ='G'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->stlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();;

                }

                $select = $sql->select();
                $select->from(array('a' => 'Crm_ReceiptRegister'))
                    ->columns(array("ReceiptId"))
                    ->where(array("a.RentRegisterId"=>$RegisterId ,"a.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $receiptDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->rCount=count($receiptDetail);
                //Previous Details
                $list['reAmt']=false;
                $list['oAmt']=false;
                $list['date']="";
                if(isset($payVal) && !is_null($payVal['RegisterId']) && $payVal['RegisterId']!=0) {
                    $rId=$payVal['RegisterId'];
                    $list['date'] = date('d-m-Y', strtotime($payVal['PVDate']));

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ReceiptRegister'))
                        ->columns(array("tAmount"=>new Expression("sum(a.Amount)")))
                        ->where(array("a.RentRegisterId"=>$rId ,"a.DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $list['reAmt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array('a' => 'PM_RentBillTrans'))
                        ->columns(array("tAmount"=>new Expression("sum(a.Amount)")))
                        ->where(array("a.RegisterId"=>$rId ,'TransType' => 'R'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $list['oAmt'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }

                $this->_view->list=$list;
            }

            //begin trans try block example starts
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function paymentEditAction(){
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

        $userId = $this->auth->getIdentity()->UserId;

        $sql     = new Sql($dbAdapter);

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
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();
                    $registerId = $this->bsf->isNullCheck($postData['registerId'], 'number');
                    //Print_r($postData); die;
                    $pvDate = $this->bsf->isNullCheck($postData['pay_date'], 'string');
                    $pvNo = $this->bsf->isNullCheck($postData['pay_no'], 'string');
                    $unitId = $this->bsf->isNullCheck($postData['unitId'], 'string');
                    $rentalRegId = $this->bsf->isNullCheck($postData['rentalRegId'], 'number');
                    $receiptAmount = $this->bsf->isNullCheck($postData['rent_amount'], 'number');
                    $paymentAmt = $this->bsf->isNullCheck($postData['payment_amount'], 'number');
                    $totalPayAmount = $this->bsf->isNullCheck($postData['amount'], 'number');
                    $chequeno = $this->bsf->isNullCheck($postData['cheque_no'], 'string');
                    $chequedate = $this->bsf->isNullCheck($postData['cheque_date'], 'string');
                    $bank = $this->bsf->isNullCheck($postData['bank_name'], 'string');
                    $remarks = $this->bsf->isNullCheck($postData['remarks'], 'string');

                    $delete = $sql->delete();
                    $delete->from('PM_PaymentTrans')
                        ->where(array('RegisterId' => $registerId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $count = $postData['RowCount'];
                    if ($count != 0) {
                        for ($i = 1; $i <= $count; $i++) {
                            $ser = $postData['service_' . $i];
                            $sam = $postData['sample_' . $i];
                            $select = $sql->insert('PM_PaymentTrans');
                            $newData = array(
                                'RegisterId' => $registerId,
                                'ServiceId' => $this->bsf->isNullCheck($sam, 'number'),
                                'TransType' => 'P',
                                'Amount' => $this->bsf->isNullCheck($ser, 'number'),
                            );
                            $select->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $update = $sql->update();
                    $update->table('PM_PaymentRegister');
                    $update->set(array(
                        'PVDate' => date('Y/m/d H:i:s', strtotime($pvDate)),
                        'PVNo' => $pvNo,
                        'UnitId' => $unitId,
                        'RentalRegisterId' => $rentalRegId,
                        'ReceiptAmount' => $receiptAmount,
                        'PaymentAmount' => $paymentAmt,
                        'Amount' => $totalPayAmount,
                        'ChequeNo' => $chequeno,
                        'ChequeDate' => date('Y/m/d H:i:s', strtotime($chequedate)),
                        'BankName' => $bank,
                        'Remarks' => $remarks
                    ));
                    $update->where(array('RegisterId' => $registerId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    // $registerId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Payment-Entry-Modify','E','Payment-Entry',$registerId,0, 0, 'CRM', $pvNo,$userId, 0 ,0);

                    $this->redirect()->toRoute('crm/register', array('controller' => 'property', 'action' => 'payment-register'));

                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $registerId = $this->params()->fromRoute('RegisterId');

                $select = $sql->select();
                $select->from(array('a' =>'PM_PaymentRegister'))
                    ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array('UnitNo'), $select::JOIN_LEFT)
                    ->join(array("e" => "Crm_UnitBooking"), "a.UnitId=e.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("d" => "Crm_Leads"), "d.LeadId=e.LeadId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "Proj_ProjectMaster"), "b.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
                    ->columns(array('data' => 'UnitId','PVDate','PaymentAmount','ReceiptAmount','PVNo','RentalRegisterId','ChequeNo','ChequeDate','BankName','Amount','Remarks', 'value' => new Expression("ProjectName + ' : ' + UnitNo + ' ('+LeadName+ ')'")))
                    ->where(array('a.RegisterId' => $registerId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultpay = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a" => "PM_PaymentTrans"))
                    ->join(array("b" => "PM_ServiceMaster"),"a.ServiceId=b.ServiceId",array('ServiceName'),$select::JOIN_LEFT)
                    ->columns(array('ServiceId','Amount'))
                    ->where(array("a.RegisterId" => $registerId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resulttrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from('PM_ServiceMaster')
                    ->columns(array('ServiceId','ServiceName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsservice = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->registerId=$registerId;
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function rentaldeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('PM_RentBillRegister')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('RegisterId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'RentalBill-Entry-Delete','D','RentalBill-Entry',$RegisterId,0, 0, 'CRM', '',$userId, 0 ,0);


                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
    public function paymentDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $RegisterId = $this->bsf->isNullCheck($this->params()->fromPost('RegisterId'),'number');
                    $Remarks = $this->bsf->isNullCheck($this->params()->fromPost('Remarks'),'string');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $update = $sql->update();
                    $update->table('PM_PaymentRegister')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('RegisterId' => $RegisterId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Payment-Entry-Delete','D','Payment-Entry',$RegisterId,0, 0, 'CRM', '',$userId, 0 ,0);

                    $status = 'deleted';
                } catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }

                $response->setContent($status);
                return $response;
            }
        }
    }

    public function ticketEntryAction(){
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
        $ticketId = $this->params()->fromRoute('TicketId');

        $sql = new Sql($dbAdapter);
        $userName = $this->auth->getIdentity()->EmployeeName;
        $userId = $this->auth->getIdentity()->UserId;
        $leadId = $this->params()->fromRoute('leadId');

        if($ticketId !=0){
            $select = $sql->select();
            $select->from(array("a" => "Crm_TicketRegister"))
                ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
                ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
                //->join(array("d" =>"Crm_Leads"),"a.LeadId=d.LeadId",array("Email"), $select::JOIN_LEFT)
                ->where("a.TicketId=$ticketId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->ticketedit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        }

        //Executives//
        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserId','EmployeeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Lead Name//
        $select = $sql->select();
        $select->from('Crm_Leads')
            ->columns(array('data' => 'LeadId', 'value'=>'LeadName','mail'=>'Email','phone'=>'Mobile'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }} else {
            $request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {

                if ($request->isPost()) {
                    $postData = $request->getPost();
                    //Print_r($postData);die;
                    $requester = $this->bsf->isNullCheck($postData['requester'],'string');
                    $leadId = $this->bsf->isNullCheck($postData['LeadId'],'number');
                    $mailId = $this->bsf->isNullCheck($postData['mailId'],'string');
                    $phone = $this->bsf->isNullCheck($postData['phonenumber'],'number');
                    $subject = $this->bsf->isNullCheck($postData['subject'],'string');
                    $type = $this->bsf->isNullCheck($postData['type'],'string');
                    $status = $this->bsf->isNullCheck($postData['status'],'string');
                    $description = $this->bsf->isNullCheck($postData['description'],'string');
                    //$tags = $this->bsf->isNullCheck($postData['tags'],'string');
                    $priority = $this->bsf->isNullCheck($postData['priority'],'string');

                    $tGroup = $this->bsf->isNullCheck($postData['tgroup'],'string');
                    $updatecli = $this->bsf->isNullCheck($postData['updatecli'],'number');
                    $executiveId = $this->bsf->isNullCheck($postData['executiveId'],'number');
                    $date=date('m-d-Y H:i:s');

                    if($ticketId ==0){
                        $insert  = $sql->insert('Crm_TicketRegister');
                        $newData = array(
                            'CreatedDate' => date('m-d-y'),
                            'Requester' => $requester,
                            'LeadId' => $leadId,
                            'Subject' => $subject,
                            'Type' => $type,
                            'Status' => $status,
                            'Priority' => $priority,
                            'Email' => $mailId,
                            'Mobile' => $phone,
                            'Description' => $description,
                            'TGroup' => $tGroup,
                            'ExecutiveId' => $executiveId,
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $ticketId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        if($type=='Lead' && $leadId!=''){
                            $insert  = $sql->insert('Crm_Leads');
                            $newData = array(
                                'LeadName' => $requester,
                                'Mobile' => $this->bsf->isNullCheck($phone,'string'),
                                'Email' => $this->bsf->isNullCheck($mailId,'string'),
                                'UserId' => $this->bsf->isNullCheck($userId,'number'),
                                'NextCallDate'=>date('m-d-Y H:i:s'),
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }


                        $mailData = array(
                            array(
                                'name' => 'TICKETID',
                                'content' => $ticketId
                            ),
                            array(
                                'name' => 'DATE',
                                'content' => $date
                            ),
                            array(
                                'name' => 'STATUS',
                                'content' => $status
                            )
                        );
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
                        $viewRenderer->MandrilSendMail()->sendMailTo($postData['mailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);


                    }
                    else{
                        $update = $sql->update();
                        $update->table('Crm_TicketRegister');
                        $update->set(array(
                            'ModifiedDate' => date('m-d-y'),
                            'Requester' => $requester,
                            'LeadId' => $leadId,
                            'Subject' => $subject,
                            'Type' => $type,
                            'Description' => $description,
                            'CliUpdate' => $updatecli,
                            'Status' => $status,
                            'Priority' => $priority,
                            'TGroup' => $tGroup,
                            'ExecutiveId' => $executiveId,
                        ));
                        $update->where(array('TicketId'=>$ticketId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        if($postData['updatecli']==1){
                            $mailData = array(
                                array(
                                    'name' => 'TICKETID',
                                    'content' => $ticketId
                                ),
                                array(
                                    'name' => 'DATE',
                                    'content' => $date
                                ),
                                array(
                                    'name' => 'STATUS',
                                    'content' => $status
                                )
                            );
                            $sm = $this->getServiceLocator();
                            $config = $sm->get('application')->getConfig();
                            $viewRenderer->MandrilSendMail()->sendMailTo($postData['MailId'], $config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);
                        }
                    }
                }
                $connection->commit();
                $this->redirect()->toRoute('crm/ticket-register', array('controller' => 'pm', 'action' => 'ticket-register'));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function newAction(){
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
        $userName = $this->auth->getIdentity()->EmployeeName;
        $userId = $this->auth->getIdentity()->UserId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Print_r($postParams);die;
                $request = $postParams['request'];
                $phone = $postParams['phoneId'];
                $email = $postParams['emailId'];

                $insert  = $sql->insert('Crm_Leads');
                $newData = array(
                    'LeadName' => $this->bsf->isNullCheck($request,'string'),
                    'UserId' => $this->bsf->isNullCheck($userId,'number'),
                    'Mobile' => $this->bsf->isNullCheck($phone,'number'),
                    'EMail' => $this->bsf->isNullCheck($email,'string'),
                    'NextCallDate'=>date('m-d-Y H:i:s'),
                );
                $insert->values($newData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

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

    public function ticketRegisterAction(){
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
        $status = $this->params()->fromRoute('status');
        $sql = new Sql($dbAdapter);


        $where="";
        if(isset($status)){

            $where =" where status =".$status;
            $select = $sql->select();
            $select->from('Crm_TicketRegister')
                ->where(array("Status"=>$status));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->status = $status;

        }



        //selecting values from Executive Table
        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserId'=>'UserId','UserName' => 'EmployeeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* added lines */

        $select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
            ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
            ->where("a.DeleteFlag='0'")
            ->order('a.TicketId desc');
        if(isset($status)){
            $select->where(array('a.Status' => $status));
        }
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ticket = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $ticketId = $postParams['ticketId'];
                $executiveId = $postParams['executiveId'];
                $update = $sql->update();
                $select->table('Crm_TicketRegister');
                $select->set(array(
                    'ExecutiveId' => $executiveId
                ));
                $update->where(array('TicketId'=>$ticketId));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
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
    public function ticketEditAction(){
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
        $ticketId = $this->params()->fromRoute('TicketId');
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->join(array("b" =>"WF_Users"),"a.ExecutiveId=b.UserId",array("EmployeeName"), $select::JOIN_INNER)
            ->join(array("c" =>"Crm_LeadPersonalInfo"),"a.LeadId=c.LeadId",array("Photo"), $select::JOIN_LEFT)
            //->join(array("d" =>"Crm_Leads"),"a.LeadId=d.LeadId",array("Email"), $select::JOIN_LEFT)
            ->where("a.TicketId=$ticketId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ticketedit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //edit tags//
        //multi tags selection
        $select = $sql->select();
        $select->from('Crm_TicketTags')
            ->columns(array('TagId'))
            ->where(array("TicketId"=>$ticketId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsMulti  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->leadProjects = array();
        foreach($this->_view->resultsMulti as $this->resultsMulti) {
            $this->leadProjects[] = $this->resultsMulti['TagId'];
        }
        $this->_view->leadProjects = $this->leadProjects;


        //selecting tags//$select = $sql->select();
        $select->from('Crm_TicketTags')
            ->columns(array('TagId','TagName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsticket = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Executives//
        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserId','EmployeeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Lead Name//
        $select = $sql->select();
        $select->from('Crm_Leads')
            ->columns(array('data' => 'LeadId', 'value'=>'LeadName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $this->_view->setTerminal(true);
                //$response = $this->getResponse()->setContent();
                //return $response;

            }} else {
            $request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {

                if ($request->isPost()) {
                    $postData = $request->getPost();
                    //Print_r($postData);die;
                    $requester = $this->bsf->isNullCheck($postData['requester'],'string');
                    $leadId = $this->bsf->isNullCheck($postData['LeadId'],'number');
                    //$mailId = $this->bsf->isNullCheck($postData['MailId'],'string');
                    $subject = $this->bsf->isNullCheck($postData['subject'],'string');
                    $type = $this->bsf->isNullCheck($postData['type'],'string');
                    $status = $this->bsf->isNullCheck($postData['status'],'string');
                    $priority = $this->bsf->isNullCheck($postData['priority'],'string');
                    $tGroup = $this->bsf->isNullCheck($postData['tgroup'],'string');
                    $description = $this->bsf->isNullCheck($postData['description'],'string');
                    // $tags = $this->bsf->isNullCheck($postData['tags'],'string');
                    $updatecli = $this->bsf->isNullCheck($postData['updatecli'],'number');
                    $executiveId = $this->bsf->isNullCheck($postData['executiveId'],'number');
                    $date=date('m-d-Y H:i:s');
                    // $select = $sql->delete();
                    // $select->from('Crm_TicketTags')
                    // ->where(array('TicketId' => $ticketId,));
                    // $DelStatement = $sql->getSqlStringForSqlObject($select);
                    // $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Crm_TicketRegister');
                    $update->set(array(
                        'ModifiedDate' => date('m-d-y'),
                        'Requester' => $requester,
                        'LeadId' => $leadId,
                        'Subject' => $subject,
                        'Type' => $type,
                        'Description' => $description,
                        'CliUpdate' => $updatecli,
                        'Status' => $status,
                        'Priority' => $priority,
                        'TGroup' => $tGroup,
                        'ExecutiveId' => $executiveId,
                    ));
                    $update->where(array('TicketId'=>$ticketId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    if($postData['updatecli']==1){
                        $mailData = array(
                            array(
                                'name' => 'TICKETID',
                                'content' => $ticketId
                            ),
                            array(
                                'name' => 'DATE',
                                'content' => $date
                            ),
                            array(
                                'name' => 'STATUS',
                                'content' => $status
                            )
                        );
                        $sm = $this->getServiceLocator();
                        $config = $sm->get('application')->getConfig();
                        $viewRenderer->MandrilSendMail()->sendMailTo($postData['MailId'],$config['general']['mandrilEmail'],'New Client Ticket','crm_ticket_new',$mailData);
                    }
                    // foreach ($postData['tags'] as $value){
                    // $select = $sql->insert('Crm_TicketTags');
                    // $newData = array(
                    // 'TicketId' => $ticketId,
                    // 'TagName'=> $value,
                    // );
                    // $select->values($newData);
                    // $statement = $sql->getSqlStringForSqlObject($select);
                    // $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    // }
                }
                $connection->commit();
                $this->redirect()->toRoute('crm/ticket-register', array('controller' => 'pm', 'action' => 'ticket-register'));
            } catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function clientbasedserviceAction(){
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
        $ticketId = $this->params()->fromRoute('TicketId');
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array("a" => "PM_ServiceMaster"))
            ->columns(array('ServiceId','ServiceName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->service = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a" => "Crm_TicketRegister"))
            ->columns(array('TicketId','Requester','LeadId'))
            ->where(array("a.DeleteFlag"=>0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ticketreg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $leadId=$postParams['LeadId'];

                $select->from(array('a' => 'KF_UnitMaster'))
                    ->columns(array('UnitId', 'UnitNo', 'UnitArea', 'Status', 'ProjectId'))
                    ->join(array('b' => 'Proj_ProjectMaster'), 'b.ProjectId=a.ProjectId',array('*'), $select::JOIN_LEFT)
                    ->join(array("c"=>"KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"=>"FloorName"), $select::JOIN_LEFT)
                    ->join(array("d"=>"KF_BlockMaster"), "c.BlockId=d.BlockId", array("BlockName"=>"BlockName"), $select::JOIN_LEFT)
                    ->join(array('i' => 'KF_UnitTypeMaster'), 'a.UnitTypeId=i.UnitTypeId', array('UnitTypeName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'KF_PhaseMaster'), 'e.phaseId=d.phaseId', array('PhaseName'), $select::JOIN_LEFT)
                    ->join(array('f' => 'Crm_UnitDetails'), 'f.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                    ->join(array('g' => 'Crm_FacingMaster'), 'f.FacingId=g.FacingId', array('Description'), $select::JOIN_LEFT)
                    ->join(array('h' => 'crm_Unitbooking'), new expression('h.UnitId=a.UnitId and h.DeleteFlag=0'), array('LeadId','BuyerName' => 'BookingName'), $select::JOIN_LEFT)
                    //->join(array('i' => 'KF_UnitTypeMaster'), 'i.UnitTypeId=a.UnitTypeId', array('UnitTypeName' => 'UnitTypeName'), $select::JOIN_LEFT)
                    ->where(array("h.LeadId"=>$leadId));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($unitInfo));
                $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                $response->setStatusCode(200);
                return $response;
            }
        } else {
            $request = $this->getRequest();

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            //try {

            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();

                $ticketId = $this->bsf->isNullCheck($postData['ticketId'],'number');
                foreach($postData as $key => $data) {
                    if(preg_match('/^service_[\d]+$/', $key)) {

                        preg_match_all('/^service_([\d]+)$/', $key, $arrMatches);
                        $id = $arrMatches[1][0];

                        $serviceId = $this->bsf->isNullCheck($postData['service_' . $id], 'number');
                        if($serviceId <= 0) {
                            continue;
                        }

                        $serviceDoneTrans = array(
                            'TicketId' => $ticketId,
                            'ServiceId' => $serviceId,
                            'CreatedDate'=>date('m-d-Y H:i:s')

                        );

                        $insert = $sql->insert('Crm_ClientService');
                        $insert->values($serviceDoneTrans);
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    //$this->redirect()->toRoute('crm/servicedone-register', array('controller' => 'property', 'action' => 'servicedone-register'));
                }

            }

            $connection->commit();
            /*} catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }*/

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            //$this->_view->qualHtml = $qualHtml;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function rentalPrintAction(){
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

        $billId = $this->bsf->isNullCheck($this->params()->fromRoute('RegisterId'), 'number');
        if($billId == 0) {
            $this->redirect()->toRoute("crm/default", array("controller" => "bill","action" => "rental-register"));
        }

        $selectStage = $sql->select();
        $selectStage->from(array("a"=>"PM_RentBillRegister"))
            ->where(array('a.RegisterId' => $billId,'a.DeleteFlag'=>0));
        $statement = $sql->getSqlStringForSqlObject($selectStage);
        $rentBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();

        //unitTable
        $this->_view->progressBill = $rentBill;



        $selectUnit = $sql->select();
        $selectUnit->from(array("a"=>"PM_RentBillRegister"))
            // ->join(array("b"=>"PM_PaymentTrans"), "a.RegisterId=b.RegisterId", array(), $selectUnit::JOIN_INNER)
            //  ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $selectUnit::JOIN_LEFT)
            ->join(array("e"=>"KF_UnitMaster"), "a.UnitId=e.UnitId", array('UnitNo'), $selectUnit::JOIN_INNER)
            ->join(array("f"=>"Crm_UnitBooking"), "f.UnitId=a.UnitId", array('BuyerName' => 'BookingName'), $selectUnit::JOIN_INNER)
            ->join(array("g"=>"Crm_Leads"), "g.LeadId=f.LeadId", array('Mobile', 'Email','LeadName'), $selectUnit::JOIN_INNER)
            ->join(array("h" => "Proj_ProjectMaster"), "e.ProjectId=h.ProjectId", array('*'), $selectUnit::JOIN_LEFT)
            ->join(array("i" => "WF_CompanyMaster"), "i.CompanyId=h.CompanyId", array('CompanyName'=>'CompanyName','CompanyAddress'=>'Address','CompanyEMail'=>'Email','CompanyMobile'=>'Mobile'), $selectUnit::JOIN_LEFT)
            ->join(array("j"=>"Crm_UnitType"), "j.UnitTypeId=e.UnitTypeId", array('CreditDays', 'IntPercent'), $selectUnit::JOIN_INNER)
            ->join(array("k" =>"PM_RentalRegister"),"a.UnitId=k.UnitId",array(), $selectUnit::JOIN_INNER)
            ->where(array('a.RegisterId' => $billId,'a.DeleteFlag'=>0));
        $statement = $sql->getSqlStringForSqlObject($selectUnit);
        $selectUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if(empty($selectUnit)) {
            throw new \Exception('Bill not found!');
        }
        $SelectReceiptType = $sql->select();
        $SelectReceiptType->from(array("a"=>"PM_PRentBillTrans"))
            ->join(array("b"=>"PM_RentBillRegister"), "a.RegisterId=b.RegisterId", array('*'), $SelectReceiptType::JOIN_INNER)
            ->join(array("c"=>"PM_ServiceMaster"), "a.ServiceId=c.ServiceId", array('ServiceName'), $SelectReceiptType::JOIN_INNER)
            ->where(array('a.RegisterId' => $billId));
        $statement = $sql->getSqlStringForSqlObject($SelectReceiptType);
        $maintainBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $pdfhtml = $this->generateRentalPdf($selectUnit, $maintainBill);


        require_once(getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php");
        $dompdf = new DOMPDF();
        $dompdf->load_html($pdfhtml);
        $dompdf->set_paper("A4");
        $dompdf->render();
        //$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
        $canvas = $dompdf->get_canvas();
        $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

        $dompdf->stream("RentBill.pdf");

    }

    private function generateRentalPdf( $selectUnit, $maintainBill) {

        $pdfhtml = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,400italic,600,600italic,700,700italic,800,300,300italic,800italic' rel='stylesheet' type='text/css'>
<title>PDF</title>
<style>
.clearfix:after {
content: "";
display: table;
clear: both;
}

a {
text-decoration: underline;
}

body {
position: relative;
width: 612px;
margin: 0 auto;
color: #001028;
background: #FFFFFF;
font-size: 12px;
font-family: "Open Sans",sans-serif;
}

header {
padding: 10px 0;
}

#logo {
text-align: center;
margin-bottom: 10px;
}

#logo img {
width: 90px;
}

h1 {
border-top: 1px solid  #5D6975;
border-bottom: 1px solid  #5D6975;
color: #5D6975;
font-size: 2.4em;
line-height: 1.4em;
font-weight: normal;
text-align: center;
margin: 0 0 20px 0;
background: url(dimension.png);
}

.project {float: left;}
.project span {color: #5D6975;text-align: right;width: 87px;margin-right: 10px;display: inline-block;font-size: 14px;}
.company {float: right;text-align: right;font-size: 14px;}
.project div,.company div {white-space: nowrap;}

2
.project2 {float:right;}
.widspn span {width: 164px;}
.project2 span {
color: #5D6975;
text-align: right;
width: 300px;
margin-right: 10px;
display: inline-block;
font-size: 14px;
}
.pd10{ padding:2px;}

table {
width: 100%;
border-collapse: collapse;
border-spacing: 0;
margin-bottom: 20px;

page-break-inside:auto;
}

table tr {
page-break-inside:avoid;
page-break-after:auto;
}

table tr:nth-child(2n-1) td {
background: #F5F5F5;
}

table th,
table td {
text-align: center;
}

table th {
padding: 5px 20px;
color: #5D6975;
border-bottom: 1px solid #C1CED9;
white-space: nowrap;
font-weight: 600;
}

table .service,
table .desc {
text-align: left;
}

table td {
padding: 5px;
text-align: right;
}

table td.service,
table td.desc {
vertical-align: top;
}

table td.unit,
table td.qty,
table td.total {
font-size: 15px;}
notices .notice {
color: #5D6975;
font-size: 1.2em;
}

.project.widspn {
page-break-after: always;
}
.project.widspn:last-of-type {
page-break-after: avoid;
}

footer {
color: #5D6975;
width: 100%;
height: 30px;
position: absolute;
bottom: 0;
border-top: 1px solid #C1CED9;
padding: 8px 0;
text-align: center;
}
</style>
</head>
<body style="border:1px solid #000;">
	<header class="clearfix" style="padding-left:10px; padding-right:10px;">
        <div id="logo">

        </div>
        <h1>Bulidsuperfast Invoicebill</h1>
    </header>
    <div align="center" style="width:100%;">
        <table align="center" style="width:98%;">
            <tbody>
                <tr>
                    <td style="text-align:left !important;">
                        <div class="project">
                            <div class="pd10"><span>REF No. : </span>{$selectUnit['PVNo']}</div>
                            <div class="pd10"><span>CLIENT : </span>{$selectUnit['LeadName']} </div>
                            <div class="pd10"><span>Unit No. : </span>{$selectUnit['UnitNo']}</div>
                            <div class="pd10"><span>EMAIL : </span>{$selectUnit['Email']}</div>
                            <div class="pd10"><span>MOBILE No. : </span>{$selectUnit['Mobile']}</div>
                            <div class="pd10"><span>DATE : </span>{$selectUnit['PVDate']}</div>
                        </div>
                    </td>
                    <td>
                        <div class="company" style="padding: 0px !important">
                            <div >{$selectUnit['CompanyName']}</div>
                            <div>{$selectUnit['CompanyAddress']}</div>
                            <div>{$selectUnit['CompanyMobile']}</div>
                            <div>{$selectUnit['CompanyEMail']}</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <main>
        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
            <p style="padding: 0 15px ; ">This is with reference to the booking of your unit in our Project <span style="color:#000;">{$selectUnit['ProjectName']}.</span> We are pleased to inform you that we have completed the below Stage.</p>
        </div>
        <div style="font-size: 15px; color:#4E4C4C; border-top:1px solid #000000;border-bottom:1px solid #000000;">
            <p style="padding: 0 15px ; ">You are therefore requested to make the payment before the due date.</p>
        </div>
        <div align="center" style="width:100%;">
            <table align="center" style="width:98%;">
                <thead  style="font-size: 16px ! important; ">
                    <tr>
                        <th class="desc">ServiceName</th>

                        <th colspan="6">Amount</th>
                    </tr>
                </thead>
                <tbody>
EOT;
        foreach($maintainBill as $maintainBil) {
            $pdfhtml .= <<<EOT
                    <tr>
                        <td class="desc">{$maintainBil['ServiceName']}</td>

                        <td class="total" colspan="3">{$maintainBil['Amount']}</td>
                    </tr>
EOT;
        }
        $pdfhtml .= <<<EOT
                     <tr>
                        <td colspan="3" >Receipt Amount</td>
                        <td class="total"><b>{$selectUnit['ReceiptAmount']} </b></td>
                    </tr>
                    <tr>
                        <td colspan="3" >Payable Amount</td>
                        <td class="total"><b>{$selectUnit['PaymentAmount']}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="project widspn" style="margin-top:20px; width:100%;"></div>
        <div class="clearfix"></div>
    </main>
    <div style="font-size: 15px; color:#4E4C4C; padding:10px 15px;">
        <p>Thanking you and assuring of our best services at all times.</p>
        <p style="padding: 0 15px ; ">For {$selectUnit['CompanyName']}</p>
        <p style="padding: 0 15px ; "><b>(Authorised Signatory)</b></p>
    </div>
</body>
</html>
EOT;
        return $pdfhtml;
    }

    public function agreementTemplateRegisterAction(){
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
            $response = $this->getResponse();
            if ($request->isPost()) {
                try {
                    $postData = $request->getPost();

                    $templateId = $this->bsf->isNullCheck($postData['TemplateId'], 'number');
                    $Remarks = $this->bsf->isNullCheck($postData['Remarks'], 'number');

                    $update = $sql->update();
                    $update->table( 'PM_AgreementTemplate' )
                        ->set( array( 'DeleteFlag' => 1
                        ,'ModifiedDate'=>date('Y-m-d H:i:s')
                        ,'Remarks'=>$Remarks))
                        ->where( array( 'TemplateId' =>$templateId ) );
                    $statement = $sql->getSqlStringForSqlObject( $update );
                    $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );

                    //Write your Ajax post code here
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent('success');
                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
                return $response;
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {
                try {
                    $select = $sql->select();
                    $select->from(array('a'=>'PM_AgreementTemplate'))
                        ->join(array('b' => 'Crm_AgreementTypeMaster' ), 'a.AgreementTypeId=b.AgreementTypeId', array('AgreementTypeName'), $select::JOIN_LEFT )
                        ->columns(array('*'))
                        ->where(array("a.DeleteFlag"=> 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->TempDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //Common function
                    $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                    return $this->_view;
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                    echo $ex->getMessage(); die;
                }
            }
        }
    }
}