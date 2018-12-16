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

use Zend\Db\Adapter\Adapter;

use Zend\Authentication\Adapter\DbTable as AuthAdapter;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Application\View\Helper\CommonHelper;
use Application\View\Helper\Qualifier;

class PurchaseController extends AbstractActionController
{
    public function __construct()	{
        $this->auth = new AuthenticationService();
        $this->bsf = new \BuildsuperfastClass();
        if ($this->auth->hasIdentity()) {
            $this->identity = $this->auth->getIdentity();
        }
        $this->_view = new ViewModel();
    }

    public function indexAction()	{
        /*if(!$this->auth->hasIdentity()) {
         $this->redirectd()->toRoute('application/default', array('controller' => 'index','action' => 'index'));
     }*/

        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');

        return $this->_view;
    }
    public function feedsEntryAction(){
        if(!$this->auth->hasIdentity()){
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }

        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $preVendor=array();
        if($this->getRequest()->isXmlHttpRequest()){
            if ($request->isPost()){
                //Ajax post code here
                $postParam = $request->getPost();
                $resp = array();
                if($postParam['mode'] == 'thirdStep'){
                    $res = json_decode($postParam['res'], true);
                    $resId = array_keys($res);
                    $decId = array_unique(json_decode($postParam['dec'], true));
                    $costId = array_unique(json_decode($postParam['cost'], true));

                    $select1 = $sql->select();
                    $select1->from(array("a"=>"VM_RequestTrans"))
                        ->columns(array('ResourceId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))")),
                            array("Sel1"=>new Expression("1-1")),array("Sel2"=>new Expression("1-1")),array("Code", "ResourceName", "UnitId"),
                            array("UnitName"),array("Sel3"=>new Expression("1-1")),array("Sel4"=>new Expression("1-1")),array())
                        ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("Sel1"=>new Expression("1-1")), $select1::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("Sel2"=>new Expression("1-1")), $select1::JOIN_LEFT)
                        ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("Code", "ResourceName", "UnitId"), $select1::JOIN_INNER)
                        ->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select1::JOIN_LEFT)
                        ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array("Sel3"=>new Expression("1-1")), $select1::JOIN_INNER)
                        ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("Sel4"=>new Expression("1-1")), $select1::JOIN_INNER)
                        ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select1::JOIN_INNER)
                        ->where(array('f1.Approve'=>'Y','f1.DecisionId'=>$decId,
                            'b.CostCentreId'=>$costId,
                            'a.ResourceId'=>$resId));
                    $select1->group(new Expression("a.ResourceId,d.Code,d.ResourceName,d.UnitId,e.UnitName"));
                    $feedStatement = $sql->getSqlStringForSqlObject($select1);
                    $feedResult = $dbAdapter->query($feedStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach($feedResult as $data){
                        //SubTrans
                        $select1 = $sql->select();
                        $select1->from(array("a"=>"VM_RequestTrans"))
                            ->columns(array('ResourceId', 'RequestTransId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))")),
                                array("CostcentreId"),array("CostCentreName"),array("RequestId"),array("DecisionId","RDecisionNo"))
                            ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array(), $select1::JOIN_INNER)
                            ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
                            ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("DecisionId", "RDecisionNo"), $select1::JOIN_INNER)
                            ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select1::JOIN_INNER)
                            ->where(array('f1.Approve'=>'Y','f1.DecisionId'=>array_keys($res[$data['ResourceId']]),
                                'a.ResourceId'=>$data['ResourceId']));
                        $select1->group(new Expression("a.ResourceId,a.RequestTransId,f.RequestId,f1.DecisionId,f1.RDecisionNo"));

                        $feedSubStatement = $sql->getSqlStringForSqlObject($select1);
                        $feedSubResult = $dbAdapter->query($feedSubStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $data['dec'] = array();
                        foreach($feedSubResult as $data1){
                            $data1['cost'] = array();
                            $costCentre = $res[$data['ResourceId']][$data1['DecisionId']];
                            foreach($costCentre as $cid){
                                $costSelect = $sql->select();
                                $costSelect->from(array("a"=>"VM_RequestTrans"))
                                    ->columns(array("Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))")),
                                        array("CostcentreId"),array("CostCentreName"),array(),array(),array() )
                                    ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("CostcentreId"), $costSelect::JOIN_INNER)
                                    ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $costSelect::JOIN_INNER)
                                    ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array(), $costSelect::JOIN_INNER)
                                    ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array(), $costSelect::JOIN_INNER)
                                    ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $costSelect::JOIN_INNER)
                                    ->where(array('f1.Approve'=>'Y','f1.DecisionId'=>$data1['DecisionId'],
                                        'b.CostCentreId'=>$cid,
                                        'a.ResourceId'=>array($data['ResourceId'])));
                                $costSelect->group(array("b.CostCentreId", "c.CostCentreName"));
                                $costStatement = $sql->getSqlStringForSqlObject($costSelect);
                                $costResult = $dbAdapter->query($costStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                foreach($costResult as $cdata){
                                    $select1 = $sql->select();
                                    $select1->from(array("a"=>"VM_RequestAnalTrans"))
                                        ->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))")),
                                            array("RequestTransId"),array("CostCentreId"),array("WbsName"),array("RequestId"),array("RDecisionNo"),array( ) )
                                        ->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $select1::JOIN_INNER)
                                        ->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $select1::JOIN_INNER)
                                        ->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WBSName"), $select1::JOIN_INNER)
                                        ->join(array("f"=>"VM_ReqDecTrans"), "c.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
                                        ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("RDecisionNo"), $select1::JOIN_INNER)
                                        ->join(array("g"=>"VM_ReqDecQtyAnalTrans"), "f.DecisionId=g.DecisionId And b.RequestTransId=g.ReqTransId And a.RequestAHTransId=g.ReqAHTransId", array( ), $select1::JOIN_INNER)
                                        //->where(array('f.DecisionId'=>1))
                                        //->where(array('c.RequestType'=>2));
                                        ->where(array('f1.Approve'=>'Y','b.ResourceId' => array($data1['ResourceId']),
                                            'f.DecisionId'=>$data1['DecisionId'],
                                            'c.CostCentreId'=>$cdata['CostcentreId'],
                                            'b.RequestTransId'=>$data1['RequestTransId'] ));
                                    $select1->group(new Expression("a.RequestAHTransId,a.AnalysisId,a.ReqTransId,a.ResourceId,a.ItemId,b.RequestTransId,c.CostCentreId,d.WbsName,f.RequestId,f1.RDecisionNo"));
                                    //po4
                                    $feedSub1Statement = $sql->getSqlStringForSqlObject($select1);
                                    $cdata['wbsname'] = $dbAdapter->query($feedSub1Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    array_push($data1['cost'], $cdata);
                                }
                            }
                            array_push($data['dec'], $data1);
                        }
                        array_push($resp, $data);
                    }
                } else if($postParam["mode"] == 'vendorSelect'){
                    /*vendor select change*/
                    $resp= array();
                    $select = $sql->select();
                    $select->from(array('a' => 'Vendor_Branch'))
                        ->columns(array('BranchId', 'BranchName'))
                        ->where->like('a.VendorId', $postParam['cid']);
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp['branch']   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //TransportMode
                    $select1 = $sql->select();
                    $select1->from(array('a' => 'Vendor_TransportMaster'))
                        ->columns(array('TransportId', 'TransportName'), array("VendorId"))
                        ->join(array('b'=>'Vendor_Transport'), "a.TransportId=b.TransportId", array("VendorId"), $select1::JOIN_LEFT)
                        ->where->like('b.VendorId', $postParam['cid']);

                    $statement1 = $sql->getSqlStringForSqlObject($select1);
                    $resp['transport']   = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Service
                    $select2 = $sql->select();
                    $select2->from(array('a' => 'Vendor_ServiceMaster'))
                        ->columns(array('ServiceId', 'ServiceName'), array("VendorId"))
                        ->join(array('b'=>'Vendor_ServiceTrans'), "a.ServiceId=b.ServiceId", array("VendorId"), $select2::JOIN_LEFT)
                        ->where->like('b.VendorId', $postParam['cid']);

                    $statement2 = $sql->getSqlStringForSqlObject($select2);
                    $resp['service']   = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Distributor
                    $select3 = $sql->select();
                    $select3->from(array('a' => 'Vendor_Master'))
                        ->columns(array('VendorId', 'VendorName'))
                        ->join(array('b'=>'Vendor_SupplierDet'), "a.VendorId=b.SupplierVendorId", array("SupplierVendorId"), $select3::JOIN_INNER)
                        ->where->like('b.VendorId', $postParam['cid']);

                    $statement3 = $sql->getSqlStringForSqlObject($select3);
                    $resp['distributor']   = $dbAdapter->query($statement3, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if($postParam["mode"] == 'branchSelect'){
                    /*branch select change*/
                    $resp = array();
                    $select = $sql->select();
                    $select->from(array('a' => 'Vendor_BranchContactDetail'))
                        ->columns(array('BranchTransId', 'ContactPerson'))
                        ->where->like('a.BranchId', $postParam['cid']);

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp['branch'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array('a' => 'Vendor_Branch'))
                        ->columns(array('Phone'))
                        ->where->like('a.BranchId', $postParam['cid']);

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp['contact'] = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if($postParam["mode"] == 'fillTerms'){
                    $select1 = $sql->select();
                    $select1->from(array("a"=>"WF_TermsMaster"))
                        ->columns(array('TermsId', 'SlNo',"Terms"=>'Title',"ValueFromNet"=>new Expression("1-1"),"Per"=>new Expression("''"),"Value"=>new Expression("''"),"Period"=>new Expression("''"),"Date"=>new Expression("''"),"Str"=>new Expression("''"),"IsPer"=>'Per',"IsValue"=>'Value',"IsPeriod"=>'Period',"IsDate"=>'TDate',"IsString"=>'TString',"IsDef"=>'SysDefault',"IGross"=>'IncludeGross' ))
                        //->where->like('a.TermType', 'S');
                        ->where(array('a.TermsId'=>$postParam['rid']));

                    $mostStatement = $sql->getSqlStringForSqlObject($select1);
                    $resp['result'] = $dbAdapter->query($mostStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                } else if($postParam["mode"] == 'filterStep'){
                    $select = $sql->select();
                    if($postParam["iproj"] != "0"){
                        $select->from(array("a"=>"VM_RequestDecision"))
                            ->columns(array(new Expression("
									STUFF((SELECT ',' + CONVERT(VARCHAR(12), m.ResourceId) FROM VM_ReqDecQtyTrans l
									inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
									inner join [VM_ReqDecTrans] n on m.RequestId=n.RequestId
									inner join [VM_RequestRegister] n2 on n.RequestId = n2.RequestId
									WHERE l.DecisionId = a.DecisionId and n.DecisionId =a.DecisionId and n2.CostCentreId='".$postParam["iproj"]."' order by m.ResourceId FOR XML PATH('')), 1, 1, '') AS ResourceId,
									STUFF((SELECT ',' + CONVERT(VARCHAR(12), n.CostCentreId) FROM VM_ReqDecQtyTrans l
									inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
									inner join [VM_ReqDecTrans] n1 on M.RequestId=n1.RequestId
									inner join [VM_RequestRegister] n on n1.RequestId = n.RequestId
									WHERE l.DecisionId = a.DecisionId and n1.DecisionId =a.DecisionId and n.CostCentreId='".$postParam["iproj"]."' order by m.ResourceId FOR XML PATH('')), 1, 1, '') AS CostCentreId,
									a.DecisionId, a.RDecisionNo, CONVERT(VARCHAR(10), a.DecDate, 105) as DecDate, a.RequestType ")));
                        $select->where (array('a.Approve'=>'Y'));
                    } else {
                        $select->from(array("a"=>"VM_RequestDecision"))
                            ->columns(array(new Expression("
									STUFF((SELECT ',' + CONVERT(VARCHAR(12), m.ResourceId) FROM VM_ReqDecQtyTrans l
									inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
									inner join [VM_ReqDecTrans] n on m.RequestId=n.RequestId
									inner join [VM_RequestRegister] n2 on n.RequestId = n2.RequestId
									WHERE l.DecisionId = a.DecisionId and n.DecisionId =a.DecisionId FOR XML PATH('')), 1, 1, '') AS ResourceId,
									STUFF((SELECT ',' + CONVERT(VARCHAR(12), n.CostCentreId) FROM VM_ReqDecQtyTrans l
									inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
									inner join [VM_ReqDecTrans] n1 on M.RequestId=n1.RequestId
									inner join [VM_RequestRegister] n on n1.RequestId = n.RequestId
									WHERE l.DecisionId = a.DecisionId and n1.DecisionId =a.DecisionId FOR XML PATH('')), 1, 1, '') AS CostCentreId,
									a.DecisionId, a.RDecisionNo, CONVERT(VARCHAR(10), a.DecDate, 105) as DecDate, a.RequestType ")));
                        $select->where(array('a.Approve'=>'Y'));
                    }

                    if($postParam['iType']!="0"){
                        $select->where(array("a.RequestType"=>$postParam['iType']));
                    }
                    //$select->where("DecDate <= '".$contractPoint."' AND TValue >= '".$contractPoint."'");
                    if($postParam['bDay']!=0){
                        $select->where("a.DecDate = '".date('d-M-Y')."'");
                    }
                    else if($postParam['bWeek']!=0){
                        $fromDate=date('d-M-Y',strtotime(Date('d-M-Y'). ' -1 week'));
                        $select->where("a.DecDate > '".$fromDate."' AND a.DecDate <= '".date('d-M-Y')."'");
                    }
                    else if($postParam['bMonth']!=0){
                        $fromDate=date('d-M-Y',strtotime(Date('d-M-Y'). ' -1 month'));
                        $select->where("a.DecDate > '".$fromDate."' AND a.DecDate <= '".date('d-M-Y')."'");
                    }
                    else{
                        $select->where("a.DecDate <= '".date('d-M-Y', strtotime($postParam["dDate"]))."'");
                    }

                    $filterSelect = $sql->select();
                    $filterSelect->from(array("g"=>$select))
                        ->columns(array("*"));
                    //$filterSelect->where->isNotNull('CostCentreId');
                    $filterSelect->where('CostCentreId IS NOT NULL');

                    $feedStatement = $sql->getSqlStringForSqlObject($filterSelect);
                    $feedResult = $dbAdapter->query($feedStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $resp['result'] = array_chunk($feedResult, 3);
                } else if($postParam["mode"] == 'selectCompany'){
                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("data"=>'PurchaseTypeId',"value" =>'PurchaseTypeName'))
                        ->join(array('b'=>'MMS_PurchaseTypeTrans'), "a.PurchaseTypeId=b.PurchaseTypeId", array(), $select::JOIN_INNER)
                        ->join(array('c'=>'WF_OperationalCostCentre'), "b.CompanyId=c.CompanyId", array(), $select::JOIN_INNER)
                        ->where(array('c.CostCentreId'=>$postParam['ccid'],'b.Sel'=>1))
                        ->order("B.SortOrder Asc");
                    $companyStatement = $sql->getSqlStringForSqlObject($select);
                    $resp['result'] = $dbAdapter->query($companyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                else if($postParam["mode"] == 'selectAccount'){
                    $selectSub=$sql->select();
                    $selectSub->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array())
                        ->join(array('b'=>'FA_AccountType'),"a.AccountTypeId=b.TypeId",array(),$selectSub::JOIN_INNER)
                        ->join(array('c'=>'FA_AccountMaster'),"b.TypeId=c.TypeId",array("data"=>'AccountId',"value"=>'AccountName'),$selectSub::JOIN_INNER)
                        ->where(array('a.Sel'=>1,'a.PurchaseTypeId IN (7,8)','a.PurchaseTypeId'=>$postParam['ptypeId']));

                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array())
                        ->join(array('b'=>'FA_AccountMaster'),"a.AccountId=b.AccountId",array("data"=>'AccountId',"value"=>'AccountName'),$select::JOIN_INNER)
                        ->where(array('a.Sel=1','a.PurchaseTypeId'=>$postParam['ptypeId']));
                    $select->combine($selectSub,'Union ALL');
                    $accountStatement = $sql->getSqlStringForSqlObject($select);
                    $resp['resultAcc'] = $dbAdapter->query($accountStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        } else if($request->isPost()){
            $postParam = $request->getPost();
            $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"");
            $voucher='';
            if($vNo['genType']){
                $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"I");
                $voucher = $vNo['voucherNo'];
            } else {
                $voucher = $postParam['voucherNo'];
            }
            $json = json_decode($postParam['hidjson'], true);
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $registerInsert = $sql->insert('MMS_PORegister');
                $registerInsert->values(array("PODate"=>date('Y-m-d', strtotime($postParam["purchase_date"])),
                    "PONo"=>$voucher,"VendorId"=>$postParam["vendorId"],"BranchId"=>$postParam["Branch"],
                    "CurrencyId"=>$postParam["currency"], "BranchTransId"=>$postParam["Branchcontactperson"],
                    "Address1"=>$postParam["addressline1"],"Address2"=>$postParam["addressline2"],"Address3"=>$postParam["addressline3"],
                    "City"=>$postParam["city"],"Pincode"=>$postParam["pincode"],"PurchaseTypeId"=>$postParam["purchase_type"],"PurchaseAccount"=>$postParam["account_type"],"Narration"=>$postParam["narration"]));
                $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                $PORegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                $POLogisticInsert = $sql->insert('MMS_POLogistic');
                $POLogisticInsert->values(array("PORegisterId"=>$PORegisterId,"ProviderId"=>$postParam["serviceprovider"],
                    "VehicleId"=>$postParam["transportmode"]));
                $registerStatement1 = $sql->getSqlStringForSqlObject($POLogisticInsert);
                $registerResults1 = $dbAdapter->query($registerStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach(array_filter(explode(",", $postParam['gridServiceId'])) as $sid){
                    $POLogisticserviceInsert = $sql->insert('MMS_POLogisticService');
                    $POLogisticserviceInsert->values(array("PORegisterId"=>$PORegisterId,"ServiceId"=>$sid));
                    $registerStatement2 = $sql->getSqlStringForSqlObject($POLogisticserviceInsert);
                    $dbAdapter->query($registerStatement2, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach(array_filter(explode(",", $postParam['gridLogisticstypeId'])) as $Logisticsid){
                    $POLogisticTypeInsert = $sql->insert('MMS_POLogisticType');
                    $POLogisticTypeInsert->values(array("PORegisterId"=>$PORegisterId,"LogisticTypeId"=>$Logisticsid));
                    $registerStatement3 = $sql->getSqlStringForSqlObject($POLogisticTypeInsert);
                    $dbAdapter->query($registerStatement3, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach(array_filter(explode(",", $postParam['distributorListId'])) as $Distributorid){
                    $DistributorInsert = $sql->insert('MMS_PODistributorTrans');
                    $DistributorInsert->values(array("PORegisterId"=>$PORegisterId,"VendorId"=>$Distributorid));
                    $registerStatement4 = $sql->getSqlStringForSqlObject($DistributorInsert);
                    $dbAdapter->query($registerStatement4, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                //PORegisterId,TermsId,ValueFromNet,Per,Value,Period,TDate,TString
                foreach(array_filter(explode(",", $postParam['gridtermListId'])) as $termsid){
                    $TermListInsert = $sql->insert('MMS_POPaymentTerms');
                    $TermListInsert->values(array("PORegisterId"=>$PORegisterId,"TermsId"=>$termsid,
                        "ValueFromNet"=>$postParam['ValueFromNet_'.$termsid],"Per"=>$postParam['Per_'.$termsid],
                        "Value"=>$postParam['Value_'.$termsid],"Period"=>$postParam['Period_'.$termsid],
                        "TDate"=>$postParam['Date_'.$termsid],"TString"=>$postParam['Str_'.$termsid]));
                    $registerStatement5 = $sql->getSqlStringForSqlObject($TermListInsert);
                    $dbAdapter->query($registerStatement5, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                /*Resource Id*/
                foreach($json as $resKey=>$resValue){
                    $resId=$resKey;
                    //POTrans
                    //PORegisterId,ResourceId,UnitId,POQty
                    $requestInsert = $sql->insert('MMS_POTrans');
                    $requestInsert->values(array("PORegisterId"=>$PORegisterId, "UnitId"=>$postParam['unitId_'.$resId],
                        "ResourceId"=>$resId, "POQty"=>$postParam['transQuantity_'.$resId]));
                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                    $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $POTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                    /*Request decision*/
                    foreach($resValue as $decKey=>$decValue){
                        $decId=$decKey;
                        //update RequestTrans
                        $select = $sql->update();
                        $select->table('VM_RequestTrans');
                        $select->set(array(
                            'IndentQty' => new Expression('IndentQty +'.$postParam['decisionQty_'.$resId.'_'.$decId]),
                            'BalQty' => new Expression('BalQty -'.$postParam['decisionQty_'.$resId.'_'.$decId])
                        ));
                        $select->where(array('RequestTransId'=>$postParam['reqTransId_'.$resId.'_'.$decId]));
                        $requestHiddenupdateStatement = $sql->getSqlStringForSqlObject($select);
                        $dbAdapter->query($requestHiddenupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        //IPDTrans
                        //POTransId,ResourceId,ReqTransId,Qty,DecisionId
                        $requestInsert = $sql->insert('MMS_IPDTrans');
                        $requestInsert->values(array("POTransId"=>$POTransId, "ReqTransId"=>$postParam['reqTransId_'.$resId.'_'.$decId], "DecisionId"=>$decId,
                            "ResourceId"=>$resId, "Qty"=>$postParam['decisionQty_'.$resId.'_'.$decId]));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        //cc
                        //MMS_POProjTrans
                        //POTransId,CostcentreId,ResourceId,UnitId,POQty
                        $requestInsert = $sql->insert('MMS_POProjTrans');
                        $requestInsert->values(array("POTransId"=>$POTransId, "UnitId"=>$postParam['unitId_'.$resId],
                            "ResourceId"=>$resId, "POQty"=>$postParam['decisionQty_'.$resId.'_'.$decId]));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $POProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        foreach($decValue as $costKey=>$costValue){
                            $costId = $costKey;
                            //MMS_IPDProjTrans
                            //IPDTRansId,POProjTransId,CostcentreId,ResourceId,UnitId,ReqTransId,Qty
                            $requestInsert = $sql->insert('MMS_IPDProjTrans');
                            $requestInsert->values(array("POProjTransId"=>$POProjTransId, "ReqTransId"=>$postParam['reqTransId_'.$resId.'_'.$decId], "CostcentreId"=>$costId,
                                "ResourceId"=>$resId, "UnitId"=>$postParam['unitId_'.$resId], "Qty"=>$postParam['costQuantity_'.$resId.'_'.$decId.'_'.$costId], 'IPDTRansId'=>$IPDTransId));
                            $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                            $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            //WBS
                            //MMS_POAnalTrans
                            //POProjTransId,AnalysisId,ResourceId,UnitId,POQty
                            $requestInsert = $sql->insert('MMS_POAnalTrans');
                            $requestInsert->values(array("POProjTransId"=>$POProjTransId,"AnalysisId"=>$postParam['analysisId_'.$resId.'_'.$decId.'_'.$costId],"UnitId"=>$postParam['unitId_'.$resId],
                                "ResourceId"=>$resId, "POQty"=>$postParam['costQuantity_'.$resId.'_'.$decId.'_'.$costId]));
                            $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                            $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $POAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            foreach($costValue as $wbs){
                                //update RequestAnalTrans
                                $select = $sql->update();
                                $select->table('VM_RequestAnalTrans');
                                $select->set(array(
                                    'IndentQty' => new Expression('IndentQty +'.$postParam['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs]),
                                    'BalQty' => new Expression('BalQty -'.$postParam['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs])
                                ));
                                $select->where(array('RequestAHTransId'=>$wbs));
                                $requestHiddenupdateStatement = $sql->getSqlStringForSqlObject($select);
                                $dbAdapter->query($requestHiddenupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                //MMS_IPDAnalTrans
                                //POAHTransId,ReqAHTransId,AnalysisId,ResourceId,UnitId,Qty
                                $requestInsert = $sql->insert('MMS_IPDAnalTrans');
                                $requestInsert->values(array("POAHTransId"=>$POAnalTransId, "ReqAHTransId"=>$wbs, "AnalysisId"=>$postParam['analysisId_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs],
                                    "ResourceId"=>$resId, "Qty"=>$postParam['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs], 'IPDProjTransId'=>$IPDProjTransId));
                                $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                                $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                }
                $connection->commit();
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        //Supply
        $select = $sql->select();
        $select->from('Vendor_Master')
            ->columns(array('VendorId','VendorName'))
            ->where(array('Supply' => '1') );
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsVendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //ServiceProvider
        $select = $sql->select();
        $select->from('Vendor_Master')
            ->columns(array('VendorId','VendorName','LogoPath'))
            ->where(array('Service' => '1') );
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsServicePro = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //TransportMode
        $select = $sql->select();
        $select->from('Vendor_TransportMaster')
            ->columns(array('TransportId','TransportName'))
            ->Order("TransportName");
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsTransPort = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //CurrencyMaster
        $select = $sql->select();
        $select->from('WF_CurrencyMaster')
            ->columns(array('CurrencyId','CurrencyName'))
            ->Order("DefaultCurrency Desc");
        $statement = $sql->getSqlStringForSqlObject($select);
        $resultsTransCurrency = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*service picklist*/
        $serviceSelect = $sql->select();
        $serviceSelect->from(array("a"=>"Vendor_ServiceMaster"))
            ->columns(array("VendorId"=>new Expression("1-1"),'ServiceId', 'ServiceName'));
        $serviceSelect->order("a.ServiceName");
        $serviceStatement = $sql->getSqlStringForSqlObject($serviceSelect);
        $service = $dbAdapter->query($serviceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*Term picklist*/
        $termSelect = $sql->select();
        $termSelect->from(array("a"=>"WF_TermsMaster"))
            ->columns(array("Sel"=>new Expression("1-1"),'TermsId', 'Title'))
            ->where->like('a.TermType', 'S');
        $termSelect->order("a.Title");
        $termStatement = $sql->getSqlStringForSqlObject($termSelect);
        $terms = $dbAdapter->query($termStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a"=>"VM_RequestDecision"))
            ->columns(array(new Expression("
						STUFF((SELECT ',' + CONVERT(VARCHAR(12), m.ResourceId) FROM VM_ReqDecQtyTrans l
						inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
						inner join [VM_ReqDecTrans] n on m.RequestId=n.RequestId
						WHERE l.DecisionId = a.DecisionId and n.DecisionId =a.DecisionId order by m.ResourceId FOR XML PATH('')), 1, 1, '') AS ResourceId,
						STUFF((SELECT ',' + CONVERT(VARCHAR(12), n.CostCentreId) FROM VM_ReqDecQtyTrans l
						inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
						inner join [VM_ReqDecTrans] n1 on M.RequestId=n1.RequestId
						inner join [VM_RequestRegister] n on n1.RequestId = n.RequestId
						WHERE  l.DecisionId = a.DecisionId and n1.DecisionId =a.DecisionId order by m.ResourceId FOR XML PATH('')), 1, 1, '') AS CostCentreId,
						a.DecisionId, a.RDecisionNo, CONVERT(VARCHAR(10), a.DecDate, 105) as DecDate, a.RequestType ")));
        $select ->where (array('a.Approve'=>'Y'));

        $feedStatement = $sql->getSqlStringForSqlObject($select);
        $feedResult = $dbAdapter->query($feedStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $decResult = array_combine(array_column($feedResult, 'DecisionId'), $feedResult);

        $resArr = array_unique(explode(',', implode(',', array_column($feedResult, 'ResourceId'))));
        $costArr = array_unique(explode(',', implode(',', array_column($feedResult, 'CostCentreId'))));

        $resSelect = $sql->select();
        $resSelect->from(array("k"=>"Proj_Resource"))
            ->columns(array(new Expression(
                "STUFF((SELECT ',' + CONVERT(VARCHAR(12), o.DecisionId)FROM VM_ReqDecQtyTrans l
						inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
						inner join VM_ReqDecTrans n on n.RequestId=m.RequestId  and l.DecisionId=n.DecisionId
						inner join VM_RequestDecision o on o.DecisionId=n.DecisionId And o.Approve='Y'
						where k.ResourceId = m.ResourceId order by o.DecisionId FOR XML PATH('')), 1, 1, '') AS decId,
						STUFF( ( SELECT ',' + CONVERT(VARCHAR(12), o.CostCentreId) FROM VM_ReqDecQtyTrans l
						inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId
						inner join VM_RequestRegister o on o.RequestId=m.RequestId
						inner join WF_OperationalCostCentre n on n.CostCentreId=o.CostCentreId
						where k.ResourceId = m.ResourceId order by l.DecisionId FOR XML PATH('') ), 1, 1, '' ) as costId,
						k.ResourceId as data, k.ResourceName as value")))
            ->where(array('k.ResourceId' => $resArr));
        $resStatement = $sql->getSqlStringForSqlObject($resSelect);
        $resResult = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $resourceResult = array_combine(array_column($resResult, 'data'), $resResult);

        $costselect = $sql->select();
        $costselect->from(array("k"=>"WF_OperationalCostCentre"))
            ->columns(array(new Expression(
                "STUFF( ( SELECT ',' + CONVERT(VARCHAR(12), m.ResourceId) FROM VM_ReqDecQtyTrans o
							inner join VM_RequestTrans M on o.ReqTransId=M.RequestTransId
							inner join VM_RequestRegister l on l.RequestId=m.RequestId
							where l.CostCentreId= k.CostCentreId order by o.DecisionId FOR XML PATH('') ), 1, 1, '' ) AS resId,
							STUFF( ( SELECT ',' + CONVERT(VARCHAR(12), o.DecisionId) FROM VM_ReqDecQtyTrans o
							inner join VM_RequestTrans M on o.ReqTransId=M.RequestTransId
							inner join VM_RequestRegister l on l.RequestId=m.RequestId
							where l.CostCentreId= k.CostCentreId order by o.DecisionId FOR XML PATH('') ), 1, 1, '' ) AS decId,
							k.CostCentreId as data, k.CostCentreName as value ")))
            ->where(array('k.CostCentreId' => $costArr));
        $costStatement = $sql->getSqlStringForSqlObject($costselect);
        $costResult = $dbAdapter->query($costStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $prevenStatement = "exec MMS_GetPreferedVendor ";
        $preVendor = $dbAdapter->query($prevenStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $preVen=array();
        foreach($preVendor As $pre){
            $preVen[$pre['ResourceId']]=$pre;
        }
        $narrationSelect = $sql->select();
        $narrationSelect->from("WF_NarrationMaster")
            ->columns(array("Description"=>"Description"))
            ->where(array("TypeId"=>"101"));
        $narrationStatement = $sql->getSqlStringForSqlObject($narrationSelect);
        $narration = $dbAdapter->query($narrationStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->narration = $narration;

        $projResult = array_combine(array_column($costResult, 'data'), $costResult);

        $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;

        $this->_view->service = $service;
        $this->_view->terms = $terms;
        $this->_view->feedResult = $decResult;
        $this->_view->resResult = $resourceResult;
        $this->_view->costResult = $projResult;
        $this->_view->VendorList = $resultsVendor;
        $this->_view->ServiceProviderList = $resultsServicePro;
        $this->_view->TransportList = $resultsTransPort;
        $this->_view->CurrencyList = $resultsTransCurrency;
        $this->_view->preVen=json_encode($preVen);
        return $this->_view;
    }
    public function designAction()	{
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');

        return $this->_view;
    }
    public function registerAction()	{
        /*if(!$this->auth->hasIdentity()) {
         $this->redirect()->toRoute('application/default', array('controller' => 'index','action' => 'index'));
     }*/

        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $config = $this->getServiceLocator()->get('config');

        return $this->_view;
    }
    public function detailedAction(){
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
        $id = $this->params()->fromRoute('rid');
        /*Ajax Request*/
        if($request->isXmlHttpRequest()) {
            $resp = array();
            if ($request->isPost()) {

                $postParam = $request->getPost();

                if($postParam['mode'] == 'final'){
                    $resourceSelect = $sql->select();
                    $resourceSelect->from(array("a"=>"MMS_POTrans"))
                        ->columns(array(new Expression("a.PoTransId,a.PORegisterId,a.ResourceId,a.ItemId,CAST(a.POQty as Decimal(18,3)) As POQty,
                                    Case When a.ItemId>0 Then f.ItemCode Else d.Code End As Code,
                                    Case When a.ItemId>0 Then f.BrandName Else d.ResourceName End As ResourceName,
                                    a.UnitId,e.UnitName,
                                    CAST(a.QRate As Decimal(18,2)) As Rate,
                                    CAST(a.QAmount As Decimal(18,2)) As Amount")))
                        // ->columns(array('PoTransId', 'PORegisterId','ResourceId','POQty'), array("Code", "ResourceName", "UnitId"), array("UnitName") )
                        ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("Code", "ResourceName", "UnitId"), $resourceSelect::JOIN_INNER)
                        ->join(array("e"=>"Proj_UOM"), "a.UnitId=e.UnitId", array("UnitName"), $resourceSelect::JOIN_LEFT)
                        ->join(array("f"=>"MMS_Brand"),"a.ItemId=f.BrandId And a.ResourceId=f.ResourceId",array(),$resourceSelect::JOIN_LEFT)
                        ->where(array('a.PORegisterId'=>$postParam['regId']));
                    $resourceStatement = $sql->getSqlStringForSqlObject($resourceSelect);
                    $resp['resource'] = $dbAdapter->query($resourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $resp['decision'] = array();
                    $resp['project'] = array();
                    $project = array();
                    $resp['wbs'] = array();
                    $wbsData = array();
                    foreach($resp['resource'] as $res){
                        $decisionSelect = $sql->select();
                        $decisionSelect->from(array("a"=>"MMS_IPDTrans"))
                            ->columns(array(new Expression("a.IPDTransId,a.ResourceId,a.POTransId,a.DecTransId,a.Decisionid,
                            Cast(a.Qty As Decimal(18,3)) As Qty,b.PORegisterId,c.RDecisionNo")))
                            // ->columns(array('IPDTransId','ResourceId', 'POTransId', 'ReqTransId','DecisionId','Qty'), array("PORegisterId"), array("RDecisionNo"))
                            ->join(array("b"=>"MMS_POTrans"), "a.POTransId=b.PoTransId", array("PORegisterId"), $decisionSelect::JOIN_INNER)
                            ->join(array("c"=>"VM_RequestDecision"), "a.DecisionId=c.DecisionId", array("RDecisionNo"), $decisionSelect::JOIN_INNER)
                            ->where(array('a.POTransId'=>$res['PoTransId'], 'b.PORegisterId'=>$postParam['regId'],'c.Approve'=>'Y'));
                        $decisionStatement = $sql->getSqlStringForSqlObject($decisionSelect);
                        $decisionResult = $dbAdapter->query($decisionStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        foreach($decisionResult as $dec){
                            $projectSelect = $sql->select();
                            $projectSelect->from(array("a"=>"MMS_POProjTrans"))
                                ->columns(array(new Expression("a.POProjTransId,a.POTransId,A.ResourceId,a.ItemId,
                                Cast(a.POQty As Decimal(18,3)) As POQty,b.ReqTransId,b.CostCentreId,b.IPDTransId,b.IPDProjTransId,c.CostCentreName")))
                                //->columns(array('POProjTransId','POTransId','ResourceId','POQty'), array("ReqTransId",'CostCentreId', "IPDTransId", "IPDProjTransId"), array("CostCentreName"))
                                ->join(array("b"=>"MMS_IPDProjTrans"), "a.POProjTransId=b.POProjTransId", array(), $projectSelect::JOIN_INNER)
                                ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $projectSelect::JOIN_INNER)
                                ->where(array('a.POTransId'=>$dec['POTransId'], "b.IPDTransId"=>$dec['IPDTransId']));
                            $projectStatement = $sql->getSqlStringForSqlObject($projectSelect);
                            $projectResult = $dbAdapter->query($projectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach($projectResult as $cost){
                                $wbsSelect = $sql->select();
                                $wbsSelect->from(array("a"=>"MMS_POAnalTrans"))
                                    ->columns(array(new Expression("a.POProjTransId,a.POAnalTransId,a.AnalysisId,a.ResourceId,a.ItemId,b.IPDAHTransId,
                                    CAST(b.Qty As Decimal(18,3)) As Qty,b.IPDProjTransId,c.WbsName")))
                                    //->columns(array('POProjTransId','POAnalTransId','AnalysisId','ResourceId'), array("IPDAHTransId","AnalysisId","Qty", "IPDProjTransId"), array("WbsName"))
                                    ->join(array("b"=>"MMS_IPDAnalTrans"), "a.POAnalTransId=b.POAHTransId", array(), $wbsSelect::JOIN_INNER)
                                    ->join(array("c"=>"Proj_WBSMaster"), "b.AnalysisId=c.WBSId", array("WBSName"), $wbsSelect::JOIN_INNER)
                                    ->where(array('b.IPDProjTransId'=>$cost['IPDProjTransId']));
                                $wbsStatement = $sql->getSqlStringForSqlObject($wbsSelect);
                                $wbsResult = $dbAdapter->query($wbsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                array_push($resp['project'], $cost);
                                foreach($wbsResult as $wbs){
                                    array_push($resp['wbs'], $wbs);
                                }
                            }
                            array_push($resp['decision'], $dec);
                        }
                    }
                }
                else if($postParam['mode'] == 'getqualdetails'){

                    $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                    $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                    $POId=$this->bsf->isNullCheck($this->params()->fromPost('poId'), 'number');

                    $select = $sql->select();
                    $select->from(array("c" => "MMS_POQualTrans"))
                        ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),'QualifierId'=>new Expression('c.QualifierId'),
                            'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
                            'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
                            'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
                            'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),
                            'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,2)) '),'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,2)) '),
                            'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,2)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
                            'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,2)) '),'QualifierName'=>new Expression('b.QualifierName'),
                            'QualifierTypeId'=>new Expression('b.QualifierTypeId'),'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
                        ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);

                    $select->where(array('a.QualType' => 'M', 'c.PORegisterId' => $POId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
                    $selMain = $sql -> select()->from(array('result'=>$select));
                    $selMain->order('SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($selMain);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $resp = Qualifier::getQualifier($qualList);
                }
            }

            $this->_view->setTerminal(true);
            $response->setStatusCode('200');
            $response->setContent(json_encode($resp));
            return $response;
        } else if ($request->isPost()) {

        }

        $regDetails = $sql->select();
        $regDetails->from(array("a" => "MMS_PORegister"))
            ->columns(array(new Expression("a.PONo,a.CCPONo,a.CPONo,Convert(Varchar(10),a.PODate,103) As PODate,
                    Convert(Varchar(10),a.ReqDate,103) As ReqdDate,a.ReqNo As RefNo,
            b.CostCentreName,c.VendorName,Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve,
            d.CurrencyName,e.PurchaseTypeName,f.AccountName,a.CostCentreId,a.VendorId,a.BranchId,a.BranchTransId,a.PurchaseTypeId,a.CurrencyId,
            a.ProjectAddress,a.Narration,a.PoDelId,a.PoDelAdd,
            a.CompanyContactName,a.CompanyContactNo,a.CompanyMobile,a.CompanyEmail,
            a.SiteContactName,a.SiteContactNo,a.SiteMobile,a.SiteEmail")))
            ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $regDetails::JOIN_INNER)
            ->join(array("c" => "Vendor_Master"), "a.VendorId=c.VendorId", array(), $regDetails::JOIN_INNER)
            ->join(array("d" => "WF_CurrencyMaster"),"a.CurrencyId=d.CurrencyId",array(),$regDetails::JOIN_LEFT)
            ->join(array("e" => "MMS_PurchaseType"),"a.PurchaseTypeId=e.PurchaseTypeId",array(),$regDetails::JOIN_LEFT)
            ->join(array("f" => "FA_AccountMaster"),"e.AccountId=f.AccountId",array(),$regDetails::JOIN_LEFT)
            ->where(array('a.PORegisterId' => $id));
        $regStatement = $sql->getSqlStringForSqlObject($regDetails);
        $regResult = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $poDis = $sql->select();
        $poDis->from(array("a" => "MMS_PODistributorTrans"))
            ->columns(array(new Expression("b.VendorName As VendorName")))
            ->join(array("b" => "Vendor_Master"),"a.VendorId=b.VendorId",array(),$poDis::JOIN_INNER)
            ->where("a.PORegisterId=$id");
        $disStatement = $sql->getSqlStringForSqlObject($poDis);
        $disResult = $dbAdapter->query($disStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $prefix = $disList = '';

        if(count($disResult) > 0)
        {
            foreach($disResult As $dis)
            {
                $disList .= $prefix .'' . $dis["VendorName"] . '';
                $prefix = ', ';
            }

        }
        $CostCentreId=$regResult['CostCentreId'];
        $VendorId=$regResult['VendorId'];
        $PurTypeId=$regResult['PurchaseTypeId'];
        $BranchId=$regResult['BranchId'];
        $BranchTransId=$regResult['BranchTransId'];
        $WareHouseId = $regResult['PoDelId'];
        $this->_view->CostCentreId = $regResult['CostCentreId'];
        $this->_view->CostCentreName = $regResult['CostCentreName'];
        $this->_view->VendorName = $regResult['VendorName'];
        $this->_view->PONo = $regResult['PONo'];
        $this->_view->PODate = $regResult['PODate'];
        $this->_view->RefNo = $regResult['RefNo'];
        $this->_view->Approve = $regResult['Approve'];
        $this->_view->CCPONo = $regResult['CCPONo'];
        $this->_view->CPONo = $regResult['CPONo'];
        $this->_view->ReqDate = $regResult['ReqdDate'];
        $this->_view->Currency = $regResult['CurrencyName'];
        $this->_view->PurchaseType = $regResult['PurchaseTypeName'];
        $this->_view->AccountType = $regResult['AccountName'];
        $this->_view->Distributor = $disList;
        $this->_view->regId = $id;
        $this->_view->VendorId = $regResult['VendorId'];
        $this->_view->BranchId = $regResult['BranchId'];
        $this->_view->BranchTransId = $regResult['BranchTransId'];
        $this->_view->gridtype = 0;
        $this->_view->Narration = $regResult['Narration'];
        $this->_view->ccphone = $regResult['PoDelAdd'];
        $this->_view->cccontact = $regResult['CompanyContactName'];
        $this->_view->ccphone = $regResult['CompanyContactNo'];
        $this->_view->ccmobile = $regResult['CompanyMobile'];
        $this->_view->ccemail = $regResult['CompanyEmail'];
        $this->_view->ccontact = $regResult['SiteContactName'];
        $this->_view->cphone = $regResult['SiteContactNo'];
        $this->_view->cmobile = $regResult['SiteMobile'];
        $this->_view->cemail = $regResult['SiteEmail'];

        $selAmt = $sql -> select();
        $selAmt -> from (array("a" => "MMS_POTrans"))
            ->columns(array(new Expression("CAST(sum(a.Amount) As Decimal(18,2)) As Amount,CAST(sum(a.QAmount) As Decimal(18,2)) As QAmount,CAST(b.NetAmount As Decimal(18,2)) As NetAmount")))
            ->join(array("b" => "MMS_PORegister"),"a.PORegisterId=b.PORegisterId",array(),$selAmt::JOIN_INNER)
            ->where('b.PORegisterId='. $id .'');
        $selAmt->group(array(new Expression("b.NetAmount")));
        $amtStatement = $sql->getSqlStringForSqlObject($selAmt);
        $amtResult = $dbAdapter->query($amtStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->BaseTotal = $amtResult['Amount'];
        $this->_view->Total = $amtResult['QAmount'];
        $this->_view->NetTotal = $amtResult['NetAmount'];


        $selTer = $sql -> select();
        $selTer -> from (array("a" => "MMS_POPaymentTerms"))
            ->columns(array(new Expression("Case When ValueFromNet=0 Then 'Base Amount' When ValueFromNet=1 Then 'Net Amount' Else 'Gross Amount' End As ValueFromNet")))
            ->where('PORegisterId='.$id.'');
        $terStatement = $sql->getSqlStringForSqlObject($selTer);
        $terResult = $dbAdapter->query($terStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->valuefrom=$this->bsf->isNullCheck($terResult['ValueFromNet'],'string');


        $select = $sql->select();
        $select->from(array("a"=>"MMS_PurchaseType"))
            ->columns(array("PurchaseTypeId","PurchaseTypeName"))
            ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array(),$select::JOIN_INNER)
            ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
            ->where('c.CostCentreId='.$CostCentreId.' and b.Sel=1');
        $typeStatement = $sql->getSqlStringForSqlObject($select);
        $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->purchaseType = $purchaseType;

        $selbranchContact = $sql -> select();
        $selbranchContact -> from (array("a" => 'Vendor_Branch'))
            -> columns(array('BranchName','ContactNo' => new Expression("a.Phone")))
            ->where('BranchId='.$BranchId.'');
        $branchcontactStatement = $sql->getSqlStringForSqlObject($selbranchContact);
        $cPerno = $dbAdapter->query($branchcontactStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->bcNo = $cPerno["ContactNo"];
        $this->_view->branchName = $cPerno["BranchName"];

        $selbranchcperson = $sql -> select();
        $selbranchcperson -> from (array("a" => "Vendor_BranchContactDetail"))
            ->columns(array('ContactPerson' => new Expression("a.ContactPerson")))
            ->where('BranchTransId=' .$BranchTransId. '');
        $statement = $sql->getSqlStringForSqlObject($selbranchcperson);
        $cPerson = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->cPerson = $cPerson['ContactPerson'];

        $selbranchcpersonno = $sql -> select();
        $selbranchcpersonno -> from (array("a" => "Vendor_BranchContactDetail"))
            ->columns(array('cpersonno' => new Expression("a.ContactNo")))
            ->where('BranchTransId=' .$BranchTransId. '');
        $cperStatement = $sql->getSqlStringForSqlObject($selbranchcpersonno);
        $cbranchperno = $dbAdapter->query($cperStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->cpNo = $cbranchperno["cpersonno"];

        $selAcc=$sql->select();
        $selAcc->from(array("a"=>"FA_AccountMaster"))
            ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
            ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
            ->where(array("b.PurchaseTypeId"=>$PurTypeId));
        $accStatement = $sql->getSqlStringForSqlObject($selAcc);
        $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->accType = $accType;

        if($regResult['ProjectAddress'] == '') {
            $selCCAddress = $sql->select();
            $selCCAddress->from(array("a" => "WF_CostCentre"))
                ->columns(array("Address" => new Expression("(a.Address+CHAR(13)+c.CityName+CHAR(9)+d.StateName+CHAR(13)+e.CountryName+CHAR(13)+a.Pincode)")))
                ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.FACostCentreId", array(), $selCCAddress::JOIN_INNER)
                ->join(array("c" => "WF_CityMaster"), "a.CityId=c.CityId", array(), $selCCAddress::JOIN_LEFT)
                ->join(array("d" => "WF_StateMaster"), "c.StateId=d.StateId", array(), $selCCAddress::JOIN_LEFT)
                ->join(array("e" => "WF_CountryMaster"), "d.CountryId=e.CountryId", array(), $selCCAddress::JOIN_LEFT)
                ->where('b.CostCentreId=' . $CostCentreId . '');
            $selAddStatement = $sql->getSqlStringForSqlObject($selCCAddress);
            $ccaddress = $dbAdapter->query($selAddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->ccaddress = $ccaddress;
            $ccadd = $this->_view->ccaddress['Address'];
            $this->_view->deladd = $ccadd;
        }
        else {
            $this->_view->deladd = $regResult['ProjectAddress'];
        }

        $selWareHouse = $sql -> select();
        $selWareHouse->from(array("a" => "MMS_WareHouseDetails"))
            ->columns(array("WareHouseName"=>new Expression("b.WareHouseName +' - ' + a.Description"),"TransId" => new Expression("a.TransId")))
            ->join(array("b"=>"MMS_WareHouse"),"a.Warehouseid=b.Warehouseid",array(),$selWareHouse::JOIN_INNER)
            ->join(array("c"=>"MMS_CCWareHouse"),"b.WareHouseId=c.WareHouseId",array(),$selWareHouse::JOIN_INNER)
            ->where('a.TransId='.$WareHouseId.' and a.LastLevel=1');
        $selWhStatement = $sql->getSqlStringForSqlObject($selWareHouse);
        $warehouse = $dbAdapter->query($selWhStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $wareTransid = $warehouse['TransId'];
        $this->_view->whname = $warehouse['WareHouseName'];

        if($wareTransid > 0){
            $selwhadd = $sql -> select();
            $selwhadd -> from (array("a" => 'MMS_WareHouse'))
                ->columns(array('whaddress'=>new Expression("(A.Address+char(13)+c.CityName+char(9)+d.StateName+char(13)+e.CountryName+char(13)+a.PinCode)")))
                ->join(array('b'=>"MMS_WareHouseDetails"),'a.WareHouseId=b.WareHouseId',array(),$selwhadd::JOIN_INNER)
                ->join(array('c'=>"WF_CityMaster"),'a.CityId=c.CityId',array(),$selwhadd::JOIN_LEFT)
                ->join(array('d'=>"WF_StateMaster"),'c.StateId=d.StateId',array(),$selwhadd::JOIN_LEFT)
                ->join(array('e'=>"WF_CountryMaster"),'d.CountryId=e.CountryId',array(),$selwhadd::JOIN_LEFT)
                ->where('b.TransId='.$wareTransid.'');
            $statement = $sql->getSqlStringForSqlObject($selwhadd);
            $arr_whadd = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $this->_view->whadd = $arr_whadd['whaddress'];
        }

//        $selcompdet = $sql->select();
//        $selcompdet->from(array("a" => "WF_CompanyMaster"))
//            ->columns(array("COContactPerson" => new Expression("a.ContactPerson"), "COPhone" => new Expression("a.Phone"),
//                "COMobile" => new Expression("a.Mobile"), "COEmail" => new Expression("a.Email"),
//                "CCContactPerson" => new Expression("C.ContactPerson"), "CCPhone" => new Expression("C.Phone"),
//                "CCMobile" => new Expression("C.Mobile"), "CCEmail" => new Expression("C.Email")))
//            ->join(array("b" => "WF_OperationalCostCentre"), "a.CompanyId=b.CompanyId", array(), $selcompdet::JOIN_INNER)
//            ->join(array("c" => "WF_CostCentre"), "b.FACostCentreId=c.CostCentreId", array(), $selcompdet::JOIN_INNER)
//            ->where('b.CostCentreId=' . $CostCentreId . '');
//        $selcomStatement = $sql->getSqlStringForSqlObject($selcompdet);
//        $compdetails = $dbAdapter->query($selcomStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
//        $this->_view->compdetails = $compdetails;
//        $cccontact = $this->_view->compdetails['CCContactPerson'];
//        $ccphone = $this->_view->compdetails['CCPhone'];
//        $ccmobile = $this->_view->compdetails['CCMobile'];
//        $ccemail = $this->_view->compdetails['CCEmail'];
//        $ccontact = $this->_view->compdetails['COContactPerson'];
//        $cphone = $this->_view->compdetails['COPhone'];
//        $cmobile = $this->_view->compdetails['COMobile'];
//        $cemail = $this->_view->compdetails['COEmail'];
//        $this->_view->cccontact = $cccontact;
//        $this->_view->ccphone = $ccphone;
//        $this->_view->ccmobile = $ccmobile;
//        $this->_view->ccemail = $ccemail;
//        $this->_view->ccontact = $ccontact;
//        $this->_view->cphone = $cphone;
//        $this->_view->cmobile = $cmobile;
//        $this->_view->cemail = $cemail;


        $select = $sql->select();
        $select->from(array("a"=>"MMS_POTrans"))
            ->columns(array(new Expression("a.POTransId,a.ResourceId,a.ItemId,Case When a.ItemId>0 Then c.ItemCode+' - '+c.BrandName Else b.Code+' - '+b.ResourceName End As [Desc],
                             d.UnitName,CAST(A.POQty As Decimal(18,3)) As Qty,A.POQty As HiddenQty,
                             CAST(A.Rate As Decimal(18,2)) As Rate,CAST(A.QRate As Decimal(18,2)) As QRate,
                             CAST(A.Amount As Decimal(18,2)) As BaseAmount,CAST(A.QAmount As Decimal(18,2)) As Amount,
                             A.UnitId,d.UnitName,a.Description As ResSpec,
                             RFrom = Case When a.ResourceId IN (Select A.ResourceId From Proj_ProjectResource A
                             Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentreId) Then 'Project' Else 'Library' End    ")))
            ->join(array('b'=>'Proj_Resource'),'a.ResourceId=b.ResourceId',array(),$select::JOIN_INNER)
            ->join(array('c'=>'MMS_Brand'),'a.ResourceId=b.ResourceId and a.ItemId=c.BrandId',array(),$select::JOIN_LEFT)
            ->join(array('d'=>'Proj_UOM'),'a.UnitId=d.UnitId',array(),$select::JOIN_LEFT)
            ->where('a.PORegisterId='.$id.'');
        $statement = $sql->getSqlStringForSqlObject($select);
        //$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $poTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->arr_requestResources = $poTrans;

        $select = $sql->select();
        $select->from(array("a" => "VM_RequestDecision"))
            ->columns(array(new Expression('a.DecisionId,b.TransId As DecTransId,b.ReqTransId,
                        a.RDecisionNo As DecisionNo,c.ResourceId,c.ItemId,
                        CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3)) As BalQty,
                        Cast(e.Qty as Decimal(18,3)) As Qty,Cast(e.Qty as Decimal(18,3)) As HiddenQty')))
            ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
            ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
            ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
            ->join(array('e' => 'MMS_IPDTrans'),'a.DecisionId=e.DecisionId And b.TransId=e.DecTransId',array(),$select::JOIN_INNER)
            ->join(array('f' => 'MMS_POTrans'),'e.POTransId=f.POTransId',array(),$select::JOIN_INNER)
            ->where('f.PORegisterId=' . $id . ' ');

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $wbsRes = $sql -> select();
        $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
            ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
            ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
            ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId and a.ProjectId=c.ProjectId',array(),$wbsRes::JOIN_INNER)
            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_INNER)
            ->where("a.IncludeFlag=1 and D.CostCentreId=$CostCentreId");
        $statement = $sql->getSqlStringForSqlObject($wbsRes);
        $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $select = $sql->select();
        $select->from(array("a" => "MMS_IPDAnalTrans"))
            ->columns(array(new Expression("F.ParentText+'->'+F.WBSName As WBSName,c.DecisionId,
                        c.DecTransId,e.RCATransId As DecATransId,e.ReqTransId,e.ReqAHTransId,
                            d.ResourceId,d.ItemId,a.AnalysisId As WBSId,
                            CAST((e.IndentQty-e.IndAdjQty) As Decimal(18,3)) As BalQty,
                            CAST(a.Qty As Decimal(18,3)) As Qty,CAST(a.Qty As Decimal(18,3)) As HiddenQty ")))
            ->join(array('b'=>'MMS_IPDProjTrans'),'a.IPDProjTransId=b.IPDProjTransId',array(),$select::JOIN_INNER)
            ->join(array('c'=>'MMS_IPDTrans'),'b.IPDTransId=c.IPDTransId',array(),$select::JOIN_INNER)
            ->join(array('d'=>'MMS_POTrans'),'c.POTransId=d.POTransId',array(),$select::JOIN_INNER)
            ->join(array('j'=>'MMS_PORegister'),'d.PORegisterId=j.PORegisterId',array(),$select::JOIN_INNER)
            ->join(array('e'=>'VM_ReqDecQtyAnalTrans'),'a.DecATransId=e.RCATransId And A.DecTransId=e.TransId',array(),$select::JOIN_INNER)
            ->join(array('f'=>'Proj_WBSMaster'),'a.AnalysisId=f.WBSId',array(),$select::JOIN_INNER)
            ->where('j.PORegisterId='. $id .'');
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a" => "Proj_QualifierTrans"))
            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
            ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','NetPer',
                'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
        $select->where(array('a.QualType' => 'M'));
        $select->order('a.SortId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sHtml=Qualifier::getQualifier($qualList);
        $this->_view->qualHtml = $sHtml;


        $arrqual = array();
        $select = $sql->select();
        $select->from(array("a" => "MMS_POQualTrans"))
            ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
                'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                'EDCessAmt', 'HEDCessAmt', 'NetAmt'));
        $select->where(array('a.PORegisterId'=>$id));
        $select->order('a.SortId ASC');
        $statement = $sql->getSqlStringForSqlObject($select);
        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $sHtml = Qualifier::getQualifier($qualList);
//                    $arrqual = $sHtml;
        $this->_view->arr_qual_list = $qualList;


        $select = $sql->select();
        $select->from(array("a" => "WF_TermsMaster"))
            //->columns(array('data' => 'TermsId',))
            ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,2)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross")))
            ->where(array("TermType"=>'S'));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("a" => "WF_TermsMaster"))
            //->columns(array('data' => 'TermsId',))
            ->columns(array(new Expression("a.TermsId As data,a.SlNo,a.Title As value,CAST(b.Per As Decimal(18,3)) As Per,
                                CAST(b.Value As Decimal(18,2)) As Val,b.Period As Period,
                                Convert(Varchar(10),b.TDate,103) As Dte,
                                b.TString As [Strg],a.Per As IsPer,
                                a.Value As IsValue,a.Period As IsPeriod,
                                a.TDate As IsTDate,a.TSTring As IsTString,a.IncludeGross")))
            ->join(array('b'=>'MMS_POPaymentTerms'),'a.TermsId=b.TermsId',array(),$select::JOIN_INNER)
            ->where(array("b.PORegisterId"=>$id));
        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_edit_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Resource Auto Complete

        $selRa = $sql -> select();
        $selRa->from(array("a" => "Proj_Resource"))
            ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then (c.ItemCode + ' - ' + c.BrandName) Else (a.Code + ' - ' + a.ResourceName) End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate else a.Rate End As Rate,'Library' As RFrom  ")))
            ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
            ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
            ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
            ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
            ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From
                                Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=". $CostCentreId .") and
                                (a.ResourceId NOT IN (Select ResourceId From MMS_POTrans
                         Where PORegisterId=".$id.") Or isnull(c.BrandId,0) NOT IN (Select ItemId From MMS_POTrans Where PORegisterId=".$id."))  ");

        $statement = $sql->getSqlStringForSqlObject($select);
        $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //Get EstimateQty,EstimateRate,AvailableQty

        $sel = $sql->select();
        $sel->from(array("a" => "Proj_ProjectResource"))
            ->columns(array('ResourceId' => new Expression('a.ResourceId'), 'EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
            ->Where ('b.CostCentreId=' . $CostCentreId .' And
                                     a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .' )      ');


        $sel1 = $sql->select();
        $sel1->from(array("a"=> "MMS_POTrans" ))
            ->columns(array('ResourceId' => new Expression("a.ResourceId"), 'EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"), 'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
            ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
            ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And
                                a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                                  And b.CostCentreId='.$CostCentreId.' And c.General=0 Group By a.ResourceId ');
        $sel1->combine($sel,'Union ALL');



        $sel2 = $sql -> select();
        $sel2->from(array("a" => "MMS_DCTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
            ->where('A.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                                     And B.CostCentreId='.$CostCentreId .' And B.General=0 Group By a.ResourceId ');
        $sel2->combine($sel1,"Union ALL");

        $sel3 = $sql -> select();
        $sel3 -> from(array("a" => "MMS_PVTrans"))
            ->columns(array('a.ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate'=> new Expression("CAST(0 As Decimal(18,3))"), 'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),
                'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
            ->where('b.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                                     and b.CostCentreId='.$CostCentreId.' and b.General=0 Group By a.ResourceId ');
        $sel3->combine($sel2,"Union ALL");

        $sel4 = $sql -> select();
        $sel4 -> from(array("a" => "MMS_PRTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),
                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                                     And b.CostCentreId='.$CostCentreId.' Group By a.ResourceId');
        $sel4->combine($sel3,"Union ALL");

        $sel5 = $sql -> select();
        $sel5 -> from(array("a" => "MMS_TransferTrans"))
            -> columns(array('ResourceId'=>new Expression("a.ResourceId"),'TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
            ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel5::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                                     and b.ToCostCentreId='.$CostCentreId.' Group By a.ResourceId ');

        $sel6 = $sql -> select();
        $sel6 -> from(array("a" => "MMS_TransferTrans"))
            -> columns(array('ResourceId'=>new Expression("a.ResourceId"),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
            ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel6::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                                     and b.FromCostCentreId='.$CostCentreId.' Group By a.ResourceId ');
        $sel6->combine($sel5,"Union ALL");

        $sel7 = $sql -> select();
        $sel7 -> from(array("A"=>$sel6))
            ->columns(array('ResourceId'=>new Expression("ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
        $sel7->group(new Expression("A.ResourceId"));
        $sel7 -> combine($sel4,"Union ALL");

        $sel8 = $sql -> select();
        $sel8 -> from(array("a" => "VM_RequestTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                                     and b.CostCentreId='.$CostCentreId.' Group By a.ResourceId ');
        $sel8->combine($sel7,"Union ALL");

        $sel9 = $sql -> select();
        $sel9 -> from(array("a" => "MMS_POTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
            ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
            ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
            ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') Group By a.ResourceId ');
        $sel9->combine($sel8,"Union ALL");

        $sel10 = $sql -> select();
        $sel10 -> from(array("a" => "MMS_DCTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
            ->where ('b.General=0 and b.CostCentreId='.$CostCentreId.'
                                and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') Group By a.ResourceId ');
        $sel10->combine($sel9,"Union ALL");

        $sel11 = $sql -> select();
        $sel11 -> from(array("a" => "MMS_PVTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
            ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
            ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CostCentreId.'
                                     and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') Group By a.ResourceId ');
        $sel11->combine($sel10,"Union ALL");

        $sel12 = $sql -> select();
        $sel12 -> from(array("G"=>$sel11))
            ->columns(array('ResourceId'=>new Expression("G.ResourceId"),
                'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,2))"),
                'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,2)) Else 0 End"),
                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,2)) Else 0 End"),
                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,2))"),
                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,2))"),
                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,2))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,2))"),
                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,2))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,2))") ));
        $sel12->group(new Expression("G.ResourceId"));

        $statement = $sql->getSqlStringForSqlObject($sel12);
        $this->_view->arr_estimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //

        //Get WBS Estimate
        $sel = $sql->select();
        $sel->from(array("a" => "Proj_ProjectWBSResource"))
            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("a.Rate"),
                'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"), 'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),
                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
            ->Where ('b.CostCentreId=' . $CostCentreId .' and  a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                 and a.WBSId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');

        $sel1 = $sql->select();
        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                'EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') And b.CostCentreId='.$CostCentreId.' And d.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel1->combine($sel,'Union ALL');


        $sel2 = $sql -> select();
        $sel2->from(array("a" => "MMS_DCAnalTrans"))
            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') And c.CostCentreId='.$CostCentreId .' And c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel2->combine($sel1,"Union ALL");


        $sel3 = $sql -> select();
        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
            ->where('c.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') and c.CostCentreId='.$CostCentreId.' and c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel3->combine($sel2,"Union ALL");

        $sel4 = $sql -> select();
        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') And c.CostCentreId='.$CostCentreId.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel4->combine($sel3,"Union ALL");

        $sel5 = $sql -> select();
        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
            -> columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') and c.ToCostCentreId='.$CostCentreId.'
                                  And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel5->group(new Expression("a.ResourceId,A.AnalysisId"));

        $sel6 = $sql -> select();
        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
            -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')and c.FromCostCentreId='.$CostCentreId.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel6->combine($sel5,"Union ALL");


        $sel7 = $sql -> select();
        $sel7 -> from(array("A"=>$sel6))
            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
        $sel7->group(new Expression("a.ResourceId,a.WBSId"));
        $sel7 -> combine($sel4,"Union ALL");


        $sel8 = $sql -> select();
        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
            ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') and c.CostCentreId='.$CostCentreId.' and
                                 a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel8->combine($sel7,"Union ALL");

        $sel9 = $sql -> select();
        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                  and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel9->combine($sel8,"Union ALL");

        $sel10 = $sql -> select();
        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
            ->where ('c.General=0 and c.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel10->combine($sel9,"Union ALL");


        $sel11 = $sql -> select();
        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .')
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .'
                                 and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $id .') )');
        $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
        $sel11->combine($sel10,"Union ALL");

        $sel12 = $sql -> select();
        $sel12 -> from(array("G"=>$sel11))
            ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),
                'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,3))"),
                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));
        $sel12->group(new Expression("G.ResourceId,G.WBSId"));

        $statement = $sql->getSqlStringForSqlObject($sel12);
        $this->_view->arr_wbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        //end

        //Common function

        $this->_view->regId = $this->params()->fromRoute('rid');
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
        return $this->_view;


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
                    $regSelect = $sql->select();
                    $regSelect->from(array("a"=>"MMS_PoRegister"))
                        ->columns(array(new Expression("a.PoRegisterId,a.PODate As PoDate,
                                a.PoNo,CAST(a.Amount As Decimal(18,2)) As NetAmount,a.CostCentreId as CostCentreId,
                                Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName"), $regSelect::JOIN_LEFT)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"a.CostCentreId=c.CostCentreId",array("CostCentreName"),$regSelect::JOIN_INNER)
                        ->where("a.DeleteFlag=0 and a.LivePO=1")
                        ->Order("a.PORegisterId Desc");
                    $regStatement = $sql->getSqlStringForSqlObject($regSelect);
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
    public function feedsEntryEditAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        /*Ajax Request*/
        if($request->isXmlHttpRequest()){
            if ($request->isPost()){
                /*Write your Ajax post code here*/
                $resp =  array();
                $postParam = $request->getPost();
                if($postParam["mode"] == 'thirdStep'){
                    $poRegId=$postParam['poregId'];
                    $res = json_decode($postParam['res'], true);
                    $resId = array_keys($res);
                    $decId = array_unique(json_decode($postParam['dec'], true));
                    $costId = array_unique(json_decode($postParam['cost'], true));
                    /*start PO 1*/
                    $select1 = $sql->select();
                    $select1->from(array("a"=>"VM_RequestTrans"))
                        ->columns(array('ResourceId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))")),
                            array("CurQty"=>new Expression("1-1")),array(),array("Code", "ResourceName", "UnitId"),array("UnitName"),array(),array(),array( ) )
                        ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("CurQty"=>new Expression("1-1")), $select1::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array(), $select1::JOIN_LEFT)
                        ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("Code", "ResourceName", "UnitId"), $select1::JOIN_INNER)
                        ->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select1::JOIN_LEFT)
                        ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array(), $select1::JOIN_INNER)
                        ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array(), $select1::JOIN_INNER)
                        ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select1::JOIN_INNER);
                    $select1->where(array('f1.DecisionId'=>$decId,
                        'b.CostCentreId'=>$costId,
                        'a.ResourceId'=>$resId));
                    $select1->group(new Expression("a.ResourceId,d.Code,d.ResourceName,d.UnitId,e.UnitName"));

                    $select2 = $sql->select();
                    $select2->from(array("a"=>"VM_RequestTrans"))
                        ->columns(array("ResourceId"=>new Expression("a.ResourceId"),"Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(h1.Qty) As Decimal(18,5))")),
                            array(),array(),array("Code", "ResourceName", "UnitId"),array("UnitName"),array(),array(),array( ) )
                        ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array(), $select2::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array(), $select2::JOIN_LEFT)
                        ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("Code", "ResourceName", "UnitId"), $select2::JOIN_INNER)
                        ->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select2::JOIN_LEFT)
                        ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array(), $select2::JOIN_INNER)
                        ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array(), $select2::JOIN_INNER)
                        ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select2::JOIN_INNER)
                        ->join(array("h"=>"MMS_POTrans"), "a.ResourceId=h.ResourceId", array(), $select2::JOIN_INNER)
                        ->join(array("h1"=>"MMS_IPDTrans"), "h.PoTransId=h1.POTransId and a.ResourceId=h1.ResourceId and a.RequestTransId=h1.ReqTransId and f.DecisionId=h1.DecisionId", array(), $select2::JOIN_INNER);
                    $select2->where(array('h.PORegisterId'=>$poRegId,
                        'f1.DecisionId'=>$decId,
                        'b.CostCentreId'=>$costId,
                        'a.ResourceId'=>$resId,
                        'f1.Approve'=>'Y'));
                    $select2->group(new Expression("a.ResourceId,d.Code,d.ResourceName,d.UnitId,e.UnitName"));
                    $select2->combine($select1,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$select2))
                        ->columns(array('ResourceId','Code','ResourceName','UnitId','UnitName',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
                    $select3->group(new Expression("ResourceId,Code,ResourceName,UnitId,UnitName"));
                    $feedStatement = $sql->getSqlStringForSqlObject($select3);
                    $feedResult = $dbAdapter->query($feedStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    /*End PO 1*/
                    foreach($feedResult as $data){
                        /*Start PO 2*/
                        $select1 = $sql->select();
                        $select1->from(array("a"=>"VM_RequestTrans"))
                            ->columns(array('ResourceId', 'RequestTransId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))"), "CurQty"=>new Expression("1-1")),
                                array("CostcentreId"),array("CostCentreName"),array("RequestId"),array("DecisionId","RDecisionNo"))
                            ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array(), $select1::JOIN_INNER)
                            ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
                            ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("DecisionId", "RDecisionNo"), $select1::JOIN_INNER)
                            ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select1::JOIN_INNER);

                        $select1->where(array('f1.DecisionId'=>array_keys($res[$data['ResourceId']]),
                            'a.ResourceId'=>array($data['ResourceId'])));
                        $select1->group(new Expression("a.ResourceId,a.RequestTransId,f.RequestId,f1.DecisionId,f1.RDecisionNo"));

                        $select2 = $sql->select();
                        $select2->from(array("a"=>"VM_RequestTrans"))
                            ->columns(array('ResourceId', 'RequestTransId',"Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(h1.Qty) As Decimal(18,5))")),
                                array("CostcentreId"),array("CostCentreName"),array("RequestId"),array("DecisionId","RDecisionNo"))
                            ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array(), $select2::JOIN_INNER)
                            ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array("RequestId"), $select2::JOIN_INNER)
                            ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("DecisionId", "RDecisionNo"), $select2::JOIN_INNER)
                            ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select2::JOIN_INNER)
                            ->join(array("h"=>"MMS_POTrans"), "a.ResourceId=h.ResourceId", array(), $select2::JOIN_INNER)
                            ->join(array("h1"=>"MMS_IPDTrans"), "h.PoTransId=h1.POTransId and a.ResourceId=h1.ResourceId and a.RequestTransId=h1.ReqTransId and f.DecisionId=h1.DecisionId", array(), $select2::JOIN_INNER);

                        $select2->where(array('h.PORegisterId'=>$poRegId,
                            'f1.DecisionId'=>array_keys($res[$data['ResourceId']]),
                            'a.ResourceId'=>array($data['ResourceId']),
                            'f1.Approve'=>'Y'));
                        $select2->group(new Expression("a.ResourceId,a.RequestTransId,f.RequestId,f1.DecisionId,f1.RDecisionNo"));
                        $select2->combine($select1,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g"=>$select2))
                            ->columns(array('ResourceId','RequestTransId','RequestId','DecisionId','RDecisionNo',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
                        $select3->group(new Expression("ResourceId,RequestTransId,RequestId,DecisionId,RDecisionNo"));
                        $feedSubStatement = $sql->getSqlStringForSqlObject($select3);
                        $feedSubResult = $dbAdapter->query($feedSubStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        /*End PO 2*/

                        $data['dec'] = array();
                        foreach($feedSubResult as $data1){
                            $data1['cost'] = array();
                            $costCentre = $res[$data['ResourceId']][$data1['DecisionId']];
                            foreach($costCentre as $cid){
                                /*start PO 3*/
                                $costSelect = $sql->select();
                                $costSelect->from(array("a"=>"VM_RequestTrans"))
                                    ->columns(array("Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))"),"CurQty"=>new Expression("1-1")),
                                        array("CostcentreId"),array("CostCentreName"),array(),array(),array() )
                                    ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("CostcentreId"), $costSelect::JOIN_INNER)
                                    ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $costSelect::JOIN_INNER)
                                    ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array(), $costSelect::JOIN_INNER)
                                    ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array(), $costSelect::JOIN_INNER)
                                    ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $costSelect::JOIN_INNER);

                                $costSelect->where(array('f1.DecisionId'=>$data1['DecisionId'],
                                    'b.CostCentreId'=>$cid,
                                    'a.ResourceId'=>array($data['ResourceId']),
                                    'f1.Approve'=>'Y'));
                                $costSelect->group(array("b.CostCentreId", "c.CostCentreName"));


                                $costSelect2 = $sql->select();
                                $costSelect2->from(array("tt"=>"MMS_IPDProjTrans"))
                                    ->columns(array("Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(tt.Qty) As Decimal(18,5))"),"CostcentreId"=>new Expression("tt.CostCentreId")),
                                        array(),array("CostCentreName") )
                                    ->join(array("a"=>"MMS_POProjTrans"), "tt.POProjTransId=a.POProjTransId", array(), $costSelect2::JOIN_INNER)
                                    ->join(array("b"=>"MMS_POTrans"), "a.PoTransId=b.PoTransId", array(), $costSelect2::JOIN_INNER)
                                    ->join(array("c"=>"WF_OperationalCostCentre"), "tt.CostCentreId=c.CostCentreId", array("CostCentreName"), $costSelect2::JOIN_INNER);

                                $costSelect2->where(array('b.PORegisterId'=>$poRegId, 'tt.CostCentreId'=>$cid,
                                    'tt.ResourceId' => array($data['ResourceId']), 'tt.ReqTransId'=>array($data1['RequestTransId'])));
                                $costSelect2->group(array("tt.CostCentreId", "c.CostCentreName"));
                                $costSelect2->combine($costSelect,'Union ALL');

                                $select3 = $sql->select();
                                $select3->from(array("g"=>$costSelect2))
                                    ->columns(array('CostcentreId','CostCentreName',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
                                $select3->group(new Expression("CostcentreId,CostCentreName"));
                                $costStatement = $sql->getSqlStringForSqlObject($select3);
                                $costResult = $dbAdapter->query($costStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                /*End PO 3*/

                                foreach($costResult as $cdata){
                                    /*Start PO 4*/
                                    $select1 = $sql->select();
                                    $select1->from(array("a"=>"VM_RequestAnalTrans"))
                                        ->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))"),"CurQty"=>new Expression("1-1")),
                                            array("RequestTransId"),array("CostCentreId"),array("WbsName"),array("RequestId"),array("RDecisionNo"),array( ) )
                                        ->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $select1::JOIN_INNER)
                                        ->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $select1::JOIN_INNER)
                                        ->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WBSName"), $select1::JOIN_INNER)
                                        ->join(array("f"=>"VM_ReqDecTrans"), "c.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
                                        ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("RDecisionNo"), $select1::JOIN_INNER)
                                        ->join(array("g"=>"VM_ReqDecQtyAnalTrans"), "f.DecisionId=g.DecisionId And b.RequestTransId=g.ReqTransId And a.RequestAHTransId=g.ReqAHTransId", array( ), $select1::JOIN_INNER);

                                    $select1->where(array('b.ResourceId' => array($data1['ResourceId']),
                                        'f.DecisionId'=>$data1['DecisionId'],
                                        'c.CostCentreId'=>$cid,
                                        'b.RequestTransId'=>$data1['RequestTransId'] ));
                                    $select1->group(new Expression("a.RequestAHTransId,a.AnalysisId,a.ReqTransId,a.ResourceId,a.ItemId,b.RequestTransId,c.CostCentreId,d.WbsName,f.RequestId,f1.RDecisionNo"));


                                    $select2 = $sql->select();
                                    $select2->from(array("a"=>"VM_RequestAnalTrans"))
                                        ->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',"Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(a1.Qty) As Decimal(18,5))")),
                                            array("RequestTransId"),array("CostCentreId"),array("WbsName"),array("RequestId"),array("RDecisionNo"),array( ) )
                                        ->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $select2::JOIN_INNER)
                                        ->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $select2::JOIN_INNER)
                                        ->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WBSName"), $select2::JOIN_INNER)
                                        ->join(array("f"=>"VM_ReqDecTrans"), "c.RequestId=f.RequestId", array("RequestId"), $select2::JOIN_INNER)
                                        ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("RDecisionNo"), $select2::JOIN_INNER)
                                        ->join(array("g"=>"VM_ReqDecQtyAnalTrans"), "f.DecisionId=g.DecisionId And b.RequestTransId=g.ReqTransId And a.RequestAHTransId=g.ReqAHTransId", array( ), $select2::JOIN_INNER)
                                        ->join(array("a1"=>"MMS_IPDAnalTrans"), "a.RequestAHTransId=a1.ReqAHTransId and a.ResourceId=a1.ResourceId and a.AnalysisId=a1.AnalysisId", array(), $select2::JOIN_INNER)
                                        ->join(array("a2"=>"MMS_POAnalTrans"), "a1.POAHTransId=a2.POAnalTransId", array(), $select2::JOIN_INNER)
                                        ->join(array("b1"=>"MMS_IPDProjTrans"), "a2.POProjTransId=b1.POProjTransId and b1.CostCentreId=c.CostCentreId", array(), $select2::JOIN_INNER)
                                        ->join(array("a3"=>"MMS_POProjTrans"), "a2.POProjTransId=a3.POProjTransId", array(), $select2::JOIN_INNER)
                                        ->join(array("a5"=>"MMS_IPDTrans"), "a3.POTransId=a5.POTransId and a.ReqTransId=a5.ReqTransId and f.DecisionId=a5.DecisionId and a.ResourceId=a5.ResourceId", array(), $select2::JOIN_INNER)
                                        ->join(array("a4"=>"MMS_POTrans"), "a3.POTransId=a4.PoTransId", array(), $select2::JOIN_INNER);

                                    $select2->where(array('a4.PORegisterId'=>$poRegId, 'b.ResourceId' => array($data1['ResourceId']),
                                        'f.DecisionId'=>$data1['DecisionId'],
                                        'c.CostCentreId'=>$cid,
                                        'b.RequestTransId'=>$data1['RequestTransId'],
                                        'f1.Approve'=>'Y'));
                                    $select2->group(new Expression("a.RequestAHTransId,a.AnalysisId,a.ReqTransId,a.ResourceId,a.ItemId,b.RequestTransId,c.CostCentreId,d.WbsName,f.RequestId,f1.RDecisionNo"));

                                    $select2->combine($select1,'Union ALL');

                                    $select3 = $sql->select();
                                    $select3->from(array("g1"=>$select2))
                                        ->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId','RequestTransId','CostCentreId','WbsName','RequestId','RDecisionNo',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
                                    $select3->group(new Expression("RequestAHTransId,AnalysisId,ReqTransId,ResourceId,ItemId,RequestTransId,CostCentreId,WbsName,RequestId,RDecisionNo"));
                                    $feedSub1Statement = $sql->getSqlStringForSqlObject($select3);
                                    $cdata['wbsname'] = $dbAdapter->query($feedSub1Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                    /*End PO 4*/
                                    array_push($data1['cost'], $cdata);
                                }
                            }
                            array_push($data['dec'], $data1);
                        }
                        array_push($resp, $data);
                    }
                } else if($postParam["mode"] == 'vendorSelect'){
                    /*vendor select change*/
                    $select = $sql->select();
                    $select->from(array('a' => 'Vendor_Branch'))
                        ->columns(array('BranchId', 'BranchName'))
                        ->where->like('a.VendorId', $postParam['cid']);

                    $statement = $sql->getSqlStringForSqlObject($select);
                    $resp['branch']   = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    /*TransportMode*/
                    $select1 = $sql->select();
                    $select1->from(array('a' => 'Vendor_TransportMaster'))
                        ->columns(array('TransportId', 'TransportName'), array("VendorId"))
                        ->join(array('b'=>'Vendor_Transport'), "a.TransportId=b.TransportId", array("VendorId"), $select1::JOIN_LEFT)
                        ->where->like('b.VendorId', $postParam['cid']);

                    $statement1 = $sql->getSqlStringForSqlObject($select1);
                    $resp['transport']   = $dbAdapter->query($statement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    /*selected item Service*/
                    $selectPOservice = $sql->select();
                    $selectPOservice->from(array("a"=>"MMS_POLogisticService"))
                        ->columns(array("ServiceId"), array("ServiceName","Sel"=>new Expression("1")))
                        ->join(array("c"=>"Vendor_ServiceMaster"), "a.ServiceId=c.ServiceId", array("ServiceName","Sel"=>new Expression("1")), $selectPOservice::JOIN_INNER)
                        ->join(array('b'=>'Vendor_ServiceTrans'), "c.ServiceId=b.ServiceId", array(), $selectPOservice::JOIN_LEFT)
                        ->where(array('a.PORegisterId'=>$postParam['poregId']
                        ,'b.VendorId'=>$postParam['cid'] ));

                    $Subselect2= $sql->select();
                    $Subselect2->from("MMS_POLogisticService")
                        ->columns(array("ServiceId"))
                        ->where(array('PORegisterId'=>$postParam['poregId'] ));

                    $selectservice = $sql->select();
                    $selectservice->from(array("a"=>'Vendor_ServiceMaster'))
                        ->columns(array("ServiceId","ServiceName","Sel"=>new Expression("1-1")))
                        ->join(array('b'=>'Vendor_ServiceTrans'), "a.ServiceId=b.ServiceId", array(), $selectservice::JOIN_LEFT)
                        ->where->notIn('a.ServiceId',$Subselect2);
                    $selectservice->where(array('b.VendorId'=>$postParam['cid']));

                    $selectservice->combine($selectPOservice,'Union ALL');
                    $serviceStatement = $sql->getSqlStringForSqlObject($selectservice);
                    $resp['service']  = $dbAdapter->query($serviceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $selectdistri = $sql->select();
                    $selectdistri->from(array('a' => 'Vendor_Master'))
                        ->columns(array('VendorId', 'VendorName'))
                        ->join(array('b'=>'Vendor_SupplierDet'), "a.VendorId=b.SupplierVendorId", array("SupplierVendorId"), $selectdistri::JOIN_INNER)
                        ->where->like('b.VendorId', $postParam['cid']);

                    $statement3 = $sql->getSqlStringForSqlObject($selectdistri);
                    $resp['distributor']   = $dbAdapter->query($statement3, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
            }
            $this->_view->setTerminal(true);
            $response->setContent(json_encode($resp));
            return $response;
        }
        else if($request->isPost()){
            $connection = $dbAdapter->getDriver()->getConnection();
            $connection->beginTransaction();
            try{
                $postParam = $request->getPost();
                $poregId=$postParam["poregId"];
                /*delete MMS_POLogistic*/
                $select = $sql->delete();
                $select->from("MMS_POLogistic")
                    ->where(array('PORegisterId'=>$poregId));
                $POLogisticStatement = $sql->getSqlStringForSqlObject($select);
                $register1 = $dbAdapter->query($POLogisticStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_POLogisticService*/
                $select = $sql->delete();
                $select->from("MMS_POLogisticService")
                    ->where(array('PORegisterId'=>$poregId));
                $POLogisticServiceStatement = $sql->getSqlStringForSqlObject($select);
                $register2 = $dbAdapter->query($POLogisticServiceStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_POLogisticType*/
                $select = $sql->delete();
                $select->from("MMS_POLogisticType")
                    ->where(array('PORegisterId'=>$poregId));
                $POLogisticTypeStatement = $sql->getSqlStringForSqlObject($select);
                $register3 = $dbAdapter->query($POLogisticTypeStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_PODistributorTrans*/
                $select = $sql->delete();
                $select->from("MMS_PODistributorTrans")
                    ->where(array('PORegisterId'=>$poregId));
                $PODistributorTransStatement = $sql->getSqlStringForSqlObject($select);
                $register4 = $dbAdapter->query($PODistributorTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_POPaymentTerms*/
                $select = $sql->delete();
                $select->from("MMS_POPaymentTerms")
                    ->where(array('PORegisterId'=>$poregId));
                $POPaymentTermsStatement = $sql->getSqlStringForSqlObject($select);
                $register5 = $dbAdapter->query($POPaymentTermsStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*Update VM_RequestTrans*/
                $select1   = $sql->select();
                $select1->from(array("a"=>"MMS_IPDTrans"))
                    ->columns(array("ReqTransId","Qty"))
                    ->join(array('b'=>'MMS_POTrans'), "a.POTransId=b.PoTransId", array(), $select1::JOIN_INNER)
                    ->where(array('b.PORegisterId' => $poregId , 'a.Qty>0' ));
                $POStatement = $sql->getSqlStringForSqlObject($select1);
                $POReqResult = $dbAdapter->query($POStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($POReqResult) > 0){
                    foreach($POReqResult as $POFill){
                        $iReqTrsna_Id=$POFill['ReqTransId'];
                        $iQty=$POFill['Qty'];

                        $select = $sql->update();
                        $select->table('VM_RequestTrans');
                        $select->set(array(
                            'IndentQty' => new Expression('IndentQty -'.$iQty),
                            'BalQty' => new Expression('BalQty +'.$iQty)
                        ));
                        $select->where(array('RequestTransId'=>$iReqTrsna_Id));
                        $ReqTransupdateStatement = $sql->getSqlStringForSqlObject($select);
                        $dbAdapter->query($ReqTransupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }
                /*Update VM_RequestAnalTrans*/
                $select1   = $sql->select();
                $select1->from(array("a"=>"MMS_IPDAnalTrans"))
                    ->columns(array("ReqAHTransId","Qty"))
                    ->join(array('b'=>'MMS_POAnalTrans'), "a.POAHTransId=b.POAnalTransId", array(), $select1::JOIN_INNER)
                    ->join(array('c'=>'MMS_POProjTrans'), "b.POProjTransId=c.POProjTransId", array(), $select1::JOIN_INNER)
                    ->join(array('d'=>'MMS_POTrans'), "c.POTransId=d.PoTransId", array(), $select1::JOIN_INNER)
                    ->where(array('d.PORegisterId' => $poregId , 'a.Qty>0' ));
                $POAnalStatement = $sql->getSqlStringForSqlObject($select1);
                $POAnalReqResult = $dbAdapter->query($POAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(count($POAnalReqResult) > 0){
                    foreach($POAnalReqResult as $POAnalFill){
                        $iReqAnalTrsna_Id=$POAnalFill['ReqAHTransId'];
                        $iQty=$POAnalFill['Qty'];

                        $select = $sql->update();
                        $select->table('VM_RequestAnalTrans');
                        $select->set(array(
                            'IndentQty' => new Expression('IndentQty -'.$iQty),
                            'BalQty' => new Expression('BalQty +'.$iQty)
                        ));
                        $select->where(array('RequestAHTransId'=>$iReqAnalTrsna_Id));
                        $requestAnalupdateStatement = $sql->getSqlStringForSqlObject($select);
                        $dbAdapter->query($requestAnalupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                }

                /*delete MMS_IPDAnalTrans*/
                $subQuery   = $sql->select();
                $subQuery->from(array("a"=>"MMS_POAnalTrans"))
                    ->columns(array("POAnalTransId"))
                    ->join(array("b"=>"MMS_POProjTrans"), "a.POProjTransId=b.POProjTransId", array(), $subQuery::JOIN_INNER)
                    ->join(array("c"=>"MMS_POTrans"), "b.POTransId=c.PoTransId", array(), $subQuery::JOIN_INNER);
                $subQuery->where(array('c.PORegisterId'=>$poregId));

                $select = $sql->delete();
                $select->from('MMS_IPDAnalTrans')
                    ->where->expression('POAHTransId IN ?', array($subQuery));
                $DelIPDAnalTransStatement = $sql->getSqlStringForSqlObject($select);
                $DelIPDAnalTransregister = $dbAdapter->query($DelIPDAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_POAnalTrans*/
                $subQuery   = $sql->select();
                $subQuery->from(array("a"=>"MMS_POProjTrans"))
                    ->columns(array("POProjTransId"))
                    ->join(array("c"=>"MMS_POTrans"), "a.POTransId=c.PoTransId", array(), $subQuery::JOIN_INNER);
                $subQuery->where(array('c.PORegisterId'=>$poregId));

                $select = $sql->delete();
                $select->from('MMS_POAnalTrans')
                    ->where->expression('POProjTransId IN ?', array($subQuery));
                $DelPOAnalTransStatement = $sql->getSqlStringForSqlObject($select);
                $DelPOAnalTransregister = $dbAdapter->query($DelPOAnalTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_IPDProjTrans*/
                $subQuery   = $sql->select();
                $subQuery->from(array("a"=>"MMS_POProjTrans"))
                    ->columns(array("POProjTransId"))
                    ->join(array("c"=>"MMS_POTrans"), "a.POTransId=c.PoTransId", array(), $subQuery::JOIN_INNER);
                $subQuery->where(array('c.PORegisterId'=>$poregId));

                $select = $sql->delete();
                $select->from('MMS_IPDProjTrans')
                    ->where->expression('POProjTransId IN ?', array($subQuery));
                $DelIPDProjTransStatement = $sql->getSqlStringForSqlObject($select);
                $DelIPDProjTransregister = $dbAdapter->query($DelIPDProjTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_POProjTrans*/
                $subQuery   = $sql->select();
                $subQuery->from(array("a"=>"MMS_POTrans"))
                    ->columns(array("POTransId"));
                $subQuery->where(array('a.PORegisterId'=>$poregId));

                $select = $sql->delete();
                $select->from('MMS_POProjTrans')
                    ->where->expression('POTransId IN ?', array($subQuery));
                $DelPOProjTransStatement = $sql->getSqlStringForSqlObject($select);
                $DelPOProjTransregister = $dbAdapter->query($DelPOProjTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_IPDTrans*/
                $subQuery   = $sql->select();
                $subQuery->from(array("a"=>"MMS_POTrans"))
                    ->columns(array("POTransId"));
                $subQuery->where(array('a.PORegisterId'=>$poregId));

                $select = $sql->delete();
                $select->from('MMS_IPDTrans')
                    ->where->expression('POTransId IN ?', array($subQuery));
                $DelIPDTransStatement = $sql->getSqlStringForSqlObject($select);
                $DelIPDTransregister = $dbAdapter->query($DelIPDTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*delete MMS_POTrans*/
                $select = $sql->delete();
                $select->from('MMS_POTrans')
                    ->where(array('PORegisterId'=>$poregId));
                $DelPOTransStatement = $sql->getSqlStringForSqlObject($select);
                $DelPOTransregister = $dbAdapter->query($DelPOTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                /*Update PORegister*/
                $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"");
                $voucher='';
                if($vNo['genType']){
                    $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"I");
                    $voucher = $vNo['voucherNo'];
                } else {
                    $voucher = $postParam['voucherNo'];
                }
                $registerUpdate = $sql->update();
                $registerUpdate->table('MMS_PORegister');
                $registerUpdate->set(array('PODate' => date('Y-m-d', strtotime($postParam['purchase_date'])), 'PONo' => $voucher, 'VendorId' => $postParam["vendorId"],
                    'BranchId' => $postParam["Branch"], 'CurrencyId' => $postParam["currency"], 'BranchTransId' => $postParam["Branchcontactperson"],
                    'Address1' => $postParam["addressline1"],'Address2' => $postParam["addressline2"], 'Address3' => $postParam["addressline3"],
                    'City' => $postParam["city"], 'Pincode' => $postParam["pincode"]
                ));
                $registerUpdate->where(array('PORegisterId' => $poregId));
                $registerStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                $POLogisticInsert = $sql->insert('MMS_POLogistic');
                $POLogisticInsert->values(array("PORegisterId"=>$poregId,"ProviderId"=>$postParam["serviceprovider"], "VehicleId"=>$postParam["transportmode"]));
                $registerStatement1 = $sql->getSqlStringForSqlObject($POLogisticInsert);
                $registerResults1 = $dbAdapter->query($registerStatement1, $dbAdapter::QUERY_MODE_EXECUTE);

                foreach(array_filter(explode(",", $postParam['gridServiceId'])) as $sid){
                    $POLogisticserviceInsert = $sql->insert('MMS_POLogisticService');
                    $POLogisticserviceInsert->values(array("PORegisterId"=>$poregId,"ServiceId"=>$sid));
                    $registerStatement2 = $sql->getSqlStringForSqlObject($POLogisticserviceInsert);
                    $dbAdapter->query($registerStatement2, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach(array_filter(explode(",", $postParam['gridLogisticstypeId'])) as $Logisticsid){
                    $POLogisticTypeInsert = $sql->insert('MMS_POLogisticType');
                    $POLogisticTypeInsert->values(array("PORegisterId"=>$poregId,"LogisticTypeId"=>$Logisticsid));
                    $registerStatement3 = $sql->getSqlStringForSqlObject($POLogisticTypeInsert);
                    $dbAdapter->query($registerStatement3, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach(array_filter(explode(",", $postParam['distributorListId'])) as $Distributorid){
                    $DistributorInsert = $sql->insert('MMS_PODistributorTrans');
                    $DistributorInsert->values(array("PORegisterId"=>$poregId,"VendorId"=>$Distributorid));
                    $registerStatement4 = $sql->getSqlStringForSqlObject($DistributorInsert);
                    $dbAdapter->query($registerStatement4, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                foreach(array_filter(explode(",", $postParam['gridtermListId'])) as $termsid){
                    $TermListInsert = $sql->insert('MMS_POPaymentTerms');
                    $TermListInsert->values(array("PORegisterId"=>$poregId,"TermsId"=>$termsid,
                        "ValueFromNet"=>$postParam['ValueFromNet_'.$termsid],"Per"=>$postParam['Per_'.$termsid],
                        "Value"=>$postParam['Value_'.$termsid],"Period"=>$postParam['Period_'.$termsid],
                        "TDate"=>$postParam['Date_'.$termsid],"TString"=>$postParam['Str_'.$termsid]));
                    $registerStatement5 = $sql->getSqlStringForSqlObject($TermListInsert);
                    $dbAdapter->query($registerStatement5, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                $json = json_decode($postParam['hidjson'], true);
                /*Resource Id*/
                foreach($json as $resKey=>$resVal){
                    $resId=$resKey;
                    /*POTrans*/
                    $requestInsert = $sql->insert('MMS_POTrans');
                    $requestInsert->values(array("PORegisterId"=>$poregId, "UnitId"=>$postParam['unitId_'.$resId],
                        "ResourceId"=>$resId, "POQty"=>$postParam['transQuantity_'.$resId]));
                    $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                    $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $POTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                    /*Request decision*/
                    foreach($resVal as $decKey=>$decVal){
                        $decId=$decKey;
                        /*update RequestTrans*/
                        $select = $sql->update('VM_RequestTrans');
                        $select->set(array('IndentQty' => new Expression('IndentQty +'.$postParam['decisionQty_'.$resId.'_'.$decId]),
                            'BalQty' => new Expression('BalQty -'.$postParam['decisionQty_'.$resId.'_'.$decId])
                        ));
                        $select->where(array('RequestTransId'=>$postParam['reqTransId_'.$resId.'_'.$decId]));
                        $requestHiddenupdateStatement = $sql->getSqlStringForSqlObject($select);
                        $dbAdapter->query($requestHiddenupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        /*IPDTrans*/
                        $requestInsert = $sql->insert('MMS_IPDTrans');
                        $requestInsert->values(array("POTransId"=>$POTransId, "ReqTransId"=>$postParam['reqTransId_'.$resId.'_'.$decId], "DecisionId"=>$decId,
                            "ResourceId"=>$resId, "Qty"=>$postParam['decisionQty_'.$resId.'_'.$decId]));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        /*MMS_POProjTrans*/
                        $requestInsert = $sql->insert('MMS_POProjTrans');
                        $requestInsert->values(array("POTransId"=>$POTransId, "UnitId"=>$postParam['unitId_'.$resId],
                            "ResourceId"=>$resId, "POQty"=>$postParam['decisionQty_'.$resId.'_'.$decId]));
                        $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                        $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $POProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        foreach($decVal as $costKey=>$costVal){
                            $costId = $costKey;
                            /*MMS_IPDProjTrans*/
                            $requestInsert = $sql->insert('MMS_IPDProjTrans');
                            $requestInsert->values(array("POProjTransId"=>$POProjTransId, "ReqTransId"=>$postParam['reqTransId_'.$resId.'_'.$decId], "CostcentreId"=>$costId,
                                "ResourceId"=>$resId, "UnitId"=>$postParam['unitId_'.$resId], "Qty"=>$postParam['costQuantity_'.$resId.'_'.$decId.'_'.$costId], 'IPDTRansId'=>$IPDTransId));
                            $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                            $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            /*MMS_POAnalTrans*/
                            $requestInsert = $sql->insert('MMS_POAnalTrans');
                            $requestInsert->values(array("POProjTransId"=>$POProjTransId, /*"AnalysisId"=>$postParam['AnalysisId_'.$i_TransId],*/ "UnitId"=>$postParam['unitId_'.$resId],
                                "ResourceId"=>$resId, "POQty"=>$postParam['costQuantity_'.$resId.'_'.$decId.'_'.$costId]));
                            $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                            $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $POAnalTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                            foreach($costVal as $wbs){
                                /*update RequestAnalTrans*/
                                $select = $sql->update();
                                $select->table('VM_RequestAnalTrans');
                                $select->set(array(
                                    'IndentQty' => new Expression('IndentQty +'.$postParam['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs]),
                                    'BalQty' => new Expression('BalQty -'.$postParam['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs])
                                ));
                                $select->where(array('RequestAHTransId'=>$wbs));
                                $requestHiddenupdateStatement = $sql->getSqlStringForSqlObject($select);
                                $dbAdapter->query($requestHiddenupdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                /*MMS_IPDAnalTrans*/
                                $requestInsert = $sql->insert('MMS_IPDAnalTrans');
                                $requestInsert->values(array("POAHTransId"=>$POAnalTransId, "ReqAHTransId"=>$wbs, "AnalysisId"=>$postParam['analysisId_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs],
                                    "ResourceId"=>$resId, "Qty"=>$postParam['wbsQuantity_'.$resId.'_'.$decId.'_'.$costId.'_'.$wbs], 'IPDProjTransId'=>$IPDProjTransId));
                                $requestStatement = $sql->getSqlStringForSqlObject($requestInsert);
                                $dbAdapter->query($requestStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                    }
                }
                $connection->commit();
            }
            catch(PDOException $e){
                $connection->rollback();
                print "Error!: " . $e->getMessage() . "</br>";
            }
        }

        $poRegId = $this->params()->fromRoute('regid');
        $resp = array();
        $allSelect = $sql->select();
        $allSelect->from(array("a" => "MMS_POTrans"))
            ->columns(array())
            ->join(array("b"=>"MMS_IPDTrans"), "a.PoTransId=b.POTransId", array("ResourceId", "DecisionId"), $allSelect::JOIN_INNER)
            ->join(array("c"=>"MMS_POProjTrans"), "a.PoTransId=c.POTransId", array(), $allSelect::JOIN_INNER)
            ->join(array("d"=>"MMS_IPDProjTrans"), "c.POProjTransId=d.POProjTransId", array("CostCentreId"), $allSelect::JOIN_INNER)
            ->where(array("a.PORegisterId" => $poRegId));
        $allStatement = $sql->getSqlStringForSqlObject($allSelect);
        $allResult = $dbAdapter->query($allStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $resId = array_column($allResult, 'ResourceId');
        $decId = array_column($allResult, 'DecisionId');
        $costId = array_column($allResult, 'CostCentreId');



        $select1 = $sql->select();
        $select1->from(array("a"=>"VM_RequestTrans"))
            ->columns(array('ResourceId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))")),
                array("CurQty"=>new Expression("1-1")),array(),array("Code", "ResourceName", "UnitId"),array("UnitName"),array(),array(),array( ) )
            ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("CurQty"=>new Expression("1-1")), $select1::JOIN_INNER)
            ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array(), $select1::JOIN_LEFT)
            ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("Code", "ResourceName", "UnitId"), $select1::JOIN_INNER)
            ->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select1::JOIN_LEFT)
            ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array(), $select1::JOIN_INNER)
            ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array(), $select1::JOIN_INNER)
            ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select1::JOIN_INNER);
        $select1->where(array('f1.DecisionId'=>$decId,
            'b.CostCentreId'=>$costId,
            'a.ResourceId'=>$resId,
            'f1.Approve'=>'Y'));
        $select1->group(new Expression("a.ResourceId,d.Code,d.ResourceName,d.UnitId,e.UnitName"));


        $select2 = $sql->select();
        $select2->from(array("a"=>"VM_RequestTrans"))
            ->columns(array("ResourceId"=>new Expression("a.ResourceId"),"Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(h1.Qty) As Decimal(18,5))")),
                array(),array(),array("Code", "ResourceName", "UnitId"),array("UnitName"),array(),array(),array( ) )
            ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array(), $select2::JOIN_INNER)
            ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array(), $select2::JOIN_LEFT)
            ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("Code", "ResourceName", "UnitId"), $select2::JOIN_INNER)
            ->join(array("e"=>"Proj_UOM"), "d.UnitId=e.UnitId", array("UnitName"), $select2::JOIN_LEFT)
            ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array(), $select2::JOIN_INNER)
            ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array(), $select2::JOIN_INNER)
            ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select2::JOIN_INNER)
            ->join(array("h"=>"MMS_POTrans"), "a.ResourceId=h.ResourceId", array(), $select2::JOIN_INNER)
            ->join(array("h1"=>"MMS_IPDTrans"), "h.PoTransId=h1.POTransId and a.ResourceId=h1.ResourceId and a.RequestTransId=h1.ReqTransId and f.DecisionId=h1.DecisionId", array(), $select2::JOIN_INNER);
        $select2->where(array('h.PORegisterId'=>$poRegId,'f1.Approve'=>'Y'));
        $select2->group(new Expression("a.ResourceId,d.Code,d.ResourceName,d.UnitId,e.UnitName"));

        $select2->combine($select1,'Union ALL');

        $select3 = $sql->select();
        $select3->from(array("g"=>$select2))
            ->columns(array('ResourceId','Code','ResourceName','UnitId','UnitName',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
        $select3->group(new Expression("ResourceId,Code,ResourceName,UnitId,UnitName"));
        $feedStatement = $sql->getSqlStringForSqlObject($select3);
        $resResult = $dbAdapter->query($feedStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $resJson = array();
        $decArray = array();
        $costArray = array();
        foreach($resResult as $res){
            $res['decision'] = array();

            $decSelect = $sql->select();
            $decSelect->from(array("a" => "MMS_POTrans"))
                ->columns(array())
                ->join(array("b"=>"MMS_IPDTrans"), "a.PoTransId=b.POTransId", array("DecisionId"), $decSelect::JOIN_INNER)
                ->where(array("a.PORegisterId" => $poRegId, "b.ResourceId" => $res['ResourceId']));

            $decStatement = $sql->getSqlStringForSqlObject($decSelect);
            $decResult = $dbAdapter->query($decStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $decisionId = array_column($decResult, 'DecisionId');

            $select1 = $sql->select();
            $select1->from(array("a"=>"VM_RequestTrans"))
                ->columns(array('ResourceId', 'RequestTransId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))"), "CurQty"=>new Expression("1-1")),
                    array("CostcentreId"),array("CostCentreName"),array("RequestId"),array("DecisionId","RDecisionNo"))
                ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array(), $select1::JOIN_INNER)
                ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
                ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("DecisionId", "RDecisionNo"), $select1::JOIN_INNER)
                ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select1::JOIN_INNER);

            $select1->where(array('f1.DecisionId'=>$decisionId,
                'a.ResourceId'=>array($res['ResourceId']),
                'f1.Approve'=>'Y'));
            $select1->group(new Expression("a.ResourceId,a.RequestTransId,f.RequestId,f1.DecisionId,f1.RDecisionNo"));

            $select2 = $sql->select();
            $select2->from(array("a"=>"VM_RequestTrans"))
                ->columns(array('ResourceId', 'RequestTransId',"Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(h1.Qty) As Decimal(18,5))")),
                    array("CostcentreId"),array("CostCentreName"),array("RequestId"),array("DecisionId","RDecisionNo"))
                ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array(), $select2::JOIN_INNER)
                ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array("RequestId"), $select2::JOIN_INNER)
                ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("DecisionId", "RDecisionNo"), $select2::JOIN_INNER)
                ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $select2::JOIN_INNER)
                ->join(array("h"=>"MMS_POTrans"), "a.ResourceId=h.ResourceId", array(), $select2::JOIN_INNER)
                ->join(array("h1"=>"MMS_IPDTrans"), "h.PoTransId=h1.POTransId and a.ResourceId=h1.ResourceId and a.RequestTransId=h1.ReqTransId and f.DecisionId=h1.DecisionId", array(), $select2::JOIN_INNER);

            $select2->where(array('h.PORegisterId'=>$poRegId, 'a.ResourceId'=>array($res['ResourceId']),'f1.Approve'=>'Y'));
            $select2->group(new Expression("a.ResourceId,a.RequestTransId,f.RequestId,f1.DecisionId,f1.RDecisionNo"));

            $select2->combine($select1,'Union ALL');

            $select3 = $sql->select();
            $select3->from(array("g"=>$select2))
                ->columns(array('ResourceId','RequestTransId','RequestId','DecisionId','RDecisionNo',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
            $select3->group(new Expression("ResourceId,RequestTransId,RequestId,DecisionId,RDecisionNo"));
            $feedSubStatement = $sql->getSqlStringForSqlObject($select3);
            $decResult = $dbAdapter->query($feedSubStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($decResult as $dec){
                $dec['cost'] = array();
                $projSelect = $sql->select();
                $projSelect->from(array("a" => "MMS_POTrans"))
                    ->columns(array())
                    ->join(array("b"=>"MMS_IPDTrans"), "a.PoTransId=b.POTransId", array(), $projSelect::JOIN_INNER)
                    ->join(array("c"=>"MMS_POProjTrans"), "a.PoTransId=c.POTransId", array(), $projSelect::JOIN_INNER)
                    ->join(array("d"=>"MMS_IPDProjTrans"), "c.POProjTransId=d.POProjTransId", array("CostCentreId"), $projSelect::JOIN_INNER)
                    ->where(array("a.PORegisterId" => $poRegId, "b.ResourceId" => $res['ResourceId'], "b.DecisionId" => $dec['DecisionId']));
                $projStatement = $sql->getSqlStringForSqlObject($projSelect);
                $projResult = $dbAdapter->query($projStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach($projResult as $cid){
                    $costSelect = $sql->select();
                    $costSelect->from(array("a"=>"VM_RequestTrans"))
                        ->columns(array("Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))"),"CurQty"=>new Expression("1-1")),
                            array("CostcentreId"),array("CostCentreName"),array(),array(),array() )
                        ->join(array("b"=>"VM_RequestRegister"), "a.RequestId=b.RequestId", array("CostcentreId"), $costSelect::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"), "b.CostCentreId=c.CostCentreId", array("CostCentreName"), $costSelect::JOIN_INNER)
                        ->join(array("f"=>"VM_ReqDecTrans"), "b.RequestId=f.RequestId", array(), $costSelect::JOIN_INNER)
                        ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array(), $costSelect::JOIN_INNER)
                        ->join(array("g"=>"VM_ReqDecQtyTrans"), "f.DecisionId=g.DecisionId And a.RequestTransId=g.ReqTransId", array(), $costSelect::JOIN_INNER);

                    $costSelect->where(array('f1.DecisionId'=>$dec['DecisionId'],
                        'b.CostCentreId'=>$cid,
                        'a.ResourceId'=>array($res['ResourceId']),
                        'f1.Approve'=>'Y'));
                    $costSelect->group(array("b.CostCentreId", "c.CostCentreName"));


                    $costSelect2 = $sql->select();
                    $costSelect2->from(array("tt"=>"MMS_IPDProjTrans"))
                        ->columns(array("Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(tt.Qty) As Decimal(18,5))"),"CostcentreId"=>new Expression("tt.CostCentreId")),
                            array(),array("CostCentreName"))
                        ->join(array("a"=>"MMS_POProjTrans"), "tt.POProjTransId=a.POProjTransId", array(), $costSelect2::JOIN_INNER)
                        ->join(array("b"=>"MMS_POTrans"), "a.PoTransId=b.PoTransId", array(), $costSelect2::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"), "tt.CostCentreId=c.CostCentreId", array("CostCentreName"), $costSelect2::JOIN_INNER);

                    $costSelect2->where(array('b.PORegisterId'=>$poRegId, 'tt.ResourceId' => array($dec['ResourceId']), 'tt.ReqTransId'=>array($dec['RequestTransId'])));
                    $costSelect2->group(array("tt.CostCentreId", "c.CostCentreName"));

                    $costSelect2->combine($costSelect,'Union ALL');

                    $select3 = $sql->select();
                    $select3->from(array("g"=>$costSelect2))
                        ->columns(array('CostcentreId','CostCentreName',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
                    $select3->group(new Expression("CostcentreId,CostCentreName"));
                    $costStatement = $sql->getSqlStringForSqlObject($select3);
                    $costResult = $dbAdapter->query($costStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $resJson[$res['ResourceId']][$dec['DecisionId']]['cost'] = array_column($costResult, 'CostcentreId');
                    foreach($costResult as $cost){
                        $select1 = $sql->select();
                        $select1->from(array("a"=>"VM_RequestAnalTrans"))
                            ->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',"Quantity"=>new Expression("CAST(Sum(a.IndentApproveQty-a.IndentQty) As Decimal(18,5))"),"CurQty"=>new Expression("1-1")),
                                array("RequestTransId"),array("CostCentreId"),array("WbsName"),array("RequestId"),array("RDecisionNo"),array( ) )
                            ->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $select1::JOIN_INNER)
                            ->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $select1::JOIN_INNER)
                            ->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WBSName"), $select1::JOIN_INNER)
                            ->join(array("f"=>"VM_ReqDecTrans"), "c.RequestId=f.RequestId", array("RequestId"), $select1::JOIN_INNER)
                            ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("RDecisionNo"), $select1::JOIN_INNER)
                            ->join(array("g"=>"VM_ReqDecQtyAnalTrans"), "f.DecisionId=g.DecisionId And b.RequestTransId=g.ReqTransId And a.RequestAHTransId=g.ReqAHTransId", array( ), $select1::JOIN_INNER);

                        $select1->where(array('b.ResourceId' => array($res['ResourceId']),
                            'f.DecisionId'=>$dec['DecisionId'],
                            'c.CostCentreId'=>$cid,
                            'b.RequestTransId'=>$dec['RequestTransId'] ));
                        $select1->group(new Expression("a.RequestAHTransId,a.AnalysisId,a.ReqTransId,a.ResourceId,a.ItemId,b.RequestTransId,c.CostCentreId,d.WbsName,f.RequestId,f1.RDecisionNo"));
                        $select2 = $sql->select();
                        $select2->from(array("a"=>"VM_RequestAnalTrans"))
                            ->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId',"Quantity"=>new Expression("1-1"),"CurQty"=>new Expression("CAST(Sum(a1.Qty) As Decimal(18,5))")),
                                array("RequestTransId"),array("CostCentreId"),array("WbsName"),array("RequestId"),array("RDecisionNo"),array( ) )
                            ->join(array("b"=>"VM_RequestTrans"), "a.ReqTransId=b.RequestTransId", array("RequestTransId"), $select2::JOIN_INNER)
                            ->join(array("c"=>"VM_RequestRegister"), "b.RequestId=c.RequestId", array("CostCentreId"), $select2::JOIN_INNER)
                            ->join(array("d"=>"Proj_WBSMaster"), "a.AnalysisId=d.WBSId", array("WBSName"), $select2::JOIN_INNER)
                            ->join(array("f"=>"VM_ReqDecTrans"), "c.RequestId=f.RequestId", array("RequestId"), $select2::JOIN_INNER)
                            ->join(array("f1"=>"VM_RequestDecision"), "f.DecisionId=f1.DecisionId", array("RDecisionNo"), $select2::JOIN_INNER)
                            ->join(array("g"=>"VM_ReqDecQtyAnalTrans"), "f.DecisionId=g.DecisionId And b.RequestTransId=g.ReqTransId And a.RequestAHTransId=g.ReqAHTransId", array( ), $select2::JOIN_INNER)
                            ->join(array("a1"=>"MMS_IPDAnalTrans"), "a.RequestAHTransId=a1.ReqAHTransId and a.ResourceId=a1.ResourceId and a.AnalysisId=a1.AnalysisId", array(), $select2::JOIN_INNER)
                            ->join(array("a2"=>"MMS_POAnalTrans"), "a1.POAHTransId=a2.POAnalTransId", array(), $select2::JOIN_INNER)
                            ->join(array("b1"=>"MMS_IPDProjTrans"), "a2.POProjTransId=b1.POProjTransId and b1.CostCentreId=c.CostCentreId", array(), $select2::JOIN_INNER)
                            ->join(array("a3"=>"MMS_POProjTrans"), "a2.POProjTransId=a3.POProjTransId", array(), $select2::JOIN_INNER)
                            ->join(array("a5"=>"MMS_IPDTrans"), "a3.POTransId=a5.POTransId and a.ReqTransId=a5.ReqTransId and f.DecisionId=a5.DecisionId and a.ResourceId=a5.ResourceId", array(), $select2::JOIN_INNER)
                            ->join(array("a4"=>"MMS_POTrans"), "a3.POTransId=a4.PoTransId", array(), $select2::JOIN_INNER);

                        $select2->where(array('a4.PORegisterId'=>$poRegId,'f1.Approve'=>'Y', 'b.ResourceId' => array($res['ResourceId']),'f.DecisionId'=>$dec['DecisionId'],
                            'c.CostCentreId'=>$cost['CostcentreId'], 'b.RequestTransId'=>$dec['RequestTransId']));
                        $select2->group(new Expression("a.RequestAHTransId,a.AnalysisId,a.ReqTransId,a.ResourceId,a.ItemId,b.RequestTransId,c.CostCentreId,d.WbsName,f.RequestId,f1.RDecisionNo"));

                        $select2->combine($select1,'Union ALL');

                        $select3 = $sql->select();
                        $select3->from(array("g1"=>$select2))
                            ->columns(array('RequestAHTransId','AnalysisId','ReqTransId','ResourceId','ItemId','RequestTransId','CostCentreId','WbsName','RequestId','RDecisionNo',"Quantity"=>new Expression("CAST(Sum(Quantity) As Decimal(18,5))"),"CurQty"=>new Expression("CAST(Sum(CurQty) As Decimal(18,5))")));
                        $select3->group(new Expression("RequestAHTransId,AnalysisId,ReqTransId,ResourceId,ItemId,RequestTransId,CostCentreId,WbsName,RequestId,RDecisionNo"));
                        $feedSub1Statement = $sql->getSqlStringForSqlObject($select3);
                        $cost['wbs'] = $dbAdapter->query($feedSub1Statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        array_push($dec['cost'], $cost);
                        array_push($costArray, $cost['CostcentreId']);
                    }
                }
                array_push($decArray, $dec['DecisionId']);
                array_push($res['decision'], $dec);
            }
            array_push($resp, $res);
        }
        //echo json_encode($resp);die;
        $select = $sql->select();
        $select->from(array("a"=>"VM_RequestDecision"))
            ->columns(array(new Expression("
								STUFF((SELECT ',' + CONVERT(VARCHAR(12), m.ResourceId) FROM VM_ReqDecQtyTrans l 
								inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId 
								inner join [VM_ReqDecTrans] n on m.RequestId=n.RequestId 
								WHERE l.DecisionId = a.DecisionId and n.DecisionId =a.DecisionId order by m.ResourceId FOR XML PATH('')), 1, 1, '') AS ResourceId, 
								STUFF((SELECT ',' + CONVERT(VARCHAR(12), n.CostCentreId) FROM VM_ReqDecQtyTrans l 
								inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId 
								inner join [VM_ReqDecTrans] n1 on M.RequestId=n1.RequestId 
								inner join [VM_RequestRegister] n on n1.RequestId = n.RequestId
								WHERE l.DecisionId = a.DecisionId and n1.DecisionId =a.DecisionId order by m.ResourceId FOR XML PATH('')), 1, 1, '') AS CostCentreId,
								a.DecisionId, a.RDecisionNo, CONVERT(VARCHAR(10), a.DecDate, 105) as DecDate, a.RequestType ")));

        $feedStatement = $sql->getSqlStringForSqlObject($select);
        $feedResult = $dbAdapter->query($feedStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $decResult = array_combine(array_column($feedResult, 'DecisionId'), $feedResult);

        $resArr = array_unique(explode(',', implode(',', array_column($feedResult, 'ResourceId'))));
        $costArr = array_unique(explode(',', implode(',', array_column($feedResult, 'CostCentreId'))));

        $resSelect = $sql->select();
        $resSelect->from(array("k"=>"Proj_Resource"))
            ->columns(array(new Expression(
                "STUFF((SELECT ',' + CONVERT(VARCHAR(12), o.DecisionId)FROM VM_ReqDecQtyTrans l
						inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId 
						inner join VM_ReqDecTrans n on n.RequestId=m.RequestId  and l.DecisionId=n.DecisionId
						inner join VM_RequestDecision o on o.DecisionId=n.DecisionId 
						where k.ResourceId = m.ResourceId order by o.DecisionId FOR XML PATH('')), 1, 1, '') AS decId, 
						STUFF( ( SELECT ',' + CONVERT(VARCHAR(12), o.CostCentreId) FROM VM_ReqDecQtyTrans l 
						inner join VM_RequestTrans M on l.ReqTransId = m.RequestTransId 
						inner join VM_RequestRegister o on o.RequestId=m.RequestId 
						inner join WF_OperationalCostCentre n on n.CostCentreId=o.CostCentreId 
						where k.ResourceId = m.ResourceId order by l.DecisionId FOR XML PATH('') ), 1, 1, '' ) as costId,
						k.ResourceId as data, k.ResourceName as value")))
            ->where(array('k.ResourceId' => $resArr));
        $resStatement = $sql->getSqlStringForSqlObject($resSelect);
        $resResult = $dbAdapter->query($resStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $resourceResult = array_combine(array_column($resResult, 'data'), $resResult);

        $costselect = $sql->select();
        $costselect->from(array("k"=>"WF_OperationalCostCentre"))
            ->columns(array(new Expression(
                "STUFF( ( SELECT ',' + CONVERT(VARCHAR(12), m.ResourceId) FROM VM_ReqDecQtyTrans o
							inner join VM_RequestTrans M on o.ReqTransId=M.RequestTransId 
							inner join VM_RequestRegister l on l.RequestId=m.RequestId 
							where l.CostCentreId= k.CostCentreId order by o.DecisionId FOR XML PATH('') ), 1, 1, '' ) AS resId, 
							STUFF( ( SELECT ',' + CONVERT(VARCHAR(12), o.DecisionId) FROM VM_ReqDecQtyTrans o 
							inner join VM_RequestTrans M on o.ReqTransId=M.RequestTransId 
							inner join VM_RequestRegister l on l.RequestId=m.RequestId 
							where l.CostCentreId= k.CostCentreId order by o.DecisionId FOR XML PATH('') ), 1, 1, '' ) AS decId, 
							k.CostCentreId as data, k.CostCentreName as value ")))
            ->where(array('k.CostCentreId' => $costArr));
        $costStatement = $sql->getSqlStringForSqlObject($costselect);
        $costResult = $dbAdapter->query($costStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $projResult = array_combine(array_column($costResult, 'data'), $costResult);

        if(is_numeric($poRegId)){
            $select1 = $sql->select();
            $select1->from(array("a"=>"MMS_PORegister"))//MMS_POLogistic
            ->columns(array('PORegisterId', 'VendorId', 'PONo', 'PODate' => new Expression('CONVERT(VARCHAR(10), PODate, 105)'), 'ReqNo', 'CostCentreId', 'BranchId',
                    'BranchTransId', 'CurrencyId', 'PurchaseTypeId', 'PurchaseAccount', 'CompanyContactName', 'CompanyContactNo', 'SiteContactName',
                    'SiteContactNo','CreatedUserId', 'ModifiedUserId', 'ModifiedDate','General',
                    'Address1', 'Address2', 'Address3', 'City', 'Pincode'))
                ->join(array('b'=>'MMS_POLogistic'), "a.PORegisterId=b.PORegisterId", array("ProviderId","VehicleId"), $select1::JOIN_INNER)
                ->join(array('c'=>'Vendor_Branch'), "a.VendorId=c.VendorId", array('Phone'), $select1::JOIN_INNER)
                ->where(array('a.PORegisterId' => $poRegId ));
            $poStatement = $sql->getSqlStringForSqlObject($select1);
            $poResult = $dbAdapter->query($poStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if(count($poResult) == 0){
                $this->redirect()->toRoute('mms/default', array('controller' => 'purchase','action' => 'register'));
            }
        } else {
            $this->redirect()->toRoute('mms/default', array('controller' => 'purchase','action' => 'register'));
        }

        /*selected item Service*/
        $selectPOservice = $sql->select();
        $selectPOservice->from(array("a"=>"MMS_POLogisticService"))
            ->columns(array("ServiceId"), array("ServiceName","Sel"=>new Expression("1")))
            ->join(array("c"=>"Vendor_ServiceMaster"), "a.ServiceId=c.ServiceId", array("ServiceName","Sel"=>new Expression("1")), $selectPOservice::JOIN_INNER)
            ->where(array('a.PORegisterId'=>$poResult['PORegisterId']));

        $Subselect2= $sql->select();
        $Subselect2->from("MMS_POLogisticService")
            ->columns(array("ServiceId"))
            ->where(array('PORegisterId'=>$poResult['PORegisterId']));

        $selectservice = $sql->select();
        $selectservice->from(array("a"=>'Vendor_ServiceMaster'))
            ->columns(array("ServiceId","ServiceName","Sel"=>new Expression("1-1")))
            ->where->notIn('a.ServiceId',$Subselect2);

        $selectservice->combine($selectPOservice,'Union ALL');
        $mostStatement = $sql->getSqlStringForSqlObject($selectservice);
        $service = $dbAdapter->query($mostStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*selected item TermsMaster*/
        $selectPOterms = $sql->select();
        $selectPOterms->from(array("a"=>"MMS_POPaymentTerms"))
            ->columns(array("TermsId"), array("Title","Sel"=>new Expression("1")))
            ->join(array("c"=>"WF_TermsMaster"), "a.TermsId=c.TermsId", array("Title","Sel"=>new Expression("1")), $selectPOterms::JOIN_INNER)
            ->where(array('a.PORegisterId'=>$poResult['PORegisterId']));

        $Subselect2= $sql->select();
        $Subselect2->from("MMS_POPaymentTerms")
            ->columns(array("TermsId"))
            ->where(array('PORegisterId'=>$poResult['PORegisterId']));

        $selectterms = $sql->select();
        $selectterms->from(array("a"=>'WF_TermsMaster'))
            ->columns(array("TermsId","Title","Sel"=>new Expression("1-1")))
            ->where->notIn('a.TermsId',$Subselect2);
        $selectterms->where(array('a.TermType'=>'S'));

        $selectterms->combine($selectPOterms,'Union ALL');
        $mosttermsStatement = $sql->getSqlStringForSqlObject($selectterms);
        $terms = $dbAdapter->query($mosttermsStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*Logistics*/
        $selectLogist = $sql->select();
        $selectLogist->from("MMS_POLogisticType")
            ->columns(array("id"=>'LogisticTypeId'))
            ->where(array('PORegisterId'=>$poResult['PORegisterId']));
        $LogistStatement1 = $sql->getSqlStringForSqlObject($selectLogist);
        $logistics = $dbAdapter->query($LogistStatement1, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*selected item Distributor*/
        $selectPOdistri = $sql->select();
        $selectPOdistri->from(array("a"=>"MMS_PODistributorTrans"))
            ->columns(array("VendorId"), array("VendorName","Sel"=>new Expression("1")))
            ->join(array("b"=>"Vendor_Master"), "a.VendorId=b.VendorId", array("VendorName","Sel"=>new Expression("1")), $selectPOdistri::JOIN_INNER)
            ->join(array('c'=>'Vendor_SupplierDet'), "b.VendorId=c.SupplierVendorId", array(), $selectPOdistri::JOIN_INNER)
            ->where(array('a.PORegisterId'=>$poResult['PORegisterId']));

        $Subselect2= $sql->select();
        $Subselect2->from("MMS_PODistributorTrans")
            ->columns(array("VendorId"))
            ->where(array('PORegisterId'=>$poResult['PORegisterId']));

        $selectdistri = $sql->select();
        $selectdistri->from(array("a"=>'Vendor_Master'))
            ->columns(array("VendorId","VendorName","Sel"=>new Expression("1-1")))
            ->join(array('b'=>'Vendor_SupplierDet'), "a.VendorId=b.SupplierVendorId", array(), $selectdistri::JOIN_INNER)
            ->where->notIn('a.VendorId',$Subselect2);
        $selectdistri->where(array('b.VendorId'=>$poResult['VendorId']));

        $selectdistri->combine($selectPOdistri,'Union ALL');

        $statement3 = $sql->getSqlStringForSqlObject($selectdistri);
        $distributor = $dbAdapter->query($statement3, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*Supply*/
        $select = $sql->select();
        $select->from('Vendor_Master')
            ->columns(array('VendorId','VendorName'))
            ->where(array('Supply' => '1') );
        $statement = $sql->getSqlStringForSqlObject($select);
        $vendorList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*ServiceProvider*/
        $select = $sql->select();
        $select->from('Vendor_Master')
            ->columns(array('VendorId','LogoPath','VendorName'))
            ->where(array('Service' => '1') );
        $statement = $sql->getSqlStringForSqlObject($select);
        $serviceProvider = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*CurrencyMaster*/
        $select = $sql->select();
        $select->from('WF_CurrencyMaster')
            ->columns(array('CurrencyId','CurrencyName'))
            ->Order("CurrencyName");
        $statement = $sql->getSqlStringForSqlObject($select);
        $currencyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array('a' => 'Vendor_Branch'))
            ->columns(array('BranchId', 'BranchName'))
            ->where(array('a.VendorId' => $poResult['VendorId']));
        $statement = $sql->getSqlStringForSqlObject($select);
        $vendorBranch = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array('a' => 'Vendor_BranchContactDetail'))
            ->columns(array('BranchTransId', 'ContactPerson'))
            ->where(array('a.BranchId' => $poResult['BranchId']));
        $branchContstatement = $sql->getSqlStringForSqlObject($select);
        $branchContact = $dbAdapter->query($branchContstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /*TransportMode*/
        $select1 = $sql->select();
        $select1->from(array('a' => 'Vendor_TransportMaster'))
            ->columns(array('TransportId', 'TransportName'), array("VendorId"))
            ->join(array('b'=>'Vendor_Transport'), "a.TransportId=b.TransportId", array("VendorId"), $select1::JOIN_LEFT)
            ->where(array('b.VendorId' => $poResult['VendorId']));

        $transportstatement = $sql->getSqlStringForSqlObject($select1);
        $transPortMode = $dbAdapter->query($transportstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $termselect = $sql->select();
        $termselect->from(array("a"=>"MMS_POPaymentTerms"))
            ->columns(array('TermsId','ValueFromNet','Per','Value','Period',"Date"=>'TDate',"Str"=>'TString' ),array('SlNo',"Terms"=>'Title',"IsPer"=>'Per',"IsValue"=>'Value',"IsPeriod"=>'Period',"IsDate"=>'TDate',"IsString"=>'TString',"IsDef"=>'SysDefault',"IGross"=>'IncludeGross'))
            ->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array('SlNo',"Terms"=>'Title',"IsPer"=>'Per',"IsValue"=>'Value',"IsPeriod"=>'Period',"IsDate"=>'TDate',"IsString"=>'TString',"IsDef"=>'SysDefault',"IGross"=>'IncludeGross'), $termselect::JOIN_INNER);
        $termselect->where(array('a.PORegisterId'=>$poRegId));
        $termsstatement = $sql->getSqlStringForSqlObject($termselect);
        $resultsFillTermdet= $dbAdapter->query($termsstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;
        $this->_view->resJson = $resJson;
        $this->_view->costArray = $costArray;
        $this->_view->decArray = $decArray;
        $this->_view->feedsResult = json_encode($resp);
        $this->_view->service = $service;
        $this->_view->terms = $terms;
        $this->_view->logistics = $logistics;
        $this->_view->distributor = $distributor;
        $this->_view->feedResult = $decResult;
        $this->_view->resResult = $resourceResult;
        $this->_view->costResult = $projResult;
        $this->_view->vendorList = $vendorList;
        $this->_view->serviceProvider = $serviceProvider;
        $this->_view->transPortMode = $transPortMode;
        $this->_view->currencyList = $currencyList;
        $this->_view->vendorBranch = $vendorBranch;
        $this->_view->branchContact = $branchContact;
        $this->_view->poRegId =$poRegId;
        $this->_view->poResult =$poResult;
        $this->_view->resultsFillTermdet = $resultsFillTermdet;

        return $this->_view;
    }
    public function printpoAction(){
        return $this->_view;
    }
    public function printpodragAction(){
        return $this->_view;
    }

    public function purchasePrintAction(){
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



    public function purchaseTypeAction(){
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
                $postData = $request->getPost();

//                echo"<pre>";
//                 print_r($postData);
//                  echo"</pre>";
//                 die;
//                   return;



                $companyId = $this->bsf->isNullCheck($postData['company'], 'number');
                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try {
                    $delete = $sql->delete();
                    $delete->from("MMS_PurchaseTypeTrans")
                        ->where(array('CompanyId'=>$companyId));
                    $deletePurchaseTypeTrans = $sql->getSqlStringForSqlObject($delete);
                    $dbAdapter->query($deletePurchaseTypeTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                    if(isset($postData['type'])){
                        foreach($postData['type'] as $type){
                            if($type == $postData['type1']){
                                $def = 1;
                            } else {
                                $def = 0;
                            }
                            $purchaseTypeUpdate = $sql->update('MMS_PurchaseType');
                            $purchaseTypeUpdate->set(array("AccountId"=>$this->bsf->isNullCheck($postData['account'][$type], 'number')))
                                ->where("PurchaseTypeId = $type");
                            $purchaseTypeUpdateStatement = $sql->getSqlStringForSqlObject($purchaseTypeUpdate);
                            $dbAdapter->query($purchaseTypeUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $purchaseTypeInsert = $sql->insert('MMS_PurchaseTypeTrans');
                            $purchaseTypeInsert->values(array("PurchaseTypeId"=>$type,
                                "Sel"=>1,
                                "CompanyId"=>$companyId,
                                "Default" =>$def,
                            ));
                            $purchaseTypeStatement = $sql->getSqlStringForSqlObject($purchaseTypeInsert);
                            $dbAdapter->query($purchaseTypeStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $connection->commit();
                } catch(PDOException $e){
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
            }

            $companyId = $this->params()->fromRoute('companyId');
            $selectCompany = $sql->select();
            $selectCompany->from("WF_CompanyMaster")
                ->columns(array("CompanyId","CompanyName"))
                ->where(array("CompanyId" => $companyId));
            $selectCompanyStatement = $sql->getSqlStringForSqlObject($selectCompany);
            $this->_view->selCompany = $dbAdapter->query($selectCompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

            $selectCompany = $sql->select();
            $selectCompany->from("WF_CompanyMaster")
                ->columns(array("CompanyId","CompanyName","IssueAccount"));
            $selectCompanyStatement = $sql->getSqlStringForSqlObject($selectCompany);
            $this->_view->company = $dbAdapter->query($selectCompanyStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if(isset($companyId)){
                if($companyId > 0 ) {
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId", "PurchaseTypeName", "AccountId", "AccountTypeId", "Sel" => new expression("ISNULL(D.Sel,0)")))
                        ->join(array("b" => "FA_AccountType"), "a.AccountTypeId=b.TypeId", array("TypeName"), $select::JOIN_INNER)
                        ->join(array("c" => "FA_AccountMaster"), "a.AccountId=c.AccountId", array(), $select::JOIN_LEFT)
                        ->join(array("d" => "MMS_PurchaseTypeTrans"), new expression("a.PurchaseTypeId=D.PurchaseTypeId and D.CompanyId=$companyId"), array("Default"), $select::JOIN_LEFT);
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($purchaseType as &$type) {
                        $select = $sql->select();
                        $select->from("FA_AccountMaster")
                            ->columns(array("AccountId", "AccountName"))
                            ->where("LastLevel='Y' and TypeId=" . $type['AccountTypeId']);
                        $typeStatement = $sql->getSqlStringForSqlObject($select);
                        $type['AccountTypeTrans'] = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                }
                else
                {
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId", "PurchaseTypeName", "AccountId", "AccountTypeId", "Sel" => new expression("ISNULL(D.Sel,0)")))
                        ->join(array("b" => "FA_AccountType"), "a.AccountTypeId=b.TypeId", array("TypeName"), $select::JOIN_INNER)
                        ->join(array("c" => "FA_AccountMaster"), "a.AccountId=c.AccountId", array(), $select::JOIN_LEFT)
                        ->join(array("d" => "MMS_PurchaseTypeTrans"), new expression("a.PurchaseTypeId=D.PurchaseTypeId and D.CompanyId=$companyId"), array("Default"), $select::JOIN_INNER);
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($purchaseType as &$type) {
                        $select = $sql->select();
                        $select->from("FA_AccountMaster")
                            ->columns(array("AccountId", "AccountName"))
                            ->where("LastLevel='Y' and TypeId=" . $type['AccountTypeId']);
                        $typeStatement = $sql->getSqlStringForSqlObject($select);
                        $type['AccountTypeTrans'] = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                }
                $this->_view->purchaseType = $purchaseType;
            }
            $this->_view->companyId = $companyId;

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }
    public function orderAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Purchase Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');


                $response = $this->getResponse();
                switch($Type) {
                    case 'preVendor':
                        $reqtransId = $this->bsf->isNullCheck($this->params()->fromPost('reqtransId'), 'string');

                        $select = $sql -> select();
                        $select->from(array("a" => "VM_RFQTrans"))
                            ->columns(array(new Expression("d.VendorId,a.ResourceId,a.ItemId,Case when a.ItemId>0 then '(' + f.ItemCode + ') ' + f.BrandName
                               else '('+e.Code + ') ' + e.ResourceName end Resource,d.VendorName + char(13)+char(10)+ D.RegAddress + char(13)+char(10)+G.CityName
                               + char(13)+char(10)+H.StateName + char(13)+char(10)+i.CountryName + char(13)+char(10)+
                               'Pin Code: ' +D.PinCode + char(13)+char(10)+'Contact Person: ' + j.CPerson1 +char(13)+char(10) +
                               'Phone: '+j.Phone1 +char(13)+char(10)+ 'Mobile: '+j.Mobile1 As Address,d.VendorName,d.RegAddress,g.CityName,h.StateName,i.CountryName,
                               'Pin Code: ' +d.PinCode As PinCode,
                               'Contact Person: ' + j.CPerson1 As CPerson,'Phone: ' + j.Phone1 As Phone,'Mobile: ' + j.Mobile1 As Mobile ")))
                            ->join(array('b' => 'VM_AnalysisRegister'),'a.RFQId=b.RFQId',array(),$select::JOIN_INNER)
                            ->join(array('c' => 'VM_AnalysisVendorTrans'),'b.AnalysisRegId=c.RegId and a.ResourceId=c.ResourceId and a.ItemId=c.ItemId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'Vendor_Master'),'c.VendorId=d.VendorId',array(),$select::JOIN_INNER)
                            ->join(array('e' => 'Proj_Resource'),'a.ResourceId=e.ResourceId',array(),$select::JOIN_INNER )
                            ->join(array('f' => 'MMS_Brand'),'a.ResourceId=f.ResourceId and a.ItemId=f.BrandId',array(),$select::JOIN_LEFT)
                            ->join(array('g' => 'WF_CityMaster'),'d.CityId=g.CityId',array(),$select::JOIN_LEFT)
                            ->join(array('h' => 'WF_StateMaster'),'g.StateId=h.StateId',array(),$select::JOIN_LEFT)
                            ->join(array('i' => 'WF_CountryMaster'),'h.CountryId=i.CountryId',array(),$select::JOIN_LEFT)
                            ->join(array('j' => 'Vendor_Contact'),'d.VendorId=j.VendorId',array(),$select::JOIN_LEFT)
                            ->join(array('k' => 'VM_RequestTrans'),'a.ResourceId=k.ResourceId and a.ItemId=k.ItemId',array(),$select::JOIN_INNER)
                            ->join(array('l' => 'VM_ReqDecQtyTrans'),'k.RequestTransId=l.ReqTransId',array(),$select::JOIN_INNER )
                            ->where("l.TransId IN ($reqtransId) and d.supply=1 and d.approve='Y' and b.Approve='Y'
                                 and d.VendorId NOT IN (Select VendorId From Vendor_RegTrans Where StatusType='S' and STDate >=  '".date('d-M-Y')."')
                                 and d.SBlock=0 and d.CBlock=0 and d.HBlock=0 and d.CityId>0  ");

                        $sel1 = $sql -> select();
                        $sel1->from(array("a" => "Vendor_Master"))
                            ->columns(array(new Expression("a.VendorId,c.ResourceId,isnull(d.BrandId,0) As ItemId,
                               case when isnull(d.brandid,0)>0 then '('+d.ItemCode + ') '+ d.BrandName else
                               '('+c.code + ') '+ c.ResourceName end Resource,a.VendorName + char(13)+char(10)+ A.RegAddress + char(13)+char(10)+E.CityName
                               + char(13)+char(10)+F.StateName + char(13)+char(10)+G.CountryName
                               + char(13)+char(10)+'Pin Code: '+A.PinCode + char(13)+char(10)+
                               'Contact Person: ' + H.CPerson1 + char(13)+char(10)+ 'Phone: '+H.Phone1 + char(13)+char(10)+
                               'Mobile: ' + H.Mobile1 As Address,a.VendorName,a.RegAddress,e.CityName,f.StateName,G.CountryName,'Pin Code: '+A.PinCode As PinCode,
                               'Contact Person: ' + H.CPerson1 As CPerson,'Phone: ' + H.Phone1 As Phone,'Mobile: ' + H.Mobile1 As Mobile  ")))
                            ->join(array('b' => 'Vendor_MaterialTrans'),'a.VendorId=b.VendorId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => 'Proj_Resource'),'b.Resource_Id=c.ResourceId',array(),$sel1::JOIN_INNER)
                            ->join(array('d' => 'MMS_Brand'),'c.ResourceId=d.ResourceId',array(),$sel1::JOIN_LEFT)
                            ->join(array('e' => 'WF_CityMaster'),'a.CityId=e.CityId',array(),$sel1::JOIN_LEFT)
                            ->join(array('f' => 'WF_StateMaster'),'e.StateId=f.StateId',array(),$sel1::JOIN_LEFT)
                            ->join(array('g' => 'WF_CountryMaster'),'f.CountryId=g.CountryId',array(),$sel1::JOIN_LEFT)
                            ->join(array('h' => 'Vendor_Contact'),'a.VendorId=h.VendorId',array(),$sel1::JOIN_LEFT)
                            ->join(array('i' => 'VM_RequestTrans'),'b.Resource_Id=i.RequestId and d.BrandId=i.ItemId',array(),$sel1::JOIN_INNER)
                            ->join(array('j' => 'VM_ReqDecQtyTrans'),'i.RequestTransId=j.ReqTransId',array(),$sel1::JOIN_INNER)
                            ->where("j.TransId IN ($reqtransId) and a.Approve='Y' and a.CityId>0 and a.VendorId NOT IN (select vendorid from Vendor_RegTrans where
                                 StatusType='S' and STDate >= '".date('d-M-Y')."') and a.VendorId NOT IN (
                                 Select d.VendorId From VM_RFQTrans A
                                 Inner Join VM_AnalysisRegister B On a.RFQId=b.RFQId
                                 Inner Join VM_AnalysisVendorTrans C On b.AnalysisRegId=c.RegId and a.Resourceid=c.ResourceId and a.ItemId=c.ItemId
                                 Inner Join Vendor_Master D On c.VendorId=d.VendorId
                                 Inner Join VM_RequestTrans E On a.ResourceId=E.ResourceId and a.ItemId=E.ItemId
                                 Inner Join VM_ReqDecQtyTrans F On E.RequestTransId=F.ReqTransId
                                 Where F.TransId IN ($reqtransId) and d.supply=1 and d.approve='Y' and b.Approve='Y' )  ");
                        $select->combine($sel1,'Union All');

                        $sel2 = $sql -> select();
                        $sel2->from(array("g" => $select ))
                            ->columns(array(new Expression("g.VendorId,g.ResourceId,g.ItemId,g.Resource,g.Address,g.VendorName,g.RegAddress,
                               g.CityName,g.StateName,g.CountryName,g.PinCode,g.CPerson,g.Phone,g.Mobile,
                              ROW_NUMBER() OVER (PARTITION BY g.VendorId,g.ResourceId,g.ItemId ORDER BY g.VendorId ASC) As RNo ")));
                        $sel3 = $sql -> select();
                        $sel3->from(array("g1" => $sel2 ))
                            ->columns(array(new Expression("g1.VendorId,g1.ResourceId,g1.ItemId,
                              g1.Resource,g1.address As Address,g1.VendorName,g1.RegAddress,g1.CityName,g1.StateName,
                              g1.CountryName,g1.PinCode,ISNULL(g1.CPerson,'') As CPerson,ISNULL(g1.Phone,'') As Phone,ISNULL(g1.Mobile,'') As Mobile,g1.RNo ")))
                            ->where("g1.RNo=1");
                        $sel3->order('g1.ResourceId');
                        $statement = $sql->getSqlStringForSqlObject($sel3);
                        $arr_prevendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_prevendor));
                        return $response;
                        break;
                    case 'getRequest':
                        $CostCentreId= $this->bsf->isNullCheck($postParams['CostCentreId'],'number');
                        $OrderType= $this->bsf->isNullCheck($postParams['OrderType'],'string');
                        $whereCond = array("a.CostCentreId"=>$CostCentreId);


                        $select = $sql->select();
                        $select->from(array("a" => "VM_RequestDecision"))
                            ->columns(array(new Expression("a.DecisionId as RequestId,
                                Convert(Varchar(10),a.DecDate,103) As DecisionDate,a.RDecisionNo As DecisionNo,
                                e.CostCentreName,d.RequestNo,Convert(Varchar(10),d.RequestDate,103) as RequestDate")))
                            ->join(array('b' => 'VM_ReqDecTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_ReqDecQtyTrans'),'b.DecisionId=c.DecisionId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestRegister'),'b.RequestId=d.RequestId',array(),$select::JOIN_INNER)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'d.CostCentreId=e.CostCentreId',array(),$select::JOIN_INNER)
                            ->where("d.CostCentreId=$CostCentreId and (c.IndentQty-c.IndAdjQty)>0 and a.Approve='Y'");
                        $select->order("a.DecDate desc");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("d" => "VM_RequestDecision"))
                            //->columns(array(new Expression("h.TransId As RequestTransId,h.DecisionId As RequestId,0 As Include,(h.IndentQty-h.IndAdjQty) As Quantity,Convert(Varchar (10),d.DecDate,103) As RequestDate,d.RDecisionNo As RequestNo,Case When i.ItemId>0 Then k.BrandName Else j.ResourceName End As [Desc]")))
                            ->columns(array(new Expression("h.TransId As RequestTransId,h.DecisionId As RequestId,0 As Include,d.RDecisionNo As RequestNo,Convert(Varchar(10),d.DecDate,103) As RequestDate,Case When i.ItemId>0 Then k.ItemCode+ '-' +k.BrandName Else j.Code + '-' +j.ResourceName End As [Desc],CAST((h.IndentQty-h.IndAdjQty) As Decimal(18,3)) As Quantity")))
                            ->join(array("g"=>'VM_ReqDecTrans'),'d.DecisionId=g.DecisionId',array(),$select::JOIN_INNER)
                            ->join(array("h"=>'VM_ReqDecQtyTrans'),'g.DecisionId=h.DecisionId',array(),$select::JOIN_INNER)
                            ->join(array('b' => 'VM_RequestRegister'), 'g.RequestId=b.RequestId', array(), $select::JOIN_LEFT)
                            ->join(array('i'=>'VM_RequestTrans'),'h.ReqTransId=i.RequestTransId and b.RequestId=i.RequestId',array(),$select::JOIN_INNER)
                            ->join(array('j'=>'Proj_Resource'),'i.ResourceId=j.ResourceId',array(),$select::JOIN_INNER)
                            ->join(array('k'=>'MMS_Brand'),'k.BrandId=i.ItemId and k.ResourceId=i.ResourceId',array(),$select::JOIN_LEFT)
                            ->where("(h.IndentQty-h.IndAdjQty)>0 and d.Approve='Y' and b.CostCentreId=$CostCentreId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('requests' => $requests, 'resources' => $requestResources)));
                        return $response;

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
            $select->from( array( 'a' => 'WF_OperationalCostCentre' ))
                ->columns( array( 'CostCentreId', 'CostCentreName' ))
                ->where( 'Deactivate=0' );
            $statement = $sql->getSqlStringForSqlObject( $select );
            $this->_view->arr_costcenter = $dbAdapter->query( $statement, $dbAdapter::QUERY_MODE_EXECUTE )->toArray();

            // vendors(contract)
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId','VendorName','LogoPath'))
                ->where(array('Supply' => '1') );
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_contract_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            // vendors(service)
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId','VendorName','LogoPath'))
                ->where(array('Service' => '1'));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_service_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function orderentryAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Purchase Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $connection = $dbAdapter->getDriver()->getConnection();
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $poRegId = $this->bsf->isNullCheck($this->params()->fromRoute('poRegId'), 'number');
        $flag = $this->bsf->isNullCheck($this->params()->fromRoute('flag'), 'number');


        if(!$this->getRequest()->isXmlHttpRequest() && $poRegId == 0 && !$request->isPost()) {
            $this->redirect()->toRoute('mms/default', array('controller' => 'purchase','action' => 'order'));
        }
        if($this->getRequest()->isXmlHttpRequest())	{
            if ($request->isPost()) {

                $Type = $this->bsf->isNullCheck($this->params()->fromPost('Type'), 'string');

                $response = $this->getResponse();
                switch($Type) {
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
                    case 'getqualdetails':

                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $POId=$this->bsf->isNullCheck($this->params()->fromPost('poId'), 'number');

                        $selSub2 = $sql -> select();
                        $selSub2->from(array("a" => "MMS_POQualTrans"))
                            ->columns(array("QualifierId"));
                        $selSub2->where(array('a.PORegisterId' => $POId,'a.ResourceId' => $ResId, 'a.ItemId' => $ItemId ));

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
                        $select->from(array("c" => "MMS_POQualTrans"))
                            ->columns(array('ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),'QualifierId'=>new Expression('c.QualifierId'),
                                'YesNo'=>new Expression('Case When c.YesNo=1 Then 1 Else 0 End'),'Expression'=>new Expression('c.Expression'),
                                'ExpPer'=>new Expression('c.ExpPer'),'TaxablePer'=>new Expression('c.TaxablePer'),'TaxPer'=>new Expression('c.TaxPer'),
                                'Sign'=>new Expression('c.Sign'),'SurCharge'=>new Expression('c.SurCharge'),'EDCess'=>new Expression('c.EDCess'),
                                'HEDCess'=>new Expression('c.HEDCess'),'NetPer'=>new Expression('c.NetPer'),'BaseAmount'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),
                                'ExpressionAmt'=>new Expression('CAST(c.ExpressionAmt As Decimal(18,2)) '),'TaxableAmt'=>new Expression('CAST(c.TaxableAmt As Decimal(18,2)) '),'TaxAmt'=>new Expression('CAST(c.TaxAmt As Decimal(18,2)) '),
                                'SurChargeAmt'=>new Expression('CAST(c.SurChargeAmt As Decimal(18,2)) '),'EDCessAmt'=>new Expression('c.EDCessAmt'),
                                'HEDCessAmt'=>new Expression('c.HEDCessAmt'),'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,2)) '),'QualifierName'=>new Expression('b.QualifierName'),
                                'QualifierTypeId'=>new Expression('b.QualifierTypeId'),'RefId'=>new Expression('b.RefNo'), 'SortId'=>new Expression('a.SortId')))
                            ->join(array("a" => "Proj_QualifierTrans"), "c.QualifierId=a.QualifierId", array(), $select::JOIN_INNER)
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array(), $select::JOIN_INNER);

                        $select->where(array('a.QualType' => 'M', 'c.PORegisterId' => $POId, 'c.ResourceId' => $ResId, 'c.ItemId' => $ItemId));
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
                    case 'getNewDecisionTrans':
                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $CostCentreId = $this->bsf->isNullCheck($this->params()->fromPost('CostCentreId'), 'number');
                        $select = $sql->select();
                        $select->from(array("a" => "VM_RequestDecision"))
                            ->columns(array(new Expression('a.DecisionId,b.TransId As DecTransId,b.ReqTransId,a.RDecisionNo As DecisionNo,c.ResourceId,c.ItemId,CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3)) As BalQty,Cast(0 as Decimal(18,3)) As Qty')))
                            ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
                            ->where('d.CostCentreId=' . $CostCentreId . ' and (b.IndentQty-b.IndAdjQty)>0 And a.Approve='."'Y'".' And c.ResourceId='.$ResId.' And c.ItemId='.$ItemId.'');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_dectrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_dectrans));
                        return $response;
                        break;
                    case 'getNewDecAnalTrans':
                        $DecId = $this->bsf->isNullCheck($this->params()->fromPost('DecisionId'), 'number');
                        $DecTransId = $this->bsf->isNullCheck($this->params()->fromPost('DecTransId'), 'number');
                        $CostCentre = $this->bsf->isNullCheck($this->params()->fromPost('CostCentre'), 'number');
                        $select = $sql->select();
                        $select->from(array("a" => "VM_RequestDecision"))
                            ->columns(array('WBSName'=>new Expression('(f.ParentText+'."'->'".'+f.WBSName)'),'WBSId'=>new Expression("f.WBSId"),'DecTransId'=>new Expression("b.TransId"),'DecATransId'=>new Expression('c.RCATransId'),
                                'ReqTransId'=>new Expression("e.RequestTransId"),'ReqAHTransId'=>new Expression("c.ReqAHTransId"),'ResourceId'=>new Expression("e.ResourceId"),'ItemId'=>new Expression('e.ItemId'),'BalQty'=>new Expression("CAST((c.IndentQty-c.IndAdjQty) As Decimal(18,3))"),
                                'Qty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_ReqDecQtyAnalTrans'),'b.TransId=c.TransId and b.DecisionId=c.DecisionId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestAnalTrans'),'c.ReqAHTransId=d.RequestAHTransId and b.ReqTransId=d.ReqTransId',array(),$select::JOIN_INNER)
                            ->join(array('e' => 'VM_RequestTrans'), 'b.ReqTransId=e.RequestTransId and d.ReqTransId=e.RequestTransId', array(), $select::JOIN_INNER)
                            ->join(array('f' => 'Proj_WBSMaster'),'d.AnalysisId=f.WBSId',array(),$select::JOIN_INNER)
                            ->join(array('g' => 'VM_RequestRegister'), 'e.RequestId=g.RequestId', array(), $select::JOIN_INNER)
                            ->where('(b.IndentQty-b.IndAdjQty)>0 And a.Approve='."'Y'".' And a.DecisionId='.$DecId.' And b.TransId='.$DecTransId.'');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_decanaltrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //Get Est Wbs
                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectWBSResource"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),
                                'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"), 'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CostCentre .' and  a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .')
                                 and a.WBSId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');



                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') And b.CostCentreId='.$CostCentre.' And d.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel1->combine($sel,'Union ALL');


                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') And c.CostCentreId='.$CostCentre .' And c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel2->combine($sel1,"Union ALL");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') and c.CostCentreId='.$CostCentre.' and c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') And c.CostCentreId='.$CostCentre.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') and c.ToCostCentreId='.$CostCentre.'
                                  And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel5->group(new Expression("a.ResourceId,A.AnalysisId"));

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .')and c.FromCostCentreId='.$CostCentre.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel6->combine($sel5,"Union ALL");


                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7->group(new Expression("a.ResourceId,a.WBSId"));
                        $sel7 -> combine($sel4,"Union ALL");


                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') and c.CostCentreId='.$CostCentre.' and
                                 a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .')
                                  and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .')
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel10->combine($sel9,"Union ALL");


                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .')
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .'
                                 and a.ResourceId IN (Select A.ResourceId From VM_RequestTrans A
                                     Inner Join VM_ReqDecQtyTrans B On A.RequestTransId=B.ReqTransId Where B.TransId='. $DecTransId .') )');
                        $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),
                                'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));
                        $sel12->group(new Expression("G.ResourceId,G.WBSId"));
                        $statement = $sql->getSqlStringForSqlObject($sel12);
                        $arr_wbsresestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        //

//                        $response->setStatusCode('200');
//                        $response->setContent(json_encode($arr_resource_decanaltrans));
//                        return $response;
//                        break;

                        $response->setStatusCode('200');
                        $response = $this->getResponse()->setContent(json_encode(array('anal' => $arr_resource_decanaltrans,'estwbs' => $arr_wbsresestimate)));
                        return $response;
                        break;
                    case 'getresourcedetails':
                        $ResourceId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectDetails"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo', 'Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.ProjectIOWId=c.ProjectIOWId and a.ProjectId=c.ProjectId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSID=c.WBSID and a.ProjectId=d.ProjectId', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                            ->join(array('e' => 'WF_OperationalCostCentre'),'a.ProjectId=e.ProjectId')
                            ->columns(array('ResourceId'))
                            ->order('a.ResourceId')
                            ->where("a.ResourceId=$ResourceId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
                        return $response;
                        break;
                    case 'getiowdetails':
                        $IOWId = $this->bsf->isNullCheck($this->params()->fromPost('IOWId'), 'number');

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_ProjectDetails"))
                            ->join(array('b' => 'Proj_ProjectIOWMaster'), 'a.ProjectIOWId=b.ProjectIOWId', array('SerialNo', 'Specification'), $select::JOIN_LEFT)
                            ->join(array('c' => 'Proj_WBSTrans'), 'a.ProjectIOWId=c.ProjectIOWId', array(), $select::JOIN_LEFT)
                            ->join(array('d' => 'Proj_WBSMaster'), 'd.WBSID=c.WBSID', array('WBSName', 'ParentText'), $select::JOIN_LEFT)
                            ->columns(array('ResourceId'))
                            ->order('a.ResourceId')
                            ->where("a.ResourceId=$IOWId");
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_resource_iows));
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
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CCId .' And ResourceId=' .$ResId. ' ');

                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty' => new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And a.ResourceId=' .$ResId. ' And b.CostCentreId='.$CCId.' And c.General=0');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId='.$ResId.' And B.CostCentreId='.$CCId .' And B.General=0 ');
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),
                                'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty'=> new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('b.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.General=0 ');
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
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
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel13 = $sql -> select();
                        $sel13 -> from(array("a" => "MMS_IssueTrans"))
                            -> columns(array('IssueQty' => new Expression("ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>"MMS_IssueRegister"),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel13::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.IssueOrReturn=1');

                        $sel14 = $sql -> select();
                        $sel14 -> from(array("a" => "MMS_IssueTrans"))
                            -> columns(array('IssueQty' => new Expression("-1 * ISNULL(SUM(A.IssueQty),0)")))
                            ->join(array('b'=>'MMS_IssueRegister'),'a.IssueRegisterId=b.IssueRegisterId',array(),$sel14::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.' and b.IssueOrReturn=0');
                        $sel14->combine($sel13,"Union ALL");

                        $sel15 = $sql -> select();
                        $sel15 -> from(array("A"=>$sel14))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(SUM(IssueQty) As Decimal(18,3))")
                            ));
                        $sel15 -> combine($sel7,"Union ALL");



                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),
                                'BalReqQty'=>new Expression("ISNULL(SUM(A.BalQty),0)"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and b.CostCentreId='.$CCId.'');
                        $sel8->combine($sel15,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'IssueQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('b.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))"),
                                'IssueQty'=>new Expression("CAST(ISNULL(SUM(G.IssueQty),0) As Decimal(18,3))"),
                                'TransferQty'=>new Expression("CAST(ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReturnQty'=>new Expression("CAST(ISNULL(SUM(G.TotRetQty),0) As Decimal(18,3))")
                            ));

                        $statement = $sql->getSqlStringForSqlObject($sel12);
                        $arr_stock = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock));
                        return $response;
                        break;
                    case 'getwbsstockdetails':
                        $CCId = $this -> bsf ->isNullCheck($this->params()->fromPost('CostCenterId'),'number');
                        $ResId = $this -> bsf ->isNullCheck($this->params()->fromPost('resourceid'),'number');
                        $WBSId = $this -> bsf ->isNullCheck($this->params()->fromPost('WBSId'),'number');

                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectWBSResource"))
                            ->columns(array('EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("a.Rate"),
                                'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty' => new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CCId .' And ResourceId=' .$ResId. ' And WbsId='.$WBSId.' ');


                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty' => new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 And a.ResourceId=' .$ResId. ' And b.CostCentreId='.$CCId.' And d.General=0 And a.AnalysisId='.$WBSId.'');
                        $sel1->combine($sel,'Union ALL');

                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId='.$ResId.' And c.CostCentreId='.$CCId .' And c.General=0 And a.AnalysisId='.$WBSId.'');
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),
                                'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and c.CostCentreId='.$CCId.' and c.General=0 And a.AnalysisId='.$WBSId.' ');
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' And c.CostCentreId='.$CCId.' And a.AnalysisId='.$WBSId.' ');
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.ToCostCentreId='.$CCId.' And a.AnalysisId='.$WBSId.' ');

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.FromCostCentreId='.$CCId.' And a.AnalysisId='.$WBSId.'');
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),
                                'BalReqQty'=>new Expression("ISNULL(SUM(A.BalQty),0)"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.CostCentreId='.$CCId.' and a.AnalysisId='.$WBSId.'');
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' and a.AnalysisId='.$WBSId.' ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")
                            ))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' and a.AnalysisId='.$WBSId.' ');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")
                            ))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CCId.' and a.ResourceId='.$ResId.' and a.AnalysisId='.$WBSId.' ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),
                                'BalReqQty'=>new Expression("CAST(ISNULL(SUM(G.BalReqQty),0) As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))")
                            ));

                        $statement = $sql->getSqlStringForSqlObject($sel12);
                        $arr_stock_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_stock_wbs));
                        return $response;
                        break;
                    case 'getresestdetails':
                        $CostCentre = $this -> bsf ->isNullCheck($this->params()->fromPost('ccid'),'number');
                        $requestTransIds = $this -> bsf ->isNullCheck($this->params()->fromPost('resid'),'number');

                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectResource"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'EstimateQty' => new Expression('a.Qty'),
                                'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CostCentre .' And
                                     a.ResourceId IN ('. $requestTransIds .')');


                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POTrans" ))
                            ->columns(array('ResourceId' => new Expression("a.ResourceId"), 'EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And
                                a.ResourceId IN ('. $requestTransIds .')
                                                  And b.CostCentreId='.$CostCentre.' And c.General=0 Group By a.ResourceId ');
                        $sel1->combine($sel,'Union ALL');



                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('A.ResourceId IN ('. $requestTransIds .')
                                                     And B.CostCentreId='.$CostCentre .' And B.General=0 Group By a.ResourceId ');
                        $sel2->combine($sel1,"Union ALL");

                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('a.ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('b.ThruPO='."'Y'".' And a.ResourceId IN ('. $requestTransIds .')
                                                     and b.CostCentreId='.$CostCentre.' and b.General=0 Group By a.ResourceId ');
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId IN ('. $requestTransIds .')
                             And b.CostCentreId='.$CostCentre.' Group By a.ResourceId');
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('ResourceId'=>new Expression("a.ResourceId"),
                                'TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                            ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId IN ('. $requestTransIds .')
                             and b.ToCostCentreId='.$CostCentre.' Group By a.ResourceId ');

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferTrans"))
                            -> columns(array('ResourceId'=>new Expression("a.ResourceId"),
                                'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId IN ('. $requestTransIds .')
                            and b.FromCostCentreId='.$CostCentre.' Group By a.ResourceId ');
                        $sel6->combine($sel5,"Union ALL");

                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('ResourceId'=>new Expression("ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7->group(new Expression("A.ResourceId"));
                        $sel7 -> combine($sel4,"Union ALL");

                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId IN ('. $requestTransIds .')
                             and b.CostCentreId='.$CostCentre.' Group By a.ResourceId ');
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId IN ('. $requestTransIds .') Group By a.ResourceId ');
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('b.General=0 and b.CostCentreId='.$CostCentre.'
                                and a.ResourceId IN ('. $requestTransIds .') Group By a.ResourceId ');
                        $sel10->combine($sel9,"Union ALL");

                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CostCentre.'
                                     and a.ResourceId IN ('. $requestTransIds .') Group By a.ResourceId ');
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));
                        $sel12->group(new Expression("G.ResourceId"));

                        $statement = $sql->getSqlStringForSqlObject($sel12);
                        $arr_autoestdetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_autoestdetails));
                        return $response;
                        break;
                    case 'wbs':
                        $CostCentre = $this -> bsf ->isNullCheck($this->params()->fromPost('CostCentreId'),'number');
                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');

                        $wbsSelect = $sql->select();
                        $wbsSelect->from(array('a' => 'Proj_WBSMaster'))
                            ->columns(array(new Expression("a.WBSId,ParentText+'=>'+WbsName As WbsName,CAST(0 As Decimal(18,3)) As Qty")))
                            ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$wbsSelect::JOIN_INNER)
                            ->where(array("a.LastLevel" => "1", "b.CostCentreId" => $CostCentre));
                        $statement = $sql->getSqlStringForSqlObject($wbsSelect);
                        $arr_resource_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        $select = $sql->select();
                        $select->from(array("a" => "VM_RequestDecision"))
                            ->columns(array(new Expression('a.DecisionId,b.TransId As DecTransId,b.ReqTransId,a.RDecisionNo As DecisionNo,c.ResourceId,c.ItemId,CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3)) As BalQty,
                            Cast(0 as Decimal(18,3)) As Qty')))
                            ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
                            ->where('d.CostCentreId=' . $CostCentre . ' and (b.IndentQty-b.IndAdjQty)>0 And a.Approve='."'Y'".' And c.ResourceId='.$ResId.' And c.ItemId='.$ItemId.'');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_dectrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //Get WBS Estimate
                        $sel = $sql->select();
                        $sel->from(array("a" => "Proj_ProjectWBSResource"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),
                                'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                            ->Where ('b.CostCentreId=' . $CostCentre .' and  a.ResourceId='.$ResId.'
                                 and a.WBSId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$ResId.')');



                        $sel1 = $sql->select();
                        $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                                'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                            ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                            ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 and a.ResourceId='.$ResId.' And b.CostCentreId='.$CostCentre.' And d.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$ResId.' )');
                        $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel1->combine($sel,'Union ALL');


                        $sel2 = $sql -> select();
                        $sel2->from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                                'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                            ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' And c.CostCentreId='.$CostCentre .' And c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$ResId.' )');
                        $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel2->combine($sel1,"Union ALL");


                        $sel3 = $sql -> select();
                        $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                            ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                            ->where('c.ThruPO='."'Y'".' And a.ResourceId='.$ResId.' and c.CostCentreId='.$CostCentre.' and c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$ResId.' )');
                        $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel3->combine($sel2,"Union ALL");

                        $sel4 = $sql -> select();
                        $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                            ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                            ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' And c.CostCentreId='.$CostCentre.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$ResId.' )');
                        $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel4->combine($sel3,"Union ALL");

                        $sel5 = $sql -> select();
                        $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                            ->where('a.ResourceId ='.$ResId.' and c.ToCostCentreId='.$CostCentre.'
                                  And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$ResId.' )');
                        $sel5->group(new Expression("a.ResourceId,A.AnalysisId"));

                        $sel6 = $sql -> select();
                        $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                            -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                            ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                            ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                            ->where('a.ResourceId ='.$ResId.'and c.FromCostCentreId='.$CostCentre.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$ResId.' )');
                        $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel6->combine($sel5,"Union ALL");


                        $sel7 = $sql -> select();
                        $sel7 -> from(array("A"=>$sel6))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                        $sel7->group(new Expression("a.ResourceId,a.WBSId"));
                        $sel7 -> combine($sel4,"Union ALL");


                        $sel8 = $sql -> select();
                        $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                            ->where('a.ResourceId='.$ResId.' and c.CostCentreId='.$CostCentre.' and
                                 a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$ResId.')');
                        $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel8->combine($sel7,"Union ALL");

                        $sel9 = $sql -> select();
                        $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                                'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                            ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                            ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId ='.$ResId.'
                                  and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId='.$ResId.' )');
                        $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel9->combine($sel8,"Union ALL");

                        $sel10 = $sql -> select();
                        $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                            ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                            ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId='.$ResId.'
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId ='.$ResId.' )');
                        $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel10->combine($sel9,"Union ALL");


                        $sel11 = $sql -> select();
                        $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                            ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                                'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                                'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                                'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                            ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                            ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                            ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and a.ResourceId='.$ResId.'
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .'
                                 and a.ResourceId='.$ResId.') ');
                        $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
                        $sel11->combine($sel10,"Union ALL");

                        $sel12 = $sql -> select();
                        $sel12 -> from(array("G"=>$sel11))
                            ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),
                                'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                                'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                                'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                                'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                                'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                                'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                                'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));
                        $sel12->group(new Expression("G.ResourceId,G.WBSId"));

                        $statement = $sql->getSqlStringForSqlObject($sel12);
                        $arr_wbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        //
                        $response->setStatusCode('200');
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('dec' => $arr_resource_dectrans,'wbs' => $arr_resource_wbs,'estwbs' =>$arr_wbsestimate)));
                        return $response;
                        break;
                    case 'wbsEdit':
                        $CostCentre = $this -> bsf ->isNullCheck($this->params()->fromPost('CCId'),'number');
                        $ResId = $this->bsf->isNullCheck($this->params()->fromPost('ResourceId'), 'number');
                        $ItemId = $this->bsf->isNullCheck($this->params()->fromPost('ItemId'), 'number');
                        $PoRegId = $this->bsf->isNullCheck($this->params()->fromPost('PORegId'), 'number');

                        $select = $sql -> select();
                        $select -> from(array("a" => "MMS_POAnalTrans"))
                            ->columns(array('WBSId' => new Expression('a.AnalysisId'),'WbsName' =>new Expression("d.ParentText+'=>'+d.WBSName"),'Qty'=>new Expression("CAST(a.POQty As Decimal(18,3))") ))
                            ->join(array('b' => 'MMS_POProjTrans'), 'a.POProjTransId=b.POProjTransId',array(),$select::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'), 'b.POTransId=c.POTransId',array(),$select::JOIN_INNER)
                            ->join(array('d' => 'Proj_WbsMaster'), 'A.AnalysisId=D.WbsId',array(),$select::JOIN_INNER)
                            ->where('c.PORegisterId=' .$PoRegId. ' and a.ResourceId=' . $ResId . ' and a.ItemId=' .$ItemId . '');
                        $selR = $sql -> select();
                        $selR -> from (array('a' => 'MMS_POAnalTrans'))
                            -> columns(array('AnalysisId' => new Expression("a.AnalysisId")))
                            ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$selR::JOIN_INNER)
                            ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$selR::JOIN_INNER)
                            ->where('c.PORegisterId='.$PoRegId. 'and a.ResourceId=' .$ResId. 'and a.ItemId='.$ItemId. '');
                        $select1 = $sql -> select();
                        $select1 -> from (array("a" => "Proj_WBSMaster"))
                            ->columns(array('WBSId' => new Expression('a.WbsId'),'WbsName' => new Expression("a.ParentText+'=>'+a.WBSName"),'Qty'=>new Expression("CAST(0 As Decimal(18,3))")))
                            ->where('a.LastLevel=1 and a.ProjectId='.$CostCentre.' ');
                        $select1 -> where -> expression('(a.WbsId NOT IN ?)',array($selR));
                        $select->combine($select1,'Union ALL');

                        $statement = $sql->getSqlStringForSqlObject($select);
                        $arr_resource_wbs = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('wbs' => $arr_resource_wbs)));
                        return $response;
                        break;
                    case 'selectBranchContact':
                        $BranchId = $this -> bsf ->isNullCheck($this->params()->fromPost('branchid'),'number');
                        $selbranchContact = $sql -> select();
                        $selbranchContact -> from (array("a" => 'Vendor_Branch'))
                            -> columns(array('ContactNo' => new Expression("a.Phone")))
                            ->where('BranchId='.$BranchId.'');
                        $statement = $sql->getSqlStringForSqlObject($selbranchContact);
                        $arr_branchcontact = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_branchcontact));
                        return $response;
                        break;
                    case 'selectBranchCPerson':
                        $BranchId = $this -> bsf ->isNullCheck($this->params()->fromPost('branchid'),'number');
                        $selbranchcperson = $sql -> select();
                        $selbranchcperson -> from (array("a" => "Vendor_BranchContactDetail"))
                            ->columns(array('data' => new Expression("a.BranchTransId"),'value' => new Expression("a.ContactPerson")))
                            ->where('BranchId=' .$BranchId. '');
                        $statement = $sql->getSqlStringForSqlObject($selbranchcperson);
                        $arr_cperson = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_cperson));
                        return $response;
                        break;
                    case 'selectCPersonno':
                        $BranchTransId =$this -> bsf ->isNullCheck($this->params()->fromPost('branchtransid'),'number');
                        $selbranchcpersonno = $sql -> select();
                        $selbranchcpersonno -> from (array("a" => "Vendor_BranchContactDetail"))
                            ->columns(array('cpersonno' => new Expression("a.ContactNo")))
                            ->where('BranchTransId=' .$BranchTransId. '');
                        $statement = $sql->getSqlStringForSqlObject($selbranchcpersonno);
                        $arr_cpersonno = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_cpersonno));
                        return $response;
                        break;
                    case 'selectWHAddress':
                        $whTransId = $this -> bsf ->isNullCheck($this->params()->fromPost('whtransid'),'number');
                        $selwhadd = $sql -> select();
                        $selwhadd -> from (array("a" => 'MMS_WareHouse'))
                            ->columns(array('whaddress'=>new Expression("(A.Address+char(13)+c.CityName+char(9)+d.StateName+char(13)+e.CountryName+char(13)+a.PinCode)")))
                            ->join(array('b'=>"MMS_WareHouseDetails"),'a.WareHouseId=b.WareHouseId',array(),$selwhadd::JOIN_INNER)
                            ->join(array('c'=>"WF_CityMaster"),'a.CityId=c.CityId',array(),$selwhadd::JOIN_LEFT)
                            ->join(array('d'=>"WF_StateMaster"),'c.StateId=d.StateId',array(),$selwhadd::JOIN_LEFT)
                            ->join(array('e'=>"WF_CountryMaster"),'d.CountryId=e.CountryId',array(),$selwhadd::JOIN_LEFT)
                            ->where('b.TransId='.$whTransId.'');
                        $statement = $sql->getSqlStringForSqlObject($selwhadd);
                        $arr_whadd = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_whadd));
                        return $response;
                        break;
                    case 'getvencontact':
                        $VendorId = $this -> bsf ->isNullCheck($this->params()->fromPost('VendorId'),'number');
                        $selVenInfo = $sql -> select();
                        $selVenInfo -> from (array("a"=>"Vendor_BranchContactDetail"))
                            ->columns(array("BranchId"=>new Expression("b.BranchId"), "BranchName"=>new Expression("b.BranchName"), "ContactPerson"=>new Expression("a.ContactPerson"),"ContactNo"=>new Expression("a.ContactNo"),
                                "Email"=>new Expression("a.Email") ))
                            ->join(array('b'=>"Vendor_Branch"),'a.BranchId=b.BranchId',array(),$selVenInfo::JOIN_INNER)
                            ->where('b.VendorId='.$VendorId.'');
                        $statement = $sql->getSqlStringForSqlObject($selVenInfo);
                        $arr_vencontact = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $response->setStatusCode('200');
                        $response->setContent(json_encode($arr_vencontact));
                        return $response;
                        break;
                    case 'getpreORate':
                        $CostCentreId = $this -> bsf -> isNullCheck($this->params()->fromPost('CostCenterId'),'number');
                        $VendorId = $this -> bsf -> isNullCheck($this->params()->fromPost('VendorId'),'number');
                        $ResourceId = $this -> bsf -> isNullCheck($this->params()->fromPost('ResourceId'),'number');
                        $ItemId = $this -> bsf -> isNullCheck($this->params()->fromPost('ItemId'),'number');
                        $selORate = $sql -> select();
                        $selORate -> from (array("a" => "MMS_POTrans"))
                            ->columns(array("Id"=>new Expression("ROW_NUMBER() OVER(ORDER BY c.PORegisterId DESC)"),"Date"=>new Expression("Convert(Varchar(10),c.PODate,103)"),"PONo"=>new Expression("c.PONo"),
                                "CostCentre"=>new Expression("d.CostCentreName"),"Vendor"=>new Expression("f.VendorName"),
                                "Company"=>new Expression("e.CompanyName"),"Specification"=>new Expression("a.Description"),"Rate"=>new Expression("CAST(a.QRate As Decimal(18,2))") ))
                            ->join(array("b"=>"MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$selORate::JOIN_INNER)
                            ->join(array("c"=>"MMS_PORegister"),"a.PORegisterId=c.PORegisterId",array(),$selORate::JOIN_INNER)
                            ->join(array("d"=>"WF_OperationalCostCentre"),"c.CostCentreId=d.CostCentreId",array(),$selORate::JOIN_INNER)
                            ->join(array("e"=>"WF_CompanyMaster"),"d.CompanyId=e.CompanyId",array(),$selORate::JOIN_INNER)
                            ->join(array("f"=>"Vendor_Master"),"c.VendorId=f.VendorId",array(),$selORate::JOIN_INNER)
                            ->where('a.ResourceId='.$ResourceId.' and a.ItemId='.$ItemId.'')
                            ->order("c.PORegisterId Desc")
                            ->limit(3);

                        $statement = $sql->getSqlStringForSqlObject($selORate);
                        $arr_orate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $selcompid = $sql -> select();
                        $selcompid -> from (array("a" => "WF_OperationalCostCentre"))
                            ->columns(array("CompanyId"=>new Expression("a.CompanyId")))
                            ->where('a.CostCentreId='.$CostCentreId.'');
                        $statement = $sql->getSqlStringForSqlObject($selcompid);
                        $this->_view->CompanyId = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $CompanyId=$this->_view->CompanyId['CompanyId'];
                        if($CompanyId == '') {$CompanyId=0;}

                        $selCompRate = $sql -> select();
                        $selCompRate -> from (array("a" => "MMS_POTrans"))
                            ->columns(array("Id"=>new Expression("ROW_NUMBER() OVER(ORDER BY c.PORegisterId DESC)"),
                                "Date"=>new Expression("Convert(Varchar(10),c.PODate,103)"),"PONo"=>new Expression("c.PONo"),
                                "CostCentre"=>new Expression("d.CostCentreName"),"Vendor"=>new Expression("f.VendorName"),
                                "Company"=>new Expression("e.CompanyName"),"Specification"=>new Expression("a.Description"),
                                "Rate"=>new Expression("CAST(a.QRate As Decimal(18,2))") ))
                            ->join(array("b"=>"MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$selORate::JOIN_INNER)
                            ->join(array("c"=>"MMS_PORegister"),"a.PORegisterId=c.PORegisterId",array(),$selORate::JOIN_INNER)
                            ->join(array("d"=>"WF_OperationalCostCentre"),"c.CostCentreId=d.CostCentreId",array(),$selORate::JOIN_INNER)
                            ->join(array("e"=>"WF_CompanyMaster"),"d.CompanyId=e.CompanyId",array(),$selORate::JOIN_INNER)
                            ->join(array("f"=>"Vendor_Master"),"c.VendorId=f.VendorId",array(),$selORate::JOIN_INNER)
                            ->where('a.ResourceId='.$ResourceId.' and a.ItemId='.$ItemId.' and d.CompanyId='.$CompanyId.'')
                            ->order("c.PORegisterId Desc")
                            ->limit(3);
                        $statement = $sql->getSqlStringForSqlObject($selCompRate);
                        $arr_comprate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $selccRate = $sql -> select();
                        $selccRate -> from (array("a" => "MMS_POTrans"))
                            ->columns(array("Id"=>new Expression("ROW_NUMBER() OVER(ORDER BY c.PORegisterId DESC)"),
                                "Date"=>new Expression("Convert(Varchar(10),c.PODate,103)"),"PONo"=>new Expression("c.PONo"),
                                "CostCentre"=>new Expression("d.CostCentreName"),"Vendor"=>new Expression("f.VendorName"),
                                "Company"=>new Expression("e.CompanyName"),"Specification"=>new Expression("a.Description"),"Rate"=>new Expression("CAST(a.QRate As Decimal(18,2))") ))
                            ->join(array("b"=>"MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$selORate::JOIN_INNER)
                            ->join(array("c"=>"MMS_PORegister"),"a.PORegisterId=c.PORegisterId",array(),$selORate::JOIN_INNER)
                            ->join(array("d"=>"WF_OperationalCostCentre"),"c.CostCentreId=d.CostCentreId",array(),$selORate::JOIN_INNER)
                            ->join(array("e"=>"WF_CompanyMaster"),"d.CompanyId=e.CompanyId",array(),$selORate::JOIN_INNER)
                            ->join(array("f"=>"Vendor_Master"),"c.VendorId=f.VendorId",array(),$selORate::JOIN_INNER)
                            ->where('a.ResourceId='.$ResourceId.' and a.ItemId='.$ItemId.' and b.CostCentreId='.$CostCentreId.'')
                            ->order("c.PORegisterId Desc")
                            ->limit(3);
                        $statement = $sql->getSqlStringForSqlObject($selccRate);
                        $arr_ccprate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('orate' => $arr_orate,'comprate' => $arr_comprate,'ccrate' => $arr_ccprate)));
                        return $response;
                        break;
                    case 'validPODelete':
                        $PORegId = $this -> bsf -> isNullCheck($this->params()->fromPost('PORegisterId'),'number');
                        $selVal1 = $sql -> select();
                        $selVal1->from(array("a" => "MMS_PORegister"))
                               ->columns(array(new Expression("a.Approve As Approve")))
                                ->where("(a.Approve='Y' or a.Approve='P') and a.PORegisterId=$PORegId");
                        $statement = $sql->getSqlStringForSqlObject($selVal1);
                        $arr_val1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $selVal2 = $sql -> select();
                        $selVal2->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("a.AcceptQty")))
                            ->where("a.PORegisterId=$PORegId and (a.AcceptQty>0 Or a.BillQty>0) ");
                        $statement2 = $sql->getSqlStringForSqlObject($selVal2);
                        $arr_val2 = $dbAdapter->query($statement2, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $selVal3 = $sql -> select();
                        $selVal3->from(array("a" => "MMS_RequestCancel"))
                            ->columns(array(new Expression("a.PORegisterId")))
                            ->where("a.PORegisterId=$PORegId ");
                        $statement = $sql->getSqlStringForSqlObject($selVal3);
                        $arr_val3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $this->_view->setTerminal(true);
                        $response = $this->getResponse()->setContent(json_encode(array('arr1' => $arr_val1,'arr2' => $arr_val2,'arr3' => $arr_val3)));
                        return $response;
                        break;

                    case 'default':
                        $response->setStatusCode('404');
                        $response->setContent('Invalid request!');
                        return $response;
                        break;
                }

            }

        } else  {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postData = $request->getPost();
//                 echo"<pre>";
//                  print_r($postData);
//                 echo"</pre>";
//                  die;
//                 return;

                $OrderType = $this->bsf->isNullCheck($postData['OrderType'], 'string');

                if (!is_null($postData['frm_index'])) {
                    $CostCentre = $this->bsf->isNullCheck($postData['CostCentre'], 'number');
                    $VendorId = $this->bsf->isNullCheck($postData['VendorId'], 'number');

                    if (count($postData['requestTransIds']) > 0) {
                        $requestTransIds = implode(',', $postData['requestTransIds']);
                    } else {
                        $requestTransIds = 0;
                    }

                    $gridtype = $this->bsf->isNullCheck($postData['gridtype'], 'number');
                    $this->_view->gridtype = $gridtype;
                    $this->_view->OrderType = $OrderType;

                    if($flag == 2){
                        $select = $sql->select();
                        $select->from(array("a" => "VM_ReqDecQtyTrans"))
                            ->columns(array(new Expression("c.CostCentreId as CostCentreId")))
                            ->join(array("b" => "VM_RequestTrans"), 'a.ReqtransId=b.RequesttransId', array(), $select::JOIN_INNER)
                            ->join(array("c" => "VM_RequestRegister"), 'c.RequestId=b.RequestId', array(), $select::JOIN_INNER)
                            ->where('a.TransId IN(' .$requestTransIds. ')');
                        $selectStatement = $sql->getSqlStringForSqlObject($select);
                        $cvName = $dbAdapter->query($selectStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $CostCentre = $this->bsf->isNullCheck($cvName['CostCentreId'],'number');
                        $this->_view->flag = $flag;
                        $OrderType = 'material';
                    }

                    //Get CompanyId
                    $CompIdSel = $sql->select();
                    $CompIdSel->from(array("a" => "WF_OperationalCostCentre"))
                        ->columns(array('CompanyId'))
                        ->where("CostCentreId=$CostCentre");
                    $CompIdSelStatement = $sql->getSqlStringForSqlObject($CompIdSel);
                    $this->_view->Company = $dbAdapter->query($CompIdSelStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CompanyId = $this->_view->Company['CompanyId'];

                    //general
                    $voNo = CommonHelper::getVoucherNo(301, date('Y/m/d'), 0, 0, $dbAdapter, "");
                    $this->_view->voNo = $voNo;
                    $vNo = $voNo['voucherNo'];
                    $this->_view->vNo = $vNo;

                    //CompanyId
                    $CPo = CommonHelper::getVoucherNo(301, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                    $this->_view->CPo = $CPo;
                    $CPONo=$CPo['voucherNo'];
                    $this->_view->CPONo = $CPONo;

                    //CostCenterId
                    $CCPo = CommonHelper::getVoucherNo(301, date('Y/m/d'), 0, $CostCentre, $dbAdapter, "");
                    $this->_view->CCPo = $CCPo;
                    $CCPONo=$CCPo['voucherNo'];
                    $this->_view->CCPONo = $CCPONo;
                    $this->_view->valuefrom = 0;

                    // cost center details
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->where("Deactivate=0 AND CostCentreId=$CostCentre");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // vendor details
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                        ->where("VendorId=$VendorId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // vendors(contract)
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId','VendorName','LogoPath'))
                        ->where(array('Supply' => '1') );
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //CurrencyMaster
                    $select = $sql->select();
                    $select->from('WF_CurrencyMaster')
                        ->columns(array(new Expression("CurrencyId,CurrencyName + ' (' + CurrencyShort + ')' As CurrencyName")))
                        ->Order("DefaultCurrency Desc");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $currencyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->currencyList = $currencyList;

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId", "PurchaseTypeName"))
                        ->join(array("b" => "MMS_PurchaseTypeTrans"), "a.PurchaseTypeId=b.PurchaseTypeId", array("Default"), $select::JOIN_INNER)
                        ->join(array("c" => "WF_OperationalCostCentre"), "b.CompanyId=c.CompanyId", array(), $select::JOIN_INNER)
                        ->order("b.Default Desc")
                        ->where('c.CostCentreId=' . $CostCentre . ' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    $selBrDe = $sql->select();
                    $selBrDe->columns(array(new Expression("0 As BranchId,'Branch' As BranchName")));

                    $selBranch = $sql->select();
                    $selBranch->from(array("a" => "Vendor_Branch"))
                        ->columns(array("BranchId" => new Expression("a.BranchId"), "BranchName" => new Expression("a.BranchName")))
                        ->where('a.VendorId=' . $VendorId . '');
                    $selBrDe->combine($selBranch, 'Union ALL');
                    $branchStatement = $sql->getSqlStringForSqlObject($selBrDe);
                    $branch = $dbAdapter->query($branchStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->branch = $branch;

                    $selCCAddress = $sql->select();
                    $selCCAddress->from(array("a" => "WF_CostCentre"))
                        ->columns(array("Address" => new Expression("(a.Address+CHAR(13)+c.CityName+CHAR(9)+d.StateName+CHAR(13)+e.CountryName+CHAR(13)+a.Pincode)")))
                        ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.FACostCentreId", array(), $selCCAddress::JOIN_INNER)
                        ->join(array("c" => "WF_CityMaster"), "a.CityId=c.CityId", array(), $selCCAddress::JOIN_LEFT)
                        ->join(array("d" => "WF_StateMaster"), "c.StateId=d.StateId", array(), $selCCAddress::JOIN_LEFT)
                        ->join(array("e" => "WF_CountryMaster"), "d.CountryId=e.CountryId", array(), $selCCAddress::JOIN_LEFT)
                        ->where('b.CostCentreId=' . $CostCentre . '');
                    $selAddStatement = $sql->getSqlStringForSqlObject($selCCAddress);
                    $ccaddress = $dbAdapter->query($selAddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->ccaddress = $ccaddress;
                    $ccadd = $this->_view->ccaddress['Address'];
                    $this->_view->deladd = $ccadd;

                    $selwh1 = $sql->select();
                    $selwh1->columns(array(new Expression("0 As data,'None' As value")));

                    $selWareHouse = $sql->select();
                    $selWareHouse->from(array("a" => "MMS_WareHouseDetails"))
                        ->columns(array("data" => new Expression("a.TransId"), "value" => new Expression("b.WareHouseName +' - ' + a.Description")))
                        ->join(array("b" => "MMS_WareHouse"), "a.Warehouseid=b.Warehouseid", array(), $selWareHouse::JOIN_INNER)
                        ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $selWareHouse::JOIN_INNER)
                        ->where('c.CostCentreId=' . $CostCentre . ' and a.LastLevel=1');
                    $selwh1->combine($selWareHouse, 'Union ALL');
                    $selWhStatement = $sql->getSqlStringForSqlObject($selwh1);
                    $warehouse = $dbAdapter->query($selWhStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->warehouse = $warehouse;

                    $selcompdet = $sql->select();
                    $selcompdet->from(array("a" => "WF_CompanyMaster"))
                        ->columns(array("COContactPerson" => new Expression("a.ContactPerson"), "COPhone" => new Expression("a.Phone"),
                            "COMobile" => new Expression("a.Mobile"), "COEmail" => new Expression("a.Email"),
                            "CCContactPerson" => new Expression("C.ContactPerson"), "CCPhone" => new Expression("C.Phone"),
                            "CCMobile" => new Expression("C.Mobile"), "CCEmail" => new Expression("C.Email")))
                        ->join(array("b" => "WF_OperationalCostCentre"), "a.CompanyId=b.CompanyId", array(), $selcompdet::JOIN_INNER)
                        ->join(array("c" => "WF_CostCentre"), "b.FACostCentreId=c.CostCentreId", array(), $selcompdet::JOIN_INNER)
                        ->where('b.CostCentreId=' . $CostCentre . '');
                    $selcomStatement = $sql->getSqlStringForSqlObject($selcompdet);
                    $compdetails = $dbAdapter->query($selcomStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->compdetails = $compdetails;
                    $cccontact = $this->_view->compdetails['CCContactPerson'];
                    $ccphone = $this->_view->compdetails['CCPhone'];
                    $ccmobile = $this->_view->compdetails['CCMobile'];
                    $ccemail = $this->_view->compdetails['CCEmail'];
                    $ccontact = $this->_view->compdetails['COContactPerson'];
                    $cphone = $this->_view->compdetails['COPhone'];
                    $cmobile = $this->_view->compdetails['COMobile'];
                    $cemail = $this->_view->compdetails['COEmail'];
                    $this->_view->cccontact = $cccontact;
                    $this->_view->ccphone = $ccphone;
                    $this->_view->ccmobile = $ccmobile;
                    $this->_view->ccemail = $ccemail;
                    $this->_view->ccontact = $ccontact;
                    $this->_view->cphone = $cphone;
                    $this->_view->cmobile = $cmobile;
                    $this->_view->cemail = $cemail;

                    $selDis = $sql->select();
                    $selDis->from(array("a" => "Vendor_Master"))
                        ->columns(array(new Expression("a.VendorId,a.VendorName As VendorName")))
                        ->join(array("b" => "Vendor_SupplierDet"), "a.VendorId=b.SupplierVendorId", array(), $selDis::JOIN_INNER)
                        ->where('b.VendorId=' . $VendorId . '');
                    $selDisStatement = $sql->getSqlStringForSqlObject($selDis);
                    $distributor = $dbAdapter->query($selDisStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->distributorList = $distributor;

                    //Getting Default Currency Details
                    $selDCurr = $sql -> select();
                    $selDCurr -> from(array("a" => "WF_CurrencyMaster"))
                        ->columns(array(new Expression("CurrencyId,CurrencyShort As CurrencyShort")))
                        ->where('defaultcurrency=1');
                    $selDCurrStatement = $sql->getSqlStringForSqlObject($selDCurr);
                    $dcurrency = $dbAdapter->query($selDCurrStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->dCurrencyId = $dcurrency['CurrencyId'];
                    $this->_view->dCurrName = $dcurrency['CurrencyShort'];

                    //zoo

                    if ($OrderType == 'material') {
                        // get resource lists
                        $select = $sql->select();
                        $select->from(array("a" => "VM_ReqDecQtyTrans"))
                            ->columns(array(new Expression("b.ResourceId,b.ItemId,Case When b.ItemId>0 Then '(' + d.ItemCode + ')' + ' ' + d.BrandName Else '(' + c.Code + ')' + ' ' + c.ResourceName End As [Desc],
                                CAST(ISNULL(SUM(a.IndentQty-a.IndAdjQty),0) As Decimal(18,3)) As Qty,
                                Case When b.ItemId>0 Then d.Rate Else c.Rate End As Rate,
                                Case When b.ItemId>0 Then d.Rate Else c.Rate End As QRate,
                                CAST(0 As Decimal(18,2)) As BaseAmount,
                                CAST(0 As Decimal(18,2)) As Amount,Case When b.ItemId>0 Then f.UnitName Else e.UnitName End As UnitName,
                                Case When b.ItemId>0 Then f.UnitId Else e.UnitId End As UnitId,
                                RFrom = Case When b.ResourceId IN (Select ResourceId From Proj_ProjectResource Where ProjectId=4) Then 'Project' Else 'Library' End ")))
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
                                c.ResourceId,c.ItemId,CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3)) As BalQty,Cast((b.IndentQty-b.IndAdjQty) as Decimal(18,3)) As Qty')))
                            ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
                            ->where('d.CostCentreId=' . $CostCentre . ' and b.TransId IN (' . $requestTransIds . ') and CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3)) > 0');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $wbsRes = $sql -> select();
                        $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                            ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                            ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                            ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId and a.ProjectId=c.ProjectId',array(),$wbsRes::JOIN_INNER)
                            ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_INNER)
                            ->where("a.IncludeFlag=1 and d.CostCentreId=$CostCentre");
                        $statement = $sql->getSqlStringForSqlObject($wbsRes);
                        $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "VM_RequestDecision"))
                            ->columns(array(new Expression("f.ParentText+'->'+f.WBSName As WBSName,a.DecisionId,c.TransId As DecTransId,
                                c.RCATransId As DecATransId,c.ReqTransId,c.ReqAHTransId,d.ResourceId,d.ItemId,d.AnalysisId As WBSId,
                                CAST((c.IndentQty-c.IndAdjQty) As Decimal(18,3)) As BalQty,CAST((c.IndentQty-c.IndAdjQty) As Decimal(18,3)) As Qty")))
                            ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('c' => 'VM_ReqDecQtyAnalTrans'), 'b.TransId=c.TransId and b.DecisionId=c.DecisionId', array(), $select::JOIN_INNER)
                            ->join(array('d' => 'VM_RequestAnalTrans'), 'c.ReqAHTransId=d.RequestAHTransId and b.ReqTransId=d.ReqTransId', array(), $select::JOIN_INNER)
                            ->join(array('e' => 'VM_RequestTrans'), 'b.ReqTransId=e.RequestTransId and d.ReqTransId=e.RequestTransId', array(), $select::JOIN_INNER)
                            ->join(array('f' => 'Proj_WBSMaster'), 'd.AnalysisId=f.WBSId', array(), $select::JOIN_INNER)
                            ->join(array('g' => 'VM_RequestRegister'), 'e.RequestId=g.RequestId', array(), $select::JOIN_INNER)
                            ->where('g.CostCentreId=' . $CostCentre . ' and b.TransId IN (' . $requestTransIds . ') and CAST((c.IndentQty-c.IndAdjQty) As Decimal(18,3)) > 0 ');

                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "WF_TermsMaster"))
                            //->columns(array('data' => 'TermsId',))
                            ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,3)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross")))
                            ->where(array("TermType" => 'S'));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        $select = $sql->select();
                        $select->from(array("a" => "Proj_QualifierTrans"))
                            ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName', 'QualifierTypeId', 'RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                            ->columns(array('QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
                                'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                                'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxableAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'TaxAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'SurChargeAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                                'EDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'HEDCessAmt' => new Expression("CAST(0 As Decimal(18,2))"), 'NetAmt' => new Expression("CAST(0 As Decimal(18,2))")));
                        $select->where(array('a.QualType' => 'M'));
                        $select->order('a.SortId ASC');
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $sHtml = Qualifier::getQualifier($qualList);
                        $this->_view->qualHtml = $sHtml;
                        $this->_view->qualList = $qualList;
                    }

                    //Resource Auto Complete
                    $select = $sql->select();
                    $select->from(array("a" => "Proj_Resource"))
                        ->columns(array('data' => new Expression('a.ResourceId'),"AutoFlag"=>new Expression("1-1"), 'ItemId' => new Expression('isnull(d.BrandId,0)'),
                            'Code' => new Expression('Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End'),
                            'value' => new Expression("Case When isnull(d.BrandId,0)>0 Then '('+ d.ItemCode +')' + ' ' + d.BrandName Else '('+ a.Code +')' + ' ' + a.ResourceName End"),
                            'UnitName' => new Expression('Case when isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End'),
                            'UnitId' => new Expression('Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End'),
                            'Rate' => new Expression('Case when isnull(d.BrandId,0)>0 Then d.Rate Else a.Rate End'),
                            'RFrom' => new Expression("'Project'")))
                        ->join(array("b" => "Proj_ResourceGroup"), "a.ResourceGroupId=b.ResourceGroupId", array(), $select::JOIN_LEFT)
                        ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $select::JOIN_LEFT)
                        ->join(array("d" => "MMS_Brand"), "a.ResourceId=d.ResourceId", array(), $select::JOIN_LEFT)
                        ->join(array("e" => "Proj_ProjectResource"), "a.ResourceId=e.ResourceId", array(), $select::JOIN_INNER)
                        ->join(array("f" => "Proj_UOM"),"d.UnitId=f.UnitId",array(),$select::JOIN_LEFT)
                        ->join(array("g" => "WF_OperationalCostCentre"),"e.ProjectId=g.ProjectId",array(),$select::JOIN_INNER)
                        ->where("g.CostCentreId=" . $CostCentre . " and a.TypeId IN (2,3) ");

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then '('+ c.ItemCode +')' + ' ' + c.BrandName Else '('+ a.Code +')' + ' ' + a.ResourceName End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate Else a.Rate End As Rate,'Library' As RFrom ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select A.ResourceId From
                                Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=". $CostCentre .")  ");
                    $select->combine($selRa,"Union All");
                    if ($requestTransIds != 0) {
                        $select = $sql->select();
                        $select->from(array("a" => "Proj_Resource"))
                            ->columns(array('data' => new Expression('a.ResourceId'),"AutoFlag"=>new Expression("1-1"), 'ItemId' => new Expression('isnull(d.BrandId,0)'),
                                'Code' => new Expression('Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End'),
                                'value' => new Expression("Case When isnull(d.BrandId,0)>0 Then '('+ d.ItemCode +')' + ' ' + d.BrandName Else '('+ a.Code +')' + ' ' + a.ResourceName End"),
                                'UnitName' => new Expression('Case When isnull(d.BrandId,0)>0 Then f.UnitName Else c.UnitName End'),
                                'UnitId' => new Expression('Case when isnull(d.BrandId,0)>0 Then f.UnitId Else c.UnitId End'),
                                'Rate' => new Expression('Case when isnull(d.BrandId,0)>0 Then d.Rate Else e.Rate End '),
                                'RFrom' => new Expression("'Project'") ))
                            ->join(array("b" => "Proj_ResourceGroup"), "a.ResourceGroupId=b.ResourceGroupId", array(), $select::JOIN_LEFT)
                            ->join(array("c" => "Proj_UOM"), "a.UnitId=c.UnitId", array(), $select::JOIN_LEFT)
                            ->join(array("d" => "MMS_Brand"), "a.ResourceId=d.ResourceId", array(), $select::JOIN_LEFT)
                            ->join(array("e" => "Proj_ProjectResource"), "a.ResourceId=e.ResourceId", array(), $select::JOIN_INNER)
                            ->join(array("f" => "Proj_UOM"),"d.UnitId=f.UnitId",array(),$select::JOIN_LEFT )
                            ->join(array("g" => "WF_OperationalCostCentre"),"e.ProjectId=g.ProjectId",array(),$select::JOIN_INNER)
                            ->where("g.CostCentreId=" . $CostCentre . " and (a.ResourceId NOT IN (Select B.ResourceId From VM_ReqDecQtyTrans A
                             Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN (" . $requestTransIds . ")) Or
                             (isnull(d.BrandId,0) NOT IN (Select B.ItemId From VM_ReqDecQtyTrans A Inner Join VM_RequestTrans B On
                             A.ReqTransId=B.RequestTransId Where A.TransId IN (" . $requestTransIds . "))))");

                        $selRa = $sql -> select();
                        $selRa->from(array("a" => "Proj_Resource"))
                            ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then '('+ c.ItemCode +')' + ' ' + c.BrandName Else '('+ a.Code +')' + ' ' + a.ResourceName End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate Else a.Rate End As Rate,'Library' As RFrom ")))
                            ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                            ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                            ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                            ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                            ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select A.ResourceId From
                                Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=". $CostCentre .") and
                                (a.ResourceId NOT IN (Select B.ResourceId From VM_ReqDecQtyTrans A
                                Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN (" . $requestTransIds . ")) Or
                                (isnull(c.BrandId,0) NOT IN (Select B.ItemId From VM_ReqDecQtyTrans A Inner Join VM_RequestTrans B On
                                A.ReqTransId=B.RequestTransId Where A.TransId IN (" . $requestTransIds . "))))  ");

                        $select -> combine($selRa,"Union All");
                    }
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    // Material List
                    $select = $sql->select();
                    $select->from(array('a' => "Proj_Resource"))
                        ->join(array('b' => 'Proj_UOM'), 'a.UnitId=b.UnitId', array("unit" => 'UnitName'), $select:: JOIN_LEFT)
                        ->columns(array("data" => 'ResourceId', "value" => new Expression("a.Code + ' ' + a.ResourceName")))
                        ->where("a.DeleteFlag=0 AND a.TypeId=2");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->materiallists = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Get EstimateQty,EstimateRate,AvailableQty

                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectResource"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'), 'EstimateQty' => new Expression('a.Qty'),
                            'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentre .' And
                                     a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) ');


                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POTrans" ))
                        ->columns(array('ResourceId' => new Expression("a.ResourceId"), 'EstimateQty' => new Expression("CAST(0 As decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,2))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,2))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,2))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,2))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,2))"),'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,2))"),'BillQty' => new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And
                                a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                                  And b.CostCentreId='.$CostCentre.' And c.General=0 Group By a.ResourceId ');
                    $sel1->combine($sel,'Union ALL');



                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,2))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,2))"),'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('A.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                                     And B.CostCentreId='.$CostCentre .' And B.General=0 Group By a.ResourceId ');
                    $sel2->combine($sel1,"Union ALL");

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('a.ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,2))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('b.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                                     and b.CostCentreId='.$CostCentre.' and b.General=0 Group By a.ResourceId ');
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,2))"),'TotTranQty' => new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,2))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                                     And b.CostCentreId='.$CostCentre.' Group By a.ResourceId');
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId'=>new Expression("a.ResourceId"),'TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                        ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                                     and b.ToCostCentreId='.$CostCentre.' Group By a.ResourceId ');

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId'=>new Expression("a.ResourceId"),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                                     and b.FromCostCentreId='.$CostCentre.' Group By a.ResourceId ');
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('ResourceId'=>new Expression("ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,2))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))") ));
                    $sel7->group(new Expression("A.ResourceId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))") ))
                        ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                                     and b.CostCentreId='.$CostCentre.' Group By a.ResourceId ');
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,2))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) Group By a.ResourceId ');
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,2))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('b.General=0 and b.CostCentreId='.$CostCentre.'
                                and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) Group By a.ResourceId ');
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,2))")))
                        ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CostCentre.'
                                     and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) Group By a.ResourceId ');
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,3))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));
                    $sel12->group(new Expression("G.ResourceId"));
                    $statement = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_estimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //

                    //Get WBS Estimate
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectWBSResource"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,2))"),
                            'TotDCQty' => new Expression("Cast(0 As Decimal(18,2))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,2))"),'BillQty' => new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentre .' and  a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                 and a.WBSId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');



                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,2))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,2))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,2))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,2))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,2))"),'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,2))"),'BillQty' => new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) And b.CostCentreId='.$CostCentre.' And d.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel1->combine($sel,'Union ALL');


                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,2))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,2))"),'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) And c.CostCentreId='.$CostCentre .' And c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel2->combine($sel1,"Union ALL");


                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,2))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                        ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('c.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) and c.CostCentreId='.$CostCentre.' and c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty' => new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,2))"),'TotTranQty' => new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,2))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) And c.CostCentreId='.$CostCentre.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) and c.ToCostCentreId='.$CostCentre.'
                                  And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel5->group(new Expression("a.ResourceId,A.AnalysisId"));

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))and c.FromCostCentreId='.$CostCentre.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel6->combine($sel5,"Union ALL");


                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,2))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))") ));
                    $sel7->group(new Expression("a.ResourceId,a.WBSId"));
                    $sel7 -> combine($sel4,"Union ALL");


                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))") ))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) and c.CostCentreId='.$CostCentre.' and
                                 a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,2))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,2))") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                  and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,2))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,2))")))
                        ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('c.General=0 and c.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel10->combine($sel9,"Union ALL");


                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,2))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,2))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,2))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,2))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,2))")))
                        ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                        ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentre.' and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.'))
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentre .'
                                 and a.ResourceId IN (Select ResourceId From VM_ReqDecQtyTrans A
                                                    Inner Join VM_RequestTrans B On A.ReqTransId=B.RequestTransId Where A.TransId IN ('.$requestTransIds.')) )');
                    $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),
                            'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,3))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));
                    $sel12->group(new Expression("G.ResourceId,G.WBSId"));
                    $statement = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_wbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //

                }
            }
            else {
                if(isset($poRegId) && $poRegId!='') {

                    // cost center details
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->join(array('b'=>'MMS_PORegister'),'a.CostCentreId=b.CostCentreId',array(),$select::JOIN_INNER)
                        ->where("a.Deactivate=0 AND b.PORegisterId=$poRegId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                    // vendor details
                    $select = $sql->select();
                    $select->from(array('a'=>'Vendor_Master'))
                        ->columns(array('VendorId', 'VendorName', 'LogoPath'))
                        ->join(array('b'=>'MMS_PORegister'),'a.VendorId=b.VendorId')
                        ->where("b.PORegisterId=$poRegId");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $vendor = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $poaVendor = $vendor['VendorId'];
                    $this->_view->vendor = $vendor;



                    $regSelect = $sql->select();
                    $regSelect->from(array("PR" => "MMS_PoRegister"))
                        ->columns(array(new Expression("PR.PoRegisterId,PoNo = Case When PR.POAmend=0 Then PR.PONo+'-A1'
                                    Else  LEFT(PR.PONo, CHARINDEX('-A', PR.PONo))  + 'A'+ CAST((PR.POAmend+1) As Varchar(100)) End")))
                        ->join(array("SM" => "Vendor_Master"), "PR.VendorId=SM.VendorId", array(), $regSelect::JOIN_INNER)
                        ->where(array("PR.LivePO = 1 And PR.ShortClose=0 And PR.Approve='Y' And PR.VendorId= $poaVendor AND
                            PR.PORegisterId IN (Select PORegisterId From MMS_POTrans  Where
                            POTransId Not IN (Select POTransId From MMS_PVTrans WITH(READPAST) Where BillQty>0)And CancelQty=0)
                            And PR.PORegisterId NOT IN  (Select PORegisterId From MMS_RequestCancel) and PR.PORegisterId = $poRegId "))
                        ->order("PR.PoRegisterId Desc");
                    $regStatement = $sql->getSqlStringForSqlObject($regSelect);
                    $poaNo = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                    // vendors(contract)
                    $select = $sql->select();
                    $select->from('Vendor_Master')
                        ->columns(array('VendorId','VendorName','LogoPath'))
                        ->where(array('Supply' => '1') );
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_vendors = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    //CurrencyMaster
                    $select = $sql->select();
                    $select->from('WF_CurrencyMaster')
                        ->columns(array(new Expression("CurrencyId,CurrencyName + '(' + CurrencyShort + ')' As CurrencyName")))
                        ->Order("DefaultCurrency Desc");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $currencyList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->currencyList = $currencyList;

                    //Get PODistributorTrans
                    $selD = $sql->select();
                    $selD->from(array("a"=>"MMS_PODistributorTrans"))
                        ->columns(array(new Expression('a.VendorId As VendorId')))
                        ->join(array("b"=>"MMS_PORegister"),"a.PORegisterId=b.PORegisterId",array(),$selD::JOIN_INNER)
                        ->where(array("b.PORegisterId"=>$poRegId));
                    $statement = $sql->getSqlStringForSqlObject($selD);
                    $distedit = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $data=array();
                    foreach($distedit as $dedit) {
                        $data[]=$dedit['VendorId'];
                    }
                    $this->_view->data = $data;

                    $selPOReg=$sql->select();
                    $selPOReg->from(array('a'=>'MMS_PORegister'))
                        ->columns(array(new Expression("a.CostCentreId,a.VendorId,a.BranchId,a.BranchTransId,a.CurrencyId,
                            b.CostCentreName,c.VendorName,a.PODate,A.ReqDate,A.PONo,A.CCPONo,A.CPONo,A.ReqNo,A.PurchaseTypeId,
                            A.PurchaseAccount As PurchaseAccount,A.Narration As Narration,A.BranchId,
                            A.BranchTransId As BranchTransId,PoDelId,PoDelAdd,ProjectAddress,
                            A.CompanyContactName,A.CompanyContactNo,A.CompanyMobile,A.CompanyEmail,
                            A.SiteContactName,A.SiteContactNo,A.SiteMobile,A.SiteEmail,
                            Case When A.Approve='Y' Then 'Yes' When A.Approve='P' Then 'Partial' Else 'No' End As Approve,a.GridType")))
                        ->join(array('b'=>'WF_OperationalCostCentre'),'a.CostCentreId=b.CostCentreId',array(),$selPOReg::JOIN_INNER)
                        ->join(array('c'=>'Vendor_Master'),'a.VendorId=c.VendorId',array(),$selPOReg::JOIN_INNER)
                        ->where(array("a.PORegisterId"=>$poRegId));
                    $statement = $sql->getSqlStringForSqlObject( $selPOReg );
                    $this->_view->poregister = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CostCentre = $this->_view->poregister['CostCentreName'];
                    $CostCentreId=$this->_view->poregister['CostCentreId'];
                    $VendorId = $this->_view->poregister['VendorId'];
                    $CurrId=$this->_view->poregister['CurrencyId'];
                    $PurTypeId=$this->_view->poregister['PurchaseTypeId'];
                    $AccountId=$this->_view->poregister['PurchaseAccount'];
                    $Narration=$this->_view->poregister['Narration'];
                    $CCPONo = $this -> _view->poregister['CCPONo'];
                    $CPONo = $this -> _view->poregister['CPONo'];
                    $ReqNo = $this -> _view->poregister['ReqNo'];
                    $branchId=$this->_view->poregister['BranchId'];
                    $branchtransId=$this->_view->poregister['BranchTransId'];
                    $deladd=$this->_view->poregister['ProjectAddress'];
                    $whid=$this->_view->poregister['PoDelId'];
                    $whadd=$this->_view->poregister['PoDelAdd'];
                    $cccontact=$this->_view->poregister['SiteContactName'];
                    $ccphone=$this->_view->poregister['SiteContactNo'];
                    $ccmobile=$this->_view->poregister['SiteMobile'];
                    $ccemail=$this->_view->poregister['SiteEmail'];
                    $ccontact=$this->_view->poregister['CompanyContactName'];
                    $cphone=$this->_view->poregister['CompanyContactNo'];
                    $cmobile=$this->_view->poregister['CompanyMobile'];
                    $cemail=$this->_view->poregister['CompanyEmail'];
                    $approve=$this->_view->poregister['Approve'];
                    $gridtype=$this->_view->poregister['GridType'];

                    $this->_view->poRegId = $poRegId;
                    $this->_view->currency= $CurrId;
                    $this->_view->purchasetype= $PurTypeId;
                    $this->_view->accounttype=$AccountId;
                    $this->_view->narration=$Narration;
                    $this->_view->branchid=$branchId;
                    $this->_view->branchtransid=$branchtransId;
                    $this->_view->deladd=$deladd;
                    $this->_view->whid=$whid;
                    $this->_view->whadd=$whadd;
                    $this->_view->cccontact = $cccontact;
                    $this->_view->ccphone = $ccphone;
                    $this->_view->ccmobile = $ccmobile;
                    $this->_view->ccemail = $ccemail;
                    $this->_view->ccontact = $ccontact;
                    $this->_view->cphone = $cphone;
                    $this->_view->cmobile = $cmobile;
                    $this->_view->cemail = $cemail;
                    $this->_view->CCPONo = $CCPONo;
                    $this->_view->CPONo = $CPONo;
                    $this->_view->RefNo = $ReqNo;
                    $this->_view->approve = $approve;
                    $this->_view->gridtype = $gridtype;
                    $this->_view->flag = $flag;


                    if($flag == 1){
                        $vNo = $poaNo['PoNo'];
                    } else {
                        $vNo = $this->_view->poregister['PONo'];
                    }
                    $this->_view->vNo = $vNo;


                    $selTer = $sql -> select();
                    $selTer -> from (array("a" => "MMS_POPaymentTerms"))
                        ->columns(array("ValueFromNet"))
                        ->where('PORegisterId='.$poRegId.'');
                    $terStatement = $sql->getSqlStringForSqlObject($selTer);
                    $terResult = $dbAdapter->query($terStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->valuefrom=$this->bsf->isNullCheck($terResult['ValueFromNet'],'number');


                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_PurchaseType"))
                        ->columns(array("PurchaseTypeId","PurchaseTypeName"))
                        ->join(array("b"=>"MMS_PurchaseTypeTrans"),"a.PurchaseTypeId=b.PurchaseTypeId",array(),$select::JOIN_INNER)
                        ->join(array("c"=>"WF_OperationalCostCentre"),"b.CompanyId=c.CompanyId",array(),$select::JOIN_INNER)
                        ->where('c.CostCentreId='.$CostCentreId.' and b.Sel=1');
                    $typeStatement = $sql->getSqlStringForSqlObject($select);
                    $purchaseType = $dbAdapter->query($typeStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->purchaseType = $purchaseType;

                    $selBrDe = $sql -> select();
                    $selBrDe -> columns(array(new Expression("0 As BranchId,'Branch' As BranchName") ));

                    $selBranch = $sql -> select();
                    $selBranch->from(array("a" => "Vendor_Branch"))
                        ->columns(array("BranchId"=>new Expression("a.BranchId"),"BranchName"=>new Expression("a.BranchName")))
                        ->where('a.VendorId='. $VendorId .'');
                    $selBrDe->combine($selBranch, 'Union ALL');

                    $branchStatement = $sql->getSqlStringForSqlObject($selBrDe);
                    $branch = $dbAdapter->query($branchStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->branch = $branch;

                    $selbranchContact = $sql -> select();
                    $selbranchContact -> from (array("a" => 'Vendor_Branch'))
                        -> columns(array('ContactNo' => new Expression("a.Phone")))
                        ->where('BranchId='.$branchId.'');
                    $branchcontactStatement = $sql->getSqlStringForSqlObject($selbranchContact);
                    $cPerno = $dbAdapter->query($branchcontactStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->bcNo = $cPerno["ContactNo"];

                    $selbranchcperson = $sql -> select();
                    $selbranchcperson -> from (array("a" => "Vendor_BranchContactDetail"))
                        ->columns(array('data' => new Expression("a.BranchTransId"),'value' => new Expression("a.ContactPerson")))
                        ->where('BranchId=' .$branchId. '');
                    $statement = $sql->getSqlStringForSqlObject($selbranchcperson);
                    $cPerson = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->cPerson = $cPerson;

                    $selbranchcpersonno = $sql -> select();
                    $selbranchcpersonno -> from (array("a" => "Vendor_BranchContactDetail"))
                        ->columns(array('cpersonno' => new Expression("a.ContactNo")))
                        ->where('BranchTransId=' .$branchtransId. '');
                    $cperStatement = $sql->getSqlStringForSqlObject($selbranchcpersonno);
                    $cbranchperno = $dbAdapter->query($cperStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->cpNo = $cbranchperno["cpersonno"];

                    $selAcc=$sql->select();
                    $selAcc->from(array("a"=>"FA_AccountMaster"))
                        ->columns(array(new Expression('A.AccountId As data,A.AccountName As value')))
                        ->join(array("b"=>"MMS_PurchaseType"),"a.AccountId=b.AccountId",array(),$selAcc::JOIN_INNER)
                        ->where(array("b.PurchaseTypeId"=>$PurTypeId));
                    $accStatement = $sql->getSqlStringForSqlObject($selAcc);
                    $accType = $dbAdapter->query($accStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->accType = $accType;

                    //Getting Default Currency Details
                    $selDCurr = $sql -> select();
                    $selDCurr -> from(array("a" => "WF_CurrencyMaster"))
                        ->columns(array(new Expression("CurrencyId,CurrencyShort As CurrencyShort")))
                        ->where('defaultcurrency=1');
                    $selDCurrStatement = $sql->getSqlStringForSqlObject($selDCurr);
                    $dcurrency = $dbAdapter->query($selDCurrStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->dCurrencyId = $dcurrency['CurrencyId'];
                    $this->_view->dCurrName = $dcurrency['CurrencyShort'];

                    //

                    if($deladd == '') {
                        $selCCAddress = $sql->select();
                        $selCCAddress->from(array("a" => "WF_CostCentre"))
                            ->columns(array("Address" => new Expression("(a.Address+CHAR(13)+c.CityName+CHAR(9)+d.StateName+CHAR(13)+e.CountryName+CHAR(13)+a.Pincode)")))
                            ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.FACostCentreId", array(), $selCCAddress::JOIN_INNER)
                            ->join(array("c" => "WF_CityMaster"), "a.CityId=c.CityId", array(), $selCCAddress::JOIN_LEFT)
                            ->join(array("d" => "WF_StateMaster"), "c.StateId=d.StateId", array(), $selCCAddress::JOIN_LEFT)
                            ->join(array("e" => "WF_CountryMaster"), "d.CountryId=e.CountryId", array(), $selCCAddress::JOIN_LEFT)
                            ->where('b.CostCentreId=' . $CostCentreId . '');
                        $selAddStatement = $sql->getSqlStringForSqlObject($selCCAddress);
                        $ccaddress = $dbAdapter->query($selAddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $this->_view->ccaddress = $ccaddress;
                        $ccadd = $this->_view->ccaddress['Address'];
                        $this->_view->deladd = $ccadd;
                    }

                    $selwh1 = $sql -> select();
                    $selwh1 -> columns(array(new Expression("0 As data,'None' As value") ));

                    $selWareHouse = $sql -> select();
                    $selWareHouse->from(array("a" => "MMS_WareHouseDetails"))
                        ->columns(array("data"=>new Expression("a.TransId"),"value"=>new Expression("b.WareHouseName +' - ' + a.Description")))
                        ->join(array("b"=>"MMS_WareHouse"),"a.Warehouseid=b.Warehouseid",array(),$selWareHouse::JOIN_INNER)
                        ->join(array("c"=>"MMS_CCWareHouse"),"b.WareHouseId=c.WareHouseId",array(),$selWareHouse::JOIN_INNER)
                        ->where('c.CostCentreId='.$CostCentreId.' and a.LastLevel=1');
                    $selwh1->combine($selWareHouse, 'Union ALL');
                    $selWhStatement = $sql->getSqlStringForSqlObject($selwh1);
                    $warehouse = $dbAdapter->query($selWhStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->warehouse = $warehouse;

                    $selDis=$sql->select();
                    $selDis->from(array("a"=>"Vendor_Master"))
                        ->columns(array(new Expression("a.VendorId,a.VendorName As VendorName")))
                        ->join(array("b"=>"Vendor_SupplierDet"),"a.VendorId=b.SupplierVendorId",array(),$selDis::JOIN_INNER)
                        ->where('b.VendorId='.$VendorId.'');
                    $selDisStatement = $sql->getSqlStringForSqlObject($selDis);
                    $distributor = $dbAdapter->query($selDisStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->distributorList = $distributor;

                    $select = $sql->select();
                    $select->from(array("a"=>"MMS_POTrans"))
                        ->columns(array(new Expression("a.POTransId,a.ResourceId,a.ItemId,Case When a.ItemId>0 Then '(' + c.ItemCode +')'+ '  '  + c.BrandName Else '(' + b.Code +')'+ '  '  + b.ResourceName End As [Desc],
                             d.UnitName,CAST(A.POQty As Decimal(18,3)) As Qty,A.POQty As HiddenQty,Case When a.AcceptQty>0 Then a.AcceptQty When a.BillQty>0 Then a.BillQty Else a.AcceptQty End As DCQty,
                             CAST(A.Rate As Decimal(18,2)) As Rate,CAST(A.QRate As Decimal(18,2)) As QRate,
                             CAST(A.Amount As Decimal(18,2)) As BaseAmount,CAST(A.QAmount As Decimal(18,2)) As Amount,
                             A.UnitId,d.UnitName,a.Description As ResSpec,Convert(Varchar(10),a.ReqDate,105) As ReqDate,
                             RFrom = Case When a.ResourceId IN (Select A.ResourceId From Proj_ProjectResource A
                             Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=$CostCentreId) Then 'Project' Else 'Library' End    ")))
                        ->join(array('b'=>'Proj_Resource'),'a.ResourceId=b.ResourceId',array(),$select::JOIN_INNER)
                        ->join(array('c'=>'MMS_Brand'),'a.ResourceId=b.ResourceId and a.ItemId=c.BrandId',array(),$select::JOIN_LEFT)
                        ->join(array('d'=>'Proj_UOM'),'a.UnitId=d.UnitId',array(),$select::JOIN_LEFT)
                        ->where('a.PORegisterId='.$poRegId.'');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    //$this->_view->arr_requestResources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $poTrans = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $this->_view->arr_requestResources = $poTrans;

                    $selSub1 = $sql -> select();
                    $selSub1 -> from (array("a" => "VM_RequestDecision"))
                        ->columns(array('DecisionId'=>new Expression('a.DecisionId'),'DecTransId'=>new Expression('b.TransId'),
                            'ReqTransId'=>new Expression('b.ReqTransId'),'DecisionNo'=>new Expression('a.RDecisionNo'),
                            'ResourceId'=>new Expression('c.ResourceId'),'ItemId'=>new Expression('c.ItemId'),
                            'BalQty'=>new Expression('CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3))'),'Qty'=>new Expression('CAST(0 As Decimal(18,3))'),
                            'HiddenQty'=>new Expression('CAST(0 As Decimal(18,3))')))
                        ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId',array(),$selSub1::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestTrans'),'b.ReqTransId=c.RequestTransId',array(),$selSub1::JOIN_INNER)
                        ->join(array('d' => 'VM_RequestRegister'),'c.RequestId=d.RequestId',array(),$selSub1::JOIN_INNER)
                        ->where ('d.CostCentreId='.$CostCentreId.' And CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3))>0 And d.Approve='."'Y'".'
                         And (c.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='.$poRegId.') And
                         c.ItemId IN (Select ItemId From MMS_POTrans Where PORegisterId='.$poRegId.')) And
                         b.TransId NOT IN (Select DecTransId From MMS_IPDTrans A Inner Join MMS_POTrans B On A.POTransId=B.POTransId Where B.PORegisterId='.$poRegId.') ');

                    $select = $sql->select();
                    $select->from(array("a" => "VM_RequestDecision"))
                        ->columns(array(new Expression('a.DecisionId,b.TransId As DecTransId,b.ReqTransId,
                        a.RDecisionNo As DecisionNo,c.ResourceId,c.ItemId,
                        CAST((b.IndentQty-b.IndAdjQty) As Decimal(18,3)) As BalQty,
                        Cast(e.Qty as Decimal(18,3)) As Qty,Cast(e.Qty as Decimal(18,3)) As HiddenQty')))
                        ->join(array('b' => 'VM_ReqDecQtyTrans'), 'a.DecisionId=b.DecisionId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestTrans'), 'b.ReqTransId=c.RequestTransId', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'VM_RequestRegister'), 'c.RequestId=d.RequestId', array(), $select::JOIN_INNER)
                        ->join(array('e' => 'MMS_IPDTrans'),'a.DecisionId=e.DecisionId And b.TransId=e.DecTransId',array(),$select::JOIN_INNER)
                        ->join(array('f' => 'MMS_POTrans'),'e.POTransId=f.POTransId',array(),$select::JOIN_INNER)
                        ->where('f.PORegisterId=' . $poRegId . ' ');
                    $select->combine($selSub1,'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iows = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $wbsRes = $sql -> select();
                    $wbsRes -> from (array('a' => 'Proj_ProjectDetails'))
                        ->columns(array(new Expression("distinct a.ResourceId,c.WBSId As WBSId")))
                        ->join(array('b' => 'Proj_ProjectIOW'),'a.ProjectIOWId=b.ProjectIOWId',array(),$wbsRes::JOIN_INNER )
                        ->join(array('c' => 'Proj_WBSTrans'),'b.ProjectIOWId=c.ProjectIOWId and a.ProjectId=c.ProjectId',array(),$wbsRes::JOIN_INNER)
                        ->join(array('d' => 'WF_OperationalCostCentre'),'a.ProjectId=d.ProjectId',array(),$wbsRes::JOIN_INNER)
                        ->where("a.IncludeFlag=1 and D.CostCentreId=$CostCentreId");
                    $statement = $sql->getSqlStringForSqlObject($wbsRes);
                    $this->_view->arr_res_wbs= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                    $selASub1 = $sql -> select();
                    $selASub1 -> from (array("a" => "VM_RequestDecision"))
                        ->columns(array('WBSName'=>new Expression("F.ParentText+'->'+F.WBSName"),
                            'DecisionId'=>new Expression('c.DecisionId'),
                            'DecTransId'=>new Expression('c.TransId'),'DecATransId'=>new Expression('c.RCATransId'),
                            'ReqTransId'=>new Expression('e.RequestTransId'),
                            'ReqAHTransId'=>new Expression('d.RequestAHTransId'),'ResourceId'=>new Expression('e.ResourceId'),'ItemId'=>new Expression('e.ItemId'),
                            'WBSId'=>new Expression('d.AnalysisId'),'BalQty'=>new Expression('CAST((c.IndentQty-c.IndAdjQty) As Decimal(18,3))'),
                            'Qty'=>new Expression('CAST(0 As Decimal(18,3))'),'HiddenQty'=>new Expression('CAST(0 As Decimal(18,3))')))
                        ->join(array('b' => 'VM_ReqDecQtyTrans'),'a.DecisionId=b.DecisionId',array(),$selASub1::JOIN_INNER)
                        ->join(array('c' => 'VM_ReqDecQtyAnalTrans'),'b.TransId=c.TransId And b.DecisionId=c.DecisionId',array(),$selASub1::JOIN_INNER)
                        ->join(array('d' => 'VM_RequestAnalTrans'),'c.ReqAHTransId=d.RequestAHTransId And b.ReqTransId=d.ReqTransId',array(),$selASub1::JOIN_INNER)
                        ->join(array('e' => 'VM_RequestTrans'),'b.ReqTransId=e.RequestTransId And d.ReqTransId=e.RequestTransId',array(),$selASub1::JOIN_INNER)
                        ->join(array('f' => 'Proj_WBSMaster'),'d.AnalysisId=f.WBSId',array(),$selASub1::JOIN_INNER)
                        ->join(array('g' => 'VM_RequestRegister'),'e.RequestId=g.RequestId',array(),$selSub1::JOIN_INNER)
                        ->where ('g.CostCentreId='.$CostCentreId.' And c.RCATransId NOT IN (Select DecATransId From MMS_IPDAnalTrans A
                         Inner Join MMS_POAnalTrans B On a.POAHTransId=b.POAnalTransId Inner Join MMS_POProjTrans C On b.POProjTransId=c.POProjTransId
                         Inner Join MMS_POTrans D On c.POTransId=d.POTransId Where d.PORegisterId='.$poRegId.' And a.Status='."'P'".') and
                         (e.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='.$poRegId.') and e.ItemId IN (Select ItemId From
                         MMS_POTrans Where PORegisterId='.$poRegId.')) And A.Approve='."'Y'".'');

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_IPDAnalTrans"))
                        ->columns(array(new Expression("F.ParentText+'->'+F.WBSName As WBSName,c.DecisionId,
                        c.DecTransId,e.RCATransId As DecATransId,e.ReqTransId,e.ReqAHTransId,
                            d.ResourceId,d.ItemId,a.AnalysisId As WBSId,
                            CAST((e.IndentQty-e.IndAdjQty) As Decimal(18,3)) As BalQty,
                            CAST(a.Qty As Decimal(18,3)) As Qty,CAST(a.Qty As Decimal(18,3)) As HiddenQty ")))
                        ->join(array('b'=>'MMS_IPDProjTrans'),'a.IPDProjTransId=b.IPDProjTransId',array(),$select::JOIN_INNER)
                        ->join(array('c'=>'MMS_IPDTrans'),'b.IPDTransId=c.IPDTransId',array(),$select::JOIN_INNER)
                        ->join(array('d'=>'MMS_POTrans'),'c.POTransId=d.POTransId',array(),$select::JOIN_INNER)
                        ->join(array('j'=>'MMS_PORegister'),'d.PORegisterId=j.PORegisterId',array(),$select::JOIN_INNER)
                        ->join(array('e'=>'VM_ReqDecQtyAnalTrans'),'a.DecATransId=e.RCATransId And A.DecTransId=e.TransId',array(),$select::JOIN_INNER)
                        ->join(array('f'=>'Proj_WBSMaster'),'a.AnalysisId=f.WBSId',array(),$select::JOIN_INNER)
                        ->where('j.PORegisterId='. $poRegId .'');
                    $select->combine($selASub1,'Union ALL');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resource_iow_requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "Proj_QualifierTrans"))
                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
                        ->columns(array('QualifierId','YesNo','Expression','ExpPer','TaxablePer','TaxPer','Sign','SurCharge','EDCess','HEDCess','NetPer',
                            'BaseAmount'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt' => new Expression("CAST(0 As Decimal(18,2))"),
                            'TaxableAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'TaxAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'SurChargeAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'EDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),
                            'HEDCessAmt'=> new Expression("CAST(0 As Decimal(18,2))"),'NetAmt'=> new Expression("CAST(0 As Decimal(18,2))")));
                    $select->where(array('a.QualType' => 'M'));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    $sHtml=Qualifier::getQualifier($qualList);
                    $this->_view->qualHtml = $sHtml;

//                    $select = $sql->select();
//                    $select->from(array("c" => "MMS_POQualTrans"))
//                        ->join(array("a" => "Proj_QualifierTrans"),"c.QualifierId=a.QualifierId",array(),$select::JOIN_INNER)
//                        ->join(array("b" => "Proj_QualifierMaster"), "a.QualifierId=b.QualifierId", array('QualifierName','QualifierTypeId','RefId' => new Expression("RefNo")), $select::JOIN_INNER)
//                        ->columns(array(new Expression("c.ResourceId,c.ItemId,c.QualifierId,Case When c.YesNo=1 Then 'off' Else 'on' End As YesNo,c.Expression,c.ExpPer,
//                                 c.TaxablePer,c.TaxPer,c.Sign,c.SurCharge,c.EDCess,c.HEDCess,c.NetPer,0 As BaseAmount,c.ExpressionAmt,c.TaxableAmt,c.TaxAmt,c.SurChargeAmt,c.EDCessAmt,c.HEDCessAmt,c.NetAmt")));
//                    $select->where(array('a.QualType' => 'M','c.PORegisterId'=>$poRegId));
//                    $select->order('a.SortId ASC');
//                    $statement = $sql->getSqlStringForSqlObject($select);
//                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $sHtml=Qualifier::getQualifier($qualList);
//                    $this->_view->qualHtml = $sHtml;

                    $arrqual = array();
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POQualTrans"))
                        ->columns(array('ResourceId','ItemId','QualifierId', 'YesNo', 'Expression', 'ExpPer', 'TaxablePer', 'TaxPer', 'Sign', 'SurCharge', 'EDCess', 'HEDCess', 'NetPer',
                            'BaseAmount' => new Expression("CAST(0 As Decimal(18,2))"),
                            'ExpressionAmt', 'TaxableAmt', 'TaxAmt', 'SurChargeAmt',
                            'EDCessAmt', 'HEDCessAmt', 'NetAmt'));
                    $select->where(array('a.PORegisterId'=>$poRegId));
                    $select->order('a.SortId ASC');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qualList = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
//                    $sHtml = Qualifier::getQualifier($qualList);
//                    $arrqual = $sHtml;
                    $this->_view->arr_qual_list = $qualList;


                    $select = $sql->select();
                    $select->from(array("a" => "WF_TermsMaster"))
                        //->columns(array('data' => 'TermsId',))
                        ->columns(array(new Expression("TermsId As data,SlNo,Title As value,CAST(0 As Decimal(18,3)) As Per,
                                CAST(0 As Decimal(18,2)) As Val,0 As Period,NULL As [Dte],'' As [Strg],Per As IsPer,
                                Value As IsValue,Period As IsPeriod,TDate As IsTDate,TSTring As IsTString,IncludeGross")))
                        ->where(array("TermType"=>'S'));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $select = $sql->select();
                    $select->from(array("a" => "WF_TermsMaster"))
                        //->columns(array('data' => 'TermsId',))
                        ->columns(array(new Expression("a.TermsId As data,a.SlNo,a.Title As value,CAST(b.Per As Decimal(18,3)) As Per,
                                CAST(b.Value As Decimal(18,2)) As Val,b.Period As Period,
                                Convert(Varchar(10),b.TDate,103) As Dte,
                                b.TString As [Strg],a.Per As IsPer,
                                a.Value As IsValue,a.Period As IsPeriod,a.TSTring As IsTString,a.IncludeGross")))
                        ->join(array('b'=>'MMS_POPaymentTerms'),'a.TermsId=b.TermsId',array(),$select::JOIN_INNER)
                        ->where(array("b.PORegisterId"=>$poRegId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_edit_terms = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Resource Auto Complete
                    $select = $sql -> select();
                    $select -> from (array("a" => "Proj_Resource"))
                        ->columns(array('data'=>new Expression('a.ResourceId'),"AutoFlag"=>new Expression("1-1"),'ItemId'=>new Expression('isnull(d.BrandId,0)'),
                            'Code'=>new Expression('Case When isnull(d.BrandId,0)>0 Then d.ItemCode Else a.Code End'),
                            'value'=>new Expression('Case When isnull(d.BrandId,0)>0 Then '."'('".' + d.ItemCode + '."')'".'+'."'  '".' + d.BrandName Else '."'('".' + a.Code + '."')'".' + '."'  '".'  + a.ResourceName End'),
                            'UnitName'=>new Expression('c.UnitName'),'UnitId'=>new Expression('c.UnitId'),
                            'Rate'=>new Expression('Case When isnull(d.BrandId,0)>0 Then d.Rate Else e.Rate End'),
                            'RFrom'=>new Expression("'Project'")  ))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$select::JOIN_INNER)
                        ->join(array("c" => "Proj_UOM"),"a.UnitId=c.UnitId",array(),$select::JOIN_LEFT)
                        ->join(array("d" => "MMS_Brand"),"a.ResourceId=d.ResourceId",array(),$select::JOIN_LEFT)
                        ->join(array("e" => "Proj_ProjectResource"),"a.ResourceId=e.ResourceId",array(),$select::JOIN_INNER)
                        ->join(array("f" => "WF_OperationalCostCentre"),"e.ProjectId=f.ProjectId",array(),$select::JOIN_INNER)
                        ->where("f.CostCentreId=".$CostCentreId." and (a.ResourceId NOT IN (Select ResourceId From MMS_POTrans
                         Where PORegisterId=".$poRegId.") Or isnull(d.BrandId,0) NOT IN (Select ItemId From MMS_POTrans Where PORegisterId=".$poRegId."))");

                    $selRa = $sql -> select();
                    $selRa->from(array("a" => "Proj_Resource"))
                        ->columns(array(new Expression("a.ResourceId As data,1 as AutoFlag,isnull(c.BrandId,0) As ItemId,
                                Case When isnull(c.BrandId,0)>0 Then c.ItemCode Else a.Code End As Code,
                                Case when isnull(c.BrandId,0)>0 Then '(' + c.ItemCode + ')' + '  ' + c.BrandName Else '(' + a.Code + ')' + '  ' + a.ResourceName End As value,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitName else d.UnitName End As UnitName,
                                Case when isnull(c.BrandId,0)>0 Then e.UnitId else d.UnitId End As UnitId,
                                Case when isnull(c.BrandId,0)>0 Then c.Rate else a.Rate End As Rate,'Library' As RFrom  ")))
                        ->join(array("b" => "Proj_ResourceGroup"),"a.ResourceGroupId=b.ResourceGroupId",array(),$selRa::JOIN_LEFT )
                        ->join(array("c" => "MMS_Brand"),"a.ResourceId=c.ResourceId",array(),$selRa::JOIN_LEFT)
                        ->join(array("d" => "Proj_Uom"),"a.UnitId=d.UnitId",array(),$selRa::JOIN_LEFT)
                        ->join(array("e" => "Proj_Uom"),"c.UnitId=e.UnitId",array(),$selRa::JOIN_LEFT)
                        ->where("a.TypeId IN (2,3) and a.ResourceId NOT IN (Select ResourceId From
                                Proj_ProjectResource A Inner Join WF_OperationalCostCentre B On A.ProjectId=B.ProjectId Where B.CostCentreId=". $CostCentreId .") and
                                (a.ResourceId NOT IN (Select ResourceId From MMS_POTrans
                         Where PORegisterId=".$poRegId.") Or isnull(c.BrandId,0) NOT IN (Select ItemId From MMS_POTrans Where PORegisterId=".$poRegId."))  ");
                    $select -> combine($selRa,"Union All");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_resources = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    //Get EstimateQty,EstimateRate,AvailableQty

                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectResource"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'), 'EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("a.Rate"), 'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => 'WF_OperationalCostCentre'),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentreId .' And
                                     a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .' )      ');


                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POTrans" ))
                        ->columns(array('ResourceId' => new Expression("a.ResourceId"), 'EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(ISNULL(SUM(B.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"), 'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POTransId=b.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c'=>"MMS_PORegister"),'a.PORegisterId=c.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('b.LivePO=1 And c.LivePO=1 And a.LivePO=1 And
                                a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                                  And b.CostCentreId='.$CostCentreId.' And c.General=0 Group By a.ResourceId ');
                    $sel1->combine($sel,'Union ALL');



                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "MMS_DCRegister"),'a.DCRegisterId=b.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('A.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                                     And B.CostCentreId='.$CostCentreId .' And B.General=0 Group By a.ResourceId ');
                    $sel2->combine($sel1,"Union ALL");

                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('a.ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate'=> new Expression("CAST(0 As Decimal(18,3))"), 'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),
                            'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=>"MMS_PVRegister"),'a.PVRegisterId=b.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('b.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                                     and b.CostCentreId='.$CostCentreId.' and b.General=0 Group By a.ResourceId ');
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=>"MMS_PRRegister"),'a.PRRegisterId=b.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                                     And b.CostCentreId='.$CostCentreId.' Group By a.ResourceId');
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId'=>new Expression("a.ResourceId"),'TotTranQty' => new Expression("ISNULL(SUM(A.RecdQty),0)")))
                        ->join(array('b'=>"MMS_TransferRegister"),'a.TransferRegisterId=b.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                                     and b.ToCostCentreId='.$CostCentreId.' Group By a.ResourceId ');

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferTrans"))
                        -> columns(array('ResourceId'=>new Expression("a.ResourceId"),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>'MMS_TransferRegister'),'a.TransferRegisterId=b.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                                     and b.FromCostCentreId='.$CostCentreId.' Group By a.ResourceId ');
                    $sel6->combine($sel5,"Union ALL");

                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('ResourceId'=>new Expression("ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                    $sel7->group(new Expression("A.ResourceId"));
                    $sel7 -> combine($sel4,"Union ALL");

                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.Quantity-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'VM_RequestRegister'),'a.RequestId=b.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                                     and b.CostCentreId='.$CostCentreId.' Group By a.ResourceId ');
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POTransId=b.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_PORegister'),'a.PORegisterId=c.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and c.LivePO=1 and c.General=0 and b.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') Group By a.ResourceId ');
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_DCRegister'),'a.DCRegisterId=b.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('b.General=0 and b.CostCentreId='.$CostCentreId.'
                                and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') Group By a.ResourceId ');
                    $sel10->combine($sel9,"Union ALL");

                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_PVRegister'),'a.PVRegisterId=b.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('b.General=0 and b.ThruPO='."'Y'".' and b.CostCentreId='.$CostCentreId.'
                                     and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') Group By a.ResourceId ');
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array('ResourceId'=>new Expression("G.ResourceId"),
                            'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,2))"),
                            'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,2))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,2)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,2)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,2))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,2))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,2))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,2))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,2))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,2))") ));
                    $sel12->group(new Expression("G.ResourceId"));

                    $statement = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_estimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    //

                    //Get WBS Estimate
                    $sel = $sql->select();
                    $sel->from(array("a" => "Proj_ProjectWBSResource"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty' => new Expression('a.Qty'),'EstimateRate' => new Expression("a.Rate"),
                            'BalPOQty' => new Expression("CAST(0 As decimal(18,3))"), 'TotDCQty' => new Expression("Cast(0 As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' => new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "WF_OperationalCostCentre"),'a.ProjectId=b.ProjectId',array(),$sel::JOIN_INNER)
                        ->Where ('b.CostCentreId=' . $CostCentreId .' and  a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                 and a.WBSId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');

                    $sel1 = $sql->select();
                    $sel1->from(array("a"=> "MMS_POAnalTrans" ))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                            'EstimateQty' => new Expression("CAST(0 As decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty' => new Expression("CAST(ISNULL(SUM(a.BalQty),0) As Decimal(18,3))"),
                            'TotDCQty' => new Expression("CAST(0 As decimal(18,3))"),'TotBillQty' => new Expression("CAST(0 As decimal(18,3))"),
                            'TotRetQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),'ReqQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty' =>  new Expression("CAST(0 As Decimal(18,3))"),'BillQty' => new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=> "MMS_POProjTrans"),'a.POProjTransId=b.POProjTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('c' => "MMS_POTrans"),'b.POTransId=c.POTransId',array(),$sel1::JOIN_INNER)
                        ->join(array('d'=>"MMS_PORegister"),'c.PORegisterId=d.PORegisterId',array(),$sel1::JOIN_INNER)
                        ->Where ('a.LivePO=1 and b.LivePO=1 And c.LivePO=1 And d.LivePO=1 and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') And b.CostCentreId='.$CostCentreId.' And d.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel1->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel1->combine($sel,'Union ALL');


                    $sel2 = $sql -> select();
                    $sel2->from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                            'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty' => new Expression("CAST(ISNULL(SUM(A.AcceptQty),0) As Decimal(18,3))"),
                            'TotBillQty' => new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty' =>  new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "MMS_DCTrans"),'a.DCTransId=b.DCTransId',array(),$sel2::JOIN_INNER)
                        ->join(array('c' => "MMS_DCRegister"),'b.DCRegisterId=c.DCRegisterId',array(),$sel2::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') And c.CostCentreId='.$CostCentreId .' And c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel2->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel2->combine($sel1,"Union ALL");


                    $sel3 = $sql -> select();
                    $sel3 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                            'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=> new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(ISNULL(SUM(A.BillQty),0) As Decimal(18,3))"),'TotRetQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=> new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=> new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=> new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=> new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=> new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => "MMS_PVTrans"),'a.PVTransId=b.PVTransId',array(),$sel3::JOIN_INNER)
                        ->join(array('c'=>"MMS_PVRegister"),'b.PVRegisterId=c.PVRegisterId',array(),$sel3::JOIN_INNER)
                        ->where('c.ThruPO='."'Y'".' And a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') and c.CostCentreId='.$CostCentreId.' and c.General=0
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel3->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel3->combine($sel2,"Union ALL");

                    $sel4 = $sql -> select();
                    $sel4 -> from(array("a" => "MMS_PRAnalTrans"))
                        ->columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                            'EstimateQty' => new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate' => new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,2))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(ISNULL(SUM(A.ReturnQty),0) As Decimal(18,3))"),'TotTranQty' => new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b'=>"MMS_PRTrans"),'a.PRTransId=b.PRTransId',array(),$sel4::JOIN_INNER)
                        ->join(array('c'=>"MMS_PRRegister"),'b.PRRegisterId=c.PRRegisterId',array(),$sel4::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') And c.CostCentreId='.$CostCentreId.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel4->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel4->combine($sel3,"Union ALL");

                    $sel5 = $sql -> select();
                    $sel5 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId' => new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                            'TotTranQty' => new Expression("ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>"MMS_TransferRegister"),'b.TransferRegisterId=c.TVRegisterId',array(),$sel5::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') and c.ToCostCentreId='.$CostCentreId.'
                                  And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel5->group(new Expression("a.ResourceId,A.AnalysisId"));

                    $sel6 = $sql -> select();
                    $sel6 -> from(array("a" => "MMS_TransferAnalTrans"))
                        -> columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'TotTranQty' => new Expression("-1 * ISNULL(SUM(A.TransferQty),0)")))
                        ->join(array('b'=>"MMS_TransferTrans"),'a.TransferTransId=b.TransferTransId',array(),$sel5::JOIN_INNER)
                        ->join(array('c'=>'MMS_TransferRegister'),'b.TransferRegisterId=c.TVRegisterId',array(),$sel6::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')and c.FromCostCentreId='.$CostCentreId.'
                                 And a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel6->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel6->combine($sel5,"Union ALL");


                    $sel7 = $sql -> select();
                    $sel7 -> from(array("A"=>$sel6))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.WBSId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(SUM(TotTranQty) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ));
                    $sel7->group(new Expression("a.ResourceId,a.WBSId"));
                    $sel7 -> combine($sel4,"Union ALL");


                    $sel8 = $sql -> select();
                    $sel8 -> from(array("a" => "VM_RequestAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'ReqQty'=>new Expression("ISNULL(SUM(A.ReqQty-A.CancelQty),0)"),'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'VM_RequestTrans'),'a.ReqTransId=b.RequestTransId',array(),$sel8::JOIN_INNER)
                        ->join(array('c' => 'VM_RequestRegister'),'b.RequestId=c.RequestId',array(),$sel8::JOIN_INNER)
                        ->where('a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') and c.CostCentreId='.$CostCentreId.' and
                                 a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel8->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel8->combine($sel7,"Union ALL");

                    $sel9 = $sql -> select();
                    $sel9 -> from(array("a" => "MMS_POAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression('a.ResourceId'),'WBSId'=>new Expression('a.AnalysisId'),
                            'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(ISNULL(Sum(ISNULL(A.POQty,0)),0)-ISNULL(SUM(ISNULL(A.CancelQty,0)),0) As Decimal(18,3))"),
                            'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),'BillQty'=>new Expression("CAST(0 As Decimal(18,3))") ))
                        ->join(array('b' => 'MMS_POProjTrans'),'a.POProjTransId=b.POProjTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('c' => 'MMS_POTrans'),'b.POTransId=c.POTransId',array(),$sel9::JOIN_INNER)
                        ->join(array('d' => 'MMS_PORegister'),'c.PORegisterId=d.PORegisterId',array(),$sel9::JOIN_INNER)
                        ->where('a.LivePO=1 and b.LivePO=1 and c.LivePO=1 and d.LivePO=1 and d.General=0 and b.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                  and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel9->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel9->combine($sel8,"Union ALL");

                    $sel10 = $sql -> select();
                    $sel10 -> from(array("a" => "MMS_DCAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                            'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.AcceptQty,0)),0) As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(0 As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_DCTrans'),'a.DCTransId=b.DCTransId',array(),$sel10::JOIN_INNER)
                        ->join(array('c' => 'MMS_DCRegister'),'b.DCRegisterId=c.DCRegisterId',array(),$sel10::JOIN_INNER)
                        ->where ('c.General=0 and c.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel10->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel10->combine($sel9,"Union ALL");


                    $sel11 = $sql -> select();
                    $sel11 -> from(array("a" => "MMS_PVAnalTrans"))
                        ->columns(array('ResourceId'=>new Expression("a.ResourceId"),'WBSId'=>new Expression("a.AnalysisId"),
                            'EstimateQty'=>new Expression("CAST(0 As Decimal(18,3))"),'EstimateRate'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BalPOQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotDCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotBillQty'=>new Expression("CAST(0 As Decimal(18,3))"),'TotRetQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'TotTranQty'=>new Expression("CAST(0 As Decimal(18,3))"),'ReqQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'POQty'=>new Expression("CAST(0 As Decimal(18,3))"),'DCQty'=>new Expression("CAST(0 As Decimal(18,3))"),
                            'BillQty'=>new Expression("CAST(ISNULL(SUM(ISNULL(A.BillQty,0)),0) As Decimal(18,3))")))
                        ->join(array('b' => 'MMS_PVTrans'),'a.PVTransId=b.PVTransId',array(),$sel11::JOIN_INNER)
                        ->join(array('c' => 'MMS_PVRegister'),'b.PVRegisterId=c.PVRegisterId',array(),$sel11::JOIN_INNER)
                        ->where('c.General=0 and c.ThruPO='."'Y'".' and c.CostCentreId='.$CostCentreId.' and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .')
                                and a.AnalysisId IN (
                                 Select WBSId From Proj_ProjectDetails A
                                 Inner Join Proj_ProjectIOW B On A.ProjectIOWId=B.ProjectIOWId
                                 Inner Join Proj_WBSTrans C On b.ProjectIOWId=c.ProjectIOWId And A.ProjectId=C.ProjectId
                                 Inner Join WF_OperationalCostCentre D On A.ProjectId=D.ProjectId
                                 Where a.IncludeFlag=1 and D.CostCentreId='. $CostCentreId .'
                                 and a.ResourceId IN (Select ResourceId From MMS_POTrans Where PORegisterId='. $poRegId .') )');
                    $sel11->group(new Expression("a.ResourceId,a.AnalysisId"));
                    $sel11->combine($sel10,"Union ALL");

                    $sel12 = $sql -> select();
                    $sel12 -> from(array("G"=>$sel11))
                        ->columns(array('ResourceId'=>new Expression("G.ResourceId"),'WBSId'=>new Expression("G.WBSId"),
                            'EstimateQty'=>new Expression("CAST(ISNULL(SUM(G.EstimateQty),0) As Decimal(18,3))"),
                            'EstimateRate'=>new Expression("CAST(ISNULL(SUM(G.EstimateRate),0) As Decimal(18,3))"),
                            'AvailableQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) > 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'ExcessQty'=>new Expression("Case When (ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) < 0 Then CAST((ISNULL(SUM(G.EstimateQty),0)-(ISNULL(SUM(G.BalPOQty),0)+ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0))) As Decimal(18,3)) Else 0 End"),
                            'BalPOQty'=>new Expression("CAST(ISNULL(SUM(G.BalPOQty),0) As Decimal(18,3))"),
                            'TotPurchase'=>new Expression("CAST(ISNULL(SUM(G.TotDCQty),0)+ISNULL(SUM(G.TotBillQty),0)-ISNULL(SUM(G.TotRetQty),0)+ISNULL(SUM(G.TotTranQty),0) As Decimal(18,3))"),
                            'ReqQty'=>new Expression("CAST(ISNULL(SUM(G.ReqQty),0) As Decimal(18,3))"),'POQty'=>new Expression("CAST(ISNULL(SUM(G.POQty),0) As Decimal(18,3))"),
                            'MinQty'=>new Expression("CAST(ISNULL(SUM(G.DCQty),0) As Decimal(18,3))"),'BillQty'=>new Expression("CAST(ISNULL(SUM(G.BillQty),0) As Decimal(18,3))") ));
                    $sel12->group(new Expression("G.ResourceId,G.WBSId"));

                    $statement = $sql->getSqlStringForSqlObject($sel12);
                    $this->_view->arr_wbsestimate = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                }
            }

            $aVNo = CommonHelper::getVoucherNo(301, date('Y/m/d'), 0, 0, $dbAdapter, "");
            $this->_view->genType = $aVNo["genType"];
            if (!$aVNo["genType"])
                $this->_view->woNo = "";
            else
                $this->_view->woNo = $aVNo["voucherNo"];

            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            return $this->_view;
        }
    }
    public function ordersaveAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Purchase Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $vNo = CommonHelper::getVoucherNo(301,date('Y/m/d') ,0,0, $dbAdapter,"");
        $this->_view->vNo = $vNo;
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();

//                echo"<pre>";
//               print_r($postParams);
//                echo"</pre>";die;

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
                $flag=$postParams['flag'];
                $PORegisterId=$postParams['PORegId'];
                $CostCenterId= $this->bsf->isNullCheck($postParams['CostCenterId'],'number');
                $OrderType= $this->bsf->isNullCheck($postParams['OrderType'],'string');
                //$VendorId=$this->bsf->isNullCheck($postParams['VendorId'],'number');
                $VendorId=$this->bsf->isNullCheck($postParams['VendorId'],'number');
                $PODate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['PODate'], 'string')));
                $ReqDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['RefDate'], 'string')));
                $PONo=$this->bsf->isNullCheck($postParams['PONo'],'string');
                $voucherNo=$PONo;
                $CCPONo=$this->bsf->isNullCheck($postParams['CCPONo'],'string');
                $CPONo=$this->bsf->isNullCheck($postParams['CPONo'],'string');
                $RefNo=$this->bsf->isNullCheck($postParams['RefNo'],'string');
                $CurrId=$this->bsf->isNullCheck($postParams['currency'],'number');
                $dist=$postParams['distributor'];

                $PurTypeId=$this->bsf->isNullCheck($postParams['purchase_type'],'number');
                $AccountId=$this->bsf->isNullCheck($postParams['account_type'],'number');
                $gridtype =  $this->bsf->isNullCheck($postParams['gridtype'],'number');
                $amount = $this->bsf->isNullCheck($postParams['basetotal'], 'number');
                $netamt = $this->bsf->isNullCheck($postParams['nettotal'], 'number');

                //Get CompanyId
                $getCompany = $sql -> select();
                $getCompany->from("WF_OperationalCostCentre")
                    ->columns(array("CompanyId"));
                $getCompany->where(array('CostCentreId'=>$CostCenterId));
                $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');


                //CompanyId
                $CPo = CommonHelper::getVoucherNo(305, date('Y/m/d'), $CompanyId, 0, $dbAdapter, "");
                $this->_view->CPo = $CPo;
                //CostCenterId
                $CCPo = CommonHelper::getVoucherNo(305, date('Y/m/d'), 0, $CostCenterId, $dbAdapter, "");
                $this->_view->CCPo = $CCPo;


                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try
                {
                    if ($this->bsf->isNullCheck($PORegisterId, 'number') > 0 && $flag == 0) {
                        $Approve="E";
                        $Role="PO-Modify";
                    }else{
                        $Approve="N";
                        $Role="PO-Create";
                    }
                    //Get CompanyId
                    $getCompany = $sql -> select();
                    $getCompany->from("WF_OperationalCostCentre")
                        ->columns(array("CompanyId"));
                    $getCompany->where(array('CostCentreId'=>$CostCenterId));
                    $compStatement = $sql->getSqlStringForSqlObject($getCompany);
                    $comName = $dbAdapter->query($compStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $CompanyId=$this->bsf->isNullCheck($comName['CompanyId'],'number');


                    if($this->bsf->isNullCheck($PORegisterId,'number') > 0){

                        if($flag == 0) {

                            //Purchase Order Edit

                            //Reverse DecAnalTrans & RequestAnalTrans
                            $PORegisterId = $this->bsf->isNullCheck($PORegisterId, 'number');
                            $selPrevAnal = $sql->select();
                            $selPrevAnal->from(array("a" => "MMS_IPDAnalTrans"))
                                ->columns(array(new Expression("a.DecTransId,a.DecATransId,e.ReqTransId,e.ReqAHTransId,A.Qty As Qty")))
                                ->join(array("b" => "MMS_POAnalTrans"), "a.POAHTransId=b.POAnalTransId", array(), $selPrevAnal::JOIN_INNER)
                                ->join(array("c" => "MMS_POProjTrans"), "b.POProjTransId=c.POProjTransId", array(), $selPrevAnal::JOIN_INNER)
                                ->join(array("d" => "MMS_POTrans"), "c.POTransId=d.POTransId", array(), $selPrevAnal::JOIN_INNER)
                                ->join(array("e" => "VM_ReqDecQtyAnalTrans"), "a.DecTransId=e.TransId and a.DecATransId=e.RCATransId", array(), $selPrevAnal::JOIN_INNER)
                                ->where(array("d.PORegisterId" => $PORegisterId));
                            $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                            $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach ($prevanal as $arrprevanal) {
                                $updDecAnal = $sql->update();
                                $updDecAnal->table('VM_ReqDecQtyAnalTrans');
                                $updDecAnal->set(array(
                                    'IndAdjQty' => new Expression('IndAdjQty-' . $arrprevanal['Qty'] . '')
                                ));
                                $updDecAnal->where(array('RCATransId' => $arrprevanal['DecATransId'], 'TransId' => $arrprevanal['DecTransId']));
                                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updReqAnal = $sql->update();
                                $updReqAnal->table('VM_RequestAnalTrans');
                                $updReqAnal->set(array(
                                    'IndentQty' => new Expression('IndentQty-' . $arrprevanal['Qty'] . ''),
                                    'BalQty' => new Expression('BalQty+' . $arrprevanal['Qty'] . '')
                                ));
                                $updReqAnal->where(array('RequestAHTransId' => $arrprevanal['ReqAHTransId'], 'ReqTransId' => $arrprevanal['ReqTransId']));
                                $updReqAnalStatement = $sql->getSqlStringForSqlObject($updReqAnal);
                                $dbAdapter->query($updReqAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                            //Reverse DecQtyTrans & RequestTrans
                            $selPrevTrans = $sql->select();
                            $selPrevTrans->from(array("a" => "MMS_IPDTrans"))
                                ->columns(array(new Expression("a.DecisionId,a.DecTransId,c.ReqTransId,c.DecisionId,a.Qty As Qty ")))
                                ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $selPrevTrans::JOIN_INNER)
                                ->join(array("c" => "VM_ReqDecQtyTrans"), "a.DecTransId=c.TransId and a.Decisionid=c.DecisionId", array(), $selPrevTrans::JOIN_INNER)
                                ->where(array("b.PORegisterId" => $PORegisterId));
                            $statementPrevTrans = $sql->getSqlStringForSqlObject($selPrevTrans);
                            $prevtrans = $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach ($prevtrans as $arrprevtrans) {
                                $updDecTran = $sql->update();
                                $updDecTran->table('VM_ReqDecQtyTrans');
                                $updDecTran->set(array(
                                    'IndAdjQty' => new Expression('IndAdjQty-' . $arrprevtrans['Qty'] . '')
                                ));
                                $updDecTran->where(array('TransId' => $arrprevtrans['DecTransId'], 'DecisionId' => $arrprevtrans['DecisionId']));
                                $statementPrevTrans = $sql->getSqlStringForSqlObject($updDecTran);
                                $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updReqTrans = $sql->update();
                                $updReqTrans->table('VM_RequestTrans');
                                $updReqTrans->set(array(
                                    'IndentQty' => new Expression('IndentQty-' . $arrprevtrans['Qty'] . ''),
                                    'BalQty' => new Expression('BalQty+' . $arrprevtrans['Qty'] . '')
                                ));
                                $updReqTrans->where(array('RequestTransId' => $arrprevtrans['ReqTransId']));
                                $statementPrevReqTrans = $sql->getSqlStringForSqlObject($updReqTrans);
                                $dbAdapter->query($statementPrevReqTrans, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                            //Delete POTrans & POProjTrans & POAnalTrans & IPDTrans & IPDProjTrans & IPDAnalTrans & PODistributorTrans
                            //PODistributorTrans
                            $delPODisTrans = $sql->delete();
                            $delPODisTrans->from('MMS_PODistributorTrans')
                                ->where(array("PORegisterId" => $PORegisterId));
                            $PODistStatement = $sql->getSqlStringForSqlObject($delPODisTrans);
                            $dbAdapter->query($PODistStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //POPaymentTerms
                            $delPOPayTrans = $sql->delete();
                            $delPOPayTrans->from('MMS_POPaymentTerms')
                                ->where(array("PORegisterId" => $PORegisterId));
                            $POPayStatement = $sql->getSqlStringForSqlObject($delPOPayTrans);
                            $dbAdapter->query($POPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //POQualTrans
                            $delPOQualTrans = $sql->delete();
                            $delPOQualTrans->from('MMS_POQualTrans')
                                ->where(array("PORegisterId" => $PORegisterId));
                            $POQualStatement = $sql->getSqlStringForSqlObject($delPOQualTrans);
                            $dbAdapter->query($POQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            //IPDAnalTrans
                            $delIPDAnalSQ3 = $sql->select();
                            $delIPDAnalSQ3->from("MMS_POTrans")
                                ->columns(array("POTransId"))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $delIPDAnalSQ2 = $sql->select();
                            $delIPDAnalSQ2->from("MMS_POProjTrans")
                                ->columns(array("POProjTransId"))
                                ->where->expression('POTransId IN ?', array($delIPDAnalSQ3));
                            $delIPDAnalSQ1 = $sql->select();
                            $delIPDAnalSQ1->from("MMS_POAnalTrans")
                                ->columns(array("POAnalTransId"))
                                ->where->expression('POProjTransId IN ?', array($delIPDAnalSQ2));
                            $delIPDAnal = $sql->delete();
                            $delIPDAnal->from('MMS_IPDAnalTrans')
                                ->where->expression('POAHTransId IN ?', array($delIPDAnalSQ1));
                            $IPDAnalStatement = $sql->getSqlStringForSqlObject($delIPDAnal);
                            $dbAdapter->query($IPDAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //IPDProjTrans
                            $delIPDProjSQ2 = $sql->select();
                            $delIPDProjSQ2->from("MMS_POTrans")
                                ->columns(array("POTransId"))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $delIPDProjSQ1 = $sql->select();
                            $delIPDProjSQ1->from("MMS_POProjTrans")
                                ->columns(array("POProjTransId"))
                                ->where->expression('POTransId IN ?', array($delIPDProjSQ2));
                            $delIPDProj = $sql->delete();
                            $delIPDProj->from('MMS_IPDProjTrans')
                                ->where->expression('POProjTransId IN ?', array($delIPDProjSQ1));
                            $IPDTransStatement = $sql->getSqlStringForSqlObject($delIPDProj);
                            $dbAdapter->query($IPDTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //IPDTrans
                            $delIPDTransSQ1 = $sql->select();
                            $delIPDTransSQ1->from("MMS_POTrans")
                                ->columns(array("POTransId"))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $delIPDTrans = $sql->delete();
                            $delIPDTrans->from('MMS_IPDTrans')
                                ->where->expression('POTransId IN ?', array($delIPDTransSQ1));
                            $delipdStatement = $sql->getSqlStringForSqlObject($delIPDTrans);
                            $dbAdapter->query($delipdStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //POAnalTrans
                            $delPOAnalSQ2 = $sql->select();
                            $delPOAnalSQ2->from("MMS_POTrans")
                                ->columns(array("POTransId"))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $delPOAnalSQ1 = $sql->select();
                            $delPOAnalSQ1->from("MMS_POProjTrans")
                                ->columns(array("POProjTransId"))
                                ->where->expression('POTransId IN ?', array($delPOAnalSQ2));
                            $delPOAnal = $sql->delete();
                            $delPOAnal->from('MMS_POAnalTrans')
                                ->where->expression('POProjTransId IN ?', array($delPOAnalSQ1));
                            $delpoanalStatement = $sql->getSqlStringForSqlObject($delPOAnal);
                            $dbAdapter->query($delpoanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //POProjTrans
                            $delPOProjSQ1 = $sql->select();
                            $delPOProjSQ1->from("MMS_POTrans")
                                ->columns(array("POTransId"))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $delPOProj = $sql->delete();
                            $delPOProj->from('MMS_POProjTrans');
                            $delPOProj->where->expression('POTransId IN ?', array($delPOProjSQ1));
                            $delpoprojStatement = $sql->getSqlStringForSqlObject($delPOProj);
                            $dbAdapter->query($delpoprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //POTrans
                            $delPOTrans = $sql->delete();
                            $delPOTrans->from('MMS_POTrans')
                                ->where(array("PORegisterId" => $PORegisterId));
                            $delpotransStatement = $sql->getSqlStringForSqlObject($delPOTrans);
                            $dbAdapter->query($delpotransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //
                            //UPDATE POREGISTER
                            $registerUpdate = $sql->update()
                                ->table("MMS_PORegister")
                                ->set(array("PODate" => $PODate, "ReqDate" => $ReqDate,
                                    "PONo" => $PONo, "CCPONo" => $CCPONo, "CPONo" => $CPONo,

                                    "PurchaseTypeId" => $PurTypeId,
                                    "PurchaseAccount" => $AccountId, "CurrencyId" => $CurrId,
                                    "Narration" => $this->bsf->isNullCheck($postParams['Narration'], 'string'),
                                    "BranchId" => $this->bsf->isNullCheck($postParams['branchname'], 'number'),
                                    "BranchTransId" => $this->bsf->isNullCheck($postParams['cperson'], 'number'),
                                    "ProjectAddress" => $this->bsf->isNullCheck($postParams['deladdress'], 'string'),
                                    "PoDelId" => $this->bsf->isNullCheck($postParams['warehouse'], 'number'),
                                    "PoDelAdd" => $this->bsf->isNullCheck($postParams['whaddress'], 'string'),
                                    "CompanyContactName" => $this->bsf->isNullCheck($postParams['ccontact'], 'string'),
                                    "CompanyContactNo" => $this->bsf->isNullCheck($postParams['cphone'], 'string'),
                                    "CompanyMobile" => $this->bsf->isNullCheck($postParams['cmobile'], 'string'),
                                    "CompanyEmail" => $this->bsf->isNullCheck($postParams['cemail'], 'string'),
                                    "SiteContactName" => $this->bsf->isNullCheck($postParams['cccontact'], 'string'),
                                    "SiteContactNo" => $this->bsf->isNullCheck($postParams['ccphone'], 'string'),
                                    "SiteMobile" => $this->bsf->isNullCheck($postParams['ccmobile'], 'string'),
                                    "SiteEmail" => $this->bsf->isNullCheck($postParams['ccemail'], 'string'),
                                    "Amount" => $amount,
                                    "GrossAmount" => $amount,
                                    "NetAmount" => $netamt,
                                    "ReqNo" => $this->bsf->isNullCheck($postParams['RefNo'], 'string'),
                                    "Narration" => $this->bsf->isNullCheck($postParams['Narration'], 'string')
                                ))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $delporegStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                            $dbAdapter->query($delporegStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $dCount = count($dist);
                            for ($d = 0; $d < $dCount; $d++) {
                                $disInsert = $sql->insert('MMS_PODistributorTrans');
                                $disInsert->values(array("PORegisterId" => $PORegisterId, "VendorId" => $dist[$d]));
                                $disStatement = $sql->getSqlStringForSqlObject($disInsert);
                                $dbAdapter->query($disStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $resTotal = $postParams['rowid'];
                            //POAnalTrans
                            $selectIPDAh = $sql->select();
                            $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                                ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER);

                            for ($i = 1; $i < $resTotal; $i++) {
                                if ($this->bsf->isNullCheck($postParams['qty_' . $i], 'number') > 0) {
                                    $treqDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['deldate_' . $i], 'string')));
                                    $potransInsert = $sql->insert('MMS_POTrans');
                                    $potransInsert->values(array("PORegisterId" => $PORegisterId, "UnitId" => $postParams['unitid_' . $i],
                                        "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "POQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                        "BalQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'), "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'), "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                        "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'), "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'), "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                        "GrossAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'), "Description" => $this->bsf->isNullCheck($postParams['resspec_' . $i], 'string'),
                                        "ReqDate" =>  $treqDate));
                                    $potransStatement = $sql->getSqlStringForSqlObject($potransInsert);
                                    $dbAdapter->query($potransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $POTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $poprojInsert = $sql->insert('MMS_POProjTrans');
                                    $poprojInsert->values(array("POTransId" => $POTransId, "CostCentreId" => $CostCenterId, "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i], "UnitId" => $postParams['unitid_' . $i], "POQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                        "BalQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number')));
                                    $poprojStatement = $sql->getSqlStringForSqlObject($poprojInsert);
                                    $dbAdapter->query($poprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $POProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $decTotal = $postParams['iow_' . $i . '_rowid'];
                                    if ($decTotal > 0) {
                                        for ($j = 1; $j <= $decTotal; $j++) {
                                            if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') > 0) {
                                                $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                                //IPDTrans
                                                $ipdtransInsert = $sql->insert('MMS_IPDTrans');
                                                $ipdtransInsert->values(array("POTransId" => $POTransId, "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j], "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                    "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "UnitId" => $postParams['unitid_' . $i], "Status" => 'P'));
                                                $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                                $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                //IPDProjTrans
                                                $ipdprojInsert = $sql->insert('MMS_IPDProjTrans');
                                                $ipdprojInsert->values(array("IPDTransId" => $IPDTransId, "CostCentreId" => $CostCenterId, "POProjTransId" => $POProjTransId, "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j], "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                    "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "UnitId" => $postParams['unitid_' . $i], "Status" => 'P'));
                                                $ipdprojStatement = $sql->getSqlStringForSqlObject($ipdprojInsert);
                                                $dbAdapter->query($ipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                //DecisionTrans And RequestTrans Update
                                                $dectransUpdate = $sql->update();
                                                $dectransUpdate->table('VM_ReqDecQtyTrans');
                                                $dectransUpdate->set(array('IndAdjQty' => new Expression('IndAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                                $dectransUpdate->where(array('TransId' => $postParams['iow_' . $i . '_dectransid_' . $j]));
                                                $dectransStatement = $sql->getSqlStringForSqlObject($dectransUpdate);
                                                $dbAdapter->query($dectransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $reqtransUpdate = $sql->update();
                                                $reqtransUpdate->table('VM_RequestTrans');
                                                $reqtransUpdate->set(array('IndentQty' => new Expression('IndentQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''),
                                                    'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                                $reqtransUpdate->where(array('RequestTransId' => $postParams['iow_' . $i . '_reqtransid_' . $j]));
                                                $reqtransStatement = $sql->getSqlStringForSqlObject($reqtransUpdate);
                                                $dbAdapter->query($reqtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                //

                                                for ($k = 1; $k <= $wbsTotal; $k++) {
                                                    if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') > 0) {
                                                        //IPDAnalTrans
                                                        $ipdanalInsert = $sql->insert('MMS_IPDAnalTrans');
                                                        $ipdanalInsert->values(array("IPDProjTransId" => $IPDProjTransId, "AnalysisId" => $postParams['iow_' . $i . '_request_' . $j . '_wbsid_' . $k . ''],
                                                            "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "UnitId" => $postParams['unitid_' . $i],
                                                            "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number'), "DecATransId" => $postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . ''],
                                                            "DecTransId" => $postParams['iow_' . $i . '_request_' . $j . '_dectransid_' . $k . ''], "Status" => 'P'));
                                                        $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                                        $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $IPDAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        //DecisionAnalTrans And RequestAnalTrans Update
                                                        $decAnalUpdate = $sql->update();
                                                        $decAnalUpdate->table('VM_ReqDecQtyAnalTrans');
                                                        $decAnalUpdate->set(array('IndAdjQty' => new Expression('IndAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . '')));
                                                        $decAnalUpdate->where(array('RCATransId' => $postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . '']));
                                                        $decAnalStatement = $sql->getSqlStringForSqlObject($decAnalUpdate);
                                                        $dbAdapter->query($decAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        $reqahUpdate = $sql->update();
                                                        $reqahUpdate->table('VM_RequestAnalTrans');
                                                        $reqahUpdate->set(array('IndentQty' => new Expression('IndentQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . ''), 'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . '')));
                                                        $reqahUpdate->where(array('RequestAHTransId' => $postParams['iow_' . $i . '_request_' . $j . '_reqahtransid_' . $k . '']));
                                                        $reqahStatement = $sql->getSqlStringForSqlObject($reqahUpdate);
                                                        $dbAdapter->query($reqahStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }


//                               if (dt.Rows.Count > 0) { dRoundDecimal = Convert.ToDecimal(dt.Rows[0]["RoundDecimal"]); }
//                               dt.Dispose();
//
//                               if (dRoundDecimal != 0)
//                               {
//                                   dRoundValue = Math.Round(dValue / dRoundDecimal, 0, MidpointRounding.AwayFromZero) * dRoundDecimal;
//                               }
//                               else
//                               {
//                                   dRoundValue = argValue;
//                               }
//
//                               return dRoundValue;


                                                //POAnalTrans
                                                $selectIPDAh = $sql->select();
                                                $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                                                    ->columns(array(new Expression("a.AnalysisId,a.ResourceId,a.ItemId,a.UnitId,SUM(a.Qty) As Qty")))
                                                    ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER)
                                                    ->group(array("a.AnalysisId", "a.ResourceId", "a.ItemId", "a.UnitId"))
                                                    ->where('b.IPDProjTransId=' . $IPDProjTransId . '');
                                                $statement = $sql->getSqlStringForSqlObject($selectIPDAh);
                                                $arr_ipdah = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                foreach ($arr_ipdah as $ipdah) {
                                                    if ($ipdah['Qty'] > 0) {
                                                        $poanalInsert = $sql->insert('MMS_POAnalTrans');
                                                        $poanalInsert->values(array("POProjTransId" => $POProjTransId, "AnalysisId" => $ipdah['AnalysisId'],
                                                            "ResourceId" => $ipdah['ResourceId'], "ItemId" => $ipdah['ItemId'],
                                                            "UnitId" => $ipdah['UnitId'], "POQty" => $this->bsf->isNullCheck($ipdah['Qty'], 'number'),
                                                            "BalQty" => $this->bsf->isNullCheck($ipdah['Qty'], 'number')));
                                                        $poanalStatement = $sql->getSqlStringForSqlObject($poanalInsert);
                                                        $dbAdapter->query($poanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $POAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        $selIPDUp = $sql->select();
                                                        $selIPDUp->from(array('a' => 'MMS_IPDAnalTrans'))
                                                            ->columns(array('IPDAHTransId'))
                                                            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selIPDUp::JOIN_INNER)
                                                            ->join(array('c' => 'MMS_POProjTrans'), 'b.POProjTransId=c.POProjTransId', array(), $selIPDUp::JOIN_INNER)
                                                            ->where('c.POProjTransId=' . $POProjTransId . ' and a.AnalysisId=' . $ipdah['AnalysisId'] . ' and a.ResourceId=' . $ipdah['ResourceId'] . ' and a.ItemId=' . $ipdah['ItemId'] . ' ');
                                                        $selIPDUpStmt = $sql->getSqlStringForSqlObject($selIPDUp);
                                                        $arr_ipdahup = $dbAdapter->query($selIPDUpStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                        foreach ($arr_ipdahup as $ipdahup) {
                                                            $ipdanalupdate = $sql->update();
                                                            $ipdanalupdate->table('MMS_IPDAnalTrans')
                                                                ->set(array('POAHTransId' => $POAHTransId))
                                                                ->where('IPDAHTransId=' . $ipdahup['IPDAHTransId'] . '');
                                                            $ipdanalupStatement = $sql->getSqlStringForSqlObject($ipdanalupdate);
                                                            $dbAdapter->query($ipdanalupStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    //Without Request Decision
                                    $wbsTotal = $postParams['wbs_' . $i . '_rowid'];

                                    if ($wbsTotal > 0) {
                                        for ($j = 1; $j <= $wbsTotal; $j++) {

                                            if ($this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $j], 'number') > 0) {
                                                $poanalInsert = $sql->insert('MMS_POAnalTrans');
                                                $poanalInsert->values(array("POProjTransId" => $POProjTransId, "AnalysisId" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_wbsid_' . $j], 'number'),
                                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'), "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                                    "UnitId" => $this->bsf->isNullCheck($postParams['unitid' . $i], 'number'), "POQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $j], 'number')));
                                                $poanalStatement = $sql->getSqlStringForSqlObject($poanalInsert);
                                                $dbAdapter->query($poanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $POAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                            }
                                        }
                                    }

                                    //Qualifier Insert
                                    $qual = $postParams['QualRowId_' . $i];

                                    for ($q = 1; $q <= $qual; $q++) {
                                        if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                            $qInsert = $sql->insert('MMS_POQualTrans');
                                            $qInsert->values(array("PORegisterId" => $PORegisterId, "POTransId" => $POTransId, "QualifierId" => $postParams['Qual_' . $i . '_Id_' . $q], "YesNo" => "1", "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                                "Sign" => $postParams['Qual_' . $i . '_Sign_' . $q], "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number'), "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number'),
                                                "Expression" => $postParams['Qual_' . $i . '_Exp_' . $q], "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number'),));
                                            $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                            $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    //
                                }
                            }
                            //Terms
                            $termsTotal = $postParams['trowid'];
                            $valueFrom = 0;
                            if ($postParams['valuefrom'] == 'BaseAmount') {
                                $valueFrom = 0;
                            } else if ($postParams['valuefrom'] == 'NetAmount') {
                                $valueFrom = 1;
                            } else if ($postParams['valuefrom'] == 'GrossAmount') {
                                $valueFrom = 2;
                            }

                            for ($t = 1; $t < $termsTotal; $t++) {
                                $datest = date('Y-m-d');
                                $dt = strtotime(str_replace('/', '-', $postParams['date_' . $t]));
                                if ($dt != false) {
                                    $datest = date('Y-m-d', strtotime(str_replace('/', '-', $postParams['date_' . $t])));
                                }
                                if ($this->bsf->isNullCheck($postParams['termsid_' . $t], 'number') > 0) {
                                    $TDate = 'NULL';
                                    if ($postParams['date_' . $t] == '' || $postParams['date_' . $t] == null) {
                                        $TDate = null;
                                    } else {
                                        $TDate = $datest;
                                    }
                                    $termsInsert = $sql->insert('MMS_POPaymentTerms');
                                    $termsInsert->values(array("PORegisterId" => $PORegisterId, "TermsId" => $this->bsf->isNullCheck($postParams['termsid_' . $t], 'number'),
                                        "Per" => $this->bsf->isNullCheck($postParams['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postParams['value_' . $t], 'number'), "Period" => $postParams['period_' . $t],
                                        "TDate" => $TDate, "TString" => $postParams['string_' . $t], "ValueFromNet" => $valueFrom));
                                    $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                    $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            //
                            //Gross Rate Update
                            $selGross = $sql->select();
                            $selGross->from(array("a" => "MMS_POTrans"))
                                ->columns(array(new Expression("a.POTransId,Case When (ROW_NUMBER() OVER(PARTITION by A.POTransId Order by A.POTransId asc))=1 Then A.QAmount Else 0 End QAmt,
                                    Case When C.QualifierTypeId=3 Then ISNULL(B.NetAmt,0) Else 0 End VatAmt,
                                    Case When (ROW_NUMBER() OVER(PARTITION by A.POTransId Order by A.POTransId asc))=1 Then ISNULL(A.POQty,0) Else 0 End As POQty")))
                                ->join(array('b' => 'MMS_POQualTrans'), 'a.POTransId=b.POTransId', array(), $selGross::JOIN_LEFT)
                                ->join(array('c' => 'Proj_QualifierMaster'), 'b.QualifierId=c.QualifierId', array(), $selGross::JOIN_LEFT)
                                ->where("a.PORegisterId=$PORegisterId");

                            $selGross1 = $sql->select();
                            $selGross1->from(array("g" => $selGross))
                                ->columns(array(new Expression("g.POTransId,(SUM(G.QAmt)-SUM(G.VatAmt))/SUM(G.POQty) As GrossRate")));
                            $selGross1->group(new Expression("g.POTransId"));
                            $statement = $sql->getSqlStringForSqlObject($selGross1);
                            $arr_gross = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach ($arr_gross as $gross) {
                                $grossUpdate = $sql->update();
                                $grossUpdate->table('MMS_POTrans');
                                $grossUpdate->set(array(
                                        "GrossRate" => new Expression($this->bsf->isNullCheck($gross["GrossRate"], 'number')),
                                        "GrossAmount" => new Expression('CAST(POQty*' . $this->bsf->isNullCheck($gross["GrossRate"], 'number') . ' As Decimal(18,3)) ')
                                    )
                                );
                                $grossUpdate->where(array("POTransId" => $gross['POTransId']));
                                $grossUpdateStatement = $sql->getSqlStringForSqlObject($grossUpdate);
                                $dbAdapter->query($grossUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                            //Gross Amount Calculation
                            $selGTotal = $sql->select();
                            $selGTotal->from(array("a" => "MMS_POTrans"))
                                ->columns(array(new Expression("SUM(GrossAmount) As GrossAmount")))
                                ->where("PORegisterId=$PORegisterId");
                            $statement = $sql->getSqlStringForSqlObject($selGTotal);
                            $arr_gtotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($arr_gtotal) > 0) {
                                $gtotalUpdate = $sql->update();
                                $gtotalUpdate->table('MMS_PORegister');
                                $gtotalUpdate->set(array(
                                        "GrossAmount" => new Expression($this->bsf->isNullCheck($arr_gtotal["GrossAmount"], 'number')))
                                );
                                $gtotalUpdate->where(array("PORegisterId" => $PORegisterId));
                                $gtotalStatement = $sql->getSqlStringForSqlObject($gtotalUpdate);
                                $dbAdapter->query($gtotalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                            // End Purchase Order Edit
                        }
                        else {

                            // Purchase Order Amendment Start

                            //Reverse DecAnalTrans & RequestAnalTrans
                            $PORegisterId = $this->bsf->isNullCheck($PORegisterId, 'number');
                            $selPrevAnal = $sql->select();
                            $selPrevAnal->from(array("a" => "MMS_IPDAnalTrans"))
                                ->columns(array(new Expression("a.DecTransId,a.DecATransId,e.ReqTransId,e.ReqAHTransId,A.Qty As Qty")))
                                ->join(array("b" => "MMS_POAnalTrans"), "a.POAHTransId=b.POAnalTransId", array(), $selPrevAnal::JOIN_INNER)
                                ->join(array("c" => "MMS_POProjTrans"), "b.POProjTransId=c.POProjTransId", array(), $selPrevAnal::JOIN_INNER)
                                ->join(array("d" => "MMS_POTrans"), "c.POTransId=d.POTransId", array(), $selPrevAnal::JOIN_INNER)
                                ->join(array("e" => "VM_ReqDecQtyAnalTrans"), "a.DecTransId=e.TransId and a.DecATransId=e.RCATransId", array(), $selPrevAnal::JOIN_INNER)
                                ->where(array("d.PORegisterId" => $PORegisterId));
                             $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                             $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach ($prevanal as $arrprevanal) {
                                $updDecAnal = $sql->update();
                                $updDecAnal->table('VM_ReqDecQtyAnalTrans');
                                $updDecAnal->set(array(
                                    'IndAdjQty' => new Expression('IndAdjQty-' . $arrprevanal['Qty'] . '')
                                ));
                                $updDecAnal->where(array('RCATransId' => $arrprevanal['DecATransId'], 'TransId' => $arrprevanal['DecTransId']));
                                $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                                $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updReqAnal = $sql->update();
                                $updReqAnal->table('VM_RequestAnalTrans');
                                $updReqAnal->set(array(
                                    'IndentQty' => new Expression('IndentQty-' . $arrprevanal['Qty'] . ''),
                                    'BalQty' => new Expression('BalQty+' . $arrprevanal['Qty'] . '')
                                ));
                                $updReqAnal->where(array('RequestAHTransId' => $arrprevanal['ReqAHTransId'], 'ReqTransId' => $arrprevanal['ReqTransId']));
                                $updReqAnalStatement = $sql->getSqlStringForSqlObject($updReqAnal);
                                $dbAdapter->query($updReqAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                            //Reverse DecQtyTrans & RequestTrans
                            $selPrevTrans = $sql->select();
                            $selPrevTrans->from(array("a" => "MMS_IPDTrans"))
                                ->columns(array(new Expression("a.DecisionId,a.DecTransId,c.ReqTransId,c.DecisionId,a.Qty As Qty ")))
                                ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $selPrevTrans::JOIN_INNER)
                                ->join(array("c" => "VM_ReqDecQtyTrans"), "a.DecTransId=c.TransId and a.Decisionid=c.DecisionId", array(), $selPrevTrans::JOIN_INNER)
                                ->where(array("b.PORegisterId" => $PORegisterId));
                            $statementPrevTrans = $sql->getSqlStringForSqlObject($selPrevTrans);
                            $prevtrans = $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                            foreach ($prevtrans as $arrprevtrans) {
                                $updDecTran = $sql->update();
                                $updDecTran->table('VM_ReqDecQtyTrans');
                                $updDecTran->set(array(
                                    'IndAdjQty' => new Expression('IndAdjQty-' . $arrprevtrans['Qty'] . '')
                                ));
                                $updDecTran->where(array('TransId' => $arrprevtrans['DecTransId'], 'DecisionId' => $arrprevtrans['DecisionId']));
                                $statementPrevTrans = $sql->getSqlStringForSqlObject($updDecTran);
                                $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updReqTrans = $sql->update();
                                $updReqTrans->table('VM_RequestTrans');
                                $updReqTrans->set(array(
                                    'IndentQty' => new Expression('IndentQty-' . $arrprevtrans['Qty'] . ''),
                                    'BalQty' => new Expression('BalQty+' . $arrprevtrans['Qty'] . '')
                                ));
                                $updReqTrans->where(array('RequestTransId' => $arrprevtrans['ReqTransId']));
                                $statementPrevReqTrans = $sql->getSqlStringForSqlObject($updReqTrans);
                                $dbAdapter->query($statementPrevReqTrans, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //


                            //Update LivePO=0
                            $pregUpdate = $sql->update()
                                ->table("MMS_PORegister")
                                ->set(array('LivePO'=>0))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $pregUpdateStatement = $sql->getSqlStringForSqlObject($pregUpdate);
                            $dbAdapter->query($pregUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $ptransUpdate = $sql->update()
                                ->table("MMS_POTrans")
                                ->set(array('LivePO'=>0))
                                ->where(array("PORegisterId" => $PORegisterId));
                            $ptransUpdateStatement = $sql->getSqlStringForSqlObject($ptransUpdate);
                            $dbAdapter->query($ptransUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $pprojUpdate = $sql->update()
                                ->table("MMS_POProjTrans")
                                ->set(array('LivePO'=>0))
                                ->where("POTransId IN (Select POTransId From MMS_POTrans Where PORegisterId=$PORegisterId)");
                            $pprojUpdateStatement = $sql->getSqlStringForSqlObject($pprojUpdate);
                            $dbAdapter->query($pprojUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            $panalUpdate = $sql->update()
                                ->table("MMS_POAnalTrans")
                                ->set(array('LivePO'=>0))
                                ->where("POProjTransId IN (Select POProjTransId From MMS_POProjTrans
                                  Where POTransId IN (Select POTransId From MMS_POTrans Where PORegisterId=$PORegisterId)) ");
                            $panalUpdateStatement = $sql->getSqlStringForSqlObject($panalUpdate);
                            $dbAdapter->query($panalUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            //

                            //Get POAmend Count
                            $selAmendCount = $sql->select();
                            $selAmendCount->from(array("a" => "MMS_PORegister"))
                                ->columns(array(new Expression("(POAmend+1) As POAmend")))
                                ->where("PORegisterId=$PORegisterId");
                            $statementAmd = $sql->getSqlStringForSqlObject($selAmendCount);
                            $poAmd = $dbAdapter->query($statementAmd, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $poAmendVal = $poAmd['POAmend'];
                            //

                            //MMS_PORegister Insert
                            $registerInsert = $sql->insert('MMS_PORegister');
                            $registerInsert->values(array("PODate" => $PODate, "ReqDate" => $ReqDate,
                                "CostCentreId" => $CostCenterId,
                                "PONo" => $voucherNo, "VendorId" => $VendorId, "BranchId" => 0,
                                "PurchaseTypeId" => $PurTypeId, "PurchaseAccount" => $AccountId, "BranchTransId" => 0,
                                "Address1" => '', "Address2" => '', "Address3" => '',
                                "City" => '', "Pincode" => '', "Narration" => '', "ReqNo" => $RefNo, "CCPONo" => $CCPONo,
                                "CPONo" => $CPONo, "CurrencyId" => $CurrId, "Narration" => $this->bsf->isNullCheck($postParams['Narration'], 'string'),
                                "BranchId" => $this->bsf->isNullCheck($postParams['branchname'], 'number'),
                                "BranchTransId" => $this->bsf->isNullCheck($postParams['cperson'], 'number'),
                                "ProjectAddress" => $this->bsf->isNullCheck($postParams['deladdress'], 'string'),
                                "PoDelId" => $this->bsf->isNullCheck($postParams['warehouse'], 'number'),
                                "PoDelAdd" => $this->bsf->isNullCheck($postParams['whaddress'], 'string'),
                                "CompanyContactName" => $this->bsf->isNullCheck($postParams['ccontact'], 'string'),
                                "CompanyContactNo" => $this->bsf->isNullCheck($postParams['cphone'], 'string'),
                                "CompanyMobile" => $this->bsf->isNullCheck($postParams['cmobile'], 'string'),
                                "CompanyEmail" => $this->bsf->isNullCheck($postParams['cemail'], 'string'),
                                "SiteContactName" => $this->bsf->isNullCheck($postParams['cccontact'], 'string'),
                                "SiteContactNo" => $this->bsf->isNullCheck($postParams['ccphone'], 'string'),
                                "SiteMobile" => $this->bsf->isNullCheck($postParams['ccmobile'], 'string'),
                                "SiteEmail" => $this->bsf->isNullCheck($postParams['ccemail'], 'string'),
                                "Amount" => $amount,
                                "GrossAmount" => $amount,
                                "NetAmount" => $netamt,
                                "GridType" => $this->bsf->isNullCheck($postParams['gridtype'], 'number'),
                                "BaseCurrencyId" => $this->bsf->isNullCheck($postParams['dCurrencyId'], 'number'),
                                "ExchangeRate" => $this->bsf->isNullCheck($postParams['exchangeRate'], 'number'),
                                "POAmend" => $this->bsf->isNullCheck($poAmendVal, 'number'),
                                "APORegisterId" => $this->bsf->isNullCheck($PORegisterId, 'number')
                            ));

                            $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                            $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            $NPORegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();
                            //

                            //Old POTrans DC&Bill Details
                            $selOPo = $sql->select();
                            $selOPo->from(array("a" => "MMS_POTrans"))
                                ->columns(array(new Expression("a.POTransId,a.PORegisterId,b.POProjTransId,a.ResourceId,a.ItemId,b.CostCentreId,a.POQty,a.QAmount,a.GrossAmount,
                                  a.DCQty,a.AcceptQty,a.RejectQty,a.BillQty As BillQty")))
                                ->join(array("b" => "MMS_POProjTrans"), "a.POTransId=b.POTransId", array(), $selOPo::JOIN_INNER)
                                ->where("a.PORegisterId=$PORegisterId");
                            $statementOPo = $sql->getSqlStringForSqlObject($selOPo);
                            $OPOTrans = $dbAdapter->query($statementOPo, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach ($OPOTrans As $oPo) {
                                $oPoUpdate = $sql->update()
                                    ->table("MMS_Stock")
                                    ->set(array('POQty' => new Expression('POQty-' . $this->bsf->isNullCheck($oPo['POQty'], 'number') . ''),
                                           'POAmount'=>new Expression('POAmount-' . $this->bsf->isNullCheck($oPo['QAmount'], 'number') . ' ')))
                                    ->where('CostCentreId=' . $oPo['CostCentreId'] . ' and ResourceId= ' . $oPo['ResourceId'] . ' and
                                       ItemId=' . $oPo['ItemId'] . ' ');
                                 $oPoUpdateStatement = $sql->getSqlStringForSqlObject($oPoUpdate);
                                $dbAdapter->query($oPoUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }

                            //

                            //POTrans
                            $dCount = count($dist);
                            for ($d = 0; $d < $dCount; $d++) {
                                $disInsert = $sql->insert('MMS_PODistributorTrans');
                                $disInsert->values(array("PORegisterId" => $NPORegisterId, "VendorId" => $dist[$d]));
                                $disStatement = $sql->getSqlStringForSqlObject($disInsert);
                                $dbAdapter->query($disStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            $resTotal = $postParams['rowid'];
                            //POAnalTrans
                            $selectIPDAh = $sql->select();
                            $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                                ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER);

                            for ($i = 1; $i < $resTotal; $i++) {
                                if ($this->bsf->isNullCheck($postParams['qty_' . $i], 'number') > 0) {
                                    $potransInsert = $sql->insert('MMS_POTrans');
                                    $potransInsert->values(array("PORegisterId" => $NPORegisterId, "UnitId" => $postParams['unitid_' . $i],
                                        "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "POQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                        "BalQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'), "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'), "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                        "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'), "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'), "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                        "GrossAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'), "Description" => $this->bsf->isNullCheck($postParams['resspec_' . $i], 'string')));
                                    $potransStatement = $sql->getSqlStringForSqlObject($potransInsert);
                                    $dbAdapter->query($potransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $POTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    $poprojInsert = $sql->insert('MMS_POProjTrans');
                                    $poprojInsert->values(array("POTransId" => $POTransId, "CostCentreId" => $CostCenterId, "ResourceId" => $postParams['resourceid_' . $i],
                                        "ItemId" => $postParams['itemid_' . $i], "UnitId" => $postParams['unitid_' . $i], "POQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                        "BalQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number')));
                                    $poprojStatement = $sql->getSqlStringForSqlObject($poprojInsert);
                                    $dbAdapter->query($poprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    $POProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                    //Update DCQty,AccpetQty,BillQty
                                    foreach ($OPOTrans as $oPOt) {
                                        if ($oPOt["ResourceId"] == $postParams['resourceid_' . $i] && $oPOt["ItemId"] == $postParams['itemid_' . $i]) {
                                            $uptPOTr = $sql->update()
                                                ->table("MMS_POTrans")
                                                ->set(array('DCQty'=>$this->bsf->isNullCheck($oPOt['DCQty'], 'number'),
                                                  'AcceptQty'=>$this->bsf->isNullCheck($oPOt['AcceptQty'], 'number'),
                                                  'RejectQty'=>$this->bsf->isNullCheck($oPOt['RejectQty'], 'number'),
                                                  'BillQty'=>$this->bsf->isNullCheck($oPOt['BillQty'], 'number'),
                                                  'APOTransId'=>$this->bsf->isNullCheck($oPOt['POTransId'], 'number'),
                                                  'APORegisterId'=>$this->bsf->isNullCheck($oPOt['PORegisterId'], 'number')))
                                                ->where("POTransId=$POTransId");
                                            $opotransUpdate = $sql->getSqlStringForSqlObject($uptPOTr);
                                            $dbAdapter->query($opotransUpdate, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $uptPOTr = $sql->update()
                                                ->table("MMS_POProjTrans")
                                                ->set(array('DCQty'=>$this->bsf->isNullCheck($oPOt['DCQty'], 'number'),
                                                   'AcceptQty'=>$this->bsf->isNullCheck($oPOt['AcceptQty'], 'number'),
                                                   'RejectQty'=>$this->bsf->isNullCheck($oPOt['RejectQty'], 'number'),
                                                   'BillQty'=>$this->bsf->isNullCheck($oPOt['BillQty'], 'number')))
                                                ->where("POProjTransId=$POProjTransId");
                                            $opotransUpdate = $sql->getSqlStringForSqlObject($uptPOTr);
                                            $dbAdapter->query($opotransUpdate, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $updatecPOTr = $sql->update()
                                                ->table("MMS_POTrans")
                                                ->set(array('BalQty'=>new Expression('POQty-(CancelQty+AcceptQty+BillQty)')))
                                                ->where("POTransId=$POTransId");
                                            $opotransUpdate = $sql->getSqlStringForSqlObject($updatecPOTr);
                                            $dbAdapter->query($opotransUpdate, $dbAdapter::QUERY_MODE_EXECUTE);

                                            $updatecPOTr = $sql->update()
                                                ->table("MMS_POProjTrans")
                                                ->set(array('BalQty' => new Expression('POQty-(CancelQty+AcceptQty+BillQty)')))
                                                ->where("POProjTransId=$POProjTransId");
                                            $opotransUpdate = $sql->getSqlStringForSqlObject($updatecPOTr);
                                            $dbAdapter->query($opotransUpdate, $dbAdapter::QUERY_MODE_EXECUTE);

                                        }
                                    }
                                    //

                                    $decTotal = $postParams['iow_' . $i . '_rowid'];
                                    if ($decTotal > 0) {
                                        for ($j = 1; $j <= $decTotal; $j++) {
                                            if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') > 0) {
                                                $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                                //IPDTrans
                                                $ipdtransInsert = $sql->insert('MMS_IPDTrans');
                                                $ipdtransInsert->values(array("POTransId" => $POTransId, "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j], "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                    "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "UnitId" => $postParams['unitid_' . $i], "Status" => 'P'));
                                                $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                                $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                //IPDProjTrans
                                                $ipdprojInsert = $sql->insert('MMS_IPDProjTrans');
                                                $ipdprojInsert->values(array("IPDTransId" => $IPDTransId, "CostCentreId" => $CostCenterId, "POProjTransId" => $POProjTransId, "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j], "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                                    "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                                    "UnitId" => $postParams['unitid_' . $i], "Status" => 'P'));
                                                $ipdprojStatement = $sql->getSqlStringForSqlObject($ipdprojInsert);
                                                $dbAdapter->query($ipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                //DecisionTrans And RequestTrans Update
                                                $dectransUpdate = $sql->update();
                                                $dectransUpdate->table('VM_ReqDecQtyTrans');
                                                $dectransUpdate->set(array('IndAdjQty' => new Expression('IndAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                                $dectransUpdate->where(array('TransId' => $postParams['iow_' . $i . '_dectransid_' . $j]));
                                                $dectransStatement = $sql->getSqlStringForSqlObject($dectransUpdate);
                                                $dbAdapter->query($dectransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $reqtransUpdate = $sql->update();
                                                $reqtransUpdate->table('VM_RequestTrans');
                                                $reqtransUpdate->set(array('IndentQty' => new Expression('IndentQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''),
                                                    'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                                $reqtransUpdate->where(array('RequestTransId' => $postParams['iow_' . $i . '_reqtransid_' . $j]));
                                                $reqtransStatement = $sql->getSqlStringForSqlObject($reqtransUpdate);
                                                $dbAdapter->query($reqtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                //

                                                for ($k = 1; $k <= $wbsTotal; $k++) {
                                                    if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') > 0) {
                                                        //IPDAnalTrans
                                                        $ipdanalInsert = $sql->insert('MMS_IPDAnalTrans');
                                                        $ipdanalInsert->values(array("IPDProjTransId" => $IPDProjTransId, "AnalysisId" => $postParams['iow_' . $i . '_request_' . $j . '_wbsid_' . $k . ''],
                                                            "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "UnitId" => $postParams['unitid_' . $i],
                                                            "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number'), "DecATransId" => $postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . ''],
                                                            "DecTransId" => $postParams['iow_' . $i . '_request_' . $j . '_dectransid_' . $k . ''], "Status" => 'P'));
                                                        $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                                        $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $IPDAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        //DecisionAnalTrans And RequestAnalTrans Update
                                                        $decAnalUpdate = $sql->update();
                                                        $decAnalUpdate->table('VM_ReqDecQtyAnalTrans');
                                                        $decAnalUpdate->set(array('IndAdjQty' => new Expression('IndAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . '')));
                                                        $decAnalUpdate->where(array('RCATransId' => $postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . '']));
                                                        $decAnalStatement = $sql->getSqlStringForSqlObject($decAnalUpdate);
                                                        $dbAdapter->query($decAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                        $reqahUpdate = $sql->update();
                                                        $reqahUpdate->table('VM_RequestAnalTrans');
                                                        $reqahUpdate->set(array('IndentQty' => new Expression('IndentQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . ''), 'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . '')));
                                                        $reqahUpdate->where(array('RequestAHTransId' => $postParams['iow_' . $i . '_request_' . $j . '_reqahtransid_' . $k . '']));
                                                        $reqahStatement = $sql->getSqlStringForSqlObject($reqahUpdate);
                                                        $dbAdapter->query($reqahStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }


//                               if (dt.Rows.Count > 0) { dRoundDecimal = Convert.ToDecimal(dt.Rows[0]["RoundDecimal"]); }
//                               dt.Dispose();
//
//                               if (dRoundDecimal != 0)
//                               {
//                                   dRoundValue = Math.Round(dValue / dRoundDecimal, 0, MidpointRounding.AwayFromZero) * dRoundDecimal;
//                               }
//                               else
//                               {
//                                   dRoundValue = argValue;
//                               }
//
//                               return dRoundValue;

                                                //OldPOAnalTrans
                                                $selectOAnal = $sql->select();
                                                $selectOAnal->from(array('a' => 'MMS_POAnalTrans'))
                                                    ->columns(array(new Expression("a.AnalysisId,a.ResourceId,a.ItemId,a.DCQty,a.AcceptQty,a.RejectQty,a.BillQty As BillQty")))
                                                    ->join(array('b' => 'MMS_POProjTrans'), 'a.POProjTransId=b.POProjTransId', array(), $selectOAnal::JOIN_INNER)
                                                    ->join(array('c' => 'MMS_POTrans'), 'b.POTransId=c.POTransId', array(), $selectOAnal::JOIN_INNER)
                                                    ->where("c.PORegisterId=$PORegisterId");
                                                $oAnalstatement = $sql->getSqlStringForSqlObject($selectOAnal);
                                                $arr_oAnal = $dbAdapter->query($oAnalstatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                //


                                                //POAnalTrans
                                                $selectIPDAh = $sql->select();
                                                $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                                                    ->columns(array(new Expression("a.AnalysisId,a.ResourceId,a.ItemId,a.UnitId,SUM(a.Qty) As Qty")))
                                                    ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER)
                                                    ->group(array("a.AnalysisId", "a.ResourceId", "a.ItemId", "a.UnitId"))
                                                    ->where('b.IPDProjTransId=' . $IPDProjTransId . '');
                                                $statement = $sql->getSqlStringForSqlObject($selectIPDAh);
                                                $arr_ipdah = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                foreach ($arr_ipdah as $ipdah) {
                                                    if ($ipdah['Qty'] > 0) {
                                                        $poanalInsert = $sql->insert('MMS_POAnalTrans');
                                                        $poanalInsert->values(array("POProjTransId" => $POProjTransId, "AnalysisId" => $ipdah['AnalysisId'],
                                                            "ResourceId" => $ipdah['ResourceId'], "ItemId" => $ipdah['ItemId'],
                                                            "UnitId" => $ipdah['UnitId'], "POQty" => $this->bsf->isNullCheck($ipdah['Qty'], 'number'),
                                                            "BalQty" => $this->bsf->isNullCheck($ipdah['Qty'], 'number')));
                                                        $poanalStatement = $sql->getSqlStringForSqlObject($poanalInsert);
                                                        $dbAdapter->query($poanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        $POAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                        $selIPDUp = $sql->select();
                                                        $selIPDUp->from(array('a' => 'MMS_IPDAnalTrans'))
                                                            ->columns(array('IPDAHTransId'))
                                                            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selIPDUp::JOIN_INNER)
                                                            ->join(array('c' => 'MMS_POProjTrans'), 'b.POProjTransId=c.POProjTransId', array(), $selIPDUp::JOIN_INNER)
                                                            ->where('c.POProjTransId=' . $POProjTransId . ' and a.AnalysisId=' . $ipdah['AnalysisId'] . ' and a.ResourceId=' . $ipdah['ResourceId'] . ' and a.ItemId=' . $ipdah['ItemId'] . ' ');
                                                        $selIPDUpStmt = $sql->getSqlStringForSqlObject($selIPDUp);
                                                        $arr_ipdahup = $dbAdapter->query($selIPDUpStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                        foreach ($arr_ipdahup as $ipdahup) {
                                                            $ipdanalupdate = $sql->update();
                                                            $ipdanalupdate->table('MMS_IPDAnalTrans')
                                                                ->set(array('POAHTransId' => $POAHTransId))
                                                                ->where('IPDAHTransId=' . $ipdahup['IPDAHTransId'] . '');
                                                            $ipdanalupStatement = $sql->getSqlStringForSqlObject($ipdanalupdate);
                                                            $dbAdapter->query($ipdanalupStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                        }
                                                        $decAnalUpdate = $sql->update();
                                                        $decAnalUpdate->table('VM_ReqDecQtyAnalTrans');
                                                        //Update POAnalTrans DCQty,AcceptQty,RejectQty,BillQty
                                                        foreach ($arr_oAnal as $oAnal) {
                                                            if ($oAnal['AnalysisId'] == $ipdah['AnalysisId'] && $oAnal['ResourceId'] == $ipdah['ResourceId'] && $oAnal['ItemId'] == $ipdah['ItemId']) {
                                                                $updPOAnal = $sql->update();
                                                                $updPOAnal->table('MMS_POAnalTrans')
                                                                    ->set(array('DCQty'=>$this->bsf->isNullCheck($oAnal['DCQty'], 'number'),
                                                                      'AcceptQty'=>$this->bsf->isNullCheck($oAnal['AcceptQty'], 'number'),
                                                                      'RejectQty'=>$this->bsf->isNullCheck($oAnal['RejectQty'], 'number'),
                                                                      'BillQty'=>$this->bsf->isNullCheck($oAnal['BillQty'], 'number')))
                                                                    ->where("POAnalTransId=$POAHTransId");
                                                                $panalUpdateStatement = $sql->getSqlStringForSqlObject($updPOAnal);
                                                                $dbAdapter->query($panalUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $updPOAnal1 = $sql->update();
                                                                $updPOAnal1->table('MMS_POAnalTrans')
                                                                    ->set(array('BalQty'=>new Expression("POQty-(CancelQty+AcceptQty+BillQty)")))
                                                                    ->where("POAnalTransId=$POAHTransId");
                                                                $panalUpdateStatement1 = $sql->getSqlStringForSqlObject($updPOAnal1);
                                                                $dbAdapter->query($panalUpdateStatement1, $dbAdapter::QUERY_MODE_EXECUTE);
                                                            }
                                                        }
                                                        //
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    //Without Request Decision
                                    $wbsTotal = $postParams['wbs_' . $i . '_rowid'];

                                    if ($wbsTotal > 0) {
                                        for ($j = 1; $j <= $wbsTotal; $j++) {

                                            if ($this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $j], 'number') > 0) {
                                                $poanalInsert = $sql->insert('MMS_POAnalTrans');
                                                $poanalInsert->values(array("POProjTransId" => $POProjTransId, "AnalysisId" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_wbsid_' . $j], 'number'),
                                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'), "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                                    "UnitId" => $this->bsf->isNullCheck($postParams['unitid' . $i], 'number'), "POQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $j], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $j], 'number')));
                                                $poanalStatement = $sql->getSqlStringForSqlObject($poanalInsert);
                                                $dbAdapter->query($poanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $POAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                            }
                                        }
                                    }

                                    //Qualifier Insert
                                    $qual = $postParams['QualRowId_' . $i];

                                    for ($q = 1; $q <= $qual; $q++) {
                                        if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                            $qInsert = $sql->insert('MMS_POQualTrans');
                                            $qInsert->values(array("PORegisterId" => $NPORegisterId, "POTransId" => $POTransId, "QualifierId" => $postParams['Qual_' . $i . '_Id_' . $q], "YesNo" => "1", "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                                "Sign" => $postParams['Qual_' . $i . '_Sign_' . $q], "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number'), "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number'),
                                                "Expression" => $postParams['Qual_' . $i . '_Exp_' . $q], "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number'),));
                                            $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                            $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    //
                                }
                            }
                            //Terms
                            $termsTotal = $postParams['trowid'];
                            $valueFrom = 0;
                            if ($postParams['valuefrom'] == 'BaseAmount') {
                                $valueFrom = 0;
                            } else if ($postParams['valuefrom'] == 'NetAmount') {
                                $valueFrom = 1;
                            } else if ($postParams['valuefrom'] == 'GrossAmount') {
                                $valueFrom = 2;
                            }

                            for ($t = 1; $t < $termsTotal; $t++) {
                                $datest = date('Y-m-d');
                                $dt = strtotime(str_replace('/', '-', $postParams['date_' . $t]));
                                if ($dt != false) {
                                    $datest = date('Y-m-d', strtotime(str_replace('/', '-', $postParams['date_' . $t])));
                                }
                                if ($this->bsf->isNullCheck($postParams['termsid_' . $t], 'number') > 0) {
                                    $TDate = 'NULL';
                                    if ($postParams['date_' . $t] == '' || $postParams['date_' . $t] == null) {
                                        $TDate = null;
                                    } else {
                                        $TDate = $datest;
                                    }
                                    $termsInsert = $sql->insert('MMS_POPaymentTerms');
                                    $termsInsert->values(array("PORegisterId" => $NPORegisterId, "TermsId" => $this->bsf->isNullCheck($postParams['termsid_' . $t], 'number'),
                                        "Per" => $this->bsf->isNullCheck($postParams['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postParams['value_' . $t], 'number'), "Period" => $postParams['period_' . $t],
                                        "TDate" => $TDate, "TString" => $postParams['string_' . $t], "ValueFromNet" => $valueFrom));
                                    $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                    $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }
                            }
                            //
                            //Gross Rate Update
                            $selGross = $sql->select();
                            $selGross->from(array("a" => "MMS_POTrans"))
                                ->columns(array(new Expression("a.POTransId,Case When (ROW_NUMBER() OVER(PARTITION by A.POTransId Order by A.POTransId asc))=1 Then A.QAmount Else 0 End QAmt,
                                    Case When C.QualifierTypeId=3 Then ISNULL(B.NetAmt,0) Else 0 End VatAmt,
                                    Case When (ROW_NUMBER() OVER(PARTITION by A.POTransId Order by A.POTransId asc))=1 Then ISNULL(A.POQty,0) Else 0 End As POQty")))
                                ->join(array('b' => 'MMS_POQualTrans'), 'a.POTransId=b.POTransId', array(), $selGross::JOIN_LEFT)
                                ->join(array('c' => 'Proj_QualifierMaster'), 'b.QualifierId=c.QualifierId', array(), $selGross::JOIN_LEFT)
                                ->where("a.PORegisterId=$NPORegisterId");

                            $selGross1 = $sql->select();
                            $selGross1->from(array("g" => $selGross))
                                ->columns(array(new Expression("g.POTransId,(SUM(G.QAmt)-SUM(G.VatAmt))/SUM(G.POQty) As GrossRate")));
                            $selGross1->group(new Expression("g.POTransId"));
                            $statement = $sql->getSqlStringForSqlObject($selGross1);
                            $arr_gross = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            foreach ($arr_gross as $gross) {
                                $grossUpdate = $sql->update();
                                $grossUpdate->table('MMS_POTrans');
                                $grossUpdate->set(array(
                                        "GrossRate" => new Expression($this->bsf->isNullCheck($gross["GrossRate"], 'number')),
                                        "GrossAmount" => new Expression('CAST(POQty*' . $this->bsf->isNullCheck($gross["GrossRate"], 'number') . ' As Decimal(18,3)) ')
                                    )
                                );
                                $grossUpdate->where(array("POTransId" => $gross['POTransId']));
                                $grossUpdateStatement = $sql->getSqlStringForSqlObject($grossUpdate);
                                $dbAdapter->query($grossUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                            //Gross Amount Calculation
                            $selGTotal = $sql->select();
                            $selGTotal->from(array("a" => "MMS_POTrans"))
                                ->columns(array(new Expression("SUM(GrossAmount) As GrossAmount")))
                                ->where("PORegisterId=$NPORegisterId");
                            $statement = $sql->getSqlStringForSqlObject($selGTotal);
                            $arr_gtotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($arr_gtotal) > 0) {
                                $gtotalUpdate = $sql->update();
                                $gtotalUpdate->table('MMS_PORegister');
                                $gtotalUpdate->set(array(
                                        "GrossAmount" => new Expression($this->bsf->isNullCheck($arr_gtotal["GrossAmount"], 'number')))
                                );
                                $gtotalUpdate->where(array("PORegisterId" => $NPORegisterId));
                                $gtotalStatement = $sql->getSqlStringForSqlObject($gtotalUpdate);
                                $dbAdapter->query($gtotalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                            //DC Qual Update
                            $selDC = $sql->select();
                            $selDC->from(array("a" => "MMS_POTrans"))
                                ->columns(array(new Expression("a.POTransId,a.Rate,a.QRate,a.GrossRate As GrossRate")))
                                ->where("a.PORegisterId=$NPORegisterId and a.APOTransId>0 ");
                            $statement = $sql->getSqlStringForSqlObject($selDC);
                            $arr_dcqual = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                            if (count($arr_dcqual) > 0) {
                                foreach ($arr_dcqual as $dcA) {
                                    $iPOTransId = $this->bsf->isNullCheck($dcA['POTransId'], 'number');
                                    $dRate = $this->bsf->isNullCheck($dcA['Rate'], 'number');
                                    $dQRate = $this->bsf->isNullCheck($dcA['QRate'], 'number');
                                    $dGRate = $this->bsf->isNullCheck($dcA['GrossRate'], 'number');

                                    $selDC1 = $sql->select();
                                    $selDC1->from(array("a" => "MMS_POQualTrans"))
                                        ->columns(array(new Expression("QualifierId,ResourceId,ItemId,Sign,ExpPer,ExpressionAmt,SurCharge,
                                         SurChargeAmt,NetAmt,EDCess,EDCessAmt,Expression,HEDCess,HEDCessAmt,NetPer,TaxablePer,TaxableAmt As TaxableAmt ")))
                                        ->where("a.POTransId=$iPOTransId");
                                    $statement = $sql->getSqlStringForSqlObject($selDC1);
                                    $arr_dcqual1 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                    if (count($arr_dcqual1) > 0) {
                                        foreach ($arr_dcqual1 as $dqual1) {
                                            $selDC2 = $sql->select();
                                            $selDC2->from(array("a" => "MMS_POTrans"))
                                                ->columns(array(new Expression("APOTransId As APOTransId")))
                                                ->where("POTransId=$iPOTransId");
                                             $statement = $sql->getSqlStringForSqlObject($selDC2);
                                            $arr_dcqual2 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                            if (count($arr_dcqual2) > 0) {
                                                $iAPOTransId = 0;
                                                $iQualId = 0;
                                                foreach ($arr_dcqual2 as $dqual2) {
                                                    $iAPOTransId = $this->bsf->isNullCheck($dqual2['APOTransId'], 'number');
                                                    $iQualId = $this->bsf->isNullCheck($dqual1['QualifierId'], 'number');
                                                    do {

                                                        $selDC3 = $sql->select();
                                                        $selDC3->from(array("a" => "MMS_DCTrans"))
                                                            ->columns(array(new Expression("a.DCTransId,a.DCRegisterId As DCRegisterId")))
                                                            ->where("a.POTransId=$iAPOTransId and a.BillQty=0");
                                                        $statement = $sql->getSqlStringForSqlObject($selDC3);
                                                        $arr_dcqual3 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                                        $iDCId = 0;
                                                        $iDCRId = 0;
                                                        foreach ($arr_dcqual3 as $dqual3) {
                                                            $iDCId = $this->bsf->isNullCheck($dqual3['DCTransId'], 'number');
                                                            $iDCRId = $this->bsf->isNullCheck($dqual3['DCRegisterId'], 'number');


                                                            $selDC4 = $sql->select();
                                                            $selDC4->from(array("a" => "MMS_DCQualTrans"))
                                                                ->columns(array(new Expression("TransId,DCRegisterId,DCTransId,QualifierId,ResourceId,ItemId,
                                                                    Sign,ExpPer,ExpressionAmt,SurCharge,SurChargeAmt,NetAmt,EDCess,EDCessAmt,Expression,
                                                                    HEDCess,HEDCessAmt,NetPer,TaxablePer,TaxableAmt As TaxableAmt  ")))
                                                                    ->where("a.DCTransId IN (Select DCTransId From MMS_DCTrans Where DCTransId=$iDCId
                                                                    and DCRegisterId=$iDCRId and BillQty=0) and a.QualifierId=$iQualId ");
                                                             $statement = $sql->getSqlStringForSqlObject($selDC4);
                                                            $arr_dcqual4 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                            if (count($arr_dcqual4) > 0) {
                                                                $iIPDQualId = 0;
                                                                $iDRegId = 0;
                                                                $iDTransId = 0;
                                                                foreach ($arr_dcqual4 as $dqual4) {
                                                                    $iIPDQualId = $this->bsf->isNullCheck($dqual4['TransId'], 'number');
                                                                    $iDRegId = $this->bsf->isNullCheck($dqual4['DCRegisterId'], 'number');
                                                                    $iDTransId = $this->bsf->isNullCheck($dqual4['DCTransId'], 'number');

                                                                    $selUpdate1 = $sql->update();
                                                                    $selUpdate1->table("MMS_DCQualTrans");
                                                                    $selUpdate1->set(array("Sign" => new Expression("'".$this->bsf->isNullCheck($dqual4["Sign"], 'string')."'"),
                                                                        "ExpPer" => new Expression($this->bsf->isNullCheck($dqual4["ExpPer"], 'number')),
                                                                        "ExpressionAmt" => new Expression($this->bsf->isNullCheck($dqual4["ExpressionAmt"], 'number')),
                                                                        "SurCharge" => new Expression($this->bsf->isNullCheck($dqual4["SurCharge"], 'number')),
                                                                        "SurChargeAmt" => new Expression($this->bsf->isNullCheck($dqual4["SurChargeAmt"], 'number')),
                                                                        "NetAmt" => new Expression($this->bsf->isNullCheck($dqual4["NetAmt"], 'number')),
                                                                        "EDCess" => new Expression($this->bsf->isNullCheck($dqual4["EDCess"], 'number')),
                                                                        "EDCessAmt" => new Expression($this->bsf->isNullCheck($dqual4["EDCessAmt"], 'number')),
                                                                        "Expression" => new Expression("'".$this->bsf->isNullCheck($dqual4["Expression"], 'string')."'"),
                                                                        "HEDCess" => new Expression($this->bsf->isNullCheck($dqual4["HEDCess"], 'number')),
                                                                        "HEDCessAmt" => new Expression($this->bsf->isNullCheck($dqual4["HEDCessAmt"], 'number')),
                                                                        "NetPer" => new Expression($this->bsf->isNullCheck($dqual4["NetPer"], 'number')),
                                                                        "TaxablePer" => new Expression($this->bsf->isNullCheck($dqual4["TaxablePer"], 'number')),
                                                                        "TaxableAmt" => new Expression($this->bsf->isNullCheck($dqual4["TaxableAmt"], 'number'))
                                                                    ))
                                                                        ->where("TransId=$iIPDQualId");
                                                                    $updStatement = $sql->getSqlStringForSqlObject($selUpdate1);
                                                                    $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                    $selUpdate2 = $sql->update();
                                                                    $selUpdate2->table("MMS_DCTrans")
                                                                        ->set(array('Rate' => $dRate, 'QRate' => $dQRate, 'GrossRate' => $dGRate))
                                                                        ->where("DCTransId=$iDTransId and DCRegisterId=$iDRegId");
                                                                    $updStatement = $sql->getSqlStringForSqlObject($selUpdate2);
                                                                    $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                    $selUpdate3 = $sql->update();
                                                                    $selUpdate3->table("MMS_DCTrans")
                                                                        ->set(array('Amount' => new Expression("AcceptQty*Rate"), 'QAmount' => new Expression("AcceptQty*QRate"), 'GrossAmount' => new Expression("AcceptQty*GrossRate")))
                                                                        ->where("DCTransId=$iDTransId and DCRegisterId=$iDRegId");
                                                                    $updStatement = $sql->getSqlStringForSqlObject($selUpdate3);
                                                                    $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                    $selQup1 = $sql->select();
                                                                    $selQup1->from(array("a" => "MMS_DCTrans"))
                                                                        ->columns(array(new Expression("SUM(a.Amount) As Amount,SUM(a.QAmount) As QAmount,SUM(a.GrossAmount) As GrossAmount")))
                                                                        ->where("a.DCRegisterId=$iDRegId");
                                                                    $statement = $sql->getSqlStringForSqlObject($selQup1);
                                                                    $arr_dcqual5 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                                    if (count($arr_dcqual5) > 0) {
                                                                        $dOAmount = 0;
                                                                        $dOQAmount = 0;
                                                                        $dOGAmount = 0;
                                                                        foreach ($arr_dcqual5 as $dqu5) {
                                                                            $dOAmount = $this->bsf->isNullCheck($dqu5["Amount"], 'number');
                                                                            $dOQAmount = $this->bsf->isNullCheck($dqu5["QAmount"], 'number');
                                                                            $dOGAmount = $this->bsf->isNullCheck($dqu5["GrossAmount"], 'number');

                                                                            $selUpdate4 = $sql->update();
                                                                            $selUpdate4->table("MMS_DCRegister")
                                                                                ->set(array('Amount' => $dOAmount, 'NetAmount' => $dOQAmount, 'GrossAmount' => $dOGAmount))
                                                                                ->where("DCRegisterId=$iDRegId");
                                                                            $updStatement = $sql->getSqlStringForSqlObject($selUpdate4);
                                                                            $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                        }
                                                                    }

                                                                }
                                                            } else {

                                                                $dqualInsert = $sql->insert('MMS_DCQualTrans');
                                                                $dqualInsert->values(array(
                                                                    "DCRegisterId" => $iDCRId,
                                                                    "DCTransId" => $iDCId,
                                                                    "ResourceId" => $this->bsf->isNullCheck($dqual1["ResourceId"], 'number'),
                                                                    "ItemId" => $this->bsf->isNullCheck($dqual1["ItemId"], 'number'),
                                                                    "Sign" => $this->bsf->isNullCheck($dqual1["Sign"], 'string'),
                                                                    "ExpPer" => $this->bsf->isNullCheck($dqual1["ExpPer"], 'number'),
                                                                    "ExpressionAmt" => $this->bsf->isNullCheck($dqual1["ExpressionAmt"], 'number'),
                                                                    "SurCharge" => $this->bsf->isNullCheck($dqual1["SurCharge"], 'number'),
                                                                    "SurChargeAmt" => $this->bsf->isNullCheck($dqual1["SurChargeAmt"], 'number'),
                                                                    "NetAmt" => $this->bsf->isNullCheck($dqual1["NetAmt"], 'number'),
                                                                    "EDCess" => $this->bsf->isNullCheck($dqual1["EDCess"], 'number'),
                                                                    "EDCessAmt" => $this->bsf->isNullCheck($dqual1["EDCessAmt"], 'number'),
                                                                    "Expression" => $this->bsf->isNullCheck($dqual1["Expression"], 'string'),
                                                                    "HEDCess" => $this->bsf->isNullCheck($dqual1["HEDCess"], 'number'),
                                                                    "HEDCessAmt" => $this->bsf->isNullCheck($dqual1["HEDCessAmt"], 'number'),
                                                                    "NetPer" => $this->bsf->isNullCheck($dqual1["NetPer"], 'number'),
                                                                    "TaxablePer" => $this->bsf->isNullCheck($dqual1["TaxablePer"], 'number'),
                                                                    "TaxableAmt" => $this->bsf->isNullCheck($dqual1["TaxableAmt"], 'number')
                                                                ));
                                                                $qualStatement = $sql->getSqlStringForSqlObject($dqualInsert);
                                                                $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $selUpdate2 = $sql->update();
                                                                $selUpdate2->table("MMS_DCTrans")
                                                                    ->set(array('Rate' => $dRate, 'QRate' => $dQRate, 'GrossRate' => $dGRate))
                                                                    ->where("DCTransId=$iDCId and DCRegisterId=$iDCRId");
                                                                $updStatement = $sql->getSqlStringForSqlObject($selUpdate2);
                                                                $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $selUpdate3 = $sql->update();
                                                                $selUpdate3->table("MMS_DCTrans")
                                                                    ->set(array('Amount' => new Expression("AcceptQty*Rate"), 'QAmount' => new Expression("AcceptQty*QGrossRate"), 'GrossAmount' => new Expression("AcceptQty*GrossRate")))
                                                                    ->where("DCTransId=$iDCId and DCRegisterId=$iDCRId");
                                                                $updStatement = $sql->getSqlStringForSqlObject($selUpdate3);
                                                                $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $selQup1 = $sql->select();
                                                                $selQup1->from(array("a" => "MMS_DCTrans"))
                                                                    ->columns(array(new Expression("SUM(a.Amount) As Amount,SUM(a.QAmount) As QAmount,SUM(a.GrossAmount) As GrossAmount")))
                                                                    ->where("a.DCRegisterId=$iDCRId");
                                                                $statement = $sql->getSqlStringForSqlObject($selQup1);
                                                                $arr_dcqual5 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                                if (count($arr_dcqual5) > 0) {
                                                                    $dOAmount = 0;
                                                                    $dOQAmount = 0;
                                                                    $dOGAmount = 0;
                                                                    foreach ($arr_dcqual5 as $dqu5) {
                                                                        $dOAmount = $this->bsf->isNullCheck($dqu5["Amount"], 'number');
                                                                        $dOQAmount = $this->bsf->isNullCheck($dqu5["QAmount"], 'number');
                                                                        $dOGAmount = $this->bsf->isNullCheck($dqu5["GrossAmount"], 'number');

                                                                        $selUpdate4 = $sql->update();
                                                                        $selUpdate4->table("MMS_DCRegister")
                                                                            ->set(array('Amount' => $dOAmount, 'NetAmount' => $dOQAmount, 'GrossAmount' => $dOGAmount))
                                                                            ->where("DCRegisterId=$iDCRId");
                                                                        $updStatement = $sql->getSqlStringForSqlObject($selUpdate4);
                                                                        $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                    }
                                                                }
                                                            }
                                                        }

                                                        $selRAPO = $sql->select();
                                                        $selRAPO->from(array("a" => "MMS_POTrans"))
                                                            ->columns(array(new Expression("a.APOTransId As APOTransId")))
                                                            ->where("POTransId=$iAPOTransId and APOTransId>0");
                                                        $statement = $sql->getSqlStringForSqlObject($selRAPO);
                                                        $arr_dcqual6 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                                        if (count($arr_dcqual6) > 0) {
                                                            $iAPOTransId = $this->bsf->isNullCheck($arr_dcqual6["APOTransId"], 'number');
                                                        } else {
                                                            $iAPOTransId = 0;
                                                        }
                                                    } while ($iAPOTransId > 0);

                                                }
                                            }
                                        }
                                    } else {
                                        $selAPO = $sql->select();
                                        $selAPO->from(array("a" => "MMS_POTrans"))
                                            ->columns(array(new Expression("a.APOTransId As APOTransId")))
                                            ->where("POTransId=$iPOTransId");
                                        $statement = $sql->getSqlStringForSqlObject($selAPO);
                                        $arr_dcqual7 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                        if (count($arr_dcqual7) > 0) {
                                            $iAPOTransId = 0;
                                            foreach ($arr_dcqual7 as $dqu7) {
                                                $iAPOTransId = $this->bsf->isNullCheck($dqu7["APOTransId"], 'number');
                                                if ($iAPOTransId > 0) {
                                                    do {
                                                        $selDCTran = $sql->select();
                                                        $selDCTran->from("MMS_DCTrans")
                                                            ->columns(array(new Expression("DCTransId,DCRegisterId As DCRegisterId")))
                                                            ->where("POTransId=$iAPOTransId and BillQty=0");
                                                        $statement = $sql->getSqlStringForSqlObject($selDCTran);
                                                        $arr_dcqual8 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                                                        if (count($arr_dcqual8) > 0) {
                                                            $iDCId = 0;
                                                            $iDCRId = 0;
                                                            foreach ($arr_dcqual8 as $dqual8) {
                                                                $iDCId = $this->bsf->isNullCheck($dqual8["DCTransId"], 'number');
                                                                $iDCRId = $this->bsf->isNullCheck($dqual8["DCRegisterId"], 'number');

                                                                $selUpdate5 = $sql->update();
                                                                $selUpdate5->table("MMS_DCRegister")
                                                                    ->set(array('Rate' => $dRate, 'QRate' => $dQRate, 'GrossRate' => $dGRate))
                                                                    ->where("DCTransId=$iDCId and DCRegisterId=$iDCRId");
                                                                $updStatement = $sql->getSqlStringForSqlObject($selUpdate5);
                                                                $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $selUpdate6 = $sql->update();
                                                                $selUpdate6->table("MMS_DCTrans")
                                                                    ->set(array('Amount' => new Expression("AcceptQty*Rate"), 'QAmount' => new Expression("AcceptQty*QRate"), 'GrossAmount' => new Expression("AcceptQty*GrossRate")))
                                                                    ->where("DCTransId=$iDCId and DCRegisterId=$iDCRId");
                                                                $updStatement = $sql->getSqlStringForSqlObject($selUpdate6);
                                                                $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                                $selDCTran1 = $sql->select();
                                                                $selDCTran1->from(array("MMS_DCTrans"))
                                                                    ->columns(array(new Expression("SUM(Amount) As Amount,SUM(QAmount) As QAmount,SUM(GrossAmount) As GrossAmount")))
                                                                    ->where("DCRegisterId=$iDCRId");
                                                                $statement = $sql->getSqlStringForSqlObject($selDCTran1);
                                                                $arr_dcqual9 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                                                if (count($arr_dcqual9) > 0) {
                                                                    $dOAmount = 0;
                                                                    $OQAmount = 0;
                                                                    $dOGAmount = 0;
                                                                    foreach ($arr_dcqual9 as $dqual9) {
                                                                        $dOAmount = $this->bsf->isNullCheck($dqual9["Amount"], 'number');
                                                                        $OQAmount = $this->bsf->isNullCheck($dqual9["QAmount"], 'number');
                                                                        $dOGAmount = $this->bsf->isNullCheck($dqual9["GrossAmount"], 'number');
                                                                        $selUpdate7 = $sql->update();
                                                                        $selUpdate7->table("MMS_DCRegister")
                                                                            ->set(array('Amount' => $dOAmount, 'NetAmount' => $OQAmount, 'GrossAmount' => $dOGAmount))
                                                                            ->where("DCRegisterId=$iDCRId");
                                                                        $updStatement = $sql->getSqlStringForSqlObject($selUpdate7);
                                                                        $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        $selRAPO = $sql->select();
                                                        $selRAPO->from(array("a" => "MMS_POTrans"))
                                                            ->columns(array(new Expression("a.APOTransId As APOTransId")))
                                                            ->where("a.POTransId=$iAPOTransId and a.APOTransId>0");
                                                        $statement = $sql->getSqlStringForSqlObject($selRAPO);
                                                        $arr_dcqual10 = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                                        if (count($arr_dcqual10) > 0) {
                                                            $iAPOTransId = $this->bsf->isNullCheck($arr_dcqual10["APOTransId"], 'number');
                                                        } else {
                                                            $iAPOTransId = 0;
                                                        }
                                                    } while ($iAPOTransId > 0);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            //

                            //DC-PO Startec

                            $selPOT = $sql->select();
                            $selPOT->from(array("a" => "MMS_POTrans"))
                                ->columns(array(new Expression("a.APOTransId,a.POTransId As POTransId")))
                                ->where("PORegisterId=$NPORegisterId");
                             $statement = $sql->getSqlStringForSqlObject($selPOT);
                            $arr_POT = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                            $iPOTId = 0;
                            $iAPOTId = 0;
                            foreach ($arr_POT as $pot) {
                                $iPOTId = $this->bsf->isNullCheck($pot["POTransId"], 'number');
                                $iAPOTId = $this->bsf->isNullCheck($pot["APOTransId"], 'number');
                                do {
                                    $selDC = $sql->select();
                                    $selDC->from(array("a"=>"MMS_DCTrans"))
                                        ->columns(array(new Expression("a.DCTransId As DCTransId")))
                                        ->where("a.POTransId=$iAPOTId");
                                    $statement = $sql->getSqlStringForSqlObject($selDC);
                                    $arr_DC = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    $iDCTId = 0;
                                    if (count($arr_DC) > 0) {
                                        foreach ($arr_DC as $dc) {
                                            $iDCTId = $this->bsf->isNullCheck($dc["DCTransId"], 'number');
                                            $updateDC = $sql->update();
                                            $updateDC->table("MMS_DCTrans")
                                                ->set(array('POTransId' => $iPOTId))
                                                ->where("DCTransId=$iDCTId");
                                            $updStatement = $sql->getSqlStringForSqlObject($updateDC);
                                            $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }
                                    $selIPDDC = $sql->select();
                                    $selIPDDC->from(array("a" => "MMS_IPDTrans"))
                                        ->columns(array(new Expression("a.IPDTransId As IPDTransId")))
                                        ->where("a.POTransId=$iAPOTId and a.Status='D'");
                                     $statement = $sql->getSqlStringForSqlObject($selIPDDC);
                                    $arr_IPDDC = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    $iIPDTransId = 0;
                                    if (count($arr_IPDDC) > 0) {
                                        foreach ($arr_IPDDC as $ipddc) {
                                            $iIPDTransId = $this->bsf->isNullCheck($ipddc["IPDTransId"], 'number');
                                            $updateIPDDC = $sql->update();
                                            $updateIPDDC->table("MMS_IPDTrans")
                                                ->set(array('POTransId' => $iPOTId))
                                                ->where("IPDTransId=$iIPDTransId");
                                             $updStatement = $sql->getSqlStringForSqlObject($updateIPDDC);
                                            $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        }
                                    }

                                    //POProjTrans
                                    $selCPOProj = $sql->select();
                                    $selCPOProj->from(array("a" => "MMS_POProjTrans"))
                                        ->columns(array(new Expression("Distinct a.POProjTransId,a.CostCentreId,a.ResourceId,a.ItemId As ItemId")))
                                        ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $selCPOProj::JOIN_INNER)
                                        ->where("b.POTransId=$iPOTId");
                                    $statement = $sql->getSqlStringForSqlObject($selCPOProj);
                                    $arr_CPOProj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    $selPOProj = $sql->select();
                                    $selPOProj->from(array("a" => "MMS_POProjTrans"))
                                        ->columns(array(new Expression("Distinct a.POProjTransId As POProjTransId")))
                                        ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $selCPOProj::JOIN_INNER)
                                        ->where("b.POTransId=$iAPOTId");
                                    $statement = $sql->getSqlStringForSqlObject($selPOProj);
                                    $arr_POProj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    $iAPOProjTransId = 0;
                                    foreach ($arr_POProj as $poproj) {
                                        $iAPOProjTransId = $this->bsf->isNullCheck($poproj["POProjTransId"], 'number');
                                        $selIPDProj = $sql->select();
                                        $selIPDProj->from(array("a" => "MMS_IPDProjTrans"))
                                            ->columns(array(new Expression("a.IPDProjTransId,a.CostCentreId,a.ResourceId,a.ItemId As ItemId")))
                                            ->where("a.POProjTransId=$iAPOProjTransId and a.Status='D'");
                                        $statement = $sql->getSqlStringForSqlObject($selIPDProj);
                                        $arr_IPDProj = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        if (count($arr_IPDProj) > 0) {
                                            $iIPDProjTransId = 0;
                                            $iIPDCCId = 0;
                                            $iIPDResId = 0;
                                            $iIPDItemId = 0;
                                            foreach ($arr_IPDProj as $ipdproj) {
                                                $iIPDProjTransId = $this->bsf->isNullCheck($ipdproj["IPDProjTransId"], 'number');
                                                $iIPDCCId = $this->bsf->isNullCheck($ipdproj["CostCentreId"], 'number');
                                                $iIPDResId = $this->bsf->isNullCheck($ipdproj["ResourceId"], 'number');
                                                $iIPDItemId = $this->bsf->isNullCheck($ipdproj["ItemId"], 'number');
                                                $iCPOProjTransId = 0;
                                                foreach ($arr_CPOProj as $cpoproj) {
                                                    if ($this->bsf->isNullCheck($cpoproj["CostCentreId"], 'number') == $iIPDCCId && $this->bsf->isNullCheck($cpoproj["ResourceId"], 'number') == $iIPDResId && $this->bsf->isNullCheck($cpoproj["ItemId"], 'number') == $iIPDItemId) {
                                                        $iCPOProjTransId = $this->bsf->isNullCheck($cpoproj["POProjTransId"], 'number');
                                                        $updateIPDProj = $sql->update();
                                                        $updateIPDProj->table("MMS_IPDProjTrans")
                                                            ->set(array('POProjTransId' => $iCPOProjTransId))
                                                            ->where("IPDProjTransId=$iIPDProjTransId");
                                                        $updStatement = $sql->getSqlStringForSqlObject($updateIPDProj);
                                                        $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }

                                                }
                                            }
                                        }
                                    }

                                    //POAnalTrans
                                    $selCPOAnal = $sql->select();
                                    $selCPOAnal->from(array("a" => "MMS_POAnalTrans"))
                                        ->columns(array(new Expression("Distinct a.POAnalTransId,a.AnalysisId,a.ResourceId,a.ItemId As ItemId")))
                                        ->join(array("b" => "MMS_POProjTrans"), "a.POProjTransId=b.POProjTransId", array(), $selCPOAnal::JOIN_INNER)
                                        ->join(array("c" => "MMS_POTrans"), "b.POTransId=c.POTransId", array(), $selCPOAnal::JOIN_INNER)
                                        ->where("c.POTransId=$iPOTId");
                                    $statement = $sql->getSqlStringForSqlObject($selCPOAnal);
                                    $arr_CPOAnal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    $selPOAnal = $sql->select();
                                    $selPOAnal->from(array("a" => "MMS_POAnalTrans"))
                                        ->columns(array(new Expression("Distinct a.POAnalTransId,a.AnalysisId,a.ResourceId,a.ItemId As ItemId")))
                                        ->join(array("b" => "MMS_POProjTrans"), "a.POProjTransId=b.POProjTransId", array(), $selCPOAnal::JOIN_INNER)
                                        ->join(array("c" => "MMS_POTrans"), "b.POTransId=c.POTransId", array(), $selCPOAnal::JOIN_INNER)
                                        ->where("c.POTransId=$iAPOTId");
                                    $statement = $sql->getSqlStringForSqlObject($selPOAnal);
                                    $arr_POAnal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                    $iAPOAnalTransId = 0;
                                    foreach ($arr_POAnal as $poanal) {
                                        $iAPOAnalTransId = $this->bsf->isNullCheck($poanal["POAnalTransId"], 'number');
                                        $selIPDAnal = $sql->select();
                                        $selIPDAnal->from(array("a" => "MMS_IPDAnalTrans"))
                                            ->columns(array(new Expression("Distinct a.IPDAHTransId,a.AnalysisId,a.ResourceId,a.ItemId As ItemId")))
                                            ->where("a.POAHTransId=$iAPOAnalTransId and a.Status='D'");
                                         $statement = $sql->getSqlStringForSqlObject($selIPDAnal);
                                        $arr_IPDAnal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        if (count($arr_IPDAnal) > 0) {
                                            $iIPDAnalTransId = 0;
                                            $iIPDAnalysisId = 0;
                                            $iIPDResId = 0;
                                            $iIPDItemId = 0;
                                            foreach ($arr_IPDAnal as $ipdanal) {
                                                $iIPDAnalTransId = $this->bsf->isNullCheck($ipdanal["IPDAHTransId"], 'number');
                                                $iIPDAnalysisId = $this->bsf->isNullCheck($ipdanal["AnalysisId"], 'number');
                                                $iIPDResId = $this->bsf->isNullCheck($ipdanal["ResourceId"], 'number');
                                                $iIPDItemId = $this->bsf->isNullCheck($ipdanal["ItemId"], 'number');

                                                $iCPOAnalTransId = 0;
                                                foreach ($arr_CPOAnal as $cpoanal) {
                                                    if ($this->bsf->isNullCheck($cpoanal["AnalysisId"], 'number') == $iIPDAnalysisId && $this->bsf->isNullCheck($cpoanal["ResourceId"], 'number') == $iIPDResId && $this->bsf->isNullCheck($cpoanal["ItemId"], 'number') == $iIPDItemId) {
                                                        $iCPOAnalTransId = $this->bsf->isNullCheck($cpoanal["POAnalTransId"], 'number');
                                                        $updateCPo = $sql->update();
                                                        $updateCPo->table("MMS_IPDAnalTrans")
                                                            ->set(array('POAHTransId' => $iCPOAnalTransId))
                                                            ->where("IPDAHTransId=$iIPDAnalTransId");
                                                        $updStatement = $sql->getSqlStringForSqlObject($updateCPo);
                                                        $dbAdapter->query($updStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    //
                                    $selPAPO = $sql->select();
                                    $selPAPO->from(array("a" => "MMS_POTrans"))
                                        ->columns(array(new Expression("a.APOTransId As APOTransId")))
                                        ->where("a.POTransId=$iAPOTId and a.APOTransId>0");
                                    $statement = $sql->getSqlStringForSqlObject($selPAPO);
                                    $arr_PAPO = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                    if (count($arr_PAPO) > 0) {
                                        $iAPOTId = $this->bsf->isNullCheck($arr_PAPO["APOTransId"], 'number');
                                    } else {
                                        $iAPOTId = 0;
                                    }
                                } while ($iAPOTId > 0);
                            }
                        }

                        //DC-PO End


                        // Purchase Order Amendment End

                    }
                    else {

                        if ($vNo['genType']) {
                            $voucher = CommonHelper::getVoucherNo(301, date('Y/m/d', strtotime($PODate)), 0, 0, $dbAdapter, "I");
                            $voucherNo = $voucher['voucherNo'];
                        } else {
                            $voucherNo = $PONo;
                        }

                        if ($CPo['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(301, date('Y/m/d', strtotime($PODate)), 0, $CostCenterId, $dbAdapter, "I");
                            $CPONo = $voucher['voucherNo'];
                        } else {
                            $CPONo = $CPONo;
                        }

                        if ($CCPo['genType']==1) {
                            $voucher = CommonHelper::getVoucherNo(301, date('Y/m/d', strtotime($PODate)), $CompanyId, 0, $dbAdapter, "I");
                            $CCPONo = $voucher['voucherNo'];
                        } else {
                            $CCPONo = $CCPONo;
                        }

                        $registerInsert = $sql->insert('MMS_PORegister');
                        $registerInsert->values(array("PODate" => $PODate,"ReqDate" => $ReqDate,
                            "CostCentreId" => $CostCenterId,
                            "PONo" => $voucherNo, "VendorId" => $VendorId, "BranchId" => 0,
                            "PurchaseTypeId" => $PurTypeId, "PurchaseAccount" => $AccountId, "BranchTransId" => 0,
                            "Address1" => '', "Address2" => '', "Address3" => '',
                            "City" => '', "Pincode" => '', "Narration" => '', "ReqNo" => $RefNo, "CCPONo" => $CCPONo,
                            "CPONo" => $CPONo, "CurrencyId" => $CurrId, "Narration" => $this->bsf->isNullCheck($postParams['Narration'], 'string'),
                            "BranchId" => $this->bsf->isNullCheck($postParams['branchname'], 'number'),
                            "BranchTransId" => $this->bsf->isNullCheck($postParams['cperson'], 'number'),
                            "ProjectAddress" => $this->bsf->isNullCheck($postParams['deladdress'], 'string'),
                            "PoDelId" => $this->bsf->isNullCheck($postParams['warehouse'], 'number'),
                            "PoDelAdd" => $this->bsf->isNullCheck($postParams['whaddress'], 'string'),
                            "CompanyContactName"=>$this->bsf->isNullCheck($postParams['ccontact'], 'string'),
                            "CompanyContactNo"=>$this->bsf->isNullCheck($postParams['cphone'], 'string'),
                            "CompanyMobile"=>$this->bsf->isNullCheck($postParams['cmobile'], 'string'),
                            "CompanyEmail"=>$this->bsf->isNullCheck($postParams['cemail'], 'string'),
                            "SiteContactName"=>$this->bsf->isNullCheck($postParams['cccontact'], 'string'),
                            "SiteContactNo"=>$this->bsf->isNullCheck($postParams['ccphone'], 'string'),
                            "SiteMobile"=>$this->bsf->isNullCheck($postParams['ccmobile'], 'string'),
                            "SiteEmail"=>$this->bsf->isNullCheck($postParams['ccemail'], 'string'),
                            "Amount" => $amount,
                            "GrossAmount" => $amount,
                            "NetAmount" => $netamt,
                            "GridType" => $this->bsf->isNullCheck($postParams['gridtype'],'number'),
                            "BaseCurrencyId" => $this->bsf->isNullCheck($postParams['dCurrencyId'], 'number'),
                            "ExchangeRate" => $this->bsf->isNullCheck($postParams['exchangeRate'], 'number')
                        ));

                        $registerStatement = $sql->getSqlStringForSqlObject($registerInsert);
                        $registerResults = $dbAdapter->query($registerStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $PORegisterId = $dbAdapter->getDriver()->getLastGeneratedValue();

                        $dCount = count($dist);
                        for ($d = 0; $d < $dCount; $d++) {
                            $disInsert = $sql->insert('MMS_PODistributorTrans');
                            $disInsert->values(array("PORegisterId" => $PORegisterId, "VendorId" => $dist[$d]));
                            $disStatement = $sql->getSqlStringForSqlObject($disInsert);
                            $dbAdapter->query($disStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        $resTotal = $postParams['rowid'];

                        //POAnalTrans
                        $selectIPDAh = $sql->select();
                        $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER);

                        for ($i = 1; $i < $resTotal; $i++) {
                            if($this->bsf->isNullCheck($postParams['qty_' . $i], 'number') > 0) {
                                $treqDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['deldate_' . $i], 'string')));
                                $potransInsert = $sql->insert('MMS_POTrans');
                                $potransInsert->values(array("PORegisterId" => $PORegisterId, "UnitId" => $postParams['unitid_' . $i],
                                    "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "POQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "BalQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'), "Rate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'), "QRate" => $this->bsf->isNullCheck($postParams['qrate_' . $i], 'number'),
                                    "Amount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),
                                    "QAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'), "GrossRate" => $this->bsf->isNullCheck($postParams['rate_' . $i], 'number'),
                                    "GrossAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number'),"Description" => $this->bsf->isNullCheck($postParams['resspec_' . $i], 'string'),
                                    "ReqDate"=>$treqDate ));
                                $potransStatement = $sql->getSqlStringForSqlObject($potransInsert);
                                $dbAdapter->query($potransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $POTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                $poprojInsert = $sql->insert('MMS_POProjTrans');
                                $poprojInsert->values(array("POTransId" => $POTransId, "CostCentreId" => $CostCenterId, "ResourceId" => $postParams['resourceid_' . $i],
                                    "ItemId" => $postParams['itemid_' . $i], "UnitId" => $postParams['unitid_' . $i], "POQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "BalQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number')));
                                $poprojStatement = $sql->getSqlStringForSqlObject($poprojInsert);
                                $dbAdapter->query($poprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                $POProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();


                                $decTotal = $postParams['iow_' . $i . '_rowid'];
                                if($decTotal > 0) {
                                    for ($j = 1; $j <= $decTotal; $j++) {
                                        $wbsTotal = $postParams['iow_' . $i . '_request_' . $j . '_rowid'];
                                        //IPDTrans
                                        $ipdtransInsert = $sql->insert('MMS_IPDTrans');
                                        $ipdtransInsert->values(array("POTransId" => $POTransId, "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j], "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                            "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                            "UnitId" => $postParams['unitid_' . $i], "Status" => 'P'));
                                        $ipdtransStatement = $sql->getSqlStringForSqlObject($ipdtransInsert);
                                        $dbAdapter->query($ipdtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $IPDTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        //IPDProjTrans
                                        $ipdprojInsert = $sql->insert('MMS_IPDProjTrans');
                                        $ipdprojInsert->values(array("IPDTransId" => $IPDTransId, "CostCentreId" => $CostCenterId, "POProjTransId" => $POProjTransId, "DecisionId" => $postParams['iow_' . $i . '_decisionid_' . $j], "DecTransId" => $postParams['iow_' . $i . '_dectransid_' . $j],
                                            "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number'),
                                            "UnitId" => $postParams['unitid_' . $i], "Status" => 'P'));
                                        $ipdprojStatement = $sql->getSqlStringForSqlObject($ipdprojInsert);
                                        $dbAdapter->query($ipdprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        $IPDProjTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                        //DecisionTrans And RequestTrans Update
                                        $dectransUpdate = $sql->update();
                                        $dectransUpdate->table('VM_ReqDecQtyTrans');
                                        $dectransUpdate->set(array('IndAdjQty' => new Expression('IndAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                        $dectransUpdate->where(array('TransId' => $postParams['iow_' . $i . '_dectransid_' . $j]));
                                        $dectransStatement = $sql->getSqlStringForSqlObject($dectransUpdate);
                                        $dbAdapter->query($dectransStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                        $reqtransUpdate = $sql->update();
                                        $reqtransUpdate->table('VM_RequestTrans');
                                        $reqtransUpdate->set(array('IndentQty' => new Expression('IndentQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . ''), 'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_qty_' . $j], 'number') . '')));
                                        $reqtransUpdate->where(array('RequestTransId' => $postParams['iow_' . $i . '_reqtransid_' . $j]));
                                        $reqtransStatement = $sql->getSqlStringForSqlObject($reqtransUpdate);
                                        $dbAdapter->query($reqtransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                        //

                                        for ($k = 1; $k <= $wbsTotal; $k++) {
                                            if ($this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') > 0) {
                                                //IPDAnalTrans
                                                $ipdanalInsert = $sql->insert('MMS_IPDAnalTrans');
                                                $ipdanalInsert->values(array("IPDProjTransId" => $IPDProjTransId, "AnalysisId" => $postParams['iow_' . $i . '_request_' . $j . '_wbsid_' . $k . ''],
                                                    "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i], "UnitId" => $postParams['unitid_' . $i],
                                                    "Qty" => $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number'), "DecATransId" => $postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . ''],
                                                    "DecTransId" => $postParams['iow_' . $i . '_request_' . $j . '_dectransid_' . $k . ''], "Status" => 'P'));
                                                $ipdanalStatement = $sql->getSqlStringForSqlObject($ipdanalInsert);
                                                $dbAdapter->query($ipdanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $IPDAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                //DecisionAnalTrans And RequestAnalTrans Update
                                                $decAnalUpdate = $sql->update();
                                                $decAnalUpdate->table('VM_ReqDecQtyAnalTrans');
                                                $decAnalUpdate->set(array('IndAdjQty' => new Expression('IndAdjQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . '')));
                                                $decAnalUpdate->where(array('RCATransId' => $postParams['iow_' . $i . '_request_' . $j . '_decatransid_' . $k . '']));
                                                $decAnalStatement = $sql->getSqlStringForSqlObject($decAnalUpdate);
                                                $dbAdapter->query($decAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $reqahUpdate = $sql->update();
                                                $reqahUpdate->table('VM_RequestAnalTrans');
                                                $reqahUpdate->set(array('IndentQty' => new Expression('IndentQty+' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . ''), 'BalQty' => new Expression('BalQty-' . $this->bsf->isNullCheck($postParams['iow_' . $i . '_request_' . $j . '_qty_' . $k . ''], 'number') . '')));
                                                $reqahUpdate->where(array('RequestAHTransId' => $postParams['iow_' . $i . '_request_' . $j . '_reqahtransid_' . $k . '']));
                                                $reqahStatement = $sql->getSqlStringForSqlObject($reqahUpdate);
                                                $dbAdapter->query($reqahStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }

                                        //POAnalTrans
                                        $selectIPDAh = $sql->select();
                                        $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
                                            ->columns(array(new Expression("a.AnalysisId,a.ResourceId,a.ItemId,a.UnitId,SUM(a.Qty) As Qty")))
                                            ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER)
                                            ->group(array("a.AnalysisId", "a.ResourceId", "a.ItemId", "a.UnitId"))
                                            ->where('b.IPDProjTransId=' . $IPDProjTransId . '');
                                        $statement = $sql->getSqlStringForSqlObject($selectIPDAh);
                                        $arr_ipdah = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                                        foreach ($arr_ipdah as $ipdah) {
                                            if ($ipdah['Qty'] > 0) {
                                                $poanalInsert = $sql->insert('MMS_POAnalTrans');
                                                $poanalInsert->values(array("POProjTransId" => $POProjTransId, "AnalysisId" => $ipdah['AnalysisId'],
                                                    "ResourceId" => $ipdah['ResourceId'], "ItemId" => $ipdah['ItemId'],
                                                    "UnitId" => $ipdah['UnitId'], "POQty" => $this->bsf->isNullCheck($ipdah['Qty'], 'number'),
                                                    "BalQty" => $this->bsf->isNullCheck($ipdah['Qty'], 'number')));
                                                $poanalStatement = $sql->getSqlStringForSqlObject($poanalInsert);
                                                $dbAdapter->query($poanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                $POAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();

                                                $selIPDUp = $sql->select();
                                                $selIPDUp->from(array('a' => 'MMS_IPDAnalTrans'))
                                                    ->columns(array('IPDAHTransId'))
                                                    ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selIPDUp::JOIN_INNER)
                                                    ->join(array('c' => 'MMS_POProjTrans'), 'b.POProjTransId=c.POProjTransId', array(), $selIPDUp::JOIN_INNER)
                                                    ->where('c.POProjTransId=' . $POProjTransId . ' and a.AnalysisId=' . $ipdah['AnalysisId'] . ' and a.ResourceId=' . $ipdah['ResourceId'] . ' and a.ItemId=' . $ipdah['ItemId'] . ' ');
                                                $selIPDUpStmt = $sql->getSqlStringForSqlObject($selIPDUp);
                                                $arr_ipdahup = $dbAdapter->query($selIPDUpStmt, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                                foreach ($arr_ipdahup as $ipdahup) {
                                                    $ipdanalupdate = $sql->update();
                                                    $ipdanalupdate->table('MMS_IPDAnalTrans')
                                                        ->set(array('POAHTransId' => $POAHTransId))
                                                        ->where('IPDAHTransId=' . $ipdahup['IPDAHTransId'] . '');
                                                    $ipdanalupStatement = $sql->getSqlStringForSqlObject($ipdanalupdate);
                                                    $dbAdapter->query($ipdanalupStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                                }
                                            }
                                        }
                                    }
                                }

                                //Without Request Decision
                                $wbsTotal = $postParams['wbs_' . $i . '_rowid'];

                                if($wbsTotal > 0)
                                {
                                    for ($j = 1; $j <= $wbsTotal; $j++) {

                                        if($this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' . $j], 'number') > 0) {
                                            $poanalInsert = $sql->insert('MMS_POAnalTrans');
                                            $poanalInsert->values(array("POProjTransId" => $POProjTransId, "AnalysisId" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_wbsid_' .$j], 'number'),
                                                "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i],'number'), "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i],'number'),
                                                "UnitId" => $this->bsf->isNullCheck($postParams['unitid' . $i],'number'), "POQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' .$j], 'number'),
                                                "BalQty" => $this->bsf->isNullCheck($postParams['wbs_' . $i . '_qty_' .$j], 'number')));
                                            $poanalStatement = $sql->getSqlStringForSqlObject($poanalInsert);
                                            $dbAdapter->query($poanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            $POAHTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                                        }
                                    }
                                }

                                //Qualifier Insert
                                $qual = $postParams['QualRowId_' . $i];

                                for ($q = 1; $q <= $qual; $q++) {
                                    if ($postParams['Qual_' . $i . '_YesNo_' . $q] == "on" && ($this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') > 0 || $this->bsf->isNullCheck((float)$postParams['Qual_' . $i . '_Amount_' . $q], 'number') < 0)) {
                                        $qInsert = $sql->insert('MMS_POQualTrans');
                                        $qInsert->values(array("PORegisterId" => $PORegisterId, "POTransId" => $POTransId, "QualifierId" => $postParams['Qual_' . $i . '_Id_' . $q], "YesNo" => "1", "ResourceId" => $postParams['resourceid_' . $i], "ItemId" => $postParams['itemid_' . $i],
                                            "Sign" => $postParams['Qual_' . $i . '_Sign_' . $q], "ExpPer" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpPer_' . $q], 'number'), "ExpressionAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_ExpValue_' . $q], 'number'),
                                            "Expression" => $postParams['Qual_' . $i . '_Exp_' . $q], "NetAmt" => $this->bsf->isNullCheck($postParams['Qual_' . $i . '_Amount_' . $q], 'number'),));
                                        $qualStatement = $sql->getSqlStringForSqlObject($qInsert);
                                        $dbAdapter->query($qualStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                                    }
                                }
                                //
                            }

                            //Stock Updation
                            $stockSelect = $sql->select();
                            $stockSelect->from(array("a" => "mms_stock"))
                                ->columns(array("StockId"))
                                ->where(array("CostCentreId" => $postParams['CostCenterId'],
                                    "ResourceId" => $postParams['resourceid_' . $i],
                                    "ItemId" => $postParams['itemid_' . $i]
                                ));
                            $stockStatement = $sql->getSqlStringForSqlObject($stockSelect);
                            $stockselId = $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                            if (count($stockselId['StockId']) > 0) {

                                $stockUpdate = $sql->update();
                                $stockUpdate->table('mms_stock');
                                $stockUpdate->set(array(
                                    "POQty" => new Expression('POQty+' . $this->bsf->isNullCheck($postParams['qty_' . $i], 'number') . ''),
                                    "POAmount" => new Expression('POAmount+' . $this->bsf->isNullCheck($postParams['amount_' . $i], 'number') . ''),
                                    "POGAmount" => new Expression('POGAmount+' . $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number') . '')
                                ));
                                $stockUpdate->where(array("StockId" => $stockselId['StockId']));
                                $stockUpdateStatement = $sql->getSqlStringForSqlObject($stockUpdate);
                                $dbAdapter->query($stockUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            } else {
                                $stock = $sql->insert('mms_stock');
                                $stock->values(array("CostCentreId" => $this->bsf->isNullCheck($postParams['CostCenterId'], 'number'),
                                    "ResourceId" => $this->bsf->isNullCheck($postParams['resourceid_' . $i], 'number'),
                                    "ItemId" => $this->bsf->isNullCheck($postParams['itemid_' . $i], 'number'),
                                    "UnitId" => $this->bsf->isNullCheck($postParams['unitid_' . $i], 'number'),
                                    "POQty" => $this->bsf->isNullCheck($postParams['qty_' . $i], 'number'),
                                    "POAmount" => $this->bsf->isNullCheck($postParams['amount_' . $i], 'number'),
                                    "POGAmount" => $this->bsf->isNullCheck($postParams['baseamount_' . $i], 'number')
                                ));
                                $stockStatement = $sql->getSqlStringForSqlObject($stock);
                                $dbAdapter->query($stockStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                            //
                        }

                        //Terms
                        $termsTotal = $postParams['trowid'];
                        $valueFrom = 0;
                        if($postParams['valuefrom'] == 'BaseAmount')
                        {
                            $valueFrom=0;
                        }
                        else if($postParams['valuefrom'] == 'NetAmount')
                        {
                            $valueFrom=1;
                        }
                        else if($postParams['valuefrom'] == 'GrossAmount')
                        {
                            $valueFrom=2;
                        }
                        for ($t = 1; $t < $termsTotal; $t++) {
                            $datest=date('Y-m-d');
                            $dt=strtotime(str_replace('/','-',$postParams['date_' . $t ]));
                            if($dt!=false){
                                $datest= date('Y-m-d', strtotime(str_replace('/','-',$postParams['date_' . $t])));
                            }

                            if($this->bsf->isNullCheck($postParams['termsid_' . $t],'number') > 0) {
                                $TDate = 'NULL';
                                if ($postParams['date_' . $t] == '' || $postParams['date_' . $t] == null) {
                                    $TDate = null;
                                } else {

                                    $TDate = $datest;
                                }
                                $termsInsert = $sql->insert('MMS_POPaymentTerms');
                                $termsInsert->values(array("PORegisterId" => $PORegisterId, "TermsId" => $this->bsf->isNullCheck($postParams['termsid_' . $t],'number'),
                                    "Per" => $this->bsf->isNullCheck($postParams['per_' . $t], 'number'), "Value" => $this->bsf->isNullCheck($postParams['value_' . $t], 'number'), "Period" => $postParams['period_' . $t],
                                    "TDate" => $TDate, "TString" => $postParams['string_' . $t], "ValueFromNet" => $valueFrom));
                                $termsStatement = $sql->getSqlStringForSqlObject($termsInsert);
                                $dbAdapter->query($termsStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        //


//                       $selectIPDAh = $sql->select();
//                       $selectIPDAh->from(array('a' => 'MMS_IPDAnalTrans'))
//                           ->columns(array(new Expression("a.AnalysisId,a.ResourceId,a.ItemId,a.UnitId,SUM(a.Qty) As Qty")))
//                           ->join(array('b' => 'MMS_IPDProjTrans'), 'a.IPDProjTransId=b.IPDProjTransId', array(), $selectIPDAh::JOIN_INNER)
//                           ->group(array("a.AnalysisId", "a.ResourceId", "a.ItemId", "a.UnitId"))
//                           ->where('b.IPDProjTransId=' . $IPDProjTransId . '');
//                       $statement = $sql->getSqlStringForSqlObject($selectIPDAh);
//                       $arr_ipdah = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


                        //Gross Rate Update
                        $selGross = $sql -> select();
                        $selGross->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("a.POTransId,Case When (ROW_NUMBER() OVER(PARTITION by A.POTransId Order by A.POTransId asc))=1 Then A.QAmount Else 0 End QAmt,
                                    Case When C.QualifierTypeId=3 Then ISNULL(B.NetAmt,0) Else 0 End VatAmt,
                                    Case When (ROW_NUMBER() OVER(PARTITION by A.POTransId Order by A.POTransId asc))=1 Then ISNULL(A.POQty,0) Else 0 End As POQty")))
                            ->join(array('b' => 'MMS_POQualTrans'),'a.POTransId=b.POTransId',array(),$selGross::JOIN_LEFT)
                            ->join(array('c' => 'Proj_QualifierMaster'),'b.QualifierId=c.QualifierId',array(),$selGross::JOIN_LEFT)
                            ->where("a.PORegisterId=$PORegisterId");

                        $selGross1 = $sql -> select();
                        $selGross1->from(array("g" => $selGross))
                            ->columns(array(new Expression("g.POTransId,(SUM(G.QAmt)-SUM(G.VatAmt))/SUM(G.POQty) As GrossRate")));
                        $selGross1->group(new Expression("g.POTransId"));
                        $statement = $sql->getSqlStringForSqlObject($selGross1);
                        $arr_gross = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        foreach ($arr_gross as $gross) {
                            $grossUpdate = $sql->update();
                            $grossUpdate->table('MMS_POTrans');
                            $grossUpdate->set(array(
                                    "GrossRate" => new Expression($this->bsf->isNullCheck($gross["GrossRate"], 'number')),
                                    "GrossAmount" => new Expression('CAST(POQty*' . $this->bsf->isNullCheck($gross["GrossRate"], 'number') . ' As Decimal(18,3)) ')
                                )
                            );
                            $grossUpdate->where(array("POTransId" => $gross['POTransId']));
                            $grossUpdateStatement = $sql->getSqlStringForSqlObject($grossUpdate);
                            $dbAdapter->query($grossUpdateStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        //
                        //Gross Amount Calculation
                        $selGTotal = $sql -> select();
                        $selGTotal -> from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("SUM(GrossAmount) As GrossAmount")))
                            ->where("PORegisterId=$PORegisterId");
                        $statement = $sql->getSqlStringForSqlObject($selGTotal);
                        $arr_gtotal = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                        if(count($arr_gtotal) > 0)
                        {
                            $gtotalUpdate = $sql -> update();
                            $gtotalUpdate -> table('MMS_PORegister');
                            $gtotalUpdate->set(array(
                                    "GrossAmount" =>new Expression($this->bsf->isNullCheck($arr_gtotal["GrossAmount"], 'number')))
                            );
                            $gtotalUpdate->where(array("PORegisterId" => $PORegisterId));
                            $gtotalStatement = $sql->getSqlStringForSqlObject($gtotalUpdate);
                            $dbAdapter->query($gtotalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                        //


                        //
                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'Purchase-Order',$PORegisterId,$CostCenterId,$CompanyId,'MMS',$voucherNo,$this->auth->getIdentity()->UserId,0,0);
                    $this->redirect()->toRoute('mms/default', array('controller' => 'purchase', 'action' => 'display-register', 'rid' => $PORegisterId));
                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }


            } else {


            }
        }
    }

    public function deletePOAction(){
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
        $poregid = $this->params()->fromRoute('regid');
        //echo $dcid; die;

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
                $selPrevAnal = $sql->select();
                $selPrevAnal->from(array("a" => "MMS_IPDAnalTrans"))
                    ->columns(array(new Expression("a.DecTransId,a.DecATransId,e.ReqTransId,e.ReqAHTransId,A.Qty As Qty")))
                    ->join(array("b" => "MMS_POAnalTrans"), "a.POAHTransId=b.POAnalTransId", array(), $selPrevAnal::JOIN_INNER)
                    ->join(array("c" => "MMS_POProjTrans"), "b.POProjTransId=c.POProjTransId", array(), $selPrevAnal::JOIN_INNER)
                    ->join(array("d" => "MMS_POTrans"), "c.POTransId=d.POTransId", array(), $selPrevAnal::JOIN_INNER)
                    ->join(array("e" => "VM_ReqDecQtyAnalTrans"), "a.DecTransId=e.TransId and a.DecATransId=e.RCATransId", array(), $selPrevAnal::JOIN_INNER)
                    ->where(array("d.PORegisterId" => $poregid));
                $statementPrev = $sql->getSqlStringForSqlObject($selPrevAnal);
                $prevanal = $dbAdapter->query($statementPrev, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach ($prevanal as $arrprevanal) {
                    $updDecAnal = $sql->update();
                    $updDecAnal->table('VM_ReqDecQtyAnalTrans');
                    $updDecAnal->set(array(
                        'IndAdjQty' => new Expression('IndAdjQty-' . $arrprevanal['Qty'] . '')
                    ));
                    $updDecAnal->where(array('RCATransId' => $arrprevanal['DecATransId'], 'TransId' => $arrprevanal['DecTransId']));
                    $updDecAnalStatement = $sql->getSqlStringForSqlObject($updDecAnal);
                    $dbAdapter->query($updDecAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $updReqAnal = $sql->update();
                    $updReqAnal->table('VM_RequestAnalTrans');
                    $updReqAnal->set(array(
                        'IndentQty' => new Expression('IndentQty-' . $arrprevanal['Qty'] . ''),
                        'BalQty' => new Expression('BalQty+' . $arrprevanal['Qty'] . '')
                    ));
                    $updReqAnal->where(array('RequestAHTransId' => $arrprevanal['ReqAHTransId'], 'ReqTransId' => $arrprevanal['ReqTransId']));
                    $updReqAnalStatement = $sql->getSqlStringForSqlObject($updReqAnal);
                    $dbAdapter->query($updReqAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                }
                //
                //Reverse DecQtyTrans & RequestTrans
                $selPrevTrans = $sql->select();
                $selPrevTrans->from(array("a" => "MMS_IPDTrans"))
                    ->columns(array(new Expression("a.DecisionId,a.DecTransId,c.ReqTransId,c.DecisionId,a.Qty As Qty ")))
                    ->join(array("b" => "MMS_POTrans"), "a.POTransId=b.POTransId", array(), $selPrevTrans::JOIN_INNER)
                    ->join(array("c" => "VM_ReqDecQtyTrans"), "a.DecTransId=c.TransId and a.Decisionid=c.DecisionId", array(), $selPrevTrans::JOIN_INNER)
                    ->where(array("b.PORegisterId" => $poregid));
                $statementPrevTrans = $sql->getSqlStringForSqlObject($selPrevTrans);
                $prevtrans = $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                foreach ($prevtrans as $arrprevtrans) {
                    $updDecTran = $sql->update();
                    $updDecTran->table('VM_ReqDecQtyTrans');
                    $updDecTran->set(array(
                        'IndAdjQty' => new Expression('IndAdjQty-' . $arrprevtrans['Qty'] . '')
                    ));
                    $updDecTran->where(array('TransId' => $arrprevtrans['DecTransId'], 'DecisionId' => $arrprevtrans['DecisionId']));
                    $statementPrevTrans = $sql->getSqlStringForSqlObject($updDecTran);
                    $dbAdapter->query($statementPrevTrans, $dbAdapter::QUERY_MODE_EXECUTE);

                    $updReqTrans = $sql->update();
                    $updReqTrans->table('VM_RequestTrans');
                    $updReqTrans->set(array(
                        'IndentQty' => new Expression('IndentQty-' . $arrprevtrans['Qty'] . ''),
                        'BalQty' => new Expression('BalQty+' . $arrprevtrans['Qty'] . '')
                    ));
                    $updReqTrans->where(array('RequestTransId' => $arrprevtrans['ReqTransId']));
                    $statementPrevReqTrans = $sql->getSqlStringForSqlObject($updReqTrans);
                    $dbAdapter->query($statementPrevReqTrans, $dbAdapter::QUERY_MODE_EXECUTE);
                }

                //Amendment Live PO
                $iARegId = 0;
                $iATransId = 0;
                $iAnalId = 0;
                $iPTransId = 0;
                $iTransId = 0;
                $dIQty = 0;
                $iInTranId = 0;
                $iReqTranId = 0;
                $iInPTransId = 0;
                $iIPDProjTransId = 0;
                $iIAHTransId = 0;

                $selAmend = $sql -> select();
                $selAmend->from(array("a" => "MMS_POTrans"))
                    ->columns(array(new Expression("a.APORegisterId,a.APOTransId As APOTransId")))
                    ->where("a.PORegisterId=$poregid and a.APORegisterId>0");
                $amendStatement = $sql->getSqlStringForSqlObject($selAmend);
                $arr_amend1 = $dbAdapter->query($amendStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                if(count($arr_amend1) > 0) {
                    foreach($arr_amend1 as $amd1) {
                        $iARegId = $this->bsf->isNullCheck($amd1['APORegisterId'],'number');
                        $iATransId = $this->bsf->isNullCheck($amd1['APOTransId'],'number');

                        //Stock Updation
                        $iSResId = 0;
                        $iSItemId = 0;
                        $dSPOQty = 0;
                        $dSPOAmt = 0;
                        $dSPOGAmt = 0;

                        $selAmend1 = $sql -> select();
                        $selAmend1->from(array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("a.POTransId,a.ResourceId,a.ItemId,b.CostCentreId,(b.POQty-b.CancelQty) As POQty,
                                 ((b.POQty-b.CancelQty)*a.QRate) As Amount,((b.POQty-b.CancelQty) * a.GrossRate) GrossAmount ")))
                            ->join(array("b"=>"MMS_POProjTrans"),"a.POTransId=b.POTransId",array(),$selAmend1::JOIN_INNER)
                            ->where("a.POTransId=$iATransId and a.PORegisterId=$iARegId");
                        $amendStatement = $sql->getSqlStringForSqlObject($selAmend1);
                        $arr_amend2 = $dbAdapter->query($amendStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($arr_amend2)>0) {
                            $iCCId = 0;
                            foreach($arr_amend2 as $amd2) {
                                $iSResId = $this->bsf->isNullCheck($amd2['ResourceId'],'number');
                                $iSItemId = $this->bsf->isNullCheck($amd2['ItemId'],'number');
                                $dSPOQty = $this->bsf->isNullCheck($amd2['POQty'],'number');
                                $dSPOAmt = $this->bsf->isNullCheck($amd2['Amount'],'number');
                                $dSPOGAmt = $this->bsf->isNullCheck($amd2['GrossAmount'],'number');
                                $iCCId=$this->bsf->isNullCheck($amd2['CostCentreId'],'number');

                                $updAmend1 = $sql -> update();
                                $updAmend1 ->table('MMS_Stock');
                                $updAmend1->set(array(
                                    'POQty' => new Expression('POQty+'. $dSPOQty .''),
                                    'POAmount' => new Expression('POAmount+'.$dSPOAmt.''),
                                    'POGAmount' => new Expression('POGAmount+'.$dSPOGAmt.'')
                                ))
                                    ->where("CostCentreId=$iCCId and Resourceid=$iSResId and ItemId=$iSItemId");
                                $updAmendStatement = $sql->getSqlStringForSqlObject($updAmend1);
                                $dbAdapter->query($updAmendStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        //Decision Trans

                        $selDec1 = $sql -> select();
                        $selDec1->from(array("a" => "MMS_IPDTrans"))
                            ->columns(array(new Expression("a.DecTransId,c.ReqTransId,(A.Qty-A.CancelQty) As Qty")))
                            ->join(array("b" => "MMS_POTrans"),"a.POTransId=b.POTransId",array(),$selDec1::JOIN_INNER)
                            ->join(array("c" => "VM_ReqDecQtyTrans"),"a.DecTransId=c.TransId and a.DecisionId=c.DecisionId",array(),$selDec1::JOIN_INNER)
                            ->where("a.POTransId=$iATransId and a.Status='P'");
                        $decStatement = $sql->getSqlStringForSqlObject($selDec1);
                        $arr_dec1 = $dbAdapter->query($decStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($arr_dec1)>0) {
                            foreach($arr_dec1 as $dec1) {
                                $dIQty =$this->bsf->isNullCheck($dec1['Qty'],'number');
                                $iInTranId =$this->bsf->isNullCheck($dec1['DecTransId'],'number');
                                $iReqTranId = $this->bsf->isNullCheck($dec1['ReqTransId'],'number');

                                $updDec1 = $sql -> update();
                                $updDec1 ->table('VM_ReqDecQtyTrans');
                                $updDec1->set(array(
                                    'IndAdjQty' => new Expression('IndAdjQty+'. $dIQty .'')
                                ))
                                    ->where("TransId=$iInTranId");
                                $updDecStatement = $sql->getSqlStringForSqlObject($updDec1);
                                $dbAdapter->query($updDecStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updDec2 = $sql -> update();
                                $updDec2 ->table('VM_RequestTrans');
                                $updDec2->set(array(
                                    'IndentQty' => new Expression('IndentQty+'. $dIQty .''),
                                    'BalQty' => new Expression('BalQty-'. $dIQty .'')
                                ))
                                    ->where("RequestTransId=$iReqTranId");
                                $updDecStatement = $sql->getSqlStringForSqlObject($updDec2);
                                $dbAdapter->query($updDecStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                            }
                        }

                        //Decision Anal Trans
                        $selDec2 = $sql -> select();
                        $selDec2->from(array("a" => "MMS_IPDAnalTrans"))
                            ->columns(array(new Expression("a.DecATransId,d.RequestAHTransId,(A.Qty-A.CancelQty) As Qty")))
                            ->join(array("b" => "MMS_POAnalTrans"),"a.POAHTransId=b.POAnalTransId",array(),$selDec2::JOIN_INNER)
                            ->join(array("c" => "VM_ReqDecQtyAnalTrans"),"a.DecTransId=c.TransId and a.DecATransId=c.RCATransId",array(),$selDec2::JOIN_INNER)
                            ->join(array("d" => "VM_RequestAnalTrans"),"c.ReqAHTransId=d.RequestAHTransId",array(),$selDec2::JOIN_INNER)
                            ->join(array("e" => "MMS_POProjTrans"),"b.POProjTransId=e.POProjTransId",array(),$selDec2::JOIN_INNER)
                            ->join(array("f" => "MMS_POTrans"),"e.POTransId=f.POTransId",array(),$selDec2::JOIN_INNER)
                            ->where("f.POTransId=$iATransId and a.Status='P'");
                        $decStatement = $sql->getSqlStringForSqlObject($selDec2);
                        $arr_dec2 = $dbAdapter->query($decStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($arr_dec2) > 0) {
                            foreach($arr_dec2 as $dec1) {
                                $dAnalQty = $this->bsf->isNullCheck($dec1['Qty'],'number');
                                $decATransId = $this->bsf->isNullCheck($dec1['DecATransId'],'number');
                                $dReqAHTransId = $this->bsf->isNullCheck($dec1['RequestAHTransId'],'number');

                                $updDec1 = $sql -> update();
                                $updDec1 ->table('VM_ReqDecQtyAnalTrans');
                                $updDec1->set(array(
                                    'IndAdjQty' => new Expression('IndAdjQty+'. $dAnalQty .'')
                                ))
                                    ->where("RCATransId=$decATransId");
                                $updDecStatement = $sql->getSqlStringForSqlObject($updDec1);
                                $dbAdapter->query($updDecStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                                $updDec2 = $sql -> update();
                                $updDec2 ->table('VM_RequestAnalTrans');
                                $updDec2->set(array(
                                    'IndentQty' => new Expression('IndentQty+'. $dAnalQty .''),
                                    'BalQty' => new Expression('BalQty-'. $dAnalQty .'')
                                ))
                                    ->where("RequestAHTransId=$dReqAHTransId");
                                $updDecStatement = $sql->getSqlStringForSqlObject($updDec2);
                                $dbAdapter->query($updDecStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        //POAnalTrans

                        $selPOAnal = $sql -> select();
                        $selPOAnal -> from (array("a" => "MMS_POAnalTrans"))
                            ->columns(array(new Expression("a.POAnalTransId As POAnalTransId")))
                            ->where("POProjTransId IN (Select POProjTransId From MMS_POProjTrans Where POTransId IN (
                                  Select POTransId From MMS_POTrans Where PORegisterId=$iARegId))");
                        $poanalStatement = $sql->getSqlStringForSqlObject($selPOAnal);
                        $arr_poanal= $dbAdapter->query($poanalStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($arr_poanal)>0){
                            foreach($arr_poanal as $anal) {
                                $iAnalId = $this->bsf->isNullCheck($anal['POAnalTransId'],'number');
                                $updAnal = $sql -> update();
                                $updAnal ->table('MMS_POAnalTrans');
                                $updAnal->set(array(
                                    'LivePO' => 1
                                ))
                                    ->where("POAnalTransId=$iAnalId");
                                $updAnalStatement = $sql->getSqlStringForSqlObject($updAnal);
                                $dbAdapter->query($updAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        //POProjTrans

                        $selPOProj = $sql -> select();
                        $selPOProj -> from (array("a" => "MMS_POProjTrans"))
                            ->columns(array(new Expression("a.POProjTransId As POProjTransId")))
                            ->where("POTransId IN (
                                  Select POTransId From MMS_POTrans Where PORegisterId=$iARegId)");
                        $poprojStatement = $sql->getSqlStringForSqlObject($selPOProj);
                        $arr_poproj= $dbAdapter->query($poprojStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($arr_poproj)>0) {
                            foreach($arr_poproj as $proj) {
                                $iPTransId = $this->bsf->isNullCheck($proj['POProjTransId'],'number');
                                $updProj = $sql -> update();
                                $updProj ->table('MMS_POProjTrans');
                                $updProj->set(array(
                                    'LivePO' => 1
                                ))
                                    ->where("POProjTransId=$iPTransId");
                                $updProjStatement = $sql->getSqlStringForSqlObject($updProj);
                                $dbAdapter->query($updProjStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }
                        //

                        //POTrans
                        $selPOTrans = $sql -> select();
                        $selPOTrans -> from (array("a" => "MMS_POTrans"))
                            ->columns(array(new Expression("a.POTransId As POTransId")))
                            ->where("PORegisterId=$iARegId");
                        $potransStatement = $sql->getSqlStringForSqlObject($selPOTrans);
                        $arr_potrans= $dbAdapter->query($potransStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                        if(count($arr_potrans)>0) {
                            foreach($arr_potrans as $trans) {
                                $iTransId = $this->bsf->isNullCheck($trans['POTransId'],'number');
                                $updTrans = $sql -> update();
                                $updTrans ->table('MMS_POTrans');
                                $updTrans->set(array(
                                    'LivePO' => 1
                                ))
                                    ->where("POTransId=$iTransId");
                                $updTransStatement = $sql->getSqlStringForSqlObject($updTrans);
                                $dbAdapter->query($updTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                            }
                        }

                        //PORegister
                        $updReg = $sql -> update();
                        $updReg ->table('MMS_PORegister');
                        $updReg->set(array(
                            'LivePO' => 1
                        ))
                            ->where("PORegisterId=$iARegId");
                        $updRegStatement = $sql->getSqlStringForSqlObject($updReg);
                        $dbAdapter->query($updRegStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }
                }


                //





                $delPODisTrans = $sql->delete();
                $delPODisTrans->from('MMS_PODistributorTrans')
                    ->where(array("PORegisterId" => $poregid));
                $PODistStatement = $sql->getSqlStringForSqlObject($delPODisTrans);
                $dbAdapter->query($PODistStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //POPaymentTerms
                $delPOPayTrans = $sql->delete();
                $delPOPayTrans->from('MMS_POPaymentTerms')
                    ->where(array("PORegisterId" => $poregid));
                $POPayStatement = $sql->getSqlStringForSqlObject($delPOPayTrans);
                $dbAdapter->query($POPayStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //POQualTrans
                $delPOQualTrans = $sql->delete();
                $delPOQualTrans->from('MMS_POQualTrans')
                    ->where(array("PORegisterId" => $poregid));
                $POQualStatement = $sql->getSqlStringForSqlObject($delPOQualTrans);
                $dbAdapter->query($POQualStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                //IPDAnalTrans
                $delIPDAnalSQ3 = $sql->select();
                $delIPDAnalSQ3->from("MMS_POTrans")
                    ->columns(array("POTransId"))
                    ->where(array("PORegisterId" => $poregid));
                $delIPDAnalSQ2 = $sql->select();
                $delIPDAnalSQ2->from("MMS_POProjTrans")
                    ->columns(array("POProjTransId"))
                    ->where->expression('POTransId IN ?', array($delIPDAnalSQ3));
                $delIPDAnalSQ1 = $sql->select();
                $delIPDAnalSQ1->from("MMS_POAnalTrans")
                    ->columns(array("POAnalTransId"))
                    ->where->expression('POProjTransId IN ?', array($delIPDAnalSQ2));
                $delIPDAnal = $sql->delete();
                $delIPDAnal->from('MMS_IPDAnalTrans')
                    ->where->expression('POAHTransId IN ?', array($delIPDAnalSQ1));
                $IPDAnalStatement = $sql->getSqlStringForSqlObject($delIPDAnal);
                $dbAdapter->query($IPDAnalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //IPDProjTrans
                $delIPDProjSQ2 = $sql->select();
                $delIPDProjSQ2->from("MMS_POTrans")
                    ->columns(array("POTransId"))
                    ->where(array("PORegisterId" => $poregid));
                $delIPDProjSQ1 = $sql->select();
                $delIPDProjSQ1->from("MMS_POProjTrans")
                    ->columns(array("POProjTransId"))
                    ->where->expression('POTransId IN ?', array($delIPDProjSQ2));
                $delIPDProj = $sql->delete();
                $delIPDProj->from('MMS_IPDProjTrans')
                    ->where->expression('POProjTransId IN ?', array($delIPDProjSQ1));
                $IPDTransStatement = $sql->getSqlStringForSqlObject($delIPDProj);
                $dbAdapter->query($IPDTransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //IPDTrans
                $delIPDTransSQ1 = $sql->select();
                $delIPDTransSQ1->from("MMS_POTrans")
                    ->columns(array("POTransId"))
                    ->where(array("PORegisterId" => $poregid));
                $delIPDTrans = $sql->delete();
                $delIPDTrans->from('MMS_IPDTrans')
                    ->where->expression('POTransId IN ?', array($delIPDTransSQ1));
                $delipdStatement = $sql->getSqlStringForSqlObject($delIPDTrans);
                $dbAdapter->query($delipdStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //POAnalTrans
                $delPOAnalSQ2 = $sql->select();
                $delPOAnalSQ2->from("MMS_POTrans")
                    ->columns(array("POTransId"))
                    ->where(array("PORegisterId" => $poregid));
                $delPOAnalSQ1 = $sql->select();
                $delPOAnalSQ1->from("MMS_POProjTrans")
                    ->columns(array("POProjTransId"))
                    ->where->expression('POTransId IN ?', array($delPOAnalSQ2));
                $delPOAnal = $sql->delete();
                $delPOAnal->from('MMS_POAnalTrans')
                    ->where->expression('POProjTransId IN ?', array($delPOAnalSQ1));
                $delpoanalStatement = $sql->getSqlStringForSqlObject($delPOAnal);
                $dbAdapter->query($delpoanalStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //POProjTrans
                $delPOProjSQ1 = $sql->select();
                $delPOProjSQ1->from("MMS_POTrans")
                    ->columns(array("POTransId"))
                    ->where(array("PORegisterId" => $poregid));
                $delPOProj = $sql->delete();
                $delPOProj->from('MMS_POProjTrans');
                $delPOProj->where->expression('POTransId IN ?', array($delPOProjSQ1));
                $delpoprojStatement = $sql->getSqlStringForSqlObject($delPOProj);
                $dbAdapter->query($delpoprojStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //POTrans
                $delPOTrans = $sql->delete();
                $delPOTrans->from('MMS_POTrans')
                    ->where(array("PORegisterId" => $poregid));
                $delpotransStatement = $sql->getSqlStringForSqlObject($delPOTrans);
                $dbAdapter->query($delpotransStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                //PORegister
                $registerUpdate = $sql->update()
                    ->table("MMS_PORegister")
                    ->set(array("DeleteFlag" => 1))
                    ->where(array("PORegisterId" => $poregid));
                $delporegStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                $dbAdapter->query($delporegStatement, $dbAdapter::QUERY_MODE_EXECUTE);


                $connection->commit();
                $this->redirect()->toRoute('mms/default', array('controller' => 'purchase','action' => 'display-register'));
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
    public function requestAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Purchase Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        $RCTranId = $this->params()->fromRoute('RcId');
        $Approve="";
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $CostCentreId = $this->bsf->isNullCheck($postParams['PId'], 'number');
                $selCCAddress = $sql->select();
                $selCCAddress->from(array("A" => "MMS_PORegister"))
                    ->columns(array(
                        "PORegisterId" => new Expression("Distinct A.PORegisterId"),
                        "PONo" => new Expression("A.PONo")
                    ))
                    ->join(array("B" => "MMS_POTrans"), "A.PORegisterId=B.PORegisterId", array(), $selCCAddress::JOIN_INNER)
                    ->join(array("CC" => "WF_OperationalCostCentre"), "A.CostCentreId= CC.CostCentreId", array(), $selCCAddress::JOIN_LEFT)
                    ->where( "A.Approve ='Y' And A.LivePO = 1 And A.ShortClose=0 And B.BalQty > 0 And  A.CostCentreId =$CostCentreId
                                    And A.PORegisterId NOT IN (Select distinct C.PORegisterId From MMS_IPDTrans A
                                   Inner Join MMS_POTrans B  On A.POTransId=B.POTransId
                                   Inner JOin MMS_PORegister C  On B.PORegisterId=C.PORegisterId
                                   Inner Join VM_ReqDecQtyTrans  D  On A.DecTransId=D.TransId And A.DecisionId=D.DecisionId
                                  Inner Join VM_RequestTrans E On D.ReqTransId=E.RequestTransId
                                   Where A.Status='P' And E.CancelQty>0)");
                $selAddStatement = $sql->getSqlStringForSqlObject($selCCAddress);
                $result = $dbAdapter->query($selAddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $response->setContent(json_encode($result));
                return $response;
            }
        }
        else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;
//                return;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try
                {
                    $CostCentreId = $this->bsf->isNullCheck($postParams['CostCentre'], 'number');
                    $CostCentre= $this->bsf->isNullCheck($postParams['CostCentreId'], 'number');
                    $PORegisterId = $this->bsf->isNullCheck($postParams['PoNo'], 'number');
                    $PO= $this->bsf->isNullCheck($postParams['PORegisterId'], 'number');
                    $RequestNo = $this->bsf->isNullCheck($postParams['RequestNo'], 'string');
                    $ccReqNo = $this->bsf->isNullCheck($postParams['ccReqNo'], 'string');
                    $cReqNo = $this->bsf->isNullCheck($postParams['cReqNo'], 'string');
                    $rDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['rDate'], 'string')));
                    $Remarks = $this->bsf->isNullCheck($postParams['Remarks'], 'string');
                    if($RCTranId > 0){
                        $registerUpdate=$sql->update()
                            ->table("mms_requestcancel")
                            ->set(array(
                                "RegNo" => $RequestNo,
                                "RegDate" => $rDate,
                                "Remarks" => $Remarks,
                                "CCReqNo" => $ccReqNo,
                                "CReqNo" => $cReqNo,
                            ))

                            ->where(array("RCTransId"=>$RCTranId));
                        $delporegStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                        $dbAdapter->query($delporegStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }else {
                        $setUpInsert = $sql->insert('mms_requestcancel');
                        $setUpInsert->values(array(
                            "PORegisterId" => $PORegisterId,
                            "RegNo" => $RequestNo,
                            "RegDate" => $rDate,
                            "Remarks" => $Remarks,
                            "CCReqNo" => $ccReqNo,
                            "CReqNo" => $cReqNo,
                        ));
                        $setUpStatement = $sql->getSqlStringForSqlObject($setUpInsert);
                        $dbAdapter->query($setUpStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        $RCTransId = $dbAdapter->getDriver()->getLastGeneratedValue();
                        $this->_view->RCTransId = $RCTransId;
                    }
                    $this->_view->RCTransId = $RCTransId;
                    $connection->commit();
                    $this->redirect()->toRoute('mms/default', array('controller' => 'purchase', 'action' => 'request-display'));
                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                if($RCTranId > 0 ){
                    $selCCAddress = $sql->select();
                    $selCCAddress->from(array("A" => "mms_requestcancel"))
                        ->columns(array(
                            "RegNo" => new Expression("A.RegNo"),
                            "RCTransId" => new Expression("A.RCTransId"),
                            "RegDate" => new Expression("Convert(Varchar(10),A.RegDate,103)"),
                            "Remarks" => new Expression("A.Remarks"),
                            "CCReqNo" => new Expression("A.CCReqNo"),
                            "CReqNo" => new Expression("A.CReqNo"),
                            "Approve" => new Expression(" CASE WHEN A.Approve='Y' THEN 'Yes'
																WHEN A.Approve='P' THEN 'Partial'
																Else 'No'
														END")
                        ))
                        ->join(array("B" => "MMS_PORegister"), "A.PORegisterId=B.PORegisterId", array("PONo","PORegisterId"), $selCCAddress::JOIN_LEFT)
                        ->join(array("CC" => "WF_OperationalCostCentre"), "B.CostCentreId= CC.CostCentreId", array("CostCentreName","CostCentreId"), $selCCAddress::JOIN_LEFT)
                        ->where(array("RCTransId=$RCTranId"));
                    $statement = $sql->getSqlStringForSqlObject($selCCAddress);
                    $Result = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->arr_ReqPo = $Result;
                    $this->_view->Approve = $Result['Approve'];
                    $this->_view->RCTranId =$RCTranId;
                }else {
                    $select = $sql->select();
                    $select->from(array('a' => 'WF_OperationalCostCentre'))
                        ->columns(array('CostCentreId', 'CostCentreName'))
                        ->where('Deactivate=0');
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->arr_costcenter = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    $selPO = $sql->select();
                    $selPO->from(array("A" => "MMS_PORegister"))
                        ->columns(array(
                            "PORegisterId" => new Expression("Distinct A.PORegisterId"),
                            "PONo" => new Expression("A.PONo")
                        ))
                        ->join(array("B" => "MMS_POTrans"), "A.PORegisterId=B.PORegisterId", array(), $selPO::JOIN_INNER)
                        ->join(array("CC" => "WF_OperationalCostCentre"), "A.CostCentreId= CC.CostCentreId", array(), $selPO::JOIN_LEFT)
                        ->where( "A.Approve ='Y' And A.LivePO = 1 And A.ShortClose=0 And B.BalQty > 0
                                    And A.PORegisterId NOT IN (Select distinct C.PORegisterId From MMS_IPDTrans A
                                   Inner Join MMS_POTrans B  On A.POTransId=B.POTransId
                                   Inner JOin MMS_PORegister C  On B.PORegisterId=C.PORegisterId
                                   Inner Join VM_ReqDecQtyTrans  D  On A.DecTransId=D.TransId And A.DecisionId=D.DecisionId
                                  Inner Join VM_RequestTrans E On D.ReqTransId=E.RequestTransId
                                   Where A.Status='P' And E.CancelQty>0)");
                    $statement = $sql->getSqlStringForSqlObject($selPO);
                    $this->_view->arr_po = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->RCTranId =$RCTranId;
                $this->_view->Approve =$Approve;
                return $this->_view;
            }
        }
    }
    public function requestDisplayAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "index","action" => "index"));
            }
        }
        $viewRenderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $dbAdapter = $this->serviceLocator->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($request->isXmlHttpRequest()){
            if ($request->isPost()){
                $resp = array();
                //Write your Ajax post code here

                $this->_view->setTerminal(true);
                $response->setContent(json_encode($resp));
                return $response;
            }
        }

        $selCCAddress = $sql->select();
        $selCCAddress->from(array("A" => "mms_requestcancel"))
            ->columns(array(
                "RegNo" => new Expression("A.RegNo"),
                "RCTransId" => new Expression("A.RCTransId"),
                "RegDate" => new Expression("A.RegDate"),
                "Remarks" => new Expression("A.Remarks"),
                "Approve" => new Expression(" CASE WHEN A.Approve='Y' THEN 'Yes'
																WHEN A.Approve='P' THEN 'Partial'
																Else 'No'
														END")
            ))
            ->join(array("B" => "MMS_PORegister"), "A.PORegisterId=B.PORegisterId", array("PONo"), $selCCAddress::JOIN_LEFT)
            ->join(array("CC" => "WF_OperationalCostCentre"), "B.CostCentreId= CC.CostCentreId", array("CostCentreName"), $selCCAddress::JOIN_LEFT);
        $selCCAddress->order(new Expression("a.RCTransId DESC"));
        $statement = $sql->getSqlStringForSqlObject($selCCAddress);
        $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $this->_view->gridResult = $gridResult;
        //Common function
        $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
        return $this->_view;
    }
    public function requestPoAction() {
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set(" Purchase Order");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        $request = $this->getRequest();
        $response = $this->getResponse();
        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
                $RCTransId = $this->bsf->isNullCheck($postParams['PId'], 'number');
                $selCCAddress = $sql->select();
                $selCCAddress->from(array("A" => "mms_requestcancel"))
                    ->columns(array(
                        "PORegisterId" => new Expression("Distinct A.PORegisterId"),
                        "PONo" => new Expression("B.PONo")
                    ))
                    ->join(array("B" => "MMS_PORegister"), "A.PORegisterId=B.PORegisterId", array(), $selCCAddress::JOIN_LEFT)
                    ->where(array("RCTransId = $RCTransId"));
                $selAddStatement = $sql->getSqlStringForSqlObject($selCCAddress);
                $result = $dbAdapter->query($selAddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->setTerminal(true);
                $response->setContent(json_encode($result));
                return $response;
            }
        }
        else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $postParams = $request->getPost();
//                echo"<pre>";
//                print_r($postParams);
//                echo"</pre>";
//                die;
//                return;

                $connection = $dbAdapter->getDriver()->getConnection();
                $connection->beginTransaction();
                try
                {
                    $RCTransId = $this->bsf->isNullCheck($postParams['RCTransId'], 'number');
                    $PORegisterId = $this->bsf->isNullCheck($postParams['PORegisterId'], 'number');
                    $cDate=date('Y-m-d', strtotime($this->bsf->isNullCheck($postParams['cDate'], 'string')));
                    $cRemarks = $this->bsf->isNullCheck($postParams['cRemarks'], 'number');
                    $registerUpdate=$sql->update()
                        ->table("mms_requestcancel")
                        ->set(array(
                            "Cancel" => 1,
                        ))
                        ->where(array("RCTransId"=>$RCTransId));
                    $delporegStatement = $sql->getSqlStringForSqlObject($registerUpdate);
                    $dbAdapter->query($delporegStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from(array('A' => 'MMS_POTrans'))
                        ->columns(array('POTransId', 'BalQty'))
                        ->where(array("A.BalQty > 0 And PORegisterId=$PORegisterId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $POTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    foreach($POTrans as $POTran) {
                        $POTransIds=$POTran['POTransId'];
                        $DBalQtys=$POTran['BalQty'];
                        $select = $sql->select();
                        $select->from(array('A' => 'MMS_IPDTrans'))
                            ->columns(array('IPDTransId', 'DecTransId','Qty'))
                            ->join(array("B" => "MMS_POTrans"), "A.POTransId=B.POTransId", array(), $select::JOIN_INNER)
                            ->where(array("B.BalQty > 0 And Status= 'p' And A.Qty > 0 And A.POTransId = $POTransIds"));
                        $statement = $sql->getSqlStringForSqlObject($select);
                        $Trans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(count($Trans) > 0){
                            $DecTransIds = 0;
                            $IPDTransIds = 0;
                            $dQty = 0;
                            $dCQty = 0;
                            $dBCQty = $DBalQtys;
                            foreach($Trans as $Tran){
                                $IPDTransIds=$Tran['IPDTransId'];
                                $DecTransIds=$Tran['DecTransId'];
                                $dQty=$Tran['Qty'];
                                if ($dQty <= $dBCQty)
                                {
                                    $dCQty = $dQty;
                                    $dBCQty = $dBCQty - $dCQty;
                                }
                                else
                                {
                                    $dCQty = $dBCQty;
                                    $dBCQty = 0;
                                }
                                if ($dCQty > 0)
                                {
                                    $Update=$sql->update()
                                        ->table("vm_ReqDecQtyTrans ")
                                        ->set(array(
                                            "IndentQty" =>  new Expression('IndentQty+'.$dCQty)
                                        ))
                                        ->where(array("TransId"=>$DecTransIds));
                                    $Statement = $sql->getSqlStringForSqlObject($Update);
                                    $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                    $Update=$sql->update()
                                        ->table("mms_IPDTrans")
                                        ->set(array(
                                            "CancelQty" =>  new Expression('CancelQty+'.$dCQty)
                                        ))
                                        ->where(array("IPDTransId"=>$IPDTransIds));
                                    $Statement = $sql->getSqlStringForSqlObject($Update);
                                    $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                }

                                $select = $sql->select();
                                $select->from(array('A' => 'MMS_IPDAnalTrans'))
                                    ->columns(array('IPDAHTransId', 'DecATransId','DecTransId',
                                        'Qty'=>new Expression("Case When A.Qty>B.BalQty Then B.BalQty Else A.Qty End")))
                                    ->join(array("IP" => "MMS_IPDProjTrans"), " A.IPDProjTransId=IP.IPDProjTransId", array(), $select::JOIN_INNER)
                                    ->join(array("B" => "MMS_POAnalTrans"), "A.POAHTransId=B.POAnalTransId And A.AnalysisId=B.AnalysisId", array(), $select::JOIN_INNER)
                                    ->join(array("C" => "MMS_POProjTrans"), "B.POProjTransId=C.PoProjTransId", array(), $select::JOIN_INNER)
                                    ->join(array("D" => "MMS_POTrans"), "C.POTransId=D.PoTransId ", array(), $select::JOIN_INNER)
                                    ->join(array("E" => "MMS_PORegister"), "D.PORegisterId=E.PORegisterId", array(), $select::JOIN_INNER)
                                    ->where(array("E.PORegisterId=$PORegisterId And B.BalQty>0 And E.Approve='Y' And A.Status='P' And
                                               IP.DecTransId=$DecTransIds"));
                                $statement = $sql->getSqlStringForSqlObject($select);
                                $IpdTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                                if(count($IpdTrans) > 0){
                                    $IPDAHTransId = 0;
                                    $DecATransId = 0;
                                    $dIABalQty = 0;
                                    $dCABQty = $dCQty;
                                    $dCACQty = 0;
                                    foreach($IpdTrans as $IpdTran){
                                        if ($dCABQty > 0)
                                        {
                                            $IPDAHTransIds=$IpdTran['IPDAHTransId'];
                                            $DecATransIds=$IpdTran['DecATransId'];
                                            $dIABalQty =$IpdTran['Qty'];
                                            if ($dIABalQty <= $dCABQty)
                                            {
                                                $dCACQty = $dIABalQty;
                                                $dCABQty = $dCABQty - $dCACQty;
                                            }
                                            else
                                            {
                                                $dCACQty = $dCABQty;
                                                $dCABQty = 0;
                                            }
                                            if ($dCACQty > 0)
                                            {
                                                $Update=$sql->update()
                                                    ->table("vm_reqDecQtyAnalTrans ")
                                                    ->set(array(
                                                        "IndentQty" =>  new Expression('IndentQty+'.$dCACQty)
                                                    ))
                                                    ->where(array("RCATransId"=>$DecATransIds));
                                                $Statement = $sql->getSqlStringForSqlObject($Update);
                                                $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                                                $Update=$sql->update()
                                                    ->table("mms_IPDAnalTrans")
                                                    ->set(array(
                                                        "CancelQty" =>  new Expression('CancelQty+'.$dCACQty)
                                                    ))
                                                    ->where(array("IPDAHTransId"=>$IPDAHTransIds));
                                                $Statement = $sql->getSqlStringForSqlObject($Update);
                                                $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                                            }
                                        }
                                    }
                                }

                            }
                        }
                    }
                    $select = $sql->select();
                    $select->from(array('A' => 'mms_POTrans'))
                        ->columns(array('BalQty', 'ResourceId','ItemId',
                            'Amount'=>new Expression("(A.BalQty*A.QRate)"),
                            'GrossAmount'=>new Expression("(A.BalQty*A.GrossRate)")
                        ))
                        ->join(array("B" => "mms_PORegister"), "A.PORegisterId=B.PORegisterId ", array("CostCentreId"), $select::JOIN_INNER)
                        ->where(array("A.PORegisterId=$PORegisterId"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $qtyTrans= $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($qtyTrans) > 0){
                        $d_BalQty = 0;
                        $i_CCId = 0;
                        $d_QAmount = 0;
                        $d_GAmount = 0;
                        $iResId = 0;
                        $iItemId = 0;
                        foreach($qtyTrans as $qtyTran){
                            $d_BalQty=$qtyTran['BalQty'];
                            $i_CCId=$qtyTran['CostCentreId'];
                            $d_QAmount=$qtyTran['Amount'];
                            $d_GAmount=$qtyTran['GrossAmount'];
                            $iResId=$qtyTran['ResourceId'];
                            $iItemId=$qtyTran['ItemId'];
                            $Update=$sql->update()
                                ->table("mms_stock ")
                                ->set(array(
                                    "POQty" =>  new Expression('POQty-'.$d_BalQty),
                                    "POAmount" =>  new Expression('POAmount-'.$d_QAmount),
                                    "POGAmount" =>  new Expression('POGAmount-'.$d_GAmount)
                                ))
                                ->where(array("ResourceId=$iResId And ItemId=$iItemId And CostCentreId=$i_CCId"));
                            $Statement = $sql->getSqlStringForSqlObject($Update);
                            $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }
                    }
                    $Update=$sql->update()
                        ->table("mms_potrans")
                        ->set(array(
                            "CancelQty" =>  new Expression('BalQty')
                        ))
                        ->where(array("PORegisterId"=>$PORegisterId));
                    $Statement = $sql->getSqlStringForSqlObject($Update);
                    $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $Update=$sql->update()
                        ->table("mms_poprojtrans")
                        ->set(array(
                            "CancelQty" =>  new Expression('BalQty')
                        ))
                        ->where(array("POTransId in (Select POTransId From mms_POTrans Where PORegisterId=$PORegisterId)"));
                    $Statement = $sql->getSqlStringForSqlObject($Update);
                    $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $Update=$sql->update()
                        ->table("mms_poAnaltrans")
                        ->set(array(
                            "CancelQty" =>  new Expression('BalQty')
                        ))
                        ->where(array("POProjTransId  in (Select POProjTransId From mms_POProjTrans Where
                         POTransId IN (Select POTransId From mms_POTrans Where PORegisterId=$PORegisterId))"));
                    $Statement = $sql->getSqlStringForSqlObject($Update);
                    $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $connection->commit();
                    $this->redirect()->toRoute('mms/default', array('controller' => 'purchase', 'action' => 'request-po'));
                }
                catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }

            } else {
                $select = $sql->select();
                $select->from(array('a' => 'mms_requestcancel'))
                    ->columns(array('RCTransId', 'RegNo'))
                    ->where(array("A.Approve ='Y'"));
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->arr_RegNo = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $selCCAddress = $sql->select();
                $selCCAddress->from(array("A" => "mms_requestcancel"))
                    ->columns(array(
                        "RegNo" => new Expression("A.RegNo"),
                        "RCTransId" => new Expression("A.RCTransId"),
                        "RegDate" => new Expression("A.RegDate"),
                        "Remarks" => new Expression("A.Remarks"),
                        "Approve" => new Expression(" CASE WHEN A.Approve='Y' THEN 'Yes'
																WHEN A.Approve='P' THEN 'Partial'
																Else 'No'
														END")
                    ))
                    ->join(array("B" => "MMS_PORegister"), "A.PORegisterId=B.PORegisterId", array("PONo"), $selCCAddress::JOIN_LEFT)
                    ->join(array("CC" => "WF_OperationalCostCentre"), "B.CostCentreId= CC.CostCentreId", array("CostCentreName"), $selCCAddress::JOIN_LEFT);
                $statement = $sql->getSqlStringForSqlObject($selCCAddress);
                $gridResult = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $this->_view->gridResult = $gridResult;
                return $this->_view;
            }
        }
    }


    public function purchaseshortCloseAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "purchase","action" => "purchaseshort-close"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql ($dbAdapter);
        $poregid = $this->bsf->isNullCheck($this->params()->fromRoute('rid'), 'number');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParams = $request->getPost();
                $PORegisterId = $this->bsf->isNullCheck($postParams['PORegisterId'], 'number');


                $select = $sql->select();
                $select->from(array("A" => "MMS_POTrans"))
                    ->columns(array(new Expression("Case When A.ItemId>0 Then D.ItemCode Else C.Code End Code,Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End Resource,A.PoTransId,B.PORegisterId,CAST(A.POQty As Decimal(18,6)) POQty,CAST(A.AcceptQty As Decimal(18,6)) DCQty,CAST(A.BillQty As Decimal(18,6)) BillQty,CAST(A.CancelQty As Decimal(18,6)) CancelQty,CAST(A.BalQty As Decimal(18,6)) BalQty,CONVERT(bit,0,0) As Include")))
                    ->join(array('B' => 'MMS_PORegister'), 'A.PORegisterId=B.PORegisterId', array(), $select::JOIN_INNER)
                    ->join(array('C' => 'Proj_Resource'), 'A.ResourceId=C.ResourceID', array(), $select::JOIN_INNER)
                    ->join(array('D' => 'MMS_Brand'), 'A.ResourceId=D.ResourceId And A.ItemId=D.BrandId', array(), $select::JOIN_LEFT)
                    ->where("A.PORegisterId= $PORegisterId And B.ShortClose=0 And A.BalQty>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('requests' => $requests)));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }else{
                $postData = $request->getPost();
                if (isset($poregid) && $poregid != '') {

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_PORegister'))
                        ->columns(array(new Expression("a.PORegisterId as POId,a.PONo as PONo")))
                        ->where(array("a.PORegisterId=$poregid"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $poid = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->selNo = $poid['PONo'];
                    $this->_view->selpoId = $poid['POId'];

                    $select = $sql->select();
                    $select->from(array('a' => 'MMS_POShortCloseReg'))
                        ->columns(array('*'))
                        ->where(array("a.PORegisterId=$poregid"));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $value = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $this->_view->remarks = $value['Remarks'];


                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POTrans"))
                        ->columns(array(new Expression("Case When A.ItemId>0 Then D.ItemCode Else C.Code End Code,
							Case When A.ItemId>0 Then D.BrandName Else C.ResourceName End Resource,
							A.PoTransId,B.PORegisterId,CAST(A.POQty As Decimal(18,6)) POQty,a.ShortClose,
							CAST(A.BillQty As Decimal(18,6)) BillQty,CAST(A.BalQty As Decimal(18,6))BalQty,CAST(A.CancelQty As Decimal(18,6)) CancelQty,CAST(A.AcceptQty As Decimal(18,6)) DCQty,
							a.ShortClose As Include")))
                        ->join(array('b' => 'MMS_PORegister'), 'a.PORegisterId=b.PORegisterId', array(), $select::JOIN_INNER)
                        ->join(array('c' => 'Proj_Resource'), 'a.ResourceId=c.ResourceID', array(), $select::JOIN_INNER)
                        ->join(array('d' => 'MMS_Brand'), 'a.ResourceId=d.ResourceId And a.ItemId=d.BrandId', array(), $select::JOIN_LEFT)
                        ->where("a.PORegisterId= $poregid ");
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $this->_view->requests = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }

            }
            // getting the  cost centres
            $select = $sql->select();
            $select->from(array('A' => 'MMS_PORegister'))
                ->columns(array(new Expression("distinct(A.PORegisterId) as PORegisterId,A.PONo as PONo")))
                ->join(array("B" => "MMS_POTrans"), "A.PORegisterId=B.PORegisterId ",array(),$select::JOIN_INNER)
                ->join(array("C" => "MMS_POProjTrans"), "B.POTransId=C.POTransId  ",array(),$select::JOIN_INNER)
                ->where(array("B.BalQty>0 And A.ShortClose=0 And A.Approve='Y' Order By A.PORegisterId"));
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_purchaseno = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
            $this->_view->PORegisterId = $poregid;
            return $this->_view;
        }
    }

    public function purchaseshortcloseSaveAction(){
        if (!$this->auth->hasIdentity()) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                echo "session-expired";
                exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "purchase", "action" => "purchaseshort-close"));
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
                $postParams = $request->getPost();
                // echo"<pre>";
                // print_r($postParams);
                // echo"</pre>";
                // die;
                // return;

                $Approve = "";
                $Role = "";
                $poId = $this->bsf->isNullCheck($postParams['PONo'], 'number');
                $poNo = $this->bsf->isNullCheck($postParams['selpoId'], 'number');
                $remarks = $this->bsf->isNullCheck($postParams['remarks'], 'string');

                $POTransIds = implode(',', $postParams['POTransIds']);
                if($POTransIds == ""){
                    $POTransIds =0;
                }

                if ($this->bsf->isNullCheck($poId, 'number') > 0) {
                    $Approve = "E";
                    $Role = "PO-Short-Close-Modify";
                } else {
                    $Approve = "N";
                    $Role = "PO-Short-Close-Create";
                }

                if($poId>0){
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PORegister"))
                        ->columns(array("PONo","CostCentreId"))
                        ->where(array('PORegisterId' => $poId));
                    $Statement = $sql->getSqlStringForSqlObject($select);
                    $poreg = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
                }else{
                    $select = $sql->select();
                    $select->from(array("a" => "MMS_PORegister"))
                        ->columns(array("PONo","CostCentreId"))
                        ->where(array('PORegisterId' => $poNo));
                    $Statement = $sql->getSqlStringForSqlObject($select);
                    $poreg = $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE)->Current();
                }

                $CostCentreId=$poreg ['CostCentreId'];
                $PONo=$poreg ['PONo'];

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
                    if($poId > 0){

                        if(count($POTransIds)> 0){

                            $updPoreg = $sql->update();
                            $updPoreg->table('MMS_PORegister');
                            $updPoreg->set(array(
                                'ShortClose' => 1,
                            ));
                            $updPoreg->where(array('PORegisterId' => $poId));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updPoreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }else{
                            $updPoreg = $sql->update();
                            $updPoreg->table('MMS_PORegister');
                            $updPoreg->set(array(
                                'ShortClose' => 0,
                            ));
                            $updPoreg->where(array('PORegisterId' => $poId));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updPoreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $del = $sql->delete();
                        $del->from('MMS_POShortCloseReg')
                            ->where(array('PORegisterId' => $poId));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $Insert = $sql->insert('MMS_POShortCloseReg');
                        $Insert->values(array(
                            "PORegisterId" => $poId,
                            "Remarks" => $remarks,
                        ));
                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $updatepo = $sql->update();
                        $updatepo->table('MMS_POTrans');
                        $updatepo->set(array(
                            'ShortClose' => 1,
                        ));
                        $updatepo->where(array("POTransId IN($POTransIds)"));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatepo);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    }else{
                        $updPoreg = $sql->update();
                        $updPoreg->table('MMS_PORegister');
                        $updPoreg->set(array(
                            'ShortClose' => 0,
                        ));
                        $updPoreg->where(array('PORegisterId' => $poNo));
                        $updPoregStatement = $sql->getSqlStringForSqlObject($updPoreg);
                        $dbAdapter->query($updPoregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $del = $sql->delete();
                        $del->from('MMS_POShortCloseReg')
                            ->where(array('PORegisterId' => $poNo));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $updatepo = $sql->update();
                        $updatepo->table('MMS_POTrans');
                        $updatepo->set(array(
                            'ShortClose' => 0,
                        ));
                        $updatepo->where(array('PORegisterId' => $poNo));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatepo);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        //update edit mode purchaseshortclose

                        if(count($POTransIds)> 0){

                            $updPoreg = $sql->update();
                            $updPoreg->table('MMS_PORegister');
                            $updPoreg->set(array(
                                'ShortClose' => 1,
                            ));
                            $updPoreg->where(array('PORegisterId' => $poNo));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updPoreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }else{
                            $updPoreg = $sql->update();
                            $updPoreg->table('MMS_PORegister');
                            $updPoreg->set(array(
                                'ShortClose' => 0,
                            ));
                            $updPoreg->where(array('PORegisterId' => $poNo));
                            $updDcregStatement = $sql->getSqlStringForSqlObject($updPoreg);
                            $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                        }

                        $del = $sql->delete();
                        $del->from('MMS_POShortCloseReg')
                            ->where(array('PORegisterId' => $poNo));
                        $statement = $sql->getSqlStringForSqlObject($del);
                        $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $Insert = $sql->insert('MMS_POShortCloseReg');
                        $Insert->values(array(
                            "PORegisterId" => $poNo,
                            "Remarks" => $remarks,
                        ));
                        $Statement = $sql->getSqlStringForSqlObject($Insert);
                        $dbAdapter->query($Statement, $dbAdapter::QUERY_MODE_EXECUTE);

                        $updatepo = $sql->update();
                        $updatepo->table('MMS_POTrans');
                        $updatepo->set(array(
                            'ShortClose' => 1,
                        ));
                        $updatepo->where(array("POTransId IN($POTransIds)"));
                        $updatedcStatement = $sql->getSqlStringForSqlObject($updatepo);
                        $dbAdapter->query($updatedcStatement, $dbAdapter::QUERY_MODE_EXECUTE);
                    }
                    $connection->commit();
                    CommonHelper::insertLog(date('Y-m-d H:i:s'),$Role,$Approve,'PO-Short-Close',$poId,$CostCentreId,$CompanyId, 'MMS',$PONo,$this->auth->getIdentity()->UserId,0,0,0);
                }catch (PDOException $e) {
                    $connection->rollback();
                    print "Error!: " . $e->getMessage() . "</br>";
                }
                //begin trans try block example ends

                //Common function
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false);
                $this->redirect()->toRoute('mms/default', array('controller' => 'purchase','action' => 'purchaseshortclose-register'));
                return $this->_view;
            }
        }
    }

    public function purchaseshortcloseRegisterAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "purchase","action" => "purchaseshort-close"));
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
                    $regSelect->from(array("a" => "MMS_POShortCloseReg"))
                        ->columns(array(new Expression("b.PORegisterId,b.PONo As PONo,
                        Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                        ->join(array("b" => "MMS_PORegister"), "a.PORegisterId=b.PORegisterId", array(), $regSelect::JOIN_LEFT)
                        ->Order("PODate Desc")
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

    public function purchaseshortcloseDeleteAction(){
        if(!$this->auth->hasIdentity()) {
            if($this->getRequest()->isXmlHttpRequest())	{
                echo "session-expired"; exit();
            } else {
                $this->redirect()->toRoute("application/default", array("controller" => "purchase","action" => "purchaseshortclose-register"));
            }
        }
        //$this->layout("layout/layout");
        $this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
        $viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
        $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);
        //$Id = $this->bsf->isNullCheck($this->params()->fromRoute('rid'), 'number');

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $status = "failed";
                $dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
                $connection = $dbAdapter->getDriver()->getConnection();
                try {
                    $PORegisterId = $this->bsf->isNullCheck($this->params()->fromPost('PORegisterId'), 'number');
                    $sql = new Sql($dbAdapter);
                    $response = $this->getResponse();
                    $connection->beginTransaction();

                    // over all delete
                    $updDcreg = $sql->update();
                    $updDcreg->table('MMS_PORegister');
                    $updDcreg->set(array(
                        'ShortClose' => 0,
                    ));
                    $updDcreg->where(array('PORegisterId' => $PORegisterId));
                    $updDcregStatement = $sql->getSqlStringForSqlObject($updDcreg);
                    $dbAdapter->query($updDcregStatement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $del = $sql->delete();
                    $del->from('MMS_POShortCloseReg')
                        ->where(array('PORegisterId' => $PORegisterId));
                    $statement = $sql->getSqlStringForSqlObject($del);
                    $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE);

                    $select = $sql->select();
                    $select->from(array("a" => "MMS_POTrans"))
                        ->columns(array("POTransId"))
                        ->join(array("b"=>"MMS_PORegister"), "a.PORegisterId=b.PORegisterId", array(), $select::JOIN_INNER)
                        ->where(array("a.PORegisterId" => $PORegisterId));
                    $statement = $sql->getSqlStringForSqlObject($select);
                    $prev = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                    foreach ($prev as $arrprev) {

                        $updatedc = $sql->update();
                        $updatedc->table('MMS_POTrans');
                        $updatedc->set(array(
                            'ShortClose' => 0,
                        ));
                        $updatedc->where(array('POTransId' => $arrprev['POTransId']));
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
    public function reportAction(){
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
            $this->redirect()->toRoute("purchase/display-register", array("controller" => "purchase","action" => "display-register"));
        }

        $dir = 'public/po/header/'. $subscriberId;
        $filePath = $dir.'/v1_template.phtml';

        $dirfooter = 'public/po/footer/'. $subscriberId;
        $filePath1 = $dirfooter.'/v1_template.phtml';

        $ReqId = $this->bsf->isNullCheck( $this->params()->fromRoute( 'id' ), 'number' );
        if($ReqId == 0)

            $this->redirect()->toRoute("purchase/display-register", array("controller" => "purchase","action" => "display-register"));

        if (!file_exists($filePath)) {
            $filePath = 'public/po/header/template.phtml';
        }
        if (!file_exists($filePath1)) {
            $filePath1 = 'public/po/footer/footertemplate.phtml';
        }

        $template = file_get_contents($filePath);
        $this->_view->template = $template;

        $footertemplate = file_get_contents($filePath1);
        $this->_view->footertemplate = $footertemplate;

        $regSelect = $sql->select();
        $regSelect->from(array("a"=>"MMS_PoRegister"))
            ->columns(array(new Expression("a.PoRegisterId,Convert(Varchar(10),a.PODate,103) As PoDate,a.PoNo,
					 a.CCPONo as ccpono,a.CPONo as cpono,a.Amount As NetAmount, a.ReqNo as reqno,
					 Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve,c.CompanyName,c.Address as companyAddress,c.LogoPath,a.PoDelAdd as Deliveryaddress,a.Narration")))
            ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.CostCentreId", array(), $regSelect::JOIN_INNER)
            ->join(array("c" => "WF_CompanyMaster"), "b.CompanyId=c.CompanyId", array(), $regSelect::JOIN_INNER)
            ->where("a.DeleteFlag=0  and a.PoRegisterId=$ReqId")
            ->Order("a.PODate Desc");
        $regStatement = $sql->getSqlStringForSqlObject($regSelect);
        $this->_view->reqregister = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

        $selWareHouse = $sql->select();
        $selWareHouse->from(array("a" => "MMS_WareHouseDetails"))
            ->columns(array("data" => new Expression("a.TransId"), "value" => new Expression("b.WareHouseName +' - ' + a.Description")))
            ->join(array("b" => "MMS_WareHouse"), "a.Warehouseid=b.Warehouseid", array(), $selWareHouse::JOIN_INNER)
            ->join(array("c" => "MMS_CCWareHouse"), "b.WareHouseId=c.WareHouseId", array(), $selWareHouse::JOIN_INNER)
            ->join(array("d" => "MMS_PoRegister"), "a.TransId=d.PoDelId", array(), $selWareHouse::JOIN_INNER)
            ->where('d.PoRegisterId=' . $ReqId . ' and a.LastLevel=1');
        $selWhStatement = $sql->getSqlStringForSqlObject($selWareHouse);
        $warehouse = $dbAdapter->query($selWhStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->warehouse = $warehouse['value'];


        $selCCAddress = $sql->select();
        $selCCAddress->from(array("a" => "WF_CostCentre"))
            ->columns(array(
                "costcentreAddress" => new Expression("(a.Address+CHAR(13)+','+c.CityName+CHAR(9)+','+d.StateName+CHAR(13)+','+e.CountryName+CHAR(13)+'-'+a.Pincode)"),
                "CostCentreName" => new Expression("a.CostCentreName")
            ))
            ->join(array("b" => "WF_OperationalCostCentre"), "a.CostCentreId=b.FACostCentreId", array(), $selCCAddress::JOIN_INNER)
            ->join(array("c" => "WF_CityMaster"), "a.CityId=c.CityId", array(), $selCCAddress::JOIN_LEFT)
            ->join(array("d" => "WF_StateMaster"), "c.StateId=d.StateId", array(), $selCCAddress::JOIN_LEFT)
            ->join(array("e" => "WF_CountryMaster"), "d.CountryId=e.CountryId", array(), $selCCAddress::JOIN_LEFT)
            ->join(array("f" => "MMS_PoRegister"), "b.CostCentreId=f.CostCentreId", array(), $selWareHouse::JOIN_INNER)
            ->where('f.PoRegisterId=' . $ReqId . '');
        $selAddStatement = $sql->getSqlStringForSqlObject($selCCAddress);
        $ccaddress = $dbAdapter->query($selAddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->ccaddress = $ccaddress;

        $sel = $sql->select();
        $sel->from(array("a" => "Vendor_Master"))
            ->columns(array(
                "vendorAddress" => new Expression("(a.RegAddress+CHAR(13)+','+c.CityName+CHAR(9)+','+d.StateName+CHAR(13)+','+e.CountryName+CHAR(13)+'-'+a.Pincode)"),
                "VendorName"=>new Expression("a.VendorName"),
                "PANNo"=>new Expression("x.PANNo"),
                "TANNo"=>new Expression("x.TANNo"),
                "CSTNo"=>new Expression("x.CSTNo"),
                "TINNo"=>new Expression("x.TINNo"),
                "PhoneNumber"=>new Expression("a.PhoneNumber")
            ))
            ->join(array("c" => "WF_CityMaster"), "a.CityId=c.CityId", array(), $sel::JOIN_LEFT)
            ->join(array("d" => "WF_StateMaster"), "c.StateId=d.StateId", array(), $sel::JOIN_LEFT)
            ->join(array("e" => "WF_CountryMaster"), "d.CountryId=e.CountryId", array(), $sel::JOIN_LEFT)
            ->join(array("f" => "MMS_PoRegister"), "a.VendorId=f.VendorId", array(), $sel::JOIN_INNER)
            ->join(array("x"=>"Vendor_Statutory"), "a.VendorId=x.VendorID", array(), $regSelect::JOIN_INNER)
            ->where('f.PoRegisterId=' . $ReqId . '');
        $selAddStatement = $sql->getSqlStringForSqlObject($sel);
        $vaddress = $dbAdapter->query($selAddStatement, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $this->_view->vaddress = $vaddress;

        $rSelect = $sql->select();
        $rSelect -> from(array("a" => "MMS_PoRegister"))
            ->columns(array(new Expression("t.VendorName as distName")))
            ->join(array("s"=>"MMS_PODistributorTrans"), "a.PoRegisterId=s.PORegisterId", array(), $regSelect::JOIN_LEFT)
            ->join(array("t"=>"Vendor_Master"), "s.vendorId=t.vendorId", array(), $regSelect::JOIN_LEFT)
            ->where("a.PoRegisterId=$ReqId");
        $regStatement = $sql->getSqlStringForSqlObject($rSelect);
        $reqDists = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $dname = array();
        foreach ($reqDists as $rDist) {
            array_push($dname, $rDist['distName']);
        }
        $this->_view->reqDist = implode(",", $dname);


        $resourceSelect = $sql->select();
        $resourceSelect->from(array("a"=>"MMS_POTrans"))
            ->columns(array(new Expression("(ROW_NUMBER() OVER(PARTITION by A.PORegisterId Order by A.PORegisterId asc)) as SNo,a.PoTransId,
			a.QRate AS QRate,a.QAmount,a.PORegisterId,a.ResourceId,a.ItemId,
			Case When a.ItemId>0 Then f.ItemCode Else d.Code End As Code,
			CAST(a.POQty as Decimal(18,5)) As POQty,
			Case When a.ItemId>0 Then f.BrandName Else d.ResourceName End As ResourceName,
			a.UnitId,e.UnitName,a.Rate As Rate,a.Amount As Amount,(Select Count(PoTransId) From MMS_POQualTrans Where PoTransId = a.PoTransId) as QCount")))
            ->join(array("d"=>"Proj_Resource"), "a.ResourceId=d.ResourceId", array("Code","UnitId"), $resourceSelect::JOIN_INNER)
            ->join(array("e"=>"Proj_UOM"), "a.UnitId=e.UnitId", array("UnitName"), $resourceSelect::JOIN_LEFT)
            ->join(array("f"=>"MMS_Brand"),"a.ItemId=f.BrandId And a.ResourceId=f.ResourceId",array(),$resourceSelect::JOIN_LEFT)
            ->join(array("g"=>"MMS_PORegister"),"a.PORegisterId=g.PORegisterId",array('NetAmount'),$resourceSelect::JOIN_INNER)
            ->where(array('a.PORegisterId'=>$ReqId));
        $resourceStatement = $sql->getSqlStringForSqlObject($resourceSelect);
        $this->_view->register = $dbAdapter->query($resourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $select = $sql->select();
        $select->from(array("c" => "MMS_POQualTrans"))
            ->columns(array(
                'PoTransId'=>new Expression('c.PoTransId'),
                'PORegisterId'=>new Expression('c.PORegisterId'),
                'Expression'=>new Expression('c.Expression'),
                'ExpPer'=>new Expression('CAST(c.ExpPer As Decimal(18,3))'),
                'Sign'=>new Expression('c.Sign'),
                'QualifierName'=>new Expression("(b.QualifierName+CHAR(13)+'-'+Convert(Varchar,c.ExpPer)+'%')"),
                'NetAmt'=>new Expression('CAST(c.NetAmt As Decimal(18,3))')))
            ->join(array("b" => "Proj_QualifierMaster"), "c.QualifierId=b.QualifierId", array(), $select::JOIN_INNER)
            //->join(array("d" => "MMS_POTrans"), "c.POTransId=d.PoTransId", array(), $select::JOIN_INNER)
            ->where(array('c.PORegisterId'=>$ReqId));
        $regStatement = $sql->getSqlStringForSqlObject($select);
        $this->_view->register1 = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();


        $resourceSelect = $sql->select();
        $resourceSelect->from(array("a"=>"MMS_POPaymentTerms"))
            ->columns(array(new Expression("(ROW_NUMBER() OVER(PARTITION by A.PORegisterId Order by A.PORegisterId asc)) as SNo,a.TermsId,a.Per,a.Value,a.Period,a.TString")))
            ->join(array("b"=>"WF_TermsMaster"), "a.TermsId=b.TermsId", array("Title"), $resourceSelect::JOIN_INNER)
            ->where(array('a.PORegisterId'=>$ReqId));
        $resourceStatement = $sql->getSqlStringForSqlObject($resourceSelect);
        $this->_view->register2 = $dbAdapter->query($resourceStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $viewRenderer->commonHelper()->commonFunctionality( $logArray = false, $shareArray = false, $requestArray = false, $reminderArray = false, $askArray = false, $feedArray = false, $activityStreamArray = false, $geoLocationArray = false, $approveArray = false );
        return $this->_view;
    }

    public function poAmendmentAction(){
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

        if($this->getRequest()->isXmlHttpRequest())	{
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Ajax post code here
                $postParam = $request->getPost();
                if ($postParam['Type'] == 'getVendor') {
                    $vendor = $this->bsf->isNullCheck($postParam['Supplier'], 'number');

                    $regSelect = $sql->select();
                    $regSelect->from(array("PR" => "MMS_PoRegister"))
                        ->columns(array(new Expression("PR.PoRegisterId,PoNo = Case When PR.POAmend=0 Then PR.PONo+'-A1'
                         Else  LEFT(PR.PONo, CHARINDEX('-A', PR.PONo))  + 'A'+ CAST((PR.POAmend+1) As Varchar(100)) End")))
                        ->join(array("SM" => "Vendor_Master"), "PR.VendorId=SM.VendorId", array(), $regSelect::JOIN_INNER)
                        ->where(array("PR.LivePO = 1 And PR.ShortClose=0 And PR.Approve='Y' And PR.VendorId= $vendor AND
                            PR.PORegisterId IN (Select PORegisterId From MMS_POTrans  Where
                            POTransId Not IN (Select POTransId From MMS_PVTrans WITH(READPAST) Where BillQty>0)And CancelQty=0)
                            And PR.PORegisterId NOT IN  (Select PORegisterId From MMS_RequestCancel)"))
                        ->order("PR.PoRegisterId Desc");
                    $regStatement = $sql->getSqlStringForSqlObject($regSelect);
                    $resData = $dbAdapter->query($regStatement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                }
                $this->_view->setTerminal(true);
                $response = $this->getResponse()->setContent(json_encode(array('request' => $resData)));
                return $response;
            }
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                //Write your Normal form post code here

            }

            // supplier
            $select = $sql->select();
            $select->from('Vendor_Master')
                ->columns(array('VendorId','VendorName'))
                ->where(array('Supply' => '1') );
            $statement = $sql->getSqlStringForSqlObject($select);
            $this->_view->arr_supplier = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //Common function
            $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);

            return $this->_view;
        }
    }

	public function poDetailsAction(){
		//$this->layout("layout/layout");
		$this->getServiceLocator()->get("ViewHelperManager")->get("HeadTitle")->set("Buildsuperfast");
		$viewRenderer = $this->serviceLocator->get("Zend\View\Renderer\RendererInterface");
		$dbAdapter = $this->serviceLocator->get("Zend\Db\Adapter\Adapter");
        $sql = new Sql($dbAdapter);

		if($this->getRequest()->isXmlHttpRequest())	{
			$request = $this->getRequest();
			if ($request->isPost()) {
				//Write your Ajax post code here

                $postParams = $request->getPost();
                $ccId = $postParams['costcentreid'];
                $porId = $postParams['poregId'];

                $select =  $sql -> select();
                $select->from(array("a" => "MMS_PORegister"))
                    ->columns(array(new Expression("Case When a.Approve='Y' Then 'Yes' When a.Approve='P' Then 'Partial' Else 'No' End As Approve")))
                    ->where("a.PORegisterId=$porId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $poAppDet = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select =  $sql -> select();
                $select->from(array("a" => "MMS_POTrans"))
                    ->columns(array(new Expression("Case When SUM(a.BalQty)>0 Then 'Yes' Else 'No' End As balAva")))
                    ->where("a.PORegisterId=$porId");
                $select->having("sum(a.balqty)>0");
                $statement = $sql->getSqlStringForSqlObject($select);
                $pobalAva = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();

                $select =  $sql -> select();
                $select->from(array("a" => "MMS_POTrans"))
                    ->columns(array(new Expression("Distinct Case When a.acceptqty>0 Then 'Yes' Else 'No' End As minOnly")))
                    ->where("a.PORegisterId=$porId");
                $statement = $sql->getSqlStringForSqlObject($select);
                $pominOnly = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->current();


                $this->_view->Approve=$poAppDet["Approve"];
                $this->_view->balAva=$pobalAva["balAva"];
                $this->_view->minOnly=$pominOnly["minOnly"];

                $select = $sql->select();
                $select->from(array("b" => "MMS_POTrans"))
                    ->columns(array(new Expression("CAST(b.POQty As Decimal(18,3)) As POQty,
                            CAST(b.CancelQty As Decimal(18,3)) As CancelQty,
                            CAST(b.DCQty As Decimal(18,3)) As DCQty,
                            CAST(b.AcceptQty As Decimal(18,3)) As AcceptQty,
                            CAST(b.RejectQty As Decimal(18,3)) As RejectQty,
                            CAST(b.BillQty As Decimal(18,3)) As BillQty,
                            CAST(b.BalQty As Decimal(18,3)) As BalQty,
                            CAST(b.QRate As Decimal(18,2)) As QRate,
                            CAST(b.QAmount As Decimal(18,2)) As QAmount,
                            f.UnitName As  UnitName,
                            Case When b.ItemId>0 Then '(' + e.ItemCode + ')' + ' ' + e.BrandName Else '(' + c.Code + ')' + ' ' + c.ResourceName End As ResourceName,
                           d.PORegisterId as PORegisterId,b.POTransId as POTransId")))
                    ->join(array("d"=>"MMS_PORegister"), "d.PORegisterId=b.PORegisterId", array(), $select::JOIN_INNER)
                    ->join(array("c"=>"proj_resource"), "b.resourceId=c.resourceId", array(), $select::JOIN_INNER)
                    ->join(array("e"=>"MMS_Brand"), "e.BrandId=b.ItemId and e.resourceId=b.resourceId", array(), $select::JOIN_LEFT)
                    ->join(array("f" => "Proj_UOM"), 'f.UnitId=b.UnitId', array(), $select::JOIN_LEFT)
                    ->where(array("d.PORegisterId = $porId and d.CostCentreId =  $ccId") );
                $statement = $sql->getSqlStringForSqlObject($select);
                $this->_view->poDetails = $dbAdapter->query($statement, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $this->_view->setTerminal(true);
                $viewRenderer->commonHelper()->commonFunctionality($logArray = false,$shareArray = false,$requestArray = false,$reminderArray = false,$askArray = false,$feedArray = false,$activityStreamArray = false,$geoLocationArray = false,$approveArray = false);
                return $this->_view;
			}
		}
	}
}