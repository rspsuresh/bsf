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
use Application\View\Helper\CommonHelper;

class WorkcompletionController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function indexAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Completion");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                $this->_view->setTerminal(true);
                $postParams = $request->getPost();

                $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                switch($Type) {
                    case 'resourcelist':
                        /*$WorkType = $this->bsf->isNullCheck($postParams['WorkType'],'string');
                        $whereCond = "c.CostCentreId=$CostCentreId AND c.WOType='$WorkType'";

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WOTrans"));

                        if($WorkType == 'iow') {
                            $select->join(array('b' => 'Proj_ProjectIOWMaster'), 'b.ProjectIOWId=a.IOWId', array('DescId' => 'ProjectIOWId', 'Desc' => new Expression("b.SerialNo + ' ' + b.Specification"), 'Rate'), $select::JOIN_LEFT);
                            $select->group(new Expression('b.ProjectIOWId,b.SerialNo,b.Specification,d.UnitName,b.Rate'));
                            $whereCond .= ' AND b.UnitId <> 0';
                        }

                        $select->join(array('c' => 'WPM_WORegister'), 'a.WORegisterId=c.WORegisterId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array('UnitName'), $select::JOIN_LEFT)
                            ->columns(array('Include' => new Expression('1-1')))
                            ->where($whereCond);
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/
                         $select = $sql->select();
                         $select->from(array('a' => 'WF_OperationalCostCentre'))
                         ->columns(array('ProjectId'))
                         ->where('CostCentreId='.$CostCentreId);
                         $statement = $sql->getSqlStringForSqlObject($select);
                         $ProjectId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                     
                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectIOW"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWID=b.ProjectIOWID',array('DescId' => 'ProjectIOWId','Desc' => new Expression("b.SerialNo + ' ' + b.Specification")))
                            ->join(array('c'=>'Proj_UOM'),' b.UnitId=c.UnitId',array('UnitName'))
                             ->columns(array('ProjectIOWId','Rate','Include' => new Expression('1-1')))
                            ->where("a.ProjectId=".$ProjectId['ProjectId']);
                       $statement = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($result));
                        return $response;
                        break;
                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }
        } else {
            // get cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function entryAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Completion");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);

        $request = $this->getRequest();
        $editId = $this->bsf->isNullCheck($this->params()->fromRoute('editId'), 'number');

        if(!$this->getRequest()->isXmlHttpRequest() && $editId == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('wpm/workcompletion-entry', array('controller' => 'workcompletion', 'action' => 'index'));
        }
        $this->_view->typeWc = '';
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                switch($Type) {
                    case 'getiowdetails':
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSTrans"))
                            ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ProjectIOWId','Qty'))
                            ->where("a.ProjectIOWId=$IOWId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        return $response;
                        break;

                     case 'gettotalqty':
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');
                        $WBSId=  $this->bsf->isNullCheck($this->params()->fromPost('WBSId'), 'number');
                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSTrans"))
                            ->columns(array('ProjectIOWId','Qty'))
                            ->where("a.ProjectIOWId=$IOWId and WBSId= $WBSId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        return $response;
                        break;   
                     case 'getwoqty':
                            $ccId = $this->bsf->isNullCheck($this->params()->fromPost('ccId'), 'number');
                            $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');
                            $WBSId = $this->bsf->isNullCheck($this->params()->fromPost('WBSId'), 'number');
                        
                        $wobArrfull=array();

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WCEIOWTrans"))
                            ->join(array('b' => 'WPM_WCETrans'), 'a.WCETransId=b.WCETransId', array())
                            ->join(array('c' => 'WPM_WCERegister'), 'b.WCERegisterId=c.WCERegisterId', array())
                            ->columns(array('Qty'=>new Expression('Sum(A.Qty)')))
                            ->where("C.CostCentreId=$ccId and A.WBSId=$WBSId and A.IOWId=$IOWId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $wobArr = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArrfull['Qty']=0;

                        $select1 = $sql->select();
                        $select1->from(array("a" => "WPM_WCEIOWTrans"))
                            ->join(array('b' => 'WPM_WCETrans'), 'a.WCETransId=b.WCETransId', array())
                            ->join(array('c' => 'WPM_WCERegister'), 'b.WCERegisterId=c.WCERegisterId', array())
                            ->columns(array('Pqty'=>new Expression('top 1 a.Qty')))
                            ->where("C.CostCentreId=$ccId and A.WBSId=$WBSId and A.IOWId=$IOWId ORDER BY a.WCEIOWTransId DESC");
                        $statement = $sql->getSqlStringForSqlObject($select1);
                        $wobArrful= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $wobArrfull['pqty']=$wobArrful['Pqty'];
                        
                       
                    
                            $response->setStatusCode('200');
                            $response->setContent(json_encode($wobArrfull));
                            return $response;
                            break;    
                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
               
                if (!is_null($postData['frm_index'])) {
                    $CostCentre = $this->bsf->isNullCheck($postData['costCentreId'], 'number');
                    $WorkType= $this->bsf->isNullCheck($postData['WorkType'],'string');

                    $resourceIds = $postData['resourceIds'];

                    if(!is_null($resourceIds) && $resourceIds != '')
                        $resourceIds = trim(implode(',',$resourceIds));
                    else
                        $resourceIds = 0;

                    $FromDate= $this->bsf->isNullCheck($postData['FromDate'],'string');
                    $ToDate = $this->bsf->isNullCheck($postData['ToDate'],'string');

                    // cost center details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                        ->columns( array( 'CostCentreId', 'CostCentreName', 'ProjectId', 'CompanyId' ) )
                        ->where( "Deactivate=0 AND CostCentreId=$CostCentre" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    $projectId = $this->_view->costcenter['ProjectId'];
                    
                    if ($resourceIds != '' && $WorkType == 'iow') {
                        $select = $sql->select();
                        $select->from(array('a' => "Proj_ProjectIOWMaster"))
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitId', 'UnitName'), $select::JOIN_LEFT)
                            ->columns(array('DescId' => 'ProjectIOWId', "Desc" => new Expression("a.SerialNo+ ' ' +a.Specification")))
                            ->where('a.ProjectIOWId IN (' . $resourceIds . ')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_WBSTrans"))
                            ->join(array('b' => 'Proj_WBSMaster'), 'a.WBSId=b.WBSId', array('WBSId', 'WBSName', 'ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ProjectIOWId','Qty'))
                            ->where('a.ProjectIOWId IN (' . $resourceIds . ')');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    }
                      $select = $sql->select();
                      $select->from(array("a" => "Proj_ProjectIOWMaster"))
                            ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT)
                            ->columns(array('value' => new Expression("a.SerialNo+ ' ' +a.Specification"),'data' => 'ProjectIOWId'))
                            ->where("a.ProjectId=".$projectId)
                            ->where('a.UnitId <> 0')
                            ->order('a.ProjectIOWId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
             
                    $this->_view->FromDate = $FromDate;
                    $this->_view->ToDate = $ToDate;
                    $this->_view->WorkType = $WorkType;
                    $this->_view->WorkTypeName = 'IOW';
                    $this->_view->typeWc = 'e';
                  
                } else if($editId == 0) {
                    // add entry
                    //print_r($postData);exit;
                    try {
                        $connection->beginTransaction();

                        $wcaVNo = CommonHelper::getVoucherNo(405, date('Y-m-d', strtotime($postData['WCEDate'])), 0, 0, $dbAdapter, "I");
                        if ($wcaVNo["genType"] == true) {
                            $wcNo = $wcaVNo["voucherNo"];
                        } else {
                            $wcNo = $postData['WCENo'];
                        }

                        $wcccaVNo = CommonHelper::getVoucherNo(405, date('Y-m-d', strtotime($postData['WCEDate'])), 0, $postData['CostCenterId'], $dbAdapter, "I");
                        if ($wcccaVNo["genType"] == true) {
                            $wcCCNo = $wcccaVNo["voucherNo"];
                        } else {
                            $wcCCNo = $postData['CCWCENo'];
                        }

                        $wccoaVNo = CommonHelper::getVoucherNo(405, date('Y-m-d', strtotime($postData['WCEDate'])), $postData['CompanyId'], 0, $dbAdapter, "I");
                        if ($wccoaVNo["genType"] == true) {
                            $wcCoNo = $wccoaVNo["voucherNo"];
                        } else {
                            $wcCoNo = $postData['CompWCENo'];
                        }

                        $insert = $sql->insert();
                        $insert->into('WPM_WCERegister');
                        $insert->Values(array('WCEDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['WCEDate'], 'string')))
                        , 'WCENo' => $this->bsf->isNullCheck($wcNo, 'string')
                        , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                        , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                        , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                        , 'CCWCENo' => $this->bsf->isNullCheck($wcCCNo, 'string')
                        , 'CompWCENo' => $this->bsf->isNullCheck($wcCoNo, 'string')
                        , 'WOType' => $this->bsf->isNullCheck($postData['WorkType'], 'string')
                        , 'CostCentreId' => $this->bsf->isNullCheck($postData['CostCenterId'], 'string')
                        , 'Amount' => $this->bsf->isNullCheck($postData['total'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                        ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $WCEId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //boq
                        $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                        for ($i = 1; $i <= $rowid; $i++) {
                            $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                            $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                            $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                            $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');
                            $checkrate=$this->bsf->isNullCheck($postData['qty_' . $i], 'number');

                            if ($DescId == 0 || $qty==0 )
                                continue;


                            $descIdColumn = 'IOWId';

                            $insert = $sql->insert();
                            $insert->into('WPM_WCETrans');
                            $insert->Values(array('WCERegisterId' => $WCEId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $WCETransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            //print_r($WCETransId);exit;

                            $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                            for ($j = 1; $j <= $iowrowid; $j++) {
                                $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                $WBSId = $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number');
                                $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');
                                $workcomplete=$this->bsf->isNullCheck($postData['iow_' . $i . '_wbwkcom_' . $j], 'number');
                                if (($IOWId == 0 && $WBSId == 0) || $qty == 0)
                                    continue;

                                $insert = $sql->insert();
                                $insert->into('WPM_WCEIOWTrans');
                                $insert->Values(array('WCETransId' => $WCETransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount, 'WorkCompleted' => $workcomplete));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $connection->commit();
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workcompletion', 'action' => 'index'));
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                } else if($editId != 0) {
                    // edit entry // update entry section
                    try {
                        $connection->beginTransaction();
                      
                        $update = $sql->update();
                        $update->table('WPM_WCERegister');
                        $update->set(array('WCEDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['WCEDate'], 'string')))
                        , 'WCENo' => $this->bsf->isNullCheck($postData['WCENo'], 'string')
                        , 'RefDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['RefDate'], 'string')))
                        , 'FDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['FromDate'], 'string')))
                        , 'TDate' => date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['ToDate'], 'string')))
                        , 'RefNo' => $this->bsf->isNullCheck($postData['RefNo'], 'string')
                        , 'CCWCENo' => $this->bsf->isNullCheck($postData['CCWCENo'], 'string')
                        , 'CompWCENo' => $this->bsf->isNullCheck($postData['CompWCENo'], 'string')
                        , 'Amount' => $this->bsf->isNullCheck($postData['total'], 'number')
                        , 'Narration' => $this->bsf->isNullCheck($postData['narration'], 'string')
                        ));
                        $update->where(array('WCERegisterId' => $editId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $subQuery = $sql->select();
                        $subQuery->from('WPM_WCETrans')
                            ->columns(array('WCETransId'))
                            ->where(array('WCERegisterId' => $editId));

                        $delete = $sql->delete();
                        $delete->from('WPM_WCEIOWTrans')
                            ->where->expression('WCETransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('WPM_WCETrans')
                            ->where("WCERegisterId = $editId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                         /* $delete = $sql->delete();
                        $delete->from('WPM_WCERegister')
                            ->where("WCERegisterId = $editId");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);*/

                        //boq
                        $rowid = $this->bsf->isNullCheck($postData['rowid'], 'number');
                        for ($i = 1; $i <= $rowid; $i++) {
                            $DescId = $this->bsf->isNullCheck($postData['descid_' . $i], 'number');
                            $qty = $this->bsf->isNullCheck($postData['qty_' . $i], 'number');
                            $Rate = $this->bsf->isNullCheck($postData['rate_' . $i], 'number');
                            $Amount = $this->bsf->isNullCheck($postData['amount_' . $i], 'number');

                            if ($DescId == 0 ||  $qty==0 )
                                continue;


                            $descIdColumn = 'IOWId';

                            $insert = $sql->insert();
                            $insert->into('WPM_WCETrans');
                            $insert->Values(array('WCERegisterId' => $editId, $descIdColumn => $DescId, 'Qty' => $qty, 'Rate' => $Rate, 'Amount' => $Amount));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $WCETransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            $iowrowid = $this->bsf->isNullCheck($postData['iow_' . $i . '_rowid'], 'number');
                            for ($j = 1; $j <= $iowrowid; $j++) {
                                $IOWId = $this->bsf->isNullCheck($postData['iow_' . $i . '_iowid_' . $j], 'number');
                                $WBSId = $this->bsf->isNullCheck($postData['iow_' . $i . '_wbsid_' . $j], 'number');
                                $Qty = $this->bsf->isNullCheck($postData['iow_' . $i . '_qty_' . $j], 'number');
                                $Rate = $this->bsf->isNullCheck($postData['iow_' . $i . '_rate_' . $j], 'number');
                                $Amount = $this->bsf->isNullCheck($postData['iow_' . $i . '_amount_' . $j], 'number');
                                $workcomplete=$this->bsf->isNullCheck($postData['iow_' . $i . '_wbwkcom_' . $j], 'number');

                                if (($IOWId == 0 && $WBSId == 0) || $qty == 0)
                                    continue;

                                $insert = $sql->insert();
                                $insert->into('WPM_WCEIOWTrans');
                                $insert->Values(array('WCETransId' => $WCETransId, 'IOWId' => $IOWId, 'WBSId' => $WBSId, 'Qty' => $Qty, 'Rate' => $Rate, 'Amount' => $Amount, 'WorkCompleted' => $workcomplete));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        $connection->commit();
                        $this->redirect()->toRoute('wpm/default', array('controller' => 'workorder', 'action' => 'register'));
                    } catch(PDOException $e){
                        $connection->rollback();
                    }
                }
            } else {
                // get request
                if($editId != 0) {
                    // register details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WPM_WCERegister' ) )
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array('CostCentreId', 'CostCentreName'), $select::JOIN_LEFT)
                        ->columns( array("WCERegisterId", "WCENo", "WCEDate" => new Expression("FORMAT(a.WCEDate, 'dd-MM-yyyy')"), "RefDate" => new Expression("FORMAT(a.RefDate, 'dd-MM-yyyy')")
                        , "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")
                        , "RefNo", "CCWCENo", "CompWCENo", "WOType", "Amount", 'Narration') )
                        ->where( "a.DeleteFlag=0 AND a.WCERegisterId=$editId" );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->wceregister = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    //print_r($this->_view->wceregister);exit;
                    $WorkType = $this->_view->wceregister['WOType'];
                    $this->_view->WorkType = $WorkType;
                    $this->_view->WorkTypeName = 'IOW';

                    // cost center details
                    $select = $sql->select();
                    $select->from( array( 'a' => 'WF_OperationalCostCentre' ) )
                        ->columns( array( 'CostCentreId', 'CostCentreName', 'ProjectId', 'CompanyId' ) )
                        ->where( "Deactivate=0 AND CostCentreId=".$this->_view->wceregister['CostCentreId'] );
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $this->_view->costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->current();
                    //print_r($this->_view->costcenter);exit;
                    $projectId = $this->_view->costcenter['ProjectId'];

                    if($WorkType == 'iow') {
                        // get iow lists
                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WCETrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('Desc' => new Expression("b.SerialNo+ ' ' +b.Specification")), $select::JOIN_LEFT)
                            //->join(array('d' => 'Proj_ProjectIOW'), 'd.ProjectIOWId=b.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'b.UnitId=c.UnitId', array('UnitName', 'UnitId'), $select::JOIN_LEFT)
                            ->columns(array('DescId' => 'IOWId'))
                            ->where('a.WCERegisterId='.$editId)
                            ->group(new Expression('a.IowId,b.SerialNo,b.Specification,c.UnitName,c.UnitId'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $subQuery = $sql->select();
                        $subQuery->from("WPM_WCETrans")
                            ->columns(array('WCETransId'))
                            ->where('WCERegisterId ='.$editId);

                        $select = $sql->select();
                        $select->from(array("a" => "WPM_WCEIOWTrans"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.IOWId=b.ProjectIOWId', array('SerialNo','Specification'), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSId=a.WBSId', array('WBSId','WBSName','ParentText'), $select::JOIN_LEFT)
                            ->columns(array('WCEIOWTransId','WCETransId','ProjectIOWId' => 'IOWId', 'Qty'))
                            ->where->expression('a.WCETransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        //print_r($this->_view->arr_resource_iows);die;
                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectIOWMaster"))
                            ->join(array('c' => 'Proj_ProjectIOW'), 'a.ProjectIOWId=c.ProjectIOWId', array('Rate'), $select::JOIN_LEFT)
                            ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array('UnitName','UnitId'), $select::JOIN_LEFT)
                            ->columns(array('value' => new Expression("a.SerialNo+ ' ' +a.Specification"),'data' => 'IOWId'))
                            ->where("a.ProjectId=".$projectId)
                            ->where('a.UnitId <> 0')
                            ->order('a.IOWId');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }

                    $this->_view->WCERegisterId = $editId;
                }
            }

            $aVNo = CommonHelper::getVoucherNo(405, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if ($aVNo["genType"] == true)
                $this->_view->woNo = $aVNo["voucherNo"];
            else
                $this->_view->woNo = "";

            $this->_view->wcTypeId = '405';

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function registerAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Work Completion");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from(array('a' => 'WPM_WCERegister'))
            ->columns(array("WCERegisterId", "WCENo", "WCEDate" => new Expression("FORMAT(a.WCEDate, 'dd-MM-yyyy')"), "FDate" => new Expression("FORMAT(a.FDate, 'dd-MM-yyyy')"), "TDate" => new Expression("FORMAT(a.TDate, 'dd-MM-yyyy')")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId = b.CostCentreId', array('CostCentreName'), $select::JOIN_LEFT);
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->wceRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function deleteWceAction()
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
            if ($request->isPost()) {
                $status = "failed";

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $wceRegId = $this->params()->fromPost('wceRegId');
                    $response = $this->getResponse();

                    $subQuery = $sql->select();
                    $subQuery->from('WPM_WCETrans')
                        ->columns(array('WCETransId'))
                        ->where(array('WCERegisterId' => $wceRegId));

                    $delete = $sql->delete();
                    $delete->from('WPM_WCEIOWTrans')
                        ->where->expression('WCETransId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_WCETrans')
                        ->where("WCERegisterId = $wceRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('WPM_WCERegister')
                        ->where("WCERegisterId = $wceRegId");
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

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
}