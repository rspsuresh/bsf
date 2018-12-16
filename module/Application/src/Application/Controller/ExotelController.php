<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

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

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version0X;

class ExotelController extends AbstractActionController
{
    public function __construct()
    {
        $this->bsf = new \BuildsuperfastClass();
        $this->exotel = new \Exotel();
        $this->auth = new AuthenticationService();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function callsAction()
    {
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $action = $this->bsf->isNullCheck($postParams['action'], 'string');
                if ($action == 'connect_call_to_agent') {

                    $update = $sql->update();
                    $update->table('WF_Users')
                        ->set(array('callBusy' => 1))
                        ->where(array("Mobile" => $postParams['from']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $returnString = $this->exotel->connectCallToAgent($postParams['from'], $postParams['to']);
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($returnString);
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
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

            return $this->_view;
        }
    }


    public function exCallAction() {
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $this->_view->setTerminal(true);
                return $this->_view;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {

            } else {
                $postParams = $request->getQuery();

                $callSid = $postParams['CallSid'];
                $cFrom = $postParams['From'];
                $cTo = $postParams['To'];
                $cStatus = $postParams['Status'];
                $dialWhomNumber = $postParams['DialWhomNumber'];

                $startTime = date('Y-m-d H:i:s');
                if (strtotime($postParams['CurrentTime']) != false) {
                    $startTime = date('Y-m-d H:i:s', strtotime($postParams['CurrentTime']));
                }

                $select = $sql->select();
                $select->from(array('a' => 'Tele_IncomingCallTrack'))
                    ->columns(array('CallSid'))
                    ->where(array('CallSid' => $callSid,'DialWhomNumber'=>$dialWhomNumber));
                $statement = $sql->getSqlStringForSqlObject($select);
                $ex = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if ($ex != "") {

                    $update = $sql->update();
                    $update->table('Tele_IncomingCallTrack');
                    $update->set(array(
                        'CurrentDate' => $startTime,
                        'CallTo' => $cTo,
                        'CallFrom' => $cFrom,
                        'DialWhomNumber' => $dialWhomNumber,
                        'Status'=>$cStatus,
                        'ModifiedDate'=>date('Y-m-d H:i:s')
                    ));
                    $update->where(array('CallSid' => $callSid,'DialWhomNumber'=>$dialWhomNumber));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                } else {
                    $campaignNo = (strlen($cTo) > 10) ? substr($cTo, -10) : $cTo;

                    $sDate = date('Y-m-d')." 00:00:00";

                    $select = $sql->select();
                    $select->from("Crm_CampaignRegister")
                        ->columns(array("Id"=>new expression("CampaignId")));
                    $select->where(array("CampaignCallNo"=>$campaignNo,"DeleteFlag"=>0));
//                    $select->where->greaterThanOrEqualTo('StartDate', $sDate);
//                    $select->where->lessThanOrEqualTo('EndDate', $sDate);
                    $select->where("'$sDate' between (StartDate) and (EndDate)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $selectCampaign1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $campId=0;
                    if($selectCampaign1!="" && isset($selectCampaign1) && $selectCampaign1['Id']!="") {

                        $campId=$selectCampaign1['Id'];

                    } else {

                        $select = $sql->select();
                        $select->from("Crm_CampaignRegister")
                            ->columns(array("Id"=>new expression("CampaignId")));
                        $select->where(array("CampaignCallNo"=>$campaignNo,"DeleteFlag"=>0));
                        $select->where->lessThan('EndDate', $sDate);
                        $select->order("EndDate Desc");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $selectCampaign2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if($selectCampaign2!="" && isset($selectCampaign2) && $selectCampaign2['Id']!="") {

                            $campId=$selectCampaign2['Id'];

                        }

                    }

                    $insert = $sql->insert('Tele_IncomingCallTrack');
                    $newData = array(
                        'CallSid' => $callSid,
                        'CurrentDate' => $startTime,
                        'CallTo' => $cTo,
                        'CallFrom' => $cFrom,
                        'DialWhomNumber' => $dialWhomNumber,
                        'Status'=>$cStatus,
                        'ModifiedDate'=>date('Y-m-d H:i:s'),
                        'CampaignId'=>$campId
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                }

                $newTo = (strlen($dialWhomNumber) > 10) ? substr($dialWhomNumber, -10) : $dialWhomNumber;
                $newFrom = (strlen($cFrom) > 10) ? substr($cFrom, -10) : $cFrom;

                $select = $sql->select();
                $select->from(array('a' => 'WF_Users'))
                    ->columns(array('UserId'))
                    ->where(array('Mobile' => $newTo));
                $statement = $sql->getSqlStringForSqlObject($select);
                $executive = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if ($cStatus == "busy") {
                    $update = $sql->update();
                    $update->table('WF_Users')
                        ->set(array('callBusy' => 1))
                        ->where(array("Mobile" => $newTo));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $update = $sql->update();
                    $update->table('WF_Users')
                        ->set(array('callBusy' => 0))
                        ->where(array("Mobile" => $newTo));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }


                $leadId = 0;
                $leadName = "";
                $leadFlag = 0;
                $select = $sql->select();
                $select->from(array('a' => 'Crm_Leads'))
                    ->columns(array('LeadId', 'LeadName'))
                    ->where(array('Mobile' => $newFrom, 'DeleteFlag' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $lex = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Tele_Leads'))
                    ->columns(array('LeadId', 'LeadName'))
                    ->where(array('Mobile' => $newFrom, 'DeleteFlag' => 0,'ConvertLead'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $tEx = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if ($lex != "") {
                    $leadId = $lex['LeadId'];
                    $leadName = $lex['LeadName'];
                    $leadFlag = 1;
                } else if ($tEx != "") {
                    $leadId = $tEx['LeadId'];
                    $leadName = $tEx['LeadName'];
                    $leadFlag = 2;
                }

                if ($executive != '') {

                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    $client = new Client(new Version0X($config['general']['socketIpCall']));
                    $client->initialize();
                    $client->emit('msg', ['mode' => 'single', 'type' => 'infirst', 'cstatus' => $cStatus, 'user' => $executive['UserId'], 'LeadId' => $leadId, 'LeadName' => $leadName, 'From' => $newFrom, 'callSid' => $callSid, 'leadFlag' => $leadFlag]);
                    $client->close();
                }
            }

            $result = "";
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent($result)->setStatusCode(200);
            return $response;
        }
    }

    public function cronTodayCallAction()
    {

        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result = "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {
                $Date = date('Y-m-d') . " 00:00:00";

                $select = $sql->select();
                $select->from('Tele_Contacts')
                    ->columns(array('*'));
                $select->where(array('AttendFlag' => 0, 'DeleteFlag' => 0, 'Date' => $Date));
                $statement = $sql->getSqlStringForSqlObject($select);
                $list = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $PositionTypeId = array(3);
                $sub = $sql->select();
                $sub->from(array('a' => 'WF_PositionMaster'))
                    ->join(array("b" => "WF_PositionType"), "a.PositionTypeId=b.PositionTypeId", array(), $sub::JOIN_LEFT)
                    ->columns(array('PositionId'))
                    ->where(array("b.PositionTypeId" => $PositionTypeId));

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('Mobile', 'UserId'))
                    ->where->expression("PositionId IN ?", array($sub));
                $select->where(array('TeleCalling' => 1, 'callBusy' => 0, 'DeleteFlag' => 0, 'Lock' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultsExe = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $index = 0;
                $cValue = count($list);
                foreach ($resultsExe as $rl) {
                    if ($index >= $cValue) break;
                    $update = $sql->update();
                    $update->table('WF_Users')
                        ->set(array('callBusy' => 1))
                        ->where(array("Mobile" => $rl['Mobile']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $toMobile = $list[$index]['Mobile'];
                    $returnString = $this->exotel->connectCallToAgent($rl['Mobile'], $toMobile);

                    $data_xml = simplexml_load_string($returnString);
                    $pParams = $data_xml->Call;

                    $update = $sql->update();
                    $update->table('Tele_Contacts')
                        ->set(array('CallSid' => $pParams->Sid))
                        ->where(array("ContactId" => $list[$index]['ContactId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    $client = new Client(new Version0X($config['general']['socketIpCall']));
                    $client->initialize();
                    $client->emit('msg', ['mode' => 'single', 'type' => 'outfirst', 'user' => $rl['UserId'], 'callSid' => $pParams->Sid, 'To' => $toMobile]);
                    $client->close();

                    $index++;
                }

            }

            return $this->_view;
        }
    }

    public function outboundCallbackAction()
    {

        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();

                $returnString = $this->exotel->getCallDetails($postParams['CallSid']);
                $data_xml = simplexml_load_string($returnString);

                $pParams = $data_xml->Call;

                $callSid = $this->bsf->isNullCheck($pParams->Sid, 'string');

                $recordingUrl = $this->bsf->isNullCheck($pParams->RecordingUrl, 'string');
                $parentCallSid = $this->bsf->isNullCheck($pParams->ParentCallSid, 'string');
                $accountSid = $this->bsf->isNullCheck($pParams->AccountSid, 'string');
                $to = $this->bsf->isNullCheck($pParams->To, 'string');
                $from = $this->bsf->isNullCheck($pParams->From, 'string');
                $phoneNumberSid = $this->bsf->isNullCheck($pParams->PhoneNumberSid, 'string');
                $status = $this->bsf->isNullCheck($pParams->Status, 'string');
                $duration = $this->bsf->isNullCheck($pParams->Duration, 'string');
                $price = $this->bsf->isNullCheck($pParams->Price, 'string');
                $direction = $this->bsf->isNullCheck($pParams->Direction, 'string');
                $answeredBy = $this->bsf->isNullCheck($pParams->AnsweredBy, 'string');
                $forwardedFrom = $this->bsf->isNullCheck($pParams->ForwardedFrom, 'string');
                $callerName = $this->bsf->isNullCheck($pParams->CallerName, 'string');


                $dateCreated = date('Y-m-d H:i:s');
                if (strtotime($pParams->DateCreated) != false) {
                    $dateCreated = date('Y-m-d H:i:s', strtotime($pParams->DateCreated));
                }

                $dateUpdated = date('Y-m-d H:i:s');
                if (strtotime($pParams->DateUpdated) != false) {
                    $dateUpdated = date('Y-m-d H:i:s', strtotime($pParams->DateUpdated));
                }

                $startTime = date('Y-m-d H:i:s');
                if (strtotime($pParams->StartTime) != false) {
                    $startTime = date('Y-m-d H:i:s', strtotime($pParams->StartTime));
                }

                $endTime = date('Y-m-d H:i:s');
                if (strtotime($pParams->EndTime) != false) {
                    $endTime = date('Y-m-d H:i:s', strtotime($pParams->EndTime));
                }

                $select = $sql->select();
                $select->from(array('a' => 'Tele_CallerDetails'))
                    ->columns(array('CallSid'))
                    ->where(array('CallSid' => $callSid));
                $statement = $sql->getSqlStringForSqlObject($select);
                $ex = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if ($ex != "") {
                    $update = $sql->update();
                    $update->table('Tele_CallerDetails');
                    $update->set(array(
                        'ParentCallSid' => $parentCallSid,
                        'DateCreated' => $dateCreated,
                        'DateUpdated' => $dateUpdated,
                        'Accountsid' => $accountSid,
                        'ToNum' => $to,
                        'FromNum' => $from,
                        'PhoneNumberSid' => $phoneNumberSid,
                        'Status' => $status,
                        'StartTime' => $startTime,
                        'EndTime' => $endTime,
                        'Duration' => $duration,
                        'Price' => $price,
                        'Direction' => $direction,
                        'AnsweredBy' => $answeredBy,
                        'ForwardedFrom' => $forwardedFrom,
                        'RecordingUrl' => $recordingUrl,
                        'CallerName' => $callerName,
                        'ModifiedDate'=>date('Y-m-d H:i:s'),
                        'LastUpdate'=>1
                    ));
                    $update->where(array('CallSid' => $callSid));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert('Tele_CallerDetails');
                    $newData = array(
                        'CallSid' => $callSid,
                        'ParentCallSid' => $parentCallSid,
                        'DateCreated' => $dateCreated,
                        'DateUpdated' => $dateUpdated,
                        'Accountsid' => $accountSid,
                        'ToNum' => $to,
                        'FromNum' => $from,
                        'PhoneNumberSid' => $phoneNumberSid,
                        'Status' => $status,
                        'StartTime' => $startTime,
                        'EndTime' => $endTime,
                        'Duration' => $duration,
                        'Price' => $price,
                        'Direction' => $direction,
                        'AnsweredBy' => $answeredBy,
                        'ForwardedFrom' => $forwardedFrom,
                        'RecordingUrl' => $recordingUrl,
                        'CallerName' => $callerName,
                        'ModifiedDate'=>date('Y-m-d H:i:s'),
                        'LastUpdate'=>1
                    );
                    $insert->values($newData);
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                $newFrom = (strlen($from) > 10) ? substr($from, -10) : $from;
                $newTo = (strlen($to) > 10) ? substr($to, -10) : $to;

                $select = $sql->select();
                $select->from(array('a' => 'WF_Users'))
                    ->columns(array('UserId'))
                    ->where(array('Mobile' => $newFrom));
                $statement = $sql->getSqlStringForSqlObject($select);
                $executive = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $select = $sql->select();
                $select->from('Tele_Contacts')
                    ->columns(array('ContactId'));
                $select->where(array('CallSid' => $callSid));
                $statement = $sql->getSqlStringForSqlObject($select);
                $dl = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $cond = "cron";
                if ($dl == "") {
                    $cond = "followup";

                }


                $leadId = 0;
                $leadName = "";
                $leadFlag = 0;
                $select = $sql->select();
                $select->from(array('a' => 'Crm_Leads'))
                    ->columns(array('LeadId', 'LeadName'))
                    ->where(array('Mobile' => $newTo, 'DeleteFlag' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $lex = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array('a' => 'Tele_Leads'))
                    ->columns(array('LeadId', 'LeadName'))
                    ->where(array('Mobile' => $newTo, 'DeleteFlag' => 0,'ConvertLead'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $tEx = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if ($lex != "") {
                    $leadId = $lex['LeadId'];
                    $leadName = $lex['LeadName'];
                    $leadFlag = 1;
                } else if ($tEx != "") {
                    $leadId = $tEx['LeadId'];
                    $leadName = $tEx['LeadName'];
                    $leadFlag = 2;
                }


                if ($executive != '') {

                    $update = $sql->update();
                    $update->table('WF_Users')
                        ->set(array('callBusy' => 0))
                        ->where(array("Mobile" => $newFrom));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    if ($status == "completed") {
                        $update = $sql->update();
                        $update->table('Tele_Contacts')
                            ->set(array('AttendFlag' => 1))
                            ->where(array("CallSid" => $callSid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }


                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();


                    $client = new Client(new Version0X($config['general']['socketIpCall']));
                    $client->initialize();
                    $client->emit('msg', ['mode' => 'single', 'type' => 'out', 'newStatus' => $status, 'user' => $executive['UserId'], 'callSid' => $callSid, 'LeadId' => $leadId,'leadFlag'=>$leadFlag,'leadName'=>$leadName, 'To' => $newTo, 'check' => $cond]);
                    $client->close();


                }

            }

            $result = "";
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent($result)->setStatusCode(200);
            return $response;
        }
    }

    public function inboundCallbackAction()
    {

        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        $postParams = $request->getQuery();
        $callSid = $postParams['CallSid'];


        if(isset($callSid) && $callSid!="") {

            $returnString = $this->exotel->getCallDetails($callSid);
            $data_xml = simplexml_load_string($returnString);

            $pParams = $data_xml->Call;

            $recordingUrl = $this->bsf->isNullCheck($pParams->RecordingUrl, 'string');
            $parentCallSid = $this->bsf->isNullCheck($pParams->ParentCallSid, 'string');
            $accountSid = $this->bsf->isNullCheck($pParams->AccountSid, 'string');
            $to = $this->bsf->isNullCheck($pParams->To, 'string');
            $from = $this->bsf->isNullCheck($pParams->From, 'string');
            $phoneNumberSid = $this->bsf->isNullCheck($pParams->PhoneNumberSid, 'string');
            $status = $this->bsf->isNullCheck($pParams->Status, 'string');
            $duration = $this->bsf->isNullCheck($pParams->Duration, 'string');
            $price = $this->bsf->isNullCheck($pParams->Price, 'string');
            $direction = $this->bsf->isNullCheck($pParams->Direction, 'string');
            $answeredBy = $this->bsf->isNullCheck($pParams->AnsweredBy, 'string');
            $forwardedFrom = $this->bsf->isNullCheck($pParams->ForwardedFrom, 'string');
            $callerName = $this->bsf->isNullCheck($pParams->CallerName, 'string');

            $dateCreated = date('Y-m-d H:i:s');
            if (strtotime($pParams->DateCreated) != false) {
                $dateCreated = date('Y-m-d H:i:s', strtotime($pParams->DateCreated));
            }

            $dateUpdated = date('Y-m-d H:i:s');
            if (strtotime($pParams->DateUpdated) != false) {
                $dateUpdated = date('Y-m-d H:i:s', strtotime($pParams->DateUpdated));
            }

            $startTime = date('Y-m-d H:i:s');
            if (strtotime($pParams->StartTime) != false) {
                $startTime = date('Y-m-d H:i:s', strtotime($pParams->StartTime));
            }

            $endTime = date('Y-m-d H:i:s');
            if (strtotime($pParams->EndTime) != false) {
                $endTime = date('Y-m-d H:i:s', strtotime($pParams->EndTime));
            }

            $insert = $sql->insert('Tele_CallerDetails');
            $newData = array(
                'CallSid' => $callSid,
                'ParentCallSid' => $parentCallSid,
                'DateCreated' => $dateCreated,
                'DateUpdated' => $dateUpdated,
                'Accountsid' => $accountSid,
                'ToNum' => $to,
                'FromNum' => $from,
                'PhoneNumberSid' => $phoneNumberSid,
                'Status' => $status,
                'StartTime' => $startTime,
                'EndTime' => $endTime,
                'Duration' => $duration,
                'Price' => $price,
                'Direction' => $direction,
                'AnsweredBy' => $answeredBy,
                'ForwardedFrom' => $forwardedFrom,
                'RecordingUrl' => $recordingUrl,
                'CallerName' => $callerName,
                'ModifiedDate'=>date('Y-m-d H:i:s'),
                'LastUpdate'=>0
            );
            $insert->values($newData);
            $statement = $sql->getSqlStringForSqlObject($insert);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $dir = "public/uploads/crm/call/";
            if(!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $myfile = 'public/uploads/crm/call/getcall999.phtml';
            if(!file_exists($myfile)) {
                $myfile = fopen($myfile, "w");
            }
//                file_put_contents($filePath, $content);
            fwrite($myfile,print_r($pParams));
            fclose($myfile);

        }
        $result = "";
        $this->_view->setTerminal(true);
        $response = $this->getResponse()->setContent($result)->setStatusCode(200);
        return $response;

    }


    public function cronIncomingCallStatusAction()
    {

        //$this->layout("layout/layout");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result = "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {
                $response = $this->getResponse();
                $cDate=date('Y-m-d H:i:s', strtotime('-3 minutes'));

                $select = $sql->select();
                $select->from('Tele_CallerDetails')
                    ->columns(array('CallSid'));
                $select->where(array('Direction' => 'inbound', 'DeleteFlag' => 0,'LastUpdate'=>0));
                $select->where("ModifiedDate < '$cDate'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $list = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach ($list as $rl) {
                    $callSid = $rl['CallSid'];

                    if(isset($callSid) && $callSid!="") {

                        $returnString = $this->exotel->getCallDetails($callSid);
                        $data_xml = simplexml_load_string($returnString);

                        $pParams = $data_xml->Call;


                        $recordingUrl = $this->bsf->isNullCheck($pParams->RecordingUrl, 'string');
                        $parentCallSid = $this->bsf->isNullCheck($pParams->ParentCallSid, 'string');
                        $accountSid = $this->bsf->isNullCheck($pParams->AccountSid, 'string');
                        $to = $this->bsf->isNullCheck($pParams->To, 'string');
                        $from = $this->bsf->isNullCheck($pParams->From, 'string');
                        $phoneNumberSid = $this->bsf->isNullCheck($pParams->PhoneNumberSid, 'string');
                        $status = $this->bsf->isNullCheck($pParams->Status, 'string');
                        $duration = $this->bsf->isNullCheck($pParams->Duration, 'string');
                        $price = $this->bsf->isNullCheck($pParams->Price, 'string');
                        $direction = $this->bsf->isNullCheck($pParams->Direction, 'string');
                        $answeredBy = $this->bsf->isNullCheck($pParams->AnsweredBy, 'string');
                        $forwardedFrom = $this->bsf->isNullCheck($pParams->ForwardedFrom, 'string');
                        $callerName = $this->bsf->isNullCheck($pParams->CallerName, 'string');

                        $dateCreated = date('Y-m-d H:i:s');
                        if (strtotime($pParams->DateCreated) != false) {
                            $dateCreated = date('Y-m-d H:i:s', strtotime($pParams->DateCreated));
                        }

                        $dateUpdated = date('Y-m-d H:i:s');
                        if (strtotime($pParams->DateUpdated) != false) {
                            $dateUpdated = date('Y-m-d H:i:s', strtotime($pParams->DateUpdated));
                        }

                        $startTime = date('Y-m-d H:i:s');
                        if (strtotime($pParams->StartTime) != false) {
                            $startTime = date('Y-m-d H:i:s', strtotime($pParams->StartTime));
                        }

                        $endTime = date('Y-m-d H:i:s');
                        if (strtotime($pParams->EndTime) != false) {
                            $endTime = date('Y-m-d H:i:s', strtotime($pParams->EndTime));
                        }

                        $update = $sql->update();
                        $update->table('Tele_CallerDetails');
                        $update->set(array(
                            'ParentCallSid' => $parentCallSid,
                            'DateCreated' => $dateCreated,
                            'DateUpdated' => $dateUpdated,
                            'Accountsid' => $accountSid,
                            'ToNum' => $to,
                            'FromNum' => $from,
                            'PhoneNumberSid' => $phoneNumberSid,
                            'Status' => $status,
                            'StartTime' => $startTime,
                            'EndTime' => $endTime,
                            'Duration' => $duration,
                            'Price' => $price,
                            'Direction' => $direction,
                            'AnsweredBy' => $answeredBy,
                            'ForwardedFrom' => $forwardedFrom,
                            'RecordingUrl' => $recordingUrl,
                            'CallerName' => $callerName,
                            'ModifiedDate'=>date('Y-m-d H:i:s'),
                            'LastUpdate'=>1
                        ));
                        $update->where(array('CallSid' => $callSid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                }

                $this->_view->setTerminal(true);
                $response->setContent("success");
                return $response;
            }
        }
    }



    public function dialWhomNumAction()
    {

        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        $postParams = $request->getQuery();
        $From = $postParams['From'];
        $CallSid = $postParams['CallSid'];
        $To = $postParams['To'];
        $tList="";
        if(isset($From) && $From!="") {
            $CallFrom = (strlen($From) > 10) ? substr($From, -10) : $From;


            $select = $sql->select();
            $select->from(array('a' => 'Crm_Leads'))
                ->join(array("b" => "WF_Users"), "a.ExecutiveId=b.UserId", array('Mobile','CallBusy','TeleCalling'), $select::JOIN_LEFT)
                ->columns(array('ExecutiveId'))
                ->where(array('a.Mobile' => $CallFrom, 'a.DeleteFlag' => 0,'b.DeleteFlag'=>0,'b.Lock'=>0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $lex = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if($lex !="") {
                if(intval($lex['ExecutiveId']) !=0) {
                    if(intval($lex['CallBusy']) ==0 && intval($lex['TeleCalling']) ==1) {
                        $tList= '0'.$lex['Mobile'];
                    } else {
                        $status="userbusy";
                        if(intval($lex['TeleCalling'])==0) {
                            $status="offline";
                        }
                        $insert = $sql->insert('Tele_IncomingCallTrack');
                        $newData = array(
                            'CallSid' => $CallSid,
                            'CurrentDate' => date('Y-m-d H:i:s'),
                            'CallTo' => $To,
                            'CallFrom' => $From,
                            'DialWhomNumber' => '0'.$lex['Mobile'],
                            'Status'=>$status,
                            'ModifiedDate'=>date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                } else {
                    $select = $sql->select();
                    $select->from(array('a' => 'Tele_Leads'))
                        ->join(array("b" => "WF_Users"), "a.TeleCaller=b.UserId", array('Mobile','CallBusy','TeleCalling'), $select::JOIN_LEFT)
                        ->columns(array('TeleCaller'))
                        ->where(array('a.Mobile' => $CallFrom, 'a.DeleteFlag' => 0,'a.ConvertLead'=>1,'b.DeleteFlag'=>0,'b.Lock'=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $tEx = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($tEx !="" && intval($tEx['TeleCaller'])!=0) {
                        if(intval($tEx['CallBusy']) ==0 && intval($tEx['TeleCalling']) ==1) {
                            $tList= '0'.$tEx['Mobile'];
                        } else {
                            $status="userbusy";
                            if(intval($tEx['TeleCalling'])==0) {
                                $status="offline";
                            }
                            $insert = $sql->insert('Tele_IncomingCallTrack');
                            $newData = array(
                                'CallSid' => $CallSid,
                                'CurrentDate' => date('Y-m-d H:i:s'),
                                'CallTo' => $To,
                                'CallFrom' => $From,
                                'DialWhomNumber' => '0'.$tEx['Mobile'],
                                'Status'=>$status,
                                'ModifiedDate'=>date('Y-m-d H:i:s')
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                    } else {

                        $PositionTypeId = array(3);
                        $sub = $sql->select();
                        $sub->from(array('a' => 'WF_PositionMaster'))
                            ->join(array("b" => "WF_PositionType"), "a.PositionTypeId=b.PositionTypeId", array(), $sub::JOIN_LEFT)
                            ->columns(array('PositionId'))
                            ->where(array("b.PositionTypeId" => $PositionTypeId));

                        $select = $sql->select();
                        $select->from('WF_Users')
                            ->columns(array('Mobile', 'UserId'))
                            ->where->expression("PositionId IN ?", array($sub));
                        $select->where(array('TeleCalling' => 1, 'callBusy' => 0, 'DeleteFlag' => 0, 'Lock' => 0));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $teleCallerList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $tList="";
                        $numItems=count($teleCallerList);
                        if($numItems>0) {
                            $i=0;
                            foreach($teleCallerList as $tc) {
                                if(++$i === $numItems) {
                                    $tList .= "0" . $tc['Mobile'];
                                } else {
                                    $tList .= "0" . $tc['Mobile'].',';
                                }
                            }
                        } else {
                            $select = $sql->select();
                            $select->from('WF_Users')
                                ->columns(array('Mobile', 'UserId','TeleCalling','callBusy'))
                                ->where->expression("PositionId IN ?", array($sub));
                            $select->where(array('DeleteFlag' => 0, 'Lock' => 0));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $allTeleCallerList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($allTeleCallerList as $tc) {
                                $status="userbusy";
                                if(intval($tc['TeleCalling'])==0) {
                                    $status="offline";
                                }
                                $insert = $sql->insert('Tele_IncomingCallTrack');
                                $newData = array(
                                    'CallSid' => $CallSid,
                                    'CurrentDate' => date('Y-m-d H:i:s'),
                                    'CallTo' => $To,
                                    'CallFrom' => $From,
                                    'DialWhomNumber' => '0'.$tc['Mobile'],
                                    'Status'=>$status,
                                    'ModifiedDate'=>date('Y-m-d H:i:s')
                                );
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                    }
                }
            } else {
                $select = $sql->select();
                $select->from(array('a' => 'Tele_Leads'))
                    ->join(array("b" => "WF_Users"), "a.TeleCaller=b.UserId", array('Mobile','CallBusy','TeleCalling'), $select::JOIN_LEFT)
                    ->columns(array('LeadId', 'LeadName','TeleCaller'))
                    ->where(array('a.Mobile' => $CallFrom, 'a.DeleteFlag' => 0,'a.ConvertLead'=>0,'b.DeleteFlag'=>0,'b.Lock'=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $tEx = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($tEx !="" && intval($tEx['TeleCaller'])!=0) {
                    if(intval($tEx['CallBusy']) ==0 && intval($tEx['TeleCalling']) ==1) {
                        $tList= '0'.$tEx['Mobile'];
                    } else {
                        $status="userbusy";
                        if(intval($tEx['TeleCalling'])==0) {
                            $status="offline";
                        }
                        $insert = $sql->insert('Tele_IncomingCallTrack');
                        $newData = array(
                            'CallSid' => $CallSid,
                            'CurrentDate' => date('Y-m-d H:i:s'),
                            'CallTo' => $To,
                            'CallFrom' => $From,
                            'DialWhomNumber' => '0'.$tEx['Mobile'],
                            'Status'=>$status,
                            'ModifiedDate'=>date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                } else {

                    $PositionTypeId = array(3);
                    $sub = $sql->select();
                    $sub->from(array('a' => 'WF_PositionMaster'))
                        ->join(array("b" => "WF_PositionType"), "a.PositionTypeId=b.PositionTypeId", array(), $sub::JOIN_LEFT)
                        ->columns(array('PositionId'))
                        ->where(array("b.PositionTypeId" => $PositionTypeId));

                    $select = $sql->select();
                    $select->from('WF_Users')
                        ->columns(array('Mobile', 'UserId'))
                        ->where->expression("PositionId IN ?", array($sub));
                    $select->where(array('TeleCalling' => 1, 'callBusy' => 0, 'DeleteFlag' => 0, 'Lock' => 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $teleCallerList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $tList="";
                    $numItems=count($teleCallerList);
                    if($numItems>0) {
                        $i=0;
                        foreach($teleCallerList as $tc) {
                            if(++$i === $numItems) {
                                $tList .= "0" . $tc['Mobile'];
                            } else {
                                $tList .= "0" . $tc['Mobile'].',';
                            }
                        }

                    } else {
                        $select = $sql->select();
                        $select->from('WF_Users')
                            ->columns(array('Mobile', 'UserId','TeleCalling','callBusy'))
                            ->where->expression("PositionId IN ?", array($sub));
                        $select->where(array('DeleteFlag' => 0, 'Lock' => 0));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $allTeleCallerList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($allTeleCallerList as $tc) {
                            $status="userbusy";
                            if(intval($tc['TeleCalling'])==0) {
                                $status="offline";
                            }
                            $insert = $sql->insert('Tele_IncomingCallTrack');
                            $newData = array(
                                'CallSid' => $CallSid,
                                'CurrentDate' => date('Y-m-d H:i:s'),
                                'CallTo' => $To,
                                'CallFrom' => $From,
                                'DialWhomNumber' => '0'.$tc['Mobile'],
                                'Status'=>$status,
                                'ModifiedDate'=>date('Y-m-d H:i:s')
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                }

            }

        }
        $result = $tList;
        $this->_view->setTerminal(true);
        $response = $this->getResponse()->setContent($result)->setStatusCode(200);
        return $response;

    }

}