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
use Application\View\Helper\Qualifier;

class TransferController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function tvwizardAction(){
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

                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                switch($Type) {
//                case 'fromCC':
//                    $FCompanyId = $this->bsf->isNullCheck($this->params()->fromPost('fcompanyid'), 'number');
//
//                    $selectFCC = $sql->select();
//                    $selectFCC->from(array("a" => "WF_OperationalCostCentre"))
//                        ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName")))
//                        ->where(array('a.CompanyId' => $FCompanyId));
//                    $statement = $sql->getSqlStringForSqlObject($selectFCC);
//                    $arr_fcc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $response->setStatusCode('200');
//                    $response->setContent(json_encode($arr_fcc));
//                    return $response;
//                    break;
//                case 'resourcelist':
//                    $FCCId = $this->bsf->isNullCheck($this->params()->fromPost('fccid'),'number');
//                    $selRes = $sql -> select();
//                    $selRes->from(array("a"=>"Proj_Resource"))
//                        ->columns(array("Code"=>new Expression("Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End "),
//                            "Resource"=>new Expression("Case When isnull(c.BrandId,0)>0 Then c.BrandName Else a.ResourceName End"),
//                            "Unit"=>new Expression("Case When isnull(c.BrandId,0)>0 Then e.UnitName Else d.UnitName End"),
//                            "Include"=>new Expression("Convert(bit,0,1)"),"ResourceId"=>new Expression("a.ResourceId"),
//                            "ItemId"=>new Expression("isnull(c.BrandId,0)")  ))
//                        ->join(array("b"=>"Proj_ProjectResource"),"a.ResourceId=b.ResourceId",array(),$selRes::JOIN_INNER)
//                        ->join(array("c"=>"MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRes::JOIN_LEFT)
//                        ->join(array("d"=>"Proj_UOM"),"a.UnitId=d.UnitId",array(),$selRes::JOIN_LEFT)
//                        ->join(array("e"=>"Proj_UOM"),"c.UnitId=e.UnitId",array(),$selRes::JOIN_LEFT)
//						->join(array('f' => 'WF_OperationalCostCentre'),'b.ProjectId=f.ProjectId',array(),$selRes::JOIN_INNER)
//                        ->where(array('f.CostCentreId' => $FCCId));
//                    $statement = $sql->getSqlStringForSqlObject($selRes);
//                    $arr_reslist = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $response->setStatusCode('200');
//                    $response->setContent(json_encode($arr_reslist));
//                    return $response;
//                    break;
                    case 'getrequest':
                        $CCId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'),'number');
                        $select = $sql -> select();
                        $select -> from(array("a"=>"VM_RequestDecision"))
                            ->columns(array(new Expression("Distinct(a.DecisionId) as RequestId,Convert(Varchar(10),a.DecDate,103) As RequestDate,
                        a.RDecisionNo As RequestNo,e.CostCentreName")))
                            ->join(array('b' => 'VM_ReqDecTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_ReqDecQtyTrans'),'b.DecisionId=c.DecisionId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestRegister'),'b.RequestId=d.RequestId',array(),$select::JOIN_INNER)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'d.CostCentreId=e.CostCentreId',array(),$select::JOIN_INNER)
                            ->where("d.CostCentreId=$CCId and (c.TransferQty-c.TranAdjQty)>0 and a.Approve='Y'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                    $attachments[] = array(
//                        'name' => '1',
//                        'type' => '2',
//                        'content' => '3'
//                    );
//
//
//                    $attachments[] = array(
//                        'name' => '5',
//                        'type' => '6',
//                        'content' => '7'
//                    );
//
//                    print_r($attachments); die;


                        $select = $sql->select();
                        $select->from(array("d" => "VM_RequestDecision"))
                            ->columns(array(new Expression("h.TransId As RequestTransId,h.DecisionId As RequestId,0 As Include,(h.TransferQty-h.TranAdjQty) As Quantity,Convert(Varchar (10),d.DecDate,103) As RequestDate,d.RDecisionNo As RequestNo,Case When i.ItemId>0 Then k.BrandName Else j.ResourceName End As [Desc]")))
                            ->columns(array(new Expression("h.TransId As RequestTransId,h.DecisionId As RequestId,0 As Include,CAST((h.TransferQty-h.TranAdjQty) As Decimal(18,3)) As Quantity,Convert(Varchar(10),d.DecDate,103) As RequestDate,d.RDecisionNo As RequestNo,Case When i.ItemId>0 Then k.BrandName Else j.ResourceName End As [Desc]")))
                            ->join(array("g"=>'VM_ReqDecTrans'),'d.DecisionId=g.DecisionId',array(),$select::JOIN_INNER)
                            ->join(array("h"=>'VM_ReqDecQtyTrans'),'g.DecisionId=h.DecisionId',array(),$select::JOIN_INNER)
                            ->join(array('b' => 'VM_RequestRegister'), 'g.RequestId=b.RequestId', array(), $select::JOIN_LEFT)
                            ->join(array('i'=>'VM_RequestTrans'),'h.ReqTransId=i.RequestTransId and b.RequestId=i.RequestId',array(),$select::JOIN_INNER)
                            ->join(array('j'=>'Proj_Resource'),'i.ResourceId=j.ResourceId',array(),$select::JOIN_INNER)
                            ->join(array('k'=>'MMS_Brand'),'k.BrandId=i.ItemId and k.ResourceId=i.ResourceId',array(),$select::JOIN_LEFT)
                            ->where("(h.TransferQty-h.TranAdjQty)>0 and d.Approve='Y' and b.CostCentreId=$CCId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('requests' => $requests, 'resources' => $requestResources)));
                        return $response;
                }

                //Write your Ajax post code here
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {
            $sel1 = $sql -> select();
            $sel1 -> columns(array(new Expression("0 As CompanyId,'None' As CompanyName") ));

            $select = $sql->select();
            $select->from( array( 'a' => 'WF_CompanyMaster' ))
                ->columns( array( 'CompanyId', 'CompanyName' ));
            $sel1->combine($select, 'Union ALL');

            $statement = $sql->getSqlStringForSqlObject( $sel1 );
            $this->_view->arr_company = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();


            $selectFCC = $sql->select();
            $selectFCC->from(array("a" => "WF_OperationalCostCentre"))
                ->columns(array("CostCentreId"=>new Expression("a.CostCentreId"),"CostCentreName"=>new Expression("a.CostCentreName")));
            //->where(array('a.CompanyId' => $FCompanyId));
            $statement = $sql->getSqlStringForSqlObject($selectFCC);
            $this->_view->arr_fcc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

    public function tventryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "transfer","action" => "tvwizard"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $this->_view->tranRegId = 0;
        $flag = $this->bsf->isNullCheck($this->params()->fromRoute('flag'), 'number');

//        if(!$this->getRequest()->isXmlHttpRequest() && $tranRegId == 0 && !$request->isPost()) {
//            $this->redirect()->toRoute('mms/default', array('controller' => 'transfer','action' => 'tvwizard'));
//        }

        if($this->getRequest()->isXmlHttpRequest())	{

            if ($request->isPost()) {
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();
                switch($Type) {
                    case 'autoDec':
                        $costcentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                        $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $itemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "VM_RequestDecision"))
                            ->columns(array(new Expression('a.DecisionId,b.TransId As DecTransId,b.ReqTransId,
                                a.RDecisionNo As DecisionNo,c.ResourceId,c.ItemId,
                                CAST((b.TransferQty-b.TranAdjQty) As Decimal(18,3)) As BalQty,
                                Cast(0 as Decimal(18,3)) As Qty')))
                            ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
                            ->where('d.CostCentreId=' . $costcentreId . ' and
                                    c.ResourceId =' .$resourceId. 'and
                                    c.ItemId =' .$itemId. ' and
                                    b.TransferQty-b.TranAdjQty > 0');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $resp['decision'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $selWh = $sql -> select();
                        $selWh->from(array("a" => "MMS_WareHouse"))
                            ->columns(array("StockId"=>new Expression("e.StockId"),"WareHouseId" => new Expression("c.transid"),
                                "ResourceId"=>new Expression("e.ResourceId"),"ItemId"=>new Expression("e.ItemId"),
                                "WareHouseName" => new Expression("a.WareHouseName"),"Description"=>new Expression("c.Description"),
                                "ClosingStock"=>new Expression("CAST(d.ClosingStock As Decimal(18,3))"),
                                "Qty"=>new Expression("CAST(0 As Decimal(18,3))"),"HiddenQty"=>new Expression("CAST(0 As Decimal(18,3))")   ))
                            ->join(array("b" => "MMS_CCWareHouse"),'a.WareHouseId=b.WareHouseId',array(),$selWh::JOIN_INNER)
                            ->join(array("c" => "MMS_WareHouseDetails"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
                            ->join(array("d" => "MMS_StockTrans"),"c.TransId=d.WareHouseId",array(),$selWh::JOIN_INNER)
                            ->join(array("e" => "MMS_Stock"),"d.StockId=e.StockId and b.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
                            ->where('b.CostCentreId='. $costcentreId .' and c.LastLevel=1 and d.ClosingStock>0 and
                            (e.ResourceId IN (Select B.ResourceId From VM_ReqDecQtyTrans A Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$resourceId.')) and
                            e.ItemId IN (Select B.ItemId From VM_ReqDecQtyTrans A Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('. $itemId .')))    ');
                        $statement = $sql->getSqlStringForSqlObject($selWh);
                        $resp['warehouse'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($resp));
                        return $response;
                        break;

                    case 'selectAccount':

                        $PurTypeId = $this->bsf->isNullCheck($this->params()->fromPost('ptypeId'), 'number');
                        $selectSub = $sql->select();
                        $selectSub->from(array("a" => "MMS_PurchaseType"))
                            ->columns(array(new Expression('c.AccountId,c.AccountName')))
                            ->join(array('b' => 'FA_AccountType'), "a.AccountTypeId=b.TypeId", array(), $selectSub::JOIN_INNER)
                            ->join(array('c' => 'FA_AccountMaster'), "b.TypeId=c.TypeId", array("data" => 'AccountId', "value" => 'AccountName'), $selectSub::JOIN_INNER)
                            ->where(array('a.PurchaseTypeId IN (7,8)', 'a.PurchaseTypeId' => $PurTypeId));

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_PurchaseType"))
                            ->columns(array(new Expression('b.AccountId,b.AccountName')))
                            ->join(array('b' => 'FA_AccountMaster'), "a.AccountId=b.AccountId", array("data" => 'AccountId', "value" => 'AccountName'), $select::JOIN_INNER)
                            ->where(array('a.PurchaseTypeId' => $PurTypeId));
                        $select->combine($selectSub, 'Union ALL');
                        $accountStatement = $sql->getSqlStringForSqlObject($select);
                        $resp['resultAcc'] = $dbAdapter->query($accountStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response->setContent(json_encode($resp));
                        return $response;
                        break;
                    case 'getClosingStock':
                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('resourceid'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('itemid'), 'number');
                        $CCId = $this->bsf->isNullCheck($this->params()->fromPost('costcentreid'), 'number');
                        $selStock = $sql->select();
                        $selStock->from('mms_stock')
                            ->columns(array("ClosingStock"))
                            ->where(array("resourceId" => $ResId,"itemId" => $ItemId,"costCentreId" => $CCId));
                        $stStatement = $sql->getSqlStringForSqlObject($selStock);
                        $result = $dbAdapter->query($stStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode($result));
                        return $response;
                        break;
                    case 'getqualdetails':
                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $tranId=$this->bsf->isNullCheck($this->params()->fromPost('tRId'), 'number');


                        $selSub2 = $sql -> select();
                        $selSub2->from(array("a" => "MMS_TransferQualTrans"))
                            ->columns(array("QualifierId"));
                        $selSub2->where(array('a.TVRegisterId' => $tranId,'a.ResourceId' => $ResId, 'a.ItemId' => $ItemId ));

                        $selSub1 = $sql -> select();
                        $selSub1->from(array("a" => "Proj_QualifierTrans"))
                            ->columns(array('ResourceId'=>new Expression("'$ResId'"),'ItemId'=>new Expression("'$ItemId'"),'QualifierId'=>new Expression('a.QualifierId'),
                                'YesNo'=>new Expression("'0'"),'Expression'=>new Expression('a.Expression'),
                                'ExpPer'=>new Expression('a.ExpPer'),'TaxablePer'=>new Expression('a.TaxablePer'),'TaxPer'=>new Expression('a.TaxPer'),
                                'Sign'=>new Expression('a.Sign'),'SurCharge'=>new Expression('a.SurCharge'),'EDCess'=>new Expression('a.EDCess'),
                                'HEDCess'=>new Expression('a.HEDCess'),'NetPer'=>new Expression('a.NetPer'),'BaseAmount'=>new Expression("'0'"),
                                'ExpressionAmt'=>new Expression("'0'"),'TaxableAmt'=>new Expression("'0'"),'TaxAmt'=>new Expression("'0'"),
                                'SurChargeAmt'=>new Expression("'0'"),'EDCessAmt'=>new Expression("'0'"),'HEDCessAmt'=>new Expression("'0'"),
                                'NetAmt'=>new Expression("'0'"),'QualifierName'=>new Expression('b.QualifierName'),'QualifierTypeId'=>new Expression('b.QualifierTypeId'),
                                'RefId'=>new Expression('b.RefNo'),'SortId'=>new Expression('a.SortId') ))
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId",array(),$selSub1::JOIN_INNER)
                            ->where->expression('a.QualType='."'M'".' and a.QualifierId NOT IN ?', array($selSub2));

                        $select = $sql->select();
                        $select->from(array("c" => "MMS_TransferQualTrans"))
                            ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),'QualifierId'=>new Expression('c.QualifierId'),
                                'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
                                'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
                                'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
                                'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),
                                'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,2)) '),
                                'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,2)) '),
                                'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,2)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
                                'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,2)) '),'QualifierName'=>new Expression('b.QualifierName'),
                                'QualifierTypeId'=>new Expression('b.QualifierTypeId'),'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
                            ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);

                        $select->where(array('a.QualType' => 'M', 'c.TVRegisterId' => $tranId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
                        $select -> combine($selSub1,"Union All");
                        $selMain = $sql -> select()->from(array('result'=>$select));
                        $selMain->order('SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($selMain);
                        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sHtml = Qualifier::getQualifier($qualList);
                        //$this->_view->qualHtml = $sHtml;

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($sHtml));
                        return $response;
                        break;
                    case 'getstockdetails':
                        $CCId = $this -> bsf ->isNullCheck($this->params()->fromPost('CostCenterId'),'number');
                        $ResId = $this -> bsf ->isNullCheck($this->params()->fromPost('resourceid'),'number');

                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectResource"))
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            //->Where (' ProjectId=' . $CCId .' And ResourceId=' .$ResId. ' ');
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where (' b.CostCentreId=' . $CCId .' And ResourceId=' .$ResId. ' ');

                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId=' .$ResId. ' And b.CostCentreId='.$CCId.' And c.General=0');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,2))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId='.$ResId.' And B.CostCentreId='.$CCId .' And B.General=0 ');
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,2))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('b.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.General=0 ');
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,2))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,2))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' And b.CostCentreId='.$CCId.'');
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                            ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.ToCostCentreId='.$CCId.' ');

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.FromCostCentreId='.$CCId.'');
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,2))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel13 = $sql -> select();
                        $sel13 -> from(array("a" => "MMS_IssueTrans"))
                            -> columns(array('IssueQty' => new Expression("ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>"MMS_IssueRegister"),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel13::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.IssueOrReturn=1 ');

                        $sel14 = $sql -> select();
                        $sel14 -> from(array("a" => "MMS_IssueTrans"))
                            -> columns(array('IssueQty' => new Expression("-1 * ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>'MMS_IssueRegister'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel14::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.IssueOrReturn=0');
                        $sel14->combine($sel13,"Union ALL");

                        $sel15 = $sql -> select();
                        $sel15 -> from(array("A"=>$sel14))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'IssueQty'=>new Expression("CAST(SUM(IssueQty) As Decimal(18,3))") ));
                        $sel15 -> combine($sel7,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'BalReqQty'=>new Expression("ISNULL(SUM(A.BalQty),0)"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.'');
                        $sel8->combine($sel15,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,2))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,2))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('b.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,2))"),'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,2))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,2))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,2))"),'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,2))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,2))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(ISNULL(SUM(G.IssueQty),0) As Decimal(18,3))"),'TransferQty'=>new Expression("CAST(ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),'ReturnQty'=>new Expression("CAST(ISNULL(SUM(G.TotRetQty),0) As Decimal(18,3))")
                            ));

                        $statement = $sql->getSqlStringForSqlObject($sel12);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock));
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
//                echo"<pre>";
//             print_r($postData);
//            echo"</pre>";
//             die;
//            return;


                if (!is_null($postData['frm_index'])) {
//                    $FCompanyId = $this->bsf->isNullCheck($postData['from_company'], 'number');
//                    $TCompanyId = $this->bsf->isNullCheck($postData['to_company'], 'number');
                    $fProjId = $this->bsf->isNullCheck($postData['from_project'], 'number');
                    $tProjId = $this->bsf->isNullCheck($postData['to_project'], 'number');
                    $gridtype=$this->bsf->isNullCheck($postData['gridtype'], 'number');
                    $this->_view->gridtype = $gridtype;
                    $this->_view->adjustment = 0;
                    if(count($postData['requestTransIds']) > 0) {
                        $requestTransIds = implode(',', $postData['requestTransIds']);
                    } else {
                        $requestTransIds =0;
                    }
                    if($flag == 1){
                        $select = $sql->select();
                        $select->from(array("a" => "VM_ReqDecTrans"))
                            ->columns(array(new Expression("b.CostCentreId as CostCentreId")))
                            ->join(array("b" => "VM_RequestRegister"), 'a.RequestId=b.RequestId', array(), $select::JOIN_INNER)
                            ->join(array("c" => "VM_ReqDecQtyTrans"), 'c.DecisionId=a.DecisionId', array(), $select::JOIN_INNER)
                            ->where('c.TransId IN(' .$requestTransIds. ')');
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $cvName = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $tProjId = $this->bsf->isNullCheck($cvName['CostCentreId'],'number');
                    }


                    //from costcentre
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId','CostCentreName','CompanyId'))
                        ->where("CostCentreId=$fProjId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $fcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $fCompanyId = $fcostcentre['CompanyId'];
                    $this->_view->fcostcentre = $fcostcentre;

                    //to costcentre
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId','CostCentreName','CompanyId'))
                        ->where("CostCentreId=$tProjId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $tcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $tCompanyId = $tcostcentre['CompanyId'];
                    $this->_view->tcostcentre = $tcostcentre;


                    //General
                    $voNo = CommonHelper::getVoucherNo(308, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->voNo = $voNo;
                    $vNo=$voNo['voucherNo'];
                    $this->_view->vNo = $vNo;

                    //CompanyId
                    $CTransfer = CommonHelper::getVoucherNo(308, date('Y/m/d'), $fCompanyId, 0, $dbAdapter, "");
                    $this->_view->CTransfer = $CTransfer;
                    $CTVNo=$CTransfer['voucherNo'];
                    $this->_view->CTVNo = $CTVNo;

                    //CostCenterId
                    $CCTransfer = CommonHelper::getVoucherNo(308, date('Y/m/d'), 0, $fProjId, $dbAdapter, "");
                    $this->_view->CCTransfer = $CCTransfer;
                    $CCTVNo=$CCTransfer['voucherNo'];
                    $this->_view->CCTVNo = $CCTVNo;

                    // from company
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_CompanyMaster'))
                        ->columns(array('CompanyId','CompanyName'))
                        ->where("CompanyId=$fCompanyId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->fcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //to company
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_CompanyMaster'))
                        ->columns(array('CompanyId','CompanyName'))
                        ->where("CompanyId=$tCompanyId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //PurchaseType
                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array("Default"),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->order("b.Default Desc")
                        ->where('c.CostCentreId='.$fProjId.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    //IsWareHouse
                    $select = $sql -> select();
                    $select->from(array("a" => "MMS_CCWareHouse"))
                        ->columns(array("WareHouseId"))
                        ->where('a.CostCentreId='.$fProjId);
                    $whStatement = $sql->getSqlStringForSqlObject($select);
                    $isWareHouse = $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->isWh = $isWareHouse;

                    $select = $sql->select();
                    $select->from(array("a" => "VM_ReqDecQtyTrans"))
                        ->columns(array(new Expression("b.ResourceId,b.ItemId,
                        Case When b.ItemId>0 Then d.ItemCode+ ' ' +d.BrandName Else c.Code+' '+c.ResourceName End As [Desc],
                         CAST(ISNULL(SUM(a.TransferQty-a.TranAdjQty),0) As Decimal(18,3)) As Qty,
                        Case When b.ItemId>0 Then d.Rate Else c.Rate End As Rate,
                        Case When b.ItemId>0 Then d.Rate Else c.Rate End As QRate,CAST(0 As Decimal(18,2)) As BaseAmount,
                        CAST(0 As Decimal(18,2)) As Amount,Case When b.ItemId>0 Then f.UnitName Else e.UnitName End As UnitName,
                        Case When b.ItemId>0 Then f.UnitId Else e.UnitId End As UnitId ")))
                        ->join(array('b' => 'VM_RequestTrans'), 'a.ReqTransId=b.RequestTransId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_Resource'), 'b.ResourceId=c.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'MMS_Brand'), 'b.ItemId=d.BrandId and b.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=E.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'Proj_UOM'), 'd.UnitId=F.UnitId', array(), $select::JOIN_LEFT)
                        ->where('a.TransId IN (' . $requestTransIds . ')')
                        ->group(new Expression('b.ResourceId,b.ItemId,d.ItemCode,d.BrandName,c.Code,c.ResourceName,e.UnitId,e.UnitName,f.UnitId,f.UnitName,c.Rate,d.Rate'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "VM_RequestDecision"))
                        ->columns(array(new Expression('a.DecisionId,b.TransId As DecTransId,b.ReqTransId,a.RDecisionNo As DecisionNo,
                        c.ResourceId,c.ItemId,CAST((b.TransferQty-b.TranAdjQty) As Decimal(18,3)) As BalQty,
                        Cast((b.TransferQty-b.TranAdjQty) as Decimal(18,3)) As Qty')))
                        ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
                        ->where('d.CostCentreId=' . $tProjId . ' and b.TransId IN (' . $requestTransIds . ') and b.TransferQty-b.TranAdjQty > 0');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

//                    $select = $sql->select();
//                    $select->from(array("a" => "VM_RequestDecision"))
//                        ->columns(array(new Expression("f.ParentText+'->'+f.WBSName As WBSName,a.DecisionId,c.TransId As DecTransId,c.RCATransId As DecATransId,c.ReqTransId,c.ReqAHTransId,d.ResourceId,d.ItemId,d.AnalysisId As WBSId,CAST((c.TransferQty-c.TranAdjQty) As Decimal(18,5)) As BalQty,CAST(0 As Decimal(18,5)) As Qty")))
//                        ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
//                        ->join(array('c' => 'VM_ReqDecQtyAnalTrans'), 'b.TransId=c.TransId and b.DecisionId=c.DecisionId', array(), $select::JOIN_INNER)
//                        ->join(array('d' => 'VM_RequestAnalTrans'), 'c.ReqAHTransId=d.RequestAHTransId and b.ReqTransId=d.ReqTransId', array(), $select::JOIN_INNER)
//                        ->join(array('e' => 'VM_RequestTrans'), 'b.ReqTransId=e.RequestTransId and d.ReqTransId=e.RequestTransId', array(), $select::JOIN_INNER)
//                        ->join(array('f' => 'Proj_WBSMaster'), 'd.AnalysisId=f.WBSId', array(), $select::JOIN_INNER)
//                        ->join(array('g' => 'VM_RequestRegister'), 'e.RequestId=g.RequestId', array(), $select::JOIN_INNER)
//                        ->where('g.CostCentreId=' . $FProjId . ' and b.TransId IN (' . $requestTransIds . ')');
//
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_QualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','NetPer',
                            'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
                    $select->where(array('a.QualType' => 'M'));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml=Qualifier::getQualifier($qualList);
                    $this->_view->qualHtml = $sHtml;

                    $selWh = $sql -> select();
                    $selWh->from(array("a" => "MMS_WareHouse"))
                        ->columns(array("StockId"=>new Expression("e.StockId"),"WareHouseId" => new Expression("c.transid"),
                            "ResourceId"=>new Expression("e.ResourceId"),"ItemId"=>new Expression("e.ItemId"),
                            "WareHouseName" => new Expression("a.WareHouseName"),"Description"=>new Expression("c.Description"),
                            "ClosingStock"=>new Expression("CAST(d.ClosingStock As Decimal(18,3))"),
                            "Qty"=>new Expression("CAST(0 As Decimal(18,3))"),"HiddenQty"=>new Expression("CAST(0 As Decimal(18,3))")   ))
                        ->join(array("b" => "MMS_CCWareHouse"),'a.WareHouseId=b.WareHouseId',array(),$selWh::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
                        ->join(array("d" => "MMS_StockTrans"),"c.TransId=d.WareHouseId",array(),$selWh::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"),"d.StockId=e.StockId and b.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
                        ->where('b.CostCentreId='. $fProjId .' and c.LastLevel=1 and d.ClosingStock>0 and
                            (e.ResourceId IN (Select B.ResourceId From VM_ReqDecQtyTrans A Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) and
                            e.ItemId IN (Select B.ItemId From VM_ReqDecQtyTrans A Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('. $requestTransIds .')))    ');
                    $statement = $sql->getSqlStringForSqlObject($selWh);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        //->columns(array("Code", "ResourceId", "ResourceName"), array("ResourceGroupName", "ResourceGroupId"), array("UnitName", "UnitId"))
                        ->columns(array(new Expression("a.ResourceId as data,isnull(d.BrandId,0) As ItemId,
                        Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                        Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                        Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
                        Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
                        Case when isnull(d.BrandId,0)>0 Then d.Rate Else e.Rate End As Rate ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                        ->join(array('g' => 'WF_OperationalCostCentre'),'e.ProjectId=g.ProjectId',array(),$select::JOIN_INNER)
                        ->where("a.TypeId IN (2,3) and g.CostCentreId=" . $fProjId );

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate else a.Rate End As Rate  ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From
                                Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=". $fProjId .")   ");

                    $select -> combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                }
                //Write your Normal form post code here

            }
            else
            {
                $tranRegId = $this->bsf->isNullCheck($this->params()->fromRoute('rid'), 'number');
                $this->_view->tranRegId=$tranRegId;
                $postData = $request->getPost();
                if(isset($tranRegId) && $tranRegId!='') {

                    $selRData = $sql -> select();
                    $selRData -> from(array('a'=>'MMS_TransferRegister'))
                        ->columns(array("TVDate"=> new Expression('Convert(Varchar(10),a.TVDate,103)'),
                            "GPDate"=> new Expression('Convert(Varchar(10),a.GPDate,103)'),
                            "TVNo"=>"TVNo","CCTVNo"=>"CCTVNo","CTVNo"=>"CTVNo","GridType" => "GridType",
                            "FromCompanyId"=>"FromCompanyId","FromCostCentreId"=>"FromCostCentreId","ToCompanyId"=>"ToCompanyId",
                            "ToCostCentreId"=>"ToCostCentreId","GPNo"=>"GPNo","Narration"=>"Narration","Approve"=>"Approve",
                            "Adjustment"=>new expression("Case when Adjustment=1 then 'on' else 'off' end"),
                            "Indirect"=>new expression("Case when Indirect=1 Then 'on' else 'off' end " ),
                            "PurchaseTypeId"=>"PurchaseTypeId","AccountId"=>"AccountId" ))
                        ->where("a.TVRegisterId=".$tranRegId);
                    $statement = $sql->getSqlStringForSqlObject($selRData);
                    $this->_view->tranReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    $FCompanyId = $this->_view->tranReg['FromCompanyId'];
                    $TCompanyId = $this->_view->tranReg['ToCompanyId'];
                    $FProjId = $this->_view->tranReg['FromCostCentreId'];
                    $TProjId = $this->_view->tranReg['ToCostCentreId'];
                    $vNo=$this->_view->tranReg['TVNo'];
                    $purchasetype=$this->_view->tranReg['PurchaseTypeId'];
                    $tvdate=$this->_view->tranReg['TVDate'];
                    $gpno=$this->_view->tranReg['GPNo'];
                    $gpdate=$this->_view->tranReg['GPDate'];
                    $cctvno=$this->_view->tranReg['CCTVNo'];
                    $ctvno=$this->_view->tranReg['CTVNo'];
                    $indirect=$this->_view->tranReg['Indirect'];
                    $adjustment=$this->_view->tranReg['Adjustment'];
                    $accounttype=$this->_view->tranReg['AccountId'];
                    $narration=$this->_view->tranReg['Narration'];
                    $Approve=$this->_view->tranReg['Approve'];
                    $gridtype=$this->_view->tranReg['GridType'];


                    $this->_view->vNo = $vNo;
                    $this->_view->purchasetype = $purchasetype;
                    $this->_view->TVDate = $tvdate;
                    $this->_view->GPNo = $gpno;
                    $this->_view->gpdate = $gpdate;
                    $this->_view->CCTVNo = $cctvno;
                    $this->_view->CTVNo = $ctvno;
                    $this->_view->indirect = $indirect;
                    $this->_view->adjustment = $adjustment;
                    $this->_view->accounttype = $accounttype;
                    $this->_view->narration = $narration;
                    $this->_view->tranRegId= $tranRegId;
                    $this->_view->Approve= $Approve;
                    $this->_view->gridtype = $gridtype;
                    // from company
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_CompanyMaster'))
                        ->columns(array('CompanyId','CompanyName'))
                        ->where("CompanyId=$FCompanyId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->fcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //to company
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_CompanyMaster'))
                        ->columns(array('CompanyId','CompanyName'))
                        ->where("CompanyId=$TCompanyId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //from costcentre
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId','CostCentreName'))
                        ->where("CostCentreId=$FProjId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->fcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //to costcentre
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId','CostCentreName'))
                        ->where("CostCentreId=$TProjId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    //PurchaseType
                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array(),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->where('c.CostCentreId='.$FProjId.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    $selAcc=$sql->select();
                    $selAcc->from(array("a"=>"FA_AccountMaster"))
                        ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
                        ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
                        ->where(array("b.PurchaseTypeId"=>$purchasetype));
                    $accStatement = $sql->getSqlStringForSqlObject($selAcc);
                    $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->accType = $accType;

                    //IsWareHouse
                    $select = $sql -> select();
                    $select->from(array("a" => "MMS_CCWareHouse"))
                        ->columns(array("WareHouseId"))
                        ->where('a.CostCentreId='.$FProjId);
                    $whStatement = $sql->getSqlStringForSqlObject($select);
                    $isWareHouse = $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->isWh = $isWareHouse;

                    $select = $sql -> select();
                    $select -> from (array("a" => "MMS_TransferTrans"))
                        ->columns(array('ResourceId','ItemId','Desc'=>new Expression("Case When a.ItemId>0 Then d.ItemCode+ ' ' +d.BrandName Else c.Code +' '+c.ResourceName End"),
                            'Qty'=>new Expression("CAST(a.TransferQty As Decimal(18,6))"),'Rate'=>new Expression("CAST(a.Rate As Decimal(18,2))"),
                            'QRate'=>new Expression("CAST(a.QRate As Decimal(18,2))"),'BaseAmount'=>new Expression("CAST(a.Amount As Decimal(18,2))"),
                            'Amount'=>new Expression("CAST(a.QAmount As Decimal(18,2))")))

                        -> join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$select::JOIN_INNER)
                        -> join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$select::JOIN_INNER)
                        -> join(array("d" => "MMS_Brand"),"a.ItemId=d.BrandId and a.ResourceId=d.ResourceId",array(),$select::JOIN_LEFT)
                        -> join(array("e" => "Proj_Uom"),"a.UnitId=e.UnitId",array("UnitId","UnitName"),$select::JOIN_LEFT)
                        ->where ("a.TransferRegisterId=".$tranRegId."");

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $selDec = $sql -> select();
                    $selDec->from(array("a" => "MMS_IPDTrans"))
                        ->columns(array(new Expression('c.DecisionId,c.TransId As DecTransId,c.ReqTransId,d.RDecisionNo As DecisionNo,b.ResourceId,b.ItemId,CAST((c.TransferQty-c.TranAdjQty) As Decimal(18,6)) As BalQty,CAST(a.Qty As Decimal(18,6)) As Qty,CAST(a.Qty As Decimal(18,6)) As HiddenQty')))
                        ->join(array("b" => "MMS_TransferTrans"),"a.TransferTransId=b.TransferTransId",array(),$selDec::JOIN_INNER)
                        ->join(array("c" => "VM_ReqDecQtyTrans"),"a.DecTransId=c.TransId and a.DecisionId=c.DecisionId",array(),$selDec::JOIN_INNER)
                        ->join(array("d" => "VM_RequestDecision"),"c.DecisionId=d.DecisionId",array(),$selDec::JOIN_INNER)
                        ->join(array("e" => "VM_RequestTrans"),"c.ReqTransId=e.RequestTransId",array(),$selDec::JOIN_INNER)
                        ->join(array("f" => "VM_RequestRegister"),"e.RequestId=f.RequestId",array(),$selDec::JOIN_INNER)
                        ->where('b.TransferRegisterId='.$tranRegId);

                    $select = $sql->select();
                    $select->from(array("a" => "VM_RequestDecision"))
                        ->columns(array(new Expression('a.DecisionId,b.TransId As DecTransId,b.ReqTransId,a.RDecisionNo As DecisionNo,c.ResourceId,c.ItemId,CAST((b.TransferQty-b.TranAdjQty) As Decimal(18,6)) As BalQty,Cast(0 as Decimal(18,6)) As Qty,Cast(0 As Decimal(18,6)) As HiddenQty')))
                        ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
                        ->where('d.CostCentreId=' . $TProjId . ' and b.TransId NOT IN (select DecTransId From MMS_IPDTrans A
                             Inner Join MMS_TransferTrans B On A.TransferTransId=B.TransferTransId Where B.TransferRegisterId='.$tranRegId. ' )
                             and CAST((b.TransferQty-b.TranAdjQty) As Decimal(18,6)) > 0');
                    $selDec->combine($select, 'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($selDec);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


//                    $select = $sql->select();
//                    $select->from(array("a" => "VM_RequestDecision"))
//                        ->columns(array(new Expression("f.ParentText+'->'+f.WBSName As WBSName,a.DecisionId,c.TransId As DecTransId,c.RCATransId As DecATransId,c.ReqTransId,c.ReqAHTransId,d.ResourceId,d.ItemId,d.AnalysisId As WBSId,CAST((c.TransferQty-c.TranAdjQty) As Decimal(18,5)) As BalQty,CAST(0 As Decimal(18,5)) As Qty")))
//                        ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
//                        ->join(array('c' => 'VM_ReqDecQtyAnalTrans'), 'b.TransId=c.TransId and b.DecisionId=c.DecisionId', array(), $select::JOIN_INNER)
//                        ->join(array('d' => 'VM_RequestAnalTrans'), 'c.ReqAHTransId=d.RequestAHTransId and b.ReqTransId=d.ReqTransId', array(), $select::JOIN_INNER)
//                        ->join(array('e' => 'VM_RequestTrans'), 'b.ReqTransId=e.RequestTransId and d.ReqTransId=e.RequestTransId', array(), $select::JOIN_INNER)
//                        ->join(array('f' => 'Proj_WBSMaster'), 'd.AnalysisId=f.WBSId', array(), $select::JOIN_INNER)
//                        ->join(array('g' => 'VM_RequestRegister'), 'e.RequestId=g.RequestId', array(), $select::JOIN_INNER)
//                        ->where('g.CostCentreId=' . $FProjId . ' and b.TransId IN (' . $requestTransIds . ')');
//
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_QualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','NetPer',
                            'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
                    $select->where(array('a.QualType' => 'M'));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml=Qualifier::getQualifier($qualList);
                    $this->_view->qualHtml = $sHtml;

                    $arrqual = array();
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_TransferQualTrans"))
                        ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'NetAmt'));
                    $select->where(array('a.TVRegisterId'=>$tranRegId));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arr_qual_list = $qualList;


                    $selWhm = $sql -> select();
                    $selWhm->from(array("a" => "MMS_TransferWareHouseAnalTrans"))
                        ->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
                            "ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
                            "DecTransId" => new Expression("a.DecTransId"),
                            "WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
                            "ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,2))"),
                            "Qty" => new Expression("CAST(a.Qty As Decimal(18,2))"),"HiddenQty"=>new expression("CAST(a.Qty As Decimal(18,2))") ))
                        ->join(array("b" => "MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$selWhm::JOIN_INNER)
                        ->join(array("c" => "MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$selWhm::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$selWhm::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.FromCostCentreId=e.CostCentreId',array(),$selWhm::JOIN_INNER)
                        ->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$selWhm::JOIN_INNER)
                        ->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$selWhm::JOIN_INNER)
                        ->where ("c.TVRegisterId=".$tranRegId."");
                    $statement = $sql->getSqlStringForSqlObject($selWhm);
                    $this->_view->arr_sel_tvwarehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $selWh = $sql -> select();
                    $selWh->from(array("a" => "MMS_WareHouse"))
                        ->columns(array("StockId"=>new Expression("e.StockId"),"WareHouseId" => new Expression("c.transid"),
                            "ResourceId"=>new Expression("e.ResourceId"),"ItemId"=>new Expression("e.ItemId"),
                            "WareHouseName" => new Expression("a.WareHouseName"),"Description"=>new Expression("c.Description"),
                            "ClosingStock"=>new Expression("CAST(d.ClosingStock As Decimal(18,2))"),
                            "Qty"=>new Expression("CAST(0 As Decimal(18,2))"),"HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")   ))
                        ->join(array("b" => "MMS_CCWareHouse"),'a.WareHouseId=b.WareHouseId',array(),$selWh::JOIN_INNER)
                        ->join(array("c" => "MMS_WareHouseDetails"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
                        ->join(array("d" => "MMS_StockTrans"),"c.TransId=d.WareHouseId",array(),$selWh::JOIN_INNER)
                        ->join(array("e" => "MMS_Stock"),"d.StockId=e.StockId and b.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
                        ->where('b.CostCentreId='. $FProjId .' and c.LastLevel=1 and d.ClosingStock>0 and
                            (e.ResourceId IN (Select ResourceId From MMS_TransferTrans  Where TransferRegisterId='.$tranRegId.') and
                            e.ItemId IN (Select ItemId From MMS_TransferTrans Where TransferRegisterId='.$tranRegId.' ))');
                    $statement = $sql->getSqlStringForSqlObject($selWh);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //auto complete process
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.ItemCode+' - '+d.BrandName Else a.Code+' - '+a.ResourceName End As value,c.UnitName,c.UnitId")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                        ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                        ->where('f.ProjectId = ' . $FProjId . ' and (a.ResourceId NOT IN( select resourceid from MMS_TransferTrans where TransferRegisterId = ' .$tranRegId. ' ) OR isnull(d.BrandId,0) NOT IN ( select ItemId from MMS_TransferTrans where TransferRegisterId = ' .$tranRegId. '))');
                    $selRa = $sql -> select();
                    $selRa->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) ItemId,Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code, Case When isnull(c.BrandId,0)>0 Then c.ItemCode + ' - ' + c.BrandName Else a.Code + ' - ' + a.ResourceName End As value,
                           Case When isnull(c.BrandId,0)>0 Then e.UnitName Else d.UnitName End As UnitName,Case When isnull(c.BrandId,0)>0 Then e.UnitId Else d.UnitId End As UnitId ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId',array(),$selRa::JOIN_LEFT)
                        ->join(array('c' => 'MMS_Brand'),'a.ResourceId=c.ResourceId',array(),$selRa::JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'),'a.UnitId=d.UnitId',array(),$selRa::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'),'c.UnitId=e.UnitId',array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$FProjId)
                          and (a.ResourceId Not IN (select resourceid from MMS_TransferTrans where TransferRegisterId =$tranRegId) or isnull(c.BrandId,0) NOT IN (select ItemId from MMS_TransferTrans where TransferRegisterId =$tranRegId) )");
                    $select->combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
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

    public function tvsaveAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Workorder");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(308,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                //echo"<pre>";
                // print_r($postParams);
                // echo"</pre>";die;

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;
//                return;
                $voucherno='';
                $TVRegisterId=$postParams['tranRegId'];
                $FCompanyId= $this->bsf->isNullCheck($postParams['FCompanyId'],'number');
                $TCompanyId= $this->bsf->isNullCheck($postParams['TCompanyId'],'number');
                $FCostCenterId= $this->bsf->isNullCheck($postParams['FCostCenterId'],'number');
                $TCostCenterId= $this->bsf->isNullCheck($postParams['TCostCenterId'],'number');
                $TVDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['TVDate'], 'string')));
                $GPDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['GPDate'], 'string')));
                $TVNo=$this->bsf->isNullCheck($postParams['TVNo'],'string');
                $voucherno=$TVNo;
                $CCTVNo=$this->bsf->isNullCheck($postParams['CCTVNo'],'string');
                $CTVNo=$this->bsf->isNullCheck($postParams['CTVNo'],'string');
                $GPNo=$this->bsf->isNullCheck($postParams['GPNo'],'string');
                $PurTypeId=$this->bsf->isNullCheck($postParams['purchase_type'],'number');
                $AccountId=$this->bsf->isNullCheck($postParams['account_type'],'number');
                $gridtype=$this->bsf->isNullCheck($postParams['gridtype'], 'number');
                $amount=$this->bsf->isNullCheck($postParams['total'], 'number');

                $Adjustment=0;
                $Indirect=1;
                if($this->bsf->isNullCheck($postParams['chkAdjustment'],'string') == 'on')
                {
                    $Adjustment=1;
                }
                else
                {
                    $Adjustment=0;
                }
                if($this->bsf->isNullCheck($postParams['chkIndirect'],'string') == 'on')
                {
                    $Indirect=1;
                }
                else
                {
                    $Indirect=0;
                }
                $Approve="";
                $Role="";

                if ($this->bsf->isNullCheck($TVRegisterId, 'number') > 0) {
                    $Approve="E";
                    $Role="Transfer-Modify";
                }else{
                    $Approve="N";
                    $Role="Transfer-Create";
                }
                //CompanyId
                $CTransfer = CommonHelper::getVoucherNo(308, date('Y/m/d'), $FCompanyId, 0, $dbAdapter, "");
                $this->_view->CTransfer = $CTransfer;
                //CostCenterId
                $CCTransfer = CommonHelper::getVoucherNo(308, date('Y/m/d'), 0, $FCostCenterId, $dbAdapter, "");
                $this->_view->CCTransfer = $CCTransfer;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try
                {
                    if($this->bsf->isNullCheck($TVRegisterId,'number') > 0){

                        $selPrevTrans=$sql->select();
                        $selPrevTrans->from(array("a"=>"MMS_IPDTrans"))
                            ->columns(array(new Expression("a.DecisionId,a.DecTransId,c.ReqTransId,c.DecisionId,a.Qty As Qty ")))
                            ->join(array("b"=>"MMS_TransferTrans"),"a.TransferTransId=b.TransferTransId",array(),$selPrevTrans::JOIN_INNER)
                            ->join(array("c"=>"VM_ReqDecQtyTrans"),"a.DecTransId=c.TransId and a.Decisionid=c.DecisionId",array(),$selPrevTrans::JOIN_INNER)
                            ->where(array("b.TransferRegisterId"=>$TVRegisterId));
                        $statementPrevTrans = $sql->getSqlStringForSqlObject($selPrevTrans);
                        $prevtrans = $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($prevtrans as $arrprevtrans) {
                            $updDecTran=$sql->update();
                            $updDecTran->table('VM_ReqDecQtyTrans');
                            $updDecTran->set(array(
                                'TranAdjQty'=>new Expression('TranAdjQty-'.$arrprevtrans['Qty'].'')
                            ));
                            $updDecTran->where(array('TransId'=>$arrprevtrans['DecTransId'],'DecisionId'=>$arrprevtrans['DecisionId']));
                            $statementPrevTrans = $sql->getSqlStringForSqlObject($updDecTran);
                            $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                            $updReqTrans=$sql->update();
                            $updReqTrans->table('VM_RequestTrans');
                            $updReqTrans->set(array(
                                'TransferQty'=>new Expression('TransferQty-'.$arrprevtrans['Qty'].''),
                                'BalQty'=>new Expression('BalQty+'.$arrprevtrans['Qty'].'')
                            ));
                            $updReqTrans->where(array('RequestTransId'=>$arrprevtrans['ReqTransId']));
                            $statementPrevReqTrans = $sql->getSqlStringForSqlObject($updReqTrans);
                            $dbAdapter->query($statementPrevReqTrans, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        //Stock Update
                        $selTrans=$sql->select();
                        $selTrans->from("MMS_TransferTrans")
                            ->columns(array(new Expression("FCostCentreId ,ResourceId,ItemId,TransferQty,
                            QAmount,Amount As Amount")))
                            ->where(array("TransferRegisterId"=>$TVRegisterId));
                        $statementTrans = $sql->getSqlStringForSqlObject($selTrans);
                        $trantrans = $dbAdapter->query($statementTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($trantrans as $arrtrans)
                        {
                            $updateSt = $sql -> update();
                            $updateSt->table('MMS_Stock');
                            $updateSt->set(array(
                                'TransferQty'=>new Expression('TransferQty+'. $this->bsf->isNullCheck($arrtrans['TransferQty'],'number')  .''),
                                'TransferAmount'=>new Expression('TransferAmount+'. $this->bsf->isNullCheck($arrtrans['QAmount'],'number')  .''),
                                'TransferGAmount'=>new Expression('TransferGAmount+'. $this->bsf->isNullCheck($arrtrans['Amount'],'number') .''),
                                'ClosingStock'=>new Expression('ClosingStock+'. $this->bsf->isNullCheck($arrtrans['TransferQty'],'number') .'')
                            ));
                        }
                        //
                        //Stock Tran Update
                        $selSTrans=$sql->select();
                        $selSTrans->from(array("a"=>"MMS_TransferWareHouseTrans"))
                            ->columns(array(new Expression("a.CostCentreId,a.WareHouseId,a.Qty As Qty")))
                            ->join(array("b"=>"MMS_TransferTrans"),"a.TransferTransId=b.TransferTransId and a.CostCentreId=b.FCostCentreId",array(),$selSTrans::JOIN_INNER)
                            ->join(array("c"=>"MMS_Stock"),"b.ResourceId=c.ResourceId And b.ItemId=c.ItemId and b.FCostCentreId=c.CostCentreId",array(),$selSTrans::JOIN_INNER)
                            ->where(array("b.TransferRegisterId"=>$TVRegisterId));
                        $stranwhtrans = $sql->getSqlStringForSqlObject($selSTrans);
                        $tranwhtrans = $dbAdapter->query($stranwhtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($tranwhtrans as $awh)
                        {
                            $updatewh = $sql -> update();
                            $updatewh->table('MMS_StockTrans');
                            $updatewh->set(array(
                                'TransferQty'=>new Expression('TransferQty+'.$this->bsf->isNullCheck($awh['TransferQty'],'number') .''),
                                'ClosingStock'=>new Expression('ClosingStock+'. $this->bsf->isNullCheck($awh['TransferQty'],'number') .'')
                            ));
                        }
                        //
                        $delIPDProj1 = $sql -> select();
                        $delIPDProj1->from("MMS_TransferTrans")
                            ->columns(array("TransferTransId"))
                            ->where (array("TransferRegisterId"=>$TVRegisterId));

                        $delIPDproj = $sql -> delete();
                        $delIPDproj->from('MMS_IpdProjTrans')
                            ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                        $delipdprojStatement = $sql->getSqlStringForSqlObject($delIPDproj);
                        $dbAdapter->query($delipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delIPDTran = $sql -> delete();
                        $delIPDTran->from('MMS_IpdTrans')
                            ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                        $delipdStatement = $sql->getSqlStringForSqlObject($delIPDTran);
                        $dbAdapter->query($delipdStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delTVQual = $sql -> delete();
                        $delTVQual -> from ('MMS_TransferQualTrans')
                            ->where(array("TVRegisterId"=>$TVRegisterId));
                        $delQualStatement = $sql->getSqlStringForSqlObject($delTVQual);
                        $dbAdapter->query($delQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delTVWh = $sql -> delete();
                        $delTVWh->from('MMS_TransferWareHouseTrans')
                            ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                        $delwhStatement = $sql->getSqlStringForSqlObject($delTVWh);
                        $dbAdapter->query($delwhStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delTVWha = $sql -> delete();
                        $delTVWha->from('MMS_TransferWareHouseAnalTrans')
                            ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                        $delTVWhaStatement = $sql->getSqlStringForSqlObject($delTVWha);
                        $dbAdapter->query($delTVWhaStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $delTrans = $sql -> delete();
                        $delTrans -> from ('MMS_TransferTrans')
                            ->where(array("TransferRegisterId"=>$TVRegisterId));
                        $delTransStatement = $sql->getSqlStringForSqlObject($delTrans);
                        $dbAdapter->query($delTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $registerUpdate = $sql -> update()
                            ->table("MMS_TransferRegister")
                            ->set(array("TVNo" => $voucherno,"CCTVNo" => $CCTVNo,"CTVNo" => $CTVNo,"TVDate" => $TVDate,"FromCompanyId" => $FCompanyId,"FromCostCentreId" => $FCostCenterId,
                                "ToCompanyId"=>$TCompanyId,"ToCostCentreId"=>$TCostCenterId,"GPNo"=>$GPNo,"GPDate"=>$GPDate,"DespatchDate"=>$TVDate,
                                "DesNarration"=>$this->bsf->isNullCheck($postParams['Narration'], 'string'),"Narration"=>$this->bsf->isNullCheck($postParams['Narration'], 'string'),
                                "Adjustment"=>$Adjustment,"Indirect"=>$Indirect,
                                "GridType" => $gridtype,"Amount" => $amount
                            ))
                            ->where(array("TVRegisterId"=>$TVRegisterId ));
                        $registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                        $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $resTotal = $postParams['rowid'];

                        for ($i = 1; $i < $resTotal; $i++) {
                            if($this->bsf->isNullCheck($postParams['qty_' . $i], 'number') > 0) {
                                $trantransInsert = $sql->insert('MMS_TransferTrans');
                                $trantransInsert->values(array("TransferRegisterId" => $TVRegisterId, "FCostCentreId"=>$FCostCenterId,"TCostCentreId"=>$TCostCenterId,
                                    "UnitId" => $postParams['unitid_' . $i],"ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                    "TransferTypeId"=>$AccountId,
                                    "TransferQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'), "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'), "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['baseamount__' . $i], 'number') ));
                                $trantransStatement = $sql->getSqlStringForSqlObject($trantransInsert);
                                $dbAdapter->query($trantransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $TVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                $decTotal = $postParams['iow_' . $i . '_rowid'];

                                if($decTotal > 0) {
                                    for ($j = 1; $j <= $decTotal; $j++) {

                                        if($this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') > 0){

                                            //IPDTrans
                                            $ipdtransInsert = $sql->insert('MMS_IPDTrans');
                                            $ipdtransInsert->values(array("TransferTransId" => $TVTransId,
                                                "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j],
                                                "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                "ResourceId" => $postParams['resourceid_' . $i],
                                                "ItemId" => $postParams['itemid_' . $i],
                                                "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                "UnitId" => $postParams['unitid_' . $i], "Status" => 'T'));
                                            $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                            $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            //IPDProjTrans
                                            $ipdprojInsert = $sql->insert('MMS_IPDProjTrans');
                                            $ipdprojInsert->values(array("IPDTransId" => $IPDTransId, "CostCentreId" => $FCostCenterId, "TransferTransId" => $TVTransId, "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j], "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                "UnitId" => $postParams['unitid_' . $i], "Status" => 'T'));
                                            $ipdprojStatement = $sql->getSqlStringForSqlObject($ipdprojInsert);
                                            $dbAdapter->query($ipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $dbAdapter->getDriver()->getLastGeneratedValue();

                                            //DecisionTrans And RequestTrans Update
                                            $dectransUpdate = $sql->update();
                                            $dectransUpdate->table('VM_ReqDecQtyTrans');
                                            $dectransUpdate->set(array('TranAdjQty' => new Expression('TranAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                            $dectransUpdate->where(array('TransId' => $postParams['iow_' . $i . '_dectransid_' . $j]));
                                            $dectransStatement = $sql->getSqlStringForSqlObject($dectransUpdate);
                                            $dbAdapter->query($dectransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $reqtransUpdate = $sql->update();
                                            $reqtransUpdate->table('VM_RequestTrans');
                                            $reqtransUpdate->set(array('TransferQty' => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''), 'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                            $reqtransUpdate->where(array('RequestTransId' => $postParams['iow_' . $i . '_reqtransid_' . $j]));
                                            $reqtransStatement = $sql->getSqlStringForSqlObject($reqtransUpdate);
                                            $dbAdapter->query($reqtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            //
                                            //warehouse-insert -add
                                            $iwhTotal = $postParams['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                            for ($wh = 1; $wh <= $iwhTotal; $wh++) {
                                                if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                                    $whInsert = $sql->insert('MMS_TransferWareHouseAnalTrans');
                                                    $whInsert->values(array("TransferTransId" => $TVTransId,
                                                        "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j],
                                                        "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                        "WareHouseId" => $postParams['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                                        "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . '')
                                                    ));
                                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    }
                                }

                                //Qualifier Insert
                                $qual = $this->bsf->isNullCheck($postParams['QualRowId_' . $i],'number');

                                for ($q = 1; $q <= $qual; $q++) {
                                    if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                        $qInsert = $sql->insert('MMS_TransferQualTrans');
                                        $qInsert->values(array("TVRegisterId" => $TVRegisterId, "TransferTransId" => $TVTransId, "QualifierId" => $postParams['Qual_' . $i . '_Id_' . $q], "YesNo" => "1", "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                            "Sign" => $postParams['Qual_' . $i . '_Sign_' . $q], "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number'), "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number'),
                                            "Expression" => $postParams['Qual_' . $i . '_Exp_' . $q], "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number'),));
                                        $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                        $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                                //
                                // stock details updating

                                $stockId=0;

                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stock"))
                                    ->columns(array("StockId"))
                                    ->where(array("CostCentreId" => $FCostCenterId,
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i]
                                    ));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $stockId=$this->bsf->isNullCheck($stockselId['StockId'], 'number');

                                if ($stockId > 0) {

                                    $stockUpdate = $sql->update();
                                    $stockUpdate->table('mms_stock');
                                    $stockUpdate->set(array(
                                        "TransferQty" => new Expression('TransferQty-' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . ''),
                                        "TransferAmount" => new Expression('TransferAmount-'. $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'). '' ),
                                        "TransferGAmount" => new Expression('TransferGAmount-'. $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'). '' ),
                                        "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . '')
                                    ));
                                    $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                    $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                    $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {
                                    $stock = $sql->insert('mms_stock');
                                    $stock->values(array("CostCentreId" => $FCostCenterId,
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "UnitId" => $postParams['unitid_' . $i],
                                        "TransferQty" => $this->bsf->isNullCheck(-$postParams['qty_' . $i],'number'),
                                        "TransferAmount" => $this->bsf->isNullCheck(-$postParams['amount_' . $i],'number'),
                                        "TransferGAmount" => $this->bsf->isNullCheck(-$postParams['baseamount_' . $i],'number'),
                                        "ClosingStock" => $this->bsf->isNullCheck(-$postParams['qty_' . $i],'number')
                                    ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                    $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $stockId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                } // end of stock update

                                //warehouse
                                $fselect = $sql->select();
                                $fselect->from(array("G" => "MMS_TransferWareHouseAnalTrans"))
                                    ->columns(array(new Expression("SUM(G.Qty) as Qty, G.WareHouseId as WareHouseId,
                                        G.TransferTransId as TransferTransId")))
                                    ->where(array("TransferTransId" => $TVTransId));
                                $fselect->group(array("G.WareHouseId","G.TransferTransId"));
                                $statement = $sql->getSqlStringForSqlObject($fselect);
                                $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                if(count($ware) > 0) {
                                    foreach($ware as $wareData) {
                                        if ($wareData['Qty'] > 0) {
                                            $wInsert = $sql->insert('MMS_TransferWareHouseTrans');
                                            $wInsert->values(array("TransferTransId" => $TVTransId, "CostCentreId" => $FCostCenterId,
                                                "WareHouseId" => $wareData['WareHouseId'],
                                                "Qty" => $wareData['Qty']));
                                            $whStatement = $sql->getSqlStringForSqlObject($wInsert);
                                            $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            //stock trans update
                                            $stockSelect = $sql->select();
                                            $stockSelect->from(array("a" => "mms_stockTrans"))
                                                ->columns(array("StockId"))
                                                ->where(array("WareHouseId" => $wareData['WareHouseId'], "StockId" => $stockId));
                                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                            $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                            if (count($sId) > 0) {
                                                $sUpdate = $sql->update();
                                                $sUpdate->table('mms_stockTrans');
                                                $sUpdate->set(array(
                                                    "TransferQty" => new Expression('TransferQty-' . $wareData['Qty'] . ''),
                                                    "ClosingStock" => new Expression('ClosingStock-' . $wareData['Qty'] . '')
                                                ));
                                                $sUpdate->where(array("StockId" => $stockId, "WareHouseId" => $wareData['WareHouseId']));
                                                $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            } else {
                                                if ($wareData['Qty'] > 0) {
                                                    $stock1 = $sql->insert('mms_stockTrans');
                                                    $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                        "StockId" => $stockId,
                                                        "TransferQty" => $this->bsf->isNullCheck(-$wareData['Qty'], 'number',''),
                                                        "ClosingStock" => $this->bsf->isNullCheck(-$wareData['Qty'], 'number',''),
                                                    ));
                                                    $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                    $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    else {
                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(308, date('Y/m/d', strtotime($TVDate)), 0, 0, $dbAdapter, "I");
                            $voucherno = $voucher['voucherNo'];
                        } else {
                            $voucherno = $TVNo;
                        }

                        if ($CCTransfer['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(308, date('Y/m/d', strtotime($TVDate)), 0, $FCostCenterId, $dbAdapter, "I");
                            $CCTVNo = $voucher['voucherNo'];
                        } else {
                            $CCTVNo = $CCTVNo;
                        }

                        if ($CTransfer['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(308, date('Y/m/d', strtotime($TVDate)), $FCompanyId, 0, $dbAdapter, "I");
                            $CTVNo = $voucher['voucherNo'];
                        } else {
                            $CTVNo = $CTVNo;
                        }

                        $registerInsert = $sql->insert('MMS_TransferRegister');
                        $registerInsert->values(array("TVDate" => $TVDate, "FromCompanyId" => $FCompanyId,
                            "ToCompanyId" => $TCompanyId,"FromCostCentreId"=>$FCostCenterId,
                            "ToCostCentreId" => $TCostCenterId,"DespatchDate"=>$TVDate,
                            "GPNo" => $GPNo,"GPDate" => $GPDate,"Narration" => $this->bsf->isNullCheck($postParams['Narration'], 'string'),
                            "TVNo" => $voucherno, "CCTVNo" => $CCTVNo,"CTVNo" => $CTVNo,"Adjustment"=>$Adjustment,"Indirect"=>$Indirect,
                            "PurchaseTypeId" => $PurTypeId, "AccountId" => $AccountId,
                            "GridType" => $gridtype,"Amount" => $amount
                        ));
                        $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                        $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $TVRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $resTotal = $postParams['rowid'];
                        for ($i = 1; $i < $resTotal; $i++) {
                            if($this->bsf->isNullCheck($postParams['qty_' . $i], 'number') > 0) {
                                $trantransInsert = $sql->insert('MMS_TransferTrans');
                                $trantransInsert->values(array("TransferRegisterId" => $TVRegisterId, "FCostCentreId"=>$FCostCenterId,"TCostCentreId"=>$TCostCenterId,
                                    "UnitId" => $postParams['unitid_' . $i],"ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                    "TransferTypeId"=>$AccountId,
                                    "TransferQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'), "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'), "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number') ));
                                $trantransStatement = $sql->getSqlStringForSqlObject($trantransInsert);
                                $dbAdapter->query($trantransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $TVTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                $decTotal = $postParams['iow_' . $i . '_rowid'];

                                if($decTotal > 0) {
                                    for ($j = 1; $j <= $decTotal; $j++) {
                                        if($this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') > 0){

                                            //IPDTrans
                                            $ipdtransInsert = $sql->insert('MMS_IPDTrans');
                                            $ipdtransInsert->values(array("TransferTransId" => $TVTransId,
                                                "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j],
                                                "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                                "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                "UnitId" => $postParams['unitid_' . $i], "Status" => 'T'));
                                            $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                            $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            //IPDProjTrans
                                            $ipdprojInsert = $sql->insert('MMS_IPDProjTrans');
                                            $ipdprojInsert->values(array("IPDTransId" => $IPDTransId,
                                                "CostCentreId" => $FCostCenterId, "TransferTransId" => $TVTransId,
                                                "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j],
                                                "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                "ResourceId" => $postParams['resourceid_' . $i],
                                                "ItemId" => $postParams['itemid_' . $i],
                                                "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                "UnitId" => $postParams['unitid_' . $i], "Status" => 'T'));
                                            $ipdprojStatement = $sql->getSqlStringForSqlObject($ipdprojInsert);
                                            $dbAdapter->query($ipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                            //DecisionTrans And RequestTrans Update
                                            $dectransUpdate = $sql->update();
                                            $dectransUpdate->table('VM_ReqDecQtyTrans');
                                            $dectransUpdate->set(array('TranAdjQty' => new Expression('TranAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                            $dectransUpdate->where(array('TransId' => $postParams['iow_' . $i . '_dectransid_' . $j]));
                                            $dectransStatement = $sql->getSqlStringForSqlObject($dectransUpdate);
                                            $dbAdapter->query($dectransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $reqtransUpdate = $sql->update();
                                            $reqtransUpdate->table('VM_RequestTrans');
                                            $reqtransUpdate->set(array('TransferQty' => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''),
                                                'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                            $reqtransUpdate->where(array('RequestTransId' => $postParams['iow_' . $i . '_reqtransid_' . $j]));
                                            $reqtransStatement = $sql->getSqlStringForSqlObject($reqtransUpdate);
                                            $dbAdapter->query($reqtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            //
                                            //warehouse-insert -add
                                            $iwhTotal = $postParams['wh_' . $i . '_wbs_' . $j . '_wrowid'];
                                            for ($wh = 1; $wh <= $iwhTotal; $wh++) {
                                                if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number') > 0) {
                                                    $whInsert = $sql->insert('MMS_TransferWareHouseAnalTrans');
                                                    $whInsert->values(array("TransferTransId" => $TVTransId,
                                                        "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j],
                                                        "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                        "WareHouseId" => $postParams['wh_' . $i . '_wbs_' . $j . '_warehouseid_' . $wh],
                                                        "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_wbs_' . $j . '_qty_' . $wh], 'number' . '')
                                                    ));
                                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    }
                                }
                                //Qualifier Insert
                                $qual = $this->bsf->isNullCheck($postParams['QualRowId_' . $i],'number');
                                for ($q = 1; $q <= $qual; $q++) {
                                    if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                        $qInsert = $sql->insert('MMS_TransferQualTrans');
                                        $qInsert->values(array("TVRegisterId" => $TVRegisterId, "TransferTransId" => $TVTransId, "QualifierId" => $postParams['Qual_' . $i . '_Id_' . $q], "YesNo" => "1", "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                            "Sign" => $postParams['Qual_' . $i . '_Sign_' . $q], "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number'), "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number'),
                                            "Expression" => $postParams['Qual_' . $i . '_Exp_' . $q], "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number')));
                                        $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                        $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                                //
                                // stock details updating
                                $stockId=0;

                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stock"))
                                    ->columns(array("StockId"))
                                    ->where(array("CostCentreId" => $FCostCenterId,
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i]
                                    ));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $stockId=$this->bsf->isNullCheck($stockselId['StockId'], 'number');

                                if ($stockId > 0) {

                                    $stockUpdate = $sql->update();
                                    $stockUpdate->table('mms_stock');
                                    $stockUpdate->set(array(
                                        "TransferQty" => new Expression('TransferQty-' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . ''),
                                        "TransferAmount" => new Expression('TransferAmount-'. $this->bsf->isNullCheck($postParams['amount_' . $i], 'number').''),
                                        "TransferGAmount" => new Expression('TransferGAmount-'. $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number').''),
                                        "ClosingStock" => new Expression('ClosingStock-' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . '')
                                    ));
                                    $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                    $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                    $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {
                                    $stock = $sql->insert('mms_stock');
                                    $stock->values(array("CostCentreId" => $FCostCenterId,
                                        "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i],
                                        "UnitId" => $postParams['unitid_' . $i],
                                        "TransferQty" => $this->bsf->isNullCheck(-$postParams['qty_' . $i],'number'),
                                        "TransferAmount" => $this->bsf->isNullCheck(-$postParams['amount_' . $i],'number'),
                                        "TransferGAmount" => $this->bsf->isNullCheck(-$postParams['baseamount_' . $i],'number'),
                                        "ClosingStock" => $this->bsf->isNullCheck(-$postParams['qty_' . $i],'number')
                                    ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                    $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $stockId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                } // end of stock update

                                //warehouse
                                $fselect = $sql->select();
                                $fselect->from(array("G" => "MMS_TransferWareHouseAnalTrans"))
                                    ->columns(array(new Expression("SUM(G.Qty) as Qty, G.WareHouseId as WareHouseId,
                                        G.TransferTransId as TransferTransId")))
                                    ->where(array("TransferTransId" => $TVTransId));
                                $fselect->group(array("G.WareHouseId","G.TransferTransId"));
                                $statement = $sql->getSqlStringForSqlObject($fselect);
                                $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($ware) > 0) {
                                    foreach($ware as $wareData) {
                                        if ($wareData['Qty'] > 0) {
                                            $wInsert = $sql->insert('MMS_TransferWareHouseTrans');
                                            $wInsert->values(array("TransferTransId" => $TVTransId, "CostCentreId" => $FCostCenterId,
                                                "WareHouseId" => $wareData['WareHouseId'],
                                                "Qty" => $wareData['Qty']));
                                            $whStatement = $sql->getSqlStringForSqlObject($wInsert);
                                            $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                            //stock trans update
                                            $stockSelect = $sql->select();
                                            $stockSelect->from(array("a" => "mms_stockTrans"))
                                                ->columns(array("StockId"))
                                                ->where(array("WareHouseId" => $wareData['WareHouseId'], "StockId" => $stockId));
                                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                            $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                            if (count($sId) > 0) {

                                                $sUpdate = $sql->update();
                                                $sUpdate->table('mms_stockTrans');
                                                $sUpdate->set(array(
                                                    "TransferQty" => new Expression('TransferQty-' . $wareData['Qty'] . ''),
                                                    "ClosingStock" => new Expression('ClosingStock-' . $wareData['Qty'] . '')
                                                ));
                                                $sUpdate->where(array("StockId" => $stockId, "WareHouseId" => $wareData['WareHouseId']));
                                                $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            } else {
                                                if ($wareData['Qty'] > 0) {
                                                    $stock1 = $sql->insert('mms_stockTrans');
                                                    $stock1->values(array("WareHouseId" => $wareData['WareHouseId'],
                                                        "StockId" => $stockId,
                                                        "TransferQty" => $this->bsf->isNullCheck(-$wareData['Qty'], 'number',''),
                                                        "ClosingStock" => $this->bsf->isNullCheck(-$wareData['Qty'], 'number','')
                                                    ));
                                                    $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                    $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Transfer',$TVRegisterId,$FCostCenterId,$FCompanyId,'mms',$voucherno,$this->auth->getIdentity()->UserId,0,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'transfer', 'action' => 'display-register', 'rid' => $TVRegisterId));
                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }


            } else {


            }
        }

    }

    public function displayRegisterAction(){
        if(!$this->auth->hasIdentity()){
            if($this->getRequest()->isXmlHttpRequest()){
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            $resp = array();
            if($request->isPost()){
                $postParam = $request->getPost();
                if($postParam['mode'] == 'first'){
                    $selReg = $sql -> select();
                    $selReg->from(array("a"=>'MMS_TransferRegister'))
                        ->columns(array("TVRegisterId"=>new Expression("a.TVRegisterId"),"TransferNo"=>new Expression("a.TVNo"),
                            "TransferDate"=>new Expression("Convert(Varchar(10),a.TVDate,103)"),"FromCompany"=>new Expression("c.CompanyName"),
                            "FromCostCentre"=>new Expression("b.CostCentreName"),"ToCompany"=>new Expression("e.CompanyName"),
                            "ToCostCentre"=>new Expression("d.CostCentreName"),"Approve"=>new Expression("Case When a.Approve='Y' Then 'Yes' When a.Approve='P' then 'Partial' Else 'No' End")  ))
                        ->join(array('b'=>'WF_OperationalCostCentre'),"a.FromCostCentreId=b.CostCentreId",array(),$selReg::JOIN_INNER)
                        ->join(array('c'=>'WF_CompanyMaster'),"a.FromCompanyId=c.CompanyId",array(),$selReg::JOIN_INNER)
                        ->join(array('d'=>'WF_OperationalCostCentre'),"a.ToCostCentreId=d.CostCentreId",array(),$selReg::JOIN_INNER)
                        ->join(array('e'=>'WF_CompanyMaster'),"a.ToCompanyId=e.CompanyId",array(),$selReg::JOIN_INNER)
                        ->where(array('a.DeleteFlag'=>0,'a.ReceiptDate'=>null));
                    $selReg->order(new Expression("a.TVRegisterId DESC"));
                    $regStatement = $sql->getSqlStringForSqlObject($selReg);
                    $resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }

            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else if($request->isPost()){

        }
        return $this->_view;
    }

    public function detailedAction()	{
        if(!$this->auth->hasIdentity()){
            if($this->getRequest()->isXmlHttpRequest()){
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        /*Renderer and config objects*/
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $tranRegId = $this->params()->fromRoute('rid');
        /*Ajax Request*/
        if($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {
                $postParam = $request->getPost();
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                switch($Type) {
                    case 'getqualdetails':

                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $tranId=$this->bsf->isNullCheck($this->params()->fromPost('tRId'), 'number');

                        $select = $sql->select();
                        $select->from(array("c" => "MMS_TransferQualTrans"))
                            ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),'QualifierId'=>new Expression('c.QualifierId'),
                                'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
                                'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
                                'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
                                'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),
                                'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,2)) '),
                                'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,2)) '),
                                'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,2)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
                                'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,2)) '),'QualifierName'=>new Expression('b.QualifierName'),
                                'QualifierTypeId'=>new Expression('b.QualifierTypeId'),'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
                            ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);

                        $select->where(array('a.QualType' => 'M', 'c.TVRegisterId' => $tranId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
                        $selMain = $sql -> select()->from(array('result'=>$select));
                        $selMain->order('SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($selMain);
                        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sHtml = Qualifier::getQualifier($qualList);

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($sHtml));
                        return $response;
                        break;


                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }
            }

            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else if ($request->isPost()) {

        }		
		$selRData = $sql -> select();
		$selRData -> from(array('a'=>'MMS_TransferRegister'))
			->columns(array("TVDate"=> new Expression('Convert(Varchar(10),a.TVDate,103)'),
				"GPDate"=> new Expression('Convert(Varchar(10),a.GPDate,103)'),
				"TVNo"=>"TVNo","CCTVNo"=>"CCTVNo","CTVNo"=>"CTVNo","GridType" => "GridType",
				"FromCompanyId"=>"FromCompanyId","FromCostCentreId"=>"FromCostCentreId","ToCompanyId"=>"ToCompanyId",
				"ToCostCentreId"=>"ToCostCentreId","GPNo"=>"GPNo","Narration"=>"Narration","Approve"=>"Approve",
				"Adjustment"=>new expression("Case when Adjustment=1 then 'on' else 'off' end"),
				"Indirect"=>new expression("Case when Indirect=1 Then 'on' else 'off' end " ),
				"PurchaseTypeId"=>"PurchaseTypeId","AccountId"=>"AccountId" ))
				-> join(array("b" => "MMS_PurchaseType"),"a.PurchaseTypeId=b.PurchaseTypeId",array("PurchaseTypeName"),$selRData::JOIN_INNER)
			->where("a.TVRegisterId=".$tranRegId);
		$statement = $sql->getSqlStringForSqlObject($selRData);
		$this->_view->tranReg = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


		$FCompanyId = $this->_view->tranReg['FromCompanyId'];
		$TCompanyId = $this->_view->tranReg['ToCompanyId'];
		$FProjId = $this->_view->tranReg['FromCostCentreId'];
		$TProjId = $this->_view->tranReg['ToCostCentreId'];
		$vNo=$this->_view->tranReg['TVNo'];
		$purchasetype=$this->_view->tranReg['PurchaseTypeId'];
		$PurchaseTypeName=$this->_view->tranReg['PurchaseTypeName'];
		$tvdate=$this->_view->tranReg['TVDate'];
		$gpno=$this->_view->tranReg['GPNo'];
		$gpdate=$this->_view->tranReg['GPDate'];
		$cctvno=$this->_view->tranReg['CCTVNo'];
		$ctvno=$this->_view->tranReg['CTVNo'];
		$indirect=$this->_view->tranReg['Indirect'];
		$adjustment=$this->_view->tranReg['Adjustment'];
		$accounttype=$this->_view->tranReg['AccountId'];
		$narration=$this->_view->tranReg['Narration'];
		$Approve=$this->_view->tranReg['Approve'];
		$gridtype=$this->_view->tranReg['GridType'];


		$this->_view->vNo = $vNo;
		$this->_view->purchasetype = $purchasetype;
		$this->_view->PurchaseTypeName = $PurchaseTypeName;
		$this->_view->TVDate = $tvdate;
		$this->_view->GPNo = $gpno;
		$this->_view->gpdate = $gpdate;
		$this->_view->CCTVNo = $cctvno;
		$this->_view->CTVNo = $ctvno;
		$this->_view->indirect = $indirect;
		$this->_view->adjustment = $adjustment;
		$this->_view->accounttype = $accounttype;
		$this->_view->narration = $narration;
		$this->_view->tranRegId= $tranRegId;
		$this->_view->Approve= $Approve;
		$this->_view->gridtype = $gridtype;
		// from company
        $select = $sql->select();
        $select->from(array('a' => 'WF_CompanyMaster'))
            ->columns(array('CompanyId','CompanyName'))
            ->where("CompanyId=$FCompanyId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $fcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->FCompanyName= $fcompany['CompanyName'];

        //to company
        $select = $sql->select();
        $select->from(array('a' => 'WF_CompanyMaster'))
            ->columns(array('CompanyId','CompanyName'))
            ->where("CompanyId=$TCompanyId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $tcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->TCompanyName= $tcompany['CompanyName'];

        //from costcentre
        $select = $sql->select();
        $select->from(array('a' => 'WF_OperationalCostCentre'))
            ->columns(array('CostCentreId','CostCentreName'))
            ->where("CostCentreId=$FProjId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $fcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->FCostCentreName= $fcostcentre['CostCentreName'];

        //to costcentre
        $select = $sql->select();
        $select->from(array('a' => 'WF_OperationalCostCentre'))
            ->columns(array('CostCentreId','CostCentreName'))
            ->where("CostCentreId=$TProjId");
        $statement = $sql->getSqlStringForSqlObject($select);
        $tcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->TCostCentreName= $tcostcentre['CostCentreName'];


		//PurchaseType
		// $select = $sql->select();
		// $select->from(array("a"=>"MMS_PurchaseType"))
			// ->columns(array("PurchaseTypeId","PurchaseTypeName"))
			// ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array(),$select::JOIN_INNER)
			// ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
			// ->where('c.CostCentreId='.$FProjId.' and b.Sel=1');
		// $typeStatement = $sql->getSqlStringForSqlObject($select);
		// $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		// $this->_view->purchaseType = $purchaseType;

		$selAcc=$sql->select();
		$selAcc->from(array("a"=>"FA_AccountMaster"))
			->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
			->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
			->where(array("b.PurchaseTypeId"=>$purchasetype));
		$accStatement = $sql->getSqlStringForSqlObject($selAcc);
		$accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		$this->_view->accType = $accType['value'];

		//IsWareHouse
		$select = $sql -> select();
		$select->from(array("a" => "MMS_CCWareHouse"))
			->columns(array("WareHouseId"))
			->where('a.CostCentreId='.$FProjId);
		$whStatement = $sql->getSqlStringForSqlObject($select);
		$isWareHouse = $dbAdapter->query($whStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->isWh = $isWareHouse;

		$select = $sql -> select();
		$select -> from (array("a" => "MMS_TransferTrans"))
			->columns(array('ResourceId','ItemId','Desc'=>new Expression("Case When a.ItemId>0 Then d.ItemCode+ ' ' +d.BrandName Else c.Code +' '+c.ResourceName End"),
				'Qty'=>new Expression("CAST(a.TransferQty As Decimal(18,3))"),'Rate'=>new Expression("CAST(a.Rate As Decimal(18,2))"),
				'QRate'=>new Expression("CAST(a.QRate As Decimal(18,2))"),'BaseAmount'=>new Expression("CAST(a.Amount As Decimal(18,2))"),
				'Amount'=>new Expression("CAST(a.QAmount As Decimal(18,2))")))

			-> join(array("b" => "MMS_TransferRegister"),"a.TransferRegisterId=b.TVRegisterId",array(),$select::JOIN_INNER)
			-> join(array("c" => "Proj_Resource"),"a.ResourceId=c.ResourceId",array(),$select::JOIN_INNER)
			-> join(array("d" => "MMS_Brand"),"a.ItemId=d.BrandId and a.ResourceId=d.ResourceId",array(),$select::JOIN_LEFT)
			-> join(array("e" => "Proj_Uom"),"a.UnitId=e.UnitId",array("UnitId","UnitName"),$select::JOIN_LEFT)
			->where ("a.TransferRegisterId=".$tranRegId."");

		$statement = $sql->getSqlStringForSqlObject($select);
		$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$selDec = $sql -> select();
		$selDec->from(array("a" => "MMS_IPDTrans"))
			->columns(array(new Expression('c.DecisionId,c.TransId As DecTransId,c.ReqTransId,d.RDecisionNo As DecisionNo,b.ResourceId,b.ItemId,CAST((c.TransferQty-c.TranAdjQty) As Decimal(18,3)) As BalQty,CAST(a.Qty As Decimal(18,3)) As Qty,CAST(a.Qty As Decimal(18,3)) As HiddenQty')))
			->join(array("b" => "MMS_TransferTrans"),"a.TransferTransId=b.TransferTransId",array(),$selDec::JOIN_INNER)
			->join(array("c" => "VM_ReqDecQtyTrans"),"a.DecTransId=c.TransId and a.DecisionId=c.DecisionId",array(),$selDec::JOIN_INNER)
			->join(array("d" => "VM_RequestDecision"),"c.DecisionId=d.DecisionId",array(),$selDec::JOIN_INNER)
			->join(array("e" => "VM_RequestTrans"),"c.ReqTransId=e.RequestTransId",array(),$selDec::JOIN_INNER)
			->join(array("f" => "VM_RequestRegister"),"e.RequestId=f.RequestId",array(),$selDec::JOIN_INNER)
			->where('b.TransferRegisterId='.$tranRegId);
		$statement = $sql->getSqlStringForSqlObject($selDec);
		$this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


		$select = $sql->select();
		$select->from(array("a" => "Proj_QualifierTrans"))
			->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
			->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','NetPer',
				'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
				'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
				'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
		$select->where(array('a.QualType' => 'M'));
		$select->order('a.SortId ASC');
		$statement = $sql->getSqlStringForSqlObject($select);
		$qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$sHtml=Qualifier::getQualifier($qualList);
		$this->_view->qualHtml = $sHtml;

		$arrqual = array();
		$select = $sql->select();
		$select->from(array("a" => "MMS_TransferQualTrans"))
			->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
				'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
				'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
				'EDCessAmt', 'HEDCessAmt', 'NetAmt'));
		$select->where(array('a.TVRegisterId'=>$tranRegId));
		$select->order('a.SortId ASC');
		$statement = $sql->getSqlStringForSqlObject($select);
		$qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
		$this->_view->arr_qual_list = $qualList;


		$selWhm = $sql -> select();
		$selWhm->from(array("a" => "MMS_TransferWareHouseAnalTrans"))
			->columns(array("StockId" => new Expression("e.StockId"),"WareHouseId" => new Expression("d.TransId"),
				"ResourceId" => new Expression("b.ResourceId"),"ItemId" => new expression("b.ItemId"),
				"DecTransId" => new Expression("a.DecTransId"),
				"WareHouseName" => new Expression("g.WareHouseName"),"Description"=>new expression("d.Description"),
				"ClosingStock" => new Expression("CAST(f.ClosingStock As Decimal(18,3))"),
				"Qty" => new Expression("CAST(a.Qty As Decimal(18,3))"),"HiddenQty"=>new expression("CAST(a.Qty As Decimal(18,3))") ))
			->join(array("b" => "MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$selWhm::JOIN_INNER)
			->join(array("c" => "MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$selWhm::JOIN_INNER)
			->join(array("d" => "MMS_WareHouseDetails"),'a.WareHouseId=d.transId',array(),$selWhm::JOIN_INNER)
			->join(array("e" => "MMS_Stock"),'b.ResourceId=e.ResourceId and b.ItemId=e.ItemId and c.FromCostCentreId=e.CostCentreId',array(),$selWhm::JOIN_INNER)
			->join(array("f" => "MMS_StockTrans"),'e.StockId=f.StockId and a.WareHouseId=f.WareHouseId',array(),$selWhm::JOIN_INNER)
			->join(array("g" => "MMS_WareHouse"),'d.WareHouseId=g.WareHouseId',array(),$selWhm::JOIN_INNER)
			->where ("c.TVRegisterId=".$tranRegId."");
		$statement = $sql->getSqlStringForSqlObject($selWhm);
		$this->_view->arr_sel_tvwarehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

		$selWh = $sql -> select();
		$selWh->from(array("a" => "MMS_WareHouse"))
			->columns(array("StockId"=>new Expression("e.StockId"),"WareHouseId" => new Expression("c.transid"),
				"ResourceId"=>new Expression("e.ResourceId"),"ItemId"=>new Expression("e.ItemId"),
				"WareHouseName" => new Expression("a.WareHouseName"),"Description"=>new Expression("c.Description"),
				"ClosingStock"=>new Expression("CAST(d.ClosingStock As Decimal(18,2))"),
				"Qty"=>new Expression("CAST(0 As Decimal(18,2))"),"HiddenQty"=>new Expression("CAST(0 As Decimal(18,2))")   ))
			->join(array("b" => "MMS_CCWareHouse"),'a.WareHouseId=b.WareHouseId',array(),$selWh::JOIN_INNER)
			->join(array("c" => "MMS_WareHouseDetails"),"b.WareHouseId=c.WareHouseId",array(),$selWh::JOIN_INNER)
			->join(array("d" => "MMS_StockTrans"),"c.TransId=d.WareHouseId",array(),$selWh::JOIN_INNER)
			->join(array("e" => "MMS_Stock"),"d.StockId=e.StockId and b.CostCentreId=e.CostCentreId",array(),$selWh::JOIN_INNER)
			->where('b.CostCentreId='. $FProjId .' and c.LastLevel=1 and d.ClosingStock>0 and
				(e.ResourceId IN (Select ResourceId From MMS_TransferTrans  Where TransferRegisterId='.$tranRegId.') and
				e.ItemId IN (Select ItemId From MMS_TransferTrans Where TransferRegisterId='.$tranRegId.' ))');
		$statement = $sql->getSqlStringForSqlObject($selWh);
		$this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $this->_view->regId = $this->params()->fromRoute('rid');
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;


    }
    public function tvreceiptWizardAction(){
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
                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');
                $response = $this->getResponse();

                switch($Type) {
//                    case 'fromCC':
//                        $FCompanyId = $this->bsf->isNullCheck($this->params()->fromPost('fcompanyid'), 'number');
//
//                        $selectFCC = $sql->select();
//                        $selectFCC->from(array("a" => "WF_OperationalCostCentre"))
//                            ->columns(array("data"=>new Expression("a.CostCentreId"),"value"=>new Expression("a.CostCentreName")))
//                            ->where(array('a.CompanyId' => $FCompanyId));
//                        $statement = $sql->getSqlStringForSqlObject($selectFCC);
//                        $arr_fcc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                        $response->setStatusCode('200');
//                        $response->setContent(json_encode($arr_fcc));
//                        return $response;
//                        break;
                    case 'getrequest':
//                        $fCCId = $this->bsf->isNullCheck($this->params()->fromPost('FromCostCentreId'),'number');
//                        $fCId = $this->bsf->isNullCheck($this->params()->fromPost('FromCompanyId'),'number');
//                        $tCId = $this->bsf->isNullCheck($this->params()->fromPost('ToCompanyId'),'number');
                        $tCCId = $this->bsf->isNullCheck($this->params()->fromPost('ToCostCentreId'),'number');
//
//                        $select = $sql -> select();
//                        $select -> from(array("a" => "MMS_TransferRegister"))
//                            ->columns(array(new Expression("Distinct(a.TVRegisterId) as TVRegisterId,Convert(Varchar(10),a.TVDate,103) As TVDate,
//                            a.TVNo As TVNo")))
//                            ->join(array('b' => 'MMS_TransferTrans'), 'a.TVRegisterId=b.TransferRegisterId', array(), $select::JOIN_INNER)
//                            ->join(array('c' => 'WF_OperationalCostCentre'),'a.fromCostCentreId=c.CostCentreId',array(),$select::JOIN_INNER)
//                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.toCostCentreId=d.CostCentreId',array(),$select::JOIN_INNER)
//                            ->where("a.FromCostCentreId =$fCCId and a.ToCostCentreId= $tCCId and
//                                     a.FromCompanyId = $fCId and a.ToCompanyId = $tCId
//                                     and b.TransferQty > 0 and a.Approve='Y'");
//                        $statement = $sql->getSqlStringForSqlObject($select);
//                        $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql -> select();
                        $select -> from(array("a" => "MMS_TransferRegister"))
                            ->columns(array(new Expression("Distinct(a.TVRegisterId) as TVRegisterId,Convert(Varchar(10),a.TVDate,103) As TVDate,
                            a.TVNo As TVNo")))
                            ->join(array('b' => 'MMS_TransferTrans'), 'a.TVRegisterId=b.TransferRegisterId', array(), $select::JOIN_INNER)
                            //  ->join(array('c' => 'WF_OperationalCostCentre'),'a.fromCostCentreId=c.CostCentreId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.toCostCentreId=d.CostCentreId',array(),$select::JOIN_INNER)
                            ->where("a.ToCostCentreId= $tCCId and b.TransferQty > 0 and a.Approve='Y'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "MMS_TransferRegister"))
                            ->columns(array(new Expression("Distinct(b.TransferRegisterId) As TransferRegisterId,b.TransferTransId,
                            0 As Include,Cast(b.TransferQty as Decimal(18,5)) as Quantity,
                            Convert(Varchar(10),a.TVDate,103) As TVDate,TVNo,
                            Case When b.ItemId>0 Then d.BrandName Else c.ResourceName End As [Desc]")))
                            ->join(array("b"=>'MMS_TransferTrans'),'a.TVRegisterId=b.TransferRegisterId',array(),$select::JOIN_INNER)
                            ->join(array("c"=>'proj_resource'),'b.resourceId=c.resourceId',array(),$select::JOIN_INNER)
                            ->join(array("d"=>'MMS_Brand'), 'd.BrandId=b.Itemid  and d.ResourceId = b.resourceId',  array(), $select::JOIN_LEFT)
//                            ->where("b.FCostCentreId =$fCCId
//                            and b.TCostCentreId= $tCCId
//                                   and b.TransferQty > 0 and a.Approve='Y'");
                            ->where(" b.TCostCentreId= $tCCId
                                   and b.TransferQty > 0 and a.Approve='Y'");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('requests' => $requests, 'resources' => $requestResources)));
                        return $response;
                }
                $result =  "";
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent($result);
                return $response;
            }
        } else {

//            $sel1 = $sql -> select();
//            $sel1 -> columns(array(new Expression("0 As CompanyId,'None' As CompanyName") ));
//
//            $select = $sql->select();
//            $select->from( array( 'a' => 'WF_CompanyMaster' ))
//                ->columns( array( 'CompanyId', 'CompanyName' ));
//            $sel1->combine($select, 'Union ALL');
//
//            $statement = $sql->getSqlStringForSqlObject( $sel1 );
//            $this->_view->arr_company = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();
//			$request = $this->getRequest();

            $selectFCC = $sql->select();
            $selectFCC->from(array("a" => "WF_OperationalCostCentre"))
                ->columns(array("CostCentreId"=>new Expression("a.CostCentreId"),"CostCentreName"=>new Expression("a.CostCentreName")));
            $statement = $sql->getSqlStringForSqlObject($selectFCC);
            $this->_view->arr_fcc = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

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

    public function tvreceiptEntryAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "transfer","action" => "tvreceipt-wizard"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $tid = $this->bsf->isNullCheck($this->params()->fromRoute('tid'), 'number');
        $request = $this->getRequest();
        $response = $this->getResponse();

//        if (!$this->getRequest()->isXmlHttpRequest() && $tid == 0 && !$request->isPost()) {
//            $this->redirect()->toRoute('mms/default', array('controller' => 'transfer', 'action' => 'transfer-register'));
//
//        }
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                $resp = array();
                if($postParam["mode"] == 'selectAccount'){
                    $PurTypeId = $this->bsf->isNullCheck($this->params()->fromPost('ptypeId'), 'number');
                    $selectSub = $sql->select();
                    $selectSub->from(array("a" => "MMS_PurchaseType"))
                        ->columns(array(new Expression('c.AccountId,c.AccountName')))
                        ->join(array('b' => 'FA_AccountType'), "a.AccountTypeId=b.TypeId", array(), $selectSub::JOIN_INNER)
                        ->join(array('c' => 'FA_AccountMaster'), "b.TypeId=c.TypeId", array("data" => 'AccountId', "value" => 'AccountName'), $selectSub::JOIN_INNER)
                        ->where(array('a.PurchaseTypeId IN (7,8)', 'a.PurchaseTypeId' => $PurTypeId));

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PurchaseType"))
                        ->columns(array(new Expression('b.AccountId,b.AccountName')))
                        ->join(array('b' => 'FA_AccountMaster'), "a.AccountId=b.AccountId", array("data" => 'AccountId', "value" => 'AccountName'), $select::JOIN_INNER)
                        ->where(array('a.PurchaseTypeId' => $PurTypeId));
                    $select->combine($selectSub, 'Union ALL');
                    $accountStatement = $sql->getSqlStringForSqlObject($select);
                    $resp['resultAcc'] = $dbAdapter->query($accountStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
//                echo"<pre>";
//                 print_r($postData);
//                  echo"</pre>";
//                 die;
//                   return;

                if (!is_null($postData['frm_index'])) {

//                    $fCompanyId = $this->bsf->isNullCheck($postData['from_company'], 'number');
//                    $tCompanyId = $this->bsf->isNullCheck($postData['to_company'], 'number');
//                    $fProjId = $this->bsf->isNullCheck($postData['from_project'], 'number');
                    $tProjId = $this->bsf->isNullCheck($postData['to_project'], 'number');
                    $gridtype = $this->bsf->isNullCheck($postData['gridtype'], 'number');

                    $voNo = CommonHelper::getVoucherNo(303, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->voNo = $voNo;
                    $vNo=$voNo['voucherNo'];
                    $this->_view->vNo = $vNo;

                    $this->_view->adjustment = 0;
                    if(count($postData['transferTransIds']) > 0)
                    {
                        $transferTransIds = implode(',', $postData['transferTransIds']);
                    }
                    else
                    {
                        $transferTransIds =0;
                    }
                    $select = $sql->select();
                    $select -> from(array('a' => 'MMS_TransferTrans'))
                        ->columns(array("TransferRegisterId"))
                        ->where(array("TransferTransId IN( $transferTransIds)" ));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->selId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $trId = $this->_view->selId['TransferRegisterId'];


                    $select = $sql->select();
                    $select -> from(array('a' => 'MMS_TransferRegister'))
                        ->columns(array(new Expression("TVRegisterId,TVNo,FromCostCentreId,CONVERT(varchar(10),a.TVDate,105) As TVDate,
                        GPNo,CONVERT(varchar(10),a.GPDate,105) As GPDate,CCTVNo,PurchaseTypeId,AccountId,
                        CTVNo,Adjustment,CONVERT(varchar(10),a.DespatchDate,105) As DespatchDate")))
                        ->where("TVRegisterId = $trId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tvRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $tvNo = $this->_view->tvRegister['TVNo'];
                    $vNo=$tvNo;
                    $this->_view->vNo = $vNo;
                    $tvdate = $this->_view->tvRegister['TVDate'];
                    $gpno = $this->_view->tvRegister['GPNo'];
                    $gpDate = $this->_view->tvRegister['GPDate'];
                    $cctvno = $this->_view->tvRegister['CCTVNo'];
                    $purchasetypeid = $this->_view->tvRegister['PurchaseTypeId'];
                    $accountid = $this->_view->tvRegister['AccountId'];
                    $ctvno = $this->_view->tvRegister['CTVNo'];
                    $adjust = $this->_view->tvRegister['Adjustment'];
                    $despatchDate = $this->_view->tvRegister['DespatchDate'];
                    $tvregId = $this->_view->tvRegister['TVRegisterId'];
                    $fProjId = $this->_view->tvRegister['FromCostCentreId'];
                    $this->_view->TVNo = $tvNo;
                    $this->_view->TVDate = $tvdate;
                    $this->_view->GPNo = $gpno;
                    $this->_view->GPDate = $gpDate;
                    $this->_view->CCTVNo = $cctvno;
                    $this->_view->purchasetype = $purchasetypeid;
                    $this->_view->accounttype = $accountid;
                    $this->_view->CTVNo = $ctvno;
                    $this->_view->adjustment = $adjust;
                    $this->_view->DespatchDate = $despatchDate;
                    $this->_view->TvregId = $tvregId;
                    $this->_view->transferTransIds = $transferTransIds;
                    $this->_view->gridtype=$gridtype;


                    //TO COSTCENTRE
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId','CostCentreName','CompanyId'))
                        ->where("CostCentreId=$tProjId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $tcostcentreId = $this->_view->tcostcentre['CostCentreId'];
                    $tcostcentre = $this->_view->tcostcentre['CostCentreName'];
                    $tcompanyId = $this->_view->tcostcentre['CompanyId'];
                    $this->_view->tcostcentreId = $tcostcentreId;
                    $this->_view->tcostcentre = $tcostcentre;


                    //TO COMPANY
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_CompanyMaster'))
                        ->columns(array('CompanyId','CompanyName'))
                        ->where("CompanyId=$tcompanyId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $tcompanyId = $this->_view->tcompany['CompanyId'];
                    $tcompanyName = $this->_view->tcompany['CompanyName'];
                    $this->_view->tcompanyId = $tcompanyId;
                    $this->_view->tcompanyName = $tcompanyName;


                    //FROM COSTCENTRE
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId','CostCentreName','CompanyId'))
                        ->where("CostCentreId=$fProjId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->fcostcentre = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $fcostcentreId = $this->_view->fcostcentre['CostCentreId'];
                    $fcostcentre = $this->_view->fcostcentre['CostCentreName'];
                    $fCompanyId = $this->_view->fcostcentre['CompanyId'];
                    $this->_view->fcostcentreId = $fcostcentreId;
                    $this->_view->fcostcentre = $fcostcentre;


                    // FROM COMPANY
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_CompanyMaster'))
                        ->columns(array('CompanyId','CompanyName'))
                        ->where("CompanyId=$fCompanyId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->fcompany = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $fcompanyId = $this->_view->fcompany['CompanyId'];
                    $fcompanyName= $this->_view->fcompany['CompanyName'];
                    $this->_view->fcompanyId = $fcompanyId;
                    $this->_view->fcompanyName = $fcompanyName;


                    //PURCHASETYPE
                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array("Default"),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->order("b.Default Desc")
                        ->where('c.CostCentreId='.$fProjId.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    //ACCOUNT TYPE
                    $selAcc=$sql->select();
                    $selAcc->from(array("a"=>"FA_AccountMaster"))
                        ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
                        ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
                        ->where(array("b.PurchaseTypeId"=>$purchasetypeid));
                    $accStatement = $sql->getSqlStringForSqlObject($selAcc);
                    $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->accType = $accType;

                    //TRANSFER REQUEST RESOURCE
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression("Distinct(a.ResourceId),e.TVRegisterid,a.TransferTransId,a.ItemId,
                        case when a.ItemId>0 then c.ItemCode+ '' +c.BrandName Else b.Code+' - '+b.ResourceName End as [Desc],
                        d.UnitName As UnitName,CAST(a.TransferQty As Decimal(18,5)) As TransferQty,
                        CAST(ISNULL(SUM(f.Qty),0) As Decimal(18,3)) As RecdQty,
                        CAST(0 As Decimal(18,5)) As AdjustmentQty,
                        CAST(a.QRate As Decimal(18,5)) As Rate,
                        CAST(a.Amount As Decimal(18,5)) As Amount")))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'MMS_TransferRegister'), 'e.TVRegisterId=a.TransferRegisterId ', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'MMS_IPDTrans'), 'a.TransferTransId=f.TransferTransId', array(), $select::JOIN_INNER)
                        ->where("e.TVRegisterId= $trId")
                    ->group(new Expression('a.TransferQty,a.Amount,e.TVRegisterid,a.TransferTransId,a.ResourceId,a.ItemId,c.ItemCode,c.BrandName,b.Code,b.ResourceName,d.UnitId,d.UnitName,a.Rate,a.QRate'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //TRANSFER RESOURCE
                    $select = $sql->select();$select->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression("d.TransId DecTransId,a.ResourceId,a.ItemId, a.UnitId,b.IPDTransId,
                                c.IPDProjTransId,f.RequestTransId,e.RDecisionNo DecisionNo,
                                g.RequestNo,CAST(b.Qty As Decimal(18,6)) As TransferQty,
                                CAST(b.Qty As Decimal(18,6)) As ReceiptQty")))
                        ->join(array('b' => 'MMS_IPDTrans'), 'a.TransferTransId=b.TransferTransId ', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_IPDProjTrans'), 'b.IPDTransId=c.IPDTransId ', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'VM_ReqDecQtyTrans'), 'c.DecTransId=d.TransId And C.DecisionId=D.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'VM_RequestDecision'), 'd.DecisionId=e.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'VM_RequestTrans'), 'd.ReqTransId=f.RequestTransId', array(), $select::JOIN_INNER)
                        ->join(array('g' => 'VM_RequestRegister'), 'f.RequestId=g.RequestId', array(), $select::JOIN_INNER)
                        ->where("a.TransferRegisterId=$trId and b.Qty > 0");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //TRANSFER REQUEST
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression("d.TransId DecTransId,e.RCATransId DecATransId,
                        a.ResourceId,a.ItemId,a.UnitId,f.RequestAHTransId, f.AnalysisId,h.RequestTransId,
                        c.IPDProjTransId,g.ParentText PLevel,g.WbsName,
                        CAST((e.TransferQty-e.TranAdjQty) As Decimal(18,6)) As BalQty,
                        CAST((e.TransferQty-e.TranAdjQty) As Decimal(18,6)) As ReceiptQty")))
                        ->join(array('b' => 'MMS_IPDTrans'), 'a.TransferTransId=b.TransferTransId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_IPDProjTrans'), 'b.IPDTransId=c.IPDTransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'VM_ReqDecQtyTrans'), 'c.DecTransId=d.TransId And c.DecisionId=d.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'VM_ReqDecQtyAnalTrans'), 'd.TransId=e.TransId And d.DecisionId=e.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'VM_RequestAnalTrans'), 'e.ReqAHTransId=f.RequestAHTransId', array(), $select::JOIN_INNER)
                        ->join(array('g' => 'Proj_WbsMaster'), 'f.AnalysisId=g.WbsId', array(), $select::JOIN_INNER)
                        ->join(array('h' => 'VM_RequestTrans'), 'f.ReqTransId=h.RequestTransId', array(), $select::JOIN_INNER)
                        ->join(array('i' => 'VM_RequestRegister'), 'h.RequestId=i.RequestId', array(), $select::JOIN_INNER)
                        ->where("a.TransferRegisterId=$trId and e.TransferQty-e.TranAdjQty > 0");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WAREHOUSE DETAILS
                    $select = $sql->select();
                    $select-> from(array("a" => "MMS_WareHouse"))
                        ->columns(array(new Expression("a.WareHouseName,c.TransId As WareHouseId,b.CostCentreId,c.Description,
                        CAST(0 As Decimal(18,6)) As Qty")))
                        ->join(array('b' => 'MMS_CCWareHouse'),'a.WareHouseId=b.WareHouseId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_WareHouseDetails'),'b.WareHouseId=c.WareHouseId', array(), $select::JOIN_INNER)
                        ->where(array("b.CostCentreId = $tProjId and c.LastLevel=1"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

            } else {

                $postData = $request->getPost();

                //edit mode of tvreceipt-entry
                if (isset($tid) && $tid != '') {

                    $select = $sql->select();
                    $select -> from(array('a' => 'MMS_TransferRegister'))
                        ->columns(array(new Expression("a.TVRegisterId,TVNo,CONVERT(varchar(10),a.TVDate,105) As TVDate,
                        GPNo,CONVERT(varchar(10),a.GPDate,105) As GPDate,CCTVNo,PurchaseTypeId,AccountId,
                        CTVNo,Adjustment,CONVERT(varchar(10),a.DespatchDate,105) As DespatchDate,
                        b.CompanyName As Fcompany,b.CompanyId As FcompanyId,
                        c.CompanyName As Tcompany,c.CompanyId As TcompanyId,f.Approve As Approve,
                        d.CostCentreName As Fcostcentre,d.CostCentreId As FcostcentreId,
                        e.CostCentreName As Tcostcentre,e.CostCentreId As TcostcentreId,
                        a.RecNarration,f.GridType")))
                        ->join(array('b' => 'WF_CompanyMaster'), 'a.FromCompanyId=b.CompanyId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'WF_CompanyMaster'), 'a.ToCompanyId=c.CompanyId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'), 'a.FromCostCentreId=d.CostCentreId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'WF_OperationalCostCentre'), 'a.ToCostCentreId=e.CostCentreId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'MMS_TransferReceipt'), 'a.TVRegisterId=f.TVRegisterId', array(), $select::JOIN_INNER)
                        ->where(array("a.TVRegisterId"=> $tid));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->tvRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $tvNo = $this->_view->tvRegister['TVNo'];
                    $tvdate = $this->_view->tvRegister['TVDate'];
                    $gpno = $this->_view->tvRegister['GPNo'];
                    $gpDate = $this->_view->tvRegister['GPDate'];
                    $cctvno = $this->_view->tvRegister['CCTVNo'];
                    $purchasetypeid = $this->_view->tvRegister['PurchaseTypeId'];
                    $accountid = $this->_view->tvRegister['AccountId'];
                    $ctvno = $this->_view->tvRegister['CTVNo'];
                    $adjust = $this->_view->tvRegister['Adjustment'];
                    $despatchDate = $this->_view->tvRegister['DespatchDate'];
                    $Fcompany = $this->_view->tvRegister['Fcompany'];
                    $FcompanyId = $this->_view->tvRegister['FcompanyId'];
                    $Tcompany = $this->_view->tvRegister['Tcompany'];
                    $TcompanyId = $this->_view->tvRegister['TcompanyId'];
                    $Fcostcentre = $this->_view->tvRegister['Fcostcentre'];
                    $FcostcentreId = $this->_view->tvRegister['FcostcentreId'];
                    $Tcostcentre = $this->_view->tvRegister['Tcostcentre'];
                    $TcostcentreId = $this->_view->tvRegister['TcostcentreId'];
                    $Approve = $this->_view->tvRegister['Approve'];
                    $narration = $this->_view->tvRegister['RecNarration'];
                    $gridType = $this->_view->tvRegister['GridType'];

                    $tvregId = $this->_view->tvRegister['TVRegisterId'];
                    $this->_view->TVNo = $tvNo;
                    $this->_view->TVDate = $tvdate;
                    $this->_view->GPNo = $gpno;
                    $this->_view->GPDate = $gpDate;
                    $this->_view->CCTVNo = $cctvno;
                    $this->_view->purchasetype = $purchasetypeid;
                    $this->_view->accounttype = $accountid;
                    $this->_view->CTVNo = $ctvno;
                    $this->_view->adjustment = $adjust;
                    $this->_view->DespatchDate = $despatchDate;
                    $this->_view->fcompanyName = $Fcompany;
                    $this->_view->fcompanyId = $FcompanyId;
                    $this->_view->tcompanyName = $Tcompany;
                    $this->_view->tcompanyId = $TcompanyId;
                    $this->_view->fcostcentre = $Fcostcentre;
                    $this->_view->fcostcentreId = $FcostcentreId;
                    $this->_view->tcostcentre = $Tcostcentre;
                    $this->_view->tcostcentreId = $TcostcentreId;
                    $this->_view->TvregId = $tvregId;
                    $this->_view->Approve = $Approve;
                    $this->_view->narration = $narration;
                    $this->_view->gridtype = $gridType;


                    //PURCHASETYPE
                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array(),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->where('c.CostCentreId='.$FcostcentreId.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    //ACCOUNT TYPE
                    $selAcc=$sql->select();
                    $selAcc->from(array("a"=>"FA_AccountMaster"))
                        ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
                        ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
                        ->where(array("b.PurchaseTypeId"=>$purchasetypeid));
                    $accStatement = $sql->getSqlStringForSqlObject($selAcc);
                    $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->accType = $accType;

                    //TRANSFER REQUEST RESOURCE
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression("Distinct(a.ResourceId),e.TVRegisterid,a.TransferTransId,a.ItemId,
                        case when a.ItemId>0 then c.ItemCode+ '' +c.BrandName Else b.Code+' - '+b.ResourceName End as [Desc],
                        d.UnitName As UnitName,CAST(a.TransferQty As Decimal(18,5)) As TransferQty,
                        CAST(a.RecdQty As Decimal(18,5)) As RecdQty,CAST(a.AdjustmentQty As Decimal(18,5)) As AdjustmentQty,
                        CAST(a.QRate As Decimal(18,5)) As Rate,
                        CAST((a.RecdQty*a.QRate) As Decimal(18,5)) As Amount")))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'MMS_TransferRegister'), 'e.TVRegisterId=a.TransferRegisterId ', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId')
                        ->where("e.TVRegisterId= $tid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //TRANSFER RESOURCE

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression("d.TransId DecTransId,a.ResourceId,a.ItemId, a.UnitId,b.IPDTransId,
                        c.IPDProjTransId,f.RequestTransId,e.RDecisionNo DecisionNo,g.RequestNo,
                        CAST(b.Qty As Decimal(18,6)) As TransferQty,CAST(c.RecdQty As Decimal(18,6)) As ReceiptQty")))
                        ->join(array('b' => 'MMS_IPDTrans'), 'a.TransferTransId=b.TransferTransId ', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_IPDProjTrans'), 'b.IPDTransId=c.IPDTransId ', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'VM_ReqDecQtyTrans'), 'c.DecTransId=d.TransId And C.DecisionId=D.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'VM_RequestDecision'), 'd.DecisionId=e.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'VM_RequestTrans'), 'd.ReqTransId=f.RequestTransId', array(), $select::JOIN_INNER)
                        ->join(array('g' => 'VM_RequestRegister'), 'f.RequestId=g.RequestId', array(), $select::JOIN_INNER)
                        ->where("a.TransferRegisterId=$tid");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //TRANSFER REQUEST
                    $selectPro = $sql->select();
                    $selectPro->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression("d.TransId DecTransId,e.RCATransId DecATransId, a.ResourceId,a.ItemId,
                        a.UnitId,f.RequestAHTransId,f.AnalysisId,h.RequestTransId, c.IPDProjTransId,
                        g.ParentText PLevel,g.WbsName, CAST((e.TransferQty-e.TranAdjQty) As Decimal(18,6)) As BalQty,
                        CAST(0 As Decimal(18,6)) As ReceiptQty")))
                        ->join(array('b' => 'MMS_IPDTrans'), 'a.TransferTransId=b.TransferTransId', array(), $selectPro::JOIN_INNER)
                        ->join(array('c' => 'MMS_IPDProjTrans'), 'b.IPDTransId=c.IPDTransId', array(), $selectPro::JOIN_INNER)
                        ->join(array('d' => 'VM_ReqDecQtyTrans'), 'c.DecTransId=d.TransId And c.DecisionId=d.DecisionId', array(), $selectPro::JOIN_INNER)
                        ->join(array('e' => 'VM_ReqDecQtyAnalTrans'), 'd.TransId=e.TransId And d.DecisionId=e.DecisionId', array(), $selectPro::JOIN_INNER)
                        ->join(array('f' => 'VM_RequestAnalTrans'), 'e.ReqAHTransId=f.RequestAHTransId', array(), $selectPro::JOIN_INNER)
                        ->join(array('g' => 'Proj_WbsMaster'), 'f.AnalysisId=g.WbsId', array(), $selectPro::JOIN_INNER)
                        ->join(array('h' => 'VM_RequestTrans'), 'f.ReqTransId=h.RequestTransId', array(), $selectPro::JOIN_INNER)
                        ->join(array('i' => 'VM_RequestRegister'), 'h.RequestId=i.RequestId', array(), $selectPro::JOIN_INNER)
                        ->Where(array(" e.RCATransId NOT IN(select a.DecATransId from MMS_IPDAnalTrans a
                        inner join MMS_TransferAnalTrans b on  a.TVAnalTransId=b.TransferAnalTransId
                        inner join MMS_TransferTrans c on b.TransferTransId= c.TransferTransId
                        where c.TransferRegisterId= $tid) And a.TransferRegisterId=$tid and e.TransferQty-e.TranAdjQty > 0 "));

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_IPDAnalTrans"))
                        ->columns(array(new expression("d.TransId As DecTransId,d.RCATransId As DecATransId, a.ResourceId,a.ItemId,
                        a.UnitId,e.RequestAHTransId,e.AnalysisId, e.ReqTransId As RequestTransId,b.IPDProjTransId,
                        f.ParentText PLevel,f.WbsName,CAST((d.TransferQty-d.TranAdjQty) As Decimal(18,6)) As BalQty,
                        CAST(a.Qty As Decimal(18,6)) As ReceiptQty")))
                        ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_IPDTrans'), 'b.IPDTransId=c.IPDTransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'VM_ReqDecQtyAnalTrans'), 'a.DecATransId=d.RCATransId And a.DecTransId=d.TransId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'VM_RequestAnalTrans'), 'd.ReqAHTransId=e.RequestAHTransId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'Proj_WbsMaster'), 'a.AnalysisId=f.WBSId', array(), $select::JOIN_INNER)
                        ->join(array('g' => 'MMS_TransferAnalTrans'), 'a.TVAnalTransId=g.TransferAnalTransId', array(), $select::JOIN_INNER)
                        ->join(array('h' => 'MMS_TransferTrans'), 'g.TransferTransId=h.TransferTransId', array(), $select::JOIN_INNER)
                        ->where("h.TransferRegisterId=$tid");
                    $select->combine($selectPro,'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //WAREHOUSE DETAILS
                    $select = $sql->select();
                    $select-> from(array("a" => "MMS_WareHouse"))
                        ->columns(array(new Expression("a.WareHouseName,c.TransId As WareHouseId,b.CostCentreId,c.Description,CAST(0 As Decimal(18,6)) As Qty")))
                        ->join(array('b' => 'MMS_CCWareHouse'),'a.WareHouseId=b.WareHouseId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'MMS_WareHouseDetails'),'b.WareHouseId=c.WareHouseId', array(), $select::JOIN_INNER)
                        ->where(array("b.CostCentreId = $TcostcentreId and c.LastLevel=1"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_sel_warehouse = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_TransferWareHouseTrans"))
                        ->columns(array(new Expression("c.CostCentreid,b.Transid as WareHouseId,
                        d.WareHouseName,b.Description,e.ResourceId,e.ItemId,CAST(a.Qty As Decimal(18,6)) As Qty")))
                        ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.warehouseid", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_TransferTrans"), "a.Transfertransid=e.transfertransid", array(), $select::JOIN_INNER)
                        ->where(array("c.CostCentreId"=> $TcostcentreId ,"b.LastLevel"=>1, "e.transferregisterid" =>  $tid ));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_tvWareHouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_TransferReceiptWareHouseWbsTrans"))
                        ->columns(array(new Expression("c.CostCentreid,
                        b.Transid as WareHouseId,
                        a.RequestAHTransId as RequestAHTransId,
                        d.WareHouseName,b.Description,e.ResourceId,e.ItemId,
                        CAST(a.Qty As Decimal(18,6)) As Qty,
                        CAST(a.Qty As Decimal(18,6)) As HiddenQty")))
                        ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.warehouseid", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_TransferTrans"), "a.Transfertransid=e.transfertransid", array(), $select::JOIN_INNER)
                        ->where(array("c.CostCentreId"=> $TcostcentreId ,"b.LastLevel"=>1, "e.transferregisterid" =>  $tid ));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_tvwbsWareHouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            //Common function
            CommonHelper::getVoucherNo(305, date('Y/m/d'), 0, 0, $dbAdapter, "I");
            $aVNo = CommonHelper::getVoucherNo(305, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->woNo = "";
            else
                $this->_view->woNo = $aVNo["voucherNo"];
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function tvreceiptSaveAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "transfer","action" => "tvreceipt-wizard"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);


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

//                echo"<pre>";
//               print_r($postParams);
//                echo"</pre>";
//                die;

                $Approve="";
                $Role="";
                $tvRegId = $this->bsf->isNullCheck($postParams['TVRegId'], 'string');
                if ($this->bsf->isNullCheck($tvRegId, 'number') > 0) {
                    $Approve="E";
                    $Role="Transfer-Receipt-Create";
                }else{
                    $Approve="N";
                    $Role="Transfer-Receipt-Modify";
                }

                $ReceiptDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['rDate'], 'string')));
                $Narration = $this->bsf->isNullCheck($postParams['narration'], 'string');
                $tCostCentreId = $this->bsf->isNullCheck($postParams['TCostCentreId'], 'string');
                $TVNo = $this->bsf->isNullCheck($postParams['TVNo'], 'string');
                $gridtype=$this->bsf->isNullCheck($postParams['gridtype'],'number');


                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where(array("CostCentreId"=>$tCostCentreId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$Comp['CompanyId'];

                $select = $sql->select ();
                $select->from(array('a' =>'MMS_TransferReceipt'));
                $select->columns(array("TVRegisterId"))
                    ->where(array("TVRegisterId"=> $tvRegId));
                $insertStatement = $sql->getSqlStringForSqlObject($select);
                $receipt = $dbAdapter->query($insertStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $receiptLen = count($receipt);

                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    if($receiptLen > 0){

                        $update = $sql->update();
                        $update->table('MMS_TransferRegister');
                        $update->set(array(

                            'ReceiptDate' => NULL,
                            'RecNarration' => '',

                        ));
                        $update->where(array('TvRegisterId' => $tvRegId));
                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del = $sql->delete();
                        $del->from('MMS_TransferReceipt')
                            ->where(array("TVRegisterId" => $tvRegId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $update = $sql->update();
                        $update->table('MMS_TransferTrans');
                        $update->set(array(

                            "RecdQty" => 0,
                            "AdjustmentQty" => 0

                        ));
                        $update->where(array('TransferRegisterId' => $tvRegId));
                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $sel = $sql->select();
                        $sel->from(array("a" => "MMS_TransferTrans"))
                            ->columns(array(new Expression('ResourceId,ItemId,RecdQty,(Rate * RecdQty) As Amount')))
                            ->join(array("b" => "MMS_TransferRegister"), "a.TransferRegisterId=b.TVRegisterId", array("ToCostCentreId"), $sel::JOIN_INNER)
                            ->where(array("a.TransferRegisterId" => $tvRegId));
                        $statementPrev = $sql->getSqlStringForSqlObject($sel);
                        $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        foreach ($pre as $preStock) {

                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId","TransferAmount","TransferGAmount"))
                                ->where(array(
                                    "ResourceId" => $preStock['ResourceId'],
                                    "CostCentreId" => $preStock['ToCostCentreId'],
                                    "ItemId" => $preStock['ItemId']
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($stockselId['StockId']) > 0) {

                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "TransferQty" => new Expression('TransferQty-' . $preStock['RecdQty'] . ''),
                                    "ClosingStock" => new Expression('ClosingStock-' . $preStock['RecdQty'] . ''),
                                    "TransferAmount" => new Expression('TransferAmount-' . $preStock['Amount'] . ''),
                                    "TransferGAmount" => new Expression('TransferGAmount-' . $preStock['Amount'] . '')
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            $sel = $sql->select();
                            $sel->from(array("a" => "MMS_TransferTrans"))
                                ->columns(array("TCostCentreId", "ResourceId", "ItemId"))
                                ->join(array("b" => "MMS_TransferWareHouseTrans"), "a.TransferTransId=b.TransferTransId", array("WareHouseId", "Qty"), $sel::JOIN_INNER)
                                ->where(array("a.TransferRegisterId" => $tvRegId));
                            $statementPrev = $sql->getSqlStringForSqlObject($sel);
                            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                            foreach ($pre as $preStockTrans) {

                                if (count($stockselId['StockId']) > 0) {

                                    $sUpdate = $sql->update();
                                    $sUpdate->table('mms_stockTrans');
                                    $sUpdate->set(array(
                                        "TransferQty" => new Expression('TransferQty-' . $preStockTrans['Qty'] . ''),
                                    ));
                                    $sUpdate->where(array("StockId" => $stockselId['StockId'], "WareHouseId" => $preStockTrans['WareHouseId']));
                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                }
                            }
                        }
                        //delete the previous row
                        $select = $sql->select();
                        $select->from('MMS_TransferTrans')
                            ->columns(array("TransferTransId"))
                            ->where(array('TransferRegisterId' => $tvRegId));

                        $del = $sql->delete();
                        $del->from('MMS_TransferWareHouseTrans')
                            ->where->expression('TransferTransId IN ?', array($select));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del1 = $sql->delete();
                        $del1->from('MMS_TransferReceiptWareHouseWbsTrans')
                            ->where->expression('TransferTransId IN ?', array($select));
                        $statement = $sql->getSqlStringForSqlObject($del1);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $sel = $sql->select();
                        $sel->from(array("a" => "MMS_IPDTrans"))
                            ->columns(array(new Expression('IPDTransId,a.RecdQty As RecdQty')))
                            ->join(array("b" => "MMS_TransferTrans"), "a.TransferTransId=b.TransferTransId", array(), $sel::JOIN_INNER)
                            ->where(array("b.TransferRegisterId" => $tvRegId));
                        $statementPrev = $sql->getSqlStringForSqlObject($sel);
                        $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($pre as $preIPD) {

                            $update = $sql->update();
                            $update->table('MMS_IPDTrans');
                            $update->set(array(
                                "RecdQty" => new Expression('RecdQty-' . $preIPD['RecdQty']  . ''),
                            ));
                            $update->where(array('IPDTransId' => $preIPD['IPDTransId'],'Status' => 'T'));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $sel = $sql->select();
                        $sel->from(array("a" => "MMS_IPDProjTrans"))
                            ->columns(array(new Expression('IPDProjTransId,a.RecdQty As RecdQty')))
                            ->join(array("b" => "MMS_TransferTrans"), "a.TransferTransId=b.TransferTransId", array(), $sel::JOIN_INNER)
                            ->where(array("b.TransferRegisterId" => $tvRegId));
                        $statementPrev = $sql->getSqlStringForSqlObject($sel);
                        $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($pre as $preIPDP){

                            $update = $sql->update();
                            $update->table('MMS_IPDProjTrans');
                            $update->set(array(

                                "RecdQty" => new Expression('RecdQty-' . $preIPDP['RecdQty'] . ''),
                            ));
                            $update->where(array('IPDProjTransId' => $preIPDP['IPDProjTransId'], 'Status' => 'T'));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }

                        $sel = $sql->select();
                        $sel->from(array("a" => "VM_ReqDecQtyAnalTrans"))
                            ->columns(array(new Expression("RCATransId,TranAdjQty,ReqAHTransId")))
                            ->join(array("b" => "MMS_IPDProjTrans"), "a.DecisionId=b.DecisionId and a.TransId=b.DecTransId", array(), $sel::JOIN_INNER)
                            ->join(array("c" => "MMS_TransferTrans"), "b.TransferTransId=c.TransferTransId", array(), $sel::JOIN_INNER)
                            ->join(array("d" => "VM_RequestAnalTrans"), "a.ReqAHTransId=d.RequestAHTransId", array("BalQty","TransferQty"), $sel::JOIN_INNER)
                            ->where(array("c.TransferRegisterId" => $tvRegId));
                        $statementPrev = $sql->getSqlStringForSqlObject($sel);
                        $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach($pre as $preVMDQ){

                            $update = $sql->update();
                            $update->table('VM_ReqDecQtyAnalTrans');
                            $update->set(array(
                                "TranAdjQty" => new Expression('TranAdjQty-' . $preVMDQ['TranAdjQty']  . ''),
                            ));
                            $update->where(array('RCATransId' => $preVMDQ['RCATransId'] ));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $update = $sql->update();
                            $update->table('VM_RequestAnalTrans');
                            $update->set(array(

                                "BalQty" => new Expression('BalQty+' . $preVMDQ['BalQty'] . ''),
                                "TransferQty" => new Expression('TransferQty-' . $preVMDQ['TransferQty'] . ''),

                            ));
                            $update->where(array('RequestAHTransId' => $preVMDQ['RCATransId'] ));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }

                        $sel1 = $sql->select();
                        $sel1->from(array("a" => "MMS_IPDAnalTrans"))
                            ->columns(array(new Expression("IPDAHTransId")))
                            ->join(array("b" => "MMS_IPDProjTrans"), "a.IPDProjTransId=b.IPDProjTransId", array(), $sel1::JOIN_INNER)
                            ->join(array("c" => "MMS_TransferTrans"), "c.TransferTransId=b.TransferTransId", array(), $sel1::JOIN_INNER)
                            ->where(array("c.TransferRegisterId" => $tvRegId, "a.Status" => 'T'));

                        $del = $sql->delete();
                        $del->from('MMS_IPDAnalTrans')
                            ->where->expression('IPDAHTransId IN ?', array($sel1));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $sel2 = $sql->select();
                        $sel2->from(array("a" => "MMS_TransferAnalTrans"))
                            ->columns(array(new Expression("TransferAnalTransId")))
                            ->join(array("b" => "MMS_TransferTrans"), "a.TransferTransId=b.TransferTransId", array(), $sel2::JOIN_INNER)
                            ->where(array("b.TransferRegisterId" => $tvRegId));

                        $del = $sql->delete();
                        $del->from('MMS_TransferAnalTrans')
                            ->where->expression('TransferAnalTransId IN ?', array($sel2));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        // tvregister-update -edit mode
                        $update = $sql->update();
                        $update->table('MMS_TransferRegister');
                        $update->set(array(

                            'ReceiptDate' => $ReceiptDate,
                            'RecNarration' => $Narration

                        ));
                        $update->where(array('TvRegisterId' => $tvRegId));
                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert('MMS_TransferReceipt');
                        $insert->values(array(
                            "TVRegisterId" => $tvRegId,
                        ));
                        $insertStatement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($insertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $tvrowId = $postParams['rowid'];
                        for ($i = 1; $i <= $tvrowId; $i++) {

                            $update = $sql->update();
                            $update->table('MMS_TransferTrans');
                            $update->set(array(

                                "RecdQty" => $this->bsf->isNullCheck($postParams['rqty_' . $i], 'number' . ''),
                                "AdjustmentQty" => $this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')

                            ));
                            $update->where(array('TransferTransId' => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . '')));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            // stock details adding
                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array("CostCentreId" => $this->bsf->isNullCheck($postParams['TCostCentreId'], 'number' . ''),
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number' . ''),
                                    "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number' . '')
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $ssId = $stockselId['StockId'];

                            if (count($ssId) > 0) {

                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "TransferQty" => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['rqty_' . $i], 'number') . ''),
                                    "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['rqty_' . $i], 'number') . ''),
                                    "TransferAmount" => new Expression('TransferAmount+' . $this->bsf->isNullCheck($postParams['amount_' . $i], 'number') . ''),
                                    "TransferGAmount" => new Expression('TransferGAmount+' . $this->bsf->isNullCheck($postParams['amount_' . $i], 'number') . ''),
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                if ($this->bsf->isNullCheck($postParams['tqty_' . $i], 'number') != '' || $this->bsf->isNullCheck($postParams['tqty_' . $i], 'number') > 0) {

                                    $stock = $sql->insert('MMS_Stock');
                                    $stock->values(array("CostCentreId" => $this->bsf->isNullCheck($postParams['TCostCentreId'], 'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                        "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                        "TransferQty" => $this->bsf->isNullCheck($postParams['tqty_' . $i], 'number' . ''),
                                        "ClosingStock" => $this->bsf->isNullCheck($postParams['tqty_' . $i], 'number' . ''),
                                        "TransferAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number' . ''),
                                        "TransferGAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number' . '')
                                    ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                    $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $StockId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                }
                                $ssId = $StockId;
                            } // end of stock


                            //warehouse qty insert
                            $whTotal = $postParams['wh_' . $i . '_rowid'];
                            for ($w = 1; $w <= $whTotal; $w++) {

                                if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0) {

                                    $whInsert = $sql->insert('MMS_TransferWareHouseTrans');
                                    $whInsert->values(array(

                                        "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_warehouseid_' . $w], 'number' . ''),
                                        "CostCentreId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_costcentreid_' . $w], 'number', ''),
                                        "TransferTransId" => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . ''),
                                        "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number' . '')
                                    ));
                                    $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                    $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }


                                //stock trans adding
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                    ->columns(array("StockId"))
                                    ->where(array("WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_warehouseid_' . $w], 'number'), "StockId" => $ssId));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if (count($sId['StockId']) > 0) {

                                    $sUpdate = $sql->update();
                                    $sUpdate->table('mms_stockTrans');
                                    $sUpdate->set(array(
                                        "TransferQty" => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number' . '') . ''),
                                    ));
                                    $sUpdate->where(array("StockId" => $sId['StockId'], "WareHouseId" => $postParams['wh_' . $i . '_warehouseid_' . $w]));
                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {

                                    if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number') > 0) {

                                        $stock1 = $sql->insert('mms_stockTrans');
                                        $stock1->values(array("WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_warehouseid_' . $w], 'number' . ''),
                                            "StockId" => $ssId,
                                            "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_warehouseid_' . $w], 'number' . ''),
                                            "DCQty" => 0,
                                            "OpeningStock" => 0,
                                            "BillQty" => 0,
                                            "IssueQty" => 0,
                                            "TransferQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_qty_' . $w], 'number' . ''),
                                            "ReturnQty" => 0

                                        ));
                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                } //end of the stocktrans - edit
                            } // end of warehouse - edit
                            $ttransTotal = $postParams['iow_' . $i . '_rowid'];
                            for ($j = 1; $j <= $ttransTotal; $j++) {

                                if($postParams['iow_' . $i . '_reqty_' . $j] > 0){

                                    $update = $sql->update();
                                    $update->table('MMS_IPDTrans');
                                    $update->set(array(
                                        "RecdQty" => new Expression('RecdQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_reqty_' . $j], 'number' , '') . ''),
                                    ));
                                    $update->where(array('IPDTransId' =>  $this->bsf->isNullCheck($postParams['iow_' . $i . '_ipdtransid_' . $j], 'number' , '') ));
                                    $updateStatement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $update = $sql->update();
                                    $update->table('MMS_IPDProjTrans');
                                    $update->set(array(

                                        "RecdQty" => new Expression('RecdQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_reqty_' . $j], 'number' , '') . ''),
                                    ));
                                    $update->where(array('IPDProjTransId' => $this->bsf->isNullCheck($postParams['iow_' . $i . '_ipdprojtransid_' . $j], 'number' , '') ));
                                    $updateStatement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                                    //warehouse qty insert -edit
                                    $whTotal = $postParams['wh_' . $i . '_po_' . $j . '_wrowid'];

                                    for ($w = 1; $w <= $whTotal; $w++) {
                                        if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number') > 0) {

                                            $whInsert = $sql->insert('MMS_TransferWareHouseTrans');
                                            $whInsert->values(array(
                                                "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w], 'number' . ''),
                                                "CostCentreId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_costcentreid_' . $w], 'number', ''),
                                                "TransferTransId" => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . ''),
                                                "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number' . '')
                                            ));
                                            $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                            $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }


                                        //stock trans adding
                                        $stockSelect = $sql->select();
                                        $stockSelect->from(array("a" => "mms_stockTrans"))
                                            ->columns(array("StockId"))
                                            ->where(array("WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w], 'number'),
                                                "StockId" => $ssId));
                                        $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                        $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if (count($sId['StockId']) > 0) {

                                            $sUpdate = $sql->update();
                                            $sUpdate->table('mms_stockTrans');
                                            $sUpdate->set(array(
                                                "TransferQty" => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number' . '') . ''),
                                            ));
                                            $sUpdate->where(array("StockId" => $sId['StockId'],
                                                "WareHouseId" => $postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w]));
                                            $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        } else {

                                            if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number') > 0) {

                                                $stock1 = $sql->insert('mms_stockTrans');
                                                $stock1->values(array("WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w], 'number' . ''),
                                                    "StockId" => $ssId,
                                                    "DCQty" => 0,
                                                    "OpeningStock" => 0,
                                                    "BillQty" => 0,
                                                    "IssueQty" => 0,
                                                    "TransferQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number' . ''),
                                                    "ReturnQty" => 0

                                                ));
                                                $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        } //end of the stocktrans

                                    } // end of the warehouse -edit
                                }
                                $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                for ($k = 1; $k <= $wbsTotal; $k++) {

                                    if ($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''] > 0) {

                                        $update = $sql->update();
                                        $update->table('VM_ReqDecQtyAnalTrans');
                                        $update->set(array(
                                            "TranAdjQty" => new Expression('TranAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number') . ''),
                                        ));
                                        $update->where(array('RCATransId' => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . ''], 'number') . ''));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $update = $sql->update();
                                        $update->table('VM_RequestAnalTrans');
                                        $update->set(array(

                                            "BalQty" => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number') . ''),
                                            "TransferQty" => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number') . ''),

                                        ));
                                        $update->where(array('RequestAHTransId' => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_reqahtransid_' . $k . ''], 'number')));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $state = 'T';
                                        $Insert = $sql->insert('MMS_IPDAnalTrans');
                                        $Insert->values(array(
                                            "IPDProjTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_ipdptid_' . $k . ''], 'number'),
                                            "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_analysisid_' . $k . ''], 'number'),
                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_resourceid_' . $k . ''], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_itemid_' . $k . ''], 'number'),
                                            "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_unitid_' . $k . ''], 'number'),
                                            "Status" => $state,
                                            "DecTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_dectransid_' . $k . ''], 'number'),
                                            "DecATransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . ''], 'number')

                                        ));
                                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $ipdAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        //wbs-warehouse-insert-add
                                        $wareTotal = $postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_wrowid'];
                                        for ($w = 1; $w <= $wareTotal; $w++) {
                                            if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w], 'number') > 0) {
                                                $whInsert = $sql->insert("MMS_TransferReceiptWareHouseWbsTrans");
                                                $whInsert->values(array(
                                                    "TransferTransId" => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . ''),
                                                    "IPDProjTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_ipdptid_' . $k . ''], 'number'),
                                                    "RequestAHTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_reqahtransid_' . $k . ''], 'number'),
                                                    "IPDAHTransId" => $ipdAHTransId,
                                                    "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_warehouseid_' . $w . ''], 'number' . ''),
                                                    "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w . ''], 'number' . '')));
                                                $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                } // end of k - edit
                                $transferTransId = $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . '');
                                $fselect = $sql->select();
                                $fselect->from(array("a" => "MMS_TransferReceiptWareHouseWbsTrans"))
                                    ->columns(array(new Expression("SUM(a.Qty) as Qty, A.WareHouseId as WareHouseId,
                                           a.TransferTransId as TransferTransId")))
                                    ->where(array("TransferTransId" => $transferTransId));
                                $fselect->group(array("a.WareHouseId","a.TransferTransId"));
                                $statement = $sql->getSqlStringForSqlObject($fselect);
                                $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                foreach($ware As $wareData){
                                    if($wareData['Qty'] > 0){
                                        $whInsert = $sql->insert('MMS_TransferWareHouseTrans');
                                        $whInsert->values(array(
                                            "TransferTransId" => $wareData['TransferTransId'],
                                            "CostCentreId" => $tCostCentreId,
                                            "WareHouseId" => $wareData['WareHouseId'],
                                            "Qty" => $wareData['Qty']
                                        ));
                                        $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                        $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        //stock trans adding
                                        $stockSelect = $sql->select();
                                        $stockSelect->from(array("a" => "mms_stockTrans"))
                                            ->columns(array("StockId"))
                                            ->where(array("WareHouseId" => $wareData['WareHouseId'],
                                                "StockId" => $ssId));
                                        $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                        $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if (count($sId['StockId']) > 0) {

                                            $sUpdate = $sql->update();
                                            $sUpdate->table('mms_stockTrans');
                                            $sUpdate->set(array(
                                                "TransferQty" => new Expression('TransferQty+' . $wareData['Qty'] . ''),
                                            ));
                                            $sUpdate->where(array("StockId" => $sId['StockId'],
                                                "WareHouseId" => $wareData['WareHouseId']));
                                            $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        } else {

                                            if ($this->bsf->isNullCheck($wareData['Qty'], 'number') > 0) {

                                                $stock1 = $sql->insert('mms_stockTrans');
                                                $stock1->values(array(
                                                    "StockId" => $ssId,
                                                    "WareHouseId" => $wareData['WareHouseId'],
                                                    "DCQty" => 0,
                                                    "OpeningStock" => 0,
                                                    "BillQty" => 0,
                                                    "IssueQty" => 0,
                                                    "TransferQty" => $wareData['Qty'],
                                                    "ReturnQty" => 0

                                                ));
                                                $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        } //end of the stocktrans
                                    }
                                }
                                $ipdptId =  $this->bsf->isNullCheck($postParams['iow_' . $i . '_ipdprojtransid_' . $j], 'number', '');

                                $Select = $sql->select();
                                $Select->from(array("a" => "MMS_IPDAnalTrans"))
                                    ->columns(array(new Expression("a.AnalysisId,a.ResourceId,a.ItemId,a.UnitId,sum(a.Qty) As Qty")))
                                    ->where(array("a.IPDProjTransId" => $ipdptId));
                                $Select->group(array("a.AnalysisId","a.ResourceId","a.ItemId","a.UnitId"));
                                $Statement = $sql->getSqlStringForSqlObject($Select);
                                $ipaTrans = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                foreach ($ipaTrans as $arripdanal) {

                                    if ($arripdanal['Qty'] > 0) {

                                        $advanceInsert = $sql->insert('MMS_TransferAnalTrans');
                                        $advanceInsert->values(array(
                                            "TransferTransId" => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . ''),
                                            "CostCentreId" => $this->bsf->isNullCheck($postParams['TCostCentreId'], 'number' . ''),
                                            "AnalysisId" => $arripdanal['AnalysisId'],
                                            "ResourceId" => $arripdanal['ResourceId'],
                                            "ItemId" => $arripdanal['ItemId'],
                                            "TransferQty" => $arripdanal['Qty'],
                                            "UnitId" => $arripdanal['UnitId'],
                                            "Type" => $state,
                                        ));
                                        $advanceStatement = $sql->getSqlStringForSqlObject($advanceInsert);
                                        $dbAdapter->query($advanceStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $TransferAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $update = $sql->update();
                                        $update->table('MMS_IPDAnalTrans');
                                        $update->set(array(
                                            "TVAnalTransId" => $TransferAnalTransId
                                        ));
                                        $update->where(array("IPDProjTransId" => $ipdptId,"AnalysisId" => $arripdanal['AnalysisId'] ));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    }
                                }
                            } // end of j- edit
                        } // end of i -edit
                    }
                    else{

                        $update = $sql->update();
                        $update->table('MMS_TransferRegister');
                        $update->set(array(

                            'ReceiptDate' => $ReceiptDate,
                            'RecNarration' => $Narration
                        ));
                        $update->where(array('TvRegisterId' => $tvRegId));
                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $insert = $sql->insert('MMS_TransferReceipt');
                        $insert->values(array(
                            "TVRegisterId" => $tvRegId,
                            "GridType" => $gridtype
                        ));
                        $insertStatement = $sql->getSqlStringForSqlObject($insert);
                        $dbAdapter->query($insertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $tvrowId = $postParams['rowid'];
                        for ($i = 1; $i <= $tvrowId; $i++) {
                            $update = $sql->update();
                            $update->table('MMS_TransferTrans');
                            $update->set(array(

                                "RecdQty" => $this->bsf->isNullCheck($postParams['rqty_' . $i], 'number' . ''),
                                "AdjustmentQty" => $this->bsf->isNullCheck($postParams['aqty_' . $i], 'number' . '')

                            ));
                            $update->where(array('TransferTransId' => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . '')));
                            $updateStatement = $sql->getSqlStringForSqlObject($update);
                            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            // stock details adding

                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array("CostCentreId" => $this->bsf->isNullCheck($postParams['TCostCentreId'], 'number' . ''),
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number' . ''),
                                    "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number' . '')
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            $ssId = $stockselId['StockId'];

                            if (count($ssId) > 0) {

                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "TransferQty" => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['rqty_' . $i], 'number') . ''),
                                    "ClosingStock" => new Expression('ClosingStock+' . $this->bsf->isNullCheck($postParams['rqty_' . $i], 'number') . ''),
                                    "TransferAmount" => new Expression('TransferAmount+' . $this->bsf->isNullCheck($postParams['amount_' . $i], 'number') . ''),
                                    "TransferGAmount" => new Expression('TransferGAmount+' . $this->bsf->isNullCheck($postParams['amount_' . $i], 'number') . ''),
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                if ($this->bsf->isNullCheck($postParams['tqty_' . $i], 'number') != '' || $this->bsf->isNullCheck($postParams['tqty_' . $i], 'number') > 0) {

                                    $stock = $sql->insert('MMS_Stock');
                                    $stock->values(array("CostCentreId" => $this->bsf->isNullCheck($postParams['TCostCentreId'], 'number'),
                                        "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'),
                                        "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                        "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                        "TransferQty" => $this->bsf->isNullCheck($postParams['tqty_' . $i], 'number' . ''),
                                        "ClosingStock" => $this->bsf->isNullCheck($postParams['tqty_' . $i], 'number' . ''),
                                        "TransferAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number' . ''),
                                        "TransferGAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number' . '')
                                    ));
                                    $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                    $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $StockId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                }
                                $ssId = $StockId;
                            } // end of stock

                            $ttransTotal = $postParams['iow_' . $i . '_rowid'];
                            for ($j = 1; $j <= $ttransTotal; $j++) {

                                if ($postParams['iow_' . $i . '_reqty_' . $j] > 0) {

                                    $update = $sql->update();
                                    $update->table('MMS_IPDTrans');
                                    $update->set(array(
                                        "RecdQty" => new Expression('RecdQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_reqty_' . $j], 'number', '') . ''),
                                    ));
                                    $update->where(array('IPDTransId' => $this->bsf->isNullCheck($postParams['iow_' . $i . '_ipdtransid_' . $j], 'number', '')));
                                    $updateStatement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $update = $sql->update();
                                    $update->table('MMS_IPDProjTrans');
                                    $update->set(array(

                                        "RecdQty" => new Expression('RecdQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_reqty_' . $j], 'number', '') . ''),
                                    ));
                                    $update->where(array('IPDProjTransId' => $this->bsf->isNullCheck($postParams['iow_' . $i . '_ipdprojtransid_' . $j], 'number', '')));
                                    $updateStatement = $sql->getSqlStringForSqlObject($update);
                                    $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    //warehouse qty insert
                                    $whTotal = $postParams['wh_' . $i . '_po_' . $j . '_wrowid'];

                                    for ($w = 1; $w <= $whTotal; $w++) {
                                        if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number') > 0) {

                                            $whInsert = $sql->insert('MMS_TransferWareHouseTrans');
                                            $whInsert->values(array(
                                                "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w], 'number' . ''),
                                                "CostCentreId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_costcentreid_' . $w], 'number', ''),
                                                "TransferTransId" => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . ''),
                                                "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number' . '')
                                            ));
                                            $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                            $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }


                                        //stock trans adding
                                        $stockSelect = $sql->select();
                                        $stockSelect->from(array("a" => "mms_stockTrans"))
                                            ->columns(array("StockId"))
                                            ->where(array("WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w], 'number'),
                                                "StockId" => $ssId));
                                        $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                        $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if (count($sId['StockId']) > 0) {

                                            $sUpdate = $sql->update();
                                            $sUpdate->table('mms_stockTrans');
                                            $sUpdate->set(array(
                                                "TransferQty" => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number' . '') . ''),
                                            ));
                                            $sUpdate->where(array("StockId" => $sId['StockId'],
                                                "WareHouseId" => $postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w]));
                                            $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        } else {

                                            if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number') > 0) {

                                                $stock1 = $sql->insert('mms_stockTrans');
                                                $stock1->values(array("WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_warehouseid_' . $w], 'number' . ''),
                                                    "StockId" => $ssId,
                                                    "DCQty" => 0,
                                                    "OpeningStock" => 0,
                                                    "BillQty" => 0,
                                                    "IssueQty" => 0,
                                                    "TransferQty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_qty_' . $w], 'number' . ''),
                                                    "ReturnQty" => 0

                                                ));
                                                $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        } //end of the stocktrans

                                    } // end of the warehouse

                                }

                                $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                for ($k = 1; $k <= $wbsTotal; $k++) {

                                    if ($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''] > 0) {

                                        $update = $sql->update();
                                        $update->table('VM_ReqDecQtyAnalTrans');
                                        $update->set(array(
                                            "TranAdjQty" => new Expression('TranAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number') . ''),
                                        ));
                                        $update->where(array('RCATransId' => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . ''], 'number') . ''));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                                        $update = $sql->update();
                                        $update->table('VM_RequestAnalTrans');
                                        $update->set(array(

                                            "BalQty" => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number') . ''),
                                            "TransferQty" => new Expression('TransferQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number') . ''),

                                        ));
                                        $update->where(array('RequestAHTransId' => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_reqahtransid_' . $k . ''], 'number')));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $state = 'T';
                                        $Insert = $sql->insert('MMS_IPDAnalTrans');
                                        $Insert->values(array(
                                            "IPDProjTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_ipdptid_' . $k . ''], 'number'),
                                            "AnalysisId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_analysisid_' . $k . ''], 'number'),
                                            "ResourceId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_resourceid_' . $k . ''], 'number'),
                                            "ItemId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_itemid_' . $k . ''], 'number'),
                                            "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_rqty_' . $k . ''], 'number'),
                                            "UnitId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_unitid_' . $k . ''], 'number'),
                                            "Status" => $state,
                                            "DecTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_dectransid_' . $k . ''], 'number'),
                                            "DecATransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . ''], 'number')

                                        ));
                                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $ipdAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        //wbs-warehouse-insert-add
                                        $wareTotal = $postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_wrowid'];
                                        for ($w = 1; $w <= $wareTotal; $w++) {
                                            if ($this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w], 'number') > 0) {
                                                $whInsert = $sql->insert("MMS_TransferReceiptWareHouseWbsTrans");
                                                $whInsert->values(array(
                                                    "TransferTransId" => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . ''),
                                                    "RequestAHTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_reqahtransid_' . $k . ''], 'number'),
                                                    "IPDProjTransId" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_ipdptid_' . $k . ''], 'number'),
                                                    "IPDAHTransId" => $ipdAHTransId,
                                                    "WareHouseId" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_warehouseid_' . $w . ''], 'number' . ''),
                                                    "Qty" => $this->bsf->isNullCheck($postParams['wh_' . $i . '_po_' . $j . '_wbs_' . $k . '_qty_' . $w . ''], 'number' . '')));
                                                $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                                $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                } //end of k
                                $transferTransId = $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . '');
                                $fselect = $sql->select();
                                $fselect->from(array("a" => "MMS_TransferReceiptWareHouseWbsTrans"))
                                    ->columns(array(new Expression("SUM(a.Qty) as Qty, A.WareHouseId as WareHouseId,
                                           a.TransferTransId as TransferTransId")))
                                    ->where(array("TransferTransId" => $transferTransId));
                                $fselect->group(array("a.WareHouseId","a.TransferTransId"));
                                $statement = $sql->getSqlStringForSqlObject($fselect);
                                $ware = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                foreach($ware As $wareData){
                                    if($wareData['Qty'] > 0){
                                        $whInsert = $sql->insert('MMS_TransferWareHouseTrans');
                                        $whInsert->values(array(
                                            "TransferTransId" => $wareData['TransferTransId'],
                                            "CostCentreId" => $tCostCentreId,
                                            "WareHouseId" => $wareData['WareHouseId'],
                                            "Qty" => $wareData['Qty']
                                        ));
                                        $whInsertStatement = $sql->getSqlStringForSqlObject($whInsert);
                                        $dbAdapter->query($whInsertStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        //stock trans adding
                                        $stockSelect = $sql->select();
                                        $stockSelect->from(array("a" => "mms_stockTrans"))
                                            ->columns(array("StockId"))
                                            ->where(array("WareHouseId" => $wareData['WareHouseId'],
                                                "StockId" => $ssId));
                                        $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                        $sId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                        if (count($sId['StockId']) > 0) {

                                            $sUpdate = $sql->update();
                                            $sUpdate->table('mms_stockTrans');
                                            $sUpdate->set(array(
                                                "TransferQty" => new Expression('TransferQty+' . $wareData['Qty'] . ''),
                                            ));
                                            $sUpdate->where(array("StockId" => $sId['StockId'],
                                                "WareHouseId" => $wareData['WareHouseId']));
                                            $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        } else {

                                            if ($this->bsf->isNullCheck($wareData['Qty'], 'number') > 0) {

                                                $stock1 = $sql->insert('mms_stockTrans');
                                                $stock1->values(array(
                                                    "StockId" => $ssId,
                                                    "WareHouseId" => $wareData['WareHouseId'],
                                                    "DCQty" => 0,
                                                    "OpeningStock" => 0,
                                                    "BillQty" => 0,
                                                    "IssueQty" => 0,
                                                    "TransferQty" => $wareData['Qty'],
                                                    "ReturnQty" => 0

                                                ));
                                                $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                                $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        } //end of the stocktrans
                                    }
                                }

                                $ipdptId = $this->bsf->isNullCheck($postParams['iow_' . $i . '_ipdprojtransid_' . $j], 'number', '');

                                $Select = $sql->select();
                                $Select->from(array("a" => "MMS_IPDAnalTrans"))
                                    ->columns(array(new Expression("a.AnalysisId,a.ResourceId,a.ItemId,a.UnitId,sum(a.Qty) As Qty")))
                                    ->where(array("a.IPDProjTransId" => $ipdptId));
                                $Select->group(array("a.AnalysisId", "a.ResourceId", "a.ItemId", "a.UnitId"));
                                $Statement = $sql->getSqlStringForSqlObject($Select);
                                $ipaTrans = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                foreach ($ipaTrans as $arripdanal) {

                                    if ($arripdanal['Qty'] > 0) {

                                        $advanceInsert = $sql->insert('MMS_TransferAnalTrans');
                                        $advanceInsert->values(array(
                                            "TransferTransId" => $this->bsf->isNullCheck($postParams['transfertransId_' . $i], 'number' . ''),
                                            "CostCentreId" => $this->bsf->isNullCheck($postParams['TCostCentreId'], 'number' . ''),
                                            "AnalysisId" => $arripdanal['AnalysisId'],
                                            "ResourceId" => $arripdanal['ResourceId'],
                                            "ItemId" => $arripdanal['ItemId'],
                                            "TransferQty" => $arripdanal['Qty'],
                                            "UnitId" => $arripdanal['UnitId'],
                                            "Type" => $state,
                                        ));
                                        $advanceStatement = $sql->getSqlStringForSqlObject($advanceInsert);
                                        $dbAdapter->query($advanceStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $TransferAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        $update = $sql->update();
                                        $update->table('MMS_IPDAnalTrans');
                                        $update->set(array(
                                            "TVAnalTransId" => $TransferAnalTransId
                                        ));
                                        $update->where(array("IPDProjTransId" => $ipdptId, "AnalysisId" => $arripdanal['AnalysisId']));
                                        $updateStatement = $sql->getSqlStringForSqlObject($update);
                                        $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                            }// end of j
                        } // end of i
                    } //end of else

                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Transfer-Receipt',$tvRegId,$tCostCentreId,$CompanyId, 'MMS',$TVNo,$this->auth->getIdentity()->UserId,0,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'transfer', 'action' => 'tvreceipt-register'));
                } catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                //begin trans try block example ends
            }

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function tvreceiptRegisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Transfer","action" => "tvreceipt-wizard"));
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
                    $selReg = $sql -> select();
                    $selReg->from(array("a"=>'MMS_TransferRegister'))
                        ->columns(array("TVRegisterId"=>new Expression("a.TVRegisterId"),"TransferNo"=>new Expression("a.TVNo"),
                            "TransferDate"=>new Expression("Convert(Varchar(10),a.TVDate,103)"),"FromCompany"=>new Expression("c.CompanyName"),
                            "FromCostCentre"=>new Expression("b.CostCentreName"),"ToCompany"=>new Expression("e.CompanyName"),
                            "ToCostCentre"=>new Expression("d.CostCentreName"),"ReceiptDate"=>new Expression("Convert(Varchar(10),a.ReceiptDate,103)"),
                            "Approve"=>new Expression("Case When f.Approve='Y' Then 'Yes' When f.Approve='P' then 'Partial' Else 'No' End")  ))
                        ->join(array('b'=>'WF_OperationalCostCentre'),"a.FromCostCentreId=b.CostCentreId",array(),$selReg::JOIN_INNER)
                        ->join(array('c'=>'WF_CompanyMaster'),"a.FromCompanyId=c.CompanyId",array(),$selReg::JOIN_INNER)
                        ->join(array('d'=>'WF_OperationalCostCentre'),"a.ToCostCentreId=d.CostCentreId",array(),$selReg::JOIN_INNER)
                        ->join(array('e'=>'WF_CompanyMaster'),"a.ToCompanyId=e.CompanyId",array(),$selReg::JOIN_INNER)
                        ->join(array('f'=>'MMS_TransferReceipt'),"a.TVRegisterId=f.TVRegisterId",array(),$selReg::JOIN_INNER);
                    $selReg->order(new Expression("a.TVNo DESC"));
                    $regStatement = $sql->getSqlStringForSqlObject($selReg);
                    $resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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
        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;

    }

    public function tvreceiptDetailsAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Transfer","action" => "tvreceiptRegister"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $tid = $this->params()->fromRoute('id');


        if($this->getRequest()->isXmlHttpRequest())	{
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();

            }

            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;

        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
        }

        $select = $sql->select();
        $select -> from(array('a' => 'MMS_TransferRegister'))
            ->columns(array(new Expression("a.TVRegisterId,TVNo,CONVERT(varchar(10),a.TVDate,105) As TVDate,
				GPNo,CONVERT(varchar(10),a.GPDate,105) As GPDate,CCTVNo,x.PurchaseTypeId,x.AccountId,
				CAST(a.Amount As Decimal(18,2)) As Amount,
				CTVNo,Adjustment,CONVERT(varchar(10),a.DespatchDate,105) As DespatchDate,
				b.CompanyName As Fcompany,b.CompanyId As FcompanyId,
				c.CompanyName As Tcompany,c.CompanyId As TcompanyId,Case When f.Approve='Y' Then 'Yes' When f.Approve='P' Then 'Partial' Else 'No' End  As Approve,
				d.CostCentreName As Fcostcentre,d.CostCentreId As FcostcentreId,
				e.CostCentreName As Tcostcentre,e.CostCentreId As TcostcentreId,
				a.RecNarration,f.GridType,CONVERT(varchar(10),a.ReceiptDate,105) As ReceiptDate")))
            ->join(array('b' => 'WF_CompanyMaster'), 'a.FromCompanyId=b.CompanyId', array(), $select::JOIN_INNER)
            ->join(array('c' => 'WF_CompanyMaster'), 'a.ToCompanyId=c.CompanyId', array(), $select::JOIN_INNER)
            ->join(array('d' => 'WF_OperationalCostCentre'), 'a.FromCostCentreId=d.CostCentreId', array(), $select::JOIN_INNER)
            ->join(array('e' => 'WF_OperationalCostCentre'), 'a.ToCostCentreId=e.CostCentreId', array(), $select::JOIN_INNER)
            ->join(array('f' => 'MMS_TransferReceipt'), 'a.TVRegisterId=f.TVRegisterId', array(), $select::JOIN_INNER)
            -> join(array("x" => "MMS_PurchaseType"),"a.PurchaseTypeId=x.PurchaseTypeId",array("PurchaseTypeName"),$select::JOIN_INNER)
            ->where(array("a.TVRegisterId"=> $tid));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->tvRegister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $tvNo = $this->_view->tvRegister['TVNo'];
        $tvdate = $this->_view->tvRegister['TVDate'];
        $gpno = $this->_view->tvRegister['GPNo'];
        $gpDate = $this->_view->tvRegister['GPDate'];
        $cctvno = $this->_view->tvRegister['CCTVNo'];
        $purchasetypeid = $this->_view->tvRegister['PurchaseTypeId'];
        $accountid = $this->_view->tvRegister['AccountId'];
        $ctvno = $this->_view->tvRegister['CTVNo'];
        $adjust = $this->_view->tvRegister['Adjustment'];
        $despatchDate = $this->_view->tvRegister['DespatchDate'];
        $Fcompany = $this->_view->tvRegister['Fcompany'];
        $FcompanyId = $this->_view->tvRegister['FcompanyId'];
        $Tcompany = $this->_view->tvRegister['Tcompany'];
        $TcompanyId = $this->_view->tvRegister['TcompanyId'];
        $Fcostcentre = $this->_view->tvRegister['Fcostcentre'];
        $FcostcentreId = $this->_view->tvRegister['FcostcentreId'];
        $Tcostcentre = $this->_view->tvRegister['Tcostcentre'];
        $TcostcentreId = $this->_view->tvRegister['TcostcentreId'];
        $Approve = $this->_view->tvRegister['Approve'];
        $narration = $this->_view->tvRegister['RecNarration'];
        $gridType = $this->_view->tvRegister['GridType'];
        $ReceiptDate = $this->_view->tvRegister['ReceiptDate'];
        $amount = $this->_view->tvRegister['Amount'];
        $PurchaseTypeName = $this->_view->tvRegister['PurchaseTypeName'];

        $tvregId = $this->_view->tvRegister['TVRegisterId'];
        $this->_view->TVNo = $tvNo;
        $this->_view->TVDate = $tvdate;
        $this->_view->GPNo = $gpno;
        $this->_view->GPDate = $gpDate;
        $this->_view->CCTVNo = $cctvno;
        $this->_view->purchasetype = $purchasetypeid;
        $this->_view->accounttype = $accountid;
        $this->_view->CTVNo = $ctvno;
        $this->_view->adjustment = $adjust;
        $this->_view->DespatchDate = $despatchDate;
        $this->_view->fcompanyName = $Fcompany;
        $this->_view->fcompanyId = $FcompanyId;
        $this->_view->tcompanyName = $Tcompany;
        $this->_view->tcompanyId = $TcompanyId;
        $this->_view->fcostcentre = $Fcostcentre;
        $this->_view->fcostcentreId = $FcostcentreId;
        $this->_view->tcostcentre = $Tcostcentre;
        $this->_view->tcostcentreId = $TcostcentreId;
        $this->_view->TvregId = $tvregId;
        $this->_view->Approve = $Approve;
        $this->_view->narration = $narration;
        $this->_view->gridtype = $gridType;
        $this->_view->ReceiptDate = $ReceiptDate;
        $this->_view->amount = $amount;
        $this->_view->PurchaseTypeName = $PurchaseTypeName;


        //ACCOUNT TYPE
        $selAcc=$sql->select();
        $selAcc->from(array("a"=>"FA_AccountMaster"))
            ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
            ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
            ->where(array("b.PurchaseTypeId"=>$purchasetypeid));
        $accStatement = $sql->getSqlStringForSqlObject($selAcc);
        $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->accType = $accType['value'];

        //TRANSFER REQUEST RESOURCE
        $select = $sql->select();
        $select->from(array("a" => "MMS_TransferTrans"))
            ->columns(array(new Expression("Distinct(a.ResourceId),e.TVRegisterid,a.TransferTransId,a.ItemId,
				case when a.ItemId>0 then c.ItemCode+ '' +c.BrandName Else b.Code+' - '+b.ResourceName End as [Desc],
				d.UnitName As UnitName,CAST(a.TransferQty As Decimal(18,3)) As TransferQty,
				CAST(a.RecdQty As Decimal(18,3)) As RecdQty,CAST(a.AdjustmentQty As Decimal(18,3)) As AdjustmentQty,
				CAST(a.QRate As Decimal(18,2)) As Rate,
				CAST((a.RecdQty*a.QRate) As Decimal(18,2)) As Amount")))
            ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select::JOIN_INNER)
            ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId and a.ItemId=c.BrandId', array(), $select::JOIN_LEFT)
            ->join(array('e' => 'MMS_TransferRegister'), 'e.TVRegisterId=a.TransferRegisterId ', array(), $select::JOIN_INNER)
            ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId')
            ->where("e.TVRegisterId= $tid");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //TRANSFER RESOURCE

        $select = $sql->select();
        $select->from(array("a" => "MMS_TransferTrans"))
            ->columns(array(new Expression("d.TransId DecTransId,a.ResourceId,a.ItemId, a.UnitId,b.IPDTransId,
				c.IPDProjTransId,f.RequestTransId,e.RDecisionNo DecisionNo,g.RequestNo,
				CAST(b.Qty As Decimal(18,3)) As TransferQty,CAST(c.RecdQty As Decimal(18,3)) As ReceiptQty")))
            ->join(array('b' => 'MMS_IPDTrans'), 'a.TransferTransId=b.TransferTransId ', array(), $select::JOIN_INNER)
            ->join(array('c' => 'MMS_IPDProjTrans'), 'b.IPDTransId=c.IPDTransId ', array(), $select::JOIN_INNER)
            ->join(array('d' => 'VM_ReqDecQtyTrans'), 'c.DecTransId=d.TransId And C.DecisionId=D.DecisionId', array(), $select::JOIN_INNER)
            ->join(array('e' => 'VM_RequestDecision'), 'd.DecisionId=e.DecisionId', array(), $select::JOIN_INNER)
            ->join(array('f' => 'VM_RequestTrans'), 'd.ReqTransId=f.RequestTransId', array(), $select::JOIN_INNER)
            ->join(array('g' => 'VM_RequestRegister'), 'f.RequestId=g.RequestId', array(), $select::JOIN_INNER)
            ->where("a.TransferRegisterId=$tid and b.Qty > 0");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //TRANSFER REQUEST
        $select = $sql->select();
        $select->from(array("a" => "MMS_IPDAnalTrans"))
            ->columns(array(new expression("d.TransId As DecTransId,d.RCATransId As DecATransId, a.ResourceId,a.ItemId,
				a.UnitId,e.RequestAHTransId,e.AnalysisId, e.ReqTransId As RequestTransId,b.IPDProjTransId,
				f.ParentText PLevel,f.WbsName,CAST((d.TransferQty-d.TranAdjQty) As Decimal(18,3)) As BalQty,
				CAST(a.Qty As Decimal(18,3)) As ReceiptQty")))
            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $select::JOIN_INNER)
            ->join(array('c' => 'MMS_IPDTrans'), 'b.IPDTransId=c.IPDTransId', array(), $select::JOIN_INNER)
            ->join(array('d' => 'VM_ReqDecQtyAnalTrans'), 'a.DecATransId=d.RCATransId And a.DecTransId=d.TransId', array(), $select::JOIN_INNER)
            ->join(array('e' => 'VM_RequestAnalTrans'), 'd.ReqAHTransId=e.RequestAHTransId', array(), $select::JOIN_INNER)
            ->join(array('f' => 'Proj_WbsMaster'), 'a.AnalysisId=f.WBSId', array(), $select::JOIN_INNER)
            ->join(array('g' => 'MMS_TransferAnalTrans'), 'a.TVAnalTransId=g.TransferAnalTransId', array(), $select::JOIN_INNER)
            ->join(array('h' => 'MMS_TransferTrans'), 'g.TransferTransId=h.TransferTransId', array(), $select::JOIN_INNER)
            ->where("h.TransferRegisterId=$tid");
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql ->select();
        $select->from(array("a" => "MMS_TransferWareHouseTrans"))
            ->columns(array(new Expression("c.CostCentreid,b.Transid as WareHouseId,
				d.WareHouseName,b.Description,e.ResourceId,e.ItemId,CAST(a.Qty As Decimal(18,3)) As Qty")))
            ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
            ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
            ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.warehouseid", array(), $select::JOIN_INNER)
            ->join(array("e" => "MMS_TransferTrans"), "a.Transfertransid=e.transfertransid", array(), $select::JOIN_INNER)
            ->where(array("c.CostCentreId"=> $TcostcentreId ,"b.LastLevel"=>1, "e.transferregisterid" =>  $tid ));
        $selectStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_tvWareHouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql ->select();
        $select->from(array("a" => "MMS_TransferReceiptWareHouseWbsTrans"))
            ->columns(array(new Expression("c.CostCentreid,
				b.Transid as WareHouseId,
				a.RequestAHTransId as RequestAHTransId,
				d.WareHouseName,b.Description,e.ResourceId,e.ItemId,
				CAST(a.Qty As Decimal(18,3)) As Qty,
				CAST(a.Qty As Decimal(18,3)) As HiddenQty")))
            ->join(array("b" => "MMS_WareHouseDetails"), "a.WareHouseId=b.TransId", array(), $select::JOIN_INNER)
            ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
            ->join(array("d" => "MMS_WareHouse"), "c.WareHouseId=d.warehouseid", array(), $select::JOIN_INNER)
            ->join(array("e" => "MMS_TransferTrans"), "a.Transfertransid=e.transfertransid", array(), $select::JOIN_INNER)
            ->where(array("c.CostCentreId"=> $TcostcentreId ,"b.LastLevel"=>1, "e.transferregisterid" =>  $tid ));
        $selectStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_tvwbsWareHouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;

    }
    public function tvreceiptDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "transfer","action" => "tvreceipt-register"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $tvid = $this->params()->fromRoute('did');

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
            //over all delete
            $update = $sql->update();
            $update->table('MMS_TransferRegister');
            $update->set(array(

                'ReceiptDate' => NULL,
                'RecNarration' => '',

            ));
            $update->where(array('TvRegisterId' => $tvid));
            $updateStatement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            $del = $sql->delete();
            $del->from('MMS_TransferReceipt')
                ->where(array("TVRegisterId" => $tvid));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $update = $sql->update();
            $update->table('MMS_TransferTrans');
            $update->set(array(

                "RecdQty" => 0,
                "AdjustmentQty" => 0

            ));
            $update->where(array('TransferRegisterId' => $tvid));
            $updateStatement = $sql->getSqlStringForSqlObject($update);
            $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


            $sel = $sql->select();
            $sel->from(array("a" => "MMS_TransferTrans"))
                ->columns(array(new Expression('ResourceId,ItemId,RecdQty,(Rate * RecdQty) As Amount')))
                ->join(array("b" => "MMS_TransferRegister"), "a.TransferRegisterId=b.TVRegisterId", array("ToCostCentreId"), $sel::JOIN_INNER)
                ->where(array("a.TransferRegisterId" => $tvid));
            $statementPrev = $sql->getSqlStringForSqlObject($sel);
            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


            foreach ($pre as $preStock) {

                $stockSelect = $sql->select();
                $stockSelect->from(array("a" => "mms_stock"))
                    ->columns(array("StockId","TransferAmount","TransferGAmount"))
                    ->where(array(
                        "ResourceId" => $preStock['ResourceId'],
                        "CostCentreId" => $preStock['ToCostCentreId'],
                        "ItemId" => $preStock['ItemId']
                    ));
                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                if (count($stockselId['StockId']) > 0) {

                    $stockUpdate = $sql->update();
                    $stockUpdate->table('mms_stock');
                    $stockUpdate->set(array(
                        "TransferQty" => new Expression('TransferQty-' . $preStock['RecdQty'] . ''),
                        "ClosingStock" => new Expression('ClosingStock-' . $preStock['RecdQty'] . ''),
                        "TransferAmount" => new Expression('TransferAmount-' . $preStock['Amount'] . ''),
                        "TransferGAmount" => new Expression('TransferGAmount-' . $preStock['Amount'] . '')
                    ));
                    $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                    $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                    $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                $sel = $sql->select();
                $sel->from(array("a" => "MMS_TransferTrans"))
                    ->columns(array("TCostCentreId", "ResourceId", "ItemId"))
                    ->join(array("b" => "MMS_TransferWareHouseTrans"), "a.TransferTransId=b.TransferTransId", array("WareHouseId", "Qty"), $sel::JOIN_INNER)
                    ->where(array("a.TransferRegisterId" => $tvid));
                $statementPrev = $sql->getSqlStringForSqlObject($sel);
                $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                foreach ($pre as $preStockTrans) {

                    if (count($stockselId['StockId']) > 0) {

                        $sUpdate = $sql->update();
                        $sUpdate->table('mms_stockTrans');
                        $sUpdate->set(array(
                            "TransferQty" => new Expression('TransferQty-' . $preStockTrans['Qty'] . ''),
                        ));
                        $sUpdate->where(array("StockId" => $stockselId['StockId'], "WareHouseId" => $preStockTrans['WareHouseId']));
                        $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                        $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                }
            }
            //delete the previous row
            $select = $sql->select();
            $select->from('MMS_TransferTrans')
                ->columns(array("TransferTransId"))
                ->where(array('TransferRegisterId' => $tvid));

            $del = $sql->delete();
            $del->from('MMS_TransferWareHouseTrans')
                ->where->expression('TransferTransId IN ?', array($select));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $del1 = $sql->delete();
            $del1->from('MMS_TransferReceiptWareHouseWbsTrans')
                ->where->expression('TransferTransId IN ?', array($select));
            $statement = $sql->getSqlStringForSqlObject($del1);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


            $sel = $sql->select();
            $sel->from(array("a" => "MMS_IPDTrans"))
                ->columns(array(new Expression('IPDTransId,a.RecdQty As RecdQty')))
                ->join(array("b" => "MMS_TransferTrans"), "a.TransferTransId=b.TransferTransId", array(), $sel::JOIN_INNER)
                ->where(array("b.TransferRegisterId" => $tvid));
            $statementPrev = $sql->getSqlStringForSqlObject($sel);
            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($pre as $preIPD) {

                $update = $sql->update();
                $update->table('MMS_IPDTrans');
                $update->set(array(
                    "RecdQty" => new Expression('RecdQty-' . $preIPD['RecdQty']  . ''),
                ));
                $update->where(array('IPDTransId' => $preIPD['IPDTransId'],'Status' => 'T'));
                $updateStatement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
            }

            $sel = $sql->select();
            $sel->from(array("a" => "MMS_IPDProjTrans"))
                ->columns(array(new Expression('IPDProjTransId,a.RecdQty As RecdQty')))
                ->join(array("b" => "MMS_TransferTrans"), "a.TransferTransId=b.TransferTransId", array(), $sel::JOIN_INNER)
                ->where(array("b.TransferRegisterId" => $tvid));
            $statementPrev = $sql->getSqlStringForSqlObject($sel);
            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach($pre as $preIPDP){

                $update = $sql->update();
                $update->table('MMS_IPDProjTrans');
                $update->set(array(

                    "RecdQty" => new Expression('RecdQty-' . $preIPDP['RecdQty'] . ''),
                ));
                $update->where(array('IPDProjTransId' => $preIPDP['IPDProjTransId'], 'Status' => 'T'));
                $updateStatement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $sel = $sql->select();
            $sel->from(array("a" => "VM_ReqDecQtyAnalTrans"))
                ->columns(array(new Expression("RCATransId,TranAdjQty,ReqAHTransId")))
                ->join(array("b" => "MMS_IPDProjTrans"), "a.DecisionId=b.DecisionId and a.TransId=b.DecTransId", array(), $sel::JOIN_INNER)
                ->join(array("c" => "MMS_TransferTrans"), "b.TransferTransId=c.TransferTransId", array(), $sel::JOIN_INNER)
                ->join(array("d" => "VM_RequestAnalTrans"), "a.ReqAHTransId=d.RequestAHTransId", array("BalQty","TransferQty"), $sel::JOIN_INNER)
                ->where(array("c.TransferRegisterId" => $tvid));
            $statementPrev = $sql->getSqlStringForSqlObject($sel);
            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach($pre as $preVMDQ){

                $update = $sql->update();
                $update->table('VM_ReqDecQtyAnalTrans');
                $update->set(array(
                    "TranAdjQty" => new Expression('TranAdjQty-' . $preVMDQ['TranAdjQty']  . ''),
                ));
                $update->where(array('RCATransId' => $preVMDQ['RCATransId'] ));
                $updateStatement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $update = $sql->update();
                $update->table('VM_RequestAnalTrans');
                $update->set(array(

                    "BalQty" => new Expression('BalQty+' . $preVMDQ['BalQty'] . ''),
                    "TransferQty" => new Expression('TransferQty-' . $preVMDQ['TransferQty'] . ''),

                ));
                $update->where(array('RequestAHTransId' => $preVMDQ['RCATransId'] ));
                $updateStatement = $sql->getSqlStringForSqlObject($update);
                $dbAdapter->query($updateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $sel1 = $sql->select();
            $sel1->from(array("a" => "MMS_IPDAnalTrans"))
                ->columns(array(new Expression("IPDAHTransId")))
                ->join(array("b" => "MMS_IPDProjTrans"), "a.IPDProjTransId=b.IPDProjTransId", array(), $sel1::JOIN_INNER)
                ->join(array("c" => "MMS_TransferTrans"), "c.TransferTransId=b.TransferTransId", array(), $sel1::JOIN_INNER)
                ->where(array("c.TransferRegisterId" => $tvid, "a.Status" => 'T'));

            $del = $sql->delete();
            $del->from('MMS_IPDAnalTrans')
                ->where->expression('IPDAHTransId IN ?', array($sel1));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            $sel2 = $sql->select();
            $sel2->from(array("a" => "MMS_TransferAnalTrans"))
                ->columns(array(new Expression("TransferAnalTransId")))
                ->join(array("b" => "MMS_TransferTrans"), "a.TransferTransId=b.TransferTransId", array(), $sel2::JOIN_INNER)
                ->where(array("b.TransferRegisterId" => $tvid));

            $del = $sql->delete();
            $del->from('MMS_TransferAnalTrans')
                ->where->expression('TransferAnalTransId IN ?', array($sel2));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->redirect()->toRoute('mms/default', array('controller' => 'transfer','action' => 'tvreceipt-register'));
            return $this->_view;
        }
    }
    public function deletetransferAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())    {
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
        $tranregid = $this->params()->fromRoute('regid');

        //echo $dcid; die;

        if($this->getRequest()->isXmlHttpRequest())    {
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
                $selPrevTrans=$sql->select();
                $selPrevTrans->from(array("a"=>"MMS_IPDTrans"))
                    ->columns(array(new Expression("a.DecisionId,a.DecTransId,c.ReqTransId,c.DecisionId,a.Qty As Qty ")))
                    ->join(array("b"=>"MMS_TransferTrans"),"a.TransferTransId=b.TransferTransId",array(),$selPrevTrans::JOIN_INNER)
                    ->join(array("c"=>"VM_ReqDecQtyTrans"),"a.DecTransId=c.TransId and a.Decisionid=c.DecisionId",array(),$selPrevTrans::JOIN_INNER)
                    ->where(array("b.TransferRegisterId"=>$tranregid));
                $statementPrevTrans = $sql->getSqlStringForSqlObject($selPrevTrans);
                $prevtrans = $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($prevtrans as $arrprevtrans) {
                    $updDecTran=$sql->update();
                    $updDecTran->table('VM_ReqDecQtyTrans');
                    $updDecTran->set(array(
                        'TranAdjQty'=>new Expression('TranAdjQty-'.$arrprevtrans['Qty'].'')
                    ));
                    $updDecTran->where(array('TransId'=>$arrprevtrans['DecTransId'],'DecisionId'=>$arrprevtrans['DecisionId']));
                    $statementPrevTrans = $sql->getSqlStringForSqlObject($updDecTran);
                    $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                    $updReqTrans=$sql->update();
                    $updReqTrans->table('VM_RequestTrans');
                    $updReqTrans->set(array(
                        'TransferQty'=>new Expression('TransferQty-'.$arrprevtrans['Qty'].''),
                        'BalQty'=>new Expression('BalQty+'.$arrprevtrans['Qty'].'')
                    ));
                    $updReqTrans->where(array('RequestTransId'=>$arrprevtrans['ReqTransId']));
                    $statementPrevReqTrans = $sql->getSqlStringForSqlObject($updReqTrans);
                    $dbAdapter->query($statementPrevReqTrans, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                //Stock Update
                $selTrans=$sql->select();
                $selTrans->from("MMS_TransferTrans")
                    ->columns(array("FCostCentreId","ResourceId","ItemId","TransferQty","QAmount","Amount"))
                    ->where(array("TransferRegisterId"=>$tranregid));
                $statementTrans = $sql->getSqlStringForSqlObject($selTrans);
                $trantrans = $dbAdapter->query($statementTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($trantrans as $arrtrans)
                {
                    $updateSt = $sql -> update();
                    $updateSt->table('MMS_Stock');
                    $updateSt->set(array(
                        'TransferQty'=>new Expression('TransferQty+'. $this->bsf->isNullCheck($arrtrans['TransferQty'],'number')  .''),
                        'TransferAmount'=>new Expression('TransferAmount+'. $this->bsf->isNullCheck($arrtrans['QAmount'],'number')  .''),
                        'TransferGAmount'=>new Expression('TransferGAmount+'. $this->bsf->isNullCheck($arrtrans['Amount'],'number') .''),
                        'ClosingStock'=>new Expression('ClosingStock+'. $this->bsf->isNullCheck($arrtrans['TransferQty'],'number') .'')
                    ));
                }
                //
//                //Stock Tran Update
                $selSTrans=$sql->select();
                $selSTrans->from(array("a"=>"MMS_TransferWareHouseTrans"))
                    ->columns(array("CostCentreId","WareHouseId","Qty"))
                    ->join(array("b"=>"MMS_TransferTrans"),"a.TransferTransId=b.TransferTransId and a.CostCentreId=b.FCostCentreId",array(),$selSTrans::JOIN_INNER)
                    ->join(array("c"=>"MMS_Stock"),"b.ResourceId=c.ResourceId And b.ItemId=c.ItemId and b.FCostCentreId=c.CostCentreId",array(),$selSTrans::JOIN_INNER)
                    ->where(array("b.TransferRegisterId"=>$tranregid));
                $stranwhtrans = $sql->getSqlStringForSqlObject($selSTrans);
                $tranwhtrans = $dbAdapter->query($stranwhtrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($tranwhtrans as $awh)
                {
                    $updatewh = $sql -> update();
                    $updatewh->table('MMS_StockTrans');
                    $updatewh->set(array(
                        'TransferQty'=>new Expression('TransferQty+'.$this->bsf->isNullCheck($awh['TransferQty'],'number') .''),
                        'ClosingStock'=>new Expression('ClosingStock+'. $this->bsf->isNullCheck($awh['TransferQty'],'number') .'')
                    ));
                }
//                //



                $delIPDProj1 = $sql -> select();
                $delIPDProj1->from("MMS_TransferTrans")
                    ->columns(array("TransferTransId"))
                    ->where (array("TransferRegisterId"=>$tranregid));

                $delIPDproj = $sql -> delete();
                $delIPDproj->from('MMS_IpdProjTrans')
                    ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                $delipdprojStatement = $sql->getSqlStringForSqlObject($delIPDproj);
                $dbAdapter->query($delipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $delIPDTran = $sql -> delete();
                $delIPDTran->from('MMS_IpdTrans')
                    ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                $delipdStatement = $sql->getSqlStringForSqlObject($delIPDTran);
                $dbAdapter->query($delipdStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $delTVQual = $sql -> delete();
                $delTVQual -> from ('MMS_TransferQualTrans')
                    ->where(array("TVRegisterId"=>$tranregid));
                $delQualStatement = $sql->getSqlStringForSqlObject($delTVQual);
                $dbAdapter->query($delQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $delTVWh = $sql -> delete();
                $delTVWh->from('MMS_TransferWareHouseTrans')
                    ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                $delwhStatement = $sql->getSqlStringForSqlObject($delTVWh);
                $dbAdapter->query($delwhStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $delTVWha = $sql -> delete();
                $delTVWha->from('MMS_TransferWareHouseAnalTrans')
                    ->where->expression('TransferTransId IN ?',array($delIPDProj1));
                $delwhaStatement = $sql->getSqlStringForSqlObject($delTVWha);
                $dbAdapter->query($delwhaStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $delTrans = $sql -> delete();
                $delTrans -> from ('MMS_TransferTrans')
                    ->where(array("TransferRegisterId"=>$tranregid));
                $delTransStatement = $sql->getSqlStringForSqlObject($delTrans);
                $dbAdapter->query($delTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $updReqReg=$sql->update();
                $updReqReg->table('MMS_TransferRegister');
                $updReqReg->set(array(
                    'DeleteFlag'=> 1
                ));
                $updReqReg->where(array('TVRegisterId'=>$tranregid));
                $statementregupdate = $sql->getSqlStringForSqlObject($updReqReg);
                $dbAdapter->query($statementregupdate, $dbAdapter::QUERY_MODE_EXECUTE);


                $connection->commit();
                $this->redirect()->toRoute('mms/default', array('controller' => 'transfer','action' => 'display-register'));
            }
            catch (PDOException $e) {
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
            //Common function
            //$viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            //$this->redirect()->toRoute('mms/default', array('controller' => 'purchase','action' => 'register'));
            //return $this->_view;
        }
    }

    public function transferShortCloseAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "min","action" => "minshort-close"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);
        $tId = $this->bsf->isNullCheck($this->params()->fromRoute('tid'), 'number');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $TVId = $this->bsf->isNullCheck($postParams['TVRegisterId'], 'number');

                $select = $sql->select();
                $select->from(array("A" => "MMS_TransferTrans"))
                    ->columns(array(
                        'Code'=>new Expression("Case When A.ItemId>0 Then D.ItemCode Else C.Code End"),
                        'Resource'=>new Expression("Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End"),
                        'TransferTransId'=>new Expression("A.TransferTransId"),
                        'TransferQty'=>new Expression("A.TransferQty"),
                        'RecdQty'=>new Expression("A.RecdQty"),
                        'IssueQty'=>new Expression("A.IssueQty")
                    ))
                    ->join(array('B' => 'MMS_TransferRegister'), ' A.TransferRegisterId =B.TVRegisterId', array("TVRegisterId"), $select::JOIN_INNER)
                    ->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceID', array(), $select::JOIN_INNER)
                    ->join(array('D' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId And a.ItemId=d.BrandId', array(), $select::JOIN_LEFT)
                    ->where("A.TransferRegisterId= $TVId And B.ShortClose=0 And (A.TransferQty-A.RecdQty)>0");
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
                $postParams = $request->getPost();

                $Approve = "";
                $Role = "";
                $TVId = $this->bsf->isNullCheck($postParams['TransferNo'], 'number');
                $TVIds = $this->bsf->isNullCheck($postParams['TransferId'], 'number');

                $DCGroupIds = implode(',', $postParams['DCGroupIds']);
                if($DCGroupIds == ""){
                    $DCGroupIds=0;
                }else{
                    $DCGroupIds=$DCGroupIds;
                }
//                if ($this->bsf->isNullCheck($dcId, 'number') > 0) {
//                    $Approve = "E";
//                    $Role = "DC-Short-Close-Modify";
//                } else {
//                    $Approve = "N";
//                    $Role = "DC-Short-Close-Create";
//                }

                $select = $sql->select();
                $select->from(array("a" => "MMS_TransferRegister"))
                    ->columns(array("TVNo", "FromCostCentreId"))
                    ->where(array('TVRegisterId' => $TVId));
                $Statement = $sql->getSqlStringForSqlObject($select);
                $dcreg = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();

                $CostCentreId = $dcreg ['FromCostCentreId'];
                $TVNo = $dcreg ['TVNo'];

                $select = $sql->select();
                $select->from(array('a' => 'WF_OperationalCostCentre'))
                    ->columns(array('CompanyId'))
                    ->where(array("CostCentreId" => $CostCentreId));
                $statement = $sql->getSqlStringForSqlObject($select);
                $Comp = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId = $Comp['CompanyId'];

                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    if ($TVId > 0) {

                        if (count($DCGroupIds) > 0) {

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_TransferRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 1,
                            ));
                            $updDcreg->where(array('TVRegisterId' => $TVId));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_TransferRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 0,
                            ));
                            $updDcreg->where(array('TVRegisterId' => $TVId));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $del = $sql->delete();
                        $del->from('MMS_TransferShortCloseReg')
                            ->where(array('TransferRegisterId' => $TVId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $Insert = $sql->insert('MMS_TransferShortCloseReg');
                        $Insert->values(array(
                            "TransferRegisterId" => $TVId,
                        ));
                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $updatedc = $sql->update();
                        $updatedc->table('MMS_TransferTrans');
                        $updatedc->set(array(
                            'ShortClose' => 1,
                        ));
                        $updatedc->where(array("TransferTransId IN($DCGroupIds)"));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatedc);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    } else {

                        $updDcreg = $sql->update();
                        $updDcreg->table('MMS_TransferRegister');
                        $updDcreg->set(array(
                            'ShortClose' => 0,
                        ));
                        $updDcreg->where(array('TVRegisterId' => $TVIds));
                        $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                        $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del = $sql->delete();
                        $del->from('MMS_TransferShortCloseReg')
                            ->where(array('TransferRegisterId' => $TVIds));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $updatedc = $sql->update();
                        $updatedc->table('MMS_TransferTrans');
                        $updatedc->set(array(
                            'ShortClose' => 0,
                        ));
                        $updatedc->where(array('TransferRegisterId' => $TVIds));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatedc);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //update edit mod minshortclose

                        if (count($DCGroupIds) > 0) {

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_TransferRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 1,
                            ));
                            $updDcreg->where(array('TVRegisterId' => $TVIds));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        } else {

                            $updDcreg = $sql->update();
                            $updDcreg->table('MMS_TransferRegister');
                            $updDcreg->set(array(
                                'ShortClose' => 0,
                            ));
                            $updDcreg->where(array('TVRegisterId' => $TVIds));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $del = $sql->delete();
                        $del->from('MMS_TransferShortCloseReg')
                            ->where(array('TransferRegisterId' => $TVIds));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $Insert = $sql->insert('MMS_TransferShortCloseReg');
                        $Insert->values(array(
                            "TransferRegisterId" => $TVIds,
                        ));
                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        $updatedc = $sql->update();
                        $updatedc->table('MMS_TransferTrans');
                        $updatedc->set(array(
                            'ShortClose' => 1,
                        ));
                        $updatedc->where(array("TransferTransId IN($DCGroupIds)"));
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
                $this->redirect()->toRoute('mms/default', array('controller' => 'transfer', 'action' => 'transfershortclose-register'));
                return $this->_view;

            }else {
                if (isset($tId) && $tId != '') {

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_TransferRegister'))
                        ->columns(array(new Expression("a.TVRegisterId as TVId,a.TVNo as TVNo")))
                        ->where(array("a.TVRegisterId=$tId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $minid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->selNo = $minid['TVNo'];
                    $this->_view->seldcId = $minid['TVId'];


                    $select = $sql->select();
                    $select->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array(new Expression("Case When A.ItemId>0 Then D.ItemCode Else C.Code End Code,
                    Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End Resource,
                    A.TransferTransId,B.TVRegisterId,CAST(A.TransferQty As Decimal(18,6)) TransferQty,a.ShortClose,
                    CAST(A.RecdQty As Decimal(18,6)) RecdQty,CAST(A.IssueQty As Decimal(18,6))IssueQty,
                    a.ShortClose As Include")))
                        ->join(array('b' => 'MMS_TransferRegister'), 'a.TransferRegisterId=b.TVRegisterId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceID', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId And a.ItemId=d.BrandId', array(), $select::JOIN_LEFT)
                        ->where("a.TransferRegisterId= $tId ");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
                $select = $sql->select();
                $select->from(array('a' => 'MMS_TransferRegister'))
                    ->columns(array(new Expression("distinct(a.TVRegisterId) as TVRegisterId,a.TVNo as TVNo")))
                    ->join(array("b" => "MMS_TransferTrans"), "A.TVRegisterId = B.TransferRegisterId ", array(), $select::JOIN_INNER)
                    ->where(array("(B.TransferQty-B.RecdQty) > 0 And A.ShortClose=0 And A.Approve='Y' Order By A.TVRegisterId"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_TVNo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //Common function
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
                $this->_view->tId = $tId;
                return $this->_view;
            }
        }
    }
    public function transfershortcloseRegisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "min","action" => "minshort-close"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                if ($postParam['mode'] == 'first') {

                    $regSelect = $sql->select();
                    $regSelect->from(array("a" => "MMS_transferShortCloseReg"))
                        ->columns(array(new Expression("a.TransferRegisterId,b.TVNo As TVNo,
                        Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("b" => "MMS_transferRegister"), "a.transferRegisterId=b.tvRegisterId", array(), $regSelect::JOIN_LEFT)
                        ->Order("TVDate Desc")
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
    public function transfershortcloseDeleteAction(){
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
        // $dcId = $this->params()->fromRoute('rid');
        $TVRegisterId = $this->bsf->isNullCheck($this->params()->fromPost('TVRegisterId'), 'number');
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();
                    $TVRegisterId = $this->bsf->isNullCheck($this->params()->fromPost('TransferRegisterId'), 'number');
                    // over all delete
                    $updDcreg = $sql->update();
                    $updDcreg->table('MMS_TransferRegister');
                    $updDcreg->set(array(
                        'ShortClose' => 0,
                    ));
                    $updDcreg->where(array('TVRegisterId' => $TVRegisterId));
                    $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                    $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $del = $sql->delete();
                    $del->from('MMS_transferShortCloseReg')
                        ->where(array('TransferRegisterId' => $TVRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($del);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_TransferTrans"))
                        ->columns(array("TransferTransId"))
                        ->join(array("b"=>"MMS_TransferRegister"), "a.TransferRegisterId=b.TVRegisterId", array(), $select::JOIN_INNER)
                        ->where(array("a.TransferRegisterId" => $TVRegisterId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $prev = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($prev as $arrprev) {

                        $updatedc = $sql->update();
                        $updatedc->table('MMS_TransferTrans');
                        $updatedc->set(array(
                            'ShortClose' => 0,
                        ));
                        $updatedc->where(array('TransferTransId' => $arrprev['TransferTransId']));
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
    public function transdispatchReportAction(){
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
            $this->redirect()->toRoute("transfer/display-register", array("controller" => "transfer","action" => "display-register"));
        }

        $dir = 'public/transdispatch/header/'. $subscriberId;
        $filePath = $dir.'/v1_template.phtml';

        $dirfooter = 'public/transdispatch/footer/'. $subscriberId;
        $filePath1 = $dirfooter.'/v1_template.phtml';

        $TvRegId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($TvRegId == 0)

            $this->redirect()->toRoute("transfer/display-register", array("controller" => "transfer","action" => "display-register"));

        if (!file_exists($filePath)) {
            $filePath = 'public/transdispatch/header/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/transdispatch/footer/footertemplate.phtml';
        }

        $template = file_get_contents($filePath);
        $this->_view->template = $template;

        $footertemplate = file_get_contents($filePath1);
        $this->_view->footertemplate = $footertemplate;
        //Template
        $selReg = $sql -> select();
        $selReg->from(array("a"=>'MMS_TransferRegister'))
            ->columns(array("TVRegisterId"=>new Expression("a.TVRegisterId"),"TransferNo"=>new Expression("a.TVNo"),
                "TransferDate"=>new Expression("Convert(Varchar(10),a.TVDate,103)"),"FromCompany"=>new Expression("c.CompanyName"),
                "FromCostCentre"=>new Expression("b.CostCentreName"),"ToCompany"=>new Expression("e.CompanyName"),
                "ToCostCentre"=>new Expression("d.CostCentreName"),"Approve"=>new Expression("Case When a.Approve='Y' Then 'Yes' When a.Approve='P' then 'Partial' Else 'No' End")  ))
            ->join(array('b'=>'WF_OperationalCostCentre'),"a.FromCostCentreId=b.CostCentreId",array(),$selReg::JOIN_INNER)
            ->join(array('c'=>'WF_CompanyMaster'),"a.FromCompanyId=c.CompanyId",array(),$selReg::JOIN_INNER)
            ->join(array('d'=>'WF_OperationalCostCentre'),"a.ToCostCentreId=d.CostCentreId",array(),$selReg::JOIN_INNER)
            ->join(array('e'=>'WF_CompanyMaster'),"a.ToCompanyId=e.CompanyId",array(),$selReg::JOIN_INNER)
            ->join(array("f"=>"WF_CostCentre"), "a.ToCostCentreId=f.CostCentreId", array("Address"), $selReg::JOIN_LEFT)
            ->join(array("g"=>"WF_CityMaster"), "f.CityId=g.CityId", array("CityName"), $selReg::JOIN_LEFT)
            ->join(array("h"=>"WF_StateMaster"), "f.StateId=h.StateId", array("StateName"), $selReg::JOIN_LEFT)
            ->join(array("i"=>"WF_CountryMaster"), "f.CountryId=i.CountryId", array("CountryName"), $selReg::JOIN_LEFT)
            ->where(array('a.DeleteFlag'=>0,'a.ReceiptDate'=>null, 'a.TVRegisterId'=> $TvRegId));
        $regStatement = $sql->getSqlStringForSqlObject($selReg);
        $this->_view->reqregister = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        //Grid

        $resourceSelect = $sql->select();
        $resourceSelect->from(array("a"=>"MMS_TransferTrans"))
            ->columns(array(new Expression("(ROW_NUMBER() OVER(PARTITION by A.TransferRegisterId Order by A.TransferRegisterId asc)) as SNo,a.TransferTransId,a.TransferRegisterId,a.ResourceId,a.ItemId,CAST(a.TransferQty as Decimal(18,5)) As TransferQty,
						Case When a.ItemId>0 Then f.ItemCode Else d.Code End As Code,Case When a.ItemId>0 Then f.BrandName Else d.ResourceName End As ResourceName,
						a.UnitId,e.UnitName,a.QRate As QRate,a.Rate,a.Amount As BaseAmount,a.QAmount As Amount,(Select Count(TransferTransId) From MMS_TransferQualTrans Where TransferTransId = a.TransferTransId) as QCount")))
            ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array(), $resourceSelect::JOIN_INNER)
            ->join(array("e"=>"Proj_UOM"), "a.UnitId=e.UnitId", array("UnitName"), $resourceSelect::JOIN_LEFT)
            ->join(array("f"=>"MMS_Brand"),"a.ItemId=f.BrandId And a.ResourceId=f.ResourceId",array(),$resourceSelect::JOIN_LEFT)
            ->where(array('a.TransferRegisterId'=>$TvRegId));
        $resourceStatement = $sql->getSqlStringForSqlObject($resourceSelect);
        $this->_view->register = $dbAdapter->query($resourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("c" => "MMS_TransferQualTrans"))
            ->columns(array(
                'TransferTransId'=>new Expression('c.TransferTransId'),
                'TVRegisterId'=>new Expression('c.TVRegisterId'),
                'Expression'=>new Expression('c.Expression'),
                'ExpPer'=>new Expression('c.ExpPer'),
                'Sign'=>new Expression('c.Sign'),
                'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,3))')))
            ->join(array("b" => "Proj_QualifierMaster"), "c.QualifierId=b.QualifierId", array('QualifierName'), $select::JOIN_INNER)

            ->where(array('c.TVRegisterId'=>$TvRegId));
        $regStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->register1 = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }


    public function warehouseTransferAction(){
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

        //Wtno generation
        $vNo = CommonHelper::getVoucherNo(310,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;
        $this->_view->genType = $vNo["genType"];

        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                if ($postParam['Type'] == 'warehouse') {
                    $projectId = $this->bsf->isNullCheck($postParam['projectId'], 'number');

                    // getting the  warehouse details
                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouseDetails"))
                        ->columns(array(new Expression("a.TransId As data,
                        a.Description+' '+ '('+ b.WareHouseName+')' As value ")))
                        ->join(array("b" => "MMS_WareHouse"), "a.WareHouseId=b.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->where(array("c.CostCentreId=  $projectId and a.LastLevel=1"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $arr_warehouse = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->setTerminal(true);
                    $response->setContent(json_encode($arr_warehouse));
                    return $response;

                } else if($postParam['Type'] == 'Grid') {

                    //getting grid resourceList
                    $CostCentreId = $this->bsf->isNullCheck($postParam['CostCentreId'], 'number');
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId  As ResourceId,isnull(d.BrandId,0) ItemId,Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,0 As Include ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array("ResourceGroupName"), $select::JOIN_LEFT)
                        ->join(array('c' => 'Proj_ProjectResource'), 'c.ResourceId=a.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'WF_OperationalCostCentre'), 'c.ProjectId=e.ProjectId', array(), $select::JOIN_INNER)
                        // ->where(" a.TypeId IN (2,3) and e.CostCentreId =" . $CostCentreId);
                        ->where(" a.TypeId IN (2,3)");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $result = array('resources' => $requestResources);

                    $this->_view->setTerminal(true);
                    $response->setContent(json_encode($result));
                    return $response;

                }
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }
            //project
            $projSelect = $sql->select();
            $projSelect->from('WF_OperationalCostCentre')
                ->columns(array("CostCentreId", "CostCentreName"));
            $projStatement = $sql->getSqlStringForSqlObject($projSelect);
            $this->_view->arr_project = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function warehousetransferEntryAction(){
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
        $response = $this->getResponse();
        $wtId = $this->bsf->isNullCheck($this->params()->fromRoute('id'), 'number');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postData = $request->getPost();

                if($postData['mode'] == 'closingstock'){
                    $resourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                    $itemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                    $projectId = $this->bsf->isNullCheck($this->params()->fromPost('costCentreId'), 'number');
                    $fwarehouseId = $this->bsf->isNullCheck($this->params()->fromPost('fwarehouseId'), 'number');

                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouseDetails"))
                        ->columns(array(new Expression("CAST(b.closingstock As Decimal(18,3)) ClosingStock,
                            c.ResourceId as ResourceId,c.ItemId as ItemId")))
                        ->join(array("b" => "MMS_stocktrans"), "b.WareHouseId=a.transId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_stock"), "c.stockId=b.stockId", array(), $select::JOIN_INNER)
                        ->where(array("c.costcentreid = $projectId and a.TransId=  $fwarehouseId
                                and c.ResourceId in($resourceId) and c.ItemId in($itemId)"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $resp = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $response->setContent(json_encode(array('arr_cs' => $resp)));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here
                $postData = $request->getPost();
//                echo"<pre>";
//                 print_r($postData);
//                  echo"</pre>";
//                 die;
//                return;

                $projectId =  $this->bsf->isNullCheck($postData['project'],'number');
                $fWarehouseId =  $this->bsf->isNullCheck($postData['fWarehouse'],'number');
                $tWarehouseId =  $this->bsf->isNullCheck($postData['tWarehouse'],'number');
                $wtno =  $this->bsf->isNullCheck($postData['wtno'],'number');
                $gridtype =  $this->bsf->isNullCheck($postData['gridtype'],'number');
                $date = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date'], 'string')));
                $resourceTransIds = implode(',', $postData['resourceTransIds']);
                $itemTransIds = implode(',', $postData['itemTransIds']);

                $this->_view->gridtype=$gridtype;
                $this->_view->projectId=$projectId;
                $this->_view->fWarehouseId=$fWarehouseId;
                $this->_view->tWarehouseId=$tWarehouseId;
                $this->_view->wtno=$wtno;
                $this->_view->date=$date;


                $select = $sql->select();
                $select->from('WF_OperationalCostCentre')
                    ->columns(array("CostCentreName"))
                    ->where("CostCentreId = $projectId");
                $selectStatement = $sql->getSqlStringForSqlObject($select);
                $projectName = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->costCentreName = $projectName['CostCentreName'];


                $select = $sql ->select();
                $select->from(array("a" => "MMS_WareHouseDetails"))
                    ->columns(array(new Expression("a.Description + ' ' + '(' + c.WareHouseName + ')' As WareHouseName")))
                    ->join(array("c" => "MMS_WareHouse"), "a.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                    ->where(array("a.TransId=  $fWarehouseId"));
                $selectStatement = $sql->getSqlStringForSqlObject($select);
                $result = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->fWarehouseName = $result['WareHouseName'];


                $select = $sql ->select();
                $select->from(array("a" => "MMS_WareHouseDetails"))
                    ->columns(array(new Expression("a.Description + ' ' + '(' + c.WareHouseName + ')' As WareHouseName")))
                    ->join(array("c" => "MMS_WareHouse"), "a.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                    ->where(array("a.TransId=  $tWarehouseId"));
                $selectStatement = $sql->getSqlStringForSqlObject($select);
                $results = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $this->_view->tWarehouseName = $results['WareHouseName'];


                // get resource lists
                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array(new Expression("a.ResourceId,isnull(d.BrandId,0) As ItemId,
                        Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                        Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As ResourceName,
                        c.UnitName,c.UnitId")))
                    ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                    ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                    ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_LEFT)
                    ->join(array('f' => 'WF_OperationalCostCentre'),'e.ProjectId=f.ProjectId',array(),$select::JOIN_INNER)
                    ->where("f.CostCentreId=".$projectId." and (a.ResourceId IN ($resourceTransIds) and isnull(d.BrandId,0) IN ($itemTransIds))");
                $statement = $sql->getSqlStringForSqlObject( $select );
                $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //closing stock
                $select = $sql ->select();
                $select->from(array("a" => "MMS_WareHouseDetails"))
                    ->columns(array(new Expression("CAST(b.closingstock As Decimal(18,3)) ClosingStock,
                    c.ResourceId as ResourceId,c.ItemId as ItemId")))
                    ->join(array("b" => "MMS_stocktrans"), "b.WareHouseId=a.transId", array(), $select::JOIN_INNER)
                    ->join(array("c" => "MMS_stock"), "c.stockId=b.stockId", array(), $select::JOIN_INNER)
                    ->where(array("c.costcentreid = $projectId and a.TransId=  $fWarehouseId
                    and c.ResourceId in($resourceTransIds) and c.ItemId in($itemTransIds)"));
                $selectStatement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_closingstock = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                //autocomplete
                $select = $sql->select();
                $select->from(array('a' => 'Proj_Resource'))
                    ->columns(array(new Expression("a.ResourceId as data,1 as AutoFlag,isnull(d.BrandId,0) As ItemId,
                    Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                    Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                    Case When isnull(d.BrandId,0)>0 Then f.UnitName else c.UnitName End As UnitName,
                    Case When isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
                    'Project' As RFrom")))
                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                    ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                    ->join(array('e' => 'Proj_ProjectResource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER)
                    ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                    ->join(array('g' => 'WF_OperationalCostCentre'),'e.ProjectId=g.ProjectId',array(),$select::JOIN_INNER)
                    ->where(" g.CostCentreId=".$projectId." and (a.ResourceId NOT IN ($resourceTransIds) Or
                    isnull(d.BrandId,0) NOT IN ($itemTransIds))");

                $selRa = $sql -> select();
                $selRa -> from (array('a' => 'Proj_Resource'))
                    ->columns(array(new Expression("a.ResourceId as data,1 as AutoFlag,isnull(d.BrandId,0) As ItemId,
                    Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                    Case When isnull(d.BrandId,0)>0 Then d.BrandName Else a.ResourceName End As value,
                    Case When isnull(d.BrandId,0)>0 Then f.UnitName else c.UnitName End As UnitName,
                    Case When isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,
                    'Library' As RFrom")))
                    ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                    ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                    ->join(array('d' => 'MMS_Brand'),'a.ResourceId=d.ResourceId',array(),$select::JOIN_LEFT )
                    ->join(array('f' => 'Proj_UOM'),'d.UnitId=f.UnitId',array(),$select::JOIN_LEFT)
                    ->where("a.ResourceId NOT IN (Select A.ResourceId From Proj_ProjectResource A
                            Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$projectId)
                           and (a.ResourceId NOT IN ($resourceTransIds) Or isnull(d.BrandId,0) NOT IN ($itemTransIds))");
                $select -> combine($selRa,"Union All");
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            } else {

                //edit mode
                $postData = $request->getPost();
                if (isset($wtId) && $wtId != '') {

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_WHTRegister'))
                        ->columns(array(new Expression("a.CostCentreId,CONVERT(varchar(10),a.WTDate,105) As WTDate,
                        a.WTNo as WTNo,a.FWHId,a.TWHId,b.CostCentreName,
                         Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve,
                         a.GridType")))
                        ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $select::JOIN_INNER)
                        ->where(array("a.WTRegisterId" => $wtId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->wtregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    $costCentreName = $this->_view->wtregister['CostCentreName'];
                    $projectId = $this->_view->wtregister['CostCentreId'];
                    $date = $this->_view->wtregister['WTDate'];
                    $wtno = $this->_view->wtregister['WTNo'];
                    $fWarehouseId = $this->_view->wtregister['FWHId'];
                    $tWarehouseId = $this->_view->wtregister['TWHId'];
                    $gridtype = $this->_view->wtregister['GridType'];
                    $approve = $this->_view->wtregister['Approve'];

                    $this->_view->costCentreName = $costCentreName;
                    $this->_view->projectId = $projectId;
                    $this->_view->date = $date;
                    $this->_view->wtno = $wtno;
                    $this->_view->fWarehouseId = $fWarehouseId;
                    $this->_view->tWarehouseId = $tWarehouseId;
                    $this->_view->gridtype = $gridtype;
                    $this->_view->approve = $approve;
                    $this->_view->wtId = $wtId;


                    //getting from warehouse-edit
                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouseDetails"))
                        ->columns(array(new Expression("a.Description + ' ' + '(' + c.WareHouseName + ')' As WareHouseName")))
                        ->join(array("c" => "MMS_WareHouse"), "a.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WHTRegister"), "a.TransId=d.FWHId", array(), $select::JOIN_INNER)
                        ->where(array("d.WTRegisterId=$wtId"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $result2 = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->fWarehouseName = $result2['WareHouseName'];

                    //getting to warehouse-edit
                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouseDetails"))
                        ->columns(array(new Expression("a.Description + ' ' + '(' + c.WareHouseName + ')' As WareHouseName")))
                        ->join(array("c" => "MMS_WareHouse"), "a.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WHTRegister"), "a.TransId=d.TWHId", array(), $select::JOIN_INNER)
                        ->where(array("d.WTRegisterId=$wtId"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $result3 = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->tWarehouseName = $result3['WareHouseName'];


                    // get resource lists
                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_WHTTrans'))
                        ->columns(array(new Expression("b.ResourceId,isnull(e.BrandId,0) As ItemId,
	                    Case When isnull(e.BrandId,0)>0 Then e.ItemCode Else b.Code End As Code,
	                    Case When isnull(e.BrandId,0)>0 Then e.BrandName Else b.ResourceName End As ResourceName,
	                    c.ResourceGroupName,c.ResourceGroupId,d.UnitName,d.UnitId,
	                    CAST(a.TransferQty As Decimal(18,3)) As Qty,CAST(a.TransferQty As Decimal(18,3)) As HiddenQty,
	                    RFrom = Case When a.ResourceId IN (Select A.ResourceId From Proj_ProjectResource A
	                    Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=4)
	                    Then 'Project' Else 'Library' End ")))
                        ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select:: JOIN_INNER)
                        ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_INNER)
                        ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'MMS_Brand'), 'b.ResourceId=e.ResourceId and e.BrandId = a.ItemId', array(), $select::JOIN_LEFT)
                        ->join(array('f' => 'MMS_WHTRegister'), 'a.WHTRegisterId=f.WTRegisterId', array(), $select::JOIN_INNER)
                        ->where("f.CostCentreId=" . $projectId . " and a.WHTRegisterId=$wtId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //closing stock
                    $select = $sql ->select();
                    $select->from(array("a" => "MMS_WareHouseDetails"))
                        ->columns(array(new Expression("CAST(b.closingstock As Decimal(18,3)) ClosingStock,
                                c.ResourceId as ResourceId,c.ItemId as ItemId")))
                        ->join(array("b" => "MMS_stocktrans"), "b.WareHouseId=a.transId", array(), $select::JOIN_INNER)
                        ->join(array("c" => "MMS_stock"), "c.stockId=b.stockId", array(), $select::JOIN_INNER)
                        ->join(array("d" => "MMS_WHTTrans"), "d.resourceId=c.resourceId and d.ItemId=c.ItemId", array(), $select::JOIN_INNER)
                        ->join(array("e" => "MMS_WHTRegister"), "d.WHTRegisterId=e.WTRegisterId", array(), $select::JOIN_INNER)
                        ->where(array("c.costcentreid = $projectId and a.TransId=  $fWarehouseId and d.WHTRegisterId=$wtId"));
                    $selectStatement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_closingstock = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //autocomplete-edit
                    $select = $sql->select();
                    $select->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId as data,0 as AutoFlag,isnull(d.BrandId,0) As ItemId,
                           Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End As Code,
                           Case When isnull(d.BrandId,0)>0 Then d.ItemCode +' - ' + d.BrandName Else a.Code + ' - ' +  a.ResourceName End As value,
                           Case When isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End As UnitName,
                           Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End As UnitId,'Project' As RFrom ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $select:: JOIN_LEFT)
                        ->join(array('c' => 'Proj_UOM'), 'a.UnitId=c.UnitId', array(), $select:: JOIN_LEFT)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId', array(), $select::JOIN_LEFT)
                        ->join(array('e' => 'Proj_ProjectResource'), 'a.ResourceId=e.ResourceId', array(), $select::JOIN_INNER)
                        ->join(array('f' => 'Proj_UOM'), 'd.UnitId=f.UnitId', array(), $select::JOIN_LEFT)
                        ->join(array('g' => 'WF_OperationalCostCentre'),'e.ProjectId=g.ProjectId',array(),$select::JOIN_INNER)
                        ->where(" g.CostCentreId=" . $projectId . " ");
                    $select -> where("a.ResourceId NOT IN (Select ResourceId From MMS_WHTTrans Where WHTRegisterId=$wtId)
                            Or isnull(d.BrandId,0) NOT IN (Select ItemId From MMS_WHTTrans Where WHTRegisterId=$wtId)  ");

                    $selRa = $sql->select();
                    $selRa->from(array('a' => 'Proj_Resource'))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) ItemId,
                           Case when isnull(c.BrandId,0)>0 then c.ItemCode Else a.Code End As Code,
                           Case When isnull(c.BrandId,0)>0 Then c.ItemCode + ' - ' + c.BrandName Else a.Code + ' - ' + a.ResourceName End As value,
                           Case When isnull(c.BrandId,0)>0 Then e.UnitName Else d.UnitName End As UnitName,
                           Case When isnull(c.BrandId,0)>0 Then e.UnitId Else d.UnitId End As UnitId,'Library' As RFrom ")))
                        ->join(array('b' => 'Proj_ResourceGroup'), 'a.ResourceGroupId=b.ResourceGroupId', array(), $selRa::JOIN_LEFT)
                        ->join(array('c' => 'MMS_Brand'), 'a.ResourceId=c.ResourceId', array(), $selRa::JOIN_LEFT)
                        ->join(array('d' => 'Proj_UOM'), 'a.UnitId=d.UnitId', array(), $selRa::JOIN_LEFT)
                        ->join(array('e' => 'Proj_UOM'), 'c.UnitId=e.UnitId', array(), $selRa::JOIN_LEFT)
                        ->where("a.ResourceId NOT IN (Select A.ResourceId From Proj_ProjectResource A
                            Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=" . $projectId . ")
                            and (a.ResourceId Not IN (select ResourceId From MMS_WHTTrans Where WHTRegisterId=$wtId) or
                            isnull(c.BrandId,0) NOT IN (select ItemId From MMS_WHTTrans Where WHTRegisterId=$wtId) )");
                    $select->combine($selRa, 'Union All');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

    public function warehouseTransferSaveAction(){
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
        $vNo = CommonHelper::getVoucherNo(310,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;

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
                $postData = $request->getPost();

//                echo"<pre>";
//                 print_r($postData);
//                  echo"</pre>";
//                 die;
//                return;
                $wtId = $this->bsf->isNullCheck($postData['wtId'], 'number');
                $this->_view->wtId=$wtId;

                if ($this->bsf->isNullCheck($wtId, 'number') > 0) {
                    $Approve="E";
                    $Role="Warehouse-Transfer-Modify";
                }else{
                    $Approve="N";
                    $Role="Warehouse-Transfer-Create";
                }

                $costCentreId = $this->bsf->isNullCheck($postData['projectId'], 'number');
                $fWareHouseId = $this->bsf->isNullCheck($postData['fWarehouseId'], 'number');
                $tWareHouseId = $this->bsf->isNullCheck($postData['tWarehouseId'], 'number');
                $wtNo = $this->bsf->isNullCheck($postData['wtno'], 'number');
                $gridtype = $this->bsf->isNullCheck($postData['gridtype'], 'number');
                $wtDate = date('Y-m-d', strtotime($this->bsf->isNullCheck($postData['date'], 'string')));

                //begin trans try block example starts
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {

                    if($wtId > 0){

                        $sel = $sql->select();
                        $sel->from(array("a" => "MMS_WHTTrans"))
                            ->columns(array("ResourceId","ItemId","TransferQty"))
                            ->join(array("b" => "MMS_WHTRegister"), "a.WHTRegisterId=b.WTRegisterId", array("CostCentreId","FWHId","TWHId"), $sel::JOIN_INNER)
                            ->join(array("c" => "MMS_Stock"), "a.resourceId=c.resourceId and a.ItemId=c.ItemId and b.costcentreId=c.costcentreId", array("StockId"), $sel::JOIN_INNER)
                            ->join(array("d" => "MMS_StockTrans"), "d.StockId=c.StockId and d.warehouseId=b.FWHId", array(), $sel::JOIN_INNER)
                            ->join(array("e" => "MMS_StockTrans"), "e.StockId=c.StockId and e.warehouseId=b.TWHId", array(), $sel::JOIN_INNER)
                            ->where(array("a.WHTRegisterId" => $wtId));
                        $statementPrev = $sql->getSqlStringForSqlObject($sel);
                        $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($pre as $arrprevtrans) {

                            $sUpdate = $sql->update();
                            $sUpdate->table('mms_stockTrans');
                            $sUpdate->set(array(
                                "WHTQty" => new Expression('WHTQty+' . $arrprevtrans['TransferQty'] . ''),
                            ));
                            $sUpdate->where(array("StockId" => $arrprevtrans['StockId'],
                                "WareHouseId" => $arrprevtrans['FWHId']));
                            $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $Update = $sql->update();
                            $Update->table('mms_stockTrans');
                            $Update->set(array(
                                "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                            ));
                            $Update->where(array("StockId" => $arrprevtrans['StockId'],
                                "WareHouseId" => $arrprevtrans['FWHId']));
                            $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                            $sUpdate = $sql->update();
                            $sUpdate->table('mms_stockTrans');
                            $sUpdate->set(array(
                                "WHTQty" => new Expression('WHTQty-' . $arrprevtrans['TransferQty'] . ''),
                            ));
                            $sUpdate->where(array("StockId" => $arrprevtrans['StockId'],
                                "WareHouseId" => $arrprevtrans['TWHId']));
                            $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $Update = $sql->update();
                            $Update->table('mms_stockTrans');
                            $Update->set(array(
                                "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                            ));
                            $Update->where(array("StockId" => $arrprevtrans['StockId'],
                                "WareHouseId" => $arrprevtrans['TWHId']));
                            $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                            $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        }


                        $del = $sql->delete();
                        $del->from('MMS_WHTTrans')
                            ->where(array("WHTRegisterId" => $wtId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);


                        //WHTRegister update
                        $registerUpdate = $sql->update();
                        $registerUpdate->table('MMS_WHTRegister');
                        $registerUpdate->set(array(
                            'WTDate' => $wtDate,
                            "CostCentreId" => $costCentreId,
                            "WTNo" => $wtNo, "FWHId" => $fWareHouseId,
                            "TWHId" => $tWareHouseId, "GridType" => $gridtype
                        ));
                        $registerUpdate->where(array('WTRegisterId' => $wtId));
                        $registerUpdateStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                        $dbAdapter->query($registerUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $wtregId = $postData['rowid'];
                        for ($i = 1; $i < $wtregId; $i++) {
                            $qty = $this->bsf->isNullCheck($postData['qty_' . $i],'number' . '');
                            if($qty > 0 || $qty != '') {

                                //WTTrans
                                $registerInsert = $sql->insert('MMS_WHTTrans');
                                $registerInsert->values(array("WHTRegisterId" => $wtId,
                                    "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number' . ''),
                                    "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number' . ''),
                                    "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number' . ''),
                                    "TransferQty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number' . ''),
                                ));
                                $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                                $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $wtTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                // stock details adding
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stock"))
                                    ->columns(array("StockId"))
                                    ->where(array("CostCentreId" => $costCentreId,
                                        "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number' . ''),
                                        "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number' . '')
                                    ));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                // from warehouse -stocktrans
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                    ->columns(array("StockId"))
                                    ->where(array("WareHouseId" => $fWareHouseId, "StockId" => $stockselId['StockId']));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if (count($stId['StockId']) > 0) {
                                    $sUpdate = $sql->update();
                                    $sUpdate->table('mms_stockTrans');
                                    $sUpdate->set(array(
                                        "WHTQty" => new Expression('WHTQty-' . $qty . ''),
                                    ));
                                    $sUpdate->where(array("StockId" => $stId['StockId'],
                                        "WareHouseId" => $fWareHouseId));
                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {
                                    if ($qty > 0) {
                                        $stock1 = $sql->insert('mms_stockTrans');
                                        $stock1->values(array(
                                            "WareHouseId" => $fWareHouseId,
                                            "StockId" => $stockselId['StockId'],
                                            "WHTQty" => $qty,
                                        ));
                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                                $Update = $sql->update();
                                $Update->table('mms_stockTrans');
                                $Update->set(array(
                                    "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                                ));
                                $Update->where(array("StockId" => $stockselId['StockId'],
                                    "WareHouseId" => $fWareHouseId));
                                $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                //end of from warehouse

                                //To warehouse-stocktrans
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                    ->columns(array("StockId"))
                                    ->where(array("WareHouseId" => $tWareHouseId, "StockId" => $stockselId['StockId']));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if (count($stId['StockId']) > 0) {
                                    $sUpdate = $sql->update();
                                    $sUpdate->table('mms_stockTrans');
                                    $sUpdate->set(array(
                                        "WHTQty" => new Expression('WHTQty+' . $qty . ''),
                                    ));
                                    $sUpdate->where(array("StockId" => $stId['StockId'],
                                        "WareHouseId" => $tWareHouseId));
                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {
                                    if ($qty > 0) {
                                        $stock1 = $sql->insert('mms_stockTrans');
                                        $stock1->values(array(
                                            "WareHouseId" => $tWareHouseId,
                                            "StockId" => $stockselId['StockId'],
                                            "WHTQty" => $qty,
                                        ));
                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                                $Update = $sql->update();
                                $Update->table('mms_stockTrans');
                                $Update->set(array(
                                    "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                                ));
                                $Update->where(array("StockId" => $stockselId['StockId'],
                                    "WareHouseId" => $tWareHouseId));
                                $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                //end of to warehouse -stocktrans
                            }
                        } //END OF i LOOP
                    } else{

                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(310, date('Y/m/d', strtotime($wtDate)), 0, 0, $dbAdapter, "I");
                            $voucherNo = $voucher['voucherNo'];
                        } else {
                            $voucherNo = $postData['voucherNo'];
                        }
                        $wtNo = $voucherNo;

                        //WTRegister
                        $registerInsert = $sql->insert('MMS_WHTRegister');
                        $registerInsert->values(array("WTDate" => $wtDate, "CostCentreId" => $costCentreId,
                            "WTNo" => $wtNo, "FWHId" => $fWareHouseId,
                            "TWHId" => $tWareHouseId, "GridType" => $gridtype
                        ));
                        $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                        $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $wtRegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $wtregId = $postData['rowid'];
                        for ($i = 1; $i < $wtregId; $i++) {
                            $qty = $this->bsf->isNullCheck($postData['qty_' . $i],'number' . '');
                            if($qty > 0 || $qty != ''){
                                //WTTrans
                                $registerInsert = $sql->insert('MMS_WHTTrans');
                                $registerInsert->values(array("WHTRegisterId" => $wtRegisterId,
                                    "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number' . ''),
                                    "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number' . ''),
                                    "UnitId" => $this->bsf->isNullCheck($postData['unitid_' . $i], 'number' . ''),
                                    "TransferQty" => $this->bsf->isNullCheck($postData['qty_' . $i], 'number' . ''),
                                ));
                                $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                                $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $wtTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                // stock details adding
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stock"))
                                    ->columns(array("StockId"))
                                    ->where(array("CostCentreId" => $costCentreId,
                                        "ResourceId" => $this->bsf->isNullCheck($postData['resourceid_' . $i], 'number' . ''),
                                        "ItemId" => $this->bsf->isNullCheck($postData['itemid_' . $i], 'number' . '')
                                    ));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                // from warehouse -stocktrans
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                    ->columns(array("StockId"))
                                    ->where(array("WareHouseId" => $fWareHouseId, "StockId" => $stockselId['StockId']));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if (count($stId['StockId']) > 0) {
                                    $sUpdate = $sql->update();
                                    $sUpdate->table('mms_stockTrans');
                                    $sUpdate->set(array(
                                        "WHTQty" => new Expression('WHTQty-' . $qty . ''),
                                    ));
                                    $sUpdate->where(array("StockId" => $stId['StockId'],
                                        "WareHouseId" => $fWareHouseId));
                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {
                                    if ($qty > 0) {
                                        $stock1 = $sql->insert('mms_stockTrans');
                                        $stock1->values(array(
                                            "WareHouseId" => $fWareHouseId,
                                            "StockId" => $stockselId['StockId'],
                                            "WHTQty" => $qty,
                                        ));
                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }

                                $Update = $sql->update();
                                $Update->table('mms_stockTrans');
                                $Update->set(array(
                                    "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                                ));
                                $Update->where(array("StockId" => $stockselId['StockId'],
                                    "WareHouseId" => $fWareHouseId));
                                $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                //end of from warehouse

                                //To warehouse-stocktrans
                                $stockSelect = $sql->select();
                                $stockSelect->from(array("a" => "mms_stockTrans"))
                                    ->columns(array("StockId"))
                                    ->where(array("WareHouseId" => $tWareHouseId, "StockId" => $stockselId['StockId']));
                                $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                                $stId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                                if (count($stId['StockId']) > 0) {
                                    $sUpdate = $sql->update();
                                    $sUpdate->table('mms_stockTrans');
                                    $sUpdate->set(array(
                                        "WHTQty" => new Expression('WHTQty+' . $qty . ''),
                                    ));
                                    $sUpdate->where(array("StockId" => $stId['StockId'],
                                        "WareHouseId" => $tWareHouseId));
                                    $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                                    $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                } else {
                                    if ($qty > 0) {
                                        $stock1 = $sql->insert('mms_stockTrans');
                                        $stock1->values(array(
                                            "WareHouseId" => $tWareHouseId,
                                            "StockId" => $stockselId['StockId'],
                                            "WHTQty" => $qty,
                                        ));
                                        $stock1Statement = $sql->getSqlStringForSqlObject($stock1);
                                        $dbAdapter->query($stock1Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                                $Update = $sql->update();
                                $Update->table('mms_stockTrans');
                                $Update->set(array(
                                    "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                                ));
                                $Update->where(array("StockId" => $stockselId['StockId'],
                                    "WareHouseId" => $tWareHouseId));
                                $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                //end of to warehouse -stocktrans
                            }
                        } // end of for i loop
                    } // end of add mode
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Warehouse-Transfer',$wtId,0,0,'mms',$wtNo,$this->auth->getIdentity()->UserId,0,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'Transfer', 'action' => 'warehouse-transfer-register'));

                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                //begin trans try block example ends
            }
        }
    }

    public function warehouseTransferRegisterAction(){
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
        $response = $this->getResponse();


        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $resp = array();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                if ($postParam['mode'] == 'register') {

                    $regSelect = $sql->select();
                    $regSelect->from(array("a" => "MMS_WHTRegister"))
                        ->columns(array(new Expression("a.WTRegisterId,Convert(Varchar(10),a.WTDate,103) As WTDate,
                           a.WTNo as WTNo,c.CostCentreName AS Project,
                           d.Description + ' ' + '(' + e.WareHouseName + ')' AS FromWareHouse,
                           f.Description + ' ' + '(' + g.WareHouseName + ')' AS ToWareHouse,
                           Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("c" => "WF_operationalcostcentre"), "a.CostCentreId=c.CostCentreId", array(), $regSelect::JOIN_LEFT)
                        ->join(array("d" => "mms_warehouseDetails"), "a.FWHId=d.transId", array(), $regSelect::JOIN_LEFT)
                        ->join(array("e" => "mms_warehouse"), "e.WareHouseId=d.WareHouseId", array(), $regSelect::JOIN_LEFT)
                        ->join(array("f" => "mms_warehouseDetails"), "a.TWHId=f.transId", array(), $regSelect::JOIN_LEFT)
                        ->join(array("g" => "mms_warehouse"), "g.WareHouseId=f.WareHouseId", array(), $regSelect::JOIN_LEFT)
                        ->order("a.CreatedDate Desc");
                    $regStatement = $sql->getSqlStringForSqlObject($regSelect);
                    $resp['data'] = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else {

        }
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }

    public function warehouseTransferDetailedAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "Transfer","action" => "warehouse-transfer"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $wtId = $this->params()->fromRoute('id');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            $resp = array();
            if ($request->isPost()) {

            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            $select = $sql->select();
            $select->from(array('a' => 'MMS_WHTRegister'))
                ->columns(array(new Expression("a.CostCentreId,CONVERT(varchar(10),a.WTDate,105) As WTDate,
				a.WTNo as WTNo,a.FWHId,a.TWHId,b.CostCentreName,
				 Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve,
				 a.GridType,c.Description + ' ' + '(' + s.WareHouseName + ')' AS FWarehouseName,
                 d.Description + ' ' + '(' + t.WareHouseName + ')' AS TWarehouseName")))
                ->join(array('b' => 'WF_OperationalCostCentre'), 'a.CostCentreId=b.CostCentreId', array(), $select::JOIN_INNER)
                ->join(array("c" => "MMS_WareHouseDetails"), "a.FWHId=c.TransId", array(), $select::JOIN_INNER)
                ->join(array("s" => "MMS_WareHouse"), "s.WareHouseId=c.WareHouseId", array(), $select::JOIN_INNER)
                ->join(array("d" => "MMS_WareHouseDetails"), "a.TWHId=d.TransId", array(), $select::JOIN_INNER)
                ->join(array("t" => "MMS_WareHouse"), "t.WareHouseId=d.WareHouseId", array(), $select::JOIN_INNER)
                ->where(array("a.WTRegisterId" => $wtId));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->wtregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $costCentreName = $this->_view->wtregister['CostCentreName'];
            $projectId = $this->_view->wtregister['CostCentreId'];
            $date = $this->_view->wtregister['WTDate'];
            $wtno = $this->_view->wtregister['WTNo'];
            $fWarehouseId = $this->_view->wtregister['FWHId'];
            $tWarehouseId = $this->_view->wtregister['TWHId'];
            $gridtype = $this->_view->wtregister['GridType'];
            $approve = $this->_view->wtregister['Approve'];
            $fWarehouseName = $this->_view->wtregister['FWarehouseName'];
            $tWarehouseName = $this->_view->wtregister['TWarehouseName'];

            $this->_view->costCentreName = $costCentreName;
            $this->_view->projectId = $projectId;
            $this->_view->date = $date;
            $this->_view->wtno = $wtno;
            $this->_view->fWarehouseId = $fWarehouseId;
            $this->_view->tWarehouseId = $tWarehouseId;
            $this->_view->gridtype = $gridtype;
            $this->_view->approve = $approve;
            $this->_view->wtId = $wtId;
            $this->_view->fWarehouseName = $fWarehouseName;
            $this->_view->tWarehouseName = $tWarehouseName;

            // get resource lists
            $select = $sql->select();
            $select->from(array('a' => 'MMS_WHTTrans'))
                ->columns(array(new Expression("b.ResourceId,isnull(e.BrandId,0) As ItemId,
				Case When isnull(e.BrandId,0)>0 Then e.ItemCode Else b.Code End As Code,
				Case When isnull(e.BrandId,0)>0 Then e.BrandName Else b.ResourceName End As ResourceName,
				c.ResourceGroupName,c.ResourceGroupId,d.UnitName,d.UnitId,
				CAST(a.TransferQty As Decimal(18,3)) As Qty,CAST(a.TransferQty As Decimal(18,3)) As HiddenQty,
				RFrom = Case When a.ResourceId IN (Select A.ResourceId From Proj_ProjectResource A
				Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=4)
				Then 'Project' Else 'Library' End,CONVERT(varchar(10),f.CreatedDate,105) As Date")))
                ->join(array('b' => 'Proj_Resource'), 'a.ResourceId=b.ResourceId', array(), $select:: JOIN_INNER)
                ->join(array('c' => 'Proj_ResourceGroup'), 'b.ResourceGroupId=c.ResourceGroupId', array(), $select:: JOIN_INNER)
                ->join(array('d' => 'Proj_UOM'), 'b.UnitId=d.UnitId', array(), $select::JOIN_INNER)
                ->join(array('e' => 'MMS_Brand'), 'b.ResourceId=e.ResourceId and e.BrandId = a.ItemId', array(), $select::JOIN_LEFT)
                ->join(array('f' => 'MMS_WHTRegister'), 'a.WHTRegisterId=f.WTRegisterId', array(), $select::JOIN_INNER)
                ->where("f.CostCentreId=" . $projectId . " and a.WHTRegisterId=$wtId");
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }

    public function warehouseTransferDeleteAction(){
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
        $wtId = $this->params()->fromRoute('id');
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
            //overall delete

            $sel = $sql->select();
            $sel->from(array("a" => "MMS_WHTTrans"))
                ->columns(array("ResourceId","ItemId","TransferQty"))
                ->join(array("b" => "MMS_WHTRegister"), "a.WHTRegisterId=b.WTRegisterId", array("CostCentreId","FWHId","TWHId"), $sel::JOIN_INNER)
                ->join(array("c" => "MMS_Stock"), "a.resourceId=c.resourceId and a.ItemId=c.ItemId and b.costcentreId=c.costcentreId", array("StockId"), $sel::JOIN_INNER)
                ->join(array("d" => "MMS_StockTrans"), "d.StockId=c.StockId and d.warehouseId=b.FWHId", array(), $sel::JOIN_INNER)
                ->join(array("e" => "MMS_StockTrans"), "e.StockId=c.StockId and e.warehouseId=b.TWHId", array(), $sel::JOIN_INNER)
                ->where(array("a.WHTRegisterId" => $wtId));
            $statementPrev = $sql->getSqlStringForSqlObject($sel);
            $pre = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            foreach ($pre as $arrprevtrans) {

                $sUpdate = $sql->update();
                $sUpdate->table('mms_stockTrans');
                $sUpdate->set(array(
                    "WHTQty" => new Expression('WHTQty+' . $arrprevtrans['TransferQty'] . ''),
                ));
                $sUpdate->where(array("StockId" => $arrprevtrans['StockId'],
                    "WareHouseId" => $arrprevtrans['FWHId']));
                $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $Update = $sql->update();
                $Update->table('mms_stockTrans');
                $Update->set(array(
                    "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                ));
                $Update->where(array("StockId" => $arrprevtrans['StockId'],
                    "WareHouseId" => $arrprevtrans['FWHId']));
                $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                $sUpdate = $sql->update();
                $sUpdate->table('mms_stockTrans');
                $sUpdate->set(array(
                    "WHTQty" => new Expression('WHTQty-' . $arrprevtrans['TransferQty'] . ''),
                ));
                $sUpdate->where(array("StockId" => $arrprevtrans['StockId'],
                    "WareHouseId" => $arrprevtrans['TWHId']));
                $sUpdateStatement = $sql->getSqlStringForSqlObject($sUpdate);
                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $Update = $sql->update();
                $Update->table('mms_stockTrans');
                $Update->set(array(
                    "ClosingStock" => new Expression('WHTQty+OpeningStock+DCQty+BillQty+IssueQty+TransferQty+ReturnQty'),
                ));
                $Update->where(array("StockId" => $arrprevtrans['StockId'],
                    "WareHouseId" => $arrprevtrans['TWHId']));
                $sUpdateStatement = $sql->getSqlStringForSqlObject($Update);
                $dbAdapter->query($sUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

            }

            $del = $sql->delete();
            $del->from('MMS_WHTTrans')
                ->where(array("WHTRegisterId" => $wtId));
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //update the deleted row
            $del= $sql ->update();
            $del ->table('MMS_WHTRegister')
                ->set(array('DeleteFlag'=>1))
                ->where("WTRegisterId = $wtId");
            $statement = $sql->getSqlStringForSqlObject($del);
            $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->redirect()->toRoute('mms/default', array('controller' => 'transfer','action' => 'warehouse-transfer-register'));
            return $this->_view;
        }
    }
}