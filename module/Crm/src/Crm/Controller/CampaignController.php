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

class CampaignController extends AbstractActionController
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
		$userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);
		if($this->getRequest()->isXmlHttpRequest())	{

            $request = $this->getRequest();
            if ($request->isPost()) {
                $number = $this->bsf->isNullCheck($this->params()->fromPost('numbermob'), 'number' );
                $frmdt = $this->bsf->isNullCheck($this->params()->fromPost('startdt'), 'string' );
                $enddt = $this->bsf->isNullCheck($this->params()->fromPost('endedt'), 'string' );

                $fromDate=date('Y-m-d', strtotime($frmdt));
                $toDate=date('Y-m-d', strtotime($enddt));

                $select = $sql->select();
                $select->from(array("a"=>"Crm_CampaignRegister"))
                    ->columns(array("CampaignId"))
                      ->where("StartDate >= '$fromDate' and EndDate<='$toDate' and CampaignCallNo='$number'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $campaign = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(count($campaign) > 0){

                    $result =  "failed";
                }
               else if(count($campaign)== 0){
                    $result="success";
                }
                else{
                    $result="";
                }
                 $this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent($result);

				return $response;
			}
		} else {
			$request = $this->getRequest();


			if ($request->isPost()) {
				//Write your Normal form post code here
                $postData = $request->getPost();

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                $sql = new Sql($dbAdapter);

                try {
                    $campaignid = $this->bsf->isNullCheck($postData['campaignid'], 'number');
                    $CampaignNo = $this->bsf->isNullCheck($postData['campaignNo'], 'string');
                    $CampaignDate = $this->bsf->isNullCheck($postData['campaigndate'], 'string');
                    $CampaignName = $this->bsf->isNullCheck($postData['campaignName'], 'string');
                    //$periority = $this->bsf->isNullCheck($postData['periority'], 'string');
                    $periority = 'H';
                    $OpportunityId = $postData['categoryId'];
					$OpportunityName = $this->bsf->isNullCheck( $postData['categoryName'], 'string' );
                    $budget = $this->bsf->isNullCheck($postData['budget'], 'number');

                    $externalAgency = $this->bsf->isNullCheck($postData['externalAgency'], 'string');
                    $iexternal=0;
                    if ($externalAgency=='Y') $iexternal=1;
                    $sourceName = $this->bsf->isNullCheck($postData['sourceName'], 'string');
                    $serviceProviderName= $this->bsf->isNullCheck($postData['serviceProviderName'], 'string');
                    $multiTimes= $this->bsf->isNullCheck($postData['multiTimes'], 'string');
                    $imulti=0;
                    if ($multiTimes=='Y') $imulti=1;

                    $noOfTimes= $this->bsf->isNullCheck($postData['noOfTimes'], 'number');
                    $timesPer= $this->bsf->isNullCheck($postData['timesPer'], 'string');
                    $StartDate = $this->bsf->isNullCheck($postData['startDate'], 'string');
                    $EndDate = $this->bsf->isNullCheck($postData['endDate'], 'string');
                    $Income = $this->bsf->isNullCheck($postData['income'], 'number');
                    $lead= $this->bsf->isNullCheck($postData['lead'], 'number');
                    $callno=$this->bsf->isNullCheck($postData['callno'], 'number');
                    $ContactPerson = $this->bsf->isNullCheck($postData['ContactPerson'], 'string');
                    $ContactNo = $this->bsf->isNullCheck($postData['ContactNo'], 'number');

                    $discountRequired=$this->bsf->isNullCheck($postData['discountRequired'], 'string');
                    $idiscount=0;
                    if ($discountRequired=='Y') $idiscount=1;
                    $discountType =$this->bsf->isNullCheck($postData['discount_type'], 'string');
                    $discountrate=$this->bsf->isNullCheck($postData['discountrate'], 'number');
                    $discountAmount=$this->bsf->isNullCheck($postData['discountAmount'], 'number');
                    $discountFrom=$this->bsf->isNullCheck($postData['discountFrom'], 'string');;
                    $discountTo=$this->bsf->isNullCheck($postData['discountTo'], 'string');



                    //$ContactPerson = $this->bsf->isNullCheck($postData['contactPerson'], 'string');
                    //$ContactNo = $this->bsf->isNullCheck($postData['contactNo'], 'string');
                    $remarks = $this->bsf->isNullCheck($postData['remarks'], 'string');
                    //$Status = $this->bsf->isNullCheck($postData['status'], 'string');
                    $Status= 'A';
                    $sMode= 'Add';
					if ($postData['categoryId'] == 'new' || $postData['categoryId'] == '0' ) {
						$insert = $sql->insert();
						$insert->into( 'Crm_OpportunityMaster' );
						$insert->Values( array( 'OpportunityName' => $OpportunityName));
						$statement = $sql->getSqlStringForSqlObject( $insert );
						$dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE );
						$OpportunityId = $dbAdapter->getDriver()->getLastGeneratedValue();
					}
					
                    if ($campaignid ==0) {
                        $sVno= $postData['campaignno'];

                        $aVNo = CommonHelper::getVoucherNo(806, date('Y-m-d', strtotime($postData['campaigndate'])), 0, 0, $dbAdapter, "I");
                        if ($aVNo["genType"] == true) $sVno = $aVNo["voucherNo"];

                        $insert = $sql->insert();
                        $insert->into('Crm_CampaignRegister');
                        $insert->Values(array('CampaignNo' => $sVno, 'CampaignDate' => date('Y-m-d', strtotime($CampaignDate))
                        , 'CampaignName' => $CampaignName,'OpportunityId' => $OpportunityId,'Status' => $Status,'StartDate' => date('Y-m-d', strtotime($StartDate)),'EndDate' => date('Y-m-d', strtotime($EndDate))
                        , 'Periority' => $periority,'ContactPerson'=>$ContactPerson,'ContactNo'=>$ContactNo,
                            'ExternalAgency'=>$iexternal,'SourceName'=> $sourceName,'ServiceProviderName'=>$serviceProviderName
                        , 'MultiTimes' =>$imulti,'NoOfTimes'=>$noOfTimes,'TimesPer' =>$timesPer,'ExpectedLeads' => $lead
                        , 'Remarks'=>$remarks,'ExpectedIncome' => $Income,'CampaignCallNo'=> $callno, 'CampaignCost'=> $budget
                        ,'DiscountRequired'=>$idiscount,'DiscountType'=>$discountType
                        ,'DiscountRate'=>$discountrate,'DiscountAmount'=>$discountAmount
                        ,'DiscountFrom'=>date('Y-m-d', strtotime($discountFrom)),'DiscountTo'=>date('Y-m-d', strtotime($discountTo))));
                        $statement = $sql->getSqlStringForSqlObject($insert);


                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $campaignid = $dbAdapter->getDriver()->getLastGeneratedValue();
                    } else {
                        $sMode= 'Edit';
                        $update = $sql->update();
                        $update->table('Crm_CampaignRegister');
                        $update->set(array('CampaignNo' => $CampaignNo, 'CampaignDate' => date('Y-m-d', strtotime($CampaignDate))
                        , 'CampaignName' => $CampaignName,'OpportunityId' => $OpportunityId,'Status' => $Status,'StartDate' => date('Y-m-d', strtotime($StartDate)),'EndDate' => date('Y-m-d', strtotime($EndDate))
                        , 'Periority' => $periority,'ContactPerson'=>$ContactPerson,'ContactNo'=>$ContactNo,'ExternalAgency'=>$iexternal,'SourceName'=> $sourceName,'ServiceProviderName'=>$serviceProviderName
                        , 'MultiTimes' =>$imulti,'NoOfTimes'=>$noOfTimes,'TimesPer' =>$timesPer,'ExpectedLeads' => $lead
                        , 'Remarks'=>$remarks,'ExpectedIncome' => $Income,'CampaignCallNo'=> $callno, 'CampaignCost'=> $budget
                        ,'DiscountRequired'=>$idiscount,'DiscountType'=>$discountType
                        ,'DiscountRate'=>$discountrate,'DiscountAmount'=>$discountAmount
                        ,'DiscountFrom'=>date('Y-m-d', strtotime($discountFrom)),'DiscountTo'=>date('Y-m-d', strtotime($discountTo))));
                        $update->where(array('CampaignId' => $campaignid));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Crm_CampaignProjectTrans')
                        ->where(array("CampaignId" => $campaignid));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $projectrowid = $this->bsf->isNullCheck($postData['projectrowid'], 'number');
                    for ($i = 1; $i <= $projectrowid; $i++) {
                        $projectId = $this->bsf->isNullCheck($postData['projectId_' . $i], 'number');
                        $overallBudget = $this->bsf->isNullCheck($postData['overallBudget_' . $i], 'number');
                        $amount = $this->bsf->isNullCheck($postData['pbudget_' . $i], 'number');
                        if ($projectId == '' || $projectId==0)
                            continue;
                        if ($amount == '' || $amount==0)
                            continue;
                        $insert = $sql->insert();
                        $insert->into('Crm_CampaignProjectTrans');
                        $insert->Values(array('CampaignId' => $campaignid, 'ProjectId' => $projectId, 'Amount' => $amount,'BalanceBudget'=>$overallBudget));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    if($sMode == "Add") {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Campaign-Entry-Add','N','Campaign-Entry',$campaignid,0, 0, 'CRM',$CampaignNo,$userId, 0 ,0);
                    } else {
                        CommonHelper::insertLog(date('Y-m-d H:i:s'),'Campaign-Entry-Modify','E','Campaign-Entry',$campaignid,0, 0, 'CRM',$CampaignNo,$userId, 0 ,0);
                    }
                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                $FeedId = $this->params()->fromQuery('FeedId');
                $AskId = $this->params()->fromQuery('AskId');
                if(isset($FeedId) && $FeedId!="") {
                    if ($sMode == 'Add') {
                        $this->redirect()->toRoute('crm/campaign-index', array('controller' => 'campaign', 'action' => 'index'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('crm/campaign-index', array('controller' => 'campaign', 'action' => 'register'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    }
                } else {
                    if ($sMode == 'Add') {
                        $this->redirect()->toRoute('crm/campaign-index', array('controller' => 'campaign', 'action' => 'index'));
                    } else {
                        $this->redirect()->toRoute('crm/campaign-index', array('controller' => 'campaign', 'action' => 'register'));
                    }
                }
//                if ($sMode == 'Add') {
//                    $this->redirect()->toRoute('crm/campaign-index', array('controller' => 'campaign', 'action' => 'index'));
//                } else {
//                    $this->redirect()->toRoute('crm/campaign-index', array('controller' => 'campaign', 'action' => 'register'));
//                }


			} else {
                $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
                $sql = new Sql($dbAdapter);
                if ($editid != 0) {
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_CampaignRegister"))
                        ->join(array("b" => "Crm_OpportunityMaster"), "a.OpportunityId=b.OpportunityId", array('OpportunityName'), $select::JOIN_INNER)
                        ->columns(array("CampaignId", "CampaignNo", "CampaignDate" => new Expression("FORMAT(a.CampaignDate, 'dd-MM-yyyy')")
                        , "StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"),"EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')"),"CampaignName", "OpportunityId", "Status"
                        , "Periority","Remarks","ExpectedIncome","CampaignCost","ExternalAgency","ContactPerson","ContactNo","SourceName","ServiceProviderName","MultiTimes","NoOfTimes","TimesPer","ExpectedLeads","CampaignCallNo"
                        ,"DiscountRequired","DiscountType","DiscountRate","DiscountAmount","DiscountFrom"=>new Expression("FORMAT(a.DiscountFrom, 'dd-MM-yyyy')"),"DiscountTo"=>new Expression("FORMAT(a.DiscountTo, 'dd-MM-yyyy')")));
                    $select->where(array('a.CampaignId' => $editid));
                    $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $campaign = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->campaign = $campaign;

                    $select = $sql->select();
                    $select->from(array("a" => "Crm_CampaignProjectTrans"))
                        ->join(array("b" => "Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array('ProjectName'), $select::JOIN_INNER)
                        ->columns(array("ProjectId", "Amount","BalanceBudget"));
                    $select->where(array('a.CampaignId' => $editid));
                   $statement = $statement = $sql->getSqlStringForSqlObject($select);
                    $campaigntrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->campaigntrans = $campaigntrans;
                }
                //Common function
                $select = $sql->select();
                $select->from(array("a" => "Crm_OpportunityMaster"))
                    ->columns(array('data' => 'OpportunityId', 'value' => 'OpportunityName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->categoryList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
	   
	            $selectTurnAround = $sql->select();
				$selectTurnAround->from(array("a"=>"Proj_ProjectMaster"))
					->columns(array("ProjectId", "Amount1"=>new Expression("isnull(Sum(b.Amount),0)"), "Amount2"=>new Expression("1-1")))
					->join(array("b"=>new Expression("KF_TurnaroundSchedule")), "a.KickoffId=b.KickoffId", array(), $selectTurnAround::JOIN_LEFT)
					->where("b.CostTypeId=10");
                $selectTurnAround ->where(array("a.DeleteFlag"=>0));
				$selectTurnAround->group(new Expression("a.ProjectId"));
				
				$selectCampaign = $sql->select(); 
				$selectCampaign->from(array("a"=>"Proj_ProjectMaster"))
					->columns(array("ProjectId"=>new Expression("a.ProjectId"), "Amount1"=>new Expression("1-1"), "Amount2"=>new Expression("isnull(Sum(b.Amount),0)")))	
					->join(array("b"=>new Expression("Crm_CampaignProjectTrans")), "a.ProjectId=b.ProjectId", array(), $selectCampaign::JOIN_LEFT);
				$selectCampaign->group(new Expression("a.ProjectId"));
                $selectCampaign->where(array("a.DeleteFlag"=>0));
				$selectCampaign->combine($selectTurnAround,'Union ALL');

				$select = $sql->select(); 
				$select->from(array("g"=>$selectCampaign))
						->columns(array("data"=>new Expression("g.ProjectId"), "value"=>new Expression("b.ProjectName"), "amount"=>new Expression("Sum(g.Amount1-g.Amount2)")))
						->join(array("b"=>new Expression("Proj_ProjectMaster")), "g.ProjectId=b.ProjectId", array(), $select::JOIN_INNER)
                        ->where(array("b.DeleteFlag"=>0));
				$select->group(new Expression("g.ProjectId,b.ProjectName"))
                       ->order("g.ProjectId Desc");
                $statement = $sql->getSqlStringForSqlObject($select);
				$this->_view->projectList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $editid = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');
                $mode = $this->bsf->isNullCheck($this->params()->fromRoute('mode'), 'string');

                $aVNo = CommonHelper::getVoucherNo(806, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];

                $this->_view->campaignid = $editid;
                $this->_view->mode = $mode;

                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

                return $this->_view;
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
			$response = $this->getResponse();
			if ($request->isPost()) {
				//Write your Ajax post code here
				$postParams = $request->getPost();
				//Print_r($postParams);die;
				$CampId=$postParams['campaignId'];
				$select = $sql->select();
				$select->from(array("a" => "Crm_CampaignProjectTrans"))
					->join(array("b" => "Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array(), $select::JOIN_INNER)
					->columns(array("ProjectId", "ProjectName" => new Expression("b.ProjectName"),"Amount"))
					->where("a.CampaignId=$CampId");
			    $statement = $sql->getSqlStringForSqlObject($select);
				$resultProjCamp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
				
				//$this->_view->setTerminal(true);
				$response = $this->getResponse()->setContent(json_encode($resultProjCamp));
				//$response = $this->getResponse()->setContent($resultLeads);
				return $response;
			}
		} 
		
        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $projectId = 0;
        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
          $projectId = $this->bsf->isNullCheck($postData['projectId'], 'number');
        }
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from(array("a" => "Crm_CampaignRegister"))
            ->join(array("b" => "Crm_OpportunityMaster"), "a.OpportunityId=b.OpportunityId", array('Category' =>'OpportunityName'), $select::JOIN_INNER)
            ->columns(array("CampaignId", "CampaignNo", "CampaignDate" => new Expression("FORMAT(a.CampaignDate, 'dd-MM-yyyy')"),"CampaignName","StartDate" => new Expression("FORMAT(a.StartDate, 'dd-MM-yyyy')"),"EndDate" => new Expression("FORMAT(a.EndDate, 'dd-MM-yyyy')"), "ExpectedIncome","CampaignCost",
                "Status"=> new Expression("Case When Status='A' then 'Active' when Status='D' then 'Deactive' else 'N/A' end") ,"Remarks","SourceName","ServiceProviderName","ContactPerson","ContactNo","ExpectedLeads"))
            ->where('a.DeleteFlag=0');
            if($projectId != 0) {
                $select->join(array("c" => "Crm_CampaignProjectTrans"), "a.CampaignId=c.CampaignId", array(), $select::JOIN_INNER)
                ->where(array("c.ProjectId"=>$projectId));
            }
        $statement = $sql->getSqlStringForSqlObject( $select );
        $campaignRegister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

        $icount = 0;

        foreach ($campaignRegister as $resu) {
            $campaignId = $resu['CampaignId'];

            //Lead Projects List Append
            $strCCName="";
            $selectMultiCC = $sql->select();
            $selectMultiCC->from(array("a"=>"Crm_CampaignProjectTrans"));
            $selectMultiCC->columns(array("ProjectId"),array("ProjectName"))
                ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.projectId", array("ProjectName"), $selectMultiCC::JOIN_INNER);
            $selectMultiCC->where(array("a.CampaignId"=>$campaignId));
            $statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC);
            $resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $camp = array();
            if($resultMultiCC ){
                foreach($resultMultiCC as $multiCC){
                    array_push($camp, $multiCC['ProjectName']);
                }
                $strCCName = implode(",", $camp);
            }
            $campaignRegister[$icount]['Projects']=$strCCName;
            $icount=$icount+1;
        }

        $this->_view->campaignRegister=$campaignRegister;
		
		$select = $sql->select();
		$select->from(array("a"=>"KF_TurnaroundSchedule"))
			->columns(array('Amount' => new Expression("isnull(Sum(a.Amount),0)")))
			->where("a.CostTypeId=10");
			 if($projectId != 0) {
                 $select ->join(array("b"=>"Proj_ProjectMaster"), "a.KickoffId=b.KickoffId", array(), $select::JOIN_INNER)
                ->where(array("b.ProjectId"=>$projectId));
            }
		 $statement = $statement = $sql->getSqlStringForSqlObject($select);
		 $this->_view->campaignExpense = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'Crm_CampaignRegister'))
            ->columns(array('Amount' => new Expression("sum(CampaignCost)")))
            ->where("a.DeleteFlag='0'");
            if($projectId != 0) {
                $select ->join(array('b' => 'Crm_CampaignProjectTrans'), 'a.CampaignId=b.CampaignId', array(), $select::JOIN_INNER)
                       ->where(array("b.ProjectId"=>$projectId));
            }
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->campaignBudgetCost = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'Crm_CampaignRegister'))
          ->columns(array('Leads' => new Expression("sum(ExpectedLeads)")))
            ->where("a.DeleteFlag='0'");
        if($projectId != 0) {
            $select ->join(array('b' => 'Crm_CampaignProjectTrans'), 'a.CampaignId=b.CampaignId', array(), $select::JOIN_INNER)
            ->where(array("b.ProjectId"=>$projectId));
        }
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->expectedLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'Crm_CampaignRegister'))

            ->columns(array('Amount' => new Expression("sum(ExpectedIncome)")))
            ->where("a.DeleteFlag='0'");
            if($projectId != 0) {
                $select ->join(array('b' => 'Crm_CampaignProjectTrans'), 'a.CampaignId=b.CampaignId', array(), $select::JOIN_INNER)
                ->where(array("b.ProjectId"=>$projectId));
            }
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->campaignExpectedIncome = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        /*$select = $sql->select();
        $select->from(array('a' => 'Crm_CampaignRegister'))
            ->columns(array('Amount' => new Expression("sum(ExpectedIncome)")))
            ->where("a.DeleteFlag='0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->campaignExpectedIncome = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();*/

        $select = $sql->select();
        $select->from(array('a' => 'Crm_UnitBooking'))
          ->join(array('c' => 'Crm_LeadSource'), 'a.LeadId=c.LeadId', array(), $select::JOIN_INNER)
            ->columns(array('Amount' => new Expression("sum(a.NetAmount)")))
            ->where("a.LeadId<>0 and c.Name='C' and a.DeleteFlag=0");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->campaignwon = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'Crm_LeadSource'))
            ->columns(array('LeadCount' => new Expression("count(*)")))
			->where("a.Name='C'");
			if($projectId != 0) {
                $select  ->join(array('b' => 'Crm_LeadProjects'), 'b.LeadId=a.LeadId', array(), $select::JOIN_INNER)
                   ->where(array("b.ProjectId"=>$projectId));
            }
	    $statement = $sql->getSqlStringForSqlObject($select); 
        $this->_view->campaigleads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'Crm_CampaignRegister'))
            ->join(array('b' => 'Crm_OpportunityMaster'), 'a.OpportunityId=b.OpportunityId', array('OpportunityName'), $select::JOIN_INNER)
             ->columns(array('Amount' => new Expression("sum(CampaignCost)")))
            ->where("a.DeleteFlag='0'")
            ->group(new Expression('b.OpportunityName'));
            if($projectId != 0) {
                $select ->join(array('c' => 'Crm_CampaignProjectTrans'), 'a.CampaignId=c.CampaignId', array(), $select::JOIN_INNER)
                ->where(array("c.ProjectId"=>$projectId));
            }
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->campaignanal = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $select = $sql->select();
        $select->from(array('a' => 'Crm_UnitDetails'))
            ->join(array('u' => 'Crm_UnitBooking'), 'a.UnitId=u.UnitId', array(), $select::JOIN_INNER)
            ->join(array('b' => 'Crm_LeadSource'), 'u.LeadId=b.LeadId', array(), $select::JOIN_INNER)
            ->join(array('c' => 'Crm_CampaignRegister'), new Expression("b.LeadSourceId=c.CampaignId and b.Name='C' and b.DeleteFlag='0'"), array(), $select::JOIN_INNER)
            ->join(array('d' => 'Crm_OpportunityMaster'), 'c.OpportunityId=d.OpportunityId', array('OpportunityName'), $select::JOIN_INNER)
           ->columns(array('Amount' => new Expression("sum(BaseAmt)")))
            ->where("u.LeadId<>0")
            ->group(new Expression('d.OpportunityName'));
            if($projectId != 0) {
                $select ->join(array('e' => 'Crm_CampaignProjectTrans'), 'c.CampaignId=e.CampaignId', array(), $select::JOIN_INNER)
                ->where(array("e.ProjectId"=>$projectId));
            }
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->leadanal = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

        $select1 = $sql->select();
        $select1->from(array('a' => 'Crm_UnitBooking'))
            ->join(array('b' => 'Crm_UnitDetails'), 'a.UnitId=b.UnitId', array(), $select::JOIN_INNER)
            ->join(array('c' => 'Crm_LeadSource'), new Expression("a.LeadId=c.LeadId and c.Name='C'"), array(), $select::JOIN_INNER)
            ->join(array('d' => 'Crm_CampaignRegister'), new Expression("c.LeadSourceId=d.CampaignId and c.Name='C' and d.DeleteFlag='0'"), array(), $select::JOIN_INNER)
            ->columns(array('Mon' => new Expression("month(a.BookingDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,a.BookingDate),3) + '-' + ltrim(str(Year(a.BookingDate)))"), 'CampaignCost' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonAmount' => new Expression("sum(b.BaseAmt)"),'LeadCount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonCount' => new Expression("count(a.UnitId)")))
            ->where("a.LeadId<>0")
            ->where(array("a.DeleteFlag"=>0));
            if($projectId != 0) {
                $select1 ->join(array('e' => 'KF_UnitMaster'), 'a.UnitId=e.UnitId', array(), $select::JOIN_INNER)
                ->where(array("e.ProjectId"=>$projectId));
            }
        $select1  ->group(new Expression('month(a.BookingDate), LEFT(DATENAME(MONTH,a.BookingDate),3),Year(a.BookingDate)'));

        $select2 = $sql->select();
        $select2->from(array('a' => 'Crm_CampaignRegister'))
           ->columns(array('Mon' => new Expression("month(a.CampaignDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,a.CampaignDate),3) + '-' + ltrim(str(Year(a.CampaignDate)))"),'CampaignCost' => new Expression("sum(CampaignCost)"), 'LeadWonAmount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadCount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonCount' => new Expression("CAST(0 As Decimal(18,2))")))
            ->where("a.DeleteFlag=0")
            ->group(new Expression('month(a.CampaignDate), LEFT(DATENAME(MONTH,a.CampaignDate),3),Year(a.CampaignDate)'));
            if($projectId != 0) {
                $select2->join(array('b' => 'Crm_CampaignProjectTrans'), 'a.CampaignId=b.CampaignId', array(), $select::JOIN_INNER)
                ->where(array("b.ProjectId"=>$projectId));
            }
        $select2->combine($select1,'Union ALL');

        $select3 = $sql->select();
        $select3->from(array('a' => 'Crm_Leads'))
            ->join(array('b' => 'Crm_LeadSource'), new Expression("a.LeadId=b.LeadId and b.Name='C'"), array(), $select::JOIN_INNER)
            ->join(array('c' => 'Crm_CampaignRegister'), new Expression("b.LeadSourceId=c.CampaignId and b.Name='C' and c.DeleteFlag='0'"), array(), $select::JOIN_INNER)
            ->columns(array('Mon' => new Expression("month(a.LeadDate)"), 'Mondata' => new Expression("LEFT(DATENAME(MONTH,a.LeadDate),3) + '-' + ltrim(str(Year(a.LeadDate)))"),'CampaignCost' => new Expression("CAST(0 As Decimal(18,2))"), 'LeadWonAmount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadCount' => new Expression("count(a.LeadId)"),'LeadWonCount' => new Expression("CAST(0 As Decimal(18,2))")))
            ->group(new Expression('month(a.LeadDate), LEFT(DATENAME(MONTH,a.LeadDate),3),Year(a.LeadDate)'));
            if($projectId != 0) {
                $select3->join(array('e' => 'Crm_CampaignProjectTrans'), 'c.CampaignId=e.CampaignId', array(), $select::JOIN_INNER)
                ->where(array("e.ProjectId"=>$projectId));
            }
        $select3->combine($select2,'Union ALL');

        $select = $sql->select();
        $select->from(array("g"=>$select3))
            ->columns(array("Mon","Mondata","CampaignCost"=>new Expression("sum(g.CampaignCost)"),"LeadWonAmount"=>new Expression("sum(g.LeadWonAmount)"),"LeadCount"=>new Expression("CONVERT(INT, sum(g.LeadCount))"),"LeadWonCount"=>new Expression("CONVERT(INT,sum(g.LeadWonCount))")));
        $select->group(new Expression('g.Mon,g.Mondata'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->campaigndetails = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());



        $select1 = $sql->select();
        $select1->from(array('a' => 'Crm_UnitBooking'))
            ->join(array('b' => 'Crm_UnitDetails'), 'a.UnitId=b.UnitId', array(), $select1::JOIN_INNER)
           ->join(array('c' => 'Crm_LeadSource'), new Expression("a.LeadId=c.LeadId and c.Name='C'"), array(), $select1::JOIN_INNER)
            ->join(array('d' => 'Crm_CampaignRegister'), new Expression("c.LeadSourceId=d.CampaignId and c.Name='C' and d.DeleteFlag='0'"), array('CampaignId','CampaignName'), $select1::JOIN_INNER)
            ->columns(array('CampaignCost' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonAmount' => new Expression("sum(b.BaseAmt)"),'LeadCount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonCount' => new Expression("count(a.UnitId)"),'SiteVisitCount'=>new Expression("CAST(0 As Decimal(18,2))")))
            ->where("a.LeadId<>0");
            if($projectId != 0) {
                $select1 ->join(array('e' => 'KF_UnitMaster'), 'a.UnitId=e.UnitId', array(), $select1::JOIN_INNER)
                ->where(array("e.ProjectId"=>$projectId));
            }
        $select1->group(new Expression('d.CampaignId,d.CampaignName'));


        $select2 = $sql->select();
        $select2->from(array('a' => 'Crm_CampaignRegister'))
            ->columns(array('CampaignCost' => new Expression("sum(CampaignCost)"), 'LeadWonAmount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadCount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonCount' => new Expression("CAST(0 As Decimal(18,2))"),'SiteVisitCount'=>new Expression("CAST(0 As Decimal(18,2))"),'CampaignId','CampaignName'))
            ->where("a.DeleteFlag=0")
            ->group(new Expression('a.CampaignId,a.CampaignName'));
        if($projectId != 0) {
            $select2 ->join(array('b' => 'Crm_CampaignProjectTrans'), 'a.CampaignId=b.CampaignId', array(), $select2::JOIN_INNER)
            ->where(array("b.ProjectId"=>$projectId));
        }
        $select2->combine($select1,'Union ALL');

        $select3 = $sql->select();
        $select3->from(array('a' => 'Crm_Leads'))
            ->join(array('b' => 'Crm_LeadSource'), new Expression("a.LeadId=b.LeadId and b.Name='C'"), array(), $select3::JOIN_INNER)
            ->join(array('c' => 'Crm_CampaignRegister'), new Expression("b.LeadSourceId=c.CampaignId and b.Name='C' and c.DeleteFlag='0'"), array('CampaignId','CampaignName'), $select3::JOIN_INNER)
           ->columns(array('CampaignCost' => new Expression("CAST(0 As Decimal(18,2))"), 'LeadWonAmount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadCount' => new Expression("count(a.LeadId)"),'LeadWonCount' => new Expression("CAST(0 As Decimal(18,2))"),'SiteVisitCount'=>new Expression("CAST(0 As Decimal(18,2))")))
           ->group(new Expression('c.CampaignId,c.CampaignName'));
         if($projectId != 0) {
             $select3 ->join(array('e' => 'Crm_CampaignProjectTrans'), 'c.CampaignId=e.CampaignId', array(), $select3::JOIN_INNER)
            ->where(array("e.ProjectId"=>$projectId));
        }
        $select3->combine($select2,'Union ALL');

        $select4 = $sql->select();
        $select4->from(array('a' => 'Crm_LeadFollowUp'))
            ->join(array('c' => 'Crm_LeadSource'), new Expression("a.LeadId=c.LeadId and c.Name='C'"), array(), $select4::JOIN_INNER)
            ->join(array('d' => 'Crm_CampaignRegister'), new Expression("c.LeadSourceId=d.CampaignId and c.Name='C' and d.DeleteFlag='0'"), array('CampaignId','CampaignName'), $select4::JOIN_INNER)
            ->columns(array('CampaignCost' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonAmount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadCount' => new Expression("CAST(0 As Decimal(18,2))"),'LeadWonCount' => new Expression("CAST(0 As Decimal(18,2))"),'SiteVisitCount'=>new Expression("count(a.EntryId)")))
            ->where("a.LeadId<>0 and a.CallTypeId=5");
        if($projectId != 0) {
            $select4 ->join(array('e' => 'KF_UnitMaster'), 'a.UnitId=e.UnitId', array(), $select4::JOIN_INNER)
                ->where(array("e.ProjectId"=>$projectId));
        }
        $select4->group(new Expression('d.CampaignId,d.CampaignName'));
        $select4->combine($select3,'Union ALL');

        $select = $sql->select();
        $select->from(array("g"=>$select4))
            ->columns(array("CampaignId","CampaignName","CampaignCost"=>new Expression("sum(g.CampaignCost)"),"LeadWonAmount"=>new Expression("sum(g.LeadWonAmount)"),
              "LeadCount"=>new Expression("sum(g.LeadCount)"),"LeadWonCount"=>new Expression("sum(g.LeadWonCount)"),"SiteVisitCount"=>new Expression("sum(g.SiteVisitCount)"),
              "CostPerLead"=>new Expression("Case When sum(g.LeadCount)>0 then sum(g.CampaignCost)/sum(g.LeadCount) else 0 end"),
              "CostPerVisit"=>new Expression("Case When sum(g.SiteVisitCount)>0 then sum(g.CampaignCost)/sum(g.SiteVisitCount) else 0 end"),
              "CostPerCLead"=>new Expression("Case When sum(g.LeadWonCount)>0 then sum(g.CampaignCost)/sum(g.LeadWonCount) else 0 end"),
              "ROI"=>new Expression("Case When sum(g.CampaignCost)>0 then (sum(g.LeadWonAmount)/sum(g.CampaignCost))*100 else 0 end")));
        $select->group(new Expression('g.CampaignId,g.CampaignName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->campaignAnal = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

//        $select = $sql->select();
//        $select->from(array('a' => 'Crm_CampaignRegister'))
//            ->columns(array("CampaignId","CampaignName","CampaignCost"));
//        $statement = $sql->getSqlStringForSqlObject($select);
//        $campaign = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//        $campaignanal = array();
//        $k = 0;
//
//        foreach ($campaign as $data) {
//            $iCampaignId = $data['CampaignId'];
//            $campaignanal[$k]['CampaignId'] = $data['CampaignId'];
//            $campaignanal[$k]['CampaignName'] = $data['CampaignName'];
//            $campaignanal[$k]['CampaignCost'] = $data['CampaignCost'];
//
//
//
//
//            $k = $k+1;
//        }




        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
	}

    public function deletecampaignAction(){
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
                    $CampaignId = $this->params()->fromPost('CampaignId');
                    $Remarks = $this->params()->fromPost('Remarks');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

//                    $select = $sql->select();
//                    $select->from('Crm_ReceiptRegister')
//                        ->columns(array('ReceiptNo'))
//                        ->where(array("ReceiptId" => $ReceiptId));
//                    $statement = $sql->getSqlStringForSqlObject( $select );
//                    $bills = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
//                    $ReceiptNo="";
//                    if (!empty($bills)) { $ReceiptNo =$bills->ReceiptNo; }

                    $update = $sql->update();
                    $update->table('Crm_CampaignRegister')
                        ->set(array('DeleteFlag' => '1','DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $Remarks))
                        ->where(array('CampaignId' => $CampaignId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
					
					//CommonHelper::insertLog(date('Y-m-d H:i:s'),'Campaign-Entry-Delete','D','Campaign-Entry',$campaignid,0, 0, 'CRM',$dbAdapter,'',$userId, 0 ,0);				
					
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
    public function leadnameAction(){
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
                    $CampaignId = $this->params()->fromPost('cid');

                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    $select = $sql->select();
                    $select->from(array('a' =>'Crm_LeadSource'))
                        ->columns(array('LeadId','LeadF'=>new expression("Case When b.LeadConvert = 0 then 'No'  else  'Yes' End")))
                        ->join(array("b"=>"Crm_Leads"), "a.LeadId=b.LeadId", array("LeadName","LeadConvert"), $select::JOIN_LEFT)
                        ->where(array('a.LeadSourceId' => $CampaignId,'a.Name'=>'C'));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $resultLeads =  $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    $icount = 0;
                    foreach ($resultLeads as $resu) {
                        $leadId = $resu['LeadId'];
                        $strCCName="";
                        $selectMultiCC = $sql->select();
                        $selectMultiCC->from(array("a"=>"Crm_UnitBooking"));
                        $selectMultiCC->columns(array('UnitName'=>new Expression("c.ProjectName + ' - ' + b.UnitNo + ''")))
                            ->join(array("b"=>"KF_UnitMaster"), "b.UnitId=a.UnitId", array(), $selectMultiCC::JOIN_INNER)
                            ->join(array("c"=>"Proj_ProjectMaster"), "c.ProjectId=b.projectId", array(), $selectMultiCC::JOIN_INNER);
                        $selectMultiCC->where(array("a.LeadId"=>$leadId));
                      $statementMultiCC = $sql->getSqlStringForSqlObject($selectMultiCC);
                        $resultMultiCC = $dbAdapter->query($statementMultiCC, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $proj = array();
                      //  $unit = array();
                        if($resultMultiCC ){
                            foreach($resultMultiCC as $multiCC){
                                array_push($proj, $multiCC['UnitName']);
                                //array_push($unit, $multiCC['UnitNo']);
                            }
                            $strCCName = implode(",",$proj);
                        }
                        $resultLeads[$icount]['UnitName']=$strCCName;
                        $icount=$icount+1;
                    }


                        //Lead Projects List Append
//                        $strCCName = "";
//                        $strUnit = "";
//                        $selectCurRequest = $sql->select();
//                        $selectCurRequest->from(array("a"=>"Proj_ProjectMaster"));
//                        $selectCurRequest->columns(array('ProjectId','ProjectName',
//                               'multiUnit' =>new Expression(" STUFF((SELECT ', ' + b1.UnitNo FROM Crm_UnitBooking t
//								INNER JOIN KF_UnitMaster b1 on t.UnitId=b1.UnitId
//								where a.ProjectId = b1.ProjectId and t.LeadId=$leadId and t.DeleteFlag=0
//								FOR XML PATH (''))
//								, 1, 1, '')")));
//                        $statement = $sql->getSqlStringForSqlObject($selectCurRequest);
//                        $resultproj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();



                  $this->_view->setTerminal(true);
                    $response = $this->getResponse()->setContent(json_encode($resultLeads));
                    return $response;



                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

                $response->setContent($status);
                return $response;
            }
        }
    }
}