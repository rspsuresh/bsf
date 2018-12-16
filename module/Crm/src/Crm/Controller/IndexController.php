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

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Zend\Session\Container;

class IndexController extends AbstractActionController
{
	public function __construct()
	{
		$this->auth = new AuthenticationService();
		$this->bsf = new \BuildsuperfastClass();
		
		if ($this->auth->hasIdentity()) {
			$this->identity = $this->auth->getIdentity();
		}
		$this->_view = new ViewModel();
	}
	
	public function dashboardAction(){
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
        $userId =$this->auth->getIdentity()->UserId;

        $arrExecutiveIds = $viewRenderer->commonHelper()->masterSuperior($userId,$dbAdapter);
        $powerUser =$this->auth->getIdentity()->PowerUser;

		$projectId = $this->params()->fromRoute('projectId');
		$curUserId = $this->params()->fromRoute('userId');

		if(isset($projectId) && $projectId!=0){
			$select = $sql->select();
			$select->from('Proj_ProjectMaster')
				->columns(array('ProjectId','ProjectName'))
                ->where(array("ProjectId"=>$projectId));
			$statement = $sql->getSqlStringForSqlObject($select);
			
			$this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
			$this->_view->projectId = $projectId;
			
		}

        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('UserId','EmployeeName'))
            ->where(array("DeleteFlag"=>0));
        $select->where('UserId IN (' . implode(',', $arrExecutiveIds) . ')');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->ControlUserList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(isset($curUserId) && $curUserId!=0) {
            $this->_view->cId=$curUserId;
            $arrExecutiveIds=array($curUserId);
            $this->_view->curUserId = $arrExecutiveIds;
        }

        $select = $sql->select();
		$select->from(array('a'=>'Crm_LeadProjects'))
			->join(array('b' => 'Crm_Leads'), 'a.LeadId=b.LeadId', array(), $select::JOIN_INNER)
			->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId=c.ProjectId', array(), $select::JOIN_INNER)
			->columns(array('ProjectId','ProjectName'=>new expression("c.ProjectName")))
			->where(array('a.DeleteFlag' => 0));
        $select->where('b.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
		$select->group(new expression("a.ProjectId,c.ProjectName"));
        $select->order("ProjectId desc");
        $statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postData = $request->getPost();
                    if($postData['mode']=="filter") {
                        $weekData="";
                        $chooseId = $this->bsf->isNullCheck($postData['choose'], 'number');
                        $projectId = $this->bsf->isNullCheck($postData['projectId'], 'number');

                        $subQuery = $sql->select();
                        $subQuery->from(array('a'=> 'Crm_Leads'))
                            ->join(array('b' => 'Crm_LeadProjects'), 'a.LeadId=b.LeadId', array('ProjectId'), $select::JOIN_INNER)
                            ->join(array('c' => 'Crm_LeadFollowup'), new Expression("a.LeadId=c.LeadId AND c.Completed = 0"), array('CallTypeId'), $select::JOIN_LEFT)
                            ->columns(array('*','TotalFollowups'=>new Expression("(select count(EntryId) from Crm_LeadFollowup where DeleteFlag = 0 AND LeadId = a.LeadId AND UserId = a.ExecutiveId)")))
                            ->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')')
                            ->where(array('a.DeleteFlag' => 0));
                        if(isset($projectId) && $projectId!=0){
                            $subQuery->where(array('b.ProjectId' => $projectId));
                        }
                        $select = $sql->select();
                        $select->from(array("g"=>$subQuery))
                            ->columns(array('LeadId'))
                            ->where("g.CallTypeID = Null OR g.CallTypeID != 3");
                        $select->group(new expression("g.LeadId"));
                       $totalLeadsQuery = $statement = $sql->getSqlStringForSqlObject($select);
                        $today = date('Y-m-d');
                        $arrWeekDays = array();
                        $weekLeadCount = array();
                        $weekFollowupCount = array();

                        if($chooseId==1) {
                            // Week status
                            // Previous 6 Days variation
                            for($i=6; $i>=0; $i--) {
                                $tDate = date('Y-m-d', strtotime($today . '-' . $i .'days'));

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_LeadFollowup'))
                                    ->columns(array('weekFollowup'=>new Expression("count(*)")))
                                    ->where(array('a.DeleteFlag' => 0, 'a.FollowUpDate' => $tDate));
                                //$select->where('a.LeadId IN (' .$totalLeadsQuery . ')');
                                $select->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $weekFollowup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array("g"=>$subQuery))
                                    ->columns(array('LeadId'))
                                    ->where(array('g.LeadDate' => $tDate));
                                $select->group(new expression("g.LeadId"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $weekLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $arrWeekDays[] = date('l', strtotime($tDate));
                                $weekLeadCount[] =  count($weekLead);
                                $weekFollowupCount[] = (int)$weekFollowup['weekFollowup'];
                            }
                        } else if($chooseId==2) {
                            for($i=29; $i>=0; $i--) {
                                $tDate = date('Y-m-d', strtotime($today . '-' . $i .'days'));

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_LeadFollowup'))
                                    ->columns(array('weekFollowup'=>new Expression("count(*)")))
                                    ->where(array('a.DeleteFlag' => 0, 'a.FollowUpDate' => $tDate));
                                //$select->where('a.LeadId IN (' .$totalLeadsQuery . ')');
                                $select->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $weekFollowup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array("g"=>$subQuery))
                                    ->columns(array('LeadId'))
                                    ->where(array('g.LeadDate' => $tDate));
                                $select->group(new expression("g.LeadId"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $weekLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $arrWeekDays[] = date('d/m', strtotime($tDate));
                                $weekLeadCount[] =  count($weekLead);
                                $weekFollowupCount[] = (int)$weekFollowup['weekFollowup'];
                            }
                        } else {
                            for($i=5; $i>=0; $i--) {
                                $tDate = date('n', strtotime($today . '-' . $i .'months'));

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_LeadFollowup'))
                                    ->columns(array('weekFollowup'=>new Expression("count(*)")))
                                    ->where(array('a.DeleteFlag' => 0));
                                $select->where->expression('MONTH(a.FollowUpDate) = ?',$tDate);
                                //$select->where('a.LeadId IN (' .$totalLeadsQuery . ')');
                                $select->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $weekFollowup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $select = $sql->select();
                                $select->from(array("g"=>$subQuery))
                                    ->columns(array('LeadId'));
                                $select->where->expression('MONTH(g.LeadDate) = ?',$tDate);
                                $select->group(new expression("g.LeadId"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $weekLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                $months = array (1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec');

                                $arrWeekDays[] = $months[(int)$tDate];

                                $weekLeadCount[] =  count($weekLead);
                                $weekFollowupCount[] = (int)$weekFollowup['weekFollowup'];
                            }
                        }
                        $weekData['arrWeekDays'] = json_encode($arrWeekDays);
                        $weekData['weekLeadCount'] = json_encode($weekLeadCount);
                        $weekData['weekFollowupCount'] = json_encode($weekFollowupCount);
                    }
                    //Write your Ajax post code here
                    $connection->commit();
                    $result =  "Success";
                    $this->_view->setTerminal(true);
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

                    //No Of Projects
                    $data['NoOfProjects'] = count($this->_view->projects);

                    $subQuery = $sql->select();
                    $subQuery->from(array('a'=> 'Crm_Leads'))
                        ->join(array('b' => 'Crm_LeadProjects'), 'a.LeadId=b.LeadId', array('ProjectId'), $select::JOIN_INNER)
                        ->join(array('c' => 'Crm_LeadFollowup'), new Expression("a.LeadId=c.LeadId AND c.Completed = 0"), array('CallTypeId'), $select::JOIN_LEFT)
                        ->columns(array('*','TotalFollowups'=>new Expression("(select count(EntryId) from Crm_LeadFollowup where DeleteFlag = 0 AND LeadId = a.LeadId AND UserId = a.ExecutiveId)")))
                        ->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')')
                        ->where(array('a.DeleteFlag' => 0));
                    if(isset($projectId) && $projectId!=0){
                        $subQuery->where(array('b.ProjectId' => $projectId));
                    }

                    //No Of Leads
                    $select = $sql->select();
                    $select->from(array("g"=>$subQuery))
                        ->columns(array('LeadId'))
                        ->where("g.CallTypeID = Null OR g.CallTypeID != 3");
                    $select->group(new expression("g.LeadId"));
                    $totalLeadsQuery = $statement = $sql->getSqlStringForSqlObject($select);
                    $totalLeads = $dbAdapter->query($totalLeadsQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['NoOfLeads'] = count($totalLeads);

                    //Current Following Leads
                    $select = $sql->select();
                    $select->from(array("g"=>$subQuery))
                        ->columns(array('LeadId'))
                        ->where("g.CallTypeID != 3 AND g.TotalFollowups != 0");
                    $select->group(new expression("g.LeadId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $currentLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['CurLeadCnt'] = count($currentLeads);
                    $data['NewLeads'] = $data['NoOfLeads'] - $data['CurLeadCnt'];

                    //No of Units Blocked
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_unitBlock'))
                        ->columns(array('BlockId'))
                        ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag' => 0));
                    $select->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                    if(isset($projectId) && $projectId!=0){
                        $select->where(array('b.ProjectId' => $projectId));
                    }
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $blockedUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['blockedLeadCnt'] = count($blockedUnits);

                    //No of Units Finalised
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_LeadFollowup'))
                        ->columns(array('EntryId'))
                        ->join(array('b' => 'KF_UnitMaster'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag' => 0, 'a.CallTypeId' => 4));
                    $select->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                    if(isset($projectId) && $projectId!=0){
                        $select->where(array('b.ProjectId' => $projectId));
                    }
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $finalisedUnits = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['FinalizedLeadCnt'] = count($finalisedUnits);

                    //Pending Followups
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_LeadFollowup'))
                        ->columns(array('*'))
                        ->join(array('b' => 'Crm_Leads'), 'b.LeadId=a.LeadId', array('LeadId','LeadName','LeadConvert'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Crm_CallTypeMaster'), 'c.CallTypeId=a.NextFollowUpTypeId', array('CallTypeDec' => 'Description'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Crm_CallTypeMaster'), 'd.CallTypeId=a.CallTypeId', array('PrevCallTypeDec' => 'Description'), $select::JOIN_LEFT)
                        ->join(array('e' => 'WF_Users'), 'b.ExecutiveId=e.UserId', array('ExecuName' => 'EmployeeName', 'Projects' => new Expression("''")), $select::JOIN_LEFT)
                        ->join(array('f' => 'Crm_NatureMaster'), 'f.NatureId=a.NatureId', array('PrevCallNatureDec' => 'Description'), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag' => 0, 'a.Completed' => 0))
                        ->where("a.NextCallDate <= '".date('Y-m-d')."'" );
                    $select->where('a.LeadId IN (' .$totalLeadsQuery . ')');
                    $select->where('b.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $pendingFollowup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['UnFollowedLeadCnt'] = count($pendingFollowup);
                    $this->_view->pendingFollowup = $pendingFollowup;

                    // Week status
                    // Previous 6 Days variation
                    $today = date('Y-m-d')." 23:59:59";
                    $fromDate = date('Y-m-d')." 00:00:00";

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_LeadFollowup'))
                        ->join(array('b' => 'Crm_Leads'), 'b.LeadId=a.LeadId', array('LeadId','LeadName','LeadConvert'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Crm_CallTypeMaster'), 'c.CallTypeId=a.NextFollowUpTypeId', array('CallTypeDec' => 'Description'), $select::JOIN_LEFT)
                        ->join(array('d' => 'Crm_CallTypeMaster'), 'd.CallTypeId=a.CallTypeId', array('PrevCallTypeDec' => 'Description'), $select::JOIN_LEFT)
                        ->join(array('g' => 'Crm_NatureMaster'), 'g.NatureId=a.NatureId', array('PrevCallNatureDec' => 'Description'), $select::JOIN_LEFT)
						->join(array('e' => 'WF_Users'), 'b.ExecutiveId=e.UserId', array('ExecuName' => 'EmployeeName', 'Projects' => new Expression("''")), $select::JOIN_LEFT)
                        ->where(array('a.DeleteFlag' => 0));
                    $select->where->greaterThanOrEqualTo('a.NextCallDate', $fromDate)
                        ->lessThanOrEqualTo('a.NextCallDate', $today);
                    $select->where('b.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');


                    if(isset($projectId) && $projectId!=0){
                        $select->join(array('f' => 'Crm_LeadProjects'), 'f.LeadId=a.LeadId', array(), $select::JOIN_LEFT);
                        $select->where(array('f.ProjectId' => $projectId));
                        $select->order('Completed ASC');
                    }

                   $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrTodayLeadFollowups = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['TotTodayLeadFollowupCnt'] = count($arrTodayLeadFollowups);

                    $totSiteVisitCnt = 0;
                    $comSiteVisitCnt = 0;
                    $totFinalizedCnt = 0;
                    $comFinalizedCnt = 0;
                    $totCPCnt = 0;
                    $comCPCnt = 0;
                    $totOthersCnt = 0;
                    $comOthersCnt = 0;
                    $totCompletedLeadFollowupCnt = 0;

                    $arrClientPlaceFollowups = array();
                    $arrFinalizationFollowups = array();
                    $arrSiteVisitFollowups = array();
					$arrOtherFollowups = array();
					$icount = 0;
                    foreach($arrTodayLeadFollowups as $curLeadFollowUp) {
						$leadid = $curLeadFollowUp['LeadId'];
						
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
						$curLeadFollowUp['Projects']=$strCCName;

					
                        $isCompleted = FALSE;
                        if($curLeadFollowUp['Completed'] == 1) {
                            $totCompletedLeadFollowupCnt++;
                            $isCompleted = TRUE;
                        }

                        switch($curLeadFollowUp['NextFollowUpTypeId']) {






                            case '5':
                                // site visit
                                $totSiteVisitCnt++;
                                if($isCompleted) {
                                    $comSiteVisitCnt++;
                                }
                                $arrSiteVisitFollowups[] = $curLeadFollowUp;
                                break;
                            case '4':
                                // finalization
                                $totFinalizedCnt++;
                                if($isCompleted) {
                                    $comFinalizedCnt++;
                                }
                                $arrFinalizationFollowups[] = $curLeadFollowUp;
                                break;
                            case '1':
                                // client place
                                $totCPCnt++;
                                if($isCompleted) {
                                    $comCPCnt++;
                                }
                                $arrClientPlaceFollowups[] = $curLeadFollowUp;
                                break;
                            default:
                                $totOthersCnt++;
                                if($isCompleted) {
                                    $comOthersCnt++;
                                }
								$arrOtherFollowups[] = $curLeadFollowUp;
                                break;
                        }
						
						$icount=$icount+1;
                    }

                    $this->_view->arrTodayLeadFollowups = $arrTodayLeadFollowups;
                    $data['TodayCompletedLeadFollowupCnt'] = $totCompletedLeadFollowupCnt;
                    $data['TodayRemaingLeadFollowupCnt'] = count($arrTodayLeadFollowups) - $totCompletedLeadFollowupCnt;

                    if(!empty($arrSiteVisitFollowups)) {
                        $this->_view->arrSiteVisitFollowups = $arrSiteVisitFollowups;
                    }
                    if(!empty($arrFinalizationFollowups)) {
                        $this->_view->arrFinalizationFollowups = $arrFinalizationFollowups;
                    }
                    if(!empty($arrClientPlaceFollowups)) {
                        $this->_view->arrClientPlaceFollowups = $arrClientPlaceFollowups;
                    }
					if(!empty($arrOtherFollowups)) {
                        $this->_view->arrOtherFollowups = $arrOtherFollowups;
                    }

                    // Client Place count
                    $data['TotCPCnt'] = $totCPCnt;
                    if($comCPCnt != 0 && $totCPCnt != 0) {
                        $data[ 'ComCPPercentage' ] = round(($comCPCnt / $totCPCnt) * 100);
                    } else {
                        $data[ 'ComCPPercentage' ] = 0;
                    }

                    // Visit count
                    $data['TotSiteVisitCnt'] = $totSiteVisitCnt;
                    if($comSiteVisitCnt != 0 && $totSiteVisitCnt != 0) {
                        $data[ 'ComSiteVisitPercentage' ] = round(($comSiteVisitCnt / $totSiteVisitCnt) * 100);
                    } else {
                        $data[ 'ComSiteVisitPercentage' ] = 0;
                    }

                    // Finalized count
                    $data['TotFinalizedCnt'] = $totFinalizedCnt;
                    if($comFinalizedCnt != 0 && $totFinalizedCnt != 0) {
                        $data[ 'ComFinalizedPercentage' ] = round(($comFinalizedCnt / $totFinalizedCnt) * 100);
                    } else {
                        $data[ 'ComFinalizedPercentage' ] = 0;
                    }

                    // Others count
                    $data['TotOthersCnt'] = $totOthersCnt;
                    if($comOthersCnt != 0 && $totOthersCnt != 0) {
                        $data[ 'ComOthersPercentage' ] = round(($comOthersCnt / $totOthersCnt) * 100);
                    } else {
                        $data[ 'ComOthersPercentage' ] = 0;
                    }

                    // Current Month Lead
                    //Warm Leads
                    $select = $sql->select();
                    $select->from(array("g"=>$subQuery))
                        ->columns(array('LeadId'))
                        ->where("g.StatusId = 2");
                    $select->group(new expression("g.LeadId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $warmLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['warmCase'] = count($warmLeads);

                    //Cold Leads
                    $select = $sql->select();
                    $select->from(array("g"=>$subQuery))
                        ->columns(array('LeadId'))
                        ->where("g.StatusId = 3");
                    $select->group(new expression("g.LeadId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $coldLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['coldCase'] = count($coldLeads);

                    //Hot Leads
                    $select = $sql->select();
                    $select->from(array("g"=>$subQuery))
                        ->columns(array('LeadId'))
                        ->where("g.StatusId = 1");
                    $select->group(new expression("g.LeadId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $hotLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data['hotCase'] = count($hotLeads);

                    // Previous 6 Monthly sale variation
                    $arrMonthVariation = array();
                    for($i=1; $i>=0; $i--) {
                       // echo $i;
                        $tDate = date('Y-m-d', strtotime($today . '-' . $i .'month'));

                        $tMonth = date('M', strtotime($tDate));
                        $tMonthNo = date('m', strtotime($tDate));
                        $tYear = date('Y', strtotime($tDate));
                        $cDate = $tYear."-".$tMonthNo."-01";

                        // Target
                        $select = $sql->select()
                            ->from(array('a' => 'Crm_TargetTrans'))
                            ->columns(array('MonthTarget' => new Expression("SUM(CASE b.TargetPeriod  WHEN 1 THEN a.TValue/1
                                WHEN 2 THEN a.TValue/2
                                WHEN 3 THEN a.TValue/3
                                WHEN 4 THEN a.TValue/6
                                WHEN 5 THEN a.TValue/12
                                END)")))
                            ->join(array('b' => 'Crm_TargetRegister'), 'b.TargetId=a.TargetId', array(), $select::JOIN_LEFT)
                            ->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')')
                            ->where(array('b.DeleteFlag' => 0))
                            ->where("'".$cDate."' >= a.FromDate AND '".$cDate."' <= a.ToDate");
                            if(isset($projectId) && $projectId!=0){
                                $select->where(array('b.ProjectId' => $projectId));
                            }
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $tarMonthTrans = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        // Acheived
                        $select = $sql->select();
                        $select->from(array('b' => 'Crm_UnitDetails'))
                            ->columns(array('AchAmount' => new Expression('SUM(GrossAmount)')))
                            ->join(array( 'a' => 'Crm_UnitBooking'), 'b.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
							->join(array( 'c' => 'kf_unitMaster'), 'b.UnitId=c.UnitId', array(), $select::JOIN_LEFT);
                        $select->where('a.ExecutiveId IN (' . implode(',', $arrExecutiveIds) . ')');
                        $select->where(array('a.DeleteFlag' => 0));
                        $select->where->expression('MONTH(BookingDate) = ?', array($tMonthNo));
                        $select->where->expression('YEAR(BookingDate) = ?', array($tYear));

                        if(isset($projectId) && $projectId!=0){
							$select->where(array('c.ProjectId' => $projectId));
						}

                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $achMonthTrans = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $monthText = $tMonth . ', '. $tYear;
                        $arrMonthVariation[(1 - $i)] = array(
                            'Month' => $monthText,
                            'Target' => $tarMonthTrans['MonthTarget'],
                            'Sold' => $achMonthTrans['AchAmount']
                        );
                    }

                   $data['JsonMonthVariationData'] = json_encode($arrMonthVariation);

					//Pre-Sale Start
                    //selecting values for Executive
                    $PositionTypeId=array(5,2);
                    $sub = $sql->select();
                    $sub->from(array('a'=>'WF_PositionMaster'))
                        ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                        ->columns(array('PositionId'))
                        ->where(array("b.PositionTypeId"=>$PositionTypeId));

					$select = $sql->select();
					$select->from("WF_Users")
						->columns(array('UserId','EmployeeName'))
                        ->where->expression("PositionId IN ?",array($sub));
                    $select->where("DeleteFlag='0'");
					$statement = $sql->getSqlStringForSqlObject($select);
					$execList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
					
						
					$arrUnitLists= array();			
					foreach($execList as &$execLists) {
						$UserId=$execLists['UserId'];
						$iLeadCount=0;
						$iDayCount=0;
					
						$select = $sql->select(); 
						$select->from(array("a"=>"Crm_UnitBooking"))
							->columns(array('LeadId' => new Expression("Distinct a.LeadId") ));
						$select->where("a.DeleteFlag='0' and a.ExecutiveId=$UserId ");						
						$statement = $sql->getSqlStringForSqlObject($select);
						$leadList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($leadList as &$leadLists) {
							$LeadId = $leadLists['LeadId'];
							
							$iLeadCount = $iLeadCount + 1;
							
							$selectFinalizations = $sql->select(); 
							$selectFinalizations->from(array("a"=>"Crm_UnitBooking"))
								->columns(array('LeadId','FromDate' => new Expression("b.CreatedDate"), 'BookingDate','Days' => new Expression("DATEDIFF(day,b.CreatedDate,a.BookingDate)")))
								->join(array( "b" => "Crm_Leads"), 'a.LeadId=b.LeadId', array(), $select::JOIN_INNER);
							$selectFinalizations->where("a.DeleteFlag='0' and a.ExecutiveId='$UserId' and a.LeadId='$LeadId' ");
							$selectFinalizations->order('a.BookingDate ASC');
							$statement = $sql->getSqlStringForSqlObject($selectFinalizations);
							$unitdet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

							$Days = $unitdet['Days'];
							$iDayCount = $iDayCount + $Days;
						}
						
						$finalDays = 0;
						if($iLeadCount != 0 && $iDayCount != 0){
							$finalDays = $iDayCount / $iLeadCount;
						}
						/*echo $iLeadCount;
						echo ",";
						echo $iDayCount;
						echo ",";
						echo $finalDays;
						echo "----------------";
						*/
						$dumArr=array();
						$dumArr = array(
							//'UserId' => $execLists['UserId'],
							'ExecutiveName' => $execLists['EmployeeName'],
							'Days' => $finalDays
						);
						$arrUnitLists[] =$dumArr;
					}

					$this->_view->arrUnitLists = $arrUnitLists;
                    $this->_view->data = $data;
					//Pre-Sale End
//                    $mailData = array(
//                            'LEADNAME' => 'balaji',
//                            'STAGENAME' => 'dob',
//                            'UNITNAME' => '3bhk'
//                        );
//
//                    $res = $viewRenderer->sendInBlue()->sendMailTo( '1', 'nepoleon.gualtiero@gmail.com', $mailData );
//var_dump($res); die;
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
	
	public function dateDiff($start, $end) {
		  $start_ts = strtotime($start);
		  $end_ts = strtotime($end);
		  $diff = $end_ts - $start_ts;
		  return round($diff / 86400);
	}

	public function extraitemsAction(){
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
		$select->from(array("a" => "Crm_UnitBooking"))
			->join(array("d" => "KF_UnitMaster"), "a.UnitId=d.UnitId", array('ProjectId'), $select::JOIN_INNER)
			->join(array("b" => "Crm_Leads"), "a.LeadId=b.LeadId", array(), $select::JOIN_INNER)
			->join(array("c" => "Proj_ProjectMaster"), "d.ProjectId=c.ProjectId", array(), $select::JOIN_INNER)
			->columns(array('data' => 'UnitId', 'value' => new Expression("c.ProjectName + ' : ' + d.UnitNo + ' ('+b.LeadName + ')'")));
		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->unitList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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

            $connection = $dbAdapter->getDriver()->getConnection();
			$connection->beginTransaction();
			try {

                if ($request->isPost()) {
                    //Write your Normal form post code here
                    $postData = $request->getPost();

                    foreach($postData as $key => $data) {
                        if(preg_match('/^extraItemId_[\d]+$/', $key)) {

                            preg_match_all('/^extraItemId_([\d]+)$/', $key, $arrMatches);
                            $id = $arrMatches[1][0];

                            $extraItemId = $this->bsf->isNullCheck($postData['extraItemId_' . $id], 'number');
                            if($extraItemId <= 0) {
                                continue;
                            }

                            $unitExtraItemTrans = array(
                                'ExtraItemId' => $extraItemId,
                                'UnitId' => $postData['unitId'],
                                'Amount' => $this->bsf->isNullCheck($postData['transAmount_' . $id], 'number'),
                                'Rate' => $this->bsf->isNullCheck($postData['transRate_' . $id], 'number'),
                                'Quantity' => $this->bsf->isNullCheck($postData['transQuantity_' . $id], 'number')
                            );

                            $insert = $sql->insert('Crm_UnitExtraItemTrans');
                            $insert->values($unitExtraItemTrans);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
				}

				$connection->commit();
			} catch(PDOException $e){
				$connection->rollback();
				print "Error!: " . $e->getMessage() . "</br>";
			}

            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
			
			//Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
	 // AJAX Request
    public function extraitemlistAction() {
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

                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number' );
                    $UnitId = $this->bsf->isNullCheck($this->params()->fromPost('UnitId'), 'number' );
                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }if($UnitId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }
					$subQuery = $sql->select();
					$subQuery->from(array("e" => "Crm_UnitExtraItemTrans"))
							->columns(array('ExtraItemId' => 'ExtraItemId'))
							 ->where(array('e.UnitId'=>$UnitId));
                    // extra item list
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_ExtraItemMaster"))
                        ->columns(array('Rate'=>'Rate','Amount'=>'Amount','Quantity'=>'Qty','data' => 'ExtraItemId', 'value' => 'ItemDescription','Code'=>'Code'))
                        ->where(array('a.ProjectId'=>$ProjectId))
						->where->expression('ExtraItemId Not IN ?', array($subQuery));
					$statement = $sql->getSqlStringForSqlObject($select);
                    $arrExtraItemList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result =  json_encode(array('extra_item_list' => $arrExtraItemList));
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

	public function crmdashboardAction() {

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

        
		//UnitBooking
		
        
		$select = $sql->select();
        $select->from(array('a' =>'Crm_UnitBooking'))
				->columns(array('BookingCount' => new expression('count(*)')));
		if(isset($projectId)){
			$select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
			$select-> where("b.ProjectId=$projectId ");
		}
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->BookingCount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//Blocked
		
		$select = $sql->select();
        $select->from(array('a' =>'Crm_UnitBlock'))
				->columns(array('BlockedCount' => new expression('count(*)')));
				if(isset($projectId)){
					$select->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
					$select->where(array('b.ProjectId' => $projectId));
				}
		//$select->where(array('a.CallTypeId' => 2));
				
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->BlockedCount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		// hotcase
		
		$select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
				->columns(array('HotCount' => new expression('count(*)')));
				if(isset($projectId)){
					$select->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
					$select->where(array('b.ProjectId' => $projectId));
				}
				$select->where(array('a.StatusId' => 1));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->HotCount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//drop count

		$select = $sql->select();
        $select->from(array('a' =>'Crm_LeadFollowup'))
				->columns(array('DropCount' => new expression('count(*)')));
				if(isset($projectId)){
					$select->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
					$select->where(array('b.ProjectId' => $projectId));
				}
				$select->where(array('a.CallTypeId' => 3));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->DropCount = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//receivable 
		
		$select = $sql->select();
        $select->from(array('a' =>'Crm_ProgressBillTrans'))
				->columns(array('Receivable' => new expression('sum(NetAmount) - sum(PaidAmount)'),'NetAmount'=>new expression('sum(NetAmount)'),'PaidAmount'=>new expression('sum(PaidAmount)')));
        $select-> where("a.CancelId=0");
        if(isset($projectId)){
			$select -> join(array("b" => "Crm_ProgressBill"), "a.ProgressBillId=b.ProgressBillId", array(), $select::JOIN_INNER);
			$select-> where("b.ProjectId=$projectId");
		}
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->Receivable = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		//print_r($this->_view->Receivable);
		if($this->_view->Receivable['NetAmount'] && $this->_view->Receivable['PaidAmount'] ){
			$ReceivedPer = ($this->_view->Receivable['PaidAmount'] / $this->_view->Receivable['NetAmount']) * 100;
			$ReceivablePer = 100 - $ReceivedPer;
			$this->_view->rcvdPer = number_format($ReceivedPer,1);
			$this->_view->rcvblPer = number_format($ReceivablePer,1);
		} else {
			$this->_view->rcvdPer = 0;
			$this->_view->rcvblPer = 100;
		}
		
		
		//received  
		
		$select = $sql->select();
        $select->from(array('a' =>'Crm_ReceiptRegister'))
				->columns(array('Received' => new expression('sum(Amount)')))
				->where(array('a.ReceiptAgainst' => 'B'));
				if(isset($projectId)){
					$select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
					$select-> where("b.ProjectId=$projectId ");
				}
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->Received = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//hotcase vs dropcase
		
		$subQry1 = $sql->select();
        $subQry1->from(array('a' =>'Crm_LeadFollowup'));
				if(isset($projectId)){
					$subQry1->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
					$subQry1->where(array('b.ProjectId' => $projectId));
				}
				$subQry1->columns(array('FollowUpDate','rank' => new expression('row_number() over(partition by FollowUpDate order by EntryId asc)')))
				->where("a.StatusId=3 and a.FollowUpDate between (Convert(nvarchar(12),DATEADD(day, -30, GETDATE()), 113)) and (Convert(nvarchar(12), GETDATE(), 113))");
		//$stmt = $sql->getSqlStringForSqlObject($subQry1);
		
		 $select = $sql->select();
                    $select->from(array("g"=>$subQry1))
                            ->columns(array('FollowUpDate',
                              'HotCount' => new Expression('count(rank)'),
                              'DropCount' => new Expression('1-1')))
                            ->group(array('g.FollowUpDate'));
							
							
		$subQry2 = $sql->select();
        $subQry2->from(array('a' =>'Crm_LeadFollowup'));
		if(isset($projectId)){
					$subQry2->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
					$subQry2->where(array('b.ProjectId' => $projectId));
				}
				$subQry2->columns(array('FollowUpDate','rank' => new expression('row_number() over(partition by FollowUpDate order by EntryId asc)')))
					->where("a.CallTypeId=3 and a.FollowUpDate between (Convert(nvarchar(12),DATEADD(day, -30, GETDATE()), 113)) and (Convert(nvarchar(12), GETDATE(), 113))");
		
		 $select2 = $sql->select();
                    $select2->from(array("g"=>$subQry2))
                            ->columns(array('FollowUpDate',
                              'HotCount' => new Expression('1-1'),
                              'DropCount' => new Expression('count(rank)')))
                            ->group(array('g.FollowUpDate'));
		$select2->combine($select,'Union ALL');
		
		 $select3 = $sql->select();
                    $select3->from(array("h"=>$select2))
                            ->columns(array('FollowUpDate' => new Expression("CAST(DATEPART(dd,h.FollowUpDate) AS VARCHAR(10) ) +'-'+ CAST(LEFT(DATENAME(MONTH,h.FollowUpDate),3)  AS VARCHAR(10) )"),
                              'HotCount' => new Expression('Sum(h.HotCount)'),
                              'DropCount' => new Expression('Sum(h.DropCount)')))
                            ->group(array('h.FollowUpDate'));
		
		$stmt = $sql->getSqlStringForSqlObject($select3);
		$arrHvc= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//booking units over month
		$select = $sql->select();
        $select->from(array('a' =>'Crm_UnitBooking'));
				if(isset($projectId)){
					$select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
					$select-> where("b.ProjectId=$projectId ");
				}
				$select->columns(array('Booking' => new expression('count(a.BookingId)'),'BookingDate' => new expression('a.BookingDate')))
				->where("BookingDate between (Convert(nvarchar(12),DATEADD(day, -30, GETDATE()), 113)) and (Convert(nvarchar(12), GETDATE(), 113))")
				 ->group(array('a.BookingDate'));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$arrBooking = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		// project Status
		
		//total units
		$select = $sql->select();
        $select->from(array('a' =>'KF_UnitMaster'))
				->columns(array('TotalUnits' => new expression('count(*)')));
				if(isset($projectId)){
					$select-> where("a.ProjectId=$projectId ");
				}
		$stmt = $sql->getSqlStringForSqlObject($select);
		$TotalUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		
		//total booked units
		
		$select = $sql->select();
        $select->from(array('a' =>'Crm_UnitBooking'));
				if(isset($projectId)){
					$select -> join(array("b" => "KF_UnitMaster"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER);
					$select-> where("b.ProjectId=$projectId ");
				}
				$select->columns(array('BookingUnits' => new expression('count(*)')));				
		$stmt = $sql->getSqlStringForSqlObject($select);
		$BookingUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

		$this->_view->RemaingUnits = $TotalUnits['TotalUnits'] - $BookingUnits['BookingUnits'];
		$this->_view->BookingUnits = $BookingUnits['BookingUnits'];
		
		
		//exe by New Lead
		$startDate = date('1-M-Y');
		$endDate = date('d-M-Y');
		$select = $sql->select();
		$select->from(array('a' =>'Crm_leads'))
				->join(array("b" => "WF_Users"), "a.UserId=b.UserId", array('EmployeeName','Photo'), $select::JOIN_INNER)
				->columns(array('userId','LeadCount' => new expression('count(a.LeadId)')))
				->where("a.LeadDate between '$startDate' and '$endDate' ")
				->group(array('a.UserId','b.EmployeeName','b.Photo'));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->NewLeadArr = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		//most activity
		
		$select = $sql->select();
		$select->from(array('a' =>'Crm_LeadFollowup'))
				->join(array("b" => "WF_Users"), "a.ExecutiveId=b.UserId", array('EmployeeName','Photo'), $select::JOIN_INNER)
				->columns(array('ExecutiveId','ActivityCount' => new expression('count(a.EntryId)')))
				->where("a.FollowUpDate between '$startDate' and '$endDate' ")
				->group(array('a.ExecutiveId','b.EmployeeName','b.Photo'));
		$stmt = $sql->getSqlStringForSqlObject($select);
		$this->_view->ActivityCountArr = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		//campian
		
		$subQry1 = $sql->select();
        $subQry1->from(array('a' =>'Crm_CampaignRegister'))
				->join(array("b" => "Crm_OpportunityMaster"), "a.OpportunityId=b.OpportunityId", array(), $select::JOIN_LEFT)
				->columns(array('Sales'=>new expression("1-1"), 'OpportunityName'=>new expression("b.OpportunityName"),'CampaignCost'));
		
		$subQry2 = $sql->select();
        $subQry2->from(array('a' =>'Crm_UnitDetails'))
			->join(array("b" => "Crm_UnitBooking"), "a.UnitId=b.UnitId", array(), $select::JOIN_INNER)
			->join(array("c" => "Crm_LeadSource"), "c.LeadId=b.LeadId", array(), $select::JOIN_INNER)
            ->join(array('d' => 'Crm_CampaignRegister'), new Expression("c.LeadSourceId=d.CampaignId and c.Name='C'"), array(), $select::JOIN_INNER)
			->join(array("e" => "Crm_OpportunityMaster"), "e.OpportunityId=d.OpportunityId", array(), $select::JOIN_INNER)
				->columns(array('Sales'=>new expression("BaseAmt"),'OpportunityName' => new expression("e.OpportunityName"),'CampaignCost'=>new expression("1-1")));
				//->where("a.CallTypeId=3 and a.FollowUpDate between (Convert(nvarchar(12),DATEADD(day, -30, GETDATE()), 113)) and (Convert(nvarchar(12), GETDATE(), 113))");
		$subQry2->combine($subQry1,'Union ALL');
		
		$select3 = $sql->select();
		$select3->from(array("g"=>$subQry2))
				->columns(array('OpportunityName',
				  'CampaignCost' => new Expression('Sum(g.CampaignCost)'),
				  'Sales' => new Expression('Sum(g.Sales)')))
				->group(array('g.OpportunityName'));
		
		$stmt = $sql->getSqlStringForSqlObject($select3);
		$arrCamp= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		// win vs loss
		
		$subQry1 = $sql->select();
        $subQry1->from(array('a' =>'Crm_LeadFollowup'));
		if(isset($projectId)){
					$subQry1->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
					$subQry1->where(array('b.ProjectId' => $projectId));
				}
			$subQry1->columns(array('FollowUpDate','rank' => new expression('row_number() over(partition by FollowUpDate order by EntryId asc)')))
				->where("a.CallTypeId=4 and a.Completed=1 and a.FollowUpDate between (Convert(nvarchar(12),DATEADD(day, -30, GETDATE()), 113)) and (Convert(nvarchar(12), GETDATE(), 113))");
		 $stmt = $sql->getSqlStringForSqlObject($subQry1);
		
		 $select = $sql->select();
                    $select->from(array("g"=>$subQry1))
                            ->columns(array('FollowUpDate',
                              'HotCount' => new Expression('count(rank)'),
                              'DropCount' => new Expression('1-1')))
                            ->group(array('g.FollowUpDate'));
							
							
		$subQry2 = $sql->select();
        $subQry2->from(array('a' =>'Crm_LeadFollowup'));
				if(isset($projectId)){
					$subQry2->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
					$subQry2->where(array('b.ProjectId' => $projectId));
				}
			$subQry2->columns(array('FollowUpDate','rank' => new expression('row_number() over(partition by FollowUpDate order by EntryId asc)')))
				->where("a.CallTypeId=3 and a.FollowUpDate between (Convert(nvarchar(12),DATEADD(day, -30, GETDATE()), 113)) and (Convert(nvarchar(12), GETDATE(), 113))");
		
		 $select2 = $sql->select();
                    $select2->from(array("g"=>$subQry2))
                            ->columns(array('FollowUpDate',
                              'HotCount' => new Expression('1-1'),
                              'DropCount' => new Expression('count(rank)')))
                            ->group(array('g.FollowUpDate'));
		$select2->combine($select,'Union ALL');
		
		 $select3 = $sql->select();
                    $select3->from(array("h"=>$select2))
                            ->columns(array('FollowUpDate',
                              'HotCount' => new Expression('Sum(h.HotCount)'),
                              'DropCount' => new Expression('Sum(h.DropCount)')))
                            ->group(array('h.FollowUpDate'));
		
		$stmt = $sql->getSqlStringForSqlObject($select3);
		$arrWvl= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		
		
		
		$arrdate = array();
		$arrFinalCount = array();
		$arrDropCount = array();
		foreach($arrWvl as $wvl){
			$arrdate[] = date('d-M',strtotime($wvl['FollowUpDate']));
			$arrFinalCount[] = (int)$wvl['HotCount'];
			$arrDropCount[] = (int)$wvl['DropCount'];
		}
		
		$arrCat = array();
		$arrInvs = array();
		$arrRet = array();
		foreach($arrCamp as $Camp){
			$arrCat[] = $Camp['OpportunityName'];
			$arrInvs[] = (int)$Camp['CampaignCost'];
			$arrRet[] = (int)$Camp['Sales'];
		}
		
		$this->_view->jsonarrHvc = json_encode($arrHvc);
		$this->_view->jsonarrWvlDate = json_encode($arrdate);
		$this->_view->jsonarrWvlFinal = json_encode($arrFinalCount);
		$this->_view->jsonarrWvlDrop = json_encode($arrDropCount);
	
		$this->_view->jsonarrCampCat = json_encode($arrCat);
		$this->_view->jsonarrCampInvs = json_encode($arrInvs);
		$this->_view->jsonarrCampRet = json_encode($arrRet);
		
		
		$this->_view->jsonBooking = json_encode($arrBooking);
		
		
		
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

                    // activities by day wise
                    $today = date('Y-m-d');
                    $arrLeadFollowups = array();
                    for($i=30;$i>=0;$i--) {
                        $curDate = date('Y-m-d', strtotime($today . '-' . $i .'days'));

                        $select = $sql->select();
                        $select->from(array('a' =>'Crm_LeadFollowup'))
                            ->where(array('a.DeleteFlag' => 0, 'a.Completed' => 1))
                            ->where->expression('CAST(CONVERT(CHAR(10), [CompletedDate], 102) AS DATE) = ?', array($curDate));
                        if(isset($projectId)){
							$select->join(array('b'=>'Crm_LeadProjects'), 'b.LeadId=a.LeadId',array(),$select::JOIN_LEFT);
                            $select->where(array('b.ProjectId' => $projectId));
                        }
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrLeadFollowups[$curDate] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    $arrDayWiseActivity = array();

                    foreach($arrLeadFollowups as $key => $leadFollowup) {

                        $dayWiseActivity = array(
                            'date' => $key,
                            'followupCnt' => 0,
                            'blockCnt' => 0,
                            'dropCnt' => 0,
                            'finalizationCnt' => 0,
                            'siteVisitCnt' => 0,
                            'clientPlaceCnt' => 0
                        );

                        foreach($leadFollowup as $followup) {
                            switch ( $followup[ 'NextFollowUpTypeId' ] ) {
                                case 1:
                                    //Followup
                                    $dayWiseActivity[ 'followupCnt' ]++;
                                    break;
                                case 2:
                                    //Block
                                    $dayWiseActivity[ 'blockCnt' ]++;
                                    break;
                                case 3:
                                    //Drop
                                    $dayWiseActivity[ 'dropCnt' ]++;
                                    break;
                                case 4:
                                    //Finalization
                                    $dayWiseActivity[ 'finalizationCnt' ]++;
                                    break;
                                case 5:
                                    //SiteVisit
                                    $dayWiseActivity[ 'siteVisitCnt' ]++;
                                    break;
                                case 6:
                                    //Client Place
                                    $dayWiseActivity[ 'clientPlaceCnt' ]++;
                                    break;
                            }
                        }
                        $arrDayWiseActivity[] = $dayWiseActivity;
                    }
                    $this->_view->jsonDayWiseActivity = json_encode($arrDayWiseActivity);

                    // Executive position
                    $curMonth = date('m');
                    $curYear = date('Y');

                    $subQuery = $sql->select();
                    $subQuery->from(array('a' =>'Crm_UnitBooking '))
                        ->columns(array('ExecutiveId', 'Rank' => new Expression('RANK() over(partition by ExecutiveId order by BookingId asc)')))
                        ->join(array('b' => 'KF_UnitMaster '), 'a.UnitId=b.UnitId', array(), $subQuery::JOIN_INNER)
                        ->join(array('c' => 'Crm_UnitDetails  '), 'b.UnitId=c.UnitId', array('GrossAmount'), $subQuery::JOIN_INNER)
                        ->where(array('a.DeleteFlag' => 0))
                       // ->where->expression('MONTH(a.BookingDate) = ?', array($curMonth));
                    ->where("a.BookingDate between '$startDate' and '$endDate' ");
                    //$subQuery->where->expression('YEAR(a.BookingDate) = ?', array($curYear));
                    if(isset($projectId)){
                        $subQuery->where(array('b.ProjectId' => $projectId));
                    }

                    $select = $sql->select();
                    $select->from(array("g"=>$subQuery))
                            ->columns(array('ExecutiveId',
                              'SoldUnitsCnt' => new Expression('count(g.Rank)'),
                              'SalesAmt' => new Expression('sum(GrossAmount)')))
                            ->join(array('c' => 'WF_Users'), 'c.UserId=g.ExecutiveId', array('ExecutiveName' => 'EmployeeName', 'Photo'), $select::JOIN_LEFT)
                            ->group(array('g.ExecutiveId','c.EmployeeName', 'c.Photo'));

                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrExecutivesPos = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrExecutivePos = $arrExecutivesPos;

                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
}