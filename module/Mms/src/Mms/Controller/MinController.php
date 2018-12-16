<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Mms\Controller;

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

class MinController extends AbstractActionController
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

    public function entryAction() {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($request->isPost()) {
                $postData = $request->getPost();
                $resp = array();
                if ($postData['mode'] == 'poList') {
                    $poSelect = $sql->select();
                    $poSelect->from(array("a" => "MMS_PORegister"))
                        ->columns(array("PONo", "PODate", "PORegisterId"))
                        ->join(array("b" => "MMS_POTrans"), "a.PORegisterId=b.PORegisterId", array(), $poSelect::JOIN_INNER)
                        ->join(array("c" => "MMS_IPDTrans"), "b.PoTransId=c.POTransId", array(), $poSelect::JOIN_INNER)
                        ->join(array("d" => "MMS_POProjTrans"), "b.PoTransId=d.POTransId", array(), $poSelect::JOIN_INNER)
                        ->join(array("e" => "MMS_IPDProjTrans"), "d.POProjTransId=e.POProjTransId and c.IPDTransId=e.IPDTransId", array("Qty" => new Expression("sum(e.Qty)")), $poSelect::JOIN_INNER);
                    $poSelect->where(array("e.CostCentreId" => $postData['cost']));
                    $poSelect->group(array("a.PONo", "a.PODate", "a.PORegisterId"));
                    $poStatement = $sql->getSqlStringForSqlObject($poSelect);
                    $resp['data'] = $dbAdapter->query($poStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($postData['mode'] == 'resList') {
                    $resSelect = $sql->select();
                    $resSelect->from(array("a" => "MMS_POTrans"))
                        ->columns(array("ResourceId", "POQty" => new Expression("CAST(POQty As Decimal(18,5))")))
                        ->join(array("b" => "MMS_PORegister"), "a.PORegisterId=b.PORegisterId", array("PONo" => new Expression("CONCAT(PONo, ':', '" . $postData['cost'] . "')"), "PODate"), $resSelect::JOIN_INNER)
                        ->join(array("c" => "Proj_Resource"), "a.ResourceId=c.ResourceId", array("Resource" => new Expression("CONCAT(ResourceName, ':', Code)")), $resSelect::JOIN_INNER)
                        ->join(array("d" => "Proj_UOM"), "c.UnitId=d.UnitId", array(), $resSelect::JOIN_INNER);
                    $resSelect->where(array("a.PORegisterId" => explode(',', $postData['poId'])));
                    $resStatement = $sql->getSqlStringForSqlObject($resSelect);
                    $resp['data'] = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($postData['mode'] == 'dataList') {
                    $selectPORes = $sql->select();
                    $selectPORes->from(array("a" => "MMS_POTrans"));
                    $selectPORes->columns(array("PoTransId", "ResourceId", 'Quantity' => new Expression('SUM(a.POQty)')), array("Code", "ResourceName"), array("UnitName", "UnitId"))
                        ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code", "ResourceName"), $selectPORes::JOIN_INNER)
                        ->join(array("c" => "Proj_UOM"), "c.UnitId=a.UnitId", array("UnitName", "UnitId"), $selectPORes::JOIN_INNER)
                        ->where(array("a.PORegisterId" => explode(',', $postData['poId']), "a.ResourceId" => explode(',', $postData['resId'])));
                    $selectPORes->group(new Expression("a.PoTransId,a.ResourceId,b.Code,b.ResourceName,c.UnitName,c.UnitId"));
                    $selectPOResStmt = $sql->getSqlStringForSqlObject($selectPORes);
                    $poResResult = $dbAdapter->query($selectPOResStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach ($poResResult as $res) {
                        $res['poList'] = array();
                        $selectPOList = $sql->select();
                        $selectPOList->from(array("a" => "MMS_POTrans"));
                        $selectPOList->columns(array("ResourceId", "Quantity" => new Expression("a.POQty")), array("PORegisterId", "PONo"))
                            ->join(array("b" => "MMS_PORegister"), "a.PORegisterId=b.PORegisterId", array("PORegisterId", "PONo"), $selectPOList::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"), "a.ResourceId=c.ResourceId", array(), $selectPOList::JOIN_INNER)
                            ->where(array("a.PORegisterId" => explode(',', $postData['poId']), "a.ResourceId" => $res['ResourceId']));
                        $selectPOListStmt = $sql->getSqlStringForSqlObject($selectPOList);
                        $poListResult = $dbAdapter->query($selectPOListStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach ($poListResult as $poList) {
                            $selectPOWBSList = $sql->select();
                            $selectPOWBSList->from(array("a1" => "MMS_IPDAnalTrans"));
                            $selectPOWBSList->columns(array("AnalysisId", "ResourceId", "Quantity" => new Expression("a1.Qty")), array("WBSName"), array("PORegisterId"))
                                ->join(array("d" => "Proj_WBSMaster"), "a1.AnalysisId=d.WBSId", array("WBSName"), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a2" => "MMS_POAnalTrans"), "a1.POAHTransId=a2.POAnalTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("b1" => "MMS_IPDProjTrans"), "a2.POProjTransId=b1.POProjTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a3" => "MMS_POProjTrans"), "a2.POProjTransId=a3.POProjTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a5" => "MMS_IPDTrans"), "a3.POTransId=a5.POTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a4" => "MMS_POTrans"), "a3.POTransId=a4.PoTransId", array("PORegisterId"), $selectPOWBSList::JOIN_INNER)
                                ->where(array("a4.PORegisterId" => $poList['PORegisterId'], "a4.ResourceId" => $res['ResourceId']));
                            $selectPOWBSListStmt = $sql->getSqlStringForSqlObject($selectPOWBSList);
                            $poList['wbs'] = $dbAdapter->query($selectPOWBSListStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            array_push($res['poList'], $poList);
                        }
                        array_push($resp, $res);
                    }
                }
                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
                return $response;
            }
        } else if ($request->isPost()) {
            $postData = $request->getPost();
            //begin trans try block example starts
            $vNo = CommonHelper::getVoucherNo(5, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $voucher = '';
            if ($vNo['genType']) {
                $vNo = CommonHelper::getVoucherNo(5, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                $voucher = $vNo['voucherNo'];
            } else {
                $voucher = $postData['grn_no'];
            }
            $json = json_decode($postData['hidjson'], true);

            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $registerInsert = $sql->insert('MMS_MINRegister');
                //GRNNo,GRNDate,GRNSiteNo,GRNSiteDate,Tested,Tested,Testingmethod,Testresult
                $registerInsert->values(array("GRNDate" => date('Y-m-d', strtotime($postData["grn_date"])),
                    "GRNNo" => $voucher, "GRNSiteDate" => date('Y-m-d', strtotime($postData["grnsite_date"])), "GRNSiteNo" => $postData["grnsite_no"],
                    "Tested" => ($postData["Tested"]) ? $postData["Tested"] : '0', "Testedby" => $postData["Testedby"], "Testingmethod" => $postData["Testingmethod"],
                    "Testresult" => $postData["Testresult"]));
                $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                $MINRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();
                foreach ($json as $resource) {
                    $resId = $resource['resId'];
                    //MMS_MINTrans
                    //MINRegisterId,POTransId,ResourceId,UnitId,AcceptQty,RejectQty,CostCentreId
                    $requestInsert = $sql->insert('MMS_MINTrans');
                    $requestInsert->values(array("MINRegisterId" => $MINRegisterId, "POTransId" => $postData['transId_' . $resId], "UnitId" => $postData['unitId_' . $resId],
                        "ResourceId" => $resId, "AcceptQty" => $postData['acceptedQty_' . $resId], "CostCentreId" => $postData['project_name'], "RejectQty" => $postData['rejectedQty_' . $resId]));
                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                    $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $MINTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    //WareHouse
                    /*foreach($WHValue as $wbs){
                        //MMS_MINWarehouseTrans
                        //MINTransId,WareHouseId,DCQty,CostCentreId
                        $requestInsert = $sql->insert('MMS_MINWarehouseTrans');
                        $requestInsert->values(array("MINTransId"=>$MINTransId, "WareHouseId"=>$MINProjTransId, "DCQty"=>$postData['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs],
                             'CostCentreId'=>$CostCentreId));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }*/

                    foreach ($resource['poId'] as $po) {
                        $poId = $po['PORegisterId'];
                        //MMS_MINProjTrans
                        //MINTransId,CostCentreId,ResourceId,UnitId,AcceptQty,RejectQty
                        $requestInsert = $sql->insert('MMS_MINProjTrans');
                        $requestInsert->values(array("MINTransId" => $MINTransId, "CostCentreId" => $postData['project_name'], "UnitId" => $postData['unitId_' . $resId],
                            "ResourceId" => $resId, "AcceptQty" => $postData['acceptPOQty_' . $resId . '_' . $poId], "RejectQty" => $postData['rejectPOQty_' . $resId . '_' . $poId]));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $MINProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        foreach ($po['wbsId'] as $wbs) {
                            //MMS_MINAnalTrans
                            //MINProjTransId,MINTransId,AnalysisId,ResourceId,UnitId,AcceptQty,RejectQty
                            $requestInsert = $sql->insert('MMS_MINAnalTrans');
                            $requestInsert->values(array("MINTransId" => $MINTransId, "MINProjTransId" => $MINProjTransId, "AnalysisId" => $wbs,
                                "ResourceId" => $resId, "AcceptQty" => $postData['acceptWbs_' . $resId . '_' . $poId . '_' . $wbs],
                                "RejectQty" => $postData['rejectWbs_' . $resId . '_' . $poId . '_' . $wbs], 'UnitId' => $postData['unitId_' . $resId]));
                            $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                            $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }
                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends

        }

        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

        $projSelect = $sql->select();
        $projSelect->from('WF_OperationalCostCentre')
            ->columns(array("data" => "CostCentreId", "value" => "CostCentreName"));
        $projStatement = $sql->getSqlStringForSqlObject($projSelect);
        $proResults = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $vNo = CommonHelper::getVoucherNo(5, date('Y/m/d'), 0, 0, $dbAdapter, "");
        $this->_view->vNo = $vNo;
        $this->_view->proResults = $proResults;
        return $this->_view;
    }

    public function indexAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");

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

    public function entryEditAction($json)
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index", "action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($request->isPost()) {
                $postData = $request->getPost();
                $resp = array();
                if ($postData['mode'] == 'poList') {
                    $poSelect = $sql->select();
                    $poSelect->from(array("a" => "MMS_PORegister"))
                        ->columns(array("PONo", "PODate", "PORegisterId"))
                        ->join(array("b" => "MMS_POTrans"), "a.PORegisterId=b.PORegisterId", array(), $poSelect::JOIN_INNER)
                        ->join(array("c" => "MMS_IPDTrans"), "b.PoTransId=c.POTransId", array(), $poSelect::JOIN_INNER)
                        ->join(array("d" => "MMS_POProjTrans"), "b.PoTransId=d.POTransId", array(), $poSelect::JOIN_INNER)
                        ->join(array("e" => "MMS_IPDProjTrans"), "d.POProjTransId=e.POProjTransId and c.IPDTransId=e.IPDTransId", array("Qty" => new Expression("sum(e.Qty)")), $poSelect::JOIN_INNER);
                    $poSelect->where(array("e.CostCentreId" => $postData['cost']));
                    $poSelect->group(array("a.PONo", "a.PODate", "a.PORegisterId"));
                    $poStatement = $sql->getSqlStringForSqlObject($poSelect);
                    $resp['data'] = $dbAdapter->query($poStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($postData['mode'] == 'resList') {
                    $resSelect = $sql->select();
                    $resSelect->from(array("a" => "MMS_POTrans"))
                        ->columns(array("ResourceId", "POQty" => new Expression("CAST(POQty As Decimal(18,5))")))
                        ->join(array("b" => "MMS_PORegister"), "a.PORegisterId=b.PORegisterId", array("PONo" => new Expression("CONCAT(PONo, ':', '" . $postData['cost'] . "')"), "PODate"), $resSelect::JOIN_INNER)
                        ->join(array("c" => "Proj_Resource"), "a.ResourceId=c.ResourceId", array("Resource" => new Expression("CONCAT(ResourceName, ':', Code)")), $resSelect::JOIN_INNER)
                        ->join(array("d" => "Proj_UOM"), "c.UnitId=d.UnitId", array(), $resSelect::JOIN_INNER);
                    $resSelect->where(array("a.PORegisterId" => explode(',', $postData['poId'])));
                    $resStatement = $sql->getSqlStringForSqlObject($resSelect);
                    $resp['data'] = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if ($postData['mode'] == 'dataList') {
                    $selectPORes = $sql->select();
                    $selectPORes->from(array("a" => "MMS_POTrans"));
                    $selectPORes->columns(array("ResourceId", 'Quantity' => new Expression('SUM(a.POQty)')), array("Code", "ResourceName"), array("UnitName", "UnitId"))
                        ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array("Code", "ResourceName"), $selectPORes::JOIN_INNER)
                        ->join(array("c" => "Proj_UOM"), "c.UnitId=a.UnitId", array("UnitName", "UnitId"), $selectPORes::JOIN_INNER)
                        ->where(array("a.PORegisterId" => explode(',', $postData['poId']), "a.ResourceId" => explode(',', $postData['resId'])));
                    $selectPORes->group(new Expression("a.ResourceId,b.Code,b.ResourceName,c.UnitName,c.UnitId"));
                    $selectPOResStmt = $sql->getSqlStringForSqlObject($selectPORes);
                    $poResResult = $dbAdapter->query($selectPOResStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach ($poResResult as $res) {
                        $res['poList'] = array();
                        $selectPOList = $sql->select();
                        $selectPOList->from(array("a" => "MMS_POTrans"));
                        $selectPOList->columns(array("ResourceId", "Quantity" => new Expression("a.POQty")), array("PORegisterId", "PONo"))
                            ->join(array("b" => "MMS_PORegister"), "a.PORegisterId=b.PORegisterId", array("PORegisterId", "PONo"), $selectPOList::JOIN_INNER)
                            ->join(array("c" => "Proj_Resource"), "a.ResourceId=c.ResourceId", array(), $selectPOList::JOIN_INNER)
                            ->where(array("a.PORegisterId" => explode(',', $postData['poId']), "a.ResourceId" => $res['ResourceId']));
                        $selectPOListStmt = $sql->getSqlStringForSqlObject($selectPOList);
                        $poListResult = $dbAdapter->query($selectPOListStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach ($poListResult as $poList) {
                            $selectPOWBSList = $sql->select();
                            $selectPOWBSList->from(array("a1" => "MMS_IPDAnalTrans"));
                            $selectPOWBSList->columns(array("AnalysisId", "ResourceId", "Quantity" => new Expression("a1.Qty")), array("WBSName"), array("PORegisterId"))
                                ->join(array("d" => "Proj_WBSMaster"), "a1.AnalysisId=d.WBSId", array("WBSName"), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a2" => "MMS_POAnalTrans"), "a1.POAHTransId=a2.POAnalTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("b1" => "MMS_IPDProjTrans"), "a2.POProjTransId=b1.POProjTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a3" => "MMS_POProjTrans"), "a2.POProjTransId=a3.POProjTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a5" => "MMS_IPDTrans"), "a3.POTransId=a5.POTransId", array(), $selectPOWBSList::JOIN_INNER)
                                ->join(array("a4" => "MMS_POTrans"), "a3.POTransId=a4.PoTransId", array("PORegisterId"), $selectPOWBSList::JOIN_INNER)
                                ->where(array("a4.PORegisterId" => $poList['PORegisterId'], "a4.ResourceId" => $res['ResourceId']));
                            $selectPOWBSListStmt = $sql->getSqlStringForSqlObject($selectPOWBSList);
                            $poList['wbs'] = $dbAdapter->query($selectPOWBSListStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            array_push($res['poList'], $poList);
                        }
                        array_push($resp, $res);
                    }
                }
                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
                return $response;
            }
        } else if ($request->isPost()) {
            //begin trans try block example starts
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try {
                $postData = $request->getPost();
                $MINRegisterId = $postData["minRegId"];
                /*delete MMS_MINAnalTrans*/
                $subQuery = $sql->select();
                $subQuery->from(array("a" => "MMS_MINProjTrans"))
                    ->columns(array("MINProjTransId"))
                    ->join(array("b" => "MMS_MINTrans"), "a.MINTransId=b.MINTransId", array(), $subQuery::JOIN_INNER);
                $subQuery->where(array('b.MINRegisterId' => $MINRegisterId));

                $select = $sql->delete();
                $select->from('MMS_MINAnalTrans')
                    ->where->expression('MINProjTransId IN ?', array($subQuery));
                $DelMINAnalTransStatement = $sql->getSqlStringForSqlObject($select);
                //$DelMINAnalTransregister = $dbAdapter->query($DelMINAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_MINProjTrans*/
                $subQuery = $sql->select();
                $subQuery->from(array("a" => "MMS_MINTrans"))
                    ->columns(array("MINTransId"));
                $subQuery->where(array('a.MINRegisterId' => $MINRegisterId));

                $select = $sql->delete();
                $select->from('MMS_MINProjTrans')
                    ->where->expression('MINTransId IN ?', array($subQuery));
                $DelMINProjTransStatement = $sql->getSqlStringForSqlObject($select);
                //$DelMINProjTransregister = $dbAdapter->query($DelMINProjTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_MINTrans*/
                $select = $sql->delete();
                $select->from('MMS_MINTrans');
                $select->where(array('MINRegisterId' => $MINRegisterId));
                $DelMINPTransStatement = $sql->getSqlStringForSqlObject($select);
                //$DelMINTransregister = $dbAdapter->query($DelMINPTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //Update
                $vNo = CommonHelper::getVoucherNo(5, date('Y/m/d'), 0, 0, $dbAdapter, "");
                $voucher = '';
                if ($vNo['genType']) {
                    $vNo = CommonHelper::getVoucherNo(5, date('Y/m/d'), 0, 0, $dbAdapter, "I");
                    $voucher = $vNo['voucherNo'];
                } else {
                    //$voucher =  $postParam['voucherNo'];
                }

                $registerUpdate = $sql->update();
                $registerUpdate->table('MMS_MINRegister');
                $registerUpdate->set(array('GRNDate' => date('Y-m-d', strtotime($postData["grn_date"])), 'GRNNo' => $voucher,
                    'GRNSiteDate' => date('Y-m-d', strtotime($postData["grnsite_date"])), 'GRNSiteNo' => $postData["grnsite_no"],
                    'Tested' => ($postData["Tested"]) ? $postData["Tested"] : '0', 'Testedby' => $postData["Testedby"],
                    'Testingmethod' => $postData["Testingmethod"], 'Testresult' => $postData["Testresult"]
                ));
                $registerUpdate->where(array('MINRegisterId' => $MINRegisterId));
                $registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                //$registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach ($json as $resource) {
                    $resId = $resource['resId'];
                    //MMS_MINTrans
                    //MINRegisterId,POTransId,ResourceId,UnitId,AcceptQty,RejectQty,CostCentreId
                    $requestInsert = $sql->insert('MMS_MINTrans');
                    $requestInsert->values(array("MINRegisterId" => $MINRegisterId, "POTransId" => $postData['transId_' . $resId], "UnitId" => $postData['unitId_' . $resId],
                        "ResourceId" => $resId, "AcceptQty" => $postData['acceptedQty_' . $resId], "CostCentreId" => $postData['project_name'], "RejectQty" => $postData['rejectedQty_' . $resId]));
                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                    $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    $MINTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    //WareHouse
                    /*foreach($WHValue as $wbs){
                        //MMS_MINWarehouseTrans
                        //MINTransId,WareHouseId,DCQty,CostCentreId
                        $requestInsert = $sql->insert('MMS_MINWarehouseTrans');
                        $requestInsert->values(array("MINTransId"=>$MINTransId, "WareHouseId"=>$MINProjTransId, "DCQty"=>$postData['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs],
                             'CostCentreId'=>$CostCentreId));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }*/

                    foreach ($resource['poId'] as $po) {
                        $poId = $po['PORegisterId'];
                        //MMS_MINProjTrans
                        //MINTransId,CostCentreId,ResourceId,UnitId,AcceptQty,RejectQty
                        $requestInsert = $sql->insert('MMS_MINProjTrans');
                        $requestInsert->values(array("MINTransId" => $MINTransId, "CostCentreId" => $postData['project_name'], "UnitId" => $postData['unitId_' . $resId],
                            "ResourceId" => $resId, "AcceptQty" => $postData['acceptPOQty_' . $resId . '_' . $poId], "RejectQty" => $postData['rejectPOQty_' . $resId . '_' . $poId]));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $MINProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        foreach ($po['wbsId'] as $wbs) {
                            //MMS_MINAnalTrans
                            //MINProjTransId,MINTransId,AnalysisId,ResourceId,UnitId,AcceptQty,RejectQty
                            $requestInsert = $sql->insert('MMS_MINAnalTrans');
                            $requestInsert->values(array("MINTransId" => $MINTransId, "MINProjTransId" => $MINProjTransId, "AnalysisId" => $wbs,
                                "ResourceId" => $resId, "AcceptQty" => $postData['acceptWbs_' . $resId . '_' . $poId . '_' . $wbs],
                                "RejectQty" => $postData['rejectWbs_' . $resId . '_' . $poId . '_' . $wbs], 'UnitId' => $postData['unitId_' . $resId]));
                            $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                            $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                }

                $connection->commit();
            } catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //begin trans try block example ends
        }
        //Common function
        $minId = $this->params()->fromRoute('mid');
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);

        $registerSelect = $sql->select('MMS_MINRegister');
        $registerSelect->columns(array("GRNDate" => new Expression("CONVERT(VARCHAR(10), GRNDate, 105)"), "GRNNo", "GRNSiteDate" => new Expression("CONVERT(VARCHAR(10), GRNSiteDate, 105)"), "GRNSiteNo", "Tested", "Testedby", "Testingmethod", "Testresult"))
            ->where(array("MINRegisterId" => $minId));
        $registerStatement = $sql->getSqlStringForSqlObject($registerSelect);
        $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $projSelect = $sql->select();
        $projSelect->from('WF_OperationalCostCentre')
            ->columns(array("data" => "CostCentreId", "value" => "CostCentreName"));
        $projStatement = $sql->getSqlStringForSqlObject($projSelect);
        $proResults = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Test QUery//

        //Populate PO Picklist
        $select1 = $sql->select();
        $select1->from(array("a" => "MMS_MINTrans"))
            ->columns(array("Sel" => new Expression("1")), array("PORegisterId"), array("PONo", "PODate"))
            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array("PORegisterId"), $select1::JOIN_INNER)
            ->join(array("c" => "MMS_PORegister"), "b.PORegisterId=c.PORegisterId", array("PONo", "PODate"), $select1::JOIN_INNER);
        $select1->where(array('a.MINRegisterId' => '1002'));

        $Subselect2 = $sql->select();
        $Subselect2->from(array("a" => "MMS_MINTrans"))
            ->columns(array("PORegisterId" => new Expression("b.PORegisterId")))
            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $Subselect2::JOIN_INNER)
            ->join(array("c" => "MMS_PORegister"), "b.PORegisterId=c.PORegisterId", array(), $Subselect2::JOIN_INNER)
            ->where(array('a.MINRegisterId' => '1002'));

        $select2 = $sql->select();
        $select2->from(array("a" => "MMS_PORegister"))
            ->columns(array(new Expression("1-1 as Sel,a.PORegisterId,a.PONo,a.PODate")), array(), array(), array(), array())
            ->join(array("b" => "MMS_POTrans"), "a.PORegisterId=b.PORegisterId", array(), $select2::JOIN_INNER)
            ->join(array("c" => "MMS_IPDTrans"), "b.PoTransId=c.POTransId", array(), $select2::JOIN_INNER)
            ->join(array("d" => "MMS_POProjTrans"), "b.PoTransId=d.POTransId", array(), $select2::JOIN_INNER)
            ->join(array("e" => "MMS_IPDProjTrans"), "d.POProjTransId=e.POProjTransId and c.IPDTransId=e.IPDTransId", array(), $select2::JOIN_INNER)
            ->where->notIn('a.PORegisterId', $Subselect2);
        $select2->where(array('e.CostCentreId' => '3'));
        $select2->group(new Expression("a.PONo, a.PODate, a.PORegisterId"));
        $select2->combine($select1, 'Union ALL');
        $select3 = $sql->select();
        $select3->from(array("g" => $select2))
            ->columns(array('PONo', 'PODate', 'PORegisterId', 'Sel'));
        $select3->group(new Expression("g.PONo, g.PODate, g.PORegisterId,g.Sel"));
        $feedStatement = $sql->getSqlStringForSqlObject($select3);

        //Populate Resource Picklist
        $select1 = $sql->select();
        $select1->from(array("a" => "MMS_MINTrans"))
            ->columns(array("ResourceId", "Sel" => new Expression("1")), array("PORegisterId", "Quantity" => new Expression("CAST(b.POQty As Decimal(18,5))")))
            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array("PORegisterId", "Quantity" => new Expression("CAST(b.POQty As Decimal(18,5))")), $select1::JOIN_INNER);
        $select1->where(array('a.MINRegisterId' => '1002',
            'b.PORegisterId' => '1'));

        $Subselect2 = $sql->select();
        $Subselect2->from(array("a" => "MMS_MINTrans"))
            ->columns(array("PoTransId" => new Expression("b.PoTransId")))
            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $Subselect2::JOIN_INNER)
            ->where(array('a.MINRegisterId' => '1002'));

        $select2 = $sql->select();
        $select2->from(array("a" => "MMS_POTrans"))
            ->columns(array("ResourceId", "Sel" => new Expression("1-1"), "PORegisterId", "Quantity" => new Expression("CAST(a.POQty As Decimal(18,5))")))
            ->where->notIn('a.PoTransId', $Subselect2);
        $select2->where(array('a.PORegisterId' => '1'));
        $select2->combine($select1, 'Union ALL');
        $select3 = $sql->select();
        $select3->from(array("g" => $select2))
            ->columns(array('ResourceId', 'Quantity', 'PORegisterId', 'Sel'), array("PONo", "PODate"), array("ResourceName", "Code"), array("UnitName"))
            ->join(array("b1" => "MMS_PORegister"), "g.PORegisterId=b1.PORegisterId", array("PONo", "PODate"), $select3::JOIN_INNER)
            ->join(array("c" => "Proj_Resource"), "g.ResourceId=c.ResourceId", array("ResourceName", "Code"), $select3::JOIN_INNER)
            ->join(array("d" => "Proj_UOM"), "c.UnitId=d.UnitId", array("UnitName"), $select3::JOIN_INNER);
        $feedStatement = $sql->getSqlStringForSqlObject($select3);

        //PO Edit Fill Resource
        $select1 = $sql->select();
        $select1->from(array("a" => "MMS_MINTrans"))
            ->columns(array("POTransId", "ResourceId", "AcceptQty", "RejectQty"), array("Quantity" => new Expression("CAST(b.POQty As Decimal(18,5))")))
            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array("Quantity" => new Expression("CAST(b.POQty As Decimal(18,5))")), $select1::JOIN_INNER);
        $select1->where(array('a.MINRegisterId' => '1002'));

        $Subselect2 = $sql->select();
        $Subselect2->from(array("a" => "MMS_MINTrans"))
            ->columns(array("PoTransId" => new Expression("b.PoTransId")))
            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $Subselect2::JOIN_INNER)
            ->where(array('a.MINRegisterId' => '1002'));

        $select2 = $sql->select();
        $select2->from(array("a" => "MMS_POTrans"))
            ->columns(array("PoTransId", "ResourceId", "AcceptQty" => new Expression("CAST(0 As Decimal(18,5))"), "RejectQty" => new Expression("CAST(0 As Decimal(18,5))"), "Quantity" => new Expression("CAST(SUM(a.POQty) As Decimal(18,5))")))
            ->where->notIn('a.PoTransId', $Subselect2);
        $select2->where(array('a.PORegisterId' => '1',
            'a.ResourceId' => '1003'));
        $select2->combine($select1, 'Union ALL');
        $select2->group(new Expression("a.PoTransId,a.ResourceId"));
        $select3 = $sql->select();
        $select3->from(array("g" => $select2))
            ->columns(array('PoTransId', 'ResourceId', 'AcceptQty', 'RejectQty', 'Quantity', "BalQty" => new Expression("CAST((g.Quantity-g.AcceptQty-g.RejectQty) As Decimal(18,5))")), array("ResourceName", "Code"), array("UnitName", "UnitId"))
            ->join(array("b" => "Proj_Resource"), "g.ResourceId=b.ResourceId", array("ResourceName", "Code"), $select3::JOIN_INNER)
            ->join(array("c" => "Proj_UOM"), "b.UnitId=c.UnitId", array("UnitName", "UnitId"), $select3::JOIN_INNER);
        $feedStatement = $sql->getSqlStringForSqlObject($select3);

        //PO Edit Fill PO
        $select1 = $sql->select();
        $select1->from(array("a" => "MMS_MINProjTrans"))
            ->columns(array("ResourceId", "AcceptQty", "RejectQty"), array(""), array("PORegisterId", "Quantity" => new Expression("CAST(c.POQty As Decimal(18,5))")))
            ->join(array("b" => "MMS_MINTrans"), "a.MINTransId=b.MINTransId", array(), $select1::JOIN_INNER)
            ->join(array("c" => "MMS_POTrans"), "b.POTransId=c.PoTransId", array("PORegisterId", "Quantity" => new Expression("CAST(c.POQty As Decimal(18,5))")), $select1::JOIN_INNER);
        $select1->where(array('b.MINRegisterId' => '1002',
            'c.PORegisterId' => '1',
            'c.ResourceId' => '1003'));
        $Subselect2 = $sql->select();
        $Subselect2->from(array("a" => "MMS_MINTrans"))
            ->columns(array("PoTransId" => new Expression("b.PoTransId")))
            ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $Subselect2::JOIN_INNER)
            ->where(array('a.MINRegisterId' => '1002'));

        $select2 = $sql->select();
        $select2->from(array("a" => "MMS_POTrans"))
            ->columns(array("ResourceId", "AcceptQty" => new Expression("CAST(0 As Decimal(18,5))"), "RejectQty" => new Expression("CAST(0 As Decimal(18,5))"), "PORegisterId", "Quantity" => new Expression("CAST(a.POQty As Decimal(18,5))")))
            ->where->notIn('a.PoTransId', $Subselect2);
        $select2->where(array('a.PORegisterId' => '1',
            'a.ResourceId' => '1003'));
        $select2->combine($select1, 'Union ALL');

        $select3 = $sql->select();
        $select3->from(array("g" => $select2))
            ->columns(array('PORegisterId', 'ResourceId', 'AcceptQty', 'RejectQty', 'Quantity', "BalQty" => new Expression("CAST((g.Quantity-g.AcceptQty-g.RejectQty) As Decimal(18,5))")), array("PONo"))
            ->join(array("b1" => "MMS_PORegister"), "g.PORegisterId=b1.PORegisterId", array("PONo"), $select3::JOIN_INNER)
            ->join(array("c1" => "Proj_Resource"), "g.ResourceId=c1.ResourceId", array(), $select3::JOIN_INNER);
        $feedStatement = $sql->getSqlStringForSqlObject($select3);

        //PO Edit Fill WBS
        $select1 = $sql->select();
        $select1->from(array("a" => "MMS_MINAnalTrans"))
            ->columns(array("IPDAHTransId", "AnalysisId", "AcceptQty", "RejectQty"), array(""), array(""), array("PORegisterId"), array("Quantity" => new Expression("CAST(e.Qty As Decimal(18,5))"), "ResourceId" => new Expression("a.ResourceId")))
            ->join(array("b1" => "MMS_MINProjTrans"), "a.MINProjTransId=b1.MINProjTransId", array(), $select1::JOIN_INNER)
            ->join(array("b" => "MMS_MINTrans"), "a.MINTransId=b.MINTransId", array(), $select1::JOIN_INNER)
            ->join(array("c" => "MMS_POTrans"), "b.POTransId=c.POTransId", array("PORegisterId"), $select1::JOIN_INNER)
            ->join(array("e" => "MMS_IPDAnalTrans"), "a.IPDAHTransId=e.IPDAHTransId", array("Quantity" => new Expression("CAST(e.Qty As Decimal(18,5))"), "ResourceId" => new Expression("a.ResourceId")), $select1::JOIN_INNER);
        $select1->where(array('b.MINRegisterId' => '1002'));

        $Subselect2 = $sql->select();
        $Subselect2->from(array("a" => "MMS_MINAnalTrans"))
            ->columns(array("IPDAHTransId" => new Expression("a.IPDAHTransId")))
            ->join(array("a1" => "MMS_MINTrans"), "a.MINTransId=a1.MINTransId", array(), $Subselect2::JOIN_INNER)
            ->join(array("b" => "MMS_POTrans"), "a1.POTransId=b.POTransId", array(), $Subselect2::JOIN_INNER)
            ->where(array('a1.MINRegisterId' => '1002'));
        $select2 = $sql->select();
        $select2->from(array("a1" => "MMS_IPDAnalTrans"))
            ->columns(array("IPDAHTransId", "AnalysisId", "AcceptQty" => new Expression("CAST(0 As Decimal(18,5))"), "RejectQty" => new Expression("CAST(0 As Decimal(18,5))"),
                "PORegisterId" => new Expression("a4.PORegisterId"), "Quantity" => new Expression("CAST(a1.Qty As Decimal(18,5))"), "ResourceId" => new Expression("a4.ResourceId")))
            ->join(array("a2" => "MMS_POAnalTrans"), "a1.POAHTransId=a2.POAnalTransId", array(), $select2::JOIN_INNER)
            ->join(array("b1" => "MMS_IPDProjTrans"), "a2.POProjTransId=b1.POProjTransId", array(), $select2::JOIN_INNER)
            ->join(array("a3" => "MMS_POProjTrans"), "a2.POProjTransId=a3.POProjTransId", array(), $select2::JOIN_INNER)
            ->join(array("a5" => "MMS_IPDTrans"), "a3.POTransId=a5.POTransId", array(), $select2::JOIN_INNER)
            ->join(array("a4" => "MMS_POTrans"), "a3.POTransId=a4.PoTransId", array(), $select2::JOIN_INNER)
            ->where->notIn('a1.IPDAHTransId', $Subselect2);
        $select2->where(array('a4.PORegisterId' => '1',
            'a4.ResourceId' => '1003'));
        $select2->combine($select1, 'Union ALL');

        $select3 = $sql->select();
        $select3->from(array("g" => $select2))
            ->columns(array('IPDAHTransId', 'AnalysisId', 'ResourceId', 'AcceptQty', 'RejectQty', 'Quantity', "BalQty" => new Expression("CAST((g.Quantity-g.AcceptQty-g.RejectQty) As Decimal(18,5))")), array("WBSName"))
            ->join(array("d" => "Proj_WBSMaster"), "g.AnalysisId=d.WBSId", array("WBSName"), $select3::JOIN_INNER);
        $feedStatement = $sql->getSqlStringForSqlObject($select3);

        //End Test Query//

        $vNo = CommonHelper::getVoucherNo(5, date('Y/m/d'), 0, 0, $dbAdapter, "");
        $this->_view->vNo = $vNo;
        $this->_view->proResults = $proResults;
        $this->_view->registerResults = $registerResults;
        return $this->_view;
    }

    public function registerAction() {

        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Min", "action" => "minWizard"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        /*Ajax Request*/
        if ($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {
                $postParam = $request->getPost();
                if ($postParam['mode'] == 'first') {
                    $regSelect = $sql->select();
                    $regSelect->from(array("a" => "MMS_DCRegister"))
                        ->columns(array(new Expression("a.DCRegisterId,Convert(Varchar(10),a.DCDate,103) As MINDate,
                           c.CostCentreName AS CostCentre,a.SiteDCNo As SiteMINNo,Convert(Varchar(10),a.SiteDCDate,103) As SiteMINDate,
                           a.CCDCNo As CCMinNo,a.CDCNo AS CMinNo,a.DCNo As MINNo,
                           Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("b" => "Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $regSelect::JOIN_LEFT)
                        ->join(array("c" => "WF_operationalcostcentre"), "a.CostCentreId=c.CostCentreId", array(), $regSelect::JOIN_LEFT)
                        ->where(array("a.DeleteFlag"=>0))
                        ->order("a.CreatedDate Desc");
                    $regStatement = $sql->getSqlStringForSqlObject($regSelect);
                    $resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else if ($request->isPost()) {

        }
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;
    }

    public function detailedAction() {

        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Min", "action" => "register"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $dcId = $this->params()->fromRoute('rid');

        /*Ajax Request*/
        if ($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {
                $postParams = $request->getPost();

//                if ($postParams['mode'] == 'final') {
//                    $trans = $sql->select();
//                    $trans->from(array("a" => "MMS_DCGroupTrans"))
//                        ->columns(array(new Expression("a.DCRegisterId,a.DCGroupId,a.ResourceId,a.ItemId,
//                        Case When a.ItemId>0 Then d.ItemCode Else b.Code End As Code,
//                        Case When a.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,c.UnitName,
//                        CAST(a.DCQty As Decimal(18,5)) As Qty, CAST(a.AcceptQty As Decimal(18,5)) As AcceptQty,
//                        CAST(a.RejectQty As Decimal(18,5)) As RejectQty")))
//                        ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array(), $trans::JOIN_INNER)
//                        ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $trans::JOIN_LEFT)
//                        ->join(array("d" => "MMS_Brand"), "a.ResourceId=d.ResourceId AND a.ItemId=d.BrandId", array(), $trans::JOIN_LEFT)
//                        ->where(array("a.DCRegisterId"=>$postParams['DCRegisterId']));
//                   $transStatement = $sql->getSqlStringForSqlObject($trans);
//                    $resp['resource'] = $dbAdapter->query($transStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                    $resp['decision'] = array();
//                    $resp['wbs'] = array();
//
//                    foreach ($resp['resource'] as $trans) {
//                        $decisionSelect = $sql->select();
//                        $decisionSelect->from(array("a" => "MMS_DCTrans"))
//                            ->columns(array(new Expression("a.DCTransId,b.DCGroupId,e.PONo,Convert(Varchar(10),e.PODate,103) As PODate,Convert(Varchar(10),e.ReqDate,103) As ReqDate,CAST(d.POQty As Decimal(18,5)) As OrderQty,
//                              CAST(d.AcceptQty As Decimal(18,5)) As MINQty, CAST(d.BalQty As Decimal(18,5)) As BalQty, CAST(a.DCQty As Decimal(18,5)) As SupplierMIN, CAST(a.AcceptQty As Decimal(18,5)) As AcceptQty, CAST(a.RejectQty As Decimal(18,5)) As RejectQty")))
//                            ->join(array("b" => "MMS_DCGroupTrans"), "a.DCGroupId=b.DCGroupId And a.DCRegisterId=b.DCRegisterId", array(), $decisionSelect::JOIN_INNER)
//                            ->join(array("c" => "MMS_DCRegister"), "b.DCRegisterId=c.DCRegisterId", array(), $decisionSelect::JOIN_INNER)
//                            ->join(array("d" => "MMS_POTrans"), "a.POTransId=D.PoTransId", array(), $decisionSelect::JOIN_INNER)
//                            ->JOIN(ARRAY("e" => "MMS_PORegister"), "d.PORegisterId=e.PORegisterId", array(), $decisionSelect::JOIN_INNER)
//                            ->where(array('b.DCGroupId' => $trans['DCGroupId'], "c.DCRegisterId"=>$postParams['DCRegisterId']));
//                        $decisionStatement = $sql->getSqlStringForSqlObject($decisionSelect);
//                        $decisionResult = $dbAdapter->query($decisionStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//
//                        foreach ($decisionResult as $anal) {
//                            $analSelect = $sql->select();
//                            $analSelect->from(array("a" => "MMS_DCAnalTrans"))
//                                ->columns(array(new Expression("a.DCTransId,a.AnalysisId,d.WBSName,CAST(c.POQty As Decimal(18,5)) As OrderQty,CAST(c.BalQty As Decimal(18,5)) As BalQty,CAST(c.AcceptQty As Decimal(18,5)) As MINQty,
//                                  CAST(a.DCQty As Decimal(18,5)) As SupplierMIN,CAST(a.AcceptQty As Decimal(18,5)) As AcceptQty,CAST(a.RejectQty As Decimal(18,5)) As RejectQty")))
//                                ->join(array("b" => "MMS_IPDAnalTrans"), "a.DCAnalTransId=b.DCAHTransId", array(), $analSelect::JOIN_INNER)
//                                ->join(array("c" => "MMS_POAnalTrans"), "b.POAHTransId=c.POAnalTransId", array(), $analSelect::JOIN_INNER)
//                                ->join(array("d" => "Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array(), $analSelect::JOIN_INNER)
//                                ->where(array("a.DCTransId" => $anal['DCTransId'], "b.Status='D'"));
//                            $analStatement = $sql->getSqlStringForSqlObject($analSelect);
//                            $analResult = $dbAdapter->query($analStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                            foreach ($analResult as $wbs) {
//                                array_push($resp['wbs'], $wbs);
//                            }
//                        }
//                        array_push($resp['decision'], $anal);
//                    }
//                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else if ($request->isPost()) {

        }

        // cost center details
        $select = $sql->select();
        $select->from(array('a' => 'WF_OperationalCostCentre'))
            ->columns(array('CostCentreId', 'CostCentreName'))
            ->join(array('b' => 'MMS_DCRegister'), 'a.CostCentreId=b.CostCentreId', array(), $select::JOIN_INNER)
            ->where("a.Deactivate=0 AND b.DCRegisterId=$dcId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        // vendor details
        $select = $sql->select();
        $select->from(array('a' => 'Vendor_Master'))
            ->columns(array('VendorId', 'VendorName', 'LogoPath'))
            ->join(array('b' => 'MMS_DCRegister'), 'a.VendorId=b.VendorId')
            ->where("b.DCRegisterId=$dcId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view-> vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $selDCReg = $sql->select();
        $selDCReg->from(array('a' => 'MMS_DCRegister'))
            ->columns(array(new Expression("a.CostCentreId,a.VendorId,a.RefNo,a.DCORCSM,b.CostCentreName,
                        c.VendorName,CONVERT(varchar(10),a.DCDate,105) As DCDate,CONVERT(varchar(10),a.SiteDCDate,105) As SiteDCDate,
                        a.SiteDCNo,a.DCNo,a.CCDCNo,a.CDCNo As CDCNo,a.Narration,a.GatePassNo,a.WithLoad,a.WithOutLoad,a.MaterialWeigh,
                         Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve,
                         a.IsTested,a.AgencyId,a.TestingMethod,a.TestResults,a.GridType")))
            ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $selDCReg::JOIN_INNER)
            ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $selDCReg::JOIN_INNER)
            ->where(array("a.DCRegisterId" => $dcId));
        $statement = $sql->getSqlStringForSqlObject($selDCReg);
        $this->_view->dcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $CostCentre = $this->_view->dcregister['CostCentreName'];
        $Type = $this->_view->dcregister['DCORCSM'];
        $CostCentreId = $this->_view->dcregister['CostCentreId'];
        $VendorId = $this->_view->dcregister['VendorId'];
        $SiteDCNo = $this->_view->dcregister['SiteDCNo'];
        $SiteDCDate = $this->_view->dcregister['SiteDCDate'];
        $CCDCNo = $this->_view->dcregister['CCDCNo'];
        $RefNo = $this->_view->dcregister['RefNo'];
        $CDCNo = $this->_view->dcregister['CDCNo'];
        $vNo = $this->_view->dcregister['DCNo'];
        $DCdate = $this->_view->dcregister['DCDate'];
        $Narration = $this->_view->dcregister['Narration'];
        $GatePassNo = $this->_view->dcregister['GatePassNo'];
        $WithLoad = $this->_view->dcregister['WithLoad'];
        $approve = $this->_view->dcregister['Approve'];
        $WithOutLoad = $this->_view->dcregister['WithOutLoad'];
        $MaterialWeigh = $this->_view->dcregister['MaterialWeigh'];
        $Tested = $this->_view->dcregister['IsTested'];
        $AgencyId = $this->_view->dcregister['AgencyId'];
        $TestingMethod = $this->_view->dcregister['TestingMethod'];
        $TestResults = $this->_view->dcregister['TestResults'];
        $gridtype = $this->_view->dcregister['GridType'];


        $this->_view->DCdate = $DCdate;
        $this->_view->vNo = $vNo;
        $this->_view->dcId = $dcId;
        $this->_view->SiteDCNo = $SiteDCNo;
        $this->_view->CCDCNo = $CCDCNo;
        $this->_view->RefNo = $RefNo;
        $this->_view->SiteDCDate = $SiteDCDate;
        $this->_view->CDCNo = $CDCNo;
        $this->_view->CostCentre = $CostCentreId;
        $this->_view->VendorId = $VendorId;
        $this->_view->Narration = $Narration;
        $this->_view->GatePassNo = $GatePassNo;
        $this->_view->WithLoad = $WithLoad;
        $this->_view->approve = $approve;
        $this->_view->WithOutLoad = $WithOutLoad;
        $this->_view->MaterialWeigh = $MaterialWeigh;
        $this->_view->Tested = $Tested;
        $this->_view->AgencyId = $AgencyId;
        $this->_view->TestingMethod = $TestingMethod;
        $this->_view->TestResults = $TestResults;
        $this->_view->gridtype = $gridtype;


        //getting POTransId from dctrans

        $selectPo = $sql->select();
        $selectPo->from(array('a' => 'MMS_DCTrans'))
            ->columns(array('PoTransId'))
            ->where("a.DCRegisterId=$dcId");
        $statement = $sql->getSqlStringForSqlObject($selectPo);
        $selectPOTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $poTransid = array();
        foreach ($selectPOTrans as $potrans) {
            array_push($poTransid, $potrans['PoTransId']);
        }
        $poTransIDS = implode(",", $poTransid);

        if (!$poTransIDS == '') {
            $this->_view->poTransId = $poTransIDS;

            $select = $sql->select();
            $select->from(array("a" => "MMS_POTrans"))
                ->columns(array(new Expression("Distinct(a.ResourceId),(a.ItemId),
                            case when a.ItemId>0 then c.ItemCode+ '' +c.BrandName Else b.Code+' - '+b.ResourceName End as [Desc],
                            d.UnitName As UnitName,a.UnitId, CAST(f.DCQty As Decimal(18,3)) As Qty,
                            CAST(f.AcceptQty As Decimal(18,3)) As AcceptQty,
                            CAST(f.RejectQty As Decimal(18,3)) As RejectQty,
                            f.Remarks,CONVERT(varchar(10),f.ExpiryDate,105) As ExpiryDate")))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $select::JOIN_LEFT)
                ->join(array('e' => 'MMS_DCTrans'), 'a.PoTransId=e.POTransId', array(), $select::JOIN_INNER)
                ->join(array('f' => 'MMS_DCGroupTrans'), 'e.DCGroupId=f.DCGroupId', array(), $select::JOIN_INNER)
                ->where('a.POTransId IN (' . $poTransIDS . ') AND f.DCRegisterId=' . $dcId . '');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //edit mode- po display
            $select = $sql->select();
            $select->from(array('a' => 'MMS_DCTrans'))
                ->columns(array(new Expression("d.POProjTransId,e.PORegisterId,c.POTransId,c.ResourceId,c.ItemId,e.PONo,
                                   CONVERT(Varchar(10),e.PODate,103) As PODate,
                                   CONVERT(Varchar(10),e.ReqDate,103) As ReqDate,CAST(c.POQty As Decimal(18,3)) As OrderQty,CAST(c.AcceptQty As Decimal(18,3)) As MINQty,
                                   CAST(c.BalQty As Decimal(18,3)) As BalQty,CAST(a.DCQty As Decimal(18,3)) As SupplierMIN,CAST(a.AcceptQty As Decimal(18,3)) As AcceptQty,
                                   CAST(a.RejectQty As Decimal(18,3)) As RejectQty,CAST(a.AcceptQty As Decimal(18,3)) As HiddenAQty,CAST(a.DCQty As Decimal(18,3)) As HiddenSMQty")))
                ->join(array('b' => 'MMS_DCRegister'), 'a.DCRegisterId=b.DCRegisterId', array(), $select::JOIN_INNER)
                ->join(array('c' => 'MMS_POTrans'), 'a.POTransId=c.PoTransId', array(), $select::JOIN_INNER)
                ->join(array('d' => 'MMS_POProjTrans'), 'c.PoTransId=d.POTransId', array(), $select::JOIN_INNER)
                ->join(array('e' => 'MMS_PORegister'), 'c.PORegisterId=e.PORegisterId ', array(), $select::JOIN_INNER)
                ->Where('a.DCRegisterId=' . $dcId . ' And b.CostCentreId=' . $CostCentreId . ' And c.POTransId IN (' . $poTransIDS . ')');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //edit-mode-wbs display
            $select1 = $sql->select();
            $select1->from(array('a' => 'MMS_IPDAnalTrans'))
                ->columns(array(new Expression("f.PORegisterId,f.PoTransId,d.POAnalTransId,f.ResourceId,f.ItemId,d.AnalysisId As WBSId,
                                 g.ParentText+'->'+g.WBSName As WBSName,CAST(d.POQty As Decimal(18,5)) As OrderQty,CAST(d.AcceptQty As Decimal(18,5)) As MINQty,
                                 CAST(d.BalQty As Decimal(18,5)) As BalQty,CAST(b.DCQty As Decimal(18,5)) As SupplierMIN,CAST(b.AcceptQty As Decimal(18,5)) As AcceptQty,
                                 CAST(b.RejectQty As Decimal(18,5)) As RejectQty,CAST(b.AcceptQty As Decimal(18,5)) As HAcceptQty,CAST(b.DCQty As Decimal(18,5)) As HSMqty")))
                ->join(array('b' => 'MMS_DCAnalTrans'), 'a.DCAHTransId=b.DCAnalTransId', array(), $select1::JOIN_INNER)
                ->join(array('c' => 'MMS_DCTrans'), 'b.DCTransId=c.DCTransId', array(), $select1::JOIN_INNER)
                ->join(array('d' => 'MMS_POAnalTrans'), 'a.POAHTransId=d.POAnalTransId  And b.AnalysisId=d.AnalysisId', array(), $select1::JOIN_INNER)
                ->join(array('e' => 'MMS_POProjTrans'), 'd.POProjTransId=e.POProjTransId', array(), $select1::JOIN_INNER)
                ->join(array('f' => 'MMS_POTrans'), 'e.POTransId=f.PoTransId And c.POTransId=f.PoTransId', array(), $select1::JOIN_INNER)
                ->join(array('g' => 'Proj_WBSMaster'), 'd.AnalysisId=g.WBSId', array(), $select1::JOIN_INNER)
                ->join(array('h' => 'WF_OperationalCostCentre'),'g.ProjectId=h.ProjectId',array(),$select1::JOIN_INNER)
                ->Where('c.DCRegisterId=' . $dcId . ' And h.CostCentreId=' . $CostCentreId . ' And f.POTransId IN (' . $poTransIDS . ')');
            $statement = $sql->getSqlStringForSqlObject($select1);
            $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //WAREHOUSE
            $select = $sql ->select();
            $select->from(array("a" => "MMS_WareHouse"))
                ->columns(array(new Expression("b.CostCentreId,c.TransId As WareHouseId,a.WareHouseName,c.Description,CAST(0 As Decimal(18,2)) Qty,CAST(0 As Decimal(18,2)) HiddenQty")))
                ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                ->where(array("b.CostCentreId=  $CostCentreId And c.LastLevel=1"));
            $selectStatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql ->select();
            $select->from(array("a" => "MMS_DCWareHousePoTrans"))
                ->columns(array(new Expression("c.CostCentreId,e.ResourceId,e.ItemId,
                            b.TransId as WareHouseId,a.POTransId as POTransId,
                            d.WareHouseName,b.Description,CAST(a.Qty As Decimal(18,2)) Qty,
                            CAST(a.Qty As Decimal(18,2)) HiddenQty")))
                ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
                ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.WareHouseId", array(), $select::JOIN_INNER)
                ->join(array("e" => "MMS_DCGroupTrans"), "a.DCGroupId=e.DCGroupId", array(), $select::JOIN_INNER)
                ->where(array("c.CostCentreId"=> $CostCentreId ,"b.LastLevel"=>1, "e.DCRegisterId" =>  $dcId ));
            $selectStatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_dcpowarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $select = $sql ->select();
            $select->from(array("a" => "MMS_DCWareHouseWbsTrans"))
                ->columns(array(new Expression("c.CostCentreId,e.ResourceId,e.ItemId,
                            b.TransId as WareHouseId,a.POAnalTransId as POAnalTransId,
                            d.WareHouseName,b.Description,CAST(a.Qty As Decimal(18,2)) Qty,
                            CAST(a.Qty As Decimal(18,2)) HiddenQty")))
                ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
                ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.WareHouseId", array(), $select::JOIN_INNER)
                ->join(array("e" => "MMS_DCGroupTrans"), "a.DCGroupId=e.DCGroupId", array(), $select::JOIN_INNER)
                ->where(array("c.CostCentreId"=> $CostCentreId ,"b.LastLevel"=>1, "e.DCRegisterId" =>  $dcId ));
            $selectStatement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_dcwbswarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        //GATEPASS
        $select = $sql ->select();
        $select->from(array("a" => "MMS_GatePass "))
            ->columns(array("GateRegId","GatePassNo"))
            ->where(array("a.CostCentreId= $CostCentreId And a.SupplierId= $VendorId "));
        $selectStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_gatepass = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        // AGENCY DETAILS
        $select = $sql->select();
        $select-> from(array("a"=> "Vendor_Master"))
            ->columns(array("VendorId","VendorName"))
            ->where(array("a.service=1"));
        $selectStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_agency = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //VEHICLE MEASUREMENT
        $select = $sql ->select();
        $select->from(array("a" => "Vendor_VehicleMaster"))
            ->columns(array("VehicleId","VehicleRegNo","VehicleName"))
            ->where(array("a.VendorId= $VendorId "));
        $selectStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_vehicle = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql ->select();
        $select->from(array("a" => "MMS_DCTripSheet"))
            ->columns(array("*"))
            ->join(array('b' => 'Vendor_VehicleMaster'),'a.VehicleId=b.VehicleId',array('VehicleName'),$select::JOIN_LEFT)
            ->where(array("a.DCRegisterId = $dcId AND a.VendorId = $VendorId "));
        $selectStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->vehicleData = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();



        $regData = $sql->select();
        $regData->from(array("a" => "MMS_DCRegister"))
            ->columns(array(new Expression("a.DCNo, Case When a.Approve = 'P' Then 'Partial' when a.Approve = 'Y' then 'Yes' Else 'No' End As Approve,Convert(Varchar(10),a.DCDate,103) As DCDate,a.RefNo,Convert(Varchar(10),a.SiteDCDate,103) As SiteDCDate,b.CostCentreName,c.VendorName")))
            ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $regData::JOIN_INNER)
            ->join(array("c" => "Vendor_Master"), "a.VendorId=c.VendorId", array(), $regData::JOIN_INNER)
            ->where(array('a.DCRegisterId' => $dcId));
        $regDataStatement = $sql->getSqlStringForSqlObject($regData);
        $regDataResult = $dbAdapter->query($regDataStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $this->_view->CostCentreName = $regDataResult['CostCentreName'];
        $this->_view->VendorName = $regDataResult['VendorName'];
        $this->_view->DCNo = $regDataResult['DCNo'];
        $this->_view->DCDate = $regDataResult['DCDate'];
        $this->_view->RefNo = $regDataResult['RefNo'];
        $this->_view->SiteDCDate = $regDataResult['SiteDCDate'];
        $this->_view->Approve = $regDataResult['Approve'];
        $this->_view->DCRegisterId = $dcId;

        //Common function
        $this->_view->DCRegisterId = $this->params()->fromRoute('rid');
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;

    }

    public function minWizardAction() {
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here

                $postParams = $request->getPost();
                $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentreId'], 'number');
                $VendorId = $this->bsf->isNullCheck($postParams['VendorId'], 'number');

                $select = $sql->select();
                $select->from(array("a" => "MMS_PORegister"))
                    ->columns(array(new Expression("Distinct(a.PORegisterId),Convert(Varchar(10),a.PODate,103) As PODate,
                    a.PONo,d.CostCentreName,a.VendorId")))
                    ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'MMS_POProjTrans'), 'b.POTransId=c.POTransId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'WF_OperationalCostCentre'), 'c.CostCentreId=d.CostCentreId', array(), $select::JOIN_INNER)
                    ->where("c.CostCentreId=$CostCentreId And b.BalQty>0 And a.Approve='Y' And a.VendorId=$VendorId")
                    ->order("a.PORegisterId Desc");
                $statement = $sql->getSqlStringForSqlObject($select);
                $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                $select = $sql->select();
                $select->from(array("a" => "MMS_PORegister"))
                    ->columns(array(new Expression("Distinct(b.PoTransId),(b.PORegisterId),
                    CAST(b.POQty as Decimal(18,3)) as Quantity,CAST(b.BalQty As Decimal(18,3)) As BalQty,a.PODate as PODate,
                    a.PONo as PONo,Case When b.ItemId>0 Then e.BrandName Else d.ResourceName End As [Desc],
                    CONVERT(bit,0,0) As Include")))
                    ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId', array(), $select::JOIN_INNER)
                    ->join(array('C' => 'MMS_POProjTrans'), 'b.PoTransId=c.POTransId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'Proj_Resource'), 'b.ResourceId=d.ResourceId', array(), $select::JOIN_INNER)
                    ->join(array('e' => 'MMS_Brand'), 'b.ItemId=e.BrandId and b.ResourceId=e.ResourceId', array(), $select::JOIN_LEFT)
                    ->where("c.CostCentreId=$CostCentreId and b.BalQty>0 and a.Approve='Y' And a.VendorId=$VendorId ");
                $statement = $sql->getSqlStringForSqlObject($select);
                $resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('requests' => $requests, 'resources' => $resources)));
                return $response;
            }
        } else {
            $request = $this->getRequest();

            if ($request->isPost()) {
                //Write your Normal form post code here
            }
            // getting the  cost centres
            $select = $sql->select();
            $select->from(array('a' => 'WF_OperationalCostCentre'))
                ->columns(array('CostCentreId', 'CostCentreName'))
                ->where('Deactivate=0');
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // vendors(contract)
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                ->where(array('Supply' => '1'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_contract_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }


    public function minentryAction(){

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $dcid = $this->bsf->isNullCheck($this->params()->fromRoute('dcId'), 'number');
        $flag = $this->bsf->isNullCheck($this->params()->fromRoute('flag'), 'number');

        if (!$this->getRequest()->isXmlHttpRequest() && $dcid == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('mms/default', array('controller' => 'min', 'action' => 'minWizard'));

        }
        //Ajax Request
        if($this->getRequest()->isXmlHttpRequest())	{
            // AJAX request
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();

                $CostCentre = $this->bsf->isNullCheck($postData['CostCenterId'], 'number');
                $resourceid = $this->bsf->isNullCheck($postData['resourceid'], 'number');
                $VendorId = $this->bsf->isNullCheck($postData['VendorId'], 'number');
                $itemid = $this->bsf->isNullCheck($postData['itemid'], 'number');
                $WBSId = $this->bsf->isNullCheck($postData['WBSId'], 'number');
                $vehicleId = $this->bsf->isNullCheck($postData['vehicleId'], 'number');

                $select = $sql->select();
                $select->from(array("a" => "MMS_PORegister"))
                    ->columns(array(new Expression("c.POProjTransId,a.PORegisterId,b.POTransId,b.ResourceId,
                    b.ItemId,a.PONo,
                    Convert(Varchar(10),a.PODate,103) As PODate,
                    Convert(Varchar(10),a.ReqDate,103) As ReqDate,
                    CAST(b.POQty As Decimal(18,5)) As OrderQty,
                    CAST(b.AcceptQty As Decimal(18,5)) As MINQty,
                    CAST(b.BalQty As Decimal(18,5)) As BalQty,
                    CAST(0 As Decimal(18,5)) As SupplierMIN,
                    CAST(0 As Decimal(18,5)) As AcceptQty,
                    CAST(0 As Decimal(18,5)) As RejectQty ")))
                    ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId ', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'MMS_POProjTrans'), 'b.POTransId=c.POTransId', array(), $select::JOIN_INNER)
                    ->where('c.CostCentreId =' . $CostCentre .' and
                             b.ResourceId =' .$resourceid. 'and
                             b.ItemId =' .$itemid. ' and
                             a.VendorId =' .$VendorId. 'and
                              b.BalQty>0');
                $statement = $sql->getSqlStringForSqlObject($select);
                $resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $select = $sql->select();
                $select->from(array("a" => "MMS_PORegister"))
                    ->columns(array(new Expression("b.PORegisterId,b.POTransId,d.POAnalTransId,b.ResourceId,b.ItemId,
                    D.AnalysisId as WBSId,e.ParentText+'->'+e.WBSName,
                    CAST(d.POQty As Decimal(18,5)) As OrderQty,CAST(d.AcceptQty As Decimal(18,5)) As MINQty,CAST(D.BalQty As Decimal(18,5)) As BalQty,
                    CAST(0 As Decimal(18,5)) As SupplierMIN,CAST(0 As Decimal(18,5)) As AcceptQty,CAST(0 As Decimal(18,5)) As RejectQty")))
                    ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'MMS_POProjTrans'), 'b.PoTransId=c.POTransId', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_POAnalTrans'), 'c.POProjTransId=d.POProjTransId', array(), $select::JOIN_INNER)
                    ->join(array('e' => 'Proj_WBSMaster'), 'd.AnalysisId=e.WBSId')
                    ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                    ->where('f.CostCentreId = ' . $CostCentre . '  and
                            b.ResourceId IN(' .$resourceid. ') and
                            b.ItemId IN(' .$itemid. ') and
                            a.VendorId IN(' .$VendorId. ') and
                            d.BalQty>0');
                $statement = $sql->getSqlStringForSqlObject($select);
                $iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if($vehicleId != '' || 0){
                    $select = $sql ->select();
                    $select->from(array("a" => "Vendor_VehicleMaster"))
                        ->columns(array("*"))
                        ->where(array("a.VehicleId= $vehicleId "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $arr_vehicleId = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                }
                else{
                    $arr_vehicleId = [];
                }

                if($postData['mode'] == 'spo'){

                    //POSTOCK DETAILS
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectResource"))
                        ->columns(array('EstimateQty' => new Expression('a.Qty'),'TotMinQty' => new Expression("CAST(0 As Decimal(18,3))"), 'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$select::JOIN_INNER)
                        ->Where (' b.CostCentreId=' . $CostCentre .' And ResourceId=' .$resourceid. ' ');

                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_DCTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotMinQty' => new Expression("CAST(ISNULL(SUM(a.AcceptQty),0) As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_DCRegister'), 'a.DCRegisterId=b.DCRegisterId ', array(), $sel1::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentre .' And a.ResourceId=' .$resourceid. ' And b.General=0');
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql->select();
                    $sel2->from(array("a"=> "MMS_PVTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotMinQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_PVRegister'), new Expression("a.PVRegisterId=b.PVRegisterId and b.ThruPO='Y'"), array(), $sel2::JOIN_LEFT)
                        ->Where ('a.ResourceId=' .$resourceid. 'And b.CostCentreId=' . $CostCentre .' And b.General=0');
                    $sel2->combine($sel1,'Union ALL');
                    $select = $sql->select();
                    $select-> from(array("g" => $sel2 ))
                        ->columns(array('EstimateQty' => new Expression('Cast(SUM(g.EstimateQty) As Decimal(18,3))'),
                            'TotMinQty' => new Expression('Cast(SUM(g.TotMinQty) As Decimal(18,3))'),
                            'TotBillQty' => new Expression('Cast(SUM(g.TotBillQty) As Decimal(18,3))'),
                            'AvailableQty' => new Expression("Cast(Case When (SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty)))>=0 Then (SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty))) Else 0 End As Decimal(18,3))"),
                            'ExcessQty' => new Expression("Cast(Case When (SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty))) < 0 Then ABS((SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty)))) Else 0 End As Decimal(18,3))")
                        ));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $arr_stock = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }
                else{
                    $arr_stock =[];
                }
                if($postData['mode'] == 'swbs'){

                    //WBSSTOCK DETAILS
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectWBSResource"))
                        ->columns(array('EstimateQty' => new Expression('a.Qty'),'TotMinQty' => new Expression("CAST(0 As decimal(18,3))"), 'TotBillQty' => new Expression("CAST(0 As decimal(18,3))") ))
                        ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('WBSId = ' . $WBSId .' and b.CostCentreId=' . $CostCentre .' And ResourceId=' .$resourceid. ' ');

                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_DCTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotMinQty' => new Expression("CAST(ISNULL(SUM(c.AcceptQty),0) As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_DCRegister'), 'a.DCRegisterId=b.DCRegisterId ', array(), $sel1::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCAnalTrans'), 'c.DCTransId=a.DCTransId ', array(), $sel1::JOIN_INNER)
                        ->Where ('c.AnalysisId= ' . $WBSId .' and b.CostCentreId=' . $CostCentre .' And a.ResourceId=' .$resourceid. ' And b.General=0');
                    $sel1->combine($sel,'Union ALL');

                    $sel2 = $sql->select();
                    $sel2->from(array("a"=> "MMS_PVTrans" ))
                        ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'TotMinQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(ISNULL(SUM(c.BillQty),0) As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_PVRegister'), new Expression("a.PVRegisterId=b.PVRegisterId and b.ThruPO='Y'"), array(), $sel2::JOIN_LEFT)
                        ->join(array('c' => 'MMS_PVAnalTrans'),"c.PVTransId=c.PVTransId", array(), $sel2::JOIN_LEFT)
                        ->Where ('c.AnalysisId = ' . $WBSId .' and a.ResourceId=' .$resourceid. 'And b.CostCentreId=' . $CostCentre .' And b.General=0');
                    $sel2->combine($sel1,'Union ALL');

                    $select = $sql->select();
                    $select-> from(array("g" => $sel2 ))
                        ->columns(array('EstimateQty' => new Expression('Cast(SUM(g.EstimateQty) As Decimal(18,3))'),
                            'TotMinQty' => new Expression('Cast(SUM(g.TotMinQty) As Decimal(18,3))'),
                            'TotBillQty' => new Expression('Cast(SUM(g.TotBillQty) As Decimal(18,3))'),
                            'AvailableQty' => new Expression("Cast(Case When (SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty)))>=0 Then (SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty))) Else 0 End As Decimal(18,3))"),
                            'ExcessQty' => new Expression("Cast(Case When (SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty))) < 0 Then ABS((SUM(g.EstimateQty) - (SUM(TotMinQty)+SUM(TotBillQty)))) Else 0 End As Decimal(18,3))" )
                        ));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $wbs_stock = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }else{
                    $wbs_stock =[];
                }

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('po' => $resource_iows, 'wbs' => $iow_requests, 'poStock' => $arr_stock,  'wbsStock' => $wbs_stock, 'vehicleid' => $arr_vehicleId )));
                return $response;
            }

        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

                //add mode
                $postData = $request->getPost();
//                echo"<pre>";
//             print_r($postData);
//            echo"</pre>";
//             die;
//            return;

                if (!is_null($postData['frm_index'])) {

                    $CostCentre = $this->bsf->isNullCheck($postData['CostCentre'], 'number');
                    $Supplier = $this->bsf->isNullCheck($postData['Supplier'], 'number');
                    $Type = $this->bsf->isNullCheck($postData['Type'], 'string');
                    $DCDate = $this->bsf->isNullCheck($postData['DCdate'], 'string');
                    $gridtype=$this->bsf->isNullCheck($postData['gridtype'], 'number');
                    $DCdate = date('Y-m-d', strtotime($DCDate));
                    $poTransIds = implode(',', $postData['poTransIds']);
                    $isWareHouse=0;

                    if($flag == 1){
                        $select = $sql->select();
                        $select->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("b.CostCentreId as CostCentreId,b.VendorId as VendorId")))
                            ->join(array("b" => "MMS_PORegister"), 'a.PORegisterId=b.PORegisterId', array(), $select::JOIN_INNER)
                            ->where('a.PoTransId IN(' .$poTransIds. ')');
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $cvName = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $CostCentre = $this->bsf->isNullCheck($cvName['CostCentreId'],'number');
                        $Supplier = $this->bsf->isNullCheck($cvName['VendorId'],'number');
                    }

                    //Get CompanyId
                    $getCompany = $sql -> select();
                    $getCompany->from("WF_OperationalCostCentre")
                        ->columns(array("CompanyId","CostCentreName"));
                    $getCompany->where(array('CostCentreId'=>$CostCentre));
                    $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                    $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');
                    $CCName = $this->bsf->isNullCheck($comName['CostCentreName'],'string');

                    //Get Vendor Name
                    $getVendor = $sql -> select();
                    $getVendor->from("Vendor_Master")
                        ->columns(array("VendorName"));
                    $getVendor->where(array('VendorId'=>$Supplier));
                    $venStatement = $sql->getSqlStringForSqlObject($getVendor);
                    $venName = $dbAdapter->query($venStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $VendorName=$this->bsf->isNullCheck($venName['VendorName'],'string');
                    //general
                    $voNo = CommonHelper::getVoucherNo(303, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->voNo = $voNo;
                    $vNo = $voNo['voucherNo'];
                    $this->_view->vNo = $vNo;

                    //CompanyId
                    $CMin = CommonHelper::getVoucherNo(303, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                    $this->_view->CMin = $CMin;
                    $CDCNo=$CMin['voucherNo'];
                    $this->_view->CDCNo = $CDCNo;

                    //CostCenterId
                    $CCMin = CommonHelper::getVoucherNo(303, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
                    $this->_view->CCMin = $CCMin;
                    $CCDCNo=$CCMin['voucherNo'];
                    $this->_view->CCDCNo = $CCDCNo;

                    $this->_view->gridtype=$gridtype;
                    $this->_view->CostCentre = $CostCentre;
                    $this->_view->Supplier = $Supplier;
                    $this->_view->Type = $Type;
                    $this->_view->DCdate = $DCdate;
                    $this->_view->poTransIds = $poTransIds;
                    $this->_view->CostCentreName= $CCName;
                    $this->_view->VendorName=$VendorName;



                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POTrans"))
                        ->columns(array(new Expression("Distinct(a.ResourceId),(a.ItemId),
                        case when a.ItemId>0 then '(' + c.ItemCode + ')' + '  ' + c.BrandName Else '(' + b.Code + ')' + '  ' + b.ResourceName End as [Desc],
                        d.UnitName As UnitName,a.UnitId,cast(sum(a.balqty) as decimal(18,3)) As Qty,
                        cast(sum(a.balqty) as decimal(18,3)) As AcceptQty,Cast(0 As Decimal(18,3)) As RejectQty")))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId',array(),$select::JOIN_LEFT)
                        ->where('a.POTransId IN (' . $poTransIds . ')');
                    $select->group(new expression("a.ResourceId,a.ItemId,c.ItemCode,c.BrandName,b.Code,b.ResourceName,d.UnitName,a.UnitId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //PO REGISTER
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PORegister"))
                        ->columns(array(new Expression("c.POProjTransId,a.PORegisterId,b.POTransId,b.ResourceId,b.ItemId,
                        a.PONo,CONVERT(varchar(10),a.PODate,105) As PODate,CONVERT(varchar(10),a.ReqDate,105) As ReqDate,
                        CAST(b.POQty As Decimal(18,3)) As OrderQty,CAST(b.AcceptQty As Decimal(18,3)) As MINQty,
                        CAST(b.BalQty As Decimal(18,3)) As BalQty,CAST(b.BalQty As Decimal(18,3)) As SupplierMIN,
                        CAST(b.BalQty As Decimal(18,3)) As AcceptQty,CAST(0 As Decimal(18,3)) As RejectQty ")))
                        ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId ', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_POProjTrans'), 'b.POTransId=c.POTransId', array(), $select::JOIN_INNER)
                        ->where('c.CostCentreId=' . $CostCentre . ' and b.POTransId IN (' . $poTransIds . ') and b.BalQty>0');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WBS
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PORegister"))
                        ->columns(array(new Expression("b.PORegisterId,b.POTransId,d.POAnalTransId,b.ResourceId,
                        b.ItemId,c.POProjTransId,D.AnalysisId as WBSId,e.ParentText+'->'+e.WBSName,CAST(d.POQty As Decimal(18,3)) As OrderQty,
                        CAST(d.AcceptQty As Decimal(18,3)) As MINQty,CAST(d.BalQty As Decimal(18,3)) As BalQty,
                        CAST(d.BalQty As Decimal(18,3)) As SupplierMIN,CAST(d.BalQty As Decimal(18,3)) As AcceptQty,CAST(0 As Decimal(18,3)) As RejectQty")))
                        ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_POProjTrans'), 'b.PoTransId=c.POTransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'MMS_POAnalTrans'), 'c.POProjTransId=d.POProjTransId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'Proj_WBSMaster'), 'd.AnalysisId=e.WBSId')
                        ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                        ->where('f.CostCentreId = ' . $CostCentre . '  and b.POTransId IN (' . $poTransIds . ') and d.BalQty>0');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WAREHOUSE
                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouse"))
                        ->columns(array(new Expression("b.CostCentreId,c.TransId As WareHouseId,a.WareHouseName,c.Description,CAST(0 As Decimal(18,3)) Qty,CAST(0 As Decimal(18,3)) HiddenQty")))
                        ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->where(array("b.CostCentreId=  $CostCentre And c.LastLevel=1"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //IsWareHouse
                    $select = $sql -> select();
                    $select->from(array("a" => "MMS_CCWareHouse" ))
                        ->columns(array(new Expression("a.WareHouseId")))
                        ->where(array("a.CostCentreId=$CostCentre"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $isWh = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if(count($isWh) > 0) {
                        $isWareHouse = 1;
                    } else {
                        $isWareHouse=0;
                    }
                    $this->_view->isWareHouse = $isWareHouse;

                    //GATEPASS
                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_GatePass "))
                        ->columns(array("GateRegId","GatePassNo"))
                        ->where(array("a.CostCentreId= $CostCentre And a.SupplierId= $Supplier "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_gatepass = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //VEHICLE MEASUREMENT
                    $select = $sql ->select();
                    $select->from(array("a" => "Vendor_VehicleMaster"))
                        ->columns(array("VehicleId","VehicleRegNo","VehicleName"))
                        ->where(array("a.VendorId= $Supplier "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_vehicle = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    // AGENCY DETAILS
                    $select = $sql->select();
                    $select-> from(array("a"=> "Vendor_Master"))
                        ->columns(array("VendorId","VendorName"))
                        ->where(array("a.service=1"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_agency = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //auto complete process
                    $sQuery = $sql->select();
                    $sQuery->from("MMS_POTrans")
                        ->columns(array('ResourceId','ItemId'))
                        ->where('POTransId IN (' . $poTransIds . ')');
                    $statement = $sql->getSqlStringForSqlObject($sQuery);
                    $resId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $resid = array();
                    $itmid = array();
                    foreach($resId as $resIds) {
                        array_push( $resid, $resIds['ResourceId']);
                        array_push( $itmid,$resIds['ItemId'] );
                    }
                    $resIDS = implode(",", $resid);
                    $itmIDS = implode(",", $itmid);
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                        ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,
                        Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then '('+d.ItemCode+ ')' + ' ' +d.BrandName Else '('+a.Code+ ')' + ' ' +a.ResourceName End As value,c.UnitName,c.UnitId")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                        ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                        ->where('f.CostCentreId = ' . $CostCentre . ' and (a.ResourceId NOT IN('. $resIDS .') OR isnull(d.BrandId,0) NOT IN ('. $itmIDS .'))');

                    $selRa = $sql -> select();
                    $selRa->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) ItemId,
                        Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code, Case When isnull(c.BrandId,0)>0 Then '('+c.ItemCode + ')' + ' ' + c.BrandName Else '('+a.Code + ')' + ' ' +a.ResourceName End As value,
                        Case When isnull(c.BrandId,0)>0 Then e.UnitName Else d.UnitName End As UnitName,Case When isnull(c.BrandId,0)>0 Then e.UnitId Else d.UnitId End As UnitId ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId',array(),$selRa::JOIN_LEFT)
                        ->join(array('c' => 'MMS_Brand'),'a.ResourceId=c.ResourceId',array(),$selRa::JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'),'a.UnitId=d.UnitId',array(),$selRa::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'),'c.UnitId=e.UnitId',array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=".$CostCentre.")
                          and (a.ResourceId Not IN ($resIDS) or isnull(c.BrandId,0) NOT IN ($itmIDS ))");

                    $select->combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
            } else {
                $postData = $request->getPost();
                //edit mode of minentry
                if (isset($dcid) && $dcid != '') {
                    // cost center details
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->join(array('b' => 'MMS_DCRegister'), 'a.CostCentreId=b.CostCentreId', array(), $select::JOIN_INNER)
                        ->where("a.Deactivate=0 AND b.DCRegisterId=$dcid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // vendor details
                    $select = $sql->select();
                    $select->from(array('a' => 'Vendor_Master'))
                        ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                        ->join(array('b' => 'MMS_DCRegister'), 'a.VendorId=b.VendorId')
                        ->where("b.DCRegisterId=$dcid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view-> vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $selDCReg = $sql->select();
                    $selDCReg->from(array('a' => 'MMS_DCRegister'))
                        ->columns(array(new Expression("a.CostCentreId,a.VendorId,a.RefNo,a.DCORCSM,b.CostCentreName,
                        c.VendorName,CONVERT(varchar(10),a.DCDate,105) As DCDate,CONVERT(varchar(10),a.SiteDCDate,105) As SiteDCDate,
                        a.SiteDCNo,a.DCNo,a.CCDCNo,a.CDCNo As CDCNo,a.Narration,a.GatePassNo,a.WithLoad,a.WithOutLoad,a.MaterialWeigh,
                         Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve,
                         a.IsTested,a.AgencyId,a.TestingMethod,a.TestResults,a.GridType")))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $selDCReg::JOIN_INNER)
                        ->join(array('c' => 'Vendor_Master'), 'a.VendorId=c.VendorId', array(), $selDCReg::JOIN_INNER)
                        ->where(array("a.DCRegisterId" => $dcid));
                    $statement = $sql->getSqlStringForSqlObject($selDCReg);
                    $this->_view->dcregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $CostCentreName = $this->_view->dcregister['CostCentreName'];
                    $VendorName = $this->_view->dcregister['VendorName'];
                    $Type = $this->_view->dcregister['DCORCSM'];
                    $CostCentreId = $this->_view->dcregister['CostCentreId'];
                    $VendorId = $this->_view->dcregister['VendorId'];
                    $SiteDCNo = $this->_view->dcregister['SiteDCNo'];
                    $SiteDCDate = $this->_view->dcregister['SiteDCDate'];
                    $CCDCNo = $this->_view->dcregister['CCDCNo'];
                    $RefNo = $this->_view->dcregister['RefNo'];
                    $CDCNo = $this->_view->dcregister['CDCNo'];
                    $vNo = $this->_view->dcregister['DCNo'];
                    $DCdate = $this->_view->dcregister['DCDate'];
                    $Narration = $this->_view->dcregister['Narration'];
                    $GatePassNo = $this->_view->dcregister['GatePassNo'];
                    $WithLoad = $this->_view->dcregister['WithLoad'];
                    $approve = $this->_view->dcregister['Approve'];
                    $WithOutLoad = $this->_view->dcregister['WithOutLoad'];
                    $MaterialWeigh = $this->_view->dcregister['MaterialWeigh'];
                    $Tested = $this->_view->dcregister['IsTested'];
                    $AgencyId = $this->_view->dcregister['AgencyId'];
                    $TestingMethod = $this->_view->dcregister['TestingMethod'];
                    $TestResults = $this->_view->dcregister['TestResults'];
                    $gridtype = $this->_view->dcregister['GridType'];

                    $this->_view->DCdate = $DCdate;
                    $this->_view->CostCentreName = $CostCentreName;
                    $this->_view->VendorName = $VendorName;
                    $this->_view->vNo = $vNo;
                    $this->_view->dcid = $dcid;
                    $this->_view->SiteDCNo = $SiteDCNo;
                    $this->_view->CCDCNo = $CCDCNo;
                    $this->_view->RefNo = $RefNo;
                    $this->_view->SiteDCDate = $SiteDCDate;
                    $this->_view->CDCNo = $CDCNo;
                    $this->_view->CostCentre = $CostCentreId;
                    $this->_view->VendorId = $VendorId;
                    $this->_view->Narration = $Narration;
                    $this->_view->GatePassNo = $GatePassNo;
                    $this->_view->WithLoad = $WithLoad;
                    $this->_view->approve = $approve;
                    $this->_view->WithOutLoad = $WithOutLoad;
                    $this->_view->MaterialWeigh = $MaterialWeigh;
                    $this->_view->Tested = $Tested;
                    $this->_view->AgencyId = $AgencyId;
                    $this->_view->TestingMethod = $TestingMethod;
                    $this->_view->TestResults = $TestResults;
                    $this->_view->gridtype = $gridtype;

                    //IsWareHouse
                    $select = $sql -> select();
                    $select->from(array("a" => "MMS_CCWareHouse" ))
                        ->columns(array(new Expression("a.WareHouseId")))
                        ->where(array("a.CostCentreId=$CostCentreId"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $isWh = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    if(count($isWh) > 0)
                    {
                        $isWareHouse = 1;
                    }
                    else
                    {
                        $isWareHouse=0;
                    }
                    $this->_view->isWareHouse = $isWareHouse;

                    //getting POTransId from dctrans

                    $selectPo = $sql->select();
                    $selectPo->from(array('a' => 'MMS_DCTrans'))
                        ->columns(array('PoTransId'))
                        ->where("a.DCRegisterId=$dcid");
                    $statement = $sql->getSqlStringForSqlObject($selectPo);
                    $selectPOTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $poTransid = array();
                    foreach ($selectPOTrans as $potrans) {
                        array_push($poTransid, $potrans['PoTransId']);
                    }
                    $poTransIDS = implode(",", $poTransid);

                    if (!$poTransIDS == '') {
                        $this->_view->poTransId = $poTransIDS;

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("Distinct(a.ResourceId),(a.ItemId),
                            case when a.ItemId>0 then '(' + c.ItemCode +')' + ' ' + c.BrandName Else '(' + b.Code +')' + ' ' +b.ResourceName End as [Desc],
                                d.UnitName As UnitName,a.UnitId, CAST(f.DCQty As Decimal(18,3)) As Qty,CAST(f.AcceptQty As Decimal(18,3)) As AcceptQty,
                                CAST(f.RejectQty As Decimal(18,3)) As RejectQty,f.Remarks,CONVERT(varchar(10),f.ExpiryDate,105) As ExpiryDate")))
                            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $select::JOIN_INNER)
                            ->join(array('e' => 'MMS_DCTrans'), 'a.PoTransId=e.POTransId', array(), $select::JOIN_INNER)
                            ->join(array('f' => 'MMS_DCGroupTrans'), 'e.DCGroupId=f.DCGroupId', array(), $select::JOIN_INNER)
                            ->where('a.POTransId IN (' . $poTransIDS . ') AND f.DCRegisterId=' . $dcid . '');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //edit mode- po display
                        $selectPro = $sql->select();
                        $selectPro->from(array('a' => 'MMS_PORegister'))
                            ->columns(array(new Expression("c.POProjTransId,a.PORegisterId,b.POTransId,b.ResourceId,b.ItemId,a.PONo,Convert(Varchar(10),a.PODate,105) As PODate,
                                Convert(Varchar(10),a.ReqDate,105) As ReqDate,CAST(b.POQty As Decimal(18,3)) As OrderQty,CAST(b.AcceptQty As Decimal(18,3)) As MINQty,
                                CAST(b.BalQty As Decimal(18,3)) As BalQty,CAST(0 As Decimal(18,3)) As SupplierMIN,CAST(0 As Decimal(18,3)) As AcceptQty,
                                CAST(0 As Decimal(18,3)) As RejectQty,CAST(0 As Decimal(18,3)) As HiddenAQty,CAST(0 As Decimal(18,3)) As HiddenSMQty ")))
                            ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId', array(), $selectPro::JOIN_INNER)
                            ->join(array('c' => 'MMS_POProjTrans'), 'b.POTransId=c.POTransId', array(), $selectPro::JOIN_INNER)
                            ->Where("c.CostCentreId= $CostCentreId  And a.VendorId= $VendorId And b.BalQty>0 And a.LivePO=1 And
                                b.POTransId NOT IN (Select POTransId From MMS_DCTrans Where DCRegisterId= $dcid)And a.Approve='Y' ");

                        $select = $sql->select();
                        $select->from(array('a' => 'MMS_DCTrans'))
                            ->columns(array(new Expression("d.POProjTransId,e.PORegisterId,c.POTransId,c.ResourceId,c.ItemId,e.PONo,
                                   CONVERT(Varchar(10),e.PODate,105) As PODate,
                                   CONVERT(Varchar(10),e.ReqDate,105) As ReqDate,CAST(c.POQty As Decimal(18,3)) As OrderQty,CAST(c.AcceptQty As Decimal(18,3)) As MINQty,
                                   CAST(c.BalQty As Decimal(18,3)) As BalQty,CAST(a.DCQty As Decimal(18,3)) As SupplierMIN,CAST(a.AcceptQty As Decimal(18,3)) As AcceptQty,
                                   CAST(a.RejectQty As Decimal(18,3)) As RejectQty,CAST(a.AcceptQty As Decimal(18,3)) As HiddenAQty,CAST(a.DCQty As Decimal(18,3)) As HiddenSMQty")))
                            ->join(array('b' => 'MMS_DCRegister'), 'a.DCRegisterId=b.DCRegisterId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'), 'a.POTransId=c.PoTransId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'MMS_POProjTrans'), 'c.PoTransId=d.POTransId', array(), $select::JOIN_INNER)
                            ->join(array('e' => 'MMS_PORegister'), 'c.PORegisterId=e.PORegisterId ', array(), $select::JOIN_INNER)
                            ->Where('a.DCRegisterId=' . $dcid . ' And b.CostCentreId=' . $CostCentreId . ' and c.BalQty>0 and  c.POTransId IN (' . $poTransIDS . ')');

                        $select->combine($selectPro,'Union ALL');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //edit-mode-wbs display
                        $analQuery = $sql->select();
                        $analQuery -> from(array('a' => 'MMS_IPDAnalTrans'))
                            ->columns(array('POAHTransId'))
                            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $analQuery::JOIN_INNER)
                            ->join(array('c' => 'MMS_IPDTrans'), 'b.IPDTransId=c.IPDTransId', array(), $analQuery::JOIN_INNER)
                            ->join(array('d' => 'MMS_DCTrans'), 'c.DCTransId=d.DCTransId', array(), $analQuery::JOIN_INNER)
                            ->where("a.Status='D' And b.Status='D' And c.Status='D'  And d.DCRegisterId= $dcid");

                        $selectProanal = $sql->select();
                        $selectProanal->from(array('a' => 'MMS_PORegister'))
                            ->columns(array(new Expression("a.PORegisterId,b.POTransId,d.POAnalTransId,b.ResourceId,b.ItemId,d.AnalysisId As WBSId,
                                    e.ParentText+'->'+e.WBSName As WBSName,CAST(d.POQty As Decimal(18,2)) As OrderQty,
                                    CAST(d.AcceptQty As Decimal(18,2)) As MINQty,CAST(d.BalQty As Decimal(18,2)) As BalQty,
                                    CAST(0 As Decimal(18,2)) As SupplierMIN,CAST(0 As Decimal(18,2)) As AcceptQty,Cast(0 As Decimal(18,2)) As RejectQty,
                                    CAST(0 As Decimal(18,5)) As HAcceptQty,CAST(0 As Decimal(18,5)) As HSMqty")))
                            ->join(array('b' => 'MMS_POTrans'), 'a.PORegisterId=b.PORegisterId', array(), $selectProanal::JOIN_INNER)
                            ->join(array('c' => 'MMS_POProjTrans'), 'b.POTransId=c.POTransId', array(), $selectProanal::JOIN_INNER)
                            ->join(array('d' => 'MMS_POAnalTrans'), 'c.POProjTransId=d.POProjTransId', array(), $selectProanal::JOIN_INNER)
                            ->join(array('e' => 'Proj_WbsMaster'), 'd.AnalysisId=e.WbsId', array(), $selectProanal::JOIN_INNER)
                            ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$selectProanal::JOIN_INNER)
                            ->Where->expression("f.CostCentreId= $CostCentreId And a.VendorId= $VendorId  And a.LivePO=1 And a.Approve='Y' And d.POAnalTransId NOT IN ?", array($analQuery));


                        $select1 = $sql->select();
                        $select1->from(array('a' => 'MMS_IPDAnalTrans'))
                            ->columns(array(new Expression("f.PORegisterId,f.PoTransId,d.POAnalTransId,f.ResourceId,f.ItemId,d.AnalysisId As WBSId,
                                 g.ParentText+'->'+g.WBSName As WBSName,CAST(d.POQty As Decimal(18,5)) As OrderQty,CAST(d.AcceptQty As Decimal(18,5)) As MINQty,
                                 CAST(d.BalQty As Decimal(18,5)) As BalQty,CAST(b.DCQty As Decimal(18,5)) As SupplierMIN,CAST(b.AcceptQty As Decimal(18,5)) As AcceptQty,
                                 CAST(b.RejectQty As Decimal(18,5)) As RejectQty,CAST(b.AcceptQty As Decimal(18,5)) As HAcceptQty,CAST(b.DCQty As Decimal(18,5)) As HSMqty")))
                            ->join(array('b' => 'MMS_DCAnalTrans'), 'a.DCAHTransId=b.DCAnalTransId', array(), $select1::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCTrans'), 'b.DCTransId=c.DCTransId', array(), $select1::JOIN_INNER)
                            ->join(array('d' => 'MMS_POAnalTrans'), 'a.POAHTransId=d.POAnalTransId  And b.AnalysisId=d.AnalysisId', array(), $select1::JOIN_INNER)
                            ->join(array('e' => 'MMS_POProjTrans'), 'd.POProjTransId=e.POProjTransId', array(), $select1::JOIN_INNER)
                            ->join(array('f' => 'MMS_POTrans'), 'e.POTransId=f.PoTransId And c.POTransId=f.PoTransId', array(), $select1::JOIN_INNER)
                            ->join(array('g' => 'Proj_WBSMaster'), 'd.AnalysisId=g.WBSId', array(), $select1::JOIN_INNER)
                            ->join(array('h' => 'WF_OperationalCostCentre'),'g.ProjectId=h.ProjectId',array(),$select1::JOIN_INNER)
                            ->Where('c.DCRegisterId=' . $dcid . ' And h.CostCentreId=' . $CostCentreId . ' And f.POTransId IN (' . $poTransIDS . ') And d.BalQty>0');
                        $select1->combine($selectProanal,'Union ALL');
                        $statement = $sql->getSqlStringForSqlObject($select1);
                        $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //WAREHOUSE
                        $select = $sql ->select();
                        $select->from(array("a" => "MMS_WareHouse"))
                            ->columns(array(new Expression("b.CostCentreId,c.TransId As WareHouseId,a.WareHouseName,c.Description,CAST(0 As Decimal(18,2)) Qty,CAST(0 As Decimal(18,2)) HiddenQty")))
                            ->join(array("b" => "MMS_CCWareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("c" => "MMS_WareHouseDetails"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                            ->where(array("b.CostCentreId=  $CostCentreId And c.LastLevel=1"));
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_sel_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql ->select();
                        $select->from(array("a" => "MMS_DCWareHousePoTrans"))
                            ->columns(array(new Expression("c.CostCentreId,e.ResourceId,e.ItemId,
                            b.TransId as WareHouseId,a.POTransId as POTransId,
                            d.WareHouseName,b.Description,CAST(a.Qty As Decimal(18,2)) Qty,
                            CAST(a.Qty As Decimal(18,2)) HiddenQty")))
                            ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
                            ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("e" => "MMS_DCGroupTrans"), "a.DCGroupId=e.DCGroupId", array(), $select::JOIN_INNER)
                            ->where(array("c.CostCentreId"=> $CostCentreId ,"b.LastLevel"=>1, "e.DCRegisterId" =>  $dcid ));
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_dcpowarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql ->select();
                        $select->from(array("a" => "MMS_DCWareHouseWbsTrans"))
                            ->columns(array(new Expression("c.CostCentreId,e.ResourceId,e.ItemId,
                            b.TransId as WareHouseId,a.POAnalTransId as POAnalTransId,
                            d.WareHouseName,b.Description,CAST(a.Qty As Decimal(18,2)) Qty,
                            CAST(a.Qty As Decimal(18,2)) HiddenQty")))
                            ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
                            ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.WareHouseId", array(), $select::JOIN_INNER)
                            ->join(array("e" => "MMS_DCGroupTrans"), "a.DCGroupId=e.DCGroupId", array(), $select::JOIN_INNER)
                            ->where(array("c.CostCentreId"=> $CostCentreId ,"b.LastLevel"=>1, "e.DCRegisterId" =>  $dcid ));
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_dcwbswarehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //auto complete process
                        $select = $sql->select();
                        $select->from(array('a' => 'Proj_Resource'))
                            //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                            ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then '(' + d.ItemCode +')' + ' ' + d.BrandName Else '(' + a.Code+')' + ' ' + a.ResourceName End As value,c.UnitName,c.UnitId")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                            ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                            ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                            ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                            ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                            ->where('f.CostCentreId = ' . $CostCentreId . ' and (a.ResourceId NOT IN( select resourceid from MMS_DCTrans where DCRegisterId = ' .$dcid. ' ) OR isnull(d.BrandId,0) NOT IN ( select ItemId from MMS_DCTrans where DCRegisterId = ' .$dcid. '))');
                        $selRa = $sql -> select();
                        $selRa->from(array('a' => 'Proj_Resource'))
                            ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) ItemId,Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code, Case When isnull(c.BrandId,0)>0 Then '(' + c.ItemCode+')' + ' ' + c.BrandName Else '(' + a.Code +')' + ' ' + a.ResourceName End As value,
                           Case When isnull(c.BrandId,0)>0 Then e.UnitName Else d.UnitName End As UnitName,Case When isnull(c.BrandId,0)>0 Then e.UnitId Else d.UnitId End As UnitId ")))
                            ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId',array(),$selRa::JOIN_LEFT)
                            ->join(array('c' => 'MMS_Brand'),'a.ResourceId=c.ResourceId',array(),$selRa::JOIN_LEFT)
                            ->join(array('d' => 'Proj_UOM'),'a.UnitId=d.UnitId',array(),$selRa::JOIN_LEFT)
                            ->join(array('e' => 'Proj_UOM'),'c.UnitId=e.UnitId',array(),$selRa::JOIN_LEFT)
                            ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentreId)
                          and (a.ResourceId Not IN (select resourceid from MMS_DCTrans where DCRegisterId =$dcid) or isnull(c.BrandId,0) NOT IN (select ItemId from MMS_DCTrans where DCRegisterId =$dcid) )");
                        $select->combine($selRa,"Union All");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    //GATEPASS
                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_GatePass "))
                        ->columns(array("GateRegId","GatePassNo"))
                        ->where(array("a.CostCentreId= $CostCentreId And a.SupplierId= $VendorId "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_gatepass = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // AGENCY DETAILS
                    $select = $sql->select();
                    $select-> from(array("a"=> "Vendor_Master"))
                        ->columns(array("VendorId","VendorName"))
                        ->where(array("a.service=1"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_agency = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //VEHICLE MEASUREMENT
                    $select = $sql ->select();
                    $select->from(array("a" => "Vendor_VehicleMaster"))
                        ->columns(array("VehicleId","VehicleRegNo","VehicleName"))
                        ->where(array("a.VendorId= $VendorId "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_vehicle = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_DCTripSheet"))
                        ->columns(array("*"))
                        ->join(array('b' => 'Vendor_VehicleMaster'),'a.VehicleId=b.VehicleId',array('VehicleName'),$select::JOIN_LEFT)
                        ->where(array("a.DCRegisterId = $dcid AND a.VendorId = $VendorId "));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->vehicleData = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                }
            }
            //Common function
            $aVNo = CommonHelper::getVoucherNo(303, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->woNo = "";
            else
                $this->_view->woNo = $aVNo["voucherNo"];
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
            return $this->_view;
        }
    }

    public function minsaveAction() {
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "min","action" => "Minentry"));
            }
        }
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(301, date('Y/m/d'), 0, 0, $dbAdapter, "");
        $this->_view->vNo = $vNo;

        //aJAX rEQUEST
        if ($this->getRequest()->isXmlHttpRequest()) {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();

                /* echo"<pre>";
                   print_r($postParams);
                  echo"</pre>";
                   die;
                  return;*/
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

//               echo"<pre>";
//                 print_r($postParams);
//                  echo"</pre>";
//                 die;
//                   return;

                $Approve="";
                $Role="";
                $dcid = $this->bsf->isNullCheck($postParams['DcId'], 'number');
                if ($this->bsf->isNullCheck($dcid, 'number') > 0) {
                    $Approve="E";
                    $Role="Min-Modify";
                }else{
                    $Approve="N";
                    $Role="Min-Create";
                }

                $CostCentre = $this->bsf->isNullCheck($postParams['CostCenterId'], 'number');
                $VendorId = $this->bsf->isNullCheck($postParams['VendorId'], 'number');
                $Type = $this->bsf->isNullCheck($postParams['Type'], 'string');
                if ($Type == 'DC') {
                    $dc = 1;
                }
                else{
                    $dc = 0;
                }

                $voucherno='';

                $DCdate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['DCdate'], 'string')));
                $SiteDCNo = $this->bsf->isNullCheck($postParams['SiteDCNo'], 'string');
                $SiteDCDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['SiteDCDate'], 'string')));
                $DCNo = $this->bsf->isNullCheck($postParams['PONo'], 'string');
                $voucherno=$DCNo;
                $CCDCNo = $this->bsf->isNullCheck($postParams['CCDCNo'], 'string');
                $CDCNo = $this->bsf->isNullCheck($postParams['CDCNo'], 'string');
                $RefNo = $this->bsf->isNullCheck($postParams['RefNo'], 'string');
                $Narration = $this->bsf->isNullCheck($postParams['Narration'], 'string');
                $GatePassNo = $this->bsf->isNullCheck($postParams['GatePassNo'], 'string');
                $WithLoad = $this->bsf->isNullCheck($postParams['WithLoad'], 'number');
                $WithOutLoad = $this->bsf->isNullCheck($postParams['WithOutLoad'], 'number');
                $MaterialWeigh = $this->bsf->isNullCheck($postParams['MaterialWeigh'], 'number');
                $Tested = $this->bsf->isNullCheck($postParams['Tested'], 'number');
                $AgencyId = $this->bsf->isNullCheck($postParams['AgencyId'], 'number');
                $TestingMethod = $this->bsf->isNullCheck($postParams['TestingMethod'], 'string');
                $TestResults = $this->bsf->isNullCheck($postParams['TestResults'], 'string');


                $vehicleId = $this->bsf->isNullCheck($postParams['vehicleId'], 'number');
                $bLength = $this->bsf->isNullCheck($postParams['bLength'], 'number');
                $bBreadth = $this->bsf->isNullCheck($postParams['bBreadth'], 'number');
                $bHeight = $this->bsf->isNullCheck($postParams['bHeight'], 'number');
                $bQty = $this->bsf->isNullCheck($postParams['bQty'], 'number');
                $tMaxLength = $this->bsf->isNullCheck($postParams['tMaxLength'], 'number');
                $tMaxBreadth = $this->bsf->isNullCheck($postParams['tMaxBreadth'], 'number');
                $tMaxHeight = $this->bsf->isNullCheck($postParams['tMaxHeight'], 'number');
                $tMaxQty = $this->bsf->isNullCheck($postParams['tMaxQty'], 'number');
                $tMinLength = $this->bsf->isNullCheck($postParams['tMinLength'], 'number');
                $tMinBreadth = $this->bsf->isNullCheck($postParams['tMinBreadth'], 'number');
                $tMinHeight = $this->bsf->isNullCheck($postParams['tMinHeight'], 'number');
                $tMinQty = $this->bsf->isNullCheck($postParams['tMinQty'], 'number');
                $bctotal = $this->bsf->isNullCheck($postParams['bctotal'], 'number');
                $perQty = $this->bsf->isNullCheck($postParams['perQty'], 'number');
                $netQty = $this->bsf->isNullCheck($postParams['netQty'], 'number');
                $remarks = $this->bsf->isNullCheck($postParams['remarks'], 'string');
                $gridtype = $this->bsf->isNullCheck($postParams['gridtype'], 'number');

                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where("CostCentreId=$CostCentre");
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$Comp['CompanyId'];

                //CompanyId
                $CMin = CommonHelper::getVoucherNo(303, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                $this->_view->CMin = $CMin;


                //CostCenterId
                $CCMin = CommonHelper::getVoucherNo(303, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
                $this->_view->CCMin = $CCMin;


                //begin trans try block example starts for edit mode
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection-> beginTransaction();
                try {

                    if ($dcid > 0) {

                        $selPrevAnal = $sql->select();
                        $selPrevAnal->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array("DCQty", "AcceptQty", "RejectQty"))
                            ->join(array("b" => "MMS_IPDAnalTrans"), "a.DCAnalTransId=b.DCAHTransId ", array("POAHTransId"), $selPrevAnal::JOIN_INNER)
                            ->join(array("c" => "MMS_DCTrans"), "a.DCTransId=c.DCTransId", array(), $selPrevAnal::JOIN_INNER)
                            ->where(array("c.DCRegisterId" => $dcid));
                        $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                        $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($prevanal as $arrprevanal) { // WBS QTY

                            $updDcAnal = $sql->update();
                            $updDcAnal->table('MMS_POAnalTrans');
                            $updDcAnal->set(array(

                                'DCQty' => new Expression('DCQty-' . $arrprevanal['DCQty'] . ''),
                                'AcceptQty' => new Expression('AcceptQty-' . $arrprevanal['AcceptQty'] . ''),
                                'RejectQty' => new Expression('RejectQty-' . $arrprevanal['RejectQty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrprevanal['AcceptQty'] . '')
                            ));
                            $updDcAnal->where(array('POAnalTransId' => $arrprevanal['POAHTransId']));
                            $updDcAnalStatement = $sql->getSqlStringForSqlObject($updDcAnal);
                            $dbAdapter->query($updDcAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $selPrevTrans = $sql->select();
                        $selPrevTrans->from("MMS_DCTrans")
                            ->columns(array("POTransId", "DCQty", "AcceptQty", "RejectQty"))
                            ->where(array("DCRegisterId" => $dcid));
                        $statement = $sql->getSqlStringForSqlObject($selPrevTrans);
                        $prevtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($prevtrans as $arrprevtrans) { // PO QTY

                            $updDcTrans = $sql->update();
                            $updDcTrans->table('MMS_POTrans');
                            $updDcTrans->set(array(

                                'DCQty' => new Expression('DCQty-' . $arrprevtrans['DCQty'] . ''),
                                'AcceptQty' => new Expression('AcceptQty-' . $arrprevtrans['AcceptQty'] . ''),
                                'RejectQty' => new Expression('RejectQty-' . $arrprevtrans['RejectQty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrprevtrans['AcceptQty'] . '')
                            ));
                            $updDcTrans->where(array('PoTransId' => $arrprevtrans['POTransId']));
                            $updDcTransStatement = $sql->getSqlStringForSqlObject($updDcTrans);
                            $dbAdapter->query($updDcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $updDcProTrans = $sql->update();
                            $updDcProTrans->table('MMS_POProjTrans');
                            $updDcProTrans->set(array(

                                'DCQty' => new Expression('DCQty-' . $arrprevtrans['DCQty'] . ''),
                                'AcceptQty' => new Expression('AcceptQty-' . $arrprevtrans['AcceptQty'] . ''),
                                'RejectQty' => new Expression('RejectQty-' . $arrprevtrans['RejectQty'] . ''),
                                'BalQty' => new Expression('BalQty+' . $arrprevtrans['AcceptQty'] . '')
                            ));
                            $updDcProTrans->where(array('POTransId' => $arrprevtrans['POTransId']));
                            $updDcProTransStatement = $sql->getSqlStringForSqlObject($updDcProTrans);
                            $dbAdapter->query($updDcProTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }


                        //stock edit mode -update
                        $sel = $sql->select();
                        $sel->from(array("a" => "MMS_DCTrans"))
                            ->columns(array("ResourceId","ItemId","AcceptQty"))
                            ->join(array("b" => "MMS_DCRegister"), "a.DCRegisterId=b.DCRegisterId", array("CostCentreId"), $sel::JOIN_INNER)
                            ->where(array("a.DCRegisterId" => $dcid));
                        $statementPrev = $sql->getSqlStringForSqlObject($sel);
                        $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($pre as $preStock) {

                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array(
                                    "ResourceId" => $preStock['ResourceId'],
                                    "CostCentreId" => $preStock['CostCentreId'],
                                    "ItemId" => $preStock['ItemId']
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($stockselId['StockId']) > 0) {

                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "DCQty" => new Expression('DCQty-' . $preStock['AcceptQty'] . ''),
                                    "ClosingStock" => new Expression('ClosingStock-' . $preStock['AcceptQty'] . '')
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }

                            //stocktrans edit mode -update
                            $sel = $sql->select();
                            $sel->from(array("a" => "MMS_DCGroupTrans"))
                                ->columns(array("CostCentreId", "ResourceId", "ItemId"))
                                ->join(array("b" => "MMS_DCWareHouseTrans"), "a.DCGroupId=b.DCGroupId", array("WareHouseId", "DCQty"), $sel::JOIN_INNER)
                                ->where(array("a.DCRegisterId" => $dcid));
                            $statementPrev = $sql->getSqlStringForSqlObject($sel);
                            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach ($pre as $preStockTrans) {

                                if (count($stockselId['StockId']) > 0) {

                                    $stockUpdate = $sql->update();
                                    $stockUpdate->table('mms_stockTrans');
                                    $stockUpdate->set(array(
                                        "DCQty" => new Expression('DCQty-' . $preStockTrans['DCQty'] . ''),
                                        "ClosingStock" => new Expression('ClosingStock-' . $preStockTrans['DCQty'] . '')
                                    ));
                                    $stockUpdate->where(array("StockId" => $stockselId['StockId'],"WareHouseId" => $preStockTrans['WareHouseId']));
                                    $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                    $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }

                        //delete the previous row
                        //subquery

                        //warehouse delete
                        $whQuery3 = $sql->select();
                        $whQuery3->from('MMS_DCGroupTrans')
                            ->columns(array("DCGroupId"))
                            ->where(array("DCRegisterId" => $dcid));

                        $del = $sql->delete();
                        $del->from('MMS_DCWareHouseTrans')
                            ->where->expression('DCGroupId IN ?', array($whQuery3));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del1 = $sql->delete();
                        $del1->from('MMS_DCWareHousePoTrans')
                            ->where->expression('DCGroupId IN ?', array($whQuery3));
                        $statement = $sql->getSqlStringForSqlObject($del1);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del2 = $sql->delete();
                        $del2->from('MMS_DCWareHouseWbsTrans')
                            ->where->expression('DCGroupId IN ?', array($whQuery3));
                        $statement = $sql->getSqlStringForSqlObject($del2);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $subQuery1 = $sql->select();
                        $subQuery1->from("MMS_DCTrans")
                            ->columns(array("DCTransId"))
                            ->where(array("DCRegisterId" => $dcid, "Status" => 'D'));

                        $subQuery = $sql->select();
                        $subQuery->from("MMS_DCAnalTrans")
                            ->columns(array("DCAnalTransId"))
                            ->where->expression('DCTransId IN ?', array($subQuery1));

                        $del = $sql->delete();
                        $del->from('MMS_IPDAnalTrans')
                            ->where->expression('DCAHTransId IN ?', array($subQuery));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $arrTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //subQuery1
                        $subIPDP = $sql->select();
                        $subIPDP->from("MMS_DCTrans")
                            ->columns(array("DCTransId"))
                            ->where(array("DCRegisterId" => $dcid, "Status" => 'D'));

                        $del = $sql->delete();
                        $del->from('MMS_IPDProjTrans')
                            ->where->expression('DCProjTransId IN ?', array($subIPDP));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $arrIPDPTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //subQuery2
                        $subQuery2 = $sql->select();
                        $subQuery2->from('MMS_DCTrans')
                            ->columns(array("DCTransId"))
                            ->where(array("DCRegisterId" => $dcid, "Status" => 'D'));

                        $del = $sql->delete();
                        $del->from('MMS_IPDTrans')
                            ->where->expression('DCTransId IN ?', array($subQuery2));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //subQuery3
                        $subQuery3 = $sql->select();
                        $subQuery3->from('MMS_DCTrans')
                            ->columns(array("DCTransId"))
                            ->where(array("DCRegisterId" => $dcid));

                        $del = $sql->delete();
                        $del->from('MMS_DCAnalTrans')
                            ->where->expression('DCTransId IN ?', array($subQuery3));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del = $sql->delete();
                        $del->from('MMS_DCGroupTrans')
                            ->where(array("DCRegisterId" => $dcid));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del = $sql->delete();
                        $del->from('MMS_DCTrans')
                            ->where(array("DCRegisterId" => $dcid));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //tripsheet delete
                        $tripDel = $sql->delete();
                        $tripDel->from('mms_DCTripSheet')
                            ->where(array("DCRegisterId" => $dcid));
                        $stat = $sql->getSqlStringForSqlObject($tripDel);
                        $dbAdapter->query($stat, $dbAdapter::QUERY_MODE_EXECUTE);

                        //DCRegister update
                        $registerUpdate = $sql->update();
                        $registerUpdate->table('MMS_DCRegister');
                        $registerUpdate->set(array(
                            'DCDate' => $DCdate,
                            'DCNo' => $voucherno,
                            'SiteDCDate' => $SiteDCDate,
                            'SiteDCNo' => $SiteDCNo,
                            'CCDCNo' => $CCDCNo,
                            'CDCNo' => $CDCNo,
                            'RefNo' => $RefNo,
                            'Narration' => $Narration,
                            'GatePassNo' => $GatePassNo,
                            'WithLoad' => $WithLoad,
                            'WithOutLoad' => $WithOutLoad,
                            'MaterialWeigh' => $MaterialWeigh,
                            "IsTested" => $Tested,
                            "AgencyId" => $AgencyId,
                            "TestingMethod" => $TestingMethod,
                            "TestResults" => $TestResults
                        ));
                        $registerUpdate->where(array('DCRegisterId' => $dcid));
                        $registerUpdateStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                        $dbAdapter->query($registerUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //$DCRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //po and wbs update
                        $dcregId = $postParams['rowid'];

                        if ($dcregId > 0) {
                            for ($i = 1; $i < $dcregId; $i++) {

                                if($postParams['unitid_' . $i] != '' ||$postParams['unitid_' . $i] !=0 ) {

                                    $ExpiryDate = 'NULL';
                                    if ($postParams['RefDate_' . $i] == '' || $postParams['RefDate_' . $i] == null) {
                                        $ExpiryDate = null;
                                    } else {
                                        $ExpiryDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['RefDate_' . $i], 'string')));
                                    }

                                    $DCGroupInsert = $sql->insert('MMS_DCGroupTrans');
                                    $DCGroupInsert->values(array("DCRegisterId" => $dcid, "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                        "ResourceId" =>  $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'), "ItemId" =>  $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                        "CostCentreId" => $this->bsf->isNullCheck($postParams['CostCenterId'],'number'), "DCQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'. ''),
                                        "BalQty" => $this->bsf->isNullCheck($postParams['AcceptQty_' . $i],'number'. ''), "AcceptQty" => $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number'. ''),
                                        "RejectQty" => $this->bsf->isNullCheck($postParams['RejectQty_' . $i], 'number'. ''), "Remarks" => $this->bsf->isNullCheck($postParams['Remarks_' . $i], 'string'),
                                        "ExpiryDate" => $ExpiryDate));
                                    $DCGroupStatement = $sql->getSqlStringForSqlObject($DCGroupInsert);
                                    $dbAdapter->query($DCGroupStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $DCGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    // stock details updating
                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stock"))
                                        ->columns(array("StockId"))
                                        ->where(array("CostCentreId" => $postParams['CostCenterId'],
                                            "ResourceId" => $postParams['resourceid_' . $i],
                                            "ItemId" => $postParams['itemid_' . $i]
                                        ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    $esId = $stockselId['StockId'];

                                    if (count($esId) > 0) {

                                        $stockUpdate = $sql->update();
                                        $stockUpdate->table('mms_stock');
                                        $stockUpdate->set(array(
                                            "DCQty" => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number') . ''),
                                            "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number') . '')
                                        ));
                                        $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                        $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                        $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    } else {
                                        if($postParams['AcceptQty_' . $i] != '' || $postParams['AcceptQty_' . $i] > 0) {
                                            $stock = $sql->insert('mms_stock');
                                            $stock->values(array("CostCentreId" => $postParams['CostCenterId'],
                                                "ResourceId" => $postParams['resourceid_' . $i],
                                                "ItemId" => $postParams['itemid_' . $i],
                                                "UnitId" => $postParams['unitid_' . $i],
                                                "DCQty" => $postParams['AcceptQty_' . $i],
                                                "ClosingStock" => $postParams['AcceptQty_' . $i]
                                            ));
                                            $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                            $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $newStockId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        }
                                        $esId = $newStockId;
                                    } // end of stock update
                                }
                                $dctransTotal = $postParams['iow_' . $i . '_rowid'];

                                if ($dctransTotal > 0) {
                                    for ($j = 1; $j <= $dctransTotal; $j++) {
                                        if($postParams['iow_' . $i . '_AcceptQty_' . $j] || $postParams['iow_' . $i . '_RejectQty_' . $j] || $postParams['iow_' . $i . '_SupplierMIN_' . $j] >0 ) {

                                            $DCTransInsert = $sql->insert('MMS_DCTrans');
                                            $DCTransInsert->values(array("DCGroupId" => $DCGroupId, "DCRegisterId" => $dcid, "POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_potransid_' . $j], 'number'), "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'), "DCQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_SupplierMIN_' . $j], 'number'), "BalQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number'),
                                                "AcceptQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number'), "RejectQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_RejectQty_' . $j], 'number')));
                                            $DCTransStatement = $sql->getSqlStringForSqlObject($DCTransInsert);
                                            $dbAdapter->query($DCTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $DCTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $status = "D";
                                            $IPDTransInsert = $sql->insert('MMS_IPDTrans');
                                            $IPDTransInsert->values(array("DCTransId" => $DCTransId, "Status" => $status, "ResourceId" => $postParams['iow_' . $i . '_resourceid_' . $j],
                                                "ItemId" => $postParams['iow_' . $i . '_itemid_' . $j], "Qty" => $postParams['iow_' . $i . '_AcceptQty_' . $j],
                                                "POTransId" => $postParams['iow_' . $i . '_potransid_' . $j], "UnitId" => $postParams['unitid_' . $i]));
                                            $IPDTransStatement = $sql->getSqlStringForSqlObject($IPDTransInsert);
                                            $dbAdapter->query($IPDTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                            $IPDProjInsert = $sql->insert('MMS_IPDProjTrans');
                                            $IPDProjInsert->values(array("IPDTransId" => $IPDTransId, "Status" => $status, "ResourceId" => $postParams['iow_' . $i . '_resourceid_' . $j],
                                                "ItemId" => $postParams['iow_' . $i . '_itemid_' . $j], "Qty" => $postParams['iow_' . $i . '_AcceptQty_' . $j],
                                                "POProjTransId" => $postParams['iow_' . $i . '_poprojtransid_' . $j], "DCProjTransId" => $DCTransId, "UnitId" => $postParams['unitid_' . $i]));
                                            $IPDProjStatement = $sql->getSqlStringForSqlObject($IPDProjInsert);
                                            $dbAdapter->query($IPDProjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                            $POTransUpdate = $sql->update();
                                            $POTransUpdate->table('MMS_POTrans');
                                            $POTransUpdate->set(array(

                                                'DCQty' => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_SupplierMIN_' . $j],'number'). ''),
                                                'AcceptQty' => new Expression('AcceptQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j],'number') . ''),
                                                'RejectQty' => new Expression('RejectQty+'  . $this->bsf->isNullCheck($postParams['iow_' . $i . '_RejectQty_' . $j],'number') . ''),
                                                'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j],'number') . '')
                                                //$this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rejectqty_' . $k . ''],'number')
                                            ));
                                            $POTransUpdate->where(array("POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_potransid_' . $j],'number')));
                                            $POTransStatement = $sql->getSqlStringForSqlObject($POTransUpdate);
                                            $dbAdapter->query($POTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $POProjTransUpdate = $sql->update();
                                            $POProjTransUpdate->table('MMS_POProjTrans');
                                            $POProjTransUpdate->set(array(

                                                'DCQty' => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_SupplierMIN_' . $j],'number') . ''),
                                                'AcceptQty' => new Expression('AcceptQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j],'number') . ''),
                                                'RejectQty' => new Expression('RejectQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_RejectQty_' . $j],'number') . ''),
                                                'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j],'number') . '')

                                            ));
                                            $POProjTransUpdate->where(array("POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_potransid_' . $j],'number')));
                                            $POProjTransStatement = $sql->getSqlStringForSqlObject($POProjTransUpdate);
                                            $dbAdapter->query($POProjTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        }

                                        $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                        if ($wbsTotal > 0) {
                                            for ($k = 1; $k <= $wbsTotal; $k++) {

                                                if ($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''] || $postParams['iow_' . $i . '_request_' . $j . '_rejectqty_' . $k . ''] || $postParams['iow_' . $i . '_request_' . $j . '_suppliermin_' . $k . ''] > 0) {

                                                    $DCAnalInsert = $sql->insert('MMS_DCAnalTrans');
                                                    $DCAnalInsert->values(array("DCGroupId" => $DCGroupId, "DCTransId" => $DCTransId,
                                                        "AnalysisId" => $postParams['iow_' . $i . '_request_' . $j . '_wbsid_' . $k . ''],
                                                        "ResourceId" => $postParams['iow_' . $i . '_request_' . $j . '_resourceid_' . $k . ''],
                                                        "ItemId" => $postParams['iow_' . $i . '_request_' . $j . '_itemid_' . $k . ''],
                                                        'DCQty'=> $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_suppliermin_' . $k . ''], 'number'),
                                                        "BalQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''], 'number'),
                                                        "AcceptQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''],'number'),
                                                        "RejectQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rejectqty_' . $k . ''],'number')
                                                    ));
                                                    $DCAnalStatement = $sql->getSqlStringForSqlObject($DCAnalInsert);
                                                    $dbAdapter->query($DCAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $DCAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                    $IPDAnalInsert = $sql->insert('MMS_IPDAnalTrans');
                                                    $IPDAnalInsert->values(array("DCAHTransId" => $DCAnalTransId, "POAHTransId" => $postParams['iow_' . $i . '_request_' . $j . '_poanaltransid_' . $k . ''],
                                                        "Status" => $status,
                                                        "IPDProjTransId" => $IPDProjTransId,
                                                        "AnalysisId" => $postParams['iow_' . $i . '_request_' . $j . '_wbsid_' . $k . ''],
                                                        "ResourceId" => $postParams['iow_' . $i . '_request_' . $j . '_resourceid_' . $k . ''],
                                                        "ItemId" => $postParams['iow_' . $i . '_request_' . $j . '_itemid_' . $k . ''],
                                                        "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''],'number')
                                                    ));
                                                    $IPDAnalStatement = $sql->getSqlStringForSqlObject($IPDAnalInsert);
                                                    $dbAdapter->query($IPDAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    $iptAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                                    $POAnalTransUpdate = $sql->update();
                                                    $POAnalTransUpdate->table('MMS_POAnalTrans');
                                                    $POAnalTransUpdate->set(array('DCQty' => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_suppliermin_' . $k . ''], 'number') . ''),
                                                        'AcceptQty' => new Expression('AcceptQty+' . $this->bsf->isNullCheck( $postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''],'number') . ''),
                                                        'RejectQty' => new Expression('RejectQty+' . $this->bsf->isNullCheck( $postParams['iow_' . $i . '_request_' . $j . '_rejectqty_' . $k . ''], 'number') . ''),
                                                        'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck( $postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''], 'number') . '')));
                                                    $POAnalTransUpdate->where(array("POAnalTransId" => $postParams['iow_' . $i . '_request_' . $j . '_poanaltransid_' . $k . '']));
                                                    $POAnalTransStatement = $sql->getSqlStringForSqlObject($POAnalTransUpdate);
                                                    $dbAdapter->query($POAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                    //warehouse insert-add
                                                    $wareTotal = $postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_wrowid'];
                                                    for ($w = 1; $w <= $wareTotal; $w++) {
                                                        if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w], 'number') > 0) {
                                                            $whInsert = $sql->insert('MMS_DCWareHouseWbsTrans');
                                                            $whInsert->values(array("DCGroupId" => $DCGroupId, "DCAnalTransId" => $DCAnalTransId,
                                                                "IPDAnalTransId" => $iptAnalTransId,
                                                                "POAnalTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_poanaltransid_' . $k . ''], 'number' . ''),
                                                                "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_warehouseid_' . $w . ''], 'number' . ''),
                                                                "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w . ''], 'number' . '')));
                                                            $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                            $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        }
                                                    }
                                                }
                                            } //end of k loop

                                        } else{
                                            $warehouseTotal = $postParams['wh_' . $i . '_po_' . $j . '_wrowid'];
                                            for ($wa = 1; $wa <= $warehouseTotal; $wa++) {
                                                if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $wa], 'number') > 0) {
                                                    $pwhInsert = $sql->insert('MMS_DCWareHousePoTrans');
                                                    $pwhInsert->values(array("DCTransId" => $DCTransId, "DCGroupId" => $DCGroupId,
                                                        "IPDTransId" => $IPDTransId, "POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_potransid_' . $j], 'number' . ''),
                                                        "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $wa . ''], 'number' . ''),
                                                        "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $wa . ''], 'number' . '')));
                                                    $pwhInsertStatement = $sql->getSqlStringForSqlObject($pwhInsert);
                                                    $dbAdapter->query($pwhInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    } //end of j loop

                                    $select = $sql->select();
                                    $select->from(array("a" => "MMS_DCWareHousePoTrans"))
                                        ->columns(array("Qty","WareHouseId","DCGroupId"))
                                        ->where(array("DCGroupId" => $DCGroupId ));
                                    $select->group(array("a.Qty","a.WareHouseId","a.DCGroupId"));

                                    $select1 = $sql->select();
                                    $select1->from(array("b" => "MMS_DCWareHouseWbsTrans"))
                                        ->columns(array("Qty","WareHouseId","DCGroupId"))
                                        ->where(array("DCGroupId" => $DCGroupId));
                                    $select1->group(array("b.Qty","b.WareHouseId","b.DCGroupId"));
                                    $select1->combine($select, 'Union All');

                                    $fselect = $sql->select();
                                    $fselect->from(array("G" => $select1))
                                        ->columns(array(new Expression("SUM(G.Qty) as Qty, G.WareHouseId as WareHouseId, G.DCGroupId as DCGroupId")));
                                    $fselect->group(array("G.WareHouseId","G.DCGroupId"));
                                    $statement = $sql->getSqlStringForSqlObject($fselect);
                                    $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    foreach($ware As $wareData){
                                        if( $wareData['Qty'] > 0){
                                            $whInsert = $sql->insert('MMS_DCWareHouseTrans');
                                            $whInsert->values(array("DCGroupId" => $wareData['DCGroupId'],
                                                "WareHouseId" => $wareData['WareHouseId'],
                                                "DCQty" => $wareData['Qty'],
                                                "CostCentreId" => $CostCentre
                                            ));
                                            $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                            $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }

                                        $stockSelect = $sql->select();
                                        $stockSelect->from(array("a" => "mms_stockTrans"))
                                            ->columns(array("StockId"))
                                            ->where(array("WareHouseId" => $wareData['WareHouseId'],"StockId" => $esId ));
                                        $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                        $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if (count($sId['StockId']) > 0) {
                                            $sUpdate = $sql->update();
                                            $sUpdate->table('mms_stockTrans');
                                            $sUpdate->set(array(
                                                "DCQty" => new Expression('DCQty+' . $wareData['Qty'] . ''),
                                                "ClosingStock" => new Expression('ClosingStock+' . $wareData['Qty'] . '')
                                            ));
                                            $sUpdate->where(array("StockId" => $sId['StockId']));
                                            $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                        else
                                        {
                                            if($wareData['Qty'] > 0 ) {
                                                $stock1 = $sql->insert('mms_stockTrans');
                                                $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                    "StockId" => $esId,
                                                    "DCQty" => $wareData['Qty'],
                                                    "ClosingStock" => $wareData['Qty']
                                                ));
                                                $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    } // end of warehouse-stocktrans-edit

                                } //end of count j

                            } //end of i loop

                        }//end of count i

                        //TRIPSHEET UPDATE
                        if($VendorId !=0){
                            if(isset($vehicleId) && $vehicleId !=""){
                                $insert = $sql->insert("mms_dctripsheet");
                                $insert->values(array(
                                    'DCRegisterId' => $dcid,
                                    'VehicleId' => $vehicleId,
                                    'VendorId' => $VendorId,
                                    'BLLen' => $bLength,
                                    'BLBreadth' => $bBreadth,
                                    'BLHeight' => $bHeight,
                                    'BLQty' => $bQty,
                                    'TSMAXLen' => $tMaxLength,
                                    'TSMAXBreadth' => $tMaxBreadth,
                                    'TSMAXHeight' => $tMaxHeight,
                                    'TSMAXQty' => $tMaxQty,
                                    'TSMinLen' => $tMinLength,
                                    'TSMinBreadth' => $tMinBreadth,
                                    'TSMinHeight' => $tMinHeight,
                                    'TSMinQty' => $tMinQty,
                                    'Total1' => $bctotal,
                                    'Total2' => $perQty,
                                    'NetTotal' => $netQty,
                                    'Remarks' => $remarks
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        //update dctrans rate from po -edit
                        $select = $sql->select();
                        $select->from(array("a" => "MMS_POTrans"))
                            ->columns(array( new Expression("a.PoTransId ,a.Rate,a.QRate,
                                    a.GrossRate As GrossRate,b.DCTransId,b.AcceptQty")))
                            ->join(array("b"=> "MMS_DCTrans"), "a.PoTransId=b.POTransId", array(), $select::JOIN_LEFT)
                            ->where(array("b.DCRegisterId" => $dcid));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $selpo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($selpo as $prePo) {

                            $DCTransupdate = $sql->update();
                            $DCTransupdate->table('MMS_DCTrans');
                            $DCTransupdate->set(array(
                                "Rate" => $prePo['Rate'],
                                "QRate" => $prePo['QRate'],
                                "GrossRate" => $prePo['GrossRate'],
                                "Amount" =>($prePo['Rate']* $prePo['AcceptQty']),
                                "QAmount" =>($prePo['QRate']* $prePo['AcceptQty']),
                                "GrossAmount" =>($prePo['GrossRate']* $prePo['AcceptQty'])
                            ));
                            $DCTransupdate->where(array("POTransId" =>$prePo['PoTransId']));
                            $DCTransStatement = $sql->getSqlStringForSqlObject($DCTransupdate);
                            $dbAdapter->query($DCTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //DCQualTrans - edit mode

                        $del = $sql->delete();
                        $del->from('MMS_DCQualTrans')
                            ->where(array("DCRegisterId" => $dcid ));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $select = $sql->select();
                        $select->from(array("a" => "MMS_DCTrans"))
                            ->columns(array( new Expression("a.DCRegisterId ,a.POTransId,a.Rate,a.QRate,
                                    a.GrossRate As GrossRate,a.DCTransId")))
                            ->where(array("a.DCRegisterId = $dcid and a.POTransId > 0"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $selDC = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($selDC) > 0){
                            foreach($selDC As $selDCData){

                                $iDCRegId = $this->bsf->isNullCheck($selDCData['DCRegisterId'], 'number');
                                $iDCTransId  = $this->bsf->isNullCheck($selDCData['DCTransId'], 'number');
                                $iPOTransId  = $this->bsf->isNullCheck($selDCData['POTransId'], 'number');
                                $dRate  =  $this->bsf->isNullCheck($selDCData['Rate'], 'number');
                                $dQRate   = $this->bsf->isNullCheck($selDCData['QRate'], 'number');
                                $dGRate   = $this->bsf->isNullCheck($selDCData['GrossRate'], 'number');

                                $select = $sql->select();
                                $select->from(array("a" => "MMS_POQualTrans"))
                                    ->columns(array( new Expression("QualifierId,ResourceId,ItemId,YesNo,Expression,
                                ExpPer,TaxablePer,TaxPer,Sign,SurCharge,EDCess,HEDCess,NetPer,ExpressionAmt,
                                TaxableAmt,SurChargeAmt,EDCessAmt,HEDCessAmt,NetAmt,SortId")))
                                    ->where(array("POTransId IN (Select POTransId From MMS_POTrans Where POTransId = $iPOTransId )"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $selPOQual = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if(count($selPOQual) > 0){

                                    foreach($selPOQual As $selDCQData){
                                        //insert DCQualtrans
                                        $dcqTransInsert = $sql->insert('MMS_DCQualTrans');
                                        $dcqTransInsert->values(array(
                                            "DCRegisterId" => $iDCRegId,
                                            "DCTransId" => $iDCTransId,
                                            "ResourceId" => $this->bsf->isNullCheck($selDCQData['ResourceId'], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($selDCQData['ItemId'], 'number'),
                                            "QualifierId" => $this->bsf->isNullCheck($selDCQData['QualifierId'], 'number'),
                                            "YesNo" => $this->bsf->isNullCheck($selDCQData['YesNo'], 'number'),
                                            "Expression" => $this->bsf->isNullCheck($selDCQData['Expression'], 'number'),
                                            "ExpPer" => $this->bsf->isNullCheck($selDCQData['ExpPer'], 'number'),
                                            "TaxablePer" => $this->bsf->isNullCheck($selDCQData['TaxablePer'], 'number'),
                                            "TaxPer" => $this->bsf->isNullCheck($selDCQData['TaxPer'], 'number'),
                                            "Sign" => $this->bsf->isNullCheck($selDCQData['Sign'], 'number'),
                                            "SurCharge" => $this->bsf->isNullCheck($selDCQData['SurCharge'], 'number'),
                                            "EDCess" => $this->bsf->isNullCheck($selDCQData['EDCess'], 'number'),
                                            "HEDCess" => $this->bsf->isNullCheck($selDCQData['HEDCess'], 'number'),
                                            "NetPer" => $this->bsf->isNullCheck($selDCQData['NetPer'], 'number'),
                                            "ExpressionAmt" => $this->bsf->isNullCheck($selDCQData['ExpressionAmt'], 'number'),
                                            "TaxableAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                            "TaxAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                            "SurChargeAmt" => $this->bsf->isNullCheck($selDCQData['SurChargeAmt'], 'number'),
                                            "EDCessAmt" => $this->bsf->isNullCheck($selDCQData['EDCessAmt'], 'number'),
                                            "HEDCessAmt" => $this->bsf->isNullCheck($selDCQData['HEDCessAmt'], 'number'),
                                            "NetAmt" => $this->bsf->isNullCheck($selDCQData['NetAmt'], 'number'),
                                            "SortId" => $this->bsf->isNullCheck($selDCQData['SortId'], 'number'),
                                            "AccountId" => $this->bsf->isNullCheck($selDCQData['AccountId'], 'number'),

                                        ));
                                        $dcqTransStatement = $sql->getSqlStringForSqlObject($dcqTransInsert);
                                        $dbAdapter->query($dcqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                                        $select = $sql->select();
                                        $select->from(array("a" => "MMS_POTrans"))
                                            ->columns(array( new Expression("APOTransId")))
                                            ->where(array("a.POTransId = $iPOTransId and a.APOTransId > 0"));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $selPOTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        if(count($selPOTrans) > 0 ){

                                            $iAPOTransId = 0;
                                            $iQualId = 0;

                                            foreach($selPOTrans as $selPOTData){
                                                $iAPOTransId = $this->bsf->isNullCheck($selPOTData['APOTransId'], 'number');
                                                $iQualId  = $this->bsf->isNullCheck($selDCQData['QualifierId'], 'number');

                                                do {
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_DCTrans"))
                                                        ->columns(array( new Expression("DCTransId,DCRegisterId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.BillQty=0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selDCTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                    $iDCId = 0;
                                                    $iDCRId = 0;

                                                    foreach($selDCTrans as $selDCTransData){

                                                        $iDCId = $this->bsf->isNullCheck($selDCTransData['DCTransId'], 'number');
                                                        $iDCRId = $this->bsf->isNullCheck($selDCTransData['DCRegisterId'], 'number');

                                                        $select = $sql->select();
                                                        $select->from(array("a" => "MMS_DCQualTrans"))
                                                            ->columns(array( new Expression("TransId,DCRegisterId,DCTransId,ResourceId,ItemId,QualifierId,YesNo,Expression,
                                                                   ExpPer,TaxablePer,TaxPer,Sign,SurCharge,EDCess,HEDCess,NetPer,ExpressionAmt,TaxableAmt,TaxAmt,SurChargeAmt,
                                                                   EDCessAmt,HEDCessAmt,NetAmt,SortId,AccountId")))
                                                            ->where(array("a.DCTransId IN (Select DCTransId From DCTrans Where DCTransId= $iDCId and
                                                            DCRegisterId = $iDCRId and BillQty=0 ) and a.QualifierId= $iQualId "));
                                                        $statement = $sql->getSqlStringForSqlObject($select);
                                                        $selDCQTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                        if(count($selDCQTrans) > 0 ){

                                                            $dcQTransId = 0;
                                                            $dcQRegId = 0;
                                                            $dcQDctId = 0;
                                                            foreach($selDCQTrans as $selDCQTData){

                                                                $dcQTransId = $this->bsf->isNullCheck($selDCQTData['TransId'], 'number');
                                                                $dcQRegId = $this->bsf->isNullCheck($selDCQTData['DCRegisterId'], 'number');
                                                                $dcQDctId = $this->bsf->isNullCheck($selDCQTData['DCTransId'], 'number');


                                                                $dcqTransUpdate = $sql->update();
                                                                $dcqTransUpdate->table('MMS_DCQualTrans');
                                                                $dcqTransUpdate->set(array(
                                                                    "YesNo" => $this->bsf->isNullCheck($selDCQTData['YesNo'], 'number'),
                                                                    "Expression" => $this->bsf->isNullCheck($selDCQTData['Expression'], 'number'),
                                                                    "ExpPer" => $this->bsf->isNullCheck($selDCQTData['ExpPer'], 'number'),
                                                                    "TaxablePer" => $this->bsf->isNullCheck($selDCQTData['TaxablePer'], 'number'),
                                                                    "TaxPer" => $this->bsf->isNullCheck($selDCQTData['TaxPer'], 'number'),
                                                                    "Sign" => $this->bsf->isNullCheck($selDCQTData['Sign'], 'number'),
                                                                    "SurCharge" => $this->bsf->isNullCheck($selDCQTData['SurCharge'], 'number'),
                                                                    "EDCess" => $this->bsf->isNullCheck($selDCQTData['EDCess'], 'number'),
                                                                    "HEDCess" => $this->bsf->isNullCheck($selDCQTData['HEDCess'], 'number'),
                                                                    "NetPer" => $this->bsf->isNullCheck($selDCQTData['NetPer'], 'number'),
                                                                    "ExpressionAmt" => $this->bsf->isNullCheck($selDCQTData['ExpressionAmt'], 'number'),
                                                                    "TaxableAmt" => $this->bsf->isNullCheck($selDCQTData['TaxableAmt'], 'number'),
                                                                    "TaxAmt" => $this->bsf->isNullCheck($selDCQTData['TaxAmt'], 'number'),
                                                                    "SurChargeAmt" => $this->bsf->isNullCheck($selDCQTData['SurChargeAmt'], 'number'),
                                                                    "EDCessAmt" => $this->bsf->isNullCheck($selDCQTData['EDCessAmt'], 'number'),
                                                                    "HEDCessAmt" => $this->bsf->isNullCheck($selDCQTData['HEDCessAmt'], 'number'),
                                                                    "NetAmt" => $this->bsf->isNullCheck($selDCQTData['NetAmt'], 'number'),
                                                                    "HEDCessAmt" => $this->bsf->isNullCheck($selDCQTData['HEDCessAmt'], 'number'),
                                                                ));
                                                                $dcqTransUpdate->where(array("TransId" => $dcQTransId));
                                                                $dcqTransStatement = $sql->getSqlStringForSqlObject($dcqTransUpdate);
                                                                $dbAdapter->query($dcqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                                                                $dcTransUpdate = $sql->update();
                                                                $dcTransUpdate->table('MMS_DCTrans');
                                                                $dcTransUpdate->set(array(
                                                                    "Rate" => $dRate,
                                                                    "QRate" => $dQRate,
                                                                    "GrossRate" => $dGRate,
                                                                ));
                                                                $dcTransUpdate->where(array("DCTransId = $dcQDctId and DCRegisterId = $dcQRegId "));
                                                                $dcTransStatement = $sql->getSqlStringForSqlObject($dcTransUpdate);
                                                                $dbAdapter->query($dcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $dcTransUpdate1 = $sql->update();
                                                                $dcTransUpdate1 ->table("MMS_DCTrans");
                                                                $dcTransUpdate1 ->set(array( new Expression("Round(AcceptQty * Rate) As Decimal(18,2)) As Amount,
                                                                        Round(AcceptQty * QRate) As Decimal(18,2)) As QRate,
                                                                        Round(AcceptQty * GrossRate) As Decimal(18,2)) As GrossRate"
                                                                )));
                                                                $dcTransUpdate1 ->where(array("DCTransId = $dcQDctId and DCRegisterId = $dcQRegId "));
                                                                $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate1);
                                                                $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $select = $sql->select();
                                                                $select->from(array("a" => "MMS_DCTrans"))
                                                                    ->columns(array( new Expression("SUM(Amount) as Amount,SUM(QAmount) as QAmount,SUM(GrossAmount) as GrossAmount")))
                                                                    ->where(array("DCRegisterId = $dcQRegId"));
                                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                                $selectDCTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                                if(count($selectDCTrans) > 0){
                                                                    $dOAmount = 0;
                                                                    $dOQAmount = 0;
                                                                    $dOGAmount =0;
                                                                    foreach($selectDCTrans as $selectDCTrsData){

                                                                        $dOAmount = $this->bsf->isNullCheck($selectDCTrsData['Amount'], 'number');
                                                                        $dOQAmount = $this->bsf->isNullCheck($selectDCTrsData['QAmount'], 'number');
                                                                        $dOGAmount = $this->bsf->isNullCheck($selectDCTrsData['GrossAmount'], 'number');

                                                                        $update1 = $sql->update();
                                                                        $update1 ->table("MMS_DCRegister");
                                                                        $update1 ->set(array(
                                                                            "Amount" => $dOAmount,
                                                                            "NetAmount" => $dOQAmount,
                                                                            "GrossAmount" => $dOGAmount,
                                                                        ));
                                                                        $update1 ->where(array("DCRegisterId = $dcQRegId "));
                                                                        $statement1 = $sql->getSqlStringForSqlObject($update1);
                                                                        $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    }
                                                                }
                                                            }
                                                        } else {
                                                            //insert DCQualtrans
                                                            $dcqTransInsert1 = $sql->insert('MMS_DCQualTrans');
                                                            $dcqTransInsert1 ->values(array(
                                                                "DCRegisterId" => $iDCRegId,
                                                                "DCTransId" => $iDCTransId,
                                                                "ResourceId" => $this->bsf->isNullCheck($selDCQData['ResourceId'], 'number'),
                                                                "ItemId" => $this->bsf->isNullCheck($selDCQData['ItemId'], 'number'),
                                                                "QualifierId" => $this->bsf->isNullCheck($selDCQData['QualifierId'], 'number'),
                                                                "YesNo" => $this->bsf->isNullCheck($selDCQData['YesNo'], 'number'),
                                                                "Expression" => $this->bsf->isNullCheck($selDCQData['Expression'], 'number'),
                                                                "ExpPer" => $this->bsf->isNullCheck($selDCQData['ExpPer'], 'number'),
                                                                "TaxablePer" => $this->bsf->isNullCheck($selDCQData['TaxablePer'], 'number'),
                                                                "TaxPer" => $this->bsf->isNullCheck($selDCQData['TaxPer'], 'number'),
                                                                "Sign" => $this->bsf->isNullCheck($selDCQData['Sign'], 'number'),
                                                                "SurCharge" => $this->bsf->isNullCheck($selDCQData['SurCharge'], 'number'),
                                                                "EDCess" => $this->bsf->isNullCheck($selDCQData['EDCess'], 'number'),
                                                                "HEDCess" => $this->bsf->isNullCheck($selDCQData['HEDCess'], 'number'),
                                                                "NetPer" => $this->bsf->isNullCheck($selDCQData['NetPer'], 'number'),
                                                                "ExpressionAmt" => $this->bsf->isNullCheck($selDCQData['ExpressionAmt'], 'number'),
                                                                "TaxableAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                                                "TaxAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                                                "SurChargeAmt" => $this->bsf->isNullCheck($selDCQData['SurChargeAmt'], 'number'),
                                                                "EDCessAmt" => $this->bsf->isNullCheck($selDCQData['EDCessAmt'], 'number'),
                                                                "HEDCessAmt" => $this->bsf->isNullCheck($selDCQData['HEDCessAmt'], 'number'),
                                                                "NetAmt" => $this->bsf->isNullCheck($selDCQData['NetAmt'], 'number'),
                                                                "SortId" => $this->bsf->isNullCheck($selDCQData['SortId'], 'number'),
                                                                "AccountId" => $this->bsf->isNullCheck($selDCQData['AccountId'], 'number'),

                                                            ));
                                                            $dcqTransStatement1 = $sql->getSqlStringForSqlObject($dcqTransInsert1);
                                                            $dbAdapter->query($dcqTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);


                                                            $dcTransUpdate1 = $sql->update();
                                                            $dcTransUpdate1 ->table('MMS_DCTrans');
                                                            $dcTransUpdate1 ->set(array(
                                                                "Rate" => $dRate,
                                                                "QRate" => $dQRate,
                                                                "GrossRate" => $dGRate,
                                                            ));
                                                            $dcTransUpdate1 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                            $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate1);
                                                            $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);


                                                            $dcTransUpdate11 = $sql->update();
                                                            $dcTransUpdate11 ->table("MMS_DCTrans");
                                                            $dcTransUpdate11 ->set(array( new Expression("Round(AcceptQty * Rate) As Decimal(18,2)) As Amount,
                                                                        Round(AcceptQty * QRate) As Decimal(18,2)) As QRate,
                                                                        Round(AcceptQty * GrossRate) As Decimal(18,2)) As GrossRate"
                                                            )));
                                                            $dcTransUpdate11 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                            $dcTransStatement11 = $sql->getSqlStringForSqlObject($dcTransUpdate11);
                                                            $dbAdapter->query($dcTransStatement11, $dbAdapter::QUERY_MODE_EXECUTE);

                                                            $select1 = $sql->select();
                                                            $select1 ->from(array("a" => "MMS_DCTrans"))
                                                                ->columns(array( new Expression("SUM(Amount) as Amount,SUM(QAmount) as QAmount,SUM(GrossAmount) as GrossAmount")))
                                                                ->where(array("DCRegisterId = $iDCRId"));
                                                            $statement1 = $sql->getSqlStringForSqlObject($select1);
                                                            $selectDCTrans1 = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                            if(count($selectDCTrans1) > 0) {
                                                                $dOAmount = 0;
                                                                $dOQAmount = 0;
                                                                $dOGAmount = 0;

                                                                foreach ($selectDCTrans1 as $selectDCTransData1) {

                                                                    $dOAmount = $this->bsf->isNullCheck($selectDCTransData1['Amount'], 'number');
                                                                    $dOQAmount = $this->bsf->isNullCheck($selectDCTransData1['QAmount'], 'number');
                                                                    $dOGAmount = $this->bsf->isNullCheck($selectDCTransData1['GrossAmount'], 'number');

                                                                    $update11 = $sql->update();
                                                                    $update11->table("MMS_DCRegister");
                                                                    $update11->set(array(
                                                                        "Amount" => $dOAmount,
                                                                        "NetAmount" => $dOQAmount,
                                                                        "GrossAmount" => $dOGAmount,
                                                                    ));
                                                                    $update11->where(array("DCRegisterId = $iDCRId "));
                                                                    $statement11 = $sql->getSqlStringForSqlObject($update11);
                                                                    $dbAdapter->query($statement11, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                }
                                                            }
                                                        }
                                                    }
                                                    //
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_POTrans"))
                                                        ->columns(array( new Expression("APOTransId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.APOTransId > 0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selPOTrans1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                                    if(count($selPOTrans1) > 0){
                                                        $iAPOTransId = $this->bsf->isNullCheck($selPOTrans1['APOTransId'], 'number');
                                                    } else {
                                                        $iAPOTransId = 0;
                                                    }

                                                } while($iAPOTransId > 0);
                                            }
                                        }
                                    }
                                }
                                else {
                                    $select = $sql->select();
                                    $select->from(array("a" => "MMS_POTrans"))
                                        ->columns(array( new Expression("APOTransId")))
                                        ->where(array("a.POTransId = $iPOTransId "));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $selPOTrans2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    if(count($selPOTrans2) > 0){
                                        $iAPOTransId = 0;

                                        foreach($selPOTrans2 as $selPOTrans2Data ){

                                            $iAPOTransId = $this->bsf->isNullCheck($selPOTrans2Data['APOTransId'], 'number');

                                            if($iAPOTransId > 0){
                                                do{
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_DCTrans"))
                                                        ->columns(array( new Expression("DCTransId,DCRegisterId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.BillQty = 0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selDCTrans2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                    $iDCId = 0;
                                                    $iDCRId = 0;

                                                    foreach($selDCTrans2 as $selDCTrans2Data) {
                                                        $iDCId = $this->bsf->isNullCheck($selPOTrans2Data['DCTransId'], 'number');
                                                        $iDCRId = $this->bsf->isNullCheck($selPOTrans2Data['DCRegisterId'], 'number');


                                                        $dcTransUpdate1 = $sql->update();
                                                        $dcTransUpdate1 ->table('MMS_DCTrans');
                                                        $dcTransUpdate1 ->set(array(
                                                            "Rate" => $dRate,
                                                            "QRate" => $dQRate,
                                                            "GrossRate" => $dGRate,
                                                        ));
                                                        $dcTransUpdate1 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                        $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate1);
                                                        $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        $dcTransUpdate12 = $sql->update();
                                                        $dcTransUpdate12 ->table("MMS_DCTrans");
                                                        $dcTransUpdate12 ->set(array( new Expression("Round(AcceptQty * Rate) As Decimal(18,2)) As Amount,
                                                                        Round(AcceptQty * QRate) As Decimal(18,2)) As QRate,
                                                                        Round(AcceptQty * GrossRate) As Decimal(18,2)) As GrossRate"
                                                        )));
                                                        $dcTransUpdate12 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                        $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate12);
                                                        $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        $select = $sql->select();
                                                        $select->from(array("a" => "MMS_DCTrans"))
                                                            ->columns(array( new Expression("SUM(Amount) as Amount,SUM(QAmount) as QAmount,SUM(GrossAmount) as GrossAmount")))
                                                            ->where(array("DCRegisterId = $iDCRId"));
                                                        $statement = $sql->getSqlStringForSqlObject($select);
                                                        $selectDCTransAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                        if(count($selectDCTransAmt) > 0 ){
                                                            $dOAmount = 0;
                                                            $dOQAmount = 0;
                                                            $dOGAmount = 0;

                                                            foreach($selectDCTransAmt as $selectDCTransAmt1){
                                                                $dOAmount = $this->bsf->isNullCheck($selectDCTransAmt1['Amount'], 'number');
                                                                $dOQAmount = $this->bsf->isNullCheck($selectDCTransAmt1['QAmount'], 'number');
                                                                $dOGAmount = $this->bsf->isNullCheck($selectDCTransAmt1['GrossAmount'], 'number');

                                                                $update12 = $sql->update();
                                                                $update12->table("MMS_DCRegister");
                                                                $update12->set(array(
                                                                    "Amount" => $dOAmount,
                                                                    "NetAmount" => $dOQAmount,
                                                                    "GrossAmount" => $dOGAmount,
                                                                ));
                                                                $update12->where(array("DCRegisterId = $iDCRId "));
                                                                $statement12 = $sql->getSqlStringForSqlObject($update12);
                                                                $dbAdapter->query($statement12, $dbAdapter::QUERY_MODE_EXECUTE);
                                                            }
                                                        }
                                                    }
                                                    //
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_POTrans"))
                                                        ->columns(array( new Expression("APOTransId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.APOTransId > 0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selPOTrans3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                                    if(count($selPOTrans3) > 0){
                                                        $iAPOTransId = $this->bsf->isNullCheck($selPOTrans3['APOTransId'], 'number');
                                                    } else {
                                                        $iAPOTransId = 0;
                                                    }
                                                }while($iAPOTransId > 0);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        // end of dcqualtrans - edit mode
                    } //end of the updating the edit details

                    // starting the add mode
                    else {

                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(303, date('Y/m/d', strtotime($DCdate)), 0, 0, $dbAdapter, "I");
                            $voucherno = $voucher['voucherNo'];
                        } else {
                            $voucherno = $DCNo;
                        }

                        if ($CCMin['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(303, date('Y/m/d', strtotime($DCdate)), 0, $CostCentre, $dbAdapter, "I");
                            $CCDCNo = $voucher['voucherNo'];
                        } else {
                            $CCDCNo = $CCDCNo;
                        }

                        if ($CMin['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(303, date('Y/m/d', strtotime($DCdate)), $CompanyId, 0, $dbAdapter, "I");
                            $CDCNo = $voucher['voucherNo'];
                        } else {
                            $CDCNo = $CDCNo;
                        }

                        //DCRegister
                        $registerInsert = $sql->insert('MMS_DCRegister');
                        $registerInsert->values(array("DCDate" => $DCdate, "CostCentreId" => $CostCentre,
                            "DCNo" => $voucherno, "VendorId" => $VendorId, "SiteDCDate" => $SiteDCDate, "SiteDCNo" => $SiteDCNo,
                            "CCDCNo" => $CCDCNo, "DCOrCSM" => $dc, "CDCNo" => $CDCNo, "RefNo" => $RefNo,
                            "Narration" => $Narration, "GatePassNo" => $GatePassNo,
                            "WithLoad" => $WithLoad, "WithOutLoad" => $WithOutLoad,
                            "MaterialWeigh" => $MaterialWeigh, "IsTested" => $Tested,
                            "AgencyId" => $AgencyId, "TestingMethod" => $TestingMethod,
                            "TestResults" => $TestResults,
                            "GridType" => $gridtype
                        ));
                        $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                        $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $DCRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $dcid = $DCRegisterId;
                        /*File upload*/
                        $fileList = json_decode($postParams['fileList'], true);

                        foreach($fileList as $files){
                            $dir = 'public/uploads/doc_files/';
                            $uploadDir = 'public/uploads/mms/'.$dcid.'/';
                            if(!is_dir($uploadDir))
                                mkdir($uploadDir, 0755, true);

                            copy($dir.$files, $uploadDir.$files);
                            unlink($dir.$files);
                        }
                        /*end of file upload*/

                        /*Quality test file upload*/
                        $qfileList = json_decode($postParams['qualfileList'], true);


                        foreach($qfileList as $qfiles){
                            $dir = 'public/uploads/doc_files/';
                            $uploadDir = 'public/uploads/mms/QT-files/'.$dcid.'/';

                            if(!is_dir($uploadDir))
                                mkdir($uploadDir, 0755, true);
                            $finsert = $sql->insert('MMS_DCQualityTestFilesDetail');
                            $finsert -> values(array("DCRegisterId" => $DCRegisterId,
                                    "DocumentPath" => $uploadDir ));
                            $finsertStatement = $sql->getSqlStringForSqlObject($finsert);
                            $dbAdapter->query($finsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            copy($dir.$qfiles, $uploadDir.$qfiles);
                            unlink($dir.$qfiles);
                        }
                        /*end of qt file upload*/

                        $dcregId = $postParams['rowid'];
                        for ($i = 1; $i < $dcregId; $i++) {
                            if($this->bsf->isNullCheck($postParams['unitid_' . $i], 'number') != '' || $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number') !=0 ) {
                                $ExpiryDate = 'NULL';
                                if ($postParams['RefDate_' . $i] == '' || $postParams['RefDate_' . $i] == null) {
                                    $ExpiryDate = null;
                                } else {
                                    $ExpiryDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['RefDate_' . $i], 'string')));
                                }

                                $DCGroupInsert = $sql->insert('MMS_DCGroupTrans');
                                $DCGroupInsert->values(array("DCRegisterId" => $DCRegisterId, "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                    "ResourceId" =>  $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number' .''), "ItemId" =>  $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'.''),
                                    "CostCentreId" => $this->bsf->isNullCheck($postParams['CostCenterId'],'number' .''), "DCQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'. ''),
                                    "BalQty" => $this->bsf->isNullCheck($postParams['AcceptQty_' . $i],'number'. ''), "AcceptQty" => $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number'. ''),
                                    "RejectQty" => $this->bsf->isNullCheck($postParams['RejectQty_' . $i], 'number'. ''), "Remarks" => $this->bsf->isNullCheck($postParams['Remarks_' . $i], 'string'.''),
                                    "ExpiryDate" => $ExpiryDate));
                                $DCGroupStatement = $sql->getSqlStringForSqlObject($DCGroupInsert);
                                $dbAdapter->query($DCGroupStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $DCGroupId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                // stock details adding
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stock"))
                                    ->columns(array("StockId"))
                                    ->where(array("CostCentreId" => $this->bsf->isNullCheck($postParams['CostCenterId'], 'number' . ''),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number' .''),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number' . '')
                                    ));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                $ssId = $stockselId['StockId'];
                                if (count($ssId) > 0 ) {
                                    $stockUpdate = $sql->update();
                                    $stockUpdate->table('mms_stock');
                                    $stockUpdate->set(array(
                                        "DCQty" => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number') . ''),
                                        "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number') . '')
                                    ));
                                    $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                    $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                    $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                                else
                                {
                                    if($this->bsf->isNullCheck($postParams['AcceptQty_' . $i],'number') != '' ||
                                        $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number') > 0 ) {

                                        $stock = $sql->insert('mms_stock');
                                        $stock->values(array("CostCentreId" => $this->bsf->isNullCheck($postParams['CostCenterId'], 'number'),
                                            "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                            "DCQty" => $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number' . ''),
                                            "ClosingStock" => $this->bsf->isNullCheck($postParams['AcceptQty_' . $i], 'number' . '')
                                        ));
                                        $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                        $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $StockId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    }
                                    $ssId = $StockId;
                                } // end of stock

                            }
                            $dctransTotal = $postParams['iow_' . $i . '_rowid'];

                            for ($j = 1; $j <= $dctransTotal; $j++) {

                                if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number') || $this->bsf->isNullCheck($postParams['iow_' . $i . '_RejectQty_' . $j], 'number')
                                    || $this->bsf->isNullCheck($postParams['iow_' . $i . '_SupplierMIN_' . $j], 'number') > 0) {

                                    $DCTransInsert = $sql->insert('MMS_DCTrans');
                                    $DCTransInsert->values(array("DCGroupId" => $DCGroupId, "DCRegisterId" => $DCRegisterId,
                                        "POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_potransid_' . $j], 'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_resourceid_' . $j], 'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_itemid_' . $j], 'number'),
                                        "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_unitid_' . $j], 'number'),
                                        "DCQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_SupplierMIN_' . $j], 'number', ''),
                                        "BalQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number', ''),
                                        "AcceptQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number', ''),
                                        "RejectQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_RejectQty_' . $j], 'number', '')
                                    ));
                                    $DCTransStatement = $sql->getSqlStringForSqlObject($DCTransInsert);
                                    $dbAdapter->query($DCTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $DCTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $status = "D";
                                    $IPDTransInsert = $sql->insert('MMS_IPDTrans');
                                    $IPDTransInsert->values(array("DCTransId" => $DCTransId, "Status" => $status, "ResourceId" => $postParams['iow_' . $i . '_resourceid_' . $j],
                                        "ItemId" => $postParams['iow_' . $i . '_itemid_' . $j], "Qty" => $postParams['iow_' . $i . '_AcceptQty_' . $j],
                                        "POTransId" => $postParams['iow_' . $i . '_potransid_' . $j], "UnitId" => $postParams['unitid_' . $i]));
                                    $IPDTransStatement = $sql->getSqlStringForSqlObject($IPDTransInsert);
                                    $dbAdapter->query($IPDTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $IPDProjInsert = $sql->insert('MMS_IPDProjTrans');
                                    $IPDProjInsert->values(array("IPDTransId" => $IPDTransId, "Status" => $status, "ResourceId" => $postParams['iow_' . $i . '_resourceid_' . $j],
                                        "ItemId" => $postParams['iow_' . $i . '_itemid_' . $j], "Qty" => $postParams['iow_' . $i . '_AcceptQty_' . $j], "CostCentreId" => $postParams['CostCenterId'],
                                        "POProjTransId" => $postParams['iow_' . $i . '_poprojtransid_' . $j], "DCProjTransId" => $DCTransId, "UnitId" => $postParams['unitid_' . $i]));
                                    $IPDProjStatement = $sql->getSqlStringForSqlObject($IPDProjInsert);
                                    $dbAdapter->query($IPDProjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $POTransUpdate = $sql->update();
                                    $POTransUpdate->table('MMS_POTrans');
                                    $POTransUpdate->set(array('DCQty' => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_SupplierMIN_' . $j], 'number') . ''),
                                        'AcceptQty' => new Expression('AcceptQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number') . ''),
                                        'RejectQty' => new Expression('RejectQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_RejectQty_' . $j], 'number') . ''),
                                        'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number') . '')));
                                    $POTransUpdate->where(array("POTransId" => $postParams['iow_' . $i . '_potransid_' . $j]));
                                    $POTransStatement = $sql->getSqlStringForSqlObject($POTransUpdate);
                                    $dbAdapter->query($POTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $POProjTransUpdate = $sql->update();
                                    $POProjTransUpdate->table('MMS_POProjTrans');
                                    $POProjTransUpdate->set(array('DCQty' => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_SupplierMIN_' . $j], 'number') . ''),
                                        'AcceptQty' => new Expression('AcceptQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number') . ''),
                                        'RejectQty' => new Expression('RejectQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_RejectQty_' . $j], 'number') . ''),
                                        'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_AcceptQty_' . $j], 'number') . '')));
                                    $POProjTransUpdate->where(array("POTransId" => $postParams['iow_' . $i . '_potransid_' . $j]));
                                    $POProjTransStatement = $sql->getSqlStringForSqlObject($POProjTransUpdate);
                                    $dbAdapter->query($POProjTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                if($wbsTotal > 0){
                                    for ($k = 1; $k <= $wbsTotal; $k++) {
                                        if ($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''] || $postParams['iow_' . $i . '_request_' . $j . '_rejectqty_' . $k . ''] || $postParams['iow_' . $i . '_request_' . $j . '_suppliermin_' . $k . ''] > 0) {

                                            $DCAnalInsert = $sql->insert('MMS_DCAnalTrans');
                                            $DCAnalInsert->values(array("DCGroupId" => $DCGroupId, "DCTransId" => $DCTransId, "AnalysisId" => $postParams['iow_' . $i . '_request_' . $j . '_wbsid_' . $k . ''], "ResourceId" => $postParams['iow_' . $i . '_request_' . $j . '_resourceid_' . $k . ''],
                                                "ItemId" => $postParams['iow_' . $i . '_request_' . $j . '_itemid_' . $k . ''],
                                                "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_unitid_' . $k . ''], 'number' . ''),
                                                "DCQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_suppliermin_' . $k . ''], 'number' . ''),
                                                "BalQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''], 'number' . ''),
                                                "AcceptQty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''], 'number' . ''),
                                                "RejectQty" => $this->bsf->isNullCheck($postParams ['iow_' . $i . '_request_' . $j . '_rejectqty_' . $k . ''], 'number' . '')
                                            ));
                                            $DCAnalStatement = $sql->getSqlStringForSqlObject($DCAnalInsert);
                                            $dbAdapter->query($DCAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $DCAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $IPDAnalInsert = $sql->insert('MMS_IPDAnalTrans');
                                            $IPDAnalInsert->values(array("DCAHTransId" => $DCAnalTransId,
                                                "POAHTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_poanaltransid_' . $k . ''], 'number'),
                                                "Status" => $status, "IPDProjTransId" => $IPDProjTransId,
                                                "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_wbsid_' . $k . ''], 'number'),
                                                "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_resourceid_' . $k . ''], 'number'),
                                                "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_itemid_' . $k . ''], 'number'),
                                                "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''], 'number' . '')
                                            ));
                                            $IPDAnalStatement = $sql->getSqlStringForSqlObject($IPDAnalInsert);
                                            $dbAdapter->query($IPDAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $iptAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            $POAnalTransUpdate = $sql->update();
                                            $POAnalTransUpdate->table('MMS_POAnalTrans');
                                            $POAnalTransUpdate->set(array('DCQty' => new Expression('DCQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_suppliermin_' . $k . ''], 'number') . ''),
                                                'AcceptQty' => new Expression('AcceptQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''], 'number') . ''),
                                                'RejectQty' => new Expression('RejectQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rejectqty_' . $k . ''], 'number') . ''),
                                                'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_acceptqty_' . $k . ''], 'number' . ''))));
                                            $POAnalTransUpdate->where(array("POAnalTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_poanaltransid_' . $k . ''], 'number' . '')));
                                            $POAnalTransStatement = $sql->getSqlStringForSqlObject($POAnalTransUpdate);
                                            //$dbAdapter->query($POAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE)
                                            $dbAdapter->query($POAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            //warehouse insert-add
                                            $wareTotal = $postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_wrowid'];
                                            for ($w = 1; $w <= $wareTotal; $w++) {
                                                if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w], 'number') > 0) {
                                                    $whInsert = $sql->insert('MMS_DCWareHouseWbsTrans');
                                                    $whInsert->values(array("DCGroupId" => $DCGroupId, "DCAnalTransId" => $DCAnalTransId,
                                                        "IPDAnalTransId" => $iptAnalTransId,
                                                        "POAnalTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_poanaltransid_' . $k . ''], 'number' . ''),
                                                        "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_warehouseid_' . $w . ''], 'number' . ''),
                                                        "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w . ''], 'number' . '')));
                                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    } //for k loop
                                } else{
                                    $warehouseTotal = $postParams['wh_' . $i . '_po_' . $j . '_wrowid'];
                                    for ($wa = 1; $wa <= $warehouseTotal; $wa++) {
                                        if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $wa], 'number') > 0) {
                                            $pwhInsert = $sql->insert('MMS_DCWareHousePoTrans');
                                            $pwhInsert->values(array("DCTransId" => $DCTransId, "DCGroupId" => $DCGroupId,
                                                "IPDTransId" => $IPDTransId, "POTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_potransid_' . $j], 'number' . ''),
                                                "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $wa . ''], 'number' . ''),
                                                "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $wa . ''], 'number' . '')));
                                            $pwhInsertStatement = $sql->getSqlStringForSqlObject($pwhInsert);
                                            $dbAdapter->query($pwhInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            } //for j loop
                            $select = $sql->select();
                            $select->from(array("a" => "MMS_DCWareHousePoTrans"))
                                ->columns(array("Qty","WareHouseId","DCGroupId"))
                                ->where(array("DCGroupId" => $DCGroupId ));
                            $select->group(array("a.Qty","a.WareHouseId","a.DCGroupId"));

                            $select1 = $sql->select();
                            $select1->from(array("b" => "MMS_DCWareHouseWbsTrans"))
                                ->columns(array("Qty","WareHouseId","DCGroupId"))
                                ->where(array("DCGroupId" => $DCGroupId));
                            $select1->group(array("b.Qty","b.WareHouseId","b.DCGroupId"));
                            $select1->combine($select, 'Union All');

                            $fselect = $sql->select();
                            $fselect->from(array("G" => $select1))
                                ->columns(array(new Expression("SUM(G.Qty) as Qty, G.WareHouseId as WareHouseId, G.DCGroupId as DCGroupId")));
                            $fselect->group(array("G.WareHouseId","G.DCGroupId"));
                            $statement = $sql->getSqlStringForSqlObject($fselect);
                            $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach($ware As $wareData) {
                                if($wareData['Qty'] > 0 ) {
                                    $whInsert = $sql->insert('MMS_DCWareHouseTrans');
                                    $whInsert->values(array("DCGroupId" => $wareData['DCGroupId'],
                                        "WareHouseId" => $wareData['WareHouseId'],
                                        "DCQty" => $wareData['Qty'],
                                        "CostCentreId" => $CostCentre
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                                    $stockSelect = $sql->select();
                                    $stockSelect->from(array("a" => "mms_stockTrans"))
                                        ->columns(array("StockId"))
                                        ->where(array("WareHouseId" => $wareData['WareHouseId'], "StockId" => $ssId));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                    $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                    if (count($sId['StockId']) > 0) {

                                        $sUpdate = $sql->update();
                                        $sUpdate->table('mms_stockTrans');
                                        $sUpdate->set(array(
                                            "DCQty" => new Expression('DCQty+' . $wareData['Qty'] . ''),
                                            "ClosingStock" => new Expression('ClosingStock+' . $wareData['Qty'] . '')
                                        ));
                                        $sUpdate->where(array("StockId" => $sId['StockId']));
                                        $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                        $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    } else {
                                        if ($wareData['Qty'] > 0) {
                                            $stock1 = $sql->insert('mms_stockTrans');
                                            $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                "StockId" => $ssId,
                                                "DCQty" => $wareData['Qty'],
                                                "ClosingStock" => $wareData['Qty']
                                            ));
                                            $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                            $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                }
                            }
                        } //for loop end

                        //tripsheet - vendor(vehicle) insert - add mode
                        if($VendorId !=0){
                            if(isset($vehicleId) && $vehicleId !=""){
                                $insert = $sql->insert("mms_dctripsheet");
                                $insert->values(array(
                                    'DCRegisterId' => $DCRegisterId,
                                    'VehicleId' => $vehicleId,
                                    'VendorId' => $VendorId,
                                    'BLLen' => $bLength,
                                    'BLBreadth' => $bBreadth,
                                    'BLHeight' => $bHeight,
                                    'BLQty' => $bQty,
                                    'TSMAXLen' => $tMaxLength,
                                    'TSMAXBreadth' => $tMaxBreadth,
                                    'TSMAXHeight' => $tMaxHeight,
                                    'TSMAXQty' => $tMaxQty,
                                    'TSMinLen' => $tMinLength,
                                    'TSMinBreadth' => $tMinBreadth,
                                    'TSMinHeight' => $tMinHeight,
                                    'TSMinQty' => $tMinQty,
                                    'Total1' => $bctotal,
                                    'Total2' => $perQty,
                                    'NetTotal' => $netQty,
                                    'Remarks' => $remarks
                                ));
                                $statement = $sql->getSqlStringForSqlObject($insert);
                                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        //update dctrans rate from po
                        $select = $sql->select();
                        $select->from(array("a" => "MMS_POTrans"))
                            ->columns(array( new Expression("a.PoTransId ,a.Rate,a.QRate,
                                    a.GrossRate As GrossRate,b.DCTransId,b.AcceptQty")))
                            ->join(array("b"=> "MMS_DCTrans"), "a.PoTransId=b.POTransId", array(), $select::JOIN_LEFT)
                            ->where(array("b.DCRegisterId" => $dcid));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $selpo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($selpo as $prePo) {

                            $DCTransupdate = $sql->update();
                            $DCTransupdate->table('MMS_DCTrans');
                            $DCTransupdate->set(array(
                                "Rate" => $prePo['Rate'],
                                "QRate" => $prePo['QRate'],
                                "GrossRate" => $prePo['GrossRate'],
                                "Amount" =>($prePo['Rate']* $prePo['AcceptQty']),
                                "QAmount" =>($prePo['QRate']* $prePo['AcceptQty']),
                                "GrossAmount" =>($prePo['GrossRate']* $prePo['AcceptQty'])
                            ));
                            $DCTransupdate->where(array("POTransId" =>$prePo['PoTransId']));
                            $DCTransStatement = $sql->getSqlStringForSqlObject($DCTransupdate);
                            $dbAdapter->query($DCTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //DCQualTrans

                        $del = $sql->delete();
                        $del->from('MMS_DCQualTrans')
                            ->where(array("DCRegisterId" => $dcid ));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $select = $sql->select();
                        $select->from(array("a" => "MMS_DCTrans"))
                            ->columns(array( new Expression("a.DCRegisterId ,a.POTransId,a.Rate,a.QRate,
                                    a.GrossRate As GrossRate,a.DCTransId")))
                            ->where(array("a.DCRegisterId = $dcid and a.POTransId > 0"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $selDC = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($selDC) > 0){
                            foreach($selDC As $selDCData){

                                $iDCRegId = $this->bsf->isNullCheck($selDCData['DCRegisterId'], 'number');
                                $iDCTransId  = $this->bsf->isNullCheck($selDCData['DCTransId'], 'number');
                                $iPOTransId  = $this->bsf->isNullCheck($selDCData['POTransId'], 'number');
                                $dRate  =  $this->bsf->isNullCheck($selDCData['Rate'], 'number');
                                $dQRate   = $this->bsf->isNullCheck($selDCData['QRate'], 'number');
                                $dGRate   = $this->bsf->isNullCheck($selDCData['GrossRate'], 'number');

                                $select = $sql->select();
                                $select->from(array("a" => "MMS_POQualTrans"))
                                    ->columns(array( new Expression("QualifierId,ResourceId,ItemId,YesNo,Expression,
                                ExpPer,TaxablePer,TaxPer,Sign,SurCharge,EDCess,HEDCess,NetPer,ExpressionAmt,
                                TaxableAmt,SurChargeAmt,EDCessAmt,HEDCessAmt,NetAmt,SortId")))
                                    ->where(array("POTransId IN (Select POTransId From MMS_POTrans Where POTransId = $iPOTransId )"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $selPOQual = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if(count($selPOQual) > 0){

                                    foreach($selPOQual As $selDCQData){
                                        //insert DCQualtrans
                                        $dcqTransInsert = $sql->insert('MMS_DCQualTrans');
                                        $dcqTransInsert->values(array(
                                            "DCRegisterId" => $iDCRegId,
                                            "DCTransId" => $iDCTransId,
                                            "ResourceId" => $this->bsf->isNullCheck($selDCQData['ResourceId'], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($selDCQData['ItemId'], 'number'),
                                            "QualifierId" => $this->bsf->isNullCheck($selDCQData['QualifierId'], 'number'),
                                            "YesNo" => $this->bsf->isNullCheck($selDCQData['YesNo'], 'number'),
                                            "Expression" => $this->bsf->isNullCheck($selDCQData['Expression'], 'string'),
                                            "ExpPer" => $this->bsf->isNullCheck($selDCQData['ExpPer'], 'number'),
                                            "TaxablePer" => $this->bsf->isNullCheck($selDCQData['TaxablePer'], 'number'),
                                            "TaxPer" => $this->bsf->isNullCheck($selDCQData['TaxPer'], 'number'),
                                            "Sign" => $this->bsf->isNullCheck($selDCQData['Sign'], 'string'),
                                            "SurCharge" => $this->bsf->isNullCheck($selDCQData['SurCharge'], 'number'),
                                            "EDCess" => $this->bsf->isNullCheck($selDCQData['EDCess'], 'number'),
                                            "HEDCess" => $this->bsf->isNullCheck($selDCQData['HEDCess'], 'number'),
                                            "NetPer" => $this->bsf->isNullCheck($selDCQData['NetPer'], 'number'),
                                            "ExpressionAmt" => $this->bsf->isNullCheck($selDCQData['ExpressionAmt'], 'number'),
                                            "TaxableAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                            "TaxAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                            "SurChargeAmt" => $this->bsf->isNullCheck($selDCQData['SurChargeAmt'], 'number'),
                                            "EDCessAmt" => $this->bsf->isNullCheck($selDCQData['EDCessAmt'], 'number'),
                                            "HEDCessAmt" => $this->bsf->isNullCheck($selDCQData['HEDCessAmt'], 'number'),
                                            "NetAmt" => $this->bsf->isNullCheck($selDCQData['NetAmt'], 'number'),
                                            "SortId" => $this->bsf->isNullCheck($selDCQData['SortId'], 'number'),
                                            "AccountId" => $this->bsf->isNullCheck($selDCQData['AccountId'], 'number')

                                        ));
                                        $dcqTransStatement = $sql->getSqlStringForSqlObject($dcqTransInsert);
                                        $dbAdapter->query($dcqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                                        $select = $sql->select();
                                        $select->from(array("a" => "MMS_POTrans"))
                                            ->columns(array( new Expression("APOTransId")))
                                            ->where(array("a.POTransId = $iPOTransId and a.APOTransId > 0"));
                                        $statement = $sql->getSqlStringForSqlObject($select);
                                        $selPOTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        if(count($selPOTrans) > 0 ){

                                            $iAPOTransId = 0;
                                            $iQualId = 0;

                                            foreach($selPOTrans as $selPOTData){
                                                $iAPOTransId = $this->bsf->isNullCheck($selPOTData['APOTransId'], 'number');
                                                $iQualId  = $this->bsf->isNullCheck($selDCQData['QualifierId'], 'number');

                                                do {
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_DCTrans"))
                                                        ->columns(array( new Expression("DCTransId,DCRegisterId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.BillQty=0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selDCTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                    $iDCId = 0;
                                                    $iDCRId = 0;

                                                    foreach($selDCTrans as $selDCTransData){

                                                        $iDCId = $this->bsf->isNullCheck($selDCTransData['DCTransId'], 'number');
                                                        $iDCRId = $this->bsf->isNullCheck($selDCTransData['DCRegisterId'], 'number');

                                                        $select = $sql->select();
                                                        $select->from(array("a" => "MMS_DCQualTrans"))
                                                            ->columns(array( new Expression("TransId,DCRegisterId,DCTransId,ResourceId,ItemId,QualifierId,YesNo,Expression,
                                                                   ExpPer,TaxablePer,TaxPer,Sign,SurCharge,EDCess,HEDCess,NetPer,ExpressionAmt,TaxableAmt,TaxAmt,SurChargeAmt,
                                                                   EDCessAmt,HEDCessAmt,NetAmt,SortId,AccountId")))
                                                            ->where(array("a.DCTransId IN (Select DCTransId From DCTrans Where DCTransId= $iDCId and
                                                            DCRegisterId = $iDCRId and BillQty=0 ) and a.QualifierId= $iQualId "));
                                                        $statement = $sql->getSqlStringForSqlObject($select);
                                                        $selDCQTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                        if(count($selDCQTrans) > 0 ){

                                                            $dcQTransId = 0;
                                                            $dcQRegId = 0;
                                                            $dcQDctId = 0;
                                                            foreach($selDCQTrans as $selDCQTData){

                                                                $dcQTransId = $this->bsf->isNullCheck($selDCQTData['TransId'], 'number');
                                                                $dcQRegId = $this->bsf->isNullCheck($selDCQTData['DCRegisterId'], 'number');
                                                                $dcQDctId = $this->bsf->isNullCheck($selDCQTData['DCTransId'], 'number');


                                                                $dcqTransUpdate = $sql->update();
                                                                $dcqTransUpdate->table('MMS_DCQualTrans');
                                                                $dcqTransUpdate->set(array(
                                                                    "YesNo" => $this->bsf->isNullCheck($selDCQTData['YesNo'], 'number'),
                                                                    "Expression" => $this->bsf->isNullCheck($selDCQTData['Expression'], 'string'),
                                                                    "ExpPer" => $this->bsf->isNullCheck($selDCQTData['ExpPer'], 'number'),
                                                                    "TaxablePer" => $this->bsf->isNullCheck($selDCQTData['TaxablePer'], 'number'),
                                                                    "TaxPer" => $this->bsf->isNullCheck($selDCQTData['TaxPer'], 'number'),
                                                                    "Sign" => $this->bsf->isNullCheck($selDCQTData['Sign'], 'string'),
                                                                    "SurCharge" => $this->bsf->isNullCheck($selDCQTData['SurCharge'], 'number'),
                                                                    "EDCess" => $this->bsf->isNullCheck($selDCQTData['EDCess'], 'number'),
                                                                    "HEDCess" => $this->bsf->isNullCheck($selDCQTData['HEDCess'], 'number'),
                                                                    "NetPer" => $this->bsf->isNullCheck($selDCQTData['NetPer'], 'number'),
                                                                    "ExpressionAmt" => $this->bsf->isNullCheck($selDCQTData['ExpressionAmt'], 'number'),
                                                                    "TaxableAmt" => $this->bsf->isNullCheck($selDCQTData['TaxableAmt'], 'number'),
                                                                    "TaxAmt" => $this->bsf->isNullCheck($selDCQTData['TaxAmt'], 'number'),
                                                                    "SurChargeAmt" => $this->bsf->isNullCheck($selDCQTData['SurChargeAmt'], 'number'),
                                                                    "EDCessAmt" => $this->bsf->isNullCheck($selDCQTData['EDCessAmt'], 'number'),
                                                                    "HEDCessAmt" => $this->bsf->isNullCheck($selDCQTData['HEDCessAmt'], 'number'),
                                                                    "NetAmt" => $this->bsf->isNullCheck($selDCQTData['NetAmt'], 'number'),
                                                                    "HEDCessAmt" => $this->bsf->isNullCheck($selDCQTData['HEDCessAmt'], 'number')
                                                                ));
                                                                $dcqTransUpdate->where(array("TransId" => $dcQTransId));
                                                                $dcqTransStatement = $sql->getSqlStringForSqlObject($dcqTransUpdate);
                                                                $dbAdapter->query($dcqTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                                                                $dcTransUpdate = $sql->update();
                                                                $dcTransUpdate->table('MMS_DCTrans');
                                                                $dcTransUpdate->set(array(
                                                                    "Rate" => $dRate,
                                                                    "QRate" => $dQRate,
                                                                    "GrossRate" => $dGRate,
                                                                ));
                                                                $dcTransUpdate->where(array("DCTransId = $dcQDctId and DCRegisterId = $dcQRegId "));
                                                                $dcTransStatement = $sql->getSqlStringForSqlObject($dcTransUpdate);
                                                                $dbAdapter->query($dcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $dcTransUpdate1 = $sql->update();
                                                                $dcTransUpdate1 ->table("MMS_DCTrans");
                                                                $dcTransUpdate1 ->set(array( new Expression("Round(AcceptQty * Rate) As Decimal(18,2)) As Amount,
                                                                        Round(AcceptQty * QRate) As Decimal(18,2)) As QRate,
                                                                        Round(AcceptQty * GrossRate) As Decimal(18,2)) As GrossRate"
                                                                )));
                                                                $dcTransUpdate1 ->where(array("DCTransId = $dcQDctId and DCRegisterId = $dcQRegId "));
                                                                $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate1);
                                                                $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $select = $sql->select();
                                                                $select->from(array("a" => "MMS_DCTrans"))
                                                                    ->columns(array( new Expression("SUM(Amount) as Amount,SUM(QAmount) as QAmount,SUM(GrossAmount) as GrossAmount")))
                                                                    ->where(array("DCRegisterId = $dcQRegId"));
                                                                $statement = $sql->getSqlStringForSqlObject($select);
                                                                $selectDCTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                                if(count($selectDCTrans) > 0){
                                                                    $dOAmount = 0;
                                                                    $dOQAmount = 0;
                                                                    $dOGAmount =0;
                                                                    foreach($selectDCTrans as $selectDCTrsData){

                                                                        $dOAmount = $this->bsf->isNullCheck($selectDCTrsData['Amount'], 'number');
                                                                        $dOQAmount = $this->bsf->isNullCheck($selectDCTrsData['QAmount'], 'number');
                                                                        $dOGAmount = $this->bsf->isNullCheck($selectDCTrsData['GrossAmount'], 'number');

                                                                        $update1 = $sql->update();
                                                                        $update1 ->table("MMS_DCRegister");
                                                                        $update1 ->set(array(
                                                                            "Amount" => $dOAmount,
                                                                            "NetAmount" => $dOQAmount,
                                                                            "GrossAmount" => $dOGAmount,
                                                                        ));
                                                                        $update1 ->where(array("DCRegisterId = $dcQRegId "));
                                                                        $statement1 = $sql->getSqlStringForSqlObject($update1);
                                                                        $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    }
                                                                }
                                                            }
                                                        } else {
                                                            //insert DCQualtrans
                                                            $dcqTransInsert1 = $sql->insert('MMS_DCQualTrans');
                                                            $dcqTransInsert1 ->values(array(
                                                                "DCRegisterId" => $iDCRegId,
                                                                "DCTransId" => $iDCTransId,
                                                                "ResourceId" => $this->bsf->isNullCheck($selDCQData['ResourceId'], 'number'),
                                                                "ItemId" => $this->bsf->isNullCheck($selDCQData['ItemId'], 'number'),
                                                                "QualifierId" => $this->bsf->isNullCheck($selDCQData['QualifierId'], 'number'),
                                                                "YesNo" => $this->bsf->isNullCheck($selDCQData['YesNo'], 'number'),
                                                                "Expression" => $this->bsf->isNullCheck($selDCQData['Expression'], 'string'),
                                                                "ExpPer" => $this->bsf->isNullCheck($selDCQData['ExpPer'], 'number'),
                                                                "TaxablePer" => $this->bsf->isNullCheck($selDCQData['TaxablePer'], 'number'),
                                                                "TaxPer" => $this->bsf->isNullCheck($selDCQData['TaxPer'], 'number'),
                                                                "Sign" => $this->bsf->isNullCheck($selDCQData['Sign'], 'string'),
                                                                "SurCharge" => $this->bsf->isNullCheck($selDCQData['SurCharge'], 'number'),
                                                                "EDCess" => $this->bsf->isNullCheck($selDCQData['EDCess'], 'number'),
                                                                "HEDCess" => $this->bsf->isNullCheck($selDCQData['HEDCess'], 'number'),
                                                                "NetPer" => $this->bsf->isNullCheck($selDCQData['NetPer'], 'number'),
                                                                "ExpressionAmt" => $this->bsf->isNullCheck($selDCQData['ExpressionAmt'], 'number'),
                                                                "TaxableAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                                                "TaxAmt" => $this->bsf->isNullCheck($selDCQData['TaxableAmt'], 'number'),
                                                                "SurChargeAmt" => $this->bsf->isNullCheck($selDCQData['SurChargeAmt'], 'number'),
                                                                "EDCessAmt" => $this->bsf->isNullCheck($selDCQData['EDCessAmt'], 'number'),
                                                                "HEDCessAmt" => $this->bsf->isNullCheck($selDCQData['HEDCessAmt'], 'number'),
                                                                "NetAmt" => $this->bsf->isNullCheck($selDCQData['NetAmt'], 'number'),
                                                                "SortId" => $this->bsf->isNullCheck($selDCQData['SortId'], 'number'),
                                                                "AccountId" => $this->bsf->isNullCheck($selDCQData['AccountId'], 'number'),

                                                            ));
                                                            $dcqTransStatement1 = $sql->getSqlStringForSqlObject($dcqTransInsert1);
                                                            $dbAdapter->query($dcqTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);


                                                            $dcTransUpdate1 = $sql->update();
                                                            $dcTransUpdate1 ->table('MMS_DCTrans');
                                                            $dcTransUpdate1 ->set(array(
                                                                "Rate" => $dRate,
                                                                "QRate" => $dQRate,
                                                                "GrossRate" => $dGRate,
                                                            ));
                                                            $dcTransUpdate1 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                            $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate1);
                                                            $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);


                                                            $dcTransUpdate11 = $sql->update();
                                                            $dcTransUpdate11 ->table("MMS_DCTrans");
                                                            $dcTransUpdate11 ->set(array( new Expression("Round(AcceptQty * Rate) As Decimal(18,2)) As Amount,
                                                                        Round(AcceptQty * QRate) As Decimal(18,2)) As QRate,
                                                                        Round(AcceptQty * GrossRate) As Decimal(18,2)) As GrossRate"
                                                            )));
                                                            $dcTransUpdate11 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                            $dcTransStatement11 = $sql->getSqlStringForSqlObject($dcTransUpdate11);
                                                            $dbAdapter->query($dcTransStatement11, $dbAdapter::QUERY_MODE_EXECUTE);

                                                            $select1 = $sql->select();
                                                            $select1 ->from(array("a" => "MMS_DCTrans"))
                                                                ->columns(array( new Expression("SUM(Amount) as Amount,SUM(QAmount) as QAmount,SUM(GrossAmount) as GrossAmount")))
                                                                ->where(array("DCRegisterId = $iDCRId"));
                                                            $statement1 = $sql->getSqlStringForSqlObject($select1);
                                                            $selectDCTrans1 = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                            if(count($selectDCTrans1) > 0) {
                                                                $dOAmount = 0;
                                                                $dOQAmount = 0;
                                                                $dOGAmount = 0;

                                                                foreach ($selectDCTrans1 as $selectDCTransData1) {

                                                                    $dOAmount = $this->bsf->isNullCheck($selectDCTransData1['Amount'], 'number');
                                                                    $dOQAmount = $this->bsf->isNullCheck($selectDCTransData1['QAmount'], 'number');
                                                                    $dOGAmount = $this->bsf->isNullCheck($selectDCTransData1['GrossAmount'], 'number');

                                                                    $update11 = $sql->update();
                                                                    $update11->table("MMS_DCRegister");
                                                                    $update11->set(array(
                                                                        "Amount" => $dOAmount,
                                                                        "NetAmount" => $dOQAmount,
                                                                        "GrossAmount" => $dOGAmount,
                                                                    ));
                                                                    $update11->where(array("DCRegisterId = $iDCRId "));
                                                                    $statement11 = $sql->getSqlStringForSqlObject($update11);
                                                                    $dbAdapter->query($statement11, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                }
                                                            }
                                                        }
                                                    }
                                                    //
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_POTrans"))
                                                        ->columns(array( new Expression("APOTransId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.APOTransId > 0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selPOTrans1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                                    if(count($selPOTrans1) > 0){
                                                        $iAPOTransId = $this->bsf->isNullCheck($selPOTrans1['APOTransId'], 'number');
                                                    } else {
                                                        $iAPOTransId = 0;
                                                    }

                                                } while($iAPOTransId > 0);
                                            }
                                        }
                                    }
                                }

                                else {
                                    $select = $sql->select();
                                    $select->from(array("a" => "MMS_POTrans"))
                                        ->columns(array( new Expression("APOTransId")))
                                        ->where(array("a.POTransId = $iPOTransId "));
                                    $statement = $sql->getSqlStringForSqlObject($select);
                                    $selPOTrans2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    if(count($selPOTrans2) > 0){
                                        $iAPOTransId = 0;

                                        foreach($selPOTrans2 as $selPOTrans2Data ){

                                            $iAPOTransId = $this->bsf->isNullCheck($selPOTrans2Data['APOTransId'], 'number');

                                            if($iAPOTransId > 0){
                                                do{
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_DCTrans"))
                                                        ->columns(array( new Expression("DCTransId,DCRegisterId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.BillQty = 0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selDCTrans2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                    $iDCId = 0;
                                                    $iDCRId = 0;

                                                    foreach($selDCTrans2 as $selDCTrans2Data) {
                                                        $iDCId = $this->bsf->isNullCheck($selPOTrans2Data['DCTransId'], 'number');
                                                        $iDCRId = $this->bsf->isNullCheck($selPOTrans2Data['DCRegisterId'], 'number');


                                                        $dcTransUpdate1 = $sql->update();
                                                        $dcTransUpdate1 ->table('MMS_DCTrans');
                                                        $dcTransUpdate1 ->set(array(
                                                            "Rate" => $dRate,
                                                            "QRate" => $dQRate,
                                                            "GrossRate" => $dGRate,
                                                        ));
                                                        $dcTransUpdate1 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                        $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate1);
                                                        $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        $dcTransUpdate12 = $sql->update();
                                                        $dcTransUpdate12 ->table("MMS_DCTrans");
                                                        $dcTransUpdate12 ->set(array( new Expression("Round(AcceptQty * Rate) As Decimal(18,2)) As Amount,
                                                                        Round(AcceptQty * QRate) As Decimal(18,2)) As QRate,
                                                                        Round(AcceptQty * GrossRate) As Decimal(18,2)) As GrossRate"
                                                        )));
                                                        $dcTransUpdate12 ->where(array("DCTransId = $iDCId and DCRegisterId = $iDCRId "));
                                                        $dcTransStatement1 = $sql->getSqlStringForSqlObject($dcTransUpdate12);
                                                        $dbAdapter->query($dcTransStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        $select = $sql->select();
                                                        $select->from(array("a" => "MMS_DCTrans"))
                                                            ->columns(array( new Expression("SUM(Amount) as Amount,SUM(QAmount) as QAmount,SUM(GrossAmount) as GrossAmount")))
                                                            ->where(array("DCRegisterId = $iDCRId"));
                                                        $statement = $sql->getSqlStringForSqlObject($select);
                                                        $selectDCTransAmt = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                        if(count($selectDCTransAmt) > 0 ){
                                                            $dOAmount = 0;
                                                            $dOQAmount = 0;
                                                            $dOGAmount = 0;

                                                            foreach($selectDCTransAmt as $selectDCTransAmt1){
                                                                $dOAmount = $this->bsf->isNullCheck($selectDCTransAmt1['Amount'], 'number');
                                                                $dOQAmount = $this->bsf->isNullCheck($selectDCTransAmt1['QAmount'], 'number');
                                                                $dOGAmount = $this->bsf->isNullCheck($selectDCTransAmt1['GrossAmount'], 'number');

                                                                $update12 = $sql->update();
                                                                $update12->table("MMS_DCRegister");
                                                                $update12->set(array(
                                                                    "Amount" => $dOAmount,
                                                                    "NetAmount" => $dOQAmount,
                                                                    "GrossAmount" => $dOGAmount,
                                                                ));
                                                                $update12->where(array("DCRegisterId = $iDCRId "));
                                                                $statement12 = $sql->getSqlStringForSqlObject($update12);
                                                                $dbAdapter->query($statement12, $dbAdapter::QUERY_MODE_EXECUTE);
                                                            }
                                                        }
                                                    }
                                                     //
                                                    $select = $sql->select();
                                                    $select->from(array("a" => "MMS_POTrans"))
                                                        ->columns(array( new Expression("APOTransId")))
                                                        ->where(array("a.POTransId = $iAPOTransId and a.APOTransId > 0"));
                                                    $statement = $sql->getSqlStringForSqlObject($select);
                                                    $selPOTrans3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                                    if(count($selPOTrans3) > 0){
                                                        $iAPOTransId = $this->bsf->isNullCheck($selPOTrans3['APOTransId'], 'number');
                                                    } else {
                                                        $iAPOTransId = 0;
                                                    }
                                                }while($iAPOTransId > 0);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        // end of dcqualtrans -add mode


                    } //insert the entry details

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Min',$dcid,$CostCentre,$CompanyId,'mms',$voucherno,$this->auth->getIdentity()->UserId,0,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'min', 'action' => 'register', 'rid' => $dcid));
                } //try block
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }
        }//END OF THE MINSAVE
    }

    public function deleteMinAction(){
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
        $dcid = $this->params()->fromRoute('rid');

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
            $postParams = $request->getPost();

            if ($request->isPost()) {
                //Write your Normal form post code here
            }
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();

            try {
                //POAmendment Updation
                $selM = $sql->select();
                $selM->from(array("a" => "MMS_DCTrans"))
                    ->columns(array(new Expression("a.DCTransId,a.POTransId,b.CostCentreId,a.ResourceId,a.ItemId,a.DCQty,a.AcceptQty,a.RejectQty,a.QAmount As Amount,a.GrossAmount As GrossAmount")))
                    ->join(array("b" => "MMS_DCRegister"), "a.DCRegisterId=b.DCRegisterId", array(), $selM::JOIN_INNER)
                    ->where("a.DCRegisterId=$dcid");
                $statementM = $sql->getSqlStringForSqlObject($selM);
                $arr_mdc = $dbAdapter->query($statementM, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if (count($arr_mdc) > 0) {
                    $dDCQty = 0;
                    $iResId = 0;
                    $iItemId = 0;
                    $dDCAmt = 0;
                    $dDCGAmt = 0;
                    $dACQty = 0;
                    $dRejQty = 0;
                    $iPOTransId = 0;
                    $iPOProjTransId = 0;
                    $iPOAnalTransId = 0;
                    $iDCTransId = 0;
                    $iIPDTransId = 0;
                    $iIPDPTransId = 0;
                    $dIPDQty = 0;
                    $dIPDPQty = 0;
                    $dIPDAQty = 0;
                    $dIPDADCQty = 0;
                    $dIPDARQty = 0;
                    $iCCId = 0;
                    foreach ($arr_mdc as $dc) {
                        $dDCQty = $this->bsf->isNullCheck($dc['DCQty'], 'number');
                        $iResId = $this->bsf->isNullCheck($dc['ResourceId'], 'number');
                        $iItemId = $this->bsf->isNullCheck($dc['ItemId'], 'number');
                        $dDCAmt = $this->bsf->isNullCheck($dc['Amount'], 'number');
                        $dDCGAmt = $this->bsf->isNullCheck($dc['GrossAmount'], 'number');
                        $dACQty = $this->bsf->isNullCheck($dc['AcceptQty'], 'number');
                        $dRejQty = $this->bsf->isNullCheck($dc['RejectQty'], 'number');
                        $iPOTransId = $this->bsf->isNullCheck($dc['POTransId'], 'number');
                        $iDCTransId = $this->bsf->isNullCheck($dc['DCTransId'], 'number');
                        $iCCId = $this->bsf->isNullCheck($dc['CostCentreId'], 'number');

                        //POAmend Updation
                        $selMPO = $sql->select();
                        $selMPO->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("a.DCQty,a.AcceptQty,a.RejectQty As RejectQty")))
                            ->where("POTransId=$iPOTransId");
                        $statementM = $sql->getSqlStringForSqlObject($selMPO);
                        $arr_mpo = $dbAdapter->query($statementM, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if (count($arr_mpo) > 0) {
                            foreach ($arr_mpo as $mpo) {
                                $dADCQty = $this->bsf->isNullCheck($mpo['DCQty'], 'number');
                                $dAAccQty = $this->bsf->isNullCheck($mpo['AcceptQty'], 'number');
                                $dARejQty = $this->bsf->isNullCheck($mpo['RejectQty'], 'number');

                                $selAPO = $sql->select();
                                $selAPO->from(array("a" => "MMS_POTrans"))
                                    ->columns(array(new Expression("a.POTransId As POTransId")))
                                    ->where("APOTransId=$iPOTransId");
                                $statementA = $sql->getSqlStringForSqlObject($selAPO);
                                $arr_apo = $dbAdapter->query($statementA, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if (count($arr_apo) > 0) {
                                    $iAPOTransId = 0;
                                    foreach ($arr_apo as $apo) {
                                        $iAPOTransId = $this->bsf->isNullCheck($apo['POTransId'], 'number');
                                        $updpotrans = $sql->update();
                                        $updpotrans->table('MMS_POTrans');
                                        $updpotrans->set(array(
                                            'DCQty' => new Expression('DCQty-' . $dADCQty . ''),
                                            'AcceptQty' => new Expression('AcceptQty+' . $dACQty . ''),
                                            'RejectQty' => new Expression('RejectQty+' . $dRejQty . '')
                                        ));
                                        $updpotrans->where(array('POTransId' => $iAPOTransId));
                                        $statementPOTrans = $sql->getSqlStringForSqlObject($updpotrans);
                                        $dbAdapter->query($statementPOTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $updbal = $sql->update();
                                        $updbal->table('MMS_POTrans');
                                        $updbal->set(array(
                                            'BalQty' => new Expression('POQty-AcceptQty-CancelQty')
                                        ));
                                        $updbal->where(array('POTransId' => $iAPOTransId));
                                        $statementbal = $sql->getSqlStringForSqlObject($updbal);
                                        $dbAdapter->query($statementbal, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                                $selAPOP = $sql->select();
                                $selAPOP->from(array("a" => "MMS_POProjTrans"))
                                    ->columns(array(new Expression("a.POProjTransId As POProjTransId")))
                                    ->where("POTransId IN (Select POTransId From MMS_POTrans Where APOTransId=$iPOTransId)");
                                $statementA = $sql->getSqlStringForSqlObject($selAPOP);
                                $arr_apop = $dbAdapter->query($statementA, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if (count($arr_apop) > 0) {
                                    $iAPOPTransId = 0;
                                    foreach ($arr_apop as $apop) {
                                        $iAPOPTransId = $this->bsf->isNullCheck($apop['POProjTransId'], 'number');
                                        $updpoprojtrans = $sql->update();
                                        $updpoprojtrans->table('MMS_POProjTrans');
                                        $updpoprojtrans->set(array(
                                            'DCQty' => new Expression('DCQty-' . $dADCQty . ''),
                                            'AcceptQty' => new Expression('AcceptQty+' . $dACQty . ''),
                                            'RejectQty' => new Expression('RejectQty+' . $dRejQty . '')
                                        ));
                                        $updpoprojtrans->where(array('POProjTransId' => $iAPOPTransId));
                                        $statementPOProjTrans = $sql->getSqlStringForSqlObject($updpoprojtrans);
                                        $dbAdapter->query($statementPOProjTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $updpoprojbal = $sql->update();
                                        $updpoprojbal->table('MMS_POProjTrans');
                                        $updpoprojbal->set(array(
                                            'BalQty' => new Expression('POQty-AcceptQty-CancelQty')
                                        ));
                                        $updpoprojbal->where(array('POProjTransId' => $iAPOPTransId));
                                        $statementbal = $sql->getSqlStringForSqlObject($updpoprojbal);
                                        $dbAdapter->query($statementbal, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                                $selAPOP = $sql->select();
                                $selAPOP->from(array("a" => "MMS_POAnalTrans"))
                                    ->columns(array(new Expression("a.POAnalTransId,AnalysisId,ResourceId,ItemId,DCQty,AcceptQty,RejectQty As RejectQty")))
                                    ->where("POProjTransId IN (Select POTransId From MMS_POTrans Where APOTransId=$iPOTransId)");
                                $statementA = $sql->getSqlStringForSqlObject($selAPOP);
                                $arr_mpanal = $dbAdapter->query($statementA, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if (count($arr_mpanal) > 0) {
                                    foreach ($arr_mpanal as $mpanal) {
                                        $iMPOAnalTransId = $this->bsf->isNullCheck($mpanal['POAnalTransId'], 'number');
                                        $iAnalId = $this->bsf->isNullCheck($mpanal['AnalysisId'], 'number');
                                        $iAResId = $this->bsf->isNullCheck($mpanal['ResourceId'], 'number');
                                        $iAItemId = $this->bsf->isNullCheck($mpanal['ItemId'], 'number');
                                        $dAnalDCQty = $this->bsf->isNullCheck($mpanal['DCQty'], 'number');
                                        $dAnalAccQty = $this->bsf->isNullCheck($mpanal['AcceptQty'], 'number');
                                        $dAnalRejQty = $this->bsf->isNullCheck($mpanal['RejectQty'], 'number');

                                        $selAPOAnal = $sql->select();
                                        $selAPOAnal->from(array("a" => "MMS_POAnalTrans"))
                                            ->columns(array(new Expression("a.POAnalTransId As POAnalTransId")))
                                            ->where("POProjTransId IN (Select POProjTransId From MMS_POProjTrans Where POTransId
                                               IN (Select POTransId From MMS_POTrans Where APOTransId=$iPOTransId)) and a.AnalysisId=$iAnalId
                                               and a.ResourceId=$iResId and a.ItemId=$iItemId ");
                                        $statementAnal = $sql->getSqlStringForSqlObject($selAPOAnal);
                                        $arr_apoanal = $dbAdapter->query($statementAnal, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        if (count($arr_apoanal) > 0) {
                                            foreach ($arr_apoanal as $apoanal) {
                                                $iAPOAnalTransId = $this->bsf->isNullCheck($apoanal['POAnalTransId'], 'number');
                                                $updpoanal = $sql->update();
                                                $updpoanal->table('MMS_POAnalTrans');
                                                $updpoanal->set(array(
                                                    'DCQty' => new Expression('DCQty-' . $dAnalDCQty . ''),
                                                    'AcceptQty' => new Expression('AcceptQty-' . $dAnalAccQty . ''),
                                                    'RejectQty' => new Expression('RejectQty-' . $dAnalRejQty . '')
                                                ));
                                                $updpoanal->where(array('POAnalTransId' => $iAPOAnalTransId));
                                                $statementpoanal = $sql->getSqlStringForSqlObject($updpoanal);
                                                $dbAdapter->query($statementpoanal, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $updpoanalbal = $sql->update();
                                                $updpoanalbal->table('MMS_POAnalTrans');
                                                $updpoanalbal->set(array(
                                                    'BalQty' => new Expression('POQty-AcceptQty-CancelQty')
                                                ));
                                                $updpoanalbal->where(array('POAnalTransId' => $iAPOAnalTransId));
                                                $statementbal = $sql->getSqlStringForSqlObject($updpoanalbal);
                                                $dbAdapter->query($statementbal, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        //for po updation

                        $selM = $sql->select();
                        $selM->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("a.APOTransId As APOTransId")))
                            ->where("POTransId=$iPOTransId");
                        $statementM = $sql->getSqlStringForSqlObject($selM);
                        $arr_M = $dbAdapter->query($statementM, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if (count($arr_M) > 0) {
                            foreach ($arr_M as $am) {
                                $iAPOTransId = $this->bsf->isNullCheck($am['APOTransId'], 'number');
                                $selA = $sql->select();
                                $selA->from(array("a" => "MMS_POTrans"))
                                    ->columns(array(new Expression("a.DCQty,a.AcceptQty,a.RejectQty As RejectQty")))
                                    ->where("POTransId=$iAPOTransId");
                                $statementA = $sql->getSqlStringForSqlObject($selA);
                                $arr_A = $dbAdapter->query($statementA, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                $dAPDCQty = 0;
                                $dAPAccQty = 0;
                                $dAPRejQty = 0;
                                if (count($arr_A) > 0) {
                                    foreach ($arr_A as $aA) {
                                        $dAPDCQty = $this->bsf->isNullCheck($aA['DCQty'], 'number');
                                        $dAPAccQty = $this->bsf->isNullCheck($aA['AcceptQty'], 'number');
                                        $dAPRejQty = $this->bsf->isNullCheck($aA['RejectQty'], 'number');
                                        $updpt = $sql->update();
                                        $updpt->table('MMS_POTrans');
                                        $updpt->set(array(
                                            'DCQty' => new Expression('DCQty-' . $dAPDCQty . ''),
                                            'AcceptQty' => new Expression('AcceptQty-' . $dAPAccQty . ''),
                                            'RejectQty' => new Expression('RejectQty-' . $dAPRejQty . '')
                                        ));
                                        $updpt->where(array('POTransId' => $iAPOTransId));
                                        $statementpt = $sql->getSqlStringForSqlObject($updpt);
                                        $dbAdapter->query($statementpt, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $updbal = $sql->update();
                                        $updbal->table('MMS_POTrans');
                                        $updbal->set(array(
                                            'BalQty' => new Expression('POQty-AcceptQty-CancelQty')
                                        ));
                                        $updbal->where(array('POTransId' => $iAPOTransId));
                                        $statementbal = $sql->getSqlStringForSqlObject($updbal);
                                        $dbAdapter->query($statementbal, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                                $selAPOP = $sql->select();
                                $selAPOP->from(array("a" => "MMS_POProjTrans"))
                                    ->columns(array(new Expression("a.POProjTransId As POProjTransId")))
                                    ->where("POTransId IN (Select POTransId From MMS_POTrans Where POTransId=$iAPOTransId)");
                                $statementA = $sql->getSqlStringForSqlObject($selAPOP);
                                $arr_apop = $dbAdapter->query($statementA, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if (count($arr_apop) > 0) {
                                    $iAPOPTransId = 0;
                                    foreach ($arr_apop as $apop) {
                                        $iAPOPTransId = $this->bsf->isNullCheck($apop['POProjTransId'], 'number');
                                        $updpoprojtrans = $sql->update();
                                        $updpoprojtrans->table('MMS_POProjTrans');
                                        $updpoprojtrans->set(array(
                                            'DCQty' => new Expression('DCQty-' . $dAPDCQty . ''),
                                            'AcceptQty' => new Expression('AcceptQty-' . $dAPAccQty . ''),
                                            'RejectQty' => new Expression('RejectQty-' . $dAPRejQty . '')
                                        ));
                                        $updpoprojtrans->where(array('POProjTransId' => $iAPOPTransId));
                                        $statementPOProjTrans = $sql->getSqlStringForSqlObject($updpoprojtrans);
                                        $dbAdapter->query($statementPOProjTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $updpoprojbal = $sql->update();
                                        $updpoprojbal->table('MMS_POProjTrans');
                                        $updpoprojbal->set(array(
                                            'BalQty' => new Expression('POQty-AcceptQty-CancelQty')
                                        ));
                                        $updpoprojbal->where(array('POProjTransId' => $iAPOPTransId));
                                        $statementbal = $sql->getSqlStringForSqlObject($updpoprojbal);
                                        $dbAdapter->query($statementbal, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                                $selAPOP = $sql->select();
                                $selAPOP->from(array("a" => "MMS_POAnalTrans"))
                                    ->columns(array(new Expression("a.POAnalTransId,AnalysisId,ResourceId,ItemId,DCQty,AcceptQty,RejectQty As RejectQty")))
                                    ->where("POProjTransId IN (Select POProjTransId From MMS_POProjTrans Where POTransId IN (Select POTransId From MMS_POTrans Where POTransId = $iAPOTransId))");
                                $statementA = $sql->getSqlStringForSqlObject($selAPOP);
                                $arr_mpanal = $dbAdapter->query($statementA, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if (count($arr_mpanal) > 0) {
                                    foreach ($arr_mpanal as $mpanal) {
                                        $iMPOAnalTransId = $this->bsf->isNullCheck($mpanal['POAnalTransId'], 'number');
                                        $iAnalId = $this->bsf->isNullCheck($mpanal['AnalysisId'], 'number');
                                        $iAResId = $this->bsf->isNullCheck($mpanal['ResourceId'], 'number');
                                        $iAItemId = $this->bsf->isNullCheck($mpanal['ItemId'], 'number');
                                        $dAnalDCQty = $this->bsf->isNullCheck($mpanal['DCQty'], 'number');
                                        $dAnalAccQty = $this->bsf->isNullCheck($mpanal['AcceptQty'], 'number');
                                        $dAnalRejQty = $this->bsf->isNullCheck($mpanal['RejectQty'], 'number');

                                        $selAPOAnal = $sql->select();
                                        $selAPOAnal->from(array("a" => "MMS_POAnalTrans"))
                                            ->columns(array(new Expression("a.POAnalTransId As POAnalTransId")))
                                            ->where("POProjTransId IN (Select POProjTransId From MMS_POProjTrans Where POTransId
                                               IN (Select POTransId From MMS_POTrans Where POTransId=$iAPOTransId)) and a.AnalysisId=$iAnalId
                                               and a.ResourceId=$iResId and a.ItemId=$iItemId ");
                                        $statementAnal = $sql->getSqlStringForSqlObject($selAPOAnal);
                                        $arr_apoanal = $dbAdapter->query($statementAnal, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        if (count($arr_apoanal) > 0) {
                                            foreach ($arr_apoanal as $apoanal) {
                                                $iAPOAnalTransId = $this->bsf->isNullCheck($apoanal['POAnalTransId'], 'number');
                                                $updpoanal = $sql->update();
                                                $updpoanal->table('MMS_POAnalTrans');
                                                $updpoanal->set(array(
                                                    'DCQty' => new Expression('DCQty-' . $dAnalDCQty . ''),
                                                    'AcceptQty' => new Expression('AcceptQty-' . $dAnalAccQty . ''),
                                                    'RejectQty' => new Expression('RejectQty-' . $dAnalRejQty . '')
                                                ));
                                                $updpoanal->where(array('POAnalTransId' => $iAPOAnalTransId));
                                                $statementpoanal = $sql->getSqlStringForSqlObject($updpoanal);
                                                $dbAdapter->query($statementpoanal, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $updpoanalbal = $sql->update();
                                                $updpoanalbal->table('MMS_POAnalTrans');
                                                $updpoanalbal->set(array(
                                                    'BalQty' => new Expression('POQty-AcceptQty-CancelQty')
                                                ));
                                                $updpoanalbal->where(array('POAnalTransId' => $iAPOAnalTransId));
                                                $statementbal = $sql->getSqlStringForSqlObject($updpoanalbal);
                                                $dbAdapter->query($statementbal, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        //

                    }
                }
                //

                // over all delete
                $selPrevAnal = $sql->select();
                $selPrevAnal->from(array("a" => "MMS_DCAnalTrans"))
                    ->columns(array("DCQty", "AcceptQty", "RejectQty"))
                    ->join(array("b" => "MMS_IPDAnalTrans"), "a.DCAnalTransId=b.DCAHTransId ", array("POAHTransId"), $selPrevAnal::JOIN_INNER)
                    ->join(array("c" => "MMS_DCTrans"), "a.DCTransId=c.DCTransId", array(), $selPrevAnal::JOIN_INNER)
                    ->where(array("c.DCRegisterId" => $dcid));
                $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach ($prevanal as $arrprevanal) { // WBS QTY

                    $updDcAnal = $sql->update();
                    $updDcAnal->table('MMS_POAnalTrans');
                    $updDcAnal->set(array(

                        'DCQty' => new Expression('DCQty-' . $arrprevanal['DCQty'] . ''),
                        'AcceptQty' => new Expression('AcceptQty-' . $arrprevanal['AcceptQty'] . ''),
                        'RejectQty' => new Expression('RejectQty-' . $arrprevanal['RejectQty'] . ''),
                        'BalQty' => new Expression('BalQty+' . $arrprevanal['AcceptQty'] . '')
                    ));
                    $updDcAnal->where(array('POAnalTransId' => $arrprevanal['POAHTransId']));
                    $updDcAnalStatement = $sql->getSqlStringForSqlObject($updDcAnal);
                    $dbAdapter->query($updDcAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                $selPrevTrans = $sql->select();
                $selPrevTrans->from("MMS_DCTrans")
                    ->columns(array("POTransId", "DCQty", "AcceptQty", "RejectQty"))
                    ->where(array("DCRegisterId" => $dcid));
                $statement = $sql->getSqlStringForSqlObject($selPrevTrans);
                $prevtrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach ($prevtrans as $arrprevtrans) { // PO QTY

                    $updDcTrans = $sql->update();
                    $updDcTrans->table('MMS_POTrans');
                    $updDcTrans->set(array(

                        'DCQty' => new Expression('DCQty-' . $arrprevtrans['DCQty'] . ''),
                        'AcceptQty' => new Expression('AcceptQty-' . $arrprevtrans['AcceptQty'] . ''),
                        'RejectQty' => new Expression('RejectQty-' . $arrprevtrans['RejectQty'] . ''),
                        'BalQty' => new Expression('BalQty+' . $arrprevtrans['AcceptQty'] . '')
                    ));
                    $updDcTrans->where(array('PoTransId' => $arrprevtrans['POTransId']));
                    $updDcTransStatement = $sql->getSqlStringForSqlObject($updDcTrans);
                    $dbAdapter->query($updDcTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $updDcProTrans = $sql->update();
                    $updDcProTrans->table('MMS_POProjTrans');
                    $updDcProTrans->set(array(

                        'DCQty' => new Expression('DCQty-' . $arrprevtrans['DCQty'] . ''),
                        'AcceptQty' => new Expression('AcceptQty-' . $arrprevtrans['AcceptQty'] . ''),
                        'RejectQty' => new Expression('RejectQty-' . $arrprevtrans['RejectQty'] . ''),
                        'BalQty' => new Expression('BalQty+' . $arrprevtrans['AcceptQty'] . '')
                    ));
                    $updDcProTrans->where(array('POTransId' => $arrprevtrans['POTransId']));
                    $updDcProTransStatement = $sql->getSqlStringForSqlObject($updDcProTrans);
                    $dbAdapter->query($updDcProTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                //stock edit mode -update
                $sel = $sql->select();
                $sel->from(array("a" => "MMS_DCTrans"))
                    ->columns(array("ResourceId", "ItemId", "AcceptQty"))
                    ->join(array("b" => "MMS_DCRegister"), "a.DCRegisterId=b.DCRegisterId", array("CostCentreId"), $sel::JOIN_INNER)
                    ->where(array("a.DCRegisterId" => $dcid));
                $statementPrev = $sql->getSqlStringForSqlObject($sel);
                $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                foreach ($pre as $preStock) {

                    $stockSelect = $sql->select();
                    $stockSelect->from(array("a" => "mms_stock"))
                        ->columns(array("StockId"))
                        ->where(array(
                            "ResourceId" => $preStock['ResourceId'],
                            "CostCentreId" => $preStock['CostCentreId'],
                            "ItemId" => $preStock['ItemId']
                        ));
                    $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                    $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    if (count($stockselId['StockId']) > 0) {

                        $stockUpdate = $sql->update();
                        $stockUpdate->table('mms_stock');
                        $stockUpdate->set(array(
                            "DCQty" => new Expression('DCQty-' . $preStock['AcceptQty'] . ''),
                            "ClosingStock" => new Expression('ClosingStock-' . $preStock['AcceptQty'] . '')
                        ));
                        $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                        $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                        $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }

                    //stocktrans edit mode -update
                    $sel = $sql->select();
                    $sel->from(array("a" => "MMS_DCGroupTrans"))
                        ->columns(array("CostCentreId", "ResourceId", "ItemId"))
                        ->join(array("b" => "MMS_DCWareHouseTrans"), "a.DCGroupId=b.DCGroupId", array("WareHouseId", "DCQty"), $sel::JOIN_INNER)
                        ->where(array("a.DCRegisterId" => $dcid));
                    $statementPrev = $sql->getSqlStringForSqlObject($sel);
                    $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($pre as $preStockTrans) {

                        if (count($stockselId['StockId']) > 0) {

                            $stockUpdate = $sql->update();
                            $stockUpdate->table('mms_stockTrans');
                            $stockUpdate->set(array(
                                "DCQty" => new Expression('DCQty-' . $preStockTrans['DCQty'] . ''),
                                "ClosingStock" => new Expression('ClosingStock-' . $preStockTrans['DCQty'] . '')
                            ));
                            $stockUpdate->where(array("StockId" => $stockselId['StockId'], "WareHouseId" => $preStockTrans['WareHouseId']));
                            $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                            $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }
                    }
                }

                //delete the previous row
                //subquery

                //warehouse delete
                $whQuery3 = $sql->select();
                $whQuery3->from('MMS_DCGroupTrans')
                    ->columns(array("DCGroupId"))
                    ->where(array("DCRegisterId" => $dcid));

                $del = $sql->delete();
                $del->from('MMS_DCWareHouseTrans')
                    ->where->expression('DCGroupId IN ?', array($whQuery3));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $subQuery1 = $sql->select();
                $subQuery1->from("MMS_DCTrans")
                    ->columns(array("DCTransId"))
                    ->where(array("DCRegisterId" => $dcid, "Status" => 'D'));

                $subQuery = $sql->select();
                $subQuery->from("MMS_DCAnalTrans")
                    ->columns(array("DCAnalTransId"))
                    ->where->expression('DCTransId IN ?', array($subQuery1));

                $del = $sql->delete();
                $del->from('MMS_IPDAnalTrans')
                    ->where->expression('DCAHTransId IN ?', array($subQuery));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                //subQuery1
                $subIPDP = $sql->select();
                $subIPDP->from("MMS_DCTrans")
                    ->columns(array("DCTransId"))
                    ->where(array("DCRegisterId" => $dcid, "Status" => 'D'));

                $del = $sql->delete();
                $del->from('MMS_IPDProjTrans')
                    ->where->expression('DCProjTransId IN ?', array($subIPDP));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                //subQuery2
                $subQuery2 = $sql->select();
                $subQuery2->from('MMS_DCTrans')
                    ->columns(array("DCTransId"))
                    ->where(array("DCRegisterId" => $dcid, "Status" => 'D'));

                $del = $sql->delete();
                $del->from('MMS_IPDTrans')
                    ->where->expression('DCTransId IN ?', array($subQuery2));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                //subQuery3
                $subQuery3 = $sql->select();
                $subQuery3->from('MMS_DCTrans')
                    ->columns(array("DCTransId"))
                    ->where(array("DCRegisterId" => $dcid));

                $del = $sql->delete();
                $del->from('MMS_DCAnalTrans')
                    ->where->expression('DCTransId IN ?', array($subQuery3));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $del = $sql->delete();
                $del->from('MMS_DCGroupTrans')
                    ->where(array("DCRegisterId" => $dcid));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                $del = $sql->delete();
                $del->from('MMS_DCTrans')
                    ->where(array("DCRegisterId" => $dcid));
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                //tripsheet delete
                $tripDel = $sql->delete();
                $tripDel->from('mms_DCTripSheet')
                    ->where(array("DCRegisterId" => $dcid));
                $stat = $sql->getSqlStringForSqlObject($tripDel);
                $dbAdapter->query($stat, $dbAdapter::QUERY_MODE_EXECUTE);


                //update the deleted row
                $del = $sql->update();
                $del->table('MMS_DCRegister')
                    ->set(array('DeleteFlag' => 1))
                    ->where("DCRegisterId = $dcid");
                $statement = $sql->getSqlStringForSqlObject($del);
                $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);
                $connection->commit();
                $this->redirect()->toRoute('mms/default', array('controller' => 'min','action' => 'register'));
            }
            catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }

//            //Common function
//            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
//            $this->redirect()->toRoute('mms/default', array('controller' => 'min','action' => 'register'));
//            return $this->_view;
        }
    }

    public function minShortcloseAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "min","action" => "min-shortclose"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);
        $dcid = $this->bsf->isNullCheck($this->params()->fromRoute('rid'), 'number');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $dcId = $this->bsf->isNullCheck($postParams['DCRegisterId'], 'number');

                $select = $sql->select();
                $select->from(array("a" => "MMS_DCGroupTrans"))
                    ->columns(array(new Expression("Case When A.ItemId>0 Then D.ItemCode Else C.Code End Code,
                    Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End Resource,
                    A.DCGroupId,B.DCRegisterId,CAST(A.DCQty As Decimal(18,6)) DCQty,
                    CAST(A.BillQty As Decimal(18,6)) BillQty,CAST(A.BalQty As Decimal(18,6))BalQty,
                    CONVERT(bit,0,0) As Include")))
                    ->join(array('b' => 'MMS_DCRegister'), 'a.DCRegisterId=b.DCRegisterId', array(), $select::JOIN_INNER)
                    ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceID', array(), $select::JOIN_INNER)
                    ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId And a.ItemId=d.BrandId', array(), $select::JOIN_LEFT)
                    ->where("a.DCRegisterId= $dcId And b.ShortClose=0 And a.BalQty>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $response = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('response' => $response)));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }else{
                $postData = $request->getPost();
                if (isset($dcid) && $dcid != '') {

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_DCRegister'))
                        ->columns(array(new Expression("a.DCRegisterId as DCId,a.DCNo as DCNo")))
                        ->where(array("a.DCRegisterId=$dcid"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $minid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->selNo = $minid['DCNo'];
                    $this->_view->seldcId = $minid['DCId'];


                    $select = $sql->select();
                    $select->from(array("a" => "MMS_DCGroupTrans"))
                        ->columns(array(new Expression("Case When A.ItemId>0 Then D.ItemCode Else C.Code End Code,
                    Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End Resource,
                    A.DCGroupId,B.DCRegisterId,CAST(A.DCQty As Decimal(18,6)) DCQty,a.ShortClose,
                    CAST(A.BillQty As Decimal(18,6)) BillQty,CAST(A.BalQty As Decimal(18,6))BalQty,
                    a.ShortClose As Include")))
                        ->join(array('b' => 'MMS_DCRegister'), 'a.DCRegisterId=b.DCRegisterId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceID', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId And a.ItemId=d.BrandId', array(), $select::JOIN_LEFT)
                        ->where(array("a.DCRegisterId= $dcid"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }

            }
            $select = $sql->select();
            $select->from(array('a' => 'MMS_DCRegister'))
                ->columns(array(new Expression("distinct(a.DCRegisterId) as DCRegisterId,a.DCNo as DCNo")))
                ->join(array("b" => "MMS_DCGroupTrans"), "a.DCRegisterId=b.DCRegisterId ", array(), $select::JOIN_INNER)
                ->where(array("b.BalQty>0 And a.ShortClose=0 And a.Approve='Y' Order By a.DCRegisterId Asc"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_minno = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->_view->dcId = $dcid;
            return $this->_view;
        }
    }

    public function minshortcloseSaveAction()
    {
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "min", "action" => "min-shortclose"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);

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
                $postParams = $request->getPost();
//				   echo"<pre>";
//                   print_r($postParams);
//                  echo"</pre>";
//                   die;
//                  return;

                $Approve = "";
                $Role = "";
                $dcId = $this->bsf->isNullCheck($postParams['minno'], 'number');
                $dcNo = $this->bsf->isNullCheck($postParams['seldcId'], 'number');

                $DCGroupIds = implode(',', $postParams['DCGroupIds']);

                if ($this->bsf->isNullCheck($dcId, 'number') > 0) {
                    $Approve = "E";
                    $Role = "DC-Short-Close-Modify";
                } else {
                    $Approve = "N";
                    $Role = "DC-Short-Close-Create";
                }
                if($dcId>0){
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_DCRegister"))
                        ->columns(array("DCNo","CostCentreId"))
                        ->where(array('DCRegisterId' => $dcId));
                    $Statement = $sql->getSqlStringForSqlObject($select);
                    $dcreg = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
                }
                else{
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_DCRegister"))
                        ->columns(array("DCNo","CostCentreId"))
                        ->where(array('DCRegisterId' => $dcNo));
                    $Statement = $sql->getSqlStringForSqlObject($select);
                    $dcreg = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
                }

                $CostCentreId=$dcreg ['CostCentreId'];
                $DCNo=$dcreg ['DCNo'];

                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where(array("CostCentreId"=> $CostCentreId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$Comp['CompanyId'];

                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if($dcId > 0){

                        if(count($DCGroupIds)> 0){

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_DCRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 1,
                            ));
                            $updDcreg->where(array('DCRegisterId' => $dcId));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        else{

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_DCRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 0,
                            ));
                            $updDcreg->where(array('DCRegisterId' => $dcId));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $del = $sql->delete();
                        $del->from('MMS_DCShortCloseReg')
                            ->where(array('DCRegisterId' => $dcId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $Insert = $sql->insert('MMS_DCShortCloseReg');
                        $Insert->values(array(
                            "DCRegisterId" => $dcId,
                        ));
                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $updatedc = $sql->update();
                        $updatedc->table('MMS_DCGroupTrans');
                        $updatedc->set(array(
                            'ShortClose' => 1,
                        ));
                        $updatedc->where(array("DCGroupId IN($DCGroupIds)"));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatedc);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    else{

                        $updDcreg = $sql->update();
                        $updDcreg->table('MMS_DCRegister');
                        $updDcreg->set(array(
                            'ShortClose' => 0,
                        ));
                        $updDcreg->where(array('DCRegisterId' => $dcNo));
                        $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                        $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del = $sql->delete();
                        $del->from('MMS_DCShortCloseReg')
                            ->where(array('DCRegisterId' => $dcNo));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $updatedc = $sql->update();
                        $updatedc->table('MMS_DCGroupTrans');
                        $updatedc->set(array(
                            'ShortClose' => 0,
                        ));
                        $updatedc->where(array('DCRegisterId' => $dcNo));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatedc);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //update edit mod minshortclose

                        if(count($DCGroupIds)> 0){

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_DCRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 1,
                            ));
                            $updDcreg->where(array('DCRegisterId' => $dcNo));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        else{

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_DCRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 0,
                            ));
                            $updDcreg->where(array('DCRegisterId' => $dcNo));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $del = $sql->delete();
                        $del->from('MMS_DCShortCloseReg')
                            ->where(array('DCRegisterId' => $dcNo));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $Insert = $sql->insert('MMS_DCShortCloseReg');
                        $Insert->values(array(
                            "DCRegisterId" => $dcNo,
                        ));
                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $updatedc = $sql->update();
                        $updatedc->table('MMS_DCGroupTrans');
                        $updatedc->set(array(
                            'ShortClose' => 1,
                        ));
                        $updatedc->where(array("DCGroupId IN($DCGroupIds)"));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatedc);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                    $connection->commit();
//                      CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'DC-Short-Close',$dcId,$CostCentreId,$CompanyId, 'MMS',$DCNo,$this->auth->getIdentity()->UserId,0,0,0);
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                //begin trans try block example ends

                //Common function
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
                $this->redirect()->toRoute('mms/default', array('controller' => 'min','action' => 'minshortclose-register'));
                return $this->_view;
            }
        }
    }

    public function minshortcloseRegisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "min","action" => "min-shortclose"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $response = $this->getResponse();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                if ($postParam['mode'] == 'first') {

                    $regSelect = $sql->select();
                    $regSelect->from(array("a" => "MMS_DCShortCloseReg"))
                        ->columns(array(new Expression("b.DCRegisterId,b.DCNo As MINNo,
                        Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("b" => "MMS_DCRegister"), "a.DCRegisterId=b.DCRegisterId", array(), $regSelect::JOIN_LEFT)
                        ->Order("DCDate Desc")
                        ->where(array("b.DeleteFlag = 0"));
                    $regStatement = $sql->getSqlStringForSqlObject($regSelect);
                    $resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
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

    public function minshortcloseDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "min","action" => "minshortclose-register"));
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
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $DCRegisterId = $this->bsf->isNullCheck($this->params()->fromPost('DCRegisterId'), 'number');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    // over all delete
                    $updDcreg = $sql->update();
                    $updDcreg->table('MMS_DCRegister');
                    $updDcreg->set(array(
                        'ShortClose' => 0,
                    ));
                    $updDcreg->where(array('DCRegisterId' => $DCRegisterId));
                    $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                    $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $del = $sql->delete();
                    $del->from('MMS_DCShortCloseReg')
                        ->where(array('DCRegisterId' => $DCRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($del);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_DCGroupTrans"))
                        ->columns(array("DCGroupId"))
                        ->join(array("b"=>"MMS_DCRegister"), "a.DCRegisterId=b.DCRegisterId", array(), $select::JOIN_INNER)
                        ->where(array("a.DCRegisterId" => $DCRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $prev = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($prev as $arrprev) {

                        $updatedc = $sql->update();
                        $updatedc->table('MMS_DCGroupTrans');
                        $updatedc->set(array(
                            'ShortClose' => 0,
                        ));
                        $updatedc->where(array('DCGroupId' => $arrprev['DCGroupId']));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatedc);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();

                    $status = 'deleted';

                }catch (PDOException $e) {
                    $connection->rollback();
                    $response->setStatusCode(400)->setContent($status);
                }
                $response->setContent($status);
                return $response;

            }
        }
    }

    public function minreportAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast || MMS");
        $viewRenderer = $this->serviceLocator->get( "Zend\View\Renderer\RendererInterface" );
        $dbAdapter = $this->serviceLocator->get( "Zend\Db\Adapter\Adapter" );
        $sql = new Sql( $dbAdapter );
        $subscriberId = $this->auth->getIdentity()->UserId;

        if($this->getRequest()->isPost()) {
            $response = $this->getResponse();
            if ($viewRenderer->commonHelper()->verifyCsrf($this->params()->fromPost('csrf')) == FALSE) {
                // CSRF attack
                if($this->getRequest()->isXmlHttpRequest())    {
                    // AJAX
                    $response->setStatusCode(401)
                        ->setContent('CSRF attack');
                    return $response;
                } else {
                    // Normal
                    $this->redirect()->toRoute("cb/default", array("controller" => "index","action" => "login"));
                    return;
                }
            }
        } else {
            $this->_view->csrf = $viewRenderer->commonHelper()->getCsrfKey();
        }
        $request = $this->getRequest();

        if ($request->isPost()) {
            $this->redirect()->toRoute("min/register", array("controller" => "min","action" => "register"));
        }

        $dir = 'public/min/header/'. $subscriberId;
        $filePath = $dir.'/v1_template.phtml';

        $dirfooter = 'public/min/footer/'. $subscriberId;
        $filePath1 = $dirfooter.'/v1_template.phtml';

        $DCRegisterId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($DCRegisterId == 0)

            $this->redirect()->toRoute("min/register", array("controller" => "min","action" => "register"));

        if (!file_exists($filePath)) {
            $filePath = 'public/min/header/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/min/footer/footertemplate.phtml';
        }

        $template = file_get_contents($filePath);
        $this->_view->template = $template;

        $footertemplate = file_get_contents($filePath1);
        $this->_view->footertemplate = $footertemplate;

        $regSelect = $sql->select();
        $regSelect->from(array("a" => "MMS_DCRegister"))
            ->columns(array(new Expression("a.DCRegisterId,Convert(Varchar(10),a.DCDate,103) As MINDate,
			   c.CostCentreName AS CostCentre,a.SiteDCNo As SiteMINNo,Convert(Varchar(10),a.SiteDCDate,103) As SiteMINDate,
			   a.CCDCNo As CCMinNo,a.CDCNo AS CMinNo,a.DCNo As MINNo,
			   Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve, a.Narration as Purpose")))
            ->join(array("b" => "Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $regSelect::JOIN_LEFT)
            ->join(array("c"=>"WF_OperationalCostCentre"), "a.CostCentreId=c.CostCentreId", array("CostCentreName"), $regSelect::JOIN_LEFT)
            ->join(array("d"=>"WF_CostCentre"), "c.CostCentreId=d.CostCentreId", array("Address"), $regSelect::JOIN_LEFT)
            ->join(array("e"=>"WF_CityMaster"), "d.CityId=e.CityId", array("CityName"), $regSelect::JOIN_LEFT)
            ->join(array("f"=>"WF_StateMaster"), "d.StateId=f.StateId", array("StateName"), $regSelect::JOIN_LEFT)
            ->join(array("g"=>"WF_CountryMaster"), "d.CountryId=g.CountryId", array("CountryName"), $regSelect::JOIN_LEFT)
            ->where(array("a.DeleteFlag=0 and a.DCRegisterId=$DCRegisterId"))
            ->order("a.CreatedDate Desc");
        $regStatement = $sql->getSqlStringForSqlObject($regSelect);
        $this->_view->reqregister = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $trans = $sql->select();
        $trans->from(array("a" => "MMS_DCGroupTrans"))
            ->columns(array(new Expression("(ROW_NUMBER() OVER(PARTITION by A.DCRegisterId Order by A.DCRegisterId asc)) as SNo,a.DCRegisterId,a.DCGroupId,a.ResourceId,a.ItemId,Case When a.ItemId>0 Then d.ItemCode Else b.Code End As Code,
			Case When a.ItemId>0 Then d.BrandName Else b.ResourceName End As ResourceName,c.UnitName,CAST(a.DCQty As Decimal(18,5)) As Qty, CAST(a.AcceptQty As Decimal(18,5)) As AcceptQty,
			CAST(a.RejectQty As Decimal(18,5)) As RejectQty")))
            ->join(array("b" => "Proj_Resource"), "a.ResourceId=b.ResourceId", array(), $trans::JOIN_INNER)
            ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $trans::JOIN_LEFT)
            ->join(array("d" => "MMS_Brand"), "a.ResourceId=d.ResourceId AND a.ItemId=d.BrandId", array(), $trans::JOIN_LEFT)
            ->where(array("a.DCRegisterId"=>$DCRegisterId));
        $transStatement = $sql->getSqlStringForSqlObject($trans);
        $this->_view->register = $dbAdapter->query($transStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }
    public function uploadfileAction(){
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $id = $this->params()->fromRoute('rfqId');
            if ($request->isPost()) {
                //Write your Ajax post code here
                $resp =  array();
                if($id == 0)
                    $dir = 'public/uploads/doc_files/';
                else
                    $dir = 'public/uploads/mms/'.$id.'/';

                if($request->getPost('mode')){
                    unlink($dir.$_POST['fname']);
                }
                else{
                    $files = $request->getFiles();

                    if(!is_dir($dir))
                        mkdir($dir, 0755, true);

                    $i = 1;
                    $fname = $files['file']['name'];
                    $parts = pathinfo($files['file']['name']);
                    while(file_exists($dir.$fname)){
                        $fname = $parts['filename'].' ('.$i.').'.$parts['extension'];
                        $i++;
                    }
                    move_uploaded_file($files["file"]["tmp_name"], $dir.$fname);

                    $resp['fname'] = $fname;
                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if ($request->isPost()){
            //Write your Normal form post code here

        }
    }

	public function qualityTestUploadAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
            $id = $this->params()->fromRoute('qtId');
			if ($request->isPost()) {
				//Write your Ajax post code here
                $postParams = $request->getPost();
//                echo"<pre>";
//               print_r($postParams);
//              echo"</pre>";
//               die;
//              return;
                $resp =  array();
                if($id == 0)
                    $dir = 'public/uploads/doc_files/';

                else

                    $dir = 'public/uploads/mms/QT-files/'.$id.'/';

                if($request->getPost('mode')){
                    unlink($dir.$_POST['f11name']);
                } else {
                    $files = $request->getFiles();

                    if(!is_dir($dir))
                        mkdir($dir, 0755, true);

                    $i = 1;
                    $f1name = $files['file']['name'];
                    $parts = pathinfo($files['file']['name']);
                    while(file_exists($dir.$f1name)){
                        $f1name = $parts['filename'].' ('.$i.').'.$parts['extension'];
                        $i++;
                    }
                    move_uploaded_file($files["file"]["tmp_name"], $dir.$f1name);
                    $resp['f1name'] = $f1name;
                }
			}
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
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
}