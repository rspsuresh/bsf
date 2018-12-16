<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Telecaller\Controller;

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

use PHPExcel;
use PHPExcel_IOFactory;

class IndexController extends AbstractActionController
{
    public function __construct()	{
        $this->exotel = new \Exotel();
        $this->bsf = new \BuildsuperfastClass();
        $this->auth = new AuthenticationService();
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

        // csrf validation
        $request = $this->getRequest();
        $response = $this->getResponse();
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

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
                    $postData = $request->getPost();
                    $this->_view->setTerminal(true);
                    $weekData=array();
                    if($postData['mode']=="project") {
                        $camp = json_decode($postData['CampaignVal']);

                        $sub = $sql->select();
                        $sub->from("Crm_CampaignProjectTrans")
                            ->columns(array("ProjectId"));
                        $sub->where(array("CampaignId"=>$camp));

                        $select = $sql->select();
                        $select->from(array("g"=>$sub))
                            ->columns(array('ProjectId'));
                        $select->group(new expression("g.ProjectId"));
                        $totalProjectQuery = $sql->getSqlStringForSqlObject($select);

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ProjectMaster'))
                            ->columns(array('ProjectId','ProjectName'));
                        $select->where(array("a.DeleteFlag"=>0));
                        $select->where('a.ProjectId IN (' .$totalProjectQuery . ')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $weekData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    } else {
                        $camp = json_decode($postData['ProjectVal']);

                        $sub = $sql->select();
                        $sub->from("Crm_CampaignProjectTrans")
                            ->columns(array("CampaignId"));
                        $sub->where(array("ProjectId"=>$camp));

                        $select = $sql->select();
                        $select->from(array("g"=>$sub))
                            ->columns(array('CampaignId'));
                        $select->group(new expression("g.CampaignId"));
                        $totalCampaignQuery = $sql->getSqlStringForSqlObject($select);

                        $select = $sql->select();
                        $select->from("Crm_CampaignRegister")
                            ->columns(array("Id"=>new expression("CampaignId"),"Name"=>new expression("CampaignName + ' - ' + SourceName")));
                        $select->where(array("DeleteFlag"=>0));
                        $select->where('CampaignId IN (' .$totalCampaignQuery . ')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $weekData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    }
                    //Write your Ajax post code here
                    $connection->commit();
                    $result =  "Success";

                    $response->setStatusCode(200);
                    $response->setContent(json_encode($weekData));
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

                $postData = $request->getPost();

                $projectArr=$postData['project'];
                $campaignArr=$postData['campaign'];
                $this->_view->projectArr=$projectArr;
                $this->_view->campaignArr=$campaignArr;
                $executive=$this->bsf->isNullCheck($postData['executive'],'number');
                $fromDate=$postData['fromDate'];
                $toDate=$postData['toDate'];
                $this->_view->filterCheck=$postData['filterCheck'];

            }
            $userId =$this->auth->getIdentity()->UserId;
            $superiors = $viewRenderer->commonHelper()->masterSuperior($userId,$dbAdapter);

            if(isset($executive) && $executive!=0) {
                $arrExecutiveIds=array();
                array_push($arrExecutiveIds,$executive);
                $this->_view->executive=$executive;
            } else {
                $arrExecutiveIds=$superiors;
            }


            if(isset($fromDate) && $fromDate!="" && strtotime($fromDate)!=false){
                $fromDate= strtotime($fromDate);
            } else {
                $fromDate=strtotime(date('d-m-Y') .' -1 month');

            }
            if(isset($toDate) && $toDate!="" && strtotime($toDate)!=false){
                $toDate= strtotime($toDate);
            } else {
                $toDate= strtotime(date('d-m-Y'));
            }
            if($toDate>=$fromDate) {
                $fromDate=date('Y-m-d',$fromDate);
                $this->_view->fromDate=date('d-m-Y',strtotime($fromDate));
                $toDate=date('Y-m-d',$toDate)." 23:59:59";;
                $this->_view->toDate=date('d-m-Y',strtotime($toDate));
            } else {
                $fromDate=date('Y-m-d',$fromDate);
                $this->_view->fromDate=date('d-m-Y',strtotime($fromDate));
                $fromDate=date('Y-m-d',$fromDate);
                $this->_view->toDate=date('d-m-Y',strtotime($fromDate));
            }

            $subQuery = $sql->select();
            $subQuery->from(array('a'=> 'Tele_Leads'))
                ->join(array('b' => 'Tele_LeadProjects'), 'a.LeadId=b.LeadId', array(), $subQuery::JOIN_LEFT)
                ->join(array('c' => 'Tele_LeadFollowup'), new Expression("a.LeadId=c.LeadId AND c.Completed = 0"), array('CallTypeId','FollowupDate'), $subQuery::JOIN_LEFT)
                ->columns(array('*'))
                ->where('a.TeleCaller IN (' . implode(',', $arrExecutiveIds) . ')');
            if(isset($projectArr) && count($projectArr)>0){
                $subQuery->where(array('b.ProjectId' => $projectArr));
            }
            if(isset($campaignArr) && count($campaignArr)>0){
                $subQuery->where(array('a.campaignId' => $campaignArr));
            }


            //No Of Leads
            $select = $sql->select();
            $select->from(array("g"=>$subQuery))
                ->columns(array('LeadId'))
                ->where("isnull(g.CallTypeId,0) != 3");
            $select->group(new expression("g.LeadId"));
            $totalLeadsQuery = $statement = $sql->getSqlStringForSqlObject($select);
            $totalLeads = $dbAdapter->query($totalLeadsQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $data['NoOfLeads'] = count($totalLeads);


            //Dropped Leads
            $select = $sql->select();
            $select->from(array("g"=>$subQuery))
                ->columns(array('LeadId'))
                ->where("isnull(g.CallTypeId,0) = 3");
            $select->group(new expression("g.LeadId"));
            $select->where("g.FollowupDate<= '$toDate' and g.FollowupDate>= '$fromDate'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $tdropLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->tdropLeads=count($tdropLeads);
            $asonDate=date('Y-m-d')." 23:59:59";


            //Pending Followups
            $select = $sql->select();
            $select->from(array('a' => 'Tele_LeadFollowup'))
                ->columns(array('*'))
                ->join(array('b' => 'Tele_Leads'), 'b.LeadId=a.LeadId', array('LeadId','LeadName','ConvertLead'), $select::JOIN_LEFT)
                //->join(array('c' => 'Crm_CallTypeMaster'), 'c.CallTypeId=a.NextFollowUpTypeId', array('CallTypeDec' => 'Description'), $select::JOIN_LEFT)
                //->join(array('d' => 'Crm_CallTypeMaster'), 'd.CallTypeId=a.CallTypeId', array('PrevCallTypeDec' => 'Description'), $select::JOIN_LEFT)
                ->join(array('e' => 'WF_Users'), 'b.TeleCaller=e.UserId', array('ExecuName' => 'EmployeeName', 'Projects' => new Expression("''")), $select::JOIN_LEFT)
                ->join(array('f' => 'Crm_NatureMaster'), 'f.NatureId=a.NatureId', array('PrevCallNatureDec' => 'Description'), $select::JOIN_LEFT)
                ->where(array('a.DeleteFlag' => 0, 'a.Completed' => 0,'b.ConvertLead'=>0))
                ->where("a.NextCallDate <= '".$asonDate."'" );
            $select->where('a.LeadId IN (' .$totalLeadsQuery . ')');
            //$subQuery->where("a.FollowupDate<= '$toDate' and a.FollowupDate>= '$fromDate'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $pendingFollowup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->pendingFollowup = $pendingFollowup;
            $data['UnFollowedLeadCnt']=count($pendingFollowup);

            if((isset($projectArr) && count($projectArr)>0) && (isset($campaignArr) && count($campaignArr)>0)){

                $select = $sql->select();
                $select->from(array('a' => 'Crm_CampaignRegister'))
                    ->columns(array('campMobile'=>new Expression("distinct(a.CampaignCallNo)")))
                    ->join(array('b' => 'Crm_CampaignProjectTrans'), 'b.CampaignId=a.CampaignId', array(), $select::JOIN_LEFT);
                $select->where(array("b.campaignId"=>$campaignArr,"b.ProjectId"=>$projectArr,"a.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $cDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            } else if(isset($projectArr) && count($projectArr)>0){

                $select = $sql->select();
                $select->from(array('a' => 'Crm_CampaignRegister'))
                    ->columns(array('campMobile'=>new Expression("distinct(a.CampaignCallNo)")))
                    ->join(array('b' => 'Crm_CampaignProjectTrans'), 'b.CampaignId=a.CampaignId', array(), $select::JOIN_LEFT);
                $select->where(array("b.ProjectId"=>$projectArr,"a.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $cDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            } else if(isset($campaignArr) && count($campaignArr)>0){
                $select = $sql->select();
                $select->from(array('a' => 'Crm_CampaignRegister'))
                    ->columns(array('campMobile'=>new Expression("distinct(a.CampaignCallNo)")));
                $select->where(array("a.CampaignId"=>$campaignArr,"a.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $cDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

            //total No of Calls
            $subQuery = $sql->select();
            $subQuery->from(array('a'=> 'Wf_Users'))
                ->columns(array('Mobile',"UserId","EmployeeName"))
                ->where('a.UserId IN (' . implode(',', $superiors) . ')')
                ->where(array('a.DeleteFlag' => 0));
            $statement = $sql->getSqlStringForSqlObject($subQuery);
            $allMobile = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->executiveList = $allMobile;

            $mobileArray=array();
            if(count($allMobile)>0) {
                foreach($allMobile as $all) {
					if($all['Mobile']!="") {
                    array_push($mobileArray,'0'.$all['Mobile']);
					}
                }
            }

            $campArray=array();
            if(isset($cDetails) && count($cDetails)>0) {
                foreach($cDetails as $al) {
                    array_push($campArray,'0'.$al['campMobile']);
                }
            }

            if(count($campArray)>0) {
                $subQuery = $sql->select();
                $subQuery->from(array('a' => 'Tele_IncomingCallTrack'))
                    ->columns(array("CallersId" => new Expression("distinct(a.CallSid)")))
                    ->where(array("CallTo" => $campArray));
                $totalQy = $sql->getSqlStringForSqlObject($subQuery);
            }

            $select = $sql->select();
            $select->from(array("a"=>"Tele_CallerDetails"))
                ->columns(array('CallSid'))
                ->where(array("a.Status"=>"completed"));
				if(count($mobileArray)>0) {
					$select->where('a.FromNum IN (' . implode(',',$mobileArray) . ') OR a.ToNum IN (' . implode(',',$mobileArray) . ')');
				}
			$select->where("a.StartTime<= '$toDate' and a.StartTime>= '$fromDate'");
            if(count($campArray)>0) {
                $select->where('a.CallSid IN (' .$totalQy . ')');
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $callDone = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->tCallDone = count($callDone);

            //Qualified Leads
            $select = $sql->select();
            $select->from(array("a" => 'Tele_Leads'))
                ->join(array('b' => 'Tele_LeadProjects'), 'a.LeadId=b.LeadId', array(), $subQuery::JOIN_LEFT)
                ->columns(array('LeadId'));
            $select->where(array("a.TeleCaller" => $arrExecutiveIds,"a.ConvertLead"=>1));
            $select->where("a.TransferDate<= '$toDate' and a.TransferDate>= '$fromDate' and a.DeleteFlag=0");
            $select->group(new expression("a.LeadId"));
            if(isset($projectArr) && count($projectArr)>0){
                $select->where(array('b.ProjectId' => $projectArr));
            }
            if(isset($campaignArr) && count($campaignArr)>0){
                $select->where(array('a.campaignId' => $campaignArr));
            }
            $statement = $sql->getSqlStringForSqlObject($select);
            $QualifiedLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->tQualifiedLeads=count($QualifiedLeads);

            //Pending Leads
            $select = $sql->select();
            $select->from(array("a" => 'Tele_Leads'))
               // ->join(array('b' => 'Tele_LeadProjects'), 'a.LeadId=b.LeadId', array(), $select::JOIN_INNER)
                ->columns(array('LeadId'));
            $select->where(array("a.ConvertLead"=>0));
            $select->where("a.LeadDate<= '$toDate' and a.LeadDate>= '$fromDate' and a.DeleteFlag=0");
//            $select->group(new expression("a.LeadId"));
//            if(isset($projectArr) && count($projectArr)>0){
//                $select->where(array('b.ProjectId' => $projectArr));
//            }
//            if(isset($campaignArr) && count($campaignArr)>0){
//                $select->where(array('a.campaignId' => $campaignArr));
//            }
            $select->where('a.LeadId IN (' .$totalLeadsQuery . ')');

            $statement = $sql->getSqlStringForSqlObject($select);
            $QualifiedLds = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->tPendingLeads=count($QualifiedLds);

            //Dropped Calls
//                    $subQuery = $sql->select();
//                    $subQuery->from(array('a'=> 'Tele_Contacts'))
//                        ->join(array('b' => 'Tele_Leads'), 'a.CallSid=b.CallSid', array(), $subQuery::JOIN_INNER)
//                        ->join(array('c' => 'Tele_CallerDetails'), 'b.CallSid=c.CallSid', array(), $subQuery::JOIN_INNER)
//                        ->columns(array('Mobile'))
//                        ->where(array("c.Direction"=>"outbound-api","c.FromNum"=>$mobileArray,"a.DeleteFlag"=>0,"a.AttendFlag"=>1));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $dropLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $this->_view->tdropLeads=count($dropLeads);

            //campaign
            $select = $sql->select();
            $select->from("Crm_CampaignRegister")
                ->columns(array("Id"=>new expression("CampaignId"),"Name"=>new expression("CampaignName + ' - ' + SourceName")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->campaign = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //selecting values for LeadProjects
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId','ProjectName'))
                ->order('ProjectId desc');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultsLeadProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->data = $data;


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function leadAction(){
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
                $sql     = new Sql($dbAdapter);
                $postParams = $request->getPost();

                if($postParams['mode']=='Mobile'){

                    $select = $sql->select();
                    $select->from('Tele_Leads')
                        ->columns(array('Mobile','LeadName'))
                        ->where(array("Mobile"=>$postParams['mobile']));
                    if($postParams['LeadId']!=0) {
                        $leadId= $postParams['LeadId'];
                        $select->where('LeadId <>'.$leadId);
                    }
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resultLeads= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                } else if($postParams['mode']=='project') {

                    //selecting values for LeadProjects
                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_CampaignProjectTrans'))
                        ->join(array('b' => 'Proj_ProjectMaster'), 'a.ProjectId=b.ProjectId', array('ProjectId','ProjectName'), $select::JOIN_LEFT)
                        ->columns(array('TransId'))
                        ->where(array("a.CampaignId"=>$postParams['CampaignVal']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resultLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } else {
                    $cost = $postParams['CostFrom'];
                    $select = $sql->select();
                    $select->from(array('a' =>'Crm_CostPreferenceMaster'))
                        ->columns(array('CostPreferenceTo'))
                        ->where(array("CostPreferenceTo >= $cost"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resultLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($resultLeads));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams = $request->getPost();
                try {
                    $leadId = $this->bsf->isNullCheck($postParams['LeadId'],'number');
                    $leadName = $this->bsf->isNullCheck($postParams['LeadName'],'string');
                    $leadType = $this->bsf->isNullCheck($postParams['LeadType'],'number');
                    $leaserType = $this->bsf->isNullCheck($postParams['LeaserType'],'string');
                    $countryCode = $this->bsf->isNullCheck($postParams['CountryCode'],'number');
                    $mobileNo = $this->bsf->isNullCheck($postParams['MobileNo'],'number');
                    $emailAdd = $this->bsf->isNullCheck($postParams['Email'],'string');
                    $costPreferenceFrom = $this->bsf->isNullCheck($postParams['costPreferenceFrom'],'number');
                    $costPreferenceTo = $this->bsf->isNullCheck($postParams['costPreferenceTo'],'number');
                    $TeleCaller = $this->bsf->isNullCheck($postParams['TeleCaller'],'number');
                    $Remarks = $this->bsf->isNullCheck($postParams['Remarks'],'string');
                    $CallSid = $this->bsf->isNullCheck($postParams['CallSid'],'string');
                    $Project = $postParams['Project'];
                    $Campaign = $this->bsf->isNullCheck($postParams['Campaign'],'number');
                    $nextFollowupType=$this->bsf->isNullCheck($postParams['NextFollowupType'], 'number');
                    $cityId=$postParams['PreCityId'];

                    $nextFollowupDate=date('Y-m-d H:i:s');
                    if(strtotime(str_replace('/','-',$postParams['NextFollowupDate']))!=false) {
                        $nextFollowupDate=$postParams['NextFollowupDate'];
                    }

                    if($leadId==0) {
                        //More Details
                        $insert = $sql->insert('Tele_Leads');
                        $newData = array(
                            'LeadName' => $leadName,
                            'LeadType' => $leadType,
                            'LeaserType' => $leaserType,
                            'CountryCode' => $countryCode,
                            'Mobile' => $mobileNo,
                            'Email' => $emailAdd,
                            'CostPreferenceFrom' => $costPreferenceFrom,
                            'CostPreferenceTo' => $costPreferenceTo,
                            'UserId' => $this->auth->getIdentity()->UserId,
                            'TeleCaller' => $TeleCaller,
                            'LeadDate' => date('m-d-Y H:i:s'),
                            'Remarks'=>$Remarks,
                            'CampaignId'=>$Campaign,
                            'CallSid'=>$CallSid

                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $leadId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        if($leadId!=0) {
                            $select = $sql->insert('Tele_LeadFollowup');
                            $newData = array(
                                'FollowUpDate' => date('m-d-Y H:i:s'),
                                'LeadId' => $leadId,
                                'ExecutiveId' => $TeleCaller,
                                'Remarks' => $Remarks,
                                'NextCallDate' => $nextFollowupDate,
                                'StatusId' => 3,
                                'NatureId' => 1,
                                'CallTypeId' => 4,
                                'NextFollowUpTypeId' => $nextFollowupType,
                                'UserId' => $userId,
                                'ModifiedDate' => date('Y-m-d H:i:s')
                            );
                            $select->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                    } else {
                        $delete = $sql->delete();
                        $delete->from('Tele_City')
                            ->where(array('LeadId' => $leadId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $deleteCity = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Tele_LeadProjects')
                            ->where(array('LeadId' => $leadId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('Tele_Leads');
                        $update->set(array(
                            'LeadType' => $leadType,
                            'LeadName' => $leadName,
                            'LeaserType'  => $leaserType,
                            'CountryCode' =>$countryCode,
                            'Mobile'  =>$mobileNo,
                            'Email'=>$emailAdd,
                            'CostPreferenceFrom'=>$costPreferenceFrom,
                            'CostPreferenceTo'=>$costPreferenceTo,
                            'TeleCaller' => $TeleCaller,
                            'Remarks'=>$Remarks,
                            'CampaignId'=>$Campaign
                        ));
                        $update->where(array('LeadId'=>$leadId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $resultUpdate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    if(count($Project)>0) {
                        foreach($Project as $Pr) {
                            $insert = $sql->insert('Tele_LeadProjects');
                            $newData = array(
                                'ProjectId' => $Pr,
                                'LeadId' => $leadId,
                                'ModifiedDate' => date('Y-m-d H:i:s')
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                    }

                    if(count($cityId)>0) {
                        foreach ($cityId as $value) {
                            if (!is_numeric($value) && trim($value) != '') {
                                $otherName = trim($value);
                                // add new other cost
                                $insert = $sql->insert('WF_CityMaster')
                                    ->values(array(
                                        'CityName' => $otherName,
                                        'StateId' => 1,
                                        'CountryId' => 2
                                    ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $value = $dbAdapter->getDriver()->getLastGeneratedValue();

                            }

                            $insert = $sql->insert('Tele_City');
                            $newData = array(
                                'LeadId' => $leadId,
                                'CityId' => $value
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                } catch (PDOException $e ) {
                    $connection->rollback();
                }
                $this->redirect()->toRoute('telecaller/default', array('controller' => 'index', 'action' => 'lead-register'));

            } else {
                try {
                    $leadId = $this->bsf->isNullCheck($this->params()->fromRoute('leadId'), 'number' );
                    $callSid = $this->bsf->isNullCheck($this->params()->fromRoute('callSid'), 'string' );

                    $this->_view->callSid=$callSid;

                    if($leadId!=0) {
                        $select = $sql->select();
                        $select->from('Tele_Leads')
                            ->columns(array('*'));
                        $select->where(array('LeadId'=>$leadId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resultEditVal= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->resultEditVal=$resultEditVal;

                        $select = $sql->select();
                        $select->from('Tele_City')
                            ->columns(array('*'));
                        $select->where(array('LeadId'=>$leadId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->cityVal= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from('Tele_LeadProjects')
                            ->columns(array('*'));
                        $select->where(array('LeadId'=>$leadId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->projectVal= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $dFromValue=0;
                        if (isset($resultEditVal)) {
                            $dFromValue = $resultEditVal["CostPreferenceFrom"];
                        }

                        $select = $sql->select();
                        $select->from('Crm_CostPreferenceMaster')
                            ->columns(array('CostPreferenceId','CostPreferenceFrom','CostPreferenceTo'))
                            ->where(array("CostPreferenceTo >= $dFromValue"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->resultsCostT  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    } else {

                        if($callSid!="") {
                            $select = $sql->select();
                            $select->from('Tele_CallerDetails')
                                ->columns(array('*'));
                            $select->where(array('CallSid'=>$callSid));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $callDetails= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if($callDetails!="") {
                                if($callDetails['Direction']!="inbound") {
                                    $agent =  $this->bsf->isNullCheck($callDetails['ToNum'],'string');
                                    $teleCaller =  $this->bsf->isNullCheck($callDetails['FromNum'],'string');

                                    $select = $sql->select();
                                    $select->from(array('a' => 'Tele_IncomingCallTrack'))
                                        ->columns(array('CallTo','CallFrom','CampaignId'))
                                        ->where(array('a.CallFrom' => $agent,'a.DialWhomNumber'=>$teleCaller));
                                    $select->order('a.TransId Desc');
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $ex = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if($ex!="") {
                                        $select = $sql->select();
                                        $select->from("Crm_CampaignRegister")
                                            ->columns(array("Id"=>new expression("CampaignId"),"Name"=>new expression("CampaignName + ' - ' + SourceName")));
                                        $select->where(array("CampaignId"=>$ex['CampaignId'],"DeleteFlag"=>0));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $selectCampaign = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                        $this->_view->selectCampaign=$selectCampaign;

                                        if($selectCampaign!="") {
                                            $select = $sql->select();
                                            $select->from("Crm_CampaignProjectTrans")
                                                ->columns(array("ProjectId"));
                                            $select->where(array("CampaignId"=>$selectCampaign['Id']));
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $sProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            $selectProjects=array();
                                            foreach($sProjects as $sP) {
                                                array_push($selectProjects,$sP['ProjectId']);
                                            }
                                            $this->_view->selectProjects=$selectProjects;
                                        }
                                    }

                                } else {
                                    $teleCaller= (strlen($this->auth->getIdentity()->Mobile)>10) ? substr($this->auth->getIdentity()->Mobile, -10) : $this->auth->getIdentity()->Mobile;
                                    $teleCaller='0'.$teleCaller;
                                    $select = $sql->select();
                                    $select->from(array('a' => 'Tele_IncomingCallTrack'))
                                        ->columns(array('CallTo','CallFrom','CampaignId'))
                                        ->where(array('CallSid' => $callSid,'DialWhomNumber'=>$teleCaller));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $ex = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if($ex!="") {
                                        $agent=$ex['CallFrom'];

                                        $select = $sql->select();
                                        $select->from("Crm_CampaignRegister")
                                            ->columns(array("Id"=>new expression("CampaignId"),"Name"=>new expression("CampaignName + ' - ' + SourceName")));
                                        $select->where(array("CampaignId"=>$ex['CampaignId'],"DeleteFlag"=>0));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $selectCampaign = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                        $this->_view->selectCampaign=$selectCampaign;

                                        if($selectCampaign!="") {
                                            $select = $sql->select();
                                            $select->from("Crm_CampaignProjectTrans")
                                                ->columns(array("ProjectId"));
                                            $select->where(array("CampaignId"=>$selectCampaign['Id']));
                                            $statement = $sql->getSqlStringForSqlObject($select);
                                            $sProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                            $selectProjects=array();
                                            foreach($sProjects as $sP) {
                                                array_push($selectProjects,$sP['ProjectId']);
                                            }
                                            $this->_view->selectProjects=$selectProjects;
                                        }
                                    }

                                }
                                if(isset($agent)) {
                                    $this->_view->to = (strlen($agent)>10) ? substr($agent, -10) : $agent;

                                }
                                $from= (strlen($teleCaller)>10) ? substr($teleCaller, -10) : $teleCaller;

                                $select = $sql->select();
                                $select->from('WF_Users')
                                    ->columns(array('UserId'));
                                $select->where(array("Mobile"=>$from,"DeleteFlag"=>0,"lock"=>0));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $this->_view->exeCall = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            }

                        }

                    }

                    $this->_view->cTeleCaller=$this->auth->getIdentity()->UserId;
                    $select = $sql->select();
                    $select->from('Tele_LeadSettings')
                        ->columns(array('*'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $Fields = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if(isset($Fields) && count($Fields)>0) {
                        $reqFields=array();
                        foreach($Fields as $f) {
                            $reqFields[$f['Fields']]= $f['Required'];
                        }
                        $this->_view->reqFields=$reqFields;
                    }

                    //campaign
                    $select = $sql->select();
                    $select->from("Crm_CampaignRegister")
                        ->columns(array("Id"=>new expression("CampaignId"),"Name"=>new expression("CampaignName + ' - ' + SourceName")));
                    $select->where(array("DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->campaign = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //selecting values for LeadProjects
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'))
                        ->order('ProjectId desc');
                    $select->where(array("DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsLeadProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //selecting values for LeadType
                    $select = $sql->select();
                    $select->from('Crm_LeadTypeMaster')
                        ->columns(array('LeadTypeId','LeadTypeName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //selecting values from CostPreference Table
                    $select = $sql->select();
                    $select->from('Crm_CostPreferenceMaster')
                        ->columns(array('CostPreferenceId','CostPreferenceFrom','CostPreferenceTo'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsCost  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //selecting values for City
                    $select = $sql->select();
                    $select->from('WF_CityMaster')
                        ->columns(array('CityId','CityName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsCity  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //selecting values for Telecaller
                    $PositionTypeId=array(3);
                    $sub = $sql->select();
                    $sub->from(array('a'=>'WF_PositionMaster'))
                        ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                        ->columns(array('PositionId'))
                        ->where(array("b.PositionTypeId"=>$PositionTypeId));

                    $select = $sql->select();
                    $select->from('WF_Users')
                        ->columns(array('UserId','EmployeeName'))
                        ->where->expression("PositionId IN ?",array($sub));
                    $select->where(array("DeleteFlag"=>0,'lock'=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsExecutive = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->userId=$userId;
                    $this->_view->leadId=$leadId;
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }


    function _convertXLStoCSV($infile, $outfile)
    {
        $fileType = PHPExcel_IOFactory::identify($infile);
        $objReader = PHPExcel_IOFactory::createReader($fileType);

        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($infile);
        $objPHPExcel->setActiveSheetIndex(0);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
        $objWriter->save($outfile);
    }

    function _validateUploadFile($file)
    {
        $ext = pathinfo($file['file']['name'], PATHINFO_EXTENSION);
        $mime_types = array('application/octet-stream', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/excel');
        $exts = array('csv', 'xls', 'xlsx');
        if (!in_array($file['file']['type'], $mime_types) || !in_array($ext, $exts))
            return false;

        return true;
    }

    public function getLeadFieldDataAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
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
                try {
                    $mode = $this->bsf->isNullCheck($this->params()->fromPost('mode'), 'string' );

                    if($mode=="title") {
                        //Write your Ajax post code here
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();

                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/telecaller/index/leadregister/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/telecaller/index/leadregister/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;
                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                foreach ($xlData as $j => $value) {
                                    if($value!="") {
                                        $data[] = array('Field' => $value);
                                    }
                                }
                            } else {
                                break;
                            }
                            $icount = $icount + 1;
                        }


                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else if($mode=="body") {
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();
                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/telecaller/index/leadregister/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/telecaller/index/leadregister/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;
                        $RType = $postData['arrHeader'];
                        $bValid = true;

                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                if(isset($xlData)) {
                                    foreach ($xlData as $j => $value) {
                                        if (trim($value) != "") {
                                            $bFound = false;
                                            $sField = "";
                                            foreach (json_decode($RType) as $k) {
                                                if (trim($value) == trim($k->efield)) {
                                                    $sField = $k->field;
                                                    $bFound = true;
                                                    break;
                                                }
                                            }
                                            if ($bFound == true) {
                                                if (trim($sField) == "LeadName") {
                                                    $col_1 = intval($j);
                                                }
                                                if (trim($sField) == "LeadDate") {
                                                    $col_2 = intval($j);
                                                }
                                                if (trim($sField) == "TeleCaller") {
                                                    $col_3 = intval($j);
                                                }
                                                if (trim($sField) == "Mobile") {
                                                    $col_4 = intval($j);
                                                }
                                                if (trim($sField) == "EmailAddress") {
                                                    $col_5 = intval($j);
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {

                                $LeadName="";
                                $LeadDate="";
                                $TeleCaller="";
                                $Mobile="";
                                $EmailAddress="";
                                if (isset($col_1) && !is_null($col_1) && trim($col_1)!="" && isset($xlData[$col_1])) {
                                    $LeadName =$this->bsf->isNullCheck(trim($xlData[$col_1]),'string');
                                }
                                if (isset($col_2) && !is_null($col_2) && trim($col_2)!="" && isset($xlData[$col_2])) {
                                    $LeadDate =$this->bsf->isNullCheck(trim($xlData[$col_2]),'string');
                                }
                                if (isset($col_3) && !is_null($col_3) && trim($col_3)!="" && isset($xlData[$col_3])) {
                                    $TeleCaller = $this->bsf->isNullCheck(trim($xlData[$col_3]),'string');
                                }
                                if (isset($col_4) && !is_null($col_4) && trim($col_4)!="" && isset($xlData[$col_4])) {
                                    $Mobile = $this->bsf->isNullCheck(trim($xlData[$col_4]),'string');
                                }
                                if (isset($col_5) && !is_null($col_5) && trim($col_5)!="" && isset($xlData[$col_5])) {
                                    $EmailAddress = $this->bsf->isNullCheck(trim($xlData[$col_5]),'string');
                                }
                                if($LeadName!="" || $LeadDate!="" || $TeleCaller!="" || $Mobile!="" || $EmailAddress!="" ) {
                                    $data[] = array('Valid' => $bValid, 'LeadName' => $LeadName, 'LeadDate' => $LeadDate, 'TeleCaller' => $TeleCaller, 'Mobile' => $Mobile, 'EmailAddress' => $EmailAddress);
                                }
                            }
                            $icount++;
                        }

                        if ($bValid == false) {
                            $data[] = array('Valid' => $bValid);
                        }
                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else {
                        $postData = $request->getPost();
                        $rowCount = $postData['rowCount'];
                        $data = array();
                        for ($i = 0; $i <= $rowCount; $i++) {

                            $leadName = $this->bsf->isNullCheck(trim($postData['excellead_' . $i]), 'string');
                            $leadDate = $this->bsf->isNullCheck(trim($postData['exceldate_' . $i]), 'string');
                            $TeleCaller = $this->bsf->isNullCheck(trim($postData['exceltelecaller_' . $i]), 'string');
                            $mobile = $this->bsf->isNullCheck(trim($postData['excelmobile_' . $i]), 'string');
                            $email = $this->bsf->isNullCheck(trim($postData['excelemail_' . $i]), 'string');
                            if($leadName=="" && $leadDate=="" && $TeleCaller=="" && $mobile == "" && $email == "") {
                                continue;
                            }
                            $error=0;
                            if ($leadName == "") {
                                $leadArray = array($leadName, 1);
                                $dateArray = array($leadDate, 0);
                                $mobileArray = array($mobile, 0);
                                $emailArray = array($email, 0);
                                $TeleCallerArray = array($TeleCaller, 0);
                                $error = 1;
                            } else {
                                $leadArray = array($leadName, 0);
                            }

                            $select = $sql->select();
                            $select->from('Tele_Leads')
                                ->columns(array('LeadId'))
                                ->where(array('Mobile' => $mobile, 'DeleteFlag' => 0));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $checkMobile = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            if ($mobile == "" || count($checkMobile)>0) {
                                $mobileArray = array($mobile, 1);
                                $dateArray = array($leadDate, 0);
                                $emailArray = array($email, 0);
                                $TeleCallerArray = array($TeleCaller, 0);
                                $error = 1;
                            } else {
                                $mobileArray = array($mobile, 0);
                            }
                            if ($email == "") {
                                $emailArray = array($email, 1);
                                $dateArray = array($leadDate, 0);
                                $TeleCallerArray = array($TeleCaller, 0);
                                $error = 1;
                            } else {
                                $emailArray = array($email, 0);
                            }
                            $exeId=0;
                            if($TeleCaller!="") {

                                $PositionTypeId=array(3);
                                $sub = $sql->select();
                                $sub->from(array('a'=>'WF_PositionMaster'))
                                    ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                                    ->columns(array('PositionId'))
                                    ->where(array("b.PositionTypeId"=>$PositionTypeId));

                                $select = $sql->select();
                                $select->from('WF_Users')
                                    ->columns(array('UserId'))
                                    ->where->expression("PositionId IN ?",array($sub));
                                $select->where(array('EmployeeName' => $TeleCaller,'DeleteFlag'=>0,"lock"=>0));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $resultsExe= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if($resultsExe!="") {
                                    $exeId = $resultsExe['UserId'];
                                    $TeleCallerArray = array($TeleCaller, 0);
                                } else {
                                    $dateArray = array($leadDate, 0);
                                    $TeleCallerArray = array($TeleCaller, 1);
                                    $error = 1;
                                }
                            }
                            if ($error == 0) {
                                if(strtotime(str_replace('/','-',$leadDate))!=false) {
                                    $lDate=date('Y-m-d H:i:s', strtotime(str_replace('/','-',$leadDate)));
                                } else {
                                    $lDate=date('Y-m-d H:i:s');
                                }
                                $insert = $sql->insert();
                                $insert->into('Tele_Leads');
                                $insert->Values(array(
                                    'LeadName' => $leadName,
                                    'LeadDate' => $lDate,
                                    'UserId'=>$this->auth->getIdentity()->UserId,
                                    'Mobile' => $mobile,
                                    'Email' => $email,
                                    'TeleCaller' => $exeId,
                                    'CountryCode' =>91
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                $data[] = array('LeadName' => $leadArray, 'LeadDate' => $dateArray, 'TeleCaller' => $TeleCallerArray, 'Mobile' => $mobileArray, 'EmailAddress' => $emailArray);
                            }
                        }

                    }
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent(json_encode($data));

                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                }
            } else {
                // GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }

            return $response;
        }
    }

    public function leadRegisterAction() {
        // Login Authentication check
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Lead Register");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{

            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                $superiorsUserList = $viewRenderer->commonHelper()->masterSuperior($this->auth->getIdentity()->UserId,$dbAdapter);
                $powerUser =$this->auth->getIdentity()->PowerUser;

                //Write your Ajax post code here
                $postParam = $request->getPost();
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('type'),'string');
                    $unCheckedColumnNames = $this->bsf->isNullCheck($this->params()->fromPost('unCheckedColumnNames'),'string');
                    $userId = $this->auth->getIdentity()->UserId;

                    if($Type == 'updateColumn') {
                        $results="";
                        $select = $sql->select();
                        $select->from('WF_GridColumnTrans')
                            ->where(array("FunctionName"=>'TeleLeadRegister','UserId'=>$userId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resCount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        if($resCount != 0) {
                            //update
                            $update = $sql->update();
                            $update->table('WF_GridColumnTrans')
                                ->set(array('ColumnName'=>$unCheckedColumnNames))
                                ->where(array('FunctionName' =>'TeleLeadRegister','UserId'=>$userId));
                            $stmt = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            //insert
                            $insert = $sql->insert();
                            $insert->into( 'WF_GridColumnTrans' )
                                ->values(array('FunctionName' => 'TeleLeadRegister',
                                    'UserId' => $userId,
                                    'ColumnName' => $unCheckedColumnNames));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    } else if($Type == 'register') {
                        $tDate = date('Y-m-d', strtotime($postParam['tDate'])) . " 23:59:59";
                        $fDate = date('Y-m-d', strtotime($postParam['fDate'])) . " 00:00:00";
                        $filterLead = $this->bsf->isNullCheck($postParam['filter'],'number');

                        if($filterLead==2) {
                            $subQuery = $sql->select();
                            $subQuery->from(array('a'=> 'Tele_Leads'))
                                ->join(array('c' => 'Tele_LeadFollowup'), new Expression("a.LeadId=c.LeadId AND c.Completed = 0"), array('CallTypeId','FollowupDate'), $subQuery::JOIN_LEFT)
                                ->columns(array('*'));

                            //No Of Leads
                            $select = $sql->select();
                            $select->from(array("g"=>$subQuery))
                                ->columns(array('LeadId'))
                                ->where("isnull(g.CallTypeId,0) = 3");
                            $select->group(new expression("g.LeadId"));
                            $totalLeadsQuery = $statement = $sql->getSqlStringForSqlObject($select);
                            $filterLead=0;
                        } else {
                            $subQuery = $sql->select();
                            $subQuery->from(array('a'=> 'Tele_Leads'))
                                ->join(array('c' => 'Tele_LeadFollowup'), new Expression("a.LeadId=c.LeadId AND c.Completed = 0"), array('CallTypeId','FollowupDate'), $subQuery::JOIN_LEFT)
                                ->columns(array('*'));

                            //No Of Leads
                            $select = $sql->select();
                            $select->from(array("g"=>$subQuery))
                                ->columns(array('LeadId'))
                                ->where("isnull(g.CallTypeId,0) != 3");
                            $select->group(new expression("g.LeadId"));
                            $totalLeadsQuery = $statement = $sql->getSqlStringForSqlObject($select);
							
                        }


                        $select = $sql->select();
                        $select->from(array("a" => 'Tele_Leads'))
                            ->columns(array('LeadId', 'LeadDate' => new Expression("FORMAT(a.LeadDate,'dd-MM-yyyy')"), 'LeadName', 'LeadType', 'LeaserType', 'Mobile', 'Convert'=>new Expression("1-1"), 'Budget' => new Expression("CAST(a.CostPreferenceFrom As Varchar) + ' - ' + CAST(a.CostPreferenceTo As Varchar)")))
                            ->join(array("b" => "Crm_LeadTypeMaster"), "a.LeadType=b.LeadTypeId", array("LeadTypeName" => new Expression("isnull(b.LeadTypeName,'')")), $select::JOIN_LEFT)
                            ->join(array("e" => "WF_Users"), "a.TeleCaller=e.UserId", array("TeleCaller" => new Expression("isnull(e.EmployeeName,'')")), $select::JOIN_LEFT)
                            ->join(array("d" => "Crm_CampaignRegister"), "a.CampaignId=d.CampaignId", array("CampaignName"), $select::JOIN_LEFT)
                            ->join(array("f" => "WF_Users"), "a.UserId=f.UserId", array("CreatedBy" => new Expression("isnull(f.EmployeeName,'')")), $select::JOIN_LEFT)
                            ->join(array("j" => "Tele_CityView"), "a.LeadId=j.LeadId", array("CityName" => new Expression("isnull(j.CityName,'')")), $select::JOIN_LEFT)
                            ->join(array("k"=>"Tele_LeadProjectView"), "a.LeadId=k.LeadId", array('Projects' => new Expression ("isnull(k.ProjectName,'')")), $select::JOIN_LEFT);
                        $select->where(array("a.TeleCaller" => $superiorsUserList));
                        $select->where('a.LeadId IN (' .$totalLeadsQuery . ')');

                        $select->where("a.LeadDate<= '$tDate' and a.LeadDate>= '$fDate' and a.DeleteFlag=0");
                        $select->where(array('a.ConvertLead'=>$filterLead));
                        $select->order('a.LeadId desc');
						$statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $results=json_encode($result);
                    } else {
                        $results="";
                        $leadArray=json_decode($postParam['Extra']);

                        if(isset($leadArray) && count($leadArray)>0) {
                            foreach($leadArray as $l) {

                                $update = $sql->update();
                                $update->table('Tele_Leads')
                                    ->set(array('ConvertLead' => 1,'TransferDate'=>date('m-d-Y H:i:s')))
                                    ->where(array('LeadId' => $l));
                                $statement = $sql->getSqlStringForSqlObject($update);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $select = $sql->select();
                                $select->from(array("a" => 'Tele_Leads'))
                                    ->columns(array('*'));
                                $select->where(array("a.LeadId" =>$l));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $rLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array("a" => 'Tele_City'))
                                    ->columns(array('*'));
                                $select->where(array("a.LeadId" =>$l));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $rCity = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if($rLeads!="") {
                                    $insert = $sql->insert('Crm_Leads');
                                    $newData = array(
                                        'LeadDate'  => $rLeads['LeadDate'],
                                        'LeadName' =>$rLeads['LeadName'],
                                        'LeadType' =>$rLeads['LeadType'],
                                        'LeaserType'  => $rLeads['LeaserType'],
                                        'CountryCode' =>$rLeads['CountryCode'],
                                        'Mobile'  => $rLeads['Mobile'],
                                        'Email'  =>$rLeads['Email'],
                                        'StatusId' =>3,
                                        'CostPreferenceFrom'=>$rLeads['CostPreferenceFrom'],
                                        'CostPreferenceTo'=>$rLeads['CostPreferenceTo'],
                                        'UserId'=>$rLeads['TeleCaller'],
                                        'CreatedDate'=>date('m-d-Y H:i:s'),
                                        'TeleLeadId'=>$l
                                    );

                                    $insert->values($newData);
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $leadId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    if(isset($rCity) && count($rCity)>0) {
                                        foreach ($rCity as $value) {

                                            $insert = $sql->insert('Crm_LeadCity');
                                            $newData = array(
                                                'LeadId' => $leadId,
                                                'CityId' => $value['CityId'],
                                            );
                                            $insert->values($newData);
                                            $statement = $sql->getSqlStringForSqlObject($insert);
                                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }

                                }

                            }
                            $results="success";
                        }
                    }
                    $this->_view->setTerminal(true);
                    $connection->commit();
                    $response->setStatusCode(200);
                    $response->setContent($results);
                } catch (PDOException $e) {
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
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
            } else {
                $fromDate = $this->params()->fromRoute('fromDate');
                $toDate = $this->params()->fromRoute('toDate');
                $this->_view->filterLead = $this->bsf->isNullCheck($this->params()->fromRoute('filter'),'number');

                if($fromDate!=""){
                    $fromDate= strtotime(date('d-m-Y',strtotime($fromDate)));
                } else {
                    $fromDate=strtotime(date('d-m-Y',strtotime(date('d-m-Y') .' -1 month')));

                }
                if($toDate!=""){
                    $toDate= strtotime(date('d-m-Y',strtotime($toDate)));
                } else {
                    $toDate= strtotime(date('d-m-Y'));
                }
                if($toDate>=$fromDate) {
                    $this->_view->fromDate=date('d-m-Y',$fromDate);
                    $this->_view->toDate=date('d-m-Y',$toDate);
                } else {
                    $this->_view->fromDate=date('d-m-Y',$fromDate);
                    $this->_view->toDate=date('d-m-Y',$fromDate);
                }
                $userId = $this->auth->getIdentity()->UserId;

                $select = $sql->select();
                $select->from('WF_GridColumnTrans')
                    ->columns(array("ColumnName"))
                    ->where(array("FunctionName"=>'TeleLeadRegister','UserId'=>$userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $GridColumn = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->GridColumn = $GridColumn;

                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

                //Common function
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
            }

        }
    }


    public function leadSettingsAction(){
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
                $postParams = $request->getPost();
                try {
                    $result =  "";

                    $update = $sql->update();
                    $update->table('Tele_Leadsettings');
                    $update->set(array(
                        "Required"=> $this->bsf->isNullCheck($postParams['Column'],'number'),
                    ));
                    $update->where(array('TransId'=>$this->bsf->isNullCheck($postParams['Id'],'number')));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $this->_view->setTerminal(true);
                    $result = "success";
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch (PDOException $e) {
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
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams = $request->getPost();
                try {


                    $connection->commit();
                } catch (PDOException $e ) {
                    $connection->rollback();
                }
                $this->redirect()->toRoute('telecaller/default', array('controller' => 'index', 'action' => 'lead-register'));

            } else {
                try {
                    $select = $sql->select();
                    $select->from('Tele_LeadSettings')
                        ->columns(array('*'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function todayCallListAction() {

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

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                try {
                    $mode = $this->bsf->isNullCheck($this->params()->fromPost('mode'), 'string' );

                    if($mode=="title") {
                        //Write your Ajax post code here
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();

                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/telecaller/todaycall/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/telecaller/todaycall/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;
                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                foreach ($xlData as $j => $value) {
                                    if($value!="") {
                                        $data[] = array('Field' => $value);
                                    }
                                }
                            } else {
                                break;
                            }
                            $icount = $icount + 1;
                        }


                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else if($mode=="body") {
                        $uploadedFile = $request->getFiles();
                        $postData = $request->getPost();
                        if ($this->_validateUploadFile($uploadedFile) === FALSE) {
                            $response->setContent('Invalid File Format');
                            $response->setStatusCode(400);
                            return $response;
                        }
                        $file_csv = "public/uploads/telecaller/todaycall/";
                        if(!is_dir($file_csv)) {
                            mkdir($file_csv, 0755, true);
                        }
                        $file_csv = "public/uploads/telecaller/todaycall/" . md5(time()) . ".csv";
                        $this->_convertXLStoCSV($uploadedFile['file']['tmp_name'], $file_csv);

                        $data = array();
                        $file = fopen($file_csv, "r");

                        $icount = 0;
                        $RType = $postData['arrHeader'];
                        $bValid = true;

                        while (($xlData = fgetcsv($file, 100, ",")) !== FALSE) {

                            if ($icount == 0) {
                                if(isset($xlData)) {
                                    foreach (json_decode($RType) as $k) {
                                        foreach ($xlData as $j => $value) {
                                            if (trim($value) != "") {
                                                $bFound = false;
                                                $sField = "";

                                                if (trim($value) == trim($k->efield)) {
                                                    $sField = $k->field;
                                                    $bFound = true;
//                                                    break;
                                                }
                                                if ($bFound == true) {
                                                    if (trim($sField) == "Name") {
                                                        $col_1 = intval($j);
                                                    }
                                                    if (trim($sField) == "Date") {
                                                        $col_2 = intval($j);
                                                    }
                                                    if (trim($sField) == "Mobile") {
                                                        $col_3 = intval($j);
                                                    }

                                                }
                                            }
                                        }
                                    }
                                }
                            } else {

                                $Name="";
                                $Date="";
                                $Mobile="";

                                if (isset($col_1) && !is_null($col_1) && trim($col_1)!="" && isset($xlData[$col_1])) {
                                    $Name =$this->bsf->isNullCheck(trim($xlData[$col_1]),'string');
                                }
                                if (isset($col_2) && !is_null($col_2) && trim($col_2)!="" && isset($xlData[$col_2])) {
                                    $Date =$this->bsf->isNullCheck(trim($xlData[$col_2]),'string');
                                }
                                if (isset($col_3) && !is_null($col_3) && trim($col_3)!="" && isset($xlData[$col_3])) {
                                    $Mobile = $this->bsf->isNullCheck(trim($xlData[$col_3]),'string');
                                }


                                if($Name!="" || $Date!="" || $Mobile!="" ) {
                                    $data[] = array('Valid' => $bValid, 'Name' => $Name, 'Date' => $Date, 'Mobile' => $Mobile);
                                }
                            }
                            $icount++;
                        }

                        if ($bValid == false) {
                            $data[] = array('Valid' => $bValid);
                        }
                        // delete csv file
                        fclose($file);
                        unlink($file_csv);
                    } else {
                        $postData = $request->getPost();
                        $rowCount = $postData['rowCount'];
                        $data = array();
                        for ($i = 0; $i <= $rowCount; $i++) {

                            $Name = $this->bsf->isNullCheck(trim($postData['name_' . $i]), 'string');
                            $Date = $this->bsf->isNullCheck(trim($postData['date_' . $i]), 'string');
                            $Mobile = $this->bsf->isNullCheck(trim($postData['mobile_' . $i]), 'string');

                            if($Name=="" && $Date=="" && $Mobile == "" ) {
                                continue;
                            }
                            $error=0;
                            if ($Name == "") {
                                $nameArray = array($Name, 1);
                                $error = 1;
                            } else {
                                $nameArray = array($Name, 0);
                            }
                            $select = $sql->select();
                            $select->from('Tele_Contacts')
                                ->columns(array('ContactId'));
                            $select->where(array('Mobile' => $Mobile,'DeleteFlag'=>0));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $cMobile= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            if ($Mobile == "" || count($cMobile)>0) {
                                $mobileArray = array($Mobile, 1);
                                $error = 1;
                            } else {
                                $mobileArray = array($Mobile, 0);
                            }
                            $dateArray = array($Date, 0);
                            if ($error == 0) {

                                $lDate=date('Y-m-d')." 00:00:00";

                                $diff = strtotime(str_replace('/','-',$Date));
                                if($diff!=false && $diff < strtotime($lDate)) {
                                    $lDate=date('Y-m-d H:i:s',$diff);
                                }
                                $insert = $sql->insert();
                                $insert->into('Tele_Contacts');
                                $insert->Values(array(
                                    'Name' => $Name,
                                    'Date' => $lDate,
                                    'Mobile' => $Mobile
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                $data[] = array('Name' => $nameArray, 'Date' => $dateArray, 'Mobile' => $mobileArray);
                            }
                        }

                    }
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent(json_encode($data));

                } catch(PDOException $e){
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
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams = $request->getPost();
                try {


                    $connection->commit();
                } catch (PDOException $e ) {
                    $connection->rollback();
                }
                $this->redirect()->toRoute('telecaller/default', array('controller' => 'index', 'action' => 'lead-register'));

            } else {
                try {
                    $select = $sql->select();
                    $select->from('Tele_Contacts')
                        ->columns(array('*'))
                        ->order('Date Desc');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->TeleContacts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
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

        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $sql     = new Sql($dbAdapter);
                $postParams = $request->getPost();

                $select = $sql->select();
                $select->from(array('a' =>'Tele_LeadProjects'))
                    ->join(array("b"=>"Tele_Leads"), "a.LeadId=b.LeadId", array("LeadName"=>new expression("b.LeadName + ' - ' + b.Mobile")), $select::JOIN_LEFT)
                    ->columns(array('LeadId'))
                    ->where(array('a.ProjectId' => $postParams['ProjectId']))
                    ->order('a.LeadId asc');
                $select->where(array("b.TeleCaller" => $this->auth->getIdentity()->UserId,"b.DeleteFlag"=>0,"b.ConvertLead"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($resultLeads));
                //$response = $this->getResponse()->setContent($resultLeads);
                return $response;
            }
        }
        else {
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Print_r($postParams);
                $leadId = $postParams['leadId'];
                $projectId = $postParams['ProjectId'];
                $FeedId = $this->params()->fromQuery('FeedId');
                $AskId = $this->params()->fromQuery('AskId');
                if((isset($FeedId) && $FeedId!="")) {
                    $this->redirect()->toRoute('telecaller/followup-page', array('controller' => 'index', 'action' => 'followup', 'leadId' => $leadId,'projectId'=>$projectId), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                } else {
                    $this->redirect()->toRoute('telecaller/followup-page', array('controller' => 'index', 'action' => 'followup', 'leadId' => $leadId,'projectId'=>$projectId));
                }
            } else {

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId','ProjectName'))
                    ->where(array("DeleteFlag"=>0));
                $select->order("ProjectId Desc");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
        $request = $this->getRequest();

        if($this->getRequest()->isXmlHttpRequest())	{

            //$resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $sql     = new Sql($dbAdapter);
                $postParams = $request->getPost();

            }
        } else {
            if ($request->isPost()) {
                // Print_r($postParams);die;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $postParams = $request->getPost();
                    $leadId = $postParams['leadId'];
                    $projectId = $postParams['projectId'];
                    $nextCallDate = date('Y-m-d H:i:s',strtotime(str_replace("/","-",$postParams['nextCallDate'])));

                    //followup
                    $leadDate = date('Y-m-d',strtotime($postParams['leadDate']));
                    $statusId  = $postParams['statusId'];
                    $nextFollowUpTypeId = $postParams['nextFollowUpTypeId'];

                    $remarks=$postParams['remarks'];
                    $executiveId=$postParams['actionRequiredBy'];
                    $natureId=$postParams['natureId'];
                    $entryId=$postParams['entryId'];
                    $callTypeId=$postParams['callType'];

                    if($callTypeId==1) {
                        $insert  = $sql->insert('Tele_LeadFollowup');
                        $newData = array(
                            'FollowUpDate'  => $leadDate,
                            'LeadId' => $leadId,
                            'NextCallDate' => $nextCallDate,
                            'ExecutiveId' => $executiveId,
                            'NextFollowUpTypeId' => $nextFollowUpTypeId,
                            'NatureId'=>$natureId,
                            'NextFollowupRemarks'=>$postParams['nextfollowremarks'],
                            'CallTypeId' => $callTypeId,
                            'StatusId' => $statusId,
                            'nCallSid'=>$this->bsf->isNullCheck($postParams['caller_sid'],'string'),
                            'Remarks' => $remarks,
                            'UserId'=>$this->auth->getIdentity()->UserId,
                            'ModifiedDate'=>date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Tele_LeadProjects')
                            ->where(array('LeadId' => $leadId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $Project=$postParams['Projects_name'];
                        if(count($Project)>0) {
                            foreach($Project as $Pr) {
                                $insert = $sql->insert('Tele_LeadProjects');
                                $newData = array(
                                    'ProjectId' => $Pr,
                                    'LeadId' => $leadId,
                                    'ModifiedDate' => date('Y-m-d H:i:s')
                                );
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        }
                    } else if($callTypeId==2) {
                        $insert  = $sql->insert('Tele_LeadFollowup');
                        $newData = array(
                            'FollowUpDate'  => $leadDate,
                            'LeadId' => $leadId,
                            'NextCallDate' => $nextCallDate,
                            'ExecutiveId' => $executiveId,
                            'CallTypeId' => $callTypeId,
                            'nCallSid'=>$this->bsf->isNullCheck($postParams['caller_sid'],'string'),
                            'Remarks' => $remarks,
                            'UserId'=>$this->auth->getIdentity()->UserId,
                            'ModifiedDate'=>date('Y-m-d H:i:s'),
                            'TransferTo'=> $postParams['transferTo'],
                            'TransferFrom'=>$postParams['curLeadExe']
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('Tele_Leads');
                        $update->set(array(
                            'TeleCaller'  => $postParams['transferTo']
                        ));
                        $update->where(array('LeadId'=>$leadId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    } else {
                        $insert  = $sql->insert('Tele_LeadFollowup');
                        $newData = array(
                            'FollowUpDate'  => $leadDate,
                            'LeadId' => $leadId,
                            'ExecutiveId' => $executiveId,
                            'CallTypeId' => $callTypeId,
                            'nCallSid'=>$this->bsf->isNullCheck($postParams['caller_sid'],'string'),
                            'Remarks' => $remarks,
                            'UserId'=>$this->auth->getIdentity()->UserId,
                            'ModifiedDate'=>date('Y-m-d H:i:s')
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }


                    $update = $sql->update();
                    $update->table('Tele_LeadFollowup');
                    $update->set(array(
                        'Completed'  => 1,
                        'CompletedDate'  => date('Y-m-d H:i:s'),

                    ));
                    $update->where(array('EntryId'=>$entryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    //CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lead-Followup-Add','N','Lead-Followup-Details',$newEntryId,0, 0, 'CRM','',$userId, 0 ,0);

                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if($callTypeId==3 || $callTypeId==2) {
                        if(isset($FeedId) && $FeedId!="") {
                            $this->redirect()->toRoute('telecaller/followup-page', array('controller' => 'index', 'action' => 'followup-entry'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        } else {
                            $this->redirect()->toRoute('telecaller/followup-page', array('controller' => 'index', 'action' => 'followup-entry'));
                        }
                    } else {
                        if(isset($FeedId) && $FeedId!="") {
                            $this->redirect()->toRoute('telecaller/followup-page', array('controller' => 'index', 'action' => 'followup', 'leadId' => $leadId,'projectId' =>$projectId), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        } else {

                            $this->redirect()->toRoute('telecaller/followup-page', array('controller' => 'index', 'action' => 'followup', 'leadId' => $leadId,'projectId' =>$projectId));
                        }
                    }

                } catch (PDOException $e) {
                    $connection->rollback();
                }
            } else {
                $leadId = $this->bsf->isNullCheck($this->params()->fromRoute('leadId'), 'number');

                $projectId = $this->bsf->isNullCheck($this->params()->fromRoute('projectId'), 'number');
                $this->_view->callSid = $this->bsf->isNullCheck($this->params()->fromRoute('callSid'), 'string');

                $PositionTypeId=array(3);


                $sub = $sql->select();
                $sub->from(array('a'=>'WF_PositionMaster'))
                    ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                    ->columns(array('PositionId'))
                    ->where(array("b.PositionTypeId"=>$PositionTypeId));

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array("*"))
                    ->where->expression("PositionId IN ?",array($sub));
                $select->where(array("DeleteFlag"=>0,"lock"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsUser = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId'=>'UserId','UserName' => 'EmployeeName'))
                    ->where->expression("PositionId IN ?",array($sub));
                $select->where(array("DeleteFlag"=>0,"lock"=>0));
                $select->where("UserId <>'".$this->auth->getIdentity()->UserId."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsTransUser = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $superiorsUserList = $viewRenderer->commonHelper()->masterSuperior($this->auth->getIdentity()->UserId,$dbAdapter);


                $select = $sql->select();
                $select->from(array("a"=>'Tele_Leads'))
                    ->columns(array('LeadId','LeadName'))
                    ->join(array("g"=>"Tele_LeadProjects"),"a.LeadId=g.LeadId",array(),$select::JOIN_LEFT);
                $select->where(array("a.TeleCaller" =>$superiorsUserList));
                if($projectId!=0) {
                    $select->where(array("g.ProjectId"=>$projectId));
                }
                $select->limit(2000);
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId','ProjectName'))
                    ->order('ProjectId desc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLeadProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $select = $sql->select();
//                $select->from('Crm_UnitTypeMaster')
//                    ->columns(array('UnitTypeId','UnitTypeName'));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultsType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId'=>'UserId','UserName' => 'EmployeeName'))
                    ->where->expression("PositionId IN ?",array($sub));
                $select->where(array("DeleteFlag"=>0,"lock"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsExecutive = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


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
                    ->where(array("Lead"=>1))
                    ->where(array("Description NOT IN ('Fresh')"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsCall = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_NatureMaster')
                    ->columns(array('NatureId','Description'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsNature  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array("a"=>'Tele_Leads'))
                    ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),'LeadName','LeaserType','TeleCaller','Email','Mobile'))
                    ->join(array("g"=>"Crm_LeadTypeMaster"),"a.LeadType=g.LeadTypeId",array("LeadTypeName"),$select::JOIN_LEFT)
                    ->join(array("k"=>"WF_Users"),"a.TeleCaller=k.UserId",array("UserName" => 'EmployeeName'),$select::JOIN_LEFT)
                    //->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    //->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
                    //->join(array("h"=>"Crm_LeadPersonalInfo"),"a.LeadId=h.LeadId",array("Photo"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$leadId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Tele_LeadFollowup'))
                    ->columns(array('EntryId','FollowUpDate'=>new Expression("CONVERT(varchar(10),FollowUpDate,105)"),'LeadId','NextFollowUpTypeId','CallTypeId','NatureId','Remarks','NextFollowupRemarks','nCallSid','NextCallDate'=>new Expression("CONVERT(varchar(10),NextCallDate,105)"),'ExecutiveId'),
                        array("call"),array("LeadName"),
                        array("next"),
                        array("UnitTypeName"),array("state"=>"Description"))
                    ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                    ->join(array("j"=>"Crm_NatureMaster"),"a.NatureId=j.NatureId",array("Nat"=>"Description"),$select::JOIN_LEFT)
                    //->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
                    ->join(array("g"=>"WF_Users"),"a.ExecutiveId=g.UserId",array("UserName" => 'EmployeeName'),$select::JOIN_LEFT);
                $select->where(array('a.LeadId'=>$leadId))
                    ->order("a.EntryId Desc");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->responseFollow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                //$EntryId = $dbAdapter->getDriver()->getLastGeneratedValue();


                $selectMultiProject = $sql->select();
                $selectMultiProject->from(array("a"=>"Tele_LeadProjects"));
                $selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
                    ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
                $selectMultiProject->where(array("a.LeadId"=>$leadId));
                $statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject);
                $this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->leadId =$leadId;
                $this->_view->projectId=$projectId;
            }
        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $select = $sql->select();
            if ($request->isPost()) {
                $postData = $request->getPost();
                $files = $request->getFiles();

                $Type = $this->bsf->isNullCheck($postData['type'], 'string');

                if($Type == "ajaxloader") {
                    $urls = array();
                    foreach($files['file'] as $doc) {
                        if($doc['name']){
                            $dir = 'public/uploads/tmp/';
                            $filename = $this->bsf->uploadFile($dir, $doc);
                            if($filename) {
                                // update valid files only
                                $urls[] = $viewRenderer->basePath. 'public/uploads/tmp/'. $filename;
//                                $attachment = file_get_contents($url);
//                                $attachment_encoded = base64_encode($attachment);
//                                $attachments[] = array(
//                                    'name' => $doc['name'],
//                                    'type' => mime_content_type($url),
//                                    'content' =>$attachment_encoded
//                                );
                            }
                        }
                    }
                    $result['url'] = $urls;

                } else if($Type == "mailRequest") {
                    $ToMail = $this->bsf->isNullCheck($postData['to'], 'string');

                    $content = $this->bsf->isNullCheck($postData['compose-textarea'], 'string');
                    $subject= $this->bsf->isNullCheck($postData['subject'], 'string');

                    $mailData=$content;
                    $i=0;
                    $attachments=array();
                    if(isset($postData['fileattached']) && $postData['fileattached'] != '') {
                        foreach ($postData['fileattached'] as $url) {
                            $i++;
                            $attachment = file_get_contents($url);
                            $attachment_encoded = base64_encode($attachment);
                            $attachments[] = array(
                                'name' => 'attachment_' . $i,
                                'type' => mime_content_type($url),
                                'content' => $attachment_encoded
                            );
                        }
                    }
                    $recipients = array(array('email' => $ToMail,'type'=>'to'));
                    if(isset($postData['Cc']) && $postData['Cc'] != '') {
                        foreach($postData['Cc'] as $Ccdoc) {
                            $recipients[] = array('email' => $Ccdoc,'type' => 'cc');
                        }
                    }
                    if(isset($postData['Bcc']) && $postData['Bcc'] != '') {
                        foreach ($postData['Bcc'] as $Bccdoc) {
                            $recipients[] = array('email' => $Bccdoc, 'type' => 'bcc');
                        }
                    }
                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    $viewRenderer->MandrilSendMail()->sendMailWithMultipleAttachmentWithoutTemplate($recipients,$config['general']['mandrilEmail'],$subject, $attachments, $mailData);
                }
                //Write your Ajax post code here
                $select->from(array("a"=>'Crm_Leads'))
                    ->columns(array('*'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result['Leads'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            } else {
                try {
                    $leadId = $this->bsf->isNullCheck($this->params()->fromRoute('leadId'), 'number');
                    $projectId = $this->bsf->isNullCheck($this->params()->fromRoute('projectId'), 'number');

                    if ($leadId == 0) {
                        throw new \Exception('Invalid Lead Id..!');
                    }
//                    if ($projectId == 0) {
//                        throw new \Exception('Invalid Project Id..!');
//                    }
                    $this->_view->projectId=$projectId;

                    $select = $sql->select();
                    $select->from('Tele_Leads')
                        ->columns(array('LeadId', 'LeadName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql->select();
                    $select->from(array("a" => 'Tele_Leads'))
                        ->columns(array('LeadId', 'LeadDate' => new Expression("CONVERT(varchar(10),LeadDate,105)"), 'LeadType', 'LeadName', 'TeleCaller', 'Email', 'Mobile',))
                        ->join(array("f" => "Crm_LeadTypeMaster"), "a.LeadType=f.LeadTypeId", array("LeadTypeName"), $select::JOIN_LEFT)
                        ->join(array("k" => "WF_Users"), "a.TeleCaller=k.UserId", array("UserName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array("h" => "Crm_LeadPersonalInfo"), "a.LeadId=h.LeadId", array("Photo"), $select::JOIN_LEFT)
//                    ->join(array("i"=>"Crm_StatusMaster"),"a.StatusId=i.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                        ->where(array('a.LeadId' => $leadId));

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a" => 'Tele_LeadFollowup'))
                        ->columns(array('NextFollowUpTypeId','NextCallDate' => new Expression("CONVERT(varchar(10),NextCallDate,105)"), 'NextTime' => new Expression("RIGHT(CONVERT(VARCHAR, NextCallDate, 100), 7)")))
                       // ->join(array("i" => "Crm_CallTypeMaster"), "a.NextFollowUpTypeId=i.CallTypeId", array("NextCallType" => "Description"), $select::JOIN_LEFT)
                        ->join(array("c" => "Crm_StatusMaster"), "a.StatusId=c.StatusId", array("state" => "Description"), $select::JOIN_LEFT)
                        ->where(array("a.LeadId" => $leadId))
                        ->where(array("a.Completed" => 0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->lastFollowdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a" => 'Tele_LeadFollowup'))
                        ->columns(array('EntryId', 'FollowUpDate' => new Expression("CONVERT(varchar(10),FollowUpDate,105)"), 'LeadId', 'CallTypeId', 'NextFollowupTypeId', 'StatusId', 'Remarks', 'nCallSid', 'NextTime' => new Expression("RIGHT(CONVERT(VARCHAR, NextCallDate, 100), 7)"), 'NextCallDate' => new Expression("CONVERT(varchar(10),NextCallDate,105)"), 'ExecutiveId'))
                        ->join(array("c" => "Crm_StatusMaster"), "a.StatusId=c.StatusId", array("state" => "Description"), $select::JOIN_LEFT)
                        ->join(array("d" => "Crm_NatureMaster"), "a.NatureId=d.NatureId", array("Nat" => "Description"), $select::JOIN_LEFT)
                        //->join(array("e"=>"Crm_UnitTypeMaster"),"a.UnitTypeId=e.UnitTypeId",array("UnitTypeName"),$select::JOIN_LEFT)
//                        ->join(array("f" => "Crm_CallTypeMaster"), "a.CallTypeId=f.CallTypeId", array("CallType" => "Description"), $select::JOIN_LEFT)
                        ->join(array("g" => "WF_Users"), "a.UserId=g.UserId", array("UserName" => 'EmployeeName'), $select::JOIN_LEFT);
                    $select->where(array("a.LeadId" => $leadId))
                        ->order("a.EntryId Desc");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->responseFollowdata = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

    public function detailsAction(){
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
        $sql = new Sql($dbAdapter);
        $userId = $this->auth->getIdentity()->UserId;
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
                $select->from(array("a"=>'Tele_Leads'))
                    ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),'LeadName','LeadType','Mobile','Remarks','Email','TeleCaller','CostPreferenceTo','CostPreferenceFrom','ConvertLead'))
                    ->join(array("f"=>"Crm_LeadTypeMaster"),"a.LeadType=f.LeadTypeId",array('LeadTypeId','LeadTypeName'),$select::JOIN_LEFT)
                    ->join(array("g"=>"Crm_CampaignRegister"),"a.CampaignId=g.CampaignId",array('CampaignName'),$select::JOIN_LEFT)
                    ->join(array("j"=>"WF_Users"), "a.TeleCaller=j.UserId", array("UserName" => 'EmployeeName'), $select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$detailId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select = $sql->select();
                $select->from(array("a"=>'Tele_LeadFollowup'))
                    ->columns(array('NextCallDate','NextFollowUpTypeId'))
                    //->join(array("i"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=i.CallTypeId",array("NextCallType"=>"Description"),$select::JOIN_LEFT)
                    ->where(array("a.LeadId"=>$detailId))
                    ->where(array("a.Completed"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->nextFollowupDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $selectMultiProject = $sql->select();
                $selectMultiProject->from(array("a"=>"Tele_LeadProjects"));
                $selectMultiProject->columns(array("ProjectId"))
                    ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
                $selectMultiProject->where(array("a.LeadId"=>$detailId));
                $statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject);
                $this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $selectMultiCT = $sql->select();
                $selectMultiCT->from(array("a"=>"Tele_City"));
                $selectMultiCT->columns(array("CityId"))
                    ->join(array("b"=>"WF_CityMaster"), "a.CityId=b.CityId", array("CityName"), $selectMultiCT::JOIN_INNER);
                $selectMultiCT->where(array("a.LeadId"=>$detailId));
                $statementMultiCT = $sql->getSqlStringForSqlObject($selectMultiCT);
                $this->_view->resultMultiCT = $dbAdapter->query($statementMultiCT, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                //Multi select LeadSource.
//                $selectMultiSource1 = $sql->select();
//                $selectMultiSource1->from(array("a"=>"Crm_LeadSource"));
//                $selectMultiSource1->columns(array("LeadName"=>new expression("b.LeadSourceName")))
//                    ->join(array("b"=>"Crm_LeadSourceMaster"), "a.LeadSourceId=b.LeadSourceId", array(), $selectMultiSource1::JOIN_INNER);
//                $selectMultiSource1->where(array("a.LeadId"=>$detailId,"a.Name"=>'L'));
//
//                $selectMultiSource2 = $sql->select();
//                $selectMultiSource2->from(array("a"=>"Crm_LeadSource"));
//                $selectMultiSource2->columns(array("LeadName"=>new expression("b.CampaignName")))
//                    ->join(array("b"=>"Crm_CampaignRegister"), "a.LeadSourceId=b.CampaignId", array(), $selectMultiSource2::JOIN_INNER);
//                $selectMultiSource2->where(array("a.LeadId"=>$detailId,"a.Name"=>'C'));
//                $selectMultiSource2->combine($selectMultiSource1,'Union ALL');
//
//                $statementMultiSource = $sql->getSqlStringForSqlObject($selectMultiSource2);
//                $this->_view->resultMultiSource = $dbAdapter->query($statementMultiSource, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                $bAns = true;
//                CommonHelper::CheckPowerUser($userId, $dbAdapter);
//                if($viewRenderer->bPowerUser == false) {
//                    $bAns = CommonHelper::FindPermission($userId, 'Lead-Modify', $dbAdapter);
//                }
//                $this->_view->leadEdit = $bAns;

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



    public function missedCallListAction(){
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
                $postParams = $request->getPost();
                try {

                    $update = $sql->update();
                    $update->table('Tele_IncomingCallTrack')
                        ->set(array('CallBack' => 1,
                            'CallBackCallSid'=>$postParams['CallSid']))
                        ->where(array("TransId" => $postParams['missedCallId']));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $result =  "";

                    $this->_view->setTerminal(true);
                    $result = "success";
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch (PDOException $e) {
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
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $postParams = $request->getPost();
                try {


                    $connection->commit();
                } catch (PDOException $e ) {
                    $connection->rollback();
                }
                $this->redirect()->toRoute('telecaller/default', array('controller' => 'index', 'action' => 'lead-register'));

            } else {
                try {
                    $executive = $this->bsf->isNullCheck($this->params()->fromRoute('exeId'), 'number' );
                    $userId =$this->auth->getIdentity()->UserId;
                    $superiors = $viewRenderer->commonHelper()->masterSuperior($userId,$dbAdapter);

                    if(isset($executive) && $executive!=0) {
                        $arrExecutiveIds=array();
                        array_push($arrExecutiveIds,$executive);
                        $this->_view->executive=$executive;
                    } else {
                        $arrExecutiveIds=$superiors;
                    }

                    $subQuery = $sql->select();
                    $subQuery->from(array('a'=> 'Wf_Users'))
                        ->columns(array('Mobile',"UserId","EmployeeName"))
                        ->where('a.UserId IN (' . implode(',', $superiors) . ')')
                        ->where(array('a.DeleteFlag' => 0,'a.lock'=>0));
                    $statement = $sql->getSqlStringForSqlObject($subQuery);
                    $this->_view->ControlUserList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'WF_Users'))
                        ->columns(array('ContactNum'=>new Expression("'0'+ a.Mobile")))
                        ->where(array('Lock'=>0,'DeleteFlag'=>0,'UserId'=>$arrExecutiveIds));
                    $exeMobile = $sql->getSqlStringForSqlObject($select);


                    $select = $sql->select();
                    $select->from(array('a' => 'Tele_IncomingCallTrack'))
                        ->join(array("b"=>"Crm_CampaignRegister"),new Expression("b.CampaignId=a.CampaignId"),array("CampName"=>new expression("b.CampaignName + ' - ' + b.SourceName")),$select::JOIN_LEFT)
                        ->join(array("c"=>"Crm_CampaignProjectView"),new Expression("a.CampaignId=c.CampaignId"),array("ProjectName"),$select::JOIN_LEFT)
                        ->join(array("d"=>"Crm_Leads"),new Expression("'0' + d.Mobile=a.CallFrom and d.DeleteFlag=0"),array(),$select::JOIN_LEFT)
                        ->join(array("e"=>"Tele_Leads"),new Expression("'0' + e.Mobile=a.CallFrom and e.DeleteFlag=0 and e.ConvertLead=0"),array(),$select::JOIN_LEFT)
                        ->columns(array('rn'=>new Expression("ROW_NUMBER() OVER (PARTITION BY a.CallFrom ORDER BY a.TransId DESC)")
                        ,'LeadNameFinal'=>new Expression("case when d.LeadName<>'' then d.LeadName else e.LeadName end"),'TransId','TypeLeadFinal'=>new Expression("case when d.LeadName<>'' then 'Marketing' when e.LeadName<>'' then 'TeleCaller' else 'Fresh' end"),'CallSid','CallFrom','Time'=>new Expression("RIGHT(CONVERT(VARCHAR, a.ModifiedDate, 100), 7)"),"ModifiedDate","AttendNumber"=>new expression ("(Select isnull(q.ToNum,'') From Tele_CallerDetails as q Where q.CallSid=a.CallSid and q.DeleteFlag=0)"),"Attend"=>new expression ("(case when (Select count(q.CallSId) From Tele_CallerDetails as q Where q.CallSid=a.CallSid and q.DeleteFlag=0)>0 Then 1 Else 0 end)")))
                        ->where("a.CallSid NOT IN (Select p.CallSId From Tele_CallerDetails as p Where p.CallSId=a.CallSid and p.ToNum = a.DialWhomNumber and p.DeleteFlag=0)");
                    $select->where('a.DialWhomNumber IN (' .$exeMobile . ')');
                    $select->where(array('a.DeleteFlag'=>0,'a.CallBack'=>0));

                    //No Of Leads
                    $subQuery = $sql->select();
                    $subQuery->from(array("g"=>$select))
                        ->join(array("b"=>"WF_Users"),new Expression("'0' + b.Mobile=g.AttendNumber and b.DeleteFlag=0"),array("AttendName"=>new expression("b.EmployeeName")),$select::JOIN_LEFT)
                        ->columns(array('TransId','CallFrom','LeadNameFinal','TypeLeadFinal','Time','ModifiedDate','CampName','Attend','AttendNumber','ProjectName','MissedCallCount'=>new Expression("(select count(f.CallFrom) from Tele_IncomingCallTrack as f Where f.CallSid Not IN (Select o.CallSId From Tele_CallerDetails as o Where o.CallSid=f.CallSid and f.DialWhomNumber = o.ToNum) and f.DeleteFlag=0 and f.CallBack=0 and f.CallFrom=g.CallFrom and f.DialWhomNumber in($exeMobile))")))
                        ->where(array("g.rn"=>1));
                    $subQuery->order("g.TransId DESC");
                    $statement = $sql->getSqlStringForSqlObject($subQuery);
                    $this->_view->missedCall = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
    }
    }



    public function followupDetailsAction(){
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

        if($request->isXmlHttpRequest())	{
            $response = $this->getResponse();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                try {
                    $result =  "";

                    $this->_view->setTerminal(true);
                    $result = "success";
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch (PDOException $e) {
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
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $leadDate = date('m-d-Y',strtotime($postParams['LeadDate']));
                $leadId = $postParams['leadId'];
                $projectId = $postParams['projectId'];
                $entryId = $postParams['entryId'];
                $statusId  = $postParams['StatusId'];
                $executiveId  = $postParams['ExecutiveId'];

                $nCallDate = str_replace('/', '-', $postParams['NextCallDate']);
                $nextCallDate = date('Y-m-d H:i:s');
                if(strtotime($nCallDate) != FALSE) {
                    $nextCallDate = date('Y-m-d H:i:s', strtotime($nCallDate));
                }


                $leadFlag = $postParams['leadFlag'];
                //followup3
                $nextFollowUpTypeId = $postParams['nextFollowUpTypeId'];

                $natureId=$postParams['natureId'];
                $remarks=$postParams['Remarks'];
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $update = $sql->update();
                    $update->table('Tele_LeadFollowup');
                    $update->set(array(
                        'FollowUpDate'  => $leadDate,
                        'NextCallDate' => $nextCallDate,
                        'ExecutiveId' => $executiveId,
                        'NatureId'=>$natureId,
                        'NextFollowUpTypeId' => $nextFollowUpTypeId,
                        'NextFollowupRemarks'=>$postParams['nextfollowremarks'],
                        'StatusId' => $statusId,
                        'Remarks' => $remarks,
                        'nCallSid'=>$this->bsf->isNullCheck($postParams['caller_sid'],'string'),
                        'LeadId' => $leadId
                    ));
                    $update->where(array('EntryId'=>$entryId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                    $connection->commit();
//                    if($leadFlag=="B") {
//                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Buyer-Followup-Modify','E','Buyer-Followup-Details',$entryId,0, 0, 'CRM','',$userId, 0 ,0);
//                    } else {
//                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Lead-Followup-Modify','E','Lead-Followup-Details',$entryId,0, 0, 'CRM','',$userId, 0 ,0);
//                    }
                    $this->redirect()->toRoute('telecaller/followup-page', array('controller' => 'index', 'action' => 'followup','leadId'=>$leadId,'projectId'=>$projectId));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                try {
                    $entryId = $this->bsf->isNullCheck($this->params()->fromRoute('EntryId'), 'number');
                    $projectId = $this->bsf->isNullCheck($this->params()->fromRoute('ProjectId'), 'number');

                    if ($entryId == 0) {
                        throw new \Exception('Invalid Lead Id..!');
                    }
                    $superiorsUserList = $viewRenderer->commonHelper()->masterSuperior($this->auth->getIdentity()->UserId,$dbAdapter);

                    $select = $sql->select();
                    $select->from('Tele_LeadFollowup')
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

                    $this->_view->entryId=$entryId;
                    $this->_view->leadId=$ld;
                    $this->_view->projectId=$projectId;

                    $superiorsUserList = $viewRenderer->commonHelper()->masterSuperior($this->auth->getIdentity()->UserId,$dbAdapter);


                    $select = $sql->select();
                    $select->from(array("a"=>'Tele_Leads'))
                        ->columns(array('LeadId','LeadName'))
                        ->join(array("g"=>"Tele_LeadProjects"),"a.LeadId=g.LeadId",array(),$select::JOIN_LEFT);
                    $select->where(array("a.TeleCaller" =>$superiorsUserList));
                    if($projectId!=0) {
                        $select->where(array("g.ProjectId"=>$projectId));
                    }
                    $select->limit(2000);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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


                    $PositionTypeId=array(3);


                    $sub = $sql->select();
                    $sub->from(array('a'=>'WF_PositionMaster'))
                        ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                        ->columns(array('PositionId'))
                        ->where(array("b.PositionTypeId"=>$PositionTypeId));

                    $select = $sql->select();
                    $select->from('WF_Users')
                        ->columns(array("*"))
                        ->where->expression("PositionId IN ?",array($sub));
                    $select->where(array("DeleteFlag"=>0,"lock"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsExecutive = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a"=>'Tele_Leads'))
                        ->columns(array('LeadId','LeadDate'=>new Expression("CONVERT(varchar(10),LeadDate,105)"),
                            'LeadName','LeadType','LeaserType','TeleCaller','Email','Mobile'))
                        ->join(array("g"=>"Crm_LeadTypeMaster"),"a.LeadType=g.LeadTypeId",array("LeadTypeName"),$select::JOIN_LEFT)
                        ->join(array("k"=>"WF_Users"),"a.TeleCaller=k.UserId",array("UserName" => 'EmployeeName'),$select::JOIN_LEFT)
                        ->where(array("a.LeadId"=>$ld));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a"=>'Tele_LeadFollowup'))
                        ->columns(array('EntryId','FollowUpDate'=>new Expression("CONVERT(varchar(10),FollowUpDate,105)"),'NextFollowUpTypeId','LeadId','CallTypeId','nCallSid','NextFollowupRemarks','NatureId','StatusId','Remarks','NextFollowupRemarks','NextCallDate'=>new Expression("NextCallDate"),'ExecutiveId'))
                        ->join(array("c"=>"Crm_StatusMaster"),"a.StatusId=c.StatusId",array("state"=>"Description"),$select::JOIN_LEFT)
                        ->join(array("d"=>"Crm_NatureMaster"),"a.NatureId=d.NatureId",array("Nat"=>"Description"),$select::JOIN_LEFT)
                        //->join(array("h"=>"Crm_CallTypeMaster"),"a.NextFollowUpTypeId=h.CallTypeId",array("Nature"=>"Description"),$select::JOIN_LEFT)
                        //->join(array("f"=>"Crm_CallTypeMaster"),"a.CallTypeId=f.CallTypeId",array("call"=>"Description"),$select::JOIN_LEFT)
                        ->join(array("g"=>"WF_Users"),"a.ExecutiveId=g.UserId",array("UserName" => 'EmployeeName'),$select::JOIN_LEFT)
                        ->where(array('a.EntryId' => $entryId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->responseFollow = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a"=>'Tele_Leads'))
                        ->columns(array('LeadId','LeadName'))
                        ->join(array("g"=>"Tele_LeadProjects"),"a.LeadId=g.LeadId",array(),$select::JOIN_LEFT);
                    $select->where(array("a.TeleCaller" =>$superiorsUserList ,'g.ProjectId'=>$projectId));
                    $select->limit(2000);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $selectMultiProject = $sql->select();
                    $selectMultiProject->from(array("a"=>"Tele_LeadProjects"));
                    $selectMultiProject->columns(array("ProjectId"),array("ProjectName"))
                        ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiProject::JOIN_INNER);
                    $selectMultiProject->where(array("a.LeadId"=>$ld));
                    $statementMultiProject = $sql->getSqlStringForSqlObject($selectMultiProject);
                    $this->_view->resultMultiProject = $dbAdapter->query($statementMultiProject, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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

}