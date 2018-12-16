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

class CommunicationController extends AbstractActionController
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
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Project Communication");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

		if($this->getRequest()->isXmlHttpRequest())	{
            $this->_view->setTerminal(true);
			$request = $this->getRequest();
			if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                switch($Type) {
                    case 'getwbs':
                        $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number');
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_WBSMaster'))
                            ->columns(array('id' => 'WBSId', 'parentid' => 'ParentId', 'text' => "WBSName"))
                            ->where("a.ProjectId=$ProjectId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

                        if(count($result) > 0)
                            return $this->getResponse()->setContent($result);
                        else
                            return $this->getResponse()->setStatusCode('201')
                                ->setContent('No data.');
                        break;
                    case 'getiows':
                        $WBSId = $this->bsf->isNullCheck($this->params()->fromPost('WBSId'), 'number');
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_WBSTrans'))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId' , array('SerialNo'=>new Expression("RefSerialNo"), 'Specification', 'WorkGroupId'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId' , array('UnitName'), $select::JOIN_LEFT)
                            ->columns(array('WBSId', 'ProjectIOWId', 'Qty'))
                            ->where("a.WBSId=$WBSId AND b.UnitId<>0");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($result) > 0) {
                            $subQuery = $sql->select();
                            $subQuery->from(array('a' => 'Proj_WBSTrans'))
                                ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId' , array('WorkGroupId'), $select::JOIN_LEFT)
                                ->columns(array())
                                ->group("b.WorkGroupId")
                                ->where("a.WBSId=$WBSId AND b.UnitId<>0");

                            $select = $sql->select();
                            $select->from(array('a' => 'Proj_WorkGroupWorkChecklistTrans'))
                                ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId' , array('CheckListId','CheckListName'), $select::JOIN_LEFT)
                                ->columns(array('WorkGroupId'))
                                ->where->expression('a.WorkGroupId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_workchks = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array('a' => 'PC_AssignedChecklist'))
                                ->where("a.WBSId=$WBSId");
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_selchks = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array('a' => 'Proj_WorkGroupSafetyChecklistTrans'))
                                ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId' , array('CheckListId','CheckListName'), $select::JOIN_LEFT)
                                ->columns(array('WorkGroupId'))
                                ->where->expression('a.WorkGroupId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_safetychks = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array('a' => 'Proj_WorkGroupQualityChecklistTrans'))
                                ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId' , array('CheckListId','CheckListName'), $select::JOIN_LEFT)
                                ->columns(array('WorkGroupId'))
                                ->where->expression('a.WorkGroupId IN ?', array($subQuery));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arr_qualitychks = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            return $this->getResponse()->setContent(json_encode(array('wbs'=> $result, 'workchks' => $arr_workchks,
                                'selchks' => $arr_selchks,
                                'safetychks' => $arr_safetychks, 'qualitychks' => $arr_qualitychks)));
                        } else
                            return $this->getResponse()->setStatusCode('201')
                                ->setContent('No data.');
                        break;
                    case 'savechks':
                        $postData = $request->getPost();
                        try {
                            $connection = $dbAdapter->getDriver()->getConnection();
                            $connection->beginTransaction();

                            $ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number');
                            $rowcount = $this->bsf->isNullCheck($postData['rowcount'], 'number');
                            for ($i = 1; $i <= $rowcount; $i++) {
                                $WBSId = $this->bsf->isNullCheck($postData['WBSId_'.$i], 'number');
                                $ProjectIOWId = $this->bsf->isNullCheck($postData['ProjectIOWId_'.$i], 'number');

                                $delete = $sql->delete();
                                $delete->from('PC_AssignedChecklist')
                                    ->where(array('WBSId' => $WBSId, 'ProjectIOWId' => $ProjectIOWId));
                                $statement = $sql->getSqlStringForSqlObject($delete);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                //work chks
                                $workrowcount = $this->bsf->isNullCheck($postData['wtype_'.$i.'_workchklist'], 'number');
                                for ($j = 1; $j <= $workrowcount; $j++) {
                                    $CheckListId = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workchklist_' . $j], 'number');
                                    $UserId = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workchkuser_' . $j], 'number');
//                                    $IsCritical = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workcritical_' . $j], 'number');
                                    $sPriority = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workpriority_' . $j], 'string');

                                    $WhenType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workwhentype_' . $j], 'string');
                                    $WhenPeriod = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workwhenperiod_' . $j], 'number');
                                    $WhenPeriodType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workwhenperiodtype_' . $j], 'string');
                                    $FreqPeriod = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workfreqperiod_' . $j], 'number');
                                    $FreqPeriodType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_workfreqperiodtype_' . $j], 'string');

                                    if($CheckListId == 0 || $UserId == 0)
                                        continue;

                                    $insert = $sql->insert();
                                    $insert->into('PC_AssignedChecklist');
                                    $insert->Values(array('WBSId' => $WBSId, 'ProjectIOWId' => $ProjectIOWId, 'CheckListId' => $CheckListId,
                                        'UserId' => $UserId,'Priority' => $sPriority,'WhenType' => $WhenType,'WhenPeriod' => $WhenPeriod,
                                        'WhenPeriodType' => $WhenPeriodType,'FrequencyPeriod' => $FreqPeriod, 'ProjectId' => $ProjectId,
                                        'FrequencyPeriodType' => $FreqPeriodType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                // safety chks
                                $safetyrowcount = $this->bsf->isNullCheck($postData['wtype_'.$i.'_safetychklist'], 'number');
                                for ($j = 1; $j <= $safetyrowcount; $j++) {
                                    $CheckListId = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetychklist_' . $j], 'number');
                                    $UserId = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetychkuser_' . $j], 'number');
//                                    $IsCritical = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetycritical_' . $j], 'number');
                                    $sPriority = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetypriority_' . $j], 'string');
                                    $WhenType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetywhentype_' . $j], 'string');
                                    $WhenPeriod = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetywhenperiod_' . $j], 'number');
                                    $WhenPeriodType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetywhenperiodtype_' . $j], 'string');
                                    $FreqPeriod = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetyfreqperiod_' . $j], 'number');
                                    $FreqPeriodType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_safetyfreqperiodtype_' . $j], 'string');

                                    if($CheckListId == 0 || $UserId == 0)
                                        continue;

                                    $insert = $sql->insert();
                                    $insert->into('PC_AssignedChecklist');
                                    $insert->Values(array('WBSId' => $WBSId, 'ProjectIOWId' => $ProjectIOWId,'CheckListId' => $CheckListId,
                                        'UserId' => $UserId,'Priority' => $sPriority,'WhenType' => $WhenType,'WhenPeriod' => $WhenPeriod,
                                        'WhenPeriodType' => $WhenPeriodType,'FrequencyPeriod' => $FreqPeriod, 'ProjectId' => $ProjectId,
                                        'FrequencyPeriodType' => $FreqPeriodType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                // quality chks
                                $qualityrowcount = $this->bsf->isNullCheck($postData['wtype_'.$i.'_qualitychklist'], 'number');
                                for ($j = 1; $j <= $qualityrowcount; $j++) {
                                    $CheckListId = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualitychklist_' . $j], 'number');
                                    $UserId = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualitychkuser_' . $j], 'number');
//                                    $IsCritical = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualitycritical_' . $j], 'number');
                                    $sPriority = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualitypriority_' . $j], 'string');
                                    $WhenType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualitywhentype_' . $j], 'string');
                                    $WhenPeriod = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualitywhenperiod_' . $j], 'number');
                                    $WhenPeriodType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualitywhenperiodtype_' . $j], 'string');
                                    $FreqPeriod = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualityfreqperiod_' . $j], 'number');
                                    $FreqPeriodType = $this->bsf->isNullCheck($postData['wtype_' . $i . '_qualityfreqperiodtype_' . $j], 'string');

                                    if($CheckListId == 0 || $UserId == 0)
                                        continue;

                                    $insert = $sql->insert();
                                    $insert->into('PC_AssignedChecklist');
                                    $insert->Values(array('WBSId' => $WBSId, 'ProjectIOWId' => $ProjectIOWId,'CheckListId' => $CheckListId,
                                        'UserId' => $UserId,'Priority' => $sPriority,'WhenType' => $WhenType,'WhenPeriod' => $WhenPeriod,
                                        'WhenPeriodType' => $WhenPeriodType,'FrequencyPeriod' => $FreqPeriod, 'ProjectId' => $ProjectId,
                                        'FrequencyPeriodType' => $FreqPeriodType));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            $connection->commit();
                            return $this->getResponse()->setStatusCode('200')
                                ->setContent('Success');
                        } catch(PDOException $e){
                            $connection->rollback();
                            return $this->getResponse()->setStatusCode('400')
                                ->setContent('Error');
                        }
                        break;
                    case 'default':
                            return $this->getResponse()->setStatusCode('400')
                                ->setContent('Bad Request');
                        break;
                }
			}
		} else {
			$request = $this->getRequest();
			if ($request->isPost()) {
				
			} else {
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId', 'UserName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->userlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }

			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			return $this->_view;
		}
	}

    public function useractivitiesAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Project Communication");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $userId = $this->auth->getIdentity()->UserId;
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $this->_view->setTerminal(true);
            $request = $this->getRequest();
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                switch($Type) {
                    case 'gethistory':
                        $TaskId = $this->bsf->isNullCheck($this->params()->fromPost('TaskId'), 'number');
                        $select = $sql->select();
                        $select->from(array('a' => 'PC_UserTaskTrans'))
                            ->join(array('d' => 'WF_Users'), 'a.UserId=d.UserId' , array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                            ->columns(array('TaskTransId', 'FileURL','TDate' => new Expression("CONVERT(VARCHAR(19), a.TDate)"), 'Status', 'Remarks'))
                            ->where("a.TaskId=$TaskId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = json_encode($dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray());

                        if(count($result) > 0)
                            return $this->getResponse()->setContent($result);
                        else
                            return $this->getResponse()->setStatusCode('201')
                                ->setContent('No data.');
                        break;
                    case 'savehistory':
                        try {
                            $postData = $request->getPost();
                            $TaskId = $this->bsf->isNullCheck($postData['taskid'], 'number');
                            $UserId = $this->bsf->isNullCheck($postData['userid'], 'number');
                            $Status = $this->bsf->isNullCheck($postData['status'], 'string');
                            $Remarks = $this->bsf->isNullCheck($postData['remarks'], 'string');

                            $files = $request->getFiles();
                            $FileURL = '';
                            if ($files['file']['name']) {
                                $dir = 'public/uploads/project/communication/useractivities/' .$TaskId.'/';
                                $filename = $this->bsf->uploadFile($dir, $files['file']);

                                if ($filename)
                                    $FileURL = '/uploads/project/communication/useractivities/' .$TaskId.'/'. $filename;
                            }

                            $connection = $dbAdapter->getDriver()->getConnection();
                            $connection->beginTransaction();


                            $insert = $sql->insert();
                            $insert->into('PC_UserTaskTrans');
                            $insert->Values(array('TDate' => date('Y-m-d H:i:s'), 'TaskId' => $TaskId, 'UserId' => $UserId,
                                'Status' => $Status,'Remarks' => $Remarks, 'FileURL' => $FileURL));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $TaskTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $update = $sql->update();
                            $update->table('PC_UserTask');
                            $update->set(array('Status' => $Status))
                                ->where(array('TaskId'=>$TaskId));
                            $stmt = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                            $connection->commit();

                            if($TaskTransId > 0) {
                                $select = $sql->select();
                                $select->from(array('a' => 'PC_UserTaskTrans'))
                                    ->join(array('d' => 'WF_Users'), 'a.UserId=d.UserId', array('UserName', 'UserLogo'), $select::JOIN_LEFT)
                                    ->columns(array('TaskTransId', 'FileURL', 'TDate' => new Expression("CONVERT(VARCHAR(19), a.TDate)"), 'Status', 'Remarks'))
                                    ->where("a.TaskTransId=$TaskTransId");
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                return $this->getResponse()->setStatusCode('200')
                                    ->setContent(json_encode($result));
                            } else {
                                return $this->getResponse()->setStatusCode('201')
                                    ->setContent('Failed');
                            }
                        } catch(PDOException $e){
                            $connection->rollback();
                            return $this->getResponse()->setStatusCode('400')
                                ->setContent('Error');
                        }
                        break;
                    case 'getusertasklists':
                        $FromDate = $this->bsf->isNullCheck($this->params()->fromPost('FromDate'), 'string');
                        $ToDate = $this->bsf->isNullCheck($this->params()->fromPost('ToDate'), 'string');
                        $TaskTypeId = $this->bsf->isNullCheck($this->params()->fromPost('TaskTypeId'), 'number');
                        $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number');
                        $TaskId = $this->bsf->isNullCheck($this->params()->fromPost('TaskId'), 'number');
                        $userId = $this->bsf->isNullCheck($this->params()->fromPost('UserId'), 'number');

                        $select = $sql->select();
                        $select->from(array('a' => 'PC_UserTask'))
                            ->join(array('b' => 'PC_UserTaskTrans'), 'b.TaskId = a.TaskId', array('RecentStatus' => new Expression("ISNULL(b.Status,'')")), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId' , array('Desc' => new Expression("c.SerialNo + ' ' + c.Specification")), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_CheckListMaster'), 'a.CheckListId=d.CheckListId' , array('CheckListName'), $select::JOIN_LEFT)
                            ->join(array('e' => 'WF_Users'), 'a.UserId=e.UserId' , array('UserName'), $select::JOIN_LEFT)
                            ->columns(array('Status','TaskId', 'UserId','Qty', 'CDate' => new Expression("CONVERT(VARCHAR(19),a.CDate)"), 'r' => new Expression('rank() over (partition by a.TaskId order by b.TaskTransId desc)')))
                            ->group(new Expression('a.Status,a.TaskId,a.UserId,a.Qty,a.CDate,b.Status,b.TaskTransId,c.SerialNo,c.Specification,d.CheckListName,e.UserName'));

                        $whereCond = 'a.TaskId != 0 ';
                        if($FromDate != '')
                            $whereCond .= " AND a.CDate >= '". date('Y-m-d', strtotime($FromDate)) . "'";

                        if($ToDate != '')
                            $whereCond .= " AND a.CDate <= '". date('Y-m-d', strtotime($ToDate)) . "'";

                        if($TaskId !=0)
                            $whereCond .= ' AND a.TaskId='.$TaskId;

                        if($TaskTypeId !=0)
                            $whereCond .= ' AND d.TypeId='.$TaskTypeId;

                        if($ProjectId !=0)
                            $whereCond .= ' AND a.ProjectId='.$ProjectId;

                        $select->where($whereCond);

                        $rankselect = $sql->select();
                        $rankselect->from(array('g'=>$select))
                            ->columns(array('*'))
                            ->where("g.r=1 AND g.userId='$userId'");
                        $statement = $sql->getSqlStringForSqlObject($rankselect);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($result) > 0)
                            return $this->getResponse()->setContent(json_encode($result));
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
            $request = $this->getRequest();
            if ($request->isPost()) {

            } else {
                $select = $sql->select();
                $select->from(array('a' => 'PC_UserTask'))
                    ->join(array('b' => 'PC_UserTaskTrans'), 'b.TaskId = a.TaskId', array('RecentStatus' => new Expression("ISNULL(b.Status,'')")), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=c.ProjectIOWId' , array('Desc' => new Expression("c.SerialNo + ' ' + c.Specification")), $select::JOIN_LEFT)
                    ->join(array('d' => 'Proj_CheckListMaster'), 'a.CheckListId=d.CheckListId' , array('CheckListName'), $select::JOIN_LEFT)
                    ->join(array('e' => 'WF_Users'), 'a.UserId=e.UserId' , array('UserName'), $select::JOIN_LEFT)
                    ->columns(array('Status','TaskId', 'UserId','Qty', 'CDate' => new Expression("CONVERT(VARCHAR(19),a.CDate)"), 'r' => new Expression('rank() over (partition by a.TaskId order by b.TaskTransId desc)')))
                    ->group(new Expression('a.Status,a.TaskId,a.UserId,a.Qty,a.CDate,b.Status,b.TaskTransId,c.SerialNo,c.Specification,d.CheckListName,e.UserName'));

                $rankselect = $sql->select();
                $rankselect->from(array('g'=>$select))
                    ->columns(array('*'))
                    ->where("g.r=1 AND g.userId='$userId'");
                $statement = $sql->getSqlStringForSqlObject($rankselect);
                $this->_view->usertasklists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId', 'UserName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->userlists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $userName = "";
                $select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserName'))
                    ->where(array('UserId'=>$userId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $users = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($users)) $userName = $users['UserName'];

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId', 'ProjectName'))
                    ->where(array('DeleteFlag'=>'0'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projectlist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' => 'PC_UserTask'))
                    ->columns(array('FDate'=>new Expression("Min(CDate)"),'EDate' => new Expression("Max(CDate)")));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if(count($arrDate))
                    $this->_view->arrDate = $arrDate;

                $subQuery = $sql->select();
                $subQuery->from(array('a' => 'PC_UserTask'))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId = b.CheckListId', array(), $subQuery::JOIN_LEFT)
                    ->columns(array('TypeId'=>new Expression('DISTINCT b.TypeId')));

                $select = $sql->select();
                $select->from(array('a' => 'Proj_ChecklistTypeMaster'))
                    ->columns(array('TypeId', 'CheckListTypeName'))
                    ->where->expression('TypeId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arrCheckListType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->userId = $userId;
                $this->_view->userName = $userName;
            }

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

	public function pcreportAction(){
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

            $select = $sql->select();
            $select->from(array('a' => 'PC_UserTask'))
                ->columns(array('FDate'=>new Expression("Min(CDate)"),'EDate' => new Expression("Max(CDate)")));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrDate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $subQuery = $sql->select();
            $subQuery->from('PC_UserTask')
                ->columns(array('UserId'=>new Expression('DISTINCT UserId')));

            $select = $sql->select();
            $select->from(array('a' => 'WF_Users'))
                ->columns(array('UserId', 'UserName'))
                ->where->expression('UserId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrUser = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $subQuery = $sql->select();
            $subQuery->from('PC_UserTask')
                ->columns(array('ProjectId'=>new Expression('DISTINCT ProjectId')));

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ProjectMaster'))
                ->columns(array('ProjectId', 'ProjectName'))
                ->where->expression('ProjectId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrProject = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $subQuery = $sql->select();
            $subQuery->from(array('a' => 'PC_UserTask'))
                ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId = b.CheckListId', array(), $subQuery::JOIN_LEFT)
                ->columns(array('TypeId'=>new Expression('DISTINCT b.TypeId')));

            $select = $sql->select();
            $select->from(array('a' => 'Proj_ChecklistTypeMaster'))
                ->columns(array('TypeId', 'CheckListTypeName'))
                ->where->expression('TypeId IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrCheckListType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
			
			return $this->_view;
		}
	}
    public function gettaskdetailsAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
            $sql = new Sql($dbAdapter);
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $dFromDate= $this->bsf->isNullCheck($postParams['fromDate'],'date');
                $dToDate= $this->bsf->isNullCheck($postParams['toDate'],'date');
                $tasktypeId= $this->bsf->isNullCheck($postParams['tasktypeId'],'number');
                $projectId =  intval($this->bsf->isNullCheck($postParams['projectId'],'number'));
                $userId= $this->bsf->isNullCheck($postParams['userId'],'number');

                $select = $sql->select();
                $select->from(array('a' => 'PC_UserTask'))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array(), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectMaster'), 'a.ProjectId=c.ProjectId', array(), $select::JOIN_LEFT)
                    ->join(array('d' => 'WF_Users'), 'a.UserId=d.UserId', array(), $select::JOIN_LEFT)
                    ->join(array('e' => 'Proj_ChecklistTypeMaster'), 'b.TypeId=e.TypeId', array(), $select::JOIN_LEFT)
                    ->join(array('f' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=f.ProjectIOWId', array(), $select::JOIN_LEFT)
                    ->join(array('g' => 'Proj_WBSMaster'), 'a.WBSId=g.WBSId', array(), $select::JOIN_LEFT)
                    ->columns(array('TaskId','CheckListTypeName' => new Expression("e.CheckListTypeName"), 'CheckListName' => new Expression("b.CheckListName"),
                        'ProjectName' => new Expression("ProjectName"),'Specification'=>new Expression("F.RefSerialNo + '  ' + F.Specification"),'WBSName'=>new Expression("G.WBSName"),
                        'Qty','UserName'=>new Expression("d.UserName"),'CDate','Status'=>new Expression("Case When Status='P' then 'In-Progress' When Status='C' then 'Completed' else '' end")));

                $where = "a.CDate >= '" . date('d-M-Y', strtotime($dFromDate)) . "' and a.CDate <='". date('d-M-Y', strtotime($dToDate)) ."'";
                if ($tasktypeId !=0) $where =  $where . " and b.TypeId  = " . $tasktypeId;
                if ($projectId !=0) $where =  $where . " and a.ProjectId = " . $projectId;
                if ($userId !=0) $where =  $where . " and a.UserId = " . $userId;

                $select->where($where);
                $statement = $sql->getSqlStringForSqlObject($select);
                $details = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $itotTask = 0;
                $itodo = 0;
                $iprgress= 0;
                $icompleted= 0;

                $select = $sql->select();
                $select->from(array('a' => 'PC_UserTask'))
                       ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array(), $select::JOIN_LEFT)
                       ->columns(array('TotalTask'=>new Expression("Count(TaskId)")));
                $select->where($where);
                $statement = $sql->getSqlStringForSqlObject($select);
                $task = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($task)) $itotTask= $task['TotalTask'];

                $select = $sql->select();
                $select->from(array('a' => 'PC_UserTask'))
                       ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array(), $select::JOIN_LEFT)
                    ->columns(array('TotalTask'=>new Expression("Count(TaskId)")));
                $twhere = $where . "and Status=''";
                $select->where($twhere);
                $statement = $sql->getSqlStringForSqlObject($select);
                $task = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($task)) $itodo= $task['TotalTask'];

                $select = $sql->select();
                $select->from(array('a' => 'PC_UserTask'))
                       ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array(), $select::JOIN_LEFT)
                       ->columns(array('TotalTask'=>new Expression("Count(TaskId)")));
                $twhere = $where . "and Status='P'";
                $select->where($twhere);
                $statement = $sql->getSqlStringForSqlObject($select);
                $task = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($task)) $iprgress= $task['TotalTask'];

                $select = $sql->select();
                $select->from(array('a' => 'PC_UserTask'))
                    ->join(array('b' => 'Proj_CheckListMaster'), 'a.CheckListId=b.CheckListId', array(), $select::JOIN_LEFT)
                    ->columns(array('TotalTask'=>new Expression("Count(TaskId)")));
                $twhere = $where . "and Status='C'";
                $select->where($twhere);
                $statement = $sql->getSqlStringForSqlObject($select);
                $task = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if (!empty($task)) $icompleted= $task['TotalTask'];

                $data['details'] = $details;
                $data['totTask'] = $itotTask;
                $data['todo'] = $itodo;
                $data['progress'] = $iprgress;
                $data['completed'] = $icompleted;

                $response = $this->getResponse();
                $response->setContent(json_encode($data));
                return $response;
            }
        }
    }
}