<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Project\Controller;

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
use Project\View\Helper\ProjectHelper;
class DashboardController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function projectmainAction(){
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" || Project Dashboard");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);



        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_view->setTerminal(true);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $year = $this->bsf->isNullCheck($this->params()->fromPost('Year'), 'number');
                switch ($Type) {
                    case 'getyeardata':
                        $select = $sql->select();
                        $select->from('Proj_ProjectStatus')
                            ->columns(array('PMonth', 'PYear', 'PDate' => new Expression("Format(PDate, 'MMM')"), 'SchAmt' => new Expression("sum(BudgetAmount)"), 'WorkDone' => new Expression("sum(workDone)"), 'BillAmount' => new Expression("sum(BillAmount)"),
                                'Receipt' => new Expression("sum(Receiptamount)")))
                            ->where(array('PYear' => $year))
                            ->group(array('PYear', 'PMonth', 'PDate'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $mStatus = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select->from('Proj_ProjectStatus')
                            ->columns(array('SchAmt' => new Expression("sum(BudgetAmount)"), 'WorkDone' => new Expression("sum(workDone)"), 'BillAmount' => new Expression("sum(BillAmount)"),
                                'Receipt' => new Expression("sum(Receiptamount)")))
                            ->where(array('PYear' => $year));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $adata['Status'] = $mStatus;
                        $adata['Total'] = $result;

                        if (count($result) > 0)
                            return $this->getResponse()->setContent(json_encode($adata));
                        else
                            return $this->getResponse()->setStatusCode('201')
                                ->setContent('No data.');
                        break;
                    case 'default':
                        return $this->getResponse()->setStatusCode('400')
                            ->setContent('Bad Request');
                        break;
                }
            }
        } else {

            ProjectHelper::_allProjectCostUpdate($dbAdapter);

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('count' => new Expression('count(ProjectId)'), 'ProjectCost' => new Expression("sum(ProjectCost)")
                , 'ProjectCompleted' => new Expression("sum(ProjectCompleted)")))
                ->where('IsCompleted=0 AND DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectMaster'))
                ->columns(array('ProjectId', 'ProjectName', 'ProjectCost', 'ProjectCompleted', 'WorksHand' => new Expression("Case When ProjectCost > ProjectCompleted then ProjectCost-ProjectCompleted else 0 end")))
                ->where('a.IsCompleted=0 AND a.DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->running_projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get projects status
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectName', 'ProjectCost', 'ProjectCompleted', 'CompPer' => new Expression("Case When ProjectCost <>0 then (ProjectCompleted/ProjectCost)*100 else 0 end"),
                    'SchPer' => new Expression("Case When ProjectCost <>0 then (ScheduleValue/ProjectCost)*100 else 0 end")))
                ->where('IsCompleted=0 AND DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects_status = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from('Proj_ProjectStatus')
                ->columns(array('WorkDone' => new Expression("Case When sum(BudgetAmount) <>0 then (sum(workDone)/sum(BudgetAmount))*100 else 0 end"),
                    'Receipt' => new Expression("case when sum(BillAmount) <>0 then (sum(Receiptamount)/sum(BillAmount))*100 else 0 end")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $dtStatus = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $this->_view->workdone_status = $dtStatus;
            $this->_view->receipt_status = $dtStatus;

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectMaster'))
                ->join(array('b' => 'WF_BusinessTypeMaster'), 'a.BusinessTypeId=b.BusinessTypeId', array('BusinessTypeName'), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('sum(ProjectCost)')))
                ->where('a.IsCompleted=0 AND a.DeleteFlag=0')
                ->group(new Expression('b.BusinessTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects_business_wise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectMaster'))
                ->join(array('b' => 'WF_CityMaster'), 'a.CityId=b.CityId', array('CityName'), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('sum(ProjectCost)')))
                ->where('a.IsCompleted=0 AND a.DeleteFlag=0')
                ->group(new Expression('b.CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects_city_wise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get projects type wise
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectMaster'))
                ->join(array('b' => 'Proj_ProjectTypeMaster'), 'a.ProjectTypeId=b.ProjectTypeId', array('ProjectTypeName'), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('sum(ProjectCost)')))
                ->where('a.IsCompleted=0 AND a.DeleteFlag=0')
                ->group(new Expression('b.ProjectTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects_type_wise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // projects list
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'))
                ->where('DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectStatus'))
                ->columns(array('Year' => new Expression('DISTINCT(a.PYear)')))
                ->where('PYear<>0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->yearlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function landbankAction(){
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" LandBank Dashboard");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        try {
            // get no. of enquiries
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('count' => new Expression('count(EnquiryId)')));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


            // get no. of inprogress
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('count' => new Expression('count(EnquiryId)')))
                ->where('KickoffDone = 0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_progress = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // get no. of finalized
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('count' => new Expression('count(EnquiryId)')))
                ->where('FinalizationId <> 0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_finalized = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // get no. of inprogress
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('count' => new Expression('count(EnquiryId)')))
                ->where('KickoffDone<> 0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_project_started = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // in progress status graph
            $select = $sql->select();
            $select->from('Proj_LandEnquiry')
                ->columns(array('PropertyName', 'IFeasibilityId' => new Expression('CASE WHEN IFeasibilityId <> 0 THEN 1 ELSE 0 END')
                , 'BFeasibilityDone' => new Expression('CASE WHEN BFeasibilityDone <> 0 THEN 1 ELSE 0 END')
                , 'FFeasibilityDone' => new Expression('CASE WHEN FFeasibilityDone <> 0 THEN 1 ELSE 0 END')
                , 'DueDiligenceId' => new Expression('CASE WHEN DueDiligenceId <> 0 THEN 1 ELSE 0 END')
                , 'FinalizationId' => new Expression('CASE WHEN FinalizationId <> 0 THEN 1 ELSE 0 END')
                , 'ConceptionDone' => new Expression('CASE WHEN ConceptionDone <> 0 THEN 1 ELSE 0 END')))
                ->where('KickoffDone = 0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->landbank_inprogress = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // enquiry vs finalished
            $select_count = $sql->select();
            $select_count->from('Proj_LandEnquiry')
                ->columns(array('Enquiry' => new Expression('count(EnquiryId)'), 'Month' => new Expression('MONTH(RefDate)'), 'Year' => new Expression('YEAR(RefDate)')))
                ->group(new Expression('MONTH(RefDate), YEAR(RefDate)'));

            $select_finalized = $sql->select();
            $select_finalized->from('Proj_LandEnquiry')
                ->columns(array('Finalize' => new Expression('count(EnquiryId)'), 'Month' => new Expression('MONTH(RefDate)'), 'Year' => new Expression('YEAR(RefDate)')))
                ->where('FinalizationId <> 0')
                ->group(new Expression('MONTH(RefDate), YEAR(RefDate)'));

            $select3 = $sql->select();
            $select3->from(array("G"=>$select_count))
                ->join(array('H' => $select_finalized), 'G.Month=H.Month and G.Year=H.Year', array(), $select3::JOIN_LEFT)
                ->columns(array('Enquiry' => new Expression("isnull(G.Enquiry,0)"), 'Month' => new Expression('LEFT(DATENAME(M, DATEADD(M, G.Month,-1)),3)'), 'Year' => new Expression('G.Year'),'Finalize' => new Expression("isnull(H.Finalize,0)")))
                ->where('G.Year=YEAR(GETDATE())');
            $statement = $sql->getSqlStringForSqlObject($select3);
            $this->_view->landbank_enquiry_finalised = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get no. of conception
            $select = $sql->select();
            $select->from('Proj_LandConceptionRegister')
                ->columns(array('count' => new Expression('count(ConceptionId)'), 'Month' => new Expression("FORMAT(RefDate,'MMM')")))
                ->where('YEAR(RefDate)=YEAR(GETDATE())')
                ->group(new Expression('Refdate'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_conception = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get source type counts
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->join(array('b' => 'Proj_SourceMaster'), 'a.SourceId=b.SourceId', array('SourceName'), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('count(a.SourceId)')))
                ->where('a.SourceId <> 0')
                ->group(new Expression('a.SourceId,b.SourceName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->source_types = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get city wise count
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->join(array('b' => 'WF_CityMaster'), 'a.CityId=b.CityId', array('CityName'), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('count(a.CityId)')))
                ->where('a.CityId <> 0')
                ->group(new Expression('a.CityId,b.CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquires_citywise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get sale type wise enquires
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->join(array('b' => 'Proj_SaleTypeMaster'), 'a.SaleTypeId=b.SaleTypeId', array('SaleTypeName'), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('count(a.SaleTypeId)')))
                ->where('a.SaleTypeId <> 0')
                ->group(new Expression('a.SaleTypeId,b.SaleTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquires_salestypewise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get sale type wise enquires
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandEnquiry'))
                ->columns(array('count' => new Expression('count(a.EnquiryId)'), 'DropReason'))
                ->where("a.DropReason <> ''")
                ->group(new Expression('a.DropReason'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquires_dropped = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get sale type wise enquires
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LandBankFinalization'))
                ->join(array('b' => 'Proj_LandEnquiry'), 'a.EnquiryId=b.EnquiryId', array('PropertyName', 'TotalArea'), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_UOM'), 'b.TotalAreaUnitId=c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                ->columns(array('FinalAmount'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquires_finalized = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get checklist dues
            $select = $sql->select();
            $select->from(array('a' => 'Proj_LBInitialFeasibilityCheckListTrans'))
                ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
                ->join(array('c' => 'WF_Users'), 'a.AssignedTo=c.UserId', array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                ->columns(array('TargetDate' => new Expression("FORMAT(a.TargetDate,'dd-MM-yyyy')"), 'TodayDue' => new Expression("CASE WHEN a.TargetDate < GETDATE() THEN '0' ELSE '1' END")))
                ->where("a.Status <> 'Done' AND a.TargetDate <= GETDATE()");
            $statement = $sql->getSqlStringForSqlObject($select);
            $initialDues = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_LBDueDiligenceCheckListTrans'))
                ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
                ->join(array('c' => 'WF_Users'), 'a.AssignedTo=c.UserId', array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                ->columns(array('TargetDate' => new Expression("FORMAT(a.TargetDate,'dd-MM-yyyy')"), 'TodayDue' => new Expression("CASE WHEN a.TargetDate < GETDATE() THEN '0' ELSE '1' END")))
                ->where("a.Status <> 'Done' AND a.TargetDate <= GETDATE()");
            $statement = $sql->getSqlStringForSqlObject($select);
            $duediligenceDues = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->reminders = array_merge($duediligenceDues, $initialDues);
        } catch(PDOException $e){
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function contractAction(){
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Contract Dashboard");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        try {
            if ($this->getRequest()->isXmlHttpRequest()) {
                $request = $this->getRequest();
                $response = $this->getResponse();
                if ($request->isPost()) {
                    $projectTypeId = $this->bsf->isNullCheck($this->params()->fromPost('id'),'number');
                    $select = $sql->select();
                    $select->from(array( 'a' => 'Proj_ContractBidCompetitorTrans'))
                        ->join(array('d' => 'Proj_ContractBidStatus'), 'a.BidTransId=d.BidTransId', array(), $select::JOIN_LEFT)
                        ->join(array('b' => 'Proj_TenderDetails'), 'd.TenderEnquiryId=b.TenderEnquiryId', array(), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectTypeMaster'), 'b.ProjectTypeId=c.ProjectTypeId', array(), $select::JOIN_LEFT)
                        ->columns(array('count' => new Expression('count(a.CompetitorName)'), 'Position','CompetitorName'))
                        ->where("c.ProjectTypeId=$projectTypeId")
                        ->group(new Expression('a.Position, a.CompetitorName'))
                        ->order('a.Position')
                        ->limit(5);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $bidcompetitors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $response->setStatusCode(200)
                        ->setContent(json_encode($bidcompetitors));
                    return $response;
                }
            }

            // get no. of enquiries
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('count' => new Expression('count(TenderEnquiryId)')));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // get no. of enquiries quoted
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('count' => new Expression('count(TenderEnquiryId)')))
                ->where('Quoted=1');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_quoted = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // get no. of enquiries bid won
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('count' => new Expression('count(TenderEnquiryId)')))
                ->where('BidWin=1');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_bid_won = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // get no. of enquiries awaiting
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('count' => new Expression('count(TenderEnquiryId)')))
                ->where('OrderReceived=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_awaiting = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // in progress status graph
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('NameOfWork', 'Quoted', 'Submitted', 'BidWin', 'OrderReceived', 'WorkStarted'))
                ->where(array('WorkStarted'=>0)) ;
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->contract_status = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // enquiry vs quoted
            $select_count = $sql->select();
            $select_count->from('Proj_TenderEnquiry')
                ->columns(array('Enquiry' => new Expression('count(TenderEnquiryId)'), 'Month' => new Expression('MONTH(RefDate)'), 'Year' => new Expression('YEAR(RefDate)')))
                ->group(new Expression('MONTH(RefDate), YEAR(RefDate)'));

            $select_quoted = $sql->select();
            $select_quoted->from('Proj_TenderEnquiry')
                ->columns(array('QuotedCount' => new Expression('count(TenderEnquiryId)'), 'Month' => new Expression('MONTH(RefDate)'), 'Year' => new Expression('YEAR(RefDate)')))
                ->where('Quoted=1')
                ->group(new Expression('MONTH(RefDate), YEAR(RefDate)'));

            $select3 = $sql->select();
            $select3->from(array("G"=>$select_count))
                ->join(array('H' => $select_quoted), 'G.Month=H.Month and G.Year=H.Year', array('QuotedCount'), $select3::JOIN_LEFT)
                ->columns(array('Enquiry' => new Expression("G.Enquiry"), 'Month' => new Expression('LEFT(DATENAME(M, DATEADD(M, G.Month,-1)),3)'), 'Year' => new Expression('G.Year')))
                ->where('G.Year=YEAR(GETDATE())');
            $statement = $sql->getSqlStringForSqlObject($select3);
            $this->_view->contract_enquiry_quoted = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select_bidwon = $sql->select();
            $select_bidwon->from('Proj_TenderEnquiry')
                ->columns(array('BidWonCount' => new Expression('count(TenderEnquiryId)'), 'Month' => new Expression('MONTH(RefDate)'), 'Year' => new Expression('YEAR(RefDate)')))
                ->where('BidWin=1')
                ->group(new Expression('MONTH(RefDate), YEAR(RefDate)'));

            $select = $sql->select();
            $select->from(array("G"=>$select_quoted))
                ->join(array('H' => $select_bidwon), 'G.Month=H.Month and G.Year=H.Year', array('BidWonCount'), $select::JOIN_LEFT)
                ->columns(array('QuotedCount' => new Expression("G.QuotedCount"), 'Month' => new Expression('LEFT(DATENAME(M, DATEADD(M, G.Month,-1)),3)'), 'Year' => new Expression('G.Year')))
                ->where('G.Year=YEAR(GETDATE())');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->contract_quote_won = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get win ratio
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('count' => new Expression('count(TenderEnquiryId)'), 'Month' => new Expression("LEFT(DATENAME(M, DATEADD(M, MONTH(Refdate),-1)),3)")))
                ->where('BidWin=1 AND YEAR(RefDate)=YEAR(GETDATE())')
                ->group(new Expression('MONTH(Refdate)'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_bidswon = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get lose by reason
            $select = $sql->select();
            $select->from('Proj_ContractPreQualBidStatus')
                ->columns(array('count' => new Expression('count(PreQualStatusId)'), 'Reason'))
                ->where("BStatus='Loss'")
                ->group(new Expression('Reason'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_lose_reason = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get win ratio - client wise
            $select = $sql->select();
            $select->from('Proj_TenderEnquiry')
                ->columns(array('count' => new Expression('count(TenderEnquiryId)'), 'ClientName'))
                ->where('BidWin=1')
                ->group(new Expression('ClientName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_bidswon_clientwise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get win ratio - consultant wise
            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderConsultant'))
                ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_ConsultantMaster'), 'a.ConsultantId=c.ConsultantId', array('ConsultantName'), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('count(b.TenderEnquiryId)')))
                ->where('b.BidWin=1')
                ->group(new Expression('ConsultantName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_bidswon_consultantwise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get win ratio - project type wise
            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderDetails'))
                ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_ProjectTypeMaster'), 'b.ProjectTypeId=c.ProjectTypeId', array('ProjectTypeName'=>new Expression("Case When b.ProjectTypeId=0 then b.ProjectTypeName else c.ProjectTypeName end")), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('count(b.TenderEnquiryId)')))
                ->where(new Expression("b.BidWin=1 and b.ProjectTypeName<>''"))
                ->group(new Expression('b.ProjectTypeId,c.ProjectTypeName,b.ProjectTypeName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_bidswon_projecttypewise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get win ratio - city wise
            $select = $sql->select();
            $select->from(array( 'a' => 'Proj_TenderEnquiry'))
                ->join(array('b' => 'WF_CityMaster'), 'a.CityId=b.CityId', array('CityName'=>new Expression("Case When a.CityId=0 then a.CityName else b.CityName end")), $select::JOIN_LEFT)
                ->columns(array('count' => new Expression('count(a.TenderEnquiryId)')))
                ->where(new Expression("a.BidWin=1 and a.CityName<>''"))
                ->group(new Expression('a.CityId,a.CityName,b.CityName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiries_bidswon_citywise = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get competitors stand
            $select = $sql->select();
            $select->from(array( 'a' => 'Proj_ContractBidCompetitorTrans'))
                ->columns(array('count' => new Expression('count(a.CompetitorName)'), 'Position','CompetitorName'))
                ->group(new Expression('Position, CompetitorName'))
                ->order('Position')
                ->limit(5);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->bidcompetitors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // project type list
            $select = $sql->select();
            $select->from('Proj_ProjectTypeMaster');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projecttypes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get reminders
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ContractChecklistTrans'))
                ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
                ->join(array('c' => 'WF_Users'), 'a.UserId=c.UserId', array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                ->columns(array('TargetDate' => new Expression("FORMAT(a.CheckListDate,'dd-MM-yyyy')"), 'TodayDue' => new Expression("CASE WHEN a.CheckListDate < GETDATE() THEN '0' ELSE '1' END")))
                ->where("a.Status <> 'Done' AND a.CheckListDate <= GETDATE()");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->reminders = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get enquiry schedules
            $select = $sql->select();
            $select->from(array('a' => 'Proj_TenderDates'))
                ->join(array('b' => 'Proj_TenderEnquiry'), 'a.TenderEnquiryId=b.TenderEnquiryId', array(), $select::JOIN_LEFT)
                ->columns(array('id' => 'TenderEnquiryId', 'description' => new Expression("''"), 'subject' => new Expression("b.NameOfWork+ ' - ' +a.TypeOfDate"), 'location' => 'PlaceOfAddress',
                    'start' => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), 'end' => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')"), 'calendar' => 'PlaceOfAddress'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->enquiry_schedules = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        } catch(PDOException $e){
        }

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function projectdashboardAction(){
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Project Dashboard");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $this->_view->setTerminal(true);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $projectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number');
                switch($Type) {
                    case 'getyeardata':
                        $year = $this->bsf->isNullCheck($this->params()->fromPost('Year'), 'number');

                        $select = $sql->select();
                        $select->from('Proj_ProjectStatus')
                            ->columns(array('SchAmt' => new Expression("sum(BudgetAmount)")))
                            ->where(array("ProjectId" => $projectId,'PYear'=>$year));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $totprogress = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from('Proj_ProjectStatus')
                            ->columns(array('PMonth', 'PYear', 'PDate' => new Expression("Format(PDate, 'MMM')"), 'SchAmt' => new Expression("sum(BudgetAmount)"), 'WorkDone' => new Expression("sum(workDone)"), 'BillAmount' => new Expression("sum(BillAmount)"),
                                'Receipt' => new Expression("sum(Receiptamount)")))
                            ->where(array("ProjectId" => $projectId,'PYear'=>$year))
                            ->group(array('PYear', 'PMonth', 'PDate'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $progress = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $arrSchedule = array();
                        $i = 0;
                        $dTSchAmt = 0;
                        if (!empty($totprogress)) $dTSchAmt = floatval($totprogress['SchAmt']);

                        $dSchAmt = 0;
                        $dWorkDone = 0;
                        foreach ($progress as $trans) {
                            $arrSchedule[$i]['PDate'] = $trans['PDate'];
                            $dSchAmt = $dSchAmt + floatval($trans['SchAmt']);
                            $dWorkDone = $dWorkDone + floatval($trans['WorkDone']);
                            $dSchPer = 0;
                            $dWorkDonePer = 0;
                            if ($dTSchAmt != 0) {
                                $dSchPer = ($dSchAmt / $dTSchAmt) * 100;
                                $dWorkDonePer = ($dWorkDone / $dTSchAmt) * 100;
                            }

                            $arrSchedule[$i]['SchAmt'] = $dSchAmt;
                            $arrSchedule[$i]['WorkDone'] = $dWorkDone;
                            $arrSchedule[$i]['SchPer'] = $dSchPer;
                            $arrSchedule[$i]['WorkDonePer'] = $dWorkDonePer;
                            $i = $i + 1;
                        }
                        return $this->getResponse()->setContent(json_encode($arrSchedule));
                        break;
                    case 'getprogress':
                        $year = $this->bsf->isNullCheck($this->params()->fromPost('Year'), 'number');
                        $select = $sql->select();
                        $select->from('Proj_ProjectStatus')
                            ->columns(array('PMonth', 'PYear', 'PDate' => new Expression("Format(PDate, 'MMM')"), 'SchAmt' => new Expression("sum(BudgetAmount)"), 'WorkDone' => new Expression("sum(workDone)"), 'BillAmount' => new Expression("sum(BillAmount)"),
                                'Receipt' => new Expression("sum(Receiptamount)")))
                            ->where(array("ProjectId" => $projectId,'PYear'=>$year))
                            ->group(array('PYear', 'PMonth', 'PDate'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $progress = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        return $this->getResponse()->setContent(json_encode($progress));
                        break;
                    case 'dayprogress':

                        $sDate= date('Y-m-d', strtotime($this->params()->fromPost('sDate')));
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_ScheduleDetails'))
                            ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new Expression("sum(a.SQty*b.Rate)")))
                            ->where(array('a.ProjectId'=>$projectId,'SDate'=>$sDate));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $shAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $dSchAmt = 0;
                        if (!empty($shAmt)) $dSchAmt = floatval($this->bsf->isNullCheck($shAmt['Amount'],'number'));

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_SchCompletion'))
                            ->join(array('b' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new Expression("sum(a.Qty*b.Rate)")))
                            ->where(array('a.ProjectId'=>$projectId,'SDate'=>$sDate));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $ComAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $dCompAmt = 0;
                        if (!empty($shAmt)) $dCompAmt = floatval($this->bsf->isNullCheck($ComAmt['Amount'],'number'));

                        $dWorkDone = array();

                        $dWorkDone[0]['Title'] = "Work";
                        $dWorkDone[0]['Schedule'] = $dSchAmt;
                        $dWorkDone[0]['WorkDone'] = $dCompAmt;

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_SchCompletion'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new Expression("sum(((B.Qty/WorkingQty)*A.Qty)*B.Rate)")))
                            ->where(array('a.ProjectId'=>$projectId,'SDate'=>$sDate,'d.TypeId'=>2));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $matTAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $mTAmt =0;
                        if (!empty($matTAmt)) $mTAmt = floatval($this->bsf->isNullCheck($matTAmt['Amount'],'number'));

                        $select = $sql->select();
                        $select->from(array('a' => 'MMS_IssueTrans'))
                            ->join(array('b' => 'MMS_IssueRegister'), 'a.IssueRegisterId=b.IssueRegisterId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'WF_OperationalCostCentre'), 'b.CostCentreId=c.CostCentreId', array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new Expression("SUM(Case when b.IssueOrReturn=0 then  a.IssueQty*a.IssueRate Else -a.issueqty*a.Issuerate End)")))
                            ->where(array('c.ProjectId'=>$projectId,'b.IssueDate'=>$sDate));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $matAAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $mAAmt =0;
                        if (!empty($matAAmt)) $mAAmt = floatval($this->bsf->isNullCheck($matAAmt['Amount'],'number'));

                        $dMaterial = array();
                        $dMaterial[0]['Title'] = "Material";
                        $dMaterial[0]['Schedule'] = $mTAmt;
                        $dMaterial[0]['Actual'] = $mAAmt;

                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_SchCompletion'))
                            ->join(array('b' => 'Proj_ProjectDetails'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new Expression("sum(((B.Qty/WorkingQty)*A.Qty)*B.Rate)")))
                            ->where(array('a.ProjectId'=>$projectId,'SDate'=>$sDate,'d.TypeId'=>1));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $labTAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $lTAmt =0;
                        if (!empty($labTAmt)) $lTAmt = floatval($this->bsf->isNullCheck($labTAmt['Amount'],'number'));

                        $select = $sql->select();
                        $select->from( array('a' => 'WPM_LSLabourTrans' ))
                            ->join(array('b' => 'WPM_LabourStrengthRegister'), 'a.LSRegisterId=b.LSRegisterId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'WF_OperationalCostCentre'), 'b.CostCentreId=c.CostCentreId', array(), $select::JOIN_INNER)
                            ->columns(array('Amount'=>new Expression("SUM(a.NetAmount)")))
                            ->where(array('c.ProjectId'=>$projectId,'b.LSDate'=>$sDate));

                        $select2 = $sql->select();
                        $select2->from( array('a' => 'WPM_LSVendorTrans' ))
                            ->join(array('b' => 'WPM_LabourStrengthRegister'), 'a.LSRegisterId=b.LSRegisterId', array(), $select2::JOIN_INNER)
                            ->join(array('c' => 'WF_OperationalCostCentre'), 'b.CostCentreId=c.CostCentreId', array(), $select2::JOIN_INNER)
                            ->columns(array('Amount'=>new Expression("SUM(a.NetAmount)")))
                            ->where(array('c.ProjectId'=>$projectId,'b.LSDate'=>$sDate));
                        $select2->combine($select,'Union ALL');

                        $selectFinal = $sql->select();
                        $selectFinal->from(array('g'=>$select2))
                            ->columns(array('Amount' => new Expression("SUM(g.Amount)") ));
                        $statement = $sql->getSqlStringForSqlObject($selectFinal);
                        $labAAmt = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                        $lAAmt =0;
                        if (!empty($labAAmt)) $lAAmt = floatval($this->bsf->isNullCheck($labAAmt['Amount'],'number'));

                        $dLabour = array();
                        $dLabour[0]['Title'] = "Labour";
                        $dLabour[0]['Schedule'] = $lTAmt;
                        $dLabour[0]['Actual'] = $lAAmt;

                        $data['Work'] = $dWorkDone;
                        $data['Material'] = $dMaterial;
                        $data['Labour'] = $dLabour;

                        return $this->getResponse()->setContent(json_encode($data));
                        break;
                    case 'default':
                        return $this->getResponse()->setStatusCode('400')
                            ->setContent('Bad Request');
                        break;
                }
            }
        } else {

            $iProjectId = $this->bsf->isNullCheck($this->params()->fromRoute('projectid'), 'number');

            if ($iProjectId == 0)
                $this->redirect()->toRoute('project/projectmain', array('controller' => 'dashboard', 'action' => 'projectmain'));

            //ProjectInfo
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectInfo'))
                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.ProjectId=b.ProjectId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'WF_CostCentre'), 'b.FACostCentreId=c.CostCentreId', array('Address','Phone'), $select::JOIN_LEFT)
                ->columns(array('BudgetCost','SDate','EDate','Duration','CompletionPer','WorkDone','CTC','TCTC','RDays','DayProgress',
                    'RProgress','Billed','Received','Receivable'))
                ->where(array('a.ProjectId'=>$iProjectId ));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectInfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            // project details
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId', 'ProjectName'))
                ->where('DeleteFlag=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // project details
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectMaster'))
                ->join(array('b' => 'Proj_UOM'), 'a.DurationUnitId=b.UnitId', array(), $select::JOIN_LEFT)
                ->columns(array('ProjectName', 'ProjectCost', 'ProjectCompleted', 'Duration' => new Expression("CONCAT(a.Duration,' ',b.UnitName)")))
                ->where("a.ProjectId=$iProjectId AND a.DeleteFlag=0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $projectDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->projectDetails = $projectDetails;

            $projectName = "";
            if (!empty($projectDetails)) $projectName = $projectDetails['ProjectName'];

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectIOW'))
                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_ProjectWorkGroup'), 'b.PWorkGroupId=c.PWorkGroupId', array('WorkGroupName'), $select::JOIN_LEFT)
                ->columns(array('Amount' => new Expression("sum(a.Amount)")))
                ->where("a.ProjectId=$iProjectId")
                ->group('c.WorkGroupName');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->project_work_group = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get resource group
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectResource'))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array('ResourceGroupName'), $select::JOIN_LEFT)
                ->columns(array('Amount' => new Expression("sum(a.Amount)")))
                ->where("a.ProjectId=$iProjectId")
                ->group('c.ResourceGroupName');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->project_resource_group = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get resource type
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectResource'))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Proj_ResourceType'), 'b.TypeId=c.TypeId', array('TypeName'), $select::JOIN_LEFT)
                ->columns(array('Amount' => new Expression('SUM(a.Amount)')))
                ->where("a.ProjectId=$iProjectId")
                ->group('c.TypeName');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->project_resource_type = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get top 5 resource
            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectResource'))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array('ResourceName'), $select::JOIN_LEFT)
                ->columns(array('Amount'))
                ->where("a.ProjectId=$iProjectId")
                ->order('a.Amount Desc')
                ->limit(5);
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects_resource_required = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectStatus'))
                ->columns(array('Year' => new Expression('DISTINCT(a.PYear)')))
                ->where("ProjectId='$iProjectId' and PYear<>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->yearlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // get photos
            $select = $sql->select();
            $select->from(array('a' => 'Proj_PhotoProgressFiles'))
                ->join(array('b' => 'Proj_PhotoProgress'), 'a.PhotoProgressTransId=b.TransId', array(), $select::JOIN_LEFT)
                ->columns(array('URL'))
                ->where("a.FileType='image' AND b.ProjectId=$iProjectId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->photos = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->_view->projectId = $iProjectId;
            $this->_view->projectName = $projectName;

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }
}