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

use Application\View\Helper\Qualifier;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
use DOMPDF;
class ProjectController extends AbstractActionController
{
    public function __construct()	{
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

        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $select->order("ProjectId desc");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                $projectId = $postParams['projectId'];
                $this->redirect()->toRoute('crm/general', array('controller' => 'project', 'action' => 'general', 'projectId' => $this->bsf->encode($projectId)));
            }
        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function generalAction(){
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->columns(array('LandArea',"StartDate"=>new Expression("CONVERT(VARCHAR(10),StartDate,105)"),"EndDate"=>new Expression("CONVERT(VARCHAR(10),EndDate,105)")))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultDetail   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Phasecount
        $select = $sql->select();
        $select->from('KF_PhaseMaster')
            ->columns(array("count"=>new Expression("isnull(count(PhaseId),0)")))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultPhasecount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Blockcount
        $select = $sql->select();
        $select->from('KF_BlockMaster')
            ->columns(array("count"=>new Expression("count(BlockId)")))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultBlockcount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Floorcount
        $select = $sql->select();
        $select->from('KF_FloorMaster')
            ->columns(array("count"=>new Expression("count(*)")))
            ->where(array("ProjectId"=>$projectId))
            ->group(new expression('BlockId'))
            ->order('count desc');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultFloorcount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //UnitTypecount
        $select = $sql->select();
        $select->from('KF_UnitTypeMaster')
            ->columns(array("count"=>new Expression("count(*)")))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultUnittypecount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Unitcount
        $select = $sql->select();
        $select->from('KF_UnitMaster')
            ->columns(array("count"=>new Expression("count(UnitId)")))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultUnitcount = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            if ($request->isPost()) {
                $postParams = $request->getPost();
                if($postParams['startDate'] != '') {
                    $startDate = date('Y-m-d H:i:s',strtotime($postParams['startDate']));
                } else {
                    $startDate = '';
                }
                if($postParams['endDate'] != '') {
                    $endDate = date('Y-m-d H:i:s',strtotime($postParams['endDate']));
                } else {
                    $endDate = '';
                }
                if($this->_view->resultDetail != '') {
                    $update = $sql->update();
                    $update->table('Crm_ProjectDetail')
                        ->set(array(
                            'StartDate' => $startDate,
                            'EndDate' => $endDate,
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ))
                        ->where(array('ProjectId'=>$this->_view->projectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert('Crm_ProjectDetail')
                        ->values(array(
                            'StartDate' => $startDate,
                            'EndDate' => $endDate,
                            'ProjectId'=>$this->_view->projectId,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                if(isset($postParams['saveNext'])) {
                    $this->redirect()->toRoute('crm/land-area', array('controller' => 'project', 'action' => 'land-area', 'projectId' => $this->bsf->encode($projectId)));
                } else {
                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function landAreaAction(){
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from(array('a'=>'Proj_ProjectMaster'))
            ->columns(array('ProjectId','ProjectName',"Amount"=>new Expression("isnull(Sum(b.UnitArea),0)")))
            ->join(array("b"=>new Expression("KF_UnitMaster")), "a.ProjectId=b.ProjectId", array(), $select::JOIN_LEFT)
            ->where(array("a.ProjectId"=>$projectId,"b.DeleteFlag"=>0,"a.DeleteFlag"=>0));
        $select->group(new Expression("a.ProjectId,a.ProjectName"));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Proj_UOM')
            ->columns(array('UnitId','UnitName'))
            ->where(array('TypeId'=>2));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->area = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $select = $sql->select();
        $select->from(array('a' => 'KF_Conception'))
            ->columns(array("BusinessTypeId"))
            ->join(array("b"=>"Proj_ProjectMaster"), "a.KickOffId=b.KickOffId", array(), $select::JOIN_LEFT)
            ->where(array("b.ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultDetail   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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
                if($this->_view->resultDetail != '') {
                    $update = $sql->update();
                    $update->table('Crm_ProjectDetail')
                        ->set(array(
                            'AreaUnit' =>  $this->bsf->isNullCheck($postParams['areaunit'], 'number'),
                            'LandArea' =>  $this->bsf->isNullCheck($postParams['landArea'], 'number'),
                            'FSICalc' =>  $this->bsf->isNullCheck($postParams['FSICalc'], 'number'),
                            'FSI' => $this->bsf->isNullCheck($postParams['fsi'], 'number'),
                            'PremiumFSI' => $this->bsf->isNullCheck($postParams['premiumFsi'], 'number'),
                            'ExpandedFSIPercent' => $this->bsf->isNullCheck($postParams['expandedFsi'], 'number'),
                            'BuildupArea' => $this->bsf->isNullCheck($postParams['buildupArea'], 'number'),
                            'NetLandArea' =>  $this->bsf->isNullCheck($postParams['landArea']-$postParams['saleArea'], 'number'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ))
                        ->where(array('ProjectId'=>$this->_view->projectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert('Crm_ProjectDetail')
                        ->values(array(
                            'AreaUnit' =>  $this->bsf->isNullCheck($postParams['areaunit'], 'number'),
                            'LandArea' => $this->bsf->isNullCheck($postParams['landArea'], 'number'),
                            'FSICalc' =>  $this->bsf->isNullCheck($postParams['FSICalc'], 'number'),
                            'SaleArea' => $this->bsf->isNullCheck($postParams['saleArea'], 'number'),
                            'FSI' => $this->bsf->isNullCheck($postParams['fsi'], 'number'),
                            'PremiumFSI' => $this->bsf->isNullCheck($postParams['premiumFsi'], 'number'),
                            'ExpandedFSIPercent' => $this->bsf->isNullCheck($postParams['expandedFsi'], 'number'),
                            'BuildupArea' =>$this->bsf->isNullCheck($postParams['buildupArea'], 'number'),
                            'NetLandArea' => $this->bsf->isNullCheck($postParams['landArea']-$postParams['saleArea'], 'number'),
                            'ProjectId'=>$this->_view->projectId,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                if(isset($postParams['saveNext'])) {
                    $this->redirect()->toRoute('crm/land-cost', array('controller' => 'project', 'action' => 'land-cost', 'projectId' => $this->bsf->encode($projectId)));
                } else {
                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function landCostAction(){
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array('a' => 'KF_Conception'))
            ->columns(array("BusinessTypeId"))
            ->join(array("b"=>"Proj_ProjectMaster"), "a.KickOffId=b.KickOffId", array(), $select::JOIN_LEFT)
            ->where(array("b.ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();





        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultDetail   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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

                if($this->_view->resultDetail != '') {
                    $update = $sql->update();
                    $update->table('Crm_ProjectDetail')
                        ->set(array(
                            'LCAreaBasedon' => $this->bsf->isNullCheck($postParams['lcAreaBasedon'], 'string'),
                            'LCRateBasedon' => $this->bsf->isNullCheck($postParams['lcRateBasedon'], 'string'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ))
                        ->where(array('ProjectId'=>$this->_view->projectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert('Crm_ProjectDetail')
                        ->values(array(
                            'LCAreaBasedon' => $this->bsf->isNullCheck($postParams['lcAreaBasedon'], 'string'),
                            'LCRateBasedon' => $this->bsf->isNullCheck($postParams['lcRateBasedon'], 'string'),
                            'ProjectId'=>$this->_view->projectId,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                if(isset($postParams['saveNext'])) {
                    $this->redirect()->toRoute('crm/other-cost', array('controller' => 'project', 'action' => 'other-cost', 'projectId' => $this->bsf->encode($projectId)));
                } else {
                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                }
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function otherCostAction(){
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $select = $sql->select();
        $select->from(array('a' => 'KF_Conception'))
            ->columns(array("BusinessTypeId"))
            ->join(array("b"=>"Proj_ProjectMaster"), "a.KickOffId=b.KickOffId", array(), $select::JOIN_LEFT)
            ->where(array("b.ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        // other cost used in unit type
        $select = $sql->select();
        $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
            ->columns(array('OtherCostId'))
            ->join(array('b' => 'KF_UnitTypeMaster'), 'b.UnitTypeId=a.UnitTypeId', array(), $select::JOIN_LEFT)
            ->where(array('ProjectId' => $projectId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $arrUsedOtherCostIds = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//        // other cost used in unit type
//        $select = $sql->select();
//        $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
//            ->columns(array('OtherCostId'))
//            ->where(array('ProjectId' => $projectId));
//         $stmt = $sql->getSqlStringForSqlObject($select);
//        $arrUsedProjOtherCostIds = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//        $arrUsedOtherCostIds = array_merge($arrUsedOtherCostIds, $arrUsedProjOtherCostIds);

        $select = $sql->select();
        $select->from('Crm_OtherCostMaster')
            ->columns(array('OtherCostId','OtherCostName'))
            ->where(array("DeleteFlag"=>0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $arrOtherCosts = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        foreach($arrOtherCosts as &$nOtherCost) {
            foreach($arrUsedOtherCostIds as $usedOtherCost) {
                if($nOtherCost['OtherCostId'] == $usedOtherCost['OtherCostId']) {
                    $nOtherCost['isUsed'] = TRUE;
                }
            }
        }
        $this->_view->resultsOtherCost = $arrOtherCosts;

        $otherCostArray = array();
        foreach($this->_view->resultsOtherCost as $resOtherCost) {
            $otherCostArray[] = $resOtherCost['OtherCostId'];
        }

        $select = $sql->select();
        $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId = b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
            ->columns(array('OtherCostId','Amount'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $selectedOtherCost = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $arrSelOtherCostIds = array();
        foreach($selectedOtherCost as $otherCost) {
            $arrSelOtherCostIds[] = $otherCost['OtherCostId'];
        }

        $this->_view->arrSelOtherCost = $selectedOtherCost;
        $this->_view->arrSelOtherCostIds = $arrSelOtherCostIds;

        $select = $sql->select();
        $select->from('Crm_ConstructOthercost')
            ->columns(array('OtherCostId'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsother  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->othercost = array();
        foreach($this->_view->resultsother as $this->resultsMulti) {
            $this->othercost[] = $this->resultsMulti['OtherCostId'];
        }
        $this->_view->constructothercost = $this->othercost;

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

                $postParams = $request->getPost();
                // Print_r($postParams);die;
                if($this->_view->resultDetail != '') {
                    $update = $sql->update();
                    $update->table('Crm_ProjectDetail')
                        ->set(array(
                            'LRegistrationValue' => $postParams['registrationVal'],
                            'LAmountValue' => $this->bsf->isNullCheck($postParams['lAmtPer'],'number'),
                            'CRegistrationValue' => $postParams['cRegistrationVal'],
                            'CAmountValue' => $this->bsf->isNullCheck($postParams['cAmtPer'],'number'),
                            'Include' => $postParams['include'],
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ))
                        ->where(array('ProjectId'=>$this->_view->projectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert('Crm_ProjectDetail')
                        ->values(array(
                            'LRegistrationValue' => $postParams['registrationVal'],
                            'LAmountValue' => $this->bsf->isNullCheck($postParams['lAmtPer'],'number'),
                            'CRegistrationValue' => $postParams['cRegistrationVal'],
                            'CAmountValue' => $this->bsf->isNullCheck($postParams['cAmtPer'],'number'),
                            'Include' => $postParams['include'],
                            'ProjectId'=>$this->_view->projectId,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $delete = $sql->delete();
                $delete->from('Crm_ProjectOtherCostTrans')
                    ->where(array('ProjectId'=>$this->_view->projectId));
                $statement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                if(!empty($postParams['OtherCost'])) {
                    foreach($postParams['OtherCost'] as $otherCostId) {
                    //  $otherCostAmt = $this->bsf->isNullCheck($postParams['selectedOtherCostValue_' . $otherCostId], 'number');

                        if(!is_numeric($otherCostId) && trim($otherCostId) != '' ) {
                            $otherCostName = trim($otherCostId);
                            // add new other cost
                            $insert = $sql->insert('Crm_OtherCostMaster')
                                ->values(array(
                                    'OtherCostName' => $otherCostName,
                                    'CreatedDate' => date('Y-m-d H:i:s'),
                                ));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $otherCostId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        }


                        $insert = $sql->insert('Crm_ProjectOtherCostTrans')
                            ->values(array(
                                'ProjectId' => $projectId,
                                'OtherCostId' => $otherCostId,
                             //    'Amount' => $otherCostAmt
                            ));
                     $statement = $sql->getSqlStringForSqlObject($insert);
                     $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                $delete = $sql->delete();
                $delete->from('Crm_ConstructOtherCost')
                    ->where(array('ProjectId' => $projectId));
                $stmt = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                if(!empty($postParams['Other'])) {
                    foreach($postParams['Other'] as $other) {


                        if(!is_numeric($other) && trim($other) != '' ) {


                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_OtherCostMaster'))
                                ->columns(array("OtherCostId"))
                                ->where(array("OtherCostName"=>$otherCostName));
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $other = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        }


                        $insert = $sql->insert('Crm_ConstructOtherCost')
                            ->values(array(
                                'ProjectId' => $projectId,
                                'OtherCostId' => $other

                            ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                }

                if(isset($postParams['saveNext'])) {
                    $this->redirect()->toRoute('crm/payment', array('controller' => 'project', 'action' => 'payment', 'projectId' => $this->bsf->encode($projectId)));
                } else {
                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function checklistAction(){
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_CheckListTypeMaster')
            ->columns(array('TypeId','CheckListName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->checklistType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


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

                $checkListTypeId = $postParams['CheckListTypeId'];
                $arrCheckLists = array();
                foreach($postParams as $key => $data) {
                    if(preg_match('/^CheckList_[a-z_\d]+$/i', $key)) {

                        preg_match_all('/^CheckList_([a-z]+)_([\d])+$/i', $key, $arrMatches);
                        //print_r($arrMatches[1][0]);
                        $arrCheckLists[$arrMatches[2][0]][$arrMatches[1][0]] = $data;
                    }
                }

                $delete = $sql->delete();
                $delete->from('Crm_CheckListProjectTrans')
                    ->where(array('ProjectId' => $projectId, 'CheckListTypeId' => $checkListTypeId));
                $stmt = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach($arrCheckLists as $check){
                    $checkListName = $this->bsf->isNullCheck($check['Name'], 'string');
                    if($checkListName == '') {
                        // skip empty rows
                        continue;
                    }

                    $checkListId = $this->bsf->isNullCheck($check['Id'], 'number');
                    if($checkListId == 0){
                        $insert = $sql->insert();
                        $insert->into('Crm_CheckListMaster')
                            ->values(array('CheckListName' => $check['Name'],
                                'TypeId' => $checkListTypeId,
                                'CreatedDate' => date('Y-m-d H:i:s')));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        $checkListId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    }
                    //insert
                    $date = NULL;
                    if(strtotime($check['Date']) != FALSE) {
                        $date = date( 'Y-m-d H:i:s', strtotime( $check[ 'Date' ] ) );
                    }
                    $insert = $sql->insert('Crm_CheckListProjectTrans')
                        ->values(array(
                            'CheckListId' => $checkListId,
                            'CheckListTypeId' => $checkListTypeId,
                            'ProjectId' => $projectId,
                            'TimeLine' => $check['TimeNeed'],
                            'DateFrom' => $check['DateFrom'],
                            'DateAfterBefore' => $check['DateAfBef'],
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'DurationType' => $check['Duration'],
                            'DurationDays' => $check['Days'],
                            'Date' => $date,
                        ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                if(isset($postParams['saveNext'])) {
                    $this->redirect()->toRoute('crm/penality-interestrate', array('controller' => 'project', 'action' => 'penality-interestrate', 'projectId' => $this->bsf->encode($projectId)));
                } else {
                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function selectTypeChecklistAction(){
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

                $postParams = $request->getPost();
                $typeId = $postParams['typeId'];
                $projectId = $postParams['projectId'];

                //Write your Ajax post code here
                $select = $sql->select();
                $select->from(array('a' => 'Crm_CheckListProjectTrans'))
                    ->join(array('b' => 'Crm_CheckListMaster'), 'b.CheckListId=a.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
                    ->where(array('ProjectId' => $projectId, 'CheckListTypeId' => $typeId));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $arrProjectCheckList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from('Crm_CheckListMaster')
                    ->columns(array('data' => 'CheckListId', 'value' => 'CheckListName'))
                    ->Where(array("TypeId"=>$typeId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $arrCheckList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                // verify checklist usage
                $select = $sql->select();
                $select->from('Crm_FinalisationCheckListTrans');
                $stmt = $sql->getSqlStringForSqlObject($select);
                $arrUsedCheckList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(!empty($arrUsedCheckList)) {
                    foreach ( $arrProjectCheckList as &$checkList ) {
                        foreach ( $arrUsedCheckList as $usedChecklist ) {
                            if($checkList['CheckListId'] == $usedChecklist['CheckListId']) {
                                $checkList['isUsed'] = true;
                                break;
                            }
                        }
                    }
                }

                $this->_view->setTerminal(true);

                $result = array(
                    'arrProjectCheckList' => $arrProjectCheckList,
                    'arrCheckList' => $arrCheckList
                );

                $response = $this->getResponse()
                    ->setContent(json_encode($result));
                $response->getHeaders()->addHeaderLine('Content-Type','application/json');
                return $response;
            }
        }

        return $this->_view;

    }

    public function penalityInterestrateAction(){
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultDetail   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Crm_UnitType')
            ->columns(array('Rate'))
            ->order("Rate ASC")
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultRate   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Crm_ProjCancellationPenality')
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->cancellationDetail   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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
                if($this->_view->resultDetail != '') {
                    $update = $sql->update();
                    $update->table('Crm_ProjectDetail')
                        ->set(array(
                            'IntCalculationOn' => $this->bsf->isNullCheck($postParams['intCalcOn'], 'string'),
                            'IntCalculationFrom' => $this->bsf->isNullCheck( $postParams['intCalcFrom'], 'string'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ))
                        ->where(array('ProjectId'=>$this->_view->projectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert('Crm_ProjectDetail')
                        ->values(array(
                            'IntCalculationOn' =>  $this->bsf->isNullCheck($postParams['intCalcOn'], 'string'),
                            'IntCalculationFrom' =>  $this->bsf->isNullCheck($postParams['intCalcFrom'], 'string'),
                            'ProjectId'=>$this->_view->projectId,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                if($this->_view->cancellationDetail != '') {
                    $update = $sql->update();
                    $update->table('Crm_ProjCancellationPenality')
                        ->set(array(
                            'BookingCancelType' =>  $this->bsf->isNullCheck($postParams['bookingCancelType'], 'string'),
                            'BookingCancelValue' => $this->bsf->isNullCheck($postParams['bookingCancelValue'], 'number'),
                            'BlockCancelType' =>  $this->bsf->isNullCheck($postParams['blockCancelType'], 'string'),
                            'BlockCancelValue' =>  $this->bsf->isNullCheck($postParams['blockCancelValue'], 'number'),
                            'UnitCancelType' =>  $this->bsf->isNullCheck($postParams['unitCancelType'], 'string'),
                            'UnitCancelValue' =>  $this->bsf->isNullCheck($postParams['unitCancelValue'], 'number'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ))
                        ->where(array('ProjectId'=>$this->_view->projectId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                } else {
                    $insert = $sql->insert('Crm_ProjCancellationPenality')
                        ->values(array(
                            'BookingCancelType' =>  $this->bsf->isNullCheck($postParams['bookingCancelType'], 'string'),
                            'BookingCancelValue' => $this->bsf->isNullCheck($postParams['bookingCancelValue'], 'number'),
                            'BlockCancelType' => $this->bsf->isNullCheck($postParams['blockCancelType'], 'string'),
                            'BlockCancelValue' => $this->bsf->isNullCheck( $postParams['blockCancelValue'], 'number'),
                            'UnitCancelType' =>  $this->bsf->isNullCheck($postParams['unitCancelType'], 'string'),
                            'UnitCancelValue' =>  $this->bsf->isNullCheck($postParams['unitCancelValue'], 'number'),
                            'ProjectId'=>$this->_view->projectId,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'ModifiedDate' => date('Y-m-d H:i:s')
                        ));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                if(isset($postParams['saveNext'])) {
                    $this->redirect()->toRoute('crm/incentive-register', array('controller' => 'project', 'action' => 'incentive-register', 'projectId' => $this->bsf->encode($projectId)));
                } else {
                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function paymentAction()
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultDetail   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function paymentScheduleAction()
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->columns(array('Include'))
            ->where(array("projectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $include = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(count($include['Include'])> 0 ){

            $select = $sql->select();
            $select->from('Crm_ProjectOtherCostTrans')
                ->columns(array('OtherCostId'))
                ->where('OtherCostId IN (1,2)')
                ->where(array("projectId"=>$projectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $projectother = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $otherCostId=array();
            if(count($projectother) == 0 ) {

                $select = $sql->select();
                $select->from('Crm_ProjectDetail')
                    ->columns(array('LRegistrationValue', 'CRegistrationValue'))
                    ->where(array("projectId" => $projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $otherCostId=array();
                if (count($projectDetail['LRegistrationValue']) > 0 && count($projectDetail['CRegistrationValue'])==0 ) {
                    $otherCostId=array(1);
                }
                elseif (count($projectDetail['LRegistrationValue']) == 0 && count($projectDetail['CRegistrationValue'])> 0 ) {
                    $otherCostId=array(2);


                }
                elseif(count($projectDetail['LRegistrationValue']) > 0 && count($projectDetail['CRegistrationValue'])> 0 ){
                    $otherCostId=array(1,2);

                }

                $select = $sql->select();
                $select->from('Crm_OtherCostMaster')
                    ->columns(array('OtherCostName', 'OtherCostId'))
                    ->where(array('OtherCostId '=> $otherCostId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->OtherCostnew = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }}

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Stage Master
        $select = $sql->select();
        $select->from('KF_StageMaster')
            ->columns(array('StageId','StageName'))
            ->where("ProjectId = '".$this->_view->projectId."'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->stageMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Receipt Type Master
        $select = $sql->select();
        $select->from('Crm_ReceiptTypeMaster')
            ->columns(array('ReceiptTypeId','ReceiptTypeName'))
            ->where("ReceiptType = 'S'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->receiptTypeMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Other Cost Master
        $select = $sql->select();
        $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId = b.OtherCostId ', array('OtherCostName'), $select::JOIN_LEFT)
            ->columns(array('OtherCostId'))
            ->where(array("a.ProjectId" => $this->_view->projectId, 'b.DeleteFlag' => 0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->otherCostMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Booking Advance Master
        $select = $sql->select();
        $select->from('Crm_BookingAdvanceMaster')
            ->columns(array('BookingAdvanceId','BookingAdvanceName'))
            ->where("DeleteFlag = '0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->bookingAdvanceMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Fetching data from Schedule Type Master
        $select = $sql->select();
        $select->from('Crm_ScheduleTypeMaster')
            ->columns(array('ScheduleTypeId','ScheduleTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->scheduleType  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Description Master, Stage Master, Other Cost Master
        $select = $sql->select();
        $select->from( array('a' => 'Crm_DescriptionMaster' ))
            ->columns(array( 'Id' => new Expression("a.DescriptionId"), 'Name'  => new Expression("a.DescriptionName"), 'Type' => new Expression("'D'") ))
            ->where("a.ProjectId = '".$this->_view->projectId."'");

        $select21 = $sql->select();
        $select21->from(array("a"=>"KF_StageMaster"))
            ->columns(array( 'Id' => new Expression("a.StageId"), 'Name'  => new Expression("a.StageName"), 'Type' => new Expression("'S'") ))
            ->where("a.ProjectId = '".$this->_view->projectId."'");
        $select21->combine($select,'Union ALL');

        $select22 = $sql->select();
        $select22->from(array("a"=>"Crm_OtherCostMaster"))
            ->columns(array( 'Id' => new Expression("a.OtherCostId"), 'Name'  => new Expression("a.OtherCostName"), 'Type' => new Expression("'O'") ));
        $select22->combine($select21,'Union ALL');

        $select3 = $sql->select();
        $select3->from(array("g"=>$select22))
            ->columns(array("data" => "Id", "Type", "value" => "Name"));
        $select3->order('g.Name');

        $statement = $sql->getSqlStringForSqlObject($select3);
        $this->_view->psResult  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();
                //echo '<pre>'; print_r($postData); die;

                $startDate = NULL;
                $endDate = NULL;

                $insert = $sql->insert();
                $insert->into('Crm_PaymentSchedule');
                $insert->Values(array('ProjectId' => $this->bsf->isNullCheck($postData['projectId'],'number')
                , 'PaymentSchedule' => $this->bsf->isNullCheck($postData['paymentSchedule'],'string')
                , 'ScheduleTypeId' => $this->bsf->isNullCheck($postData['scheduleType'],'number')
                , 'IncludeAdvance' => $this->bsf->isNullCheck($postData['advanceIncluded'],'number')
                , 'EmiType' => $this->bsf->isNullCheck('0','number')
                , 'Terms' => $this->bsf->isNullCheck('0','number')
                , 'StartDate' => $startDate
                , 'EndDate' => $endDate
                , 'CreatedDate' => date('Y-m-d')
                , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                $statement = $sql->getSqlStringForSqlObject($insert);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $paymentScheduleId = $dbAdapter->getDriver()->getLastGeneratedValue();

                $roTypesCount = count($postData['roTypes']);
                for($i=1;$i<=$roTypesCount;$i++) {
                    $insert = $sql->insert();
                    $insert->into('Crm_PaymentScheduleReceiptTrans');
                    $insert->Values(array('PaymentScheduleId' => $this->bsf->isNullCheck($paymentScheduleId,'number')
                    , 'ReceiptTypeId' => $this->bsf->isNullCheck($postData['rtTypeId_'.$i],'number')
                    , 'ReceiptType' => $this->bsf->isNullCheck($postData['rtTypeType_'.$i],'string')
                    , 'SortId' => $this->bsf->isNullCheck($postData['rtSortId_'.$i],'number')));
                   $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }



                $stageType = 'S';
                if($postData['scheduleType']==1) {
                    $scheduleCount = $postData['psCount'];
                    for($i=1;$i<=$scheduleCount;$i++) {
                        if($this->bsf->isNullCheck($postData['scheduleId_'.$i],'number') != "") {
                            $dateFrom = '';
                            $dateAfBef = 0;
                            $duration = 0;
                            $days = 0;
                            $schDate = NULL;
                            $roundOff = 0;
                            $percentage = 0;
                            $isAdvance = 0;
                            $isValue = 0;
                            $amount = 0;
                            $IsAdvanceDeductible = 0;
                            $psSortId = $postData['psSortId_'.$i];

                            if($postData['advanceIncluded']==1) {
                                if($i == 1) {
                                    if($postData['chosenType_'.$i] != '') {
                                        $stageType = $postData['chosenType_'.$i];
                                    }
                                    $isAdvance = 1;
                                    $isValue = $postData['advAmount_'.$i];
                                    if($postData['advAmount_'.$i] == '1') {
                                        $percentage = $postData['percentage_'.$i];
                                    } else if($postData['advAmount_'.$i] == '2') {
                                        $amount = $postData['amount_'.$i];
                                    }
                                } else {
                                    if($postData['chosenType_'.$i] != '') {
                                        $stageType = $postData['chosenType_'.$i];
                                    }
                                    $dateFrom = $postData['dateFrom_'.$i];
                                    if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                        $dateAfBef = $postData['dateAfBef_'.$i];
                                    }
                                    $duration = $postData['duration_'.$i];
                                    $days = $postData['days_'.$i];
                                    if($postData['date_'.$i]) {
                                        $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                    }
                                    $roundOff = $postData['roundOff_'.$i];
                                    $percentage = $postData['percentage_'.$i];
                                }
                            } else {
                                if($postData['chosenType_'.$i] != '') {
                                    $stageType = $postData['chosenType_'.$i];
                                }
                                $dateFrom = $postData['dateFrom_'.$i];
                                if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                    $dateAfBef = $postData['dateAfBef_'.$i];
                                }
                                $duration = $postData['duration_'.$i];
                                $days = $postData['days_'.$i];
                                if($postData['date_'.$i]) {
                                    $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                }
                                $roundOff = $postData['roundOff_'.$i];
                                $percentage = $postData['percentage_'.$i];
                                if($postData['isAdvanceDeductible_'.$i]) {
                                    $IsAdvanceDeductible = $postData['isAdvanceDeductible_'.$i];
                                }
                            }

                            $insert = $sql->insert();
                            $insert->into('Crm_PaymentScheduleDetail');
                            $insert->Values(array('PaymentScheduleId' => $this->bsf->isNullCheck($paymentScheduleId,'number')
                            , 'StageId' => $this->bsf->isNullCheck($postData['scheduleId_'.$i],'number')
                            , 'StageType' => $this->bsf->isNullCheck($stageType,'string')
                            , 'DateFrom' => $this->bsf->isNullCheck($dateFrom,'string')
                            , 'DateAfterBefore' => $this->bsf->isNullCheck($dateAfBef,'number')
                            , 'DurationType' => $this->bsf->isNullCheck($duration,'number')
                            , 'DurationDays' => $this->bsf->isNullCheck($days,'number')
                            , 'Date' => $schDate
                            , 'RoundOff' => $this->bsf->isNullCheck($roundOff,'number')
                            , 'Percentage' => $this->bsf->isNullCheck($percentage,'number')
                            , 'IsAdvance' => $this->bsf->isNullCheck($isAdvance,'number')
                            , 'IsValue' => $this->bsf->isNullCheck($isValue,'number')
                            , 'Amount' => $this->bsf->isNullCheck($amount,'number')
                            , 'IsAdvanceDeductible' => $this->bsf->isNullCheck($IsAdvanceDeductible,'number')
                            , 'SortId' => $this->bsf->isNullCheck($psSortId,'number')
                            , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $paymentScheduleDetailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            for($j=1;$j<=$roTypesCount;$j++) {
                                $rtPer = explode('#',$postData['receiptType_'.$i.'_'.$j]);
                                $insert = $sql->insert();
                                $insert->into('Crm_PaySchReceiptTypePercent');
                                $insert->Values(array('PaymentScheduleDetailId' => $this->bsf->isNullCheck($paymentScheduleDetailId,'number')
                                , 'ReceiptTypeId' => $this->bsf->isNullCheck($rtPer[0],'number')
                                , 'ReceiptType' => $this->bsf->isNullCheck($rtPer[1],'string')
                                , 'Percentage' => $this->bsf->isNullCheck('0','number')
                                , 'SortId' => $this->bsf->isNullCheck($postData['esSortId_'.$i.'_'.$j],'number')));
                                 $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                        } else {
                            if($this->bsf->isNullCheck($postData['description_'.$i],'string') != "") {
                                $stageType = $postData['chooseInsType_'.$i];
                                if($postData['chooseInsType_'.$i]=='D') {
                                    $insert = $sql->insert();
                                    $insert->into('Crm_DescriptionMaster');
                                    $insert->Values(array('ProjectId' => $this->bsf->isNullCheck($postData['projectId'],'number')
                                    , 'DescriptionName' => $this->bsf->isNullCheck($postData['description_'.$i],'string')
                                    , 'DescriptionType' => $this->bsf->isNullCheck('','string')
                                    , 'CreatedDate' => date('Y-m-d')
                                    , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $descriptionId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                } else if($postData['chooseInsType_'.$i]=='S') {
                                    $insert = $sql->insert();
                                    $insert->into('KF_StageMaster');
                                    $insert->Values(array('StageName' => $this->bsf->isNullCheck($postData['description_'.$i],'string')
                                    , 'CreatedDate' => date('Y-m-d H:i:s')
                                    , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')
                                    , 'ProjectId' => $this->bsf->isNullCheck($postData['projectId'],'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $descriptionId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                }

                                $dateAfBef = 0;
                                $schDate = NULL;
                                $isAdvance = 0;
                                $isValue = 0;
                                $amount = 0;
                                $IsAdvanceDeductible = 0;
                                $psSortId = $postData['psSortId_'.$i];

                                $dateFrom = $postData['dateFrom_'.$i];
                                if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                    $dateAfBef = $postData['dateAfBef_'.$i];
                                }
                                $duration = $postData['duration_'.$i];
                                $days = $postData['days_'.$i];
                                if($postData['date_'.$i]) {
                                    $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                }
                                $roundOff = $postData['roundOff_'.$i];
                                $percentage = $postData['percentage_'.$i];
                                if($postData['isAdvanceDeductible_'.$i]) {
                                    $IsAdvanceDeductible = $postData['isAdvanceDeductible_'.$i];
                                }

                                $insert = $sql->insert();
                                $insert->into('Crm_PaymentScheduleDetail');
                                $insert->Values(array('PaymentScheduleId' => $this->bsf->isNullCheck($paymentScheduleId,'number')
                                , 'StageId' => $this->bsf->isNullCheck($descriptionId,'number')
                                , 'StageType' => $this->bsf->isNullCheck($stageType,'string')
                                , 'DateFrom' => $this->bsf->isNullCheck($dateFrom,'string')
                                , 'DateAfterBefore' => $this->bsf->isNullCheck($dateAfBef,'number')
                                , 'DurationType' => $this->bsf->isNullCheck($duration,'number')
                                , 'DurationDays' => $this->bsf->isNullCheck($days,'number')
                                , 'Date' => $schDate
                                , 'RoundOff' => $this->bsf->isNullCheck($roundOff,'number')
                                , 'Percentage' => $this->bsf->isNullCheck($percentage,'number')
                                , 'IsAdvance' => $this->bsf->isNullCheck($isAdvance,'number')
                                , 'IsValue' => $this->bsf->isNullCheck($isValue,'number')
                                , 'Amount' => $this->bsf->isNullCheck($amount,'number')
                                , 'IsAdvanceDeductible' => $this->bsf->isNullCheck($IsAdvanceDeductible,'number')
                                , 'SortId' => $this->bsf->isNullCheck($psSortId,'number')
                                , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $paymentScheduleDetailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                for($j=1;$j<=$roTypesCount;$j++) {
                                    $rtPer = explode('#',$postData['receiptType_'.$i.'_'.$j]);
                                    $insert = $sql->insert();
                                    $insert->into('Crm_PaySchReceiptTypePercent');
                                    $insert->Values(array('PaymentScheduleDetailId' => $this->bsf->isNullCheck($paymentScheduleDetailId,'number')
                                    , 'ReceiptTypeId' => $this->bsf->isNullCheck($rtPer[0],'number')
                                    , 'ReceiptType' => $this->bsf->isNullCheck($rtPer[1],'string')
                                    , 'Percentage' => $this->bsf->isNullCheck('0','number')
                                    , 'SortId' => $this->bsf->isNullCheck($postData['esSortId_'.$i.'_'.$j],'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }

                }

                $connection->commit();
                $this->redirect()->toRoute('crm/payment-schedule-register', array('controller' => 'project', 'action' => 'payment-schedule-register', 'projectId' => $this->bsf->encode($postData['projectId'])));
            } catch(PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
        return $this->_view;
    }

    public function paymentScheduleRegisterAction()
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();
            if ($request->isPost()) {
                //Fetching data from Payment Schedule
                $select = $sql->select();
                $select->from(array('a' => 'Crm_PaymentSchedule'))
                    ->join(array('b' => 'Crm_ScheduleTypeMaster'), 'a.ScheduleTypeId = b.ScheduleTypeId', array('ScheduleTypeName'), $select::JOIN_LEFT)
                    ->join(array('c' => 'Proj_ProjectMaster'), 'c.ProjectId = a.ProjectId', array('ProjectName'), $select::JOIN_LEFT)
                    ->where(array("a.ProjectId" => $projectId, 'a.DeleteFlag' => 0))
                    ->order("a.PaymentScheduleId DESC");
                $statement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $response->setContent(json_encode($results));
                return $response;
            }
        } else {
            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId','ProjectName'))
                ->where(array("ProjectId"=>$projectId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
                $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
            }

            $select = $sql->select();
            $select->from('Proj_ProjectMaster')
                ->columns(array('ProjectId','ProjectName'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function paymentScheduleEditAction()
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

        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->paymentScheduleId = $this->bsf->encode($this->params()->fromRoute('paySchId'));

        $select = $sql->select();
        $select->from('Crm_PaymentSchedule')
            ->where("PaymentScheduleId = ".$this->bsf->decode($this->_view->paymentScheduleId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $paySchedule = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->paymentSchedule = $paySchedule['PaymentSchedule'];
        $this->_view->scheduleTypeId = $paySchedule['ScheduleTypeId'];
        $this->_view->projectId = $paySchedule['ProjectId'];
        $this->_view->includeAdvance = $paySchedule['IncludeAdvance'];

        $this->_view->paySchRes = array();
        if($paySchedule['ScheduleTypeId'] == '1') {
            $select = $sql->select();
            $select->from(array('a'=>'Crm_PaymentScheduleDetail'))
                ->columns(array('*'))
                ->where(array("PaymentScheduleId" => $this->bsf->decode($this->_view->paymentScheduleId)))
                ->order("a.SortId ASC");
            $statement = $sql->getSqlStringForSqlObject($select);
            $paymentId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Fetching data from Payment Schedule
            $select1 = $sql->select();
            $select1->from(array('a' => 'Crm_PaymentScheduleDetail'))
                ->columns(array('PaymentScheduleId','PaymentScheduleDetailId','StageId','StageType','DateFrom','DateAfterBefore','DurationType','DurationDays','Date','RoundOff','Percentage','IsAdvance','IsValue','Amount','IsAdvanceDeductible','SortId'))
                ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                ->where("a.StageType = 'S'");

            $select2 = $sql->select();
            $select2->from(array('a' => 'Crm_PaymentScheduleDetail'))
                ->columns(array('PaymentScheduleId','PaymentScheduleDetailId','StageId','StageType','DateFrom','DateAfterBefore','DurationType','DurationDays','Date','RoundOff','Percentage','IsAdvance','IsValue','Amount','IsAdvanceDeductible','SortId'))
                ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                ->where("a.StageType = 'D'");
            $select2->combine($select1,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array('a' => 'Crm_PaymentScheduleDetail'))
                ->columns(array('PaymentScheduleId','PaymentScheduleDetailId','StageId','StageType','DateFrom','DateAfterBefore','DurationType','DurationDays','Date','RoundOff','Percentage','IsAdvance','IsValue','Amount','IsAdvanceDeductible','SortId'))
                ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                ->where("a.StageType = 'O'");
            $select3->combine($select2,'Union ALL');

            $select4 = $sql->select();
            $select4->from(array('a' => 'Crm_PaymentScheduleDetail'))
                ->columns(array('PaymentScheduleId','PaymentScheduleDetailId','StageId','StageType','DateFrom','DateAfterBefore','DurationType','DurationDays','Date','RoundOff','Percentage','IsAdvance','IsValue','Amount','IsAdvanceDeductible','SortId'))
                ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                ->where("a.StageType = 'A'");
            $select4->combine($select3,'Union ALL');

            $select5 = $sql->select();
            $select5->from(array("g"=>$select4))
                ->columns(array('*'))
                ->join(array('d' => 'Crm_PaymentSchedule'), 'g.PaymentScheduleId=d.PaymentScheduleId', array('PaymentSchedule','ScheduleTypeId','ProjectId', 'IncludeAdvance'), $select5::JOIN_LEFT)
                ->where(array("d.PaymentScheduleId" => $this->bsf->decode($this->_view->paymentScheduleId), 'd.DeleteFlag' => 0))
                ->order("g.SortId ASC");
            $statement = $sql->getSqlStringForSqlObject($select5);
            $this->_view->paySchRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $stageArray = array();
            $detailIds = '';
            foreach($this->_view->paySchRes as $paySch) {
                $detailIds .= "'".$paySch['PaymentScheduleDetailId']."',";
                if($paySch['StageType']=='S') {
                    $stageArray[] = $paySch['StageId'];
                }
            }
            $this->_view->selectedStages = $stageArray;
            $detailIds = substr($detailIds,0,-1);

            //Fetching data from Crm_PaySchReceiptTypePercent
            $select = $sql->select();
            $select->from('Crm_PaySchReceiptTypePercent')
                ->columns(array('PaymentScheduleDetailId','ReceiptTypeId'))
                ->where("PaymentScheduleDetailId IN (".$detailIds.")");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->paySchRecTypeRes = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $receiptTypes = array();
            foreach($this->_view->paySchRecTypeRes as $paySchRecType) {
                $receiptTypes[$paySchRecType['PaymentScheduleDetailId']][] = $paySchRecType['ReceiptTypeId'];
            }
            $this->_view->scheduleReceiptTypes = $receiptTypes;
        }

        $select1 = $sql->select();
        $select1->from(array('a' => 'Crm_PaymentScheduleReceiptTrans'))
            ->columns(array('PaymentScheduleId','ReceiptTypeId','ReceiptType','SortId'))
            ->join(array('b' => 'Crm_ReceiptTypeMaster'), 'a.ReceiptTypeId = b.ReceiptTypeId', array('ReceiptTypeName'), $select1::JOIN_LEFT)
            ->where("a.ReceiptType = 'S'");

        $select2 = $sql->select();
        $select2->from(array('a' => 'Crm_PaymentScheduleReceiptTrans'))
            ->columns(array('PaymentScheduleId','ReceiptTypeId','ReceiptType','SortId'))
            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.ReceiptTypeId = b.OtherCostId', array('ReceiptTypeName' => new Expression("b.OtherCostName")), $select2::JOIN_LEFT)
            ->where("a.ReceiptType = 'O'");
        $select2->combine($select1,'Union ALL');

        $select4 = $sql->select();
        $select4->from(array("g"=>$select2))
            ->where(array("g.PaymentScheduleId"=>$this->bsf->decode($this->_view->paymentScheduleId)))
            ->order("g.SortId ASC");
        $statement = $sql->getSqlStringForSqlObject($select4);
        $this->_view->psReceiptTrans  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $rtArray = array();
        $ocArray = array();
        foreach($this->_view->psReceiptTrans as $receiptTrans) {
            if($receiptTrans['ReceiptType']=='S') {
                $rtArray[] = $receiptTrans['ReceiptTypeId'];
            }
            if($receiptTrans['ReceiptType']=='O') {
                $ocArray[] = $receiptTrans['ReceiptTypeId'];
            }
        }
        $this->_view->selectedRT = $rtArray;
        $this->_view->selectedOC = $ocArray;

        //Fetching data from Stage Master
        $select = $sql->select();
        $select->from('KF_StageMaster')
            ->columns(array('StageId','StageName'))
            ->where("ProjectId = '".$this->_view->projectId."'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->stageMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Receipt Type Master
        $select = $sql->select();
        $select->from('Crm_ReceiptTypeMaster')
            ->columns(array('ReceiptTypeId','ReceiptTypeName'))
            ->where("ReceiptType = 'S'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->receiptTypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Other Cost Master
        $select = $sql->select();
        $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId = b.OtherCostId ', array('OtherCostName'), $select::JOIN_LEFT)
            ->columns(array('OtherCostId'))
            ->where(array("a.ProjectId" => $this->_view->projectId, 'b.DeleteFlag' => 0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->otherCostMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Booking Advance Master
        $select = $sql->select();
        $select->from('Crm_BookingAdvanceMaster')
            ->columns(array('BookingAdvanceId','BookingAdvanceName'))
            ->where("DeleteFlag = '0'");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->bookingAdvanceMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Fetching data from Schedule Type Master
        $select = $sql->select();
        $select->from('Crm_ScheduleTypeMaster')
            ->columns(array('ScheduleTypeId','ScheduleTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->scheduleType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Fetching data from Description Master, Stage Master, Other Cost Master
        $select11 = $sql->select();
        $select11->from( array('a' => 'Crm_DescriptionMaster' ))
            ->columns(array( 'Id' => new Expression("a.DescriptionId"), 'Name'  => new Expression("a.DescriptionName"), 'Type' => new Expression("'D'") ))
            ->where("a.ProjectId = '".$this->_view->projectId."'");

        $select22 = $sql->select();
        $select22->from(array("a"=>"KF_StageMaster"))
            ->columns(array( 'Id' => new Expression("a.StageId"), 'Name'  => new Expression("a.StageName"), 'Type' => new Expression("'S'") ))
            ->where("a.ProjectId = '".$this->_view->projectId."'");
        $select22->combine($select11,'Union ALL');

        $select33 = $sql->select();
        $select33->from(array("a"=>"Crm_OtherCostMaster"))
            ->columns(array( 'Id' => new Expression("a.OtherCostId"), 'Name'  => new Expression("a.OtherCostName"), 'Type' => new Expression("'O'") ));
        $select33->combine($select22,'Union ALL');

        $select44 = $sql->select();
        $select44->from(array("g"=>$select33))
            ->columns(array("data" => "Id", "Type", "value" => "Name"));
        $select44->order('g.Name');

        $statement = $sql->getSqlStringForSqlObject($select44);
        $this->_view->psResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $request = $this->getRequest();
        if ($request->isPost()) {
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                $postData = $request->getPost();
                // echo '<pre>'; print_r($postData); die;

                $paySchId = $this->bsf->decode($postData['paymentScheduleId']);

                $update = $sql->update();
                $update->table('Crm_PaymentSchedule');
                $update->set(array('PaymentSchedule' => $this->bsf->isNullCheck($postData['paymentSchedule'],'string')
                , 'IncludeAdvance' => $this->bsf->isNullCheck($postData['advanceIncluded'],'number')
                ))
                    ->where(array('PaymentScheduleId'=>$paySchId));
                $updateStmt = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                $delete = $sql->delete();
                $delete->from('Crm_PaymentScheduleReceiptTrans')
                    ->where(array('PaymentScheduleId'=>$paySchId));
                $statement = $sql->getSqlStringForSqlObject($delete);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $roTypesCount = count($postData['roTypes']);
                for($i=1;$i<=$roTypesCount;$i++) {
                    $insert = $sql->insert();
                    $insert->into('Crm_PaymentScheduleReceiptTrans');
                    $insert->Values(array('PaymentScheduleId' => $this->bsf->isNullCheck($paySchId,'number')
                    , 'ReceiptTypeId' => $this->bsf->isNullCheck($postData['rtTypeId_'.$i],'number')
                    , 'ReceiptType' => $this->bsf->isNullCheck($postData['rtTypeType_'.$i],'string')
                    , 'SortId' => $this->bsf->isNullCheck($postData['rtSortId_'.$i],'number')));
                  echo  $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                die;

                if($postData['advanceIncluded']==0) {
                    $select = $sql->select();
                    $select->from('Crm_PaymentScheduleDetail')
                        ->columns(array('PaymentScheduleDetailId'))
                        ->where(array('PaymentScheduleId' => $paySchId, 'StageType' => 'A'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->paySchDetailId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $delete = $sql->delete();
                    $delete->from('Crm_PaymentScheduleDetail')
                        ->where(array('PaymentScheduleId' => $paySchId, 'StageType' => 'A'));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Crm_PaySchReceiptTypePercent')
                        ->where(array('PaymentScheduleDetailId'=>$this->_view->paySchDetailId['PaymentScheduleDetailId']));
                    $statement = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $stageType = 'S';
                if($postData['typeSch']==1) {
                    $scheduleCount = $postData['psCount'];
                    for($i=1;$i<=$scheduleCount;$i++) {
                        if($this->bsf->isNullCheck($postData['paySchDetId_'.$i],'number') != "") {
                            $dateFrom = '';
                            $dateAfBef = 0;
                            $duration = 0;
                            $days = 0;
                            $schDate = NULL;
                            $roundOff = 0;
                            $percentage = 0;
                            $isAdvance = 0;
                            $isValue = 0;
                            $amount = 0;
                            $IsAdvanceDeductible = 0;
                            $psSortId = $postData['psSortId_'.$i];

                            if($postData['advanceIncluded']==1) {
                                if($i == 1) {
                                    if($postData['chosenType_'.$i] != '') {
                                        $stageType = $postData['chosenType_'.$i];
                                    }
                                    $isAdvance = 1;
                                    $isValue = $postData['advAmount_'.$i];
                                    if($postData['advAmount_'.$i] == '1') {
                                        $percentage = $postData['percentage_'.$i];
                                    } else if($postData['advAmount_'.$i] == '2') {
                                        $amount = $postData['amount_'.$i];
                                    }
                                } else {
                                    if($postData['chosenType_'.$i] != '') {
                                        $stageType = $postData['chosenType_'.$i];
                                    }
                                    $dateFrom = $postData['dateFrom_'.$i];
                                    if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                        $dateAfBef = $postData['dateAfBef_'.$i];
                                    }
                                    $duration = $postData['duration_'.$i];
                                    $days = $postData['days_'.$i];
                                    if($postData['date_'.$i]) {
                                        $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                    }
                                    $roundOff = $postData['roundOff_'.$i];
                                    $percentage = $postData['percentage_'.$i];
                                }
                            } else {
                                if($postData['chosenType_'.$i] != '') {
                                    $stageType = $postData['chosenType_'.$i];
                                }
                                $dateFrom = $postData['dateFrom_'.$i];
                                if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                    $dateAfBef = $postData['dateAfBef_'.$i];
                                }
                                $duration = $postData['duration_'.$i];
                                $days = $postData['days_'.$i];
                                if($postData['date_'.$i]) {
                                    $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                }
                                $roundOff = $postData['roundOff_'.$i];
                                $percentage = $postData['percentage_'.$i];
                                if($postData['isAdvanceDeductible_'.$i]) {
                                    $IsAdvanceDeductible = $postData['isAdvanceDeductible_'.$i];
                                }
                            }

                            $update = $sql->update();
                            $update->table('Crm_PaymentScheduleDetail');
                            $update->set(array('DateFrom' => $this->bsf->isNullCheck($dateFrom,'string')
                            , 'DateAfterBefore' => $this->bsf->isNullCheck($dateAfBef,'number')
                            , 'DurationType' => $this->bsf->isNullCheck($duration,'number')
                            , 'DurationDays' => $this->bsf->isNullCheck($days,'number')
                            , 'Date' => $schDate
                            , 'RoundOff' => $this->bsf->isNullCheck($roundOff,'number')
                            , 'Percentage' => $this->bsf->isNullCheck($percentage,'number')
                            , 'IsAdvance' => $this->bsf->isNullCheck($isAdvance,'number')
                            , 'IsValue' => $this->bsf->isNullCheck($isValue,'number')
                            , 'Amount' => $this->bsf->isNullCheck($amount,'number')
                            , 'IsAdvanceDeductible' => $this->bsf->isNullCheck($IsAdvanceDeductible,'number')
                            , 'SortId' => $this->bsf->isNullCheck($psSortId,'number')
                            ))
                                ->where(array('PaymentScheduleDetailId'=>$postData['paySchDetId_'.$i]));
                            $updateStmt = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                            $delete = $sql->delete();
                            $delete->from('Crm_PaySchReceiptTypePercent')
                                ->where(array('PaymentScheduleDetailId'=>$postData['paySchDetId_'.$i]));
                            $statement = $sql->getSqlStringForSqlObject($delete);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            for($j=1;$j<=$roTypesCount;$j++) {
                                $rtPer = explode('#',$postData['receiptType_'.$i.'_'.$j]);
                                $insert = $sql->insert();
                                $insert->into('Crm_PaySchReceiptTypePercent');
                                $insert->Values(array('PaymentScheduleDetailId' => $this->bsf->isNullCheck($postData['paySchDetId_'.$i],'number')
                                , 'ReceiptTypeId' => $this->bsf->isNullCheck($rtPer[0],'number')
                                , 'ReceiptType' => $this->bsf->isNullCheck($rtPer[1],'string')
                                , 'Percentage' => $this->bsf->isNullCheck('0','number')
                                , 'SortId' => $this->bsf->isNullCheck($postData['esSortId_'.$i.'_'.$j],'number')));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        } else {
                            if($this->bsf->isNullCheck($postData['scheduleId_'.$i],'number') != "") {
                                $dateFrom = '';
                                $dateAfBef = 0;
                                $duration = 0;
                                $days = 0;
                                $schDate = NULL;
                                $roundOff = 0;
                                $percentage = 0;
                                $isAdvance = 0;
                                $isValue = 0;
                                $amount = 0;
                                $IsAdvanceDeductible = 0;
                                $psSortId = $postData['psSortId_'.$i];

                                if($postData['advanceIncluded']==1) {
                                    if($i == 1) {
                                        if($postData['chosenType_'.$i] != '') {
                                            $stageType = $postData['chosenType_'.$i];
                                        }
                                        $isAdvance = 1;
                                        $isValue = $postData['advAmount_'.$i];
                                        if($postData['advAmount_'.$i] == '1') {
                                            $percentage = $postData['percentage_'.$i];
                                        } else if($postData['advAmount_'.$i] == '2') {
                                            $amount = $postData['amount_'.$i];
                                        }
                                    } else {
                                        if($postData['chosenType_'.$i] != '') {
                                            $stageType = $postData['chosenType_'.$i];
                                        }
                                        $dateFrom = $postData['dateFrom_'.$i];
                                        if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                            $dateAfBef = $postData['dateAfBef_'.$i];
                                        }
                                        $duration = $postData['duration_'.$i];
                                        $days = $postData['days_'.$i];
                                        if($postData['date_'.$i]) {
                                            $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                        }
                                        $roundOff = $postData['roundOff_'.$i];
                                        $percentage = $postData['percentage_'.$i];
                                    }
                                } else {
                                    if($postData['chosenType_'.$i] != '') {
                                        $stageType = $postData['chosenType_'.$i];
                                    }
                                    $dateFrom = $postData['dateFrom_'.$i];
                                    if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                        $dateAfBef = $postData['dateAfBef_'.$i];
                                    }
                                    $duration = $postData['duration_'.$i];
                                    $days = $postData['days_'.$i];
                                    if($postData['date_'.$i]) {
                                        $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                    }
                                    $roundOff = $postData['roundOff_'.$i];
                                    $percentage = $postData['percentage_'.$i];
                                    if($postData['isAdvanceDeductible_'.$i]) {
                                        $IsAdvanceDeductible = $postData['isAdvanceDeductible_'.$i];
                                    }
                                }

                                $insert = $sql->insert();
                                $insert->into('Crm_PaymentScheduleDetail');
                                $insert->Values(array('PaymentScheduleId' => $this->bsf->isNullCheck($paySchId,'number')
                                , 'StageId' => $this->bsf->isNullCheck($postData['scheduleId_'.$i],'number')
                                , 'StageType' => $this->bsf->isNullCheck($stageType,'string')
                                , 'DateFrom' => $this->bsf->isNullCheck($dateFrom,'string')
                                , 'DateAfterBefore' => $this->bsf->isNullCheck($dateAfBef,'number')
                                , 'DurationType' => $this->bsf->isNullCheck($duration,'number')
                                , 'DurationDays' => $this->bsf->isNullCheck($days,'number')
                                , 'Date' => $schDate
                                , 'RoundOff' => $this->bsf->isNullCheck($roundOff,'number')
                                , 'Percentage' => $this->bsf->isNullCheck($percentage,'number')
                                , 'IsAdvance' => $this->bsf->isNullCheck($isAdvance,'number')
                                , 'IsValue' => $this->bsf->isNullCheck($isValue,'number')
                                , 'Amount' => $this->bsf->isNullCheck($amount,'number')
                                , 'IsAdvanceDeductible' => $this->bsf->isNullCheck($IsAdvanceDeductible,'number')
                                , 'SortId' => $this->bsf->isNullCheck($psSortId,'number')
                                , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                                echo $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $paymentScheduleDetailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                for($j=1;$j<=$roTypesCount;$j++) {
                                    $rtPer = explode('#',$postData['receiptType_'.$i.'_'.$j]);
                                    $insert = $sql->insert();
                                    $insert->into('Crm_PaySchReceiptTypePercent');
                                    $insert->Values(array('PaymentScheduleDetailId' => $this->bsf->isNullCheck($paymentScheduleDetailId,'number')
                                    , 'ReceiptTypeId' => $this->bsf->isNullCheck($rtPer[0],'number')
                                    , 'ReceiptType' => $this->bsf->isNullCheck($rtPer[1],'string')
                                    , 'Percentage' => $this->bsf->isNullCheck('0','number')
                                    , 'SortId' => $this->bsf->isNullCheck($postData['esSortId_'.$i.'_'.$j],'number')));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else {
                                if($this->bsf->isNullCheck($postData['description_'.$i],'string') != "") {
                                    $stageType = $postData['chooseInsType_'.$i];
                                    if($postData['chooseInsType_'.$i]=='D') {
                                        $insert = $sql->insert();
                                        $insert->into('Crm_DescriptionMaster');
                                        $insert->Values(array('ProjectId' => $this->bsf->isNullCheck($this->bsf->decode($postData['projectId']),'number')
                                        , 'DescriptionName' => $this->bsf->isNullCheck($postData['description_'.$i],'string')
                                        , 'DescriptionType' => $this->bsf->isNullCheck('','string')
                                        , 'CreatedDate' => date('Y-m-d')
                                        , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $descriptionId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    } else if($postData['chooseInsType_'.$i]=='S') {
                                        $insert = $sql->insert();
                                        $insert->into('KF_StageMaster');
                                        $insert->Values(array('StageName' => $this->bsf->isNullCheck($postData['description_'.$i],'string')
                                        , 'CreatedDate' => date('Y-m-d H:i:s')
                                        , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')
                                        , 'ProjectId' => $this->bsf->isNullCheck($this->bsf->decode($postData['projectId']),'number')));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $descriptionId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                    }

                                    $dateAfBef = 0;
                                    $schDate = NULL;
                                    $isAdvance = 0;
                                    $isValue = 0;
                                    $amount = 0;
                                    $IsAdvanceDeductible = 0;
                                    $psSortId = $postData['psSortId_'.$i];

                                    $dateFrom = $postData['dateFrom_'.$i];
                                    if($postData['dateFrom_'.$i] == 'S' || $postData['dateFrom_'.$i] == 'E') {
                                        $dateAfBef = $postData['dateAfBef_'.$i];
                                    }
                                    $duration = $postData['duration_'.$i];
                                    $days = $postData['days_'.$i];
                                    if($postData['date_'.$i]) {
                                        $schDate = date('Y-m-d', strtotime($postData['date_'.$i]));
                                    }
                                    $roundOff = $postData['roundOff_'.$i];
                                    $percentage = $postData['percentage_'.$i];
                                    if($postData['isAdvanceDeductible_'.$i]) {
                                        $IsAdvanceDeductible = $postData['isAdvanceDeductible_'.$i];
                                    }

                                    $insert = $sql->insert();
                                    $insert->into('Crm_PaymentScheduleDetail');
                                    $insert->Values(array('PaymentScheduleId' => $this->bsf->isNullCheck($paySchId,'number')
                                    , 'StageId' => $this->bsf->isNullCheck($descriptionId,'number')
                                    , 'StageType' => $this->bsf->isNullCheck($stageType,'string')
                                    , 'DateFrom' => $this->bsf->isNullCheck($dateFrom,'string')
                                    , 'DateAfterBefore' => $this->bsf->isNullCheck($dateAfBef,'number')
                                    , 'DurationType' => $this->bsf->isNullCheck($duration,'number')
                                    , 'DurationDays' => $this->bsf->isNullCheck($days,'number')
                                    , 'Date' => $schDate
                                    , 'RoundOff' => $this->bsf->isNullCheck($roundOff,'number')
                                    , 'Percentage' => $this->bsf->isNullCheck($percentage,'number')
                                    , 'IsAdvance' => $this->bsf->isNullCheck($isAdvance,'number')
                                    , 'IsValue' => $this->bsf->isNullCheck($isValue,'number')
                                    , 'Amount' => $this->bsf->isNullCheck($amount,'number')
                                    , 'IsAdvanceDeductible' => $this->bsf->isNullCheck($IsAdvanceDeductible,'number')
                                    , 'SortId' => $this->bsf->isNullCheck($psSortId,'number')
                                    , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                                    echo $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $paymentScheduleDetailId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    for($j=1;$j<=$roTypesCount;$j++) {
                                        $rtPer = explode('#',$postData['receiptType_'.$i.'_'.$j]);
                                        $insert = $sql->insert();
                                        $insert->into('Crm_PaySchReceiptTypePercent');
                                        $insert->Values(array('PaymentScheduleDetailId' => $this->bsf->isNullCheck($paymentScheduleDetailId,'number')
                                        , 'ReceiptTypeId' => $this->bsf->isNullCheck($rtPer[0],'number')
                                        , 'ReceiptType' => $this->bsf->isNullCheck($rtPer[1],'string')
                                        , 'Percentage' => $this->bsf->isNullCheck('0','number')
                                        , 'SortId' => $this->bsf->isNullCheck($postData['esSortId_'.$i.'_'.$j],'number')));
                                        $statement = $sql->getSqlStringForSqlObject($insert);
                                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }
                        }
                    }
                    // die;
                }
                $connection->commit();
                $this->redirect()->toRoute('crm/payment-schedule-register', array('controller' => 'project', 'action' => 'payment-schedule-register', 'projectId' => $postData['projectId']));
            } catch(PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }
        return $this->_view;
    }

    public function paymentScheduleDeleteAction()
    {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $response = $this->getResponse();

            if ($request->isPost()) {
                $status = "failed";
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $paySchId = $this->bsf->isNullCheck($this->params()->fromPost('paySchId'),'number');
                    $remarks = $this->bsf->isNullCheck($this->params()->fromPost('remarks'),'string');

                    $update = $sql->update();
                    $update->table('Crm_PaymentSchedule')
                        ->set(array('DeleteFlag' => '1', 'DeletedOn' => date('Y/m/d H:i:s'), 'DeleteRemarks' => $remarks))
                        ->where(array('PaymentScheduleId' => $paySchId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $update = $sql->update();
                    $update->table('Crm_PaymentScheduleDetail')
                        ->set(array('DeleteFlag' => '1'))
                        ->where(array('PaymentScheduleId' => $paySchId));
                    $statement = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
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

    public function getprojectsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql     = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $select = $sql->select();
                $select->from(array('a' => 'Crm_ProjectDetail'))
                    ->columns(array('TotalFlats', 'TotalBlocks','TotalArea','NoOfFloors',"StartDate"=>new Expression("CONVERT(VARCHAR(10),StartDate,105)"),"EndDate"=>new Expression("CONVERT(VARCHAR(10),EndDate,105)")))
                    ->where(array('a.CostCentreId' => $postParams['cid']));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;

            } else {
                $request = $this->getRequest();
                if ($request->isPost()){
                    //Write your Normal form post code here
                }
            }
        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function unitGenerationAction(){
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

        $projectId = $this->params()->fromRoute('projectId');

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $select->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Crm_BlockMaster')
            ->columns(array('BlockId','BlockName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsBlock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_LevelMaster')
            ->columns(array('LevelId','LevelName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLevel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_UnitTypeMaster')
            ->columns(array('UnitTypeId','UnitTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_SequenceOrder')
            ->columns(array('OrderId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsOrder = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_SequenceBased')
            ->columns(array('BaseId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsBase = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
                if(isset($postParams['Title'])){
                    $title=$postParams['Title'];
                }else{
                    $title='No';
                }
                $blockId= $postParams['BlockId'];
                $levelId = $postParams['LevelId'];
                $unitTypeId = $postParams['UnitTypeId'];
                $flatNo = $postParams['FlatNo'];
                $sequenceBasedId = $postParams['SequenceBasedId'];
                $sequenceOrderId = $postParams['SequenceOrderId'];
                $startFrom = $postParams['StartFrom'];
                $insert = $sql->insert('Crm_UnitGeneration');
                $insertData = array(
                    'BlockId'  => $blockId,
                    'LevelId' => $levelId,
                    'UnitTypeId'=>$unitTypeId,
                    'FlatNo'=>$flatNo,
                    'Title'=>$title,
                    'SequenceBasedId'=>$sequenceBasedId,
                    'SequenceOrderId'=>$sequenceOrderId,
                    'StartFrom'=>$startFrom,
                    // 'ProjectId'=>$projectId,
                );
                $insert->values($insertData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $results= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $unitId = $dbAdapter->getDriver()->getLastGeneratedValue();
                $this->redirect()->toRoute('crm/unit-details', array('controller' => 'project', 'action' => 'unit-details','unitId' => $unitId));
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

//	public function unitTypeGenerationAction(){
//		if(!$this->auth->hasIdentity()) {
//			if($this->getRequest()->isXmlHttpRequest())	{
//				echo "session-expired"; exit();
//			} else {
//				$this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
//			}
//		}
//		//$this->layout("layout/layout");
//		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
//		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
//		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
//		$sql = new Sql($dbAdapter);
//
//		$projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
//		$this->_view->projectId = $projectId;
//
//		$select = $sql->select();
//		$select->from('Proj_ProjectMaster')
//			   ->columns(array('ProjectId','ProjectName'));
//		$statement = $sql->getSqlStringForSqlObject($select);
//		$this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//		$select = $sql->select();
//		$select->from('Proj_ProjectMaster')
//				->columns(array('ProjectId','ProjectName'));
//		$select->where(array("ProjectId"=>$this->_view->projectId));
//		$statement = $sql->getSqlStringForSqlObject($select);
//		$this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//
//		$select = $sql->select();
//		$select->from('Crm_BlockMaster')
//			   ->columns(array('BlockId','BlockName'));
//		$statement = $sql->getSqlStringForSqlObject($select);
//		$this->_view->resultsBlock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//		$select = $sql->select();
//		$select->from('Crm_LevelMaster')
//			   ->columns(array('LevelId','LevelName'));
//		$statement = $sql->getSqlStringForSqlObject($select);
//		$this->_view->resultsLevel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//		$select = $sql->select();
//		$select->from('Crm_UnitTypeMaster')
//			   ->columns(array('UnitTypeId','UnitTypeName'));
//		$statement = $sql->getSqlStringForSqlObject($select);
//		$this->_view->resultsType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//		$select = $sql->select();
//		$select->from('Crm_FacingMaster')
//			   ->columns(array('FacingId','Description'));
//		$statement = $sql->getSqlStringForSqlObject($select);
//		$this->_view->resultsFacing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//		$select = $sql->select();
//		$select->from('Crm_PaymentScheduleUnit')
//			   ->columns(array('TemplateId','Description'));
//		$statement = $sql->getSqlStringForSqlObject($select);
//		$this->_view->resultsPayment = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//		if($this->getRequest()->isXmlHttpRequest())	{
//			$request = $this->getRequest();
//			if ($request->isPost()) {
//				//Write your Ajax post code here
//				$result =  "";
//				$this->_view->setTerminal(true);
//				$response = $this->getResponse()->setContent($result);
//				return $response;
//			}
//		} else {
//			$request = $this->getRequest();
//			if ($request->isPost()) {
//					$postParams = $request->getPost();
//					$unitType = $postParams['UnitType'];
//					$area = $postParams['Area'];
//					$carpetArea = $postParams['CarpetArea'];
//					$udsLandArea = $postParams['UDSLandArea'];
//					$facing = $postParams['Facing'];
//					//page2
//					$rate = $postParams['Rate'];
//					$landAmount = $postParams['LandAmount'];
//					$constructionAmount = $postParams['ConstructionAmount'];
//					$grossAmount = $postParams['GrossAmount'];
//					$levelwiseRate = $postParams['LevelwiseRate'];
//					//page3
//					$baseAmount = $postParams['BaseAmount'];
//					$advanceAmount = $postParams['AdvanceAmount'];
//					$advancePercent = $postParams['AdvancePercent'];
//					$lateInterest = $postParams['LateInterest'];
//					$paymentSchedule = $postParams['PaymentSchedule'];
//					$creditDays = $postParams['CreditDays'];
//
//
//						$insert = $sql->insert('Crm_UnitType');
//						$newData = array(
//							'TypeName' => $unitType,
//							'Area' => $area,
//							'CarpetArea' => $carpetArea,
//							'USLandArea' => $udsLandArea,
//							'FacingId' => $facing,
//							'Rate' => $rate,
//							'LandAmount' => $landAmount,
//							'ConstructionAmount' => $constructionAmount,
//							'FloorwiseRate' => $levelwiseRate,
//							'BaseAmt' => $baseAmount,
//							'AdvAmount' => $advanceAmount,
//							'AdvPercent' => $advancePercent,
//							'IntPercent' => $lateInterest,
//							'PayTypeId' => $paymentSchedule,
//							'CreditDays' => $creditDays,
//							'ProjectId' => $projectId
//						);
//						$insert->values($newData);
//					    $statement = $sql->getSqlStringForSqlObject($insert);
//						$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//						$unitTypeId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//					$this->redirect()->toRoute('crm/car-park', array('controller' => 'project', 'action' => 'car-park', 'projectId' => $this->bsf->encode($projectId)));
//				}
//			//Common function
//			$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
//
//			return $this->_view;
//		}
//	}

    public function unitTypeGenerationAction() {
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
//        if ($request->isPost()
//            && $viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
//            // CSRF attack
//            if($this->getRequest()->isXmlHttpRequest())	{
//                // AJAX
//                $response->setStatusCode(401);
//                $response->setContent('CSRF attack');
//                return $response;
//            } else {
//                // Normal
//                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
//            }
//        }

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
                    $postData = $request->getPost();
                    $unitTypeId = $this->bsf->isNullCheck($postData['copyUnitTypeId'], 'number');

                    $result =  "";
                    $unitTypeMaster="";
                    $unitTypeDetails="";
                    $unitOtherCost="";
                    $unitExtraItem="";

                    $select = $sql->select();
                    $select->from('KF_UnitTypeMaster')
                        ->columns(array('*'))
                        ->where(array("UnitTypeId" =>$unitTypeId,"DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $unitTypeMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('Crm_UnitType')
                        ->columns(array('*'))
                        ->where(array("UnitTypeId" =>$unitTypeId,"DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $unitTypeDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from('Crm_UnitTypeOtherCostTrans')
                        ->columns(array('*'))
                        ->where(array("UnitTypeId" =>$unitTypeId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $unitOtherCost = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from('Crm_UnitTypeExtraItem')
                        ->columns(array('*'))
                        ->where(array("UnitTypeId" =>$unitTypeId,"DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $unitExtraItem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result=array($unitTypeMaster,$unitTypeDetails,$unitOtherCost,$unitExtraItem);

                    $connection->commit();
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent(json_encode($result));
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

                    $projectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number');
                    if(!is_numeric($projectId)) {
                        throw new \Exception('Invalid Project!');
                    }

                    $arrKFValues = array(
                        'ProjectId' => $projectId,
                        'UnitTypeName' => $this->bsf->isNullCheck($postData['UnitTypeName'], 'string'),
                        'Title' => $this->bsf->isNullCheck($postData['UnitTypeName'], 'string'),
                        'Area' => $this->bsf->isNullCheck($postData['Area'], 'number')
                    );

                    $insert = $sql->insert();
                    $insert->into('KF_UnitTypeMaster')
                        ->values($arrKFValues);
                    $stmt = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $unitTypeId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $arrValues = array(
                        'UnitTypeId' => $unitTypeId,
                        'CarpetArea' => $this->bsf->isNullCheck($postData['CarpetArea'], 'number'),
                        'UDSLandArea' => $this->bsf->isNullCheck($postData['UDSLandArea'], 'number'),
                        'FacingId' => $this->bsf->isNullCheck($postData['Facing'], 'number'),
                        'Rate' => $this->bsf->isNullCheck($postData['Rate'], 'number'),
                        'LandAmount' => $this->bsf->isNullCheck($postData['LandAmount'], 'number'),
                        'ConstructionAmount' => $this->bsf->isNullCheck($postData['ConstructionAmount'], 'number'),
                        'BaseAmt' => $this->bsf->isNullCheck($postData['BaseAmount'], 'number'),
                        'AdvAmount' => $this->bsf->isNullCheck($postData['AdvanceAmount'], 'number'),
                        'AdvPercent' => $this->bsf->isNullCheck($postData['AdvancePercent'], 'number'),
                        'IntPercent' => $this->bsf->isNullCheck($postData['LateInterest'], 'number'),
                        'PaymentScheduleId' => $this->bsf->isNullCheck($postData['PaymentSchedule'], 'number'),
                        'CreditDays' => $this->bsf->isNullCheck($postData['CreditDays'], 'number'),
                        'GuideLineValue' => $this->bsf->isNullCheck($postData['GuideLineValue'], 'number'),
                        'MarketLandValue' => $this->bsf->isNullCheck($postData['MarketLandValue'], 'number'),
                        'GrossAmt' => $this->bsf->isNullCheck($postData['GrossAmount'], 'number')
                    );

                    $insert = $sql->insert();
                    $insert->into('Crm_UnitType')
                        ->values($arrValues);
                    $stmt = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    // Other cost trans
                    if(!empty($postData['OtherCost'])) {

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
                            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostId', 'OtherCostName'), $select::JOIN_LEFT)
                            ->where(array('ProjectId' => $projectId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrOtherCosts = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($postData['OtherCost'] as $otherCostId) {
                            foreach($arrOtherCosts as $otherCost) {
                                if($otherCost['OtherCostId'] == $otherCostId) {
                                    $insert = $sql->insert();
                                    $insert->into( 'Crm_UnitTypeOtherCostTrans' )
                                        ->values(array('UnitTypeId' => $unitTypeId,
                                                'OtherCostId' => $otherCostId,
                                                'Amount' => $otherCost['Amount'])
                                        );
                                    $stmt = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                                    break;
                                }
                            }
                        }
                    }

                    $connection->commit();

                    $redirectUrl = $this->bsf->isNullCheck($postData['redirectUrl'], 'string');
                    if(is_null($redirectUrl) || $redirectUrl == '') {
                        $this->redirect()->toRoute("crm/default", array("controller" => "project", "action" => "index"));
                    } else {
                        $this->redirect()->toRoute("crm/car-park", array("controller" => "project",
                            "action" => "car-park" , 'projectId' => $this->bsf->encode($projectId)));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
                    $this->_view->projectId = $projectId;

                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'))
                        ->where(array("ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }

                    $select = $sql->select();
                    $select->from('Crm_ProjectDetail')
                        ->where(array("ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    // Projects
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // Facing
                    $select = $sql->select();
                    $select->from('Crm_FacingMaster')
                        ->columns(array('FacingId','Description'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsFacing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // existing unittypes
                    $select = $sql->select();
                    $select->from('KF_UnitTypeMaster')
                        ->where(array('ProjectId' => $projectId, 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);

                    $arrExistingUnitTypes = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrExistingUnitTypes=$arrExistingUnitTypes;
                    $arrExistingUnitTypeNames = array();
                    foreach($arrExistingUnitTypes as $unitType) {
                        $arrExistingUnitTypeNames[] = strtolower($unitType['UnitTypeName']);
                    }
                    $this->_view->jsonExistingUnitTypeNames = json_encode($arrExistingUnitTypeNames);

                    // other cost
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
                        ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostId', 'OtherCostName'), $select::JOIN_LEFT)
                        ->where(array('ProjectId' => $projectId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrOtherCosts = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrOtherCosts = $arrOtherCosts;

                    // payment schedule
                    $select = $sql->select();
                    $select->from('Crm_PaymentSchedule')
                        ->where(array('ProjectId' => $projectId, 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrPaymentSchedules = $arrPaymentSchedules;

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

    public function unitTypeRegisterAction() {
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
                $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
                $this->_view->projectId = $projectId;
                $postData = $request->getPost();
                //print_r($postData); die;
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {


                    $connection->commit();
                    if(isset($postData['saveNext'])) {
                        $this->redirect()->toRoute('crm/facility', array('controller' => 'project', 'action' => 'facility', 'projectId' => $this->bsf->encode($projectId)));
                    } else {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
                    $this->_view->projectId = $projectId;

                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'))
                        ->where(array("ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }

                    // Projects
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitTypeMaster'))
                        ->join(array('b' => 'Crm_UnitType'), 'b.UnitTypeId=a.UnitTypeId', array('CarpetArea', 'UDSLandArea', 'Rate', 'BaseAmt'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Crm_FacingMaster'), 'c.FacingId=b.FacingId', array('Facing' => 'Description'), $select::JOIN_LEFT)
                        ->where(array('a.ProjectId' => $projectId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnitTypes = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->jsonUnitTypes = json_encode($arrUnitTypes);

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

    public function unitTypeEditAction() {
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here
                    $BlockId = $this->bsf->isNullCheck($this->params()->fromPost('BlockId'), 'number');
                    $Type = $this->bsf->isNullCheck($this->params()->fromPost('type'), 'string');


                    if($Type == 'fromFloor') {
                        //Floor Name Block Wise
                        $select = $sql->select();
                        $select->from('KF_FloorMaster')
                            ->where(array('BlockId' => $BlockId , 'DeleteFlag' => 0));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response->setStatusCode(200);
                        $response->setContent(json_encode($result));
                    } else if($Type == 'toFloor') {
//                        $fromFloorId =$this->params()->fromPost('fromFloorId');
                        $fromFloorId = $this->bsf->isNullCheck($this->params()->fromPost('fromFloorId'), 'number');
                        $SortId = $this->bsf->isNullCheck($this->params()->fromPost('SortId'), 'number');

                        //Floor Name Block Wise
                        $select = $sql->select();
                        $select->from('KF_FloorMaster')
                            ->where('BlockId='.$BlockId.'and DeleteFlag=0 and FloorId <> '.$fromFloorId.' and SortId >'.$SortId);
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $result = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response->setStatusCode(200);
                        $response->setContent(json_encode($result));
                    }



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

                    // $projectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number');
                    if(!is_numeric($projectId)) {
                        throw new \Exception('Invalid Project!');
                    }

                    $unitTypeId = $this->bsf->isNullCheck($postData['UnitTypeId'], 'number');
                    if(!is_numeric($unitTypeId)) {
                        throw new \Exception('Invalid Unit-Type!');
                    }
                    $Area = $this->bsf->isNullCheck($postData['Area'], 'number');

                    $arrKFValues = array(
                        'UnitTypeName' => $this->bsf->isNullCheck($postData['UnitTypeName'], 'string'),
                        'Title' => $this->bsf->isNullCheck($postData['UnitTypeName'], 'string'),
                        'Area' => $Area,
                        'EditFlag' => 1
                    );

                    $update = $sql->update();
                    $update->table('KF_UnitTypeMaster')
                        ->set($arrKFValues)
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $dbAdapter->getDriver()->getLastGeneratedValue();
                    $floorWiseReq = $this->bsf->isNullCheck($postData['floor_wise_yes_no'], 'number');

                    $arrValues = array(
                        'CarpetArea' => $this->bsf->isNullCheck($postData['CarpetArea'], 'number'),
                        'UDSLandArea' => $this->bsf->isNullCheck($postData['UDSLandArea'], 'number'),
                        'FacingId' => $this->bsf->isNullCheck($postData['Facing'], 'number'),
                        'Rate' => $this->bsf->isNullCheck($postData['Rate'], 'number'),
                        'LandAmount' => $this->bsf->isNullCheck($postData['LandAmount'], 'number'),
                        'ConstructionAmount' => $this->bsf->isNullCheck($postData['ConstructionAmount'], 'number'),
                        'BaseAmt' => $this->bsf->isNullCheck($postData['BaseAmount'], 'number'),
                        'AdvAmount' => $this->bsf->isNullCheck($postData['AdvanceAmount'], 'number'),
                        'AdvPercent' => $this->bsf->isNullCheck($postData['AdvancePercent'], 'number'),
                        'IntPercent' => $this->bsf->isNullCheck($postData['LateInterest'], 'number'),
                        'PaymentScheduleId' => $this->bsf->isNullCheck($postData['PaymentSchedule'], 'number'),
                        'CreditDays' => $this->bsf->isNullCheck($postData['CreditDays'], 'number'),
                        'GuideLineValue' => $this->bsf->isNullCheck($postData['GuideLineValue'], 'number'),
                        'MarketLandValue' => $this->bsf->isNullCheck($postData['MarketLandValue'], 'number'),
                        'GrossAmt' => $this->bsf->isNullCheck($postData['GrossAmount'], 'number'),
                        'NetAmt' => $this->bsf->isNullCheck($postData['NetAmt'], 'number'),
                        'LandRegAmount' => $this->bsf->isNullCheck($postData['landReg'], 'number'),
                        'ConsRegAmount' => $this->bsf->isNullCheck($postData['constructionReg'], 'number'),
                        'ProjectId' =>$projectId,
                        'FloorWiseRequired' => $floorWiseReq
                    );

                    $update = $sql->update();
                    $update->table('Crm_UnitType')
                        ->set($arrValues)
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $delete = $sql->delete();
                    $delete->from('Crm_UnitTypeFloorWiseRate')
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);


                    // Other cost trans
                    $delete = $sql->delete();
                    $delete->from('Crm_UnitTypeOtherCostTrans')
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $dOtherCost=0;
                    if(!empty($postData['OtherCost'])) {
                        foreach($postData['OtherCost'] as $otherCostId) {
                            $otherCostAmt = $this->bsf->isNullCheck($postData['selectedOtherCostValue_' . $otherCostId], 'number');
                            $otherCostArea=$this->bsf->isNullCheck($postData['selectedOtherCostArea_' . $otherCostId], 'number');
                            $otherCostRate=$this->bsf->isNullCheck($postData['selectedOtherCostRate_' . $otherCostId], 'number');

                            $dOtherCost = $dOtherCost+$otherCostAmt;

                            if(!is_numeric($otherCostId) && trim($otherCostId) != '' ) {
                                $otherCostName = trim($otherCostId);
                                // add new other cost
                                $insert = $sql->insert('Crm_OtherCostMaster')
                                    ->values(array(
                                        'OtherCostName' => $otherCostName,
                                        'CreatedDate' => date('Y-m-d H:i:s'),
                                    ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $otherCostId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $insert = $sql->insert('Crm_ProjectOtherCostTrans')
                                    ->values(array(
                                        'ProjectId' => $projectId,
                                        'OtherCostId' => $otherCostId,
                                    ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $insert = $sql->insert();
                            $insert->into( 'Crm_UnitTypeOtherCostTrans' )
                                ->values(array('UnitTypeId' => $unitTypeId,
                                        'OtherCostId' => $otherCostId,
                                        'Amount' => $otherCostAmt,
                                        'Area' => $otherCostArea,
                                        'Rate' => $otherCostRate)
                                );
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }


                    // update unsold unit's details
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitDetails'))
                        ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array('*'), $select::JOIN_LEFT)
                        ->where(array('b.UnitTypeId' => $unitTypeId, 'b.DeleteFlag' => 0, 'b.Status' => 'U'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnitDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    if($floorWiseReq==1) {

                        for($i=1;$i<=intval($postData['blockrowid']);$i++) {
                            $blockId = $this->bsf->isNullCheck($postData['blockid_'.$i], 'number');
                            for($j=1;$j<=intval($postData['floorrowid_'.$i]);$j++) {
                                $FloorId= $this->bsf->isNullCheck($postData['block_'.$i.'_floor_id_'.$j],'number');
                                $FloorRiseRate= $this->bsf->isNullCheck($postData['block_'.$i.'_floor_rise_'.$j],'number');
                                $FloorRate= $this->bsf->isNullCheck($postData['block_'.$i.'_floor_rate_'.$j],'number');

                                $insert = $sql->insert('Crm_UnitTypeFloorWiseRate')
                                    ->values(array(
                                        'FloorId' => $FloorId,
                                        'FloorRiseRate' => $FloorRiseRate,
                                        'FloorRate' => $FloorRate,
                                        'BlockId' => $blockId,
                                        'UnitTypeId' => $unitTypeId
                                    ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }
                        }
                        foreach($arrUnitDetails as $unitDetail) {

                            $select = $sql->select();
                            $select->from('Crm_UnitTypeFloorWiseRate')
                                ->where(array('UnitTypeId' => $unitDetail['UnitTypeId'], 'FloorId' =>$unitDetail['FloorId'] ));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $floorWiseDetail = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $BaseAmount = $Area * $floorWiseDetail['FloorRate'];
                            $ConstructionAmt = $BaseAmount-$arrValues['LandAmount'];
                            $GrossAmt = $ConstructionAmt+$arrValues['LandAmount'];

                            $arrUnitDetailsVal = array(

                                'Rate' => $floorWiseDetail['FloorRate'],
                                'BaseAmt' => $BaseAmount,
                                'AdvPercent' => $arrValues['AdvPercent'],
                                'AdvAmount' => $arrValues['AdvAmount'],
                                'GuideLineValue' => $arrValues['GuideLineValue'],
                                'FacingId' => $arrValues['FacingId'],
                                'CreditDays' => $arrValues['CreditDays'],
                                'LandAmount' => $arrValues['LandAmount'],
                                'ConstructionAmount' => $ConstructionAmt,
                                'OtherCostAmt' => $dOtherCost,
                                'GrossAmount' => $GrossAmt,
                                'MarketLandValue' => $arrValues['MarketLandValue'],
                                'CarpetArea' => $arrValues['CarpetArea'],
                                'UDSLandArea' => $arrValues['UDSLandArea'],
                                'IntPercent' => $arrValues['IntPercent'],
                                'NetAmt' => $GrossAmt
                            );

                            $update = $sql->update();
                            $update->table('Crm_UnitDetails')
                                ->set($arrUnitDetailsVal)
                                ->where(array('UnitId' => $unitDetail['UnitId']));
                            $stmt = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    } else {

                        $arrUnitDetailsVal = array(

                            'Rate' => $arrValues['Rate'],
                            'BaseAmt' => $arrValues['BaseAmt'],
                            'AdvPercent' => $arrValues['AdvPercent'],
                            'AdvAmount' => $arrValues['AdvAmount'],
                            'GuideLineValue' => $arrValues['GuideLineValue'],
                            'FacingId' => $arrValues['FacingId'],
                            'CreditDays' => $arrValues['CreditDays'],
                            'LandAmount' => $arrValues['LandAmount'],
                            'ConstructionAmount' => $arrValues['ConstructionAmount'],
                            'OtherCostAmt' => $dOtherCost,
                            'GrossAmount' => $arrValues['GrossAmt'],
                            'MarketLandValue' => $arrValues['MarketLandValue'],
                            'CarpetArea' => $arrValues['CarpetArea'],
                            'UDSLandArea' => $arrValues['UDSLandArea'],
                            'IntPercent' => $arrValues['IntPercent'],
                            'NetAmt' => $arrValues['GrossAmt']
                        );
                        foreach($arrUnitDetails as $unitDetail) {

                            $update = $sql->update();
                            $update->table('Crm_UnitDetails')
                                ->set($arrUnitDetailsVal)
                                ->where(array('UnitId' => $unitDetail['UnitId']));
                            $stmt = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }


                    // Extra items
                    $delete = $sql->delete();
                    $delete->from('Crm_UnitTypeExtraItem')
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    if(!empty($postData['ExtraItem'])) {
                        foreach($postData['ExtraItem'] as $extraItemId) {
//                            $otherCostAmt = $this->bsf->isNullCheck($postData['selectedOtherCostValue_' . $otherCostId], 'number');

                            $arrExtraItemValues = array(
                                'Project' => $projectId,
                                'UnitTypeId' => $unitTypeId
                            );
                            if(!is_numeric($extraItemId) && trim($extraItemId) != '' ) {
                                $otherCostName = trim($extraItemId);
                                // add new other cost
                                $insert = $sql->insert('Crm_ExtraItemMaster')
                                    ->values(array(
                                        'ItemDescription' => $otherCostName,
                                        'CreatedDate' => date('Y-m-d H:i:s'),
                                        'ProjectId' => $projectId
                                    ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $extraItemId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $arrExtraItemValues['ExtraRate'] = 0;
                                $arrExtraItemValues['Qty'] = 0;
                                $arrExtraItemValues['Amount'] = 0;
                                $arrExtraItemValues['NetAmount'] = 0;
                                $arrExtraItemValues['ItemDescription'] = $otherCostName;
                            } else {
                                $select = $sql->select();
                                $select->from('Crm_ExtraItemMaster')
                                    ->where(array('ExtraItemId' => $extraItemId));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $extraItem = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $arrExtraItemValues['ExtraRate'] = 0;
                                $arrExtraItemValues['Qty'] = $extraItem['Qty'];
                                $arrExtraItemValues['Amount'] = $extraItem['Amount'];
                                $arrExtraItemValues['NetAmount'] = $extraItem['Qty'] * $extraItem['Amount'];
                                $arrExtraItemValues['ItemDescription'] = $extraItem['ItemDescription'];
                            }

                            $arrExtraItemValues['ExtraItemId'] = $extraItemId;

                            $insert = $sql->insert();
                            $insert->into( 'Crm_UnitTypeExtraItem' )
                                ->values($arrExtraItemValues);
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $delete = $sql->delete();
                    $delete->from('CRM_UnitTypePhotoTrans')
                        ->where(array('UnitTypeId' =>$unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    //upload UnitType Image file
                    $files = $request->getFiles();

                    foreach($_FILES['files']['name'] as $key=>$val){
                        //upload and stored images
                        //$target_dir = "uploads/crm/projectunittype/". $unitTypeId;
                        //$target_file = $target_dir.$_FILES['files']['tmp_name'][$key];
                        $fileName=$_FILES['files']['name'][$key];
                        if($fileName != ""){
                            $dir = "public/uploads/crm/projectunittype/". $unitTypeId. '/';
                            if(!is_dir($dir))
                                mkdir($dir, 0755, true);

                            $path = $dir.$_FILES['files']['name'][$key];
                            move_uploaded_file($_FILES['files']['tmp_name'][$key], $path);
                            $url = "uploads/crm/projectunittype/". $unitTypeId. '/' .$fileName;

                            $insert = $sql->insert();
                            $insert  = $sql->insert('CRM_UnitTypePhotoTrans');
                            $newData = array(
                                'UnitTypeId'=>$unitTypeId,
                                'ImageUrl' =>$url
                            );
                            $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();

                    $redirectUrl = $this->bsf->isNullCheck($postData['redirectUrl'], 'string');
                    if(is_null($redirectUrl) || $redirectUrl == '') {
                        $this->redirect()->toRoute("crm/default", array("controller" => "project", "action" => "index"));
                    } else {
                        $this->redirect()->toRoute("crm/unit-type-register", array("controller" => "project",
                            "action" => 'unit-type-register', 'projectId' => $this->bsf->encode($projectId)));
                    }
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
                    $this->_view->projectId = $projectId;

                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'))
                        ->where(array("ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }

                    $unitTypeId = $this->bsf->isNullCheck($this->params()->fromRoute('unitTypeId'), 'number');

                    if(!is_numeric($unitTypeId)) {
                        throw new \Exception('Invalid Unit-Type!');
                    }
                    $select = $sql->select()
                        ->from("KF_BlockMaster")
                        ->columns(array('BlockId','BlockName'))
                        ->where("ProjectId=$projectId and DeleteFlag= '0'");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->BlockDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $subQuery = $sql->select()
                        ->from("KF_BlockMaster")
                        ->columns(array('BlockId'))
                        ->where("ProjectId=$projectId and DeleteFlag= '0'");

                    $select = $sql->select();
                    $select->from(array('a'=>'KF_FloorMaster'))
                        ->join(array('b' => 'Crm_UnitTypeFloorWiseRate'), new Expression("b.FloorId=a.FloorId and b.UnitTypeId=$unitTypeId"), array('FloorRiseRate'=>new expression("b.FloorRiseRate"),'FloorRate'=>new expression("b.FloorRate")), $select::JOIN_LEFT)
                        ->columns(array('FloorId','FloorName','BlockId'))
                        ->where->expression('a.BlockId IN ?', array($subQuery));
                    $select->order(new Expression("a.BlockId, a.FloorId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->FloorDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitTypeMaster'))
                        ->join(array('b' => 'Crm_UnitType'), 'b.UnitTypeId=a.UnitTypeId', array('CarpetArea', 'FacingId', 'Rate', 'LandAmount', 'ConstructionAmount', 'BaseAmt','LandRegAmount','ConsRegAmount',
                            'AdvAmount', 'AdvPercent', 'IntPercent', 'PaymentScheduleId', 'CreditDays', 'GuideLineValue',
                            'MarketLandValue', 'GrossAmt', 'NetAmt','FloorWiseRequired'), $select::JOIN_LEFT)
                        ->join(array('c' => 'Crm_ProjectDetail'), 'c.ProjectId=a.ProjectId', array('UDSLandArea'=>new Expression('CASE WHEN c.BuildupArea > 0 THEN (a.Area / c.BuildupArea)*c.LandArea ELSE 0 END'),'BuildupArea','LandArea'), $select::JOIN_LEFT)
                        ->where(array('a.UnitTypeId' => $unitTypeId, 'a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitTypeInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(empty($unitTypeInfo)) {
                        $this->redirect()->toRoute('crm/unit-type-register', array(
                            'controller' => 'project',
                            'action' => 'unit-type-register',
                            'projectId' => $this->params()->fromRoute('projectId')
                        ));
                    }
                    $this->_view->unitTypeInfo = $unitTypeInfo;

                    // Projects
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from('Crm_ProjectDetail')
                        ->where(array("ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ConstructOtherCost'))
                        ->columns(array('OtherCostId'))
                        // ->join(array("b"=>"Crm_ProjectOtherCostTrans"), "a.OtherCostId=b.OtherCostId", array('Amount'), $select::JOIN_INNER)
                        ->where(array("a.ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultconstruct = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // Facing
                    $select = $sql->select();
                    $select->from('Crm_FacingMaster')
                        ->columns(array('FacingId','Description'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsFacing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql->select();
                    $select->from('Crm_UnitType')
                        ->columns(array('*'))
                        ->where(array("UnitTypeId" =>$unitTypeId,"DeleteFlag"=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $unitTypeDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->unitTypeDetails=$unitTypeDetails;




                    $select = $sql->select();
                    $select->from(array('a' => 'KF_Conception'))
                        ->columns(array("BusinessTypeId"))
                        ->join(array("b"=>"Proj_ProjectMaster"), "a.KickOffId=b.KickOffId", array(), $select::JOIN_LEFT)
                        ->where(array("b.ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsinfo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    // existing unittypes
                    $select = $sql->select();
                    $select->from(array('a'=>'KF_UnitTypeMaster'))
                        ->where(array('a.ProjectId' => $projectId, 'a.DeleteFlag' => 0,'a.EditFlag'=>1))
                        ->where('a.UnitTypeId <> ' . $unitTypeId);
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrExistingUnitTypes = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrExistingUnitTypes=$arrExistingUnitTypes;

                    $arrExistingUnitTypeNames = array();
                    foreach($arrExistingUnitTypes as $unitType) {
                        $arrExistingUnitTypeNames[] = strtolower($unitType['UnitTypeName']);
                    }
                    $this->_view->jsonExistingUnitTypeNames = json_encode($arrExistingUnitTypeNames);

                    // other cost
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
                        ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostId', 'OtherCostName'), $select::JOIN_LEFT)
                        ->where(array('ProjectId' => $projectId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrOtherCosts = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrOtherCosts = $arrOtherCosts;

                    // selected Other costs Ids
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
                        ->join(array('b' => 'Crm_OtherCostMaster'), 'b.OtherCostId=a.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrExistingOtherCosts = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $arrExistOtherCostIds = array();
                    if(!empty($arrExistingOtherCosts)) {
                        foreach($arrExistingOtherCosts as $existOtherCost) {
                            $arrExistOtherCostIds[] = $existOtherCost['OtherCostId'];
                        }
                    }
                    $this->_view->arrExistOtherCostIds = $arrExistOtherCostIds;
                    $this->_view->arrExistingOtherCosts = $arrExistingOtherCosts;

                    // Extra items
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ExtraItemMaster'))
                        ->join(array('b' => 'Proj_UOM'), 'a.MUnitId=b.UnitId', array('UnitName'), $select::JOIN_LEFT)
                        ->where(array('ProjectId' => $projectId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrExtraItems = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrExtraItems = $arrExtraItems;

                    // selected Other costs Ids
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitTypeExtraItem'))
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrExistingExtraItems = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $arrExistExtraItemIds = array();
                    if(!empty($arrExistingExtraItems)) {
                        foreach($arrExistingExtraItems as $existExtraItem) {
                            $arrExistExtraItemIds[] = $existExtraItem['ExtraItemId'];
                        }
                    }
                    $this->_view->arrExistExtraItemIds = $arrExistExtraItemIds;

                    $select = $sql->select();
                    $select->from('CRM_UnitTypePhotoTrans')
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrPhotos = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrPhotos = $arrPhotos;

                    // payment schedule
                    $select = $sql->select();
                    $select->from('Crm_PaymentSchedule')
                        ->where(array('ProjectId' => $projectId, 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrPaymentSchedules = $arrPaymentSchedules;

                    //Block Name Project Wise
                    $select = $sql->select();
                    $select->from('KF_BlockMaster')
                        ->where(array('UnitTypeId' => $unitTypeId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrBlockName = $arrPaymentSchedules;

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

    public function unitEditAction() {
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
        // Facing
        $select = $sql->select();
        $select->from('Crm_FacingMaster')
            ->columns(array('FacingId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsFacing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

                    $unitId = $this->bsf->isNullCheck($postData['UnitId'], 'number');
                    if(!is_numeric($unitId)) {
                        throw new \Exception('Invalid Unit-Type!');
                    }

                    $arrKFValues = array(
                        'UnitNo' => $this->bsf->isNullCheck($postData['UnitName'], 'string'),
                        'UnitArea' => $this->bsf->isNullCheck($postData['Area'], 'number')
                    );


                    $update = $sql->update();
                    $update->table('KF_UnitMaster')
                        ->set($arrKFValues)
                        ->where(array('UnitId' => $unitId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $dbAdapter->getDriver()->getLastGeneratedValue();

                    $arrValues = array(
                        'CarpetArea' => $this->bsf->isNullCheck($postData['CarpetArea'], 'number'),
                        'UDSLandArea' => $this->bsf->isNullCheck($postData['UDSLandArea'], 'number'),
                        'FacingId' => $this->bsf->isNullCheck($postData['Facing'], 'number'),
                        'Rate' => $this->bsf->isNullCheck($postData['Rate'], 'number'),
                        'LandAmount' => $this->bsf->isNullCheck($postData['LandAmount'], 'number'),
                        'ConstructionAmount' => $this->bsf->isNullCheck($postData['ConstructionAmount'], 'number'),
                        'BaseAmt' => $this->bsf->isNullCheck($postData['BaseAmount'], 'number'),
                        'AdvAmount' => $this->bsf->isNullCheck($postData['AdvanceAmount'], 'number'),
                        'AdvPercent' => $this->bsf->isNullCheck($postData['AdvancePercent'], 'number'),
                        'IntPercent' => $this->bsf->isNullCheck($postData['LateInterest'], 'number'),
                        'CreditDays' => $this->bsf->isNullCheck($postData['CreditDays'], 'number'),
                        'GuideLineValue' => $this->bsf->isNullCheck($postData['GuideLineValue'], 'number'),
                        'MarketLandValue' => $this->bsf->isNullCheck($postData['MarketLandValue'], 'number'),
                        'GrossAmount' => $this->bsf->isNullCheck($postData['GrossAmount'], 'number'),
                        'NetAmt' => $this->bsf->isNullCheck($postData['NetAmt'], 'number')
                    );

                    $update = $sql->update();
                    $update->table('Crm_UnitDetails')
                        ->set($arrValues)
                        ->where(array('UnitId' => $unitId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $files = $request->getFiles();

                    $delete = $sql->delete();
                    $delete->from('Crm_UnitImageTrans')
                        ->where(array('UnitId'=>$unitId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                     $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                    foreach($files["files"] as $index => $file) {

                        $fileTempName = $file['tmp_name'];

                        if(!empty($file['error'][$index])) {
                            return false;
                        }

                        if(!empty($fileTempName) && is_uploaded_file($fileTempName)) {
                            $url = "public/uploads/crm/unitImg/";
                            $filename = $this->bsf->uploadFile($url, $file);
                            if ($filename) {
                                // update valid files only
                                $url = 'uploads/crm/unitImg/' . $filename;

                                $insert  = $sql->insert('Crm_UnitImageTrans');
                                $newData = array(
                                    'UnitId'=>$unitId,
                                    'ImageUrl' =>$url
                                );
                                $insert->values($newData);
                            $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }
                        }
                    }



//                    // Other cost trans
//                    $delete = $sql->delete();
//                    $delete->from('Crm_UnitTypeOtherCostTrans')
//                        ->where(array('UnitTypeId' => $unitTypeId));
//                    $stmt = $sql->getSqlStringForSqlObject($delete);
//                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
//
//                    if(!empty($postData['OtherCost'])) {
//                        foreach($postData['OtherCost'] as $otherCostId) {
//                            $otherCostAmt = $this->bsf->isNullCheck($postData['selectedOtherCostValue_' . $otherCostId], 'number');
//
//                            if(!is_numeric($otherCostId) && trim($otherCostId) != '' ) {
//                                $otherCostName = trim($otherCostId);
//                                // add new other cost
//                                $insert = $sql->insert('Crm_OtherCostMaster')
//                                    ->values(array(
//                                                 'OtherCostName' => $otherCostName,
//                                                 'CreatedDate' => date('Y-m-d H:i:s'),
//                                             ));
//                                $statement = $sql->getSqlStringForSqlObject($insert);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                                $otherCostId = $dbAdapter->getDriver()->getLastGeneratedValue();
//
//                                $insert = $sql->insert('Crm_ProjectOtherCostTrans')
//                                    ->values(array(
//                                                 'ProjectId' => $projectId,
//                                                 'OtherCostId' => $otherCostId,
//                                             ));
//                                $statement = $sql->getSqlStringForSqlObject($insert);
//                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
//                            }
//
//                            $insert = $sql->insert();
//                            $insert->into( 'Crm_UnitTypeOtherCostTrans' )
//                                ->values(array('UnitTypeId' => $unitTypeId,
//                                             'OtherCostId' => $otherCostId,
//                                             'Amount' => $otherCostAmt)
//                                );
//                            $stmt = $sql->getSqlStringForSqlObject($insert);
//                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
//                        }
//                    }
//
//                    // update unsold unit's details
//                    $select = $sql->select();
//                    $select->from(array('a' => 'Crm_UnitDetails'))
//                        ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array(), $select::JOIN_LEFT)
//                        ->where(array('b.UnitTypeId' => $unitTypeId, 'b.DeleteFlag' => 0, 'b.Status' => 'U'));
//                    $stmt = $sql->getSqlStringForSqlObject($select);
//                    $arrUnitDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $connection->commit();

                    $this->redirect()->toRoute("crm/unit-fulldetails", array("controller" => "project", "action" => "unit-fulldetails", 'UnitDetailId' => $unitId));

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $unitId = $this->bsf->isNullCheck($this->params()->fromRoute('unitId'), 'number');
                    if(!is_numeric($unitId)) {
                        $this->redirect()->toRoute('crm/unit-grid', array('controller' => 'project', 'action' => 'unit-grid', 'projectId' => 1));
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->join(array('b' => 'Crm_UnitDetails'), 'b.UnitId=a.UnitId', array('CarpetArea',
                            'UDSLandArea' , 'FacingId', 'Rate', 'LandAmount', 'ConstructionAmount', 'BaseAmt',
                            'AdvAmount', 'AdvPercent', 'IntPercent', 'CreditDays', 'GuideLineValue',
                            'MarketLandValue', 'GrossAmount', 'NetAmt'), $select::JOIN_LEFT)
                        ->where(array('a.UnitId' => $unitId, 'a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if(empty($unitInfo)) {
                        $this->redirect()->toRoute('crm/unit-grid', array(
                            'controller' => 'project',
                            'action' => 'unit-grid',
                            'projectId' => 1
                        ));
                    }
                    $this->_view->unitInfo = $unitInfo;

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitImageTrans'))
                        ->columns(array('ImageUrl'))
                        ->where(array('UnitId' => $unitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->unitIdImg = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    // Facing
                    $select = $sql->select();
                    $select->from('Crm_FacingMaster')
                        ->columns(array('FacingId','Description'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultsFacing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // existing unittypes
                    $select = $sql->select();
                    $select->from('KF_UnitMaster')
                        ->where(array('ProjectId' => $unitInfo['ProjectId'],
                            'BlockId' => $unitInfo['BlockId'],
                            'FloorId' => $unitInfo['FloorId'],
                            'UnitTypeId' => $unitInfo['UnitTypeId'],
                            'DeleteFlag' => 0))
                        ->where('UnitId <> ' . $unitId);
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrExistingUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $arrExistingUnitNames = array();
                    foreach($arrExistingUnits as $unit) {
                        $arrExistingUnitNames[] = strtolower($unit['UnitNo']);
                    }
                    $this->_view->jsonExistingUnitNames = json_encode($arrExistingUnitNames);

                    $select = $sql->select();
                    $select->from('Crm_ProjectDetail')
                        ->where(array("ProjectId"=>$unitInfo['ProjectId']));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->resultDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // other cost
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ProjectOtherCostTrans'))
                        ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostId', 'OtherCostName'), $select::JOIN_LEFT)
                        ->where(array('ProjectId' => $unitInfo['ProjectId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrOtherCosts = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrOtherCosts = $arrOtherCosts;

                    // selected Other costs Ids
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
                        ->join(array('b' => 'Crm_OtherCostMaster'), 'b.OtherCostId=a.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                        ->where(array('UnitTypeId' => $unitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrExistingOtherCosts = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $arrExistOtherCostIds = array();
                    if(!empty($arrExistingOtherCosts)) {
                        foreach($arrExistingOtherCosts as $existOtherCost) {
                            $arrExistOtherCostIds[] = $existOtherCost['OtherCostId'];
                        }
                    }
                    $this->_view->arrExistOtherCostIds = $arrExistOtherCostIds;
                    $this->_view->arrExistingOtherCosts = $arrExistingOtherCosts;

                    // payment schedule
                    $select = $sql->select();
                    $select->from('Crm_PaymentSchedule')
                        ->where(array('ProjectId' => $unitInfo['ProjectId'], 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrPaymentSchedules = $arrPaymentSchedules;

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

    public function facilityAction()
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
        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function carParkAction()
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

        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->cardet = $this->bsf->isNullCheck($this->params()->fromRoute('carId'), 'number' );

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $select->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Crm_CarParkTypeMaster')
            ->columns(array('TypeId','TypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsCarParkType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->isPost = 0;

        /*$select = $sql->select();
        $select->from(array("a"=>'KF_BlockMaster'))
            ->columns(array('BlockId','BlockName'))
            ->join(array("b"=>"KF_PhaseMaster"),"a.PhaseId=b.PhaseId", array('PhaseId','PhaseName'), $select::JOIN_LEFT)
            ->where(array("a.ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsBlock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_CarParkMaster')
            ->columns(array(new Expression('DISTINCT(BlockId) as BlockId')))
            ->where(array('IsOther' => 0));
        $statement = $sql->getSqlStringForSqlObject($select);
        $slottedBlocks = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $arrSlotBlocks = array();
        foreach($slottedBlocks as $slotBlock) {
            $arrSlotBlocks[] = $slotBlock['BlockId'];
        }
        $this->_view->arrSlBlocks = $arrSlotBlocks;*/


        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $select = $sql->select();
                $select->from(array("a"=>'Crm_CarParkTrans'))
                    ->columns(array('SlotNo','CarParkTransId'))
                    ->join(array("b"=>"Crm_CarParkMaster"),"a.CarParkId=b.CarParkId", array('ProjectId','BlockId','TypeId','AllottedSlots','TotalCarParks'), $select::JOIN_LEFT)
                    ->join(array("c"=>"KF_BlockMaster"), "b.BlockId=c.BlockId", array('PhaseId','BlockName'), $select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_CarParkTypeMaster"), "b.TypeId=d.TypeId", array('TypeName'), $select::JOIN_LEFT)
                    ->join(array("e"=>"KF_PhaseMaster"),"c.PhaseId=e.PhaseId", array('PhaseName'), $select::JOIN_LEFT)
                    ->where(array("b.ProjectId"=>$postParams['proj'] ,"b.IsOther" => 0))
                    ->order("b.BlockId ASC");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postParams = $request->getPost();
                    //echo '<pre>'; print_r($postParams); die;

                    $bkPhId = explode('-',$postParams['blockId']);
                    $blockId = $this->bsf->isNullCheck($bkPhId[0],'number');
                    //$blockName = $bkPhId[1];
                    $phaseTitle = $this->bsf->isNullCheck($postParams['phaseTitle'],'string');
                    $blockTitle = $this->bsf->isNullCheck($postParams['blockTitle'],'string');

                    $insert = $sql->insert();
                    $insert->into('Crm_CarParkMaster');
                    $insert->Values(array('ProjectId' => $this->bsf->isNullCheck($this->bsf->decode($postParams['projectId']),'number')
                    , 'BlockId' => $blockId
                    , 'TypeId' => $this->bsf->isNullCheck($postParams['typeId'],'number')
                    , 'AllottedSlots' => $this->bsf->isNullCheck('0','number')
                    , 'TotalCarParks' => $this->bsf->isNullCheck($postParams['totalCarParks'],'number')
                    , 'FacilityName' => $this->bsf->isNullCheck('','string')
                    , 'IsOther' => $this->bsf->isNullCheck('0','number')
                    , 'SequenceOrder' => $this->bsf->isNullCheck($postParams['SequenceOrder'],'string')
                    , 'CreatedDate' => date('Y-m-d H:i:s')
                    , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $carParkId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    // get Block title for sequence
                    $select = $sql->select();
                    $select->from('KF_BlockMaster')
                        ->where(array('BlockId' => $blockId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $block = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // get phase title for sequence
                    $select = $sql->select();
                    $select->from('KF_PhaseMaster')
                        ->where(array('PhaseId' => $block['PhaseId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $phase = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($blockTitle == '') {
                        $blockTitle = $block['Title'];
                    }
                    if($phaseTitle == '') {
                        $phaseTitle = $phase['Title'];
                    }

                    $sequence = $postParams['Sequence'];
                    $seqStartingNo = $postParams['SequenceStartingNo'];
                    $seqWidth = $postParams['SequenceWidth'];
                    $arrSeqSkipNos = explode(',', strtoupper($postParams['SequenceSkipNos']));
                    $arrSeqOrder = explode('_', $postParams['SequenceOrder']);

                    // generate car park sequence number
                    $noOfCarParks = $this->bsf->isNullCheck($postParams['totalCarParks'], 'number');
                    $arrSlotNos = array();
                    $k=1;
                    $startPos = 1;
                    if($seqStartingNo != '') {
                        if ( $sequence == 'alpha' ) {
                            $startPos = ord( strtoupper( $seqStartingNo ) ) - 65;
                        } else {
                            $startPos = $seqStartingNo;
                        }
                    }
                    for($j=0;$j<=$noOfCarParks;) {
                        $seqValue = $startPos;
                        if($sequence == 'alpha') {
                            $seqValue = $this->getNameFromNumber(($startPos - 1));
                        }

                        if(in_array($seqValue, $arrSeqSkipNos)) {
                            $startPos++;
                            continue;
                        }

                        if($sequence == 'numeric') {
                            // width
                            $sumOfDigit = strlen($seqValue);
                            if($sumOfDigit < $seqWidth) {
                                for($l=$sumOfDigit; $l< $seqWidth; $l++) {
                                    $seqValue = '0'.$seqValue;
                                }
                            }
                        }

                        $seqOrder = '';
                        foreach($arrSeqOrder as $order) {
                            switch($order) {
                                case 'phase':
                                    $seqOrder .= $phaseTitle;
                                    break;
                                case 'block':
                                    $seqOrder .= $blockTitle;
                                    break;
                                case 'carpark':
                                    $seqOrder .= $seqValue;
                                    break;
                            }
                        }

                        $arrSlotNos[$k] = $seqOrder;
                        $startPos++;
                        $k++;
                        $j++;
                    }

                    for($i=1;$i<=$postParams['totalCarParks'];$i++) {
                        $insert = $sql->insert();
                        $insert->into('Crm_CarParkTrans');
                        $insert->Values(array('CarParkId' => $this->bsf->isNullCheck($carParkId,'number')
                        , 'UnitId' => $this->bsf->isNullCheck('0','number')
                        , 'SlotNo' => $arrSlotNos[$i]
                        , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();

                    $this->_view->isPost = 1;
                } catch(PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
            $this->_view->projectId = $projectId;
            $select = $sql->select();
            $select->from(array("a"=>'Crm_CarParkTrans'))
                ->columns(array('SlotNo','CarParkTransId'))
                ->join(array("b"=>"Crm_CarParkMaster"),"a.CarParkId=b.CarParkId", array('ProjectId','BlockId','TypeId','AllottedSlots','TotalCarParks'), $select::JOIN_LEFT)
                ->join(array("c"=>"KF_BlockMaster"), "b.BlockId=c.BlockId", array('PhaseId','BlockName'), $select::JOIN_LEFT)
                ->join(array("d"=>"Crm_CarParkTypeMaster"), "b.TypeId=d.TypeId", array('TypeName'), $select::JOIN_LEFT)
                ->join(array("e"=>"KF_PhaseMaster"),"c.PhaseId=e.PhaseId", array('PhaseName'), $select::JOIN_LEFT)
                ->join(array("f"=>"KF_UnitMaster"),"a.UnitId=f.UnitId", array('UnitNo','UnitId'), $select::JOIN_LEFT)
                ->where(array('b.IsOther' => 0,'a.DeleteFlag'=>0))
                ->where(array("b.ProjectId"=>$projectId))
                ->order("b.BlockId ASC");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultsReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    private function getNameFromNumber($num)
    {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }

    public function otherFacilityAction()
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

        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;




        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsLeadData = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $select->where(array("ProjectId"=>$this->_view->projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->responseLead = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from('Crm_CarParkTypeMaster')
            ->columns(array('TypeId','TypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsCarParkType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a"=>'KF_BlockMaster'))
            ->columns(array('BlockId','BlockName'))
            ->join(array("b"=>"KF_PhaseMaster"),"a.PhaseId=b.PhaseId", array('PhaseId','PhaseName'), $select::JOIN_LEFT)
            ->where(array("a.ProjectId"=>$this->_view->projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsBlock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->isPost = 0;

        $request = $this->getRequest();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                $postParams = $request->getPost();
                //Write your Ajax post code here
                $select = $sql->select();
                $select->from(array("a"=>'Crm_CarParkTrans'))
                    ->columns(array('SlotNo','CarParkTransId'))
                    ->join(array("b"=>"Crm_CarParkMaster"),"a.CarParkId=b.CarParkId", array('ProjectId','FacilityName','BlockId','TypeId','AllottedSlots','TotalCarParks'), $select::JOIN_LEFT)
                    ->join(array("c"=>"KF_BlockMaster"), "b.BlockId=c.BlockId", array('PhaseId','BlockName'), $select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_CarParkTypeMaster"), "b.TypeId=d.TypeId", array('TypeName'), $select::JOIN_LEFT)
                    ->join(array("e"=>"KF_PhaseMaster"),"c.PhaseId=e.PhaseId", array('PhaseName'), $select::JOIN_LEFT)
                    ->where(array('b.IsOther' => 1,'a.DeleteFlag'=>0))
                    ->where(array("b.ProjectId"=>$postParams['proj']))
                    ->order("b.BlockId ASC");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
            }
        } else {
            if ($request->isPost()) {
                //Write your Normal form post code here
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    $postParams = $request->getPost();
                    //echo '<pre>'; print_r($postParams); die;

                    $bkPhId = explode('-',$postParams['blockId']);
                    $blockId = $this->bsf->isNullCheck($bkPhId[0],'number');
                    $blockName = $bkPhId[1];
                    $phaseTitle = $this->bsf->isNullCheck($postParams['phaseTitle'],'string');
                    $blockTitle = $this->bsf->isNullCheck($postParams['blockTitle'],'string');

                    $insert = $sql->insert();
                    $insert->into('Crm_CarParkMaster');
                    $insert->Values(array('ProjectId' => $this->bsf->isNullCheck($this->bsf->decode($postParams['projectId']),'number')
                    , 'BlockId' => $blockId
                    , 'TypeId' => $this->bsf->isNullCheck('0','number')
                    , 'AllottedSlots' => $this->bsf->isNullCheck('0','number')
                    , 'TotalCarParks' => $this->bsf->isNullCheck($postParams['totalCarParks'],'number')
                    , 'FacilityName' => $this->bsf->isNullCheck($postParams['facilityName'],'string')
                    , 'IsOther' => $this->bsf->isNullCheck('1','number')
                    , 'SequenceOrder' => $this->bsf->isNullCheck($postParams['SequenceOrder'],'string')
                    , 'CreatedDate' => date('Y-m-d H:i:s')
                    , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $carParkId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    // get Block title for sequence
                    $select = $sql->select();
                    $select->from('KF_BlockMaster')
                        ->where(array('BlockId' => $blockId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $block = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // get phase title for sequence
                    $select = $sql->select();
                    $select->from('KF_PhaseMaster')
                        ->where(array('PhaseId' => $block['PhaseId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $phase = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($blockTitle == '') {
                        $blockTitle = $block['Title'];
                    }
                    if($phaseTitle == '') {
                        $phaseTitle = $phase['Title'];
                    }

                    $sequence = $postParams['Sequence'];
                    $seqStartingNo = $postParams['SequenceStartingNo'];
                    $seqWidth = $postParams['SequenceWidth'];
                    $arrSeqSkipNos = explode(',', strtoupper($postParams['SequenceSkipNos']));
                    $arrSeqOrder = explode('_', $postParams['SequenceOrder']);

                    // generate car park sequence number
                    $noOfCarParks = $this->bsf->isNullCheck($postParams['totalCarParks'], 'number');
                    $arrSlotNos = array();
                    $k=1;
                    $startPos = 1;
                    if($seqStartingNo != '') {
                        if ( $sequence == 'alpha' ) {
                            $startPos = ord( strtoupper( $seqStartingNo ) ) - 65;
                        } else {
                            $startPos = $seqStartingNo;
                        }
                    }
                    for($j=0;$j<=$noOfCarParks;) {
                        $seqValue = $startPos;
                        if($sequence == 'alpha') {
                            $seqValue = $this->getNameFromNumber(($startPos - 1));
                        }

                        if(in_array($seqValue, $arrSeqSkipNos)) {
                            $startPos++;
                            continue;
                        }

                        if($sequence == 'numeric') {
                            // width
                            $sumOfDigit = strlen($seqValue);
                            if($sumOfDigit < $seqWidth) {
                                for($l=$sumOfDigit; $l< $seqWidth; $l++) {
                                    $seqValue = '0'.$seqValue;
                                }
                            }
                        }

                        $seqOrder = '';
                        foreach($arrSeqOrder as $order) {
                            switch($order) {
                                case 'phase':
                                    $seqOrder .= $phaseTitle;
                                    break;
                                case 'block':
                                    $seqOrder .= $blockTitle;
                                    break;
                                case 'carpark':
                                    $seqOrder .= $seqValue;
                                    break;
                            }
                        }

                        $arrSlotNos[$k] = $seqOrder;
                        $startPos++;
                        $k++;
                        $j++;
                    }

                    for($i=1;$i<=$postParams['totalCarParks'];$i++) {
                        $insert = $sql->insert();
                        $insert->into('Crm_CarParkTrans');
                        $insert->Values(array('CarParkId' => $this->bsf->isNullCheck($carParkId,'number')
                        , 'UnitId' => $this->bsf->isNullCheck('0','number')
                        , 'SlotNo' => $arrSlotNos[$i]
                        , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();

                    $this->_view->isPost = 1;
                } catch(PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            $select = $sql->select();
            $select->from(array("a"=>'Crm_CarParkTrans'))
                ->columns(array('SlotNo','CarParkTransId'))
                ->join(array("b"=>"Crm_CarParkMaster"),"a.CarParkId=b.CarParkId", array('ProjectId','BlockId','TypeId','AllottedSlots','TotalCarParks','FacilityName'), $select::JOIN_LEFT)
                ->join(array("c"=>"KF_BlockMaster"), "b.BlockId=c.BlockId", array('PhaseId','BlockName'), $select::JOIN_LEFT)
                //->join(array("d"=>"Crm_CarParkTypeMaster"), "b.TypeId=d.TypeId", array('TypeName'), $select::JOIN_LEFT)
                ->join(array("e"=>"KF_PhaseMaster"),"c.PhaseId=e.PhaseId", array('PhaseName'), $select::JOIN_LEFT)
                ->join(array("f"=>"KF_UnitMaster"),"a.UnitId=f.UnitId", array('UnitNo','UnitId'), $select::JOIN_LEFT)
                ->where(array('b.IsOther' => 1))
                ->where(array("b.ProjectId"=>$this->_view->projectId))
                ->order("b.BlockId ASC");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->resultsReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function checkFacilityNameAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $bkPhId = explode('-',$postParams['blockId']);
                $blockId = $this->bsf->isNullCheck($bkPhId[0],'number');

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Crm_CarParkMaster')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where(array('FacilityName' => $postParams['facName'], 'BlockId' => $blockId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function getBookedUnitAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $projId = $postParams['projId'];
                $blkId = $postParams['blkId'];

                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from(array("a"=>'KF_UnitMaster'))
                    ->columns(array('UnitId','UnitNo'))
//                    ->join(array("b"=>"KF_UnitMaster"),"a.UnitId=b.UnitId", array('UnitNo'), $select::JOIN_LEFT)
                    ->where(array('a.ProjectId' => $projId, 'a.BlockId' => $blkId))
                    ->where(array('a.DeleteFlag' => 0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function allotParkAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $unitId = $postParams['unitId'];
                $carTransId = $postParams['carTransId'];
                $slotnum = $postParams['slotNum'];

                if($postParams['mode']=='delete') {
                    $update = $sql->update();
                    $update->table('Crm_CarParkTrans')
                        ->set(array('UnitId' => 0 ))
                        ->where(array('SlotNo' => $slotnum));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $status = 'success';
                } else if($postParams['mode']=='slot') {
                    $update = $sql->update();
                    $update->table('Crm_CarParkTrans')
                        ->set(array('DeleteFlag' =>1))
                        ->where(array('SlotNo' => $slotnum));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $status = 'success';
                } else {
                    $update = $sql->update();
                    $update->table('Crm_CarParkTrans')
                        ->set(array('UnitId' => $unitId))
                        ->where(array('CarParkTransId' => $carTransId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $status = 'success';
                }
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($status));
            return $response;
        }
    }

    public function unitDetailsAction(){
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

        $unitId = $this->params()->fromRoute('unitId');

        $select = $sql->select();
        $select->from('Crm_WingMaster')
            ->columns(array('WingId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsWing = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_FacingMaster')
            ->columns(array('FacingId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsFace = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('WF_Users')
            ->columns(array('ExecutiveId' => 'UserId','ExecutiveName' => 'EmployeeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_StatusMaster')
            ->columns(array('StatusId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsStatus  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_UnitTypeMaster')
            ->columns(array('UnitTypeId','UnitTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsUnitType = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_BlockMaster')
            ->columns(array('BlockId','BlockName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsBlock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_UnitTypeMaster')
            ->columns(array('UnitTypeId','UnitTypeName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsFlat = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_PaymentScheduleUnit')
            ->columns(array('TemplateId','Description'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsPayment = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_ExtraItemMaster')
            ->columns(array('ExtraItemId','ItemDescription'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsExtraItem = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_CheckListMaster')
            ->columns(array('CheckListId','CheckListName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultsCheck = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $request = $this->getRequest();

        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        }
        else {
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
                //Unit Details
                // $Wing = $postParams['Wing'];

                // $FlatId=$postParams['BlockId'];

                //Unit Particulars
                // $BlockId=$postParams['BlockId'];
                $unitNo = $postParams['UnitNo'];
                //$unitTypeId = $postParams['UnitTypeId'];
                $blockName = $postParams['BlockName'];
                $floorName = $postParams['FloorName'];
                $buyerName = $postParams['BuyerName'];
                $executiveId = $postParams['ExecutiveId'];
                $statusId = $postParams['StatusId'];

                //unit Area Details
                $area = $postParams['Area'];
                $carpetArea = $postParams['CarpetArea'];
                $udsLandArea = $postParams['UdsLandArea'];
                $facingId = $postParams['FacingId'];

                //Unit Cost Details
                $rate = $postParams['Rate'];
                $guideLinevalue = $postParams['GuideLinevalue'];
                $marketLandValue = $postParams['MarketLandValue'];
                $landAmount = $postParams['LandAmount'];
                $constructionAmount = $postParams['ConstructionAmount'];
                $netAmt = $postParams['NetAmt'];
                $grossAmount = $postParams['GrossAmount'];

                //payable Amount Details
                $baseAmt = $postParams['BaseAmt'];
                $qualifierAmount = $postParams['QualifierAmount'];
                $advAmount = $postParams['AdvAmount'];
                $advPercent = $postParams['AdvPercent'];
                $lateInterest = $postParams['LateInterest'];
                $paymentSchId = $postParams['PaymentSchId'];
                $creditDays = $postParams['CreditDays'];
                $landDiscount = $postParams['LandDiscount'];
                $statusId = $postParams['StatusId'];
                $constructionDiscount = $postParams['ConstructionDiscount'];

                $insert = $sql->insert('Crm_UnitDetails');
                $newData = array(
                    'UnitNo' => $unitNo,
                    'UnitId' => $unitId,
                    'Area' => $area,
                    'Rate' => $rate,
                    'BaseAmt' => $baseAmt,
                    'AdvPercent' => $advPercent,
                    'AdvAmount' => $advAmount,
                    'GuideLinevalue' => $guideLinevalue,
                    'ConstructionAmount' => $constructionAmount,
                    'CreditDays' => $creditDays,
                    'NetAmt' => $netAmt,
                    'FacingId' => $facingId,
                    'LandAmount' => $landAmount,
                    'GrossAmount' => $grossAmount,
                    //'BlockName' => $blockName,
                    'FloorName' => $floorName,
                    'BuyerName' => $buyerName,
                    'ExecutiveId' => $executiveId,
                    'MarketLandValue' => $marketLandValue,
                    //'LateInterestPercent' => $lateInterest,
                    'StatusId' => $statusId,
                    'CarpetArea' => $carpetArea,
                );
                $insert->values($newData);
                $statement = $sql->getSqlStringForSqlObject($insert);
                $results=$dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            }
            //	$this->redirect()->toRoute('crm/car-park', array('controller' => 'project', 'action' => 'car-park', 'unitId' => $unitId));
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }




    public function utgAction(){
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
        $unitTypeId = $this->params()->fromRoute('unitTypeId');
        $projectId = $this->params()->fromRoute('projectId');
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                /*CheckList insert*/
                if($postParams['mode'] == 'CheckList'){
                    $checkListInsert = $sql->insert("Crm_CheckListMaster");
                    $checkListInsert->values(array("CheckListName"=>$postParams['CheckList']));
                    $insertStmt = $sql->getSqlStringForSqlObject($checkListInsert);
                    $result['check'] = $dbAdapter->query($insertStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                /*extraItem insert*/
                else if($postParams['mode'] == 'extraItem'){
                    $extraItemInsert = $sql->insert("Crm_ExtraItemMaster");
                    $extraItemInsert->values(array("ItemDescription"=>$postParams['extraItem']));
                    $insertStmt = $sql->getSqlStringForSqlObject($extraItemInsert);
                    $result['extra'] = $dbAdapter->query($insertStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                /*featureItem insert*/
                else if($postParams['mode'] == 'featureItem'){
                    $extraItemInsert = $sql->insert("Crm_FeatureListMaster");
                    $extraItemInsert->values(array("FeatureDesc"=>$postParams['featureItem']));
                    $insertStmt = $sql->getSqlStringForSqlObject($extraItemInsert);
                    $result['feature'] = $dbAdapter->query($insertStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                /*CheckList selected view*/

                // $this->_view->resp

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode($result));
                return $response;
                // return $this->_view;
            }
        } else {
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postParams = $request->getPost();
                /*Submit data*/
                //echo json_encode($postParams);die;
                $lid = array_filter(explode(",", $postParams['hidlocId']));
                $locDelete = $sql->delete();
                $locDelete->from('Crm_UnitTypeChecklist')
                    ->where(array('UnitTypeId'=>$projectId));
                $deleteLocStmt = $sql->getSqlStringForSqlObject($locDelete);
                $dbAdapter->query($deleteLocStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($lid as $sid){
                    $ckInsert = $sql->insert("Crm_UnitTypeChecklist");
                    $ckInsert->values(array("UnitTypeId"=>$projectId,"CheckListName"=>$postParams['CheckListName_'.$sid],"ExpCompletionDate"=>date('m-d-Y',strtotime($postParams['ExpCompletionDate_'.$sid])),"CompletionDate"=>date('m-d-Y',strtotime($postParams['CompletionDate_'.$sid])),"RefNo"=>$postParams['RefNo_'.$sid],"CheckListId"=>$sid));
                    $insertLocStmt = $sql->getSqlStringForSqlObject($ckInsert);
                    $dbAdapter->query($insertLocStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                /***********extra Items**************/
                $extId = array_filter(explode(",", $postParams['hidextraId']));
                $extDelete = $sql->delete();
                $extDelete->from('Crm_UnitTypeExtraItem')
                    ->where(array('UnitTypeId'=>$projectId));
                $deleteExtStmt = $sql->getSqlStringForSqlObject($extDelete);
                $dbAdapter->query($deleteExtStmt, $dbAdapter::QUERY_MODE_EXECUTE);
                foreach($extId as $sid){
                    $ckInsert = $sql->insert("Crm_UnitTypeExtraItem");
                    $ckInsert->values(array("UnitTypeId"=>$projectId,"ItemDescription"=>$postParams['ItemDescription_'.$sid],"ExtraRate"=>$postParams['ExtraRate_'.$sid],"ExtraItemId"=>$sid,"Qty"=>$postParams['Qty_'.$sid],"Amount"=>$postParams['Amount_'.$sid],"NetAmount"=>$postParams['NetAmount_'.$sid]));
                    $insertLocStmt = $sql->getSqlStringForSqlObject($ckInsert);
                    $dbAdapter->query($insertLocStmt, $dbAdapter::QUERY_MODE_EXECUTE);

                }
                $this->redirect()->toRoute('crm/car-park', array('controller' => 'project', 'action' => 'car-park', 'UnitId' => $unitTypeId));
            }
            /*extra Item default selected view*/

            //$projectId = 0;
            $locSelect1 = $sql->select();
            $locSelect1->from(array("a"=>"Crm_UnitTypeChecklist"))
                ->columns(array("CheckListId", "Sel"=>new Expression("'Y'")), array("CheckListId"))
                ->join(array("b"=>"Crm_CheckListMaster"), "a.CheckListId=b.CheckListId", array("CheckListId", "CheckListName"), $locSelect1::JOIN_INNER)
                ->where(array('a.UnitTypeId'=>$projectId));

            $Subselect= $sql->select();
            $Subselect->from("Crm_UnitTypeChecklist")
                ->columns(array("CheckListId"))
                ->where(array('UnitTypeId'=>$projectId));

            $checkSelect2 = $sql->select();
            $checkSelect2->from(array("a"=>'Crm_CheckListMaster'))
                ->columns(array("CheckListId", "Sel"=>new Expression("'N'"), "CheckListId", "CheckListName"))
                ->where->notIn('a.CheckListId',$Subselect);

            $checkSelect2->combine($locSelect1,'Union ALL');

            $checkStatement= $sql->getSqlStringForSqlObject($checkSelect2);
            $checkResult = $dbAdapter->query($checkStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $checkSelected= $sql->select();
            $checkSelected->from("Crm_UnitTypeChecklist")
                ->columns(array("CheckListName","CheckListId",'ExpCompletionDate'=>new Expression("CONVERT(varchar(10),ExpCompletionDate,105)"),'CompletionDate'=>new Expression("CONVERT(varchar(10),CompletionDate,105)"), "RefNo"))
                ->where(array("UnitTypeId"=>$projectId));
            $checkSelectStmt= $sql->getSqlStringForSqlObject($checkSelected);
            $checkSelectResult = $dbAdapter->query($checkSelectStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            /***extra items ********/
            $extSelect1 = $sql->select();
            $extSelect1->from(array("a"=>"Crm_UnitTypeExtraItem"))
                ->columns(array("ExtraItemId", "Sel"=>new Expression("'Y'")), array("ExtraItemId"))
                ->join(array("b"=>"Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array("ExtraItemId", "ItemDescription"), $locSelect1::JOIN_INNER)
                ->where(array('a.UnitTypeId'=>$projectId));

            $Subselect= $sql->select();
            $Subselect->from("Crm_UnitTypeExtraItem")
                ->columns(array("ExtraItemId"))
                ->where(array('UnitTypeId'=>$projectId));

            $extraSelect2 = $sql->select();
            $extraSelect2->from(array("a"=>'Crm_ExtraItemMaster'))
                ->columns(array("ExtraItemId", "Sel"=>new Expression("'N'"), "ExtraItemId", "ItemDescription"))
                ->where->notIn('a.ExtraItemId',$Subselect);

            $extraSelect2->combine($extSelect1,'Union ALL');

            $extraStatement= $sql->getSqlStringForSqlObject($extraSelect2);
            $extraResult = $dbAdapter->query($extraStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            /******************/

            $extSelected= $sql->select();
            $extSelected->from("Crm_UnitTypeExtraItem")
                ->columns(array("ExtraRate","Qty","Amount","NetAmount","ItemDescription","ExtraItemId"))
                ->where(array("UnitTypeId"=>$projectId));
            $extSelectStmt= $sql->getSqlStringForSqlObject($extSelected);
            $extraSelectResult1 = $dbAdapter->query($extSelectStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            /***feature items ********/
            $extSelect1 = $sql->select();
            $extSelect1->from(array("a"=>"Crm_UnitTypeFeatureItem"))
                ->columns(array("FeatureId", "Sel"=>new Expression("'Y'")), array("FeatureId"))
                ->join(array("b"=>"Crm_FeatureListMaster"), "a.FeatureId=b.FeatureId", array("FeatureId", "FeatureDesc"), $locSelect1::JOIN_INNER)
                ->where(array('a.UnitTypeId'=>$projectId));

            $Subselect= $sql->select();
            $Subselect->from("Crm_UnitTypeFeatureItem")
                ->columns(array("FeatureId"))
                ->where(array('UnitTypeId'=>$projectId));

            $extraSelect2 = $sql->select();
            $extraSelect2->from(array("a"=>'Crm_FeatureListMaster'))
                ->columns(array("FeatureId", "Sel"=>new Expression("'N'"), "FeatureId", "FeatureDesc"))
                ->where->notIn('a.FeatureId',$Subselect);

            $extraSelect2->combine($extSelect1,'Union ALL');

            $extraStatement= $sql->getSqlStringForSqlObject($extraSelect2);
            $featureResult = $dbAdapter->query($extraStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            $this->_view->unitTypeId = $unitTypeId;
            $this->_view->checkResult = $checkResult;
            $this->_view->extraResult = $extraResult;
            $this->_view->featureResult = $featureResult;
            $this->_view->responseCheck = $checkSelectResult;
            $this->_view->responseExtra = $extraSelectResult1;

            // return $this->_view;
        }

        return $this->_view;
    }

    public function unitGridAction() {
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
                $postParams = $request->getPost();
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here
                    $blockId = json_decode($postParams['bId']);
                    $uDetails = $this->bsf->isNullCheck($postParams['uValue'], 'string');
                    $uStatus = $this->bsf->isNullCheck($postParams['uStatus'], 'string');
                    $ProjectId = $this->bsf->isNullCheck($postParams['pId'], 'number');

                    $select = $sql->select();
                    $select->from(array('a'=>'KF_BlockMaster'))
                        ->columns(array('BlockId', 'BlockName'))
                        ->join(array('b' => 'KF_PhaseMaster'), 'b.PhaseId=a.PhaseId', array('PhaseName'), $select::JOIN_LEFT)
                        ->where(array('a.ProjectId' => $ProjectId, 'a.DeleteFlag' => 0 ))
                        ->order("a.BlockId ASC");
                    if(count($blockId)>0) {
                        $select->where(array('BlockId' => $blockId));
                    }
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $blockArray = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $blockAllDetails="";
                    $isFirst = TRUE;

                    if(!empty($blockArray)) {
                        foreach($blockArray as &$block) {
                            $select = $sql->select();
                            $select->from(array('a' => 'KF_FloorMaster'))
                                ->columns(array('FloorId', 'FloorName'))
                                ->where(array('a.BlockId' => $block['BlockId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $arrFloors= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $block['arrFloors']= $arrFloors;
                        }
                    }

                    if(!empty($blockArray)) {
                        // get units inside block
                        foreach($blockArray as &$block) {
                            if ($isFirst==TRUE) {
                                $newClass = "accordion_head_crnt";
                                $newClassData = "in";
                            } else {
                                $newClass = "";
                                $newClassData = "";
                            }
                            $blockAllDetails .= "<div class='panel panel-default'>
                                <div class='panel-heading accordion_head ".$newClass."' role='tab' data-toggle='collapse' data-parent='#accordion' href='#block-collapse-" . $block['BlockId'] . "' aria-expanded='true' aria-controls='collapseOne'>
                                    <h4>".$block['PhaseName'].' - '.$block['BlockName']."</h4>
                                </div>
                                <div id='block-collapse-" . $block['BlockId'] . "' class='panel-collapse collapse " . $newClassData . "' role='tabpanel' style='height:auto !important; overflow: hidden;'>
                                    <div class='panel-body'>";
                            $isFirst = FALSE;
                            $blockAllDetails .= "<div class='row'>
                                <div class='col-lg-12 clear'>";
                            foreach ($block['arrFloors'] as &$floor) {
                                $blockAllDetails .= "<div class='floor_name_area'>
                                                            <h4>".$floor['FloorName']."</h4>
                                                            </div>
                                                            <div class='col-lg-12 clear'>";

                                $select = $sql->select();
                                $select->from(array('a' => 'KF_UnitMaster'))
                                    ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status, Case When d.BookingId > 0 then d.NetAmount  else  b.NetAmt   End as NetAmt"),'rank' => new expression("RANK() OVER (PARTITION BY a.UnitId ORDER BY e.CreatedDate desc)")))
                                    ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId', array('StatusId', 'Rate'), $select::JOIN_LEFT)
                                    ->join(array('g' => 'Crm_ProjectDetail'), 'g.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Proj_UOM'), 'g.AreaUnit=c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                                    ->join(array('d' => 'Crm_UnitBooking'),  new Expression("a.UnitId=d.UnitId and d.DeleteFlag=0"), array(), $select::JOIN_LEFT)
                                    ->join(array('e' => 'Crm_PostSaleDiscountRegister'), 'e.BookingId =d.BookingId', array("postNet"=>"NetAmount",'PostSaleDiscountId'), $select::JOIN_LEFT)
                                    ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array("BlockNet"=>"NetAmount",'BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                                    ->join(array('i' => 'Crm_UnitPreBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array("PreNet"=>"NetAmount",'PreStatus' => new Expression("CAST ( CASE WHEN i.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                                    ->join(array('h' => 'Crm_Leads'), 'h.LeadId=d.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
                                    ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
                                    ->join(array('l' => 'Crm_Leads'), 'l.LeadId=i.LeadId', array('PreName' => 'LeadName'), $select::JOIN_LEFT)
                                    ->where(array('a.ProjectId' => $ProjectId, 'a.FloorId' => $floor['FloorId'], 'a.DeleteFlag' => 0));
                                if($uStatus != "") {
                                    $select->where("(a.Status='$uStatus')");
                                }
                                if ($uDetails != "") {
                                    $select->where("(a.UnitNo LIKE '%" . $uDetails . "%' OR a.UnitArea LIKE '%" . $uDetails . "%' OR h.LeadName LIKE '%" . $uDetails . "%' OR k.LeadName LIKE '%" . $uDetails . "%')");
                                }
                                $select1 = $sql->select();
                                $select1->from(array("g"=>$select))
                                    ->where(array('g.rank'=>1));
                                $stmt = $sql->getSqlStringForSqlObject($select1);
                                $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if(count($arrUnits)>0) {
                                    foreach ($arrUnits as &$unit) {

                                        $select = $sql->select();
                                        $select->from(array('a' => 'KF_UnitMaster'))
                                            ->columns(array(new Expression("a.UnitId,a.UnitTypeId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
                                            ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                                            ->where(array("a.UnitId"=>$unit['UnitId']));
                                        $stmt = $sql->getSqlStringForSqlObject($select);
                                        $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                                        $select = $sql->select();
                                        $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
                                            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_INNER)
                                            ->columns(array('OtherCostId','Amount', 'Area', 'Rate'))
                                            ->where(array('a.UnitTypeId' => $unitIn['UnitTypeId']));
                                        $stmt = $sql->getSqlStringForSqlObject($select);
                                        $unitamt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                        //taxcalculation//
                                        $dGrossAmt = $unitIn['GrossAmount'];
                                        $dDate = date('Y-m-d');
                                        $arrReceipt=array();
                                        $arrReceipt[]=array("ReceiptType"=>'S',"TypeId"=>1,"Amount"=>$unitIn['LandAmount']);
                                        $arrReceipt[]=array("ReceiptType"=>'S',"TypeId"=>2,"Amount"=>$unitIn['ConstructionAmount']);


                                        if(isset($unitamt)) {
                                            foreach($unitamt as $uAmt) {
                                                $arrReceipt[]=array("ReceiptType"=>'O',"TypeId"=>$uAmt['OtherCostId'],"Amount"=>$uAmt['Amount']);
                                            }
                                        }

                                        $select = $sql->select();
                                        $select->from(array('a' => 'Proj_QualifierTrans'))
                                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
                                            ->where(array('a.QualType'=>'C'))
                                            ->columns(array('QualifierId'));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                        $arrTotTax = array();
                                        foreach ($arrList as $atax) {
                                            $arrTotTax[$atax['QualifierId']]['QualifierId'] = $atax['QualifierId'];
                                            $arrTotTax[$atax['QualifierId']]['QualifierName'] = $atax['QualifierName'];
                                            $arrTotTax[$atax['QualifierId']]['Amount'] = 0;
                                        }

                                        if (!empty($arrReceipt)) {
                                            foreach ($arrReceipt as $v) {

                                                $sReceiptType = $this->bsf->isNullCheck($v['ReceiptType'], 'string');
                                                $iTypeId = $this->bsf->isNullCheck($v['TypeId'], 'number');
                                                $dBaseAmt = floatval($this->bsf->isNullCheck($v['Amount'], 'number'));

                                                $select = $sql->select();
                                                $select->from(array('c' => 'Crm_QualifierSettings'))
                                                    ->join(array("a" => "Proj_QualifierTrans"), 'c.QualifierId=a.QualifierId',
                                                        array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(a.QualifierId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                                            'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select::JOIN_LEFT)
                                                    ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER);
                                                $select->where(array('QualSetType' => $sReceiptType, 'QualSetTypeId' => $iTypeId, 'a.QualType' => 'C'))
                                                    ->order('SortOrder ASC');
                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                $arrQualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                $qualTotAmt = 0;

                                                $arrTax = array();
                                                foreach ($arrQualList as $qualList) {
                                                    $sRefNo = $qualList['RefId'];
                                                    $arrTax[$sRefNo]['QualifierName'] = $qualList['QualifierName'];
                                                    $arrTax[$sRefNo]['QualifierId'] = $qualList['QualifierId'];
                                                    $arrTax[$sRefNo]['RefNo'] = $qualList['RefId'];
                                                    $arrTax[$sRefNo]['QualifierTypeId'] = $qualList['QualifierTypeId'];
                                                    //$arrTax[$sRefNo]['Expression'] = $qualList['Expression'];
                                                    $arrTax[$sRefNo]['ExpPer'] = $qualList['ExpPer'];


                                                    $sExpression = $qualList['Expression'];
                                                    $sExpression = str_replace('R0', $dBaseAmt, $sExpression);
                                                    $arrTax[$sRefNo]['Expression'] = $sExpression;

                                                    $arrTax[$sRefNo]['TaxablePer'] = 0;
                                                    $arrTax[$sRefNo]['TaxPer'] = 0;
                                                    $arrTax[$sRefNo]['SurCharge'] = 0;
                                                    $arrTax[$sRefNo]['EDCess'] = 0;
                                                    $arrTax[$sRefNo]['HEDCess'] = 0;
                                                    $arrTax[$sRefNo]['SBCess'] = 0;
                                                    $arrTax[$sRefNo]['KKCess'] = 0;
                                                    $arrTax[$sRefNo]['NetPer'] = 0;

                                                    if ($qualList['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {
                                                        $tds = CommonHelper::getTDSSetting(11, $dDate, $dbAdapter);
                                                        $arrTax[$sRefNo]['TaxablePer'] = $tds["TaxablePer"];
                                                        $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                                        $arrTax[$sRefNo]['SurCharge'] = $tds["SurCharge"];
                                                        $arrTax[$sRefNo]['EDCess'] = $tds["EDCess"];
                                                        $arrTax[$sRefNo]['HEDCess'] = $tds["HEDCess"];
                                                        $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];

                                                    } else if ($qualList['QualifierTypeId'] == 2) {
                                                        $select = $sql->select();
                                                        if ($sReceiptType == "S") {
                                                            $select->from('Crm_ReceiptTypeMaster')
                                                                ->columns(array('TaxablePer'))
                                                                ->where(array('ReceiptTypeId' => $iTypeId));

                                                        } else {
                                                            $select->from('Crm_OtherCostMaster')
                                                                ->columns(array('TaxablePer'))
                                                                ->where(array('OtherCostId' => $iTypeId));
                                                        }

                                                        $stmt = $sql->getSqlStringForSqlObject($select);
                                                        $stTax = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                                        $Taxable = 0;
                                                        if (!empty($stTax)) $Taxable = $stTax['TaxablePer'];

                                                        $tds = CommonHelper::getSTSetting('F', $dDate, $dbAdapter);
                                                        $arrTax[$sRefNo]['TaxablePer'] = $Taxable;
                                                        $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                                        $arrTax[$sRefNo]['SBCess'] = $tds["SBCess"];
                                                        $arrTax[$sRefNo]['KKCess'] = $tds["KKCess"];
                                                        $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];
                                                    }
                                                }

                                                foreach ($arrTax as $qual) {

                                                    $sRef = $qual['RefNo'];
                                                    $sExpression = $qual['Expression'];
                                                    $dAmt = eval('return ' . $sExpression . ';');

                                                    $dNetAmt = 0;

                                                    if ($qual['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {

                                                        $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                                        $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                                        $dSurCharge = floatval($this->bsf->isNullCheck($qual['SurCharge'], 'number'));
                                                        $dEDCess = floatval($this->bsf->isNullCheck($qual['EDCess'], 'number'));
                                                        $dHEDCess = floatval($this->bsf->isNullCheck($qual['HEDCess'], 'number'));

                                                        $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                                        $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                                        $dCessAmt = $dTaxAmt * ($dSurCharge / 100);
                                                        $dEDAmt = $dTaxAmt * ($dEDCess / 100);
                                                        $dHEDAmt = $dTaxAmt * ($dHEDCess / 100);
                                                        $dNetAmt = $dTaxAmt + $dCessAmt + $dEDAmt + $dHEDAmt;

                                                    } else if ($qual['QualifierTypeId'] == 2) {

                                                        $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                                        $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                                        $dSBCess = floatval($this->bsf->isNullCheck($qual['SBCess'], 'number'));
                                                        $dKKCess = floatval($this->bsf->isNullCheck($qual['KKCess'], 'number'));

                                                        $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                                        $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                                        $dKKCessAmt = $dTaxableAmt * ($dKKCess / 100);
                                                        $dSBCessAmt = $dTaxableAmt * ($dSBCess / 100);
                                                        $dNetAmt = $dTaxAmt + $dKKCessAmt + $dSBCessAmt;

                                                    } else {
                                                        $dPer = floatval($this->bsf->isNullCheck($qual['ExpPer'], 'number'));
                                                        if ($qual['QualifierTypeId'] !=1) {
                                                            if ($dPer != 0) $dNetAmt = $dAmt * ($dPer / 100);
                                                            else $dNetAmt = $dAmt;
                                                        }
                                                    }

                                                    $arrTax[$sRef]['NetAmt'] = $dNetAmt;
                                                    foreach ($arrTax as $qualData) {
                                                        $sExpression = $qualData['Expression'];
                                                        $ssRef = $qualData['RefNo'];

                                                        $sExpression = Str_replace($sRef, $dNetAmt, $sExpression);
                                                        $arrTax[$sRef]['Expression'] = $sExpression;
                                                    }
                                                }

                                                if (!empty($arrTax)) {

                                                    foreach ($arrTax as $qual) {
                                                        $qualId = $qual['QualifierId'];
                                                        $doamt =  floatval($this->bsf->isNullCheck($arrTotTax[$qualId]['Amount'],'number'));
                                                        $dtamt =  floatval($this->bsf->isNullCheck($qual['NetAmt'],'number'));

                                                        $arrTotTax[$qualId]['Amount'] = $doamt + $dtamt;
                                                    }
                                                }
                                            }
                                        }

                                        $qualifierAmount=0;

                                        if(isset($arrTotTax)&& count($arrTotTax)>0) {
                                            foreach($arrTotTax as $tax) {
                                                $qualifierAmount+=floatval($tax['Amount']);
                                            }
                                        }



                                        $logStatus="";
                                        if ($unit['Status'] == 'B') {
                                            $unit['BuyerName'] = $unit['BlockedName'];
                                            $unit['NetAmt'] =  $unit['BlockNet'];
                                            $logStatus = "blocked";
                                        }
                                        if ($unit['Status'] == 'P') {
                                            $unit['BuyerName'] = $unit['PreName'];
                                            $unit['NetAmt'] =  $unit['PreNet'];
                                            $logStatus = "prebook";
                                        }
                                        if ($unit['Status'] == 'U') {
                                            $unit['BuyerName'] = '';
                                            $logStatus = " ";
                                            $unit['NetAmt'] =  $unit['NetAmt']+ $qualifierAmount ;
                                        }
                                        if ($unit['Status'] == 'S') {
                                            $logStatus = "sold_out";
                                        }
                                        if ($unit['Status'] == 'R') {
                                            $logStatus = "reserved";
                                        }
                                        if ($unit['postNet'] != '') {
                                            $unit['NetAmt'] =  $unit['postNet'];
                                        }
                                        $blockAllDetails .= "<div class='col-lg-3 col-md-3 col-sm-6 flat_grid carpark_grid " . $logStatus . "'>
                                        <a class='ripple rightbox_trigger unitgrid'  cid='" . $unit['UnitId'] . "' >
                                            <div class='slot_no flat_no'>
                                                <span class='float_r brad_50' id='UnitNo' title='" . $unit['UnitNo'] . "' >" . $unit['UnitNo'] . "</span>
                                                <p>Unit No</p>
                                            </div>
                                            <ul>
                                                <li>
                                                    <div class='col-lg-6 col-md-6 col-sm-6 col-xs-6 txt_left padlr0'><p><span class='p_label'>Buyer Name</span></p></div>
                                                    <div class='col-lg-6 col-md-6 col-sm-6 col-xs-6 padlr0'>
                                                        <p>" . $unit['BuyerName'] . "</p>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class='col-lg-6 col-md-6 col-sm-6 col-xs-6 txt_left padlr0'><p><span class='p_label'>Area</span></p></div>
                                                    <div class='col-lg-6 col-md-6 col-sm-6 padlr0'>
                                                        <p>" . $unit['UnitArea'] . "</p>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class='col-lg-6 col-md-6 col-sm-6 col-xs-6 txt_left padlr0'><p><span class='p_label'>Net Amount</span></p></div>
                                                    <div class='col-lg-6 col-md-6 col-sm-6 padlr0'>
                                                        <p>" .$viewRenderer->commonHelper()->sanitizeNumber($unit['NetAmt'],2,true) . "</p>
                                                    </div>
                                                </li>
                                            </ul>
                                        </a>
                                    </div>";
                                    }
                                } else {
                                    $blockAllDetails .= "<p class='text-center'> No Units Found!</p>";
                                }
                                $blockAllDetails.="</div>";
                            }
                            $blockAllDetails .= "</div>
                                    </div>
                                </div>
                            </div>
                        </div>";
                        }
                    }

                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($blockAllDetails);
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
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {


                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request
                try {
                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromRoute('projectId'), 'number');

                    if ($ProjectId <= 0) {
                        $select = $sql->select();
                        $select->from('Proj_ProjectMaster')
                            ->where(array('DeleteFlag' => 0));
                        $select->order("ProjectId Desc");
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $Project = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    } else {
                        $select = $sql->select();
                        $select->from('Proj_ProjectMaster')
                            ->where(array('DeleteFlag' => 0, 'ProjectId' => $ProjectId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $Project = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    }


                    if(empty($Project)) {
                        throw new \Exception('Project not found!');
                    }

                    // get blocks
                    $select = $sql->select();
                    $select->from(array('a'=>'KF_BlockMaster'))
                        ->columns(array('BlockId', 'BlockName'))
                        ->join(array('b' => 'KF_PhaseMaster'), 'b.PhaseId=a.PhaseId', array('PhaseName'), $select::JOIN_LEFT)
                        ->where(array('a.ProjectId' => $ProjectId, 'a.DeleteFlag' => 0 ))
                        ->order("a.BlockId ASC");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrBlocks = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                    //Select FloorName From KF_FloorMaster where BlockId=1
                    if(!empty($arrBlocks)) {
                        foreach($arrBlocks as &$block) {
                            $select = $sql->select();
                            $select->from(array('a' => 'KF_FloorMaster'))
                                ->columns(array('FloorId', 'FloorName'))
                                ->where(array('a.BlockId' => $block['BlockId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $arrFloors= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $block['arrFloors']= $arrFloors;
                        }
                    }

                    if(!empty($arrBlocks)) {
                        // get units inside block
                        foreach($arrBlocks as &$block) {
                            foreach ($block['arrFloors'] as &$floor) {
                                $select = $sql->select();
                                $select->from(array('a' => 'KF_UnitMaster'))
                                    ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status, Case When d.BookingId > 0 then d.NetAmount  else  b.NetAmt   End as NetAmt"),'rank' => new expression("RANK() OVER (PARTITION BY a.UnitId ORDER BY e.CreatedDate desc)")))
                                    ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId', array('StatusId', 'Rate'), $select::JOIN_LEFT)
                                    ->join(array('g' => 'Crm_ProjectDetail'), 'g.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                                    ->join(array('c' => 'Proj_UOM'), 'g.AreaUnit=c.UnitId', array('UnitName'), $select::JOIN_LEFT)
                                    ->join(array('d' => 'Crm_UnitBooking'),  new Expression("a.UnitId=d.UnitId and d.DeleteFlag=0"), array(), $select::JOIN_LEFT)
                                    ->join(array('e' => 'Crm_PostSaleDiscountRegister'), 'e.BookingId =d.BookingId', array("postNet"=>"NetAmount",'PostSaleDiscountId'), $select::JOIN_LEFT)
                                    ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array("BlockNet"=>"NetAmount",'BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                                    ->join(array('i' => 'Crm_UnitPreBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array("PreNet"=>"NetAmount",'PreStatus' => new Expression("CAST ( CASE WHEN i.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                                    ->join(array('h' => 'Crm_Leads'), 'h.LeadId=d.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
                                    ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
                                    ->join(array('l' => 'Crm_Leads'), 'l.LeadId=i.LeadId', array('PreName' => 'LeadName'), $select::JOIN_LEFT)
                                    ->where(array('a.ProjectId' => $ProjectId, 'a.FloorId' => $floor['FloorId'], 'a.DeleteFlag' => 0));

                                $select1 = $sql->select();
                                $select1->from(array("g"=>$select))
                                    ->where(array('g.rank'=>1));
                                $stmt = $sql->getSqlStringForSqlObject($select1);
                                $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                 foreach($arrUnits as &$unit ) {
                                     $select = $sql->select();
                                     $select->from(array('a' => 'KF_UnitMaster'))
                                         ->columns(array(new Expression("a.UnitId,a.UnitTypeId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
                                         ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                                         ->where(array("a.UnitId"=>$unit['UnitId']));
                                     $stmt = $sql->getSqlStringForSqlObject($select);
                                     $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                                     $select = $sql->select();
                                     $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
                                         ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_INNER)
                                         ->columns(array('OtherCostId','Amount', 'Area', 'Rate'))
                                         ->where(array('a.UnitTypeId' => $unitIn['UnitTypeId']));
                                     $stmt = $sql->getSqlStringForSqlObject($select);
                                     $unitamt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                     //taxcalculation//
                                     $dGrossAmt = $unitIn['GrossAmount'];
                                     $dDate = date('Y-m-d');
                                     $arrReceipt=array();
                                     $arrReceipt[]=array("ReceiptType"=>'S',"TypeId"=>1,"Amount"=>$unitIn['LandAmount']);
                                     $arrReceipt[]=array("ReceiptType"=>'S',"TypeId"=>2,"Amount"=>$unitIn['ConstructionAmount']);


                                     if(isset($unitamt)) {
                                         foreach($unitamt as $uAmt) {
                                             $arrReceipt[]=array("ReceiptType"=>'O',"TypeId"=>$uAmt['OtherCostId'],"Amount"=>$uAmt['Amount']);
                                         }
                                     }

                                     $select = $sql->select();
                                     $select->from(array('a' => 'Proj_QualifierTrans'))
                                         ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
                                         ->where(array('a.QualType'=>'C'))
                                         ->columns(array('QualifierId'));
                                     $statement = $sql->getSqlStringForSqlObject($select);
                                     $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                     $arrTotTax = array();
                                     foreach ($arrList as $atax) {
                                         $arrTotTax[$atax['QualifierId']]['QualifierId'] = $atax['QualifierId'];
                                         $arrTotTax[$atax['QualifierId']]['QualifierName'] = $atax['QualifierName'];
                                         $arrTotTax[$atax['QualifierId']]['Amount'] = 0;
                                     }

                                     if (!empty($arrReceipt)) {
                                         foreach ($arrReceipt as $v) {

                                             $sReceiptType = $this->bsf->isNullCheck($v['ReceiptType'], 'string');
                                             $iTypeId = $this->bsf->isNullCheck($v['TypeId'], 'number');
                                             $dBaseAmt = floatval($this->bsf->isNullCheck($v['Amount'], 'number'));

                                             $select = $sql->select();
                                             $select->from(array('c' => 'Crm_QualifierSettings'))
                                                 ->join(array("a" => "Proj_QualifierTrans"), 'c.QualifierId=a.QualifierId',
                                                     array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(a.QualifierId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                                         'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                                         'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select::JOIN_LEFT)
                                                 ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER);
                                             $select->where(array('QualSetType' => $sReceiptType, 'QualSetTypeId' => $iTypeId, 'a.QualType' => 'C'))
                                                 ->order('SortOrder ASC');
                                             $statement = $sql->getSqlStringForSqlObject($select);
                                             $arrQualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                             $qualTotAmt = 0;

                                             $arrTax = array();
                                             foreach ($arrQualList as $qualList) {
                                                 $sRefNo = $qualList['RefId'];
                                                 $arrTax[$sRefNo]['QualifierName'] = $qualList['QualifierName'];
                                                 $arrTax[$sRefNo]['QualifierId'] = $qualList['QualifierId'];
                                                 $arrTax[$sRefNo]['RefNo'] = $qualList['RefId'];
                                                 $arrTax[$sRefNo]['QualifierTypeId'] = $qualList['QualifierTypeId'];
                                                 //$arrTax[$sRefNo]['Expression'] = $qualList['Expression'];
                                                 $arrTax[$sRefNo]['ExpPer'] = $qualList['ExpPer'];


                                                 $sExpression = $qualList['Expression'];
                                                 $sExpression = str_replace('R0', $dBaseAmt, $sExpression);
                                                 $arrTax[$sRefNo]['Expression'] = $sExpression;

                                                 $arrTax[$sRefNo]['TaxablePer'] = 0;
                                                 $arrTax[$sRefNo]['TaxPer'] = 0;
                                                 $arrTax[$sRefNo]['SurCharge'] = 0;
                                                 $arrTax[$sRefNo]['EDCess'] = 0;
                                                 $arrTax[$sRefNo]['HEDCess'] = 0;
                                                 $arrTax[$sRefNo]['SBCess'] = 0;
                                                 $arrTax[$sRefNo]['KKCess'] = 0;
                                                 $arrTax[$sRefNo]['NetPer'] = 0;

                                                 if ($qualList['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {
                                                     $tds = CommonHelper::getTDSSetting(11, $dDate, $dbAdapter);
                                                     $arrTax[$sRefNo]['TaxablePer'] = $tds["TaxablePer"];
                                                     $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                                     $arrTax[$sRefNo]['SurCharge'] = $tds["SurCharge"];
                                                     $arrTax[$sRefNo]['EDCess'] = $tds["EDCess"];
                                                     $arrTax[$sRefNo]['HEDCess'] = $tds["HEDCess"];
                                                     $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];

                                                 } else if ($qualList['QualifierTypeId'] == 2) {
                                                     $select = $sql->select();
                                                     if ($sReceiptType == "S") {
                                                         $select->from('Crm_ReceiptTypeMaster')
                                                             ->columns(array('TaxablePer'))
                                                             ->where(array('ReceiptTypeId' => $iTypeId));

                                                     } else {
                                                         $select->from('Crm_OtherCostMaster')
                                                             ->columns(array('TaxablePer'))
                                                             ->where(array('OtherCostId' => $iTypeId));
                                                     }

                                                     $stmt = $sql->getSqlStringForSqlObject($select);
                                                     $stTax = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                                     $Taxable = 0;
                                                     if (!empty($stTax)) $Taxable = $stTax['TaxablePer'];

                                                     $tds = CommonHelper::getSTSetting('F', $dDate, $dbAdapter);
                                                     $arrTax[$sRefNo]['TaxablePer'] = $Taxable;
                                                     $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                                     $arrTax[$sRefNo]['SBCess'] = $tds["SBCess"];
                                                     $arrTax[$sRefNo]['KKCess'] = $tds["KKCess"];
                                                     $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];
                                                 }
                                             }

                                             foreach ($arrTax as $qual) {

                                                 $sRef = $qual['RefNo'];
                                                 $sExpression = $qual['Expression'];
                                                 $dAmt = eval('return ' . $sExpression . ';');

                                                 $dNetAmt = 0;

                                                 if ($qual['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {

                                                     $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                                     $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                                     $dSurCharge = floatval($this->bsf->isNullCheck($qual['SurCharge'], 'number'));
                                                     $dEDCess = floatval($this->bsf->isNullCheck($qual['EDCess'], 'number'));
                                                     $dHEDCess = floatval($this->bsf->isNullCheck($qual['HEDCess'], 'number'));

                                                     $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                                     $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                                     $dCessAmt = $dTaxAmt * ($dSurCharge / 100);
                                                     $dEDAmt = $dTaxAmt * ($dEDCess / 100);
                                                     $dHEDAmt = $dTaxAmt * ($dHEDCess / 100);
                                                     $dNetAmt = $dTaxAmt + $dCessAmt + $dEDAmt + $dHEDAmt;

                                                 } else if ($qual['QualifierTypeId'] == 2) {

                                                     $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                                     $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                                     $dSBCess = floatval($this->bsf->isNullCheck($qual['SBCess'], 'number'));
                                                     $dKKCess = floatval($this->bsf->isNullCheck($qual['KKCess'], 'number'));

                                                     $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                                     $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                                     $dKKCessAmt = $dTaxableAmt * ($dKKCess / 100);
                                                     $dSBCessAmt = $dTaxableAmt * ($dSBCess / 100);
                                                     $dNetAmt = $dTaxAmt + $dKKCessAmt + $dSBCessAmt;

                                                 } else {
                                                     $dPer = floatval($this->bsf->isNullCheck($qual['ExpPer'], 'number'));
                                                     if ($qual['QualifierTypeId'] !=1) {
                                                         if ($dPer != 0) $dNetAmt = $dAmt * ($dPer / 100);
                                                         else $dNetAmt = $dAmt;
                                                     }
                                                 }

                                                 $arrTax[$sRef]['NetAmt'] = $dNetAmt;
                                                 foreach ($arrTax as $qualData) {
                                                     $sExpression = $qualData['Expression'];
                                                     $ssRef = $qualData['RefNo'];

                                                     $sExpression = Str_replace($sRef, $dNetAmt, $sExpression);
                                                     $arrTax[$sRef]['Expression'] = $sExpression;
                                                 }
                                             }

                                             if (!empty($arrTax)) {

                                                 foreach ($arrTax as $qual) {
                                                     $qualId = $qual['QualifierId'];
                                                     $doamt =  floatval($this->bsf->isNullCheck($arrTotTax[$qualId]['Amount'],'number'));
                                                     $dtamt =  floatval($this->bsf->isNullCheck($qual['NetAmt'],'number'));

                                                     $arrTotTax[$qualId]['Amount'] = $doamt + $dtamt;
                                                 }
                                             }
                                         }
                                     }

                                     $qualifierAmount=0;

                                     if(isset($arrTotTax)&& count($arrTotTax)>0) {
                                         foreach($arrTotTax as $tax) {
                                             $qualifierAmount+=floatval($tax['Amount']);
                                         }
                                     }


                                     if ($unit['Status'] == 'U') {
                                         $unit['BuyerName'] = '';
                                         $unit['NetAmt'] =  $unit['NetAmt']+ $qualifierAmount ;
                                       }

                                     if ($unit['Status'] == 'B') {
                                         $unit['BuyerName'] = $unit['BlockedName'];
                                         $unit['NetAmt'] =  $unit['BlockNet'];
                                     }
                                     if ($unit['Status'] == 'P') {
                                         $unit['BuyerName'] = $unit['PreName'];
                                         $unit['NetAmt'] =  $unit['PreNet'];
                                     }

                                     if ($unit['postNet'] != '') {
                                         $unit['NetAmt'] =  $unit['postNet'];
                                     }

                                 }






                                $floor['arrUnits'] = $arrUnits;

                            }
                        }


                        $this->_view->arrBlocks = $arrBlocks;
                    }


                    $this->_view->ProjId = $this->bsf->encode($ProjectId);
                    $this->_view->project = $Project;
                    $this->_view->filldet = $this->bsf->isNullCheck($this->params()->fromRoute('soldId'), 'number' );


                    // Projects
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId', 'ProjectName'))
                        ->where(array('DeleteFlag' => 0));
                    $select->order("ProjectId desc");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrProjects = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrProjects = $arrProjects;

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

    public function unitgridDetailsAction() {

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
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $gridId = $this->bsf->isNullCheck($this->params()->fromPost('cid'), 'number' );
                    if($gridId == 0) {
                        throw new \Exception('Invalid cid-id!');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea,a.UnitTypeId, a.Status,a.ProjectId,Case When i.BookingId > 0 then 1 else  0  End as count")))
                        ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                        ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                        ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
                        ->join(array("d"=>"Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac"=>"Description"), $select::JOIN_LEFT)
                        ->join(array("e"=>"Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status"=>"Description"), $select::JOIN_LEFT)
                        ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT)
                        ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('SLeadId'=>'LeadId','B00kRate'=>'Rate','BOther'=>'OtherCostAmount','BQual'=>'QualifierAmount','BDiscountType'=>'DiscountType','BNet'=>'NetAmount','BBase'=>'BaseAmount','BConst'=>'ConstructionAmount',"BOther"=>'OtherCostAmount','BLand'=>'LandAmount','BookingStatus' => new Expression("CAST ( CASE WHEN i.BookingId IS NOT NULL THEN 'Sold' ELSE '' END AS varchar(11))"),'BookingId','Approve','BDiscount'=>new Expression("isnull(i.Discount,0)")), $select::JOIN_LEFT)
                        ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId','PrevRate'=>'Rate','base'=>'BaseAmount','const'=>'ConstructionAmount','land'=>'LandAmount','gross'=>'GrossAmount','PostDiscount',"other"=>'OtherCostAmount',"PostDiscountType","qual"=>'QualifierAmount',"net"=>'NetAmount','PRate'=>'Rate'), $select::JOIN_LEFT)
                        ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array('BlockId','ValidUpto','BlockBAdv'=>'AdvAmnt','Blockbase'=>'BaseAmount','BRate','BlockDiscount'=>'Discount','Blockconst'=>'ConstructionAmount','Blockland'=>'LandAmount','Blockgross'=>'GrossAmount',"Blockother"=>'OtherCost',"Blockqual"=>'QualAmount',"Blocknet"=>'NetAmount','BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                        ->join(array('x' => 'Crm_UnitPreBooking'),new Expression("a.UnitId=x.UnitId and x.DeleteFlag=0"), array('PreBookingId','ValidUpto','PreBAdv'=>'AdvAmount','Prebase'=>'BaseAmount','PreRate'=>'PRate','PreDiscount'=>'Discount','Preconst'=>'ConstructionAmount','Preland'=>'LandAmount','Pregross'=>'GrossAmount',"Preother"=>'OtherCost',"Prequal"=>'QualAmount',"Prenet"=>'NetAmount','PreStatus' => new Expression("CAST ( CASE WHEN x.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                        ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
                        ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName','BLeadId'=>'LeadId'), $select::JOIN_LEFT)
                        ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto'=>'Photo'), $select::JOIN_LEFT)
                        ->join(array('w' => 'Crm_LeadPersonalInfo'), 'w.LeadId=j.LeadId', array('BlockedPhoto'=>'Photo'), $select::JOIN_LEFT)
                        ->join(array('y' => 'Crm_Leads'), 'y.LeadId=x.LeadId', array('PreName' => 'LeadName','PreLeadId'=>'LeadId'), $select::JOIN_LEFT)
                        ->join(array("c"=>"WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array("m"=>"WF_Users"), "j.ExecutiveId=m.UserId", array("BlockExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array("s"=>"WF_Users"), "y.ExecutiveId=s.UserId", array("PreExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array('ff' => 'Crm_UnitProposal'), 'b.UnitId=ff.UnitId',array ("ProDiscountType"=>'DiscountType','ProposalId',"ProDiscount"=>'Discount'), $select::JOIN_LEFT)
                        ->where(array("a.UnitId"=>$gridId))
                        ->order("o.PostSaleDiscountId desc");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $proj = $unitInfo['ProjectId'];


                    $select = $sql->select();
                    $select->from('Crm_ProjectOtherCostTrans')
                        ->columns(array('TotAmount' => new Expression("sum(Amount)")))
                        ->where(array('ProjectId' => $proj));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $other = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->other = $other;
                    $this->_view->UnitId = $gridId;


                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->columns(array(new Expression("a.UnitId,a.UnitTypeId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
                        ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId', array('*'), $select::JOIN_LEFT)
                        ->where(array("a.UnitId" => $unitInfo['UnitId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
                        ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_INNER)
                        ->columns(array('OtherCostId', 'Amount', 'Area', 'Rate'))
                        ->where(array('a.UnitTypeId' => $unitIn['UnitTypeId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitamt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


    $select = $sql->select();
    $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
        ->columns(array('NetAmount'))
        ->where(array("a.UnitId" => $unitInfo['UnitId'], "a.StageType" => 'A'));
    $stmt = $sql->getSqlStringForSqlObject($select);
    $unitbasamt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

    $select = $sql->select();
    $select->from(array('a' => 'crm_unitType '))
        ->columns(array('AdvAmount'))
        ->where(array("a.UnitTypeId" => $unitInfo['UnitTypeId']));
    $stmt = $sql->getSqlStringForSqlObject($select);
    $unitbaseamt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if($unitbasamt['NetAmount']>0){
            $this->_view->Amtresp = floatval($unitbasamt['NetAmount']);
        }else{
            $this->_view->Amtresp = floatval($unitbaseamt['AdvAmount']);
        }



                    //taxcalculation//
                    $dGrossAmt = $unitIn['GrossAmount'];
                    $dDate = date('Y-m-d');
                    $arrReceipt = array();
                    $arrReceipt[] = array("ReceiptType" => 'S', "TypeId" => 1, "Amount" => $unitIn['LandAmount']);
                    $arrReceipt[] = array("ReceiptType" => 'S', "TypeId" => 2, "Amount" => $unitIn['ConstructionAmount']);


                    if (isset($unitamt)) {
                        foreach ($unitamt as $uAmt) {
                            $arrReceipt[] = array("ReceiptType" => 'O', "TypeId" => $uAmt['OtherCostId'], "Amount" => $uAmt['Amount']);
                        }
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_QualifierTrans'))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
                        ->where(array('a.QualType' => 'C'))
                        ->columns(array('QualifierId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $arrTotTax = array();
                    foreach ($arrList as $atax) {
                        $arrTotTax[$atax['QualifierId']]['QualifierId'] = $atax['QualifierId'];
                        $arrTotTax[$atax['QualifierId']]['QualifierName'] = $atax['QualifierName'];
                        $arrTotTax[$atax['QualifierId']]['Amount'] = 0;
                    }

                    if (!empty($arrReceipt)) {
                        foreach ($arrReceipt as $v) {

                            $sReceiptType = $this->bsf->isNullCheck($v['ReceiptType'], 'string');
                            $iTypeId = $this->bsf->isNullCheck($v['TypeId'], 'number');
                            $dBaseAmt = floatval($this->bsf->isNullCheck($v['Amount'], 'number'));

                            $select = $sql->select();
                            $select->from(array('c' => 'Crm_QualifierSettings'))
                                ->join(array("a" => "Proj_QualifierTrans"), 'c.QualifierId=a.QualifierId',
                                    array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(a.QualifierId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                        'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                        'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select::JOIN_LEFT)
                                ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER);
                            $select->where(array('QualSetType' => $sReceiptType, 'QualSetTypeId' => $iTypeId, 'a.QualType' => 'C'))
                                ->order('SortOrder ASC');
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arrQualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $qualTotAmt = 0;

                            $arrTax = array();
                            foreach ($arrQualList as $qualList) {
                                $sRefNo = $qualList['RefId'];
                                $arrTax[$sRefNo]['QualifierName'] = $qualList['QualifierName'];
                                $arrTax[$sRefNo]['QualifierId'] = $qualList['QualifierId'];
                                $arrTax[$sRefNo]['RefNo'] = $qualList['RefId'];
                                $arrTax[$sRefNo]['QualifierTypeId'] = $qualList['QualifierTypeId'];
                                //$arrTax[$sRefNo]['Expression'] = $qualList['Expression'];
                                $arrTax[$sRefNo]['ExpPer'] = $qualList['ExpPer'];


                                $sExpression = $qualList['Expression'];
                                $sExpression = str_replace('R0', $dBaseAmt, $sExpression);
                                $arrTax[$sRefNo]['Expression'] = $sExpression;

                                $arrTax[$sRefNo]['TaxablePer'] = 0;
                                $arrTax[$sRefNo]['TaxPer'] = 0;
                                $arrTax[$sRefNo]['SurCharge'] = 0;
                                $arrTax[$sRefNo]['EDCess'] = 0;
                                $arrTax[$sRefNo]['HEDCess'] = 0;
                                $arrTax[$sRefNo]['SBCess'] = 0;
                                $arrTax[$sRefNo]['KKCess'] = 0;
                                $arrTax[$sRefNo]['NetPer'] = 0;

                                if ($qualList['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {
                                    $tds = CommonHelper::getTDSSetting(11, $dDate, $dbAdapter);
                                    $arrTax[$sRefNo]['TaxablePer'] = $tds["TaxablePer"];
                                    $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                    $arrTax[$sRefNo]['SurCharge'] = $tds["SurCharge"];
                                    $arrTax[$sRefNo]['EDCess'] = $tds["EDCess"];
                                    $arrTax[$sRefNo]['HEDCess'] = $tds["HEDCess"];
                                    $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];

                                } else if ($qualList['QualifierTypeId'] == 2) {
                                    $select = $sql->select();
                                    if ($sReceiptType == "S") {
                                        $select->from('Crm_ReceiptTypeMaster')
                                            ->columns(array('TaxablePer'))
                                            ->where(array('ReceiptTypeId' => $iTypeId));

                                    } else {
                                        $select->from('Crm_OtherCostMaster')
                                            ->columns(array('TaxablePer'))
                                            ->where(array('OtherCostId' => $iTypeId));
                                    }

                                    $stmt = $sql->getSqlStringForSqlObject($select);
                                    $stTax = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $Taxable = 0;
                                    if (!empty($stTax)) $Taxable = $stTax['TaxablePer'];

                                    $tds = CommonHelper::getSTSetting('F', $dDate, $dbAdapter);
                                    $arrTax[$sRefNo]['TaxablePer'] = $Taxable;
                                    $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                    $arrTax[$sRefNo]['SBCess'] = $tds["SBCess"];
                                    $arrTax[$sRefNo]['KKCess'] = $tds["KKCess"];
                                    $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];
                                }
                            }

                            foreach ($arrTax as $qual) {

                                $sRef = $qual['RefNo'];
                                $sExpression = $qual['Expression'];
                                $dAmt = eval('return ' . $sExpression . ';');

                                $dNetAmt = 0;

                                if ($qual['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {

                                    $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                    $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                    $dSurCharge = floatval($this->bsf->isNullCheck($qual['SurCharge'], 'number'));
                                    $dEDCess = floatval($this->bsf->isNullCheck($qual['EDCess'], 'number'));
                                    $dHEDCess = floatval($this->bsf->isNullCheck($qual['HEDCess'], 'number'));

                                    $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                    $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                    $dCessAmt = $dTaxAmt * ($dSurCharge / 100);
                                    $dEDAmt = $dTaxAmt * ($dEDCess / 100);
                                    $dHEDAmt = $dTaxAmt * ($dHEDCess / 100);
                                    $dNetAmt = $dTaxAmt + $dCessAmt + $dEDAmt + $dHEDAmt;

                                } else if ($qual['QualifierTypeId'] == 2) {

                                    $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                    $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                    $dSBCess = floatval($this->bsf->isNullCheck($qual['SBCess'], 'number'));
                                    $dKKCess = floatval($this->bsf->isNullCheck($qual['KKCess'], 'number'));

                                    $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                    $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                    $dKKCessAmt = $dTaxableAmt * ($dKKCess / 100);
                                    $dSBCessAmt = $dTaxableAmt * ($dSBCess / 100);
                                    $dNetAmt = $dTaxAmt + $dKKCessAmt + $dSBCessAmt;

                                } else {
                                    $dPer = floatval($this->bsf->isNullCheck($qual['ExpPer'], 'number'));
                                    if ($qual['QualifierTypeId'] != 1) {
                                        if ($dPer != 0) $dNetAmt = $dAmt * ($dPer / 100);
                                        else $dNetAmt = $dAmt;
                                    }
                                }

                                $arrTax[$sRef]['NetAmt'] = $dNetAmt;
                                foreach ($arrTax as $qualData) {
                                    $sExpression = $qualData['Expression'];
                                    $ssRef = $qualData['RefNo'];

                                    $sExpression = Str_replace($sRef, $dNetAmt, $sExpression);
                                    $arrTax[$sRef]['Expression'] = $sExpression;
                                }
                            }

                            if (!empty($arrTax)) {

                                foreach ($arrTax as $qual) {
                                    $qualId = $qual['QualifierId'];
                                    $doamt = floatval($this->bsf->isNullCheck($arrTotTax[$qualId]['Amount'], 'number'));
                                    $dtamt = floatval($this->bsf->isNullCheck($qual['NetAmt'], 'number'));

                                    $arrTotTax[$qualId]['Amount'] = $doamt + $dtamt;
                                }
                            }
                        }
                    }

                    $qualifierAmount = 0;
                    if (isset($arrTotTax) && count($arrTotTax) > 0) {
                        foreach ($arrTotTax as $tax) {
                            $qualifierAmount += floatval($tax['Amount']);
                        }
                    }


                    if(empty($unitInfo)) {
                        throw new \Exception('Unit info not found!');
                    }

                    if($unitInfo['Status'] == 'B') {
                        $unitInfo['BuyerName'] = $unitInfo['BlockedName'];
                       $unitInfo['ExecutiveName'] = $unitInfo['BlockExecutiveName'];
                        $unitInfo['OtherCostAmt'] = 0;
                        $unitInfo['NetAmt'] = $unitInfo['Blocknet'];
                        $unitInfo['QualifierAmount'] =$unitInfo['Blockqual'];
                        $unitInfo['Discount'] =$unitInfo['BlockDiscount'];
                        $unitInfo['AdvAmount'] =$unitInfo['BlockBAdv'];
                        $unitInfo['BaseAmt'] = $unitInfo['Blockbase'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['Blockconst'];
                        $unitInfo['GrossAmount'] =$unitInfo['Blockgross'];
                        $unitInfo['Rate'] =$unitInfo['BRate'];
                        $unitInfo['LeadId'] =$unitInfo['BLeadId'];
                        //  $unitInfo['Rate'] =$unitInfo['BRate'];
                        $unitInfo['LandAmount'] =$unitInfo['Blockland'];
                    }
                    else if($unitInfo['Status'] == 'P') {

                        $unitInfo['BuyerName'] = $unitInfo['PreName'];
//                        $unitInfo['LeadId'] = $unitInfo['PreId'];
                        $unitInfo['ExecutiveName'] = $unitInfo['PreExecutiveName'];
                        $unitInfo['OtherCostAmt'] =0;
                        $unitInfo['NetAmt'] = $unitInfo['Prenet'];
                        $unitInfo['QualifierAmount'] =$unitInfo['Prequal'];
                        $unitInfo['Discount'] =$unitInfo['PreDiscount'];
                        $unitInfo['AdvAmount'] =$unitInfo['PreBAdv'];
                        $unitInfo['BaseAmt'] = $unitInfo['Prebase'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['Preconst'];
                        $unitInfo['GrossAmount'] =$unitInfo['Pregross'];
                        $unitInfo['Rate'] =$unitInfo['PreRate'];
                        $unitInfo['LandAmount'] =$unitInfo['Preland'];
                        $unitInfo['LeadId'] =$unitInfo['PreLeadId'];
                    }
                    else if($unitInfo['Status'] == 'U') {

                        $unitInfo['BuyerName'] = " ";
                        $unitInfo['QualifierAmount'] =$qualifierAmount;
                        $unitInfo['NetAmt'] =$unitInfo['NetAmt']+$qualifierAmount;

                        //OtherCostAmount
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))

                            ->columns(array('Amount'=>new expression("SUM(a.Amount)")))
                            ->where(array('a.UnitTypeId' => $unitIn['UnitTypeId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $otheramt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $unitInfo['OtherCostAmt'] =$otheramt['Amount'];


                    }
                    else if($unitInfo['Status'] == 'R') {
                        $unitInfo['OtherCostAmt'] =0;
                    }
                    if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {

                        $unitInfo['OtherCostAmt'] = $unitInfo['other'];
                        $unitInfo['NetAmt'] = $unitInfo['net'];
                        $unitInfo['QualifierAmount'] =$unitInfo['qual'];
                        $unitInfo['Discount'] =$unitInfo['PostDiscount'];
                        $unitInfo['BaseAmt'] = $unitInfo['base'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['const'];
                        $unitInfo['GrossAmount'] =$unitInfo['gross'];
                        $unitInfo['LandAmount'] =$unitInfo['land'];
                        $unitInfo['Rate'] =$unitInfo['PrevRate'];
                        $unitInfo['LeadId'] =$unitInfo['SLeadId'];
                    }
                    else if($unitInfo['count'] == 1) {
                        $unitInfo['OtherCostAmt'] = $unitInfo['BOther'];
                        $unitInfo['NetAmt'] = $unitInfo['BNet'];
                        $unitInfo['QualifierAmount'] =$unitInfo['BQual'];
                        $unitInfo['Discount'] =$unitInfo['BDiscount'];
                        $unitInfo['BaseAmt'] = $unitInfo['BBase'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['BConst'];
                        $unitInfo['GrossAmount'] =$unitInfo['BBase']+ $unitInfo['BOther'];
                        $unitInfo['LandAmount'] =$unitInfo['BLand'];
                        $unitInfo['Rate'] =$unitInfo['B00kRate'];
                        $unitInfo['LeadId'] =$unitInfo['SLeadId'];

                    }

                    // //$this->_view->unitInfo = $unitInfo;

           if($unitInfo['Status'] == 'U'){
               $select = $sql->select();
               $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
                   ->join(array('b' => 'CRM_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                   ->where(array('a.UnitTypeId' => $unitIn['UnitTypeId']));
               $stmt = $sql->getSqlStringForSqlObject($select);
               $this->_view->arrUnitOtherCost= $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


           }
                    else {
                        // Unit Other Cost
                        $select = $sql->select();
                        $select->from(array('a' => 'CRM_FinalisationOtherCostTrans'))
                            ->join(array('b' => 'CRM_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                            ->where(array('a.UnitId' => $gridId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arrUnitOtherCost = $arrUnitOtherCost = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    $select = $sql->select();
                    $select->from(array('a' => 'CRM_UnitTypePhotoTrans'))
                        ->columns(array('UnitTypeId', 'ImageUrl'))
                        ->join(array('b' => 'KF_UnitMaster'), 'a.UnitTypeId=b.UnitTypeId', array(), $select::JOIN_INNER)
                        ->where(array("b.UnitId"=>$gridId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnitTypePhotos = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrUnitTypePhotos = $arrUnitTypePhotos;

                    $this->_view->resp = $unitInfo;



                    $this->_view->setTerminal(true);
                    return $this->_view;
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

    public function unitFulldetailsAction(){
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
            $request = $this->getRequest();
            if ($request->isPost()) {
                try {
                    $postData = $request->getPost();


                    $arrUnitDetails= $postData['arrUnitDetails'];
                    $arrUnitDetails= json_decode($arrUnitDetails,true);

                    $unitId = $arrUnitDetails['UnitId'];

                    $newFileName = $this->generateRandomString() . '.pdf';

                    $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";
                    $pdfHtml = $this->generateDownloadPdf($arrUnitDetails);

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

                    $dir = 'public/uploads/crm/agreement/' .$unitId;

                    if(!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $filePath = $dir . '/'. $fileName;
                    file_put_contents($filePath, $output);
                    $response->setContent($filePath);
                    //Write your Ajax post code here

                } catch(PDOException $e){
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                // GET request
                $UnitId = $this->bsf->isNullCheck($this->params()->fromRoute('UnitDetailId'), 'number' );
                if($UnitId == 0) {
                    throw new \Exception('Invalid Unit!');
                }

                $select = $sql->select();
                $select->from(array('a' => 'KF_UnitMaster'))
                    ->columns(array('UnitId', 'UnitNo', 'UnitArea'))
                    ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',
                        array('*'), $select::JOIN_LEFT)
                    ->join(array("c"=>"WF_Users"), "b.ExecutiveId=c.UserId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                    ->join(array("d"=>"Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac"=>"Description"), $select::JOIN_LEFT)
                    ->join(array("e"=>"Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status"=>"Description"), $select::JOIN_LEFT)
                    ->where(array("a.UnitId"=>$UnitId));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $this->_view->resultsResp = $unitInfo;
                $this->_view->setTerminal(true);
                return $this->_view;
            }
            return $response;
        } else {

            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
                // Print_r($postData); die;
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {


                    $files = $request->getFiles();
                    // save documents
                    $unitId = $this->bsf->isNullCheck($this->params()->fromRoute('UnitDetailId'), 'number' );

                    $documentrowid = $this->bsf->isNullCheck($postData['documentrowid'], 'number');
                    $documentdeleteids = trim($this->bsf->isNullCheck($postData['documentdeleteids'],'string'), ",");
                    if($documentdeleteids !== '') {
                        $delete = $sql->delete();
                        $delete->from('Crm_UnitDetailsDocument')
                            ->where("DocumentId IN ($documentdeleteids)");
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    for ($i = 1; $i <= $documentrowid; $i++) {
                        $Type = $this->bsf->isNullCheck($postData['docType_' . $i], 'string');
                        $Description = $this->bsf->isNullCheck($postData['docDesc_' . $i], 'string');
                        $url = $this->bsf->isNullCheck($postData['docFile_' . $i], 'string');
                        $docId = $this->bsf->isNullCheck($postData['docTransId_' . $i], 'number');

                        if ($Type == '' || $Description == '')
                            continue;

                        if($files['docFile_' . $i]['name']){
                            $dir = 'public/uploads/crm/project/unit/'.$unitId.'/';
                            $filename = $this->bsf->uploadFile($dir, $files['docFile_' . $i]);

                            if($filename) {
                                // upload valid files only
                                $url = '/uploads/crm/project/unit/'.$unitId.'/' . $filename;
                            }
                        }

                        if ($docId) {
                            $update = $sql->update();
                            $update->table('Crm_UnitDetailsDocument')
                                ->set(array('Type' => $Type, 'Description' => $Description, 'URL' => $url))
                                ->where(array('DocumentId' => $docId));
                            $statement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {
                            $insert = $sql->insert();
                            $insert->into('Crm_UnitDetailsDocument');
                            $insert->Values(array('UnitId' => $unitId, 'Type' => $Type, 'Description' => $Description, 'URL' => $url));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    //update the checklist-final&handingover
                    $hrCount = $this->bsf->isNullCheck($postData['hrowCount'], 'number');
                    if($hrCount !== '') {
                        $delete = $sql->delete();
                        $delete->from('Crm_HandingoverCheckTrans')
                            ->where(array('UnitId' => $unitId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        for ($i = 1; $i <=$hrCount; $i++) {
                            $checkdate = $this->bsf->isNullCheck($postData['hCheckDate_' . $i], 'string');
                            $insert = $sql->insert();
                            $insert->into('Crm_HandingoverCheckTrans');
                            $insert->Values(array('UnitId' => $unitId,
                                'CheckListId' => $this->bsf->isNullCheck($postData['hCheckListId_' . $i], 'number'),
                                'Date' => date('Y-m-d', strtotime($checkdate)),
                                'ExecutiveId' => $this->bsf->isNullCheck($postData['hExecutiveId_' . $i], 'number'),
                                'IsChecked' => '1'));
                            $statement = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                    }
                    $frCount = $this->bsf->isNullCheck($postData['frowCount'], 'number');
                    $bookId = $this->bsf->isNullCheck($postData['fBookingId'], 'number');

                    if($frCount !== '') {
                        $delete = $sql->delete();
                        $delete->from('Crm_FinalisationCheckListTrans')
                            ->where(array('BookingId' => $bookId));
                        $statement = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    for($j=1; $j <=$frCount;$j++){
                        $checkdate = $this->bsf->isNullCheck($postData['fCheckDate_' . $j], 'string');
                        $insert = $sql->insert();
                        $insert->into('Crm_FinalisationCheckListTrans');
                        $insert->Values(array('BookingId' => $bookId,
                            'CheckListId' => $this->bsf->isNullCheck($postData['fCheckListId_'. $j], 'number'),
                            'SubmittedDate' =>date('Y-m-d', strtotime($checkdate)),
                            'ExecutiveId' =>$this->bsf->isNullCheck($postData['fExecutiveId_' . $j], 'number'),
                            'IsChecked' => '1'));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    $connection->commit();
                    // deleted files
                    if(!empty($delFilesUrl)) {
                        foreach($delFilesUrl as $url) {
                            unlink($url);
                        }
                    }

                    $this->redirect()->toRoute('crm/unit-fulldetails', array('controller' => 'project', 'action' => 'unit-fulldetails', 'UnitDetailId' => $unitId));
                } catch(PDOException $e){
                    $connection->rollback();
                }

            } else {
                // GET request

                try {
                    $UnitId = $this->bsf->isNullCheck($this->params()->fromRoute('UnitDetailId'), 'number' );
                    if($UnitId == 0) {
                        throw new \Exception('Invalid Unit!');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId, Case When i.BookingId > 0 then 1 else  0  End as count")))
                        ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                        ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                        ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
                        ->join(array("d"=>"Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac"=>"Description"), $select::JOIN_LEFT)
                        ->join(array("e"=>"Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status"=>"Description"), $select::JOIN_LEFT)
                        ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT)
                        ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('B00kRate'=>'Rate','BOther'=>'OtherCostAmount','BQual'=>'QualifierAmount','BDiscountType'=>'DiscountType','BNet'=>'NetAmount','BBase'=>'BaseAmount','BConst'=>'ConstructionAmount','BLand'=>'LandAmount','BookingStatus' => new Expression("CAST ( CASE WHEN i.BookingId IS NOT NULL THEN 'Sold' ELSE '' END AS varchar(11))"),'BookingId','Approve','BDiscount'=>new Expression("isnull(i.Discount,0)")), $select::JOIN_LEFT)
                        ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId','PrevRate'=>'Rate','base'=>'BaseAmount','const'=>'ConstructionAmount','land'=>'LandAmount','gross'=>'GrossAmount','PostDiscount',"other"=>'OtherCostAmount',"PostDiscountType","qual"=>'QualifierAmount',"net"=>'NetAmount','PRate'=>'Rate'), $select::JOIN_LEFT)
                        ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array('BlockId','ValidUpto','BlockBAdv'=>'AdvAmnt','Blockbase'=>'BaseAmount','BRate','BlockDiscount'=>'Discount','Blockconst'=>'ConstructionAmount','Blockland'=>'LandAmount','Blockgross'=>'GrossAmount',"Blockother"=>'OtherCost',"Blockqual"=>'QualAmount',"Blocknet"=>'NetAmount','BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                        ->join(array('x' => 'Crm_UnitPreBooking'),new Expression("a.UnitId=x.UnitId and x.DeleteFlag=0"), array('PreBookingId','ValidUpto','PreBAdv'=>'AdvAmount','Prebase'=>'BaseAmount','PreRate'=>'PRate','PreDiscount'=>'Discount','Preconst'=>'ConstructionAmount','Preland'=>'LandAmount','Pregross'=>'GrossAmount',"Preother"=>'OtherCost',"Prequal"=>'QualAmount',"Prenet"=>'NetAmount','PreStatus' => new Expression("CAST ( CASE WHEN x.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                        ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName','LeadId' ), $select::JOIN_LEFT)
                        ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
                        ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto'=>'Photo'), $select::JOIN_LEFT)
                        ->join(array('w' => 'Crm_LeadPersonalInfo'), 'w.LeadId=j.LeadId', array('BlockedPhoto'=>'Photo'), $select::JOIN_LEFT)
                        ->join(array('y' => 'Crm_Leads'), 'y.LeadId=x.LeadId', array('PreName' => 'LeadName'), $select::JOIN_LEFT)
                        ->join(array("c"=>"WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array("m"=>"WF_Users"), "j.ExecutiveId=m.UserId", array("BlockExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array("s"=>"WF_Users"), "y.ExecutiveId=s.UserId", array("PreExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array("l"=>"Proj_ProjectMaster"), "l.ProjectId=a.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                        ->join(array('ff' => 'Crm_UnitProposal'), 'b.UnitId=ff.UnitId',array ("ProDiscountType"=>'DiscountType','ProposalId',"ProDiscount"=>'Discount'), $select::JOIN_LEFT)
                        ->where(array("a.UnitId"=>$UnitId))
                        ->order("o.PostSaleDiscountId desc");
                       $stmt = $sql->getSqlStringForSqlObject($select);
                   $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $this->_view->proj=$proj = $unitInfo['ProjectId'];
                  // $this->_view->prop=$prop = $unitInfo['ProposalId'];
                   //$this->_view->dis=$dis = $unitInfo['Discount'];


                    if($unitInfo['ProposalId'] == '') {
                        $unitInfo['ProposalId'] = 0;
                    }

                    $select = $sql->select();
                    $select->from('Crm_ProjectOtherCostTrans')
                        ->columns(array('TotAmount' => new Expression("sum(Amount)")))
                        ->where(array('ProjectId' => $proj));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $other = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->other = $other;
                    $this->_view->UnitId = $UnitId;

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->columns(array(new Expression("a.UnitId,a.UnitTypeId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
                        ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                        ->where(array("a.UnitId"=>$UnitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
                        ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_INNER)
                        ->columns(array('OtherCostId','Amount', 'Area', 'Rate'))
                        ->where(array('a.UnitTypeId' => $unitIn['UnitTypeId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitamt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //taxcalculation//
                    $dGrossAmt = $unitIn['GrossAmount'];
                    $dDate = date('Y-m-d');
                    $arrReceipt=array();
                    $arrReceipt[]=array("ReceiptType"=>'S',"TypeId"=>1,"Amount"=>$unitIn['LandAmount']);
                    $arrReceipt[]=array("ReceiptType"=>'S',"TypeId"=>2,"Amount"=>$unitIn['ConstructionAmount']);


                    if(isset($unitamt)) {
                        foreach($unitamt as $uAmt) {
                            $arrReceipt[]=array("ReceiptType"=>'O',"TypeId"=>$uAmt['OtherCostId'],"Amount"=>$uAmt['Amount']);
                        }
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_QualifierTrans'))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)
                        ->where(array('a.QualType'=>'C'))
                        ->columns(array('QualifierId'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $arrTotTax = array();
                    foreach ($arrList as $atax) {
                        $arrTotTax[$atax['QualifierId']]['QualifierId'] = $atax['QualifierId'];
                        $arrTotTax[$atax['QualifierId']]['QualifierName'] = $atax['QualifierName'];
                        $arrTotTax[$atax['QualifierId']]['Amount'] = 0;
                    }

                    if (!empty($arrReceipt)) {
                        foreach ($arrReceipt as $v) {

                            $sReceiptType = $this->bsf->isNullCheck($v['ReceiptType'], 'string');
                            $iTypeId = $this->bsf->isNullCheck($v['TypeId'], 'number');
                            $dBaseAmt = floatval($this->bsf->isNullCheck($v['Amount'], 'number'));

                            $select = $sql->select();
                            $select->from(array('c' => 'Crm_QualifierSettings'))
                                ->join(array("a" => "Proj_QualifierTrans"), 'c.QualifierId=a.QualifierId',
                                    array('QualifierId', 'YesNo', 'RefId' => new Expression("'R'+ rtrim(ltrim(str(a.QualifierId)))"), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'KKCess', 'SBCess', 'NetPer', 'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                        'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                        'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SBCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'KKCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")), $select::JOIN_LEFT)
                                ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId'), $select::JOIN_INNER);
                            $select->where(array('QualSetType' => $sReceiptType, 'QualSetTypeId' => $iTypeId, 'a.QualType' => 'C'))
                                ->order('SortOrder ASC');
                            $statement = $sql->getSqlStringForSqlObject($select);
                            $arrQualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            $qualTotAmt = 0;

                            $arrTax = array();
                            foreach ($arrQualList as $qualList) {
                                $sRefNo = $qualList['RefId'];
                                $arrTax[$sRefNo]['QualifierName'] = $qualList['QualifierName'];
                                $arrTax[$sRefNo]['QualifierId'] = $qualList['QualifierId'];
                                $arrTax[$sRefNo]['RefNo'] = $qualList['RefId'];
                                $arrTax[$sRefNo]['QualifierTypeId'] = $qualList['QualifierTypeId'];
                                //$arrTax[$sRefNo]['Expression'] = $qualList['Expression'];
                                $arrTax[$sRefNo]['ExpPer'] = $qualList['ExpPer'];


                                $sExpression = $qualList['Expression'];
                                $sExpression = str_replace('R0', $dBaseAmt, $sExpression);
                                $arrTax[$sRefNo]['Expression'] = $sExpression;

                                $arrTax[$sRefNo]['TaxablePer'] = 0;
                                $arrTax[$sRefNo]['TaxPer'] = 0;
                                $arrTax[$sRefNo]['SurCharge'] = 0;
                                $arrTax[$sRefNo]['EDCess'] = 0;
                                $arrTax[$sRefNo]['HEDCess'] = 0;
                                $arrTax[$sRefNo]['SBCess'] = 0;
                                $arrTax[$sRefNo]['KKCess'] = 0;
                                $arrTax[$sRefNo]['NetPer'] = 0;

                                if ($qualList['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {
                                    $tds = CommonHelper::getTDSSetting(11, $dDate, $dbAdapter);
                                    $arrTax[$sRefNo]['TaxablePer'] = $tds["TaxablePer"];
                                    $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                    $arrTax[$sRefNo]['SurCharge'] = $tds["SurCharge"];
                                    $arrTax[$sRefNo]['EDCess'] = $tds["EDCess"];
                                    $arrTax[$sRefNo]['HEDCess'] = $tds["HEDCess"];
                                    $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];

                                } else if ($qualList['QualifierTypeId'] == 2) {
                                    $select = $sql->select();
                                    if ($sReceiptType == "S") {
                                        $select->from('Crm_ReceiptTypeMaster')
                                            ->columns(array('TaxablePer'))
                                            ->where(array('ReceiptTypeId' => $iTypeId));

                                    } else {
                                        $select->from('Crm_OtherCostMaster')
                                            ->columns(array('TaxablePer'))
                                            ->where(array('OtherCostId' => $iTypeId));
                                    }

                                    $stmt = $sql->getSqlStringForSqlObject($select);
                                    $stTax = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    $Taxable = 0;
                                    if (!empty($stTax)) $Taxable = $stTax['TaxablePer'];

                                    $tds = CommonHelper::getSTSetting('F', $dDate, $dbAdapter);
                                    $arrTax[$sRefNo]['TaxablePer'] = $Taxable;
                                    $arrTax[$sRefNo]['TaxPer'] = $tds["TaxPer"];
                                    $arrTax[$sRefNo]['SBCess'] = $tds["SBCess"];
                                    $arrTax[$sRefNo]['KKCess'] = $tds["KKCess"];
                                    $arrTax[$sRefNo]['NetPer'] = $tds["NetTax"];
                                }
                            }

                            foreach ($arrTax as $qual) {

                                $sRef = $qual['RefNo'];
                                $sExpression = $qual['Expression'];
                                $dAmt = eval('return ' . $sExpression . ';');

                                $dNetAmt = 0;

                                if ($qual['QualifierTypeId'] == 1 && $dGrossAmt > 5000000) {

                                    $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                    $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                    $dSurCharge = floatval($this->bsf->isNullCheck($qual['SurCharge'], 'number'));
                                    $dEDCess = floatval($this->bsf->isNullCheck($qual['EDCess'], 'number'));
                                    $dHEDCess = floatval($this->bsf->isNullCheck($qual['HEDCess'], 'number'));

                                    $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                    $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                    $dCessAmt = $dTaxAmt * ($dSurCharge / 100);
                                    $dEDAmt = $dTaxAmt * ($dEDCess / 100);
                                    $dHEDAmt = $dTaxAmt * ($dHEDCess / 100);
                                    $dNetAmt = $dTaxAmt + $dCessAmt + $dEDAmt + $dHEDAmt;

                                } else if ($qual['QualifierTypeId'] == 2) {

                                    $dTaxablePer = floatval($this->bsf->isNullCheck($qual['TaxablePer'], 'number'));
                                    $dTaxPer = floatval($this->bsf->isNullCheck($qual['TaxPer'], 'number'));
                                    $dSBCess = floatval($this->bsf->isNullCheck($qual['SBCess'], 'number'));
                                    $dKKCess = floatval($this->bsf->isNullCheck($qual['KKCess'], 'number'));

                                    $dTaxableAmt = $dAmt * ($dTaxablePer / 100);
                                    $dTaxAmt = $dTaxableAmt * ($dTaxPer / 100);
                                    $dKKCessAmt = $dTaxableAmt * ($dKKCess / 100);
                                    $dSBCessAmt = $dTaxableAmt * ($dSBCess / 100);
                                    $dNetAmt = $dTaxAmt + $dKKCessAmt + $dSBCessAmt;

                                } else {
                                    $dPer = floatval($this->bsf->isNullCheck($qual['ExpPer'], 'number'));
                                    if ($qual['QualifierTypeId'] !=1) {
                                        if ($dPer != 0) $dNetAmt = $dAmt * ($dPer / 100);
                                        else $dNetAmt = $dAmt;
                                    }
                                }

                                $arrTax[$sRef]['NetAmt'] = $dNetAmt;
                                foreach ($arrTax as $qualData) {
                                    $sExpression = $qualData['Expression'];
                                    $ssRef = $qualData['RefNo'];

                                    $sExpression = Str_replace($sRef, $dNetAmt, $sExpression);
                                    $arrTax[$sRef]['Expression'] = $sExpression;
                                }
                            }

                            if (!empty($arrTax)) {

                                foreach ($arrTax as $qual) {
                                    $qualId = $qual['QualifierId'];
                                    $doamt =  floatval($this->bsf->isNullCheck($arrTotTax[$qualId]['Amount'],'number'));
                                    $dtamt =  floatval($this->bsf->isNullCheck($qual['NetAmt'],'number'));

                                    $arrTotTax[$qualId]['Amount'] = $doamt + $dtamt;
                                }
                            }
                        }
                    }

                    $qualifierAmount=0;
                    if(isset($arrTotTax)&& count($arrTotTax)>0) {
                        foreach($arrTotTax as $tax) {
                            $qualifierAmount+=floatval($tax['Amount']);
                        }
                    }


                    if(empty($unitInfo)) {
                        throw new \Exception('Unit info not found!');
                    }

                    if($unitInfo['Status'] == 'B') {
                        $unitInfo['BuyerName'] = $unitInfo['BlockedName'];
                        $unitInfo['ExecutiveName'] = $unitInfo['BlockExecutiveName'];
                        $unitInfo['OtherCostAmt'] = 0;
                        $unitInfo['NetAmt'] = $unitInfo['Blocknet'];
                        $unitInfo['QualifierAmount'] =$unitInfo['Blockqual'];
                        $unitInfo['Discount'] =$unitInfo['BlockDiscount'];
                        $unitInfo['AdvAmount'] =$unitInfo['BlockBAdv'];
                        $unitInfo['BaseAmt'] = $unitInfo['Blockbase'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['Blockconst'];
                        $unitInfo['GrossAmount'] =$unitInfo['Blockgross'];
                        $unitInfo['Rate'] =$unitInfo['BRate'];
                        //  $unitInfo['Rate'] =$unitInfo['BRate'];
                        $unitInfo['LandAmount'] =$unitInfo['Blockland'];
                    }
                    else if($unitInfo['Status'] == 'P') {
                        $unitInfo['BuyerName'] = $unitInfo['PreName'];
                        $unitInfo['ExecutiveName'] = $unitInfo['PreExecutiveName'];
                        $unitInfo['OtherCostAmt'] =0;
                        $unitInfo['NetAmt'] = $unitInfo['Prenet'];
                        $unitInfo['QualifierAmount'] =$unitInfo['Prequal'];
                        $unitInfo['Discount'] =$unitInfo['PreDiscount'];
                        $unitInfo['AdvAmount'] =$unitInfo['PreBAdv'];
                        $unitInfo['BaseAmt'] = $unitInfo['Prebase'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['Preconst'];
                        $unitInfo['GrossAmount'] =$unitInfo['Pregross'];
                        $unitInfo['Rate'] =$unitInfo['PreRate'];
                        $unitInfo['LandAmount'] =$unitInfo['Preland'];
                    }
                    else if($unitInfo['Status'] == 'U') {
                        $unitInfo['BuyerName'] = " ";
                        $unitInfo['OtherCostAmt'] =0;
                        $unitInfo['QualifierAmount'] =$qualifierAmount;
                        $unitInfo['NetAmt'] =$unitInfo['NetAmt']+$qualifierAmount;
                    }
                    else if($unitInfo['Status'] == 'R') {
                        $unitInfo['OtherCostAmt'] =0;
                    }
                    if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
                        $unitInfo['OtherCostAmt'] = $unitInfo['other'];
                        $unitInfo['NetAmt'] = $unitInfo['net'];
                        $unitInfo['QualifierAmount'] =$unitInfo['qual'];
                        $unitInfo['Discount'] =$unitInfo['PostDiscount'];
                        $unitInfo['BaseAmt'] = $unitInfo['base'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['const'];
                        $unitInfo['GrossAmount'] =$unitInfo['gross'];
                        $unitInfo['LandAmount'] =$unitInfo['land'];
                        $unitInfo['Rate'] =$unitInfo['PrevRate'];
                    }
                    else if($unitInfo['count'] == 1) {
                        $unitInfo['OtherCostAmt'] = $unitInfo['BOther'];
                        $unitInfo['NetAmt'] = $unitInfo['BNet'];
                        $unitInfo['QualifierAmount'] =$unitInfo['BQual'];
                        $unitInfo['Discount'] =$unitInfo['BDiscount'];
                        $unitInfo['BaseAmt'] = $unitInfo['BBase'];
                        $unitInfo['ConstructionAmount'] = $unitInfo['BConst'];
                        $unitInfo['GrossAmount'] =$unitInfo['BBase']+ $unitInfo['BOther'];
                        $unitInfo['LandAmount'] =$unitInfo['BLand'];
                        $unitInfo['Rate'] =$unitInfo['B00kRate'];

                    }

                    $this->_view->unitInfo = $unitInfo;

                    // car parking info

                    //  if($unitInfo['TotalCarPark'] != 0) {
                    $select = $sql->select();
                    $select->from('Crm_CarParkTrans')
                        ->where(array('UnitId' => $UnitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrCarParks = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(!empty($arrCarParks)) {
                        $this->_view->arrCarParks = $arrCarParks;
                    }
                    // }
                    //prebooking discount//
                    $select = $sql->select();
                    $select->from('Crm_UnitPreBooking')
                        ->where(array('UnitId' => $UnitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitpreAdvance = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if($unitpreAdvance['UnitId']>0){
                        $this->_view->PreDistype = $unitpreAdvance['DiscountType'];
                        $this->_view->PreDist = $unitpreAdvance['Discount'];
                    }

                    // qualifier split-up
                    if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
                        $subQuery = $sql->select();
                        $subQuery->from(array('a' => 'Crm_PSDPaymentScheduleQualifierTrans'))
                            ->join(array('b' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans'), 'a.PSReceiptTypeTransId=b.ReceiptTypeTransId', array(), $subQuery::JOIN_INNER)
                            ->columns(array('TotAmount' => new Expression("sum(a.NetAmt)"), 'QualifierId'))
                            ->where(array('b.UnitId' => $UnitId))
                            ->group('QualifierId');
                    }
                    else {
                        $subQuery = $sql->select();
                        $subQuery->from(array('a' => 'Crm_PaymentScheduleQualifierTrans'))
                            ->join(array('b' => 'Crm_PaymentScheduleUnitReceiptTypeTrans'), 'a.PSReceiptTypeTransId=b.ReceiptTypeTransId', array(), $subQuery::JOIN_INNER)
                            ->columns(array('TotAmount' => new Expression("sum(a.NetAmt)"), 'QualifierId'))
                            ->where(array('b.UnitId' => $UnitId))
                            ->group('QualifierId');
                    }

                    $select = $sql->select();
                    $select->from(array("g" => $subQuery))
                        ->join(array('b' => 'Proj_QualifierMaster'), 'b.QualifierId=g.QualifierId', array('QualifierName'), $select::JOIN_LEFT);
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrQualifiers = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(!empty($arrQualifiers)) {
                        $this->_view->arrQualifiers = $arrQualifiers;
                    }

                    // check list
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_CheckListProjectTrans'))
                        ->join(array('b' => 'Crm_CheckListMaster'), 'b.CheckListId=a.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
                        ->where(array('a.ProjectId' => $unitInfo['ProjectId'], 'a.DeleteFlag' => 0, 'a.CheckListTypeId' => 1));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrCheckList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


//                    $select = $sql->select();
//                    $select->from(array('a'=>'Crm_CheckListProjectTrans'))
//                        ->columns(array('ProjectId'))
//                        ->join(array('b'=>'Crm_CheckListMaster'),"a.CheckListId=b.CheckListId ",array('CheckListName','CheckListId'),$select::JOIN_INNER)
//                        ->where(array('a.ProjectId' => $unitInfo['ProjectId'], 'a.DeleteFlag' => 0, 'a.CheckListTypeId' => 1));
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $checklists= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                        ->columns(array('*'))
                        ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName','StageId'), $select::JOIN_LEFT)
                        ->where(array('a.StageType' => 'S', 'UnitId' => $unitInfo['UnitId']));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $payStageDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    foreach($payStageDetails as &$pay){
                        $select = $sql->select();
                        $select->from(array('a' =>'KF_stageCompletionTrans'))
                            ->columns(array('StageCompletionId'))
                            ->join(array('b' => 'KF_StageCompletion'), 'a.stageCompletionId=b.stageCompletionId', array(), $select::JOIN_LEFT)
                            ->join(array('c' => 'Crm_ProgressBill'), 'a.stageCompletionId=c.stageCompletionId', array('DemandApproval','ProgressBillId'), $select::JOIN_LEFT)
                            ->where(array("a.UnitId"=>$unitInfo['UnitId'],"b.stageId"=>$pay['StageId']));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $pay['StageDetails'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }
                    $this->_view->payStageDetails=$payStageDetails;

                    //Advance Amount
                    $select = $sql->select();
                    $select->from('Crm_ReceiptRegister')
                        ->columns(array('count' => new Expression("count(ReceiptNo)")))
                        ->where(array('UnitId' => $unitInfo['UnitId'],'CancelId'=>0,'ReceiptAgainst'=>'A','DeleteFlag'=>0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->advAmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $planDiscountValid2=false;
                    $planDiscountValid1=false;

                    if($unitInfo['Status'] == 'S') {
                        //cost sheet
                        if($unitInfo['count']==1){
                            $planDiscountValid2=true;
                            $Bother =$unitInfo['BOther'];
                            $BNet = $unitInfo['BNet'];
                            $BQual =$unitInfo['BQual'];
                            $BDis =$unitInfo['BDiscount'];
                            $BDisType =$unitInfo['BDiscountType'];
                            // Print_r($BDisType);die;
                            $Bbase = $unitInfo['BBase'];
                            $Bconst = $unitInfo['BConst'];
                            $BGross =$unitInfo['BBase']+ $unitInfo['BOther'];
                            $BLand=$unitInfo['BLand'];
                            $BRate=$unitInfo['B00kRate'];

                            if($BDisType=='R'){
                                $BDisType='Rate/Sqft';
                            }else if($BDisType=='L'){
                                $BDisType='Lumpsum';
                            }
                            else if($BDisType=='P'){
                                $BDisType='Percentage';
                            }
                            else{
                                $BDisType='-';
                            }

                        }
                        if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
                            $planDiscountValid1=true;
                            $Pother  = $unitInfo['other'];
                            $PNet  = $unitInfo['net'];
                            $PQual =$unitInfo['qual'];
                            $PDis =$unitInfo['PostDiscount'];
                            $PDisType =$unitInfo['PostDiscountType'];
                            // Print_r($PDisType);die;
                            $Pbase = $unitInfo['base'];
                            $Pconst = $unitInfo['const'];
                            $PGross =$unitInfo['gross'];
                            $PLand =$unitInfo['land'];
                            $PRate =$unitInfo['PRate'];
                            if($PDisType=='R'){
                                $PDisType='Rate/Sqft';
                            }else if($PDisType=='L'){
                                $PDisType='Lumpsum';
                            }
                            else if($PDisType=='P'){
                                $PDisType='Percentage';
                            }
                            else{
                                $PDisType='-';
                            }
                        }
                        $discount =0;
                        //  $area =$unitInfo['UnitArea'];
                        $rate =$unitIn['Rate'];
                        $baseAmt =$unitIn['BaseAmt'];
                        $grossAmt =$unitIn['GrossAmount'];
                        $netAmt =$unitIn['NetAmt'];
                        $otherCostAmt =$unitIn['OtherCostAmt'];
                        $constructionAmount =$unitIn['ConstructionAmount'];
                        $landAmount =$unitIn['LandAmount'];
                        $discountType ='-';




                        $costSheet=array();

                        $this->_view->planDiscountValid2 = $planDiscountValid2;
                        $this->_view->planDiscountValid1 = $planDiscountValid1;
                        if($planDiscountValid1==true && $planDiscountValid2==true){
                            $costSheet['Discount Type']=array($discountType,$BDisType,$PDisType);
                            $costSheet['Discount']=array($viewRenderer->commonHelper()->sanitizeNumber($discount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BDis,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PDis,2,true));
                            $costSheet['other Cost Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bother,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Pother,2,true));
                            $costSheet['Rate']=array($viewRenderer->commonHelper()->sanitizeNumber($rate,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BRate,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PRate,2,true));
                            $costSheet['Base Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bbase,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Pbase,2,true));
                            $costSheet['Gross Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BGross,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PGross,2,true));
                            $costSheet['Net Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($netAmt+$qualifierAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BNet,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PNet,2,true));
                            $costSheet['Construction Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bconst,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Pconst,2,true));
                            $costSheet['Land Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($landAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BLand,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PLand,2,true));
                        } else if($planDiscountValid1==false &&  $planDiscountValid2==true){
                            $costSheet['Discount Type']=array($discountType,$BDisType,'-');
                            $costSheet['Discount']=array($viewRenderer->commonHelper()->sanitizeNumber($discount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BDis,2,true),'-');
                            $costSheet['Rate']=array($viewRenderer->commonHelper()->sanitizeNumber($rate,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BRate,2,true),'-');
                            $costSheet['Base Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bbase,2,true),'-');
                            $costSheet['Gross Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BGross,2,true),'-');
                            $costSheet['Net Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($netAmt+$qualifierAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BNet,2,true),'-');
                            $costSheet['Construction Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bconst,2,true),'-');
                            $costSheet['Land Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($landAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BLand,2,true),'-');
                            $costSheet['otherCost Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bother,2,true),'-');
                        }  else if($planDiscountValid1==false &&  $planDiscountValid2==false){
                            $costSheet['Discount Type']=array($discountType,'-','-');
                            $costSheet['Discount']=array($viewRenderer->commonHelper()->sanitizeNumber($discount,2,true),'-','-');
                            $costSheet['Rate']=array($viewRenderer->commonHelper()->sanitizeNumber($rate,2,true),'-','-');
                            $costSheet['Base Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt,2,true),'-','-');
                            $costSheet['Gross Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt,2,true),'-','-');
                            $costSheet['Net Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($netAmt+$qualifierAmount,2,true),'-','-');
                            $costSheet['Construction Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount,2,true),'-','-');
                            $costSheet['Land Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($landAmount,2,true),'-','-');
                            $costSheet['otherCost Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt,2,true),'-','-');
                        }
                        $this->_view->costSheet=$costSheet;

                        $select = $sql->select();
                        $select->from('Crm_UnitBooking')
                            ->where(array('UnitId' => $unitInfo['UnitId'],'DeleteFlag'=>0));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $unitBooking = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->unitBooking = $unitBooking;
                        $select = $sql->select();
                        $select->from('Crm_PaymentScheduleUnitTrans')
                            ->columns(array("NetAmount"))
                            ->where(array('UnitId' => $unitInfo['UnitId'],'StageType'=>'A'));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $adv = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->adv = $adv['NetAmount'];




                        $select = $sql->select();
                        $select->from('Crm_PostSaleDiscountRegister')
                            ->columns(array('PostSaleDiscountId','BookingId'))
                            ->where(array('BookingId' => $unitBooking['BookingId']))
                            ->order("PostSaleDiscountId desc");
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $unitPostSale = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $postsalecount=count($unitPostSale['BookingId']);
                        //payment schedules
                        $select = $sql->select();
                        $select->from('Crm_PaymentSchedule')
                            ->where(array('ProjectId' => $unitInfo['ProjectId'], 'DeleteFlag' => 0));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        // Receipt Type
                        $select = $sql->select();
                        $select->from('Crm_ReceiptTypeMaster');
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrResults = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->arrReceiptTypes = $arrResults;
                        $arrAllReceiptTypes = array();
                        foreach($arrResults as $result) {
                            $arrAllReceiptTypes[$result['ReceiptTypeId']] = $result['Type'];
                        }
                        if( $postsalecount > 0 ){
                            $select1 = $sql->select();
                            $select1->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                                ->where(array('a.StageType' => 'S', 'a.DistFlag'=>0,'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));

                            $select2 = $sql->select();
                            $select2->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                                ->where(array('a.StageType' => 'D','a.DistFlag'=>0, 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                            $select2->combine($select1,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                                ->where(array('a.StageType' => 'O', 'a.DistFlag'=>0, 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                            $select3->combine($select2,'Union ALL');

                            $select4 = $sql->select();
                            $select4->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                                ->where(array('a.StageType' => 'A', 'a.DistFlag'=>0 ,'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                            $select4->combine($select3,'Union ALL');

                            $select5 = $sql->select();
                            $select5->from(array("g"=>$select4))
                                ->columns(array('*'))
                                ->where(array('BookingId' => $unitBooking['BookingId']))
                                ->order("g.SortId ASC");
                       $stmt = $sql->getSqlStringForSqlObject($select5);
                            $stmt = $sql->getSqlStringForSqlObject($select5);
                            $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            if(!empty($arrPaymentScheduleDetails)) {

                                foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                                    // receipt type
                                    $select1 = $sql->select();
                                    $select1->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                        ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                                        ->where( array( 'b.ReceiptType' => 'S','a.DistFlag'=>0, 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PSDPaymentScheduleUnitTransId' ] ) );

                                    $select2 = $sql->select();
                                    $select2->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                        ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                                        ->where( array( 'a.ReceiptType' => 'D','a.DistFlag'=>0, 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PSDPaymentScheduleUnitTransId' ]) );
                                    $select2->combine( $select1, 'Union ALL' );

                                    $select3 = $sql->select();
                                    $select3->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                        ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                                        ->where( array( 'a.ReceiptType' => 'O','a.DistFlag'=>0, 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PSDPaymentScheduleUnitTransId' ] ) );
                                    $select3->combine( $select2, 'Union ALL' );

                                    $select4 = $sql->select();
                                    $select4->from( array( "g" => $select3 ) )
                                        ->columns( array( '*' ) )
                                        ->order("g.ReceiptTypeTransId ASC");
                                    $stmt = $sql->getSqlStringForSqlObject( $select4 );
                                    $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    if(!empty($arrReceiptTypes)) {

                                        $iQualCount = 0;
                                        foreach($arrReceiptTypes as &$receipt) {

                                            switch($receipt['ReceiptType']) {
                                                case 'O':
                                                    $receipt['Type'] = 'O';
                                                    break;
                                                case 'S':
                                                    $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                                    break;
                                            }

                                            // qualifier
                                            $select = $sql->select();
                                            $select->from( array( 'a' => 'Crm_PSDPaymentScheduleQualifierTrans' ) )
                                                ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                                    'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                                    'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                                ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                                            $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                                            $statement = $sql->getSqlStringForSqlObject( $select );
                                            $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                            if ( !empty( $qualList ) ) {
                                                foreach($qualList as &$qual) {
                                                    $qual['BaseAmount'] = $receipt['Amount'];
                                                }

                                                $sHtml = Qualifier::getQualifier( $qualList );
                                                $iQualCount = $iQualCount + 1;
                                                $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                                $receipt[ 'qualHtmlTag' ] = $sHtml;

                                            }

                                        }

                                        $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                                    }
                                }
                            }
                        }
                        else{

                            // current payment schedule detail
                            $select1 = $sql->select();
                            $select1->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                                ->where(array('a.StageType' => 'S', 'BookingId' => $unitBooking['BookingId']));

                            $select2 = $sql->select();
                            $select2->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                                ->where(array('a.StageType' => 'D', 'BookingId' => $unitBooking['BookingId']));
                            $select2->combine($select1,'Union ALL');

                            $select3 = $sql->select();
                            $select3->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                                ->where(array('a.StageType' => 'O', 'BookingId' => $unitBooking['BookingId']));
                            $select3->combine($select2,'Union ALL');

                            $select4 = $sql->select();
                            $select4->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                ->columns(array('*'))
                                ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                                ->where(array('a.StageType' => 'A', 'BookingId' => $unitBooking['BookingId']));
                            $select4->combine($select3,'Union ALL');

                            $select6 = $sql->select();
                            $select6->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                                ->columns(array('*' ))
                                ->join(array('b' => 'Crm_UnitBooking'), 'a.BookingId = b.BookingId', array('StageName' => new Expression("a.TermDescription")), $select4::JOIN_LEFT)
                                ->where(array('a.StageType' => 'C', 'a.BookingId' => $unitBooking['BookingId']));
                            $select6->combine($select4,'Union ALL');

                            $select5 = $sql->select();
                            $select5->from(array("g"=>$select6))
                                ->columns(array('*'))
                                ->where(array('BookingId' => $unitBooking['BookingId']))
                                ->order("g.SortId ASC");
                            $stmt = $sql->getSqlStringForSqlObject($select5);
                            $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            if(!empty($arrPaymentScheduleDetails)) {

                                foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                                    // receipt type
                                    $select1 = $sql->select();
                                    $select1->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                        ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                                        ->where( array( 'b.ReceiptType' => 'S', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                                    $select2 = $sql->select();
                                    $select2->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                        ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                                        ->where( array( 'a.ReceiptType' => 'D', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                                    $select2->combine( $select1, 'Union ALL' );

                                    $select3 = $sql->select();
                                    $select3->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                        ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                                        ->where( array( 'a.ReceiptType' => 'O', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                                    $select3->combine( $select2, 'Union ALL' );

                                    $select4 = $sql->select();
                                    $select4->from( array( "g" => $select3 ) )
                                        ->columns( array( '*' ) )
                                        ->order("g.ReceiptTypeTransId ASC");

                                    $stmt = $sql->getSqlStringForSqlObject( $select4 );
                                    $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                    if(!empty($arrReceiptTypes)) {

                                        $iQualCount = 0;
                                        foreach($arrReceiptTypes as &$receipt) {

                                            switch($receipt['ReceiptType']) {
                                                case 'O':
                                                    $receipt['Type'] = 'O';
                                                    break;
                                                case 'S':
                                                    $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                                    break;
                                            }

                                            // qualifier
                                            $select = $sql->select();
                                            $select->from( array( 'a' => 'Crm_PaymentScheduleQualifierTrans' ) )
                                                ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                                    'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                                    'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                                ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                                            $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                                            $statement = $sql->getSqlStringForSqlObject( $select );
                                            $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                            if ( !empty( $qualList ) ) {
                                                foreach($qualList as &$qual) {
                                                    $qual['BaseAmount'] = $receipt['Amount'];
                                                }

                                                $sHtml = Qualifier::getQualifier( $qualList );
                                                $iQualCount = $iQualCount + 1;
                                                $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                                $receipt[ 'qualHtmlTag' ] = $sHtml;

                                            }

                                        }

                                        $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                                    }
                                }
                            }
                        }

                        $this->_view->arrPaymentScheduleDetails = $arrPaymentScheduleDetails;



                        // extra items
                        $subQuery = $sql->select();
                        $subQuery->from(array("a" => "Crm_ExtraBillTrans"))
                            ->join(array("b" => "Crm_ExtraBillRegister"), "a.ExtraBillRegisterId=b.ExtraBillRegisterId", array(), $subQuery::JOIN_INNER)
                            ->columns(array('ExtraItemId'))
                            ->where(array('b.UnitId'=>$UnitId));

                        $select = $sql->select();
                        $select->from(array("a" => "Crm_UnitExtraItemTrans"))
                            ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array('ItemDescription', 'Code'), $select::JOIN_LEFT)
                            ->join(array("c" => "Proj_UOM"), "c.UnitId=b.MUnitid", array('UnitName'), $select::JOIN_LEFT)
                            ->where(array('a.UnitId' => $UnitId))
                            ->where->expression('a.ExtraItemId Not IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arrExtraItemList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(!empty($arrExtraItemList)) {
                            $this->_view->arrExtraItemList = $arrExtraItemList;
                        }

                        // Buyer statement
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ProgressBillTrans'))
                            ->columns(array('BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"),'QualAmount', 'PBNo','Amount' ,'BillAmount'=>new Expression("a.QualAmount + a.Amount"),'PaidAmount','Balance' => new Expression('Case When (a.QualAmount + a.Amount)-a.PaidAmount > 0 then (a.QualAmount + a.Amount)-a.PaidAmount else 0 end'),
                                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
                            // ->join(array("f"=>"KF_StageCompletion"), "a.StageId=f.StageId", array(), $select::JOIN_LEFT)
                            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
                            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT)
                            ->where(array("a.UnitId" => $UnitId,'a.CancelId'=>0))
                            ->order('a.PBNo asc');
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrBuyerStmt1 = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(!empty($arrBuyerStmt1)) {
                            $this->_view->arrBuyerStmt1 = $arrBuyerStmt1;
                        }

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array('ExcessAmount','ReceiptId'))
                            ->where(array("a.UnitId" => $UnitId,'ReceiptAgainst'=>'B','CancelId'=>'0'))
                            ->order('a.ReceiptId desc');
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrexcess1 = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if(count($arrexcess1['ExcessAmount'])>0) {

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_PaymentVoucher'))
                                ->columns(array('ExcessAmount' => new expression('SUM(ExcessAmount)'),'PaymentVoucherId'))
                                ->where(array("a.UnitId" => $UnitId))
                                ->group('PaymentVoucherId');
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $arrpayment1 = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($arrpayment1['PaymentVoucherId']) > 0) {
                                if ($arrpayment1['ExcessAmount'] >= $arrexcess1['ExcessAmount']){

                                    $arrexcess1=0;

                                }
                                else if ($arrpayment1['ExcessAmount'] < $arrexcess1['ExcessAmount']){
                                    $arrexcess1=$arrexcess1['ExcessAmount']-$arrpayment1['ExcessAmount'];
                                }
                                else{
                                    $arrexcess1=$arrexcess1['ExcessAmount'];
                                }
                            }
                            else{
                                $arrexcess1=$arrexcess1['ExcessAmount'];
                            }


                        }

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns(array('Amount'))
                            ->where(array("UnitId" => $UnitId,"StageType"=>'A'));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $amountadvance1 = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_UnitDetails'))
                            ->columns(array('AdvAmount'))
                            ->where(array("UnitId" => $UnitId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $unitadvance1 = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptRegister'))
                            ->columns(array('Amount' => new expression('SUM(Amount)')))
                            ->where(array("UnitId" => $UnitId,'a.CancelId'=>0,'a.DeleteFlag' => 0,"ReceiptAgainst"=>'A'));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $receiptAgainst1 = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array("a" => "Crm_ReceiptRegister"))
                            ->columns(array("Amount"))
                            ->where(array('a.DeleteFlag' => 0, 'a.CancelId'=>0,'a.UnitId' => $UnitId,'ReceiptAgainst'=>'A'));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $arrpaid1 = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

                        $this->_view->unitadvance1 = $unitadvance1;
                        $this->_view->amountadvance1 = $amountadvance1;
                        $this->_view->receiptAgainst1 = $receiptAgainst1;
                        $this->_view->arrexcess1 = $arrexcess1;

                        $select = $sql->select();
                        $select->from(array("a"=>"Crm_ExtraBillRegister"))
                            ->columns( array( 'BillAmount' => new Expression("Sum(a.Amount)"),'NetAmount'=> new Expression("Sum(a.NetAmount)"),'QualAmount'=> new Expression("Sum(a.QualAmount)"),
                                'PaidAmount'=>new Expression("Sum(a.PaidAmount)")))
                            //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId  and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select1::JOIN_LEFT)
                            ->where("a.UnitId=$UnitId")
                            ->where("a.CancelId=0");
                        // $select->group(new Expression('a.ExtraBillDate,a.ExtraBillRegisterId,a.ExtraBillNo,a.NetAmount,a.amount,a.QualAmount'));
                       $stmt = $sql->getSqlStringForSqlObject($select);
                        $this->_view->extraamt1  = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
                        if(!empty($arrpaid)) {
                            $this->_view->arrpaid1 = $arrpaid1;
                        }




                        // selected check list
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_FinalisationCheckListTrans'))
                            ->columns(array('BookingId','ExecutiveId'))
                            ->join(array('b' => 'WF_Users'), 'b.UserId=a.ExecutiveId', array('ExecutiveName' => 'EmployeeName','Date1' => new Expression("CONVERT(varchar(10),a.SubmittedDate,105) ")), $select::JOIN_LEFT)
                            ->join(array('c' => 'Crm_CheckListMaster'), 'c.CheckListId=a.CheckListId', array('CheckListName','CheckListId'), $select::JOIN_LEFT)
                            ->where(array('BookingId' => $unitBooking['BookingId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arrSelectedCheckLists = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //all executive details
                        $select = $sql->select();
                        $select->from('WF_Users')
                            ->columns(array('data' => 'UserId', 'value' => 'EmployeeName'))
                            ->where(array('DeleteFlag' => 0));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arrExecutives = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        /*foreach($arrCheckList as &$checkList) {
                            $isFound = FALSE;
                            foreach($arrSelectedCheckLists as $selCheckList) {
                                if($checkList['CheckListId'] == $selCheckList['CheckListId']) {
                                    $isFound = TRUE;
                                    $submittedDate = NULL;
                                    if(strtotime($selCheckList['SubmittedDate']) != FALSE) {
                                        $submittedDate = date('d-m-Y', strtotime($selCheckList['SubmittedDate']));
                                    }
                                    $checkList['Checked'] = 'Checked';
                                    $checkList['SubmittedDate'] = $submittedDate;
                                    $checkList['ExecutiveName'] = $selCheckList['ExecutiveName'];
                                    $checkList['ExecutiveId'] = $selCheckList['ExecutiveId'];
                                    break;
                                }
                            }
                            if(!$isFound) {
                                $checkList['Checked'] = '';
                            }
                        }*/
                        //handingover

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_HandingoverCheckTrans'))
                            ->join(array('b' => 'WF_Users'), 'b.UserId=a.ExecutiveId', array('ExecutiveName' => 'EmployeeName', 'Date1' => new Expression("CONVERT(varchar(10),a.Date,105) ")), $select::JOIN_LEFT)
                            ->join(array('c' => 'Crm_CheckListMaster'), 'c.CheckListId=a.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
                            ->where(array('a.UnitId' => $UnitId,'a.IsChecked' => '1'));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arrSelHandCheck = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        // $this->_view->arrReceipts = '';
                        // receipts
                        $select = $sql->select();
                        $select->from(array("a" => "Crm_ReceiptRegister"))
                            ->columns(array("ReceiptId", "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"),
                                "ReceiptAgainst"=> new Expression("Case When ReceiptAgainst ='B' then 'Bill/Schedule' When ReceiptAgainst ='A' then 'Advance' When ReceiptAgainst='L' then 'LateInterest' When ReceiptAgainst ='O' then 'Others' When ReceiptAgainst ='P' then 'Pre-Booking' else 'N/A 'end") ,"Amount","ReceiptMode"))
                            ->where(array('a.DeleteFlag' => 0,'a.CancelId'=>0, 'a.UnitId' => $UnitId));
                        $statement = $sql->getSqlStringForSqlObject( $select );
                        $this->_view->arrReceipts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    }

                    $this->_view->arrCheckList = $arrCheckList;

                    // get documents
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_UnitDetailsDocument"))
                        ->where(array('a.UnitId' => $UnitId));
                    $statement = $sql->getSqlStringForSqlObject( $select );
                    $arrDocuments = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(!empty($arrDocuments))
                        $this->_view->arrDocuments = $arrDocuments;

                    // get kickoff documents
                    $subQuery = $sql->select();
                    $subQuery->from("Proj_ProjectMaster")
                        ->columns(array('KickoffId'))
                        ->where(array('ProjectId' => $unitInfo['ProjectId']));

                    $select = $sql->select();
                    $select->from('KF_Documents')
                        ->columns(array('URL', 'DocumentName', 'DocumentType'))
                        ->where->expression('KickoffId IN ?', array($subQuery));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $arrKickoffDocuments  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(!empty($arrKickoffDocuments))
                        $this->_view->arrKickoffDocuments = $arrKickoffDocuments;

                    //agreement Document

                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_UnitDocuments'))
                        ->join(array('b' => 'Crm_AgreementTypeMaster'), 'b.AgreementTypeId=a.AgreementId', array('AgreementTypeName'), $select::JOIN_LEFT)
                        ->columns(array('TemplatePath','DocId','UnitId'))
                        ->where(array("UnitId"=>$UnitId,'DeleteFlag'=>0));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->agreementDoc  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    // Unit Other Cost
                    $select = $sql->select();
                    $select->from(array('a' => 'CRM_FinalisationOtherCostTrans'))
                        ->join(array('b' => 'CRM_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                        ->where(array('a.UnitId' => $UnitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrUnitOtherCost = $arrUnitOtherCost = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->UnitId=$UnitId;



                    //statementOFAccount or buyer Statement

                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_ReceiptRegister'))
                        ->columns(array('ExcessAmount','ReceiptId'))
                        ->where(array("a.UnitId" => $UnitId,'ReceiptAgainst'=>'B','CancelId'=>'0'))
                        ->order('a.ReceiptId desc');
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrexcess = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(count($arrexcess['ExcessAmount'])>0) {

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PaymentVoucher'))
                            ->columns(array('ExcessAmount' => new expression('SUM(ExcessAmount)'),'PaymentVoucherId'))
                            ->where(array("a.UnitId" => $UnitId))
                            ->group('PaymentVoucherId');
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrpayment = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if (count($arrpayment['PaymentVoucherId']) > 0) {
                            if ($arrpayment['ExcessAmount'] >= $arrexcess['ExcessAmount']){

                                $arrexcess=0;

                            }
                            else if ($arrpayment['ExcessAmount'] < $arrexcess['ExcessAmount']){
                                $arrexcess=$arrexcess['ExcessAmount']-$arrpayment['ExcessAmount'];
                            }
                            else{
                                $arrexcess=$arrexcess['ExcessAmount'];
                            }
                        }
                        else{
                            $arrexcess=$arrexcess['ExcessAmount'];
                        }


                    }


                    $this->_view->arrexcess = $arrexcess;
                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_UnitBooking'))
                        ->join(array("b" => "Crm_Leads"), "a.LeadId=b.LeadId", array('LeadName','LeadId'), $select::JOIN_LEFT)
                        ->join(array("d" => "Crm_UnitDetails"), "a.UnitId=d.UnitId", array('Rate'), $select::JOIN_LEFT)
                        ->join(array("e" => "KF_UnitMaster"), "a.UnitId=e.UnitId", array('UnitArea','UnitNo','UnitTypeId'), $select::JOIN_LEFT)
                        ->join(array("f" => "Proj_ProjectMaster"), "e.ProjectId=f.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
                        ->join(array("g" => "Crm_UnitTypeMaster"), "e.UnitTypeId=g.UnitTypeId", array('UnitTypeName'), $select::JOIN_LEFT)
                        ->columns(array("BookingDate","DeleteFlag"))
                        ->where(array('a.UnitId' => $UnitId,'a.DeleteFlag'=>0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitBook = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(empty($unitBook)) {
                        throw new \Exception('Invalid Statement of Account!');
                    }

                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_LeadAddress'))
                        ->columns(array("Address1"))
                        ->where(array('a.LeadId' => $unitBook['LeadId'],'a.AddressType'=>"P"));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $leadAddress = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //Extra Bill
                    $select = $sql->select();
                    $select->from(array("a"=>"Crm_ExtraBillRegister"))
                        ->columns( array( 'BillAmount' => new Expression("Sum(a.Amount)"),'NetAmount'=> new Expression("Sum(a.NetAmount)"),'QualAmount'=> new Expression("Sum(a.QualAmount)"),
                            'PaidAmount'=>new Expression("Sum(a.PaidAmount)")))
                        //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId  and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select1::JOIN_LEFT)
                        ->where("a.UnitId=$UnitId")
                        ->where("a.CancelId=0");
                    // $select->group(new Expression('a.ExtraBillDate,a.ExtraBillRegisterId,a.ExtraBillNo,a.NetAmount,a.amount,a.QualAmount'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->extraamt  = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->Current();

                    $select = $sql->select();
                    $select->from(array("a"=>"Crm_ExtraBillRegister"))
                        ->columns( array( 'ExtraBillRegisterId'))
                        //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId  and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select1::JOIN_LEFT)
                        ->where("a.UnitId=$UnitId")
                        ->where("a.CancelId=0");
                    // $select->group(new Expression('a.ExtraBillDate,a.ExtraBillRegisterId,a.ExtraBillNo,a.NetAmount,a.amount,a.QualAmount'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $extraArr  = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($extraArr as &$eArr) {
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                            ->join(array("b" => "Crm_ReceiptAdjustment"), new expression("a.ReceiptAdjId=b.ReceiptAdjId"), array(), $select::JOIN_LEFT)
                            ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array('ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode'), $select::JOIN_LEFT)
                            ->columns(array('Amount', 'QualAmount', 'NetAmount'))
                            ->where(array('b.ExtraBillRegisterId'=>$eArr['ExtraBillRegisterId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $eArr['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    }

                    $this->_view->extraArr=$extraArr;
                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_ReceiptAdjustment'))
                        ->columns(array('Amount' ,'QualAmount','NetAmount','stageId'))
                        ->where(array('a.UnitId' => $UnitId))
                        ->where("a.StageType='O'");
                    $select->where(array("a.StageId"=>'2'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrregcons = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_ReceiptRegister'))
                        ->columns(array('Amount'=>new expression("SUM(Amount)")))
                        ->where(array('a.UnitId' => $UnitId))
                        ->where(array('a.DeleteFlag' =>0))
                        ->where("a.ReceiptAgainst='L'");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrrlate = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_ReceiptRegister'))
                        ->columns(array('*'))
                        ->where(array('a.UnitId' => $UnitId))
                        ->where(array('a.DeleteFlag' =>0))
                        ->where("a.ReceiptAgainst='L'");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrrlis = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_ProgressBillTrans'))
                        ->columns(array('Amount'=>new expression("SUM(LateFee)")))
                        ->where(array('a.UnitId' => $UnitId));

                   $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrrtrans = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();



                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_ReceiptAdjustment'))
                        ->columns(array('Amount' ,'QualAmount','NetAmount','stageId'))
                        ->where(array('a.UnitId' => $UnitId))
                        ->where("a.StageType='O'");
                    $select->where(array("a.StageId"=>'1'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrregland = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0){
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                            ->columns(array('PostSaleDiscountId'))
                            ->where(array('a.UnitId' => $UnitId))
                        ->order('PostSaleDiscountId desc');
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $stage1 = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                            ->join(array("e" => "Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $select::JOIN_LEFT)
                            ->join(array("f" => "Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $select::JOIN_LEFT)
                            ->join(array("g" => "KF_StageMaster"), NEW Expression("a.StageId=g.StageId"), array(), $select::JOIN_LEFT)
                            ->columns(array(new Expression("Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
								When a.StageType='D' then e.DescriptionName When a.StageType='A' then 'Booking Advance' end as StageName"), "PSDPaymentScheduleUnitTransId", "StageId", "StageType", "Amount", "QualAmount"))
                            ->where(array('a.UnitId' => $UnitId))
                            ->where(array('a.PostSaleDiscountId' => $stage1['PostSaleDiscountId']));
                          $stmt = $sql->getSqlStringForSqlObject($select);
                        $stageList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($stageList as &$stage) {


                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans'))
                                ->join(array("b" => "crm_ReceiptTypeMaster"), "a.ReceiptTypeId=b.ReceiptTypeId", array(), $select::JOIN_LEFT)
                                ->columns(array('DueAmount' => new Expression("Sum(a.NetAmount)"), 'GrossAmount' => new Expression("Sum(a.Amount)"), 'QualAmount' => new Expression("Sum(a.QualAmount)")))
                                ->where(array('a.PSDPaymentScheduleUnitTransId' => $stage['PSDPaymentScheduleUnitTransId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $stage['Type'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if ($stage['StageId'] == '1' && $stage['StageType'] == 'O') {
                                $stage['Type']['QualAmount'] = $stage['QualAmount'];
                                $stage['Type']['DueAmount'] = $stage['QualAmount'] + $stage['Amount'];
                                $stage['Type']['GrossAmount'] = $stage['QualAmount'] + $stage['Amount'];


                            }
                            if ($stage['StageId'] == '2' && $stage['StageType'] == 'O') {
                                $stage['Type']['QualAmount'] = $stage['QualAmount'];
                                $stage['Type']['DueAmount'] = $stage['QualAmount'] + $stage['Amount'];
                                $stage['Type']['GrossAmount'] = $stage['QualAmount'] + $stage['Amount'];

                            }


                            if ($stage['StageType'] != "A") {
                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                                    ->join(array("b" => "Crm_ReceiptAdjustment"), "a.ReceiptAdjId=b.ReceiptAdjId", array(), $select::JOIN_LEFT)
                                    ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array('ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode'), $select::JOIN_LEFT)
                                    ->columns(array('Amount', 'QualAmount', 'NetAmount'))
                                    ->where(array('b.StageId' => $stage['StageId'], 'b.StageType' => $stage['StageType'], 'c.UnitId' => $UnitId, 'c.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                                    ->join(array("b" => "Crm_ReceiptAdjustment"), "a.ReceiptAdjId=b.ReceiptAdjId", array(), $select::JOIN_LEFT)
                                    ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array(), $select::JOIN_LEFT)
                                    ->columns(array('Amt' => new Expression("Sum(a.NetAmount)")))
                                    ->where(array('b.StageId' => $stage['StageId'], 'b.StageType' => $stage['StageType'], 'c.UnitId' => $UnitId, 'c.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['TotPaid'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                            } else {

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptRegister'))
                                    ->columns(array('receiptId', 'ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode', 'Amount', 'QualAmount', 'NetAmount'))
                                    ->where(array('a.CancelId' => 0, 'a.UnitId' => $UnitId, 'a.ReceiptAgainst' => 'A', 'a.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptRegister'))
                                    ->columns(array('Amt' => new Expression("Sum(a.Amount)")))
                                    ->where(array('a.CancelId' => 0, 'a.UnitId' => $UnitId, 'a.ReceiptAgainst' => 'A', 'a.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['TotPaid'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            }

                        }
                    }
                    else {

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->join(array("e" => "Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $select::JOIN_LEFT)
                            ->join(array("f" => "Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $select::JOIN_LEFT)
                            ->join(array("g" => "KF_StageMaster"), NEW Expression("a.StageId=g.StageId"), array(), $select::JOIN_LEFT)
                            ->columns(array(new Expression("Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
								When a.StageType='D' then e.DescriptionName When a.StageType='A' then 'Booking Advance' end as StageName"), "PaymentScheduleUnitTransId", "StageId", "StageType", "Amount", "QualAmount"))
                            ->where(array('a.UnitId' => $UnitId))
                            ->order("PaymentScheduleUnitTransId asc");
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $stageList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($stageList as &$stage) {


                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans'))
                                ->join(array("b" => "crm_ReceiptTypeMaster"), "a.ReceiptTypeId=b.ReceiptTypeId", array(), $select::JOIN_LEFT)
                                ->columns(array('DueAmount' => new Expression("Sum(a.NetAmount)"), 'GrossAmount' => new Expression("Sum(a.Amount)"), 'QualAmount' => new Expression("Sum(a.QualAmount)")))
                                ->where(array('a.PaymentScheduleUnitTransId' => $stage['PaymentScheduleUnitTransId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $stage['Type'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if ($stage['StageId'] == '1' && $stage['StageType'] == 'O') {
                                $stage['Type']['QualAmount'] = $stage['QualAmount'];
                                $stage['Type']['DueAmount'] = $stage['QualAmount'] + $stage['Amount'];
                                $stage['Type']['GrossAmount'] = $stage['QualAmount'] + $stage['Amount'];


                            }
                            if ($stage['StageId'] == '2' && $stage['StageType'] == 'O') {
                                $stage['Type']['QualAmount'] = $stage['QualAmount'];
                                $stage['Type']['DueAmount'] = $stage['QualAmount'] + $stage['Amount'];
                                $stage['Type']['GrossAmount'] = $stage['QualAmount'] + $stage['Amount'];

                            }


                            if ($stage['StageType'] != "A") {
                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                                    ->join(array("b" => "Crm_ReceiptAdjustment"), "a.ReceiptAdjId=b.ReceiptAdjId", array(), $select::JOIN_LEFT)
                                    ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array('ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode'), $select::JOIN_LEFT)
                                    ->columns(array('Amount', 'QualAmount', 'NetAmount'))
                                    ->where(array('b.StageId' => $stage['StageId'], 'b.StageType' => $stage['StageType'], 'c.UnitId' => $UnitId, 'c.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                                    ->join(array("b" => "Crm_ReceiptAdjustment"), "a.ReceiptAdjId=b.ReceiptAdjId", array(), $select::JOIN_LEFT)
                                    ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array(), $select::JOIN_LEFT)
                                    ->columns(array('Amt' => new Expression("Sum(a.NetAmount)")))
                                    ->where(array('b.StageId' => $stage['StageId'], 'b.StageType' => $stage['StageType'], 'c.UnitId' => $UnitId, 'c.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['TotPaid'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                            } else {

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptRegister'))
                                    ->columns(array('receiptId', 'ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode', 'Amount', 'QualAmount', 'NetAmount'))
                                    ->where(array('a.CancelId' => 0, 'a.UnitId' => $UnitId, 'a.ReceiptAgainst' => 'A', 'a.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $select = $sql->select();
                                $select->from(array('a' => 'Crm_ReceiptRegister'))
                                    ->columns(array('Amt' => new Expression("Sum(a.Amount)")))
                                    ->where(array('a.CancelId' => 0, 'a.UnitId' => $UnitId, 'a.ReceiptAgainst' => 'A', 'a.LeadId' => $unitBook['LeadId']));
                                $stmt = $sql->getSqlStringForSqlObject($select);
                                $stage['TotPaid'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            }

                        }
                    }

                    $this->_view->unitBook=$unitBook;
                    $this->_view->leadAddress=$leadAddress;
                    $this->_view->stageList=$stageList;
                    $this->_view->arrlate=$arrrlate;
                    $this->_view->arrrtrans=$arrrtrans;
                    // Print_r($stageList);die;

//                    $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

                } catch(\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }
            }
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    function generateDownloadPdf($arrUnitDetails) {
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $pdfHtml = <<<EOT
        <br> {$arrUnitDetails['UnitNo']} <br>
EOT;
        return $pdfHtml;
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function generateProposalPdf($unitProposal) {

        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");

        $ProposalDate = date('d-m-Y', strtotime($unitProposal['ProposalNo']));
        $pdfHtml = <<<EOT
		<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt</title>

    <style>
    .invoice-box{
        max-width:800px;
        margin:auto;
        padding:10px;
        border:1px solid #eee;
        box-shadow:0 0 10px rgba(0, 0, 0, .15);
        font-size:16px;
        line-height:24px;
        color:#555;
		background:#e2ebef;
    }

    .invoice-box table{
        width:100%;
        text-align:left;
		background:#fff !important;
		padding:10px;
    }

    .invoice-box table td{
        padding:5px;
        vertical-align:top;
    }

    .invoice-box table tr td:nth-child(2){
        text-align:right;
    }

    .invoice-box table tr.heading td{
        background:#eee;
        border-bottom:1px solid #ddd;
        font-weight:bold;
		font-size:14px;
    }
    .invoice-box table tr.item td{
        border-bottom:1px solid #eee;
		font-size:14px;
    }

    .invoice-box table tr.item.last td{
        border-bottom:none;
    }

    .invoice-box table tr.total td:nth-child(2){
        border-top:2px solid #eee;
        font-weight:bold;
    }
    </style>
</head>

<body>
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="mainheading">
                <td colspan="2" class="" style="color:#244996; font-size:25px; font-weight:500; text-align:center; padding:10px 0px 22px 0px;">Unit Proposal</td>
            </tr>
            <tr class="heading">
                <td >
                    <div><span>Proposal No : </span> &nbsp; <span>{$unitProposal['ProposalNo']}</span></div>
                </td>

                <td>
                     <div><span>Date : </span> &nbsp; <span>{$ProposalDate}</span></div>
                </td>
            </tr>
            <tr class="item">
                <td>
                    Project Name
                </td>

                <td>
                    {$unitProposal['ProjectName']}
                </td>
            </tr>
            <tr class="item">
                <td>
                    Unit No
                </td>

                <td>
                    {$unitProposal['UnitName']}
                </td>
            </tr>
            <tr class="item">
                <td>
                    Buyer Name
                </td>

                <td>
                    {$unitProposal['LeadName']}
                </td>
            </tr>
            <tr class="heading">
                <td colspan="2">
                    Unit Detail
                </td>
            </tr>

            <tr class="item">
                <td>
                    Block Name
                </td>

                <td>
                    {$unitProposal['BlockName']}
                </td>
            </tr>

            <tr class="item">
                <td>
                    Level
                </td>

                <td>
                     {$unitProposal['FloorName']}
                </td>
            </tr>

            <tr class="item">
                <td>
                    Area
                </td>

                <td>
                    {$unitProposal['Area']}
                </td>
            </tr>
            <tr class="item">
                <td>
                    Rate
                </td>

                <td>
                    {$viewRenderer->commonHelper()->sanitizeNumber($unitProposal['Rate'], 2, TRUE)}
                </td>
            </tr>
            <tr class="item">
                <td>
                    Base Amount
                </td>

                <td>
                    {$viewRenderer->commonHelper()->sanitizeNumber($unitProposal['BaseAmt'], 2, TRUE)}
                </td>
            </tr>
EOT;

        if($unitProposal['OtherCostAmt'] != 0) {
            $pdfHtml .= <<<EOT
            <tr class="item">
                <td>
                    Other Cost
                </td>

                <td>
                    {$viewRenderer->commonHelper()->sanitizeNumber($unitProposal['OtherCostAmt'], 2, TRUE)}
                </td>
            </tr>
EOT;
        }

        $pdfHtml .= <<<EOT
            <tr class="total">
                <td colspan="2" style="color:#098833 !important; text-align:right;">
                   Net Amount : {$viewRenderer->commonHelper()->sanitizeNumber($unitProposal['NetAmt'], 2, TRUE)}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
EOT;

        return $pdfHtml;
    }


    public function stagecompletionAction() {

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
        $userId = $this->auth->getIdentity()->UserId;
        $this->_view->project = $this->params()->fromRoute('ProjectId');
        $this->_view->unitId = $this->params()->fromRoute('UnitId');
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
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
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $postData = $request->getPost();

                    $stageCompletionNo = $this->bsf->isNullCheck($postData['StageCompletionNo'], 'string' );
                    $ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number' );
                    $BlockId = $this->bsf->isNullCheck($postData['BlockId'], 'number' );
                    $FloorId = $this->bsf->isNullCheck($postData['FloorId'], 'number' );
                    $arrUnits = $postData['Units'];
                    $CompletionDate = date('Y-m-d', strtotime($postData['CompletionDate']));
                    $StageType = $this->bsf->isNullCheck($postData['StageType'], 'string');
                    $StageId = $this->bsf->isNullCheck($postData['StageId'], 'number');
                    $DueDate = date('Y-m-d', strtotime($postData['DueDate']));
                    $ProgressBill = $this->bsf->isNullCheck($postData['ProgressBill'], 'number');

                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $UnitWise = 0;
                    if(is_array($arrUnits) && !empty($arrUnits)) {
                        $UnitWise = 1;
                    }

                    $sVno= $stageCompletionNo;

                    $aVNo = CommonHelper::getVoucherNo(801, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                    if ($aVNo["genType"] == true) $sVno = $aVNo["voucherNo"];

                    $files = $request->getFiles();


                    $insert = $sql->insert();
                    $insert->into('KF_StageCompletion')
                        ->values(array(
                            'StageCompletionNo' => $sVno,
                            'ProjectId' => $ProjectId,
                            'BlockId' =>$BlockId,
                            'FloorId' => $FloorId,
                            'StageType' => $StageType,
                            'StageId' => $StageId,
                            'CompletionDate' => $CompletionDate,
                            'DueDate' => $DueDate,
                            'UnitWise' => $UnitWise,
                            'CreatedDate' => date('Y-m-d H:i:s')));
                    $stmt = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    $stageCompletionId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    $delete = $sql->delete();
                    $delete->from('KF_StageImageTrans')
                        ->where(array('StageCompletionId'=>$stageCompletionId));
                    $DelStatement = $sql->getSqlStringForSqlObject($delete);
                    $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach($files["files"] as $index => $file) {

                        $fileTempName = $file['tmp_name'];

                        if(!empty($file['error'][$index])) {
                            return false;
                        }

                        if(!empty($fileTempName) && is_uploaded_file($fileTempName)) {
                            $url = "public/uploads/crm/stageImg/";
                            $filename = $this->bsf->uploadFile($url, $file);
                            if ($filename) {
                                // update valid files only
                                $url = 'uploads/crm/stageImg/' . $filename;

                                $insert  = $sql->insert('KF_StageImageTrans');
                                $newData = array(
                                    'StageCompletionId'=>$stageCompletionId,
                                    'ImageUrl' =>$url
                                );
                                $insert->values($newData);
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }
                        }
                    }
                    $subQuery = '';
                    if(empty($arrUnits)) {
                        $arrWhere = array('ProjectId' => $ProjectId);
                        if($BlockId != 0) {
                            $arrWhere['BlockId'] = $BlockId;
                        }

                        if($FloorId != 0) {
                            $arrWhere['FloorId'] = $FloorId;
                        }

                        $subQuery = $sql->select();
                        $subQuery->from('KF_UnitMaster')
                            ->columns(array('UnitId'))
                            ->where($arrWhere);
                    } else {
                        $subQuery = implode(',', $arrUnits);
                    }

//                    $this->_view->Units = $arrProjects;

                    $update = $sql->update();
                    $update->table('Crm_PaymentScheduleUnitTrans')
                        ->set(array('StageCompleted' => 1,'StageCompletionId'=>$stageCompletionId))
                        ->where(array('StageType' => $StageType, 'StageId' => $StageId));

                    if(empty($arrUnits)) {
                        $update->where->expression('UnitId IN ?', array($subQuery));
                    } else {
                        $update->where(array('UnitId IN ('. $subQuery .')'));
                    }
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    if(is_array($arrUnits) && !empty($arrUnits)) {
                        foreach($arrUnits as $UnitId) {
                            $insert = $sql->insert();
                            $insert->into('KF_StageCompletionTrans')
                                ->values(array('StageCompletionId' => $stageCompletionId, 'UnitId' => $UnitId));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Stage-Completion-Add','N','Stage-Completion',$stageCompletionId,$ProjectId, 0, 'CRM', $sVno,$userId, 0 ,0);

                    $UnitArray= implode(',', $arrUnits);


                    $select = $sql->select();
                    $select->from(array("a" => "KF_StageCompletion"))
                        ->join(array("b" => "KF_BlockMaster"), "a.BlockId=b.BlockId", array("BlockName"), $select::JOIN_LEFT)
                        // ->join(array("c" => "KF_FloorMaster"), "a.FloorId=c.FloorId", array("FloorName"), $select::JOIN_LEFT)
                        ->join(array("d" => "KF_StageCompletionTrans"), "a.StageCompletionId=d.StageCompletionId", array("UnitId"), $select::JOIN_LEFT)
                        // ->join(array("e" => "KF_UnitMaster"), "d.UnitId=e.UnitId", array("UnitNo"), $select::JOIN_LEFT)
                        ->join(array("f" => "Proj_ProjectMaster"), "a.ProjectId=f.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                        ->join(array("i" => "KF_StageMaster"), "a.StageId=i.StageId", array("StageName"), $select::JOIN_LEFT)
                        ->columns(array('CompletionDate'))
                        ->where(array('d.UnitId IN (' . $UnitArray . ')'))
                        ->where(array('a.DeleteFlag=0'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrProjects = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();
                    $select->from(array("a" => "Crm_UnitBooking"))
                        ->columns(array('UnitId'))
                        ->join(array("e" => "KF_UnitMaster"), "a.UnitId=e.UnitId", array("UnitNo"), $select::JOIN_LEFT)
                        ->join(array("c" => "KF_FloorMaster"), "e.FloorId=c.FloorId", array("FloorName"), $select::JOIN_LEFT)
                        ->join(array("d" => "Crm_Leads"), "a.LeadId=d.LeadId", array("LeadName","Email"), $select::JOIN_LEFT)
                        ->where(array('e.UnitId IN (' . $UnitArray . ')'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrunits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $flr=array();
                    $unt=array();
                    foreach($arrunits as $unts){
                        array_push($unt, $unts['UnitNo']);
                        array_push($flr, $unts['FloorName']);
                    }
                    // Print_r($flr);
                    $sm = $this->getServiceLocator();
                    $config = $sm->get('application')->getConfig();
                    foreach($arrunits as $arrproj){
                        if($arrproj['Email']!=''||$arrproj['Email']!=null)   {
                            $mailData = array(

                                array(
                                    'name' => 'PROJECTNAME',
                                    'content' => $arrProjects['ProjectName']
                                ),
                                array(
                                    'name' => 'STAGENAME',
                                    'content' =>$arrProjects['StageName']
                                ),
                                array(
                                    'name' => 'UNITNAME',
                                    'content' =>$arrproj['UnitNo']
                                ),
                                array(
                                    'name' => 'FLOORNAME',
                                    'content' => $arrproj['FloorName']
                                ),
                                array(
                                    'name' => 'BLOCKNAME',
                                    'content' =>$arrProjects['BlockName']
                                ),
                                array(
                                    'name' => 'COMPLETIONDATE',
                                    'content' =>$arrProjects['CompletionDate']
                                )

                            );


                            $viewRenderer->MandrilSendMail()->sendMailTo($arrproj['Email'],$config['general']['mandrilEmail'],'stagecompletion','Crm_StageCompletion_alerts',$mailData);
                        }
                    }



                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if((isset($FeedId) && $FeedId!="")) {
                        if($ProgressBill==1){
                            $this->redirect()->toRoute("crm/progress", array("controller" => "bill","action" => "progress","stgCId"=>$stageCompletionId),array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        } else {
                            $this->redirect()->toRoute("crm/default", array("controller" => "project","action" => "completedstage"),array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                        }
                    } else {
                        if($ProgressBill==1){
                            $this->redirect()->toRoute("crm/progress", array("controller" => "bill","action" => "progress","stgCId"=>$stageCompletionId));
                        } else {
                            $this->redirect()->toRoute("crm/default", array("controller" => "project","action" => "completedstage"));
                        }
                    }

//                    if($ProgressBill==1){
//						$this->redirect()->toRoute("crm/progress", array("controller" => "bill","action" => "progress","stgCId"=>$stageCompletionId));
//					} else {
//						$this->redirect()->toRoute("crm/default", array("controller" => "project","action" => "completedstage"));
//					}
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                // Projects
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('data' => 'ProjectId', 'value' => 'ProjectName'))
                    ->where(array('DeleteFlag' => 0));
                $stmt = $sql->getSqlStringForSqlObject($select);
                $arrProjects = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->jsonProjects = json_encode($arrProjects);

                $aVNo = CommonHelper::getVoucherNo(801, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];




                $route= $this->params()->fromRoute('ProjectId');
                $rout= $this->params()->fromRoute('UnitId');
//                $select = $sql->select();
//                $select->from(array('a' =>'Crm_LeadProjects'))
//                    ->columns(array('LeadId'))
//                    ->join(array("b"=>"Crm_Leads"), "a.LeadId=b.LeadId", array("LeadName"=>new expression("b.LeadName + ' - ' + b.Mobile")), $select::JOIN_LEFT)
//                    ->where(array('a.ProjectId' => $route))
//                    ->order('a.LeadId asc');
//                $select->where(array("b.ExecutiveId" => $this->auth->getIdentity()->UserId));
//                $statement = $sql->getSqlStringForSqlObject($select);
//                $this->_view->resultLeads = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a' =>'KF_UnitMaster'))
                    ->columns(array('UnitId','ProjectId','UnitNo','FloorId','BlockId'))
                    ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                    ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array("BlockName"), $select::JOIN_LEFT)
                    ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array("FloorName"), $select::JOIN_LEFT)
                    ->where(array('a.UnitId'=>$rout,'a.ProjectId' => $route));
                $statement = $sql->getSqlStringForSqlObject($select);
                $saveproj= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($saveproj['UnitId']>0){
                    $this->_view->saveproj = $saveproj;
                }

                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function stagecompletioneditAction() {

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
        $userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here

                    $postData = $request->getPost();

                    $StageCompletionId = $this->bsf->isNullCheck($postData['StageCompletionId'], 'number' );
                    $ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number' );
                    $CompletionDate = date('Y-m-d', strtotime($postData['CompletionDate']));
                    $DueDate = date('Y-m-d', strtotime($postData['DueDate']));

                    if($StageCompletionId == 0) {
                        throw new \Exception('Invalid Stage Completion-id!');
                    }

                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $update = $sql->update();
                    $update->table('KF_StageCompletion')
                        ->set(array(
                            'ProjectId' => $ProjectId,
                            'CompletionDate' => $CompletionDate,
                            'DueDate' => $DueDate,
                        ))
                        ->where(array('StageCompletionId' => $StageCompletionId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
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
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $postData = $request->getPost();

                    $stageCompletionNo = $this->bsf->isNullCheck($postData['StageCompletionNo'], 'string' );
                    $StageCompletionId = $this->bsf->isNullCheck($postData['StageCompletionId'], 'number' );
                    $ProjectId = $this->bsf->isNullCheck($postData['ProjectId'], 'number' );
                    $CompletionDate = date('Y-m-d', strtotime($postData['CompletionDate']));
                    $DueDate = date('Y-m-d', strtotime($postData['DueDate']));

                    if($StageCompletionId == 0) {
                        throw new \Exception('Invalid Stage Completion-id!');
                    }

                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_StageCompletion'))
                        ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                        ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array("BlockName"), $select::JOIN_LEFT)
                        ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array("FloorName"), $select::JOIN_LEFT)
                        ->where(array('StageCompletionId' => $StageCompletionId))
                        ->limit(1);
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $stageCompletion = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(empty($stageCompletion)) {
                        throw new \Exception('Stage Completion not found!');
                    }

                    if($stageCompletion['PBRaised'] != 0) {
                        throw new \Exception('Bill raised!');
                    }

                    $update = $sql->update();
                    $update->table('KF_StageCompletion')
                        ->set(array(
                            'StageCompletionNo' => $stageCompletionNo,
                            'ProjectId' => $ProjectId,
                            'CompletionDate' => $CompletionDate,
                            'DueDate' => $DueDate,
                        ))
                        ->where(array('StageCompletionId' => $StageCompletionId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $files = $request->getFiles();
                    if(count($files["files"])>0) {
                        $delete = $sql->delete();
                        $delete->from('KF_StageImageTrans')
                            ->where(array('StageCompletionId'=>$StageCompletionId));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        foreach($files["files"] as $index => $file) {

                            $fileTempName = $file['tmp_name'];

                            if(!empty($file['error'][$index])) {
                                return false;
                            }

                            if(!empty($fileTempName) && is_uploaded_file($fileTempName)) {
                                $url = "public/uploads/crm/stageImg/";
                                $filename = $this->bsf->uploadFile($url, $file);
                                if ($filename) {
                                    // update valid files only
                                    $url = 'uploads/crm/stageImg/' . $filename;

                                    $insert  = $sql->insert('KF_StageImageTrans');
                                    $newData = array(
                                        'StageCompletionId'=>$StageCompletionId,
                                        'ImageUrl' =>$url
                                    );
                                    $insert->values($newData);
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }
                    }

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Stage-Completion-Modify','E','Stage-Completion',$StageCompletionId,$ProjectId, 0, 'CRM', $stageCompletionNo,$userId, 0 ,0);

                    $this->redirect()->toRoute("crm/default", array("controller" => "project","action" => "completedstage"));
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {
                    $stageCompletionId = $this->bsf->isNullCheck($this->params()->fromRoute('stageCompletionId'), 'number');
                    if (!is_numeric($stageCompletionId) || $stageCompletionId <= 0) {
                        throw new \Exception('Invalid Stage Completion-id!');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_StageCompletion'))
                        ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array("ProjectName"), $select::JOIN_LEFT)
                        ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array("BlockName"), $select::JOIN_LEFT)
                        ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array("FloorName"), $select::JOIN_LEFT)
                        ->where(array('StageCompletionId' => $stageCompletionId))
                        ->limit(1);
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $stageCompletion = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(empty($stageCompletion)) {
                        throw new \Exception('Stage Completion not found!');
                    }

                    $select = $sql->select();
                    switch($stageCompletion['StageType']) {
                        case 'S':
                            $select->from('KF_StageMaster')
                                ->columns(array('StageName'))
                                ->where(array('StageId' => $stageCompletion['StageId']));
                            break;
                        case 'O':
                            $select->from('Crm_OtherCostMaster')
                                ->columns(array('StageName' => 'OtherCostName'))
                                ->where(array('OtherCostId' => $stageCompletion['StageId']));
                            break;
                        case 'D':
                            $select->from('Crm_DescriptionMaster')
                                ->columns(array('StageName' => 'DescriptionName'))
                                ->where(array('DescriptionId' => $stageCompletion['StageId']));
                            break;
                    }
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $stage = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $stageCompletion['StageName'] = $stage['StageName'];

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_StageCompletionTrans'))
                        ->columns(array('data' => 'UnitId'))
                        ->join(array('b' => 'KF_UnitMaster'), 'a.UnitId=b.UnitId', array('value' => 'UnitNo'), $select::JOIN_LEFT)
                        ->where(array('StageCompletionId' => $stageCompletionId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $stageCompletion['units'] = $arrUnits;
                    $this->_view->stageCompletion = $stageCompletion;

                    //Photos
                    $select = $sql->select();
                    $select->from(array('a' => 'KF_StageImageTrans'))
                        ->columns(array('ImageUrl'))
                        ->where(array('StageCompletionId' => $stageCompletionId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->stageCompletionImg = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // Projects
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('data' => 'ProjectId', 'value' => 'ProjectName'))
                        ->where(array('DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrProjects = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->jsonProjects = json_encode($arrProjects);
                } catch (PDOException $ex) {
                    print "Error!: " . $ex->getMessage() . "</br>";
                } catch (\Exception $ex) {
                    $this->_view->err = $ex->getMessage();
                }


                $aVNo = CommonHelper::getVoucherNo(801, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $this->_view->genType = $aVNo["genType"];
                if ($aVNo["genType"] == false)
                    $this->_view->svNo = "";
                else
                    $this->_view->svNo = $aVNo["voucherNo"];



                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    // AJAX Request
    public function checkStageCompletionNoAction() {
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
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $stageCompletionNo = $this->bsf->isNullCheck($this->params()->fromPost('StageCompletionNo'), 'string' );
                    if(is_null($stageCompletionNo)) {
                        throw new \Exception('Invalid Stage Completion No!');
                    }

                    $isEditMode = FALSE;
                    $stageCompletionId = $this->bsf->isNullCheck($this->params()->fromPost('StageCompletionId'), 'number');
                    if($stageCompletionId != 0) {
                        $isEditMode = TRUE;
                    }

                    $select = $sql->select();
                    $select->from('KF_StageCompletion')
                        ->where(array('StageCompletionNo' => $stageCompletionNo, 'DeleteFlag' => 0));
                    if($isEditMode) {
                        $select->where('StageCompletionId <>' . $stageCompletionId);
                    }
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $stageCompletion = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $result =  json_encode(array('stageCompletion' => $stageCompletion));
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

    // AJAX Request
    public function deleteStageCompletionAction() {
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
        $userId = $this->auth->getIdentity()->UserId;
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    //Write your Ajax post code here

                    $stageCompletionId = $this->bsf->isNullCheck($this->params()->fromPost('StageCompletionId'), 'number');
                    if($stageCompletionId == 0) {
                        throw new \Exception('Invalid Stage Completion Id!');
                    }
                    $deleteRemarks = $this->bsf->isNullCheck($this->params()->fromPost('DeleteRemarks'), 'string');
                    if(is_null($deleteRemarks) || strlen($deleteRemarks) <= 0) {
                        throw new \Exception('Invalid Delete Remarks!');
                    }

                    $update = $sql->update();
                    $update->table('KF_StageCompletion')
                        ->set(array('DeleteFlag' => 1, 'DeleteRemarks' => $deleteRemarks))
                        ->where(array('StageCompletionId' => $stageCompletionId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),'Stage-Completion-Delete','D','Stage-Completion',$stageCompletionId,0, 0, 'CRM','',$userId, 0 ,0);

                    $result =  'success';
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $connection->rollback();
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

    // AJAX Request
    public function blocksAction() {
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
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number' );
                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $select = $sql->select();
                    $select->from(array('a' => 'KF_BlockMaster'));
                    $select->columns(array('data' => 'BlockId', 'value' => new expression(" a.BlockName +' - '+ b.PhaseName")))
                        ->join(array('b' => 'KF_PhaseMaster'), 'a.PhaseId=b.PhaseId', array(), $select::JOIN_LEFT)
                        ->where(array('a.ProjectId' => $ProjectId, 'a.DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrBlocks = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result =  json_encode(array('blocks' => $arrBlocks));
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

    // AJAX Request
    public function levelsAction() {
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
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number' );
                    $BlockId = $this->bsf->isNullCheck($this->params()->fromPost('BlockId'), 'number' );

                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $arrWhere = array('ProjectId' => $ProjectId, 'DeleteFlag' => 0);
                    if ($BlockId != 0) {
                        $arrWhere['BlockId'] = $BlockId;
                    }

                    $select = $sql->select();
                    $select->from('KF_FloorMaster');
                    $select->columns(array('data' => 'FloorId', 'value' => 'FloorName'))
                        ->where($arrWhere);
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrLevels = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result =  json_encode(array('levels' => $arrLevels));
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

    // AJAX Request
    public function unitsAction() {
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
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number' );
                    $BlockId = $this->bsf->isNullCheck($this->params()->fromPost('BlockId'), 'number' );
                    $FloorId = $this->bsf->isNullCheck($this->params()->fromPost('FloorId'), 'number' );
                    $StageId = $this->bsf->isNullCheck($this->params()->fromPost('StageId'), 'number' );
                    $StageType = $this->bsf->isNullCheck($this->params()->fromPost('StageType'), 'string' );


                    $sub1 = $sql->select();
                    $sub1->from(array('a' =>'Crm_PaymentScheduleDetail'))
                    ->columns(array('PaymentscheduleId'))
                        ->where (array('StageId'=>$StageId,'StageType'=>$StageType));


                    $select1 = $sql->select();
                    $select1->from(array('a' =>'Crm_PaymentScheduleDetail'))
                    ->columns(array('StageId'))
                        ->where (array('PaymentscheduleId'=>array($sub1) ))
                        ->where ("stageId < $StageId")
                        ->where ("stageType <> 'A'");

                 $stmt = $sql->getSqlStringForSqlObject($select1);
                    $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($arrUnits)>0) {

                        $select2 = $sql->select();
                        $select2->from(array('a' =>'Crm_PaymentScheduleDetail'))
                            ->columns(array('StageId'))
                            ->where (array('PaymentscheduleId'=>array($sub1) ))
                            ->where ("stageId < $StageId")
                            ->where ("stageType <> 'A'");
//                        //strat
//                        $sDesc = "";
//                        $icount = 0;
//                        foreach ($arrUnits as &$arrUnitss) {
//                            if ($icount != 0) {
//                                $sDesc = $sDesc . "And ";
//                            }
//                            $sDesc = $sDesc . "StageId =" . $arrUnitss['StageId'] . " ";
//                            $icount = $icount + 1;
//                        }

                        //End


                        $subQuery1 = $sql->select();
                        $subQuery1->from(array("a" => "KF_StageCompletionTrans"))
                            ->join(array('b' => 'KF_StageCompletion'), 'a.StageCompletionId=b.StageCompletionId', array(), $subQuery1::JOIN_LEFT)
                            ->columns(array('UnitId'));
                    $subQuery1 ->where(array('b.StageId' => array($select2)));
                       // $subQuery1->where($sDesc);
                        // ->having("b.StageId"=>array($select2);

                    }
                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $arrWhere = array('ProjectId' => $ProjectId, 'DeleteFlag' => 0);

                    if ($BlockId != 0) {
                        $arrWhere['BlockId'] = $BlockId;
                    }
                    if ($FloorId != 0) {
                        $arrWhere['FloorId'] = $FloorId;
                    }

                    $subQuery = $sql->select();
                    $subQuery->from(array("a" => "KF_StageCompletionTrans"))
                        ->join(array('b' => 'KF_StageCompletion'), 'a.StageCompletionId=b.StageCompletionId', array(), $subQuery::JOIN_LEFT)
                        ->columns(array('UnitId'))
                        ->where(array('b.StageId' => $StageId,'b.StageType'=>$StageType));



                    $select = $sql->select();
                    $select->from(array('a' =>'KF_UnitMaster'));
                    $select->columns(array('data' => 'UnitId', 'value' => 'UnitNo'))
                        ->where( $arrWhere);
                    if(count($arrUnits)>0) {
                        $select->where->expression('a.UnitId  IN ?', array($subQuery1));
                    }
                    $select->where->expression('a.UnitId Not IN ?',array( $subQuery));
                     $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnits = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result =  json_encode(array('units' => $arrUnits));
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

    // AJAX Request
    public function stagesAction() {
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
//                $select->from(array('a' => 'KF_StageCompletion'))
//                    ->join(array('b' => 'Crm_PaymentScheduleDetail'), 'a.StageId=b.StageId', array(), $select::JOIN_LEFT)
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
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number' );
                    $StageType = $this->bsf->isNullCheck($this->params()->fromPost('StageType'), 'string' );
                    $unitId = $this->bsf->isNullCheck($this->params()->fromPost('editunit'), 'number' );

                    $subquery = $sql->select();
                    $subquery->from(array('a' => 'KF_StageCompletion'))
                        ->join(array('b' => 'KF_StageCompletionTrans'), 'a.StageCompletionId=b.StageCompletionId', array(), $subquery::JOIN_LEFT)
                      ->columns(array('StageId'))
                        ->where(array(
                            'a.ProjectId' => $ProjectId,
                            'b.UnitId' => $unitId,
                            'a.DeleteFlag' => 0,

                        ));
//                    $stmt = $sql->getSqlStringForSqlObject($select);
//                    $stages = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $stagesa=array();
//                    foreach($stages as $stages){
//                        array_push($stagesa,$stages['StageId']);
//                    }


                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }

                    $subQuery = '';
                    //  if(empty($arrUnits)) {
                    //   $arrWhere = array('ProjectId' => $ProjectId);
//                        if($BlockId != 0) {
//                            $arrWhere['BlockId'] = $BlockId;
//                        }
//
//                        if($FloorId != 0) {
//                            $arrWhere['FloorId'] = $FloorId;
//                        }

//                        $subQuery = $sql->select();
//                        $subQuery->from('KF_UnitMaster')
//                            ->columns(array('UnitId'))
//                            ->where($arrWhere);
//                    } else {
//                        $subQuery = implode(',', $arrUnits);
//                    }
                   // Print_r($stagesa);die;

                    $select = $sql->select();
                    switch($StageType) {
                        case 'S':
                            // Stages
                            $select->from(array('a' => 'KF_StageMaster'))
                                ->join(array('b' => 'Crm_PaymentScheduleDetail'), 'a.StageId=b.StageId', array(), $select::JOIN_LEFT)
                               ->columns(array(new Expression("DISTINCT a.StageId as data, a.StageName as value")))
                                ->where(array(
                                    'a.ProjectId' => $ProjectId,
                                    'b.DeleteFlag' => 0,
                                    'a.DeleteFlag' => 0,
                                    'b.StageType' => 'S'
                                ));
                                if($unitId!=''||$unitId>0 ) {
                                      $select-> where->expression('a.StageId Not IN ?', array($subquery));
                                    }




                            break;
                        case 'O':
                            // Other cost
                            $select->from(array('c' => 'Crm_ProjectOtherCostTrans'))
                                ->join(array('a' => 'Crm_OtherCostMaster'), 'c.OtherCostId=a.OtherCostId', array('value'=>new Expression("OtherCostName")), $select::JOIN_LEFT)
                                ->join(array('b' => 'Crm_PaymentScheduleDetail'), new Expression("b.StageId=a.OtherCostId and b.StageType='O'"), array(), $select::JOIN_LEFT)
                                ->columns(array('data' => 'OtherCostId'))
                                ->where(array(
                                    'c.ProjectId' => $ProjectId,
                                    // 'b.DeleteFlag' => 0,
                                    // 'a.DeleteFlag' => 0,
                                    // 'b.StageCompleted' => 0,
                                ));
                            if($unitId!=''||$unitId>0 ) {
                                $select-> where->expression('a.OtherCostId Not IN ?', array($subquery));
                            }
                            break;
                        case 'D':
                            $select->from(array('a' => 'Crm_DescriptionMaster'))
                                ->join(array('b' => 'Crm_PaymentScheduleDetail'), 'b.StageId=a.DescriptionId', array(), $select::JOIN_LEFT)
                                ->join(array('c' => 'Crm_PaymentSchedule'), 'b.PaymentScheduleId=c.PaymentScheduleId', array(), $select::JOIN_LEFT)
                                ->columns(array('data' => 'DescriptionId', 'value' => 'DescriptionName'))
                                ->where(array(
                                    'c.ProjectId' => $ProjectId,
                                    //'b.DeleteFlag' => 0,
                                    'a.DeleteFlag' => 0,
                                    //'b.StageCompleted' => 0,
                                    'b.StageType' => 'D'
                                ));
                            if($unitId!=''||$unitId>0 ) {
                                $select-> where->expression('a.DescriptionId Not IN ?', array($subquery));
                            }
                            break;
                    }



                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrStages = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $result =  json_encode(array('stages' => $arrStages ));
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);
//               } catch(PDOException $e){
//                    $response->setStatusCode(500);
//                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                //GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }
    public function selectedStageAction() {
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
//                $select->from(array('a' => 'KF_StageCompletion'))
//                    ->join(array('b' => 'Crm_PaymentScheduleDetail'), 'a.StageId=b.StageId', array(), $select::JOIN_LEFT)
               // $response->setStatusCode(401);
               // $response->setContent('CSRF attack');
                //return $response;
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
            $request = $this->getRequest();
            if ($request->isPost()) {

                try {
                    //Write your Ajax post code here

                    $ProjectId = $this->bsf->isNullCheck($this->params()->fromPost('ProjectId'), 'number' );
                    $StagelId = $this->bsf->isNullCheck($this->params()->fromPost('stageId'), 'number' );

                    if($ProjectId == 0) {
                        throw new \Exception('Invalid Project-id!');
                    }
                    $select = $sql->select();
                    $select->from(array('a' => 'Crm_PaymentSchedule'))
                       ->join(array('b' => 'Crm_PaymentScheduleDetail'), 'a.PaymentScheduleId=b.PaymentScheduleId', array('PaymentScheduleDetailId'), $select::JOIN_LEFT)
                        ->where(array(
                            'a.ProjectId' => $ProjectId,
                            'b.StageId' => $StagelId,
                        ));

                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $schedulename = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $select = $sql->select();

                            $select->from(array('a' => 'KF_StageCompletion'))
                                ->join(array('b' => 'Crm_PaymentScheduleDetail'), 'a.StageId=b.StageId', array(), $select::JOIN_LEFT)
                                ->columns(array('StageCompletionId'))
                                ->where(array(
                                    'a.ProjectId' => $ProjectId,

                                  ));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrStages = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql->select();
                    $select->from(array("a" => "Crm_PaymentScheduleDetail"))
                        ->columns(array('StageId'=> new Expression("TOP 1 StageId")))
                        ->where(array('a.StageType' => 'S',
                                    'a.PaymentScheduleId' => $schedulename['PaymentScheduleId']));
                  $statement = $sql->getSqlStringForSqlObject($select);
                    $entryResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                   if(Count($arrStages)==0 && $StagelId !=$entryResult['StageId']){
                        $stages="2";
                    }
                    else{
                        $stages="1";
                    }


                    $result =  json_encode(array('stages' => $stages ));
                    $this->_view->setTerminal(true);
                    $response->getHeaders()->addHeaderLine( 'Content-Type', 'application/json' );
                    $response->setStatusCode(200);
                    $response->setContent($result);
//               } catch(PDOException $e){
//                    $response->setStatusCode(500);
//                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
                }

            } else {
                //GET request
                $response->setStatusCode(405);
                $response->setContent('Method not allowed!');
            }
            return $response;
        }
    }

    public function completedstageAction(){
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

            } else {
                $subQuery = $sql->select();
                $subQuery->from("Crm_PaymentScheduleUnitTrans")
                    ->columns(array('StageCompletionId'))
                    ->where(array('BillPassed'=>0))
                    ->where->notEqualTo('StageCompletionId', 0);

                $update = $sql->update();
                $update->table('KF_StageCompletion');
                $update->set(array(
                    'PBRaised'  =>0,
                ));
                $update->where(array('StageCompletionId'=>array($subQuery)));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                $selectStage = $sql->select();
                $selectStage->from(array("a"=>"KF_StageCompletion"));
                $selectStage->columns(array(new Expression("a.ProjectId,a.BlockId,Convert(varchar(10),a.CompletionDate,105) as CompletionDate,a.StageCompletionId,a.PBRaised, a.StageCompletionNo,Case When a.StageType='S' then f.StageName when a.StageType='O' then e.OtherCostName
								When a.StageType='D' then d.DescriptionName end as StageName")),array("ProjectName"))
                    ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array("ProjectName"), $selectStage::JOIN_LEFT)
                    ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array("BlockName"), $selectStage::JOIN_LEFT)
                    ->join(array("d"=>"Crm_DescriptionMaster"), NEW Expression("a.StageId=d.DescriptionId and a.StageType='D' "), array(), $selectStage::JOIN_LEFT)
                    ->join(array("e"=>"Crm_OtherCostMaster"), NEW Expression("a.StageId=e.OtherCostId and a.StageType='O'"), array(), $selectStage::JOIN_LEFT)
                    ->join(array("f"=>"KF_StageMaster"), NEW Expression("a.StageId=f.StageId and a.StageType='S'"), array(), $selectStage::JOIN_LEFT)
                    ->where(array('a.DeleteFlag'=>0))
                    ->order("a.StageCompletionId Desc");
                $statement = $sql->getSqlStringForSqlObject($selectStage);
                $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
                //Common function
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

                $this->_view->gridResult = $gridResult;
                return $this->_view;
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
        }
    }

    public function checkNameAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Crm_PaymentSchedule')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("PaymentSchedule = '".$postParams['psName']."'")
                    ->where("ProjectId='".$postParams['projectId']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function qualifierSettingsAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);


        $request = $this->getRequest();
        if ($request->isPost()) {
            $postData = $request->getPost();
            //echo '<pre>'; print_r($postData); die;

            $delete = $sql->delete();
            $delete->from('Crm_QualifierSettings');
            $statement = $sql->getSqlStringForSqlObject($delete);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $rtCount = $postData['rtCount'];

            for($i=1;$i<=$rtCount;$i++) {
                $taxper=0;
                if($postData['rtQualSet_'.$i]) {
                    foreach($postData['rtQualSet_'.$i] as $key => $value) {
                        $iqualid =$this->bsf->isNullCheck($value,'number');
                        if ($postData['rtQualTypeId_'.$i.'_'.$iqualid] ==2) $taxper = $this->bsf->isNullCheck($postData['rtQualTaxPer_'.$i],'number');
                        $insert = $sql->insert();
                        $insert->into('Crm_QualifierSettings');
                        $insert->Values(array('QualSetTypeId' => $this->bsf->isNullCheck($postData['rtTypeId_'.$i],'number')
                        , 'QualifierId' => $iqualid
                        , 'QualSetType' => $this->bsf->isNullCheck('S','string')
                        , 'SortOrder' => $this->bsf->isNullCheck($postData['rtSortId_'.$i],'number')
                        , 'CreatedDate' => date('Y-m-d')
                        , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $update = $sql->update();
                $update->table('Crm_ReceiptTypeMaster');
                $update->set(array( 'TaxablePer' => $taxper));
                $update->where(array('ReceiptTypeId' =>  $this->bsf->isNullCheck($postData['rtTypeId_'.$i],'number')));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $ocCount = $postData['ocCount'];
            for($i=1;$i<=$ocCount;$i++) {
                $taxper=0;
                if($postData['ocQualSet_'.$i]) {
                    foreach($postData['ocQualSet_'.$i] as $key => $value) {
                        $iqualid =$this->bsf->isNullCheck($value,'number');
                        if ($postData['ocQualTypeId_'.$i. '_' .$iqualid] ==2) $taxper = $this->bsf->isNullCheck($postData['ocQualTaxPer_'.$i],'number');
                        $insert = $sql->insert();
                        $insert->into('Crm_QualifierSettings');
                        $insert->Values(array('QualSetTypeId' => $this->bsf->isNullCheck($postData['ocTypeId_'.$i],'number')
                        , 'QualifierId' => $iqualid
                        , 'QualSetType' => $this->bsf->isNullCheck('O','string')
                        , 'SortOrder' => $this->bsf->isNullCheck($postData['ocSortId_'.$i],'number')
                        , 'CreatedDate' => date('Y-m-d')
                        , 'DeleteFlag' => $this->bsf->isNullCheck('0','number')));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                $update = $sql->update();
                $update->table('Crm_OtherCostMaster');
                $update->set(array( 'TaxablePer' => $taxper));
                $update->where(array('OtherCostId' =>  $this->bsf->isNullCheck($postData['ocTypeId_'.$i],'number')));
                $statement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
            }
            $this->redirect()->toRoute("crm/default", array("controller" => "project","action" => "qualifier-settings"));
        } else {
            //Fetching data from Receipt Type Master
            $select = $sql->select();
            $select->from('Crm_ReceiptTypeMaster')
                ->columns(array('ReceiptTypeId','ReceiptTypeName','TaxablePer'));
               // ->where("ReceiptType = 'S'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->receiptTypeMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Fetching data from Other Cost Master
            $select = $sql->select();
            $select->from('Crm_OtherCostMaster')
                ->columns(array('OtherCostId','OtherCostName','TaxablePer'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->otherCostMaster  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Fetching data from Qualifier Trans
            $select = $sql->select();
            $select->from(array('a' => 'Proj_QualifierTrans'))
                ->join(array('b' => 'Proj_QualifierMaster'), 'a.QualifierId = b.QualifierId', array('QualifierName','QualifierTypeId'), $select::JOIN_LEFT)
                ->where("a.QualType = 'C'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->qualifierMaster = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Fetching data from Crm_QualifierSettings
            $select = $sql->select();
            $select->from('Crm_QualifierSettings')
                ->columns(array('*'))
                ->where("QualSetType = 'S'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->qualSetReceiptTypes  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $qualSetRecTypes = array();
            foreach($this->_view->qualSetReceiptTypes as $qualSetRT) {
                $qualSetRecTypes[$qualSetRT['QualSetTypeId']][] = $qualSetRT['QualifierId'];
            }
            $this->_view->selQualSetRecTypes = $qualSetRecTypes;
            //echo '<pre>'; print_r($qualSetRecTypes);

            $select = $sql->select();
            $select->from('Crm_QualifierSettings')
                ->columns(array('*'))
                ->where("QualSetType = 'O'");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->qualSetOtherCosts  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $qualSetOthCost = array();
            foreach($this->_view->qualSetOtherCosts as $qualSetOC) {
                $qualSetOthCost[$qualSetOC['QualSetTypeId']][] = $qualSetOC['QualifierId'];
            }
            $this->_view->selQualSetOthCosts = $qualSetOthCost;
        }
        return $this->_view;
    }

    public function unitTypeAction(){
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

        $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
        $this->_view->projectId = $projectId;

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'))
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        if(!isset($this->_view->projectId) || $this->_view->projectId == '' || $this->_view->projectDetail == '') {
            $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
        }

        $select = $sql->select();
        $select->from('Proj_ProjectMaster')
            ->columns(array('ProjectId','ProjectName'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from('Crm_ProjectDetail')
            ->where(array("ProjectId"=>$projectId));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->resultDetail   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

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
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function incentiveRegisterAction(){
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
                $postParams = $request->getPost();

                $exeId=$postParams['levelId'];
                $projectId=$postParams['projectId'];
                $mode=$postParams['mode'];
                $type = $this->bsf->isNullCheck($postParams['iBasedOn'],'string');

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if($mode == 1) {
                        //print_r($postParams);die;
                        $delete = $sql->delete();
                        $delete->from('Crm_IncentiveTrans')
                            ->where(array('ProjId'=>$projectId,));
                        $DelStatement = $sql->getSqlStringForSqlObject($delete);
                        $deleteProject = $dbAdapter->query($DelStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('Crm_IncentiveRegister');
                        $update->set(array(
                            'IncentiveType' =>$this->bsf->isNullCheck($postParams['incentiveType'],'string'),
                            'IBasedOn' =>$type,
                            'CreatedDate'=>date('m-d-Y H:i:s'),
                        ));
                        $update->where(array('ProjectId'=>$projectId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $results2   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $rowCount =$postParams['RowCount'];
                        foreach(range(1,$rowCount) as $count){

                            if($type=="P") {
                                if($postParams['perct_'.$count]){
                                    $IncPercent = $postParams['perct_'.$count];
                                } else {
                                    $IncPercent  = "0";
                                }
                                $fValue=$this->bsf->isNullCheck($postParams['fromval_'.$count],'number');
                                $tValue=$this->bsf->isNullCheck($postParams['toval_'.$count],'number');
                                $tper=$this->bsf->isNullCheck($postParams['perct_'.$count],'number');

                                if($fValue!=0 || $tValue!=0 )
                                {
                                    //print_r($postParams);die;
                                    $insert = $sql->insert('Crm_IncentiveTrans');
                                    $insert->values(array(
                                        'IncentiveId' =>$this->bsf->isNullCheck($postParams['incentiveId'],'number'),
                                        'LevelId'=>$exeId,
                                        'FromValue'=>$fValue,
                                        'ToValue'=>$tValue,
                                        'ProjId'=>$projectId,
                                        'IncPercent'=>$IncPercent,
                                        'DeleteFlag'=>0,
                                        'ModifiedDate'=>date('m-d-Y H:i:s')
                                    ));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else {
                                if($postParams['amount_'.$count]){
                                    $IncValue = $postParams['amount_'.$count];
                                } else {
                                    $IncValue  = "0";
                                }
                                $fVal=$this->bsf->isNullCheck($postParams['fromvalue_'.$count],'number');
                                $tVal=$this->bsf->isNullCheck($postParams['tovalue_'.$count],'number');
                                $tamt=$this->bsf->isNullCheck($postParams['amount_'.$count],'number');

                                if($fVal!=0 || $tVal!=0)
                                {
                                    //print_r($postParams);die;
                                    $insert = $sql->insert('Crm_IncentiveTrans');
                                    $insert->values(array(
                                        'IncentiveId' =>$this->bsf->isNullCheck($postParams['incentiveId'],'number'),
                                        'LevelId'=>$exeId,
                                        'FromValue'=>$fVal,
                                        'ToValue'=>$tVal,
                                        'ProjId'=>$projectId,
                                        'IncValue'=>$IncValue,
                                        //'IncPercent'=>$IncPercent,
                                        'ModifiedDate'=>date('m-d-Y H:i:s'),
                                        'DeleteFlag'=>0
                                    ));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    } else {

                        $insert  = $sql->insert('Crm_IncentiveRegister');
                        $newData = array(
                            'ProjectId'=>$projectId,
                            'IncentiveType' =>$this->bsf->isNullCheck($postParams['incentiveType'],'string'),
                            'IBasedOn' =>$this->bsf->isNullCheck($postParams['iBasedOn'],'string'),
                            'CreatedDate'=>date('m-d-Y H:i:s'),
                        );
                        $insert->values($newData);
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $targetresult =  $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $incentiveId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        //target trans//
                        $rowCount =$postParams['RowCount'];
                        foreach(range(1,$rowCount) as $count){
                            if($type=="P") {
                                if($postParams['perct_'.$count]){
                                    $IncPercent = $postParams['perct_'.$count];
                                } else {
                                    $IncPercent  = "0";
                                }
                                $fValue=$this->bsf->isNullCheck($postParams['fromval_'.$count],'number');
                                $tValue=$this->bsf->isNullCheck($postParams['toval_'.$count],'number');
                                $tper=$this->bsf->isNullCheck($postParams['perct_'.$count],'number');

                                if($fValue!=0 || $tValue!=0)
                                {
                                    $insert = $sql->insert('Crm_IncentiveTrans');
                                    $insert->values(array(
                                        'IncentiveId' =>$incentiveId ,
                                        'LevelId'=>$exeId,
                                        'FromValue'=>$fValue,
                                        'ToValue'=>$tValue,
                                        'ProjId'=>$projectId ,
                                        //'IncValue'=>$IncValue,
                                        'IncPercent'=>$IncPercent,
                                        'DeleteFlag'=>0,
                                        'ModifiedDate'=>date('m-d-Y H:i:s')
                                    ));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            } else {
                                if($postParams['amount_'.$count]){
                                    $IncValue = $postParams['amount_'.$count];
                                } else {
                                    $IncValue  = "0";
                                }

                                $fVal=$this->bsf->isNullCheck($postParams['fromvalue_'.$count],'number');
                                $tVal=$this->bsf->isNullCheck($postParams['tovalue_'.$count],'number');
                                $tamt=$this->bsf->isNullCheck($postParams['amount_'.$count],'number');
                                if($fVal!=0 || $tVal != 0)
                                {
                                    $insert = $sql->insert('Crm_IncentiveTrans');
                                    $insert->values(array(
                                        'IncentiveId' =>$incentiveId ,
                                        'LevelId'=>$exeId,
                                        'FromValue'=>$fVal,
                                        'ToValue'=>$tVal,
                                        'ProjId'=>$projectId,
                                        'IncValue'=>$IncValue,
                                        //'IncPercent'=>$IncPercent,
                                        'DeleteFlag'=>0,
                                        'ModifiedDate'=>date('m-d-Y H:i:s')
                                    ));
                                    $statement = $sql->getSqlStringForSqlObject($insert);
                                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                        }
                    }

                    $connection->commit();

                    if($postParams['submitFunc']==1) {
                        $this->redirect()->toRoute('crm/property-management', array('controller' => 'project', 'action' => 'property-management', 'projectId' => $this->bsf->encode($projectId)));
                    } else {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $projectId = $this->bsf->decode($this->params()->fromRoute('projectId'));
                $this->_view->projectId = $projectId;

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId','ProjectName'))
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->projectDetail=$projectDetail;
                if(!isset($projectId) || $projectId == '' || $projectDetail == '') {
                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                }

                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId','ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->projects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //edit Incentive Management//
                $select = $sql->select();
                $select->from('Crm_IncentiveRegister')
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $resultbased = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if($resultbased != '') {
                    $this->_view->mode = 1;
                } else  {
                    $this->_view->mode = 0;
                }
                $this->_view->resultbased=$resultbased;
                $select = $sql->select();
                $select->from(array("a"=>'Crm_IncentiveRegister'))
                    ->columns(array('*'))
                    ->join(array("c"=>"Crm_IncentiveTrans"), "a.IncentiveId= c.IncentiveId", array('LevelId','FromValue','ToValue','IncValue','IncPercent'), $select::JOIN_LEFT)
                    ->join(array("d"=>"WF_LevelMaster"), "c.LevelId=d.LevelId", array("LevelName"), $select::JOIN_INNER)
                    ->where(array("ProjectId"=>$projectId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                //selecting values for LeadProjects
                $select = $sql->select();
                $select->from('Proj_ProjectMaster')
                    ->columns(array('ProjectId','ProjectName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLeadProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //selecting values for Executive
                /*$select = $sql->select();
                $select->from('WF_Users')
                    ->columns(array('UserId','UserName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsExecutive  = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();*/

                //Level Master
                $select = $sql->select();
                $select->from('WF_LevelMaster')
                    ->columns(array('LevelId','LevelName'))
                    ->order('OrderId asc');
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->resultsLevel = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            }
            return $this->_view;

        }
    }

    public function propertyManagementAction() {
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

                    $postData = $request->getPost();
                    $projectId = $this->bsf->isNullCheck( $postData[ 'ProjectId' ], 'number' );
                    if($projectId == 0) {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }

                    $arrCoveredServices = array();
                    $arrUncoveredServices = array();

                    foreach($postData as $key => $data) {
                        if(preg_match('/^covService_[a-z0-9]+/i', $key)) {
                            // covered services
                            $arrMatches = explode('_', $key);
                            $arrCoveredServices[$arrMatches[1]][$arrMatches[2]] = $data;
                        } elseif(preg_match('/^uncovServ_[a-z0-9]+/i', $key)) {
                            // uncovered services
                            $arrMatches = explode('_', $key);
                            $arrUncoveredServices[$arrMatches[1]][$arrMatches[2]] = $data;
                        }
                    }

                    $arrValues = array(
                        'Term' => $this->bsf->isNullCheck($postData['Term'], 'string'),
                        'NoOfTerms' => $this->bsf->isNullCheck($postData['NoOfTerms'], 'number'),
                        'AMCAmt' => $this->bsf->isNullCheck($postData['AMCAmt'], 'number'),
                        'MaintenanceType' => $this->bsf->isNullCheck($postData['MaintenanceType'], 'string'),
                        'MaintenanceAmt' => $this->bsf->isNullCheck($postData['MaintenanceAmt'], 'number'),
                        'DueDayOfMonth' => $this->bsf->isNullCheck($postData['DueDayOfMonth'], 'number'),
                        'MaintenanceDepositAmt' => $this->bsf->isNullCheck($postData['MaintenanceDepositAmt'], 'number'),
                    );

                    $PMId = $this->bsf->isNullCheck( $postData[ 'PMId' ], 'number' );
                    if($PMId == 0) {
                        // insert
                        $arrValues['ProjectId'] = $projectId;

                        $insert = $sql->insert();
                        $insert->into('Crm_ProjPropertyManagement')
                            ->values($arrValues);
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        // update
                        $update = $sql->update();
                        $update->table('Crm_ProjPropertyManagement')
                            ->set($arrValues)
                            ->where(array('PMId' => $PMId));
                        $stmt = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Crm_ProjCoveredServiceTrans')
                            ->where(array('ProjectId' => $projectId));
                        $stmt = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delete = $sql->delete();
                        $delete->from('Crm_ProjUncoveredServiceTrans')
                            ->where(array('ProjectId' => $projectId));
                        $stmt = $sql->getSqlStringForSqlObject($delete);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // covered services
                    foreach($arrCoveredServices as $covService) {

                        $servId = $this->bsf->isNullCheck($covService['id'], 'number');
                        if($servId == 0) {
                            $insert = $sql->insert();
                            $insert->into('PM_ServiceMaster')
                                ->values(array('ServiceName' => $covService['name']));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $servId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        $insert = $sql->insert();
                        $insert->into( 'Crm_ProjCoveredServiceTrans' )
                            ->values( array(
                                'ProjectId' => $projectId,
                                'ServiceId' => $servId
                            ));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    // uncovered services
                    foreach($arrUncoveredServices as $uncovService) {

                        $servId = $this->bsf->isNullCheck($uncovService['id'], 'number');
                        if($servId == 0) {
                            $insert = $sql->insert();
                            $insert->into('PM_ServiceMaster')
                                ->values(array('ServiceName' => $uncovService['name']));
                            $stmt = $sql->getSqlStringForSqlObject($insert);
                            $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                            $servId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        }

                        $insert = $sql->insert();
                        $insert->into( 'Crm_ProjUncoveredServiceTrans' )
                            ->values( array(
                                'ProjectId' => $projectId,
                                'ServiceId' => $servId,
                                'Type' => $uncovService['type'],
                                'Amount' => $this->bsf->isNullCheck($uncovService['amount'], 'number')
                            ));
                        $stmt = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);
                    }

                    if(isset($postData['saveNext'])) {
                        $this->redirect()->toRoute('crm/payment', array('controller' => 'project', 'action' => 'payment', 'projectId' => $this->bsf->encode($projectId)));
                    } else {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }

                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request

                try {

                    $projectId = $this->bsf->isNullCheck($this->bsf->decode($this->params()->fromRoute('projectId')), 'number');
                    if($projectId == 0) {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }
                    $this->_view->projectId = $projectId;

                    // Project Info
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'))
                        ->where(array("ProjectId"=>$projectId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $projectDetail = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if(empty($projectDetail)) {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'index'));
                    }
                    $this->_view->projectDetail = $projectDetail;

                    $select = $sql->select();
                    $select->from('Crm_ProjPropertyManagement')
                        ->where(array('ProjectId' => $projectId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $projPropertyMgmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if(!empty($projPropertyMgmt)) {
                        $this->_view->projPropertyMgmt = $projPropertyMgmt;

                        // covered services
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ProjCoveredServiceTrans'))
                            ->join(array('b' => 'PM_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('ServiceName'), $select::JOIN_LEFT)
                            ->where(array('ProjectId' => $projectId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrCoveredServices = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(!empty($arrCoveredServices)) {
                            $this->_view->jsonCoveredServices = json_encode($arrCoveredServices);
                        }

                        // uncovered services
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ProjUncoveredServiceTrans'))
                            ->join(array('b' => 'PM_ServiceMaster'), 'a.ServiceId=b.ServiceId', array('ServiceName'), $select::JOIN_LEFT)
                            ->where(array('ProjectId' => $projectId));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $arrUncoveredServices = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(!empty($arrUncoveredServices)) {
                            $this->_view->jsonUncoveredServices = json_encode($arrUncoveredServices);
                        }
                    }

                    // Projects
                    $select = $sql->select();
                    $select->from('Proj_ProjectMaster')
                        ->columns(array('ProjectId','ProjectName'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrProjects = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //services
                    $select = $sql->select();
                    $select->from('PM_ServiceMaster')
                        ->columns(array('data' => 'ServiceId', 'value' => 'ServiceName'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrServices = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->jsonServices = json_encode($arrServices);

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

    /*
	 public function otherCostlistAction() {
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

                   echo  $projectId = $this->bsf->isNullCheck($this->params()->fromPost('UnitId'), 'number' );
                    echo $otherCostId = $this->params()->fromPost('otherCostId');die;
                    if($projectId == 0) {
                        throw new \Exception('Invalid Unit-id!');
                    }
					// subQuery

					$subQuery = $sql->select();
					$subQuery->from(array("a" => "Crm_ExtraBillTrans"))
							->join(array("b" => "Crm_ExtraBillRegister"), "a.ExtraBillRegisterId=b.ExtraBillRegisterId", array(), $subQuery::JOIN_INNER)
							->columns(array('ExtraItemId'))
							 ->where(array('b.UnitId'=>$UnitId));
                    // extra item list
                    $select = $sql->select();
                    $select->from(array("a" => "Crm_UnitExtraItemTrans"))
                        ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array('data' => 'ExtraItemId', 'value' => 'ItemDescription','Code'=>'Code'), $select::JOIN_INNER)
                        ->join(array("c" => "Proj_UOM"), "c.UnitId=b.MUnitid", array('UnitName'=>'UnitName'), $select::JOIN_INNER)
                        ->columns(array('Rate'=>'Rate','Amount'=>'Amount','Quantity'=>'Quantity'),array('data' => 'ExtraItemId', 'value' => 'ItemDescription','Code'=>'Code'),array('UnitName'=>'UnitName'))
                        ->where(array('a.UnitId'=>$UnitId))
						->where->expression('a.ExtraItemId Not IN ?', array($subQuery));
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
    }*/

    public function planBasedDiscountAction() {
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
                return $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
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
                    $postData = $request->getPost();
                    $mode = $this->bsf->isNullCheck($postData['Type'], 'string');

                    if($mode=="check") {
                        $planName = $this->bsf->isNullCheck($postData['PlanName'], 'string');
                        $planId = $this->bsf->isNullCheck($postData['PlanId'], 'number');

                        $select = $sql->select();
                        $select->from('Crm_PlanBasedDiscount')
                            ->columns(array('PlanId'));
                        if($planId!=0) {
                            $select->where("DeleteFlag='0' AND PlanName='$planName' AND PlanId<> $planId");
                        } else {
                            $select->where("DeleteFlag='0' AND PlanName='$planName'");

                        }
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $client = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if (count($client) > 0) {
                            $response->setStatusCode(201)->setContent('Failed');
                            return $response;
                        }

                        $response->setStatusCode(200)->setContent('Not used');
                    }
                    $this->_view->setTerminal(true);
                    return $response;
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
            // Normal request
            $request = $this->getRequest();
            if ($request->isPost()) {
                // POST request
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {

                    $postData = $request->getPost();

                    $planName = $this->bsf->isNullCheck($postData['plan_name'], 'string');
                    $discountType = $this->bsf->isNullCheck($postData['discount_type'], 'string');
                    if($discountType=='L' || $discountType=='P') {
                        $lumpType = $this->bsf->isNullCheck($postData['lump_type'], 'string');
                        if($lumpType=='R'){
                            $receiptType = $this->bsf->isNullCheck($postData['receipt_type'], 'number');

                        } else{
                            $receiptType=0;
                        }

                    } else {
                        $lumpType="";
                        $receiptType=0;
                    }
                    $discountValue = $this->bsf->isNullCheck($postData['discount_value'], 'number');
                    $planId = $this->bsf->isNullCheck($postData['plan_id'], 'number');

                    if($planId==0) {
                        $insert = $sql->insert('Crm_PlanBasedDiscount')
                            ->values(array(
                                'PlanName' => $planName,
                                'DiscountType' => $discountType,
                                'LumpsumType' => $lumpType,
                                'ReceiptType' => $receiptType,
                                'Discount' => $discountValue,
                                'CreatedDate' => date('Y-m-d H:i:s')
                            ));
                        $statement = $sql->getSqlStringForSqlObject($insert);
                        $results = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {
                        $update = $sql->update();
                        $update->table('Crm_PlanBasedDiscount')
                            ->set(array(
                                'PlanName' => $planName,
                                'DiscountType' => $discountType,
                                'LumpsumType' => $lumpType,
                                'ReceiptType' => $receiptType,
                                'Discount' => $discountValue
                            ))
                            ->where(array('PlanId'=>$planId));
                        $statement = $sql->getSqlStringForSqlObject($update);
                        $results   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();

                    $FeedId = $this->params()->fromQuery('FeedId');
                    $AskId = $this->params()->fromQuery('AskId');
                    if(isset($FeedId) && $FeedId!="") {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'plan-discount-grid'), array('query'=>array('AskId'=>$AskId,'FeedId'=>$FeedId,'type'=>'feed')));
                    } else {
                        $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'plan-discount-grid'));
                    }

//                    $this->redirect()->toRoute('crm/default', array('controller' => 'project', 'action' => 'plan-discount-grid'));

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                // GET request
                try {

                    $planId = $this->bsf->isNullCheck($this->params()->fromRoute('PlanId'),'number');

                    if($planId!=0) {
                        $select = $sql->select();
                        $select->from('Crm_PlanBasedDiscount')
                            ->columns(array('*'))
                            ->where(array('PlanId'=>$planId));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    }
                    $select = $sql->select();
                    $select->from('Crm_ReceiptTypeMaster')
                        ->where(array('ReceiptType'=>'s'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrResults = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $this->_view->planId = $planId;
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

    public function planDiscountGridAction(){
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
        $request = $this->getRequest();
        $response = $this->getResponse();
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
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {
                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();

                try {
                    //Write your Ajax post code here
                    $postData = $request->getPost();
                    $planId = $this->bsf->isNullCheck($postData['planId'], 'number');
                    $deleteRemarks = $this->bsf->isNullCheck($postData['DeleteRemarks'], 'string');
                    if($planId == 0) {
                        throw new \Exception('Invalid Plan Id!');
                    }
                    if(is_null($deleteRemarks) || strlen($deleteRemarks) <= 0) {
                        throw new \Exception('Invalid Delete Remarks!');
                    }
                    $update = $sql->update();
                    $update->table('Crm_PlanBasedDiscount')
                        ->set(array('DeleteFlag' => 1, 'Remarks' => $deleteRemarks,'DeletedOn'=>date('Y-m-d H:i:s')))
                        ->where(array('PlanId' => $planId));
                    $stmt = $sql->getSqlStringForSqlObject($update);
                    $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();

                    $result =  "Success";
                    $this->_view->setTerminal(true);
                    $response->setStatusCode(200);
                    $response->setContent($result);
                } catch(PDOException $e){
                    $connection->rollback();
                    $response->setStatusCode(500);
                    $response->setContent('Internal error!');
                } catch(\Exception $e) {
                    $connection->rollback();
                    $response->setStatusCode(400);
                    $response->setContent($e->getMessage());
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
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            } else {
                $select = $sql->select();
                $select->from('Crm_ReceiptTypeMaster');
                $stmt = $sql->getSqlStringForSqlObject($select);
                $this->_view->arrResults = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array('a'=>'Crm_PlanBasedDiscount'))
                    ->join(array("b"=>new Expression("Crm_ReceiptTypeMaster")), "a.ReceiptType=b.ReceiptTypeId", array('ReceiptTypeName','DiscountType' => new Expression("Case When a.DiscountType='R' then 'Rate/Sq.ft ' When a.DiscountType='L' then 'Lump sum'  When a.DiscountType='P' then 'Percentage ' else ' ' End"),'LumpsumType' => new Expression("Case When a.LumpsumType='O' then 'Over All ' When a.LumpsumType='R' then 'Receipt Wise ' else ' ' End")), $select::JOIN_LEFT)
                    ->columns(array('*'))
                    ->where(array("a.DeleteFlag"=>0));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();

            }


            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function statementAccountPrintAction() {
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
                    $this->_view->setTerminal(true);
                    $unitId = $this->params()->fromRoute('UnitId');
                    if(preg_match('/^[\d]+$/', $unitId) == FALSE) {
                        throw new \Exception('Invalid Unit-Id');
                    }

                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_UnitBooking'))
                        ->join(array("b" => "Crm_Leads"), "a.LeadId=b.LeadId", array('LeadName','LeadId'), $select::JOIN_LEFT)
                        ->join(array("d" => "Crm_UnitDetails"), "a.UnitId=d.UnitId", array('Rate'), $select::JOIN_LEFT)
                        ->join(array("e" => "KF_UnitMaster"), "a.UnitId=e.UnitId", array('UnitArea','UnitNo','UnitTypeId'), $select::JOIN_LEFT)
                        ->join(array("f" => "Proj_ProjectMaster"), "e.ProjectId=f.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
                        ->join(array("g" => "Crm_UnitTypeMaster"), "e.UnitTypeId=g.UnitTypeId", array('UnitTypeName'), $select::JOIN_LEFT)
                        ->columns(array("BookingDate","DeleteFlag"))
                        ->where(array('a.UnitId' => $unitId,'a.DeleteFlag'=>0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitBooking = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if(empty($unitBooking)) {
                        throw new \Exception('Invalid Statement of Account!');
                    }

                    //Extra Bill
                    $select = $sql->select();
                    $select->from(array("a"=>"Crm_ExtraBillRegister"))
                        ->columns( array( 'BillAmount' => new Expression("Sum(a.Amount)"),'NetAmount'=> new Expression("Sum(a.NetAmount)"),'QualAmount'=> new Expression("Sum(a.QualAmount)"),
                            'PaidAmount'=>new Expression("Sum(a.PaidAmount)")))
                        //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId  and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select1::JOIN_LEFT)
                        ->where("a.UnitId=$unitId")
                        ->where("a.CancelId=0");
                    // $select->group(new Expression('a.ExtraBillDate,a.ExtraBillRegisterId,a.ExtraBillNo,a.NetAmount,a.amount,a.QualAmount'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $extraamt  = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->Current();

                    $select = $sql->select();
                    $select->from(array("a"=>"Crm_ExtraBillRegister"))
                        ->columns( array( 'ExtraBillRegisterId'))
                        //->join(array('b' => 'Crm_ReceiptAdjustment'), 'a.ExtraBillRegisterId=b.ExtraBillRegisterId  and a.UnitId=b.UnitId', array('PaidAmount' => new Expression("Sum(b.Amount)")), $select1::JOIN_LEFT)
                        ->where("a.UnitId=$unitId")
                        ->where("a.CancelId=0");
                    // $select->group(new Expression('a.ExtraBillDate,a.ExtraBillRegisterId,a.ExtraBillNo,a.NetAmount,a.amount,a.QualAmount'));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $extraArr  = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($extraArr as &$eArr) {
                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                            ->join(array("b" => "Crm_ReceiptAdjustment"), new expression("a.ReceiptAdjId=b.ReceiptAdjId"), array(), $select::JOIN_LEFT)
                            ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array('ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode'), $select::JOIN_LEFT)
                            ->columns(array('Amount', 'QualAmount', 'NetAmount'))
                            ->where(array('b.ExtraBillRegisterId'=>$eArr['ExtraBillRegisterId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $eArr['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    }

                    $select = $sql->select();
                    $select->from(array('a'=>'Crm_PaymentScheduleUnitTrans'))
                        ->join(array("e"=>"Crm_DescriptionMaster"), NEW Expression("a.StageId=e.DescriptionId and a.StageType='D' "), array(), $select::JOIN_LEFT)
                        ->join(array("f"=>"Crm_OtherCostMaster"), NEW Expression("a.StageId=f.OtherCostId and a.StageType='O'"), array(), $select::JOIN_LEFT)
                        ->join(array("g"=>"KF_StageMaster"), NEW Expression("a.StageId=g.StageId"), array(), $select::JOIN_LEFT)
                        ->columns(array(new Expression("Case When a.StageType='S' then g.StageName when a.StageType='O' then f.OtherCostName
								When a.StageType='D' then e.DescriptionName When a.StageType='A' then 'Booking Advance' end as StageName"),"PaymentScheduleUnitTransId","StageId","StageType"))
                        ->where(array('a.UnitId' => $unitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $stageList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                    foreach($stageList as &$stage) {

                        $select = $sql->select();
                        $select->from(array('a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans'))
                            ->join(array("b" => "crm_ReceiptTypeMaster"), "a.ReceiptTypeId=b.ReceiptTypeId", array(), $select::JOIN_LEFT)
                            ->columns(array('DueAmount' => new Expression("Sum(a.NetAmount)")))
                            ->where(array('a.PaymentScheduleUnitTransId' => $stage['PaymentScheduleUnitTransId']));
                        $stmt = $sql->getSqlStringForSqlObject($select);
                        $stage['Type'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if($stage['StageType']!="A") {
                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                                ->join(array("b" => "Crm_ReceiptAdjustment"), "a.ReceiptAdjId=b.ReceiptAdjId", array(), $select::JOIN_LEFT)
                                ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array('ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode'), $select::JOIN_LEFT)
                                ->columns(array('Amount', 'QualAmount', 'NetAmount'))
                                ->where(array('b.StageId' => $stage['StageId'], 'b.StageType' => $stage['StageType'], 'c.UnitId' => $unitId, 'c.LeadId' => $unitBooking['LeadId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $stage['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptAdjustmenttrans'))
                                ->join(array("b" => "Crm_ReceiptAdjustment"), "a.ReceiptAdjId=b.ReceiptAdjId", array(), $select::JOIN_LEFT)
                                ->join(array("c" => "Crm_ReceiptRegister"), new expression("b.ReceiptId=c.ReceiptId and c.CancelId=0"), array(), $select::JOIN_LEFT)
                                ->columns(array('Amt' => new Expression("Sum(a.NetAmount)")))
                                ->where(array('b.StageId' => $stage['StageId'], 'b.StageType' => $stage['StageType'], 'c.UnitId' => $unitId, 'c.LeadId' => $unitBooking['LeadId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $stage['TotPaid'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        } else {

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptRegister'))
                                ->columns(array('receiptId','ReceiptNo', 'ReceiptDate', 'TransNo', 'TransDate', 'ReceiptMode','Amount', 'QualAmount', 'NetAmount'))
                                ->where(array('a.CancelId'=>0,'a.UnitId' => $unitId, 'a.ReceiptAgainst' => 'A', 'a.LeadId' => $unitBooking['LeadId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $stage['Details'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $select = $sql->select();
                            $select->from(array('a' => 'Crm_ReceiptRegister'))
                                ->columns(array('Amt' => new Expression("Sum(a.Amount)")))
                                ->where(array('a.CancelId'=>0,'a.UnitId' => $unitId, 'a.ReceiptAgainst' => 'A', 'a.LeadId' => $unitBooking['LeadId']));
                            $stmt = $sql->getSqlStringForSqlObject($select);
                            $stage['TotPaid'] = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        }
                    }


                    $pdfHtml = $this->generateStatementOfAccountPdf($stageList,$extraamt,$extraArr,$viewRenderer);

//echo $pdfHtml; die;
                    require_once(getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php");
                    $dompdf = new DOMPDF();

                    $dompdf->load_html($pdfHtml);

                    $paper_orientation = 'A4';
                    $customPaper = array(0,0,950,600);
                    $dompdf->set_paper($customPaper,$paper_orientation);
                    $dompdf->render();

                    $canvas = $dompdf->get_canvas();

                    $canvas->page_text(275, 925, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                    $dompdf->stream("Statement Of Account.pdf");

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

    private function generateStatementOfAccountPdf($stageList,$extraamt,$extraArr,$viewRenderer) {

        $pdfHtml = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Statement of Account</title>
</head>
<style>
html,body {width: 100% ;height:100%;}
table {border-collapse:collapse;border: 1px solid #000;}
td, th {border: 1px solid #000;text-align: left;padding:12px;border-right:1px solid #000 !important;}

</style>
<body>
<h1 style="text-align:center;padding-top:10px;padding-bottom:10px;color:#7c0c9a;font-family: arial, sans-serif;">Statement of Account</h1>
	<table align="center" style="font-family: arial,sans-serif;border: 1px solid #000;">
			<tr style="background:#4CAF50;color:#fff;border: 1px solid #000;">
				<th width="154px" style="text-align:left;">Description</th>
				<th width="154px" style="text-align:right;">Stage Value</th>
				<th width="154px" style="text-align:right;">Paid</th>
				<th width="154px" style="text-align:left;">Due</th>
			</tr>


EOT;
        $totStageVal=0; $totAllPaid=0; $totAllDue=0; if(isset($extraamt)) {
            $totStageVal=$extraamt['NetAmount'];
            $totAllPaid=$extraamt['PaidAmount'];
            $totAllDue=floatval($extraamt['NetAmount'])-floatval($extraamt['PaidAmount']);
            if($extraamt['NetAmount']!=0) {
                $netAmt = $viewRenderer->commonHelper()->sanitizeNumber($extraamt['NetAmount'], 2, true);
            } else {
                $netAmt = 0;
            }
            if($extraamt['PaidAmount']!=0) {
                $PAmt = $viewRenderer->commonHelper()->sanitizeNumber($extraamt['PaidAmount'], 2, true);
            } else {
                $PAmt = 0;
            }
            $totBal = floatval($extraamt['NetAmount'])-floatval($extraamt['PaidAmount']);
            if($totBal!=0) {
                $totBal = $viewRenderer->commonHelper()->sanitizeNumber($totBal, 2, true);
            } else { $totBal= 0; }
            $pdfHtml .= <<<EOT
			<tr style="border: 1px solid #000;">
				<td width="154px" style="color:#337ab7;" >Extra Bill</td>
				<td width="154px" style="text-align:right;"> {$netAmt} </td>
				<td width="154px" style="text-align:right;"> {$PAmt} </td>
				<td width="154px" style="text-align:left;"> {$totBal}</td>
			</tr>
			<tr>
				<td colspan="4">
					<table style="width:100%;">
							<tr style="background-color: #dddddd;">
								<th>Receipt date</th>
								<th>Transaction Type</th>
								<th>Transaction No</th>
								<th>Paid Gross</th>
								<th>Paid Tax</th>
								<th>Value</th>
							</tr>


EOT;

        } if(isset($extraArr)) {
            $totalval = 0;
            foreach ($extraArr as $eArr) {
                foreach ($eArr['Details'] as $eA) { $totalval+=floatval($eA['NetAmount']);
                    if($eA['ReceiptDate']!="" && strtotime($eA['ReceiptDate'])!=false) {
                        $BookingDate = date('d-m-Y', strtotime($eA['ReceiptDate']));
                    } else {
                        $BookingDate="";
                    }
                    if ($eA['Amount'] != 0) {
                        $eAmt = $viewRenderer->commonHelper()->sanitizeNumber($eA['Amount'], 2, true);
                    } else {
                        $eAmt=0;
                    }
                    if ($eA['QualAmount'] != 0) {
                        $eQAmt = $viewRenderer->commonHelper()->sanitizeNumber($eA['QualAmount'], 2, true);
                    } else {
                        $eQAmt = 0;
                    }
                    if ($eA['NetAmount'] != 0) {
                        $eNAmt = $viewRenderer->commonHelper()->sanitizeNumber($eA['NetAmount'], 2, true);
                    } else {
                        $eNAmt = 0;
                    }
                    $pdfHtml .= <<<EOT
							<tr>
								<td>{$BookingDate}</td>
								<td>{$eA['ReceiptMode']}</td>
								<td>{$eA['TransNo']}</td>
								<td>{$eAmt}</td>
								<td>{$eQAmt}</td>
								<td>{$eNAmt}</td>
							</tr>
EOT;
                }

            }
        }
        if($totalval!=0) { $tMAmt = $viewRenderer->commonHelper()->sanitizeNumber($totalval,2,true); } else { $tMAmt = 0; }
        $pdfHtml .= <<<EOT
							<tr>
								<td colspan="4">&nbsp;</td>
								<td>Total</td>
								<td>{$tMAmt}</td>
							</tr>

						</table>

				</td>
			</tr>
EOT;
        if(isset($stageList)) { $i=1;
            foreach($stageList as $stage) {
                $totStageVal += floatval($stage['Type']['DueAmount']);
                $totAllPaid += floatval($stage['TotPaid']['Amt']);
                $totAllDue += floatval($stage['Type']['DueAmount'])-floatval($stage['TotPaid']['Amt']);
                if($stage['Type']['DueAmount']!=0) {
                    $netAmt = $viewRenderer->commonHelper()->sanitizeNumber($stage['Type']['DueAmount'], 2, true);
                } else {
                    $netAmt = 0;
                }
                if($stage['TotPaid']['Amt']!=0) {
                    $PAmt = $viewRenderer->commonHelper()->sanitizeNumber($stage['TotPaid']['Amt'], 2, true);
                } else {
                    $PAmt = 0;
                }
                $totBal = floatval($stage['Type']['DueAmount'])-floatval($stage['TotPaid']['Amt']); if($totBal!=0) { $totBal = $viewRenderer->commonHelper()->sanitizeNumber($totBal, 2, true);} else { $totBal = 0; }
                $pdfHtml .= <<<EOT
                                            <tr>
                                                <td  style="color:#337ab7;"> {$stage['StageName']} </td>
                                                <td style="text-align: right;" > {$netAmt} </td>
                                                <td style="text-align: right;" > {$PAmt} </td>
                                                <td style="text-align: right;"> {$totBal} </td>
                                            </tr>
                                            <tr>
                                                <td colspan="4">
													<table style="width:100%;">
														<tr style="background-color: #dddddd;">
															<th>Receipt date</th>
															<th>Transaction Type</th>
															<th>Transaction No</th>
															<th>Paid Gross</th>
															<th>Paid Tax</th>
															<th style="border-right: 1px solid #000;">Value</th>
														</tr>


EOT;
                $totalval = 0; foreach($stage['Details'] as $st) { $totalval+=floatval($st['NetAmount']);
                    if($st['ReceiptDate']!="" && strtotime($st['ReceiptDate'])!=false) {
                        $erDate = date('d-m-Y', strtotime($st['ReceiptDate']));
                    } else {
                        $erDate="";
                    }

                    if($st['Amount']!=0) {
                        $stAmt = $viewRenderer->commonHelper()->sanitizeNumber($st['Amount'],2,true);
                    } else {
                        $stAmt =  0;
                    }
                    if($st['QualAmount']!=0) {
                        $stQAmt = $viewRenderer->commonHelper()->sanitizeNumber($st['QualAmount'],2,true);
                    } else {
                        $stQAmt = 0;
                    }
                    if($st['NetAmount']!=0) {
                        $stNAmt = $viewRenderer->commonHelper()->sanitizeNumber($st['NetAmount'],2,true);
                    } else {
                        $stNAmt = 0;
                    }
                    $pdfHtml .= <<<EOT
                                                                <tr>
                                                                    <td> {$erDate} </td>
                                                                    <td> {$st['ReceiptMode']} </td>
                                                                    <td> {$st['TransNo']} </td>
                                                                    <td> {$stAmt} </td>
                                                                    <td> {$stQAmt} </td>
                                                                    <td style="border-right: 1px solid #000;"> {$stNAmt} </td>
                                                                </tr>
EOT;
                }
                if($totalval!=0) {
                    $totalval = $viewRenderer->commonHelper()->sanitizeNumber($totalval,2,true);
                } else {
                    $totalval = 0;
                }
                $pdfHtml .= <<<EOT
                                                            <tr>
                                                                <td colspan="4">&nbsp;</td>
                                                                <td>Total</td>
                                                                <td style="border-right: 1px solid #000;"> {$totalval} </td>
                                                            </tr>

                                                        </table>

                                                </td>
                                            </tr>
EOT;
            } } else {
            $pdfHtml .= <<<EOT
                                        <tr><td colspan='8' style='color:#e80f0f;text-align:center;padding-top:20px;padding-bottom:20px;'>No Data Found</td></tr>
EOT;
        }
        $pdfHtml .= <<<EOT
                                    </tbody>
EOT;
        if(isset($stageList)) {
            $pdfHtml .= <<<EOT
                                        <tr style="background:#9bbaf7;color:#000;">
                                            <td style="text-align:right;">Total</td>
                                            <td>{$viewRenderer->commonHelper()->sanitizeNumber($totStageVal, 2, true)}</td>
                                            <td>{$viewRenderer->commonHelper()->sanitizeNumber($totAllPaid, 2, true)}</td>
                                            <td style="border-right: 1px solid #000;">{$viewRenderer->commonHelper()->sanitizeNumber($totAllDue, 2, true)}</td>
                                        </tr>
EOT;
        }
        $pdfHtml .= <<<EOT
                                </table>
                                </body>

</html>

EOT;

        return $pdfHtml;
    }

    public function addParkTypeAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $ptName = $postParams['ptName'];

                if($ptName != '') {
                    $insert = $sql->insert('Crm_CarParkTypeMaster')
                        ->values(array(
                                'TypeName' => $ptName)
                        );
                    $statement = $sql->getSqlStringForSqlObject($insert);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $select = $sql->select();
                $select->from('Crm_CarParkTypeMaster')
                    ->columns(array('TypeId','TypeName'));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function checkStageNameAction() {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $countArr[] = array();
                $sql = new Sql($dbAdapter);
                $select = $sql->select();
                $select->from('Crm_DescriptionMaster')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("DescriptionName = '".$postParams['descName']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $countArr['dCount'] = $result1['Count'];

                $select = $sql->select();
                $select->from('KF_StageMaster')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("StageName = '".$postParams['descName']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $countArr['sCount'] = $result2['Count'];

                $select = $sql->select();
                $select->from('Crm_OtherCostMaster')
                    ->columns(array("Count" => new Expression("Count(*)")))
                    ->where("OtherCostName = '".$postParams['descName']."'");
                $statement = $sql->getSqlStringForSqlObject($select);
                $result3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $countArr['oCount'] = $result3['Count'];
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($countArr));
            return $response;
        }
    }

    public function getBlocksAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $typeId = $postParams['typeId'];
                $projectId = $this->bsf->decode($postParams['projectId']);

                $sql = new Sql($dbAdapter);

                $subQuery = $sql->select();
                $subQuery->from('Crm_CarParkMaster')
                    ->columns(array(new Expression('DISTINCT(BlockId) as BlockId')))
                    ->where(array('ProjectId' => $projectId, 'TypeId' => $typeId, 'IsOther' => 0));

                $select = $sql->select();
                $select->from(array("a"=>'KF_BlockMaster'))
                    ->columns(array('BlockId','BlockName'))
                    ->join(array("b"=>"KF_PhaseMaster"),"a.PhaseId=b.PhaseId", array('PhaseId','PhaseName'), $select::JOIN_LEFT)
                    ->where(array("a.ProjectId"=>$projectId))
                    ->where->expression('a.BlockId NOT IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            }
            $this->_view->setTerminal(true);
            $response = $this->getResponse()->setContent(json_encode($result));
            return $response;
        }
    }

    public function buyerreceivableabstractAction(){
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
        $PBId = 5102;
        $UnitId = $this->params()->fromRoute('unitId');;
        $sql = new Sql($dbAdapter);
        $selectStage = $sql->select();
        $selectStage->from(array("a"=>"Crm_ProgressBill"));
        $selectStage->columns(array(new Expression("a.ProjectId,a.BlockId,Convert(varchar(10),a.BillDate,105) as BillDate,a.ProgressNo, a.DemandApproval,
                            Case When a.StageType='S' then G.StageName when a.StageType='O' then F.OtherCostName When a.StageType='D' then E.DescriptionName end as StageName,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                            When a.StageType='D' then 'DescriptionName' end as Stage,a.StageType,b.ProjectName,c.BlockName,c.BlockId,d.FloorName,d.FloorId,a.StageType,a.StageId")),array())
            ->join(array("i"=>"Crm_ProgressBillTrans"), new Expression("a.ProgressBillId=i.ProgressBillId and i.CancelId=0"), array('UnitId'), $selectStage::JOIN_LEFT)
            ->join(array("b"=>"Proj_ProjectMaster"), "a.ProjectId=b.ProjectId", array('ProjectName'), $selectStage::JOIN_LEFT)
            ->join(array("h"=>"WF_CompanyMaster"), "h.CompanyId=b.CompanyId", array('CompanyName', 'Address', 'Mobile', 'Email', 'Photo'), $selectStage::JOIN_LEFT)
            ->join(array("c"=>"KF_BlockMaster"), "a.BlockId=c.BlockId", array(), $selectStage::JOIN_LEFT)
            ->join(array("d"=>"KF_FloorMaster"), "a.FloorId=d.FloorId", array(), $selectStage::JOIN_LEFT)
            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $selectStage::JOIN_LEFT)
            ->join(array("f"=>"Crm_OtherCostMaster"), "a.StageId=f.OtherCostId", array(), $selectStage::JOIN_LEFT)
            ->join(array("g"=>"KF_StageMaster"), "a.StageId=g.StageId", array(), $selectStage::JOIN_LEFT)
            ->where(array('i.UnitId' => $UnitId,'a.DeleteFlag'=>0));
       $statement = $sql->getSqlStringForSqlObject($selectStage);
        $progressBill = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
        //unitTable

        $selectUnit = $sql->select();
        $selectUnit->from(array("a"=>"Crm_ProgressBill"));
        $selectUnit->columns(array(new Expression("b.UnitId,e.UnitNo,b.PBNo,d.StageName,a.StageCompletionId,Convert(varchar(10),a.BillDate,105) as BillDate,a.CreditDays,b.UnitId,d.StageName,d.StageId,e.UnitNo,a.StageType, b.ProgressBillTransId,b.Amount,b.NetAmount,Case When a.StageType='S' then 'Stage' when a.StageType='O' then 'OtherCostName'
                            When a.StageType='D' then 'DescriptionName' end as Stage")))
            ->join(array("b"=>"Crm_ProgressBillTrans"), "a.ProgressBillId=b.ProgressBillId", array(), $selectUnit::JOIN_LEFT)
            ->join(array("d"=>"KF_StageMaster"), "a.StageId=d.StageId", array(), $selectUnit::JOIN_LEFT)
            ->join(array("e"=>"KF_UnitMaster"), "b.UnitId=e.UnitId", array(), $selectUnit::JOIN_LEFT)
            ->join(array("f"=>"Crm_UnitBooking"),new Expression("b.UnitId=f.UnitId and f.DeleteFlag=0"), array('BuyerName' => 'BookingName'), $selectStage::JOIN_LEFT)
            ->join(array("g"=>"Crm_Leads"), "g.LeadId=f.LeadId", array('Mobile', 'Email'), $selectStage::JOIN_LEFT)
            ->join(array("h"=>"Crm_UnitType"), "h.UnitTypeId=e.UnitTypeId", array('IntPercent'), $selectStage::JOIN_LEFT)
            ->where(array('b.UnitId' => $UnitId,'a.DeleteFlag'=>0));
         $statement = $sql->getSqlStringForSqlObject($selectUnit);
        $selectUnit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        foreach($selectUnit as &$unit){
            $SelectReceiptType = $sql->select();
            $SelectReceiptType->from(array("a"=>"Crm_ProgressBillReceiptTypeTrans"));
            $SelectReceiptType->columns( array('PBReceiptTypeTransId','ReceiptTypeId','Percentage','Amount','QualAmount', 'NetAmount' ,'ReceiptTypeName' => new Expression("B.ReceiptTypeName")))
                ->join(array("b"=>"Crm_ReceiptTypeMaster"), "a.ReceiptTypeId=b.ReceiptTypeId", array(), $SelectReceiptType::JOIN_INNER)
                ->where(array('a.ProgressBillTransId' => $unit['ProgressBillTransId']));
            $statement = $sql->getSqlStringForSqlObject($SelectReceiptType);
            $arrTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $unit['ReceiptTypeTrans'] = $arrTrans;
        }

        $this->_view->progressBill = $progressBill;
        $this->_view->unit = $selectUnit;
        $this->_view->PBId = $PBId;

        /*
        select a.ProjectName,d.CompanyName from Proj_ProjectMaster a
        LEFT JOIN WF_OperationalCostCentre b on a.ProjectId=b.ProjectId
        LEFT JOIN WF_CostCentre c on b.FACostCentreId=c.CostCentreId
        LEFT JOIN WF_CompanyMaster d on c.CompanyId=d.CompanyId
        */
        $select = $sql->select();
        $select->from(array('a' => 'KF_UnitMaster'))
            ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId, Case When i.BookingId > 0 then 1 else  0  End as count")))
            ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
            ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
            ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
            ->join(array("d"=>"Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac"=>"Description"), $select::JOIN_LEFT)
            ->join(array("e"=>"Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status"=>"Description"), $select::JOIN_LEFT)
            ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT)
            ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('B00kRate'=>'Rate','BOther'=>'OtherCostAmount','BQual'=>'QualifierAmount','BDiscountType'=>'DiscountType','BNet'=>'NetAmount','BBase'=>'BaseAmount','BConst'=>'ConstructionAmount',"BOther"=>'OtherCostAmount','BLand'=>'LandAmount','BookingStatus' => new Expression("CAST ( CASE WHEN i.BookingId IS NOT NULL THEN 'Sold' ELSE '' END AS varchar(11))"),'BookingId','Approve','BDiscount'=>new Expression("isnull(i.Discount,0)")), $select::JOIN_LEFT)
            ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId','PrevRate'=>'Rate','base'=>'BaseAmount','const'=>'ConstructionAmount','land'=>'LandAmount','gross'=>'GrossAmount','PostDiscount',"other"=>'OtherCostAmount',"PostDiscountType","qual"=>'QualifierAmount',"net"=>'NetAmount','PRate'=>'Rate'), $select::JOIN_LEFT)
            ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array('BlockId','ValidUpto','BlockBAdv'=>'AdvAmnt','Blockbase'=>'BaseAmount','BRate','BlockDiscount'=>'Discount','Blockconst'=>'ConstructionAmount','Blockland'=>'LandAmount','Blockgross'=>'GrossAmount',"Blockother"=>'OtherCost',"Blockqual"=>'QualAmount',"Blocknet"=>'NetAmount','BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
            ->join(array('x' => 'Crm_UnitPreBooking'),new Expression("a.UnitId=x.UnitId and x.DeleteFlag=0"), array('PreBookingId','ValidUpto','PreBAdv'=>'AdvAmount','Prebase'=>'BaseAmount','PreRate'=>'PRate','PreDiscount'=>'Discount','Preconst'=>'ConstructionAmount','Preland'=>'LandAmount','Pregross'=>'GrossAmount',"Preother"=>'OtherCost',"Prequal"=>'QualAmount',"Prenet"=>'NetAmount','PreStatus' => new Expression("CAST ( CASE WHEN x.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)                        ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
            ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
            ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto'=>'Photo'), $select::JOIN_LEFT)
            ->join(array('w' => 'Crm_LeadPersonalInfo'), 'w.LeadId=j.LeadId', array('BlockedPhoto'=>'Photo'), $select::JOIN_LEFT)
            ->join(array('y' => 'Crm_Leads'), 'y.LeadId=x.LeadId', array('PreName' => 'LeadName'), $select::JOIN_LEFT)
            ->join(array("c"=>"WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
            ->join(array("m"=>"WF_Users"), "j.ExecutiveId=m.UserId", array("BlockExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
            ->join(array("s"=>"WF_Users"), "y.ExecutiveId=s.UserId", array("PreExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
            ->where(array("a.UnitId"=>$UnitId))
            ->order("o.PostSaleDiscountId desc");
        $stmt = $sql->getSqlStringForSqlObject($select);
        $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $proj = $unitInfo['ProjectId'];

        $select = $sql->select();
        $select->from('Crm_ProjectOtherCostTrans')
            ->columns(array('TotAmount' => new Expression("sum(Amount)")))
            ->where(array('ProjectId' => $proj));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $other = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->other = $other;
        $this->_view->UnitId = $UnitId;


        if(empty($unitInfo)) {
            throw new \Exception('Unit info not found!');
        }

        if($unitInfo['Status'] == 'B') {
            $unitInfo['BuyerName'] = $unitInfo['BlockedName'];
            $unitInfo['ExecutiveName'] = $unitInfo['BlockExecutiveName'];
            $unitInfo['OtherCostAmt'] = 0;
            $unitInfo['NetAmt'] = $unitInfo['Blocknet'];
            $unitInfo['QualifierAmount'] =$unitInfo['Blockqual'];
            $unitInfo['Discount'] =$unitInfo['BlockDiscount'];
            $unitInfo['AdvAmount'] =$unitInfo['BlockBAdv'];
            $unitInfo['BaseAmt'] = $unitInfo['Blockbase'];
            $unitInfo['ConstructionAmount'] = $unitInfo['Blockconst'];
            $unitInfo['GrossAmount'] =$unitInfo['Blockgross'];
            $unitInfo['Rate'] =$unitInfo['BRate'];
            $unitInfo['Rate'] =$unitInfo['BRate'];
            $unitInfo['LandAmount'] =$unitInfo['Blockland'];
        }
        else if($unitInfo['Status'] == 'P') {
            $unitInfo['BuyerName'] = $unitInfo['PreName'];
            $unitInfo['ExecutiveName'] = $unitInfo['PreExecutiveName'];
            $unitInfo['OtherCostAmt'] =0;
            $unitInfo['NetAmt'] = $unitInfo['Prenet'];
            $unitInfo['QualifierAmount'] =$unitInfo['Prequal'];
            $unitInfo['Discount'] =$unitInfo['PreDiscount'];
            $unitInfo['AdvAmount'] =$unitInfo['PreBAdv'];
            $unitInfo['BaseAmt'] = $unitInfo['Prebase'];
            $unitInfo['ConstructionAmount'] = $unitInfo['Preconst'];
            $unitInfo['GrossAmount'] =$unitInfo['Pregross'];
            $unitInfo['Rate'] =$unitInfo['PreRate'];
            $unitInfo['LandAmount'] =$unitInfo['Preland'];
        }
        else if($unitInfo['Status'] == 'U') {
            $unitInfo['BuyerName'] = " ";
            $unitInfo['OtherCostAmt'] =0;
        }
        else if($unitInfo['Status'] == 'R') {
            $unitInfo['OtherCostAmt'] =0;
        }
        if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
            $unitInfo['OtherCostAmt'] = $unitInfo['other'];
            $unitInfo['NetAmt'] = $unitInfo['net'];
            $unitInfo['QualifierAmount'] =$unitInfo['qual'];
            $unitInfo['Discount'] =$unitInfo['PostDiscount'];
            $unitInfo['BaseAmt'] = $unitInfo['base'];
            $unitInfo['ConstructionAmount'] = $unitInfo['const'];
            $unitInfo['GrossAmount'] =$unitInfo['gross'];
            $unitInfo['LandAmount'] =$unitInfo['land'];
            $unitInfo['Rate'] =$unitInfo['PrevRate'];
        }
        else if($unitInfo['count'] == 1) {
            $unitInfo['OtherCostAmt'] = $unitInfo['BOther'];
            $unitInfo['NetAmt'] = $unitInfo['BNet'];
            $unitInfo['QualifierAmount'] =$unitInfo['BQual'];
            $unitInfo['Discount'] =$unitInfo['BDiscount'];
            $unitInfo['BaseAmt'] = $unitInfo['BBase'];
            $unitInfo['ConstructionAmount'] = $unitInfo['BConst'];
            $unitInfo['GrossAmount'] =$unitInfo['BBase']+ $unitInfo['BOther'];
            $unitInfo['LandAmount'] =$unitInfo['BLand'];
            $unitInfo['Rate'] =$unitInfo['B00kRate'];

        }
        $this->_view->unitInfo = $unitInfo;


        // qualifier split-up
        $subQuery = $sql->select();
        $subQuery->from(array('a' => 'Crm_PaymentScheduleQualifierTrans'))
            ->join(array('b' => 'Crm_PaymentScheduleUnitReceiptTypeTrans'), 'a.PSReceiptTypeTransId=b.ReceiptTypeTransId',array(), $subQuery::JOIN_INNER)
            ->columns(array('TotAmount' => new Expression("sum(a.NetAmt)"), 'QualifierId'))
            ->where(array('b.UnitId' => $UnitId))
            ->group('QualifierId');

        $select = $sql->select();
        $select->from(array("g" => $subQuery))
            ->join(array('b' => 'Proj_QualifierMaster'), 'b.QualifierId=g.QualifierId', array('QualifierName'), $select::JOIN_LEFT);
          $stmt = $sql->getSqlStringForSqlObject($select);
        $arrQualifiers = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(!empty($arrQualifiers)) {
            $this->_view->arrQualifiers = $arrQualifiers;
        }
        $select = $sql->select();
        $select->from(array('a'=>'Crm_ReceiptRegister'))
            ->columns(array('Amount'=>new expression("SUM(Amount)")))
            ->where(array('a.UnitId' => $UnitId))
            ->where(array('a.DeleteFlag' =>0))
            ->where("a.ReceiptAgainst='L'");
        $stmt = $sql->getSqlStringForSqlObject($select);
        $arrrlate = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        // check list
        $select = $sql->select();
        $select->from(array('a' => 'Crm_CheckListProjectTrans'))
            ->join(array('b' => 'Crm_CheckListMaster'), 'b.CheckListId=a.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
            //->join(array('c' => 'WF_Users'), 'c.UserId=e.ExecutiveId', array('ExecutiveName' => 'EmployeeName','Date1' => new Expression("CONVERT(varchar(10),a.SubmittedDate,105) ")), $select::JOIN_LEFT)
            ->where(array('a.ProjectId' => $unitInfo['ProjectId'], 'a.DeleteFlag' => 0, 'a.CheckListTypeId' => 1));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $arrCheckList = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
            ->columns(array('*'))
            ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName','StageId'), $select::JOIN_LEFT)
            ->where(array('a.StageType' => 'S', 'UnitId' => $unitInfo['UnitId']));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $payStageDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from(array('a' => 'KF_UnitMaster'))
            ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
            ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
            ->where(array("a.UnitId"=>$UnitId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        foreach($payStageDetails as &$pay){
            $select = $sql->select();
            $select->from(array('a' =>'KF_stageCompletionTrans'))
                ->columns(array('StageCompletionId'))
                ->join(array('b' => 'KF_StageCompletion'), 'a.stageCompletionId=b.stageCompletionId', array(), $select::JOIN_LEFT)
                ->join(array('c' => 'Crm_ProgressBill'), 'a.stageCompletionId=c.stageCompletionId', array('DemandApproval','ProgressBillId'), $select::JOIN_LEFT)
                ->where(array("a.UnitId"=>$unitInfo['UnitId'],"b.stageId"=>$pay['StageId']));
          $statement = $sql->getSqlStringForSqlObject($select);
            $pay['StageDetails'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        }

        $this->_view->payStageDetails=$payStageDetails;

        $select = $sql->select();
        $select->from('Crm_UnitBooking')
            ->where(array('UnitId' => $unitInfo['UnitId'],'DeleteFlag'=>0));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $unitBooking = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->unitBooking = $unitBooking;

        $select = $sql->select();
        $select->from('Crm_PostSaleDiscountRegister')
            ->columns(array('PostSaleDiscountId','BookingId'))
            ->where(array('BookingId' => $unitBooking['BookingId']))
            ->order("PostSaleDiscountId desc");
         $stmt = $sql->getSqlStringForSqlObject($select);
        $unitPostSale = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $postsalecount=count($unitPostSale['BookingId']);
        //payment schedules
        $select = $sql->select();
        $select->from('Crm_PaymentSchedule')
            ->where(array('ProjectId' => $unitInfo['ProjectId'], 'DeleteFlag' => 0));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        // Receipt Type
        $select = $sql->select();
        $select->from('Crm_ReceiptTypeMaster');
        $stmt = $sql->getSqlStringForSqlObject($select);
        $arrResults = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->arrReceiptTypes = $arrResults;
        $arrAllReceiptTypes = array();
        foreach($arrResults as $result) {
            $arrAllReceiptTypes[$result['ReceiptTypeId']] = $result['Type'];
        }
        if( $postsalecount > 0 ){
            $select1 = $sql->select();
            $select1->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                ->where(array('a.StageType' => 'S', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));

            $select2 = $sql->select();
            $select2->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                ->where(array('a.StageType' => 'D', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
            $select2->combine($select1,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                ->where(array('a.StageType' => 'O', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
            $select3->combine($select2,'Union ALL');

            $select4 = $sql->select();
            $select4->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                ->where(array('a.StageType' => 'A', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
            $select4->combine($select3,'Union ALL');

            $select5 = $sql->select();
            $select5->from(array("g"=>$select4))
                ->columns(array('*'))
                ->where(array('BookingId' => $unitBooking['BookingId']))
                ->order("g.SortId ASC");
            $stmt = $sql->getSqlStringForSqlObject($select5);
            $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if(!empty($arrPaymentScheduleDetails)) {

                foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                    // receipt type
                    $select1 = $sql->select();
                    $select1->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                        ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                        ->where( array( 'b.ReceiptType' => 'S', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                    $select2 = $sql->select();
                    $select2->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                        ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                        ->where( array( 'a.ReceiptType' => 'D', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                    $select2->combine( $select1, 'Union ALL' );

                    $select3 = $sql->select();
                    $select3->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                        ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                        ->where( array( 'a.ReceiptType' => 'O', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                    $select3->combine( $select2, 'Union ALL' );

                    $select4 = $sql->select();
                    $select4->from( array( "g" => $select3 ) )
                        ->columns( array( '*' ) )
                        ->order("g.ReceiptTypeTransId ASC");

                    $stmt = $sql->getSqlStringForSqlObject( $select4 );
                    $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(!empty($arrReceiptTypes)) {

                        $iQualCount = 0;
                        foreach($arrReceiptTypes as &$receipt) {

                            switch($receipt['ReceiptType']) {
                                case 'O':
                                    $receipt['Type'] = 'O';
                                    break;
                                case 'S':
                                    $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                    break;
                            }

                            // qualifier
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Crm_PSDPaymentScheduleQualifierTrans' ) )
                                ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                    'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                    'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                            $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if ( !empty( $qualList ) ) {
                                foreach($qualList as &$qual) {
                                    $qual['BaseAmount'] = $receipt['Amount'];
                                }

                                $sHtml = Qualifier::getQualifier( $qualList );
                                $iQualCount = $iQualCount + 1;
                                $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                $receipt[ 'qualHtmlTag' ] = $sHtml;

                            }

                        }

                        $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                    }
                }
            }
        }
        else{

            // current payment schedule detail
            $select1 = $sql->select();
            $select1->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                ->where(array('a.StageType' => 'S', 'BookingId' => $unitBooking['BookingId']));

            $select2 = $sql->select();
            $select2->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                ->where(array('a.StageType' => 'D', 'BookingId' => $unitBooking['BookingId']));
            $select2->combine($select1,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                ->where(array('a.StageType' => 'O', 'BookingId' => $unitBooking['BookingId']));
            $select3->combine($select2,'Union ALL');

            $select4 = $sql->select();
            $select4->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                ->columns(array('*'))
                ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                ->where(array('a.StageType' => 'A', 'BookingId' => $unitBooking['BookingId']));
            $select4->combine($select3,'Union ALL');

            $select5 = $sql->select();
            $select5->from(array("g"=>$select4))
                ->columns(array('*'))
                ->where(array('BookingId' => $unitBooking['BookingId']))
                ->order("g.SortId ASC");
            $stmt = $sql->getSqlStringForSqlObject($select5);
            $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if(!empty($arrPaymentScheduleDetails)) {

                foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                    // receipt type
                    $select1 = $sql->select();
                    $select1->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                        ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                        ->where( array( 'b.ReceiptType' => 'S', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                    $select2 = $sql->select();
                    $select2->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                        ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                        ->where( array( 'a.ReceiptType' => 'D', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                    $select2->combine( $select1, 'Union ALL' );

                    $select3 = $sql->select();
                    $select3->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                        ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                        ->where( array( 'a.ReceiptType' => 'O', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                    $select3->combine( $select2, 'Union ALL' );

                    $select4 = $sql->select();
                    $select4->from( array( "g" => $select3 ) )
                        ->columns( array( '*' ) )
                        ->order("g.ReceiptTypeTransId ASC");

                    $stmt = $sql->getSqlStringForSqlObject( $select4 );
                    $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                    if(!empty($arrReceiptTypes)) {

                        $iQualCount = 0;
                        foreach($arrReceiptTypes as &$receipt) {

                            switch($receipt['ReceiptType']) {
                                case 'O':
                                    $receipt['Type'] = 'O';
                                    break;
                                case 'S':
                                    $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                    break;
                            }

                            // qualifier
                            $select = $sql->select();
                            $select->from( array( 'a' => 'Crm_PaymentScheduleQualifierTrans' ) )
                                ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                    'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                    'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                            $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                            $statement = $sql->getSqlStringForSqlObject( $select );
                            $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                            if ( !empty( $qualList ) ) {
                                foreach($qualList as &$qual) {
                                    $qual['BaseAmount'] = $receipt['Amount'];
                                }

                                $sHtml = Qualifier::getQualifier( $qualList );
                                $iQualCount = $iQualCount + 1;
                                $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                $receipt[ 'qualHtmlTag' ] = $sHtml;

                            }

                        }

                        $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                    }
                }
            }}

        $this->_view->arrPaymentScheduleDetails = $arrPaymentScheduleDetails;
        ///End////
        // Buyer statement
        $select = $sql->select();
        $select->from(array('a' => 'Crm_ProgressBillTrans'))
            ->columns(array('BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'PBNo','BillAmount'=>'Amount','PaidAmount','Balance' => new Expression('Case When Amount-PaidAmount > 0 then Amount-PaidAmount else 0 end'),
                'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
            ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
            // ->join(array("f"=>"KF_StageCompletion"), "a.StageId=f.StageId", array(), $select::JOIN_LEFT)
            ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
            ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT)
            ->where(array("a.UnitId" => $UnitId,'a.CancelId'=>0));
       $stmt = $sql->getSqlStringForSqlObject($select);
        $arrBuyerStmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if(!empty($arrBuyerStmt)) {
            $this->_view->arrBuyerStmt = $arrBuyerStmt;
        }

        $this->_view->arrReceipts = '';
        // receipts
        $select = $sql->select();
        $select->from(array("a" => "Crm_ReceiptRegister"))
            ->columns(array("ReceiptId", "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"),
                "ReceiptAgainst"=> new Expression("Case When ReceiptAgainst ='B' then 'Bill/Schedule' When ReceiptAgainst ='A' then 'Advance'  When ReceiptAgainst='L' then 'LateInterest' When ReceiptAgainst ='O' then 'Others' When ReceiptAgainst ='P' then 'Pre-Booking' else 'N/A 'end") ,"Amount","ReceiptMode"))
            ->where(array('a.DeleteFlag' => 0, 'a.CancelId'=>0,'a.UnitId' => $UnitId));
        $statement = $sql->getSqlStringForSqlObject( $select );
        $arrReceipts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        $this->_view->arrReceipts = $arrReceipts;

        $pdfhtml = $this->generatebuyerreceivableabs($selectUnit, $arrrlate,$progressBill, $unitInfo, $other, $arrPaymentScheduleDetails, $arrReceipts);


        // Print_r($pdfhtml); die;
        $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";
        require_once($path);
        $dompdf = new DOMPDF();
      //  var_dump($pdfhtml);

        $dompdf->load_html($pdfhtml);
        $paper_orientation = 'A4';
        $customPaper = array(0,0,950,970);
        $dompdf->set_paper($customPaper,$paper_orientation);

        $dompdf->render();

      //  $dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
        $canvas = $dompdf->get_canvas();
        $canvas->page_text(105, 970, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

        $dompdf->stream("BuyerReceivableAbstract.pdf");
    }

    private function generatebuyerreceivableabs(array $selectUnit, $arrrlate,$progressBill, $unitInfo, $other, $arrPaymentScheduleDetails, $arrReceipts) {

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
.project span {color: #5D6975;text-align: right;width: 150px;margin-right: 10px;display: inline-block;font-size: 14px;}
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
text-align: left;
}

table th {
padding: 10px 0px;
color: #5D6975;
border-bottom: 1px solid #C1CED9;
white-space: nowrap;
font-weight: 600;
text-align:left;
}

table .service,
table .desc {
text-align: left;
}

table td {
padding: 5px;
text-align: left;
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

EOT;
//        foreach($selectUnit as $selUnit) {
//            $billDate = date('d-m-Y', strtotime($selUnit['BillDate']));
//            $dueDate = date('d-m-Y', strtotime($selUnit['BillDate']));
//            $demandApproval = ($progressBill['DemandApproval'] == 1)? 'Demand Letter': '';
//
//            if(is_numeric($selUnit['CreditDays'])) {
//                $dueDate = date('d-m-Y', strtotime($selUnit['BillDate'] . '+ '.$selUnit['CreditDays'].' days'));
//            }
        $netamount=0;
        $netamount= $unitInfo[ 'NetAmt' ];

        $pdfhtml .= <<<EOT
				<header class="clearfix" style="padding-left:10px; padding-right:10px;">
					<div id="logo">
					   <!-- <img src=""/>-->
					</div>
					<h1>Buyer Receivable Abstract</h1>
				</header>
				<div align="center" style="width:100%;">
					<table align="center" style="width:98%;">
						<tbody>
							<tr>
								<td style="text-align:left !important;">
									<div class="project">
										<div class="pd10 mar_20"><span style="margin-left:30px;">Unit No. : </span>{$unitInfo['UnitNo']}</div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">BLOCK : </span>{$unitInfo['BlockName']} </div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">Buyer Name. : </span>{$unitInfo['BuyerName']}</div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">Area : </span>{$unitInfo['UnitArea']}</div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">UDS Land Area : </span>{$unitInfo['UDSLandArea']}</div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">Guideline Value : </span>{$unitInfo['GuideLinevalue']}</div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">Land Amount : </span>{$unitInfo['LandAmount']}</div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">Carpet Area : </span>{$unitInfo['CarpetArea']}</div>										
										<div class="pd10 mar_20"><span style="margin-left:30px;">Construction Amount : </span>{$unitInfo['ConstructionAmount']}</div>
										<div class="pd10 mar_20"><span style="margin-left:30px;">Base Amount : </span>{$unitInfo['BaseAmt']}</div>									
										<div class="pd10 mar_20"><span style="margin-left:30px;">Other Cost : </span>{$other['TotAmount']}</div>										
										<div class="pd10 mar_20"><span style="margin-left:30px;">GrossAmount : </span>{$unitInfo['GrossAmount']}</div>										
										<div class="pd10 mar_20"><span style="margin-left:30px;">NetAmount : </span>{$netamount}</div>
									</div>
								</td>
								
							</tr>
						</tbody>
					</table>
				</div>
				<main>
					<div align="center" style="width:100%;">
						<table align="center" style="width:98%;">
							<thead  style="font-size: 16px ! important; ">
								<tr>
									<th class="desc">Description</th>
									<th>Schedule Amount</th>
									<th>Tax</th>
									<th>Net Amount</th>
								</tr>
							</thead>
							<tbody>
EOT;
        $netamount1=0 + $arrrlate['Amount'];
        $tax1=0;
        $sch1=0 + $arrrlate['Amount'];

        foreach($arrPaymentScheduleDetails as $paymentSchedule) {
            $netamount1= $netamount1 + $paymentSchedule[ 'NetAmount' ];
            $tax1= $tax1 + $paymentSchedule[ 'QualAmount' ];
            $sch1= $sch1 + $paymentSchedule[ 'Amount' ];
            $pdfhtml .= <<<EOT
                    <tr>
                        <td class="desc">{$paymentSchedule['StageName']}</td>
                        <td class="total">{$paymentSchedule['Amount']}</td>
						<td class="">{$paymentSchedule['QualAmount']}</td>
						<td class="">{$paymentSchedule['NetAmount']}</td>
                    </tr>
EOT;
        }
        $pdfhtml .= <<<EOT
        <tr>
                        <td class="desc"> LateInterest </td>
                        <td class="total">{$arrrlate['Amount']}</td>
						<td class=""> 0</td>
						<td class="">{$arrrlate['Amount']}</td>
                    </tr>

						<tr>
							<td >Total</td>
							<td class="total"><b>{$sch1 }</b></td>
							<td class="total"><b>{$tax1}</b></td>
							<td class="total"><b>{$netamount1 }</b></td>
						</tr>
					</tbody>
				</table>
			</div>
			<div align="center" style="width:100%;">
						<table align="center" style="width:98%;">
							<thead  style="font-size: 16px ! important; ">
								<tr>
									<th class="desc">Receipt Date</th>
									<th>Receipt No</th>
									<th>Receipt Mode</th>
									<th>Amount</th>
								</tr>
							</thead>
							<tbody>
EOT;
        $netamount2=0;
        foreach($arrReceipts as $receipt) {
            $netamount2= $netamount2 + $receipt[ 'Amount' ];
            $pdfhtml .= <<<EOT
                    <tr>
                        <td class="desc">{$receipt['ReceiptDate']}</td>
                        <td class="total">{$receipt['ReceiptNo']}</td>
						<td class="">{$receipt['ReceiptMode']}</td>
						<td class="">{$receipt['Amount']}</td>
                    </tr>
EOT;
        }
        $pdfhtml .= <<<EOT
						<tr>
							<td colspan="3" >Total</td>
							<td class="total"><b>{$netamount2}</b></td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="project widspn" style="margin-top:20px; width:100%;"></div>
			<div class="clearfix"></div>
		</main>
EOT;
        //   }
        $pdfhtml .= <<<EOT
		<div style="font-size: 15px; color:#4E4C4C; padding:10px 15px;">
			<p>Thanking you and assuring of our best services at all times.</p>
			<p style="padding: 0 15px ; ">For {$progressBill['CompanyName']}</p>
			<p style="padding: 0 15px ; "><b>(Authorised Signatory)</b></p>
		</div>
	</body>
</html>
EOT;
        return $pdfhtml;
    }

    public function costsheetAction()
    {
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

            }
        }
        $UnitId = $this->bsf->isNullCheck($this->params()->fromRoute('UnitId'), 'number' );
        $select = $sql->select();
        $select->from(array('a' => 'KF_UnitMaster'))
            ->columns(array(new Expression("a.UnitId,a.UnitTypeId ,a.UnitNo, a.UnitArea, a.Status,a.ProjectId, Case When i.BookingId > 0 then 1 else  0  End as count")))
            ->join(array('z' => 'KF_UnitTypeMaster'), 'z.UnitTypeId=a.UnitTypeId',array('TypeName'), $select::JOIN_LEFT)
            ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
            ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
            ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
            ->join(array("d"=>"Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac"=>"Description"), $select::JOIN_LEFT)
            ->join(array("e"=>"Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status"=>"Description"), $select::JOIN_LEFT)
            ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT)
            ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('B00kRate'=>'Rate','BOther'=>'OtherCostAmount','BQual'=>'QualifierAmount','BDiscountType'=>'DiscountType','BNet'=>'NetAmount','BBase'=>'BaseAmount','BConst'=>'ConstructionAmount',"BOther"=>'OtherCostAmount','BLand'=>'LandAmount','BookingStatus' => new Expression("CAST ( CASE WHEN i.BookingId IS NOT NULL THEN 'Sold' ELSE '' END AS varchar(11))"),'BookingId','Approve','BDiscount'=>new Expression("isnull(i.Discount,0)")), $select::JOIN_LEFT)
            ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId','PrevRate'=>'Rate','base'=>'BaseAmount','const'=>'ConstructionAmount','land'=>'LandAmount','gross'=>'GrossAmount','PostDiscount',"other"=>'OtherCostAmount',"PostDiscountType","qual"=>'QualifierAmount',"net"=>'NetAmount','PRate'=>'Rate'), $select::JOIN_LEFT)
            ->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array('BlockId','ValidUpto','BlockBAdv'=>'AdvAmnt','Blockbase'=>'BaseAmount','BRate','BlockDiscount'=>'Discount','Blockconst'=>'ConstructionAmount','Blockland'=>'LandAmount','Blockgross'=>'GrossAmount',"Blockother"=>'OtherCost',"Blockqual"=>'QualAmount',"Blocknet"=>'NetAmount','BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
            ->join(array('x' => 'Crm_UnitPreBooking'),new Expression("a.UnitId=x.UnitId and x.DeleteFlag=0"), array('PreBookingId','ValidUpto','PreBAdv'=>'AdvAmount','Prebase'=>'BaseAmount','PreRate'=>'PRate','PreDiscount'=>'Discount','Preconst'=>'ConstructionAmount','Preland'=>'LandAmount','Pregross'=>'GrossAmount',"Preother"=>'OtherCost',"Prequal"=>'QualAmount',"Prenet"=>'NetAmount','PreStatus' => new Expression("CAST ( CASE WHEN x.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)                        ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
            ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
            ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto'=>'Photo'), $select::JOIN_LEFT)
            ->join(array('w' => 'Crm_LeadPersonalInfo'), 'w.LeadId=j.LeadId', array('BlockedPhoto'=>'Photo'), $select::JOIN_LEFT)
            ->join(array('y' => 'Crm_Leads'), 'y.LeadId=x.LeadId', array('PreName' => 'LeadName'), $select::JOIN_LEFT)
            ->join(array("c"=>"WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
            ->join(array("m"=>"WF_Users"), "j.ExecutiveId=m.UserId", array("BlockExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
            ->join(array("s"=>"WF_Users"), "y.ExecutiveId=s.UserId", array("PreExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
            ->where(array("a.UnitId"=>$UnitId))
            ->order("o.PostSaleDiscountId desc");
        $stmt = $sql->getSqlStringForSqlObject($select);
        $unitInfo = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $proj = $unitInfo['ProjectId'];


        $this->_view->UnitId = $UnitId;


        if(empty($unitInfo)) {
            throw new \Exception('Unit info not found!');
        }

        if($unitInfo['Status'] == 'B') {
            $unitInfo['BuyerName'] = $unitInfo['BlockedName'];
            $unitInfo['ExecutiveName'] = $unitInfo['BlockExecutiveName'];
            $unitInfo['OtherCostAmt'] = 0;
            $unitInfo['NetAmt'] = $unitInfo['Blocknet'];
            $unitInfo['QualifierAmount'] =$unitInfo['Blockqual'];
            $unitInfo['Discount'] =$unitInfo['BlockDiscount'];
            $unitInfo['AdvAmount'] =$unitInfo['BlockBAdv'];
            $unitInfo['BaseAmt'] = $unitInfo['Blockbase'];
            $unitInfo['ConstructionAmount'] = $unitInfo['Blockconst'];
            $unitInfo['GrossAmount'] =$unitInfo['Blockgross'];
            $unitInfo['Rate'] =$unitInfo['BRate'];
            $unitInfo['Rate'] =$unitInfo['BRate'];
            $unitInfo['LandAmount'] =$unitInfo['Blockland'];
        }
        else if($unitInfo['Status'] == 'P') {
            $unitInfo['BuyerName'] = $unitInfo['PreName'];
            $unitInfo['ExecutiveName'] = $unitInfo['PreExecutiveName'];
            $unitInfo['OtherCostAmt'] =0;
            $unitInfo['NetAmt'] = $unitInfo['Prenet'];
            $unitInfo['QualifierAmount'] =$unitInfo['Prequal'];
            $unitInfo['Discount'] =$unitInfo['PreDiscount'];
            $unitInfo['AdvAmount'] =$unitInfo['PreBAdv'];
            $unitInfo['BaseAmt'] = $unitInfo['Prebase'];
            $unitInfo['ConstructionAmount'] = $unitInfo['Preconst'];
            $unitInfo['GrossAmount'] =$unitInfo['Pregross'];
            $unitInfo['Rate'] =$unitInfo['PreRate'];
            $unitInfo['LandAmount'] =$unitInfo['Preland'];
        }
        else if($unitInfo['Status'] == 'U') {
            $unitInfo['BuyerName'] = " ";
            $unitInfo['OtherCostAmt'] =0;
        }
        else if($unitInfo['Status'] == 'R') {
            $unitInfo['OtherCostAmt'] =0;
        }
        if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
            $unitInfo['OtherCostAmt'] = $unitInfo['other'];
            $unitInfo['NetAmt'] = $unitInfo['net'];
            $unitInfo['QualifierAmount'] =$unitInfo['qual'];
            $unitInfo['Discount'] =$unitInfo['PostDiscount'];
            $unitInfo['BaseAmt'] = $unitInfo['base'];
            $unitInfo['ConstructionAmount'] = $unitInfo['const'];
            $unitInfo['GrossAmount'] =$unitInfo['gross'];
            $unitInfo['LandAmount'] =$unitInfo['land'];
            $unitInfo['Rate'] =$unitInfo['PrevRate'];
        }
        else if($unitInfo['count'] == 1) {
            $unitInfo['OtherCostAmt'] = $unitInfo['BOther'];
            $unitInfo['NetAmt'] = $unitInfo['BNet'];
            $unitInfo['QualifierAmount'] =$unitInfo['BQual'];
            $unitInfo['Discount'] =$unitInfo['BDiscount'];
            $unitInfo['BaseAmt'] = $unitInfo['BBase'];
            $unitInfo['ConstructionAmount'] = $unitInfo['BConst'];
            $unitInfo['GrossAmount'] =$unitInfo['BBase']+ $unitInfo['BOther'];
            $unitInfo['LandAmount'] =$unitInfo['BLand'];
            $unitInfo['Rate'] =$unitInfo['B00kRate'];

        }
        $this->_view->unitInfo = $unitInfo;


        $select = $sql->select();
        $select->from(array('a' => 'KF_UnitMaster'))
            ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId")))
            ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
            ->where(array("a.UnitId"=>$UnitId));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $unitIn = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $select = $sql->select();
        $select->from(array('a' => 'Crm_UnitTypeOtherCostTrans'))
            ->columns(array('Amount'))
            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.OtherCostId = b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
            ->where(array('a.UnitTypeId' => $unitInfo['UnitTypeId']));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $other = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->other = $other;

        $select = $sql->select();
        $select->from('Crm_ReceiptRegister')
            ->columns(array('count' => new Expression("count(ReceiptNo)")))
            ->where(array('UnitId' => $unitInfo['UnitId'],'ReceiptAgainst'=>'A','CancelId'=>0,'DeleteFlag'=>0));
        $stmt = $sql->getSqlStringForSqlObject($select);
        $this->_view->advAmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $planDiscountValid2=false;
        $planDiscountValid1=false;

        if($unitInfo['Status'] == 'S') {
            //cost sheet
            if($unitInfo['count']==1){
                $planDiscountValid2=true;
                $Bother =$unitInfo['BOther'];
                $BNet = $unitInfo['BNet'];
                $BQual =$unitInfo['BQual'];
                $BDis =$unitInfo['BDiscount'];
                $BDisType =$unitInfo['BDiscountType'];
                // Print_r($BDisType);die;
                $Bbase = $unitInfo['BBase'];
                $Bconst = $unitInfo['BConst'];
                $BGross =$unitInfo['BBase']+ $unitInfo['BOther'];
                $BLand=$unitInfo['BLand'];
                $BRate=$unitInfo['B00kRate'];

                if($BDisType=='R'){
                    $BDisType='Rate/Sqft';
                }else if($BDisType=='L'){
                    $BDisType='Lumpsum';
                }
                else if($BDisType=='P'){
                    $BDisType='Percentage';
                }
                else{
                    $BDisType='-';
                }

            }
            if($unitInfo['count'] == 1 && $unitInfo['PostSaleDiscountId'] > 0) {
                $planDiscountValid1=true;
                $Pother  = $unitInfo['other'];
                $PNet  = $unitInfo['net'];
                $PQual =$unitInfo['qual'];
                $PDis =$unitInfo['PostDiscount'];
                $PDisType =$unitInfo['PostDiscountType'];
                // Print_r($PDisType);die;
                $Pbase = $unitInfo['base'];
                $Pconst = $unitInfo['const'];
                $PGross =$unitInfo['gross'];
                $PLand =$unitInfo['land'];
                $PRate =$unitInfo['PRate'];
                if($PDisType=='R'){
                    $PDisType='Rate/Sqft';
                }else if($PDisType=='L'){
                    $PDisType='Lumpsum';
                }
                else if($PDisType=='P'){
                    $PDisType='Percentage';
                }
                else{
                    $PDisType='-';
                }
            }
            $discount =0;
            //  $area =$unitInfo['UnitArea'];
            $rate =$unitIn['Rate'];
            $baseAmt =$unitIn['BaseAmt'];
            $grossAmt =$unitIn['GrossAmount'];
            $netAmt =$unitIn['NetAmt'];
            $otherCostAmt =$unitIn['OtherCostAmt'];
            $constructionAmount =$unitIn['ConstructionAmount'];
            $landAmount =$unitIn['LandAmount'];
            $discountType ='-';


            $costSheet=array();

            $this->_view->planDiscountValid2 = $planDiscountValid2;
            $this->_view->planDiscountValid1 = $planDiscountValid1;
            if($planDiscountValid1==true && $planDiscountValid2==true){
                $costSheet['Discount Type']=array($discountType,$BDisType,$PDisType);
                $costSheet['Discount']=array($viewRenderer->commonHelper()->sanitizeNumber($discount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BDis,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PDis,2,true));
                $costSheet['other Cost Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bother,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Pother,2,true));
                $costSheet['Rate']=array($viewRenderer->commonHelper()->sanitizeNumber($rate,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BRate,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PRate,2,true));
                $costSheet['Base Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bbase,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Pbase,2,true));
                $costSheet['Gross Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BGross,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PGross,2,true));
                $costSheet['Net Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($netAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BNet,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PNet,2,true));
                $costSheet['Construction Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bconst,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Pconst,2,true));
                $costSheet['Land Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($landAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BLand,2,true),$viewRenderer->commonHelper()->sanitizeNumber($PLand,2,true));
            } else if($planDiscountValid1==false &&  $planDiscountValid2==true){
                $costSheet['Discount Type']=array($discountType,$BDisType,'-');
                $costSheet['Discount']=array($viewRenderer->commonHelper()->sanitizeNumber($discount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BDis,2,true),'-');
                $costSheet['Rate']=array($viewRenderer->commonHelper()->sanitizeNumber($rate,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BRate,2,true),'-');
                $costSheet['Base Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bbase,2,true),'-');
                $costSheet['Gross Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BGross,2,true),'-');
                $costSheet['Net Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($netAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BNet,2,true),'-');
                $costSheet['Construction Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bconst,2,true),'-');
                $costSheet['Land Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($landAmount,2,true),$viewRenderer->commonHelper()->sanitizeNumber($BLand,2,true),'-');
                $costSheet['otherCost Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt,2,true),$viewRenderer->commonHelper()->sanitizeNumber($Bother,2,true),'-');
            }  else if($planDiscountValid1==false &&  $planDiscountValid2==false){
                $costSheet['Discount Type']=array($discountType,'-','-');
                $costSheet['Discount']=array($viewRenderer->commonHelper()->sanitizeNumber($discount,2,true),'-','-');
                $costSheet['Rate']=array($viewRenderer->commonHelper()->sanitizeNumber($rate,2,true),'-','-');
                $costSheet['Base Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($baseAmt,2,true),'-','-');
                $costSheet['Gross Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($grossAmt,2,true),'-','-');
                $costSheet['Net Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($netAmt,2,true),'-','-');
                $costSheet['Construction Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($constructionAmount,2,true),'-','-');
                $costSheet['Land Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($landAmount,2,true),'-','-');
                $costSheet['otherCost Amount']=array($viewRenderer->commonHelper()->sanitizeNumber($otherCostAmt,2,true),'-','-');
            }
            $this->_view->costSheet=$costSheet;

            $select = $sql->select();
            $select->from('Crm_UnitBooking')
                ->where(array('UnitId' => $unitInfo['UnitId']));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $unitBooking = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->unitBooking = $unitBooking;

            $select = $sql->select();
            $select->from('Crm_PostSaleDiscountRegister')
                ->columns(array('PostSaleDiscountId','BookingId'))
                ->where(array('BookingId' => $unitBooking['BookingId']))
                ->order("PostSaleDiscountId desc");
            $stmt = $sql->getSqlStringForSqlObject($select);
            $unitPostSale = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $postsalecount=count($unitPostSale['BookingId']);
            //payment schedules
            $select = $sql->select();
            $select->from('Crm_PaymentSchedule')
                ->where(array('ProjectId' => $unitInfo['ProjectId'], 'DeleteFlag' => 0));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            // Receipt Type
            $select = $sql->select();
            $select->from('Crm_ReceiptTypeMaster');
            $stmt = $sql->getSqlStringForSqlObject($select);
            $arrResults = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arrReceiptTypes = $arrResults;
            $arrAllReceiptTypes = array();
            foreach($arrResults as $result) {
                $arrAllReceiptTypes[$result['ReceiptTypeId']] = $result['Type'];
            }
            if( $postsalecount > 0 ){
                $select1 = $sql->select();
                $select1->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                    ->where(array('a.StageType' => 'S', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));

                $select2 = $sql->select();
                $select2->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                    ->where(array('a.StageType' => 'D', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                $select2->combine($select1,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                    ->where(array('a.StageType' => 'O', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                $select3->combine($select2,'Union ALL');

                $select4 = $sql->select();
                $select4->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                    ->where(array('a.StageType' => 'A', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                $select4->combine($select3,'Union ALL');

                $select5 = $sql->select();
                $select5->from(array("g"=>$select4))
                    ->columns(array('*'))
                    ->where(array('BookingId' => $unitBooking['BookingId']))
                    ->order("g.SortId ASC");
                $stmt = $sql->getSqlStringForSqlObject($select5);
                $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(!empty($arrPaymentScheduleDetails)) {

                    foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                        // receipt type
                        $select1 = $sql->select();
                        $select1->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                            ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                            ->where( array( 'b.ReceiptType' => 'S', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                        $select2 = $sql->select();
                        $select2->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                            ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                            ->where( array( 'a.ReceiptType' => 'D', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                        $select2->combine( $select1, 'Union ALL' );

                        $select3 = $sql->select();
                        $select3->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                            ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                            ->where( array( 'a.ReceiptType' => 'O', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                        $select3->combine( $select2, 'Union ALL' );

                        $select4 = $sql->select();
                        $select4->from( array( "g" => $select3 ) )
                            ->columns( array( '*' ) )
                            ->order("g.ReceiptTypeTransId ASC");

                        $stmt = $sql->getSqlStringForSqlObject( $select4 );
                        $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        if(!empty($arrReceiptTypes)) {

                            $iQualCount = 0;
                            foreach($arrReceiptTypes as &$receipt) {

                                switch($receipt['ReceiptType']) {
                                    case 'O':
                                        $receipt['Type'] = 'O';
                                        break;
                                    case 'S':
                                        $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                        break;
                                }

                                // qualifier
                                $select = $sql->select();
                                $select->from( array( 'a' => 'Crm_PSDPaymentScheduleQualifierTrans' ) )
                                    ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                        'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                        'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                    ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                                $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                if ( !empty( $qualList ) ) {
                                    foreach($qualList as &$qual) {
                                        $qual['BaseAmount'] = $receipt['Amount'];
                                    }

                                    $sHtml = Qualifier::getQualifier( $qualList );
                                    $iQualCount = $iQualCount + 1;
                                    $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                    $receipt[ 'qualHtmlTag' ] = $sHtml;

                                }

                            }

                            $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                        }
                    }
                }
            }
            else{

                // current payment schedule detail
                $select1 = $sql->select();
                $select1->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                    ->where(array('a.StageType' => 'S', 'BookingId' => $unitBooking['BookingId']));

                $select2 = $sql->select();
                $select2->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                    ->where(array('a.StageType' => 'D', 'BookingId' => $unitBooking['BookingId']));
                $select2->combine($select1,'Union ALL');

                $select3 = $sql->select();
                $select3->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                    ->where(array('a.StageType' => 'O', 'BookingId' => $unitBooking['BookingId']));
                $select3->combine($select2,'Union ALL');

                $select4 = $sql->select();
                $select4->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                    ->columns(array('*'))
                    ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                    ->where(array('a.StageType' => 'A', 'BookingId' => $unitBooking['BookingId']));
                $select4->combine($select3,'Union ALL');

                $select5 = $sql->select();
                $select5->from(array("g"=>$select4))
                    ->columns(array('*'))
                    ->where(array('BookingId' => $unitBooking['BookingId']))
                    ->order("g.SortId ASC");
                $stmt = $sql->getSqlStringForSqlObject($select5);
                $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(!empty($arrPaymentScheduleDetails)) {

                    foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                        // receipt type
                        $select1 = $sql->select();
                        $select1->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                            ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                            ->where( array( 'b.ReceiptType' => 'S', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                        $select2 = $sql->select();
                        $select2->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                            ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                            ->where( array( 'a.ReceiptType' => 'D', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                        $select2->combine( $select1, 'Union ALL' );

                        $select3 = $sql->select();
                        $select3->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                            ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                            ->where( array( 'a.ReceiptType' => 'O', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                        $select3->combine( $select2, 'Union ALL' );

                        $select4 = $sql->select();
                        $select4->from( array( "g" => $select3 ) )
                            ->columns( array( '*' ) )
                            ->order("g.ReceiptTypeTransId ASC");

                        $stmt = $sql->getSqlStringForSqlObject( $select4 );
                        $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                        if(!empty($arrReceiptTypes)) {

                            $iQualCount = 0;
                            foreach($arrReceiptTypes as &$receipt) {

                                switch($receipt['ReceiptType']) {
                                    case 'O':
                                        $receipt['Type'] = 'O';
                                        break;
                                    case 'S':
                                        $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                        break;
                                }

                                // qualifier
                                $select = $sql->select();
                                $select->from( array( 'a' => 'Crm_PaymentScheduleQualifierTrans' ) )
                                    ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                        'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                        'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                    ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                                $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                                $statement = $sql->getSqlStringForSqlObject( $select );
                                $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                if ( !empty( $qualList ) ) {
                                    foreach($qualList as &$qual) {
                                        $qual['BaseAmount'] = $receipt['Amount'];
                                    }

                                    $sHtml = Qualifier::getQualifier( $qualList );
                                    $iQualCount = $iQualCount + 1;
                                    $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                    $receipt[ 'qualHtmlTag' ] = $sHtml;

                                }

                            }

                            $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                        }
                    }
                }}

            $this->_view->arrPaymentScheduleDetails = $arrPaymentScheduleDetails;



            // extra items
            $subQuery = $sql->select();
            $subQuery->from(array("a" => "Crm_ExtraBillTrans"))
                ->join(array("b" => "Crm_ExtraBillRegister"), "a.ExtraBillRegisterId=b.ExtraBillRegisterId", array(), $subQuery::JOIN_INNER)
                ->columns(array('ExtraItemId'))
                ->where(array('b.UnitId'=>$UnitId));

            $select = $sql->select();
            $select->from(array("a" => "Crm_UnitExtraItemTrans"))
                ->join(array("b" => "Crm_ExtraItemMaster"), "a.ExtraItemId=b.ExtraItemId", array('ItemDescription', 'Code'), $select::JOIN_LEFT)
                ->join(array("c" => "Proj_UOM"), "c.UnitId=b.MUnitid", array('UnitName'), $select::JOIN_LEFT)
                ->where(array('a.UnitId' => $UnitId))
                ->where->expression('a.ExtraItemId Not IN ?', array($subQuery));
            $statement = $sql->getSqlStringForSqlObject($select);
            $arrExtraItemList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if(!empty($arrExtraItemList)) {
                $this->_view->arrExtraItemList = $arrExtraItemList;
            }

            // Buyer statement
            $select = $sql->select();
            $select->from(array('a' => 'Crm_ProgressBillTrans'))
                ->columns(array('BillDate' => new Expression("FORMAT(BillDate, 'dd-MM-yyyy')"), 'PBNo','BillAmount'=>'Amount','PaidAmount','Balance' => new Expression('Case When Amount-PaidAmount > 0 then Amount-PaidAmount else 0 end'),
                    'StageName' => new Expression("Case When a.StageType='S' then c.StageName when a.StageType='O' then d.OtherCostName When a.StageType='D' then e.DescriptionName end")))
                ->join(array("c"=>"KF_StageMaster"), "a.StageId=c.StageId", array(), $select::JOIN_LEFT)
                // ->join(array("f"=>"KF_StageCompletion"), "a.StageId=f.StageId", array(), $select::JOIN_LEFT)
                ->join(array("d"=>"Crm_OtherCostMaster"), "a.StageId=d.OtherCostId", array(), $select::JOIN_LEFT)
                ->join(array("e"=>"Crm_DescriptionMaster"), "a.StageId=e.DescriptionId", array(), $select::JOIN_LEFT)
                ->where(array("a.UnitId" => $UnitId,'a.CancelId'=>0));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $arrBuyerStmt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(!empty($arrBuyerStmt)) {
                $this->_view->arrBuyerStmt = $arrBuyerStmt;
            }

            $select = $sql->select();
            $select->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                ->columns(array('Amount'))
                ->where(array("UnitId" => $UnitId,"StageType"=>'A'));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $amountadvance = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


            $select = $sql->select();
            $select->from(array('a' => 'Crm_UnitDetails'))
                ->columns(array('AdvAmount'))
                ->where(array("UnitId" => $UnitId));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $unitadvance = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();



            $select = $sql->select();
            $select->from(array('a' => 'Crm_ReceiptRegister'))
                ->columns(array('Amount' => new expression('SUM(Amount)')))
                ->where(array("UnitId" => $UnitId,'a.DeleteFlag' => 0,'a.CancelId'=>0,"ReceiptAgainst"=>'A'));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $receiptAgainst = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $select = $sql->select();
            $select->from(array("a" => "Crm_ReceiptRegister"))
                ->columns(array("Amount"))
                ->where(array('a.DeleteFlag' => 0, 'a.UnitId' => $UnitId,'a.CancelId'=>0,'ReceiptAgainst'=>'A'));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $arrpaid = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            $this->_view->unitadvance = $unitadvance;
            $this->_view->amountadvance = $amountadvance;
            $this->_view->receiptAgainst = $receiptAgainst;

            if(!empty($arrpaid)) {
                $this->_view->arrpaid = $arrpaid;
            }

            //executives//
            $PositionTypeId=array(5,2);
            $sub = $sql->select();
            $sub->from(array('a'=>'WF_PositionMaster'))
                ->join(array("b"=>"WF_PositionType"),"a.PositionTypeId=b.PositionTypeId",array(),$sub::JOIN_LEFT)
                ->columns(array('PositionId'))
                ->where(array("b.PositionTypeId"=>$PositionTypeId));

            $select = $sql->select();
            $select->from('WF_Users')
                ->columns(array('data' => 'UserId', 'value' => 'EmployeeName'))
                ->where(array('DeleteFlag' => 0))
                ->where->expression("PositionId IN ?",array($sub));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrExecutives = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            // selected check list
            $select = $sql->select();
            $select->from(array('a' => 'Crm_FinalisationCheckListTrans'))
                ->columns(array('BookingId','ExecutiveId'))
                ->join(array('b' => 'WF_Users'), 'b.UserId=a.ExecutiveId', array('ExecutiveName' => 'EmployeeName','Date1' => new Expression("CONVERT(varchar(10),a.SubmittedDate,105) ")), $select::JOIN_LEFT)
                ->join(array('c' => 'Crm_CheckListMaster'), 'c.CheckListId=a.CheckListId', array('CheckListName','CheckListId'), $select::JOIN_LEFT)
                ->where(array('BookingId' => $unitBooking['BookingId'],'a.IsChecked' => '1'));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $arrSelectedCheckLists = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $this->_view->arrSelectedCheckLists = $arrSelectedCheckLists;



            foreach($arrSelectedCheckLists as &$checkList) {
                $isFound = FALSE;
                foreach($arrSelectedCheckLists as $selCheckList) {
                    if($checkList['CheckListId'] == $selCheckList['CheckListId']) {
                        $isFound = TRUE;
                        $submittedDate = NULL;
                        if(strtotime($selCheckList['SubmittedDate']) != FALSE) {
                            $submittedDate = date('d-m-Y', strtotime($selCheckList['SubmittedDate']));
                        }
                        $checkList['Checked'] = 'Checked';
                        $checkList['SubmittedDate'] = $submittedDate;
                        $checkList['ExecutiveName'] = $selCheckList['ExecutiveName'];
                        $checkList['ExecutiveId'] = $selCheckList['ExecutiveId'];
                        break;
                    }
                }
                if(!$isFound) {
                    $checkList['Checked'] = '';
                }
            }
            //handingover

            $select = $sql->select();
            $select->from(array('a' => 'Crm_HandingoverCheckTrans'))
                ->join(array('b' => 'WF_Users'), 'b.UserId=a.ExecutiveId', array('ExecutiveName' => 'EmployeeName', 'Date1' => new Expression("CONVERT(varchar(10),a.Date,105) ")), $select::JOIN_LEFT)
                ->join(array('c' => 'Crm_CheckListMaster'), 'c.CheckListId=a.CheckListId', array('CheckListName'), $select::JOIN_LEFT)
                ->where(array('a.UnitId' => $UnitId,'a.IsChecked' => '1'));
            $stmt = $sql->getSqlStringForSqlObject($select);
            $this->_view->arrSelHandCheck = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // $this->_view->arrReceipts = '';
            // receipts
            $select = $sql->select();
            $select->from(array("a" => "Crm_ReceiptRegister"))
                ->columns(array("ReceiptId", "ReceiptNo", "ReceiptDate" => new Expression("FORMAT(a.ReceiptDate, 'dd-MM-yyyy')"),
                    "ReceiptAgainst"=> new Expression("Case When ReceiptAgainst ='B' then 'Bill/Schedule' When ReceiptAgainst ='A' then 'Advance' When ReceiptAgainst ='O' then 'Others' When ReceiptAgainst ='P' then 'Pre-Booking' else 'N/A 'end") ,"Amount","ReceiptMode"))
                ->where(array('a.DeleteFlag' => 0, 'a.CancelId'=>0, 'a.UnitId' => $UnitId));
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->arrReceipts = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
        }

//        $this->_view->arrCheckList = $arrCheckList;

        $pdfhtml = $this->generateCostSheetPdf($unitInfo,$arrPaymentScheduleDetails,$costSheet,$other,$viewRenderer);

        $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";
        require_once($path);
        $dompdf = new DOMPDF();

        $dompdf->load_html($pdfhtml);
        $dompdf->set_paper("A4");
        $dompdf->render();
        //$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
        $canvas = $dompdf->get_canvas();
        $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

        $dompdf->stream("costSheet.pdf");

    }
    private function generateCostSheetPdf($unitInfo,$arrPaymentScheduleDetails,$costSheet,$other,$viewRenderer) {

        $pdfhtml = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>index</title>
</head>
<style>
body {
	background:#fff;
}
.letter-tit 		{font-size:14px;font-family:Arial; background:#ccc; color:#000;text-align:center;padding-top:15px;padding-bottom:15px;display:block;border-bottom:1px dashed #ccc;}
.name-fli 			{font-size:13px;font-family:Arial;color:#000;display;block;}
.date-fel 			{font-size:13px;font-family:Arial;color:#000;text-align:right;margin-top:10px;display:block;margin-right:10px;}
.add-det 			{font-size:13px;font-family:Arial;color:#000;margin-top:10px;display:block;margin-right:10px;line-height:20px;}
.dears-b 			{font-size:13px;font-family:Arial;color:#000;margin-bottom:5px;display:block;}
.det-b				{font-size:13px;font-family:Arial;color:#000;margin:0px;line-height:20px;}
.add-dets		    	{font-size:13px;font-family:Arial;color:#000;margin:0px;line-height:20px;}
.bb-bd 				{font-size:13px;font-family:Arial;color:#000;margin:10px;}
.brdr				{border-top:2px solid #ddd;}
.mar20				{margin-top:20px;}
.bld				{font-size:15px; padding-top:10px;padding-bottom:10px;}
</style>
<body>
EOT;
        $pdfhtml .= <<<EOT
<table cellpadding="0" cellspacing="0" align="left" width="100%">
              <tr>
                <td align="center" width="100%"><b class="letter-tit">Cost Schedule For <span>{$unitInfo['UnitNo']}</span></b></td>
              </tr>
</table>
<table cellpadding="0" cellspacing="0" align="center" width="700">
	     <tr> <tr>
          <td>&nbsp;</td>
        </tr> <tr>
          <td>&nbsp;</td>
        </tr>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Rate per Sft : </b></span>&nbsp;{$unitInfo['Rate']}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Base Amount :</b> </span>&nbsp;{$viewRenderer->commonHelper()->sanitizeNumber($unitInfo['BaseAmt'],2,true)} </td>
		        <td class="add-det"><span class="name-fli"><b class="bb-bd">Advance Amount :</b></span>&nbsp;{$viewRenderer->commonHelper()->sanitizeNumber($unitInfo['AdvAmount'],2,true)}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">UDS LandArea :</b></span>&nbsp;{$unitInfo['UDSLandArea']}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Land Amount :</b></span>&nbsp;{$viewRenderer->commonHelper()->sanitizeNumber($unitInfo['LandAmount'],2,true)}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Construction Amount:</b></span>&nbsp;{$viewRenderer->commonHelper()->sanitizeNumber($unitInfo['ConstructionAmount'],2,true)}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">OtherCost Amount:</b> </span> &nbsp;{$viewRenderer->commonHelper()->sanitizeNumber($unitInfo['OtherCostAmt'],2,true)}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Gross Amount :</b></span> &nbsp;{$viewRenderer->commonHelper()->sanitizeNumber($unitInfo['GrossAmount'],2,true)}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Net Amount :</b> </span>&nbsp;{$viewRenderer->commonHelper()->sanitizeNumber($unitInfo['NetAmt'],2,true)}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Block Name :</b></span>&nbsp;{$unitInfo['BlockName']}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Type Name :</b></span>&nbsp;{$unitInfo['TypeName']}</td>
                <td class="add-det"><span class="name-fli"><b class="bb-bd">Area in Sft :</b> </span>&nbsp;{$unitInfo['UnitArea']}</td>
		</tr>
</table>
<table cellpadding="0" cellspacing="0" align="center" width="585px" style="border:1px solid #000; background:#fff;margin-top:15px;" >
              	<tbody>
		<tr>
	        <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"colspan="2"><p class="bb-bd"><b class="bb-bd">Other Cost Name</b></p></td>
                <td align="center"style="border-bottom:1px solid #000;"colspan="2"><p class="bb-bd"><b class="bb-bd">Amount</b></p><td>
	    </tr>
EOT;
        foreach($other as $other){
            $pdfhtml .= <<<EOT
		<tr>
		    <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"colspan="2"><p class="bb-bd">{$other['OtherCostName']}</p></td>
                <td align="center"style="border-bottom:1px solid #000;"colspan="2"><p class="bb-bd">{$viewRenderer->commonHelper()->sanitizeNumber($other['Amount'],2,true)}</p><td>
		</tr>
EOT;
        }
        $pdfhtml .= <<<EOT

	        </tbody>
 </table>
 <table>
 <tr>
          <td align="center" width="100%"><b class="bld">Payment Schedule of Amount</b></td>
	  <td>&nbsp;</td>
        </tr>
 </table>
 <div>
 <table cellpadding="0" cellspacing="0" align="center" width="585px" style="border:1px solid #000; background:#fff;margin-top:15px;" >
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Description</b></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">%</b></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">schedule Amount</b></td>
		  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Tax Amount</b></td>
		  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Net Amount</b></td>
                </tr>
                 <tbody>
EOT;
        $netamount5=0;
        $tax5=0;
        $schedule5=0;
        $percent5=0;
        foreach($arrPaymentScheduleDetails as $PaymentDetails){
            $netamount5 = $netamount5 + $PaymentDetails[ 'NetAmount' ];
            $percent5 = $percent5 + $PaymentDetails[ 'Percentage' ];
            $tax5 = $tax5 + $PaymentDetails[ 'QualAmount' ];
            $schedule5 = $schedule5 + $PaymentDetails[ 'Amount' ];
            $pdfhtml .= <<<EOT
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$PaymentDetails['StageName']}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$PaymentDetails['Percentage']}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$viewRenderer->commonHelper()->sanitizeNumber($PaymentDetails['Amount'],2,true)}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$viewRenderer->commonHelper()->sanitizeNumber($PaymentDetails['QualAmount'],2,true)}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$viewRenderer->commonHelper()->sanitizeNumber($PaymentDetails['NetAmount'],2,true)}</p></td>
               </tr>
EOT;
        }
        $pdfhtml .= <<<EOT
	       <tr>
		 <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"colspan="1">&nbsp;</td>
		 <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"><p class="bb-bd">Percentage:<b> {$viewRenderer->commonHelper()->sanitizeNumber($percent5,2,true)} % </b></p></td>
		 <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"><p class="bb-bd">Gross Amount:<b> {$viewRenderer->commonHelper()->sanitizeNumber($schedule5,2,true)}</b></p></td>
		 <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"><p class="bb-bd">Tax Amount:<b> {$viewRenderer->commonHelper()->sanitizeNumber($tax5,2,true)}</b></p></td>
		 <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"><p class="bb-bd">Net Amount:<b> {$viewRenderer->commonHelper()->sanitizeNumber($netamount5,2,true)}</b></p></td>
               </tr>
              </tbody>
            </table>
            </div>
            <table>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td>&nbsp;</td></tr> 

 <tr>
          <td align="center" width="100%"><b class="bld">Discount</b></td>
        </tr>
 </table>
 <div>
 <table cellpadding="0" cellspacing="0" align="center" width="585px" style="border:1px solid #000; background:#fff;margin-top:15px;" >
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Charge</b></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">Cost Before Discount</b></td>
		  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">After Plan Discount</b></td>
		  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><b class="bb-bd">After Post Sale Discount</b></td>
                </tr>
                 <tbody>
EOT;
        foreach($costSheet as $key => $cost){
            $pdfhtml .= <<<EOT
                <tr>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$key}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$cost[0]}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$cost[1]}</p></td>
                  <td align="center" style="border-bottom:1px solid #000; border-right:1px solid #000;"><p class="bb-bd">{$cost[2]}</p></td>
               </tr>
EOT;
        }
        $pdfhtml .= <<<EOT
              </tbody>
            </table>
            </div>
</body>
</html>

EOT;

        return $pdfhtml;
    }
    public function paymentSchedulePrintAction(){

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

                    $this->_view->setTerminal(true);
                    $UnitId = $this->params()->fromRoute('paySchPrintId');

                    if(preg_match('/^[\d]+$/', $UnitId) == FALSE) {
                        throw new \Exception('Invalid receipt-id');
                    }
                    $select = $sql->select();
                    $select->from(array('a' => 'KF_UnitMaster'))
                        ->columns(array(new Expression("a.UnitId, a.UnitNo, a.UnitArea, a.Status,a.ProjectId, Case When i.BookingId > 0 then 1 else  0  End as count")))
                        ->join(array('b' => 'CRM_UnitDetails'), 'b.UnitId=a.UnitId',array('*'), $select::JOIN_LEFT)
                        ->join(array('p' => 'Crm_ProjectDetail'), 'p.ProjectId=a.ProjectId', array(), $select::JOIN_LEFT)
                        ->join(array('g' => 'Proj_UOM'), 'p.AreaUnit=g.UnitId',array('UnitName'), $select::JOIN_LEFT)
                        ->join(array("d"=>"Crm_FacingMaster"), "b.FacingId=d.FacingId", array("fac"=>"Description"), $select::JOIN_LEFT)
                        ->join(array("e"=>"Crm_StatusMaster"), "b.StatusId=e.StatusId", array("status"=>"Description"), $select::JOIN_LEFT)
                        ->join(array('f' => 'KF_BlockMaster'), 'f.BlockId=a.BlockId', array('BlockName'), $select::JOIN_LEFT)
                        ->join(array('f1' => 'KF_FloorMaster'), 'f1.FloorId=a.FloorId', array('FloorName'), $select::JOIN_LEFT)
                        ->join(array('i' => 'Crm_UnitBooking'), new Expression("a.UnitId=i.UnitId and i.DeleteFlag=0"), array('B00kRate'=>'Rate','BOther'=>'OtherCostAmount','BQual'=>'QualifierAmount','BDiscountType'=>'DiscountType','BNet'=>'NetAmount','BBase'=>'BaseAmount','BConst'=>'ConstructionAmount',"BOther"=>'OtherCostAmount','BLand'=>'LandAmount','BookingStatus' => new Expression("CAST ( CASE WHEN i.BookingId IS NOT NULL THEN 'Sold' ELSE '' END AS varchar(11))"),'BookingId','Approve','BDiscount'=>new Expression("isnull(i.Discount,0)")), $select::JOIN_LEFT)
                        ->join(array('o' => 'Crm_PostSaleDiscountRegister'), 'o.BookingId=i.BookingId ', array('PostSaleDiscountId','PrevRate'=>'Rate','base'=>'BaseAmount','const'=>'ConstructionAmount','land'=>'LandAmount','gross'=>'GrossAmount','PostDiscount',"other"=>'OtherCostAmount',"PostDiscountType","qual"=>'QualifierAmount',"net"=>'NetAmount','PRate'=>'Rate'), $select::JOIN_LEFT)
                        //->join(array('j' => 'Crm_UnitBlock'), new Expression("a.UnitId=j.UnitId and j.DeleteFlag=0"), array('BlockId','ValidUpto','BlockBAdv'=>'AdvAmnt','Blockbase'=>'BaseAmount','BRate','BlockDiscount'=>'Discount','Blockconst'=>'ConstructionAmount','Blockland'=>'LandAmount','Blockgross'=>'GrossAmount',"Blockother"=>'OtherCost',"Blockqual"=>'QualAmount',"Blocknet"=>'NetAmount','BlockedStatus' => new Expression("CAST ( CASE WHEN j.BlockId IS NOT NULL THEN 'Blocked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)
                        //->join(array('x' => 'Crm_UnitPreBooking'),new Expression("a.UnitId=x.UnitId and x.DeleteFlag=0"), array('PreBookingId','ValidUpto','PreBAdv'=>'AdvAmount','Prebase'=>'BaseAmount','PreRate'=>'PRate','PreDiscount'=>'Discount','Preconst'=>'ConstructionAmount','Preland'=>'LandAmount','Pregross'=>'GrossAmount',"Preother"=>'OtherCost',"Prequal"=>'QualAmount',"Prenet"=>'NetAmount','PreStatus' => new Expression("CAST ( CASE WHEN x.PreBookingId IS NOT NULL THEN 'PreBooked' ELSE '' END AS varchar(11))")), $select::JOIN_LEFT)                        ->join(array('h' => 'Crm_Leads'), 'h.LeadId=i.LeadId', array('BuyerName' => 'LeadName'), $select::JOIN_LEFT)
                        // ->join(array('k' => 'Crm_Leads'), 'k.LeadId=j.LeadId', array('BlockedName' => 'LeadName'), $select::JOIN_LEFT)
                        ->join(array('q' => 'Crm_LeadPersonalInfo'), 'q.LeadId=i.LeadId', array('BuyerPhoto'=>'Photo'), $select::JOIN_LEFT)
                        // ->join(array('w' => 'Crm_LeadPersonalInfo'), 'w.LeadId=j.LeadId', array('BlockedPhoto'=>'Photo'), $select::JOIN_LEFT)
                        ->join(array('s' => 'Crm_Leads'), 'i.LeadId=s.LeadId', array('LeadName','Mobile'), $select::JOIN_LEFT)
                        ->join(array("c"=>"WF_Users"), "c.UserId=i.ExecutiveId", array("ExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        // ->join(array("m"=>"WF_Users"), "j.ExecutiveId=m.UserId", array("BlockExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        //  ->join(array("s"=>"WF_Users"), "y.ExecutiveId=s.UserId", array("PreExecutiveName" => 'EmployeeName'), $select::JOIN_LEFT)
                        ->join(array("z"=>"Crm_LeadAddress"),  new Expression("i.LeadId=z.LeadId and z.AddressType = 'P' "), array('Address1','Address2','Email','PanNo'), $select::JOIN_LEFT)
                        ->join(array("y"=>"Crm_LeadPersonalInfo"), "i.LeadId=y.LeadId", array(), $select::JOIN_LEFT)
                        ->join(array("x"=>"Crm_ProfessionMaster"), "y.professionId=x.professionId", array('Description'), $select::JOIN_LEFT)
                        ->join(array("v"=>"Crm_LeadCoApplicantInfo"), "i.LeadId=v.LeadId", array('LeadId','CoApplicantName1' =>'CoApplicantName'), $select::JOIN_LEFT)
                        ->join(array("u"=>"Crm_CoApplicantTrans"), "i.BookingId=u.BookingId", array('CoApplicantName2' => 'CoApplicantName'), $select::JOIN_LEFT)
                        ->join(array("w"=>"Crm_UnitType"), "b.UnitId=i.UnitId", array(), $select::JOIN_LEFT)
                        ->join(array("n"=>"KF_UnitTypeMaster"), "a.UnitTypeId=n.UnitTypeId", array('UnitTypeId','UnitTypeName','Area'), $select::JOIN_INNER)
                        ->join(array("m"=>"Proj_ProjectMaster"), "a.ProjectId=m.ProjectId", array('ProjectName'), $select::JOIN_LEFT)
                        ->where(array("a.UnitId"=>$UnitId))
                        ->order("o.PostSaleDiscountId desc");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $paymentScheduleprnt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();


//                    $select = $sql->select();
//                    $select->from(array('a' => 'Crm_UnitDetails'))
//                        ->Columns(array('CarpetArea','GrossAmount','LandAmount','ConstructionAmount','AdvAmount'))
//                        ->join(array("b"=>"Crm_Leads"), "a.UnitId=b.LeadId", array('Email','Mobile','LeadName'), $select::JOIN_LEFT)
//                        ->join(array("c"=>"Crm_LeadAddress"), "a.UnitId=c.AddressId", array('Address1','Address2','Email','PanNo'), $select::JOIN_LEFT)
//                        ->join(array("e"=>"Crm_LeadPersonalInfo"), "a.UnitId=e.LeadId", array(), $select::JOIN_LEFT)
//                        ->join(array("f"=>"Crm_ProfessionMaster"), "e.professionId=f.professionId", array('Description'), $select::JOIN_LEFT)
//                        ->join(array("g"=>"Crm_LeadCoApplicantInfo"), "a.UnitId=g.CoAppId", array('LeadId','CoApplicantName1' =>'CoApplicantName'), $select::JOIN_LEFT)
//                        ->join(array("h"=>"Crm_CoApplicantTrans"), "g.CoAppId=h.CoApplicantId", array('CoApplicantName2' => 'CoApplicantName'), $select::JOIN_LEFT)
//                        ->join(array("i"=>"Crm_UnitType"), "a.UnitId=i.UnitTypeId", array(), $select::JOIN_LEFT)
//                        ->join(array("j"=>"Crm_UnitTypeMaster"), "a.UnitId=j.UnitTypeId", array('UnitTypeName'), $select::JOIN_LEFT)
//                        ->join(array("k"=>"Crm_LevelMaster"), "a.UnitId=k.LevelId", array('LevelName'), $select::JOIN_LEFT)
//                        ->join(array("l"=>"KF_UnitMaster"), "a.UnitId=l.UnitId", array('UnitNo'), $select::JOIN_LEFT)
//                        ->join(array('q' => 'Crm_ProjectDetail'), 'q.ProjectId=l.ProjectId', array(), $select::JOIN_LEFT)
//                        ->join(array('r' => 'Proj_UOM'), 'r.UnitId=q.AreaUnit', array('UnitName'), $select::JOIN_LEFT)
//                        ->join(array('s' => 'Crm_UnitBooking'), 's.UnitId=a.UnitId', array(), $select::JOIN_LEFT)
//                        ->join(array('t' => 'Crm_FinalisationOtherCostTrans'), 's.BookingId=t.BookingId', array('Amount'), $select::JOIN_LEFT)
//                        ->where(array('a.UnitId' => $UnitId, 'a.DeleteFlag' => 0, 'c.AddressType' => 'P'));
//                    $stmt = $sql->getSqlStringForSqlObject($select);
//                    $paymentScheduleprnt = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    if($paymentScheduleprnt['count'] == 1 && $paymentScheduleprnt['PostSaleDiscountId'] > 0) {
                        $paymentScheduleprnt['OtherCostAmt'] = $paymentScheduleprnt['other'];
                        $paymentScheduleprnt['NetAmt'] = $paymentScheduleprnt['net'];
                        $paymentScheduleprnt['QualifierAmount'] =$paymentScheduleprnt['qual'];
                        $paymentScheduleprnt['Discount'] =$paymentScheduleprnt['PostDiscount'];
                        $paymentScheduleprnt['BaseAmt'] = $paymentScheduleprnt['base'];
                        $paymentScheduleprnt['ConstructionAmount'] = $paymentScheduleprnt['const'];
                        $paymentScheduleprnt['GrossAmount'] =$paymentScheduleprnt['gross'];
                        $paymentScheduleprnt['LandAmount'] =$paymentScheduleprnt['land'];
                        $paymentScheduleprnt['Rate'] =$paymentScheduleprnt['PrevRate'];
                        $paymentScheduleprnt['UnitTypeName'] =$paymentScheduleprnt['UnitTypeName'];
                    }
                    else if($paymentScheduleprnt['count'] == 1) {
                        $paymentScheduleprnt['OtherCostAmt'] = $paymentScheduleprnt['BOther'];
                        $paymentScheduleprnt['NetAmt'] = $paymentScheduleprnt['BNet'];
                        $paymentScheduleprnt['QualifierAmount'] =$paymentScheduleprnt['BQual'];
                        $paymentScheduleprnt['Discount'] =$paymentScheduleprnt['BDiscount'];
                        $paymentScheduleprnt['BaseAmt'] = $paymentScheduleprnt['BBase'];
                        $paymentScheduleprnt['ConstructionAmount'] = $paymentScheduleprnt['BConst'];
                        $paymentScheduleprnt['GrossAmount'] =$paymentScheduleprnt['BBase']+ $paymentScheduleprnt['BOther'];
                        $paymentScheduleprnt['LandAmount'] =$paymentScheduleprnt['BLand'];
                        $paymentScheduleprnt['Rate'] =$paymentScheduleprnt['B00kRate'];
                        $paymentScheduleprnt['UnitTypeName'] =$paymentScheduleprnt['UnitTypeName'];

                    }
                    if(empty($paymentScheduleprnt)) {
                        throw new \Exception('Invalid Receipt!');

                    }
                    $select = $sql->select();
                    $select->from('Crm_UnitBooking')
                        ->where(array('UnitId' => $paymentScheduleprnt['UnitId'],'DeleteFlag'=>0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitBooking = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->unitBooking = $unitBooking;

                    $select = $sql->select();
                    $select->from('Crm_PostSaleDiscountRegister')
                        ->columns(array('PostSaleDiscountId','BookingId'))
                        ->where(array('BookingId' => $unitBooking['BookingId']))
                        ->order("PostSaleDiscountId desc");
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $unitPostSale = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $postsalecount=count($unitPostSale['BookingId']);
                    //payment schedules
                    $select = $sql->select();
                    $select->from('Crm_PaymentSchedule')
                        ->where(array('ProjectId' => $paymentScheduleprnt['ProjectId'], 'DeleteFlag' => 0));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arrPaymentSchedules = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $select = $sql->select();
                    $select->from(array('a' => 'CRM_FinalisationOtherCostTrans'))
                        ->join(array('b' => 'CRM_OtherCostMaster'), 'a.OtherCostId=b.OtherCostId', array('OtherCostName'), $select::JOIN_LEFT)
                        ->where(array('a.UnitId' => $UnitId));
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrUnitOtherCost = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();



                    // Receipt Type
                    $select = $sql->select();
                    $select->from('Crm_ReceiptTypeMaster');
                    $stmt = $sql->getSqlStringForSqlObject($select);
                    $arrResults = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arrReceiptTypes = $arrResults;
                    $arrAllReceiptTypes = array();
                    foreach($arrResults as $result) {
                        $arrAllReceiptTypes[$result['ReceiptTypeId']] = $result['Type'];
                    }
                    if( $postsalecount > 0 ){
                        $select1 = $sql->select();
                        $select1->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                            ->where(array('a.StageType' => 'S', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));

                        $select2 = $sql->select();
                        $select2->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                            ->where(array('a.StageType' => 'D', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                        $select2->combine($select1,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                            ->where(array('a.StageType' => 'O', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                        $select3->combine($select2,'Union ALL');

                        $select4 = $sql->select();
                        $select4->from(array('a' => 'Crm_PSDPaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                            ->where(array('a.StageType' => 'A', 'BookingId' => $unitBooking['BookingId'],'PostSaleDiscountId'=>$unitPostSale['PostSaleDiscountId']));
                        $select4->combine($select3,'Union ALL');

                        $select5 = $sql->select();
                        $select5->from(array("g"=>$select4))
                            ->columns(array('*'))
                            ->where(array('BookingId' => $unitBooking['BookingId']))
                            ->order("g.SortId ASC");
                        $stmt = $sql->getSqlStringForSqlObject($select5);
                        $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(!empty($arrPaymentScheduleDetails)) {

                            foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                                // receipt type
                                $select1 = $sql->select();
                                $select1->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                    ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                                    ->where( array( 'b.ReceiptType' => 'S', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                                $select2 = $sql->select();
                                $select2->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                    ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                                    ->where( array( 'a.ReceiptType' => 'D', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                                $select2->combine( $select1, 'Union ALL' );

                                $select3 = $sql->select();
                                $select3->from( array( 'a' => 'Crm_PSDPaymentScheduleUnitReceiptTypeTrans' ) )
                                    ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                                    ->where( array( 'a.ReceiptType' => 'O', 'a.PSDPaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                                $select3->combine( $select2, 'Union ALL' );

                                $select4 = $sql->select();
                                $select4->from( array( "g" => $select3 ) )
                                    ->columns( array( '*' ) )
                                    ->order("g.ReceiptTypeTransId ASC");

                                $stmt = $sql->getSqlStringForSqlObject( $select4 );
                                $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                if(!empty($arrReceiptTypes)) {

                                    $iQualCount = 0;
                                    foreach($arrReceiptTypes as &$receipt) {

                                        switch($receipt['ReceiptType']) {
                                            case 'O':
                                                $receipt['Type'] = 'O';
                                                break;
                                            case 'S':
                                                $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                                break;
                                        }

                                        // qualifier
                                        $select = $sql->select();
                                        $select->from( array( 'a' => 'Crm_PSDPaymentScheduleQualifierTrans' ) )
                                            ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                                'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                                'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                            ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                                        $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                                        $statement = $sql->getSqlStringForSqlObject( $select );
                                        $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                        if ( !empty( $qualList ) ) {
                                            foreach($qualList as &$qual) {
                                                $qual['BaseAmount'] = $receipt['Amount'];
                                            }

                                            $sHtml = Qualifier::getQualifier( $qualList );
                                            $iQualCount = $iQualCount + 1;
                                            $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                            $receipt[ 'qualHtmlTag' ] = $sHtml;

                                        }

                                    }

                                    $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                                }
                            }
                        }
                    }
                    else{

                        // current payment schedule detail
                        $select1 = $sql->select();
                        $select1->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'KF_StageMaster'), 'a.StageId = b.StageId', array('StageName'), $select1::JOIN_LEFT)
                            ->where(array('a.StageType' => 'S', 'BookingId' => $unitBooking['BookingId']));

                        $select2 = $sql->select();
                        $select2->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'Crm_DescriptionMaster'), 'a.StageId = b.DescriptionId', array('StageName' => new Expression("b.DescriptionName")), $select2::JOIN_LEFT)
                            ->where(array('a.StageType' => 'D', 'BookingId' => $unitBooking['BookingId']));
                        $select2->combine($select1,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'Crm_OtherCostMaster'), 'a.StageId = b.OtherCostId', array('StageName' => new Expression("b.OtherCostName")), $select3::JOIN_LEFT)
                            ->where(array('a.StageType' => 'O', 'BookingId' => $unitBooking['BookingId']));
                        $select3->combine($select2,'Union ALL');

                        $select4 = $sql->select();
                        $select4->from(array('a' => 'Crm_PaymentScheduleUnitTrans'))
                            ->columns(array('*'))
                            ->join(array('b' => 'Crm_BookingAdvanceMaster'), 'a.StageId = b.BookingAdvanceId', array('StageName' => new Expression("b.BookingAdvanceName")), $select4::JOIN_LEFT)
                            ->where(array('a.StageType' => 'A', 'BookingId' => $unitBooking['BookingId']));
                        $select4->combine($select3,'Union ALL');

                        $select5 = $sql->select();
                        $select5->from(array("g"=>$select4))
                            ->columns(array('*'))
                            ->where(array('BookingId' => $unitBooking['BookingId']))
                            ->order("g.SortId ASC");
                        $stmt = $sql->getSqlStringForSqlObject($select5);
                        $arrPaymentScheduleDetails = $dbAdapter->query($stmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(!empty($arrPaymentScheduleDetails)) {

                            foreach($arrPaymentScheduleDetails as &$paymentSchedule) {
                                // receipt type
                                $select1 = $sql->select();
                                $select1->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                    ->join( array( 'b' => 'Crm_ReceiptTypeMaster' ), 'a.ReceiptTypeId = b.ReceiptTypeId', array( 'ReceiptName' => 'ReceiptTypeName' ), $select1::JOIN_LEFT )
                                    ->where( array( 'b.ReceiptType' => 'S', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );

                                $select2 = $sql->select();
                                $select2->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                    ->join( array( 'b' => 'Crm_DescriptionMaster' ), 'a.ReceiptTypeId = b.DescriptionId', array( 'ReceiptName' => 'DescriptionName' ), $select2::JOIN_LEFT )
                                    ->where( array( 'a.ReceiptType' => 'D', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ]) );
                                $select2->combine( $select1, 'Union ALL' );

                                $select3 = $sql->select();
                                $select3->from( array( 'a' => 'Crm_PaymentScheduleUnitReceiptTypeTrans' ) )
                                    ->join( array( 'b' => 'Crm_OtherCostMaster' ), 'a.ReceiptTypeId = b.OtherCostId', array( 'ReceiptName' => 'OtherCostName' ), $select3::JOIN_LEFT )
                                    ->where( array( 'a.ReceiptType' => 'O', 'a.PaymentScheduleUnitTransId' => $paymentSchedule[ 'PaymentScheduleUnitTransId' ] ) );
                                $select3->combine( $select2, 'Union ALL' );

                                $select4 = $sql->select();
                                $select4->from( array( "g" => $select3 ) )
                                    ->columns( array( '*' ) )
                                    ->order("g.ReceiptTypeTransId ASC");

                                $stmt = $sql->getSqlStringForSqlObject( $select4 );
                                $arrReceiptTypes = $dbAdapter->query( $stmt, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                if(!empty($arrReceiptTypes)) {

                                    $iQualCount = 0;
                                    foreach($arrReceiptTypes as &$receipt) {

                                        switch($receipt['ReceiptType']) {
                                            case 'O':
                                                $receipt['Type'] = 'O';
                                                break;
                                            case 'S':
                                                $receipt['Type'] = $arrAllReceiptTypes[$receipt['ReceiptTypeId']];;
                                                break;
                                        }

                                        // qualifier
                                        $select = $sql->select();
                                        $select->from( array( 'a' => 'Crm_PaymentScheduleQualifierTrans' ) )
                                            ->columns(array( 'QualifierId', 'YesNo', 'RefId' => new Expression( "'R'+ rtrim(ltrim(str(TransId)))" ), 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess','KKCess','SBCess','NetPer',
                                                'ExpressionAmt', 'TaxableAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'TaxAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'SurChargeAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),
                                                'EDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'HEDCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'KKCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ),'SBCessAmt' => new Expression( "CAST(0 As Decimal(18,2))" ), 'NetAmt' => new Expression( "CAST(0 As Decimal(18,2))" ) ), $select::JOIN_LEFT )
                                            ->join( array( "b" => "Proj_QualifierMaster" ), "a.QualifierId=b.QualifierId", array( 'QualifierName', 'QualifierTypeId' ), $select::JOIN_INNER );
                                        $select->where(array('PSReceiptTypeTransId' => $receipt['ReceiptTypeTransId']));
                                        $statement = $sql->getSqlStringForSqlObject( $select );
                                        $qualList = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
                                        if ( !empty( $qualList ) ) {
                                            foreach($qualList as &$qual) {
                                                $qual['BaseAmount'] = $receipt['Amount'];
                                            }

                                            $sHtml = Qualifier::getQualifier( $qualList );
                                            $iQualCount = $iQualCount + 1;
                                            $sHtml = str_replace( '__1', '_' . $iQualCount, $sHtml );
                                            $receipt[ 'qualHtmlTag' ] = $sHtml;

                                        }

                                    }

                                    $paymentSchedule['arrReceiptTypes'] = $arrReceiptTypes;


                                }
                            }
                        }}

                    $this->_view->arrPaymentScheduleDetails = $arrPaymentScheduleDetails;
                   // Print_R($paymentScheduleprnt); die;
                    $this->_view->paymentScheduleprnt = $paymentScheduleprnt;

                    $pdfHtmlFormat = $this->generatePaymentPdf($paymentScheduleprnt,$arrPaymentScheduleDetails,$arrUnitOtherCost);
                    //  echo $pdfHtmlFormat; die;
                    $path =  getcwd()."/vendor/dompdf/dompdf/dompdf_config.inc.php";
                    require_once($path);
                    $dompdf = new DOMPDF();

                    $dompdf->load_html($pdfHtmlFormat);
                    $dompdf->set_paper("A4");

                    $dompdf->render();
                    //$dompdf->get_canvas()->get_cpdf()->setEncryption($ClientPass,array('copy','print'));
                    $canvas = $dompdf->get_canvas();
                    $canvas->page_text(275, 820, "Page: {PAGE_NUM} of {PAGE_COUNT}", "", 8, array(0,0,0));

                    $dompdf->stream("TermSheet.pdf");
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

    private function generatePaymentPdf($paymentScheduleprnt,$arrPaymentScheduleDetails,$arrUnitOtherCost) {

        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $curDate = date('d-m-Y');
        $total = $paymentScheduleprnt['NetAmt'];
        $pdfHtmlFormat = <<<EOT



<!DOCTYPE html PUBLIC "-//W3C//DTD
 XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Untitled Document</title>
</head>
<style>
tr, th, td {
	border: solid 1px #000;
	 font-size:12px; padding:1px;
}
table {
	border-collapse:collapse;
	caption-side:bottom;
}
.sheet1 tr td{padding:12px 4px;}
</style>
<body>
<!-- CSS Code -->
  <div style="background: #ffffff none repeat scroll 0 0;font-family:'Lucida Grande'; margin: 0 auto;  width:100%;">
    <div style="border-bottom:5px solid #bdbdbe; width:100%; float:left; height:145px;">
      <div style="width:50%; float:left; padding-top:32px;"><img src="img/logo.jpg" /></div>
     <div align="right" style="width:50%; color:#999; text-align:right; font-size:25px; height:70px;  line-height:30px">Adding life to Living!&nbsp;</div>
    </div>
    <!-- HTML Code -->
      <div style="height:842px; width:100%;">
    <table  style="width:100%; margin:20px 0;">
      <tbody>
        <tr style="background:#ddd">
          <td  colspan="6" style=" font-size:20px; text-align:center"><span style="border-bottom:3px solid #000;">TERM SHEET</span></td>
        </tr>
      </tbody>
    </table>

    <!-- HTML Code -->
    <table class="sheet1" style="width:100%;">
      <tbody>
        <tr>
          <td style="background:#ddd;"><b>Date</b></td>
          <td  colspan="5">{$curDate}</td>
        </tr>
        <tr>
          <td  style="background:#ddd"><b>Apartment Number</b></td>
          <td width="10%">&nbsp;{$paymentScheduleprnt['UnitNo']}</td>
          <td style="background:#ddd"><b>Towers</b></td>
          <td width="10%">{$paymentScheduleprnt['FloorName']} </td>
          <td style="background:#ddd"><b>Apartment Type</b></td>
         <td width="10%">&nbsp;{$paymentScheduleprnt['UnitTypeName']}</td>
        </tr>
         <tr>
          <td  style="background:#ddd"><b>Block Number</b></td>
          <td width="10%">&nbsp;{$paymentScheduleprnt['BlockName']}</td>
          <td style="background:#ddd"><b>Carpet Area</b></td>
          <td width="10%">{$paymentScheduleprnt['CarpetArea']} </td>

        </tr>

        <tr>
          <td  style="background:#ddd"><b>UDS Land Area</b></td>
          <td width="10%">&nbsp;{$paymentScheduleprnt['UDSLandArea']}</td>
          <td style="background:#ddd"><b>Rate</b></td>
          <td width="10%">{$paymentScheduleprnt['Rate']} </td>
          <td style="background:#ddd"><b>Area</b></td>
         <td width="10%">&nbsp;{$paymentScheduleprnt['Area']}</td>

        </tr>
        <tr>
          <td style="background:#ddd"><b>Name of the Applicant</b></td>
          <td  colspan="5">{$paymentScheduleprnt['LeadName']}</td>
        </tr>
        <tr>
          <td style="background:#ddd"><b>Occupation</b></td>
          <td  colspan="5">{$paymentScheduleprnt['Description']}</td>
        </tr>
        <tr>
          <td rowspan="2" style="background:#ddd"><b>Correspondence Address</b></td>
          <td  width="10%" colspan="5">&nbsp;{$paymentScheduleprnt['Address1']}</td>
        </tr>
        <tr>
          <td  width="10%" colspan="5">&nbsp;{$paymentScheduleprnt['Address2']}</td>
        </tr>
        <tr>
          <td  style="background:#ddd"><b>Mobile Number</b></td>
           <td width="10%">&nbsp;{$paymentScheduleprnt['Mobile']}</td>
          <td style="background:#ddd"><b>Email Id</b></td>
      <td width="10%">&nbsp;{$paymentScheduleprnt['Email']}</td>
          <td style="background:#ddd"><b>Pan No</b></td>
           <td width="10%">&nbsp;{$paymentScheduleprnt['PanNo']}</td>
        </tr>
        <tr>
          <td style="background:#ddd"><b>Co Applicant1</b></td>
        <td width="10%">&nbsp;{$paymentScheduleprnt['CoApplicantName1']}</td>
          <td style="background:#ddd"><b>Email Id</b></td>
           <td width="10%">&nbsp;</td>
          <td style="background:#ddd"><b>Pan No</b></td>
          <td width="10%">&nbsp;</td>
        </tr>
        <tr>
          <td  style="background:#ddd"><b>Co Applicant2</b></td>
          <td width="10%">&nbsp;{$paymentScheduleprnt['CoApplicantName2']}</td>
          <td style="background:#ddd"><b>Email Id</b></td>
          <td width="10%">&nbsp;</td>
          <td style="background:#ddd"><b>Pan No</b></td>
          <td width="10%">&nbsp;</td>
        </tr>
      </tbody>
    </table>
    <!-- Codes by Quackit.com -->

    <div>
      <!-- HTML Code -->
      <table  class="sheet1" style="width:100%; background:#ddd; margin-top:20px;">
        <thead>
          <tr>
            <th colspan="3" align="left" style="font-size:12px;"><b>Cost Head</b></th>
            <th align="right"><b>Amount(Rs. )</b></th>
          </tr>
          <tr>
            <td>1</td>
            <td colspan="2" >Construction Value </td>
            <td align="right">{$viewRenderer->commonHelper()->sanitizeNumber($paymentScheduleprnt['ConstructionAmount'],2,true)}</td>
          </tr>
          <tr>
            <td>2</td>
            <td colspan="2" >Land Value </td>
            <td align="right">{$viewRenderer->commonHelper()->sanitizeNumber($paymentScheduleprnt['LandAmount'],2,true)}</td>
          </tr>

          <tr>
            <td>3</td>
            <td colspan="2" >Other cost</td>
            <td align="right">{$viewRenderer->commonHelper()->sanitizeNumber($paymentScheduleprnt['OtherCostAmt'],2,true)}</td>

          </tr>

          <tr>
            <td>&nbsp;</td>
            <td colspan="2" ><b>Total Amount (excluding Vat, Service Tax, Registration and Stamp Charges)</b></td>
            <td align="right"><b>{$viewRenderer->commonHelper()->sanitizeNumber($total,2,true)}</b></td>

          </tr>
          <tr>
            <td><b>Estimated VAT & Service Tax (at current rates; Rs.Lakhs)</b></td>
            <td style="background:#fff">{$viewRenderer->commonHelper()->sanitizeNumber($paymentScheduleprnt['QualifierAmount'],2,true)}</td>
            <td  ><b>Estimated Stamp Duty & Registration Charges (at current rates; Rs. Lakhs) </b></td>
            <td  style="background:#fff"></td>
          </tr>
        </thead>
      </table>
    </div>

    </div>
	<br />
	<br />
 <p style=" text-align:center; font-weight:bold">OtherCost Breakups</p>
<table cellpadding="0" cellspacing="0" align="center" width="585px" style="border:1px solid #000; background:#fff;margin-top:15px;" >

              	<tbody>
		<tr>
	        <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"colspan="2"><p class="bb-bd"><b class="bb-bd">Other Cost Name</b></p></td>
                <td align="center"style="border-bottom:1px solid #000;"colspan="2"><p class="bb-bd"><b class="bb-bd">Amount</b></p> </td>
	    </tr>
EOT;
        foreach($arrUnitOtherCost as $other){
            $pdfHtmlFormat .= <<<EOT
		<tr>
		    <td align="center"style="border-bottom:1px solid #000;border-right:1px solid #000;"colspan="2"><p class="bb-bd">{$other['OtherCostName']}</p></td>
                <td align="center"style="border-bottom:1px solid #000;"colspan="2"><p class="bb-bd">{$viewRenderer->commonHelper()->sanitizeNumber($other['Amount'],2,true)}</p></td>
		</tr>
EOT;
        }
        $pdfHtmlFormat .= <<<EOT

	        </tbody>
 </table>
 <p style=" text-align:center; font-weight:bold">Payment Schedule</p>
 <table>

	
    <!-- Codes by Quackit.com -->
      <div style="width:100%;">
    <div>
      <!-- HTML Code -->
      <table   class="sheet1" style="width:100%; background:#ddd; margin-top:20px;">
        <thead>
          <tr style="background:#ddd;margin-top:20px;">
             <th align="left" ><b>Payment Schedule</b></th>
            <th align="left"><b>Gross Amount</b></th>
            <th align="left"><b>Tax</b></th>
            <th align="left"><b>NetAmount</b></th>
          </tr>

EOT;
        $netamount1=0;
        $tax1=0;
        $sch1=0;

        foreach($arrPaymentScheduleDetails as $paymentSchedule) {
            $netamount1= $netamount1 + $paymentSchedule[ 'NetAmount' ];
            $tax1= $tax1 + $paymentSchedule[ 'QualAmount' ];
            $sch1= $sch1 + $paymentSchedule[ 'Amount' ];
            $pdfHtmlFormat .= <<<EOT
                   <tr style="background:#fff">
                        <td class="desc">{$paymentSchedule['StageName']}</td>
                        <td class="total">{$viewRenderer->commonHelper()->sanitizeNumber($paymentSchedule['Amount'],2,true)}</td>
						<td class="">{$viewRenderer->commonHelper()->sanitizeNumber($paymentSchedule['QualAmount'],2,true)}</td>
						<td class="">{$viewRenderer->commonHelper()->sanitizeNumber($paymentSchedule['NetAmount'],2,true)}</td>
                    </tr>
EOT;
        }
        $pdfHtmlFormat .= <<<EOT
						<tr>
							<td >Total</td>
							<td class="total"><b>{$viewRenderer->commonHelper()->sanitizeNumber($sch1,2,true)}</b></td>
							<td class="total"><b>{$viewRenderer->commonHelper()->sanitizeNumber($tax1,2,true)}</b></td>
							<td class="total"><b>{$viewRenderer->commonHelper()->sanitizeNumber($netamount1,2,true)}</b></td>
					  </tr>
        </thead>
      </table>

      </div>



      <!-- Codes by Quackit.com -->

    <div style="">
      <!-- HTML Code -->
      <p style=" text-align:center; font-weight:bold">* Service Tax and VAT as applicable, will be charged extra with each installment of payment towards Construction Cost</p>
      <table class="sheet1" style="width:100%; background:#ddd; margin-top:20px;">
        <thead>
          <tr>
            <td><b>Party</b></td>
            <td><b>Applicant</b></td>
            <td><b>Co-Applicant-1</b></td>
            <td><b>Co-Applicant-2</b></td>
            <td><b>Received On Behalf of {$paymentScheduleprnt['ProjectName']}</b></td>
          </tr>
          <tr style="background:#fff">
            <td>Initial</td>
             <td>&nbsp;{$paymentScheduleprnt['LeadName']}</td>
             <td>&nbsp;{$paymentScheduleprnt['CoApplicantName1']}</td>
             <td>&nbsp;{$paymentScheduleprnt['CoApplicantName2']}</td>
             <td>&nbsp;</td>
          </tr>

          </thead>
          </table>
          </div>


      </div>
    </div>


</body>
</html>


EOT;
        return $pdfHtmlFormat;
    }

}