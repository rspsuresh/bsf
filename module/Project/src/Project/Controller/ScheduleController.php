<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace Project\Controller;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
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

class ScheduleController extends AbstractActionController
{
    public function __construct()
    {
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        $this->strText="";
        $this->taskId=0;
        $this->wbssharray= array();
        $this->shWBSIOWTrans=array();
        $this->scheduleTrans=array();

        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function indexAction()
    {
    }

    public function rfcscheduleAction()
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

        $rfcId = $this->params()->fromRoute('rfcId');
        $this->_view->rfcId = (isset($rfcId) && $rfcId != 0) ? $rfcId : 0;

        $icount = 0;
        $stdate = "";
        $eddate = "";
        $strText = "";
        $strHDay = "";

        $iProjectId=0;
        $iParentId=0;
        $typename="";

        $aVNo = CommonHelper::getVoucherNo(101, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            if (!is_null($postData['frm_what'])) {
                // get project list
                $iProjectId = $this->bsf->isNullCheck($postData['project_id'], 'number');
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'))
                    ->where(array('ProjectId' => $iProjectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->frmwhat = $postData['frm_what'];
                $typename =$postData['type_name'];

                $this->_view->projecttype = $typename;

                if ($typename == 'B')
                    $this->_view->projecttypename = 'Budget';
                else if ($typename == 'P')
                    $this->_view->projecttypename = 'Plan';
            }

            $strHDay = $this->_getHolidays($iProjectId,$dbAdapter);
            $weektype = $this->_getWeeklyHolidays($iProjectId,$dbAdapter);

            $select = $sql->select();
            $select->from(array('a' => 'WBSList'));
            if ($typename=="P") $select->join(array('b' => 'Proj_SchedulePlan'), new Expression("a.WBSId = b.WBSId and b.ProjectIOWId=0"),array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);
            else  $select->join(array('b' => 'Proj_Schedule'), new Expression("a.WBSId = b.WBSId and b.ProjectIOWId=0"),array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);

            $select->columns(array('WBSId','WBSName','LastLevel','Parent'=>new Expression("ParentId")))
                ->where(array('a.ProjectId' => $iProjectId, 'a.ParentId' => 0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $shParent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'WBSList'));
            if ($typename=="P") $select->join(array('b' => 'Proj_SchedulePlan'), new Expression("a.WBSId = b.WBSId and b.ProjectIOWId=0"),array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);
            else $select->join(array('b' => 'Proj_Schedule'), new Expression("a.WBSId = b.WBSId and b.ProjectIOWId=0"),array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);

            $select->columns(array('WBSId','WBSName','LastLevel','Parent'=>new Expression("ParentId")))
                ->where("a.ProjectId = $iProjectId and a.ParentId != 0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shChild = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->wbssharray = $shChild;

            $select = $sql->select();
            if ($typename=="P") $select->from(array('a' => 'Proj_ScheduleDetailsPlan'));
            else $select->from(array('a' => 'Proj_ScheduleDetails'));
            $select->columns(array('ProjectIOWId','WBSId','SDate'=>new Expression("Format(SDate,'dd-MM-yyyy')"),'SQty','CQty','Holiday','Freeze'))
                ->where(array('a.ProjectId' => $iProjectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $shdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->shdetails= $shdetails;

            $select = $sql->select();
            if ($typename=="P") $select->from(array('a' => 'Proj_Schedule'));
            else $select->from(array('a' => 'Proj_Schedule'));
            $select->columns(array('ProjectIOWId','WBSId','Qty'))
                ->where("a.ProjectId = '$iProjectId' and ProjectIOWId<>0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shQty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->shQty= $shQty;

            $select = $sql->select();
            $select->from(array('a' => 'Proj_WBSTrans'))
                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId = b.ProjectIOWId', array('WBSName'=>new Expression("b.Specification")));

            if ($typename=="P") $select->join(array('c' => 'Proj_SchedulePlan'), new Expression("a.WBSId = c.WBSId and a.ProjectIOWId=c.ProjectIOWId"),array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);
            else $select->join(array('c' => 'Proj_Schedule'), new Expression("a.WBSId = c.WBSId and a.ProjectIOWId=c.ProjectIOWId"),array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);

            $select->columns(array('WBSId','ProjectIOWId'))
                ->where("a.ProjectId = $iProjectId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->shWBSIOWTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $this->scheduleTrans=array();
            foreach($shParent as $row) {
                $shArr =  array();

                $iParentId = $row['WBSId'];
                $arr = array();
                $this->taskId =  $this->taskId + 1;
                $iPTaskId  = $this->taskId ;

                $iDuration =  intval($this->bsf->isNullCheck($row['Duration'],'number'));
                if ($iDuration ==0) $iDuration =1;
                $dSDate =  $this->bsf->isNullCheck($row['StartDate'],'date');
                $dEDate =  $this->bsf->isNullCheck($row['EndDate'],'date');

                $shArr['TaskID'] = $this->taskId;
                $shArr['TaskName'] = $row['WBSName'];
                $shArr['StartDate'] = date('d/m/Y',strtotime($dSDate));
                $shArr['EndDate'] = date('d/m/Y',strtotime($dEDate));
                $shArr['Duration'] = $iDuration;
                $shArr['Progress'] = "";
                $shArr['parent'] = $row['Parent'];
                $shArr['Predecessor'] = "";
                $shArr['iowid'] = "";
                $shArr['wbsid'] = $row['WBSId'];

                $this->scheduleTrans[$this->taskId] =$shArr;

                if ($row['LastLevel'] == 1) {
                    $arr = array_filter($this->shWBSIOWTrans, function ($v) use ($iParentId) {
                        return $v['WBSId'] == $iParentId;
                    });
                    $this->_generateiowarray($iPTaskId,$arr);
                } else {
                    $arr = array_filter($this->wbssharray, function ($v) use ($iParentId) {
                        return $v['Parent'] == $iParentId;
                    });
                    $this->_generatewbstreearray($iPTaskId, $arr);
                }
            }

            $select = $sql->select();
            $select->from('Proj_SchPredecessors')
                ->columns(array('ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag'))
                ->where("ProjectId = $iProjectId and PProjectIOWId !=0")
                ->order(array('WBSId Asc','ProjectIOWId  ASC'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $predarr = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $iIOWId =0;
            $iWBSId =0;
            $sPred ="";
            $iTaskId =0;

            foreach($predarr as $row) {
                $arr = array();
                $parr = array();
                $iPTaskId=0;
                $sTask="";

                if ($iWBSId != intval($row['WBSId']) ||  $iIOWId !=intval($row['ProjectIOWId'])) {

                    if ($iTaskId !=0 && $sPred != "") {
                        $sPred = rtrim($sPred, ",");
                        $this->scheduleTrans[$iTaskId]['Predecessor'] =  $sPred;
                    }

                    $iWBSId =intval($row['WBSId']);
                    $iIOWId = intval($row['ProjectIOWId']);
                    $iPWBSId = intval($row['PWBSId']);
                    $iPIOWId = intval($row['PProjectIOWId']);
                    $sPred ="";
                    $iTaskId =0;

                    $arr = array_filter($this->scheduleTrans, function ($v) use ($iWBSId,$iIOWId) {
                        return $v['wbsid'] == $iWBSId && $v['iowid'] == $iIOWId;
                    });
                    foreach($arr as $arow) {
                        $iTaskId =$arow['TaskID'];
                    }

                    $parr = array_filter($this->scheduleTrans, function ($v) use ($iPWBSId,$iPIOWId) {
                        return $v['wbsid'] == $iPWBSId && $v['iowid'] == $iPIOWId;
                    });
                    foreach($parr as $brow) {
                        $iPTaskId =$brow['TaskID'];
                    }


                    $sTaskType = $row['TaskType'];
                    $iLag = $row['Lag'];

                    $sTask = $iPTaskId .$sTaskType;

                    if ($iLag !=0) {
                        if ($iLag > 0) $sTask =  $sTask . '+' . $iLag;
                        else  $sTask =  $sTask . '-' . $iLag;
                    }
                    if ($sTask !="") $sPred = $sTask;
                } else {

                    $iPWBSId = $row['PWBSId'];
                    $iPIOWId = $row['PProjectIOWId'];
                    $parr = array_filter($this->scheduleTrans, function ($v) use ($iPWBSId,$iPIOWId) {
                        return $v['wbsid'] == $iPWBSId && $v['iowid'] == $iPIOWId;
                    });
                    foreach($parr as $brow) {
                        $iPTaskId =$brow['TaskID'];
                    }
                    $sTaskType = $row['TaskType'];
                    $iLag = $row['Lag'];

                    $sTask = $iPTaskId .$sTaskType;
                    if ($iLag !=0) {
                        if ($iLag > 0) $sTask =  $sTask . '+' . $iLag;
                        else  $sTask =  $sTask . '-' . $iLag;
                    }
                    if ($sTask !="") {
                        if ($sPred !="") $sPred = $sPred. ',' . $sTask;
                        else $sPred = $sTask;
                    }
                }
            }
            if ($iTaskId !=0 && $sPred != "") {
                $sPred = rtrim($sPred, ",");
                $this->scheduleTrans[$iTaskId]['Predecessor'] =  $sPred;
            }

            $this->strText ="";
            $this->taskId=0;

            $sarr = array();
            $sarr = array_filter($this->scheduleTrans, function ($v) use ($iParentId) {
                return $v['parent'] == 0;
            });

            if (!empty($sarr)) $this->strText.="{";
            foreach($sarr as $row) {

                $iParentId = $row['TaskID'];
                $arr = array();
                $this->strText.= '"TaskID" : ' . $row['TaskID'] . ' ,';
                $this->strText.= '"TaskName" : "' . $row['TaskName'] . '" ,';
//                $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
//                $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
                $this->strText.= '"StartDate" : "' . $row['StartDate'] . '" ,';
                $this->strText.= '"EndDate" : "' . $row['EndDate'] . '" ,';
                $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
                $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
                $this->strText.= '"parent" : "' . $row['parent'] . '", ';
                $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
                $this->strText.= '"iowid" : "' . $row['iowid'] . '" , ';
                $this->strText.= '"wbsid" : "' . $row['wbsid'] . '" , ';

                $arr = array_filter($this->scheduleTrans, function ($v) use ($iParentId) {
                    return $v['parent'] == $iParentId;
                });
                $this->_generatetreestringPred($iPTaskId, $arr);
            }
            if ($this->strText !="")   { $this->strText = rtrim($this->strText, ','); $this->strText.="}"; }

            $stdate = date('d/m/Y',strtotime('-4 day'));
            $eddate = date('d/m/Y',strtotime('+60 day'));



            $this->_view->strText =  $this->strText;
            $this->_view->stdate = $stdate;
            $this->_view->eddate = $eddate;
            $this->_view->strHDay = $strHDay;
            $this->_view->weektype= $weektype;
            //$this->_view->hdaylist = $hdaylist;

        } else {
            if (isset($rfcId) && $rfcId != 0) {
                $iProjectId=0;

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCRegister'))
                    ->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId = c.ProjectId', array('ProjectName'))
                    ->where('a.RFCRegisterId = ' . $rfcId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($rfcRegister)) $iProjectId =$rfcRegister['ProjectId'];
                $this->_view->rfcRegister = $rfcRegister;

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
                $select->where(array('RFCRegisterId' => $rfcId, 'Parent' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $shParent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCScheduleDetails'));
                $select->columns(array('ProjectIOWId','WBSId','SDate'=>new Expression("Format(SDate,'dd-MM-yyyy')"),'SQty','CQty','Holiday','Freeze'))
                    ->where(array('a.RFCRegisterId' => $rfcId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $shdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->shdetails= $shdetails;

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCSchedule'));
                $select->columns(array('ProjectIOWId','WBSId','Qty'))
                    ->where("a.RFCRegisterId = '$rfcId' and ProjectIOWId<>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $shQty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->shQty= $shQty;

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
                $select->where("RFCRegisterId = $rfcId and Parent != 0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $shChild = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->wbssharray = $shChild;
                $this->strText ="";

                if (!empty($shParent)) $this->strText.="{";
                foreach($shParent as $row) {
                    $iParentId = $row['Id'];
                    $arr = array();
                    $this->strText.= '"TaskID" : ' . $row['Id'] . ' ,';
                    $this->strText.= '"TaskName" : "' . $row['Specification'] . '" ,';
                    $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
                    $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
                    $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
                    $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
                    $this->strText.= '"parent" : "' . $row['Parent'] . '", ';
                    $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
                    $this->strText.= '"iowid" : "' . $row['ProjectIOWId'] . '" , ';
                    $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';
                    $arr = array_filter($this->wbssharray, function($v) use($iParentId) { return $v['Parent'] == $iParentId; });
                    $this->_generatetreestring($iParentId,$arr);
                }
                if ($this->strText !="")  $this->strText.="}";

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array("EndDate"))
                    ->where(array('RFCRegisterId' => $rfcId))
                    ->order("EndDate DESC")
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($typeResult as $rowDay) {
                    $eddate=date('d/m/Y',strtotime($rowDay['EndDate']));
                }

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array("StartDate"))
                    ->where(array('RFCRegisterId' => $rfcId))
                    ->order("StartDate ASC")
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($typeResult as $rowDayed) {
                    $stdate=date('d/m/Y',strtotime($rowDayed['StartDate']));
                }

                $strHDay = $this->_getHolidays($iProjectId,$dbAdapter);
                $weektype = $this->_getWeeklyHolidays($iProjectId,$dbAdapter);

                $this->_view->strText = $this->strText;
                $this->_view->stdate = $stdate;
                $this->_view->eddate = $eddate;
                $this->_view->strHDay = $strHDay;
                $this->_view->weektype = $weektype;
                //$this->_view->hdaylist = $hdaylist;
            }
        }


        $select = $sql->select();
        $select->from('Proj_WeekHoliday')
            ->columns(array('WeekDay'))
            ->where(array('ProjectId' => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $weekHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_Holiday')
            ->columns(array('HDate'=>new Expression("Format(HDate,'yyyy-MM-dd')")))
            ->where(array('ProjectId' => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $tHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_SchCompletion')
            ->columns(array('ProjectIOWId','WBSId','SDate'=>new Expression("Format(sDate,'dd-MM-yyyy')"),'Qty'))
            ->where(array('ProjectId' => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $shCompQty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_SchCompletion')
            ->columns(array('ProjectIOWId','WBSId','Qty' => new Expression("Sum(Qty)")))
            ->where(array('ProjectId' => $iProjectId))
            ->group(array('ProjectIOWId','WBSId'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $shCompTotQty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        if ($typename=="P") $select->from('Proj_WBSTransPlan');
        else $select->from('Proj_WBSTrans');
        $select->columns(array('ProjectIOWId','WBSId','Qty'))
            ->where(array('ProjectId' => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $shActualQty = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->shCompQty = $shCompQty;
        $this->_view->shCompTotQty = $shCompTotQty;
        $this->_view->shActualQty = $shActualQty;
        $this->_view->weekHoliday = $weekHoliday;
        $this->_view->tHoliday = $tHoliday;

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function updateAction()
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
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);

        //if ($request->isPost()) {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $postParams = $request->getPost();

            $status = 'process';
            $projectId =$this->bsf->isNullCheck($postParams['projectId'],'number');
            $rfcId = $this->bsf->isNullCheck($postParams['rfcId'],'number');
            $rfctype = $this->bsf->isNullCheck($postParams['rfcType'],'string');
            $sRefNo = $this->bsf->isNullCheck($postParams['refNo'],'string');
            if($rfcId == 0) {

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    $aVNo = CommonHelper::getVoucherNo(101, date('Y-m-d', strtotime($postParams['refDate'])), 0, 0, $dbAdapter, "I");

                    if ($aVNo["genType"] == true) {
                        $sVno = $aVNo["voucherNo"];
                    } else {
                        $sVno = $sRefNo;
                    }

                    $insert = $sql->insert();
                    $insert->into('Proj_RFCRegister');
                    $insert->Values(array('RefDate' =>  date('Y-m-d', strtotime($postParams['refDate']))
                    , 'RefNo' => $sVno
                    , 'RFCFrom' => $this->bsf->isNullCheck('Project','string')
                    , 'RFCType' => $rfctype
                    , 'ProjectId' => $projectId
                    , 'ProjectType' => $this->bsf->isNullCheck($postParams['projectType'],'string')
                    , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $rfcRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    for ($i = 0; $i < count($postParams['arrPredVal']); $i++) {
                        $dSDate =  $postParams['arrPredVal'][$i]['stdate'];
                        $dEDate =  $postParams['arrPredVal'][$i]['endate'];
                        $dSDate = str_replace('/', '-', $dSDate);
                        $dEDate = str_replace('/', '-', $dEDate);
                        $iDuration = (int)$postParams['arrPredVal'][$i]['duration'];
                        if ($iDuration ==0) $iDuration=1;

                        $insert = $sql->insert();
                        $insert->into('Proj_RFCSchedule');
                        $insert->Values(array('RFCRegisterId' => $rfcRegisterId, 'ProjectId' => $projectId, 'Id' =>  $postParams['arrPredVal'][$i]['taskid'],
                            'Specification' => $postParams['arrPredVal'][$i]['taskName'], 'StartDate' => date('Y-m-d', strtotime($dSDate)), 'EndDate' => date('Y-m-d', strtotime($dEDate)), 'Duration' => $iDuration,
                            'Progress' => $postParams['arrPredVal'][$i]['prgress'], 'Predecessor' => $postParams['arrPredVal'][$i]['prdecessor'], 'Parent' => $postParams['arrPredVal'][$i]['parent'],
                            'ProjectIOWId' => $postParams['arrPredVal'][$i]['iowid'], 'WBSId' => $postParams['arrPredVal'][$i]['wbsid'],'Qty' => $postParams['arrPredVal'][$i]['qty']
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $rfcTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        for ($j = 0; $j < count($postParams['arrPredVal'][$i]['arrPred']); $j++) {
                            $sTaskType = $this->bsf->isNullCheck($postParams['arrPredVal'][$i]['arrPred'][$j]['tasktype'],'string');
                            if ($sTaskType != "") {
                                $dFDate = $postParams['arrPredVal'][$i]['arrPred'][$j]['fdate'];
                                $dFDate = str_replace('/', '-', $dFDate);

                                $insert = $sql->insert();
                                $insert->into('Proj_RFCSchPredecessors');
                                $insert->Values(array('RFCTransId' => $rfcTransId, 'ProjectIOWId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['iowid'], 'WBSId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['wbsid'],
                                    'PProjectIOWId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['piowid'], 'PWBSId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['pwbsid'],
                                    'TaskType' => $sTaskType, 'Lag' => $postParams['arrPredVal'][$i]['arrPred'][$j]['lag'],
                                    'FDate' => date('Y-m-d', strtotime($dFDate))));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }

                    $arrshdetails = json_decode($postParams['arrshdetails']);
                    foreach ($arrshdetails as $data) {
                        $dSDate = $data->SDate;
                        if (is_null($dSDate) || empty($dSDate)) continue;
                        $dSDate = str_replace('/', '-', $dSDate);

                        $insert = $sql->insert();
                        $insert->into('Proj_RFCScheduleDetails');
                        $insert->Values(array('RFCRegisterId' => $rfcRegisterId,'ProjectId' => $projectId, 'ProjectIOWId' => $data->ProjectIOWId, 'WBSId' => $data->WBSId,
                            'SDate' => date('Y-m-d', strtotime($dSDate)), 'SQty' =>$data->SQty,'CQty' =>$data->CQty,
                            'Holiday' => $data->Holiday));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_SchCompletion')
                        ->where(array('ProjectId'=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $arrcompdetails = json_decode($postParams['arrcompdetails']);
                    foreach ($arrcompdetails as $data) {
                        $dSDate = $data->SDate;
                        if (is_null($dSDate) || empty($dSDate)) continue;
                        $dSDate = str_replace('/', '-', $dSDate);

                        $insert = $sql->insert();
                        $insert->into('Proj_SchCompletion');
                        $insert->Values(array('ProjectId' => $projectId, 'ProjectIOWId' => $data->ProjectIOWId, 'WBSId' => $data->WBSId,
                            'sDate' => date('Y-m-d', strtotime($dSDate)), 'Qty' =>$data->Qty));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $status = 'success';
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Request-For-Creation-Add','N',$rfctype,$rfcRegisterId,0, 0, 'Project',$sVno,$userId, 0 ,0);
                } catch(PDOException $e){
                    $connection->rollback();
                }
            } else {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $update = $sql->update();
                    $update->table('Proj_RFCRegister');
                    $update->set(array('RefDate' =>  date('Y-m-d', strtotime($postParams['refDate']))
                    , 'RefNo' => $this->bsf->isNullCheck($postParams['refNo'],'string')
                    , 'RFCType' => $this->bsf->isNullCheck($postParams['rfcType'],'string')));
                    $update->where(array('RFCRegisterId'=>$rfcId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $subQuery1 = $sql->select();
                    $subQuery1->from('Proj_RFCSchedule')
                        ->columns(array('RFCTransId'))
                        ->where("RFCRegisterId=$rfcId");

                    $delete = $sql->delete();
                    $delete->from('Proj_RFCSchPredecessors')
                        ->where->expression('RFCTransId IN ?', array($subQuery1));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_RFCScheduleDetails')
                        ->where("RFCRegisterId=$rfcId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Proj_RFCSchedule')
                        ->where("RFCRegisterId=$rfcId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    for ($i = 0; $i < count($postParams['arrPredVal']); $i++) {

                        $dSDate =  $postParams['arrPredVal'][$i]['stdate'];
                        $dEDate =  $postParams['arrPredVal'][$i]['endate'];
                        $dSDate = str_replace('/', '-', $dSDate);
                        $dEDate = str_replace('/', '-', $dEDate);
                        $iDuration = (int)$postParams['arrPredVal'][$i]['duration'];
                        if ($iDuration ==0) $iDuration=1;

                        $insert = $sql->insert();
                        $insert->into('Proj_RFCSchedule');
                        $insert->Values(array('RFCRegisterId' => $rfcRegisterId, 'ProjectId' => $projectId, 'Id' =>  $postParams['arrPredVal'][$i]['taskid'],
                            'Specification' => $postParams['arrPredVal'][$i]['taskName'], 'StartDate' => date('Y-m-d', strtotime($dSDate)), 'EndDate' => date('Y-m-d', strtotime($dEDate)), 'Duration' => $iDuration,
                            'Progress' => $postParams['arrPredVal'][$i]['prgress'], 'Predecessor' => $postParams['arrPredVal'][$i]['prdecessor'], 'Parent' => $postParams['arrPredVal'][$i]['parent'],
                            'ProjectIOWId' => $postParams['arrPredVal'][$i]['iowid'], 'WBSId' => $postParams['arrPredVal'][$i]['wbsid'],'Qty' => $postParams['arrPredVal'][$i]['qty']
                        ));

                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $rfcTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        for ($j = 0; $j < count($postParams['arrPredVal'][$i]['arrPred']); $j++) {
                            $dFDate = $postParams['arrPredVal'][$i]['arrPred'][$j]['fdate'];
                            $dFDate = str_replace('/', '-', $dFDate);

                            $insert = $sql->insert();
                            $insert->into('Proj_RFCSchPredecessors');
                            $insert->Values(array('RFCTransId' => $rfcTransId, 'ProjectIOWId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['iowid'], 'WBSId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['wbsid'],
                                'PProjectIOWId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['piowid'], 'PWBSId' => $postParams['arrPredVal'][$i]['arrPred'][$j]['pwbsid'],
                                'TaskType' => $sTaskType, 'Lag' => $postParams['arrPredVal'][$i]['arrPred'][$j]['lag'],
                                'FDate' => date('Y-m-d', strtotime($dFDate))));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    var_dump($postParams['arrshdetails']);

                    for ($i = 0; $i < count($postParams['arrshdetails']); $i++) {
                        $dSDate = $postParams['arrshdetails'][$i]['SDate'];
                        if (is_null($dSDate) || empty($dSDate)) continue;
                        $dSDate = str_replace('/', '-', $dSDate);

                        $insert = $sql->insert();
                        $insert->into('Proj_RFCScheduleDetails');
                        $insert->Values(array('RFCRegisterId' => $rfcRegisterId,'ProjectId' => $projectId, 'ProjectIOWId' => $postParams['arrshdetails'][$i]['ProjectIOWId'], 'WBSId' => $postParams['arrshdetails'][$i]['WBSId'],
                            'SDate' => date('Y-m-d', strtotime($dSDate)), 'SQty' =>$postParams['arrshdetails'][$i]['SQty'],'CQty' =>$postParams['arrshdetails'][$i]['CQty'],
                            'Holiday' => $postParams['arrshdetails'][$i]['Holiday']));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $delete = $sql->delete();
                    $delete->from('Proj_SchCompletion')
                        ->where(array('ProjectId'=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $arrcompdetails = json_decode($postParams['arrcompdetails']);
                    foreach ($arrcompdetails as $data) {
                        $dSDate = $data->SDate;
                        if (is_null($dSDate) || empty($dSDate)) continue;
                        $dSDate = str_replace('/', '-', $dSDate);

                        $insert = $sql->insert();
                        $insert->into('Proj_SchCompletion');
                        $insert->Values(array('ProjectId' => $projectId, 'ProjectIOWId' => $data->ProjectIOWId, 'WBSId' => $data->WBSId,
                            'sDate' => date('Y-m-d', strtotime($dSDate)), 'Qty' =>$data->Qty));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }


                    $status = 'success';
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Request-For-Creation-Edit','E',$rfctype,$rfcId,0, 0, 'Project',$sRefNo,$userId, 0 ,0);
                } catch(PDOException $e){
                    $connection->rollback();
                }
            }
            $response = $this->getResponse();
            $response->setContent($status);
            return $response;
        }
        return $this->_view;
    }

    public function scheduleviewAction()
    {
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

        $this->_view->strText = '';
        $this->_view->projectId = '';
        $this->_view->typeName = '';

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $iProjectId=$postData['projectId'];
            $icount = 0;
            $stdate = "";
            $eddate = "";
            $strText = "";
            $ParentId = 0;
            $typename = $postData['typeName'];
            $this->strText ="";

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_SchedulePlan'));
            else $select->from(array('v' => 'Proj_Schedule'));
            $select->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
            $select->where(array('ProjectId' => $iProjectId, 'Parent' => $ParentId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $shParent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_SchedulePlan'));
            else $select->from(array('v' => 'Proj_Schedule'));
            $select->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
            $select->where("ProjectId = $iProjectId and Parent != $ParentId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shChild = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->wbssharray = $shChild;

            if (!empty($shParent)) $this->strText.="{";

            foreach($shParent as $row) {
                $iParentId = $row['Id'];
                $arr = array();
                $this->strText.= '"TaskID" : ' . $row['Id'] . ' ,';
                $this->strText.= '"TaskName" : "' . $row['Specification'] . '" ,';
                $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
                $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
                $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
                $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
                $this->strText.= '"parent" : "' . $row['Parent'] . '", ';
                $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
                $this->strText.= '"iowid" : "' . $row['ProjectIOWId'] . '" , ';
                $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';
                $arr = array_filter($this->wbssharray, function($v) use($iParentId) { return $v['Parent'] == $iParentId; });
                $this->_generatetreestring($iParentId,$arr);
            }
            if ($this->strText !="")  $this->strText.="}";

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_SchedulePlan'));
            else $select->from(array('v' => 'Proj_Schedule'));
            $select->columns(array("EndDate"))
                ->where(array('ProjectId' => $iProjectId))
                ->order("EndDate DESC")
                ->limit(1);
            $statement = $sql->getSqlStringForSqlObject($select);
            $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            foreach($typeResult as $rowDay) {
                $eddate=date('d/m/Y',strtotime($rowDay['EndDate']));
            }

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_SchedulePlan'));
            else $select->from(array('v' => 'Proj_Schedule'));
            $select->columns(array("StartDate"))
                ->where(array('ProjectId' => $iProjectId))
                ->order("StartDate ASC")
                ->limit(1);
            $statement = $sql->getSqlStringForSqlObject($select);
            $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            foreach($typeResult as $rowDayed) {
                $stdate=date('d/m/Y',strtotime($rowDayed['StartDate']));
            }

            $strHDay = $this->_getHolidays($iProjectId,$dbAdapter);
            $weektype = $this->_getWeeklyHolidays($iProjectId,$dbAdapter);

            $this->_view->strText = $this->strText;
            $this->_view->stdate = $stdate;
            $this->_view->eddate = $eddate;
            $this->_view->strHDay = $strHDay;
            $this->_view->weektype = $weektype;
            $this->_view->projectId = $postData['projectId'];
            $this->_view->typeName = $postData['typeName'];
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId', 'ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function _generatetreestring($parentId,$sharray)
    {
        if (empty($sharray)) return;
        $this->strText.='"Children": [';
        foreach($sharray as $row) {
            $this->strText.='{';
            $iParentId = $row['Id'];
            $arr = array();
            $this->strText.= '"TaskID" : ' . $row['Id'] . ' ,';
            $this->strText.= '"TaskName" : "' . $row['Specification'] . '" ,';
            $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
            $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
            $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
            $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
            $this->strText.= '"parent" : "' . $row['Parent'] . '", ';
            $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
            $this->strText.= '"iowid" : "' . $row['ProjectIOWId'] . '" , ';
            $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';
            $arr = array_filter($this->wbssharray, function($v) use($iParentId) { return $v['Parent'] == $iParentId; });
            $this->_generatetreestring($iParentId,$arr);
            $this->strText.="},";
        }
        $this->strText = rtrim($this->strText, ',');
        $this->strText.= '] ';
    }


    public function _generatetreestringPred($parentId,$sharray)
    {
        if (empty($sharray)) return;
        $this->strText.='"Children": [';
        foreach($sharray as $row) {
            $this->strText.='{';
            $iParentId = $row['TaskID'];
            $arr = array();
            $this->strText.= '"TaskID" : ' . $row['TaskID'] . ' ,';
            $this->strText.= '"TaskName" : "' . $row['TaskName'] . '" ,';
//            $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
//            $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
            $this->strText.= '"StartDate" : "' . $row['StartDate'] . '" ,';
            $this->strText.= '"EndDate" : "' . $row['EndDate'] . '" ,';
            $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
            $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
            $this->strText.= '"parent" : "' . $row['parent'] . '", ';
            $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
            $this->strText.= '"iowid" : "' . $row['iowid'] . '" , ';
            $this->strText.= '"wbsid" : "' . $row['wbsid'] . '" , ';
            $arr = array_filter($this->scheduleTrans, function($v) use($iParentId) { return $v['parent'] == $iParentId; });
            $this->_generatetreestringPred($iParentId,$arr);
            $this->strText.="},";
        }
        $this->strText = rtrim($this->strText, ',');
        $this->strText.= '] ';
    }


    public function _generateiowarray($parentId,$sharray)
    {
        if (empty($sharray)) return;
        foreach($sharray as $row) {
            $shArr = array();

            $iDuration =  intval($this->bsf->isNullCheck($row['Duration'],'number'));
            if ($iDuration ==0) $iDuration =1;
            $dSDate =  $this->bsf->isNullCheck($row['StartDate'],'date');
            $dEDate =  $this->bsf->isNullCheck($row['EndDate'],'date');
            $this->taskId =  $this->taskId + 1;

            $shArr['TaskID'] = $this->taskId;
            $shArr['TaskName'] = $row['WBSName'];
            $shArr['StartDate'] = date('d/m/Y',strtotime($dSDate));
            $shArr['EndDate'] = date('d/m/Y',strtotime($dEDate));
            $shArr['Duration'] = $iDuration;
            $shArr['Progress'] = "";
            $shArr['parent'] = $parentId;
            $shArr['Predecessor'] = "";
            $shArr['iowid'] = $row['ProjectIOWId'];
            $shArr['wbsid'] = $row['WBSId'];

            $this->scheduleTrans[$this->taskId] =$shArr;
        }
    }




    public function _generateiowstring($parentId,$sharray)
    {
        if (empty($sharray)) return;
        $this->strText.='"Children": [';
        foreach($sharray as $row) {

            $iDuration =  intval($this->bsf->isNullCheck($row['Duration'],'number'));
            if ($iDuration ==0) $iDuration =1;
            $dSDate =  $this->bsf->isNullCheck($row['StartDate'],'date');
            $dEDate =  $this->bsf->isNullCheck($row['EndDate'],'date');

            $this->strText.='{';
            $this->taskId =  $this->taskId + 1;
            $this->strText.= '"TaskID" : ' . $this->taskId . ' ,';
            $this->strText.= '"TaskName" : "' . $row['WBSName'] . '" ,';
            $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($dSDate)) . '" ,';
            $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($dEDate)). '" ,';
            $this->strText.= '"Duration" : ' . $iDuration . ' ,';
            $this->strText.= '"Progress" : "' . "" . '" ,';
            $this->strText.= '"parent" : "' . $parentId . '", ';
            $this->strText.= '"Predecessor" : "' . "" . '" , ';
            $this->strText.= '"iowid" : "' . $row['ProjectIOWId'] . '" , ';
            $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';
            $this->strText.="},";
        }
        $this->strText = rtrim($this->strText, ',');
        $this->strText.= '] ';
    }


    public function _generatewbstreearray($parentId,$sharray)
    {
        if (empty($sharray)) return;
        foreach($sharray as $row) {
            $shArr = array();

            $iDuration =  intval($this->bsf->isNullCheck($row['Duration'],'number'));
            if ($iDuration ==0) $iDuration =1;
            $dSDate =  $this->bsf->isNullCheck($row['StartDate'],'date');
            $dEDate =  $this->bsf->isNullCheck($row['EndDate'],'date');
            $this->taskId =  $this->taskId + 1;
            $iParentId = $row['WBSId'];
            $iPTaskId = $this->taskId ;

            $shArr['TaskID'] = $this->taskId;
            $shArr['TaskName'] = $row['WBSName'];
            $shArr['StartDate'] = date('d/m/Y',strtotime($dSDate));
            $shArr['EndDate'] = date('d/m/Y',strtotime($dEDate));
            $shArr['Duration'] = $iDuration;
            $shArr['Progress'] = "";
            $shArr['parent'] = $parentId;
            $shArr['Predecessor'] = "";
            $shArr['iowid'] = "";
            $shArr['wbsid'] = $row['WBSId'];

            $this->scheduleTrans[$this->taskId] =$shArr;

            $arr = array();
            if ($row['LastLevel'] == 1) {
                $arr = array_filter($this->shWBSIOWTrans, function ($v) use ($iParentId) {
                    return $v['WBSId'] == $iParentId;
                });
                $this->_generateiowarray($iPTaskId,$arr);
            } else {
                $arr = array_filter($this->wbssharray, function ($v) use ($iParentId) {
                    return $v['Parent'] == $iParentId;
                });
                $this->_generatewbstreearray($iPTaskId, $arr);
            }
        }
    }


    public function _generatewbstreestring($parentId,$sharray)
    {
        if (empty($sharray)) return;
        $this->strText.='"Children": [';
        foreach($sharray as $row) {

            $iDuration =  intval($this->bsf->isNullCheck($row['Duration'],'number'));
            if ($iDuration ==0) $iDuration =1;
            $dSDate =  $this->bsf->isNullCheck($row['StartDate'],'date');
            $dEDate =  $this->bsf->isNullCheck($row['EndDate'],'date');

            $this->strText.='{';
            $iParentId = $row['WBSId'];
            $this->taskId =  $this->taskId + 1;
            $iPTaskId = $this->taskId ;
            $arr = array();
            $this->strText.= '"TaskID" : ' . $this->taskId . ' ,';
            $this->strText.= '"TaskName" : "' . $row['WBSName'] . '" ,';
            $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($dSDate)) . '" ,';
            $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($dEDate)) . '" ,';
            $this->strText.= '"Duration" : ' . $iDuration . ' ,';
            $this->strText.= '"Progress" : "' . "" . '" ,';
            $this->strText.= '"parent" : "' . $parentId . '", ';
            $this->strText.= '"Predecessor" : "' . "" . '" , ';
            $this->strText.= '"iowid" : "' . "" . '" , ';
            $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';

            if ($row['LastLevel'] == 1) {
                $arr = array_filter($this->shWBSIOWTrans, function ($v) use ($iParentId) {
                    return $v['WBSId'] == $iParentId;
                });
                $this->_generateiowstring($iPTaskId,$arr);
            } else {
                $arr = array_filter($this->wbssharray, function ($v) use ($iParentId) {
                    return $v['Parent'] == $iParentId;
                });
                $this->_generatewbstreestring($iPTaskId, $arr);
            }
            $this->strText.="},";
        }
        $this->strText = rtrim($this->strText, ',');
        $this->strText.= '] ';
    }

//    function filter_by_value ($array, $value){
//        $newarray = array();
//        if(is_array($array) && count($array)>0)
//        {
//            foreach($array as $key){
//                if ($key["Parent"] == $value){
//                    $newarray[] = $key;
//                }
//            }
//        }
//        return $newarray;
//    }

    public function wbsScheduleAction()
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

        $rfcId = $this->params()->fromRoute('rfcId');
        $this->_view->rfcId = (isset($rfcId) && $rfcId != 0) ? $rfcId : 0;

        $icount = 0;
        $iProjectId = 0;
        $stdate = "";
        $eddate = "";
        $iParentId=0;

        $strHDay = "";

        $aVNo = CommonHelper::getVoucherNo(101, date('Y/m/d'), 0, 0, $dbAdapter, "");
        if ($aVNo["genType"] == false)
            $this->_view->svNo = "";
        else
            $this->_view->svNo = $aVNo["voucherNo"];

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            if (!is_null($postData['frm_what'])) {
                // get project list
                $iProjectId = $this->bsf->isNullCheck($postData['project_id'], 'number');
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'))
                    ->where(array('ProjectId' =>$iProjectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $typename= $postData['type_name'];
                $this->_view->frmwhat = $postData['frm_what'];
                $this->_view->projecttype = $typename;
                if ($typename == 'B')
                    $this->_view->projecttypename = 'Budget';
                else if ($typename == 'P')
                    $this->_view->projecttypename = 'Plan';
            }

            $strHDay = $this->_getHolidays($iProjectId,$dbAdapter);
            $weektype = $this->_getWeeklyHolidays($iProjectId,$dbAdapter);

            $select = $sql->select();
            $select->from(array('a' => 'Proj_WBSMaster'));
            if ($typename=="P") $select->join(array('b' => 'Proj_WBSSchedulePlan'), "a.WBSId = b.WBSId",array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);
            else $select->join(array('b' => 'Proj_WBSSchedule'), "a.WBSId = b.WBSId",array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);

            $select->columns(array('WBSId','WBSName','LastLevel','Parent'=>new Expression("ParentId")))
                ->where(array('a.ProjectId' => $iProjectId, 'a.ParentId' => 0));
            $statement = $sql->getSqlStringForSqlObject($select);
            $shParent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            $select->from(array('a' => 'Proj_WBSMaster'));
            if ($typename=="P") $select->join(array('b' => 'Proj_WBSSchedulePlan'), "a.WBSId = b.WBSId",array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);
            else $select->join(array('b' => 'Proj_WBSSchedule'), "a.WBSId = b.WBSId",array('Duration','StartDate','EndDate'), $select:: JOIN_LEFT);

            $select->columns(array('WBSId','WBSName','LastLevel','Parent'=>new Expression("ParentId")))
                ->where("a.ProjectId = $iProjectId and a.ParentId != 0");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shChild = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->wbssharray = $shChild;
            $this->strText ="";
            $this->taskId=0;
            $this->shWBSIOWTrans = array();

            $this->scheduleTrans=array();
            foreach($shParent as $row) {
                $shArr =  array();

                $iParentId = $row['WBSId'];
                $arr = array();
                $this->taskId =  $this->taskId + 1;
                $iPTaskId  = $this->taskId ;

                $iDuration =  intval($this->bsf->isNullCheck($row['Duration'],'number'));
                if ($iDuration ==0) $iDuration =1;
                $dSDate =  $this->bsf->isNullCheck($row['StartDate'],'date');
                $dEDate =  $this->bsf->isNullCheck($row['EndDate'],'date');

                $shArr['TaskID'] = $this->taskId;
                $shArr['TaskName'] = $row['WBSName'];
                $shArr['StartDate'] = date('d/m/Y',strtotime($dSDate));
                $shArr['EndDate'] = date('d/m/Y',strtotime($dEDate));
                $shArr['Duration'] = $iDuration;
                $shArr['Progress'] = "";
                $shArr['parent'] = $row['Parent'];
                $shArr['Predecessor'] = "";
                $shArr['iowid'] = "";
                $shArr['wbsid'] = $row['WBSId'];

                $this->scheduleTrans[$this->taskId] =$shArr;

                $arr = array_filter($this->wbssharray, function ($v) use ($iParentId) {
                    return $v['Parent'] == $iParentId;
                });
                $this->_generatewbstreearray($iPTaskId, $arr);

            }

            $select = $sql->select();
            $select->from('Proj_WBSSchPredecessors')
                ->columns(array('WBSId','PWBSId','TaskType','Lag'))
                ->where("ProjectId = $iProjectId and PWBSId !=0")
                ->order(array('WBSId Asc'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $predarr = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            $iWBSId =0;
            $sPred ="";
            $iTaskId =0;

            foreach($predarr as $row) {
                $arr = array();
                $parr = array();
                $iPTaskId=0;
                $sTask="";

                if ($iWBSId != intval($row['WBSId'])) {

                    if ($iTaskId !=0 && $sPred != "") {
                        $sPred = rtrim($sPred, ",");
                        $this->scheduleTrans[$iTaskId]['Predecessor'] =  $sPred;
                    }

                    $iWBSId =intval($row['WBSId']);
                    $iPWBSId = intval($row['PWBSId']);
                    $sPred ="";
                    $iTaskId =0;

                    $arr = array_filter($this->scheduleTrans, function ($v) use ($iWBSId) {
                        return $v['wbsid'] == $iWBSId;
                    });
                    foreach($arr as $arow) {
                        $iTaskId =$arow['TaskID'];
                    }

                    $parr = array_filter($this->scheduleTrans, function ($v) use ($iPWBSId) {
                        return $v['wbsid'] == $iPWBSId;
                    });
                    foreach($parr as $brow) {
                        $iPTaskId =$brow['TaskID'];
                    }


                    $sTaskType = $row['TaskType'];
                    $iLag = $row['Lag'];

                    $sTask = $iPTaskId .$sTaskType;

                    if ($iLag !=0) {
                        if ($iLag > 0) $sTask =  $sTask . '+' . $iLag;
                        else  $sTask =  $sTask . '-' . $iLag;
                    }
                    if ($sTask !="") $sPred = $sTask;
                } else {
                    $iPWBSId = $row['PWBSId'];
                    $parr = array_filter($this->scheduleTrans, function ($v) use ($iPWBSId) {
                        return $v['wbsid'] == $iPWBSId;
                    });
                    foreach($parr as $brow) {
                        $iPTaskId =$brow['TaskID'];
                    }
                    $sTaskType = $row['TaskType'];
                    $iLag = $row['Lag'];

                    $sTask = $iPTaskId .$sTaskType;
                    if ($iLag !=0) {
                        if ($iLag > 0) $sTask =  $sTask . '+' . $iLag;
                        else  $sTask =  $sTask . '-' . $iLag;
                    }
                    if ($sTask !="") {
                        if ($sPred !="") $sPred = $sPred. ',' . $sTask;
                        else $sPred = $sTask;
                    }
                }
            }
            if ($iTaskId !=0 && $sPred != "") {
                $sPred = rtrim($sPred, ",");
                $this->scheduleTrans[$iTaskId]['Predecessor'] =  $sPred;
            }

            $this->strText ="";
            $this->taskId=0;

            $sarr = array();
            $sarr = array_filter($this->scheduleTrans, function ($v) use ($iParentId) {
                return $v['parent'] == 0;
            });

            if (!empty($sarr)) $this->strText.="{";
            foreach($sarr as $row) {
                $iParentId = $row['TaskID'];
                $arr = array();
                $this->strText.= '"TaskID" : ' . $row['TaskID'] . ' ,';
                $this->strText.= '"TaskName" : "' . $row['TaskName'] . '" ,';
                $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
                $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
                $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
                $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
                $this->strText.= '"parent" : "' . $row['parent'] . '", ';
                $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
                $this->strText.= '"iowid" : "' . $row['iowid'] . '" , ';
                $this->strText.= '"wbsid" : "' . $row['wbsid'] . '" , ';

                $arr = array_filter($this->scheduleTrans, function ($v) use ($iParentId) {
                    return $v['parent'] == $iParentId;
                });
                $this->_generatetreestringPred($iPTaskId, $arr);
            }
            if ($this->strText !="")  $this->strText.="}";


//            if (!empty($shParent)) $this->strText.="{";
//            foreach($shParent as $row) {
//                $iParentId = $row['WBSId'];
//                $arr = array();
//                $this->taskId =  $this->taskId + 1;
//                $iPTaskId  = $this->taskId ;
//
//                $iDuration =  intval($this->bsf->isNullCheck($row['Duration'],'number'));
//                if ($iDuration ==0) $iDuration =1;
//                $dSDate =  $this->bsf->isNullCheck($row['StartDate'],'date');
//                $dEDate =  $this->bsf->isNullCheck($row['EndDate'],'date');
//
//                $this->strText.= '"TaskID" : ' . $this->taskId . ' ,';
//                $this->strText.= '"TaskName" : "' . $row['WBSName'] . '" ,';
//                $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($dSDate)) . '" ,';
//                $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($dEDate)) . '" ,';
//                $this->strText.= '"Duration" : ' . $iDuration . ' ,';
//                $this->strText.= '"Progress" : "' . "" . '" ,';
//                $this->strText.= '"parent" : "' . $row['Parent'] . '", ';
//                $this->strText.= '"Predecessor" : "' . "" . '" , ';
//                $this->strText.= '"iowid" : "' . "" . '" , ';
//                $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';
//                $arr = array_filter($this->wbssharray, function($v) use($iParentId) { return $v['Parent'] == $iParentId; });
//                $this->_generatewbstreestring($iPTaskId,$arr);
//            }
//            $this->strText.="}";

            $stdate = date('d/m/Y',strtotime('-4 day'));
            $eddate = date('d/m/Y',strtotime('+60 day'));

            $this->_view->strText =  $this->strText;;
            $this->_view->stdate = $stdate;
            $this->_view->eddate = $eddate;
            $this->_view->strHDay = $strHDay;
            $this->_view->weektype= $weektype;
//            $this->_view->hdaylist = $hdaylist;
        } else {
            if (isset($rfcId) && $rfcId != 0) {
                $iProjectId=0;

                $select = $sql->select();
                $select->from(array('a' => 'Proj_RFCRegister'))
                    ->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId = c.ProjectId', array('ProjectName'))
                    ->where('a.RFCRegisterId = ' . $rfcId);
                $statement = $sql->getSqlStringForSqlObject($select);
                $rfcRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!(empty($rfcRegister))) $iProjectId =$rfcRegister['ProjectId'];
                $this->_view->rfcRegister = $rfcRegister;

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
                $select->where(array('RFCRegisterId' => $rfcId, 'Parent' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $shParent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
                $select->where("RFCRegisterId = $rfcId and Parent != 0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $shChild = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->wbssharray = $shChild;
                $this->strText ="";

                if (!empty($shParent)) $this->strText.="{";
                foreach($shParent as $row) {
                    $iParentId = $row['Id'];
                    $arr = array();
                    $this->strText.= '"TaskID" : ' . $row['Id'] . ' ,';
                    $this->strText.= '"TaskName" : "' . $row['Specification'] . '" ,';
                    $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
                    $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
                    $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
                    $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
                    $this->strText.= '"parent" : "' . $row['Parent'] . '", ';
                    $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
                    $this->strText.= '"iowid" : "' . $row['ProjectIOWId'] . '" , ';
                    $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';
                    $arr = array_filter($this->wbssharray, function($v) use($iParentId) { return $v['Parent'] == $iParentId; });
                    $this->_generatetreestring($iParentId,$arr);
                }
                if ($this->strText !="")  $this->strText.="}";

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array("EndDate"))
                    ->where(array('RFCRegisterId' => $rfcId))
                    ->order("EndDate DESC")
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($typeResult as $rowDay) {
                    $eddate=date('d/m/Y',strtotime($rowDay['EndDate']));
                }

                $select = $sql->select();
                $select->from(array('v' => 'Proj_RFCSchedule'))
                    ->columns(array("StartDate"))
                    ->where(array('RFCRegisterId' => $rfcId))
                    ->order("StartDate ASC")
                    ->limit(1);
                $statement = $sql->getSqlStringForSqlObject($select);
                $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($typeResult as $rowDayed) {
                    $stdate=date('d/m/Y',strtotime($rowDayed['StartDate']));
                }

                $strHDay = $this->_getHolidays($iProjectId,$dbAdapter);
                $weektype = $this->_getWeeklyHolidays($iProjectId,$dbAdapter);

                $this->_view->strText = $this->strText;
                $this->_view->stdate = $stdate;
                $this->_view->eddate = $eddate;
                $this->_view->strHDay = $strHDay;
                $this->_view->weektype = $weektype;
//                $this->_view->hdaylist = $hdaylist;
            }
        }

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    function _getWeeklyHolidays($iProjectId,$dbAdapter) {

        $weektype ="";

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_WeekHoliday')
            ->columns(array('WeekDay'))
            ->where(array('ProjectId' => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $weekhdaylist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($weekhdaylist as $wrow) {
            if ($wrow['WeekDay']=='Sunday') {
                $weektype =$weektype . '0' . ',';
            } else if ($wrow['WeekDay']=='Monday') {
                $weektype =$weektype . '1' . ',';
            } else if ($wrow['WeekDay']=='Tuesday') {
                $weektype =$weektype . '2' . ',';
            } else if ($wrow['WeekDay']=='Wednesday') {
                $weektype =$weektype . '3' . ',';
            } else if ($wrow['WeekDay']=='Tuesday') {
                $weektype =$weektype . '4' . ',';
            } else if ($wrow['WeekDay']=='Friday') {
                $weektype =$weektype . '5' . ',';
            } else if ($wrow['WeekDay']=='Saturday') {
                $weektype =$weektype . '6' . ',';
            }
        }
        $weektype = rtrim($weektype,',');

        return $weektype;
    }

    function _getHolidays($iProjectId,$dbAdapter) {

        $strHDay ="";

        $sql = new Sql($dbAdapter);
        $select = $sql->select();
        $select->from('Proj_Holiday')
            ->columns(array("day"=>'HDate', "label"=>'Note'))
            ->where(array('ProjectId' => $iProjectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $hdaylist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $ihStart = 0;
        foreach($hdaylist as $hrow) {
            if ($ihStart==0) {
                $strHDay.= "{";
            } else {
                $strHDay.= ", {";
            }

            $strHDay.= 'day : "' . date("m-d-Y",strtotime($hrow['day'])) . '",';
            $strHDay.= 'label : "' . $hrow['label'] . ' ",';
            $strHDay.= 'background : "' . "yellowgreen" .'"}';

            $ihStart = $ihStart+1;
        }

        return $strHDay;
    }




//    function _approveFromScheduleRFC($rfcid)
//    {
//
//        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
//        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
//        $sql = new Sql($dbAdapter);
//
//        $connection = $dbAdapter->getDriver()->getConnection();
//        $connection->beginTransaction();
//        try {
//            $select = $sql->select();
//            $select->from('Proj_RFCRegister')
//                ->columns(array('RFCType', 'ProjectId', 'ProjectType'))
//                ->where(array("RFCRegisterId='$rfcid'"));
//
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $rfcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//            $rfctype = "";
//            $iProjectId = 0;
//            $sProjectType = "";
//            if (!empty($rfcregister)) {
//                $rfctype = $this->bsf->isNullCheck($rfcregister['RFCType'], 'string');
//                $iProjectId = $this->bsf->isNullCheck($rfcregister['ProjectId'], 'number');
//                $sProjectType = $this->bsf->isNullCheck($rfcregister['ProjectType'], 'string');
//            }
//
//            $select = $sql->select();
//            $select->from('Proj_WeekHoliday')
//                ->columns(array('WeekDay'))
//                ->where(array("ProjectId='$iProjectId'"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $weekHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//            $select = $sql->select();
//            $select->from('Proj_Holiday')
//                ->columns(array('HDate'))
//                ->where(array("ProjectId='$iProjectId'"));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $tHoliday = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//            if ($rfctype = "Schedule-Add") {
//
//                $iShId = $this->_getScheduleName($iProjectId, $sProjectType, $rfcid, $dbAdapter);
//                $this->_scheduleCopy($iProjectId,$sProjectType,$iShId,$dbAdapter);
//
//                $select = $sql->select();
//                $select->from('Proj_RFCSchedule');
//                $select->columns(array('ProjectId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','Qty','RFCTransId'))
//                    ->where(array("RFCRegisterId=$rfcid"));
//
//                $insert = $sql->insert();
//                if ($sProjectType =="P") $insert->into('Proj_SchedulePlan');
//                else $insert->into('Proj_Schedule');
//
//                $insert->columns(array('ProjectId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','Qty','RFCTransId'));
//                $insert->Values($select);
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                $select = $sql->select();
//                $select->from('Proj_RFCSchPredecessors');
//                $select->columns(array('ProjectId' =>new Expression("$iProjectId") ,'ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType','RFCTransId'))
//                    ->where(array("ProjectId=$iProjectId"));
//
//                $insert = $sql->insert();
//                if ($sProjectType =="P") $insert->into('Proj_SchPredecessorsPlan');
//                else $insert->into('Proj_SchPredecessors');
//                $insert->columns(array('ProjectId','ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType', 'RFCTransId'));
//                $insert->Values($select);
//                $statement = $sql->getSqlStringForSqlObject($insert);
//                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                $select = $sql->select();
//                if ($sProjectType =="P") $select->from('Proj_SchedulePlan');
//                else $select->from('Proj_Schedule');
//                $select->columns(array('ProjectId','ShTransId','StartDate','EndDate','Duration','Qty'))
//                       ->where(array("ProjectId=$iProjectId"));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $shtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                foreach ($shtrans as $trans) {
//                    $iShTransId = $trans[ShTransId];
//                    $dStartDate= $trans[StartDate];
//                    $dEndDate= $trans[EndDate];
//                    $iDuration= intval($trans[Duration]);
//                    $dQty= floatval($trans[Duration]);
//                    $dTQty =0;
//                    $dSplitQty =0;
//                    if ($iDuration !=0) $dSplitQty = $dQty/$iDuration;
//                    $sDate = date('m-d-Y',strtotime($dStartDate));
//                    while (strtotime($sDate) <= strtotime($dEndDate)) {
//                        $bHoliday = $this->_checkHoliDay($tHoliday,$weekHoliday,$sDate);
//                        if ($bHoliday==false) {
//                            $insert = $sql->insert();
//                            if ($sProjectType == "P") $insert->into('Proj_ScheduleDetailsPlan');
//                            else $insert->into('Proj_ScheduleDetails');
//                            $insert->Values(array('ProjectId' => $iProjectId, 'ShTransId' => $iShTransId, 'sDate' => date('Y-m-d', strtotime($sDate)),
//                                'Qty' => $dSplitQty));
//                            $statement = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            $iTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//                            $dTQty = $dTQty + $dSplitQty;
//                        }
//                        $sDate = date('m-d-Y',strtotime($dStartDate. "+1 days"));
//                        if (strtotime($sDate) > strtotime($dEndDate)) {
//                            if ($dTQty != $dQty) {
//                               $dFinalQty = $dSplitQty + ($dQty-$dTQty);
//                                $update = $sql->update();
//                                if ($sProjectType=="P") $update->table('Proj_ScheduleDetailsPlan');
//                                else $update->table('Proj_ScheduleDetails');
//                                $update->set(array(
//                                    'Qty' => $dFinalQty,
//                                ));
//                                $update->where(array('TransId' => $iTransId));
//                                $statement = $sql->getSqlStringForSqlObject($update);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            }
//                        }
//                    }
//                }
//            }
//            $connection->commit();
//        } catch (PDOException $e) {
//            $connection->rollback();
//            print "Error!: " . $e->getMessage() . "</br>";
//        }
//    }


//    function _checkHoliDay($nHoliday,$weekHoliday,$argDate) {
//        $bFound = false;
//
//        if (in_array($argDate, $nHoliday)) {
//            $bFound = true;
//        }
//        if ($bFound == false) {
//            $sWeekDay = date('l', $argDate);
//            if (in_array($sWeekDay, $weekHoliday)) {
//                $bFound = true;
//            }
//        }
//        return $bFound;
//    }

//    function _getScheduleName($argProjectId,$argType,$argRFCId,$dbAdapter)
//    {
//        $iShId=0;
//        try {
//            $iWidth = 0;
//            $iMaxNo = 0;
//            $iVNo = 0;
//            $iLen = 0;
//            $sPre = "";
//            $sPrefix = "";
//            $sSuffix = "";
//            $sSeperator = "";
//
//            $sStage = "";
//            if ($argType =="P") {
//                $sStage = "Plan";
//            } else if ($argType =="B") {
//                $sStage = "Budget";
//            }
//
//            $iMaxShId=0;
//            $select = $sql->select();
//            $select->from('Proj_ScheduleMaster')
//                ->columns(array('ScheduleId'=>new Expression("Max(ScheduleId)")))
//                ->where(array("ProjectId"=>$argProjectId,'RevisionType'=>'$argType'));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $maxrevmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//            if (!empty($maxrevmaster)) {
//                $iMaxShId = $revmaster['ScheduleId'];
//            }
//            $iShId=$iMaxShId;
//
//            $sql     = new Sql($dbAdapter);
//            $select = $sql->select();
//            $select->from('Proj_ScheduleNameSetup')
//                ->columns(array('Prefix','Width','Suffix','Seperator'))
//                ->where(array("StageName"=>'$sStage'));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $namesetup = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//            if (!empty($namesetup)) {
//                $iWidth = $namesetup['Width'];
//                $sPrefix = $namesetup['Prefix'];
//                $sSuffix = $namesetup['Suffix'];
//                $sSeperator = $namesetup['Seperator'];
//            }
//
//            $select = $sql->select();
//            $select->from('Proj_ScheduleMaster')
//                ->columns(array('OrderId'=>new Expression("Max(OrderId)")))
//                ->where(array("ProjectId"=>$argProjectId,'RevisionType'=>'$argType'));
//            $statement = $sql->getSqlStringForSqlObject($select);
//            $revmaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//            if (!empty($revmaster)) {
//                $iMaxNo = $revmaster['OrderId'];
//            }
//
//            $iVNo = $iMaxNo + 1;
//
//            $iLen = $iWidth - strlen($iVNo);
//            $sPre = "";
//            for($i = 1; $i < $iLen; $i++) {
//                $sPre = $sPre."0";
//            }
//
//            $shname = $sPrefix.$sSeperator.$sPre.trim($iVNo);
//            if ($sSuffix != "") {
//                $shname =  $shname.$sSeperator.$sSuffix;
//            }
//
//            $insert = $sql->insert();
//            $insert->into('Proj_ScheduleNameSetup');
//            $insert->Values(array('OrderId' => $iVNo, 'ProjectId' => $argProjectId,
//                'ScheduleName' => $shname, 'RevisionType'=> $argType,
//                'RFCRegisterId' => $argRFCId));
//            $statement = $sql->getSqlStringForSqlObject($insert);
//            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//        } catch (Zend_Exception $e) {
//            echo "Error: " . $e->getMessage() . "</br>";
//        }
//
//        return $iShId;
//    }
//
//    function _scheduleCopy($iProjectId,$sProjectType,$iShId, $dbAdapter)
//    {
//
//        $sql = new Sql($dbAdapter);
//
//        //Proj_Schedule
//        $select = $sql->select();
//        if ($sProjectType == "P") $select->from('Proj_SchedulePlan');
//        else $select->from('Proj_Schedule');
//        $select->columns(array('ProjectId','ShTransId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','Qty','RFCTransId','ScheduleId' => new Experession("$iShId")))
//            ->where(array("ProjectId=$iProjectId"));
//
//        $insert = $sql->insert();
//        $insert->into('Proj_ScheduleTrans');
//        $insert->columns(array('ProjectId','ShTransId','Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId','Qty','RFCTransId','ScheduleId'));
//        $insert->Values($select);
//        $statement = $sql->getSqlStringForSqlObject($insert);
//        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//        //Proj_ScheduleDetails
//        $select = $sql->select();
//        if ($sProjectType == "P") $select->from('Proj_ScheduleDetailsPlan');
//        else $select->from('Proj_ScheduleDetails');
//        $select->columns(array('ProjectId','ShTransId','sDate','Qty', 'ScheduleId' => new Experession("$iShId")))
//            ->where(array("ProjectId=$iProjectId"));
//
//        $insert = $sql->insert();
//        $insert->into('Proj_ScheduleDetailsTrans');
//        $insert->columns(array('ProjectId','ShTransId','sDate','Qty', 'ScheduleId'));
//        $insert->Values($select);
//        $statement = $sql->getSqlStringForSqlObject($insert);
//        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//        //Proj_Predecessors
//        $select = $sql->select();
//        if ($sProjectType == "P") $select->from('Proj_SchPredecessorsPlan');
//        else $select->from('Proj_SchPredecessors');
//        $select->columns(array('ProjectId','ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType','RFCTransId', 'ScheduleId' => new Experession("$iShId")))
//            ->where(array("ProjectId=$iProjectId"));
//
//        $insert = $sql->insert();
//        $insert->into('Proj_SchPredecessorsTrans');
//        $insert->columns(array('ProjectId','ProjectIOWId','WBSId','PProjectIOWId','PWBSId','TaskType','Lag','FDate','PType','PPType','RFCTransId','ScheduleId'));
//        $insert->Values($select);
//        $statement = $sql->getSqlStringForSqlObject($insert);
//        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//
//    }

    public function wbsscheduleviewAction(){
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

        $this->_view->strText = '';
        $this->_view->projectId = '';
        $this->_view->typeName = '';

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            $iProjectId=$postData['projectId'];
            $icount = 0;
            $stdate = "";
            $eddate = "";
            $strText = "";
            $ParentId = 0;
            $typename = $postData['typeName'];
            $this->strText ="";

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_WBSSchedulePlan'));
            else $select->from(array('v' => 'Proj_WBSSchedule'));
            $select->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
            $select->where(array('ProjectId' => $iProjectId, 'Parent' => $ParentId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $shParent = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_WBSSchedulePlan'));
            else $select->from(array('v' => 'Proj_WBSSchedule'));
            $select->columns(array('Id','Specification','StartDate','EndDate','Duration','Progress','Predecessor','Parent','ProjectIOWId','WBSId'));
            $select->where("ProjectId = $iProjectId and Parent != $ParentId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $shChild = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->wbssharray = $shChild;

            if (!empty($shParent)) $this->strText.="{";

            foreach($shParent as $row) {
                $iParentId = $row['Id'];
                $arr = array();
                $this->strText.= '"TaskID" : ' . $row['Id'] . ' ,';
                $this->strText.= '"TaskName" : "' . $row['Specification'] . '" ,';
                $this->strText.= '"StartDate" : "' . date('d/m/Y',strtotime($row['StartDate'])) . '" ,';
                $this->strText.= '"EndDate" : "' . date('d/m/Y',strtotime($row['EndDate'])) . '" ,';
                $this->strText.= '"Duration" : ' . $row['Duration'] . ' ,';
                $this->strText.= '"Progress" : "' . $row['Progress'] . '" ,';
                $this->strText.= '"parent" : "' . $row['Parent'] . '", ';
                $this->strText.= '"Predecessor" : "' . $row['Predecessor'] . '" , ';
                $this->strText.= '"iowid" : "' . $row['ProjectIOWId'] . '" , ';
                $this->strText.= '"wbsid" : "' . $row['WBSId'] . '" , ';
                $arr = array_filter($this->wbssharray, function($v) use($iParentId) { return $v['Parent'] == $iParentId; });
                $this->_generatetreestring($iParentId,$arr);
            }
            if ($this->strText !="")  $this->strText.="}";

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_WBSSchedulePlan'));
            else $select->from(array('v' => 'Proj_WBSSchedule'));
            $select->columns(array("EndDate"))
                ->where(array('ProjectId' => $iProjectId))
                ->order("EndDate DESC")
                ->limit(1);
            $statement = $sql->getSqlStringForSqlObject($select);
            $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            foreach($typeResult as $rowDay) {
                $eddate=date('d/m/Y',strtotime($rowDay['EndDate']));
            }

            $select = $sql->select();
            if ($typename=="P") $select->from(array('v' => 'Proj_WBSSchedulePlan'));
            else $select->from(array('v' => 'Proj_WBSSchedule'));
            $select->columns(array("StartDate"))
                ->where(array('ProjectId' => $iProjectId))
                ->order("StartDate ASC")
                ->limit(1);
            $statement = $sql->getSqlStringForSqlObject($select);
            $typeResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            foreach($typeResult as $rowDayed) {
                $stdate=date('d/m/Y',strtotime($rowDayed['StartDate']));
            }

            $strHDay = $this->_getHolidays($iProjectId,$dbAdapter);
            $weektype = $this->_getWeeklyHolidays($iProjectId,$dbAdapter);

            $this->strText;

            $this->_view->strText = $this->strText;
            $this->_view->stdate = $stdate;
            $this->_view->eddate = $eddate;
            $this->_view->strHDay = $strHDay;
            $this->_view->weektype = $weektype;
            $this->_view->projectId = $postData['projectId'];
            $this->_view->typeName = $postData['typeName'];
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId', 'ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectLists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//        $shArr['StartDate'] = date('d/m/Y',strtotime($dSDate));
//        $shArr['EndDate'] = date('d/m/Y',strtotime($dEDate));

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }
}