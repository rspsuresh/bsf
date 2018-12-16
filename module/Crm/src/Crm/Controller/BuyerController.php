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
use Application\View\Helper\Qualifier;
use DOMPDF;

class BuyerController extends AbstractActionController
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

    public function registerAction(){
        // Login Authentication check
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Lead Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();

                $select = $sql->select();
                $select->from(array('a' => 'Crm_Leads'))
                    ->join(array("c"=>"Crm_LeadTypeMaster"), "a.LeadType=c.LeadTypeId", array("LeadTypeName"), $select::JOIN_LEFT)
                    ->join(array("d"=>"WF_Users"), "a.ExecutiveId=d.UserId", array("UserName"), $select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_LeadPersonalInfo"), "a.LeadId=e.LeadId", array("Photo"), $select::JOIN_LEFT)
                    ->join(array("b"=>"Crm_unitBooking"), "a.LeadId=b.LeadId", array("DeleteFlag"), $select::JOIN_LEFT)
                    ->join(array("f"=>"WF_Users"), "a.UserId=f.UserId", array("CreatedByName" => new Expression("isnull(f.EmployeeName,'')")), $select::JOIN_LEFT)
                    ->where(array("a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)","b.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $icount=0;
                foreach ($results as $resu){
                    $leadid=0;
                    $leadid=$resu['LeadId'];

                    //CC
                    $strCCName="";
                    $selectMultiCC = $sql->select();
                    $selectMultiCC->from(array("a"=>"Crm_LeadProjects"));
                    $selectMultiCC->columns(array("ProjectId"),array("ProjectName"))
                        ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiCC::JOIN_INNER);
                    $selectMultiCC->where(array("a.LeadId"=>$leadid));
                    $statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC);
                    $resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $proj = array();
                    if($resultMultiCC ){
                        foreach($resultMultiCC as $multiCC){
                            array_push($proj, $multiCC['ProjectName']);
                        }
                        $strCCName = implode(",", $proj);
                    }
                    $results[$icount]['Projects']=$strCCName;

                    //units//

                    $strUnitName="";
                    $selectMultiCC = $sql->select();
                    $selectMultiCC->from(array("a"=>"Crm_UnitBooking"));
                    $selectMultiCC->columns(array("UnitId"),array("UnitNo"))
                        ->join(array("b"=>"KF_UnitMaster"), "a.UnitId=b.UnitId", array("UnitNo"), $selectMultiCC::JOIN_INNER);
                    $selectMultiCC->where(array("a.LeadId"=>$leadid ,"a.DeleteFlag"=>0));
                    $statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC);
                    $resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $unit = array();
                    if($resultMultiCC ){
                        foreach($resultMultiCC as $multiCC){
                            array_push($unit, $multiCC['UnitNo']);
                        }
                        $strUnitName = implode(",", $unit);
                    }
                    $results[$icount]['Unit']=$strUnitName;

                    //City
                    $strCTName="";
                    $selectMultiCT = $sql->select();
                    $selectMultiCT->from(array("a"=>"Crm_LeadCity"));
                    $selectMultiCT->columns(array("CityId"),array("CityName"))
                        ->join(array("b"=>"WF_CityMaster"), "a.CityId=b.CityId", array("CityName"), $selectMultiCT::JOIN_INNER);
                    $selectMultiCT->where(array("a.LeadId"=>$leadid));
                    $statementMultiCT = $sql->getSqlStringForSqlObject($selectMultiCT);
                    $resultMultiCT = $dbAdapter->query($statementMultiCT, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $city = array();
                    if($resultMultiCT ){
                        foreach($resultMultiCT as $multiCT){
                            array_push($city, $multiCT['CityName']);
                        }
                        $strCTName = implode(",", $city);
                    }
                    $results[$icount]['CityName']=$strCTName;
                    $icount=$icount+1;
                }

                $response->setContent(json_encode($results));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
            }



            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function followupEntryAction(){
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

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->order("ProjectId Desc");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a"=>'Crm_UnitBooking'))
            ->columns(array("LeadId"),array("LeadName"))
            ->join(array("b"=>"Crm_Leads"), "a.LeadId=b.LeadId", array("LeadName"), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsRef = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $sql     = new Sql($dbAdapter);
                $postParams = $request->getPost();
                $select = $sql->select();

                $select = $sql->select();
                $select ->from(array("a"=>'Crm_UnitBooking'))
                    ->columns(array(new Expression('DISTINCT(a.LeadId) as LeadId')),
                        array("LeadName"))
                    ->join(array("b"=>"Crm_LeadProjects"), "a.LeadId=b.LeadId", array("ProjectId"), $select::JOIN_LEFT)
                    ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array("LeadName"=>new expression("c.LeadName + ' - ' + c.Mobile")), $select::JOIN_INNER)
                    ->where(array('b.ProjectId' => $postParams['ProjectId'],'a.DeleteFlag'=>0))
                    ->order('a.LeadId asc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($resultLeads));
                return $response;
            }
        } else {
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $leadId = $postParams['leadId'];
                $pId = $postParams['ProjectId'];

                $this->redirect()->toRoute('crm/followup', array('controller' => 'buyer', 'action' => 'followup', 'LeadId' => $leadId, 'ProjectId' => $pId));
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function followupAction(){
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
        $leadId = $this->params()->fromRoute('LeadId');
        $request = $this->getRequest();

        if($this->getRequest()->isXmlHttpRequest())	{
            //$resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $sql = new Sql($dbAdapter);
                $postParams = $request->getPost();
                if($postParams['mode'] == 'getunit'){

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitBooking'))
                        ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectMaster'), 'c.ProjectId=b.ProjectId', array('ProjectId'), $select::JOIN_LEFT)
                        ->columns(array('UnitNo' => new Expression("b.UnitNo"),
                            'UnitId','LeadId'))
                        ->where(array('a.LeadId' => $postParams['LeadId'], 'a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $responseunit = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if($postParams['mode'] == 'construct'){

                    //construction-agreement-sign
                    $select = $sql->select();
                    $select->from(array("a" => 'Crm_UnitBooking'))
                        ->columns(array('UnitId'))
                        ->join(array("l" => "Crm_Leads"), "a.LeadId=l.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "m.UnitId=a.UnitId", array("UnitNo"), $select::JOIN_INNER)
                        ->where(array('a.LeadId' =>$postParams['LeadId'], 'a.CSigned' => 'N'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $responseunit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if($postParams['mode'] == 'land') {
                    //land-agreement-sign
                    $select = $sql->select();
                    $select->from(array("a" => 'Crm_UnitBooking'))
                        ->columns(array('UnitId'))
                        ->join(array("l" => "Crm_Leads"), "a.LeadId=l.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "m.UnitId=a.UnitId", array("UnitNo"), $select::JOIN_INNER)
                        ->where(array('a.LeadId' => $postParams['LeadId'], 'a.LSigned' => 'N'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $responseunit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }else if($postParams['mode'] == 'sale') {

                    //sale-deed-agreement- sign
                    $select = $sql->select();
                    $select->from(array("a" => 'Crm_UnitBooking'))
                        ->columns(array('UnitId'))
                        ->join(array("l" => "Crm_Leads"), "a.LeadId=l.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "m.UnitId=a.UnitId", array("UnitNo"), $select::JOIN_INNER)
                        ->where(array('a.LeadId' =>$postParams['LeadId'], 'a.SDSigned' => 'N'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $responseunit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }else if($postParams['mode'] == 'new') {

                    //grahapravesam
                    $select = $sql->select();
                    $select->from(array("a" => 'Crm_UnitBooking'))
                        ->columns(array('UnitId'))
                        ->join(array("l" => "Crm_Leads"), "a.LeadId=l.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "m.UnitId=a.UnitId", array("UnitNo"), $select::JOIN_INNER)
                        ->where(array('a.LeadId' =>$postParams['LeadId'], 'a.GSigned' => 'N'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $responseunit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                else if($postParams['mode'] == 'reg'){

                    //Registeration
                    $select = $sql->select();
                    $select->from(array("a" => 'Crm_UnitBooking'))
                        ->columns(array('UnitId'))
                        ->join(array("l" => "Crm_Leads"), "a.LeadId=l.LeadId", array(), $select::JOIN_INNER)
                        ->join(array("m" => "KF_UnitMaster"), "m.UnitId=a.UnitId", array("UnitNo"), $select::JOIN_INNER)
                        ->where(array('a.LeadId' =>$postParams['LeadId'], 'a.RSigned' => 'N'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $responseunit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($responseunit));
                return $response;
            }
        } else {
            $postParams = $request->getPost();
            if ($request->isPost()) {
                //print_r($postParams);die;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $leadDate = date('m-d-Y',strtotime($postParams['leadDate']));
                    //$statusId  = $postParams['statusId'];
                    $nextCallDate = date('m-d-Y',strtotime($postParams['nextCallDate']));
                    //followup3
                    $nextFollowUpTypeId = $postParams['nextFollowUpTypeId'];
                    if($postParams['vehicleAllocation']){
                        $vehicleAllocation = $postParams['vehicleAllocation'];
                    } else {
                        $vehicleAllocation  = "";
                    }
                    //if followup type is sitevisit we have to show pickuptime
                    if($postParams['pickUpTime']){
                        $pickUpTime  = $postParams['pickUpTime'];
                    } else {
                        $pickUpTime  = "";
                    }
                    if($postParams['pickUpAddress']){
                        $pickUpAddress = $postParams['pickUpAddress'];
                    } else {
                        $pickUpAddress = "";
                    }
                    $remarks=$postParams['remarks'];
                    $executiveId=$postParams['actionRequiredBy'];
                    $natureId=$postParams['natureId'];
                    $entryId=$postParams['entryId'];
                    $callTypeId=$postParams['callType'];
                    $unitId=$this->bsf->isNullCheck($postParams['unitNo'],'number');

                    $insert  = $sql->insert('Crm_LeadFollowup');
                    $newData = array(
                        'FollowUpDate'  => $leadDate,
                        'LeadId' => $leadId,
                        'NextCallDate' => $nextCallDate,
                        'ExecutiveId' => $executiveId,
                        'UnitId' => $unitId,
                        'NextFollowUpTypeId' => $nextFollowUpTypeId,
                        'NatureId'=>$postParams['natureId'],
                        'NextFollowupRemarks'=>$postParams['nextfollowremarks'],
                        'VehicleAllocation'=>$vehicleAllocation,
                        'PickUpTime'=>	$pickUpTime,
                        'PickUpAddress'=>	$pickUpAddress,
                        'CallTypeId' => $callTypeId,
                        'Remarks' => $remarks,
                        'LeadFlag'=>'B',
                        'ModifiedDate'=>date('m-d-Y H:i:s'),
                        'CallerSid'=>$postParams['caller_sid']
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $newEntryId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $update = $sql->update();
                    $update->table('Crm_LeadFollowup');
                    $update->set(array(

                        'Completed'  => 1,
                        'CompletedDate'  => date('Y-m-d H:i:s'),

                    ));
                    $update->where(array('EntryId'=>$entryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results2  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    //update crm_unitbooking
                    if($unitId > 0 &&  $callTypeId == 13 ) {
                        $update = $sql->update();
                        $update->table('Crm_UnitBooking');
                        $update->set(array(
                            'CSigned'  => 'Y',
                        ));
                        $update->where(array('UnitId'=>$unitId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    if($unitId > 0 && $callTypeId == 15){
                        $update = $sql->update();
                        $update->table('Crm_UnitBooking');
                        $update->set(array(
                            'LSigned'  => 'Y',
                        ));
                        $update->where(array('UnitId'=>$unitId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    if($unitId > 0 && $callTypeId == 17){

                        $update = $sql->update();
                        $update->table('Crm_UnitBooking');
                        $update->set(array(
                            'SDSigned'  => 'Y',
                        ));
                        $update->where(array('UnitId'=>$unitId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    if($unitId > 0 && $callTypeId == 18){

                        $update = $sql->update();
                        $update->table('Crm_UnitBooking');
                        $update->set(array(
                            'RSigned' => 'Y',
                        ));
                        $update->where(array('UnitId'=>$unitId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    if($unitId > 0 && $callTypeId == 20){

                        $update = $sql->update();
                        $update->table('Crm_UnitBooking');
                        $update->set(array(
                            'GSigned'  => 'Y',
                        ));
                        $update->where(array('UnitId'=>$unitId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Buyer-Followup-Add','N','Buyer-Followup-Details',$newEntryId,0, 0, 'CRM','',$userId, 0 ,0);

                    $this->redirect()->toRoute('crm/followup-page', array('controller' => 'buyer', 'action' => 'followup', 'leadId' => $leadId));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $this->_view->CallTypeId = $this->params()->fromRoute('CallTypeId');
                $this->_view->BuyerId = $leadId;
                $pId = $this->params()->fromRoute('ProjectId');
                $this->_view->callSid = $this->bsf->isNullCheck($this->params()->fromRoute('CallSid'), 'string');


//                $select = $sql->select();
//                $select->from('WF_Users')
//                    ->columns(array("*"))
//                    ->where(array("UserId"=>$userId));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultsUser = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select ->from(array("a"=>'Crm_UnitBooking'))
                    ->columns(array(new Expression('DISTINCT(a.LeadId) as LeadId')),
                        array("LeadName"))
                    ->join(array("b"=>"Crm_LeadProjects"), "a.LeadId=b.LeadId", array("ProjectId"), $select::JOIN_LEFT)
                    ->join(array("c"=>"Crm_Leads"), "b.LeadId=c.LeadId", array("LeadName"=>new expression("c.LeadName")), $select::JOIN_INNER)
                    ->where(array('b.ProjectId' => $pId))
                    ->order('a.LeadId asc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId','ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLeadProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_UnitTypeMaster')
                    ->columns(array('UnitTypeId','UnitTypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId','UserName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_BrokerMaster')
                    ->columns(array('BrokerId','BrokerName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsBroker = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_StatusMaster')
                    ->columns(array('StatusId','Description'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsStatus  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_CallTypeMaster')
                    ->columns(array('CallTypeId','Description'))
                    ->where(array("Buyer"=>1));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsCall = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => 'Crm_UnitBooking'))
                    ->columns(array("Count" => new Expression("Count(a.UnitId)")))
                    ->join(array("l" => "Crm_Leads"), "a.LeadId=l.LeadId", array(), $select::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "m.UnitId=a.UnitId", array(), $select::JOIN_INNER)
                    ->where(array('a.LeadId' =>$leadId, 'a.Handover' => 'N'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->unitCount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();

                $select = $sql->select();
                $select->from('Crm_NatureMaster')
                    ->columns(array('NatureId','Description'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsNature  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_UnitBooking')
                    ->columns(array("*"))
                    ->where(array("LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsMain  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array("a"=>'Crm_Leads'))
                    ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),
                        'UnitTypeId','LeadName','ExecutiveId','Email','Mobile','VIP'),
                        array("ProjectName"),array("LeadTypeName"),array("ExecutiveName"),array("UnitTypeName"),array("state"=>"Description"),array("call"=>"Description"))
                    //->join(array("b"=>"Crm_LeadProjects"),"a.ProjectsId=b.ProjectsId",array("ProjectName"),$select::JOIN_LEFT)
                    ->join(array("g"=>"Crm_LeadTypeMaster"),"a.LeadType=g.LeadTypeId",array("LeadTypeName"),$select::JOIN_LEFT)
                    ->join(array("k"=>"WF_Users"),"a.ExecutiveId=k.UserId",array("UserName"=> 'EmployeeName'),$select::JOIN_LEFT)
                    ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
                    ->join(array("h"=>"Crm_LeadPersonalInfo"),"a.LeadId=h.LeadId",array("Photo"),$select::JOIN_LEFT)
                    ->where(array('a.LeadId' => $leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadFollowup'))
                    ->columns(array('EntryId','FollowUpDate'=>new Expression("CONVERT(varchar(10),FollowUpDate,105)"),'LeadId','UnitTypeId','CallTypeId','NatureId','Remarks','NextFollowupRemarks','NextCallDate'=>new Expression("CONVERT(varchar(10),NextCallDate,105)"),'ExecutiveId'),
                        array("call"),array("LeadName"),array("UserName"),array("Nature"),
                        array("next"),
                        array("UnitTypeName"),array("Photo"),array("state"=>"Description"))
                    ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("j"=>"Crm_NatureMaster"),"a.NatureId=j.NatureId",array("Nat"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=d.CallTypeId",array("Nature"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
                    ->join(array("f"=>"Crm_CallTypeMaster"),"a.CallTypeId=f.CallTypeId",array("call"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("g"=>"WF_Users"),"a.ExecutiveId=g.UserId",array("UserName"),$select::JOIN_LEFT);
                $select->where(array('a.LeadId'=>$leadId))
                    ->order("a.EntryId Desc");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->responseFollow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                //$EntryId = $dbAdapter->getDriver()->getLastGeneratedValue();


                $selectMultiProject = $sql->select();
                $selectMultiProject->from(array("a"=>"Crm_LeadProjects"));
                $selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
                    ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
                $selectMultiProject->where(array("a.LeadId"=>$leadId));
                $statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject);
                $this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
            return $this->_view;
        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function followupRegisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        // $this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql     = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
            ->columns(array('FollowupId' => new expression('count(EntryId)')))
            ->where(array("a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)"));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->FollowupCount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
            ->columns(array('CallTypeId' => new expression('count(CallTypeId)')))
            ->where(array('a.CallTypeId' => 4,"a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)"));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->Finalise = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
            ->columns(array('CallTypeId' => new expression('count(StatusId)')))
            ->where(array('a.StatusId' => 1,"a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)"));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->statushot = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
            ->columns(array('CallTypeId' => new expression('count(StatusId)')))
            ->where(array('a.StatusId' => 2,"a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)"));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->statuswarm = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
            ->columns(array('CallTypeId' => new expression('count(StatusId)')))
            ->where(array('a.StatusId' => 3,"a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)"));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->statuscold = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
            ->columns(array('CallTypeId' => new expression('count(CallTypeId)')))
            ->where(array('a.CallTypeId' => 3,"a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)"));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->drop = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();



        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadFollowup'))
                    ->join(array("c"=>"WF_Users"), "a.ExecutiveId=c.UserId", array("UserName"), $select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_StatusMaster"),"a.StatusId=d.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("k"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=k.CallTypeId",array("Nature"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("f"=>"Crm_Leads"),"a.LeadId=f.LeadId",array("LeadId","LeadName","LeadType","Mobile","VIP"),$select::JOIN_LEFT)
                    ->join(array("g"=>"Crm_CallTypeMaster"),"a.CallTypeId=g.CallTypeId",array("CallType"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId  IN (SELECT LeadId FROM Crm_UnitBooking GROUP BY LeadId)"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                $icount = 0;
                foreach ($results as $resu){
                    $leadid = $resu['LeadId'];

                    //Lead Projects List Append
                    $strCCName="";
                    $selectMultiCC = $sql->select();
                    $selectMultiCC->from(array("a"=>"Crm_LeadProjects"));
                    $selectMultiCC->columns(array("ProjectId"),array("ProjectName"))
                        ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiCC::JOIN_INNER);
                    $selectMultiCC->where(array("a.LeadId"=>$leadid));
                    $statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC);
                    $resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $proj = array();
                    if($resultMultiCC ){
                        foreach($resultMultiCC as $multiCC){
                            array_push($proj, $multiCC['ProjectName']);
                        }
                        $strCCName = implode(",", $proj);
                    }
                    $results[$icount]['Projects']=$strCCName;

                    //Lead City list Append
                    $strCTName="";
                    $selectMultiCT = $sql->select();
                    $selectMultiCT->from(array("a"=>"Crm_LeadCity"));
                    $selectMultiCT->columns(array("CityId"),array("CityName"))
                        ->join(array("b"=>"WF_CityMaster"), "a.CityId=b.CityId", array("CityName"), $selectMultiCT::JOIN_INNER);
                    $selectMultiCT->where(array("a.LeadId"=>$leadid));
                    $statementMultiCT = $sql->getSqlStringForSqlObject($selectMultiCT);
                    $resultMultiCT = $dbAdapter->query($statementMultiCT, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $city = array();
                    if($resultMultiCT ){
                        foreach($resultMultiCT as $multiCT){
                            array_push($city, $multiCT['CityName']);
                        }
                        $strCTName = implode(",", $city);
                    }
                    $results[$icount]['CityName']=$strCTName;

                    //Lead source list append
                    $strSourceName="";
                    $selectMultiSource = $sql->select();
                    $selectMultiSource->from(array("a"=>"Crm_LeadSource"));
                    $selectMultiSource->columns(array("LeadSourceId"),array("LeadSourceId"))
                        ->join(array("b"=>"Crm_LeadSourceMaster"), "a.LeadSourceId=b.LeadSourceId", array("LeadSourceName"), $selectMultiSource::JOIN_INNER);
                    $selectMultiSource->where(array("a.LeadId"=>$leadid));
                    $statementMultiSource = $sql->getSqlStringForSqlObject($selectMultiSource);
                    $resultMultiSource = $dbAdapter->query($statementMultiSource, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $source = array();
                    if($resultMultiSource ){
                        foreach($resultMultiSource as $multiSource){
                            array_push($source, $multiSource['LeadSourceName']);
                        }
                        $strSourceName = implode(",", $source);
                    }
                    $results[$icount]['SourceName']=$strSourceName;

                    $icount=$icount+1;
                }

                $response->setContent(json_encode($results));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function followupDetailsAction() {
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

        $entryId = $this->params()->fromRoute('followupId');

        $select = $sql->select();
        $select->from('Crm_Leads')
            ->columns(array('LeadId','LeadName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_LeadFollowup')
            ->columns(array('EntryId','LeadId'))
            ->where(array('EntryId' => $entryId));

        $statement = $sql->getSqlStringForSqlObject($select);
        $lid  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        // print_r($lid);
        if(count($lid) > 0){
            $ld = $lid['LeadId'];
        } else {
            $ld =0;
        }

        $select = $sql->select();
        $select->from('Crm_UnitTypeMaster')
            ->columns(array('UnitTypeId','UnitTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_CallTypeMaster')
            ->columns(array('CallTypeId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsCall  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from('Crm_StatusMaster')
            ->columns(array('StatusId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsStatus  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_NatureMaster')
            ->columns(array('NatureId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsNature  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserId','UserName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsExecutive = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a"=>'Crm_Leads'))
            ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),'LeadName','ExecutiveId','Email','Mobile','VIP'),
                array("ProjectName"),array("LeadTypeName"),array("UserName"),array("Photo"))
            //->join(array("b"=>"Crm_LeadProjects"),"a.ProjectsId=b.ProjectsId",array("ProjectName"),$select::JOIN_LEFT)
            ->join(array("f"=>"Crm_LeadTypeMaster"),"a.UnitTypeId=f.LeadTypeId",array("LeadTypeName"),$select::JOIN_LEFT)
            ->join(array("k"=>"WF_Users"),"a.ExecutiveId=k.UserId",array("UserName"),$select::JOIN_LEFT)
            ->join(array("h"=>"Crm_LeadPersonalInfo"),"a.LeadId=h.LeadId",array("Photo"),$select::JOIN_LEFT)
            ->where(array('a.LeadId' => $ld));

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array("a"=>'Crm_LeadFollowup'))
            ->columns(array('EntryId','FollowUpDate'=>new Expression("CONVERT(varchar(10),FollowUpDate,105)"),'NextFollowUpTypeId','LeadId','UnitTypeId','CallTypeId','NextFollowupRemarks','NatureId','StatusId','Remarks','NextFollowupRemarks','NextCallDate'=>new Expression("CONVERT(varchar(10),NextCallDate,105)"),'ExecutiveId','VehicleAllocation','PickUpTime','NextFollowUpTypeId','PickUpAddress'),
                array("LeadName"),array("ExecutiveName"),array("Nature"=>"Description"),array("Nature"=>"Description"),
                array("call"=>"Description"),array("next"=>"Description"),
                array("UnitTypeName"),array("state"=>"Description"))
            ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
            ->join(array("d"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=d.CallTypeId",array("Nature"=>"Description"),$select::JOIN_LEFT)
            ->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
            ->join(array("f"=>"Crm_CallTypeMaster"),"a.CallTypeId=f.CallTypeId",array("call"=>"Description"),$select::JOIN_LEFT)
            ->join(array("g"=>"WF_Users"),"a.ExecutiveId=g.UserId",array("UserName"),$select::JOIN_LEFT)
            ->where(array('a.EntryId' => $entryId));

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->responseFollow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


        $selectMultiProject = $sql->select();
        $selectMultiProject->from(array("a"=>"Crm_LeadProjects"));
        $selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
            ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
        $selectMultiProject->where(array("a.LeadId"=>$ld));
        $statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject);
        $this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $request = $this->getRequest();
        $postParams = $request->getPost();
        $leadDate = date('m-d-Y',strtotime($postParams['LeadDate']));
        $leadId = $postParams['LeadId'];
        $statusId  = $postParams['StatusId'];
        $executiveId  = $postParams['ExecutiveId'];
        //$unitTypeId  = $postParams['UnitTypeId'];
        $nextCallDate = date('m-d-Y',strtotime($postParams['NextCallDate']));

        //followup3
        $nextFollowUpTypeId = $postParams['nextFollowUpTypeId'];
        if($postParams['VehicleAllocation']){
            $vehicleAllocation = $postParams['VehicleAllocation'];
        } else {
            $vehicleAllocation  = "";
        }
        //if followup type is sitevisit we have to show pickuptime
        if($postParams['PickUpTime']){
            $pickUpTime  = $postParams['PickUpTime'];
        } else {
            $pickUpTime  = "";
        }
        if($postParams['pickUpAddress']){
            $pickUpAddress = $postParams['pickUpAddress'];
        } else {
            $pickUpAddress = "";
        }
        $remarks=$postParams['Remarks'];
        if($this->getRequest()->isXmlHttpRequest())	{
            //$resp = array();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here

                $this->_view->resp = "";
                $this->_view->setTerminal(true);
                return $this->_view;
            }
        }
        else {
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if($entryId == 0){
                        $insert  = $sql->insert('Crm_LeadFollowup');
                        $newData = array(
                            'FollowUpDate'  => $leadDate,
                            'NextCallDate' => $nextCallDate,
                            'ExecutiveId' => $executiveId,
                            'NextFollowUpTypeId' => $nextFollowUpTypeId,
                            'NextFollowupRemarks'=>$postParams['nextfollowremarks'],
                            'StatusId' => $statusId,
                            'Remarks' => $remarks,
                            'LeadId' => $ld,
                            'VehicleAllocation'=>$vehicleAllocation,
                            'PickUpTime'=>$pickUpTime,
                            'UserId'=>$this->auth->getIdentity()->UserId,
                            'PickUpAddress'=> $pickUpAddress
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $this->redirect()->toRoute('crm/followup-register', array('controller' => 'buyer', 'action' => 'followup-register'));

                        $update = $sql->update();
                        $update->table('Crm_Leads');
                        $update->set(array(
                            'StatusId' => $statusId
                        ));
                        $update->where(array('LeadId' => $ld));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    else{
                        $update = $sql->update();
                        $update->table('Crm_LeadFollowup');
                        $update->set(array(
                            'FollowUpDate'  => $leadDate,
                            'NextCallDate' => $nextCallDate,
                            'ExecutiveId' => $executiveId,
                            'NextFollowUpTypeId' => $nextFollowUpTypeId,
                            'NextFollowupRemarks'=>$postParams['nextfollowremarks'],
                            'StatusId' => $statusId,
                            'Remarks' => $remarks,
                            'LeadId' => $ld,
                            'VehicleAllocation'=>$vehicleAllocation,
                            'PickUpTime'=>$pickUpTime,
                            'PickUpAddress'=> $pickUpAddress
                        ));
                        $update->where(array('EntryId'=>$entryId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $results2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $this->redirect()->toRoute('crm/followup-register', array('controller' => 'buyer', 'action' => 'followup-register'));
                    }
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                //begin trans try block example ends
                return $this->_view;
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function detailsAction(){
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

        $response = $this->getResponse();
        $request = $this->getRequest();
        // $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            //$resp = array();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $detailId = $postParams['cid'];
                //Write your Ajax post code here
                $select = $sql->select();
                $select->from(array("a"=>'Crm_Leads'))
                    ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),'LeadName','LeadType','Mobile','BrokerId','StatusId','RefBuyerId','Remarks','Email','ExecutiveId','UnitTypeId','VIP'))
                    ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
                    ->join(array("f"=>"Crm_LeadTypeMaster"),"a.LeadType=f.LeadTypeId",array('LeadTypeId','LeadTypeName'),$select::JOIN_LEFT)
                    ->join(array("j"=>"WF_Users"), "a.ExecutiveId=j.UserId", array("UserName"), $select::JOIN_LEFT)
                    ->join(array("l"=>"Crm_LeadPersonalInfo"), "a.LeadId=l.LeadId", array("Photo"), $select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$detailId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->resp=$resp;

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadFollowup'))
                    ->columns(array('NextCallDate'))
                    ->join(array("i"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=i.CallTypeId",array("NextCallType"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$detailId))
                    ->where(array("a.Completed"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->respDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $selectMultiProject = $sql->select();
                $selectMultiProject->from(array("a"=>"Crm_LeadProjects"));
                $selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
                    ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
                $selectMultiProject->where(array("a.Leadid"=>$detailId));
                $statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject);
                $this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $selectMultiCT = $sql->select();
                $selectMultiCT->from(array("a"=>"Crm_LeadCity"));
                $selectMultiCT->columns(array("CityId"),array("CityName"))
                    ->join(array("b"=>"WF_CityMaster"), "a.CityId=b.CityId", array("CityName"), $selectMultiCT::JOIN_INNER);
                $selectMultiCT->where(array("a.LeadId"=>$detailId));
                $statementMultiCT = $sql->getSqlStringForSqlObject($selectMultiCT);
                $this->_view->resultMultiCT = $dbAdapter->query($statementMultiCT, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $selectMultiSource1 = $sql->select();
                $selectMultiSource1->from(array("a"=>"Crm_LeadSource"));
                $selectMultiSource1->columns(array("LeadName"=>new expression("b.LeadSourceName")))
                    ->join(array("b"=>"Crm_LeadSourceMaster"), "a.LeadSourceId=b.LeadSourceId", array(), $selectMultiSource1::JOIN_INNER);
                $selectMultiSource1->where(array("a.LeadId"=>$detailId,"a.Name"=>'L'));

                $selectMultiSource2 = $sql->select();
                $selectMultiSource2->from(array("a"=>"Crm_LeadSource"));
                $selectMultiSource2->columns(array("LeadName"=>new expression("b.CampaignName")))
                    ->join(array("b"=>"Crm_CampaignRegister"), "a.LeadSourceId=b.CampaignId", array(), $selectMultiSource2::JOIN_INNER);
                $selectMultiSource2->where(array("a.LeadId"=>$detailId,"a.Name"=>'C'));
                $selectMultiSource2->combine($selectMultiSource1,'Union ALL');
                $statementMultiSource = $sql->getSqlStringForSqlObject($selectMultiSource2);
                $this->_view->resultMultiSource = $dbAdapter->query($statementMultiSource, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                return $this->_view;
            }
        } else {
            if ($request->isPost()) {
                //Write your Normal form post code here
            }
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function fullDetailsAction(){
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
        $sql     = new Sql($dbAdapter);
        $leadId = $this->params()->fromRoute('leadId');
        //print_r($this->params()->fromRoute());die;


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $select = $sql->select();
                $select->from(array("a"=>'Crm_Leads'))
                    ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),'UnitTypeId','PickUpTime'=>new Expression("CONVERT(varchar(10),PickUpTime,100)"),'CostPreferenceId','RefBuyerId','BrokerId','ProjectsId','LeadName','LeadType','BrokerId','RefBuyerId','NextCallDate'=>new Expression("CONVERT(varchar(10),NextCallDate,105)"),'StatusId','NextFollowUpTypeId','CostPreferenceId','PreCityId','ExecutiveId','Mobile','Email'),
                        array("ProjectName"),array("ExecutiveName"),array("state"=>"Description"),array("BrokerName"),array("Nat"=>"Description"),array("call"=>"Description"),array("next"=>"Description"),array("UnitTypeName"),array('LeadTypeId','LeadTypeName'),array('CostPreferenceId','CostPreferenceFrom','CostPreferenceTo'),array('CityId','CityName'),array('LeadSourceId','LeadSourceName'),array('Photo'),array('state'))
                    ->join(array("b"=>"Proj_ProjectMaster"),"a.ProjectsId=b.ProjectsId",array("ProjectName"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
                    ->join(array("f"=>"Crm_LeadTypeMaster"),"a.LeadType=f.LeadTypeId",array('LeadTypeId','LeadTypeName'),$select::JOIN_LEFT)
                    ->join(array("g"=>"Crm_CostPreferenceMaster"),"a.CostPreferenceId=g.CostPreferenceId",array('CostPreferenceId','CostPreferenceFrom','CostPreferenceTo'),$select::JOIN_LEFT)
                    ->join(array("h"=>"WF_CityMaster"),"a.PreCityId=h.CityId",array('CityId','CityName'),$select::JOIN_LEFT)
                    ->join(array("i"=>"Crm_NatureMaster"),"a.NatureId=i.NatureId",array("call"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("j"=>"Crm_BrokerMaster"),"a.BrokerId=j.BrokerId",array("BrokerName"),$select::JOIN_LEFT)
                    ->join(array("k"=>"WF_Users"),"a.ExecutiveId=k.UserId",array("UserName"),$select::JOIN_LEFT)
                    ->join(array("l"=>"Crm_LeadSourceMaster"),"a.LeadSourceId=l.LeadSourceId",array('LeadSourceId','LeadSourceName'),$select::JOIN_LEFT)
                    ->join(array("m"=>"Crm_LeadPersonalInfo"),"a.LeadId=m.LeadId",array('Photo'),$select::JOIN_LEFT)
                    ->join(array("n"=>"Crm_StatusMaster"),"a.StatusId=n.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId,"a.CallTypeId"=>'4'));
                //print_r($select);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLead   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->setTerminal(true);
                return $this->_view;

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {
                $select = $sql->select();
                $select->from(array("a"=>'Crm_UnitBooking'))
                    ->columns(array(new Expression('DISTINCT(a.LeadId) as LeadId')) ,array("LeadName"))
                    ->join(array("b"=>"Crm_Leads"), "a.LeadId=b.LeadId", array("LeadName"), $select::JOIN_LEFT);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_UnitBooking')
                    ->columns(array(new Expression('DISTINCT(LeadId) as LeadId')))
                    ->where(array("LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsBuyer  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_Leads')
                    ->columns(array('LeadId','LeadName'))
                    ->where(array('LeadId'=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_Leads'))
                    ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),'UnitTypeId','RefBuyerId','BrokerId','LeadName','LeadType','BrokerId','RefBuyerId','StatusId','ExecutiveId','Mobile','Email','VIP','CostPreferenceFrom','CostPreferenceTo'))
                    ->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
                    ->join(array("f"=>"Crm_LeadTypeMaster"),"a.LeadType=f.LeadTypeId",array('LeadTypeId','LeadTypeName'),$select::JOIN_LEFT)
                    ->join(array("j"=>"Crm_BrokerMaster"),"a.BrokerId=j.BrokerId",array("BrokerName"),$select::JOIN_LEFT)
                    ->join(array("k"=>"WF_Users"),"a.ExecutiveId=k.UserId",array("UserName"),$select::JOIN_LEFT)
                    ->join(array("m"=>"Crm_LeadPersonalInfo"),"a.LeadId=m.LeadId",array('Photo'),$select::JOIN_LEFT)
                    ->join(array("n"=>"Crm_StatusMaster"),"a.StatusId=n.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLead   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                // multi project selection
                $selectMultiProject = $sql->select();
                $selectMultiProject->from(array("a"=>"Crm_LeadProjects"));
                $selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
                    ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
                $selectMultiProject->where(array("a.Leadid"=>$leadId));
                $statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject);
                $this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // multi city selection
                $selectMultiCT = $sql->select();
                $selectMultiCT->from(array("a"=>"Crm_LeadCity"));
                $selectMultiCT->columns(array("CityId"))
                    ->join(array("b"=>"WF_CityMaster"), "a.CityId=b.CityId", array("CityName"), $selectMultiCT::JOIN_INNER);
                $selectMultiCT->where(array("a.LeadId"=>$leadId));
                $statementMultiCT = $sql->getSqlStringForSqlObject($selectMultiCT);
                $this->_view->resultMultiCT = $dbAdapter->query($statementMultiCT, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadFollowup'))
                    ->columns(array('NextCallDate'=>new Expression("CONVERT(varchar(10),a.NextCallDate,105)"),"PickUpTime"))
                    ->join(array("i"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=i.CallTypeId",array("Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId))
                    ->where(array("a.Completed"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->respDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                // multi Source selection
                //Multi select LeadSource.
                $selectMultiSource1 = $sql->select();
                $selectMultiSource1->from(array("a"=>"Crm_LeadSource"));
                $selectMultiSource1->columns(array("LeadName"=>new expression("b.LeadSourceName")))
                    ->join(array("b"=>"Crm_LeadSourceMaster"), "a.LeadSourceId=b.LeadSourceId", array(), $selectMultiSource1::JOIN_INNER);
                $selectMultiSource1->where(array("a.LeadId"=>$leadId,"a.Name"=>'L'));

                $selectMultiSource2 = $sql->select();
                $selectMultiSource2->from(array("a"=>"Crm_LeadSource"));
                $selectMultiSource2->columns(array("LeadName"=>new expression("b.CampaignName")))
                    ->join(array("b"=>"Crm_CampaignRegister"), "a.LeadSourceId=b.CampaignId", array(), $selectMultiSource2::JOIN_INNER);
                $selectMultiSource2->where(array("a.LeadId"=>$leadId,"a.Name"=>'C'));
                $selectMultiSource2->combine($selectMultiSource1,'Union ALL');

                $statementMultiSource = $sql->getSqlStringForSqlObject($selectMultiSource2);
                $this->_view->resultMultiSource = $dbAdapter->query($statementMultiSource, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                //multi facility selection
                $selectMultiSource = $sql->select();
                $selectMultiSource->from(array("a"=>"Crm_LeadFacility"));
                $selectMultiSource->columns(array("FacilityId"),array("FacilityId"))
                    ->join(array("b"=>"Crm_FacilityMaster"), "a.FacilityId=b.FacilityId", array("Description"), $selectMultiSource::JOIN_INNER);
                $selectMultiSource->where(array("a.LeadId"=>$leadId));
                $statementMultiSource = $sql->getSqlStringForSqlObject($selectMultiSource);
                $this->_view->resultMultiFacility = $dbAdapter->query($statementMultiSource, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadPersonalInfo'))
                    ->columns(array('leadDob'=>new Expression("CONVERT(varchar(10),DOB,105)"),'LeadId','leadWeddingDate'=>new Expression("CONVERT(varchar(10),WeddingDate,105)"),'leadChildDob'=>new Expression("CONVERT(varchar(10),ChildDOB,105)"),'Religion','ProfessionId','Gender','NationalityId','Organization','FatherName','MotherName','MaritalStatus','SpouseName','ChildName','ChildSex'),
                        array("National"=>"Description"),array("ReligionName"),array("Profession"=>"Description"),array("Marital"=>"Description"))
                    ->join(array("b"=>"Crm_ReligionMaster"),"a.Religion=b.ReligionId",array("ReligionName"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_MaritalStatusMaster"),"a.MaritalStatus=e.MaritalId",array("Marital"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_NationalityMaster"),"a.NationalityId=d.NationalityId",array("National"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("c"=>"Crm_ProfessionMaster"),"a.ProfessionId=c.ProfessionId",array("Profession"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsPersonal  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('Crm_LeadBankDetails')
                    ->columns(array('BankName','LeadId','Branch','LoanAmount','ContactPerson','LoanNo','InterestRate','ContactMobileNo'))
                    ->where(array("LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsBank  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadFinance'))
                    ->columns(array('Employment','Apartment','Stay','Income','ApartmentSize','GuestHouse','LeadId'),
                        array("apart"=>"Description"),array("Employment"=>"Description"),array("IncomeId","IncomeFrom","IncomeTo"),array("ApartmentSizeId","ApartmentSizeFrom","ApartmentSizeTo"),array("Stay"=>"Description"),array("GuestHouse"=>"Description") )
                    //->join(array("b"=>"Crm_Apartment"),"a.Apartment=b.ApartmentId",array("apart"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_ProfessionMaster"),"a.Employment=e.ProfessionId",array("Employment"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_IncomeMaster"),"a.Income=d.IncomeId",array("IncomeId","IncomeFrom","IncomeTo"),$select::JOIN_LEFT)
                    // ->join(array("f"=>"Crm_Stay"),"a.Stay=f.StayId",array("Stay"=>"Description"),$select::JOIN_LEFT)
                    // ->join(array("g"=>"Crm_GuestHouse"),"a.GuestHouse=g.GuestHouseId",array("GuestHouse"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("c"=>"Crm_ApartmentSizeMaster"),"a.ApartmentSize=c.ApartmentSizeId",array("ApartmentSizeId","ApartmentSizeFrom","ApartmentSizeTo"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsFinance  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadRequirement'))
                    ->columns(array('Facility','Remarks','LeadId'),
                        array("Description"))
                    ->join(array("b"=>"Crm_FacilityMaster"),"a.Facility=b.FacilityId",array("Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsRequirement  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadAddress'))
                    ->columns(array('AddressType','LeadId','Address1','Address2','Locality','PinCode','Mobile','LandLine','Fax','Email'))
                    ->join(array("b"=>"WF_CityMaster"),"a.CityId=b.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array("c"=>"WF_StateMaster"),"a.StateId=c.StateID",array("StateName"),$select::JOIN_LEFT)
                    ->join(array("d"=>"WF_CountryMaster"),"a.CountryId=d.CountryId",array("CountryName"),$select::JOIN_LEFT)
                    ->where(array("AddressType"=>'P',"LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsPer = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadAddress'))
                    ->columns(array('AddressType','Address1','LeadId','Address2','Locality','PinCode','Mobile','LandLine','Fax','Email'))
                    ->join(array("b"=>"WF_CityMaster"),"a.CityId=b.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array("c"=>"WF_StateMaster"),"a.StateId=c.StateID",array("StateName"),$select::JOIN_LEFT)
                    ->join(array("d"=>"WF_CountryMaster"),"a.CountryId=d.CountryId",array("CountryName"),$select::JOIN_LEFT)
                    ->where(array("AddressType"=>'O',"LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsOffice = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadAddress'))
                    ->columns(array('AddressType','LeadId','Address1','Address2','Locality','PinCode','Mobile','LandLine','Fax','Email'))
                    ->join(array("b"=>"WF_CityMaster"),"a.CityId=b.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array("c"=>"WF_StateMaster"),"a.StateId=c.StateID",array("StateName"),$select::JOIN_LEFT)
                    ->join(array("d"=>"WF_CountryMaster"),"a.CountryId=d.CountryId",array("CountryName"),$select::JOIN_LEFT)
                    ->where(array("AddressType"=>'N',"LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsNRI = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadAddress'))
                    ->columns(array('AddressType','Address1','LeadId','Address2','Locality','PinCode','Mobile','LandLine','Fax','Email'))
                    ->join(array("b"=>"WF_CityMaster"),"a.CityId=b.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array("c"=>"WF_StateMaster"),"a.StateId=c.StateID",array("StateName"),$select::JOIN_LEFT)
                    ->join(array("d"=>"WF_CountryMaster"),"a.CountryId=d.CountryId",array("CountryName"),$select::JOIN_LEFT)
                    ->where(array("AddressType"=>'C',"a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsComm = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadAddress'))
                    ->columns(array('AddressType','Address1','Address2','Locality','PinCode','Mobile','LandLine','Fax','Email'))
                    ->join(array("b"=>"WF_CityMaster"),"a.CityId=b.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array("c"=>"WF_StateMaster"),"a.StateId=c.StateID",array("StateName"),$select::JOIN_LEFT)
                    ->join(array("d"=>"WF_CountryMaster"),"a.CountryId=d.CountryId",array("CountryName"),$select::JOIN_LEFT)
                    ->where(array("AddressType"=>'COA',"a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultscoapp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadAddress'))
                    ->columns(array('AddressType','Address1','Address2','Locality','PinCode','Mobile','LandLine','Fax','Email'))
                    ->join(array("b"=>"WF_CityMaster"),"a.CityId=b.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array("c"=>"WF_StateMaster"),"a.StateId=c.StateID",array("StateName"),$select::JOIN_LEFT)
                    ->join(array("d"=>"WF_CountryMaster"),"a.CountryId=d.CountryId",array("CountryName"),$select::JOIN_LEFT)
                    ->where(array("AddressType"=>'POA',"a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsapp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadCoApplicantInfo'))
                    ->columns(array('CoApplicantName','leadDob'=>new Expression("CONVERT(varchar(10),DOB,105)"),'leadWeddingDate'=>new Expression("CONVERT(varchar(10),WeddingDate,105)"),'Religion','ProfessionId','Gender','NationalityId','Organization','FatherName','MotherName','MaritalStatus','SpouseName'),
                        array("National"=>"Description"),array("ReligionName"),array("Profession"=>"Description"),array("Marital"=>"Description"))
                    ->join(array("b"=>"Crm_ReligionMaster"),"a.Religion=b.ReligionId",array("ReligionName"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_MaritalStatusMaster"),"a.MaritalStatus=e.MaritalId",array("Marital"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_NationalityMaster"),"a.NationalityId=d.NationalityId",array("National"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("c"=>"Crm_ProfessionMaster"),"a.ProfessionId=c.ProfessionId",array("Profession"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsCOA = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadPOAInfo'))
                    ->columns(array('ApplicantName','leadDob'=>new Expression("CONVERT(varchar(10),DOB,105)"),'leadWeddingDate'=>new Expression("CONVERT(varchar(10),WeddingDate,105)"),'Religion','ProfessionId','Gender','NationalityId','Organization','FatherName','MotherName','MaritalStatus','SpouseName'),
                        array("National"=>"Description"),array("ReligionName"),array("Profession"=>"Description"),array("Marital"=>"Description"))
                    ->join(array("b"=>"Crm_ReligionMaster"),"a.Religion=b.ReligionId",array("ReligionName"),$select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_MaritalStatusMaster"),"a.MaritalStatus=e.MaritalId",array("Marital"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_NationalityMaster"),"a.NationalityId=d.NationalityId",array("National"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("c"=>"Crm_ProfessionMaster"),"a.ProfessionId=c.ProfessionId",array("Profession"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsPOA = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Crm_LeadNriInfo'))
                    ->columns(array('PersonName','Email','MobileNo','Address','CityId','StateId'))
                    ->join(array("h"=>"WF_CityMaster"),"a.CityId=h.CityId",array('CityName'),$select::JOIN_LEFT)
                    ->join(array("g"=>"WF_StateMaster"),"a.StateId=g.StateID",array('StateName'),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsnon = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                // Buyer statement
                $select = $sql->select();
                $select->from(array('a' => 'Crm_ProgressBillTrans'))
                    ->columns(array('BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'PBNo','BillAmount'=>'Amount','PaidAmount','Balance' => new Expression('Case When Amount-PaidAmount > 0 then Amount-PaidAmount else 0 end'),
                        'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                    ->join(array("b"=>"Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
                    ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT)
                    ->where(array("b.LeadId" => $leadId,'a.CancelId'=>0));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $arrBuyerStmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(!empty($arrBuyerStmt)) {
                    $this->_view->arrBuyerStmt = $arrBuyerStmt;
                }

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


    public function followupHistoryAction(){
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

        $leadId = $this->params()->fromRoute('leadId');

        $select = $sql->select();
        $select->from('Crm_Leads')
            ->columns(array('LeadId','LeadName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from(array("a"=>'Crm_Leads'))
            ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),'StatusId','UnitTypeId','LeadType','LeadName','ExecutiveId','Email','Mobile','VIP'),
                array("ProjectName"),array("LeadTypeName"),array("UserName"),array("Photo"),array("state"),array("call"=>"Description"))
            ->join(array("f"=>"Crm_LeadTypeMaster"),"a.LeadType=f.LeadTypeId",array("LeadTypeName"),$select::JOIN_LEFT)
            ->join(array("k"=>"WF_Users"),"a.ExecutiveId=k.UserId",array("UserName" => 'EmployeeName'),$select::JOIN_LEFT)
            ->join(array("h"=>"Crm_LeadPersonalInfo"),"a.LeadId=h.LeadId",array("Photo"),$select::JOIN_LEFT)
            ->join(array("i"=>"Crm_StatusMaster"),"a.StatusId=i.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
            ->where(array('a.LeadId' => $leadId));

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array("a"=>'Crm_LeadFollowup'))
            ->columns(array('NextCallDate'=>new Expression("CONVERT(varchar(10),a.NextCallDate,105)"),'PickUpTime'))
            ->join(array("i"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=i.CallTypeId",array("NextCallType"=>"Description"),$select::JOIN_LEFT)
            ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
            ->where(array("a.LeadId"=>$leadId))
            ->order("EntryId desc");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->lastFollowdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


        $select = $sql->select();
        $select->from(array("a"=>'Crm_LeadFollowup'))
            ->columns(array('EntryId','DemandLetterId','FollowUpDate'=>new Expression("CONVERT(varchar(10),FollowUpDate,105)"),'LeadId','UnitTypeId','CallTypeId','NextFollowupTypeId','StatusId','Remarks','NextCallDate'=>new Expression("CONVERT(varchar(10),NextCallDate,105)"),'ExecutiveId','CallerSid'),
                array("LeadName"),array("UserName"),array("Nat"=>"Description"),
                array("CallType"=>"Description"),array("next"=>"Description"),
                array("UnitTypeName"),array("state"=>"Description"))
            ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
            ->join(array("d"=>"Crm_NatureMaster"),"a.NatureId=d.NatureId",array("Nat"=>"Description"),$select::JOIN_LEFT)
            ->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
            ->join(array("f"=>"Crm_CallTypeMaster"),"a.CallTypeId=f.CallTypeId",array("CallType"=>"Description"),$select::JOIN_LEFT)
            ->join(array("g"=>"WF_Users"),"a.UserId=g.UserId",array("UserName" => 'EmployeeName'),$select::JOIN_LEFT)
            ->join(array("h"=>"Crm_ProgressBillTrans"),new expression("a.DemandLetterId=h.ProgressBillTransId and h.CancelId=0"),array("DemandLetter","UnitId"),$select::JOIN_LEFT);
        $select->where(array("a.LeadId"=>$leadId))
            ->order("a.FollowUpDate Desc");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->responseFollowdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $select = $sql->select();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $select->from(array("a"=>'Crm_Leads'))
                    ->columns(array('*'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
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

    public function agreementGenerationAction(){
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
            $select = $sql->select();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result="";
                $postData = $request->getPost();
                $unitId = $this->bsf->isNullCheck($postData['unitId'],'number');

                $agreementId = $this->bsf->isNullCheck($postData['agreementId'],'number');
                $agreementTypeId = $this->bsf->isNullCheck($postData['agreementTypeId'],'number');
                $buyerId = $this->bsf->isNullCheck($postData['buyerId'],'number');
                $docDate = $this->bsf->isNullCheck($postData['docDate'],'date');
                $mergeTags = $this->bsf->getMergeTags($agreementTypeId);

                $select = $sql->select();
                $select->from('PM_AgreementTemplate')
                    ->columns(array('TemplateContent'))
                    ->where(array("AgreementTypeId"=>$agreementTypeId,"TemplateId"=>$agreementId,'DeleteFlag'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $agreementTemplateContent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result = $agreementTemplateContent['TemplateContent'];


                $select = $sql->select();
                $select->from(array('a'=>'Crm_Leads'))
                    ->join(array('b'=>'Crm_LeadPersonalInfo'),"a.LeadId=b.LeadId",array("FatherName","DOB"),$select::JOIN_LEFT)
                    ->join(array('c'=>'Crm_LeadAddress'),new Expression("a.LeadId=c.LeadId and c.AddressType='P'"),array("Address1","Address2","PinCode","Locality","PanNo"),$select::JOIN_LEFT)
                    ->join(array('d'=>'WF_CityMaster'),"c.CityId=d.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array('e'=>'WF_StateMaster'),"c.StateId=e.StateId",array("StateName"),$select::JOIN_LEFT)
                    ->join(array('f'=>'WF_CountryMaster'),"c.CountryId=f.CountryId",array("CountryName"),$select::JOIN_LEFT)
                    ->join(array('g'=>'Crm_LeadCoApplicantInfo'),"a.LeadId=g.LeadId",array("CoApplicantName","CoAppDob"=>new Expression("g.DOB")),$select::JOIN_LEFT)
                    ->columns(array('LeadName','Mobile','Email'))
                    ->where(array("a.LeadId"=>$buyerId,'a.DeleteFlag'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $leadDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a'=>'Crm_Leads'))
                    ->join(array('b'=>'Crm_LeadAddress'),"a.LeadId=b.LeadId",array("Address1","PanNo"),$select::JOIN_LEFT)
                    ->columns(array('LeadId'))
                    ->where(array("a.LeadId"=>$buyerId,'a.DeleteFlag'=>0,'b.AddressType'=>'COA'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $CoaDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a'=>'KF_UnitMaster'))
                    ->join(array('b'=>'KF_BlockMaster'),"a.BlockId=b.BlockId",array("BlockName"),$select::JOIN_LEFT)
                    ->join(array('c'=>'KF_FloorMaster'),"a.FloorId=c.FloorId",array("FloorName"),$select::JOIN_LEFT)
                    ->join(array('d'=>'Crm_UnitDetails'),"a.UnitId=d.UnitId",array("*"),$select::JOIN_LEFT)
                    ->join(array('e'=>'Crm_UnitBooking'),"a.UnitId=e.UnitId",array("BookingDate",'BookRate'=>'Rate','BOther'=>'OtherCostAmount','BQual'=>'QualifierAmount','BDiscountType'=>'DiscountType','BNet'=>'NetAmount','BBase'=>'BaseAmount','BConst'=>'ConstructionAmount','BLand'=>'LandAmount','BookingId','Approve'),$select::JOIN_LEFT)
                    ->join(array('f'=>'WF_OperationalCostCentre'),"a.ProjectId=f.ProjectId",array(),$select::JOIN_LEFT)
                    ->join(array('g'=>'WF_CostCentre'),"f.FACostCentreId=g.CostCentreId",array("CostCentreName","Address","Pincode"),$select::JOIN_LEFT)
                    ->join(array('h'=>'WF_CityMaster'),"g.CityId=h.CityId",array("CityName"),$select::JOIN_LEFT)
                    ->join(array('i'=>'Crm_ProjectDetail'),"a.ProjectId=i.ProjectId",array("LRegistrationValue"),$select::JOIN_LEFT)
                    ->columns(array('UnitNo','UnitArea','ProjectId','UnitTypeId'))
                    ->where(array("a.UnitId"=>$unitId,'a.DeleteFlag'=>0,'e.DeleteFlag'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $unitDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();



                $select = $sql->select();
                $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
                    ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId = b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Crm_UnitTypeOtherCostTrans'), 'a.OtherCostId = c.OtherCostId', array('Amount'), $select::JOIN_LEFT)
                    ->where(array('a.ProjectId' =>$unitDetails['ProjectId'],'b.OtherCostTypeId'=>3,'c.UnitTypeId' =>$unitDetails['UnitTypeId']));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $carParkAmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

//                $select = $sql->select();
//                $select->from(array('a'=>'Crm_ProjectOtherCostTrans'))
//                    ->join(array('b'=>'Crm_OtherCostMaster'),"a.OtherCostId=b.OtherCostId",array(),$select::JOIN_LEFT)
//                    ->columns(array("Amount"=>new expression("SUM(a.Amount)")))
//                    ->where(array("a.ProjectId"=>$unitDetails['ProjectId'],'b.OtherCostTypeId'=>3));
//               echo $statement = $sql->getSqlStringForSqlObject($select);die;
//                $carParkAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                foreach($mergeTags as $mer):
                    $output = strtolower(substr($mer, 2, -2));


                    switch ($output) {
                        case 'document_date':
                            $result=str_replace($mer,$docDate,$result);
                            break;
                        case 'name':
                            $result=str_replace($mer,$leadDetails['LeadName'],$result);
                            break;
                        case 'father_name':
                            $result=str_replace($mer,$leadDetails['FatherName'],$result);
                            break;
                        case 'age':
                            $dob = $this->bsf->isNullCheck($leadDetails['DOB'],'date');
                            $today = date("Y-m-d");
                            $diff = date_diff(date_create($dob), date_create($today));
                            $result=str_replace($mer,$diff->format('%y'),$result);
                            break;
                        case 'address1':

                            $result=str_replace($mer,$leadDetails['Address1'],$result);


                            break;
                        case 'address2':
                            $result=str_replace($mer,$leadDetails['Address2'],$result);
                            break;
                        case 'city':
                            $result=str_replace($mer,$leadDetails['CityName'],$result);

                            break;
                        case 'state':
                            $result=str_replace($mer,$leadDetails['StateName'],$result);
                            break;
                        case 'country':
                            $result=str_replace($mer,$leadDetails['CountryName'],$result);
                            break;
                        case 'pincode':
                            $result=str_replace($mer,$leadDetails['PinCode'],$result);
                            break;
                        case 'locality':
                            $result=str_replace($mer,$leadDetails['Locality'],$result);
                            break;
                        case 'pan_no':
                            $result=str_replace($mer,$leadDetails['PanNo'],$result);
                            break;
                        case 'unit_no':
                            $result=str_replace($mer,$unitDetails['UnitNo'],$result);
                            break;
                        case 'block':
                            $result=str_replace($mer,$unitDetails['BlockName'],$result);
                            break;
                        case 'level':
                            $result=str_replace($mer,$unitDetails['FloorName'],$result);
                            break;
                        case 'uds':
                            $result=str_replace($mer,$unitDetails['UDSLandArea'],$result);
                            break;
                        case 'area':
                            $result=str_replace($mer,$unitDetails['UnitArea'],$result);
                            break;
                        case 'finalisation_date':
                            $result=str_replace($mer,$unitDetails['BookingDate'],$result);
                            break;
                        case 'costcentre_name':
                            $result=str_replace($mer,$unitDetails['CostCentreName'],$result);
                            break;
                        case 'costcentre_address':
                            $result=str_replace($mer,$unitDetails['Address'],$result);
                            break;
                        case 'costcentre_city':
                            $result=str_replace($mer,$unitDetails['CityName'],$result);
                            break;
                        case 'costcentre_pincode':
                            $result=str_replace($mer,$unitDetails['Pincode'],$result);
                            break;
                        case 'co_applicant_name':
                            $result=str_replace($mer,$leadDetails['CoApplicantName'],$result);
                            break;
                        case 'co_applicant_address':
                            $result=str_replace($mer,$CoaDetails['Address1'],$result);
                            break;
                        case 'co_applicant_age':
                            $dob = $this->bsf->isNullCheck($leadDetails['CoAppDob'],'date');
                            $today = date("Y-m-d");
                            $diff = date_diff(date_create($dob), date_create($today));
                            $result=str_replace($mer,$diff->format('%y'),$result);
                            break;
//                        case 'co_applicant_relationship_with_buyer':
//                            $result=str_replace($mer,$unitDetails['UDSLandArea'],$result);
//                            break;
                        case 'co_applicant_pan_no':
                            $result=str_replace($mer,$CoaDetails['PanNo'],$result);
                            break;
                        case 'mobile_no':
                            $result=str_replace($mer,$leadDetails['Mobile'],$result);
                            break;
                        case 'email':
                            $result=str_replace($mer,$leadDetails['Email'],$result);
                            break;
                        case 'registration_value':
                            $result=str_replace($mer,$unitDetails['LRegistrationValue'].' %',$result);
                             break;
                        case 'rate':
                            $result=str_replace($mer,$unitDetails['BookRate'],$result);
                            break;
                        case 'rate_in_words':
                            $result=str_replace($mer,$this->convertAmountToWords($unitDetails['BookRate']),$result);
                            break;
                        case 'car_park_cost':
                            $result=str_replace($mer,$carParkAmt['Amount'],$result);
                            break;
                        case 'car_park_cost_in_words':
                            $result=str_replace($mer,$this->convertAmountToWords($carParkAmt['Amount']),$result);
                            break;
                        case 'basic_cost':
                            $result=str_replace($mer,$unitDetails['BBase'],$result);
                            break;

                        case 'basic_cost_in_words':
                            $result=str_replace($mer,$this->convertAmountToWords($unitDetails['BBase']),$result);
                            break;
                        case 'unit_cost':
                            $result=str_replace($mer,$unitDetails['BNet'],$result);
                            break;
                        case 'unit_cost_in_words':
                            $result=str_replace($mer,$this->convertAmountToWords($unitDetails['BNet']),$result);
                            break;
                        case 'land_cost':
                            $result=str_replace($mer,$unitDetails['BLand'],$result);
                            break;
                        case 'land_cost_in_words':
                            $result=str_replace($mer,$this->convertAmountToWords($unitDetails['BLand']),$result);
                            break;
                        case 'construction_cost':
                            $result=str_replace($mer,$unitDetails['BConst'],$result);
                            break;
                        case 'construction_cost_in_words':
                            $result=str_replace($mer,$this->convertAmountToWords($unitDetails['BConst']),$result);
                            break;
                        case 'advance':
                            $result=str_replace($mer,$unitDetails['AdvAmount'],$result);

                            break;
                        case 'advance_in_words':
                            $result=str_replace($mer,$this->convertAmountToWords($unitDetails['AdvAmount']),$result);
                            break;
                        case 'document_day':
                            $result=str_replace($mer,date('l',strtotime($docDate)),$result);
                            break;
                        case 'document_month':
                            $result=str_replace($mer,date('F',strtotime($docDate)),$result);
                            break;
                        default:
                            break;
                    }
                endforeach;

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
                    $postData = $request->getPost();
                    $unitId = $this->bsf->isNullCheck($postData['UnitNo'],'number');
                    $buyerId = $this->bsf->isNullCheck($postData['buyerId'],'number');
                    $agreementId = $this->bsf->isNullCheck($postData['agreementId'],'number');
                    // echo $postData['templateContentHide'];die;

                    $agreement = $postData['templateContentHide'];

                    $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";

                    require_once($path);

                    $dompdf = new DOMPDF();
                    $dompdf->load_html($agreement);


                    $dompdf->set_paper("A4");
                    $dompdf->render();

//                            $dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));

                    $canvas = $dompdf->get_canvas();
                    $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                    $output = $dompdf->output();
                    $fileName = "agreement_{$unitId}.pdf";

                    $dir = 'public/uploads/crm/agreement/'.$unitId.'/';
                    if(!is_dir($dir)){
                        mkdir($dir, 0755, true);
                    }
                    file_put_contents($dir.$fileName, $output);

                    $agreement = htmlentities($postData['templateContentHide']);

                    $insert  = $sql->insert('Crm_UnitDocuments');
                    $newData = array(
                        'UnitId'  => $unitId,
                        'TemplateContent' => $agreement,
                        'AgreementId' =>$agreementId,
                        'TemplatePath' => $fileName,
                        'DeleteFlag'=>0
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $documentId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $insert = $sql->insert('Crm_LeadFollowUp');
                    $insertData = array(
                        'LeadId' => $buyerId,
                        'FollowUpDate'=>date('m-d-Y H:i:s'),
                        'NatureId'=>3,
                        'LeadFlag'=>'B',
                        'UserId'=>$this->auth->getIdentity()->UserId,
                        'DocumentId'=>$documentId
                    );
                    $insert->values($insertData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $this->redirect()->toRoute('crm/unit-fulldetails', array('controller' => 'project', 'action' => 'unit-fulldetails','UnitDetailId'=>$unitId));


                    $connection->commit();

                } catch ( PDOException $e ) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $buyerId = $this->bsf->isNullCheck($this->params()->fromRoute('BuyerId'),'number');
                $agreementId = $this->bsf->isNullCheck($this->params()->fromRoute('AgreementId'),'number');
                $this->_view->buyerId = $buyerId;
                $this->_view->agreementId=$agreementId;


                $subQuery = $sql->select();
                $subQuery->from('Crm_UnitDocuments')
                    ->columns(array('UnitId'));
                $subQuery->where(array("AgreementId"=>$agreementId,"DeleteFlag"=>0));

                $select = $sql->select();
                $select->from(array('a'=>'Crm_UnitBooking'))
                    ->join(array("b"=>"KF_UnitMaster"),"a.UnitId=b.UnitId",array("UnitNo","UnitId"),$select::JOIN_LEFT)
                    ->columns(array())
                    ->where(array("a.LeadId"=>$buyerId,"a.DeleteFlag"=>0,"b.DeleteFlag"=>0));
                $select->where->expression('a.UnitId NOT IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->unitDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_AgreementTypeMaster')
                    ->columns(array('AgreementTypeId','AgreementTypeName'))
                    ->where(array("AgreementTypeId"=>$agreementId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->agreementName = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from('PM_AgreementTemplate')
                    ->columns(array('TemplateId','TemplateName'))
                    ->where(array("AgreementTypeId"=>$agreementId,'DeleteFlag'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->agreementTemplate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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

        return $result . "Rupees  " . $points . " Only.";
    }


    public function handingoverAction(){
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
        $request = $this->getRequest();
        $sql = new Sql($dbAdapter);
        $userId = $this->auth->getIdentity()->UserId;

        $leadId = $this->bsf->isNullCheck($this->params()->fromRoute('leadId'),'number');


        if(!$this->getRequest()->isXmlHttpRequest() && $leadId == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('crm/default', array('controller' => 'Buyer','action' => 'followupEntry'));
        }

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here

                $postParams = $request->getPost();
                $unitId=$postParams['unitNoId'];
                $select = $sql->select();
                $select->from(array('a'=>'Crm_CheckListProjectTrans'))
                    ->columns(array('ProjectId'))
                    ->join(array('b'=>'Crm_CheckListMaster'),"a.CheckListId=b.CheckListId ",array('CheckListName','CheckListId'),$select::JOIN_INNER)
                    ->join(array('c'=>'KF_UnitMaster'),"a.ProjectId=c.ProjectId ",array(),$select::JOIN_INNER)
                    ->where(array("c.UnitId"=>$unitId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $checklists= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($checklists));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams = $request->getPost();
                $callTypeId=$postParams['CallTypeId'];
                $LeadId=$postParams['LeadId'];
                try {
                    $unitId=$postParams['unitNo'];
                    $rowCount = $postParams['rowCount'];

                    $delete = $sql->delete();
                    $delete->from('Crm_HandingoverCheckTrans')
                        ->where(array('UnitId'=>$unitId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    for($i=1;$i<=$rowCount;$i++) {
                        $checkdate = $this->bsf->isNullCheck($postParams['CheckDate_'. $i], 'string');
                        $cDate =date('Y-m-d');
                        if(strtotime($checkdate)!=false) {
                            $cDate=date('Y-m-d', strtotime($checkdate));
                        }
                        $checkInsert = $sql->insert();
                        $checkInsert->into('Crm_HandingoverCheckTrans');
                        $checkInsert->values(array('CheckListId' => $this->bsf->isNullCheck($postParams['CheckListId_'. $i], 'number'),
                            'Date' => $cDate,
                            'ExecutiveId' => $this->bsf->isNullCheck($postParams['UserId_'. $i], 'number'),
                            'UnitId' => $unitId,
                            'IsChecked' => $this->bsf->isNullCheck($postParams['select_'. $i], 'number')));
                        $statement = $sql->getSqlStringForSqlObject($checkInsert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    if($unitId > 0 && $callTypeId == 19){
                        $select = $sql->select();
                        $select->from('Crm_LeadFollowup')
                            ->columns(array('EntryId'))
                            ->where(array('LeadId' =>$leadId))
                            ->order("EntryId desc");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $entry  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $update = $sql->update();
                        $update->table('Crm_LeadFollowup');
                        $update->set(array(
                            'Completed'  => 1,
                            'CompletedDate'  => date('Y-m-d H:i:s'),

                        ));
                        $update->where(array('EntryId'=>$entry['EntryId']));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert();
                        $insert->into('Crm_LeadFollowup');
                        $insert->Values(array(
                            'LeadId' => $leadId,
                            'FollowUpDate' => date('Y-m-d'),
                            'ExecutiveId'=>$userId,
                            'StatusId'=>1,
                            'LeadFlag'=>'B',
                            'UserId'=>$this->auth->getIdentity()->UserId,
                            'CallTypeId' => 19));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('Crm_Leads');
                        $update->set(array(
                            'StatusId' => 1
                        ));
                        $update->where(array('LeadId' => $leadId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('Crm_UnitBooking');
                        $update->set(array(
                            'Handover'  => 'Y',
                        ));
                        $update->where(array('UnitId'=>$unitId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                }
                $this->redirect()->toRoute("crm/handing-over", array("controller" => "buyer","action" => "handingover","leadId"=>$LeadId,'callTypeId'=>$callTypeId));

            } else {
                $this->_view->leadId = $leadId;
                $this->_view->CallTypeId = $this->params()->fromRoute('callTypeId');

                $select = $sql->select();
                $select->from(array("a" => 'Crm_UnitBooking'))
                    ->columns(array('UnitId'))
                    ->join(array("l" => "Crm_Leads"), "a.LeadId=l.LeadId", array(), $select::JOIN_INNER)
                    ->join(array("m" => "KF_UnitMaster"), "m.UnitId=a.UnitId", array("UnitNo"), $select::JOIN_INNER)
//                    ->join(array("f" => "Proj_ProjectMaster"), "m.ProjectId=f.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                    ->where(array('a.LeadId' =>$leadId, 'a.Handover' => 'N','a.DeleteFlag'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $responseunit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->responseunit = $responseunit;

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('data' => 'UserId', 'value' => 'EmployeeName'))
                    ->where(array('DeleteFlag' => 0));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->arrExecutives = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
        }

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function allotmentLetterAction(){
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
            $select = $sql->select();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postData = $request->getPost();
                    $unitId = $this->bsf->isNullCheck($postData['UnitNo'],'number');
                    $allotmentDate = $this->bsf->isNullCheck($postData['allotment_date'],'date');

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->columns(array('UnitId', 'UnitNo', 'UnitArea','ProjectId','FloorId','UnitTypeId'))
                        ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('UDSLandArea','CarpetArea'), $select::JOIN_LEFT)
                        ->join(array('t' => 'Proj_ProjectMaster'), 't.ProjectId=a.ProjectId',array('ProjectName'), $select::JOIN_LEFT)
                        ->join(array('u' => 'KF_FloorMaster'), 'u.FloorId=a.FloorId',array('FloorName'), $select::JOIN_LEFT)
                        ->join(array('z' => 'KF_BlockMaster'), 'z.blockId=a.BlockId',array('BlockName'), $select::JOIN_LEFT)
                        ->join(array('v' => 'KF_UnitTypeMaster'), 'v.UnitTypeId=a.UnitTypeId',array('UnitTypeName'), $select::JOIN_LEFT)
                        ->join(array('d' => 'WF_operationalCostcentre'), 'd.ProjectId=a.ProjectId',array('FACostCentreId'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WF_CostCentre'), 'e.CostCentreId=d.FACostCentreId',array('CompanyId'), $select::JOIN_LEFT)
                        ->join(array('f' => 'WF_CompanyMaster'), 'f.CompanyId=e.CompanyId',array('CompanyName'), $select::JOIN_LEFT)
                        ->join(array('g' => 'Proj_UOM'), 'b.UnitId=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
                        ->join(array('i' => 'Crm_UnitBooking'), new Expression("i.UnitId=a.UnitId and i.DeleteFlag=0"), array('BookingDate','Rate','Discount','DiscountType','NetAmount','AdvAmount'), $select::JOIN_LEFT)
                        ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName','Email','Mobile'), $select::JOIN_LEFT)
                        ->join(array('n' => 'Crm_LeadAddress'), new Expression("h.LeadId=n.LeadId and n.AddressType='p'"), array('Address1','PinCode'), $select::JOIN_LEFT)//City Removed
                        ->where(array("a.UnitId"=>$unitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";

                    $pdfHtml = $this->generateAllotmentPdf($unitInfo,$allotmentDate);

                    require_once($path);

                    $dompdf = new DOMPDF();
                    $dompdf->load_html($pdfHtml);


                    $dompdf->set_paper("A4");
                    $dompdf->render();

//                            $dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));

                    $canvas = $dompdf->get_canvas();
                    $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                    $output = $dompdf->output();
                    $fileName = "allotment_{$unitId}.pdf";
                    $content_encoded = base64_encode($output);

                    $dir = 'public/uploads/crm/agreement/'.$unitId.'/';
                    if(!is_dir($dir)){
                        mkdir($dir, 0755, true);
                    }
                    file_put_contents($dir.$fileName, $output);

                    $insert  = $sql->insert('Crm_UnitDocuments');
                    $newData = array(
                        'UnitId'  => $unitId,
                        'TemplateContent' => $pdfHtml,
                        'TemplatePath' => $fileName,
                        'DeleteFlag'=>0
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $mailData = array(
                        array(
                            'name' => 'LEADNAME',
                            'content' => $unitInfo['BuyerName']
                        ),
                        array(
                            'name' => 'UNITNAME',
                            'content' => $unitInfo['UnitNo']
                        ),
                        array(
                            'name' => 'PROJECTNAME',
                            'content' => $unitInfo['ProjectName']
                        ),
                        array(
                            'name' => 'UNITTYPE',
                            'content' => $unitInfo['UnitTypeName']
                        ),
                        array(
                            'name' => 'BLOCKNAME',
                            'content' => $unitInfo['BlockName']
                        ),
                        array(
                            'name' => 'FLOORNAME',
                            'content' => $unitInfo['FloorName']
                        ),
                        array(
                            'name' => 'DISCOUNTTYPE',
                            'content' => $unitInfo['DiscountType']
                        ),
                        array(
                            'name' => 'DISCOUNT',
                            'content' => $unitInfo['Discount']
                        ),
                        array(
                            'name' => 'NETAMOUNT',
                            'content' => $unitInfo['NetAmount']
                        ),

                        array(
                            'name' => 'ADVANCEAMOUNT',
                            'content' => $unitInfo['AdvAmount']
                        ),
                        array(
                            'name' => 'RATE',
                            'content' => $unitInfo['Rate']
                        ),
                        array(
                            'name' => 'LEADMOBILENUMBER',
                            'content' => $unitInfo['Mobile']
                        ),
                        array(
                            'name' => 'MAILID',
                            'content' => $unitInfo['Email']
                        ),
                        array(
                            'name' => 'ADDRESS',
                            'content' => $unitInfo['Address1']
                        ),
                        array(
                            'name' => 'DATEOFBOOKING',
                            'content' => $unitInfo['BookingDate']
                        )
                    );
                    $Tomail = $unitInfo['Email'];
//                    $Tomail = 'sairam@micromen.info';
                    $attachment = array(
                        'name' => $fileName,
                        'type' => "application/pdf",
                        'content' => $content_encoded
                    );
                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    $viewRenderer->MandrilSendMail()->sendMailWithAttachment($Tomail,$config['general']['mandrilEmail'],'Allotment Letter','Crm_AllotmentLetter', $attachment, $mailData);

                    $this->redirect()->toRoute('crm/unit-fulldetails', array('controller' => 'project', 'action' => 'unit-fulldetails','UnitDetailId'=>$unitId));


                    $connection->commit();

                } catch ( PDOException $e ) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $buyerId = $this->bsf->isNullCheck($this->params()->fromRoute('BuyerId'),'number');
                $this->_view->buyerId = $buyerId;

                $subQuery = $sql->select();
                $subQuery->from('Crm_UnitDocuments')
                    ->columns(array('UnitId'));
                $subQuery->where(array("AgreementId"=>0,"DeleteFlag"=>0));


                $select = $sql->select();
                $select->from(array('a'=>'Crm_UnitBooking'))
                    ->join(array("b"=>"KF_UnitMaster"),"a.UnitId=b.UnitId",array("UnitNo","UnitId"),$select::JOIN_LEFT)
                    ->columns(array())
                    ->where(array("a.LeadId"=>$buyerId,"a.DeleteFlag"=>0,"b.DeleteFlag"=>0));
                $select->where->expression('a.UnitId NOT IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->unitDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

    function generateAllotmentPdf($unitInfo,$allotmentDate) {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $allotmentDate = date('d-m-Y', strtotime($allotmentDate));
        $bookingDate = date('d-m-Y', strtotime($unitInfo['BookingDate']));
        $pdfHtml = <<<EOT
  <!-----------------------------------------------pdf----------------------------------------------->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>index</title>
</head>
<style>

.letter-tit 		{font-size:14px;font-family:Arial; color:#000;}
.name-fli 			{font-size:14px;font-family:Arial;color:#000;}
.date-fel 			{font-size:14px;font-family:Arial;color:#000;margin-top:10px;display:block;}
.add-det 			{font-size:14px;font-family:Arial;color:#000;margin:0px;display:block;margin-right:10px;line-height:20px;}
.dears-b 			{font-size:14px;font-family:Arial;color:#000;margin-bottom:5px;display:block;}
.det-b				{font-size:14px;font-family:Arial;color:#000;margin:0px;line-height:20px;}
.add-dets		    {font-size:14px;font-family:Arial;color:#000;margin:0px;line-height:20px;}
.bb-bd 				{font-size:14px;font-family:Arial;color:#000;margin:10px; }
</style>

<body>
<div class="page">
<div class="subpage">
<table cellpadding="0" cellspacing="0" width="590" height="842">
  <!-----------------------------------------------mani content ----------------------------------------------->
  <tr>
    <td><!-----------------------------------------------mani inner content ----------------------------------------------->

      <table width="595" height="842" align="center" cellspacing="0">
        <!-----------------------------------------------Tittle Date ----------------------------------------------->
        <tr>
          <td><table cellpadding="0" cellspacing="0" align="center" width="595" style="background:#fff; width:700px !important; ">
              <tr>
                <td align="center"><b class="letter-tit" style="margin-left:-10px !important;">ALLOTMENT LETTER</b></td>
              </tr>
           
            </table></td>
        </tr>
		
		 <tr>
          <td><table cellpadding="0" cellspacing="0" align="center" width="595">
            
              <tr>
                <td align="right"><b class="date-fel" style="width:700px !important;">{$allotmentDate}</b></td>
              </tr>
            </table></td>
        </tr>
		
        <!-----------------------------------------------Tittle Date  end ----------------------------------------------->
        <!-----------------------------------------------Address area ----------------------------------------------->
        <tr>
          <td><table cellpadding="0" cellspacing="0" align="center" width="595">
              <tr>
                <td align="left" width="200"><b class="name-fli" style="width:200px !important;">{$unitInfo['BuyerName']}</b>
                  <p class="add-det" style="width:200px !important;">{$unitInfo['Address1']}, <br />
                    PinCode : {$unitInfo['PinCode']} ,<br />
                    Phone no : {$unitInfo['Mobile']}</p></td>
              </tr>
            </table></td>
        </tr>
        <!-----------------------------------------------Address area end ----------------------------------------------->
        <tr>
          <td>&nbsp;</td>
        </tr>
        <!-----------------------------------------------content start ----------------------------------------------->
        <tr>
          <td><table cellpadding="0" cellspacing="0" align="center" width="595">
              <tr>
                <td align="left"><b class="dears-b">Dear Sir / Madam,</b></td>
              </tr>
              <tr>
                <td><p class="det-b"><span>Sub</span> : Allotment letter - {$unitInfo['ProjectName']} - Unit No. {$unitInfo['UnitNo']} </p></td>
              </tr>
              <tr>
                <td><p class="det-b"><span>Ref</span> : Booking dated {$bookingDate} </p></td>
              </tr>
            </table></td>
        </tr>
        <!-----------------------------------------------content end ----------------------------------------------->
        <tr>
          <td>&nbsp;</td>
        </tr>
        <!-----------------------------------------------content start ----------------------------------------------->
        <tr>
          <td><table cellpadding="0" cellspacing="0" align="center" width="595">
              <tr>
                <td><p class="add-dets">A very warm welcome to our <b> {$unitInfo['CompanyName']} </b></p></td>
              </tr>
              <tr>
                <td><p class="add-dets">We are glad to inform you that {$unitInfo['UnitNo']} in {$unitInfo['ProjectName']} is alloted to you. </p></td>
              </tr>
              <tr>
                <td><p class="add-dets">Please find below the details of your Unit no<b> {$unitInfo['UnitNo']} </b></p></td>
              </tr>
            </table></td>
        </tr>

        <!-----------------------------------------------content start ----------------------------------------------->
        <tr>
          <td>&nbsp;</td>
        </tr>
        <!-----------------------------------------------table start ----------------------------------------------->
        <tr>
          <td><table cellpadding="0" cellspacing="0" width="595" style="border:1px solid #000; background:#fff; width:700px !important;" >
              <tbody>
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Project</b></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$unitInfo['ProjectName']}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Unit No</b></td>
                  <td align="left"   style="border-bottom:1px solid #000;"><p class="bb-bd">{$unitInfo['UnitNo']}</p></td>
                </tr>
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Unit Type</b></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$unitInfo['UnitTypeName']}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Floor</b></td>
                  <td align="left"   style="border-bottom:1px solid #000;"><p class="bb-bd">{$unitInfo['FloorName']}</p></td>
                </tr>
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Area</b></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$unitInfo['UnitArea']}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">UDS Land Area</b></td>
                  <td align="left" style="border-bottom:1px solid #000;"><p class="bb-bd">{$unitInfo['UDSLandArea']}</p></td>
                </tr>
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Carpet Area</b></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$unitInfo['CarpetArea']}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Land area (sft)</b></td>
                  <td align="left"  style="border-bottom:1px solid #000;"><p class="bb-bd">459</p></td>
                </tr>
              </tbody>
            </table></td>
        </tr>

        <!-----------------------------------------------table end ----------------------------------------------->
        <tr>
          <td>&nbsp;</td>
        </tr>
        <!-----------------------------------------------Thanking  start ----------------------------------------------->
        <tr>
          <td><table cellpadding="0" cellspacing="0" align="center" width="595">
              <tr>
                <td><p class="add-dets"><em>Thanking You and Assuring best of our services at all times, For</em> <b>{$unitInfo['CompanyName']}</b></p></td>
              </tr>
              <tr>
                <td><p class="add-dets"></td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td><em class="add-dets"><b>Authorized Signatory</em></td>
              </tr>
            </table></td>
        </tr>
        <!-----------------------------------------------Thanking  end ----------------------------------------------->
        <tr>
          <td>&nbsp;</td>
        </tr>
       
      </table>

      <!-----------------------------------------------mani inner content end -----------------------------------------------></td>
  </tr>
  <!-----------------------------------------------mani content end ----------------------------------------------->
</table>
</div></div>
</body>
</html>


EOT;
        return $pdfHtml;

    }

}